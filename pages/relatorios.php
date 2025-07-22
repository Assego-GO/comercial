<?php
/**
 * Página de Relatórios
 * pages/relatorios.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Relatorios.php';

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
$page_title = 'Relatórios - ASSEGO';

// Inicializa classe de relatórios
$relatorios = new Relatorios();

// Busca estatísticas
try {
    $estatisticas = $relatorios->getEstatisticas(30);
    $modelosDisponiveis = $relatorios->listarModelos();
    $historicoRecente = $relatorios->getHistorico(['limite' => 5]);
} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    $estatisticas = $modelosDisponiveis = $historicoRecente = [];
}
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #0056D2;
            --primary-dark: #003A8C;
            --primary-light: #E8F1FF;
            --secondary: #FFB800;
            --secondary-dark: #CC9200;
            --success: #00C853;
            --danger: #FF3B30;
            --warning: #FF9500;
            --info: #00B8D4;
            --dark: #1C1C1E;
            --gray-100: #F7F7F7;
            --gray-200: #E5E5E7;
            --gray-300: #D1D1D6;
            --gray-400: #C7C7CC;
            --gray-500: #8E8E93;
            --gray-600: #636366;
            --gray-700: #48484A;
            --gray-800: #3A3A3C;
            --gray-900: #2C2C2E;
            --white: #FFFFFF;

            --header-height: 70px;

            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.24);
            --shadow-lg: 0 10px 20px rgba(0, 0, 0, 0.12), 0 4px 8px rgba(0, 0, 0, 0.24);
            --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.12), 0 8px 16px rgba(0, 0, 0, 0.24);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--gray-100);
            color: var(--dark);
            overflow-x: hidden;
        }

        /* Reutiliza estilos do dashboard */
        .main-wrapper {
            min-height: 100vh;
            background: var(--gray-100);
        }

        .main-header {
            background: var(--white);
            height: var(--header-height);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-text {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .system-subtitle {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin: 0;
            font-weight: 500;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: var(--gray-100);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            color: var(--gray-600);
        }

        .header-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: var(--gray-100);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .user-menu:hover {
            background: var(--gray-200);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: var(--white);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--dark);
            margin: 0;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin: 0;
        }

        .dropdown-menu-custom {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            padding: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .dropdown-menu-custom.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item-custom {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dropdown-item-custom:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .dropdown-divider-custom {
            height: 1px;
            background: var(--gray-200);
            margin: 0.5rem 0;
        }

        /* Navigation Tabs */
        .nav-tabs-container {
            background: var(--white);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: var(--header-height);
            z-index: 99;
            border-bottom: 1px solid var(--gray-200);
        }

        .nav-tabs-modern {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 2rem;
            margin: 0;
            list-style: none;
            gap: 1rem;
        }

        .nav-tab-item {
            margin: 0;
        }

        .nav-tab-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 2rem;
            color: var(--gray-600);
            text-decoration: none;
            border: none;
            background: var(--gray-100);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 12px;
            min-width: 120px;
        }

        .nav-tab-link:hover {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .nav-tab-link.active {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.25);
        }

        .nav-tab-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            margin-bottom: 0.375rem;
            transition: all 0.3s ease;
        }

        .nav-tab-link.active .nav-tab-icon {
            color: var(--white);
        }

        .nav-tab-text {
            font-weight: 600;
            font-size: 0.8125rem;
            transition: all 0.3s ease;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-100);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }

        .stat-icon.primary {
            background: var(--primary-light);
            color: var(--primary);
        }

        .stat-icon.success {
            background: rgba(0, 200, 83, 0.1);
            color: var(--success);
        }

        .stat-icon.warning {
            background: rgba(255, 149, 0, 0.1);
            color: var(--warning);
        }

        .stat-icon.info {
            background: rgba(0, 184, 212, 0.1);
            color: var(--info);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.375rem;
        }

        .stat-label {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-bottom: 0.375rem;
        }

        /* Section Cards */
        .section-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 1rem;
        }

        .section-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Report Grid */
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .report-card {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            border: 2px solid transparent;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            border-color: var(--primary-light);
        }

        .report-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .report-icon.blue {
            background: rgba(0, 86, 210, 0.1);
            color: var(--primary);
        }

        .report-icon.green {
            background: rgba(0, 200, 83, 0.1);
            color: var(--success);
        }

        .report-icon.orange {
            background: rgba(255, 149, 0, 0.1);
            color: var(--warning);
        }

        .report-icon.purple {
            background: rgba(124, 58, 237, 0.1);
            color: #7c3aed;
        }

        .report-icon.red {
            background: rgba(255, 59, 48, 0.1);
            color: var(--danger);
        }

        .report-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .report-description {
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .report-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .report-badge {
            padding: 0.25rem 0.625rem;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.625rem;
            text-transform: uppercase;
        }

        /* Recent Activity */
        .activity-list {
            padding: 0;
        }

        .activity-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background: var(--gray-100);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-100);
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.125rem;
        }

        .activity-description {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--gray-400);
        }

        /* Modal Styles */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        .modal-custom.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-content-custom {
            background: var(--white);
            border-radius: 24px;
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header-custom {
            padding: 1.5rem 2rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title-custom {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .modal-close-custom {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--gray-600);
        }

        .modal-close-custom:hover {
            background: var(--gray-200);
            color: var(--dark);
        }

        .modal-body-custom {
            padding: 2rem;
            overflow-y: auto;
        }

        /* Form Styles */
        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-100);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control-custom {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
        }

        .form-select-custom {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23636366' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        /* Checkbox Group */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 12px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        .checkbox-item:hover {
            background: var(--gray-200);
        }

        .checkbox-custom {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 0.8125rem;
            color: var(--gray-700);
            cursor: pointer;
            user-select: none;
        }

        /* Category Header */
        .category-header {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        /* Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 2px 8px rgba(0, 86, 210, 0.25);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .btn-icon:hover {
            background: var(--gray-100);
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            margin-top: 1rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Results Section */
        .results-section {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            margin-top: 2rem;
            overflow: hidden;
        }

        .results-header {
            padding: 1.5rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .results-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .results-table thead th {
            background: var(--gray-100);
            padding: 0.875rem 1.25rem;
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        .results-table tbody td {
            padding: 0.875rem 1.25rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
        }

        .results-table tbody tr:hover {
            background: var(--gray-100);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-tabs-modern {
                overflow-x: auto;
                justify-content: flex-start;
                padding: 0 1rem;
            }

            .report-grid {
                grid-template-columns: 1fr;
            }

            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .modal-content-custom {
                max-width: 100%;
                margin: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processando relatório...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header -->
        <header class="main-header">
            <div class="header-left">
                <div class="logo-section">
                    <div style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                        A
                    </div>
                    <div>
                        <h1 class="logo-text">ASSEGO</h1>
                        <p class="system-subtitle">Sistema de Gestão</p>
                    </div>
                </div>
            </div>

            <div class="header-right">
                <button class="header-btn">
                    <i class="fas fa-search"></i>
                </button>
                <button class="header-btn">
                    <i class="fas fa-bell"></i>
                </button>
                <div class="user-menu" id="userMenu">
                    <div class="user-info">
                        <p class="user-name"><?php echo htmlspecialchars($usuarioLogado['nome']); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'Funcionário'); ?></p>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($usuarioLogado['nome'], 0, 1)); ?>
                    </div>
                    <i class="fas fa-chevron-down ms-2" style="font-size: 0.75rem; color: var(--gray-500);"></i>

                    <!-- Dropdown Menu -->
                    <div class="dropdown-menu-custom" id="userDropdown">
                        <a href="perfil.php" class="dropdown-item-custom">
                            <i class="fas fa-user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        <?php if ($auth->isDiretor()): ?>
                        <a href="configuracoes.php" class="dropdown-item-custom">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                        <?php endif; ?>
                        <div class="dropdown-divider-custom"></div>
                        <a href="../php/login/logout.php" class="dropdown-item-custom">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="nav-tabs-container">
            <ul class="nav-tabs-modern">
                <li class="nav-tab-item">
                    <a href="dashboard.php" class="nav-tab-link">
                        <div class="nav-tab-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="nav-tab-text">Associados</span>
                    </a>
                </li>
                <?php if ($auth->isDiretor()): ?>
                <li class="nav-tab-item">
                    <a href="funcionarios.php" class="nav-tab-link">
                        <div class="nav-tab-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <span class="nav-tab-text">Funcionários</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-tab-item">
                    <a href="relatorios.php" class="nav-tab-link active">
                        <div class="nav-tab-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span class="nav-tab-text">Relatórios</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header mb-4" data-aos="fade-right">
                <h1 class="page-title">Central de Relatórios</h1>
                <p class="page-subtitle">Gere relatórios personalizados e análises detalhadas</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $estatisticas['totais']['total'] ?? 0; ?></div>
                            <div class="stat-label">Relatórios Gerados</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo count($modelosDisponiveis); ?></div>
                            <div class="stat-label">Modelos Disponíveis</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $estatisticas['totais']['total_usuarios'] ?? 0; ?></div>
                            <div class="stat-label">Usuários Ativos</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">
                                <?php 
                                $mediaExecucoes = 0;
                                if (!empty($estatisticas['mais_utilizados'])) {
                                    $mediaExecucoes = array_sum(array_column($estatisticas['mais_utilizados'], 'total_execucoes')) / count($estatisticas['mais_utilizados']);
                                }
                                echo number_format($mediaExecucoes, 1);
                                ?>
                            </div>
                            <div class="stat-label">Média de Execuções</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Reports Section -->
            <div class="section-card" data-aos="fade-up" data-aos-delay="100">
                <div class="section-header">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        Relatórios Disponíveis
                    </h3>
                    <div class="section-actions">
                        <button class="btn-modern btn-primary" onclick="abrirModalNovoRelatorio()">
                            <i class="fas fa-plus"></i>
                            Criar Novo Relatório
                        </button>
                    </div>
                </div>

                <div class="report-grid">
                    <!-- Relatório de Associados -->
                    <div class="report-card" onclick="abrirModalConfiguracao('associados')">
                        <div class="report-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="report-title">Relatório de Associados</h4>
                        <p class="report-description">
                            Gere relatórios completos dos associados com filtros por situação, 
                            corporação, patente e período.
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-database"></i> Dados completos</span>
                            <span class="report-badge">POPULAR</span>
                        </div>
                    </div>

                    <!-- Relatório Financeiro -->
                    <div class="report-card" onclick="abrirModalConfiguracao('financeiro')">
                        <div class="report-icon green">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4 class="report-title">Relatório Financeiro</h4>
                        <p class="report-description">
                            Análise financeira dos associados, incluindo situação de pagamento 
                            e tipos de contribuição.
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-chart-line"></i> Análise detalhada</span>
                        </div>
                    </div>

                    <!-- Relatório Militar -->
                    <div class="report-card" onclick="abrirModalConfiguracao('militar')">
                        <div class="report-icon orange">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="report-title">Relatório Militar</h4>
                        <p class="report-description">
                            Informações militares dos associados, distribuição por patente, 
                            corporação e unidade.
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-sitemap"></i> Hierarquia</span>
                        </div>
                    </div>

                    <!-- Relatório de Serviços -->
                    <div class="report-card" onclick="abrirModalConfiguracao('servicos')">
                        <div class="report-icon purple">
                            <i class="fas fa-concierge-bell"></i>
                        </div>
                        <h4 class="report-title">Relatório de Serviços</h4>
                        <p class="report-description">
                            Adesão aos serviços oferecidos, valores aplicados e análise 
                            de utilização.
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-list-check"></i> Serviços ativos</span>
                        </div>
                    </div>

                    <!-- Relatório de Documentos -->
                    <div class="report-card" onclick="abrirModalConfiguracao('documentos')">
                        <div class="report-icon red">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h4 class="report-title">Relatório de Documentos</h4>
                        <p class="report-description">
                            Status dos documentos dos associados, verificações pendentes 
                            e lotes processados.
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-file-check"></i> Verificação</span>
                        </div>
                    </div>

                    <!-- Modelos Salvos -->
                    <?php foreach ($modelosDisponiveis as $modelo): ?>
                    <div class="report-card" onclick="executarModelo(<?php echo $modelo['id']; ?>)">
                        <div class="report-icon info">
                            <i class="fas fa-save"></i>
                        </div>
                        <h4 class="report-title"><?php echo htmlspecialchars($modelo['nome']); ?></h4>
                        <p class="report-description">
                            <?php echo htmlspecialchars($modelo['descricao'] ?? 'Modelo personalizado'); ?>
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($modelo['criado_por_nome'] ?? 'Sistema'); ?></span>
                            <span><?php echo $modelo['total_execucoes'] ?? 0; ?> execuções</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="section-card" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        Atividade Recente
                    </h3>
                </div>

                <div class="activity-list">
                    <?php if (empty($historicoRecente)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Nenhuma atividade recente</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($historicoRecente as $item): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($item['nome_relatorio']); ?></div>
                                <div class="activity-description">
                                    Gerado por <?php echo htmlspecialchars($item['gerado_por_nome'] ?? 'Sistema'); ?> • 
                                    <?php echo $item['contagem_registros'] ?? 0; ?> registros
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php 
                                $data = new DateTime($item['data_geracao']);
                                echo $data->format('d/m/Y H:i');
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Configuração de Relatório -->
    <div class="modal-custom" id="modalConfiguracao">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom" id="modalTitle">Configurar Relatório</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalConfiguracao')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formRelatorio">
                    <input type="hidden" id="tipoRelatorio" name="tipo">
                    
                    <!-- Seleção de Campos -->
                    <div class="form-section">
                        <h3 class="form-section-title">Campos do Relatório</h3>
                        <p class="form-text mb-3">Selecione os campos que deseja incluir no relatório</p>
                        <div class="checkbox-group" id="camposContainer">
                            <!-- Campos serão carregados dinamicamente -->
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="form-section">
                        <h3 class="form-section-title">Filtros</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Data Inicial</label>
                                    <input type="date" class="form-control-custom" id="dataInicio" name="data_inicio">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Data Final</label>
                                    <input type="date" class="form-control-custom" id="dataFim" name="data_fim">
                                </div>
                            </div>
                        </div>
                        
                        <div id="filtrosEspecificos">
                            <!-- Filtros específicos por tipo serão carregados aqui -->
                        </div>
                    </div>

                    <!-- Opções -->
                    <div class="form-section">
                        <h3 class="form-section-title">Opções do Relatório</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Ordenar por</label>
                                    <select class="form-control-custom form-select-custom" id="ordenacao" name="ordenacao">
                                        <option value="">Padrão</option>
                                        <option value="nome ASC">Nome (A-Z)</option>
                                        <option value="nome DESC">Nome (Z-A)</option>
                                        <option value="id DESC">Mais recentes</option>
                                        <option value="id ASC">Mais antigos</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Formato de Exportação</label>
                                    <select class="form-control-custom form-select-custom" id="formato" name="formato">
                                        <option value="html">Visualizar (HTML)</option>
                                        <option value="excel">Excel</option>
                                        <option value="csv">CSV</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-item">
                                <input type="checkbox" class="checkbox-custom" id="salvarModelo" name="salvar_modelo">
                                <span class="checkbox-label">Salvar como modelo para uso futuro</span>
                            </label>
                        </div>

                        <div class="form-group" id="nomeModeloGroup" style="display: none;">
                            <label class="form-label">Nome do Modelo</label>
                            <input type="text" class="form-control-custom" id="nomeModelo" name="nome_modelo" placeholder="Digite um nome para o modelo">
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalConfiguracao')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-play"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Novo Relatório -->
    <div class="modal-custom" id="modalNovoRelatorio">
        <div class="modal-content-custom" style="max-width: 600px;">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Criar Novo Relatório</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalNovoRelatorio')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formNovoRelatorio">
                    <div class="form-group">
                        <label class="form-label">Nome do Relatório</label>
                        <input type="text" class="form-control-custom" id="novoNome" name="nome" required placeholder="Ex: Relatório Mensal de Associados">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control-custom" id="novoDescricao" name="descricao" rows="3" placeholder="Descreva o objetivo deste relatório"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipo de Relatório</label>
                        <select class="form-control-custom form-select-custom" id="novoTipo" name="tipo" required>
                            <option value="">Selecione o tipo</option>
                            <option value="associados">Associados</option>
                            <option value="financeiro">Financeiro</option>
                            <option value="militar">Militar</option>
                            <option value="servicos">Serviços</option>
                            <option value="documentos">Documentos</option>
                        </select>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalNovoRelatorio')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-arrow-right"></i>
                            Continuar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais
        let tipoRelatorioAtual = '';
        let camposDisponiveis = {};

        // User Dropdown Menu
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');

            if (userMenu && userDropdown) {
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                document.addEventListener('click', function() {
                    userDropdown.classList.remove('show');
                });
            }

            // Event listeners para formulários
            document.getElementById('formRelatorio').addEventListener('submit', gerarRelatorio);
            document.getElementById('formNovoRelatorio').addEventListener('submit', criarNovoRelatorio);
            
            // Checkbox salvar modelo
            document.getElementById('salvarModelo').addEventListener('change', function() {
                document.getElementById('nomeModeloGroup').style.display = this.checked ? 'block' : 'none';
            });
        });

        // Loading functions
        function showLoading(texto = 'Processando...') {
            const overlay = document.getElementById('loadingOverlay');
            const loadingText = overlay.querySelector('.loading-text');
            loadingText.textContent = texto;
            overlay.classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Abre modal de configuração
        function abrirModalConfiguracao(tipo) {
            tipoRelatorioAtual = tipo;
            document.getElementById('tipoRelatorio').value = tipo;
            
            // Atualiza título
            const titulos = {
                'associados': 'Configurar Relatório de Associados',
                'financeiro': 'Configurar Relatório Financeiro',
                'militar': 'Configurar Relatório Militar',
                'servicos': 'Configurar Relatório de Serviços',
                'documentos': 'Configurar Relatório de Documentos'
            };
            document.getElementById('modalTitle').textContent = titulos[tipo] || 'Configurar Relatório';
            
            // Carrega campos disponíveis
            carregarCampos(tipo);
            
            // Carrega filtros específicos
            carregarFiltrosEspecificos(tipo);
            
            // Abre modal
            document.getElementById('modalConfiguracao').classList.add('show');
        }

        // Carrega campos disponíveis
        function carregarCampos(tipo) {
            showLoading('Carregando campos...');
            
            $.ajax({
                url: '../api/relatorios_campos.php',
                method: 'GET',
                data: { tipo: tipo },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        camposDisponiveis = response.campos;
                        renderizarCampos(response.campos);
                    } else {
                        alert('Erro ao carregar campos: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro ao carregar campos:', error);
                    
                    // Usa campos padrão como fallback
                    const camposPadrao = getCamposPadrao(tipo);
                    renderizarCampos(camposPadrao);
                }
            });
        }

        // Campos padrão por tipo (fallback)
        function getCamposPadrao(tipo) {
            const campos = {
                'associados': {
                    'Dados Pessoais': [
                        { nome_campo: 'nome', nome_exibicao: 'Nome Completo' },
                        { nome_campo: 'cpf', nome_exibicao: 'CPF' },
                        { nome_campo: 'rg', nome_exibicao: 'RG' },
                        { nome_campo: 'nasc', nome_exibicao: 'Data de Nascimento' },
                        { nome_campo: 'sexo', nome_exibicao: 'Sexo' },
                        { nome_campo: 'email', nome_exibicao: 'E-mail' },
                        { nome_campo: 'telefone', nome_exibicao: 'Telefone' }
                    ],
                    'Informações Militares': [
                        { nome_campo: 'corporacao', nome_exibicao: 'Corporação' },
                        { nome_campo: 'patente', nome_exibicao: 'Patente' },
                        { nome_campo: 'categoria', nome_exibicao: 'Categoria' },
                        { nome_campo: 'lotacao', nome_exibicao: 'Lotação' },
                        { nome_campo: 'unidade', nome_exibicao: 'Unidade' }
                    ],
                    'Situação': [
                        { nome_campo: 'situacao', nome_exibicao: 'Situação' },
                        { nome_campo: 'dataFiliacao', nome_exibicao: 'Data de Filiação' },
                        { nome_campo: 'dataDesfiliacao', nome_exibicao: 'Data de Desfiliação' }
                    ]
                },
                'financeiro': {
                    'Dados Financeiros': [
                        { nome_campo: 'tipoAssociado', nome_exibicao: 'Tipo de Associado' },
                        { nome_campo: 'situacaoFinanceira', nome_exibicao: 'Situação Financeira' },
                        { nome_campo: 'vinculoServidor', nome_exibicao: 'Vínculo Servidor' },
                        { nome_campo: 'localDebito', nome_exibicao: 'Local de Débito' }
                    ],
                    'Dados Bancários': [
                        { nome_campo: 'agencia', nome_exibicao: 'Agência' },
                        { nome_campo: 'operacao', nome_exibicao: 'Operação' },
                        { nome_campo: 'contaCorrente', nome_exibicao: 'Conta Corrente' }
                    ]
                },
                'militar': {
                    'Informações Militares': [
                        { nome_campo: 'corporacao', nome_exibicao: 'Corporação' },
                        { nome_campo: 'patente', nome_exibicao: 'Patente' },
                        { nome_campo: 'categoria', nome_exibicao: 'Categoria' },
                        { nome_campo: 'lotacao', nome_exibicao: 'Lotação' },
                        { nome_campo: 'unidade', nome_exibicao: 'Unidade' }
                    ]
                },
                'servicos': {
                    'Serviços': [
                        { nome_campo: 'servico_nome', nome_exibicao: 'Nome do Serviço' },
                        { nome_campo: 'valor_aplicado', nome_exibicao: 'Valor Aplicado' },
                        { nome_campo: 'percentual_aplicado', nome_exibicao: 'Percentual' },
                        { nome_campo: 'data_adesao', nome_exibicao: 'Data de Adesão' },
                        { nome_campo: 'ativo', nome_exibicao: 'Status' }
                    ]
                },
                'documentos': {
                    'Documentos': [
                        { nome_campo: 'tipo_documento', nome_exibicao: 'Tipo de Documento' },
                        { nome_campo: 'nome_arquivo', nome_exibicao: 'Nome do Arquivo' },
                        { nome_campo: 'data_upload', nome_exibicao: 'Data de Upload' },
                        { nome_campo: 'verificado', nome_exibicao: 'Verificado' }
                    ]
                }
            };
            
            return campos[tipo] || {};
        }

        // Renderiza campos no checkbox group
        function renderizarCampos(campos) {
            const container = document.getElementById('camposContainer');
            container.innerHTML = '';
            
            for (const categoria in campos) {
                // Adiciona header da categoria
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'w-100';
                categoryDiv.innerHTML = `<div class="category-header">${categoria}</div>`;
                container.appendChild(categoryDiv);
                
                // Adiciona campos da categoria
                campos[categoria].forEach(campo => {
                    const checkboxItem = document.createElement('div');
                    checkboxItem.className = 'checkbox-item';
                    checkboxItem.innerHTML = `
                        <input type="checkbox" 
                               class="checkbox-custom" 
                               id="campo_${campo.nome_campo}" 
                               name="campos[]" 
                               value="${campo.nome_campo}"
                               checked>
                        <label class="checkbox-label" for="campo_${campo.nome_campo}">
                            ${campo.nome_exibicao}
                        </label>
                    `;
                    container.appendChild(checkboxItem);
                });
            }
        }

        // Carrega filtros específicos por tipo
        function carregarFiltrosEspecificos(tipo) {
            const container = document.getElementById('filtrosEspecificos');
            let html = '';
            
            switch(tipo) {
                case 'associados':
                    html = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Situação</label>
                                    <select class="form-control-custom form-select-custom" name="situacao">
                                        <option value="">Todos</option>
                                        <option value="Filiado">Filiado</option>
                                        <option value="Desfiliado">Desfiliado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Corporação</label>
                                    <select class="form-control-custom form-select-custom" name="corporacao">
                                        <option value="">Todas</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Patente</label>
                                    <select class="form-control-custom form-select-custom" name="patente">
                                        <option value="">Todas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tipo de Associado</label>
                                    <select class="form-control-custom form-select-custom" name="tipo_associado">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'financeiro':
                    html = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Situação Financeira</label>
                                    <select class="form-control-custom form-select-custom" name="situacaoFinanceira">
                                        <option value="">Todas</option>
                                        <option value="Regular">Regular</option>
                                        <option value="Inadimplente">Inadimplente</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tipo de Associado</label>
                                    <select class="form-control-custom form-select-custom" name="tipo_associado">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'servicos':
                    html = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Serviço</label>
                                    <select class="form-control-custom form-select-custom" name="servico_id">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select class="form-control-custom form-select-custom" name="ativo">
                                        <option value="">Todos</option>
                                        <option value="1">Ativo</option>
                                        <option value="0">Inativo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'documentos':
                    html = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Tipo de Documento</label>
                                    <select class="form-control-custom form-select-custom" name="tipo_documento">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Status de Verificação</label>
                                    <select class="form-control-custom form-select-custom" name="verificado">
                                        <option value="">Todos</option>
                                        <option value="1">Verificado</option>
                                        <option value="0">Não Verificado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
            }
            
            container.innerHTML = html;
            
            // Carrega opções dos selects dinamicamente
            carregarOpcoesSelects(tipo);
        }

        // Carrega opções dos selects
        function carregarOpcoesSelects(tipo) {
            // Aqui você pode fazer chamadas AJAX para carregar as opções dinamicamente
            // Por exemplo, carregar corporações, patentes, serviços, etc.
        }

        // Abre modal de novo relatório
        function abrirModalNovoRelatorio() {
            document.getElementById('modalNovoRelatorio').classList.add('show');
        }

        // Fecha modal
        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            
            // Limpa formulários
            if (modalId === 'modalConfiguracao') {
                document.getElementById('formRelatorio').reset();
            } else if (modalId === 'modalNovoRelatorio') {
                document.getElementById('formNovoRelatorio').reset();
            }
        }

        // Cria novo relatório
        function criarNovoRelatorio(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const tipo = formData.get('tipo');
            
            // Fecha modal atual e abre modal de configuração
            fecharModal('modalNovoRelatorio');
            
            // Aguarda um pouco para a animação
            setTimeout(() => {
                abrirModalConfiguracao(tipo);
            }, 300);
        }

        // Gera relatório
        function gerarRelatorio(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {};
            
            // Converte FormData para objeto
            for (let [key, value] of formData.entries()) {
                if (key === 'campos[]') {
                    if (!dados.campos) dados.campos = [];
                    dados.campos.push(value);
                } else {
                    dados[key] = value;
                }
            }
            
            // Validações
            if (!dados.campos || dados.campos.length === 0) {
                alert('Selecione ao menos um campo para o relatório');
                return;
            }
            
            // Se marcou para salvar modelo, precisa de nome
            if (dados.salvar_modelo && !dados.nome_modelo) {
                alert('Digite um nome para o modelo');
                document.getElementById('nomeModelo').focus();
                return;
            }
            
            showLoading('Gerando relatório...');
            
            // Se deve salvar como modelo
            if (dados.salvar_modelo) {
                salvarModelo(dados).then(modeloId => {
                    executarRelatorio(dados);
                }).catch(error => {
                    hideLoading();
                    alert('Erro ao salvar modelo: ' + error);
                });
            } else {
                executarRelatorio(dados);
            }
        }

        // Salva modelo de relatório
        function salvarModelo(dados) {
            return new Promise((resolve, reject) => {
                const modeloData = {
                    nome: dados.nome_modelo,
                    tipo: dados.tipo,
                    campos: dados.campos,
                    filtros: {
                        data_inicio: dados.data_inicio,
                        data_fim: dados.data_fim,
                        situacao: dados.situacao,
                        corporacao: dados.corporacao,
                        patente: dados.patente,
                        tipo_associado: dados.tipo_associado,
                        servico_id: dados.servico_id,
                        ativo: dados.ativo,
                        tipo_documento: dados.tipo_documento,
                        verificado: dados.verificado
                    },
                    ordenacao: dados.ordenacao
                };
                
                $.ajax({
                    url: '../api/relatorios_salvar_modelo.php',
                    method: 'POST',
                    data: JSON.stringify(modeloData),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            resolve(response.modelo_id);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        reject('Erro ao salvar modelo');
                    }
                });
            });
        }

        // Executa relatório
        function executarRelatorio(dados) {
            // Cria formulário temporário para POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../api/relatorios_executar.php';
            form.target = '_blank';
            
            // Adiciona campos
            for (const key in dados) {
                if (Array.isArray(dados[key])) {
                    dados[key].forEach(value => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key + '[]';
                        input.value = value;
                        form.appendChild(input);
                    });
                } else if (dados[key]) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = dados[key];
                    form.appendChild(input);
                }
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            hideLoading();
            fecharModal('modalConfiguracao');
        }

        // Executa modelo salvo
        function executarModelo(modeloId) {
            showLoading('Carregando modelo...');
            
            $.ajax({
                url: '../api/relatorios_carregar_modelo.php',
                method: 'GET',
                data: { id: modeloId },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        const modelo = response.modelo;
                        
                        // Preenche o formulário com os dados do modelo
                        tipoRelatorioAtual = modelo.tipo;
                        document.getElementById('tipoRelatorio').value = modelo.tipo;
                        
                        // Carrega campos e marca os selecionados
                        carregarCampos(modelo.tipo);
                        
                        // Aguarda campos carregarem
                        setTimeout(() => {
                            // Desmarca todos primeiro
                            document.querySelectorAll('#camposContainer input[type="checkbox"]').forEach(cb => {
                                cb.checked = false;
                            });
                            
                            // Marca apenas os campos do modelo
                            if (modelo.campos && Array.isArray(modelo.campos)) {
                                modelo.campos.forEach(campo => {
                                    const checkbox = document.getElementById('campo_' + campo);
                                    if (checkbox) checkbox.checked = true;
                                });
                            }
                            
                            // Preenche filtros
                            if (modelo.filtros) {
                                for (const key in modelo.filtros) {
                                    const input = document.querySelector(`[name="${key}"]`);
                                    if (input && modelo.filtros[key]) {
                                        input.value = modelo.filtros[key];
                                    }
                                }
                            }
                            
                            // Ordenação
                            if (modelo.ordenacao) {
                                document.getElementById('ordenacao').value = modelo.ordenacao;
                            }
                            
                            // Abre modal
                            document.getElementById('modalTitle').textContent = 'Executar: ' + modelo.nome;
                            document.getElementById('modalConfiguracao').classList.add('show');
                        }, 500);
                    } else {
                        alert('Erro ao carregar modelo: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro ao carregar modelo:', error);
                    alert('Erro ao carregar modelo');
                }
            });
        }

        // Fecha modais ao clicar fora
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-custom')) {
                event.target.classList.remove('show');
            }
        });

        // Tecla ESC fecha modais
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal-custom.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });
    </script>
</body>
</html>