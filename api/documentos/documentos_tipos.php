<?php
/**
 * API para buscar tipos de documentos
 * api/documentos/documentos_tipos.php
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
    $tipos = $documentos->getTiposDocumentos();
    
    echo json_encode([
        'status' => 'success',
        'tipos_documentos' => $tipos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>