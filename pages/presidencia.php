<?php
/**
 * Página da Presidência - Assinatura de Documentos
 * pages/presidencia.php
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
$page_title = 'Presidência - ASSEGO';

// Busca estatísticas de documentos
try {
    $documentos = new Documentos();
    $statsPresidencia = $documentos->getEstatisticasPresidencia();
    
    $aguardandoAssinatura = $statsPresidencia['aguardando_assinatura'] ?? 0;
    $assinadosHoje = $statsPresidencia['assinados_hoje'] ?? 0;
    $assinadosMes = $statsPresidencia['assinados_mes'] ?? 0;
    $tempoMedio = $statsPresidencia['tempo_medio_assinatura'] ?? 0;

} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas da presidência: " . $e->getMessage());
    $aguardandoAssinatura = $assinadosHoje = $assinadosMes = $tempoMedio = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'avatar' => $usuarioLogado['avatar'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'presidencia',
    'notificationCount' => $aguardandoAssinatura,
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #0056D2;
            --primary-dark: #003F9E;
            --primary-light: #E8F0FF;
            --secondary: #6C757D;
            --success: #00C853;
            --danger: #FF3B30;
            --warning: #FF9500;
            --info: #00B8D4;
            --dark: #1C1C1E;
            --gray-100: #F8F9FA;
            --gray-200: #E9ECEF;
            --gray-300: #DEE2E6;
            --gray-400: #CED4DA;
            --gray-500: #ADB5BD;
            --gray-600: #6C757D;
            --gray-700: #495057;
            --gray-800: #343A40;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 24px;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--gray-100);
            color: var(--dark);
            overflow-x: hidden;
        }

        /* Main Content */
        .main-wrapper {
            min-height: 100vh;
            background: var(--gray-100);
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            position: relative;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: var(--border-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }

        .page-subtitle {
            font-size: 1.125rem;
            color: var(--gray-600);
            margin: 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
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
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
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
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
        }

        .stat-change {
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Quick Actions */
        .quick-actions {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .quick-actions-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-action-btn {
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-md);
            background: var(--gray-100);
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .quick-action-btn:hover {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .quick-action-icon {
            font-size: 1.5rem;
        }

        /* Documents Section */
        .documents-section {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title-icon {
            width: 40px;
            height: 40px;
            background: var(--warning);
            color: var(--white);
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }

        .section-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Filter Bar */
        .filter-bar {
            padding: 1rem 1.5rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input {
            flex: 1;
            min-width: 250px;
            padding: 0.625rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
        }

        .filter-select {
            padding: 0.625rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
        }

        /* Document List */
        .documents-list {
            padding: 0;
        }

        .document-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            transition: all 0.2s ease;
            position: relative;
        }

        .document-item:hover {
            background: var(--gray-100);
        }

        .document-item:last-child {
            border-bottom: none;
        }

        .document-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .document-icon-wrapper {
            width: 60px;
            height: 60px;
            background: var(--warning);
            background: linear-gradient(135deg, var(--warning), var(--primary));
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
            flex-shrink: 0;
            position: relative;
        }

        .document-icon-wrapper.urgent::after {
            content: '';
            position: absolute;
            top: -4px;
            right: -4px;
            width: 12px;
            height: 12px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--white);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 59, 48, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 59, 48, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 59, 48, 0);
            }
        }

        .document-info {
            flex: 1;
            min-width: 0;
        }

        .document-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .document-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .meta-item i {
            font-size: 0.875rem;
            color: var(--gray-400);
        }

        .document-status {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .document-status.waiting {
            background: var(--warning);
            color: var(--white);
        }

        .document-status.urgent {
            background: var(--danger);
            color: var(--white);
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .document-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .btn-action {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-action.primary {
            background: var(--primary);
            color: var(--white);
        }

        .btn-action.primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-action.secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-action.secondary:hover {
            background: var(--gray-300);
        }

        .btn-action.success {
            background: var(--success);
            color: var(--white);
        }

        .btn-action.success:hover {
            background: #00A845;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--gray-500);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .empty-state-description {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        /* Modal Styles */
        .modal-content {
            border-radius: var(--border-radius-lg);
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-body {
            padding: 2rem;
        }

        /* Document Preview */
        .document-preview {
            background: var(--gray-100);
            border-radius: var(--border-radius-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .preview-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-300);
        }

        .preview-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: var(--border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .preview-title {
            flex: 1;
        }

        .preview-title h5 {
            margin: 0;
            font-weight: 700;
            color: var(--dark);
        }

        .preview-title p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .preview-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 0.875rem;
            color: var(--dark);
            font-weight: 500;
        }

        /* Signature Section */
        .signature-section {
            background: var(--primary-light);
            border-radius: var(--border-radius-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 2px dashed var(--primary);
        }

        .signature-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .signature-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .signature-option {
            background: var(--white);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .signature-option:hover {
            border-color: var(--primary);
        }

        .signature-option.selected {
            border-color: var(--primary);
            background: var(--primary-light);
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
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 210, 0.1);
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--gray-300);
            border-radius: var(--border-radius-md);
            padding: 2rem;
            text-align: center;
            background: var(--gray-100);
            transition: all 0.3s ease;
            cursor: pointer;
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
            font-size: 2.5rem;
            color: var(--gray-400);
            margin-bottom: 0.75rem;
        }

        .upload-text {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        /* Loading */
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--gray-200);
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
            .content-area {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .document-content {
                flex-direction: column;
                text-align: center;
            }

            .document-actions {
                width: 100%;
                justify-content: center;
                margin-top: 1rem;
            }

            .preview-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-stamp"></i>
                    </div>
                    Área da Presidência
                </h1>
                <p class="page-subtitle">Gerencie e assine documentos de filiação dos associados</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $aguardandoAssinatura; ?></div>
                            <div class="stat-label">Aguardando Assinatura</div>
                            <?php if ($aguardandoAssinatura > 0): ?>
                            <div class="stat-change negative">
                                <i class="fas fa-exclamation-triangle"></i>
                                Requer atenção
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $assinadosHoje; ?></div>
                            <div class="stat-label">Assinados Hoje</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Produtividade
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $assinadosMes; ?></div>
                            <div class="stat-label">Assinados no Mês</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $tempoMedio; ?>h</div>
                            <div class="stat-label">Tempo Médio de Assinatura</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions" data-aos="fade-up" data-aos-delay="100">
                <h3 class="quick-actions-title">Ações Rápidas</h3>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="abrirRelatorios()">
                        <i class="fas fa-chart-line quick-action-icon"></i>
                        Relatórios
                    </button>
                    <button class="quick-action-btn" onclick="verHistorico()">
                        <i class="fas fa-history quick-action-icon"></i>
                        Histórico
                    </button>
                    <button class="quick-action-btn" onclick="configurarAssinatura()">
                        <i class="fas fa-cog quick-action-icon"></i>
                        Configurações
                    </button>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="documents-section" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h2 class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        Documentos Pendentes de Assinatura
                    </h2>
                    <div class="section-actions">
                        <button class="btn-action secondary" onclick="atualizarLista()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Buscar por nome ou CPF...">
                    <select class="filter-select" id="filterUrgencia">
                        <option value="">Todas as prioridades</option>
                        <option value="urgente">Urgente</option>
                        <option value="normal">Normal</option>
                    </select>
                    <select class="filter-select" id="filterOrigem">
                        <option value="">Todas as origens</option>
                        <option value="FISICO">Físico</option>
                        <option value="VIRTUAL">Virtual</option>
                    </select>
                </div>

                <!-- Documents List -->
                <div class="documents-list" id="documentsList">
                    <!-- Documentos serão carregados aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Assinatura -->
    <div class="modal fade" id="assinaturaModal" tabindex="-1" aria-labelledby="assinaturaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assinaturaModalLabel">
                        <i class="fas fa-signature" style="color: var(--primary);"></i>
                        Assinar Documento de Filiação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Preview do Documento -->
                    <div class="document-preview">
                        <div class="preview-header">
                            <div class="preview-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="preview-title">
                                <h5 id="previewTitulo">Ficha de Associação</h5>
                                <p id="previewSubtitulo">-</p>
                            </div>
                            <button class="btn-action secondary" onclick="visualizarDocumento()">
                                <i class="fas fa-eye"></i>
                                Visualizar
                            </button>
                        </div>
                        <div class="preview-details">
                            <div class="detail-item">
                                <span class="detail-label">Associado</span>
                                <span class="detail-value" id="previewAssociado">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">CPF</span>
                                <span class="detail-value" id="previewCPF">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Data de Upload</span>
                                <span class="detail-value" id="previewData">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Origem</span>
                                <span class="detail-value" id="previewOrigem">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Opções de Assinatura -->
                    <div class="signature-section">
                        <h5 class="signature-title">
                            <i class="fas fa-pen-fancy"></i>
                            Método de Assinatura
                        </h5>
                        <div class="signature-options">
                            <label class="signature-option selected">
                                <input type="radio" name="metodoAssinatura" value="digital" checked>
                                <strong>Assinatura Digital</strong>
                                <p class="mb-0 text-muted">Assinar digitalmente sem upload de arquivo</p>
                            </label>
                            <label class="signature-option">
                                <input type="radio" name="metodoAssinatura" value="upload">
                                <strong>Upload de Documento Assinado</strong>
                                <p class="mb-0 text-muted">Fazer upload do PDF já assinado</p>
                            </label>
                        </div>
                    </div>

                    <!-- Upload Area (mostrada apenas quando selecionado) -->
                    <div id="uploadSection" class="d-none">
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <p class="upload-text mb-0">
                                Arraste o arquivo aqui ou clique para selecionar<br>
                                <small class="text-muted">Apenas arquivos PDF (máx. 10MB)</small>
                            </p>
                            <input type="file" id="fileInput" class="d-none" accept=".pdf">
                        </div>
                        <div id="fileInfo" class="mt-3"></div>
                    </div>

                    <!-- Observações -->
                    <div class="mb-3">
                        <label class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="observacoes" rows="3" 
                            placeholder="Adicione observações sobre a assinatura..."></textarea>
                    </div>

                    <!-- Confirmação -->
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>
                            <strong>Importante:</strong> Ao assinar, você confirma que revisou o documento e 
                            autoriza o prosseguimento do processo de filiação.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="documentoId">
                    <button type="button" class="btn-action secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-action success" onclick="confirmarAssinatura()">
                        <i class="fas fa-check"></i>
                        Confirmar Assinatura
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Assinatura em Lote -->
    <div class="modal fade" id="assinaturaLoteModal" tabindex="-1" aria-labelledby="assinaturaLoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assinaturaLoteModalLabel">
                        <i class="fas fa-layer-group" style="color: var(--primary);"></i>
                        Assinatura em Lote
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Você está prestes a assinar múltiplos documentos de uma vez.
                        Certifique-se de ter revisado todos os documentos selecionados.
                    </div>

                    <div class="mb-4">
                        <h6>Documentos selecionados:</h6>
                        <div id="documentosLoteLista" class="mt-2">
                            <!-- Lista de documentos será carregada aqui -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observações para todos os documentos</label>
                        <textarea class="form-control" id="observacoesLote" rows="3" 
                            placeholder="Estas observações serão aplicadas a todos os documentos..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-action success" onclick="confirmarAssinaturaLote()">
                        <i class="fas fa-check-double"></i>
                        Assinar Todos
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
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais
        let documentosPendentes = [];
        let documentoSelecionado = null;
        let arquivoAssinado = null;

        // Inicialização
        $(document).ready(function() {
            carregarDocumentosPendentes();
            configurarFiltros();
            configurarUpload();
            configurarMetodoAssinatura();
            
            // Atualizar lista a cada 30 segundos
            setInterval(carregarDocumentosPendentes, 30000);
        });

        // Carregar documentos pendentes
        function carregarDocumentosPendentes() {
            const container = $('#documentsList');
            
            // Mostra loading
            container.html(`
                <div class="text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando documentos pendentes...</p>
                </div>
            `);

            $.ajax({
                url: '../api/documentos/documentos_presidencia_listar.php',
                type: 'GET',
                data: {
                    status: 'AGUARDANDO_ASSINATURA'
                },
                success: function(response) {
                    if (response.status === 'success') {
                        documentosPendentes = response.data;
                        renderizarDocumentos(response.data);
                        atualizarContadores();
                    } else {
                        mostrarErro('Erro ao carregar documentos');
                    }
                },
                error: function() {
                    mostrarErro('Erro de conexão ao carregar documentos');
                }
            });
        }

        // Renderizar documentos
        function renderizarDocumentos(documentos) {
            const container = $('#documentsList');
            container.empty();

            if (documentos.length === 0) {
                container.html(`
                    <div class="empty-state">
                        <i class="fas fa-check-circle empty-state-icon"></i>
                        <h5 class="empty-state-title">Tudo em dia!</h5>
                        <p class="empty-state-description">
                            Não há documentos pendentes de assinatura no momento.
                        </p>
                    </div>
                `);
                return;
            }

            documentos.forEach(doc => {
                const urgente = doc.dias_em_processo > 3;
                const itemHtml = `
                    <div class="document-item" data-doc-id="${doc.id}">
                        <div class="document-content">
                            <div class="document-icon-wrapper ${urgente ? 'urgent' : ''}">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h4 class="document-title">
                                    Ficha de Associação
                                    ${urgente ? '<span class="document-status urgent"><i class="fas fa-fire"></i> Urgente</span>' : '<span class="document-status waiting"><i class="fas fa-clock"></i> Aguardando</span>'}
                                </h4>
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
                                        <i class="fas fa-${doc.tipo_origem === 'VIRTUAL' ? 'laptop' : 'paper-plane'}"></i>
                                        <span>${doc.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Físico'}</span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>${formatarData(doc.data_upload)}</span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span>${doc.dias_em_processo} dias aguardando</span>
                                    </div>
                                </div>
                            </div>
                            <div class="document-actions">
                                <button class="btn-action secondary" onclick="visualizarDocumento(${doc.id})">
                                    <i class="fas fa-eye"></i>
                                    Visualizar
                                </button>
                                <button class="btn-action success" onclick="abrirModalAssinatura(${doc.id})">
                                    <i class="fas fa-signature"></i>
                                    Assinar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.append(itemHtml);
            });
        }

        // Configurar filtros
        function configurarFiltros() {
            $('#searchInput').on('input', function() {
                const termo = $(this).val().toLowerCase();
                filtrarDocumentos();
            });

            $('#filterUrgencia, #filterOrigem').on('change', function() {
                filtrarDocumentos();
            });
        }

        // Filtrar documentos
        function filtrarDocumentos() {
            const termo = $('#searchInput').val().toLowerCase();
            const urgencia = $('#filterUrgencia').val();
            const origem = $('#filterOrigem').val();

            let documentosFiltrados = documentosPendentes;

            // Filtro por termo de busca
            if (termo) {
                documentosFiltrados = documentosFiltrados.filter(doc => 
                    doc.associado_nome.toLowerCase().includes(termo) ||
                    doc.associado_cpf.includes(termo.replace(/\D/g, ''))
                );
            }

            // Filtro por urgência
            if (urgencia) {
                documentosFiltrados = documentosFiltrados.filter(doc => {
                    const isUrgente = doc.dias_em_processo > 3;
                    return urgencia === 'urgente' ? isUrgente : !isUrgente;
                });
            }

            // Filtro por origem
            if (origem) {
                documentosFiltrados = documentosFiltrados.filter(doc => 
                    doc.tipo_origem === origem
                );
            }

            renderizarDocumentos(documentosFiltrados);
        }

        // Abrir modal de assinatura
        function abrirModalAssinatura(documentoId) {
            documentoSelecionado = documentosPendentes.find(doc => doc.id === documentoId);
            
            if (!documentoSelecionado) {
                alert('Documento não encontrado');
                return;
            }

            // Preencher informações do documento
            $('#documentoId').val(documentoId);
            $('#previewAssociado').text(documentoSelecionado.associado_nome);
            $('#previewCPF').text(formatarCPF(documentoSelecionado.associado_cpf));
            $('#previewData').text(formatarData(documentoSelecionado.data_upload));
            $('#previewOrigem').text(documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Físico');
            $('#previewSubtitulo').text(documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Gerado pelo sistema' : 'Digitalizado');

            // Resetar formulário
            $('input[name="metodoAssinatura"][value="digital"]').prop('checked', true);
            $('#uploadSection').addClass('d-none');
            $('#observacoes').val('');
            $('#fileInfo').empty();
            arquivoAssinado = null;

            $('#assinaturaModal').modal('show');
        }

        // Configurar método de assinatura
        function configurarMetodoAssinatura() {
            $('input[name="metodoAssinatura"]').on('change', function() {
                const metodo = $(this).val();
                if (metodo === 'upload') {
                    $('#uploadSection').removeClass('d-none');
                } else {
                    $('#uploadSection').addClass('d-none');
                    arquivoAssinado = null;
                    $('#fileInfo').empty();
                }
            });
        }

        // Configurar upload
        function configurarUpload() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');

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
                handleFile(e.dataTransfer.files[0]);
            });

            // Seleção de arquivo
            fileInput.addEventListener('change', (e) => {
                handleFile(e.target.files[0]);
            });
        }

        // Processar arquivo
        function handleFile(file) {
            if (!file) return;

            if (file.type !== 'application/pdf') {
                alert('Por favor, selecione apenas arquivos PDF');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert('O arquivo deve ter no máximo 10MB');
                return;
            }

            arquivoAssinado = file;

            $('#fileInfo').html(`
                <div class="alert alert-success">
                    <i class="fas fa-file-pdf me-2"></i>
                    <strong>${file.name}</strong> (${formatBytes(file.size)})
                    <button type="button" class="btn-close float-end" onclick="removerArquivo()"></button>
                </div>
            `);
        }

        // Remover arquivo
        function removerArquivo() {
            arquivoAssinado = null;
            $('#fileInfo').empty();
            $('#fileInput').val('');
        }

        // Visualizar documento
        function visualizarDocumento(documentoId) {
            if (!documentoId && documentoSelecionado) {
                documentoId = documentoSelecionado.id;
            }
            
            window.open(`../api/documentos/documentos_download.php?id=${documentoId}`, '_blank');
        }

        // Confirmar assinatura
        function confirmarAssinatura() {
            const documentoId = $('#documentoId').val();
            const observacoes = $('#observacoes').val();
            const metodo = $('input[name="metodoAssinatura"]:checked').val();

            if (metodo === 'upload' && !arquivoAssinado) {
                alert('Por favor, selecione o arquivo assinado');
                return;
            }

            // Mostra loading no botão
            const btnAssinar = event.target;
            const btnText = btnAssinar.innerHTML;
            btnAssinar.disabled = true;
            btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';

            const formData = new FormData();
            formData.append('documento_id', documentoId);
            formData.append('observacao', observacoes);
            
            if (arquivoAssinado) {
                formData.append('arquivo_assinado', arquivoAssinado);
            }

            $.ajax({
                url: '../api/documentos/documentos_assinar.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.status === 'success') {
                        $('#assinaturaModal').modal('hide');
                        mostrarSucesso('Documento assinado com sucesso!');
                        carregarDocumentosPendentes();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao assinar documento');
                },
                complete: function() {
                    btnAssinar.disabled = false;
                    btnAssinar.innerHTML = btnText;
                }
            });
        }

        // Assinar todos
        function assinarTodos() {
            const documentosParaAssinar = documentosPendentes.filter(doc => {
                // Aqui você pode adicionar filtros, por exemplo, apenas urgentes
                return true;
            });

            if (documentosParaAssinar.length === 0) {
                alert('Não há documentos para assinar');
                return;
            }

            if (documentosParaAssinar.length > 10) {
                if (!confirm(`Você está prestes a assinar ${documentosParaAssinar.length} documentos. Deseja continuar?`)) {
                    return;
                }
            }

            // Mostrar modal de confirmação
            const listaHtml = documentosParaAssinar.map(doc => `
                <div class="mb-2">
                    <i class="fas fa-file-pdf text-danger me-2"></i>
                    ${doc.associado_nome} - CPF: ${formatarCPF(doc.associado_cpf)}
                </div>
            `).join('');

            $('#documentosLoteLista').html(listaHtml);
            $('#assinaturaLoteModal').modal('show');
        }

        // Confirmar assinatura em lote
        function confirmarAssinaturaLote() {
            const observacoes = $('#observacoesLote').val();
            const documentosIds = documentosPendentes.map(doc => doc.id);

            // Mostra loading
            const btnAssinar = event.target;
            const btnText = btnAssinar.innerHTML;
            btnAssinar.disabled = true;
            btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';

            $.ajax({
                url: '../api/documentos/documentos_assinar_lote.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    documentos_ids: documentosIds,
                    observacao: observacoes
                }),
                success: function(response) {
                    if (response.status === 'success') {
                        $('#assinaturaLoteModal').modal('hide');
                        mostrarSucesso(`${response.assinados} documentos assinados com sucesso!`);
                        carregarDocumentosPendentes();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function() {
                    alert('Erro ao assinar documentos');
                },
                complete: function() {
                    btnAssinar.disabled = false;
                    btnAssinar.innerHTML = btnText;
                }
            });
        }

        // Atualizar lista
        function atualizarLista() {
            carregarDocumentosPendentes();
        }

        // Abrir relatórios
        function abrirRelatorios() {
            // Implementar abertura de relatórios
            alert('Funcionalidade de relatórios em desenvolvimento');
        }

        // Ver histórico
        function verHistorico() {
            // Implementar visualização de histórico
            alert('Funcionalidade de histórico em desenvolvimento');
        }

        // Configurar assinatura
        function configurarAssinatura() {
            // Implementar configurações
            alert('Funcionalidade de configurações em desenvolvimento');
        }

        // Atualizar contadores
        function atualizarContadores() {
            // Atualizar o badge no header se necessário
            const totalPendentes = documentosPendentes.length;
            if (window.updateNotificationCount) {
                window.updateNotificationCount(totalPendentes);
            }
        }

        // Funções auxiliares
        function formatarCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
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

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function mostrarSucesso(mensagem) {
            // Aqui você pode implementar um toast ou notificação
            alert(mensagem);
        }

        function mostrarErro(mensagem) {
            $('#documentsList').html(`
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle empty-state-icon"></i>
                    <h5 class="empty-state-title">Erro</h5>
                    <p class="empty-state-description">${mensagem}</p>
                    <button class="btn-action primary mt-3" onclick="carregarDocumentosPendentes()">
                        <i class="fas fa-redo"></i>
                        Tentar Novamente
                    </button>
                </div>
            `);
        }

        // Atalhos de teclado
        $(document).on('keydown', function(e) {
            // ESC para fechar modais
            if (e.key === 'Escape') {
                $('.modal').modal('hide');
            }
            
            // Ctrl+R para atualizar lista
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                carregarDocumentosPendentes();
            }
        });

        console.log('✓ Sistema da Presidência carregado com sucesso!');
    </script>
</body>

</html>