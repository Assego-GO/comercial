<?php
/**
 * API Unificada - Listar Documentos (Sócios e Agregados)
 * api/documentos/documentos_unificados_listar.php
 * 
 * VERSÃO 3.0 - Debug melhorado e busca corrigida
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Flag de debug - mude para true para ver detalhes dos erros
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
    $dbName = defined('DB_NAME') ? DB_NAME : (defined('DB_DATABASE') ? DB_DATABASE : 'wwasse_cadastro');
    $db = Database::getInstance($dbName);
    $conn = $db->getConnection();

    // ===== VERIFICAR QUAIS TABELAS EXISTEM =====
    $tabelaSociosExiste = false;
    $tabelaAgregadosExiste = false;
    
    try {
        $conn->query("SELECT 1 FROM Documentos_Associado LIMIT 1");
        $tabelaSociosExiste = true;
    } catch (PDOException $e) {}
    
    try {
        $conn->query("SELECT 1 FROM Socios_Agregados LIMIT 1");
        $tabelaAgregadosExiste = true;
    } catch (PDOException $e) {}

    if (!$tabelaSociosExiste && !$tabelaAgregadosExiste) {
        jsonError('Nenhuma tabela de documentos encontrada', 500);
    }

    // ===== DECIDIR QUAL QUERY USAR =====
    $usarSocios = $tabelaSociosExiste && ($tipo === '' || $tipo === 'SOCIO');
    $usarAgregados = $tabelaAgregadosExiste && ($tipo === '' || $tipo === 'AGREGADO');

    // Preparar valores de busca uma vez só
    $buscaLike = !empty($busca) ? "%" . $busca . "%" : null;
    $buscaCpf = !empty($busca) ? "%" . preg_replace('/\D/', '', $busca) . "%" : null;

    // ===== FUNÇÃO PARA MONTAR WHERE E PARAMS DE SÓCIOS =====
    function montarFiltrosSocios($status, $buscaLike, $buscaCpf, $periodo) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($status)) {
            $where[] = "da.status_fluxo = ?";
            $params[] = $status;
        }
        
        if ($buscaLike !== null) {
            $where[] = "(a.nome LIKE ? OR a.cpf LIKE ?)";
            $params[] = $buscaLike;
            $params[] = $buscaCpf;
        }
        
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
        
        return ['where' => implode(" AND ", $where), 'params' => $params];
    }

    // ===== FUNÇÃO PARA MONTAR WHERE E PARAMS DE AGREGADOS =====
    function montarFiltrosAgregados($status, $buscaLike, $buscaCpf, $periodo) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($status)) {
            if ($status === 'AGUARDANDO_ASSINATURA') {
                $where[] = "sa.situacao = 'pendente'";
            } elseif ($status === 'ASSINADO' || $status === 'FINALIZADO') {
                $where[] = "sa.situacao = 'ativo'";
            } elseif ($status === 'DIGITALIZADO') {
                $where[] = "1=0"; // Agregados não têm esse status
            }
        }
        
        if ($buscaLike !== null) {
            $where[] = "(sa.nome LIKE ? OR sa.cpf LIKE ? OR sa.socio_titular_nome LIKE ?)";
            $params[] = $buscaLike;
            $params[] = $buscaCpf;
            $params[] = $buscaLike;
        }
        
        if (!empty($periodo)) {
            switch ($periodo) {
                case 'hoje':
                    $where[] = "DATE(sa.data_criacao) = CURDATE()";
                    break;
                case 'semana':
                    $where[] = "sa.data_criacao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'mes':
                    $where[] = "sa.data_criacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
            }
        }
        
        return ['where' => implode(" AND ", $where), 'params' => $params];
    }

    // ===== ARRAYS PARA RESULTADO =====
    $todosDocumentos = [];
    $totalSocios = 0;
    $totalAgregados = 0;

    // ===== BUSCAR SÓCIOS =====
    if ($usarSocios) {
        $filtrosSocios = montarFiltrosSocios($status, $buscaLike, $buscaCpf, $periodo);
        
        // Contar
        $queryCountSocios = "
            SELECT COUNT(*) as total
            FROM Documentos_Associado da
            LEFT JOIN Associados a ON da.associado_id = a.id
            WHERE {$filtrosSocios['where']}
        ";
        
        $stmtCount = $conn->prepare($queryCountSocios);
        $stmtCount->execute($filtrosSocios['params']);
        $totalSocios = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // Buscar dados (se houver)
        if ($totalSocios > 0) {
            $querySocios = "
                SELECT 
                    da.id,
                    'SOCIO' as tipo_documento,
                    a.id as pessoa_id,
                    a.nome,
                    a.cpf,
                    a.email,
                    NULL as titular_id,
                    NULL as titular_nome,
                    NULL as titular_cpf,
                    NULL as parentesco,
                    'Ficha de Filiação' as tipo_descricao,
                    da.status_fluxo,
                    CASE da.status_fluxo
                        WHEN 'DIGITALIZADO' THEN 'Aguardando Envio'
                        WHEN 'AGUARDANDO_ASSINATURA' THEN 'Na Presidência'
                        WHEN 'ASSINADO' THEN 'Assinado'
                        WHEN 'FINALIZADO' THEN 'Finalizado'
                        ELSE da.status_fluxo
                    END as status_descricao,
                    da.data_upload,
                    'VIRTUAL' as tipo_origem,
                    da.caminho_arquivo,
                    da.nome_arquivo,
                    COALESCE(d.nome, 'Comercial') as departamento_atual_nome,
                    DATEDIFF(CURDATE(), da.data_upload) as dias_em_processo
                FROM Documentos_Associado da
                LEFT JOIN Associados a ON da.associado_id = a.id
                LEFT JOIN Departamentos d ON da.departamento_atual = d.id
                WHERE {$filtrosSocios['where']}
                ORDER BY da.data_upload DESC
            ";
            
            $stmt = $conn->prepare($querySocios);
            $stmt->execute($filtrosSocios['params']);
            $documentosSocios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $todosDocumentos = array_merge($todosDocumentos, $documentosSocios);
        }
    }

    // ===== BUSCAR AGREGADOS =====
    if ($usarAgregados) {
        $filtrosAgregados = montarFiltrosAgregados($status, $buscaLike, $buscaCpf, $periodo);
        
        // Contar
        $queryCountAgregados = "
            SELECT COUNT(*) as total
            FROM Socios_Agregados sa
            WHERE {$filtrosAgregados['where']}
        ";
        
        $stmtCount = $conn->prepare($queryCountAgregados);
        $stmtCount->execute($filtrosAgregados['params']);
        $totalAgregados = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // Buscar dados (se houver)
        if ($totalAgregados > 0) {
            $queryAgregados = "
                SELECT 
                    sa.id,
                    'AGREGADO' as tipo_documento,
                    sa.id as pessoa_id,
                    sa.nome,
                    sa.cpf,
                    sa.email,
                    NULL as titular_id,
                    sa.socio_titular_nome as titular_nome,
                    sa.socio_titular_cpf as titular_cpf,
                    'Dependente' as parentesco,
                    'Ficha de Sócio Agregado' as tipo_descricao,
                    CASE sa.situacao
                        WHEN 'pendente' THEN 'AGUARDANDO_ASSINATURA'
                        WHEN 'ativo' THEN 'FINALIZADO'
                        ELSE 'AGUARDANDO_ASSINATURA'
                    END as status_fluxo,
                    CASE sa.situacao
                        WHEN 'pendente' THEN 'Na Presidência'
                        WHEN 'ativo' THEN 'Finalizado'
                        ELSE 'Na Presidência'
                    END as status_descricao,
                    sa.data_criacao as data_upload,
                    'VIRTUAL' as tipo_origem,
                    NULL as caminho_arquivo,
                    NULL as nome_arquivo,
                    'Comercial' as departamento_atual_nome,
                    DATEDIFF(CURDATE(), sa.data_criacao) as dias_em_processo
                FROM Socios_Agregados sa
                WHERE {$filtrosAgregados['where']}
                ORDER BY sa.data_criacao DESC
            ";
            
            $stmt = $conn->prepare($queryAgregados);
            $stmt->execute($filtrosAgregados['params']);
            $documentosAgregados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $todosDocumentos = array_merge($todosDocumentos, $documentosAgregados);
        }
    }

    // ===== CALCULAR PAGINAÇÃO TOTAL =====
    $totalRegistros = $totalSocios + $totalAgregados;
    $totalPaginas = $totalRegistros > 0 ? ceil($totalRegistros / $porPagina) : 1;

    // ===== ORDENAR E PAGINAR EM PHP =====
    usort($todosDocumentos, function($a, $b) {
        $dataA = strtotime($a['data_upload'] ?? '1970-01-01');
        $dataB = strtotime($b['data_upload'] ?? '1970-01-01');
        return $dataB - $dataA; // DESC
    });

    // Aplicar paginação
    $documentosPaginados = array_slice($todosDocumentos, $offset, $porPagina);

    // ===== ESTATÍSTICAS GERAIS (sem filtros) =====
    $estatisticas = [
        'total_socios' => 0,
        'total_agregados' => 0,
        'pendentes_socios' => 0,
        'pendentes_agregados' => 0,
        'assinados_socios' => 0,
        'assinados_agregados' => 0
    ];

    if ($tabelaSociosExiste) {
        try {
            $stmtStats = $conn->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status_fluxo = 'AGUARDANDO_ASSINATURA' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status_fluxo IN ('ASSINADO', 'FINALIZADO') THEN 1 ELSE 0 END) as assinados
                FROM Documentos_Associado
            ");
            $statsSocios = $stmtStats->fetch(PDO::FETCH_ASSOC);
            $estatisticas['total_socios'] = (int) ($statsSocios['total'] ?? 0);
            $estatisticas['pendentes_socios'] = (int) ($statsSocios['pendentes'] ?? 0);
            $estatisticas['assinados_socios'] = (int) ($statsSocios['assinados'] ?? 0);
        } catch (PDOException $e) {}
    }

    if ($tabelaAgregadosExiste) {
        try {
            $stmtStats = $conn->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN situacao = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN situacao = 'ativo' THEN 1 ELSE 0 END) as assinados
                FROM Socios_Agregados
            ");
            $statsAgregados = $stmtStats->fetch(PDO::FETCH_ASSOC);
            $estatisticas['total_agregados'] = (int) ($statsAgregados['total'] ?? 0);
            $estatisticas['pendentes_agregados'] = (int) ($statsAgregados['pendentes'] ?? 0);
            $estatisticas['assinados_agregados'] = (int) ($statsAgregados['assinados'] ?? 0);
        } catch (PDOException $e) {}
    }

    // ===== RESPOSTA =====
    jsonSuccess($documentosPaginados, [
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
        'resultados_filtrados' => [
            'socios_encontrados' => $totalSocios,
            'agregados_encontrados' => $totalAgregados
        ],
        'estatisticas' => $estatisticas,
        'tabelas_disponiveis' => [
            'socios' => $tabelaSociosExiste,
            'agregados' => $tabelaAgregadosExiste
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erro de banco na API unificada: " . $e->getMessage());
    jsonError('Erro de banco de dados: ' . $e->getMessage(), 500, [
        'tipo' => 'PDOException',
        'query_params' => $_GET
    ]);
} catch (Exception $e) {
    error_log("Erro geral na API unificada: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    jsonError('Erro interno: ' . $e->getMessage(), 500, [
        'tipo' => get_class($e),
        'linha' => $e->getLine(),
        'arquivo' => basename($e->getFile())
    ]);
}