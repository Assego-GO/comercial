<?php
/**
 * Página de Gestão de Funcionários
 * pages/funcionarios.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

// Verifica se é diretor
if (!$auth->isDiretor()) {
    header('Location: ../pages/dashboard.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Funcionários - ASSEGO';

// Inicializa classe de funcionários
$funcionarios = new Funcionarios();

// Busca estatísticas
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Total de funcionários
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Funcionarios");
    $stmt->execute();
    $totalFuncionarios = $stmt->fetch()['total'] ?? 0;
    
    // Funcionários ativos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Funcionarios WHERE ativo = 1");
    $stmt->execute();
    $funcionariosAtivos = $stmt->fetch()['total'] ?? 0;
    
    // Funcionários inativos
    $funcionariosInativos = $totalFuncionarios - $funcionariosAtivos;
    
    // Novos funcionários (últimos 30 dias)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM Funcionarios 
        WHERE criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $novosFuncionarios = $stmt->fetch()['total'] ?? 0;
    
    // Total de departamentos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Departamentos WHERE ativo = 1");
    $stmt->execute();
    $totalDepartamentos = $stmt->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $totalFuncionarios = $funcionariosAtivos = $funcionariosInativos = $novosFuncionarios = $totalDepartamentos = 0;
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

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Custom CSS (reutilizando estilos do dashboard) -->
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

            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.24);
            --shadow-lg: 0 10px 20px rgba(0, 0, 0, 0.12), 0 4px 8px rgba(0, 0, 0, 0.24);
            --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.12), 0 8px 16px rgba(0, 0, 0, 0.24);
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

        /* Reutiliza estilos do dashboard */
        .main-wrapper {
            min-height: 100vh;
            background: var(--gray-100);
        }

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

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: var(--gray-100);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            color: var(--gray-600);
        }

        .header-btn:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: var(--gray-100);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .user-menu:hover {
            background: var(--gray-200);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: var(--white);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--dark);
            margin: 0;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin: 0;
        }

        /* Navigation Tabs */
        .nav-tabs-container {
            background: var(--white);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: var(--header-height);
            z-index: 99;
            border-bottom: 1px solid var(--gray-200);
        }

        .nav-tabs-modern {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 2rem;
            margin: 0;
            list-style: none;
            gap: 1rem;
        }

        .nav-tab-item {
            margin: 0;
        }

        .nav-tab-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 2rem;
            color: var(--gray-600);
            text-decoration: none;
            border: none;
            background: var(--gray-100);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 12px;
            min-width: 120px;
        }

        .nav-tab-link:hover {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .nav-tab-link.active {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.25);
        }

        .nav-tab-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            margin-bottom: 0.375rem;
            transition: all 0.3s ease;
        }

        .nav-tab-link.active .nav-tab-icon {
            color: var(--white);
        }

        .nav-tab-text {
            font-weight: 600;
            font-size: 0.8125rem;
            transition: all 0.3s ease;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.25rem;
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
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
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
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }

        .stat-icon.primary {
            background: var(--primary-light);
            color: var(--primary);
        }

        .stat-icon.success {
            background: rgba(0, 200, 83, 0.1);
            color: var(--success);
        }

        .stat-icon.danger {
            background: rgba(255, 59, 48, 0.1);
            color: var(--danger);
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
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.375rem;
        }

        .stat-label {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-bottom: 0.375rem;
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
        }

        .stat-change.positive {
            color: var(--success);
            background: rgba(0, 200, 83, 0.1);
        }

        .stat-change.negative {
            color: var(--danger);
            background: rgba(255, 59, 48, 0.1);
        }

        /* Actions Bar */
        .actions-bar {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 0.625rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
            padding-left: 0.5rem;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23636366' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--white);
        }

        .search-box {
            position: relative;
            flex: 2;
            min-width: 300px;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
        }

        .actions-row {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 2px 8px rgba(0, 86, 210, 0.25);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        /* Table Container */
        .table-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .table-info {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        /* Custom Table */
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .modern-table thead th {
            background: var(--gray-100);
            padding: 0.875rem 1.25rem;
            font-weight: 600;
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        .modern-table tbody td {
            padding: 0.875rem 1.25rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
        }

        .modern-table tbody tr {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .modern-table tbody tr:hover {
            background: var(--gray-100);
            transform: translateX(4px);
        }

        /* Avatar com foto */
        .table-avatar {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 600;
            font-size: 0.875rem;
            overflow: hidden;
            position: relative;
        }

        .table-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .table-avatar span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: #e8f5e9;
            color: var(--success);
        }

        .status-badge.inactive {
            background: #ffebee;
            color: var(--danger);
        }

        .status-badge i {
            font-size: 0.5rem;
        }

        /* Badge de cargo */
        .cargo-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .cargo-badge.diretor {
            background: rgba(255, 184, 0, 0.1);
            color: var(--warning);
        }

        .cargo-badge.gerente {
            background: rgba(0, 184, 212, 0.1);
            color: var(--info);
        }

        /* Badges de conquistas */
        .badges-list {
            display: flex;
            gap: 0.375rem;
            align-items: center;
        }

        .mini-badge {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.625rem;
            background: var(--gray-100);
            color: var(--gray-600);
            position: relative;
            transition: all 0.2s ease;
        }

        .mini-badge:hover {
            transform: scale(1.1);
            z-index: 1;
        }

        .mini-badge.gold {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: var(--dark);
        }

        .mini-badge.silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: var(--dark);
        }

        .mini-badge.bronze {
            background: linear-gradient(135deg, #cd7f32, #e2a76f);
            color: var(--white);
        }

        .badge-count {
            font-size: 0.625rem;
            color: var(--gray-500);
            margin-left: 0.25rem;
        }

        .action-buttons-table {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .btn-icon:hover {
            background: var(--gray-100);
        }

        .btn-icon.view:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn-icon.edit:hover {
            background: rgba(0, 184, 212, 0.1);
            color: var(--info);
        }

        .btn-icon.delete:hover {
            background: rgba(255, 59, 48, 0.1);
            color: var(--danger);
        }

        /* Dropdown Menu */
        .dropdown-menu-custom {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            padding: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .dropdown-menu-custom.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item-custom {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dropdown-item-custom:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .dropdown-divider-custom {
            height: 1px;
            background: var(--gray-200);
            margin: 0.5rem 0;
        }

        /* Loading */
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

        /* Modal Customizado */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        .modal-custom.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-content-custom {
            background: var(--white);
            border-radius: 24px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header-custom {
            padding: 1.5rem 2rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title-custom {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .modal-close-custom {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--gray-600);
        }

        .modal-close-custom:hover {
            background: var(--gray-200);
            color: var(--dark);
        }

        .modal-body-custom {
            padding: 2rem;
            overflow-y: auto;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-label span {
            color: var(--danger);
        }

        .form-control-custom {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
        }

        .form-select-custom {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23636366' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .form-switch {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .switch-input {
            width: 48px;
            height: 24px;
            appearance: none;
            background: var(--gray-300);
            border-radius: 24px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .switch-input::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--white);
            top: 2px;
            left: 2px;
            transition: all 0.3s ease;
        }

        .switch-input:checked {
            background: var(--success);
        }

        .switch-input:checked::before {
            transform: translateX(24px);
        }

        .switch-label {
            font-size: 0.875rem;
            color: var(--gray-700);
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-tabs-modern {
                overflow-x: auto;
                justify-content: flex-start;
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .search-box {
                min-width: 100%;
            }

            .user-info {
                display: none;
            }

            .table-container {
                overflow-x: auto;
            }

            .modern-table {
                min-width: 800px;
            }

            .modal-content-custom {
                max-width: 100%;
                margin: 1rem;
            }
        }

        /* Estilos do Modal de Visualização */
        .modal-avatar-view {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .view-tabs {
            display: flex;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
        }

        .view-tab {
            flex: 1;
            padding: 1rem;
            background: transparent;
            border: none;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
        }

        .view-tab:hover {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .view-tab.active {
            color: var(--primary);
            background: var(--white);
        }

        .view-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
        }

        .view-content {
            padding: 2rem;
        }

        .view-tab-content {
            display: none;
        }

        .view-tab-content.active {
            display: block;
        }

        .info-section {
            margin-bottom: 2rem;
        }

        .info-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding: 1.5rem;
            background: var(--gray-100);
            border-radius: 12px;
        }

        .stat-summary-item {
            text-align: center;
        }

        .stat-summary-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-summary-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badges-container, .contribuicoes-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }

        .badge-card {
            background: var(--gray-100);
            padding: 1.25rem;
            border-radius: 12px;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .badge-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .badge-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .badge-icon-wrapper.gold {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: var(--dark);
        }

        .badge-icon-wrapper.silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: var(--dark);
        }

        .badge-icon-wrapper.bronze {
            background: linear-gradient(135deg, #cd7f32, #e2a76f);
            color: var(--white);
        }

        .badge-content {
            flex: 1;
        }

        .badge-name {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .badge-description {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .badge-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .contribuicao-card {
            background: var(--gray-100);
            padding: 1.25rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .contribuicao-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .contribuicao-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .contribuicao-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .contribuicao-tipo {
            font-size: 0.625rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .contribuicao-description {
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: 0.75rem;
            line-height: 1.5;
        }

        .contribuicao-dates {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .modal-footer-custom {
            padding: 1.5rem 2rem;
            background: var(--gray-100);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando dados...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
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

            <div class="header-right">
                <button class="header-btn">
                    <i class="fas fa-search"></i>
                </button>
                <button class="header-btn">
                    <i class="fas fa-bell"></i>
                </button>
                <div class="user-menu" id="userMenu">
                    <div class="user-info">
                        <p class="user-name"><?php echo htmlspecialchars($usuarioLogado['nome']); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'Funcionário'); ?></p>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($usuarioLogado['nome'], 0, 1)); ?>
                    </div>
                    <i class="fas fa-chevron-down ms-2" style="font-size: 0.75rem; color: var(--gray-500);"></i>

                    <!-- Dropdown Menu -->
                    <div class="dropdown-menu-custom" id="userDropdown">
                        <a href="perfil.php" class="dropdown-item-custom">
                            <i class="fas fa-user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        <a href="configuracoes.php" class="dropdown-item-custom">
                            <i class="fas fa-cog"></i>
                            <span>Configurações</span>
                        </a>
                        <div class="dropdown-divider-custom"></div>
                        <a href="logout.php" class="dropdown-item-custom">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="nav-tabs-container">
            <ul class="nav-tabs-modern">
                <li class="nav-tab-item">
                    <a href="dashboard.php" class="nav-tab-link">
                        <div class="nav-tab-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="nav-tab-text">Associados</span>
                    </a>
                </li>
                <li class="nav-tab-item">
                    <a href="funcionarios.php" class="nav-tab-link active">
                        <div class="nav-tab-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <span class="nav-tab-text">Funcionários</span>
                    </a>
                </li>
                <li class="nav-tab-item">
                    <a href="relatorios.php" class="nav-tab-link">
                        <div class="nav-tab-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span class="nav-tab-text">Relatórios</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header mb-4" data-aos="fade-right">
                <h1 class="page-title">Gestão de Funcionários</h1>
                <p class="page-subtitle">Gerencie os funcionários e departamentos do sistema</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalFuncionarios, 0, ',', '.'); ?></div>
                            <div class="stat-label">Total de Funcionários</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                12% este mês
                            </div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($funcionariosAtivos, 0, ',', '.'); ?></div>
                            <div class="stat-label">Funcionários Ativos</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                5% este mês
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($funcionariosInativos, 0, ',', '.'); ?></div>
                            <div class="stat-label">Inativos</div>
                            <div class="stat-change negative">
                                <i class="fas fa-arrow-down"></i>
                                2% este mês
                            </div>
                        </div>
                        <div class="stat-icon danger">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalDepartamentos, 0, ',', '.'); ?></div>
                            <div class="stat-label">Departamentos</div>
                            <div class="stat-change positive">
                                <i class="fas fa-plus"></i>
                                +1 este mês
                            </div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($novosFuncionarios, 0, ',', '.'); ?></div>
                            <div class="stat-label">Novos (30 dias)</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                15% este mês
                            </div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions Bar with Filters -->
            <div class="actions-bar" data-aos="fade-up" data-aos-delay="100">
                <div class="filters-row">
                    <div class="search-box">
                        <label class="filter-label">Buscar</label>
                        <div style="position: relative;">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="search-input" id="searchInput" 
                                   placeholder="Buscar por nome, email ou cargo...">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="filterStatus">
                            <option value="">Todos</option>
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Departamento</label>
                        <select class="filter-select" id="filterDepartamento">
                            <option value="">Todos</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Cargo</label>
                        <select class="filter-select" id="filterCargo">
                            <option value="">Todos</option>
                            <option value="Diretor">Diretor</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Analista">Analista</option>
                            <option value="Assistente">Assistente</option>
                        </select>
                    </div>
                </div>

                <div class="actions-row">
                    <button class="btn-modern btn-secondary" onclick="limparFiltros()">
                        <i class="fas fa-eraser"></i>
                        Limpar Filtros
                    </button>
                    <button class="btn-modern btn-secondary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt"></i>
                        Atualizar
                    </button>
                    <button class="btn-modern btn-primary" onclick="abrirModalNovo()">
                        <i class="fas fa-plus"></i>
                        Novo Funcionário
                    </button>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container" data-aos="fade-up" data-aos-delay="200">
                <div class="table-header">
                    <h3 class="table-title">Lista de Funcionários</h3>
                    <span class="table-info">Mostrando <span id="showingCount">0</span> registros</span>
                </div>

                <div class="table-responsive p-2">
                    <table class="modern-table" id="funcionariosTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Foto</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Departamento</th>
                                <th>Cargo</th>
                                <th>Badges</th>
                                <th>Status</th>
                                <th>Data Cadastro</th>
                                <th style="width: 120px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center">
                                        <div class="loading-spinner mb-3"></div>
                                        <p class="text-muted mb-0">Carregando funcionários...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Novo/Editar Funcionário -->
    <div class="modal-custom" id="modalFuncionario">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom" id="modalTitle">Novo Funcionário</h2>
                <button class="modal-close-custom" onclick="fecharModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formFuncionario">
                    <input type="hidden" id="funcionarioId" name="id">
                    
                    <div class="form-group">
                        <label class="form-label">Nome Completo <span>*</span></label>
                        <input type="text" class="form-control-custom" id="nome" name="nome" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email <span>*</span></label>
                        <input type="email" class="form-control-custom" id="email" name="email" required>
                        <div class="form-text">Este email será usado para login no sistema</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Senha</label>
                        <input type="password" class="form-control-custom" id="senha" name="senha" readonly>
                        <div class="form-text">
                            <span id="senhaInfo">Senha padrão: Assego@123</span>
                            <span id="senhaEditInfo" style="display: none;">Deixe em branco para manter a senha atual.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Departamento</label>
                        <select class="form-control-custom form-select-custom" id="departamento_id" name="departamento_id">
                            <option value="">Selecione um departamento</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cargo</label>
                        <select class="form-control-custom form-select-custom" id="cargo" name="cargo">
                            <option value="">Selecione um cargo</option>
                            <option value="Diretor">Diretor</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Analista">Analista</option>
                            <option value="Assistente">Assistente</option>
                            <option value="Coordenador">Coordenador</option>
                            <option value="Auxiliar">Auxiliar</option>
                            <option value="Estagiário">Estagiário</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">CPF</label>
                        <input type="text" class="form-control-custom" id="cpf" name="cpf" 
                               placeholder="000.000.000-00" maxlength="14">
                    </div>

                    <div class="form-group">
                        <label class="form-label">RG</label>
                        <input type="text" class="form-control-custom" id="rg" name="rg">
                    </div>

                    <div class="form-group">
                        <div class="form-switch">
                            <input type="checkbox" class="switch-input" id="ativo" name="ativo" checked>
                            <label class="switch-label" for="ativo">Funcionário ativo</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModal()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-save"></i>
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização do Funcionário -->
    <div class="modal-custom" id="modalVisualizacao">
        <div class="modal-content-custom" style="max-width: 700px;">
            <div class="modal-header-custom" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: var(--white);">
                <div class="d-flex align-items-center gap-3">
                    <div class="modal-avatar-view" id="avatarView">
                        <span>?</span>
                    </div>
                    <div>
                        <h2 class="modal-title-custom mb-1" style="color: var(--white);" id="nomeView">Carregando...</h2>
                        <div class="d-flex align-items-center gap-3" style="font-size: 0.875rem; opacity: 0.9;">
                            <span id="cargoView">-</span>
                            <span>•</span>
                            <span id="departamentoView">-</span>
                        </div>
                    </div>
                </div>
                <button class="modal-close-custom" style="color: var(--white); border-color: rgba(255,255,255,0.3);" onclick="fecharModalVisualizacao()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom p-0">
                <!-- Tabs de navegação -->
                <div class="view-tabs">
                    <button class="view-tab active" onclick="abrirTabView('dados')">
                        <i class="fas fa-user"></i>
                        Dados Pessoais
                    </button>
                    <button class="view-tab" onclick="abrirTabView('badges')">
                        <i class="fas fa-medal"></i>
                        Badges e Conquistas
                    </button>
                    <button class="view-tab" onclick="abrirTabView('contribuicoes')">
                        <i class="fas fa-project-diagram"></i>
                        Contribuições
                    </button>
                </div>

                <!-- Conteúdo das tabs -->
                <div class="view-content">
                    <!-- Tab Dados Pessoais -->
                    <div id="dados-tab" class="view-tab-content active">
                        <div class="info-section">
                            <h4 class="info-title">
                                <i class="fas fa-info-circle"></i>
                                Informações Gerais
                            </h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Email</span>
                                    <span class="info-value" id="emailView">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="info-value" id="statusView">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">CPF</span>
                                    <span class="info-value" id="cpfView">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">RG</span>
                                    <span class="info-value" id="rgView">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Data de Cadastro</span>
                                    <span class="info-value" id="dataCadastroView">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Última Atualização de Senha</span>
                                    <span class="info-value" id="senhaAlteradaView">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="stats-summary">
                            <div class="stat-summary-item">
                                <div class="stat-summary-value" id="totalBadgesView">0</div>
                                <div class="stat-summary-label">Badges</div>
                            </div>
                            <div class="stat-summary-item">
                                <div class="stat-summary-value" id="totalPontosView">0</div>
                                <div class="stat-summary-label">Pontos</div>
                            </div>
                            <div class="stat-summary-item">
                                <div class="stat-summary-value" id="totalContribuicoesView">0</div>
                                <div class="stat-summary-label">Contribuições</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Badges -->
                    <div id="badges-tab" class="view-tab-content">
                        <div class="badges-container" id="badgesContainer">
                            <div class="empty-state">
                                <i class="fas fa-medal"></i>
                                <p>Nenhuma badge conquistada ainda</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Contribuições -->
                    <div id="contribuicoes-tab" class="view-tab-content">
                        <div class="contribuicoes-container" id="contribuicoesContainer">
                            <div class="empty-state">
                                <i class="fas fa-project-diagram"></i>
                                <p>Nenhuma contribuição registrada</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer com ações -->
                <div class="modal-footer-custom">
                    <button class="btn-modern btn-secondary" onclick="fecharModalVisualizacao()">
                        Fechar
                    </button>
                    <button class="btn-modern btn-primary" onclick="editarDoVisualizacao()">
                        <i class="fas fa-edit"></i>
                        Editar Funcionário
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais
        let todosFuncionarios = [];
        let funcionariosFiltrados = [];
        let departamentosDisponiveis = [];

        // User Dropdown Menu
        document.addEventListener('DOMContentLoaded', function() {
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');

            if (userMenu && userDropdown) {
                userMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                document.addEventListener('click', function() {
                    userDropdown.classList.remove('show');
                });
            }

            // Máscaras
            $('#cpf').mask('000.000.000-00');

            // Event listeners
            document.getElementById('searchInput').addEventListener('input', aplicarFiltros);
            document.getElementById('filterStatus').addEventListener('change', aplicarFiltros);
            document.getElementById('filterDepartamento').addEventListener('change', aplicarFiltros);
            document.getElementById('filterCargo').addEventListener('change', aplicarFiltros);

            // Form submit
            document.getElementById('formFuncionario').addEventListener('submit', salvarFuncionario);

            // Carrega dados
            carregarFuncionarios();
            carregarDepartamentos();
        });

        // Loading functions
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Carrega lista de funcionários
        function carregarFuncionarios() {
            showLoading();

            $.ajax({
                url: '../api/funcionarios_listar.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        todosFuncionarios = response.funcionarios;
                        funcionariosFiltrados = [...todosFuncionarios];
                        renderizarTabela();
                    } else {
                        console.error('Erro ao carregar funcionários:', response);
                        alert('Erro ao carregar dados');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao carregar funcionários');
                }
            });
        }

        // Carrega departamentos
        function carregarDepartamentos() {
            $.ajax({
                url: '../api/departamentos_listar.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        departamentosDisponiveis = response.departamentos;
                        preencherSelectDepartamentos();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao carregar departamentos:', error);
                }
            });
        }

        // Preenche select de departamentos
        function preencherSelectDepartamentos() {
            const selectFilter = document.getElementById('filterDepartamento');
            const selectForm = document.getElementById('departamento_id');
            
            selectFilter.innerHTML = '<option value="">Todos</option>';
            selectForm.innerHTML = '<option value="">Selecione um departamento</option>';
            
            departamentosDisponiveis.forEach(dep => {
                const optionFilter = document.createElement('option');
                optionFilter.value = dep.id;
                optionFilter.textContent = dep.nome;
                selectFilter.appendChild(optionFilter);
                
                const optionForm = document.createElement('option');
                optionForm.value = dep.id;
                optionForm.textContent = dep.nome;
                selectForm.appendChild(optionForm);
            });
        }

        // Renderiza tabela
        function renderizarTabela() {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';
            
            if (funcionariosFiltrados.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="d-flex flex-column align-items-center">
                                <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                                <p class="text-muted mb-0">Nenhum funcionário encontrado</p>
                            </div>
                        </td>
                    </tr>
                `;
                document.getElementById('showingCount').textContent = '0';
                return;
            }
            
            funcionariosFiltrados.forEach(funcionario => {
                const statusBadge = funcionario.ativo == 1
                    ? '<span class="status-badge active"><i class="fas fa-check-circle"></i> Ativo</span>'
                    : '<span class="status-badge inactive"><i class="fas fa-times-circle"></i> Inativo</span>';
                
                // Cargo badge
                let cargoBadge = `<span class="cargo-badge">${funcionario.cargo || 'Sem cargo'}</span>`;
                if (funcionario.cargo === 'Diretor') {
                    cargoBadge = `<span class="cargo-badge diretor"><i class="fas fa-crown"></i> Diretor</span>`;
                } else if (funcionario.cargo === 'Gerente') {
                    cargoBadge = `<span class="cargo-badge gerente"><i class="fas fa-user-tie"></i> Gerente</span>`;
                }
                
                // Badges
                let badgesHtml = '<div class="badges-list">';
                const totalBadges = funcionario.total_badges || 0;
                if (totalBadges > 0) {
                    badgesHtml += `
                        <span class="mini-badge gold" title="${totalBadges} badges">
                            <i class="fas fa-medal"></i>
                        </span>
                        <span class="badge-count">${totalBadges}</span>
                    `;
                } else {
                    badgesHtml += '<span class="text-muted small">-</span>';
                }
                badgesHtml += '</div>';
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="table-avatar">
                            <span>${funcionario.nome ? funcionario.nome.charAt(0).toUpperCase() : '?'}</span>
                        </div>
                    </td>
                    <td>
                        <span class="fw-semibold">${funcionario.nome}</span>
                        <br>
                        <small class="text-muted">ID: ${funcionario.id}</small>
                    </td>
                    <td>${funcionario.email}</td>
                    <td>${funcionario.departamento_nome || '-'}</td>
                    <td>${cargoBadge}</td>
                    <td>${badgesHtml}</td>
                    <td>${statusBadge}</td>
                    <td>${formatarData(funcionario.criado_em)}</td>
                    <td>
                        <div class="action-buttons-table">
                            <button class="btn-icon view" onclick="visualizarFuncionario(${funcionario.id})" title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-icon edit" onclick="editarFuncionario(${funcionario.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon delete" onclick="desativarFuncionario(${funcionario.id})" title="Desativar">
                                <i class="fas fa-ban"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            document.getElementById('showingCount').textContent = funcionariosFiltrados.length;
        }

        // Aplica filtros
        function aplicarFiltros() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterStatus = document.getElementById('filterStatus').value;
            const filterDepartamento = document.getElementById('filterDepartamento').value;
            const filterCargo = document.getElementById('filterCargo').value;
            
            funcionariosFiltrados = todosFuncionarios.filter(funcionario => {
                const matchSearch = !searchTerm || 
                    funcionario.nome.toLowerCase().includes(searchTerm) ||
                    funcionario.email.toLowerCase().includes(searchTerm) ||
                    (funcionario.cargo && funcionario.cargo.toLowerCase().includes(searchTerm));
                
                const matchStatus = !filterStatus || funcionario.ativo == filterStatus;
                const matchDepartamento = !filterDepartamento || funcionario.departamento_id == filterDepartamento;
                const matchCargo = !filterCargo || funcionario.cargo === filterCargo;
                
                return matchSearch && matchStatus && matchDepartamento && matchCargo;
            });
            
            renderizarTabela();
        }

        // Limpa filtros
        function limparFiltros() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterDepartamento').value = '';
            document.getElementById('filterCargo').value = '';
            
            funcionariosFiltrados = [...todosFuncionarios];
            renderizarTabela();
        }

        // Abre modal para novo funcionário
        function abrirModalNovo() {
            document.getElementById('modalTitle').textContent = 'Novo Funcionário';
            document.getElementById('formFuncionario').reset();
            document.getElementById('funcionarioId').value = '';
            
            // Para novo funcionário, define a senha padrão
            document.getElementById('senha').value = 'Assego@123';
            document.getElementById('senha').readOnly = true;
            document.getElementById('senhaInfo').style.display = 'inline';
            document.getElementById('senhaEditInfo').style.display = 'none';
            
            document.getElementById('modalFuncionario').classList.add('show');
        }

        // Edita funcionário
        function editarFuncionario(id) {
            showLoading();
            
            // Busca dados completos do funcionário
            $.ajax({
                url: '../api/funcionarios_detalhes.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        const funcionario = response.funcionario;
                        
                        // Debug para ver os dados retornados
                        console.log('Dados do funcionário:', funcionario);
                        console.log('Cargo retornado:', funcionario.cargo);
                        
                        // Preenche o formulário com todos os dados
                        document.getElementById('modalTitle').textContent = 'Editar Funcionário';
                        document.getElementById('funcionarioId').value = funcionario.id;
                        document.getElementById('nome').value = funcionario.nome;
                        document.getElementById('email').value = funcionario.email;
                        document.getElementById('departamento_id').value = funcionario.departamento_id || '';
                        
                        // Preenche o cargo com comparação flexível
                        const cargoSelect = document.getElementById('cargo');
                        const cargoValue = funcionario.cargo || '';
                        
                        // Limpa o select e adiciona as opções
                        cargoSelect.innerHTML = `
                            <option value="">Selecione um cargo</option>
                            <option value="Diretor">Diretor</option>
                            <option value="Gerente">Gerente</option>
                            <option value="Supervisor">Supervisor</option>
                            <option value="Analista">Analista</option>
                            <option value="Assistente">Assistente</option>
                            <option value="Coordenador">Coordenador</option>
                            <option value="Auxiliar">Auxiliar</option>
                            <option value="Estagiário">Estagiário</option>
                        `;
                        
                        // Se tem um cargo, tenta selecionar
                        if (cargoValue) {
                            // Primeiro tenta selecionar exatamente
                            cargoSelect.value = cargoValue;
                            
                            // Se não funcionou, tenta comparação case-insensitive
                            if (cargoSelect.value === '') {
                                const cargoLower = cargoValue.toLowerCase().trim();
                                for (let option of cargoSelect.options) {
                                    if (option.value.toLowerCase() === cargoLower) {
                                        cargoSelect.value = option.value;
                                        break;
                                    }
                                }
                            }
                            
                            // Se ainda não funcionou, adiciona como nova opção
                            if (cargoSelect.value === '' && cargoValue.trim() !== '') {
                                const newOption = document.createElement('option');
                                newOption.value = cargoValue;
                                newOption.textContent = cargoValue;
                                newOption.selected = true;
                                cargoSelect.appendChild(newOption);
                                console.log('Cargo personalizado adicionado:', cargoValue);
                            }
                        }
                        
                        document.getElementById('cpf').value = funcionario.cpf || '';
                        document.getElementById('rg').value = funcionario.rg || '';
                        document.getElementById('ativo').checked = funcionario.ativo == 1;
                        
                        // Senha não é obrigatória na edição
                        document.getElementById('senha').required = false;
                        document.getElementById('senha').value = '';
                        document.getElementById('senha').readOnly = false;
                        document.getElementById('senha').placeholder = 'Digite uma nova senha se desejar alterá-la';
                        document.getElementById('senhaInfo').style.display = 'none';
                        document.getElementById('senhaEditInfo').style.display = 'inline';
                        
                        // Abre o modal
                        document.getElementById('modalFuncionario').classList.add('show');
                    } else {
                        alert('Erro ao buscar dados do funcionário');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao carregar dados do funcionário');
                }
            });
        }

        // Visualiza funcionário
        function visualizarFuncionario(id) {
            showLoading();
            
            $.ajax({
                url: '../api/funcionarios_detalhes.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        const funcionario = response.funcionario;
                        
                        // Atualizar header do modal
                        document.getElementById('avatarView').innerHTML = 
                            `<span>${funcionario.nome.charAt(0).toUpperCase()}</span>`;
                        document.getElementById('nomeView').textContent = funcionario.nome;
                        document.getElementById('cargoView').textContent = funcionario.cargo || 'Sem cargo';
                        document.getElementById('departamentoView').textContent = funcionario.departamento_nome || 'Sem departamento';
                        
                        // Atualizar dados pessoais
                        document.getElementById('emailView').textContent = funcionario.email;
                        document.getElementById('statusView').innerHTML = funcionario.ativo == 1 
                            ? '<span class="status-badge active"><i class="fas fa-check-circle"></i> Ativo</span>'
                            : '<span class="status-badge inactive"><i class="fas fa-times-circle"></i> Inativo</span>';
                        document.getElementById('cpfView').textContent = formatarCPF(funcionario.cpf);
                        document.getElementById('rgView').textContent = funcionario.rg || '-';
                        document.getElementById('dataCadastroView').textContent = formatarData(funcionario.criado_em);
                        document.getElementById('senhaAlteradaView').textContent = 
                            funcionario.senha_alterada_em ? formatarData(funcionario.senha_alterada_em) : 'Nunca alterada';
                        
                        // Atualizar estatísticas
                        const stats = funcionario.estatisticas || {};
                        document.getElementById('totalBadgesView').textContent = stats.total_badges || 0;
                        document.getElementById('totalPontosView').textContent = stats.total_pontos || 0;
                        document.getElementById('totalContribuicoesView').textContent = stats.total_contribuicoes || 0;
                        
                        // Atualizar badges
                        renderizarBadges(funcionario.badges || []);
                        
                        // Atualizar contribuições
                        renderizarContribuicoes(funcionario.contribuicoes || []);
                        
                        // Guardar ID para poder editar depois
                        document.getElementById('modalVisualizacao').setAttribute('data-funcionario-id', id);
                        
                        // Abrir modal
                        document.getElementById('modalVisualizacao').classList.add('show');
                        
                        // Voltar para primeira tab
                        abrirTabView('dados');
                    } else {
                        alert('Erro ao buscar detalhes do funcionário');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao buscar funcionário');
                }
            });
        }

        // Renderiza badges no modal
        function renderizarBadges(badges) {
            const container = document.getElementById('badgesContainer');
            
            if (badges.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-medal"></i>
                        <p>Nenhuma badge conquistada ainda</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            badges.forEach(badge => {
                const nivel = badge.badge_nivel || 'BRONZE';
                const corClass = nivel === 'OURO' ? 'gold' : nivel === 'PRATA' ? 'silver' : 'bronze';
                
                html += `
                    <div class="badge-card">
                        <div class="badge-icon-wrapper ${corClass}">
                            <i class="${badge.badge_icone || 'fas fa-award'}"></i>
                        </div>
                        <div class="badge-content">
                            <div class="badge-name">${badge.badge_nome}</div>
                            <div class="badge-description">${badge.badge_descricao || badge.tipo_descricao || ''}</div>
                            <div class="badge-meta">
                                <span><i class="fas fa-layer-group"></i> ${badge.categoria || 'Geral'}</span>
                                <span><i class="fas fa-star"></i> ${badge.pontos || 0} pontos</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Renderiza contribuições no modal
        function renderizarContribuicoes(contribuicoes) {
            const container = document.getElementById('contribuicoesContainer');
            
            if (contribuicoes.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-project-diagram"></i>
                        <p>Nenhuma contribuição registrada</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            contribuicoes.forEach(contrib => {
                html += `
                    <div class="contribuicao-card">
                        <div class="contribuicao-header">
                            <div>
                                <div class="contribuicao-title">${contrib.titulo}</div>
                                <span class="contribuicao-tipo">${contrib.tipo || 'PROJETO'}</span>
                            </div>
                        </div>
                        <div class="contribuicao-description">${contrib.descricao || 'Sem descrição'}</div>
                        <div class="contribuicao-dates">
                            <i class="fas fa-calendar"></i>
                            ${formatarData(contrib.data_inicio)} 
                            ${contrib.data_fim ? ' até ' + formatarData(contrib.data_fim) : ' - Em andamento'}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Alterna entre tabs do modal de visualização
        function abrirTabView(tab) {
            // Remove active de todas as tabs
            document.querySelectorAll('.view-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.view-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Adiciona active na tab selecionada
            const activeButton = document.querySelector(`.view-tab[onclick="abrirTabView('${tab}')"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }
            
            const activeContent = document.getElementById(`${tab}-tab`);
            if (activeContent) {
                activeContent.classList.add('active');
            }
        }

        // Fecha modal de visualização
        function fecharModalVisualizacao() {
            document.getElementById('modalVisualizacao').classList.remove('show');
        }

        // Abre edição a partir da visualização
        function editarDoVisualizacao() {
            const id = document.getElementById('modalVisualizacao').getAttribute('data-funcionario-id');
            fecharModalVisualizacao();
            setTimeout(() => {
                editarFuncionario(id);
            }, 300);
        }

        // Formata CPF
        function formatarCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        // Salva funcionário
        function salvarFuncionario(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {};
            
            // Converte FormData para objeto
            for (let [key, value] of formData.entries()) {
                dados[key] = value;
            }
            
            // Ajusta valor do checkbox
            dados.ativo = document.getElementById('ativo').checked ? 1 : 0;
            
            // Para novo funcionário, garante que a senha padrão seja enviada
            if (!dados.id) {
                dados.senha = 'Assego@123';
            } else {
                // Para edição, remove senha se estiver vazia
                if (!dados.senha) {
                    delete dados.senha;
                }
            }
            
            showLoading();
            
            const url = dados.id ? '../api/funcionarios_atualizar.php' : '../api/funcionarios_criar.php';
            const method = dados.id ? 'PUT' : 'POST';
            
            $.ajax({
                url: url,
                method: method,
                data: JSON.stringify(dados),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        if (!dados.id) {
                            alert('Funcionário criado com sucesso!\n\nSenha padrão: Assego@123\n\nOriente o funcionário a alterar a senha no primeiro acesso.');
                        } else {
                            alert('Funcionário atualizado com sucesso!');
                        }
                        fecharModal();
                        carregarFuncionarios();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', xhr.responseText);
                    alert('Erro ao salvar funcionário');
                }
            });
        }

        // Desativa funcionário
        function desativarFuncionario(id) {
            const funcionario = todosFuncionarios.find(f => f.id == id);
            if (!funcionario) return;
            
            const acao = funcionario.ativo == 1 ? 'desativar' : 'ativar';
            const confirmMsg = `Tem certeza que deseja ${acao} o funcionário ${funcionario.nome}?`;
            
            if (!confirm(confirmMsg)) return;
            
            showLoading();
            
            const dados = {
                id: id,
                ativo: funcionario.ativo == 1 ? 0 : 1
            };
            
            $.ajax({
                url: '../api/funcionarios_atualizar.php',
                method: 'PUT',
                data: JSON.stringify(dados),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        alert(`Funcionário ${acao === 'desativar' ? 'desativado' : 'ativado'} com sucesso!`);
                        carregarFuncionarios();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao atualizar funcionário');
                }
            });
        }

        // Fecha modal
        function fecharModal() {
            document.getElementById('modalFuncionario').classList.remove('show');
            document.getElementById('formFuncionario').reset();
        }

        // Fecha modal ao clicar fora
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modalFuncionario');
            if (event.target === modal) {
                fecharModal();
            }
        });

        // Formata data
        function formatarData(dataStr) {
            if (!dataStr) return '-';
            
            try {
                const data = new Date(dataStr);
                return data.toLocaleDateString('pt-BR');
            } catch (e) {
                return '-';
            }
        }

        // Tecla ESC fecha modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModal();
            }
        });
    </script>
</body>
</html>