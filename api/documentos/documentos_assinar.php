<?php
/**
 * API para assinar documento
 * api/documentos/documentos_assinar.php
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
    
    // Verificar se usuário tem permissão para assinar (presidência)
    $usuario = $auth->getUser();
    if (!$auth->isDiretor() && $usuario['departamento_id'] != 2) {
        throw new Exception('Sem permissão para assinar documentos');
    }
    
    if (!isset($_POST['documento_id']) || empty($_POST['documento_id'])) {
        throw new Exception('Documento não informado');
    }
    
    $documentoId = intval($_POST['documento_id']);
    $observacao = $_POST['observacao'] ?? null;
    
    // Verificar se foi enviado arquivo assinado
    $arquivoAssinado = null;
    if (isset($_FILES['arquivo_assinado']) && $_FILES['arquivo_assinado']['error'] === UPLOAD_ERR_OK) {
        $arquivoAssinado = $_FILES['arquivo_assinado'];
    }
    
    $documentos = new Documentos();
    $resultado = $documentos->assinarDocumento($documentoId, $arquivoAssinado, $observacao);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Documento assinado com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>