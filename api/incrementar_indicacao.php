<?php
/**
 * API para incrementar contador de indicações
 * api/incrementar_indicacao.php
 */

header('Content-Type: application/json; charset=utf-8');

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Verifica autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

try {
    // Pega dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $indicadorId = isset($input['id']) ? intval($input['id']) : 0;
    
    if ($indicadorId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Incrementa o contador
    $sql = "UPDATE Indicadores 
            SET total_indicacoes = total_indicacoes + 1 
            WHERE id = :id AND ativo = 1";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $indicadorId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Contador incrementado']);
    } else {
        echo json_encode(['status' => 'warning', 'message' => 'Indicador não encontrado']);
    }
    
} catch (Exception $e) {
    error_log("Erro ao incrementar indicação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar requisição'
    ]);
}
?>