<?php
header('Content-Type: application/json; charset=utf-8');
//atualizar_peculio.php
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
    $valor = $data['valor'] ?? null;
    $data_prevista = $data['data_prevista'] ?? null;
    $data_recebimento = $data['data_recebimento'] ?? null;
    
    if (!$associado_id) {
        jsonResponse('error', 'ID do associado é obrigatório');
    }
    
    // Conectar no banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Verificar se já existe registro de pecúlio para este associado
    $sqlCheck = "SELECT id FROM Peculio WHERE associado_id = ?";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute([$associado_id]);
    $peculioExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($peculioExistente) {
        // Atualizar registro existente
        $sql = "UPDATE Peculio SET 
                    valor = ?, 
                    data_prevista = ?, 
                    data_recebimento = ?
                WHERE associado_id = ?";
        $params = [
            $valor ?: 0,
            $data_prevista ?: null,
            $data_recebimento ?: null,
            $associado_id
        ];
    } else {
        // Criar novo registro
        $sql = "INSERT INTO Peculio (associado_id, valor, data_prevista, data_recebimento) 
                VALUES (?, ?, ?, ?)";
        $params = [
            $associado_id,
            $valor ?: 0,
            $data_prevista ?: null,
            $data_recebimento ?: null
        ];
    }
    
    $stmt = $db->prepare($sql);
    $resultado = $stmt->execute($params);
    
    if ($resultado) {
        $operacao = $peculioExistente ? 'atualizado' : 'criado';
        jsonResponse('success', "Registro de pecúlio $operacao com sucesso!");
    } else {
        jsonResponse('error', 'Erro ao salvar dados no banco');
    }
    
} catch (Exception $e) {
    jsonResponse('error', 'Erro interno: ' . $e->getMessage());
}
?>