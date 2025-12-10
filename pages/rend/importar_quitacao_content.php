<?php
/**
 * Importar Quitação - Conteúdo da Página COMPLETO E MELHORADO
 * Sistema ASSEGO - Serviços Financeiros
 * Importação de CSV de repasse mensal (NeoConsig)
 * 
 * Features:
 * - Preview do CSV antes de importar
 * - Validações visuais em tempo real
 * - Estatísticas detalhadas do arquivo
 * - Interface moderna e responsiva
 */
?>

<style>
    /* ===== ESTILOS COMPLETOS DO IMPORTAR QUITAÇÃO ===== */
    .quitacao-container {
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
    }

    /* Section Cards */
    .section-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
        border: 1px solid #e9ecef;
        transition: all 0.3s ease;
    }

    .section-card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #f0f0f0;
    }

    .section-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }

    .section-subtitle {
        font-size: 0.9rem;
        color: #6b7280;
        margin: 0.25rem 0 0 0;
    }

    /* Feature Cards */
    .feature-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .feature-card {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.25rem;
        transition: all 0.3s ease;
    }

    .feature-card:hover {
        border-color: #10b981;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
    }

    .feature-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 0.75rem;
    }

    .feature-title {
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .feature-description {
        font-size: 0.85rem;
        color: #6b7280;
        line-height: 1.5;
        margin: 0;
    }

    /* Drop Zone */
    .drop-zone {
        border: 3px dashed #d1d5db;
        border-radius: 12px;
        padding: 3rem 2rem;
        text-align: center;
        background: #f9fafb;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .drop-zone:hover {
        border-color: #10b981;
        background: #f0fdf4;
    }

    .drop-zone.drag-over {
        border-color: #10b981;
        background: #dcfce7;
        transform: scale(1.02);
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
    }

    .drop-zone-icon {
        font-size: 4rem;
        color: #10b981;
        margin-bottom: 1rem;
        opacity: 0.8;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .drop-zone-text {
        font-size: 1.1rem;
        color: #374151;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .drop-zone-hint {
        font-size: 0.9rem;
        color: #6b7280;
    }

    /* File Info Box */
    .file-info-box {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border: 2px solid #10b981;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1.5rem;
        display: none;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .file-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .file-meta-item {
        background: white;
        padding: 0.75rem;
        border-radius: 8px;
        border: 1px solid #d1fae5;
    }

    .file-meta-label {
        font-size: 0.75rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .file-meta-value {
        font-size: 1rem;
        font-weight: 600;
        color: #1f2937;
    }

    /* CSV Preview */
    .csv-preview-container {
        margin-top: 1.5rem;
        display: none;
        animation: slideDown 0.3s ease;
    }

    .csv-preview-header {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 2px solid #10b981;
        border-bottom: none;
        border-radius: 12px 12px 0 0;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .csv-preview-title {
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }

    .csv-preview-badge {
        background: #10b981;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .csv-preview-body {
        background: white;
        border: 2px solid #10b981;
        border-top: none;
        border-radius: 0 0 12px 12px;
        padding: 0;
        max-height: 400px;
        overflow: auto;
    }

    .csv-preview-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .csv-preview-table thead {
        position: sticky;
        top: 0;
        background: #f9fafb;
        z-index: 10;
    }

    .csv-preview-table th {
        padding: 0.75rem;
        text-align: left;
        font-weight: 700;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
        white-space: nowrap;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .csv-preview-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #f3f4f6;
        color: #1f2937;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }

    .csv-preview-table tbody tr:hover {
        background: #f9fafb;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }

    .status-quitado {
        background: #d1fae5;
        color: #065f46;
    }

    .status-pendente {
        background: #fef3c7;
        color: #92400e;
    }

    /* Validation Messages */
    .validation-container {
        margin-top: 1rem;
        display: none;
    }

    .validation-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border-radius: 8px;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .validation-item.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #10b981;
    }

    .validation-item.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #ef4444;
    }

    .validation-item.warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #f59e0b;
    }

    /* Progress Bar */
    .progress-container {
        margin-top: 1.5rem;
        display: none;
    }

    .progress {
        height: 35px;
        border-radius: 8px;
        background: #e5e7eb;
        overflow: visible;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .progress-bar {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        font-weight: 600;
        font-size: 0.9rem;
        line-height: 35px;
        transition: width 0.3s ease;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .progress-text {
        margin-top: 0.5rem;
        text-align: center;
        color: #6b7280;
        font-size: 0.9rem;
    }

    /* Results */
    .import-results {
        margin-top: 2rem;
    }

    .results-header {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border-radius: 12px;
        padding: 1.5rem;
        border: 2px solid #10b981;
        margin-bottom: 1.5rem;
        animation: slideDown 0.3s ease;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border: 1px solid #e5e7eb;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    .stat-card.bg-primary { border-left: 4px solid #3b82f6; }
    .stat-card.bg-success { border-left: 4px solid #10b981; }
    .stat-card.bg-warning { border-left: 4px solid #f59e0b; }
    .stat-card.bg-danger { border-left: 4px solid #ef4444; }
    .stat-card.bg-secondary { border-left: 4px solid #6b7280; }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        color: white;
        flex-shrink: 0;
    }

    .stat-card.bg-primary .stat-icon { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
    .stat-card.bg-success .stat-icon { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .stat-card.bg-warning .stat-icon { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .stat-card.bg-danger .stat-icon { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
    .stat-card.bg-secondary .stat-icon { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); }

    .stat-info {
        flex: 1;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 0.25rem;
        color: #1f2937;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #6b7280;
        font-weight: 600;
    }

    /* Historic Table */
    .historic-table-container {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }

    .table-responsive {
        border-radius: 12px;
    }

    .table thead {
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    }

    .table thead th {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
        padding: 1rem;
    }

    .table tbody tr {
        transition: background-color 0.2s ease;
    }

    .table tbody tr:hover {
        background: #f9fafb;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    /* Buttons */
    .btn-quitacao {
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .btn-quitacao:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-quitacao:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-quitacao i {
        margin-right: 0.5rem;
    }

    /* Badges */
    .badge {
        padding: 0.5rem 0.75rem;
        font-weight: 600;
        font-size: 0.8rem;
        border-radius: 6px;
    }

    /* Alert Info */
    .alert-info-custom {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 2px solid #3b82f6;
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .alert-info-custom i {
        color: #1e40af;
        font-size: 1.25rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .section-card {
            padding: 1.5rem;
        }

        .drop-zone {
            padding: 2rem 1rem;
        }

        .stat-card {
            flex-direction: column;
            text-align: center;
        }

        .stat-icon {
            margin-bottom: 0.5rem;
        }

        .file-meta {
            grid-template-columns: 1fr;
        }

        .csv-preview-table {
            font-size: 0.75rem;
        }

        .csv-preview-table th,
        .csv-preview-table td {
            padding: 0.5rem;
        }
    }
</style>

<div class="quitacao-container">
    <!-- Informações Importantes -->
    <div class="alert-info-custom">
        <div class="d-flex align-items-start gap-3">
            <i class="fas fa-info-circle mt-1"></i>
            <div class="flex-grow-1">
                <h6 class="mb-2 fw-bold">Como Funciona a Importação de Quitação</h6>
                <ul class="mb-0 small">
                    <li>Faça o upload do arquivo CSV de repasse mensal fornecido pelo NeoConsig</li>
                    <li>O sistema irá identificar automaticamente os associados pelo CPF</li>
                    <li>Os pagamentos com status "Quitado" serão registrados no banco de dados</li>
                    <li>Os pagamentos "Pendentes" serão ignorados (não geram registro)</li>
                    <li>Você verá um preview do arquivo antes de confirmar a importação</li>
                    <li>Associados não encontrados serão listados no relatório final</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Feature Cards -->
    <div class="feature-cards">
        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-shield-check"></i>
            </div>
            <div class="feature-title">Seguro e Validado</div>
            <p class="feature-description">
                Validação automática de CPFs, prevenção de duplicatas e transações seguras
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-eye"></i>
            </div>
            <div class="feature-title">Preview do CSV</div>
            <p class="feature-description">
                Visualize os dados do arquivo antes de importar para garantir a qualidade
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="feature-title">Estatísticas Detalhadas</div>
            <p class="feature-description">
                Relatórios completos com quitados, pendentes, erros e taxa de sucesso
            </p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="feature-title">Histórico Completo</div>
            <p class="feature-description">
                Acompanhe todas as importações realizadas com detalhes e timestamps
            </p>
        </div>
    </div>

    <!-- Upload de Arquivo -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <div class="flex-grow-1">
                <h3 class="section-title">Importar Arquivo de Quitação</h3>
                <p class="section-subtitle">Selecione o CSV de repasse mensal do NeoConsig</p>
            </div>
        </div>

        <!-- Drop Zone -->
        <div class="drop-zone" id="quitacao-drop-zone">
            <div class="drop-zone-icon">
                <i class="fas fa-file-csv"></i>
            </div>
            <div class="drop-zone-text">
                Arraste o arquivo CSV aqui ou clique para selecionar
            </div>
            <div class="drop-zone-hint">
                Formato: CSV (separado por ponto e vírgula) • Tamanho máximo: 10MB
            </div>
            <input 
                type="file" 
                id="quitacao-file-input" 
                accept=".csv" 
                style="display: none;"
            >
        </div>

        <!-- File Info -->
        <div class="file-info-box" id="quitacao-file-info">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div>
                    <i class="fas fa-file-csv text-success" style="font-size: 2.5rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold fs-5" id="quitacao-file-name">Arquivo.csv</div>
                    <div class="text-muted small">
                        <span id="quitacao-file-size">0 MB</span> • 
                        <span id="quitacao-file-date">00/00/0000</span>
                    </div>
                </div>
            </div>

            <!-- File Meta -->
            <div class="file-meta" id="quitacao-file-meta"></div>
        </div>

        <!-- Validation Messages -->
        <div class="validation-container" id="quitacao-validation"></div>

        <!-- CSV Preview -->
        <div class="csv-preview-container" id="quitacao-preview-container">
            <div class="csv-preview-header">
                <h6 class="csv-preview-title">
                    <i class="fas fa-table me-2"></i>
                    Preview do Arquivo
                    <span class="csv-preview-badge" id="quitacao-preview-count">0 linhas</span>
                </h6>
                <button class="btn btn-sm btn-outline-success" id="toggle-preview-btn">
                    <i class="fas fa-eye me-1"></i>
                    Mostrar/Ocultar
                </button>
            </div>
            <div class="csv-preview-body" id="quitacao-preview-body">
                <table class="csv-preview-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="quitacao-preview-tbody">
                        <!-- Preenchido via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-container" id="quitacao-progress-container">
            <div class="progress">
                <div 
                    class="progress-bar" 
                    id="quitacao-progress-bar"
                    role="progressbar" 
                    aria-valuenow="0" 
                    aria-valuemin="0" 
                    aria-valuemax="100"
                >
                    0%
                </div>
            </div>
            <div class="progress-text" id="quitacao-progress-text">Processando...</div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2 mt-3">
            <button 
                type="button" 
                class="btn btn-success btn-quitacao flex-grow-1" 
                id="upload-quitacao-btn"
                disabled
            >
                <i class="fas fa-upload"></i>
                Importar Quitação
            </button>
            <button 
                type="button" 
                class="btn btn-outline-primary btn-quitacao" 
                id="preview-quitacao-btn"
                disabled
                style="display: none;"
            >
                <i class="fas fa-eye"></i>
                Ver Preview
            </button>
            <button 
                type="button" 
                class="btn btn-secondary btn-quitacao" 
                id="clear-quitacao-file"
                style="display: none;"
            >
                <i class="fas fa-times"></i>
                Limpar
            </button>
        </div>
    </div>

    <!-- Resultados da Importação -->
    <div id="quitacao-results-container" style="display: none;"></div>

    <!-- Histórico de Importações -->
    <div class="section-card">
        <div class="section-header">
            <div class="section-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                <i class="fas fa-history"></i>
            </div>
            <div class="flex-grow-1">
                <h3 class="section-title">Histórico de Importações</h3>
                <p class="section-subtitle">Últimas importações de quitação realizadas</p>
            </div>
            <button 
                type="button" 
                class="btn btn-sm btn-primary" 
                id="refresh-quitacao-historic"
            >
                <i class="fas fa-sync-alt"></i>
                Atualizar
            </button>
        </div>

        <div class="historic-table-container">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Data/Hora</th>
                            <th>Funcionário</th>
                            <th class="text-center" style="width: 120px;">Total Registros</th>
                            <th class="text-center" style="width: 100px;">Quitados</th>
                            <th class="text-center" style="width: 100px;">Pendentes</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="quitacao-historic-table">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                                <p class="text-muted mt-2 mb-0">Carregando histórico...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Script de Inicialização -->
<script>
/**
 * Inicialização do módulo Importar Quitação
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Inicializando módulo Importar Quitação...');
    
    // Verificar se o módulo foi carregado
    if (typeof ImportarQuitacao === 'undefined') {
        console.error('❌ Módulo ImportarQuitacao não foi carregado!');
        console.error('Verifique se o arquivo importar_quitacao.js está incluído');
        return;
    }
    
    // Inicializar com permissões
    // A verificação real de permissões é feita no backend (PHP)
    try {
        ImportarQuitacao.init({
            permissoes: {
                visualizar: true,
                importar: true,
                exportar: true
            }
        });
        console.log('✅ Módulo Importar Quitação inicializado com sucesso!');
    } catch (error) {
        console.error('❌ Erro ao inicializar módulo:', error);
    }
});
</script>