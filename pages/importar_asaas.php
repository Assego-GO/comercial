<?php
/**
 * Página de Importação CSV ASAAS - Sistema ASSEGO
 * pages/importar_asaas.php
 * Importa dados do ASAAS e atualiza status de adimplência automaticamente
 * 
 * ATUALIZADO: Nova lógica - CSV contém apenas quem PAGOU
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
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
$page_title = 'Importar CSV ASAAS - Sistema ASSEGO';

// Verificar permissões - APENAS FINANCEIRO E PRESIDÊNCIA
$temPermissao = false;
$motivoNegacao = '';

if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    
    if ($deptId == 5 || $deptId == 1) { // Financeiro ou Presidência
        $temPermissao = true;
    } else {
        $motivoNegacao = 'Acesso restrito ao Setor Financeiro e Presidência.';
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
}

// Cria instância do Header Component seguindo padrão do sistema
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'financeiro',
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
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <!-- CSS Personalizado Moderno -->
    <style>
        /* Variáveis CSS modernas */
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
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            --shadow-lg: 0 8px 40px rgba(44, 90, 160, 0.15);
            --border-radius: 15px;
            --border-radius-sm: 8px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
            color: var(--dark);
            margin: 0;
            padding: 0;
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
            transition: margin-left 0.3s ease;
            max-width: 100%;
            width: 100%;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }

        .page-title-icon {
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            margin: 1rem 0 0 0;
            position: relative;
            z-index: 1;
        }

        /* Step Container Moderno */
        .step-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .step-container:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .step-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 2px solid var(--light);
            padding-bottom: 1rem;
        }

        .step-title i {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 0.75rem;
            border-radius: var(--border-radius-sm);
            font-size: 1.25rem;
        }

        /* Upload Area Moderna */
        .upload-area {
            border: 3px dashed var(--primary-light);
            border-radius: var(--border-radius);
            padding: 3rem;
            text-align: center;
            background: linear-gradient(135deg, #f8f9ff, #e8f2ff);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .upload-area::before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 100px;
            height: 100px;
            background: rgba(74, 144, 226, 0.1);
            border-radius: 50%;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: linear-gradient(135deg, #e8f2ff, #d1e7ff);
            transform: translateY(-2px);
        }

        .upload-area.dragover {
            border-color: var(--success);
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }

        .upload-icon {
            font-size: 4rem;
            color: var(--primary-light);
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .upload-area h3 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        /* Botão de Upload Moderno */
        .upload-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--border-radius-sm);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 86, 210, 0.3);
            position: relative;
            z-index: 1;
        }

        .upload-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 86, 210, 0.4);
        }

        /* File Info */
        .file-info {
            background: var(--white);
            border: 2px solid var(--success);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin: 1rem 0;
            display: none;
            box-shadow: var(--shadow);
        }

        /* Progress Container */
        .progress-container {
            background: var(--white);
            border-radius: var(--border-radius-sm);
            padding: 2rem;
            margin: 1.5rem 0;
            box-shadow: var(--shadow);
            display: none;
            border: 2px solid var(--info);
        }

        .progress-container h5 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        /* Results Container */
        .results-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            display: none;
            margin-top: 2rem;
        }

        /* Stats Grid Moderna */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius-sm);
            padding: 2rem;
            box-shadow: var(--shadow);
            border: none;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }

        .stat-card.total::before { background: linear-gradient(135deg, var(--info), #0ea5e9); }
        .stat-card.pagantes::before { background: linear-gradient(135deg, var(--success), #16a34a); }
        .stat-card.nao-encontrados::before { background: linear-gradient(135deg, var(--warning), #ea580c); }
        .stat-card.ignorados::before { background: linear-gradient(135deg, var(--secondary), #64748b); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-value {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Tabs Container Moderna */
        .tabs-container {
            background: var(--white);
            border-radius: var(--border-radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .nav-tabs {
            background: var(--light);
            border-bottom: none;
            padding: 0.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary);
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius-sm);
            margin: 0 0.25rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            background: rgba(0, 86, 210, 0.1);
            color: var(--primary);
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 86, 210, 0.3);
        }

        .tab-content {
            padding: 2rem;
        }

        /* Table Container */
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius-sm);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--light);
            color: var(--primary);
            font-weight: 700;
            border: none;
            padding: 1.25rem 1rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(0, 86, 210, 0.05);
            transform: translateX(2px);
        }

        /* Status Badges Modernos */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge.pagou {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .status-badge.nao-encontrado {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }

        .status-badge.ignorado {
            background: linear-gradient(135deg, #f8d7da, #fab1a0);
            color: #721c24;
        }

        /* Action Buttons */
        .action-buttons {
            text-align: center;
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-action {
            background: linear-gradient(135deg, var(--success), #16a34a);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--border-radius-sm);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }

        .btn-action.secondary {
            background: linear-gradient(135deg, var(--secondary), #64748b);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-action.secondary:hover {
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }

        /* Loading Spinner */
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            color: var(--primary);
        }

        .loading-spinner i {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alertas Modernos */
        .alert {
            border-radius: var(--border-radius-sm);
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .alert-custom {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }

        /* Info Box Moderna */
        .info-box {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 2px solid var(--info);
            border-radius: var(--border-radius-sm);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .info-box::before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 80px;
            height: 80px;
            background: rgba(23, 162, 184, 0.1);
            border-radius: 50%;
        }

        .info-box h5 {
            color: var(--info);
            margin-bottom: 1rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .info-box ul {
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .info-box li {
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        /* Progress bar moderna */
        .progress {
            height: 1rem;
            border-radius: 10px;
            background: var(--light);
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(135deg, var(--info), var(--primary));
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        /* Toast Container */
        .toast-container {
            z-index: 9999;
        }

        /* Badge customizado */
        .badge {
            border-radius: var(--border-radius-sm);
            padding: 0.5rem 0.75rem;
            font-weight: 600;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 2rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .step-container {
                padding: 1.5rem;
            }
            
            .upload-area {
                padding: 2rem 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-action {
                width: 100%;
                max-width: 300px;
            }
        }

        /* Animações personalizadas */
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Hover effects para cards */
        .associado-card,
        .file-info {
            transition: all 0.3s ease;
        }

        .associado-card:hover,
        .file-info:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>

<body>
    <!-- Toast Container para Notificações -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissao): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-custom" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                <a href="financeiro.php" class="btn-action secondary">
                    <i class="fas fa-arrow-left"></i>
                    Voltar aos Serviços Financeiros
                </a>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-file-import"></i>
                    </div>
                    Importar CSV ASAAS - Pagamentos
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-info-circle me-2"></i>
                    Importe arquivo CSV do ASAAS com dados de <strong>pagamentos realizados</strong> para atualizar o status de adimplência de <strong>todos os associados</strong> do sistema
                </p>
            </div>

            <!-- Informações sobre a Nova Lógica -->
            <div class="info-box" data-aos="fade-up" data-aos-delay="100">
                <h5><i class="fas fa-info-circle me-2"></i>Como Funciona a Importação</h5>
                <ul>
                    <li><strong>Escopo:</strong> Processa <strong>todos os associados</strong> encontrados no arquivo CSV</li>
                    <li><strong>CSV:</strong> Deve conter apenas quem <strong>realizou pagamentos</strong> (não cobranças pendentes)</li>
                    <li><strong>Pagou:</strong> Quem está no CSV será marcado como <strong>ADIMPLENTE</strong></li>
                    <li><strong>Não pagou:</strong> Associados do sistema que não estão no CSV serão apenas <strong>reportados</strong></li>
                    <li><strong>Corporações:</strong> Todos os tipos de corporação são processados e identificados</li>
                </ul>
            </div>

            <!-- Container de Importação -->
            <div class="step-container" data-aos="fade-up" data-aos-delay="200">
                <h4 class="step-title">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Upload do Arquivo CSV
                </h4>
                
                <div class="row">
                    <div class="col-md-10 mx-auto">
                        
                        <!-- Área de Upload -->
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h3>Arraste o arquivo CSV do ASAAS aqui</h3>
                            <p class="text-muted mb-3">ou clique no botão abaixo para selecionar</p>
                            
                            <button class="upload-btn" onclick="document.getElementById('csvFile').click()">
                                <i class="fas fa-file-csv me-2"></i>
                                Selecionar Arquivo CSV de Pagamentos
                            </button>
                            
                            <input type="file" id="csvFile" accept=".csv" style="display: none;" onchange="handleFileSelect(event)">
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Formato: CSV (separado por ponto e vírgula) | Tamanho máximo: 10MB | Todas as corporações
                                </small>
                            </div>
                        </div>

                        <!-- Informações do Arquivo -->
                        <div class="file-info" id="fileInfo">
                            <!-- Informações do arquivo aparecerão aqui -->
                        </div>

                        <!-- Barra de Progresso -->
                        <div class="progress-container" id="progressContainer">
                            <h5><i class="fas fa-cog fa-spin me-2"></i>Processando arquivo...</h5>
                            <div class="progress mt-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 0%" id="progressBar">
                                    0%
                                </div>
                            </div>
                            <p class="text-muted mt-2" id="progressText">Iniciando processamento...</p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Container de Resultados -->
            <div class="results-container" id="resultsContainer">
                
                <!-- Estatísticas -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-value" id="totalProcessados">0</div>
                        <div class="stat-label">Total Processados</div>
                    </div>
                    <div class="stat-card pagantes">
                        <div class="stat-value" id="totalPagantes">0</div>
                        <div class="stat-label">Pagamentos (Adimplentes)</div>
                    </div>
                    <div class="stat-card nao-encontrados">
                        <div class="stat-value" id="totalNaoEncontrados">0</div>
                        <div class="stat-label">Não Encontrados</div>
                    </div>
                    <div class="stat-card ignorados">
                        <div class="stat-value" id="totalIgnorados">0</div>
                        <div class="stat-label">Ignorados</div>
                    </div>
                </div>

                <!-- Tabs com Resultados -->
                <div class="tabs-container">
                    <ul class="nav nav-tabs" id="resultTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pagantes-tab" data-bs-toggle="tab" data-bs-target="#pagantes" type="button" role="tab">
                                <i class="fas fa-check-circle me-2"></i>Pagamentos (<span id="countPagantes">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="nao-encontrados-tab" data-bs-toggle="tab" data-bs-target="#nao-encontrados" type="button" role="tab">
                                <i class="fas fa-exclamation-triangle me-2"></i>Não Encontrados (<span id="countNaoEncontrados">0</span>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ignorados-tab" data-bs-toggle="tab" data-bs-target="#ignorados" type="button" role="tab">
                                <i class="fas fa-times-circle me-2"></i>Ignorados (<span id="countIgnorados">0</span>)
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="resultTabContent">
                        <!-- Tab Pagantes -->
                        <div class="tab-pane fade show active" id="pagantes" role="tabpanel">
                            <div class="table-container">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>CPF</th>
                                            <th>Corporação</th>
                                            <th>Status</th>
                                            <th>Valor Pago</th>
                                            <th>Data Pagamento</th>
                                            <th>Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pagantesTable">
                                        <!-- Resultados aparecerão aqui -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab Não Encontrados -->
                        <div class="tab-pane fade" id="nao-encontrados" role="tabpanel">
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Associados do sistema que NÃO foram encontrados no arquivo de pagamentos.</strong><br>
                                Estes não tiveram seu status alterado - apenas reportados.
                            </div>
                            <div class="table-container">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>CPF</th>
                                            <th>Corporação</th>
                                            <th>Status Atual</th>
                                            <th>Motivo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="naoEncontradosTable">
                                        <!-- Resultados aparecerão aqui -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Tab Ignorados -->
                        <div class="tab-pane fade" id="ignorados" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>CPFs encontrados no arquivo que NÃO existem no sistema de associados.</strong><br>
                                Foram ignorados automaticamente pois não são associados cadastrados.
                            </div>
                            <div class="table-container">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>CPF</th>
                                            <th>Corporação</th>
                                            <th>Motivo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ignoradosTable">
                                        <!-- Resultados aparecerão aqui -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de Ação -->
                <div class="action-buttons">
                    <button class="btn-action" onclick="voltarImportacao()">
                        <i class="fas fa-upload"></i>
                        Nova Importação
                    </button>
                    <button class="btn-action secondary" onclick="voltarFinanceiro()">
                        <i class="fas fa-arrow-left"></i>
                        Voltar ao Financeiro
                    </button>
                </div>

            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // ===== SISTEMA DE NOTIFICAÇÕES MODERNO =====
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('toastContainer');
            }

            show(message, type = 'success', duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                toast.style.minWidth = '350px';

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

        // ===== INICIALIZAÇÃO =====
        const notifications = new NotificationSystem();

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar AOS
            AOS.init({ 
                duration: 800, 
                once: true,
                offset: 50
            });

            notifications.show('Sistema de Importação ASAAS carregado com sucesso!', 'info', 3000);
        });

        // ===== CONFIGURAR DRAG AND DROP =====
        const uploadArea = document.getElementById('uploadArea');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                processarArquivo(files[0]);
            }
        });

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                processarArquivo(file);
            }
        }

        function processarArquivo(file) {
            // Validações
            if (!file.name.toLowerCase().endsWith('.csv')) {
                notifications.show('Erro: Por favor, selecione um arquivo CSV válido.', 'error');
                return;
            }

            if (file.size > 10 * 1024 * 1024) { // 10MB
                notifications.show('Erro: Arquivo muito grande. Máximo: 10MB', 'error');
                return;
            }

            // Mostrar informações do arquivo
            mostrarInfoArquivo(file);

            // Mostrar progresso
            mostrarProgresso();

            // Processar CSV com PapaParse
            Papa.parse(file, {
                header: true,
                delimiter: ';',
                encoding: 'UTF-8',
                skipEmptyLines: true,
                complete: function(results) {
                    if (results.errors.length > 0) {
                        console.error('Erros no CSV:', results.errors);
                        notifications.show('Erro ao processar CSV: ' + results.errors[0].message, 'error');
                        esconderProgresso();
                        return;
                    }
                    
                    console.log('📊 CSV processado:', results.data.length, 'registros');
                    
                    // Enviar dados para o servidor
                    enviarDadosServidor(results.data);
                },
                error: function(error) {
                    console.error('Erro no parse:', error);
                    notifications.show('Erro ao ler arquivo CSV: ' + error.message, 'error');
                    esconderProgresso();
                }
            });
        }

        function mostrarInfoArquivo(file) {
            const fileInfo = document.getElementById('fileInfo');
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            
            fileInfo.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-csv fa-3x text-success me-3"></i>
                    <div>
                        <h6 class="mb-1 text-primary"><strong>${file.name}</strong></h6>
                        <small class="text-muted">
                            <i class="fas fa-weight me-1"></i>${fileSize} MB | 
                            <i class="fas fa-calendar me-1"></i>${file.lastModifiedDate?.toLocaleDateString() || 'Data não disponível'}
                        </small>
                    </div>
                    <div class="ms-auto">
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Arquivo OK</span>
                    </div>
                </div>
            `;
            fileInfo.style.display = 'block';
            fileInfo.classList.add('animate-fade-in');
        }

        function mostrarProgresso() {
            const container = document.getElementById('progressContainer');
            container.style.display = 'block';
            container.classList.add('animate-fade-in');
            updateProgress(10, 'Lendo arquivo CSV...');
        }

        function updateProgress(percent, text) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
            progressText.textContent = text;
        }

        function esconderProgresso() {
            document.getElementById('progressContainer').style.display = 'none';
        }

        function enviarDadosServidor(dadosCSV) {
            updateProgress(30, 'Enviando dados para processamento...');

            const formData = new FormData();
            formData.append('dados_csv', JSON.stringify(dadosCSV));
            formData.append('action', 'processar_asaas');
            
            console.log('🚀 Enviando para processamento ASAAS...');

            fetch('../api/financeiro/processar_asaas.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.log('📄 Resposta recebida:', text.substring(0, 200) + '...');
                
                // Tentar parsear JSON
                try {
                    return JSON.parse(text);
                } catch (e) {
                    // Se falhar, tentar extrair JSON do meio do texto
                    const jsonStart = text.lastIndexOf('{');
                    const jsonEnd = text.lastIndexOf('}');
                    
                    if (jsonStart !== -1 && jsonEnd !== -1) {
                        const jsonText = text.substring(jsonStart, jsonEnd + 1);
                        return JSON.parse(jsonText);
                    }
                    
                    throw new Error('Resposta inválida do servidor');
                }
            })
            .then(data => {
                updateProgress(100, 'Processamento concluído!');
                
                setTimeout(() => {
                    esconderProgresso();
                    
                    if (data.status === 'success') {
                        mostrarResultados(data.resultado);
                        notifications.show(`✅ Importação concluída! ${data.resultado.resumo.totalProcessados} associados processados.`, 'success');
                    } else {
                        notifications.show('❌ Erro: ' + data.message, 'error');
                        console.error('Erro:', data);
                    }
                }, 1000);
            })
            .catch(error => {
                console.error('❌ Erro:', error);
                esconderProgresso();
                notifications.show('Erro de comunicação: ' + error.message, 'error');
            });
        }

        function validarEFormatarCPF(cpf) {
            if (!cpf && cpf !== 0) return '-';
            
            cpf = String(cpf).replace(/\D/g, '');
            
            if (cpf.length === 0) return '-';
            if (cpf.length !== 11) return cpf;
            
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }

        function mostrarResultados(resultado) {
            console.log('🎯 Mostrando resultados:', resultado);
            
            // Atualizar estatísticas (usando nova estrutura)
            document.getElementById('totalProcessados').textContent = resultado.resumo.totalProcessados;
            document.getElementById('totalPagantes').textContent = resultado.resumo.pagantes;
            document.getElementById('totalNaoEncontrados').textContent = resultado.resumo.nao_encontrados;
            document.getElementById('totalIgnorados').textContent = resultado.resumo.ignorados;

            // Atualizar contadores nas tabs
            document.getElementById('countPagantes').textContent = resultado.resumo.pagantes;
            document.getElementById('countNaoEncontrados').textContent = resultado.resumo.nao_encontrados;
            document.getElementById('countIgnorados').textContent = resultado.resumo.ignorados;

            // Preencher tabela de PAGANTES
            preencherTabelaPagantes(resultado.pagantes);
            
            // Preencher tabela de NÃO ENCONTRADOS
            preencherTabelaNaoEncontrados(resultado.nao_encontrados);
            
            // Preencher tabela de IGNORADOS
            preencherTabelaIgnorados(resultado.ignorados);

            // Mostrar container de resultados com animação
            const resultsContainer = document.getElementById('resultsContainer');
            resultsContainer.style.display = 'block';
            resultsContainer.classList.add('animate-fade-in');
            
            // Scroll suave para os resultados
            resultsContainer.scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        function preencherTabelaPagantes(pagantes) {
            const tbody = document.getElementById('pagantesTable');
            tbody.innerHTML = '';

            pagantes.forEach(associado => {
                const row = document.createElement('tr');
                row.style.backgroundColor = '#f8fff9'; // Verde claro
                
                const cpfFormatado = validarEFormatarCPF(associado.cpf);
                const dadosPagamento = associado.dados_pagamento || {};
                
                row.innerHTML = `
                    <td><strong>${associado.nome || 'Nome não informado'}</strong></td>
                    <td><code>${cpfFormatado}</code></td>
                    <td><span class="badge bg-primary">${associado.corporacao || 'N/A'}</span></td>
                    <td>
                        <span class="status-badge pagou">
                            ✅ PAGOU
                        </span>
                    </td>
                    <td><strong class="text-success">R$ ${dadosPagamento.valor || '0,00'}</strong></td>
                    <td><small>${dadosPagamento.data_pagamento || 'N/A'}</small></td>
                    <td>
                        <small class="text-success"><i class="fas fa-check-circle me-1"></i>Marcado como ADIMPLENTE</small>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function preencherTabelaNaoEncontrados(naoEncontrados) {
            const tbody = document.getElementById('naoEncontradosTable');
            tbody.innerHTML = '';

            naoEncontrados.forEach(associado => {
                const row = document.createElement('tr');
                row.style.backgroundColor = '#fff9e6'; // Amarelo claro
                
                const cpfFormatado = validarEFormatarCPF(associado.cpf);
                
                row.innerHTML = `
                    <td><strong>${associado.nome || 'Nome não informado'}</strong></td>
                    <td><code>${cpfFormatado}</code></td>
                    <td><span class="badge bg-warning text-dark">${associado.corporacao || 'N/A'}</span></td>
                    <td>
                        <span class="status-badge nao-encontrado">
                            ⚠️ NÃO ENCONTRADO
                        </span>
                    </td>
                    <td>
                        <small class="text-muted">${associado.motivo || 'Não encontrado no arquivo'}</small>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function preencherTabelaIgnorados(ignorados) {
            const tbody = document.getElementById('ignoradosTable');
            tbody.innerHTML = '';

            ignorados.forEach(pessoa => {
                const row = document.createElement('tr');
                row.style.backgroundColor = '#f8f9fa'; // Cinza claro
                
                const cpfFormatado = validarEFormatarCPF(pessoa.cpf);
                
                row.innerHTML = `
                    <td><strong>${pessoa.nome || 'Nome não informado'}</strong></td>
                    <td><code>${cpfFormatado}</code></td>
                    <td><span class="badge bg-secondary">${pessoa.corporacao || 'N/A'}</span></td>
                    <td>
                        <small class="text-muted">${pessoa.motivo || 'Fora do escopo'}</small>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function voltarImportacao() {
            // Resetar formulário
            document.getElementById('csvFile').value = '';
            document.getElementById('fileInfo').style.display = 'none';
            document.getElementById('resultsContainer').style.display = 'none';
            
            // Scroll para o topo
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            notifications.show('Formulário resetado. Pronto para nova importação!', 'info');
        }

        function voltarFinanceiro() {
            window.location.href = 'financeiro.php';
        }

        // Log de inicialização
        console.log('✅ Sistema de Importação ASAAS (Pagamentos) carregado com sucesso!');
        console.log('📋 Escopo: Todos os associados do sistema');
        console.log('💰 Função: Processar arquivo de pagamentos realizados');
        console.log('🎨 Design: Moderno e responsivo');
    </script>

</body>
</html>