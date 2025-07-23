<?php
/**
 * API para listar funcionários
 * api/funcionarios_listar.php
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

try {
    $funcionarios = new Funcionarios();
    
    // Filtros opcionais
    $filtros = [];
    if (isset($_GET['ativo'])) {
        $filtros['ativo'] = $_GET['ativo'];
    }
    if (isset($_GET['departamento_id'])) {
        $filtros['departamento_id'] = $_GET['departamento_id'];
    }
    if (isset($_GET['cargo'])) {
        $filtros['cargo'] = $_GET['cargo'];
    }
    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }
    
    $lista = $funcionarios->listar($filtros);
    
    echo json_encode([
        'status' => 'success',
        'funcionarios' => $lista,
        'total' => count($lista)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar funcionários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar funcionários'
    ]);
}   