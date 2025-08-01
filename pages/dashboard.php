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

// NOVO: Include do componente Header
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

// DEBUG USUÁRIO LOGADO - CONSOLE (REMOVER APÓS TESTE)
echo "<script>";
echo "console.log('=== DEBUG USUÁRIO LOGADO ===');";
echo "console.log('Array completo:', " . json_encode($usuarioLogado) . ");";
echo "console.log('Tem departamento_id?', " . (isset($usuarioLogado['departamento_id']) ? 'true' : 'false') . ");";
if (isset($usuarioLogado['departamento_id'])) {
    echo "console.log('Departamento ID valor:', " . json_encode($usuarioLogado['departamento_id']) . ");";
    echo "console.log('Departamento ID tipo:', '" . gettype($usuarioLogado['departamento_id']) . "');";
    echo "console.log('É igual a 1?', " . ($usuarioLogado['departamento_id'] == 1 ? 'true' : 'false') . ");";
    echo "console.log('É idêntico a 1?', " . ($usuarioLogado['departamento_id'] === 1 ? 'true' : 'false') . ");";
    echo "console.log('É idêntico a \"1\"?', " . ($usuarioLogado['departamento_id'] === '1' ? 'true' : 'false') . ");";
}
echo "console.log('isDiretor:', " . ($auth->isDiretor() ? 'true' : 'false') . ");";
echo "console.log('=============================');";
echo "</script>";

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

// CORREÇÃO: Cria instância do Header Component - Passa TODO o array do usuário
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado, // ← CORRIGIDO: Agora passa TODO o array (incluindo departamento_id)
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'associados',
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./estilizacao/style.css">

</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando dados...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        
        <!-- NOVO: Header Component -->
        <?php $headerComponent->render(); ?>


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

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <!-- JavaScript customizado para os botões do header -->
    <script>
        function toggleSearch() {
            // Implementar funcionalidade de busca global
            console.log('Busca global ativada');
            // Você pode focar no campo de busca da tabela ou abrir um modal de busca
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        function toggleNotifications() {
            // Implementar painel de notificações
            console.log('Painel de notificações');
            alert('Painel de notificações em desenvolvimento');
        }
    </script>

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
        let carregamentoCompleto = false;
        let imagensCarregadas = new Set();

        // Variáveis de paginação
        let paginaAtual = 1;
        let registrosPorPagina = 25;
        let totalPaginas = 1;

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
                    const fotoUrl = associado.foto
                    ? `../${associado.foto}`
                    : `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;

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
                const fotoUrl = associado.foto
                    ? `../${associado.foto}`
                    : `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;

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

        // FUNÇÃO CORRIGIDA: Preenche tab Documentos com filtro por associado
        function preencherTabDocumentos(associado) {
            const documentosTab = document.getElementById('documentos-tab');

            // Mostra loading enquanto carrega
            documentosTab.innerHTML = `
                <div class="detail-section">
                    <div class="text-center py-5">
                        <div class="loading-spinner mb-3"></div>
                        <p class="text-muted">Carregando documentos...</p>
                    </div>
                </div>
            `;

            // Primeiro busca no array de dados carregados para contar total de documentos
            const totalDocumentos = todosAssociados.find(a => a.id == associado.id)?.total_documentos || 0;

            // Busca documentos do associado em fluxo - CORRIGIDO: busca TODOS os documentos
            $.ajax({
                url: '../api/documentos/documentos_fluxo_listar.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Resposta completa da API:', response);
                    console.log('Buscando documentos para associado ID:', associado.id);
                    
                    if (response.status === 'success' && response.data && Array.isArray(response.data)) {
                        // IMPORTANTE: Filtra apenas documentos do associado específico
                        const documentosDoAssociado = response.data.filter(doc => {
                            // Converte para string para comparação segura
                            return String(doc.associado_id) === String(associado.id);
                        });
                        
                        console.log(`Documentos encontrados para o associado: ${documentosDoAssociado.length} de ${response.data.length} total`);
                        
                        if (documentosDoAssociado.length > 0) {
                            renderizarDocumentosNoModal(documentosDoAssociado, documentosTab);
                        } else {
                            // Nenhum documento deste associado em fluxo
                            documentosTab.innerHTML = `
                                <div class="empty-documents-state">
                                    <i class="fas fa-folder-open"></i>
                                    <h5>Nenhum documento em fluxo de assinatura</h5>
                                    <p>${associado.nome} ${totalDocumentos > 0 ? `possui ${totalDocumentos} documento(s) no sistema, mas nenhum está em processo de assinatura` : 'não possui documentos anexados'}</p>
                                    ${associado.pre_cadastro == 1 ? 
                                        '<small class="text-muted">Este é um pré-cadastro. Os documentos serão anexados durante o processo</small>' : 
                                        '<small class="text-muted">Entre em contato com o setor comercial para mais informações</small>'
                                    }
                                </div>
                            `;
                        }
                    } else {
                        // Sem documentos ou erro na estrutura
                        documentosTab.innerHTML = `
                            <div class="empty-documents-state">
                                <i class="fas fa-folder-open"></i>
                                <h5>Nenhum documento encontrado</h5>
                                <p>${associado.nome} ainda não possui documentos em fluxo de assinatura</p>
                                ${associado.pre_cadastro == 1 ? 
                                    '<small class="text-muted">Este é um pré-cadastro aguardando documentação</small>' : 
                                    '<small class="text-muted">Os documentos podem estar sendo processados</small>'
                                }
                            </div>
                        `;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro ao buscar documentos:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    
                    documentosTab.innerHTML = `
                        <div class="empty-documents-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5>Erro ao carregar documentos</h5>
                            <p>Não foi possível carregar os documentos de ${associado.nome}</p>
                            <small class="text-muted">Por favor, tente novamente mais tarde</small>
                        </div>
                    `;
                }
            });
        }

        // FUNÇÃO ATUALIZADA: Renderizar documentos no modal com validação extra
        function renderizarDocumentosNoModal(documentos, container) {
            let html = '<div class="document-flow-container">';

            // Adiciona contador de documentos
            html += `
                <div class="document-count-info" style="margin-bottom: 1rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                    <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 0.5rem;"></i>
                    <span style="font-size: 0.875rem; color: var(--gray-600);">
                        ${documentos.length} documento${documentos.length > 1 ? 's' : ''} em fluxo de assinatura
                    </span>
                </div>
            `;

            documentos.forEach(doc => {
                const statusClass = doc.status_fluxo.toLowerCase().replace('_', '-');
                
                html += `
                    <div class="document-flow-card">
                        <span class="status-badge-modal ${statusClass}">
                            <i class="fas fa-${getStatusIcon(doc.status_fluxo)} me-1"></i>
                            ${doc.status_descricao}
                        </span>
                        
                        <div class="document-flow-header">
                            <div class="document-flow-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-flow-info">
                                <h6>Ficha de Filiação</h6>
                                <p>${doc.tipo_origem === 'VIRTUAL' ? 'Gerada no Sistema' : 'Digitalizada'}</p>
                            </div>
                        </div>
                        
                        <div class="document-meta-modal">
                            <div class="meta-item-modal">
                                <i class="fas fa-calendar"></i>
                                <span>Cadastrado em ${formatarDataDocumento(doc.data_upload)}</span>
                            </div>
                            ${doc.departamento_atual_nome ? `
                                <div class="meta-item-modal">
                                    <i class="fas fa-building"></i>
                                    <span>${doc.departamento_atual_nome}</span>
                                </div>
                            ` : ''}
                            ${doc.dias_em_processo > 0 ? `
                                <div class="meta-item-modal">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span>${doc.dias_em_processo} dia${doc.dias_em_processo > 1 ? 's' : ''} em processo</span>
                                </div>
                            ` : ''}
                            ${doc.funcionario_upload ? `
                                <div class="meta-item-modal">
                                    <i class="fas fa-user"></i>
                                    <span>Por: ${doc.funcionario_upload}</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Progress do Fluxo -->
                        <div class="fluxo-progress-modal">
                            <div class="fluxo-steps-modal">
                                <div class="fluxo-step-modal ${doc.status_fluxo !== 'DIGITALIZADO' ? 'completed' : 'active'}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Digitalizado</div>
                                    <div class="fluxo-line-modal"></div>
                                </div>
                                <div class="fluxo-step-modal ${doc.status_fluxo === 'AGUARDANDO_ASSINATURA' ? 'active' : (doc.status_fluxo === 'ASSINADO' || doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-signature"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Assinatura</div>
                                    <div class="fluxo-line-modal"></div>
                                </div>
                                <div class="fluxo-step-modal ${doc.status_fluxo === 'ASSINADO' ? 'active' : (doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Assinado</div>
                                    <div class="fluxo-line-modal"></div>
                                </div>
                                <div class="fluxo-step-modal ${doc.status_fluxo === 'FINALIZADO' ? 'completed' : ''}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Finalizado</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações adicionais baseadas no status -->
                        ${renderizarInfoAdicional(doc)}
                        
                        <div class="document-actions-modal">
                            <button class="btn-modern btn-primary btn-sm" onclick="downloadDocumentoModal(${doc.id})">
                                <i class="fas fa-download"></i>
                                Baixar
                            </button>
                            
                            ${getAcoesFluxoModal(doc)}
                            
                            <button class="btn-modern btn-secondary btn-sm" onclick="verHistoricoModal(${doc.id})">
                                <i class="fas fa-history"></i>
                                Histórico
                            </button>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        // NOVA FUNÇÃO: Renderizar informações adicionais baseadas no status
        function renderizarInfoAdicional(doc) {
            let html = '';
            
            switch(doc.status_fluxo) {
                case 'DIGITALIZADO':
                    html = `
                        <div class="alert-info-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(0, 123, 255, 0.1); border-radius: 8px;">
                            <i class="fas fa-info-circle" style="color: var(--info);"></i>
                            <span style="font-size: 0.8125rem;">Documento aguardando envio para assinatura</span>
                        </div>
                    `;
                    break;
                    
                case 'AGUARDANDO_ASSINATURA':
                    html = `
                        <div class="alert-warning-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(255, 193, 7, 0.1); border-radius: 8px;">
                            <i class="fas fa-clock" style="color: var(--warning);"></i>
                            <span style="font-size: 0.8125rem;">Documento na presidência aguardando assinatura</span>
                        </div>
                    `;
                    break;
                    
                case 'ASSINADO':
                    html = `
                        <div class="alert-success-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(40, 167, 69, 0.1); border-radius: 8px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            <span style="font-size: 0.8125rem;">Documento assinado e retornado ao comercial</span>
                        </div>
                    `;
                    break;
                    
                case 'FINALIZADO':
                    html = `
                        <div class="alert-primary-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(0, 86, 210, 0.1); border-radius: 8px;">
                            <i class="fas fa-flag-checkered" style="color: var(--primary);"></i>
                            <span style="font-size: 0.8125rem;">Processo concluído com sucesso</span>
                        </div>
                    `;
                    break;
            }
            
            return html;
        }

        // NOVA FUNÇÃO: Obter ícone do status
        function getStatusIcon(status) {
            const icons = {
                'DIGITALIZADO': 'upload',
                'AGUARDANDO_ASSINATURA': 'clock',
                'ASSINADO': 'check',
                'FINALIZADO': 'flag-checkered'
            };
            return icons[status] || 'file';
        }

        // NOVA FUNÇÃO: Obter ações do fluxo para o modal
        function getAcoesFluxoModal(doc) {
            let acoes = '';

            switch (doc.status_fluxo) {
                case 'DIGITALIZADO':
                    acoes = `
                        <button class="btn-modern btn-warning btn-sm" onclick="enviarParaAssinaturaModal(${doc.id})" title="Enviar para Assinatura">
                            <i class="fas fa-paper-plane"></i>
                            Enviar
                        </button>
                    `;
                    break;

                case 'AGUARDANDO_ASSINATURA':
                    // Verificar se usuário tem permissão para assinar (apenas presidência)
                    <?php if ($auth->isDiretor() || $usuarioLogado['departamento_id'] == 2): ?>
                        acoes = `
                        <button class="btn-modern btn-success btn-sm" onclick="abrirModalAssinaturaModal(${doc.id})" title="Assinar">
                            <i class="fas fa-signature"></i>
                            Assinar
                        </button>
                    `;
                    <?php endif; ?>
                    break;

                case 'ASSINADO':
                    acoes = `
                        <button class="btn-modern btn-info btn-sm" onclick="finalizarProcessoModal(${doc.id})" title="Finalizar">
                            <i class="fas fa-flag-checkered"></i>
                            Finalizar
                        </button>
                    `;
                    break;
            }

            return acoes;
        }

        // NOVA FUNÇÃO: Formatar data para documentos
        function formatarDataDocumento(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // NOVA FUNÇÃO: Download documento no modal
        function downloadDocumentoModal(id) {
            window.open('../api/documentos/documentos_download.php?id=' + id, '_blank');
        }

        // FUNÇÃO ATUALIZADA: Ver histórico no modal com mais detalhes
        function verHistoricoModal(documentoId) {
            // Criar um modal secundário para o histórico
            const historicoHtml = `
                <div class="modal fade" id="historicoDocumentoModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-history me-2" style="color: var(--primary);"></i>
                                    Histórico do Documento
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="historicoDocumentoContent">
                                    <div class="text-center py-5">
                                        <div class="loading-spinner mb-3"></div>
                                        <p class="text-muted">Carregando histórico...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                                    Fechar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove modal anterior se existir
            $('#historicoDocumentoModal').remove();
            
            // Adiciona o novo modal ao body
            $('body').append(historicoHtml);
            
            // Abre o modal
            const modalHistorico = new bootstrap.Modal(document.getElementById('historicoDocumentoModal'));
            modalHistorico.show();
            
            // Busca o histórico
            $.get('../api/documentos/documentos_historico_fluxo.php', { documento_id: documentoId }, function(response) {
                if (response.status === 'success' && response.data) {
                    renderizarHistoricoNoModal(response.data);
                } else {
                    $('#historicoDocumentoContent').html(`
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Não foi possível carregar o histórico do documento
                        </div>
                    `);
                }
            }).fail(function() {
                $('#historicoDocumentoContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        Erro ao carregar histórico
                    </div>
                `);
            });
        }

        // NOVA FUNÇÃO: Renderizar histórico no modal
        function renderizarHistoricoNoModal(historico) {
            if (!historico || historico.length === 0) {
                $('#historicoDocumentoContent').html(`
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum histórico disponível para este documento
                    </div>
                `);
                return;
            }
            
            let html = '<div class="timeline">';
            
            historico.forEach((item, index) => {
                const isLast = index === historico.length - 1;
                html += `
                    <div class="timeline-item ${isLast ? 'last' : ''}">
                        <div class="timeline-marker">
                            <i class="fas fa-${getIconForStatus(item.status_novo)}"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">${getStatusLabel(item.status_novo)}</h6>
                                <span class="timeline-date">${formatarDataDocumento(item.data_acao)}</span>
                            </div>
                            <p class="timeline-description">${item.observacao || 'Sem observações'}</p>
                            <div class="timeline-meta">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i> ${item.funcionario_nome || 'Sistema'}
                                    ${item.dept_origem_nome ? `<br><i class="fas fa-building me-1"></i> De: ${item.dept_origem_nome}` : ''}
                                    ${item.dept_destino_nome ? ` → Para: ${item.dept_destino_nome}` : ''}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $('#historicoDocumentoContent').html(html);
        }

        // FUNÇÃO AUXILIAR: Obter ícone para status
        function getIconForStatus(status) {
            const icons = {
                'DIGITALIZADO': 'fa-upload',
                'AGUARDANDO_ASSINATURA': 'fa-clock',
                'ENVIADO_PRESIDENCIA': 'fa-paper-plane',
                'ASSINADO': 'fa-signature',
                'FINALIZADO': 'fa-flag-checkered'
            };
            return icons[status] || 'fa-circle';
        }

        // FUNÇÃO AUXILIAR: Obter label para status
        function getStatusLabel(status) {
            const labels = {
                'DIGITALIZADO': 'Documento Digitalizado',
                'AGUARDANDO_ASSINATURA': 'Enviado para Assinatura',
                'ENVIADO_PRESIDENCIA': 'Na Presidência',
                'ASSINADO': 'Documento Assinado',
                'FINALIZADO': 'Processo Finalizado'
            };
            return labels[status] || status;
        }

        // NOVA FUNÇÃO: Enviar para assinatura no modal
        function enviarParaAssinaturaModal(documentoId) {
            if (confirm('Deseja enviar este documento para assinatura na presidência?')) {
                $.ajax({
                    url: '../api/documentos/documentos_enviar_assinatura.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Documento enviado para assinatura via modal'
                    }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Documento enviado para assinatura com sucesso!');
                            // Recarrega a tab de documentos
                            const associadoId = document.getElementById('modalId').textContent.replace('ID: ', '');
                            const associado = todosAssociados.find(a => a.id == associadoId);
                            if (associado) {
                                preencherTabDocumentos(associado);
                            }
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao enviar documento para assinatura');
                    }
                });
            }
        }

        // NOVA FUNÇÃO: Finalizar processo no modal
        function finalizarProcessoModal(documentoId) {
            if (confirm('Deseja finalizar o processo deste documento?')) {
                $.ajax({
                    url: '../api/documentos/documentos_finalizar.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Processo finalizado via modal'
                    }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Processo finalizado com sucesso!');
                            // Recarrega a tab de documentos
                            const associadoId = document.getElementById('modalId').textContent.replace('ID: ', '');
                            const associado = todosAssociados.find(a => a.id == associadoId);
                            if (associado) {
                                preencherTabDocumentos(associado);
                            }
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao finalizar processo');
                    }
                });
            }
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
            event.stopPropagation();
            window.location.href = `cadastroForm.php?id=${id}`;
        }

        // Função para excluir associado
        function excluirAssociado(id) {
            console.log('Excluindo associado ID:', id);
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

        console.log('Sistema inicializado com Header Component e Fluxo de Documentos!');
    </script>

</body>

</html>