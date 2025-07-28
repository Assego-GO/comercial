<?php
/**
 * API para gerar ficha virtual
 * api/documentos/documentos_gerar_ficha_virtual.php
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
    
    if (!isset($input['associado_id']) || empty($input['associado_id'])) {
        throw new Exception('Associado não informado');
    }
    
    $associadoId = intval($input['associado_id']);
    
    $documentos = new Documentos();
    $resultado = $documentos->gerarFichaVirtual($associadoId);
    
    // Converter HTML para PDF seria ideal aqui
    // Por enquanto, retornar o HTML gerado
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Ficha virtual gerada com sucesso',
        'dados' => [
            'html' => $resultado['html'],
            'nome_arquivo' => $resultado['nome_arquivo'],
            'associado_nome' => $resultado['associado_nome']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>