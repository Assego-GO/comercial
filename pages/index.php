<?php
/**
 * Página de Login
 * index.php
 */

// Incluir configurações
require_once '../config/config.php';
require_once '../config/database.php';

// Incluir classes
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Criar instância da classe Auth
$auth = new Auth();

// Se já estiver logado, redirecionar
if ($auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

// Processar login
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $lembrar = isset($_POST['lembrar']);
    
    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
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
                    <button type="submit" class="btn-login w-full bg-gradient-to-r from-sky-600 to-blue-800 hover:from-sky-700 hover:to-blue-900 text-white font-bold py-4 px-8 rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-sky-500/50 relative overflow-hidden">
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
</body>
</html>