<?php

require_once '../config/config.php';
require_once '../config/database.php';

require_once '../classes/Database.php';
require_once '../classes/Auth.php';

/**
 * Classe para gerenciar Rate Limiting de tentativas de login
 */
class LoginRateLimit {
    private $maxAttempts = 100;// mudar em produção
    private $lockoutTime = 9; // mudar em produção
    private $globalMaxAttempts = 500; 
    private $globalLockoutTime = 18; // mudar em produção
    private $minTimeBetweenAttempts = 0.5; 
    private $dataFile;
    private $globalFile;
    
    public function __construct() {
        // Criar diretório para armazenar dados se não existir
        $dataDir = '../data/rate_limit';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->dataFile = $dataDir . '/login_attempts.json';
        $this->globalFile = $dataDir . '/global_attempts.json';
    }
    
    /**
     * Carrega dados de tentativas do arquivo
     */
    private function loadAttempts() {
        if (!file_exists($this->dataFile)) {
            return [];
        }
        
        $data = file_get_contents($this->dataFile);
        $attempts = json_decode($data, true);
        
        return is_array($attempts) ? $attempts : [];
    }
    
    /**
     * Salva dados de tentativas no arquivo
     */
    private function saveAttempts($attempts) {
        file_put_contents($this->dataFile, json_encode($attempts, JSON_PRETTY_PRINT));
    }
    
    /**
     * Carrega dados globais de tentativas
     */
    private function loadGlobalAttempts() {
        if (!file_exists($this->globalFile)) {
            return ['attempts' => 0, 'last_attempt' => 0, 'ips' => []];
        }
        
        $data = file_get_contents($this->globalFile);
        $attempts = json_decode($data, true);
        
        return is_array($attempts) ? $attempts : ['attempts' => 0, 'last_attempt' => 0, 'ips' => []];
    }
    
    /**
     * Salva dados globais de tentativas
     */
    private function saveGlobalAttempts($attempts) {
        file_put_contents($this->globalFile, json_encode($attempts, JSON_PRETTY_PRINT));
    }
    
    /**
     * Registra uma tentativa global (MÉTODO QUE ESTAVA FALTANDO)
     */
    private function recordGlobalAttempt($ip) {
        $globalData = $this->loadGlobalAttempts();
        $currentTime = time();
        
        // Incrementar contador global
        $globalData['attempts']++;
        $globalData['last_attempt'] = $currentTime;
        
        // Registrar IP único
        if (!in_array($ip, $globalData['ips'])) {
            $globalData['ips'][] = $ip;
        }
        
        // Salvar dados globais
        $this->saveGlobalAttempts($globalData);
        
        return $globalData;
    }
    
    /**
     * Verifica se o sistema está em bloqueio global
     */
    public function isGloballyBlocked() {
        $globalData = $this->loadGlobalAttempts();
        $currentTime = time();
        
        // Se passou do tempo de bloqueio global, resetar
        if (($currentTime - $globalData['last_attempt']) >= $this->globalLockoutTime) {
            if ($globalData['attempts'] >= $this->globalMaxAttempts) {
                // Resetar contador global
                $globalData = ['attempts' => 0, 'last_attempt' => 0, 'ips' => []];
                $this->saveGlobalAttempts($globalData);
            }
            return false;
        }
        
        // Verificar se atingiu o limite global
        if ($globalData['attempts'] >= $this->globalMaxAttempts) {
            return [
                'blocked' => true,
                'attempts' => $globalData['attempts'],
                'time_remaining' => $this->globalLockoutTime - ($currentTime - $globalData['last_attempt']),
                'unique_ips' => count($globalData['ips'])
            ];
        }
        
        return false;
    }
    
    /**
     * Verifica se a tentativa é muito rápida
     */
    public function isTooFast($email, $ip) {
        $attempts = $this->loadAttempts();
        $key = $this->generateKey($email, $ip);
        $currentTime = microtime(true);
        
        if (isset($attempts[$key])) {
            $lastAttemptTime = $attempts[$key]['last_attempt_precise'] ?? $attempts[$key]['last_attempt'];
            $timeDiff = $currentTime - $lastAttemptTime;
            
            if ($timeDiff < $this->minTimeBetweenAttempts) {
                return [
                    'too_fast' => true,
                    'time_diff' => $timeDiff,
                    'required_wait' => $this->minTimeBetweenAttempts - $timeDiff
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Limpa tentativas expiradas
     */
    private function cleanExpiredAttempts($attempts) {
        $currentTime = time();
        $cleaned = [];
        
        foreach ($attempts as $key => $data) {
            if (($currentTime - $data['last_attempt']) < $this->lockoutTime) {
                $cleaned[$key] = $data;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Gera chave única baseada no IP e email
     */
    private function generateKey($email, $ip) {
        return md5($email . '|' . $ip);
    }
    
    /**
     * Verifica se o usuário está bloqueado
     */
    public function isBlocked($email, $ip) {
        $attempts = $this->loadAttempts();
        $attempts = $this->cleanExpiredAttempts($attempts);
        
        $key = $this->generateKey($email, $ip);
        
        if (!isset($attempts[$key])) {
            return false;
        }
        
        $userData = $attempts[$key];
        $currentTime = time();
        
        // Se passou do tempo de bloqueio, libera o usuário
        if (($currentTime - $userData['last_attempt']) >= $this->lockoutTime) {
            unset($attempts[$key]);
            $this->saveAttempts($attempts);
            return false;
        }
        
        // Se atingiu o máximo de tentativas e ainda está no período de bloqueio
        if ($userData['attempts'] >= $this->maxAttempts) {
            return [
                'blocked' => true,
                'attempts' => $userData['attempts'],
                'last_attempt' => $userData['last_attempt'],
                'time_remaining' => $this->lockoutTime - ($currentTime - $userData['last_attempt'])
            ];
        }
        
        return false;
    }
    
    /**
     * Registra uma tentativa de login falhada
     */
    public function recordFailedAttempt($email, $ip) {
        // Registrar tentativa global
        $globalData = $this->recordGlobalAttempt($ip);
        
        $attempts = $this->loadAttempts();
        $attempts = $this->cleanExpiredAttempts($attempts);
        
        $key = $this->generateKey($email, $ip);
        $currentTime = time();
        $preciseTime = microtime(true);
        
        if (!isset($attempts[$key])) {
            $attempts[$key] = [
                'email' => $email,
                'ip' => $ip,
                'attempts' => 0,
                'first_attempt' => $currentTime,
                'last_attempt' => $currentTime,
                'last_attempt_precise' => $preciseTime
            ];
        }
        
        $attempts[$key]['attempts']++;
        $attempts[$key]['last_attempt'] = $currentTime;
        $attempts[$key]['last_attempt_precise'] = $preciseTime;
        
        $this->saveAttempts($attempts);
        
        return [
            'attempts' => $attempts[$key]['attempts'],
            'max_attempts' => $this->maxAttempts,
            'remaining' => max(0, $this->maxAttempts - $attempts[$key]['attempts']),
            'global_attempts' => $globalData['attempts'],
            'global_max' => $this->globalMaxAttempts
        ];
    }
    
    /**
     * Limpa tentativas após login bem-sucedido
     */
    public function clearAttempts($email, $ip) {
        $attempts = $this->loadAttempts();
        $key = $this->generateKey($email, $ip);
        
        if (isset($attempts[$key])) {
            unset($attempts[$key]);
            $this->saveAttempts($attempts);
        }
        
        // Não limpar tentativas globais - elas continuam para proteção geral
    }
    
    /**
     * Retorna informações sobre as tentativas atuais
     */
    public function getAttemptInfo($email, $ip) {
        $attempts = $this->loadAttempts();
        $attempts = $this->cleanExpiredAttempts($attempts);
        
        $key = $this->generateKey($email, $ip);
        
        if (!isset($attempts[$key])) {
            return [
                'attempts' => 0,
                'max_attempts' => $this->maxAttempts,
                'remaining' => $this->maxAttempts
            ];
        }
        
        $userData = $attempts[$key];
        return [
            'attempts' => $userData['attempts'],
            'max_attempts' => $this->maxAttempts,
            'remaining' => max(0, $this->maxAttempts - $userData['attempts']),
            'last_attempt' => $userData['last_attempt']
        ];
    }
    
    /**
     * Formata tempo restante para exibição
     */
    public static function formatTimeRemaining($seconds) {
        if ($seconds <= 0) return '0 segundos';
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($minutes > 0) {
            return $minutes . ' minuto' . ($minutes != 1 ? 's' : '') . 
                   ($remainingSeconds > 0 ? ' e ' . $remainingSeconds . ' segundo' . ($remainingSeconds != 1 ? 's' : '') : '');
        } else {
            return $remainingSeconds . ' segundo' . ($remainingSeconds != 1 ? 's' : '');
        }
    }
}

/**
 * Classe para validação de campos honeypot (proteção contra bots)
 */
class HoneypotValidator {
    
    /**
     * Valida os campos honeypot
     * 
     * @param array $postData Dados do POST
     * @return array Resultado da validação
     */
    public static function validate($postData) {
        $errors = [];
        
        // Campos honeypot que devem estar vazios
        $honeypotFields = [
            'username',      // Campo username falso
            'phone',         // Campo telefone falso
            'website',       // Campo website falso
            'company',       // Campo empresa falso
            'address'        // Campo endereço falso
        ];
        
        // Verificar se algum campo honeypot foi preenchido
        foreach ($honeypotFields as $field) {
            if (!empty($postData[$field])) {
                $errors[] = "Campo {$field} foi preenchido (possível bot)";
            }
        }
        
        // Campo de tempo - deve ser preenchido e ter um valor mínimo
        $formTime = $postData['form_time'] ?? '';
        if (empty($formTime)) {
            $errors[] = "Campo de tempo não encontrado";
        } else {
            $submitTime = time();
            $timeDiff = $submitTime - (int)$formTime;
            
            // Tempo mínimo de 3 segundos para preencher o formulário
            if ($timeDiff < 3) {
                $errors[] = "Formulário preenchido muito rapidamente ({$timeDiff}s)";
            }
            
            // Tempo máximo de 1 hora
            if ($timeDiff > 3600) {
                $errors[] = "Formulário expirado (mais de 1 hora)";
            }
        }
        
        // Verificar se o JavaScript está habilitado através do campo oculto
        $jsEnabled = $postData['js_enabled'] ?? '';
        if ($jsEnabled !== '1') {
            $errors[] = "JavaScript não está habilitado ou campo não foi preenchido";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'is_bot' => !empty($errors)
        ];
    }
    
    /**
     * Gera um token único para o formulário
     */
    public static function generateToken() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Valida o token do formulário
     */
    public static function validateToken($token, $sessionToken) {
        return !empty($token) && !empty($sessionToken) && hash_equals($sessionToken, $token);
    }
}

class TurnstileValidator {
    
    private $secretKey;
    private $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    
    public function __construct($secretKey = null) {
        $this->secretKey = $secretKey ?: CLOUDFLARE_TURNSTILE_SECRET_KEY;
    }
    
    /**
     * Valida o token do Turnstile
     * 
     * @param string $token Token retornado pelo widget
     * @param string $remoteIp IP do usuário (opcional)
     * @return array Resultado da validação
     */
    public function verify($token, $remoteIp = null) {
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token do Turnstile não fornecido.',
                'error_codes' => ['missing-input-response']
            ];
        }
        
        // Preparar dados para envio
        $postData = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];
        
        // Adicionar IP se fornecido
        if ($remoteIp) {
            $postData['remoteip'] = $remoteIp;
        }
        
        // Configurar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->verifyUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Verificar erros de cURL
        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Erro na comunicação com o Turnstile: ' . $curlError,
                'error_codes' => ['curl-error']
            ];
        }
        
        // Verificar código HTTP
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => 'Erro HTTP na verificação do Turnstile: ' . $httpCode,
                'error_codes' => ['http-error-' . $httpCode]
            ];
        }
        
        // Decodificar resposta JSON
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Resposta inválida do Turnstile.',
                'error_codes' => ['invalid-json']
            ];
        }
        
        // Processar resultado
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Verificação do Turnstile bem-sucedida.',
                'challenge_ts' => $result['challenge_ts'] ?? null,
                'hostname' => $result['hostname'] ?? null
            ];
        } else {
            $errorCodes = $result['error-codes'] ?? ['unknown-error'];
            $errorMessage = $this->getErrorMessage($errorCodes);
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'error_codes' => $errorCodes
            ];
        }
    }
    
    /**
     * Converte códigos de erro em mensagens amigáveis
     * 
     * @param array $errorCodes
     * @return string
     */
    private function getErrorMessage($errorCodes) {
        $messages = [
            'missing-input-secret' => 'Chave secreta não configurada.',
            'invalid-input-secret' => 'Chave secreta inválida.',
            'missing-input-response' => 'Token de verificação não fornecido.',
            'invalid-input-response' => 'Token de verificação inválido.',
            'bad-request' => 'Requisição mal formada.',
            'timeout-or-duplicate' => 'Token expirado ou já utilizado.',
            'internal-error' => 'Erro interno do Turnstile.',
            'unknown-error' => 'Erro desconhecido na verificação.'
        ];
        
        $userMessages = [];
        foreach ($errorCodes as $code) {
            $userMessages[] = $messages[$code] ?? 'Erro de verificação: ' . $code;
        }
        
        return implode(' ', $userMessages);
    }
    
    /**
     * Verifica se o Turnstile está configurado
     * 
     * @return bool
     */
    public static function isConfigured() {
        return !empty(CLOUDFLARE_TURNSTILE_SITE_KEY) && 
               !empty(CLOUDFLARE_TURNSTILE_SECRET_KEY) &&
               CLOUDFLARE_TURNSTILE_SITE_KEY !== 'your_site_key_here' &&
               CLOUDFLARE_TURNSTILE_SECRET_KEY !== 'your_secret_key_here';
    }
}

// Inicializar classes
$auth = new Auth();
$rateLimit = new LoginRateLimit();

// Inicializar sessão para honeypot
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gerar token para honeypot se não existir
if (!isset($_SESSION['honeypot_token'])) {
    $_SESSION['honeypot_token'] = HoneypotValidator::generateToken();
}

// Se já estiver logado, redirecionar
if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

// Processar mensagens de logout
$mensagem = '';
$tipo_mensagem = '';
if (isset($_GET['mensagem']) && isset($_GET['tipo'])) {
    $mensagem = urldecode($_GET['mensagem']);
    $tipo_mensagem = urldecode($_GET['tipo']);
}

// Processar login
$erro = '';
$sucesso = '';
$userIP = $_SERVER['REMOTE_ADDR'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $lembrar = isset($_POST['lembrar']);
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    $honeypotToken = $_POST['honeypot_token'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        // Validar honeypot primeiro
        $honeypotResult = HoneypotValidator::validate($_POST);
        
        if (!$honeypotResult['valid']) {
            // Log da tentativa de bot
            error_log("Possível bot detectado no login - IP: {$userIP}, Email: {$email}, Erros: " . implode(', ', $honeypotResult['errors']));
            
            // Registrar como tentativa falhada mas não mostrar erro específico
            $attemptInfo = $rateLimit->recordFailedAttempt($email, $userIP);
            
            // Dar uma resposta genérica para não revelar o honeypot
            $erro = 'Erro na validação do formulário. Tente novamente.';
            
            // Opcional: banir IP temporariamente se for claramente um bot
            if (count($honeypotResult['errors']) > 2) {
                // Criar arquivo de IPs banidos se não existir
                $bannedIpsFile = '../data/rate_limit/banned_ips.json';
                $bannedIps = [];
                if (file_exists($bannedIpsFile)) {
                    $bannedIps = json_decode(file_get_contents($bannedIpsFile), true) ?: [];
                }
                
                $bannedIps[$userIP] = [
                    'banned_at' => time(),
                    'reason' => 'Honeypot detection',
                    'errors' => $honeypotResult['errors']
                ];
                
                file_put_contents($bannedIpsFile, json_encode($bannedIps, JSON_PRETTY_PRINT));
            }
        } else {
            // Validar token do honeypot
            if (!HoneypotValidator::validateToken($honeypotToken, $_SESSION['honeypot_token'])) {
                $erro = 'Token de segurança inválido. Recarregue a página.';
            } else {
                // Verificar se o usuário está bloqueado
                $blockStatus = $rateLimit->isBlocked($email, $userIP);
                
                if ($blockStatus && $blockStatus['blocked']) {
                    $timeRemaining = LoginRateLimit::formatTimeRemaining($blockStatus['time_remaining']);
                    $erro = "Muitas tentativas de login falhadas. Tente novamente em {$timeRemaining}. " .
                           "({$blockStatus['attempts']} tentativas registradas)";
                } else {
                    // Validar Turnstile
                    $turnstileValidator = new TurnstileValidator();
                    $turnstileResult = $turnstileValidator->verify($turnstileToken, $userIP);
                    
                    if (!$turnstileResult['success']) {
                        $erro = 'Verificação de segurança falhou: ' . $turnstileResult['message'];
                        
                        // Também registrar como tentativa falhada se Turnstile falhar
                        $attemptInfo = $rateLimit->recordFailedAttempt($email, $userIP);
                        if ($attemptInfo['remaining'] > 0) {
                            $erro .= " (Restam {$attemptInfo['remaining']} tentativas)";
                        }
                    } else {
                        // Prosseguir com o login
                        $resultado = $auth->login($email, $senha);
                        
                        if ($resultado['success']) {
                            // Login bem-sucedido - limpar tentativas e regenerar token
                            $rateLimit->clearAttempts($email, $userIP);
                            $_SESSION['honeypot_token'] = HoneypotValidator::generateToken();
                            
                            // Redirecionar para página solicitada ou dashboard
                            $redirect = $_GET['redirect'] ?? BASE_URL . '/pages/dashboard.php';
                            header('Location: ' . $redirect);
                            exit;
                        } else {
                            // Login falhou - registrar tentativa
                            $attemptInfo = $rateLimit->recordFailedAttempt($email, $userIP);
                            
                            if ($attemptInfo['remaining'] > 0) {
                                $erro = $resultado['message'] . " (Restam {$attemptInfo['remaining']} tentativas)";
                            } else {
                                $erro = "Credenciais inválidas. Você atingiu o limite de tentativas e foi temporariamente bloqueado por 15 minutos.";
                            }
                        }
                    }
                }
            }
        }
    }
}

// Obter informações atuais de tentativas para exibir aviso se necessário
$currentAttempts = null;
if (!empty($_POST['email'])) {
    $currentAttempts = $rateLimit->getAttemptInfo($_POST['email'], $userIP);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SISTEMA_NOME; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="estilizacao/login-style.css">
    
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#e0f2fe',
                            100: '#bae6fd',
                            200: '#7dd3fc',
                            300: '#38bdf8',
                            400: '#0ea5e9',
                            500: '#0284c7',
                            600: '#0369a1',
                            700: '#0c4a6e',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                        'twinkle': 'twinkle 3s ease-in-out infinite alternate',
                        'shoot': 'shoot 3s linear infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Honeypot styles - campos completamente ocultos */
        .honeypot {
            position: absolute !important;
            left: -9999px !important;
            top: -9999px !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 0 !important;
            height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            font-size: 0 !important;
            tab-index: -1;
            z-index: -1;
        }
    </style>
</head>
<body class="min-h-screen overflow-hidden relative">
    <!-- Galaxy Background -->
    <div class="galaxy-bg" id="galaxy-container"></div>
    
    <!-- Stars Background -->
    <div class="stars-bg" id="stars-container"></div>
    
    <!-- Nebulas -->
    <div class="nebulas-bg" id="nebulas-container"></div>
    
    <!-- Particles -->
    <div id="particles-container"></div>
    
    <!-- Main Container -->
    <div class="min-h-screen flex items-center justify-center relative z-10 p-4">
        <div class="login-container backdrop-blur-lg bg-gradient-to-br from-sky-500/10 to-blue-800/20 border border-sky-300/20 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
            <!-- Header -->
            <div class="login-header bg-gradient-to-br from-sky-600/90 to-blue-800/90 text-white p-8 text-center relative">
                <div class="absolute inset-0 bg-gradient-to-br from-sky-500/20 to-blue-600/20 animate-pulse-slow"></div>
                
                <div class="logo-container relative z-10 mb-6">
                 <div class="logo w-40 h-40 bg-white/20 backdrop-blur-sm rounded-full mx-auto flex items-center justify-center text-4xl animate-float border border-white/30">
                <img src="./img/logoassego.png" alt="Logo" class="w-36 h-36 object-cover rounded-full">
                </div>
                </div>
                
                <h1 class="text-3xl font-bold mb-2 animate-glow relative z-10">
                    <?php echo SISTEMA_NOME ?? 'Sistema Comercial'; ?>
                </h1>
                <p class="text-sky-100 text-sm font-medium relative z-10">
                    Área Do Comercial Da Assego
                </p>
            </div>
            
            <!-- Form Body -->
            <div class="login-body p-8 relative">
                <?php if ($mensagem): ?>
                    <?php
                    $alert_classes = [
                        'success' => 'bg-green-500/10 border-green-500/20 text-green-300',
                        'error' => 'bg-red-500/10 border-red-500/20 text-red-300',
                        'warning' => 'bg-yellow-500/10 border-yellow-500/20 text-yellow-300',
                        'info' => 'bg-blue-500/10 border-blue-500/20 text-blue-300'
                    ];
                    $icon_classes = [
                        'success' => 'fa-check-circle text-green-400',
                        'error' => 'fa-exclamation-circle text-red-400',
                        'warning' => 'fa-exclamation-triangle text-yellow-400',
                        'info' => 'fa-info-circle text-blue-400'
                    ];
                    $alert_class = $alert_classes[$tipo_mensagem] ?? $alert_classes['info'];
                    $icon_class = $icon_classes[$tipo_mensagem] ?? $icon_classes['info'];
                    ?>
                    <div class="alert-message <?php echo $alert_class; ?> backdrop-blur-sm border px-4 py-3 rounded-xl mb-6 flex items-center animate-fade-in" role="alert">
                        <i class="fas <?php echo $icon_class; ?> mr-3"></i>
                        <span><?php echo htmlspecialchars($mensagem); ?></span>
                        <button type="button" class="ml-auto text-gray-300 hover:text-white transition-colors" onclick="this.parentElement.style.display='none'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if ($erro): ?>
                    <div class="alert-error bg-red-500/10 backdrop-blur-sm border border-red-500/20 text-red-300 px-4 py-3 rounded-xl mb-6 flex items-center animate-pulse" role="alert">
                        <i class="fas fa-exclamation-circle mr-3 text-red-400"></i>
                        <span><?php echo htmlspecialchars($erro); ?></span>
                        <button type="button" class="ml-auto text-red-400 hover:text-red-300 transition-colors" onclick="this.parentElement.style.display='none'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if ($currentAttempts && $currentAttempts['attempts'] > 0 && !$erro): ?>
                    <div class="alert-warning bg-yellow-500/10 backdrop-blur-sm border border-yellow-500/20 text-yellow-300 px-4 py-3 rounded-xl mb-6 flex items-center" role="alert">
                        <i class="fas fa-exclamation-triangle mr-3 text-yellow-400"></i>
                        <span>
                            Atenção: <?php echo $currentAttempts['attempts']; ?> tentativa(s) de login registrada(s). 
                            Restam <?php echo $currentAttempts['remaining']; ?> tentativa(s) antes do bloqueio temporário.
                        </span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm" class="space-y-6">
                    <!-- Campos Honeypot (Ocultos) -->
                    <div class="honeypot">
                        <label for="username">Nome de usuário (não preencha)</label>
                        <input type="text" id="username" name="username" tabindex="-1" autocomplete="off">
                    </div>
                    
                    <div class="honeypot">
                        <label for="phone">Telefone (não preencha)</label>
                        <input type="tel" id="phone" name="phone" tabindex="-1" autocomplete="off">
                    </div>
                    
                    <div class="honeypot">
                        <label for="website">Website (não preencha)</label>
                        <input type="url" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    
                    <div class="honeypot">
                        <label for="company">Empresa (não preencha)</label>
                        <input type="text" id="company" name="company" tabindex="-1" autocomplete="off">
                    </div>
                    
                    <div class="honeypot">
                        <label for="address">Endereço (não preencha)</label>
                        <input type="text" id="address" name="address" tabindex="-1" autocomplete="off">
                    </div>
                    
                    <!-- Campo de tempo oculto -->
                    <input type="hidden" name="form_time" value="<?php echo time(); ?>">
                    
                    <!-- Campo para verificar se JavaScript está habilitado -->
                    <input type="hidden" name="js_enabled" id="js_enabled" value="0">
                    
                    <!-- Token de segurança -->
                    <input type="hidden" name="honeypot_token" value="<?php echo $_SESSION['honeypot_token']; ?>">
                    
                    <!-- Email Field -->
                    <div class="form-group relative">
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-input w-full bg-white/5 backdrop-blur-sm border border-white/20 rounded-xl px-12 py-4 text-black placeholder-gray-300 focus:border-sky-400 focus:bg-white/10 transition-all duration-300"
                                   id="email" 
                                   name="email" 
                                   placeholder="Seu Email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required 
                                   autofocus>
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="form-group relative">
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   class="form-input w-full bg-white/5 backdrop-blur-sm border border-white/20 rounded-xl px-12 py-4 pr-16 text-black placeholder-gray-300 focus:border-sky-400 focus:bg-white/10 transition-all duration-300"
                                   id="senha" 
                                   name="senha" 
                                   placeholder="Sua Senha"
                                   required>
                            <button type="button" class="password-toggle absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-black transition-colors" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Cloudflare Turnstile -->
                    <div class="form-group relative">
                        <div class="turnstile-container bg-white/5 backdrop-blur-sm border border-white/20 rounded-xl p-4 flex justify-center">
                            <div class="cf-turnstile" 
                                 data-sitekey="<?php echo CLOUDFLARE_TURNSTILE_SITE_KEY; ?>"
                                 data-theme="light"
                                 data-size="normal"
                                 data-callback="onTurnstileSuccess"
                                 data-error-callback="onTurnstileError"
                                 data-expired-callback="onTurnstileExpired">
                            </div>
                        </div>
                        <div id="turnstile-error" class="text-red-400 text-sm mt-2 hidden">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <span>Verificação de segurança necessária</span>
                        </div>
                    </div>
                    
                    <!-- Remember Me -->
                    <div class="form-check flex items-center">
                        <input type="checkbox" 
                               id="lembrar" 
                               name="lembrar"
                               class="w-4 h-4 text-sky-600 bg-white/10 border-white/20 rounded focus:ring-sky-500 focus:ring-2">
                        <label for="lembrar" class="ml-3 text-sm text-gray-300 cursor-pointer">
                            Lembrar meu acesso
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn" class="btn-login w-full bg-gradient-to-r from-sky-600 to-blue-800 hover:from-sky-700 hover:to-blue-900 text-white font-bold py-4 px-8 rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-sky-500/50 relative overflow-hidden group" disabled>
                        <span class="relative z-10 flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-3"></i>
                            ACESSAR SISTEMA
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/20 to-white/0 transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
                    </button>
                </form>
                
                <!-- Forgot Password -->
                <div class="text-center mt-8">
                    <a href="<?php echo BASE_URL ?? ''; ?>/pages/recuperar-senha.php" 
                       class="forgot-link text-sky-300 hover:text-sky-100 text-sm font-medium transition-colors duration-300 flex items-center justify-center">
                        <i class="fas fa-key mr-2"></i>
                        Esqueceu sua senha?
                    </a>
                </div>
                
                <!-- Footer -->
                <div class="text-center mt-8 pt-6 border-t border-white/10">
                    <p class="text-xs text-gray-400 mb-1">
                        &copy; <?php echo date('Y'); ?> <?php echo SISTEMA_EMPRESA ?? 'ASSEGO'; ?>. Todos os direitos reservados.
                    </p>
                    <p class="text-xs text-gray-500">
                        Versão <?php echo SISTEMA_VERSAO ?? '1.0.0'; ?>
                    </p>
                    <div class="flex items-center justify-center mt-2 text-xs text-gray-500">
                        <i class="fas fa-shield-alt mr-1"></i>
                        <span>Protegido por Cloudflare + Honeypot</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 text-center">
            <div class="loading-spinner mx-auto mb-4"></div>
            <p class="text-white font-medium">Fazendo login...</p>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="js/login-script.js"></script>
    
    <!-- Honeypot + Turnstile Integration Scripts -->
    <script>
        let turnstileVerified = false;
        
        // Marcar que JavaScript está habilitado
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('js_enabled').value = '1';
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        });
        
        // Callback para sucesso do Turnstile
        function onTurnstileSuccess(token) {
            console.log('Turnstile verificado com sucesso');
            turnstileVerified = true;
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').classList.remove('opacity-50', 'cursor-not-allowed');
            document.getElementById('turnstile-error').classList.add('hidden');
        }
        
        // Callback para erro do Turnstile
        function onTurnstileError(error) {
            console.error('Erro no Turnstile:', error);
            turnstileVerified = false;
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').classList.add('opacity-50', 'cursor-not-allowed');
            document.getElementById('turnstile-error').classList.remove('hidden');
        }
        
        // Callback para expiração do Turnstile
        function onTurnstileExpired() {
            console.log('Turnstile expirado');
            turnstileVerified = false;
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').classList.add('opacity-50', 'cursor-not-allowed');
            document.getElementById('turnstile-error').classList.remove('hidden');
            document.getElementById('turnstile-error').querySelector('span').textContent = 'Verificação expirada, clique novamente';
        }
        
        // Validação do formulário
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (!turnstileVerified) {
                e.preventDefault();
                document.getElementById('turnstile-error').classList.remove('hidden');
                document.getElementById('turnstile-error').querySelector('span').textContent = 'Complete a verificação de segurança';
                
                // Animar o widget Turnstile
                const turnstileWidget = document.querySelector('.cf-turnstile');
                if (turnstileWidget) {
                    turnstileWidget.style.transform = 'scale(1.05)';
                    turnstileWidget.style.boxShadow = '0 0 20px rgba(239, 68, 68, 0.5)';
                    setTimeout(() => {
                        turnstileWidget.style.transform = 'scale(1)';
                        turnstileWidget.style.boxShadow = 'none';
                    }, 300);
                }
                
                return false;
            }
            
            // Mostrar loading overlay
            document.getElementById('loadingOverlay').classList.remove('hidden');
        });
        
        // Função para alternar visibilidade da senha
        function togglePassword() {
            const passwordInput = document.getElementById('senha');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Proteção adicional contra bots - verificar comportamento do mouse
        let mouseMovements = 0;
        document.addEventListener('mousemove', function() {
            mouseMovements++;
        });
        
        // Adicionar campo oculto com movimentos do mouse
        document.getElementById('loginForm').addEventListener('submit', function() {
            const mouseField = document.createElement('input');
            mouseField.type = 'hidden';
            mouseField.name = 'mouse_movements';
            mouseField.value = mouseMovements;
            this.appendChild(mouseField);
        });
    </script>
    
    <!-- Auto-hide messages after 5 seconds -->
    <script>
        // Auto-hide logout messages
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-message, .alert-warning');
            alerts.forEach(function(alert) {
                if (!alert.classList.contains('alert-error')) {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>