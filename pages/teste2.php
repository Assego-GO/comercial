<?php
/**
 * Página da Presidência - Assinatura de Documentos
 * pages/presidencia.php
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Documentos.php';
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
$page_title = 'Presidência - ASSEGO';

// Verificar se o usuário tem permissão para acessar a presidência
$temPermissaoPresidencia = false;
$motivoNegacao = '';

// Debug completo ANTES das verificações
error_log("=== DEBUG DETALHADO PERMISSÕES PRESIDÊNCIA ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Array completo do usuário: " . print_r($usuarioLogado, true));
error_log("Departamento ID (valor): " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("Departamento ID (tipo): " . gettype($usuarioLogado['departamento_id'] ?? null));
error_log("É Diretor (método): " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Verificações de permissão:
// 1. É diretor OU
// 2. Está no departamento da presidência (APENAS ID: 1)
if ($auth->isDiretor()) {
    $temPermissaoPresidencia = true;
    error_log("✅ Permissão concedida: É DIRETOR");
} elseif (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    
    // Testar diferentes tipos de comparação
    $isString1 = ($deptId === '1');
    $isInt1 = ($deptId === 1);
    $isEqual1 = ($deptId == 1);
    
    error_log("Testes de comparação:");
    error_log("  deptId === '1': " . ($isString1 ? 'true' : 'false'));
    error_log("  deptId === 1: " . ($isInt1 ? 'true' : 'false'));
    error_log("  deptId == 1: " . ($isEqual1 ? 'true' : 'false'));
    
    if ($deptId == 1) { // Comparação flexível para pegar string ou int
        $temPermissaoPresidencia = true;
        error_log("✅ Permissão concedida: Departamento ID = 1");
    } else {
        error_log("❌ Departamento incorreto. Valor: '$deptId' (tipo: " . gettype($deptId) . ")");
    }
} else {
    error_log("❌ departamento_id não existe no array do usuário");
}

if (!$temPermissaoPresidencia) {
    $motivoNegacao = 'Para acessar a presidência, você precisa ser diretor ou estar no departamento da Presidência (ID: 1). Seu departamento atual: ' . ($usuarioLogado['departamento_id'] ?? 'não definido') . ' (tipo: ' . gettype($usuarioLogado['departamento_id'] ?? null) . ')';
    error_log("❌ ACESSO NEGADO: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO");
}

// Busca estatísticas de documentos (apenas se tem permissão)
if ($temPermissaoPresidencia) {
    try {
        $documentos = new Documentos();
        $statsPresidencia = $documentos->getEstatisticasPresidencia();
        
        $aguardandoAssinatura = $statsPresidencia['aguardando_assinatura'] ?? 0;
        $assinadosHoje = $statsPresidencia['assinados_hoje'] ?? 0;
        $assinadosMes = $statsPresidencia['assinados_mes'] ?? 0;
        $tempoMedio = $statsPresidencia['tempo_medio_assinatura'] ?? 0;

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas da presidência: " . $e->getMessage());
        $aguardandoAssinatura = $assinadosHoje = $assinadosMes = $tempoMedio = 0;
    }
} else {
    $aguardandoAssinatura = $assinadosHoje = $assinadosMes = $tempoMedio = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'avatar' => $usuarioLogado['avatar'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'presidencia',
    'notificationCount' => $aguardandoAssinatura,
    'showSearch' => false
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/presidencia.css">
</head>

<body>
    <!-- Toast Container para Notificações -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;" id="toastContainer"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoPresidencia): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Área da Presidência</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Como resolver:</h6>
                    <ol class="mb-0">
                        <li>Verifique se você está no departamento correto (ID: 1)</li>
                        <li>Confirme se você é diretor no sistema</li>
                        <li>Use o botão "Debug Detalhado" para mais informações</li>
                        <li>Entre em contato com o administrador se necessário</li>
                    </ol>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Suas informações atuais:</h6>
                        <ul class="mb-0">
                            <li><strong>Nome:</strong> <?php echo htmlspecialchars($usuarioLogado['nome']); ?></li>
                            <li><strong>Cargo:</strong> <?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'N/A'); ?></li>
                            <li><strong>Departamento ID:</strong> 
                                <span class="badge bg-<?php echo ($usuarioLogado['departamento_id'] == 1) ? 'success' : 'danger'; ?>">
                                    <?php echo $usuarioLogado['departamento_id'] ?? 'N/A'; ?>
                                </span>
                            </li>
                            <li><strong>É Diretor:</strong> 
                                <span class="badge bg-<?php echo $auth->isDiretor() ? 'success' : 'danger'; ?>">
                                    <?php echo $auth->isDiretor() ? 'Sim' : 'Não'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Requisitos para acesso:</h6>
                        <ul class="mb-3">
                            <li>Ser diretor <strong>OU</strong></li>
                            <li>Estar no departamento da Presidência (ID: 1)</li>
                        </ul>
                        
                        <div class="btn-group d-block">
                            <button class="btn btn-primary btn-sm" onclick="window.location.reload()">
                                <i class="fas fa-sync me-1"></i>
                                Recarregar Página
                            </button>
                            <button class="btn btn-info btn-sm ms-2" onclick="mostrarDebugCompleto()">
                                <i class="fas fa-bug me-1"></i>
                                Debug Detalhado
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-stamp"></i>
                    </div>
                    Área da Presidência
                </h1>
                <p class="page-subtitle">Gerencie e assine documentos de filiação dos associados</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $aguardandoAssinatura; ?></div>
                            <div class="stat-label">Aguardando Assinatura</div>
                            <?php if ($aguardandoAssinatura > 0): ?>
                            <div class="stat-change negative">
                                <i class="fas fa-exclamation-triangle"></i>
                                Requer atenção
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $assinadosHoje; ?></div>
                            <div class="stat-label">Assinados Hoje</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Produtividade
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $assinadosMes; ?></div>
                            <div class="stat-label">Assinados no Mês</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $tempoMedio; ?>h</div>
                            <div class="stat-label">Tempo Médio de Assinatura</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions" data-aos="fade-up" data-aos-delay="100">
                <h3 class="quick-actions-title">Ações Rápidas</h3>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="abrirRelatorios()">
                        <i class="fas fa-chart-line quick-action-icon"></i>
                        Relatórios
                    </button>
                    <button class="quick-action-btn" onclick="verHistorico()">
                        <i class="fas fa-history quick-action-icon"></i>
                        Histórico
                    </button>
                    <button class="quick-action-btn" onclick="configurarAssinatura()">
                        <i class="fas fa-cog quick-action-icon"></i>
                        Configurações
                    </button>
                    <button class="quick-action-btn" onclick="assinarTodos()">
                        <i class="fas fa-layer-group quick-action-icon"></i>
                        Assinar em Lote
                    </button>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="documents-section" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h2 class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        Documentos Pendentes de Assinatura
                    </h2>
                    <div class="section-actions">
                        <button class="btn-action secondary" onclick="atualizarLista()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Buscar por nome ou CPF...">
                    <select class="filter-select" id="filterUrgencia">
                        <option value="">Todas as prioridades</option>
                        <option value="urgente">Urgente</option>
                        <option value="normal">Normal</option>
                    </select>
                    <select class="filter-select" id="filterOrigem">
                        <option value="">Todas as origens</option>
                        <option value="FISICO">Físico</option>
                        <option value="VIRTUAL">Virtual</option>
                    </select>
                </div>

                <!-- Documents List -->
                <div class="documents-list" id="documentsList">
                    <!-- Documentos serão carregados aqui -->
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Assinatura -->
    <div class="modal fade" id="assinaturaModal" tabindex="-1" aria-labelledby="assinaturaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assinaturaModalLabel">
                        <i class="fas fa-signature" style="color: var(--primary);"></i>
                        Assinar Documento de Filiação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Preview do Documento -->
                    <div class="document-preview">
                        <div class="preview-header">
                            <div class="preview-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="preview-title">
                                <h5 id="previewTitulo">Ficha de Associação</h5>
                                <p id="previewSubtitulo">-</p>
                            </div>
                            <button class="btn-action secondary" onclick="visualizarDocumento()">
                                <i class="fas fa-eye"></i>
                                Visualizar
                            </button>
                        </div>
                        <div class="preview-details">
                            <div class="detail-item">
                                <span class="detail-label">Associado</span>
                                <span class="detail-value" id="previewAssociado">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">CPF</span>
                                <span class="detail-value" id="previewCPF">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Data de Upload</span>
                                <span class="detail-value" id="previewData">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Origem</span>
                                <span class="detail-value" id="previewOrigem">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Opções de Assinatura -->
                    <div class="signature-section">
                        <h5 class="signature-title">
                            <i class="fas fa-pen-fancy"></i>
                            Método de Assinatura
                        </h5>
                        <div class="signature-options">
                            <label class="signature-option selected">
                                <input type="radio" name="metodoAssinatura" value="digital" checked>
                                <strong>Assinatura Digital</strong>
                                <p class="mb-0 text-muted">Assinar digitalmente sem upload de arquivo</p>
                            </label>
                            <label class="signature-option">
                                <input type="radio" name="metodoAssinatura" value="upload">
                                <strong>Upload de Documento Assinado</strong>
                                <p class="mb-0 text-muted">Fazer upload do PDF já assinado</p>
                            </label>
                        </div>
                    </div>

                    <!-- Upload Area (mostrada apenas quando selecionado) -->
                    <div id="uploadSection" class="d-none">
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <p class="upload-text mb-0">
                                Arraste o arquivo aqui ou clique para selecionar<br>
                                <small class="text-muted">Apenas arquivos PDF (máx. 10MB)</small>
                            </p>
                            <input type="file" id="fileInput" class="d-none" accept=".pdf">
                        </div>
                        <div id="fileInfo" class="mt-3"></div>
                    </div>

                    <!-- Observações -->
                    <div class="mb-3">
                        <label class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="observacoes" rows="3" 
                            placeholder="Adicione observações sobre a assinatura..."></textarea>
                    </div>

                    <!-- Confirmação -->
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>
                            <strong>Importante:</strong> Ao assinar, você confirma que revisou o documento e 
                            autoriza o prosseguimento do processo de filiação.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" id="documentoId">
                    <button type="button" class="btn-action secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-action success" onclick="confirmarAssinatura()">
                        <i class="fas fa-check"></i>
                        Confirmar Assinatura
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Assinatura em Lote -->
    <div class="modal fade" id="assinaturaLoteModal" tabindex="-1" aria-labelledby="assinaturaLoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assinaturaLoteModalLabel">
                        <i class="fas fa-layer-group" style="color: var(--primary);"></i>
                        Assinatura em Lote
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção:</strong> Você está prestes a assinar múltiplos documentos de uma vez.
                        Certifique-se de ter revisado todos os documentos selecionados.
                    </div>

                    <div class="mb-4">
                        <h6>Documentos selecionados:</h6>
                        <div id="documentosLoteLista" class="mt-2">
                            <!-- Lista de documentos será carregada aqui -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observações para todos os documentos</label>
                        <textarea class="form-control" id="observacoesLote" rows="3" 
                            placeholder="Estas observações serão aplicadas a todos os documentos..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-action success" onclick="confirmarAssinaturaLote()">
                        <i class="fas fa-check-double"></i>
                        Assinar Todos
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

    <script>
        // ===== CLASSES E SISTEMAS AUXILIARES =====

        // Sistema de Notificações Toast
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('toastContainer');
            }
            
            show(message, type = 'success', duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${this.getIcon(type)} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                
                this.container.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast, { delay: duration });
                bsToast.show();
                
                toast.addEventListener('hidden.bs.toast', () => {
                    toast.remove();
                });
            }
            
            getIcon(type) {
                const icons = {
                    success: 'check-circle',
                    error: 'exclamation-triangle',
                    warning: 'exclamation-circle',
                    info: 'info-circle'
                };
                return icons[type] || 'info-circle';
            }
        }

        // Cache Simples
        class SimpleCache {
            constructor(ttl = 300000) { // 5 minutos padrão
                this.cache = new Map();
                this.ttl = ttl;
            }
            
            set(key, value) {
                const expiry = Date.now() + this.ttl;
                this.cache.set(key, { value, expiry });
            }
            
            get(key) {
                const item = this.cache.get(key);
                if (!item) return null;
                
                if (Date.now() > item.expiry) {
                    this.cache.delete(key);
                    return null;
                }
                
                return item.value;
            }
            
            clear() {
                this.cache.clear();
            }
        }

        // Sistema de Atualização Automática
        class AutoUpdater {
            constructor(interval = 30000) {
                this.interval = interval;
                this.timer = null;
                this.isActive = true;
            }
            
            start() {
                if (this.timer) this.stop();
                
                this.timer = setInterval(() => {
                    if (this.isActive && document.hasFocus()) {
                        carregarDocumentosPendentes();
                    }
                }, this.interval);
            }
            
            stop() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            }
            
            pause() {
                this.isActive = false;
            }
            
            resume() {
                this.isActive = true;
            }
        }

        // ===== INICIALIZAÇÃO =====

        // Instanciar sistemas
        const notifications = new NotificationSystem();
        const cache = new SimpleCache();
        const autoUpdater = new AutoUpdater();

        // Variáveis globais
        let documentosPendentes = [];
        let documentoSelecionado = null;
        let arquivoAssinado = null;
        const temPermissao = <?php echo json_encode($temPermissaoPresidencia); ?>;

        // Debounce para filtros
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        const debouncedFilter = debounce(filtrarDocumentos, 300);

        // Inicialização - CORRIGIDA
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa AOS
            AOS.init({
                duration: 800,
                once: true
            });

            // Debug inicial DETALHADO
            console.log('=== DEBUG PRESIDÊNCIA FRONTEND DETALHADO ===');
            const usuario = <?php echo json_encode($usuarioLogado); ?>;
            const isDiretor = <?php echo json_encode($auth->isDiretor()); ?>;
            
            console.log('👤 Usuário completo:', usuario);
            console.log('🏢 Departamento ID:', usuario.departamento_id, '(tipo:', typeof usuario.departamento_id, ')');
            console.log('👔 É diretor:', isDiretor);
            console.log('🔐 Tem permissão:', temPermissao);
            
            // Teste das comparações
            console.log('🧪 Testes de comparação:');
            console.log('  departamento_id == 1:', usuario.departamento_id == 1);
            console.log('  departamento_id === 1:', usuario.departamento_id === 1);
            console.log('  departamento_id === "1":', usuario.departamento_id === "1");
            
            // Resultado final da lógica
            const resultadoLogica = isDiretor || usuario.departamento_id == 1;
            console.log('📋 Lógica de acesso (isDiretor || dept==1):', resultadoLogica);
            console.log('📋 Permissão PHP vs JS:', temPermissao, '===', resultadoLogica, '?', temPermissao === resultadoLogica);
            
            console.log('🔗 URL da API:', '../api/documentos/documentos_presidencia_listar.php');
            
            // Só continuar se tiver permissão
            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                console.log('💡 Para debug detalhado, clique no botão "Debug Detalhado" na tela');
                return;
            }

            console.log('✅ Usuário autorizado - carregando funcionalidades...');

            // Carregar documentos automaticamente
            carregarDocumentosPendentes();
            configurarFiltros();
            configurarUpload();
            configurarMetodoAssinatura();
            configurarEventos();
            
            // Iniciar auto-update
            autoUpdater.start();
        });

        // ===== FUNÇÕES PRINCIPAIS - CORRIGIDAS =====

        // Carregar documentos pendentes - CORRIGIDA
        async function carregarDocumentosPendentes() {
            // Verificar permissão primeiro
            if (!temPermissao) {
                console.log('❌ Sem permissão para carregar documentos');
                return;
            }
            
            const container = document.getElementById('documentsList');
            
            // Verificar cache primeiro
            const cached = cache.get('documentos_pendentes');
            if (cached) {
                documentosPendentes = cached;
                renderizarDocumentos(cached);
                atualizarContadores();
                return;
            }
            
            // Mostra loading
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-muted mt-3">Carregando documentos pendentes...</p>
                </div>
            `;

            try {
                console.log('🔄 Carregando documentos...');
                
                const response = await fetch('../api/documentos/documentos_presidencia_listar.php?status=AGUARDANDO_ASSINATURA', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                console.log('📡 Response status:', response.status);
                
                if (response.status === 403) {
                    // Erro de permissão específico
                    const errorText = await response.text();
                    console.error('403 Error details:', errorText);
                    
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-ban me-2"></i>Acesso Negado</h5>
                            <p>Você não tem permissão para acessar os documentos da presidência.</p>
                            <details class="mt-2">
                                <summary>Detalhes do erro</summary>
                                <pre class="mt-2 small bg-light p-2">${errorText}</pre>
                            </details>
                        </div>
                    `;
                    return;
                }
                
                if (response.status === 401) {
                    // Não autenticado - redirecionar para login
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-sign-out-alt me-2"></i>Sessão Expirada</h5>
                            <p>Redirecionando para o login...</p>
                        </div>
                    `;
                    setTimeout(() => {
                        window.location.href = '../pages/index.php';
                    }, 2000);
                    return;
                }
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('HTTP Error Details:', errorText);
                    throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
                }
                
                const data = await response.json();
                console.log('✅ API Response:', data);
                
                if (data.status === 'success') {
                    documentosPendentes = data.data || [];
                    cache.set('documentos_pendentes', documentosPendentes); // Armazenar em cache
                    renderizarDocumentos(documentosPendentes);
                    atualizarContadores();
                    
                    notifications.show(`${documentosPendentes.length} documento(s) carregado(s)`, 'success');
                } else {
                    throw new Error(data.message || 'Erro ao carregar documentos');
                }
            } catch (error) {
                console.error('❌ Erro completo:', error);
                mostrarErro('Erro ao carregar documentos: ' + error.message);
            }
        }

        // Renderizar documentos
        function renderizarDocumentos(documentos) {
            const container = document.getElementById('documentsList');
            
            if (!container) {
                console.error('Container de documentos não encontrado');
                return;
            }
            
            container.innerHTML = '';

            if (documentos.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle empty-state-icon"></i>
                        <h5 class="empty-state-title">Tudo em dia!</h5>
                        <p class="empty-state-description">
                            Não há documentos pendentes de assinatura no momento.
                        </p>
                    </div>
                `;
                return;
            }

            documentos.forEach(doc => {
                const urgente = doc.dias_em_processo > 3;
                const itemDiv = document.createElement('div');
                itemDiv.className = 'document-item';
                itemDiv.dataset.docId = doc.id;
                
                itemDiv.innerHTML = `
                    <div class="document-content">
                        <div class="document-icon-wrapper ${urgente ? 'urgent' : ''}">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="document-info">
                            <h4 class="document-title">
                                Ficha de Associação
                                ${urgente ? '<span class="document-status urgent"><i class="fas fa-fire"></i> Urgente</span>' : '<span class="document-status waiting"><i class="fas fa-clock"></i> Aguardando</span>'}
                            </h4>
                            <div class="document-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <span>${doc.associado_nome}</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-id-card"></i>
                                    <span>CPF: ${formatarCPF(doc.associado_cpf)}</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-${doc.tipo_origem === 'VIRTUAL' ? 'laptop' : 'paper-plane'}"></i>
                                    <span>${doc.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Físico'}</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>${formatarData(doc.data_upload)}</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span>${doc.dias_em_processo} dias aguardando</span>
                                </div>
                            </div>
                        </div>
                        <div class="document-actions">
                            <button class="btn-action secondary" onclick="visualizarDocumento(${doc.id})">
                                <i class="fas fa-eye"></i>
                                Visualizar
                            </button>
                            <button class="btn-action success" onclick="abrirModalAssinatura(${doc.id})">
                                <i class="fas fa-signature"></i>
                                Assinar
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(itemDiv);
            });
        }

        // Configurar filtros com debounce
        function configurarFiltros() {
            const searchInput = document.getElementById('searchInput');
            const filterUrgencia = document.getElementById('filterUrgencia');
            const filterOrigem = document.getElementById('filterOrigem');

            if (searchInput) searchInput.addEventListener('input', debouncedFilter);
            if (filterUrgencia) filterUrgencia.addEventListener('change', filtrarDocumentos);
            if (filterOrigem) filterOrigem.addEventListener('change', filtrarDocumentos);
        }

        // Filtrar documentos
        function filtrarDocumentos() {
            const searchInput = document.getElementById('searchInput');
            const filterUrgencia = document.getElementById('filterUrgencia');
            const filterOrigem = document.getElementById('filterOrigem');
            
            if (!searchInput || !filterUrgencia || !filterOrigem) return;
            
            const termo = searchInput.value.toLowerCase();
            const urgencia = filterUrgencia.value;
            const origem = filterOrigem.value;

            let documentosFiltrados = documentosPendentes;

            // Filtro por termo de busca
            if (termo) {
                documentosFiltrados = documentosFiltrados.filter(doc => 
                    doc.associado_nome.toLowerCase().includes(termo) ||
                    doc.associado_cpf.includes(termo.replace(/\D/g, ''))
                );
            }

            // Filtro por urgência
            if (urgencia) {
                documentosFiltrados = documentosFiltrados.filter(doc => {
                    const isUrgente = doc.dias_em_processo > 3;
                    return urgencia === 'urgente' ? isUrgente : !isUrgente;
                });
            }

            // Filtro por origem
            if (origem) {
                documentosFiltrados = documentosFiltrados.filter(doc => 
                    doc.tipo_origem === origem
                );
            }

            renderizarDocumentos(documentosFiltrados);
        }

        // ===== FUNÇÕES DE ASSINATURA =====

        // Abrir modal de assinatura
        function abrirModalAssinatura(documentoId) {
            documentoSelecionado = documentosPendentes.find(doc => doc.id === documentoId);
            
            if (!documentoSelecionado) {
                notifications.show('Documento não encontrado', 'error');
                return;
            }

            // Preencher informações do documento
            document.getElementById('documentoId').value = documentoId;
            document.getElementById('previewAssociado').textContent = documentoSelecionado.associado_nome;
            document.getElementById('previewCPF').textContent = formatarCPF(documentoSelecionado.associado_cpf);
            document.getElementById('previewData').textContent = formatarData(documentoSelecionado.data_upload);
            document.getElementById('previewOrigem').textContent = documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Físico';
            document.getElementById('previewSubtitulo').textContent = documentoSelecionado.tipo_origem === 'VIRTUAL' ? 'Gerado pelo sistema' : 'Digitalizado';

            // Resetar formulário
            document.querySelector('input[name="metodoAssinatura"][value="digital"]').checked = true;
            document.getElementById('uploadSection').classList.add('d-none');
            document.getElementById('observacoes').value = '';
            document.getElementById('fileInfo').innerHTML = '';
            arquivoAssinado = null;

            new bootstrap.Modal(document.getElementById('assinaturaModal')).show();
        }

        // Validação robusta de arquivo
        function validarArquivoAssinatura(file) {
            const errors = [];
            
            if (!file) {
                errors.push('Nenhum arquivo selecionado');
                return errors;
            }
            
            // Validar tipo
            if (file.type !== 'application/pdf') {
                errors.push('Apenas arquivos PDF são permitidos');
            }
            
            // Validar tamanho (10MB)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                errors.push(`Arquivo muito grande. Máximo: ${formatBytes(maxSize)}`);
            }
            
            // Validar nome do arquivo
            if (!/^[a-zA-Z0-9._-]+\.pdf$/i.test(file.name)) {
                errors.push('Nome do arquivo contém caracteres inválidos');
            }
            
            return errors;
        }

        // Confirmar assinatura com melhorias
        async function confirmarAssinatura() {
            const documentoId = document.getElementById('documentoId').value;
            const observacoes = document.getElementById('observacoes').value.trim();
            const metodo = document.querySelector('input[name="metodoAssinatura"]:checked').value;
            
            // Validações
            if (!documentoId) {
                notifications.show('ID do documento não encontrado', 'error');
                return;
            }
            
            if (metodo === 'upload' && !arquivoAssinado) {
                notifications.show('Por favor, selecione o arquivo assinado', 'warning');
                return;
            }
            
            // Validar arquivo se upload
            if (metodo === 'upload') {
                const validationErrors = validarArquivoAssinatura(arquivoAssinado);
                if (validationErrors.length > 0) {
                    notifications.show(validationErrors.join('<br>'), 'error');
                    return;
                }
            }
            
            // Confirmação para documentos urgentes
            if (documentoSelecionado && documentoSelecionado.dias_em_processo > 7) {
                const confirmar = await showConfirmDialog(
                    'Documento com Atraso',
                    `Este documento está aguardando há ${documentoSelecionado.dias_em_processo} dias. Deseja prosseguir com a assinatura?`
                );
                if (!confirmar) return;
            }
            
            const btnAssinar = event.target;
            const originalContent = btnAssinar.innerHTML;
            
            try {
                // Loading state
                btnAssinar.disabled = true;
                btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';
                
                const formData = new FormData();
                formData.append('documento_id', documentoId);
                formData.append('observacao', observacoes);
                formData.append('metodo', metodo);
                
                if (arquivoAssinado) {
                    formData.append('arquivo_assinado', arquivoAssinado);
                }
                
                const response = await fetch('../api/documentos/documentos_assinar.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('assinaturaModal')).hide();
                    notifications.show('Documento assinado com sucesso!', 'success');
                    
                    // Limpar cache e recarregar
                    cache.clear();
                    await carregarDocumentosPendentes();
                    
                    // Log para auditoria
                    console.log(`Documento ${documentoId} assinado pelo usuário ${<?php echo json_encode($usuarioLogado['nome']); ?>}`);
                    
                } else {
                    throw new Error(result.message || 'Erro desconhecido');
                }
                
            } catch (error) {
                console.error('Erro ao assinar documento:', error);
                notifications.show('Erro ao assinar documento: ' + error.message, 'error');
            } finally {
                btnAssinar.disabled = false;
                btnAssinar.innerHTML = originalContent;
            }
        }

        // Função auxiliar para confirmações
        function showConfirmDialog(title, message) {
            return new Promise((resolve) => {
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${title}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>${message}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="confirmBtn">Confirmar</button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                const bsModal = new bootstrap.Modal(modal);
                
                modal.querySelector('#confirmBtn').addEventListener('click', () => {
                    modal.dataset.resolved = 'true';
                    resolve(true);
                    bsModal.hide();
                });
                
                modal.addEventListener('hidden.bs.modal', () => {
                    if (!modal.dataset.resolved) resolve(false);
                    modal.remove();
                });
                
                bsModal.show();
            });
        }

        // Assinar todos com melhorias
        async function assinarTodos() {
            const documentosParaAssinar = documentosPendentes.filter(doc => {
                return true; // Pode adicionar filtros aqui
            });

            if (documentosParaAssinar.length === 0) {
                notifications.show('Não há documentos para assinar', 'warning');
                return;
            }

            if (documentosParaAssinar.length > 10) {
                const confirmar = await showConfirmDialog(
                    'Assinatura em Lote',
                    `Você está prestes a assinar ${documentosParaAssinar.length} documentos. Deseja continuar?`
                );
                if (!confirmar) return;
            }

            // Mostrar modal de confirmação
            const listaHtml = documentosParaAssinar.map(doc => `
                <div class="mb-2">
                    <i class="fas fa-file-pdf text-danger me-2"></i>
                    ${doc.associado_nome} - CPF: ${formatarCPF(doc.associado_cpf)}
                </div>
            `).join('');

            document.getElementById('documentosLoteLista').innerHTML = listaHtml;
            new bootstrap.Modal(document.getElementById('assinaturaLoteModal')).show();
        }

        // Confirmar assinatura em lote
        async function confirmarAssinaturaLote() {
            const observacoes = document.getElementById('observacoesLote').value;
            const documentosIds = documentosPendentes.map(doc => doc.id);

            const btnAssinar = event.target;
            const originalContent = btnAssinar.innerHTML;
            
            try {
                // Loading state
                btnAssinar.disabled = true;
                btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';

                const response = await fetch('../api/documentos/documentos_assinar_lote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        documentos_ids: documentosIds,
                        observacao: observacoes
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('assinaturaLoteModal')).hide();
                    notifications.show(`${result.assinados} documentos assinados com sucesso!`, 'success');
                    
                    // Limpar cache e recarregar
                    cache.clear();
                    await carregarDocumentosPendentes();
                } else {
                    throw new Error(result.message || 'Erro desconhecido');
                }
                
            } catch (error) {
                console.error('Erro ao assinar documentos:', error);
                notifications.show('Erro ao assinar documentos: ' + error.message, 'error');
            } finally {
                btnAssinar.disabled = false;
                btnAssinar.innerHTML = originalContent;
            }
        }

        // ===== CONFIGURAÇÕES E EVENTOS =====

        // Configurar método de assinatura
        function configurarMetodoAssinatura() {
            const radios = document.querySelectorAll('input[name="metodoAssinatura"]');
            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    const metodo = this.value;
                    const uploadSection = document.getElementById('uploadSection');
                    
                    if (metodo === 'upload') {
                        uploadSection.classList.remove('d-none');
                    } else {
                        uploadSection.classList.add('d-none');
                        arquivoAssinado = null;
                        document.getElementById('fileInfo').innerHTML = '';
                    }
                });
            });
        }

        // Configurar upload
        function configurarUpload() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');

            if (!uploadArea || !fileInput) return;

            // Clique para selecionar
            uploadArea.addEventListener('click', () => fileInput.click());

            // Arrastar e soltar
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragging');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragging');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragging');
                handleFile(e.dataTransfer.files[0]);
            });

            // Seleção de arquivo
            fileInput.addEventListener('change', (e) => {
                handleFile(e.target.files[0]);
            });
        }

        // Processar arquivo
        function handleFile(file) {
            if (!file) return;

            const validationErrors = validarArquivoAssinatura(file);
            if (validationErrors.length > 0) {
                notifications.show(validationErrors.join('<br>'), 'error');
                return;
            }

            arquivoAssinado = file;

            document.getElementById('fileInfo').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-file-pdf me-2"></i>
                    <strong>${file.name}</strong> (${formatBytes(file.size)})
                    <button type="button" class="btn-close float-end" onclick="removerArquivo()"></button>
                </div>
            `;
        }

        // Configurar eventos globais
        function configurarEventos() {
            // Pausar auto-update quando modal estiver aberto
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', () => autoUpdater.pause());
                modal.addEventListener('hidden.bs.modal', () => autoUpdater.resume());
            });
            
            // Pausar quando página não estiver visível
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    autoUpdater.pause();
                } else {
                    autoUpdater.resume();
                }
            });

            // Atalhos de teclado
            document.addEventListener('keydown', function(e) {
                // ESC para fechar modais
                if (e.key === 'Escape') {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        bootstrap.Modal.getInstance(modal).hide();
                    });
                }
                
                // Ctrl+R para atualizar lista
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    cache.clear();
                    carregarDocumentosPendentes();
                }
            });
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Remover arquivo
        function removerArquivo() {
            arquivoAssinado = null;
            document.getElementById('fileInfo').innerHTML = '';
            document.getElementById('fileInput').value = '';
        }

        // Visualizar documento
        function visualizarDocumento(documentoId) {
            if (!documentoId && documentoSelecionado) {
                documentoId = documentoSelecionado.id;
            }
            
            window.open(`../api/documentos/documentos_download.php?id=${documentoId}`, '_blank');
        }

        // Atualizar lista
        function atualizarLista() {
            cache.clear();
            carregarDocumentosPendentes();
        }

        // Placeholder functions para ações rápidas
        function abrirRelatorios() {
            notifications.show('Funcionalidade de relatórios em desenvolvimento', 'info');
        }

        function verHistorico() {
            notifications.show('Funcionalidade de histórico em desenvolvimento', 'info');
        }

        function configurarAssinatura() {
            notifications.show('Funcionalidade de configurações em desenvolvimento', 'info');
        }

        // Função de debug completo para diagnosticar problemas de acesso
        function mostrarDebugCompleto() {
            const usuario = <?php echo json_encode($usuarioLogado); ?>;
            const isDiretor = <?php echo json_encode($auth->isDiretor()); ?>;
            const temPermissao = <?php echo json_encode($temPermissaoPresidencia); ?>;
            
            let debugHtml = `
                <div class="debug-completo">
                    <h6><i class="fas fa-bug"></i> Debug Completo de Permissões</h6>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Dados do Usuário:</h6>
                            <pre class="bg-light p-2 small">${JSON.stringify(usuario, null, 2)}</pre>
                        </div>
                        <div class="col-md-6">
                            <h6>Verificações:</h6>
                            <ul class="small">
                                <li><strong>É Diretor:</strong> ${isDiretor ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Departamento ID:</strong> ${usuario.departamento_id} (tipo: ${typeof usuario.departamento_id})</li>
                                <li><strong>Departamento == 1:</strong> ${usuario.departamento_id == 1 ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Departamento === 1:</strong> ${usuario.departamento_id === 1 ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Departamento === '1':</strong> ${usuario.departamento_id === '1' ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Tem Permissão Final:</strong> ${temPermissao ? 'SIM ✅' : 'NÃO ❌'}</li>
                            </ul>
                            
                            <div class="mt-3">
                                <strong>Regra de Acesso:</strong><br>
                                <code>isDiretor || departamento_id == 1</code><br><br>
                                
                                <strong>Resultado:</strong><br>
                                <code>${isDiretor} || ${usuario.departamento_id == 1} = ${isDiretor || usuario.departamento_id == 1}</code>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <small class="text-muted">
                        <strong>Dica:</strong> Se você deveria ter acesso mas não consegue, verifique:
                        <br>1. Se você é diretor no sistema
                        <br>2. Se seu departamento_id está correto no banco de dados
                        <br>3. Se não há cache ou sessão antiga
                    </small>
                </div>
            `;
            
            // Criar modal customizado
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Debug de Permissões</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${debugHtml}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                                <i class="fas fa-sync me-1"></i>
                                Recarregar Página
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }
        async function executarDebug() {
            console.log('🔍 EXECUTANDO DEBUG SISTEMA...');
            
            const debugInfo = {
                usuario: <?php echo json_encode($usuarioLogado); ?>,
                timestamp: new Date().toISOString(),
                documentosCarregados: documentosPendentes.length,
                cacheAtivo: cache.cache.size,
                autoUpdateAtivo: autoUpdater.isActive,
                temPermissao: temPermissao
            };
            
            console.log('📊 Info do Sistema:', debugInfo);
            
            let debugReport = `
                <div class="debug-report">
                    <h6><i class="fas fa-info-circle"></i> Debug do Sistema</h6>
                    <small class="text-muted">Timestamp: ${debugInfo.timestamp}</small>
                    
                    <div class="mt-3">
                        <strong>👤 Usuário:</strong><br>
                        Nome: ${debugInfo.usuario.nome}<br>
                        Cargo: ${debugInfo.usuario.cargo || 'N/A'}<br>
                        Departamento ID: ${debugInfo.usuario.departamento_id || 'N/A'}<br>
                        Tem permissão: ${debugInfo.temPermissao ? 'Sim' : 'Não'}
                    </div>
                    
                    <div class="mt-3">
                        <strong>📁 Documentos:</strong><br>
                        Carregados: ${debugInfo.documentosCarregados}<br>
                        Cache: ${debugInfo.cacheAtivo} itens<br>
                        Auto-update: ${debugInfo.autoUpdateAtivo ? 'Ativo' : 'Inativo'}
                    </div>
                    
                    <div class="mt-3">
                        <strong>🔗 API Status:</strong><br>
                        <div id="debugApiStatus">Testando...</div>
                    </div>
                </div>
            `;
            
            notifications.show(debugReport, 'info', 15000);
            
            // Teste simples da API apenas se tem permissão
            if (temPermissao) {
                try {
                    const response = await fetch('../api/documentos/documentos_presidencia_listar.php?status=AGUARDANDO_ASSINATURA');
                    const status = response.status;
                    
                    document.getElementById('debugApiStatus').innerHTML = `
                        <span class="${status === 200 ? 'text-success' : 'text-danger'}">
                            <i class="fas fa-${status === 200 ? 'check' : 'times'}"></i>
                            API Documentos: ${status} ${response.statusText}
                        </span>
                    `;
                    
                } catch (error) {
                    document.getElementById('debugApiStatus').innerHTML = `
                        <span class="text-danger">
                            <i class="fas fa-times"></i>
                            API Documentos: Erro - ${error.message}
                        </span>
                    `;
                }
            } else {
                document.getElementById('debugApiStatus').innerHTML = `
                    <span class="text-warning">
                        <i class="fas fa-lock"></i>
                        API Documentos: Sem permissão para testar
                    </span>
                `;
            }
            
            console.log('🔍 DEBUG FINALIZADO');
        }

        // Atualizar contadores
        function atualizarContadores() {
            const totalPendentes = documentosPendentes.length;
            if (window.updateNotificationCount) {
                window.updateNotificationCount(totalPendentes);
            }
        }

        // Funções de formatação
        function formatarCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        function formatarData(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function mostrarSucesso(mensagem) {
            notifications.show(mensagem, 'success');
        }

        function mostrarErro(mensagem) {
            notifications.show(mensagem, 'error');
            
            const container = document.getElementById('documentsList');
            if (container) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle empty-state-icon"></i>
                        <h5 class="empty-state-title">Erro</h5>
                        <p class="empty-state-description">${mensagem}</p>
                        <button class="btn-action primary mt-3" onclick="carregarDocumentosPendentes()">
                            <i class="fas fa-redo"></i>
                            Tentar Novamente
                        </button>
                    </div>
                `;
            }
        }

        console.log('✓ Sistema da Presidência carregado com sucesso! (Versão Completa Corrigida)');
    </script>
</body>

</html>