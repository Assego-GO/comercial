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
    $deptIdInt = (int)$deptId;

    if ($deptIdInt == 2) {
        $temPermissaoFinanceiro = true;
        $isFinanceiro = true;
    } elseif ($deptIdInt == 1) {
        $temPermissaoFinanceiro = true;
        $isPresidencia = true;
    } else {
        $motivoNegacao = "Departamento não autorizado. Apenas Financeiro e Presidência.";
    }
} else {
    $motivoNegacao = 'Departamento não identificado na sessão.';
}

if (!$temPermissaoFinanceiro) {
    echo '<div class="alert alert-danger">
        <h4><i class="fas fa-ban me-2"></i>Acesso Negado</h4>
        <p>' . htmlspecialchars($motivoNegacao) . '</p>
    </div>';
    exit;
}

// Obter mês atual
$mesAtual = date('m/Y');
?>

<!-- Google Fonts - Fontes Premium -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Container Principal -->
<div class="inadim-container">
    
    <!-- Header com Stats Compactas -->
    <div class="inadim-header">
        <div class="inadim-header-content">
            <div class="inadim-title-section">
                <div class="inadim-icon-badge">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h2 class="inadim-title">Gestão de Inadimplência</h2>
                    <p class="inadim-subtitle">Monitoramento e controle financeiro de associados</p>
                </div>
            </div>
            <div class="inadim-quick-stats">
                <div class="quick-stat danger">
                    <span class="quick-stat-value" id="totalInadimplentesInadim">0</span>
                    <span class="quick-stat-label">Inadimplentes</span>
                </div>
                <div class="quick-stat warning">
                    <span class="quick-stat-value" id="percentualInadimplenciaInadim">0%</span>
                    <span class="quick-stat-label">da Base</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros Modernos -->
    <div class="inadim-filters">
        <div class="filter-group">
            <div class="filter-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="filtroNomeInadim" placeholder="Buscar por nome...">
            </div>
            <div class="filter-input-wrapper">
                <i class="fas fa-id-card"></i>
                <input type="text" id="filtroRGInadim" placeholder="Buscar por RG...">
            </div>
            <select id="filtroVinculoInadim" class="filter-select">
                <option value="">Todos os vínculos</option>
                <option value="ATIVO">Ativo</option>
                <option value="APOSENTADO">Aposentado</option>
                <option value="PENSIONISTA">Pensionista</option>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn-filter" onclick="ListaInadimplentes.aplicarFiltros(event)">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <button class="btn-filter secondary" onclick="ListaInadimplentes.limparFiltros()">
                <i class="fas fa-times"></i> Limpar
            </button>
        </div>
    </div>

    <!-- Tabela Moderna -->
    <div class="inadim-table-container">
        <div class="inadim-table-header">
            <span class="table-title">
                <i class="fas fa-list"></i>
                Lista de Inadimplentes
            </span>
            <div class="table-actions">
                <button class="btn-export" onclick="ListaInadimplentes.exportarExcel()">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="btn-export" onclick="ListaInadimplentes.exportarPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>
        
        <div class="table-wrapper">
            <table class="inadim-table">
                <thead>
                    <tr>
                        <th>Associado</th>
                        <th>Documentos</th>
                        <th>Contato</th>
                        <th>Vínculo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="tabelaInadimplentesInadim">
                </tbody>
            </table>
        </div>

        <!-- Loading State -->
        <div id="loadingInadimplentesInadim" class="loading-state">
            <div class="loading-spinner"></div>
            <span>Carregando dados...</span>
        </div>
    </div>
</div>

<!-- ==================== MODAL DE DETALHES DO ASSOCIADO ==================== -->
<div class="modal-overlay" id="modalOverlayInadim">
    <div class="modal-container" id="modalContainerInadim">
        
        <!-- Modal Header -->
        <div class="modal-header-custom">
            <div class="modal-header-bg"></div>
            <div class="modal-header-content">
                <button class="modal-close-btn" onclick="ListaInadimplentes.fecharModal()">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="profile-section">
                    <div class="profile-avatar" id="modalAvatarInadim">
                        <span>--</span>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name" id="modalNomeInadim">Carregando...</h2>
                        <div class="profile-meta">
                            <span class="meta-item">
                                <i class="fas fa-fingerprint"></i>
                                <span id="modalCPFHeaderInadim">---</span>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-id-badge"></i>
                                <span id="modalIDHeaderInadim">ID: ---</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Status Badge Grande -->
                <div class="status-alert" id="statusAlertInadim">
                    <div class="status-alert-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="status-alert-content">
                        <span class="status-label">INADIMPLENTE</span>
                        <span class="status-detail" id="statusDetailInadim">Verificando situação...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Navigation -->
        <div class="modal-nav">
            <button class="nav-tab active" data-tab="pessoais" onclick="ListaInadimplentes.trocarTab('pessoais')">
                <i class="fas fa-user"></i>
                <span>Dados Pessoais</span>
            </button>
            <button class="nav-tab" data-tab="financeiro" onclick="ListaInadimplentes.trocarTab('financeiro')">
                <i class="fas fa-wallet"></i>
                <span>Financeiro</span>
            </button>
            <button class="nav-tab" data-tab="militar" onclick="ListaInadimplentes.trocarTab('militar')">
                <i class="fas fa-shield-alt"></i>
                <span>Militar</span>
            </button>
            <button class="nav-tab" data-tab="observacoes" onclick="ListaInadimplentes.trocarTab('observacoes')">
                <i class="fas fa-comments"></i>
                <span>Observações</span>
                <span class="tab-badge" id="obsCountBadge" style="display: none;">0</span>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="modal-body-custom">
            
            <!-- Loading State -->
            <div class="modal-loading" id="modalLoadingInadim">
                <div class="loading-animation">
                    <div class="loading-circle"></div>
                    <div class="loading-circle"></div>
                    <div class="loading-circle"></div>
                </div>
                <p>Carregando informações do associado...</p>
            </div>

            <!-- Content Tabs -->
            <div class="modal-content-wrapper" id="modalContentInadim" style="display: none;">
                
                <!-- Tab: Dados Pessoais -->
                <div class="tab-content active" id="tab-pessoais">
                    <div class="content-grid">
                        <!-- Card Identificação -->
                        <div class="info-card-modern">
                            <div class="card-header-modern">
                                <div class="card-icon blue">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <h3>Identificação</h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="info-row">
                                    <div class="info-label">Nome Completo</div>
                                    <div class="info-value" id="detalheNomeInadim">-</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">CPF</div>
                                    <div class="info-value mono" id="detalheCPFInadim">-</div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">RG Militar</div>
                                    <div class="info-value mono" id="detalheRGInadim">-</div>
                                </div>
                                <div class="info-grid-2">
                                    <div class="info-row">
                                        <div class="info-label">Nascimento</div>
                                        <div class="info-value" id="detalheNascimentoInadim">-</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Sexo</div>
                                        <div class="info-value" id="detalheSexoInadim">-</div>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Estado Civil</div>
                                    <div class="info-value" id="detalheEstadoCivilInadim">-</div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Contato -->
                        <div class="info-card-modern">
                            <div class="card-header-modern">
                                <div class="card-icon green">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <h3>Contato</h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="contact-item" id="contatoTelefoneInadim">
                                    <div class="contact-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="contact-info">
                                        <span class="contact-label">Telefone</span>
                                        <span class="contact-value" id="detalheTelefoneInadim">-</span>
                                    </div>
                                    <button class="contact-action" onclick="ListaInadimplentes.ligarTelefone()" title="Ligar">
                                        <i class="fas fa-phone-volume"></i>
                                    </button>
                                </div>
                                <div class="contact-item" id="contatoEmailInadim">
                                    <div class="contact-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-info">
                                        <span class="contact-label">E-mail</span>
                                        <span class="contact-value" id="detalheEmailInadim">-</span>
                                    </div>
                                    <button class="contact-action" onclick="ListaInadimplentes.enviarEmail()" title="Enviar e-mail">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Card Endereço -->
                        <div class="info-card-modern full-width">
                            <div class="card-header-modern">
                                <div class="card-icon purple">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <h3>Endereço</h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="address-display">
                                    <div class="address-main" id="detalheEnderecoInadim">-</div>
                                    <div class="address-secondary">
                                        <span id="detalheBairroInadim">-</span> • 
                                        <span id="detalheCidadeInadim">-</span>
                                    </div>
                                    <div class="address-cep">
                                        CEP: <span id="detalheCEPInadim">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Financeiro -->
                <div class="tab-content" id="tab-financeiro">
                    <div class="content-grid">
                        <!-- Card Débito Principal -->
                        <div class="debt-card">
                            <div class="debt-header">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Débito Total</span>
                            </div>
                            <div class="debt-amount" id="valorTotalDebitoInadim">R$ 0,00</div>
                            <div class="debt-detail">
                                <span id="mesesAtrasoInadim">0</span> meses em atraso
                            </div>
                        </div>

                        <!-- Card Timeline Pagamentos -->
                        <div class="info-card-modern">
                            <div class="card-header-modern">
                                <div class="card-icon orange">
                                    <i class="fas fa-history"></i>
                                </div>
                                <h3>Histórico</h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="payment-timeline">
                                    <div class="timeline-item danger">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-content">
                                            <span class="timeline-label">Última Contribuição</span>
                                            <span class="timeline-value" id="ultimaContribuicaoInadim">-</span>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-content">
                                            <span class="timeline-label">Valor Mensal</span>
                                            <span class="timeline-value" id="valorMensalInadim">R$ 86,55</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Dados Bancários -->
                        <div class="info-card-modern full-width">
                            <div class="card-header-modern">
                                <div class="card-icon teal">
                                    <i class="fas fa-university"></i>
                                </div>
                                <h3>Informações Financeiras</h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="info-grid-3">
                                    <div class="info-row">
                                        <div class="info-label">Tipo Associado</div>
                                        <div class="info-value" id="detalheTipoAssociadoInadim">-</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Vínculo</div>
                                        <div class="info-value" id="detalheVinculoInadim">-</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Local Débito</div>
                                        <div class="info-value" id="detalheLocalDebitoInadim">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Militar -->
                <div class="tab-content" id="tab-militar">
                    <div class="content-grid">
                        <div class="military-card">
                            <div class="military-badge">
                                <div class="badge-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="badge-info">
                                    <span class="badge-rank" id="detalhePatenteInadim">-</span>
                                    <span class="badge-corp" id="detalheCorporacaoInadim">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="info-card-modern full-width">
                            <div class="card-header-modern">
                                <div class="card-icon indigo">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h3>Lotação</h3>
                            </div>
                            <div class="card-body-modern">
                                <div class="info-grid-2">
                                    <div class="info-row">
                                        <div class="info-label">Lotação</div>
                                        <div class="info-value" id="detalheLotacaoInadim">-</div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Unidade</div>
                                        <div class="info-value" id="detalheUnidadeInadim">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Observações -->
                <div class="tab-content" id="tab-observacoes">
                    <div class="obs-header">
                        <h3><i class="fas fa-comments"></i> Observações</h3>
                        <button class="btn-add-obs" onclick="ListaInadimplentes.adicionarObservacao()">
                            <i class="fas fa-plus"></i> Nova Observação
                        </button>
                    </div>
                    <div class="obs-list" id="listaObservacoesInadim">
                        <div class="obs-empty">
                            <i class="fas fa-comment-slash"></i>
                            <p>Nenhuma observação registrada</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="modal-footer-custom">
            <div class="footer-info">
                <span class="last-update">
                    <i class="fas fa-clock"></i>
                    Última atualização: <span id="lastUpdateInadim">-</span>
                </span>
            </div>
            <div class="footer-actions">
                <button class="btn-action secondary" onclick="ListaInadimplentes.fecharModal()">
                    <i class="fas fa-times"></i> Fechar
                </button>
                <button class="btn-action warning" onclick="ListaInadimplentes.abrirModalPendencias()">
                    <i class="fas fa-file-invoice-dollar"></i> Ver Pendências
                </button>
                <button class="btn-action success" onclick="ListaInadimplentes.registrarPagamentoModal()">
                    <i class="fas fa-check-circle"></i> Registrar Pagamento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MODAL DE PENDÊNCIAS FINANCEIRAS ==================== -->
<div class="modal-overlay" id="modalOverlayPendencias">
    <div class="modal-container modal-pendencias" id="modalContainerPendencias">
        
        <!-- Header do Modal de Pendências -->
        <div class="pendencias-header">
            <div class="pendencias-header-content">
                <button class="modal-close-btn" onclick="ListaInadimplentes.fecharModalPendencias()">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="pendencias-title-section">
                    <div class="pendencias-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="pendencias-title-info">
                        <h2>Pendências Financeiras</h2>
                        <p id="pendenciasAssociadoNome">Carregando...</p>
                    </div>
                </div>
                
                <div class="pendencias-info-bar">
                    <div class="info-bar-item">
                        <span class="info-bar-label">Associado</span>
                        <span class="info-bar-value" id="pendenciasAssociadoId">ID: ---</span>
                    </div>
                    <div class="info-bar-item">
                        <span class="info-bar-label">Mês Atual</span>
                        <span class="info-bar-value"><?php echo $mesAtual; ?></span>
                    </div>
                    <div class="info-bar-item highlight">
                        <span class="info-bar-label">Total em Débito</span>
                        <span class="info-bar-value" id="pendenciasTotalDebito">R$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Corpo do Modal de Pendências -->
        <div class="pendencias-body">
            
            <!-- Loading -->
            <div class="pendencias-loading" id="pendenciasLoading">
                <div class="loading-animation">
                    <div class="loading-circle"></div>
                    <div class="loading-circle"></div>
                    <div class="loading-circle"></div>
                </div>
                <p>Carregando pendências...</p>
            </div>

            <!-- Conteúdo das Pendências -->
            <div class="pendencias-content" id="pendenciasContent" style="display: none;">
                
                <!-- Tabela de Pendências por Mês -->
                <div class="pendencias-table-container">
                    <div class="pendencias-table-header">
                        <h3><i class="fas fa-calendar-alt"></i> Dívidas Anteriores</h3>
                    </div>
                    
                    <table class="pendencias-table">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th>Valor Original</th>
                                <th>Status</th>
                                <th>Valor p/ Acerto</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody id="pendenciasTableBody">
                            <!-- Linhas serão inseridas via JavaScript -->
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3"><strong>Total de dívidas anteriores:</strong></td>
                                <td colspan="2"><strong id="pendenciasSomaTotal">R$ 0,00</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Seção de Renegociação -->
                <div class="renegociacao-section">
                    <div class="renegociacao-header">
                        <h3><i class="fas fa-handshake"></i> Renegociação de Dívidas</h3>
                    </div>
                    <div class="renegociacao-body">
                        <div class="renegociacao-form">
                            <div class="form-group">
                                <label for="valorRenegociado">Valor Renegociado:</label>
                                <div class="input-with-prefix">
                                    <span class="input-prefix">R$</span>
                                    <input type="text" id="valorRenegociado" class="form-input" placeholder="0,00">
                                </div>
                            </div>
                            <button class="btn-renegociar" onclick="ListaInadimplentes.lancarRenegociacao()">
                                <i class="fas fa-file-signature"></i>
                                Lançar Renegociação de dívidas no próximo mês
                            </button>
                        </div>
                        <div class="renegociacao-info">
                            <i class="fas fa-info-circle"></i>
                            <p>O valor renegociado será incluído na próxima fatura do associado. Esta ação ficará registrada no histórico financeiro.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Footer do Modal de Pendências -->
        <div class="pendencias-footer">
            <div class="footer-left">
                <button class="btn-action secondary" onclick="ListaInadimplentes.imprimirPendencias()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button class="btn-action secondary" onclick="ListaInadimplentes.exportarPendenciasPDF()">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
            </div>
            <div class="footer-right">
                <button class="btn-action secondary" onclick="ListaInadimplentes.fecharModalPendencias()">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
                <button class="btn-action success" onclick="ListaInadimplentes.quitarTodasDividas()">
                    <i class="fas fa-check-double"></i> Quitar Todas as Dívidas
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container-custom" id="toastContainerInadim"></div>

<!-- ==================== ESTILOS ==================== -->
<style>
/* ===== RESET E VARIÁVEIS ===== */
:root {
    --font-primary: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
    
    /* Cores principais */
    --color-primary: #2563eb;
    --color-primary-dark: #1d4ed8;
    --color-primary-light: #3b82f6;
    
    --color-danger: #dc2626;
    --color-danger-dark: #b91c1c;
    --color-danger-light: #ef4444;
    
    --color-success: #16a34a;
    --color-warning: #d97706;
    --color-info: #0891b2;
    
    /* Neutrals */
    --color-gray-50: #f9fafb;
    --color-gray-100: #f3f4f6;
    --color-gray-200: #e5e7eb;
    --color-gray-300: #d1d5db;
    --color-gray-400: #9ca3af;
    --color-gray-500: #6b7280;
    --color-gray-600: #4b5563;
    --color-gray-700: #374151;
    --color-gray-800: #1f2937;
    --color-gray-900: #111827;
    
    /* Shadows */
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    
    /* Border radius */
    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;
    --radius-xl: 24px;
}

/* ===== CONTAINER PRINCIPAL ===== */
.inadim-container {
    font-family: var(--font-primary);
    background: var(--color-gray-50);
    min-height: 100%;
    padding: 0;
}

/* ===== HEADER ===== */
.inadim-header {
    background: linear-gradient(135deg, var(--color-gray-900) 0%, var(--color-gray-800) 100%);
    padding: 1.5rem 2rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
}

.inadim-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.inadim-title-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.inadim-icon-badge {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--color-danger) 0%, var(--color-danger-dark) 100%);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 4px 14px rgb(220 38 38 / 0.4);
}

.inadim-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: white;
    margin: 0;
    letter-spacing: -0.02em;
}

.inadim-subtitle {
    color: var(--color-gray-400);
    font-size: 0.875rem;
    margin: 0;
}

.inadim-quick-stats {
    display: flex;
    gap: 1rem;
}

.quick-stat {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius-md);
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.quick-stat.danger .quick-stat-value {
    color: var(--color-danger-light);
}

.quick-stat.warning .quick-stat-value {
    color: var(--color-warning);
}

.quick-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    display: block;
    line-height: 1;
}

.quick-stat-label {
    font-size: 0.75rem;
    color: var(--color-gray-400);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ===== FILTROS ===== */
.inadim-filters {
    background: white;
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-gray-200);
}

.filter-group {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    flex: 1;
}

.filter-input-wrapper {
    position: relative;
    flex: 1;
    min-width: 180px;
}

.filter-input-wrapper i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-gray-400);
    font-size: 0.875rem;
}

.filter-input-wrapper input {
    width: 100%;
    padding: 0.625rem 0.75rem 0.625rem 2.25rem;
    border: 2px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    font-family: var(--font-primary);
    transition: all 0.2s ease;
    background: var(--color-gray-50);
}

.filter-input-wrapper input:focus {
    outline: none;
    border-color: var(--color-primary);
    background: white;
    box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
}

.filter-select {
    padding: 0.625rem 2rem 0.625rem 0.75rem;
    border: 2px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    font-family: var(--font-primary);
    background: var(--color-gray-50);
    cursor: pointer;
    min-width: 160px;
    transition: all 0.2s ease;
}

.filter-select:focus {
    outline: none;
    border-color: var(--color-primary);
    background: white;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-filter {
    padding: 0.625rem 1rem;
    border: none;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    font-weight: 600;
    font-family: var(--font-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    background: var(--color-primary);
    color: white;
}

.btn-filter:hover {
    background: var(--color-primary-dark);
    transform: translateY(-1px);
}

.btn-filter.secondary {
    background: var(--color-gray-200);
    color: var(--color-gray-700);
}

.btn-filter.secondary:hover {
    background: var(--color-gray-300);
}

/* ===== TABELA ===== */
.inadim-table-container {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--color-gray-200);
}

.inadim-table-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--color-gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--color-gray-50);
}

.table-title {
    font-weight: 700;
    color: var(--color-gray-800);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-title i {
    color: var(--color-primary);
}

.table-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-export {
    padding: 0.5rem 0.875rem;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    background: white;
    font-size: 0.8125rem;
    font-family: var(--font-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s ease;
    color: var(--color-gray-600);
}

.btn-export:hover {
    background: var(--color-gray-100);
    border-color: var(--color-gray-400);
}

.table-wrapper {
    overflow-x: auto;
}

.inadim-table {
    width: 100%;
    border-collapse: collapse;
}

.inadim-table thead th {
    background: var(--color-gray-50);
    padding: 0.875rem 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-gray-500);
    border-bottom: 2px solid var(--color-gray-200);
}

.inadim-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--color-gray-100);
    font-size: 0.875rem;
    color: var(--color-gray-700);
}

.inadim-table tbody tr {
    transition: background 0.15s ease;
}

.inadim-table tbody tr:hover {
    background: var(--color-gray-50);
}

/* Células da tabela */
.cell-associado {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.cell-nome {
    font-weight: 600;
    color: var(--color-gray-900);
}

.cell-email {
    font-size: 0.75rem;
    color: var(--color-gray-500);
}

.cell-docs {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.cell-doc {
    font-family: var(--font-mono);
    font-size: 0.8125rem;
    background: var(--color-gray-100);
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
}

.cell-contato a {
    color: var(--color-primary);
    text-decoration: none;
    font-size: 0.875rem;
}

.cell-contato a:hover {
    text-decoration: underline;
}

.badge-vinculo {
    padding: 0.25rem 0.625rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--color-gray-100);
    color: var(--color-gray-600);
}

.badge-status {
    padding: 0.375rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--color-danger) 0%, var(--color-danger-dark) 100%);
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.cell-actions {
    display: flex;
    gap: 0.375rem;
}

.btn-table-action {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.btn-table-action.view {
    background: var(--color-primary);
    color: white;
}

.btn-table-action.view:hover {
    background: var(--color-primary-dark);
    transform: scale(1.05);
}

.btn-table-action.pendencias {
    background: var(--color-warning);
    color: white;
}

.btn-table-action.pendencias:hover {
    background: #b45309;
    transform: scale(1.05);
}

.btn-table-action.pay {
    background: var(--color-success);
    color: white;
}

.btn-table-action.pay:hover {
    background: #15803d;
}

/* Loading State */
.loading-state {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    color: var(--color-gray-500);
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--color-gray-200);
    border-top-color: var(--color-primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ===== MODAL DE DETALHES ===== */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.active {
    display: flex;
    opacity: 1;
}

.modal-container {
    background: white;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    border-radius: var(--radius-xl);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-xl), 0 0 0 1px rgba(0,0,0,0.05);
    transform: scale(0.95) translateY(20px);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-container {
    transform: scale(1) translateY(0);
}

/* Modal Header - CORRIGIDO */
.modal-header-custom {
    position: relative;
    padding: 1.5rem 1.5rem 1.25rem;
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    overflow: hidden;
    min-height: auto;
}

.modal-header-bg {
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}

.modal-header-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.modal-close-btn {
    position: absolute;
    top: 0;
    right: 0;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-size: 1rem;
    z-index: 10;
}

.modal-close-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

/* Profile Section */
.profile-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-right: 50px;
}

.profile-avatar {
    width: 56px;
    height: 56px;
    min-width: 56px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 800;
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
    border: 2px solid rgba(255, 255, 255, 0.15);
}

.profile-info {
    flex: 1;
    min-width: 0;
}

.profile-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
    margin: 0 0 0.375rem 0;
    letter-spacing: -0.01em;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8125rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.08);
    padding: 0.25rem 0.625rem;
    border-radius: 6px;
}

.meta-item i {
    font-size: 0.7rem;
    opacity: 0.8;
}

/* Status Alert */
.status-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(220, 38, 38, 0.15);
    border: 1px solid rgba(220, 38, 38, 0.25);
    border-radius: 10px;
    padding: 0.625rem 1rem;
}

.status-alert-icon {
    width: 32px;
    height: 32px;
    min-width: 32px;
    border-radius: 50%;
    background: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    animation: pulse-danger 2s infinite;
}

@keyframes pulse-danger {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
}

.status-alert-content {
    display: flex;
    flex-direction: column;
    gap: 0;
    line-height: 1.3;
}

.status-label {
    font-weight: 700;
    color: #fca5a5;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.status-detail {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.75rem;
}

/* Modal Navigation */
.modal-nav {
    display: flex;
    border-bottom: 1px solid var(--color-gray-200);
    background: white;
    padding: 0 1rem;
}

.nav-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem;
    border: none;
    background: none;
    font-family: var(--font-primary);
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-gray-500);
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
}

.nav-tab::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 3px;
    background: var(--color-primary);
    border-radius: 3px 3px 0 0;
    transform: translateX(-50%);
    transition: width 0.3s ease;
}

.nav-tab:hover {
    color: var(--color-gray-700);
    background: var(--color-gray-50);
}

.nav-tab.active {
    color: var(--color-primary);
}

.nav-tab.active::after {
    width: 100%;
}

.nav-tab i {
    font-size: 1rem;
}

.tab-badge {
    background: var(--color-danger);
    color: white;
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 0.125rem 0.375rem;
    border-radius: 50px;
    min-width: 18px;
    text-align: center;
}

/* Modal Body */
.modal-body-custom {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem 2rem;
    background: var(--color-gray-50);
}

/* Modal Loading */
.modal-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
}

.loading-animation {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.loading-circle {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--color-primary);
    animation: bounce-loading 1.4s ease-in-out infinite both;
}

.loading-circle:nth-child(1) { animation-delay: -0.32s; }
.loading-circle:nth-child(2) { animation-delay: -0.16s; }

@keyframes bounce-loading {
    0%, 80%, 100% { transform: scale(0); opacity: 0.5; }
    40% { transform: scale(1); opacity: 1; }
}

.modal-loading p {
    color: var(--color-gray-500);
    margin: 0;
}

/* Tab Content */
.tab-content {
    display: none;
    animation: fadeSlideIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeSlideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.content-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}

/* Info Cards */
.info-card-modern {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-gray-200);
}

.info-card-modern.full-width {
    grid-column: 1 / -1;
}

.card-header-modern {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--color-gray-100);
    background: var(--color-gray-50);
}

.card-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
}

.card-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.card-icon.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
.card-icon.purple { background: linear-gradient(135deg, #a855f7, #9333ea); }
.card-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); }
.card-icon.teal { background: linear-gradient(135deg, #14b8a6, #0d9488); }
.card-icon.indigo { background: linear-gradient(135deg, #6366f1, #4f46e5); }

.card-header-modern h3 {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 700;
    color: var(--color-gray-800);
}

.card-body-modern {
    padding: 1.25rem;
}

.info-row {
    padding: 0.625rem 0;
    border-bottom: 1px solid var(--color-gray-100);
}

.info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-row:first-child {
    padding-top: 0;
}

.info-label {
    font-size: 0.75rem;
    color: var(--color-gray-500);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.info-value {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--color-gray-900);
}

.info-value.mono {
    font-family: var(--font-mono);
    font-size: 0.875rem;
    background: var(--color-gray-100);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
}

.info-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.info-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

/* Contact Items */
.contact-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.875rem;
    background: var(--color-gray-50);
    border-radius: var(--radius-md);
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
}

.contact-item:last-child {
    margin-bottom: 0;
}

.contact-item:hover {
    background: var(--color-gray-100);
}

.contact-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary);
    box-shadow: var(--shadow-sm);
}

.contact-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.contact-label {
    font-size: 0.75rem;
    color: var(--color-gray-500);
}

.contact-value {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--color-gray-900);
}

.contact-action {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: var(--color-primary);
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.contact-action:hover {
    background: var(--color-primary-dark);
    transform: scale(1.1);
}

/* Address Display */
.address-display {
    text-align: center;
    padding: 1rem;
    background: var(--color-gray-50);
    border-radius: var(--radius-md);
}

.address-main {
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-gray-900);
    margin-bottom: 0.375rem;
}

.address-secondary {
    color: var(--color-gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.375rem;
}

.address-cep {
    font-family: var(--font-mono);
    font-size: 0.8125rem;
    color: var(--color-gray-500);
}

/* Debt Card */
.debt-card {
    background: linear-gradient(135deg, var(--color-danger) 0%, var(--color-danger-dark) 100%);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    color: white;
    text-align: center;
    grid-column: 1 / -1;
}

.debt-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    opacity: 0.9;
}

.debt-amount {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.25rem;
    letter-spacing: -0.02em;
}

.debt-detail {
    opacity: 0.8;
    font-size: 0.9375rem;
}

/* Payment Timeline */
.payment-timeline {
    position: relative;
    padding-left: 1rem;
}

.timeline-item {
    position: relative;
    padding-left: 1.5rem;
    padding-bottom: 1rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 8px;
    bottom: -8px;
    width: 2px;
    background: var(--color-gray-200);
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-dot {
    position: absolute;
    left: -4px;
    top: 6px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--color-gray-300);
    border: 2px solid white;
}

.timeline-item.danger .timeline-dot {
    background: var(--color-danger);
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2);
}

.timeline-content {
    display: flex;
    flex-direction: column;
}

.timeline-label {
    font-size: 0.75rem;
    color: var(--color-gray-500);
}

.timeline-value {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--color-gray-900);
}

/* Military Card */
.military-card {
    grid-column: 1 / -1;
    background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
    border-radius: var(--radius-lg);
    padding: 2rem;
    display: flex;
    justify-content: center;
}

.military-badge {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 1.25rem 2rem;
    border-radius: var(--radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.badge-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #1e3a5f;
    box-shadow: 0 8px 24px rgba(251, 191, 36, 0.3);
}

.badge-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.badge-rank {
    font-size: 1.5rem;
    font-weight: 800;
    color: white;
}

.badge-corp {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
}

/* Observações */
.obs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.obs-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-gray-800);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-add-obs {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-primary);
    color: white;
    font-family: var(--font-primary);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s ease;
}

.btn-add-obs:hover {
    background: var(--color-primary-dark);
}

.obs-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.obs-empty {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--color-gray-400);
}

.obs-empty i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    display: block;
}

.obs-item {
    background: white;
    border-radius: var(--radius-md);
    padding: 1rem;
    border-left: 4px solid var(--color-primary);
    box-shadow: var(--shadow-sm);
}

.obs-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.obs-author {
    font-weight: 600;
    color: var(--color-gray-800);
    font-size: 0.875rem;
}

.obs-date {
    font-size: 0.75rem;
    color: var(--color-gray-500);
}

.obs-text {
    color: var(--color-gray-700);
    font-size: 0.875rem;
    line-height: 1.5;
}

/* Modal Footer */
.modal-footer-custom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    border-top: 1px solid var(--color-gray-200);
    background: white;
}

.footer-info {
    display: flex;
    align-items: center;
}

.last-update {
    font-size: 0.75rem;
    color: var(--color-gray-500);
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.footer-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-action {
    padding: 0.625rem 1.25rem;
    border: none;
    border-radius: var(--radius-md);
    font-family: var(--font-primary);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-action.secondary {
    background: var(--color-gray-200);
    color: var(--color-gray-700);
}

.btn-action.secondary:hover {
    background: var(--color-gray-300);
}

.btn-action.warning {
    background: var(--color-warning);
    color: white;
}

.btn-action.warning:hover {
    background: #b45309;
}

.btn-action.success {
    background: var(--color-success);
    color: white;
}

.btn-action.success:hover {
    background: #15803d;
}

/* ===== MODAL DE PENDÊNCIAS FINANCEIRAS ===== */
.modal-pendencias {
    max-width: 1100px;
}

/* Header do Modal de Pendências */
.pendencias-header {
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
    padding: 1.5rem 2rem;
    position: relative;
}

.pendencias-header-content {
    position: relative;
}

.pendencias-title-section {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
    padding-right: 50px;
}

.pendencias-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.pendencias-title-info h2 {
    font-size: 1.375rem;
    font-weight: 700;
    color: white;
    margin: 0 0 0.25rem 0;
}

.pendencias-title-info p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.875rem;
    margin: 0;
}

.pendencias-info-bar {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.info-bar-item {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 0.75rem 1.25rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    flex: 1;
    min-width: 150px;
}

.info-bar-item.highlight {
    background: rgba(220, 38, 38, 0.2);
    border-color: rgba(220, 38, 38, 0.3);
}

.info-bar-label {
    display: block;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 0.25rem;
}

.info-bar-value {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    color: white;
}

.info-bar-item.highlight .info-bar-value {
    color: #fca5a5;
}

/* Corpo do Modal de Pendências */
.pendencias-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem 2rem;
    background: var(--color-gray-50);
}

.pendencias-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
}

/* Tabela de Pendências */
.pendencias-table-container {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    margin-bottom: 1.5rem;
}

.pendencias-table-header {
    padding: 1rem 1.5rem;
    background: var(--color-gray-50);
    border-bottom: 1px solid var(--color-gray-200);
}

.pendencias-table-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-gray-800);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pendencias-table-header h3 i {
    color: var(--color-warning);
}

.pendencias-table {
    width: 100%;
    border-collapse: collapse;
}

.pendencias-table thead th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-gray-500);
    background: var(--color-gray-50);
    border-bottom: 2px solid var(--color-gray-200);
}

.pendencias-table tbody td {
    padding: 1rem 1.25rem;
    font-size: 0.9375rem;
    color: var(--color-gray-700);
    border-bottom: 1px solid var(--color-gray-100);
    vertical-align: middle;
}

.pendencias-table tbody tr {
    transition: background 0.15s ease;
}

.pendencias-table tbody tr:hover {
    background: var(--color-gray-50);
}

/* Descrição da pendência */
.pendencia-descricao {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.pendencia-tipo {
    font-weight: 600;
    color: var(--color-gray-900);
}

.pendencia-mes {
    font-size: 0.75rem;
    color: var(--color-gray-500);
}

/* Valor original */
.valor-original {
    font-family: var(--font-mono);
    font-weight: 600;
    color: var(--color-danger);
}

/* Status da pendência */
.pendencia-status {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
}

.pendencia-status.sem-retorno {
    background: #fef3c7;
    color: #92400e;
}

.pendencia-status.parcial {
    background: #dbeafe;
    color: #1e40af;
}

/* Input de valor para acerto */
.input-valor-acerto {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.input-valor-acerto .prefix {
    font-size: 0.875rem;
    color: var(--color-gray-500);
    font-weight: 500;
}

.input-valor-acerto input {
    width: 100px;
    padding: 0.5rem 0.75rem;
    border: 2px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    font-family: var(--font-mono);
    font-size: 0.875rem;
    text-align: right;
    transition: all 0.2s ease;
}

.input-valor-acerto input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Botão de acerto */
.btn-acerto {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-primary);
    color: white;
    font-family: var(--font-primary);
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
}

.btn-acerto:hover {
    background: var(--color-primary-dark);
    transform: translateY(-1px);
}

/* Linha de total */
.total-row {
    background: var(--color-gray-100);
}

.total-row td {
    font-size: 1rem;
    padding: 1.25rem;
    border-bottom: none;
}

.total-row strong {
    color: var(--color-gray-900);
}

#pendenciasSomaTotal {
    font-family: var(--font-mono);
    font-size: 1.25rem;
    color: var(--color-danger);
}

/* Seção de Renegociação */
.renegociacao-section {
    background: white;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 2px solid var(--color-primary-light);
}

.renegociacao-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
}

.renegociacao-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.renegociacao-body {
    padding: 1.5rem;
}

.renegociacao-form {
    display: flex;
    align-items: flex-end;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-gray-700);
}

.input-with-prefix {
    display: flex;
    align-items: center;
    border: 2px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    overflow: hidden;
    transition: all 0.2s ease;
}

.input-with-prefix:focus-within {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.input-prefix {
    padding: 0.625rem 0.875rem;
    background: var(--color-gray-100);
    color: var(--color-gray-600);
    font-weight: 600;
    font-size: 0.9375rem;
}

.form-input {
    padding: 0.625rem 0.875rem;
    border: none;
    font-family: var(--font-mono);
    font-size: 1rem;
    width: 150px;
    outline: none;
}

.btn-renegociar {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, var(--color-success) 0%, #15803d 100%);
    color: white;
    font-family: var(--font-primary);
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-renegociar:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
}

.renegociacao-info {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--color-gray-50);
    border-radius: var(--radius-md);
    border-left: 4px solid var(--color-info);
}

.renegociacao-info i {
    color: var(--color-info);
    font-size: 1rem;
    margin-top: 2px;
}

.renegociacao-info p {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--color-gray-600);
    line-height: 1.5;
}

/* Footer do Modal de Pendências */
.pendencias-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    border-top: 1px solid var(--color-gray-200);
    background: white;
    gap: 1rem;
}

.footer-left,
.footer-right {
    display: flex;
    gap: 0.75rem;
}

/* Toast Container */
.toast-container-custom {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.toast-custom {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: white;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-xl);
    min-width: 320px;
    animation: slideInRight 0.3s ease;
    border-left: 4px solid var(--color-gray-400);
}

.toast-custom.success { border-left-color: var(--color-success); }
.toast-custom.error { border-left-color: var(--color-danger); }
.toast-custom.warning { border-left-color: var(--color-warning); }
.toast-custom.info { border-left-color: var(--color-primary); }

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.toast-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.toast-custom.success .toast-icon { background: var(--color-success); }
.toast-custom.error .toast-icon { background: var(--color-danger); }
.toast-custom.warning .toast-icon { background: var(--color-warning); }
.toast-custom.info .toast-icon { background: var(--color-primary); }

.toast-message {
    flex: 1;
    font-size: 0.875rem;
    color: var(--color-gray-700);
}

.toast-close {
    background: none;
    border: none;
    color: var(--color-gray-400);
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s ease;
}

.toast-close:hover {
    color: var(--color-gray-600);
}

/* Responsive */
@media (max-width: 768px) {
    .inadim-header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .inadim-quick-stats {
        width: 100%;
    }
    
    .quick-stat {
        flex: 1;
    }
    
    .inadim-filters {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-actions .btn-filter {
        flex: 1;
    }
    
    .modal-container {
        max-height: 95vh;
        border-radius: var(--radius-lg);
        margin: 0.5rem;
    }
    
    .modal-header-custom,
    .pendencias-header {
        padding: 1rem;
    }
    
    .profile-section,
    .pendencias-title-section {
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
        padding-right: 40px;
    }
    
    .profile-avatar,
    .pendencias-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;
        font-size: 1rem;
    }
    
    .profile-name {
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }
    
    .profile-meta {
        gap: 0.5rem;
    }
    
    .meta-item {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
    }
    
    .status-alert {
        padding: 0.5rem 0.75rem;
        gap: 0.5rem;
    }
    
    .modal-nav {
        overflow-x: auto;
    }
    
    .nav-tab span {
        display: none;
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
    
    .info-grid-2,
    .info-grid-3 {
        grid-template-columns: 1fr;
    }
    
    .modal-footer-custom,
    .pendencias-footer {
        flex-direction: column;
        gap: 1rem;
    }
    
    .footer-actions,
    .footer-left,
    .footer-right {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .pendencias-info-bar {
        flex-direction: column;
    }
    
    .info-bar-item {
        min-width: 100%;
    }
    
    .renegociacao-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-input {
        width: 100%;
    }
    
    .btn-renegociar {
        width: 100%;
        justify-content: center;
    }
    
    .pendencias-table {
        font-size: 0.8125rem;
    }
    
    .pendencias-table thead th,
    .pendencias-table tbody td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<!-- Script de Configuração -->
<script>
window.listaInadimplentesPermissions = {
    temPermissao: <?php echo json_encode($temPermissaoFinanceiro); ?>,
    isFinanceiro: <?php echo json_encode($isFinanceiro); ?>,
    isPresidencia: <?php echo json_encode($isPresidencia); ?>
};
console.log('✅ Lista Inadimplentes v2 com Modal de Pendências carregada');
</script>