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
            gap: 2rem;
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
            margin-right: 1.5rem;
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
let documentosZapSign = [];
let documentosFluxo = [];
let documentosTodos = []; // Array unificado
let paginaAtual = 1;
let statusFiltro = '';
let termoBusca = '';
let ordenacao = 'desc';
let carregandoDocumentos = false;
let estatisticasGlobais = {};
let documentoSelecionado = null;
let arquivoAssinado = null;
let filtrosAtuais = {};
let valoresBaseAtuais = {};
let dadosSimulacao = {};
let chartDiaSemana = null;
let chartTempoProcessamento = null;
const temPermissao = typeof window.temPermissaoPresidencia !== 'undefined' ? window.temPermissaoPresidencia : true;

// ===== SISTEMA DE NOTIFICAÇÕES UNIFICADO =====
class NotificationSystem {
    constructor() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.createContainer();
        }
    }
    
    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'toastContainer';
        this.container.className = 'toast-container position-fixed top-0 end-0 p-3';
        this.container.style.zIndex = '1055';
        document.body.appendChild(this.container);
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
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="modal"></button>
            </div>
        `;
        
        this.container.appendChild(toast);
        
        // Usar Bootstrap Toast se disponível, senão fazer manualmente
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const bsToast = new bootstrap.Toast(toast, { delay: duration });
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        } else {
            // Fallback manual
            toast.style.display = 'block';
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
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

// ===== CACHE SIMPLES =====
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

// ===== SISTEMA DE ATUALIZAÇÃO AUTOMÁTICA =====
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
                carregarTodosDocumentos();
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

// Instanciar sistemas
const notifications = new NotificationSystem();
const cache = new SimpleCache();
const autoUpdater = new AutoUpdater();

// ===== INICIALIZAÇÃO ROBUSTA =====
function initializeUserDropdown() {
    console.log('🎯 Inicializando dropdown do usuário na presidência...');
    
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
    
    for (const selector of menuSelectors) {
        userMenu = document.querySelector(selector);
        if (userMenu) {
            console.log('✅ Menu encontrado com seletor:', selector);
            break;
        }
    }
    
    for (const selector of dropdownSelectors) {
        userDropdown = document.querySelector(selector);
        if (userDropdown) {
            console.log('✅ Dropdown encontrado com seletor:', selector);
            break;
        }
    }
    
    if (userMenu && userDropdown) {
        userMenu.removeEventListener('click', handleUserMenuClick);
        document.removeEventListener('click', handleDocumentClick);
        
        userMenu.addEventListener('click', handleUserMenuClick);
        document.addEventListener('click', handleDocumentClick);
        
        console.log('✅ User dropdown inicializado com sucesso na presidência!');
        
        function handleUserMenuClick(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isVisible = userDropdown.classList.contains('show');
            
            document.querySelectorAll('.user-dropdown.show').forEach(dropdown => {
                if (dropdown !== userDropdown) {
                    dropdown.classList.remove('show');
                }
            });
            
            userDropdown.classList.toggle('show', !isVisible);
            console.log('Dropdown toggled:', !isVisible);
        }
        
        function handleDocumentClick(e) {
            if (!userMenu.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        }
    } else {
        console.warn('⚠️ Elementos do dropdown não encontrados na presidência');
    }
}

// ===== CARREGAMENTO UNIFICADO DE DOCUMENTOS =====
async function carregarTodosDocumentos(resetarPagina = false) {
    if (carregandoDocumentos) return;
    
    carregandoDocumentos = true;
    
    const container = document.getElementById('documentsList');
    
    if (!container) {
        console.error('Container de documentos não encontrado');
        carregandoDocumentos = false;
        return;
    }
    
    if (resetarPagina || paginaAtual === 1) {
        mostrarSkeletonLoading();
    }
    
    try {
        console.log('🔄 Carregando TODOS os documentos (ZapSign + Fluxo Interno)...');
        
        // Carregar AMBOS os tipos de documentos em paralelo
        const [resultadoZapSign, resultadoFluxo] = await Promise.allSettled([
            carregarDocumentosZapSign(),
            carregarDocumentosFluxo()
        ]);
        
        // Processar resultados
        if (resultadoZapSign.status === 'fulfilled') {
            documentosZapSign = resultadoZapSign.value || [];
            console.log('✅ ZapSign carregados:', documentosZapSign.length);
        } else {
            console.error('❌ Erro ao carregar ZapSign:', resultadoZapSign.reason);
            documentosZapSign = [];
        }
        
        if (resultadoFluxo.status === 'fulfilled') {
            documentosFluxo = resultadoFluxo.value || [];
            console.log('✅ Fluxo Interno carregados:', documentosFluxo.length);
        } else {
            console.error('❌ Erro ao carregar Fluxo Interno:', resultadoFluxo.reason);
            documentosFluxo = [];
        }
        
        // Unificar e renderizar documentos
        unificarERenderizarDocumentos();
        
        const totalDocs = documentosZapSign.length + documentosFluxo.length;
        notifications.show(`${totalDocs} documento(s) carregado(s) (${documentosZapSign.length} ZapSign + ${documentosFluxo.length} Fluxo Interno)`, 'success', 4000);
        
    } catch (error) {
        console.error('❌ Erro ao carregar documentos:', error);
        mostrarErroCarregamento(error.message);
        notifications.show('Erro ao carregar documentos: ' + error.message, 'error');
    } finally {
        carregandoDocumentos = false;
    }
}

// ===== CARREGAMENTO ESPECÍFICO DOS DOCUMENTOS ZAPSIGN =====
async function carregarDocumentosZapSign() {
    try {
        statusFiltro = document.getElementById('filterStatus')?.value || '';
        termoBusca = document.getElementById('searchInput')?.value || '';
        ordenacao = document.getElementById('filterOrdenacao')?.value || 'desc';
        
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
        
        if (!response.ok) {
            throw new Error(`Erro HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.status === 'success') {
            return data.data || [];
        } else {
            throw new Error(data.message || 'Erro ao carregar documentos ZapSign');
        }
        
    } catch (error) {
        console.error('❌ Erro ZapSign:', error);
        return [];
    }
}

// ===== CARREGAMENTO ESPECÍFICO DOS DOCUMENTOS DO FLUXO INTERNO =====
async function carregarDocumentosFluxo() {
    try {
        // Montar filtros para o fluxo interno
        const filtros = {};
        
        // Mapear filtros da interface para API do fluxo
        if (statusFiltro) {
            // Mapear status ZapSign para status do fluxo interno
            const mapeamentoStatus = {
                'pending': 'AGUARDANDO_ASSINATURA',
                'signed': 'ASSINADO',
                'refused': 'RECUSADO',
                'expired': 'EXPIRADO'
            };
            filtros.status = mapeamentoStatus[statusFiltro] || statusFiltro;
        }
        
        if (termoBusca) {
            filtros.busca = termoBusca;
        }
        
        const params = new URLSearchParams(filtros);
        
        const response = await fetch(`../api/documentos/documentos_fluxo_listar.php?${params}`, {
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
            return data.data || [];
        } else {
            throw new Error(data.message || 'Erro ao carregar documentos do fluxo interno');
        }
        
    } catch (error) {
        console.error('❌ Erro Fluxo Interno:', error);
        return [];
    }
}

// ===== UNIFICAÇÃO E RENDERIZAÇÃO DOS DOCUMENTOS =====
function unificarERenderizarDocumentos() {
    const container = document.getElementById('documentsList');
    container.innerHTML = '';
    
    // Adicionar CSS moderno se não existir
    adicionarEstilosModernos();
    
    // Unificar documentos com identificação de origem
    documentosTodos = [];
    
    // Adicionar documentos ZapSign
    documentosZapSign.forEach(doc => {
        documentosTodos.push({
            ...doc,
            tipo_sistema: 'ZAPSIGN',
            sistema_nome: 'ZapSign',
            sistema_cor: 'primary',
            sistema_icone: 'fas fa-cloud'
        });
    });
    
    // Adicionar documentos do fluxo interno
    documentosFluxo.forEach(doc => {
        documentosTodos.push({
            ...doc,
            tipo_sistema: 'FLUXO_INTERNO',
            sistema_nome: 'Sistema Interno',
            sistema_cor: 'success',
            sistema_icone: 'fas fa-building'
        });
    });
    
    console.log('📋 Documentos unificados:', documentosTodos.length);
    
    if (documentosTodos.length === 0) {
        mostrarEstadoVazio();
        return;
    }
    
    // Ordenar por data (mais recentes primeiro)
    documentosTodos.sort((a, b) => {
        const dataA = new Date(a.created_at || a.data_upload || 0);
        const dataB = new Date(b.created_at || b.data_upload || 0);
        return dataB - dataA;
    });
    
    // Renderizar cada documento
    documentosTodos.forEach(doc => {
        const itemDiv = document.createElement('div');
        itemDiv.className = `document-item-modern document-${doc.tipo_sistema.toLowerCase()}`;
        itemDiv.dataset.docId = doc.id;
        itemDiv.dataset.tipoSistema = doc.tipo_sistema;
        
        if (doc.tipo_sistema === 'ZAPSIGN') {
            itemDiv.dataset.token = doc.token;
            renderizarDocumentoZapSign(itemDiv, doc);
        } else {
            renderizarDocumentoFluxo(itemDiv, doc);
        }
        
        container.appendChild(itemDiv);
    });
    
    // Atualizar estatísticas
    atualizarEstatisticasUnificadas();
}

// ===== ADICIONAR ESTILOS MODERNOS =====
function adicionarEstilosModernos() {
    const styleId = 'estilos-documentos-modernos';
    
    // Verificar se já existe
    if (document.getElementById(styleId)) {
        return;
    }
    
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = `
        /* Container principal dos documentos */
        .document-item-modern {
            margin-bottom: 1.5rem;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            background: white;
        }
        
        .document-item-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }
        
        /* Card principal */
        .document-card-modern {
            padding: 1.5rem;
        }
        
        /* Header do documento */
        .document-header-modern {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        
        .document-icon-modern {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .document-title-section {
            flex: 1;
            min-width: 0;
        }
        
        .document-title-modern {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 0.5rem 0;
        }
        
        /* Badges organizados */
        .document-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        
        .badge {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .badge-sistema {
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .badge-origem {
            color: white !important;
        }
        
        .badge-status.status-pending,
        .badge-status.status-aguardando-assinatura {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-status.status-signed,
        .badge-status.status-assinado {
            background-color: #28a745;
            color: white;
        }
        
        .badge-status.status-refused {
            background-color: #dc3545;
            color: white;
        }
        
        .badge-urgencia {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Ações do documento */
        .document-actions-modern {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: flex-start;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-action.primary {
            background: #007bff;
            color: white;
        }
        
        .btn-action.secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-action.success {
            background: #28a745;
            color: white;
        }
        
        .btn-action.warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Informações do associado */
        .document-associado-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .icon-info {
            width: 16px;
            color: #6c757d;
            flex-shrink: 0;
        }
        
        .info-content {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 0.875rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        /* Grid de metadados */
        .document-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .meta-item-modern {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #007bff;
        }
        
        .meta-icon {
            width: 16px;
            color: #007bff;
            flex-shrink: 0;
        }
        
        .meta-content {
            flex: 1;
            min-width: 0;
        }
        
        .meta-label {
            display: block;
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .meta-value {
            display: block;
            font-size: 0.875rem;
            color: #2c3e50;
            font-weight: 600;
            word-break: break-word;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .document-header-modern {
                flex-direction: column;
                gap: 1rem;
            }
            
            .document-actions-modern {
                width: 100%;
                justify-content: center;
            }
            
            .document-meta-grid {
                grid-template-columns: 1fr;
            }
            
            .document-badges {
                justify-content: flex-start;
            }
            
            .btn-action {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
            
            .btn-text {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .document-card-modern {
                padding: 1rem;
            }
            
            .info-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .document-badges {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.5rem;
            }
            
            .document-actions-modern {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
                padding: 0.6rem;
            }
        }
        
        /* Estados de loading e transições */
        .document-item-modern {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .document-item-modern:nth-child(even) {
            animation-delay: 0.1s;
        }
        
        .document-item-modern:nth-child(odd) {
            animation-delay: 0.2s;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Melhorias visuais */
        .document-item-modern.document-zapsign {
            border-left: 4px solid #007bff;
        }
        
        .document-item-modern.document-fluxo_interno {
            border-left: 4px solid #28a745;
        }
        
        /* Efeitos de hover aprimorados */
        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .btn-action.primary:hover {
            background: #0056b3;
        }
        
        .btn-action.success:hover {
            background: #1e7e34;
        }
        
        .btn-action.warning:hover {
            background: #e0a800;
        }
        
        .btn-action.secondary:hover {
            background: #545b62;
        }
        
        /* Indicadores de sistema melhorados */
        .badge-sistema.bg-primary {
            background: linear-gradient(135deg, #007bff, #0056b3) !important;
        }
        
        .badge-sistema.bg-success {
            background: linear-gradient(135deg, #28a745, #1e7e34) !important;
        }
        
        /* Scroll personalizado para container se necessário */
        .documents-list {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }
        
        .documents-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .documents-list::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 3px;
        }
        
        .documents-list::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }
        
        .documents-list::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
    `;
    
    document.head.appendChild(style);
}

// ===== RENDERIZAÇÃO ESPECÍFICA PARA ZAPSIGN =====
function renderizarDocumentoZapSign(container, doc) {
    const statusIcon = getStatusIconZapSign(doc.status);
    const actionButtons = getActionButtonsZapSign(doc);
    
    container.innerHTML = `
        <div class="document-card-modern">
            <!-- Header com badges organizados -->
            <div class="document-header-modern">
                <div class="document-icon-modern">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="document-title-section">
                    <h6 class="document-title-modern">${escapeHtml(doc.name)}</h6>
                    <div class="document-badges">
                        <span class="badge badge-sistema bg-${doc.sistema_cor}">
                            <i class="${doc.sistema_icone}"></i> ${doc.sistema_nome}
                        </span>
                        <span class="badge badge-status status-${doc.status}">
                            ${statusIcon} ${doc.status_label}
                        </span>
                    </div>
                </div>
                <div class="document-actions-modern">
                    ${actionButtons}
                </div>
            </div>
            
            <!-- Informações do Associado (se existir) -->
            ${doc.associado?.id ? `
            <div class="document-associado-info">
                <div class="info-row">
                    <i class="fas fa-user icon-info"></i>
                    <div class="info-content">
                        <span class="info-label">Associado:</span>
                        <span class="info-value">${doc.associado.id} - ${escapeHtml(doc.associado.nome || 'N/A')}</span>
                    </div>
                </div>
                ${doc.associado.situacao ? `
                <div class="info-row">
                    <i class="fas fa-info-circle icon-info"></i>
                    <div class="info-content">
                        <span class="info-label">Situação:</span>
                        <span class="badge bg-secondary">${escapeHtml(doc.associado.situacao)}</span>
                    </div>
                </div>
                ` : ''}
            </div>
            ` : ''}
            
            <!-- Grid de informações técnicas -->
            <div class="document-meta-grid">
                <div class="meta-item-modern">
                    <i class="fas fa-calendar-plus meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">Criado</span>
                        <span class="meta-value">${doc.created_at_formatted}</span>
                    </div>
                </div>
                <div class="meta-item-modern">
                    <i class="fas fa-clock meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">Atualizado</span>
                        <span class="meta-value">${doc.last_update_formatted}</span>
                    </div>
                </div>
                <div class="meta-item-modern">
                    <i class="fas fa-hourglass-half meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">Tempo</span>
                        <span class="meta-value">${doc.tempo_desde_criacao}</span>
                    </div>
                </div>
                <div class="meta-item-modern">
                    <i class="fas fa-folder meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">Pasta</span>
                        <span class="meta-value">${doc.folder_path}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// ===== RENDERIZAÇÃO ESPECÍFICA PARA FLUXO INTERNO =====
function renderizarDocumentoFluxo(container, doc) {
    const statusIcon = getStatusIconFluxo(doc.status_fluxo);
    const actionButtons = getActionButtonsFluxo(doc);
    const isPresencial = doc.tipo_origem === 'FISICO';
    const diasEmProcesso = doc.dias_em_processo || 0;
    
    container.innerHTML = `
        <div class="document-card-modern">
            <!-- Header com badges organizados -->
            <div class="document-header-modern">
                <div class="document-icon-modern">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="document-title-section">
                    <h6 class="document-title-modern">Ficha de Filiação</h6>
                    <div class="document-badges">
                        <span class="badge badge-sistema bg-${doc.sistema_cor}">
                            <i class="${doc.sistema_icone}"></i> ${doc.sistema_nome}
                        </span>
                        <span class="badge badge-origem bg-${isPresencial ? 'warning' : 'info'}">
                            <i class="fas fa-${isPresencial ? 'handshake' : 'desktop'}"></i> ${isPresencial ? 'Presencial' : 'Virtual'}
                        </span>
                        <span class="badge badge-status status-${doc.status_fluxo?.toLowerCase().replace('_', '-')}">
                            ${statusIcon} ${doc.status_descricao || doc.status_fluxo}
                        </span>
                        ${diasEmProcesso > 0 ? `
                        <span class="badge badge-urgencia bg-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${diasEmProcesso} dias
                        </span>
                        ` : ''}
                    </div>
                </div>
                <div class="document-actions-modern">
                    ${actionButtons}
                </div>
            </div>
            
            <!-- Informações do Associado -->
            <div class="document-associado-info">
                <div class="info-row">
                    <i class="fas fa-user icon-info"></i>
                    <div class="info-content">
                        <span class="info-label">Associado:</span>
                        <span class="info-value">${doc.associado_nome || 'N/A'}</span>
                    </div>
                </div>
                <div class="info-row">
                    <i class="fas fa-id-card icon-info"></i>
                    <div class="info-content">
                        <span class="info-label">CPF:</span>
                        <span class="info-value">${formatarCPF(doc.associado_cpf)}</span>
                    </div>
                </div>
            </div>
            
            <!-- Grid de informações técnicas -->
            <div class="document-meta-grid">
                <div class="meta-item-modern">
                    <i class="fas fa-calendar-plus meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">Upload</span>
                        <span class="meta-value">${formatarData(doc.data_upload)}</span>
                    </div>
                </div>
                <div class="meta-item-modern">
                    <i class="fas fa-building meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">Departamento</span>
                        <span class="meta-value">${doc.departamento_atual_nome || 'Comercial'}</span>
                    </div>
                </div>
                <div class="meta-item-modern">
                    <i class="fas fa-hashtag meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">ID Associado</span>
                        <span class="meta-value">${doc.associado_id || 'N/A'}</span>
                    </div>
                </div>
                <div class="meta-item-modern">
                    <i class="fas fa-route meta-icon"></i>
                    <div class="meta-content">
                        <span class="meta-label">Origem</span>
                        <span class="meta-value">${isPresencial ? 'Digitalizada' : 'Sistema'}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// ===== FUNÇÕES DE SUPORTE PARA ÍCONES E AÇÕES =====
function getStatusIconZapSign(status) {
    const icons = {
        'pending': '<i class="fas fa-clock"></i>',
        'signed': '<i class="fas fa-check-circle"></i>',
        'refused': '<i class="fas fa-times-circle"></i>',
        'expired': '<i class="fas fa-hourglass-end"></i>'
    };
    
    return icons[status] || '<i class="fas fa-question-circle"></i>';
}

function getStatusIconFluxo(status) {
    const icons = {
        'DIGITALIZADO': '<i class="fas fa-upload"></i>',
        'AGUARDANDO_ASSINATURA': '<i class="fas fa-clock"></i>',
        'ASSINADO': '<i class="fas fa-check"></i>',
        'FINALIZADO': '<i class="fas fa-flag-checkered"></i>'
    };
    
    return icons[status] || '<i class="fas fa-file"></i>';
}

function getActionButtonsZapSign(doc) {
    let buttons = '';
    
    // Botão visualizar
    if (doc.original_file || doc.signed_file) {
        buttons += `
            <button class="btn-action secondary" onclick="visualizarDocumentoZapSign('${doc.token}', '${doc.status}')" title="Visualizar documento">
                <i class="fas fa-eye"></i>
                <span class="btn-text">Visualizar</span>
            </button>
        `;
    }
    
    // Botão assinar presidente (ZapSign)
    if (doc.status === 'pending' && doc.signed_file) {
        buttons += `
            <button class="btn-action primary" onclick="abrirLinkAssinaturaPresidente('${doc.token}')" title="Assinar como presidente">
                <i class="fas fa-signature"></i>
                <span class="btn-text">Assinar</span>
            </button>
        `;
    }
    
    // Ações específicas por status
    switch (doc.status) {
        case 'pending':
            buttons += `
                <button class="btn-action warning" onclick="acompanharDocumentoZapSign('${doc.token}')" title="Ver detalhes">
                    <i class="fas fa-users"></i>
                    <span class="btn-text">Detalhes</span>
                </button>
            `;
            break;
            
        case 'signed':
            if (doc.signed_file) {
                buttons += `
                    <button class="btn-action success" onclick="baixarDocumentoAssinado('${doc.token}')" title="Baixar assinado">
                        <i class="fas fa-download"></i>
                        <span class="btn-text">Baixar</span>
                    </button>
                `;
            }
            break;
    }
    
    return buttons;
}

function getActionButtonsFluxo(doc) {
    let buttons = '';
    
    // Botão download sempre disponível
    buttons += `
        <button class="btn-action primary" onclick="downloadDocumentoFluxo(${doc.id})" title="Download">
            <i class="fas fa-download"></i>
            <span class="btn-text">Baixar</span>
        </button>
    `;
    
    // Ações específicas por status
    switch (doc.status_fluxo) {
        case 'AGUARDANDO_ASSINATURA':
            buttons += `
                <button class="btn-action success" onclick="abrirModalAssinaturaFluxo(${doc.id})" title="Assinar Documento">
                    <i class="fas fa-signature"></i>
                    <span class="btn-text">Assinar</span>
                </button>
            `;
            break;
            
        case 'ASSINADO':
            buttons += `
                <button class="btn-action warning" onclick="finalizarProcessoFluxo(${doc.id})" title="Finalizar Processo">
                    <i class="fas fa-flag-checkered"></i>
                    <span class="btn-text">Finalizar</span>
                </button>
            `;
            break;
    }
    
    // Histórico sempre disponível
    buttons += `
        <button class="btn-action secondary" onclick="verHistoricoFluxo(${doc.id})" title="Ver Histórico">
            <i class="fas fa-history"></i>
            <span class="btn-text">Histórico</span>
        </button>
    `;
    
    return buttons;
}

// ===== OTIMIZAÇÃO RESPONSIVA =====
function otimizarResponsividade() {
    // Verificar tamanho da tela e ajustar interface
    const isMobile = window.innerWidth <= 768;
    const isSmallMobile = window.innerWidth <= 480;
    
    if (isMobile) {
        // Ocultar texto dos botões em dispositivos móveis
        const btnTexts = document.querySelectorAll('.btn-text');
        btnTexts.forEach(text => {
            text.style.display = isSmallMobile ? 'none' : 'inline';
        });
        
        // Ajustar grid de metadados para uma coluna em dispositivos pequenos
        const metaGrids = document.querySelectorAll('.document-meta-grid');
        metaGrids.forEach(grid => {
            if (isSmallMobile) {
                grid.style.gridTemplateColumns = '1fr';
            } else {
                grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(200px, 1fr))';
            }
        });
    }
}

// ===== MELHORIAS NA PERFORMANCE =====
function otimizarPerformance() {
    // Debounce para resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(otimizarResponsividade, 150);
    });
    
    // Lazy loading para elementos visuais pesados (se necessário)
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });
    
    // Observar elementos que entram na viewport
    const observeElements = () => {
        document.querySelectorAll('.document-item-modern').forEach(el => {
            observer.observe(el);
        });
    };
    
    // Chamar quando elementos são adicionados
    const originalAppendChild = Element.prototype.appendChild;
    Element.prototype.appendChild = function(child) {
        const result = originalAppendChild.call(this, child);
        if (child.classList && child.classList.contains('document-item-modern')) {
            observer.observe(child);
        }
        return result;
    };
}

// ===== FUNÇÃO PARA GARANTIR COMPATIBILIDADE =====
function garantirCompatibilidade() {
    // Verificar se jQuery está disponível
    const jqueryDisponivel = typeof $ !== 'undefined';
    
    // Verificar se Bootstrap está disponível
    const bootstrapDisponivel = typeof bootstrap !== 'undefined';
    
    // Log de compatibilidade
    console.log('📋 Verificação de Compatibilidade:');
    console.log('  jQuery:', jqueryDisponivel ? '✅ Disponível' : '❌ Não disponível');
    console.log('  Bootstrap:', bootstrapDisponivel ? '✅ Disponível' : '❌ Não disponível');
    
    // Se Bootstrap não estiver disponível, adicionar polyfill básico para modais
    if (!bootstrapDisponivel && typeof $ !== 'undefined') {
        console.log('🔧 Aplicando polyfill para Bootstrap...');
        
        // Polyfill básico para modal
        if (!$.fn.modal) {
            $.fn.modal = function(action) {
                return this.each(function() {
                    const $this = $(this);
                    if (action === 'show') {
                        $this.show().css('display', 'block').addClass('show');
                        $('body').addClass('modal-open');
                    } else if (action === 'hide') {
                        $this.hide().removeClass('show');
                        $('body').removeClass('modal-open');
                    }
                });
            };
        }
    }
    
    // Garantir que Font Awesome está carregado
    const fontAwesome = document.querySelector('link[href*="font-awesome"], link[href*="fontawesome"]');
    if (!fontAwesome) {
        console.log('⚠️ Font Awesome pode não estar carregado - alguns ícones podem não aparecer');
    }
    
    return {
        jquery: jqueryDisponivel,
        bootstrap: bootstrapDisponivel,
        fontAwesome: !!fontAwesome
    };
}

// ===== FUNÇÃO DE HEALTH CHECK =====
function executarHealthCheck() {
    console.log('🏥 Executando Health Check do Sistema...');
    
    const checks = {
        permissao: temPermissao,
        containerDocumentos: !!document.getElementById('documentsList'),
        apis: {
            zapsign: false,
            fluxoInterno: false
        },
        bibliotecas: garantirCompatibilidade(),
        elementos: {
            filtros: !!document.getElementById('filterStatus'),
            busca: !!document.getElementById('searchInput'),
            modais: !!document.getElementById('assinaturaModal')
        }
    };
    
    // Teste rápido das APIs (sem fazer requisições completas)
    const testarAPIs = async () => {
        try {
            // Teste ZapSign
            const responseZap = await fetch('../api/documentos/zapsign_listar_documentos.php?page=1&limit=1', {
                method: 'HEAD'
            });
            checks.apis.zapsign = responseZap.ok;
        } catch (e) {
            checks.apis.zapsign = false;
        }
        
        try {
            // Teste Fluxo Interno
            const responseFluxo = await fetch('../api/documentos/documentos_fluxo_listar.php', {
                method: 'HEAD'
            });
            checks.apis.fluxoInterno = responseFluxo.ok;
        } catch (e) {
            checks.apis.fluxoInterno = false;
        }
        
        console.log('📊 Resultado do Health Check:', checks);
        
        // Mostrar warnings se necessário
        if (!checks.apis.zapsign) {
            console.warn('⚠️ API ZapSign não está respondendo');
        }
        
        if (!checks.apis.fluxoInterno) {
            console.warn('⚠️ API Fluxo Interno não está respondendo');
        }
        
        if (!checks.elementos.filtros) {
            console.warn('⚠️ Elementos de filtro não encontrados');
        }
        
        return checks;
    };
    
    // Executar testes assíncronos
    testarAPIs();
    
    return checks;
}

// ===== AÇÕES ESPECÍFICAS PARA ZAPSIGN =====
async function abrirLinkAssinaturaPresidente(token) {
    try {
        notifications.show('Buscando link de assinatura...', 'info', 2000);
        
        const response = await fetch(`../api/documentos/zapsign_detalhar_documento.php?token=${token}`);
        const data = await response.json();
        
        if (data.status === 'success' && data.data.signers) {
            const presidente = data.data.signers[1];
            
            if (!presidente) {
                throw new Error('Presidente (signatário 2) não encontrado');
            }
            
            if (!presidente.sign_url) {
                throw new Error('Link de assinatura do presidente não disponível');
            }
            
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

function visualizarDocumentoZapSign(token, status) {
    const documento = documentosZapSign.find(doc => doc.token === token);
    
    if (!documento) {
        notifications.show('Documento não encontrado na lista atual', 'error');
        return;
    }
    
    let linkParaAbrir = null;
    
    if (status === 'signed' && documento.signed_file) {
        linkParaAbrir = documento.signed_file;
    } else if (documento.original_file) {
        linkParaAbrir = documento.original_file;
    } else {
        notifications.show('Nenhum arquivo disponível para visualização', 'warning');
        return;
    }
    
    window.open(linkParaAbrir, '_blank');
}

function acompanharDocumentoZapSign(token) {
    const documento = documentosZapSign.find(doc => doc.token === token);
    
    if (!documento) {
        notifications.show('Documento não encontrado', 'error');
        return;
    }
    
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle"></i>
                        Detalhes do Documento ZapSign
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
    
    const link = document.createElement('a');
    link.href = documento.signed_file;
    link.download = `${documento.name}_assinado.pdf`;
    link.target = '_blank';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    notifications.show('Download iniciado', 'success');
}

// ===== AÇÕES ESPECÍFICAS PARA FLUXO INTERNO =====
function downloadDocumentoFluxo(documentoId) {
    console.log('Iniciando download do documento Fluxo ID:', documentoId);
    
    notifications.show('Preparando download da ficha PDF...', 'info', 2000);
    
    fetch('../api/documentos/documentos_download.php?id=' + documentoId, {
        method: 'HEAD'
    })
    .then(response => {
        if (response.ok) {
            const link = document.createElement('a');
            link.href = '../api/documentos/documentos_download.php?id=' + documentoId;
            link.target = '_blank';
            link.download = `ficha_filiacao_${documentoId}.pdf`;
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
        
        window.open('../api/documentos/documentos_download.php?id=' + documentoId, '_blank');
    });
}

function abrirModalAssinaturaFluxo(documentoId) {
    const documento = documentosFluxo.find(doc => doc.id === documentoId);
    
    if (!documento) {
        notifications.show('Documento não encontrado', 'error');
        return;
    }

    documentoSelecionado = documento;
    
    // Preencher informações do documento
    document.getElementById('documentoId').value = documentoId;
    document.getElementById('previewAssociado').textContent = documento.associado_nome;
    document.getElementById('previewCPF').textContent = formatarCPF(documento.associado_cpf);
    document.getElementById('previewData').textContent = formatarData(documento.data_upload);
    document.getElementById('previewOrigem').textContent = documento.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Presencial';
    document.getElementById('previewSubtitulo').textContent = documento.tipo_origem === 'VIRTUAL' ? 'Gerado pelo sistema' : 'Digitalizado';

    // Resetar upload
    document.getElementById('uploadSection').classList.add('d-none');
    document.getElementById('fileInfo').innerHTML = '';
    arquivoAssinado = null;

    // Mostrar modal
    new bootstrap.Modal(document.getElementById('assinaturaModal')).show();
}

function finalizarProcessoFluxo(documentoId) {
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
                carregarTodosDocumentos();
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

function verHistoricoFluxo(documentoId) {
    fetch('../api/documentos/documentos_historico_fluxo.php?documento_id=' + documentoId)
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                renderizarHistoricoModal(result.data);
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

function renderizarHistoricoModal(historico) {
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

// ===== ASSINATURA DE DOCUMENTOS (SISTEMA INTERNO) =====
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
                
                carregarTodosDocumentos();
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

// ===== CARREGAMENTO DE ESTATÍSTICAS UNIFICADAS =====
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
            
            if (data.cache) {
                console.log('📊 Estatísticas carregadas do cache');
            } else {
                console.log('📊 Estatísticas obtidas da API ZapSign');
            }
            
            if (!data.cache) {
                setTimeout(carregarEstatisticasZapSign, 5 * 60 * 1000);
            }
        } else {
            throw new Error(data.message || 'Erro desconhecido');
        }
        
    } catch (error) {
        console.error('❌ Erro ao carregar estatísticas:', error);
        mostrarEstatisticasErro();
        setTimeout(carregarEstatisticasZapSign, 30 * 1000);
    }
}

function atualizarCardsEstatisticas(dados) {
    const aguardandoElement = document.querySelector('.stat-card:nth-child(1) .stat-value');
    const aguardandoStatus = document.querySelector('.stat-card:nth-child(1) .stat-change');
    
    if (aguardandoElement) {
        // Somar pendentes do ZapSign + aguardando assinatura do fluxo interno
        const totalPendentes = (dados.pending || 0) + documentosFluxo.filter(doc => doc.status_fluxo === 'AGUARDANDO_ASSINATURA').length;
        aguardandoElement.textContent = totalPendentes;
        
        if (aguardandoStatus) {
            if (totalPendentes > 0) {
                aguardandoStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Requer atenção';
                aguardandoStatus.className = 'stat-change negative';
            } else {
                aguardandoStatus.innerHTML = '<i class="fas fa-check-circle"></i> Tudo em dia';
                aguardandoStatus.className = 'stat-change positive';
            }
        }
    }
    
    const assinadosHojeElement = document.querySelector('.stat-card:nth-child(2) .stat-value');
    const assinadosHojeStatus = document.querySelector('.stat-card:nth-child(2) .stat-change');
    
    if (assinadosHojeElement) {
        const hoje = new Date().toDateString();
        const assinadosZapSignHoje = (dados.documentos_recentes || []).filter(doc => {
            if (doc.status === 'signed' && doc.created_at) {
                const docDate = new Date(doc.created_at).toDateString();
                return docDate === hoje;
            }
            return false;
        }).length;
        
        // Somar assinados do ZapSign + assinados do fluxo interno hoje
        const assinadosFluxoHoje = documentosFluxo.filter(doc => {
            if (doc.status_fluxo === 'ASSINADO' && doc.data_upload) {
                const docDate = new Date(doc.data_upload).toDateString();
                return docDate === hoje;
            }
            return false;
        }).length;
        
        const totalAssinadosHoje = assinadosZapSignHoje + assinadosFluxoHoje;
        assinadosHojeElement.textContent = totalAssinadosHoje;
        
        if (assinadosHojeStatus) {
            assinadosHojeStatus.innerHTML = '<i class="fas fa-arrow-up"></i> Produtividade';
            assinadosHojeStatus.className = 'stat-change positive';
        }
    }
    
    const totalAssinadosElement = document.querySelector('.stat-card:nth-child(3) .stat-value');
    if (totalAssinadosElement) {
        const totalZapSign = dados.signed || 0;
        const totalFluxo = documentosFluxo.filter(doc => doc.status_fluxo === 'ASSINADO' || doc.status_fluxo === 'FINALIZADO').length;
        totalAssinadosElement.textContent = totalZapSign + totalFluxo;
    }
    
    const tempoMedioElement = document.querySelector('.stat-card:nth-child(4) .stat-value');
    if (tempoMedioElement) {
        const tempo = dados.tempo_medio_assinatura || 0;
        tempoMedioElement.textContent = tempo > 0 ? `${tempo}h` : '-';
    }
    
    atualizarIconesCards(dados);
}

function atualizarEstatisticasUnificadas() {
    // Atualizar contadores com dados unificados
    const totalZapSign = documentosZapSign.length;
    const totalFluxo = documentosFluxo.length;
    const totalPendentesZapSign = documentosZapSign.filter(doc => doc.status === 'pending').length;
    const totalPendentesFluxo = documentosFluxo.filter(doc => doc.status_fluxo === 'AGUARDANDO_ASSINATURA').length;
    
    console.log('📊 Estatísticas Unificadas:', {
        totalDocumentos: totalZapSign + totalFluxo,
        zapSign: totalZapSign,
        fluxoInterno: totalFluxo,
        totalPendentes: totalPendentesZapSign + totalPendentesFluxo
    });
}

function atualizarIconesCards(dados) {
    const cards = document.querySelectorAll('.stat-card');
    
    if (cards[0]) {
        const icon = cards[0].querySelector('.stat-icon');
        if (icon) {
            const totalPendentes = (dados.pending || 0) + documentosFluxo.filter(doc => doc.status_fluxo === 'AGUARDANDO_ASSINATURA').length;
            if (totalPendentes > 5) {
                icon.className = 'stat-icon danger';
            } else if (totalPendentes > 0) {
                icon.className = 'stat-icon warning';
            } else {
                icon.className = 'stat-icon success';
            }
        }
    }
    
    if (cards[1]) {
        const icon = cards[1].querySelector('.stat-icon');
        if (icon) {
            icon.className = 'stat-icon success';
        }
    }
    
    if (cards[2]) {
        const icon = cards[2].querySelector('.stat-icon');
        if (icon) {
            icon.className = 'stat-icon primary';
        }
    }
    
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

function mostrarEstatisticasErro() {
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
    
    const statusChanges = document.querySelectorAll('.stat-change');
    statusChanges.forEach(element => {
        element.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro ao carregar';
        element.className = 'stat-change negative';
    });
    
    notifications.show('Erro ao carregar estatísticas do ZapSign. Tentando novamente...', 'warning', 5000);
}

function forcarAtualizacaoEstatisticas() {
    carregarEstatisticasZapSign();
    notifications.show('Atualizando estatísticas...', 'info', 2000);
}

// ===== SISTEMA DE VALORES BASE =====
function abrirModalEditarValores() {
    console.log('Abrindo modal de edição de valores base...');
    
    const modal = document.getElementById('modalEditarValoresBase');
    if (!modal) {
        notifications.show('Modal de edição não encontrado. Verifique se o HTML do modal foi incluído na página.', 'error');
        return;
    }
    
    carregarValoresBaseAtuais()
        .then(() => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
                console.log('✓ Modal aberto via Bootstrap 5');
            } else if (typeof $ !== 'undefined' && $.fn.modal) {
                $('#modalEditarValoresBase').modal('show');
                console.log('✓ Modal aberto via jQuery');
            } else {
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.classList.add('modal-open');
                
                let backdrop = document.querySelector('.modal-backdrop');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
                
                console.log('✓ Modal aberto via fallback');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar valores:', error);
            notifications.show('Erro ao carregar valores atuais: ' + error.message, 'error');
        });
}

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
                    
                    const campoSocial = document.getElementById('valorBaseSocial');
                    const campoJuridico = document.getElementById('valorBaseJuridico');
                    
                    if (campoSocial && campoJuridico) {
                        campoSocial.value = valoresBaseAtuais.social.valor_base;
                        campoJuridico.value = valoresBaseAtuais.juridico.valor_base;
                        
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

function calcularImpacto() {
    const valorSocial = parseFloat(document.getElementById('valorBaseSocial').value) || 0;
    const valorJuridico = parseFloat(document.getElementById('valorBaseJuridico').value) || 0;
    
    atualizarPreviewValores(valorSocial, valorJuridico);
    simularImpactoAssociados(valorSocial, valorJuridico);
}

function atualizarPreviewValores(valorSocial, valorJuridico) {
    const percentuais = {
        'Contribuinte': 100,
        'Aluno': 50,
        'Remido': 0
    };
    
    document.getElementById('impactoSocialContribuinte').textContent = 
        'R$ ' + ((valorSocial * percentuais.Contribuinte) / 100).toFixed(2).replace('.', ',');
    document.getElementById('impactoSocialAluno').textContent = 
        'R$ ' + ((valorSocial * percentuais.Aluno) / 100).toFixed(2).replace('.', ',');
    document.getElementById('impactoSocialRemido').textContent = 
        'R$ ' + ((valorSocial * percentuais.Remido) / 100).toFixed(2).replace('.', ',');
    
    document.getElementById('impactoJuridicoContribuinte').textContent = 
        'R$ ' + ((valorJuridico * percentuais.Contribuinte) / 100).toFixed(2).replace('.', ',');
    document.getElementById('impactoJuridicoAluno').textContent = 
        'R$ ' + ((valorJuridico * percentuais.Aluno) / 100).toFixed(2).replace('.', ',');
    document.getElementById('impactoJuridicoRemido').textContent = 
        'R$ ' + ((valorJuridico * percentuais.Remido) / 100).toFixed(2).replace('.', ',');
}

function simularImpactoAssociados(valorSocial, valorJuridico) {
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
    });
}

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
    
    const btnConfirmar = document.getElementById('btnConfirmarAlteracao');
    const textoOriginal = btnConfirmar.innerHTML;
    btnConfirmar.disabled = true;
    btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    
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
            
            fecharModalEditarValores();
            
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
        btnConfirmar.disabled = false;
        btnConfirmar.innerHTML = textoOriginal;
    });
}

function fecharModalEditarValores() {
    const modal = document.getElementById('modalEditarValoresBase');
    if (!modal) return;
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }
    } else if (typeof $ !== 'undefined' && $.fn.modal) {
        $('#modalEditarValoresBase').modal('hide');
    } else {
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
        
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }
}

// ===== SISTEMA DE RECÁLCULO DE SERVIÇOS =====
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('active');
        console.log('Loading ativado');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        console.log('Loading desativado');
    }
}

function recalcularServicos() {
    if (!confirm(
        'ATENÇÃO!\n\n' +
        'Esta ação irá recalcular TODOS os valores dos serviços dos associados ' +
        'baseado nos valores base atuais do sistema.\n\n' +
        'Isso pode alterar centenas de registros!\n\n' +
        'Deseja continuar?'
    )) {
        return;
    }

    const btnRecalcular = document.getElementById('btnRecalcular');
    const originalText = btnRecalcular.innerHTML;
    
    btnRecalcular.disabled = true;
    btnRecalcular.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recalculando...';
    
    showLoading();
    
    console.log('Iniciando recálculo dos serviços...');
    
    fetch('../api/recalcular_servicos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(responseText => {
        console.log('Response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Erro ao fazer parse JSON:', e);
            throw new Error('Resposta inválida do servidor');
        }
        
        if (data.status === 'success') {
            console.log('✓ Recálculo concluído:', data.data);
            
            let mensagem = data.message;
            
            if (data.data.total_valores_alterados > 0) {
                mensagem += '\n\n📊 RESUMO:';
                mensagem += '\n• Total processados: ' + data.data.total_servicos_processados;
                mensagem += '\n• Valores alterados: ' + data.data.total_valores_alterados;
                mensagem += '\n• Sem alteração: ' + data.data.total_sem_alteracao;
                
                if (data.data.economia_total !== 0) {
                    const economia = data.data.economia_total;
                    if (economia > 0) {
                        mensagem += '\n• Aumento total: +R$ ' + economia.toFixed(2).replace('.', ',');
                    } else {
                        mensagem += '\n• Redução total: R$ ' + Math.abs(economia).toFixed(2).replace('.', ',');
                    }
                }
                
                mensagem += '\n\n🕒 Processado em: ' + data.data.data_recalculo;
                
                if (data.data.alteracoes_detalhadas && data.data.alteracoes_detalhadas.length > 0) {
                    mensagem += '\n\n📋 EXEMPLOS DE ALTERAÇÕES:';
                    data.data.alteracoes_detalhadas.slice(0, 5).forEach(alt => {
                        mensagem += `\n• ${alt.associado} (${alt.servico}): R$ ${alt.valor_anterior.toFixed(2)} → R$ ${alt.valor_novo.toFixed(2)}`;
                    });
                    
                    if (data.data.alteracoes_detalhadas.length > 5) {
                        mensagem += `\n... e mais ${data.data.alteracoes_detalhadas.length - 5} alterações`;
                    }
                }
            }
            
            alert(mensagem);
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
            
        } else {
            console.error('Erro na API:', data);
            alert('❌ ERRO: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro de rede:', error);
        alert('❌ Erro de comunicação: ' + error.message);
    })
    .finally(() => {
        btnRecalcular.disabled = false;
        btnRecalcular.innerHTML = originalText;
        hideLoading();
    });
}

// ===== SISTEMA DE FILTROS =====
function configurarFiltrosZapSign() {
    const filterStatus = document.getElementById('filterStatus');
    const searchInput = document.getElementById('searchInput');
    const filterOrdenacao = document.getElementById('filterOrdenacao');
    
    if (filterStatus) {
        filterStatus.addEventListener('change', () => {
            carregarTodosDocumentos(true);
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            carregarTodosDocumentos(true);
        }, 500));
    }
    
    if (filterOrdenacao) {
        filterOrdenacao.addEventListener('change', () => {
            carregarTodosDocumentos(true);
        });
    }
}

// ===== FUNÇÕES DE APOIO PARA INTERFACE =====
function mostrarSkeletonLoading() {
    const container = document.getElementById('documentsList');
    container.innerHTML = '';
    
    // Adicionar estilos modernos primeiro
    adicionarEstilosModernos();
    
    for (let i = 0; i < 4; i++) {
        const skeleton = document.createElement('div');
        skeleton.className = 'document-item-modern loading-skeleton';
        skeleton.innerHTML = `
            <div class="document-card-modern">
                <div class="document-header-modern">
                    <div style="width: 48px; height: 48px; background: #e0e0e0; border-radius: 10px;"></div>
                    <div style="flex: 1;">
                        <div style="height: 20px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem; width: 60%;"></div>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                            <div style="height: 24px; background: #e0e0e0; border-radius: 6px; width: 100px;"></div>
                            <div style="height: 24px; background: #e0e0e0; border-radius: 6px; width: 80px;"></div>
                            <div style="height: 24px; background: #e0e0e0; border-radius: 6px; width: 120px;"></div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div style="height: 32px; background: #e0e0e0; border-radius: 6px; width: 80px;"></div>
                        <div style="height: 32px; background: #e0e0e0; border-radius: 6px; width: 100px;"></div>
                    </div>
                </div>
                <div style="background: #f5f5f5; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                    <div style="height: 16px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.5rem; width: 70%;"></div>
                    <div style="height: 16px; background: #e0e0e0; border-radius: 4px; width: 50%;"></div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div style="background: #f5f5f5; border-radius: 6px; padding: 0.75rem; border-left: 3px solid #e0e0e0;">
                        <div style="height: 12px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.25rem; width: 40%;"></div>
                        <div style="height: 16px; background: #e0e0e0; border-radius: 4px; width: 80%;"></div>
                    </div>
                    <div style="background: #f5f5f5; border-radius: 6px; padding: 0.75rem; border-left: 3px solid #e0e0e0;">
                        <div style="height: 12px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.25rem; width: 50%;"></div>
                        <div style="height: 16px; background: #e0e0e0; border-radius: 4px; width: 70%;"></div>
                    </div>
                    <div style="background: #f5f5f5; border-radius: 6px; padding: 0.75rem; border-left: 3px solid #e0e0e0;">
                        <div style="height: 12px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.25rem; width: 35%;"></div>
                        <div style="height: 16px; background: #e0e0e0; border-radius: 4px; width: 60%;"></div>
                    </div>
                    <div style="background: #f5f5f5; border-radius: 6px; padding: 0.75rem; border-left: 3px solid #e0e0e0;">
                        <div style="height: 12px; background: #e0e0e0; border-radius: 4px; margin-bottom: 0.25rem; width: 45%;"></div>
                        <div style="height: 16px; background: #e0e0e0; border-radius: 4px; width: 85%;"></div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(skeleton);
    }
    
    // Adicionar animação de loading
    const loadingSkeletons = container.querySelectorAll('.loading-skeleton');
    loadingSkeletons.forEach(skeleton => {
        skeleton.style.animation = 'loading-pulse 1.5s ease-in-out infinite';
    });
    
    // Adicionar CSS da animação se não existir
    if (!document.getElementById('loading-animation-styles')) {
        const style = document.createElement('style');
        style.id = 'loading-animation-styles';
        style.textContent = `
            @keyframes loading-pulse {
                0% { opacity: 1; }
                50% { opacity: 0.6; }
                100% { opacity: 1; }
            }
            
            .loading-skeleton div {
                background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                background-size: 200% 100%;
                animation: loading-shimmer 2s infinite;
            }
            
            @keyframes loading-shimmer {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
        `;
        document.head.appendChild(style);
    }
}

function mostrarEstadoVazio() {
    const container = document.getElementById('documentsList');
    
    let mensagem = 'Nenhum documento encontrado';
    let icone = 'fas fa-inbox';
    let descricao = 'Ainda não há documentos registrados no sistema.';
    
    if (statusFiltro) {
        mensagem = `Nenhum documento encontrado com o filtro aplicado`;
        icone = 'fas fa-filter';
        descricao = 'Tente ajustar os filtros ou fazer uma nova busca.';
    }
    
    if (termoBusca) {
        mensagem += ` para "${termoBusca}"`;
        icone = 'fas fa-search';
        descricao = 'Tente usar outros termos de busca ou verifique a ortografia.';
    }
    
    container.innerHTML = `
        <div class="empty-state-modern">
            <div class="empty-state-content">
                <div class="empty-state-icon-wrapper">
                    <i class="${icone} empty-state-icon"></i>
                </div>
                <h5 class="empty-state-title">${mensagem}</h5>
                <p class="empty-state-description">${descricao}</p>
                
                ${statusFiltro || termoBusca ? `
                <div class="empty-state-actions">
                    <button class="btn-action primary" onclick="limparFiltros()">
                        <i class="fas fa-times"></i>
                        Limpar Filtros
                    </button>
                    <button class="btn-action secondary" onclick="carregarTodosDocumentos(true)">
                        <i class="fas fa-refresh"></i>
                        Atualizar
                    </button>
                </div>
                ` : `
                <div class="empty-state-actions">
                    <button class="btn-action primary" onclick="carregarTodosDocumentos(true)">
                        <i class="fas fa-refresh"></i>
                        Atualizar Lista
                    </button>
                </div>
                `}
                
                <div class="empty-state-tips">
                    <h6>💡 Dicas:</h6>
                    <ul>
                        <li><strong>ZapSign:</strong> Documentos enviados para assinatura digital</li>
                        <li><strong>Sistema Interno:</strong> Fichas de filiação do fluxo presencial</li>
                        <li>Use os filtros para encontrar documentos específicos</li>
                        <li>A lista é atualizada automaticamente a cada 30 segundos</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar estilos para o estado vazio se não existirem
    if (!document.getElementById('empty-state-styles')) {
        const style = document.createElement('style');
        style.id = 'empty-state-styles';
        style.textContent = `
            .empty-state-modern {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 400px;
                padding: 3rem 1rem;
            }
            
            .empty-state-content {
                text-align: center;
                max-width: 500px;
                background: white;
                border-radius: 16px;
                padding: 3rem 2rem;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }
            
            .empty-state-icon-wrapper {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
            }
            
            .empty-state-icon {
                font-size: 2.5rem;
                color: #6c757d;
            }
            
            .empty-state-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: #2c3e50;
                margin-bottom: 1rem;
            }
            
            .empty-state-description {
                font-size: 1rem;
                color: #6c757d;
                margin-bottom: 2rem;
                line-height: 1.5;
            }
            
            .empty-state-actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
                margin-bottom: 2rem;
                flex-wrap: wrap;
            }
            
            .empty-state-tips {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 1.5rem;
                text-align: left;
            }
            
            .empty-state-tips h6 {
                color: #495057;
                margin-bottom: 1rem;
                font-weight: 600;
            }
            
            .empty-state-tips ul {
                margin: 0;
                padding-left: 1.5rem;
                color: #6c757d;
            }
            
            .empty-state-tips li {
                margin-bottom: 0.5rem;
                line-height: 1.4;
            }
            
            .empty-state-tips strong {
                color: #495057;
            }
            
            @media (max-width: 768px) {
                .empty-state-content {
                    padding: 2rem 1.5rem;
                }
                
                .empty-state-actions {
                    flex-direction: column;
                    align-items: center;
                }
                
                .btn-action {
                    width: 100%;
                    max-width: 200px;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function mostrarErroCarregamento(mensagem) {
    const container = document.getElementById('documentsList');
    container.innerHTML = `
        <div class="error-state-modern">
            <div class="error-state-content">
                <div class="error-state-icon-wrapper">
                    <i class="fas fa-exclamation-triangle error-state-icon"></i>
                </div>
                <h5 class="error-state-title">Erro ao carregar documentos</h5>
                <p class="error-state-description">${escapeHtml(mensagem)}</p>
                
                <div class="error-state-actions">
                    <button class="btn-action primary" onclick="carregarTodosDocumentos(true)">
                        <i class="fas fa-redo"></i>
                        Tentar Novamente
                    </button>
                    <button class="btn-action secondary" onclick="window.location.reload()">
                        <i class="fas fa-refresh"></i>
                        Recarregar Página
                    </button>
                </div>
                
                <div class="error-state-details">
                    <h6>🔧 Soluções possíveis:</h6>
                    <ul>
                        <li>Verifique sua conexão com a internet</li>
                        <li>Aguarde alguns minutos e tente novamente</li>
                        <li>Recarregue a página completamente</li>
                        <li>Entre em contato com o suporte se o problema persistir</li>
                    </ul>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar estilos para o estado de erro se não existirem
    if (!document.getElementById('error-state-styles')) {
        const style = document.createElement('style');
        style.id = 'error-state-styles';
        style.textContent = `
            .error-state-modern {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 400px;
                padding: 3rem 1rem;
            }
            
            .error-state-content {
                text-align: center;
                max-width: 500px;
                background: white;
                border-radius: 16px;
                padding: 3rem 2rem;
                box-shadow: 0 4px 20px rgba(220, 53, 69, 0.15);
                border: 1px solid rgba(220, 53, 69, 0.1);
            }
            
            .error-state-icon-wrapper {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.2));
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
            }
            
            .error-state-icon {
                font-size: 2.5rem;
                color: #dc3545;
            }
            
            .error-state-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: #dc3545;
                margin-bottom: 1rem;
            }
            
            .error-state-description {
                font-size: 1rem;
                color: #6c757d;
                margin-bottom: 2rem;
                line-height: 1.5;
                background: #f8f9fa;
                padding: 1rem;
                border-radius: 8px;
                border-left: 4px solid #dc3545;
            }
            
            .error-state-actions {
                display: flex;
                gap: 1rem;
                justify-content: center;
                margin-bottom: 2rem;
                flex-wrap: wrap;
            }
            
            .error-state-details {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 1.5rem;
                text-align: left;
            }
            
            .error-state-details h6 {
                color: #495057;
                margin-bottom: 1rem;
                font-weight: 600;
            }
            
            .error-state-details ul {
                margin: 0;
                padding-left: 1.5rem;
                color: #6c757d;
            }
            
            .error-state-details li {
                margin-bottom: 0.5rem;
                line-height: 1.4;
            }
            
            @media (max-width: 768px) {
                .error-state-content {
                    padding: 2rem 1.5rem;
                }
                
                .error-state-actions {
                    flex-direction: column;
                    align-items: center;
                }
                
                .btn-action {
                    width: 100%;
                    max-width: 200px;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function limparFiltros() {
    document.getElementById('filterStatus').value = '';
    document.getElementById('searchInput').value = '';
    document.getElementById('filterOrdenacao').value = 'desc';
    
    statusFiltro = '';
    termoBusca = '';
    ordenacao = 'desc';
    paginaAtual = 1;
    
    carregarTodosDocumentos(true);
}

function atualizarDocumentos() {
    carregarTodosDocumentos(true);
}

// ===== FUNÇÕES DE UPLOAD E ASSINATURA =====
function configurarUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');

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
        handleFile(e.dataTransfer.files[0]);
    });

    fileInput.addEventListener('change', (e) => {
        handleFile(e.target.files[0]);
    });
}

function handleFile(file) {
    if (!file) return;

    if (file.type !== 'application/pdf') {
        notifications.show('Por favor, selecione apenas arquivos PDF', 'warning');
        return;
    }

    const maxSize = 10 * 1024 * 1024;
    if (file.size > maxSize) {
        notifications.show('Arquivo muito grande. Máximo: 10MB', 'warning');
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

function removerArquivo() {
    arquivoAssinado = null;
    document.getElementById('fileInfo').innerHTML = '';
    document.getElementById('fileInput').value = '';
}

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

function visualizarDocumento(documentoId) {
    if (!documentoId && documentoSelecionado) {
        documentoId = documentoSelecionado.id;
    }
    
    window.open(`../api/documentos/documentos_download.php?id=${documentoId}`, '_blank');
}

// ===== AÇÕES RÁPIDAS =====
function abrirRelatorios() {
    window.location.href = 'relatorios.php';
}

function verHistoricoGeral() {
    notifications.show('Funcionalidade de histórico geral em desenvolvimento', 'info');
}

function configurarAssinatura() {
    notifications.show('Funcionalidade de configurações em desenvolvimento', 'info');
}

function assinarTodos() {
    notifications.show('Funcionalidade de assinatura em lote em desenvolvimento', 'info');
}

function atualizarLista() {
    cache.clear();
    carregarTodosDocumentos(true);
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
        return new Date(data).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return 'N/A';
    }
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

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'warning',
        'signed': 'success',
        'refused': 'danger',
        'expired': 'secondary'
    };
    
    return classes[status] || 'secondary';
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

// ===== INICIALIZAÇÃO PRINCIPAL =====
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa AOS se disponível
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true
        });
    }

    // Executar health check do sistema
    const healthCheck = executarHealthCheck();

    // Inicializa dropdown do usuário de forma robusta
    initializeUserDropdown();
    setTimeout(initializeUserDropdown, 500);
    setTimeout(initializeUserDropdown, 1000);
    setTimeout(initializeUserDropdown, 2000);

    console.log('=== 🚀 PRESIDÊNCIA FRONTEND UNIFICADO v2.0 ===');
    console.log('🔐 Tem permissão:', temPermissao);
    
    // Só continuar se tiver permissão
    if (!temPermissao) {
        console.log('❌ Usuário sem permissão - não carregará funcionalidades');
        
        // Mostrar mensagem amigável mesmo sem permissão
        setTimeout(() => {
            notifications.show('Área restrita à Presidência 🔒', 'warning', 5000);
        }, 1000);
        
        return;
    }

    console.log('✅ Usuário autorizado - carregando funcionalidades unificadas...');

    // Configurar todas as funcionalidades
    configurarFiltrosZapSign();
    configurarUpload();
    configurarMetodoAssinatura();

    // Otimizar responsividade e performance
    otimizarResponsividade();
    otimizarPerformance();

    // Verificar compatibilidade
    const compatibilidade = garantirCompatibilidade();
    
    // Carregar TODOS os documentos (ZapSign + Fluxo Interno)
    console.log('📋 Iniciando carregamento de documentos...');
    carregarTodosDocumentos(true);
    
    // Aguardar um pouco antes de carregar estatísticas para não sobrecarregar
    setTimeout(() => {
        console.log('📊 Carregando estatísticas...');
        carregarEstatisticasZapSign();
    }, 1500);
    
    // Configurar refresh automático das estatísticas (a cada 5 minutos)
    setInterval(carregarEstatisticasZapSign, 5 * 60 * 1000);

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

    // Adicionar botão de refresh manual nas estatísticas se não existir
    const statsGrid = document.querySelector('.stats-grid');
    if (statsGrid && temPermissao) {
        const refreshBtn = document.createElement('button');
        refreshBtn.className = 'btn btn-sm btn-outline-secondary position-absolute';
        refreshBtn.style.cssText = 'top: 10px; right: 10px; z-index: 10; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;';
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        refreshBtn.title = 'Atualizar estatísticas e documentos';
        refreshBtn.onclick = () => {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            refreshBtn.disabled = true;
            
            Promise.all([
                forcarAtualizacaoEstatisticas(),
                carregarTodosDocumentos(true)
            ]).finally(() => {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
                refreshBtn.disabled = false;
                notifications.show('Dados atualizados! 🔄', 'success', 2000);
            });
        };
        
        statsGrid.style.position = 'relative';
        statsGrid.appendChild(refreshBtn);
    }

    // Auto-refresh dos documentos a cada 30 segundos (apenas quando em foco)
    setInterval(function() {
        if (temPermissao && document.hasFocus() && !document.querySelector('.modal.show')) {
            console.log('🔄 Auto-refresh executado');
            carregarTodosDocumentos();
        }
    }, 30000);

    // Notificação de sucesso da inicialização
    setTimeout(() => {
        const totalSistemas = 2;
        const funcionalidades = [
            'ZapSign Integration',
            'Sistema Interno',
            'Valores Base',
            'Recálculo Automático',
            'Estatísticas em Tempo Real',
            'Interface Responsiva'
        ];
        
        notifications.show(
            `Sistema da Presidência v2.0 carregado! 🎉<br>
            <small>${totalSistemas} sistemas integrados • ${funcionalidades.length} funcionalidades ativas</small>`, 
            'success', 
            4000
        );
    }, 2500);

    // Logs finais
    console.log('✅ Sistema da Presidência UNIFICADO v2.0 carregado com sucesso!');
    console.log('📋 Sistemas integrados:', {
        'ZapSign': '✅ Documentos digitais',
        'Sistema Interno': '✅ Fluxo presencial',
        'Valores Base': '✅ Gestão financeira',
        'Estatísticas': '✅ Métricas em tempo real'
    });
    console.log('🎨 Interface:', {
        'Design': 'Moderno e responsivo',
        'Compatibilidade': compatibilidade,
        'Performance': 'Otimizada',
        'Acessibilidade': 'Melhorada'
    });
    console.log('⚡ Performance:', {
        'Auto-refresh': '30s',
        'Lazy loading': 'Ativo',
        'Cache': 'Inteligente',
        'Debounce': 'Configurado'
    });
    
    console.log('🚀 Sistema pronto para uso!');
});
    </script>

</body>

</html>