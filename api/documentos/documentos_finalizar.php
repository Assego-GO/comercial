<?php
/**
 * API para finalizar processo de documento
 * api/documentos/documentos_finalizar.php
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
    $resultado = $documentos->finalizarProcesso($documentoId, $observacao);
    
    // Montar resposta com feedback do Atacadão
    $response = [
        'status' => 'success',
        'message' => 'Processo finalizado com sucesso'
    ];
    
    // Se resultado é array (desfiliação com info do Atacadão)
    if (is_array($resultado) && isset($resultado['atacadao'])) {
        $response['atacadao'] = $resultado['atacadao'];
        $response['atacadao_success'] = $resultado['atacadao']['ok'] ?? false;
        if ($resultado['atacadao']['ok']) {
            $response['atacadao_message'] = 'CPF inativado com sucesso no sistema de benefícios do Atacadão Dia a Dia';
        } else {
            $response['atacadao_message'] = 'Não foi possível inativar o CPF no Atacadão. Verifique os logs.';
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>