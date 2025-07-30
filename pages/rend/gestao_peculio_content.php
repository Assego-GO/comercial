<?php
// Iniciar sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$temPermissaoFinanceiro = false;
$isFinanceiro = false;
$isPresidencia = false;
$motivoNegacao = '';

// CORREÇÃO: Os dados estão diretamente na sessão, não em um sub-array
if (isset($_SESSION['departamento_id'])) {
    $deptId = $_SESSION['departamento_id'];
    
    error_log("=== DEBUG GESTA_PECULIO - ACESSO CORRIGIDO ===");
    error_log("Departamento ID encontrado: " . $deptId);
    error_log("Usuário: " . ($_SESSION['funcionario_nome'] ?? $_SESSION['nome'] ?? 'N/A'));

    // Converter para inteiro para comparação segura
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
        <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Gestão de Pecúlio</h4>
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
            <a href="../pages/dashboard.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-home me-1"></i>
                Dashboard
            </a>
        </div>
    </div>';
    exit;
}

// Obter nome do usuário para exibição
$nomeUsuario = $_SESSION['funcionario_nome'] ?? $_SESSION['nome'] ?? 'Usuário';
?>

<!-- Card Principal super compacto -->
<div class="peculio-card-ultra-compact">
    <!-- Seção de Busca super compacta -->
    <div class="busca-section-ultra-compact" style="position: relative;">
        <div class="busca-header-ultra-compact">
            <h5 class="busca-title-ultra-compact">
                <i class="fas fa-search"></i>
                Consultar Pecúlio do Associado
            </h5>
            
            <!-- Botões de ação -->
            <div class="acoes-header-btns mt-2">
                <button type="button" class="btn btn-info btn-sm btn-listar-todos" onclick="Peculio.listarTodos()">
                    <i class="fas fa-list-ul me-1"></i>
                    Ver Todos os Pecúlios
                    <i class="fas fa-arrow-right ms-1"></i>
                </button>
                
                <!-- NOVO: Botão para abrir modal de relatórios -->
                <button type="button" class="btn btn-purple btn-sm btn-relatorios" onclick="Peculio.abrirModalRelatorio()">
                    <i class="fas fa-file-pdf me-1"></i>
                    Gerar Relatório
                    <i class="fas fa-chart-bar ms-1"></i>
                </button>
            </div>
        </div>

        <form class="busca-form-ultra-compact" onsubmit="Peculio.buscar(event)">
            <div class="busca-input-container">
                <label class="form-label-compact" for="rgBuscaPeculio">RG Militar ou Nome</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user-search"></i>
                    </span>
                    <input type="text" class="form-control" id="rgBuscaPeculio"
                        placeholder="Digite o RG militar ou nome completo..." required>
                </div>
            </div>
            
            <div class="busca-actions-ultra-compact">
                <button type="submit" class="btn btn-warning btn-sm" id="btnBuscarPeculio">
                    <i class="fas fa-search me-1"></i>
                    Consultar
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Peculio.limpar()">
                    <i class="fas fa-eraser me-1"></i>
                    Limpar
                </button>
            </div>
        </form>

        <!-- Alert para mensagens de busca -->
        <div id="alertBuscaPeculio" class="alert mt-2" style="display: none;">
            <i class="fas fa-info-circle me-2"></i>
            <span id="alertBuscaPeculioText"></span>
        </div>

        <!-- Loading overlay -->
        <div id="loadingBuscaPeculio" class="loading-overlay" style="display: none;">
            <div class="loading-spinner mb-2"></div>
            <p class="text-muted">Consultando...</p>
        </div>
    </div>

    <!-- Informações do Associado -->
    <div id="associadoInfoContainer" class="associado-info-ultra-compact fade-in" style="display: none;">
        <div class="associado-nome" id="associadoNome"></div>
        <div class="associado-rg" id="associadoRG"></div>
    </div>

    <!-- Dados do Pecúlio -->
    <div id="peculioDadosContainer" class="peculio-dados-ultra-compact fade-in" style="display: none;">
        <h6 class="peculio-title-ultra-compact">
            <i class="fas fa-piggy-bank"></i>
            Informações do Pecúlio
        </h6>

        <div class="dados-grid-ultra-compact">
            <div class="dados-item-ultra-compact">
                <div class="dados-label">
                    <i class="fas fa-calendar-plus"></i>
                    Data Prevista
                </div>
                <div class="dados-value data" id="dataPrevistaPeculio">
                    <!-- Data será inserida aqui -->
                </div>
            </div>

            <div class="dados-item-ultra-compact">
                <div class="dados-label">
                    <i class="fas fa-dollar-sign"></i>
                    Valor
                </div>
                <div class="dados-value" id="valorPeculio">
                    <!-- Valor será inserido aqui -->
                </div>
            </div>

            <div class="dados-item-ultra-compact">
                <div class="dados-label">
                    <i class="fas fa-calendar-check"></i>
                    Recebimento
                </div>
                <div class="dados-value data" id="dataRecebimentoPeculio">
                    <!-- Data será inserida aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- Botões de Ação ultra compactos -->
    <div class="acoes-container-ultra-compact" id="acoesContainer" style="display: none;">
        <button type="button" class="btn btn-warning btn-sm me-2" onclick="Peculio.editar()" id="btnEditarPeculio">
            <i class="fas fa-edit me-1"></i>
            Editar
        </button>
        <button type="button" class="btn btn-success btn-sm" onclick="Peculio.confirmarRecebimento()" id="btnConfirmarRecebimento">
            <i class="fas fa-check-circle me-1"></i>
            Confirmar
        </button>
    </div>
</div>

<!-- ======================= -->
<!-- MODAL DE RELATÓRIOS     -->
<!-- ======================= -->
<div class="modal fade" id="modalRelatorioPeculio" tabindex="-1" aria-labelledby="modalRelatorioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
            <!-- Header do Modal -->
            <div class="modal-header modal-header-relatorio">
                <div class="modal-title-container">
                    <h5 class="modal-title" id="modalRelatorioLabel">
                        <i class="fas fa-file-chart-line me-2"></i>
                        Gerar Relatório de Pecúlios
                    </h5>
                    <p class="modal-subtitle mb-0">Configure os filtros para gerar seu relatório personalizado</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            
            <!-- Body do Modal -->
            <div class="modal-body p-4">
                <form id="formRelatorioPeculio">
                    <!-- Tipo de Relatório -->
                    <div class="filtro-section mb-4">
                        <label class="filtro-section-title">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Tipo de Relatório
                        </label>
                        <div class="tipo-relatorio-grid">
                            <div class="tipo-relatorio-item">
                                <input type="radio" name="tipoRelatorio" id="tipoTodos" value="todos" checked>
                                <label for="tipoTodos" class="tipo-relatorio-label">
                                    <div class="tipo-icon bg-primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="tipo-info">
                                        <span class="tipo-nome">Todos os Pecúlios</span>
                                        <span class="tipo-desc">Lista completa de registros</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="tipo-relatorio-item">
                                <input type="radio" name="tipoRelatorio" id="tipoRecebidos" value="recebidos">
                                <label for="tipoRecebidos" class="tipo-relatorio-label">
                                    <div class="tipo-icon bg-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="tipo-info">
                                        <span class="tipo-nome">Recebidos</span>
                                        <span class="tipo-desc">Já receberam o pecúlio</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="tipo-relatorio-item">
                                <input type="radio" name="tipoRelatorio" id="tipoPendentes" value="pendentes">
                                <label for="tipoPendentes" class="tipo-relatorio-label">
                                    <div class="tipo-icon bg-warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="tipo-info">
                                        <span class="tipo-nome">Pendentes</span>
                                        <span class="tipo-desc">Aguardando recebimento</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="tipo-relatorio-item">
                                <input type="radio" name="tipoRelatorio" id="tipoSemData" value="sem_data">
                                <label for="tipoSemData" class="tipo-relatorio-label">
                                    <div class="tipo-icon bg-secondary">
                                        <i class="fas fa-question-circle"></i>
                                    </div>
                                    <div class="tipo-info">
                                        <span class="tipo-nome">Sem Data Definida</span>
                                        <span class="tipo-desc">Data prevista não informada</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="tipo-relatorio-item">
                                <input type="radio" name="tipoRelatorio" id="tipoVencidos" value="vencidos">
                                <label for="tipoVencidos" class="tipo-relatorio-label">
                                    <div class="tipo-icon bg-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="tipo-info">
                                        <span class="tipo-nome">Vencidos</span>
                                        <span class="tipo-desc">Data prevista já passou</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="tipo-relatorio-item">
                                <input type="radio" name="tipoRelatorio" id="tipoProximos" value="proximos">
                                <label for="tipoProximos" class="tipo-relatorio-label">
                                    <div class="tipo-icon bg-info">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="tipo-info">
                                        <span class="tipo-nome">Próximos 30 Dias</span>
                                        <span class="tipo-desc">Vencem em breve</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Período -->
                    <div class="filtro-section mb-4">
                        <label class="filtro-section-title">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Período (Opcional)
                        </label>
                        <div class="periodo-container">
                            <div class="periodo-toggle mb-3">
                                <input type="checkbox" id="usarPeriodo" class="form-check-input">
                                <label for="usarPeriodo" class="form-check-label ms-2">
                                    Filtrar por período de datas
                                </label>
                            </div>
                            
                            <div id="periodoFields" class="periodo-fields" style="display: none;">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Tipo de Data</label>
                                        <select class="form-select" id="tipoDataPeriodo">
                                            <option value="data_prevista">Data Prevista</option>
                                            <option value="data_recebimento">Data de Recebimento</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Data Inicial</label>
                                        <input type="date" class="form-control" id="dataInicioPeriodo">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Data Final</label>
                                        <input type="date" class="form-control" id="dataFimPeriodo">
                                    </div>
                                </div>
                                
                                <!-- Atalhos de período -->
                                <div class="periodo-atalhos mt-3">
                                    <span class="atalho-label">Atalhos:</span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Peculio.definirPeriodo('mes_atual')">
                                        Mês Atual
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Peculio.definirPeriodo('mes_anterior')">
                                        Mês Anterior
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Peculio.definirPeriodo('trimestre')">
                                        Trimestre
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Peculio.definirPeriodo('ano_atual')">
                                        Ano Atual
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ordenação -->
                    <div class="filtro-section mb-4">
                        <label class="filtro-section-title">
                            <i class="fas fa-sort-amount-down me-2"></i>
                            Ordenação
                        </label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Ordenar por</label>
                                <select class="form-select" id="ordenarPor">
                                    <option value="nome">Nome do Associado (A-Z)</option>
                                    <option value="nome_desc">Nome do Associado (Z-A)</option>
                                    <option value="data_prevista">Data Prevista (Mais Antiga)</option>
                                    <option value="data_prevista_desc">Data Prevista (Mais Recente)</option>
                                    <option value="data_recebimento">Data Recebimento (Mais Antiga)</option>
                                    <option value="data_recebimento_desc">Data Recebimento (Mais Recente)</option>
                                    <option value="valor">Valor (Menor para Maior)</option>
                                    <option value="valor_desc">Valor (Maior para Menor)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Formato de Saída</label>
                                <select class="form-select" id="formatoRelatorio">
                                    <option value="html">Visualizar na Tela (HTML)</option>
                                    <option value="pdf">Exportar PDF</option>
                                    <option value="excel">Exportar Excel</option>
                                    <option value="csv">Exportar CSV</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos a Incluir -->
                    <div class="filtro-section">
                        <label class="filtro-section-title">
                            <i class="fas fa-columns me-2"></i>
                            Campos a Incluir no Relatório
                        </label>
                        <div class="campos-grid">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoNome" checked disabled>
                                <label class="form-check-label" for="campoNome">Nome (obrigatório)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoRG" checked>
                                <label class="form-check-label" for="campoRG">RG</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoCPF" checked>
                                <label class="form-check-label" for="campoCPF">CPF</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoTelefone" checked>
                                <label class="form-check-label" for="campoTelefone">Telefone</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoEmail">
                                <label class="form-check-label" for="campoEmail">E-mail</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoValor" checked>
                                <label class="form-check-label" for="campoValor">Valor</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoDataPrevista" checked>
                                <label class="form-check-label" for="campoDataPrevista">Data Prevista</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoDataRecebimento" checked>
                                <label class="form-check-label" for="campoDataRecebimento">Data Recebimento</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="campoStatus" checked>
                                <label class="form-check-label" for="campoStatus">Status</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Footer do Modal -->
            <div class="modal-footer modal-footer-relatorio">
                <div class="footer-info">
                    <i class="fas fa-info-circle me-1"></i>
                    <span id="previewCount">Carregando...</span>
                </div>
                <div class="footer-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="Peculio.previewRelatorio()">
                        <i class="fas fa-eye me-1"></i>
                        Pré-visualizar
                    </button>
                    <button type="button" class="btn btn-success" onclick="Peculio.gerarRelatorio()">
                        <i class="fas fa-file-export me-1"></i>
                        Gerar Relatório
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================================= -->
<!-- MODAL DE PREVIEW/RESULTADO        -->
<!-- ================================= -->
<div class="modal fade" id="modalPreviewRelatorio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt me-2"></i>
                    <span id="tituloPreviewRelatorio">Relatório de Pecúlios</span>
                </h5>
                <div class="preview-actions">
                    <button type="button" class="btn btn-outline-light btn-sm me-2" onclick="Peculio.imprimirRelatorio()">
                        <i class="fas fa-print me-1"></i>
                        Imprimir
                    </button>
                    <button type="button" class="btn btn-outline-light btn-sm me-2" onclick="Peculio.exportarPDF()">
                        <i class="fas fa-file-pdf me-1"></i>
                        PDF
                    </button>
                    <button type="button" class="btn btn-outline-light btn-sm me-2" onclick="Peculio.exportarExcel()">
                        <i class="fas fa-file-excel me-1"></i>
                        Excel
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="conteudoPreviewRelatorio">
                <!-- Conteúdo do relatório será inserido aqui -->
            </div>
        </div>
    </div>
</div>

<!-- Estilos ultra compactos + estilos do modal de relatórios -->
<style>
/* ========================================= */
/* ESTILOS ORIGINAIS DO PECULIO              */
/* ========================================= */

/* Container principal ultra compacto */
.peculio-card-ultra-compact {
    background: white;
    border-radius: 8px;
    padding: 0.75rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin: 0.25rem 0 0 0;
}

/* Seção de busca ultra compacta */
.busca-section-ultra-compact {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border: 1px solid #dee2e6;
    position: relative;
}

/* Header da busca ultra compacto */
.busca-header-ultra-compact {
    text-align: center;
    margin-bottom: 0.75rem;
}

.busca-title-ultra-compact {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.busca-title-ultra-compact i {
    margin-right: 0.5rem;
    color: var(--warning);
    font-size: 1.2rem;
}

/* Container dos botões de ação no header */
.acoes-header-btns {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Estilo para botão Listar Todos */
.btn-listar-todos {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.5rem 1.2rem;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(23, 162, 184, 0.3);
    transition: all 0.3s ease;
}

.btn-listar-todos:hover {
    background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
    color: white;
}

/* NOVO: Estilo para botão de Relatórios */
.btn-purple {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    border: none;
    color: white;
    font-weight: 600;
    padding: 0.5rem 1.2rem;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(124, 58, 237, 0.3);
    transition: all 0.3s ease;
}

.btn-purple:hover {
    background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(124, 58, 237, 0.4);
    color: white;
}

.btn-purple:active {
    transform: translateY(0);
}

/* Formulário ultra compacto */
.busca-form-ultra-compact {
    max-width: 500px;
    margin: 0 auto;
}

.busca-input-container {
    margin-bottom: 0.75rem;
}

.form-label-compact {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
    display: block;
}

.input-group-text {
    background: var(--warning);
    border-color: var(--warning);
    color: #212529;
    font-weight: 600;
    border-radius: 6px 0 0 6px;
    padding: 0.5rem 0.75rem;
}

.form-control {
    border-radius: 0 6px 6px 0;
    border: 1px solid #e9ecef;
    border-left: none;
    padding: 0.5rem 0.75rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--warning);
    box-shadow: 0 0 0 0.15rem rgba(255, 193, 7, 0.25);
}

/* Ações do formulário ultra compactas */
.busca-actions-ultra-compact {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.btn-warning {
    background: var(--warning);
    border-color: var(--warning);
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
    transform: translateY(-1px);
}

.btn-outline-secondary:hover {
    transform: translateY(-1px);
}

/* Informações do associado ultra compactas */
.associado-info-ultra-compact {
    background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%);
    border-radius: 6px;
    padding: 0.75rem 1rem;
    margin-bottom: 0.75rem;
    border-left: 3px solid var(--primary);
}

.associado-nome {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary);
    margin-bottom: 0.2rem;
}

.associado-rg {
    color: var(--secondary);
    font-size: 0.85rem;
    font-weight: 500;
}

/* Dados do pecúlio ultra compactos */
.peculio-dados-ultra-compact {
    background: white;
    border-radius: 6px;
    padding: 1rem;
    box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
    border-left: 3px solid var(--warning);
    margin-bottom: 0.75rem;
}

.peculio-title-ultra-compact {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.peculio-title-ultra-compact i {
    margin-right: 0.4rem;
    color: var(--warning);
    font-size: 1.1rem;
}

.dados-grid-ultra-compact {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
}

.dados-item-ultra-compact {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-radius: 6px;
    padding: 0.75rem;
    border: 1px solid transparent;
    transition: all 0.3s ease;
    text-align: center;
}

.dados-item-ultra-compact:hover {
    border-color: var(--warning);
    transform: translateY(-1px);
}

.dados-label {
    font-weight: 600;
    color: #856404;
    font-size: 0.8rem;
    margin-bottom: 0.4rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dados-label i {
    margin-right: 0.3rem;
    font-size: 0.9rem;
}

.dados-value {
    color: var(--dark);
    font-size: 1rem;
    font-weight: 700;
}

.dados-value.data {
    color: #d39e00;
}

.dados-value.pendente {
    color: var(--secondary);
    font-style: italic;
}

/* Container de ações ultra compacto */
.acoes-container-ultra-compact {
    text-align: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
    margin-top: 0.5rem;
}

.btn-success {
    background: var(--success);
    border-color: var(--success);
}

.btn-success:hover {
    background: #218838;
    transform: translateY(-1px);
}

/* Loading overlay ultra compacto */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    border-radius: 6px;
    z-index: 1000;
}

.loading-spinner {
    width: 30px;
    height: 30px;
    border: 3px solid var(--light);
    border-top: 3px solid var(--warning);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Animação fade-in */
.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Alerts ultra compactos */
.alert {
    border-radius: 6px;
    border: none;
    padding: 0.5rem 0.75rem;
    font-weight: 500;
    font-size: 0.85rem;
}

/* Estilos para seleção de múltiplos RGs */
.selecao-rg-container {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border: 2px solid #ffc107;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1rem 0;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
}

.selecao-rg-title {
    color: #856404;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}

.selecao-rg-title i {
    margin-right: 0.5rem;
    font-size: 1.2rem;
}

.rg-opcao {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.rg-opcao:hover {
    border-color: #ffc107;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.25);
}

.rg-opcao:active {
    transform: translateY(0);
}

.rg-opcao::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 193, 7, 0.1), transparent);
    transition: left 0.5s ease;
}

.rg-opcao:hover::before {
    left: 100%;
}

.rg-opcao-nome {
    font-weight: 600;
    color: #2c3e50;
    font-size: 1rem;
    margin-bottom: 0.3rem;
}

.rg-opcao-detalhes {
    color: #6c757d;
    font-size: 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.rg-opcao-rg {
    font-weight: 500;
}

.rg-opcao-info {
    font-style: italic;
    color: #28a745;
}

.selecao-rg-actions {
    text-align: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(133, 100, 4, 0.2);
}

/* ========================================= */
/* ESTILOS DO MODAL DE RELATÓRIOS            */
/* ========================================= */

.modal-header-relatorio {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: white;
    padding: 1.5rem;
    border-bottom: none;
}

.modal-title-container {
    display: flex;
    flex-direction: column;
}

.modal-subtitle {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 0.25rem;
}

/* Seções de filtro */
.filtro-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid #e9ecef;
}

.filtro-section-title {
    font-weight: 700;
    color: #2c3e50;
    font-size: 1rem;
    margin-bottom: 1rem;
    display: block;
}

/* Grid de tipos de relatório */
.tipo-relatorio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
}

.tipo-relatorio-item {
    position: relative;
}

.tipo-relatorio-item input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.tipo-relatorio-label {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    gap: 0.75rem;
}

.tipo-relatorio-label:hover {
    border-color: #7c3aed;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
}

.tipo-relatorio-item input[type="radio"]:checked + .tipo-relatorio-label {
    border-color: #7c3aed;
    background: linear-gradient(135deg, #f3e8ff 0%, #ede9fe 100%);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2);
}

.tipo-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.tipo-info {
    display: flex;
    flex-direction: column;
}

.tipo-nome {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.95rem;
}

.tipo-desc {
    font-size: 0.8rem;
    color: #6c757d;
}

/* Container de período */
.periodo-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
}

.periodo-toggle {
    display: flex;
    align-items: center;
}

.periodo-fields {
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
    margin-top: 1rem;
}

.periodo-atalhos {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.atalho-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.85rem;
}

/* Grid de campos */
.campos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem;
    background: white;
    padding: 1rem;
    border-radius: 8px;
}

.campos-grid .form-check {
    margin: 0;
    padding: 0.5rem 0.75rem;
    background: #f8f9fa;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.campos-grid .form-check:hover {
    background: #e9ecef;
}

/* Footer do modal */
.modal-footer-relatorio {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.footer-info {
    font-size: 0.9rem;
    color: #6c757d;
}

.footer-actions {
    display: flex;
    gap: 0.5rem;
}

/* Modal de Preview */
#modalPreviewRelatorio .modal-header {
    padding: 1rem 1.5rem;
}

.preview-actions {
    display: flex;
    align-items: center;
}

#conteudoPreviewRelatorio {
    background: #f0f0f0;
    overflow: auto;
}

/* Estilos do conteúdo do relatório */
.relatorio-container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    min-height: 100vh;
}

.relatorio-header {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: white;
    padding: 2rem;
    text-align: center;
}

.relatorio-logo {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.relatorio-titulo {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.relatorio-subtitulo {
    font-size: 1rem;
    opacity: 0.9;
}

.relatorio-info {
    display: flex;
    justify-content: space-between;
    padding: 1rem 2rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    flex-wrap: wrap;
    gap: 1rem;
}

.relatorio-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.relatorio-info-item i {
    color: #7c3aed;
}

.relatorio-estatisticas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #f3e8ff 0%, #ede9fe 100%);
}

.stat-box {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.stat-box-valor {
    font-size: 1.8rem;
    font-weight: 700;
    color: #7c3aed;
}

.stat-box-label {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 600;
}

.relatorio-tabela-container {
    padding: 1.5rem 2rem;
    overflow-x: auto;
}

.relatorio-tabela {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.relatorio-tabela thead th {
    background: #7c3aed;
    color: white;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    white-space: nowrap;
}

.relatorio-tabela tbody tr:nth-child(even) {
    background: #f8f9fa;
}

.relatorio-tabela tbody tr:hover {
    background: #f3e8ff;
}

.relatorio-tabela tbody td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e9ecef;
}

.relatorio-tabela .status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-recebido {
    background: #d4edda;
    color: #155724;
}

.status-pendente {
    background: #fff3cd;
    color: #856404;
}

.status-vencido {
    background: #f8d7da;
    color: #721c24;
}

.status-sem-data {
    background: #e2e3e5;
    color: #383d41;
}

.relatorio-footer {
    padding: 1.5rem 2rem;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
    text-align: center;
    font-size: 0.85rem;
    color: #6c757d;
}

/* Responsivo */
@media (max-width: 768px) {
    .peculio-card-ultra-compact {
        padding: 0.5rem;
        margin: 0.1rem;
    }
    
    .busca-section-ultra-compact {
        padding: 0.75rem;
    }
    
    .acoes-header-btns {
        flex-direction: column;
        align-items: stretch;
    }
    
    .busca-actions-ultra-compact {
        flex-direction: column;
        align-items: stretch;
    }

    .dados-grid-ultra-compact {
        grid-template-columns: 1fr;
    }
    
    .acoes-container-ultra-compact .btn {
        display: block;
        width: 100%;
        margin-bottom: 0.4rem;
    }

    .btn-listar-todos,
    .btn-purple {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .tipo-relatorio-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-footer-relatorio {
        flex-direction: column;
        gap: 1rem;
    }
    
    .footer-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .footer-actions .btn {
        width: 100%;
    }
    
    .relatorio-info {
        flex-direction: column;
    }
    
    .relatorio-estatisticas {
        grid-template-columns: 1fr 1fr;
    }
}

@media print {
    .relatorio-container {
        box-shadow: none;
    }
    
    .preview-actions {
        display: none !important;
    }
    
    .modal-header {
        display: none !important;
    }
}
</style>

<!-- Script inline para passar dados para o JavaScript -->
<script>
// Passar dados de permissão para o JavaScript quando a partial carregar
window.peculioPermissions = {
    temPermissao: <?php echo json_encode($temPermissaoFinanceiro); ?>,
    isFinanceiro: <?php echo json_encode($isFinanceiro); ?>,
    isPresidencia: <?php echo json_encode($isPresidencia); ?>
};

// Mostrar botões de ação quando houver dados carregados
window.mostrarAcoesPeculio = function() {
    const container = document.getElementById('acoesContainer');
    if (container) {
        container.style.display = 'block';
    }
}

console.log('✅ Gestão de Pecúlio carregada com sistema de relatórios');
</script>