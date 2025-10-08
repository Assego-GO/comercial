<?php
// ============================================
// api/permissoes/listar_recursos.php
// ============================================

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$permissoes = Permissoes::getInstance();
if (!$permissoes->isSuperAdmin() && !$permissoes->hasPermission('SISTEMA_PERMISSOES', 'VIEW')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $sql = "SELECT 
                r.*,
                d.nome as departamento_nome
            FROM recursos r
            LEFT JOIN Departamentos d ON r.departamento_id = d.id
            WHERE r.ativo = 1
            ORDER BY r.categoria, r.modulo, r.ordem, r.nome";
    
    $stmt = $db->query($sql);
    $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($recursos);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar recursos: ' . $e->getMessage()]);
}