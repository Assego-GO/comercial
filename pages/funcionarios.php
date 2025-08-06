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

// CORREÇÃO: Incluir a classe HeaderComponent ANTES de tentar usá-la
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

// NOVA LÓGICA: Sistema flexível de permissões
$temPermissaoFuncionarios = true; // Todos têm acesso à página
$motivoNegacao = '';
$escopoVisualizacao = ''; // 'TODOS', 'DEPARTAMENTO' ou 'PROPRIO'
$departamentoPermitido = null;
$podeEditar = false;
$podeCriar = false;

// Log para debug
error_log("=== DEBUG PERMISSÕES FUNCIONÁRIOS ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Cargo: " . ($usuarioLogado['cargo'] ?? 'Sem cargo'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));

// Sistema de permissões baseado em cargo e departamento
$cargoUsuario = $usuarioLogado['cargo'] ?? '';
$departamentoUsuario = $usuarioLogado['departamento_id'] ?? null;

// CORREÇÃO: Nova lógica de permissões
// Define escopo de visualização
if ($departamentoUsuario == 1) {
    // PRESIDÊNCIA - vê todos
    $escopoVisualizacao = 'TODOS';
    $podeEditar = true;
    $podeCriar = true;
    error_log("✅ PRESIDÊNCIA: Acesso total");
} elseif (in_array($cargoUsuario, ['Presidente', 'Vice-Presidente'])) {
    // Apenas Presidente e Vice-Presidente veem todos (mesmo fora da presidência)
    $escopoVisualizacao = 'TODOS';
    $podeEditar = true;
    $podeCriar = true;
    error_log("✅ {$cargoUsuario}: Acesso total");
} elseif (in_array($cargoUsuario, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador'])) {
    // CORREÇÃO: Diretores agora também veem apenas seu departamento
    $escopoVisualizacao = 'DEPARTAMENTO';
    $departamentoPermitido = $departamentoUsuario;
    $podeEditar = true;
    $podeCriar = true;
    error_log("✅ {$cargoUsuario}: Acesso ao departamento {$departamentoPermitido}");
} else {
    // Funcionários comuns - veem apenas seus dados
    $escopoVisualizacao = 'PROPRIO';
    $podeEditar = false;
    $podeCriar = false;
    error_log("✅ Funcionário comum: Acesso apenas aos próprios dados");
}

// Define o título da página
$page_title = 'Funcionários - ASSEGO';

// Inicializa classe de funcionários
$funcionarios = new Funcionarios();

// Busca estatísticas (com filtro por escopo)
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Preparar filtro baseado no escopo
    $filtroSQL = '';
    $params = [];
    
    if ($escopoVisualizacao === 'DEPARTAMENTO' && $departamentoPermitido) {
        $filtroSQL = ' WHERE departamento_id = ?';
        $params = [$departamentoPermitido];
    } elseif ($escopoVisualizacao === 'PROPRIO') {
        $filtroSQL = ' WHERE id = ?';
        $params = [$usuarioLogado['id']];
    }
    
    // Total de funcionários (com filtro)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Funcionarios" . $filtroSQL);
    $stmt->execute($params);
    $totalFuncionarios = $stmt->fetch()['total'] ?? 0;
    
    // Funcionários ativos (com filtro)
    $sqlAtivos = "SELECT COUNT(*) as total FROM Funcionarios" . 
                 ($filtroSQL ? $filtroSQL . ' AND' : ' WHERE') . " ativo = 1";
    $stmt = $db->prepare($sqlAtivos);
    $stmt->execute($params);
    $funcionariosAtivos = $stmt->fetch()['total'] ?? 0;
    
    // Funcionários inativos
    $funcionariosInativos = $totalFuncionarios - $funcionariosAtivos;
    
    // Novos funcionários (últimos 30 dias) (com filtro)
    $sqlNovos = "SELECT COUNT(*) as total FROM Funcionarios " . 
                ($filtroSQL ? str_replace('WHERE', 'WHERE', $filtroSQL) . ' AND' : 'WHERE') . 
                " criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $stmt = $db->prepare($sqlNovos);
    $stmt->execute($params);
    $novosFuncionarios = $stmt->fetch()['total'] ?? 0;
    
    // Total de departamentos (sempre todos, para contexto)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Departamentos WHERE ativo = 1");
    $stmt->execute();
    $totalDepartamentos = $stmt->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $totalFuncionarios = $funcionariosAtivos = $funcionariosInativos = $novosFuncionarios = $totalDepartamentos = 0;
}

// CORREÇÃO: Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'funcionarios',
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

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/funcionarios.css">
    
    <style>
        /* ===== ESTILOS CORRIGIDOS PARA O DROPDOWN ===== */
        :root {
            --primary: #0056d2;
            --primary-dark: #003d99;
            --primary-light: rgba(0, 86, 210, 0.1);
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-500: #6c757d;
            --gray-600: #495057;
            --gray-700: #343a40;
            --dark: #212529;
            --success: #00c853;
            --danger: #ff3b30;
            --warning: #ff9500;
            --info: #00b8d4;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        /* Header User Section - CORRIGIDO */
        .header-user {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: var(--white);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid var(--gray-200);
        }

        .header-user:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
            border-color: var(--primary);
        }

        .header-user.active {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: var(--primary);
            color: var(--white);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-weight: 700;
            color: var(--dark);
            font-size: 0.875rem;
            line-height: 1.2;
            margin-bottom: 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--gray-500);
            line-height: 1;
        }

        .dropdown-arrow {
            color: var(--gray-500);
            font-size: 0.75rem;
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }

        .header-user.active .dropdown-arrow {
            transform: rotate(180deg);
            color: var(--primary);
        }

        /* Dropdown Menu - CORRIGIDO */
        .user-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 240px;
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 9999;
            backdrop-filter: blur(10px);
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .dropdown-header {
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
        }

        .dropdown-user-name {
            font-weight: 700;
            color: var(--white);
            font-size: 0.9375rem;
            margin-bottom: 0.25rem;
        }

        .dropdown-user-role {
            font-size: 0.8125rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .dropdown-menu {
            padding: 0.5rem 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            color: var(--gray-700);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            color: var(--dark);
            transform: translateX(4px);
        }

        .dropdown-item i {
            width: 18px;
            color: var(--gray-500);
            font-size: 0.9375rem;
            text-align: center;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 0.5rem 0;
        }

        .dropdown-item.logout {
            color: var(--danger);
        }

        .dropdown-item.logout:hover {
            background: rgba(255, 59, 48, 0.1);
            color: var(--danger);
        }

        .dropdown-item.logout i {
            color: var(--danger);
        }

        /* Overlay para fechar dropdown */
        .dropdown-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9998;
            display: none;
        }

        .dropdown-overlay.show {
            display: block;
        }
        
        /* Estilos para permissões limitadas */
        .permission-notice {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .permission-notice i {
            color: #ffc107;
            font-size: 20px;
        }
        
        .action-disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando dados...</div>
    </div>

    <!-- Dropdown Overlay -->
    <div class="dropdown-overlay" id="dropdownOverlay"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header mb-4" data-aos="fade-right">
                <h1 class="page-title">Gestão de Funcionários</h1>
                <p class="page-subtitle">
                    <?php if ($escopoVisualizacao === 'TODOS'): ?>
                        Gerencie todos os funcionários e departamentos do sistema
                    <?php elseif ($escopoVisualizacao === 'DEPARTAMENTO'): ?>
                        Gerencie os funcionários do seu departamento
                    <?php else: ?>
                        Visualize seus dados pessoais
                    <?php endif; ?>
                </p>
                
                <?php if ($escopoVisualizacao === 'DEPARTAMENTO'): ?>
                    <div class="permission-notice">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Visualização por Departamento:</strong> Como <?php echo $cargoUsuario; ?>, você visualiza funcionários do seu departamento.
                        </div>
                    </div>
                <?php elseif ($escopoVisualizacao === 'PROPRIO'): ?>
                    <div class="permission-notice">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Visualização Limitada:</strong> Você pode visualizar apenas seus próprios dados.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalFuncionarios, 0, ',', '.'); ?></div>
                            <div class="stat-label">
                                <?php 
                                if ($escopoVisualizacao === 'TODOS') {
                                    echo 'Total de Funcionários';
                                } elseif ($escopoVisualizacao === 'DEPARTAMENTO') {
                                    echo 'Funcionários do Departamento';
                                } else {
                                    echo 'Seu Perfil';
                                }
                                ?>
                            </div>
                            <?php if ($escopoVisualizacao !== 'PROPRIO'): ?>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                12% este mês
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>

                <?php if ($escopoVisualizacao !== 'PROPRIO'): ?>
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
                <?php endif; ?>
            </div>

            <!-- Actions Bar with Filters -->
            <?php if ($escopoVisualizacao !== 'PROPRIO'): ?>
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

                    <?php if ($escopoVisualizacao === 'TODOS'): ?>
                    <div class="filter-group">
                        <label class="filter-label">Departamento</label>
                        <select class="filter-select" id="filterDepartamento">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <?php endif; ?>

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
                    <?php if ($podeCriar): ?>
                    <button class="btn-modern btn-primary" onclick="abrirModalNovo()">
                        <i class="fas fa-plus"></i>
                        Novo Funcionário
                    </button>
                    <?php else: ?>
                    <button class="btn-modern btn-primary action-disabled" disabled title="Sem permissão para criar funcionários">
                        <i class="fas fa-plus"></i>
                        Novo Funcionário
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Table Container -->
            <div class="table-container" data-aos="fade-up" data-aos-delay="200">
                <div class="table-header">
                    <h3 class="table-title">
                        <?php 
                        if ($escopoVisualizacao === 'PROPRIO') {
                            echo 'Meus Dados';
                        } else {
                            echo 'Lista de Funcionários';
                        }
                        ?>
                    </h3>
                    <span class="table-info">Mostrando <span id="showingCount">0</span> registros</span>
                </div>

                <div class="table-responsive p-2">
                    <table class="modern-table" id="funcionariosTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Foto</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <?php if ($escopoVisualizacao === 'TODOS'): ?>
                                <th>Departamento</th>
                                <?php endif; ?>
                                <th>Cargo</th>
                                <th>Badges</th>
                                <th>Status</th>
                                <th>Data Cadastro</th>
                                <th style="width: 120px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="<?php echo $escopoVisualizacao === 'TODOS' ? '9' : '8'; ?>" class="text-center py-5">
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
                    <?php if ($podeEditar): ?>
                    <button class="btn-modern btn-primary" onclick="editarDoVisualizacao()">
                        <i class="fas fa-edit"></i>
                        Editar Funcionário
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // ===== INICIALIZAÇÃO E CONFIGURAÇÃO =====
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais - INCLUINDO ESCOPO DE VISUALIZAÇÃO
        let todosFuncionarios = [];
        let funcionariosFiltrados = [];
        let departamentosDisponiveis = [];
        let dropdownOpen = false;
        
        // Configurações de permissão
        const escopoVisualizacao = <?php echo json_encode($escopoVisualizacao); ?>;
        const departamentoPermitido = <?php echo json_encode($departamentoPermitido); ?>;
        const podeEditar = <?php echo json_encode($podeEditar); ?>;
        const podeCriar = <?php echo json_encode($podeCriar); ?>;
        const usuarioLogadoId = <?php echo json_encode($usuarioLogado['id']); ?>;
        
        console.log('=== CONFIG FUNCIONÁRIOS ===');
        console.log('Escopo:', escopoVisualizacao);
        console.log('Departamento permitido:', departamentoPermitido);
        console.log('Pode editar:', podeEditar);
        console.log('Pode criar:', podeCriar);

        // ===== FUNÇÕES CORRIGIDAS PARA O DROPDOWN =====
        
        // Função principal para toggle do dropdown - VERSÃO CORRIGIDA
        function toggleUserDropdown(event) {
            if (event) {
                event.stopPropagation();
            }
            
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');
            const dropdownOverlay = document.getElementById('dropdownOverlay');
            
            if (!userMenu || !userDropdown) {
                console.log('❌ Elementos do dropdown não encontrados');
                return;
            }

            // Verificar estado atual
            const isOpen = userDropdown.classList.contains('show');
            console.log('Estado atual do dropdown:', isOpen ? 'ABERTO' : 'FECHADO');
            
            if (isOpen) {
                // Fechar dropdown
                userDropdown.classList.remove('show');
                userMenu.classList.remove('active');
                if (dropdownOverlay) {
                    dropdownOverlay.classList.remove('show');
                }
                dropdownOpen = false;
                console.log('✅ Dropdown fechado');
            } else {
                // Abrir dropdown
                userDropdown.classList.add('show');
                userMenu.classList.add('active');
                if (dropdownOverlay) {
                    dropdownOverlay.classList.add('show');
                }
                dropdownOpen = true;
                console.log('✅ Dropdown aberto');
            }
        }

        // Função para fechar o dropdown - VERSÃO CORRIGIDA
        function closeUserDropdown() {
            const userMenu = document.getElementById('userMenu');
            const userDropdown = document.getElementById('userDropdown');
            const dropdownOverlay = document.getElementById('dropdownOverlay');
            
            if (userDropdown) {
                userDropdown.classList.remove('show');
            }
            if (userMenu) {
                userMenu.classList.remove('active');
            }
            if (dropdownOverlay) {
                dropdownOverlay.classList.remove('show');
            }
            dropdownOpen = false;
            console.log('✅ Dropdown fechado via closeUserDropdown');
        }

        // Função para lidar com cliques nos itens do menu - SIMPLIFICADA
        function handleMenuClick(action, event) {
            console.log('Ação:', action);
            
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            closeUserDropdown();
            
            switch(action) {
                case 'profile':
                    window.location.href = 'perfil.php';
                    break;
                case 'settings':
                    window.location.href = 'configuracoes.php';
                    break;
                case 'logout':
                    if(confirm('Deseja realmente sair do sistema?')) {
                        showLoading();
                        window.location.href = '../auth/logout.php';
                    }
                    break;
            }
        }

        // ===== EVENT LISTENERS =====
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== INICIALIZANDO PÁGINA FUNCIONÁRIOS ===');
            
            // EVENT DELEGATION - Abordagem mais robusta
            document.addEventListener('click', function(event) {
                const target = event.target;
                const userMenu = document.getElementById('userMenu');
                const userDropdown = document.getElementById('userDropdown');
                
                // Clique no userMenu ou seus filhos
                if (userMenu && (target === userMenu || userMenu.contains(target))) {
                    console.log('👆 Clique detectado no userMenu');
                    event.stopPropagation();
                    toggleUserDropdown(event);
                    return;
                }
                
                // Clique em item do dropdown
                if (userDropdown && userDropdown.contains(target)) {
                    const dropdownItem = target.closest('.dropdown-item');
                    if (dropdownItem) {
                        console.log('👆 Clique em item do dropdown:', dropdownItem.textContent.trim());
                        event.preventDefault();
                        event.stopPropagation();
                        
                        const text = dropdownItem.textContent.trim();
                        if (text.includes('Perfil') || text.includes('Meu Perfil')) {
                            handleMenuClick('profile', event);
                        } else if (text.includes('Configurações')) {
                            handleMenuClick('settings', event);
                        } else if (text.includes('Sair')) {
                            handleMenuClick('logout', event);
                        }
                        return;
                    }
                }
                
                // Clique no overlay
                if (target.id === 'dropdownOverlay') {
                    console.log('👆 Clique no overlay');
                    closeUserDropdown();
                    return;
                }
                
                // Clique fora do dropdown
                if (userMenu && userDropdown && 
                    !userMenu.contains(target) && 
                    !userDropdown.contains(target)) {
                    if (dropdownOpen) {
                        console.log('👆 Clique fora do dropdown');
                        closeUserDropdown();
                    }
                }
            });
            
            // Fechar dropdown com tecla ESC
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && dropdownOpen) {
                    console.log('⌨️ ESC pressionado');
                    closeUserDropdown();
                }
            });
            
            // Debug: Verificar se elementos existem após carregamento
            setTimeout(() => {
                const userMenu = document.getElementById('userMenu');
                const userDropdown = document.getElementById('userDropdown');
                console.log('🔍 Status dos elementos:');
                console.log('- userMenu:', userMenu ? '✅' : '❌');
                console.log('- userDropdown:', userDropdown ? '✅' : '❌');
                
                if (userDropdown) {
                    const items = userDropdown.querySelectorAll('.dropdown-item');
                    console.log('- Itens do dropdown:', items.length);
                    items.forEach((item, i) => {
                        console.log(`  ${i+1}. "${item.textContent.trim()}"`);
                    });
                }
            }, 1000);

            // Máscaras
            if (typeof $ !== 'undefined' && $('#cpf').length) {
                $('#cpf').mask('000.000.000-00');
            }

            // Event listeners dos filtros (apenas se não for visualização própria)
            if (escopoVisualizacao !== 'PROPRIO') {
                const searchInput = document.getElementById('searchInput');
                const filterStatus = document.getElementById('filterStatus');
                const filterDepartamento = document.getElementById('filterDepartamento');
                const filterCargo = document.getElementById('filterCargo');
                
                if (searchInput) searchInput.addEventListener('input', aplicarFiltros);
                if (filterStatus) filterStatus.addEventListener('change', aplicarFiltros);
                if (filterDepartamento) filterDepartamento.addEventListener('change', aplicarFiltros);
                if (filterCargo) filterCargo.addEventListener('change', aplicarFiltros);
            }
            
            const formFuncionario = document.getElementById('formFuncionario');
            if (formFuncionario) formFuncionario.addEventListener('submit', salvarFuncionario);

            // Carrega dados
            carregarFuncionarios();
            carregarDepartamentos();
        });

        // ===== FUNÇÕES DE LOADING =====
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.add('active');
            }
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }

        // ===== FUNÇÕES DE DADOS =====
        
        // Carrega lista de funcionários - AJUSTADO PARA ESCOPO
        function carregarFuncionarios() {
            showLoading();
            
            // Parâmetros baseados no escopo
            let params = {};
            if (escopoVisualizacao === 'DEPARTAMENTO' && departamentoPermitido) {
                params.departamento_filtro = departamentoPermitido;
            } else if (escopoVisualizacao === 'PROPRIO') {
                params.proprio_id = usuarioLogadoId;
            }

            $.ajax({
                url: '../api/funcionarios_listar.php',
                method: 'GET',
                data: params,
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        todosFuncionarios = response.funcionarios;
                        funcionariosFiltrados = [...todosFuncionarios];
                        renderizarTabela();
                        
                        console.log(`✅ Carregados ${todosFuncionarios.length} funcionários (escopo: ${escopoVisualizacao})`);
                    } else {
                        console.error('Erro ao carregar funcionários:', response);
                        alert('Erro ao carregar dados: ' + (response.message || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', error);
                    console.error('Response:', xhr.responseText);
                    alert('Erro ao carregar funcionários: ' + error);
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

        // Preenche select de departamentos - AJUSTADO PARA ESCOPO
        function preencherSelectDepartamentos() {
            const selectFilter = document.getElementById('filterDepartamento');
            const selectForm = document.getElementById('departamento_id');
            
            // Filtro de departamento só aparece para visualização total
            if (selectFilter && escopoVisualizacao === 'TODOS') {
                selectFilter.innerHTML = '<option value="">Todos</option>';
                departamentosDisponiveis.forEach(dep => {
                    const optionFilter = document.createElement('option');
                    optionFilter.value = dep.id;
                    optionFilter.textContent = dep.nome;
                    selectFilter.appendChild(optionFilter);
                });
            }
            
            // Formulário
            if (selectForm) {
                selectForm.innerHTML = '<option value="">Selecione um departamento</option>';
                
                if (escopoVisualizacao === 'DEPARTAMENTO' && departamentoPermitido) {
                    // Para diretores/gerentes/supervisores: pode escolher apenas seu departamento
                    const deptPermitido = departamentosDisponiveis.find(d => d.id == departamentoPermitido);
                    if (deptPermitido) {
                        const option = document.createElement('option');
                        option.value = deptPermitido.id;
                        option.textContent = deptPermitido.nome;
                        option.selected = true;
                        selectForm.appendChild(option);
                        selectForm.disabled = true;
                    }
                } else if (escopoVisualizacao === 'TODOS') {
                    // Presidência e presidentes/vice-presidentes: todos os departamentos
                    departamentosDisponiveis.forEach(dep => {
                        const option = document.createElement('option');
                        option.value = dep.id;
                        option.textContent = dep.nome;
                        selectForm.appendChild(option);
                    });
                }
            }
        }

        // Renderiza tabela - AJUSTADO PARA PERMISSÕES
        function renderizarTabela() {
            const tbody = document.getElementById('tableBody');
            const totalColunas = escopoVisualizacao === 'TODOS' ? 9 : 8;
            
            if (!tbody) {
                console.error('Elemento tableBody não encontrado');
                return;
            }
            
            tbody.innerHTML = '';
            
            if (funcionariosFiltrados.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${totalColunas}" class="text-center py-5">
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
                
                // Determinar se pode editar este funcionário
                let podeEditarEste = false;
                if (escopoVisualizacao === 'PROPRIO') {
                    // Só pode editar se for o próprio
                    podeEditarEste = (funcionario.id == usuarioLogadoId);
                } else {
                    podeEditarEste = podeEditar;
                }
                
                const row = document.createElement('tr');
                
                // Monta HTML da linha baseado no escopo
                let rowHtml = `
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
                `;
                
                // Só mostra departamento se for visualização completa
                if (escopoVisualizacao === 'TODOS') {
                    rowHtml += `<td>${funcionario.departamento_nome || '-'}</td>`;
                }
                
                rowHtml += `
                    <td>${cargoBadge}</td>
                    <td>${badgesHtml}</td>
                    <td>${statusBadge}</td>
                    <td>${formatarData(funcionario.criado_em)}</td>
                    <td>
                        <div class="action-buttons-table">
                            <button class="btn-icon view" onclick="visualizarFuncionario(${funcionario.id})" title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </button>
                `;
                
                if (podeEditarEste) {
                    rowHtml += `
                            <button class="btn-icon edit" onclick="editarFuncionario(${funcionario.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon delete" onclick="desativarFuncionario(${funcionario.id})" title="Desativar">
                                <i class="fas fa-ban"></i>
                            </button>
                    `;
                } else {
                    rowHtml += `
                            <button class="btn-icon edit action-disabled" disabled title="Sem permissão">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon delete action-disabled" disabled title="Sem permissão">
                                <i class="fas fa-ban"></i>
                            </button>
                    `;
                }
                
                rowHtml += `
                        </div>
                    </td>
                `;
                
                row.innerHTML = rowHtml;
                tbody.appendChild(row);
            });
            
            const showingCount = document.getElementById('showingCount');
            if (showingCount) {
                showingCount.textContent = funcionariosFiltrados.length;
            }
        }

        // Aplica filtros
        function aplicarFiltros() {
            const searchInput = document.getElementById('searchInput');
            const filterStatus = document.getElementById('filterStatus');
            const filterDepartamento = document.getElementById('filterDepartamento');
            const filterCargo = document.getElementById('filterCargo');
            
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const filterStatusValue = filterStatus ? filterStatus.value : '';
            const filterDepartamentoValue = filterDepartamento ? filterDepartamento.value : '';
            const filterCargoValue = filterCargo ? filterCargo.value : '';
            
            funcionariosFiltrados = todosFuncionarios.filter(funcionario => {
                const matchSearch = !searchTerm || 
                    funcionario.nome.toLowerCase().includes(searchTerm) ||
                    funcionario.email.toLowerCase().includes(searchTerm) ||
                    (funcionario.cargo && funcionario.cargo.toLowerCase().includes(searchTerm));
                
                const matchStatus = !filterStatusValue || funcionario.ativo == filterStatusValue;
                const matchDepartamento = !filterDepartamentoValue || funcionario.departamento_id == filterDepartamentoValue;
                const matchCargo = !filterCargoValue || funcionario.cargo === filterCargoValue;
                
                return matchSearch && matchStatus && matchDepartamento && matchCargo;
            });
            
            renderizarTabela();
        }

        // Limpa filtros
        function limparFiltros() {
            const searchInput = document.getElementById('searchInput');
            const filterStatus = document.getElementById('filterStatus');
            const filterDepartamento = document.getElementById('filterDepartamento');
            const filterCargo = document.getElementById('filterCargo');
            
            if (searchInput) searchInput.value = '';
            if (filterStatus) filterStatus.value = '';
            if (filterDepartamento) filterDepartamento.value = '';
            if (filterCargo) filterCargo.value = '';
            
            funcionariosFiltrados = [...todosFuncionarios];
            renderizarTabela();
        }

        // ===== FUNÇÕES DOS MODAIS =====
        
        // Abre modal para novo funcionário
        function abrirModalNovo() {
            if (!podeCriar) {
                alert('Você não tem permissão para criar funcionários');
                return;
            }
            
            const modalTitle = document.getElementById('modalTitle');
            const form = document.getElementById('formFuncionario');
            const funcionarioId = document.getElementById('funcionarioId');
            const senha = document.getElementById('senha');
            const senhaInfo = document.getElementById('senhaInfo');
            const senhaEditInfo = document.getElementById('senhaEditInfo');
            const modal = document.getElementById('modalFuncionario');
            
            if (modalTitle) modalTitle.textContent = 'Novo Funcionário';
            if (form) form.reset();
            if (funcionarioId) funcionarioId.value = '';
            
            // Para novo funcionário, define a senha padrão
            if (senha) {
                senha.value = 'Assego@123';
                senha.readOnly = true;
            }
            if (senhaInfo) senhaInfo.style.display = 'inline';
            if (senhaEditInfo) senhaEditInfo.style.display = 'none';
            
            // Se for diretor/gerente/supervisor, pré-seleciona o departamento
            if (escopoVisualizacao === 'DEPARTAMENTO' && departamentoPermitido) {
                setTimeout(() => {
                    const departamentoSelect = document.getElementById('departamento_id');
                    if (departamentoSelect) {
                        departamentoSelect.value = departamentoPermitido;
                    }
                }, 100);
            }
            
            if (modal) {
                modal.classList.add('show');
            }
        }

        // Edita funcionário
        function editarFuncionario(id) {
            if (!podeEditar && !(escopoVisualizacao === 'PROPRIO' && id == usuarioLogadoId)) {
                alert('Você não tem permissão para editar funcionários');
                return;
            }
            
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
                        
                        document.getElementById('modalTitle').textContent = 'Editar Funcionário';
                        document.getElementById('funcionarioId').value = funcionario.id;
                        document.getElementById('nome').value = funcionario.nome;
                        document.getElementById('email').value = funcionario.email;
                        document.getElementById('departamento_id').value = funcionario.departamento_id || '';
                        
                        // Preenche o cargo
                        const cargoSelect = document.getElementById('cargo');
                        const cargoValue = funcionario.cargo || '';
                        
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
                        
                        if (cargoValue) {
                            cargoSelect.value = cargoValue;
                            
                            if (cargoSelect.value === '' && cargoValue.trim() !== '') {
                                const newOption = document.createElement('option');
                                newOption.value = cargoValue;
                                newOption.textContent = cargoValue;
                                newOption.selected = true;
                                cargoSelect.appendChild(newOption);
                            }
                        }
                        
                        document.getElementById('cpf').value = funcionario.cpf || '';
                        document.getElementById('rg').value = funcionario.rg || '';
                        document.getElementById('ativo').checked = funcionario.ativo == 1;
                        
                        // Senha
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
            
            // Para novo funcionário
            if (!dados.id) {
                dados.senha = 'Assego@123';
                
                // Se for diretor/gerente/supervisor, força o departamento
                if (escopoVisualizacao === 'DEPARTAMENTO' && departamentoPermitido) {
                    dados.departamento_id = departamentoPermitido;
                }
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
            if (!podeEditar && !(escopoVisualizacao === 'PROPRIO' && id == usuarioLogadoId)) {
                alert('Você não tem permissão para desativar funcionários');
                return;
            }
            
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
            const modal = document.getElementById('modalFuncionario');
            const form = document.getElementById('formFuncionario');
            
            if (modal) modal.classList.remove('show');
            if (form) form.reset();
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
                fecharModalVisualizacao();
                closeUserDropdown();
            }
        });
    </script>
</body>
</html>