<?php
/**
 * API para Listar Documentos de Associados
 * api/documentos_associados.php
 * 
 * Lista dados REAIS da tabela Documentos_Associado
 * Retorna JSON válido sempre
 */

// ===== CONFIGURAÇÃO PARA JSON LIMPO =====
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers obrigatórios para JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buffer para capturar saída indesejada
ob_start();

// Função para resposta JSON limpa
function jsonResponse($status, $message, $data = null, $code = 200) {
    // Limpar buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($code);
    
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Log de debug
function debugLog($message) {
    error_log("[DOCS_API] " . $message);
}

try {
    debugLog("=== INICIANDO API DOCUMENTOS ASSOCIADOS ===");
    
    // ===== INCLUDES SEGUROS =====
    $requiredFiles = [
        '../config/config.php',
        '../config/database.php', 
        '../classes/Database.php',
        '../classes/Auth.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Arquivo não encontrado: $file");
        }
        require_once $file;
    }
    
    debugLog("✅ Arquivos incluídos");
    
    // ===== AUTENTICAÇÃO =====
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonResponse('error', 'Usuário não autenticado', null, 401);
    }

    $usuarioLogado = $auth->getUser();
    debugLog("✅ Usuário: " . ($usuarioLogado['nome'] ?? 'N/A'));
    
    // ===== CONEXÃO COM BANCO =====
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception("Falha na conexão com banco de dados");
    }
    
    debugLog("✅ Conectado ao banco");
    
    // ===== PARÂMETROS DA REQUISIÇÃO =====
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $tipo_filter = $_GET['tipo'] ?? '';
    $origem_filter = $_GET['origem'] ?? '';
    
    debugLog("Parâmetros: page=$page, limit=$limit, search='$search'");
    
    // ===== CONSTRUIR QUERY BASE =====
    $baseQuery = "
        SELECT 
            da.id,
            da.associado_id,
            da.tipo_documento,
            da.tipo_origem,
            da.nome_arquivo,
            da.caminho_arquivo,
            da.verificado,
            da.status_fluxo,
            da.data_upload,
            da.data_envio_assinatura,
            da.data_assinatura,
            da.data_finalizacao,
            da.observacao,
            da.observacoes_fluxo,
            
            -- Dados do Associado
            COALESCE(a.nome, CONCAT('Associado #', da.associado_id)) as nome_associado,
            a.cpf as cpf_associado,
            a.matricula as matricula_associado,
            
            -- Dados do Funcionário que fez upload
            COALESCE(f.nome, 'Sistema') as funcionario_upload,
            
            -- Dados do Departamento Atual
            COALESCE(d.nome, 'N/A') as departamento_atual_nome,
            
            -- Dados de quem assinou
            COALESCE(fa.nome, '') as assinado_por_nome
            
        FROM Documentos_Associado da
        
        -- JOINs opcionais para buscar dados relacionados
        LEFT JOIN Associados a ON da.associado_id = a.id
        LEFT JOIN Funcionarios f ON da.funcionario_id = f.id
        LEFT JOIN Departamentos d ON da.departamento_atual = d.id
        LEFT JOIN Funcionarios fa ON da.assinado_por = fa.id
    ";
    
    // ===== CONSTRUIR WHERE =====
    $whereConditions = [];
    $params = [];
    
    // Filtro de busca (nome do associado, nome do arquivo, observações)
    if (!empty($search)) {
        $whereConditions[] = "(
            a.nome LIKE :search OR 
            da.nome_arquivo LIKE :search OR 
            da.observacao LIKE :search OR
            da.observacoes_fluxo LIKE :search OR
            a.cpf LIKE :search OR
            a.matricula LIKE :search
        )";
        $params[':search'] = "%$search%";
    }
    
    // Filtro por status
    if (!empty($status_filter)) {
        $whereConditions[] = "da.status_fluxo = :status";
        $params[':status'] = $status_filter;
    }
    
    // Filtro por tipo de documento
    if (!empty($tipo_filter)) {
        $whereConditions[] = "da.tipo_documento = :tipo";
        $params[':tipo'] = $tipo_filter;
    }
    
    // Filtro por origem (FISICO/VIRTUAL)
    if (!empty($origem_filter)) {
        $whereConditions[] = "da.tipo_origem = :origem";
        $params[':origem'] = $origem_filter;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // ===== CONTAR TOTAL DE REGISTROS =====
    $countQuery = "
        SELECT COUNT(DISTINCT da.id) as total
        FROM Documentos_Associado da
        LEFT JOIN Associados a ON da.associado_id = a.id
        $whereClause
    ";
    
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    debugLog("Total de registros: $totalRecords");
    
    // ===== BUSCAR DADOS COM PAGINAÇÃO =====
    $fullQuery = $baseQuery . $whereClause . " 
        ORDER BY da.data_upload DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($fullQuery);
    
    // Bind dos parâmetros de filtro
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind dos parâmetros de paginação
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    debugLog("Registros encontrados: " . count($documentos));
    
    // ===== PROCESSAR DADOS PARA RESPOSTA =====
    $processedData = array_map(function($doc) {
        return [
            'id' => (int)$doc['id'],
            'associado' => [
                'id' => (int)$doc['associado_id'],
                'nome' => $doc['nome_associado'] ?? 'N/A',
                'cpf' => $doc['cpf_associado'] ?? '',
                'matricula' => $doc['matricula_associado'] ?? ''
            ],
            'documento' => [
                'tipo' => $doc['tipo_documento'],
                'origem' => $doc['tipo_origem'],
                'nome_arquivo' => $doc['nome_arquivo'],
                'caminho' => $doc['caminho_arquivo'],
                'verificado' => (bool)$doc['verificado']
            ],
            'fluxo' => [
                'status' => $doc['status_fluxo'],
                'departamento_atual' => $doc['departamento_atual_nome'],
                'assinado_por' => $doc['assinado_por_nome'] ?: null
            ],
            'datas' => [
                'upload' => $doc['data_upload'],
                'envio_assinatura' => $doc['data_envio_assinatura'],
                'assinatura' => $doc['data_assinatura'],
                'finalizacao' => $doc['data_finalizacao']
            ],
            'observacoes' => [
                'geral' => $doc['observacao'] ?: '',
                'fluxo' => $doc['observacoes_fluxo'] ?: ''
            ],
            'funcionario_upload' => $doc['funcionario_upload']
        ];
    }, $documentos);
    
    // ===== ESTATÍSTICAS ADICIONAIS =====
    $statsQuery = "
        SELECT 
            da.status_fluxo,
            COUNT(*) as total
        FROM Documentos_Associado da
        LEFT JOIN Associados a ON da.associado_id = a.id
        $whereClause
        GROUP BY da.status_fluxo
    ";
    
    $stmt = $pdo->prepare($statsQuery);
    $stmt->execute($params);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $statusStats = [];
    foreach ($stats as $stat) {
        $statusStats[$stat['status_fluxo']] = (int)$stat['total'];
    }
    
    // ===== TIPOS DE DOCUMENTO =====
    $tiposQuery = "
        SELECT DISTINCT tipo_documento, COUNT(*) as total
        FROM Documentos_Associado da
        LEFT JOIN Associados a ON da.associado_id = a.id
        $whereClause
        GROUP BY tipo_documento
        ORDER BY total DESC
    ";
    
    $stmt = $pdo->prepare($tiposQuery);
    $stmt->execute($params);
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== RESPOSTA FINAL =====
    $responseData = [
        'documentos' => $processedData,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => (int)$totalRecords,
            'total_pages' => ceil($totalRecords / $limit),
            'has_next' => $page < ceil($totalRecords / $limit),
            'has_prev' => $page > 1
        ],
        'statistics' => [
            'total_documentos' => (int)$totalRecords,
            'por_status' => $statusStats,
            'tipos_documento' => $tipos
        ],
        'filters_applied' => [
            'search' => $search,
            'status' => $status_filter,
            'tipo' => $tipo_filter,
            'origem' => $origem_filter
        ]
    ];
    
    debugLog("✅ Resposta preparada com sucesso");
    
    jsonResponse('success', 'Documentos listados com sucesso', $responseData);
    
} catch (PDOException $e) {
    debugLog("❌ Erro de banco: " . $e->getMessage());
    jsonResponse('error', 'Erro ao consultar banco de dados', null, 500);
    
} catch (Exception $e) {
    debugLog("❌ Erro geral: " . $e->getMessage());
    jsonResponse('error', 'Erro interno do servidor: ' . $e->getMessage(), null, 500);
}

// Limpeza final do buffer
while (ob_get_level()) {
    ob_end_clean();
}
?>