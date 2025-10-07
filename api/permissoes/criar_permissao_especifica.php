<?php
// ============================================
// api/permissoes/criar_permissao_especifica.php
// ============================================
// ============================================
// api/permissoes/criar_permissao_especifica.php - CORRIGIDA
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
    echo json_encode(['error' => 'Sem permissão para criar permissões específicas']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validações
if (empty($data['funcionario_id']) || empty($data['recurso_id']) || 
    empty($data['permissao_id']) || empty($data['tipo']) || empty($data['motivo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados obrigatórios faltando']);
    exit;
}

// Validar tipo
if (!in_array($data['tipo'], ['GRANT', 'DENY'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo deve ser GRANT ou DENY']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    $usuarioLogado = $auth->getUser();
    $atribuidoPor = $usuarioLogado['id'] ?? null;
    
    // Buscar informações para o log
    $stmt = $db->prepare("SELECT nome FROM Funcionarios WHERE id = ?");
    $stmt->execute([$data['funcionario_id']]);
    $funcInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT nome, codigo, categoria FROM recursos WHERE id = ?");
    $stmt->execute([$data['recurso_id']]);
    $recursoInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT nome, codigo FROM permissoes WHERE id = ?");
    $stmt->execute([$data['permissao_id']]);
    $permissaoInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se já existe
    $sql = "SELECT id, tipo, motivo FROM funcionario_permissoes 
            WHERE funcionario_id = ? 
            AND recurso_id = ? 
            AND permissao_id = ?
            AND (data_fim IS NULL OR data_fim >= CURDATE())";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $data['funcionario_id'],
        $data['recurso_id'],
        $data['permissao_id']
    ]);
    
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);
    $alteracoes = [];
    
    if ($existente) {
        // Preparar alterações para auditoria
        if ($existente['tipo'] != $data['tipo']) {
            $alteracoes[] = [
                'campo' => 'tipo',
                'valor_anterior' => $existente['tipo'],
                'valor_novo' => $data['tipo']
            ];
        }
        
        if ($existente['motivo'] != $data['motivo']) {
            $alteracoes[] = [
                'campo' => 'motivo',
                'valor_anterior' => $existente['motivo'],
                'valor_novo' => $data['motivo']
            ];
        }
        
        // Atualizar existente
        $sql = "UPDATE funcionario_permissoes 
                SET tipo = ?, motivo = ?, data_inicio = ?, data_fim = ?, 
                    atribuido_por = ?, criado_em = NOW()
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['tipo'],
            $data['motivo'],
            $data['data_inicio'] ?? date('Y-m-d'),
            $data['data_fim'] ?? null,
            $atribuidoPor,
            $existente['id']
        ]);
        
        $permissaoId = $existente['id'];
        $action = 'UPDATE';
    } else {
        // Inserir nova
        $sql = "INSERT INTO funcionario_permissoes 
                (funcionario_id, recurso_id, permissao_id, tipo, motivo, 
                 data_inicio, data_fim, atribuido_por) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['funcionario_id'],
            $data['recurso_id'],
            $data['permissao_id'],
            $data['tipo'],
            $data['motivo'],
            $data['data_inicio'] ?? date('Y-m-d'),
            $data['data_fim'] ?? null,
            $atribuidoPor
        ]);
        
        $permissaoId = $db->lastInsertId();
        $action = 'CREATE';
    }
    
    $db->commit();
    
    // Invalidar cache
    Permissoes::invalidateCache($data['funcionario_id']);
    
    // Registrar na auditoria CORRETAMENTE
    $auditoria = new Auditoria();
    
    if ($action == 'UPDATE' && !empty($alteracoes)) {
        $auditoria->registrar([
            'tabela' => 'funcionario_permissoes',
            'acao' => 'UPDATE',
            'registro_id' => $permissaoId,
            'alteracoes' => $alteracoes,
            'detalhes' => [
                'funcionario' => $funcInfo['nome'] ?? 'ID: ' . $data['funcionario_id'],
                'recurso' => $recursoInfo['nome'] ?? 'ID: ' . $data['recurso_id'],
                'permissao' => $permissaoInfo['nome'] ?? 'ID: ' . $data['permissao_id'],
                'tipo_permissao' => $data['tipo']
            ]
        ]);
    } else {
        $auditoria->registrar([
            'tabela' => 'funcionario_permissoes',
            'acao' => 'CREATE',
            'registro_id' => $permissaoId,
            'detalhes' => [
                'funcionario' => $funcInfo['nome'] ?? 'ID: ' . $data['funcionario_id'],
                'recurso' => $recursoInfo['nome'] ?? 'ID: ' . $data['recurso_id'],
                'categoria_recurso' => $recursoInfo['categoria'] ?? null,
                'permissao' => $permissaoInfo['nome'] ?? 'ID: ' . $data['permissao_id'],
                'tipo_permissao' => $data['tipo'],
                'motivo' => $data['motivo'],
                'data_inicio' => $data['data_inicio'] ?? date('Y-m-d'),
                'data_fim' => $data['data_fim'] ?? null
            ]
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Permissão específica ' . ($action == 'CREATE' ? 'criada' : 'atualizada') . ' com sucesso',
        'id' => $permissaoId
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar permissão: ' . $e->getMessage()]);
}
