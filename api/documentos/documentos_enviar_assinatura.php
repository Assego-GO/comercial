<?php
/**
 * API para enviar documento para assinatura
 * api/documentos/documentos_enviar_assinatura.php
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
    
    // Ler dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['documento_id']) || empty($input['documento_id'])) {
        throw new Exception('Documento não informado');
    }
    
    $documentoId = intval($input['documento_id']);
    $observacao = $input['observacao'] ?? null;
    
    $documentos = new Documentos();
    $resultado = $documentos->enviarParaAssinatura($documentoId, $observacao);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Documento enviado para assinatura com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>