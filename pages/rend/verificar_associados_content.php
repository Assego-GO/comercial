<?php
/**
 * Conteúdo da aba Verificar Associados
 * rend/verificar_associados_content.php
 */
?>

<div class="verificar-associados-container" style="padding: 2rem; margin: 2.5rem 0 0 0;">
    <!-- Header -->
    <div class="mb-4">
        <h2 class="content-title">
            <i class="fas fa-search text-success"></i>
            Verificador de Associados
        </h2>
        <p class="content-description">
            Importe um arquivo CSV com nomes e RGs para verificar se as pessoas são filiadas à ASSEGO
        </p>
    </div>

    <!-- Upload Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light border-0">
            <h5 class="card-title mb-0">
                <i class="fas fa-upload text-primary me-2"></i>
                Importar Lista CSV
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="upload-zone border border-dashed border-primary rounded p-4 text-center" 
                         id="uploadZone"
                         ondrop="dropHandler(event);" 
                         ondragover="dragOverHandler(event);"
                         ondragenter="dragEnterHandler(event);"
                         ondragleave="dragLeaveHandler(event);">
                        <div class="upload-content">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <h5>Arraste o arquivo CSV aqui ou clique para selecionar</h5>
                            <p class="text-muted mb-3">
                                O arquivo deve conter as colunas: <strong>nome</strong> e <strong>rg</strong>
                            </p>
                            <input type="file" id="csvFile" accept=".csv" class="d-none" />
                            <button type="button" class="btn btn-outline-primary" id="selectFileBtn">
                                <i class="fas fa-folder-open me-1"></i>
                                Selecionar Arquivo
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-panel">
                        <h6><i class="fas fa-info-circle text-info me-1"></i> Formato do CSV</h6>
                        <p class="small text-muted mb-2">O arquivo deve ter as seguintes colunas:</p>
                        <ul class="small text-muted">
                            <li><strong>nome:</strong> Nome completo da pessoa</li>
                            <li><strong>rg:</strong> Número do RG (com ou sem pontos)</li>
                        </ul>
                        
                        <div class="mt-3">
                            <h6><i class="fas fa-download text-success me-1"></i> Modelo</h6>
                            <button class="btn btn-sm btn-outline-success" id="downloadTemplate">
                                <i class="fas fa-download me-1"></i>
                                Baixar Modelo CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Section (Hidden by default) -->
    <div class="card border-0 shadow-sm mb-4 d-none" id="progressSection">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm text-primary me-3" role="status">
                    <span class="visually-hidden">Processando...</span>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">Processando arquivo CSV...</h6>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
                    </div>
                </div>
                <span class="text-muted small" id="progressText">0%</span>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <div class="card border-0 shadow-sm d-none" id="resultsSection">
        <div class="card-header bg-light border-0">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-check text-success me-2"></i>
                        Resultados da Verificação
                    </h5>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-sm" id="exportResults" disabled>
                        <i class="fas fa-download me-1"></i>
                        Exportar Resultados
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Summary Stats -->
            <div class="row g-0 border-bottom">
                <div class="col-md-3 border-end">
                    <div class="p-3 text-center">
                        <div class="h4 mb-1 text-primary" id="totalProcessed">0</div>
                        <div class="small text-muted">Total Processados</div>
                    </div>
                </div>
                <div class="col-md-3 border-end">
                    <div class="p-3 text-center">
                        <div class="h4 mb-1 text-success" id="totalFiliados">0</div>
                        <div class="small text-muted">Filiados</div>
                    </div>
                </div>
                <div class="col-md-3 border-end">
                    <div class="p-3 text-center">
                        <div class="h4 mb-1 text-warning" id="totalNaoFiliados">0</div>
                        <div class="small text-muted">Não Filiados</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 text-center">
                        <div class="h4 mb-1 text-danger" id="totalNaoEncontrados">0</div>
                        <div class="small text-muted">Não Encontrados</div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="border-bottom">
                <nav class="nav nav-tabs border-0" id="resultTabs">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#allTab">
                        Todos <span class="badge bg-secondary ms-1" id="badgeAll">0</span>
                    </button>
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#filiadosTab">
                        Filiados <span class="badge bg-success ms-1" id="badgeFiliados">0</span>
                    </button>
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#naoFiliadosTab">
                        Não Filiados <span class="badge bg-warning ms-1" id="badgeNaoFiliados">0</span>
                    </button>
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#naoEncontradosTab">
                        Não Encontrados <span class="badge bg-danger ms-1" id="badgeNaoEncontrados">0</span>
                    </button>
                </nav>
            </div>

            <!-- Results Table Container -->
            <div class="tab-content">
                <div class="tab-pane fade show active" id="allTab">
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover mb-0" id="resultsTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Nome</th>
                                    <th>RG Pesquisado</th>
                                    <th>CPF</th>
                                    <th>RG Cadastrado</th>
                                    <th>Status</th>
                                    <th>Patente</th>
                                    <th>Corporação</th>
                                </tr>
                            </thead>
                            <tbody id="resultsTableBody">
                                <!-- Results will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Other tabs will have filtered results -->
                <div class="tab-pane fade" id="filiadosTab">
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Nome</th>
                                    <th>RG Pesquisado</th>
                                    <th>CPF</th>
                                    <th>RG Cadastrado</th>
                                    <th>Patente</th>
                                    <th>Corporação</th>
                                </tr>
                            </thead>
                            <tbody id="filiadosTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="naoFiliadosTab">
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Nome</th>
                                    <th>RG Pesquisado</th>
                                    <th>CPF</th>
                                    <th>RG Cadastrado</th>
                                    <th>Status</th>
                                    <th>Patente</th>
                                    <th>Corporação</th>
                                </tr>
                            </thead>
                            <tbody id="naoFiliadosTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="naoEncontradosTab">
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Nome Pesquisado</th>
                                    <th>RG Pesquisado</th>
                                    <th>Observação</th>
                                </tr>
                            </thead>
                            <tbody id="naoEncontradosTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Specific styles for this component */
.verificar-associados-container {
    background: transparent !important;
    margin: 0 !important;
    padding: 0 2rem !important;
}

.upload-zone {
    transition: all 0.3s ease;
    cursor: pointer;
    background: #f8f9fa;
}

.upload-zone:hover,
.upload-zone.dragover {
    background: #e3f2fd;
    border-color: #2196f3 !important;
    transform: translateY(-2px);
}

.info-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
    height: 100%;
}

.badge {
    font-size: 0.7em;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    padding: 1rem 1.5rem;
}

.nav-tabs .nav-link.active {
    background: white;
    color: #0d6efd;
    border-bottom: 2px solid #0d6efd;
}

.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.table th {
    font-weight: 600;
    font-size: 0.875rem;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    font-size: 0.875rem;
    vertical-align: middle;
}

.sticky-top {
    position: sticky;
    top: 0;
    z-index: 1020;
}

/* Status specific colors */
.status-filiado {
    background-color: #d1edff;
    color: #0969da;
}

.status-nao-filiado {
    background-color: #fff2cd;
    color: #bf8700;
}

.status-nao-encontrado {
    background-color: #ffebe9;
    color: #cf222e;
}
</style>