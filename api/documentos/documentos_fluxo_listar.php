<?php
/**
 * API para listar documentos em fluxo de assinatura
 * api/documentos/documentos_fluxo_listar.php
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Documentos.php';

    session_start();
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Filtros da requisição
    $filtros = [];
    
    if (isset($_GET['status'])) {
        $filtros['status'] = $_GET['status'];
    }
    
    if (isset($_GET['origem'])) {
        $filtros['origem'] = $_GET['origem'];
    }
    
    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }
    
    if (isset($_GET['periodo'])) {
        $filtros['periodo'] = $_GET['periodo'];
    }
    
    if (isset($_GET['limit'])) {
        $filtros['limit'] = intval($_GET['limit']);
    }
    
    if (isset($_GET['offset'])) {
        $filtros['offset'] = intval($_GET['offset']);
    }

    // Buscar documentos em fluxo
    $documentos = new Documentos();
    $lista = $documentos->listarDocumentosEmFluxo($filtros);

    $response = [
        'status' => 'success',
        'data' => $lista,
        'total' => count($lista)
    ];

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;