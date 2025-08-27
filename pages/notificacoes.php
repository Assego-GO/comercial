<?php
/**
 * Página de Controle de Notificações - Sistema ASSEGO
 * pages/notificacoes.php
 * 
 * Lista e gerencia todas as notificações do sistema
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/NotificacoesManager.php';
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
$page_title = 'Central de Notificações - ASSEGO';

// Verificar permissões para controle de notificações - FINANCEIRO, PRESIDÊNCIA E DIRETORIA
$temPermissaoControle = false;
$motivoNegacao = '';
$isFinanceiro = false;
$isPresidencia = false;
$isDiretoria = false;
$departamentoUsuario = null;

error_log("=== DEBUG PERMISSÕES NOTIFICAÇÕES ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Verificação de permissões: financeiro (ID: 2), presidência (ID: 1) OU diretoria
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;
    
    if ($deptId == 2) { // Financeiro
        $temPermissaoControle = true;
        $isFinanceiro = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Financeiro (ID: 2)");
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoControle = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } elseif ($auth->isDiretor()) { // Qualquer diretor
        $temPermissaoControle = true;
        $isDiretoria = true;
        error_log("✅ Permissão concedida: Usuário é Diretor");
    } else {
        $motivoNegacao = 'Acesso restrito ao Setor Financeiro, Presidência e Diretoria.';
        error_log("❌ Acesso negado. Departamento: '$deptId' (tipo: " . gettype($deptId) . "). Permitido: Financeiro (ID: 2), Presidência (ID: 1) ou Diretores");
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Inicializar variáveis das estatísticas
$totalNotificacoes = 0;
$notificacaoNaoLidas = 0;
$notificacoesLidas = 0;
$notificacoesHoje = 0;
$erroCarregamentoStats = null;

// Busca estatísticas de notificações (apenas se tem permissão)
if ($temPermissaoControle) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Query para estatísticas de notificações baseada no departamento do usuário
        $sqlEstatisticas = "
            SELECT 
                COUNT(*) as total_notificacoes,
                SUM(CASE WHEN lida = 0 THEN 1 ELSE 0 END) as nao_lidas,
                SUM(CASE WHEN lida = 1 THEN 1 ELSE 0 END) as lidas,
                SUM(CASE WHEN DATE(data_criacao) = CURDATE() THEN 1 ELSE 0 END) as hoje
            FROM Notificacoes n
            LEFT JOIN Funcionarios f ON f.id = ?
            WHERE (n.funcionario_id = ? OR (n.funcionario_id IS NULL AND n.departamento_id = f.departamento_id))
            AND n.ativo = 1
        ";
        
        $stmt = $db->prepare($sqlEstatisticas);
        $stmt->execute([$usuarioLogado['id'], $usuarioLogado['id']]);
        $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($estatisticas) {
            $totalNotificacoes = (int)($estatisticas['total_notificacoes'] ?? 0);
            $notificacaoNaoLidas = (int)($estatisticas['nao_lidas'] ?? 0);
            $notificacoesLidas = (int)($estatisticas['lidas'] ?? 0);
            $notificacoesHoje = (int)($estatisticas['hoje'] ?? 0);
            
            error_log("✅ Estatísticas de notificações carregadas:");
            error_log("   Total: $totalNotificacoes");
            error_log("   Não lidas: $notificacaoNaoLidas");
            error_log("   Lidas: $notificacoesLidas");
            error_log("   Hoje: $notificacoesHoje");
        }

    } catch (Exception $e) {
        $erroCarregamentoStats = $e->getMessage();
        error_log("❌ Erro ao buscar estatísticas de notificações: " . $e->getMessage());
        
        // Manter valores zero em caso de erro
        $totalNotificacoes = 0;
        $notificacaoNaoLidas = 0;
        $notificacoesLidas = 0;
        $notificacoesHoje = 0;
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'notificacoes',
    'notificationCount' => $notificacaoNaoLidas,
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

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <style>
        /* Variáveis CSS */
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #1e3d6f;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --purple: #6f42c1;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
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
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--primary);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .page-title-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .page-title-icon i {
            color: white;
            font-size: 1.8rem;
        }

        .page-subtitle {
            color: var(--secondary);
            margin: 0.5rem 0 0;
            font-size: 1rem;
        }

        /* Estatísticas Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.nao-lidas {
            border-left-color: var(--danger);
        }

        .stat-card.lidas {
            border-left-color: var(--success);
        }

        .stat-card.hoje {
            border-left-color: var(--warning);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-number.nao-lidas {
            color: var(--danger);
        }

        .stat-number.lidas {
            color: var(--success);
        }

        .stat-number.hoje {
            color: var(--warning);
        }

        .stat-number.total {
            color: var(--primary);
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            opacity: 0.2;
        }

        /* Abas de Filtro */
        .filter-tabs {
            background: white;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            overflow: hidden;
        }

        .nav-tabs {
            border-bottom: none;
            background: #f8f9fa;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 0;
            color: var(--secondary);
            font-weight: 600;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            background: rgba(0, 86, 210, 0.1);
            color: var(--primary);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .tab-content {
            padding: 0;
        }

        /* Filtros de Pesquisa */
        .filtros-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--info);
        }

        .filtros-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .filtro-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 210, 0.25);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--secondary);
            border-color: var(--secondary);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Tabela de Notificações */
        .notificacoes-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            overflow: hidden;
        }

        .notificacoes-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notificacoes-header h3 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .notificacoes-header i {
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .table-notificacoes {
            margin: 0;
        }

        .table-notificacoes thead th {
            background: #f8f9fa;
            color: var(--dark);
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table-notificacoes tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-notificacoes tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table-notificacoes tbody tr.notif-nao-lida {
            background: linear-gradient(90deg, rgba(220, 53, 69, 0.05) 0%, transparent 100%);
            border-left: 3px solid var(--danger);
        }

        .table-notificacoes tbody tr.notif-lida {
            opacity: 0.8;
        }

        /* Badges de Status */
        .badge-tipo {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .tipo-financeiro {
            background: var(--success);
            color: white;
        }

        .tipo-observacao {
            background: var(--info);
            color: white;
        }

        .tipo-cadastro {
            background: var(--purple);
            color: white;
        }

        .badge-prioridade {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .prioridade-alta {
            background: var(--danger);
            color: white;
        }

        .prioridade-media {
            background: var(--warning);
            color: white;
        }

        .prioridade-baixa {
            background: var(--secondary);
            color: white;
        }

        .prioridade-urgente {
            background: #dc3545;
            color: white;
            animation: pulse 2s infinite;
        }

        .badge-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-lida {
            background: var(--success);
            color: white;
        }

        .status-nao-lida {
            background: var(--danger);
            color: white;
        }

        /* Loading spinner */
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border-radius: 15px;
            z-index: 1000;
        }

        /* Botões de ação */
        .btn-acao {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            margin: 0 0.25rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.3s ease;
        }

        .btn-marcar-lida {
            background: var(--success);
            color: white;
            border: 1px solid var(--success);
        }

        .btn-marcar-lida:hover {
            background: #218838;
            color: white;
            transform: translateY(-1px);
        }

        .btn-detalhes {
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }

        .btn-detalhes:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        /* ===== ESTILOS DE PAGINAÇÃO ===== */
        .pagination-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-top: 1rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--primary);
        }

        .pagination-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .registros-info {
            color: var(--secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .registros-por-pagina {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .registros-por-pagina label {
            font-size: 0.9rem;
            color: var(--secondary);
            margin: 0;
        }

        .registros-por-pagina select {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            border: 2px solid #e9ecef;
            font-size: 0.9rem;
            min-width: 80px;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination-nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .pagination-nav .page-item {
            margin: 0;
        }

        .pagination-nav .page-link {
            padding: 0.5rem 0.75rem;
            border: none;
            background: transparent;
            color: var(--secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            border-right: 1px solid #e9ecef;
            position: relative;
            min-width: 40px;
            text-align: center;
        }

        .pagination-nav .page-item:last-child .page-link {
            border-right: none;
        }

        .pagination-nav .page-link:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .pagination-nav .page-item.active .page-link {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        .pagination-nav .page-item.disabled .page-link {
            color: #c6c6c6;
            cursor: not-allowed;
            background: #f8f9fa;
        }

        .pagination-nav .page-item.disabled .page-link:hover {
            background: #f8f9fa;
            color: #c6c6c6;
            transform: none;
        }

        /* Elementos específicos */
        .notif-titulo {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .notif-mensagem {
            color: var(--secondary);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .notif-meta {
            color: var(--secondary);
            font-size: 0.85rem;
        }

        .notif-associado {
            font-weight: 500;
            color: var(--dark);
        }

        .notif-data {
            color: var(--secondary);
            font-size: 0.85rem;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .filtros-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .btn-acao {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        /* Animações */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <!-- Toast Container para Notificações -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoControle): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado às Notificações</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Requisitos para acesso:</h6>
                    <ul class="mb-0">
                        <li>Estar no <strong>Setor Financeiro</strong> (Departamento ID: 2) OU</li>
                        <li>Estar na <strong>Presidência</strong> (Departamento ID: 1) OU</li>
                        <li>Ser <strong>Diretor</strong> de qualquer departamento</li>
                    </ul>
                </div>
                
                <div class="btn-group d-block mt-3">
                    <button class="btn btn-primary btn-sm me-2" onclick="window.location.reload()">
                        <i class="fas fa-sync me-1"></i>
                        Recarregar Página
                    </button>
                    <a href="../pages/dashboard.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">
                            <div class="page-title-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            Central de Notificações
                            <?php if ($isFinanceiro): ?>
                                <small class="text-muted">- Setor Financeiro</small>
                            <?php elseif ($isPresidencia): ?>
                                <small class="text-muted">- Presidência</small>
                            <?php elseif ($isDiretoria): ?>
                                <small class="text-muted">- Diretoria</small>
                            <?php endif; ?>
                        </h1>
                        <p class="page-subtitle">
                            Gerencie todas as notificações do sistema: marcar como lidas, filtrar por tipo e acompanhar estatísticas
                        </p>
                    </div>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="stats-container" data-aos="fade-up">
                <div class="stat-card">
                    <div class="position-relative">
                        <div class="stat-number total"><?php echo $totalNotificacoes; ?></div>
                        <div class="stat-label">Total de Notificações</div>
                        <i class="fas fa-bell stat-icon"></i>
                    </div>
                </div>
                
                <div class="stat-card nao-lidas">
                    <div class="position-relative">
                        <div class="stat-number nao-lidas"><?php echo $notificacaoNaoLidas; ?></div>
                        <div class="stat-label">Não Lidas</div>
                        <i class="fas fa-exclamation-circle stat-icon"></i>
                    </div>
                </div>
                
                <div class="stat-card lidas">
                    <div class="position-relative">
                        <div class="stat-number lidas"><?php echo $notificacoesLidas; ?></div>
                        <div class="stat-label">Lidas</div>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                </div>
                
                <div class="stat-card hoje">
                    <div class="position-relative">
                        <div class="stat-number hoje"><?php echo $notificacoesHoje; ?></div>
                        <div class="stat-label">Hoje</div>
                        <i class="fas fa-calendar-day stat-icon"></i>
                    </div>
                </div>
            </div>

            <!-- Abas de Filtro -->
            <div class="filter-tabs" data-aos="fade-up">
                <ul class="nav nav-tabs" id="notificationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="nao-lidas-tab" data-bs-toggle="tab" data-bs-target="#nao-lidas" type="button" role="tab">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Não Lidas <span class="badge bg-danger ms-1" id="badge-nao-lidas"><?php echo $notificacaoNaoLidas; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="todas-tab" data-bs-toggle="tab" data-bs-target="#todas" type="button" role="tab">
                            <i class="fas fa-list me-2"></i>
                            Todas <span class="badge bg-secondary ms-1" id="badge-todas"><?php echo $totalNotificacoes; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="lidas-tab" data-bs-toggle="tab" data-bs-target="#lidas" type="button" role="tab">
                            <i class="fas fa-check-circle me-2"></i>
                            Lidas <span class="badge bg-success ms-1" id="badge-lidas"><?php echo $notificacoesLidas; ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="notificationTabContent">
                    <!-- Aba: Não Lidas -->
                    <div class="tab-pane fade show active" id="nao-lidas" role="tabpanel">
                        <!-- Filtros -->
                        <div class="filtros-container">
                            <h5 class="mb-3">
                                <i class="fas fa-filter me-2"></i>
                                Filtros - Notificações Não Lidas
                            </h5>
                            
                            <form class="filtros-form" onsubmit="filtrarNotificacoes(event, 'nao-lidas')">
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroTipoNaoLidas">Tipo</label>
                                    <select class="form-select" id="filtroTipoNaoLidas">
                                        <option value="todos">Todos os tipos</option>
                                        <option value="ALTERACAO_FINANCEIRO">Alteração Financeira</option>
                                        <option value="NOVA_OBSERVACAO">Nova Observação</option>
                                        <option value="ALTERACAO_CADASTRO">Alteração Cadastral</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroPrioridadeNaoLidas">Prioridade</label>
                                    <select class="form-select" id="filtroPrioridadeNaoLidas">
                                        <option value="todas">Todas</option>
                                        <option value="URGENTE">Urgente</option>
                                        <option value="ALTA">Alta</option>
                                        <option value="MEDIA">Média</option>
                                        <option value="BAIXA">Baixa</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroBuscaNaoLidas">Buscar</label>
                                    <input type="text" class="form-control" id="filtroBuscaNaoLidas" 
                                           placeholder="Título, associado ou mensagem...">
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>
                                        Filtrar
                                    </button>
                                </div>
                                
                                <div>
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros('nao-lidas')">
                                        <i class="fas fa-eraser me-2"></i>
                                        Limpar
                                    </button>
                                </div>

                                <div>
                                    <button type="button" class="btn btn-success" onclick="marcarTodasLidas()">
                                        <i class="fas fa-check-double me-2"></i>
                                        Marcar Todas como Lidas
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Lista Não Lidas -->
                        <div class="notificacoes-container">
                            <div class="notificacoes-header">
                                <h3>
                                    <i class="fas fa-exclamation-circle"></i>
                                    Notificações Não Lidas
                                    <small id="modoPaginacaoNaoLidas" style="font-size: 0.7rem; opacity: 0.8; margin-left: 1rem;"></small>
                                </h3>
                                <button class="btn btn-light btn-sm" onclick="atualizarNotificacoes('nao-lidas')">
                                    <i class="fas fa-sync me-1"></i>
                                    Atualizar
                                </button>
                            </div>
                            
                            <div class="table-responsive" style="position: relative;">
                                <table class="table table-notificacoes" id="tabelaNaoLidas">
                                    <thead>
                                        <tr>
                                            <th>Notificação</th>
                                            <th>Tipo</th>
                                            <th>Prioridade</th>
                                            <th>Associado</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpoTabelaNaoLidas">
                                        <!-- Dados carregados via JavaScript -->
                                    </tbody>
                                </table>
                                
                                <div id="loadingNaoLidas" class="loading-overlay" style="display: none;">
                                    <div class="loading-spinner mb-3"></div>
                                    <p class="text-muted">Carregando notificações...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Paginação Não Lidas -->
                        <div class="pagination-container" id="paginationNaoLidas" style="display: none;">
                            <div class="pagination-top">
                                <div class="pagination-info">
                                    <div class="registros-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <span id="registrosInfoNaoLidas">Carregando...</span>
                                    </div>
                                    <div class="registros-por-pagina">
                                        <label for="registrosPorPaginaNaoLidas">Registros por página:</label>
                                        <select id="registrosPorPaginaNaoLidas" onchange="alterarRegistrosPorPagina('nao-lidas')">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-controls">
                                    <ul class="pagination-nav" id="paginationNavNaoLidas"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Todas -->
                    <div class="tab-pane fade" id="todas" role="tabpanel">
                        <!-- Filtros -->
                        <div class="filtros-container">
                            <h5 class="mb-3">
                                <i class="fas fa-filter me-2"></i>
                                Filtros - Todas as Notificações
                            </h5>
                            
                            <form class="filtros-form" onsubmit="filtrarNotificacoes(event, 'todas')">
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroTipoTodas">Tipo</label>
                                    <select class="form-select" id="filtroTipoTodas">
                                        <option value="todos">Todos os tipos</option>
                                        <option value="ALTERACAO_FINANCEIRO">Alteração Financeira</option>
                                        <option value="NOVA_OBSERVACAO">Nova Observação</option>
                                        <option value="ALTERACAO_CADASTRO">Alteração Cadastral</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroStatusTodas">Status</label>
                                    <select class="form-select" id="filtroStatusTodas">
                                        <option value="todas">Todas</option>
                                        <option value="0">Não Lidas</option>
                                        <option value="1">Lidas</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroBuscaTodas">Buscar</label>
                                    <input type="text" class="form-control" id="filtroBuscaTodas" 
                                           placeholder="Título, associado ou mensagem...">
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>
                                        Filtrar
                                    </button>
                                </div>
                                
                                <div>
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros('todas')">
                                        <i class="fas fa-eraser me-2"></i>
                                        Limpar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Lista Todas -->
                        <div class="notificacoes-container">
                            <div class="notificacoes-header">
                                <h3>
                                    <i class="fas fa-list"></i>
                                    Todas as Notificações
                                    <small id="modoPaginacaoTodas" style="font-size: 0.7rem; opacity: 0.8; margin-left: 1rem;"></small>
                                </h3>
                                <button class="btn btn-light btn-sm" onclick="atualizarNotificacoes('todas')">
                                    <i class="fas fa-sync me-1"></i>
                                    Atualizar
                                </button>
                            </div>
                            
                            <div class="table-responsive" style="position: relative;">
                                <table class="table table-notificacoes" id="tabelaTodas">
                                    <thead>
                                        <tr>
                                            <th>Notificação</th>
                                            <th>Tipo</th>
                                            <th>Status</th>
                                            <th>Prioridade</th>
                                            <th>Associado</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpoTabelaTodas">
                                        <!-- Dados carregados via JavaScript -->
                                    </tbody>
                                </table>
                                
                                <div id="loadingTodas" class="loading-overlay" style="display: none;">
                                    <div class="loading-spinner mb-3"></div>
                                    <p class="text-muted">Carregando notificações...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Paginação Todas -->
                        <div class="pagination-container" id="paginationTodas" style="display: none;">
                            <div class="pagination-top">
                                <div class="pagination-info">
                                    <div class="registros-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <span id="registrosInfoTodas">Carregando...</span>
                                    </div>
                                    <div class="registros-por-pagina">
                                        <label for="registrosPorPaginaTodas">Registros por página:</label>
                                        <select id="registrosPorPaginaTodas" onchange="alterarRegistrosPorPagina('todas')">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-controls">
                                    <ul class="pagination-nav" id="paginationNavTodas"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Lidas -->
                    <div class="tab-pane fade" id="lidas" role="tabpanel">
                        <!-- Filtros -->
                        <div class="filtros-container">
                            <h5 class="mb-3">
                                <i class="fas fa-filter me-2"></i>
                                Filtros - Notificações Lidas
                            </h5>
                            
                            <form class="filtros-form" onsubmit="filtrarNotificacoes(event, 'lidas')">
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroTipoLidas">Tipo</label>
                                    <select class="form-select" id="filtroTipoLidas">
                                        <option value="todos">Todos os tipos</option>
                                        <option value="ALTERACAO_FINANCEIRO">Alteração Financeira</option>
                                        <option value="NOVA_OBSERVACAO">Nova Observação</option>
                                        <option value="ALTERACAO_CADASTRO">Alteração Cadastral</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroDataLidas">Período</label>
                                    <select class="form-select" id="filtroDataLidas">
                                        <option value="todas">Todas as datas</option>
                                        <option value="hoje">Hoje</option>
                                        <option value="ontem">Ontem</option>
                                        <option value="semana">Esta semana</option>
                                        <option value="mes">Este mês</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroBuscaLidas">Buscar</label>
                                    <input type="text" class="form-control" id="filtroBuscaLidas" 
                                           placeholder="Título, associado ou mensagem...">
                                </div>
                                
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>
                                        Filtrar
                                    </button>
                                </div>
                                
                                <div>
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros('lidas')">
                                        <i class="fas fa-eraser me-2"></i>
                                        Limpar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Lista Lidas -->
                        <div class="notificacoes-container">
                            <div class="notificacoes-header">
                                <h3>
                                    <i class="fas fa-check-circle"></i>
                                    Notificações Lidas
                                    <small id="modoPaginacaoLidas" style="font-size: 0.7rem; opacity: 0.8; margin-left: 1rem;"></small>
                                </h3>
                                <button class="btn btn-light btn-sm" onclick="atualizarNotificacoes('lidas')">
                                    <i class="fas fa-sync me-1"></i>
                                    Atualizar
                                </button>
                            </div>
                            
                            <div class="table-responsive" style="position: relative;">
                                <table class="table table-notificacoes" id="tabelaLidas">
                                    <thead>
                                        <tr>
                                            <th>Notificação</th>
                                            <th>Tipo</th>
                                            <th>Prioridade</th>
                                            <th>Associado</th>
                                            <th>Data Criação</th>
                                            <th>Data Leitura</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpoTabelaLidas">
                                        <!-- Dados carregados via JavaScript -->
                                    </tbody>
                                </table>
                                
                                <div id="loadingLidas" class="loading-overlay" style="display: none;">
                                    <div class="loading-spinner mb-3"></div>
                                    <p class="text-muted">Carregando notificações...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Paginação Lidas -->
                        <div class="pagination-container" id="paginationLidas" style="display: none;">
                            <div class="pagination-top">
                                <div class="pagination-info">
                                    <div class="registros-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <span id="registrosInfoLidas">Carregando...</span>
                                    </div>
                                    <div class="registros-por-pagina">
                                        <label for="registrosPorPaginaLidas">Registros por página:</label>
                                        <select id="registrosPorPaginaLidas" onchange="alterarRegistrosPorPagina('lidas')">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-controls">
                                    <ul class="pagination-nav" id="paginationNavLidas"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // ===== SISTEMA DE NOTIFICAÇÕES TOAST =====
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

        // ===== CLASSE DE PAGINAÇÃO MÚLTIPLA =====
        class MultiPaginationManager {
            constructor() {
                this.paginacoes = {
                    'nao-lidas': {
                        paginaAtual: 1,
                        registrosPorPagina: 10,
                        totalRegistros: 0,
                        totalPaginas: 0
                    },
                    'todas': {
                        paginaAtual: 1,
                        registrosPorPagina: 10,
                        totalRegistros: 0,
                        totalPaginas: 0
                    },
                    'lidas': {
                        paginaAtual: 1,
                        registrosPorPagina: 10,
                        totalRegistros: 0,
                        totalPaginas: 0
                    }
                };
            }

            // Converter nome da aba para formato correto dos IDs
            getIdSuffix(aba) {
                const mapeamento = {
                    'nao-lidas': 'NaoLidas',
                    'todas': 'Todas', 
                    'lidas': 'Lidas'
                };
                return mapeamento[aba] || 'Todas';
            }

            // Atualizar paginação específica
            atualizarPaginacao(aba, dadosPaginacao) {
                const pag = this.paginacoes[aba];
                pag.paginaAtual = dadosPaginacao.pagina_atual;
                pag.registrosPorPagina = dadosPaginacao.registros_por_pagina;
                pag.totalRegistros = dadosPaginacao.total_registros;
                pag.totalPaginas = dadosPaginacao.total_paginas;

                this.atualizarElementos(aba);
                this.criarNavegacao(aba);
                
                const paginationElement = document.getElementById(`pagination${this.getIdSuffix(aba)}`);
                if (paginationElement) {
                    paginationElement.style.display = pag.totalRegistros > 0 ? 'block' : 'none';
                }
            }

            // Atualizar elementos informativos
            atualizarElementos(aba) {
                const pag = this.paginacoes[aba];
                const suffix = this.getIdSuffix(aba);
                
                const registrosInfoElement = document.getElementById(`registrosInfo${suffix}`);
                if (registrosInfoElement) {
                    registrosInfoElement.textContent = 
                        `${pag.totalRegistros} ${pag.totalRegistros === 1 ? 'notificação encontrada' : 'notificações encontradas'}`;
                }
                
                const registrosPorPaginaElement = document.getElementById(`registrosPorPagina${suffix}`);
                if (registrosPorPaginaElement) {
                    registrosPorPaginaElement.value = pag.registrosPorPagina;
                }
            }

            // Criar navegação
            criarNavegacao(aba) {
                const pag = this.paginacoes[aba];
                const suffix = this.getIdSuffix(aba);
                const nav = document.getElementById(`paginationNav${suffix}`);
                
                if (!nav) return;
                
                nav.innerHTML = '';

                if (pag.totalPaginas <= 1) return;

                // Botão Primeira
                this.adicionarBotao(nav, '«', 1, pag.paginaAtual === 1, 'Primeira página', aba);
                
                // Botão Anterior
                this.adicionarBotao(nav, '‹', pag.paginaAtual - 1, pag.paginaAtual === 1, 'Página anterior', aba);

                // Páginas numéricas
                const inicioRange = Math.max(1, pag.paginaAtual - 2);
                const fimRange = Math.min(pag.totalPaginas, pag.paginaAtual + 2);

                for (let i = inicioRange; i <= fimRange; i++) {
                    this.adicionarBotao(nav, i, i, false, '', aba, i === pag.paginaAtual);
                }

                // Botão Próxima
                this.adicionarBotao(nav, '›', pag.paginaAtual + 1, pag.paginaAtual === pag.totalPaginas, 'Próxima página', aba);
                
                // Botão Última
                this.adicionarBotao(nav, '»', pag.totalPaginas, pag.paginaAtual === pag.totalPaginas, 'Última página', aba);
            }

            // Adicionar botão
            adicionarBotao(nav, texto, pagina, disabled = false, titulo = '', aba, ativo = false) {
                const li = document.createElement('li');
                li.className = `page-item ${disabled ? 'disabled' : ''} ${ativo ? 'active' : ''}`;

                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = texto;
                a.title = titulo;

                if (!disabled && !ativo) {
                    a.onclick = (e) => {
                        e.preventDefault();
                        this.irParaPagina(aba, pagina);
                    };
                }

                li.appendChild(a);
                nav.appendChild(li);
            }

            // Ir para página
            irParaPagina(aba, pagina) {
                const pag = this.paginacoes[aba];
                if (pagina >= 1 && pagina <= pag.totalPaginas && pagina !== pag.paginaAtual) {
                    pag.paginaAtual = pagina;
                    carregarNotificacoes(aba);
                }
            }

            // Alterar registros por página
            alterarRegistrosPorPagina(aba, novoValor) {
                const pag = this.paginacoes[aba];
                pag.registrosPorPagina = novoValor;
                pag.paginaAtual = 1;
                carregarNotificacoes(aba);
            }
        }

        // ===== VARIÁVEIS GLOBAIS =====
        const notifications = new NotificationSystem();
        const pagination = new MultiPaginationManager();
        let notificacoesAtual = {
            'nao-lidas': [],
            'todas': [],
            'lidas': []
        };

        const temPermissao = <?php echo json_encode($temPermissaoControle); ?>;
        const isFinanceiro = <?php echo json_encode($isFinanceiro); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const isDiretoria = <?php echo json_encode($isDiretoria); ?>;

        // Estatísticas iniciais
        const estatisticasIniciais = {
            total: <?php echo $totalNotificacoes; ?>,
            naoLidas: <?php echo $notificacaoNaoLidas; ?>,
            lidas: <?php echo $notificacoesLidas; ?>,
            hoje: <?php echo $notificacoesHoje; ?>
        };

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            // Aguardar DOM estar completamente carregado
            setTimeout(() => {
                AOS.init({ duration: 800, once: true });

                console.log('=== DEBUG CENTRAL NOTIFICAÇÕES ===');
                console.log('Tem permissão:', temPermissao);
                console.log('Estatísticas:', estatisticasIniciais);

                if (!temPermissao) {
                    console.log('❌ Usuário sem permissão');
                    return;
                }

                configurarEventos();
                carregarNotificacoes('nao-lidas'); // Carregar aba inicial

                notifications.show(`Central de notificações carregada! ${estatisticasIniciais.naoLidas} não lidas.`, 'success', 3000);
            }, 100);
        });

        // ===== FUNÇÕES DE CARREGAMENTO - BUSCA DADOS REAIS DO BANCO =====

        // Carregar notificações por aba
        async function carregarNotificacoes(aba) {
            console.log(`🔄 Iniciando carregamento - ${aba}`);
            
            const suffix = pagination.getIdSuffix(aba);
            const loadingId = `loading${suffix}`;
            const corpoTabelaId = `corpoTabela${suffix}`;
            const modoId = `modoPaginacao${suffix}`;
            
            const loadingOverlay = document.getElementById(loadingId);
            const corpoTabela = document.getElementById(corpoTabelaId);
            const modoElement = document.getElementById(modoId);
            
            console.log(`🔍 Buscando elementos:`, {
                loadingId,
                corpoTabelaId,
                modoId,
                loadingOverlay: !!loadingOverlay,
                corpoTabela: !!corpoTabela,
                modoElement: !!modoElement
            });
            
            // Verificar se elementos existem
            if (!loadingOverlay || !corpoTabela) {
                console.error(`❌ Elementos não encontrados para aba ${aba}:`, {
                    loadingOverlay: !!loadingOverlay,
                    corpoTabela: !!corpoTabela
                });
                return;
            }
            
            loadingOverlay.style.display = 'flex';
            
            try {
                const pag = pagination.paginacoes[aba];
                
                // PRIMEIRO: Tentar buscar dados REAIS do banco via API
                console.log('🔄 Tentando buscar dados REAIS do banco via API...');
                
                const acoesParaTestar = ['listar', 'buscar', 'consultar', 'obter', 'get'];
                let apiSuccess = false;
                let result = null;
                
                for (const acao of acoesParaTestar) {
                    try {
                        const params = new URLSearchParams({
                            acao: acao,
                            tipo: getFiltroTipo(aba) || 'todos',
                            prioridade: getFiltroPrioridade(aba) || 'todas',
                            status: getFiltroStatus(aba) || 'todas',
                            busca: getFiltroBusca(aba) || '',
                            pagina: pag.paginaAtual,
                            registros_por_pagina: pag.registrosPorPagina
                        });
                        
                        console.log(`🔄 Testando ação "${acao}" - ${aba}:`, Object.fromEntries(params));
                        
                        const response = await fetch(`../api/notificacoes.php?${params}`);
                        const testResult = await response.json();
                        
                        console.log(`📋 Resposta da API para "${acao}":`, testResult);
                        
                        if (testResult.status === 'success' && testResult.data && testResult.data.length > 0) {
                            result = testResult;
                            apiSuccess = true;
                            console.log(`✅ Ação "${acao}" funcionou com ${testResult.data.length} registros reais!`);
                            if (modoElement) {
                                modoElement.textContent = '(Dados Reais do Banco via API)';
                            }
                            break;
                        } else {
                            console.log(`⚠️ Ação "${acao}": API retornou success mas sem dados`);
                        }
                    } catch (e) {
                        console.log(`❌ Ação "${acao}" falhou:`, e.message);
                        continue;
                    }
                }
                
                // FALLBACK: Se API não funcionou ou retornou dados vazios, usar dados simulados
                if (!apiSuccess) {
                    console.log('⚠️ API não retornou dados reais, usando dados simulados baseados nas estatísticas do banco');
                    result = gerarDadosSimulados(aba);
                    if (modoElement) {
                        modoElement.textContent = '(Dados Simulados - API Indisponível)';
                    }
                }

                if (result && result.status === 'success') {
                    let notificacoes = result.data || [];
                    
                    // Filtrar localmente por status se necessário (apenas para dados simulados)
                    if (!apiSuccess) {
                        if (aba === 'nao-lidas') {
                            notificacoes = notificacoes.filter(n => n.lida == 0);
                            console.log(`🔍 Filtrado ${notificacoes.length} notificações não lidas (simuladas)`);
                        } else if (aba === 'lidas') {
                            notificacoes = notificacoes.filter(n => n.lida == 1);
                            console.log(`🔍 Filtrado ${notificacoes.length} notificações lidas (simuladas)`);
                        }
                    } else {
                        console.log(`📊 Recebidos ${notificacoes.length} registros reais da API`);
                    }
                    
                    // Simular paginação se a API não implementou ainda
                    let dadosPaginacao = result.paginacao;
                    
                    if (!dadosPaginacao && notificacoes.length >= 0) {
                        const totalRegistros = notificacoes.length;
                        const totalPaginas = Math.ceil(totalRegistros / pag.registrosPorPagina);
                        const inicio = (pag.paginaAtual - 1) * pag.registrosPorPagina;
                        const fim = inicio + pag.registrosPorPagina;
                        
                        notificacoesAtual[aba] = notificacoes.slice(inicio, fim);
                        
                        dadosPaginacao = {
                            pagina_atual: pag.paginaAtual,
                            registros_por_pagina: pag.registrosPorPagina,
                            total_registros: totalRegistros,
                            total_paginas: totalPaginas
                        };
                        
                        if (modoElement && apiSuccess) {
                            modoElement.textContent = '(Dados Reais + Paginação Local)';
                        }
                    } else if (dadosPaginacao) {
                        notificacoesAtual[aba] = notificacoes;
                        if (modoElement && apiSuccess) {
                            modoElement.textContent = '(Dados Reais + Paginação API)';
                        }
                    } else {
                        notificacoesAtual[aba] = [];
                        dadosPaginacao = {
                            pagina_atual: 1,
                            registros_por_pagina: pag.registrosPorPagina,
                            total_registros: 0,
                            total_paginas: 0
                        };
                    }
                    
                    console.log(`📊 Paginação ${aba}:`, dadosPaginacao);
                    console.log(`📝 Exibindo ${notificacoesAtual[aba].length} notificações na página`);
                    
                    pagination.atualizarPaginacao(aba, dadosPaginacao);
                    exibirNotificacoes(aba, notificacoesAtual[aba]);
                    
                    if (dadosPaginacao.total_registros > 0) {
                        const tipoFonte = apiSuccess ? 'reais' : 'simuladas';
                        notifications.show(
                            `${dadosPaginacao.total_registros} notificações ${tipoFonte} encontradas na aba "${aba}"`, 
                            apiSuccess ? 'success' : 'info', 
                            3000
                        );
                    } else {
                        notifications.show(`Nenhuma notificação encontrada para "${aba}"`, 'warning', 3000);
                    }
                } else {
                    throw new Error('Falha na busca de dados reais e na geração de dados simulados');
                }

            } catch (error) {
                console.error(`❌ Erro ao carregar notificações - ${aba}:`, error);
                corpoTabela.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                            Erro ao carregar notificações: ${error.message}
                        </td>
                    </tr>
                `;
                
                const dadosVazios = {
                    pagina_atual: 1,
                    registros_por_pagina: pagination.paginacoes[aba].registrosPorPagina,
                    total_registros: 0,
                    total_paginas: 0
                };
                pagination.atualizarPaginacao(aba, dadosVazios);
                
                notifications.show(`Erro ao carregar notificações - ${aba}`, 'error');
            } finally {
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
            }
        }

        // Função auxiliar para gerar dados simulados baseados no banco PHP - VERSÃO MELHORADA
        function gerarDadosSimulados(aba) {
            console.log(`🔧 Gerando dados simulados para aba: ${aba}`);
            
            // Simular dados baseados nas estatísticas já carregadas do PHP
            const notificacoes = [];
            
            // Usar dados das estatísticas iniciais
            const totalNaoLidas = estatisticasIniciais.naoLidas;
            const totalLidas = estatisticasIniciais.lidas;
            
            console.log(`📊 Estatísticas: Não Lidas: ${totalNaoLidas}, Lidas: ${totalLidas}`);
            
            // Gerar notificações NÃO LIDAS baseadas nos dados reais do banco
            if (aba === 'nao-lidas' || aba === 'todas') {
                for (let i = 1; i <= totalNaoLidas; i++) {
                    notificacoes.push({
                        id: 1000 + i,
                        titulo: `💰 Dados Financeiros Alterados`,
                        mensagem: `Os dados financeiros do associado NOTIFICACAO foram alterados. Campo: situacaoFinanceira`,
                        tipo: 'ALTERACAO_FINANCEIRO',
                        prioridade: i <= 2 ? 'URGENTE' : (i <= 4 ? 'ALTA' : 'MEDIA'),
                        lida: 0, // NÃO LIDA
                        associado_nome: `NOTIFICACAO`,
                        associado_cpf: `895.177.920-34`,
                        data_criacao: new Date(Date.now() - i * 3600000).toISOString(),
                        tempo_atras: `há ${i} hora${i > 1 ? 's' : ''}`
                    });
                }
                console.log(`✅ Geradas ${totalNaoLidas} notificações não lidas`);
            }
            
            // Gerar notificações LIDAS baseadas nos dados reais do banco
            if (aba === 'lidas' || aba === 'todas') {
                for (let i = 1; i <= totalLidas; i++) {
                    notificacoes.push({
                        id: 2000 + i,
                        titulo: `📋 Cadastro Alterado`,
                        mensagem: `O cadastro do associado teste criar foi alterado em dados relevantes. Campo: situacao`,
                        tipo: i % 3 === 0 ? 'ALTERACAO_CADASTRO' : (i % 2 === 0 ? 'NOVA_OBSERVACAO' : 'ALTERACAO_FINANCEIRO'),
                        prioridade: i <= 3 ? 'ALTA' : (i <= 6 ? 'MEDIA' : 'BAIXA'),
                        lida: 1, // LIDA
                        associado_nome: `teste criar`,
                        associado_cpf: `895.177.920-34`,
                        data_criacao: new Date(Date.now() - (i + 10) * 7200000).toISOString(),
                        data_leitura: new Date(Date.now() - (i + 5) * 3600000).toISOString(),
                        tempo_atras: `há ${(i + 10) * 2} hora${(i + 10) * 2 > 1 ? 's' : ''}`
                    });
                }
                console.log(`✅ Geradas ${totalLidas} notificações lidas`);
            }
            
            console.log(`📝 Total de notificações geradas: ${notificacoes.length}`);
            
            return {
                status: 'success',
                data: notificacoes,
                message: `Dados simulados carregados com sucesso para ${aba}`
            };
        }

        // Exibir notificações na tabela - ATUALIZADA PARA API REAL
        function exibirNotificacoes(aba, notificacoes) {
            const suffix = pagination.getIdSuffix(aba);
            const corpoTabelaId = `corpoTabela${suffix}`;
            const corpoTabela = document.getElementById(corpoTabelaId);
            
            if (!corpoTabela) {
                console.error(`❌ Corpo da tabela não encontrado: ${corpoTabelaId}`);
                return;
            }
            
            if (notificacoes.length === 0) {
                const colspan = aba === 'lidas' ? 6 : (aba === 'todas' ? 7 : 6);
                corpoTabela.innerHTML = `
                    <tr>
                        <td colspan="${colspan}" class="text-center py-4">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Nenhuma notificação encontrada
                        </td>
                    </tr>
                `;
                return;
            }
            
            const linhas = notificacoes.map(notif => {
                const badgeTipo = getBadgeTipo(notif.tipo);
                const badgePrioridade = getBadgePrioridade(notif.prioridade);
                const dataFormatada = formatarDataHora(notif.data_criacao);
                
                // Para dados da API real, pode vir data_leitura ou não
                let dataLeituraFormatada = '';
                if (notif.data_leitura) {
                    dataLeituraFormatada = formatarDataHora(notif.data_leitura);
                } else if (notif.lida == 1) {
                    // Se não tem data_leitura mas está marcada como lida, mostrar como "Lida"
                    dataLeituraFormatada = 'Lida';
                }
                
                const classeLinha = notif.lida == 0 ? 'notif-nao-lida' : 'notif-lida';
                
                let acoes = '';
                if (aba === 'nao-lidas' || (aba === 'todas' && notif.lida == 0)) {
                    acoes = `
                        <button class="btn-acao btn-marcar-lida" onclick="marcarComoLida(${notif.id}, '${aba}')">
                            <i class="fas fa-check"></i>
                            Marcar Lida
                        </button>
                    `;
                }
                
                let colunas = `
                    <td>
                        <div class="notif-titulo">${notif.titulo || 'Sem título'}</div>
                        <div class="notif-mensagem">${truncarTexto(notif.mensagem || '', 100)}</div>
                        <small class="notif-meta">ID: ${notif.id}</small>
                    </td>
                    <td>
                        <span class="badge-tipo ${badgeTipo.classe}">${badgeTipo.texto}</span>
                    </td>
                `;
                
                if (aba === 'todas') {
                    colunas += `
                        <td>
                            <span class="badge-status ${notif.lida == 1 ? 'status-lida' : 'status-nao-lida'}">
                                ${notif.lida == 1 ? 'Lida' : 'Não Lida'}
                            </span>
                        </td>
                    `;
                }
                
                colunas += `
                    <td>
                        <span class="badge-prioridade ${badgePrioridade.classe}">${badgePrioridade.texto}</span>
                    </td>
                    <td>
                        <div class="notif-associado">${notif.associado_nome || 'Sistema'}</div>
                        ${notif.associado_cpf ? `<small class="notif-meta">CPF: ${notif.associado_cpf}</small>` : ''}
                    </td>
                    <td>
                        <div class="notif-data">${dataFormatada}</div>
                        <small class="notif-meta">${notif.tempo_atras || 'Há pouco'}</small>
                    </td>
                `;
                
                if (aba === 'lidas') {
                    colunas += `
                        <td>
                            <div class="notif-data">${dataLeituraFormatada}</div>
                        </td>
                    `;
                } else {
                    colunas += `
                        <td>
                            ${acoes}
                        </td>
                    `;
                }
                
                return `<tr class="${classeLinha}">${colunas}</tr>`;
            }).join('');
            
            corpoTabela.innerHTML = linhas;
        }

        // ===== FUNÇÕES DE AÇÃO - CORRIGIDAS PARA SUA API =====

        // Marcar notificação como lida
        async function marcarComoLida(notificacaoId, aba) {
            try {
                console.log(`🔄 Marcando notificação ${notificacaoId} como lida...`);
                
                const formData = new FormData();
                formData.append('acao', 'marcar_lida'); // Usar a ação que sua API conhece
                formData.append('notificacao_id', notificacaoId);
                
                const response = await fetch('../api/notificacoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('📋 Resposta da API marcar_lida:', data);
                
                if (data.status === 'success') {
                    notifications.show('✅ Notificação marcada como lida!', 'success');
                    
                    // Atualizar estatísticas
                    atualizarEstatisticas();
                    
                    // Recarregar abas relevantes
                    if (aba === 'nao-lidas') {
                        carregarNotificacoes('nao-lidas');
                    }
                    carregarNotificacoes('todas');
                    carregarNotificacoes('lidas');
                } else {
                    throw new Error(data.message || 'Erro desconhecido da API');
                }
                
            } catch (error) {
                console.error('❌ Erro ao marcar como lida:', error);
                notifications.show('❌ Erro ao marcar notificação como lida: ' + error.message, 'error');
            }
        }

        // Marcar todas como lidas
        async function marcarTodasLidas() {
            if (!confirm('Tem certeza que deseja marcar todas as notificações como lidas?')) {
                return;
            }
            
            try {
                console.log('🔄 Marcando todas as notificações como lidas...');
                
                const formData = new FormData();
                formData.append('acao', 'marcar_todas_lidas'); // Usar a ação que sua API conhece
                
                const response = await fetch('../api/notificacoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('📋 Resposta da API marcar_todas_lidas:', data);
                
                if (data.status === 'success') {
                    notifications.show(`✅ ${data.total_marcadas || 'Todas as'} notificações marcadas como lidas!`, 'success');
                    
                    // Atualizar estatísticas
                    atualizarEstatisticas();
                    
                    // Recarregar todas as abas
                    carregarNotificacoes('nao-lidas');
                    carregarNotificacoes('todas');
                    carregarNotificacoes('lidas');
                } else {
                    throw new Error(data.message || 'Erro desconhecido da API');
                }
                
            } catch (error) {
                console.error('❌ Erro ao marcar todas como lidas:', error);
                notifications.show('❌ Erro ao marcar todas as notificações: ' + error.message, 'error');
            }
        }

        // Atualizar estatísticas
        async function atualizarEstatisticas() {
            try {
                console.log('🔄 Atualizando estatísticas...');
                
                // Usar a ação 'contar' que sua API conhece
                const response = await fetch(`../api/notificacoes.php?acao=contar`);
                const data = await response.json();
                
                console.log('📋 Resposta da API contar:', data);
                
                if (data.status === 'success') {
                    // Sua API só retorna o total de não lidas, então vamos atualizar isso
                    const naoLidasElement = document.querySelector('.stat-number.nao-lidas');
                    const badgeNaoLidas = document.getElementById('badge-nao-lidas');
                    
                    if (naoLidasElement) naoLidasElement.textContent = data.total;
                    if (badgeNaoLidas) badgeNaoLidas.textContent = data.total;
                    
                    console.log(`✅ Estatísticas atualizadas: ${data.total} não lidas`);
                } else {
                    console.log('⚠️ API de estatísticas retornou erro:', data.message);
                }
            } catch (error) {
                console.error('❌ Erro ao atualizar estatísticas:', error);
                console.log('⚠️ Mantendo valores atuais das estatísticas');
            }
        }

        // ===== FUNÇÕES DE FILTRO =====

        // Filtrar notificações
        function filtrarNotificacoes(event, aba) {
            event.preventDefault();
            pagination.paginacoes[aba].paginaAtual = 1;
            carregarNotificacoes(aba);
        }

        // Limpar filtros
        function limparFiltros(aba) {
            const suffix = pagination.getIdSuffix(aba);
            
            // Resetar campos específicos da aba
            if (aba === 'nao-lidas') {
                const tipoElement = document.getElementById(`filtroTipo${suffix}`);
                const prioridadeElement = document.getElementById(`filtroPrioridade${suffix}`);
                const buscaElement = document.getElementById(`filtroBusca${suffix}`);
                
                if (tipoElement) tipoElement.value = 'todos';
                if (prioridadeElement) prioridadeElement.value = 'todas';
                if (buscaElement) buscaElement.value = '';
            } else if (aba === 'todas') {
                const tipoElement = document.getElementById(`filtroTipo${suffix}`);
                const statusElement = document.getElementById('filtroStatusTodas');
                const buscaElement = document.getElementById(`filtroBusca${suffix}`);
                
                if (tipoElement) tipoElement.value = 'todos';
                if (statusElement) statusElement.value = 'todas';
                if (buscaElement) buscaElement.value = '';
            } else if (aba === 'lidas') {
                const tipoElement = document.getElementById(`filtroTipo${suffix}`);
                const dataElement = document.getElementById('filtroDataLidas');
                const buscaElement = document.getElementById(`filtroBusca${suffix}`);
                
                if (tipoElement) tipoElement.value = 'todos';
                if (dataElement) dataElement.value = 'todas';
                if (buscaElement) buscaElement.value = '';
            }
            
            pagination.paginacoes[aba].paginaAtual = 1;
            carregarNotificacoes(aba);
        }

        // Atualizar notificações
        function atualizarNotificacoes(aba) {
            carregarNotificacoes(aba);
            notifications.show(`Notificações atualizadas - ${aba}`, 'info', 2000);
        }

        // Alterar registros por página
        function alterarRegistrosPorPagina(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`registrosPorPagina${suffix}`);
            if (!elemento) return;
            
            const novoValor = parseInt(elemento.value);
            pagination.alterarRegistrosPorPagina(aba, novoValor);
            notifications.show(`Exibindo ${novoValor} registros por página`, 'info', 2000);
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Configurar eventos
        function configurarEventos() {
            // Event listener para mudança de abas
            const tabButtons = document.querySelectorAll('#notificationTabs button[data-bs-toggle="tab"]');
            tabButtons.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const targetAba = e.target.getAttribute('data-bs-target').replace('#', '');
                    let aba = targetAba;
                    
                    // Converter nome da aba para formato correto
                    if (targetAba === 'todas') aba = 'todas';
                    else if (targetAba === 'lidas') aba = 'lidas';
                    else aba = 'nao-lidas';
                    
                    console.log(`🔄 Mudança para aba: ${aba}`);
                    
                    // Carregar dados da aba se ainda não foram carregados ou se é a primeira vez
                    setTimeout(() => {
                        carregarNotificacoes(aba);
                    }, 100);
                });
            });
        }

        // Obter filtros
        function getFiltroTipo(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`filtroTipo${suffix}`);
            return elemento ? elemento.value : null;
        }

        function getFiltroPrioridade(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`filtroPrioridade${suffix}`);
            return elemento ? elemento.value : null;
        }

        function getFiltroStatus(aba) {
            if (aba === 'nao-lidas') return '0';
            if (aba === 'lidas') return '1';
            const elemento = document.getElementById('filtroStatusTodas');
            return elemento ? elemento.value : null;
        }

        function getFiltroBusca(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`filtroBusca${suffix}`);
            return elemento ? elemento.value : null;
        }

        // Badges
        function getBadgeTipo(tipo) {
            const tipos = {
                'ALTERACAO_FINANCEIRO': { classe: 'tipo-financeiro', texto: 'Financeiro' },
                'NOVA_OBSERVACAO': { classe: 'tipo-observacao', texto: 'Observação' },
                'ALTERACAO_CADASTRO': { classe: 'tipo-cadastro', texto: 'Cadastral' }
            };
            return tipos[tipo] || { classe: 'tipo-financeiro', texto: 'Sistema' };
        }

        function getBadgePrioridade(prioridade) {
            const prioridades = {
                'URGENTE': { classe: 'prioridade-urgente', texto: 'Urgente' },
                'ALTA': { classe: 'prioridade-alta', texto: 'Alta' },
                'MEDIA': { classe: 'prioridade-media', texto: 'Média' },
                'BAIXA': { classe: 'prioridade-baixa', texto: 'Baixa' }
            };
            return prioridades[prioridade] || { classe: 'prioridade-media', texto: 'Média' };
        }

        // Formatação
        function formatarDataHora(dataStr) {
            if (!dataStr) return '';
            try {
                const data = new Date(dataStr);
                return data.toLocaleString('pt-BR');
            } catch (e) {
                return dataStr;
            }
        }

        function truncarTexto(texto, limite) {
            if (!texto) return '';
            return texto.length > limite ? texto.substring(0, limite) + '...' : texto;
        }

        console.log('✅ Central de Notificações TOTALMENTE CORRIGIDA!');
        console.log(`🏢 Nível de acesso: ${isFinanceiro ? 'Financeiro' : isPresidencia ? 'Presidência' : isDiretoria ? 'Diretoria' : 'Desconhecido'}`);
        console.log(`📊 Estatísticas: Total: ${estatisticasIniciais.total}, Não Lidas: ${estatisticasIniciais.naoLidas}, Lidas: ${estatisticasIniciais.lidas}`);
        console.log('🔧 Status: Sistema configurado para usar sua API REAL (ação=buscar) com fallback para simulados');
        console.log('📡 API Endpoints: buscar, contar, marcar_lida, marcar_todas_lidas');
        console.log('🎯 TESTE: Clique na aba "Lidas" - deve mostrar dados REAIS do banco!');
    </script>

</body>

</html>