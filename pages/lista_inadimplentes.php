<?php
/**
 * Página de Relatório de Inadimplentes - Sistema ASSEGO
 * pages/relatorio_inadimplentes.php
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
        let dadosInadimplentes = [];
        let dadosOriginais = [];

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function () {
            AOS.init({ duration: 800, once: true });

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

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
                const response = await fetch('../api/financeiro/buscar_inadimplentes.php');
                const result = await response.json();

                if (result.status === 'success') {
                    dadosInadimplentes = result.data;
                    dadosOriginais = [...dadosInadimplentes]; // Cópia para filtros
                    exibirInadimplentes(dadosInadimplentes);
                } else {
                    throw new Error(result.message || 'Erro ao carregar inadimplentes');
                }

            } catch (error) {
                console.error('Erro ao carregar inadimplentes:', error);
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
                    <td><code>${associado.rg}</code></td>
                    <td><code>${formatarCPF(associado.cpf)}</code></td>
                    <td>
                        <a href="tel:${associado.telefone}" class="text-decoration-none">
                            ${formatarTelefone(associado.telefone)}
                        </a>
                    </td>
                    <td>${formatarData(associado.nasc)}</td>
                    <td>
                        <span class="badge bg-secondary">${associado.vinculoServidor || 'N/A'}</span>
                    </td>
                    <td>
                        <span class="badge-situacao situacao-inadimplente">
                            INADIMPLENTE
                        </span>
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

        // Aplicar filtros
        function aplicarFiltros(event) {
            event.preventDefault();

            const filtroNome = document.getElementById('filtroNome').value.toLowerCase().trim();
            const filtroRG = document.getElementById('filtroRG').value.trim();
            const filtroVinculo = document.getElementById('filtroVinculo').value;

            let dadosFiltrados = [...dadosOriginais];

            // Aplicar filtro por nome
            if (filtroNome) {
                dadosFiltrados = dadosFiltrados.filter(associado =>
                    associado.nome.toLowerCase().includes(filtroNome)
                );
            }

            // Aplicar filtro por RG
            if (filtroRG) {
                dadosFiltrados = dadosFiltrados.filter(associado =>
                    associado.rg.includes(filtroRG)
                );
            }

            // Aplicar filtro por vínculo
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

        // ===== FUNÇÕES DE AÇÕES =====

        // Ver detalhes do associado
        function verDetalhes(id) {
            const associado = dadosInadimplentes.find(a => a.id === id);
            if (!associado) {
                notifications.show('Associado não encontrado', 'error');
                return;
            }

            // Aqui você pode abrir um modal ou redirecionar para página de detalhes
            notifications.show(`Abrindo detalhes de ${associado.nome}`, 'info');
            // window.location.href = `../pages/detalhes_associado.php?id=${id}`;
        }

        // Enviar cobrança
        function enviarCobranca(id) {
            const associado = dadosInadimplentes.find(a => a.id === id);
            if (!associado) {
                notifications.show('Associado não encontrado', 'error');
                return;
            }

            // Implementar envio de cobrança
            notifications.show(`Cobrança enviada para ${associado.nome}`, 'success');
        }

        // Registrar pagamento
        function registrarPagamento(id) {
            const associado = dadosInadimplentes.find(a => a.id === id);
            if (!associado) {
                notifications.show('Associado não encontrado', 'error');
                return;
            }

            // Implementar registro de pagamento
            notifications.show(`Abrindo registro de pagamento para ${associado.nome}`, 'info');
        }

        // ===== FUNÇÕES DE EXPORTAÇÃO =====

        // Exportar para Excel
        function exportarExcel() {
            notifications.show('Gerando arquivo Excel...', 'info');
            // Implementar exportação para Excel
        }

        // Exportar para PDF
        function exportarPDF() {
            notifications.show('Gerando arquivo PDF...', 'info');
            // Implementar exportação para PDF
        }

        // Imprimir relatório
        function imprimirRelatorio() {
            window.print();
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Configurar eventos
        function configurarEventos() {
            // Enter nos campos de filtro
            document.getElementById('filtroNome').addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    aplicarFiltros(e);
                }
            });

            document.getElementById('filtroRG').addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    aplicarFiltros(e);
                }
            });
        }

        // Formatação de CPF
        function formatarCPF(cpf) {
            if (!cpf) return '';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length === 11) {
                return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }
            return cpf;
        }

        // Formatação de telefone
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

        // Formatação de data
        function formatarData(data) {
            if (!data) return '';
            try {
                const dataObj = new Date(data + 'T00:00:00');
                return dataObj.toLocaleDateString('pt-BR');
            } catch (e) {
                return data;
            }
        }

        // Log de inicialização
        console.log('✓ Relatório de Inadimplentes carregado com sucesso!');
        console.log(`🏢 Departamento: ${isFinanceiro ? 'Financeiro (ID: 5)' : isPresidencia ? 'Presidência (ID: 1)' : 'Desconhecido'}`);
        console.log(`🔐 Permissões: ${temPermissao ? 'Concedidas' : 'Negadas'}`);

        // Variável global para armazenar dados do associado atual
        let associadoAtual = null;

        // Função atualizada para ver detalhes (substitui a função existente)
        async function verDetalhes(id) {
            try {
                // Resetar modal
                resetarModal();

                // Abrir modal
                const modal = new bootstrap.Modal(document.getElementById('modalDetalhesInadimplente'));
                modal.show();

                // Buscar dados do associado
                const response = await fetch(`../api/financeiro/buscar_detalhes_inadimplente.php?id=${id}`);
                const result = await response.json();

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
                console.error('Erro ao carregar detalhes:', error);
                notifications.show('Erro ao carregar detalhes do associado', 'error');

                // Mostrar erro no modal
                document.getElementById('modalLoading').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Erro ao carregar dados: ${error.message}
            </div>
        `;
            }
        }

        // Função para resetar o modal
        function resetarModal() {
            document.getElementById('modalLoading').style.display = 'block';
            document.getElementById('modalContent').style.display = 'none';

            // Resetar tabs
            document.querySelector('#dadosPessoais-tab').click();

            // Limpar campos
            document.querySelectorAll('[id^="detalhe"]').forEach(el => {
                if (el.tagName !== 'SELECT') {
                    el.textContent = '-';
                }
            });
        }

        // Função para preencher o modal com os dados
        function preencherModal(dados) {
            // Título do modal
            document.getElementById('modalSubtitle').innerHTML = `
        <strong>${dados.nome}</strong> - ID: ${dados.id}
    `;

            // Dados Pessoais
            document.getElementById('detalheNome').textContent = dados.nome || '-';
            document.getElementById('detalheCPF').textContent = formatarCPF(dados.cpf) || '-';
            document.getElementById('detalheRG').textContent = dados.rg || '-';
            document.getElementById('detalheNascimento').textContent = formatarData(dados.nasc) || '-';
            document.getElementById('detalheSexo').textContent = dados.sexo === 'M' ? 'Masculino' : dados.sexo === 'F' ? 'Feminino' : '-';
            document.getElementById('detalheEstadoCivil').textContent = dados.estadoCivil || '-';
            document.getElementById('detalheEscolaridade').textContent = dados.escolaridade || '-';

            // Contato
            document.getElementById('detalheTelefone').innerHTML = dados.telefone ?
                `<a href="tel:${dados.telefone}">${formatarTelefone(dados.telefone)}</a>` : '-';
            document.getElementById('detalheEmail').innerHTML = dados.email ?
                `<a href="mailto:${dados.email}">${dados.email}</a>` : '-';

            // Endereço
            document.getElementById('detalheCEP').textContent = dados.cep || '-';
            document.getElementById('detalheEndereco').textContent = dados.endereco || '-';
            document.getElementById('detalheNumero').textContent = dados.numero || '-';
            document.getElementById('detalheComplemento').textContent = dados.complemento || '-';
            document.getElementById('detalheBairro').textContent = dados.bairro || '-';
            document.getElementById('detalheCidade').textContent = dados.cidade || '-';

            // Dados Financeiros
            document.getElementById('detalheTipoAssociado').textContent = dados.tipoAssociado || '-';
            document.getElementById('detalheVinculo').textContent = dados.vinculoServidor || '-';
            document.getElementById('detalheLocalDebito').textContent = dados.localDebito || '-';
            document.getElementById('detalheDoador').innerHTML = dados.doador == 1 ?
                '<span class="badge bg-success">Sim</span>' :
                '<span class="badge bg-secondary">Não</span>';

            // Dados Bancários
            document.getElementById('detalheAgencia').textContent = dados.agencia || '-';
            document.getElementById('detalheOperacao').textContent = dados.operacao || '-';
            document.getElementById('detalheContaCorrente').textContent = dados.contaCorrente || '-';

            // Observações Financeiras
            if (dados.observacoes_financeiras) {
                document.getElementById('observacoesFinanceiras').innerHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-info-circle me-2"></i>
                ${dados.observacoes_financeiras}
            </div>
        `;
            }

            // Dados Militares
            document.getElementById('detalheCorporacao').textContent = dados.corporacao || '-';
            document.getElementById('detalhePatente').textContent = dados.patente || '-';
            document.getElementById('detalheCategoria').textContent = dados.categoria || '-';
            document.getElementById('detalheLotacao').textContent = dados.lotacao || '-';
            document.getElementById('detalheUnidade').textContent = dados.unidade || '-';

            // Dependentes
            if (dados.dependentes && dados.dependentes.length > 0) {
                let dependentesHtml = '';
                dados.dependentes.forEach(dep => {
                    dependentesHtml += `
                <div class="dependente-item">
                    <div class="dependente-info">
                        <span class="dependente-nome">${dep.nome}</span>
                        <span class="dependente-detalhes">
                            ${dep.parentesco || 'Parentesco não informado'} • 
                            ${dep.data_nascimento ? formatarData(dep.data_nascimento) : 'Data não informada'}
                        </span>
                    </div>
                </div>
            `;
                });
                document.getElementById('listaDependentes').innerHTML = dependentesHtml;
            }

            // Calcular período de inadimplência
            if (dados.data_inadimplencia) {
                const diasInadimplente = calcularDiasInadimplencia(dados.data_inadimplencia);
                document.getElementById('diasInadimplencia').innerHTML = `
            <i class="fas fa-calendar-times me-1"></i>
            Inadimplente há <strong>${diasInadimplente} dias</strong>
        `;
            }

            // Valores de débito (simulado - ajustar conforme sua lógica)
            const valorMensal = 86.55; // Valor padrão, ajustar conforme necessário
            const mesesAtraso = dados.meses_atraso || 3; // Simulado
            const valorTotal = valorMensal * mesesAtraso;

            document.getElementById('valorTotalDebito').textContent = `R$ ${valorTotal.toFixed(2).replace('.', ',')}`;
            document.getElementById('mesesAtraso').textContent = mesesAtraso;
            document.getElementById('ultimaContribuicao').textContent = dados.ultima_contribuicao || 'Não identificada';

            // Carregar observações
            carregarObservacoes(dados.id);

            // Carregar histórico
            carregarHistorico(dados.id);
        }

        // Função para carregar observações
        async function carregarObservacoes(associadoId) {
            try {
                const response = await fetch(`../api/associados/buscar_observacoes.php?associado_id=${associadoId}`);
                const result = await response.json();

                if (result.status === 'success' && result.data.length > 0) {
                    let observacoesHtml = '';

                    result.data.forEach(obs => {
                        const tags = obs.tags ? obs.tags.split(',') : [];
                        let tagsHtml = '';

                        tags.forEach(tag => {
                            const classe = tag.toLowerCase() === 'importante' ? 'importante' : '';
                            tagsHtml += `<span class="observation-tag ${classe}">${tag}</span>`;
                        });

                        observacoesHtml += `
                    <div class="observation-card">
                        <div class="observation-header">
                            <span class="observation-author">
                                <i class="fas fa-user-circle me-1"></i>
                                ${obs.criado_por_nome || 'Sistema'}
                            </span>
                            <span class="observation-date">
                                <i class="fas fa-clock me-1"></i>
                                ${obs.data_formatada}
                            </span>
                        </div>
                        <div class="observation-text">${obs.observacao}</div>
                        ${tagsHtml ? `<div class="observation-tags">${tagsHtml}</div>` : ''}
                    </div>
                `;
                    });

                    document.getElementById('listaObservacoes').innerHTML = observacoesHtml;

                    // Atualizar badge
                    document.getElementById('badgeObservacoes').textContent = result.data.length;
                    document.getElementById('badgeObservacoes').style.display = 'inline-block';
                }

            } catch (error) {
                console.error('Erro ao carregar observações:', error);
            }
        }

        // Função para carregar histórico
        async function carregarHistorico(associadoId) {
            try {
                const response = await fetch(`../api/financeiro/buscar_historico_cobrancas.php?associado_id=${associadoId}`);
                const result = await response.json();

                if (result.status === 'success' && result.data.length > 0) {
                    let historicoHtml = '<div class="timeline">';

                    result.data.forEach(item => {
                        historicoHtml += `
                    <div class="timeline-item">
                        <div class="timeline-date">${formatarDataHora(item.data)}</div>
                        <div class="timeline-content">
                            <strong>${item.tipo}</strong>
                            <p class="mb-0 small">${item.descricao}</p>
                        </div>
                    </div>
                `;
                    });

                    historicoHtml += '</div>';
                    document.getElementById('historicoCobrancas').innerHTML = historicoHtml;
                }

            } catch (error) {
                console.error('Erro ao carregar histórico:', error);
            }
        }

        // Função para adicionar observação
        async function adicionarObservacao() {
            const { value: text } = await Swal.fire({
                title: 'Nova Observação',
                input: 'textarea',
                inputLabel: 'Digite a observação sobre este associado inadimplente',
                inputPlaceholder: 'Digite sua observação aqui...',
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Você precisa escrever algo!';
                    }
                }
            });

            if (text) {
                try {
                    const response = await fetch('../api/associados/adicionar_observacao.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            associado_id: associadoAtual.id,
                            observacao: text,
                            categoria: 'financeiro',
                            prioridade: 'alta',
                            importante: 1
                        })
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        notifications.show('Observação adicionada com sucesso', 'success');
                        carregarObservacoes(associadoAtual.id);
                    } else {
                        throw new Error(result.message);
                    }

                } catch (error) {
                    notifications.show('Erro ao adicionar observação', 'error');
                }
            }
        }

        // Função para enviar cobrança do modal
        async function enviarCobrancaModal() {
            if (!associadoAtual) return;

            const result = await Swal.fire({
                title: 'Enviar Cobrança',
                html: `
            <p>Confirma o envio de cobrança para:</p>
            <p><strong>${associadoAtual.nome}</strong></p>
            <p>CPF: ${formatarCPF(associadoAtual.cpf)}</p>
            <div class="mt-3">
                <label for="tipoCobranca" class="form-label">Tipo de Cobrança:</label>
                <select id="tipoCobranca" class="form-select">
                    <option value="email">E-mail</option>
                    <option value="sms">SMS</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="carta">Carta</option>
                </select>
            </div>
            <div class="mt-3">
                <label for="mensagemCobranca" class="form-label">Mensagem Adicional:</label>
                <textarea id="mensagemCobranca" class="form-control" rows="3" 
                    placeholder="Digite uma mensagem adicional (opcional)"></textarea>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: 'Enviar Cobrança',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#ffc107',
                preConfirm: () => {
                    return {
                        tipo: document.getElementById('tipoCobranca').value,
                        mensagem: document.getElementById('mensagemCobranca').value
                    };
                }
            });

            if (result.isConfirmed) {
                // Implementar envio de cobrança
                notifications.show(`Cobrança enviada via ${result.value.tipo} para ${associadoAtual.nome}`, 'success');

                // Registrar no histórico
                await registrarHistoricoCobranca(associadoAtual.id, result.value);

                // Recarregar histórico
                carregarHistorico(associadoAtual.id);
            }
        }

        // Função para registrar pagamento do modal
        async function registrarPagamentoModal() {
            if (!associadoAtual) return;

            const result = await Swal.fire({
                title: 'Registrar Pagamento',
                html: `
            <p>Registrar pagamento de:</p>
            <p><strong>${associadoAtual.nome}</strong></p>
            <div class="mt-3">
                <label for="valorPagamento" class="form-label">Valor do Pagamento:</label>
                <input type="number" id="valorPagamento" class="form-control" 
                    step="0.01" placeholder="0,00" required>
            </div>
            <div class="mt-3">
                <label for="dataPagamento" class="form-label">Data do Pagamento:</label>
                <input type="date" id="dataPagamento" class="form-control" 
                    value="${new Date().toISOString().split('T')[0]}" required>
            </div>
            <div class="mt-3">
                <label for="formaPagamento" class="form-label">Forma de Pagamento:</label>
                <select id="formaPagamento" class="form-select">
                    <option value="boleto">Boleto</option>
                    <option value="pix">PIX</option>
                    <option value="debito">Débito em Conta</option>
                    <option value="cartao">Cartão</option>
                    <option value="dinheiro">Dinheiro</option>
                </select>
            </div>
            <div class="mt-3">
                <label for="observacaoPagamento" class="form-label">Observação:</label>
                <textarea id="observacaoPagamento" class="form-control" rows="2" 
                    placeholder="Observações sobre o pagamento (opcional)"></textarea>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                preConfirm: () => {
                    const valor = document.getElementById('valorPagamento').value;
                    if (!valor || valor <= 0) {
                        Swal.showValidationMessage('Por favor, insira um valor válido');
                        return false;
                    }
                    return {
                        valor: valor,
                        data: document.getElementById('dataPagamento').value,
                        forma: document.getElementById('formaPagamento').value,
                        observacao: document.getElementById('observacaoPagamento').value
                    };
                }
            });

            if (result.isConfirmed) {
                try {
                    // Implementar registro de pagamento
                    const response = await fetch('../api/financeiro/registrar_pagamento.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            associado_id: associadoAtual.id,
                            ...result.value
                        })
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        notifications.show('Pagamento registrado com sucesso!', 'success');

                        // Fechar modal e recarregar lista
                        bootstrap.Modal.getInstance(document.getElementById('modalDetalhesInadimplente')).hide();
                        carregarInadimplentes();
                    } else {
                        throw new Error(data.message);
                    }

                } catch (error) {
                    notifications.show('Erro ao registrar pagamento', 'error');
                }
            }
        }

        // Função para registrar histórico de cobrança
        async function registrarHistoricoCobranca(associadoId, dados) {
            try {
                const response = await fetch('../api/financeiro/registrar_historico_cobranca.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        associado_id: associadoId,
                        tipo: `Cobrança via ${dados.tipo}`,
                        descricao: dados.mensagem || `Cobrança enviada via ${dados.tipo}`,
                        status: 'enviada'
                    })
                });

                return await response.json();

            } catch (error) {
                console.error('Erro ao registrar histórico:', error);
            }
        }

        // Função para imprimir detalhes
        function imprimirDetalhes() {
            if (!associadoAtual) return;

            // Criar janela de impressão
            const printWindow = window.open('', '_blank');

            // Gerar HTML para impressão
            const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Detalhes do Inadimplente - ${associadoAtual.nome}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { color: #dc3545; font-size: 24px; }
                h2 { color: #333; font-size: 18px; margin-top: 20px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
                .info-row { margin: 10px 0; }
                .info-label { font-weight: bold; display: inline-block; width: 150px; }
                .alert { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 20px 0; }
                @media print { body { padding: 0; } }
            </style>
        </head>
        <body>
            <h1>Relatório de Inadimplência</h1>
            <div class="alert">
                <strong>ASSOCIADO INADIMPLENTE</strong>
            </div>
            
            <h2>Dados Pessoais</h2>
            <div class="info-row"><span class="info-label">Nome:</span> ${associadoAtual.nome}</div>
            <div class="info-row"><span class="info-label">CPF:</span> ${formatarCPF(associadoAtual.cpf)}</div>
            <div class="info-row"><span class="info-label">RG:</span> ${associadoAtual.rg || '-'}</div>
            <div class="info-row"><span class="info-label">Telefone:</span> ${formatarTelefone(associadoAtual.telefone) || '-'}</div>
            <div class="info-row"><span class="info-label">E-mail:</span> ${associadoAtual.email || '-'}</div>
            
            <h2>Dados Financeiros</h2>
            <div class="info-row"><span class="info-label">Tipo Associado:</span> ${associadoAtual.tipoAssociado || '-'}</div>
            <div class="info-row"><span class="info-label">Vínculo:</span> ${associadoAtual.vinculoServidor || '-'}</div>
            <div class="info-row"><span class="info-label">Situação:</span> INADIMPLENTE</div>
            
            <h2>Dados Militares</h2>
            <div class="info-row"><span class="info-label">Corporação:</span> ${associadoAtual.corporacao || '-'}</div>
            <div class="info-row"><span class="info-label">Patente:</span> ${associadoAtual.patente || '-'}</div>
            <div class="info-row"><span class="info-label">Unidade:</span> ${associadoAtual.unidade || '-'}</div>
            
            <p style="margin-top: 50px; text-align: center; color: #666; font-size: 12px;">
                Documento gerado em ${new Date().toLocaleString('pt-BR')} por ${usuarioLogado.nome}
            </p>
        </body>
        </html>
    `;

            printWindow.document.write(printContent);
            printWindow.document.close();

            // Aguardar carregamento e imprimir
            printWindow.onload = function () {
                printWindow.print();
                printWindow.onafterprint = function () {
                    printWindow.close();
                };
            };
        }

        // Função para calcular dias de inadimplência
        function calcularDiasInadimplencia(dataInicio) {
            const inicio = new Date(dataInicio);
            const hoje = new Date();
            const diffTime = Math.abs(hoje - inicio);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return diffDays;
        }

        // Função para formatar data e hora
        function formatarDataHora(dataHora) {
            if (!dataHora) return '';
            try {
                const dt = new Date(dataHora);
                return dt.toLocaleString('pt-BR');
            } catch (e) {
                return dataHora;
            }
        }

        // Importar SweetAlert2 se ainda não estiver importado
        if (typeof Swal === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(script);
        }

    </script>

</body>

</html>