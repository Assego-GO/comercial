<?php
/**
 * Página de Relatórios Comerciais - Sistema ASSEGO
 * pages/comercial_relatorios.php
 * 
 * VERSÃO PADRONIZADA - Estilo minimalista
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Permissoes.php';
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

// VERIFICAÇÃO DE PERMISSÃO
$temPermissaoRelatorios = Permissoes::tem('COMERCIAL_RELATORIOS');
if (!$temPermissaoRelatorios) {
    Permissoes::registrarAcessoNegado('COMERCIAL_RELATORIOS', 'comercial_relatorios.php');
    $_SESSION['erro'] = 'Você não tem permissão para acessar os relatórios comerciais.';
    header('Location: ../pages/comercial.php');
    exit;
}

// Define o título da página
$page_title = 'Relatórios Comerciais - ASSEGO';

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
    'notificationCount' => 0,
    'showSearch' => false
]);

// Buscar dados dinâmicos do banco para os filtros
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // BUSCAR CORPORAÇÕES ÚNICAS DO BANCO
    $stmt = $db->prepare("
        SELECT DISTINCT corporacao 
        FROM Militar 
        WHERE corporacao IS NOT NULL 
        AND corporacao != '' 
        ORDER BY corporacao
    ");
    $stmt->execute();
    $corporacoesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // BUSCAR PATENTES ÚNICAS DO BANCO
    $stmt = $db->prepare("
        SELECT DISTINCT patente 
        FROM Militar 
        WHERE patente IS NOT NULL 
        AND patente != '' 
        ORDER BY 
            CASE 
                WHEN patente LIKE '%Coronel%' THEN 1
                WHEN patente LIKE '%Tenente-Coronel%' THEN 2
                WHEN patente LIKE '%Major%' THEN 3
                WHEN patente LIKE '%Capitão%' THEN 4
                WHEN patente LIKE '%Tenente%' THEN 5
                WHEN patente LIKE '%Aspirante%' THEN 6
                WHEN patente LIKE '%Subtenente%' THEN 7
                WHEN patente LIKE '%Sargento%' THEN 8
                WHEN patente LIKE '%Cabo%' THEN 9
                WHEN patente LIKE '%Soldado%' THEN 10
                WHEN patente LIKE '%Aluno%' THEN 11
                ELSE 12
            END,
            patente
    ");
    $stmt->execute();
    $patentesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // BUSCAR LOTAÇÕES ÚNICAS DO BANCO
    $stmt = $db->prepare("
        SELECT lotacao, COUNT(*) as total 
        FROM Militar 
        WHERE lotacao IS NOT NULL 
        AND lotacao != '' 
        GROUP BY lotacao 
        ORDER BY total DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $lotacoesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $corporacoesDB = [];
    $patentesDB = [];
    $lotacoesDB = [];
    error_log("Erro ao buscar dados dinâmicos: " . $e->getMessage());
}

// Se não há dados no banco, usar valores padrão
if (empty($corporacoesDB)) {
    $corporacoesDB = ['Polícia Militar', 'Bombeiro Militar'];
}

if (empty($patentesDB)) {
    $patentesDB = [
        'Coronel',
        'Tenente-Coronel',
        'Major',
        'Capitão',
        'Primeiro-Tenente',
        'Segundo-Tenente',
        'Aspirante-a-Oficial',
        'Subtenente',
        'Primeiro-Sargento',
        'Segundo-Sargento',
        'Terceiro-Sargento',
        'Cabo',
        'Soldado 1ª Classe',
        'Soldado 2ª Classe',
        'Aluno Soldado'
    ];
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

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./estilizacao/style.css">

    <!-- Estilos Padronizados - Estilo Minimalista -->
    <style>
        /* Variáveis CSS minimalistas */
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --border-light: #e9ecef;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--gray-100);
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
            margin-left: 0;
        }

        /* Page Header - Fora de cards */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
        }

        /* Back Button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--secondary);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            box-shadow: var(--shadow-sm);
        }

        .btn-back:hover {
            transform: translateX(-2px);
            color: white;
            box-shadow: var(--shadow-md);
            background: #5a6268;
        }

        /* Actions Container - Estilo Minimalista */
        .actions-container {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .actions-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .actions-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .actions-title i {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }

        .action-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .action-card.active {
            background: linear-gradient(135deg, rgba(0, 86, 210, 0.05) 0%, rgba(74, 144, 226, 0.05) 100%);
            border-color: var(--primary);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .action-icon.primary { background: var(--primary); }
        .action-icon.success { background: var(--success); }
        .action-icon.warning { background: var(--warning); }
        .action-icon.info { background: var(--info); }

        .action-content h5 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-content p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        /* Filters Container */
        .filters-container {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        /* Forms */
        .form-control-custom,
        .form-select-custom {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control-custom:focus,
        .form-select-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
            outline: none;
        }

        .form-label-custom {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label-custom i {
            color: var(--primary);
            font-size: 0.9rem;
        }

        /* Buttons */
        .btn-custom {
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .btn-primary-custom {
            background: var(--primary);
            color: white;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: var(--primary-light);
        }

        .btn-secondary-custom {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: #5a6268;
        }

        /* Results Container */
        .results-container {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            min-height: 400px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .results-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .results-title i {
            color: var(--primary);
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .btn-export-excel {
            background: var(--success);
            color: white;
        }

        .btn-export-pdf {
            background: var(--danger);
            color: white;
        }

        .btn-export-print {
            background: var(--info);
            color: white;
        }

        .btn-export:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Loading */
        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        .spinner-custom {
            width: 50px;
            height: 50px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Alert */
        .alert-custom {
            padding: 1rem;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-sm);
        }

        .alert-info-custom {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border-left: 3px solid var(--info);
        }

        /* Badge */
        .badge-custom {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            background: var(--primary);
            color: white;
        }

        /* DataTable Custom */
        .dataTables_wrapper {
            padding-top: 1rem;
        }

        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
        }

        table.dataTable thead th {
            background: var(--gray-100);
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border-light);
        }

        table.dataTable tbody tr:hover {
            background: var(--gray-100);
        }

        /* Badge Status */
        .badge-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
        }

        .badge-status.ativo {
            background: var(--success);
            color: white;
        }

        .badge-status.inativo {
            background: var(--danger);
            color: white;
        }

        .badge-status.pre-cadastro {
            background: var(--warning);
            color: white;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* Animations */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                grid-template-columns: 1fr !important;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .export-buttons {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Botão Voltar -->
            <a href="./comercial.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Voltar aos Serviços Comerciais
            </a>

            <!-- Page Header - Fora de cards como no comercial.php -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-chart-line me-2"></i>Relatórios Comerciais
                </h1>
                <p class="page-subtitle">
                    Sistema avançado de geração de relatórios para análise comercial e estratégica
                </p>
            </div>

            <!-- Report Type Selector - Cards de Ação -->
            <div class="actions-container">
                <div class="actions-header">
                    <h3 class="actions-title">
                        <i class="fas fa-clipboard-list"></i>
                        Selecione o Tipo de Relatório
                    </h3>
                </div>

                <div class="actions-grid">
                    <div class="action-card active" data-type="desfiliacoes" onclick="selecionarRelatorio('desfiliacoes')">
                        <div class="action-icon primary">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="action-content">
                            <h5>Desfiliações</h5>
                            <p>Análise de associados desfiliados</p>
                        </div>
                    </div>

                    <div class="action-card" data-type="indicacoes" onclick="selecionarRelatorio('indicacoes')">
                        <div class="action-icon success">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-content">
                            <h5>Indicações</h5>
                            <p>Relatório completo de indicações</p>
                        </div>
                    </div>

                    <div class="action-card" data-type="aniversariantes" onclick="selecionarRelatorio('aniversariantes')">
                        <div class="action-icon warning">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="action-content">
                            <h5>Aniversariantes</h5>
                            <p>Aniversários por período</p>
                        </div>
                    </div>

                    <div class="action-card" data-type="novos_cadastros" onclick="selecionarRelatorio('novos_cadastros')">
                        <div class="action-icon info">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="action-content">
                            <h5>Novos Cadastros</h5>
                            <p>Associados recém cadastrados</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-container">
                <div class="actions-header">
                    <h3 class="actions-title">
                        <i class="fas fa-filter"></i>
                        Configuração de Filtros
                    </h3>
                </div>

                <form id="formFiltros">
                    <input type="hidden" name="tipo" id="tipoRelatorio" value="desfiliacoes">

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-calendar-alt"></i>
                                Data Inicial
                            </label>
                            <input type="date" name="data_inicio" class="form-control form-control-custom" id="dataInicio"
                                value="<?php echo date('Y-m-01'); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-calendar-check"></i>
                                Data Final
                            </label>
                            <input type="date" name="data_fim" class="form-control form-control-custom" id="dataFim"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-building"></i>
                                Corporação
                            </label>
                            <select name="corporacao" class="form-select form-select-custom select2" id="corporacao">
                                <option value="">Todas as corporações</option>
                                <?php foreach($corporacoesDB as $corporacao): ?>
                                    <?php if(!empty($corporacao)): ?>
                                        <option value="<?php echo htmlspecialchars($corporacao); ?>">
                                            <?php echo htmlspecialchars($corporacao); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-star"></i>
                                Patente
                            </label>
                            <select name="patente" class="form-select form-select-custom select2" id="patente">
                                <option value="">Todas as patentes</option>
                                <?php foreach($patentesDB as $patente): ?>
                                    <?php if(!empty($patente)): ?>
                                        <option value="<?php echo htmlspecialchars($patente); ?>">
                                            <?php echo htmlspecialchars($patente); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-map-marker-alt"></i>
                                Lotação
                            </label>
                            <select name="lotacao" class="form-select form-select-custom select2" id="lotacao">
                                <option value="">Todas as lotações</option>
                                <?php foreach($lotacoesDB as $lotacao): ?>
                                    <?php if(!empty($lotacao)): ?>
                                        <option value="<?php echo htmlspecialchars($lotacao); ?>">
                                            <?php echo htmlspecialchars($lotacao); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-sort-alpha-down"></i>
                                Ordenar Por
                            </label>
                            <select name="ordenacao" class="form-select form-select-custom" id="ordenacao">
                                <option value="nome">Nome (A-Z)</option>
                                <option value="data">Data (Mais recente)</option>
                                <option value="patente">Patente (Hierarquia)</option>
                                <option value="corporacao">Corporação</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-search"></i>
                                Busca Rápida
                            </label>
                            <input type="text" name="busca" class="form-control form-control-custom" id="busca"
                                placeholder="Nome, CPF ou RG...">
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-custom btn-primary-custom w-100"
                                onclick="gerarRelatorio()">
                                <i class="fas fa-chart-bar me-2"></i>
                                Gerar Relatório
                            </button>
                        </div>
                    </div>

                    <!-- Botões de ação -->
                    <div class="text-center">
                        <button type="button" class="btn btn-custom btn-secondary-custom" onclick="limparFiltros()">
                            <i class="fas fa-eraser me-2"></i>
                            Limpar Filtros
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Container -->
            <div class="results-container">
                <div class="results-header">
                    <h5 class="results-title">
                        <i class="fas fa-table"></i>
                        <span id="tituloResultado">Aguardando Geração do Relatório</span>
                        <span class="badge-custom ms-3" id="totalRegistros" style="display: none;">0</span>
                    </h5>
                    <div class="export-buttons" id="exportButtons" style="display: none;">
                        <button class="btn-export btn-export-excel" onclick="exportarRelatorio('excel')">
                            <i class="fas fa-file-excel"></i>
                            Excel
                        </button>
                        <button class="btn-export btn-export-pdf" onclick="exportarRelatorio('pdf')">
                            <i class="fas fa-file-pdf"></i>
                            PDF
                        </button>
                        <button class="btn-export btn-export-print" onclick="imprimirRelatorio()">
                            <i class="fas fa-print"></i>
                            Imprimir
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div id="loadingContainer" class="loading-container" style="display: none;">
                    <div class="spinner-custom"></div>
                    <p class="mt-3 text-muted">Processando relatório...</p>
                </div>

                <!-- Results -->
                <div id="resultsContainer">
                    <div class="alert-custom alert-info-custom">
                        <i class="fas fa-info-circle fa-lg"></i>
                        <div>
                            <strong>Pronto para gerar relatórios!</strong><br>
                            Configure os filtros desejados e clique em "Gerar Relatório" para visualizar os dados.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Variáveis globais
        let currentReportType = 'desfiliacoes';
        let currentReportData = null;
        let dataTable = null;

        // Inicialização
        $(document).ready(function () {
            // Inicializar Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function() {
                    return $(this).find('option:first').text();
                },
                allowClear: true,
                language: {
                    noResults: function() {
                        return "Nenhum resultado encontrado";
                    }
                }
            });
        });

        // Selecionar tipo de relatório
        function selecionarRelatorio(tipo) {
            currentReportType = tipo;
            document.getElementById('tipoRelatorio').value = tipo;

            // Atualizar visual dos cards
            document.querySelectorAll('.action-card').forEach(card => {
                card.classList.remove('active');
                if (card.dataset.type === tipo) {
                    card.classList.add('active');
                }
            });

            // Limpar resultados anteriores
            document.getElementById('resultsContainer').innerHTML = `
                <div class="alert-custom alert-info-custom">
                    <i class="fas fa-info-circle fa-lg"></i>
                    <div>
                        <strong>Tipo de relatório alterado!</strong><br>
                        Configure os filtros e clique em "Gerar Relatório" para visualizar os dados.
                    </div>
                </div>
            `;
            
            document.getElementById('exportButtons').style.display = 'none';
            document.getElementById('totalRegistros').style.display = 'none';

            showToast(`Relatório de ${getNomeTipoRelatorio(tipo)} selecionado`, 'info');
        }

        // Obter nome do tipo de relatório
        function getNomeTipoRelatorio(tipo) {
            const nomes = {
                'desfiliacoes': 'Desfiliações',
                'indicacoes': 'Indicações',
                'aniversariantes': 'Aniversariantes',
                'novos_cadastros': 'Novos Cadastros'
            };
            return nomes[tipo] || 'Relatório';
        }

        // Gerar relatório
        async function gerarRelatorio() {
            const formData = new FormData(document.getElementById('formFiltros'));
            const params = new URLSearchParams(formData);

            // Validar datas
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;

            if (!dataInicio || !dataFim) {
                showToast('Por favor, selecione as datas inicial e final', 'warning');
                return;
            }

            if (dataInicio > dataFim) {
                showToast('A data inicial não pode ser maior que a data final', 'warning');
                return;
            }

            // Mostrar loading
            document.getElementById('loadingContainer').style.display = 'flex';
            document.getElementById('resultsContainer').innerHTML = '';
            document.getElementById('exportButtons').style.display = 'none';
            document.getElementById('totalRegistros').style.display = 'none';
            document.getElementById('tituloResultado').textContent = 'Processando...';

            try {
                const response = await fetch(`../api/relatorios/gerar_relatorio_comercial.php?${params.toString()}`);
                
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    currentReportData = data;
                    renderizarRelatorio(data);
                    document.getElementById('exportButtons').style.display = 'flex';
                    showToast('Relatório gerado com sucesso!', 'success');
                } else {
                    throw new Error(data.message || 'Erro ao gerar relatório');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarErro(error.message || 'Erro ao processar relatório');
            } finally {
                document.getElementById('loadingContainer').style.display = 'none';
            }
        }

        // Renderizar relatório
        function renderizarRelatorio(response) {
            const container = document.getElementById('resultsContainer');
            const data = response.data || [];

            // Atualizar título
            document.getElementById('tituloResultado').textContent = `${getNomeTipoRelatorio(currentReportType)} - ${data.length} registros`;
            document.getElementById('totalRegistros').textContent = data.length;
            document.getElementById('totalRegistros').style.display = 'inline-block';

            if (data.length === 0) {
                container.innerHTML = `
                    <div class="alert-custom alert-info-custom">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div>
                            <strong>Nenhum registro encontrado</strong><br>
                            Tente ajustar os filtros ou verificar se existem dados no período selecionado.
                        </div>
                    </div>
                `;
                return;
            }

            // Renderização especial para INDICAÇÕES
            if (currentReportType === 'indicacoes') {
                renderizarRelatorioIndicacoes(data);
                return;
            }

            // Criar tabela para outros relatórios
            let tableHTML = '<div class="table-responsive"><table id="reportTable" class="table table-striped table-hover">';
            
            // Headers
            const headers = getTableHeaders(currentReportType);
            tableHTML += '<thead><tr>';
            tableHTML += '<th width="50">#</th>';
            headers.forEach(header => {
                tableHTML += `<th>${header.label}</th>`;
            });
            tableHTML += '</tr></thead><tbody>';

            // Dados
            data.forEach((row, index) => {
                tableHTML += '<tr>';
                tableHTML += `<td>${index + 1}</td>`;
                headers.forEach(header => {
                    const value = row[header.key] || '-';
                    tableHTML += `<td>${formatValue(value, header.type)}</td>`;
                });
                tableHTML += '</tr>';
            });

            tableHTML += '</tbody></table></div>';
            container.innerHTML = tableHTML;

            inicializarDataTable();
        }

        // Renderizar relatório de indicações especial
        function renderizarRelatorioIndicacoes(data) {
            const container = document.getElementById('resultsContainer');
            
            // Criar tabela detalhada para indicações
            let tableHTML = `
                <div class="table-responsive">
                    <table id="reportTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Data</th>
                                <th colspan="3" style="background: #e0f2fe;">INDICADOR (Quem indicou)</th>
                                <th colspan="6" style="background: #dcfce7;">ASSOCIADO INDICADO</th>
                                <th>Status</th>
                                <th>Totais</th>
                            </tr>
                            <tr>
                                <th></th>
                                <th>Indicação</th>
                                <!-- Indicador -->
                                <th style="background: #f0f9ff;">Nome</th>
                                <th style="background: #f0f9ff;">Patente</th>
                                <th style="background: #f0f9ff;">Corporação</th>
                                <!-- Associado Indicado -->
                                <th style="background: #f0fdf4;">Nome</th>
                                <th style="background: #f0fdf4;">CPF</th>
                                <th style="background: #f0fdf4;">Telefone</th>
                                <th style="background: #f0fdf4;">Patente</th>
                                <th style="background: #f0fdf4;">Corporação</th>
                                <th style="background: #f0fdf4;">Lotação</th>
                                <!-- Status e Totais -->
                                <th>Situação</th>
                                <th>Indicações</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            // Processar dados
            data.forEach((row, index) => {
                // Determinar cor do status
                let statusClass = 'badge-status ';
                let statusText = row.status_simplificado || row.situacao_associado || 'Desconhecido';
                
                if (statusText.toLowerCase().includes('ativo') || statusText.toLowerCase().includes('filiado')) {
                    statusClass += 'ativo';
                } else if (statusText.toLowerCase().includes('desfil') || statusText.toLowerCase().includes('inativ')) {
                    statusClass += 'inativo';
                } else if (row.tipo_cadastro === 'Pré-cadastro') {
                    statusClass += 'pre-cadastro';
                }

                tableHTML += '<tr>';
                tableHTML += `<td>${index + 1}</td>`;
                tableHTML += `<td>${formatValue(row.data_indicacao, 'date')}</td>`;
                
                // Dados do Indicador
                tableHTML += `<td style="background: #f0f9ff;"><strong>${row.indicador_nome || '-'}</strong></td>`;
                tableHTML += `<td style="background: #f0f9ff;">${row.indicador_patente || '-'}</td>`;
                tableHTML += `<td style="background: #f0f9ff;">${row.indicador_corporacao || '-'}</td>`;
                
                // Dados do Associado Indicado
                tableHTML += `<td style="background: #f0fdf4;"><strong>${row.associado_indicado_nome || '-'}</strong></td>`;
                tableHTML += `<td style="background: #f0fdf4;">${formatValue(row.associado_indicado_cpf_formatado || row.associado_indicado_cpf, 'cpf')}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${formatValue(row.associado_indicado_telefone_formatado || row.associado_indicado_telefone, 'phone')}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${row.associado_indicado_patente || '-'}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${row.associado_indicado_corporacao || '-'}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${row.associado_indicado_lotacao || '-'}</td>`;
                
                // Status
                tableHTML += `<td><span class="${statusClass}">${statusText}</span></td>`;
                
                // Total de indicações do indicador
                const totalIndicacoes = row.total_indicacoes_do_indicador || 0;
                const totalAtivos = row.total_indicados_ativos || 0;
                tableHTML += `<td>
                    <span class="badge bg-primary">${totalIndicacoes} total</span>
                    <span class="badge bg-success ms-1">${totalAtivos} ativos</span>
                </td>`;
                
                tableHTML += '</tr>';
            });

            tableHTML += '</tbody></table></div>';
            container.innerHTML = tableHTML;

            inicializarDataTable();
        }

        // Inicializar DataTable
        function inicializarDataTable() {
            // Destruir DataTable anterior se existir
            if (dataTable) {
                dataTable.destroy();
            }

            // Inicializar novo DataTable
            dataTable = $('#reportTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                pageLength: 25,
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Copiar',
                        className: 'btn btn-sm btn-secondary'
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: `Relatório_${currentReportType}_${new Date().toISOString().split('T')[0]}`
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-sm btn-danger',
                        title: `Relatório_${currentReportType}_${new Date().toISOString().split('T')[0]}`,
                        orientation: 'landscape',
                        pageSize: 'A4'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-sm btn-info'
                    }
                ]
            });
        }

        // Obter headers da tabela
        function getTableHeaders(tipo) {
            const headers = {
                'desfiliacoes': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'cpf', label: 'CPF', type: 'cpf' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'data_desfiliacao', label: 'Data Desfiliação', type: 'date' }
                ],
                'aniversariantes': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'data_nascimento', label: 'Data Nascimento', type: 'date' },
                    { key: 'idade', label: 'Idade', type: 'number' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'telefone', label: 'Telefone', type: 'phone' }
                ],
                'novos_cadastros': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'cpf', label: 'CPF', type: 'cpf' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'indicado_por', label: 'Indicado Por', type: 'text' },
                    { key: 'data_aprovacao', label: 'Data Cadastro', type: 'date' },
                    { key: 'tipo_cadastro', label: 'Tipo', type: 'text' }
                ]
            };
            return headers[tipo] || [];
        }

        // Formatar valores
        function formatValue(value, type) {
            if (!value || value === null || value === '' || value === undefined) return '-';

            switch (type) {
                case 'date':
                    return formatDate(value);
                case 'cpf':
                    return formatCPF(value);
                case 'phone':
                    return formatPhone(value);
                case 'number':
                    const num = parseInt(value);
                    if (isNaN(num)) return '0';
                    return num.toLocaleString('pt-BR');
                default:
                    return value || '-';
            }
        }

        // Formatar data
        function formatDate(date) {
            if (!date || date === '0000-00-00' || date === '') return '-';
            const [year, month, day] = date.split('-');
            return `${day}/${month}/${year}`;
        }

        // Formatar CPF
        function formatCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length === 11) {
                return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }
            return cpf || '-';
        }

        // Formatar telefone
        function formatPhone(phone) {
            if (!phone) return '-';
            phone = phone.toString().replace(/\D/g, '');
            if (phone.length === 11) {
                return phone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
            } else if (phone.length === 10) {
                return phone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
            }
            return phone || '-';
        }

        // Limpar filtros
        function limparFiltros() {
            document.getElementById('formFiltros').reset();
            $('.select2').val(null).trigger('change');
            document.getElementById('dataInicio').value = new Date().toISOString().slice(0, 8) + '01';
            document.getElementById('dataFim').value = new Date().toISOString().slice(0, 10);
            showToast('Filtros limpos com sucesso!', 'info');
        }

        // Exportar relatório
        function exportarRelatorio(formato) {
            if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
                showToast('Nenhum relatório para exportar', 'warning');
                return;
            }

            // Usar DataTables export
            if (dataTable) {
                switch(formato) {
                    case 'excel':
                        dataTable.button('.buttons-excel').trigger();
                        break;
                    case 'pdf':
                        dataTable.button('.buttons-pdf').trigger();
                        break;
                }
                showToast(`Exportando para ${formato.toUpperCase()}...`, 'success');
            }
        }

        // Imprimir relatório
        function imprimirRelatorio() {
            if (dataTable) {
                dataTable.button('.buttons-print').trigger();
            } else {
                window.print();
            }
        }

        // Mostrar erro
        function mostrarErro(mensagem) {
            document.getElementById('resultsContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Erro:</strong> ${mensagem}
                </div>
            `;
            document.getElementById('tituloResultado').textContent = 'Erro ao gerar relatório';
            showToast(mensagem, 'danger');
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
    </script>
</body>
</html>