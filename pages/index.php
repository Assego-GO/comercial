<?php
/**
 * Página de Login com Cloudflare Turnstile
 * index.php
 */

// Incluir configurações
require_once '../config/config.php';
require_once '../config/database.php';

// Incluir classes
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

/**
 * Validador do Cloudflare Turnstile
 */
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

// Criar instância da classe Auth
$auth = new Auth();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $lembrar = isset($_POST['lembrar']);
    $turnstileToken = $_POST['cf-turnstile-response'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        // Validar Turnstile primeiro
        $turnstileValidator = new TurnstileValidator();
        $turnstileResult = $turnstileValidator->verify($turnstileToken, $_SERVER['REMOTE_ADDR']);
        
        if (!$turnstileResult['success']) {
            $erro = 'Verificação de segurança falhou: ' . $turnstileResult['message'];
        } else {
            // Prosseguir com o login
            $resultado = $auth->login($email, $senha);
            
            if ($resultado['success']) {
                // Redirecionar para página solicitada ou dashboard
                $redirect = $_GET['redirect'] ?? BASE_URL . '/pages/dashboard.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $erro = $resultado['message'];
            }
        }
    }
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
                    <div class="logo w-20 h-20 bg-white/20 backdrop-blur-sm rounded-full mx-auto flex items-center justify-center text-4xl animate-float border border-white/30">
                        <i class="fas fa-shield-alt text-white"></i>
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
                
                <form method="POST" action="" id="loginForm" class="space-y-6">
                    <!-- Email Field -->
                    <div class="form-group relative">
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-input w-full bg-white/5 backdrop-blur-sm border border-white/20 rounded-xl px-12 py-4 text-white placeholder-gray-300 focus:border-sky-400 focus:bg-white/10 transition-all duration-300"
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
                                   class="form-input w-full bg-white/5 backdrop-blur-sm border border-white/20 rounded-xl px-12 py-4 pr-16 text-white placeholder-gray-300 focus:border-sky-400 focus:bg-white/10 transition-all duration-300"
                                   id="senha" 
                                   name="senha" 
                                   placeholder="Sua Senha"
                                   required>
                            <button type="button" class="password-toggle absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white transition-colors" onclick="togglePassword()">
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
                        <span>Protegido por Cloudflare</span>
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
    
    <!-- Turnstile Integration Scripts -->
    <script>
        let turnstileVerified = false;
        
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
        });
        
        // Inicializar estado do botão
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        });
    </script>
    
    <!-- Auto-hide messages after 5 seconds -->
    <script>
        // Auto-hide logout messages
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-message');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html> 