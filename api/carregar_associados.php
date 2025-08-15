<?php
/**
 * Script otimizado para carregar dados dos associados
 * api/carregar_associados.php
 */

// Desabilita erros de exibição
error_reporting(0);
ini_set('display_errors', '0');

// Aumenta limite de memória e tempo
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '300');

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Função para enviar resposta
function sendResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Inicia sessão
@session_start();

// Verifica autenticação
if (!isset($_SESSION['funcionario_id'])) {
    sendResponse([
        'status' => 'error',
        'message' => 'Não autorizado',
        'total' => 0,
        'dados' => []
    ]);
}

// Função para normalizar nome da corporação
function normalizarCorporacao($corporacao) {
    if (empty($corporacao)) return '';
    
    // Remove espaços extras e converte para maiúsculas para comparação
    $corporacao = trim($corporacao);
    $corporacao = preg_replace('/\s+/', ' ', $corporacao); // Remove espaços múltiplos
    
    // Cria uma versão em maiúsculas sem acentos para comparação
    $corporacaoUpper = strtoupper($corporacao);
    $corporacaoUpper = str_replace(
        ['Á','À','Ã','Â','É','È','Ê','Í','Ì','Î','Ó','Ò','Õ','Ô','Ú','Ù','Û','Ç'],
        ['A','A','A','A','E','E','E','I','I','I','O','O','O','O','U','U','U','C'],
        $corporacaoUpper
    );
    
    // Mapeamento de TODAS as possíveis variações para nomes padronizados
    $mapeamento = [
        // Polícia Militar - todas as variações
        'PM' => 'Polícia Militar',
        'P.M.' => 'Polícia Militar',
        'P.M' => 'Polícia Militar',
        'PMGO' => 'Polícia Militar',
        'PM-GO' => 'Polícia Militar',
        'PM GO' => 'Polícia Militar',
        'PM/GO' => 'Polícia Militar',
        'POLICIA MILITAR' => 'Polícia Militar',
        'POLÍCIA MILITAR' => 'Polícia Militar',
        'POLICIA MILITAR DE GOIAS' => 'Polícia Militar',
        'POLÍCIA MILITAR DE GOIÁS' => 'Polícia Militar',
        'POLICIA MILITAR DO ESTADO DE GOIAS' => 'Polícia Militar',
        
        // Bombeiro Militar - todas as variações
        'BM' => 'Bombeiro Militar',
        'B.M.' => 'Bombeiro Militar',
        'B.M' => 'Bombeiro Militar',
        'BMGO' => 'Bombeiro Militar',
        'BM-GO' => 'Bombeiro Militar',
        'BM GO' => 'Bombeiro Militar',
        'BM/GO' => 'Bombeiro Militar',
        'BOMBEIRO' => 'Bombeiro Militar',
        'BOMBEIROS' => 'Bombeiro Militar',
        'BOMBEIRO MILITAR' => 'Bombeiro Militar',
        'BOMBEIROS MILITAR' => 'Bombeiro Militar',
        'BOMBEIROS MILITARES' => 'Bombeiro Militar',
        'CBM' => 'Bombeiro Militar',
        'CBMGO' => 'Bombeiro Militar',
        'CBM-GO' => 'Bombeiro Militar',
        'CBM GO' => 'Bombeiro Militar',
        'CORPO DE BOMBEIROS' => 'Bombeiro Militar',
        'CORPO DE BOMBEIROS MILITAR' => 'Bombeiro Militar',
        'CORPO DE BOMBEIROS MILITAR DE GOIAS' => 'Bombeiro Militar',
        'CORPO DE BOMBEIROS MILITAR DO ESTADO DE GOIAS' => 'Bombeiro Militar',
        
        // Polícia Civil - todas as variações  
        'PC' => 'Polícia Civil',
        'P.C.' => 'Polícia Civil',
        'P.C' => 'Polícia Civil',
        'PCGO' => 'Polícia Civil',
        'PC-GO' => 'Polícia Civil',
        'PC GO' => 'Polícia Civil',
        'PC/GO' => 'Polícia Civil',
        'POLICIA CIVIL' => 'Polícia Civil',
        'POLÍCIA CIVIL' => 'Polícia Civil',
        'POLICIA CIVIL DE GOIAS' => 'Polícia Civil',
        'POLÍCIA CIVIL DE GOIÁS' => 'Polícia Civil',
        'POLICIA CIVIL DO ESTADO DE GOIAS' => 'Polícia Civil',
        
        // Polícia Penal - todas as variações
        'PP' => 'Polícia Penal',
        'P.P.' => 'Polícia Penal',
        'P.P' => 'Polícia Penal',
        'PPGO' => 'Polícia Penal',
        'PP-GO' => 'Polícia Penal',
        'PP GO' => 'Polícia Penal',
        'PP/GO' => 'Polícia Penal',
        'POLICIA PENAL' => 'Polícia Penal',
        'POLÍCIA PENAL' => 'Polícia Penal',
        'POLICIA PENAL DE GOIAS' => 'Polícia Penal',
        'POLÍCIA PENAL DE GOIÁS' => 'Polícia Penal',
        'AGEPEN' => 'Polícia Penal',
        'DGAP' => 'Polícia Penal',
        'DIRETORIA GERAL DE ADMINISTRACAO PENITENCIARIA' => 'Polícia Penal',
        'DIRETORIA-GERAL DE ADMINISTRAÇÃO PENITENCIÁRIA' => 'Polícia Penal'
    ];
    
    // Verifica se existe no mapeamento (usando a versão em maiúsculas sem acentos)
    if (isset($mapeamento[$corporacaoUpper])) {
        return $mapeamento[$corporacaoUpper];
    }
    
    // Se não encontrar exatamente, tenta variações parciais
    foreach ($mapeamento as $chave => $valor) {
        if (stripos($corporacaoUpper, $chave) !== false) {
            return $valor;
        }
    }
    
    // Se ainda não encontrar, retorna com capitalização correta
    $palavras = explode(' ', mb_strtolower($corporacao, 'UTF-8'));
    $palavrasPadronizadas = array_map(function($palavra) {
        // Palavras que devem ficar em minúsculas
        $minusculas = ['de', 'da', 'do', 'dos', 'das', 'e', 'em'];
        if (in_array($palavra, $minusculas)) {
            return $palavra;
        }
        return mb_convert_case($palavra, MB_CASE_TITLE, 'UTF-8');
    }, $palavras);
    
    $resultado = implode(' ', $palavrasPadronizadas);
    
    // Correções finais para acentuação
    $resultado = str_replace(
        ['Policia', 'Policia', 'Goias'],
        ['Polícia', 'Polícia', 'Goiás'],
        $resultado
    );
    
    return $resultado;
}

try {
    // Carrega configurações
    @include_once '../config/database.php';
    
    // Verifica constantes
    if (!defined('DB_HOST') || !defined('DB_NAME_CADASTRO')) {
        throw new Exception('Configurações não encontradas');
    }
    
    // Conexão com configurações otimizadas
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_CADASTRO . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Desabilita temporariamente o ONLY_FULL_GROUP_BY para esta sessão
    $pdo->exec("SET SESSION sql_mode = REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', '')");
    
    // Primeiro, conta o total de registros
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM Associados");
    $totalRegistros = $countStmt->fetch()['total'];
    
    // Se houver muitos registros, limita a consulta
    $limite = $totalRegistros > 5000 ? 5000 : $totalRegistros;
    
    // CORRIGIDO: Removida f.observacoes e adicionada f.id_neoconsig
    $sql = "
        SELECT DISTINCT
            a.id,
            a.nome,
            a.cpf,
            a.foto,
            a.rg,
            a.email,
            a.telefone,
            a.nasc,
            a.sexo,
            COALESCE(a.situacao, 'Desfiliado') as situacao,
            a.escolaridade,
            a.estadoCivil,
            a.indicacao,
            a.pre_cadastro,
            MAX(m.corporacao) as corporacao,
            MAX(m.patente) as patente,
            MAX(m.categoria) as categoria,
            MAX(m.lotacao) as lotacao,
            MAX(m.unidade) as unidade,
            MAX(f.tipoAssociado) as tipoAssociado,
            MAX(f.situacaoFinanceira) as situacaoFinanceira,
            MAX(f.vinculoServidor) as vinculoServidor,
            MAX(f.localDebito) as localDebito,
            MAX(f.agencia) as agencia,
            MAX(f.operacao) as operacao,
            MAX(f.contaCorrente) as contaCorrente,
            MAX(f.id_neoconsig) as id_neoconsig,
            MAX(f.doador) as doador,
            MAX(e.cep) as cep,
            MAX(e.endereco) as endereco,
            MAX(e.bairro) as bairro,
            MAX(e.cidade) as cidade,
            MAX(e.numero) as numero,
            MAX(e.complemento) as complemento,
            MAX(c.dataFiliacao) as data_filiacao,
            MAX(c.dataDesfiliacao) as data_desfiliacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Endereco e ON a.id = e.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        GROUP BY 
            a.id,
            a.nome,
            a.cpf,
            a.rg,
            a.email,
            a.telefone,
            a.nasc,
            a.sexo,
            a.situacao,
            a.escolaridade,
            a.estadoCivil,
            a.indicacao,
            a.pre_cadastro,
            a.foto
        ORDER BY a.id DESC
        LIMIT " . $limite;
    
    $stmt = $pdo->query($sql);
    
    // Processa os dados e remove duplicatas no PHP também
    $dados = [];
    $idsProcessados = [];
    $associadosIds = [];
    
    while ($row = $stmt->fetch()) {
        // Evita duplicatas verificando o ID
        if (in_array($row['id'], $idsProcessados)) {
            continue;
        }
        $idsProcessados[] = $row['id'];
        $associadosIds[] = $row['id']; // Salva os IDs para buscar dependentes depois
        
        // Trata a corporação para exibir nome completo
        $corporacao = normalizarCorporacao($row['corporacao']);
        
        // CORRIGIDO: Removida observacoes e adicionada id_neoconsig
        $dados[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome'] ?? '',
            'cpf' => $row['cpf'] ?? '',
            'rg' => $row['rg'] ?? '',
            'email' => $row['email'] ?? '',
            'telefone' => $row['telefone'] ?? '',
            'nasc' => $row['nasc'] ?? '',
            'sexo' => $row['sexo'] ?? '',
            'situacao' => $row['situacao'],
            'situacao_debug' => 'Original: ' . ($row['situacao'] ?? 'NULL') . ' | Row: ' . json_encode($row),
            'escolaridade' => $row['escolaridade'] ?? '',
            'estadoCivil' => $row['estadoCivil'] ?? '',
            'foto' => $row['foto'] ?? '',
            'indicacao' => $row['indicacao'] ?? '',
            'pre_cadastro' => $row['pre_cadastro'] ?? 0,
            'corporacao' => $corporacao,
            'patente' => $row['patente'] ?? '',
            'categoria' => $row['categoria'] ?? '',
            'lotacao' => $row['lotacao'] ?? '',
            'unidade' => $row['unidade'] ?? '',
            'tipoAssociado' => $row['tipoAssociado'] ?? '',
            'situacaoFinanceira' => $row['situacaoFinanceira'] ?? '',
            'vinculoServidor' => $row['vinculoServidor'] ?? '',
            'localDebito' => $row['localDebito'] ?? '',
            'agencia' => $row['agencia'] ?? '',
            'operacao' => $row['operacao'] ?? '',
            'contaCorrente' => $row['contaCorrente'] ?? '',
            'id_neoconsig' => $row['id_neoconsig'] ?? '',
            'doador' => intval($row['doador'] ?? 0),
            'cep' => $row['cep'] ?? '',
            'endereco' => $row['endereco'] ?? '',
            'bairro' => $row['bairro'] ?? '',
            'cidade' => $row['cidade'] ?? '',
            'numero' => $row['numero'] ?? '',
            'complemento' => $row['complemento'] ?? '',
            'data_filiacao' => $row['data_filiacao'] ?? '',
            'data_desfiliacao' => $row['data_desfiliacao'] ?? '',
            'dependentes' => [],
            'total_dependentes' => 0,
            'total_servicos' => 0,
            'total_documentos' => 0,
            'total_observacoes' => 0,
            'tem_observacoes_importantes' => false,
            'redesSociais' => [],
            'servicos' => [],
            'documentos' => []
        ];
    }
    
    // Busca dados adicionais para todos os associados de uma vez
    if (!empty($associadosIds)) {
        $placeholders = str_repeat('?,', count($associadosIds) - 1) . '?';
        
        // Busca os dependentes
        $sqlDependentes = "
            SELECT 
                associado_id,
                nome,
                data_nascimento,
                parentesco,
                sexo
            FROM Dependentes
            WHERE associado_id IN ($placeholders)
            ORDER BY associado_id, nome
        ";
        
        $stmtDep = $pdo->prepare($sqlDependentes);
        $stmtDep->execute($associadosIds);
        
        $dependentesPorAssociado = [];
        while ($dep = $stmtDep->fetch()) {
            if (!isset($dependentesPorAssociado[$dep['associado_id']])) {
                $dependentesPorAssociado[$dep['associado_id']] = [];
            }
            $dependentesPorAssociado[$dep['associado_id']][] = [
                'nome' => $dep['nome'] ?? '',
                'data_nascimento' => $dep['data_nascimento'] ?? '',
                'parentesco' => $dep['parentesco'] ?? '',
                'sexo' => $dep['sexo'] ?? ''
            ];
        }
        
        // Busca a contagem de serviços ativos
        $sqlServicos = "
            SELECT 
                associado_id,
                COUNT(*) as total
            FROM Servicos_Associado
            WHERE associado_id IN ($placeholders)
            AND ativo = 1
            GROUP BY associado_id
        ";
        
        $stmtServ = $pdo->prepare($sqlServicos);
        $stmtServ->execute($associadosIds);
        
        $servicosPorAssociado = [];
        while ($serv = $stmtServ->fetch()) {
            $servicosPorAssociado[$serv['associado_id']] = $serv['total'];
        }
        
        // Busca a contagem de documentos
        $sqlDocumentos = "
            SELECT 
                associado_id,
                COUNT(*) as total
            FROM Documentos_Associado
            WHERE associado_id IN ($placeholders)
            GROUP BY associado_id
        ";
        
        $stmtDoc = $pdo->prepare($sqlDocumentos);
        $stmtDoc->execute($associadosIds);
        
        $documentosPorAssociado = [];
        while ($doc = $stmtDoc->fetch()) {
            $documentosPorAssociado[$doc['associado_id']] = $doc['total'];
        }
        
        // NOVO: Busca a contagem de observações e observações importantes
        $sqlObservacoes = "
            SELECT 
                associado_id,
                COUNT(*) as total_observacoes,
                SUM(CASE WHEN importante = 1 THEN 1 ELSE 0 END) as observacoes_importantes,
                SUM(CASE WHEN categoria = 'pendencia' THEN 1 ELSE 0 END) as pendencias
            FROM Observacoes_Associado
            WHERE associado_id IN ($placeholders)
            AND ativo = 1
            GROUP BY associado_id
        ";
        
        $stmtObs = $pdo->prepare($sqlObservacoes);
        $stmtObs->execute($associadosIds);
        
        $observacoesPorAssociado = [];
        while ($obs = $stmtObs->fetch()) {
            $observacoesPorAssociado[$obs['associado_id']] = [
                'total' => intval($obs['total_observacoes']),
                'importantes' => intval($obs['observacoes_importantes']),
                'pendencias' => intval($obs['pendencias'])
            ];
        }
        
        // Adiciona os dados aos associados
        foreach ($dados as &$associado) {
            $id = $associado['id'];
            
            // Adiciona dependentes
            if (isset($dependentesPorAssociado[$id])) {
                $associado['dependentes'] = $dependentesPorAssociado[$id];
                $associado['total_dependentes'] = count($dependentesPorAssociado[$id]);
            }
            
            // Adiciona total de serviços
            if (isset($servicosPorAssociado[$id])) {
                $associado['total_servicos'] = intval($servicosPorAssociado[$id]);
            }
            
            // Adiciona total de documentos
            if (isset($documentosPorAssociado[$id])) {
                $associado['total_documentos'] = intval($documentosPorAssociado[$id]);
            }
            
            // NOVO: Adiciona dados de observações
            if (isset($observacoesPorAssociado[$id])) {
                $associado['total_observacoes'] = $observacoesPorAssociado[$id]['total'];
                $associado['tem_observacoes_importantes'] = $observacoesPorAssociado[$id]['importantes'] > 0;
                $associado['total_observacoes_importantes'] = $observacoesPorAssociado[$id]['importantes'];
                $associado['total_pendencias'] = $observacoesPorAssociado[$id]['pendencias'];
            }
        }
    }
    
    // Libera recursos
    $stmt->closeCursor();
    $stmt = null;
    if (isset($stmtDep)) {
        $stmtDep->closeCursor();
        $stmtDep = null;
    }
    if (isset($stmtServ)) {
        $stmtServ->closeCursor();
        $stmtServ = null;
    }
    if (isset($stmtDoc)) {
        $stmtDoc->closeCursor();
        $stmtDoc = null;
    }
    if (isset($stmtObs)) {
        $stmtObs->closeCursor();
        $stmtObs = null;
    }
    $pdo = null;
    
    // Adiciona um array para armazenar corporações únicas normalizadas
    $corporacoesUnicas = [];
    
    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'total' => count($dados),
        'dados' => $dados,
        'corporacoes_unicas' => array_values(array_unique(array_filter(array_column($dados, 'corporacao')))),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($totalRegistros > $limite) {
        $response['aviso'] = "Mostrando apenas os $limite registros mais recentes de $totalRegistros total";
    }
    
    sendResponse($response);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro em carregar_associados.php: " . $e->getMessage());
    
    // Resposta de erro
    sendResponse([
        'status' => 'error',
        'message' => 'Erro ao carregar dados',
        'total' => 0,
        'dados' => [],
        'error' => $e->getMessage()
    ]);
}
?>