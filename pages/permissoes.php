<?php
/**
 * Sistema de Gerenciamento de Permissões RBAC + ACL
 * pages/permissoes.php
 * 
 * Versão completa com todas as melhorias implementadas
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
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
$permissoes = Permissoes::getInstance();

// Define o título da página
$page_title = 'Gerenciamento de Permissões - Sistema RBAC/ACL';

// Verifica permissões
$temPermissao = $permissoes->isSuperAdmin() ||
    $permissoes->hasPermission('SISTEMA_PERMISSOES', 'FULL') ||
    $permissoes->hasPermission('SISTEMA_PERMISSOES', 'VIEW');

$podeEditar = $permissoes->isSuperAdmin() ||
    $permissoes->hasPermission('SISTEMA_PERMISSOES', 'EDIT');

$podeCriar = $permissoes->isSuperAdmin() ||
    $permissoes->hasPermission('SISTEMA_PERMISSOES', 'CREATE');

$podeDeletar = $permissoes->isSuperAdmin() ||
    $permissoes->hasPermission('SISTEMA_PERMISSOES', 'DELETE');

// Buscar estatísticas se tem permissão
$stats = ['total_roles' => 0, 'total_recursos' => 0, 'total_delegacoes' => 0, 'logs_hoje' => 0];

if ($temPermissao) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

        // Total de roles
        $stmt = $db->query("SELECT COUNT(*) FROM roles WHERE ativo = 1");
        $stats['total_roles'] = $stmt->fetchColumn();

        // Total de recursos
        $stmt = $db->query("SELECT COUNT(*) FROM recursos WHERE ativo = 1");
        $stats['total_recursos'] = $stmt->fetchColumn();

        // Delegações ativas
        $stmt = $db->query("SELECT COUNT(*) FROM delegacoes WHERE ativo = 1 AND NOW() BETWEEN data_inicio AND data_fim");
        $stats['total_delegacoes'] = $stmt->fetchColumn();

        // Logs de hoje
        $stmt = $db->query("SELECT COUNT(*) FROM log_acessos WHERE DATE(criado_em) = CURDATE()");
        $stats['logs_hoje'] = $stmt->fetchColumn();

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas de permissões: " . $e->getMessage());
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'admin',
    'notificationCount' => 0,
    'showSearch' => false
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

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <style>
        /* ================================================
           SISTEMA DE PERMISSÕES - CSS COMPLETO MELHORADO
           ================================================ */

        /* === VARIÁVEIS GLOBAIS === */
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
            --border-color: #e0e0e0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 15px rgba(0, 86, 210, 0.12);
        }

        /* === CONFIGURAÇÕES BASE === */
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f5f7fa;
            color: var(--dark);
        }

        .content-area {
            padding: 1rem;
            margin-left: 0;
            position: relative;
        }

        /* === LOADING OVERLAY === */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .spinner-border-lg {
            width: 3rem;
            height: 3rem;
            border-width: 0.3em;
        }

        /* === LOADING STATES FOR ELEMENTS === */
        .element-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.6;
        }

        .element-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--primary);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* === PAGE HEADER === */
        .page-header-simple {
            background: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
        }

        .page-header-simple .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header-simple .page-title i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        /* === STATISTICS CARDS === */
        .stats-grid-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .stat-card-compact {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .stat-card-compact:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card-compact .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: white;
        }

        .stat-card-compact.primary .stat-icon {
            background: var(--primary);
        }

        .stat-card-compact.success .stat-icon {
            background: var(--success);
        }

        .stat-card-compact.warning .stat-icon {
            background: var(--warning);
        }

        .stat-card-compact.info .stat-icon {
            background: var(--info);
        }

        /* === QUICK ACTIONS === */
        .quick-actions-simple {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .quick-action-btn-simple {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--dark);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action-btn-simple:hover:not(:disabled) {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .quick-action-btn-simple:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* === NAVIGATION TABS === */
        .nav-tabs-custom {
            background: white;
            border-radius: 8px;
            padding: 0.25rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
        }

        .nav-tabs-custom .nav-link {
            color: var(--secondary);
            font-weight: 500;
            padding: 0.6rem 1.25rem;
            border-radius: 6px;
            border: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .nav-tabs-custom .nav-link:hover {
            background: rgba(0, 86, 210, 0.05);
            color: var(--primary);
        }

        .nav-tabs-custom .nav-link.active {
            background: var(--primary);
            color: white;
        }

        /* === CONTENT SECTIONS === */
        .content-section {
            background: white;
            border-radius: 8px;
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
            min-height: 400px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e9ecef;
        }

        /* === BADGES === */
        .badge-role {
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.75rem;
            display: inline-block;
        }

        .badge-super-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge-presidente {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .badge-diretor {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .badge-funcionario {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        /* === HIERARCHY TREE === */
        .hierarchy-tree {
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
        }

        .hierarchy-item {
            padding: 0.75rem;
            margin: 0.5rem 0;
            background: white;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            transition: all 0.3s ease;
        }

        .hierarchy-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
        }

        /* === PERMISSION MATRIX PREMIUM === */
        .permission-matrix {
            overflow-x: auto;
            border-radius: 12px;
            background: white;
            padding: 1rem;
        }

        .permission-matrix table {
            width: 100%;
        }

        .permission-cell {
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            padding: 0.75rem;
            position: relative;
            transition: all 0.2s ease;
        }

        .permission-cell:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            transform: scale(1.05);
        }

        .permission-check {
            color: var(--success);
            font-size: 1.25rem;
            filter: drop-shadow(0 2px 4px rgba(16, 185, 129, 0.3));
        }

        .permission-deny {
            color: var(--danger);
            font-size: 1.25rem;
            filter: drop-shadow(0 2px 4px rgba(239, 68, 68, 0.3));
        }

        /* === DELEGATION CARDS PREMIUM === */
        .delegation-card {
            border: 2px solid var(--border-light);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--lighter);
            position: relative;
            overflow: hidden;
        }

        .delegation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-primary);
        }

        .delegation-card:hover {
            border-color: rgba(102, 126, 234, 0.3);
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .delegation-badge {
            padding: 0.375rem 1rem;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .delegation-active {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: white;
        }

        .delegation-expired {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: white;
        }

        .delegation-future {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            color: white;
        }

        /* === TIMELINE LOGS PREMIUM === */
        .timeline {
            position: relative;
            padding-left: 3rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
            animation: slideInLeft 0.4s ease-out;
        }

        .timeline-marker {
            position: absolute;
            left: -2.125rem;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            background: white;
            border: 3px solid;
            box-shadow: var(--shadow-md);
        }

        .timeline-marker.bg-success { border-color: var(--success); }
        .timeline-marker.bg-danger { border-color: var(--danger); }
        .timeline-marker.bg-warning { border-color: var(--warning); }

        .timeline-content {
            background: var(--lighter);
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border-left: 3px solid var(--primary);
            transition: all 0.3s ease;
        }

        .timeline-content:hover {
            box-shadow: var(--shadow-lg);
            transform: translateX(4px);
        }

        /* === TOAST NOTIFICATIONS PREMIUM === */
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 1080;
        }

        .toast {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-2xl);
            margin-bottom: 1rem;
            animation: slideInRight 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 2px solid transparent;
        }

        .toast.bg-success {
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
            color: white;
        }

        .toast.bg-danger {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
        }

        .toast.bg-warning {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
        }

        .toast.bg-info {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            color: white;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* === LEVEL INDICATORS PREMIUM === */
        .level-indicator {
            display: inline-block;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            text-align: center;
            line-height: 36px;
            font-weight: 700;
            color: white;
            font-size: 0.875rem;
            box-shadow: var(--shadow-md);
        }

        .level-1000 { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
        .level-900 { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
        .level-800 { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .level-700 { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        .level-600 { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .level-500 { background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%); }
        .level-400 { background: linear-gradient(135deg, #84cc16 0%, #65a30d 100%); }
        .level-300 { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
        .level-200 { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .level-100 { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); }

        /* === SCROLLBAR CUSTOM === */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
        }

        /* === DATATABLES CUSTOM === */
        .dataTables_wrapper {
            font-size: 0.9375rem;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 1rem;
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.5rem 1rem;
            margin-left: 0.5rem;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--gradient-primary);
            border: none;
            color: white !important;
            border-radius: 8px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--light);
            border: none;
            color: var(--primary) !important;
        }

        /* === RESPONSIVE DESIGN === */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }

            .page-header-simple .page-title {
                font-size: 2rem;
            }

            .page-header-simple .page-subtitle {
                font-size: 1rem;
                padding-left: 0;
            }

            .stats-grid-compact {
                grid-template-columns: 1fr;
            }

            .quick-actions-simple {
                flex-direction: column;
            }

            .quick-action-btn-simple {
                width: 100%;
                justify-content: center;
            }

            .content-section {
                padding: 1.5rem;
                border-radius: 16px;
            }

            .nav-tabs-custom .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            .page-header-simple .page-title {
                font-size: 1.75rem;
                gap: 0.5rem;
            }

            .stat-card-compact {
                padding: 1.25rem;
            }

            .modal-dialog {
                margin: 1rem;
            }
        }

        /* === ANIMATIONS === */
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

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        /* === UTILITIES === */
        .shadow-sm { box-shadow: var(--shadow-sm) !important; }
        .shadow-md { box-shadow: var(--shadow-md) !important; }
        .shadow-lg { box-shadow: var(--shadow-lg) !important; }
        .shadow-xl { box-shadow: var(--shadow-xl) !important; }
        .shadow-2xl { box-shadow: var(--shadow-2xl) !important; }

        .rounded { border-radius: 12px !important; }
        .rounded-lg { border-radius: 16px !important; }
        .rounded-xl { border-radius: 20px !important; }

        .text-gradient {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border spinner-border-lg text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <div class="text-muted">Carregando dados...</div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="main-wrapper">
        <?php $headerComponent->render(); ?>
        
        <div class="content-area">
            <?php if (!$temPermissao): ?>
                <div class="alert alert-danger">
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado</h4>
                    <p>Você não tem permissão para acessar o gerenciamento de permissões.</p>
                    <a href="../pages/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>
            <?php else: ?>
                
                <!-- Page Header -->
                <div class="page-header-simple">
                    <h1 class="page-title">
                        <i class="fas fa-shield-alt"></i>
                        Gerenciamento de Permissões
                    </h1>
                    <p class="page-subtitle">Sistema RBAC (Role-Based Access Control) + ACL (Access Control List)</p>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid-compact">
                    <div class="stat-card-compact primary">
                        <div class="stat-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $stats['total_roles']; ?></div>
                            <div class="stat-label">Roles Ativas</div>
                        </div>
                    </div>
                    <div class="stat-card-compact success">
                        <div class="stat-icon">
                            <i class="fas fa-cube"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $stats['total_recursos']; ?></div>
                            <div class="stat-label">Recursos</div>
                        </div>
                    </div>
                    <div class="stat-card-compact warning">
                        <div class="stat-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $stats['total_delegacoes']; ?></div>
                            <div class="stat-label">Delegações</div>
                        </div>
                    </div>
                    <div class="stat-card-compact info">
                        <div class="stat-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $stats['logs_hoje']; ?></div>
                            <div class="stat-label">Acessos Hoje</div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <?php if ($podeCriar || $podeEditar): ?>
                <div class="quick-actions-simple">
                    <button class="quick-action-btn-simple" onclick="permissionManager.openModalNovaRole()">
                        <i class="fas fa-user-plus"></i> Nova Role
                    </button>
                    <button class="quick-action-btn-simple" onclick="permissionManager.openModalAtribuirRole()">
                        <i class="fas fa-user-tag"></i> Atribuir Role
                    </button>
                    <button class="quick-action-btn-simple" onclick="permissionManager.openModalDelegacao()">
                        <i class="fas fa-handshake"></i> Criar Delegação
                    </button>
                    <button class="quick-action-btn-simple" onclick="permissionManager.openModalPermissaoEspecifica()">
                        <i class="fas fa-key"></i> Permissão Específica
                    </button>
                    <button class="quick-action-btn-simple" onclick="permissionManager.refreshAllData()">
                        <i class="fas fa-sync-alt"></i> Atualizar Dados
                    </button>
                </div>
                <?php endif; ?>

                <!-- Navigation Tabs -->
                <nav class="nav-tabs-custom">
                    <ul class="nav nav-tabs border-0" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-roles">
                                <i class="fas fa-users-cog me-2"></i>Roles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-funcionarios">
                                <i class="fas fa-user-shield me-2"></i>Funcionários
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-recursos">
                                <i class="fas fa-cube me-2"></i>Recursos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-delegacoes">
                                <i class="fas fa-handshake me-2"></i>Delegações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-matriz">
                                <i class="fas fa-th me-2"></i>Matriz de Permissões
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-logs">
                                <i class="fas fa-history me-2"></i>Logs de Acesso
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Roles Tab -->
                    <div class="tab-pane fade show active" id="tab-roles">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-users-cog me-2"></i>
                                    Gerenciamento de Roles
                                </h3>
                                <?php if ($podeCriar): ?>
                                    <button class="btn btn-primary" onclick="permissionManager.openModalNovaRole()">
                                        <i class="fas fa-plus me-2"></i>Nova Role
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Hierarchy Visualization -->
                            <div class="hierarchy-tree mb-4">
                                <h5 class="mb-3">Hierarquia de Roles</h5>
                                <div id="hierarchyContainer">
                                    <!-- Será preenchido via JavaScript -->
                                </div>
                            </div>

                            <!-- Roles Table -->
                            <table id="rolesTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Role</th>
                                        <th>Nível</th>
                                        <th>Tipo</th>
                                        <th>Usuários</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Preenchido via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Funcionários Tab -->
                    <div class="tab-pane fade" id="tab-funcionarios">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-user-shield me-2"></i>
                                    Permissões por Funcionário
                                </h3>
                            </div>

                            <!-- Search and Filters -->
                            <div class="search-filter-bar">
                                <div class="search-input">
                                    <input type="text" class="form-control" id="searchFuncionario"
                                        placeholder="Buscar funcionário...">
                                </div>
                                <select class="form-select" id="filterDepartamento" style="width: 200px;">
                                    <option value="">Todos os Departamentos</option>
                                </select>
                                <select class="form-select" id="filterRole" style="width: 200px;">
                                    <option value="">Todas as Roles</option>
                                </select>
                            </div>

                            <!-- Funcionários Table -->
                            <table id="funcionariosTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Departamento</th>
                                        <th>Roles</th>
                                        <th>Permissões Especiais</th>
                                        <th>Delegações</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Preenchido via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recursos Tab -->
                    <div class="tab-pane fade" id="tab-recursos">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-cube me-2"></i>
                                    Recursos do Sistema
                                </h3>
                                <?php if ($podeCriar): ?>
                                    <button class="btn btn-primary" onclick="permissionManager.openModalNovoRecurso()">
                                        <i class="fas fa-plus me-2"></i>Novo Recurso
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Resources by Category -->
                            <div class="row" id="recursosContainer">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Delegações Tab -->
                    <div class="tab-pane fade" id="tab-delegacoes">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-handshake me-2"></i>
                                    Delegações Temporárias
                                </h3>
                                <?php if ($podeCriar): ?>
                                    <button class="btn btn-primary" onclick="permissionManager.openModalDelegacao()">
                                        <i class="fas fa-plus me-2"></i>Nova Delegação
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Delegation Cards -->
                            <div id="delegacoesContainer">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>

                    <!-- Matriz de Permissões Tab -->
                    <div class="tab-pane fade" id="tab-matriz">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-th me-2"></i>
                                    Matriz de Permissões
                                </h3>
                                <button class="btn btn-secondary btn-sm" onclick="permissionManager.exportMatrizPermissoes()">
                                    <i class="fas fa-download me-1"></i>Exportar
                                </button>
                            </div>

                            <!-- Permission Matrix -->
                            <div class="permission-matrix">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Recurso / Role</th>
                                            <!-- Roles serão adicionadas dinamicamente -->
                                        </tr>
                                    </thead>
                                    <tbody id="matrizPermissoesBody">
                                        <!-- Será preenchido via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Logs Tab -->
                    <div class="tab-pane fade" id="tab-logs">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-history me-2"></i>
                                    Logs de Acesso
                                </h3>
                                <button class="btn btn-secondary" onclick="permissionManager.exportarLogs()">
                                    <i class="fas fa-download me-2"></i>Exportar
                                </button>
                            </div>

                            <!-- Filters -->
                            <div class="search-filter-bar">
                                <input type="date" class="form-control" id="logDateStart" style="width: 150px;">
                                <input type="date" class="form-control" id="logDateEnd" style="width: 150px;">
                                <select class="form-select" id="logResultado" style="width: 150px;">
                                    <option value="">Todos</option>
                                    <option value="PERMITIDO">Permitido</option>
                                    <option value="NEGADO">Negado</option>
                                    <option value="ERRO">Erro</option>
                                </select>
                                <button class="btn btn-primary" onclick="permissionManager.filtrarLogs()">
                                    <i class="fas fa-filter"></i> Filtrar
                                </button>
                            </div>

                            <!-- Timeline Logs -->
                            <div class="timeline mt-4" id="logsTimeline">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Nova Role -->
    <div class="modal fade" id="modalNovaRole" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Nova Role
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovaRole">
                        <div class="mb-3">
                            <label class="form-label">Código da Role *</label>
                            <input type="text" class="form-control" id="roleCodigo" required 
                                   placeholder="Ex: COORDENADOR_TI">
                            <small class="text-muted">Use apenas letras maiúsculas e underscore</small>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome da Role *</label>
                            <input type="text" class="form-control" id="roleNome" required
                                   placeholder="Ex: Coordenador de TI">
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="roleDescricao" rows="3"
                                      placeholder="Descreva as responsabilidades desta role..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nível Hierárquico *</label>
                            <input type="number" class="form-control" id="roleNivel" min="0" max="999" required
                                   placeholder="0-999">
                            <small class="text-muted">Maior valor = mais privilégios</small>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" id="roleTipo">
                                <option value="CUSTOMIZADO">Customizado</option>
                                <option value="DEPARTAMENTO">Departamento</option>
                                <option value="SISTEMA">Sistema</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="permissionManager.salvarNovaRole()">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Atribuir Role -->
    <div class="modal fade" id="modalAtribuirRole" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tag me-2"></i>Atribuir Role
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAtribuirRole">
                        <div class="mb-3">
                            <label class="form-label">Funcionário *</label>
                            <select class="form-select select2" id="atribuirFuncionario" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select select2" id="atribuirRole" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Departamento (opcional)</label>
                            <select class="form-select" id="atribuirDepartamento">
                                <option value="">Todos os departamentos</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="atribuirDataInicio">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Fim (opcional)</label>
                            <input type="date" class="form-control" id="atribuirDataFim">
                            <small class="text-muted">Deixe em branco para atribuição permanente</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="atribuirObservacao" rows="2"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="atribuirPrincipal">
                            <label class="form-check-label" for="atribuirPrincipal">
                                Definir como role principal
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="permissionManager.salvarAtribuicaoRole()">
                        <i class="fas fa-save me-2"></i>Atribuir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Delegação -->
    <div class="modal fade" id="modalDelegacao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-handshake me-2"></i>Criar Delegação
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formDelegacao">
                        <div class="mb-3">
                            <label class="form-label">Delegante (Quem delega) *</label>
                            <select class="form-select select2" id="delegacaoDelegante" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delegado (Quem recebe) *</label>
                            <select class="form-select select2" id="delegacaoDelegado" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Delegação</label>
                            <select class="form-select" id="delegacaoTipo" onchange="permissionManager.toggleDelegacaoOptions()">
                                <option value="completa">Delegação Completa</option>
                                <option value="role">Role Específica</option>
                                <option value="recurso">Recurso Específico</option>
                            </select>
                        </div>
                        <div class="mb-3" id="delegacaoRoleGroup" style="display:none;">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="delegacaoRole">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3" id="delegacaoRecursoGroup" style="display:none;">
                            <label class="form-label">Recurso</label>
                            <select class="form-select" id="delegacaoRecurso">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data/Hora Início *</label>
                                    <input type="datetime-local" class="form-control" id="delegacaoInicio" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data/Hora Fim *</label>
                                    <input type="datetime-local" class="form-control" id="delegacaoFim" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo *</label>
                            <textarea class="form-control" id="delegacaoMotivo" rows="3" required
                                      placeholder="Ex: Férias, Licença médica, Viagem a trabalho..."></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="permissionManager.salvarDelegacao()">
                        <i class="fas fa-save me-2"></i>Criar Delegação
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Permissão Específica -->
    <div class="modal fade" id="modalPermissaoEspecifica" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>Permissão Específica
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formPermissaoEspecifica">
                        <div class="mb-3">
                            <label class="form-label">Funcionário *</label>
                            <select class="form-select select2" id="permEspecFuncionario" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recurso *</label>
                            <select class="form-select" id="permEspecRecurso" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permissão *</label>
                            <select class="form-select" id="permEspecPermissao" required>
                                <option value="">Selecione...</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo *</label>
                            <select class="form-select" id="permEspecTipo" required>
                                <option value="GRANT">GRANT (Conceder)</option>
                                <option value="DENY">DENY (Negar)</option>
                            </select>
                            <small class="text-muted">DENY tem precedência sobre GRANT</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo *</label>
                            <textarea class="form-control" id="permEspecMotivo" rows="3" required
                                      placeholder="Justifique a concessão/negação desta permissão..."></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Início</label>
                                    <input type="date" class="form-control" id="permEspecInicio">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Fim</label>
                                    <input type="date" class="form-control" id="permEspecFim">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="permissionManager.salvarPermissaoEspecifica()">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
    /**
     * Sistema de Gerenciamento de Permissões - JavaScript Completo
     * Versão melhorada com todas as implementações solicitadas
     */

    // ===========================
    // CACHE MANAGER COM TTL
    // ===========================
    class CacheManager {
        constructor(defaultTTL = 5 * 60 * 1000) { // 5 minutos padrão
            this.cache = new Map();
            this.timestamps = new Map();
            this.defaultTTL = defaultTTL;
        }

        set(key, value, ttl = this.defaultTTL) {
            this.cache.set(key, value);
            this.timestamps.set(key, Date.now() + ttl);
            return value;
        }

        get(key) {
            const expiry = this.timestamps.get(key);
            if (!expiry || Date.now() > expiry) {
                this.delete(key);
                return null;
            }
            return this.cache.get(key);
        }

        delete(key) {
            this.cache.delete(key);
            this.timestamps.delete(key);
        }

        clear() {
            this.cache.clear();
            this.timestamps.clear();
        }

        has(key) {
            return this.get(key) !== null;
        }
    }

    // ===========================
    // PERMISSION MANAGER CLASS
    // ===========================
    class PermissionManager {
        constructor() {
            this.cache = new CacheManager();
            this.dataTables = {};
            
            // Estado da aplicação
            this.state = {
                roles: [],
                funcionarios: [],
                recursos: [],
                permissoes: [],
                departamentos: [],
                delegacoes: [],
                logs: []
            };

            // Configurações de permissões
            this.permissions = {
                canEdit: <?php echo json_encode($podeEditar); ?>,
                canCreate: <?php echo json_encode($podeCriar); ?>,
                canDelete: <?php echo json_encode($podeDeletar); ?>
            };

            // Debounce functions
            this.debouncedSearch = this.debounce(this.performSearch.bind(this), 300);
            this.debouncedFilterLogs = this.debounce(this.filterLogs.bind(this), 500);
        }

        // ===========================
        // UTILITY FUNCTIONS
        // ===========================
        
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        showLoading(message = 'Carregando...') {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.querySelector('.text-muted').textContent = message;
                overlay.classList.add('active');
            }
        }

        hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }

        showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast_' + Date.now();
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type}" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                                data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('pt-BR');
        }

        formatDateTime(dateString) {
            return new Date(dateString).toLocaleString('pt-BR');
        }

        // ===========================
        // VALIDATION FUNCTIONS
        // ===========================
        
        validateRoleForm() {
            const form = document.getElementById('formNovaRole');
            const codigo = document.getElementById('roleCodigo');
            const nome = document.getElementById('roleNome');
            const nivel = document.getElementById('roleNivel');
            
            let isValid = true;
            
            // Reset validation states
            [codigo, nome, nivel].forEach(input => {
                input.classList.remove('is-invalid');
            });
            
            // Validate código
            if (!codigo.value) {
                this.showFieldError(codigo, 'Código é obrigatório');
                isValid = false;
            } else if (!/^[A-Z_]+$/.test(codigo.value)) {
                this.showFieldError(codigo, 'Use apenas letras maiúsculas e underscore');
                isValid = false;
            } else if (this.state.roles.some(r => r.codigo === codigo.value)) {
                this.showFieldError(codigo, 'Já existe uma role com este código');
                isValid = false;
            }
            
            // Validate nome
            if (!nome.value || nome.value.length < 3) {
                this.showFieldError(nome, 'Nome deve ter pelo menos 3 caracteres');
                isValid = false;
            }
            
            // Validate nível
            const nivelValue = parseInt(nivel.value);
            if (isNaN(nivelValue) || nivelValue < 0 || nivelValue > 999) {
                this.showFieldError(nivel, 'Nível deve estar entre 0 e 999');
                isValid = false;
            }
            
            return isValid;
        }

        validateDelegacaoForm() {
            const delegante = document.getElementById('delegacaoDelegante');
            const delegado = document.getElementById('delegacaoDelegado');
            const inicio = document.getElementById('delegacaoInicio');
            const fim = document.getElementById('delegacaoFim');
            const motivo = document.getElementById('delegacaoMotivo');
            
            let isValid = true;
            
            // Reset validation
            [delegante, delegado, inicio, fim, motivo].forEach(input => {
                input.classList.remove('is-invalid');
            });
            
            // Validations
            if (!delegante.value) {
                this.showFieldError(delegante, 'Selecione o delegante');
                isValid = false;
            }
            
            if (!delegado.value) {
                this.showFieldError(delegado, 'Selecione o delegado');
                isValid = false;
            }
            
            if (delegante.value === delegado.value) {
                this.showFieldError(delegado, 'Delegante e delegado devem ser diferentes');
                isValid = false;
            }
            
            if (!inicio.value) {
                this.showFieldError(inicio, 'Data de início é obrigatória');
                isValid = false;
            }
            
            if (!fim.value) {
                this.showFieldError(fim, 'Data de fim é obrigatória');
                isValid = false;
            }
            
            if (inicio.value && fim.value && new Date(inicio.value) >= new Date(fim.value)) {
                this.showFieldError(fim, 'Data de fim deve ser posterior à data de início');
                isValid = false;
            }
            
            if (!motivo.value || motivo.value.length < 10) {
                this.showFieldError(motivo, 'Motivo deve ter pelo menos 10 caracteres');
                isValid = false;
            }
            
            return isValid;
        }

        showFieldError(field, message) {
            field.classList.add('is-invalid');
            const feedback = field.nextElementSibling;
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = message;
            }
        }

        // ===========================
        // API CALLS WITH CACHE
        // ===========================
        
        async fetchWithCache(url, cacheKey, ttl = 5 * 60 * 1000) {
            // Check cache first
            const cached = this.cache.get(cacheKey);
            if (cached) {
                console.log(`Using cached data for: ${cacheKey}`);
                return cached;
            }
            
            // Fetch from API
            try {
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Store in cache
                this.cache.set(cacheKey, data, ttl);
                
                return data;
            } catch (error) {
                console.error(`Error fetching ${url}:`, error);
                throw error;
            }
        }

        async apiCall(url, options = {}) {
            try {
                const response = await fetch(url, {
                    ...options,
                    headers: {
                        'Content-Type': 'application/json',
                        ...options.headers
                    }
                });
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => null);
                    throw {
                        status: response.status,
                        message: errorData?.error || errorData?.message || 'Erro desconhecido',
                        data: errorData
                    };
                }
                
                return await response.json();
            } catch (error) {
                if (error.status === 403) {
                    throw { ...error, message: 'Sem permissão para realizar esta ação' };
                } else if (error.status === 409) {
                    throw { ...error, message: 'Conflito: Item já existe' };
                } else if (error.status === 404) {
                    throw { ...error, message: 'Recurso não encontrado' };
                }
                throw error;
            }
        }

        // ===========================
        // DATA LOADING
        // ===========================
        
        async loadAllData() {
            this.showLoading('Carregando dados do sistema...');
            
            try {
                // Load data in parallel with error handling for each
                const [roles, funcionarios, recursos, permissoes, departamentos, delegacoes] = await Promise.allSettled([
                    this.fetchWithCache('../api/permissoes/listar_roles.php', 'roles'),
                    this.fetchWithCache('../api/permissoes/listar_funcionarios.php', 'funcionarios'),
                    this.fetchWithCache('../api/permissoes/listar_recursos.php', 'recursos'),
                    this.fetchWithCache('../api/permissoes/listar_permissoes.php', 'permissoes'),
                    this.fetchWithCache('../api/permissoes/listar_departamentos.php', 'departamentos'),
                    this.fetchWithCache('../api/permissoes/listar_delegacoes.php', 'delegacoes')
                ]);

                // Process results
                this.state.roles = roles.status === 'fulfilled' ? roles.value : [];
                this.state.funcionarios = funcionarios.status === 'fulfilled' ? funcionarios.value : [];
                this.state.recursos = recursos.status === 'fulfilled' ? recursos.value : [];
                this.state.permissoes = permissoes.status === 'fulfilled' ? permissoes.value : [];
                this.state.departamentos = departamentos.status === 'fulfilled' ? departamentos.value : [];
                this.state.delegacoes = delegacoes.status === 'fulfilled' ? delegacoes.value : [];

                // Update UI
                this.updateAllUI();
                
                // Load logs separately
                this.loadRecentLogs();
                
            } catch (error) {
                console.error('Error loading data:', error);
                Swal.fire('Erro', 'Erro ao carregar dados do sistema', 'error');
            } finally {
                this.hideLoading();
            }
        }

        async refreshAllData() {
            // Clear cache
            this.cache.clear();
            
            // Reload data
            await this.loadAllData();
            
            this.showToast('Dados atualizados com sucesso', 'success');
        }

        updateAllUI() {
            this.updateRolesTable();
            this.updateFuncionariosTable();
            this.updateHierarchy();
            this.updateRecursos();
            this.updateDelegacoes();
            this.updateMatrizPermissoes();
            this.populateSelects();
        }

        // ===========================
        // TABLE UPDATES
        // ===========================
        
        updateRolesTable() {
            const table = this.getDataTable('rolesTable');
            if (!table) return;
            
            table.clear();
            
            this.state.roles.forEach(role => {
                const badge = this.getRoleBadge(role.codigo);
                const levelIndicator = `<span class="level-indicator level-${Math.min(Math.floor(role.nivel_hierarquia / 100) * 100, 1000)}">${role.nivel_hierarquia}</span>`;
                const status = role.ativo ?
                    '<span class="badge bg-success">Ativo</span>' :
                    '<span class="badge bg-secondary">Inativo</span>';
                
                const actions = `
                    <button class="btn btn-sm btn-info btn-action" onclick="permissionManager.viewRole(${role.id})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${this.permissions.canEdit ? `
                    <button class="btn btn-sm btn-warning btn-action" onclick="permissionManager.editRole(${role.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-primary btn-action" onclick="permissionManager.configureRolePermissions(${role.id})" title="Configurar Permissões">
                        <i class="fas fa-cog"></i>
                    </button>` : ''}
                    ${this.permissions.canDelete ? `
                    <button class="btn btn-sm btn-danger btn-action" onclick="permissionManager.deleteRole(${role.id})" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>` : ''}
                `;
                
                table.row.add([
                    badge + ' ' + role.nome,
                    levelIndicator,
                    role.tipo,
                    role.total_usuarios || 0,
                    status,
                    actions
                ]);
            });
            
            table.draw();
        }

        updateFuncionariosTable() {
            const table = this.getDataTable('funcionariosTable');
            if (!table) return;
            
            table.clear();
            
            this.state.funcionarios.forEach(func => {
                const roles = func.roles ? func.roles.map(r =>
                    `<span class="badge bg-primary me-1">${r.nome}</span>`
                ).join(' ') : '-';
                
                const permEspeciais = func.permissoes_especiais || 0;
                const delegacoes = func.delegacoes_ativas || 0;
                
                const actions = `
                    <button class="btn btn-sm btn-info btn-action" onclick="permissionManager.viewFuncionarioPermissions(${func.id})" title="Visualizar Permissões">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${this.permissions.canEdit ? `
                    <button class="btn btn-sm btn-warning btn-action" onclick="permissionManager.editFuncionarioPermissions(${func.id})" title="Editar Permissões">
                        <i class="fas fa-edit"></i>
                    </button>` : ''}
                `;
                
                table.row.add([
                    func.nome,
                    func.departamento || '-',
                    roles,
                    permEspeciais > 0 ? `<span class="badge bg-warning">${permEspeciais}</span>` : '-',
                    delegacoes > 0 ? `<span class="badge bg-info">${delegacoes}</span>` : '-',
                    actions
                ]);
            });
            
            table.draw();
        }

        updateHierarchy() {
            const container = document.getElementById('hierarchyContainer');
            if (!container) return;
            
            container.innerHTML = '';
            
            // Sort roles by hierarchy level
            const sortedRoles = [...this.state.roles].sort((a, b) => b.nivel_hierarquia - a.nivel_hierarquia);
            
            sortedRoles.forEach(role => {
                const item = document.createElement('div');
                item.className = 'hierarchy-item fade-in';
                item.style.marginLeft = `${Math.max(0, (1000 - role.nivel_hierarquia) / 20)}px`;
                
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${role.nome}</strong>
                            <span class="hierarchy-level ms-2">Nível ${role.nivel_hierarquia}</span>
                        </div>
                        <div>
                            ${this.getRoleBadge(role.codigo)}
                        </div>
                    </div>
                    <small class="text-muted">${role.descricao || 'Sem descrição'}</small>
                `;
                
                container.appendChild(item);
            });
        }

        updateRecursos() {
            const container = document.getElementById('recursosContainer');
            if (!container) return;
            
            container.innerHTML = '';
            
            // Group resources by category
            const categorias = {};
            this.state.recursos.forEach(recurso => {
                if (!categorias[recurso.categoria]) {
                    categorias[recurso.categoria] = [];
                }
                categorias[recurso.categoria].push(recurso);
            });
            
            // Create cards by category
            Object.keys(categorias).forEach(categoria => {
                const col = document.createElement('div');
                col.className = 'col-md-6 mb-3';
                
                const card = document.createElement('div');
                card.className = 'card fade-in';
                
                card.innerHTML = `
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-folder me-2"></i>${categoria}
                            <span class="badge bg-secondary float-end">${categorias[categoria].length}</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            ${categorias[categoria].map(r => `
                                <li class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-cube text-primary me-2"></i>
                                            <strong>${r.nome}</strong>
                                            <small class="text-muted d-block ms-4">${r.codigo}</small>
                                        </div>
                                        ${this.permissions.canEdit ? `
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="permissionManager.editRecurso(${r.id})" 
                                                title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>` : ''}
                                    </div>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
                
                col.appendChild(card);
                container.appendChild(col);
            });
        }

        updateDelegacoes() {
            const container = document.getElementById('delegacoesContainer');
            if (!container) return;
            
            container.innerHTML = '';
            
            if (this.state.delegacoes.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhuma delegação ativa no momento.</p>';
                return;
            }
            
            this.state.delegacoes.forEach(del => {
                const now = new Date();
                const inicio = new Date(del.data_inicio);
                const fim = new Date(del.data_fim);
                
                let status = 'delegation-future';
                let badge = 'Futura';
                
                if (now >= inicio && now <= fim) {
                    status = 'delegation-active';
                    badge = 'Ativa';
                } else if (now > fim) {
                    status = 'delegation-expired';
                    badge = 'Expirada';
                }
                
                const card = document.createElement('div');
                card.className = 'delegation-card fade-in';
                
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>
                                <i class="fas fa-user me-2"></i>${del.delegante_nome}
                                <i class="fas fa-arrow-right mx-2"></i>
                                <i class="fas fa-user me-2"></i>${del.delegado_nome}
                            </h6>
                            <p class="mb-2">${del.motivo}</p>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                ${this.formatDate(del.data_inicio)} até ${this.formatDate(del.data_fim)}
                            </small>
                        </div>
                        <div>
                            <span class="delegation-badge ${status}">${badge}</span>
                            ${this.permissions.canDelete && status !== 'delegation-expired' ? `
                            <button class="btn btn-sm btn-danger ms-2" 
                                    onclick="permissionManager.cancelDelegacao(${del.id})" 
                                    title="Cancelar">
                                <i class="fas fa-times"></i>
                            </button>` : ''}
                        </div>
                    </div>
                `;
                
                container.appendChild(card);
            });
        }

        async updateMatrizPermissoes() {
            const thead = document.querySelector('#tab-matriz thead tr');
            const tbody = document.getElementById('matrizPermissoesBody');
            
            if (!thead || !tbody) return;
            
            try {
                // Mock data for demonstration
                const matrizData = {};
                
                // Build header
                thead.innerHTML = '<th>Recurso / Role</th>';
                this.state.roles.forEach(role => {
                    thead.innerHTML += `
                        <th class="text-center" style="writing-mode: vertical-rl; text-orientation: mixed;">
                            ${role.codigo}
                        </th>`;
                });
                
                // Build body
                tbody.innerHTML = '';
                
                // Group resources by category
                const categorias = {};
                this.state.recursos.forEach(recurso => {
                    if (!categorias[recurso.categoria]) {
                        categorias[recurso.categoria] = [];
                    }
                    categorias[recurso.categoria].push(recurso);
                });
                
                for (const [categoria, recursos] of Object.entries(categorias)) {
                    // Add category row
                    tbody.innerHTML += `
                        <tr class="table-secondary">
                            <td colspan="${this.state.roles.length + 1}">
                                <strong>${categoria}</strong>
                            </td>
                        </tr>`;
                    
                    // Add resource rows
                    recursos.forEach(recurso => {
                        let row = `<tr><td class="ps-3">${recurso.nome}</td>`;
                        
                        this.state.roles.forEach(role => {
                            // Mock permission check
                            const hasPermission = Math.random() > 0.5;
                            
                            row += `
                                <td class="permission-cell text-center" 
                                    style="cursor: pointer;"
                                    onclick="permissionManager.editPermissionMatrix(${role.id}, ${recurso.id})">
                                    ${hasPermission ?
                                        '<i class="fas fa-check permission-check"></i>' :
                                        '<i class="fas fa-times permission-deny"></i>'}
                                </td>`;
                        });
                        
                        row += '</tr>';
                        tbody.innerHTML += row;
                    });
                }
            } catch (error) {
                console.error('Error updating permission matrix:', error);
            }
        }

        async loadRecentLogs() {
            try {
                const logs = await this.fetchWithCache(
                    '../api/permissoes/listar_logs.php?limit=20',
                    'recent_logs',
                    1 * 60 * 1000 // 1 minute cache
                );
                
                this.state.logs = logs;
                this.updateLogsTimeline(logs);
            } catch (error) {
                console.error('Error loading logs:', error);
            }
        }

        updateLogsTimeline(logs) {
            const timeline = document.getElementById('logsTimeline');
            if (!timeline) return;
            
            timeline.innerHTML = '';
            
            if (!logs || logs.length === 0) {
                timeline.innerHTML = '<p class="text-muted">Nenhum log encontrado.</p>';
                return;
            }
            
            logs.forEach(log => {
                const item = document.createElement('div');
                item.className = 'timeline-item';
                
                const resultadoClass = log.resultado === 'PERMITIDO' ? 'success' :
                                       log.resultado === 'NEGADO' ? 'danger' : 'warning';
                
                item.innerHTML = `
                    <div class="timeline-marker bg-${resultadoClass}"></div>
                    <div class="timeline-content">
                        <div class="d-flex justify-content-between">
                            <strong>${log.funcionario_nome || 'Sistema'}</strong>
                            <small class="text-muted">${this.formatDateTime(log.criado_em)}</small>
                        </div>
                        <p class="mb-1">${log.acao}</p>
                        <span class="badge bg-${resultadoClass}">${log.resultado}</span>
                        ${log.motivo_negacao ? `<small class="text-danger d-block mt-1">${log.motivo_negacao}</small>` : ''}
                        ${log.ip ? `<small class="text-muted d-block">IP: ${log.ip}</small>` : ''}
                    </div>
                `;
                
                timeline.appendChild(item);
            });
        }

        // ===========================
        // MODAL FUNCTIONS
        // ===========================
        
        openModalNovaRole() {
            // Reset form
            document.getElementById('formNovaRole').reset();
            
            // Clear validation states
            document.querySelectorAll('#formNovaRole .is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            $('#modalNovaRole').modal('show');
        }

        openModalAtribuirRole() {
            document.getElementById('formAtribuirRole').reset();
            $('#modalAtribuirRole').modal('show');
        }

        openModalDelegacao() {
            document.getElementById('formDelegacao').reset();
            $('#modalDelegacao').modal('show');
        }

        openModalPermissaoEspecifica() {
            document.getElementById('formPermissaoEspecifica').reset();
            $('#modalPermissaoEspecifica').modal('show');
        }

        openModalNovoRecurso() {
            // Implementation for new resource modal
            Swal.fire('Em desenvolvimento', 'Modal de novo recurso será implementado', 'info');
        }

        toggleDelegacaoOptions() {
            const tipo = document.getElementById('delegacaoTipo').value;
            document.getElementById('delegacaoRoleGroup').style.display = tipo === 'role' ? 'block' : 'none';
            document.getElementById('delegacaoRecursoGroup').style.display = tipo === 'recurso' ? 'block' : 'none';
        }

        // ===========================
        // SAVE FUNCTIONS
        // ===========================
        
        async salvarNovaRole() {
            if (!this.validateRoleForm()) {
                return;
            }
            
            const data = {
                codigo: document.getElementById('roleCodigo').value,
                nome: document.getElementById('roleNome').value,
                descricao: document.getElementById('roleDescricao').value,
                nivel_hierarquia: document.getElementById('roleNivel').value,
                tipo: document.getElementById('roleTipo').value
            };
            
            this.showLoading('Salvando nova role...');
            
            try {
                const result = await this.apiCall('../api/permissoes/criar_role.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                
                if (result.success) {
                    $('#modalNovaRole').modal('hide');
                    this.showToast('Role criada com sucesso!', 'success');
                    this.cache.delete('roles'); // Clear cache
                    await this.loadAllData();
                } else {
                    throw { message: result.message || 'Erro ao criar role' };
                }
            } catch (error) {
                Swal.fire('Erro', error.message || 'Erro ao criar role', 'error');
            } finally {
                this.hideLoading();
            }
        }

        async salvarAtribuicaoRole() {
            const funcionario = document.getElementById('atribuirFuncionario');
            const role = document.getElementById('atribuirRole');
            
            // Basic validation
            if (!funcionario.value || !role.value) {
                if (!funcionario.value) this.showFieldError(funcionario, 'Selecione um funcionário');
                if (!role.value) this.showFieldError(role, 'Selecione uma role');
                return;
            }
            
            const data = {
                funcionario_id: funcionario.value,
                role_id: role.value,
                departamento_id: document.getElementById('atribuirDepartamento').value || null,
                data_inicio: document.getElementById('atribuirDataInicio').value,
                data_fim: document.getElementById('atribuirDataFim').value || null,
                principal: document.getElementById('atribuirPrincipal').checked,
                observacao: document.getElementById('atribuirObservacao').value
            };
            
            this.showLoading('Atribuindo role...');
            
            try {
                const result = await this.apiCall('../api/permissoes/atribuir_role.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                
                if (result.success) {
                    $('#modalAtribuirRole').modal('hide');
                    this.showToast('Role atribuída com sucesso!', 'success');
                    this.cache.delete('funcionarios');
                    await this.loadAllData();
                } else {
                    throw { message: result.message || 'Erro ao atribuir role' };
                }
            } catch (error) {
                Swal.fire('Erro', error.message || 'Erro ao atribuir role', 'error');
            } finally {
                this.hideLoading();
            }
        }

        async salvarDelegacao() {
            if (!this.validateDelegacaoForm()) {
                return;
            }
            
            const tipo = document.getElementById('delegacaoTipo').value;
            const data = {
                delegante_id: document.getElementById('delegacaoDelegante').value,
                delegado_id: document.getElementById('delegacaoDelegado').value,
                role_id: tipo === 'role' ? document.getElementById('delegacaoRole').value : null,
                recurso_id: tipo === 'recurso' ? document.getElementById('delegacaoRecurso').value : null,
                data_inicio: document.getElementById('delegacaoInicio').value,
                data_fim: document.getElementById('delegacaoFim').value,
                motivo: document.getElementById('delegacaoMotivo').value
            };
            
            this.showLoading('Criando delegação...');
            
            try {
                const result = await this.apiCall('../api/permissoes/criar_delegacao.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                
                if (result.success) {
                    $('#modalDelegacao').modal('hide');
                    this.showToast('Delegação criada com sucesso!', 'success');
                    this.cache.delete('delegacoes');
                    await this.loadAllData();
                } else {
                    throw { message: result.message || 'Erro ao criar delegação' };
                }
            } catch (error) {
                Swal.fire('Erro', error.message || 'Erro ao criar delegação', 'error');
            } finally {
                this.hideLoading();
            }
        }

        async salvarPermissaoEspecifica() {
            const funcionario = document.getElementById('permEspecFuncionario');
            const recurso = document.getElementById('permEspecRecurso');
            const permissao = document.getElementById('permEspecPermissao');
            const motivo = document.getElementById('permEspecMotivo');
            
            // Validation
            let isValid = true;
            [funcionario, recurso, permissao, motivo].forEach(field => {
                field.classList.remove('is-invalid');
                if (!field.value || (field === motivo && field.value.length < 10)) {
                    this.showFieldError(field, field === motivo ? 'Motivo deve ter pelo menos 10 caracteres' : 'Campo obrigatório');
                    isValid = false;
                }
            });
            
            if (!isValid) return;
            
            const data = {
                funcionario_id: funcionario.value,
                recurso_id: recurso.value,
                permissao_id: permissao.value,
                tipo: document.getElementById('permEspecTipo').value,
                motivo: motivo.value,
                data_inicio: document.getElementById('permEspecInicio').value || null,
                data_fim: document.getElementById('permEspecFim').value || null
            };
            
            this.showLoading('Salvando permissão específica...');
            
            try {
                const result = await this.apiCall('../api/permissoes/criar_permissao_especifica.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                
                if (result.success) {
                    $('#modalPermissaoEspecifica').modal('hide');
                    this.showToast('Permissão específica criada com sucesso!', 'success');
                    this.cache.clear(); // Clear all cache as this affects multiple areas
                    await this.loadAllData();
                } else {
                    throw { message: result.message || 'Erro ao criar permissão' };
                }
            } catch (error) {
                Swal.fire('Erro', error.message || 'Erro ao criar permissão específica', 'error');
            } finally {
                this.hideLoading();
            }
        }

        // ===========================
        // VIEW/EDIT FUNCTIONS
        // ===========================
        
        async viewRole(id) {
            const role = this.state.roles.find(r => r.id == id);
            if (!role) return;
            
            try {
                const permissoes = await this.fetchWithCache(
                    `../api/permissoes/obter_permissoes_role.php?id=${id}`,
                    `role_permissions_${id}`
                );
                
                let permissoesHtml = '';
                if (permissoes && permissoes.length > 0) {
                    permissoesHtml = '<h6>Permissões:</h6><ul class="list-unstyled">';
                    permissoes.forEach(p => {
                        permissoesHtml += `<li><i class="fas fa-check text-success me-2"></i>${p.recurso_nome} - ${p.permissao_nome}</li>`;
                    });
                    permissoesHtml += '</ul>';
                } else {
                    permissoesHtml = '<p class="text-muted">Nenhuma permissão configurada.</p>';
                }
                
                Swal.fire({
                    title: `<i class="fas fa-users-cog me-2"></i>${role.nome}`,
                    html: `
                        <div class="text-start">
                            <p><strong>Código:</strong> ${role.codigo}</p>
                            <p><strong>Descrição:</strong> ${role.descricao || 'Sem descrição'}</p>
                            <p><strong>Nível Hierárquico:</strong> ${role.nivel_hierarquia}</p>
                            <p><strong>Tipo:</strong> ${role.tipo}</p>
                            <p><strong>Usuários:</strong> ${role.total_usuarios || 0}</p>
                            <p><strong>Status:</strong> ${role.ativo ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>'}</p>
                            <hr>
                            ${permissoesHtml}
                        </div>
                    `,
                    width: 600,
                    confirmButtonText: 'Fechar'
                });
            } catch (error) {
                console.error('Error viewing role:', error);
                Swal.fire('Erro', 'Erro ao carregar detalhes da role', 'error');
            }
        }

        async editRole(id) {
            const role = this.state.roles.find(r => r.id == id);
            if (!role) return;
            
            const { value: formValues } = await Swal.fire({
                title: 'Editar Role',
                html: `
                    <div class="form-group mb-3">
                        <label>Nome da Role</label>
                        <input id="swal-nome" class="swal2-input" value="${role.nome}">
                    </div>
                    <div class="form-group mb-3">
                        <label>Descrição</label>
                        <textarea id="swal-descricao" class="swal2-textarea">${role.descricao || ''}</textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label>Nível Hierárquico</label>
                        <input id="swal-nivel" type="number" class="swal2-input" value="${role.nivel_hierarquia}" min="0" max="999">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="swal-ativo" class="swal2-select">
                            <option value="1" ${role.ativo ? 'selected' : ''}>Ativo</option>
                            <option value="0" ${!role.ativo ? 'selected' : ''}>Inativo</option>
                        </select>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    return {
                        nome: document.getElementById('swal-nome').value,
                        descricao: document.getElementById('swal-descricao').value,
                        nivel_hierarquia: document.getElementById('swal-nivel').value,
                        ativo: document.getElementById('swal-ativo').value
                    }
                }
            });
            
            if (formValues) {
                this.showLoading('Salvando alterações...');
                
                try {
                    const result = await this.apiCall('../api/permissoes/editar_role.php', {
                        method: 'POST',
                        body: JSON.stringify({ id, ...formValues })
                    });
                    
                    if (result.success) {
                        this.showToast('Role atualizada com sucesso!', 'success');
                        this.cache.delete('roles');
                        await this.loadAllData();
                    } else {
                        throw { message: result.error || 'Erro ao editar role' };
                    }
                } catch (error) {
                    Swal.fire('Erro', error.message || 'Erro ao editar role', 'error');
                } finally {
                    this.hideLoading();
                }
            }
        }

        async configureRolePermissions(id) {
            const role = this.state.roles.find(r => r.id == id);
            if (!role) return;
            
            // Implementation for configuring role permissions
            this.showToast('Configuração de permissões em desenvolvimento', 'info');
        }

        async deleteRole(id) {
            const role = this.state.roles.find(r => r.id == id);
            if (!role) return;
            
            const result = await Swal.fire({
                title: 'Confirmar Exclusão',
                html: `
                    <p>Tem certeza que deseja excluir a role <strong>${role.nome}</strong>?</p>
                    <p class="text-danger">Esta ação irá remover todas as atribuições desta role!</p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            });
            
            if (result.isConfirmed) {
                this.showLoading('Excluindo role...');
                
                try {
                    const response = await this.apiCall('../api/permissoes/excluir_role.php', {
                        method: 'POST',
                        body: JSON.stringify({ id })
                    });
                    
                    if (response.success) {
                        this.showToast('Role excluída com sucesso!', 'success');
                        this.cache.delete('roles');
                        await this.loadAllData();
                    } else {
                        throw { message: response.error || 'Erro ao excluir role' };
                    }
                } catch (error) {
                    Swal.fire('Erro', error.message || 'Erro ao excluir role', 'error');
                } finally {
                    this.hideLoading();
                }
            }
        }

        async viewFuncionarioPermissions(id) {
            const func = this.state.funcionarios.find(f => f.id == id);
            if (!func) return;
            
            this.showLoading('Carregando permissões...');
            
            try {
                const data = await this.fetchWithCache(
                    `../api/permissoes/visualizar_permissoes_funcionario.php?id=${id}`,
                    `funcionario_permissions_${id}`
                );
                
                if (data.error) {
                    throw { message: data.error };
                }
                
                // Build detailed HTML
                let html = `
                    <div class="funcionario-details">
                        <div class="mb-3">
                            <h6>Informações do Funcionário</h6>
                            <p><strong>Nome:</strong> ${data.funcionario.nome}</p>
                            <p><strong>Email:</strong> ${data.funcionario.email}</p>
                            <p><strong>Cargo:</strong> ${data.funcionario.cargo || '-'}</p>
                            <p><strong>Departamento:</strong> ${data.funcionario.departamento_nome || '-'}</p>
                        </div>`;
                
                // Roles
                if (data.roles && data.roles.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6>Roles Atribuídas</h6>
                            <ul class="list-unstyled">`;
                    
                    data.roles.forEach(role => {
                        html += `
                            <li class="mb-2">
                                <i class="fas fa-user-tag text-primary me-2"></i>
                                <strong>${role.role_nome}</strong>
                                ${role.principal ? '<span class="badge bg-success ms-2">Principal</span>' : ''}
                                ${role.departamento_nome ? `<small class="text-muted"> (${role.departamento_nome})</small>` : ''}
                            </li>`;
                    });
                    
                    html += '</ul></div>';
                }
                
                // Special permissions
                if (data.permissoes_especificas && data.permissoes_especificas.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6>Permissões Específicas</h6>
                            <ul class="list-unstyled">`;
                    
                    data.permissoes_especificas.forEach(perm => {
                        const icon = perm.tipo === 'GRANT' ?
                            'fa-check text-success' :
                            'fa-times text-danger';
                        
                        html += `
                            <li class="mb-2">
                                <i class="fas ${icon} me-2"></i>
                                ${perm.recurso_nome} - ${perm.permissao_nome}
                                <span class="badge bg-${perm.tipo === 'GRANT' ? 'success' : 'danger'} ms-2">
                                    ${perm.tipo}
                                </span>
                            </li>`;
                    });
                    
                    html += '</ul></div>';
                }
                
                // Delegations
                if (data.delegacoes && data.delegacoes.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6>Delegações Recebidas</h6>
                            <ul class="list-unstyled">`;
                    
                    data.delegacoes.forEach(del => {
                        html += `
                            <li class="mb-2">
                                <i class="fas fa-handshake text-info me-2"></i>
                                De: <strong>${del.delegante_nome}</strong>
                                ${del.role_nome ? ` - Role: ${del.role_nome}` : ''}
                                <br>
                                <small class="text-muted">
                                    Válida até: ${this.formatDate(del.data_fim)}
                                </small>
                            </li>`;
                    });
                    
                    html += '</ul></div>';
                }
                
                // Total permissions
                html += `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Total de permissões efetivas: <strong>${data.total_permissoes || 0}</strong>
                    </div>`;
                
                html += '</div>';
                
                Swal.fire({
                    title: '<i class="fas fa-user-shield me-2"></i>Permissões do Funcionário',
                    html: html,
                    width: 700,
                    confirmButtonText: 'Fechar'
                });
                
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Erro', error.message || 'Erro ao carregar permissões', 'error');
            } finally {
                this.hideLoading();
            }
        }

        async editFuncionarioPermissions(id) {
            const func = this.state.funcionarios.find(f => f.id == id);
            if (!func) return;
            
            const { value: action } = await Swal.fire({
                title: `Editar Permissões: ${func.nome}`,
                html: `
                    <div class="list-group">
                        <button type="button" class="list-group-item list-group-item-action" onclick="Swal.close('add-role')">
                            <i class="fas fa-user-plus me-2"></i>Adicionar Role
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" onclick="Swal.close('remove-role')">
                            <i class="fas fa-user-minus me-2"></i>Remover Role
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" onclick="Swal.close('specific-perm')">
                            <i class="fas fa-key me-2"></i>Adicionar Permissão Específica
                        </button>
                        <button type="button" class="list-group-item list-group-item-action" onclick="Swal.close('delegation')">
                            <i class="fas fa-handshake me-2"></i>Criar Delegação
                        </button>
                    </div>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Fechar'
            });
            
            switch (action) {
                case 'add-role':
                    document.getElementById('atribuirFuncionario').value = id;
                    $('#modalAtribuirRole').modal('show');
                    break;
                case 'remove-role':
                    await this.removeRoleFromFuncionario(id);
                    break;
                case 'specific-perm':
                    document.getElementById('permEspecFuncionario').value = id;
                    $('#modalPermissaoEspecifica').modal('show');
                    break;
                case 'delegation':
                    document.getElementById('delegacaoDelegado').value = id;
                    $('#modalDelegacao').modal('show');
                    break;
            }
        }

        async removeRoleFromFuncionario(funcionarioId) {
            // Implementation for removing role
            this.showToast('Remoção de role em desenvolvimento', 'info');
        }

        async cancelDelegacao(id) {
            const delegacao = this.state.delegacoes.find(d => d.id == id);
            if (!delegacao) return;
            
            const result = await Swal.fire({
                title: 'Confirmar Cancelamento',
                text: 'Tem certeza que deseja cancelar esta delegação?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, cancelar',
                cancelButtonText: 'Não'
            });
            
            if (result.isConfirmed) {
                this.showLoading('Cancelando delegação...');
                
                try {
                    const response = await this.apiCall('../api/permissoes/cancelar_delegacao.php', {
                        method: 'POST',
                        body: JSON.stringify({ id })
                    });
                    
                    if (response.success) {
                        this.showToast('Delegação cancelada com sucesso!', 'success');
                        this.cache.delete('delegacoes');
                        await this.loadAllData();
                    } else {
                        throw { message: response.error || 'Erro ao cancelar delegação' };
                    }
                } catch (error) {
                    Swal.fire('Erro', error.message || 'Erro ao cancelar delegação', 'error');
                } finally {
                    this.hideLoading();
                }
            }
        }

        async editRecurso(id) {
            // Implementation for editing resource
            this.showToast('Edição de recurso em desenvolvimento', 'info');
        }

        async editPermissionMatrix(roleId, recursoId) {
            // Implementation for editing permission matrix
            this.showToast('Edição de matriz em desenvolvimento', 'info');
        }

        // ===========================
        // FILTER AND SEARCH
        // ===========================
        
        performSearch(searchTerm) {
            const table = this.getDataTable('funcionariosTable');
            if (table) {
                table.search(searchTerm).draw();
            }
        }

        filterLogs() {
            this.filtrarLogs();
        }

        async filtrarLogs() {
            const inicio = document.getElementById('logDateStart').value;
            const fim = document.getElementById('logDateEnd').value;
            const resultado = document.getElementById('logResultado').value;
            
            let url = '../api/permissoes/listar_logs.php?limit=50';
            
            if (inicio) url += `&data_inicio=${inicio}`;
            if (fim) url += `&data_fim=${fim}`;
            if (resultado) url += `&resultado=${resultado}`;
            
            this.showLoading('Filtrando logs...');
            
            try {
                // Clear cache for logs when filtering
                this.cache.delete('filtered_logs');
                
                const logs = await this.fetchWithCache(url, 'filtered_logs', 30000); // 30 second cache
                
                this.updateLogsTimeline(logs);
                
                if (logs.length === 0) {
                    this.showToast('Nenhum log encontrado com os filtros aplicados', 'info');
                }
            } catch (error) {
                console.error('Error filtering logs:', error);
                Swal.fire('Erro', 'Erro ao filtrar logs', 'error');
            } finally {
                this.hideLoading();
            }
        }

        async exportarLogs() {
            const inicio = document.getElementById('logDateStart').value;
            const fim = document.getElementById('logDateEnd').value;
            const resultado = document.getElementById('logResultado').value;
            
            let url = '../api/permissoes/exportar_logs.php?formato=csv';
            
            if (inicio) url += `&data_inicio=${inicio}`;
            if (fim) url += `&data_fim=${fim}`;
            if (resultado) url += `&resultado=${resultado}`;
            
            // Open in new window for download
            window.open(url, '_blank');
            
            this.showToast('Exportação iniciada', 'success');
        }

        async exportMatrizPermissoes() {
            window.open('../api/permissoes/exportar_matriz.php?formato=csv', '_blank');
            this.showToast('Exportação da matriz iniciada', 'success');
        }

        // ===========================
        // UTILITY HELPERS
        // ===========================
        
        getRoleBadge(codigo) {
            const badges = {
                'SUPER_ADMIN': 'badge-super-admin',
                'PRESIDENTE': 'badge-presidente',
                'DIRETOR': 'badge-diretor',
                'SUBDIRETOR': 'badge-diretor',
                'SUPERVISOR': 'badge-funcionario',
                'FUNCIONARIO_SENIOR': 'badge-funcionario',
                'FUNCIONARIO': 'badge-funcionario',
                'VISUALIZADOR': 'badge-funcionario'
            };
            
            const badgeClass = badges[codigo] || 'badge-funcionario';
            return `<span class="badge badge-role ${badgeClass}">${codigo}</span>`;
        }

        getDataTable(tableId) {
            if (!this.dataTables[tableId]) {
                const element = document.getElementById(tableId);
                if (element && $.fn.DataTable.isDataTable(`#${tableId}`)) {
                    this.dataTables[tableId] = $(`#${tableId}`).DataTable();
                }
            }
            return this.dataTables[tableId];
        }

        populateSelects() {
            // Populate Funcionários selects
            const funcionarioSelects = ['atribuirFuncionario', 'delegacaoDelegante', 
                                        'delegacaoDelegado', 'permEspecFuncionario'];
            funcionarioSelects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    const currentValue = select.value;
                    select.innerHTML = '<option value="">Selecione...</option>';
                    this.state.funcionarios.forEach(func => {
                        select.innerHTML += `<option value="${func.id}">${func.nome}</option>`;
                    });
                    select.value = currentValue; // Preserve selected value
                }
            });
            
            // Populate Roles selects
            const roleSelects = ['atribuirRole', 'delegacaoRole', 'filterRole'];
            roleSelects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    const currentValue = select.value;
                    const emptyText = selectId === 'filterRole' ? 'Todas as Roles' : 'Selecione...';
                    select.innerHTML = `<option value="">${emptyText}</option>`;
                    this.state.roles.forEach(role => {
                        select.innerHTML += `<option value="${role.id}">${role.nome}</option>`;
                    });
                    select.value = currentValue;
                }
            });
            
            // Populate Departamentos selects
            const departamentoSelects = ['atribuirDepartamento', 'filterDepartamento'];
            departamentoSelects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    const currentValue = select.value;
                    const emptyText = selectId === 'atribuirDepartamento' ? 
                                      'Todos os departamentos' : 
                                      'Todos os Departamentos';
                    select.innerHTML = `<option value="">${emptyText}</option>`;
                    this.state.departamentos.forEach(dept => {
                        select.innerHTML += `<option value="${dept.id}">${dept.nome}</option>`;
                    });
                    select.value = currentValue;
                }
            });
            
            // Populate Recursos selects
            const recursoSelects = ['delegacaoRecurso', 'permEspecRecurso'];
            recursoSelects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    const currentValue = select.value;
                    select.innerHTML = '<option value="">Selecione...</option>';
                    this.state.recursos.forEach(recurso => {
                        select.innerHTML += `<option value="${recurso.id}">${recurso.categoria} - ${recurso.nome}</option>`;
                    });
                    select.value = currentValue;
                }
            });
            
            // Populate Permissões select
            const permissaoSelect = document.getElementById('permEspecPermissao');
            if (permissaoSelect) {
                const currentValue = permissaoSelect.value;
                permissaoSelect.innerHTML = '<option value="">Selecione...</option>';
                this.state.permissoes.forEach(perm => {
                    permissaoSelect.innerHTML += `<option value="${perm.id}">${perm.nome}</option>`;
                });
                permissaoSelect.value = currentValue;
            }
        }

        // ===========================
        // INITIALIZATION
        // ===========================
        
        init() {
            // Initialize DataTables
            if (!$.fn.DataTable.isDataTable('#rolesTable')) {
                this.dataTables['rolesTable'] = $('#rolesTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                    },
                    pageLength: 10,
                    order: [[1, 'desc']],
                    responsive: true
                });
            }
            
            if (!$.fn.DataTable.isDataTable('#funcionariosTable')) {
                this.dataTables['funcionariosTable'] = $('#funcionariosTable').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                    },
                    pageLength: 10,
                    responsive: true
                });
            }
            
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecione...',
                allowClear: true
            });
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Load initial data
            this.loadAllData();
        }

        setupEventListeners() {
            // Search with debounce
            const searchInput = document.getElementById('searchFuncionario');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.debouncedSearch(e.target.value);
                });
            }
            
            // Filter changes
            document.getElementById('filterDepartamento')?.addEventListener('change', () => {
                this.filterFuncionarios();
            });
            
            document.getElementById('filterRole')?.addEventListener('change', () => {
                this.filterFuncionarios();
            });
            
            // Tab changes - reload specific data
            document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', (e) => {
                    const target = e.target.getAttribute('href');
                    if (target === '#tab-logs') {
                        this.loadRecentLogs();
                    }
                });
            });
        }

        filterFuncionarios() {
            const departamento = document.getElementById('filterDepartamento').value;
            const role = document.getElementById('filterRole').value;
            const search = document.getElementById('searchFuncionario').value;
            
            const table = this.getDataTable('funcionariosTable');
            if (!table) return;
            
            // Apply filters
            let filtered = [...this.state.funcionarios];
            
            if (departamento) {
                filtered = filtered.filter(f => f.departamento_id == departamento);
            }
            
            if (role) {
                filtered = filtered.filter(f => 
                    f.roles && f.roles.some(r => r.id == role)
                );
            }
            
            if (search) {
                const searchLower = search.toLowerCase();
                filtered = filtered.filter(f =>
                    f.nome.toLowerCase().includes(searchLower) ||
                    (f.email && f.email.toLowerCase().includes(searchLower))
                );
            }
            
            // Update table
            this.updateFuncionariosTableWithData(filtered);
        }

        updateFuncionariosTableWithData(data) {
            const table = this.getDataTable('funcionariosTable');
            if (!table) return;
            
            table.clear();
            
            data.forEach(func => {
                const roles = func.roles ? func.roles.map(r =>
                    `<span class="badge bg-primary me-1">${r.nome}</span>`
                ).join(' ') : '-';
                
                const permEspeciais = func.permissoes_especiais || 0;
                const delegacoes = func.delegacoes_ativas || 0;
                
                const actions = `
                    <button class="btn btn-sm btn-info btn-action" 
                            onclick="permissionManager.viewFuncionarioPermissions(${func.id})" 
                            title="Visualizar Permissões">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${this.permissions.canEdit ? `
                    <button class="btn btn-sm btn-warning btn-action" 
                            onclick="permissionManager.editFuncionarioPermissions(${func.id})" 
                            title="Editar Permissões">
                        <i class="fas fa-edit"></i>
                    </button>` : ''}
                `;
                
                table.row.add([
                    func.nome,
                    func.departamento || '-',
                    roles,
                    permEspeciais > 0 ? `<span class="badge bg-warning">${permEspeciais}</span>` : '-',
                    delegacoes > 0 ? `<span class="badge bg-info">${delegacoes}</span>` : '-',
                    actions
                ]);
            });
            
            table.draw();
        }
    }

    // ===========================
    // INITIALIZE APPLICATION
    // ===========================
    
    // Create global instance
    const permissionManager = new PermissionManager();

    // Initialize when DOM is ready
    $(document).ready(function() {
        permissionManager.init();
    });

    // Expose to global scope for onclick handlers
    window.permissionManager = permissionManager;
    </script>
</body>
</html>