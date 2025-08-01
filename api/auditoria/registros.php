<?php
/**
 * API para listagem de registros de auditoria
 * /api/auditoria/registros.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configurar tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }

    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    // Preparar filtros
    $whereConditions = [];
    $params = [];
    
    // Filtro por tabela
    if (!empty($_GET['tabela'])) {
        $whereConditions[] = "a.tabela = :tabela";
        $params[':tabela'] = $_GET['tabela'];
    }
    
    // Filtro por ação
    if (!empty($_GET['acao'])) {
        $whereConditions[] = "a.acao = :acao";
        $params[':acao'] = $_GET['acao'];
    }
    
    // Filtro por funcionário
    if (!empty($_GET['funcionario_id'])) {
        $whereConditions[] = "a.funcionario_id = :funcionario_id";
        $params[':funcionario_id'] = (int)$_GET['funcionario_id'];
    }
    
    // Filtro por associado
    if (!empty($_GET['associado_id'])) {
        $whereConditions[] = "a.associado_id = :associado_id";
        $params[':associado_id'] = (int)$_GET['associado_id'];
    }
    
    // Filtro por data início
    if (!empty($_GET['data_inicio'])) {
        $whereConditions[] = "DATE(a.data_hora) >= :data_inicio";
        $params[':data_inicio'] = $_GET['data_inicio'];
    }
    
    // Filtro por data fim
    if (!empty($_GET['data_fim'])) {
        $whereConditions[] = "DATE(a.data_hora) <= :data_fim";
        $params[':data_fim'] = $_GET['data_fim'];
    }
    
    // Filtro por busca (funcionário ou tabela)
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(f.nome LIKE :search OR a.tabela LIKE :search2)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
    }
    
    // Construir cláusula WHERE
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // === BUSCAR REGISTROS ===
    
    $sql = "
        SELECT 
            a.id,
            a.tabela,
            a.acao,
            a.registro_id,
            a.data_hora,
            a.ip_origem,
            a.browser_info,
            a.sessao_id,
            a.alteracoes,
            a.associado_id,
            f.nome as funcionario_nome,
            f.email as funcionario_email,
            f.cargo as funcionario_cargo,
            ass.nome as associado_nome,
            ass.cpf as associado_cpf
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id
        $whereClause
        ORDER BY a.data_hora DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    
    // Bind dos parâmetros
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // === CONTAR TOTAL DE REGISTROS ===
    
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id
        $whereClause
    ";
    
    $stmtCount = $db->prepare($sqlCount);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $stmtCount->bindValue($key, $value);
        }
    }
    $stmtCount->execute();
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPaginas = ceil($totalRegistros / $limit);
    
    // === PROCESSAR REGISTROS ===
    
    $registrosProcessados = [];
    foreach ($registros as $registro) {
        $processado = [
            'id' => (int)$registro['id'],
            'tabela' => $registro['tabela'],
            'acao' => $registro['acao'],
            'registro_id' => $registro['registro_id'],
            'data_hora' => $registro['data_hora'],
            'ip_origem' => $registro['ip_origem'],
            'browser_info' => $registro['browser_info'],
            'sessao_id' => $registro['sessao_id'],
            'alteracoes' => $registro['alteracoes'],
            'funcionario_nome' => $registro['funcionario_nome'] ?: 'Sistema',
            'funcionario_email' => $registro['funcionario_email'],
            'funcionario_cargo' => $registro['funcionario_cargo'],
            'associado_id' => $registro['associado_id'],
            'associado_nome' => $registro['associado_nome'],
            'associado_cpf' => $registro['associado_cpf']
        ];
        
        // Formatar data
        if ($processado['data_hora']) {
            $processado['data_hora_formatada'] = date('d/m/Y H:i:s', strtotime($processado['data_hora']));
        }
        
        // Decodificar alterações se existir
        if (!empty($processado['alteracoes'])) {
            try {
                $processado['alteracoes_decoded'] = json_decode($processado['alteracoes'], true);
            } catch (Exception $e) {
                $processado['alteracoes_decoded'] = null;
            }
        } else {
            $processado['alteracoes_decoded'] = null;
        }
        
        $registrosProcessados[] = $processado;
    }
    
    // === PREPARAR DADOS DE PAGINAÇÃO ===
    
    $paginacao = [
        'pagina_atual' => $page,
        'total_paginas' => $totalPaginas,
        'total_registros' => (int)$totalRegistros,
        'registros_por_pagina' => $limit,
        'tem_proxima' => $page < $totalPaginas,
        'tem_anterior' => $page > 1,
        'primeira_pagina' => 1,
        'ultima_pagina' => $totalPaginas
    ];
    
    // === FILTROS APLICADOS ===
    
    $filtrosAplicados = [];
    foreach (['tabela', 'acao', 'funcionario_id', 'associado_id', 'data_inicio', 'data_fim', 'search'] as $filtro) {
        if (!empty($_GET[$filtro])) {
            $filtrosAplicados[$filtro] = $_GET[$filtro];
        }
    }
    
    // === RESPOSTA DE SUCESSO ===
    
    $response = [
        'status' => 'success',
        'message' => 'Registros obtidos com sucesso',
        'data' => [
            'registros' => $registrosProcessados,
            'paginacao' => $paginacao,
            'filtros_aplicados' => $filtrosAplicados,
            'resumo' => [
                'total_encontrados' => count($registrosProcessados),
                'pagina_atual' => $page,
                'total_geral' => (int)$totalRegistros
            ]
        ],
        'meta' => [
            'tempo_execucao' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'timestamp' => time(),
            'versao_api' => '1.0'
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    // Erro específico do banco de dados
    error_log("Erro PDO na API de registros de auditoria: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de banco de dados',
        'error_code' => 'DB_ERROR_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Outros erros
    error_log("Erro na API de registros de auditoria: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'error_code' => 'GENERAL_ERROR_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Error $e) {
    // Erros fatais do PHP
    error_log("Erro fatal na API de registros: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro fatal do sistema',
        'error_code' => 'FATAL_ERROR_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>