<?php

// ============================================
// api/permissoes/excluir_role.php
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
if (!$permissoes->isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas Super Admin pode excluir roles']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da role é obrigatório']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Verificar se é role de sistema
    $stmt = $db->prepare("SELECT codigo, tipo FROM roles WHERE id = ?");
    $stmt->execute([$data['id']]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role['tipo'] === 'SISTEMA') {
        http_response_code(400);
        echo json_encode(['error' => 'Não é possível excluir roles de sistema']);
        exit;
    }
    
    $db->beginTransaction();
    
    // Buscar funcionários afetados
    $stmt = $db->prepare("
        SELECT DISTINCT funcionario_id 
        FROM funcionario_roles 
        WHERE role_id = ? 
        AND (data_fim IS NULL OR data_fim >= CURDATE())
    ");
    $stmt->execute([$data['id']]);
    $funcionariosAfetados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Soft delete - desativar role
    $stmt = $db->prepare("UPDATE roles SET ativo = 0 WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    // Finalizar atribuições ativas
    $stmt = $db->prepare("
        UPDATE funcionario_roles 
        SET data_fim = CURDATE() 
        WHERE role_id = ? 
        AND (data_fim IS NULL OR data_fim > CURDATE())
    ");
    $stmt->execute([$data['id']]);
    
    $db->commit();
    
    // Invalidar cache dos funcionários afetados
    foreach ($funcionariosAfetados as $funcId) {
        Permissoes::invalidateCache($funcId);
    }
    
    // Registrar na auditoria
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'roles',
        'acao' => 'DELETE',
        'registro_id' => $data['id'],
        'detalhes' => [
            'role_codigo' => $role['codigo'],
            'funcionarios_afetados' => count($funcionariosAfetados)
        ]
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Role excluída com sucesso']);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao excluir role: ' . $e->getMessage()]);
}