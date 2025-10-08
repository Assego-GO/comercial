<?php
// ============================================
// api/permissoes/editar_role.php - NOVA
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
    echo json_encode(['error' => 'Sem permissão para editar roles']);
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
    
    // Buscar dados atuais para comparação
    $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$data['id']]);
    $roleAtual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$roleAtual) {
        http_response_code(404);
        echo json_encode(['error' => 'Role não encontrada']);
        exit;
    }
    
    // Preparar alterações para auditoria
    $alteracoes = [];
    
    // Atualizar role
    $updates = [];
    $params = [];
    
    if (isset($data['nome']) && $data['nome'] != $roleAtual['nome']) {
        $updates[] = "nome = ?";
        $params[] = $data['nome'];
        $alteracoes[] = [
            'campo' => 'nome',
            'valor_anterior' => $roleAtual['nome'],
            'valor_novo' => $data['nome']
        ];
    }
    
    if (isset($data['descricao']) && $data['descricao'] != $roleAtual['descricao']) {
        $updates[] = "descricao = ?";
        $params[] = $data['descricao'];
        $alteracoes[] = [
            'campo' => 'descricao',
            'valor_anterior' => $roleAtual['descricao'],
            'valor_novo' => $data['descricao']
        ];
    }
    
    if (isset($data['nivel_hierarquia']) && $data['nivel_hierarquia'] != $roleAtual['nivel_hierarquia']) {
        $updates[] = "nivel_hierarquia = ?";
        $params[] = $data['nivel_hierarquia'];
        $alteracoes[] = [
            'campo' => 'nivel_hierarquia',
            'valor_anterior' => $roleAtual['nivel_hierarquia'],
            'valor_novo' => $data['nivel_hierarquia']
        ];
    }
    
    if (isset($data['ativo']) && $data['ativo'] != $roleAtual['ativo']) {
        $updates[] = "ativo = ?";
        $params[] = $data['ativo'];
        $alteracoes[] = [
            'campo' => 'ativo',
            'valor_anterior' => $roleAtual['ativo'],
            'valor_novo' => $data['ativo']
        ];
    }
    
    if (!empty($updates)) {
        $updates[] = "atualizado_em = NOW()";
        $params[] = $data['id'];
        
        $sql = "UPDATE roles SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Invalidar cache de todos os funcionários com esta role
        $stmt = $db->prepare("
            SELECT DISTINCT funcionario_id 
            FROM funcionario_roles 
            WHERE role_id = ? 
            AND (data_fim IS NULL OR data_fim >= CURDATE())
        ");
        $stmt->execute([$data['id']]);
        
        while ($row = $stmt->fetch()) {
            Permissoes::invalidateCache($row['funcionario_id']);
        }
        
        // Registrar na auditoria
        $auditoria = new Auditoria();
        $auditoria->registrar([
            'tabela' => 'roles',
            'acao' => 'UPDATE',
            'registro_id' => $data['id'],
            'alteracoes' => $alteracoes,
            'detalhes' => [
                'role_codigo' => $roleAtual['codigo'],
                'role_nome' => $roleAtual['nome'],
                'total_alteracoes' => count($alteracoes)
            ]
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Role atualizada com sucesso',
            'alteracoes' => count($alteracoes)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Nenhuma alteração detectada'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao editar role: ' . $e->getMessage()]);
}