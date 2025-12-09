<?php
/**
 * API Unificada - Listar Documentos de Associados
 * VERSÃO 5.0 - Usa DocumentosFluxo (igual ao dashboard principal)
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
    
    // Debug - log dos parâmetros recebidos
    error_log("API Documentos - Busca: '$busca', Tipo: '$tipo', Status: '$status', Periodo: '$periodo'");

    // Conexão
    $dbName = defined('DB_NAME') ? constant('DB_NAME') : (defined('DB_DATABASE') ? constant('DB_DATABASE') : 'wwasse_cadastro');
    $db = Database::getInstance($dbName);
    $conn = $db->getConnection();

    // ===== MONTAR FILTROS COM NAMED PARAMETERS =====
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
        $where[] = "df.status_fluxo = :status";
        $params[':status'] = $status;
    }

    // Filtro por busca (nome ou CPF ou RG) - USANDO NAMED PARAMS
    if (!empty($busca)) {
        $buscaLike = "%" . $busca . "%";
        $buscaNumeros = preg_replace('/\D/', '', $busca);
        
        // Montar condições de busca dinamicamente
        $buscaConditions = ["a.nome LIKE :busca_nome"];
        $params[':busca_nome'] = $buscaLike;
        
        // Só buscar por CPF se tiver números na busca
        if (!empty($buscaNumeros)) {
            $buscaConditions[] = "a.cpf LIKE :busca_cpf";
            $params[':busca_cpf'] = "%" . $buscaNumeros . "%";
        }
        
        // RG pode ter letras, então sempre busca
        $buscaConditions[] = "a.rg LIKE :busca_rg";
        $params[':busca_rg'] = $buscaLike;
        
        $where[] = "(" . implode(" OR ", $buscaConditions) . ")";
        error_log("BUSCA APLICADA: '$busca' -> condições: " . implode(", ", $buscaConditions));
    } else {
        error_log("BUSCA VAZIA - busca='$busca', empty=" . (empty($busca) ? 'true' : 'false'));
    }

    // Filtro por período
    if (!empty($periodo)) {
        switch ($periodo) {
            case 'hoje':
                $where[] = "DATE(df.data_upload) = CURDATE()";
                break;
            case 'semana':
                $where[] = "df.data_upload >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $where[] = "df.data_upload >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }

    $whereClause = implode(" AND ", $where);
    
    // Debug: mostrar query e params
    error_log("WHERE: $whereClause");
    error_log("PARAMS: " . json_encode($params));

    // ===== CONTAR TOTAL - USANDO bindValue EXPLÍCITO =====
    $queryCount = "
        SELECT COUNT(*) as total
        FROM DocumentosFluxo df
        INNER JOIN Associados a ON df.associado_id = a.id
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE $whereClause
    ";
    
    $stmtCount = $conn->prepare($queryCount);
    
    // Bind cada parâmetro explicitamente
    foreach ($params as $key => $value) {
        $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
    }
    
    $stmtCount->execute();
    $totalRegistros = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Debug total
    $debugTotal = $totalRegistros;

    // ===== BUSCAR DOCUMENTOS PAGINADOS =====
    $query = "
        SELECT 
            da.id,
            da.tipo_documento as tipo_documento_real,

            CASE 
                WHEN m.corporacao = 'Agregados' THEN 'AGREGADO'
                ELSE 'SOCIO'
            END as tipo_documento,
            a.id as pessoa_id,
            a.nome,
            a.cpf,
            a.email,
            a.situacao,
            a.rg,
            NULL as titular_id,
            NULL as titular_nome,
            NULL as titular_cpf,
            CASE 
                WHEN m.corporacao = 'Agregados' THEN 'Agregado'
                ELSE NULL
            END as parentesco,
            CASE 
                WHEN da.tipo_documento = 'ficha_desfiliacao' THEN 'Ficha de Desfiliação'
                WHEN m.corporacao = 'Agregados' THEN 'Ficha de Sócio Agregado'
                ELSE 'Ficha de Filiação'
            END as tipo_descricao,
            da.status_fluxo,
            CASE da.status_fluxo

                WHEN 'DIGITALIZADO' THEN 'Aguardando Envio'
                WHEN 'AGUARDANDO_ASSINATURA' THEN 'Na Presidência'
                WHEN 'ASSINADO' THEN 'Assinado - Aguardando Finalização'
                WHEN 'FINALIZADO' THEN 'Finalizado'
                ELSE df.status_fluxo
            END as status_descricao,
            df.data_upload,
            df.caminho_arquivo,
            df.nome_arquivo,
            df.tipo_mime,
            df.tamanho_arquivo,
            COALESCE(dept.nome, 'Comercial') as departamento_atual_nome,
            DATEDIFF(CURDATE(), df.data_upload) as dias_em_processo,
            m.corporacao,
            m.patente,
            f.nome as funcionario_upload
        FROM DocumentosFluxo df
        INNER JOIN Associados a ON df.associado_id = a.id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Departamentos dept ON df.departamento_atual = dept.id
        LEFT JOIN Funcionarios f ON df.funcionario_upload = f.id
        WHERE $whereClause
        ORDER BY df.data_upload DESC
        LIMIT $porPagina OFFSET $offset
    ";
    
    $stmt = $conn->prepare($query);
    
    // Bind cada parâmetro explicitamente (igual ao count)
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona informações extras (igual upload_documentos_listar.php)
    foreach ($documentos as &$doc) {
        // Formata tamanho
        $bytes = $doc['tamanho_arquivo'] ?? 0;
        if ($bytes == 0) {
            $doc['tamanho_formatado'] = '0 B';
        } else {
            $k = 1024;
            $sizes = ['B', 'KB', 'MB', 'GB'];
            $i = floor(log($bytes) / log($k));
            $doc['tamanho_formatado'] = round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
        }

        // Verifica se arquivo existe
        $doc['arquivo_existe'] = file_exists('../../' . $doc['caminho_arquivo']);
        
        // Formata datas
        $doc['data_upload_formatada'] = date('d/m/Y H:i', strtotime($doc['data_upload']));
    }

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
                SUM(CASE WHEN df.status_fluxo = 'AGUARDANDO_ASSINATURA' AND m.corporacao = 'Agregados' THEN 1 ELSE 0 END) as pendentes_agregados,
                SUM(CASE WHEN df.status_fluxo = 'AGUARDANDO_ASSINATURA' AND (m.corporacao IS NULL OR m.corporacao != 'Agregados') THEN 1 ELSE 0 END) as pendentes_socios,
                SUM(CASE WHEN df.status_fluxo IN ('ASSINADO', 'FINALIZADO') AND m.corporacao = 'Agregados' THEN 1 ELSE 0 END) as assinados_agregados,
                SUM(CASE WHEN df.status_fluxo IN ('ASSINADO', 'FINALIZADO') AND (m.corporacao IS NULL OR m.corporacao != 'Agregados') THEN 1 ELSE 0 END) as assinados_socios
            FROM DocumentosFluxo df
            INNER JOIN Associados a ON df.associado_id = a.id
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
        'debug' => [
            'where_clause' => $whereClause,
            'params_count' => count($params),
            'params_values' => $params,
            'busca_vazia' => empty($busca),
            'busca_raw' => $_GET['busca'] ?? 'NAO_RECEBIDO',
            'query_count' => $queryCount ?? '',
            'total_encontrado' => $totalRegistros,
            'debug_total_direto' => $debugTotal ?? 0
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