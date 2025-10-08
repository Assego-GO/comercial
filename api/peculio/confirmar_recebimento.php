<?php
header('Content-Type: application/json; charset=utf-8');

// Função para resposta JSON
function jsonResponse($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Incluir dependências
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Método não permitido');
    }
    
    // Ler dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        jsonResponse('error', 'Dados inválidos');
    }
    
    // Validar dados obrigatórios
    $associado_id = $data['associado_id'] ?? null;
    $data_recebimento = $data['data_recebimento'] ?? date('Y-m-d');
    
    if (!$associado_id) {
        jsonResponse('error', 'ID do associado é obrigatório');
    }
    
    // Conectar no banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Verificar se existe registro de pecúlio
    $sqlCheck = "SELECT id, data_recebimento FROM Peculio WHERE associado_id = ?";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute([$associado_id]);
    $peculio = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$peculio) {
        jsonResponse('error', 'Registro de pecúlio não encontrado para este associado');
    }
    
    // Verificar se já foi recebido
    if ($peculio['data_recebimento'] && $peculio['data_recebimento'] !== '0000-00-00') {
        jsonResponse('warning', 'Pecúlio já foi recebido anteriormente');
    }
    
    // Atualizar data de recebimento
    $sql = "UPDATE Peculio SET data_recebimento = ? WHERE associado_id = ?";
    $stmt = $db->prepare($sql);
    $resultado = $stmt->execute([$data_recebimento, $associado_id]);
    
    if ($resultado) {
        jsonResponse('success', 'Recebimento do pecúlio confirmado com sucesso!', [
            'data_recebimento' => $data_recebimento
        ]);
    } else {
        jsonResponse('error', 'Erro ao confirmar recebimento');
    }
    
} catch (Exception $e) {
    jsonResponse('error', 'Erro interno: ' . $e->getMessage());
}
?>