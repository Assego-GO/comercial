<?php
/**
 * API para listar departamentos
 * api/departamentos_listar.php
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

try {
    $funcionarios = new Funcionarios();
    $departamentos = $funcionarios->getDepartamentos();
    
    echo json_encode([
        'status' => 'success',
        'departamentos' => $departamentos,
        'total' => count($departamentos)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar departamentos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar departamentos'
    ]);
}