<?php
/**
 * Página de Gerenciamento do Fluxo de Assinatura - VERSÃO PADRONIZADA
 * pages/documentos_fluxo.php
 * 
 * Esta página gerencia o fluxo de assinatura dos documentos
 * anexados durante o pré-cadastro
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
$page_title = 'Fluxo de Assinatura - ASSEGO';

// Busca estatísticas de documentos em fluxo
try {
    $documentos = new Documentos();
    $statsFluxo = $documentos->getEstatisticasFluxo();
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de fluxo: " . $e->getMessage());
}

// Organizar dados do fluxo
$aguardandoEnvio = 0;
$naPresidencia = 0;
$assinados = 0;
$finalizados = 0;

if (isset($statsFluxo['por_status'])) {
    foreach ($statsFluxo['por_status'] as $status) {
        switch ($status['status_fluxo']) {
            case 'DIGITALIZADO':
                $aguardandoEnvio = $status['total'] ?? 0;
                break;
            case 'AGUARDANDO_ASSINATURA':
                $naPresidencia = $status['total'] ?? 0;
                break;
            case 'ASSINADO':
                $assinados = $status['total'] ?? 0;
                break;
            case 'FINALIZADO':
                $finalizados = $status['total'] ?? 0;
                break;
        }
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'documentos',
    'notificationCount' => $aguardandoEnvio,
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

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./estilizacao/style.css">

    <!-- Estilos Personalizados Premium -->
    <style>
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #003d94;
            --secondary: #6c757d;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            --gradient-danger: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-warning: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            --shadow-premium: 0 10px 40px rgba(0, 86, 210, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            position: relative;
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 2rem;
            margin-left: 0;
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            padding: 0 0 1rem 0;
            position: relative;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title i {
            color: var(--primary);
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
            font-weight: 500;
        }

        /* Stats Grid Premium */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.75rem;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 86, 210, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-2xl);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-lg);
        }

        .stat-icon::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 18px;
            background: inherit;
            filter: blur(10px);
            opacity: 0.4;
            z-index: -1;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
        }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-trend {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }

        .stat-trend.up {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-trend.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        /* Documents Container Premium */
        .documents-container {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .documents-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(180deg, rgba(0, 86, 210, 0.03) 0%, transparent 100%);
            pointer-events: none;
        }

        .documents-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid transparent;
            background: linear-gradient(90deg, var(--light) 0%, var(--light) 50%, transparent 50%);
            background-size: 20px 2px;
            background-repeat: repeat-x;
            background-position: bottom;
        }

        .documents-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .documents-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }

        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 86, 210, 0.1);
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }

        .filter-select, .filter-input {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 86, 210, 0.1);
        }

        .actions-row {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        /* Documents Grid */
        .documents-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .document-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .document-card:hover {
            border-color: var(--primary);
            transform: translateX(4px);
            box-shadow: var(--shadow-lg);
        }

        .document-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .document-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .document-info {
            flex: 1;
        }

        .document-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .document-subtitle {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge.digitalizado {
            background: linear-gradient(135deg, rgba(23, 162, 184, 0.1) 0%, rgba(19, 132, 150, 0.1) 100%);
            color: #138496;
        }

        .status-badge.aguardando-assinatura {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(253, 126, 20, 0.1) 100%);
            color: #fd7e14;
        }

        .status-badge.assinado {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(32, 201, 151, 0.1) 100%);
            color: #20c997;
        }

        .status-badge.finalizado {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.1) 0%, rgba(102, 16, 242, 0.1) 100%);
            color: #6610f2;
        }

        .document-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        .meta-item i {
            color: var(--primary);
            width: 16px;
            text-align: center;
        }

        /* Fluxo Progress */
        .fluxo-progress {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .fluxo-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .fluxo-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .fluxo-step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .fluxo-step.active .fluxo-step-icon {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
        }

        .fluxo-step.completed .fluxo-step-icon {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .fluxo-step-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
        }

        .fluxo-line {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }

        .fluxo-step.completed .fluxo-line {
            background: var(--success);
        }

        .fluxo-step:last-child .fluxo-line {
            display: none;
        }

        .document-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-premium {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-md);
            cursor: pointer;
        }

        .btn-premium::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-premium:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary-premium {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 86, 210, 0.3);
            color: white;
        }

        .btn-success-premium {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
        }

        .btn-success-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-warning-premium {
            background: linear-gradient(135deg, var(--warning) 0%, #dc2626 100%);
            color: white;
        }

        .btn-warning-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
            color: white;
        }

        .btn-secondary-premium {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
        }

        .btn-secondary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
            color: white;
        }

        .btn-modern {
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-modern.btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Toast Container */
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
        }

        /* Alert Informativo */
        .alert-premium {
            padding: 1.25rem;
            border-radius: 12px;
            border: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }

        .alert-info-premium {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
            color: #1e40af;
            border-left: 4px solid var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h5 {
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        /* Loading */
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(0, 86, 210, 0.1);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        /* Modal Custom */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-2xl);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.75rem;
            border: none;
        }

        .modal-title {
            font-size: 1.375rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-body {
            padding: 2rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-top: 1px solid rgba(0, 86, 210, 0.1);
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding: 1rem 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary), var(--primary-light));
        }

        .timeline-item {
            position: relative;
            padding-left: 50px;
            margin-bottom: 2rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 11px;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 4px solid var(--primary);
        }

        .timeline-content {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .timeline-title {
            font-weight: 700;
            color: var(--dark);
        }

        .timeline-date {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: rgba(0, 86, 210, 0.02);
        }

        .upload-area.dragging {
            border-color: var(--primary);
            background: rgba(0, 86, 210, 0.05);
        }

        /* Animações */
        @keyframes fadeIn {
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
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .document-meta {
                grid-template-columns: 1fr;
            }

            .fluxo-steps {
                flex-direction: column;
                gap: 1rem;
            }

            .fluxo-line {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header animate__animated animate__fadeInDown">
                <h1 class="page-title">
                    Fluxo de Assinatura de Documentos
                </h1>
                <p class="page-subtitle">
                    Gerencie o processo de assinatura das fichas de filiação com eficiência e controle
                </p>
            </div>

            <!-- Alert Informativo -->
            <div class="alert-premium alert-info-premium animate__animated animate__fadeIn">
                <i class="fas fa-info-circle fa-lg"></i>
                <div>
                    <strong>Como funciona o fluxo:</strong><br>
                    1. Ficha anexada no pré-cadastro → 2. Envio para presidência → 3. Assinatura → 4. Retorno ao comercial → 5. Aprovação do pré-cadastro
                </div>
            </div>

            <?php if (isset($_GET['novo']) && $_GET['novo'] == '1'): ?>
            <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Pré-cadastro criado com sucesso!</strong>
                A ficha de filiação foi anexada e está aguardando envio para assinatura.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="100">
                    <span class="stat-trend pending">
                        <i class="fas fa-hourglass-half me-1"></i>
                        Pendente
                    </span>
                    <div class="stat-icon primary">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($aguardandoEnvio, 0, ',', '.'); ?></div>
                    <div class="stat-label">Aguardando Envio</div>
                </div>

                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="200">
                    <span class="stat-trend pending">
                        <i class="fas fa-clock me-1"></i>
                        Em processo
                    </span>
                    <div class="stat-icon warning">
                        <i class="fas fa-signature"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($naPresidencia, 0, ',', '.'); ?></div>
                    <div class="stat-label">Na Presidência</div>
                </div>

                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="300">
                    <span class="stat-trend up">
                        <i class="fas fa-check me-1"></i>
                        Prontos
                    </span>
                    <div class="stat-icon success">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($assinados, 0, ',', '.'); ?></div>
                    <div class="stat-label">Assinados</div>
                </div>

                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="400">
                    <span class="stat-trend up">
                        <i class="fas fa-trophy me-1"></i>
                        Concluídos
                    </span>
                    <div class="stat-icon danger">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($finalizados, 0, ',', '.'); ?></div>
                    <div class="stat-label">Finalizados</div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="filters-bar animate__animated animate__fadeIn" data-aos="fade-up" data-aos-delay="500">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Status do Fluxo</label>
                        <select class="filter-select" id="filtroStatusFluxo">
                            <option value="">Todos os Status</option>
                            <option value="DIGITALIZADO">Aguardando Envio</option>
                            <option value="AGUARDANDO_ASSINATURA">Na Presidência</option>
                            <option value="ASSINADO">Assinados</option>
                            <option value="FINALIZADO">Finalizados</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Buscar Associado</label>
                        <input type="text" class="filter-input" id="filtroBuscaFluxo"
                            placeholder="Nome ou CPF do associado">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Período</label>
                        <select class="filter-select" id="filtroPeriodo">
                            <option value="">Todo período</option>
                            <option value="hoje">Hoje</option>
                            <option value="semana">Esta semana</option>
                            <option value="mes">Este mês</option>
                        </select>
                    </div>
                </div>

                <div class="actions-row">
                    <button class="btn-premium btn-secondary-premium" onclick="limparFiltros()">
                        <i class="fas fa-eraser me-2"></i>
                        Limpar
                    </button>
                    <button class="btn-premium btn-primary-premium" onclick="aplicarFiltros()">
                        <i class="fas fa-filter me-2"></i>
                        Filtrar
                    </button>
                </div>
            </div>

            <!-- Documents Container -->
            <div class="documents-container animate__animated animate__fadeIn" data-aos="fade-up" data-aos-delay="600">
                <div class="documents-header">
                    <h3 class="documents-title">
                        <i class="fas fa-file-alt"></i>
                        Documentos em Fluxo
                    </h3>
                </div>

                <div class="documents-list" id="documentosFluxoList">
                    <!-- Documentos serão carregados aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico -->
    <div class="modal fade" id="historicoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history"></i>
                        Histórico do Documento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historicoContent">
                        <!-- Timeline será carregada aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-premium btn-secondary-premium" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Assinatura -->
    <div class="modal fade" id="assinaturaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-signature"></i>
                        Assinar Documento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assinaturaForm">
                        <input type="hidden" id="assinaturaDocumentoId">

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Arquivo Assinado (opcional)</label>
                            <div class="upload-area" id="uploadAssinaturaArea">
                                <i class="fas fa-file-signature mb-3" style="font-size: 2.5rem; color: var(--primary);"></i>
                                <h6 class="mb-2">Upload do documento assinado</h6>
                                <p class="mb-0 text-muted small">Arraste o PDF ou clique para selecionar</p>
                                <input type="file" id="assinaturaFileInput" class="d-none" accept=".pdf">
                            </div>
                        </div>

                        <div id="assinaturaFilesList" class="mb-4"></div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Observações</label>
                            <textarea class="form-control" id="assinaturaObservacao" rows="3"
                                placeholder="Adicione observações sobre a assinatura..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-premium btn-secondary-premium" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn-premium btn-success-premium" onclick="assinarDocumento()">
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

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                once: true,
                offset: 50
            });

            carregarDocumentosFluxo();
            configurarUploadAssinatura();
        });

        // Variáveis globais
        let arquivoAssinaturaSelecionado = null;
        let filtrosAtuais = {};

        // Carregar documentos em fluxo
        function carregarDocumentosFluxo(filtros = {}) {
            const container = document.getElementById('documentosFluxoList');
            
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando documentos em fluxo...</p>
                </div>
            `;

            $.get('../api/documentos/documentos_fluxo_listar.php', filtros, function(response) {
                if (response.status === 'success') {
                    renderizarDocumentosFluxo(response.data);
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5>Erro ao carregar documentos</h5>
                            <p>${response.message || 'Tente novamente mais tarde'}</p>
                        </div>
                    `;
                }
            }).fail(function() {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-wifi-slash"></i>
                        <h5>Erro de conexão</h5>
                        <p>Verifique sua conexão com a internet</p>
                    </div>
                `;
            });
        }

        function renderizarDocumentosFluxo(documentos) {
            const container = document.getElementById('documentosFluxoList');
            container.innerHTML = '';

            if (documentos.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h5>Nenhum documento em fluxo</h5>
                        <p>Os documentos anexados durante o pré-cadastro aparecerão aqui</p>
                    </div>
                `;
                return;
            }

            documentos.forEach(doc => {
                const statusClass = doc.status_fluxo.toLowerCase().replace('_', '-');
                const cardHtml = `
                    <div class="document-card" data-aos="fade-up">
                        <div class="document-header">
                            <div class="document-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h6 class="document-title">Ficha de Filiação</h6>
                                <p class="document-subtitle">${doc.tipo_origem === 'VIRTUAL' ? 'Gerada no Sistema' : 'Digitalizada'}</p>
                            </div>
                            <div class="ms-auto">
                                <span class="status-badge ${statusClass}">
                                    <i class="fas fa-${getStatusIcon(doc.status_fluxo)}"></i>
                                    ${doc.status_descricao}
                                </span>
                            </div>
                        </div>
                        
                        <div class="document-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><strong>${doc.associado_nome}</strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-id-card"></i>
                                <span>CPF: ${formatarCPF(doc.associado_cpf)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-building"></i>
                                <span>${doc.departamento_atual_nome || 'Comercial'}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>${formatarData(doc.data_upload)}</span>
                            </div>
                            ${doc.dias_em_processo > 0 ? `
                            <div class="meta-item">
                                <i class="fas fa-hourglass-half"></i>
                                <span class="text-warning"><strong>${doc.dias_em_processo} dias em processo</strong></span>
                            </div>
                            ` : ''}
                        </div>
                        
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
                            <button class="btn-modern btn-primary-premium btn-sm" onclick="downloadDocumento(${doc.id})">
                                <i class="fas fa-download me-1"></i>
                                Baixar
                            </button>
                            
                            ${getAcoesFluxo(doc)}
                            
                            <button class="btn-modern btn-secondary-premium btn-sm" onclick="verHistorico(${doc.id})">
                                <i class="fas fa-history me-1"></i>
                                Histórico
                            </button>
                        </div>
                    </div>
                `;

                container.innerHTML += cardHtml;
            });
        }

        function getStatusIcon(status) {
            const icons = {
                'DIGITALIZADO': 'upload',
                'AGUARDANDO_ASSINATURA': 'clock',
                'ASSINADO': 'check',
                'FINALIZADO': 'flag-checkered'
            };
            return icons[status] || 'file';
        }

        function getAcoesFluxo(doc) {
            let acoes = '';

            switch (doc.status_fluxo) {
                case 'DIGITALIZADO':
                    acoes = `
                        <button class="btn-modern btn-warning-premium btn-sm" onclick="enviarParaAssinatura(${doc.id})">
                            <i class="fas fa-paper-plane me-1"></i>
                            Enviar
                        </button>
                    `;
                    break;

                case 'AGUARDANDO_ASSINATURA':
                    <?php if ($auth->isDiretor() || $usuarioLogado['departamento_id'] == 2): ?>
                    acoes = `
                        <button class="btn-modern btn-success-premium btn-sm" onclick="abrirModalAssinatura(${doc.id})">
                            <i class="fas fa-signature me-1"></i>
                            Assinar
                        </button>
                    `;
                    <?php endif; ?>
                    break;

                case 'ASSINADO':
                    acoes = `
                        <button class="btn-modern btn-primary-premium btn-sm" onclick="finalizarProcesso(${doc.id})">
                            <i class="fas fa-flag-checkered me-1"></i>
                            Finalizar
                        </button>
                    `;
                    break;

                case 'FINALIZADO':
                    acoes = `
                        <button class="btn-modern btn-success-premium btn-sm" disabled>
                            <i class="fas fa-check-circle me-1"></i>
                            Concluído
                        </button>
                    `;
                    break;
            }

            return acoes;
        }

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
                    success: function(response) {
                        if (response.status === 'success') {
                            showToast('Documento enviado com sucesso!', 'success');
                            carregarDocumentosFluxo(filtrosAtuais);
                        } else {
                            showToast('Erro: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Erro ao enviar documento', 'danger');
                    }
                });
            }
        }

        function abrirModalAssinatura(documentoId) {
            document.getElementById('assinaturaDocumentoId').value = documentoId;
            document.getElementById('assinaturaObservacao').value = '';
            document.getElementById('assinaturaFilesList').innerHTML = '';
            arquivoAssinaturaSelecionado = null;
            
            const modal = new bootstrap.Modal(document.getElementById('assinaturaModal'));
            modal.show();
        }

        function assinarDocumento() {
            const documentoId = document.getElementById('assinaturaDocumentoId').value;
            const observacao = document.getElementById('assinaturaObservacao').value;

            const formData = new FormData();
            formData.append('documento_id', documentoId);
            formData.append('observacao', observacao || 'Documento assinado pela presidência');

            if (arquivoAssinaturaSelecionado) {
                formData.append('arquivo_assinado', arquivoAssinaturaSelecionado);
            }

            $.ajax({
                url: '../api/documentos/documentos_assinar.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.status === 'success') {
                        showToast('Documento assinado com sucesso!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('assinaturaModal')).hide();
                        carregarDocumentosFluxo(filtrosAtuais);
                    } else {
                        showToast('Erro: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    showToast('Erro ao assinar documento', 'danger');
                }
            });
        }

        function finalizarProcesso(documentoId) {
            if (confirm('Deseja finalizar o processo deste documento?')) {
                $.ajax({
                    url: '../api/documentos/documentos_finalizar.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Processo finalizado'
                    }),
                    success: function(response) {
                        if (response.status === 'success') {
                            showToast('Processo finalizado com sucesso!', 'success');
                            carregarDocumentosFluxo(filtrosAtuais);
                        } else {
                            showToast('Erro: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Erro ao finalizar processo', 'danger');
                    }
                });
            }
        }

        function verHistorico(documentoId) {
            $.get('../api/documentos/documentos_historico_fluxo.php', {
                documento_id: documentoId
            }, function(response) {
                if (response.status === 'success') {
                    renderizarHistorico(response.data);
                    const modal = new bootstrap.Modal(document.getElementById('historicoModal'));
                    modal.show();
                } else {
                    showToast('Erro ao carregar histórico', 'danger');
                }
            });
        }

        function renderizarHistorico(historico) {
            const container = document.getElementById('historicoContent');
            
            if (historico.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum histórico disponível</p>';
                return;
            }

            let timelineHtml = '<div class="timeline">';
            
            historico.forEach(item => {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">${item.status_novo}</h6>
                                <span class="timeline-date">${formatarData(item.data_acao)}</span>
                            </div>
                            <p class="mb-2">${item.observacao}</p>
                            <p class="text-muted mb-0">
                                <small>
                                    Por: ${item.funcionario_nome}<br>
                                    ${item.dept_origem_nome ? `De: ${item.dept_origem_nome}<br>` : ''}
                                    ${item.dept_destino_nome ? `Para: ${item.dept_destino_nome}` : ''}
                                </small>
                            </p>
                        </div>
                    </div>
                `;
            });
            
            timelineHtml += '</div>';
            container.innerHTML = timelineHtml;
        }

        function configurarUploadAssinatura() {
            const uploadArea = document.getElementById('uploadAssinaturaArea');
            const fileInput = document.getElementById('assinaturaFileInput');

            if (!uploadArea || !fileInput) return;

            uploadArea.addEventListener('click', () => fileInput.click());

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

            fileInput.addEventListener('change', (e) => {
                handleAssinaturaFile(e.target.files[0]);
            });
        }

        function handleAssinaturaFile(file) {
            if (!file) return;

            if (file.type !== 'application/pdf') {
                showToast('Por favor, selecione apenas arquivos PDF', 'warning');
                return;
            }

            arquivoAssinaturaSelecionado = file;

            const filesList = document.getElementById('assinaturaFilesList');
            filesList.innerHTML = `
                <div class="alert alert-info d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-file-pdf me-2"></i>
                        <strong>${file.name}</strong> (${formatBytes(file.size)})
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removerArquivoAssinatura()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }

        function removerArquivoAssinatura() {
            arquivoAssinaturaSelecionado = null;
            document.getElementById('assinaturaFilesList').innerHTML = '';
            document.getElementById('assinaturaFileInput').value = '';
        }

        function aplicarFiltros() {
            filtrosAtuais = {};

            const status = document.getElementById('filtroStatusFluxo').value;
            if (status) filtrosAtuais.status = status;

            const busca = document.getElementById('filtroBuscaFluxo').value.trim();
            if (busca) filtrosAtuais.busca = busca;

            const periodo = document.getElementById('filtroPeriodo').value;
            if (periodo) filtrosAtuais.periodo = periodo;

            carregarDocumentosFluxo(filtrosAtuais);
        }

        function limparFiltros() {
            document.getElementById('filtroStatusFluxo').value = '';
            document.getElementById('filtroBuscaFluxo').value = '';
            document.getElementById('filtroPeriodo').value = '';
            filtrosAtuais = {};
            carregarDocumentosFluxo();
        }

        function downloadDocumento(id) {
            window.open('../api/documentos/documentos_download.php?id=' + id, '_blank');
        }

        // Funções auxiliares
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

        // Sistema de Toast
        function showToast(message, type = 'success') {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;

            const container = document.querySelector('.toast-container');
            const toastElement = document.createElement('div');
            toastElement.innerHTML = toastHTML;
            container.appendChild(toastElement.firstElementChild);

            const toast = new bootstrap.Toast(container.lastElementChild);
            toast.show();
        }

        // Auto-refresh a cada 30 segundos
        setInterval(function() {
            carregarDocumentosFluxo(filtrosAtuais);
        }, 30000);
    </script>
</body>

</html>