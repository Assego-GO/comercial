<?php
/**
 * Sistema de Gerenciamento de Permissões RBAC + ACL
 * pages/permissoes.php
 * 
 * Gerenciamento completo de roles, permissões, delegações e políticas de acesso
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
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
$permissoes = Permissoes::getInstance();

// Define o título da página
$page_title = 'Gerenciamento de Permissões - Sistema RBAC/ACL';

// Verifica permissões - APENAS SUPER ADMIN ou quem tem permissão específica

$temPermissao = $permissoes->isSuperAdmin() || 
                $permissoes->hasPermission('SISTEMA_PERMISSOES', 'FULL') ||
                $permissoes->hasPermission('SISTEMA_PERMISSOES', 'VIEW');

$podeEditar = $permissoes->isSuperAdmin() || 
              $permissoes->hasPermission('SISTEMA_PERMISSOES', 'EDIT');

$podeCriar = $permissoes->isSuperAdmin() || 
             $permissoes->hasPermission('SISTEMA_PERMISSOES', 'CREATE');

$podeDeletar = $permissoes->isSuperAdmin() || 
               $permissoes->hasPermission('SISTEMA_PERMISSOES', 'DELETE');


// Buscar estatísticas se tem permissão
$stats = ['total_roles' => 0, 'total_recursos' => 0, 'total_delegacoes' => 0, 'logs_hoje' => 0];

if ($temPermissao) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Total de roles
        $stmt = $db->query("SELECT COUNT(*) FROM roles WHERE ativo = 1");
        $stats['total_roles'] = $stmt->fetchColumn();
        
        // Total de recursos
        $stmt = $db->query("SELECT COUNT(*) FROM recursos WHERE ativo = 1");
        $stats['total_recursos'] = $stmt->fetchColumn();
        
        // Delegações ativas
        $stmt = $db->query("SELECT COUNT(*) FROM delegacoes WHERE ativo = 1 AND NOW() BETWEEN data_inicio AND data_fim");
        $stats['total_delegacoes'] = $stmt->fetchColumn();
        
        // Logs de hoje
        $stmt = $db->query("SELECT COUNT(*) FROM log_acessos WHERE DATE(criado_em) = CURDATE()");
        $stats['logs_hoje'] = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas de permissões: " . $e->getMessage());
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'admin',
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
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <style>
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
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
            color: var(--dark);
        }
        
        .content-area {
            padding: 1.5rem;
            margin-left: 0;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 86, 210, 0.08);
            border-left: 4px solid var(--primary);
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .page-title-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .page-title-icon i {
            color: white;
            font-size: 1.5rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(0, 86, 210, 0.08);
            transition: transform 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 86, 210, 0.12);
        }
        
        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.info { border-left-color: var(--info); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Tabs de navegação */
        .nav-tabs-custom {
            background: white;
            border-radius: 12px;
            padding: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 86, 210, 0.08);
        }
        
        .nav-tabs-custom .nav-link {
            color: var(--secondary);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .nav-tabs-custom .nav-link:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        .nav-tabs-custom .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 86, 210, 0.08);
            margin-bottom: 1.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        
        /* DataTables customization */
        .dataTables_wrapper {
            font-size: 0.9rem;
        }
        
        table.dataTable thead th {
            background: var(--light);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            padding: 0.75rem;
        }
        
        /* Badges */
        .badge-role {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge-super-admin { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .badge-presidente { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .badge-diretor { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .badge-funcionario { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        
        /* Action buttons */
        .btn-action {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            margin: 0 0.125rem;
        }
        
        /* Role hierarchy visualization */
        .hierarchy-tree {
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
        }
        
        .hierarchy-item {
            padding: 0.75rem;
            margin: 0.5rem 0;
            background: white;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            transition: all 0.3s ease;
        }
        
        .hierarchy-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0, 86, 210, 0.1);
        }
        
        .hierarchy-level {
            font-size: 0.75rem;
            color: var(--secondary);
            font-weight: 600;
        }
        
        /* Permission matrix */
        .permission-matrix {
            overflow-x: auto;
        }
        
        .permission-matrix table {
            font-size: 0.85rem;
        }
        
        .permission-cell {
            text-align: center;
            vertical-align: middle;
        }
        
        .permission-check {
            color: var(--success);
            font-size: 1.2rem;
        }
        
        .permission-deny {
            color: var(--danger);
            font-size: 1.2rem;
        }
        
        /* Delegation cards */
        .delegation-card {
            border: 2px solid var(--light);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .delegation-card:hover {
            border-color: var(--primary);
            background: var(--light);
        }
        
        .delegation-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .delegation-active { background: var(--success); color: white; }
        .delegation-expired { background: var(--secondary); color: white; }
        .delegation-future { background: var(--info); color: white; }
        
        /* Modal customizations */
        .modal-header-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        /* Search and filters */
        .search-filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 250px;
        }
        
        /* Permission level indicators */
        .level-indicator {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            color: white;
            font-size: 0.8rem;
        }
        
        .level-1000 { background: #8b5cf6; }
        .level-900 { background: #ec4899; }
        .level-800 { background: #ef4444; }
        .level-700 { background: #f97316; }
        .level-600 { background: #f59e0b; }
        .level-500 { background: #eab308; }
        .level-400 { background: #84cc16; }
        .level-300 { background: #22c55e; }
        .level-200 { background: #10b981; }
        .level-100 { background: #6b7280; }
        
        /* Timeline for logs */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--light);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1rem;
        }
        
        .timeline-marker {
            position: absolute;
            left: -1.75rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--primary);
        }
        
        .timeline-content {
            background: var(--light);
            padding: 0.75rem;
            border-radius: 6px;
        }
        
        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .quick-action-btn {
            padding: 1rem;
            border-radius: 10px;
            border: 2px solid var(--light);
            background: white;
            transition: all 0.3s ease;
            text-align: center;
            cursor: pointer;
        }
        
        .quick-action-btn:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 86, 210, 0.15);
        }
        
        .quick-action-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .search-filter-bar {
                flex-direction: column;
            }
            
            .search-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>
        
        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissao): ?>
                <!-- Sem Permissão -->
                <div class="alert alert-danger">
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado</h4>
                    <p>Você não tem permissão para acessar o gerenciamento de permissões.</p>
                    <a href="../pages/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">
                        <div class="page-title-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        Gerenciamento de Permissões
                    </h1>
                    <p class="text-muted mb-0">
                        Sistema RBAC (Role-Based Access Control) + ACL (Access Control List)
                    </p>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-value"><?php echo $stats['total_roles']; ?></div>
                        <div class="stat-label">Roles Ativas</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?php echo $stats['total_recursos']; ?></div>
                        <div class="stat-label">Recursos do Sistema</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?php echo $stats['total_delegacoes']; ?></div>
                        <div class="stat-label">Delegações Ativas</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value"><?php echo $stats['logs_hoje']; ?></div>
                        <div class="stat-label">Acessos Hoje</div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <?php if ($podeCriar || $podeEditar): ?>
                <div class="quick-actions">
                    <div class="quick-action-btn" onclick="abrirModalNovaRole()">
                        <div class="quick-action-icon"><i class="fas fa-user-plus"></i></div>
                        <div>Nova Role</div>
                    </div>
                    <div class="quick-action-btn" onclick="abrirModalAtribuirRole()">
                        <div class="quick-action-icon"><i class="fas fa-user-tag"></i></div>
                        <div>Atribuir Role</div>
                    </div>
                    <div class="quick-action-btn" onclick="abrirModalDelegacao()">
                        <div class="quick-action-icon"><i class="fas fa-handshake"></i></div>
                        <div>Criar Delegação</div>
                    </div>
                    <div class="quick-action-btn" onclick="abrirModalPermissaoEspecifica()">
                        <div class="quick-action-icon"><i class="fas fa-key"></i></div>
                        <div>Permissão Específica</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Navigation Tabs -->
                <nav class="nav-tabs-custom">
                    <ul class="nav nav-tabs border-0" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-roles">
                                <i class="fas fa-users-cog me-2"></i>Roles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-funcionarios">
                                <i class="fas fa-user-shield me-2"></i>Funcionários
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-recursos">
                                <i class="fas fa-cube me-2"></i>Recursos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-delegacoes">
                                <i class="fas fa-handshake me-2"></i>Delegações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-matriz">
                                <i class="fas fa-th me-2"></i>Matriz de Permissões
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-logs">
                                <i class="fas fa-history me-2"></i>Logs de Acesso
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Roles Tab -->
                    <div class="tab-pane fade show active" id="tab-roles">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-users-cog me-2"></i>
                                    Gerenciamento de Roles
                                </h3>
                                <?php if ($podeCriar): ?>
                                <button class="btn btn-primary" onclick="abrirModalNovaRole()">
                                    <i class="fas fa-plus me-2"></i>Nova Role
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Hierarchy Visualization -->
                            <div class="hierarchy-tree mb-4">
                                <h5 class="mb-3">Hierarquia de Roles</h5>
                                <div id="hierarchyContainer">
                                    <!-- Será preenchido via JavaScript -->
                                </div>
                            </div>
                            
                            <!-- Roles Table -->
                            <table id="rolesTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Role</th>
                                        <th>Nível</th>
                                        <th>Tipo</th>
                                        <th>Usuários</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Preenchido via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Funcionários Tab -->
                    <div class="tab-pane fade" id="tab-funcionarios">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-user-shield me-2"></i>
                                    Permissões por Funcionário
                                </h3>
                            </div>
                            
                            <!-- Search and Filters -->
                            <div class="search-filter-bar">
                                <div class="search-input">
                                    <input type="text" class="form-control" id="searchFuncionario" 
                                           placeholder="Buscar funcionário...">
                                </div>
                                <select class="form-select" id="filterDepartamento" style="width: 200px;">
                                    <option value="">Todos os Departamentos</option>
                                </select>
                                <select class="form-select" id="filterRole" style="width: 200px;">
                                    <option value="">Todas as Roles</option>
                                </select>
                            </div>
                            
                            <!-- Funcionários Table -->
                            <table id="funcionariosTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Departamento</th>
                                        <th>Roles</th>
                                        <th>Permissões Especiais</th>
                                        <th>Delegações</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Preenchido via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Recursos Tab -->
                    <div class="tab-pane fade" id="tab-recursos">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-cube me-2"></i>
                                    Recursos do Sistema
                                </h3>
                                <?php if ($podeCriar): ?>
                                <button class="btn btn-primary" onclick="abrirModalNovoRecurso()">
                                    <i class="fas fa-plus me-2"></i>Novo Recurso
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Resources by Category -->
                            <div class="row" id="recursosContainer">
                                <!-- Será preenchido via JavaScript agrupado por categoria -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delegações Tab -->
                    <div class="tab-pane fade" id="tab-delegacoes">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-handshake me-2"></i>
                                    Delegações Temporárias
                                </h3>
                                <?php if ($podeCriar): ?>
                                <button class="btn btn-primary" onclick="abrirModalDelegacao()">
                                    <i class="fas fa-plus me-2"></i>Nova Delegação
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Delegation Cards -->
                            <div id="delegacoesContainer">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Matriz de Permissões Tab -->
                    <div class="tab-pane fade" id="tab-matriz">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-th me-2"></i>
                                    Matriz de Permissões
                                </h3>
                            </div>
                            
                            <!-- Permission Matrix -->
                            <div class="permission-matrix">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Recurso / Role</th>
                                            <!-- Roles serão adicionadas dinamicamente -->
                                        </tr>
                                    </thead>
                                    <tbody id="matrizPermissoesBody">
                                        <!-- Será preenchido via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Logs Tab -->
                    <div class="tab-pane fade" id="tab-logs">
                        <div class="content-section">
                            <div class="section-header">
                                <h3 class="section-title">
                                    <i class="fas fa-history me-2"></i>
                                    Logs de Acesso
                                </h3>
                                <button class="btn btn-secondary" onclick="exportarLogs()">
                                    <i class="fas fa-download me-2"></i>Exportar
                                </button>
                            </div>
                            
                            <!-- Filters -->
                            <div class="search-filter-bar">
                                <input type="date" class="form-control" id="logDateStart" style="width: 150px;">
                                <input type="date" class="form-control" id="logDateEnd" style="width: 150px;">
                                <select class="form-select" id="logResultado" style="width: 150px;">
                                    <option value="">Todos</option>
                                    <option value="PERMITIDO">Permitido</option>
                                    <option value="NEGADO">Negado</option>
                                    <option value="ERRO">Erro</option>
                                </select>
                                <button class="btn btn-primary" onclick="filtrarLogs()">
                                    <i class="fas fa-filter"></i> Filtrar
                                </button>
                            </div>
                            
                            <!-- Timeline Logs -->
                            <div class="timeline mt-4" id="logsTimeline">
                                <!-- Será preenchido via JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Nova Role -->
    <div class="modal fade" id="modalNovaRole" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Nova Role
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovaRole">
                        <div class="mb-3">
                            <label class="form-label">Código da Role</label>
                            <input type="text" class="form-control" id="roleCodigo" required>
                            <small class="text-muted">Ex: COORDENADOR, ANALISTA_SENIOR</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nome da Role</label>
                            <input type="text" class="form-control" id="roleNome" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea class="form-control" id="roleDescricao" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nível Hierárquico</label>
                            <input type="number" class="form-control" id="roleNivel" min="0" max="999" required>
                            <small class="text-muted">0-999 (maior = mais privilégios)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" id="roleTipo">
                                <option value="CUSTOMIZADO">Customizado</option>
                                <option value="DEPARTAMENTO">Departamento</option>
                                <option value="SISTEMA">Sistema</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarNovaRole()">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Atribuir Role -->
    <div class="modal fade" id="modalAtribuirRole" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tag me-2"></i>Atribuir Role
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formAtribuirRole">
                        <div class="mb-3">
                            <label class="form-label">Funcionário</label>
                            <select class="form-select" id="atribuirFuncionario" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="atribuirRole" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Departamento (opcional)</label>
                            <select class="form-select" id="atribuirDepartamento">
                                <option value="">Todos os departamentos</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="atribuirDataInicio">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data Fim (opcional)</label>
                            <input type="date" class="form-control" id="atribuirDataFim">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="atribuirObservacao" rows="2"></textarea>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="atribuirPrincipal">
                            <label class="form-check-label" for="atribuirPrincipal">
                                Definir como role principal
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarAtribuicaoRole()">
                        <i class="fas fa-save me-2"></i>Atribuir
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Delegação -->
    <div class="modal fade" id="modalDelegacao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-handshake me-2"></i>Criar Delegação
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formDelegacao">
                        <div class="mb-3">
                            <label class="form-label">Delegante (Quem delega)</label>
                            <select class="form-select" id="delegacaoDelegante" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delegado (Quem recebe)</label>
                            <select class="form-select" id="delegacaoDelegado" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Delegação</label>
                            <select class="form-select" id="delegacaoTipo" onchange="toggleDelegacaoOptions()">
                                <option value="completa">Delegação Completa</option>
                                <option value="role">Role Específica</option>
                                <option value="recurso">Recurso Específico</option>
                            </select>
                        </div>
                        <div class="mb-3" id="delegacaoRoleGroup" style="display:none;">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="delegacaoRole">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3" id="delegacaoRecursoGroup" style="display:none;">
                            <label class="form-label">Recurso</label>
                            <select class="form-select" id="delegacaoRecurso">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data/Hora Início</label>
                                    <input type="datetime-local" class="form-control" id="delegacaoInicio" required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data/Hora Fim</label>
                                    <input type="datetime-local" class="form-control" id="delegacaoFim" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea class="form-control" id="delegacaoMotivo" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarDelegacao()">
                        <i class="fas fa-save me-2"></i>Criar Delegação
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Permissão Específica -->
    <div class="modal fade" id="modalPermissaoEspecifica" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>Permissão Específica
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formPermissaoEspecifica">
                        <div class="mb-3">
                            <label class="form-label">Funcionário</label>
                            <select class="form-select" id="permEspecFuncionario" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recurso</label>
                            <select class="form-select" id="permEspecRecurso" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permissão</label>
                            <select class="form-select" id="permEspecPermissao" required>
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" id="permEspecTipo" required>
                                <option value="GRANT">GRANT (Conceder)</option>
                                <option value="DENY">DENY (Negar)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea class="form-control" id="permEspecMotivo" rows="3" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Início</label>
                                    <input type="date" class="form-control" id="permEspecInicio">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Fim</label>
                                    <input type="date" class="form-control" id="permEspecFim">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarPermissaoEspecifica()">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>
    
    <script>
        // Variáveis globais
        let rolesData = [];
        let funcionariosData = [];
        let recursosData = [];
        let permissoesData = [];
        let departamentosData = [];
        let delegacoesData = [];
        
        const podeEditar = <?php echo json_encode($podeEditar); ?>;
        const podeCriar = <?php echo json_encode($podeCriar); ?>;
        const podeDeletar = <?php echo json_encode($podeDeletar); ?>;
        
        // Inicialização
        $(document).ready(function() {
            carregarDados();
            inicializarDataTables();
            inicializarSelect2();
        });
        
        // Carregar todos os dados necessários
        async function carregarDados() {
            try {
                // Carregar roles
                const rolesResponse = await fetch('../api/permissoes/listar_roles.php');
                rolesData = await rolesResponse.json();
                
                // Carregar funcionários
                const funcionariosResponse = await fetch('../api/permissoes/listar_funcionarios.php');
                funcionariosData = await funcionariosResponse.json();
                
                // Carregar recursos
                const recursosResponse = await fetch('../api/permissoes/listar_recursos.php');
                recursosData = await recursosResponse.json();
                
                // Carregar permissões
                const permissoesResponse = await fetch('../api/permissoes/listar_permissoes.php');
                permissoesData = await permissoesResponse.json();
                
                // Carregar departamentos
                const departamentosResponse = await fetch('../api/permissoes/listar_departamentos.php');
                departamentosData = await departamentosResponse.json();
                
                // Carregar delegações
                const delegacoesResponse = await fetch('../api/permissoes/listar_delegacoes.php');
                delegacoesData = await delegacoesResponse.json();
                
                // Atualizar interfaces
                atualizarTabelaRoles();
                atualizarTabelaFuncionarios();
                atualizarHierarquia();
                atualizarRecursos();
                atualizarDelegacoes();
                atualizarMatrizPermissoes();
                carregarLogsRecentes();
                
                // Preencher selects
                preencherSelects();
                
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                Swal.fire('Erro', 'Erro ao carregar dados do sistema', 'error');
            }
        }
        
        // Inicializar DataTables
        function inicializarDataTables() {
            $('#rolesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                pageLength: 10,
                order: [[1, 'desc']]
            });
            
            $('#funcionariosTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                pageLength: 10
            });
        }
        
        // Inicializar Select2
        function inicializarSelect2() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecione...'
            });
        }
        
        // Atualizar tabela de roles
        function atualizarTabelaRoles() {
            const table = $('#rolesTable').DataTable();
            table.clear();
            
            rolesData.forEach(role => {
                const badge = getBadgeRole(role.codigo);
                const levelIndicator = `<span class="level-indicator level-${role.nivel_hierarquia}">${role.nivel_hierarquia}</span>`;
                const status = role.ativo ? 
                    '<span class="badge bg-success">Ativo</span>' : 
                    '<span class="badge bg-secondary">Inativo</span>';
                
                const actions = `
                    <button class="btn btn-sm btn-info btn-action" onclick="visualizarRole(${role.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${podeEditar ? `
                    <button class="btn btn-sm btn-warning btn-action" onclick="editarRole(${role.id})">
                        <i class="fas fa-edit"></i>
                    </button>` : ''}
                    ${podeEditar ? `
                    <button class="btn btn-sm btn-primary btn-action" onclick="configurarPermissoesRole(${role.id})">
                        <i class="fas fa-cog"></i>
                    </button>` : ''}
                    ${podeDeletar ? `
                    <button class="btn btn-sm btn-danger btn-action" onclick="excluirRole(${role.id})">
                        <i class="fas fa-trash"></i>
                    </button>` : ''}
                `;
                
                table.row.add([
                    badge + ' ' + role.nome,
                    levelIndicator,
                    role.tipo,
                    role.total_usuarios || 0,
                    status,
                    actions
                ]);
            });
            
            table.draw();
        }
        
        // Atualizar tabela de funcionários
        function atualizarTabelaFuncionarios() {
            const table = $('#funcionariosTable').DataTable();
            table.clear();
            
            funcionariosData.forEach(func => {
                const roles = func.roles ? func.roles.map(r => 
                    `<span class="badge bg-primary me-1">${r.nome}</span>`
                ).join(' ') : '-';
                
                const permEspeciais = func.permissoes_especiais || 0;
                const delegacoes = func.delegacoes_ativas || 0;
                
                const actions = `
                    <button class="btn btn-sm btn-info btn-action" onclick="visualizarPermissoesFuncionario(${func.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${podeEditar ? `
                    <button class="btn btn-sm btn-warning btn-action" onclick="editarPermissoesFuncionario(${func.id})">
                        <i class="fas fa-edit"></i>
                    </button>` : ''}
                `;
                
                table.row.add([
                    func.nome,
                    func.departamento || '-',
                    roles,
                    permEspeciais > 0 ? `<span class="badge bg-warning">${permEspeciais}</span>` : '-',
                    delegacoes > 0 ? `<span class="badge bg-info">${delegacoes}</span>` : '-',
                    actions
                ]);
            });
            
            table.draw();
        }
        
        // Atualizar hierarquia visual
        function atualizarHierarquia() {
            const container = document.getElementById('hierarchyContainer');
            container.innerHTML = '';
            
            // Ordenar roles por nível hierárquico
            const sortedRoles = [...rolesData].sort((a, b) => b.nivel_hierarquia - a.nivel_hierarquia);
            
            sortedRoles.forEach(role => {
                const item = document.createElement('div');
                item.className = 'hierarchy-item';
                item.style.marginLeft = `${(1000 - role.nivel_hierarquia) / 20}px`;
                
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${role.nome}</strong>
                            <span class="hierarchy-level ms-2">Nível ${role.nivel_hierarquia}</span>
                        </div>
                        <div>
                            ${getBadgeRole(role.codigo)}
                        </div>
                    </div>
                    <small class="text-muted">${role.descricao || ''}</small>
                `;
                
                container.appendChild(item);
            });
        }
        
        // Atualizar recursos
        function atualizarRecursos() {
            const container = document.getElementById('recursosContainer');
            container.innerHTML = '';
            
            // Agrupar recursos por categoria
            const categorias = {};
            recursosData.forEach(recurso => {
                if (!categorias[recurso.categoria]) {
                    categorias[recurso.categoria] = [];
                }
                categorias[recurso.categoria].push(recurso);
            });
            
            // Criar cards por categoria
            Object.keys(categorias).forEach(categoria => {
                const col = document.createElement('div');
                col.className = 'col-md-6 mb-3';
                
                const card = document.createElement('div');
                card.className = 'card';
                
                card.innerHTML = `
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-folder me-2"></i>${categoria}
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            ${categorias[categoria].map(r => `
                                <li class="mb-2">
                                    <i class="fas fa-cube text-primary me-2"></i>
                                    <strong>${r.nome}</strong>
                                    <small class="text-muted d-block ms-4">${r.codigo}</small>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
                
                col.appendChild(card);
                container.appendChild(col);
            });
        }
        
        // Atualizar delegações
        function atualizarDelegacoes() {
            const container = document.getElementById('delegacoesContainer');
            container.innerHTML = '';
            
            if (delegacoesData.length === 0) {
                container.innerHTML = '<p class="text-muted">Nenhuma delegação ativa no momento.</p>';
                return;
            }
            
            delegacoesData.forEach(del => {
                const now = new Date();
                const inicio = new Date(del.data_inicio);
                const fim = new Date(del.data_fim);
                
                let status = 'delegation-future';
                let badge = 'Futura';
                
                if (now >= inicio && now <= fim) {
                    status = 'delegation-active';
                    badge = 'Ativa';
                } else if (now > fim) {
                    status = 'delegation-expired';
                    badge = 'Expirada';
                }
                
                const card = document.createElement('div');
                card.className = 'delegation-card';
                
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6>
                                <i class="fas fa-user me-2"></i>${del.delegante_nome}
                                <i class="fas fa-arrow-right mx-2"></i>
                                <i class="fas fa-user me-2"></i>${del.delegado_nome}
                            </h6>
                            <p class="mb-2">${del.motivo}</p>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                ${formatarData(del.data_inicio)} até ${formatarData(del.data_fim)}
                            </small>
                        </div>
                        <span class="delegation-badge ${status}">${badge}</span>
                    </div>
                `;
                
                container.appendChild(card);
            });
        }
        
        // Atualizar matriz de permissões
        function atualizarMatrizPermissoes() {
            // Implementação simplificada - pode ser expandida
            const thead = document.querySelector('#tab-matriz thead tr');
            const tbody = document.getElementById('matrizPermissoesBody');
            
            // Limpar e reconstruir cabeçalho
            thead.innerHTML = '<th>Recurso / Role</th>';
            rolesData.slice(0, 5).forEach(role => {
                thead.innerHTML += `<th class="text-center">${role.codigo}</th>`;
            });
            
            // Construir corpo da tabela
            tbody.innerHTML = '';
            recursosData.slice(0, 10).forEach(recurso => {
                let row = `<tr><td>${recurso.nome}</td>`;
                
                rolesData.slice(0, 5).forEach(role => {
                    // Aqui você verificaria as permissões reais
                    const hasPermission = Math.random() > 0.5;
                    row += `<td class="permission-cell">
                        ${hasPermission ? 
                            '<i class="fas fa-check permission-check"></i>' : 
                            '<i class="fas fa-times permission-deny"></i>'}
                    </td>`;
                });
                
                row += '</tr>';
                tbody.innerHTML += row;
            });
        }
        
        // Carregar logs recentes
        async function carregarLogsRecentes() {
            try {
                const response = await fetch('../api/permissoes/listar_logs.php?limit=20');
                const logs = await response.json();
                
                const timeline = document.getElementById('logsTimeline');
                timeline.innerHTML = '';
                
                logs.forEach(log => {
                    const item = document.createElement('div');
                    item.className = 'timeline-item';
                    
                    const resultadoClass = log.resultado === 'PERMITIDO' ? 'success' : 
                                          log.resultado === 'NEGADO' ? 'danger' : 'warning';
                    
                    item.innerHTML = `
                        <div class="timeline-marker bg-${resultadoClass}"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <strong>${log.funcionario_nome}</strong>
                                <small class="text-muted">${formatarDataHora(log.criado_em)}</small>
                            </div>
                            <p class="mb-1">${log.acao}</p>
                            <span class="badge bg-${resultadoClass}">${log.resultado}</span>
                            ${log.motivo_negacao ? `<small class="text-danger d-block">${log.motivo_negacao}</small>` : ''}
                        </div>
                    `;
                    
                    timeline.appendChild(item);
                });
            } catch (error) {
                console.error('Erro ao carregar logs:', error);
            }
        }
        
        // Preencher selects dos modais
        function preencherSelects() {
            // Funcionários
            const selectFuncionarios = ['#atribuirFuncionario', '#delegacaoDelegante', 
                                       '#delegacaoDelegado', '#permEspecFuncionario'];
            selectFuncionarios.forEach(selector => {
                const select = document.querySelector(selector);
                if (select) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    funcionariosData.forEach(func => {
                        select.innerHTML += `<option value="${func.id}">${func.nome}</option>`;
                    });
                }
            });
            
            // Roles
            const selectRoles = ['#atribuirRole', '#delegacaoRole'];
            selectRoles.forEach(selector => {
                const select = document.querySelector(selector);
                if (select) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    rolesData.forEach(role => {
                        select.innerHTML += `<option value="${role.id}">${role.nome}</option>`;
                    });
                }
            });
            
            // Departamentos
            const selectDepartamentos = ['#atribuirDepartamento', '#filterDepartamento'];
            selectDepartamentos.forEach(selector => {
                const select = document.querySelector(selector);
                if (select) {
                    const keepEmpty = selector === '#atribuirDepartamento';
                    select.innerHTML = keepEmpty ? 
                        '<option value="">Todos os departamentos</option>' : 
                        '<option value="">Todos os Departamentos</option>';
                    departamentosData.forEach(dept => {
                        select.innerHTML += `<option value="${dept.id}">${dept.nome}</option>`;
                    });
                }
            });
            
            // Recursos
            const selectRecursos = ['#delegacaoRecurso', '#permEspecRecurso'];
            selectRecursos.forEach(selector => {
                const select = document.querySelector(selector);
                if (select) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    recursosData.forEach(recurso => {
                        select.innerHTML += `<option value="${recurso.id}">${recurso.categoria} - ${recurso.nome}</option>`;
                    });
                }
            });
            
            // Permissões
            const selectPermissoes = ['#permEspecPermissao'];
            selectPermissoes.forEach(selector => {
                const select = document.querySelector(selector);
                if (select) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    permissoesData.forEach(perm => {
                        select.innerHTML += `<option value="${perm.id}">${perm.nome}</option>`;
                    });
                }
            });
        }
        
        // === FUNÇÕES DOS MODAIS ===
        
        function abrirModalNovaRole() {
            $('#modalNovaRole').modal('show');
        }
        
        function abrirModalAtribuirRole() {
            $('#modalAtribuirRole').modal('show');
        }
        
        function abrirModalDelegacao() {
            $('#modalDelegacao').modal('show');
        }
        
        function abrirModalPermissaoEspecifica() {
            $('#modalPermissaoEspecifica').modal('show');
        }
        
        function toggleDelegacaoOptions() {
            const tipo = document.getElementById('delegacaoTipo').value;
            document.getElementById('delegacaoRoleGroup').style.display = tipo === 'role' ? 'block' : 'none';
            document.getElementById('delegacaoRecursoGroup').style.display = tipo === 'recurso' ? 'block' : 'none';
        }
        
        // === FUNÇÕES DE SALVAMENTO ===
        
        async function salvarNovaRole() {
            const data = {
                codigo: document.getElementById('roleCodigo').value,
                nome: document.getElementById('roleNome').value,
                descricao: document.getElementById('roleDescricao').value,
                nivel_hierarquia: document.getElementById('roleNivel').value,
                tipo: document.getElementById('roleTipo').value
            };
            
            try {
                const response = await fetch('../api/permissoes/criar_role.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire('Sucesso', 'Role criada com sucesso!', 'success');
                    $('#modalNovaRole').modal('hide');
                    carregarDados();
                } else {
                    Swal.fire('Erro', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Erro', 'Erro ao criar role', 'error');
            }
        }
        
        async function salvarAtribuicaoRole() {
            const data = {
                funcionario_id: document.getElementById('atribuirFuncionario').value,
                role_id: document.getElementById('atribuirRole').value,
                departamento_id: document.getElementById('atribuirDepartamento').value || null,
                data_inicio: document.getElementById('atribuirDataInicio').value,
                data_fim: document.getElementById('atribuirDataFim').value || null,
                principal: document.getElementById('atribuirPrincipal').checked,
                observacao: document.getElementById('atribuirObservacao').value
            };
            
            try {
                const response = await fetch('../api/permissoes/atribuir_role.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire('Sucesso', 'Role atribuída com sucesso!', 'success');
                    $('#modalAtribuirRole').modal('hide');
                    carregarDados();
                } else {
                    Swal.fire('Erro', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Erro', 'Erro ao atribuir role', 'error');
            }
        }
        
        async function salvarDelegacao() {
            const tipo = document.getElementById('delegacaoTipo').value;
            const data = {
                delegante_id: document.getElementById('delegacaoDelegante').value,
                delegado_id: document.getElementById('delegacaoDelegado').value,
                role_id: tipo === 'role' ? document.getElementById('delegacaoRole').value : null,
                recurso_id: tipo === 'recurso' ? document.getElementById('delegacaoRecurso').value : null,
                data_inicio: document.getElementById('delegacaoInicio').value,
                data_fim: document.getElementById('delegacaoFim').value,
                motivo: document.getElementById('delegacaoMotivo').value
            };
            
            try {
                const response = await fetch('../api/permissoes/criar_delegacao.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire('Sucesso', 'Delegação criada com sucesso!', 'success');
                    $('#modalDelegacao').modal('hide');
                    carregarDados();
                } else {
                    Swal.fire('Erro', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Erro', 'Erro ao criar delegação', 'error');
            }
        }
        
        async function salvarPermissaoEspecifica() {
            const data = {
                funcionario_id: document.getElementById('permEspecFuncionario').value,
                recurso_id: document.getElementById('permEspecRecurso').value,
                permissao_id: document.getElementById('permEspecPermissao').value,
                tipo: document.getElementById('permEspecTipo').value,
                motivo: document.getElementById('permEspecMotivo').value,
                data_inicio: document.getElementById('permEspecInicio').value || null,
                data_fim: document.getElementById('permEspecFim').value || null
            };
            
            try {
                const response = await fetch('../api/permissoes/criar_permissao_especifica.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire('Sucesso', 'Permissão específica criada com sucesso!', 'success');
                    $('#modalPermissaoEspecifica').modal('hide');
                    carregarDados();
                } else {
                    Swal.fire('Erro', result.message, 'error');
                }
            } catch (error) {
                Swal.fire('Erro', 'Erro ao criar permissão específica', 'error');
            }
        }
        
        // === FUNÇÕES AUXILIARES ===
        
        function getBadgeRole(codigo) {
            const badges = {
                'SUPER_ADMIN': 'badge-super-admin',
                'PRESIDENTE': 'badge-presidente',
                'DIRETOR': 'badge-diretor',
                'SUBDIRETOR': 'badge-diretor',
                'SUPERVISOR': 'badge-funcionario',
                'FUNCIONARIO_SENIOR': 'badge-funcionario',
                'FUNCIONARIO': 'badge-funcionario',
                'VISUALIZADOR': 'badge-funcionario'
            };
            
            const badgeClass = badges[codigo] || 'badge-funcionario';
            return `<span class="badge badge-role ${badgeClass}">${codigo}</span>`;
        }
        
        function formatarData(data) {
            return new Date(data).toLocaleDateString('pt-BR');
        }
        
        function formatarDataHora(data) {
            return new Date(data).toLocaleString('pt-BR');
        }
        
        // Funções de visualização e edição
        function visualizarRole(id) {
            // Implementar modal de visualização
            console.log('Visualizar role:', id);
        }
        
        function editarRole(id) {
            // Implementar modal de edição
            console.log('Editar role:', id);
        }
        
        function configurarPermissoesRole(id) {
            // Implementar modal de configuração de permissões
            console.log('Configurar permissões da role:', id);
        }
        
        function excluirRole(id) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: 'Tem certeza que deseja excluir esta role?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Implementar exclusão
                    console.log('Excluir role:', id);
                }
            });
        }
        
        function visualizarPermissoesFuncionario(id) {
            // Implementar modal de visualização
            console.log('Visualizar permissões do funcionário:', id);
        }
        
        function editarPermissoesFuncionario(id) {
            // Implementar modal de edição
            console.log('Editar permissões do funcionário:', id);
        }
        
        function filtrarLogs() {
            // Implementar filtro de logs
            const inicio = document.getElementById('logDateStart').value;
            const fim = document.getElementById('logDateEnd').value;
            const resultado = document.getElementById('logResultado').value;
            
            console.log('Filtrar logs:', {inicio, fim, resultado});
            carregarLogsRecentes();
        }
        
        function exportarLogs() {
            // Implementar exportação de logs
            console.log('Exportar logs');
        }
    </script>
</body>
</html>