<?php
/**
 * API para alterar permissão de relatórios comerciais
 * Apenas diretor do comercial ou funcionária ID 71 podem alterar
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

header('Content-Type: application/json');

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$usuarioLogado = $auth->getUser();

// Verificar se pode gerenciar permissões (diretor do comercial ou ID 71)
$podeGerenciar = false;
if ($usuarioLogado['id'] == 71 || 
    ($usuarioLogado['cargo'] == 'Diretor' && $usuarioLogado['departamento_id'] == 10) ||
    Permissoes::tem('SUPER_ADMIN')) {
    $podeGerenciar = true;
}

if (!$podeGerenciar) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para gerenciar']);
    exit;
}

// Pegar dados da requisição
$data = json_decode(file_get_contents('php://input'), true);
$funcionarioId = $data['funcionario_id'] ?? null;
$conceder = $data['conceder'] ?? false;

if (!$funcionarioId) {
    echo json_encode(['success' => false, 'message' => 'ID do funcionário não informado']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar ID do recurso de relatórios
    $stmt = $db->prepare("SELECT id FROM recursos WHERE codigo = 'COMERCIAL_RELATORIOS'");
    $stmt->execute();
    $recursoId = $stmt->fetchColumn();
    
    if (!$recursoId) {
        throw new Exception("Recurso de relatórios não encontrado");
    }
    
    // Buscar ID da permissão FULL
    $stmt = $db->prepare("SELECT id FROM permissoes WHERE codigo = 'FULL'");
    $stmt->execute();
    $permissaoId = $stmt->fetchColumn();
    
    if ($conceder) {
        // Conceder permissão
        $stmt = $db->prepare("
            INSERT INTO funcionario_permissoes (
                funcionario_id, recurso_id, permissao_id, tipo, 
                motivo, atribuido_por
            ) VALUES (?, ?, ?, 'GRANT', ?, ?)
            ON DUPLICATE KEY UPDATE
                tipo = 'GRANT',
                atribuido_por = VALUES(atribuido_por),
                criado_em = NOW()
        ");
        
        $stmt->execute([
            $funcionarioId,
            $recursoId,
            $permissaoId,
            'Permissão concedida pelo ' . $usuarioLogado['nome'],
            $usuarioLogado['id']
        ]);
        
        $message = 'Permissão de relatórios concedida com sucesso';
        
    } else {
        // Remover permissão
        $stmt = $db->prepare("
            DELETE FROM funcionario_permissoes 
            WHERE funcionario_id = ? 
            AND recurso_id = ?
        ");
        
        $stmt->execute([$funcionarioId, $recursoId]);
        
        $message = 'Permissão de relatórios removida com sucesso';
    }
    
    // Limpar cache de permissões
    Permissoes::invalidateCache($funcionarioId);
    
    // Registrar na auditoria
    $stmt = $db->prepare("
        INSERT INTO Auditoria (
            tabela, acao, registro_id, funcionario_id, 
            alteracoes, data_hora
        ) VALUES (
            'funcionario_permissoes', 
            ?, 
            ?, 
            ?, 
            ?, 
            NOW()
        )
    ");
    
    $stmt->execute([
        $conceder ? 'GRANT_RELATORIOS' : 'REVOKE_RELATORIOS',
        $funcionarioId,
        $usuarioLogado['id'],
        json_encode([
            'funcionario_alvo' => $funcionarioId,
            'acao' => $conceder ? 'conceder' : 'remover',
            'recurso' => 'COMERCIAL_RELATORIOS'
        ])
    ]);
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    error_log("Erro ao alterar permissão: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação']);
}