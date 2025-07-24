<?php
/**
 * Pagina inicial
 * pages/dashboard.php
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


// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Associados - ASSEGO';

// Busca estatísticas usando a classe Database
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Associados");
    $stmt->execute();
    $totalAssociados = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.id) as total 
        FROM Associados a 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $associadosFiliados = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.id) as total 
        FROM Associados a 
        WHERE a.situacao = 'Desfiliado'
    ");
    $stmt->execute();
    $associadosDesfiliados = $stmt->fetch()['total'] ?? 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM Associados a
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE c.dataFiliacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $novosAssociados = $stmt->fetch()['total'] ?? 0;

} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $totalAssociados = $associadosFiliados = $associadosDesfiliados = $novosAssociados = 0;
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

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

        /* Scrollbar personalizada */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
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

        /* Main Content */
        .main-wrapper {
            min-height: 100vh;
            background: var(--gray-100);
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

        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--white);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(220, 53, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
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

        /* Content Area */
        .content-area {
            padding: 2rem;
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

        /* Transição suave para carregamento de imagens */
        .table-avatar img,
        .modal-avatar img {
            transition: opacity 0.3s ease;
        }

        /* Indicador visual de foto carregando */
        .table-avatar {
            position: relative;
        }

        .table-avatar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: translateX(-100%);
            transition: transform 0.8s;
        }

        .table-avatar.loading::after {
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
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

        .loading-text {
            margin-top: 1rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Paginação */
        .pagination-container {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pagination-info {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-btn {
            min-width: 40px;
            height: 40px;
            border: 2px solid var(--gray-200);
            background: var(--white);
            color: var(--gray-700);
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0 0.75rem;
        }

        .page-btn:hover:not(:disabled) {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        .page-btn.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-btn i {
            font-size: 0.75rem;
        }

        .page-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.875rem;
            background: var(--white);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .page-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Modal Customizado Melhorado */
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
            max-width: 900px;
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

        /* Header Redesenhado */
        .modal-header-custom {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .modal-header-custom::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        .modal-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .modal-header-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .modal-avatar-header {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            overflow: hidden;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        .modal-avatar-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-avatar-header-placeholder {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: var(--primary-light);
        }

        .modal-header-text h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
            color: var(--white);
        }

        .modal-header-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .meta-item i {
            font-size: 0.75rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.875rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .status-pill.active {
            background: rgba(0, 200, 83, 0.2);
            color: #00ff6a;
        }

        .status-pill.inactive {
            background: rgba(255, 59, 48, 0.2);
            color: #ff6b6b;
        }

        .modal-close-custom {
            width: 40px;
            height: 40px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--white);
        }

        .modal-close-custom:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        /* Tabs Navigation */
        .modal-tabs {
            background: var(--gray-100);
            padding: 0.5rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            border-bottom: 1px solid var(--gray-200);
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--gray-600);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 12px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .tab-button:hover {
            background: var(--white);
            color: var(--gray-700);
        }

        .tab-button.active {
            background: var(--white);
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .tab-button i {
            font-size: 1rem;
        }

        .tab-indicator {
            position: absolute;
            bottom: -0.5rem;
            left: 50%;
            transform: translateX(-50%);
            width: 30px;
            height: 3px;
            background: var(--primary);
            border-radius: 3px 3px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .tab-button.active .tab-indicator {
            opacity: 1;
        }

        /* Tab Content */
        .modal-body-custom {
            padding: 0;
            overflow-y: auto;
            flex: 1;
            background: var(--white);
        }

        .tab-content {
            display: none;
            animation: fadeInTab 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInTab {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Overview Tab - Card Grid */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .overview-card {
            background: var(--gray-100);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .overview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            border-color: var(--gray-200);
        }

        .overview-card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .overview-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .overview-card-icon.blue {
            background: rgba(0, 86, 210, 0.1);
            color: var(--primary);
        }

        .overview-card-icon.green {
            background: rgba(0, 200, 83, 0.1);
            color: var(--success);
        }

        .overview-card-icon.orange {
            background: rgba(255, 149, 0, 0.1);
            color: var(--warning);
        }

        .overview-card-icon.purple {
            background: rgba(124, 58, 237, 0.1);
            color: #7c3aed;
        }

        .overview-card-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-600);
            margin: 0;
        }

        .overview-card-content {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .overview-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .overview-item:last-child {
            border-bottom: none;
        }

        .overview-label {
            font-size: 0.8125rem;
            color: var(--gray-500);
        }

        .overview-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            text-align: right;
        }

        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(0, 86, 210, 0.05) 100%);
            padding: 1.5rem 2rem;
            margin: 0 2rem 1.5rem;
            border-radius: 16px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        /* Detail Sections */
        .detail-section {
            padding: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-100);
        }

        .section-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-light);
            color: var(--primary);
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            background: var(--gray-100);
            padding: 1rem 1.25rem;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .detail-item:hover {
            background: var(--gray-200);
        }

        .detail-label {
            font-size: 0.8125rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .detail-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            text-align: right;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state p {
            margin: 0;
            font-size: 0.875rem;
        }

        /* List Items */
        .list-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .list-item {
            background: var(--gray-100);
            padding: 1.25rem;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .list-item:hover {
            background: var(--gray-200);
            transform: translateX(4px);
        }

        .list-item-content {
            flex: 1;
        }

        .list-item-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .list-item-subtitle {
            font-size: 0.8125rem;
            color: var(--gray-500);
        }

        .list-item-badge {
            padding: 0.25rem 0.75rem;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0.5rem;
            bottom: 0.5rem;
            width: 2px;
            background: var(--gray-200);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            background: var(--white);
            border: 3px solid var(--primary);
            border-radius: 50%;
        }

        .timeline-content {
            background: var(--gray-100);
            padding: 1rem 1.25rem;
            border-radius: 12px;
        }

        .timeline-date {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .timeline-title {
            font-weight: 600;
            color: var(--dark);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .nav-tabs-modern {
                overflow-x: auto;
                justify-content: flex-start;
                padding: 0 1rem;
            }

            .nav-tab-link {
                min-width: 100px;
                padding: 1rem;
            }

            .nav-tab-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
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

            .pagination-container {
                flex-direction: column;
            }

            .modal-content-custom {
                max-width: 100%;
                margin: 1rem;
                max-height: calc(100vh - 2rem);
            }

            .modal-header-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .modal-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .overview-grid,
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .stats-section {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        /* Animations */
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

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
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
                    <div
                        style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                        A
                    </div>
                    <div>
                        <h1 class="logo-text" style="margin-bottom: -2px;">ASSEGO</h1>
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
                    <span class="notification-badge"></span>
                </button>
                <div class="user-menu" id="userMenu">
                    <div class="user-info">
                        <p class="user-name"><?php echo htmlspecialchars($usuarioLogado['nome']); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'Funcionário'); ?>
                        </p>
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
                        <?php if ($auth->isDiretor()): ?>
                            <a href="configuracoes.php" class="dropdown-item-custom">
                                <i class="fas fa-cog"></i>
                                <span>Configurações</span>
                            </a>
                        <?php endif; ?>
                        <div class="dropdown-divider-custom"></div>
                        <a href="../php/login/logout.php" class="dropdown-item-custom">
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
                    <a href="#" class="nav-tab-link active">
                        <div class="nav-tab-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="nav-tab-text">Associados</span>
                    </a>
                </li>
                <?php if ($auth->isDiretor()): ?>
                    <li class="nav-tab-item">
                        <a href="funcionarios.php" class="nav-tab-link">
                            <div class="nav-tab-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <span class="nav-tab-text">Funcionários</span>
                        </a>
                    </li>
                <?php endif; ?>
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
            <!-- Page Title -->
            <div class="page-header mb-4" data-aos="fade-right">
                <h1 class="page-title">Gestão de Associados</h1>
                <p class="page-subtitle">Gerencie os associados da ASSEGO</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalAssociados, 0, ',', '.'); ?></div>
                            <div class="stat-label">Total de Associados</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                12% este mês
                            </div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($associadosFiliados, 0, ',', '.'); ?></div>
                            <div class="stat-label">Associados Ativos</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                8% este mês
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
                            <div class="stat-value"><?php echo number_format($associadosDesfiliados, 0, ',', '.'); ?>
                            </div>
                            <div class="stat-label">Inativos</div>
                            <div class="stat-change negative">
                                <i class="fas fa-arrow-down"></i>
                                3% este mês
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
                            <div class="stat-value"><?php echo number_format($novosAssociados, 0, ',', '.'); ?></div>
                            <div class="stat-label">Novos (30 dias)</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                25% este mês
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
                                placeholder="Buscar por RG, nome, CPF ou telefone...">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Situação</label>
                        <select class="filter-select" id="filterSituacao">
                            <option value="">Todos</option>
                            <option value="Filiado">Filiado</option>
                            <option value="Desfiliado">Desfiliado</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Corporação</label>
                        <select class="filter-select" id="filterCorporacao">
                            <option value="">Todos</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Patente</label>
                        <select class="filter-select" id="filterPatente">
                            <option value="">Todos</option>
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
                    <a href="../pages/cadastroForm.php" class="btn-modern btn-primary">
                        <i class="fas fa-plus"></i>
                        Novo Associado
                    </a>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container" data-aos="fade-up" data-aos-delay="200">
                <div class="table-header">
                    <h3 class="table-title">Lista de Associados</h3>
                    <span class="table-info">Mostrando <span id="showingCount">0</span> de <span
                            id="totalCount">0</span> registros</span>
                </div>

                <div class="table-responsive p-2">
                    <table class="modern-table" id="associadosTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Foto</th>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>RG</th>
                                <th>Situação</th>
                                <th>Corporação</th>
                                <th>Patente</th>
                                <th>Dt. Filiação</th>
                                <th>Contato</th>
                                <th style="width: 120px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center">
                                        <div class="loading-spinner mb-3"></div>
                                        <p class="text-muted mb-0">Carregando associados...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        <span>Mostrando página <strong id="currentPage">1</strong> de <strong
                                id="totalPages">1</strong></span>
                        <select class="page-select ms-3" id="perPageSelect">
                            <option value="10">10 por página</option>
                            <option value="25" selected>25 por página</option>
                            <option value="50">50 por página</option>
                            <option value="100">100 por página</option>
                        </select>
                    </div>

                    <div class="pagination-controls">
                        <button class="page-btn" id="firstPage" title="Primeira página">
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        <button class="page-btn" id="prevPage" title="Página anterior">
                            <i class="fas fa-angle-left"></i>
                        </button>

                        <div id="pageNumbers"></div>

                        <button class="page-btn" id="nextPage" title="Próxima página">
                            <i class="fas fa-angle-right"></i>
                        </button>
                        <button class="page-btn" id="lastPage" title="Última página">
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes do Associado -->
    <div class="modal-custom" id="modalAssociado">
        <div class="modal-content-custom">
            <!-- Header Redesenhado -->
            <div class="modal-header-custom">
                <div class="modal-header-content">
                    <div class="modal-header-info">
                        <div class="modal-avatar-header" id="modalAvatarHeader">
                            <!-- Avatar será inserido dinamicamente -->
                        </div>
                        <div class="modal-header-text">
                            <h2 id="modalNome">Carregando...</h2>
                            <div class="modal-header-meta">
                                <div class="meta-item">
                                    <i class="fas fa-id-badge"></i>
                                    <span id="modalId">ID: -</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span id="modalDataFiliacao">-</span>
                                </div>
                                <div id="modalStatusPill">
                                    <!-- Status será inserido dinamicamente -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="modal-close-custom" onclick="fecharModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="modal-tabs">
                <button class="tab-button active" onclick="abrirTab('overview')">
                    <i class="fas fa-th-large"></i>
                    Visão Geral
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-button" onclick="abrirTab('militar')">
                    <i class="fas fa-shield-alt"></i>
                    Militar
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-button" onclick="abrirTab('financeiro')">
                    <i class="fas fa-dollar-sign"></i>
                    Financeiro
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-button" onclick="abrirTab('contato')">
                    <i class="fas fa-address-card"></i>
                    Contato
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-button" onclick="abrirTab('dependentes')">
                    <i class="fas fa-users"></i>
                    Família
                    <span class="tab-indicator"></span>
                </button>
                <button class="tab-button" onclick="abrirTab('documentos')">
                    <i class="fas fa-folder-open"></i>
                    Documentos
                    <span class="tab-indicator"></span>
                </button>
            </div>

            <!-- Tab Contents -->
            <div class="modal-body-custom">
                <!-- Visão Geral Tab -->
                <div id="overview-tab" class="tab-content active">
                    <!-- Conteúdo será inserido dinamicamente -->
                </div>

                <!-- Militar Tab -->
                <div id="militar-tab" class="tab-content">
                    <!-- Conteúdo será inserido dinamicamente -->
                </div>

                <!-- Financeiro Tab -->
                <div id="financeiro-tab" class="tab-content">
                    <!-- Conteúdo será inserido dinamicamente -->
                </div>

                <!-- Contato Tab -->
                <div id="contato-tab" class="tab-content">
                    <!-- Conteúdo será inserido dinamicamente -->
                </div>

                <!-- Dependentes Tab -->
                <div id="dependentes-tab" class="tab-content">
                    <!-- Conteúdo será inserido dinamicamente -->
                </div>

                <!-- Documentos Tab -->
                <div id="documentos-tab" class="tab-content">
                    <!-- Conteúdo será inserido dinamicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Configuração inicial
        console.log('=== INICIANDO SISTEMA ASSEGO ===');
        console.log('jQuery versão:', jQuery.fn.jquery);

        // Inicializa AOS com delay
        setTimeout(() => {
            AOS.init({
                duration: 800,
                once: true
            });
        }, 100);

        // Variáveis globais
        let todosAssociados = [];
        let associadosFiltrados = [];
        let carregamentoIniciado = false;
        let carregamentoCompleto = false; // Nova flag para evitar duplicação
        let imagensCarregadas = new Set();

        // Variáveis de paginação
        let paginaAtual = 1;
        let registrosPorPagina = 25;
        let totalPaginas = 1;

        // User Dropdown Menu
        document.addEventListener('DOMContentLoaded', function () {
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');

            if (userMenu && userDropdown) {
                userMenu.addEventListener('click', function (e) {
                    e.stopPropagation();
                    userDropdown.classList.toggle('show');
                });

                // Fecha dropdown ao clicar fora
                document.addEventListener('click', function () {
                    userDropdown.classList.remove('show');
                });
            }
        });

        // Loading functions
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

        // Função para obter URL da foto
        function getFotoUrl(cpf) {
            if (!cpf) return null;
            const cpfNormalizado = normalizarCPF(cpf);
            return `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;
        }

        // Função para pré-carregar imagem
        function preloadImage(url) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(url);
                img.onerror = () => reject(url);
                img.src = url;
            });
        }

        // Formata data
        function formatarData(dataStr) {
            if (!dataStr || dataStr === "0000-00-00" || dataStr === "") return "-";
            try {
                const [ano, mes, dia] = dataStr.split("-");
                return `${dia}/${mes}/${ano}`;
            } catch (e) {
                return "-";
            }
        }

        // Formata CPF
        function formatarCPF(cpf) {
            if (!cpf) return "-";
            cpf = cpf.toString().replace(/\D/g, '');
            cpf = cpf.padStart(11, '0');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        // Função para garantir CPF com 11 dígitos
        function normalizarCPF(cpf) {
            if (!cpf) return '';
            cpf = cpf.toString().replace(/\D/g, '');
            return cpf.padStart(11, '0');
        }

        // Formata telefone
        function formatarTelefone(telefone) {
            if (!telefone) return "-";
            telefone = telefone.toString().replace(/\D/g, '');
            if (telefone.length === 11) {
                return telefone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
            } else if (telefone.length === 10) {
                return telefone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
            } else if (telefone.length === 9) {
                return telefone.replace(/(\d{5})(\d{4})/, "$1-$2");
            } else if (telefone.length === 8) {
                return telefone.replace(/(\d{4})(\d{4})/, "$1-$2");
            }
            return telefone;
        }

        // Função principal - Carrega dados da tabela
        function carregarAssociados() {
            // Evita múltiplas chamadas e recarregamentos
            if (carregamentoIniciado || carregamentoCompleto) {
                console.log('Carregamento já realizado ou em andamento, ignorando nova chamada');
                return;
            }

            carregamentoIniciado = true;
            console.log('Iniciando carregamento de associados...');
            showLoading();

            const startTime = Date.now();

            const timeoutId = setTimeout(() => {
                hideLoading();
                carregamentoIniciado = false;
                console.error('TIMEOUT: Requisição demorou mais de 30 segundos');
                alert('Tempo esgotado ao carregar dados. Por favor, recarregue a página.');
                renderizarTabela([]);
            }, 30000);

            // Requisição AJAX
            $.ajax({
                url: '../api/carregar_associados.php',
                method: 'GET',
                dataType: 'json',
                cache: false,
                timeout: 25000,
                beforeSend: function () {
                    console.log('Enviando requisição para:', this.url);
                },
                success: function (response) {
                    clearTimeout(timeoutId);
                    const elapsed = Date.now() - startTime;
                    console.log(`Resposta recebida em ${elapsed}ms`);
                    console.log('Total de registros:', response.total);

                    if (response && response.status === 'success') {
                        todosAssociados = Array.isArray(response.dados) ? response.dados : [];

                        // Remove duplicatas baseado no ID
                        const idsUnicos = new Set();
                        todosAssociados = todosAssociados.filter(associado => {
                            if (idsUnicos.has(associado.id)) {
                                return false;
                            }
                            idsUnicos.add(associado.id);
                            return true;
                        });

                        // Ordena por ID decrescente (mais recentes primeiro)
                        todosAssociados.sort((a, b) => b.id - a.id);

                        associadosFiltrados = [...todosAssociados];

                        // Preenche os filtros
                        preencherFiltros();

                        // Calcula total de páginas
                        calcularPaginacao();

                        // Renderiza a primeira página
                        renderizarPagina();

                        // Marca como carregamento completo
                        carregamentoCompleto = true;

                        console.log('✓ Dados carregados com sucesso!');
                        console.log(`Total de associados únicos: ${todosAssociados.length}`);

                        if (response.aviso) {
                            console.warn(response.aviso);
                        }
                    } else {
                        console.error('Resposta com erro:', response);
                        alert('Erro ao carregar dados: ' + (response.message || 'Erro desconhecido'));
                        renderizarTabela([]);
                    }
                },
                error: function (xhr, status, error) {
                    clearTimeout(timeoutId);
                    const elapsed = Date.now() - startTime;
                    console.error(`Erro após ${elapsed}ms:`, {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        error: error
                    });

                    let mensagemErro = 'Erro ao carregar dados';

                    if (xhr.status === 0) {
                        mensagemErro = 'Sem conexão com o servidor';
                    } else if (xhr.status === 404) {
                        mensagemErro = 'Arquivo não encontrado';
                    } else if (xhr.status === 500) {
                        mensagemErro = 'Erro no servidor';
                    } else if (status === 'timeout') {
                        mensagemErro = 'Tempo esgotado';
                    } else if (status === 'parsererror') {
                        mensagemErro = 'Resposta inválida do servidor';
                    }

                    alert(mensagemErro + '\n\nPor favor, recarregue a página.');
                    renderizarTabela([]);
                },
                complete: function () {
                    clearTimeout(timeoutId);
                    hideLoading();
                    carregamentoIniciado = false;
                    console.log('Carregamento finalizado');
                }
            });
        }

        // [Resto das funções permanece igual...]
        // Copie todas as outras funções do seu código original aqui
        // (preencherFiltros, calcularPaginacao, renderizarTabela, etc.)

        // Preenche os filtros dinâmicos
        function preencherFiltros() {
            console.log('Preenchendo filtros...');

            const selectCorporacao = document.getElementById('filterCorporacao');
            const selectPatente = document.getElementById('filterPatente');

            selectCorporacao.innerHTML = '<option value="">Todos</option>';
            selectPatente.innerHTML = '<option value="">Todos</option>';

            const corporacoes = [...new Set(todosAssociados
                .map(a => a.corporacao)
                .filter(c => c && c.trim() !== '')
            )].sort();

            corporacoes.forEach(corp => {
                const option = document.createElement('option');
                option.value = corp;
                option.textContent = corp;
                selectCorporacao.appendChild(option);
            });

            const patentes = [...new Set(todosAssociados
                .map(a => a.patente)
                .filter(p => p && p.trim() !== '')
            )].sort();

            patentes.forEach(pat => {
                const option = document.createElement('option');
                option.value = pat;
                option.textContent = pat;
                selectPatente.appendChild(option);
            });

            console.log(`Filtros preenchidos: ${corporacoes.length} corporações, ${patentes.length} patentes`);
        }

        // Calcula paginação
        function calcularPaginacao() {
            totalPaginas = Math.ceil(associadosFiltrados.length / registrosPorPagina);
            if (paginaAtual > totalPaginas) {
                paginaAtual = 1;
            }
            atualizarControlesPaginacao();
        }

        // Atualiza controles de paginação
        function atualizarControlesPaginacao() {
            document.getElementById('currentPage').textContent = paginaAtual;
            document.getElementById('totalPages').textContent = totalPaginas;
            document.getElementById('totalCount').textContent = associadosFiltrados.length;

            document.getElementById('firstPage').disabled = paginaAtual === 1;
            document.getElementById('prevPage').disabled = paginaAtual === 1;
            document.getElementById('nextPage').disabled = paginaAtual === totalPaginas;
            document.getElementById('lastPage').disabled = paginaAtual === totalPaginas;

            const pageNumbers = document.getElementById('pageNumbers');
            pageNumbers.innerHTML = '';

            let startPage = Math.max(1, paginaAtual - 2);
            let endPage = Math.min(totalPaginas, paginaAtual + 2);

            for (let i = startPage; i <= endPage; i++) {
                const btn = document.createElement('button');
                btn.className = 'page-btn' + (i === paginaAtual ? ' active' : '');
                btn.textContent = i;
                btn.onclick = () => irParaPagina(i);
                pageNumbers.appendChild(btn);
            }
        }

        // Renderiza página atual
        function renderizarPagina() {
            const inicio = (paginaAtual - 1) * registrosPorPagina;
            const fim = inicio + registrosPorPagina;
            const dadosPagina = associadosFiltrados.slice(inicio, fim);

            renderizarTabela(dadosPagina);

            const mostrando = Math.min(registrosPorPagina, dadosPagina.length);
            document.getElementById('showingCount').textContent =
                `${inicio + 1}-${inicio + mostrando}`;
        }

        // Navegar entre páginas
        function irParaPagina(pagina) {
            paginaAtual = pagina;
            renderizarPagina();
            atualizarControlesPaginacao();
        }

        // Renderiza tabela
        function renderizarTabela(dados) {
            console.log(`Renderizando ${dados.length} registros...`);
            const tbody = document.getElementById('tableBody');

            if (!tbody) {
                console.error('Elemento tableBody não encontrado!');
                return;
            }

            tbody.innerHTML = '';

            if (dados.length === 0) {
                tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-5">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                        <p class="text-muted mb-0">Nenhum associado encontrado</p>
                        <small class="text-muted">Tente ajustar os filtros de busca</small>
                    </div>
                </td>
            </tr>
        `;
                return;
            }

            dados.forEach(associado => {
                const situacaoBadge = associado.situacao === 'Filiado'
                    ? '<span class="status-badge active"><i class="fas fa-check-circle"></i> Filiado</span>'
                    : '<span class="status-badge inactive"><i class="fas fa-times-circle"></i> Desfiliado</span>';

                const row = document.createElement('tr');
                row.onclick = (e) => {
                    if (!e.target.closest('.btn-icon')) {
                        visualizarAssociado(associado.id);
                    }
                };

                let fotoHtml = `<span>${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}</span>`;

                if (associado.cpf) {
                    const cpfNormalizado = normalizarCPF(associado.cpf);
                    const fotoUrl = `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;

                    fotoHtml = `
                <img src="${fotoUrl}" 
                     alt="${associado.nome}"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                     onload="this.style.display='block'; this.nextElementSibling.style.display='none';">
                <span style="display:none;">${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}</span>
            `;
                }

                row.innerHTML = `
            <td>
                <div class="table-avatar">
                    ${fotoHtml}
                </div>
            </td>
            <td>
                <span class="fw-semibold">${associado.nome || 'Sem nome'}</span>
                <br>
                <small class="text-muted">ID: ${associado.id}</small>
            </td>
            <td>${formatarCPF(associado.cpf)}</td>
            <td>${associado.rg || '-'}</td>
            <td>${situacaoBadge}</td>
            <td>${associado.corporacao || '-'}</td>
            <td>${associado.patente || '-'}</td>
            <td>${formatarData(associado.data_filiacao)}</td>
            <td>${formatarTelefone(associado.telefone)}</td>
            <td>
                <div class="action-buttons-table">
                    <button class="btn-icon view" onclick="visualizarAssociado(${associado.id})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon edit" onclick="editarAssociado(${associado.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon delete" onclick="excluirAssociado(${associado.id})" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
                tbody.appendChild(row);
            });
        }

        // Aplica filtros
        function aplicarFiltros() {
            console.log('Aplicando filtros...');
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const filterSituacao = document.getElementById('filterSituacao').value;
            const filterCorporacao = document.getElementById('filterCorporacao').value;
            const filterPatente = document.getElementById('filterPatente').value;

            associadosFiltrados = todosAssociados.filter(associado => {
                const matchSearch = !searchTerm ||
                    (associado.nome && associado.nome.toLowerCase().includes(searchTerm)) ||
                    (associado.cpf && associado.cpf.includes(searchTerm)) ||
                    (associado.rg && associado.rg.includes(searchTerm)) ||
                    (associado.telefone && associado.telefone.includes(searchTerm));

                const matchSituacao = !filterSituacao || associado.situacao === filterSituacao;
                const matchCorporacao = !filterCorporacao || associado.corporacao === filterCorporacao;
                const matchPatente = !filterPatente || associado.patente === filterPatente;

                return matchSearch && matchSituacao && matchCorporacao && matchPatente;
            });

            console.log(`Filtros aplicados: ${associadosFiltrados.length} de ${todosAssociados.length} registros`);

            paginaAtual = 1;
            calcularPaginacao();
            renderizarPagina();
        }

        // Limpa filtros
        function limparFiltros() {
            console.log('Limpando filtros...');
            document.getElementById('searchInput').value = '';
            document.getElementById('filterSituacao').value = '';
            document.getElementById('filterCorporacao').value = '';
            document.getElementById('filterPatente').value = '';

            associadosFiltrados = [...todosAssociados];
            paginaAtual = 1;
            calcularPaginacao();
            renderizarPagina();
        }

        // [Copie aqui todas as outras funções do código original]
        // visualizarAssociado, atualizarHeaderModal, preencherTabVisaoGeral, etc.
        // Função para visualizar detalhes do associado
        function visualizarAssociado(id) {
            console.log('Visualizando associado ID:', id);
            const associado = todosAssociados.find(a => a.id == id);

            if (!associado) {
                console.error('Associado não encontrado:', id);
                alert('Associado não encontrado!');
                return;
            }

            // Atualiza o header do modal
            atualizarHeaderModal(associado);

            // Preenche as tabs
            preencherTabVisaoGeral(associado);
            preencherTabMilitar(associado);
            preencherTabFinanceiro(associado);
            preencherTabContato(associado);
            preencherTabDependentes(associado);
            preencherTabDocumentos(associado);

            // Abre o modal
            document.getElementById('modalAssociado').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Atualiza o header do modal
        function atualizarHeaderModal(associado) {
            // Nome e ID
            document.getElementById('modalNome').textContent = associado.nome || 'Sem nome';
            document.getElementById('modalId').textContent = `ID: ${associado.id}`;

            // Data de filiação
            document.getElementById('modalDataFiliacao').textContent =
                formatarData(associado.data_filiacao) !== '-'
                    ? `Desde ${formatarData(associado.data_filiacao)}`
                    : 'Data não informada';

            // Status
            const statusPill = document.getElementById('modalStatusPill');
            if (associado.situacao === 'Filiado') {
                statusPill.innerHTML = `
            <div class="status-pill active">
                <i class="fas fa-check-circle"></i>
                Ativo
            </div>
        `;
            } else {
                statusPill.innerHTML = `
            <div class="status-pill inactive">
                <i class="fas fa-times-circle"></i>
                Inativo
            </div>
        `;
            }

            // Avatar
            const modalAvatar = document.getElementById('modalAvatarHeader');
            if (associado.cpf) {
                const cpfNormalizado = normalizarCPF(associado.cpf);
                const fotoUrl = `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;

                modalAvatar.innerHTML = `
            <img src="${fotoUrl}" 
                 alt="${associado.nome}"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                 onload="this.style.display='block'; this.nextElementSibling.style.display='none';">
            <div class="modal-avatar-header-placeholder" style="display:none;">
                ${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}
            </div>
        `;
            } else {
                modalAvatar.innerHTML = `
            <div class="modal-avatar-header-placeholder">
                ${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}
            </div>
        `;
            }
        }

        // Preenche tab Visão Geral
        function preencherTabVisaoGeral(associado) {
            const overviewTab = document.getElementById('overview-tab');

            // Calcula idade
            let idade = '-';
            if (associado.nasc && associado.nasc !== '0000-00-00') {
                const hoje = new Date();
                const nascimento = new Date(associado.nasc);
                idade = Math.floor((hoje - nascimento) / (365.25 * 24 * 60 * 60 * 1000));
                idade = idade + ' anos';
            }

            overviewTab.innerHTML = `
        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-item">
                <div class="stat-value">${associado.total_servicos || 0}</div>
                <div class="stat-label">Serviços Ativos</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${associado.total_dependentes || 0}</div>
                <div class="stat-label">Dependentes</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${associado.total_documentos || 0}</div>
                <div class="stat-label">Documentos</div>
            </div>
        </div>
        
        <!-- Overview Grid -->
        <div class="overview-grid">
            <!-- Dados Pessoais -->
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon blue">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4 class="overview-card-title">Dados Pessoais</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Nome Completo</span>
                        <span class="overview-value">${associado.nome || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">CPF</span>
                        <span class="overview-value">${formatarCPF(associado.cpf)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">RG</span>
                        <span class="overview-value">${associado.rg || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Data de Nascimento</span>
                        <span class="overview-value">${formatarData(associado.nasc)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Idade</span>
                        <span class="overview-value">${idade}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Sexo</span>
                        <span class="overview-value">${associado.sexo === 'M' ? 'Masculino' : associado.sexo === 'F' ? 'Feminino' : '-'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Informações de Filiação -->
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon green">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <h4 class="overview-card-title">Informações de Filiação</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Situação</span>
                        <span class="overview-value">${associado.situacao || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Data de Filiação</span>
                        <span class="overview-value">${formatarData(associado.data_filiacao)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Data de Desfiliação</span>
                        <span class="overview-value">${formatarData(associado.data_desfiliacao)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Escolaridade</span>
                        <span class="overview-value">${associado.escolaridade || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Estado Civil</span>
                        <span class="overview-value">${associado.estadoCivil || '-'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Informações Extras -->
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon purple">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h4 class="overview-card-title">Informações Adicionais</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Indicação</span>
                        <span class="overview-value">${associado.indicacao || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Tipo de Associado</span>
                        <span class="overview-value">${associado.tipoAssociado || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Situação Financeira</span>
                        <span class="overview-value">${associado.situacaoFinanceira || '-'}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
        }

        // Preenche tab Militar
        function preencherTabMilitar(associado) {
            const militarTab = document.getElementById('militar-tab');

            militarTab.innerHTML = `
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="section-title">Informações Militares</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Corporação</span>
                    <span class="detail-value">${associado.corporacao || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Patente</span>
                    <span class="detail-value">${associado.patente || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Categoria</span>
                    <span class="detail-value">${associado.categoria || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Lotação</span>
                    <span class="detail-value">${associado.lotacao || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Unidade</span>
                    <span class="detail-value">${associado.unidade || '-'}</span>
                </div>
            </div>
        </div>
    `;
        }

        // Preenche tab Financeiro
        // VERSÃO COMPLETA - Adicione ao seu dashboard.php

// Preenche tab Financeiro - VERSÃO COMPLETA COM HISTÓRICO
function preencherTabFinanceiro(associado) {
    const financeiroTab = document.getElementById('financeiro-tab');

    // Mostra loading enquanto carrega
    financeiroTab.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; color: var(--gray-500);">
            <div class="loading-spinner" style="margin-bottom: 1rem;"></div>
            <p>Carregando informações financeiras...</p>
        </div>
    `;

    // Busca dados dos serviços do associado
    buscarServicosAssociado(associado.id)
        .then(dadosServicos => {
            console.log('Dados dos serviços:', dadosServicos);
            
            let servicosHtml = '';
            let historicoHtml = '';
            let valorTotalMensal = 0;
            let tipoAssociadoServico = 'Não definido';
            let servicosAtivos = [];
            let resumoServicos = 'Nenhum serviço ativo';

            if (dadosServicos && dadosServicos.status === 'success' && dadosServicos.data) {
                const dados = dadosServicos.data;
                valorTotalMensal = dados.valor_total_mensal || 0;
                tipoAssociadoServico = dados.tipo_associado_servico || 'Não definido';

                // Analisa os serviços contratados
                if (dados.servicos.social) {
                    servicosAtivos.push('Social');
                }
                if (dados.servicos.juridico) {
                    servicosAtivos.push('Jurídico');
                }

                // Define resumo dos serviços
                if (servicosAtivos.length === 2) {
                    resumoServicos = 'Social + Jurídico';
                } else if (servicosAtivos.includes('Social')) {
                    resumoServicos = 'Apenas Social';
                } else if (servicosAtivos.includes('Jurídico')) {
                    resumoServicos = 'Apenas Jurídico';
                }

                // Gera HTML dos serviços
                servicosHtml = gerarHtmlServicosCompleto(dados.servicos, valorTotalMensal);
                
                // Gera HTML do histórico
                if (dados.historico && dados.historico.length > 0) {
                    historicoHtml = gerarHtmlHistorico(dados.historico);
                }
            } else {
                servicosHtml = `
                    <div class="empty-state" style="padding: 2rem; text-align: center; color: var(--gray-500);">
                        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p>Nenhum serviço contratado</p>
                        <small>Este associado ainda não possui serviços ativos</small>
                    </div>
                `;
            }

            financeiroTab.innerHTML = `
                <!-- Resumo Financeiro Principal -->
                <div class="resumo-financeiro" style="margin: 1.5rem 2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: 16px; padding: 2rem; color: white; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -30px; right: -30px; font-size: 6rem; opacity: 0.1;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div style="position: relative; z-index: 1; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; align-items: center;">
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px;">
                                Valor Mensal Total
                            </div>
                            <div style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.25rem;">
                                R$ ${valorTotalMensal.toFixed(2).replace('.', ',')}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.8;">
                                ${servicosAtivos.length} serviço${servicosAtivos.length !== 1 ? 's' : ''} ativo${servicosAtivos.length !== 1 ? 's' : ''}
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px;">
                                Tipo de Associado
                            </div>
                            <div style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.25rem;">
                                ${tipoAssociadoServico}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.8;">
                                Define percentual de cobrança
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px;">
                                Serviços Contratados
                            </div>
                            <div style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.25rem;">
                                ${resumoServicos}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.8;">
                                ${servicosAtivos.includes('Jurídico') ? 'Inclui cobertura jurídica' : 'Cobertura básica'}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção de Serviços Contratados -->
                <div class="detail-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3 class="section-title">Detalhes dos Serviços</h3>
                    </div>
                    ${servicosHtml}
                </div>


                <!-- Dados Bancários -->
                <div class="detail-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <h3 class="section-title">Dados Bancários e Cobrança</h3>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Categoria</span>
                            <span class="detail-value">${associado.tipoAssociado || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Situação Financeira</span>
                            <span class="detail-value">
                                ${associado.situacaoFinanceira ? 
                                    `<span style="color: ${associado.situacaoFinanceira === 'Adimplente' ? 'var(--success)' : 'var(--danger)'}; font-weight: 600;">${associado.situacaoFinanceira}</span>` 
                                    : '-'}
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vínculo Servidor</span>
                            <span class="detail-value">${associado.vinculoServidor || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Local de Débito</span>
                            <span class="detail-value">${associado.localDebito || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Agência</span>
                            <span class="detail-value">${associado.agencia || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Operação</span>
                            <span class="detail-value">${associado.operacao || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Conta Corrente</span>
                            <span class="detail-value">${associado.contaCorrente || '-'}</span>
                        </div>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            console.error('Erro ao buscar serviços:', error);
            
            // Fallback: mostra apenas dados tradicionais
            financeiroTab.innerHTML = `
                <div class="detail-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                        </div>
                        <h3 class="section-title">Dados Financeiros</h3>
                        <small style="color: var(--warning); font-size: 0.75rem;">⚠ Não foi possível carregar dados dos serviços</small>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Categoria</span>
                            <span class="detail-value">${associado.tipoAssociado || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Situação Financeira</span>
                            <span class="detail-value">${associado.situacaoFinanceira || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vínculo Servidor</span>
                            <span class="detail-value">${associado.vinculoServidor || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Local de Débito</span>
                            <span class="detail-value">${associado.localDebito || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Agência</span>
                            <span class="detail-value">${associado.agencia || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Operação</span>
                            <span class="detail-value">${associado.operacao || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Conta Corrente</span>
                            <span class="detail-value">${associado.contaCorrente || '-'}</span>
                        </div>
                    </div>
                </div>
            `;
        });
}

// Função para gerar HTML dos serviços - VERSÃO COMPLETA
function gerarHtmlServicosCompleto(servicos, valorTotal) {
    let servicosHtml = '';
    
    // Verifica se tem serviços
    if (!servicos.social && !servicos.juridico) {
        return `
            <div class="empty-state" style="padding: 2rem; text-align: center; color: var(--gray-500);">
                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Nenhum serviço ativo encontrado</p>
                <small>Este associado não possui serviços contratados</small>
            </div>
        `;
    }

    servicosHtml += '<div class="servicos-container" style="display: flex; flex-direction: column; gap: 1.5rem;">';

    // Serviço Social
    if (servicos.social) {
        const social = servicos.social;
        const dataAdesao = new Date(social.data_adesao).toLocaleDateString('pt-BR');
        const valorBase = parseFloat(social.valor_base || 173.10);
        const desconto = ((valorBase - parseFloat(social.valor_aplicado)) / valorBase * 100).toFixed(0);
        
        servicosHtml += `
            <div class="servico-card" style="
                background: linear-gradient(135deg, var(--success) 0%, #00a847 100%);
                padding: 1.5rem;
                border-radius: 16px;
                color: white;
                position: relative;
                overflow: hidden;
                box-shadow: 0 8px 24px rgba(0, 200, 83, 0.3);
            ">
                <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.1;">
                    <i class="fas fa-heart"></i>
                </div>
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-heart"></i>
                                Serviço Social
                            </h4>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.75rem; flex-wrap: wrap;">
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    OBRIGATÓRIO
                                </span>
                                <span style="font-size: 0.875rem; opacity: 0.9;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 0.25rem;"></i>
                                    Desde ${dataAdesao}
                                </span>
                                ${desconto > 0 ? `
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fas fa-percentage" style="margin-right: 0.25rem;"></i>
                                    ${desconto}% desconto
                                </span>
                                ` : ''}
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 120px;">
                            <div style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.25rem;">
                                R$ ${parseFloat(social.valor_aplicado).toFixed(2).replace('.', ',')}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.5rem; border-radius: 8px;">
                                ${parseFloat(social.percentual_aplicado).toFixed(0)}% do valor base
                            </div>
                        </div>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 8px; font-size: 0.875rem; line-height: 1.5;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Valor base:</span>
                            <span style="font-weight: 600;">R$ ${valorBase.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Observação:</span>
                            <span style="font-weight: 600; max-width: 200px; text-align: right;">
                                ${social.observacao || 'Serviço social básico'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Serviço Jurídico
    if (servicos.juridico) {
        const juridico = servicos.juridico;
        const dataAdesao = new Date(juridico.data_adesao).toLocaleDateString('pt-BR');
        const valorBase = parseFloat(juridico.valor_base || 43.28);
        const desconto = ((valorBase - parseFloat(juridico.valor_aplicado)) / valorBase * 100).toFixed(0);
        
        servicosHtml += `
            <div class="servico-card" style="
                background: linear-gradient(135deg, var(--info) 0%, #0097a7 100%);
                padding: 1.5rem;
                border-radius: 16px;
                color: white;
                position: relative;
                overflow: hidden;
                box-shadow: 0 8px 24px rgba(0, 184, 212, 0.3);
            ">
                <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.1;">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-balance-scale"></i>
                                Serviço Jurídico
                            </h4>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.75rem; flex-wrap: wrap;">
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    OPCIONAL
                                </span>
                                <span style="font-size: 0.875rem; opacity: 0.9;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 0.25rem;"></i>
                                    Desde ${dataAdesao}
                                </span>
                                ${desconto > 0 ? `
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fas fa-percentage" style="margin-right: 0.25rem;"></i>
                                    ${desconto}% desconto
                                </span>
                                ` : ''}
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 120px;">
                            <div style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.25rem;">
                                R$ ${parseFloat(juridico.valor_aplicado).toFixed(2).replace('.', ',')}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.5rem; border-radius: 8px;">
                                ${parseFloat(juridico.percentual_aplicado).toFixed(0)}% do valor base
                            </div>
                        </div>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 8px; font-size: 0.875rem; line-height: 1.5;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Valor base:</span>
                            <span style="font-weight: 600;">R$ ${valorBase.toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Observação:</span>
                            <span style="font-weight: 600; max-width: 200px; text-align: right;">
                                ${juridico.observacao || 'Serviço jurídico opcional'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    servicosHtml += '</div>';
    return servicosHtml;
}

// Função para gerar HTML do histórico
function gerarHtmlHistorico(historico) {
    if (!historico || historico.length === 0) {
        return '';
    }

    let historicoHtml = '<div class="historico-container" style="display: flex; flex-direction: column; gap: 1rem;">';

    historico.slice(0, 5).forEach(item => { // Mostra apenas os últimos 5
        const data = new Date(item.data_alteracao).toLocaleDateString('pt-BR');
        const hora = new Date(item.data_alteracao).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        
        let icone = 'fa-edit';
        let cor = 'var(--info)';
        let titulo = item.tipo_alteracao;
        
        if (item.tipo_alteracao === 'ADESAO') {
            icone = 'fa-plus-circle';
            cor = 'var(--success)';
            titulo = 'Adesão';
        } else if (item.tipo_alteracao === 'CANCELAMENTO') {
            icone = 'fa-times-circle';
            cor = 'var(--danger)';
            titulo = 'Cancelamento';
        } else if (item.tipo_alteracao === 'ALTERACAO_VALOR') {
            icone = 'fa-exchange-alt';
            cor = 'var(--warning)';
            titulo = 'Alteração de Valor';
        }

        historicoHtml += `
            <div style="
                background: var(--gray-100);
                padding: 1rem;
                border-radius: 12px;
                border-left: 4px solid ${cor};
                display: flex;
                align-items: flex-start;
                gap: 1rem;
            ">
                <div style="
                    width: 40px;
                    height: 40px;
                    background: ${cor};
                    color: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                ">
                    <i class="fas ${icone}"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <h5 style="margin: 0; font-weight: 600; color: var(--dark);">
                            ${titulo} - ${item.servico_nome}
                        </h5>
                        <small style="color: var(--gray-500); font-size: 0.75rem;">
                            ${data} às ${hora}
                        </small>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                        ${item.motivo || 'Sem observações'}
                    </div>
                    ${item.valor_anterior && item.valor_novo ? `
                        <div style="display: flex; gap: 1rem; font-size: 0.75rem;">
                            <span style="color: var(--danger);">
                                De: R$ ${parseFloat(item.valor_anterior).toFixed(2).replace('.', ',')}
                            </span>
                            <span style="color: var(--success);">
                                Para: R$ ${parseFloat(item.valor_novo).toFixed(2).replace('.', ',')}
                            </span>
                        </div>
                    ` : ''}
                    ${item.funcionario_nome ? `
                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.5rem;">
                            <i class="fas fa-user" style="margin-right: 0.25rem;"></i>
                            Por: ${item.funcionario_nome}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    });

    historicoHtml += '</div>';
    return historicoHtml;
}

// Função para buscar serviços do associado (mantém a mesma)
function buscarServicosAssociado(associadoId) {
    return fetch(`../api/buscar_servicos_associado.php?associado_id=${associadoId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        });
}

        // Preenche tab Contato
        function preencherTabContato(associado) {
            const contatoTab = document.getElementById('contato-tab');

            contatoTab.innerHTML = `
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <h3 class="section-title">Informações de Contato</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Telefone</span>
                    <span class="detail-value">${formatarTelefone(associado.telefone)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">E-mail</span>
                    <span class="detail-value">${associado.email || '-'}</span>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3 class="section-title">Endereço</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">CEP</span>
                    <span class="detail-value">${associado.cep || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Endereço</span>
                    <span class="detail-value">${associado.endereco || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Número</span>
                    <span class="detail-value">${associado.numero || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Complemento</span>
                    <span class="detail-value">${associado.complemento || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Bairro</span>
                    <span class="detail-value">${associado.bairro || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Cidade</span>
                    <span class="detail-value">${associado.cidade || '-'}</span>
                </div>
            </div>
        </div>
    `;
        }

        // Preenche tab Dependentes
        function preencherTabDependentes(associado) {
            const dependentesTab = document.getElementById('dependentes-tab');

            if (!associado.dependentes || associado.dependentes.length === 0) {
                dependentesTab.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>Nenhum dependente cadastrado</p>
            </div>
        `;
                return;
            }

            let dependentesHtml = `
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="section-title">Dependentes (${associado.dependentes.length})</h3>
            </div>
            <div class="list-container">
    `;

            associado.dependentes.forEach(dep => {
                let idade = '-';
                if (dep.data_nascimento && dep.data_nascimento !== '0000-00-00') {
                    const hoje = new Date();
                    const nascimento = new Date(dep.data_nascimento);
                    idade = Math.floor((hoje - nascimento) / (365.25 * 24 * 60 * 60 * 1000));
                    idade = idade + ' anos';
                }

                dependentesHtml += `
            <div class="list-item">
                <div class="list-item-content">
                    <div class="list-item-title">${dep.nome || 'Sem nome'}</div>
                    <div class="list-item-subtitle">
                        ${dep.parentesco || 'Parentesco não informado'} • 
                        ${formatarData(dep.data_nascimento)} • 
                        ${idade}
                    </div>
                </div>
                <span class="list-item-badge">${dep.sexo || '-'}</span>
            </div>
        `;
            });

            dependentesHtml += `
            </div>
        </div>
    `;

            dependentesTab.innerHTML = dependentesHtml;
        }

        // Preenche tab Documentos
        function preencherTabDocumentos(associado) {
            const documentosTab = document.getElementById('documentos-tab');

            documentosTab.innerHTML = `
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3 class="section-title">Documentos</h3>
            </div>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>Funcionalidade de documentos em desenvolvimento</p>
            </div>
        </div>
    `;
        }

        // Função para fechar modal
        function fecharModal() {
            const modal = document.getElementById('modalAssociado');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';

            // Volta para a primeira tab
            abrirTab('overview');
        }

        // Função para trocar de tab
        function abrirTab(tabName) {
            // Remove active de todas as tabs
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Adiciona active na tab selecionada
            const activeButton = document.querySelector(`.tab-button[onclick="abrirTab('${tabName}')"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }

            const activeContent = document.getElementById(`${tabName}-tab`);
            if (activeContent) {
                activeContent.classList.add('active');
            }
        }

        // Função para editar associado
        function editarAssociado(id) {
            console.log('Editando associado ID:', id);

            // Previne propagação do clique
            event.stopPropagation();

            // Redireciona para a página de edição
            window.location.href = `cadastroForm.php?id=${id}`;
        }

        // Função para excluir associado
        function excluirAssociado(id) {
            console.log('Excluindo associado ID:', id);

            // Previne propagação do clique
            event.stopPropagation();

            const associado = todosAssociados.find(a => a.id == id);

            if (!associado) {
                alert('Associado não encontrado!');
                return;
            }

            if (!confirm(`Tem certeza que deseja excluir o associado ${associado.nome}?\n\nEsta ação não pode ser desfeita!`)) {
                return;
            }

            showLoading();

            // Chamada AJAX para excluir
            $.ajax({
                url: '../api/excluir_associado.php',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    hideLoading();

                    if (response.status === 'success') {
                        alert('Associado excluído com sucesso!');

                        // Remove da lista local
                        todosAssociados = todosAssociados.filter(a => a.id != id);
                        associadosFiltrados = associadosFiltrados.filter(a => a.id != id);

                        // Recalcula paginação e renderiza
                        calcularPaginacao();
                        renderizarPagina();
                    } else {
                        alert('Erro ao excluir associado: ' + response.message);
                    }
                },
                error: function (xhr, status, error) {
                    hideLoading();
                    console.error('Erro ao excluir:', error);
                    alert('Erro ao excluir associado. Por favor, tente novamente.');
                }
            });
        }

        // Fecha modal ao clicar fora
        window.addEventListener('click', function (event) {
            const modal = document.getElementById('modalAssociado');
            if (event.target === modal) {
                fecharModal();
            }
        });

        // Tecla ESC fecha o modal
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                fecharModal();
            }
        });

        // Adiciona smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Função auxiliar para formatar valores monetários
        function formatarMoeda(valor) {
            if (!valor || isNaN(valor)) return 'R$ 0,00';

            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
        }

        // Função para validar CPF
        function validarCPF(cpf) {
            cpf = cpf.replace(/[^\d]+/g, '');

            if (cpf.length !== 11) return false;

            // Elimina CPFs invalidos conhecidos
            if (/^(\d)\1+$/.test(cpf)) return false;

            // Valida 1o digito
            let add = 0;
            for (let i = 0; i < 9; i++) {
                add += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let rev = 11 - (add % 11);
            if (rev === 10 || rev === 11) rev = 0;
            if (rev !== parseInt(cpf.charAt(9))) return false;

            // Valida 2o digito
            add = 0;
            for (let i = 0; i < 10; i++) {
                add += parseInt(cpf.charAt(i)) * (11 - i);
            }
            rev = 11 - (add % 11);
            if (rev === 10 || rev === 11) rev = 0;
            if (rev !== parseInt(cpf.charAt(10))) return false;

            return true;
        }

        // Auto-resize para textarea
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });

        console.log('✓ Todas as funções JavaScript carregadas com sucesso!');

        // Event listeners - só adiciona UMA VEZ
        document.addEventListener('DOMContentLoaded', function () {
            // Adiciona listeners aos filtros
            const searchInput = document.getElementById('searchInput');
            const filterSituacao = document.getElementById('filterSituacao');
            const filterCorporacao = document.getElementById('filterCorporacao');
            const filterPatente = document.getElementById('filterPatente');

            if (searchInput) searchInput.addEventListener('input', aplicarFiltros);
            if (filterSituacao) filterSituacao.addEventListener('change', aplicarFiltros);
            if (filterCorporacao) filterCorporacao.addEventListener('change', aplicarFiltros);
            if (filterPatente) filterPatente.addEventListener('change', aplicarFiltros);

            // Paginação
            const perPageSelect = document.getElementById('perPageSelect');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', function () {
                    registrosPorPagina = parseInt(this.value);
                    paginaAtual = 1;
                    calcularPaginacao();
                    renderizarPagina();
                });
            }

            const firstPage = document.getElementById('firstPage');
            const prevPage = document.getElementById('prevPage');
            const nextPage = document.getElementById('nextPage');
            const lastPage = document.getElementById('lastPage');

            if (firstPage) firstPage.addEventListener('click', () => irParaPagina(1));
            if (prevPage) prevPage.addEventListener('click', () => irParaPagina(paginaAtual - 1));
            if (nextPage) nextPage.addEventListener('click', () => irParaPagina(paginaAtual + 1));
            if (lastPage) lastPage.addEventListener('click', () => irParaPagina(totalPaginas));

            console.log('Event listeners adicionados');

            // Carrega dados apenas UMA vez após 500ms
            setTimeout(function () {
                carregarAssociados();
            }, 500);
        });

        // Remove todos os outros event listeners e timeouts duplicados

        // Função para verificar sistema
        function verificarSistema() {
            console.log('=== VERIFICAÇÃO DO SISTEMA ===');
            console.log('jQuery:', typeof jQuery !== 'undefined' ? '✓' : '✗');
            console.log('Total associados carregados:', todosAssociados.length);
            console.log('Carregamento completo:', carregamentoCompleto ? '✓' : '✗');
            console.log('=========================');
        }

        // Exporta funções para debug
        window.debugAssociados = function () {
            verificarSistema();
            console.log('Total associados:', todosAssociados.length);
            console.log('Associados filtrados:', associadosFiltrados.length);
            console.log('Carregamento em andamento:', carregamentoIniciado);
            console.log('Carregamento completo:', carregamentoCompleto);
            console.log('Página atual:', paginaAtual);
            console.log('Total de páginas:', totalPaginas);
            console.log('Registros por página:', registrosPorPagina);

            // Verifica duplicatas
            const ids = todosAssociados.map(a => a.id);
            const idsUnicos = [...new Set(ids)];
            console.log('IDs totais:', ids.length);
            console.log('IDs únicos:', idsUnicos.length);
            console.log('Duplicatas:', ids.length - idsUnicos.length);
        };

        console.log('Sistema inicializado. Use debugAssociados() para debug.');
    </script>

</body>

</html>