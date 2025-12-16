<?php
/**
 * Página de Gerenciamento de Cadastros Online - Sistema ASSEGO
 * pages/cadastros_online.php
 * 
 * ✅ VERSÃO SIMPLIFICADA - Apenas Pré-cadastros e Todos Site
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once './components/header.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

$usuarioLogado = $auth->getUser();
$page_title = 'Cadastros Online - ASSEGO';

// BUSCAR PRÉ-CADASTROS DO SISTEMA
$preCadastros = [];
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.cpf,
            a.telefone,
            a.email,
            a.data_pre_cadastro,
            a.situacao,
            a.pre_cadastro,
            a.observacao_aprovacao,
            m.corporacao,
            m.patente,
            m.lotacao,
            fpc.status as status_fluxo,
            fpc.data_envio_presidencia,
            fpc.observacoes as fluxo_observacoes,
            DATEDIFF(NOW(), a.data_pre_cadastro) as dias_pre_cadastro
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Fluxo_Pre_Cadastro fpc ON a.id = fpc.associado_id
        WHERE a.pre_cadastro = 1
            AND a.nome IS NOT NULL
            AND a.nome != ''
            AND a.cpf IS NOT NULL
            AND a.cpf != ''
            AND a.associado_titular_id IS NULL
        ORDER BY a.data_pre_cadastro DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $preCadastros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro ao buscar pré-cadastros: " . $e->getMessage());
    $preCadastros = [];
}

// Separar origem (importados do site vs manual)
$preCadastrosImportados = array_filter($preCadastros, function($p) {
    return strpos($p['observacao_aprovacao'] ?? '', 'Importado do cadastro online') !== false;
});

$preCadastrosManuais = array_filter($preCadastros, function($p) {
    return strpos($p['observacao_aprovacao'] ?? '', 'Importado do cadastro online') === false;
});

$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
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

    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <?php $headerComponent->renderCSS(); ?>

    <style>
        :root {
            --primary: #0056d2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--gray-100);
            min-height: 100vh;
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 2rem;
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        .loading-overlay.active { display: flex; }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* Header */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--info));
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stats-summary {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .btn-back, .btn-refresh {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-back {
            background: linear-gradient(135deg, #64748b, #475569);
            color: white;
        }

        .btn-refresh {
            background: linear-gradient(135deg, var(--primary), #003d94);
            color: white;
        }

        .btn-back:hover, .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            color: white;
        }

        /* Tabs */
        .nav-tabs-custom {
            border-bottom: 2px solid var(--gray-200);
            margin-bottom: 2rem;
            display: flex;
            gap: 0.5rem;
        }

        .nav-tab-custom {
            padding: 1rem 2rem;
            border: none;
            background: transparent;
            color: var(--gray-600);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-tab-custom:hover {
            color: var(--primary);
            background: rgba(0, 86, 210, 0.05);
        }

        .nav-tab-custom.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(0, 86, 210, 0.08);
        }

        .tab-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .tab-content-area {
            display: none;
        }

        .tab-content-area.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--gray-100), var(--gray-200));
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-responsive {
            padding: 1.5rem;
        }

        .table thead th {
            background: var(--gray-100);
            font-weight: 600;
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            border-bottom: 2px solid var(--gray-200);
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(0, 86, 210, 0.05);
        }

        /* Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin: 0.25rem;
        }

        .badge-pm {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .badge-bm {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .badge-patente {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #3730a3;
            border: 1px solid #6366f1;
        }

        .badge-aguardando {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .badge-enviado {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .badge-aprovado {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
            border: 1px solid #22c55e;
        }

        .badge-rejeitado {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* Buttons */
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin: 0.25rem;
            cursor: pointer;
        }

        .btn-complete {
            background: linear-gradient(135deg, #ffc107, #ffb300);
            color: #000;
            box-shadow: 0 4px 14px rgba(255, 193, 7, 0.3);
        }

        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
            color: #000;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--danger), #c82333);
            color: white;
            box-shadow: 0 4px 14px rgba(220, 53, 69, 0.3);
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
            color: white;
        }

        .btn-import {
            background: linear-gradient(135deg, var(--success), #20c997);
            color: white;
            box-shadow: 0 4px 14px rgba(40, 167, 69, 0.3);
        }

        .btn-import:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            color: white;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--info), #138496);
            color: white;
            box-shadow: 0 4px 14px rgba(23, 162, 184, 0.3);
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        /* Alert */
        .alert-origem {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .alert-origem-site {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-origem-manual {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        @media (max-width: 768px) {
            .content-area { padding: 1rem; }
            .page-title { font-size: 1.5rem; }
            .stats-summary { flex-direction: column; }
        }
    </style>
</head>

<body>
    <!-- Loading -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <p class="text-white mt-3">Processando...</p>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 99999;"></div>

    <!-- Modal Recusar Cadastro -->
    <div class="modal fade" id="modalRecusar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-trash-alt me-2"></i>
                        Recusar Cadastro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Atenção!</strong> Esta ação é irreversível. O cadastro será excluído permanentemente.
                    </div>
                    
                    <p><strong>Cadastro:</strong> <span id="recusarNome"></span></p>
                    <p><strong>CPF:</strong> <span id="recusarCpf"></span></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo da Recusa *</label>
                        <select class="form-select" id="recusarMotivo" required>
                            <option value="">Selecione...</option>
                            <option value="Dados Falsos">Dados Falsos</option>
                            <option value="CPF Inválido">CPF Inválido</option>
                            <option value="Documentos Falsos">Documentos Falsos</option>
                            <option value="Duplicado">Cadastro Duplicado</option>
                            <option value="Não é PM/BM">Não é PM/BM</option>
                            <option value="Teste">Cadastro de Teste</option>
                            <option value="Outros">Outros</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Observações</label>
                        <textarea class="form-control" id="recusarObservacao" rows="3" placeholder="Detalhes adicionais..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmarRecusa()">
                        <i class="fas fa-trash-alt me-1"></i> Confirmar Recusa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ver Dados do Site -->
    <div class="modal fade" id="modalVerDados" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        Dados Completos do Site
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalVerDadosBody">
                    <!-- Conteúdo dinâmico -->
                </div>
            </div>
        </div>
    </div>

    <div class="main-wrapper">
        <?php $headerComponent->render(); ?>

        <div class="content-area">
            <!-- Action Bar -->
            <div class="action-bar">
                <a href="comercial.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </a>
                <button class="btn-refresh" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i>
                    Atualizar
                </button>
            </div>

            <!-- Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-laptop"></i>
                    Cadastros Online
                </h1>
                <p class="text-muted mb-0">Gerencie pré-cadastros e cadastros do site público</p>

                <div class="stats-summary">
                    <div class="stat-item">
                        <i class="fas fa-users text-primary"></i>
                        <span class="stat-value"><?php echo count($preCadastros); ?></span>
                        <span>Total Pré-cadastros</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-globe text-info"></i>
                        <span class="stat-value"><?php echo count($preCadastrosImportados); ?></span>
                        <span>Do Site</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-hand-paper text-warning"></i>
                        <span class="stat-value"><?php echo count($preCadastrosManuais); ?></span>
                        <span>Manuais</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-database text-success"></i>
                        <span class="stat-value" id="statTodosSite">-</span>
                        <span>Total no Site</span>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="nav-tabs-custom">
                <button class="nav-tab-custom active" onclick="trocarAba('precadastros')" id="tab-precadastros">
                    <i class="fas fa-edit"></i>
                    Pré-cadastros Sistema
                    <span class="tab-badge"><?php echo count($preCadastros); ?></span>
                </button>
                <button class="nav-tab-custom" onclick="trocarAba('todosSite')" id="tab-todosSite">
                    <i class="fas fa-globe"></i>
                    Todos do Site
                    <span class="tab-badge" id="badge-todosSite">0</span>
                </button>
            </div>

            <!-- ABA: PRÉ-CADASTROS -->
            <div class="tab-content-area active" id="content-precadastros">
                <?php if (count($preCadastrosImportados) > 0): ?>
                <div class="alert-origem alert-origem-site">
                    <i class="fas fa-globe me-2"></i>
                    <strong><?php echo count($preCadastrosImportados); ?> cadastros</strong> importados do site público
                </div>
                <?php endif; ?>

                <?php if (count($preCadastrosManuais) > 0): ?>
                <div class="alert-origem alert-origem-manual">
                    <i class="fas fa-hand-paper me-2"></i>
                    <strong><?php echo count($preCadastrosManuais); ?> cadastros</strong> criados manualmente no sistema
                </div>
                <?php endif; ?>

                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-user-edit"></i>
                            Pré-cadastros para Completar
                        </h3>
                    </div>

                    <div class="table-responsive">
                        <?php if (count($preCadastros) > 0): ?>
                        <table id="tablePreCadastros" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação</th>
                                    <th>Patente</th>
                                    <th>Telefone</th>
                                    <th>Data</th>
                                    <th>Dias</th>
                                    <th>Status</th>
                                    <th>Origem</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($preCadastros as $pre): ?>
                                <?php 
                                    $doSite = strpos($pre['observacao_aprovacao'] ?? '', 'Importado do cadastro online') !== false;
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $pre['id']; ?></strong></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($pre['nome']); ?></div>
                                        <?php if ($pre['situacao']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($pre['situacao']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($pre['cpf']); ?></code></td>
                                    <td>
                                        <?php if ($pre['corporacao']): ?>
                                            <span class="status-badge badge-<?php echo strtolower($pre['corporacao']); ?>">
                                                <i class="fas fa-<?php echo $pre['corporacao'] === 'PM' ? 'shield-alt' : 'fire'; ?>"></i>
                                                <?php echo $pre['corporacao']; ?>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pre['patente']): ?>
                                            <span class="status-badge badge-patente">
                                                <i class="fas fa-star"></i>
                                                <?php echo htmlspecialchars($pre['patente']); ?>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pre['telefone']): ?>
                                            <i class="fas fa-phone text-primary"></i>
                                            <?php echo htmlspecialchars($pre['telefone']); ?>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo $pre['data_pre_cadastro'] ? date('d/m/Y H:i', strtotime($pre['data_pre_cadastro'])) : '-'; ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $dias = intval($pre['dias_pre_cadastro'] ?? 0);
                                        $corDias = $dias > 7 ? 'bg-danger' : ($dias > 3 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <span class="badge <?php echo $corDias; ?>">
                                            <i class="fas fa-clock"></i> <?php echo $dias; ?> <?php echo $dias === 1 ? 'dia' : 'dias'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $pre['status_fluxo'] ?? 'AGUARDANDO_DOCUMENTOS';
                                        switch ($status) {
                                            case 'AGUARDANDO_DOCUMENTOS':
                                                echo '<span class="status-badge badge-aguardando"><i class="fas fa-hourglass-half"></i> Aguardando</span>';
                                                break;
                                            case 'ENVIADO_PRESIDENCIA':
                                                echo '<span class="status-badge badge-enviado"><i class="fas fa-paper-plane"></i> Enviado</span>';
                                                break;
                                            case 'ASSINADO':
                                            case 'APROVADO':
                                                echo '<span class="status-badge badge-aprovado"><i class="fas fa-check-circle"></i> Aprovado</span>';
                                                break;
                                            case 'REJEITADO':
                                                echo '<span class="status-badge badge-rejeitado"><i class="fas fa-times-circle"></i> Rejeitado</span>';
                                                break;
                                            default:
                                                echo '<span class="status-badge badge-aguardando"><i class="fas fa-clock"></i> Pendente</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($doSite): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-globe"></i> Site
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-hand-paper"></i> Manual
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="cadastroForm.php?id=<?php echo $pre['id']; ?>" 
                                           class="btn-action btn-complete" 
                                           target="_blank">
                                            <i class="fas fa-edit"></i>
                                            Completar
                                        </a>
                                        <?php if ($doSite): ?>
                                        <button class="btn-action btn-delete" 
                                                onclick="abrirModalRecusar(<?php echo $pre['id']; ?>, '<?php echo htmlspecialchars($pre['nome'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($pre['cpf']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                            Recusar
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>Nenhum pré-cadastro</h4>
                            <p>Não há pré-cadastros pendentes no sistema.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ABA: TODOS SITE -->
            <div class="tab-content-area" id="content-todosSite">
                <div class="table-container">
                    <div class="table-header">
                        <h3 class="table-title">
                            <i class="fas fa-database"></i>
                            Todos os Cadastros do Site Público
                        </h3>
                    </div>

                    <div class="table-responsive">
                        <table id="tableTodosSite" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Corporação/Patente</th>
                                    <th>Telefone</th>
                                    <th>Data Cadastro</th>
                                    <th>Aguardando</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tableBodyTodosSite">
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="loading-spinner mx-auto mb-3"></div>
                                        <p class="text-muted">Carregando cadastros do site...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <?php $headerComponent->renderJS(); ?>

    <script>
        let dataTables = {};
        let cadastrosSite = [];
        let associadoParaRecusar = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar tabela de pré-cadastros
            <?php if (count($preCadastros) > 0): ?>
            dataTables.precadastros = $('#tablePreCadastros').DataTable({
                responsive: true,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[7, 'desc']], // Ordenar por dias
                pageLength: 25
            });
            <?php endif; ?>

            // Carregar cadastros do site
            carregarCadastrosSite();
        });

        function trocarAba(aba) {
            document.querySelectorAll('.nav-tab-custom').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + aba).classList.add('active');
            
            document.querySelectorAll('.tab-content-area').forEach(c => c.classList.remove('active'));
            document.getElementById('content-' + aba).classList.add('active');
        }

        function carregarCadastrosSite() {
            showLoading();

            $.ajax({
                url: '../api/proxy_cadastros_online.php?todos=1',
                method: 'GET',
                dataType: 'json',
                timeout: 30000,
                success: function(response) {
                    hideLoading();

                    if (response.status === 'success' && response.data) {
                        cadastrosSite = response.data.cadastros || [];
                        
                        $('#statTodosSite').text(response.data.estatisticas?.total_geral || cadastrosSite.length);
                        $('#badge-todosSite').text(cadastrosSite.length);
                        
                        renderizarTabelaSite();
                    } else {
                        exibirErro('Erro ao carregar dados: ' + (response.message || 'Desconhecido'));
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    
                    let msg = 'Erro ao conectar com o site';
                    try {
                        const err = JSON.parse(xhr.responseText);
                        msg = err.message || msg;
                    } catch (e) {}
                    
                    exibirErro(msg);
                }
            });
        }

        function renderizarTabelaSite() {
            const tbody = $('#tableBodyTodosSite');
            tbody.empty();

            if (cadastrosSite.length === 0) {
                tbody.html(`
                    <tr><td colspan="9" class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>Nenhum cadastro</h4>
                        <p>Não há cadastros no site ainda.</p>
                    </td></tr>
                `);
                return;
            }

            cadastrosSite.forEach(cad => {
                const statusBadge = cad.importado == 1
                    ? '<span class="status-badge badge-aprovado"><i class="fas fa-check"></i> IMPORTADO</span>'
                    : '<span class="status-badge badge-aguardando"><i class="fas fa-clock"></i> PENDENTE</span>';

                let dadosMilitares = '';
                if (cad.corporacao) {
                    const badgeClass = cad.corporacao === 'PM' ? 'badge-pm' : 'badge-bm';
                    dadosMilitares += `<span class="status-badge ${badgeClass}"><i class="fas fa-${cad.corporacao === 'PM' ? 'shield-alt' : 'fire'}"></i> ${cad.corporacao}</span>`;
                }
                if (cad.patente) {
                    dadosMilitares += `<br><span class="status-badge badge-patente"><i class="fas fa-star"></i> ${cad.patente}</span>`;
                }
                if (!dadosMilitares) dadosMilitares = '<small class="text-muted">-</small>';

                const dataCad = cad.data_cadastro 
                    ? new Date(cad.data_cadastro).toLocaleString('pt-BR', {
                        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                    })
                    : '-';

                const diasAg = cad.dias_aguardando || 0;
                const corDias = diasAg > 7 ? 'danger' : diasAg > 3 ? 'warning' : 'success';

                let acoes = '';
                if (cad.importado == 1) {
                    // Já importado
                    if (cad.observacao_importacao) {
                        const match = cad.observacao_importacao.match(/ID:\s*(\d+)/i);
                        if (match) {
                            acoes = `<button class="btn-action btn-view" onclick="window.open('cadastroForm.php?id=${match[1]}', '_blank')">
                                <i class="fas fa-eye"></i> Ver
                            </button>`;
                        }
                    }
                } else {
                    // Pendente - Importar ou Recusar
                    acoes = `
                        <button class="btn-action btn-import" onclick="importarCadastro(${cad.id}, '${htmlEscape(cad.nome)}')">
                            <i class="fas fa-download"></i> Importar
                        </button>
                        <button class="btn-action btn-delete" onclick="abrirModalRecusarSite(${cad.id}, '${htmlEscape(cad.nome)}', '${htmlEscape(cad.cpf)}')">
                            <i class="fas fa-trash-alt"></i> Recusar
                        </button>
                    `;
                }

                const row = `
                    <tr>
                        <td><strong>#${cad.id}</strong></td>
                        <td>${statusBadge}</td>
                        <td>
                            <div class="fw-semibold">${htmlEscape(cad.nome)}</div>
                            <small class="text-muted">RG: ${htmlEscape(cad.rg) || '-'}</small>
                        </td>
                        <td><code>${htmlEscape(cad.cpf_formatado || cad.cpf)}</code></td>
                        <td>${dadosMilitares}</td>
                        <td>
                            ${cad.telefone ? `<i class="fas fa-phone text-primary"></i> ${htmlEscape(cad.telefone)}` : '-'}
                            ${cad.email ? `<br><small class="text-muted"><i class="fas fa-envelope"></i> ${htmlEscape(cad.email)}</small>` : ''}
                        </td>
                        <td><small>${dataCad}</small></td>
                        <td><span class="badge bg-${corDias}"><i class="fas fa-clock"></i> ${diasAg} dia${diasAg !== 1 ? 's' : ''}</span></td>
                        <td class="text-center">${acoes}</td>
                    </tr>
                `;
                tbody.append(row);
            });

            if (dataTables.todosSite) dataTables.todosSite.destroy();
            dataTables.todosSite = $('#tableTodosSite').DataTable({
                responsive: true,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[0, 'desc']],
                pageLength: 25
            });
        }

        function importarCadastro(id, nome) {
            if (!confirm(`Importar cadastro de "${nome}"?`)) return;

            showLoading();

            $.ajax({
                url: '../api/cadastros_online_importar.php',
                method: 'POST',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                success: function(resp) {
                    hideLoading();

                    if (resp.status === 'success') {
                        showToast(`✅ Importado!\nID: ${resp.associado_id}`, 'success');
                        setTimeout(() => {
                            window.open(`cadastroForm.php?id=${resp.associado_id}`, '_blank');
                            location.reload();
                        }, 1500);
                    } else {
                        showToast('❌ ' + resp.message, 'danger');
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    let msg = 'Erro ao importar';
                    try {
                        const err = JSON.parse(xhr.responseText);
                        msg = err.message || msg;
                    } catch (e) {}
                    showToast('❌ ' + msg, 'danger');
                }
            });
        }

        function abrirModalRecusar(id, nome, cpf) {
            associadoParaRecusar = { id, nome, cpf, tipo: 'sistema' };
            $('#recusarNome').text(nome);
            $('#recusarCpf').text(cpf);
            $('#recusarMotivo').val('');
            $('#recusarObservacao').val('');
            new bootstrap.Modal(document.getElementById('modalRecusar')).show();
        }

        function abrirModalRecusarSite(id, nome, cpf) {
            associadoParaRecusar = { id, nome, cpf, tipo: 'site' };
            $('#recusarNome').text(nome);
            $('#recusarCpf').text(cpf);
            $('#recusarMotivo').val('');
            $('#recusarObservacao').val('');
            new bootstrap.Modal(document.getElementById('modalRecusar')).show();
        }

        function confirmarRecusa() {
            const motivo = $('#recusarMotivo').val();
            const obs = $('#recusarObservacao').val();

            if (!motivo) {
                showToast('⚠️ Selecione o motivo da recusa', 'warning');
                return;
            }

            if (!associadoParaRecusar) return;

            const modal = bootstrap.Modal.getInstance(document.getElementById('modalRecusar'));
            modal.hide();

            showLoading();

            $.ajax({
                url: '../api/recusar_cadastro_online.php',
                method: 'POST',
                data: JSON.stringify({
                    id: associadoParaRecusar.id,
                    tipo: associadoParaRecusar.tipo,
                    motivo: motivo,
                    observacao: obs
                }),
                contentType: 'application/json',
                success: function(resp) {
                    hideLoading();

                    if (resp.status === 'success') {
                        showToast('✅ Cadastro recusado e excluído com sucesso!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('❌ ' + resp.message, 'danger');
                    }
                },
                error: function(xhr) {
                    hideLoading();
                    let msg = 'Erro ao recusar cadastro';
                    try {
                        const err = JSON.parse(xhr.responseText);
                        msg = err.message || msg;
                    } catch (e) {}
                    showToast('❌ ' + msg, 'danger');
                }
            });
        }

        function exibirErro(msg) {
            const tbody = $('#tableBodyTodosSite');
            tbody.html(`
                <tr><td colspan="9" class="empty-state">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <h4>Erro ao carregar</h4>
                    <p class="text-danger">${msg}</p>
                    <button class="btn-refresh" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Tentar Novamente
                    </button>
                </td></tr>
            `);
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function showToast(message, type = 'success') {
            const bgClass = type === 'success' ? 'bg-success' : 
                           type === 'danger' ? 'bg-danger' : 
                           type === 'warning' ? 'bg-warning' : 'bg-info';

            const toast = `
                <div class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body" style="white-space: pre-line;">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;

            const container = document.querySelector('.toast-container');
            const el = document.createElement('div');
            el.innerHTML = toast;
            container.appendChild(el.firstElementChild);

            const bsToast = new bootstrap.Toast(container.lastElementChild, { delay: type === 'success' ? 5000 : 8000 });
            bsToast.show();
        }

        function htmlEscape(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    </script>
</body>
</html>