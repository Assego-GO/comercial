<?php
/**
 * Formulário de Cadastro de Associados
 * pages/cadastroForm.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Cadastrar Novo Associado - ASSEGO';

// Verifica se é edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$associadoId = $isEdit ? intval($_GET['id']) : null;
$associadoData = null;

if ($isEdit) {
    $associados = new Associados();
    $associadoData = $associados->getById($associadoId);
    
    if (!$associadoData) {
        header('Location: dashboard.php');
        exit;
    }
    
    $page_title = 'Editar Associado - ASSEGO';
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

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- jQuery Mask -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

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
            
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.24);
            --shadow-lg: 0 10px 20px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.24);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.24);
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

        /* Breadcrumb */
        .breadcrumb-container {
            background: var(--white);
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .breadcrumb-custom {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .breadcrumb-custom li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .breadcrumb-custom a {
            color: var(--gray-600);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .breadcrumb-custom a:hover {
            color: var(--primary);
        }

        .breadcrumb-custom .active {
            color: var(--dark);
            font-weight: 600;
        }

        .breadcrumb-custom .separator {
            color: var(--gray-400);
            font-size: 0.75rem;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
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
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
            padding-left: 4rem;
        }

        /* Form Container */
        .form-container {
            background: var(--white);
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        /* Progress Bar */
        .progress-bar-container {
            padding: 2rem 2rem 0;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gray-200);
            z-index: 0;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
            z-index: 1;
        }

        .step {
            position: relative;
            z-index: 2;
            background: var(--white);
            padding: 0 0.5rem;
            text-align: center;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-200);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.3);
        }

        .step.completed .step-circle {
            background: var(--success);
            color: var(--white);
        }

        .step-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .step.active .step-label {
            color: var(--primary);
            font-weight: 600;
        }

        .step.completed .step-label {
            color: var(--success);
        }

        /* Form Content */
        .form-content {
            padding: 2rem;
        }

        .section-card {
            background: var(--gray-100);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: none;
        }

        .section-card.active {
            display: block;
            animation: fadeInUp 0.3s ease;
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

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .section-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .section-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin: 0.25rem 0 0 0;
        }

        /* Form Groups */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label .required {
            color: var(--danger);
        }

        .form-label .info-tooltip {
            font-size: 0.75rem;
            color: var(--gray-500);
            cursor: help;
        }

        .form-input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--white);
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
        }

        .form-input.error {
            border-color: var(--danger);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23636366' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-error {
            font-size: 0.75rem;
            color: var(--danger);
            margin-top: 0.25rem;
            display: none;
        }

        .form-input.error + .form-error {
            display: block;
        }

        /* Radio and Checkbox */
        .radio-group,
        .checkbox-group {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .radio-item,
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .radio-item input[type="radio"],
        .checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray-300);
            cursor: pointer;
            margin: 0;
        }

        .radio-item label,
        .checkbox-item label {
            font-size: 0.875rem;
            color: var(--gray-700);
            cursor: pointer;
            margin: 0;
        }

        /* Photo Upload */
        .photo-upload-container {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .photo-preview {
            width: 150px;
            height: 150px;
            border-radius: 16px;
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            border: 3px dashed var(--gray-300);
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview-placeholder {
            text-align: center;
            color: var(--gray-500);
        }

        .photo-preview-placeholder i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .photo-upload-btn {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--primary);
            background: var(--white);
            color: var(--primary);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .photo-upload-btn:hover {
            background: var(--primary);
            color: var(--white);
        }

        /* Address Fields */
        .address-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            margin-top: 1rem;
        }

        .cep-search-container {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }

        .btn-search-cep {
            padding: 0.875rem 1.5rem;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-search-cep:hover {
            background: var(--primary-dark);
        }

        .btn-search-cep:disabled {
            background: var(--gray-300);
            cursor: not-allowed;
        }

        /* Dependentes Section */
        .dependente-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            margin-bottom: 1rem;
            position: relative;
        }

        .dependente-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .dependente-number {
            font-weight: 600;
            color: var(--primary);
        }

        .btn-remove-dependente {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--danger);
            color: var(--white);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-remove-dependente:hover {
            background: var(--danger);
            transform: scale(1.1);
        }

        .btn-add-dependente {
            width: 100%;
            padding: 1rem;
            border: 2px dashed var(--gray-300);
            background: transparent;
            color: var(--gray-600);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-add-dependente:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            padding: 2rem;
            border-top: 1px solid var(--gray-200);
            background: var(--gray-100);
        }

        .btn-nav {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-back {
            background: var(--white);
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
        }

        .btn-back:hover {
            background: var(--gray-100);
        }

        .btn-next {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.25);
        }

        .btn-next:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 86, 210, 0.3);
        }

        .btn-submit {
            background: var(--success);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(0, 200, 83, 0.25);
        }

        .btn-submit:hover {
            background: #00a847;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 200, 83, 0.3);
        }

        .btn-nav:disabled {
            background: var(--gray-300);
            color: var(--gray-500);
            cursor: not-allowed;
            box-shadow: none;
        }

        /* Loading State */
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

        /* Success Message */
        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideInDown 0.3s ease;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: var(--success);
            color: var(--white);
        }

        .alert-error {
            background: var(--danger);
            color: var(--white);
        }

        .alert-warning {
            background: var(--warning);
            color: var(--white);
        }

        /* Tooltips */
        .tooltip-custom {
            position: absolute;
            background: var(--dark);
            color: var(--white);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1000;
        }

        .tooltip-custom.show {
            opacity: 1;
            visibility: visible;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .photo-upload-container {
                flex-direction: column;
            }

            .form-navigation {
                flex-direction: column;
                gap: 1rem;
            }

            .btn-nav {
                width: 100%;
                justify-content: center;
            }

            .progress-steps {
                overflow-x: auto;
                padding-bottom: 1rem;
            }

            .step-label {
                font-size: 0.75rem;
            }
        }

        /* Select2 Custom Styles */
        .select2-container--default .select2-selection--single {
            height: 46px;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--dark);
            line-height: normal;
            padding: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px;
            right: 10px;
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
        }

        .select2-dropdown {
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
        }

        .select2-results__option {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }

        .select2-results__option--highlighted {
            background: var(--primary);
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processando...</div>
    </div>

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
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
        <nav>
            <ol class="breadcrumb-custom">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><a href="dashboard.php">Associados</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li class="active"><?php echo $isEdit ? 'Editar' : 'Novo Cadastro'; ?></li>
            </ol>
        </nav>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-plus"></i>
                <?php echo $isEdit ? 'Editar Associado' : 'Cadastrar Novo Associado'; ?>
            </h1>
            <p class="page-subtitle">
                <?php echo $isEdit ? 'Atualize os dados do associado' : 'Preencha todos os campos obrigatórios para cadastrar um novo associado'; ?>
            </p>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Progress Bar -->
            <div class="progress-bar-container">
                <div class="progress-steps">
                    <div class="progress-line" id="progressLine"></div>
                    
                    <div class="step active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Dados Pessoais</div>
                    </div>
                    
                    <div class="step" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Dados Militares</div>
                    </div>
                    
                    <div class="step" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Endereço</div>
                    </div>
                    
                    <div class="step" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Financeiro</div>
                    </div>
                    
                    <div class="step" data-step="5">
                        <div class="step-circle">5</div>
                        <div class="step-label">Dependentes</div>
                    </div>
                    
                    <div class="step" data-step="6">
                        <div class="step-circle">6</div>
                        <div class="step-label">Revisão</div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <form id="formAssociado" class="form-content" enctype="multipart/form-data">
                <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo $associadoId; ?>">
                <?php endif; ?>

                <!-- Step 1: Dados Pessoais -->
                <div class="section-card active" data-step="1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Pessoais</h2>
                            <p class="section-subtitle">Informações básicas do associado</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">
                                Nome Completo <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="nome" id="nome" required
                                   value="<?php echo $associadoData['nome'] ?? ''; ?>"
                                   placeholder="Digite o nome completo do associado">
                            <span class="form-error">Por favor, insira o nome completo</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Nascimento <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="nasc" id="nasc" required
                                   value="<?php echo $associadoData['nasc'] ?? ''; ?>">
                            <span class="form-error">Por favor, insira a data de nascimento</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Sexo <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_m" value="M" required
                                           <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'M') ? 'checked' : ''; ?>>
                                    <label for="sexo_m">Masculino</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_f" value="F" required
                                           <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'F') ? 'checked' : ''; ?>>
                                    <label for="sexo_f">Feminino</label>
                                </div>
                            </div>
                            <span class="form-error">Por favor, selecione o sexo</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Estado Civil
                            </label>
                            <select class="form-input form-select" name="estadoCivil" id="estadoCivil">
                                <option value="">Selecione...</option>
                                <option value="Solteiro(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Solteiro(a)') ? 'selected' : ''; ?>>Solteiro(a)</option>
                                <option value="Casado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Casado(a)') ? 'selected' : ''; ?>>Casado(a)</option>
                                <option value="Divorciado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Divorciado(a)') ? 'selected' : ''; ?>>Divorciado(a)</option>
                                <option value="Viúvo(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Viúvo(a)') ? 'selected' : ''; ?>>Viúvo(a)</option>
                                <option value="União Estável" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'União Estável') ? 'selected' : ''; ?>>União Estável</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                RG <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="rg" id="rg" required
                                   value="<?php echo $associadoData['rg'] ?? ''; ?>"
                                   placeholder="Número do RG">
                            <span class="form-error">Por favor, insira o RG</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                CPF <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="cpf" id="cpf" required
                                   value="<?php echo $associadoData['cpf'] ?? ''; ?>"
                                   placeholder="000.000.000-00">
                            <span class="form-error">Por favor, insira um CPF válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Telefone <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="telefone" id="telefone" required
                                   value="<?php echo $associadoData['telefone'] ?? ''; ?>"
                                   placeholder="(00) 00000-0000">
                            <span class="form-error">Por favor, insira o telefone</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                E-mail
                            </label>
                            <input type="email" class="form-input" name="email" id="email"
                                   value="<?php echo $associadoData['email'] ?? ''; ?>"
                                   placeholder="email@exemplo.com">
                            <span class="form-error">Por favor, insira um e-mail válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Escolaridade
                            </label>
                            <select class="form-input form-select" name="escolaridade" id="escolaridade">
                                <option value="">Selecione...</option>
                                <option value="Fundamental Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Incompleto') ? 'selected' : ''; ?>>Fundamental Incompleto</option>
                                <option value="Fundamental Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Completo') ? 'selected' : ''; ?>>Fundamental Completo</option>
                                <option value="Médio Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Incompleto') ? 'selected' : ''; ?>>Médio Incompleto</option>
                                <option value="Médio Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Completo') ? 'selected' : ''; ?>>Médio Completo</option>
                                <option value="Superior Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Incompleto') ? 'selected' : ''; ?>>Superior Incompleto</option>
                                <option value="Superior Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Completo') ? 'selected' : ''; ?>>Superior Completo</option>
                                <option value="Pós-graduação" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Pós-graduação') ? 'selected' : ''; ?>>Pós-graduação</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Indicado por
                                <i class="fas fa-info-circle info-tooltip" title="Nome da pessoa que indicou o associado"></i>
                            </label>
                            <input type="text" class="form-input" name="indicacao" id="indicacao"
                                   value="<?php echo $associadoData['indicacao'] ?? ''; ?>"
                                   placeholder="Nome do indicador">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Situação <span class="required">*</span>
                            </label>
                            <select class="form-input form-select" name="situacao" id="situacao" required>
                                <option value="Filiado" <?php echo (!isset($associadoData['situacao']) || $associadoData['situacao'] == 'Filiado') ? 'selected' : ''; ?>>Filiado</option>
                                <option value="Desfiliado" <?php echo (isset($associadoData['situacao']) && $associadoData['situacao'] == 'Desfiliado') ? 'selected' : ''; ?>>Desfiliado</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Filiação <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="dataFiliacao" id="dataFiliacao" required
                                   value="<?php echo $associadoData['data_filiacao'] ?? date('Y-m-d'); ?>">
                            <span class="form-error">Por favor, insira a data de filiação</span>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                Foto do Associado
                            </label>
                            <div class="photo-upload-container">
                                <div class="photo-preview" id="photoPreview">
                                    <?php if (isset($associadoData['foto']) && $associadoData['foto']): ?>
                                        <img src="<?php echo $associadoData['foto']; ?>" alt="Foto do associado">
                                    <?php else: ?>
                                        <div class="photo-preview-placeholder">
                                            <i class="fas fa-camera"></i>
                                            <p>Sem foto</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <input type="file" name="foto" id="foto" accept="image/*" style="display: none;">
                                    <button type="button" class="photo-upload-btn" onclick="document.getElementById('foto').click();">
                                        <i class="fas fa-upload"></i>
                                        Escolher Foto
                                    </button>
                                    <p class="text-muted mt-2" style="font-size: 0.75rem;">
                                        Formatos aceitos: JPG, PNG, GIF<br>
                                        Tamanho máximo: 5MB
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Dados Militares -->
                <div class="section-card" data-step="2">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Militares</h2>
                            <p class="section-subtitle">Informações sobre a carreira militar</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Corporação
                            </label>
                            <select class="form-input form-select" name="corporacao" id="corporacao">
                                <option value="">Selecione...</option>
                                <option value="Polícia Militar" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Militar') ? 'selected' : ''; ?>>Polícia Militar</option>
                                <option value="Corpo de Bombeiros" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Corpo de Bombeiros') ? 'selected' : ''; ?>>Corpo de Bombeiros</option>
                                <option value="Polícia Civil" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Civil') ? 'selected' : ''; ?>>Polícia Civil</option>
                                <option value="Polícia Federal" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Federal') ? 'selected' : ''; ?>>Polícia Federal</option>
                                <option value="Forças Armadas" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Forças Armadas') ? 'selected' : ''; ?>>Forças Armadas</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Patente
                            </label>
                            <select class="form-input form-select" name="patente" id="patente">
                                <option value="">Selecione...</option>
                                <optgroup label="Praças">
                                    <option value="Soldado" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Soldado') ? 'selected' : ''; ?>>Soldado</option>
                                    <option value="Cabo" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Cabo') ? 'selected' : ''; ?>>Cabo</option>
                                    <option value="3º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '3º Sargento') ? 'selected' : ''; ?>>3º Sargento</option>
                                    <option value="2º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '2º Sargento') ? 'selected' : ''; ?>>2º Sargento</option>
                                    <option value="1º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '1º Sargento') ? 'selected' : ''; ?>>1º Sargento</option>
                                    <option value="Subtenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Subtenente') ? 'selected' : ''; ?>>Subtenente</option>
                                </optgroup>
                                <optgroup label="Oficiais">
                                    <option value="2º Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '2º Tenente') ? 'selected' : ''; ?>>2º Tenente</option>
                                    <option value="1º Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '1º Tenente') ? 'selected' : ''; ?>>1º Tenente</option>
                                    <option value="Capitão" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Capitão') ? 'selected' : ''; ?>>Capitão</option>
                                    <option value="Major" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Major') ? 'selected' : ''; ?>>Major</option>
                                    <option value="Tenente-Coronel" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Tenente-Coronel') ? 'selected' : ''; ?>>Tenente-Coronel</option>
                                    <option value="Coronel" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Coronel') ? 'selected' : ''; ?>>Coronel</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Categoria
                            </label>
                            <select class="form-input form-select" name="categoria" id="categoria">
                                <option value="">Selecione...</option>
                                <option value="Ativo" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Ativo') ? 'selected' : ''; ?>>Ativo</option>
                                <option value="Reserva" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Reserva') ? 'selected' : ''; ?>>Reserva</option>
                                <option value="Reformado" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Reformado') ? 'selected' : ''; ?>>Reformado</option>
                                <option value="Pensionista" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Lotação
                            </label>
                            <input type="text" class="form-input" name="lotacao" id="lotacao"
                                   value="<?php echo $associadoData['lotacao'] ?? ''; ?>"
                                   placeholder="Local de lotação">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                Unidade
                            </label>
                            <input type="text" class="form-input" name="unidade" id="unidade"
                                   value="<?php echo $associadoData['unidade'] ?? ''; ?>"
                                   placeholder="Unidade em que serve/serviu">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Endereço -->
                <div class="section-card" data-step="3">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Endereço</h2>
                            <p class="section-subtitle">Dados de localização do associado</p>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="cep-search-container">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">
                                    CEP
                                </label>
                                <input type="text" class="form-input" name="cep" id="cep"
                                       value="<?php echo $associadoData['cep'] ?? ''; ?>"
                                       placeholder="00000-000">
                            </div>
                            <button type="button" class="btn-search-cep" onclick="buscarCEP()">
                                <i class="fas fa-search"></i>
                                Buscar CEP
                            </button>
                        </div>

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">
                                    Endereço
                                </label>
                                <input type="text" class="form-input" name="endereco" id="endereco"
                                       value="<?php echo $associadoData['endereco'] ?? ''; ?>"
                                       placeholder="Rua, Avenida, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Número
                                </label>
                                <input type="text" class="form-input" name="numero" id="numero"
                                       value="<?php echo $associadoData['numero'] ?? ''; ?>"
                                       placeholder="Nº">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Complemento
                                </label>
                                <input type="text" class="form-input" name="complemento" id="complemento"
                                       value="<?php echo $associadoData['complemento'] ?? ''; ?>"
                                       placeholder="Apto, Bloco, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Bairro
                                </label>
                                <input type="text" class="form-input" name="bairro" id="bairro"
                                       value="<?php echo $associadoData['bairro'] ?? ''; ?>"
                                       placeholder="Nome do bairro">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Cidade
                                </label>
                                <input type="text" class="form-input" name="cidade" id="cidade"
                                       value="<?php echo $associadoData['cidade'] ?? ''; ?>"
                                       placeholder="Nome da cidade">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Financeiro -->
                <div class="section-card" data-step="4">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Financeiros</h2>
                            <p class="section-subtitle">Informações para cobrança e pagamentos</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Tipo de Associado
                            </label>
                            <select class="form-input form-select" name="tipoAssociado" id="tipoAssociado">
                                <option value="">Selecione...</option>
                                <option value="Titular" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Titular') ? 'selected' : ''; ?>>Titular</option>
                                <option value="Pensionista" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista</option>
                                <option value="Dependente" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Dependente') ? 'selected' : ''; ?>>Dependente</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Situação Financeira
                            </label>
                            <select class="form-input form-select" name="situacaoFinanceira" id="situacaoFinanceira">
                                <option value="">Selecione...</option>
                                <option value="Adimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Adimplente') ? 'selected' : ''; ?>>Adimplente</option>
                                <option value="Inadimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Inadimplente') ? 'selected' : ''; ?>>Inadimplente</option>
                                <option value="Isento" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Isento') ? 'selected' : ''; ?>>Isento</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Vínculo Servidor
                            </label>
                            <select class="form-input form-select" name="vinculoServidor" id="vinculoServidor">
                                <option value="">Selecione...</option>
                                <option value="Estado" <?php echo (isset($associadoData['vinculoServidor']) && $associadoData['vinculoServidor'] == 'Estado') ? 'selected' : ''; ?>>Estado</option>
                                <option value="Federal" <?php echo (isset($associadoData['vinculoServidor']) && $associadoData['vinculoServidor'] == 'Federal') ? 'selected' : ''; ?>>Federal</option>
                                <option value="Municipal" <?php echo (isset($associadoData['vinculoServidor']) && $associadoData['vinculoServidor'] == 'Municipal') ? 'selected' : ''; ?>>Municipal</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Local de Débito
                            </label>
                            <select class="form-input form-select" name="localDebito" id="localDebito">
                                <option value="">Selecione...</option>
                                <option value="Folha de Pagamento" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Folha de Pagamento') ? 'selected' : ''; ?>>Folha de Pagamento</option>
                                <option value="Débito em Conta" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Débito em Conta') ? 'selected' : ''; ?>>Débito em Conta</option>
                                <option value="Boleto" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Boleto') ? 'selected' : ''; ?>>Boleto</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Agência
                            </label>
                            <input type="text" class="form-input" name="agencia" id="agencia"
                                   value="<?php echo $associadoData['agencia'] ?? ''; ?>"
                                   placeholder="Número da agência">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Operação
                            </label>
                            <input type="text" class="form-input" name="operacao" id="operacao"
                                   value="<?php echo $associadoData['operacao'] ?? ''; ?>"
                                   placeholder="Código da operação">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Conta Corrente
                            </label>
                            <input type="text" class="form-input" name="contaCorrente" id="contaCorrente"
                                   value="<?php echo $associadoData['contaCorrente'] ?? ''; ?>"
                                   placeholder="Número da conta">
                        </div>
                    </div>
                </div>

                <!-- Step 5: Dependentes -->
                <div class="section-card" data-step="5">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dependentes</h2>
                            <p class="section-subtitle">Adicione os dependentes do associado</p>
                        </div>
                    </div>

                    <div id="dependentesContainer">
                        <?php if (isset($associadoData['dependentes']) && count($associadoData['dependentes']) > 0): ?>
                            <?php foreach ($associadoData['dependentes'] as $index => $dependente): ?>
                            <div class="dependente-card" data-index="<?php echo $index; ?>">
                                <div class="dependente-header">
                                    <span class="dependente-number">Dependente <?php echo $index + 1; ?></span>
                                    <button type="button" class="btn-remove-dependente" onclick="removerDependente(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group full-width">
                                        <label class="form-label">Nome Completo</label>
                                        <input type="text" class="form-input" name="dependentes[<?php echo $index; ?>][nome]"
                                               value="<?php echo $dependente['nome'] ?? ''; ?>"
                                               placeholder="Nome do dependente">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-input" name="dependentes[<?php echo $index; ?>][data_nascimento]"
                                               value="<?php echo $dependente['data_nascimento'] ?? ''; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Parentesco</label>
                                        <select class="form-input form-select" name="dependentes[<?php echo $index; ?>][parentesco]">
                                            <option value="">Selecione...</option>
                                            <option value="Cônjuge" <?php echo ($dependente['parentesco'] == 'Cônjuge') ? 'selected' : ''; ?>>Cônjuge</option>
                                            <option value="Filho(a)" <?php echo ($dependente['parentesco'] == 'Filho(a)') ? 'selected' : ''; ?>>Filho(a)</option>
                                            <option value="Pai" <?php echo ($dependente['parentesco'] == 'Pai') ? 'selected' : ''; ?>>Pai</option>
                                            <option value="Mãe" <?php echo ($dependente['parentesco'] == 'Mãe') ? 'selected' : ''; ?>>Mãe</option>
                                            <option value="Irmão(ã)" <?php echo ($dependente['parentesco'] == 'Irmão(ã)') ? 'selected' : ''; ?>>Irmão(ã)</option>
                                            <option value="Outro" <?php echo ($dependente['parentesco'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Sexo</label>
                                        <select class="form-input form-select" name="dependentes[<?php echo $index; ?>][sexo]">
                                            <option value="">Selecione...</option>
                                            <option value="M" <?php echo ($dependente['sexo'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="F" <?php echo ($dependente['sexo'] == 'F') ? 'selected' : ''; ?>>Feminino</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="btn-add-dependente" onclick="adicionarDependente()">
                        <i class="fas fa-plus"></i>
                        Adicionar Dependente
                    </button>
                </div>

                <!-- Step 6: Revisão -->
                <div class="section-card" data-step="6">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Revisão dos Dados</h2>
                            <p class="section-subtitle">Confira todos os dados antes de salvar</p>
                        </div>
                    </div>

                    <div id="revisaoContainer">
                        <!-- Conteúdo será preenchido dinamicamente -->
                    </div>
                </div>
            </form>

            <!-- Navigation -->
            <div class="form-navigation">
                <button type="button" class="btn-nav btn-back" id="btnVoltar" onclick="voltarStep()">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </button>
                
                <div>
                    <button type="button" class="btn-nav btn-back me-2" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    
                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <button type="button" class="btn-nav btn-submit" id="btnSalvar" onclick="salvarAssociado()" style="display: none;">
                        <i class="fas fa-save"></i>
                        <?php echo $isEdit ? 'Atualizar' : 'Salvar'; ?> Associado
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/pt-BR.min.js"></script>
    
    <script>
// Configuração inicial
const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
const associadoId = <?php echo $associadoId ? $associadoId : 'null'; ?>;

// Estado do formulário
let currentStep = 1;
const totalSteps = 6;
let dependenteIndex = <?php echo isset($associadoData['dependentes']) ? count($associadoData['dependentes']) : 0; ?>;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando formulário de cadastro...');
    
    // Máscaras
    $('#cpf').mask('000.000.000-00');
    $('#telefone').mask('(00) 00000-0000');
    $('#cep').mask('00000-000');
    
    // Select2
    $('.form-select').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%'
    });
    
    // Preview de foto
    document.getElementById('foto').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                showAlert('Arquivo muito grande! O tamanho máximo é 5MB.', 'error');
                e.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photoPreview').innerHTML = 
                    `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Validação em tempo real
    setupRealtimeValidation();
    
    // Atualiza interface
    updateProgressBar();
    updateNavigationButtons();
});

// Navegação entre steps
function proximoStep() {
    if (validarStepAtual()) {
        if (currentStep < totalSteps) {
            // Marca step atual como completo
            document.querySelector(`[data-step="${currentStep}"]`).classList.add('completed');
            
            currentStep++;
            mostrarStep(currentStep);
            
            // Se for o último step, preenche a revisão
            if (currentStep === totalSteps) {
                preencherRevisao();
            }
        }
    }
}

function voltarStep() {
    if (currentStep > 1) {
        currentStep--;
        mostrarStep(currentStep);
    }
}

function mostrarStep(step) {
    // Esconde todos os cards
    document.querySelectorAll('.section-card').forEach(card => {
        card.classList.remove('active');
    });
    
    // Mostra o card atual
    document.querySelector(`.section-card[data-step="${step}"]`).classList.add('active');
    
    // Atualiza progress
    updateProgressBar();
    updateNavigationButtons();
    
    // Scroll para o topo
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Atualiza barra de progresso
function updateProgressBar() {
    const progressLine = document.getElementById('progressLine');
    const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
    progressLine.style.width = progressPercent + '%';
    
    // Atualiza steps
    document.querySelectorAll('.step').forEach((step, index) => {
        const stepNumber = index + 1;
        
        if (stepNumber === currentStep) {
            step.classList.add('active');
            step.classList.remove('completed');
        } else if (stepNumber < currentStep) {
            step.classList.remove('active');
            step.classList.add('completed');
        } else {
            step.classList.remove('active', 'completed');
        }
    });
}

// Atualiza botões de navegação
function updateNavigationButtons() {
    const btnVoltar = document.getElementById('btnVoltar');
    const btnProximo = document.getElementById('btnProximo');
    const btnSalvar = document.getElementById('btnSalvar');
    
    // Botão voltar
    btnVoltar.style.display = currentStep === 1 ? 'none' : 'flex';
    
    // Botões próximo/salvar
    if (currentStep === totalSteps) {
        btnProximo.style.display = 'none';
        btnSalvar.style.display = 'flex';
    } else {
        btnProximo.style.display = 'flex';
        btnSalvar.style.display = 'none';
    }
}

// Validação do step atual
function validarStepAtual() {
    const stepCard = document.querySelector(`.section-card[data-step="${currentStep}"]`);
    const requiredFields = stepCard.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Validações específicas
    if (currentStep === 1) {
        // Valida CPF
        const cpfField = document.getElementById('cpf');
        if (cpfField.value && !validarCPF(cpfField.value)) {
            cpfField.classList.add('error');
            isValid = false;
            showAlert('CPF inválido!', 'error');
        }
        
        // Valida email
        const emailField = document.getElementById('email');
        if (emailField.value && !validarEmail(emailField.value)) {
            emailField.classList.add('error');
            isValid = false;
            showAlert('E-mail inválido!', 'error');
        }
    }
    
    if (!isValid) {
        showAlert('Por favor, preencha todos os campos obrigatórios!', 'warning');
    }
    
    return isValid;
}

// Validação em tempo real
function setupRealtimeValidation() {
    // Remove classe de erro ao digitar
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('error');
            }
        });
    });
    
    // Validação específica de CPF
    document.getElementById('cpf').addEventListener('blur', function() {
        if (this.value && !validarCPF(this.value)) {
            this.classList.add('error');
            showAlert('CPF inválido!', 'error');
        }
    });
    
    // Validação específica de email
    document.getElementById('email').addEventListener('blur', function() {
        if (this.value && !validarEmail(this.value)) {
            this.classList.add('error');
            showAlert('E-mail inválido!', 'error');
        }
    });
}

// Funções de validação
function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]/g, '');
    
    if (cpf.length !== 11) return false;
    
    // Verifica sequências inválidas
    if (/^(\d)\1{10}$/.test(cpf)) return false;
    
    // Validação do dígito verificador
    let soma = 0;
    let resto;
    
    for (let i = 1; i <= 9; i++) {
        soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    }
    
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.substring(9, 10))) return false;
    
    soma = 0;
    for (let i = 1; i <= 10; i++) {
        soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
    }
    
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.substring(10, 11))) return false;
    
    return true;
}

function validarEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Buscar CEP
function buscarCEP() {
    const cep = document.getElementById('cep').value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        showAlert('CEP inválido!', 'error');
        return;
    }
    
    showLoading();
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.erro) {
                showAlert('CEP não encontrado!', 'error');
                return;
            }
            
            document.getElementById('endereco').value = data.logradouro;
            document.getElementById('bairro').value = data.bairro;
            document.getElementById('cidade').value = data.localidade;
            
            // Foca no campo número
            document.getElementById('numero').focus();
        })
        .catch(error => {
            hideLoading();
            console.error('Erro ao buscar CEP:', error);
            showAlert('Erro ao buscar CEP!', 'error');
        });
}

// Gerenciar dependentes
function adicionarDependente() {
    const container = document.getElementById('dependentesContainer');
    const novoIndex = dependenteIndex++;
    
    const dependenteHtml = `
        <div class="dependente-card" data-index="${novoIndex}" style="animation: fadeInUp 0.3s ease;">
            <div class="dependente-header">
                <span class="dependente-number">Dependente ${novoIndex + 1}</span>
                <button type="button" class="btn-remove-dependente" onclick="removerDependente(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Nome Completo</label>
                    <input type="text" class="form-input" name="dependentes[${novoIndex}][nome]" 
                           placeholder="Nome do dependente">
                </div>
                <div class="form-group">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-input" name="dependentes[${novoIndex}][data_nascimento]">
                </div>
                <div class="form-group">
                    <label class="form-label">Parentesco</label>
                    <select class="form-input form-select" name="dependentes[${novoIndex}][parentesco]">
                        <option value="">Selecione...</option>
                        <option value="Cônjuge">Cônjuge</option>
                        <option value="Filho(a)">Filho(a)</option>
                        <option value="Pai">Pai</option>
                        <option value="Mãe">Mãe</option>
                        <option value="Irmão(ã)">Irmão(ã)</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sexo</label>
                    <select class="form-input form-select" name="dependentes[${novoIndex}][sexo]">
                        <option value="">Selecione...</option>
                        <option value="M">Masculino</option>
                        <option value="F">Feminino</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', dependenteHtml);
    
    // Inicializa Select2 nos novos selects
    $(`[data-index="${novoIndex}"] .form-select`).select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%'
    });
}

function removerDependente(button) {
    const card = button.closest('.dependente-card');
    card.style.animation = 'fadeOut 0.3s ease';
    
    setTimeout(() => {
        card.remove();
        // Reordena os números
        document.querySelectorAll('.dependente-card').forEach((card, index) => {
            card.querySelector('.dependente-number').textContent = `Dependente ${index + 1}`;
        });
    }, 300);
}

// Preencher revisão
function preencherRevisao() {
    const container = document.getElementById('revisaoContainer');
    const formData = new FormData(document.getElementById('formAssociado'));
    
    let html = '';
    
    // Dados Pessoais
    html += `
        <div class="overview-card">
            <div class="overview-card-header">
                <div class="overview-card-icon blue">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="overview-card-title">Dados Pessoais</h3>
            </div>
            <div class="overview-card-content">
                <div class="overview-item">
                    <span class="overview-label">Nome</span>
                    <span class="overview-value">${formData.get('nome') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">CPF</span>
                    <span class="overview-value">${formData.get('cpf') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">RG</span>
                    <span class="overview-value">${formData.get('rg') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">Telefone</span>
                    <span class="overview-value">${formData.get('telefone') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">E-mail</span>
                    <span class="overview-value">${formData.get('email') || '-'}</span>
                </div>
            </div>
        </div>
    `;
    
    // Dados Militares
    if (formData.get('corporacao') || formData.get('patente')) {
        html += `
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon green">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="overview-card-title">Dados Militares</h3>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Corporação</span>
                        <span class="overview-value">${formData.get('corporacao') || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Patente</span>
                        <span class="overview-value">${formData.get('patente') || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Categoria</span>
                        <span class="overview-value">${formData.get('categoria') || '-'}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Endereço
    if (formData.get('endereco') || formData.get('cidade')) {
        html += `
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon orange">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="overview-card-title">Endereço</h3>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Endereço</span>
                        <span class="overview-value">${formData.get('endereco') || '-'} ${formData.get('numero') || ''}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Bairro</span>
                        <span class="overview-value">${formData.get('bairro') || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Cidade</span>
                        <span class="overview-value">${formData.get('cidade') || '-'}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Salvar associado
function salvarAssociado() {
    // Validação final
    if (!validarFormularioCompleto()) {
        showAlert('Por favor, verifique todos os campos obrigatórios!', 'error');
        return;
    }
    
    showLoading();
    
    const formData = new FormData(document.getElementById('formAssociado'));
    
    // URL da API
    const url = isEdit 
        ? `../api/atualizar_associado.php?id=${associadoId}`
        : '../api/criar_associado.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.status === 'success') {
            showAlert(
                isEdit ? 'Associado atualizado com sucesso!' : 'Associado cadastrado com sucesso!',
                'success'
            );
            
            // Redireciona após 2 segundos
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        } else {
            showAlert(data.message || 'Erro ao salvar associado!', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showAlert('Erro ao processar requisição!', 'error');
    });
}

// Validação do formulário completo
function validarFormularioCompleto() {
    const form = document.getElementById('formAssociado');
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
            
            // Encontra em qual step está o campo
            const stepCard = field.closest('.section-card');
            if (stepCard) {
                const step = stepCard.getAttribute('data-step');
                console.log(`Campo obrigatório vazio no step ${step}: ${field.name}`);
            }
        }
    });
    
    return isValid;
}

// Funções auxiliares
function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('active');
}

function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const alertHtml = `
        <div id="${alertId}" class="alert-custom alert-${type}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML('beforeend', alertHtml);
    
    // Remove após 5 segundos
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }
    }, 5000);
}

// Animação fadeOut
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);
    </script>
</body>
</html>