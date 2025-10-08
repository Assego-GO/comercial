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
    
    error_log("=== DEBUG NEOCONSIG - ACESSO ===");
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
        <h4><i class="fas fa-ban me-2"></i>Acesso Negado ao NeoConsig</h4>
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

<!-- Container NeoConsig -->
<div class="neoconsig-container">
    <!-- Informações sobre o Sistema -->
    <div class="info-box-neo">
        <h6 class="info-title-neo">
            <i class="fas fa-info-circle me-2"></i>Sistema de Geração de Arquivos NeoConsig
        </h6>
        <ul class="info-list-neo">
            <li><strong>Inclusões (Tipo 1):</strong> Novos associados para desconto em folha</li>
            <li><strong>Cancelamentos (Tipo 0):</strong> Remover associados do desconto</li>
            <li><strong>Alterações (Tipo 2):</strong> Modificar valores de associados existentes</li>
            <li><strong>Formato:</strong> Arquivo TXT compatível com Governo do Estado de Goiás</li>
        </ul>
    </div>

    <!-- Formulário Principal -->
    <form id="formRecorrenciaNeo" onsubmit="NeoConsig.gerarArquivo(event)">
        
        <!-- Passo 1: Tipo de Processamento -->
        <div class="step-container-neo">
            <h6 class="step-title-neo">
                <i class="fas fa-cog"></i>
                Tipo de Processamento
            </h6>
            
            <div class="field-container-neo">
                <label class="form-label-neo">Selecione o tipo de operação:</label>
                <select id="tipoProcessamentoNeo" class="form-select form-control-neo" required onchange="NeoConsig.toggleCampos()">
                    <option value="">-- Selecione o tipo de processamento --</option>
                    <option value="1">1 - Inclusões (Novos Associados)</option>
                    <option value="0">0 - Cancelamentos</option>
                    <option value="2">2 - Alterações de Valores</option>
                </select>
            </div>
        </div>

        <!-- Passo 2: Matrículas -->
        <div class="step-container-neo">
            <h6 class="step-title-neo">
                <i class="fas fa-users"></i>
                Matrículas dos Associados
            </h6>
            
            <div class="field-container-neo">
                <label class="form-label-neo">Digite as matrículas dos associados:</label>
                <textarea id="matriculasNeo" class="form-control form-control-neo" rows="4" 
                          placeholder="Digite as matrículas separadas por vírgula. Ex: 445, 788, 1023, 1205, 1456" 
                          required onchange="NeoConsig.validarMatriculas()"></textarea>
                <small class="text-muted">
                    <i class="fas fa-lightbulb me-1"></i>
                    <strong>Dica:</strong> Separe múltiplas matrículas com vírgula. Exemplo: 445,788,1023
                </small>
            </div>
            
            <!-- Preview das Matrículas -->
            <div id="previewMatriculasNeo" class="matriculas-encontradas-neo" style="display: none;">
                <h6><i class="fas fa-search me-2"></i>Preview das Matrículas:</h6>
                <div id="listaMatriculasNeo"></div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-primary btn-sm" onclick="NeoConsig.buscarAssociados()">
                        <i class="fas fa-search me-2"></i>Buscar Associado(s)
                    </button>
                </div>
            </div>
            
            <!-- Preview dos Associados Encontrados -->
            <div id="previewAssociadosNeo" class="preview-associados-neo" style="display: none;">
                <h6><i class="fas fa-users text-primary me-2"></i>Associados Encontrados</h6>
                
                <!-- Loading -->
                <div id="loadingAssociadosNeo" class="loading-spinner-neo">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p class="mt-2">Buscando associados no banco de dados...</p>
                </div>
                
                <!-- Lista de associados -->
                <div id="listaAssociadosNeo"></div>
                
                <!-- Resumo -->
                <div id="resumoAssociadosNeo"></div>
            </div>
        </div>

        <!-- Passo 3: Rubrica (Opcional) -->
        <div class="step-container-neo">
            <h6 class="step-title-neo">
                <i class="fas fa-hashtag"></i>
                Rubrica (Opcional)
            </h6>
            
            <div class="field-container-neo">
                <label class="form-label-neo">Código da rubrica:</label>
                <input type="text" id="rubricaNeo" class="form-control form-control-neo" 
                       value="0900892" placeholder="Ex: 0900892">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Código padrão: 0900892 (Governo do Estado de Goiás)
                </small>
            </div>
        </div>

        <!-- Passo 4: Gerar Arquivo -->
        <div class="step-container-neo">
            <h6 class="step-title-neo">
                <i class="fas fa-download"></i>
                Gerar Arquivo
            </h6>
            
            <!-- Alert de status -->
            <div id="alertNeoConsig" class="alert" style="display: none;">
                <i class="fas fa-info-circle me-2"></i>
                <span id="alertNeoConsigText"></span>
            </div>
            
            <!-- Loading -->
            <div id="loadingNeoConsig" class="loading-spinner-neo" style="display: none;">
                <i class="fas fa-cog fa-spin"></i>
                <p>Gerando arquivo TXT...</p>
            </div>
            
            <div class="acoes-container-neo">
                <button type="submit" class="btn btn-success btn-neo-generate" id="btnGerarNeo">
                    <i class="fas fa-file-download me-2"></i>
                    Gerar e Baixar Arquivo TXT
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="NeoConsig.limpar()">
                    <i class="fas fa-eraser me-1"></i>
                    Limpar
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainerNeo"></div>

<!-- Estilos compactos para NeoConsig -->
<style>
/* Container principal */
.neoconsig-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin: 0;
}

/* Info box */
.info-box-neo {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border: 2px solid var(--info);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.info-title-neo {
    color: var(--info);
    margin-bottom: 0.75rem;
    font-weight: 700;
}

.info-list-neo {
    margin-bottom: 0;
    padding-left: 1.2rem;
}

.info-list-neo li {
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Step containers */
.step-container-neo {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.step-container-neo:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.step-title-neo {
    color: var(--primary);
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 2px solid var(--light);
    padding-bottom: 0.5rem;
}

.step-title-neo i {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 0.5rem;
    border-radius: 6px;
    font-size: 1rem;
}

/* Form controls */
.field-container-neo {
    margin-bottom: 1rem;
}

.form-label-neo {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    display: block;
    font-size: 0.95rem;
}

.form-control-neo {
    border-radius: 6px;
    border: 2px solid #e9ecef;
    padding: 0.75rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control-neo:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.125rem rgba(0, 86, 210, 0.25);
    transform: translateY(-1px);
}

/* Preview das matrículas */
.matriculas-encontradas-neo {
    background: linear-gradient(135deg, #e8f5e8, #d4edda);
    border: 2px solid var(--success);
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

/* Preview dos associados */
.preview-associados-neo {
    background: linear-gradient(135deg, #f8f9ff, #e8f2ff);
    border: 2px solid var(--primary-light);
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 1rem;
    position: relative;
    overflow: hidden;
}

.preview-associados-neo::before {
    content: '';
    position: absolute;
    top: -10px;
    right: -10px;
    width: 80px;
    height: 80px;
    background: rgba(74, 144, 226, 0.1);
    border-radius: 50%;
}

/* Associado cards */
.associado-card-neo {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.associado-card-neo:hover {
    transform: translateX(3px);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.associado-info-neo {
    flex-grow: 1;
}

.associado-valor-neo {
    font-weight: 700;
    color: var(--success);
    font-size: 1.1rem;
    background: rgba(40, 167, 69, 0.1);
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
}

/* Loading spinners */
.loading-spinner-neo {
    display: none;
    text-align: center;
    padding: 1.5rem;
    color: var(--primary);
}

.loading-spinner-neo i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

/* Botões */
.btn-neo-generate {
    background: linear-gradient(135deg, var(--success), #1e7e34);
    border: none;
    padding: 0.75rem 1.5rem;
    font-weight: 700;
    font-size: 1rem;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
    color: white;
}

.btn-neo-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    color: white;
}

.btn-neo-generate:disabled {
    opacity: 0.6;
    transform: none;
}

/* Container de ações */
.acoes-container-neo {
    text-align: center;
    margin-top: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Alerts */
.alert {
    border-radius: 6px;
    border: none;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}


/* ===== CSS ADICIONAL PARA BOTÕES BLOQUEADOS ===== */
/* Adicione este CSS ao arquivo gerar_recorrencia.php e neoconsig_content.php */

/* Estilo para botão bloqueado/desabilitado */
.btn-generate:disabled,
.btn-neo-generate:disabled {
    opacity: 0.6;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
    pointer-events: none;
}

/* Estilo para botão de erro/bloqueado */
.btn-generate.btn-danger,
.btn-neo-generate.btn-danger {
    background: linear-gradient(135deg, #dc3545, #a71e2a);
    border: none;
    color: white;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

.btn-generate.btn-danger:hover,
.btn-neo-generate.btn-danger:hover {
    background: linear-gradient(135deg, #dc3545, #a71e2a);
    transform: none !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

/* Estilo para botão secundário (estado inicial) */
.btn-generate.btn-secondary,
.btn-neo-generate.btn-secondary {
    background: linear-gradient(135deg, #6c757d, #495057);
    border: none;
    color: white;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
}

.btn-generate.btn-secondary:hover,
.btn-neo-generate.btn-secondary:hover {
    background: linear-gradient(135deg, #6c757d, #495057);
    transform: none !important;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
}

/* Animação de pulse para chamar atenção quando bloqueado */
.btn-blocked-pulse {
    animation: blockedPulse 2s infinite;
}

@keyframes blockedPulse {
    0% { 
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }
    50% { 
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.6);
    }
    100% { 
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }
}

/* Estilo para alertas informativos sobre botão bloqueado */
.alert-blocked-info {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border: 2px solid #ffc107;
    border-radius: 8px;
    color: #856404;
    padding: 1rem;
    margin: 1rem 0;
    font-weight: 500;
}

/* Estilo para indicador visual de status do botão */
.button-status-indicator {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 0.5rem;
}

.button-status-indicator.blocked {
    background: #dc3545;
    color: white;
}

.button-status-indicator.ready {
    background: #28a745;
    color: white;
}

.button-status-indicator.searching {
    background: #17a2b8;
    color: white;
}

/* Responsivo para dispositivos móveis */
@media (max-width: 768px) {
    .btn-generate,
    .btn-neo-generate {
        font-size: 0.9rem;
        padding: 0.75rem 1rem;
    }
    
    .button-status-indicator {
        display: block;
        margin: 0.5rem 0 0 0;
        text-align: center;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .neoconsig-container {
        padding: 0.5rem;
    }
    
    .step-container-neo {
        padding: 0.75rem;
    }
    
    .associado-card-neo {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .acoes-container-neo {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-neo-generate {
        width: 100%;
        max-width: 300px;
    }
}

/* Animation */
.animate-fade-in-neo {
    animation: fadeInNeo 0.4s ease-out;
}

@keyframes fadeInNeo {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Script inline para passar dados para o JavaScript -->
<script>
// Passar dados de permissão para o JavaScript quando a partial carregar
window.neoconsigPermissions = {
    temPermissao: <?php echo json_encode($temPermissaoFinanceiro); ?>,
    isFinanceiro: <?php echo json_encode($isFinanceiro); ?>,
    isPresidencia: <?php echo json_encode($isPresidencia); ?>
};

console.log('✅ NeoConsig carregado como partial');
</script>