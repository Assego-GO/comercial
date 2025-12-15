<?php
/**
 * Página de Gerenciamento de Cadastros Online - Sistema ASSEGO
 * pages/cadastros_online.php
 * 
 * LOCALIZAÇÃO: Sistema 172 (INTERNO)
 * VERSÃO ATUALIZADA - Com todas as abas incluindo Pré-cadastros
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
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
$page_title = 'Cadastros Online - ASSEGO';

// ✅ BUSCAR PRÉ-CADASTROS DO SISTEMA (Lógica antiga)
$preCadastros = [];
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.cpf,
            a.telefone,
            a.email,
            a.data_pre_cadastro,
            a.situacao,
            a.pre_cadastro,
            m.corporacao,
            m.patente,
            m.lotacao,
            fpc.status as status_fluxo,
            fpc.data_envio_presidencia,
            DATEDIFF(NOW(), a.data_pre_cadastro) as dias_pre_cadastro
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Fluxo_Pre_Cadastro fpc ON a.id = fpc.associado_id
        WHERE a.pre_cadastro = 1
            AND a.nome IS NOT NULL
            AND a.nome != ''
            AND a.cpf IS NOT NULL
            AND a.cpf != ''
            AND a.associado_titular_id IS NULL
        ORDER BY a.data_pre_cadastro DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $preCadastros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro ao buscar pré-cadastros: " . $e->getMessage());
    $preCadastros = [];
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
    'notificationCount' => 0,
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <style>
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #003d94;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--gray-100);
            min-height: 100vh;
            color: var(--dark);
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 2rem;
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin-top: 1rem;
            text-align: center;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            background: var(--white);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--info));
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
            color: white;
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
        }

        .stats-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-back, .btn-refresh {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            border: none;
            cursor: pointer;
        }

        .btn-back {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(100, 116, 139, 0.3);
            color: white;
        }

        .btn-refresh {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 86, 210, 0.3);
        }

        /* Alert Banner */
        .alert-banner {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-banner i {
            font-size: 1.5rem;
            color: #856404;
        }

        .alert-banner-text {
            flex: 1;
            color: #856404;
            font-weight: 500;
        }

        /* Abas de Navegação */
        .nav-tabs-custom {
            border-bottom: 2px solid var(--gray-200);
            margin-bottom: 2rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .nav-tab-custom {
            padding: 1rem 2rem;
            border: none;
            background: transparent;
            color: var(--gray-600);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-tab-custom:hover {
            color: var(--primary);
            background: rgba(0, 86, 210, 0.05);
        }

        .nav-tab-custom.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(0, 86, 210, 0.08);
        }

        .tab-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .tab-content-area {
            display: none;
        }

        .tab-content-area.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        /* Table Container */
        .table-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-title i {
            color: var(--primary);
        }

        /* Custom Table Styles */
        .table-responsive {
            padding: 1.5rem;
        }

        .table thead th {
            background: var(--gray-100);
            border-bottom: 2px solid var(--gray-200);
            font-weight: 600;
            color: var(--dark);
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(0, 86, 210, 0.05);
            transform: scale(1.01);
        }

        /* Foto Preview */
        .foto-preview {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .foto-preview:hover {
            transform: scale(1.1);
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.3);
        }

        .sem-foto {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
        }

        /* Modal Foto */
        .modal-foto .modal-body {
            padding: 0;
            text-align: center;
            background: #000;
        }

        .modal-foto .modal-body img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }

        .modal-foto .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
            color: white;
            border: none;
        }

        .modal-foto .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
        }

        .status-pendente {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .status-importado {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 1px solid #22c55e;
        }

        .status-optante {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .badge-foto {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 1px solid #22c55e;
        }

        /* Badges Militares */
        .badge-pm {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .badge-bm {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .badge-patente {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: #3730a3;
            border: 1px solid #6366f1;
        }

        /* ✅ NOVO: Badges de Status de Fluxo */
        .badge-aguardando {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .badge-enviado {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .badge-aprovado {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 1px solid #22c55e;
        }

        .badge-rejeitado {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* ✅ NOVO: Ajustes específicos para tabela de pré-cadastros */
        #tablePreCadastros td {
            white-space: nowrap;
            vertical-align: middle;
        }

        #tablePreCadastros .fw-semibold {
            white-space: normal;
            word-break: break-word;
        }

        #tablePreCadastros code {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            background: var(--gray-100);
            border-radius: 4px;
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
        }

        .btn-import {
            background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(40, 167, 69, 0.3);
        }

        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            color: white;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--info) 0%, #138496 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(23, 162, 184, 0.3);
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
            color: white;
        }

        .btn-complete {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            color: #000;
            box-shadow: 0 4px 14px rgba(255, 193, 7, 0.3);
        }

        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
            color: #000;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: var(--dark);
            margin-bottom: 1rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .content-area { padding: 1rem; }
            .page-title { font-size: 1.5rem; }
            .stats-summary { flex-direction: column; text-align: center; }
            .action-bar { flex-direction: column; }
            .nav-tabs-custom { flex-direction: column; }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <div class="loading-text">Carregando dados...</div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 99999;"></div>

    <!-- Modal de Visualização de Foto -->
    <div class="modal fade modal-foto" id="modalFoto" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-image me-2"></i>
                        <span id="modalFotoTitulo">Foto do Associado</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <img id="modalFotoImg" src="" alt="Foto">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Action Bar -->
            <div class="action-bar">
                <a href="../pages/comercial.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Voltar ao Comercial
                </a>

                <button class="btn-refresh" onclick="recarregarDados()">
                    <i class="fas fa-sync-alt"></i>
                    Atualizar
                </button>
            </div>

            <!-- Alert Banner -->
            <div class="alert-banner">
                <i class="fas fa-info-circle"></i>
                <div class="alert-banner-text">
                    <strong>Como funciona:</strong> Cadastros do site público aparecem em "Pendentes". 
                    Após importar, vão para "Importados". Os já importados aparecem em "Pré-cadastros" para completar.
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-laptop"></i>
                    Cadastros Online
                </h1>
                <p class="page-subtitle">
                    Gerencie os cadastros realizados através do portal online público
                </p>

                <div class="stats-summary">
                    <div class="stat-item">
                        <i class="fas fa-list-alt text-primary"></i>
                        <span class="stat-value" id="statTotal">0</span>
                        <span>Total Site</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock text-warning"></i>
                        <span class="stat-value" id="statPendentes">0</span>
                        <span>Pendentes</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span class="stat-value" id="statImportados">0</span>
                        <span>Importados</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-edit text-info"></i>
                        <span class="stat-value" id="statPreCadastros"><?php echo count($preCadastros); ?></span>
                        <span>Pré-cadastros</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-calendar-day text-primary"></i>
                        <span class="stat-value" id="statHoje">0</span>
                        <span>Hoje</span>
                    </div>
                </div>
            </div>

            <!-- Abas de Navegação -->
            <div class="nav-tabs-custom">
                <button class="nav-tab-custom active" onclick="trocarAba('pendentes')" id="tab-pendentes">
                    <i class="fas fa-clock"></i>
                    Pendentes
                    <span class="tab-badge" id="badge-pendentes">0</span>
                </button>
                <button class="nav-tab-custom" onclick="trocarAba('importados')" id="tab-importados">
                    <i class="fas fa-check-circle"></i>
                    Importados
                    <span class="tab-badge" id="badge-importados">0</span>
                </button>
                <button class="nav-tab-custom" onclick="trocarAba('precadastros')" id="tab-precadastros">
                    <i class="fas fa-edit"></i>
                    Pré-cadastros
                    <span class="tab-badge"><?php echo count($preCadastros); ?></span>
                </button>
                <button class="nav-tab-custom" onclick="trocarAba('todos')" id="tab-todos">
                    <i class="fas fa-list"></i>
                    Todos Site
                    <span class="tab-badge" id="badge-todos">0</span>
                </button>
            </div>

            <!-- ABA: PENDENTES -->
            <div class="tab-content-area active" id="content-pendentes">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-hourglass-half"></i>
                            Cadastros Pendentes de Importação
                        </h3>
                    </div>

                    <div class="table-responsive">
                        <table id="tablePendentes" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Foto</th>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação/Patente</th>
                                    <th>Contato</th>
                                    <th>Idade</th>
                                    <th>Data Cadastro</th>
                                    <th>Aguardando</th>
                                    <th>Indicação</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tableBodyPendentes">
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        <div class="loading-spinner mx-auto mb-3"></div>
                                        <p class="text-muted mb-0">Carregando cadastros...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ABA: IMPORTADOS -->
            <div class="tab-content-area" id="content-importados">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-check-double"></i>
                            Cadastros Já Importados
                        </h3>
                    </div>

                    <div class="table-responsive">
                        <table id="tableImportados" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Foto</th>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação/Patente</th>
                                    <th>Contato</th>
                                    <th>Data Cadastro</th>
                                    <th>Data Importação</th>
                                    <th>Importado Por</th>
                                    <th>ID Sistema</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tableBodyImportados">
                                <tr>
                                    <td colspan="11" class="text-center py-5">
                                        <div class="loading-spinner mx-auto mb-3"></div>
                                        <p class="text-muted mb-0">Carregando cadastros...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ✅ NOVA ABA: PRÉ-CADASTROS -->
            <div class="tab-content-area" id="content-precadastros">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-user-edit"></i>
                            Pré-cadastros do Sistema (Lógica Antiga)
                        </h3>
                    </div>

                    <div class="table-responsive">
                        <?php if (count($preCadastros) > 0): ?>
                        <table id="tablePreCadastros" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação</th>
                                    <th>Patente</th>
                                    <th>Telefone</th>
                                    <th>Data Pré-cadastro</th>
                                    <th>Dias</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preCadastros as $pre): ?>
                                <tr>
                                    <td><strong>#<?php echo $pre['id']; ?></strong></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($pre['nome'] ?? ''); ?></div>
                                        <?php if ($pre['situacao']): ?>
                                            <small class="text-muted">Situação: <?php echo htmlspecialchars($pre['situacao']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pre['cpf']): ?>
                                            <code><?php echo htmlspecialchars($pre['cpf']); ?></code>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($pre['corporacao']) && in_array($pre['corporacao'], ['PM', 'BM'])): ?>
                                            <span class="status-badge badge-<?php echo strtolower($pre['corporacao']); ?>">
                                                <i class="fas fa-<?php echo $pre['corporacao'] === 'PM' ? 'shield-alt' : 'fire'; ?>"></i>
                                                <?php echo htmlspecialchars($pre['corporacao']); ?>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($pre['patente'])): ?>
                                            <span class="status-badge badge-patente">
                                                <i class="fas fa-star"></i>
                                                <?php echo htmlspecialchars($pre['patente']); ?>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pre['telefone']): ?>
                                            <i class="fas fa-phone text-primary"></i>
                                            <?php echo htmlspecialchars($pre['telefone']); ?>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($pre['data_pre_cadastro']) {
                                            echo '<small>' . date('d/m/Y H:i', strtotime($pre['data_pre_cadastro'])) . '</small>';
                                        } else {
                                            echo '<small class="text-muted">-</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $dias = intval($pre['dias_pre_cadastro'] ?? 0);
                                        $corDias = $dias > 7 ? 'bg-danger' : ($dias > 3 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <span class="badge <?php echo $corDias; ?>">
                                            <i class="fas fa-clock"></i> <?php echo $dias; ?> <?php echo $dias === 1 ? 'dia' : 'dias'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $pre['status_fluxo'] ?? 'AGUARDANDO_DOCUMENTOS';
                                        
                                        switch ($status) {
                                            case 'AGUARDANDO_DOCUMENTOS':
                                                echo '<span class="status-badge badge-aguardando"><i class="fas fa-hourglass-half"></i> Aguardando</span>';
                                                break;
                                            case 'ENVIADO_PRESIDENCIA':
                                                echo '<span class="status-badge badge-enviado"><i class="fas fa-paper-plane"></i> Enviado</span>';
                                                break;
                                            case 'ASSINADO':
                                                echo '<span class="status-badge badge-aprovado"><i class="fas fa-pen-fancy"></i> Assinado</span>';
                                                break;
                                            case 'APROVADO':
                                                echo '<span class="status-badge badge-aprovado"><i class="fas fa-check-circle"></i> Aprovado</span>';
                                                break;
                                            case 'REJEITADO':
                                                echo '<span class="status-badge badge-rejeitado"><i class="fas fa-times-circle"></i> Rejeitado</span>';
                                                break;
                                            default:
                                                echo '<span class="status-badge badge-aguardando"><i class="fas fa-clock"></i> Pendente</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="cadastroForm.php?id=<?php echo $pre['id']; ?>" 
                                           class="btn-action btn-complete" 
                                           target="_blank"
                                           title="Completar cadastro de <?php echo htmlspecialchars($pre['nome'] ?? ''); ?>">
                                            <i class="fas fa-edit"></i>
                                            Completar
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>Nenhum pré-cadastro encontrado</h4>
                            <p>Não há pré-cadastros pendentes no sistema.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ABA: TODOS -->
            <div class="tab-content-area" id="content-todos">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-list-ul"></i>
                            Todos os Cadastros do Site
                        </h3>
                    </div>

                    <div class="table-responsive">
                        <table id="tableTodos" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Foto</th>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação/Patente</th>
                                    <th>Data Cadastro</th>
                                    <th>Data Importação</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tableBodyTodos">
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="loading-spinner mx-auto mb-3"></div>
                                        <p class="text-muted mb-0">Carregando cadastros...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        let dataTables = {};
        let cadastrosData = {
            pendentes: [],
            importados: [],
            todos: []
        };
        let abaAtual = 'pendentes';

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            carregarCadastros();
            
            // ✅ Inicializar tabela de pré-cadastros se tiver dados
            <?php if (count($preCadastros) > 0): ?>
            setTimeout(function() {
                try {
                    if (!dataTables.precadastros && $('#tablePreCadastros').length > 0) {
                        dataTables.precadastros = $('#tablePreCadastros').DataTable({
                            responsive: true,
                            language: {
                                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                            },
                            order: [[6, 'desc']], // Ordenar por data pré-cadastro
                            pageLength: 25,
                            columnDefs: [
                                { targets: [0, 9], orderable: true },
                                { targets: '_all', orderable: true }
                            ]
                        });
                        console.log('✅ DataTable Pré-cadastros inicializado');
                    }
                } catch (e) {
                    console.error('Erro ao inicializar DataTable Pré-cadastros:', e);
                }
            }, 500);
            <?php endif; ?>
        });

        // Trocar Aba
        function trocarAba(aba) {
            // Atualizar botões
            document.querySelectorAll('.nav-tab-custom').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(`tab-${aba}`).classList.add('active');

            // Atualizar conteúdo
            document.querySelectorAll('.tab-content-area').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`content-${aba}`).classList.add('active');

            abaAtual = aba;
        }

        // Carregar cadastros via PROXY interno
        function carregarCadastros() {
            showLoading();

            $.ajax({
                url: '../api/proxy_cadastros_online.php?todos=1',
                method: 'GET',
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    hideLoading();

                    if (response.status === 'success') {
                        if (response.data && response.data.cadastros) {
                            const cadastros = response.data.cadastros;
                            
                            // Separar por status
                            cadastrosData.pendentes = cadastros.filter(c => c.importado == 0);
                            cadastrosData.importados = cadastros.filter(c => c.importado == 1);
                            cadastrosData.todos = cadastros;

                            atualizarEstatisticas(response.data.estatisticas);
                            atualizarBadges();
                            renderizarTodasTabelas();
                        } else {
                            exibirErroTabela('Nenhum cadastro encontrado');
                        }
                    } else {
                        showToast('Erro: ' + response.message, 'danger');
                        exibirErroTabela(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    
                    let errorMsg = 'Erro ao conectar com o sistema online';
                    
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMsg = errorResponse.message;
                        }
                    } catch (e) {}
                    
                    showToast(errorMsg, 'danger');
                    exibirErroTabela(errorMsg);
                }
            });
        }

        function atualizarEstatisticas(stats) {
            if (stats) {
                document.getElementById('statTotal').textContent = stats.total_geral || 0;
                document.getElementById('statPendentes').textContent = stats.pendentes || 0;
                document.getElementById('statImportados').textContent = stats.importados || 0;
                document.getElementById('statHoje').textContent = stats.cadastros_hoje || 0;
            }
        }

        function atualizarBadges() {
            document.getElementById('badge-pendentes').textContent = cadastrosData.pendentes.length;
            document.getElementById('badge-importados').textContent = cadastrosData.importados.length;
            document.getElementById('badge-todos').textContent = cadastrosData.todos.length;
        }

        function renderizarTodasTabelas() {
            renderizarTabela('pendentes', cadastrosData.pendentes);
            renderizarTabela('importados', cadastrosData.importados);
            renderizarTabela('todos', cadastrosData.todos);
        }

        function renderizarTabela(tipo, dados) {
            const tableId = tipo === 'pendentes' ? 'tablePendentes' : 
                           tipo === 'importados' ? 'tableImportados' : 'tableTodos';
            const bodyId = `tableBody${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`;
            
            const tbody = document.getElementById(bodyId);
            if (!tbody) return;

            // Destruir DataTable se existir
            if (dataTables[tipo]) {
                dataTables[tipo].destroy();
            }

            tbody.innerHTML = '';

            if (dados.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${tipo === 'todos' ? '9' : '11'}">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>Nenhum cadastro ${tipo === 'pendentes' ? 'pendente' : tipo === 'importados' ? 'importado' : 'encontrado'}</h4>
                                <p>${tipo === 'pendentes' ? 'Não há cadastros aguardando importação.' : tipo === 'importados' ? 'Nenhum cadastro foi importado ainda.' : 'Nenhum cadastro disponível.'}</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            dados.forEach((cadastro) => {
                const row = document.createElement('tr');

                // Coluna Foto
                let fotoHtml = '';
                if (cadastro.tem_foto && cadastro.foto_base64) {
                    const fotoSrc = 'data:' + (cadastro.foto_mime_type || 'image/jpeg') + ';base64,' + cadastro.foto_base64;
                    fotoHtml = `
                        <img src="${fotoSrc}" 
                             class="foto-preview" 
                             alt="Foto de ${htmlEscape(cadastro.nome)}"
                             onclick="visualizarFoto('${fotoSrc}', '${htmlEscape(cadastro.nome).replace(/'/g, "\\'")}')">
                    `;
                } else {
                    fotoHtml = '<div class="sem-foto"><i class="fas fa-user"></i></div>';
                }

                // Badges Militares
                let dadosMilitares = '';
                if (cadastro.corporacao || cadastro.patente) {
                    if (cadastro.corporacao) {
                        const badgeClass = cadastro.corporacao === 'PM' ? 'badge-pm' : 'badge-bm';
                        const icone = cadastro.corporacao === 'PM' ? 'fa-shield-alt' : 'fa-fire';
                        dadosMilitares += `<span class="status-badge ${badgeClass}"><i class="fas ${icone}"></i> ${htmlEscape(cadastro.corporacao)}</span>`;
                    }
                    if (cadastro.patente) {
                        dadosMilitares += `<br><span class="status-badge badge-patente"><i class="fas fa-star"></i> ${htmlEscape(cadastro.patente)}</span>`;
                    }
                } else {
                    dadosMilitares = '<small class="text-muted">Não informado</small>';
                }

                const dataCadastro = cadastro.data_cadastro 
                    ? new Date(cadastro.data_cadastro).toLocaleString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                    : '-';

                const dataImportacao = cadastro.data_importacao
                    ? new Date(cadastro.data_importacao).toLocaleString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                    : '-';

                // PENDENTES
                if (tipo === 'pendentes') {
                    const optanteJuridico = cadastro.optante_juridico == 1 
                        ? '<br><span class="status-badge status-optante"><i class="fas fa-balance-scale"></i> Optante Jurídico</span>'
                        : '';

                    const badgeFoto = cadastro.tem_foto 
                        ? '<br><span class="status-badge badge-foto"><i class="fas fa-camera"></i> Com foto</span>'
                        : '';

                    const diasAguardando = cadastro.dias_aguardando || 0;
                    const corDias = diasAguardando > 7 ? 'text-danger' : diasAguardando > 3 ? 'text-warning' : 'text-success';

                    row.innerHTML = `
                        <td><strong>#${cadastro.id}</strong></td>
                        <td>${fotoHtml}</td>
                        <td>
                            <div class="fw-semibold">${htmlEscape(cadastro.nome)}</div>
                            <small class="text-muted">RG: ${htmlEscape(cadastro.rg) || 'Não informado'}</small>
                            ${badgeFoto}
                            ${optanteJuridico}
                        </td>
                        <td><code>${htmlEscape(cadastro.cpf_formatado || cadastro.cpf)}</code></td>
                        <td>${dadosMilitares}</td>
                        <td>
                            ${cadastro.telefone ? `<i class="fas fa-phone text-primary"></i> ${htmlEscape(cadastro.telefone)}` : '-'}
                            ${cadastro.email ? `<br><small class="text-muted"><i class="fas fa-envelope"></i> ${htmlEscape(cadastro.email)}</small>` : ''}
                        </td>
                        <td>${cadastro.idade ? cadastro.idade + ' anos' : '-'}</td>
                        <td><small>${dataCadastro}</small></td>
                        <td>
                            <span class="badge ${corDias}">
                                <i class="fas fa-clock"></i> ${diasAguardando} ${diasAguardando === 1 ? 'dia' : 'dias'}
                            </span>
                        </td>
                        <td>${cadastro.indicacao ? htmlEscape(cadastro.indicacao) : '-'}</td>
                        <td class="text-center">
                            <button class="btn-action btn-import" onclick="importarCadastro(${cadastro.id}, '${htmlEscape(cadastro.nome).replace(/'/g, "\\'")}')">
                                <i class="fas fa-download"></i>
                                Importar
                            </button>
                        </td>
                    `;
                }

                // IMPORTADOS
                else if (tipo === 'importados') {
                    const statusImportado = '<span class="status-badge status-importado"><i class="fas fa-check"></i> IMPORTADO</span>';
                    
                    row.innerHTML = `
                        <td><strong>#${cadastro.id}</strong></td>
                        <td>${fotoHtml}</td>
                        <td>
                            <div class="fw-semibold">${htmlEscape(cadastro.nome)}</div>
                            <small class="text-muted">RG: ${htmlEscape(cadastro.rg) || 'Não informado'}</small>
                            <br>${statusImportado}
                        </td>
                        <td><code>${htmlEscape(cadastro.cpf_formatado || cadastro.cpf)}</code></td>
                        <td>${dadosMilitares}</td>
                        <td>
                            ${cadastro.telefone ? `<i class="fas fa-phone text-primary"></i> ${htmlEscape(cadastro.telefone)}` : '-'}
                            ${cadastro.email ? `<br><small class="text-muted"><i class="fas fa-envelope"></i> ${htmlEscape(cadastro.email)}</small>` : ''}
                        </td>
                        <td><small>${dataCadastro}</small></td>
                        <td><small>${dataImportacao}</small></td>
                        <td>${cadastro.importado_por ? htmlEscape(cadastro.importado_por) : '-'}</td>
                        <td>${cadastro.observacao_importacao ? htmlEscape(cadastro.observacao_importacao) : '-'}</td>
                        <td class="text-center">
                            ${cadastro.observacao_importacao ? `
                                <button class="btn-action btn-view" onclick="verAssociado('${cadastro.observacao_importacao}')">
                                    <i class="fas fa-eye"></i>
                                    Ver no Sistema
                                </button>
                            ` : '-'}
                        </td>
                    `;
                }

                // TODOS
                else if (tipo === 'todos') {
                    const statusBadge = cadastro.importado == 1
                        ? '<span class="status-badge status-importado"><i class="fas fa-check"></i> IMPORTADO</span>'
                        : '<span class="status-badge status-pendente"><i class="fas fa-clock"></i> PENDENTE</span>';

                    const acaoBtn = cadastro.importado == 1
                        ? (cadastro.observacao_importacao ? `
                            <button class="btn-action btn-view" onclick="verAssociado('${cadastro.observacao_importacao}')">
                                <i class="fas fa-eye"></i>
                                Ver
                            </button>
                        ` : '-')
                        : `
                            <button class="btn-action btn-import" onclick="importarCadastro(${cadastro.id}, '${htmlEscape(cadastro.nome).replace(/'/g, "\\'")}')">
                                <i class="fas fa-download"></i>
                                Importar
                            </button>
                        `;

                    row.innerHTML = `
                        <td><strong>#${cadastro.id}</strong></td>
                        <td>${statusBadge}</td>
                        <td>${fotoHtml}</td>
                        <td>
                            <div class="fw-semibold">${htmlEscape(cadastro.nome)}</div>
                            <small class="text-muted">RG: ${htmlEscape(cadastro.rg) || 'Não informado'}</small>
                        </td>
                        <td><code>${htmlEscape(cadastro.cpf_formatado || cadastro.cpf)}</code></td>
                        <td>${dadosMilitares}</td>
                        <td><small>${dataCadastro}</small></td>
                        <td><small>${dataImportacao}</small></td>
                        <td class="text-center">${acaoBtn}</td>
                    `;
                }

                tbody.appendChild(row);
            });

            // Inicializar DataTable
            try {
                dataTables[tipo] = $(`#${tableId}`).DataTable({
                    responsive: true,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    order: [[0, 'desc']],
                    pageLength: 25
                });
            } catch (e) {
                console.error('Erro DataTables:', e);
            }
        }

        function visualizarFoto(fotoSrc, nome) {
            document.getElementById('modalFotoImg').src = fotoSrc;
            document.getElementById('modalFotoTitulo').textContent = 'Foto de ' + nome;
            new bootstrap.Modal(document.getElementById('modalFoto')).show();
        }

        function verAssociado(observacao) {
            // Tentar extrair ID do associado da observação
            const match = observacao.match(/ID:\s*(\d+)/i);
            if (match) {
                const associadoId = match[1];
                window.open(`cadastroForm.php?id=${associadoId}`, '_blank');
            } else {
                showToast('⚠️ ID do associado não encontrado na observação', 'warning');
            }
        }

        function importarCadastro(id, nome) {
            if (!confirm(`Importar cadastro de "${nome}"?\n\nSerá criado como pré-cadastro.`)) {
                return;
            }

            showLoading();

            $.ajax({
                url: '../api/cadastros_online_importar.php',
                method: 'POST',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();

                    if (response.status === 'success') {
                        showToast(
                            `✅ Importado com sucesso!\n\n` +
                            `ID: ${response.associado_id}\n` +
                            `Protocolo: ${response.info.protocolo}`,
                            'success'
                        );
                        
                        setTimeout(() => {
                            window.open(`cadastroForm.php?id=${response.associado_id}`, '_blank');
                            recarregarDados();
                        }, 2000);
                    } else {
                        showToast('❌ ' + response.message, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    
                    let errorMsg = 'Erro ao importar';
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMsg = errorResponse.message;
                        }
                    } catch (e) {}
                    
                    showToast('❌ ' + errorMsg, 'danger');
                }
            });
        }

        function recarregarDados() {
            location.reload();
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function exibirErroTabela(mensagem) {
            ['pendentes', 'importados', 'todos'].forEach(tipo => {
                const tbody = document.getElementById(`tableBody${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${tipo === 'todos' ? '9' : '11'}">
                                <div class="empty-state">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    <h4>Erro ao carregar</h4>
                                    <p class="text-danger mb-3">${mensagem}</p>
                                    <button class="btn-refresh" onclick="recarregarDados()">
                                        <i class="fas fa-sync-alt"></i>
                                        Tentar Novamente
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            });
        }

        function showToast(message, type = 'success') {
            const bgClass = type === 'success' ? 'bg-success' : 
                           type === 'danger' ? 'bg-danger' : 
                           type === 'warning' ? 'bg-warning' : 'bg-info';

            const toastHTML = `
                <div class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body" style="white-space: pre-line;">
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

            const toast = new bootstrap.Toast(container.lastElementChild, {
                delay: type === 'success' ? 5000 : 8000
            });
            toast.show();
        }

        function htmlEscape(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    </script>
</body>
</html>