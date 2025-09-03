<?php
// ============================================
// api/permissoes/configurar_permissoes_role.php
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
    echo json_encode(['error' => 'Sem permissão para configurar permissões de roles']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['role_id']) || !isset($data['permissoes'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Role ID e permissões são obrigatórios']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // Buscar informações da role
    $stmt = $db->prepare("SELECT nome, codigo FROM roles WHERE id = ?");
    $stmt->execute([$data['role_id']]);
    $roleInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$roleInfo) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Role não encontrada']);
        exit;
    }
    
    // Buscar permissões antigas para comparação
    $stmt = $db->prepare("
        SELECT 
            rp.*,
            rec.nome as recurso_nome,
            p.nome as permissao_nome
        FROM role_permissoes rp
        JOIN recursos rec ON rp.recurso_id = rec.id
        JOIN permissoes p ON rp.permissao_id = p.id
        WHERE rp.role_id = ?
    ");
    $stmt->execute([$data['role_id']]);
    $permissoesAntigas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Remover permissões antigas
    $stmt = $db->prepare("DELETE FROM role_permissoes WHERE role_id = ?");
    $stmt->execute([$data['role_id']]);
    
    // Inserir novas permissões
    $sql = "INSERT INTO role_permissoes (role_id, recurso_id, permissao_id, condicoes) 
            VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    $novasPermissoes = [];
    foreach ($data['permissoes'] as $perm) {
        if (!empty($perm['recurso_id']) && !empty($perm['permissao_id'])) {
            $stmt->execute([
                $data['role_id'],
                $perm['recurso_id'],
                $perm['permissao_id'],
                isset($perm['condicoes']) ? json_encode($perm['condicoes']) : null
            ]);
            
            // Buscar nomes para o log
            $stmtInfo = $db->prepare("
                SELECT 
                    rec.nome as recurso_nome,
                    p.nome as permissao_nome
                FROM recursos rec, permissoes p
                WHERE rec.id = ? AND p.id = ?
            ");
            $stmtInfo->execute([$perm['recurso_id'], $perm['permissao_id']]);
            $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            
            $novasPermissoes[] = [
                'recurso' => $info['recurso_nome'] ?? 'ID: ' . $perm['recurso_id'],
                'permissao' => $info['permissao_nome'] ?? 'ID: ' . $perm['permissao_id']
            ];
        }
    }
    
    $db->commit();
    
    // Invalidar cache de todos os funcionários com esta role
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
    
    // Registrar na auditoria CORRETAMENTE
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'role_permissoes',
        'acao' => 'CONFIGURAR',
        'registro_id' => $data['role_id'],
        'detalhes' => [
            'role' => $roleInfo['nome'],
            'codigo_role' => $roleInfo['codigo'],
            'permissoes_antigas' => count($permissoesAntigas),
            'permissoes_novas' => count($novasPermissoes),
            'permissoes_configuradas' => $novasPermissoes
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Permissões da role atualizadas com sucesso',
        'total_permissoes' => count($novasPermissoes)
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao configurar permissões: ' . $e->getMessage()]);
}
