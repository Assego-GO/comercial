<?php
/**
 * Página de Logout
 * logout.php
 * 
 * Responsável por encerrar a sessão do usuário e realizar limpezas necessárias
 */

// Incluir configurações
require_once '../config/config.php';
require_once '../config/database.php';

// Incluir classes necessárias
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Criar instância da classe Auth
$auth = new Auth();

// Registrar ação de logout na auditoria (opcional)
if (isset($_SESSION['funcionario_id'])) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Registrar logout na tabela de auditoria
        $stmt = $db->prepare("
            INSERT INTO Auditoria (
                tabela, 
                acao, 
                funcionario_id, 
                alteracoes, 
                ip_origem, 
                browser_info,
                sessao_id,
                data_hora
            ) VALUES (
                'Funcionarios',
                'LOGOUT',
                :funcionario_id,
                :alteracoes,
                :ip,
                :browser,
                :sessao,
                NOW()
            )
        ");
        
        $alteracoes = json_encode([
            'funcionario_nome' => $_SESSION['funcionario_nome'] ?? 'Desconhecido',
            'funcionario_email' => $_SESSION['funcionario_email'] ?? 'Desconhecido',
            'tempo_sessao' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0,
            'motivo' => $_GET['motivo'] ?? 'logout_manual'
        ]);
        
        $stmt->execute([
            'funcionario_id' => $_SESSION['funcionario_id'],
            'alteracoes' => $alteracoes,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'browser' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'sessao' => session_id()
        ]);
        
    } catch (Exception $e) {
        // Log de erro se falhar ao registrar logout
        error_log("Erro ao registrar logout: " . $e->getMessage());
    }
}

// Guardar informações para mensagem de logout (opcional)
$nome_usuario = $_SESSION['funcionario_nome'] ?? '';
$motivo_logout = $_GET['motivo'] ?? 'manual';

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Se desejar, destruir também o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_destroy();

// Limpar cookies de "lembrar-me" se existirem
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
if (isset($_COOKIE['user_email'])) {
    setcookie('user_email', '', time() - 3600, '/');
}

// Preparar mensagem de logout baseada no motivo
$mensagem = '';
$tipo_mensagem = 'info';

switch ($motivo_logout) {
    case 'sessao_expirada':
        $mensagem = 'Sua sessão expirou. Por favor, faça login novamente.';
        $tipo_mensagem = 'warning';
        break;
    case 'acesso_negado':
        $mensagem = 'Acesso negado. Por favor, faça login com as credenciais apropriadas.';
        $tipo_mensagem = 'error';
        break;
    case 'conta_bloqueada':
        $mensagem = 'Sua conta foi bloqueada. Entre em contato com o administrador.';
        $tipo_mensagem = 'error';
        break;
    case 'manutencao':
        $mensagem = 'Sistema em manutenção. Tente novamente mais tarde.';
        $tipo_mensagem = 'warning';
        break;
    case 'senha_alterada':
        $mensagem = 'Senha alterada com sucesso. Por favor, faça login novamente.';
        $tipo_mensagem = 'success';
        break;
    default:
        if (!empty($nome_usuario)) {
            $mensagem = 'Logout realizado com sucesso. Até logo, ' . htmlspecialchars($nome_usuario) . '!';
            $tipo_mensagem = 'success';
        } else {
            $mensagem = 'Logout realizado com sucesso!';
            $tipo_mensagem = 'success';
        }
}

// Codificar mensagem para passar via URL
$mensagem_encoded = urlencode($mensagem);
$tipo_encoded = urlencode($tipo_mensagem);

// Redirecionar para a página de login com mensagem
$redirect_url = BASE_URL . '/index.php?mensagem=' . $mensagem_encoded . '&tipo=' . $tipo_encoded;

// Se houver uma URL de retorno específica
if (isset($_GET['redirect'])) {
    $redirect_url .= '&redirect=' . urlencode($_GET['redirect']);
}

// Adicionar headers de segurança
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirecionar
header('Location: ' . $redirect_url);
exit();

/**
 * Função alternativa de logout via AJAX (opcional)
 * Para ser chamada via requisição AJAX
 */
if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    // Resposta JSON para requisições AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $mensagem,
        'redirect' => BASE_URL . '/index.php'
    ]);
    exit();
}
?>