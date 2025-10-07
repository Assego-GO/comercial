<?php
// ============================================
// api/permissoes/listar_delegacoes.php
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
                d.*,
                f1.nome as delegante_nome,
                f2.nome as delegado_nome,
                r.nome as role_nome,
                rec.nome as recurso_nome
            FROM delegacoes d
            INNER JOIN Funcionarios f1 ON d.delegante_id = f1.id
            INNER JOIN Funcionarios f2 ON d.delegado_id = f2.id
            LEFT JOIN roles r ON d.role_id = r.id
            LEFT JOIN recursos rec ON d.recurso_id = rec.id
            WHERE d.ativo = 1
            ORDER BY d.data_inicio DESC";
    
    $stmt = $db->query($sql);
    $delegacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($delegacoes);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar delegações: ' . $e->getMessage()]);
}