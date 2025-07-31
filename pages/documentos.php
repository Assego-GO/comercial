<?php
/**
 * Página de Gerenciamento de Documentos com Fluxo de Assinatura
 * pages/documentos.php
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

// Define o título da página
$page_title = 'Documentos - ASSEGO';

// Busca estatísticas de documentos
try {
    $documentos = new Documentos();
    $stats = $documentos->getEstatisticas();
    $statsFluxo = $documentos->getEstatisticasFluxo();

    $totalDocumentos = $stats['total_documentos'] ?? 0;
    $docsVerificados = $stats['verificados'] ?? 0;
    $docsPendentes = $stats['pendentes'] ?? 0;
    $uploadsHoje = $stats['uploads_hoje'] ?? 0;

} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de documentos: " . $e->getMessage());
    $totalDocumentos = $docsVerificados = $docsPendentes = $uploadsHoje = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'avatar' => $usuarioLogado['avatar'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'documentos', // CORREÇÃO: mudei de 'associados' para 'documentos'
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Gerenciamento de Documentos</h1>
                        <p class="page-subtitle">Faça upload, visualize e gerencie documentos dos associados</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn-modern btn-secondary" data-bs-toggle="modal"
                            data-bs-target="#fichaVirtualModal">
                            <i class="fas fa-file-alt"></i>
                            Gerar Ficha Virtual
                        </button>
                        <button class="btn-modern btn-primary" data-bs-toggle="modal"
                            data-bs-target="#uploadFichaModal">
                            <i class="fas fa-file-upload"></i>
                            Upload Ficha Associação
                        </button>
                    </div>
                </div>
            </div>

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
                                            'AGUARDANDO_ASSINATURA' => 'Para Assinatura',
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

            <!-- Content Tabs -->
            <div class="content-tabs" data-aos="fade-up" data-aos-delay="100">
                <div class="content-tab-list">
                    <button class="content-tab active" data-tab="fluxo">
                        <i class="fas fa-exchange-alt"></i>
                        Fluxo de Assinatura
                        <span class="content-tab-badge" id="badgeFluxo">0</span>
                    </button>
                    <button class="content-tab" data-tab="todos">
                        <i class="fas fa-folder"></i>
                        Todos os Documentos
                        <span class="content-tab-badge" id="badgeTodos"><?php echo $totalDocumentos; ?></span>
                    </button>
                    <button class="content-tab" data-tab="pendentes">
                        <i class="fas fa-clock"></i>
                        Pendentes
                        <span class="content-tab-badge" id="badgePendentes"><?php echo $docsPendentes; ?></span>
                    </button>
                    <button class="content-tab" data-tab="verificados">
                        <i class="fas fa-check-circle"></i>
                        Verificados
                        <span class="content-tab-badge" id="badgeVerificados"><?php echo $docsVerificados; ?></span>
                    </button>
                </div>
            </div>

            <!-- Tab Panels -->
            <div id="tabPanels">
                <!-- Fluxo de Assinatura Panel -->
                <div class="tab-panel active" id="fluxo-panel">
                    <!-- Filters -->
                    <div class="actions-bar" data-aos="fade-up" data-aos-delay="200">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label class="filter-label">Status do Fluxo</label>
                                <select class="filter-select" id="filtroStatusFluxo">
                                    <option value="">Todos</option>
                                    <option value="DIGITALIZADO">Aguardando Envio</option>
                                    <option value="AGUARDANDO_ASSINATURA">Para Assinatura</option>
                                    <option value="ASSINADO">Assinados</option>
                                    <option value="FINALIZADO">Finalizados</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Origem</label>
                                <select class="filter-select" id="filtroOrigem">
                                    <option value="">Todas</option>
                                    <option value="FISICO">Físico</option>
                                    <option value="VIRTUAL">Virtual</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Buscar Associado</label>
                                <input type="text" class="filter-input" id="filtroBuscaFluxo" placeholder="Nome ou CPF">
                            </div>
                        </div>

                        <div class="actions-row">
                            <button class="btn-modern btn-secondary" onclick="limparFiltrosFluxo()">
                                <i class="fas fa-eraser"></i>
                                Limpar Filtros
                            </button>
                            <button class="btn-modern btn-primary" onclick="aplicarFiltrosFluxo()">
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

                <!-- Todos os Documentos Panel -->
                <div class="tab-panel" id="todos-panel">
                    <!-- Filters -->
                    <div class="actions-bar" data-aos="fade-up" data-aos-delay="200">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label class="filter-label">Associado</label>
                                <input type="text" class="filter-input" id="filtroAssociado" placeholder="Nome ou CPF">
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Tipo de Documento</label>
                                <select class="filter-select" id="filtroTipo">
                                    <option value="">Todos</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Status</label>
                                <select class="filter-select" id="filtroStatus">
                                    <option value="">Todos</option>
                                    <option value="1">Verificado</option>
                                    <option value="0">Pendente</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Período</label>
                                <select class="filter-select" id="filtroPeriodo">
                                    <option value="">Todo período</option>
                                    <option value="hoje">Hoje</option>
                                    <option value="semana">Esta semana</option>
                                    <option value="mes">Este mês</option>
                                    <option value="ano">Este ano</option>
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

                    <!-- Documents Grid -->
                    <div class="documents-grid" id="documentosList">
                        <!-- Documentos serão carregados aqui -->
                    </div>
                </div>

                <!-- Pendentes Panel -->
                <div class="tab-panel" id="pendentes-panel">
                    <div class="documents-grid" id="documentosPendentesList">
                        <!-- Documentos pendentes serão carregados aqui -->
                    </div>
                </div>

                <!-- Verificados Panel -->
                <div class="tab-panel" id="verificados-panel">
                    <div class="documents-grid" id="documentosVerificadosList">
                        <!-- Documentos verificados serão carregados aqui -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Upload de Ficha de Associação -->
    <div class="modal fade" id="uploadFichaModal" tabindex="-1" aria-labelledby="uploadFichaModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadFichaModalLabel">
                        <i class="fas fa-file-upload me-2" style="color: var(--primary);"></i>
                        Upload de Ficha de Associação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadFichaForm">
                        <div class="mb-4">
                            <label class="form-label">Associado *</label>
                            <select class="form-select" id="fichaAssociadoSelect" required>
                                <option value="">Selecione o associado</option>
                            </select>
                            <small class="text-muted">Selecione o associado para o qual está fazendo upload da
                                ficha</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Origem do Documento *</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipoOrigem" id="origemFisico"
                                        value="FISICO" checked>
                                    <label class="form-check-label" for="origemFisico">
                                        <i class="fas fa-paper-plane me-1"></i>
                                        Físico (Digitalizado)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipoOrigem" id="origemVirtual"
                                        value="VIRTUAL">
                                    <label class="form-check-label" for="origemVirtual">
                                        <i class="fas fa-laptop me-1"></i>
                                        Virtual (Gerado no sistema)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Arquivo da Ficha *</label>
                            <div class="upload-area" id="uploadFichaArea">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <h6 class="upload-title">Arraste o arquivo aqui ou clique para selecionar</h6>
                                <p class="upload-subtitle">Formato aceito: PDF</p>
                                <p class="upload-subtitle">Tamanho máximo: 10MB</p>
                                <input type="file" id="fichaFileInput" class="d-none" accept=".pdf">
                            </div>
                        </div>

                        <div id="fichaFilesList" class="mb-4"></div>

                        <div class="mb-4">
                            <label class="form-label">Observações (opcional)</label>
                            <textarea class="form-control" id="fichaObservacao" rows="3"
                                placeholder="Adicione observações sobre a ficha..."></textarea>
                        </div>

                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>Fluxo de Assinatura:</strong><br>
                                Após o upload, a ficha será enviada para a presidência assinar e depois retornará ao
                                comercial.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-modern btn-primary" onclick="realizarUploadFicha()">
                        <i class="fas fa-upload me-2"></i>
                        Fazer Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Gerar Ficha Virtual -->
    <div class="modal fade" id="fichaVirtualModal" tabindex="-1" aria-labelledby="fichaVirtualModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fichaVirtualModalLabel">
                        <i class="fas fa-file-alt me-2" style="color: var(--primary);"></i>
                        Gerar Ficha Virtual de Associação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="fichaVirtualForm">
                        <div class="mb-4">
                            <label class="form-label">Associado *</label>
                            <select class="form-select" id="virtualAssociadoSelect" required>
                                <option value="">Selecione o associado</option>
                            </select>
                            <small class="text-muted">A ficha será gerada com os dados do associado selecionado</small>
                        </div>

                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>Processo Virtual:</strong><br>
                                A ficha será gerada automaticamente com os dados do associado e seguirá o mesmo fluxo de
                                assinatura.
                            </div>
                        </div>

                        <div id="previewAssociado" class="d-none">
                            <h6 class="mb-3">Dados que serão incluídos na ficha:</h6>
                            <div class="bg-light rounded p-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <strong>Nome:</strong>
                                        <p class="mb-0" id="previewNome">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>CPF:</strong>
                                        <p class="mb-0" id="previewCPF">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>RG:</strong>
                                        <p class="mb-0" id="previewRG">-</p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Email:</strong>
                                        <p class="mb-0" id="previewEmail">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn-modern btn-primary" onclick="gerarFichaVirtual()">
                        <i class="fas fa-file-alt me-2"></i>
                        Gerar Ficha
                    </button>
                </div>
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
                                <p class="upload-subtitle" style="font-size: 0.75rem;">Se desejar, faça upload do PDF
                                    assinado</p>
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
    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais
        let arquivoFichaSelecionado = null;
        let arquivoAssinaturaSelecionado = null;
        let tiposDocumentos = [];
        let tabAtual = 'fluxo';

        // Inicialização
        $(document).ready(function () {
            carregarEstatisticas();
            carregarTiposDocumentos();
            carregarDocumentosFluxo();
            carregarAssociados();
            configurarUploadFicha();
            configurarUploadAssinatura();
            configurarUserMenu();
            configurarTabs();
        });

        // Configurar tabs
        function configurarTabs() {
            $('.content-tab').on('click', function () {
                const tab = $(this).data('tab');

                // Atualizar tabs
                $('.content-tab').removeClass('active');
                $(this).addClass('active');

                // Atualizar panels
                $('.tab-panel').removeClass('active');
                $(`#${tab}-panel`).addClass('active');

                // Carregar conteúdo da tab
                tabAtual = tab;
                switch (tab) {
                    case 'fluxo':
                        carregarDocumentosFluxo();
                        break;
                    case 'todos':
                        carregarDocumentos();
                        break;
                    case 'pendentes':
                        carregarDocumentos({ verificado: 'nao' }, 'documentosPendentesList');
                        break;
                    case 'verificados':
                        carregarDocumentos({ verificado: 'sim' }, 'documentosVerificadosList');
                        break;
                }
            });
        }

        // CORREÇÃO: Configurar menu do usuário com abordagem mais específica
        function configurarUserMenu() {
            // Função para tentar configurar o dropdown
            function tentarConfigurarDropdown() {
                // Procura por todos os elementos que podem ser o botão do usuário
                const possiveisElementos = [
                    // Por ID
                    document.getElementById('userMenu'),
                    document.getElementById('user-menu'),
                    // Por classe ou atributo que contenha o nome do usuário
                    ...Array.from(document.querySelectorAll('[class*="user"]')),
                    ...Array.from(document.querySelectorAll('[id*="user"]')),
                    // Por conteúdo (procura elementos que contenham "LUIS FILIPE")
                    ...Array.from(document.querySelectorAll('*')).filter(el =>
                        el.textContent && el.textContent.includes('LUIS FILIPE')
                    ),
                    // Elementos com dropdown do Bootstrap
                    ...Array.from(document.querySelectorAll('[data-bs-toggle="dropdown"]')),
                    // Elementos clickáveis na área do header
                    ...Array.from(document.querySelectorAll('button, [role="button"], .btn')).filter(el => {
                        const rect = el.getBoundingClientRect();
                        return rect.top < 100; // Elementos no topo da página (header)
                    })
                ];

                console.log('Elementos encontrados para teste:', possiveisElementos.length);

                for (const elemento of possiveisElementos) {
                    if (!elemento) continue;

                    // Procura pelo dropdown associado
                    let dropdown = null;

                    // Métodos para encontrar o dropdown
                    const metodosDropdown = [
                        // Por aria-controls
                        () => elemento.getAttribute('aria-controls') ?
                            document.getElementById(elemento.getAttribute('aria-controls')) : null,
                        // Por data-bs-target  
                        () => elemento.getAttribute('data-bs-target') ?
                            document.querySelector(elemento.getAttribute('data-bs-target')) : null,
                        // Próximo elemento com classe dropdown
                        () => elemento.nextElementSibling?.classList.contains('dropdown-menu') ?
                            elemento.nextElementSibling : null,
                        // Filho direto com classe dropdown
                        () => elemento.querySelector('.dropdown-menu'),
                        // Irmão com classe dropdown
                        () => elemento.parentNode?.querySelector('.dropdown-menu'),
                        // Por posição (elemento abaixo do botão)
                        () => {
                            const rect = elemento.getBoundingClientRect();
                            const elementoAbaixo = document.elementFromPoint(
                                rect.left + rect.width / 2,
                                rect.bottom + 10
                            );
                            return elementoAbaixo?.closest('.dropdown-menu');
                        }
                    ];

                    // Tenta cada método para encontrar o dropdown
                    for (const metodo of metodosDropdown) {
                        try {
                            dropdown = metodo();
                            if (dropdown) break;
                        } catch (e) {
                            // Ignora erros e continua tentando
                        }
                    }

                    // Se encontrou um par válido, configura
                    if (dropdown && elemento !== dropdown) {
                        console.log('✓ Configurando dropdown do usuário:', elemento, dropdown);

                        // Remove listeners anteriores se existirem
                        elemento.removeEventListener('click', handleUserMenuClick);
                        document.removeEventListener('click', handleDocumentClick);

                        // Adiciona novos listeners
                        elemento.addEventListener('click', handleUserMenuClick);
                        document.addEventListener('click', handleDocumentClick);

                        // Armazena referências globais
                        window.userMenuElement = elemento;
                        window.userDropdownElement = dropdown;

                        return true; // Sucesso
                    }
                }

                return false; // Não encontrou
            }

            // Handlers para o menu
            function handleUserMenuClick(e) {
                e.preventDefault();
                e.stopPropagation();

                if (window.userDropdownElement) {
                    const isVisible = window.userDropdownElement.classList.contains('show');

                    // Fecha todos os dropdowns primeiro
                    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });

                    // Alterna o estado do dropdown atual
                    if (!isVisible) {
                        window.userDropdownElement.classList.add('show');
                        window.userDropdownElement.style.display = 'block';
                    }
                }
            }

            function handleDocumentClick(e) {
                if (window.userDropdownElement &&
                    !window.userMenuElement?.contains(e.target) &&
                    !window.userDropdownElement.contains(e.target)) {
                    window.userDropdownElement.classList.remove('show');
                    window.userDropdownElement.style.display = '';
                }
            }

            // Tenta configurar imediatamente
            if (!tentarConfigurarDropdown()) {
                // Se não conseguiu, tenta novamente após um delay
                setTimeout(() => {
                    if (!tentarConfigurarDropdown()) {
                        // Última tentativa após mais tempo
                        setTimeout(() => {
                            if (!tentarConfigurarDropdown()) {
                                console.log('⚠ Não foi possível configurar o dropdown do usuário automaticamente');
                                console.log('💡 Verificar se o HeaderComponent está usando data-bs-toggle="dropdown"');
                            }
                        }, 2000);
                    }
                }, 1000);
            }

            // Também configura dropdowns padrão do Bootstrap como fallback
            setTimeout(() => {
                if (typeof bootstrap !== 'undefined') {
                    // Reinicializa dropdowns do Bootstrap
                    const dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
                    dropdownElementList.map(function (dropdownToggleEl) {
                        return new bootstrap.Dropdown(dropdownToggleEl);
                    });
                    console.log('✓ Dropdowns do Bootstrap reinicializados');
                }
            }, 1500);
        }

        // Carregar estatísticas
        function carregarEstatisticas() {
            $.get('../api/documentos/documentos_estatisticas.php', function (response) {
                if (response.status === 'success') {
                    // Atualizar badges se necessário
                    if (response.data.fluxo && response.data.fluxo.por_status) {
                        let totalFluxo = 0;
                        response.data.fluxo.por_status.forEach(status => {
                            totalFluxo += parseInt(status.total);
                        });
                        $('#badgeFluxo').text(totalFluxo);
                    }
                }
            });
        }

        // Carregar tipos de documentos
        function carregarTiposDocumentos() {
            $.get('../api/documentos/documentos_tipos.php', function (response) {
                if (response.status === 'success') {
                    tiposDocumentos = response.tipos_documentos;

                    // Preencher select de filtros
                    const filtroTipo = $('#filtroTipo');
                    filtroTipo.empty().append('<option value="">Todos</option>');

                    tiposDocumentos.forEach(tipo => {
                        if (tipo.codigo !== 'ficha_associacao') { // Não mostrar ficha no filtro geral
                            filtroTipo.append(`<option value="${tipo.codigo}">${tipo.nome}</option>`);
                        }
                    });
                }
            });
        }

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
                            <p>Faça upload de fichas de associação para iniciar o processo</p>
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
                                <h6 class="document-title">Ficha de Associação</h6>
                                <p class="document-subtitle">${doc.tipo_origem === 'VIRTUAL' ? 'Virtual' : 'Físico'}</p>
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
                                <span>${doc.departamento_atual_nome || 'Não definido'}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>${formatarData(doc.data_upload)}</span>
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
                                Download
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
                            Enviar p/ Assinatura
                        </button>
                    `;
                    break;

                case 'AGUARDANDO_ASSINATURA':
                    // Verificar se usuário tem permissão para assinar
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
                        <button class="btn-modern btn-success btn-sm" onclick="finalizarProcesso(${doc.id})" title="Finalizar">
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
                            carregarDocumentosFluxo();
                            carregarEstatisticas();
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
            formData.append('observacao', observacao);

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
                        carregarDocumentosFluxo();
                        carregarEstatisticas();
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
            if (confirm('Deseja finalizar o processo deste documento?\n\nO documento será marcado como concluído.')) {
                $.ajax({
                    url: '../api/documentos/documentos_finalizar.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Processo finalizado'
                    }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Processo finalizado com sucesso!');
                            carregarDocumentosFluxo();
                            carregarEstatisticas();
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

        // Configurar área de upload de ficha
        function configurarUploadFicha() {
            const uploadArea = document.getElementById('uploadFichaArea');
            const fileInput = document.getElementById('fichaFileInput');

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
                handleFichaFile(e.dataTransfer.files[0]);
            });

            // Seleção de arquivo
            fileInput.addEventListener('change', (e) => {
                handleFichaFile(e.target.files[0]);
            });
        }

        // Configurar área de upload de assinatura
        function configurarUploadAssinatura() {
            const uploadArea = document.getElementById('uploadAssinaturaArea');
            const fileInput = document.getElementById('assinaturaFileInput');

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

        // Processar arquivo de ficha
        function handleFichaFile(file) {
            if (!file) return;

            // Verificar se é PDF
            if (file.type !== 'application/pdf') {
                alert('Por favor, selecione apenas arquivos PDF');
                return;
            }

            arquivoFichaSelecionado = file;

            const filesList = $('#fichaFilesList');
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
                    <button type="button" class="btn-remove" onclick="removerArquivoFicha()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
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

        // Remover arquivo de ficha
        function removerArquivoFicha() {
            arquivoFichaSelecionado = null;
            $('#fichaFilesList').empty();
            $('#fichaFileInput').val('');
        }

        // Remover arquivo de assinatura
        function removerArquivoAssinatura() {
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaFilesList').empty();
            $('#assinaturaFileInput').val('');
        }

        // Realizar upload de ficha
        function realizarUploadFicha() {
            const associadoId = $('#fichaAssociadoSelect').val();
            const tipoOrigem = $('input[name="tipoOrigem"]:checked').val();
            const observacao = $('#fichaObservacao').val();

            if (!associadoId || !arquivoFichaSelecionado) {
                alert('Por favor, preencha todos os campos obrigatórios');
                return;
            }

            const formData = new FormData();
            formData.append('associado_id', associadoId);
            formData.append('tipo_origem', tipoOrigem);
            formData.append('observacao', observacao);
            formData.append('documento', arquivoFichaSelecionado);

            // Mostra loading no botão
            const btnUpload = event.target;
            const btnText = btnUpload.innerHTML;
            btnUpload.disabled = true;
            btnUpload.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';

            $.ajax({
                url: '../api/documentos/documentos_ficha_upload.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.status === 'success') {
                        alert('Ficha de associação enviada com sucesso!\n\nEla seguirá o fluxo de assinatura.');
                        $('#uploadFichaModal').modal('hide');
                        $('#uploadFichaForm')[0].reset();
                        arquivoFichaSelecionado = null;
                        $('#fichaFilesList').empty();
                        carregarDocumentosFluxo();
                        carregarEstatisticas();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function () {
                    alert('Erro ao fazer upload. Por favor, tente novamente.');
                },
                complete: function () {
                    btnUpload.disabled = false;
                    btnUpload.innerHTML = btnText;
                }
            });
        }

        // Gerar ficha virtual
        function gerarFichaVirtual() {
            const associadoId = $('#virtualAssociadoSelect').val();

            if (!associadoId) {
                alert('Por favor, selecione um associado');
                return;
            }

            if (!confirm('Confirma a geração da ficha virtual?\n\nA ficha será gerada com os dados atuais do associado.')) {
                return;
            }

            // Mostra loading no botão
            const btnGerar = event.target;
            const btnText = btnGerar.innerHTML;
            btnGerar.disabled = true;
            btnGerar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando...';

            $.ajax({
                url: '../api/documentos/documentos_gerar_ficha_virtual.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ associado_id: associadoId }),
                success: function (response) {
                    if (response.status === 'success') {
                        alert('Ficha virtual gerada com sucesso!\n\nAgora você pode fazer o upload dela.');
                        $('#fichaVirtualModal').modal('hide');

                        // Abrir modal de upload com o associado já selecionado
                        $('#fichaAssociadoSelect').val(associadoId);
                        $('input[name="tipoOrigem"][value="VIRTUAL"]').prop('checked', true);
                        $('#uploadFichaModal').modal('show');
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function () {
                    alert('Erro ao gerar ficha virtual');
                },
                complete: function () {
                    btnGerar.disabled = false;
                    btnGerar.innerHTML = btnText;
                }
            });
        }

        // Atualizar preview do associado
        $('#virtualAssociadoSelect').on('change', function () {
            const associadoId = $(this).val();

            if (!associadoId) {
                $('#previewAssociado').addClass('d-none');
                return;
            }

            // Buscar dados do associado selecionado
            const option = $(this).find('option:selected');
            const texto = option.text();

            // Extrair nome e CPF do texto da opção
            const partes = texto.split(' - CPF: ');
            const nome = partes[0];
            const cpf = partes[1] || '';

            $('#previewNome').text(nome);
            $('#previewCPF').text(cpf);
            $('#previewRG').text('-'); // Seria necessário buscar via API
            $('#previewEmail').text('-'); // Seria necessário buscar via API

            $('#previewAssociado').removeClass('d-none');
        });

        // Carregar associados
        function carregarAssociados() {
            $.get('../api/carregar_associados.php', function (response) {
                if (response.status === 'success') {
                    const selectFicha = $('#fichaAssociadoSelect');
                    const selectVirtual = $('#virtualAssociadoSelect');

                    selectFicha.empty().append('<option value="">Selecione o associado</option>');
                    selectVirtual.empty().append('<option value="">Selecione o associado</option>');

                    response.dados.forEach(associado => {
                        const cpfFormatado = formatarCPF(associado.cpf);
                        const option = `<option value="${associado.id}">${associado.nome} - CPF: ${cpfFormatado}</option>`;
                        selectFicha.append(option);
                        selectVirtual.append(option);
                    });
                }
            });
        }

        // Carregar documentos (tab todos)
        function carregarDocumentos(filtros = {}, containerId = 'documentosList') {
            const container = $('#' + containerId);

            // Mostra loading
            container.html(`
                <div class="col-12 text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando documentos...</p>
                </div>
            `);

            $.get('../api/documentos/documentos_listar.php', filtros, function (response) {
                if (response.status === 'success') {
                    renderizarDocumentos(response.data, containerId);
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

        // Renderizar documentos
        function renderizarDocumentos(documentos, containerId) {
            const container = $('#' + containerId);
            container.empty();

            if (documentos.length === 0) {
                container.html(`
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h5>Nenhum documento encontrado</h5>
                            <p>Não há documentos com os filtros selecionados</p>
                        </div>
                    </div>
                `);
                return;
            }

            documentos.forEach(doc => {
                // Pular fichas de associação na listagem geral
                if (doc.tipo_documento === 'ficha_associacao') return;

                const iconClass = getIconClass(doc.extensao);
                const badge = doc.verificado == 1
                    ? '<span class="status-badge assinado"><i class="fas fa-check me-1"></i>Verificado</span>'
                    : '<span class="status-badge aguardando-assinatura"><i class="fas fa-clock me-1"></i>Pendente</span>';

                const cardHtml = `
                    <div class="document-card" data-aos="fade-up">
                        ${badge}
                        <div class="document-header">
                            <div class="document-icon ${iconClass}">
                                <i class="fas fa-file-${iconClass}"></i>
                            </div>
                            <div class="document-info">
                                <h6 class="document-title">${doc.tipo_documento_nome}</h6>
                                <p class="document-subtitle">${doc.nome_arquivo}</p>
                            </div>
                        </div>
                        
                        <div class="document-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span>${doc.associado_nome}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>${formatarData(doc.data_upload)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-file"></i>
                                <span>${doc.tamanho_formatado}</span>
                            </div>
                            ${doc.observacao ? `
                                <div class="meta-item">
                                    <i class="fas fa-comment"></i>
                                    <span>${doc.observacao}</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <div class="document-actions">
                            <button class="btn-modern btn-primary btn-sm" onclick="downloadDocumento(${doc.id})" title="Download">
                                <i class="fas fa-download"></i>
                                Download
                            </button>
                            ${doc.verificado == 0 ? `
                                <button class="btn-modern btn-success btn-sm" onclick="verificarDocumento(${doc.id})" title="Verificar">
                                    <i class="fas fa-check"></i>
                                    Verificar
                                </button>
                            ` : ''}
                            <button class="btn-modern btn-danger btn-sm" onclick="excluirDocumento(${doc.id})" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;

                container.append(cardHtml);
            });
        }

        // Funções auxiliares
        function getIconClass(extensao) {
            const ext = extensao?.toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) return 'image';
            if (ext === 'pdf') return 'pdf';
            if (['doc', 'docx'].includes(ext)) return 'word';
            if (['xls', 'xlsx'].includes(ext)) return 'excel';
            return 'alt';
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

        function verificarDocumento(id) {
            if (confirm('Confirma a verificação deste documento?')) {
                $.ajax({
                    url: '../api/documentos/documentos_verificar.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ documento_id: id }),
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Documento verificado com sucesso!');
                            carregarDocumentos();
                            carregarEstatisticas();
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao verificar documento');
                    }
                });
            }
        }

        function excluirDocumento(id) {
            if (confirm('Tem certeza que deseja excluir este documento?\n\nEsta ação não pode ser desfeita!')) {
                $.ajax({
                    url: '../api/documentos/documentos_excluir.php?id=' + id,
                    type: 'DELETE',
                    success: function (response) {
                        if (response.status === 'success') {
                            alert('Documento excluído com sucesso!');
                            carregarDocumentos();
                            carregarEstatisticas();
                        } else {
                            alert('Erro: ' + response.message);
                        }
                    },
                    error: function () {
                        alert('Erro ao excluir documento');
                    }
                });
            }
        }

        // Aplicar filtros do fluxo
        function aplicarFiltrosFluxo() {
            const filtros = {};

            const status = $('#filtroStatusFluxo').val();
            if (status) filtros.status = status;

            const origem = $('#filtroOrigem').val();
            if (origem) filtros.origem = origem;

            const busca = $('#filtroBuscaFluxo').val().trim();
            if (busca) filtros.busca = busca;

            carregarDocumentosFluxo(filtros);
        }

        // Limpar filtros do fluxo
        function limparFiltrosFluxo() {
            $('#filtroStatusFluxo').val('');
            $('#filtroOrigem').val('');
            $('#filtroBuscaFluxo').val('');
            carregarDocumentosFluxo();
        }

        // Aplicar filtros gerais
        function aplicarFiltros() {
            const filtros = {};

            const busca = $('#filtroAssociado').val().trim();
            if (busca) filtros.busca = busca;

            const tipo = $('#filtroTipo').val();
            if (tipo) filtros.tipo_documento = tipo;

            const status = $('#filtroStatus').val();
            if (status !== '') {
                filtros.verificado = status === '1' ? 'sim' : 'nao';
            }

            const periodo = $('#filtroPeriodo').val();
            if (periodo) filtros.periodo = periodo;

            carregarDocumentos(filtros);
        }

        // Limpar filtros gerais
        function limparFiltros() {
            $('#filtroAssociado').val('');
            $('#filtroTipo').val('');
            $('#filtroStatus').val('');
            $('#filtroPeriodo').val('');
            carregarDocumentos();
        }

        // Fecha modal quando pressiona ESC
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('.modal').modal('hide');
            }
        });

        // Limpa formulários quando modais são fechados
        $('#uploadFichaModal').on('hidden.bs.modal', function () {
            $('#uploadFichaForm')[0].reset();
            arquivoFichaSelecionado = null;
            $('#fichaFilesList').empty();
        });

        $('#fichaVirtualModal').on('hidden.bs.modal', function () {
            $('#fichaVirtualForm')[0].reset();
            $('#previewAssociado').addClass('d-none');
        });

        $('#assinaturaModal').on('hidden.bs.modal', function () {
            $('#assinaturaForm')[0].reset();
            arquivoAssinaturaSelecionado = null;
            $('#assinaturaFilesList').empty();
        });

        console.log('✓ Sistema de documentos com fluxo de assinatura carregado!');
    </script>

</body>

</html>