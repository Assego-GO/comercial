<?php
/**
 * Configurações de sessão e timeout
 * session-config.php
 * 
 * Este arquivo é rastreado pelo git e compartilhado entre todos os ambientes
 */

// Configurações de sessão
define('SESSAO_TEMPO_VIDA', 28800); // 8 horas

// Configurar parâmetros de sessão ANTES de session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 28800); // 8 horas
    ini_set('session.cookie_lifetime', 28800); // 8 horas
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // HTTPS se disponível
    
    // Configurar cookie params
    session_set_cookie_params(28800); // 8 horas
}
