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
    
    error_log("=== DEBUG IMPORTAR_ASAAS - ACESSO ===");
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
        <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Importação ASAAS</h4>
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

<!-- Container de Importação ASAAS -->
<div class="importar-asaas-container">
    <!-- Informações sobre a Nova Lógica -->
    <div class="info-box-asaas">
        <h6 class="info-title-asaas">
            <i class="fas fa-info-circle me-2"></i>Como Funciona a Importação
        </h6>
        <ul class="info-list-asaas">
            <li><strong>Escopo:</strong> Processa <strong>todos os associados</strong> encontrados no arquivo CSV</li>
            <li><strong>CSV:</strong> Deve conter apenas quem <strong>realizou pagamentos</strong> (não cobranças pendentes)</li>
            <li><strong>Pagou:</strong> Quem está no CSV será marcado como <strong>ADIMPLENTE</strong></li>
            <li><strong>Não pagou:</strong> Associados do sistema que não estão no CSV serão apenas <strong>reportados</strong></li>
        </ul>
    </div>

    <!-- Área de Upload -->
    <div class="upload-section-asaas">
        <div class="upload-header-asaas">
            <h6 class="upload-title-asaas">
                <i class="fas fa-cloud-upload-alt"></i>
                Upload do Arquivo CSV ASAAS
            </h6>
        </div>

        <div class="upload-area-asaas" id="uploadAreaAsaas">
            <div class="upload-icon-asaas">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h6>Arraste o arquivo CSV do ASAAS aqui</h6>
            <p class="text-muted mb-3">ou clique no botão abaixo para selecionar</p>
            
            <button class="upload-btn-asaas" onclick="document.getElementById('csvFileAsaas').click()">
                <i class="fas fa-file-csv me-2"></i>
                Selecionar Arquivo CSV de Pagamentos
            </button>
            
            <input type="file" id="csvFileAsaas" accept=".csv" style="display: none;" onchange="ImportarAsaas.handleFileSelect(event)">
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Formato: CSV (separado por ponto e vírgula) | Tamanho máximo: 10MB
                </small>
            </div>
        </div>

        <!-- Informações do Arquivo -->
        <div class="file-info-asaas" id="fileInfoAsaas" style="display: none;">
            <!-- Informações do arquivo aparecerão aqui -->
        </div>

        <!-- Barra de Progresso -->
        <div class="progress-container-asaas" id="progressContainerAsaas" style="display: none;">
            <h6><i class="fas fa-cog fa-spin me-2"></i>Processando arquivo...</h6>
            <div class="progress mt-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 0%" id="progressBarAsaas">
                    0%
                </div>
            </div>
            <p class="text-muted mt-2" id="progressTextAsaas">Iniciando processamento...</p>
        </div>
    </div>

    <!-- Container de Resultados -->
    <div class="results-container-asaas" id="resultsContainerAsaas" style="display: none;">
        
        <!-- Estatísticas -->
        <div class="stats-grid-asaas">
            <div class="stat-card-asaas total">
                <div class="stat-value-asaas" id="totalProcessadosAsaas">0</div>
                <div class="stat-label-asaas">Total Processados</div>
            </div>
            <div class="stat-card-asaas pagantes">
                <div class="stat-value-asaas" id="totalPagantesAsaas">0</div>
                <div class="stat-label-asaas">Pagamentos (Adimplentes)</div>
            </div>
            <div class="stat-card-asaas nao-encontrados">
                <div class="stat-value-asaas" id="totalNaoEncontradosAsaas">0</div>
                <div class="stat-label-asaas">Não Encontrados</div>
            </div>
            <div class="stat-card-asaas ignorados">
                <div class="stat-value-asaas" id="totalIgnoradosAsaas">0</div>
                <div class="stat-label-asaas">Ignorados</div>
            </div>
        </div>

        <!-- Tabs com Resultados -->
        <div class="tabs-container-asaas">
            <ul class="nav nav-tabs nav-tabs-asaas" id="resultTabsAsaas" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link nav-link-asaas active" id="pagantes-tab-asaas" 
                            data-bs-toggle="tab" data-bs-target="#pagantesAsaas" type="button" role="tab">
                        <i class="fas fa-check-circle me-2"></i>Pagamentos (<span id="countPagantesAsaas">0</span>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link nav-link-asaas" id="nao-encontrados-tab-asaas" 
                            data-bs-toggle="tab" data-bs-target="#naoEncontradosAsaas" type="button" role="tab">
                        <i class="fas fa-exclamation-triangle me-2"></i>Não Encontrados (<span id="countNaoEncontradosAsaas">0</span>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link nav-link-asaas" id="ignorados-tab-asaas" 
                            data-bs-toggle="tab" data-bs-target="#ignoradosAsaas" type="button" role="tab">
                        <i class="fas fa-times-circle me-2"></i>Ignorados (<span id="countIgnoradosAsaas">0</span>)
                    </button>
                </li>
            </ul>
            
            <div class="tab-content tab-content-asaas" id="resultTabContentAsaas">
                <!-- Tab Pagantes -->
                <div class="tab-pane fade show active" id="pagantesAsaas" role="tabpanel">
                    <div class="table-container-asaas">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação</th>
                                    <th>Status</th>
                                    <th>Valor Pago</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody id="pagantesTableAsaas">
                                <!-- Resultados aparecerão aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tab Não Encontrados -->
                <div class="tab-pane fade" id="naoEncontradosAsaas" role="tabpanel">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Associados do sistema que NÃO foram encontrados no arquivo de pagamentos.</strong><br>
                        Estes não tiveram seu status alterado - apenas reportados.
                    </div>
                    <div class="table-container-asaas">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação</th>
                                    <th>Status Atual</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody id="naoEncontradosTableAsaas">
                                <!-- Resultados aparecerão aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Tab Ignorados -->
                <div class="tab-pane fade" id="ignoradosAsaas" role="tabpanel">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>CPFs encontrados no arquivo que NÃO existem no sistema de associados.</strong><br>
                        Foram ignorados automaticamente pois não são associados cadastrados.
                    </div>
                    <div class="table-container-asaas">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody id="ignoradosTableAsaas">
                                <!-- Resultados aparecerão aqui -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="acoes-container-asaas">
            <button class="btn btn-primary btn-sm me-2" onclick="ImportarAsaas.voltarImportacao()">
                <i class="fas fa-upload me-1"></i>
                Nova Importação
            </button>
            <button class="btn btn-secondary btn-sm" onclick="ImportarAsaas.limpar()">
                <i class="fas fa-eraser me-1"></i>
                Limpar
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainerAsaas"></div>

<!-- Estilos compactos para ASAAS -->
<style>
/* Container principal */
.importar-asaas-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin: 0;
}

/* Info box */
.info-box-asaas {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border: 2px solid var(--info);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.info-title-asaas {
    color: var(--info);
    margin-bottom: 0.75rem;
    font-weight: 700;
}

.info-list-asaas {
    margin-bottom: 0;
    padding-left: 1.2rem;
}

.info-list-asaas li {
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Upload section */
.upload-section-asaas {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #dee2e6;
}

.upload-header-asaas {
    text-align: center;
    margin-bottom: 1rem;
}

.upload-title-asaas {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.upload-title-asaas i {
    margin-right: 0.5rem;
    color: var(--primary);
    font-size: 1.2rem;
}

/* Upload area */
.upload-area-asaas {
    border: 3px dashed var(--primary-light);
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    background: linear-gradient(135deg, #f8f9ff, #e8f2ff);
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.upload-area-asaas:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, #e8f2ff, #d1e7ff);
    transform: translateY(-2px);
}

.upload-area-asaas.dragover {
    border-color: var(--success);
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
}

.upload-icon-asaas {
    font-size: 3rem;
    color: var(--primary-light);
    margin-bottom: 1rem;
}

.upload-btn-asaas {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 86, 210, 0.3);
}

.upload-btn-asaas:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 86, 210, 0.4);
}

/* File info */
.file-info-asaas {
    background: white;
    border: 2px solid var(--success);
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

/* Progress container */
.progress-container-asaas {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin: 1rem 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 2px solid var(--info);
}

.progress-container-asaas h6 {
    color: var(--primary);
    font-weight: 700;
    margin-bottom: 1rem;
}

/* Results container */
.results-container-asaas {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin-top: 1rem;
}

/* Stats grid */
.stats-grid-asaas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card-asaas {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card-asaas::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-card-asaas.total::before { background: linear-gradient(135deg, var(--info), #0ea5e9); }
.stat-card-asaas.pagantes::before { background: linear-gradient(135deg, var(--success), #16a34a); }
.stat-card-asaas.nao-encontrados::before { background: linear-gradient(135deg, var(--warning), #ea580c); }
.stat-card-asaas.ignorados::before { background: linear-gradient(135deg, var(--secondary), #64748b); }

.stat-card-asaas:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.stat-value-asaas {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 0.4rem;
}

.stat-label-asaas {
    color: var(--secondary);
    font-weight: 600;
    font-size: 0.9rem;
}

/* Tabs container */
.tabs-container-asaas {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin-bottom: 1rem;
}

.nav-tabs-asaas {
    background: var(--light);
    border-bottom: none;
    padding: 0.5rem;
}

.nav-link-asaas {
    border: none;
    color: var(--secondary);
    font-weight: 600;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.nav-link-asaas:hover {
    background: rgba(0, 86, 210, 0.1);
    color: var(--primary);
}

.nav-link-asaas.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(0, 86, 210, 0.3);
}

.tab-content-asaas {
    padding: 1rem;
}

/* Table container */
.table-container-asaas {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.table thead th {
    background: var(--light);
    color: var(--primary);
    font-weight: 700;
    border: none;
    padding: 0.75rem 0.5rem;
    font-size: 0.9rem;
}

.table tbody tr:hover {
    background: rgba(0, 86, 210, 0.05);
    transform: translateX(1px);
}

.table tbody td {
    padding: 0.75rem 0.5rem;
    font-size: 0.9rem;
}

/* Status badges */
.status-badge-asaas {
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.status-badge-asaas.pagou {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
}

.status-badge-asaas.nao-encontrado {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    color: #856404;
}

.status-badge-asaas.ignorado {
    background: linear-gradient(135deg, #f8d7da, #fab1a0);
    color: #721c24;
}

/* Action buttons */
.acoes-container-asaas {
    text-align: center;
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

/* Responsive */
@media (max-width: 768px) {
    .importar-asaas-container {
        padding: 0.5rem;
    }
    
    .stats-grid-asaas {
        grid-template-columns: 1fr;
    }
    
    .upload-area-asaas {
        padding: 1rem;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
}

/* Animation */
.animate-fade-in {
    animation: fadeInAsaas 0.6s ease-out;
}

@keyframes fadeInAsaas {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Script inline para passar dados para o JavaScript -->
<script>
// Passar dados de permissão para o JavaScript quando a partial carregar
window.importarAsaasPermissions = {
    temPermissao: <?php echo json_encode($temPermissaoFinanceiro); ?>,
    isFinanceiro: <?php echo json_encode($isFinanceiro); ?>,
    isPresidencia: <?php echo json_encode($isPresidencia); ?>
};

console.log('✅ Importar ASAAS carregado como partial');
</script>