<?php
/**
 * Página de Logout Simplificada
 * logout.php
 */

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guardar nome do usuário para mensagem (opcional)
$nome_usuario = $_SESSION['user_name'] ?? $_SESSION['funcionario_nome'] ?? '';

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Destruir cookie de sessão se existir
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"]);
}

// Destruir a sessão
session_destroy();

// Limpar cookies de "lembrar-me" (se existirem)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Preparar mensagem de logout
$mensagem = !empty($nome_usuario) 
    ? "Logout realizado com sucesso. Até logo, " . htmlspecialchars($nome_usuario) . "!"
    : "Logout realizado com sucesso!";

// Headers de segurança básicos
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// Redirecionar para a página de login
header('Location: index.php?msg=' . urlencode($mensagem));
exit();
?>