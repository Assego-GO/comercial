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

// Define o título da página
$page_title = 'Associados - ASSEGO';

// CORREÇÃO: Cria instância do Header Component - Passa TODO o array do usuário
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
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
             <?php include 'components/simulation-banner.php'; ?>

            <?php
// Apenas a parte que precisa ser modificada no dashboard.php

// ANTES da seção Stats Grid, adicionar:
$departamentoComercialId = 10; // AJUSTE CONFORME SEU BANCO
$departamentoPresidenciaId = 1; // AJUSTE CONFORME SEU BANCO

$podeVerKPIs = $auth->isDiretor() || 
               (isset($usuarioLogado['departamento_id']) && 
                $usuarioLogado['departamento_id'] == $departamentoComercialId || $usuarioLogado['departamento_id'] == $departamentoPresidenciaId);

if ($podeVerKPIs): ?>
    <!-- Stats Grid -->
    <div class="stats-grid" data-aos="fade-up">
        <!-- Card 1: Associados Ativos + Novos - COM GRÁFICOS PIZZA 3 FATIAS -->
        <div class="stat-card dual-stat-card associados-pie">
            <div class="dual-stat-header">
                <div class="dual-stat-title">
                    <i class="fas fa-users"></i>
                    Associados
                </div>
                <div class="dual-stat-percentage" id="associadosPercent">
                    <i class="fas fa-chart-line"></i>
                    Crescimento
                </div>
            </div>
            <div class="dual-stats-row vertical-layout">
                <div class="dual-stat-item ativos-item">
                    <div class="dual-stat-icon ativos-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="dual-stat-info">
                        <div class="dual-stat-value" id="associadosAtivos">-</div>
                        <div class="dual-stat-label">Ativos</div>
                    </div>
                    <!-- Gráfico Pizza para Ativos com 3 categorias -->
                    <div class="pie-chart-container">
                        <svg class="pie-chart" width="120" height="120" viewBox="0 0 42 42">
                            <circle class="pie-background" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#e5e7eb" stroke-width="3"></circle>
                            <circle class="pie-ativa" id="ativosPieAtiva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#00c853" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-reserva" id="ativosPieReserva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#ff9500" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-pensionista" id="ativosPiePensionista" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#8b5cf6" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                        </svg>
                        <div class="pie-legend">
                            <div class="legend-item">
                                <span class="color-dot ativa"></span>
                                <span id="associadosAtiva">-</span> Ativa
                            </div>
                            <div class="legend-item">
                                <span class="color-dot reserva"></span>
                                <span id="associadosReserva">-</span> Reserva
                            </div>
                            <div class="legend-item">
                                <span class="color-dot pensionista"></span>
                                <span id="associadosPensionista">-</span> Pensionista
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dual-stats-separator"></div>
                <div class="dual-stat-item novos-item">
                    <div class="dual-stat-icon novos-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="dual-stat-info">
                        <div class="dual-stat-value" id="novosAssociados">-</div>
                        <div class="dual-stat-label">Novos (30d)</div>
                    </div>
                    <!-- Gráfico Pizza para Novos com 3 categorias -->
                    <div class="pie-chart-container">
                        <svg class="pie-chart" width="120" height="120" viewBox="0 0 42 42">
                            <circle class="pie-background" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#e5e7eb" stroke-width="3"></circle>
                            <circle class="pie-ativa" id="novosPieAtiva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#00c853" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-reserva" id="novosPieReserva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#ff9500" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-pensionista" id="novosPiePensionista" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#8b5cf6" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                        </svg>
                        <div class="pie-legend">
                            <div class="legend-item">
                                <span class="color-dot ativa"></span>
                                <span id="novosAtiva">-</span> Ativa
                            </div>
                            <div class="legend-item">
                                <span class="color-dot reserva"></span>
                                <span id="novosReserva">-</span> Reserva
                            </div>
                            <div class="legend-item">
                                <span class="color-dot pensionista"></span>
                                <span id="novosPensionista">-</span> Pensionista
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: PM + BM + Outros - COM GRÁFICO PIZZA NO HOVER 3 FATIAS -->
        <div class="stat-card dual-stat-card triple-stat-card corporacoes-pie">
            <div class="dual-stat-header">
                <div class="dual-stat-title">
                    <i class="fas fa-shield-alt"></i>
                    Corporações
                </div>
                <div class="dual-stat-percentage" id="corporacoesPercent">
                    <i class="fas fa-chart-pie"></i>
                    -% do total
                </div>
            </div>
            <div class="dual-stats-row triple-stats-row">
                <div class="dual-stat-item pm-item">
                    <div class="dual-stat-icon pm-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="dual-stat-info">
                        <div class="dual-stat-value" id="pmQuantidade">-</div>
                        <div class="dual-stat-label">PM</div>
                    </div>
                    <!-- Gráfico Pizza -->
                    <div class="pie-chart-container">
                        <svg class="pie-chart" width="120" height="120" viewBox="0 0 42 42">
                            <circle class="pie-background" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#e5e7eb" stroke-width="3"></circle>
                            <circle class="pie-ativa" id="pmPieAtiva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#00c853" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-reserva" id="pmPieReserva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#ff9500" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-pensionista" id="pmPiePensionista" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#8b5cf6" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                        </svg>
                        <div class="pie-legend">
                            <div class="legend-item">
                                <span class="color-dot ativa"></span>
                                <span id="pmAtiva">-</span> Ativa
                            </div>
                            <div class="legend-item">
                                <span class="color-dot reserva"></span>
                                <span id="pmReserva">-</span> Reserva
                            </div>
                            <div class="legend-item">
                                <span class="color-dot pensionista"></span>
                                <span id="pmPensionista">-</span> Pensionista
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dual-stats-separator"></div>
                <div class="dual-stat-item bm-item">
                    <div class="dual-stat-icon bm-icon">
                        <i class="fas fa-fire-extinguisher"></i>
                    </div>
                    <div class="dual-stat-info">
                        <div class="dual-stat-value" id="bmQuantidade">-</div>
                        <div class="dual-stat-label">BM</div>
                    </div>
                    <!-- Gráfico Pizza -->
                    <div class="pie-chart-container">
                        <svg class="pie-chart" width="120" height="120" viewBox="0 0 42 42">
                            <circle class="pie-background" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#e5e7eb" stroke-width="3"></circle>
                            <circle class="pie-ativa" id="bmPieAtiva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#00c853" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-reserva" id="bmPieReserva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#ff9500" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-pensionista" id="bmPiePensionista" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#8b5cf6" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                        </svg>
                        <div class="pie-legend">
                            <div class="legend-item">
                                <span class="color-dot ativa"></span>
                                <span id="bmAtiva">-</span> Ativa
                            </div>
                            <div class="legend-item">
                                <span class="color-dot reserva"></span>
                                <span id="bmReserva">-</span> Reserva
                            </div>
                            <div class="legend-item">
                                <span class="color-dot pensionista"></span>
                                <span id="bmPensionista">-</span> Pensionista
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dual-stats-separator"></div>
                <div class="dual-stat-item outros-item">
                    <div class="dual-stat-icon outros-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="dual-stat-info">
                        <div class="dual-stat-value" id="outrosQuantidade">-</div>
                        <div class="dual-stat-label">Outros</div>
                    </div>
                    <!-- Gráfico Pizza para Outros -->
                    <div class="pie-chart-container">
                        <svg class="pie-chart" width="120" height="120" viewBox="0 0 42 42">
                            <circle class="pie-background" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#e5e7eb" stroke-width="3"></circle>
                            <circle class="pie-ativa" id="outrosPieAtiva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#00c853" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-reserva" id="outrosPieReserva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#ff9500" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-pensionista" id="outrosPiePensionista" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#8b5cf6" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                        </svg>
                        <div class="pie-legend">
                            <div class="legend-item">
                                <span class="color-dot ativa"></span>
                                <span id="outrosAtiva">-</span> Ativa
                            </div>
                            <div class="legend-item">
                                <span class="color-dot reserva"></span>
                                <span id="outrosReserva">-</span> Reserva
                            </div>
                            <div class="legend-item">
                                <span class="color-dot pensionista"></span>
                                <span id="outrosPensionista">-</span> Pensionista
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Capital/Interior - COM GRÁFICOS PIZZA 3 FATIAS -->
        <div class="stat-card dual-stat-card distribuicao-pie">
            <div class="dual-stat-header">
                <div class="dual-stat-title">
                    <i class="fas fa-map-marked-alt"></i>
                    Distribuição
                </div>
                <div class="dual-stat-percentage" id="localizacaoPercent">
                    <i class="fas fa-percentage"></i>
                    <span id="totalLocalizacao">-</span> Mapeados
                </div>
            </div>
            <div class="dual-stats-row vertical-layout">
                <div class="dual-stat-item capital-item">
                    <div class="dual-stat-icon capital-icon">
                        <i class="fas fa-city"></i>
                    </div>
                    <div class="dual-stat-info">
                        <div class="dual-stat-value" id="capitalQuantidade">-</div>
                        <div class="dual-stat-label">Capital (Goiânia)</div>
                        <div class="dual-stat-extra">
                            <div class="status-breakdown">
                                <div class="status-item status-capital">
                                    <i class="fas fa-circle"></i>
                                    <span id="capitalPercent">-%</span> do total
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Gráfico Pizza para Capital com 3 categorias -->
                    <div class="pie-chart-container">
                        <svg class="pie-chart" width="120" height="120" viewBox="0 0 42 42">
                            <circle class="pie-background" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#e5e7eb" stroke-width="3"></circle>
                            <circle class="pie-ativa" id="capitalPieAtiva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#00c853" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-reserva" id="capitalPieReserva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#ff9500" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-pensionista" id="capitalPiePensionista" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#8b5cf6" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                        </svg>
                        <div class="pie-legend">
                            <div class="legend-item">
                                <span class="color-dot ativa"></span>
                                <span id="capitalAtiva">-</span> Ativa
                            </div>
                            <div class="legend-item">
                                <span class="color-dot reserva"></span>
                                <span id="capitalReserva">-</span> Reserva
                            </div>
                            <div class="legend-item">
                                <span class="color-dot pensionista"></span>
                                <span id="capitalPensionista">-</span> Pensionista
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dual-stats-separator"></div>
                <div class="dual-stat-item interior-item">
                    <div class="dual-stat-icon interior-icon">
                        <i class="fas fa-tree"></i>
                    </div>
                    <div class="dual-stat-info">
                        <div class="dual-stat-value" id="interiorQuantidade">-</div>
                        <div class="dual-stat-label">Interior</div>
                        <div class="dual-stat-extra">
                            <div class="status-breakdown">
                                <div class="status-item status-interior">
                                    <i class="fas fa-circle"></i>
                                    <span id="interiorPercent">-%</span> do total
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Gráfico Pizza para Interior com 3 categorias -->
                    <div class="pie-chart-container">
                        <svg class="pie-chart" width="120" height="120" viewBox="0 0 42 42">
                            <circle class="pie-background" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#e5e7eb" stroke-width="3"></circle>
                            <circle class="pie-ativa" id="interiorPieAtiva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#00c853" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-reserva" id="interiorPieReserva" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#ff9500" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                            <circle class="pie-pensionista" id="interiorPiePensionista" cx="21" cy="21" r="15.9155" fill="transparent" stroke="#8b5cf6" stroke-width="3" 
                                stroke-dasharray="0 100" stroke-dashoffset="25" transform="rotate(-90 21 21)"></circle>
                        </svg>
                        <div class="pie-legend">
                            <div class="legend-item">
                                <span class="color-dot ativa"></span>
                                <span id="interiorAtiva">-</span> Ativa
                            </div>
                            <div class="legend-item">
                                <span class="color-dot reserva"></span>
                                <span id="interiorReserva">-</span> Reserva
                            </div>
                            <div class="legend-item">
                                <span class="color-dot pensionista"></span>
                                <span id="interiorPensionista">-</span> Pensionista
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

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
                            <option value="Remido">Remido</option>
                            <option value="Agregado">Agregado</option>
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

    <!-- CSS e JavaScript inline -->
    <style>
        /* Estilos melhorados para KPI */
        .stat-icon.birthday {
            background: linear-gradient(135deg, #e91e63 0%, #ad1457 100%);
        }
        
        .stat-icon.geographic {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        
        .stat-change.neutral {
            background: rgba(156, 39, 176, 0.1);
            color: #9c27b0;
        }
        
        .stat-change.neutral i {
            color: #e91e63;
        }
        
        /* Ajusta grid para 3 cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        @media (min-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1199px) and (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 767px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Card Principal */
        .dual-stat-card {
            position: relative;
            overflow: visible;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            min-width: 320px;
            width: 100%;
        }

        .dual-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .dual-stat-card:hover::before {
            transform: scaleX(1);
        }

        .dual-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(0, 86, 210, 0.2);
        }

        /* Header do Card */
        .dual-stat-header {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dual-stat-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .dual-stat-percentage {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--primary);
            background: var(--primary-light);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        /* Layout Desktop - Vertical */
        .dual-stats-row {
            display: flex;
            align-items: stretch;
            padding: 0;
            min-height: 120px;
            width: 100%;
        }

        .dual-stat-item {
            flex: 1;
            min-width: 0;
            padding: 1.5rem 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1rem;
            transition: all 0.3s ease;
            position: relative;
            width: 50%;
        }

        .dual-stat-item:hover {
            background: rgba(0, 86, 210, 0.02);
        }

        .dual-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .dual-stat-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            text-align: center;
            align-items: center;
        }

        .dual-stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
            transition: all 0.3s ease;
        }

        .dual-stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 600;
            line-height: 1;
        }

        /* Separador vertical */
        .dual-stats-separator {
            width: 1px;
            background: linear-gradient(to bottom, transparent, var(--gray-300), transparent);
            margin: 1.5rem 0;
            flex-shrink: 0;
        }

        /* Card Triplo */
        .triple-stat-card .triple-stats-row {
            display: flex;
            align-items: stretch;
            padding: 0;
            min-height: 140px;
            width: 100%;
        }

        .triple-stats-row .dual-stat-item {
            flex: 1;
            min-width: 0;
            padding: 1.5rem 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1rem;
            transition: all 0.3s ease;
            position: relative;
            width: 33.33%;
        }

        /* === GRÁFICOS DE PIZZA COM HOVER PARA TODOS OS KPIs - 3 FATIAS === */
        
        /* Card gráficos de pizza - todos os KPIs */
        .corporacoes-pie .triple-stats-row,
        .associados-pie .dual-stats-row,
        .distribuicao-pie .dual-stats-row {
            min-height: 140px;
        }

        /* Container do gráfico pizza - escondido por padrão */
        .pie-chart-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 2px solid var(--gray-200);
            pointer-events: none;
            width: 200px;
        }

        /* Mostrar gráfico no hover */
        .dual-stat-item:hover .pie-chart-container {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translate(-50%, -50%) scale(1);
        }

        /* Gráfico SVG */
        .pie-chart {
            width: 100%;
            height: auto;
            margin-bottom: 0.5rem;
        }

        /* Círculos do gráfico */
        .pie-chart circle {
            transition: stroke-dasharray 1s ease-in-out;
        }

        .pie-background {
            stroke: #f3f4f6;
        }

        .pie-ativa {
            stroke: #00c853;
        }

        .pie-reserva {
            stroke: #ff9500;
        }

        .pie-pensionista {
            stroke: #8b5cf6;
        }

        /* Legenda do gráfico de pizza */
        .pie-legend {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.75rem;
        }

        .pie-legend .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .color-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .color-dot.ativa {
            background: #00c853;
        }

        .color-dot.reserva {
            background: #ff9500;
        }

        .color-dot.pensionista {
            background: #8b5cf6;
        }

        /* Informações extras para outros cards (Capital/Interior) */
        .dual-stat-extra {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            align-items: center;
        }

        .status-breakdown {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            white-space: nowrap;
        }

        .status-capital {
            background: rgba(13, 110, 253, 0.15);
            color: var(--primary);
        }

        .status-interior {
            background: rgba(25, 135, 84, 0.15);
            color: var(--success);
        }

        /* Estilos específicos dos ícones */
        .ativos-icon {
            background: linear-gradient(135deg, #00c853 0%, #00a847 100%);
            color: white;
        }

        .novos-icon {
            background: linear-gradient(135deg, #0d6efd 0%, #084298 100%);
            color: white;
        }

        .pm-icon {
            background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
            color: white;
        }

        .bm-icon {
            background: linear-gradient(135deg, #fd7e14 0%, #e8690b 100%);
            color: white;
        }

        .outros-icon {
            background: linear-gradient(135deg, #6f42c1 0%, #5a2d8a 100%);
            color: white;
        }

        .capital-icon {
            background: linear-gradient(135deg, #0d6efd 0%, #084298 100%);
            color: white;
        }

        .interior-icon {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
        }

        /* Efeitos hover */
        .ativos-item:hover .ativos-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 25px rgba(0, 200, 83, 0.4);
        }

        .novos-item:hover .novos-icon {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
        }

        .pm-item:hover .pm-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        .bm-item:hover .bm-icon {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 8px 25px rgba(253, 126, 20, 0.4);
        }

        .outros-item:hover .outros-icon {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 8px 25px rgba(111, 66, 193, 0.4);
        }

        .capital-item:hover .capital-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.4);
        }

        .interior-item:hover .interior-icon {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 8px 25px rgba(25, 135, 84, 0.4);
        }

        /* Mobile: sempre mostrar gráficos */
        @media (max-width: 768px) {
            .pie-chart-container {
                position: static;
                transform: none;
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                margin-top: 0.75rem;
                width: 100%;
                background: var(--gray-50);
                border: 1px solid var(--gray-300);
                box-shadow: none;
            }

            .dual-stat-item {
                padding-bottom: 1rem;
            }
            
            .corporacoes-pie .triple-stats-row,
            .associados-pie .dual-stats-row,
            .distribuicao-pie .dual-stats-row {
                min-height: auto;
            }

            .dual-stats-row {
                flex-direction: column;
                min-height: auto;
            }

            .dual-stats-separator {
                width: 80%;
                height: 1px;
                margin: 0.75rem auto;
                background: linear-gradient(to right, transparent, var(--gray-300), transparent);
            }

            /* LAYOUT HORIZONTAL NO MOBILE - TODOS OS CARDS */
            .dual-stat-item {
                padding: 1.25rem;
                width: 100%;
                min-width: 0;
                flex-direction: row !important;
                align-items: center !important;
                text-align: left !important;
                gap: 1rem !important;
                justify-content: flex-start !important;
            }

            .dual-stat-info {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                text-align: left !important;
            }

            .dual-stat-value {
                font-size: 1.75rem;
            }

            .dual-stat-icon {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
                flex-shrink: 0;
            }

            /* Card triplo também horizontal no mobile */
            .triple-stats-row {
                flex-direction: column;
                min-height: auto;
            }

            .triple-stats-row .dual-stats-separator {
                width: 80%;
                height: 1px;
                margin: 0.75rem auto;
                background: linear-gradient(to right, transparent, var(--gray-300), transparent);
            }

            .triple-stats-row .dual-stat-item {
                padding: 1.25rem;
                width: 100%;
                min-width: 0;
                flex-direction: row !important;
                align-items: center !important;
                text-align: left !important;
                gap: 1rem !important;
                justify-content: flex-start !important;
            }

            .triple-stats-row .dual-stat-info {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                text-align: left !important;
            }
        }

        /* Responsivo desktop */
        @media (min-width: 769px) {
            .dual-stat-item {
                max-width: 50%;
                overflow: visible;
            }
            
            .dual-stat-value {
                font-size: 1.5rem;
            }
            
            .dual-stat-icon {
                width: 44px;
                height: 44px;
                font-size: 1.125rem;
            }
        }

        @media (min-width: 1200px) {
            .dual-stat-value {
                font-size: 1.75rem;
            }
            
            .dual-stat-icon {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
            }

            .triple-stats-row .dual-stat-value {
                font-size: 2rem;
            }
            
            .triple-stats-row .dual-stat-icon {
                width: 52px;
                height: 52px;
                font-size: 1.375rem;
            }
        }

        /* Animações */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .dual-stat-icon {
            animation: float 4s ease-in-out infinite;
        }

        .dual-stat-item:hover .dual-stat-icon {
            animation: none;
        }
    </style>

    <script>
        function toggleSearch() {
            console.log('Busca global ativada');
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }

        function toggleNotifications() {
            console.log('Painel de notificações');
            alert('Painel de notificações em desenvolvimento');
        }

        function abrirModalNovaObservacao() {
            const modal = new bootstrap.Modal(document.getElementById('modalNovaObservacao'));
            modal.show();
        }

        function salvarObservacao() {
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

        // Carrega estatísticas via API - TODOS OS KPIs COM GRÁFICOS DE PIZZA 3 FATIAS
        function carregarEstatisticas() {
            fetch('../api/dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const stats = data.data;
                        
                        // === CARD 1: ASSOCIADOS ATIVOS + NOVOS COM GRÁFICOS DE PIZZA ===
                        document.getElementById('associadosAtivos').textContent = 
                            new Intl.NumberFormat('pt-BR').format(stats.associados_ativos);
                        document.getElementById('novosAssociados').textContent = 
                            new Intl.NumberFormat('pt-BR').format(stats.novos_associados);
                        
                        // Dados para Associados Ativos
                        const associadosAtiva = stats.associados_ativa || 0;
                        const associadosReserva = stats.associados_reserva || 0;
                        const associadosPensionista = stats.associados_pensionista || 0;
                        document.getElementById('associadosAtiva').textContent = associadosAtiva;
                        document.getElementById('associadosReserva').textContent = associadosReserva;
                        document.getElementById('associadosPensionista').textContent = associadosPensionista;
                        
                        // Dados para Novos Associados
                        const novosAtiva = stats.novos_ativa || 0;
                        const novosReserva = stats.novos_reserva || 0;
                        const novosPensionista = stats.novos_pensionista || 0;
                        document.getElementById('novosAtiva').textContent = novosAtiva;
                        document.getElementById('novosReserva').textContent = novosReserva;
                        document.getElementById('novosPensionista').textContent = novosPensionista;
                        
                        // === CARD 2: CORPORAÇÕES COM GRÁFICOS DE PIZZA ===
                        const corp = stats.corporacoes_principais;
                        
                        // Atualizar valores principais
                        document.getElementById('pmQuantidade').textContent = 
                            new Intl.NumberFormat('pt-BR').format(corp.pm_quantidade);
                        document.getElementById('bmQuantidade').textContent = 
                            new Intl.NumberFormat('pt-BR').format(corp.bm_quantidade);
                        document.getElementById('outrosQuantidade').textContent = 
                            new Intl.NumberFormat('pt-BR').format(corp.outros_quantidade);
                        
                        // Atualizar valores de ativa/reserva/pensionista
                        const pmAtiva = corp.pm_ativa || 0;
                        const pmReserva = corp.pm_reserva || 0;
                        const pmPensionista = corp.pm_pensionista || 0;
                        const bmAtiva = corp.bm_ativa || 0;
                        const bmReserva = corp.bm_reserva || 0;
                        const bmPensionista = corp.bm_pensionista || 0;
                        const outrosAtiva = corp.outros_ativa || 0;
                        const outrosReserva = corp.outros_reserva || 0;
                        const outrosPensionista = corp.outros_pensionista || 0;
                        
                        document.getElementById('pmAtiva').textContent = pmAtiva;
                        document.getElementById('pmReserva').textContent = pmReserva;
                        document.getElementById('pmPensionista').textContent = pmPensionista;
                        document.getElementById('bmAtiva').textContent = bmAtiva;
                        document.getElementById('bmReserva').textContent = bmReserva;
                        document.getElementById('bmPensionista').textContent = bmPensionista;
                        document.getElementById('outrosAtiva').textContent = outrosAtiva;
                        document.getElementById('outrosReserva').textContent = outrosReserva;
                        document.getElementById('outrosPensionista').textContent = outrosPensionista;
                        
                        document.getElementById('corporacoesPercent').innerHTML = 
                            `<i class="fas fa-chart-pie"></i> ${corp.total_percentual}% do total`;
                        
                        // === CARD 3: DISTRIBUIÇÃO COM GRÁFICOS DE PIZZA ===
                        document.getElementById('capitalQuantidade').textContent = 
                            new Intl.NumberFormat('pt-BR').format(stats.capital);
                        document.getElementById('interiorQuantidade').textContent = 
                            new Intl.NumberFormat('pt-BR').format(stats.interior);
                        document.getElementById('totalLocalizacao').textContent = 
                            new Intl.NumberFormat('pt-BR').format(stats.total_localizacao);
                        
                        // Dados para Capital e Interior
                        const capitalAtiva = stats.capital_ativa || 0;
                        const capitalReserva = stats.capital_reserva || 0;
                        const capitalPensionista = stats.capital_pensionista || 0;
                        const interiorAtiva = stats.interior_ativa || 0;
                        const interiorReserva = stats.interior_reserva || 0;
                        const interiorPensionista = stats.interior_pensionista || 0;
                        
                        document.getElementById('capitalAtiva').textContent = capitalAtiva;
                        document.getElementById('capitalReserva').textContent = capitalReserva;
                        document.getElementById('capitalPensionista').textContent = capitalPensionista;
                        document.getElementById('interiorAtiva').textContent = interiorAtiva;
                        document.getElementById('interiorReserva').textContent = interiorReserva;
                        document.getElementById('interiorPensionista').textContent = interiorPensionista;
                        
                        document.getElementById('capitalPercent').textContent = `${stats.capital_percentual}%`;
                        document.getElementById('interiorPercent').textContent = `${stats.interior_percentual}%`;
                        
                        // ANIMAR TODOS OS GRÁFICOS DE PIZZA COM 3 FATIAS
                        setTimeout(() => {
                            // Card 1: Associados
                            animarGraficoPizza('ativos', associadosAtiva, associadosReserva, associadosPensionista);
                            animarGraficoPizza('novos', novosAtiva, novosReserva, novosPensionista);
                            
                            // Card 2: Corporações
                            animarGraficoPizza('pm', pmAtiva, pmReserva, pmPensionista);
                            animarGraficoPizza('bm', bmAtiva, bmReserva, bmPensionista);
                            animarGraficoPizza('outros', outrosAtiva, outrosReserva, outrosPensionista);
                            
                            // Card 3: Distribuição
                            animarGraficoPizza('capital', capitalAtiva, capitalReserva, capitalPensionista);
                            animarGraficoPizza('interior', interiorAtiva, interiorReserva, interiorPensionista);
                        }, 500);
                        
                        console.log('Estatísticas carregadas com gráficos de pizza 3 fatias:', stats);
                        
                    } else {
                        console.error('Erro ao carregar estatísticas:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro de rede:', error);
                    // Fallback: calcular com dados locais se disponível
                    if (todosAssociados && todosAssociados.length > 0) {
                        calcularEstatisticasLocal();
                    }
                });
        }

        // Função para animar gráfico de pizza UNIVERSAL - funciona para todos os cards com 3 FATIAS
        function animarGraficoPizza(tipo, ativa, reserva, pensionista) {
            const total = ativa + reserva + pensionista;
            if (total === 0) {
                // Se não há dados, esconder o gráfico ou mostrar vazio
                return;
            }
            
            const ativaPercent = (ativa / total) * 100;
            const reservaPercent = (reserva / total) * 100;
            const pensionistaPercent = (pensionista / total) * 100;
            
            // Elementos SVG
            const pieAtiva = document.getElementById(`${tipo}PieAtiva`);
            const pieReserva = document.getElementById(`${tipo}PieReserva`);
            const piePensionista = document.getElementById(`${tipo}PiePensionista`);
            
            if (!pieAtiva || !pieReserva || !piePensionista) {
                console.warn(`Elementos de gráfico não encontrados para: ${tipo}`);
                return;
            }
            
            // Animar fatia "Ativa" (começa do topo - 12h)
            pieAtiva.style.strokeDasharray = `${ativaPercent} 100`;
            pieAtiva.style.strokeDashoffset = '25';
            
            // Animar fatia "Reserva" (continua após a ativa)
            const reservaOffset = 25 - ativaPercent;
            pieReserva.style.strokeDasharray = `${reservaPercent} 100`;
            pieReserva.style.strokeDashoffset = `${reservaOffset}`;
            
            // Animar fatia "Pensionista" (continua após reserva)
            const pensionistaOffset = reservaOffset - reservaPercent;
            piePensionista.style.strokeDasharray = `${pensionistaPercent} 100`;
            piePensionista.style.strokeDashoffset = `${pensionistaOffset}`;
        }

        // Função de fallback para cálculo local
        function calcularEstatisticasLocal() {
            if (!todosAssociados || todosAssociados.length === 0) return;

            // Associados ativos
            const ativos = todosAssociados.filter(a => a.situacao === 'Filiado').length;
            document.getElementById('associadosAtivos').textContent = 
                new Intl.NumberFormat('pt-BR').format(ativos);

            // Novos (30 dias) - aproximação
            const agora = new Date();
            const trintaDiasAtras = new Date(agora.getTime() - (30 * 24 * 60 * 60 * 1000));
            const novos = todosAssociados.filter(a => {
                if (!a.data_filiacao || a.data_filiacao === '0000-00-00') return false;
                const dataFiliacao = new Date(a.data_filiacao);
                return dataFiliacao >= trintaDiasAtras;
            }).length;
            document.getElementById('novosAssociados').textContent = 
                new Intl.NumberFormat('pt-BR').format(novos);

            console.log('Estatísticas calculadas localmente');
        }

        // Carrega quando a página está pronta
        document.addEventListener('DOMContentLoaded', function() {
            carregarEstatisticas();
            
            // Inicializa AOS
            AOS.init({
                duration: 800,
                easing: 'ease-out-cubic',
                once: true
            });
        });
    </script>

    <script>
    // Verifica se deve carregar estatísticas
    const podeVerKPIs = <?php echo $podeVerKPIs ? 'true' : 'false'; ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Só carrega estatísticas se o usuário tem permissão
        if (podeVerKPIs) {
            carregarEstatisticas();
        }
        
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true
        });
    });
</script>

    <script src="js/dashboard.js"></script>

</body>

</html>