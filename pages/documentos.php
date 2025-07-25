<?php
/**
 * Página de Gerenciamento de Documentos com Fluxo de Assinatura
 * pages/documentos.php
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Documentos.php';

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
$page_title = 'Documentos - ASSEGO';

// Busca estatísticas de documentos
try {
    $documentos = new Documentos();
    $stats = $documentos->getEstatisticas();
    $statsFluxo = $documentos->getEstatisticasFluxo();
    
    $totalDocumentos = $stats['total_documentos'] ?? 0;
    $docsVerificados = $stats['verificados'] ?? 0;
    $docsPendentes = $stats['pendentes'] ?? 0;
    $uploadsHoje = $stats['uploads_hoje'] ?? 0;

} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de documentos: " . $e->getMessage());
    $totalDocumentos = $docsVerificados = $docsPendentes = $uploadsHoje = 0;
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
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

        /* Scrollbar personalizada */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
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

        /* Main Content */
        .main-wrapper {
            min-height: 100vh;
            background: var(--gray-100);
        }

        /* Header */
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

        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--white);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(220, 53, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
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

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        /* Page Header */
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

        /* Content Tabs */
        .content-tabs {
            background: var(--white);
            border-radius: 16px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .content-tab-list {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
        }

        .content-tab {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray-600);
            transition: all 0.2s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-tab:hover {
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .content-tab.active {
            background: var(--primary);
            color: var(--white);
        }

        .content-tab-badge {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 0.125rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .content-tab.active .content-tab-badge {
            background: rgba(255, 255, 255, 0.3);
            color: var(--white);
        }

        /* Tab Panels */
        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
            animation: fadeIn 0.3s ease;
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

        /* Actions Bar */
        .actions-bar {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 0.625rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
            padding-left: 0.5rem;
        }

        .filter-select,
        .filter-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
            cursor: pointer;
            width: 100%;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--white);
        }

        .actions-row {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

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

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-success:hover {
            background: #00A845;
        }

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .btn-warning:hover {
            background: #E68900;
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #E6332A;
        }

        /* Document Cards Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .document-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-100);
        }

        .document-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--gray-200);
        }

        .document-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .document-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .document-icon.pdf {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .document-icon.image {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }

        .document-icon.doc {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .document-icon.default {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .document-info {
            flex: 1;
            min-width: 0;
        }

        .document-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .document-subtitle {
            font-size: 0.8125rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .document-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            width: 16px;
            text-align: center;
            color: var(--gray-400);
        }

        .document-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-100);
        }

        /* Status Badges */
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge.digitalizado {
            background: var(--info);
            color: white;
        }

        .status-badge.aguardando-assinatura {
            background: var(--warning);
            color: white;
        }

        .status-badge.assinado {
            background: var(--success);
            color: white;
        }

        .status-badge.finalizado {
            background: var(--gray-600);
            color: white;
        }

        /* Fluxo Progress */
        .fluxo-progress {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 0.75rem;
            margin: 1rem 0;
        }

        .fluxo-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .fluxo-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }

        .fluxo-step-icon {
            width: 32px;
            height: 32px;
            background: var(--gray-300);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 0.875rem;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .fluxo-step.completed .fluxo-step-icon {
            background: var(--success);
        }

        .fluxo-step.active .fluxo-step-icon {
            background: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 86, 210, 0.2);
        }

        .fluxo-step-label {
            font-size: 0.625rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
            text-align: center;
        }

        .fluxo-line {
            position: absolute;
            top: 16px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: var(--gray-300);
            z-index: 1;
        }

        .fluxo-step:last-child .fluxo-line {
            display: none;
        }

        .fluxo-step.completed .fluxo-line {
            background: var(--success);
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            background: var(--gray-100);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .upload-area.dragging {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .upload-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .upload-subtitle {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gray-300);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 4px;
            width: 16px;
            height: 16px;
            background: var(--white);
            border: 3px solid var(--primary);
            border-radius: 50%;
        }

        .timeline-content {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 1rem;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .timeline-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .timeline-date {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .timeline-description {
            font-size: 0.8125rem;
            color: var(--gray-600);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h5 {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        /* Modal Custom */
        .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 700;
            color: var(--dark);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-select {
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 210, 0.1);
        }

        /* File List */
        .file-item {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .file-item-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .file-item-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-item-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .file-item-size {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .btn-remove {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            color: var(--gray-400);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-remove:hover {
            background: var(--danger);
            color: var(--white);
        }

        /* Dropdown Menu */
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

        /* Loading */
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-tabs-modern {
                overflow-x: auto;
                justify-content: flex-start;
                padding: 0 1rem;
            }

            .nav-tab-link {
                min-width: 100px;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .user-info {
                display: none;
            }

            .content-tab-list {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header -->
        <header class="main-header">
            <div class="header-left">
                <div class="logo-section">
                    <div
                        style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                        A
                    </div>
                    <div>
                        <h1 class="logo-text" style="margin-bottom: -2px;">ASSEGO</h1>
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
                    <span class="notification-badge"></span>
                </button>
                <div class="user-menu" id="userMenu">
                    <div class="user-info">
                        <p class="user-name"><?php echo htmlspecialchars($usuarioLogado['nome']); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'Funcionário'); ?>
                        </p>
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
                <li class="nav-tab-item">
                    <a href="#" class="nav-tab-link active">
                        <div class="nav-tab-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <span class="nav-tab-text">Documentos</span>
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
                    <a href="relatorios.php" class="nav-tab-link">
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
            <!-- Page Title -->
            <div class="page-header mb-4" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Gerenciamento de Documentos</h1>
                        <p class="page-subtitle">Faça upload, visualize e gerencie documentos dos associados</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn-modern btn-secondary" data-bs-toggle="modal" data-bs-target="#fichaVirtualModal">
                            <i class="fas fa-file-alt"></i>
                            Gerar Ficha Virtual
                        </button>
                        <button class="btn-modern btn-primary" data-bs-toggle="modal" data-bs-target="#uploadFichaModal">
                            <i class="fas fa-file-upload"></i>
                            Upload Ficha Associação
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <?php if (isset($statsFluxo['por_status'])): ?>
                    <?php foreach ($statsFluxo['por_status'] as $status): ?>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value"><?php echo number_format($status['total'] ?? 0, 0, ',', '.'); ?></div>
                                    <div class="stat-label">
                                        <?php 
                                        $labels = [
                                            'DIGITALIZADO' => 'Aguardando Envio',
                                            'AGUARDANDO_ASSINATURA' => 'Para Assinatura',
                                            'ASSINADO' => 'Assinados',
                                            'FINALIZADO' => 'Finalizados'
                                        ];
                                        echo $labels[$status['status_fluxo']] ?? $status['status_fluxo'];
                                        ?>
                                    </div>
                                </div>
                                <div class="stat-icon <?php 
                                    echo match($status['status_fluxo']) {
                                        'DIGITALIZADO' => 'info',
                                        'AGUARDANDO_ASSINATURA' => 'warning',
                                        'ASSINADO' => 'success',
                                        'FINALIZADO' => 'primary',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <i class="fas <?php 
                                        echo match($status['status_fluxo']) {
                                            'DIGITALIZADO' => 'fa-upload',
                                            'AGUARDANDO_ASSINATURA' => 'fa-clock',
                                            'ASSINADO' => 'fa-check',
                                            'FINALIZADO' => 'fa-flag-checkered',
                                            default => 'fa-file'
                                        };
                                    ?>"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Content Tabs -->
            <div class="content-tabs" data-aos="fade-up" data-aos-delay="100">
                <div class="content-tab-list">
                    <button class="content-tab active" data-tab="fluxo">
                        <i class="fas fa-exchange-alt"></i>
                        Fluxo de Assinatura
                        <span class="content-tab-badge" id="badgeFluxo">0</span>
                    </button>
                    <button class="content-tab" data-tab="todos">
                        <i class="fas fa-folder"></i>
                        Todos os Documentos
                        <span class="content-tab-badge" id="badgeTodos"><?php echo $totalDocumentos; ?></span>
                    </button>
                    <button class="content-tab" data-tab="pendentes">
                        <i class="fas fa-clock"></i>
                        Pendentes
                        <span class="content-tab-badge" id="badgePendentes"><?php echo $docsPendentes; ?></span>
                    </button>
                    <button class="content-tab" data-tab="verificados">
                        <i class="fas fa-check-circle"></i>
                        Verificados
                        <span class="content-tab-badge" id="badgeVerificados"><?php echo $docsVerificados; ?></span>
                    </button>
                </div>
            </div>

            <!-- Tab Panels -->
            <div id="tabPanels">
                <!-- Fluxo de Assinatura Panel -->
                <div class="tab-panel active" id="fluxo-panel">
                    <!-- Filters -->
                    <div class="actions-bar" data-aos="fade-up" data-aos-delay="200">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label class="filter-label">Status do Fluxo</label>
                                <select class="filter-select" id="filtroStatusFluxo">
                                    <option value="">Todos</option>
                                    <option value="DIGITALIZADO">Aguardando Envio</option>
                                    <option value="AGUARDANDO_ASSINATURA">Para Assinatura</option>
                                    <option value="ASSINADO">Assinados</option>
                                    <option value="FINALIZADO">Finalizados</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Origem</label>
                                <select class="filter-select" id="filtroOrigem">
                                    <option value="">Todas</option>
                                    <option value="FISICO">Físico</option>
                                    <option value="VIRTUAL">Virtual</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Buscar Associado</label>
                                <input type="text" class="filter-input" id="filtroBuscaFluxo" placeholder="Nome ou CPF">
                            </div>
                        </div>

                        <div class="actions-row">
                            <button class="btn-modern btn-secondary" onclick="limparFiltrosFluxo()">
                                <i class="fas fa-eraser"></i>
                                Limpar Filtros
                            </button>
                            <button class="btn-modern btn-primary" onclick="aplicarFiltrosFluxo()">
                                <i class="fas fa-filter"></i>
                                Aplicar Filtros
                            </button>
                        </div>
                    </div>

                    <!-- Documents in Flow -->
                    <div class="documents-grid" id="documentosFluxoList" data-aos="fade-up" data-aos-delay="300">
                        <!-- Documentos em fluxo serão carregados aqui -->
                    </div>
                </div>

                <!-- Todos os Documentos Panel -->
                <div class="tab-panel" id="todos-panel">
                    <!-- Filters -->
                    <div class="actions-bar" data-aos="fade-up" data-aos-delay="200">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label class="filter-label">Associado</label>
                                <input type="text" class="filter-input" id="filtroAssociado" placeholder="Nome ou CPF">
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Tipo de Documento</label>
                                <select class="filter-select" id="filtroTipo">
                                    <option value="">Todos</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Status</label>
                                <select class="filter-select" id="filtroStatus">
                                    <option value="">Todos</option>
                                    <option value="1">Verificado</option>
                                    <option value="0">Pendente</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Período</label>
                                <select class="filter-select" id="filtroPeriodo">
                                    <option value="">Todo período</option>
                                    <option value="hoje">Hoje</option>
                                    <option value="semana">Esta semana</option>
                                    <option value="mes">Este mês</option>
                                    <option value="ano">Este ano</option>
                                </select>
                            </div>
                        </div>

                        <div class="actions-row">
                            <button class="btn-modern btn-secondary" onclick="limparFiltros()">
                                <i class="fas fa-eraser"></i>
                                Limpar Filtros
                            </button>
                            <button class="btn-modern btn-primary" onclick="aplicarFiltros()">
                                <i class="fas fa-filter"></i>
                                Aplicar Filtros
                            </button>
                        </div>
                    </div>

                    <!-- Documents Grid -->
                    <div class="documents-grid" id="documentosList">
                        <!-- Documentos serão carregados aqui -->
                    </div>
                </div>

                <!-- Pendentes Panel -->
                <div class="tab-panel" id="pendentes-panel">
                    <div class="documents-grid" id="documentosPendentesList">
                        <!-- Documentos pendentes serão carregados aqui -->
                    </div>
                </div>

                <!-- Verificados Panel -->
                <div class="tab-panel" id="verificados-panel">
                    <div class="documents-grid" id="documentosVerificadosList">
                        <!-- Documentos verificados serão carregados aqui -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Upload de Ficha de Associação -->
    <div class="modal fade" id="uploadFichaModal" tabindex="-1" aria-labelledby="uploadFichaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadFichaModalLabel">
                        <i class="fas fa-file-upload me-2" style="color: var(--primary);"></i>
                        Upload de Ficha de Associação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadFichaForm">
                        <div class="mb-4">
                            <label class="form-label">Associado *</label>
                            <select class="form-select" id="fichaAssociadoSelect" required>
                                <option value="">Selecione o associado</option>
                            </select>
                            <small class="text-muted">Selecione o associado para o qual está fazendo upload da ficha</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Origem do Documento *</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipoOrigem" id="origemFisico" value="FISICO" checked>
                                    <label class="form-check-label" for="origemFisico">
                                        <i class="fas fa-paper-plane me-1"></i>
                                        Físico (Digitalizado)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipoOrigem" id="origemVirtual" value="VIRTUAL">
                                    <label class="form-check-label" for="origemVirtual">
                                        <i class="fas fa-laptop me-1"></i>
                                        Virtual (Gerado no sistema)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Arquivo da Ficha *</label>
                            <div class="upload-area" id="uploadFichaArea">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <h6 class="upload-title">Arraste o arquivo aqui ou clique para selecionar</h6>
                                <p class="upload-subtitle">Formato aceito: PDF</p>
                                <p class="upload-subtitle">Tamanho máximo: 10MB</p>
                                <input type="file" id="fichaFileInput" class="d-none" accept=".pdf">
                            </div>
                        </div>

                        <div id="fichaFilesList" class="mb-4"></div>

                        <div class="mb-4">
                            <label class="form-label">Observações (opcional)</label>
                            <textarea class="form-control" id="fichaObservacao" rows="3"
                                placeholder="Adicione observações sobre a ficha..."></textarea>
                        </div>

                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>Fluxo de Assinatura:</strong><br>
                                Após o upload, a ficha será enviada para a presidência assinar e depois retornará ao comercial.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-modern btn-primary" onclick="realizarUploadFicha()">
                        <i class="fas fa-upload me-2"></i>
                        Fazer Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Gerar Ficha Virtual -->
    <div class="modal fade" id="fichaVirtualModal" tabindex="-1" aria-labelledby="fichaVirtualModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fichaVirtualModalLabel">
                        <i class="fas fa-file-alt me-2" style="color: var(--primary);"></i>
                        Gerar Ficha Virtual de Associação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="fichaVirtualForm">
                        <div class="mb-4">
                            <label class="form-label">Associado *</label>
                            <select class="form-select" id="virtualAssociadoSelect" required>
                                <option value="">Selecione o associado</option>
                            </select>
                            <small class="text-muted">A ficha será gerada com os dados do associado selecionado</small>
                        </div>

                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>Processo Virtual:</strong><br>
                                A ficha será gerada automaticamente com os dados do associado e seguirá o mesmo fluxo de assinatura.
                            </div>
                        </div>

                        <div id="previewAssociado" class="d-none">
                            <h6 class="mb-3">Dados que serão incluídos na ficha:</h6>
                            <div class="bg-light rounded p-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <strong>Nome:</strong>
                                        <p class="mb-0" id="previewNome">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>CPF:</strong>
                                        <p class="mb-0" id="previewCPF">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>RG:</strong>
                                        <p class="mb-0" id="previewRG">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Email:</strong>
                                        <p class="mb-0" id="previewEmail">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-modern btn-primary" onclick="gerarFichaVirtual()">
                        <i class="fas fa-file-alt me-2"></i>
                        Gerar Ficha
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico do Fluxo -->
    <div class="modal fade" id="historicoModal" tabindex="-1" aria-labelledby="historicoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historicoModalLabel">
                        <i class="fas fa-history me-2" style="color: var(--primary);"></i>
                        Histórico do Documento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="historicoContent">
                        <!-- Timeline será carregada aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Assinatura -->
    <div class="modal fade" id="assinaturaModal" tabindex="-1" aria-labelledby="assinaturaModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assinaturaModalLabel">
                        <i class="fas fa-signature me-2" style="color: var(--primary);"></i>
                        Assinar Documento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assinaturaForm">
                        <input type="hidden" id="assinaturaDocumentoId">
                        
                        <div class="mb-4">
                            <label class="form-label">Arquivo Assinado (opcional)</label>
                            <div class="upload-area small" id="uploadAssinaturaArea" style="padding: 2rem;">
                                <i class="fas fa-file-signature upload-icon" style="font-size: 2rem;"></i>
                                <h6 class="upload-title" style="font-size: 1rem;">Upload do documento assinado</h6>
                                <p class="upload-subtitle" style="font-size: 0.75rem;">Se desejar, faça upload do PDF assinado</p>
                                <input type="file" id="assinaturaFileInput" class="d-none" accept=".pdf">
                            </div>
                        </div>

                        <div id="assinaturaFilesList" class="mb-4"></div>

                        <div class="mb-4">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="assinaturaObservacao" rows="3"
                                placeholder="Adicione observações sobre a assinatura..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-modern btn-success" onclick="assinarDocumento()">
                        <i class="fas fa-check me-2"></i>
                        Confirmar Assinatura
                    </button>
                </div>
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
        let arquivoFichaSelecionado = null;
        let arquivoAssinaturaSelecionado = null;
        let tiposDocumentos = [];
        let tabAtual = 'fluxo';

        // Inicialização
        $(document).ready(function () {
            carregarEstatisticas();
            carregarTiposDocumentos();
            carregarDocumentosFluxo();
            carregarAssociados();
            configurarUploadFicha();
            configurarUploadAssinatura();
            configurarUserMenu();
            configurarTabs();
        });

        // Configurar tabs
        function configurarTabs() {
            $('.content-tab').on('click', function() {
                const tab = $(this).data('tab');
                
                // Atualizar tabs
                $('.content-tab').removeClass('active');
                $(this).addClass('active');
                
                // Atualizar panels
                $('.tab-panel').removeClass('active');
                $(`#${tab}-panel`).addClass('active');
                
                // Carregar conteúdo da tab
                tabAtual = tab;
                switch(tab) {
                    case 'fluxo':
                        carregarDocumentosFluxo();
                        break;
                    case 'todos':
                        carregarDocumentos();
                        break;
                    case 'pendentes':
                        carregarDocumentos({verificado: 'nao'}, 'documentosPendentesList');
                        break;
                    case 'verificados':
                        carregarDocumentos({verificado: 'sim'}, 'documentosVerificadosList');
                        break;
                }
            });
        }

        // Configurar menu do usuário
        function configurarUserMenu() {
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');

            if (userMenu && userDropdown) {
                userMenu.addEventListener('click', function (e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                // Fecha dropdown ao clicar fora
                document.addEventListener('click', function () {
                    userDropdown.classList.remove('show');
                });
            }
        }

        // Carregar estatísticas
        function carregarEstatisticas() {
            $.get('../api/documentos/documentos_estatisticas.php', function (response) {
                if (response.status === 'success') {
                    // Atualizar badges se necessário
                    if (response.data.fluxo && response.data.fluxo.por_status) {
                        let totalFluxo = 0;
                        response.data.fluxo.por_status.forEach(status => {
                            totalFluxo += parseInt(status.total);
                        });
                        $('#badgeFluxo').text(totalFluxo);
                    }
                }
            });
        }

        // Carregar tipos de documentos
        function carregarTiposDocumentos() {
            $.get('../api/documentos/documentos_tipos.php', function (response) {
                if (response.status === 'success') {
                    tiposDocumentos = response.tipos_documentos;

                    // Preencher select de filtros
                    const filtroTipo = $('#filtroTipo');
                    filtroTipo.empty().append('<option value="">Todos</option>');

                    tiposDocumentos.forEach(tipo => {
                        if (tipo.codigo !== 'ficha_associacao') { // Não mostrar ficha no filtro geral
                            filtroTipo.append(`<option value="${tipo.codigo}">${tipo.nome}</option>`);
                        }
                    });
                }
            });
        }

        // Carregar documentos em fluxo
        function carregarDocumentosFluxo(filtros = {}) {
            const container = $('#documentosFluxoList');
            
            // Mostra loading
            container.html(`
                <div class="col-12 text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando documentos em fluxo...</p>
                </div>
            `);

            $.get('../api/documentos/documentos_fluxo_listar.php', filtros, function (response) {
                if (response.status === 'success') {
                    renderizarDocumentosFluxo(response.data);
                } else {
                    container.html(`
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h5>Erro ao carregar documentos</h5>
                                <p>${response.message || 'Tente novamente mais tarde'}</p>
                            </div>
                        </div>
                    `);
                }
            }).fail(function() {
                container.html(`
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-wifi-slash"></i>
                            <h5>Erro de conexão</h5>
                            <p>Verifique sua conexão com a internet</p>
                        </div>
                    </div>
                `);
            });
        }

        // Renderizar documentos em fluxo
        function renderizarDocumentosFluxo(documentos) {
            const container = $('#documentosFluxoList');
            container.empty();

            if (documentos.length === 0) {
                container.html(`
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h5>Nenhum documento em fluxo</h5>
                            <p>Faça upload de fichas de associação para iniciar o processo</p>
                        </div>
                    </div>
                `);
                return;
            }

            documentos.forEach(doc => {
                const statusClass = doc.status_fluxo.toLowerCase().replace('_', '-');
                const cardHtml = `
                    <div class="document-card" data-aos="fade-up">
                        <span class="status-badge ${statusClass}">
                            <i class="fas fa-${getStatusIcon(doc.status_fluxo)} me-1"></i>
                            ${doc.status_descricao}
                        </span>
                        
                        <div class="document-header">
                            <div class="document-icon pdf">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h6 class="document-title">Ficha de Associação</h6>
                                <p class="document-subtitle">${doc.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Físico'}</p>
                            </div>
                        </div>
                        
                        <div class="document-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span>${doc.associado_nome}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-id-card"></i>
                                <span>CPF: ${formatarCPF(doc.associado_cpf)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-building"></i>
                                <span>${doc.departamento_atual_nome || 'Não definido'}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>${formatarData(doc.data_upload)}</span>
                            </div>
                            ${doc.dias_em_processo > 0 ? `
                                <div class="meta-item">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span>${doc.dias_em_processo} dias em processo</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Progress do Fluxo -->
                        <div class="fluxo-progress">
                            <div class="fluxo-steps">
                                <div class="fluxo-step ${doc.status_fluxo !== 'DIGITALIZADO' ? 'completed' : 'active'}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="fluxo-step-label">Digitalizado</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'AGUARDANDO_ASSINATURA' ? 'active' : (doc.status_fluxo === 'ASSINADO' || doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-signature"></i>
                                    </div>
                                    <div class="fluxo-step-label">Assinatura</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'ASSINADO' ? 'active' : (doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="fluxo-step-label">Assinado</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'FINALIZADO' ? 'completed' : ''}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                    <div class="fluxo-step-label">Finalizado</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="document-actions">
                            <button class="btn-modern btn-primary btn-sm" onclick="downloadDocumento(${doc.id})" title="Download">
                                <i class="fas fa-download"></i>
                                Download
                            </button>
                            
                            ${getAcoesFluxo(doc)}
                            
                            <button class="btn-modern btn-secondary btn-sm" onclick="verHistorico(${doc.id})" title="Histórico">
                                <i class="fas fa-history"></i>
                                Histórico
                            </button>
                        </div>
                    </div>
                `;

                container.append(cardHtml);
            });
        }

        // Obter ações do fluxo baseado no status
        function getAcoesFluxo(doc) {
            let acoes = '';
            
            switch(doc.status_fluxo) {
                case 'DIGITALIZADO':
                    acoes = `
                        <button class="btn-modern btn-warning btn-sm" onclick="enviarParaAssinatura(${doc.id})" title="Enviar para Assinatura">
                            <i class="fas fa-paper-plane"></i>
                            Enviar p/ Assinatura
                        </button>
                    `;
                    break;
                    
                case 'AGUARDANDO_ASSINATURA':
                    // Verificar se usuário tem permissão para assinar
                    <?php if ($auth->isDiretor() || $usuarioLogado['departamento_id'] == 2): ?>
                    acoes = `
                        <button class="btn-modern btn-success btn-sm" onclick="abrirModalAssinatura(${doc.id})" title="Assinar">
                            <i class="fas fa-signature"></i>
                            Assinar
                        </button>
                    `;
                    <?php endif; ?>
                    break;
                    
                case 'ASSINADO':
                    acoes = `
                        <button class="btn-modern btn-success btn-sm" onclick="finalizarProcesso(${doc.id})" title="Finalizar">
                            <i class="fas fa-flag-checkered"></i>
                            Finalizar
                        </button>
                    `;
                    break;
            }
            
            return acoes;
        }

        // Obter ícone do status
        function getStatusIcon(status) {
            const icons = {
                'DIGITALIZADO': 'upload',
                'AGUARDANDO_ASSINATURA': 'clock',
                'ASSINADO': 'check',
                'FINALIZADO': 'flag-checkered'
            };
            return icons[status] || 'file';
        }

        // Enviar para assinatura
        function enviarParaAssinatura(documentoId) {
            if (confirm('Deseja enviar este documento para assinatura na presidência?')) {
                $.ajax({
                    url: '../api/documentos/documentos_enviar_assinatura.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ 
                        documento_id: documentoId,
                        observacao: 'Documento enviado para assinatura'
                    }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Documento enviado para assinatura com sucesso!');
                            carregarDocumentosFluxo();
                            carregarEstatisticas();
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao enviar documento para assinatura');
                    }
                });
            }
        }

        // Abrir modal de assinatura
        function abrirModalAssinatura(documentoId) {
            $('#assinaturaDocumentoId').val(documentoId);
            $('#assinaturaObservacao').val('');
            $('#assinaturaFilesList').empty();
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaModal').modal('show');
        }

        // Assinar documento
        function assinarDocumento() {
            const documentoId = $('#assinaturaDocumentoId').val();
            const observacao = $('#assinaturaObservacao').val();
            
            const formData = new FormData();
            formData.append('documento_id', documentoId);
            formData.append('observacao', observacao);
            
            if (arquivoAssinaturaSelecionado) {
                formData.append('arquivo_assinado', arquivoAssinaturaSelecionado);
            }
            
            // Mostra loading no botão
            const btnAssinar = event.target;
            const btnText = btnAssinar.innerHTML;
            btnAssinar.disabled = true;
            btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';
            
            $.ajax({
                url: '../api/documentos/documentos_assinar.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.status === 'success') {
                        alert('Documento assinado com sucesso!');
                        $('#assinaturaModal').modal('hide');
                        carregarDocumentosFluxo();
                        carregarEstatisticas();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function () {
                    alert('Erro ao assinar documento');
                },
                complete: function () {
                    btnAssinar.disabled = false;
                    btnAssinar.innerHTML = btnText;
                }
            });
        }

        // Finalizar processo
        function finalizarProcesso(documentoId) {
            if (confirm('Deseja finalizar o processo deste documento?\n\nO documento será marcado como concluído.')) {
                $.ajax({
                    url: '../api/documentos/documentos_finalizar.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ 
                        documento_id: documentoId,
                        observacao: 'Processo finalizado'
                    }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Processo finalizado com sucesso!');
                            carregarDocumentosFluxo();
                            carregarEstatisticas();
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao finalizar processo');
                    }
                });
            }
        }

        // Ver histórico
        function verHistorico(documentoId) {
            $.get('../api/documentos/documentos_historico_fluxo.php', { documento_id: documentoId }, function (response) {
                if (response.status === 'success') {
                    renderizarHistorico(response.data);
                    $('#historicoModal').modal('show');
                } else {
                    alert('Erro ao carregar histórico');
                }
            });
        }

        // Renderizar histórico
        function renderizarHistorico(historico) {
            const container = $('#historicoContent');
            container.empty();
            
            if (historico.length === 0) {
                container.html('<p class="text-muted text-center">Nenhum histórico disponível</p>');
                return;
            }
            
            const timeline = $('<div class="timeline"></div>');
            
            historico.forEach(item => {
                const timelineItem = `
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">${item.status_novo}</h6>
                                <span class="timeline-date">${formatarData(item.data_acao)}</span>
                            </div>
                            <p class="timeline-description mb-2">${item.observacao}</p>
                            <p class="timeline-description text-muted mb-0">
                                <small>
                                    Por: ${item.funcionario_nome}<br>
                                    ${item.dept_origem_nome ? `De: ${item.dept_origem_nome}<br>` : ''}
                                    ${item.dept_destino_nome ? `Para: ${item.dept_destino_nome}` : ''}
                                </small>
                            </p>
                        </div>
                    </div>
                `;
                timeline.append(timelineItem);
            });
            
            container.append(timeline);
        }

        // Configurar área de upload de ficha
        function configurarUploadFicha() {
            const uploadArea = document.getElementById('uploadFichaArea');
            const fileInput = document.getElementById('fichaFileInput');

            // Clique para selecionar
            uploadArea.addEventListener('click', () => fileInput.click());

            // Arrastar e soltar
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragging');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragging');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragging');
                handleFichaFile(e.dataTransfer.files[0]);
            });

            // Seleção de arquivo
            fileInput.addEventListener('change', (e) => {
                handleFichaFile(e.target.files[0]);
            });
        }

        // Configurar área de upload de assinatura
        function configurarUploadAssinatura() {
            const uploadArea = document.getElementById('uploadAssinaturaArea');
            const fileInput = document.getElementById('assinaturaFileInput');

            // Clique para selecionar
            uploadArea.addEventListener('click', () => fileInput.click());

            // Arrastar e soltar
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragging');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragging');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragging');
                handleAssinaturaFile(e.dataTransfer.files[0]);
            });

            // Seleção de arquivo
            fileInput.addEventListener('change', (e) => {
                handleAssinaturaFile(e.target.files[0]);
            });
        }

        // Processar arquivo de ficha
        function handleFichaFile(file) {
            if (!file) return;
            
            // Verificar se é PDF
            if (file.type !== 'application/pdf') {
                alert('Por favor, selecione apenas arquivos PDF');
                return;
            }
            
            arquivoFichaSelecionado = file;
            
            const filesList = $('#fichaFilesList');
            filesList.empty();
            
            filesList.append(`
                <div class="file-item">
                    <div class="file-item-info">
                        <div class="file-item-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <div class="file-item-name">${file.name}</div>
                            <div class="file-item-size">${formatBytes(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove" onclick="removerArquivoFicha()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
        }

        // Processar arquivo de assinatura
        function handleAssinaturaFile(file) {
            if (!file) return;
            
            // Verificar se é PDF
            if (file.type !== 'application/pdf') {
                alert('Por favor, selecione apenas arquivos PDF');
                return;
            }
            
            arquivoAssinaturaSelecionado = file;
            
            const filesList = $('#assinaturaFilesList');
            filesList.empty();
            
            filesList.append(`
                <div class="file-item">
                    <div class="file-item-info">
                        <div class="file-item-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <div class="file-item-name">${file.name}</div>
                            <div class="file-item-size">${formatBytes(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove" onclick="removerArquivoAssinatura()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
        }

        // Remover arquivo de ficha
        function removerArquivoFicha() {
            arquivoFichaSelecionado = null;
            $('#fichaFilesList').empty();
            $('#fichaFileInput').val('');
        }

        // Remover arquivo de assinatura
        function removerArquivoAssinatura() {
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaFilesList').empty();
            $('#assinaturaFileInput').val('');
        }

        // Realizar upload de ficha
        function realizarUploadFicha() {
            const associadoId = $('#fichaAssociadoSelect').val();
            const tipoOrigem = $('input[name="tipoOrigem"]:checked').val();
            const observacao = $('#fichaObservacao').val();

            if (!associadoId || !arquivoFichaSelecionado) {
                alert('Por favor, preencha todos os campos obrigatórios');
                return;
            }

            const formData = new FormData();
            formData.append('associado_id', associadoId);
            formData.append('tipo_origem', tipoOrigem);
            formData.append('observacao', observacao);
            formData.append('documento', arquivoFichaSelecionado);

            // Mostra loading no botão
            const btnUpload = event.target;
            const btnText = btnUpload.innerHTML;
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';

            $.ajax({
                url: '../api/documentos/documentos_ficha_upload.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.status === 'success') {
                        alert('Ficha de associação enviada com sucesso!\n\nEla seguirá o fluxo de assinatura.');
                        $('#uploadFichaModal').modal('hide');
                        $('#uploadFichaForm')[0].reset();
                        arquivoFichaSelecionado = null;
                        $('#fichaFilesList').empty();
                        carregarDocumentosFluxo();
                        carregarEstatisticas();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function () {
                    alert('Erro ao fazer upload. Por favor, tente novamente.');
                },
                complete: function () {
                    btnUpload.disabled = false;
                    btnUpload.innerHTML = btnText;
                }
            });
        }

        // Gerar ficha virtual
        function gerarFichaVirtual() {
            const associadoId = $('#virtualAssociadoSelect').val();
            
            if (!associadoId) {
                alert('Por favor, selecione um associado');
                return;
            }
            
            if (!confirm('Confirma a geração da ficha virtual?\n\nA ficha será gerada com os dados atuais do associado.')) {
                return;
            }
            
            // Mostra loading no botão
            const btnGerar = event.target;
            const btnText = btnGerar.innerHTML;
            btnGerar.disabled = true;
            btnGerar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando...';
            
            $.ajax({
                url: '../api/documentos/documentos_gerar_ficha_virtual.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ associado_id: associadoId }),
                success: function (response) {
                    if (response.status === 'success') {
                        alert('Ficha virtual gerada com sucesso!\n\nAgora você pode fazer o upload dela.');
                        $('#fichaVirtualModal').modal('hide');
                        
                        // Abrir modal de upload com o associado já selecionado
                        $('#fichaAssociadoSelect').val(associadoId);
                        $('input[name="tipoOrigem"][value="VIRTUAL"]').prop('checked', true);
                        $('#uploadFichaModal').modal('show');
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function () {
                    alert('Erro ao gerar ficha virtual');
                },
                complete: function () {
                    btnGerar.disabled = false;
                    btnGerar.innerHTML = btnText;
                }
            });
        }

        // Atualizar preview do associado
        $('#virtualAssociadoSelect').on('change', function() {
            const associadoId = $(this).val();
            
            if (!associadoId) {
                $('#previewAssociado').addClass('d-none');
                return;
            }
            
            // Buscar dados do associado selecionado
            const option = $(this).find('option:selected');
            const texto = option.text();
            
            // Extrair nome e CPF do texto da opção
            const partes = texto.split(' - CPF: ');
            const nome = partes[0];
            const cpf = partes[1] || '';
            
            $('#previewNome').text(nome);
            $('#previewCPF').text(cpf);
            $('#previewRG').text('-'); // Seria necessário buscar via API
            $('#previewEmail').text('-'); // Seria necessário buscar via API
            
            $('#previewAssociado').removeClass('d-none');
        });

        // Carregar associados
        function carregarAssociados() {
            $.get('../api/carregar_associados.php', function (response) {
                if (response.status === 'success') {
                    const selectFicha = $('#fichaAssociadoSelect');
                    const selectVirtual = $('#virtualAssociadoSelect');
                    
                    selectFicha.empty().append('<option value="">Selecione o associado</option>');
                    selectVirtual.empty().append('<option value="">Selecione o associado</option>');

                    response.dados.forEach(associado => {
                        const cpfFormatado = formatarCPF(associado.cpf);
                        const option = `<option value="${associado.id}">${associado.nome} - CPF: ${cpfFormatado}</option>`;
                        selectFicha.append(option);
                        selectVirtual.append(option);
                    });
                }
            });
        }

        // Carregar documentos (tab todos)
        function carregarDocumentos(filtros = {}, containerId = 'documentosList') {
            const container = $('#' + containerId);
            
            // Mostra loading
            container.html(`
                <div class="col-12 text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando documentos...</p>
                </div>
            `);

            $.get('../api/documentos/documentos_listar.php', filtros, function (response) {
                if (response.status === 'success') {
                    renderizarDocumentos(response.data, containerId);
                } else {
                    container.html(`
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h5>Erro ao carregar documentos</h5>
                                <p>${response.message || 'Tente novamente mais tarde'}</p>
                            </div>
                        </div>
                    `);
                }
            }).fail(function() {
                container.html(`
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-wifi-slash"></i>
                            <h5>Erro de conexão</h5>
                            <p>Verifique sua conexão com a internet</p>
                        </div>
                    </div>
                `);
            });
        }

        // Renderizar documentos
        function renderizarDocumentos(documentos, containerId) {
            const container = $('#' + containerId);
            container.empty();

            if (documentos.length === 0) {
                container.html(`
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h5>Nenhum documento encontrado</h5>
                            <p>Não há documentos com os filtros selecionados</p>
                        </div>
                    </div>
                `);
                return;
            }

            documentos.forEach(doc => {
                // Pular fichas de associação na listagem geral
                if (doc.tipo_documento === 'ficha_associacao') return;
                
                const iconClass = getIconClass(doc.extensao);
                const badge = doc.verificado == 1
                    ? '<span class="status-badge assinado"><i class="fas fa-check me-1"></i>Verificado</span>'
                    : '<span class="status-badge aguardando-assinatura"><i class="fas fa-clock me-1"></i>Pendente</span>';

                const cardHtml = `
                    <div class="document-card" data-aos="fade-up">
                        ${badge}
                        <div class="document-header">
                            <div class="document-icon ${iconClass}">
                                <i class="fas fa-file-${iconClass}"></i>
                            </div>
                            <div class="document-info">
                                <h6 class="document-title">${doc.tipo_documento_nome}</h6>
                                <p class="document-subtitle">${doc.nome_arquivo}</p>
                            </div>
                        </div>
                        
                        <div class="document-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span>${doc.associado_nome}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>${formatarData(doc.data_upload)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-file"></i>
                                <span>${doc.tamanho_formatado}</span>
                            </div>
                            ${doc.observacao ? `
                                <div class="meta-item">
                                    <i class="fas fa-comment"></i>
                                    <span>${doc.observacao}</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <div class="document-actions">
                            <button class="btn-modern btn-primary btn-sm" onclick="downloadDocumento(${doc.id})" title="Download">
                                <i class="fas fa-download"></i>
                                Download
                            </button>
                            ${doc.verificado == 0 ? `
                                <button class="btn-modern btn-success btn-sm" onclick="verificarDocumento(${doc.id})" title="Verificar">
                                    <i class="fas fa-check"></i>
                                    Verificar
                                </button>
                            ` : ''}
                            <button class="btn-modern btn-danger btn-sm" onclick="excluirDocumento(${doc.id})" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;

                container.append(cardHtml);
            });
        }

        // Funções auxiliares
        function getIconClass(extensao) {
            const ext = extensao?.toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) return 'image';
            if (ext === 'pdf') return 'pdf';
            if (['doc', 'docx'].includes(ext)) return 'word';
            if (['xls', 'xlsx'].includes(ext)) return 'excel';
            return 'alt';
        }

        function formatarData(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatarCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function downloadDocumento(id) {
            window.open('../api/documentos/documentos_download.php?id=' + id, '_blank');
        }

        function verificarDocumento(id) {
            if (confirm('Confirma a verificação deste documento?')) {
                $.ajax({
                    url: '../api/documentos/documentos_verificar.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ documento_id: id }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Documento verificado com sucesso!');
                            carregarDocumentos();
                            carregarEstatisticas();
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao verificar documento');
                    }
                });
            }
        }

        function excluirDocumento(id) {
            if (confirm('Tem certeza que deseja excluir este documento?\n\nEsta ação não pode ser desfeita!')) {
                $.ajax({
                    url: '../api/documentos/documentos_excluir.php?id=' + id,
                    type: 'DELETE',
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Documento excluído com sucesso!');
                            carregarDocumentos();
                            carregarEstatisticas();
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao excluir documento');
                    }
                });
            }
        }

        // Aplicar filtros do fluxo
        function aplicarFiltrosFluxo() {
            const filtros = {};

            const status = $('#filtroStatusFluxo').val();
            if (status) filtros.status = status;

            const origem = $('#filtroOrigem').val();
            if (origem) filtros.origem = origem;

            const busca = $('#filtroBuscaFluxo').val().trim();
            if (busca) filtros.busca = busca;

            carregarDocumentosFluxo(filtros);
        }

        // Limpar filtros do fluxo
        function limparFiltrosFluxo() {
            $('#filtroStatusFluxo').val('');
            $('#filtroOrigem').val('');
            $('#filtroBuscaFluxo').val('');
            carregarDocumentosFluxo();
        }

        // Aplicar filtros gerais
        function aplicarFiltros() {
            const filtros = {};

            const busca = $('#filtroAssociado').val().trim();
            if (busca) filtros.busca = busca;

            const tipo = $('#filtroTipo').val();
            if (tipo) filtros.tipo_documento = tipo;

            const status = $('#filtroStatus').val();
            if (status !== '') {
                filtros.verificado = status === '1' ? 'sim' : 'nao';
            }

            const periodo = $('#filtroPeriodo').val();
            if (periodo) filtros.periodo = periodo;

            carregarDocumentos(filtros);
        }

        // Limpar filtros gerais
        function limparFiltros() {
            $('#filtroAssociado').val('');
            $('#filtroTipo').val('');
            $('#filtroStatus').val('');
            $('#filtroPeriodo').val('');
            carregarDocumentos();
        }

        // Fecha modal quando pressiona ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('.modal').modal('hide');
            }
        });

        // Limpa formulários quando modais são fechados
        $('#uploadFichaModal').on('hidden.bs.modal', function () {
            $('#uploadFichaForm')[0].reset();
            arquivoFichaSelecionado = null;
            $('#fichaFilesList').empty();
        });

        $('#fichaVirtualModal').on('hidden.bs.modal', function () {
            $('#fichaVirtualForm')[0].reset();
            $('#previewAssociado').addClass('d-none');
        });

        $('#assinaturaModal').on('hidden.bs.modal', function () {
            $('#assinaturaForm')[0].reset();
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaFilesList').empty();
        });

        console.log('✓ Sistema de documentos com fluxo de assinatura carregado!');
    </script>

</body>

</html>