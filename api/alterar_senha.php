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
    
    // Determinar tipo de usuário (funcionário ou associado)
    $tipoUsuario = $usuarioLogado['tipo_usuario'] ?? 'funcionario';
    $tabela = ($tipoUsuario === 'associado') ? 'Associados' : 'Funcionarios';
    
    // Buscar senha atual do banco
    $stmt = $db->prepare("SELECT senha FROM {$tabela} WHERE id = ?");
    $stmt->execute([$usuarioLogado['id']]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        throw new Exception('Usuário não encontrado');
    }
    
    // Verificar senha atual
    // Verifica se a senha está com hash ou em texto plano (MD5/SHA1)
    $senha_correta = false;
    
    if (password_verify($dados['senha_atual'], $usuario['senha'])) {
        // Senha com hash password_hash()
        $senha_correta = true;
    } elseif ($usuario['senha'] === md5($dados['senha_atual'])) {
        // Senha com MD5
        $senha_correta = true;
    } elseif ($usuario['senha'] === sha1($dados['senha_atual'])) {
        // Senha com SHA1
        $senha_correta = true;
    } elseif ($usuario['senha'] === $dados['senha_atual']) {
        // Senha em texto plano
        $senha_correta = true;
    }
    
    if (!$senha_correta) {
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
        UPDATE {$tabela} 
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
            :tabela,
            'UPDATE',
            :registro_id,
            :funcionario_id,
            :alteracoes,
            :ip,
            :browser,
            NOW()
        )
    ");
    
    $alteracoes = json_encode([
        'campo' => 'senha',
        'motivo' => 'Alteração de senha pelo próprio usuário',
        'tipo_usuario' => $tipoUsuario
    ]);
    
    $stmt->execute([
        'tabela' => $tabela,
        'registro_id' => $usuarioLogado['id'],
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
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}