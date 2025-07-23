<?php
/**
 * API para alterar senha do usuário
 * api/alterar_senha.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Pegar dados do usuário logado
$usuarioLogado = $auth->getUser();

// Pegar dados do POST
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || !isset($dados['senha_atual']) || !isset($dados['nova_senha'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar senha atual do banco
    $stmt = $db->prepare("SELECT senha FROM Funcionarios WHERE id = ?");
    $stmt->execute([$usuarioLogado['id']]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        throw new Exception('Funcionário não encontrado');
    }
    
    // Verificar senha atual
    if (!password_verify($dados['senha_atual'], $funcionario['senha'])) {
        throw new Exception('Senha atual incorreta');
    }
    
    // Validar nova senha
    if (strlen($dados['nova_senha']) < 6) {
        throw new Exception('A nova senha deve ter no mínimo 6 caracteres');
    }
    
    // Hash da nova senha
    $nova_senha_hash = password_hash($dados['nova_senha'], PASSWORD_DEFAULT);
    
    // Atualizar senha
    $stmt = $db->prepare("
        UPDATE Funcionarios 
        SET senha = ?, senha_alterada_em = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$nova_senha_hash, $usuarioLogado['id']]);
    
    // Registrar na auditoria
    $stmt = $db->prepare("
        INSERT INTO Auditoria (
            tabela, 
            acao, 
            registro_id, 
            funcionario_id, 
            alteracoes, 
            ip_origem, 
            browser_info,
            data_hora
        ) VALUES (
            'Funcionarios',
            'UPDATE',
            :funcionario_id,
            :funcionario_id,
            :alteracoes,
            :ip,
            :browser,
            NOW()
        )
    ");
    
    $alteracoes = json_encode([
        'campo' => 'senha',
        'motivo' => 'Alteração de senha pelo próprio usuário'
    ]);
    
    $stmt->execute([
        'funcionario_id' => $usuarioLogado['id'],
        'alteracoes' => $alteracoes,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'browser' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Senha alterada com sucesso'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao alterar senha: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}