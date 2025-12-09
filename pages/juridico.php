<?php
/**
 * Página de Serviços Jurídicos - Sistema ASSEGO
 * pages/juridico.php
 * VERSÃO COM SISTEMA DE PERMISSÕES RBAC/ACL INTEGRADO
 * Sistema de navegação interno com componentes dinâmicos
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Permissoes.php';
require_once './components/header.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Serviços Jurídicos - ASSEGO';

// ===== SISTEMA DE PERMISSÕES RBAC/ACL =====
$permissoes = Permissoes::getInstance();

// Verificar se é do departamento jurídico (ID 3)
$departamentoJuridico = 3;
$isJuridico = ($usuarioLogado['departamento_id'] == $departamentoJuridico);

// Níveis de acesso
$isPresidencia = $permissoes->hasRole('PRESIDENTE') || $permissoes->hasRole('SUPER_ADMIN');
$isDiretor = $permissoes->isDiretor();
$departamentoUsuario = $usuarioLogado['departamento_id'] ?? null;

// Verificar permissão geral para o módulo jurídico (RBAC OU departamento OU diretor/presidência)
$temPermissaoJuridico = $permissoes->hasPermission('JURIDICO_DASHBOARD', 'VIEW')
    || $isJuridico
    || $isDiretor
    || $isPresidencia;

// Verificar permissões específicas para cada recurso
$permissoesDetalhadas = [
    'dashboard' => $temPermissaoJuridico,
    'desfiliacao' => [
        'visualizar' => $permissoes->hasPermission('JURIDICO_DESFILIACAO', 'VIEW') || $isJuridico || $isDiretor || $isPresidencia,
        'aprovar' => $permissoes->hasPermission('JURIDICO_DESFILIACAO_APROVAR', 'APPROVE') || $isJuridico || $isDiretor || $isPresidencia
    ]
];

// Log de debug das permissões
error_log("=== DEBUG PERMISSÕES JURÍDICAS RBAC/ACL ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("ID: " . ($usuarioLogado['id'] ?? 'NULL'));
error_log("Departamento: " . ($departamentoUsuario ?? 'NULL'));
error_log("Tem permissão jurídico: " . ($temPermissaoJuridico ? 'SIM' : 'NÃO'));
error_log("Roles: Jurídico=" . ($isJuridico ? 'SIM' : 'NÃO') . ", Presidência=" . ($isPresidencia ? 'SIM' : 'NÃO'));

$motivoNegacao = '';
if (!$temPermissaoJuridico) {
    $motivoNegacao = 'Você não possui permissão para acessar o módulo jurídico. Entre em contato com o administrador do sistema.';
    error_log("❌ ACESSO NEGADO: Sem permissão JURIDICO_DASHBOARD");
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $isDiretor,
    'activeTab' => 'juridico',
    'notificationCount' => 0,
    'showSearch' => true
]);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <style>
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #1e3d6f;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --juridico-purple: #7c3aed;
            --juridico-blue: #2563eb;
            --juridico-green: #059669;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--dark);
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 1.5rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--secondary);
            margin: 0;
        }

        /* Sistema de Navegação */
        .nav-tabs-container {
            padding: 0 !important;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 0 !important;
        }

        .juridico-nav-tabs {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 0;
            margin: 0 !important;
            list-style: none;
            border-bottom: none;
        }

        .nav-tab {
            flex: 0 0 auto;
            min-width: 180px;
            position: relative;
        }

        .nav-tab-btn {
            width: 100%;
            padding: 1.25rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            min-height: 85px;
        }

        .nav-tab-btn:hover {
            background: rgba(124, 58, 237, 0.1);
            color: var(--juridico-purple);
            transform: translateY(-2px);
        }

        .nav-tab-btn.active {
            background: white;
            color: var(--juridico-purple);
            box-shadow: 0 -2px 10px rgba(124, 58, 237, 0.1);
            transform: translateY(-2px);
        }

        .nav-tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, var(--juridico-purple) 0%, var(--juridico-blue) 100%);
        }

        .nav-tab-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            transition: transform 0.2s ease;
            margin-bottom: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .nav-tab-btn:hover .nav-tab-icon {
            transform: scale(1.05);
        }

        .nav-tab-btn.active .nav-tab-icon {
            transform: scale(1.1);
        }

        .nav-tab-label {
            font-size: 0.85rem;
            line-height: 1.2;
            font-weight: 600;
        }

        /* Ícone de Desfiliações */
        .juridico-nav-tabs .nav-tab .nav-tab-btn[data-target="desfiliacao-pendentes"] .nav-tab-icon {
            background: linear-gradient(135deg, var(--juridico-purple) 0%, #9333ea 100%);
        }

        .juridico-nav-tabs .nav-tab .nav-tab-btn[data-target="desfiliacao-pendentes"] .nav-tab-icon::before {
            content: "\f0e3";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        /* Content Area */
        .juridico-content {
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
        }

        .content-panel {
            padding: 0 !important;
            margin: 0 !important;
            min-height: auto !important;
            background: transparent !important;
            border: none !important;
            display: none;
        }

        .content-panel.active {
            display: block !important;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading States */
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 50px !important;
            flex-direction: column;
            gap: 0.5rem;
        }

        .spinner {
            width: 25px;
            height: 25px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--juridico-purple);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .permission-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            background: rgba(124, 58, 237, 0.1);
            color: var(--juridico-purple);
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.25rem;
            margin-left: 0.5rem;
        }

        .toast-container {
            z-index: 9999;
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            .juridico-nav-tabs {
                flex-direction: column;
            }
            .nav-tab {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoJuridico): ?>
                <!-- Sem Permissão -->
                <div class="alert alert-danger" data-aos="fade-up">
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado aos Serviços Jurídicos</h4>
                    <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                    <a href="../pages/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Com Permissão -->

                <!-- Page Header -->
                <div class="mb-4">
                    <h1 class="page-title">
                        <i class="fas fa-balance-scale me-2"></i>Serviços Jurídicos
                        <?php if ($isPresidencia): ?>
                            <span class="permission-badge">
                                <i class="fas fa-crown"></i> Presidência
                            </span>
                        <?php elseif ($isJuridico): ?>
                            <span class="permission-badge">
                                <i class="fas fa-gavel"></i> Jurídico
                            </span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">Gerencie aprovações jurídicas e processos legais da ASSEGO</p>
                </div>

                <!-- Navegação -->
                <div class="nav-tabs-container">
                    <ul class="juridico-nav-tabs">
                        <?php if ($permissoesDetalhadas['desfiliacao']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn active" data-target="desfiliacao-pendentes">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">
                                        Desfiliações
                                        <span id="desfiliacao-badge" class="badge bg-danger" style="display: none; margin-left: 0.5rem;">0</span>
                                    </span>
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Content Area -->
                <div class="juridico-content">
                    <?php if ($permissoesDetalhadas['desfiliacao']['visualizar']): ?>
                        <div id="desfiliacao-pendentes" class="content-panel active">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando desfiliações pendentes...</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Permissões do usuário
        const permissoesUsuario = <?php echo json_encode($permissoesDetalhadas); ?>;
        const temPermissaoJuridico = <?php echo json_encode($temPermissaoJuridico); ?>;
        const isJuridico = <?php echo json_encode($isJuridico); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;

        console.log('=== Permissões do Usuário (Jurídico) ===');
        console.log('Tem permissão jurídico:', temPermissaoJuridico);
        console.log('É do jurídico:', isJuridico);
        console.log('É da presidência:', isPresidencia);

        // Sistema de Notificações
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('toastContainer');
            }

            show(message, type = 'success', duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');

                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${this.getIcon(type)} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;

                this.container.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast, { delay: duration });
                bsToast.show();

                toast.addEventListener('hidden.bs.toast', () => {
                    toast.remove();
                });
            }

            getIcon(type) {
                const icons = {
                    success: 'check-circle',
                    error: 'exclamation-triangle',
                    warning: 'exclamation-circle',
                    info: 'info-circle'
                };
                return icons[type] || 'info-circle';
            }
        }

        const notifications = new NotificationSystem();
        const TAB_SCRIPTS = {
            'desfiliacao-pendentes': './rend/js/desfiliacao_juridico.js?v=' + Date.now()
        };

        const loadedScripts = new Set();

        function loadScriptOnce(src) {
            return new Promise((resolve, reject) => {
                if (loadedScripts.has(src)) {
                    console.log(`Script já carregado: ${src}`);
                    resolve();
                    return;
                }

                console.log(`Carregando script: ${src}`);
                const script = document.createElement('script');
                script.src = src;
                script.onload = () => {
                    loadedScripts.add(src);
                    console.log(`Script carregado com sucesso: ${src}`);
                    resolve();
                };
                script.onerror = (error) => {
                    console.error(`Erro ao carregar script: ${src}`, error);
                    reject(error);
                };
                document.head.appendChild(script);
            });
        }

        // Sistema de Navegação
        class JuridicoNavigation {
            constructor() {
                this.activeTab = 'desfiliacao-pendentes';
                this.loadedTabs = new Set([this.activeTab]);
                this.init();
            }

            init() {
                document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const target = e.currentTarget.dataset.target;
                        this.switchTab(target);
                    });
                });

                this.loadTabContent(this.activeTab);
            }

            switchTab(tabId) {
                if (this.activeTab === tabId) return;

                document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector(`[data-target="${tabId}"]`).classList.add('active');

                document.querySelectorAll('.content-panel').forEach(panel => {
                    panel.classList.remove('active');
                });

                const targetPanel = document.getElementById(tabId);
                if (targetPanel) {
                    targetPanel.classList.add('active');

                    if (!this.loadedTabs.has(tabId)) {
                        this.loadTabContent(tabId);
                        this.loadedTabs.add(tabId);
                    }
                }

                this.activeTab = tabId;
                notifications.show(`Seção ativada`, 'info', 2000);
            }

            async loadTabContent(tabId) {
                const panel = document.getElementById(tabId);
                if (!panel) return;

                const spinner = panel.querySelector('.loading-spinner');

                try {
                    console.log(`Carregando conteúdo: ${tabId}`);

                    if (tabId === 'desfiliacao-pendentes') {
                        const response = await fetch('./rend/desfiliacao_juridico_content.php');
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        const htmlContent = await response.text();
                        if (spinner) spinner.style.display = 'none';
                        panel.innerHTML = htmlContent;
                        
                        await loadScriptOnce(TAB_SCRIPTS[tabId]);
                        
                        if (typeof carregarDesfiliaçõesJuridico === 'function') {
                            console.log('Executando carregarDesfiliaçõesJuridico()');
                            carregarDesfiliaçõesJuridico();
                        }
                    }
                } catch (error) {
                    console.error(`Erro ao carregar ${tabId}:`, error);
                    panel.innerHTML = `
                        <div class="alert alert-danger" style="margin: 1rem;">
                            <h4><i class="fas fa-exclamation-triangle"></i> Erro ao Carregar</h4>
                            <p>${error.message}</p>
                        </div>
                    `;
                }
            }
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function () {
            AOS.init({ duration: 800, once: true });

            if (!temPermissaoJuridico) {
                console.log('❌ Usuário sem permissão para módulo jurídico');
                return;
            }

            new JuridicoNavigation();

            if (isPresidencia) {
                notifications.show('Sistema jurídico carregado - Acesso Presidência', 'success', 3000);
            } else if (isJuridico) {
                notifications.show('Sistema jurídico carregado - Acesso Setor Jurídico', 'success', 3000);
            } else {
                notifications.show('Sistema jurídico carregado', 'success', 3000);
            }

            console.log('✅ Sistema Jurídico inicializado');
        });
    </script>
</body>

</html>
