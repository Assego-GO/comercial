<?php
// ============================================
// api/permissoes/atualizar_permissao_matriz.php
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
if (!$permissoes->isSuperAdmin() && !$permissoes->hasPermission('SISTEMA_PERMISSOES', 'EDIT')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['role_id']) || empty($data['recurso_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Role e recurso são obrigatórios']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // Remover permissões antigas
    $stmt = $db->prepare("DELETE FROM role_permissoes WHERE role_id = ? AND recurso_id = ?");
    $stmt->execute([$data['role_id'], $data['recurso_id']]);
    
    // Inserir novas permissões
    if (!empty($data['permissoes']) && is_array($data['permissoes'])) {
        $sql = "INSERT INTO role_permissoes (role_id, recurso_id, permissao_id) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        foreach ($data['permissoes'] as $permissaoId) {
            $stmt->execute([$data['role_id'], $data['recurso_id'], $permissaoId]);
        }
    }
    
    $db->commit();
    
    // Invalidar cache
    $stmt = $db->prepare("
        SELECT DISTINCT funcionario_id 
        FROM funcionario_roles 
        WHERE role_id = ? 
        AND (data_fim IS NULL OR data_fim >= CURDATE())
    ");
    $stmt->execute([$data['role_id']]);
    
    while ($row = $stmt->fetch()) {
        Permissoes::invalidateCache($row['funcionario_id']);
    }
    
    // Registrar na auditoria
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'role_permissoes',
        'acao' => 'ATUALIZAR_MATRIZ',
        'registro_id' => $data['role_id'],
        'detalhes' => [
            'recurso_id' => $data['recurso_id'],
            'permissoes' => $data['permissoes']
        ]
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Permissões atualizadas com sucesso']);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar permissões']);
}