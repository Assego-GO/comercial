<?php
/**
 * API para buscar histórico do fluxo
 * api/documentos/documentos_historico_fluxo.php
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
    
    if (!isset($_GET['documento_id']) || empty($_GET['documento_id'])) {
        throw new Exception('Documento não informado');
    }
    
    $documentoId = intval($_GET['documento_id']);
    
    $documentos = new Documentos();
    $historico = $documentos->getHistoricoFluxo($documentoId);
    
    echo json_encode([
        'status' => 'success',
        'data' => $historico
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>