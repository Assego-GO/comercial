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
                                    <span id="modalId">Matrícula: -</span>
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
                <!-- NOVA ABA DE OBSERVAÇÕES -->
                <button class="tab-button" onclick="abrirTab('observacoes')">
                    <i class="fas fa-sticky-note"></i>
                    Observações
                    <span class="tab-indicator"></span>
                    <span class="observacoes-count-badge" id="observacoesCountBadge" style="display: none;">0</span>
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

                <!-- NOVA ABA: Observações Tab -->
                <div id="observacoes-tab" class="tab-content">
                    <!-- Header da Aba de Observações -->
                    <div class="observacoes-header">
                        <div class="observacoes-header-content">
                            <div class="observacoes-header-info">
                                <div class="observacoes-icon">
                                    <i class="fas fa-sticky-note"></i>
                                </div>
                                <div>
                                    <h4 class="observacoes-title">Observações do Associado</h4>
                                    <p class="observacoes-subtitle">Histórico de anotações e observações importantes</p>
                                </div>
                            </div>
                            <button class="btn-add-observacao" onclick="abrirModalNovaObservacao()">
                                <i class="fas fa-plus-circle"></i>
                                Nova Observação
                            </button>
                        </div>
                    </div>

                    <!-- Filtros e Busca -->
                    <div class="observacoes-filters">
                        <div class="observacoes-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchObservacoes" placeholder="Buscar nas observações..." class="observacoes-search-input">
                        </div>
                        <div class="observacoes-filter-buttons">
                            <button class="filter-btn active" data-filter="all">
                                <i class="fas fa-list"></i>
                                Todas
                            </button>
                            <button class="filter-btn" data-filter="recent">
                                <i class="fas fa-clock"></i>
                                Recentes
                            </button>
                            <button class="filter-btn" data-filter="important">
                                <i class="fas fa-star"></i>
                                Importantes
                            </button>
                        </div>
                    </div>

                    <!-- Container de Observações -->
                    <div class="observacoes-container" id="observacoesContainer">
                        <!-- As observações serão carregadas dinamicamente aqui -->
                        
                        <!-- Template de uma observação (exemplo) -->
                        <div class="observacao-card">
                            <div class="observacao-header">
                                <div class="observacao-meta">
                                    <div class="observacao-author">
                                        <div class="author-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="author-info">
                                            <span class="author-name">João Silva</span>
                                            <span class="author-role">Secretaria</span>
                                        </div>
                                    </div>
                                    <div class="observacao-actions">
                                        <button class="btn-observacao-action" title="Marcar como importante">
                                            <i class="far fa-star"></i>
                                        </button>
                                        <button class="btn-observacao-action" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-observacao-action delete" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="observacao-date">
                                    <i class="far fa-calendar"></i>
                                    12/08/2025 às 14:30
                                </div>
                            </div>
                            <div class="observacao-content">
                                <p>Esta é uma observação de exemplo. O associado compareceu para atualização cadastral e apresentou todos os documentos necessários.</p>
                            </div>
                            <div class="observacao-tags">
                                <span class="tag tag-info">Atualização</span>
                                <span class="tag tag-success">Documentos OK</span>
                            </div>
                        </div>

                        <!-- Estado vazio -->
                        <div class="empty-observacoes-state" style="display: none;">
                            <div class="empty-observacoes-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h5>Nenhuma observação registrada</h5>
                            <p>Ainda não há observações para este associado.</p>
                            <button class="btn-modern btn-primary" onclick="abrirModalNovaObservacao()">
                                <i class="fas fa-plus"></i>
                                Adicionar Primeira Observação
                            </button>
                        </div>
                    </div>

                    <!-- Paginação das Observações -->
                    <div class="observacoes-pagination">
                        <div class="pagination-info">
                            Mostrando <span id="observacoesShowing">1-5</span> de <span id="observacoesTotal">12</span> observações
                        </div>
                        <div class="pagination-controls">
                            <button class="pagination-btn" id="prevObservacoes">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <span class="page-number">1</span>
                            <button class="pagination-btn" id="nextObservacoes">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Nova Observação -->
    <div class="modal fade" id="modalNovaObservacao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header observacao-modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Nova Observação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovaObservacao">
                        <div class="mb-4">
                            <label for="observacaoTexto" class="form-label">
                                <i class="fas fa-pen me-1"></i>
                                Observação
                            </label>
                            <textarea class="form-control observacao-textarea" id="observacaoTexto" rows="6" 
                                placeholder="Digite aqui a observação sobre o associado..." required></textarea>
                            <div class="form-text">Seja claro e objetivo em suas anotações.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-tag me-1"></i>
                                        Categoria
                                    </label>
                                    <select class="form-select" id="observacaoCategoria">
                                        <option value="geral">Geral</option>
                                        <option value="financeiro">Financeiro</option>
                                        <option value="documentacao">Documentação</option>
                                        <option value="atendimento">Atendimento</option>
                                        <option value="pendencia">Pendência</option>
                                        <option value="importante">Importante</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-exclamation-circle me-1"></i>
                                        Prioridade
                                    </label>
                                    <select class="form-select" id="observacaoPrioridade">
                                        <option value="baixa">Baixa</option>
                                        <option value="media" selected>Média</option>
                                        <option value="alta">Alta</option>
                                        <option value="urgente">Urgente</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="observacaoImportante">
                                <label class="form-check-label" for="observacaoImportante">
                                    <i class="fas fa-star text-warning me-1"></i>
                                    Marcar como observação importante
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-info alert-observacao">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Esta observação será registrada com seu nome e data/hora atual.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="salvarObservacao()">
                        <i class="fas fa-save me-1"></i>
                        Salvar Observação
                    </button>
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

        // Funções para a aba de observações
        function abrirModalNovaObservacao() {
            const modal = new bootstrap.Modal(document.getElementById('modalNovaObservacao'));
            modal.show();
        }

        function salvarObservacao() {
            // Esta função será implementada no JavaScript
            console.log('Salvando observação...');
        }

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
    </script>


    <script src="js/dashboard.js"></script>



<script>
/**
 * Função para recalcular valores dos serviços
 */
function recalcularServicos() {
    // Confirmação
    if (!confirm(
        'ATENÇÃO!\n\n' +
        'Esta ação irá recalcular TODOS os valores dos serviços dos associados ' +
        'baseado nos valores base atuais do sistema.\n\n' +
        'Isso pode alterar centenas de registros!\n\n' +
        'Deseja continuar?'
    )) {
        return;
    }

    const btnRecalcular = document.getElementById('btnRecalcular');
    const originalText = btnRecalcular.innerHTML;
    
    // Desabilita botão e mostra loading
    btnRecalcular.disabled = true;
    btnRecalcular.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recalculando...';
    
    // Mostra loading geral
    showLoading();
    
    console.log('Iniciando recálculo dos serviços...');
    
    fetch('../api/recalcular_servicos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(responseText => {
        console.log('Response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Erro ao fazer parse JSON:', e);
            throw new Error('Resposta inválida do servidor');
        }
        
        if (data.status === 'success') {
            console.log('✓ Recálculo concluído:', data.data);
            
            // Monta mensagem detalhada
            let mensagem = data.message;
            
            if (data.data.total_valores_alterados > 0) {
                mensagem += '\n\n📊 RESUMO:';
                mensagem += '\n• Total processados: ' + data.data.total_servicos_processados;
                mensagem += '\n• Valores alterados: ' + data.data.total_valores_alterados;
                mensagem += '\n• Sem alteração: ' + data.data.total_sem_alteracao;
                
                if (data.data.economia_total !== 0) {
                    const economia = data.data.economia_total;
                    if (economia > 0) {
                        mensagem += '\n• Aumento total: +R$ ' + economia.toFixed(2).replace('.', ',');
                    } else {
                        mensagem += '\n• Redução total: R$ ' + Math.abs(economia).toFixed(2).replace('.', ',');
                    }
                }
                
                mensagem += '\n\n🕒 Processado em: ' + data.data.data_recalculo;
                
                // Mostra alguns exemplos
                if (data.data.alteracoes_detalhadas && data.data.alteracoes_detalhadas.length > 0) {
                    mensagem += '\n\n📋 EXEMPLOS DE ALTERAÇÕES:';
                    data.data.alteracoes_detalhadas.slice(0, 5).forEach(alt => {
                        mensagem += `\n• ${alt.associado} (${alt.servico}): R$ ${alt.valor_anterior.toFixed(2)} → R$ ${alt.valor_novo.toFixed(2)}`;
                    });
                    
                    if (data.data.alteracoes_detalhadas.length > 5) {
                        mensagem += `\n... e mais ${data.data.alteracoes_detalhadas.length - 5} alterações`;
                    }
                }
            }
            
            alert(mensagem);
            
            // Recarrega a página para atualizar os dados
            setTimeout(() => {
                window.location.reload();
            }, 2000);
            
        } else {
            console.error('Erro na API:', data);
            alert('❌ ERRO: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro de rede:', error);
        alert('❌ Erro de comunicação: ' + error.message);
    })
    .finally(() => {
        // Restaura botão
        btnRecalcular.disabled = false;
        btnRecalcular.innerHTML = originalText;
        hideLoading();
    });
}

// Adiciona CSS para o botão (coloque no <head> ou arquivo CSS)
const style = document.createElement('style');
style.textContent = `
.btn-modern.btn-warning {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
    transition: all 0.3s ease;
}

.btn-modern.btn-warning:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
    background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
}

.btn-modern:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}
`;
document.head.appendChild(style);
</script>


</body>

</html>