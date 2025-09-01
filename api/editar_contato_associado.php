<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID não informado']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Atualizar dados do associado
    $stmt = $db->prepare("
        UPDATE Associados 
        SET telefone = ?, email = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['telefone'] ?? null,
        $_POST['email'] ?? null,
        $id
    ]);
    
    // Atualizar endereço
    $stmt = $db->prepare("
        UPDATE Endereco 
        SET cep = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?
        WHERE associado_id = ?
    ");
    $stmt->execute([
        $_POST['cep'] ?? null,
        $_POST['endereco'] ?? null,
        $_POST['numero'] ?? null,
        $_POST['complemento'] ?? null,
        $_POST['bairro'] ?? null,
        $_POST['cidade'] ?? null,
        $id
    ]);
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Contato atualizado com sucesso']);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}   