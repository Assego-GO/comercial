<?php
/**
 * API para criar funcionário
 * api/funcionarios_criar.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Verificar se é diretor
if (!$auth->isDiretor()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado']);
    exit;
}

// Pegar dados do POST
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

try {
    $funcionarios = new Funcionarios();
    
    // Validações básicas
    if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
        throw new Exception('Preencha todos os campos obrigatórios');
    }
    
    // Validar email
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    // Validar senha
    if (strlen($dados['senha']) < 6) {
        throw new Exception('A senha deve ter no mínimo 6 caracteres');
    }
    
    $funcionario_id = $funcionarios->criar($dados);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Funcionário criado com sucesso',
        'funcionario_id' => $funcionario_id
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao criar funcionário: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
