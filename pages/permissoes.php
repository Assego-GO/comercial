<?php
/**
 * Página de Gerenciamento de Permissões
 * pages/permissoes.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Permissoes.php';
require_once '../classes/PermissoesManager.php';
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

// Inicializa o gerenciador de permissões e funcionários
$permissoesManager = PermissoesManager::getInstance();
$funcionariosClass = new Funcionarios();

// Busca dados completos do usuário logado incluindo departamento
$dadosUsuarioCompleto = $funcionariosClass->getById($usuarioLogado['id']);
$usuarioLogado['departamento_id'] = $dadosUsuarioCompleto['departamento_id'] ?? null;
$usuarioLogado['cargo'] = $dadosUsuarioCompleto['cargo'] ?? null;

// Verifica se é da presidência (departamento 1 ou cargos específicos)
$ehPresidencia = ($usuarioLogado['departamento_id'] == 1) || 
                 in_array($usuarioLogado['cargo'], ['Presidente', 'Vice-Presidente']);

// Verifica permissões de acesso
$temAcessoTotal = $ehPresidencia; // Presidência tem acesso total
$isDiretor = $permissoesManager->isDiretorDepartamento($usuarioLogado['id']);
$podeGerenciarPermissoes = $temAcessoTotal || $isDiretor;

if (!$podeGerenciarPermissoes) {
    $_SESSION['erro'] = 'Você não tem permissão para acessar esta página.';
    header('Location: dashboard.php');
    exit;
}

// Define o título da página
$page_title = 'Gerenciamento de Permissões - ASSEGO';

// Busca estatísticas
$db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

try {
    // Dashboard de permissões
    $dashboard = $permissoesManager->getDashboardDiretor($usuarioLogado['id']);
    
    // Busca funcionários baseado no nível de acesso
    $funcionariosDepartamento = [];
    
    if ($ehPresidencia) {
        // Presidência vê TODOS os funcionários de TODOS os departamentos
        $funcionariosDepartamento = $funcionariosClass->listar([
            'ativo' => 1
        ]);
    } else if ($isDiretor) {
        // Diretor vê apenas funcionários do SEU departamento
        $funcionariosDepartamento = $funcionariosClass->listar([
            'ativo' => 1,
            'departamento_id' => $usuarioLogado['departamento_id']
        ]);
    }
    
    // Busca delegações ativas - filtradas por acesso
    $todasDelegacoes = [];
    if ($ehPresidencia) {
        // Presidência vê todas as delegações do sistema
        $stmt = $db->prepare("
            SELECT 
                pd.*,
                fo.nome as origem_nome,
                fd.nome as destino_nome,
                do.nome as dept_origem,
                dd.nome as dept_destino
            FROM Permissoes_Delegadas pd
            JOIN Funcionarios fo ON pd.funcionario_origem = fo.id
            JOIN Funcionarios fd ON pd.funcionario_destino = fd.id
            LEFT JOIN Departamentos do ON fo.departamento_id = do.id
            LEFT JOIN Departamentos dd ON fd.departamento_id = dd.id
            WHERE pd.ativa = 1
            AND (pd.data_fim IS NULL OR pd.data_fim > NOW())
            ORDER BY pd.criado_em DESC
        ");
        $stmt->execute();
        $todasDelegacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if ($isDiretor) {
        // Diretor vê apenas delegações do seu departamento
        $stmt = $db->prepare("
            SELECT 
                pd.*,
                fo.nome as origem_nome,
                fd.nome as destino_nome,
                do.nome as dept_origem,
                dd.nome as dept_destino
            FROM Permissoes_Delegadas pd
            JOIN Funcionarios fo ON pd.funcionario_origem = fo.id
            JOIN Funcionarios fd ON pd.funcionario_destino = fd.id
            LEFT JOIN Departamentos do ON fo.departamento_id = do.id
            LEFT JOIN Departamentos dd ON fd.departamento_id = dd.id
            WHERE pd.ativa = 1
            AND (pd.data_fim IS NULL OR pd.data_fim > NOW())
            AND (fo.departamento_id = ? OR fd.departamento_id = ?)
            ORDER BY pd.criado_em DESC
        ");
        $stmt->execute([$usuarioLogado['departamento_id'], $usuarioLogado['departamento_id']]);
        $todasDelegacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Busca permissões expirando - filtradas por acesso
    $permissoesExpirando = [];
    if ($ehPresidencia) {
        $permissoesExpirando = $permissoesManager->getPermissoesExpirando(7);
    } else if ($isDiretor) {
        // Filtrar apenas do departamento
        $stmt = $db->prepare("
            SELECT 
                'delegacao' as tipo,
                pd.id,
                pd.permissao,
                pd.data_fim,
                fd.nome as funcionario_nome,
                pd.funcionario_destino as funcionario_id
            FROM Permissoes_Delegadas pd
            JOIN Funcionarios fd ON pd.funcionario_destino = fd.id
            WHERE pd.ativa = 1
            AND pd.data_fim BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            AND fd.departamento_id = ?
            
            UNION ALL
            
            SELECT 
                'temporaria' as tipo,
                pt.id,
                pt.permissao,
                pt.data_fim,
                f.nome as funcionario_nome,
                pt.funcionario_id
            FROM Permissoes_Temporarias pt
            JOIN Funcionarios f ON pt.funcionario_id = f.id
            WHERE pt.ativa = 1
            AND pt.data_fim BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
            AND f.departamento_id = ?
            
            ORDER BY data_fim ASC
        ");
        $stmt->execute([$usuarioLogado['departamento_id'], $usuarioLogado['departamento_id']]);
        $permissoesExpirando = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Estatísticas do departamento/sistema
    $estatisticas = $funcionariosClass->getEstatisticasGerais(
        $ehPresidencia ? [] : ['departamento_id' => $usuarioLogado['departamento_id']]
    );
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    $dashboard = ['delegacoes_ativas' => 0, 'funcionarios_com_delegacao' => 0, 'permissoes_temporarias' => 0, 'grupos_departamento' => 0];
    $funcionariosDepartamento = [];
    $todasDelegacoes = [];
    $permissoesExpirando = [];
    $estatisticas = [];
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'permissoes',
    'notificationCount' => count($permissoesExpirando),
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <!-- CSS Personalizado -->
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #eef2ff;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1f2937;
            --gray-600: #4b5563;
            --gray-400: #9ca3af;
            --white: #ffffff;
            --border-light: #e5e7eb;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
        }

        .main-wrapper {
            display: flex;
            min-height: 100vh;
            background: #f3f4f6;
        }

        .content-area {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .page-subtitle {
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .access-level-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            margin-top: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background: var(--primary-light); color: var(--primary); }
        .stat-icon.success { background: rgba(16, 185, 129, 0.1); color: var(--secondary); }
        .stat-icon.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .stat-icon.info { background: rgba(59, 130, 246, 0.1); color: var(--info); }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-light);
        }

        .tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--gray-600);
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .tab:hover {
            color: var(--primary);
            background: var(--primary-light);
        }

        .tab.active {
            color: var(--primary);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
        }

        .tab-content {
            padding: 2rem;
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }

        /* Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--border-light);
        }

        .btn-secondary:hover {
            background: var(--primary-light);
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            background: #f9fafb;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-light);
        }

        .modern-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            color: var(--gray-600);
        }

        .modern-table tr:hover {
            background: #fafafa;
        }

        .dept-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Badge */
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .badge-info {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }

        /* Modal */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }

        .modal-custom.show {
            display: flex;
        }

        .modal-content-custom {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
            animation: slideUp 0.3s;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header-custom {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title-custom {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .modal-close-custom {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .modal-close-custom:hover {
            background: var(--primary-light);
            color: var(--primary);
        }

        .modal-body-custom {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .modal-footer-custom {
            padding: 1.5rem;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-label span {
            color: var(--danger);
        }

        .form-control-custom {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-text {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        /* Permission Grid */
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .permission-item:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .permission-item.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .permission-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .permission-item label {
            cursor: pointer;
            flex: 1;
            margin: 0;
            font-size: 0.95rem;
        }

        /* Alert */
        .alert-custom {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .alert-info {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            border: 1px solid rgba(79, 70, 229, 0.2);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-400);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 2.5rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-light);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--primary);
        }

        .timeline-date {
            color: var(--gray-400);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            color: var(--gray-600);
        }

        /* Action buttons in table */
        .action-buttons-table {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.875rem;
        }

        .btn-icon.view {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn-icon.edit {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }

        .btn-icon.delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-icon:hover {
            transform: scale(1.1);
        }

        /* Loading */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-area {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                overflow-x: auto;
            }

            .modal-content-custom {
                width: 95%;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">🛡️ Gerenciamento de Permissões</h1>
                <p class="page-subtitle">
                    <?php if ($ehPresidencia): ?>
                        Controle total de permissões do sistema
                    <?php else: ?>
                        Gerencie e delegue permissões para sua equipe do <?php echo htmlspecialchars($dadosUsuarioCompleto['departamento_nome'] ?? 'departamento'); ?>
                    <?php endif; ?>
                </p>
                <div class="access-level-badge">
                    <?php if ($ehPresidencia): ?>
                        <i class="fas fa-crown"></i> Acesso: Presidência - Sistema Completo
                    <?php else: ?>
                        <i class="fas fa-shield-alt"></i> Acesso: Diretor - <?php echo htmlspecialchars($dadosUsuarioCompleto['departamento_nome'] ?? ''); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo count($funcionariosDepartamento); ?></div>
                            <div class="stat-label">
                                <?php if ($ehPresidencia): ?>
                                    Funcionários no Sistema
                                <?php else: ?>
                                    Funcionários no Departamento
                                <?php endif; ?>
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
                            <div class="stat-value"><?php echo count($todasDelegacoes); ?></div>
                            <div class="stat-label">Delegações Ativas</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo count($permissoesExpirando); ?></div>
                            <div class="stat-label">Expirando em 7 dias</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">
                                <?php 
                                    if ($ehPresidencia) {
                                        echo count($estatisticas['por_departamento'] ?? []);
                                    } else {
                                        echo $estatisticas['ativos'] ?? 0;
                                    }
                                ?>
                            </div>
                            <div class="stat-label">
                                <?php if ($ehPresidencia): ?>
                                    Departamentos Ativos
                                <?php else: ?>
                                    Funcionários Ativos
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-key"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert for expiring permissions -->
            <?php if (count($permissoesExpirando) > 0): ?>
            <div class="alert-custom alert-warning" data-aos="fade-up">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong><?php echo count($permissoesExpirando); ?> permissões</strong> expiram nos próximos 7 dias. 
                    <a href="#" onclick="switchTab('expirando'); return false;">Ver detalhes</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabs Container -->
            <div class="tabs-container" data-aos="fade-up" data-aos-delay="100">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('delegacoes')">
                        <i class="fas fa-hand-holding-usd"></i> Delegações
                    </button>
                    <button class="tab" onclick="switchTab('funcionarios')">
                        <i class="fas fa-users"></i> Funcionários
                    </button>
                    <button class="tab" onclick="switchTab('grupos')">
                        <i class="fas fa-layer-group"></i> Grupos
                    </button>
                    <button class="tab" onclick="switchTab('temporarias')">
                        <i class="fas fa-hourglass-half"></i> Temporárias
                    </button>
                    <?php if (count($permissoesExpirando) > 0): ?>
                    <button class="tab" onclick="switchTab('expirando')">
                        <i class="fas fa-exclamation-circle"></i> Expirando (<?php echo count($permissoesExpirando); ?>)
                    </button>
                    <?php endif; ?>
                    <button class="tab" onclick="switchTab('historico')">
                        <i class="fas fa-history"></i> Histórico
                    </button>
                </div>

                <!-- Tab: Delegações -->
                <div id="delegacoes-tab" class="tab-content active">
                    <div class="action-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Buscar delegações..." id="searchDelegacoes">
                        </div>
                        <button class="btn-modern btn-primary" onclick="abrirModalDelegar()">
                            <i class="fas fa-plus"></i> Nova Delegação
                        </button>
                    </div>

                    <div class="table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <?php if ($ehPresidencia): ?>
                                    <th>Departamento</th>
                                    <?php endif; ?>
                                    <th>Permissão</th>
                                    <th>Delegada em</th>
                                    <th>Expira em</th>
                                    <th>Status</th>
                                    <th width="120">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="delegacoesTableBody">
                                <?php if (empty($todasDelegacoes)): ?>
                                <tr>
                                    <td colspan="<?php echo $ehPresidencia ? '7' : '6'; ?>">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>Nenhuma delegação ativa</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($todasDelegacoes as $delegacao): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($delegacao['destino_nome']); ?></strong>
                                        </td>
                                        <?php if ($ehPresidencia): ?>
                                        <td>
                                            <span class="dept-tag"><?php echo htmlspecialchars($delegacao['dept_destino'] ?? 'N/A'); ?></span>
                                        </td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($delegacao['permissao']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($delegacao['criado_em'])); ?></td>
                                        <td>
                                            <?php if ($delegacao['data_fim']): ?>
                                                <?php echo date('d/m/Y', strtotime($delegacao['data_fim'])); ?>
                                            <?php else: ?>
                                                <span class="badge badge-info">Sem prazo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-success">Ativa</span></td>
                                        <td>
                                            <div class="action-buttons-table">
                                                <button class="btn-icon delete" onclick="revogarDelegacao(<?php echo $delegacao['id']; ?>)" title="Revogar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Funcionários -->
                <div id="funcionarios-tab" class="tab-content">
                    <div class="action-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Buscar funcionário..." id="searchFuncionarios">
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cargo</th>
                                    <th>Departamento</th>
                                    <th>Permissões Ativas</th>
                                    <th width="150">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="funcionariosTableBody">
                                <?php foreach ($funcionariosDepartamento as $func): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($func['nome']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($func['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($func['cargo'] ?: 'Sem cargo'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($func['departamento_nome'] ?: 'Sem departamento'); ?>
                                        <?php if ($ehPresidencia && $func['departamento_id'] == 1): ?>
                                            <span class="badge badge-warning">Presidência</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-modern btn-secondary btn-sm" onclick="verPermissoesFuncionario(<?php echo $func['id']; ?>)">
                                            Ver permissões
                                        </button>
                                    </td>
                                    <td>
                                        <div class="action-buttons-table">
                                            <button class="btn-icon view" onclick="verPermissoesFuncionario(<?php echo $func['id']; ?>)" title="Ver Permissões">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php 
                                            // Só pode gerenciar se for presidência ou se for diretor do mesmo departamento
                                            $podeGerenciar = $ehPresidencia || 
                                                           ($isDiretor && $func['departamento_id'] == $usuarioLogado['departamento_id'] && $func['id'] != $usuarioLogado['id']);
                                            if ($podeGerenciar): 
                                            ?>
                                            <button class="btn-icon edit" onclick="gerenciarPermissoes(<?php echo $func['id']; ?>)" title="Gerenciar">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Grupos -->
                <div id="grupos-tab" class="tab-content">
                    <div class="action-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Buscar grupos..." id="searchGrupos">
                        </div>
                        <?php if ($ehPresidencia): ?>
                        <button class="btn-modern btn-primary" onclick="abrirModalNovoGrupo()">
                            <i class="fas fa-plus"></i> Novo Grupo
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <p>Nenhum grupo de permissões cadastrado</p>
                        <?php if ($ehPresidencia): ?>
                        <button class="btn-modern btn-primary mt-3" onclick="abrirModalNovoGrupo()">
                            Criar primeiro grupo
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab: Temporárias -->
                <div id="temporarias-tab" class="tab-content">
                    <div class="action-bar">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Buscar permissões temporárias..." id="searchTemporarias">
                        </div>
                        <button class="btn-modern btn-primary" onclick="abrirModalPermissaoTemporaria()">
                            <i class="fas fa-plus"></i> Nova Permissão Temporária
                        </button>
                    </div>

                    <div class="empty-state">
                        <i class="fas fa-hourglass-half"></i>
                        <p>Nenhuma permissão temporária ativa</p>
                    </div>
                </div>

                <!-- Tab: Expirando -->
                <?php if (count($permissoesExpirando) > 0): ?>
                <div id="expirando-tab" class="tab-content">
                    <div class="table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Funcionário</th>
                                    <th>Permissão</th>
                                    <th>Expira em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissoesExpirando as $perm): ?>
                                <tr>
                                    <td>
                                        <?php if ($perm['tipo'] == 'delegacao'): ?>
                                            <span class="badge badge-info">Delegação</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Temporária</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($perm['funcionario_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($perm['permissao']); ?></td>
                                    <td>
                                        <?php 
                                        $diasRestantes = floor((strtotime($perm['data_fim']) - time()) / (60 * 60 * 24));
                                        if ($diasRestantes <= 3) {
                                            echo '<span class="badge badge-danger">' . $diasRestantes . ' dias</span>';
                                        } else {
                                            echo '<span class="badge badge-warning">' . $diasRestantes . ' dias</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn-modern btn-secondary btn-sm" onclick="renovarPermissao(<?php echo $perm['id']; ?>, '<?php echo $perm['tipo']; ?>')">
                                            <i class="fas fa-sync-alt"></i> Renovar
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tab: Histórico -->
                <div id="historico-tab" class="tab-content">
                    <div class="timeline" id="timelineHistorico">
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>Carregando histórico...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Delegar Permissão -->
    <div class="modal-custom" id="modalDelegar">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Nova Delegação de Permissão</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalDelegar')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formDelegar">
                    <div class="form-group">
                        <label class="form-label">Funcionário <span>*</span></label>
                        <select class="form-control-custom" id="funcionario_destino" required>
                            <option value="">Selecione um funcionário</option>
                            <?php 
                            // Filtrar funcionários disponíveis para delegação
                            foreach ($funcionariosDepartamento as $func): 
                                // Não pode delegar para si mesmo
                                if ($func['id'] == $usuarioLogado['id']) continue;
                                
                                // Se não for presidência, só pode delegar para o mesmo departamento
                                if (!$ehPresidencia && $func['departamento_id'] != $usuarioLogado['departamento_id']) continue;
                            ?>
                                <option value="<?php echo $func['id']; ?>">
                                    <?php echo htmlspecialchars($func['nome'] . ' - ' . ($func['cargo'] ?: 'Sem cargo')); ?>
                                    <?php if ($ehPresidencia): ?>
                                        (<?php echo htmlspecialchars($func['departamento_nome'] ?: 'Sem depto'); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Permissão <span>*</span></label>
                        <select class="form-control-custom" id="permissao_delegar" required>
                            <option value="">Selecione uma permissão</option>
                            <?php 
                            // Listar permissões disponíveis baseado no nível de acesso
                            $permissoesDisponiveis = Permissoes::listarTodasPermissoes();
                            
                            foreach ($permissoesDisponiveis as $key => $desc): 
                                // Se não for presidência, verificar se tem a permissão
                                if (!$ehPresidencia && !$permissoesManager->temPermissao($usuarioLogado['id'], $key)) {
                                    continue;
                                }
                            ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($desc); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Data de Expiração</label>
                        <input type="date" class="form-control-custom" id="data_fim_delegar" 
                               min="<?php echo date('Y-m-d'); ?>">
                        <div class="form-text">Deixe em branco para delegação sem prazo</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Motivo</label>
                        <textarea class="form-control-custom" id="motivo_delegar" rows="3" 
                                  placeholder="Descreva o motivo da delegação..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalDelegar')">
                    Cancelar
                </button>
                <button type="button" class="btn-modern btn-primary" onclick="salvarDelegacao()">
                    <i class="fas fa-save"></i> Salvar Delegação
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Permissões do Funcionário -->
    <div class="modal-custom" id="modalPermissoesFuncionario">
        <div class="modal-content-custom" style="max-width: 700px;">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom" id="modalTitlePermissoes">Permissões do Funcionário</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalPermissoesFuncionario')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <div class="alert-custom alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>Visualizando todas as permissões efetivas do funcionário</div>
                </div>
                
                <div id="permissoesFuncionarioContent">
                    <div class="text-center">
                        <div class="loading-spinner"></div>
                        <p class="text-muted mt-3">Carregando permissões...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalPermissoesFuncionario')">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais
        const usuarioLogadoId = <?php echo json_encode($usuarioLogado['id']); ?>;
        const ehPresidencia = <?php echo json_encode($ehPresidencia); ?>;
        const isDiretor = <?php echo json_encode($isDiretor); ?>;
        const departamentoUsuario = <?php echo json_encode($usuarioLogado['departamento_id']); ?>;

        // Funções de loading
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Switch tabs
        function switchTab(tabName) {
            // Remove active de todas as tabs e conteúdos
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Ativa a tab clicada
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Carrega dados específicos da tab se necessário
            if (tabName === 'historico') {
                carregarHistorico();
            }
        }

        // Abre modal de delegação
        function abrirModalDelegar() {
            document.getElementById('modalDelegar').classList.add('show');
        }

        // Fechar modal
        function fecharModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Salvar delegação
        function salvarDelegacao() {
            const funcionario_destino = document.getElementById('funcionario_destino').value;
            const permissao = document.getElementById('permissao_delegar').value;
            const data_fim = document.getElementById('data_fim_delegar').value;
            const motivo = document.getElementById('motivo_delegar').value;

            if (!funcionario_destino || !permissao) {
                alert('Por favor, selecione um funcionário e uma permissão.');
                return;
            }

            showLoading();

            $.ajax({
                url: '../api/permissoes_delegar.php',
                method: 'POST',
                data: JSON.stringify({
                    funcionario_destino: funcionario_destino,
                    permissao: permissao,
                    data_fim: data_fim,
                    motivo: motivo
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        alert('Permissão delegada com sucesso!');
                        fecharModal('modalDelegar');
                        location.reload(); // Recarrega para atualizar lista
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao delegar permissão');
                }
            });
        }

        // Revogar delegação
        function revogarDelegacao(id) {
            if (!confirm('Tem certeza que deseja revogar esta delegação?')) {
                return;
            }

            showLoading();

            $.ajax({
                url: '../api/permissoes_revogar.php',
                method: 'POST',
                data: JSON.stringify({ delegacao_id: id }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        alert('Delegação revogada com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao revogar delegação');
                }
            });
        }

        // Ver permissões do funcionário
        function verPermissoesFuncionario(funcionarioId) {
            showLoading();
            document.getElementById('modalPermissoesFuncionario').classList.add('show');

            $.ajax({
                url: '../api/permissoes_funcionario.php',
                method: 'GET',
                data: { funcionario_id: funcionarioId },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        const permissoes = response.permissoes;
                        const funcionario = response.funcionario;
                        
                        document.getElementById('modalTitlePermissoes').textContent = 
                            `Permissões de ${funcionario.nome}`;
                        
                        let html = '<div class="permission-grid">';
                        
                        if (permissoes.length === 0) {
                            html = '<div class="empty-state"><i class="fas fa-lock"></i><p>Nenhuma permissão específica</p></div>';
                        } else if (permissoes.includes('*')) {
                            html = '<div class="alert-custom alert-warning"><i class="fas fa-crown"></i> <strong>Acesso Total ao Sistema</strong></div>';
                        } else {
                            permissoes.forEach(perm => {
                                html += `
                                    <div class="permission-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <label>${perm}</label>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }
                        
                        document.getElementById('permissoesFuncionarioContent').innerHTML = html;
                    } else {
                        alert('Erro ao carregar permissões');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    alert('Erro ao buscar permissões');
                }
            });
        }

        // Gerenciar permissões
        function gerenciarPermissoes(funcionarioId) {
            // Implementar modal de gerenciamento
            alert('Funcionalidade em desenvolvimento');
        }

        // Carregar histórico
        function carregarHistorico() {
            showLoading();
            
            $.ajax({
                url: '../api/permissoes_historico.php',
                method: 'GET',
                data: {
                    departamento_id: ehPresidencia ? null : departamentoUsuario
                },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success' && response.historico.length > 0) {
                        let html = '<div class="timeline">';
                        
                        response.historico.forEach(item => {
                            html += `
                                <div class="timeline-item">
                                    <div class="timeline-date">${item.data_formatada}</div>
                                    <div class="timeline-content">
                                        <strong>${item.acao}</strong><br>
                                        ${item.descricao}
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                        document.getElementById('timelineHistorico').innerHTML = html;
                    } else {
                        document.getElementById('timelineHistorico').innerHTML = 
                            '<div class="empty-state"><i class="fas fa-history"></i><p>Nenhum histórico disponível</p></div>';
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                }
            });
        }

        // Renovar permissão
        function renovarPermissao(id, tipo) {
            // Implementar renovação
            alert('Funcionalidade em desenvolvimento');
        }

        // Abrir modal novo grupo
        function abrirModalNovoGrupo() {
            alert('Funcionalidade em desenvolvimento');
        }

        // Abrir modal permissão temporária
        function abrirModalPermissaoTemporaria() {
            alert('Funcionalidade em desenvolvimento');
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Busca em tempo real nas tabelas
            ['searchDelegacoes', 'searchFuncionarios', 'searchGrupos', 'searchTemporarias'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', function() {
                        const searchValue = this.value.toLowerCase();
                        let tableId = '';
                        
                        switch(id) {
                            case 'searchDelegacoes':
                                tableId = 'delegacoesTableBody';
                                break;
                            case 'searchFuncionarios':
                                tableId = 'funcionariosTableBody';
                                break;
                            case 'searchGrupos':
                                // Implementar busca em grupos quando houver
                                return;
                            case 'searchTemporarias':
                                // Implementar busca em temporárias quando houver
                                return;
                        }
                        
                        if (tableId) {
                            const tbody = document.getElementById(tableId);
                            const rows = tbody.getElementsByTagName('tr');
                            
                            for (let row of rows) {
                                const text = row.textContent.toLowerCase();
                                if (text.includes(searchValue)) {
                                    row.style.display = '';
                                } else {
                                    row.style.display = 'none';
                                }
                            }
                        }
                    });
                }
            });

            // Fechar modal ao clicar fora
            document.querySelectorAll('.modal-custom').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            });

            // ESC fecha modais
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal-custom.show').forEach(modal => {
                        modal.classList.remove('show');
                    });
                }
            });
            
            // Limpar formulários ao fechar modais
            document.querySelectorAll('.modal-close-custom').forEach(closeBtn => {
                closeBtn.addEventListener('click', function() {
                    const modal = this.closest('.modal-custom');
                    const forms = modal.querySelectorAll('form');
                    forms.forEach(form => form.reset());
                });
            });
            
            // Adicionar informação de nível de acesso no console
            console.log('Sistema de Permissões carregado');
            console.log('Nível de acesso:', ehPresidencia ? 'Presidência (Total)' : 'Diretor (Departamental)');
            console.log('Departamento:', departamentoUsuario);
        });
    </script>
</body>
</html>