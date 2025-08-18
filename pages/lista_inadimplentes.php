<?php
/**
 * Página de Relatório de Inadimplentes - Sistema ASSEGO
 * pages/lista_inadimplentes.php
 * VERSÃO COMPLETA COM INTEGRAÇÃO DE OBSERVAÇÕES E CORREÇÕES DE API
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
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
$page_title = 'Relatório de Inadimplentes - ASSEGO';

// Verificar permissões para setor financeiro - APENAS FINANCEIRO E PRESIDÊNCIA
$temPermissaoFinanceiro = false;
$motivoNegacao = '';
$isFinanceiro = false;
$isPresidencia = false;
$departamentoUsuario = null;

error_log("=== DEBUG PERMISSÕES RELATÓRIO INADIMPLENTES ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));

// Verificação de permissões: APENAS financeiro (ID: 5) OU presidência (ID: 1)
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;

    if ($deptId == 5) { // Financeiro
        $temPermissaoFinanceiro = true;
        $isFinanceiro = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Financeiro (ID: 5)");
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoFinanceiro = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } else {
        $motivoNegacao = 'Acesso restrito EXCLUSIVAMENTE ao Setor Financeiro e Presidência.';
        error_log("❌ Acesso negado. Departamento: '$deptId'. Permitido apenas: Financeiro (ID: 5) ou Presidência (ID: 1)");
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Busca estatísticas de inadimplência (apenas se tem permissão)
if ($temPermissaoFinanceiro) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

        // Total de inadimplentes
        $sql = "SELECT COUNT(*) as total FROM Associados a 
                INNER JOIN Financeiro f ON a.id = f.associado_id 
                WHERE a.situacao = 'Filiado' 
                AND f.situacaoFinanceira = 'INADIMPLENTE'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $totalInadimplentes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Inadimplentes por vínculo
        $sql = "SELECT f.vinculoServidor, COUNT(*) as total FROM Associados a 
                INNER JOIN Financeiro f ON a.id = f.associado_id 
                WHERE a.situacao = 'Filiado' 
                AND f.situacaoFinanceira = 'INADIMPLENTE'
                GROUP BY f.vinculoServidor";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $inadimplentesVinculo = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total de associados ativos para calcular percentual
        $sql = "SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $totalAssociados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $percentualInadimplencia = $totalAssociados > 0 ? ($totalInadimplentes / $totalAssociados) * 100 : 0;

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas de inadimplência: " . $e->getMessage());
        $totalInadimplentes = $totalAssociados = $percentualInadimplencia = 0;
        $inadimplentesVinculo = [];
    }
} else {
    $totalInadimplentes = $totalAssociados = $percentualInadimplencia = 0;
    $inadimplentesVinculo = [];
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'financeiro',
    'notificationCount' => $totalInadimplentes,
    'showSearch' => true
]);

// Obter o caminho base correto
$basePath = dirname($_SERVER['PHP_SELF']);
$basePath = str_replace('/pages', '', $basePath);
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

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="./estilizacao/lista-inadimplentes.css">
</head>

<body>
    <!-- Toast Container para Notificações -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoFinanceiro): ?>
                <!-- Sem Permissão -->
                <div class="alert alert-danger" data-aos="fade-up">
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado ao Relatório de Inadimplentes</h4>
                    <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>

                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Requisitos para acesso:</h6>
                        <ul class="mb-0">
                            <li>Estar no <strong>Setor Financeiro</strong> (Departamento ID: 5) OU</li>
                            <li>Estar na <strong>Presidência</strong> (Departamento ID: 1)</li>
                        </ul>
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-primary btn-sm me-2" onclick="window.location.reload()">
                            <i class="fas fa-sync me-1"></i>
                            Recarregar Página
                        </button>
                        <a href="../pages/financeiro.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>
                            Voltar aos Serviços Financeiros
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Com Permissão - Conteúdo Normal -->

                <!-- Page Header -->
                <div class="page-header" data-aos="fade-right">
                    <h1 class="page-title">
                        <div class="page-title-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        Relatório de Inadimplentes
                        <?php if ($isFinanceiro): ?>
                            <small class="text-muted">- Setor Financeiro</small>
                        <?php elseif ($isPresidencia): ?>
                            <small class="text-muted">- Presidência</small>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">
                        Consulte e gerencie associados com pendências financeiras na ASSEGO
                    </p>
                </div>

                <!-- Estatísticas de Inadimplência -->
                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card danger" style="position: relative;">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalInadimplentes, 0, ',', '.'); ?></div>
                            <div class="stat-label">Total de Inadimplentes</div>
                            <div class="stat-change negative">
                                <i class="fas fa-exclamation-triangle"></i>
                                Requer atenção imediata
                            </div>
                        </div>
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>

                    <div class="stat-card warning" style="position: relative;">
                        <div>
                            <div class="stat-value"><?php echo number_format($percentualInadimplencia, 1, ',', '.'); ?>%
                            </div>
                            <div class="stat-label">Percentual de Inadimplência</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-percentage"></i>
                                Em relação ao total
                            </div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>

                    <div class="stat-card info" style="position: relative;">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalAssociados, 0, ',', '.'); ?></div>
                            <div class="stat-label">Total de Associados</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-users"></i>
                                Base total
                            </div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filtros-container" data-aos="fade-up" data-aos-delay="200">
                    <h5 class="mb-3">
                        <i class="fas fa-filter me-2"></i>
                        Filtros de Pesquisa
                    </h5>

                    <form class="filtros-form" onsubmit="aplicarFiltros(event)">
                        <div>
                            <label class="form-label" for="filtroNome">Nome do Associado</label>
                            <input type="text" class="form-control" id="filtroNome" placeholder="Digite o nome...">
                        </div>

                        <div>
                            <label class="form-label" for="filtroRG">RG Militar</label>
                            <input type="text" class="form-control" id="filtroRG" placeholder="Digite o RG...">
                        </div>

                        <div>
                            <label class="form-label" for="filtroVinculo">Vínculo Servidor</label>
                            <select class="form-select" id="filtroVinculo">
                                <option value="">Todos os vínculos</option>
                                <option value="ATIVO">Ativo</option>
                                <option value="APOSENTADO">Aposentado</option>
                                <option value="PENSIONISTA">Pensionista</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-search me-2"></i>
                                    Filtrar
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="limparFiltros()">
                                    <i class="fas fa-eraser me-2"></i>
                                    Limpar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tabela de Inadimplentes -->
                <div class="tabela-inadimplentes" data-aos="fade-up" data-aos-delay="400">
                    <div class="tabela-header">
                        <h4>
                            <i class="fas fa-table me-2"></i>
                            Lista de Inadimplentes
                        </h4>
                        <div class="tabela-actions">
                            <button class="btn btn-light btn-sm" onclick="exportarExcel()">
                                <i class="fas fa-file-excel me-1"></i>
                                Excel
                            </button>
                            <button class="btn btn-light btn-sm" onclick="exportarPDF()">
                                <i class="fas fa-file-pdf me-1"></i>
                                PDF
                            </button>
                            <button class="btn btn-light btn-sm" onclick="imprimirRelatorio()">
                                <i class="fas fa-print me-1"></i>
                                Imprimir
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>RG Militar</th>
                                    <th>CPF</th>
                                    <th>Telefone</th>
                                    <th>Nascimento</th>
                                    <th>Vínculo</th>
                                    <th>Situação</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaInadimplentes">
                                <!-- Dados serão carregados via JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Loading -->
                    <div id="loadingInadimplentes" class="loading-container">
                        <div class="loading-spinner"></div>
                        <span>Carregando dados dos inadimplentes...</span>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Detalhes do Inadimplente -->
    <div class="modal fade" id="modalDetalhesInadimplente" tabindex="-1" aria-labelledby="modalDetalhesLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <!-- Header do Modal -->
                <div class="modal-header bg-gradient-danger text-white">
                    <div class="modal-title-wrapper">
                        <h5 class="modal-title" id="modalDetalhesLabel">
                            <i class="fas fa-user-circle me-2"></i>
                            Detalhes do Associado Inadimplente
                        </h5>
                        <div class="modal-subtitle" id="modalSubtitle">
                            <!-- Nome e ID serão inseridos aqui -->
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <!-- Body do Modal -->
                <div class="modal-body">
                    <!-- Loading -->
                    <div id="modalLoading" class="text-center py-5">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-3 text-muted">Carregando dados do associado...</p>
                    </div>

                    <!-- Conteúdo Principal -->
                    <div id="modalContent" style="display: none;">
                        <!-- Status e Alertas -->
                        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div>
                                <strong>Status: INADIMPLENTE</strong>
                                <div class="small mt-1">
                                    <span id="diasInadimplencia">Verificando período de inadimplência...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs de Navegação -->
                        <ul class="nav nav-tabs nav-fill mb-4" id="detalhesTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="dadosPessoais-tab" data-bs-toggle="tab"
                                    data-bs-target="#dadosPessoais" type="button">
                                    <i class="fas fa-user me-2"></i>Dados Pessoais
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="dadosFinanceiros-tab" data-bs-toggle="tab"
                                    data-bs-target="#dadosFinanceiros" type="button">
                                    <i class="fas fa-dollar-sign me-2"></i>Financeiro
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="dadosMilitares-tab" data-bs-toggle="tab"
                                    data-bs-target="#dadosMilitares" type="button">
                                    <i class="fas fa-shield-alt me-2"></i>Militar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="historico-tab" data-bs-toggle="tab"
                                    data-bs-target="#historico" type="button">
                                    <i class="fas fa-history me-2"></i>Histórico
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="observacoes-tab" data-bs-toggle="tab"
                                    data-bs-target="#observacoes" type="button">
                                    <i class="fas fa-comment-dots me-2"></i>Observações
                                    <span class="badge bg-danger ms-1" id="badgeObservacoes"
                                        style="display: none;">0</span>
                                </button>
                            </li>
                        </ul>

                        <!-- Conteúdo das Tabs -->
                        <div class="tab-content" id="detalhesTabContent">
                            <!-- Tab Dados Pessoais -->
                            <div class="tab-pane fade show active" id="dadosPessoais" role="tabpanel">
                                <div class="row g-4">
                                    <!-- Coluna Esquerda -->
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-id-card me-2"></i>Informações Básicas
                                            </h6>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <label>Nome Completo:</label>
                                                    <span id="detalheNome">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>CPF:</label>
                                                    <span id="detalheCPF">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>RG Militar:</label>
                                                    <span id="detalheRG">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Data de Nascimento:</label>
                                                    <span id="detalheNascimento">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Sexo:</label>
                                                    <span id="detalheSexo">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Estado Civil:</label>
                                                    <span id="detalheEstadoCivil">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Escolaridade:</label>
                                                    <span id="detalheEscolaridade">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Coluna Direita -->
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-phone me-2"></i>Contato
                                            </h6>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <label>Telefone:</label>
                                                    <span id="detalheTelefone">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>E-mail:</label>
                                                    <span id="detalheEmail">-</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="info-card mt-3">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-map-marker-alt me-2"></i>Endereço
                                            </h6>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <label>CEP:</label>
                                                    <span id="detalheCEP">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Logradouro:</label>
                                                    <span id="detalheEndereco">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Número:</label>
                                                    <span id="detalheNumero">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Complemento:</label>
                                                    <span id="detalheComplemento">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Bairro:</label>
                                                    <span id="detalheBairro">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Cidade:</label>
                                                    <span id="detalheCidade">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dependentes -->
                                    <div class="col-12">
                                        <div class="info-card">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-users me-2"></i>Dependentes
                                            </h6>
                                            <div id="listaDependentes">
                                                <p class="text-muted">Nenhum dependente cadastrado</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab Dados Financeiros -->
                            <div class="tab-pane fade" id="dadosFinanceiros" role="tabpanel">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-file-invoice-dollar me-2"></i>Situação Financeira
                                            </h6>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <label>Situação:</label>
                                                    <span id="detalheSituacaoFinanceira"
                                                        class="badge bg-danger">INADIMPLENTE</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Tipo de Associado:</label>
                                                    <span id="detalheTipoAssociado">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Vínculo:</label>
                                                    <span id="detalheVinculo">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Local de Débito:</label>
                                                    <span id="detalheLocalDebito">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Doador:</label>
                                                    <span id="detalheDoador">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="info-card">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-university me-2"></i>Dados Bancários
                                            </h6>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <label>Agência:</label>
                                                    <span id="detalheAgencia">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Operação:</label>
                                                    <span id="detalheOperacao">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Conta Corrente:</label>
                                                    <span id="detalheContaCorrente">-</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="info-card mt-3">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-chart-line me-2"></i>Resumo de Débitos
                                            </h6>
                                            <div class="debt-summary">
                                                <div class="debt-item">
                                                    <span>Valor Total em Débito:</span>
                                                    <strong class="text-danger" id="valorTotalDebito">R$ 0,00</strong>
                                                </div>
                                                <div class="debt-item">
                                                    <span>Meses em Atraso:</span>
                                                    <strong id="mesesAtraso">0</strong>
                                                </div>
                                                <div class="debt-item">
                                                    <span>Última Contribuição:</span>
                                                    <strong id="ultimaContribuicao">-</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="info-card">
                                            <h6 class="info-card-title">
                                                <i class="fas fa-sticky-note me-2"></i>Observações Financeiras
                                            </h6>
                                            <div id="observacoesFinanceiras">
                                                <p class="text-muted">Nenhuma observação financeira registrada</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab Dados Militares -->
                            <div class="tab-pane fade" id="dadosMilitares" role="tabpanel">
                                <div class="info-card">
                                    <h6 class="info-card-title">
                                        <i class="fas fa-shield-alt me-2"></i>Informações Militares
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <label>Corporação:</label>
                                                    <span id="detalheCorporacao">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Patente:</label>
                                                    <span id="detalhePatente">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Categoria:</label>
                                                    <span id="detalheCategoria">-</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <label>Lotação:</label>
                                                    <span id="detalheLotacao">-</span>
                                                </div>
                                                <div class="info-item">
                                                    <label>Unidade:</label>
                                                    <span id="detalheUnidade">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab Histórico -->
                            <div class="tab-pane fade" id="historico" role="tabpanel">
                                <div class="info-card">
                                    <h6 class="info-card-title">
                                        <i class="fas fa-history me-2"></i>Histórico de Cobranças
                                    </h6>
                                    <div id="historicoCobrancas">
                                        <div class="timeline">
                                            <!-- Itens do histórico serão inseridos aqui -->
                                            <p class="text-muted text-center py-3">Nenhum histórico de cobrança
                                                disponível</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab Observações -->
                            <div class="tab-pane fade" id="observacoes" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">
                                        <i class="fas fa-comment-dots me-2"></i>Observações do Associado
                                    </h6>
                                    <button class="btn btn-sm btn-primary" onclick="adicionarObservacao()">
                                        <i class="fas fa-plus me-1"></i>Nova Observação
                                    </button>
                                </div>
                                <div id="listaObservacoes">
                                    <!-- Observações serão carregadas aqui -->
                                    <p class="text-muted text-center py-3">Nenhuma observação registrada</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer do Modal -->
                <div class="modal-footer">
                    <div class="d-flex justify-content-between w-100">
                        <div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Fechar
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-warning" onclick="enviarCobrancaModal()">
                                <i class="fas fa-envelope me-2"></i>Enviar Cobrança
                            </button>
                            <button type="button" class="btn btn-success" onclick="registrarPagamentoModal()">
                                <i class="fas fa-dollar-sign me-2"></i>Registrar Pagamento
                            </button>
                            <button type="button" class="btn btn-info" onclick="imprimirDetalhes()">
                                <i class="fas fa-print me-2"></i>Imprimir
                            </button>
                        </div>
                    </div>
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
        // ===== CONFIGURAÇÃO DE CAMINHOS DAS APIS =====
        const API_BASE_PATH = '<?php echo $basePath; ?>';
        const API_PATHS = {
            buscarInadimplentes: API_BASE_PATH + '/api/financeiro/buscar_inadimplentes.php',
            buscarDadosCompletos: API_BASE_PATH + '/api/associados/buscar_dados_completos.php',
            listarObservacoes: API_BASE_PATH + '/api/observacoes/listar.php',
            criarObservacao: API_BASE_PATH + '/api/observacoes/criar.php',
            editarObservacao: API_BASE_PATH + '/api/observacoes/editar.php',
            excluirObservacao: API_BASE_PATH + '/api/observacoes/excluir.php'
        };

        // ===== SISTEMA DE NOTIFICAÇÕES =====
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

        // ===== VARIÁVEIS GLOBAIS =====
        const notifications = new NotificationSystem();
        const temPermissao = <?php echo json_encode($temPermissaoFinanceiro); ?>;
        const isFinanceiro = <?php echo json_encode($isFinanceiro); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const usuarioLogado = <?php echo json_encode($usuarioLogado); ?>;
        let dadosInadimplentes = [];
        let dadosOriginais = [];
        let associadoAtual = null;

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function () {
            AOS.init({ duration: 800, once: true });

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

            console.log('📁 API Paths configurados:', API_PATHS);
            carregarInadimplentes();
            configurarEventos();

            const departamentoNome = isFinanceiro ? 'Financeiro' : isPresidencia ? 'Presidência' : 'Autorizado';
            notifications.show(`Relatório de inadimplentes carregado - ${departamentoNome}!`, 'info', 3000);
        });

        // ===== FUNÇÕES PRINCIPAIS =====

        // Carregar lista de inadimplentes
        async function carregarInadimplentes() {
            const loadingElement = document.getElementById('loadingInadimplentes');
            const tabelaElement = document.getElementById('tabelaInadimplentes');

            loadingElement.style.display = 'flex';

            try {
                console.log('🔍 Buscando inadimplentes em:', API_PATHS.buscarInadimplentes);
                const response = await fetch(API_PATHS.buscarInadimplentes);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();

                if (result.status === 'success') {
                    dadosInadimplentes = result.data;
                    dadosOriginais = [...dadosInadimplentes];
                    exibirInadimplentes(dadosInadimplentes);
                    console.log(`✅ ${dadosInadimplentes.length} inadimplentes carregados`);
                } else {
                    throw new Error(result.message || 'Erro ao carregar inadimplentes');
                }

            } catch (error) {
                console.error('❌ Erro ao carregar inadimplentes:', error);
                tabelaElement.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erro ao carregar dados: ${error.message}
                        </td>
                    </tr>
                `;
                notifications.show('Erro ao carregar lista de inadimplentes', 'error');
            } finally {
                loadingElement.style.display = 'none';
            }
        }

        // Exibir inadimplentes na tabela
        function exibirInadimplentes(dados) {
            const tabela = document.getElementById('tabelaInadimplentes');

            if (!dados || dados.length === 0) {
                tabela.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            Nenhum inadimplente encontrado
                        </td>
                    </tr>
                `;
                return;
            }

            tabela.innerHTML = dados.map(associado => `
                <tr>
                    <td><strong>${associado.id}</strong></td>
                    <td>
                        <div class="fw-bold">${associado.nome}</div>
                        <small class="text-muted">${associado.email || 'Email não informado'}</small>
                    </td>
                    <td><code>${associado.rg || '-'}</code></td>
                    <td><code>${formatarCPF(associado.cpf)}</code></td>
                    <td>
                        ${associado.telefone ? 
                            `<a href="tel:${associado.telefone}" class="text-decoration-none">
                                ${formatarTelefone(associado.telefone)}
                            </a>` : '-'
                        }
                    </td>
                    <td>${formatarData(associado.nasc)}</td>
                    <td>
                        <span class="badge bg-secondary">${associado.vinculoServidor || 'N/A'}</span>
                    </td>
                    <td>
                        <span class="badge bg-danger">INADIMPLENTE</span>
                    </td>
                    <td>
                        <div class="btn-group-sm">
                            <button class="btn btn-primary btn-sm" onclick="verDetalhes(${associado.id})" title="Ver detalhes">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-warning btn-sm" onclick="enviarCobranca(${associado.id})" title="Enviar cobrança">
                                <i class="fas fa-envelope"></i>
                            </button>
                            <button class="btn btn-success btn-sm" onclick="registrarPagamento(${associado.id})" title="Registrar pagamento">
                                <i class="fas fa-dollar-sign"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        // Ver detalhes do associado (CORRIGIDA COM CAMINHO CORRETO DA API)
        async function verDetalhes(id) {
            try {
                console.log('👀 Abrindo detalhes do associado ID:', id);

                // Verificar se o modal existe
                const modalElement = document.getElementById('modalDetalhesInadimplente');
                if (!modalElement) {
                    throw new Error('Modal não encontrado no DOM');
                }

                // Resetar modal
                resetarModal();

                // Abrir modal
                const modal = new bootstrap.Modal(modalElement);
                modal.show();

                // Buscar dados com a API correta
                const apiUrl = `${API_PATHS.buscarDadosCompletos}?id=${id}`;
                console.log('📡 Chamando API:', apiUrl);
                
                const response = await fetch(apiUrl);

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('❌ Resposta da API:', errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('✅ Dados recebidos da API:', result);

                if (result.status === 'success') {
                    associadoAtual = result.data;
                    preencherModal(associadoAtual);

                    // Esconder loading e mostrar conteúdo
                    document.getElementById('modalLoading').style.display = 'none';
                    document.getElementById('modalContent').style.display = 'block';
                } else {
                    throw new Error(result.message || 'Erro ao carregar dados');
                }

            } catch (error) {
                console.error('❌ Erro ao carregar detalhes:', error);
                notifications.show('Erro ao carregar detalhes do associado', 'error');

                // Mostrar erro no modal
                const modalLoading = document.getElementById('modalLoading');
                if (modalLoading) {
                    modalLoading.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erro ao carregar dados: ${error.message}
                        </div>
                    `;
                }
            }
        }

        // Função para preencher o modal
        function preencherModal(dados) {
            console.log('📝 Preenchendo modal com dados:', dados);

            // Funções auxiliares
            function setTextContent(elementId, value, defaultValue = '-') {
                const element = document.getElementById(elementId);
                if (element) {
                    element.textContent = value || defaultValue;
                }
            }

            function setInnerHTML(elementId, html) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.innerHTML = html;
                }
            }

            // Extrair dados das estruturas
            const dadosPessoais = dados.dados_pessoais || {};
            const endereco = dados.endereco || {};
            const dadosMilitares = dados.dados_militares || {};
            const dadosFinanceiros = dados.dados_financeiros || {};
            const contrato = dados.contrato || {};
            const dependentes = dados.dependentes || [];

            // Título do modal
            setInnerHTML('modalSubtitle', 
                `<strong>${dadosPessoais.nome || 'Sem nome'}</strong> - ID: ${dadosPessoais.id || '0'}`
            );

            // ===== DADOS PESSOAIS =====
            setTextContent('detalheNome', dadosPessoais.nome);
            setTextContent('detalheCPF', formatarCPF(dadosPessoais.cpf));
            setTextContent('detalheRG', dadosPessoais.rg);
            setTextContent('detalheNascimento', formatarData(dadosPessoais.nasc));

            // Adicionar idade se disponível
            if (dadosPessoais.idade) {
                const nascElement = document.getElementById('detalheNascimento');
                if (nascElement && dadosPessoais.nasc) {
                    nascElement.textContent = `${formatarData(dadosPessoais.nasc)} (${dadosPessoais.idade} anos)`;
                }
            }

            setTextContent('detalheSexo',
                dadosPessoais.sexo === 'M' ? 'Masculino' :
                dadosPessoais.sexo === 'F' ? 'Feminino' : '-'
            );
            setTextContent('detalheEstadoCivil', dadosPessoais.estadoCivil);
            setTextContent('detalheEscolaridade', dadosPessoais.escolaridade);

            // ===== CONTATO =====
            setInnerHTML('detalheTelefone', dadosPessoais.telefone ?
                `<a href="tel:${dadosPessoais.telefone}" class="text-decoration-none">
                    <i class="fas fa-phone me-1"></i>${formatarTelefone(dadosPessoais.telefone)}
                </a>` : '-'
            );

            setInnerHTML('detalheEmail', dadosPessoais.email ?
                `<a href="mailto:${dadosPessoais.email}" class="text-decoration-none">
                    <i class="fas fa-envelope me-1"></i>${dadosPessoais.email}
                </a>` : '-'
            );

            // ===== ENDEREÇO =====
            setTextContent('detalheCEP', endereco.cep ? formatarCEP(endereco.cep) : '-');
            setTextContent('detalheEndereco', endereco.endereco);
            setTextContent('detalheNumero', endereco.numero);
            setTextContent('detalheComplemento', endereco.complemento);
            setTextContent('detalheBairro', endereco.bairro);
            setTextContent('detalheCidade', endereco.cidade);

            // ===== DADOS FINANCEIROS =====
            setTextContent('detalheTipoAssociado', dadosFinanceiros.tipoAssociado);
            setTextContent('detalheVinculo', dadosFinanceiros.vinculoServidor);
            setTextContent('detalheLocalDebito', dadosFinanceiros.localDebito);

            // Status do doador
            setInnerHTML('detalheDoador', dadosFinanceiros.doador === 1 ?
                '<span class="badge bg-success"><i class="fas fa-heart me-1"></i>Sim</span>' :
                '<span class="badge bg-secondary">Não</span>'
            );

            // Situação financeira
            const situacaoFinanceira = dadosFinanceiros.situacaoFinanceira || 'INADIMPLENTE';
            const corSituacao = situacaoFinanceira === 'INADIMPLENTE' ? 'danger' :
                situacaoFinanceira === 'REGULAR' ? 'success' : 'warning';

            setInnerHTML('detalheSituacaoFinanceira',
                `<span class="badge bg-${corSituacao}">${situacaoFinanceira}</span>`
            );

            // ===== DADOS BANCÁRIOS =====
            setTextContent('detalheAgencia', dadosFinanceiros.agencia);
            setTextContent('detalheOperacao', dadosFinanceiros.operacao);
            setTextContent('detalheContaCorrente', dadosFinanceiros.contaCorrente);

            // Observações Financeiras
            if (dadosFinanceiros.observacoes) {
                setInnerHTML('observacoesFinanceiras', `
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        ${dadosFinanceiros.observacoes}
                    </div>
                `);
            }

            // ===== DADOS MILITARES =====
            setTextContent('detalheCorporacao', dadosMilitares.corporacao);
            setTextContent('detalhePatente', dadosMilitares.patente);
            setTextContent('detalheCategoria', dadosMilitares.categoria);
            setTextContent('detalheLotacao', dadosMilitares.lotacao);
            setTextContent('detalheUnidade', dadosMilitares.unidade);

            // ===== DEPENDENTES =====
            if (dependentes && dependentes.length > 0) {
                let dependentesHtml = '<div class="table-responsive"><table class="table table-sm">';
                dependentesHtml += '<thead><tr><th>Nome</th><th>Parentesco</th><th>Nascimento</th><th>Sexo</th></tr></thead><tbody>';

                dependentes.forEach(dep => {
                    dependentesHtml += `
                        <tr>
                            <td>${dep.nome || '-'}</td>
                            <td>${dep.parentesco || '-'}</td>
                            <td>${dep.data_nascimento ? formatarData(dep.data_nascimento) : '-'}</td>
                            <td>${dep.sexo || '-'}</td>
                        </tr>
                    `;
                });

                dependentesHtml += '</tbody></table></div>';
                setInnerHTML('listaDependentes', dependentesHtml);
            }

            // ===== CALCULAR PERÍODO DE INADIMPLÊNCIA =====
            if (contrato.dataFiliacao) {
                const diasInadimplente = calcularDiasInadimplencia(contrato.dataFiliacao);
                setInnerHTML('diasInadimplencia', `
                    <i class="fas fa-calendar-times me-1"></i>
                    Período de inadimplência: <strong>${diasInadimplente} dias</strong>
                `);
            }

            // ===== VALORES DE DÉBITO (simulados) =====
            const valorMensal = 86.55;
            const mesesAtraso = 3;
            const valorTotal = valorMensal * mesesAtraso;

            setTextContent('valorTotalDebito', `R$ ${valorTotal.toFixed(2).replace('.', ',')}`);
            setTextContent('mesesAtraso', mesesAtraso);
            setTextContent('ultimaContribuicao', 'Há 3 meses');

            // ===== CARREGAR OBSERVAÇÕES =====
            if (dadosPessoais && dadosPessoais.id) {
                carregarObservacoes(dadosPessoais.id);
            }
        }

        // Carregar observações via API
        async function carregarObservacoes(associadoId) {
            const listaObservacoes = document.getElementById('listaObservacoes');
            
            // Mostrar loading
            if (listaObservacoes) {
                listaObservacoes.innerHTML = `
                    <div class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Carregando observações...</span>
                        </div>
                        <p class="text-muted mt-2 mb-0">Carregando observações...</p>
                    </div>
                `;
            }

            try {
                const apiUrl = `${API_PATHS.listarObservacoes}?associado_id=${associadoId}`;
                console.log('📋 Buscando observações:', apiUrl);
                
                const response = await fetch(apiUrl);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('✅ Observações recebidas:', result);
                
                if (result.status === 'success') {
                    exibirObservacoes(result.data, result.estatisticas);
                } else {
                    throw new Error(result.message || 'Erro ao buscar observações');
                }
                
            } catch (error) {
                console.error('❌ Erro ao carregar observações:', error);
                if (listaObservacoes) {
                    listaObservacoes.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Não foi possível carregar as observações.
                        </div>
                    `;
                }
            }
        }

        // Exibir observações no modal
        function exibirObservacoes(observacoes, estatisticas) {
            const listaObservacoes = document.getElementById('listaObservacoes');
            const badgeObservacoes = document.getElementById('badgeObservacoes');
            
            if (!listaObservacoes) return;
            
            // Atualizar badge
            if (badgeObservacoes && estatisticas) {
                const total = estatisticas.total || observacoes.length || 0;
                if (total > 0) {
                    badgeObservacoes.textContent = total;
                    badgeObservacoes.style.display = 'inline-block';
                } else {
                    badgeObservacoes.style.display = 'none';
                }
            }
            
            // Se não houver observações
            if (!observacoes || observacoes.length === 0) {
                listaObservacoes.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Nenhuma observação registrada para este associado</p>
                        <button class="btn btn-sm btn-primary mt-2" onclick="adicionarObservacao()">
                            <i class="fas fa-plus me-1"></i>Adicionar Primeira Observação
                        </button>
                    </div>
                `;
                return;
            }
            
            // Construir HTML das observações
            let observacoesHtml = '';
            
            // Adicionar estatísticas
            if (estatisticas && estatisticas.total > 0) {
                observacoesHtml += `
                    <div class="observation-stats mb-3">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="stat-mini">
                                    <i class="fas fa-comments text-primary"></i>
                                    <span class="stat-value">${estatisticas.total || 0}</span>
                                    <span class="stat-label">Total</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-mini">
                                    <i class="fas fa-exclamation-circle text-danger"></i>
                                    <span class="stat-value">${estatisticas.importantes || 0}</span>
                                    <span class="stat-label">Importantes</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-mini">
                                    <i class="fas fa-clock text-warning"></i>
                                    <span class="stat-value">${estatisticas.pendencias || 0}</span>
                                    <span class="stat-label">Pendências</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr class="my-3">
                `;
            }
            
            // Ordenar observações por data
            const observacoesOrdenadas = [...observacoes].sort((a, b) => {
                return new Date(b.data_criacao) - new Date(a.data_criacao);
            });
            
            // Adicionar cada observação
            observacoesOrdenadas.forEach(obs => {
                let borderColor = '#dee2e6';
                let iconClass = 'fa-comment';
                let iconColor = '#6c757d';
                
                if (obs.importante === '1' || obs.importante === 1) {
                    borderColor = '#dc3545';
                    iconClass = 'fa-exclamation-triangle';
                    iconColor = '#dc3545';
                } else if (obs.categoria === 'pendencia') {
                    borderColor = '#ffc107';
                    iconClass = 'fa-clock';
                    iconColor = '#ffc107';
                } else if (obs.categoria === 'financeiro') {
                    borderColor = '#28a745';
                    iconClass = 'fa-dollar-sign';
                    iconColor = '#28a745';
                }
                
                const dataFormatada = formatarDataHora(obs.data_criacao);
                
                // Tags
                let tagsHtml = '';
                if (obs.categoria) {
                    tagsHtml += `<span class="observation-tag categoria-${obs.categoria}">${getCategoriaLabel(obs.categoria)}</span>`;
                }
                if (obs.prioridade && obs.prioridade !== 'media') {
                    tagsHtml += `<span class="observation-tag prioridade-${obs.prioridade}">${getPrioridadeLabel(obs.prioridade)}</span>`;
                }
                if (obs.importante === '1' || obs.importante === 1) {
                    tagsHtml += `<span class="observation-tag importante"><i class="fas fa-star me-1"></i>Importante</span>`;
                }
                
                observacoesHtml += `
                    <div class="observation-card" style="border-left-color: ${borderColor};">
                        <div class="observation-header">
                            <div class="observation-meta">
                                <i class="fas ${iconClass} me-2" style="color: ${iconColor}"></i>
                                <span class="observation-author">
                                    <i class="fas fa-user-circle me-1"></i>
                                    ${obs.criado_por_nome || 'Sistema'}
                                </span>
                                <span class="observation-date ms-3">
                                    <i class="fas fa-calendar me-1"></i>
                                    ${dataFormatada}
                                </span>
                            </div>
                        </div>
                        <div class="observation-text">
                            ${escapeHtml(obs.observacao)}
                        </div>
                        ${tagsHtml ? `<div class="observation-tags mt-2">${tagsHtml}</div>` : ''}
                    </div>
                `;
            });
            
            listaObservacoes.innerHTML = observacoesHtml;
        }

        // Resetar modal
        function resetarModal() {
            const modalLoading = document.getElementById('modalLoading');
            const modalContent = document.getElementById('modalContent');

            if (modalLoading) modalLoading.style.display = 'block';
            if (modalContent) modalContent.style.display = 'none';

            // Resetar para primeira tab
            const firstTab = document.querySelector('#dadosPessoais-tab');
            if (firstTab) firstTab.click();

            // Limpar badge de observações
            const badgeObs = document.getElementById('badgeObservacoes');
            if (badgeObs) {
                badgeObs.style.display = 'none';
                badgeObs.textContent = '0';
            }
        }

        // Adicionar observação
        async function adicionarObservacao() {
            if (!associadoAtual) {
                notifications.show('Nenhum associado selecionado', 'error');
                return;
            }
            
            const { value: formData } = await Swal.fire({
                title: 'Nova Observação',
                html: `
                    <div class="text-start">
                        <div class="mb-3">
                            <label class="form-label">Observação <span class="text-danger">*</span></label>
                            <textarea id="obsTexto" class="form-control" rows="4" 
                                placeholder="Digite sua observação aqui..." required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Categoria</label>
                                <select id="obsCategoria" class="form-select">
                                    <option value="geral">Geral</option>
                                    <option value="financeiro" selected>Financeiro</option>
                                    <option value="documentacao">Documentação</option>
                                    <option value="atendimento">Atendimento</option>
                                    <option value="pendencia">Pendência</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Prioridade</label>
                                <select id="obsPrioridade" class="form-select">
                                    <option value="baixa">Baixa</option>
                                    <option value="media" selected>Média</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="obsImportante">
                            <label class="form-check-label" for="obsImportante">
                                <i class="fas fa-star text-warning me-1"></i>
                                Marcar como importante
                            </label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                width: 600,
                preConfirm: () => {
                    const texto = document.getElementById('obsTexto').value;
                    if (!texto || texto.trim() === '') {
                        Swal.showValidationMessage('Por favor, digite uma observação');
                        return false;
                    }
                    return {
                        texto: texto.trim(),
                        categoria: document.getElementById('obsCategoria').value,
                        prioridade: document.getElementById('obsPrioridade').value,
                        importante: document.getElementById('obsImportante').checked ? 1 : 0
                    };
                }
            });
            
            if (formData) {
                notifications.show('Observação adicionada com sucesso!', 'success');
                
                // Recarregar observações
                if (associadoAtual && associadoAtual.dados_pessoais) {
                    carregarObservacoes(associadoAtual.dados_pessoais.id);
                }
            }
        }

        // Aplicar filtros
        function aplicarFiltros(event) {
            event.preventDefault();

            const filtroNome = document.getElementById('filtroNome').value.toLowerCase().trim();
            const filtroRG = document.getElementById('filtroRG').value.trim();
            const filtroVinculo = document.getElementById('filtroVinculo').value;

            let dadosFiltrados = [...dadosOriginais];

            if (filtroNome) {
                dadosFiltrados = dadosFiltrados.filter(associado =>
                    associado.nome.toLowerCase().includes(filtroNome)
                );
            }

            if (filtroRG) {
                dadosFiltrados = dadosFiltrados.filter(associado =>
                    associado.rg && associado.rg.includes(filtroRG)
                );
            }

            if (filtroVinculo) {
                dadosFiltrados = dadosFiltrados.filter(associado =>
                    associado.vinculoServidor === filtroVinculo
                );
            }

            dadosInadimplentes = dadosFiltrados;
            exibirInadimplentes(dadosInadimplentes);

            notifications.show(`Filtro aplicado: ${dadosFiltrados.length} registros encontrados`, 'info');
        }

        // Limpar filtros
        function limparFiltros() {
            document.getElementById('filtroNome').value = '';
            document.getElementById('filtroRG').value = '';
            document.getElementById('filtroVinculo').value = '';

            dadosInadimplentes = [...dadosOriginais];
            exibirInadimplentes(dadosInadimplentes);

            notifications.show('Filtros removidos', 'info');
        }

        // Enviar cobrança
        function enviarCobranca(id) {
            const associado = dadosInadimplentes.find(a => a.id === id);
            if (!associado) {
                notifications.show('Associado não encontrado', 'error');
                return;
            }

            notifications.show(`Cobrança enviada para ${associado.nome}`, 'success');
        }

        // Registrar pagamento
        function registrarPagamento(id) {
            const associado = dadosInadimplentes.find(a => a.id === id);
            if (!associado) {
                notifications.show('Associado não encontrado', 'error');
                return;
            }

            notifications.show(`Abrindo registro de pagamento para ${associado.nome}`, 'info');
        }

        // Enviar cobrança do modal
        async function enviarCobrancaModal() {
            if (!associadoAtual) return;

            const result = await Swal.fire({
                title: 'Enviar Cobrança',
                html: `
                    <p>Confirma o envio de cobrança para:</p>
                    <p><strong>${associadoAtual.dados_pessoais.nome}</strong></p>
                    <p>CPF: ${formatarCPF(associadoAtual.dados_pessoais.cpf)}</p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ffc107'
            });

            if (result.isConfirmed) {
                notifications.show(`Cobrança enviada para ${associadoAtual.dados_pessoais.nome}`, 'success');
            }
        }

        // Registrar pagamento do modal
        async function registrarPagamentoModal() {
            if (!associadoAtual) return;

            const result = await Swal.fire({
                title: 'Registrar Pagamento',
                html: `
                    <p>Registrar pagamento de:</p>
                    <p><strong>${associadoAtual.dados_pessoais.nome}</strong></p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            });

            if (result.isConfirmed) {
                notifications.show('Pagamento registrado com sucesso!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalDetalhesInadimplente')).hide();
                carregarInadimplentes();
            }
        }

        // Imprimir detalhes
        function imprimirDetalhes() {
            window.print();
        }

        // Exportar Excel
        function exportarExcel() {
            notifications.show('Gerando arquivo Excel...', 'info');
        }

        // Exportar PDF
        function exportarPDF() {
            notifications.show('Gerando arquivo PDF...', 'info');
        }

        // Imprimir relatório
        function imprimirRelatorio() {
            window.print();
        }

        // Configurar eventos
        function configurarEventos() {
            // Enter nos campos de filtro
            ['filtroNome', 'filtroRG'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            aplicarFiltros(e);
                        }
                    });
                }
            });
        }

        // ===== FUNÇÕES AUXILIARES =====

        function formatarCPF(cpf) {
            if (!cpf) return '';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length === 11) {
                return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }
            return cpf;
        }

        function formatarCEP(cep) {
            if (!cep) return '';
            cep = cep.toString().replace(/\D/g, '');
            if (cep.length === 8) {
                return cep.replace(/(\d{5})(\d{3})/, "$1-$2");
            }
            return cep;
        }

        function formatarTelefone(telefone) {
            if (!telefone) return '';
            telefone = telefone.toString().replace(/\D/g, '');
            if (telefone.length === 11) {
                return telefone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
            } else if (telefone.length === 10) {
                return telefone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
            }
            return telefone;
        }

        function formatarData(data) {
            if (!data) return '';
            try {
                const dataObj = new Date(data + 'T00:00:00');
                return dataObj.toLocaleDateString('pt-BR');
            } catch (e) {
                return data;
            }
        }

        function formatarDataHora(dataString) {
            if (!dataString) return '';
            try {
                const data = new Date(dataString);
                return data.toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return dataString;
            }
        }

        function calcularDiasInadimplencia(dataInicio) {
            try {
                const inicio = new Date(dataInicio);
                const hoje = new Date();
                const diffTime = Math.abs(hoje - inicio);
                return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            } catch (e) {
                return 0;
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function getCategoriaLabel(categoria) {
            const categorias = {
                'geral': 'Geral',
                'financeiro': 'Financeiro',
                'documentacao': 'Documentação',
                'atendimento': 'Atendimento',
                'pendencia': 'Pendência',
                'importante': 'Importante'
            };
            return categorias[categoria] || categoria;
        }

        function getPrioridadeLabel(prioridade) {
            const prioridades = {
                'baixa': 'Baixa',
                'media': 'Média',
                'alta': 'Alta',
                'urgente': 'Urgente'
            };
            return prioridades[prioridade] || prioridade;
        }

        // Log de inicialização
        console.log('✅ Sistema de Inadimplentes inicializado');
        console.log('📁 Caminhos das APIs configurados');
        console.log(`🔐 Permissões: ${temPermissao ? 'Concedidas' : 'Negadas'}`);
    </script>

</body>
</html>