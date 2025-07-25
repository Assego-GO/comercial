<?php
/**
 * API para excluir documento
 * api/documentos/documentos_excluir.php
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
    
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Documento não informado');
    }
    
    $documentoId = intval($_GET['id']);
    
    $documentos = new Documentos();
    $resultado = $documentos->excluir($documentoId);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Documento excluído com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>