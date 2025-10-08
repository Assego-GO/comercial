<?php

// ============================================
// api/permissoes/listar_departamentos.php
// ============================================

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $sql = "SELECT 
                d.*,
                COUNT(DISTINCT f.id) as total_funcionarios
            FROM Departamentos d
            LEFT JOIN Funcionarios f ON d.id = f.departamento_id AND f.ativo = 1
            WHERE d.ativo = 1
            GROUP BY d.id
            ORDER BY d.nome";
    
    $stmt = $db->query($sql);
    $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($departamentos);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar departamentos: ' . $e->getMessage()]);
}
