<?php
/**
 * Página de Serviços Comerciais - Sistema ASSEGO
 * pages/servicos_comercial.php
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
$page_title = 'Serviços Comerciais - ASSEGO';

// Verificar permissões para setor comercial - APENAS COMERCIAL E PRESIDÊNCIA
$temPermissaoComercial = false;
$motivoNegacao = '';
$isComercial = false;
$isPresidencia = false;
$departamentoUsuario = null;

error_log("=== DEBUG PERMISSÕES SERVIÇOS COMERCIAIS - RESTRITO ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Verificação de permissões: APENAS comercial (ID: 10) OU presidência (ID: 1)
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;
    
    // Debug dos testes de comparação
    error_log("Testes de comparação:");
    error_log("  deptId === '10': " . ($deptId === '10' ? 'true' : 'false'));
    error_log("  deptId === 10: " . ($deptId === 10 ? 'true' : 'false'));
    error_log("  deptId == 10: " . ($deptId == 10 ? 'true' : 'false'));
    error_log("  deptId === '1': " . ($deptId === '1' ? 'true' : 'false'));
    error_log("  deptId === 1: " . ($deptId === 1 ? 'true' : 'false'));
    error_log("  deptId == 1: " . ($deptId == 1 ? 'true' : 'false'));
    
    if ($deptId == 10) { // Comercial - comparação flexível para pegar string ou int
        $temPermissaoComercial = true;
        $isComercial = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Comercial (ID: 10)");
    } elseif ($deptId == 1) { // Presidência - comparação flexível para pegar string ou int
        $temPermissaoComercial = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } else {
        $motivoNegacao = 'Acesso restrito EXCLUSIVAMENTE ao Setor Comercial e Presidência.';
        error_log("❌ Acesso negado. Departamento: '$deptId' (tipo: " . gettype($deptId) . "). Permitido apenas: Comercial (ID: 10) ou Presidência (ID: 1)");
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Log final do resultado
if (!$temPermissaoComercial) {
    error_log("❌ ACESSO NEGADO AOS SERVIÇOS COMERCIAIS: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO - Usuário " . ($isComercial ? 'do Comercial' : 'da Presidência'));
}

// Busca estatísticas do setor comercial (apenas se tem permissão)
if ($temPermissaoComercial) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Total de associados ativos
        $sql = "SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $totalAssociadosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Novos cadastros hoje
        $sql = "SELECT COUNT(*) as hoje FROM Associados WHERE DATE(created_at) = CURDATE()";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $cadastrosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];
        
        // Pré-cadastros pendentes
        $sql = "SELECT COUNT(*) as pendentes FROM Associados WHERE pre_cadastro = 1 AND situacao = 'PENDENTE'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $preCadastrosPendentes = $stmt->fetch(PDO::FETCH_ASSOC)['pendentes'];
        
        // Solicitações de desfiliação (último mês)
        $sql = "SELECT COUNT(*) as desfiliacao FROM Auditoria 
                WHERE acao = 'UPDATE' 
                AND tabela = 'Associados'
                AND JSON_EXTRACT(valores_novos, '$.situacao') = 'DESFILIADO'
                AND data_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $desfiliacoesRecentes = $stmt->fetch(PDO::FETCH_ASSOC)['desfiliacao'];

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas comerciais: " . $e->getMessage());
        $totalAssociadosAtivos = $cadastrosHoje = $preCadastrosPendentes = $desfiliacoesRecentes = 0;
    }
} else {
    $totalAssociadosAtivos = $cadastrosHoje = $preCadastrosPendentes = $desfiliacoesRecentes = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado, // ← Passa TODO o array do usuário (como no exemplo da presidência)
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
    'notificationCount' => $preCadastrosPendentes,
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
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.15);
        }

        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
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
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
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

        /* Seção de Desfiliação */
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
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
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

        /* Seção de Cadastro */
        .cadastro-options {
            display: grid;
            gap: 1.5rem;
        }

        .cadastro-option {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 2rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .cadastro-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .cadastro-option-icon {
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

        .cadastro-option h5 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .cadastro-option p {
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
            background: linear-gradient(135deg, var(--primary-light) 0%, #e3f2fd 100%);
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

        /* Dados do associado */
        .dados-associado-container {
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
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.1);
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

        /* Ficha de Desfiliação */
        .ficha-desfiliacao-container {
            background: white;
            border-radius: 15px;
            margin-top: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            overflow: hidden;
        }

        .ficha-header-container {
            background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
            color: white;
            padding: 1.5rem 2rem;
        }

        .ficha-content {
            padding: 3rem;
        }

        .ficha-desfiliacao {
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
            color: #333;
            font-size: 14px;
        }

        .ficha-title {
            text-align: center;
            font-size: 1.6rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary);
        }

        .campo-preenchimento {
            border-bottom: 1px solid #333;
            min-width: 150px;
            display: inline-block;
            padding: 2px 8px;
            margin: 0 3px;
            font-weight: bold;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 3px;
        }

        .campo-preenchimento.largo {
            min-width: 400px;
        }

        .campo-preenchimento.medio {
            min-width: 250px;
        }

        .motivo-area {
            border: 2px solid var(--primary);
            min-height: 100px;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            background: #f8f9fa;
            font-style: italic;
        }

        .assinatura-area {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #333;
        }

        .linha-assinatura {
            border-top: 2px solid #333;
            width: 300px;
            margin: 2rem auto 1rem;
            padding-top: 0.5rem;
            font-weight: bold;
        }

        .ficha-actions {
            padding: 2rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            text-align: center;
        }

        .btn-imprimir {
            background: var(--success);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            margin-right: 1rem;
        }

        .btn-imprimir:hover {
            background: #146c43;
            transform: translateY(-2px);
        }

        .btn-gerar-pdf {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-gerar-pdf:hover {
            background: #b02a37;
            transform: translateY(-2px);
        }

        /* Toast personalizado */
        .toast-container {
            z-index: 9999;
        }

        .toast {
            min-width: 300px;
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
            
            .ficha-content {
                padding: 2rem 1.5rem;
            }
        }

        /* Modo impressão */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .ficha-desfiliacao-container {
                box-shadow: none;
                border: 2px solid #000;
            }
            
            .ficha-content {
                padding: 2rem;
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
            <?php if (!$temPermissaoComercial): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado aos Serviços Comerciais</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Requisitos para acesso:</h6>
                    <ul class="mb-0">
                        <li>Estar no <strong>Setor Comercial</strong> (Departamento ID: 10) OU</li>
                        <li>Estar na <strong>Presidência</strong> (Departamento ID: 1)</li>
                    </ul>
                    <hr class="my-2">
                    <small class="text-muted">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Atenção:</strong> Apenas funcionários destes dois departamentos específicos têm acesso aos serviços comerciais.
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
                            <li>Confirme se deveria ter acesso aos serviços comerciais</li>
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
                        <i class="fas fa-handshake"></i>
                    </div>
                    Serviços Comerciais
                    <?php if ($isComercial): ?>
                        <small class="text-muted">- Setor Comercial</small>
                    <?php elseif ($isPresidencia): ?>
                        <small class="text-muted">- Presidência</small>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    Gerencie desfiliações, cadastros de novos associados e demais serviços comerciais
                </p>
            </div>

            <!-- Alert informativo sobre o nível de acesso -->
            <div class="alert-custom alert-info-custom" data-aos="fade-up">
                <div>
                    <i class="fas fa-<?php echo $isComercial ? 'handshake' : 'crown'; ?>"></i>
                </div>
                <div>
                    <h6 class="mb-1">
                        <?php if ($isComercial): ?>
                            <i class="fas fa-handshake text-primary"></i> Setor Comercial
                        <?php elseif ($isPresidencia): ?>
                            <i class="fas fa-crown text-warning"></i> Presidência
                        <?php endif; ?>
                    </h6>
                    <small>
                        <?php if ($isComercial): ?>
                            Você tem acesso completo aos serviços comerciais: desfiliações, cadastros e atendimento.
                        <?php elseif ($isPresidencia): ?>
                            Você tem acesso administrativo aos serviços comerciais como membro da presidência.
                        <?php endif; ?>
                    </small>
                </div>
            </div>

            <!-- Seções de Serviços -->
            <div class="services-container" data-aos="fade-up" data-aos-delay="200">
                
                <!-- Seção de Desfiliação -->
                <div class="service-section">
                    <div class="service-header">
                        <h3>
                            <i class="fas fa-user-times"></i>
                            Solicitação de Desfiliação
                        </h3>
                    </div>
                    <div class="service-content" style="position: relative;">
                        <p class="text-muted mb-3">
                            Busque um associado pelo RG militar e gere automaticamente a ficha de desfiliação.
                        </p>
                        
                        <form class="busca-form" onsubmit="buscarAssociadoPorRG(event)">
                            <div class="busca-input-group">
                                <label class="form-label" for="rgBusca">RG Militar</label>
                                <input type="text" class="form-control" id="rgBusca" 
                                       placeholder="Digite o RG militar..." required>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary" id="btnBuscarRG">
                                    <i class="fas fa-search me-2"></i>
                                    Buscar Associado
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary" onclick="limparBuscaRG()">
                                    <i class="fas fa-eraser me-2"></i>
                                    Limpar
                                </button>
                            </div>
                        </form>

                        <!-- Alert para mensagens de busca -->
                        <div id="alertBusca" class="alert" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="alertBuscaText"></span>
                        </div>

                        <!-- Container para dados do associado -->
                        <div id="dadosAssociadoContainer" class="dados-associado-container fade-in" style="display: none;">
                            <h6 class="mb-3">
                                <i class="fas fa-user me-2" style="color: var(--primary);"></i>
                                Dados do Associado Encontrado
                            </h6>
                            
                            <div class="dados-grid" id="dadosAssociadoGrid">
                                <!-- Dados serão inseridos aqui dinamicamente -->
                            </div>
                        </div>

                        <!-- Loading overlay -->
                        <div id="loadingBuscaDesfiliacao" class="loading-overlay" style="display: none;">
                            <div class="loading-spinner mb-3"></div>
                            <p class="text-muted">Buscando dados do associado...</p>
                        </div>
                    </div>
                </div>

                <!-- Seção de Cadastro de Associados -->
                <div class="service-section">
                    <div class="service-header">
                        <h3>
                            <i class="fas fa-user-plus"></i>
                            Cadastro de Associados
                        </h3>
                    </div>
                    <div class="service-content">
                        <p class="text-muted mb-4">
                            Inicie novos cadastros de associados ou gerencie pré-cadastros existentes.
                        </p>
                        
                        <div class="cadastro-options">
                            <div class="cadastro-option" onclick="novoPreCadastro()">
                                <div class="cadastro-option-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <h5>Novo Cadastro</h5>
                                <p>Inicie um novo cadastro de associado com formulário completo</p>
                            </div>

                            <div class="cadastro-option" onclick="consultarAssociado()">
                                <div class="cadastro-option-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h5>Consultar Associado</h5>
                                <p>Busque e consulte dados de associados existentes</p>
                            </div>

                            <div class="cadastro-option" onclick="relatoriosComerciais()">
                                <div class="cadastro-option-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h5>Relatórios Comerciais</h5>
                                <p>Visualize estatísticas e relatórios do setor comercial</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Container para ficha de desfiliação (inicialmente oculto) -->
            <div id="fichaDesfiliacao" class="ficha-desfiliacao-container fade-in" style="display: none;" data-aos="fade-up">
                <div class="ficha-header-container no-print">
                    <h4>
                        <i class="fas fa-file-alt me-2"></i>
                        Ficha de Desfiliação - ASSEGO
                    </h4>
                    <p class="mb-0">Documento oficial preenchido automaticamente</p>
                </div>

                <div class="ficha-content">
                    <div class="ficha-desfiliacao">
                        <div class="ficha-title">
                            SOLICITAÇÃO DE DESFILIAÇÃO<br>
                            ASSEGO
                        </div>

                        <p>
                            Goiânia, <span class="campo-preenchimento" id="diaAtual"></span> de 
                            <span class="campo-preenchimento" id="mesAtual"></span> de 
                            <span class="campo-preenchimento" id="anoAtual"></span>
                        </p>

                        <br>

                        <p><strong>Prezado Sr. Presidente,</strong></p>

                        <br>

                        <p>
                            Eu, <span class="campo-preenchimento largo" id="nomeCompleto" contenteditable="true"></span>,
                            portador do RG militar: <span class="campo-preenchimento" id="rgMilitar" contenteditable="true"></span>, 
                            Instituição: <span class="campo-preenchimento medio" id="corporacao" contenteditable="true"></span>,
                            residente e domiciliado: 
                            <span class="campo-preenchimento largo" id="endereco1" contenteditable="true"></span>
                        </p>

                        <p>
                            <span class="campo-preenchimento largo" id="endereco2" contenteditable="true"></span>
                        </p>

                        <p>
                            <span class="campo-preenchimento largo" id="endereco3" contenteditable="true"></span>,
                            telefone <span class="campo-preenchimento" id="telefoneFormatado" contenteditable="true"></span>, 
                            Lotação: <span class="campo-preenchimento medio" id="lotacao" contenteditable="true"></span>,
                            solicito minha desfiliação total da Associação dos Subtenentes e Sargentos do Estado
                            de Goiás – ASSEGO, pelo motivo:
                        </p>

                        <div class="motivo-area" contenteditable="true" id="motivoDesfiliacao">
                            Clique aqui para digitar o motivo da desfiliação...
                        </div>

                        <br>

                        <p>
                            Me coloco à disposição, através do telefone informado acima para informações
                            adicionais necessárias à conclusão deste processo e, desde já, <strong>DECLARO ESTAR 
                            CIENTE QUE O PROCESSO INTERNO TEM UM PRAZO DE ATÉ 30 DIAS, A CONTAR DA 
                            DATA DE SOLICITAÇÃO, PARA SER CONCLUÍDO.</strong>
                        </p>

                        <br>

                        <p><strong>Respeitosamente,</strong></p>

                        <div class="assinatura-area">
                            <div class="linha-assinatura">
                                Assinatura do requerente
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de ação -->
                <div class="ficha-actions no-print">
                    <button class="btn-imprimir" onclick="imprimirFicha()">
                        <i class="fas fa-print me-2"></i>
                        Imprimir Ficha
                    </button>
                    <button class="btn-gerar-pdf" onclick="gerarPDFFicha()">
                        <i class="fas fa-file-pdf me-2"></i>
                        Gerar PDF
                    </button>
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
        let dadosAssociadoAtual = null;
        const temPermissao = <?php echo json_encode($temPermissaoComercial); ?>;
        const isComercial = <?php echo json_encode($isComercial); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const departamentoUsuario = <?php echo json_encode($departamentoUsuario); ?>;

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({ duration: 800, once: true });

            console.log('=== DEBUG SERVIÇOS COMERCIAIS - RESTRITO ===');
            console.log('Tem permissão:', temPermissao);
            console.log('É comercial:', isComercial);
            console.log('É presidência:', isPresidencia);
            console.log('Departamento usuário:', departamentoUsuario);

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

            preencherDataAtual();
            configurarEventos();
            configurarFichaDesfiliacao();

            // Event listener para Enter no campo RG
            $('#rgBusca').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAssociadoPorRG(e);
                }
            });

            const departamentoNome = isComercial ? 'Comercial' : isPresidencia ? 'Presidência' : 'Autorizado';
            notifications.show(`Serviços comerciais carregados - ${departamentoNome}!`, 'success', 3000);
        });

        // ===== FUNÇÃO DE DEBUG DETALHADO =====
        function mostrarDebugDetalhado() {
            const usuario = <?php echo json_encode($usuarioLogado); ?>;
            const isDiretor = <?php echo json_encode($auth->isDiretor()); ?>;
            
            let debugHtml = `
                <div class="debug-completo">
                    <h6><i class="fas fa-bug"></i> Debug Detalhado - Serviços Comerciais</h6>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Dados do Usuário:</h6>
                            <pre class="bg-light p-2 small">${JSON.stringify(usuario, null, 2)}</pre>
                        </div>
                        <div class="col-md-6">
                            <h6>Verificações de Acesso:</h6>
                            <ul class="small">
                                <li><strong>É Diretor:</strong> ${isDiretor ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Departamento ID:</strong> ${usuario.departamento_id} (tipo: ${typeof usuario.departamento_id})</li>
                                <li><strong>É Comercial (dept==10):</strong> ${usuario.departamento_id == 10 ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>É Presidência (dept==1):</strong> ${usuario.departamento_id == 1 ? 'SIM ✅' : 'NÃO ❌'}</li>
                                <li><strong>Tem Permissão Final:</strong> ${temPermissao ? 'SIM ✅' : 'NÃO ❌'}</li>
                            </ul>
                            
                            <div class="mt-3">
                                <strong>Regra de Acesso:</strong><br>
                                <code>departamento_id == 10 OU departamento_id == 1</code><br><br>
                                
                                <strong>Resultado:</strong><br>
                                <code>Comercial: ${usuario.departamento_id == 10} | Presidência: ${usuario.departamento_id == 1}</code><br>
                                <code>Final: ${usuario.departamento_id == 10 || usuario.departamento_id == 1}</code>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-shield-alt"></i> Política de Acesso</h6>
                        <p class="mb-0">
                            <strong>RESTRITO:</strong> Apenas funcionários dos departamentos <strong>Comercial (ID: 10)</strong> 
                            e <strong>Presidência (ID: 1)</strong> podem acessar os serviços comerciais.
                            <br><br>
                            <small><strong>Nota:</strong> Diretores de outros departamentos NÃO têm acesso automático.</small>
                        </p>
                    </div>
                    
                    <small class="text-muted">
                        <strong>Para corrigir acesso:</strong>
                        <br>1. Verifique o departamento_id no banco de dados
                        <br>2. Confirme se o usuário deve ter acesso aos serviços comerciais
                        <br>3. Se necessário, mova o usuário para o departamento correto
                    </small>
                </div>
            `;
            
            // Criar modal customizado
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Debug - Serviços Comerciais</h5>
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

        // ===== FUNÇÕES DE DESFILIAÇÃO =====

        // Preencher data atual
        function preencherDataAtual() {
            const hoje = new Date();
            const dia = hoje.getDate();
            const meses = [
                'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
            ];
            const mes = meses[hoje.getMonth()];
            const ano = hoje.getFullYear();

            document.getElementById('diaAtual').textContent = dia.toString().padStart(2, '0');
            document.getElementById('mesAtual').textContent = mes;
            document.getElementById('anoAtual').textContent = ano.toString();
        }

        // Buscar associado por RG
        async function buscarAssociadoPorRG(event) {
            event.preventDefault();
            
            const rgInput = document.getElementById('rgBusca');
            const rg = rgInput.value.trim();
            const btnBuscar = document.getElementById('btnBuscarRG');
            const loadingOverlay = document.getElementById('loadingBuscaDesfiliacao');
            const dadosContainer = document.getElementById('dadosAssociadoContainer');
            const fichaContainer = document.getElementById('fichaDesfiliacao');
            
            if (!rg) {
                mostrarAlertaBusca('Por favor, digite um RG para buscar.', 'danger');
                return;
            }

            // Mostra loading
            loadingOverlay.style.display = 'flex';
            btnBuscar.disabled = true;
            dadosContainer.style.display = 'none';
            fichaContainer.style.display = 'none';
            esconderAlertaBusca();

            try {
                const response = await fetch(`../api/associados/buscar_por_rg.php?rg=${encodeURIComponent(rg)}`);
                const result = await response.json();

                if (result.status === 'success') {
                    dadosAssociadoAtual = result.data;
                    exibirDadosAssociado(dadosAssociadoAtual);
                    preencherFichaDesfiliacao(dadosAssociadoAtual);
                    
                    dadosContainer.style.display = 'block';
                    fichaContainer.style.display = 'block';
                    
                    mostrarAlertaBusca('Associado encontrado! Dados carregados e ficha preenchida automaticamente.', 'success');
                    
                    // Scroll suave até os dados
                    setTimeout(() => {
                        dadosContainer.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start' 
                        });
                    }, 300);
                } else {
                    mostrarAlertaBusca(result.message, 'danger');
                }

            } catch (error) {
                console.error('Erro na busca:', error);
                mostrarAlertaBusca('Erro ao buscar associado. Verifique sua conexão.', 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                btnBuscar.disabled = false;
            }
        }

        // Exibir dados do associado
        function exibirDadosAssociado(dados) {
            const grid = document.getElementById('dadosAssociadoGrid');
            grid.innerHTML = '';

            // Função auxiliar para criar item de dados
            function criarDadosItem(label, value, icone = 'fa-info') {
                if (!value || value === 'null' || value === '') return '';
                
                return `
                    <div class="dados-item">
                        <div class="dados-label">
                            <i class="fas ${icone} me-1"></i>
                            ${label}
                        </div>
                        <div class="dados-value">${value}</div>
                    </div>
                `;
            }

            // Dados pessoais
            const pessoais = dados.dados_pessoais || {};
            grid.innerHTML += criarDadosItem('Nome Completo', pessoais.nome, 'fa-user');
            grid.innerHTML += criarDadosItem('RG Militar', pessoais.rg, 'fa-id-card');
            grid.innerHTML += criarDadosItem('CPF', formatarCPF(pessoais.cpf), 'fa-id-card');
            grid.innerHTML += criarDadosItem('Data Nascimento', formatarData(pessoais.data_nascimento), 'fa-calendar');
            grid.innerHTML += criarDadosItem('Email', pessoais.email, 'fa-envelope');
            grid.innerHTML += criarDadosItem('Telefone', formatarTelefone(pessoais.telefone), 'fa-phone');

            // Dados militares
            const militares = dados.dados_militares || {};
            grid.innerHTML += criarDadosItem('Corporação', militares.corporacao, 'fa-shield-alt');
            grid.innerHTML += criarDadosItem('Patente', militares.patente, 'fa-medal');
            grid.innerHTML += criarDadosItem('Lotação', militares.lotacao, 'fa-building');
            grid.innerHTML += criarDadosItem('Unidade', militares.unidade, 'fa-map-marker-alt');

            // Endereço
            const endereco = dados.endereco || {};
            if (endereco.endereco) {
                const enderecoCompleto = [
                    endereco.endereco,
                    endereco.numero ? `nº ${endereco.numero}` : '',
                    endereco.bairro,
                    endereco.cidade
                ].filter(Boolean).join(', ');
                
                grid.innerHTML += criarDadosItem('Endereço', enderecoCompleto, 'fa-home');
            }
            grid.innerHTML += criarDadosItem('CEP', formatarCEP(endereco.cep), 'fa-map-pin');

            // Dados financeiros
            const financeiros = dados.dados_financeiros || {};
            grid.innerHTML += criarDadosItem('Tipo Associado', financeiros.tipo_associado, 'fa-user-tag');
            grid.innerHTML += criarDadosItem('Situação Financeira', financeiros.situacao_financeira, 'fa-dollar-sign');
            
            // Contrato
            const contrato = dados.contrato || {};
            grid.innerHTML += criarDadosItem('Data Filiação', formatarData(contrato.data_filiacao), 'fa-handshake');
            
            // Status
            const statusBadge = dados.status_cadastro === 'PRE_CADASTRO' 
                ? '<span class="badge bg-warning">Pré-cadastro</span>'
                : '<span class="badge bg-success">Cadastro Definitivo</span>';
            grid.innerHTML += `
                <div class="dados-item">
                    <div class="dados-label">
                        <i class="fas fa-info-circle me-1"></i>
                        Status do Cadastro
                    </div>
                    <div class="dados-value">${statusBadge}</div>
                </div>
            `;
        }

        // Preencher ficha de desfiliação
        function preencherFichaDesfiliacao(dados) {
            // Dados pessoais
            const pessoais = dados.dados_pessoais || {};
            document.getElementById('nomeCompleto').textContent = pessoais.nome || '';
            document.getElementById('rgMilitar').textContent = pessoais.rg || '';
            document.getElementById('telefoneFormatado').textContent = formatarTelefone(pessoais.telefone) || '';

            // Dados militares
            const militares = dados.dados_militares || {};
            document.getElementById('corporacao').textContent = militares.corporacao || '';
            document.getElementById('lotacao').textContent = militares.lotacao || '';

            // Endereço
            const endereco = dados.endereco || {};
            const enderecoCompleto = montarEnderecoCompleto(endereco);
            
            // Divide o endereço em até 3 linhas
            const linhas = quebrarEnderecoEmLinhas(enderecoCompleto);
            document.getElementById('endereco1').textContent = linhas[0] || '';
            document.getElementById('endereco2').textContent = linhas[1] || '';
            document.getElementById('endereco3').textContent = linhas[2] || '';

            // Limpa o motivo para o usuário digitar
            document.getElementById('motivoDesfiliacao').textContent = '';
        }

        // Montar endereço completo
        function montarEnderecoCompleto(endereco) {
            const partes = [];
            
            if (endereco.endereco) {
                let linha = endereco.endereco;
                if (endereco.numero) linha += `, nº ${endereco.numero}`;
                if (endereco.complemento) linha += `, ${endereco.complemento}`;
                partes.push(linha);
            }
            
            if (endereco.bairro) {
                partes.push(`Bairro: ${endereco.bairro}`);
            }
            
            if (endereco.cidade) {
                let cidade = endereco.cidade;
                if (endereco.cep) cidade += ` - CEP: ${formatarCEP(endereco.cep)}`;
                partes.push(cidade);
            }
            
            return partes.join(', ');
        }

        // Quebrar endereço em linhas
        function quebrarEnderecoEmLinhas(enderecoCompleto, maxPorLinha = 60) {
            if (!enderecoCompleto) return ['', '', ''];
            
            const palavras = enderecoCompleto.split(' ');
            const linhas = [];
            let linhaAtual = '';
            
            for (const palavra of palavras) {
                if ((linhaAtual + ' ' + palavra).length <= maxPorLinha) {
                    linhaAtual += (linhaAtual ? ' ' : '') + palavra;
                } else {
                    if (linhaAtual) {
                        linhas.push(linhaAtual);
                        linhaAtual = palavra;
                    } else {
                        linhas.push(palavra);
                    }
                }
            }
            
            if (linhaAtual) linhas.push(linhaAtual);
            
            // Garante 3 linhas
            while (linhas.length < 3) {
                linhas.push('');
            }
            
            return linhas.slice(0, 3);
        }

        // Configurar ficha de desfiliação
        function configurarFichaDesfiliacao() {
            // Limpar placeholder do motivo ao clicar
            const motivoArea = document.getElementById('motivoDesfiliacao');
            
            motivoArea.addEventListener('focus', function() {
                if (this.textContent === 'Clique aqui para digitar o motivo da desfiliação...') {
                    this.textContent = '';
                }
            });

            // Restaurar placeholder se vazio
            motivoArea.addEventListener('blur', function() {
                if (this.textContent.trim() === '') {
                    this.textContent = 'Clique aqui para digitar o motivo da desfiliação...';
                }
            });
        }

        // Limpar busca por RG
        function limparBuscaRG() {
            document.getElementById('rgBusca').value = '';
            document.getElementById('dadosAssociadoContainer').style.display = 'none';
            document.getElementById('fichaDesfiliacao').style.display = 'none';
            document.getElementById('dadosAssociadoGrid').innerHTML = '';
            dadosAssociadoAtual = null;
            esconderAlertaBusca();

            // Limpa campos da ficha
            const campos = [
                'nomeCompleto', 'rgMilitar', 'corporacao', 'endereco1', 
                'endereco2', 'endereco3', 'telefoneFormatado', 'lotacao'
            ];
            
            campos.forEach(campo => {
                const elemento = document.getElementById(campo);
                if (elemento) elemento.textContent = '';
            });
            
            // Restaura placeholder do motivo
            const motivoArea = document.getElementById('motivoDesfiliacao');
            if (motivoArea) {
                motivoArea.textContent = 'Clique aqui para digitar o motivo da desfiliação...';
            }
        }

        // Mostrar alerta de busca
        function mostrarAlertaBusca(mensagem, tipo) {
            const alertDiv = document.getElementById('alertBusca');
            const alertText = document.getElementById('alertBuscaText');
            
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
                setTimeout(esconderAlertaBusca, 5000);
            }
        }

        // Esconder alerta de busca
        function esconderAlertaBusca() {
            document.getElementById('alertBusca').style.display = 'none';
        }

        // Imprimir ficha
        function imprimirFicha() {
            // Verifica se os campos obrigatórios estão preenchidos
            const nome = document.getElementById('nomeCompleto').textContent.trim();
            const rg = document.getElementById('rgMilitar').textContent.trim();
            const motivo = document.getElementById('motivoDesfiliacao').textContent.trim();
            
            if (!nome || !rg) {
                mostrarAlertaBusca('Por favor, busque um associado antes de imprimir.', 'danger');
                return;
            }
            
            if (!motivo || motivo === 'Clique aqui para digitar o motivo da desfiliação...') {
                mostrarAlertaBusca('Por favor, informe o motivo da desfiliação antes de imprimir.', 'danger');
                return;
            }
            
            window.print();
        }

        // Gerar PDF
        function gerarPDFFicha() {
            notifications.show('Funcionalidade de geração de PDF será implementada em breve.', 'info');
        }

        // ===== FUNÇÕES DE CADASTRO =====

        // Novo pré-cadastro
        function novoPreCadastro() {
            notifications.show('Redirecionando para novo pré-cadastro...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/cadastroForm.php';
            }, 1000);
        }

        // Ver pré-cadastros pendentes
        function verPreCadastrosPendentes() {
            notifications.show('Carregando pré-cadastros pendentes...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/pre_cadastros_pendentes.php';
            }, 1000);
        }

        // Consultar associado
        function consultarAssociado() {
            notifications.show('Abrindo consulta de associados...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/dashboard.php';
            }, 1000);
        }

        // Relatórios comerciais
        function relatoriosComerciais() {
            notifications.show('Carregando relatórios comerciais...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/relatorios.php';
            }, 1000);
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Configurar eventos
        function configurarEventos() {
            // Aqui podem ser adicionados outros event listeners se necessário
        }

        // Funções auxiliares de formatação
        function formatarCPF(cpf) {
            if (!cpf) return '';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length === 11) {
                return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }
            return cpf;
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

        function formatarCEP(cep) {
            if (!cep) return '';
            cep = cep.toString().replace(/\D/g, '');
            if (cep.length === 8) {
                return cep.replace(/(\d{5})(\d{3})/, "$1-$2");
            }
            return cep;
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
        console.log('✓ Sistema de Serviços Comerciais carregado com sucesso!');
        console.log(`🏢 Departamento: ${isComercial ? 'Comercial (ID: 10)' : isPresidencia ? 'Presidência (ID: 1)' : 'Desconhecido'}`);
        console.log(`🔐 Permissões: ${temPermissao ? 'Concedidas' : 'Negadas'}`);
        console.log(`📋 Acesso restrito a: Comercial (ID: 10) e Presidência (ID: 1)`);
    </script>

</body>

</html>