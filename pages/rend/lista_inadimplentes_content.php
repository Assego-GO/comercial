<?php
// Iniciar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$temPermissaoFinanceiro = false;
$isFinanceiro = false;
$isPresidencia = false;
$motivoNegacao = '';

// Verificar permissões
if (isset($_SESSION['departamento_id'])) {
    $deptId = $_SESSION['departamento_id'];
    
    error_log("=== DEBUG LISTA INADIMPLENTES - ACESSO ===");
    error_log("Departamento ID encontrado: " . $deptId);
    error_log("Usuário: " . ($_SESSION['funcionario_nome'] ?? $_SESSION['nome'] ?? 'N/A'));

    $deptIdInt = (int)$deptId;

    if ($deptIdInt == 2) { // Financeiro
        $temPermissaoFinanceiro = true;
        $isFinanceiro = true;
        error_log("✅ ACESSO LIBERADO PARA FINANCEIRO (ID: 2)");
    } elseif ($deptIdInt == 1) { // Presidência
        $temPermissaoFinanceiro = true;
        $isPresidencia = true;
        error_log("✅ ACESSO LIBERADO PARA PRESIDÊNCIA (ID: 1)");
    } else {
        $motivoNegacao = "Departamento '$deptId' não autorizado. Apenas Financeiro (2) e Presidência (1).";
        error_log("❌ DEPARTAMENTO '$deptId' NÃO AUTORIZADO");
    }
} else {
    $motivoNegacao = 'Departamento não identificado na sessão.';
    error_log("❌ departamento_id não encontrado na sessão");
}

// Se não tem permissão, mostrar erro
if (!$temPermissaoFinanceiro) {
    echo '<div class="alert alert-danger">
        <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Lista de Inadimplentes</h4>
        <p class="mb-3">' . htmlspecialchars($motivoNegacao) . '</p>
        <div class="btn-group">
            <button onclick="location.reload()" class="btn btn-warning btn-sm">
                <i class="fas fa-redo me-1"></i>
                Tentar Novamente
            </button>
            <a href="../pages/financeiro.php" class="btn btn-primary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>
                Voltar aos Serviços Financeiros
            </a>
        </div>
    </div>';
    exit;
}
?>

<!-- Container Lista Inadimplentes -->
<div class="lista-inadimplentes-container">
    
    <!-- Estatísticas de Inadimplência 
    <div class="stats-grid-inadim">
        <div class="stat-card-inadim danger">
            <div class="stat-content-inadim">
                <div class="stat-value-inadim" id="totalInadimplentesInadim">0</div>
                <div class="stat-label-inadim">Total de Inadimplentes</div>
                <div class="stat-change-inadim negative">
                    <i class="fas fa-exclamation-triangle"></i>
                    Requer atenção imediata
                </div>
            </div>
            <div class="stat-icon-inadim danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>

        <div class="stat-card-inadim warning">
            <div class="stat-content-inadim">
                <div class="stat-value-inadim" id="percentualInadimplenciaInadim">0%</div>
                <div class="stat-label-inadim">Percentual de Inadimplência</div>
                <div class="stat-change-inadim neutral">
                    <i class="fas fa-percentage"></i>
                    Em relação ao total
                </div>
            </div>
            <div class="stat-icon-inadim warning">
                <i class="fas fa-percentage"></i>
            </div>
        </div>

        <div class="stat-card-inadim info">
            <div class="stat-content-inadim">
                <div class="stat-value-inadim" id="totalAssociadosInadim">0</div>
                <div class="stat-label-inadim">Total de Associados</div>
                <div class="stat-change-inadim neutral">
                    <i class="fas fa-users"></i>
                    Base total
                </div>
            </div>
            <div class="stat-icon-inadim info">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>-->

    <!-- Filtros -->
    <div class="filtros-container-inadim">
        <h6 class="mb-3">
            <i class="fas fa-filter me-2"></i>
            Filtros de Pesquisa
        </h6>

        <form class="filtros-form-inadim" onsubmit="ListaInadimplentes.aplicarFiltros(event)">
            <div>
                <label class="form-label-inadim" for="filtroNomeInadim">Nome do Associado</label>
                <input type="text" class="form-control form-control-inadim" id="filtroNomeInadim" 
                       placeholder="Digite o nome...">
            </div>

            <div>
                <label class="form-label-inadim" for="filtroRGInadim">RG Militar</label>
                <input type="text" class="form-control form-control-inadim" id="filtroRGInadim" 
                       placeholder="Digite o RG...">
            </div>

            <!-- <div>
                <label class="form-label-inadim" for="filtroVinculoInadim">Vínculo Servidor</label>
                <select class="form-select form-control-inadim" id="filtroVinculoInadim">
                    <option value="">Todos os vínculos</option>
                    <option value="ATIVO">Ativo</option>
                    <option value="APOSENTADO">Aposentado</option>
                    <option value="PENSIONISTA">Pensionista</option>
                </select>
            </div> -->

            <div>
                <label class="form-label-inadim">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-search me-1"></i>
                        Filtrar
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="ListaInadimplentes.limparFiltros()">
                        <i class="fas fa-eraser me-1"></i>
                        Limpar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabela de Inadimplentes -->
    <div class="tabela-inadimplentes-container">
        <div class="tabela-header-inadim">
            <h6>
                <i class="fas fa-table me-2"></i>
                Lista de Inadimplentes
            </h6>
            <div class="tabela-actions-inadim">
                <button class="btn btn-light btn-sm" onclick="ListaInadimplentes.exportarExcel()">
                    <i class="fas fa-file-excel me-1"></i>
                    Excel
                </button>
                <button class="btn btn-light btn-sm" onclick="ListaInadimplentes.exportarPDF()">
                    <i class="fas fa-file-pdf me-1"></i>
                    PDF
                </button>
                <button class="btn btn-light btn-sm" onclick="ListaInadimplentes.imprimirRelatorio()">
                    <i class="fas fa-print me-1"></i>
                    Imprimir
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-inadim">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>RG</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Nascimento</th>
                        <th>Vínculo</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaInadimplentesInadim">
                    <!-- Dados serão carregados via JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Loading -->
        <div id="loadingInadimplentesInadim" class="loading-container-inadim">
            <div class="loading-spinner-inadim"></div>
            <span>Carregando dados dos inadimplentes...</span>
        </div>
    </div>
</div>

<!-- Modal de Detalhes do Inadimplente -->
<div class="modal fade" id="modalDetalhesInadimplenteLista" tabindex="-1" aria-labelledby="modalDetalhesLabelInadim" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Header do Modal -->
            <div class="modal-header bg-gradient-danger text-white">
                <div class="modal-title-wrapper">
                    <h5 class="modal-title" id="modalDetalhesLabelInadim">
                        <i class="fas fa-user-circle me-2"></i>
                        Detalhes do Associado Inadimplente
                    </h5>
                    <div class="modal-subtitle" id="modalSubtitleInadim">
                        <!-- Nome e ID serão inseridos aqui -->
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body do Modal -->
            <div class="modal-body">
                <!-- Loading -->
                <div id="modalLoadingInadim" class="text-center py-5">
                    <div class="spinner-border text-danger" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-3 text-muted">Carregando dados do associado...</p>
                </div>

                <!-- Conteúdo Principal -->
                <div id="modalContentInadim" style="display: none;">
                    <!-- Status e Alertas -->
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <strong>Status: INADIMPLENTE</strong>
                            <div class="small mt-1">
                                <span id="diasInadimplenciaInadim">Verificando período de inadimplência...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs de Navegação -->
                    <ul class="nav nav-tabs nav-fill mb-4" id="detalhesTabInadim" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dadosPessoais-tab-inadim" data-bs-toggle="tab"
                                    data-bs-target="#dadosPessoaisInadim" type="button">
                                <i class="fas fa-user me-2"></i>Dados Pessoais
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="dadosFinanceiros-tab-inadim" data-bs-toggle="tab"
                                    data-bs-target="#dadosFinanceirosInadim" type="button">
                                <i class="fas fa-dollar-sign me-2"></i>Financeiro
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="dadosMilitares-tab-inadim" data-bs-toggle="tab"
                                    data-bs-target="#dadosMilitaresInadim" type="button">
                                <i class="fas fa-shield-alt me-2"></i>Militar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="observacoes-tab-inadim" data-bs-toggle="tab"
                                    data-bs-target="#observacoesInadim" type="button">
                                <i class="fas fa-comment-dots me-2"></i>Observações
                                <span class="badge bg-danger ms-1" id="badgeObservacoesInadim" style="display: none;">0</span>
                            </button>
                        </li>
                    </ul>

                    <!-- Conteúdo das Tabs -->
                    <div class="tab-content" id="detalhesTabContentInadim">
                        <!-- Tab Dados Pessoais -->
                        <div class="tab-pane fade show active" id="dadosPessoaisInadim" role="tabpanel">
                            <div class="row g-4">
                                <!-- Coluna Esquerda -->
                                <div class="col-md-6">
                                    <div class="info-card-inadim">
                                        <h6 class="info-card-title-inadim">
                                            <i class="fas fa-id-card me-2"></i>Informações Básicas
                                        </h6>
                                        <div class="info-grid-inadim">
                                            <div class="info-item-inadim">
                                                <label>Nome Completo:</label>
                                                <span id="detalheNomeInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>CPF:</label>
                                                <span id="detalheCPFInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>RG Militar:</label>
                                                <span id="detalheRGInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Data de Nascimento:</label>
                                                <span id="detalheNascimentoInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Sexo:</label>
                                                <span id="detalheSexoInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Estado Civil:</label>
                                                <span id="detalheEstadoCivilInadim">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Coluna Direita -->
                                <div class="col-md-6">
                                    <div class="info-card-inadim">
                                        <h6 class="info-card-title-inadim">
                                            <i class="fas fa-phone me-2"></i>Contato
                                        </h6>
                                        <div class="info-grid-inadim">
                                            <div class="info-item-inadim">
                                                <label>Telefone:</label>
                                                <span id="detalheTelefoneInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>E-mail:</label>
                                                <span id="detalheEmailInadim">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="info-card-inadim mt-3">
                                        <h6 class="info-card-title-inadim">
                                            <i class="fas fa-map-marker-alt me-2"></i>Endereço
                                        </h6>
                                        <div class="info-grid-inadim">
                                            <div class="info-item-inadim">
                                                <label>CEP:</label>
                                                <span id="detalheCEPInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Logradouro:</label>
                                                <span id="detalheEnderecoInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Cidade:</label>
                                                <span id="detalheCidadeInadim">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Dados Financeiros -->
                        <div class="tab-pane fade" id="dadosFinanceirosInadim" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="info-card-inadim">
                                        <h6 class="info-card-title-inadim">
                                            <i class="fas fa-file-invoice-dollar me-2"></i>Situação Financeira
                                        </h6>
                                        <div class="info-grid-inadim">
                                            <div class="info-item-inadim">
                                                <label>Situação:</label>
                                                <span id="detalheSituacaoFinanceiraInadim" class="badge bg-danger">INADIMPLENTE</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Vínculo:</label>
                                                <span id="detalheVinculoInadim">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="info-card-inadim">
                                        <h6 class="info-card-title-inadim">
                                            <i class="fas fa-chart-line me-2"></i>Resumo de Débitos
                                        </h6>
                                        <div class="debt-summary-inadim">
                                            <div class="debt-item-inadim">
                                                <span>Valor Total em Débito:</span>
                                                <strong class="text-danger" id="valorTotalDebitoInadim">R$ 0,00</strong>
                                            </div>
                                            <div class="debt-item-inadim">
                                                <span>Meses em Atraso:</span>
                                                <strong id="mesesAtrasoInadim">0</strong>
                                            </div>
                                            <div class="debt-item-inadim">
                                                <span>Última Contribuição:</span>
                                                <strong id="ultimaContribuicaoInadim">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Dados Militares -->
                        <div class="tab-pane fade" id="dadosMilitaresInadim" role="tabpanel">
                            <div class="info-card-inadim">
                                <h6 class="info-card-title-inadim">
                                    <i class="fas fa-shield-alt me-2"></i>Informações Militares
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="info-grid-inadim">
                                            <div class="info-item-inadim">
                                                <label>Corporação:</label>
                                                <span id="detalheCorporacaoInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Patente:</label>
                                                <span id="detalhePatenteInadim">-</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-grid-inadim">
                                            <div class="info-item-inadim">
                                                <label>Lotação:</label>
                                                <span id="detalheLotacaoInadim">-</span>
                                            </div>
                                            <div class="info-item-inadim">
                                                <label>Unidade:</label>
                                                <span id="detalheUnidadeInadim">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Observações -->
                        <div class="tab-pane fade" id="observacoesInadim" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                    <i class="fas fa-comment-dots me-2"></i>Observações do Associado
                                </h6>
                                <button class="btn btn-sm btn-primary" onclick="ListaInadimplentes.adicionarObservacao()">
                                    <i class="fas fa-plus me-1"></i>Nova Observação
                                </button>
                            </div>
                            <div id="listaObservacoesInadim">
                                <!-- Observações serão carregadas aqui -->
                                <p class="text-muted text-center py-3">Nenhuma observação registrada</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer do Modal -->
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Fechar
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-warning btn-sm" onclick="ListaInadimplentes.enviarCobrancaModal()">
                            <i class="fas fa-envelope me-1"></i>Cobrança
                        </button>
                        <button type="button" class="btn btn-success btn-sm" onclick="ListaInadimplentes.registrarPagamentoModal()">
                            <i class="fas fa-dollar-sign me-1"></i>Pagamento
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainerInadim"></div>

<!-- Estilos compactos para Lista de Inadimplentes -->
<style>
/* Container principal */
.lista-inadimplentes-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin: 0;
}

/* Stats grid */
.stats-grid-inadim {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card-inadim {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card-inadim::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-card-inadim.danger::before {
    background: linear-gradient(135deg, var(--danger), #c82333);
}

.stat-card-inadim.warning::before {
    background: linear-gradient(135deg, var(--warning), #ea580c);
}

.stat-card-inadim.info::before {
    background: linear-gradient(135deg, var(--info), #0ea5e9);
}

.stat-card-inadim:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.stat-content-inadim {
    flex: 1;
}

.stat-value-inadim {
    font-size: 2rem;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.stat-label-inadim {
    color: var(--secondary);
    font-weight: 600;
    font-size: 0.9rem;
}

.stat-change-inadim {
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.stat-change-inadim.negative {
    color: var(--danger);
}

.stat-change-inadim.neutral {
    color: var(--secondary);
}

.stat-icon-inadim {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-left: 1rem;
}

.stat-icon-inadim.danger {
    background: linear-gradient(135deg, var(--danger), #c82333);
}

.stat-icon-inadim.warning {
    background: linear-gradient(135deg, var(--warning), #ea580c);
}

.stat-icon-inadim.info {
    background: linear-gradient(135deg, var(--info), #0ea5e9);
}

/* Filtros */
.filtros-container-inadim {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border: 1px solid #dee2e6;
}

.filtros-form-inadim {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}

.form-label-inadim {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    display: block;
    font-size: 0.9rem;
}

.form-control-inadim {
    border-radius: 6px;
    border: 2px solid #e9ecef;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.form-control-inadim:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.125rem rgba(0, 86, 210, 0.25);
}

/* Tabela */
.tabela-inadimplentes-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.tabela-header-inadim {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tabela-header-inadim h6 {
    margin: 0;
    color: var(--dark);
    font-weight: 700;
}

.tabela-actions-inadim {
    display: flex;
    gap: 0.5rem;
}

.table-inadim {
    margin-bottom: 0;
}

.table-inadim thead th {
    background: var(--light);
    color: var(--primary);
    font-weight: 700;
    border: none;
    padding: 0.75rem 0.5rem;
    font-size: 0.85rem;
}

.table-inadim tbody tr:hover {
    background: rgba(0, 86, 210, 0.05);
}

.table-inadim tbody td {
    padding: 0.75rem 0.5rem;
    font-size: 0.85rem;
    vertical-align: middle;
}

/* Loading */
.loading-container-inadim {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--primary);
}

.loading-spinner-inadim {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 1rem;
}

/* Modal info cards */
.info-card-inadim {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.info-card-title-inadim {
    color: var(--primary);
    margin-bottom: 0.75rem;
    font-weight: 700;
    font-size: 0.95rem;
}

.info-grid-inadim {
    display: grid;
    gap: 0.5rem;
}

.info-item-inadim {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
    border-bottom: 1px solid #dee2e6;
}

.info-item-inadim:last-child {
    border-bottom: none;
}

.info-item-inadim label {
    font-weight: 600;
    color: var(--secondary);
    margin: 0;
    font-size: 0.85rem;
}

.info-item-inadim span {
    font-weight: 500;
    color: var(--dark);
    font-size: 0.85rem;
}

/* Debt summary */
.debt-summary-inadim {
    background: white;
    border-radius: 6px;
    padding: 0.75rem;
}

.debt-item-inadim {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.debt-item-inadim:last-child {
    margin-bottom: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .lista-inadimplentes-container {
        padding: 0.5rem;
    }
    
    .stats-grid-inadim {
        grid-template-columns: 1fr;
    }
    
    .filtros-form-inadim {
        grid-template-columns: 1fr;
    }
    
    .tabela-actions-inadim {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .stat-card-inadim {
        padding: 0.75rem;
    }
    
    .stat-icon-inadim {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
}

/* Animation */
.animate-fade-in-inadim {
    animation: fadeInInadim 0.4s ease-out;
}

@keyframes fadeInInadim {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<!-- Script inline para passar dados para o JavaScript -->
<script>
// Passar dados de permissão para o JavaScript quando a partial carregar
window.listaInadimplentesPermissions = {
    temPermissao: <?php echo json_encode($temPermissaoFinanceiro); ?>,
    isFinanceiro: <?php echo json_encode($isFinanceiro); ?>,
    isPresidencia: <?php echo json_encode($isPresidencia); ?>
};

console.log('✅ Lista Inadimplentes carregada como partial');
</script>