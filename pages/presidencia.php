<?php
/**
 * Página da Presidência - Assinatura de Documentos
 * pages/presidencia.php
 * 
 * VERSÃO: Sistema interno de documentos apenas
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
require_once '../classes/Permissoes.php'; // Adicionar a classe de Permissões
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

// NOVA VERIFICAÇÃO DE PERMISSÃO USANDO O BANCO DE DADOS
$temPermissaoPresidencia = false;
$motivoNegacao = '';

// Debug das permissões
error_log("=== VERIFICAÇÃO DE PERMISSÃO PRESIDÊNCIA ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("ID do usuário: " . ($_SESSION['funcionario_id'] ?? 'N/A'));

// Verificar se o usuário tem a permissão PRESIDENCIA_DASHBOARD
try {
    // Usar o sistema de permissões para verificar acesso
    if (Permissoes::tem('PRESIDENCIA_DASHBOARD', 'VIEW')) {
        $temPermissaoPresidencia = true;
        error_log("✅ PERMISSÃO CONCEDIDA: Usuário tem acesso ao PRESIDENCIA_DASHBOARD");
    } else {
        $temPermissaoPresidencia = false;
        $motivoNegacao = 'Você não possui permissão para acessar a área da Presidência.';
        error_log("❌ PERMISSÃO NEGADA: Usuário não tem acesso ao PRESIDENCIA_DASHBOARD");
    }
    
    // Verificação adicional: Super Admin sempre tem acesso
    $permissoesInstance = Permissoes::getInstance();
    if ($permissoesInstance->isSuperAdmin()) {
        $temPermissaoPresidencia = true;
        error_log("✅ PERMISSÃO CONCEDIDA: Usuário é Super Admin");
    }
    
} catch (Exception $e) {
    error_log("❌ ERRO ao verificar permissão: " . $e->getMessage());
    $temPermissaoPresidencia = false;
    $motivoNegacao = 'Erro ao verificar permissões. Entre em contato com o administrador.';
}

// Log final do resultado
if (!$temPermissaoPresidencia) {
    error_log("❌ ACESSO FINAL NEGADO: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO FINAL PERMITIDO - Usuário autorizado para Presidência");
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

// Cria instância do Header Component - passa TODO o array do usuário
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* Modal de Edição de Valores */
        #modalEditarValoresBase .card {
            transition: all 0.3s ease;
        }

        #modalEditarValoresBase .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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

        .dual-stat-card {
            position: relative;
            overflow: visible;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            min-width: 320px;
            width: 100%;
        }

        .dual-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .dual-stat-card:hover::before {
            transform: scaleX(1);
        }

        .dual-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(0, 86, 210, 0.2);
        }

        /* Header do Card */
        .dual-stat-header {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dual-stat-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .dual-stat-percentage {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        /* Layout Desktop - Vertical */
        .dual-stats-row {
            display: flex;
            align-items: stretch;
            padding: 0;
            min-height: 120px;
            width: 100%;
        }

        .dual-stat-item {
            flex: 1;
            min-width: 0;
            padding: 1.5rem 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1rem;
            transition: all 0.3s ease;
            position: relative;
            width: 50%;
        }

        .dual-stat-item:hover {
            background: rgba(0, 86, 210, 0.02);
        }

        .dual-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .dual-stat-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            text-align: center;
            align-items: center;
        }

        .dual-stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
        }

        .dual-stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 600;
            line-height: 1;
        }

        /* Separador vertical */
        .dual-stats-separator {
            width: 1px;
            background: linear-gradient(to bottom, transparent, var(--gray-300), transparent);
            margin: 1.5rem 0;
            flex-shrink: 0;
        }

        /* Cores específicas dos ícones */
        .pendentes-icon {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        .assinados-icon {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .hoje-icon {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
            color: white;
        }

        .mes-icon {
            background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
            color: white;
        }

        .tempo-icon {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            color: white;
        }

        .velocidade-icon {
            background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
            color: white;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            .dual-stats-row {
                flex-direction: column;
                min-height: auto;
            }

            .dual-stats-separator {
                width: 80%;
                height: 1px;
                margin: 0.75rem auto;
                background: linear-gradient(to right, transparent, var(--gray-300), transparent);
            }

            .dual-stat-item {
                padding: 1.25rem;
                width: 100%;
                min-width: 0;
                flex-direction: row !important;
                align-items: center !important;
                text-align: left !important;
                gap: 1rem !important;
                justify-content: flex-start !important;
            }

            .dual-stat-info {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                text-align: left !important;
            }

            .dual-stat-value {
                font-size: 1.75rem;
            }

            .dual-stat-icon {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
                flex-shrink: 0;
            }
        }

        /* === MODAL VALORES BASE - DESIGN MODERNO === */

        #modalEditarValoresBase .modal-dialog {
            max-width: 1000px;
            margin: 2rem auto;
        }

        #modalEditarValoresBase .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        #modalEditarValoresBase .modal-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        #modalEditarValoresBase .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        #modalEditarValoresBase .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }

        #modalEditarValoresBase .modal-title i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        #modalEditarValoresBase .modal-body {
            padding: 2.5rem;
            background: white;
        }

        #modalEditarValoresBase .alert {
            border-radius: 12px;
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, rgba(13, 202, 240, 0.1) 0%, rgba(13, 202, 240, 0.05) 100%);
            border-left: 4px solid #0dcaf0;
        }

        #modalEditarValoresBase .alert i {
            font-size: 1.2rem;
            margin-right: 0.75rem;
            color: #0dcaf0;
        }

        /* Cards dos Serviços */
        #modalEditarValoresBase .card {
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
        }

        #modalEditarValoresBase .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        #modalEditarValoresBase .card-header {
            background: linear-gradient(135deg, var(--color-start) 0%, var(--color-end) 100%);
            color: white;
            border: none;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        #modalEditarValoresBase .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            transform: translate(20px, -20px);
        }

        #modalEditarValoresBase .border-success .card-header {
            --color-start: #28a745;
            --color-end: #20c997;
        }

        #modalEditarValoresBase .border-warning .card-header {
            --color-start: #ffc107;
            --color-end: #fd7e14;
            color: #212529 !important;
        }

        #modalEditarValoresBase .card-header h6 {
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        #modalEditarValoresBase .card-header i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: 8px;
            backdrop-filter: blur(5px);
        }

        #modalEditarValoresBase .card-body {
            padding: 2rem;
            background: white;
        }

        /* Inputs modernos */
        #modalEditarValoresBase .input-group {
            margin-bottom: 1.5rem;
        }

        #modalEditarValoresBase .input-group-text {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #e9ecef;
            border-right: none;
            font-weight: 700;
            color: #495057;
            border-radius: 12px 0 0 12px;
            padding: 0.75rem 1rem;
        }

        #modalEditarValoresBase .form-control {
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 12px 12px 0;
            padding: 0.75rem 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #modalEditarValoresBase .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
            transform: translateY(-1px);
        }

        /* Área de impacto */
        #modalEditarValoresBase .bg-light {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%) !important;
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            margin-top: 1rem;
        }

        #modalEditarValoresBase .bg-light h6 {
            color: #495057;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #modalEditarValoresBase .bg-light .d-flex {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 0.5rem;
        }

        #modalEditarValoresBase .bg-light .d-flex:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        #modalEditarValoresBase .fw-bold {
            font-weight: 700 !important;
            color: #007bff;
        }

        /* Card de Resumo */
        #modalEditarValoresBase .border-info .card-header {
            --color-start: #17a2b8;
            --color-end: #007bff;
        }

        #modalEditarValoresBase .text-center .row>div {
            padding: 1rem;
            border-radius: 12px;
            margin: 0.25rem;
            background: rgba(0, 123, 255, 0.03);
            transition: all 0.3s ease;
        }

        #modalEditarValoresBase .text-center .row>div:hover {
            background: rgba(0, 123, 255, 0.08);
            transform: translateY(-2px);
        }

        #modalEditarValoresBase .text-info {
            color: #007bff !important;
            font-size: 1.8rem;
            font-weight: 800;
        }

        #modalEditarValoresBase .text-success {
            color: #28a745 !important;
            font-size: 1.8rem;
            font-weight: 800;
        }

        #modalEditarValoresBase .text-primary {
            color: #007bff !important;
            font-size: 1.8rem;
            font-weight: 800;
        }

        /* Footer do Modal */
        #modalEditarValoresBase .modal-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: none;
            padding: 2rem;
            gap: 1rem;
        }

        #modalEditarValoresBase .modal-footer .btn {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            position: relative;
            overflow: hidden;
        }

        #modalEditarValoresBase .modal-footer .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        #modalEditarValoresBase .modal-footer .btn:hover::before {
            left: 100%;
        }

        #modalEditarValoresBase .modal-footer .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        #modalEditarValoresBase .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }

        #modalEditarValoresBase .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }

        /* Animações */
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #modalEditarValoresBase .modal-content {
            animation: modalSlideIn 0.4s ease-out;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            #modalEditarValoresBase .modal-dialog {
                margin: 1rem;
                max-width: calc(100vw - 2rem);
            }

            #modalEditarValoresBase .modal-header,
            #modalEditarValoresBase .modal-body,
            #modalEditarValoresBase .modal-footer {
                padding: 1.5rem;
            }

            #modalEditarValoresBase .modal-title {
                font-size: 1.25rem;
            }

            #modalEditarValoresBase .row {
                margin: 0;
            }

            #modalEditarValoresBase .row>div {
                padding: 0.5rem;
                margin-bottom: 1rem;
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
                                <li><strong>Cargo:</strong>
                                    <?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'N/A'); ?></li>
                                <li><strong>Departamento ID:</strong>
                                    <?php echo htmlspecialchars($usuarioLogado['departamento_id'] ?? 'N/A'); ?></li>
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
                                <div class="page">
                                    <i class="fas"></i>
                                </div>
                                Área da Presidência
                            </h1>
                            <p class="page-subtitle">Gerencie e assine documentos de filiação dos associados</p>
                        </div>

                        <!-- BOTÃO DE FUNCIONÁRIOS - PARA USUÁRIOS DA PRESIDÊNCIA -->
                        <!-- 
                        <?php if ($temPermissaoPresidencia): ?>
                            <div class="header-actions">
                                <a href="funcionarios.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-users me-2"></i> Funcionários
                                </a>
                            </div>
                        <?php endif; ?>
                        -->
                    </div>
                </div>

                <!-- Stats Grid com Gráficos de Pizza - Presidência -->
                <div class="stats-grid" data-aos="fade-up">
                    <!-- Card 1: Documentos Pendentes + Assinados -->
                    <div class="stat-card dual-stat-card documentos-pie">
                        <div class="dual-stat-header">
                            <div class="dual-stat-title">
                                <i class="fas fa-file-signature"></i>
                                Documentos
                            </div>
                            <div class="dual-stat-percentage" id="documentosPercent">
                                <i class="fas fa-chart-line"></i>
                                Status
                            </div>
                        </div>
                        <div class="dual-stats-row vertical-layout">
                            <div class="dual-stat-item pendentes-item">
                                <div class="dual-stat-icon pendentes-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="dual-stat-info">
                                    <div class="dual-stat-value"><?php echo $aguardandoAssinatura; ?></div>
                                    <div class="dual-stat-label">Pendentes</div>
                                </div>
                            </div>
                            <div class="dual-stats-separator"></div>
                            <div class="dual-stat-item assinados-item">
                                <div class="dual-stat-icon assinados-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="dual-stat-info">
                                    <div class="dual-stat-value"><?php echo $assinadosMes; ?></div>
                                    <div class="dual-stat-label">Assinados</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Performance Diária + Mensal -->
                    <div class="stat-card dual-stat-card performance-pie">
                        <div class="dual-stat-header">
                            <div class="dual-stat-title">
                                <i class="fas fa-chart-line"></i>
                                Performance
                            </div>
                            <div class="dual-stat-percentage" id="performancePercent">
                                <i class="fas fa-trending-up"></i>
                                Produtividade
                            </div>
                        </div>
                        <div class="dual-stats-row vertical-layout">
                            <div class="dual-stat-item hoje-item">
                                <div class="dual-stat-icon hoje-icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div class="dual-stat-info">
                                    <div class="dual-stat-value"><?php echo $assinadosHoje; ?></div>
                                    <div class="dual-stat-label">Hoje</div>
                                </div>
                            </div>
                            <div class="dual-stats-separator"></div>
                            <div class="dual-stat-item mes-item">
                                <div class="dual-stat-icon mes-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="dual-stat-info">
                                    <div class="dual-stat-value"><?php echo $assinadosMes; ?></div>
                                    <div class="dual-stat-label">Este Mês</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Eficiência + Tempo Médio -->
                    <div class="stat-card dual-stat-card eficiencia-pie">
                        <div class="dual-stat-header">
                            <div class="dual-stat-title">
                                <i class="fas fa-stopwatch"></i>
                                Eficiência
                            </div>
                            <div class="dual-stat-percentage" id="eficienciaPercent">
                                <i class="fas fa-tachometer-alt"></i>
                                Tempo
                            </div>
                        </div>
                        <div class="dual-stats-row vertical-layout">
                            <div class="dual-stat-item tempo-item">
                                <div class="dual-stat-icon tempo-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="dual-stat-info">
                                    <div class="dual-stat-value"><?php echo $tempoMedio; ?>h</div>
                                    <div class="dual-stat-label">Tempo Médio</div>
                                </div>
                            </div>
                            <div class="dual-stats-separator"></div>
                            <div class="dual-stat-item velocidade-item">
                                <div class="dual-stat-icon velocidade-icon">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <div class="dual-stat-info">
                                    <div class="dual-stat-value">
                                        <?php echo $aguardandoAssinatura > 0 ? round($assinadosHoje / max($aguardandoAssinatura, 1), 1) : 0; ?>x
                                    </div>
                                    <div class="dual-stat-label">Velocidade</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions" data-aos="fade-up" data-aos-delay="100">
                    <h3 class="quick-actions-title">Ações Rápidas</h3>
                    <div class="quick-actions-grid">
                        <button class="btn-modern btn-warning" onclick="abrirModalEditarValores()"
                            id="btnEditarValoresBase">
                            <i class="fas fa-calculator"></i>
                            Editar Valores Base dos Serviços
                        </button>

                        <button class="quick-action-btn" onclick="abrirRelatorios()">
                            <i class="fas fa-chart-line quick-action-icon"></i>
                            Relatórios
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
    <div class="modal fade" id="assinaturaModal" tabindex="-1" aria-labelledby="assinaturaModalLabel"
        aria-hidden="true">
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
    <div class="modal fade" id="historicoGeralModal" tabindex="-1" aria-labelledby="historicoGeralModalLabel"
        aria-hidden="true">
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
                                <option value="<?php echo $_SESSION['funcionario_id'] ?? ''; ?>">Minhas assinaturas
                                </option>
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
    <div class="modal fade" id="modalEditarValoresBase" tabindex="-1" role="dialog"
        aria-labelledby="modalEditarValoresBaseLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalEditarValoresBaseLabel">
                        <i class="fas fa-calculator"></i>
                        Editar Valores Base dos Serviços
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Atenção:</strong> Alterar os valores base irá recalcular automaticamente todos os
                        valores dos associados baseado nos percentuais do tipo de cada um.
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
                                                <input type="number" class="form-control" id="valorBaseSocial"
                                                    name="valorBaseSocial" step="0.01" min="0" placeholder="0,00"
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
                                                <input type="number" class="form-control" id="valorBaseJuridico"
                                                    name="valorBaseJuridico" step="0.01" min="0" placeholder="0,00"
                                                    required>
                                            </div>
                                            <small class="form-text text-muted">
                                                Valor aplicado apenas aos associados que aderiram ao serviço jurídico
                                            </small>
                                        </div>

                                        <div class="bg-light p-3 rounded">
                                            <h6 class="text-warning mb-2">
                                                <i class="fas fa-balance-scale"></i>
                                                Impacto do Serviço:
                                            </h6>
                                            <div id="impactoJuridico">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span>Valor para quem aderir:</span>
                                                    <span id="impactoJuridicoContribuinte" class="fw-bold">R$
                                                        0,00</span>
                                                </div>
                                                <div class="alert alert-warning mb-0 py-2">
                                                    <small>
                                                        <i class="fas fa-info-circle"></i>
                                                        <strong>Serviço opcional:</strong> Associado paga o valor
                                                        integral ou não adere ao serviço
                                                    </small>
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
                                                    <small class="text-muted">Diferença Total (+ Aumento | -
                                                        Redução)</small>
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
                    <button type="button" class="btn btn-primary" onclick="confirmarAlteracaoValores()"
                        id="btnConfirmarAlteracao">
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

        // Instanciar sistemas
        const notifications = new NotificationSystem();
        const cache = new SimpleCache();

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

        // ===== CARREGAMENTO DE DOCUMENTOS DO FLUXO INTERNO =====
        async function carregarDocumentosFluxo(resetarPagina = false) {
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
                console.log('🔄 Carregando documentos do fluxo interno...');

                // Montar filtros para o fluxo interno
                const filtros = {};

                const statusFiltroAtual = document.getElementById('filtroStatusFluxo')?.value || '';
                const termoBuscaAtual = document.getElementById('filtroBuscaFluxo')?.value || '';
                const tipoFluxo = document.getElementById('filtroTipoFluxo')?.value || '';
                const periodo = document.getElementById('filtroPeriodo')?.value || '';

                if (statusFiltroAtual) {
                    filtros.status = statusFiltroAtual;
                }

                if (termoBuscaAtual) {
                    filtros.busca = termoBuscaAtual;
                }

                if (tipoFluxo) {
                    filtros.tipo_origem = tipoFluxo;
                }

                if (periodo) {
                    filtros.periodo = periodo;
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
                    documentosFluxo = data.data || [];
                    console.log('✅ Documentos do fluxo interno carregados:', documentosFluxo.length);

                    renderizarDocumentosFluxo();

                    // notifications.show(`${documentosFluxo.length} documento(s) carregado(s) do sistema interno`, 'success', 3000);
                } else {
                    throw new Error(data.message || 'Erro ao carregar documentos do fluxo interno');
                }

            } catch (error) {
                console.error('❌ Erro ao carregar documentos:', error);
                mostrarErroCarregamento(error.message);
                notifications.show('Erro ao carregar documentos: ' + error.message, 'error');
            } finally {
                carregandoDocumentos = false;
            }
        }

        // ===== RENDERIZAÇÃO DOS DOCUMENTOS DO FLUXO INTERNO =====
        function renderizarDocumentosFluxo() {
            const container = document.getElementById('documentsList');
            container.innerHTML = '';

            // Adicionar CSS moderno se não existir
            adicionarEstilosModernos();

            console.log('📋 Renderizando documentos do fluxo interno:', documentosFluxo.length);

            if (documentosFluxo.length === 0) {
                mostrarEstadoVazio();
                return;
            }

            // Ordenar por data (mais recentes primeiro)
            documentosFluxo.sort((a, b) => {
                const dataA = new Date(a.data_upload || 0);
                const dataB = new Date(b.data_upload || 0);
                return dataB - dataA;
            });

            // Renderizar cada documento
            documentosFluxo.forEach(doc => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'document-item-modern document-fluxo-interno';
                itemDiv.dataset.docId = doc.id;

                renderizarDocumentoFluxoInterno(itemDiv, doc);

                container.appendChild(itemDiv);
            });
        }

        // ===== RENDERIZAÇÃO ESPECÍFICA PARA FLUXO INTERNO =====
        function renderizarDocumentoFluxoInterno(container, doc) {
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
                        <span class="badge badge-sistema bg-success">
                            <i class="fas fa-building"></i> Sistema Interno
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
        function getStatusIconFluxo(status) {
            const icons = {
                'DIGITALIZADO': '<i class="fas fa-upload"></i>',
                'AGUARDANDO_ASSINATURA': '<i class="fas fa-clock"></i>',
                'ASSINADO': '<i class="fas fa-check"></i>',
                'FINALIZADO': '<i class="fas fa-flag-checkered"></i>'
            };

            return icons[status] || '<i class="fas fa-file"></i>';
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
        .document-item-modern.document-fluxo-interno {
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
        .badge-sistema.bg-success {
            background: linear-gradient(135deg, #28a745, #1e7e34) !important;
        }
    `;

            document.head.appendChild(style);
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
            const documento = documentosFluxo.find(doc => doc.id === documentoId);

            if (!documento) {
                notifications.show('Documento não encontrado', 'error');
                return;
            }

            // Criar o modal dinamicamente
            const modalHTML = `
        <div class="modal fade" id="modalFinalizarProcesso" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                    <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #28a745, #20c997);">
                        <div class="d-flex align-items-center">
                            <div class="modal-icon me-3" style="width: 60px; height: 60px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
                                <i class="fas fa-flag-checkered fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="modal-title mb-0 fw-bold">Finalizar Processo</h5>
                                <small style="opacity: 0.75;">Última etapa do documento</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body p-4">
                        <div class="text-center mb-4">
                            <div class="alert border-0" style="background-color: rgba(13, 202, 240, 0.1);">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-info-circle text-info me-3 mt-1"></i>
                                    <div class="flex-grow-1 text-start">
                                        <h6 class="mb-2 text-dark">O que acontecerá:</h6>
                                        <ul class="mb-0 text-muted small">
                                            <li>O documento retornará para o departamento comercial</li>
                                            <li>O pré-cadastro poderá ser aprovado automaticamente</li>
                                            <li>O processo de filiação será concluído</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-light p-3 rounded mb-3" style="border-left: 4px solid #007bff;">
                            <h6 class="text-dark mb-2">
                                <i class="fas fa-file-alt text-primary me-2"></i>
                                Informações do Documento
                            </h6>
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <strong>Associado:</strong><br>
                                    <span class="text-muted">${documento.associado_nome || 'N/A'}</span>
                                </div>
                                <div class="col-6">
                                    <strong>CPF:</strong><br>
                                    <span class="text-muted">${formatarCPF(documento.associado_cpf) || 'N/A'}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-comment-dots me-2"></i>
                                Observação (opcional)
                            </label>
                            <textarea class="form-control border-2" id="observacaoFinalizacao" rows="3" 
                                      placeholder="Adicione uma observação sobre a finalização..." 
                                      style="border-radius: 8px;"></textarea>
                        </div>

                        <div class="alert border-0" style="background-color: rgba(255, 193, 7, 0.1);">
                            <div class="d-flex">
                                <i class="fas fa-exclamation-triangle text-warning me-3"></i>
                                <div>
                                    <strong>Atenção:</strong> Esta ação não pode ser desfeita. 
                                    Certifique-se de que o documento foi devidamente revisado.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 p-4">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" 
                                style="border-radius: 8px; padding: 10px 20px; font-weight: 500;">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-success px-4 fw-bold" id="btnConfirmarFinalizacao"
                                style="border-radius: 8px; padding: 10px 20px; font-weight: 500; transition: all 0.3s ease;">
                            <i class="fas fa-check me-2"></i>Finalizar Processo
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

            // Adicionar CSS se não existir
            if (!document.getElementById('modal-finalizar-styles')) {
                const styleSheet = document.createElement('style');
                styleSheet.id = 'modal-finalizar-styles';
                styleSheet.textContent = `
            #modalFinalizarProcesso .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            #modalFinalizarProcesso .form-control:focus {
                border-color: #28a745;
                box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
            }
        `;
                document.head.appendChild(styleSheet);
            }

            // Remover modal anterior se existir
            const modalExistente = document.getElementById('modalFinalizarProcesso');
            if (modalExistente) {
                modalExistente.remove();
            }

            // Adicionar modal ao DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);

            // Configurar evento do botão de confirmação
            document.getElementById('btnConfirmarFinalizacao').addEventListener('click', function () {
                const observacao = document.getElementById('observacaoFinalizacao').value.trim();
                const btnConfirmar = this;
                const textoOriginal = btnConfirmar.innerHTML;

                // Loading state
                btnConfirmar.disabled = true;
                btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Finalizando...';

                // Fazer requisição
                fetch('../api/documentos/documentos_finalizar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        documento_id: documentoId,
                        observacao: observacao || 'Processo finalizado - Documento pronto para aprovação do pré-cadastro'
                    })
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success') {
                            // Fechar e remover modal
                            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalFinalizarProcesso'));
                            if (modalInstance) {
                                modalInstance.hide();
                            }

                            // Remover modal do DOM após fechar
                            setTimeout(() => {
                                document.getElementById('modalFinalizarProcesso')?.remove();
                            }, 300);

                            notifications.show('Processo finalizado com sucesso! O pré-cadastro já pode ser aprovado.', 'success');
                            carregarDocumentosFluxo();
                        } else {
                            notifications.show('Erro: ' + result.message, 'error');
                            btnConfirmar.disabled = false;
                            btnConfirmar.innerHTML = textoOriginal;
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao finalizar processo:', error);
                        notifications.show('Erro ao finalizar processo', 'error');
                        btnConfirmar.disabled = false;
                        btnConfirmar.innerHTML = textoOriginal;
                    });
            });

            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('modalFinalizarProcesso'));
            modal.show();

            // Limpar modal do DOM quando fechado
            document.getElementById('modalFinalizarProcesso').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
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

                            carregarDocumentosFluxo();
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
            // Para serviço jurídico - ou paga integral ou não paga
            document.getElementById('impactoJuridicoContribuinte').textContent =
                'R$ ' + valorJuridico.toFixed(2).replace('.', ',');
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

        // ===== SISTEMA DE FILTROS =====
        function configurarFiltros() {
            const filtroStatus = document.getElementById('filtroStatusFluxo');
            const filtroBusca = document.getElementById('filtroBuscaFluxo');
            const filtroTipo = document.getElementById('filtroTipoFluxo');
            const filtroPeriodo = document.getElementById('filtroPeriodo');

            if (filtroStatus) {
                filtroStatus.addEventListener('change', () => {
                    carregarDocumentosFluxo(true);
                });
            }

            if (filtroBusca) {
                filtroBusca.addEventListener('input', debounce(() => {
                    carregarDocumentosFluxo(true);
                }, 500));
            }

            if (filtroTipo) {
                filtroTipo.addEventListener('change', () => {
                    carregarDocumentosFluxo(true);
                });
            }

            if (filtroPeriodo) {
                filtroPeriodo.addEventListener('change', () => {
                    carregarDocumentosFluxo(true);
                });
            }
        }

        function aplicarFiltros() {
            carregarDocumentosFluxo(true);
        }

        function limparFiltros() {
            document.getElementById('filtroStatusFluxo').value = '';
            document.getElementById('filtroTipoFluxo').value = '';
            document.getElementById('filtroBuscaFluxo').value = '';
            document.getElementById('filtroPeriodo').value = '';

            carregarDocumentosFluxo(true);
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

            const statusFiltroAtual = document.getElementById('filtroStatusFluxo')?.value || '';
            const termoBuscaAtual = document.getElementById('filtroBuscaFluxo')?.value || '';

            if (statusFiltroAtual) {
                mensagem = `Nenhum documento encontrado com o filtro aplicado`;
                icone = 'fas fa-filter';
                descricao = 'Tente ajustar os filtros ou fazer uma nova busca.';
            }

            if (termoBuscaAtual) {
                mensagem += ` para "${termoBuscaAtual}"`;
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
                
                ${statusFiltroAtual || termoBuscaAtual ? `
                <div class="empty-state-actions">
                    <button class="btn-action primary" onclick="limparFiltros()">
                        <i class="fas fa-times"></i>
                        Limpar Filtros
                    </button>
                    <button class="btn-action secondary" onclick="carregarDocumentosFluxo(true)">
                        <i class="fas fa-refresh"></i>
                        Atualizar
                    </button>
                </div>
                ` : `
                <div class="empty-state-actions">
                    <button class="btn-action primary" onclick="carregarDocumentosFluxo(true)">
                        <i class="fas fa-refresh"></i>
                        Atualizar Lista
                    </button>
                </div>
                `}
                
                <div class="empty-state-tips">
                    <h6>💡 Dicas:</h6>
                    <ul>
                        <li><strong>Sistema Interno:</strong> Fichas de filiação do fluxo presencial e virtual</li>
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
                    <button class="btn-action primary" onclick="carregarDocumentosFluxo(true)">
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

        function atualizarLista() {
            cache.clear();
            carregarDocumentosFluxo(true);
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
                radio.addEventListener('change', function () {
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

        function carregarHistoricoGeral() {
            notifications.show('Carregando histórico geral...', 'info', 2000);

            const periodo = document.getElementById('filtroPeriodoHistorico').value;
            const funcionario = document.getElementById('filtroFuncionarioHistorico').value;

            const params = new URLSearchParams();
            params.append('periodo_dias', periodo);
            if (funcionario) {
                params.append('funcionario_id', funcionario);
            }

            fetch('../api/documentos/historico_assinaturas_presidencia.php?' + params)
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        renderizarHistoricoGeral(result.data);
                    } else {
                        notifications.show('Erro ao carregar histórico: ' + result.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar histórico geral:', error);
                    notifications.show('Erro ao carregar histórico', 'error');
                });
        }

        function renderizarHistoricoGeral(dados) {
            const container = document.getElementById('timelineHistoricoGeral');
            const resumoContainer = document.getElementById('resumoHistoricoGeral');

            // Limpar containers
            container.innerHTML = '';
            resumoContainer.innerHTML = '';

            // Renderizar timeline
            if (dados.historico && dados.historico.length > 0) {
                dados.historico.forEach(item => {
                    const timelineItem = document.createElement('div');
                    timelineItem.className = 'timeline-item';

                    timelineItem.innerHTML = `
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${item.acao}</h6>
                            <p class="mb-2">${item.associado_nome} - ${formatarCPF(item.associado_cpf)}</p>
                            <small class="text-muted">
                                Por: ${item.funcionario_nome}<br>
                                ${item.observacao ? item.observacao : 'Sem observações'}
                            </small>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">${formatarData(item.data_acao)}</small>
                        </div>
                    </div>
                </div>
            `;

                    container.appendChild(timelineItem);
                });
            } else {
                container.innerHTML = '<p class="text-muted text-center">Nenhum histórico encontrado para o período selecionado</p>';
            }

            // Renderizar resumo
            if (dados.resumo) {
                resumoContainer.innerHTML = `
            <div class="col-md-3">
                <h5 class="text-primary">${dados.resumo.total_assinaturas || 0}</h5>
                <small class="text-muted">Total de Assinaturas</small>
            </div>
            <div class="col-md-3">
                <h5 class="text-success">${dados.resumo.documentos_assinados || 0}</h5>
                <small class="text-muted">Documentos Assinados</small>
            </div>
            <div class="col-md-3">
                <h5 class="text-info">${dados.resumo.documentos_finalizados || 0}</h5>
                <small class="text-muted">Documentos Finalizados</small>
            </div>
            <div class="col-md-3">
                <h5 class="text-warning">${dados.resumo.tempo_medio || 0}h</h5>
                <small class="text-muted">Tempo Médio</small>
            </div>
        `;
            }
        }

        function imprimirHistoricoGeral() {
            window.print();
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
                    $.fn.modal = function (action) {
                        return this.each(function () {
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
                    fluxoInterno: false
                },
                bibliotecas: garantirCompatibilidade(),
                elementos: {
                    filtros: !!document.getElementById('filtroStatusFluxo'),
                    busca: !!document.getElementById('filtroBuscaFluxo'),
                    modais: !!document.getElementById('assinaturaModal')
                }
            };

            // Teste rápido da API (sem fazer requisições completas)
            const testarAPIs = async () => {
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
        document.addEventListener('DOMContentLoaded', function () {
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

            console.log('=== 🚀 PRESIDÊNCIA FRONTEND SISTEMA INTERNO v2.0 ===');
            console.log('🔐 Tem permissão:', temPermissao);

            // Só continuar se tiver permissão
            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');

                // Mostrar mensagem amigável mesmo sem permissão
                setTimeout(() => {
                    //notifications.show('Área restrita à Presidência 🔒', 'warning', 5000);
                }, 1000);

                return;
            }

            console.log('✅ Usuário autorizado - carregando funcionalidades do sistema interno...');

            // Configurar todas as funcionalidades
            configurarFiltros();
            configurarUpload();
            configurarMetodoAssinatura();

            // Otimizar responsividade
            otimizarResponsividade();

            // Verificar compatibilidade
            const compatibilidade = garantirCompatibilidade();

            // Carregar documentos do fluxo interno
            console.log('📋 Iniciando carregamento de documentos do sistema interno...');
            carregarDocumentosFluxo(true);

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
            

            // Auto-refresh dos documentos a cada 30 segundos (apenas quando em foco)
            setInterval(function () {
                if (temPermissao && document.hasFocus() && !document.querySelector('.modal.show')) {
                    console.log('🔄 Auto-refresh executado');
                    carregarDocumentosFluxo();
                }
            }, 30000);

            // Notificação de sucesso da inicialização
            // setTimeout(() => {
            //     const funcionalidades = [
            //         'Sistema Interno',
            //         'Valores Base',
            //         'Recálculo Automático',
            //         'Interface Responsiva'
            //     ];
            //     
            //     notifications.show(
            //         `Sistema da Presidência v2.0 carregado! 🎉<br>
            //         <small>Sistema interno • ${funcionalidades.length} funcionalidades ativas</small>`, 
            //         'success', 
            //         4000
            //     );
            // }, 2500);

            // Logs finais
            console.log('✅ Sistema da Presidência INTERNO v2.0 carregado com sucesso!');
            console.log('📋 Sistemas integrados:', {
                'Sistema Interno': '✅ Fluxo presencial e virtual',
                'Valores Base': '✅ Gestão financeira'
            });
            console.log('🎨 Interface:', {
                'Design': 'Moderno e responsivo',
                'Compatibilidade': compatibilidade,
                'Performance': 'Otimizada'
            });
            console.log('⚡ Performance:', {
                'Auto-refresh': '30s',
                'Cache': 'Inteligente',
                'Debounce': 'Configurado'
            });

            console.log('🚀 Sistema pronto para uso!');
        });
    </script>

</body>

</html>