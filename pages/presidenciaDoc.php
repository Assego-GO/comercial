<?php
/**
 * Página da Presidência - Assinatura de Documentos
 * pages/presidencia.php
 * 
 * VERSÃO CORRIGIDA: Usa sistema interno de documentos com funcionalidades completas
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

// NOVA VALIDAÇÃO: APENAS usuários do departamento da presidência (ID: 1) OU diretores
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    
    // Debug dos testes de comparação
    error_log("Testes de comparação:");
    error_log("  deptId === '1': " . ($deptId === '1' ? 'true' : 'false'));
    error_log("  deptId === 1: " . ($deptId === 1 ? 'true' : 'false'));
    error_log("  deptId == 1: " . ($deptId == 1 ? 'true' : 'false'));
    error_log("  isDiretor: " . ($auth->isDiretor() ? 'true' : 'false'));
    
    if ($deptId == 1 || $auth->isDiretor()) { // Presidência (ID=1) OU Diretor
        $temPermissaoPresidencia = true;
        error_log("✅ Permissão concedida: Usuário da Presidência ou Diretor");
    } else {
        $motivoNegacao = 'Acesso restrito ao departamento da Presidência ou Diretores.';
        error_log("❌ Acesso negado. Departamento: '$deptId' (tipo: " . gettype($deptId) . "). Necessário: Presidência (ID = 1) ou ser Diretor");
    }
} else {
    $motivoNegacao = 'Departamento não identificado. Acesso restrito ao departamento da Presidência.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Log final do resultado
if (!$temPermissaoPresidencia) {
    error_log("❌ ACESSO NEGADO: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO - Usuário da Presidência ou Diretor");
}

// Busca estatísticas de documentos (apenas se tem permissão)
if ($temPermissaoPresidencia) {
    try {
        $documentos = new Documentos();
        if (method_exists($documentos, 'getEstatisticasFluxo')) {
            $statsFluxo = $documentos->getEstatisticasFluxo();
            
            $aguardandoAssinatura = 0;
            $assinadosHoje = 0;
            $assinadosMes = 0;
            $tempoMedio = 0;
            
            // Processar estatísticas do fluxo interno
            if (isset($statsFluxo['por_status'])) {
                foreach ($statsFluxo['por_status'] as $status) {
                    if ($status['status_fluxo'] === 'AGUARDANDO_ASSINATURA') {
                        $aguardandoAssinatura = $status['total'] ?? 0;
                    }
                }
            }
            
            // Buscar estatísticas adicionais se método existir
            if (method_exists($documentos, 'getEstatisticasPresidencia')) {
                $statsPresidencia = $documentos->getEstatisticasPresidencia();
                $assinadosHoje = $statsPresidencia['assinados_hoje'] ?? 0;
                $assinadosMes = $statsPresidencia['assinados_mes'] ?? 0;
                $tempoMedio = $statsPresidencia['tempo_medio_assinatura'] ?? 0;
            }
        } else {
            // Fallback caso o método não exista
            $aguardandoAssinatura = $assinadosHoje = $assinadosMes = $tempoMedio = 0;
        }

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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

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

        /* Estilo para documentos no fluxo */
        .document-card {
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

        .document-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-1px);
        }

        .document-card.status-digitalizado {
            border-left-color: var(--primary);
        }

        .document-card.status-aguardando-assinatura {
            border-left-color: var(--warning);
        }

        .document-card.status-assinado {
            border-left-color: var(--success);
        }

        .document-card.status-finalizado {
            border-left-color: var(--secondary);
        }

        .document-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .document-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            margin-right: 1rem;
            background: #f8f9fa;
        }

        .document-icon.pdf {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .document-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 1rem 0;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .meta-item i {
            color: var(--primary);
        }

        .document-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-action.primary {
            background: var(--primary);
            color: white;
        }

        .btn-action.primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .btn-action.success {
            background: var(--success);
            color: white;
        }

        .btn-action.success:hover {
            background: #1e7e34;
            transform: translateY(-1px);
        }

        .btn-action.warning {
            background: var(--warning);
            color: #212529;
        }

        .btn-action.warning:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }

        .btn-action.secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-action.secondary:hover {
            background: var(--gray-300);
            transform: translateY(-1px);
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.digitalizado {
            background: rgba(0, 123, 255, 0.1);
            color: #0056b3;
        }

        .status-badge.aguardando-assinatura {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-badge.assinado {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }

        .status-badge.finalizado {
            background: rgba(108, 117, 125, 0.1);
            color: #495057;
        }

        /* Filtros */
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

        /* Loading */
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

        /* Modal de Edição de Valores */
        #modalEditarValoresBase .card {
            transition: all 0.3s ease;
        }

        #modalEditarValoresBase .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        #modalEditarValoresBase .input-group-text {
            background-color: #f8f9fa;
            border-color: #ced4da;
            font-weight: 600;
        }

        #modalEditarValoresBase .bg-light {
            border: 1px solid #e9ecef;
        }

        #modalEditarValoresBase .modal-lg {
            max-width: 900px;
        }

        .text-money-positive {
            color: #28a745 !important;
        }

        .text-money-negative {
            color: #dc3545 !important;
        }

        .text-money-neutral {
            color: #6c757d !important;
        }

        .btn-modern.btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
            transition: all 0.3s ease;
        }

        .btn-modern.btn-warning:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
        }

        .btn-modern:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: unset;
                width: 100%;
            }
            
            .document-card {
                padding: 1rem;
            }
            
            .document-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .document-actions {
                justify-content: center;
                width: 100%;
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
                        <li>Verifique se você está no departamento da Presidência</li>
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
                            <li><strong>Departamento ID:</strong> <?php echo htmlspecialchars($usuarioLogado['departamento_id'] ?? 'N/A'); ?></li>
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
                            <li>Estar no departamento da Presidência (ID: 1) OU</li>
                            <li>Ser um diretor do sistema</li>
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
                            <?php else: ?>
                            <div class="stat-change positive">
                                <i class="fas fa-check-circle"></i>
                                Tudo em dia
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-icon <?php echo $aguardandoAssinatura > 5 ? 'danger' : ($aguardandoAssinatura > 0 ? 'warning' : 'success'); ?>">
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
                    <button class="btn-modern btn-warning" onclick="abrirModalEditarValores()" id="btnEditarValoresBase">
                        <i class="fas fa-calculator"></i>
                        Editar Valores Base dos Serviços
                    </button>
                    
                    <button class="quick-action-btn" onclick="abrirRelatorios()">
                        <i class="fas fa-chart-line quick-action-icon"></i>
                        Relatórios
                    </button>
                    <button class="quick-action-btn" onclick="verHistoricoGeral()">
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
                    <div class="filter-row">
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
                            <label class="filter-label">Tipo de Fluxo</label>
                            <select class="filter-select" id="filtroTipoFluxo">
                                <option value="">Todos os Tipos</option>
                                <option value="VIRTUAL">Virtual (Sistema)</option>
                                <option value="PRESENCIAL">Presencial (Digitalizada)</option>
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

                    <div class="filter-row">
                        <button class="btn-action secondary" onclick="limparFiltros()">
                            <i class="fas fa-eraser"></i>
                            Limpar Filtros
                        </button>
                        <button class="btn-action primary" onclick="aplicarFiltros()">
                            <i class="fas fa-filter"></i>
                            Aplicar Filtros
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

    <!-- Modal de Histórico -->
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
                    <button type="button" class="btn-action secondary" data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico Geral -->
    <div class="modal fade" id="historicoGeralModal" tabindex="-1" aria-labelledby="historicoGeralModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historicoGeralModalLabel">
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
                                <option value="<?php echo $_SESSION['funcionario_id'] ?? ''; ?>">Minhas assinaturas</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary w-100" onclick="carregarHistoricoGeral()">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </div>

                    <!-- Timeline de Assinaturas -->
                    <div id="timelineHistoricoGeral" class="timeline-container">
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
                                    <div class="row text-center" id="resumoHistoricoGeral">
                                        <!-- Será preenchido dinamicamente -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="imprimirHistoricoGeral()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição de Valores Base -->
    <div class="modal fade" id="modalEditarValoresBase" tabindex="-1" role="dialog" aria-labelledby="modalEditarValoresBaseLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalEditarValoresBaseLabel">
                        <i class="fas fa-calculator"></i>
                        Editar Valores Base dos Serviços
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Atenção:</strong> Alterar os valores base irá recalcular automaticamente todos os valores dos associados baseado nos percentuais do tipo de cada um.
                    </div>

                    <form id="formEditarValoresBase">
                        <div class="row">
                            <!-- Serviço Social -->
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-users"></i>
                                            Serviço Social
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="valorBaseSocial">Valor Base Atual:</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="valorBaseSocial"
                                                       name="valorBaseSocial"
                                                       step="0.01" 
                                                       min="0"
                                                       placeholder="0,00"
                                                       required>
                                            </div>
                                            <small class="form-text text-muted">
                                                Valor que será aplicado aos percentuais de cada tipo de associado
                                            </small>
                                        </div>
                                        
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="text-success mb-2">
                                                <i class="fas fa-chart-pie"></i>
                                                Impacto Estimado:
                                            </h6>
                                            <div id="impactoSocial">
                                                <div class="d-flex justify-content-between">
                                                    <span>Contribuintes (100%):</span>
                                                    <span id="impactoSocialContribuinte" class="fw-bold">R$ 0,00</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span>Alunos (50%):</span>
                                                    <span id="impactoSocialAluno" class="fw-bold">R$ 0,00</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span>Remidos (0%):</span>
                                                    <span id="impactoSocialRemido" class="fw-bold">R$ 0,00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Serviço Jurídico -->
                            <div class="col-md-6">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h6 class="mb-0">
                                            <i class="fas fa-balance-scale"></i>
                                            Serviço Jurídico
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group mb-3">
                                            <label for="valorBaseJuridico">Valor Base Atual:</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="valorBaseJuridico"
                                                       name="valorBaseJuridico"
                                                       step="0.01" 
                                                       min="0"
                                                       placeholder="0,00"
                                                       required>
                                            </div>
                                            <small class="form-text text-muted">
                                                Valor aplicado apenas aos associados que aderiram ao serviço jurídico
                                            </small>
                                        </div>
                                        
                                        <div class="bg-light p-3 rounded">
                                            <h6 class="text-warning mb-2">
                                                <i class="fas fa-chart-pie"></i>
                                                Impacto Estimado:
                                            </h6>
                                            <div id="impactoJuridico">
                                                <div class="d-flex justify-content-between">
                                                    <span>Contribuintes (100%):</span>
                                                    <span id="impactoJuridicoContribuinte" class="fw-bold">R$ 0,00</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span>Alunos (50%):</span>
                                                    <span id="impactoJuridicoAluno" class="fw-bold">R$ 0,00</span>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <span>Remidos (0%):</span>
                                                    <span id="impactoJuridicoRemido" class="fw-bold">R$ 0,00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumo de Impacto -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-calculator"></i>
                                            Resumo do Impacto
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="resumoImpacto" class="text-center">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <h5 class="text-info mb-1" id="totalAssociadosAfetados">0</h5>
                                                    <small class="text-muted">Associados Afetados</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <h5 class="text-success mb-1" id="totalValorAnterior">R$ 0,00</h5>
                                                    <small class="text-muted">Valor Total Anterior</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <h5 class="text-primary mb-1" id="totalValorNovo">R$ 0,00</h5>
                                                    <small class="text-muted">Valor Total Novo</small>
                                                </div>
                                            </div>
                                            <hr>
                                            <div class="row">
                                                <div class="col-12">
                                                    <h4 id="diferencaTotal" class="mb-1">R$ 0,00</h4>
                                                    <small class="text-muted">Diferença Total (+ Aumento | - Redução)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmarAlteracaoValores()" id="btnConfirmarAlteracao">
                        <i class="fas fa-check"></i>
                        Confirmar e Atualizar Todos os Associados
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
        // ===== VARIÁVEIS GLOBAIS =====
        let documentosFluxo = [];
        let documentoSelecionado = null;
        let arquivoAssinado = null;
        let filtrosAtuais = {};
        let valoresBaseAtuais = {};
        let dadosSimulacao = {};
        let chartDiaSemana = null;
        let chartTempoProcessamento = null;
        const temPermissao = <?php echo json_encode($temPermissaoPresidencia); ?>;

        // ===== SISTEMA DE NOTIFICAÇÕES =====
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

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa AOS
            AOS.init({
                duration: 800,
                once: true
            });

            console.log('=== PRESIDÊNCIA - SISTEMA INTERNO ===');
            const usuario = <?php echo json_encode($usuarioLogado); ?>;
            const isDiretor = <?php echo json_encode($auth->isDiretor()); ?>;
            
            console.log('👤 Usuário completo:', usuario);
            console.log('🏢 Departamento ID:', usuario.departamento_id, '(tipo:', typeof usuario.departamento_id, ')');
            console.log('👔 É diretor:', isDiretor);
            console.log('🔐 Tem permissão:', temPermissao);
            
            // Teste das comparações
            console.log('🧪 Testes de comparação:');
            console.log('  departamento_id == 1:', usuario.departamento_id == 1);
            console.log('  departamento_id === 1:', usuario.departamento_id === 1);
            console.log('  departamento_id === "1":', usuario.departamento_id === "1");
            
            // Resultado final da lógica
            const resultadoLogica = usuario.departamento_id == 1 || isDiretor;
            console.log('📋 Lógica de acesso (dept==1 OU isDiretor):', resultadoLogica);
            console.log('📋 Permissão PHP vs JS:', temPermissao, '===', resultadoLogica, '?', temPermissao === resultadoLogica);

            // Só carregar se tiver permissão
            if (temPermissao) {
                carregarDocumentosFluxo();
                configurarFiltros();
                configurarUpload();
                configurarMetodoAssinatura();
                
                // Definir datas padrão nos relatórios
                const hoje = new Date();
                const mesPassado = new Date(hoje.getFullYear(), hoje.getMonth() - 1, hoje.getDate());
                document.getElementById('relatorioDataInicio').value = mesPassado.toISOString().split('T')[0];
                document.getElementById('relatorioDataFim').value = hoje.toISOString().split('T')[0];
            }
        });

        // ===== FUNÇÕES PRINCIPAIS =====

        /**
         * Carrega documentos do sistema interno (fluxo de assinatura)
         */
        function carregarDocumentosFluxo(filtros = {}) {
            const container = document.getElementById('documentsList');

            // Mostra loading
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="loading-skeleton mb-3" style="height: 60px;"></div>
                    <div class="loading-skeleton mb-3" style="height: 60px;"></div>
                    <div class="loading-skeleton mb-3" style="height: 60px;"></div>
                    <p class="text-muted">Carregando documentos...</p>
                </div>
            `;

            console.log('Carregando documentos com filtros:', filtros);

            // Faz requisição para API do sistema interno
            $.get('../api/documentos/documentos_fluxo_listar.php', filtros, function (response) {
                console.log('Resposta da API:', response);
                
                if (response.status === 'success') {
                    documentosFluxo = response.data || [];
                    console.log('Documentos carregados:', documentosFluxo.length);
                    renderizarDocumentosFluxo(documentosFluxo);
                } else {
                    console.error('Erro na API:', response.message);
                    mostrarErroCarregamento(response.message || 'Erro ao carregar documentos');
                }
            }).fail(function (xhr, status, error) {
                console.error('Erro na requisição:', error);
                console.error('Response Text:', xhr.responseText);
                mostrarErroCarregamento('Erro de conexão: ' + error);
            });
        }

        /**
         * Renderiza lista de documentos do fluxo interno
         */
        function renderizarDocumentosFluxo(documentos) {
            const container = document.getElementById('documentsList');
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
                const statusClass = doc.status_fluxo.toLowerCase().replace('_', '-');
                const isPresencial = doc.tipo_origem === 'FISICO';
                const fluxoClass = isPresencial ? 'presencial' : 'virtual';
                
                // Usar campos corretos da API
                const associadoNome = doc.associado_nome || 'Nome não encontrado';
                const associadoCpf = doc.associado_cpf || '';
                const departamentoNome = doc.departamento_atual_nome || 'Comercial';
                const statusDescricao = doc.status_descricao || doc.status_fluxo;
                const diasProcesso = doc.dias_em_processo || 0;
                
                const cardDiv = document.createElement('div');
                cardDiv.className = `document-card status-${statusClass}`;
                cardDiv.dataset.docId = doc.id;
                
                cardDiv.innerHTML = `
                    <div class="document-header">
                        <div class="document-icon pdf">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="document-info">
                            <h6 class="document-title">Ficha de Filiação</h6>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-${isPresencial ? 'success' : 'primary'} text-white">
                                    <i class="fas fa-${isPresencial ? 'handshake' : 'desktop'}"></i>
                                    ${isPresencial ? 'Presencial' : 'Virtual'}
                                </span>
                                <span class="status-badge ${statusClass}">
                                    <i class="fas fa-${getStatusIcon(doc.status_fluxo)} me-1"></i>
                                    ${statusDescricao}
                                </span>
                            </div>
                        </div>
                        <div class="document-actions">
                            ${getAcoesFluxo(doc, isPresencial)}
                        </div>
                    </div>
                    
                    <div class="document-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span><strong>${associadoNome}</strong></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-id-card-o"></i>
                            <span>ID: ${doc.associado_id}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-id-card"></i>
                            <span>CPF: ${formatarCPF(associadoCpf)}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-building"></i>
                            <span>${departamentoNome}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>${formatarData(doc.data_upload)}</span>
                        </div>
                        ${diasProcesso > 0 ? `
                            <div class="meta-item">
                                <i class="fas fa-hourglass-half"></i>
                                <span class="text-warning"><strong>${diasProcesso} dias em processo</strong></span>
                            </div>
                        ` : ''}
                    </div>
                `;

                container.appendChild(cardDiv);
            });
        }

        /**
         * Retorna ações baseadas no status do documento
         */
        function getAcoesFluxo(doc, isPresencial = false) {
            let acoes = '';

            // Botão de download sempre disponível
            acoes += `
                <button class="btn-action primary" onclick="downloadDocumento(${doc.id})" title="Download">
                    <i class="fas fa-download"></i>
                    Baixar
                </button>
            `;

            // Ações específicas por status
            switch (doc.status_fluxo) {
                case 'AGUARDANDO_ASSINATURA':
                    // Apenas presidência pode assinar
                    acoes += `
                        <button class="btn-action success" onclick="abrirModalAssinatura(${doc.id})" title="Assinar Documento">
                            <i class="fas fa-signature"></i>
                            Assinar
                        </button>
                    `;
                    break;

                case 'ASSINADO':
                    acoes += `
                        <button class="btn-action warning" onclick="finalizarProcesso(${doc.id})" title="Finalizar Processo">
                            <i class="fas fa-flag-checkered"></i>
                            Finalizar
                        </button>
                    `;
                    break;

                case 'FINALIZADO':
                    acoes += `
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle"></i>
                            Processo Concluído
                        </span>
                    `;
                    break;
            }

            // Botão de histórico sempre disponível
            acoes += `
                <button class="btn-action secondary" onclick="verHistorico(${doc.id})" title="Ver Histórico">
                    <i class="fas fa-history"></i>
                    Histórico
                </button>
            `;

            return acoes;
        }

        /**
         * Retorna ícone baseado no status
         */
        function getStatusIcon(status) {
            const icons = {
                'DIGITALIZADO': 'upload',
                'AGUARDANDO_ASSINATURA': 'clock',
                'ASSINADO': 'check',
                'FINALIZADO': 'flag-checkered'
            };
            return icons[status] || 'file';
        }

        // ===== FUNÇÕES DE AÇÃO =====

        /**
         * Abre modal de assinatura
         */
        function abrirModalAssinatura(documentoId) {
            documentoSelecionado = documentosFluxo.find(doc => doc.id === documentoId);
            
            if (!documentoSelecionado) {
                notifications.show('Documento não encontrado', 'error');
                return;
            }

            // Preencher informações do documento
            document.getElementById('documentoId').value = documentoId;
            document.getElementById('previewAssociado').textContent = documentoSelecionado.associado_nome;
            document.getElementById('previewCPF').textContent = formatarCPF(documentoSelecionado.associado_cpf);
            document.getElementById('previewData').textContent = formatarData(documentoSelecionado.data_upload);
            document.getElementById('previewOrigem').textContent = documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Presencial';
            document.getElementById('previewSubtitulo').textContent = documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Gerado pelo sistema' : 'Digitalizado';

            // Resetar upload
            document.getElementById('uploadSection').classList.add('d-none');
            document.getElementById('fileInfo').innerHTML = '';
            arquivoAssinado = null;

            // Mostrar modal
            new bootstrap.Modal(document.getElementById('assinaturaModal')).show();
        }

        /**
         * Confirma assinatura do documento
         */
        function confirmarAssinatura() {
            const documentoId = document.getElementById('documentoId').value;
            const observacoes = document.getElementById('observacoes').value.trim();
            const metodo = document.querySelector('input[name="metodoAssinatura"]:checked').value;
            
            if (!documentoId) {
                notifications.show('ID do documento não encontrado', 'error');
                return;
            }
            
            if (metodo === 'upload' && !arquivoAssinado) {
                notifications.show('Por favor, selecione o arquivo assinado', 'warning');
                return;
            }
            
            const btnAssinar = event.target;
            const originalContent = btnAssinar.innerHTML;
            
            try {
                // Loading state
                btnAssinar.disabled = true;
                btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';
                
                const formData = new FormData();
                formData.append('documento_id', documentoId);
                formData.append('observacao', observacoes || 'Documento assinado pela presidência');
                formData.append('metodo', metodo);
                
                if (arquivoAssinado) {
                    formData.append('arquivo_assinado', arquivoAssinado);
                }
                
                fetch('../api/documentos/documentos_assinar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        bootstrap.Modal.getInstance(document.getElementById('assinaturaModal')).hide();
                        notifications.show('Documento assinado com sucesso!', 'success');
                        
                        // Recarregar lista
                        carregarDocumentosFluxo(filtrosAtuais);
                    } else {
                        throw new Error(result.message || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('Erro ao assinar documento:', error);
                    notifications.show('Erro ao assinar documento: ' + error.message, 'error');
                })
                .finally(() => {
                    btnAssinar.disabled = false;
                    btnAssinar.innerHTML = originalContent;
                });
                
            } catch (error) {
                console.error('Erro ao assinar documento:', error);
                notifications.show('Erro ao assinar documento: ' + error.message, 'error');
                btnAssinar.disabled = false;
                btnAssinar.innerHTML = originalContent;
            }
        }

        /**
         * Finaliza processo do documento
         */
        function finalizarProcesso(documentoId) {
            if (confirm('Deseja finalizar o processo deste documento?\n\nO documento retornará ao comercial e o pré-cadastro poderá ser aprovado.')) {
                fetch('../api/documentos/documentos_finalizar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Processo finalizado - Documento pronto para aprovação do pré-cadastro'
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        notifications.show('Processo finalizado com sucesso! O pré-cadastro já pode ser aprovado.', 'success');
                        carregarDocumentosFluxo(filtrosAtuais);
                    } else {
                        notifications.show('Erro: ' + result.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro ao finalizar processo:', error);
                    notifications.show('Erro ao finalizar processo', 'error');
                });
            }
        }

        /**
         * Ver histórico do documento
         */
        function verHistorico(documentoId) {
            fetch('../api/documentos/documentos_historico_fluxo.php?documento_id=' + documentoId)
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        renderizarHistorico(result.data);
                        new bootstrap.Modal(document.getElementById('historicoModal')).show();
                    } else {
                        notifications.show('Erro ao carregar histórico', 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar histórico:', error);
                    notifications.show('Erro ao carregar histórico', 'error');
                });
        }

        /**
         * Renderiza histórico do documento
         */
        function renderizarHistorico(historico) {
            const container = document.getElementById('historicoContent');
            container.innerHTML = '';

            if (historico.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum histórico disponível</p>';
                return;
            }

            const timeline = document.createElement('div');
            timeline.className = 'timeline';

            historico.forEach(item => {
                const timelineItem = document.createElement('div');
                timelineItem.className = 'timeline-item';
                
                timelineItem.innerHTML = `
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${item.status_novo_desc || item.status_novo}</h6>
                                <p class="mb-2">${item.observacao || 'Sem observações'}</p>
                                <small class="text-muted">
                                    Por: ${item.funcionario_nome || 'Sistema'}<br>
                                    ${item.dept_origem_nome ? `De: ${item.dept_origem_nome}<br>` : ''}
                                    ${item.dept_destino_nome ? `Para: ${item.dept_destino_nome}` : ''}
                                </small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${formatarData(item.data_acao)}</small>
                            </div>
                        </div>
                    </div>
                `;
                
                timeline.appendChild(timelineItem);
            });

            container.appendChild(timeline);
        }

        /**
         * Download do documento PDF
         */
        function downloadDocumento(documentoId) {
            console.log('Iniciando download do documento ID:', documentoId);
            
            // Mostra notificação de loading
            notifications.show('Preparando download da ficha PDF...', 'info', 2000);
            
            // Cria uma requisição para verificar se o arquivo é válido
            fetch('../api/documentos/documentos_download.php?id=' + documentoId, {
                method: 'HEAD' // Apenas verifica headers
            })
            .then(response => {
                if (response.ok) {
                    // Se a resposta for OK, faz o download
                    const link = document.createElement('a');
                    link.href = '../api/documentos/documentos_download.php?id=' + documentoId;
                    link.target = '_blank';
                    link.download = `ficha_filiacao_${documentoId}.pdf`; // Nome sugerido para o arquivo
                    
                    // Adiciona atributos para forçar download de PDF
                    link.type = 'application/pdf';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    notifications.show('Download iniciado! Verifique sua pasta de downloads.', 'success', 3000);
                } else {
                    throw new Error('Arquivo não encontrado ou erro no servidor');
                }
            })
            .catch(error => {
                console.error('Erro no download:', error);
                notifications.show('Erro ao baixar arquivo: ' + error.message, 'error');
                
                // Fallback: tenta abrir diretamente
                window.open('../api/documentos/documentos_download.php?id=' + documentoId, '_blank');
            });
        }

        /**
         * Visualizar documento (no modal)
         */
        function visualizarDocumento() {
            if (documentoSelecionado) {
                downloadDocumento(documentoSelecionado.id);
            }
        }

        // ===== RELATÓRIOS =====

        /**
         * Redireciona para página de relatórios
         */
        function abrirRelatorios() {
            console.log('Redirecionando para página de relatórios...');
            window.location.href = 'relatorios.php';
        }

        /**
         * Carrega relatórios
         */
        function carregarRelatorios() {
            const dataInicio = document.getElementById('relatorioDataInicio').value;
            const dataFim = document.getElementById('relatorioDataFim').value;
            
            if (!dataInicio || !dataFim) {
                notifications.show('Por favor, selecione o período', 'warning');
                return;
            }
            
            fetch('../api/documentos/relatorio_produtividade.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    data_inicio: dataInicio,
                    data_fim: dataFim
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    renderizarRelatorios(data.data);
                } else {
                    throw new Error(data.message || 'Erro ao processar relatórios');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                notifications.show('Erro ao carregar relatórios: ' + error.message, 'error');
            });
        }

        /**
         * Renderiza relatórios
         */
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

        /**
         * Renderiza gráfico por dia da semana
         */
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

        /**
         * Renderiza gráfico de tempo de processamento
         */
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

        /**
         * Renderiza tabela de produtividade
         */
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

        /**
         * Exporta relatório
         */
        function exportarRelatorio(formato) {
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

        // ===== HISTÓRICO GERAL =====

        /**
         * Abre modal de histórico geral
         */
        function verHistoricoGeral() {
            const modal = new bootstrap.Modal(document.getElementById('historicoGeralModal'));
            modal.show();
            
            // Carregar histórico ao abrir
            setTimeout(() => carregarHistoricoGeral(), 500);
        }

        /**
         * Carrega histórico geral
         */
        function carregarHistoricoGeral() {
            const periodo = document.getElementById('filtroPeriodoHistorico').value;
            const funcionarioId = document.getElementById('filtroFuncionarioHistorico').value;
            
            const params = new URLSearchParams({
                periodo: periodo,
                funcionario_id: funcionarioId || ''
            });
            
            fetch(`../api/documentos/historico_assinaturas.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderizarHistoricoGeral(data.data);
                    } else {
                        throw new Error(data.message || 'Erro ao processar histórico');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    notifications.show('Erro ao carregar histórico: ' + error.message, 'error');
                });
        }

        /**
         * Renderiza histórico geral
         */
        function renderizarHistoricoGeral(dados) {
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
            
            document.getElementById('timelineHistoricoGeral').innerHTML = timelineHtml;
            
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
            
            document.getElementById('resumoHistoricoGeral').innerHTML = resumoHtml;
        }

        /**
         * Imprime histórico geral
         */
        function imprimirHistoricoGeral() {
            window.print();
        }

        // ===== EDIÇÃO DE VALORES BASE =====

        /**
         * Abre modal de edição de valores base
         */
        function abrirModalEditarValores() {
            console.log('Abrindo modal de edição de valores base...');
            
            // Carrega valores antes de abrir
            carregarValoresBaseAtuais()
                .then(() => {
                    const modal = new bootstrap.Modal(document.getElementById('modalEditarValoresBase'));
                    modal.show();
                    console.log('✓ Modal aberto com sucesso');
                })
                .catch(error => {
                    console.error('Erro ao carregar valores:', error);
                    notifications.show('Erro ao carregar valores atuais: ' + error.message, 'error');
                });
        }

        /**
         * Carrega valores base atuais
         */
        function carregarValoresBaseAtuais() {
            console.log('Carregando valores base atuais...');
            
            return new Promise((resolve, reject) => {
                fetch('../api/buscar_valores_base.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            valoresBaseAtuais = data.data;
                            
                            // Preenche os campos
                            const campoSocial = document.getElementById('valorBaseSocial');
                            const campoJuridico = document.getElementById('valorBaseJuridico');
                            
                            if (campoSocial && campoJuridico) {
                                campoSocial.value = valoresBaseAtuais.social.valor_base;
                                campoJuridico.value = valoresBaseAtuais.juridico.valor_base;
                                
                                // Calcula impacto inicial
                                calcularImpacto();
                                
                                console.log('✓ Valores base carregados:', valoresBaseAtuais);
                                resolve(valoresBaseAtuais);
                            } else {
                                reject(new Error('Campos do formulário não encontrados'));
                            }
                        } else {
                            reject(new Error(data.message || 'Erro desconhecido ao carregar valores'));
                        }
                    })
                    .catch(error => {
                        console.error('Erro de rede:', error);
                        reject(error);
                    });
            });
        }

        /**
         * Calcula impacto da alteração
         */
        function calcularImpacto() {
            const valorSocial = parseFloat(document.getElementById('valorBaseSocial').value) || 0;
            const valorJuridico = parseFloat(document.getElementById('valorBaseJuridico').value) || 0;
            
            // Atualiza preview dos valores por tipo
            atualizarPreviewValores(valorSocial, valorJuridico);
            
            // Simula impacto nos associados
            simularImpactoAssociados(valorSocial, valorJuridico);
        }

        /**
         * Atualiza preview dos valores por tipo
         */
        function atualizarPreviewValores(valorSocial, valorJuridico) {
            const percentuais = {
                'Contribuinte': 100,
                'Aluno': 50,
                'Remido': 0
            };
            
            // Atualiza Social
            document.getElementById('impactoSocialContribuinte').textContent = 
                'R$ ' + ((valorSocial * percentuais.Contribuinte) / 100).toFixed(2).replace('.', ',');
            document.getElementById('impactoSocialAluno').textContent = 
                'R$ ' + ((valorSocial * percentuais.Aluno) / 100).toFixed(2).replace('.', ',');
            document.getElementById('impactoSocialRemido').textContent = 
                'R$ ' + ((valorSocial * percentuais.Remido) / 100).toFixed(2).replace('.', ',');
            
            // Atualiza Jurídico
            document.getElementById('impactoJuridicoContribuinte').textContent = 
                'R$ ' + ((valorJuridico * percentuais.Contribuinte) / 100).toFixed(2).replace('.', ',');
            document.getElementById('impactoJuridicoAluno').textContent = 
                'R$ ' + ((valorJuridico * percentuais.Aluno) / 100).toFixed(2).replace('.', ',');
            document.getElementById('impactoJuridicoRemido').textContent = 
                'R$ ' + ((valorJuridico * percentuais.Remido) / 100).toFixed(2).replace('.', ',');
        }

        /**
         * Simula impacto nos associados
         */
        function simularImpactoAssociados(valorSocial, valorJuridico) {
            // Para não sobrecarregar, só simula se os valores mudaram significativamente
            if (Math.abs(valorSocial - (valoresBaseAtuais.social?.valor_base || 0)) < 0.01 && 
                Math.abs(valorJuridico - (valoresBaseAtuais.juridico?.valor_base || 0)) < 0.01) {
                return;
            }
            
            fetch('../api/simular_impacto_valores.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    valor_social: valorSocial,
                    valor_juridico: valorJuridico
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    dadosSimulacao = data.data;
                    atualizarResumoImpacto(data.data);
                }
            })
            .catch(error => {
                console.log('Simulação não disponível:', error.message);
                // Não é crítico, continua sem simulação
            });
        }

        /**
         * Atualiza resumo do impacto
         */
        function atualizarResumoImpacto(simulacao) {
            document.getElementById('totalAssociadosAfetados').textContent = simulacao.total_afetados || 0;
            document.getElementById('totalValorAnterior').textContent = 
                'R$ ' + (simulacao.valor_total_anterior || 0).toFixed(2).replace('.', ',');
            document.getElementById('totalValorNovo').textContent = 
                'R$ ' + (simulacao.valor_total_novo || 0).toFixed(2).replace('.', ',');
            
            const diferenca = (simulacao.valor_total_novo || 0) - (simulacao.valor_total_anterior || 0);
            const elementoDiferenca = document.getElementById('diferencaTotal');
            
            if (diferenca > 0) {
                elementoDiferenca.textContent = '+R$ ' + diferenca.toFixed(2).replace('.', ',');
                elementoDiferenca.className = 'mb-1 text-money-positive';
            } else if (diferenca < 0) {
                elementoDiferenca.textContent = '-R$ ' + Math.abs(diferenca).toFixed(2).replace('.', ',');
                elementoDiferenca.className = 'mb-1 text-money-negative';
            } else {
                elementoDiferenca.textContent = 'R$ 0,00';
                elementoDiferenca.className = 'mb-1 text-money-neutral';
            }
        }

        /**
         * Confirma alteração de valores
         */
        function confirmarAlteracaoValores() {
            const valorSocial = parseFloat(document.getElementById('valorBaseSocial').value);
            const valorJuridico = parseFloat(document.getElementById('valorBaseJuridico').value);
            
            if (!valorSocial || valorSocial <= 0) {
                notifications.show('Informe um valor válido para o Serviço Social', 'warning');
                document.getElementById('valorBaseSocial').focus();
                return;
            }
            
            if (!valorJuridico || valorJuridico <= 0) {
                notifications.show('Informe um valor válido para o Serviço Jurídico', 'warning');
                document.getElementById('valorBaseJuridico').focus();
                return;
            }
            
            // Confirmação final
            const diferenca = (dadosSimulacao.valor_total_novo || 0) - (dadosSimulacao.valor_total_anterior || 0);
            let mensagemConfirmacao = `CONFIRMAÇÃO FINAL\n\n`;
            mensagemConfirmacao += `Serviço Social: R$ ${valoresBaseAtuais.social?.valor_base || 0} → R$ ${valorSocial.toFixed(2)}\n`;
            mensagemConfirmacao += `Serviço Jurídico: R$ ${valoresBaseAtuais.juridico?.valor_base || 0} → R$ ${valorJuridico.toFixed(2)}\n\n`;
            
            if (dadosSimulacao.total_afetados) {
                mensagemConfirmacao += `Isso irá afetar ${dadosSimulacao.total_afetados} associados.\n`;
                if (diferenca !== 0) {
                    mensagemConfirmacao += `Impacto financeiro: ${diferenca >= 0 ? '+' : ''}R$ ${diferenca.toFixed(2)}\n\n`;
                }
            }
            
            mensagemConfirmacao += `Deseja continuar?`;
            
            if (!confirm(mensagemConfirmacao)) {
                return;
            }
            
            // Desabilita botão e mostra loading
            const btnConfirmar = document.getElementById('btnConfirmarAlteracao');
            const textoOriginal = btnConfirmar.innerHTML;
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            
            // Envia alterações
            fetch('../api/atualizar_valores_base_e_recalcular.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    valor_social: valorSocial,
                    valor_juridico: valorJuridico
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    notifications.show(`✅ ${data.message}\n\n📊 ${data.data.resultado_recalculo.total_valores_alterados} valores atualizados`, 'success');
                    
                    // Fecha modal
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarValoresBase')).hide();
                    
                    // Recarrega página
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    notifications.show('Erro: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                notifications.show('Erro de comunicação: ' + error.message, 'error');
            })
            .finally(() => {
                // Restaura botão
                btnConfirmar.disabled = false;
                btnConfirmar.innerHTML = textoOriginal;
            });
        }

        // ===== FILTROS =====

        /**
         * Configura filtros
         */
        function configurarFiltros() {
            const filtroStatus = document.getElementById('filtroStatusFluxo');
            const filtroTipo = document.getElementById('filtroTipoFluxo');
            const filtroBusca = document.getElementById('filtroBuscaFluxo');
            const filtroPeriodo = document.getElementById('filtroPeriodo');

            if (filtroStatus) {
                filtroStatus.addEventListener('change', aplicarFiltros);
            }
            if (filtroTipo) {
                filtroTipo.addEventListener('change', aplicarFiltros);
            }
            if (filtroBusca) {
                filtroBusca.addEventListener('input', debounce(aplicarFiltros, 500));
            }
            if (filtroPeriodo) {
                filtroPeriodo.addEventListener('change', aplicarFiltros);
            }

            // Event listeners para cálculo de impacto em tempo real (valores base)
            const valorSocial = document.getElementById('valorBaseSocial');
            const valorJuridico = document.getElementById('valorBaseJuridico');
            
            if (valorSocial) {
                valorSocial.addEventListener('input', calcularImpacto);
                valorSocial.addEventListener('change', calcularImpacto);
            }
            
            if (valorJuridico) {
                valorJuridico.addEventListener('input', calcularImpacto);
                valorJuridico.addEventListener('change', calcularImpacto);
            }
        }

        /**
         * Aplica filtros
         */
        function aplicarFiltros() {
            filtrosAtuais = {};

            const status = document.getElementById('filtroStatusFluxo')?.value;
            if (status) filtrosAtuais.status = status;

            const tipo = document.getElementById('filtroTipoFluxo')?.value;
            if (tipo) {
                // Mapear para formato da API
                if (tipo === 'PRESENCIAL') {
                    filtrosAtuais.origem = 'FISICO';
                } else if (tipo === 'VIRTUAL') {
                    filtrosAtuais.origem = 'VIRTUAL';
                }
            }

            const busca = document.getElementById('filtroBuscaFluxo')?.value?.trim();
            if (busca) filtrosAtuais.busca = busca;

            const periodo = document.getElementById('filtroPeriodo')?.value;
            if (periodo) filtrosAtuais.periodo = periodo;

            carregarDocumentosFluxo(filtrosAtuais);
        }

        /**
         * Limpa filtros
         */
        function limparFiltros() {
            document.getElementById('filtroStatusFluxo').value = '';
            document.getElementById('filtroTipoFluxo').value = '';
            document.getElementById('filtroBuscaFluxo').value = '';
            document.getElementById('filtroPeriodo').value = '';
            
            filtrosAtuais = {};
            carregarDocumentosFluxo();
        }

        // ===== CONFIGURAÇÃO DE UPLOAD =====

        /**
         * Configura área de upload
         */
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

        /**
         * Processa arquivo selecionado
         */
        function handleFile(file) {
            if (!file) return;

            // Verificar se é PDF
            if (file.type !== 'application/pdf') {
                notifications.show('Por favor, selecione apenas arquivos PDF', 'warning');
                return;
            }

            // Verificar tamanho (10MB)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                notifications.show('Arquivo muito grande. Máximo: 10MB', 'warning');
                return;
            }

            arquivoAssinado = file;

            document.getElementById('fileInfo').innerHTML = `
                <div class="alert alert-success d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-file-pdf me-2"></i>
                        <strong>${file.name}</strong> (${formatBytes(file.size)})
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removerArquivo()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }

        /**
         * Remove arquivo selecionado
         */
        function removerArquivo() {
            arquivoAssinado = null;
            document.getElementById('fileInfo').innerHTML = '';
            document.getElementById('fileInput').value = '';
        }

        /**
         * Configura método de assinatura
         */
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

        // ===== FUNÇÕES AUXILIARES =====

        /**
         * Atualiza lista de documentos
         */
        function atualizarLista() {
            carregarDocumentosFluxo(filtrosAtuais);
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
                    <p class="empty-state-description">${mensagem}</p>
                    <button class="btn-action primary" onclick="carregarDocumentosFluxo()">
                        <i class="fas fa-redo"></i>
                        Tentar Novamente
                    </button>
                </div>
            `;
        }

        /**
         * Debounce para filtros
         */
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

        /**
         * Formata CPF
         */
        function formatarCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        /**
         * Formata data
         */
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

        /**
         * Formata bytes
         */
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // ===== AÇÕES RÁPIDAS =====

        function configurarAssinatura() {
            notifications.show('Funcionalidade de configurações em desenvolvimento', 'info');
        }

        function assinarTodos() {
            const pendentes = documentosFluxo.filter(doc => doc.status_fluxo === 'AGUARDANDO_ASSINATURA');
            if (pendentes.length === 0) {
                notifications.show('Não há documentos pendentes para assinar', 'warning');
                return;
            }
            notifications.show(`Funcionalidade de assinatura em lote para ${pendentes.length} documentos em desenvolvimento`, 'info');
        }

        // ===== AUTO-REFRESH =====
        
        // Auto-refresh a cada 30 segundos
        setInterval(function() {
            if (temPermissao && document.hasFocus()) {
                carregarDocumentosFluxo(filtrosAtuais);
            }
        }, 30000);

        console.log('✓ Sistema da Presidência carregado com funcionalidades completas!');
        console.log('✓ API: ../api/documentos/documentos_fluxo_listar.php');
        console.log('✓ Relatórios, Histórico e Edição de Valores incluídos');
    </script>

</body>

</html>