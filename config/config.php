<?php
/**
 * Configurações gerais do sistema
 * config/config.php
 */

// Configuração de Timezone
date_default_timezone_set('America/Sao_Paulo');

// Informações do sistema

define('SISTEMA_NOME', 'Sistema De Gestão');
define('SISTEMA_VERSAO', '1.0.0');
define('SISTEMA_EMPRESA', 'ASSEGO');

// URLs borreca
define('BASE_URL', 'http://172.16.253.44/gabriel/comercial'); // Ajuste conforme seu ambientei

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


define('CLOUDFLARE_TURNSTILE_SITE_KEY', getenv('CLOUDFLARE_TURNSTILE_SITE_KEY') ?: '1x00000000000000000000AA');
define('CLOUDFLARE_TURNSTILE_SECRET_KEY', getenv('CLOUDFLARE_TURNSTILE_SECRET_KEY') ?: '1x0000000000000000000000000000000AA');

define('API_KEY', 'f0a60b1b-0a4c-413a-9c28-47f5dfd78c18021a89f2-9286-4cb9-b1f2-689d4b0b6f24');
