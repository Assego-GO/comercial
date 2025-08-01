<?php
/**
 * Página de Gerenciamento do Fluxo de Assinatura - VERSÃO SIMPLIFICADA
 * pages/documentos_fluxo.php
 * 
 * Esta página agora serve APENAS para gerenciar o fluxo de assinatura
 * dos documentos que já foram anexados durante o pré-cadastro
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

// CORREÇÃO: Incluir a classe HeaderComponent ANTES de tentar usá-la
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

// DEBUG USUÁRIO LOGADO - CONSOLE (REMOVER APÓS TESTE)
echo "<script>";
echo "console.log('=== DEBUG USUÁRIO LOGADO - DOCUMENTOS ===');";
echo "console.log('Array completo:', " . json_encode($usuarioLogado) . ");";
echo "console.log('Tem departamento_id?', " . (isset($usuarioLogado['departamento_id']) ? 'true' : 'false') . ");";
if (isset($usuarioLogado['departamento_id'])) {
    echo "console.log('Departamento ID valor:', " . json_encode($usuarioLogado['departamento_id']) . ");";
    echo "console.log('Departamento ID tipo:', '" . gettype($usuarioLogado['departamento_id']) . "');";
    echo "console.log('É igual a 1?', " . ($usuarioLogado['departamento_id'] == 1 ? 'true' : 'false') . ");";
    echo "console.log('É idêntico a 1?', " . ($usuarioLogado['departamento_id'] === 1 ? 'true' : 'false') . ");";
    echo "console.log('É idêntico a \"1\"?', " . ($usuarioLogado['departamento_id'] === '1' ? 'true' : 'false') . ");";
}
echo "console.log('isDiretor:', " . ($auth->isDiretor() ? 'true' : 'false') . ");";
echo "console.log('===========================================');";
echo "</script>";

// Define o título da página
$page_title = 'Fluxo de Assinatura - ASSEGO';

// Busca estatísticas de documentos em fluxo
try {
    $documentos = new Documentos();
    $statsFluxo = $documentos->getEstatisticasFluxo();
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de fluxo: " . $e->getMessage());
}

// CORREÇÃO: Cria instância do Header Component - Passa TODO o array do usuário
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado, // ← CORRIGIDO: Agora passa TODO o array (incluindo departamento_id)
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'documentos',
    'notificationCount' => 0,
    'showSearch' => true
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/documentos.css">
</head>

<body>
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Title -->
            <div class="page-header mb-4" data-aos="fade-right">
                <div>
                    <h1 class="page-title">Fluxo de Assinatura de Documentos</h1>
                    <p class="page-subtitle">Gerencie o processo de assinatura das fichas de filiação</p>
                </div>
            </div>

            <!-- Alert Informativo -->
            <div class="alert-info-custom" data-aos="fade-up">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Como funciona o fluxo:</strong><br>
                    1. Ficha é anexada durante o pré-cadastro → 2. Envio para presidência → 3. Assinatura → 4. Retorno ao comercial → 5. Aprovação do pré-cadastro
                </div>
            </div>
            
            <?php if (isset($_GET['novo']) && $_GET['novo'] == '1'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Pré-cadastro criado com sucesso!</strong> 
                A ficha de filiação foi anexada e está aguardando envio para assinatura.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <?php if (isset($statsFluxo['por_status'])): ?>
                    <?php foreach ($statsFluxo['por_status'] as $status): ?>
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value"><?php echo number_format($status['total'] ?? 0, 0, ',', '.'); ?>
                                    </div>
                                    <div class="stat-label">
                                        <?php
                                        $labels = [
                                            'DIGITALIZADO' => 'Aguardando Envio',
                                            'AGUARDANDO_ASSINATURA' => 'Na Presidência',
                                            'ASSINADO' => 'Assinados',
                                            'FINALIZADO' => 'Finalizados'
                                        ];
                                        echo $labels[$status['status_fluxo']] ?? $status['status_fluxo'];
                                        ?>
                                    </div>
                                </div>
                                <div class="stat-icon <?php
                                echo match ($status['status_fluxo']) {
                                    'DIGITALIZADO' => 'info',
                                    'AGUARDANDO_ASSINATURA' => 'warning',
                                    'ASSINADO' => 'success',
                                    'FINALIZADO' => 'primary',
                                    default => 'secondary'
                                };
                                ?>">
                                    <i class="fas <?php
                                    echo match ($status['status_fluxo']) {
                                        'DIGITALIZADO' => 'fa-upload',
                                        'AGUARDANDO_ASSINATURA' => 'fa-clock',
                                        'ASSINADO' => 'fa-check',
                                        'FINALIZADO' => 'fa-flag-checkered',
                                        default => 'fa-file'
                                    };
                                    ?>"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="actions-bar" data-aos="fade-up" data-aos-delay="200">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Status do Fluxo</label>
                        <select class="filter-select" id="filtroStatusFluxo">
                            <option value="">Todos os Status</option>
                            <option value="DIGITALIZADO">Aguardando Envio</option>
                            <option value="AGUARDANDO_ASSINATURA">Na Presidência</option>
                            <option value="ASSINADO">Assinados</option>
                            <option value="FINALIZADO">Finalizados</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Buscar Associado</label>
                        <input type="text" class="filter-input" id="filtroBuscaFluxo" 
                               placeholder="Nome ou CPF do associado">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Período</label>
                        <select class="filter-select" id="filtroPeriodo">
                            <option value="">Todo período</option>
                            <option value="hoje">Hoje</option>
                            <option value="semana">Esta semana</option>
                            <option value="mes">Este mês</option>
                        </select>
                    </div>
                </div>

                <div class="actions-row">
                    <button class="btn-modern btn-secondary" onclick="limparFiltros()">
                        <i class="fas fa-eraser"></i>
                        Limpar Filtros
                    </button>
                    <button class="btn-modern btn-primary" onclick="aplicarFiltros()">
                        <i class="fas fa-filter"></i>
                        Aplicar Filtros
                    </button>
                </div>
            </div>

            <!-- Documents in Flow -->
            <div class="documents-grid" id="documentosFluxoList" data-aos="fade-up" data-aos-delay="300">
                <!-- Documentos em fluxo serão carregados aqui -->
            </div>
        </div>
    </div>

    <!-- Modal de Histórico do Fluxo -->
    <div class="modal fade" id="historicoModal" tabindex="-1" aria-labelledby="historicoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historicoModalLabel">
                        <i class="fas fa-history me-2" style="color: var(--primary);"></i>
                        Histórico do Documento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="historicoContent">
                        <!-- Timeline será carregada aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Assinatura -->
    <div class="modal fade" id="assinaturaModal" tabindex="-1" aria-labelledby="assinaturaModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assinaturaModalLabel">
                        <i class="fas fa-signature me-2" style="color: var(--primary);"></i>
                        Assinar Documento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="assinaturaForm">
                        <input type="hidden" id="assinaturaDocumentoId">

                        <div class="mb-4">
                            <label class="form-label">Arquivo Assinado (opcional)</label>
                            <div class="upload-area small" id="uploadAssinaturaArea" style="padding: 2rem;">
                                <i class="fas fa-file-signature upload-icon" style="font-size: 2rem;"></i>
                                <h6 class="upload-title" style="font-size: 1rem;">Upload do documento assinado</h6>
                                <p class="upload-subtitle" style="font-size: 0.75rem;">Se desejar, faça upload do PDF assinado</p>
                                <input type="file" id="assinaturaFileInput" class="d-none" accept=".pdf">
                            </div>
                        </div>

                        <div id="assinaturaFilesList" class="mb-4"></div>

                        <div class="mb-4">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="assinaturaObservacao" rows="3"
                                placeholder="Adicione observações sobre a assinatura..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-modern btn-success" onclick="assinarDocumento()">
                        <i class="fas fa-check me-2"></i>
                        Confirmar Assinatura
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
    
    <!-- JavaScript customizado para os botões do header -->
    <script>
        
        
        function toggleNotifications() {
            // Implementar painel de notificações
            console.log('Painel de notificações');
            alert('Painel de notificações em desenvolvimento');
        }

        function irParaFuncionarios() {
            // Redireciona para a página de funcionários
            console.log('Navegando para funcionários');
            window.location.href = './funcionarios.php';
        }
    </script>
    
    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais
        let arquivoAssinaturaSelecionado = null;
        let filtrosAtuais = {};

        // Inicialização
        $(document).ready(function () {
            carregarDocumentosFluxo();
            configurarUploadAssinatura();
        });

        // Carregar documentos em fluxo
        function carregarDocumentosFluxo(filtros = {}) {
            const container = $('#documentosFluxoList');

            // Mostra loading
            container.html(`
                <div class="col-12 text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando documentos em fluxo...</p>
                </div>
            `);

            $.get('../api/documentos/documentos_fluxo_listar.php', filtros, function (response) {
                if (response.status === 'success') {
                    renderizarDocumentosFluxo(response.data);
                } else {
                    container.html(`
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h5>Erro ao carregar documentos</h5>
                                <p>${response.message || 'Tente novamente mais tarde'}</p>
                            </div>
                        </div>
                    `);
                }
            }).fail(function () {
                container.html(`
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-wifi-slash"></i>
                            <h5>Erro de conexão</h5>
                            <p>Verifique sua conexão com a internet</p>
                        </div>
                    </div>
                `);
            });
        }

        // Renderizar documentos em fluxo
        function renderizarDocumentosFluxo(documentos) {
            const container = $('#documentosFluxoList');
            container.empty();

            if (documentos.length === 0) {
                container.html(`
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h5>Nenhum documento em fluxo</h5>
                            <p>Os documentos anexados durante o pré-cadastro aparecerão aqui</p>
                        </div>
                    </div>
                `);
                return;
            }

            documentos.forEach(doc => {
                const statusClass = doc.status_fluxo.toLowerCase().replace('_', '-');
                const cardHtml = `
                    <div class="document-card" data-aos="fade-up">
                        <span class="status-badge ${statusClass}">
                            <i class="fas fa-${getStatusIcon(doc.status_fluxo)} me-1"></i>
                            ${doc.status_descricao}
                        </span>
                        
                        <div class="document-header">
                            <div class="document-icon pdf">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h6 class="document-title">Ficha de Filiação</h6>
                                <p class="document-subtitle">${doc.tipo_origem === 'VIRTUAL' ? 'Gerada no Sistema' : 'Digitalizada'}</p>
                            </div>
                        </div>
                        
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
                                <i class="fas fa-building"></i>
                                <span>${doc.departamento_atual_nome || 'Comercial'}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>Cadastrado em ${formatarData(doc.data_upload)}</span>
                            </div>
                            ${doc.dias_em_processo > 0 ? `
                                <div class="meta-item">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span>${doc.dias_em_processo} dias em processo</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Progress do Fluxo -->
                        <div class="fluxo-progress">
                            <div class="fluxo-steps">
                                <div class="fluxo-step ${doc.status_fluxo !== 'DIGITALIZADO' ? 'completed' : 'active'}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="fluxo-step-label">Digitalizado</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'AGUARDANDO_ASSINATURA' ? 'active' : (doc.status_fluxo === 'ASSINADO' || doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-signature"></i>
                                    </div>
                                    <div class="fluxo-step-label">Assinatura</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'ASSINADO' ? 'active' : (doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="fluxo-step-label">Assinado</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'FINALIZADO' ? 'completed' : ''}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                    <div class="fluxo-step-label">Finalizado</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="document-actions">
                            <button class="btn-modern btn-primary btn-sm" onclick="downloadDocumento(${doc.id})" title="Download">
                                <i class="fas fa-download"></i>
                                Baixar
                            </button>
                            
                            ${getAcoesFluxo(doc)}
                            
                            <button class="btn-modern btn-secondary btn-sm" onclick="verHistorico(${doc.id})" title="Histórico">
                                <i class="fas fa-history"></i>
                                Histórico
                            </button>
                        </div>
                    </div>
                `;

                container.append(cardHtml);
            });
        }

        // Obter ações do fluxo baseado no status
        function getAcoesFluxo(doc) {
            let acoes = '';

            switch (doc.status_fluxo) {
                case 'DIGITALIZADO':
                    acoes = `
                        <button class="btn-modern btn-warning btn-sm" onclick="enviarParaAssinatura(${doc.id})" title="Enviar para Assinatura">
                            <i class="fas fa-paper-plane"></i>
                            Enviar
                        </button>
                    `;
                    break;

                case 'AGUARDANDO_ASSINATURA':
                    // Verificar se usuário tem permissão para assinar (apenas presidência)
                    <?php if ($auth->isDiretor() || $usuarioLogado['departamento_id'] == 2): ?>
                        acoes = `
                        <button class="btn-modern btn-success btn-sm" onclick="abrirModalAssinatura(${doc.id})" title="Assinar">
                            <i class="fas fa-signature"></i>
                            Assinar
                        </button>
                    `;
                    <?php endif; ?>
                    break;

                case 'ASSINADO':
                    acoes = `
                        <button class="btn-modern btn-info btn-sm" onclick="finalizarProcesso(${doc.id})" title="Finalizar">
                            <i class="fas fa-flag-checkered"></i>
                            Finalizar
                        </button>
                    `;
                    break;
            }

            return acoes;
        }

        // Obter ícone do status
        function getStatusIcon(status) {
            const icons = {
                'DIGITALIZADO': 'upload',
                'AGUARDANDO_ASSINATURA': 'clock',
                'ASSINADO': 'check',
                'FINALIZADO': 'flag-checkered'
            };
            return icons[status] || 'file';
        }

        // Enviar para assinatura
        function enviarParaAssinatura(documentoId) {
            if (confirm('Deseja enviar este documento para assinatura na presidência?')) {
                $.ajax({
                    url: '../api/documentos/documentos_enviar_assinatura.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Documento enviado para assinatura'
                    }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Documento enviado para assinatura com sucesso!');
                            carregarDocumentosFluxo(filtrosAtuais);
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao enviar documento para assinatura');
                    }
                });
            }
        }

        // Abrir modal de assinatura
        function abrirModalAssinatura(documentoId) {
            $('#assinaturaDocumentoId').val(documentoId);
            $('#assinaturaObservacao').val('');
            $('#assinaturaFilesList').empty();
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaModal').modal('show');
        }

        // Assinar documento
        function assinarDocumento() {
            const documentoId = $('#assinaturaDocumentoId').val();
            const observacao = $('#assinaturaObservacao').val();

            const formData = new FormData();
            formData.append('documento_id', documentoId);
            formData.append('observacao', observacao || 'Documento assinado pela presidência');

            if (arquivoAssinaturaSelecionado) {
                formData.append('arquivo_assinado', arquivoAssinaturaSelecionado);
            }

            // Mostra loading no botão
            const btnAssinar = event.target;
            const btnText = btnAssinar.innerHTML;
            btnAssinar.disabled = true;
            btnAssinar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Assinando...';

            $.ajax({
                url: '../api/documentos/documentos_assinar.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.status === 'success') {
                        alert('Documento assinado com sucesso!');
                        $('#assinaturaModal').modal('hide');
                        carregarDocumentosFluxo(filtrosAtuais);
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function () {
                    alert('Erro ao assinar documento');
                },
                complete: function () {
                    btnAssinar.disabled = false;
                    btnAssinar.innerHTML = btnText;
                }
            });
        }

        // Finalizar processo
        function finalizarProcesso(documentoId) {
            if (confirm('Deseja finalizar o processo deste documento?\n\nO documento retornará ao comercial e o pré-cadastro poderá ser aprovado.')) {
                $.ajax({
                    url: '../api/documentos/documentos_finalizar.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Processo finalizado - Documento pronto para aprovação do pré-cadastro'
                    }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Processo finalizado com sucesso!\n\nO pré-cadastro já pode ser aprovado.');
                            carregarDocumentosFluxo(filtrosAtuais);
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao finalizar processo');
                    }
                });
            }
        }

        // Ver histórico
        function verHistorico(documentoId) {
            $.get('../api/documentos/documentos_historico_fluxo.php', { documento_id: documentoId }, function (response) {
                if (response.status === 'success') {
                    renderizarHistorico(response.data);
                    $('#historicoModal').modal('show');
                } else {
                    alert('Erro ao carregar histórico');
                }
            });
        }

        // Renderizar histórico
        function renderizarHistorico(historico) {
            const container = $('#historicoContent');
            container.empty();

            if (historico.length === 0) {
                container.html('<p class="text-muted text-center">Nenhum histórico disponível</p>');
                return;
            }

            const timeline = $('<div class="timeline"></div>');

            historico.forEach(item => {
                const timelineItem = `
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">${item.status_novo}</h6>
                                <span class="timeline-date">${formatarData(item.data_acao)}</span>
                            </div>
                            <p class="timeline-description mb-2">${item.observacao}</p>
                            <p class="timeline-description text-muted mb-0">
                                <small>
                                    Por: ${item.funcionario_nome}<br>
                                    ${item.dept_origem_nome ? `De: ${item.dept_origem_nome}<br>` : ''}
                                    ${item.dept_destino_nome ? `Para: ${item.dept_destino_nome}` : ''}
                                </small>
                            </p>
                        </div>
                    </div>
                `;
                timeline.append(timelineItem);
            });

            container.append(timeline);
        }

        // Configurar área de upload de assinatura
        function configurarUploadAssinatura() {
            const uploadArea = document.getElementById('uploadAssinaturaArea');
            const fileInput = document.getElementById('assinaturaFileInput');

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
                handleAssinaturaFile(e.dataTransfer.files[0]);
            });

            // Seleção de arquivo
            fileInput.addEventListener('change', (e) => {
                handleAssinaturaFile(e.target.files[0]);
            });
        }

        // Processar arquivo de assinatura
        function handleAssinaturaFile(file) {
            if (!file) return;

            // Verificar se é PDF
            if (file.type !== 'application/pdf') {
                alert('Por favor, selecione apenas arquivos PDF');
                return;
            }

            arquivoAssinaturaSelecionado = file;

            const filesList = $('#assinaturaFilesList');
            filesList.empty();

            filesList.append(`
                <div class="file-item">
                    <div class="file-item-info">
                        <div class="file-item-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <div class="file-item-name">${file.name}</div>
                            <div class="file-item-size">${formatBytes(file.size)}</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove" onclick="removerArquivoAssinatura()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
        }

        // Remover arquivo de assinatura
        function removerArquivoAssinatura() {
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaFilesList').empty();
            $('#assinaturaFileInput').val('');
        }

        // Aplicar filtros
        function aplicarFiltros() {
            filtrosAtuais = {};

            const status = $('#filtroStatusFluxo').val();
            if (status) filtrosAtuais.status = status;

            const busca = $('#filtroBuscaFluxo').val().trim();
            if (busca) filtrosAtuais.busca = busca;

            const periodo = $('#filtroPeriodo').val();
            if (periodo) filtrosAtuais.periodo = periodo;

            carregarDocumentosFluxo(filtrosAtuais);
        }

        // Limpar filtros
        function limparFiltros() {
            $('#filtroStatusFluxo').val('');
            $('#filtroBuscaFluxo').val('');
            $('#filtroPeriodo').val('');
            filtrosAtuais = {};
            carregarDocumentosFluxo();
        }

        // Funções auxiliares
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

        function formatarCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function downloadDocumento(id) {
            window.open('../api/documentos/documentos_download.php?id=' + id, '_blank');
        }

        // Fecha modal quando pressiona ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('.modal').modal('hide');
            }
        });

        // Limpa formulários quando modais são fechados
        $('#assinaturaModal').on('hidden.bs.modal', function () {
            $('#assinaturaForm')[0].reset();
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaFilesList').empty();
        });

        // Auto-refresh a cada 30 segundos
        setInterval(function() {
            carregarDocumentosFluxo(filtrosAtuais);
        }, 30000);

        console.log('✓ Sistema de fluxo de assinatura carregado com Header Component!');
    </script>

</body>

</html>