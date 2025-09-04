<?php
// ============================================
// api/permissoes/obter_permissoes_role.php
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

$roleId = $_GET['id'] ?? null;

if (!$roleId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da role é obrigatório']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $sql = "SELECT 
                rp.*,
                rec.nome as recurso_nome,
                rec.codigo as recurso_codigo,
                p.nome as permissao_nome,
                p.codigo as permissao_codigo
            FROM role_permissoes rp
            JOIN recursos rec ON rp.recurso_id = rec.id
            JOIN permissoes p ON rp.permissao_id = p.id
            WHERE rp.role_id = ?
            ORDER BY rec.categoria, rec.nome";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$roleId]);
    $permissoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($permissoes);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar permissões da role']);
}

