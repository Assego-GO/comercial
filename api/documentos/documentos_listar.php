<?php
/**
 * API para listar documentos
 * api/documentos/documentos_listar.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Documentos.php';

header('Content-Type: application/json');

try {
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        throw new Exception('Não autorizado');
    }
    
    $documentos = new Documentos();
    
    // Filtros
    $filtros = [];
    if (isset($_GET['associado_id'])) $filtros['associado_id'] = $_GET['associado_id'];
    if (isset($_GET['tipo_documento'])) $filtros['tipo_documento'] = $_GET['tipo_documento'];
    if (isset($_GET['verificado'])) $filtros['verificado'] = $_GET['verificado'];
    if (isset($_GET['periodo'])) $filtros['periodo'] = $_GET['periodo'];
    if (isset($_GET['busca'])) $filtros['busca'] = $_GET['busca'];
    if (isset($_GET['limit'])) $filtros['limit'] = intval($_GET['limit']);
    if (isset($_GET['offset'])) $filtros['offset'] = intval($_GET['offset']);
    
    $lista = $documentos->listar($filtros);
    
    echo json_encode([
        'status' => 'success',
        'data' => $lista
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>