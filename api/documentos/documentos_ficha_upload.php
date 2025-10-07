<?php
/**
 * API para upload de ficha de associação
 * api/documentos/documentos_ficha_upload.php
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
    
    // Validar dados recebidos
    if (!isset($_POST['associado_id']) || empty($_POST['associado_id'])) {
        throw new Exception('Associado não informado');
    }
    
    if (!isset($_FILES['documento']) || $_FILES['documento']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Arquivo não enviado corretamente');
    }
    
    $associadoId = intval($_POST['associado_id']);
    $tipoOrigem = $_POST['tipo_origem'] ?? 'FISICO';
    $observacao = $_POST['observacao'] ?? null;
    
    $documentos = new Documentos();
    $documentoId = $documentos->uploadDocumentoAssociacao(
        $associadoId,
        $_FILES['documento'],
        $tipoOrigem,
        $observacao
    );
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Ficha de associação enviada com sucesso',
        'documento_id' => $documentoId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>