<?php
// ============================================
// api/permissoes/criar_role.php - CORRIGIDA
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
if (!$permissoes->isSuperAdmin() && !$permissoes->hasPermission('SISTEMA_PERMISSOES', 'CREATE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão para criar roles']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['codigo']) || empty($data['nome'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Código e nome são obrigatórios']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Verificar se código já existe
    $stmt = $db->prepare("SELECT id FROM roles WHERE codigo = ?");
    $stmt->execute([$data['codigo']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Já existe uma role com este código']);
        exit;
    }
    
    // Inserir nova role
    $sql = "INSERT INTO roles (codigo, nome, descricao, nivel_hierarquia, tipo, ativo) 
            VALUES (?, ?, ?, ?, ?, 1)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        strtoupper($data['codigo']),
        $data['nome'],
        $data['descricao'] ?? null,
        $data['nivel_hierarquia'] ?? 400,
        $data['tipo'] ?? 'CUSTOMIZADO'
    ]);
    
    $roleId = $db->lastInsertId();
    
    // Registrar na auditoria usando a classe correta
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'roles',
        'acao' => 'CREATE',
        'registro_id' => $roleId,
        'detalhes' => [
            'codigo' => strtoupper($data['codigo']),
            'nome' => $data['nome'],
            'nivel_hierarquia' => $data['nivel_hierarquia'] ?? 400,
            'tipo' => $data['tipo'] ?? 'CUSTOMIZADO'
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Role criada com sucesso',
        'id' => $roleId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar role: ' . $e->getMessage()]);
}