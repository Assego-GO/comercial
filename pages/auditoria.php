<?php
/**
 * Página de Auditoria - Sistema ASSEGO
 * pages/auditoria.php
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
$page_title = 'Auditoria - ASSEGO';

// Verificar se o usuário tem permissão para acessar a auditoria
$temPermissaoAuditoria = false;
$motivoNegacao = '';
$isPresidencia = false;
$isDiretor = false;
$departamentoUsuario = null;

// Debug completo ANTES das verificações
error_log("=== DEBUG DETALHADO PERMISSÕES AUDITORIA ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Array completo do usuário: " . print_r($usuarioLogado, true));
error_log("Departamento ID (valor): " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("Departamento ID (tipo): " . gettype($usuarioLogado['departamento_id'] ?? null));
error_log("É Diretor (método): " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// CORREÇÃO: NOVA LÓGICA DE PERMISSÕES (igual à correção de funcionários)
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $cargoUsuario = $usuarioLogado['cargo'] ?? '';
    $departamentoUsuario = $deptId;
    
    // Debug dos testes de comparação
    error_log("Testes de comparação:");
    error_log("  deptId === '1': " . ($deptId === '1' ? 'true' : 'false'));
    error_log("  deptId === 1: " . ($deptId === 1 ? 'true' : 'false'));
    error_log("  deptId == 1: " . ($deptId == 1 ? 'true' : 'false'));
    error_log("  Cargo: " . $cargoUsuario);
    
    // NOVA LÓGICA: Sistema flexível de permissões
    if ($deptId == 1) {
        // PRESIDÊNCIA - vê tudo
        $temPermissaoAuditoria = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Departamento da Presidência (ID = 1) - VÊ TUDO");
    } elseif (in_array($cargoUsuario, ['Presidente', 'Vice-Presidente'])) {
        // Apenas Presidente e Vice-Presidente veem todos (mesmo fora da presidência)
        $temPermissaoAuditoria = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: {$cargoUsuario} - VÊ TUDO");
    } elseif (in_array($cargoUsuario, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador'])) {
        // CORREÇÃO: Diretores agora também veem apenas seu departamento
        $temPermissaoAuditoria = true;
        $isDiretor = true;
        error_log("✅ Permissão concedida: {$cargoUsuario} - VÊ APENAS DEPARTAMENTO " . $deptId);
    } else {
        $motivoNegacao = 'Acesso restrito ao departamento da Presidência, Presidente/Vice-Presidente ou cargos de gestão.';
        error_log("❌ Acesso negado. Departamento: '$deptId', Cargo: '$cargoUsuario'. Necessário: Presidência (ID = 1) OU Presidente/Vice-Presidente OU Diretor/Gerente/Supervisor/Coordenador");
    }
} else {
    $motivoNegacao = 'Departamento não identificado. Acesso restrito ao departamento da Presidência ou cargos de gestão.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Log final do resultado
if (!$temPermissaoAuditoria) {
    error_log("❌ ACESSO NEGADO: " . $motivoNegacao);
} else {
    if ($isPresidencia) {
        $motivo = "Usuário com Acesso Total - " . ($usuarioLogado['departamento_id'] == 1 ? 'Presidência' : $usuarioLogado['cargo']);
        error_log("✅ ACESSO PERMITIDO - " . $motivo);
    } else {
        $motivo = "Usuário é {$cargoUsuario} - Acesso Departamental (Dept: " . $departamentoUsuario . ")";
        error_log("✅ ACESSO PERMITIDO - " . $motivo);
    }
}

// Busca estatísticas de auditoria (apenas se tem permissão)
if ($temPermissaoAuditoria) {
    try {
        $auditoria = new Auditoria();
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Adicionar filtro de departamento se não for presidência
        $whereDepartamento = '';
        $paramsDepartamento = [];
        
        if (!$isPresidencia && $isDiretor && $departamentoUsuario) {
            // Para diretores, filtrar apenas registros relacionados ao seu departamento
            // Isso pode incluir funcionários do departamento ou ações em registros relacionados
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
        
        // Total de registros
        $sql = "SELECT COUNT(*) as total FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id 
                WHERE 1=1" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $totalRegistros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ações hoje
        $sql = "SELECT COUNT(*) as hoje FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id 
                WHERE DATE(a.data_hora) = CURDATE()" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $acoesHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];
        
        // Usuários ativos (últimas 24h)
        $sql = "SELECT COUNT(DISTINCT a.funcionario_id) as usuarios_ativos
                FROM Auditoria a
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                WHERE a.data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND a.funcionario_id IS NOT NULL" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $usuariosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['usuarios_ativos'];
        
        // Alertas (tentativas de login falharam, ações suspeitas)
        $sql = "SELECT COUNT(*) as alertas
                FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                WHERE a.acao IN ('LOGIN_FALHA', 'DELETE') 
                AND DATE(a.data_hora) = CURDATE()" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $alertas = $stmt->fetch(PDO::FETCH_ASSOC)['alertas'];

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas da auditoria: " . $e->getMessage());
        $totalRegistros = $acoesHoje = $usuariosAtivos = $alertas = 0;
    }
} else {
    $totalRegistros = $acoesHoje = $usuariosAtivos = $alertas = 0;
}

// Cria instância do Header Component
// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'email' => $usuarioLogado['email'] ?? $_SESSION['funcionario_email'] ?? 'usuario@assego.com.br', // ← ADICIONAR ESTA LINHA
        'avatar' => $usuarioLogado['avatar'] ?? null,
        'departamento_id' => $usuarioLogado['departamento_id'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'auditoria',
    'notificationCount' => $alertas,
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/auditoriaModels.css">
     <link rel="stylesheet" href="estilizacao/auditoria.css">
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
            <?php if (!$temPermissaoAuditoria): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Área de Auditoria</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Como resolver:</h6>
                    <ol class="mb-0">
                        <li>Verifique se você está no departamento correto</li>
                        <li>Confirme se você é diretor no sistema</li>
                        <li>Entre em contato com o administrador se necessário</li>
                    </ol>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Suas informações atuais:</h6>
                        <ul class="mb-0">
                            <li><strong>Nome:</strong> <?php echo htmlspecialchars($usuarioLogado['nome']); ?></li>
                            <li><strong>Cargo:</strong> <?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'N/A'); ?></li>
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
                            <li>Ser diretor <strong>OU</strong></li>
                            <li>Estar no departamento da Presidência</li>
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
                <h1 class="page-title">
                    <div class="page">
                        <i class="fas"></i>
                    </div>
                    Sistema de Auditoria
                    <?php if (!$isPresidencia): ?>
                        <small class="text-muted">- Departamento <?php echo htmlspecialchars($departamentoUsuario); ?></small>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <?php if ($isPresidencia): ?>
                        Monitoramento completo de atividades e segurança do sistema
                    <?php else: ?>
                        Monitoramento de atividades do seu departamento
                    <?php endif; ?>
                </p>
            </div>

            <!-- Stats Grid com Dual Cards - Auditoria -->
<div class="stats-grid" data-aos="fade-up">
    <!-- Card 1: Total de Registros + Ações Hoje -->
    <div class="stat-card dual-stat-card">
        <div class="dual-stat-header">
            <div class="dual-stat-title">
                <i class="fas fa-database"></i>
                Atividade Geral
                <?php echo !$isPresidencia ? ' (Dept.)' : ''; ?>
            </div>
            <div class="dual-stat-percentage" id="atividadePercent">
                <i class="fas fa-chart-line"></i>
                Status
            </div>
        </div>
        <div class="dual-stats-row">
            <div class="dual-stat-item">
                <div class="dual-stat-icon registros-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="dual-stat-info">
                    <div class="dual-stat-value"><?php echo number_format($totalRegistros); ?></div>
                    <div class="dual-stat-label">Total Registros</div>
                </div>
            </div>
            <div class="dual-stats-separator"></div>
            <div class="dual-stat-item">
                <div class="dual-stat-icon acoes-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="dual-stat-info">
                    <div class="dual-stat-value"><?php echo $acoesHoje; ?></div>
                    <div class="dual-stat-label">Ações Hoje</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Usuários Ativos + Alertas -->
    <div class="stat-card dual-stat-card">
        <div class="dual-stat-header">
            <div class="dual-stat-title">
                <i class="fas fa-users"></i>
                Monitoramento
                <?php echo !$isPresidencia ? ' (Dept.)' : ''; ?>
            </div>
            <div class="dual-stat-percentage" id="monitoramentoPercent">
                <i class="fas fa-shield-alt"></i>
                Segurança
            </div>
        </div>
        <div class="dual-stats-row">
            <div class="dual-stat-item">
                <div class="dual-stat-icon usuarios-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="dual-stat-info">
                    <div class="dual-stat-value"><?php echo $usuariosAtivos; ?></div>
                    <div class="dual-stat-label">Usuários Ativos</div>
                </div>
            </div>
            <div class="dual-stats-separator"></div>
            <div class="dual-stat-item">
                <div class="dual-stat-icon alertas-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="dual-stat-info">
                    <div class="dual-stat-value"><?php echo $alertas; ?></div>
                    <div class="dual-stat-label">Alertas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Performance Temporal -->
    <div class="stat-card dual-stat-card">
        <div class="dual-stat-header">
            <div class="dual-stat-title">
                <i class="fas fa-chart-area"></i>
                Performance Temporal
                <?php echo !$isPresidencia ? ' (Dept.)' : ''; ?>
            </div>
            <div class="dual-stat-percentage" id="performancePercent">
                <i class="fas fa-trending-up"></i>
                Tendência
            </div>
        </div>
        <div class="dual-stats-row">
            <div class="dual-stat-item">
                <div class="dual-stat-icon hoje-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="dual-stat-info">
                    <div class="dual-stat-value"><?php echo $acoesHoje; ?></div>
                    <div class="dual-stat-label">Hoje</div>
                </div>
            </div>
            <div class="dual-stats-separator"></div>
            <div class="dual-stat-item">
                <div class="dual-stat-icon periodo-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="dual-stat-info">
                    <div class="dual-stat-value"><?php 
                        // Calculate a simple trend - you can modify this logic
                        $tendencia = $totalRegistros > 0 ? round(($acoesHoje / max($totalRegistros, 1)) * 100, 1) : 0;
                        echo $tendencia . '%';
                    ?></div>
                    <div class="dual-stat-label">Taxa Atividade</div>
                </div>
            </div>
        </div>
    </div>
</div>


            <!-- Quick Actions -->
            <div class="quick-actions" data-aos="fade-up" data-aos-delay="100">
                <h3 class="quick-actions-title">Ações Rápidas</h3>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="abrirRelatorios()">
                        <i class="fas fa-chart-line quick-action-icon"></i>
                        Relatórios
                    </button>
                    <button class="quick-action-btn" onclick="verEstatisticas()">
                        <i class="fas fa-chart-bar quick-action-icon"></i>
                        Estatísticas
                    </button>
                    <button class="quick-action-btn" onclick="exportarDados()">
                        <i class="fas fa-download quick-action-icon"></i>
                        Exportar
                    </button>
                    <button class="quick-action-btn" onclick="configurarAuditoria()">
                        <i class="fas fa-cog quick-action-icon"></i>
                        Configurações
                    </button>
                </div>
            </div>

            <!-- Audit Section -->
            <div class="documents-section" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h2 class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        Registros de Auditoria
                        <?php if (!$isPresidencia): ?>
                            <small class="text-muted">- Departamento <?php echo htmlspecialchars($departamentoUsuario); ?></small>
                        <?php endif; ?>
                    </h2>
                    <div class="section-actions">
                        <button class="btn-action secondary" onclick="atualizarRegistros()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Buscar por funcionário ou tabela...">
                    <select class="filter-select" id="filterAcao">
                        <option value="">Todas as ações</option>
                        <option value="INSERT">Inserção</option>
                        <option value="UPDATE">Atualização</option>
                        <option value="DELETE">Exclusão</option>
                        <option value="LOGIN">Login</option>
                        <option value="LOGOUT">Logout</option>
                    </select>
                    <select class="filter-select" id="filterTabela">
                        <option value="">Todas as tabelas</option>
                        <option value="Associados">Associados</option>
                        <option value="Funcionarios">Funcionários</option>
                        <option value="Documentos_Associado">Documentos</option>
                    </select>
                    <input type="date" class="filter-input" id="filterData" style="min-width: 150px;">
                </div>

                <!-- Audit Table -->
                <div class="table-responsive">
                    <table class="audit-table table">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Funcionário</th>
                                <th>Ação</th>
                                <th>Tabela</th>
                                <th>Registro</th>
                                <th>IP</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                    <p class="text-muted mt-2">Carregando registros de auditoria...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Mostrando <span id="paginaAtual">1</span> - <span id="totalPaginas">1</span> de <span id="totalRegistrosPagina">0</span> registros
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

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalhesModal" tabindex="-1" aria-labelledby="detalhesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalhesModalLabel">
                        <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                        Detalhes da Auditoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detalhesModalBody">
                    <!-- Conteúdo será carregado dinamicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Relatórios -->
    <div class="modal fade" id="relatoriosModal" tabindex="-1" aria-labelledby="relatoriosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="relatoriosModalLabel">
                        <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                        Relatórios de Auditoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="chartAcoesPorDia"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="chartTiposAcao"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Filtros de Relatório</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <select class="form-select" id="relatorioTipo">
                                        <option value="geral">Relatório Geral</option>
                                        <option value="por_funcionario">Por Funcionário</option>
                                        <option value="por_acao">Por Tipo de Ação</option>
                                        <option value="seguranca">Segurança</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="relatorioPeriodo">
                                        <option value="hoje">Hoje</option>
                                        <option value="semana">Esta Semana</option>
                                        <option value="mes" selected>Este Mês</option>
                                        <option value="trimestre">Trimestre</option>
                                        <option value="ano">Este Ano</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary" onclick="gerarRelatorio()">
                                        <i class="fas fa-chart-bar"></i>
                                        Gerar
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-success" onclick="exportarRelatorio()">
                                        <i class="fas fa-download"></i>
                                        Exportar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
<!-- GABRIEL TCHOLA DEVELOPER 01/09/2025 -->
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // ===== CLASSES E SISTEMAS AUXILIARES =====

        // Sistema de Notificações Toast
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

        // Cache Simples
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

        // Sistema de Atualização Automática
        class AutoUpdater {
            constructor(interval = 30000) {
                this.interval = interval;
                this.timer = null;
                this.isActive = true;
            }
            
            start() {
                if (this.timer) this.stop();
                
                this.timer = setInterval(() => {
                    if (this.isActive && document.hasFocus()) {
                        carregarRegistrosAuditoria();
                    }
                }, this.interval);
            }
            
            stop() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            }
            
            pause() {
                this.isActive = false;
            }
            
            resume() {
                this.isActive = true;
            }
        }

        // ===== INICIALIZAÇÃO =====

        // Instanciar sistemas
        const notifications = new NotificationSystem();
        const cache = new SimpleCache();
        const autoUpdater = new AutoUpdater();

        // Variáveis globais
        let registrosAuditoria = [];
        let currentPage = 1;
        let totalPages = 1;
        let chartAcoes, chartTipos;
        const temPermissao = <?php echo json_encode($temPermissaoAuditoria); ?>;
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

        const debouncedFilter = debounce(filtrarRegistros, 300);

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa AOS
            AOS.init({
                duration: 800,
                once: true
            });

            // Debug inicial
            console.log('=== DEBUG AUDITORIA DEPARTAMENTAL ===');
            const usuario = <?php echo json_encode($usuarioLogado); ?>;
            
            console.log('👤 Usuário:', usuario.nome);
            console.log('👔 É diretor:', isDiretor);
            console.log('🏛️ É presidência:', isPresidencia);
            console.log('🏬 Departamento:', departamentoUsuario);
            console.log('🔐 Tem permissão:', temPermissao);
            
            // Só continuar se tiver permissão
            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

            if (isPresidencia) {
                console.log('✅ Usuário da Presidência - carregando todas as funcionalidades...');
            } else {
                console.log('✅ Diretor autorizado - carregando funcionalidades departamentais...');
            }

            // Carregar dados automaticamente
            setTimeout(atualizarPercentuaisKPI, 1000);
    
            // Update every 30 seconds along with the data refresh
            setInterval(atualizarPercentuaisKPI, 30000);
            carregarRegistrosAuditoria();
            configurarFiltros();
            configurarEventos();
            initializeCharts();
            
            // Iniciar auto-update
            autoUpdater.start();
        });

        // ===== FUNÇÕES PRINCIPAIS =====

        // Função para obter parâmetros de filtro departamental
        function obterParametrosDepartamentais() {
            const params = {};
            
            // Se não for presidência, adicionar filtro de departamento
            if (!isPresidencia && isDiretor && departamentoUsuario) {
                params.departamento_usuario = departamentoUsuario;
            }
            
            return params;
        }

        // Carregar registros de auditoria
        async function carregarRegistrosAuditoria(page = 1, filters = {}) {
            if (!temPermissao) {
                console.log('❌ Sem permissão para carregar registros');
                return;
            }
            
            const tbody = document.getElementById('auditTableBody');
            
            // Adicionar filtros departamentais
            const allFilters = {
                ...filters,
                ...obterParametrosDepartamentais()
            };
            
            // Verificar cache primeiro
            const cacheKey = `audit_${page}_${JSON.stringify(allFilters)}`;
            const cached = cache.get(cacheKey);
            if (cached) {
                renderizarTabela(cached.registros);
                atualizarPaginacao(cached.paginacao);
                return;
            }
            
            // Mostra loading se não for do cache
            if (tbody.children.length > 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="text-muted mt-2">Carregando registros...</p>
                        </td>
                    </tr>
                `;
            }

            try {
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
                    registrosAuditoria = data.data.registros || [];
                    
                    // Armazenar em cache
                    cache.set(cacheKey, {
                        registros: registrosAuditoria,
                        paginacao: data.data.paginacao
                    });
                    
                    renderizarTabela(registrosAuditoria);
                    atualizarPaginacao(data.data.paginacao);
                    
                    const mensagem = isPresidencia 
                        ? `${registrosAuditoria.length} registro(s) carregado(s) (Todos os departamentos)`
                        : `${registrosAuditoria.length} registro(s) carregado(s) (Departamento ${departamentoUsuario})`;
                    
                    notifications.show(mensagem, 'success', 2000);
                } else {
                    throw new Error(data.message || 'Erro ao carregar registros');
                }
            } catch (error) {
                console.error('❌ Erro ao carregar registros:', error);
                mostrarErroTabela('Erro ao carregar registros: ' + error.message);
            }
        }

        // Renderizar tabela
        function renderizarTabela(registros) {
            const tbody = document.getElementById('auditTableBody');
            
            if (!tbody) {
                console.error('Tbody não encontrado');
                return;
            }
            
            tbody.innerHTML = '';

            if (registros.length === 0) {
                const mensagem = isPresidencia 
                    ? 'Nenhum registro encontrado no sistema'
                    : `Nenhum registro encontrado para o departamento ${departamentoUsuario}`;
                
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Nenhum registro encontrado</h5>
                            <p class="text-muted">${mensagem}</p>
                            <p class="text-muted">Tente ajustar os filtros ou o período de busca.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            registros.forEach(registro => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${formatarData(registro.data_hora)}</td>
                    <td>
                        ${registro.funcionario_nome || 'Sistema'}
                        ${!isPresidencia && registro.funcionario_departamento ? 
                            `<br><small class="text-muted">Dept: ${registro.funcionario_departamento}</small>` : ''}
                    </td>
                    <td><span class="action-badge ${registro.acao}">${registro.acao}</span></td>
                    <td>${registro.tabela}</td>
                    <td>${registro.registro_id || '-'}</td>
                    <td>${registro.ip_origem || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="mostrarDetalhes(${registro.id})" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Configurar filtros
        function configurarFiltros() {
            const searchInput = document.getElementById('searchInput');
            const filterAcao = document.getElementById('filterAcao');
            const filterTabela = document.getElementById('filterTabela');
            const filterData = document.getElementById('filterData');

            if (searchInput) searchInput.addEventListener('input', debouncedFilter);
            if (filterAcao) filterAcao.addEventListener('change', filtrarRegistros);
            if (filterTabela) filterTabela.addEventListener('change', filtrarRegistros);
            if (filterData) filterData.addEventListener('change', filtrarRegistros);
        }

        // Filtrar registros
        function filtrarRegistros() {
            const searchInput = document.getElementById('searchInput');
            const filterAcao = document.getElementById('filterAcao');
            const filterTabela = document.getElementById('filterTabela');
            const filterData = document.getElementById('filterData');
            
            const filters = {};
            
            if (searchInput && searchInput.value) {
                filters.search = searchInput.value;
            }
            
            if (filterAcao && filterAcao.value) {
                filters.acao = filterAcao.value;
            }
            
            if (filterTabela && filterTabela.value) {
                filters.tabela = filterTabela.value;
            }
            
            if (filterData && filterData.value) {
                filters.data_inicio = filterData.value;
                filters.data_fim = filterData.value;
            }

            currentPage = 1;
            carregarRegistrosAuditoria(1, filters);
        }

        // Configurar eventos globais
        function configurarEventos() {
            // Pausar auto-update quando modal estiver aberto
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', () => autoUpdater.pause());
                modal.addEventListener('hidden.bs.modal', () => autoUpdater.resume());
            });
            
            // Pausar quando página não estiver visível
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    autoUpdater.pause();
                } else {
                    autoUpdater.resume();
                }
            });

            // Atalhos de teclado
            document.addEventListener('keydown', function(e) {
                // ESC para fechar modais
                if (e.key === 'Escape') {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        bootstrap.Modal.getInstance(modal).hide();
                    });
                }
                
                // Ctrl+R para atualizar
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    cache.clear();
                    carregarRegistrosAuditoria();
                }
            });
        }

        // Inicializar gráficos
        function initializeCharts() {
            const ctxAcoes = document.getElementById('chartAcoesPorDia');
            const ctxTipos = document.getElementById('chartTiposAcao');
            
            if (ctxAcoes) {
                chartAcoes = new Chart(ctxAcoes.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Ações por Dia',
                            data: [],
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            if (ctxTipos) {
                chartTipos = new Chart(ctxTipos.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            backgroundColor: [
                                '#3b82f6', '#10b981', '#f59e0b', 
                                '#ef4444', '#8b5cf6', '#06b6d4'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        }

        // ===== FUNÇÕES DE INTERAÇÃO =====

        // Nova função para abrir funcionários (só para usuários da presidência)
        function abrirFuncionarios() {
            notifications.show('Redirecionando para página de funcionários...', 'info');
            // Redirecionar para a página de funcionários
            window.location.href = './funcionarios.php';
        }

        // Mostrar detalhes - VERSÃO CORRIGIDA COM FILTRO DEPARTAMENTAL
        async function mostrarDetalhes(auditId) {
            console.log('🔍 Tentando mostrar detalhes para ID:', auditId);
            
            try {
                // Construir URL com verificação e filtros departamentais
                const params = new URLSearchParams({
                    id: auditId,
                    ...obterParametrosDepartamentais()
                });
                
                const apiUrl = `../api/auditoria/detalhes.php?${params}`;
                console.log('📡 URL da API:', apiUrl);
                
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                console.log('📥 Response status:', response.status);
                console.log('📥 Response headers:', response.headers);
                
                // Verificar se a resposta é válida
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Verificar se o conteúdo é JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.warn('⚠️ Resposta não é JSON:', contentType);
                    
                    // Ler como texto para ver o que está sendo retornado
                    const textResponse = await response.text();
                    console.log('📄 Resposta em texto:', textResponse.substring(0, 500));
                    
                    throw new Error('API não retornou JSON válido. Verifique se o arquivo ../api/auditoria/detalhes.php existe e está funcionando.');
                }
                
                const data = await response.json();
                console.log('📊 Dados recebidos:', data);
                
                if (data.status === 'success' && data.data) {
                    const modalBody = document.getElementById('detalhesModalBody');
                    const registro = data.data;
                    
                    modalBody.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Informações Básicas</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>ID:</strong></td><td>${registro.id}</td></tr>
                                    <tr><td><strong>Data/Hora:</strong></td><td>${formatarData(registro.data_hora)}</td></tr>
                                    <tr><td><strong>Funcionário:</strong></td><td>${registro.funcionario_nome || 'Sistema'}</td></tr>
                                    <tr><td><strong>Ação:</strong></td><td><span class="action-badge ${registro.acao}">${registro.acao}</span></td></tr>
                                    <tr><td><strong>Tabela:</strong></td><td>${registro.tabela}</td></tr>
                                    <tr><td><strong>Registro ID:</strong></td><td>${registro.registro_id || '-'}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Informações Técnicas</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>IP Origem:</strong></td><td>${registro.ip_origem || '-'}</td></tr>
                                    <tr><td><strong>Navegador:</strong></td><td>${registro.browser_info || '-'}</td></tr>
                                    <tr><td><strong>Sessão ID:</strong></td><td>${registro.sessao_id || '-'}</td></tr>
                                    ${!isPresidencia && registro.funcionario_departamento ? 
                                        `<tr><td><strong>Departamento:</strong></td><td>${registro.funcionario_departamento}</td></tr>` : ''}
                                </table>
                            </div>
                        </div>
                        
                        ${registro.alteracoes ? `
                            <div class="mt-3">
                                <h6>Alterações</h6>
                                <pre class="bg-light p-3 rounded" style="font-size: 0.8rem; max-height: 200px; overflow-y: auto;">${JSON.stringify(JSON.parse(registro.alteracoes), null, 2)}</pre>
                            </div>
                        ` : ''}
                        
                        ${!isPresidencia ? `
                            <div class="mt-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Filtro Departamental Ativo:</strong> Visualizando apenas registros relacionados ao departamento ${departamentoUsuario}.
                                </div>
                            </div>
                        ` : ''}
                    `;
                    
                    new bootstrap.Modal(document.getElementById('detalhesModal')).show();
                } else {
                    throw new Error(data.message || 'Resposta inválida da API');
                }
                
            } catch (error) {
                console.error('❌ Erro ao carregar detalhes:', error);
                
                // Mostrar detalhes simulados como fallback
                console.log('🔄 Mostrando detalhes simulados como fallback');
                mostrarDetalhesSimulados(auditId);
                
                notifications.show('API de detalhes indisponível. Mostrando dados simulados.', 'warning');
            }
        }

        // Função de fallback para mostrar detalhes simulados
        function mostrarDetalhesSimulados(auditId) {
            const modalBody = document.getElementById('detalhesModalBody');
            
            // Encontrar registro na lista carregada
            const registro = registrosAuditoria.find(r => r.id == auditId);
            
            if (registro) {
                modalBody.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Dados básicos do registro (API de detalhes indisponível)</strong>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informações Básicas</h6>
                            <table class="table table-sm">
                                <tr><td><strong>ID:</strong></td><td>${registro.id}</td></tr>
                                <tr><td><strong>Data/Hora:</strong></td><td>${formatarData(registro.data_hora)}</td></tr>
                                <tr><td><strong>Funcionário:</strong></td><td>${registro.funcionario_nome || 'Sistema'}</td></tr>
                                <tr><td><strong>Ação:</strong></td><td><span class="action-badge ${registro.acao}">${registro.acao}</span></td></tr>
                                <tr><td><strong>Tabela:</strong></td><td>${registro.tabela}</td></tr>
                                <tr><td><strong>Registro ID:</strong></td><td>${registro.registro_id || '-'}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Informações Técnicas</h6>
                            <table class="table table-sm">
                                <tr><td><strong>IP Origem:</strong></td><td>${registro.ip_origem || '-'}</td></tr>
                                <tr><td><strong>Navegador:</strong></td><td>N/A (detalhes completos indisponíveis)</td></tr>
                                <tr><td><strong>Sessão ID:</strong></td><td>N/A (detalhes completos indisponíveis)</td></tr>
                                ${!isPresidencia ? 
                                    `<tr><td><strong>Filtro Departamental:</strong></td><td>Ativo (Dept: ${departamentoUsuario})</td></tr>` : ''}
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="alert alert-warning">
                            <i class="fas fa-tools"></i>
                            <strong>Para ver detalhes completos:</strong><br>
                            • Verifique se o arquivo <code>../api/auditoria/detalhes.php</code> existe<br>
                            • Confirme as permissões de acesso à API<br>
                            • Verifique os logs do servidor para mais informações<br>
                            ${!isPresidencia ? '• Lembre-se: você visualiza apenas registros do seu departamento' : ''}
                        </div>
                    </div>
                `;
            } else {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Erro:</strong> Registro não encontrado na lista atual.
                    </div>
                `;
            }
            
            new bootstrap.Modal(document.getElementById('detalhesModal')).show();
        }

        // Atualizar paginação
        function atualizarPaginacao(paginacao) {
            currentPage = paginacao.pagina_atual;
            totalPages = paginacao.total_paginas;
            
            document.getElementById('paginaAtual').textContent = paginacao.pagina_atual;
            document.getElementById('totalPaginas').textContent = paginacao.total_paginas;
            document.getElementById('totalRegistrosPagina').textContent = paginacao.total_registros;

            // Gerar navegação de páginas
            const nav = document.getElementById('paginationNav');
            nav.innerHTML = '';

            // Botão anterior
            if (currentPage > 1) {
                nav.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="irParaPagina(${currentPage - 1})">Anterior</a>
                    </li>
                `;
            }

            // Páginas
            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                nav.innerHTML += `
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="irParaPagina(${i})">${i}</a>
                    </li>
                `;
            }

            // Botão próximo
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
                
                // Obter filtros atuais
                const filters = {};
                const searchInput = document.getElementById('searchInput');
                const filterAcao = document.getElementById('filterAcao');
                const filterTabela = document.getElementById('filterTabela');
                const filterData = document.getElementById('filterData');
                
                if (searchInput && searchInput.value) filters.search = searchInput.value;
                if (filterAcao && filterAcao.value) filters.acao = filterAcao.value;
                if (filterTabela && filterTabela.value) filters.tabela = filterTabela.value;
                if (filterData && filterData.value) {
                    filters.data_inicio = filterData.value;
                    filters.data_fim = filterData.value;
                }
                
                carregarRegistrosAuditoria(page, filters);
            }
        }

        // ===== AÇÕES RÁPIDAS =====

        function abrirRelatorios() {
            // Verificar se o modal já existe, se não, criar
            let modal = document.getElementById('relatoriosModal');
            if (!modal) {
                // Criar o modal se não existir
                modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.id = 'relatoriosModal';
                modal.innerHTML = `
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-chart-line text-primary"></i>
                                    Relatórios de Auditoria${!isPresidencia ? ` - Departamento ${departamentoUsuario}` : ''}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${!isPresidencia ? `
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Filtro Departamental Ativo:</strong> Os relatórios mostrarão apenas dados relacionados ao departamento ${departamentoUsuario}.
                                    </div>
                                ` : ''}
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6>Filtros de Relatório</h6>
                                        <div class="row">
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Tipo:</label>
                                                <select class="form-select" id="relatorioTipo">
                                                    <option value="geral">Relatório Geral</option>
                                                    <option value="por_funcionario">Por Funcionário</option>
                                                    <option value="por_acao">Por Tipo de Ação</option>
                                                    <option value="seguranca">Segurança</option>
                                                    <option value="performance">Performance</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">Período:</label>
                                                <select class="form-select" id="relatorioPeriodo">
                                                    <option value="hoje">Hoje</option>
                                                    <option value="semana">Esta Semana</option>
                                                    <option value="mes" selected>Este Mês</option>
                                                    <option value="trimestre">Trimestre</option>
                                                    <option value="ano">Este Ano</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">&nbsp;</label>
                                                <button class="btn btn-primary d-block" onclick="gerarRelatorio()">
                                                    <i class="fas fa-chart-bar"></i>
                                                    Gerar Relatório
                                                </button>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label">&nbsp;</label>
                                                <button class="btn btn-success d-block" onclick="exportarRelatorio()">
                                                    <i class="fas fa-download"></i>
                                                    Exportar CSV
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row" id="graficosContainer">
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-chart-area text-info"></i>
                                                    Ações por Período
                                                </h6>
                                                <div class="chart-container">
                                                    <canvas id="chartAcoesPorDia" height="300"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-chart-pie text-warning"></i>
                                                    Tipos de Ação
                                                </h6>
                                                <div class="chart-container">
                                                    <canvas id="chartTiposAcao" height="300"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row" id="relatorioResultado" style="display: none;">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <i class="fas fa-file-alt text-success"></i>
                                                    Resultado do Relatório
                                                </h6>
                                                <div id="relatorioConteudo">
                                                    <!-- Conteúdo será inserido aqui -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
            
            // Mostrar modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Carregar dados dos gráficos quando o modal for mostrado
            modal.addEventListener('shown.bs.modal', () => {
                carregarDadosGraficos();
            }, { once: true });
        }

        async function verEstatisticas() {
            try {
                const mensagem = isPresidencia 
                    ? 'Carregando estatísticas detalhadas de todo o sistema...'
                    : `Carregando estatísticas detalhadas do departamento ${departamentoUsuario}...`;
                
                notifications.show(mensagem, 'info');
                
                // Carregar estatísticas detalhadas com filtros departamentais
                const params = new URLSearchParams(obterParametrosDepartamentais());
                const response = await fetch(`../api/auditoria/estatisticas.php?${params}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    // Criar modal com estatísticas detalhadas
                    mostrarModalEstatisticas(data.data);
                } else {
                    throw new Error(data.message || 'Erro ao carregar estatísticas');
                }
                
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
                notifications.show('Problema ao carregar API. Mostrando estatísticas básicas.', 'warning');
                
                // Fallback: mostrar estatísticas básicas da página
                mostrarEstatisticasBasicas();
            }
        }

        function mostrarEstatisticasBasicas() {
            // Rolar suavemente para a seção de estatísticas
            const statsGrid = document.querySelector('.stats-grid');
            if (statsGrid) {
                statsGrid.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                
                // Adicionar um destaque temporário aos cards
                const cards = statsGrid.querySelectorAll('.stat-card');
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.transform = 'scale(1.05)';
                        card.style.boxShadow = '0 8px 30px rgba(37, 99, 235, 0.3)';
                        card.style.transition = 'all 0.3s ease';
                        
                        setTimeout(() => {
                            card.style.transform = '';
                            card.style.boxShadow = '';
                        }, 1000);
                    }, index * 100);
                });
                
                // Mostrar informações adicionais em um toast
                setTimeout(() => {
                    const dica = isPresidencia 
                        ? '💡 Dica: Use "Relatórios" para ver estatísticas mais detalhadas de todo o sistema'
                        : `💡 Dica: Use "Relatórios" para ver estatísticas detalhadas do departamento ${departamentoUsuario}`;
                    notifications.show(dica, 'info', 3000);
                }, 1500);
            } else {
                // Se não encontrar a seção, mostrar modal simples
                mostrarModalEstatisticasSimples();
            }
        }

        function mostrarModalEstatisticasSimples() {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-chart-bar text-primary"></i>
                                Estatísticas do Sistema${!isPresidencia ? ` - Departamento ${departamentoUsuario}` : ''}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${!isPresidencia ? `
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Filtro Departamental Ativo:</strong><br>
                                    Visualizando apenas dados relacionados ao departamento ${departamentoUsuario}.
                                </div>
                            ` : ''}
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Sistema de Auditoria Ativo</strong><br>
                                ${isPresidencia 
                                    ? 'O sistema está monitorando todas as atividades. Use a seção "Relatórios" para análises detalhadas.' 
                                    : `O sistema está monitorando atividades do departamento ${departamentoUsuario}. Use a seção "Relatórios" para análises detalhadas.`
                                }
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-cogs text-primary"></i> Status do Sistema</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success"></i> Auditoria Ativa</li>
                                        <li><i class="fas fa-check text-success"></i> Monitoramento em Tempo Real</li>
                                        <li><i class="fas fa-check text-success"></i> Logs de Segurança</li>
                                        <li><i class="fas fa-check text-success"></i> Relatórios Disponíveis</li>
                                        ${!isPresidencia ? '<li><i class="fas fa-check text-warning"></i> Filtro Departamental Ativo</li>' : ''}
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-tools text-warning"></i> Funcionalidades</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-eye text-info"></i> Visualização de Registros</li>
                                        <li><i class="fas fa-filter text-info"></i> Filtros Avançados</li>
                                        <li><i class="fas fa-download text-info"></i> Exportação de Dados</li>
                                        <li><i class="fas fa-chart-line text-info"></i> Relatórios Detalhados</li>
                                        ${!isPresidencia ? '<li><i class="fas fa-building text-info"></i> Visão Departamental</li>' : ''}
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <h6><i class="fas fa-lightbulb text-warning"></i> Dicas de Uso</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            • Use os filtros para encontrar registros específicos<br>
                                            • Clique em "Ver Detalhes" para informações completas<br>
                                            • Exporte dados para análise externa
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            • Monitore alertas de segurança regularmente<br>
                                            • Gere relatórios periódicos para auditoria<br>
                                            • Use Ctrl+R para atualizar a lista<br>
                                            ${!isPresidencia ? `• Lembre-se: você vê apenas dados do dept. ${departamentoUsuario}` : ''}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="abrirRelatorios(); this.closest('.modal').querySelector('.btn-close').click();">
                                <i class="fas fa-chart-line"></i>
                                Ver Relatórios
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        function mostrarModalEstatisticas(stats) {
            // Criar modal dinamicamente
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'estatisticasModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-chart-bar text-primary"></i>
                                Estatísticas Detalhadas${!isPresidencia ? ` - Departamento ${departamentoUsuario}` : ' do Sistema'}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${!isPresidencia ? `
                                <div class="alert alert-warning mb-4">
                                    <i class="fas fa-building"></i>
                                    <strong>Filtro Departamental Ativo:</strong> Todas as estatísticas mostradas são específicas do departamento ${departamentoUsuario}.
                                </div>
                            ` : ''}
                            
                            <div class="row">
                                <!-- Estatísticas Principais -->
                                <div class="col-md-6 mb-4">
                                    <h6><i class="fas fa-database text-primary"></i> Estatísticas Gerais${!isPresidencia ? ` (Dept. ${departamentoUsuario})` : ''}</h6>
                                    <table class="table table-sm">
                                        <tr><td><strong>Total de Registros:</strong></td><td>${formatNumber(stats.total_registros || 0)}</td></tr>
                                        <tr><td><strong>Ações Hoje:</strong></td><td>${formatNumber(stats.acoes_hoje || 0)}</td></tr>
                                        <tr><td><strong>Mudança vs Ontem:</strong></td><td>
                                            <span class="badge bg-${(stats.mudanca_hoje || 0) >= 0 ? 'success' : 'danger'}">
                                                ${(stats.mudanca_hoje || 0) >= 0 ? '+' : ''}${stats.mudanca_hoje || 0}%
                                            </span>
                                        </td></tr>
                                        <tr><td><strong>Usuários Ativos (24h):</strong></td><td>${formatNumber(stats.usuarios_ativos || 0)}</td></tr>
                                        <tr><td><strong>Alertas de Segurança:</strong></td><td>
                                            <span class="badge bg-${(stats.alertas || 0) > 0 ? 'warning' : 'success'}">
                                                ${formatNumber(stats.alertas || 0)}
                                            </span>
                                        </td></tr>
                                    </table>
                                </div>
                                
                                <!-- Informações Adicionais -->
                                <div class="col-md-6 mb-4">
                                    <h6><i class="fas fa-info-circle text-info"></i> Informações do Sistema</h6>
                                    <table class="table table-sm">
                                        <tr><td><strong>Tabela Mais Ativa:</strong></td><td>${stats.tabela_mais_ativa || 'N/A'}</td></tr>
                                        <tr><td><strong>Horário de Pico:</strong></td><td>${stats.horario_pico || 'N/A'}</td></tr>
                                        <tr><td><strong>Última Atualização:</strong></td><td>${stats.ultima_atualizacao ? formatarData(stats.ultima_atualizacao) : 'N/A'}</td></tr>
                                        <tr><td><strong>Tempo de Resposta:</strong></td><td>${stats.tempo_resposta ? (stats.tempo_resposta * 1000).toFixed(2) + 'ms' : 'N/A'}</td></tr>
                                        ${!isPresidencia ? `<tr><td><strong>Escopo:</strong></td><td>Departamento ${departamentoUsuario}</td></tr>` : ''}
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Gráficos -->
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <h6><i class="fas fa-chart-line text-success"></i> Ações por Período (7 dias)</h6>
                                    <div class="chart-container">
                                        <canvas id="modalChartPeriodo" height="300"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <h6><i class="fas fa-chart-pie text-warning"></i> Tipos de Ação (30 dias)</h6>
                                    <div class="chart-container">
                                        <canvas id="modalChartTipos" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detalhes dos Dados -->
                            <div class="row">
                                <div class="col-12">
                                    <h6><i class="fas fa-list text-secondary"></i> Detalhes dos Dados</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Ações por Dia:</strong>
                                            <ul class="list-unstyled mt-2">
                                                ${stats.acoes_periodo && stats.acoes_periodo.labels ? 
                                                    stats.acoes_periodo.labels.map((label, index) => 
                                                        `<li><span class="badge bg-light text-dark me-2">${label}</span> ${stats.acoes_periodo.data[index] || 0} ações</li>`
                                                    ).join('') : 
                                                    '<li class="text-muted">Nenhum dado disponível</li>'
                                                }
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Tipos de Ação:</strong>
                                            <ul class="list-unstyled mt-2">
                                                ${stats.tipos_acao && stats.tipos_acao.labels ? 
                                                    stats.tipos_acao.labels.map((label, index) => 
                                                        `<li><span class="badge bg-primary me-2">${label}</span> ${stats.tipos_acao.data[index] || 0} vezes</li>`
                                                    ).join('') : 
                                                    '<li class="text-muted">Nenhum dado disponível</li>'
                                                }
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success" onclick="exportarEstatisticas()">
                                <i class="fas fa-download"></i>
                                Exportar Estatísticas
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Adicionar modal ao DOM
            document.body.appendChild(modal);
            
            // Mostrar modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Remover modal quando fechado
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
            
            // Criar gráficos após o modal ser mostrado
            modal.addEventListener('shown.bs.modal', () => {
                criarGraficosModal(stats);
            });
        }

        function criarGraficosModal(stats) {
            // Gráfico de período
            const ctxPeriodo = document.getElementById('modalChartPeriodo');
            if (ctxPeriodo) {
                new Chart(ctxPeriodo.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: stats.acoes_periodo?.labels || [],
                        datasets: [{
                            label: 'Ações por Dia',
                            data: stats.acoes_periodo?.data || [],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
            
            // Gráfico de tipos
            const ctxTipos = document.getElementById('modalChartTipos');
            if (ctxTipos) {
                new Chart(ctxTipos.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: stats.tipos_acao?.labels || [],
                        datasets: [{
                            data: stats.tipos_acao?.data || [],
                            backgroundColor: [
                                '#3b82f6', '#10b981', '#f59e0b', 
                                '#ef4444', '#8b5cf6', '#06b6d4'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        }

        function exportarEstatisticas() {
            const mensagem = isPresidencia 
                ? 'Funcionalidade de exportação de estatísticas em desenvolvimento'
                : `Funcionalidade de exportação de estatísticas departamentais em desenvolvimento`;
            notifications.show(mensagem, 'info');
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('pt-BR').format(num);
        }

        async function exportarDados() {
            try {
                const mensagem = isPresidencia 
                    ? 'Iniciando exportação de todos os dados...'
                    : `Iniciando exportação dos dados do departamento ${departamentoUsuario}...`;
                
                notifications.show(mensagem, 'info');
                
                // Obter filtros atuais
                const filters = {};
                const searchInput = document.getElementById('searchInput');
                const filterAcao = document.getElementById('filterAcao');
                const filterTabela = document.getElementById('filterTabela');
                const filterData = document.getElementById('filterData');
                
                if (searchInput && searchInput.value) filters.search = searchInput.value;
                if (filterAcao && filterAcao.value) filters.acao = filterAcao.value;
                if (filterTabela && filterTabela.value) filters.tabela = filterTabela.value;
                if (filterData && filterData.value) {
                    filters.data_inicio = filterData.value;
                    filters.data_fim = filterData.value;
                }
                
                // Adicionar filtros departamentais
                const allFilters = {
                    ...filters,
                    ...obterParametrosDepartamentais()
                };
                
                const params = new URLSearchParams(allFilters);
                const response = await fetch(`../api/auditoria/exportar.php?${params}`);
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    
                    const sufixo = isPresidencia ? 'completo' : `dept_${departamentoUsuario}`;
                    a.download = `auditoria_${sufixo}_${new Date().toISOString().split('T')[0]}.csv`;
                    
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    const sucessoMsg = isPresidencia 
                        ? 'Dados exportados com sucesso!'
                        : `Dados do departamento ${departamentoUsuario} exportados com sucesso!`;
                    
                    notifications.show(sucessoMsg, 'success');
                } else {
                    throw new Error('Erro ao exportar dados');
                }
            } catch (error) {
                console.error('Erro ao exportar:', error);
                notifications.show('Erro ao exportar dados: ' + error.message, 'error');
            }
        }

        function configurarAuditoria() {
            // Criar modal de configuração
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-cog text-primary"></i>
                                Configurações de Auditoria${!isPresidencia ? ` - Departamento ${departamentoUsuario}` : ''}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${!isPresidencia ? `
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Filtro Departamental:</strong> Suas configurações se aplicam apenas aos dados do departamento ${departamentoUsuario}.
                                </div>
                            ` : ''}
                            
                            <!-- Configurações de Visualização -->
                            <div class="mb-4">
                                <h6><i class="fas fa-eye text-info"></i> Configurações de Visualização</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="autoUpdate" checked>
                                            <label class="form-check-label" for="autoUpdate">
                                                Auto-atualização (30s)
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="showNotifications" checked>
                                            <label class="form-check-label" for="showNotifications">
                                                Mostrar notificações
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="compactView">
                                            <label class="form-check-label" for="compactView">
                                                Visualização compacta
                                            </label>
                                        </div>
                                        ${!isPresidencia ? `
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="showDeptInfo" checked>
                                                <label class="form-check-label" for="showDeptInfo">
                                                    Mostrar informações departamentais
                                                </label>
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Registros por página:</label>
                                        <select class="form-select" id="recordsPerPage">
                                            <option value="10">10 registros</option>
                                            <option value="20" selected>20 registros</option>
                                            <option value="50">50 registros</option>
                                            <option value="100">100 registros</option>
                                        </select>
                                        
                                        <label class="form-label mt-2">Tema:</label>
                                        <select class="form-select" id="themeSelect">
                                            <option value="light" selected>Claro</option>
                                            <option value="dark">Escuro</option>
                                            <option value="auto">Automático</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configurações de Filtros -->
                            <div class="mb-4">
                                <h6><i class="fas fa-filter text-warning"></i> Filtros Padrão</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Período padrão:</label>
                                        <select class="form-select" id="defaultPeriod">
                                            <option value="">Todos os registros</option>
                                            <option value="hoje">Hoje</option>
                                            <option value="semana">Esta semana</option>
                                            <option value="mes" selected>Este mês</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ação padrão:</label>
                                        <select class="form-select" id="defaultAction">
                                            <option value="" selected>Todas as ações</option>
                                            <option value="LOGIN">Apenas Logins</option>
                                            <option value="UPDATE">Apenas Atualizações</option>
                                            <option value="INSERT">Apenas Inserções</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configurações de Exportação -->
                            <div class="mb-4">
                                <h6><i class="fas fa-download text-success"></i> Configurações de Exportação</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="includeDetails" checked>
                                            <label class="form-check-label" for="includeDetails">
                                                Incluir detalhes das alterações
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="includeTimestamp" checked>
                                            <label class="form-check-label" for="includeTimestamp">
                                                Incluir timestamp completo
                                            </label>
                                        </div>
                                        ${!isPresidencia ? `
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="includeDeptFilter" checked disabled>
                                                <label class="form-check-label" for="includeDeptFilter">
                                                    Aplicar filtro departamental (sempre ativo)
                                                </label>
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Formato padrão:</label>
                                        <select class="form-select" id="exportFormat">
                                            <option value="csv" selected>CSV</option>
                                            <option value="excel">Excel</option>
                                            <option value="json">JSON</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Configurações Avançadas -->
                            <div class="mb-4">
                                <h6><i class="fas fa-tools text-danger"></i> Configurações Avançadas</h6>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Atenção:</strong> Estas configurações afetam o desempenho do sistema.
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enableCache" checked>
                                            <label class="form-check-label" for="enableCache">
                                                Habilitar cache
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="debugMode">
                                            <label class="form-check-label" for="debugMode">
                                                Modo debug (logs detalhados)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tempo de cache (minutos):</label>
                                        <input type="number" class="form-control" id="cacheTime" value="5" min="1" max="60">
                                        
                                        <label class="form-label mt-2">Intervalo de atualização (segundos):</label>
                                        <input type="number" class="form-control" id="updateInterval" value="30" min="10" max="300">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informações do Sistema -->
                            <div class="mb-4">
                                <h6><i class="fas fa-info-circle text-secondary"></i> Informações do Sistema</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <strong>Versão:</strong> 1.0.0<br>
                                            <strong>Última atualização:</strong> ${new Date().toLocaleDateString('pt-BR')}<br>
                                            <strong>Cache ativo:</strong> ${cache.cache.size} itens
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <strong>Auto-update:</strong> ${autoUpdater.isActive ? 'Ativo' : 'Inativo'}<br>
                                            <strong>Registros carregados:</strong> ${registrosAuditoria.length}<br>
                                            <strong>Permissão:</strong> ${temPermissao ? 'Concedida' : 'Negada'}<br>
                                            ${!isPresidencia ? `<strong>Departamento:</strong> ${departamentoUsuario}` : '<strong>Escopo:</strong> Sistema completo'}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" onclick="limparCache()">
                                <i class="fas fa-trash"></i>
                                Limpar Cache
                            </button>
                            <button type="button" class="btn btn-warning" onclick="resetarConfiguracoes()">
                                <i class="fas fa-undo"></i>
                                Resetar
                            </button>
                            <button type="button" class="btn btn-success" onclick="salvarConfiguracoes()">
                                <i class="fas fa-save"></i>
                                Salvar
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Carregar configurações salvas
            carregarConfiguracoesModal();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        function carregarConfiguracoesModal() {
            // Carregar configurações do localStorage
            const configs = JSON.parse(localStorage.getItem('auditoriaConfigs') || '{}');
            
            if (configs.autoUpdate !== undefined) {
                document.getElementById('autoUpdate').checked = configs.autoUpdate;
            }
            if (configs.showNotifications !== undefined) {
                document.getElementById('showNotifications').checked = configs.showNotifications;
            }
            if (configs.recordsPerPage) {
                document.getElementById('recordsPerPage').value = configs.recordsPerPage;
            }
            if (configs.defaultPeriod) {
                document.getElementById('defaultPeriod').value = configs.defaultPeriod;
            }
            if (configs.cacheTime) {
                document.getElementById('cacheTime').value = configs.cacheTime;
            }
            if (configs.updateInterval) {
                document.getElementById('updateInterval').value = configs.updateInterval;
            }
        }

        function salvarConfiguracoes() {
            const configuracoes = {
                autoUpdate: document.getElementById('autoUpdate').checked,
                showNotifications: document.getElementById('showNotifications').checked,
                compactView: document.getElementById('compactView').checked,
                recordsPerPage: document.getElementById('recordsPerPage').value,
                themeSelect: document.getElementById('themeSelect').value,
                defaultPeriod: document.getElementById('defaultPeriod').value,
                defaultAction: document.getElementById('defaultAction').value,
                includeDetails: document.getElementById('includeDetails').checked,
                includeTimestamp: document.getElementById('includeTimestamp').checked,
                exportFormat: document.getElementById('exportFormat').value,
                enableCache: document.getElementById('enableCache').checked,
                debugMode: document.getElementById('debugMode').checked,
                cacheTime: parseInt(document.getElementById('cacheTime').value),
                updateInterval: parseInt(document.getElementById('updateInterval').value),
                // Configurações específicas para diretores
                showDeptInfo: !isPresidencia ? document.getElementById('showDeptInfo')?.checked : null,
                isPresidencia: isPresidencia,
                departamentoUsuario: departamentoUsuario
            };
            
            // Salvar no localStorage
            localStorage.setItem('auditoriaConfigs', JSON.stringify(configuracoes));
            
            // Aplicar configurações
            aplicarConfiguracoes(configuracoes);
            
            const mensagem = isPresidencia 
                ? 'Configurações salvas com sucesso!'
                : `Configurações departamentais salvas com sucesso!`;
            
            notifications.show(mensagem, 'success');
            
            // Fechar modal
            const modal = document.querySelector('#configurarAuditoria');
            if (modal) {
                bootstrap.Modal.getInstance(modal).hide();
            }
        }

        function aplicarConfiguracoes(configs) {
            // Aplicar auto-update
            if (configs.autoUpdate) {
                autoUpdater.resume();
            } else {
                autoUpdater.pause();
            }
            
            // Aplicar intervalo de atualização
            if (configs.updateInterval && configs.updateInterval !== autoUpdater.interval) {
                autoUpdater.interval = configs.updateInterval * 1000;
                autoUpdater.stop();
                if (configs.autoUpdate) {
                    autoUpdater.start();
                }
            }
            
            // Aplicar cache
            if (!configs.enableCache) {
                cache.clear();
            }
            
            // Aplicar tema
            if (configs.themeSelect === 'dark') {
                document.body.classList.add('dark-theme');
            } else {
                document.body.classList.remove('dark-theme');
            }
            
            // Aplicar visualização compacta
            if (configs.compactView) {
                document.body.classList.add('compact-view');
            } else {
                document.body.classList.remove('compact-view');
            }
            
            // Aplicar configurações específicas de departamento
            if (!isPresidencia && configs.showDeptInfo === false) {
                document.body.classList.add('hide-dept-info');
            } else {
                document.body.classList.remove('hide-dept-info');
            }
            
            console.log('Configurações aplicadas (Departamental):', configs);
        }

        function resetarConfiguracoes() {
            if (confirm('Tem certeza que deseja resetar todas as configurações?')) {
                localStorage.removeItem('auditoriaConfigs');
                
                // Resetar valores padrão no formulário
                document.getElementById('autoUpdate').checked = true;
                document.getElementById('showNotifications').checked = true;
                document.getElementById('compactView').checked = false;
                document.getElementById('recordsPerPage').value = '20';
                document.getElementById('themeSelect').value = 'light';
                document.getElementById('defaultPeriod').value = 'mes';
                document.getElementById('defaultAction').value = '';
                document.getElementById('includeDetails').checked = true;
                document.getElementById('includeTimestamp').checked = true;
                document.getElementById('exportFormat').value = 'csv';
                document.getElementById('enableCache').checked = true;
                document.getElementById('debugMode').checked = false;
                document.getElementById('cacheTime').value = '5';
                document.getElementById('updateInterval').value = '30';
                
                if (!isPresidencia && document.getElementById('showDeptInfo')) {
                    document.getElementById('showDeptInfo').checked = true;
                }
                
                notifications.show('Configurações resetadas!', 'info');
            }
        }

        function limparCache() {
            cache.clear();
            notifications.show('Cache limpo com sucesso!', 'success');
            
            // Recarregar dados
            carregarRegistrosAuditoria();
        }

        function atualizarRegistros() {
            cache.clear();
            carregarRegistrosAuditoria();
            
            const mensagem = isPresidencia 
                ? 'Registros atualizados!'
                : `Registros do departamento ${departamentoUsuario} atualizados!`;
            
            notifications.show(mensagem, 'success');
        }

        async function carregarDadosGraficos() {
            try {
                // Adicionar filtros departamentais para gráficos
                const params = new URLSearchParams(obterParametrosDepartamentais());
                const response = await fetch(`../api/auditoria/estatisticas.php?${params}`);
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    // Atualizar gráfico de ações por período
                    if (data.data.acoes_periodo && chartAcoes) {
                        chartAcoes.data.labels = data.data.acoes_periodo.labels;
                        chartAcoes.data.datasets[0].data = data.data.acoes_periodo.data;
                        chartAcoes.update();
                    }

                    // Atualizar gráfico de tipos de ação
                    if (data.data.tipos_acao && chartTipos) {
                        chartTipos.data.labels = data.data.tipos_acao.labels;
                        chartTipos.data.datasets[0].data = data.data.tipos_acao.data;
                        chartTipos.update();
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar dados dos gráficos:', error);
            }
        }

        async function gerarRelatorio() {
            const tipo = document.getElementById('relatorioTipo').value;
            const periodo = document.getElementById('relatorioPeriodo').value;
            
            try {
                const mensagem = isPresidencia 
                    ? 'Gerando relatório completo...'
                    : `Gerando relatório do departamento ${departamentoUsuario}...`;
                
                notifications.show(mensagem, 'info');
                
                // Adicionar filtros departamentais
                const filtrosDepartamentais = obterParametrosDepartamentais();
                
                const response = await fetch('../api/auditoria/relatorios.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        tipo: tipo,
                        periodo: periodo,
                        ...filtrosDepartamentais
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    const sucessoMsg = isPresidencia 
                        ? 'Relatório gerado com sucesso!'
                        : `Relatório departamental gerado com sucesso!`;
                    
                    notifications.show(sucessoMsg, 'success');
                    
                    // Mostrar resultado do relatório
                    mostrarResultadoRelatorio(data.data, tipo, periodo);
                } else {
                    throw new Error(data.message || 'Erro ao gerar relatório');
                }
            } catch (error) {
                console.error('Erro ao gerar relatório:', error);
                
                // Mostrar relatório simulado se a API não funcionar
                const warningMsg = isPresidencia 
                    ? 'Mostrando dados simulados (API indisponível)'
                    : `Mostrando dados simulados do departamento ${departamentoUsuario} (API indisponível)`;
                
                notifications.show(warningMsg, 'warning');
                mostrarRelatorioSimulado(tipo, periodo);
            }
        }

        function mostrarResultadoRelatorio(dadosRelatorio, tipo, periodo) {
            const container = document.getElementById('relatorioResultado');
            const conteudo = document.getElementById('relatorioConteudo');
            
            const escopo = isPresidencia ? 'Sistema Completo' : `Departamento ${departamentoUsuario}`;
            
            let html = `
                <div class="mb-3">
                    <h6>Relatório: ${getTipoRelatorioNome(tipo)}</h6>
                    <p class="text-muted">
                        Período: ${getPeriodoNome(periodo)} | 
                        Escopo: ${escopo} | 
                        Gerado em: ${new Date().toLocaleString('pt-BR')}
                    </p>
                </div>
            `;
            
            if (dadosRelatorio.estatisticas) {
                if (tipo === 'geral') {
                    html += gerarHtmlRelatorioGeral(dadosRelatorio.estatisticas);
                } else if (tipo === 'por_funcionario') {
                    html += gerarHtmlRelatorioPorFuncionario(dadosRelatorio.estatisticas);
                } else if (tipo === 'por_acao') {
                    html += gerarHtmlRelatorioPorAcao(dadosRelatorio.estatisticas);
                } else if (tipo === 'seguranca') {
                    html += gerarHtmlRelatorioSeguranca(dadosRelatorio.estatisticas);
                } else {
                    html += `<pre>${JSON.stringify(dadosRelatorio, null, 2)}</pre>`;
                }
            } else {
                html += `<p class="text-muted">Nenhum dado disponível para este relatório no escopo ${escopo.toLowerCase()}.</p>`;
            }
            
            conteudo.innerHTML = html;
            container.style.display = 'block';
            
            // Rolar até o resultado
            container.scrollIntoView({ behavior: 'smooth' });
        }

        function mostrarRelatorioSimulado(tipo, periodo) {
            const dadosSimulados = {
                estatisticas: gerarDadosSimulados(tipo, periodo)
            };
            mostrarResultadoRelatorio(dadosSimulados, tipo, periodo);
        }

        function gerarDadosSimulados(tipo, periodo) {
            // Dados simulados baseados no tipo e se é presidência ou departamental
            const multiplicador = isPresidencia ? 1 : 0.3; // Departamentos têm menos dados
            
            if (tipo === 'geral') {
                return {
                    total_acoes: Math.floor((Math.random() * 1000 + 500) * multiplicador),
                    funcionarios_ativos: Math.floor((Math.random() * 20 + 5) * multiplicador),
                    associados_afetados: Math.floor((Math.random() * 100 + 50) * multiplicador),
                    dias_com_atividade: Math.floor((Math.random() * 30 + 15) * multiplicador),
                    ips_unicos: Math.floor((Math.random() * 50 + 10) * multiplicador)
                };
            } else if (tipo === 'por_funcionario') {
                const funcionarios = isPresidencia 
                    ? ['João Silva', 'Maria Santos', 'Pedro Costa', 'Ana Oliveira']
                    : [`Funcionário Dept ${departamentoUsuario} A`, `Funcionário Dept ${departamentoUsuario} B`];
                
                return funcionarios.map(nome => ({
                    nome: nome,
                    total_acoes: Math.floor((Math.random() * 200 + 50) * multiplicador),
                    dias_ativos: Math.floor((Math.random() * 25 + 5) * multiplicador),
                    tabelas_acessadas: Math.floor((Math.random() * 10 + 3) * multiplicador)
                }));
            }
            return {};
        }

        function gerarHtmlRelatorioGeral(stats) {
            return `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td><strong>Total de Ações:</strong></td><td>${formatNumber(stats.total_acoes)}</td></tr>
                            <tr><td><strong>Funcionários Ativos:</strong></td><td>${formatNumber(stats.funcionarios_ativos)}</td></tr>
                            <tr><td><strong>Associados Afetados:</strong></td><td>${formatNumber(stats.associados_afetados)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td><strong>Dias com Atividade:</strong></td><td>${formatNumber(stats.dias_com_atividade)}</td></tr>
                            <tr><td><strong>IPs Únicos:</strong></td><td>${formatNumber(stats.ips_unicos)}</td></tr>
                            ${!isPresidencia ? `<tr><td><strong>Escopo:</strong></td><td>Departamento ${departamentoUsuario}</td></tr>` : ''}
                        </table>
                    </div>
                </div>
            `;
        }

        function gerarHtmlRelatorioPorFuncionario(stats) {
            if (!Array.isArray(stats)) return '<p>Dados inválidos</p>';
            
            return `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Total de Ações</th>
                                <th>Dias Ativos</th>
                                <th>Tabelas Acessadas</th>
                                <th>Produtividade</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${stats.map(func => `
                                <tr>
                                    <td>${func.nome}</td>
                                    <td>${formatNumber(func.total_acoes)}</td>
                                    <td>${formatNumber(func.dias_ativos)}</td>
                                    <td>${formatNumber(func.tabelas_acessadas)}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" style="width: ${Math.min(100, func.total_acoes / 2)}%">
                                                ${(func.total_acoes / Math.max(func.dias_ativos, 1)).toFixed(1)} ações/dia
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function gerarHtmlRelatorioPorAcao(stats) {
            if (!Array.isArray(stats)) return '<p>Dados inválidos</p>';
            
            return `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Tabela</th>
                                <th>Total</th>
                                <th>Funcionários</th>
                                <th>Primeira Vez</th>
                                <th>Última Vez</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${stats.map(acao => `
                                <tr>
                                    <td><span class="action-badge ${acao.acao}">${acao.acao}</span></td>
                                    <td>${acao.tabela}</td>
                                    <td>${formatNumber(acao.total)}</td>
                                    <td>${formatNumber(acao.funcionarios)}</td>
                                    <td>${acao.primeira_vez ? formatarData(acao.primeira_vez) : '-'}</td>
                                    <td>${acao.ultima_vez ? formatarData(acao.ultima_vez) : '-'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function gerarHtmlRelatorioSeguranca(stats) {
            return `
                <div class="alert alert-info">
                    <h6><i class="fas fa-shield-alt"></i> Relatório de Segurança${!isPresidencia ? ` - Departamento ${departamentoUsuario}` : ''}</h6>
                    <p>Análise de eventos de segurança e atividades suspeitas${!isPresidencia ? ' no seu departamento' : ''}.</p>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h5 class="card-title">Tentativas de Login</h5>
                                <h2 class="text-warning">${stats.total_tentativas_falhas || 0}</h2>
                                <small class="text-muted">Falhas detectadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h5 class="card-title">Exclusões</h5>
                                <h2 class="text-danger">${stats.total_exclusoes || 0}</h2>
                                <small class="text-muted">Registros excluídos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h5 class="card-title">IPs Suspeitos</h5>
                                <h2 class="text-info">${stats.total_ips_suspeitos || 0}</h2>
                                <small class="text-muted">Endereços monitorados</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function getTipoRelatorioNome(tipo) {
            const nomes = {
                'geral': 'Relatório Geral',
                'por_funcionario': 'Relatório por Funcionário',
                'por_acao': 'Relatório por Tipo de Ação',
                'seguranca': 'Relatório de Segurança',
                'performance': 'Relatório de Performance'
            };
            return nomes[tipo] || tipo;
        }

        function getPeriodoNome(periodo) {
            const nomes = {
                'hoje': 'Hoje',
                'semana': 'Esta Semana',
                'mes': 'Este Mês',
                'trimestre': 'Trimestre Atual',
                'ano': 'Este Ano'
            };
            return nomes[periodo] || periodo;
        }

        async function exportarRelatorio() {
            try {
                const tipo = document.getElementById('relatorioTipo')?.value || 'geral';
                const periodo = document.getElementById('relatorioPeriodo')?.value || 'mes';
                
                const mensagem = isPresidencia 
                    ? 'Iniciando exportação do relatório completo...'
                    : `Iniciando exportação do relatório departamental...`;
                
                notifications.show(mensagem, 'info');
                
                // Primeiro tentar a API de relatórios com filtros departamentais
                try {
                    const filtrosDepartamentais = obterParametrosDepartamentais();
                    
                    const response = await fetch('../api/auditoria/relatorios.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            tipo: tipo,
                            periodo: periodo,
                            formato: 'csv',
                            ...filtrosDepartamentais
                        })
                    });
                    
                    if (response.ok) {
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        
                        const sufixo = isPresidencia ? 'completo' : `dept_${departamentoUsuario}`;
                        a.download = `relatorio_${tipo}_${periodo}_${sufixo}_${new Date().toISOString().split('T')[0]}.csv`;
                        
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        
                        const sucessoMsg = isPresidencia 
                            ? 'Relatório exportado com sucesso!'
                            : `Relatório departamental exportado com sucesso!`;
                        
                        notifications.show(sucessoMsg, 'success');
                        return;
                    }
                } catch (apiError) {
                    console.log('API de relatórios não disponível, usando exportação geral');
                }
                
                // Fallback: usar a API de exportação geral com filtros departamentais
                const allFilters = {
                    data_inicio: getDataInicioPeriodo(periodo),
                    data_fim: new Date().toISOString().split('T')[0],
                    ...obterParametrosDepartamentais()
                };
                
                const params = new URLSearchParams(allFilters);
                const response = await fetch(`../api/auditoria/exportar.php?${params}`);
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    
                    const sufixo = isPresidencia ? 'completo' : `dept_${departamentoUsuario}`;
                    a.download = `auditoria_${tipo}_${periodo}_${sufixo}_${new Date().toISOString().split('T')[0]}.csv`;
                    
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    const sucessoMsg = isPresidencia 
                        ? 'Dados exportados com sucesso!'
                        : `Dados do departamento ${departamentoUsuario} exportados com sucesso!`;
                    
                    notifications.show(sucessoMsg, 'success');
                } else {
                    throw new Error('Erro ao exportar dados');
                }
                
            } catch (error) {
                console.error('Erro ao exportar relatório:', error);
                notifications.show('Erro ao exportar relatório: ' + error.message, 'error');
            }
        }

        function getDataInicioPeriodo(periodo) {
            const hoje = new Date();
            let dataInicio;
            
            switch (periodo) {
                case 'hoje':
                    dataInicio = hoje;
                    break;
                case 'semana':
                    dataInicio = new Date(hoje.getTime() - (7 * 24 * 60 * 60 * 1000));
                    break;
                case 'mes':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                    break;
                case 'trimestre':
                    const mesAtual = hoje.getMonth();
                    const mesInicioTrimestre = Math.floor(mesAtual / 3) * 3;
                    dataInicio = new Date(hoje.getFullYear(), mesInicioTrimestre, 1);
                    break;
                case 'ano':
                    dataInicio = new Date(hoje.getFullYear(), 0, 1);
                    break;
                default:
                    dataInicio = new Date(hoje.getTime() - (30 * 24 * 60 * 60 * 1000));
            }
            
            return dataInicio.toISOString().split('T')[0];
        }

        // ===== FUNÇÕES AUXILIARES =====

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

        function mostrarErroTabela(mensagem) {
            const tbody = document.getElementById('auditTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h5 class="text-danger">Erro</h5>
                        <p class="text-muted">${mensagem}</p>
                        ${!isPresidencia ? `<p class="text-muted"><small>Filtro departamental: Departamento ${departamentoUsuario}</small></p>` : ''}
                        <button class="btn btn-primary btn-sm mt-2" onclick="carregarRegistrosAuditoria()">
                            <i class="fas fa-redo"></i>
                            Tentar Novamente
                        </button>
                    </td>
                </tr>
            `;
        }

        // Log de inicialização
        console.log('✓ Sistema de Auditoria Departamental carregado com sucesso!');
        console.log(`📊 Escopo: ${isPresidencia ? 'Sistema Completo (Presidência)' : 'Departamento ' + departamentoUsuario}`);
        console.log(`🔐 Permissões: ${temPermissao ? 'Concedidas' : 'Negadas'}`);
    </script>

    <!-- CSS adicional para melhorar a visualização departamental -->
    <style>
        /* Estilos para indicadores departamentais */
        .dept-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .dept-indicator.presidencia {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        /* Estilos para cards com informação departamental */
        .stat-card {
            position: relative;
        }

        .stat-card.departamental::before {
            content: "DEPT";
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(59, 130, 246, 0.8);
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: bold;
        }

        .stat-card.presidencia::before {
            content: "GERAL";
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(245, 158, 11, 0.8);
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.6rem;
            font-weight: bold;
        }

        /* Estilos para modo compacto departamental */
        .compact-view .dept-info {
            display: none;
        }

        .hide-dept-info .dept-info {
            display: none !important;
        }

        /* Estilos para alerts departamentais */
        .alert.departamental {
            border-left: 4px solid #3b82f6;
        }

        .alert.presidencia {
            border-left: 4px solid #f59e0b;
        }

        /* Estilos para tabela com indicadores */
        .audit-table .dept-badge {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        /* Animações para mudança de contexto */
        .context-change {
            transition: all 0.3s ease;
        }

        .context-change.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Responsividade para informações departamentais */
        @media (max-width: 768px) {
            .dept-indicator {
                position: static;
                display: inline-block;
                margin-bottom: 10px;
            }
            
            .page-subtitle {
                font-size: 0.9rem;
            }
        }

        /* Destaque para seção atual baseada no contexto */
        .departamental-section {
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.02);
        }

        .presidencia-section {
            border: 2px solid rgba(245, 158, 11, 0.2);
            border-radius: 8px;
            background: rgba(245, 158, 11, 0.02);
        }
    </style>

    <!-- Script adicional para aplicar classes baseadas no contexto -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Aplicar classes baseadas no contexto do usuário
            const body = document.body;
            const statsCards = document.querySelectorAll('.stat-card');
            const sections = document.querySelectorAll('.documents-section, .quick-actions, .stats-grid');
            
            if (isPresidencia) {
                body.classList.add('presidencia-context');
                statsCards.forEach(card => card.classList.add('presidencia'));
                sections.forEach(section => section.classList.add('presidencia-section'));
            } else if (isDiretor) {
                body.classList.add('departamental-context');
                statsCards.forEach(card => card.classList.add('departamental'));
                sections.forEach(section => section.classList.add('departamental-section'));
            }
            
            // Adicionar indicadores visuais aos elementos relevantes
            const pageHeader = document.querySelector('.page-header');
            if (pageHeader && !isPresidencia) {
                const indicator = document.createElement('div');
                indicator.className = 'dept-indicator';
                indicator.textContent = `Dept. ${departamentoUsuario}`;
                pageHeader.style.position = 'relative';
                pageHeader.appendChild(indicator);
            } else if (pageHeader && isPresidencia) {
                const indicator = document.createElement('div');
                indicator.className = 'dept-indicator presidencia';
                indicator.textContent = 'Sistema Completo';
                pageHeader.style.position = 'relative';
                pageHeader.appendChild(indicator);
            }
        });

        // Função para destacar mudanças de contexto
        function destacarMudancaContexto(elemento, duracao = 2000) {
            if (!elemento) return;
            
            elemento.classList.add('context-change');
            elemento.style.transform = 'scale(1.02)';
            elemento.style.boxShadow = isPresidencia 
                ? '0 4px 20px rgba(245, 158, 11, 0.3)'
                : '0 4px 20px rgba(59, 130, 246, 0.3)';
            
            setTimeout(() => {
                elemento.style.transform = '';
                elemento.style.boxShadow = '';
                elemento.classList.remove('context-change');
            }, duracao);
        }

        // Override das funções de notificação para incluir contexto
        const originalShow = notifications.show;
        notifications.show = function(message, type = 'success', duration = 5000) {
            let contextMessage = message;
            
            // Adicionar contexto às mensagens se não for presidência
            if (!isPresidencia && !message.includes('Departamento') && !message.includes('departamento')) {
                if (type === 'success' && (message.includes('carregado') || message.includes('exportado') || message.includes('atualizado'))) {
                    contextMessage = `${message} (Dept. ${departamentoUsuario})`;
                }
            }
            
            return originalShow.call(this, contextMessage, type, duration);
        };

        function atualizarPercentuaisKPI() {
            const totalRegistros = <?php echo $totalRegistros; ?>;
            const acoesHoje = <?php echo $acoesHoje; ?>;
            const usuariosAtivos = <?php echo $usuariosAtivos; ?>;
            const alertas = <?php echo $alertas; ?>;
            
            // Calculate activity percentage
            const atividadePercent = totalRegistros > 0 ? Math.min(100, Math.round((acoesHoje / totalRegistros) * 100)) : 0;
            document.getElementById('atividadePercent').innerHTML = `
                <i class="fas fa-chart-line"></i>
                ${atividadePercent}%
            `;
            
            // Calculate security status
            const segurancaStatus = alertas === 0 ? 'Seguro' : alertas < 5 ? 'Atenção' : 'Crítico';
            const segurancaIcon = alertas === 0 ? 'fa-shield-alt' : alertas < 5 ? 'fa-exclamation-triangle' : 'fa-exclamation-circle';
            document.getElementById('monitoramentoPercent').innerHTML = `
                <i class="fas ${segurancaIcon}"></i>
                ${segurancaStatus}
            `;
            
            // Calculate performance trend
            const performanceTrend = acoesHoje > usuariosAtivos ? '+' + Math.round((acoesHoje / Math.max(usuariosAtivos, 1)) * 10) + '%' : 'Estável';
            document.getElementById('performancePercent').innerHTML = `
                <i class="fas fa-trending-up"></i>
                ${performanceTrend}
            `;
        }

        // Log final de confirmação
        console.log('🎯 Sistema configurado para:', {
            usuario: <?php echo json_encode($usuarioLogado['nome']); ?>,
            isPresidencia: isPresidencia,
            isDiretor: isDiretor,
            departamento: departamentoUsuario,
            permissao: temPermissao,
            escopo: isPresidencia ? 'Sistema Completo' : `Departamento ${departamentoUsuario}`
        });
    </script>
</body>

</html>