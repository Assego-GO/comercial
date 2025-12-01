<?php
/**
 * API Unificada - Listar Documentos de Associados
 * VERSÃO 4.1 - Usa apenas Documentos_Associado
 * Agregados identificados por Militar.corporacao = 'Agregados'
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

$DEBUG = false;

function jsonError($message, $code = 500, $debug = null) {
    global $DEBUG;
    ob_end_clean();
    http_response_code($code);
    $response = ['status' => 'error', 'message' => $message];
    if ($debug !== null && $DEBUG) {
        $response['debug'] = $debug;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonSuccess($data, $extra = []) {
    ob_end_clean();
    $response = array_merge(['status' => 'success', 'data' => $data], $extra);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método não permitido', 405);
}

try {
    // Includes
    $basePaths = [
        __DIR__ . '/../../',
        __DIR__ . '/../../../',
        $_SERVER['DOCUMENT_ROOT'] . '/comercial/',
        $_SERVER['DOCUMENT_ROOT'] . '/',
    ];
    
    $configLoaded = false;
    foreach ($basePaths as $basePath) {
        $configFile = $basePath . 'config/config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            require_once $basePath . 'config/database.php';
            require_once $basePath . 'classes/Database.php';
            require_once $basePath . 'classes/Auth.php';
            $configLoaded = true;
            break;
        }
    }
    
    if (!$configLoaded) {
        jsonError('Arquivos de configuração não encontrados', 500);
    }

    // Autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonError('Não autorizado', 401);
    }

    // Parâmetros
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $porPagina = min(100, max(10, intval($_GET['por_pagina'] ?? 20)));
    $offset = ($pagina - 1) * $porPagina;

    $tipo = strtoupper(trim($_GET['tipo'] ?? ''));
    $status = strtoupper(trim($_GET['status'] ?? ''));
    $busca = trim($_GET['busca'] ?? '');
    $periodo = trim($_GET['periodo'] ?? '');

    // Conexão
    $dbName = defined('DB_NAME') ? constant('DB_NAME') : (defined('DB_DATABASE') ? constant('DB_DATABASE') : 'wwasse_cadastro');
    $db = Database::getInstance($dbName);
    $conn = $db->getConnection();

    // ===== MONTAR FILTROS =====
    $where = ["1=1"];
    $params = [];

    // Filtro por tipo (Agregado vs Sócio)
    if ($tipo === 'AGREGADO') {
        $where[] = "m.corporacao = 'Agregados'";
    } elseif ($tipo === 'SOCIO') {
        $where[] = "(m.corporacao IS NULL OR m.corporacao != 'Agregados')";
    }

    // Filtro por status
    if (!empty($status)) {
        $where[] = "da.status_fluxo = ?";
        $params[] = $status;
    }

    // Filtro por busca (nome ou CPF)
    if (!empty($busca)) {
        $buscaLike = "%" . $busca . "%";
        $buscaCpf = "%" . preg_replace('/\D/', '', $busca) . "%";
        $where[] = "(a.nome LIKE ? OR a.cpf LIKE ? OR titular.nome LIKE ?)";
        $params[] = $buscaLike;
        $params[] = $buscaCpf;
        $params[] = $buscaLike;
    }

    // Filtro por período
    if (!empty($periodo)) {
        switch ($periodo) {
            case 'hoje':
                $where[] = "DATE(da.data_upload) = CURDATE()";
                break;
            case 'semana':
                $where[] = "da.data_upload >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $where[] = "da.data_upload >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }

    $whereClause = implode(" AND ", $where);

    // ===== CONTAR TOTAL =====
    $queryCount = "
        SELECT COUNT(*) as total
        FROM Documentos_Associado da
        INNER JOIN Associados a ON da.associado_id = a.id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
        WHERE $whereClause
    ";
    
    $stmtCount = $conn->prepare($queryCount);
    $stmtCount->execute($params);
    $totalRegistros = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // ===== BUSCAR DOCUMENTOS PAGINADOS =====
    $query = "
        SELECT 
            da.id,
            CASE 
                WHEN m.corporacao = 'Agregados' THEN 'AGREGADO'
                ELSE 'SOCIO'
            END as tipo_documento,
            a.id as pessoa_id,
            a.nome,
            a.cpf,
            a.email,
            a.associado_titular_id as titular_id,
            titular.nome as titular_nome,
            titular.cpf as titular_cpf,
            CASE 
                WHEN m.corporacao = 'Agregados' THEN 'Agregado'
                ELSE NULL
            END as parentesco,
            CASE 
                WHEN m.corporacao = 'Agregados' THEN 'Ficha de Sócio Agregado'
                ELSE 'Ficha de Filiação'
            END as tipo_descricao,
            da.status_fluxo,
            CASE da.status_fluxo
                WHEN 'DIGITALIZADO' THEN 'Aguardando Envio'
                WHEN 'AGUARDANDO_ASSINATURA' THEN 'Na Presidência'
                WHEN 'ASSINADO' THEN 'Assinado - Aguardando Finalização'
                WHEN 'FINALIZADO' THEN 'Finalizado'
                ELSE da.status_fluxo
            END as status_descricao,
            da.data_upload,
            COALESCE(da.tipo_origem, 'VIRTUAL') as tipo_origem,
            da.caminho_arquivo,
            da.nome_arquivo,
            COALESCE(dept.nome, 'Comercial') as departamento_atual_nome,
            DATEDIFF(CURDATE(), da.data_upload) as dias_em_processo,
            m.corporacao,
            m.patente
        FROM Documentos_Associado da
        INNER JOIN Associados a ON da.associado_id = a.id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
        LEFT JOIN Departamentos dept ON da.departamento_atual = dept.id
        WHERE $whereClause
        ORDER BY da.data_upload DESC
        LIMIT $porPagina OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== ESTATÍSTICAS GERAIS =====
    $estatisticas = [
        'total_socios' => 0,
        'total_agregados' => 0,
        'pendentes_socios' => 0,
        'pendentes_agregados' => 0,
        'assinados_socios' => 0,
        'assinados_agregados' => 0
    ];

    try {
        $stmtStats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN m.corporacao = 'Agregados' THEN 1 ELSE 0 END) as total_agregados,
                SUM(CASE WHEN (m.corporacao IS NULL OR m.corporacao != 'Agregados') THEN 1 ELSE 0 END) as total_socios,
                SUM(CASE WHEN da.status_fluxo = 'AGUARDANDO_ASSINATURA' AND m.corporacao = 'Agregados' THEN 1 ELSE 0 END) as pendentes_agregados,
                SUM(CASE WHEN da.status_fluxo = 'AGUARDANDO_ASSINATURA' AND (m.corporacao IS NULL OR m.corporacao != 'Agregados') THEN 1 ELSE 0 END) as pendentes_socios,
                SUM(CASE WHEN da.status_fluxo IN ('ASSINADO', 'FINALIZADO') AND m.corporacao = 'Agregados' THEN 1 ELSE 0 END) as assinados_agregados,
                SUM(CASE WHEN da.status_fluxo IN ('ASSINADO', 'FINALIZADO') AND (m.corporacao IS NULL OR m.corporacao != 'Agregados') THEN 1 ELSE 0 END) as assinados_socios
            FROM Documentos_Associado da
            INNER JOIN Associados a ON da.associado_id = a.id
            LEFT JOIN Militar m ON a.id = m.associado_id
        ");
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        $estatisticas = [
            'total_socios' => (int) ($stats['total_socios'] ?? 0),
            'total_agregados' => (int) ($stats['total_agregados'] ?? 0),
            'pendentes_socios' => (int) ($stats['pendentes_socios'] ?? 0),
            'pendentes_agregados' => (int) ($stats['pendentes_agregados'] ?? 0),
            'assinados_socios' => (int) ($stats['assinados_socios'] ?? 0),
            'assinados_agregados' => (int) ($stats['assinados_agregados'] ?? 0)
        ];
    } catch (PDOException $e) {
        error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    }

    // Cálculo de paginação
    $totalPaginas = $totalRegistros > 0 ? ceil($totalRegistros / $porPagina) : 1;

    // Resposta
    jsonSuccess($documentos, [
        'paginacao' => [
            'pagina_atual' => $pagina,
            'por_pagina' => $porPagina,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'tem_anterior' => $pagina > 1,
            'tem_proxima' => $pagina < $totalPaginas
        ],
        'filtros_aplicados' => [
            'tipo' => $tipo ?: 'TODOS',
            'status' => $status ?: 'TODOS',
            'busca' => $busca,
            'periodo' => $periodo
        ],
        'estatisticas' => $estatisticas
    ]);

} catch (PDOException $e) {
    error_log("Erro de banco na API unificada: " . $e->getMessage());
    jsonError('Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Erro geral na API unificada: " . $e->getMessage());
    jsonError('Erro interno: ' . $e->getMessage(), 500);
}
