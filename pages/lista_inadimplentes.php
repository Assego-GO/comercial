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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <style>
        /* Variáveis CSS */
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #1e3d6f;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
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
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.1);
            border-left: 4px solid var(--danger);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--danger);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .page-title-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--danger) 0%, #e74c3c 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .page-title-icon i {
            color: white;
            font-size: 1.8rem;
        }

        .page-subtitle {
            color: var(--secondary);
            margin: 0.5rem 0 0;
            font-size: 1rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.15);
        }

        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.info { border-left-color: var(--info); }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            font-size: 0.8rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.negative { color: var(--danger); }
        .stat-change.neutral { color: var(--info); }

        .stat-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            opacity: 0.1;
            position: absolute;
            right: 20px;
            top: 20px;
        }

        .stat-icon.danger { background: var(--danger); color: var(--danger); }
        .stat-icon.warning { background: var(--warning); color: var(--warning); }
        .stat-icon.info { background: var(--info); color: var(--info); }

        /* Filtros */
        .filtros-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.1);
        }

        .filtros-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .btn-danger {
            background: var(--danger);
            border-color: var(--danger);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--secondary);
            border-color: var(--secondary);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Tabela de Inadimplentes */
        .tabela-inadimplentes {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(220, 53, 69, 0.1);
            margin-bottom: 2rem;
        }

        .tabela-header {
            background: linear-gradient(135deg, var(--danger) 0%, #c82333 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tabela-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .tabela-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: #f8f9fa;
            color: var(--dark);
            border: none;
            padding: 1rem;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Badge para situação */
        .badge-situacao {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .situacao-inadimplente {
            background: var(--danger);
            color: white;
        }

        .situacao-filiado {
            background: var(--success);
            color: white;
        }

        /* Loading */
        .loading-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
            color: var(--secondary);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light);
            border-top: 4px solid var(--danger);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .filtros-form {
                grid-template-columns: 1fr;
            }
            
            .tabela-actions {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
        }

        /* Animações */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
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
                        <div class="stat-value"><?php echo number_format($percentualInadimplencia, 1, ',', '.'); ?>%</div>
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
        document.addEventListener('DOMContentLoaded', function() {
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
            document.getElementById('filtroNome').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    aplicarFiltros(e);
                }
            });
            
            document.getElementById('filtroRG').addEventListener('keypress', function(e) {
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
    </script>

</body>

</html>