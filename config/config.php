<?php
/**
 * Configurações gerais do sistema
 * config/config.php
 */

// Informações do sistema
define('SISTEMA_NOME', 'Sistema Comercial Assego');
define('SISTEMA_VERSAO', '1.0.0');
define('SISTEMA_EMPRESA', 'ASSEGO');

// URLs base
define('BASE_URL', 'http://172.16.253.44/matheus/comercial'); // Ajuste conforme seu ambiente
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Configurações de email (para notificações futuras)
define('EMAIL_FROM', 'noreply@assego.com.br');
define('EMAIL_FROM_NAME', 'Sistema Comercial Assego');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls');


// Configurações de segurança
define('SENHA_MIN_CARACTERES', 8);
define('SESSAO_TEMPO_VIDA', 3600); // 1 hora
define('MAX_TENTATIVAS_LOGIN', 5);
define('BLOQUEIO_TEMPO_MINUTOS', 30);

// Configurações de paginação
define('REGISTROS_POR_PAGINA', 20);

// Configurações de auto-save
define('AUTOSAVE_INTERVALO', 300); // 5 minutos em segundos

// Modo debug (desativar em produção)
define('DEBUG_MODE', true);

