<?php
/**
 * API para atualizar funcionário
 * api/funcionarios_atualizar.php
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

// Pegar dados do PUT
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || !isset($dados['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

try {
    $funcionarios = new Funcionarios();
    
    $id = $dados['id'];
    unset($dados['id']); // Remove ID dos dados de atualização
    
    // Validações
    if (isset($dados['email']) && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    if (isset($dados['senha']) && strlen($dados['senha']) < 6) {
        throw new Exception('A senha deve ter no mínimo 6 caracteres');
    }
    
    $resultado = $funcionarios->atualizar($id, $dados);
    
    if ($resultado) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Funcionário atualizado com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao atualizar funcionário');
    }
    
} catch (Exception $e) {
    error_log("Erro ao atualizar funcionário: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
