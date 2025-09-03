<?php
// ============================================
// api/permissoes/criar_recurso.php
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
    echo json_encode(['error' => 'Apenas Super Admin pode criar recursos']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validações
if (empty($data['codigo']) || empty($data['nome']) || 
    empty($data['categoria']) || empty($data['modulo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados obrigatórios faltando']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // Verificar se código já existe
    $stmt = $db->prepare("SELECT id FROM recursos WHERE codigo = ?");
    $stmt->execute([$data['codigo']]);
    if ($stmt->fetch()) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Já existe um recurso com este código']);
        exit;
    }
    
    // Buscar nome do departamento se especificado
    $departamentoNome = null;
    if (!empty($data['departamento_id'])) {
        $stmt = $db->prepare("SELECT nome FROM Departamentos WHERE id = ?");
        $stmt->execute([$data['departamento_id']]);
        $deptInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $departamentoNome = $deptInfo['nome'] ?? null;
    }
    
    // Inserir novo recurso
    $sql = "INSERT INTO recursos 
            (codigo, nome, descricao, categoria, modulo, tipo, 
             departamento_id, rota, icone, ordem, requer_autenticacao, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        strtoupper($data['codigo']),
        $data['nome'],
        $data['descricao'] ?? null,
        $data['categoria'],
        $data['modulo'],
        $data['tipo'] ?? 'PAGINA',
        $data['departamento_id'] ?? null,
        $data['rota'] ?? null,
        $data['icone'] ?? null,
        $data['ordem'] ?? 0,
        $data['requer_autenticacao'] ?? 1
    ]);
    
    $recursoId = $db->lastInsertId();
    
    $db->commit();
    
    // Registrar na auditoria CORRETAMENTE
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'recursos',
        'acao' => 'CREATE',
        'registro_id' => $recursoId,
        'detalhes' => [
            'codigo' => strtoupper($data['codigo']),
            'nome' => $data['nome'],
            'categoria' => $data['categoria'],
            'modulo' => $data['modulo'],
            'tipo' => $data['tipo'] ?? 'PAGINA',
            'departamento' => $departamentoNome,
            'rota' => $data['rota'] ?? null,
            'icone' => $data['icone'] ?? null,
            'requer_autenticacao' => $data['requer_autenticacao'] ?? true
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Recurso criado com sucesso',
        'id' => $recursoId
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar recurso: ' . $e->getMessage()]);
}
?>