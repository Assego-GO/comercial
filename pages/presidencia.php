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

// Verificar se o usuário tem permissão para acessar a presidência
$temPermissaoPresidencia = false;
$motivoNegacao = '';

// Debug completo ANTES das verificações
error_log("=== DEBUG DETALHADO PERMISSÕES PRESIDÊNCIA ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Array completo do usuário: " . print_r($usuarioLogado, true));
error_log("Departamento ID (valor): " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("Departamento ID (tipo): " . gettype($usuarioLogado['departamento_id'] ?? null));
error_log("É Diretor (método): " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// NOVA VALIDAÇÃO: APENAS usuários do departamento da presidência (ID: 1)
// Não importa se é diretor ou não - só quem é da presidência pode acessar
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    
    // Debug dos testes de comparação
    error_log("Testes de comparação:");
    error_log("  deptId === '1': " . ($deptId === '1' ? 'true' : 'false'));
    error_log("  deptId === 1: " . ($deptId === 1 ? 'true' : 'false'));
    error_log("  deptId == 1: " . ($deptId == 1 ? 'true' : 'false'));
    
    if ($deptId == 1) { // Comparação flexível para pegar string ou int
        $temPermissaoPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Departamento da Presidência (ID = 1)");
    } else {
        $motivoNegacao = 'Acesso restrito ao departamento da Presidência.';
        error_log("❌ Acesso negado. Departamento: '$deptId' (tipo: " . gettype($deptId) . "). Necessário: Presidência (ID = 1)");
    }
} else {
    $motivoNegacao = 'Departamento não identificado. Acesso restrito ao departamento da Presidência.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Log final do resultado
if (!$temPermissaoPresidencia) {
    error_log("❌ ACESSO NEGADO: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO - Usuário da Presidência");
}

// Busca estatísticas de documentos (apenas se tem permissão)
if ($temPermissaoPresidencia) {
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
} else {
    $aguardandoAssinatura = $assinadosHoje = $assinadosMes = $tempoMedio = 0;
}

// Cria instância do Header Component - CORRIGIDO: passa TODO o array do usuário
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado, // ← CORRIGIDO: Agora passa TODO o array (incluindo departamento_id)
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/presidencia.css">
    
    <style>
        /* Estilos adicionais para as novas funcionalidades */
        .stat-mini-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-mini-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-mini-label {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1.5rem;
            border-left: 2px solid var(--gray-200);
        }
        
        .timeline-item:last-child {
            border-left: none;
        }
        
        .timeline-marker {
            position: absolute;
            left: -9px;
            top: 0;
            width: 16px;
            height: 16px;
            background: var(--primary);
            border-radius: 50%;
            border: 3px solid var(--white);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .timeline-content {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 8px;
        }
        
        .config-card {
            background: var(--white);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1rem;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        
        .config-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.1);
        }

:root {
    --primary: #007bff;
    --primary-rgb: 0, 123, 255;
    --success: #28a745;
    --warning: #ffc107;
    --danger: #dc3545;
    --secondary: #6c757d;
    --white: #ffffff;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    --border-radius: 8px;
    --border-radius-lg: 12px;
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s ease;
}

/* Barra de filtros */
.filter-bar {
    background: var(--white);
    padding: 1.5rem;
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    border: 1px solid var(--gray-200);
}

.filter-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.filter-row:last-child {
    margin-bottom: 0;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 200px;
    flex: 1;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-700);
    margin-bottom: 0.25rem;
}

.filter-select, 
.filter-input {
    padding: 0.75rem 1rem;
    border: 1px solid var(--gray-300);
    border-radius: var(--border-radius);
    font-size: 0.875rem;
    background: var(--white);
    transition: var(--transition);
    width: 100%;
}

.filter-select:focus, 
.filter-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
}

.filter-select:hover,
.filter-input:hover {
    border-color: var(--gray-400);
}

/* Botões de filtro */
.filter-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-top: 1rem;
}

.btn-filter {
    padding: 0.5rem 1rem;
    border: 1px solid var(--primary);
    background: var(--primary);
    color: var(--white);
    border-radius: var(--border-radius);
    font-size: 0.875rem;
    cursor: pointer;
    transition: var(--transition);
}

.btn-filter:hover {
    background: #0056b3;
    border-color: #0056b3;
}

.btn-filter-clear {
    background: transparent;
    color: var(--gray-600);
    border-color: var(--gray-300);
}

.btn-filter-clear:hover {
    background: var(--gray-100);
    color: var(--gray-700);
}

/* Filtros ativos */
.filtros-ativos {
    padding-top: 1rem;
    margin-top: 1rem;
    border-top: 1px solid var(--gray-200);
}

.filtros-ativos-titulo {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.tag-filtro {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary);
    color: var(--white);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: var(--transition);
}

.tag-filtro:hover {
    background: #0056b3;
}

.tag-filtro .remove-filter {
    margin-left: 0.25rem;
    cursor: pointer;
    opacity: 0.7;
}

.tag-filtro .remove-filter:hover {
    opacity: 1;
}

/* Items de documento */
.document-item {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    border-left: 4px solid var(--gray-300);
    transition: var(--transition);
    position: relative;
}

.document-item:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-1px);
}

/* Layout interno do documento */
.document-content {
    width: 100%;
}

.document-content .d-flex.justify-content-between {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    width: 100%;
    gap: 1rem;
}

.document-content .d-flex.justify-content-between > div {
    flex: 0 0 auto;
    min-width: 0;
}

.document-content .d-flex.justify-content-between > div:not(:last-child) {
    margin-right: 1.5rem;
}

/* Informações do documento distribuídas */
.document-info-row {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    width: 100% !important;
    margin-bottom: 1rem !important;
    gap: 1rem !important;
    flex-wrap: nowrap !important;
}

.document-info-item {
    display: inline-flex !important;
    align-items: center !important;
    white-space: nowrap !important;
    gap: 0.25rem !important;
    flex: 1 !important;
    min-width: 0 !important;
}

.document-info-item i {
    flex-shrink: 0 !important;
    margin-right: 0.25rem !important;
}

.document-info-item span {
    white-space: nowrap !important;
}

.document-info-item .text-truncate {
    max-width: 100px !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}

/* Seção do associado */
.associado-info-row {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    width: 100%;
    gap: 1rem;
}

.associado-info-left {
    display: flex;
    align-items: center;
    flex: 0 0 auto;
}

.associado-info-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 0 0 auto;
}

/* Status do documento */
.document-item.status-pending {
    border-left-color: var(--warning);
}

.document-item.status-signed {
    border-left-color: var(--success);
}

.document-item.status-refused {
    border-left-color: var(--danger);
}

.document-item.status-expired {
    border-left-color: var(--secondary);
}

/* Badges de status */
.document-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    border: 1px solid transparent;
}

.document-status-badge.pending {
    background: rgba(255, 193, 7, 0.1);
    color: #856404;
    border-color: rgba(255, 193, 7, 0.3);
}

.document-status-badge.signed {
    background: rgba(40, 167, 69, 0.1);
    color: #155724;
    border-color: rgba(40, 167, 69, 0.3);
}

.document-status-badge.refused {
    background: rgba(220, 53, 69, 0.1);
    color: #721c24;
    border-color: rgba(220, 53, 69, 0.3);
}

.document-status-badge.expired {
    background: rgba(108, 117, 125, 0.1);
    color: #495057;
    border-color: rgba(108, 117, 125, 0.3);
}

/* Cards de estatísticas */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-mini-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    text-align: center;
    transition: var(--transition);
}

.stat-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-mini-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--primary);
}

.stat-mini-label {
    color: var(--gray-600);
    font-size: 0.875rem;
    font-weight: 500;
}

/* Estados de carregamento */
.loading-skeleton {
    background: linear-gradient(90deg, var(--gray-200) 25%, var(--gray-300) 50%, var(--gray-200) 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: var(--border-radius);
    height: 1rem;
    margin-bottom: 0.5rem;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Estado vazio */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray-600);
    background: var(--white);
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
}

.empty-state-icon {
    font-size: 4rem;
    color: var(--gray-400);
    margin-bottom: 1rem;
}

.empty-state-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: var(--gray-700);
}

.empty-state-description {
    font-size: 1rem;
    margin-bottom: 2rem;
    color: var(--gray-600);
}

/* Paginação */
.paginacaoContainer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1rem;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
}

.paginacao {
    display: flex;
    gap: 0.5rem;
}

.paginacao button {
    padding: 0.5rem 1rem;
    border: 1px solid var(--gray-300);
    background: var(--white);
    color: var(--gray-700);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.paginacao button:hover {
    background: var(--gray-100);
}

.paginacao button.active {
    background: var(--primary);
    color: var(--white);
    border-color: var(--primary);
}

.paginacao button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsividade */
@media (max-width: 768px) {
    .filter-bar {
        padding: 1rem;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        min-width: unset;
        width: 100%;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .paginacaoContainer {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .document-item {
        padding: 1rem;
    }
    
    /* Layout responsivo do documento */
    .document-info-row {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
    }
    
    .document-info-item {
        flex: none !important;
        min-width: 45% !important;
        white-space: normal !important;
    }
    
    .associado-info-row {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem;
    }
    
    .associado-info-right {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .filter-buttons {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-filter {
        width: 100%;
        justify-content: center;
    }
    
    .tag-filtro {
        font-size: 0.7rem;
        padding: 0.2rem 0.6rem;
    }
    
    /* Layout mobile do documento */
    .document-info-row {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }
    
    .document-info-item {
        width: 100% !important;
        min-width: unset !important;
        white-space: normal !important;
    }
}

.document-content .row .col-md-6 {
    flex: 0 0 100% !important;
    max-width: 100% !important;
}

/* Transformar document-meta em flexbox horizontal */
.document-meta {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    gap: 1rem !important;
    flex-wrap: nowrap !important;
    width: 100% !important;
}

/* Cada meta-item deve ocupar espaço igual */
.meta-item {
    display: flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    flex: 1 !important;
    min-width: 0 !important;
    white-space: nowrap !important;
}

/* Ícones não devem encolher */
.meta-item i {
    flex-shrink: 0 !important;
}

/* Textos podem ser truncados se necessário */
.meta-item span {
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
}

/* Responsividade para tablets */
@media (max-width: 768px) {
    .document-meta {
        flex-wrap: wrap !important;
        gap: 0.75rem !important;
    }
    
    .meta-item {
        flex: 0 0 48% !important;
        white-space: normal !important;
    }
    
    .meta-item span {
        white-space: normal !important;
    }
}

/* Responsividade para mobile */
@media (max-width: 480px) {
    .document-meta {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.5rem !important;
    }
    
    .meta-item {
        flex: none !important;
        width: 100% !important;
    }
}

    </style>
</head>

<body>
    <!-- Toast Container para Notificações -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;" id="toastContainer"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoPresidencia): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Área da Presidência</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Como resolver:</h6>
                    <ol class="mb-0">
                        <li>Verifique se você está no departamento correto</li>
                        <li>Confirme se você é diretor no sistema</li>
                        <li>Entre em contato com o administrador se necessário</li>
                    </ol>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Suas informações atuais:</h6>
                        <ul class="mb-0">
                            <li><strong>Nome:</strong> <?php echo htmlspecialchars($usuarioLogado['nome']); ?></li>
                            <li><strong>Cargo:</strong> <?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'N/A'); ?></li>
                            </li>
                            <li><strong>É Diretor:</strong> 
                                <span class="badge bg-<?php echo $auth->isDiretor() ? 'success' : 'danger'; ?>">
                                    <?php echo $auth->isDiretor() ? 'Sim' : 'Não'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Requisitos para acesso:</h6>
                        <ul class="mb-3">
                            <li>Estar no departamento da Presidência</li>
                        </ul>
                        
                        <div class="btn-group d-block">
                            <button class="btn btn-primary btn-sm" onclick="window.location.reload()">
                                <i class="fas fa-sync me-1"></i>
                                Recarregar Página
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <div class="page-title-icon">
                                <i class="fas fa-stamp"></i>
                            </div>
                            Área da Presidência
                        </h1>
                        <p class="page-subtitle">Gerencie e assine documentos de filiação dos associados</p>
                    </div>
                    
                    <!-- BOTÃO DE FUNCIONÁRIOS - PARA USUÁRIOS DA PRESIDÊNCIA -->
                    <?php if ($temPermissaoPresidencia): ?>
                    <div class="header-actions">
                        <a href="funcionarios.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-users me-2"></i> Funcionários
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
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
                    <button class="quick-action-btn" onclick="assinarTodos()">
                        <i class="fas fa-layer-group quick-action-icon"></i>
                        Assinar em Lote
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
                    <!-- Filtro por status -->
                    <select class="filter-select" id="filterStatus">
                        <option value="">Todos os documentos</option>
                        <option value="pending">📋 Aguardando Assinatura</option>
                        <option value="signed">✅ Assinados</option>
                        <option value="refused">❌ Recusados</option>
                        <option value="expired">⏰ Expirados</option>
                    </select>
                    
                    <!-- Filtro de busca -->
                    <input type="text" class="filter-input" id="searchInput" placeholder="Buscar por nome, CPF ou documento...">
                    
                    <!-- Filtro de ordenação -->
                    <select class="filter-select" id="filterOrdenacao">
                        <option value="desc">Mais recentes primeiro</option>
                        <option value="asc">Mais antigos primeiro</option>
                    </select>
                    
                    <!-- Botão de atualizar -->
                    <button class="btn-action secondary" onclick="atualizarDocumentos()" title="Atualizar lista">
                        <i class="fas fa-sync-alt"></i>
                        Atualizar
                    </button>
                    
                    <!-- Indicador de filtros ativos -->
                    <div id="filtrosAtivos" class="filtros-ativos" style="display: none;">
                        <small class="text-muted">Filtros aplicados:</small>
                        <div id="tagsFiltros" class="d-inline"></div>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="limparFiltros()">
                            <i class="fas fa-times"></i> Limpar
                        </button>
                    </div>
                </div>



<!-- PAGINAÇÃO -->
<div class="d-flex justify-content-between align-items-center mb-3" id="paginacaoContainer" style="display: none;">
    <div>
        <small class="text-muted" id="infoPaginacao"></small>
    </div>
    <div class="btn-group" id="botoesNavegacao">
        <button class="btn btn-outline-primary btn-sm" id="btnPaginaAnterior" onclick="navegarPagina(-1)" disabled>
            <i class="fas fa-chevron-left"></i> Anterior
        </button>
        <button class="btn btn-outline-primary btn-sm" id="btnProximaPagina" onclick="navegarPagina(1)" disabled>
            Próxima <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

                <!-- Documents List -->
                <div class="documents-list" id="documentsList">
                    <!-- Documentos serão carregados aqui -->
                </div>
            </div>
            
            <?php endif; ?>
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

    <!-- Modal de Relatórios -->
    <div class="modal fade" id="relatoriosModal" tabindex="-1" aria-labelledby="relatoriosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="relatoriosModalLabel">
                        <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                        Relatórios da Presidência
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Filtros de Período -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="relatorioDataInicio" value="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="relatorioDataFim" value="">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary w-100" onclick="carregarRelatorios()">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>

                    <!-- Estatísticas Resumidas -->
                    <div class="row mb-4" id="estatisticasResumo">
                        <!-- Será preenchido dinamicamente -->
                    </div>

                    <!-- Gráficos -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Documentos por Dia da Semana</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="chartDiaSemana" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Tempo Médio de Processamento</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="chartTempoProcessamento" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Produtividade -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Produtividade por Funcionário</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tabelaProdutividade">
                                    <thead>
                                        <tr>
                                            <th>Funcionário</th>
                                            <th>Total Assinados</th>
                                            <th>Tempo Médio (horas)</th>
                                            <th>Eficiência</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Será preenchido dinamicamente -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-success" onclick="exportarRelatorio('pdf')">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
                    </button>
                    <button type="button" class="btn btn-success" onclick="exportarRelatorio('excel')">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico -->
    <div class="modal fade" id="historicoModal" tabindex="-1" aria-labelledby="historicoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historicoModalLabel">
                        <i class="fas fa-history" style="color: var(--primary);"></i>
                        Histórico de Assinaturas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Filtros -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Período</label>
                            <select class="form-select" id="filtroPeriodoHistorico">
                                <option value="7">Últimos 7 dias</option>
                                <option value="30" selected>Últimos 30 dias</option>
                                <option value="60">Últimos 60 dias</option>
                                <option value="90">Últimos 90 dias</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Funcionário</label>
                            <select class="form-select" id="filtroFuncionarioHistorico">
                                <option value="">Todos</option>
                                <option value="<?php echo $_SESSION['funcionario_id']; ?>">Minhas assinaturas</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary w-100" onclick="carregarHistorico()">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </div>

                    <!-- Timeline de Assinaturas -->
                    <div id="timelineHistorico" class="timeline-container">
                        <!-- Será preenchido dinamicamente -->
                    </div>

                    <!-- Estatísticas do Histórico -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Resumo do Período</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center" id="resumoHistorico">
                                        <!-- Será preenchido dinamicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="imprimirHistorico()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Configurações -->
    <div class="modal fade" id="configuracoesModal" tabindex="-1" aria-labelledby="configuracoesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="configuracoesModalLabel">
                        <i class="fas fa-cog" style="color: var(--primary);"></i>
                        Configurações da Presidência
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Configuração de Notificações -->
                    <div class="config-card">
                        <h6 class="mb-3">
                            <i class="fas fa-bell"></i> Notificações
                        </h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notifNovoDoc" checked>
                            <label class="form-check-label" for="notifNovoDoc">
                                Notificar quando novos documentos chegarem para assinatura
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="notifUrgente" checked>
                            <label class="form-check-label" for="notifUrgente">
                                Alertar sobre documentos urgentes (mais de 3 dias aguardando)
                            </label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notifRelatorio">
                            <label class="form-check-label" for="notifRelatorio">
                                Enviar relatório semanal por e-mail
                            </label>
                        </div>
                    </div>

                    <!-- Configuração de Assinatura -->
                    <div class="config-card">
                        <h6 class="mb-3">
                            <i class="fas fa-signature"></i> Assinatura Padrão
                        </h6>
                        <div class="mb-3">
                            <label class="form-label">Método de assinatura preferido</label>
                            <select class="form-select" id="configMetodoAssinatura">
                                <option value="digital">Assinatura Digital</option>
                                <option value="upload">Upload de Arquivo Assinado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observação padrão</label>
                            <textarea class="form-control" id="configObsPadrao" rows="2" 
                                placeholder="Ex: Aprovado conforme normas vigentes"></textarea>
                        </div>
                    </div>

                    <!-- Configuração de Interface -->
                    <div class="config-card">
                        <h6 class="mb-3">
                            <i class="fas fa-desktop"></i> Interface
                        </h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="configAutoUpdate" checked>
                            <label class="form-check-label" for="configAutoUpdate">
                                Atualizar lista de documentos automaticamente (30 segundos)
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Documentos por página</label>
                            <select class="form-select" id="configDocsPorPagina">
                                <option value="10">10 documentos</option>
                                <option value="20" selected>20 documentos</option>
                                <option value="50">50 documentos</option>
                                <option value="100">100 documentos</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarConfiguracoes()">
                        <i class="fas fa-save"></i> Salvar Configurações
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

        let documentosZapSign = [];
let paginaAtual = 1;
let statusFiltro = '';
let termoBusca = '';
let ordenacao = 'desc';
let carregandoDocumentos = false;
let estatisticasGlobais = {};

// ===== FUNÇÃO PRINCIPAL ATUALIZADA =====

/**
 * Carrega documentos do ZapSign com filtros
 */
async function carregarDocumentosZapSign(resetarPagina = false) {
    if (carregandoDocumentos) return;
    
    if (resetarPagina) paginaAtual = 1;
    
    carregandoDocumentos = true;
    
    // Obter valores dos filtros
    statusFiltro = document.getElementById('filterStatus')?.value || '';
    termoBusca = document.getElementById('searchInput')?.value || '';
    ordenacao = document.getElementById('filterOrdenacao')?.value || 'desc';
    
    const container = document.getElementById('documentsList');
    
    if (!container) {
        console.error('Container de documentos não encontrado');
        carregandoDocumentos = false;
        return;
    }
    
    // Mostrar loading
    if (resetarPagina || paginaAtual === 1) {
        mostrarSkeletonLoading();
    }
    
    try {
        console.log('🔄 Carregando documentos ZapSign...', {
            pagina: paginaAtual,
            status: statusFiltro,
            busca: termoBusca,
            ordenacao: ordenacao
        });
        
        // Monta parâmetros da URL
        const params = new URLSearchParams({
            page: paginaAtual,
            sort_order: ordenacao
        });
        
        if (statusFiltro) params.append('status', statusFiltro);
        if (termoBusca) params.append('search', termoBusca);
        
        const response = await fetch(`../api/documentos/zapsign_listar_documentos.php?${params}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        console.log('📡 Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Erro HTTP:', response.status, errorText);
            throw new Error(`Erro HTTP ${response.status}: ${errorText}`);
        }
        
        const data = await response.json();
        console.log('✅ Resposta da API:', data);
        
        if (data.status === 'success') {
            documentosZapSign = data.data || [];
            estatisticasGlobais = data.estatisticas || {};
            
            renderizarDocumentosZapSign(documentosZapSign);
            atualizarPaginacao(data.paginacao || {});
            atualizarEstatisticasResumo();
            atualizarIndicadorFiltros();
            
            notifications.show(`${documentosZapSign.length} documento(s) carregado(s)`, 'success', 3000);
        } else {
            throw new Error(data.message || 'Erro desconhecido ao carregar documentos');
        }
        
    } catch (error) {
        console.error('❌ Erro ao carregar documentos:', error);
        mostrarErroCarregamento(error.message);
        notifications.show('Erro ao carregar documentos: ' + error.message, 'error');
    } finally {
        carregandoDocumentos = false;
    }
}

/**
 * Renderiza a lista de documentos ZapSign
 */
function renderizarDocumentosZapSign(documentos) {
    const container = document.getElementById('documentsList');
    
    if (!container) {
        console.error('Container de documentos não encontrado');
        return;
    }
    
    container.innerHTML = '';
    
    if (documentos.length === 0) {
        mostrarEstadoVazio();
        return;
    }
    
    documentos.forEach(doc => {
        const itemDiv = document.createElement('div');
        itemDiv.className = `document-item status-${doc.status}`;
        itemDiv.dataset.docId = doc.id;
        itemDiv.dataset.token = doc.token;
        
        // Define ícone baseado no status
        const statusIcon = getStatusIcon(doc.status);
        const actionButtons = getActionButtons(doc);
        
        itemDiv.innerHTML = `
            <div class="document-content">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center">
                        <div class="document-icon-wrapper me-3">
                            <i class="fas fa-file-pdf text-danger"></i>
                        </div>
                        <div>
                            <h5 class="document-title mb-1">${escapeHtml(doc.name)}</h5>
                            <span class="document-status-badge ${doc.status}">
                                ${statusIcon} ${doc.status_label}
                            </span>
                        </div>
                    </div>
                    <div class="document-actions">
                        ${actionButtons}
                    </div>
                </div>
                
                <div class="row">
                    
                    <div class="col-md-6">
                        <div class="document-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar-plus text-primary"></i>
                                <span><strong>Criado em:</strong> ${doc.created_at_formatted}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock text-info"></i>
                                <span><strong>Atualizado:</strong> ${doc.last_update_formatted}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-hourglass-half text-warning"></i>
                                <span><strong>Tempo:</strong> ${doc.tempo_desde_criacao}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-folder text-secondary"></i>
                                <span><strong>Pasta:</strong> ${doc.folder_path}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${doc.associado?.id ? `
                <div class="mt-3 pt-3 border-top">
                    <small class="text-muted">
                        <i class="fas fa-link"></i>
                        <strong>Vinculado ao associado ID:</strong> ${doc.associado.id} | 
                        <strong>Situação:</strong> ${escapeHtml(doc.associado.situacao || 'N/A')} |
                        <strong>Data filiação:</strong> ${formatarData(doc.associado.data_filiacao)}
                    </small>
                </div>
                ` : ''}
            </div>
        `;
        
        container.appendChild(itemDiv);
    });
}

/**
 * Retorna ícone baseado no status
 */
function getStatusIcon(status) {
    const icons = {
        'pending': '<i class="fas fa-clock"></i>',
        'signed': '<i class="fas fa-check-circle"></i>',
        'refused': '<i class="fas fa-times-circle"></i>',
        'expired': '<i class="fas fa-hourglass-end"></i>'
    };
    
    return icons[status] || '<i class="fas fa-question-circle"></i>';
}

/**
 * Retorna botões de ação baseados no status
 */

/**
 * Atualiza os botões de ação - com botão para presidente assinar
 */
function getActionButtons(doc) {
    let buttons = '';
    
    // Botão visualizar (sempre disponível se tiver arquivo)
    if (doc.original_file || doc.signed_file) {
        buttons += `
            <button class="btn-action secondary me-2" onclick="visualizarDocumentoZapSign('${doc.token}', '${doc.status}')" title="Visualizar documento">
                <i class="fas fa-eye"></i>
                Visualizar
            </button>
        `;
    }
    
    // ✅ BOTÃO PRESIDENTE ASSINAR
    // Lógica: Se documento está pending E já tem signed_file (associado assinou), presidente pode assinar
    if (doc.status === 'pending' && doc.signed_file) {
        buttons += `
            <button class="btn-action primary me-2" onclick="abrirLinkAssinaturaPresidente('${doc.token}')" title="Assinar como presidente">
                <i class="fas fa-signature"></i>
                Assinar
            </button>
        `;
    }
    
    // Botões específicos por status
    switch (doc.status) {
        case 'pending':
            buttons += `
                <button class="btn-action warning" onclick="acompanharDocumento('${doc.token}')" title="Ver detalhes e signatários">
                    <i class="fas fa-users"></i>
                    Detalhes
                </button>
            `;
            break;
            
        case 'signed':
            if (doc.signed_file) {
                buttons += `
                    <button class="btn-action success" onclick="baixarDocumentoAssinado('${doc.token}')" title="Baixar documento assinado">
                        <i class="fas fa-download"></i>
                        Baixar Assinado
                    </button>
                `;
            }
            break;
            
        case 'refused':
            buttons += `
                <button class="btn-action danger" onclick="verMotivoRecusa('${doc.token}')" title="Ver detalhes da recusa">
                    <i class="fas fa-info-circle"></i>
                    Ver Detalhes
                </button>
            `;
            break;
            
        case 'expired':
            buttons += `
                <button class="btn-action secondary" onclick="reenviarDocumento('${doc.token}')" title="Reenviar documento">
                    <i class="fas fa-redo"></i>
                    Reenviar
                </button>
            `;
            break;
    }
    
    return buttons;
}

function precisaAssinaturaPresidente(doc) {
    // Verifica se existe informação dos signatários
    if (!doc.signers || !Array.isArray(doc.signers) || doc.signers.length < 2) {
        return false; // Se não tem info dos signatários, não mostra botão
    }
    
    const associado = doc.signers[0]; // Primeiro signatário (associado)
    const presidente = doc.signers[1]; // Segundo signatário (presidente)
    
    // Verifica se associado JÁ assinou
    const associadoAssinou = associado && associado.status === 'signed';
    
    // Verifica se presidente ainda NÃO assinou
    const presidenteNaoAssinou = presidente && presidente.status !== 'signed';
    
    // Só mostra botão se associado assinou E presidente não assinou
    return associadoAssinou && presidenteNaoAssinou;
}

async function abrirLinkAssinaturaPresidente(token) {
    try {
        notifications.show('Buscando link de assinatura...', 'info', 2000);
        
        const response = await fetch(`../api/documentos/zapsign_detalhar_documento.php?token=${token}`);
        const data = await response.json();
        
        if (data.status === 'success' && data.data.signers) {
            // Pega o segundo signatário (presidente) - índice 1
            const presidente = data.data.signers[1];
            
            if (!presidente) {
                throw new Error('Presidente (signatário 2) não encontrado');
            }
            
            if (!presidente.sign_url) {
                throw new Error('Link de assinatura do presidente não disponível');
            }
            
            // Abre link específico de assinatura do presidente
            window.open(presidente.sign_url, '_blank');
            notifications.show('Link de assinatura aberto!', 'success', 3000);
            
        } else {
            throw new Error('Dados do documento não encontrados');
        }
        
    } catch (error) {
        console.error('Erro ao abrir link de assinatura:', error);
        notifications.show('Erro: ' + error.message, 'error');
    }
}
/**
 * Mostra skeleton loading
 */
function mostrarSkeletonLoading() {
    const container = document.getElementById('documentsList');
    container.innerHTML = '';
    
    for (let i = 0; i < 3; i++) {
        const skeleton = document.createElement('div');
        skeleton.className = 'document-item loading-skeleton';
        skeleton.style.height = '150px';
        skeleton.innerHTML = `
            <div class="d-flex">
                <div style="width: 60px; height: 60px; background: #e0e0e0; border-radius: 8px; margin-right: 1rem;"></div>
                <div style="flex: 1;">
                    <div style="height: 20px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem; width: 60%;"></div>
                    <div style="height: 16px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem; width: 40%;"></div>
                    <div style="height: 16px; background: #e0e0e0; border-radius: 4px; width: 80%;"></div>
                </div>
            </div>
        `;
        container.appendChild(skeleton);
    }
}

/**
 * Mostra estado vazio
 */
function mostrarEstadoVazio() {
    const container = document.getElementById('documentsList');
    
    let mensagem = 'Nenhum documento encontrado';
    let icone = 'fas fa-inbox';
    
    if (statusFiltro) {
        const statusLabels = {
            'pending': 'aguardando assinatura',
            'signed': 'assinados',
            'refused': 'recusados',
            'expired': 'expirados'
        };
        mensagem = `Nenhum documento ${statusLabels[statusFiltro] || 'com este status'} encontrado`;
        icone = 'fas fa-filter';
    }
    
    if (termoBusca) {
        mensagem += ` para "${termoBusca}"`;
        icone = 'fas fa-search';
    }
    
    container.innerHTML = `
        <div class="empty-state">
            <i class="${icone} empty-state-icon"></i>
            <h5 class="empty-state-title">${mensagem}</h5>
            <p class="empty-state-description">
                ${statusFiltro || termoBusca ? 
                    'Tente ajustar os filtros ou fazer uma nova busca.' : 
                    'Ainda não há documentos ZapSign registrados no sistema.'
                }
            </p>
            ${statusFiltro || termoBusca ? `
                <button class="btn-action primary" onclick="limparFiltros()">
                    <i class="fas fa-times"></i>
                    Limpar Filtros
                </button>
            ` : ''}
        </div>
    `;
}

/**
 * Mostra erro de carregamento
 */
function mostrarErroCarregamento(mensagem) {
    const container = document.getElementById('documentsList');
    container.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle empty-state-icon text-danger"></i>
            <h5 class="empty-state-title">Erro ao carregar documentos</h5>
            <p class="empty-state-description">${escapeHtml(mensagem)}</p>
            <button class="btn-action primary" onclick="carregarDocumentosZapSign(true)">
                <i class="fas fa-redo"></i>
                Tentar Novamente
            </button>
        </div>
    `;
}

/**
 * Atualiza controles de paginação
 */
function atualizarPaginacao(paginacao) {
    const container = document.getElementById('paginacaoContainer');
    const infoPaginacao = document.getElementById('infoPaginacao');
    const btnAnterior = document.getElementById('btnPaginaAnterior');
    const btnProxima = document.getElementById('btnProximaPagina');
    
    if (!container) return;
    
    if (paginacao.total_itens > 0) {
        container.style.display = 'flex';
        
        // Info da paginação
        const inicio = ((paginaAtual - 1) * 25) + 1;
        const fim = Math.min(paginaAtual * 25, paginacao.total_itens);
        infoPaginacao.textContent = `Mostrando ${inicio}-${fim} de ${paginacao.total_itens} documentos`;
        
        // Botões de navegação
        btnAnterior.disabled = !paginacao.tem_anterior;
        btnProxima.disabled = !paginacao.tem_proxima;
    } else {
        container.style.display = 'none';
    }
}

/**
 * Navega entre páginas
 */
function navegarPagina(direcao) {
    if (carregandoDocumentos) return;
    
    const novaPagina = paginaAtual + direcao;
    if (novaPagina < 1) return;
    
    paginaAtual = novaPagina;
    carregarDocumentosZapSign();
}

/**
 * Atualiza estatísticas resumidas
 */
function atualizarEstatisticasResumo() {
    // Esta função precisaria de dados adicionais da API
    // Por enquanto, vamos esconder as estatísticas
    const container = document.getElementById('estatisticasResumo');
    if (container) {
        container.style.display = 'none';
    }
}

/**
 * Atualiza indicador de filtros ativos
 */
function atualizarIndicadorFiltros() {
    const container = document.getElementById('filtrosAtivos');
    const tagsContainer = document.getElementById('tagsFiltros');
    
    if (!container || !tagsContainer) return;
    
    const filtrosAtivos = [];
    
    if (statusFiltro) {
        const statusLabels = {
            'pending': 'Aguardando Assinatura',
            'signed': 'Assinados',
            'refused': 'Recusados',
            'expired': 'Expirados'
        };
        filtrosAtivos.push(`Status: ${statusLabels[statusFiltro]}`);
    }
    
    if (termoBusca) {
        filtrosAtivos.push(`Busca: "${termoBusca}"`);
    }
    
    if (ordenacao !== 'desc') {
        filtrosAtivos.push('Ordem: Mais antigos primeiro');
    }
    
    if (filtrosAtivos.length > 0) {
        tagsContainer.innerHTML = filtrosAtivos.map(filtro => 
            `<span class="tag-filtro">${escapeHtml(filtro)}</span>`
        ).join('');
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}

/**
 * Limpa todos os filtros
 */
function limparFiltros() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('searchInput').value = '';
    document.getElementById('filterOrdenacao').value = 'desc';
    
    statusFiltro = '';
    termoBusca = '';
    ordenacao = 'desc';
    paginaAtual = 1;
    
    carregarDocumentosZapSign(true);
}

/**
 * Atualiza documentos (botão refresh)
 */
function atualizarDocumentos() {
    carregarDocumentosZapSign(true);
}

// ===== FUNÇÕES DE AÇÕES DOS DOCUMENTOS =====

/**
 * Visualiza documento do ZapSign
 */
function visualizarDocumentoZapSign(token, status) {
    // Busca o documento na lista atual
    const documento = documentosZapSign.find(doc => doc.token === token);
    
    if (!documento) {
        notifications.show('Documento não encontrado na lista atual', 'error');
        return;
    }
    
    let linkParaAbrir = null;
    
    // Determina qual link usar baseado no status e disponibilidade
    if (status === 'signed' && documento.signed_file) {
        linkParaAbrir = documento.signed_file;
    } else if (documento.original_file) {
        linkParaAbrir = documento.original_file;
    } else {
        notifications.show('Nenhum arquivo disponível para visualização', 'warning');
        return;
    }
    
    // Abrir documento em nova aba
    window.open(linkParaAbrir, '_blank');
}
/**
 * Acompanha documento pendente
 */
function acompanharDocumento(token) {
    const documento = documentosZapSign.find(doc => doc.token === token);
    
    if (!documento) {
        notifications.show('Documento não encontrado', 'error');
        return;
    }
    
    // Modal simples com informações básicas
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle"></i>
                        Informações do Documento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Nome:</strong></td>
                            <td>${escapeHtml(documento.name || 'N/A')}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge bg-${getStatusBadgeClass(documento.status)}">
                                    ${documento.status_label || documento.status}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Token:</strong></td>
                            <td><code>${documento.token}</code></td>
                        </tr>
                        <tr>
                            <td><strong>Criado:</strong></td>
                            <td>${documento.created_at_formatted || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Tempo:</strong></td>
                            <td>${documento.tempo_desde_criacao || 'N/A'}</td>
                        </tr>
                    </table>
                    
                    ${documento.associado && documento.associado.nome ? `
                        <hr>
                        <h6>Associado Vinculado</h6>
                        <p>
                            <strong>Nome:</strong> ${escapeHtml(documento.associado.nome)}<br>
                            <strong>CPF:</strong> ${documento.associado.cpf_formatted || 'N/A'}<br>
                            <strong>Email:</strong> ${escapeHtml(documento.associado.email || 'N/A')}
                        </p>
                    ` : ''}
                    
                    <div class="d-flex gap-2 mt-3">
                        ${documento.original_file ? `
                            <button class="btn btn-outline-primary btn-sm" onclick="window.open('${documento.original_file}', '_blank')">
                                <i class="fas fa-file-pdf"></i> Ver Original
                            </button>
                        ` : ''}
                        ${documento.signed_file ? `
                            <button class="btn btn-outline-success btn-sm" onclick="window.open('${documento.signed_file}', '_blank')">
                                <i class="fas fa-file-pdf"></i> Ver Assinado
                            </button>
                        ` : ''}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', () => {
        modal.remove();
    });
}


/**
 * Baixa documento assinado
 */
function baixarDocumentoAssinado(token) {
    const documento = documentosZapSign.find(doc => doc.token === token);
    
    if (!documento) {
        notifications.show('Documento não encontrado', 'error');
        return;
    }
    
    if (!documento.signed_file) {
        notifications.show('Documento assinado não disponível', 'warning');
        return;
    }
    
    // Criar link temporário para download
    const link = document.createElement('a');
    link.href = documento.signed_file;
    link.download = `${documento.name}_assinado.pdf`;
    link.target = '_blank';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    notifications.show('Download iniciado', 'success');
}
/**
 * Ver motivo da recusa
 */
function verMotivoRecusa(token) {
    acompanharDocumento(token);
}

/**
 * Reenvia documento expirado
 */
function reenviarDocumento(token) {
    notifications.show('Funcionalidade de reenvio em desenvolvimento', 'info');
    // Implementar chamada para API de reenvio
}

// ===== CONFIGURAÇÃO DE EVENTOS =====

/**
 * Configura eventos dos filtros
 */
function configurarFiltrosZapSign() {
    const filterStatus = document.getElementById('filterStatus');
    const searchInput = document.getElementById('searchInput');
    const filterOrdenacao = document.getElementById('filterOrdenacao');
    
    if (filterStatus) {
        filterStatus.addEventListener('change', () => {
            carregarDocumentosZapSign(true);
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            carregarDocumentosZapSign(true);
        }, 500));
    }
    
    if (filterOrdenacao) {
        filterOrdenacao.addEventListener('change', () => {
            carregarDocumentosZapSign(true);
        });
    }
}

// ===== FUNÇÕES AUXILIARES =====

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatarData(data) {
    if (!data) return 'N/A';
    try {
        return new Date(data).toLocaleDateString('pt-BR');
    } catch (e) {
        return 'N/A';
    }
}

function debounce(func, wait) {
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






async function carregarEstatisticasZapSign() {
    try {
        console.log('🔄 Carregando estatísticas ZapSign...');
        
        const response = await fetch('../api/documentos/zapsign_estatisticas.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`Erro HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.status === 'success') {
            atualizarCardsEstatisticas(data.data);
            atualizarEstatisticasResumoZapSign(data.data);
            
            if (data.cache) {
                console.log('📊 Estatísticas carregadas do cache');
            } else {
                console.log('📊 Estatísticas obtidas da API ZapSign');
            }
            
            // Atualizar novamente em 5 minutos se não for cache
            if (!data.cache) {
                setTimeout(carregarEstatisticasZapSign, 5 * 60 * 1000);
            }
        } else {
            throw new Error(data.message || 'Erro desconhecido');
        }
        
    } catch (error) {
        console.error('❌ Erro ao carregar estatísticas:', error);
        mostrarEstatisticasErro();
        
        // Tentar novamente em 30 segundos em caso de erro
        setTimeout(carregarEstatisticasZapSign, 30 * 1000);
    }
}

/**
 * Atualiza os cards principais de estatísticas
 */
function atualizarCardsEstatisticas(dados) {
    // Card 1: Aguardando Assinatura
    const aguardandoElement = document.querySelector('.stat-card:nth-child(1) .stat-value');
    const aguardandoStatus = document.querySelector('.stat-card:nth-child(1) .stat-change');
    
    if (aguardandoElement) {
        aguardandoElement.textContent = dados.pending || 0;
        
        if (aguardandoStatus) {
            if (dados.pending > 0) {
                aguardandoStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Requer atenção';
                aguardandoStatus.className = 'stat-change negative';
            } else {
                aguardandoStatus.innerHTML = '<i class="fas fa-check-circle"></i> Tudo em dia';
                aguardandoStatus.className = 'stat-change positive';
            }
        }
    }
    
    // Card 2: Assinados Hoje (usaremos documentos recentes)
    const assinadosHojeElement = document.querySelector('.stat-card:nth-child(2) .stat-value');
    const assinadosHojeStatus = document.querySelector('.stat-card:nth-child(2) .stat-change');
    
    if (assinadosHojeElement) {
        // Contar documentos assinados hoje
        const hoje = new Date().toDateString();
        const assinadosHoje = (dados.documentos_recentes || []).filter(doc => {
            if (doc.status === 'signed' && doc.created_at) {
                const docDate = new Date(doc.created_at).toDateString();
                return docDate === hoje;
            }
            return false;
        }).length;
        
        assinadosHojeElement.textContent = assinadosHoje;
        
        if (assinadosHojeStatus) {
            assinadosHojeStatus.innerHTML = '<i class="fas fa-arrow-up"></i> Produtividade';
            assinadosHojeStatus.className = 'stat-change positive';
        }
    }
    
    // Card 3: Total Assinados
    const totalAssinadosElement = document.querySelector('.stat-card:nth-child(3) .stat-value');
    if (totalAssinadosElement) {
        totalAssinadosElement.textContent = dados.signed || 0;
    }
    
    // Card 4: Tempo Médio
    const tempoMedioElement = document.querySelector('.stat-card:nth-child(4) .stat-value');
    if (tempoMedioElement) {
        const tempo = dados.tempo_medio_assinatura || 0;
        tempoMedioElement.textContent = tempo > 0 ? `${tempo}h` : '-';
    }
    
    // Atualizar ícones dos cards baseado no status
    atualizarIconesCards(dados);
}

/**
 * Atualiza ícones dos cards baseado nos dados
 */
function atualizarIconesCards(dados) {
    const cards = document.querySelectorAll('.stat-card');
    
    // Card 1: Cor baseada em pendentes
    if (cards[0]) {
        const icon = cards[0].querySelector('.stat-icon');
        if (icon) {
            if (dados.pending > 5) {
                icon.className = 'stat-icon danger';
            } else if (dados.pending > 0) {
                icon.className = 'stat-icon warning';
            } else {
                icon.className = 'stat-icon success';
            }
        }
    }
    
    // Card 2: Sempre success para assinados
    if (cards[1]) {
        const icon = cards[1].querySelector('.stat-icon');
        if (icon) {
            icon.className = 'stat-icon success';
        }
    }
    
    // Card 3: Primary para total
    if (cards[2]) {
        const icon = cards[2].querySelector('.stat-icon');
        if (icon) {
            icon.className = 'stat-icon primary';
        }
    }
    
    // Card 4: Info para tempo médio
    if (cards[3]) {
        const icon = cards[3].querySelector('.stat-icon');
        if (icon) {
            if (dados.tempo_medio_assinatura > 48) {
                icon.className = 'stat-icon warning';
            } else if (dados.tempo_medio_assinatura > 24) {
                icon.className = 'stat-icon info';
            } else {
                icon.className = 'stat-icon success';
            }
        }
    }
}

/**
 * Atualiza estatísticas resumidas (mini cards)
 */
function atualizarEstatisticasResumoZapSign(dados) {
    const container = document.getElementById('estatisticasResumo');
    
    if (!container) return;
    
    // Atualizar valores dos mini cards
    const elements = {
        'statPendentes': dados.pending || 0,
        'statAssinados': dados.signed || 0,
        'statRecusados': dados.refused || 0,
        'statExpirados': dados.expired || 0
    };
    
    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
            
            // Adicionar efeito de contagem animada
            animarContador(element, 0, value, 1000);
        }
    });
    
    // Mostrar container se tiver dados
    if (dados.total > 0) {
        container.style.display = 'block';
        
        // Adicionar informações adicionais
        adicionarInfoAdicional(container, dados);
    }
}

/**
 * Anima contador de números
 */
function animarContador(element, start, end, duration) {
    if (start === end) {
        element.textContent = end;
        return;
    }
    
    const range = end - start;
    const startTime = performance.now();
    
    function updateCounter(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const currentValue = Math.floor(start + (range * progress));
        element.textContent = currentValue;
        
        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        } else {
            element.textContent = end;
        }
    }
    
    requestAnimationFrame(updateCounter);
}

/**
 * Adiciona informações adicionais às estatísticas
 */
function adicionarInfoAdicional(container, dados) {
    // Remove info adicional existente
    const existingInfo = container.querySelector('.info-adicional');
    if (existingInfo) {
        existingInfo.remove();
    }
    
    // Criar nova info adicional
    const infoDiv = document.createElement('div');
    infoDiv.className = 'info-adicional mt-3 pt-3 border-top';
    
    let infoHtml = `
        <div class="row text-center">
            <div class="col-md-3">
                <small class="text-muted d-block">Taxa de Assinatura</small>
                <strong class="text-success">${dados.taxa_assinatura || 0}%</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Total de Documentos</small>
                <strong class="text-primary">${dados.total || 0}</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Tempo Médio</small>
                <strong class="text-info">${dados.tempo_medio_assinatura || 0}h</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Última Atualização</small>
                <strong class="text-secondary">${formatarHora(dados.ultima_atualizacao)}</strong>
            </div>
        </div>
    `;
    
    // Adicionar documentos urgentes se existirem
    if (dados.documentos_urgentes && dados.documentos_urgentes.length > 0) {
        infoHtml += `
            <div class="mt-3">
                <h6 class="text-warning mb-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    Documentos Urgentes (${dados.documentos_urgentes.length})
                </h6>
                <div class="row">
        `;
        
        dados.documentos_urgentes.slice(0, 3).forEach(doc => {
            infoHtml += `
                <div class="col-md-4 mb-2">
                    <div class="p-2 bg-warning bg-opacity-10 rounded">
                        <small class="d-block">
                            <strong>${escapeHtml(doc.nome)}</strong><br>
                            CPF: ${doc.cpf}<br>
                            <span class="text-danger">${doc.dias_pendente} dias aguardando</span>
                        </small>
                    </div>
                </div>
            `;
        });
        
        infoHtml += '</div></div>';
    }
    
    // Adicionar documentos recentes
    if (dados.documentos_recentes && dados.documentos_recentes.length > 0) {
        infoHtml += `
            <div class="mt-3">
                <h6 class="text-info mb-2">
                    <i class="fas fa-clock"></i>
                    Documentos Recentes (${Math.min(dados.documentos_recentes.length, 5)})
                </h6>
                <div class="row">
        `;
        
        dados.documentos_recentes.slice(0, 5).forEach(doc => {
            const statusClass = getStatusBadgeClass(doc.status);
            infoHtml += `
                <div class="col-md-2 mb-2">
                    <div class="p-2 border rounded text-center">
                        <small class="d-block">
                            <span class="badge bg-${statusClass} mb-1">${doc.status_label}</span><br>
                            ${doc.tempo_desde_criacao}
                        </small>
                    </div>
                </div>
            `;
        });
        
        infoHtml += '</div></div>';
    }
    
    infoDiv.innerHTML = infoHtml;
    container.appendChild(infoDiv);
}

/**
 * Retorna classe CSS para badge de status
 */
function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'warning',
        'signed': 'success',
        'refused': 'danger',
        'expired': 'secondary'
    };
    
    return classes[status] || 'secondary';
}

/**
 * Mostra erro nas estatísticas
 */
function mostrarEstatisticasErro() {
    // Zerar todos os valores
    const elements = [
        '.stat-card .stat-value',
        '#statPendentes',
        '#statAssinados', 
        '#statRecusados',
        '#statExpirados'
    ];
    
    elements.forEach(selector => {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = '-';
        }
    });
    
    // Mostrar mensagem de erro nos status changes
    const statusChanges = document.querySelectorAll('.stat-change');
    statusChanges.forEach(element => {
        element.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro ao carregar';
        element.className = 'stat-change negative';
    });
    
    notifications.show('Erro ao carregar estatísticas do ZapSign. Tentando novamente...', 'warning', 5000);
}

/**
 * Formatar horas para exibição
 */
function formatarHora(dataHora) {
    if (!dataHora) return '-';
    
    try {
        const date = new Date(dataHora);
        return date.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return '-';
    }
}

/**
 * Força atualização das estatísticas
 */
function forcarAtualizacaoEstatisticas() {
    // Limpar cache forçando nova requisição
    carregarEstatisticasZapSign();
    notifications.show('Atualizando estatísticas...', 'info', 2000);
}

// ===== INTEGRAÇÃO COM SISTEMA EXISTENTE =====

/**
 * Substitui a função original de carregar estatísticas
 */
function carregarEstatisticasPresidencia() {
    return carregarEstatisticasZapSign();
}

/**
 * Atualiza contadores (integração com sistema existente)
 */
function atualizarContadores() {
    // Mantém a funcionalidade original se necessário
    const totalPendentes = documentosZapSign.filter(doc => doc.status === 'pending').length;
    
    if (window.updateNotificationCount) {
        window.updateNotificationCount(totalPendentes);
    }
    
    // Força atualização das estatísticas se há mudança significativa
    const ultimoTotal = localStorage.getItem('ultimo_total_documentos');
    const totalAtual = documentosZapSign.length;
    
    if (!ultimoTotal || Math.abs(parseInt(ultimoTotal) - totalAtual) > 0) {
        localStorage.setItem('ultimo_total_documentos', totalAtual.toString());
        setTimeout(carregarEstatisticasZapSign, 2000); // Aguarda 2s para atualizar estatísticas
    }
}

        // ===== CLASSES E SISTEMAS AUXILIARES =====

        // Sistema de Notificações Toast
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

        // Cache Simples
        class SimpleCache {
            constructor(ttl = 300000) { // 5 minutos padrão
                this.cache = new Map();
                this.ttl = ttl;
            }
            
            set(key, value) {
                const expiry = Date.now() + this.ttl;
                this.cache.set(key, { value, expiry });
            }
            
            get(key) {
                const item = this.cache.get(key);
                if (!item) return null;
                
                if (Date.now() > item.expiry) {
                    this.cache.delete(key);
                    return null;
                }
                
                return item.value;
            }
            
            clear() {
                this.cache.clear();
            }
        }

        // Sistema de Atualização Automática
        class AutoUpdater {
            constructor(interval = 30000) {
                this.interval = interval;
                this.timer = null;
                this.isActive = true;
            }
            
            start() {
                if (this.timer) this.stop();
                
                this.timer = setInterval(() => {
                    if (this.isActive && document.hasFocus()) {
                        carregarDocumentosPendentes();
                    }
                }, this.interval);
            }
            
            stop() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            }
            
            pause() {
                this.isActive = false;
            }
            
            resume() {
                this.isActive = true;
            }
        }

        // ===== INICIALIZAÇÃO =====

        // Instanciar sistemas
        const notifications = new NotificationSystem();
        const cache = new SimpleCache();
        const autoUpdater = new AutoUpdater();

        // Variáveis globais
        let documentosPendentes = [];
        let documentoSelecionado = null;
        let arquivoAssinado = null;
        const temPermissao = <?php echo json_encode($temPermissaoPresidencia); ?>;

        // Gráficos Chart.js
        let chartDiaSemana = null;
        let chartTempoProcessamento = null;

        // Configurações (podem ser salvas no localStorage)
        const configuracoes = {
            notificacoes: {
                novoDoc: true,
                urgente: true,
                relatorio: false
            },
            assinatura: {
                metodo: 'digital',
                obsPadrao: ''
            },
            interface: {
                autoUpdate: true,
                docsPorPagina: 20
            }
        };

        // Debounce para filtros
        function debounce(func, wait) {
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

        const debouncedFilter = debounce(filtrarDocumentos, 300);

        // FUNÇÃO ROBUSTA PARA INICIALIZAR DROPDOWN DO USUÁRIO
        function initializeUserDropdown() {
            console.log('🎯 Inicializando dropdown do usuário na presidência...');
            
            // Diferentes possibilidades de seletores
            const menuSelectors = [
                '#userMenu',
                '.user-menu-btn',
                '[data-user-menu]',
                '.user-profile-btn',
                '.user-avatar'
            ];
            
            const dropdownSelectors = [
                '#userDropdown',
                '.user-dropdown',
                '[data-user-dropdown]',
                '.user-menu-dropdown'
            ];
            
            let userMenu = null;
            let userDropdown = null;
            
            // Procura pelo botão do menu
            for (const selector of menuSelectors) {
                userMenu = document.querySelector(selector);
                if (userMenu) {
                    console.log('✅ Menu encontrado com seletor:', selector);
                    break;
                }
            }
            
            // Procura pelo dropdown
            for (const selector of dropdownSelectors) {
                userDropdown = document.querySelector(selector);
                if (userDropdown) {
                    console.log('✅ Dropdown encontrado com seletor:', selector);
                    break;
                }
            }
            
            if (userMenu && userDropdown) {
                // Remove listeners antigos se existirem
                userMenu.removeEventListener('click', handleUserMenuClick);
                document.removeEventListener('click', handleDocumentClick);
                
                // Adiciona novos listeners
                userMenu.addEventListener('click', handleUserMenuClick);
                document.addEventListener('click', handleDocumentClick);
                
                console.log('✅ User dropdown inicializado com sucesso na presidência!');
                
                // Função para lidar com clique no menu
                function handleUserMenuClick(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isVisible = userDropdown.classList.contains('show');
                    
                    // Fecha outros dropdowns abertos
                    document.querySelectorAll('.user-dropdown.show').forEach(dropdown => {
                        if (dropdown !== userDropdown) {
                            dropdown.classList.remove('show');
                        }
                    });
                    
                    // Alterna o dropdown atual
                    userDropdown.classList.toggle('show', !isVisible);
                    
                    console.log('Dropdown toggled:', !isVisible);
                }
                
                // Função para lidar com cliques no documento
                function handleDocumentClick(e) {
                    if (!userMenu.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('show');
                    }
                }
                
            } else {
                console.warn('⚠️ Elementos do dropdown não encontrados na presidência');
                console.log('Elementos com ID disponíveis:', 
                    Array.from(document.querySelectorAll('[id]')).map(el => `#${el.id}`));
                console.log('Elementos com classes de usuário:', 
                    Array.from(document.querySelectorAll('[class*="user"]')).map(el => el.className));
            }
        }

        // Inicialização - CORRIGIDA
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa AOS
            AOS.init({
                duration: 800,
                once: true
            });

            // INICIALIZA DROPDOWN DO USUÁRIO - VERSÃO ROBUSTA
            initializeUserDropdown();
            
            // Tenta novamente após delays (caso elementos sejam carregados assincronamente)
            setTimeout(initializeUserDropdown, 500);
            setTimeout(initializeUserDropdown, 1000);
            setTimeout(initializeUserDropdown, 2000);

            // Debug inicial DETALHADO
            console.log('=== DEBUG PRESIDÊNCIA FRONTEND DETALHADO ===');
            const usuario = <?php echo json_encode($usuarioLogado); ?>;
            const isDiretor = <?php echo json_encode($auth->isDiretor()); ?>;
            
            console.log('👤 Usuário completo:', usuario);
            console.log('🏢 Departamento ID:', usuario.departamento_id, '(tipo:', typeof usuario.departamento_id, ')');
            console.log('👔 É diretor:', isDiretor);
            console.log('🔐 Tem permissão:', temPermissao);
            console.log('🎯 Botão Funcionários deve aparecer:', temPermissao ? 'SIM' : 'NÃO');
            
            // Teste das comparações
            console.log('🧪 Testes de comparação:');
            console.log('  departamento_id == 1:', usuario.departamento_id == 1);
            console.log('  departamento_id === 1:', usuario.departamento_id === 1);
            console.log('  departamento_id === "1":', usuario.departamento_id === "1");
            
            // Resultado final da lógica
            const resultadoLogica = usuario.departamento_id == 1;
            console.log('📋 Lógica de acesso (dept==1):', resultadoLogica);
            console.log('📋 Permissão PHP vs JS:', temPermissao, '===', resultadoLogica, '?', temPermissao === resultadoLogica);
            
            console.log('🔗 URL da API:', '../api/documentos/documentos_presidencia_listar.php');
            
            // Só continuar se tiver permissão
            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                console.log('💡 Para debug detalhado, clique no botão "Debug Detalhado" na tela');
                return;
            }

            console.log('✅ Usuário autorizado - carregando funcionalidades...');

            // Carregar configurações do localStorage
            carregarConfiguracoes();
            
            // Definir datas padrão nos relatórios
            const hoje = new Date();
            const mesPassado = new Date(hoje.getFullYear(), hoje.getMonth() - 1, hoje.getDate());
            document.getElementById('relatorioDataInicio').value = mesPassado.toISOString().split('T')[0];
            document.getElementById('relatorioDataFim').value = hoje.toISOString().split('T')[0];

            // Carregar documentos automaticamente
            carregarDocumentosPendentes();
            configurarFiltros();
            configurarUpload();
            configurarMetodoAssinatura();
            configurarEventos();
            configurarFiltrosZapSign();
    
            // Carregar documentos ZapSign em vez dos documentos locais
            if (temPermissao) {
                carregarDocumentosZapSign(true);
            }


                // Carregar estatísticas ZapSign se tiver permissão
    if (temPermissao) {
        // Aguardar um pouco para não sobrecarregar
        setTimeout(carregarEstatisticasZapSign, 1000);
        
        // Configurar refresh automático das estatísticas (a cada 5 minutos)
        setInterval(carregarEstatisticasZapSign, 5 * 60 * 1000);
    }
    
    // Adicionar botão de refresh manual nas estatísticas se não existir
    const statsGrid = document.querySelector('.stats-grid');
    if (statsGrid && temPermissao) {
        const refreshBtn = document.createElement('button');
        refreshBtn.className = 'btn btn-sm btn-outline-secondary position-absolute';
        refreshBtn.style.cssText = 'top: 10px; right: 10px; z-index: 10;';
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        refreshBtn.title = 'Atualizar estatísticas';
        refreshBtn.onclick = forcarAtualizacaoEstatisticas;
        
        statsGrid.style.position = 'relative';
        statsGrid.appendChild(refreshBtn);
    }
            
            // Iniciar auto-update se configurado
            if (configuracoes.interface.autoUpdate) {
                autoUpdater.start();
            }
        });

        // ===== FUNÇÕES PRINCIPAIS - CORRIGIDAS =====

        // Carregar documentos pendentes - CORRIGIDA
async function carregarDocumentosPendentes() {
    await carregarDocumentosZapSign(true);
}


if (typeof autoUpdater !== 'undefined') {
    const originalStart = autoUpdater.start;
    autoUpdater.start = function() {
        originalStart.call(this);
        
        // Também atualizar estatísticas periodicamente
        this.statsTimer = setInterval(carregarEstatisticasZapSign, 10 * 60 * 1000); // 10 minutos
    };
    
    const originalStop = autoUpdater.stop;
    autoUpdater.stop = function() {
        originalStop.call(this);
        
        if (this.statsTimer) {
            clearInterval(this.statsTimer);
            this.statsTimer = null;

        }
    };
}
        // Renderizar documentos
        function renderizarDocumentos(documentos) {
            const container = document.getElementById('documentsList');
            
            if (!container) {
                console.error('Container de documentos não encontrado');
                return;
            }
            
            container.innerHTML = '';

            if (documentos.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle empty-state-icon"></i>
                        <h5 class="empty-state-title">Tudo em dia!</h5>
                        <p class="empty-state-description">
                            Não há documentos pendentes de assinatura no momento.
                        </p>
                    </div>
                `;
                return;
            }

            documentos.forEach(doc => {
                const urgente = doc.dias_em_processo > 3;
                const itemDiv = document.createElement('div');
                itemDiv.className = 'document-item';
                itemDiv.dataset.docId = doc.id;
                
                itemDiv.innerHTML = `
                    <div class="document-content">
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center">
            <i class="fas fa-file-pdf text-danger me-3"></i>
            <div>
                <h6 class="mb-1 fw-semibold">${escapeHtml(doc.name)}</h6>
                <span class="badge ${doc.status} badge-sm">${statusIcon} ${doc.status_label}</span>
            </div>
        </div>
        <div class="document-actions">
            ${actionButtons}
        </div>
    </div>
    
    <!-- Grid de informações distribuído -->
    <div class="d-flex justify-content-between align-items-center flex-wrap small mb-3">
        <div class="d-flex align-items-center me-4 mb-2">
            <i class="fas fa-calendar-plus text-primary me-2"></i>
            <div>
                <span class="text-muted">Criado em:</span>
                <span class="text-dark fw-medium ms-1">${doc.created_at_formatted}</span>
            </div>
        </div>
        
        <div class="d-flex align-items-center me-4 mb-2">
            <i class="fas fa-clock text-info me-2"></i>
            <div>
                <span class="text-muted">Atualizado:</span>
                <span class="text-dark fw-medium ms-1">${doc.last_update_formatted}</span>
            </div>
        </div>
        
        <div class="d-flex align-items-center me-4 mb-2">
            <i class="fas fa-hourglass-half text-warning me-2"></i>
            <div>
                <span class="text-muted">Tempo:</span>
                <span class="text-dark fw-medium ms-1">${doc.tempo_desde_criacao}</span>
            </div>
        </div>
        
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-folder text-secondary me-2"></i>
            <div>
                <span class="text-muted">Pasta:</span>
                <span class="text-dark fw-medium ms-1" title="${doc.folder_path}">${doc.folder_path}</span>
            </div>
        </div>
    </div>
    
    <!-- Linha separadora e informação do associado -->
    ${doc.associado?.id ? `
        <div class="border-top pt-2">
            <div class="d-flex align-items-center justify-content-between flex-wrap small">
                <div class="d-flex align-items-center">
                    <i class="fas fa-user text-primary me-2"></i>
                    <span class="text-muted me-2">Associado ID:</span>
                    <span class="fw-semibold text-dark">${doc.associado.id}</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <span class="text-muted me-1">Situação:</span>
                        <span class="badge bg-secondary">${escapeHtml(doc.associado.situacao || 'N/A')}</span>
                    </div>
                    <div>
                        <span class="text-muted me-1">Filiação:</span>
                        <span class="text-dark">${formatarData(doc.associado.data_filiacao)}</span>
                    </div>
                </div>
            </div>
        </div>
    ` : `
        <div class="border-top pt-2">
            <div class="d-flex align-items-center small">
                <i class="fas fa-info-circle text-secondary me-2"></i>
                <span class="text-muted">Documento não vinculado a nenhum associado</span>
            </div>
        </div>
    `}
</div>
                `;
                container.appendChild(itemDiv);
            });
        }

        // Configurar filtros com debounce
        function configurarFiltros() {
            const searchInput = document.getElementById('searchInput');
            const filterUrgencia = document.getElementById('filterUrgencia');
            const filterOrigem = document.getElementById('filterOrigem');

            if (searchInput) searchInput.addEventListener('input', debouncedFilter);
            if (filterUrgencia) filterUrgencia.addEventListener('change', filtrarDocumentos);
            if (filterOrigem) filterOrigem.addEventListener('change', filtrarDocumentos);
        }

        // Filtrar documentos
        function filtrarDocumentos() {
            const searchInput = document.getElementById('searchInput');
            const filterUrgencia = document.getElementById('filterUrgencia');
            const filterOrigem = document.getElementById('filterOrigem');
            
            if (!searchInput || !filterUrgencia || !filterOrigem) return;
            
            const termo = searchInput.value.toLowerCase();
            const urgencia = filterUrgencia.value;
            const origem = filterOrigem.value;

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

        // ===== FUNÇÕES DE ASSINATURA =====

        // Abrir modal de assinatura
        function abrirModalAssinatura(documentoId) {
            documentoSelecionado = documentosPendentes.find(doc => doc.id === documentoId);
            
            if (!documentoSelecionado) {
                notifications.show('Documento não encontrado', 'error');
                return;
            }

            // Preencher informações do documento
            document.getElementById('documentoId').value = documentoId;
            document.getElementById('previewAssociado').textContent = documentoSelecionado.associado_nome;
            document.getElementById('previewCPF').textContent = formatarCPF(documentoSelecionado.associado_cpf);
            document.getElementById('previewData').textContent = formatarData(documentoSelecionado.data_upload);
            document.getElementById('previewOrigem').textContent = documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Físico';
            document.getElementById('previewSubtitulo').textContent = documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Gerado pelo sistema' : 'Digitalizado';

            // Aplicar configurações
            document.querySelector(`input[name="metodoAssinatura"][value="${configuracoes.assinatura.metodo}"]`).checked = true;
            document.getElementById('observacoes').value = configuracoes.assinatura.obsPadrao || '';
            
            // Resetar upload
            document.getElementById('uploadSection').classList.add('d-none');
            document.getElementById('fileInfo').innerHTML = '';
            arquivoAssinado = null;

            new bootstrap.Modal(document.getElementById('assinaturaModal')).show();
        }

        // Validação robusta de arquivo
        function validarArquivoAssinatura(file) {
            const errors = [];
            
            if (!file) {
                errors.push('Nenhum arquivo selecionado');
                return errors;
            }
            
            // Validar tipo
            if (file.type !== 'application/pdf') {
                errors.push('Apenas arquivos PDF são permitidos');
            }
            
            // Validar tamanho (10MB)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                errors.push(`Arquivo muito grande. Máximo: ${formatBytes(maxSize)}`);
            }
            
            // Validar nome do arquivo
            if (!/^[a-zA-Z0-9._-]+\.pdf$/i.test(file.name)) {
                errors.push('Nome do arquivo contém caracteres inválidos');
            }
            
            return errors;
        }

        // Confirmar assinatura com melhorias
        async function confirmarAssinatura() {
            const documentoId = document.getElementById('documentoId').value;
            const observacoes = document.getElementById('observacoes').value.trim();
            const metodo = document.querySelector('input[name="metodoAssinatura"]:checked').value;
            
            // Validações
            if (!documentoId) {
                notifications.show('ID do documento não encontrado', 'error');
                return;
            }
            
            if (metodo === 'upload' && !arquivoAssinado) {
                notifications.show('Por favor, selecione o arquivo assinado', 'warning');
                return;
            }
            
            // Validar arquivo se upload
            if (metodo === 'upload') {
                const validationErrors = validarArquivoAssinatura(arquivoAssinado);
                if (validationErrors.length > 0) {
                    notifications.show(validationErrors.join('<br>'), 'error');
                    return;
                }
            }
            
            // Confirmação para documentos urgentes
            if (documentoSelecionado && documentoSelecionado.dias_em_processo > 7) {
                const confirmar = await showConfirmDialog(
                    'Documento com Atraso',
                    `Este documento está aguardando há ${documentoSelecionado.dias_em_processo} dias. Deseja prosseguir com a assinatura?`
                );
                if (!confirmar) return;
            }
            
            const btnAssinar = event.target;
            const originalContent = btnAssinar.innerHTML;
            
            try {
                // Loading state
                btnAssinar.disabled = true;
                btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';
                
                const formData = new FormData();
                formData.append('documento_id', documentoId);
                formData.append('observacao', observacoes);
                formData.append('metodo', metodo);
                
                if (arquivoAssinado) {
                    formData.append('arquivo_assinado', arquivoAssinado);
                }
                
                const response = await fetch('../api/documentos/documentos_assinar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('assinaturaModal')).hide();
                    notifications.show('Documento assinado com sucesso!', 'success');
                    
                    // Limpar cache e recarregar
                    cache.clear();
                    await carregarDocumentosPendentes();
                    
                    // Log para auditoria
                    console.log(`Documento ${documentoId} assinado pelo usuário ${<?php echo json_encode($usuarioLogado['nome']); ?>}`);
                    
                } else {
                    throw new Error(result.message || 'Erro desconhecido');
                }
                
            } catch (error) {
                console.error('Erro ao assinar documento:', error);
                notifications.show('Erro ao assinar documento: ' + error.message, 'error');
            } finally {
                btnAssinar.disabled = false;
                btnAssinar.innerHTML = originalContent;
            }
        }

        // Função auxiliar para confirmações
        function showConfirmDialog(title, message) {
            return new Promise((resolve) => {
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>${message}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="confirmBtn">Confirmar</button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                const bsModal = new bootstrap.Modal(modal);
                
                modal.querySelector('#confirmBtn').addEventListener('click', () => {
                    modal.dataset.resolved = 'true';
                    resolve(true);
                    bsModal.hide();
                });
                
                modal.addEventListener('hidden.bs.modal', () => {
                    if (!modal.dataset.resolved) resolve(false);
                    modal.remove();
                });
                
                bsModal.show();
            });
        }

        // Assinar todos com melhorias
        async function assinarTodos() {
            const documentosParaAssinar = documentosPendentes.filter(doc => {
                return true; // Pode adicionar filtros aqui
            });

            if (documentosParaAssinar.length === 0) {
                notifications.show('Não há documentos para assinar', 'warning');
                return;
            }

            if (documentosParaAssinar.length > 10) {
                const confirmar = await showConfirmDialog(
                    'Assinatura em Lote',
                    `Você está prestes a assinar ${documentosParaAssinar.length} documentos. Deseja continuar?`
                );
                if (!confirmar) return;
            }

            // Mostrar modal de confirmação
            const listaHtml = documentosParaAssinar.map(doc => `
                <div class="mb-2">
                    <i class="fas fa-file-pdf text-danger me-2"></i>
                    ${doc.associado_nome} - CPF: ${formatarCPF(doc.associado_cpf)}
                </div>
            `).join('');

            document.getElementById('documentosLoteLista').innerHTML = listaHtml;
            new bootstrap.Modal(document.getElementById('assinaturaLoteModal')).show();
        }

        // Confirmar assinatura em lote
        async function confirmarAssinaturaLote() {
            const observacoes = document.getElementById('observacoesLote').value;
            const documentosIds = documentosPendentes.map(doc => doc.id);

            const btnAssinar = event.target;
            const originalContent = btnAssinar.innerHTML;
            
            try {
                // Loading state
                btnAssinar.disabled = true;
                btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';

                const response = await fetch('../api/documentos/documentos_assinar_lote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        documentos_ids: documentosIds,
                        observacao: observacoes
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('assinaturaLoteModal')).hide();
                    notifications.show(`${result.assinados} documentos assinados com sucesso!`, 'success');
                    
                    // Limpar cache e recarregar
                    cache.clear();
                    await carregarDocumentosPendentes();
                } else {
                    throw new Error(result.message || 'Erro desconhecido');
                }
                
            } catch (error) {
                console.error('Erro ao assinar documentos:', error);
                notifications.show('Erro ao assinar documentos: ' + error.message, 'error');
            } finally {
                btnAssinar.disabled = false;
                btnAssinar.innerHTML = originalContent;
            }
        }

        // ===== FUNÇÕES DE RELATÓRIOS =====

        async function abrirRelatorios() {
            const modal = new bootstrap.Modal(document.getElementById('relatoriosModal'));
            modal.show();
            
            // Carregar relatórios ao abrir
            await carregarRelatorios();
        }

        async function carregarRelatorios() {
            const dataInicio = document.getElementById('relatorioDataInicio').value;
            const dataFim = document.getElementById('relatorioDataFim').value;
            
            if (!dataInicio || !dataFim) {
                notifications.show('Por favor, selecione o período', 'warning');
                return;
            }
            
            try {
                const response = await fetch('../api/documentos/relatorio_produtividade.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        data_inicio: dataInicio,
                        data_fim: dataFim
                    })
                });
                
                if (!response.ok) throw new Error('Erro ao carregar relatórios');
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    renderizarRelatorios(data.data);
                } else {
                    throw new Error(data.message || 'Erro ao processar relatórios');
                }
                
            } catch (error) {
                console.error('Erro:', error);
                notifications.show('Erro ao carregar relatórios: ' + error.message, 'error');
            }
        }

        function renderizarRelatorios(dados) {
            // Estatísticas resumidas
            const resumoHtml = `
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-mini-value">${dados.resumo?.total_processados || 0}</div>
                        <div class="stat-mini-label">Total Processados</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-mini-value">${Math.round(dados.resumo?.tempo_medio || 0)}h</div>
                        <div class="stat-mini-label">Tempo Médio</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-mini-value">${Math.round(dados.resumo?.tempo_minimo || 0)}h</div>
                        <div class="stat-mini-label">Tempo Mínimo</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-mini-value">${Math.round(dados.resumo?.tempo_maximo || 0)}h</div>
                        <div class="stat-mini-label">Tempo Máximo</div>
                    </div>
                </div>
            `;
            document.getElementById('estatisticasResumo').innerHTML = resumoHtml;
            
            // Gráfico por dia da semana
            if (dados.por_dia_semana) {
                renderizarGraficoDiaSemana(dados.por_dia_semana);
            }
            
            // Gráfico de tempo de processamento
            if (dados.por_origem) {
                renderizarGraficoTempoProcessamento(dados.por_origem);
            }
            
            // Tabela de produtividade
            if (dados.por_funcionario) {
                renderizarTabelaProdutividade(dados.por_funcionario);
            }
        }

        function renderizarGraficoDiaSemana(dados) {
            const ctx = document.getElementById('chartDiaSemana').getContext('2d');
            
            if (chartDiaSemana) {
                chartDiaSemana.destroy();
            }
            
            const diasSemana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            
            chartDiaSemana = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dados.map(d => diasSemana[d.dia_numero - 1]),
                    datasets: [{
                        label: 'Documentos Assinados',
                        data: dados.map(d => d.total),
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function renderizarGraficoTempoProcessamento(dados) {
            const ctx = document.getElementById('chartTempoProcessamento').getContext('2d');
            
            if (chartTempoProcessamento) {
                chartTempoProcessamento.destroy();
            }
            
            chartTempoProcessamento = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: dados.map(d => d.tipo_origem),
                    datasets: [{
                        label: 'Tempo Médio (horas)',
                        data: dados.map(d => Math.round(d.tempo_medio)),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.5)',
                            'rgba(54, 162, 235, 0.5)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        function renderizarTabelaProdutividade(dados) {
            const tbody = document.querySelector('#tabelaProdutividade tbody');
            tbody.innerHTML = '';
            
            dados.forEach(func => {
                const eficiencia = func.tempo_medio < 24 ? 'Alta' : func.tempo_medio < 48 ? 'Média' : 'Baixa';
                const corEficiencia = func.tempo_medio < 24 ? 'success' : func.tempo_medio < 48 ? 'warning' : 'danger';
                
                tbody.innerHTML += `
                    <tr>
                        <td>${func.funcionario}</td>
                        <td>${func.total_assinados}</td>
                        <td>${Math.round(func.tempo_medio)}h</td>
                        <td><span class="badge bg-${corEficiencia}">${eficiencia}</span></td>
                    </tr>
                `;
            });
        }

        async function exportarRelatorio(formato) {
            const dataInicio = document.getElementById('relatorioDataInicio').value;
            const dataFim = document.getElementById('relatorioDataFim').value;
            
            if (!dataInicio || !dataFim) {
                notifications.show('Por favor, selecione o período', 'warning');
                return;
            }
            
            notifications.show(`Exportação em ${formato.toUpperCase()} em desenvolvimento`, 'info');
            
            // TODO: Implementar exportação real
            // window.open(`../api/documentos/exportar_relatorio.php?formato=${formato}&inicio=${dataInicio}&fim=${dataFim}`);
        }

        // ===== FUNÇÕES DE HISTÓRICO =====

        async function verHistorico() {
            const modal = new bootstrap.Modal(document.getElementById('historicoModal'));
            modal.show();
            
            // Carregar histórico ao abrir
            await carregarHistorico();
        }

        async function carregarHistorico() {
            const periodo = document.getElementById('filtroPeriodoHistorico').value;
            const funcionarioId = document.getElementById('filtroFuncionarioHistorico').value;
            
            try {
                const params = new URLSearchParams({
                    periodo: periodo,
                    funcionario_id: funcionarioId || ''
                });
                
                const response = await fetch(`../api/documentos/historico_assinaturas.php?${params}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (!response.ok) throw new Error('Erro ao carregar histórico');
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    renderizarHistorico(data.data);
                } else {
                    throw new Error(data.message || 'Erro ao processar histórico');
                }
                
            } catch (error) {
                console.error('Erro:', error);
                notifications.show('Erro ao carregar histórico: ' + error.message, 'error');
            }
        }

        function renderizarHistorico(dados) {
            // Timeline
            let timelineHtml = '';
            
            if (dados.historico && dados.historico.length > 0) {
                dados.historico.forEach(item => {
                    const data = new Date(item.data_assinatura);
                    const tempoProcessamento = item.tempo_processamento || 0;
                    
                    timelineHtml += `
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">${item.associado_nome}</h6>
                                        <p class="text-muted mb-0">
                                            <small>
                                                CPF: ${formatarCPF(item.associado_cpf)} | 
                                                Origem: ${item.tipo_origem}
                                            </small>
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            ${data.toLocaleDateString('pt-BR')} às ${data.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})}
                                        </small>
                                        <br>
                                        <span class="badge bg-info">
                                            <i class="fas fa-clock"></i> ${tempoProcessamento}h
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            } else {
                timelineHtml = '<p class="text-center text-muted">Nenhuma assinatura encontrada no período</p>';
            }
            
            document.getElementById('timelineHistorico').innerHTML = timelineHtml;
            
            // Resumo do período
            const resumoHtml = `
                <div class="col-md-3 text-center">
                    <h2 class="text-primary">${dados.resumo?.total_assinados || 0}</h2>
                    <p class="text-muted">Total Assinados</p>
                </div>
                <div class="col-md-3 text-center">
                    <h2 class="text-info">${Math.round(dados.resumo?.tempo_medio || 0)}h</h2>
                    <p class="text-muted">Tempo Médio</p>
                </div>
                <div class="col-md-3 text-center">
                    <h2 class="text-success">${dados.resumo?.origem_fisica || 0}</h2>
                    <p class="text-muted">Origem Física</p>
                </div>
                <div class="col-md-3 text-center">
                    <h2 class="text-warning">${dados.resumo?.origem_virtual || 0}</h2>
                    <p class="text-muted">Origem Virtual</p>
                </div>
            `;
            
            document.getElementById('resumoHistorico').innerHTML = resumoHtml;
        }

        function imprimirHistorico() {
            window.print();
        }

        // ===== FUNÇÕES DE CONFIGURAÇÕES =====

        function configurarAssinatura() {
            // Carregar configurações atuais
            document.getElementById('notifNovoDoc').checked = configuracoes.notificacoes.novoDoc;
            document.getElementById('notifUrgente').checked = configuracoes.notificacoes.urgente;
            document.getElementById('notifRelatorio').checked = configuracoes.notificacoes.relatorio;
            document.getElementById('configMetodoAssinatura').value = configuracoes.assinatura.metodo;
            document.getElementById('configObsPadrao').value = configuracoes.assinatura.obsPadrao;
            document.getElementById('configAutoUpdate').checked = configuracoes.interface.autoUpdate;
            document.getElementById('configDocsPorPagina').value = configuracoes.interface.docsPorPagina;
            
            const modal = new bootstrap.Modal(document.getElementById('configuracoesModal'));
            modal.show();
        }

        function salvarConfiguracoes() {
            // Coletar valores
            configuracoes.notificacoes.novoDoc = document.getElementById('notifNovoDoc').checked;
            configuracoes.notificacoes.urgente = document.getElementById('notifUrgente').checked;
            configuracoes.notificacoes.relatorio = document.getElementById('notifRelatorio').checked;
            configuracoes.assinatura.metodo = document.getElementById('configMetodoAssinatura').value;
            configuracoes.assinatura.obsPadrao = document.getElementById('configObsPadrao').value;
            configuracoes.interface.autoUpdate = document.getElementById('configAutoUpdate').checked;
            configuracoes.interface.docsPorPagina = parseInt(document.getElementById('configDocsPorPagina').value);
            
            // Salvar no localStorage
            localStorage.setItem('configuracoes_presidencia', JSON.stringify(configuracoes));
            
            // Aplicar configurações
            if (configuracoes.interface.autoUpdate) {
                autoUpdater.start();
            } else {
                autoUpdater.stop();
            }
            
            bootstrap.Modal.getInstance(document.getElementById('configuracoesModal')).hide();
            notifications.show('Configurações salvas com sucesso!', 'success');
        }

        function carregarConfiguracoes() {
            const saved = localStorage.getItem('configuracoes_presidencia');
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    Object.assign(configuracoes, parsed);
                } catch (e) {
                    console.error('Erro ao carregar configurações:', e);
                }
            }
        }

        // ===== CONFIGURAÇÕES E EVENTOS =====

        // Configurar método de assinatura
        function configurarMetodoAssinatura() {
            const radios = document.querySelectorAll('input[name="metodoAssinatura"]');
            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    const metodo = this.value;
                    const uploadSection = document.getElementById('uploadSection');
                    
                    if (metodo === 'upload') {
                        uploadSection.classList.remove('d-none');
                    } else {
                        uploadSection.classList.add('d-none');
                        arquivoAssinado = null;
                        document.getElementById('fileInfo').innerHTML = '';
                    }
                });
            });
        }

        // Configurar upload
        function configurarUpload() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');

            if (!uploadArea || !fileInput) return;

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

            const validationErrors = validarArquivoAssinatura(file);
            if (validationErrors.length > 0) {
                notifications.show(validationErrors.join('<br>'), 'error');
                return;
            }

            arquivoAssinado = file;

            document.getElementById('fileInfo').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-file-pdf me-2"></i>
                    <strong>${file.name}</strong> (${formatBytes(file.size)})
                    <button type="button" class="btn-close float-end" onclick="removerArquivo()"></button>
                </div>
            `;
        }

        // Configurar eventos globais
        function configurarEventos() {
            // Pausar auto-update quando modal estiver aberto
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', () => autoUpdater.pause());
                modal.addEventListener('hidden.bs.modal', () => autoUpdater.resume());
            });
            
            // Pausar quando página não estiver visível
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    autoUpdater.pause();
                } else {
                    autoUpdater.resume();
                }
            });

            // Atalhos de teclado
            document.addEventListener('keydown', function(e) {
                // ESC para fechar modais
                if (e.key === 'Escape') {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        bootstrap.Modal.getInstance(modal).hide();
                    });
                }
                
                // Ctrl+R para atualizar lista
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    cache.clear();
                    carregarDocumentosPendentes();
                }
            });
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Remover arquivo
        function removerArquivo() {
            arquivoAssinado = null;
            document.getElementById('fileInfo').innerHTML = '';
            document.getElementById('fileInput').value = '';
        }

        // Visualizar documento
        function visualizarDocumento(documentoId) {
            if (!documentoId && documentoSelecionado) {
                documentoId = documentoSelecionado.id;
            }
            
            window.open(`../api/documentos/documentos_download.php?id=${documentoId}`, '_blank');
        }

        // Atualizar lista
        function atualizarLista() {
            cache.clear();
            carregarDocumentosPendentes();
        }


        // Placeholder functions para ações rápidas
        function abrirRelatorios() {
            window.location.href = 'relatorios.php';
        }

        function verHistorico() {
            notifications.show('Funcionalidade de histórico em desenvolvimento', 'info');
        }

        function configurarAssinatura() {
            notifications.show('Funcionalidade de configurações em desenvolvimento', 'info');
        }

        // Função de debug completo para diagnosticar problemas de acesso
        function mostrarDebugCompleto() {
            const usuario = <?php echo json_encode($usuarioLogado); ?>;
            const isDiretor = <?php echo json_encode($auth->isDiretor()); ?>;
            const temPermissao = <?php echo json_encode($temPermissaoPresidencia); ?>;
            
            let debugHtml = `
                <div class="debug-completo">
                    <h6><i class="fas fa-bug"></i> Debug Completo de Permissões</h6>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Dados do Usuário:</h6>
                            <pre class="bg-light p-2 small">${JSON.stringify(usuario, null, 2)}</pre>
                        </div>
                        <div class="col-md-6">
                            <h6>Verificações:</h6>
                            <ul class="small">
                                <li><strong>É Diretor:</strong> ${isDiretor ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Departamento ID:</strong> ${usuario.departamento_id} (tipo: ${typeof usuario.departamento_id})</li>
                                <li><strong>Departamento == 1:</strong> ${usuario.departamento_id == 1 ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Departamento === 1:</strong> ${usuario.departamento_id === 1 ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Departamento === '1':</strong> ${usuario.departamento_id === '1' ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Tem Permissão Final:</strong> ${temPermissao ? 'SIM ✅' : 'NÃO ❌'}</li>
                            </ul>
                            
                            <div class="mt-3">
                                <strong>Regra de Acesso:</strong><br>
                                <code>departamento_id == 1</code><br><br>
                                
                                <strong>Resultado:</strong><br>
                                <code>${usuario.departamento_id == 1}</code>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <small class="text-muted">
                        <strong>Dica:</strong> Se você deveria ter acesso mas não consegue, verifique:
                        <br>1. Se seu departamento_id está correto no banco de dados (deve ser 1 para presidência)
                        <br>2. Se não há cache ou sessão antiga
                        <br>3. Se os logs do servidor mostram algum erro
                    </small>
                </div>
            `;
            
            // Criar modal customizado
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Debug de Permissões</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${debugHtml}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                                <i class="fas fa-sync me-1"></i>
                                Recarregar Página
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }
        async function executarDebug() {
            console.log('🔍 EXECUTANDO DEBUG SISTEMA...');
            
            const debugInfo = {
                usuario: <?php echo json_encode($usuarioLogado); ?>,
                timestamp: new Date().toISOString(),
                documentosCarregados: documentosPendentes.length,
                cacheAtivo: cache.cache.size,
                autoUpdateAtivo: autoUpdater.isActive,
                temPermissao: temPermissao
            };
            
            console.log('📊 Info do Sistema:', debugInfo);
            
            let debugReport = `
                <div class="debug-report">
                    <h6><i class="fas fa-info-circle"></i> Debug do Sistema</h6>
                    <small class="text-muted">Timestamp: ${debugInfo.timestamp}</small>
                    
                    <div class="mt-3">
                        <strong>👤 Usuário:</strong><br>
                        Nome: ${debugInfo.usuario.nome}<br>
                        Cargo: ${debugInfo.usuario.cargo || 'N/A'}<br>
                        Departamento ID: ${debugInfo.usuario.departamento_id || 'N/A'}<br>
                        Tem permissão: ${debugInfo.temPermissao ? 'Sim' : 'Não'}
                    </div>
                    
                    <div class="mt-3">
                        <strong>📁 Documentos:</strong><br>
                        Carregados: ${debugInfo.documentosCarregados}<br>
                        Cache: ${debugInfo.cacheAtivo} itens<br>
                        Auto-update: ${debugInfo.autoUpdateAtivo ? 'Ativo' : 'Inativo'}
                    </div>
                    
                    <div class="mt-3">
                        <strong>🔗 API Status:</strong><br>
                        <div id="debugApiStatus">Testando...</div>
                    </div>
                </div>
            `;
            
            notifications.show(debugReport, 'info', 15000);
            
            // Teste simples da API apenas se tem permissão
            if (temPermissao) {
                try {
                    const response = await fetch('../api/documentos/documentos_presidencia_listar.php?status=AGUARDANDO_ASSINATURA');
                    const status = response.status;
                    
                    document.getElementById('debugApiStatus').innerHTML = `
                        <span class="${status === 200 ? 'text-success' : 'text-danger'}">
                            <i class="fas fa-${status === 200 ? 'check' : 'times'}"></i>
                            API Documentos: ${status} ${response.statusText}
                        </span>
                    `;
                    
                } catch (error) {
                    document.getElementById('debugApiStatus').innerHTML = `
                        <span class="text-danger">
                            <i class="fas fa-times"></i>
                            API Documentos: Erro - ${error.message}
                        </span>
                    `;
                }
            } else {
                document.getElementById('debugApiStatus').innerHTML = `
                    <span class="text-warning">
                        <i class="fas fa-lock"></i>
                        API Documentos: Sem permissão para testar
                    </span>
                `;
            }
            
            console.log('🔍 DEBUG FINALIZADO');
        }

        // Atualizar contadores
        function atualizarContadores() {
            const totalPendentes = documentosPendentes.length;
            if (window.updateNotificationCount) {
                window.updateNotificationCount(totalPendentes);
            }
        }

        // Funções de formatação
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
            notifications.show(mensagem, 'success');
        }

        function mostrarErro(mensagem) {
            notifications.show(mensagem, 'error');
            
            const container = document.getElementById('documentsList');
            if (container) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle empty-state-icon"></i>
                        <h5 class="empty-state-title">Erro</h5>
                        <p class="empty-state-description">${mensagem}</p>
                        <button class="btn-action primary mt-3" onclick="carregarDocumentosPendentes()">
                            <i class="fas fa-redo"></i>
                            Tentar Novamente
                        </button>
                    </div>
                `;
            }
        }


    </script>
</body>

</html>