<?php
// ============================================
// api/permissoes/atribuir_role.php
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
    echo json_encode(['error' => 'Sem permissão para atribuir roles']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['funcionario_id']) || empty($data['role_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Funcionário e role são obrigatórios']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // Buscar nome da role para o log
    $stmt = $db->prepare("SELECT nome, codigo FROM roles WHERE id = ?");
    $stmt->execute([$data['role_id']]);
    $roleInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar nome do funcionário
    $stmt = $db->prepare("SELECT nome FROM Funcionarios WHERE id = ?");
    $stmt->execute([$data['funcionario_id']]);
    $funcInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se já existe esta atribuição
    $sql = "SELECT id FROM funcionario_roles 
            WHERE funcionario_id = ? AND role_id = ? 
            AND departamento_id " . ($data['departamento_id'] ? "= ?" : "IS NULL") . "
            AND (data_fim IS NULL OR data_fim >= CURDATE())";
    
    $params = [$data['funcionario_id'], $data['role_id']];
    if ($data['departamento_id']) {
        $params[] = $data['departamento_id'];
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetch()) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Esta role já está atribuída a este funcionário']);
        exit;
    }
    
    // Se marcar como principal, desmarcar outras
    if (!empty($data['principal'])) {
        $sql = "UPDATE funcionario_roles 
                SET principal = 0 
                WHERE funcionario_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$data['funcionario_id']]);
    }
    
    // Inserir atribuição
    $sql = "INSERT INTO funcionario_roles 
            (funcionario_id, role_id, departamento_id, data_inicio, data_fim, 
             principal, atribuido_por, observacao) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $usuarioLogado = $auth->getUser();
    $atribuidoPor = $usuarioLogado['id'] ?? null;
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $data['funcionario_id'],
        $data['role_id'],
        $data['departamento_id'] ?? null,
        $data['data_inicio'] ?? date('Y-m-d'),
        $data['data_fim'] ?? null,
        $data['principal'] ?? 0,
        $atribuidoPor,
        $data['observacao'] ?? null
    ]);
    
    $atribuicaoId = $db->lastInsertId();
    
    $db->commit();
    
    // Invalidar cache de permissões
    Permissoes::invalidateCache($data['funcionario_id']);
    
    // Registrar na auditoria
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'funcionario_roles',
        'acao' => 'ATRIBUIR_ROLE',
        'registro_id' => $atribuicaoId,
        'funcionario_id' => $atribuidoPor,
        'detalhes' => [
            'funcionario_alvo' => $funcInfo['nome'] ?? 'ID: ' . $data['funcionario_id'],
            'role_atribuida' => $roleInfo['nome'] ?? 'ID: ' . $data['role_id'],
            'codigo_role' => $roleInfo['codigo'] ?? null,
            'departamento_id' => $data['departamento_id'] ?? null,
            'principal' => $data['principal'] ?? 0,
            'data_inicio' => $data['data_inicio'] ?? date('Y-m-d'),
            'data_fim' => $data['data_fim'] ?? null
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Role atribuída com sucesso',
        'id' => $atribuicaoId
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atribuir role: ' . $e->getMessage()]);
}