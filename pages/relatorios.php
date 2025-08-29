<?php
/**
 * Página de Relatórios com Sistema de Permissões por Departamento
 * pages/relatorios.php
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Relatorios.php';

// NOVO: Include do componente Header
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

// DEBUG USUÁRIO LOGADO - CONSOLE
echo "<script>";
echo "console.log('=== DEBUG USUÁRIO LOGADO ===');";
echo "console.log('Array completo:', " . json_encode($usuarioLogado) . ");";
echo "console.log('Tem departamento_id?', " . (isset($usuarioLogado['departamento_id']) ? 'true' : 'false') . ");";
if (isset($usuarioLogado['departamento_id'])) {
    echo "console.log('Departamento ID valor:', " . json_encode($usuarioLogado['departamento_id']) . ");";
    echo "console.log('Departamento ID tipo:', '" . gettype($usuarioLogado['departamento_id']) . "');";
}
echo "console.log('isDiretor:', " . ($auth->isDiretor() ? 'true' : 'false') . ");";
echo "console.log('=============================');";
echo "</script>";

// Define o título da página
$page_title = 'Relatórios - ASSEGO';

// NOVO SISTEMA DE PERMISSÕES POR DEPARTAMENTO
// Função atualizada com permissões para aniversariantes
function getPermissoesDepartamento($departamentoId) {
    $departamentoId = (int)$departamentoId;
    
    $permissoes = [
        1 => [ // Presidência
            'nome' => 'Presidência',
            'acesso_total' => true,
            'criar_personalizado' => true,
            'relatorios_permitidos' => [
                'associados', 'financeiro', 'militar', 'servicos', 'documentos', 'estatisticas', 'aniversariantes'
            ]
        ],
        2 => [ // Financeiro (ID correto: 2)
            'nome' => 'Financeiro', 
            'acesso_total' => false,
            'criar_personalizado' => false,
            'relatorios_permitidos' => [
                'financeiro', 'servicos', 'estatisticas'
            ]
        ],
        10 => [ // Comercial (ID correto: 10)
            'nome' => 'Comercial',
            'acesso_total' => false,
            'criar_personalizado' => false,
            'relatorios_permitidos' => [
                'associados', 'militar', 'documentos', 'aniversariantes'
            ]
        ]
    ];
    
    return $permissoes[$departamentoId] ?? null;
}

// Verificar permissões do usuário
$temPermissaoRelatorios = false;
$motivoNegacao = '';
$permissoesDept = null;
$isPresidencia = false;

// Debug completo ANTES das verificações
error_log("=== DEBUG DETALHADO PERMISSÕES RELATÓRIOS ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Array completo do usuário: " . print_r($usuarioLogado, true));
error_log("Departamento ID (valor): " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("Departamento ID (tipo): " . gettype($usuarioLogado['departamento_id'] ?? null));

if (isset($usuarioLogado['departamento_id'])) {
    $deptId = (int)$usuarioLogado['departamento_id'];
    $permissoesDept = getPermissoesDepartamento($deptId);
    
    if ($permissoesDept) {
        $temPermissaoRelatorios = true;
        $isPresidencia = ($deptId === 1);
        
        error_log("✅ Acesso concedido para departamento: " . $permissoesDept['nome']);
        error_log("Relatórios permitidos: " . implode(', ', $permissoesDept['relatorios_permitidos']));
        error_log("Pode criar personalizado: " . ($permissoesDept['criar_personalizado'] ? 'SIM' : 'NÃO'));
    } else {
        $motivoNegacao = 'Seu departamento não tem acesso ao sistema de relatórios.';
        error_log("❌ Departamento sem permissão: ID = $deptId");
    }
} else {
    $motivoNegacao = 'Departamento não identificado.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Função para verificar se pode acessar um relatório específico
function podeAcessarRelatorio($tipo, $permissoesDept) {
    if (!$permissoesDept) return false;
    if ($permissoesDept['acesso_total']) return true;
    return in_array($tipo, $permissoesDept['relatorios_permitidos']);
}

// Log final do resultado
if (!$temPermissaoRelatorios) {
    error_log("❌ ACESSO NEGADO: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO - Departamento: " . $permissoesDept['nome'] . " | Relatórios: " . implode(', ', $permissoesDept['relatorios_permitidos']));
}

// Se não tem permissão, mostra página de erro
if (!$temPermissaoRelatorios) {
    // Cria instância do Header Component
    $headerComponent = HeaderComponent::create([
        'usuario' => $usuarioLogado,
        'isDiretor' => $auth->isDiretor(),
        'activeTab' => 'relatorios',
        'notificationCount' => 0,
        'showSearch' => false
    ]);
    
    // Renderiza página de erro
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso Negado - ASSEGO</title>
        <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
        <?php $headerComponent->renderCSS(); ?>
        <style>
            body {
                background-color: #f8f9fa;
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
            .main-wrapper {
                min-height: 100vh;
            }
            .content-area {
                padding: 2rem;
                max-width: 1200px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="main-wrapper">
            <?php $headerComponent->render(); ?>
            
            <div class="content-area">
                <div class="alert alert-danger" data-aos="fade-up">
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Área de Relatórios</h4>
                    <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Apenas estes departamentos têm acesso aos relatórios:</h6>
                        <ul class="mb-0">
                            <li><strong>Presidência:</strong> Acesso total a todos os relatórios</li>
                            <li><strong>Comercial:</strong> Associados, Distribuição Militar, Status de Documentos</li>
                            <li><strong>Financeiro:</strong> Situação Financeira, Adesão aos Serviços, Estatísticas</li>
                        </ul>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Suas informações atuais:</h6>
                            <ul class="mb-0">
                                <li><strong>Nome:</strong> <?php echo htmlspecialchars($usuarioLogado['nome']); ?></li>
                                <li><strong>Departamento ID:</strong> <?php echo htmlspecialchars($usuarioLogado['departamento_id'] ?? 'N/A'); ?></li>
                                <li><strong>É Diretor:</strong> 
                                    <span class="badge bg-<?php echo $auth->isDiretor() ? 'success' : 'danger'; ?>">
                                        <?php echo $auth->isDiretor() ? 'Sim' : 'Não'; ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Para solicitar acesso:</h6>
                            <ul class="mb-3">
                                <li>Entre em contato com a Presidência</li>
                                <li>Verifique se está no departamento correto</li>
                                <li>Confirme seu perfil no sistema</li>
                            </ul>
                            
                            <div class="btn-group d-block">
                                <button class="btn btn-primary btn-sm" onclick="window.location.reload()">
                                    <i class="fas fa-sync me-1"></i>
                                    Recarregar Página
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
        <?php $headerComponent->renderJS(); ?>
        
        <script>
            AOS.init({
                duration: 800,
                once: true
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Continua apenas se tiver permissão...
// Inicializa classe de relatórios
$relatorios = new Relatorios();

// Busca estatísticas
try {
    $estatisticas = $relatorios->getEstatisticas(30);
    $modelosDisponiveis = $relatorios->listarModelos();
    $historicoRecente = $relatorios->getHistorico(['limite' => 5]);
} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    $estatisticas = $modelosDisponiveis = $historicoRecente = [];
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'relatorios',
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/relatorios.css">
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processando relatório...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header mb-4" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">Central de Relatórios</h1>
                        <p class="page-subtitle">
                            Departamento: <strong><?php echo $permissoesDept['nome']; ?></strong>
                            <?php if (!$permissoesDept['acesso_total']): ?>
                                <span class="badge bg-primary ms-2">Permissões Específicas</span>
                            <?php else: ?>
                                <span class="badge bg-success ms-2">Acesso Total</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <!-- BOTÃO DE FUNCIONÁRIOS - SOMENTE PARA PRESIDÊNCIA -->
                    <?php if ($isPresidencia): ?>
                    <div class="header-actions">
                        <a href="funcionarios.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-users me-2"></i> Funcionários
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $estatisticas['totais']['total'] ?? 0; ?></div>
                            <div class="stat-label">Relatórios Gerados</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo count($modelosDisponiveis); ?></div>
                            <div class="stat-label">Modelos Salvos</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $estatisticas['totais']['total_usuarios'] ?? 0; ?></div>
                            <div class="stat-label">Usuários Ativos</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">
                                <?php 
                                $mediaExecucoes = 0;
                                if (!empty($estatisticas['mais_utilizados'])) {
                                    $mediaExecucoes = array_sum(array_column($estatisticas['mais_utilizados'], 'total_execucoes')) / count($estatisticas['mais_utilizados']);
                                }
                                echo number_format($mediaExecucoes, 1);
                                ?>
                            </div>
                            <div class="stat-label">Média de Execuções</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Reports Section -->
            <div class="section-card" data-aos="fade-up" data-aos-delay="100">
                <div class="section-header">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        Relatórios Disponíveis
                    </h3>
                    <div class="section-actions">
                        <!-- BOTÃO CRIAR PERSONALIZADO - SOMENTE PRESIDÊNCIA -->
                        <?php if ($permissoesDept['criar_personalizado']): ?>
                        <button class="btn-modern btn-primary" onclick="abrirModalPersonalizado()">
                            <i class="fas fa-magic"></i>
                            Criar Relatório Personalizado
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="report-grid">
                    <!-- Relatório de Associados - Presidência e Comercial -->
                    <?php if (podeAcessarRelatorio('associados', $permissoesDept)): ?>
                    <div class="report-card" onclick="executarRelatorioRapido('associados')">
                        <div class="report-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4 class="report-title">Associados Ativos</h4>
                        <p class="report-description">
                            Lista completa de todos os associados ativos com informações básicas e contato.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('associados', 'hoje'); event.stopPropagation();">
                                <i class="fas fa-calendar-day"></i>
                                <span>Hoje</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('associados', 'mes'); event.stopPropagation();">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Este Mês</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('associados', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Relatório Financeiro - Presidência e Financeiro -->
                    <?php if (podeAcessarRelatorio('financeiro', $permissoesDept)): ?>
                    <div class="report-card" onclick="executarRelatorioRapido('financeiro')">
                        <div class="report-icon green">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4 class="report-title">Situação Financeira</h4>
                        <p class="report-description">
                            Análise da situação financeira dos associados e status de pagamentos.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('financeiro', 'adimplentes'); event.stopPropagation();">
                                <i class="fas fa-check"></i>
                                <span>Adimplentes</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('financeiro', 'inadimplentes'); event.stopPropagation();">
                                <i class="fas fa-times"></i>
                                <span>Inadimplentes</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('financeiro', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Relatório Militar - Presidência e Comercial -->
                    <?php if (podeAcessarRelatorio('militar', $permissoesDept)): ?>
                    <div class="report-card" onclick="executarRelatorioRapido('militar')">
                        <div class="report-icon orange">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4 class="report-title">Distribuição Militar</h4>
                        <p class="report-description">
                            Distribuição dos associados por patente, corporação e unidade militar.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('militar', 'patente'); event.stopPropagation();">
                                <i class="fas fa-star"></i>
                                <span>Por Patente</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('militar', 'corporacao'); event.stopPropagation();">
                                <i class="fas fa-building"></i>
                                <span>Por Corporação</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('militar', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Relatório de Aniversariantes - Presidência e Comercial -->
                    <?php if (podeAcessarRelatorio('aniversariantes', $permissoesDept)): ?>
                    <div class="report-card" onclick="executarRelatorioRapido('aniversariantes')">
                        <div class="report-icon pink">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <h4 class="report-title">Aniversariantes</h4>
                        <p class="report-description">
                            Lista de associados aniversariantes por período para envio de felicitações.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('aniversariantes', 'hoje'); event.stopPropagation();">
                                <i class="fas fa-calendar-day"></i>
                                <span>Hoje</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('aniversariantes', 'semana'); event.stopPropagation();">
                                <i class="fas fa-calendar-week"></i>
                                <span>Esta Semana</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('aniversariantes', 'mes'); event.stopPropagation();">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Este Mês</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('aniversariantes', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Estatísticas Financeiras - Presidência e Financeiro -->
                    <?php if (podeAcessarRelatorio('estatisticas', $permissoesDept)): ?>
                    <div class="report-card" onclick="window.location.href='estatisticas.php'">
                        <div class="report-icon blue">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h4 class="report-title">Estatísticas Financeiras</h4>
                        <p class="report-description">
                            Análise completa dos dados financeiros, relatórios de pagamentos e estatísticas da associação.
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Relatório de Serviços - Presidência e Financeiro -->
                    <?php if (podeAcessarRelatorio('servicos', $permissoesDept)): ?>
                    <div class="report-card" onclick="executarRelatorioRapido('servicos')">
                        <div class="report-icon purple">
                            <i class="fas fa-concierge-bell"></i>
                        </div>
                        <h4 class="report-title">Adesão aos Serviços</h4>
                        <p class="report-description">
                            Relatório de adesão aos serviços oferecidos e valores aplicados.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('servicos', 'ativos'); event.stopPropagation();">
                                <i class="fas fa-toggle-on"></i>
                                <span>Ativos</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('servicos', 'todos'); event.stopPropagation();">
                                <i class="fas fa-list"></i>
                                <span>Todos</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('servicos', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Relatório de Documentos - APENAS PRESIDÊNCIA E COMERCIAL -->
                    <?php if (podeAcessarRelatorio('documentos', $permissoesDept)): ?>
                    <div class="report-card" onclick="executarRelatorioRapido('documentos')">
                        <div class="report-icon red">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h4 class="report-title">Status de Documentos</h4>
                        <p class="report-description">
                            Controle de documentos enviados e status de verificação.
                        </p>
                        <div class="quick-report-actions">
                            <a class="quick-report-action" onclick="executarRelatorioRapido('documentos', 'pendentes'); event.stopPropagation();">
                                <i class="fas fa-clock"></i>
                                <span>Pendentes</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('documentos', 'verificados'); event.stopPropagation();">
                                <i class="fas fa-check-circle"></i>
                                <span>Verificados</span>
                            </a>
                            <a class="quick-report-action" onclick="executarRelatorioRapido('documentos', 'personalizar'); event.stopPropagation();">
                                <i class="fas fa-sliders-h"></i>
                                <span>Filtrar</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Aviso sobre permissões específicas do departamento -->
                <?php if (!$permissoesDept['acesso_total']): ?>
                <div class="alert alert-info mt-3" data-aos="fade-up">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Acesso por Departamento:</strong> Você está vendo os relatórios disponíveis para o departamento 
                    <strong><?php echo $permissoesDept['nome']; ?></strong>. 
                    Diferentes departamentos têm acesso a diferentes tipos de relatórios.
                </div>
                <?php endif; ?>
            </div>

            <!-- Saved Models Section - Somente se houver modelos E permissão para criar -->
            <?php if (!empty($modelosDisponiveis) && $permissoesDept['criar_personalizado']): ?>
            <div class="section-card" data-aos="fade-up" data-aos-delay="150">
                <div class="section-header">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-save"></i>
                        </div>
                        Modelos Salvos
                    </h3>
                </div>

                <div class="report-grid">
                    <?php foreach ($modelosDisponiveis as $modelo): ?>
                    <div class="report-card" onclick="executarModelo(<?php echo $modelo['id']; ?>)">
                        <div class="report-icon info">
                            <i class="fas fa-file-code"></i>
                        </div>
                        <h4 class="report-title"><?php echo htmlspecialchars($modelo['nome']); ?></h4>
                        <p class="report-description">
                            <?php echo htmlspecialchars($modelo['descricao'] ?? 'Modelo personalizado de relatório'); ?>
                        </p>
                        <div class="report-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($modelo['criado_por_nome'] ?? 'Sistema'); ?></span>
                            <span><?php echo $modelo['total_execucoes'] ?? 0; ?> execuções</span>
                        </div>
                        <div class="model-actions">
                            <a class="model-action primary" onclick="executarModelo(<?php echo $modelo['id']; ?>); event.stopPropagation();">
                                <i class="fas fa-play"></i>
                                <span>Executar</span>
                            </a>
                            <a class="model-action" onclick="editarModelo(<?php echo $modelo['id']; ?>); event.stopPropagation();">
                                <i class="fas fa-edit"></i>
                                <span>Editar</span>
                            </a>
                            <a class="model-action" onclick="duplicarModelo(<?php echo $modelo['id']; ?>); event.stopPropagation();">
                                <i class="fas fa-copy"></i>
                                <span>Duplicar</span>
                            </a>
                            <?php if ($auth->isDiretor()): ?>
                            <a class="model-action danger" onclick="excluirModelo(<?php echo $modelo['id']; ?>, '<?php echo htmlspecialchars($modelo['nome'], ENT_QUOTES); ?>'); event.stopPropagation();">
                                <i class="fas fa-trash"></i>
                                <span>Excluir</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="section-card" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h3 class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        Atividade Recente
                    </h3>
                </div>

                <div class="activity-list">
                    <?php if (empty($historicoRecente)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Nenhuma atividade recente</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($historicoRecente as $item): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($item['nome_relatorio']); ?></div>
                                <div class="activity-description">
                                    Gerado por <?php echo htmlspecialchars($item['gerado_por_nome'] ?? 'Sistema'); ?> • 
                                    <?php echo $item['contagem_registros'] ?? 0; ?> registros
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php 
                                $data = new DateTime($item['data_geracao']);
                                echo $data->format('d/m/Y H:i');
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Filtros Rápidos -->
    <div class="modal-custom" id="modalFiltrosRapidos">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom" id="modalFiltrosTitle">Filtrar Relatório</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalFiltrosRapidos')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formFiltrosRapidos">
                    <input type="hidden" id="tipoRelatorioRapido" name="tipo">
                    
                    <!-- Filtros de Data -->
                    <div class="form-section">
                        <h3 class="form-section-title">Período</h3>
                        <div class="date-range-simple">
                            <input type="date" class="form-control-custom" id="dataInicioRapido" name="data_inicio">
                            <span style="color: var(--gray-500);">até</span>
                            <input type="date" class="form-control-custom" id="dataFimRapido" name="data_fim">
                        </div>
                    </div>

                    <!-- Filtros Específicos serão carregados aqui -->
                    <div id="filtrosEspecificosRapidos"></div>

                    <!-- Botões -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalFiltrosRapidos')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-filter"></i>
                            Aplicar Filtros
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Relatório Personalizado - SOMENTE PRESIDÊNCIA -->
    <?php if ($permissoesDept['criar_personalizado']): ?>
    <div class="modal-custom" id="modalPersonalizado">
        <div class="modal-content-custom large">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Criar Relatório Personalizado</h2>
                <button class="modal-close-custom" onclick="fecharModal('modalPersonalizado')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formPersonalizado">
                    <!-- Informações Básicas -->
                    <div class="form-section">
                        <h3 class="form-section-title">Informações do Relatório</h3>
                        <div class="form-group">
                            <label class="form-label">Nome do Relatório</label>
                            <input type="text" class="form-control-custom" id="nomeRelatorio" name="nome" required placeholder="Ex: Relatório Mensal de Associados">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tipo de Dados</label>
                            <select class="form-control-custom form-select-custom" id="tipoRelatorio" name="tipo" required>
                                <option value="">Selecione o tipo</option>
                                <option value="associados">Associados</option>
                                <option value="aniversariantes">🎂 Aniversariantes</option>
                                <option value="financeiro">Financeiro</option>
                                <option value="militar">Militar</option>
                                <option value="servicos">Serviços</option>
                                <option value="documentos">Documentos</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descrição (opcional)</label>
                            <textarea class="form-control-custom" id="descricaoRelatorio" name="descricao" rows="2" placeholder="Descreva o objetivo deste relatório"></textarea>
                        </div>
                    </div>

                    <!-- Seleção de Campos -->
                    <div class="form-section" id="secaoCampos" style="display: none;">
                        <h3 class="form-section-title">Campos do Relatório</h3>
                        
                        <!-- Tabs para alternar entre seleção e ordenação -->
                        <div class="campos-tabs">
                            <button type="button" class="campos-tab active" onclick="alternarTabCampos('selecao', event)">
                                <i class="fas fa-check-square"></i> Selecionar Campos
                            </button>
                            <button type="button" class="campos-tab" onclick="alternarTabCampos('ordem', event)">
                                <i class="fas fa-sort"></i> Ordenar Campos
                            </button>
                        </div>

                        <!-- Tab de Seleção -->
                        <div class="campos-tab-content active" id="tabSelecao">
                            <div class="quick-filters">
                                <div class="quick-filters-title">Ações rápidas:</div>
                                <div class="filter-pills">
                                    <span class="filter-pill" onclick="selecionarTodosCampos()">
                                        <i class="fas fa-check-square"></i> Selecionar Todos
                                    </span>
                                    <span class="filter-pill" onclick="limparTodosCampos()">
                                        <i class="fas fa-square"></i> Limpar Todos
                                    </span>
                                    <span class="filter-pill" onclick="selecionarCamposBasicos()">
                                        <i class="fas fa-star"></i> Campos Básicos
                                    </span>
                                </div>
                            </div>
                            <div class="checkbox-group" id="camposPersonalizados">
                                <!-- Campos serão carregados dinamicamente -->
                            </div>
                        </div>

                        <!-- Tab de Ordenação -->
                        <div class="campos-tab-content" id="tabOrdem">
                            <div class="campos-selecionados-container">
                                <div class="campos-selecionados-header">
                                    <div class="campos-selecionados-title">
                                        <i class="fas fa-grip-vertical"></i> Arraste para reordenar
                                    </div>
                                    <div class="campos-ordem-info">
                                        Os campos aparecerão no relatório nesta ordem
                                    </div>
                                </div>
                                <ul class="campos-selecionados-list" id="camposSelecionadosList">
                                    <!-- Campos selecionados aparecerão aqui -->
                                </ul>
                                <div class="campos-selecionados-empty" id="camposSelecionadosEmpty">
                                    <i class="fas fa-inbox"></i>
                                    <p>Nenhum campo selecionado</p>
                                    <p class="text-muted small">Selecione campos na aba anterior</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros e Ordenação -->
                    <div class="form-section" id="secaoFiltros" style="display: none;">
                        <h3 class="form-section-title">Filtros e Ordenação</h3>
                        <div id="filtrosPersonalizados">
                            <!-- Filtros serão carregados dinamicamente -->
                        </div>
                        
                        <div class="form-group mt-3">
                            <label class="form-label">Ordenar por</label>
                            <select class="form-control-custom form-select-custom" name="ordenacao" id="selectOrdenacao">
                                <option value="">Padrão</option>
                                <option value="nome_asc">Nome (A-Z)</option>
                                <option value="nome_desc">Nome (Z-A)</option>
                                <option value="id_desc">Mais recentes</option>
                                <option value="id_asc">Mais antigos</option>
                            </select>
                        </div>
                    </div>

                    <!-- Opções de Salvamento -->
                    <div class="form-section">
                        <label class="checkbox-item">
                            <input type="checkbox" class="checkbox-custom" id="salvarModelo" name="salvar_modelo" checked>
                            <span class="checkbox-label">Salvar como modelo para uso futuro</span>
                        </label>
                    </div>

                    <!-- Botões -->
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModal('modalPersonalizado')">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-file-export"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <!-- JavaScript customizado para os botões do header -->
    <script>
        function toggleSearch() {
            console.log('Busca global ativada');
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        function toggleNotifications() {
            console.log('Painel de notificações');
            alert('Painel de notificações em desenvolvimento');
        }
    </script>

    <script>
        // Configuração inicial
        console.log('=== INICIANDO SISTEMA RELATÓRIOS ===');
        console.log('jQuery versão:', jQuery.fn.jquery);

        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Variáveis globais com permissões
        let permissoesDepartamento = <?php echo json_encode($permissoesDept); ?>;
        let isPresidencia = <?php echo $isPresidencia ? 'true' : 'false'; ?>;
        let temPermissao = <?php echo json_encode($temPermissaoRelatorios); ?>;
        let isDiretor = <?php echo $auth->isDiretor() ? 'true' : 'false'; ?>;
        
        let tipoRelatorioAtual = '';
        let camposDisponiveis = {};
        let camposOrdenados = [];
        let camposBasicos = {
            'associados': ['nome', 'cpf', 'telefone', 'email', 'situacao'],
            'aniversariantes': ['nome', 'nasc', 'telefone', 'email', 'idade'],
            'financeiro': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira'],
            'militar': ['nome', 'cpf', 'corporacao', 'patente'],
            'servicos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'ativo'],
            'documentos': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'verificado']
        };

        // Debug das permissões
        console.log('=== PERMISSÕES DEPARTAMENTO ===');
        console.log('Departamento:', permissoesDepartamento?.nome || 'Não definido');
        console.log('Acesso total:', permissoesDepartamento?.acesso_total || false);
        console.log('Pode criar personalizado:', permissoesDepartamento?.criar_personalizado || false);
        console.log('Relatórios permitidos:', permissoesDepartamento?.relatorios_permitidos || []);
        console.log('Tem permissão geral:', temPermissao);
        console.log('===============================');

        // Função para verificar se pode acessar um relatório
        function podeAcessarRelatorio(tipo) {
            if (!permissoesDepartamento) return false;
            if (permissoesDepartamento.acesso_total) return true;
            return permissoesDepartamento.relatorios_permitidos.includes(tipo);
        }

        // FUNÇÃO ROBUSTA PARA INICIALIZAR DROPDOWN DO USUÁRIO
        function initializeUserDropdown() {
            console.log('🎯 Inicializando dropdown do usuário...');
            
            const menuSelectors = [
                '#userMenu',
                '.user-menu-btn',
                '[data-user-menu]',
                '.user-profile-btn',
                '.user-avatar'
            ];
            
            const dropdownSelectors = [
                '#userDropdown',
                '.user-dropdown',
                '[data-user-dropdown]',
                '.user-menu-dropdown'
            ];
            
            let userMenu = null;
            let userDropdown = null;
            
            for (const selector of menuSelectors) {
                userMenu = document.querySelector(selector);
                if (userMenu) {
                    console.log('✅ Menu encontrado com seletor:', selector);
                    break;
                }
            }
            
            for (const selector of dropdownSelectors) {
                userDropdown = document.querySelector(selector);
                if (userDropdown) {
                    console.log('✅ Dropdown encontrado com seletor:', selector);
                    break;
                }
            }
            
            if (userMenu && userDropdown) {
                userMenu.removeEventListener('click', handleUserMenuClick);
                document.removeEventListener('click', handleDocumentClick);
                
                userMenu.addEventListener('click', handleUserMenuClick);
                document.addEventListener('click', handleDocumentClick);
                
                console.log('✅ User dropdown inicializado com sucesso!');
                
                function handleUserMenuClick(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isVisible = userDropdown.classList.contains('show');
                    
                    document.querySelectorAll('.user-dropdown.show').forEach(dropdown => {
                        if (dropdown !== userDropdown) {
                            dropdown.classList.remove('show');
                        }
                    });
                    
                    userDropdown.classList.toggle('show', !isVisible);
                    console.log('Dropdown toggled:', !isVisible);
                }
                
                function handleDocumentClick(e) {
                    if (!userMenu.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('show');
                    }
                }
                
            } else {
                console.warn('⚠️ Elementos do dropdown não encontrados');
            }
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM carregado, inicializando componentes...');
            
            initializeUserDropdown();
            setTimeout(initializeUserDropdown, 500);
            setTimeout(initializeUserDropdown, 1000);
            setTimeout(initializeUserDropdown, 2000);

            // Event listeners dos formulários
            const formFiltrosRapidos = document.getElementById('formFiltrosRapidos');
            const formPersonalizado = document.getElementById('formPersonalizado');
            
            if (formFiltrosRapidos) {
                formFiltrosRapidos.addEventListener('submit', aplicarFiltrosRapidos);
            }
            
            if (formPersonalizado) {
                formPersonalizado.addEventListener('submit', gerarRelatorioPersonalizado);
            }
            
            // Mudança de tipo no relatório personalizado
            const tipoRelatorio = document.getElementById('tipoRelatorio');
            if (tipoRelatorio) {
                tipoRelatorio.addEventListener('change', function() {
                    if (this.value) {
                        document.getElementById('secaoCampos').style.display = 'block';
                        document.getElementById('secaoFiltros').style.display = 'block';
                        
                        if (this.value !== tipoRelatorioAtual) {
                            camposOrdenados = [];
                        }
                        
                        tipoRelatorioAtual = this.value;
                        carregarCamposPersonalizados(this.value);
                        carregarFiltrosPersonalizados(this.value);
                        atualizarOpcoesOrdenacao(this.value);
                        alternarTabCampos('selecao', null);
                    } else {
                        document.getElementById('secaoCampos').style.display = 'none';
                        document.getElementById('secaoFiltros').style.display = 'none';
                    }
                });
            }
            
            // Event listener para mudanças nos checkboxes
            document.addEventListener('change', function(e) {
                if (e.target.matches('#camposPersonalizados input[type="checkbox"]')) {
                    atualizarCamposSelecionados();
                }
            });

            console.log('✅ Sistema de Relatórios iniciado com permissões!');
        });

        // Loading functions
        function showLoading(texto = 'Processando...') {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                const loadingText = overlay.querySelector('.loading-text');
                if (loadingText) {
                    loadingText.textContent = texto;
                }
                overlay.classList.add('active');
            }
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }

        // Executa relatório rápido COM VERIFICAÇÃO DE PERMISSÃO
        function executarRelatorioRapido(tipo, preset = null) {
            // Verifica permissão antes de executar
            if (!podeAcessarRelatorio(tipo)) {
                alert(`Seu departamento (${permissoesDepartamento?.nome || 'Não identificado'}) não tem acesso ao relatório de ${tipo}.`);
                return;
            }
            
            if (preset === 'personalizar') {
                abrirModalFiltrosRapidos(tipo);
                return;
            }

            showLoading('Gerando relatório...');

            const dados = {
                tipo: tipo,
                campos: getCamposPreset(tipo, preset),
                formato: 'html'
            };

            const filtros = getFiltrosPreset(tipo, preset);
            Object.assign(dados, filtros);

            executarRelatorio(dados);
        }

        // Retorna campos predefinidos para relatórios rápidos
        function getCamposPreset(tipo, preset) {
            const presets = {
                'associados': {
                    'default': ['nome', 'cpf', 'telefone', 'email', 'situacao', 'corporacao', 'patente'],
                    'hoje': ['nome', 'cpf', 'telefone', 'email', 'dataFiliacao'],
                    'mes': ['nome', 'cpf', 'telefone', 'email', 'situacao', 'dataFiliacao']
                },
                'financeiro': {
                    'default': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira', 'localDebito'],
                    'adimplentes': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira'],
                    'inadimplentes': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira', 'telefone', 'email']
                },
                'militar': {
                    'default': ['nome', 'cpf', 'corporacao', 'patente', 'categoria', 'unidade'],
                    'patente': ['patente', 'nome', 'cpf', 'corporacao', 'unidade'],
                    'corporacao': ['corporacao', 'nome', 'cpf', 'patente', 'unidade']
                },
                'servicos': {
                    'default': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao', 'ativo'],
                    'ativos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao'],
                    'todos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao', 'ativo']
                },
                'documentos': {
                    'default': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'verificado'],
                    'pendentes': ['nome', 'cpf', 'tipo_documento', 'data_upload'],
                    'verificados': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'funcionario_nome']
                }
            };

            return presets[tipo]?.[preset] || presets[tipo]?.['default'] || [];
        }

        // Retorna filtros predefinidos para relatórios rápidos
       function getFiltrosPreset(tipo, preset) {
    const hoje = new Date().toISOString().split('T')[0];
    const inicioMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    
    // Calcular data de uma semana à frente
    const umaSemana = new Date();
    umaSemana.setDate(umaSemana.getDate() + 7);
    const fimSemana = umaSemana.toISOString().split('T')[0];
    
    // Calcular fim do mês
    const fimMes = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0];
    
    const filtros = {
        'associados': {
            'hoje': { data_inicio: hoje, data_fim: hoje },
            'mes': { data_inicio: inicioMes, data_fim: hoje }
        },
        'aniversariantes': {
    'default': { periodo_aniversario: 'hoje' },
    'hoje': { periodo_aniversario: 'hoje' },
    'semana': { periodo_aniversario: 'semana' },  
    'mes': { periodo_aniversario: 'mes' }
},
        'financeiro': {
            'adimplentes': { situacaoFinanceira: 'Regular' },
            'inadimplentes': { situacaoFinanceira: 'Inadimplente' }
        },
        'militar': {
            'patente': { ordenacao: 'patente ASC, nome ASC' },
            'corporacao': { ordenacao: 'corporacao ASC, patente ASC, nome ASC' }
        },
        'servicos': {
            'ativos': { ativo: '1' },
            'todos': {}
        },
        'documentos': {
            'pendentes': { verificado: '0' },
            'verificados': { verificado: '1' }
        }
    };

    return filtros[tipo]?.[preset] || {};
}

        // Abre modal de filtros rápidos COM VERIFICAÇÃO DE PERMISSÃO
        function abrirModalFiltrosRapidos(tipo) {
            if (!podeAcessarRelatorio(tipo)) {
                alert(`Seu departamento (${permissoesDepartamento?.nome || 'Não identificado'}) não tem acesso ao relatório de ${tipo}.`);
                return;
            }
            
            tipoRelatorioAtual = tipo;
            document.getElementById('tipoRelatorioRapido').value = tipo;
            
            const titulos = {
                'associados': 'Filtrar Relatório de Associados',
                'financeiro': 'Filtrar Relatório Financeiro',
                'militar': 'Filtrar Relatório Militar',
                'servicos': 'Filtrar Relatório de Serviços',
                'documentos': 'Filtrar Relatório de Documentos'
            };
            document.getElementById('modalFiltrosTitle').textContent = titulos[tipo] || 'Filtrar Relatório';
            
            carregarFiltrosRapidos(tipo);
            document.getElementById('modalFiltrosRapidos').classList.add('show');
        }

        // Carrega filtros específicos para modal rápido
       function carregarFiltrosRapidos(tipo) {
    const container = document.getElementById('filtrosEspecificosRapidos');
    let html = '<div class="form-section"><h3 class="form-section-title">Filtros Específicos</h3>';
    
    switch(tipo) {
        case 'associados':
            html += `
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select class="form-control-custom form-select-custom" name="situacao">
                        <option value="">Todos</option>
                        <option value="Filiado">Filiado</option>
                        <option value="Desfiliado">Desfiliado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control-custom" name="busca" placeholder="Nome, CPF ou RG">
                </div>
            `;
            break;
            
        case 'aniversariantes':
            html += `
                <div class="form-group">
                    <label class="form-label">Período dos Aniversários</label>
                    <select class="form-control-custom form-select-custom" name="periodo_aniversario">
                        <option value="hoje">Apenas hoje</option>
                        <option value="semana">Próximos 7 dias</option>
                        <option value="mes">Este mês</option>
                        <option value="customizado">Período personalizado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Corporação</label>
                    <select class="form-control-custom form-select-custom" name="corporacao">
                        <option value="">Todas</option>
                        <option value="PM">Polícia Militar</option>
                        <option value="CBM">Corpo de Bombeiros</option>
                        <option value="PC">Polícia Civil</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Incluir idade na listagem</label>
                    <select class="form-control-custom form-select-custom" name="incluir_idade">
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
            `;
            break;
            
        case 'financeiro':
            html += `
                <div class="form-group">
                    <label class="form-label">Situação Financeira</label>
                    <select class="form-control-custom form-select-custom" name="situacaoFinanceira">
                        <option value="">Todas</option>
                        <option value="Regular">Regular</option>
                        <option value="Inadimplente">Inadimplente</option>
                    </select>
                </div>
            `;
            break;
            
        case 'militar':
            html += `
                <div class="form-group">
                    <label class="form-label">Corporação</label>
                    <select class="form-control-custom form-select-custom" name="corporacao">
                        <option value="">Todas</option>
                        <option value="PM">Polícia Militar</option>
                        <option value="CBM">Corpo de Bombeiros</option>
                        <option value="PC">Polícia Civil</option>
                    </select>
                </div>
            `;
            break;
            
        case 'servicos':
            html += `
                <div class="form-group">
                    <label class="form-label">Status do Serviço</label>
                    <select class="form-control-custom form-select-custom" name="ativo">
                        <option value="">Todos</option>
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </select>
                </div>
            `;
            break;
            
        case 'documentos':
            html += `
                <div class="form-group">
                    <label class="form-label">Status de Verificação</label>
                    <select class="form-control-custom form-select-custom" name="verificado">
                        <option value="">Todos</option>
                        <option value="1">Verificado</option>
                        <option value="0">Não Verificado</option>
                    </select>
                </div>
            `;
            break;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

        // Aplica filtros rápidos
        function aplicarFiltrosRapidos(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {
                tipo: formData.get('tipo'),
                campos: getCamposPreset(formData.get('tipo'), 'default'),
                formato: 'html'
            };
            
            for (let [key, value] of formData.entries()) {
                if (key !== 'tipo' && value) {
                    dados[key] = value;
                }
            }
            
            showLoading('Gerando relatório...');
            executarRelatorio(dados);
            fecharModal('modalFiltrosRapidos');
        }

        // Abre modal de relatório personalizado COM VERIFICAÇÃO DE PERMISSÃO
        function abrirModalPersonalizado() {
            if (!permissoesDepartamento?.criar_personalizado) {
                alert('Apenas a Presidência pode criar relatórios personalizados.');
                return;
            }
            
            const formPersonalizado = document.getElementById('formPersonalizado');
            if (!formPersonalizado.getAttribute('data-modelo-id')) {
                camposOrdenados = [];
                tipoRelatorioAtual = '';
            }
            document.getElementById('modalPersonalizado').classList.add('show');
        }

        // Carrega campos para relatório personalizado
        function carregarCamposPersonalizados(tipo) {
            showLoading('Carregando campos...');
            
            $.ajax({
                url: '../api/relatorios_campos.php',
                method: 'GET',
                data: { tipo: tipo },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        camposDisponiveis = response.campos;
                        renderizarCamposPersonalizados(response.campos);
                    } else {
                        alert('Erro ao carregar campos: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro ao carregar campos:', error);
                    
                    const camposPadrao = getCamposPadrao(tipo);
                    renderizarCamposPersonalizados(camposPadrao);
                }
            });
        }

        // Renderiza campos no modal personalizado
        function renderizarCamposPersonalizados(campos) {
            const container = document.getElementById('camposPersonalizados');
            container.innerHTML = '';
            
            let camposOrganizados = reorganizarCamposPorOrdem(campos);
            
            for (const categoria in camposOrganizados) {
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'w-100';
                categoryDiv.innerHTML = `<div class="category-header">${categoria}</div>`;
                container.appendChild(categoryDiv);
                
                camposOrganizados[categoria].forEach(campo => {
                    const checkboxItem = document.createElement('div');
                    checkboxItem.className = 'checkbox-item';
                    
                    const isBasico = camposBasicos[tipoRelatorioAtual]?.includes(campo.nome_campo);
                    const isSelecionado = camposOrdenados.includes(campo.nome_campo);
                    
                    checkboxItem.innerHTML = `
                        <input type="checkbox" 
                               class="checkbox-custom" 
                               id="campo_personalizado_${campo.nome_campo}" 
                               name="campos[]" 
                               value="${campo.nome_campo}"
                               ${(isBasico || isSelecionado) ? 'checked' : ''}>
                        <label class="checkbox-label" for="campo_personalizado_${campo.nome_campo}">
                            ${campo.nome_exibicao}
                        </label>
                    `;
                    container.appendChild(checkboxItem);
                });
            }
            
            if (camposOrdenados.length > 0) {
                setTimeout(() => {
                    atualizarCamposSelecionados();
                }, 100);
            }
        }

        // Reorganiza campos respeitando a ordem salva
        function reorganizarCamposPorOrdem(campos) {
            if (camposOrdenados.length === 0) {
                return campos;
            }
            
            let mapaCampos = {};
            for (const categoria in campos) {
                campos[categoria].forEach(campo => {
                    mapaCampos[campo.nome_campo] = {
                        ...campo,
                        categoria: categoria
                    };
                });
            }
            
            let camposReorganizados = {};
            
            camposOrdenados.forEach(nomeCampo => {
                if (mapaCampos[nomeCampo]) {
                    const campo = mapaCampos[nomeCampo];
                    const categoria = campo.categoria;
                    
                    if (!camposReorganizados[categoria]) {
                        camposReorganizados[categoria] = [];
                    }
                    
                    if (!camposReorganizados[categoria].find(c => c.nome_campo === nomeCampo)) {
                        camposReorganizados[categoria].push({
                            nome_campo: campo.nome_campo,
                            nome_exibicao: campo.nome_exibicao,
                            tipo_dado: campo.tipo_dado
                        });
                    }
                }
            });
            
            for (const categoria in campos) {
                campos[categoria].forEach(campo => {
                    if (!camposOrdenados.includes(campo.nome_campo)) {
                        if (!camposReorganizados[categoria]) {
                            camposReorganizados[categoria] = [];
                        }
                        camposReorganizados[categoria].push(campo);
                    }
                });
            }
            
            return camposReorganizados;
        }

        // Carrega filtros para relatório personalizado
        // Atualizar a função carregarFiltrosPersonalizados no relatorios.php
function carregarFiltrosPersonalizados(tipo) {
    const container = document.getElementById('filtrosPersonalizados');
    let html = '';
    
    html += `
        <div class="date-range-simple mb-3">
            <input type="date" class="form-control-custom" name="data_inicio" placeholder="Data inicial">
            <span style="color: var(--gray-500);">até</span>
            <input type="date" class="form-control-custom" name="data_fim" placeholder="Data final">
        </div>
    `;
    
    switch(tipo) {
        case 'associados':
            html += `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Situação</label>
                            <select class="form-control-custom form-select-custom" name="situacao">
                                <option value="">Todos</option>
                                <option value="Filiado">Filiado</option>
                                <option value="Desfiliado">Desfiliado</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control-custom" name="busca" placeholder="Nome, CPF ou RG">
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'aniversariantes':
            html += `
                <div class="form-group">
                    <label class="form-label">Período dos Aniversários</label>
                    <select class="form-control-custom form-select-custom" name="periodo_aniversario" onchange="toggleCustomDateRange(this.value)">
                        <option value="hoje">Apenas hoje</option>
                        <option value="semana">Próximos 7 dias</option>
                        <option value="mes">Este mês</option>
                        <option value="customizado">Período personalizado (usar datas acima)</option>
                    </select>
                    <div class="form-text">
                        <small><strong>Dica:</strong> Para período personalizado, use as datas de início e fim acima.</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Corporação</label>
                            <select class="form-control-custom form-select-custom" name="corporacao">
                                <option value="">Todas</option>
                                <option value="PM">Polícia Militar</option>
                                <option value="CBM">Corpo de Bombeiros</option>
                                <option value="PC">Polícia Civil</option>
                                <option value="PRF">Polícia Rodoviária Federal</option>
                                <option value="PF">Polícia Federal</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Sexo</label>
                            <select class="form-control-custom form-select-custom" name="sexo">
                                <option value="">Todos</option>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Idade Mínima</label>
                            <input type="number" class="form-control-custom" name="idade_min" min="18" max="100" placeholder="Ex: 30">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Idade Máxima</label>
                            <input type="number" class="form-control-custom" name="idade_max" min="18" max="100" placeholder="Ex: 65">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select class="form-control-custom form-select-custom" name="situacao">
                        <option value="Filiado">Apenas Filiados</option>
                        <option value="">Todos (incluir desfiliados)</option>
                        <option value="Desfiliado">Apenas Desfiliados</option>
                    </select>
                </div>
            `;
            break;
            
        case 'financeiro':
            html += `
                <div class="form-group">
                    <label class="form-label">Situação Financeira</label>
                    <select class="form-control-custom form-select-custom" name="situacaoFinanceira">
                        <option value="">Todas</option>
                        <option value="Regular">Regular</option>
                        <option value="Inadimplente">Inadimplente</option>
                    </select>
                </div>
            `;
            break;
            
        case 'militar':
            html += `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Corporação</label>
                            <select class="form-control-custom form-select-custom" name="corporacao">
                                <option value="">Todas</option>
                                <option value="PM">Polícia Militar</option>
                                <option value="CBM">Corpo de Bombeiros</option>
                                <option value="PC">Polícia Civil</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Patente</label>
                            <input type="text" class="form-control-custom" name="patente" placeholder="Ex: Coronel">
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'servicos':
            html += `
                <div class="form-group">
                    <label class="form-label">Status do Serviço</label>
                    <select class="form-control-custom form-select-custom" name="ativo">
                        <option value="">Todos</option>
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </select>
                </div>
            `;
            break;
            
        case 'documentos':
            html += `
                <div class="form-group">
                    <label class="form-label">Status de Verificação</label>
                    <select class="form-control-custom form-select-custom" name="verificado">
                        <option value="">Todos</option>
                        <option value="1">Verificado</option>
                        <option value="0">Não Verificado</option>
                    </select>
                </div>
            `;
            break;
    }
    
    container.innerHTML = html;
}

function atualizarOpcoesOrdenacao(tipo) {
    const selectOrdenacao = document.getElementById('selectOrdenacao');
    if (!selectOrdenacao) return;
    
    // Opções base (sempre disponíveis)
    let opcoes = [
        { value: '', text: 'Padrão' },
        { value: 'nome_asc', text: 'Nome (A-Z)' },
        { value: 'nome_desc', text: 'Nome (Z-A)' }
    ];
    
    // Opções específicas por tipo
    switch(tipo) {
        case 'aniversariantes':
            opcoes = [
                { value: '', text: 'Padrão (proximidade do aniversário)' },
                { value: 'aniversario_asc', text: 'Data do aniversário (janeiro → dezembro)' },
                { value: 'dias_ate_aniversario', text: 'Dias até o aniversário (mais próximos primeiro)' },
                { value: 'idade_desc', text: 'Idade (mais velho → mais novo)' },
                { value: 'idade_asc', text: 'Idade (mais novo → mais velho)' },
                { value: 'nome_asc', text: 'Nome (A-Z)' },
                { value: 'nome_desc', text: 'Nome (Z-A)' },
                { value: 'corporacao_asc', text: 'Corporação e Patente' }
            ];
            break;
            
        case 'associados':
            opcoes.push(
                { value: 'id_desc', text: 'Mais recentes' },
                { value: 'id_asc', text: 'Mais antigos' },
                { value: 'corporacao_asc', text: 'Corporação' },
                { value: 'patente_asc', text: 'Patente' }
            );
            break;
            
        case 'financeiro':
            opcoes.push(
                { value: 'situacao_financeira', text: 'Situação Financeira' },
                { value: 'tipo_associado', text: 'Tipo de Associado' }
            );
            break;
            
        case 'militar':
            opcoes.push(
                { value: 'corporacao_asc', text: 'Corporação' },
                { value: 'patente_asc', text: 'Patente' },
                { value: 'unidade_asc', text: 'Unidade' }
            );
            break;
            
        case 'servicos':
            opcoes.push(
                { value: 'valor_desc', text: 'Maior valor' },
                { value: 'valor_asc', text: 'Menor valor' },
                { value: 'data_adesao_desc', text: 'Adesão mais recente' },
                { value: 'data_adesao_asc', text: 'Adesão mais antiga' }
            );
            break;
            
        case 'documentos':
            opcoes.push(
                { value: 'data_upload_desc', text: 'Upload mais recente' },
                { value: 'data_upload_asc', text: 'Upload mais antigo' },
                { value: 'tipo_documento', text: 'Tipo de documento' }
            );
            break;
    }
    
    // Limpar e recriar as opções
    selectOrdenacao.innerHTML = '';
    opcoes.forEach(opcao => {
        const option = document.createElement('option');
        option.value = opcao.value;
        option.textContent = opcao.text;
        selectOrdenacao.appendChild(option);
    });
}

// Função auxiliar para mostrar/ocultar campos de data quando for período customizado
function toggleCustomDateRange(periodo) {
    const dateRange = document.querySelector('.date-range-simple');
    const dateInputs = document.querySelectorAll('.date-range-simple input[type="date"]');
    
    if (periodo === 'customizado') {
        if (dateRange) {
            dateRange.style.border = '2px solid #3b82f6';
            dateRange.style.background = '#eff6ff';
        }
        dateInputs.forEach(input => {
            input.required = true;
            input.style.borderColor = '#3b82f6';
        });
    } else {
        if (dateRange) {
            dateRange.style.border = '1px solid #e5e7eb';
            dateRange.style.background = '#f9fafb';
        }
        dateInputs.forEach(input => {
            input.required = false;
            input.style.borderColor = '#e5e7eb';
            input.value = ''; // Limpa os valores
        });
    }
}



// Também atualizar a opção de aniversariantes no select do tipo de relatório personalizado
// No modal personalizado, onde tem:
// <option value="documentos">Documentos</option>
// Adicionar:
// <option value="aniversariantes">Aniversariantes</option>

        // Funções de seleção de campos
        function selecionarTodosCampos() {
            document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
            });
            atualizarCamposSelecionados();
        }

        function limparTodosCampos() {
            document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            atualizarCamposSelecionados();
        }

        function selecionarCamposBasicos() {
            const tipo = document.getElementById('tipoRelatorio').value;
            const basicos = camposBasicos[tipo] || [];
            
            document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                cb.checked = basicos.includes(cb.value);
            });
            atualizarCamposSelecionados();
        }

        // Gera relatório personalizado
        function gerarRelatorioPersonalizado(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {};
            
            for (let [key, value] of formData.entries()) {
                if (key === 'campos[]') {
                    // Ignora campos[] do FormData, usaremos camposOrdenados
                } else {
                    dados[key] = value;
                }
            }
            
            if (camposOrdenados.length > 0) {
                dados.campos = camposOrdenados;
            } else {
                dados.campos = [];
                document.querySelectorAll('#camposPersonalizados input[type="checkbox"]:checked').forEach(cb => {
                    dados.campos.push(cb.value);
                });
            }
            
            if (!dados.campos || dados.campos.length === 0) {
                alert('Selecione ao menos um campo para o relatório');
                return;
            }
            
            const modeloIdEditando = document.getElementById('formPersonalizado').getAttribute('data-modelo-id');
            if (modeloIdEditando) {
                dados.id = modeloIdEditando;
            }
            
            dados.formato = 'html';
            
            showLoading('Gerando relatório...');
            
            if (dados.salvar_modelo) {
                salvarModelo(dados).then(modeloId => {
                    executarRelatorio(dados);
                    fecharModal('modalPersonalizado');
                    if (modeloIdEditando) {
                        setTimeout(() => location.reload(), 1000);
                    }
                }).catch(error => {
                    hideLoading();
                    alert('Erro ao salvar modelo: ' + error);
                });
            } else {
                executarRelatorio(dados);
                fecharModal('modalPersonalizado');
            }
        }

        // Salva modelo de relatório
        function salvarModelo(dados) {
            return new Promise((resolve, reject) => {
                const modeloData = {
                    nome: dados.nome,
                    descricao: dados.descricao || '',
                    tipo: dados.tipo,
                    campos: dados.campos,
                    filtros: {}
                };
                
                if (dados.id) {
                    modeloData.id = dados.id;
                }
                
                const filtrosPossiveis = ['data_inicio', 'data_fim', 'situacao', 'corporacao', 
                                         'patente', 'situacaoFinanceira', 'ativo', 'verificado', 'busca'];
                
                filtrosPossiveis.forEach(filtro => {
                    if (dados[filtro] && dados[filtro] !== '') {
                        modeloData.filtros[filtro] = dados[filtro];
                    }
                });
                
                if (dados.ordenacao && dados.ordenacao !== '') {
                    modeloData.ordenacao = dados.ordenacao;
                }
                
                const method = dados.id ? 'PUT' : 'POST';
                
                console.log('Enviando modelo:', modeloData);
                
                $.ajax({
                    url: '../api/relatorios_salvar_modelo.php',
                    method: method,
                    data: JSON.stringify(modeloData),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(response) {
                        console.log('Resposta do servidor:', response);
                        if (response.status === 'success') {
                            resolve(response.modelo_id);
                        } else {
                            reject(response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erro na requisição:', xhr.responseText);
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            reject(errorResponse.message || 'Erro ao salvar modelo');
                        } catch (e) {
                            reject('Erro ao salvar modelo: ' + error);
                        }
                    }
                });
            });
        }

        // Executa relatório
        function executarRelatorio(dados) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../api/relatorios_executar.php';
            form.target = '_blank';
            
            for (const key in dados) {
                if (Array.isArray(dados[key])) {
                    dados[key].forEach(value => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key + '[]';
                        input.value = value;
                        form.appendChild(input);
                    });
                } else if (dados[key]) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = dados[key];
                    form.appendChild(input);
                }
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            hideLoading();
        }

        // Executa modelo salvo
        function executarModelo(modeloId) {
            showLoading('Carregando modelo...');
            
            $.ajax({
                url: '../api/relatorios_carregar_modelo.php',
                method: 'GET',
                data: { id: modeloId },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        const modelo = response.modelo;
                        
                        const dados = {
                            tipo: modelo.tipo,
                            campos: modelo.campos,
                            formato: 'html',
                            modelo_id: modeloId
                        };
                        
                        if (modelo.filtros) {
                            Object.assign(dados, modelo.filtros);
                        }
                        
                        if (modelo.ordenacao) {
                            dados.ordenacao = modelo.ordenacao;
                        }
                        
                        showLoading('Gerando relatório...');
                        executarRelatorio(dados);
                    } else {
                        alert('Erro ao carregar modelo: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro ao carregar modelo:', error);
                    alert('Erro ao carregar modelo');
                }
            });
        }

        // Edita modelo (abre modal com dados preenchidos)
        function editarModelo(modeloId) {
            showLoading('Carregando modelo...');
            
            $.ajax({
                url: '../api/relatorios_carregar_modelo.php',
                method: 'GET',
                data: { id: modeloId },
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        const modelo = response.modelo;
                        
                        document.getElementById('formPersonalizado').setAttribute('data-modelo-id', modeloId);
                        
                        document.getElementById('nomeRelatorio').value = modelo.nome;
                        document.getElementById('descricaoRelatorio').value = modelo.descricao || '';
                        document.getElementById('tipoRelatorio').value = modelo.tipo;
                        
                        document.getElementById('tipoRelatorio').dispatchEvent(new Event('change'));
                        
                        setTimeout(() => {
                            if (modelo.campos && Array.isArray(modelo.campos)) {
                                document.querySelectorAll('#camposPersonalizados input[type="checkbox"]').forEach(cb => {
                                    cb.checked = modelo.campos.includes(cb.value);
                                });
                                camposOrdenados = [...modelo.campos];
                                atualizarCamposSelecionados();
                            }
                            
                            if (modelo.filtros) {
                                for (const key in modelo.filtros) {
                                    const input = document.querySelector(`#filtrosPersonalizados [name="${key}"]`);
                                    if (input && modelo.filtros[key]) {
                                        input.value = modelo.filtros[key];
                                    }
                                }
                            }
                            
                            if (modelo.ordenacao) {
                                const selectOrdenacao = document.querySelector('[name="ordenacao"]');
                                if (selectOrdenacao) {
                                    selectOrdenacao.value = modelo.ordenacao;
                                }
                            }
                            
                            document.getElementById('salvarModelo').checked = true;
                            
                            document.querySelector('#modalPersonalizado .modal-title-custom').textContent = 'Editar Relatório Personalizado';
                            
                            abrirModalPersonalizado();
                        }, 1000);
                    } else {
                        alert('Erro ao carregar modelo: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro ao carregar modelo:', error);
                    alert('Erro ao carregar modelo');
                }
            });
        }

        // Duplica modelo
        function duplicarModelo(modeloId) {
            if (confirm('Deseja duplicar este modelo?')) {
                showLoading('Duplicando modelo...');
                
                $.ajax({
                    url: '../api/relatorios_carregar_modelo.php',
                    method: 'GET',
                    data: { id: modeloId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            const modelo = response.modelo;
                            modelo.nome = modelo.nome + ' (Cópia)';
                            delete modelo.id;
                            
                            salvarModelo(modelo).then(novoId => {
                                hideLoading();
                                alert('Modelo duplicado com sucesso!');
                                location.reload();
                            }).catch(error => {
                                hideLoading();
                                alert('Erro ao duplicar modelo: ' + error);
                            });
                        } else {
                            hideLoading();
                            alert('Erro ao carregar modelo: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        hideLoading();
                        alert('Erro ao duplicar modelo');
                    }
                });
            }
        }

        // Exclui modelo (apenas diretores)
        function excluirModelo(modeloId, nomeModelo) {
            if (!isDiretor) {
                alert('Apenas diretores podem excluir modelos de relatórios.');
                return;
            }
            
            const mensagem = `Tem certeza que deseja excluir o modelo "${nomeModelo}"?\n\nEsta ação não pode ser desfeita.`;
            
            if (confirm(mensagem)) {
                if (confirm('Esta é uma ação permanente. Confirma a exclusão?')) {
                    showLoading('Excluindo modelo...');
                    
                    $.ajax({
                        url: '../api/relatorios_excluir_modelo.php?id=' + modeloId,
                        method: 'DELETE',
                        dataType: 'json',
                        success: function(response) {
                            hideLoading();
                            
                            if (response.status === 'success') {
                                const card = document.querySelector(`[onclick*="executarModelo(${modeloId})"]`);
                                if (card) {
                                    card.style.transition = 'all 0.3s ease';
                                    card.style.transform = 'scale(0.9)';
                                    card.style.opacity = '0.5';
                                }
                                
                                setTimeout(() => {
                                    alert('Modelo excluído com sucesso!');
                                    location.reload();
                                }, 300);
                            } else {
                                alert('Erro ao excluir modelo: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            hideLoading();
                            
                            try {
                                const errorResponse = JSON.parse(xhr.responseText);
                                alert('Erro ao excluir modelo: ' + errorResponse.message);
                            } catch (e) {
                                alert('Erro ao excluir modelo. Por favor, tente novamente.');
                            }
                            
                            console.error('Erro ao excluir:', xhr.responseText);
                        }
                    });
                }
            }
        }

        // Fecha modal
        function fecharModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
            }
            
            if (modalId === 'modalFiltrosRapidos') {
                const form = document.getElementById('formFiltrosRapidos');
                if (form) form.reset();
            } else if (modalId === 'modalPersonalizado') {
                const form = document.getElementById('formPersonalizado');
                const modeloIdEditando = form ? form.getAttribute('data-modelo-id') : null;
                
                if (!modeloIdEditando && form) {
                    form.reset();
                    form.removeAttribute('data-modelo-id');
                    document.getElementById('secaoCampos').style.display = 'none';
                    document.getElementById('secaoFiltros').style.display = 'none';
                    const title = document.querySelector('#modalPersonalizado .modal-title-custom');
                    if (title) title.textContent = 'Criar Relatório Personalizado';
                    camposOrdenados = [];
                    alternarTabCampos('selecao', null);
                }
            }
        }

        // Fecha modais ao clicar fora
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-custom')) {
                event.target.classList.remove('show');
            }
        });

        // Tecla ESC fecha modais
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal-custom.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        // Retorna campos padrão (fallback)
        // Atualizar a função getCamposPreset para incluir aniversariantes
        function getCamposPreset(tipo, preset) {
            const presets = {
                'associados': {
                    'default': ['nome', 'cpf', 'telefone', 'email', 'situacao', 'corporacao', 'patente'],
                    'hoje': ['nome', 'cpf', 'telefone', 'email', 'dataFiliacao'],
                    'mes': ['nome', 'cpf', 'telefone', 'email', 'situacao', 'dataFiliacao']
                },
                'aniversariantes': {
                    'default': ['nome', 'nasc', 'telefone', 'email', 'corporacao', 'patente', 'idade'],
                    'hoje': ['nome', 'nasc', 'telefone', 'email', 'idade'],
                    'semana': ['nome', 'nasc', 'telefone', 'email', 'corporacao', 'patente', 'idade', 'dias_ate_aniversario'],
                    'mes': ['nome', 'nasc', 'telefone', 'email', 'corporacao', 'patente', 'idade']
                },
                'financeiro': {
                    'default': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira', 'localDebito'],
                    'adimplentes': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira'],
                    'inadimplentes': ['nome', 'cpf', 'tipoAssociado', 'situacaoFinanceira', 'telefone', 'email']
                },
                'militar': {
                    'default': ['nome', 'cpf', 'corporacao', 'patente', 'categoria', 'unidade'],
                    'patente': ['patente', 'nome', 'cpf', 'corporacao', 'unidade'],
                    'corporacao': ['corporacao', 'nome', 'cpf', 'patente', 'unidade']
                },
                'servicos': {
                    'default': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao', 'ativo'],
                    'ativos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao'],
                    'todos': ['nome', 'cpf', 'servico_nome', 'valor_aplicado', 'data_adesao', 'ativo']
                },
                'documentos': {
                    'default': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'verificado'],
                    'pendentes': ['nome', 'cpf', 'tipo_documento', 'data_upload'],
                    'verificados': ['nome', 'cpf', 'tipo_documento', 'data_upload', 'funcionario_nome']
                }
            };

            return presets[tipo]?.[preset] || presets[tipo]?.['default'] || [];
        }

        // Alternância entre tabs de campos
        function alternarTabCampos(tab, event) {
            if (event && event.target) {
                document.querySelectorAll('.campos-tab').forEach(btn => {
                    btn.classList.remove('active');
                });
                event.target.closest('.campos-tab').classList.add('active');
            } else {
                document.querySelectorAll('.campos-tab').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                const botaoCorreto = tab === 'selecao' 
                    ? document.querySelector('.campos-tab:first-child')
                    : document.querySelector('.campos-tab:last-child');
                    
                if (botaoCorreto) {
                    botaoCorreto.classList.add('active');
                }
            }
            
            document.querySelectorAll('.campos-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            if (tab === 'selecao') {
                const tabSelecao = document.getElementById('tabSelecao');
                if (tabSelecao) tabSelecao.classList.add('active');
            } else {
                const tabOrdem = document.getElementById('tabOrdem');
                if (tabOrdem) tabOrdem.classList.add('active');
                atualizarCamposSelecionados();
            }
        }

        // Atualiza lista de campos selecionados para ordenação
        function atualizarCamposSelecionados() {
            const checkboxes = document.querySelectorAll('#camposPersonalizados input[type="checkbox"]:checked');
            const lista = document.getElementById('camposSelecionadosList');
            const empty = document.getElementById('camposSelecionadosEmpty');
            
            if (!lista || !empty) return;
            
            lista.innerHTML = '';
            
            if (checkboxes.length === 0) {
                lista.style.display = 'none';
                empty.style.display = 'block';
                camposOrdenados = [];
                return;
            }
            
            lista.style.display = 'block';
            empty.style.display = 'none';
            
            if (camposOrdenados.length > 0) {
                camposOrdenados = camposOrdenados.filter(campo => {
                    const checkbox = document.querySelector(`#campo_personalizado_${campo}`);
                    return checkbox && checkbox.checked;
                });
                
                checkboxes.forEach(checkbox => {
                    if (!camposOrdenados.includes(checkbox.value)) {
                        camposOrdenados.push(checkbox.value);
                    }
                });
            } else {
                camposOrdenados = [];
                checkboxes.forEach(checkbox => {
                    camposOrdenados.push(checkbox.value);
                });
            }
            
            camposOrdenados.forEach((campo, index) => {
                const checkbox = document.querySelector(`#campo_personalizado_${campo}`);
                if (checkbox && checkbox.checked) {
                    const label = checkbox.closest('.checkbox-item').querySelector('label').textContent.trim();
                    
                    const li = document.createElement('li');
                    li.className = 'campo-selecionado-item';
                    li.draggable = true;
                    li.dataset.campo = campo;
                    li.innerHTML = `
                        <span class="campo-drag-handle">
                            <i class="fas fa-grip-vertical"></i>
                        </span>
                        <span class="campo-selecionado-numero">${index + 1}</span>
                        <span class="campo-selecionado-nome">${label}</span>
                        <button type="button" class="campo-selecionado-remove" onclick="removerCampoSelecionado('${campo}')">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    li.addEventListener('dragstart', handleDragStart);
                    li.addEventListener('dragend', handleDragEnd);
                    li.addEventListener('dragover', handleDragOver);
                    li.addEventListener('drop', handleDrop);
                    li.addEventListener('dragenter', handleDragEnter);
                    li.addEventListener('dragleave', handleDragLeave);
                    
                    lista.appendChild(li);
                }
            });
        }

        // Remove campo da seleção
        function removerCampoSelecionado(campo) {
            const checkbox = document.querySelector(`#camposPersonalizados input[value="${campo}"]`);
            if (checkbox) {
                checkbox.checked = false;
                atualizarCamposSelecionados();
            }
        }

        // Drag and Drop handlers
        let draggedElement = null;

        function handleDragStart(e) {
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            
            document.querySelectorAll('.campo-selecionado-item').forEach(item => {
                item.classList.remove('drag-over');
            });
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDragEnter(e) {
            this.classList.add('drag-over');
        }

        function handleDragLeave(e) {
            this.classList.remove('drag-over');
        }

        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }
            
            if (draggedElement !== this) {
                const lista = document.getElementById('camposSelecionadosList');
                const allItems = [...lista.querySelectorAll('.campo-selecionado-item')];
                const draggedIndex = allItems.indexOf(draggedElement);
                const targetIndex = allItems.indexOf(this);
                
                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedElement, this);
                }
                
                atualizarOrdemCampos();
            }
            
            return false;
        }

        // Atualiza array de campos após reordenação
        function atualizarOrdemCampos() {
            const items = document.querySelectorAll('.campo-selecionado-item');
            camposOrdenados = [];
            
            items.forEach((item, index) => {
                camposOrdenados.push(item.dataset.campo);
                const numero = item.querySelector('.campo-selecionado-numero');
                if (numero) numero.textContent = index + 1;
            });
        }

        console.log('✅ Sistema de Relatórios carregado com sistema de permissões completo!');
    </script>
</body>
</html>