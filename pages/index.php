<?php
// Configurar duração da sessão para 14 horas (50400 segundos)
ini_set('session.gc_maxlifetime', 50400);
session_set_cookie_params(50400);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

/**
 * Classe para gerenciar Rate Limiting de tentativas de login
 */
class LoginRateLimit {
    private $maxAttempts = 100;
    private $lockoutTime = 9; 
    private $globalMaxAttempts = 500; 
    private $globalLockoutTime = 18; 
    private $minTimeBetweenAttempts = 0.5; 
    private $dataFile;
    private $globalFile;
    
    public function __construct() {
        $dataDir = '../data/rate_limit';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->dataFile = $dataDir . '/login_attempts.json';
        $this->globalFile = $dataDir . '/global_attempts.json';
    }
    
    private function loadAttempts() {
        if (!file_exists($this->dataFile)) {
            return [];
        }
        
        $data = file_get_contents($this->dataFile);
        $attempts = json_decode($data, true);
        
        return is_array($attempts) ? $attempts : [];
    }
    
    private function saveAttempts($attempts) {
        file_put_contents($this->dataFile, json_encode($attempts, JSON_PRETTY_PRINT));
    }
    
    private function loadGlobalAttempts() {
        if (!file_exists($this->globalFile)) {
            return ['attempts' => 0, 'last_attempt' => 0, 'ips' => []];
        }
        
        $data = file_get_contents($this->globalFile);
        $attempts = json_decode($data, true);
        
        return is_array($attempts) ? $attempts : ['attempts' => 0, 'last_attempt' => 0, 'ips' => []];
    }
    
    private function saveGlobalAttempts($attempts) {
        file_put_contents($this->globalFile, json_encode($attempts, JSON_PRETTY_PRINT));
    }
    
    private function recordGlobalAttempt($ip) {
        $globalData = $this->loadGlobalAttempts();
        $currentTime = time();
        
        $globalData['attempts']++;
        $globalData['last_attempt'] = $currentTime;
        
        if (!in_array($ip, $globalData['ips'])) {
            $globalData['ips'][] = $ip;
        }
        
        $this->saveGlobalAttempts($globalData);
        
        return $globalData;
    }
    
    public function isGloballyBlocked() {
        $globalData = $this->loadGlobalAttempts();
        $currentTime = time();
        
        if (($currentTime - $globalData['last_attempt']) >= $this->globalLockoutTime) {
            if ($globalData['attempts'] >= $this->globalMaxAttempts) {
                $globalData = ['attempts' => 0, 'last_attempt' => 0, 'ips' => []];
                $this->saveGlobalAttempts($globalData);
            }
            return false;
        }
        
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
    
    private function generateKey($email, $ip) {
        return md5($email . '|' . $ip);
    }
    
    public function isBlocked($email, $ip) {
        $attempts = $this->loadAttempts();
        $attempts = $this->cleanExpiredAttempts($attempts);
        
        $key = $this->generateKey($email, $ip);
        
        if (!isset($attempts[$key])) {
            return false;
        }
        
        $userData = $attempts[$key];
        $currentTime = time();
        
        if (($currentTime - $userData['last_attempt']) >= $this->lockoutTime) {
            unset($attempts[$key]);
            $this->saveAttempts($attempts);
            return false;
        }
        
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
    
    public function recordFailedAttempt($email, $ip) {
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
    
    public function clearAttempts($email, $ip) {
        $attempts = $this->loadAttempts();
        $key = $this->generateKey($email, $ip);
        
        if (isset($attempts[$key])) {
            unset($attempts[$key]);
            $this->saveAttempts($attempts);
        }
    }
    
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
    
    public static function validate($postData) {
        $errors = [];
        
        $honeypotFields = [
            'username',
            'phone',
            'website',
            'company',
            'address'
        ];
        
        foreach ($honeypotFields as $field) {
            if (!empty($postData[$field])) {
                $errors[] = "Campo {$field} foi preenchido (possível bot)";
            }
        }
        
        $formTime = $postData['form_time'] ?? '';
        if (empty($formTime)) {
            $errors[] = "Campo de tempo não encontrado";
        } else {
            $submitTime = time();
            $timeDiff = $submitTime - (int)$formTime;
            
            if ($timeDiff < 3) {
                $errors[] = "Formulário preenchido muito rapidamente ({$timeDiff}s)";
            }
            
            if ($timeDiff > 3600) {
                $errors[] = "Formulário expirado (mais de 1 hora)";
            }
        }
        
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
    
    public static function generateToken() {
        return bin2hex(random_bytes(16));
    }
    
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
    
    public function verify($token, $remoteIp = null) {
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token do Turnstile não fornecido.',
                'error_codes' => ['missing-input-response']
            ];
        }
        
        $postData = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];
        
        if ($remoteIp) {
            $postData['remoteip'] = $remoteIp;
        }
        
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
        
        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Erro na comunicação com o Turnstile: ' . $curlError,
                'error_codes' => ['curl-error']
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => 'Erro HTTP na verificação do Turnstile: ' . $httpCode,
                'error_codes' => ['http-error-' . $httpCode]
            ];
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Resposta inválida do Turnstile.',
                'error_codes' => ['invalid-json']
            ];
        }
        
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

// Frases motivacionais para o loading
$frasesMotivacionais = [
    "O sucesso é a soma de pequenos esforços repetidos dia após dia.",
    "Grandes realizações requerem grandes ambições.",
    "O único limite para o nosso crescimento é nossa determinação.",
    "Cada meta alcançada é o início de uma nova jornada.",
    "A excelência não é um ato, mas um hábito.",
    "Transformamos desafios em oportunidades de crescimento.",
    "Juntos, construímos um futuro de sucesso e prosperidade.",
    "Nossa dedicação hoje define o sucesso de amanhã.",
    "Inovação e qualidade são os pilares do nosso progresso.",
    "Cada dia é uma nova chance de superar nossas expectativas.",
    "A gestão eficiente é a chave para o crescimento sustentável.",
    "Trabalho em equipe é o combustível do sucesso organizacional.",
    "Planejamento estratégico é o mapa para alcançar nossos objetivos.",
    "A melhoria contínua nos leva sempre adiante.",
    "Foco, disciplina e perseverança constroem grandes resultados.",
];

// Selecionar frase aleatória
$fraseAleatoria = $frasesMotivacionais[array_rand($frasesMotivacionais)];

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
            error_log("Possível bot detectado no login - IP: {$userIP}, Email: {$email}, Erros: " . implode(', ', $honeypotResult['errors']));
            
            $attemptInfo = $rateLimit->recordFailedAttempt($email, $userIP);
            
            $erro = 'Erro na validação do formulário. Tente novamente.';
            
            if (count($honeypotResult['errors']) > 2) {
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
            if (!HoneypotValidator::validateToken($honeypotToken, $_SESSION['honeypot_token'])) {
                $erro = 'Token de segurança inválido. Recarregue a página.';
            } else {
                $blockStatus = $rateLimit->isBlocked($email, $userIP);
                
                if ($blockStatus && $blockStatus['blocked']) {
                    $timeRemaining = LoginRateLimit::formatTimeRemaining($blockStatus['time_remaining']);
                    $erro = "Muitas tentativas de login falhadas. Tente novamente em {$timeRemaining}. " .
                        "({$blockStatus['attempts']} tentativas registradas)";
                } else {
                    $turnstileValidator = new TurnstileValidator();
                    $turnstileResult = $turnstileValidator->verify($turnstileToken, $userIP);
                    
                    if (!$turnstileResult['success']) {
                        $erro = 'Verificação de segurança falhou: ' . $turnstileResult['message'];
                        
                        $attemptInfo = $rateLimit->recordFailedAttempt($email, $userIP);
                        if ($attemptInfo['remaining'] > 0) {
                            $erro .= " (Restam {$attemptInfo['remaining']} tentativas)";
                        }
                    } else {
                        $resultado = $auth->login($email, $senha);
                        
                        if ($resultado['success']) {
                            $rateLimit->clearAttempts($email, $userIP);
                            $_SESSION['honeypot_token'] = HoneypotValidator::generateToken();
                            
                            $redirect = $_GET['redirect'] ?? BASE_URL . '/pages/dashboard.php';
                            header('Location: ' . $redirect);
                            exit;
                        } else {
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
    <title>ASSEGO - Sistema de Gestão</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
    <style>
        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        html, body {
            height: 100%;
            font-family: 'Inter', system-ui, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }

        /* Loading inicial */
        .initial-loading {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.8s ease-out, visibility 0.8s ease-out;
        }

        .initial-loading.fade-out {
            opacity: 0;
            visibility: hidden;
        }

        .loading-content {
            text-align: center;
            animation: loadingPulse 1.5s ease-in-out infinite;
            max-width: 500px;
            padding: 0 1.5rem;
        }

        @keyframes loadingPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }

        .loading-logo {
            margin-bottom: 2rem;
        }

        .loading-logo img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
        }

        .loading-title {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.02em;
            margin: 1rem 0;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        .loading-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            margin-top: 1rem;
            font-weight: 500;
        }

        .loading-quote {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .loading-quote-text {
            font-size: 0.8125rem;
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
            line-height: 1.5;
        }

        /* Container principal */
        .login-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
            opacity: 0;
            animation-delay: 0.3s;
            animation-fill-mode: forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Header com logo */
        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            position: relative;
        }

        .logo-container {
            margin-bottom: 1rem;
        }

        .logo-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .logo-wrapper img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .system-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.25rem;
        }

        .system-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 400;
        }

        /* Corpo do formulário */
        .login-body {
            padding: 2rem;
        }

        /* Alertas */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fed7aa;
        }

        .alert svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Formulário */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            color: #1f2937;
            transition: all 0.3s ease;
            position: relative;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: #6b7280;
            font-weight: 400;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #6b7280;
            pointer-events: none;
            transition: color 0.3s;
            z-index: 2;
        }

        .form-input:focus + .input-icon {
            color: #3b82f6;
        }

        /* Toggle de senha */
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.3s;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #3b82f6;
        }

        /* Turnstile */
        .turnstile-container {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }

        /* Checkbox e link */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: #1f2937;
            cursor: pointer;
            font-weight: 500;
            user-select: none;
        }

        .forgot-link {
            font-size: 0.875rem;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: #1e40af;
            text-decoration: underline;
        }

        /* Botão de submit */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: #3b82f6;
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            position: relative;
            overflow: hidden;
        }

        .submit-btn:not(:disabled):hover {
            background: #1e40af;
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .submit-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        /* Footer */
        .login-footer {
            padding: 1.5rem 2rem 2rem;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            background: #fafafa;
        }

        .copyright {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .protection {
            font-size: 0.6875rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .protection svg {
            width: 12px;
            height: 12px;
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 58, 138, 0.95);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loader {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Responsividade */
        @media (max-width: 480px) {
            body {
                padding: 0;
            }

            .login-container {
                border-radius: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .login-header {
                padding: 2rem 1.5rem;
            }

            .logo-wrapper img {
                width: 60px;
                height: 60px;
            }

            .system-title {
                font-size: 1.125rem;
            }

            .login-body {
                padding: 1.5rem;
            }

            .form-input {
                font-size: 16px; /* Previne zoom no iOS */
                padding: 1rem;
                padding-left: 3rem;
            }

            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .submit-btn {
                padding: 1.125rem;
                font-size: 1rem;
            }

            .login-footer {
                padding: 1.5rem;
            }

            /* Loading mobile */
            .loading-logo img {
                width: 60px;
                height: 60px;
            }

            .loading-title {
                font-size: 1.75rem;
            }

            .loading-spinner {
                width: 35px;
                height: 35px;
            }

            .loading-quote {
                margin-top: 1.5rem;
                padding: 0.875rem;
            }

            .loading-quote-text {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Inicial -->
    <div class="initial-loading" id="initialLoading">
        <div class="loading-content">
            <div class="loading-logo">
                <img src="./img/logoassego.png" alt="ASSEGO Logo">
            </div>
            <h1 class="loading-title">ASSEGO</h1>
            <div class="loading-spinner"></div>
            <p class="loading-text">Preparando o sistema...</p>
            
            <!-- Frase Motivacional no Loading -->
            <div class="loading-quote">
                <p class="loading-quote-text"><?= htmlspecialchars($fraseAleatoria) ?></p>
            </div>
        </div>
    </div>

    <!-- Container Principal -->
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-container">
                <div class="logo-wrapper">
                    <img src="./img/logoassego.png" alt="ASSEGO Logo">
                </div>
            </div>
            
            <h1 class="system-title">Sistema De Gestão</h1>
            <p class="system-subtitle">Área De Gestão Da ASSEGO</p>
        </div>
        
        <!-- Corpo do formulário -->
        <div class="login-body">
            <?php if ($mensagem): ?>
                <?php
                $alert_classes = [
                    'success' => 'alert-success',
                    'error' => 'alert-error',
                    'warning' => 'alert-warning'
                ];
                $alert_class = $alert_classes[$tipo_mensagem] ?? 'alert-success';
                ?>
                <div class="alert <?php echo $alert_class; ?>" role="alert">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <?php if ($tipo_mensagem === 'success'): ?>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        <?php else: ?>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        <?php endif; ?>
                    </svg>
                    <span><?php echo htmlspecialchars($mensagem); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($erro): ?>
                <div class="alert alert-error" role="alert">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($erro); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($currentAttempts && $currentAttempts['attempts'] > 0 && !$erro): ?>
                <div class="alert alert-warning" role="alert">
                    <svg fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>
                        Atenção: <?php echo $currentAttempts['attempts']; ?> tentativa(s) de login registrada(s). 
                        Restam <?php echo $currentAttempts['remaining']; ?> tentativa(s) antes do bloqueio temporário.
                    </span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
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
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="email" 
                            class="form-input"
                            id="email" 
                            name="email" 
                            placeholder="Seu Email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required 
                            autofocus>
                        <svg class="input-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                    </div>
                </div>
                
                <!-- Password Field -->
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="password" 
                            class="form-input"
                            id="senha" 
                            name="senha" 
                            placeholder="Sua Senha"
                            required>
                        <svg class="input-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <svg fill="currentColor" viewBox="0 0 20 20" style="width: 18px; height: 18px;">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Cloudflare Turnstile -->
                <div class="form-group">
                    <div class="turnstile-container">
                        <div class="cf-turnstile" 
                            data-sitekey="<?php echo CLOUDFLARE_TURNSTILE_SITE_KEY; ?>"
                            data-theme="light"
                            data-size="normal"
                            data-callback="onTurnstileSuccess"
                            data-error-callback="onTurnstileError"
                            data-expired-callback="onTurnstileExpired">
                        </div>
                    </div>
                </div>
                
                <!-- Checkbox e forgot password -->
                <div class="form-options">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" 
                            id="lembrar" 
                            name="lembrar"
                            class="checkbox-input">
                        <label for="lembrar" class="checkbox-label">
                            Lembrar meu acesso
                        </label>
                    </div>
                    <a href="<?php echo BASE_URL ?? ''; ?>/pages/recuperar-senha.php" class="forgot-link">
                        Esqueceu sua senha?
                    </a>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" id="submitBtn" class="submit-btn" disabled>
                    ACESSAR SISTEMA
                </button>
            </form>
        </div>
        
        <!-- Footer -->
        <div class="login-footer">
            <p class="copyright">
                &copy; <?php echo date('Y'); ?> <?php echo SISTEMA_EMPRESA ?? 'ASSEGO'; ?>. Todos os direitos reservados.
            </p>
            <p class="copyright">
                Versão <?php echo SISTEMA_VERSAO ?? '1.0.0'; ?>
            </p>
            <div class="protection">
                <svg fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
               
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loader"></div>
    </div>
    
    <script>
        let turnstileVerified = false;
        
        // Loading inicial de 5 segundos
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.getElementById('initialLoading').classList.add('fade-out');
            }, 5000);
        });
        
        // Marcar que JavaScript está habilitado
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('js_enabled').value = '1';
        });
        
        // Callback para sucesso do Turnstile
        function onTurnstileSuccess(token) {
            turnstileVerified = true;
            document.getElementById('submitBtn').disabled = false;
        }
        
        // Callback para erro do Turnstile
        function onTurnstileError(error) {
            turnstileVerified = false;
            document.getElementById('submitBtn').disabled = true;
        }
        
        // Callback para expiração do Turnstile
        function onTurnstileExpired() {
            turnstileVerified = false;
            document.getElementById('submitBtn').disabled = true;
        }
        
        // Validação do formulário
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (!turnstileVerified) {
                e.preventDefault();
                alert('Complete a verificação de segurança');
                return false;
            }
            
            // Mostrar loading overlay
            document.getElementById('loadingOverlay').classList.add('active');
            document.getElementById('submitBtn').classList.add('loading');
        });
        
        // Função para alternar visibilidade da senha
        function togglePassword() {
            const passwordInput = document.getElementById('senha');
            const toggleBtn = passwordInput.nextElementSibling;
            const icon = toggleBtn.querySelector('svg');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 01.66 10C2.36 6.91 6 4.5 10 4.5c1.2 0 2.37.18 3.5.5M9.9 4.24A9.12 9.12 0 01.66 10a14.5 14.5 0 006.58 6.58"/><path d="M6.61 6.61A13.526 13.526 0 00.41 10a13.526 13.526 0 0019.18 0A13.526 13.526 0 0013.39 13.39M9.9 4.24A9.12 9.12 0 0119.34 10"/><path d="m15 9l-6 6m0-6l6 6"/>';
            } else {
                passwordInput.type = 'password';
                icon.innerHTML = '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>';
            }
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
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