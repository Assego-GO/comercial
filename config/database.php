<?php
/**
 * Configurações de conexão com o banco de dados
 * config/database.php
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'wwasse_cadastro');
define('DB_PASS', 'rotam102030');
define('DB_NAME_CADASTRO', 'wwasse_cadastro');
define('DB_NAME_RELATORIOS', 'wwasse_relatorios_diarios');
define('DB_CHARSET', 'utf8mb4');

// Configurações de upload
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50MB
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'avi', 'mov', 'wmv']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Configurações de sessão
ini_set('session.gc_maxlifetime', 36000); // 10 hora
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // HTTPS se disponível

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro (desenvolvimento)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Em produção, mude para:
// error_reporting(0);
// ini_set('display_errors', 0);