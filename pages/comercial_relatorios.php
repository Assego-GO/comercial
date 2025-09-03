<?php
/**
 * Página de Relatórios Comerciais - Sistema ASSEGO
 * pages/comercial_relatorios.php
 * 
 * VERSÃO FINAL - Sem Estatísticas Gerais
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

    // Total de associados
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'");
    $stmt->execute();
    $totalAssociados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de desfiliações no mês
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM Associados 
        WHERE situacao IN ('DESFILIADO', 'Desfiliado') 
        AND MONTH(data_desfiliacao) = MONTH(CURDATE())
        AND YEAR(data_desfiliacao) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $desfiliacoesmes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de novos cadastros no mês
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM Associados 
        WHERE MONTH(data_aprovacao) = MONTH(CURDATE())
        AND YEAR(data_aprovacao) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $novosCadastrosMes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

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
                WHEN patente LIKE '%Aluno%' THEN 1
                WHEN patente LIKE '%Soldado%' THEN 2
                WHEN patente LIKE '%Cabo%' THEN 3
                WHEN patente LIKE '%Sargento%' THEN 4
                WHEN patente LIKE '%Subtenente%' THEN 5
                WHEN patente LIKE '%Suboficial%' THEN 6
                WHEN patente LIKE '%Aspirante%' THEN 7
                WHEN patente LIKE '%Tenente%' THEN 8
                WHEN patente LIKE '%Capitão%' THEN 9
                WHEN patente LIKE '%Major%' THEN 10
                WHEN patente LIKE '%Coronel%' THEN 11
                ELSE 12
            END,
            patente
    ");
    $stmt->execute();
    $patentesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // BUSCAR LOTAÇÕES ÚNICAS DO BANCO (limitado às 50 mais usadas)
    $stmt = $db->prepare("
        SELECT lotacao, COUNT(*) as total 
        FROM Militar 
        WHERE lotacao IS NOT NULL 
        AND lotacao != '' 
        GROUP BY lotacao 
        ORDER BY total DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $lotacoesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $totalAssociados = $desfiliacoesmes = $novosCadastrosMes = 0;
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
        'Aluno Soldado',
        'Soldado 2ª Classe',
        'Soldado 1ª Classe',
        'Cabo',
        'Terceiro Sargento',
        'Terceiro-Sargento',
        'Segundo Sargento',
        'Segundo-Sargento',
        'Primeiro Sargento',
        'Primeiro-Sargento',
        'Subtenente',
        'Aspirante-a-Oficial',
        'Segundo-Tenente',
        'Primeiro-Tenente',
        'Capitão',
        'Major',
        'Tenente-Coronel',
        'Coronel'
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

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

        /* Back Button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .btn-back:hover {
            transform: translateX(-4px);
            color: white;
            box-shadow: var(--shadow-lg);
        }

        /* Stats Grid */
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

        /* Report Type Selector */
        .report-selector-container {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .report-selector-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(180deg, rgba(0, 86, 210, 0.03) 0%, transparent 100%);
            pointer-events: none;
        }

        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid transparent;
            background: linear-gradient(90deg, var(--light) 0%, var(--light) 50%, transparent 50%);
            background-size: 20px 2px;
            background-repeat: repeat-x;
            background-position: bottom;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
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

        .report-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
        }

        .report-type-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 1.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .report-type-card::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(0, 86, 210, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .report-type-card:hover::before {
            width: 400px;
            height: 400px;
        }

        .report-type-card:hover,
        .report-type-card.active {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 12px 24px rgba(0, 86, 210, 0.15);
            background: white;
        }

        .report-type-card.active {
            border-width: 3px;
        }

        .report-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.375rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .report-type-card:hover .report-icon {
            transform: rotate(5deg) scale(1.1);
        }

        .report-icon.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
        }

        .report-icon.success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(34, 197, 94, 0.4);
        }

        .report-icon.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.4);
        }

        .report-icon.primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }

        .report-content h5 {
            margin: 0 0 0.375rem 0;
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
        }

        .report-content p {
            margin: 0;
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.5;
        }

        /* Filters Section */
        .filters-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group-premium {
            position: relative;
        }

        .form-label-premium {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control-premium,
        .form-select-premium {
            padding: 0.875rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
            width: 100%;
        }

        .form-control-premium:focus,
        .form-select-premium:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 86, 210, 0.1);
            outline: none;
        }

        /* Info badges */
        .filter-info-badge {
            display: inline-block;
            background: var(--info);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
            font-weight: 600;
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

        /* Results Container */
        .results-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            min-height: 400px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .results-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-export-csv {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-export-excel {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .btn-export-pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Loading */
        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        .spinner-premium {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(0, 86, 210, 0.1);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* DataTable Custom Style */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: white !important;
            border: none !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-light) !important;
            color: white !important;
            border: none !important;
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

            .stats-grid,
            .report-type-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 1.75rem;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
            }

            .export-buttons {
                width: 100%;
                justify-content: center;
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
            <!-- Botão Voltar -->
            <a href="./comercial.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Voltar aos Serviços Comerciais
            </a>

            <!-- Page Header -->
            <div class="page-header animate__animated animate__fadeInDown">
                <h1 class="page-title">
                    <i class="fas fa-file-alt"></i>
                    Relatórios Comerciais
                </h1>
                <p class="page-subtitle">
                    Gere relatórios detalhados sobre cadastros, desfiliações e indicações
                </p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalAssociados, 0, ',', '.'); ?></div>
                    <div class="stat-label">Total de Associados</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-value"><?php echo $novosCadastrosMes; ?></div>
                    <div class="stat-label">Novos no Mês</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-value"><?php echo $desfiliacoesmes; ?></div>
                    <div class="stat-label">Desfiliações no Mês</div>
                </div>
            </div>

            <!-- Report Type Selector - SEM ESTATÍSTICAS -->
            <div class="report-selector-container" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Selecione o Tipo de Relatório
                    </h3>
                </div>

                <div class="report-type-grid">
                    <div class="report-type-card" data-type="desfiliacoes"
                        onclick="selecionarRelatorio('desfiliacoes')">
                        <div class="report-icon danger">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="report-content">
                            <h5>Desfiliações</h5>
                            <p>Associados desfiliados por período</p>
                        </div>
                    </div>

                    <div class="report-type-card" data-type="indicacoes" onclick="selecionarRelatorio('indicacoes')">
                        <div class="report-icon success">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="report-content">
                            <h5>Indicações</h5>
                            <p>Ranking de indicadores</p>
                        </div>
                    </div>

                    <div class="report-type-card" data-type="aniversariantes"
                        onclick="selecionarRelatorio('aniversariantes')">
                        <div class="report-icon warning">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="report-content">
                            <h5>Aniversariantes</h5>
                            <p>Aniversários por período</p>
                        </div>
                    </div>

                    <div class="report-type-card" data-type="novos_cadastros"
                        onclick="selecionarRelatorio('novos_cadastros')">
                        <div class="report-icon primary">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="report-content">
                            <h5>Novos Cadastros</h5>
                            <p>Associados cadastrados no período</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-container" data-aos="fade-up" data-aos-delay="300">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-filter"></i>
                        Filtros do Relatório
                    </h3>
                </div>

                <form id="formFiltros">
                    <input type="hidden" name="tipo" id="tipoRelatorio" value="desfiliacoes">

                    <div class="filter-row">
                        <div class="form-group-premium">
                            <label class="form-label-premium">
                                <i class="fas fa-calendar"></i>
                                Data Inicial
                            </label>
                            <input type="date" name="data_inicio" class="form-control-premium" id="dataInicio"
                                value="<?php echo date('Y-m-01'); ?>">
                        </div>

                        <div class="form-group-premium">
                            <label class="form-label-premium">
                                <i class="fas fa-calendar"></i>
                                Data Final
                            </label>
                            <input type="date" name="data_fim" class="form-control-premium" id="dataFim"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group-premium">
                            <label class="form-label-premium">
                                <i class="fas fa-building"></i>
                                Corporação
                                <?php if(count($corporacoesDB) > 0): ?>
                                    <span class="filter-info-badge"><?php echo count($corporacoesDB); ?> opções</span>
                                <?php endif; ?>
                            </label>
                            <select name="corporacao" class="form-select-premium select2" id="corporacao">
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

                        <div class="form-group-premium">
                            <label class="form-label-premium">
                                <i class="fas fa-star"></i>
                                Patente
                                <?php if(count($patentesDB) > 0): ?>
                                    <span class="filter-info-badge"><?php echo count($patentesDB); ?> patentes</span>
                                <?php endif; ?>
                            </label>
                            <select name="patente" class="form-select-premium select2" id="patente">
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

                    <div class="filter-row">
                        <div class="form-group-premium">
                            <label class="form-label-premium">
                                <i class="fas fa-map-marker-alt"></i>
                                Lotação
                                <?php if(count($lotacoesDB) > 0): ?>
                                    <span class="filter-info-badge">Top <?php echo count($lotacoesDB); ?> lotações</span>
                                <?php endif; ?>
                            </label>
                            <select name="lotacao" class="form-select-premium select2" id="lotacao">
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

                        <div class="form-group-premium">
                            <label class="form-label-premium">
                                <i class="fas fa-sort"></i>
                                Ordenar Por
                            </label>
                            <select name="ordenacao" class="form-select-premium" id="ordenacao">
                                <option value="nome">Nome</option>
                                <option value="data">Data</option>
                                <option value="patente">Patente</option>
                                <option value="corporacao">Corporação</option>
                            </select>
                        </div>

                        <div class="form-group-premium">
                            <label class="form-label-premium">
                                <i class="fas fa-search"></i>
                                Buscar
                            </label>
                            <input type="text" name="busca" class="form-control-premium" id="busca"
                                placeholder="Nome, CPF ou RG do associado...">
                        </div>

                        <div class="form-group-premium d-flex align-items-end">
                            <button type="button" class="btn btn-premium btn-primary-premium w-100"
                                onclick="gerarRelatorio()">
                                <i class="fas fa-chart-bar me-2"></i>
                                GERAR RELATÓRIO
                            </button>
                        </div>
                    </div>

                    <!-- Botão de reset dos filtros -->
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="limparFiltros()">
                            <i class="fas fa-eraser me-2"></i>
                            Limpar Filtros
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Container -->
            <div class="results-container" data-aos="fade-up" data-aos-delay="400">
                <div class="results-header">
                    <h5 class="results-title">
                        <i class="fas fa-table"></i>
                        <span id="tituloResultado">Resultado do Relatório</span>
                        <span class="badge bg-primary ms-2" id="totalRegistros" style="display: none;">0</span>
                    </h5>
                    <div class="export-buttons" id="exportButtons" style="display: none;">
                        <button class="btn-export btn-export-csv" onclick="exportarRelatorio('csv')">
                            <i class="fas fa-file-csv me-1"></i>
                            CSV
                        </button>
                        <button class="btn-export btn-export-excel" onclick="exportarRelatorio('excel')">
                            <i class="fas fa-file-excel me-1"></i>
                            Excel
                        </button>
                        <button class="btn-export btn-export-pdf" onclick="exportarRelatorio('pdf')">
                            <i class="fas fa-file-pdf me-1"></i>
                            PDF
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div id="loadingContainer" class="loading-container" style="display: none;">
                    <div class="spinner-premium"></div>
                    <p class="mt-3 text-muted">Gerando relatório, aguarde...</p>
                </div>

                <!-- Results -->
                <div id="resultsContainer">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Selecione o tipo de relatório e configure os filtros desejados, depois clique em "Gerar Relatório".
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <!-- Bibliotecas para botões do DataTables -->
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>

    <!-- Select2 e AOS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Variáveis globais
        let currentReportType = 'desfiliacoes';
        let currentReportData = null;
        let dataTable = null;
        let currentPage = 1;
        let totalPages = 1;
        let registrosPorPagina = 50;

        // Inicialização
        $(document).ready(function () {
            // Inicializar AOS
            AOS.init({
                duration: 800,
                once: true,
                offset: 50
            });

            // Inicializar Select2 com busca melhorada
            $('#corporacao, #patente, #lotacao').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function() {
                    return $(this).find('option:first').text();
                },
                allowClear: true,
                language: {
                    noResults: function() {
                        return "Nenhum resultado encontrado";
                    },
                    searching: function() {
                        return "Buscando...";
                    },
                    inputTooShort: function() {
                        return "Digite para buscar";
                    }
                }
            });

            // Adiciona efeito de ondulação nos cards
            document.querySelectorAll('.report-type-card').forEach(card => {
                card.addEventListener('click', function (e) {
                    document.querySelectorAll('.report-type-card').forEach(c => c.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // Marcar o primeiro como ativo
            document.querySelector('.report-type-card[data-type="desfiliacoes"]').classList.add('active');
        });

        // Função para limpar filtros
        function limparFiltros() {
            $('#formFiltros')[0].reset();
            
            // Limpar Select2
            $('#corporacao, #patente, #lotacao').val(null).trigger('change');
            
            // Resetar datas para o padrão
            $('#dataInicio').val(new Date().toISOString().slice(0, 8) + '01');
            $('#dataFim').val(new Date().toISOString().slice(0, 10));
            
            showToast('Filtros limpos!', 'info');
        }

        // Selecionar tipo de relatório
        function selecionarRelatorio(tipo) {
            currentReportType = tipo;
            currentPage = 1;
            document.getElementById('tipoRelatorio').value = tipo;

            // Atualizar visual dos cards
            document.querySelectorAll('.report-type-card').forEach(card => {
                card.classList.remove('active');
                if (card.dataset.type === tipo) {
                    card.classList.add('active');
                }
            });

            ajustarFiltros(tipo);
            showToast(`Relatório de ${getNomeTipoRelatorio(tipo)} selecionado`, 'info');
        }

        // Ajustar filtros baseado no tipo de relatório
        function ajustarFiltros(tipo) {
            if (tipo === 'aniversariantes') {
                document.getElementById('dataInicio').value = new Date().toISOString().slice(0, 8) + '01';
                document.getElementById('dataFim').value = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10);
            } else {
                document.getElementById('dataInicio').value = new Date().toISOString().slice(0, 8) + '01';
                document.getElementById('dataFim').value = new Date().toISOString().slice(0, 10);
            }
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

        // Gerar relatório com paginação
        async function gerarRelatorio(pagina = 1) {
            currentPage = pagina;
            const formData = new FormData(document.getElementById('formFiltros'));

            // Adicionar parâmetros de paginação
            const params = new URLSearchParams(formData);
            params.append('pagina', pagina);
            params.append('registros_por_pagina', registrosPorPagina);

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

            try {
                const response = await fetch(`../api/relatorios/gerar_relatorio_comercial.php?${params.toString()}`);
                const text = await response.text();
                let data;

                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Resposta não é JSON:', text);
                    throw new Error('Erro ao processar resposta do servidor');
                }

                if (!response.ok) {
                    throw new Error(data.message || `Erro HTTP: ${response.status}`);
                }

                if (data.success) {
                    currentReportData = data;
                    renderizarRelatorio(data);
                    document.getElementById('exportButtons').style.display = 'flex';
                    showToast('Relatório gerado com sucesso!', 'success');
                } else {
                    mostrarErro(data.message || 'Erro ao gerar relatório');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarErro(error.message || 'Erro ao processar relatório. Verifique sua conexão.');
            } finally {
                document.getElementById('loadingContainer').style.display = 'none';
            }
        }

        // Renderizar relatório com controles de paginação
        function renderizarRelatorio(response) {
            const container = document.getElementById('resultsContainer');
            const data = response.data || [];
            const paginacao = response.paginacao;

            // Atualizar título
            document.getElementById('tituloResultado').textContent = `${getNomeTipoRelatorio(currentReportType)} - Resultados`;

            // Atualizar contador com informações de paginação
            if (paginacao) {
                document.getElementById('totalRegistros').innerHTML = `
                    ${paginacao.registros_inicio}-${paginacao.registros_fim} de ${paginacao.total_registros}
                `;
                document.getElementById('totalRegistros').style.display = 'inline-block';
                totalPages = paginacao.total_paginas;
            } else {
                document.getElementById('totalRegistros').textContent = data.length;
                document.getElementById('totalRegistros').style.display = 'inline-block';
            }

            if (data.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Nenhum registro encontrado com os filtros aplicados.
                        <br><small class="text-muted mt-2">
                            Tente ajustar os filtros ou verificar se os dados existem no período selecionado.
                        </small>
                    </div>
                `;
                return;
            }

            // Criar tabela HTML
            let tableHTML = '<div class="table-responsive"><table id="reportTable" class="table table-striped table-hover">';

            // Headers baseados no tipo de relatório
            const headers = getTableHeaders(currentReportType);
            tableHTML += '<thead class="table-dark"><tr>';

            // Adicionar coluna de número
            tableHTML += '<th width="50">#</th>';

            headers.forEach(header => {
                tableHTML += `<th>${header.label}</th>`;
            });
            tableHTML += '</tr></thead><tbody>';

            // Dados com numeração
            const startIndex = paginacao ? paginacao.registros_inicio : 1;
            data.forEach((row, index) => {
                tableHTML += '<tr>';
                tableHTML += `<td>${startIndex + index}</td>`;
                headers.forEach(header => {
                    const value = row[header.key] || '';
                    tableHTML += `<td>${formatValue(value, header.type)}</td>`;
                });
                tableHTML += '</tr>';
            });

            tableHTML += '</tbody></table></div>';

            // Adicionar controles de paginação
            if (paginacao && paginacao.total_paginas > 1) {
                tableHTML += renderizarPaginacao(paginacao);
            }

            container.innerHTML = tableHTML;

            // Inicializar DataTable sem paginação própria
            if (dataTable) {
                dataTable.destroy();
            }

            dataTable = $('#reportTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                paging: false, // Desabilitar paginação do DataTable
                info: false,   // Desabilitar info do DataTable
                responsive: true,
                order: [[1, 'asc']], // Ordenar pela segunda coluna (primeira é o número)
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Copiar',
                        className: 'btn btn-sm btn-secondary'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-sm btn-secondary'
                    }
                ]
            });
        }

        // Renderizar controles de paginação
        function renderizarPaginacao(paginacao) {
            let paginationHTML = `
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="pagination-info">
                        <select id="registrosPorPagina" class="form-select form-select-sm" style="width: auto; display: inline-block;" onchange="mudarRegistrosPorPagina(this.value)">
                            <option value="25" ${registrosPorPagina == 25 ? 'selected' : ''}>25 por página</option>
                            <option value="50" ${registrosPorPagina == 50 ? 'selected' : ''}>50 por página</option>
                            <option value="100" ${registrosPorPagina == 100 ? 'selected' : ''}>100 por página</option>
                        </select>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
            `;

            // Botão Primeira página
            if (paginacao.pagina_atual > 1) {
                paginationHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="gerarRelatorio(1); return false;">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                `;
            }

            // Botão Anterior
            if (paginacao.pagina_atual > 1) {
                paginationHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="gerarRelatorio(${paginacao.pagina_atual - 1}); return false;">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                `;
            }

            // Páginas numeradas
            let startPage = Math.max(1, paginacao.pagina_atual - 2);
            let endPage = Math.min(paginacao.total_paginas, startPage + 4);

            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }

            for (let i = startPage; i <= endPage; i++) {
                const active = i === paginacao.pagina_atual ? 'active' : '';
                paginationHTML += `
                    <li class="page-item ${active}">
                        <a class="page-link" href="#" onclick="gerarRelatorio(${i}); return false;">${i}</a>
                    </li>
                `;
            }

            // Botão Próxima
            if (paginacao.pagina_atual < paginacao.total_paginas) {
                paginationHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="gerarRelatorio(${paginacao.pagina_atual + 1}); return false;">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                `;
            }

            // Botão Última página
            if (paginacao.pagina_atual < paginacao.total_paginas) {
                paginationHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="gerarRelatorio(${paginacao.total_paginas}); return false;">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                `;
            }

            paginationHTML += `
                        </ul>
                    </nav>
                </div>
            `;

            return paginationHTML;
        }

        // Mudar quantidade de registros por página
        function mudarRegistrosPorPagina(valor) {
            registrosPorPagina = parseInt(valor);
            currentPage = 1;
            gerarRelatorio(1);
        }

        // Obter headers da tabela baseado no tipo
        function getTableHeaders(tipo) {
            const headers = {
                'desfiliacoes': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'rg', label: 'RG', type: 'text' },
                    { key: 'cpf', label: 'CPF', type: 'cpf' },
                    { key: 'telefone', label: 'Telefone', type: 'phone' },
                    { key: 'email', label: 'E-mail', type: 'text' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'lotacao', label: 'Lotação', type: 'text' },
                    { key: 'data_desfiliacao', label: 'Data Desfiliação', type: 'date' }
                ],
                'indicacoes': [
                    { key: 'indicador', label: 'Nome do Indicador', type: 'text' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'total_indicacoes', label: 'Total Indicações', type: 'number' },
                    { key: 'indicacoes_periodo', label: 'Indicações no Período', type: 'number' },
                    { key: 'ultima_indicacao', label: 'Última Indicação', type: 'date' }
                ],
                'aniversariantes': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'data_nascimento', label: 'Data Nascimento', type: 'date' },
                    { key: 'idade', label: 'Idade', type: 'number' },
                    { key: 'dia_aniversario', label: 'Dia', type: 'number' },
                    { key: 'mes_aniversario', label: 'Mês', type: 'number' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'lotacao', label: 'Lotação', type: 'text' },
                    { key: 'telefone', label: 'Telefone', type: 'phone' },
                    { key: 'email', label: 'E-mail', type: 'text' }
                ],
                'novos_cadastros': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'rg', label: 'RG', type: 'text' },
                    { key: 'cpf', label: 'CPF', type: 'cpf' },
                    { key: 'telefone', label: 'Telefone', type: 'phone' },
                    { key: 'email', label: 'E-mail', type: 'text' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'lotacao', label: 'Lotação', type: 'text' },
                    { key: 'data_aprovacao', label: 'Data Cadastro', type: 'date' },
                    { key: 'indicacao', label: 'Indicado por', type: 'text' },
                    { key: 'tipo_cadastro', label: 'Tipo Cadastro', type: 'text' }
                ]
            };

            return headers[tipo] || [];
        }

        // Formatar valores para exibição
        function formatValue(value, type) {
            if (!value || value === null || value === undefined || value === '') {
                return '-';
            }

            switch (type) {
                case 'date':
                    return formatDate(value);
                case 'cpf':
                    return formatCPF(value);
                case 'phone':
                    return formatPhone(value);
                case 'currency':
                    return formatCurrency(value);
                case 'percent':
                    return value + '%';
                case 'number':
                    return parseInt(value).toLocaleString('pt-BR');
                default:
                    return value;
            }
        }

        // Formatar data
        function formatDate(date) {
            if (!date || date === null || date === undefined || date === '') {
                return '-';
            }

            date = date.toString().trim();

            if (date === '0000-00-00' || date === '0000-00-00 00:00:00') {
                return '-';
            }

            try {
                let dateObj;

                if (date.includes('T')) {
                    dateObj = new Date(date);
                } else if (date.includes(' ')) {
                    date = date.replace(' ', 'T');
                    dateObj = new Date(date);
                } else {
                    dateObj = new Date(date + 'T00:00:00');
                }

                if (isNaN(dateObj.getTime())) {
                    return '-';
                }

                const dia = String(dateObj.getDate()).padStart(2, '0');
                const mes = String(dateObj.getMonth() + 1).padStart(2, '0');
                const ano = dateObj.getFullYear();

                if (ano < 1900 || ano > 2100) {
                    return '-';
                }

                return `${dia}/${mes}/${ano}`;

            } catch (e) {
                console.error('Erro ao formatar data:', date, e);
                return '-';
            }
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

        // Formatar moeda
        function formatCurrency(value) {
            if (!value || value === 0) return 'R$ 0,00';
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        }

        // Exportar relatório
        async function exportarRelatorio(formato) {
            if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
                showToast('Nenhum relatório gerado para exportar', 'warning');
                return;
            }

            showToast(`Preparando exportação completa em formato ${formato.toUpperCase()}...`, 'info');

            // Para exportação, buscar TODOS os registros (sem paginação)
            const formData = new FormData(document.getElementById('formFiltros'));
            const params = new URLSearchParams(formData);
            params.append('pagina', 1);
            params.append('registros_por_pagina', 999999); // Buscar todos

            try {
                const response = await fetch(`../api/relatorios/gerar_relatorio_comercial.php?${params.toString()}`);
                const data = await response.json();

                if (data.success) {
                    // Criar form para download
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../api/relatorios/exportar_relatorio.php';
                    form.target = '_blank';

                    const campos = {
                        tipo: currentReportType,
                        formato: formato,
                        data: JSON.stringify(data.data),
                        filtros: JSON.stringify(Object.fromEntries(new FormData(document.getElementById('formFiltros'))))
                    };

                    for (let [key, value] of Object.entries(campos)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }

                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);

                    setTimeout(() => {
                        showToast(`Arquivo ${formato.toUpperCase()} com todos os ${data.paginacao.total_registros} registros gerado com sucesso!`, 'success');
                    }, 1000);
                }
            } catch (error) {
                showToast('Erro ao preparar exportação', 'danger');
            }
        }

        // Mostrar erro
        function mostrarErro(mensagem) {
            document.getElementById('resultsContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Erro:</strong> ${mensagem}
                    <br><small class="text-muted">Verifique os filtros selecionados e tente novamente.</small>
                </div>
            `;
            showToast(mensagem, 'danger');
        }

        // Sistema de Toast
        function showToast(message, type = 'success') {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${getToastIcon(type)} me-2"></i>
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
                autohide: true,
                delay: type === 'danger' ? 5000 : 3000
            });
            toast.show();

            setTimeout(() => {
                if (container.lastElementChild) {
                    container.lastElementChild.remove();
                }
            }, type === 'danger' ? 6000 : 4000);
        }

        // Obter ícone para toast
        function getToastIcon(type) {
            const icons = {
                'success': 'check-circle',
                'danger': 'times-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle',
                'primary': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }
    </script>
</body>

</html>