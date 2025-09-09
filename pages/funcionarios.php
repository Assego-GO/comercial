    <?php
    /**
     * Página de Gestão de Funcionários - Versão Melhorada
     * pages/funcionarios.php
     */

    // Configuração e includes
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';
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

    // Processar ação de simular funcionário
    if (isset($_POST['action']) && $_POST['action'] === 'simular' && isset($_POST['funcionario_id'])) {
        if ($auth->isAdmin()) {
            $funcionario_id = $_POST['funcionario_id'];
            $resultado = $auth->assumirFuncionario($funcionario_id);
            
            if ($resultado['success']) {
                header('Location: ./dashboard.php?mensagem=' . urlencode('Simulando funcionário!') . '&tipo=success');
                exit;
            } else {
                $erro_simulacao = $resultado['message'];
            }
        }
    }

    // Processar volta da simulação
    if (isset($_POST['voltar_simulacao']) || isset($_GET['voltar_simulacao'])) {
        if ($auth->estaSimulando()) {
            $resultado = $auth->voltarParaAdmin();
            
            if ($resultado) {
                if (isset($_POST['voltar_simulacao'])) {
                    http_response_code(200);
                    echo json_encode(['status' => 'success']);
                    exit;
                }
                
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }

    // Sistema flexível de permissões
    $temPermissaoFuncionarios = true;
    $motivoNegacao = '';
    $escopoVisualizacao = '';
    $departamentoPermitido = null;
    $podeEditar = false;
    $podeCriar = false;

    // Departamentos estratégicos
    $departamentosEstrategicos = [1, 2, 9, 10];

    // Sistema de permissões baseado em cargo e departamento
    $cargoUsuario = $usuarioLogado['cargo'] ?? '';
    $departamentoUsuario = (int)($usuarioLogado['departamento_id'] ?? 0);

    $estaNoDepartamentoEstrategico = in_array($departamentoUsuario, $departamentosEstrategicos, true);

    if ($estaNoDepartamentoEstrategico) {
        $escopoVisualizacao = 'TODOS';
        $podeEditar = true;
        $podeCriar = true;
    } elseif (in_array($cargoUsuario, ['Presidente', 'Vice-Presidente'])) {
        $escopoVisualizacao = 'TODOS';
        $podeEditar = true;
        $podeCriar = true;
    } elseif (in_array($cargoUsuario, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador'])) {
        $escopoVisualizacao = 'DEPARTAMENTO';
        $departamentoPermitido = $departamentoUsuario;
        $podeEditar = true;
        $podeCriar = true;
    } else {
        $escopoVisualizacao = 'PROPRIO';
        $podeEditar = false;
        $podeCriar = false;
    }

    // Define o título da página
    $page_title = 'Funcionários - ASSEGO';

    // Inicializa classe de funcionários
    $funcionarios = new Funcionarios();

    // Busca estatísticas
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
        
        // Total de funcionários
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM Funcionarios" . $filtroSQL);
        $stmt->execute($params);
        $totalFuncionarios = $stmt->fetch()['total'] ?? 0;
        
        // Funcionários ativos
        $sqlAtivos = "SELECT COUNT(*) as total FROM Funcionarios" . 
                    ($filtroSQL ? $filtroSQL . ' AND' : ' WHERE') . " ativo = 1";
        $stmt = $db->prepare($sqlAtivos);
        $stmt->execute($params);
        $funcionariosAtivos = $stmt->fetch()['total'] ?? 0;
        
        // Funcionários inativos
        $funcionariosInativos = $totalFuncionarios - $funcionariosAtivos;
        
        // Novos funcionários (últimos 30 dias)
        $sqlNovos = "SELECT COUNT(*) as total FROM Funcionarios " . 
                    ($filtroSQL ? str_replace('WHERE', 'WHERE', $filtroSQL) . ' AND' : 'WHERE') . 
                    " criado_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $stmt = $db->prepare($sqlNovos);
        $stmt->execute($params);
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
            /* Estilos adicionais para linha clicável */
            .modern-table tbody tr {
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .modern-table tbody tr:hover {
                background-color: rgba(79, 70, 229, 0.05);
                transform: translateX(2px);
            }

            /* Avatar padronizado com cor primária */
            .table-avatar {
                width: 45px;
                height: 45px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 1.1rem;
                text-transform: uppercase;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
                background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
                color: white;
                box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
            }

            .table-avatar:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
            }

            .table-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 12px;
            }

            /* Modal Avatar View padronizado */
            .modal-avatar-view {
                width: 60px;
                height: 60px;
                border-radius: 15px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 1.5rem;
                text-transform: uppercase;
                background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
                color: white;
                border: 3px solid rgba(255, 255, 255, 0.2);
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
            }

            /* Indicador visual de linha clicável */
            .clickable-indicator {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                opacity: 0;
                transition: opacity 0.3s ease;
                color: var(--primary);
                font-size: 0.9rem;
            }

            .modern-table tbody tr:hover .clickable-indicator {
                opacity: 1;
            }

            /* Paginação */
            .pagination-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                background: var(--white);
                border-top: 1px solid var(--border-light);
                border-radius: 0 0 16px 16px;
                margin-top: auto;
            }

            .pagination-info {
                color: var(--text-muted);
                font-size: 0.875rem;
            }

            .pagination-controls {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .pagination-nav {
                display: flex;
                align-items: center;
                gap: 0.25rem;
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .pagination-nav li {
                margin: 0;
            }

            .pagination-nav .page-link {
                display: flex;
                align-items: center;
                justify-content: center;
                min-width: 40px;
                height: 40px;
                padding: 0.5rem;
                background: var(--white);
                border: 1px solid var(--border-light);
                color: var(--text-primary);
                text-decoration: none;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 500;
                transition: all 0.2s ease;
            }

            .pagination-nav .page-link:hover {
                background: var(--primary-light);
                border-color: var(--primary);
                color: var(--primary);
                transform: translateY(-1px);
            }

            .pagination-nav .page-item.active .page-link {
                background: var(--primary);
                border-color: var(--primary);
                color: var(--white);
                font-weight: 600;
            }

            .pagination-nav .page-item.disabled .page-link {
                background: var(--gray-100);
                border-color: var(--border-light);
                color: var(--text-muted);
                cursor: not-allowed;
                opacity: 0.6;
            }

            .page-size-selector {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                color: var(--text-muted);
            }

            .page-size-selector select {
                padding: 0.375rem 0.75rem;
                border: 1px solid var(--border-light);
                border-radius: 6px;
                background: var(--white);
                color: var(--text-primary);
                font-size: 0.875rem;
                cursor: pointer;
            }

            /* Tooltip para linha clicável */
            .click-hint {
                position: fixed;
                bottom: 30px;
                right: 30px;
                background: var(--primary);
                color: white;
                padding: 12px 20px;
                border-radius: 50px;
                font-size: 0.875rem;
                font-weight: 500;
                box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
                z-index: 1000;
                animation: slideInUp 0.5s ease;
                display: none;
            }

            .click-hint.show {
                display: block;
            }

            @keyframes slideInUp {
                from {
                    transform: translateY(100px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            /* Responsividade */
            @media (max-width: 768px) {
                .pagination-container {
                    flex-direction: column;
                    gap: 1rem;
                    text-align: center;
                }

                .pagination-nav .page-link {
                    min-width: 36px;
                    height: 36px;
                    font-size: 0.8rem;
                }

                .click-hint {
                    bottom: 20px;
                    right: 20px;
                    left: 20px;
                    text-align: center;
                }

                .table-avatar {
                    width: 40px;
                    height: 40px;
                    font-size: 1rem;
                }

                .modal-avatar-view {
                    width: 50px;
                    height: 50px;
                    font-size: 1.2rem;
                }
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

        <!-- Dica de clique -->
        <div class="click-hint" id="clickHint">
            <i class="fas fa-lightbulb"></i> Clique em qualquer linha para ver detalhes
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
                    <p class="page-subtitle">
                        <?php if ($escopoVisualizacao === 'TODOS'): ?>
                            Gerencie todos os funcionários e departamentos do sistema
                        <?php elseif ($escopoVisualizacao === 'DEPARTAMENTO'): ?>
                            Gerencie os funcionários do seu departamento
                        <?php else: ?>
                            Visualize seus dados pessoais
                        <?php endif; ?>
                    </p>
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
                        <button class="btn-modern btn-secondary" onclick="recarregarDados()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                        <?php if ($podeCriar): ?>
                        <button class="btn-modern btn-primary" onclick="abrirModalNovo()">
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
                            <?php echo $escopoVisualizacao === 'PROPRIO' ? 'Meus Dados' : 'Lista de Funcionários'; ?>
                        </h3>
                        <div class="table-info">
                            <div class="info-item">
                                <i class="fas fa-list"></i>
                                <span>Exibindo <span id="showingStart">0</span>-<span id="showingEnd">0</span> de <span id="totalRecords">0</span> registros</span>
                            </div>
                        </div>
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
                                    <th>Status</th>
                                    <th>Data Cadastro</th>
                                    <th style="width: 120px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td colspan="<?php echo $escopoVisualizacao === 'TODOS' ? '8' : '7'; ?>" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="loading-spinner mb-3"></div>
                                            <p class="text-muted mb-0">Carregando funcionários...</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <div class="pagination-container" id="paginationContainer">
                        <div class="pagination-info">
                            <div class="page-size-selector">
                                <span>Mostrar:</span>
                                <select id="pageSize" onchange="alterarTamanhoPagina()">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span>por página</span>
                            </div>
                        </div>

                        <div class="pagination-controls">
                            <ul class="pagination-nav" id="paginationNav">
                                <!-- Controles de paginação serão inseridos aqui pelo JavaScript -->
                            </ul>
                        </div>
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
                            <input type="password" class="form-control-custom" id="senha" name="senha">
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
            <div class="modal-content-custom" style="max-width: 600px;">
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
                    <div class="view-content">
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
                    </div>

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
            // Inicializa AOS
            AOS.init({
                duration: 800,
                once: true
            });

            // Variáveis globais
            let todosFuncionarios = [];
            let funcionariosFiltrados = [];
            let departamentosDisponiveis = [];
            let dropdownOpen = false;
            
            // Variáveis de paginação
            let paginaAtual = 1;
            let tamanhoPagina = 10;
            let totalRegistros = 0;
            let totalPaginas = 0;
            
            // Configurações de permissão
            const escopoVisualizacao = <?php echo json_encode($escopoVisualizacao); ?>;
            const departamentoPermitido = <?php echo json_encode($departamentoPermitido); ?>;
            const podeEditar = <?php echo json_encode($podeEditar); ?>;
            const podeCriar = <?php echo json_encode($podeCriar); ?>;
            const usuarioLogadoId = <?php echo json_encode($usuarioLogado['id']); ?>;
            const isAdmin = <?php echo json_encode($auth->isAdmin()); ?>;
            const userDepartamento = <?php echo json_encode($usuarioLogado["departamento_id"]); ?>;

            // Função para obter as iniciais do nome
            function getInitials(nome) {
                if (!nome) return '?';
                
                const parts = nome.trim().split(' ');
                if (parts.length === 1) {
                    return parts[0].charAt(0).toUpperCase();
                }
                
                // Pega primeira e última inicial
                return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
            }

            // Toggle do dropdown
            function toggleUserDropdown(event) {
                if (event) {
                    event.stopPropagation();
                }
                
                const userMenu = document.getElementById('userMenu');
                const userDropdown = document.getElementById('userDropdown');
                const dropdownOverlay = document.getElementById('dropdownOverlay');
                
                if (!userMenu || !userDropdown) return;

                const isOpen = userDropdown.classList.contains('show');
                
                if (isOpen) {
                    userDropdown.classList.remove('show');
                    userMenu.classList.remove('active');
                    if (dropdownOverlay) dropdownOverlay.classList.remove('show');
                    dropdownOpen = false;
                } else {
                    userDropdown.classList.add('show');
                    userMenu.classList.add('active');
                    if (dropdownOverlay) dropdownOverlay.classList.add('show');
                    dropdownOpen = true;
                }
            }

            // Fecha o dropdown
            function closeUserDropdown() {
                const userMenu = document.getElementById('userMenu');
                const userDropdown = document.getElementById('userDropdown');
                const dropdownOverlay = document.getElementById('dropdownOverlay');
                
                if (userDropdown) userDropdown.classList.remove('show');
                if (userMenu) userMenu.classList.remove('active');
                if (dropdownOverlay) dropdownOverlay.classList.remove('show');
                dropdownOpen = false;
            }

            // Event Listeners
            document.addEventListener('DOMContentLoaded', function() {
                console.log('=== INICIALIZANDO PÁGINA FUNCIONÁRIOS ===');
                
                // Mostra dica de clique após 2 segundos
                setTimeout(() => {
                    const hint = document.getElementById('clickHint');
                    if (hint && escopoVisualizacao !== 'PROPRIO') {
                        hint.classList.add('show');
                        
                        // Remove após 5 segundos
                        setTimeout(() => {
                            hint.classList.remove('show');
                        }, 5000);
                    }
                }, 2000);
                
                // Event delegation para cliques
                document.addEventListener('click', function(event) {
                    const target = event.target;
                    const userMenu = document.getElementById('userMenu');
                    const userDropdown = document.getElementById('userDropdown');
                    
                    if (userMenu && (target === userMenu || userMenu.contains(target))) {
                        event.stopPropagation();
                        toggleUserDropdown(event);
                        return;
                    }
                    
                    if (userDropdown && userDropdown.contains(target)) {
                        const dropdownItem = target.closest('.dropdown-item');
                        if (dropdownItem) {
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
                    
                    if (target.id === 'dropdownOverlay') {
                        closeUserDropdown();
                        return;
                    }
                    
                    if (userMenu && userDropdown && 
                        !userMenu.contains(target) && 
                        !userDropdown.contains(target)) {
                        if (dropdownOpen) {
                            closeUserDropdown();
                        }
                    }
                });
                
                // Máscaras
                if (typeof $ !== 'undefined' && $('#cpf').length) {
                    $('#cpf').mask('000.000.000-00');
                }

                // Event listeners dos filtros
                if (escopoVisualizacao !== 'PROPRIO') {
                    const searchInput = document.getElementById('searchInput');
                    const filterStatus = document.getElementById('filterStatus');
                    const filterDepartamento = document.getElementById('filterDepartamento');
                    const filterCargo = document.getElementById('filterCargo');
                    
                    if (searchInput) {
                        searchInput.addEventListener('input', debounce(aplicarFiltros, 500));
                    }
                    if (filterStatus) filterStatus.addEventListener('change', aplicarFiltros);
                    if (filterDepartamento) filterDepartamento.addEventListener('change', aplicarFiltros);
                    if (filterCargo) filterCargo.addEventListener('change', aplicarFiltros);
                }
                
                const formFuncionario = document.getElementById('formFuncionario');
                if (formFuncionario) formFuncionario.addEventListener('submit', salvarFuncionario);

                // Carrega dados iniciais
                carregarFuncionarios();
                carregarDepartamentos();
            });

            // Lidar com cliques no menu
            function handleMenuClick(action, event) {
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

            // Funções de Loading
            function showLoading() {
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.classList.add('active');
            }

            function hideLoading() {
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.classList.remove('active');
            }

            // Debounce para melhorar performance
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

            // Coleta filtros ativos
            function coletarFiltros() {
                const filtros = {};
                
                if (escopoVisualizacao !== 'PROPRIO') {
                    const searchInput = document.getElementById('searchInput');
                    const filterStatus = document.getElementById('filterStatus');
                    const filterDepartamento = document.getElementById('filterDepartamento');
                    const filterCargo = document.getElementById('filterCargo');
                    
                    if (searchInput && searchInput.value.trim()) {
                        filtros.busca = searchInput.value.trim();
                    }
                    if (filterStatus && filterStatus.value) {
                        filtros.status = filterStatus.value;
                    }
                    if (filterDepartamento && filterDepartamento.value) {
                        filtros.departamento = filterDepartamento.value;
                    }
                    if (filterCargo && filterCargo.value) {
                        filtros.cargo = filterCargo.value;
                    }
                }
                
                return filtros;
            }

            // Carrega funcionários
            function carregarFuncionarios(resetarPagina = true) {
                if (resetarPagina) {
                    paginaAtual = 1;
                }
                
                showLoading();
                
                let params = {
                    pagina: paginaAtual,
                    limite: tamanhoPagina,
                    ...coletarFiltros()
                };
                
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
                            todosFuncionarios = response.funcionarios || [];
                            totalRegistros = parseInt(response.total || 0);
                            totalPaginas = Math.ceil(totalRegistros / tamanhoPagina);
                            
                            renderizarTabela();
                            renderizarPaginacao();
                            atualizarInfoPaginacao();
                            
                            console.log(`✅ Carregados ${todosFuncionarios.length} funcionários`);
                        } else {
                            console.error('Erro ao carregar funcionários:', response);
                            exibirErroTabela('Erro ao carregar dados: ' + (response.message || 'Erro desconhecido'));
                        }
                    },
                    error: function(xhr, status, error) {
                        hideLoading();
                        console.error('Erro:', error);
                        exibirErroTabela('Erro ao carregar funcionários: ' + error);
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
                
                if (selectFilter && escopoVisualizacao === 'TODOS') {
                    selectFilter.innerHTML = '<option value="">Todos</option>';
                    departamentosDisponiveis.forEach(dep => {
                        const optionFilter = document.createElement('option');
                        optionFilter.value = dep.id;
                        optionFilter.textContent = dep.nome;
                        selectFilter.appendChild(optionFilter);
                    });
                }
                
                if (selectForm) {
                    selectForm.innerHTML = '<option value="">Selecione um departamento</option>';
                    
                    if (escopoVisualizacao === 'DEPARTAMENTO' && departamentoPermitido) {
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
                        departamentosDisponiveis.forEach(dep => {
                            const option = document.createElement('option');
                            option.value = dep.id;
                            option.textContent = dep.nome;
                            selectForm.appendChild(option);
                        });
                    }
                }
            }

            // Renderiza tabela com linha clicável e avatares padronizados
            function renderizarTabela() {
                const tbody = document.getElementById('tableBody');
                const totalColunas = escopoVisualizacao === 'TODOS' ? 8 : 7;
                
                if (!tbody) return;
                
                tbody.innerHTML = '';
                
                if (todosFuncionarios.length === 0) {
                    const mensagem = totalRegistros === 0 ? 'Nenhum funcionário encontrado' : 'Nenhum resultado para esta página';
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${totalColunas}" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                                    <p class="text-muted mb-0">${mensagem}</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                todosFuncionarios.forEach((funcionario, index) => {
                    const statusBadge = funcionario.ativo == 1
                        ? '<span class="status-badge active"><i class="fas fa-check-circle"></i> Ativo</span>'
                        : '<span class="status-badge inactive"><i class="fas fa-times-circle"></i> Inativo</span>';
                    
                    let cargoBadge = `<span class="cargo-badge">${funcionario.cargo || 'Sem cargo'}</span>`;
                    if (funcionario.cargo === 'Diretor') {
                        cargoBadge = `<span class="cargo-badge diretor"><i class="fas fa-crown"></i> Diretor</span>`;
                    } else if (funcionario.cargo === 'Gerente') {
                        cargoBadge = `<span class="cargo-badge gerente"><i class="fas fa-user-tie"></i> Gerente</span>`;
                    }
                    
                    // Avatar padronizado com cor primária
                    const initials = getInitials(funcionario.nome);
                    const avatarHtml = funcionario.foto 
                        ? `<div class="table-avatar"><img src="${funcionario.foto}" alt="${funcionario.nome}"></div>`
                        : `<div class="table-avatar"><span>${initials}</span></div>`;
                    
                    const row = document.createElement('tr');
                    
                    // Adiciona evento de clique na linha
                    row.onclick = function(event) {
                        // Verifica se o clique foi em um botão de ação
                        if (!event.target.closest('.action-buttons-table')) {
                            visualizarFuncionario(funcionario.id);
                        }
                    };
                    
                    row.style.position = 'relative';
                    
                    let rowHtml = `
                        <td>${avatarHtml}</td>
                        <td>
                            <span class="fw-semibold">${funcionario.nome}</span>
                            <br>
                            <small class="text-muted">ID: ${funcionario.id}</small>
                        </td>
                        <td>${funcionario.email}</td>
                    `;
                    
                    if (escopoVisualizacao === 'TODOS') {
                        rowHtml += `<td>${funcionario.departamento_nome || '-'}</td>`;
                    }
                    
                    rowHtml += `
                        <td>${cargoBadge}</td>
                        <td>${statusBadge}</td>
                        <td>${formatarData(funcionario.criado_em)}</td>
                        <td>
                            <div class="action-buttons-table">
                                <button class="btn-icon view" onclick="event.stopPropagation(); visualizarFuncionario(${funcionario.id})" title="Visualizar">
                                    <i class="fas fa-eye"></i>
                                </button>
                    `;
                    
                    if (isAdmin && funcionario.id != usuarioLogadoId || userDepartamento == 15 || userDepartamento == 1) {
                        rowHtml += `
                                <button class="btn-icon simulate" onclick="event.stopPropagation(); simularFuncionario(${funcionario.id}, '${funcionario.nome.replace(/'/g, "\\'")}', '${funcionario.cargo}')" title="Simular">
                                    <i class="fas fa-user-secret"></i>
                                </button>
                        `;
                    }
                    
                    if (podeEditar) {
                        rowHtml += `
                                <button class="btn-icon edit" onclick="event.stopPropagation(); editarFuncionario(${funcionario.id})" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon delete" onclick="event.stopPropagation(); desativarFuncionario(${funcionario.id})" title="Desativar">
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
                            <span class="clickable-indicator">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        </td>
                    `;
                    
                    row.innerHTML = rowHtml;
                    tbody.appendChild(row);
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
                            
                            // Avatar com inicial
                            const initials = getInitials(funcionario.nome);
                            document.getElementById('avatarView').innerHTML = `<span>${initials}</span>`;
                            
                            // Informações do header
                            document.getElementById('nomeView').textContent = funcionario.nome;
                            document.getElementById('cargoView').textContent = funcionario.cargo || 'Sem cargo';
                            document.getElementById('departamentoView').textContent = funcionario.departamento_nome || 'Sem departamento';
                            
                            // Informações detalhadas
                            document.getElementById('emailView').textContent = funcionario.email;
                            document.getElementById('statusView').innerHTML = funcionario.ativo == 1 
                                ? '<span class="status-badge active"><i class="fas fa-check-circle"></i> Ativo</span>'
                                : '<span class="status-badge inactive"><i class="fas fa-times-circle"></i> Inativo</span>';
                            document.getElementById('cpfView').textContent = formatarCPF(funcionario.cpf);
                            document.getElementById('rgView').textContent = funcionario.rg || '-';
                            document.getElementById('dataCadastroView').textContent = formatarData(funcionario.criado_em);
                            document.getElementById('senhaAlteradaView').textContent = 
                                funcionario.senha_alterada_em ? formatarData(funcionario.senha_alterada_em) : 'Nunca alterada';
                            
                            // Guardar ID para edição
                            document.getElementById('modalVisualizacao').setAttribute('data-funcionario-id', id);
                            
                            // Abrir modal
                            document.getElementById('modalVisualizacao').classList.add('show');
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

            // Funções auxiliares
            function formatarCPF(cpf) {
                if (!cpf) return '-';
                cpf = cpf.toString().replace(/\D/g, '');
                if (cpf.length !== 11) return cpf;
                return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }

            function formatarData(dataStr) {
                if (!dataStr) return '-';
                
                try {
                    const data = new Date(dataStr);
                    return data.toLocaleDateString('pt-BR');
                } catch (e) {
                    return '-';
                }
            }

            // Exibe erro na tabela
            function exibirErroTabela(mensagem) {
                const tbody = document.getElementById('tableBody');
                const totalColunas = escopoVisualizacao === 'TODOS' ? 8 : 7;
                
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${totalColunas}" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                    <p class="text-danger mb-0">${mensagem}</p>
                                    <button class="btn btn-outline-primary btn-sm mt-3" onclick="recarregarDados()">
                                        <i class="fas fa-sync-alt"></i> Tentar Novamente
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                
                const paginationNav = document.getElementById('paginationNav');
                if (paginationNav) {
                    paginationNav.innerHTML = '';
                }
                
                atualizarInfoPaginacao(true);
            }

            // Funções de paginação
            function renderizarPaginacao() {
                const container = document.getElementById('paginationNav');
                if (!container) return;
                
                container.innerHTML = '';
                
                if (totalPaginas <= 1) return;
                
                // Primeira página
                container.appendChild(criarBotaoPaginacao(1, 'first', '<i class="fas fa-angle-double-left"></i>', paginaAtual === 1));
                
                // Página anterior
                container.appendChild(criarBotaoPaginacao(paginaAtual - 1, 'prev', '<i class="fas fa-angle-left"></i>', paginaAtual === 1));
                
                // Páginas numeradas
                const paginas = calcularPaginasVisiveis();
                paginas.forEach(item => {
                    if (item === '...') {
                        const li = document.createElement('li');
                        li.innerHTML = '<span class="pagination-ellipsis">...</span>';
                        container.appendChild(li);
                    } else {
                        container.appendChild(criarBotaoPaginacao(item, 'page', item.toString(), false, item === paginaAtual));
                    }
                });
                
                // Próxima página
                container.appendChild(criarBotaoPaginacao(paginaAtual + 1, 'next', '<i class="fas fa-angle-right"></i>', paginaAtual === totalPaginas));
                
                // Última página
                container.appendChild(criarBotaoPaginacao(totalPaginas, 'last', '<i class="fas fa-angle-double-right"></i>', paginaAtual === totalPaginas));
            }

            function criarBotaoPaginacao(pagina, tipo, texto, desabilitado = false, ativo = false) {
                const li = document.createElement('li');
                li.className = `page-item${ativo ? ' active' : ''}${desabilitado ? ' disabled' : ''}`;
                
                const a = document.createElement('a');
                a.className = 'page-link';
                a.innerHTML = texto;
                a.href = '#';
                
                if (!desabilitado) {
                    a.onclick = (e) => {
                        e.preventDefault();
                        if (pagina !== paginaAtual) {
                            irParaPagina(pagina);
                        }
                    };
                }
                
                li.appendChild(a);
                return li;
            }

            function calcularPaginasVisiveis() {
                const delta = 2;
                const range = [];
                const rangeWithDots = [];
                
                for (let i = Math.max(2, paginaAtual - delta); i <= Math.min(totalPaginas - 1, paginaAtual + delta); i++) {
                    range.push(i);
                }
                
                if (paginaAtual - delta > 2) {
                    rangeWithDots.push(1, '...');
                } else {
                    rangeWithDots.push(1);
                }
                
                rangeWithDots.push(...range);
                
                if (paginaAtual + delta < totalPaginas - 1) {
                    rangeWithDots.push('...', totalPaginas);
                } else if (totalPaginas > 1) {
                    rangeWithDots.push(totalPaginas);
                }
                
                return [...new Set(rangeWithDots)];
            }

            function irParaPagina(pagina) {
                if (pagina < 1 || pagina > totalPaginas || pagina === paginaAtual) {
                    return;
                }
                
                paginaAtual = pagina;
                carregarFuncionarios(false);
            }

            function alterarTamanhoPagina() {
                const select = document.getElementById('pageSize');
                const novoTamanho = parseInt(select.value);
                
                if (novoTamanho !== tamanhoPagina) {
                    tamanhoPagina = novoTamanho;
                    carregarFuncionarios(true);
                }
            }

            function atualizarInfoPaginacao(erro = false) {
                const showingStart = document.getElementById('showingStart');
                const showingEnd = document.getElementById('showingEnd');
                const totalRecordsEl = document.getElementById('totalRecords');
                
                if (erro || totalRegistros === 0) {
                    if (showingStart) showingStart.textContent = '0';
                    if (showingEnd) showingEnd.textContent = '0';
                    if (totalRecordsEl) totalRecordsEl.textContent = '0';
                    return;
                }
                
                const inicio = ((paginaAtual - 1) * tamanhoPagina) + 1;
                const fim = Math.min(paginaAtual * tamanhoPagina, totalRegistros);
                
                if (showingStart) showingStart.textContent = inicio.toString();
                if (showingEnd) showingEnd.textContent = fim.toString();
                if (totalRecordsEl) totalRecordsEl.textContent = totalRegistros.toString();
            }

            // Outras funções
            function aplicarFiltros() {
                console.log('🔍 Aplicando filtros...');
                carregarFuncionarios(true);
            }

            function limparFiltros() {
                const searchInput = document.getElementById('searchInput');
                const filterStatus = document.getElementById('filterStatus');
                const filterDepartamento = document.getElementById('filterDepartamento');
                const filterCargo = document.getElementById('filterCargo');
                
                if (searchInput) searchInput.value = '';
                if (filterStatus) filterStatus.value = '';
                if (filterDepartamento) filterDepartamento.value = '';
                if (filterCargo) filterCargo.value = '';
                
                carregarFuncionarios(true);
            }

            function recarregarDados() {
                carregarFuncionarios(false);
            }

            // Funções dos modais
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
                
                if (senha) {
                    senha.value = 'Assego@123';
                    senha.readOnly = true;
                }
                if (senhaInfo) senhaInfo.style.display = 'inline';
                if (senhaEditInfo) senhaEditInfo.style.display = 'none';
                
                if (modal) modal.classList.add('show');
            }

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
                            document.getElementById('cargo').value = funcionario.cargo || '';
                            document.getElementById('cpf').value = funcionario.cpf || '';
                            document.getElementById('rg').value = funcionario.rg || '';
                            document.getElementById('ativo').checked = funcionario.ativo == 1;
                            
                            document.getElementById('senha').value = '';
                            document.getElementById('senha').readOnly = false;
                            document.getElementById('senha').placeholder = 'Digite uma nova senha se desejar alterá-la';
                            document.getElementById('senhaInfo').style.display = 'none';
                            document.getElementById('senhaEditInfo').style.display = 'inline';
                            
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

            function salvarFuncionario(e) {
                e.preventDefault();
                
                const formData = new FormData(e.target);
                const dados = {};
                
                for (let [key, value] of formData.entries()) {
                    dados[key] = value;
                }
                
                dados.ativo = document.getElementById('ativo').checked ? 1 : 0;
                
                if (!dados.id) {
                    dados.senha = 'Assego@123';
                } else {
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
                                alert('Funcionário criado com sucesso!\n\nSenha padrão: Assego@123');
                            } else {
                                alert('Funcionário atualizado com sucesso!');
                            }
                            fecharModal();
                            carregarFuncionarios(false);
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

            function desativarFuncionario(id) {
                if (!podeEditar) {
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
                            carregarFuncionarios(false);
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

            function simularFuncionario(id, nome, cargo) {
                const confirmMsg = `🎭 SIMULAR: ${nome} (${cargo})\n\nVocê assumirá as permissões deste funcionário. Continuar?`;
                
                if (confirm(confirmMsg)) {
                    showLoading();
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="simular">
                        <input type="hidden" name="funcionario_id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function fecharModal() {
                const modal = document.getElementById('modalFuncionario');
                const form = document.getElementById('formFuncionario');
                
                if (modal) modal.classList.remove('show');
                if (form) form.reset();
            }

            function fecharModalVisualizacao() {
                document.getElementById('modalVisualizacao').classList.remove('show');
            }

            function editarDoVisualizacao() {
                const id = document.getElementById('modalVisualizacao').getAttribute('data-funcionario-id');
                fecharModalVisualizacao();
                setTimeout(() => {
                    editarFuncionario(id);
                }, 300);
            }

            // Fecha modais ao clicar fora
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('modalFuncionario');
                const modalView = document.getElementById('modalVisualizacao');
                
                if (event.target === modal) {
                    fecharModal();
                }
                if (event.target === modalView) {
                    fecharModalVisualizacao();
                }
            });

            // Tecla ESC fecha modais
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