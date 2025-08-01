<?php
/**
 * Página de Edições - Sistema ASSEGO
 * pages/edicoes.php
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Auditoria.php';
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
$page_title = 'Histórico de Edições - ASSEGO';

// Verificar permissões (mesma lógica da auditoria)
$temPermissaoEdicoes = false;
$motivoNegacao = '';
$isPresidencia = false;
$isDiretor = false;
$departamentoUsuario = null;

// Debug completo ANTES das verificações
error_log("=== DEBUG DETALHADO PERMISSÕES EDIÇÕES ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Verificação de permissões: usuários do departamento da presidência (ID: 1) OU diretores
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $isDiretor = $auth->isDiretor();
    $departamentoUsuario = $deptId;
    
    if ($deptId == 1) { // Presidência - vê tudo
        $temPermissaoEdicoes = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Departamento da Presidência");
    } elseif ($isDiretor) { // Diretor - vê apenas seu departamento
        $temPermissaoEdicoes = true;
        $isDiretor = true;
        error_log("✅ Permissão concedida: Usuário é Diretor - Departamento " . $deptId);
    } else {
        $motivoNegacao = 'Acesso restrito ao departamento da Presidência ou diretores.';
        error_log("❌ Acesso negado. Necessário: Presidência (ID = 1) OU ser diretor");
    }
} else {
    $motivoNegacao = 'Departamento não identificado.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Busca estatísticas específicas de edições (apenas se tem permissão)
if ($temPermissaoEdicoes) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Filtro de departamento se não for presidência
        $whereDepartamento = '';
        $paramsDepartamento = [];
        
        if (!$isPresidencia && $isDiretor && $departamentoUsuario) {
            $whereDepartamento = " AND (
                f.departamento_id = :departamento_usuario 
                OR a.funcionario_id IN (
                    SELECT id FROM Funcionarios WHERE departamento_id = :departamento_usuario2
                )
            )";
            $paramsDepartamento = [
                ':departamento_usuario' => $departamentoUsuario,
                ':departamento_usuario2' => $departamentoUsuario
            ];
        }
        
        // Total de edições
        $sql = "SELECT COUNT(*) as total FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id 
                WHERE a.acao = 'UPDATE' 
                AND a.tabela IN ('Associados', 'Funcionarios')" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $totalEdicoes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Edições hoje
        $sql = "SELECT COUNT(*) as hoje FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id 
                WHERE a.acao = 'UPDATE' 
                AND a.tabela IN ('Associados', 'Funcionarios')
                AND DATE(a.data_hora) = CURDATE()" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $edicoesHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];
        
        // Editores ativos (últimos 7 dias)
        $sql = "SELECT COUNT(DISTINCT a.funcionario_id) as editores_ativos
                FROM Auditoria a
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                WHERE a.acao = 'UPDATE' 
                AND a.tabela IN ('Associados', 'Funcionarios')
                AND a.data_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND a.funcionario_id IS NOT NULL" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $editoresAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['editores_ativos'];
        
        // Tabela mais editada
        $sql = "SELECT a.tabela, COUNT(*) as total
                FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                WHERE a.acao = 'UPDATE' 
                AND a.tabela IN ('Associados', 'Funcionarios')
                AND DATE(a.data_hora) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" . $whereDepartamento . "
                GROUP BY a.tabela
                ORDER BY total DESC
                LIMIT 1";
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $tabelaMaisEditada = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas das edições: " . $e->getMessage());
        $totalEdicoes = $edicoesHoje = $editoresAtivos = 0;
        $tabelaMaisEditada = ['tabela' => 'N/A', 'total' => 0];
    }
} else {
    $totalEdicoes = $edicoesHoje = $editoresAtivos = 0;
    $tabelaMaisEditada = ['tabela' => 'N/A', 'total' => 0];
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'avatar' => $usuarioLogado['avatar'] ?? null,
        'departamento_id' => $usuarioLogado['departamento_id'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'edicoes',
    'notificationCount' => 0,
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

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --light: #f8fafc;
            --dark: #1e293b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .main-wrapper {
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        .content-area {
            padding: 2rem;
            margin-top: 80px;
        }

        /* Page Header */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            display: flex;
            align-items: center;
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .page-title-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1.1rem;
            margin-top: 0.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--danger); }
        .stat-change.neutral { color: var(--info); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .stat-icon.success { background: linear-gradient(135deg, var(--success), #059669); }
        .stat-icon.warning { background: linear-gradient(135deg, var(--warning), #d97706); }
        .stat-icon.info { background: linear-gradient(135deg, var(--info), #0891b2); }

        /* Filters */
        .filters-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .filter-input, .filter-select {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-filter {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s ease;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            color: white;
        }

        /* Edições Table */
        .edicoes-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .section-title-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--warning), #d97706);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
        }

        .edicoes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .edicoes-table thead th {
            background: var(--light);
            color: var(--dark);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
        }

        .edicoes-table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .edicoes-table tbody tr:hover {
            background: rgba(37, 99, 235, 0.05);
        }

        .edit-badge {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .table-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .table-badge.associados {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .table-badge.funcionarios {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        .btn-details {
            background: var(--info);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            transition: background 0.3s ease;
        }

        .btn-details:hover {
            background: #0891b2;
            color: white;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .pagination-info {
            color: #64748b;
            font-size: 0.9rem;
        }

        .pagination {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .pagination .page-item {
            margin: 0 0.25rem;
        }

        .pagination .page-link {
            display: block;
            padding: 0.5rem 0.75rem;
            background: white;
            border: 1px solid var(--border-color);
            color: var(--dark);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .pagination .page-link:hover {
            background: var(--primary);
            color: white;
        }

        .pagination .page-item.active .page-link {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Loading */
        .loading-row {
            text-align: center;
            padding: 3rem;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-area { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-row { grid-template-columns: 1fr; }
            .pagination-wrapper { flex-direction: column; gap: 1rem; }
        }

        /* Toast Container */
        .toast-container {
            z-index: 1050;
        }

        .toast {
            border-radius: 8px;
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
            <?php if (!$temPermissaoEdicoes): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado ao Histórico de Edições</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Requisitos para acesso:</h6>
                    <ul class="mb-0">
                        <li>Ser diretor <strong>OU</strong></li>
                        <li>Estar no departamento da Presidência</li>
                    </ul>
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-primary btn-sm" onclick="window.location.reload()">
                        <i class="fas fa-sync me-1"></i>
                        Recarregar Página
                    </button>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    Histórico de Edições
                    <?php if (!$isPresidencia): ?>
                        <small class="text-muted">- Departamento <?php echo htmlspecialchars($departamentoUsuario); ?></small>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <?php if ($isPresidencia): ?>
                        Monitoramento completo de todas as edições de associados e funcionários
                    <?php else: ?>
                        Monitoramento de edições relacionadas ao seu departamento
                    <?php endif; ?>
                </p>
            </div>

            <!-- Alert informativo sobre o nível de acesso -->
            <div class="alert alert-<?php echo $isPresidencia ? 'info' : 'warning'; ?>" data-aos="fade-up">
                <div class="d-flex align-items-center">
                    <i class="fas fa-<?php echo $isPresidencia ? 'globe' : 'building'; ?> fa-2x me-3"></i>
                    <div>
                        <h6 class="mb-1">
                            <?php if ($isPresidencia): ?>
                                <i class="fas fa-crown text-warning"></i> Acesso Total - Presidência
                            <?php else: ?>
                                <i class="fas fa-user-tie text-info"></i> Acesso Departamental - Diretor
                            <?php endif; ?>
                        </h6>
                        <small>
                            <?php if ($isPresidencia): ?>
                                Você pode visualizar todas as edições realizadas no sistema.
                            <?php else: ?>
                                Você pode visualizar edições relacionadas ao departamento <strong><?php echo htmlspecialchars($departamentoUsuario); ?></strong>.
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalEdicoes); ?></div>
                            <div class="stat-label">Total de Edições<?php echo !$isPresidencia ? ' (Dept.)' : ''; ?></div>
                            <div class="stat-change neutral">
                                <i class="fas fa-edit"></i>
                                <?php echo $isPresidencia ? 'Todo o sistema' : 'Seu departamento'; ?>
                            </div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-edit"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $edicoesHoje; ?></div>
                            <div class="stat-label">Edições Hoje<?php echo !$isPresidencia ? ' (Dept.)' : ''; ?></div>
                            <div class="stat-change positive">
                                <i class="fas fa-calendar-day"></i>
                                Atividade atual
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $editoresAtivos; ?></div>
                            <div class="stat-label">Editores Ativos<?php echo !$isPresidencia ? ' (Dept.)' : ''; ?></div>
                            <div class="stat-change neutral">
                                <i class="fas fa-users"></i>
                                Últimos 7 dias
                            </div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-user-edit"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $tabelaMaisEditada['tabela']; ?></div>
                            <div class="stat-label">Mais Editada<?php echo !$isPresidencia ? ' (Dept.)' : ''; ?></div>
                            <div class="stat-change neutral">
                                <i class="fas fa-chart-bar"></i>
                                <?php echo $tabelaMaisEditada['total']; ?> edições (30 dias)
                            </div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-table"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section" data-aos="fade-up" data-aos-delay="100">
                <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filtros de Busca</h6>
                <div class="filter-row">
                    <div>
                        <label class="form-label">Buscar por funcionário ou registro</label>
                        <input type="text" class="filter-input" id="searchInput" placeholder="Digite para buscar...">
                    </div>
                    <div>
                        <label class="form-label">Tabela</label>
                        <select class="filter-select" id="filterTabela">
                            <option value="">Todas</option>
                            <option value="Associados">Associados</option>
                            <option value="Funcionarios">Funcionários</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Data</label>
                        <input type="date" class="filter-input" id="filterData">
                    </div>
                    <div>
                        <label class="form-label">Período</label>
                        <select class="filter-select" id="filterPeriodo">
                            <option value="">Todos</option>
                            <option value="hoje">Hoje</option>
                            <option value="semana">Esta Semana</option>
                            <option value="mes">Este Mês</option>
                        </select>
                    </div>
                    <div>
                        <button class="btn-filter" onclick="aplicarFiltros()">
                            <i class="fas fa-search me-1"></i>
                            Filtrar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Edições Section -->
            <div class="edicoes-section" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h2 class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        Registro de Edições
                        <?php if (!$isPresidencia): ?>
                            <small class="text-muted">- Departamento <?php echo htmlspecialchars($departamentoUsuario); ?></small>
                        <?php endif; ?>
                    </h2>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" onclick="atualizarEdicoes()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                        <button class="btn btn-sm btn-outline-success ms-2" onclick="exportarEdicoes()">
                            <i class="fas fa-download"></i>
                            Exportar
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="edicoes-table table">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Funcionário</th>
                                <th>Tabela</th>
                                <th>Registro</th>
                                <th>Resumo da Edição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="edicoesTableBody">
                            <tr class="loading-row">
                                <td colspan="6">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                    <p class="text-muted mt-2">Carregando histórico de edições...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Mostrando <span id="paginaAtual">1</span> - <span id="totalPaginas">1</span> de <span id="totalRegistrosPagina">0</span> edições
                    </div>
                    <nav>
                        <ul class="pagination" id="paginationNav">
                            <!-- Paginação será gerada dinamicamente -->
                        </ul>
                    </nav>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Detalhes da Edição -->
    <div class="modal fade" id="detalhesEdicaoModal" tabindex="-1" aria-labelledby="detalhesEdicaoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalhesEdicaoModalLabel">
                        <i class="fas fa-edit text-warning"></i>
                        Detalhes da Edição
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detalhesEdicaoModalBody">
                    <!-- Conteúdo será carregado dinamicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
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

        // ===== VARIÁVEIS GLOBAIS =====
        const notifications = new NotificationSystem();
        let edicoesData = [];
        let currentPage = 1;
        let totalPages = 1;
        const temPermissao = <?php echo json_encode($temPermissaoEdicoes); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const isDiretor = <?php echo json_encode($isDiretor); ?>;
        const departamentoUsuario = <?php echo json_encode($departamentoUsuario); ?>;

        // Debounce para filtros
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

        const debouncedFilter = debounce(aplicarFiltros, 300);

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({ duration: 800, once: true });

            console.log('=== DEBUG HISTÓRICO DE EDIÇÕES ===');
            console.log('Tem permissão:', temPermissao);
            console.log('É presidência:', isPresidencia);
            console.log('É diretor:', isDiretor);
            console.log('Departamento:', departamentoUsuario);

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

            // Carregar edições automaticamente
            carregarEdicoes();
            configurarEventos();
        });

        // ===== FUNÇÕES PRINCIPAIS =====

        // Função para obter parâmetros departamentais
        function obterParametrosDepartamentais() {
            const params = {};
            if (!isPresidencia && isDiretor && departamentoUsuario) {
                params.departamento_usuario = departamentoUsuario;
            }
            return params;
        }

        // Carregar edições
        async function carregarEdicoes(page = 1, filters = {}) {
            if (!temPermissao) {
                console.log('❌ Sem permissão para carregar edições');
                return;
            }
            
            const tbody = document.getElementById('edicoesTableBody');
            
            // Mostrar loading
            tbody.innerHTML = `
                <tr class="loading-row">
                    <td colspan="6">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="text-muted mt-2">Carregando histórico de edições...</p>
                    </td>
                </tr>
            `;

            try {
                // Construir filtros específicos para edições
                const allFilters = {
                    acao: 'UPDATE', // Apenas edições
                    tabela: filters.tabela || '',
                    search: filters.search || '',
                    data_inicio: filters.data_inicio || '',
                    data_fim: filters.data_fim || '',
                    ...obterParametrosDepartamentais()
                };

                // Filtros especiais para período
                if (filters.periodo) {
                    const hoje = new Date();
                    let dataInicio;
                    
                    switch (filters.periodo) {
                        case 'hoje':
                            dataInicio = hoje.toISOString().split('T')[0];
                            allFilters.data_inicio = dataInicio;
                            allFilters.data_fim = dataInicio;
                            break;
                        case 'semana':
                            dataInicio = new Date(hoje.getTime() - (7 * 24 * 60 * 60 * 1000));
                            allFilters.data_inicio = dataInicio.toISOString().split('T')[0];
                            break;
                        case 'mes':
                            dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                            allFilters.data_inicio = dataInicio.toISOString().split('T')[0];
                            break;
                    }
                }

                // Se há filtro de tabela específica, limitar às tabelas relevantes
                if (!allFilters.tabela) {
                    // Se não especificou tabela, garantir que só pegue Associados e Funcionarios
                    allFilters.tabela_in = 'Associados,Funcionarios';
                }

                const params = new URLSearchParams({
                    page: page,
                    limit: 20,
                    ...allFilters
                });
                
                console.log('📡 Parâmetros da requisição:', allFilters);
                
                const response = await fetch(`../api/auditoria/registros.php?${params}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    edicoesData = data.data.registros || [];
                    
                    renderizarTabelaEdicoes(edicoesData);
                    atualizarPaginacao(data.data.paginacao);
                    
                    const mensagem = isPresidencia 
                        ? `${edicoesData.length} edição(ões) carregada(s) (Todo o sistema)`
                        : `${edicoesData.length} edição(ões) carregada(s) (Departamento ${departamentoUsuario})`;
                    
                    notifications.show(mensagem, 'success', 2000);
                } else {
                    throw new Error(data.message || 'Erro ao carregar edições');
                }
            } catch (error) {
                console.error('❌ Erro ao carregar edições:', error);
                mostrarErroTabela('Erro ao carregar edições: ' + error.message);
            }
        }

        // Renderizar tabela de edições
        function renderizarTabelaEdicoes(edicoes) {
            const tbody = document.getElementById('edicoesTableBody');
            
            if (!tbody) {
                console.error('Tbody não encontrado');
                return;
            }
            
            tbody.innerHTML = '';

            if (edicoes.length === 0) {
                const mensagem = isPresidencia 
                    ? 'Nenhuma edição encontrada no sistema'
                    : `Nenhuma edição encontrada para o departamento ${departamentoUsuario}`;
                
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem;">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Nenhuma edição encontrada</h5>
                            <p class="text-muted">${mensagem}</p>
                            <p class="text-muted">Tente ajustar os filtros ou o período de busca.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            edicoes.forEach(edicao => {
                const resumo = gerarResumoEdicao(edicao);
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${formatarData(edicao.data_hora)}</td>
                    <td>
                        ${edicao.funcionario_nome || 'Sistema'}
                        ${!isPresidencia && edicao.funcionario_departamento ? 
                            `<br><small class="text-muted">Dept: ${edicao.funcionario_departamento}</small>` : ''}
                    </td>
                    <td><span class="table-badge ${edicao.tabela.toLowerCase()}">${edicao.tabela}</span></td>
                    <td>
                        ${edicao.registro_id ? `ID: ${edicao.registro_id}` : 'N/A'}
                        ${edicao.associado_nome ? `<br><small class="text-muted">${edicao.associado_nome}</small>` : ''}
                    </td>
                    <td>
                        <span class="edit-badge">EDIÇÃO</span>
                        <br><small class="text-muted">${resumo}</small>
                    </td>
                    <td>
                        <button class="btn-details" onclick="mostrarDetalhesEdicao(${edicao.id})" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                            Detalhes
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Gerar resumo da edição
        function gerarResumoEdicao(edicao) {
            if (!edicao.alteracoes_decoded || !Array.isArray(edicao.alteracoes_decoded)) {
                return 'Dados alterados';
            }
            
            const campos = edicao.alteracoes_decoded.length;
            if (campos === 1) {
                return `1 campo alterado`;
            } else if (campos <= 3) {
                return `${campos} campos alterados`;
            } else {
                return `${campos} campos alterados (edição extensa)`;
            }
        }

        // Mostrar detalhes da edição
        async function mostrarDetalhesEdicao(edicaoId) {
            console.log('🔍 Mostrando detalhes da edição ID:', edicaoId);
            
            try {
                const params = new URLSearchParams({
                    id: edicaoId,
                    ...obterParametrosDepartamentais()
                });
                
                const response = await fetch(`../api/auditoria/detalhes.php?${params}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    const modalBody = document.getElementById('detalhesEdicaoModalBody');
                    const edicao = data.data;
                    
                    modalBody.innerHTML = gerarHtmlDetalhesEdicao(edicao);
                    
                    new bootstrap.Modal(document.getElementById('detalhesEdicaoModal')).show();
                } else {
                    throw new Error(data.message || 'Erro ao carregar detalhes');
                }
                
            } catch (error) {
                console.error('❌ Erro ao carregar detalhes da edição:', error);
                notifications.show('Erro ao carregar detalhes: ' + error.message, 'error');
            }
        }

        // Gerar HTML dos detalhes da edição
        function gerarHtmlDetalhesEdicao(edicao) {
            let alteracoesHtml = '';
            
            if (edicao.alteracoes_decoded && Array.isArray(edicao.alteracoes_decoded)) {
                alteracoesHtml = `
                    <div class="mt-4">
                        <h6><i class="fas fa-list-alt text-info"></i> Campos Alterados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Campo</th>
                                        <th>Valor Anterior</th>
                                        <th>Valor Novo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${edicao.alteracoes_decoded.map(alt => `
                                        <tr>
                                            <td><strong>${alt.campo || 'N/A'}</strong></td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    ${alt.valor_anterior || 'N/A'}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    ${alt.valor_novo || 'N/A'}
                                                </span>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            return `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle text-primary"></i> Informações da Edição</h6>
                        <table class="table table-sm">
                            <tr><td><strong>ID da Edição:</strong></td><td>${edicao.id}</td></tr>
                            <tr><td><strong>Data/Hora:</strong></td><td>${formatarData(edicao.data_hora)}</td></tr>
                            <tr><td><strong>Funcionário:</strong></td><td>${edicao.funcionario_nome || 'Sistema'}</td></tr>
                            <tr><td><strong>Tabela:</strong></td><td><span class="table-badge ${edicao.tabela.toLowerCase()}">${edicao.tabela}</span></td></tr>
                            <tr><td><strong>Registro ID:</strong></td><td>${edicao.registro_id || 'N/A'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-globe text-success"></i> Informações Técnicas</h6>
                        <table class="table table-sm">
                            <tr><td><strong>IP de Origem:</strong></td><td>${edicao.ip_origem || 'N/A'}</td></tr>
                            <tr><td><strong>Navegador:</strong></td><td>${edicao.browser_info || 'N/A'}</td></tr>
                            <tr><td><strong>Sessão ID:</strong></td><td>${edicao.sessao_id || 'N/A'}</td></tr>
                            ${!isPresidencia && edicao.departamento_nome ? 
                                `<tr><td><strong>Departamento:</strong></td><td>${edicao.departamento_nome}</td></tr>` : ''}
                        </table>
                    </div>
                </div>
                
                ${alteracoesHtml}
                
                ${edicao.associado_nome ? `
                    <div class="mt-4">
                        <h6><i class="fas fa-user text-warning"></i> Informações do Associado</h6>
                        <div class="alert alert-info">
                            <strong>Nome:</strong> ${edicao.associado_nome}<br>
                            <strong>CPF:</strong> ${edicao.associado_cpf || 'N/A'}
                        </div>
                    </div>
                ` : ''}
                
                ${!isPresidencia ? `
                    <div class="mt-4">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Filtro Departamental Ativo:</strong> Visualizando apenas edições relacionadas ao departamento ${departamentoUsuario}.
                        </div>
                    </div>
                ` : ''}
            `;
        }

        // Configurar eventos
        function configurarEventos() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', debouncedFilter);
            }
            
            const filterTabela = document.getElementById('filterTabela');
            if (filterTabela) {
                filterTabela.addEventListener('change', aplicarFiltros);
            }
            
            const filterData = document.getElementById('filterData');
            if (filterData) {
                filterData.addEventListener('change', aplicarFiltros);
            }
            
            const filterPeriodo = document.getElementById('filterPeriodo');
            if (filterPeriodo) {
                filterPeriodo.addEventListener('change', aplicarFiltros);
            }
        }

        // Aplicar filtros
        function aplicarFiltros() {
            const filters = {};
            
            const searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value) {
                filters.search = searchInput.value;
            }
            
            const filterTabela = document.getElementById('filterTabela');
            if (filterTabela && filterTabela.value) {
                filters.tabela = filterTabela.value;
            }
            
            const filterData = document.getElementById('filterData');
            if (filterData && filterData.value) {
                filters.data_inicio = filterData.value;
                filters.data_fim = filterData.value;
            }
            
            const filterPeriodo = document.getElementById('filterPeriodo');
            if (filterPeriodo && filterPeriodo.value) {
                filters.periodo = filterPeriodo.value;
            }

            currentPage = 1;
            carregarEdicoes(1, filters);
        }

        // Atualizar paginação
        function atualizarPaginacao(paginacao) {
            currentPage = paginacao.pagina_atual;
            totalPages = paginacao.total_paginas;
            
            document.getElementById('paginaAtual').textContent = paginacao.pagina_atual;
            document.getElementById('totalPaginas').textContent = paginacao.total_paginas;
            document.getElementById('totalRegistrosPagina').textContent = paginacao.total_registros;

            const nav = document.getElementById('paginationNav');
            nav.innerHTML = '';

            if (currentPage > 1) {
                nav.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="irParaPagina(${currentPage - 1})">Anterior</a>
                    </li>
                `;
            }

            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                nav.innerHTML += `
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="irParaPagina(${i})">${i}</a>
                    </li>
                `;
            }

            if (currentPage < totalPages) {
                nav.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="irParaPagina(${currentPage + 1})">Próximo</a>
                    </li>
                `;
            }
        }

        function irParaPagina(page) {
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                
                const filters = {};
                const searchInput = document.getElementById('searchInput');
                const filterTabela = document.getElementById('filterTabela');
                const filterData = document.getElementById('filterData');
                const filterPeriodo = document.getElementById('filterPeriodo');
                
                if (searchInput && searchInput.value) filters.search = searchInput.value;
                if (filterTabela && filterTabela.value) filters.tabela = filterTabela.value;
                if (filterData && filterData.value) {
                    filters.data_inicio = filterData.value;
                    filters.data_fim = filterData.value;
                }
                if (filterPeriodo && filterPeriodo.value) filters.periodo = filterPeriodo.value;
                
                carregarEdicoes(page, filters);
            }
        }

        // Atualizar edições
        function atualizarEdicoes() {
            carregarEdicoes();
            
            const mensagem = isPresidencia 
                ? 'Edições atualizadas!'
                : `Edições do departamento ${departamentoUsuario} atualizadas!`;
            
            notifications.show(mensagem, 'success');
        }

        // Exportar edições
        async function exportarEdicoes() {
            try {
                const mensagem = isPresidencia 
                    ? 'Iniciando exportação de todas as edições...'
                    : `Iniciando exportação das edições do departamento ${departamentoUsuario}...`;
                
                notifications.show(mensagem, 'info');
                
                const filters = {
                    acao: 'UPDATE',
                    tabela_in: 'Associados,Funcionarios',
                    ...obterParametrosDepartamentais()
                };
                
                const params = new URLSearchParams(filters);
                const response = await fetch(`../api/auditoria/exportar.php?${params}`);
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    
                    const sufixo = isPresidencia ? 'completo' : `dept_${departamentoUsuario}`;
                    a.download = `edicoes_${sufixo}_${new Date().toISOString().split('T')[0]}.csv`;
                    
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    const sucessoMsg = isPresidencia 
                        ? 'Edições exportadas com sucesso!'
                        : `Edições do departamento ${departamentoUsuario} exportadas com sucesso!`;
                    
                    notifications.show(sucessoMsg, 'success');
                } else {
                    throw new Error('Erro ao exportar edições');
                }
            } catch (error) {
                console.error('Erro ao exportar:', error);
                notifications.show('Erro ao exportar edições: ' + error.message, 'error');
            }
        }

        // Função auxiliar para formatar data
        function formatarData(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr);
            return data.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Mostrar erro na tabela
        function mostrarErroTabela(mensagem) {
            const tbody = document.getElementById('edicoesTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h5 class="text-danger">Erro</h5>
                        <p class="text-muted">${mensagem}</p>
                        ${!isPresidencia ? `<p class="text-muted"><small>Filtro departamental: Departamento ${departamentoUsuario}</small></p>` : ''}
                        <button class="btn btn-primary btn-sm mt-2" onclick="carregarEdicoes()">
                            <i class="fas fa-redo"></i>
                            Tentar Novamente
                        </button>
                    </td>
                </tr>
            `;
        }

        // Log de inicialização
        console.log('✓ Sistema de Histórico de Edições carregado com sucesso!');
        console.log(`📊 Escopo: ${isPresidencia ? 'Sistema Completo (Presidência)' : 'Departamento ' + departamentoUsuario}`);
        console.log(`🔐 Permissões: ${temPermissao ? 'Concedidas' : 'Negadas'}`);
    </script>
</body>

</html>