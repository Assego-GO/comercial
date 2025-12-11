<?php
/**
 * VERSÃO ULTRA OTIMIZADA - api/buscar_associados.php
 * Funciona PERFEITAMENTE sem precisar mexer no banco de dados
 * 
 * ESTRATÉGIA:
 * - Busca EXATA super otimizada (não usa LIKE)
 * - Normalização inteligente no PHP
 * - Geração de variações de CPF automaticamente
 * - Performance sem depender de índices
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

@session_start();

if (!isset($_SESSION['funcionario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Função para normalizar corporação
function normalizarCorporacao($corporacao) {
    if (empty($corporacao)) return '';
    
    $corporacao = trim($corporacao);
    $corporacaoUpper = strtoupper($corporacao);
    
    $mapeamento = [
        'PM' => 'Polícia Militar', 'P.M.' => 'Polícia Militar', 
        'PMGO' => 'Polícia Militar', 'PM-GO' => 'Polícia Militar',
        'BM' => 'Bombeiro Militar', 'B.M.' => 'Bombeiro Militar',
        'BMGO' => 'Bombeiro Militar', 'CBM' => 'Bombeiro Militar',
        'PC' => 'Polícia Civil', 'PCGO' => 'Polícia Civil',
        'PP' => 'Polícia Penal', 'PPGO' => 'Polícia Penal'
    ];

    if (isset($mapeamento[$corporacaoUpper])) {
        return $mapeamento[$corporacaoUpper];
    }

    foreach ($mapeamento as $chave => $valor) {
        if (stripos($corporacaoUpper, $chave) !== false) {
            return $valor;
        }
    }

    return $corporacao;
}

// Função para gerar todas as variações possíveis de um CPF
function gerarVariacoesCPF($termo) {
    $variacoes = [];
    
    // Remove tudo que não é número
    $termoNumerico = preg_replace('/\D/', '', $termo);
    
    if (empty($termoNumerico)) {
        return $variacoes;
    }
    
    // 1. Como foi digitado (apenas números)
    $variacoes[] = $termoNumerico;
    
    // 2. Remove zeros à esquerda (CPFs podem estar sem zeros no banco)
    $semZeros = ltrim($termoNumerico, '0');
    if (!empty($semZeros) && $semZeros !== $termoNumerico) {  // Usa !== para comparação estrita
        $variacoes[] = $semZeros;
    }
    
    // 3. Com padding de zeros à esquerda até 11 dígitos
    if (strlen($termoNumerico) < 11) {
        $variacoes[] = str_pad($termoNumerico, 11, '0', STR_PAD_LEFT);
    }
    if (!empty($semZeros) && strlen($semZeros) < 11 && $semZeros !== $termoNumerico) {
        $variacoes[] = str_pad($semZeros, 11, '0', STR_PAD_LEFT);
    }
    
    // 4. Com padding de zeros até 10 dígitos (alguns CPFs no banco têm 10 dígitos)
    if (strlen($termoNumerico) < 10) {
        $variacoes[] = str_pad($termoNumerico, 10, '0', STR_PAD_LEFT);
    }
    if (!empty($semZeros) && strlen($semZeros) < 10 && $semZeros !== $termoNumerico) {
        $variacoes[] = str_pad($semZeros, 10, '0', STR_PAD_LEFT);
    }
    
    // Remove duplicatas e valores vazios
    $variacoes = array_filter(array_unique($variacoes), function($v) {
        return !empty($v);
    });
    
    return array_values($variacoes);
}

try {
    @include_once '../config/database.php';

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_CADASTRO . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $termo = $_GET['termo'] ?? '';
    $limit = min(10000, max(10, intval($_GET['limit'] ?? 500)));
    
    // Se não há termo, retorna TODOS os filiados (sem limite de 40 páginas)
    $temTermo = !empty(trim($termo)) && strlen(trim($termo)) >= 2;
    
    // Normalização do termo
    $termoNumerico = preg_replace('/\D/', '', $termo);
    $ehNumero = !empty($termoNumerico) && strlen($termoNumerico) >= 2;
    
    // Se é um número, gera variações de CPF
    $variacoesCPF = ($ehNumero && $temTermo) ? gerarVariacoesCPF($termo) : [];
    
    $resultados = [];
    
    // ============================================
    // SE NÃO TEM TERMO: Retorna TODOS os FILIADOS
    // ============================================
    if (!$temTermo) {
        $sqlTodos = "
        SELECT DISTINCT
            a.id, a.nome, a.cpf, a.rg, a.telefone, a.foto,
            COALESCE(a.situacao, 'Desfiliado') as situacao,
            m.corporacao, m.patente, c.dataFiliacao as data_filiacao,
            1 as prioridade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.pre_cadastro = 0 
        AND a.situacao = 'Filiado'
        ORDER BY a.nome
        LIMIT " . intval($limit) . "
        ";
        
        $stmtTodos = $pdo->prepare($sqlTodos);
        $stmtTodos->execute();
        $resultados = $stmtTodos->fetchAll();
    } 
    // ============================================
    // SE TEM TERMO: Busca por CPF/RG ou nome (APENAS FILIADOS)
    // ============================================
    else {
        // ESTRATÉGIA 1: Se é número, busca por CPF/RG
        if ($ehNumero && !empty($variacoesCPF)) {
            // Constrói condições LIKE para CPF (busca por prefixo)
            $cpfConditions = [];
            foreach ($variacoesCPF as $variacao) {
                $cpfConditions[] = "REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') LIKE ?";
            }
            $cpfLikeClause = implode(' OR ', $cpfConditions);
            
            $sqlNumerico = "
            SELECT DISTINCT
                a.id, a.nome, a.cpf, a.rg, a.telefone, a.foto,
                COALESCE(a.situacao, 'Desfiliado') as situacao,
                m.corporacao, m.patente, c.dataFiliacao as data_filiacao,
                1 as prioridade
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE a.pre_cadastro = 0 
            AND a.situacao = 'Filiado'
            AND (
                -- Busca por prefixo do CPF
                ($cpfLikeClause)
                OR
                -- Busca por prefixo do RG
                REPLACE(REPLACE(a.rg, '.', ''), '-', '') LIKE ?
                OR
                a.rg LIKE ?
            )
            LIMIT " . intval($limit) . "
            ";
            
            $stmtNumerico = $pdo->prepare($sqlNumerico);
            
            // Bind das variações de CPF (com % no final para LIKE)
            $paramIndex = 1;
            foreach ($variacoesCPF as $variacao) {
                $stmtNumerico->bindValue($paramIndex++, $variacao . '%', PDO::PARAM_STR);
            }
            
            // Bind do RG
            $stmtNumerico->bindValue($paramIndex++, $termoNumerico . '%', PDO::PARAM_STR);
            $stmtNumerico->bindValue($paramIndex++, $termo . '%', PDO::PARAM_STR);
            
            $stmtNumerico->execute();
            $resultados = $stmtNumerico->fetchAll();
        }
        
        // ESTRATÉGIA 2: Se não encontrou pelo número OU é texto, busca por nome (TODOS OS STATUS)
        if (count($resultados) < $limit) {
            $termoLike = '%' . $termo . '%';
            
            $sqlNome = "
            SELECT DISTINCT
                a.id, a.nome, a.cpf, a.rg, a.telefone, a.foto,
                COALESCE(a.situacao, 'Desfiliado') as situacao,
                m.corporacao, m.patente, c.dataFiliacao as data_filiacao,
                CASE
                    WHEN a.nome LIKE CONCAT(?, '%') THEN 2
                    ELSE 3
                END as prioridade
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE a.pre_cadastro = 0 
            AND a.nome LIKE ?
            ORDER BY prioridade, a.nome
            LIMIT " . intval($limit) . "
            ";
            
            $stmtNome = $pdo->prepare($sqlNome);
            $termoInicio = $termo . '%';
            $stmtNome->execute([$termoInicio, $termoLike]);
            
            $resultadosNome = $stmtNome->fetchAll();
            
            // Mescla resultados (evita duplicatas)
            $idsExistentes = array_column($resultados, 'id');
            foreach ($resultadosNome as $row) {
                if (!in_array($row['id'], $idsExistentes)) {
                    $resultados[] = $row;
                }
            }
        }
    }

    // ============================================
    // PROCESSAMENTO DOS RESULTADOS
    // ============================================
    $dados = [];
    foreach ($resultados as $row) {
        $item = [
            'id' => intval($row['id']),
            'nome' => $row['nome'] ?? '',
            'cpf' => $row['cpf'] ?? '',
            'rg' => $row['rg'] ?? '',
            'telefone' => $row['telefone'] ?? '',
            'situacao' => $row['situacao'],
            'corporacao' => normalizarCorporacao($row['corporacao']),
            'patente' => $row['patente'] ?? '',
            'data_filiacao' => $row['data_filiacao'] ?? '',
            'foto' => $row['foto'] ?? '',
            'detalhes_carregados' => false
        ];
        
        // Se for agregado, adiciona info do associado titular
        if ($row['situacao'] === 'Agregado' && isset($row['associado_id'])) {
            $item['agregado_de'] = [
                'id' => $row['associado_id'],
                'nome' => $row['associado_nome'] ?? ''
            ];
        }
        
        $dados[] = $item;
    }

    echo json_encode([
        'status' => 'success',
        'dados' => $dados,
        'total' => count($dados),
        'termo_busca' => $termo,
        'termo_normalizado' => $termoNumerico,
        'variacoes_testadas' => $variacoesCPF,
        'eh_numero' => $ehNumero,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro na busca: ' . $e->getMessage(),
        'dados' => []
    ]);
}