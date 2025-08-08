<?php
/**
 * Página de Serviços Financeiros - Sistema ASSEGO
 * pages/financeiro.php
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
$page_title = 'Serviços Financeiros - ASSEGO';

// Verificar permissões para setor financeiro - APENAS FINANCEIRO E PRESIDÊNCIA
$temPermissaoFinanceiro = false;
$motivoNegacao = '';
$isFinanceiro = false;
$isPresidencia = false;
$departamentoUsuario = null;

error_log("=== DEBUG PERMISSÕES SERVIÇOS FINANCEIROS - RESTRITO ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Verificação de permissões: APENAS financeiro (ID: 5) OU presidência (ID: 1)
// NOTA: Ajuste o ID do departamento financeiro conforme sua base de dados
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;
    
    // Debug dos testes de comparação
    error_log("Testes de comparação:");
    error_log("  deptId === '5': " . ($deptId === '5' ? 'true' : 'false'));
    error_log("  deptId === 5: " . ($deptId === 5 ? 'true' : 'false'));
    error_log("  deptId == 5: " . ($deptId == 5 ? 'true' : 'false'));
    error_log("  deptId === '1': " . ($deptId === '1' ? 'true' : 'false'));
    error_log("  deptId === 1: " . ($deptId === 1 ? 'true' : 'false'));
    error_log("  deptId == 1: " . ($deptId == 1 ? 'true' : 'false'));
    
    if ($deptId == 5) { // Financeiro - comparação flexível para pegar string ou int
        $temPermissaoFinanceiro = true;
        $isFinanceiro = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Financeiro (ID: 5)");
    } elseif ($deptId == 1) { // Presidência - comparação flexível para pegar string ou int
        $temPermissaoFinanceiro = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } else {
        $motivoNegacao = 'Acesso restrito EXCLUSIVAMENTE ao Setor Financeiro e Presidência.';
        error_log("❌ Acesso negado. Departamento: '$deptId' (tipo: " . gettype($deptId) . "). Permitido apenas: Financeiro (ID: 5) ou Presidência (ID: 1)");
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Log final do resultado
if (!$temPermissaoFinanceiro) {
    error_log("❌ ACESSO NEGADO AOS SERVIÇOS FINANCEIROS: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO - Usuário " . ($isFinanceiro ? 'do Financeiro' : 'da Presidência'));
}

// Busca estatísticas do setor financeiro (apenas se tem permissão)
if ($temPermissaoFinanceiro) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Total de associados ativos (para cálculo de arrecadação)
        $sql = "SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $totalAssociadosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Mensalidades recebidas hoje
        $sql = "SELECT COUNT(*) as hoje FROM Pagamentos WHERE DATE(data_pagamento) = CURDATE() AND status_pagamento = 'PAGO'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $pagamentosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'] ?? 0;
        
        // Associados em débito
        $sql = "SELECT COUNT(*) as inadimplentes FROM Associados a 
                LEFT JOIN Pagamentos p ON a.id = p.associado_id 
                WHERE a.situacao = 'Filiado' 
                AND (p.status_pagamento IS NULL OR p.status_pagamento = 'PENDENTE')
                AND a.situacao_financeira = 'INADIMPLENTE'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $associadosInadimplentes = $stmt->fetch(PDO::FETCH_ASSOC)['inadimplentes'] ?? 0;
        
        // Valor total arrecadado no mês atual
        $sql = "SELECT COALESCE(SUM(valor_pagamento), 0) as valor_mes FROM Pagamentos 
                WHERE YEAR(data_pagamento) = YEAR(CURDATE()) 
                AND MONTH(data_pagamento) = MONTH(CURDATE())
                AND status_pagamento = 'PAGO'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $arrecadacaoMes = $stmt->fetch(PDO::FETCH_ASSOC)['valor_mes'] ?? 0;

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas financeiras: " . $e->getMessage());
        $totalAssociadosAtivos = $pagamentosHoje = $associadosInadimplentes = $arrecadacaoMes = 0;
    }
} else {
    $totalAssociadosAtivos = $pagamentosHoje = $associadosInadimplentes = $arrecadacaoMes = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado, // ← Passa TODO o array do usuário
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'financeiro',
    'notificationCount' => $associadosInadimplentes,
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
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--primary);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .page-title-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
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
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.15);
        }

        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.info { border-left-color: var(--info); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

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

        .stat-change.positive { color: var(--success); }
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
        }

        .stat-icon.primary { background: var(--primary); color: var(--primary); }
        .stat-icon.success { background: var(--success); color: var(--success); }
        .stat-icon.warning { background: var(--warning); color: var(--warning); }
        .stat-icon.danger { background: var(--danger); color: var(--danger); }
        .stat-icon.info { background: var(--info); color: var(--info); }

        /* Seções de Serviços */
        .services-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .service-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.1);
            overflow: hidden;
        }

        .service-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem 2rem;
        }

        .service-header h3 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .service-header i {
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .service-content {
            padding: 2rem;
        }

        /* Seção de Gestão Financeira */
        .busca-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .busca-input-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
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

        /* Seções Financeiras */
        .financeiro-options {
            display: grid;
            gap: 1.5rem;
        }

        .financeiro-option {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 2rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .financeiro-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .financeiro-option-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .financeiro-option h5 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .financeiro-option p {
            color: var(--secondary);
            margin: 0;
            font-size: 0.9rem;
        }

        /* Alert personalizado */
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
        }

        .alert-custom i {
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .alert-info-custom {
            background: linear-gradient(135deg, var(--primary-light) 0%, #d4edda 100%);
            color: var(--primary-dark);
            border-left: 4px solid var(--primary);
        }

        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .alert-warning-custom {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 4px solid var(--warning);
        }

        /* Dados financeiros */
        .dados-financeiros-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            border: 2px solid #e9ecef;
        }

        .dados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .dados-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .dados-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.1);
        }

        .dados-label {
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dados-value {
            color: var(--dark);
            font-size: 1rem;
            font-weight: 500;
            word-break: break-word;
        }

        /* Loading spinner */
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border-radius: 15px;
            z-index: 1000;
        }

        /* Toast personalizado */
        .toast-container {
            z-index: 9999;
        }

        .toast {
            min-width: 300px;
        }

        /* Tabelas financeiras */
        .table-financeiro {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.1);
        }

        .table-financeiro .table {
            margin: 0;
        }

        .table-financeiro .table thead th {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
        }

        .table-financeiro .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .services-container {
                grid-template-columns: 1fr;
            }
            
            .busca-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .dados-grid {
                grid-template-columns: 1fr;
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

        /* Elementos específicos financeiros */
        .valor-monetario {
            font-weight: bold;
            color: var(--success);
        }

        .valor-debito {
            font-weight: bold;
            color: var(--danger);
        }

        .badge-situacao-financeira {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .situacao-em-dia {
            background: var(--success);
            color: white;
        }

        .situacao-inadimplente {
            background: var(--danger);
            color: white;
        }

        .situacao-pendente {
            background: var(--warning);
            color: white;
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
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado aos Serviços Financeiros</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Requisitos para acesso:</h6>
                    <ul class="mb-0">
                        <li>Estar no <strong>Setor Financeiro</strong> (Departamento ID: 5) OU</li>
                        <li>Estar na <strong>Presidência</strong> (Departamento ID: 1)</li>
                    </ul>
                    <hr class="my-2">
                    <small class="text-muted">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Atenção:</strong> Apenas funcionários destes dois departamentos específicos têm acesso aos serviços financeiros.
                    </small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Suas informações atuais:</h6>
                        <ul class="mb-0">
                            <li><strong>Nome:</strong> <?php echo htmlspecialchars($usuarioLogado['nome']); ?></li>
                            <li><strong>Cargo:</strong> <?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'N/A'); ?></li>
                            <li><strong>Departamento ID:</strong> 
                                <span class="badge bg-<?php echo isset($usuarioLogado['departamento_id']) ? 'info' : 'danger'; ?>">
                                    <?php echo $usuarioLogado['departamento_id'] ?? 'Não identificado'; ?>
                                </span>
                            </li>
                            <li><strong>É Diretor:</strong> 
                                <span class="badge bg-<?php echo $auth->isDiretor() ? 'success' : 'secondary'; ?>">
                                    <?php echo $auth->isDiretor() ? 'Sim' : 'Não'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Para resolver:</h6>
                        <ol class="mb-3">
                            <li>Verifique se você está no departamento correto no sistema</li>
                            <li>Entre em contato com o administrador se necessário</li>
                            <li>Confirme se deveria ter acesso aos serviços financeiros</li>
                        </ol>
                        
                        <div class="btn-group d-block">
                            <button class="btn btn-primary btn-sm me-2" onclick="window.location.reload()">
                                <i class="fas fa-sync me-1"></i>
                                Recarregar Página
                            </button>
                            <a href="../pages/dashboard.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>
                                Voltar ao Dashboard
                            </a>
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
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    Serviços Financeiros
                    <?php if ($isFinanceiro): ?>
                        <small class="text-muted">- Setor Financeiro</small>
                    <?php elseif ($isPresidencia): ?>
                        <small class="text-muted">- Presidência</small>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    Gerencie mensalidades, inadimplência, relatórios financeiros e arrecadação da ASSEGO
                </p>
            </div>

            <!-- Estatísticas Financeiras -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalAssociadosAtivos, 0, ',', '.'); ?></div>
                            <div class="stat-label">Associados Ativos</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-users"></i>
                                Base de contribuintes
                            </div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">R$ <?php echo number_format($arrecadacaoMes, 2, ',', '.'); ?></div>
                            <div class="stat-label">Arrecadação do Mês</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Receita atual
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($pagamentosHoje, 0, ',', '.'); ?></div>
                            <div class="stat-label">Pagamentos Hoje</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-calendar-day"></i>
                                Recebimentos diários
                            </div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card danger">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($associadosInadimplentes, 0, ',', '.'); ?></div>
                            <div class="stat-label">Associados em Débito</div>
                            <div class="stat-change negative">
                                <i class="fas fa-exclamation-triangle"></i>
                                Requer atenção
                            </div>
                        </div>
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert informativo sobre o nível de acesso -->
            <div class="alert-custom alert-info-custom" data-aos="fade-up">
                <div>
                    <i class="fas fa-<?php echo $isFinanceiro ? 'dollar-sign' : 'crown'; ?>"></i>
                </div>
                <div>
                    <h6 class="mb-1">
                        <?php if ($isFinanceiro): ?>
                            <i class="fas fa-dollar-sign text-success"></i> Setor Financeiro
                        <?php elseif ($isPresidencia): ?>
                            <i class="fas fa-crown text-warning"></i> Presidência
                        <?php endif; ?>
                    </h6>
                    <small>
                        <?php if ($isFinanceiro): ?>
                            Você tem acesso completo aos serviços financeiros: mensalidades, inadimplência e relatórios.
                        <?php elseif ($isPresidencia): ?>
                            Você tem acesso administrativo aos serviços financeiros como membro da presidência.
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <!-- Seções de Serviços -->
            <div class="services-container" data-aos="fade-up" data-aos-delay="200">
                
                <!-- Seção de Gestão de Inadimplência -->
                <div class="service-section">
                    <div class="service-header">
                        <h3>
                            <i class="fas fa-exclamation-triangle"></i>
                            Gestão de Inadimplência
                        </h3>
                    </div>
                    <div class="service-content" style="position: relative;">
                        <p class="text-muted mb-3">
                            Consulte associados em débito, gere cobranças e atualize situações financeiras.
                        </p>
                        
                        <form class="busca-form" onsubmit="buscarAssociadoPorRG(event)">
                            <div class="busca-input-group">
                                <label class="form-label" for="rgBuscaFinanceiro">RG Militar ou Nome</label>
                                <input type="text" class="form-control" id="rgBuscaFinanceiro" 
                                       placeholder="Digite o RG militar ou nome..." required>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary" id="btnBuscarFinanceiro">
                                    <i class="fas fa-search me-2"></i>
                                    Buscar Associado
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary" onclick="limparBuscaFinanceiro()">
                                    <i class="fas fa-eraser me-2"></i>
                                    Limpar
                                </button>
                            </div>
                        </form>

                        <!-- Alert para mensagens de busca -->
                        <div id="alertBuscaFinanceiro" class="alert" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="alertBuscaFinanceiroText"></span>
                        </div>

                        <!-- Container para dados financeiros do associado -->
                        <div id="dadosFinanceirosContainer" class="dados-financeiros-container fade-in" style="display: none;">
                            <h6 class="mb-3">
                                <i class="fas fa-dollar-sign me-2" style="color: var(--primary);"></i>
                                Situação Financeira do Associado
                            </h6>
                            
                            <div class="dados-grid" id="dadosFinanceirosGrid">
                                <!-- Dados serão inseridos aqui dinamicamente -->
                            </div>
                        </div>

                        <!-- Loading overlay -->
                        <div id="loadingBuscaFinanceiro" class="loading-overlay" style="display: none;">
                            <div class="loading-spinner mb-3"></div>
                            <p class="text-muted">Consultando situação financeira...</p>
                        </div>
                    </div>
                </div>

                <!-- Seção de Relatórios e Gestão -->
                <div class="service-section">
                    <div class="service-header">
                        <h3>
                            <i class="fas fa-chart-line"></i>
                            Relatórios Financeiros
                        </h3>
                    </div>
                    <div class="service-content">
                        <p class="text-muted mb-4">
                            Acesse relatórios de arrecadação, inadimplência e estatísticas financeiras.
                        </p>
                        
                        <div class="financeiro-options">
                            <div class="financeiro-option" onclick="relatorioArrecadacao()">
                                <div class="financeiro-option-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h5>Relatório de Arrecadação</h5>
                                <p>Visualize a evolução da arrecadação mensal e anual</p>
                            </div>

                            <div class="financeiro-option" onclick="listarInadimplentes()">
                                <div class="financeiro-option-icon">
                                    <i class="fas fa-list-ul"></i>
                                </div>
                                <h5>Lista de Inadimplentes</h5>
                                <p>Consulte e gerencie associados com pendências financeiras</p>
                            </div>

                            <div class="financeiro-option" onclick="extratoFinanceiro()">
                                <div class="financeiro-option-icon">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                                <h5>Extrato Financeiro</h5>
                                <p>Acompanhe entradas, saídas e saldo atual da associação</p>
                            </div>

                            <div class="financeiro-option" onclick="estatisticasFinanceiras()">
                                <div class="financeiro-option-icon">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <h5>Estatísticas Financeiras</h5>
                                <p>Dashboard completo com indicadores financeiros</p>
                            </div>
                        </div>
                    </div>
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
        let dadosFinanceirosAtual = null;
        const temPermissao = <?php echo json_encode($temPermissaoFinanceiro); ?>;
        const isFinanceiro = <?php echo json_encode($isFinanceiro); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const departamentoUsuario = <?php echo json_encode($departamentoUsuario); ?>;

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({ duration: 800, once: true });

            console.log('=== DEBUG SERVIÇOS FINANCEIROS - RESTRITO ===');
            console.log('Tem permissão:', temPermissao);
            console.log('É financeiro:', isFinanceiro);
            console.log('É presidência:', isPresidencia);
            console.log('Departamento usuário:', departamentoUsuario);

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

            configurarEventos();

            // Event listener para Enter no campo de busca
            $('#rgBuscaFinanceiro').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAssociadoPorRG(e);
                }
            });

            const departamentoNome = isFinanceiro ? 'Financeiro' : isPresidencia ? 'Presidência' : 'Autorizado';
            notifications.show(`Serviços financeiros carregados - ${departamentoNome}!`, 'success', 3000);
        });

        // ===== FUNÇÕES DE BUSCA FINANCEIRA =====

        // Buscar associado por RG para consulta financeira
        async function buscarAssociadoPorRG(event) {
            event.preventDefault();
            
            const rgInput = document.getElementById('rgBuscaFinanceiro');
            const busca = rgInput.value.trim();
            const btnBuscar = document.getElementById('btnBuscarFinanceiro');
            const loadingOverlay = document.getElementById('loadingBuscaFinanceiro');
            const dadosContainer = document.getElementById('dadosFinanceirosContainer');
            
            if (!busca) {
                mostrarAlertaBuscaFinanceiro('Por favor, digite um RG ou nome para buscar.', 'danger');
                return;
            }

            // Mostra loading
            loadingOverlay.style.display = 'flex';
            btnBuscar.disabled = true;
            dadosContainer.style.display = 'none';
            esconderAlertaBuscaFinanceiro();

            try {
                // Determina se é busca por RG ou nome
                const parametro = isNaN(busca) ? 'nome' : 'rg';
                const response = await fetch(`../api/associados/buscar_situacao_financeira.php?${parametro}=${encodeURIComponent(busca)}`);
                const result = await response.json();

                if (result.status === 'success') {
                    dadosFinanceirosAtual = result.data;
                    exibirDadosFinanceiros(dadosFinanceirosAtual);
                    
                    dadosContainer.style.display = 'block';
                    
                    mostrarAlertaBuscaFinanceiro('Dados financeiros carregados com sucesso!', 'success');
                    
                    // Scroll suave até os dados
                    setTimeout(() => {
                        dadosContainer.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start' 
                        });
                    }, 300);
                } else {
                    mostrarAlertaBuscaFinanceiro(result.message, 'danger');
                }

            } catch (error) {
                console.error('Erro na busca financeira:', error);
                mostrarAlertaBuscaFinanceiro('Erro ao consultar dados financeiros. Verifique sua conexão.', 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                btnBuscar.disabled = false;
            }
        }

        // Exibir dados financeiros do associado
        function exibirDadosFinanceiros(dados) {
            const grid = document.getElementById('dadosFinanceirosGrid');
            grid.innerHTML = '';

            // Função auxiliar para criar item de dados
            function criarDadosItemFinanceiro(label, value, icone = 'fa-info', tipo = 'normal') {
                if (!value || value === 'null' || value === '') return '';
                
                let valorFormatado = value;
                let classeValor = 'dados-value';

                if (tipo === 'monetario') {
                    valorFormatado = formatarMoeda(value);
                    classeValor += ' valor-monetario';
                } else if (tipo === 'debito') {
                    valorFormatado = formatarMoeda(value);
                    classeValor += ' valor-debito';
                } else if (tipo === 'situacao') {
                    valorFormatado = `<span class="badge-situacao-financeira situacao-${value.toLowerCase().replace(' ', '-')}">${value}</span>`;
                }
                
                return `
                    <div class="dados-item">
                        <div class="dados-label">
                            <i class="fas ${icone} me-1"></i>
                            ${label}
                        </div>
                        <div class="${classeValor}">${valorFormatado}</div>
                    </div>
                `;
            }

            // Dados pessoais
            const pessoais = dados.dados_pessoais || {};
            grid.innerHTML += criarDadosItemFinanceiro('Nome Completo', pessoais.nome, 'fa-user');
            grid.innerHTML += criarDadosItemFinanceiro('RG Militar', pessoais.rg, 'fa-id-card');
            grid.innerHTML += criarDadosItemFinanceiro('Email', pessoais.email, 'fa-envelope');
            grid.innerHTML += criarDadosItemFinanceiro('Telefone', formatarTelefone(pessoais.telefone), 'fa-phone');

            // Situação financeira
            const financeiro = dados.situacao_financeira || {};
            grid.innerHTML += criarDadosItemFinanceiro('Situação Atual', financeiro.situacao, 'fa-flag', 'situacao');
            grid.innerHTML += criarDadosItemFinanceiro('Tipo de Associado', financeiro.tipo_associado, 'fa-user-tag');
            grid.innerHTML += criarDadosItemFinanceiro('Valor Mensalidade', financeiro.valor_mensalidade, 'fa-dollar-sign', 'monetario');
            
            // Dados de débito (se houver)
            if (financeiro.valor_debito && financeiro.valor_debito > 0) {
                grid.innerHTML += criarDadosItemFinanceiro('Valor em Débito', financeiro.valor_debito, 'fa-exclamation-triangle', 'debito');
                grid.innerHTML += criarDadosItemFinanceiro('Meses em Atraso', financeiro.meses_atraso, 'fa-calendar-times');
            }

            // Último pagamento
            if (financeiro.ultimo_pagamento) {
                grid.innerHTML += criarDadosItemFinanceiro('Último Pagamento', formatarData(financeiro.ultimo_pagamento), 'fa-calendar-check');
            }

            // Data de vencimento
            if (financeiro.vencimento_proxima) {
                grid.innerHTML += criarDadosItemFinanceiro('Próximo Vencimento', formatarData(financeiro.vencimento_proxima), 'fa-calendar');
            }
        }

        // Limpar busca financeiro
        function limparBuscaFinanceiro() {
            document.getElementById('rgBuscaFinanceiro').value = '';
            document.getElementById('dadosFinanceirosContainer').style.display = 'none';
            document.getElementById('dadosFinanceirosGrid').innerHTML = '';
            dadosFinanceirosAtual = null;
            esconderAlertaBuscaFinanceiro();
        }

        // Mostrar alerta de busca financeiro
        function mostrarAlertaBuscaFinanceiro(mensagem, tipo) {
            const alertDiv = document.getElementById('alertBuscaFinanceiro');
            const alertText = document.getElementById('alertBuscaFinanceiroText');
            
            alertText.textContent = mensagem;
            
            // Remove classes anteriores
            alertDiv.className = 'alert';
            
            // Adiciona classe baseada no tipo
            switch (tipo) {
                case 'success':
                    alertDiv.classList.add('alert-success');
                    break;
                case 'danger':
                    alertDiv.classList.add('alert-danger');
                    break;
                case 'info':
                    alertDiv.classList.add('alert-info');
                    break;
                case 'warning':
                    alertDiv.classList.add('alert-warning');
                    break;
            }
            
            alertDiv.style.display = 'flex';
            
            // Auto-hide após 5 segundos se for sucesso
            if (tipo === 'success') {
                setTimeout(esconderAlertaBuscaFinanceiro, 5000);
            }
        }

        // Esconder alerta de busca financeiro
        function esconderAlertaBuscaFinanceiro() {
            document.getElementById('alertBuscaFinanceiro').style.display = 'none';
        }

        // ===== FUNÇÕES DE RELATÓRIOS =====

        // Relatório de arrecadação
        function relatorioArrecadacao() {
            notifications.show('Carregando relatório de arrecadação...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/relatorio_arrecadacao.php';
            }, 1000);
        }

        // Lista de inadimplentes
        function listarInadimplentes() {
            notifications.show('Carregando lista de inadimplentes...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/lista_inadimplentes.php';
            }, 1000);
        }

        // Extrato financeiro
        function extratoFinanceiro() {
            notifications.show('Abrindo extrato financeiro...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/extrato_financeiro.php';
            }, 1000);
        }

        // Estatísticas financeiras
        function estatisticasFinanceiras() {
            notifications.show('Carregando estatísticas financeiras...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/dashboard_financeiro.php';
            }, 1000);
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Configurar eventos
        function configurarEventos() {
            // Aqui podem ser adicionados outros event listeners se necessário
        }

        // Formatação de moeda
        function formatarMoeda(valor) {
            if (!valor || valor === 0) return 'R$ 0,00';
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
        }

        // Funções auxiliares de formatação
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

        // Log de inicialização
        console.log('✓ Sistema de Serviços Financeiros carregado com sucesso!');
        console.log(`🏢 Departamento: ${isFinanceiro ? 'Financeiro (ID: 5)' : isPresidencia ? 'Presidência (ID: 1)' : 'Desconhecido'}`);
        console.log(`🔐 Permissões: ${temPermissao ? 'Concedidas' : 'Negadas'}`);
        console.log(`📋 Acesso restrito a: Financeiro (ID: 5) e Presidência (ID: 1)`);
        console.log(`💰 Estatísticas carregadas: Arrecadação R$ ${<?php echo $arrecadacaoMes; ?>}, Inadimplentes: ${<?php echo $associadosInadimplentes; ?>}`);
    </script>

</body>

</html>