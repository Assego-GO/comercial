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
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700&display=swap" rel="stylesheet">
    
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
    <style>
        /* Reset minimalista */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Honeypot - campos ocultos */
        .honeypot {
            position: absolute !important;
            left: -9999px !important;
            top: -9999px !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 0 !important;
            height: 0 !important;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 400;
        }

        body {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
            color: #334155;
            position: relative;
            overflow: hidden;
        }

        /* Background com imagens sutis */
        .bg-image {
            position: fixed;
            inset: -10%;
            width: 120%;
            height: 120%;
            z-index: -2;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            filter: blur(1px);
            transition: opacity 4s ease-in-out;
            animation: slowFloat 20s ease-in-out infinite alternate;
        }

        @keyframes slowFloat {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.05) translate(-1%, -1%); }
        }

        .bg-image.active {
            opacity: 0.4;
        }

        .bg-image.inactive {
            opacity: 0;
        }

        /* Canvas de partículas */
        #particles-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -3;
            pointer-events: none;
        }

        /* Overlay azul sobre as imagens */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.6) 0%, rgba(59, 130, 246, 0.5) 100%);
            z-index: -1;
            pointer-events: none;
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
            max-width: 500px;
            padding: 0 1.5rem;
        }

        .loading-logo img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
            margin-bottom: 1.5rem;
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
            margin: 1.5rem auto;
        }

        .loading-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
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
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
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

        /* Header minimalista e limpo */
        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            padding: 1.5rem 2rem;
            text-align: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.875rem;
        }

        .header-logo {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px;
            flex-shrink: 0;
        }

        .header-content {
            text-align: left;
        }

        .system-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: white;
            margin: 0;
            letter-spacing: -0.025em;
            line-height: 1.1;
        }

        .system-subtitle {
            font-size: 0.8125rem;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 400;
            margin-top: 0.125rem;
        }

        /* Corpo do formulário */
        .login-body {
            padding: 2rem;
        }

        /* Alertas minimalistas */
        .alert {
            padding: 0.875rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            font-weight: 400;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            border: 1px solid;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .alert-warning {
            background: #fffbeb;
            color: #d97706;
            border-color: #fed7aa;
        }

        .alert svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Formulário limpo */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9375rem;
            color: #374151;
            transition: all 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .input-wrapper {
            position: relative;
        }

        /* Toggle de senha minimalista */
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.15s;
        }

        .password-toggle:hover {
            color: #6b7280;
        }

        /* Turnstile */
        .turnstile-container {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        /* Opções do formulário */
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
            width: 16px;
            height: 16px;
            accent-color: #3b82f6;
        }

        .checkbox-label {
            font-size: 0.875rem;
            color: #374151;
            font-weight: 400;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 0.875rem;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 400;
            transition: color 0.15s;
        }

        .forgot-link:hover {
            color: #2563eb;
        }

        /* Botão minimalista */
        .submit-btn {
            width: 100%;
            padding: 0.875rem;
            background: #3b82f6;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
        }

        .submit-btn:hover:not(:disabled) {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .submit-btn:disabled {
            background: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .submit-btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin: -8px 0 0 -8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        /* Footer minimalista */
        .login-footer {
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #f1f5f9;
            background: #fafafa;
        }

        .copyright {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 0.25rem;
            font-weight: 400;
        }

        .protection {
            font-size: 0.6875rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            font-weight: 400;
        }

        .protection svg {
            width: 11px;
            height: 11px;
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(30, 58, 138, 0.95);
            backdrop-filter: blur(4px);
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
                padding: 1rem;
            }

            .login-container {
                border-radius: 12px;
            }

            .login-header {
                padding: 1.25rem 1.5rem;
                gap: 0.75rem;
            }

            .header-logo {
                width: 40px;
                height: 40px;
            }

            .system-title {
                font-size: 1.25rem;
            }

            .system-subtitle {
                font-size: 0.75rem;
            }

            .login-body {
                padding: 1.5rem;
            }

            .form-input {
                font-size: 16px; /* Previne zoom no iOS */
                padding: 0.875rem;
            }

            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .submit-btn {
                padding: 1rem;
            }

            .login-footer {
                padding: 1.25rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Inicial -->
    <div class="initial-loading" id="initialLoading">
        <div class="loading-content">
            <div class="loading-logo">
                <img src="./img/logo-assego.jpeg" alt="ASSEGO">
            </div>
            <h1 class="loading-title">ASSEGO</h1>
            <div class="loading-spinner"></div>
            <p class="loading-text">Carregando sistema...</p>
            
            <div class="loading-quote">
                <p class="loading-quote-text"><?= htmlspecialchars($fraseAleatoria) ?></p>
            </div>
        </div>
    </div>

    <!-- Background com partículas interativas -->
    <canvas id="particles-canvas"></canvas>
    
    <!-- Background sutil -->
    <div class="bg-image active" id="bg1" style="background-image: url('./img/fundo-1.jpeg')"></div>
    <div class="bg-image inactive" id="bg2" style="background-image: url('./img/fundo-2.jpeg')"></div>

    <!-- Container Principal -->
    <div class="login-container">
        <!-- Header minimalista e limpo -->
        <div class="login-header">
            <img src="./img/logo-assego.jpeg" alt="ASSEGO" class="header-logo">
            <div class="header-content">
                <h1 class="system-title">ASSEGO</h1>
                <p class="system-subtitle">Sistema de Gestão</p>
            </div>
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
                <!-- Campos Honeypot -->
                <div class="honeypot">
                    <input type="text" name="username" tabindex="-1" autocomplete="off">
                    <input type="tel" name="phone" tabindex="-1" autocomplete="off">
                    <input type="url" name="website" tabindex="-1" autocomplete="off">
                    <input type="text" name="company" tabindex="-1" autocomplete="off">
                    <input type="text" name="address" tabindex="-1" autocomplete="off">
                </div>
                
                <input type="hidden" name="form_time" value="<?php echo time(); ?>">
                <input type="hidden" name="js_enabled" id="js_enabled" value="0">
                <input type="hidden" name="honeypot_token" value="<?php echo $_SESSION['honeypot_token']; ?>">
                
                <!-- Email -->
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="email" 
                            class="form-input"
                            name="email" 
                            placeholder="Email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required 
                            autofocus>
                    </div>
                </div>
                
                <!-- Senha -->
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="password" 
                            class="form-input"
                            id="senha" 
                            name="senha" 
                            placeholder="Senha"
                            required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Turnstile -->
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
                
                <!-- Opções -->
                <div class="form-options">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="lembrar" name="lembrar" class="checkbox-input">
                        <label for="lembrar" class="checkbox-label">Lembrar acesso</label>
                    </div>
                    <a href="<?php echo BASE_URL ?? ''; ?>/pages/recuperar-senha.php" class="forgot-link">
                        Esqueceu a senha?
                    </a>
                </div>
                
                <!-- Botão -->
                <button type="submit" id="submitBtn" class="submit-btn" disabled>
                    Entrar
                </button>
            </form>
        </div>
        
        <!-- Footer -->
        <div class="login-footer">
            <p class="copyright">
                &copy; <?php echo date('Y'); ?> ASSEGO. Todos os direitos reservados.
            </p>
            <div class="protection">
                <svg fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Sistema protegido</span>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loader"></div>
    </div>
    
    <script>
        let turnstileVerified = false;
        
        // Função para alternar as imagens de fundo
        function alternateBackgrounds() {
            const bg1 = document.getElementById('bg1');
            const bg2 = document.getElementById('bg2');
            
            if (bg1.classList.contains('active')) {
                bg1.classList.remove('active');
                bg1.classList.add('inactive');
                bg2.classList.remove('inactive');
                bg2.classList.add('active');
            } else {
                bg2.classList.remove('active');
                bg2.classList.add('inactive');
                bg1.classList.remove('inactive');
                bg1.classList.add('active');
            }
        }

        // Loading e alternância de imagens
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.getElementById('initialLoading').classList.add('fade-out');
                setTimeout(() => {
                    alternateBackgrounds();
                    setInterval(alternateBackgrounds, 6000);
                }, 1000);
            }, 3000);
        });
         // JavaScript habilitado
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('js_enabled').value = '1';
        });
        
        // Callbacks do Turnstile
        function onTurnstileSuccess(token) {
            turnstileVerified = true;
            document.getElementById('submitBtn').disabled = false;
        }
        
        function onTurnstileError(error) {
            turnstileVerified = false;
            document.getElementById('submitBtn').disabled = true;
        }
        
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
            
            document.getElementById('loadingOverlay').classList.add('active');
            document.getElementById('submitBtn').classList.add('loading');
        });
        
        // Toggle da senha
        function togglePassword() {
            const input = document.getElementById('senha');
            const icon = document.querySelector('.password-toggle svg');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 01.66 10C2.36 6.91 6 4.5 10 4.5c1.2 0 2.37.18 3.5.5M9.9 4.24A9.12 9.12 0 01.66 10a14.5 14.5 0 006.58 6.58"/><path d="M6.61 6.61A13.526 13.526 0 00.41 10a13.526 13.526 0 0019.18 0A13.526 13.526 0 0013.39 13.39M9.9 4.24A9.12 9.12 0 0119.34 10"/><path d="m15 9l-6 6m0-6l6 6"/>';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>';
            }
        }
        
        // Auto-hide de alertas
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (!alert.classList.contains('alert-error')) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        }, 5000);

        // Sistema de partículas interativas
        class ParticleNetwork {
            constructor() {
                this.canvas = document.getElementById('particles-canvas');
                this.ctx = this.canvas.getContext('2d');
                this.particles = [];
                this.mouse = { x: null, y: null };
                this.animationId = null;
                
                this.settings = {
                    particleCount: 150,        // Quantidade de partículas (aumente para mais)
                    particleSize: 5,           // Tamanho das partículas (aumente para maiores)
                    connectionDistance: 140,   
                    mouseDistance: 200,        
                    particleSpeed: 0.5,        
                    lineOpacity: 0.75,         // Opacidade das linhas (aumente para mais visíveis)
                    mouseLineOpacity: 1.0,     // Opacidade das linhas com mouse (máximo)
                    particleOpacity: 1.0       // Opacidade das partículas (máximo)
                };
                
                this.init();
            }
            
            init() {
                this.resizeCanvas();
                this.createParticles();
                this.addEventListeners();
                this.animate();
            }
            
            resizeCanvas() {
                this.canvas.width = window.innerWidth;
                this.canvas.height = window.innerHeight;
            }
            
            createParticles() {
                this.particles = [];
                for (let i = 0; i < this.settings.particleCount; i++) {
                    this.particles.push({
                        x: Math.random() * this.canvas.width,
                        y: Math.random() * this.canvas.height,
                        vx: (Math.random() - 0.5) * this.settings.particleSpeed,
                        vy: (Math.random() - 0.5) * this.settings.particleSpeed,
                        size: Math.random() * this.settings.particleSize + 1
                    });
                }
            }
            
            addEventListeners() {
                window.addEventListener('resize', () => {
                    this.resizeCanvas();
                    this.createParticles();
                });
                
                document.addEventListener('mousemove', (e) => {
                    this.mouse.x = e.clientX;
                    this.mouse.y = e.clientY;
                });
                
                document.addEventListener('mouseleave', () => {
                    this.mouse.x = null;
                    this.mouse.y = null;
                });
            }
            
            updateParticles() {
                this.particles.forEach(particle => {
                    particle.x += particle.vx;
                    particle.y += particle.vy;
                    
                    // Rebote nas bordas
                    if (particle.x < 0 || particle.x > this.canvas.width) {
                        particle.vx *= -1;
                        particle.x = Math.max(0, Math.min(this.canvas.width, particle.x));
                    }
                    if (particle.y < 0 || particle.y > this.canvas.height) {
                        particle.vy *= -1;
                        particle.y = Math.max(0, Math.min(this.canvas.height, particle.y));
                    }
                });
            }
            
            drawParticles() {
                this.particles.forEach(particle => {
                    this.ctx.globalAlpha = 1.0; // Forçar máxima opacidade
                    
                    // Primeiro desenho: shadow/glow forte
                    this.ctx.shadowColor = '#FFFFFF';
                    this.ctx.shadowBlur = 15; // Brilho muito forte
                    this.ctx.fillStyle = '#FFFFFF';
                    this.ctx.beginPath();
                    this.ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                    this.ctx.fill();
                    
                    // Segundo desenho: partícula sólida por cima
                    this.ctx.shadowBlur = 0;
                    this.ctx.fillStyle = '#FFFFFF';
                    this.ctx.beginPath();
                    this.ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                    this.ctx.fill();
                    
                    // Terceiro desenho: centro super brilhante
                    this.ctx.fillStyle = '#FFFFFF';
                    this.ctx.beginPath();
                    this.ctx.arc(particle.x, particle.y, particle.size * 0.7, 0, Math.PI * 2);
                    this.ctx.fill();
                });
            }
            
            drawConnections() {
                for (let i = 0; i < this.particles.length; i++) {
                    for (let j = i + 1; j < this.particles.length; j++) {
                        const dx = this.particles[i].x - this.particles[j].x;
                        const dy = this.particles[i].y - this.particles[j].y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        
                        if (distance < this.settings.connectionDistance) {
                            const opacity = (1 - distance / this.settings.connectionDistance) * this.settings.lineOpacity;
                            this.ctx.globalAlpha = opacity;
                            this.ctx.strokeStyle = 'rgba(255, 255, 255, 1)';
                            this.ctx.lineWidth = 0.8;
                            this.ctx.beginPath();
                            this.ctx.moveTo(this.particles[i].x, this.particles[i].y);
                            this.ctx.lineTo(this.particles[j].x, this.particles[j].y);
                            this.ctx.stroke();
                        }
                    }
                }
            }
            
            drawMouseConnections() {
                if (this.mouse.x === null || this.mouse.y === null) return;
                
                this.particles.forEach(particle => {
                    const dx = particle.x - this.mouse.x;
                    const dy = particle.y - this.mouse.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < this.settings.mouseDistance) {
                        const opacity = (1 - distance / this.settings.mouseDistance) * this.settings.mouseLineOpacity;
                        this.ctx.globalAlpha = opacity;
                        this.ctx.strokeStyle = 'rgba(255, 255, 255, 1)';
                        this.ctx.lineWidth = 1.2;
                        this.ctx.beginPath();
                        this.ctx.moveTo(particle.x, particle.y);
                        this.ctx.lineTo(this.mouse.x, this.mouse.y);
                        this.ctx.stroke();
                    }
                });
            }
            
            animate() {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                
                this.updateParticles();
                this.drawConnections();
                this.drawMouseConnections();
                this.drawParticles();
                
                this.animationId = requestAnimationFrame(() => this.animate());
            }
            
            destroy() {
                if (this.animationId) {
                    cancelAnimationFrame(this.animationId);
                }
            }
        }
        
        // Inicializar partículas após o loading
        let particleNetwork;
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.getElementById('initialLoading').classList.add('fade-out');
                setTimeout(() => {
                    // Inicializar sistema de partículas
                    particleNetwork = new ParticleNetwork();
                    
                    alternateBackgrounds();
                    setInterval(alternateBackgrounds, 6000);
                }, 1000);
            }, 3000);
        });
    </script>
        
</body>
</html>