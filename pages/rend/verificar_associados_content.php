<?php
/**
 * Conteúdo da aba Verificar Associados - Módulo Financeiro
 * pages/rend/verificar_associados_content.php
 */
?>

<div class="verificar-associados-container">
    <!-- Header da Seção -->
    <div class="section-header">
        <div class="section-header-content">
            <h2 class="section-title">
                <i class="fas fa-search-plus"></i>
                Verificar Associados
            </h2>
            <p class="section-description">
                Cole uma lista de pessoas para verificar quais são associados filiados da ASSEGO
            </p>
        </div>
        <div class="section-actions">
            <button type="button" class="btn btn-outline-secondary" id="btnLimparTudo">
                <i class="fas fa-eraser"></i>
                Limpar
            </button>
            <button type="button" class="btn btn-success" id="btnExportarResultado" disabled>
                <i class="fas fa-download"></i>
                Exportar
            </button>
        </div>
    </div>

    <!-- Card Principal -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light border-bottom-0 py-3">
            <h5 class="mb-0">
                <i class="fas fa-paste text-primary me-2"></i>
                Inserir Lista de Pessoas
            </h5>
        </div>
        <div class="card-body p-4">
            <!-- Área de Input -->
            <div class="input-section mb-4">
                <label for="listaInput" class="form-label fw-bold">
                    <i class="fas fa-clipboard-list text-secondary me-1"></i>
                    Cole sua lista aqui:
                </label>
                <textarea 
                    class="form-control" 
                    id="listaInput" 
                    rows="8" 
                    placeholder="Cole sua lista aqui. Exemplos aceitos:&#10;01 - RG:26.096 Charles Raniel Santos de Oliveira&#10;3°SGT 35.782 Jefferson Pereira coelho&#10;Cap 29.767 Luís Alves dos Santos&#10;CB Lemuel Santiago Diniz 36423"
                    style="font-family: 'Courier New', monospace; font-size: 0.9rem; min-height: 150px;">
                </textarea>
                <div class="form-text text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    O sistema irá extrair automaticamente RGs e CPFs da lista. Aceita diversos formatos.
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="d-flex gap-2 mb-4">
                <button type="button" class="btn btn-primary" id="btnProcessarLista">
                    <i class="fas fa-cogs me-2"></i>
                    Processar Lista
                </button>
                <button type="button" class="btn btn-outline-info" id="btnPreviewExtracao">
                    <i class="fas fa-eye me-2"></i>
                    Preview Extração
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnExemploLista">
                    <i class="fas fa-question-circle me-2"></i>
                    Ver Exemplo
                </button>
            </div>

            <!-- Estatísticas de Processamento -->
            <div id="estatisticasProcessamento" class="stats-container d-none">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-value text-primary" id="totalProcessados">0</div>
                            <div class="stat-label">Total Processados</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-value text-success" id="totalFiliados">0</div>
                            <div class="stat-label">Filiados</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-value text-warning" id="totalNaoFiliados">0</div>
                            <div class="stat-label">Não Filiados</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card text-center">
                            <div class="stat-value text-secondary" id="totalNaoEncontrados">0</div>
                            <div class="stat-label">Não Encontrados</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Área de Resultados -->
    <div id="resultadosContainer" class="d-none">
      

        <!-- Tabela de Resultados -->
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-light py-3">
                <h5 class="mb-0">
                    <i class="fas fa-table text-primary me-2"></i>
                    Resultados da Verificação
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tabelaResultados">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="25%">Nome</th>
                                <th width="12%">RG</th>
                                <th width="12%">CPF</th>
                                <th width="15%">Status</th>
                                <th width="12%">Corporação</th>
                                <th width="12%">Patente</th>
                                
                            </tr>
                        </thead>
                        <tbody id="tabelaResultadosBody">
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Loading na tabela -->
                <div id="tabelaLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Processando...</span>
                    </div>
                    <div class="mt-2 text-muted">Verificando associados...</div>
                </div>

                <!-- Empty state -->
                <div id="tabelaEmpty" class="text-center py-5 d-none">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Nenhum resultado encontrado</h5>
                    <p class="text-muted">Tente ajustar os filtros ou processar uma nova lista</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Preview de Extração -->
<div class="modal fade" id="modalPreviewExtracao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>
                    Preview da Extração
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Visualização dos dados que serão extraídos da sua lista antes do processamento
                </div>
                
                <div id="previewContent">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-id-card text-primary me-1"></i> RGs Encontrados</h6>
                            <div id="previewRGs" class="preview-list">
                                <!-- Preenchido via JS -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-id-badge text-success me-1"></i> CPFs Encontrados</h6>
                            <div id="previewCPFs" class="preview-list">
                                <!-- Preenchido via JS -->
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6><i class="fas fa-users text-info me-1"></i> Pessoas Identificadas</h6>
                    <div id="previewPessoas" class="preview-list">
                        <!-- Preenchido via JS -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="btnProcessarAposPreview">
                    <i class="fas fa-cogs me-2"></i>
                    Processar Lista
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Exemplo -->
<div class="modal fade" id="modalExemplo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle me-2"></i>
                    Exemplo de Lista
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary">
                    <i class="fas fa-lightbulb me-2"></i>
                    O sistema aceita diversos formatos de lista. Veja alguns exemplos:
                </div>
                
                <div class="example-content">
                    <h6>Formato com RG no início:</h6>
                    <pre class="bg-light p-3 rounded">01 - RG:26.096 Charles Raniel Santos de Oliveira 
02 - RG: 30.239 Henrique Moreira Mendes</pre>
                    
                    <h6 class="mt-3">Formato com patente e RG:</h6>
                    <pre class="bg-light p-3 rounded">03 - 3°SGT 35.782 Jefferson Pereira coelho 
04 - 1° SGT 33.140 Gumercindo Lemes dos Santos Filho
5 - Cap 29.767 Luís Alves dos Santos</pre>
                    
                    <h6 class="mt-3">Formato com RG no final:</h6>
                    <pre class="bg-light p-3 rounded">10. CB Lemuel Santiago Diniz 36423
11. CAP 29738 Eládio José do Prado Neto</pre>
                    
                    <h6 class="mt-3">Com CPF (11 dígitos):</h6>
                    <pre class="bg-light p-3 rounded">João Silva - CPF: 12345678901 - RG: 1234567
Maria Santos 98765432100 RG:7654321</pre>
                </div>
                
                <div class="alert alert-info mt-3">
                    <strong>Dicas:</strong>
                    <ul class="mb-0">
                        <li>O sistema identifica automaticamente RGs (4-6 dígitos)</li>
                        <li>CPFs são identificados como sequências de 11 dígitos</li>
                        <li>Formatos flexíveis - números, patentes e nomes em qualquer ordem</li>
                        <li>Pontos nos RGs são removidos automaticamente</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-success" id="btnUsarExemplo">
                    <i class="fas fa-clipboard me-2"></i>
                    Usar Exemplo
                </button>
            </div>
        </div>
    </div>
    <script>
// Inicializar módulo quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado, iniciando VerificarAssociados...');
    
    if (typeof window.VerificarAssociados !== 'undefined') {
        window.VerificarAssociados.init();
        console.log('VerificarAssociados inicializado com sucesso!');
    } else {
        console.error('Módulo VerificarAssociados não encontrado!');
        // Tentar novamente em 500ms
        setTimeout(function() {
            if (typeof window.VerificarAssociados !== 'undefined') {
                window.VerificarAssociados.init();
                console.log('VerificarAssociados inicializado após delay!');
            } else {
                console.error('VerificarAssociados ainda não carregado após delay');
            }
        }, 500);
    }
});
</script>

</div>

<style>
/* Estilos específicos para a aba Verificar Associados */
.verificar-associados-container {
    padding: 0 !important;
    margin: 0 !important;
}

.section-header {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 10px rgba(0, 86, 210, 0.08);
    border-left: 4px solid #28a745;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0 0 0.25rem 0;
}

.section-description {
    color: var(--secondary);
    margin: 0;
    font-size: 1rem;
}

.section-actions {
    display: flex;
    gap: 0.75rem;
}

/* Debug Panel */
#debugPanel {
    border: 2px dashed #17a2b8;
    background: linear-gradient(45deg, #e7f3ff 25%, transparent 25%),
                linear-gradient(-45deg, #e7f3ff 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #e7f3ff 75%),
                linear-gradient(-45deg, transparent 75%, #e7f3ff 75%);
    background-size: 20px 20px;
    background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
}

/* Teste de CSS para botões */
#btnProcessarLista:hover {
    background-color: #0056b3 !important;
    transform: translateY(-1px);
}

#btnPreviewExtracao:hover {
    background-color: #138496 !important;
    transform: translateY(-1px);
}

#btnExemploLista:hover {
    background-color: #6c757d !important;
    transform: translateY(-1px);
}

.stats-container .stat-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--secondary);
    font-weight: 500;
}

.preview-list {
    max-height: 200px;
    overflow-y: auto;
    background: #f8f9fa;
    border-radius: 4px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.preview-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
    border-bottom: 1px solid #e9ecef;
}

.preview-item:last-child {
    border-bottom: none;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-filiado {
    background-color: #d1edff;
    color: #0c5460;
}

.status-nao-filiado {
    background-color: #fff3cd;
    color: #664d03;
}

.status-nao-encontrado {
    background-color: #f8d7da;
    color: #721c24;
}

.example-content pre {
    font-size: 0.875rem;
    line-height: 1.4;
}

/* Loading e estados vazios */
.table-responsive {
    min-height: 200px;
}

#tabelaResultados tbody tr:hover {
    background-color: rgba(0, 86, 210, 0.05);
}

/* Responsividade */
@media (max-width: 768px) {
    .section-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .section-actions {
        width: 100%;
        justify-content: space-between;
    }
}
</style>