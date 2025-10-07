<?php
/**
 * API para buscar estatísticas de documentos
 * api/documentos/documentos_estatisticas.php
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
    $estatisticas = $documentos->getEstatisticas();
    
    echo json_encode([
        'status' => 'success',
        'data' => $estatisticas
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>