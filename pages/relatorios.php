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

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .quick-action {
            flex: 1;
            padding: 0.5rem 0.75rem;
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            position: relative;
            overflow: hidden;
        }

        .quick-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent, rgba(0, 86, 210, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .quick-action:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.2);
        }

        .quick-action:hover::before {
            opacity: 1;
        }

        .quick-action:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 86, 210, 0.2);
        }

        .quick-action i {
            font-size: 0.875rem;
        }

        /* Model Actions */
        .model-actions {
            display: flex;
            gap: 0.375rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .model-action {
            flex: 1;
            padding: 0.5rem;
            background: transparent;
            border: 1.5px solid transparent;
            border-radius: 8px;
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--gray-600);
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            position: relative;
        }

        .model-action:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        .model-action.primary {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary-light);
        }

        .model-action.primary:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 86, 210, 0.2);
        }

        .model-action.danger {
            color: var(--danger);
        }

        .model-action.danger:hover {
            background: var(--danger);
            color: var(--white);
            border-color: var(--danger);
        }

        .model-action i {
            font-size: 0.75rem;
        }

        /* Report Card Actions */
        .report-card-footer {
            margin-top: auto;
            padding-top: 1rem;
        }

        .report-actions-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .report-action-btn {
            padding: 0.625rem 0.75rem;
            background: var(--white);
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-700);
            text-align: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            position: relative;
            overflow: hidden;
        }

        .report-action-btn i {
            font-size: 1rem;
            margin-bottom: 0.125rem;
            transition: transform 0.2s ease;
        }

        .report-action-btn span {
            display: block;
            font-size: 0.625rem;
            opacity: 0.8;
        }

        .report-action-btn:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.2);
        }

        .report-action-btn:hover i {
            transform: scale(1.1);
        }

        .report-action-btn.secondary:hover {
            background: var(--gray-700);
            border-color: var(--gray-700);
        }

        .report-action-btn.info:hover {
            background: var(--info);
            border-color: var(--info);
        }

        /* Quick Report Actions */
        .quick-report-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.375rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .quick-report-action {
            padding: 0.5rem 0.625rem;
            background: linear-gradient(135deg, var(--white), var(--gray-100));
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-700);
            text-align: center;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            position: relative;
            overflow: hidden;
        }

        .quick-report-action::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: radial-gradient(circle, rgba(0, 86, 210, 0.1), transparent);
            transform: translate(-50%, -50%);
            transition: width 0.4s ease, height 0.4s ease;
        }

        .quick-report-action:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 20px rgba(0, 86, 210, 0.3);
        }

        .quick-report-action:hover::after {
            width: 100%;
            height: 100%;
        }

        .quick-report-action:active {
            transform: translateY(0) scale(1);
        }

        .quick-report-action i {
            font-size: 0.875rem;
            transition: transform 0.2s ease;
        }

        .quick-report-action:hover i {
            transform: rotate(5deg) scale(1.1);
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
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .modal-content-custom.large {
            max-width: 800px;
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

        /* Campos Selecionados com Drag and Drop */
        .campos-selecionados-container {
            background: var(--white);
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 1rem;
            min-height: 200px;
        }

        .campos-selecionados-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .campos-selecionados-title {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .campos-ordem-info {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .campos-selecionados-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .campo-selecionado-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-100);
            border-radius: 8px;
            margin-bottom: 0.5rem;
            cursor: move;
            transition: all 0.2s ease;
            position: relative;
        }

        .campo-selecionado-item:hover {
            background: var(--gray-200);
            transform: translateX(4px);
        }

        .campo-selecionado-item.dragging {
            opacity: 0.5;
            background: var(--primary-light);
        }

        .campo-selecionado-item.drag-over {
            border-top: 3px solid var(--primary);
        }

        .campo-drag-handle {
            color: var(--gray-400);
            font-size: 0.875rem;
            cursor: grab;
        }

        .campo-drag-handle:active {
            cursor: grabbing;
        }

        .campo-selecionado-numero {
            width: 24px;
            height: 24px;
            background: var(--primary);
            color: var(--white);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .campo-selecionado-nome {
            flex: 1;
            font-size: 0.8125rem;
            color: var(--gray-700);
        }

        .campo-selecionado-remove {
            width: 28px;
            height: 28px;
            border: none;
            background: transparent;
            color: var(--gray-400);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .campo-selecionado-remove:hover {
            background: var(--danger);
            color: var(--white);
        }

        .campos-selecionados-empty {
            text-align: center;
            padding: 3rem;
            color: var(--gray-400);
            font-size: 0.875rem;
        }

        .campos-selecionados-empty i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
            opacity: 0.3;
        }

        /* Tabs para alternar entre seleção e ordenação */
        .campos-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            background: var(--gray-100);
            padding: 0.25rem;
            border-radius: 10px;
        }

        .campos-tab {
            flex: 1;
            padding: 0.625rem 1rem;
            background: transparent;
            border: none;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .campos-tab.active {
            background: var(--white);
            color: var(--primary);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .campos-tab-content {
            display: none;
        }

        .campos-tab-content.active {
            display: block;
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

        /* Quick Filters */
        .quick-filters {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .quick-filters-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
        }

        .filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .filter-pill {
            padding: 0.5rem 1rem;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-pill:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-pill.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        /* Simple Date Range */
        .date-range-simple {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .date-range-simple .form-control-custom {
            flex: 1;
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

            .quick-actions {
                flex-direction: column;
            }

            .date-range-simple {
                flex-direction: column;
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
                        <a href="logout.php" class="dropdown-item-custom">
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
                            <div class="stat-label">Modelos Salvos</div>
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
                        Relatórios Rápidos
                    </h3>
                    <div class="section-actions">
                        <button class="btn-modern btn-primary" onclick="abrirModalPersonalizado()">
                            <i class="fas fa-magic"></i>
                            Criar Relatório Personalizado
                        </button>
                    </div>
                </div>

                <div class="report-grid">
                    <!-- Relatório de Associados -->
                    <div class="report-card" onclick="executarRelatorioRapido('associados')">
                        <div class="report-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="report-title">Associados Ativos</h4>
                        <p class="report-description">
                            Lista completa de todos os associados ativos com informações básicas e contato.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('associados', 'hoje'); event.stopPropagation();">
                                <i class="fas fa-calendar-day"></i>
                                <span>Hoje</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('associados', 'mes'); event.stopPropagation();">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Este Mês</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('associados', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>

                    <!-- Relatório Financeiro -->
                    <div class="report-card" onclick="executarRelatorioRapido('financeiro')">
                        <div class="report-icon green">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4 class="report-title">Situação Financeira</h4>
                        <p class="report-description">
                            Análise da situação financeira dos associados e status de pagamentos.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('financeiro', 'adimplentes'); event.stopPropagation();">
                                <i class="fas fa-check"></i>
                                <span>Adimplentes</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('financeiro', 'inadimplentes'); event.stopPropagation();">
                                <i class="fas fa-times"></i>
                                <span>Inadimplentes</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('financeiro', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>

                    <!-- Relatório Militar -->
                    <div class="report-card" onclick="executarRelatorioRapido('militar')">
                        <div class="report-icon orange">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="report-title">Distribuição Militar</h4>
                        <p class="report-description">
                            Distribuição dos associados por patente, corporação e unidade militar.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('militar', 'patente'); event.stopPropagation();">
                                <i class="fas fa-star"></i>
                                <span>Por Patente</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('militar', 'corporacao'); event.stopPropagation();">
                                <i class="fas fa-building"></i>
                                <span>Por Corporação</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('militar', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>

                    <!-- Relatório de Serviços -->
                    <div class="report-card" onclick="executarRelatorioRapido('servicos')">
                        <div class="report-icon purple">
                            <i class="fas fa-concierge-bell"></i>
                        </div>
                        <h4 class="report-title">Adesão aos Serviços</h4>
                        <p class="report-description">
                            Relatório de adesão aos serviços oferecidos e valores aplicados.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('servicos', 'ativos'); event.stopPropagation();">
                                <i class="fas fa-toggle-on"></i>
                                <span>Ativos</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('servicos', 'todos'); event.stopPropagation();">
                                <i class="fas fa-list"></i>
                                <span>Todos</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('servicos', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>

                    <!-- Relatório de Documentos -->
                    <div class="report-card" onclick="executarRelatorioRapido('documentos')">
                        <div class="report-icon red">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h4 class="report-title">Status de Documentos</h4>
                        <p class="report-description">
                            Controle de documentos enviados e status de verificação.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('documentos', 'pendentes'); event.stopPropagation();">
                                <i class="fas fa-clock"></i>
                                <span>Pendentes</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('documentos', 'verificados'); event.stopPropagation();">
                                <i class="fas fa-check-circle"></i>
                                <span>Verificados</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('documentos', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Saved Models Section -->
            <?php if (!empty($modelosDisponiveis)): ?>
            <div class="section-card" data-aos="fade-up" data-aos-delay="150">
                <div class="section-header">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-save"></i>
                        </div>
                        Modelos Salvos
                    </h3>
                </div>

                <div class="report-grid">
                    <?php foreach ($modelosDisponiveis as $modelo): ?>
                    <div class="report-card" onclick="executarModelo(<?php echo $modelo['id']; ?>)">
                        <div class="report-icon info">
                            <i class="fas fa-file-code"></i>
                        </div>
                        <h4 class="report-title"><?php echo htmlspecialchars($modelo['nome']); ?></h4>
                        <p class="report-description">
                            <?php echo htmlspecialchars($modelo['descricao'] ?? 'Modelo personalizado de relatório'); ?>
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($modelo['criado_por_nome'] ?? 'Sistema'); ?></span>
                            <span><?php echo $modelo['total_execucoes'] ?? 0; ?> execuções</span>
                        </div>
                        <div class="model-actions">
                            <a class="model-action primary" onclick="executarModelo(<?php echo $modelo['id']; ?>); event.stopPropagation();">
                                <i class="fas fa-play"></i>
                                <span>Executar</span>
                            </a>
                            <a class="model-action" onclick="editarModelo(<?php echo $modelo['id']; ?>); event.stopPropagation();">
                                <i class="fas fa-edit"></i>
                                <span>Editar</span>
                            </a>
                            <a class="model-action" onclick="duplicarModelo(<?php echo $modelo['id']; ?>); event.stopPropagation();">
                                <i class="fas fa-copy"></i>
                                <span>Duplicar</span>
                            </a>
                            <?php if ($auth->isDiretor()): ?>
                            <a class="model-action danger" onclick="excluirModelo(<?php echo $modelo['id']; ?>, '<?php echo htmlspecialchars($modelo['nome'], ENT_QUOTES); ?>'); event.stopPropagation();">
                                <i class="fas fa-trash"></i>
                                <span>Excluir</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

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

    <!-- Modal de Filtros Rápidos -->
    <div class="modal-custom" id="modalFiltrosRapidos">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom" id="modalFiltrosTitle">Filtrar Relatório</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalFiltrosRapidos')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formFiltrosRapidos">
                    <input type="hidden" id="tipoRelatorioRapido" name="tipo">
                    
                    <!-- Filtros de Data -->
                    <div class="form-section">
                        <h3 class="form-section-title">Período</h3>
                        <div class="date-range-simple">
                            <input type="date" class="form-control-custom" id="dataInicioRapido" name="data_inicio">
                            <span style="color: var(--gray-500);">até</span>
                            <input type="date" class="form-control-custom" id="dataFimRapido" name="data_fim">
                        </div>
                    </div>

                    <!-- Filtros Específicos serão carregados aqui -->
                    <div id="filtrosEspecificosRapidos"></div>

                    <!-- Botões -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalFiltrosRapidos')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-filter"></i>
                            Aplicar Filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Relatório Personalizado -->
    <div class="modal-custom" id="modalPersonalizado">
        <div class="modal-content-custom large">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Criar Relatório Personalizado</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalPersonalizado')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formPersonalizado">
                    <!-- Informações Básicas -->
                    <div class="form-section">
                        <h3 class="form-section-title">Informações do Relatório</h3>
                        <div class="form-group">
                            <label class="form-label">Nome do Relatório</label>
                            <input type="text" class="form-control-custom" id="nomeRelatorio" name="nome" required placeholder="Ex: Relatório Mensal de Associados">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tipo de Dados</label>
                            <select class="form-control-custom form-select-custom" id="tipoRelatorio" name="tipo" required>
                                <option value="">Selecione o tipo</option>
                                <option value="associados">Associados</option>
                                <option value="financeiro">Financeiro</option>
                                <option value="militar">Militar</option>
                                <option value="servicos">Serviços</option>
                                <option value="documentos">Documentos</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descrição (opcional)</label>
                            <textarea class="form-control-custom" id="descricaoRelatorio" name="descricao" rows="2" placeholder="Descreva o objetivo deste relatório"></textarea>
                        </div>
                    </div>

                    <!-- Seleção de Campos -->
                    <div class="form-section" id="secaoCampos" style="display: none;">
                        <h3 class="form-section-title">Campos do Relatório</h3>
                        
                        <!-- Tabs para alternar entre seleção e ordenação -->
                        <div class="campos-tabs">
                            <button type="button" class="campos-tab active" onclick="alternarTabCampos('selecao', event)">
                                <i class="fas fa-check-square"></i> Selecionar Campos
                            </button>
                            <button type="button" class="campos-tab" onclick="alternarTabCampos('ordem', event)">
                                <i class="fas fa-sort"></i> Ordenar Campos
                            </button>
                        </div>

                        <!-- Tab de Seleção -->
                        <div class="campos-tab-content active" id="tabSelecao">
                            <div class="quick-filters">
                                <div class="quick-filters-title">Ações rápidas:</div>
                                <div class="filter-pills">
                                    <span class="filter-pill" onclick="selecionarTodosCampos()">
                                        <i class="fas fa-check-square"></i> Selecionar Todos
                                    </span>
                                    <span class="filter-pill" onclick="limparTodosCampos()">
                                        <i class="fas fa-square"></i> Limpar Todos
                                    </span>
                                    <span class="filter-pill" onclick="selecionarCamposBasicos()">
                                        <i class="fas fa-star"></i> Campos Básicos
                                    </span>
                                </div>
                            </div>
                            <div class="checkbox-group" id="camposPersonalizados">
                                <!-- Campos serão carregados dinamicamente -->
                            </div>
                        </div>

                        <!-- Tab de Ordenação -->
                        <div class="campos-tab-content" id="tabOrdem">
                            <div class="campos-selecionados-container">
                                <div class="campos-selecionados-header">
                                    <div class="campos-selecionados-title">
                                        <i class="fas fa-grip-vertical"></i> Arraste para reordenar
                                    </div>
                                    <div class="campos-ordem-info">
                                        Os campos aparecerão no relatório nesta ordem
                                    </div>
                                </div>
                                <ul class="campos-selecionados-list" id="camposSelecionadosList">
                                    <!-- Campos selecionados aparecerão aqui -->
                                </ul>
                                <div class="campos-selecionados-empty" id="camposSelecionadosEmpty">
                                    <i class="fas fa-inbox"></i>
                                    <p>Nenhum campo selecionado</p>
                                    <p class="text-muted small">Selecione campos na aba anterior</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros e Ordenação -->
                    <div class="form-section" id="secaoFiltros" style="display: none;">
                        <h3 class="form-section-title">Filtros e Ordenação</h3>
                        <div id="filtrosPersonalizados">
                            <!-- Filtros serão carregados dinamicamente -->
                        </div>
                        
                        <div class="form-group mt-3">
                            <label class="form-label">Ordenar por</label>
                            <select class="form-control-custom form-select-custom" name="ordenacao">
                                <option value="">Padrão</option>
                                <option value="nome ASC">Nome (A-Z)</option>
                                <option value="nome DESC">Nome (Z-A)</option>
                                <option value="id DESC">Mais recentes</option>
                                <option value="id ASC">Mais antigos</option>
                            </select>
                        </div>
                    </div>

                    <!-- Opções de Salvamento -->
                    <div class="form-section">
                        <label class="checkbox-item">
                            <input type="checkbox" class="checkbox-custom" id="salvarModelo" name="salvar_modelo" checked>
                            <span class="checkbox-label">Salvar como modelo para uso futuro</span>
                        </label>
                    </div>

                    <!-- Botões -->
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalPersonalizado')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-file-export"></i>
                            Gerar Relatório
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
        let camposOrdenados = [];
        let isDiretor = <?php echo $auth->isDiretor() ? 'true' : 'false'; ?>;
        let camposBasicos = {
            'associados': ['nome', 'cpf', 'telefone', 'email', 'situacao'],
            'financeiro': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira'],
            'militar': ['nome', 'cpf', 'corporacao', 'patente'],
            'servicos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'ativo'],
            'documentos': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'verificado']
        };

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

            // Event listeners
            document.getElementById('formFiltrosRapidos').addEventListener('submit', aplicarFiltrosRapidos);
            document.getElementById('formPersonalizado').addEventListener('submit', gerarRelatorioPersonalizado);
            
            // Mudança de tipo no relatório personalizado
            document.getElementById('tipoRelatorio').addEventListener('change', function() {
                if (this.value) {
                    document.getElementById('secaoCampos').style.display = 'block';
                    document.getElementById('secaoFiltros').style.display = 'block';
                    
                    // Se mudou o tipo, limpa a ordem anterior pois os campos são diferentes
                    if (this.value !== tipoRelatorioAtual) {
                        camposOrdenados = [];
                    }
                    
                    tipoRelatorioAtual = this.value;
                    carregarCamposPersonalizados(this.value);
                    carregarFiltrosPersonalizados(this.value);
                    // Reseta para aba de seleção (sem event)
                    alternarTabCampos('selecao', null);
                } else {
                    document.getElementById('secaoCampos').style.display = 'none';
                    document.getElementById('secaoFiltros').style.display = 'none';
                }
            });
            
            // Event listener para mudanças nos checkboxes
            document.addEventListener('change', function(e) {
                if (e.target.matches('#camposPersonalizados input[type="checkbox"]')) {
                    atualizarCamposSelecionados();
                }
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

        // Executa relatório rápido
        function executarRelatorioRapido(tipo, preset = null) {
            if (preset === 'personalizar') {
                // Abre modal de filtros
                abrirModalFiltrosRapidos(tipo);
                return;
            }

            showLoading('Gerando relatório...');

            // Prepara dados baseados no preset
            const dados = {
                tipo: tipo,
                campos: getCamposPreset(tipo, preset),
                formato: 'html'
            };

            // Adiciona filtros baseados no preset
            const filtros = getFiltrosPreset(tipo, preset);
            Object.assign(dados, filtros);

            // Executa relatório
            executarRelatorio(dados);
        }

        // Retorna campos predefinidos para relatórios rápidos
        function getCamposPreset(tipo, preset) {
            const presets = {
                'associados': {
                    'default': ['nome', 'cpf', 'telefone', 'email', 'situacao', 'corporacao', 'patente'],
                    'hoje': ['nome', 'cpf', 'telefone', 'email', 'dataFiliacao'],
                    'mes': ['nome', 'cpf', 'telefone', 'email', 'situacao', 'dataFiliacao']
                },
                'financeiro': {
                    'default': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira', 'localDebito'],
                    'adimplentes': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira'],
                    'inadimplentes': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira', 'telefone', 'email']
                },
                'militar': {
                    'default': ['nome', 'cpf', 'corporacao', 'patente', 'categoria', 'unidade'],
                    'patente': ['patente', 'nome', 'cpf', 'corporacao', 'unidade'],
                    'corporacao': ['corporacao', 'nome', 'cpf', 'patente', 'unidade']
                },
                'servicos': {
                    'default': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao', 'ativo'],
                    'ativos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao'],
                    'todos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao', 'ativo']
                },
                'documentos': {
                    'default': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'verificado'],
                    'pendentes': ['nome', 'cpf', 'tipo_documento', 'data_upload'],
                    'verificados': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'funcionario_nome']
                }
            };

            return presets[tipo]?.[preset] || presets[tipo]?.['default'] || [];
        }

        // Retorna filtros predefinidos para relatórios rápidos
        function getFiltrosPreset(tipo, preset) {
            const hoje = new Date().toISOString().split('T')[0];
            const inicioMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
            
            const filtros = {
                'associados': {
                    'hoje': { data_inicio: hoje, data_fim: hoje },
                    'mes': { data_inicio: inicioMes, data_fim: hoje }
                },
                'financeiro': {
                    'adimplentes': { situacaoFinanceira: 'Regular' },
                    'inadimplentes': { situacaoFinanceira: 'Inadimplente' }
                },
                'militar': {
                    'patente': { ordenacao: 'patente ASC, nome ASC' },
                    'corporacao': { ordenacao: 'corporacao ASC, patente ASC, nome ASC' }
                },
                'servicos': {
                    'ativos': { ativo: '1' },
                    'todos': {}
                },
                'documentos': {
                    'pendentes': { verificado: '0' },
                    'verificados': { verificado: '1' }
                }
            };

            return filtros[tipo]?.[preset] || {};
        }

        // Abre modal de filtros rápidos
        function abrirModalFiltrosRapidos(tipo) {
            tipoRelatorioAtual = tipo;
            document.getElementById('tipoRelatorioRapido').value = tipo;
            
            // Atualiza título
            const titulos = {
                'associados': 'Filtrar Relatório de Associados',
                'financeiro': 'Filtrar Relatório Financeiro',
                'militar': 'Filtrar Relatório Militar',
                'servicos': 'Filtrar Relatório de Serviços',
                'documentos': 'Filtrar Relatório de Documentos'
            };
            document.getElementById('modalFiltrosTitle').textContent = titulos[tipo] || 'Filtrar Relatório';
            
            // Carrega filtros específicos
            carregarFiltrosRapidos(tipo);
            
            // Abre modal
            document.getElementById('modalFiltrosRapidos').classList.add('show');
        }

        // Carrega filtros específicos para modal rápido
        function carregarFiltrosRapidos(tipo) {
            const container = document.getElementById('filtrosEspecificosRapidos');
            let html = '<div class="form-section"><h3 class="form-section-title">Filtros Específicos</h3>';
            
            switch(tipo) {
                case 'associados':
                    html += `
                        <div class="form-group">
                            <label class="form-label">Situação</label>
                            <select class="form-control-custom form-select-custom" name="situacao">
                                <option value="">Todos</option>
                                <option value="Filiado">Filiado</option>
                                <option value="Desfiliado">Desfiliado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control-custom" name="busca" placeholder="Nome, CPF ou RG">
                        </div>
                    `;
                    break;
                    
                case 'financeiro':
                    html += `
                        <div class="form-group">
                            <label class="form-label">Situação Financeira</label>
                            <select class="form-control-custom form-select-custom" name="situacaoFinanceira">
                                <option value="">Todas</option>
                                <option value="Regular">Regular</option>
                                <option value="Inadimplente">Inadimplente</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'militar':
                    html += `
                        <div class="form-group">
                            <label class="form-label">Corporação</label>
                            <select class="form-control-custom form-select-custom" name="corporacao">
                                <option value="">Todas</option>
                                <option value="PM">Polícia Militar</option>
                                <option value="CBM">Corpo de Bombeiros</option>
                                <option value="PC">Polícia Civil</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'servicos':
                    html += `
                        <div class="form-group">
                            <label class="form-label">Status do Serviço</label>
                            <select class="form-control-custom form-select-custom" name="ativo">
                                <option value="">Todos</option>
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'documentos':
                    html += `
                        <div class="form-group">
                            <label class="form-label">Status de Verificação</label>
                            <select class="form-control-custom form-select-custom" name="verificado">
                                <option value="">Todos</option>
                                <option value="1">Verificado</option>
                                <option value="0">Não Verificado</option>
                            </select>
                        </div>
                    `;
                    break;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }

        // Aplica filtros rápidos
        function aplicarFiltrosRapidos(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {
                tipo: formData.get('tipo'),
                campos: getCamposPreset(formData.get('tipo'), 'default'),
                formato: 'html'
            };
            
            // Adiciona filtros do formulário
            for (let [key, value] of formData.entries()) {
                if (key !== 'tipo' && value) {
                    dados[key] = value;
                }
            }
            
            showLoading('Gerando relatório...');
            executarRelatorio(dados);
            fecharModal('modalFiltrosRapidos');
        }

        // Abre modal de relatório personalizado
        function abrirModalPersonalizado() {
            // Limpa estado anterior apenas se não estiver editando
            const formPersonalizado = document.getElementById('formPersonalizado');
            if (!formPersonalizado.getAttribute('data-modelo-id')) {
                camposOrdenados = [];
                tipoRelatorioAtual = '';
            }
            document.getElementById('modalPersonalizado').classList.add('show');
        }

        // Carrega campos para relatório personalizado
        function carregarCamposPersonalizados(tipo) {
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
                        renderizarCamposPersonalizados(response.campos);
                    } else {
                        alert('Erro ao carregar campos: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro ao carregar campos:', error);
                    
                    // Usa campos padrão como fallback
                    const camposPadrao = getCamposPadrao(tipo);
                    renderizarCamposPersonalizados(camposPadrao);
                }
            });
        }

        // Renderiza campos no modal personalizado
        function renderizarCamposPersonalizados(campos) {
            const container = document.getElementById('camposPersonalizados');
            container.innerHTML = '';
            
            // Se já temos uma ordem definida, reorganiza os campos para respeitar
            let camposOrganizados = reorganizarCamposPorOrdem(campos);
            
            for (const categoria in camposOrganizados) {
                // Adiciona header da categoria
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'w-100';
                categoryDiv.innerHTML = `<div class="category-header">${categoria}</div>`;
                container.appendChild(categoryDiv);
                
                // Adiciona campos da categoria
                camposOrganizados[categoria].forEach(campo => {
                    const checkboxItem = document.createElement('div');
                    checkboxItem.className = 'checkbox-item';
                    
                    // Marca campos básicos por padrão ou campos que estavam selecionados
                    const isBasico = camposBasicos[tipoRelatorioAtual]?.includes(campo.nome_campo);
                    const isSelecionado = camposOrdenados.includes(campo.nome_campo);
                    
                    checkboxItem.innerHTML = `
                        <input type="checkbox" 
                               class="checkbox-custom" 
                               id="campo_personalizado_${campo.nome_campo}" 
                               name="campos[]" 
                               value="${campo.nome_campo}"
                               ${(isBasico || isSelecionado) ? 'checked' : ''}>
                        <label class="checkbox-label" for="campo_personalizado_${campo.nome_campo}">
                            ${campo.nome_exibicao}
                        </label>
                    `;
                    container.appendChild(checkboxItem);
                });
            }
            
            // Se temos campos ordenados, atualiza a lista
            if (camposOrdenados.length > 0) {
                setTimeout(() => {
                    atualizarCamposSelecionados();
                }, 100);
            }
        }

        // Reorganiza campos respeitando a ordem salva
        function reorganizarCamposPorOrdem(campos) {
            if (camposOrdenados.length === 0) {
                return campos;
            }
            
            // Cria um mapa de campos para facilitar busca
            let mapaCampos = {};
            for (const categoria in campos) {
                campos[categoria].forEach(campo => {
                    mapaCampos[campo.nome_campo] = {
                        ...campo,
                        categoria: categoria
                    };
                });
            }
            
            // Reorganiza baseado na ordem
            let camposReorganizados = {};
            
            // Primeiro, adiciona campos na ordem definida
            camposOrdenados.forEach(nomeCampo => {
                if (mapaCampos[nomeCampo]) {
                    const campo = mapaCampos[nomeCampo];
                    const categoria = campo.categoria;
                    
                    if (!camposReorganizados[categoria]) {
                        camposReorganizados[categoria] = [];
                    }
                    
                    // Evita duplicatas
                    if (!camposReorganizados[categoria].find(c => c.nome_campo === nomeCampo)) {
                        camposReorganizados[categoria].push({
                            nome_campo: campo.nome_campo,
                            nome_exibicao: campo.nome_exibicao,
                            tipo_dado: campo.tipo_dado
                        });
                    }
                }
            });
            
            // Depois, adiciona campos que não estão na ordem (novos campos)
            for (const categoria in campos) {
                campos[categoria].forEach(campo => {
                    if (!camposOrdenados.includes(campo.nome_campo)) {
                        if (!camposReorganizados[categoria]) {
                            camposReorganizados[categoria] = [];
                        }
                        camposReorganizados[categoria].push(campo);
                    }
                });
            }
            
            return camposReorganizados;
        }

        // Carrega filtros para relatório personalizado
        function carregarFiltrosPersonalizados(tipo) {
            const container = document.getElementById('filtrosPersonalizados');
            let html = '';
            
            // Filtros de data (comuns a todos)
            html += `
                <div class="date-range-simple mb-3">
                    <input type="date" class="form-control-custom" name="data_inicio" placeholder="Data inicial">
                    <span style="color: var(--gray-500);">até</span>
                    <input type="date" class="form-control-custom" name="data_fim" placeholder="Data final">
                </div>
            `;
            
            // Filtros específicos por tipo
            switch(tipo) {
                case 'associados':
                    html += `
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
                                    <label class="form-label">Buscar</label>
                                    <input type="text" class="form-control-custom" name="busca" placeholder="Nome, CPF ou RG">
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'financeiro':
                    html += `
                        <div class="form-group">
                            <label class="form-label">Situação Financeira</label>
                            <select class="form-control-custom form-select-custom" name="situacaoFinanceira">
                                <option value="">Todas</option>
                                <option value="Regular">Regular</option>
                                <option value="Inadimplente">Inadimplente</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'militar':
                    html += `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Corporação</label>
                                    <select class="form-control-custom form-select-custom" name="corporacao">
                                        <option value="">Todas</option>
                                        <option value="PM">Polícia Militar</option>
                                        <option value="CBM">Corpo de Bombeiros</option>
                                        <option value="PC">Polícia Civil</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Patente</label>
                                    <input type="text" class="form-control-custom" name="patente" placeholder="Ex: Coronel">
                                </div>
                            </div>
                        </div>
                    `;
                    break;
            }
            
            container.innerHTML = html;
        }

        // Funções de seleção de campos
        function selecionarTodosCampos() {
            document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
            });
        }

        function limparTodosCampos() {
            document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
        }

        function selecionarCamposBasicos() {
            const tipo = document.getElementById('tipoRelatorio').value;
            const basicos = camposBasicos[tipo] || [];
            
            document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                cb.checked = basicos.includes(cb.value);
            });
        }

        // Gera relatório personalizado
        function gerarRelatorioPersonalizado(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {};
            
            // Converte FormData para objeto
            for (let [key, value] of formData.entries()) {
                if (key === 'campos[]') {
                    // Ignora campos[] do FormData, usaremos camposOrdenados
                } else {
                    dados[key] = value;
                }
            }
            
            // Usa campos na ordem definida pelo usuário
            if (camposOrdenados.length > 0) {
                dados.campos = camposOrdenados;
            } else {
                // Se não há ordem definida, pega dos checkboxes
                dados.campos = [];
                document.querySelectorAll('#camposPersonalizados input[type="checkbox"]:checked').forEach(cb => {
                    dados.campos.push(cb.value);
                });
            }
            
            // Validações
            if (!dados.campos || dados.campos.length === 0) {
                alert('Selecione ao menos um campo para o relatório');
                return;
            }
            
            // Adiciona o ID do modelo se estiver editando
            const modeloIdEditando = document.getElementById('formPersonalizado').getAttribute('data-modelo-id');
            if (modeloIdEditando) {
                dados.id = modeloIdEditando;
            }
            
            dados.formato = 'html'; // Padrão
            
            showLoading('Gerando relatório...');
            
            // Se deve salvar como modelo
            if (dados.salvar_modelo) {
                salvarModelo(dados).then(modeloId => {
                    executarRelatorio(dados);
                    fecharModal('modalPersonalizado');
                    // Recarrega a página para mostrar o modelo atualizado
                    if (modeloIdEditando) {
                        setTimeout(() => location.reload(), 1000);
                    }
                }).catch(error => {
                    hideLoading();
                    alert('Erro ao salvar modelo: ' + error);
                });
            } else {
                executarRelatorio(dados);
                fecharModal('modalPersonalizado');
            }
        }

        // Salva modelo de relatório
        function salvarModelo(dados) {
            return new Promise((resolve, reject) => {
                const modeloData = {
                    nome: dados.nome,
                    descricao: dados.descricao || '',
                    tipo: dados.tipo,
                    campos: dados.campos,
                    filtros: {}
                };
                
                // Se tem ID, é atualização
                if (dados.id) {
                    modeloData.id = dados.id;
                }
                
                // Adiciona apenas filtros que não são vazios
                const filtrosPossiveis = ['data_inicio', 'data_fim', 'situacao', 'corporacao', 
                                         'patente', 'situacaoFinanceira', 'ativo', 'verificado', 'busca'];
                
                filtrosPossiveis.forEach(filtro => {
                    if (dados[filtro] && dados[filtro] !== '') {
                        modeloData.filtros[filtro] = dados[filtro];
                    }
                });
                
                // Adiciona ordenação se existir
                if (dados.ordenacao && dados.ordenacao !== '') {
                    modeloData.ordenacao = dados.ordenacao;
                }
                
                // Define método baseado se é criação ou atualização
                const method = dados.id ? 'PUT' : 'POST';
                
                console.log('Enviando modelo:', modeloData);
                
                $.ajax({
                    url: '../api/relatorios_salvar_modelo.php',
                    method: method,
                    data: JSON.stringify(modeloData),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Resposta do servidor:', response);
                        if (response.status === 'success') {
                            resolve(response.modelo_id);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro na requisição:', xhr.responseText);
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            reject(errorResponse.message || 'Erro ao salvar modelo');
                        } catch (e) {
                            reject('Erro ao salvar modelo: ' + error);
                        }
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
                        
                        // Prepara dados para execução
                        const dados = {
                            tipo: modelo.tipo,
                            campos: modelo.campos,
                            formato: 'html',
                            modelo_id: modeloId
                        };
                        
                        // Adiciona filtros
                        if (modelo.filtros) {
                            Object.assign(dados, modelo.filtros);
                        }
                        
                        // Adiciona ordenação
                        if (modelo.ordenacao) {
                            dados.ordenacao = modelo.ordenacao;
                        }
                        
                        showLoading('Gerando relatório...');
                        executarRelatorio(dados);
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

        // Edita modelo (abre modal com dados preenchidos)
        function editarModelo(modeloId) {
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
                        
                        // Marca o formulário como edição
                        document.getElementById('formPersonalizado').setAttribute('data-modelo-id', modeloId);
                        
                        // Preenche o formulário
                        document.getElementById('nomeRelatorio').value = modelo.nome;
                        document.getElementById('descricaoRelatorio').value = modelo.descricao || '';
                        document.getElementById('tipoRelatorio').value = modelo.tipo;
                        
                        // Dispara mudança para carregar campos
                        document.getElementById('tipoRelatorio').dispatchEvent(new Event('change'));
                        
                        // Aguarda campos carregarem
                        setTimeout(() => {
                            // Marca campos do modelo
                            if (modelo.campos && Array.isArray(modelo.campos)) {
                                document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                                    cb.checked = modelo.campos.includes(cb.value);
                                });
                                // Define a ordem dos campos
                                camposOrdenados = [...modelo.campos];
                                atualizarCamposSelecionados();
                            }
                            
                            // Preenche filtros
                            if (modelo.filtros) {
                                for (const key in modelo.filtros) {
                                    const input = document.querySelector(`#filtrosPersonalizados [name="${key}"]`);
                                    if (input && modelo.filtros[key]) {
                                        input.value = modelo.filtros[key];
                                    }
                                }
                            }
                            
                            // Preenche ordenação
                            if (modelo.ordenacao) {
                                const selectOrdenacao = document.querySelector('[name="ordenacao"]');
                                if (selectOrdenacao) {
                                    selectOrdenacao.value = modelo.ordenacao;
                                }
                            }
                            
                            // Marca para salvar
                            document.getElementById('salvarModelo').checked = true;
                            
                            // Atualiza título do modal
                            document.querySelector('#modalPersonalizado .modal-title-custom').textContent = 'Editar Relatório Personalizado';
                            
                            // Abre modal
                            abrirModalPersonalizado();
                        }, 1000);
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

        // Duplica modelo
        function duplicarModelo(modeloId) {
            if (confirm('Deseja duplicar este modelo?')) {
                showLoading('Duplicando modelo...');
                
                // Por simplicidade, carrega o modelo e cria um novo
                $.ajax({
                    url: '../api/relatorios_carregar_modelo.php',
                    method: 'GET',
                    data: { id: modeloId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            const modelo = response.modelo;
                            modelo.nome = modelo.nome + ' (Cópia)';
                            delete modelo.id;
                            
                            // Salva como novo modelo
                            salvarModelo(modelo).then(novoId => {
                                hideLoading();
                                alert('Modelo duplicado com sucesso!');
                                location.reload();
                            }).catch(error => {
                                hideLoading();
                                alert('Erro ao duplicar modelo: ' + error);
                            });
                        } else {
                            hideLoading();
                            alert('Erro ao carregar modelo: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        hideLoading();
                        alert('Erro ao duplicar modelo');
                    }
                });
            }
        }

        // Exclui modelo (apenas diretores)
        function excluirModelo(modeloId, nomeModelo) {
            if (!isDiretor) {
                alert('Apenas diretores podem excluir modelos de relatórios.');
                return;
            }
            
            // Confirmação com nome do modelo
            const mensagem = `Tem certeza que deseja excluir o modelo "${nomeModelo}"?\n\nEsta ação não pode ser desfeita.`;
            
            if (confirm(mensagem)) {
                // Segunda confirmação para modelos importantes
                if (confirm('Esta é uma ação permanente. Confirma a exclusão?')) {
                    showLoading('Excluindo modelo...');
                    
                    $.ajax({
                        url: '../api/relatorios_excluir_modelo.php?id=' + modeloId,
                        method: 'DELETE',
                        dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            
                            if (response.status === 'success') {
                                // Feedback visual
                                const card = document.querySelector(`[onclick*="executarModelo(${modeloId})"]`);
                                if (card) {
                                    card.style.transition = 'all 0.3s ease';
                                    card.style.transform = 'scale(0.9)';
                                    card.style.opacity = '0.5';
                                }
                                
                                setTimeout(() => {
                                    alert('Modelo excluído com sucesso!');
                                    location.reload();
                                }, 300);
                            } else {
                                alert('Erro ao excluir modelo: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            hideLoading();
                            
                            // Tenta obter mensagem de erro do servidor
                            try {
                                const errorResponse = JSON.parse(xhr.responseText);
                                alert('Erro ao excluir modelo: ' + errorResponse.message);
                            } catch (e) {
                                alert('Erro ao excluir modelo. Por favor, tente novamente.');
                            }
                            
                            console.error('Erro ao excluir:', xhr.responseText);
                        }
                    });
                }
            }
        }

        // Fecha modal
        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            
            // Limpa formulários
            if (modalId === 'modalFiltrosRapidos') {
                document.getElementById('formFiltrosRapidos').reset();
            } else if (modalId === 'modalPersonalizado') {
                // Salva o estado atual antes de fechar
                const modeloIdEditando = document.getElementById('formPersonalizado').getAttribute('data-modelo-id');
                
                // Se não está editando um modelo existente, limpa tudo
                if (!modeloIdEditando) {
                    document.getElementById('formPersonalizado').reset();
                    document.getElementById('formPersonalizado').removeAttribute('data-modelo-id');
                    document.getElementById('secaoCampos').style.display = 'none';
                    document.getElementById('secaoFiltros').style.display = 'none';
                    // Restaura título original
                    document.querySelector('#modalPersonalizado .modal-title-custom').textContent = 'Criar Relatório Personalizado';
                    // Limpa campos ordenados apenas se não estiver editando
                    camposOrdenados = [];
                    // Volta para aba de seleção (sem event)
                    alternarTabCampos('selecao', null);
                }
                // Se está editando, mantém o estado atual
            }
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

        // Retorna campos padrão (fallback)
        function getCamposPadrao(tipo) {
            const campos = {
                'associados': {
                    'Dados Pessoais': [
                        { nome_campo: 'nome', nome_exibicao: 'Nome Completo' },
                        { nome_campo: 'cpf', nome_exibicao: 'CPF' },
                        { nome_campo: 'rg', nome_exibicao: 'RG' },
                        { nome_campo: 'telefone', nome_exibicao: 'Telefone' },
                        { nome_campo: 'email', nome_exibicao: 'E-mail' }
                    ],
                    'Informações Militares': [
                        { nome_campo: 'corporacao', nome_exibicao: 'Corporação' },
                        { nome_campo: 'patente', nome_exibicao: 'Patente' }
                    ]
                },
                'financeiro': {
                    'Dados Financeiros': [
                        { nome_campo: 'tipoAssociado', nome_exibicao: 'Tipo de Associado' },
                        { nome_campo: 'situacaoFinanceira', nome_exibicao: 'Situação Financeira' }
                    ]
                },
                'militar': {
                    'Informações Militares': [
                        { nome_campo: 'corporacao', nome_exibicao: 'Corporação' },
                        { nome_campo: 'patente', nome_exibicao: 'Patente' },
                        { nome_campo: 'unidade', nome_exibicao: 'Unidade' }
                    ]
                },
                'servicos': {
                    'Serviços': [
                        { nome_campo: 'servico_nome', nome_exibicao: 'Nome do Serviço' },
                        { nome_campo: 'valor_aplicado', nome_exibicao: 'Valor' },
                        { nome_campo: 'ativo', nome_exibicao: 'Status' }
                    ]
                },
                'documentos': {
                    'Documentos': [
                        { nome_campo: 'tipo_documento', nome_exibicao: 'Tipo' },
                        { nome_campo: 'data_upload', nome_exibicao: 'Data' },
                        { nome_campo: 'verificado', nome_exibicao: 'Status' }
                    ]
                }
            };
            
            return campos[tipo] || {};
        }

        // Alternância entre tabs de campos
        function alternarTabCampos(tab, event) {
            // Se event não foi passado (chamada programática), não tenta acessar event.target
            if (event && event.target) {
                // Atualiza botões
                document.querySelectorAll('.campos-tab').forEach(btn => {
                    btn.classList.remove('active');
                });
                event.target.closest('.campos-tab').classList.add('active');
            } else {
                // Chamada programática - atualiza botões baseado no tab
                document.querySelectorAll('.campos-tab').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Encontra e ativa o botão correto
                const botaoCorreto = tab === 'selecao' 
                    ? document.querySelector('.campos-tab:first-child')
                    : document.querySelector('.campos-tab:last-child');
                    
                if (botaoCorreto) {
                    botaoCorreto.classList.add('active');
                }
            }
            
            // Atualiza conteúdo
            document.querySelectorAll('.campos-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            if (tab === 'selecao') {
                document.getElementById('tabSelecao').classList.add('active');
            } else {
                document.getElementById('tabOrdem').classList.add('active');
                atualizarCamposSelecionados();
            }
        }

        // Atualiza lista de campos selecionados para ordenação
        function atualizarCamposSelecionados() {
            const checkboxes = document.querySelectorAll('#camposPersonalizados input[type="checkbox"]:checked');
            const lista = document.getElementById('camposSelecionadosList');
            const empty = document.getElementById('camposSelecionadosEmpty');
            
            lista.innerHTML = '';
            
            // Se não há checkboxes marcados
            if (checkboxes.length === 0) {
                lista.style.display = 'none';
                empty.style.display = 'block';
                camposOrdenados = [];
                return;
            }
            
            lista.style.display = 'block';
            empty.style.display = 'none';
            
            // Se já temos uma ordem definida, usa ela
            if (camposOrdenados.length > 0) {
                // Remove campos que foram desmarcados
                camposOrdenados = camposOrdenados.filter(campo => {
                    const checkbox = document.querySelector(`#campo_personalizado_${campo}`);
                    return checkbox && checkbox.checked;
                });
                
                // Adiciona novos campos marcados ao final
                checkboxes.forEach(checkbox => {
                    if (!camposOrdenados.includes(checkbox.value)) {
                        camposOrdenados.push(checkbox.value);
                    }
                });
            } else {
                // Se não há ordem definida, cria uma nova
                camposOrdenados = [];
                checkboxes.forEach(checkbox => {
                    camposOrdenados.push(checkbox.value);
                });
            }
            
            // Renderiza a lista na ordem correta
            camposOrdenados.forEach((campo, index) => {
                const checkbox = document.querySelector(`#campo_personalizado_${campo}`);
                if (checkbox && checkbox.checked) {
                    const label = checkbox.parentElement.querySelector('label').textContent.trim();
                    
                    const li = document.createElement('li');
                    li.className = 'campo-selecionado-item';
                    li.draggable = true;
                    li.dataset.campo = campo;
                    li.innerHTML = `
                        <span class="campo-drag-handle">
                            <i class="fas fa-grip-vertical"></i>
                        </span>
                        <span class="campo-selecionado-numero">${index + 1}</span>
                        <span class="campo-selecionado-nome">${label}</span>
                        <button type="button" class="campo-selecionado-remove" onclick="removerCampoSelecionado('${campo}')">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    // Event listeners para drag and drop
                    li.addEventListener('dragstart', handleDragStart);
                    li.addEventListener('dragend', handleDragEnd);
                    li.addEventListener('dragover', handleDragOver);
                    li.addEventListener('drop', handleDrop);
                    li.addEventListener('dragenter', handleDragEnter);
                    li.addEventListener('dragleave', handleDragLeave);
                    
                    lista.appendChild(li);
                }
            });
        }

        // Remove campo da seleção
        function removerCampoSelecionado(campo) {
            const checkbox = document.querySelector(`#camposPersonalizados input[value="${campo}"]`);
            if (checkbox) {
                checkbox.checked = false;
                atualizarCamposSelecionados();
            }
        }

        // Drag and Drop handlers
        let draggedElement = null;

        function handleDragStart(e) {
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            
            // Remove todas as classes de drag-over
            document.querySelectorAll('.campo-selecionado-item').forEach(item => {
                item.classList.remove('drag-over');
            });
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDragEnter(e) {
            this.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            this.classList.remove('drag-over');
        }

        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (draggedElement !== this) {
                const lista = document.getElementById('camposSelecionadosList');
                const allItems = [...lista.querySelectorAll('.campo-selecionado-item')];
                const draggedIndex = allItems.indexOf(draggedElement);
                const targetIndex = allItems.indexOf(this);
                
                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedElement, this);
                }
                
                // Atualiza array de campos ordenados
                atualizarOrdemCampos();
            }
            
            return false;
        }

        // Atualiza array de campos após reordenação
        function atualizarOrdemCampos() {
            const items = document.querySelectorAll('.campo-selecionado-item');
            camposOrdenados = [];
            
            items.forEach((item, index) => {
                camposOrdenados.push(item.dataset.campo);
                // Atualiza número
                item.querySelector('.campo-selecionado-numero').textContent = index + 1;
            });
        }
    </script>
</body>
</html>