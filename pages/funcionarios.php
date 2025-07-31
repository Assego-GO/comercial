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

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'avatar' => $usuarioLogado['avatar'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'funcionarios', // CORREÇÃO: mudei para 'funcionarios'
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
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando dados...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

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

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

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