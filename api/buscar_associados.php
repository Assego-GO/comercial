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

// Habilita logs temporariamente para debug
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/buscar_associados_debug.log');

header('Content-Type: application/json; charset=utf-8');

@session_start();

error_log("=== BUSCAR ASSOCIADOS INICIADO ===");
error_log("Termo recebido: " . ($_GET['termo'] ?? 'VAZIO'));
error_log("Session funcionario_id: " . ($_SESSION['funcionario_id'] ?? 'NÃO LOGADO'));

if (!isset($_SESSION['funcionario_id'])) {
    error_log("ERRO: Usuário não autenticado");
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
    
    // 2. Se tem menos de 11 dígitos, adiciona com padding de zeros à esquerda
    if (strlen($termoNumerico) < 11) {
        $variacoes[] = str_pad($termoNumerico, 11, '0', STR_PAD_LEFT);
    }
    
    // 3. Remove zeros à esquerda e adiciona novamente com padding
    $semZeros = ltrim($termoNumerico, '0');
    if (!empty($semZeros) && $semZeros != $termoNumerico) {
        $variacoes[] = $semZeros;
        $variacoes[] = str_pad($semZeros, 11, '0', STR_PAD_LEFT);
    }
    
    // 4. Se digitou 10 dígitos, tenta com '0' e '1' no final
    if (strlen($termoNumerico) == 10) {
        $variacoes[] = $termoNumerico . '0';
        $variacoes[] = $termoNumerico . '1';
    }
    
    // Remove duplicatas
    return array_unique($variacoes);
}

try {
    error_log("Tentando conectar ao banco de dados...");
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
    
    error_log("Conexão com banco estabelecida com sucesso");

    $termo = $_GET['termo'] ?? '';
    $limit = min(500, max(10, intval($_GET['limit'] ?? 200)));
    
    error_log("Termo normalizado: '$termo', Limit: $limit");
    
    if (strlen($termo) < 2) {
        error_log("ERRO: Termo muito curto");
        echo json_encode([
            'status' => 'error',
            'message' => 'Termo deve ter pelo menos 2 caracteres'
        ]);
        exit;
    }

    // Normalização do termo
    $termoNumerico = preg_replace('/\D/', '', $termo);
    $ehNumero = !empty($termoNumerico) && strlen($termoNumerico) >= 2;
    
    error_log("Termo numérico: '$termoNumerico', É número: " . ($ehNumero ? 'SIM' : 'NÃO'));
    
    // Se é um número, gera variações de CPF
    $variacoesCPF = $ehNumero ? gerarVariacoesCPF($termo) : [];
    
    error_log("Variações de CPF geradas: " . json_encode($variacoesCPF));
    
    $resultados = [];
    
    // ============================================
    // ESTRATÉGIA 1: Se é número, busca por CPF/RG
    // ============================================
    if ($ehNumero && !empty($variacoesCPF)) {
        error_log("Iniciando busca numérica (CPF/RG)...");
        
        // Monta placeholders para as variações
        $placeholders = implode(',', array_fill(0, count($variacoesCPF), '?'));
        
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
        AND (
            -- Busca exata por CPF (todas as variações)
            REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') IN ($placeholders)
            OR
            -- Busca exata por RG
            REPLACE(REPLACE(a.rg, '.', ''), '-', '') = ?
            OR
            a.rg = ?
        )
        LIMIT $limit
        ";
        
        error_log("SQL montado: " . $sqlNumerico);
        
        $stmtNumerico = $pdo->prepare($sqlNumerico);
        
        // Bind das variações de CPF
        $paramIndex = 1;
        foreach ($variacoesCPF as $variacao) {
            error_log("Binding CPF variação #$paramIndex: $variacao");
            $stmtNumerico->bindValue($paramIndex++, $variacao, PDO::PARAM_STR);
        }
        
        // Bind do RG
        error_log("Binding RG #$paramIndex: $termoNumerico");
        $stmtNumerico->bindValue($paramIndex++, $termoNumerico, PDO::PARAM_STR);
        error_log("Binding RG #$paramIndex: $termo");
        $stmtNumerico->bindValue($paramIndex++, $termo, PDO::PARAM_STR);
        // REMOVIDO: Não faz bind do LIMIT, já está na query
        
        error_log("Executando query numérica...");
        $stmtNumerico->execute();
        $resultados = $stmtNumerico->fetchAll();
        error_log("Resultados encontrados na busca numérica: " . count($resultados));
    }
    
    // ============================================
    // ESTRATÉGIA 2: Se não achou ou é texto, busca por nome
    // ============================================
    if (count($resultados) < 5) {
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
        LIMIT $limit
        ";
        
        $stmtNome = $pdo->prepare($sqlNome);
        $stmtNome->execute([$termo, $termoLike]);
        
        $resultadosNome = $stmtNome->fetchAll();
        
        // Mescla resultados (evita duplicatas)
        $idsExistentes = array_column($resultados, 'id');
        foreach ($resultadosNome as $row) {
            if (!in_array($row['id'], $idsExistentes)) {
                $resultados[] = $row;
            }
        }
    }

    // ============================================
    // PROCESSAMENTO DOS RESULTADOS
    // ============================================
    $dados = [];
    foreach ($resultados as $row) {
        $dados[] = [
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
    }

    error_log("Total de resultados processados: " . count($dados));
    error_log("Primeiros dados: " . json_encode(array_slice($dados, 0, 2)));

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
    
    error_log("=== BUSCAR ASSOCIADOS FINALIZADO COM SUCESSO ===");

} catch (Exception $e) {
    error_log("!!! ERRO CRÍTICO: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro na busca: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'dados' => []
    ]);
}