<?php
// ============================================
// api/permissoes/listar_roles.php
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
                COUNT(DISTINCT fr.funcionario_id) as total_usuarios
            FROM roles r
            LEFT JOIN funcionario_roles fr ON r.id = fr.role_id 
                AND (fr.data_fim IS NULL OR fr.data_fim >= CURDATE())
            GROUP BY r.id
            ORDER BY r.nivel_hierarquia DESC, r.nome";
    
    $stmt = $db->query($sql);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($roles);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar roles: ' . $e->getMessage()]);
}