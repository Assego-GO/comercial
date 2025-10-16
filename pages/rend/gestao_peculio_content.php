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
            <!-- NOVO: Botão para listar todos -->
            <button type="button" class="btn btn-info btn-sm btn-listar-todos mt-2" onclick="Peculio.listarTodos()">
                <i class="fas fa-list-ul me-1"></i>
                Ver Todos os Pecúlios
                <i class="fas fa-arrow-right ms-1"></i>
            </button>
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

<!-- Estilos ultra compactos -->
<style>
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

/* NOVO: Estilo para botão Listar Todos */
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

.btn-listar-todos:active {
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

/* Responsivo ultra compacto */
@media (max-width: 768px) {
    .peculio-card-ultra-compact {
        padding: 0.5rem;
        margin: 0.1rem;
    }
    
    .busca-section-ultra-compact {
        padding: 0.75rem;
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

    .btn-listar-todos {
        width: 100%;
        margin-top: 0.5rem;
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

@media (max-width: 768px) {
    .rg-opcao-detalhes {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.2rem;
    }
    
    .selecao-rg-container {
        padding: 1rem;
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

console.log('✅ Gestão de Pecúlio carregada com layout ultra compacto e lista completa');
</script>v