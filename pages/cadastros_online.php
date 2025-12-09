<?php
/**
 * ==================== CADASTRO ONLINE - PÁGINA DE GERENCIAMENTO ====================
 * Página de Gerenciamento de Cadastros Online - Sistema ASSEGO
 * pages/cadastros_online.php
 * 
 * Esta página exibe e gerencia todos os cadastros realizados online (pré-cadastros)
 * pelos usuários através do formulário público de cadastro.
 * ==================== CADASTRO ONLINE ====================
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';
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
$page_title = 'Cadastros Online - ASSEGO';

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
            m.corporacao,
            m.patente,
            m.lotacao,
            fpc.status as status_fluxo,
            fpc.data_envio_presidencia
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Fluxo_Pre_Cadastro fpc ON a.id = fpc.associado_id
        WHERE a.pre_cadastro = 1
        ORDER BY a.data_pre_cadastro DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $preCadastros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug - remover depois
    error_log("=== DEBUG CADASTROS ONLINE ===");
    error_log("Query executada: " . $sql);
    error_log("Número de registros encontrados: " . count($preCadastros));
    
    if (count($preCadastros) > 0) {
        error_log("Primeiro registro: " . print_r($preCadastros[0], true));
    }
    
    // Teste para verificar se existem registros na tabela
    $testStmt = $db->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN pre_cadastro = 1 THEN 1 END) as pre_cadastros FROM Associados");
    $testStmt->execute();
    $testResult = $testStmt->fetch();
    error_log("Total de associados: " . $testResult['total'] . " | Pré-cadastros: " . $testResult['pre_cadastros']);
    
} catch (Exception $e) {
    error_log("Erro ao buscar pré-cadastros: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $preCadastros = [];
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
    'notificationCount' => count($preCadastros),
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

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./estilizacao/style.css">

    <style>
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #003d94;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #343a40;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --shadow-lg: 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--gray-100);
            min-height: 100vh;
            color: var(--dark);
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 2rem;
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            background: var(--white);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
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
            color: var(--dark);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
        }

        .stats-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Table Container */
        .table-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-title i {
            color: var(--primary);
        }

        /* Custom Table Styles */
        .table-responsive {
            padding: 1.5rem;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--gray-100);
            border-bottom: 2px solid var(--gray-200);
            font-weight: 600;
            color: var(--dark);
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(0, 86, 210, 0.05);
            transform: scale(1.01);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-aguardando {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #f59e0b;
        }

        .status-enviado {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .status-aprovado {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #166534;
            border: 1px solid #22c55e;
        }

        .status-rejeitado {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .btn-complete {
            background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(40, 167, 69, 0.3);
        }

        .btn-complete:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
            color: white;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--info) 0%, #0ea5e9 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(23, 162, 184, 0.3);
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
            color: white;
        }

        .btn-back {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(100, 116, 139, 0.3);
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

        .empty-state h4 {
            color: var(--dark);
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .stats-summary {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .btn-action {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }

        /* DataTables Customization */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            padding: 0.375rem 0.5rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 8px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            border-color: var(--primary) !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Back Button -->
            <a href="../pages/comercial.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Voltar ao Comercial
            </a>

            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-laptop"></i>
                    Cadastros Online
                </h1>
                <p class="page-subtitle">
                    Gerencie e complete os pré-cadastros realizados através do portal online
                </p>

                <div class="stats-summary">
                    <div class="stat-item">
                        <i class="fas fa-list-alt text-primary"></i>
                        <span class="stat-value"><?php echo count($preCadastros); ?></span>
                        <span>Total de Pré-cadastros</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock text-warning"></i>
                        <span class="stat-value">
                            <?php 
                            $aguardando = array_filter($preCadastros, fn($p) => empty($p['status_fluxo']) || $p['status_fluxo'] == 'AGUARDANDO_DOCUMENTOS');
                            echo count($aguardando); 
                            ?>
                        </span>
                        <span>Aguardando Processamento</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-check-circle text-success"></i>
                        <span class="stat-value">
                            <?php 
                            $processados = array_filter($preCadastros, fn($p) => !empty($p['status_fluxo']) && in_array($p['status_fluxo'], ['ENVIADO_PRESIDENCIA', 'APROVADO']));
                            echo count($processados); 
                            ?>
                        </span>
                        <span>Processados</span>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-table"></i>
                        Lista de Pré-cadastros
                    </h3>
                </div>

                <div class="table-responsive">
                    <?php if (count($preCadastros) > 0): ?>
                    <table id="preCadastrosTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Corporação</th>
                                <th>Patente</th>
                                <th>Data Cadastro</th>
                                <th>Status</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preCadastros as $pre): ?>
                            <tr>
                                <td><strong>#<?php echo $pre['id']; ?></strong></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($pre['nome']); ?></div>
                                    <?php if ($pre['telefone']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-phone"></i> 
                                        <?php echo htmlspecialchars($pre['telefone']); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($pre['cpf']); ?></code>
                                    <?php if ($pre['email']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-envelope"></i> 
                                        <?php echo htmlspecialchars($pre['email']); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo $pre['corporacao'] ? htmlspecialchars($pre['corporacao']) : 'Não informado'; ?>
                                    </span>
                                </td>
                                <td><?php echo $pre['patente'] ? htmlspecialchars($pre['patente']) : '-'; ?></td>
                                <td>
                                    <?php 
                                    if ($pre['data_pre_cadastro']) {
                                        $data = new DateTime($pre['data_pre_cadastro']);
                                        echo $data->format('d/m/Y');
                                        echo '<br><small class="text-muted">' . $data->format('H:i') . '</small>';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status = $pre['status_fluxo'] ?? 'AGUARDANDO_DOCUMENTOS';
                                    $statusClass = '';
                                    $statusText = '';
                                    $statusIcon = '';
                                    
                                    switch($status) {
                                        case 'AGUARDANDO_DOCUMENTOS':
                                            $statusClass = 'status-aguardando';
                                            $statusText = 'Aguardando';
                                            $statusIcon = 'fas fa-clock';
                                            break;
                                        case 'ENVIADO_PRESIDENCIA':
                                            $statusClass = 'status-enviado';
                                            $statusText = 'Enviado';
                                            $statusIcon = 'fas fa-paper-plane';
                                            break;
                                        case 'APROVADO':
                                            $statusClass = 'status-aprovado';
                                            $statusText = 'Aprovado';
                                            $statusIcon = 'fas fa-check-circle';
                                            break;
                                        case 'REJEITADO':
                                            $statusClass = 'status-rejeitado';
                                            $statusText = 'Rejeitado';
                                            $statusIcon = 'fas fa-times-circle';
                                            break;
                                        default:
                                            $statusClass = 'status-aguardando';
                                            $statusText = 'Pendente';
                                            $statusIcon = 'fas fa-question-circle';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <i class="<?php echo $statusIcon; ?>"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="http://172.16.253.44/victor/comercial/pages/cadastroForm.php?id=<?php echo $pre['id']; ?>" 
                                       class="btn-action btn-complete" 
                                       title="Completar Cadastro"
                                       target="_blank">
                                        <i class="fas fa-edit"></i>
                                        Completar
                                    </a>
                                
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>Nenhum pré-cadastro encontrado</h4>
                        <p>Não há pré-cadastros pendentes no momento.</p>
                        <a href="../pages/comercial.php" class="btn-back">
                            <i class="fas fa-arrow-left"></i>
                            Voltar ao Comercial
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar DataTables
            if (document.getElementById('preCadastrosTable')) {
                $('#preCadastrosTable').DataTable({
                    responsive: true,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    order: [[0, 'desc']], // Ordenar por ID decrescente
                    pageLength: 25,
                    columnDefs: [
                        {
                            targets: [7], // Coluna de ações
                            orderable: false,
                            searchable: false
                        }
                    ]
                });
            }
        });

        // Função para visualizar detalhes
        function visualizarDetalhes(associadoId) {
            showToast('Carregando detalhes...', 'info');
            // Aqui você pode implementar um modal com os detalhes ou redirecionar
            setTimeout(() => {
                window.open(`../pages/dashboard.php?busca=${associadoId}`, '_blank');
            }, 500);
        }

        // Sistema de Toast
        function showToast(message, type = 'success') {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;

            const container = document.querySelector('.toast-container');
            const toastElement = document.createElement('div');
            toastElement.innerHTML = toastHTML;
            container.appendChild(toastElement.firstElementChild);

            const toast = new bootstrap.Toast(container.lastElementChild);
            toast.show();
        }

        // Confirmar ação de completar cadastro
        document.querySelectorAll('.btn-complete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const confirmed = confirm('Deseja abrir o formulário para completar este cadastro?');
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
                
                showToast('Abrindo formulário de cadastro...', 'info');
            });
        });
    </script>
</body>
</html>