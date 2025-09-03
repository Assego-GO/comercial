<?php
// ============================================
// api/permissoes/remover_role.php - NOVA
// ============================================

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';
require_once '../../classes/Auditoria.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$permissoes = Permissoes::getInstance();
if (!$permissoes->isSuperAdmin() && !$permissoes->hasPermission('SISTEMA_PERMISSOES', 'DELETE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão para remover roles']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['funcionario_role_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da atribuição é obrigatório']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar informações da atribuição
    $stmt = $db->prepare("
        SELECT fr.*, f.nome as funcionario_nome, r.nome as role_nome
        FROM funcionario_roles fr
        JOIN Funcionarios f ON fr.funcionario_id = f.id
        JOIN roles r ON fr.role_id = r.id
        WHERE fr.id = ?
    ");
    $stmt->execute([$data['funcionario_role_id']]);
    $atribuicao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$atribuicao) {
        http_response_code(404);
        echo json_encode(['error' => 'Atribuição não encontrada']);
        exit;
    }
    
    // Atualizar data_fim para hoje (soft delete)
    $stmt = $db->prepare("
        UPDATE funcionario_roles 
        SET data_fim = CURDATE()
        WHERE id = ?
    ");
    $stmt->execute([$data['funcionario_role_id']]);
    
    // Invalidar cache
    Permissoes::invalidateCache($atribuicao['funcionario_id']);
    
    // Registrar na auditoria
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'funcionario_roles',
        'acao' => 'REMOVER_ROLE',
        'registro_id' => $data['funcionario_role_id'],
        'detalhes' => [
            'funcionario' => $atribuicao['funcionario_nome'],
            'role_removida' => $atribuicao['role_nome'],
            'motivo' => $data['motivo'] ?? 'Não especificado'
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Role removida com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao remover role: ' . $e->getMessage()]);
}
