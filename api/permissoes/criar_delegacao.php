<?php
// ============================================
// api/permissoes/criar_delegacao.php - CORRIGIDA
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

$data = json_decode(file_get_contents('php://input'), true);

// Validações básicas
if (empty($data['delegante_id']) || empty($data['delegado_id']) || 
    empty($data['data_inicio']) || empty($data['data_fim']) || empty($data['motivo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados obrigatórios faltando']);
    exit;
}

// Verificar se o usuário pode criar delegação
$permissoes = Permissoes::getInstance();
$usuarioLogado = $auth->getUser();
$funcionarioId = $usuarioLogado['id'] ?? null;

// Pode delegar se:
// 1. É super admin
// 2. Tem permissão específica
// 3. É o próprio delegante (delegando suas próprias permissões)
$podeDelegar = $permissoes->isSuperAdmin() || 
               $permissoes->hasPermission('SISTEMA_PERMISSOES', 'CREATE') ||
               $data['delegante_id'] == $funcionarioId;

if (!$podeDelegar) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão para criar delegação']);
    exit;
}

// Validar datas
$dataInicio = new DateTime($data['data_inicio']);
$dataFim = new DateTime($data['data_fim']);

if ($dataFim <= $dataInicio) {
    http_response_code(400);
    echo json_encode(['error' => 'Data fim deve ser posterior à data início']);
    exit;
}

// Limite máximo de delegação: 90 dias
$diff = $dataInicio->diff($dataFim);
if ($diff->days > 90) {
    http_response_code(400);
    echo json_encode(['error' => 'Delegação não pode exceder 90 dias']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // Buscar nomes para o log
    $stmt = $db->prepare("SELECT nome FROM Funcionarios WHERE id = ?");
    $stmt->execute([$data['delegante_id']]);
    $deleganteInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT nome FROM Funcionarios WHERE id = ?");
    $stmt->execute([$data['delegado_id']]);
    $delegadoInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar nome da role se especificada
    $roleInfo = null;
    if (!empty($data['role_id'])) {
        $stmt = $db->prepare("SELECT nome, codigo FROM roles WHERE id = ?");
        $stmt->execute([$data['role_id']]);
        $roleInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Buscar nome do recurso se especificado
    $recursoInfo = null;
    if (!empty($data['recurso_id'])) {
        $stmt = $db->prepare("SELECT nome, codigo FROM recursos WHERE id = ?");
        $stmt->execute([$data['recurso_id']]);
        $recursoInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Verificar conflitos de delegação
    $sql = "SELECT COUNT(*) FROM delegacoes 
            WHERE delegante_id = ? 
            AND delegado_id = ? 
            AND ativo = 1
            AND (
                (? BETWEEN data_inicio AND data_fim) OR
                (? BETWEEN data_inicio AND data_fim) OR
                (data_inicio BETWEEN ? AND ?) OR
                (data_fim BETWEEN ? AND ?)
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $data['delegante_id'],
        $data['delegado_id'],
        $data['data_inicio'],
        $data['data_fim'],
        $data['data_inicio'],
        $data['data_fim'],
        $data['data_inicio'],
        $data['data_fim']
    ]);
    
    if ($stmt->fetchColumn() > 0) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode(['error' => 'Já existe delegação ativa neste período']);
        exit;
    }
    
    // Inserir delegação
    $sql = "INSERT INTO delegacoes 
            (delegante_id, delegado_id, role_id, recurso_id, 
             data_inicio, data_fim, motivo, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $data['delegante_id'],
        $data['delegado_id'],
        $data['role_id'] ?? null,
        $data['recurso_id'] ?? null,
        $data['data_inicio'],
        $data['data_fim'],
        $data['motivo']
    ]);
    
    $delegacaoId = $db->lastInsertId();
    
    $db->commit();
    
    // Invalidar cache do delegado
    Permissoes::invalidateCache($data['delegado_id']);
    
    // Registrar na auditoria CORRETAMENTE
    $auditoria = new Auditoria();
    $auditoria->registrar([
        'tabela' => 'delegacoes',
        'acao' => 'CREATE',
        'registro_id' => $delegacaoId,
        'funcionario_id' => $funcionarioId, // Será detectado automaticamente se null
        'detalhes' => [
            'delegante' => $deleganteInfo['nome'] ?? 'ID: ' . $data['delegante_id'],
            'delegado' => $delegadoInfo['nome'] ?? 'ID: ' . $data['delegado_id'],
            'role' => $roleInfo ? $roleInfo['nome'] : 'Todas as roles',
            'recurso' => $recursoInfo ? $recursoInfo['nome'] : 'Todos os recursos',
            'periodo' => $data['data_inicio'] . ' até ' . $data['data_fim'],
            'dias' => $diff->days,
            'motivo' => $data['motivo']
        ]
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Delegação criada com sucesso',
        'id' => $delegacaoId
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar delegação: ' . $e->getMessage()]);
}