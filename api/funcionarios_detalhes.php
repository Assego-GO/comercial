<?php
/**
 * API para buscar detalhes de um funcionário
 * api/funcionarios_detalhes.php
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

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID não informado']);
    exit;
}

try {
    $funcionarios = new Funcionarios();
    
    $funcionario = $funcionarios->getById($id);
    
    if (!$funcionario) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Funcionário não encontrado']);
        exit;
    }
    
    // Buscar informações adicionais
    $funcionario['badges'] = $funcionarios->getBadges($id);
    $funcionario['contribuicoes'] = $funcionarios->getContribuicoes($id);
    $funcionario['estatisticas'] = $funcionarios->getEstatisticas($id);
    
    // Remover senha do retorno
    unset($funcionario['senha']);
    
    echo json_encode([
        'status' => 'success',
        'funcionario' => $funcionario
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar detalhes do funcionário: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar funcionário'
    ]);
}
?>