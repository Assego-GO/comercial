<?php
/**
 * Página de Controle de Dependentes - Sistema ASSEGO
 * pages/dependentes_18anos.php
 * 
 * Controla dependentes que completaram ou estão prestes a completar 18 anos
 * para verificação de situação de pagamento de mensalidade
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
$page_title = 'Controle de Dependentes - 18 Anos - ASSEGO';

// Verificar permissões para controle de dependentes - FINANCEIRO, PRESIDÊNCIA E DIRETORIA
$temPermissaoControle = false;
$motivoNegacao = '';
$isFinanceiro = false;
$isPresidencia = false;
$isDiretoria = false;
$departamentoUsuario = null;

error_log("=== DEBUG PERMISSÕES CONTROLE DEPENDENTES - AMPLIADO ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Verificação de permissões: financeiro (ID: 5), presidência (ID: 1) OU diretoria
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;
    
    if ($deptId == 5) { // Financeiro
        $temPermissaoControle = true;
        $isFinanceiro = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Financeiro (ID: 5)");
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoControle = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } elseif ($auth->isDiretor()) { // Qualquer diretor
        $temPermissaoControle = true;
        $isDiretoria = true;
        error_log("✅ Permissão concedida: Usuário é Diretor");
    } else {
        $motivoNegacao = 'Acesso restrito ao Setor Financeiro, Presidência e Diretoria.';
        error_log("❌ Acesso negado. Departamento: '$deptId' (tipo: " . gettype($deptId) . "). Permitido: Financeiro (ID: 5), Presidência (ID: 1) ou Diretores");
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Inicializar variáveis das estatísticas
$totalDependentesFilhos = 0;
$dependentesJaCompletaram = 0;
$dependentesEsteMes = 0;
$dependentesProximosMeses = 0;
$erroCarregamentoStats = null;

// Busca estatísticas de dependentes (apenas se tem permissão)
if ($temPermissaoControle) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // === QUERY CORRIGIDA BASEADA NOS SEUS DADOS ===
        $sqlEstatisticas = "
            SELECT 
                COUNT(*) as total_filhos,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) >= 18 
                    THEN 1 ELSE 0 
                END) as ja_completaram_18,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 18
                    AND YEAR(DATE_ADD(data_nascimento, INTERVAL 18 YEAR)) = YEAR(CURDATE()) 
                    AND MONTH(DATE_ADD(data_nascimento, INTERVAL 18 YEAR)) = MONTH(CURDATE())
                    THEN 1 ELSE 0 
                END) as completam_este_mes,
                SUM(CASE 
                    WHEN TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 18
                    AND DATE_ADD(data_nascimento, INTERVAL 18 YEAR) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                    THEN 1 ELSE 0 
                END) as proximos_3_meses
            FROM Dependentes 
            WHERE parentesco LIKE '%Filho%'
            AND data_nascimento IS NOT NULL
            AND data_nascimento != '0000-00-00'
        ";
        
        $stmt = $db->prepare($sqlEstatisticas);
        $stmt->execute();
        $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($estatisticas) {
            $totalDependentesFilhos = (int)($estatisticas['total_filhos'] ?? 0);
            $dependentesJaCompletaram = (int)($estatisticas['ja_completaram_18'] ?? 0);
            $dependentesEsteMes = (int)($estatisticas['completam_este_mes'] ?? 0);
            $dependentesProximosMeses = (int)($estatisticas['proximos_3_meses'] ?? 0);
            
            error_log("✅ Estatísticas carregadas com sucesso:");
            error_log("   Total filhos: $totalDependentesFilhos");
            error_log("   Já completaram 18: $dependentesJaCompletaram");
            error_log("   Completam este mês: $dependentesEsteMes");
            error_log("   Próximos 3 meses: $dependentesProximosMeses");
        }

    } catch (Exception $e) {
        $erroCarregamentoStats = $e->getMessage();
        error_log("❌ Erro ao buscar estatísticas de dependentes: " . $e->getMessage());
        error_log("❌ Trace: " . $e->getTraceAsString());
        
        // Manter valores zero em caso de erro
        $totalDependentesFilhos = 0;
        $dependentesJaCompletaram = 0;
        $dependentesEsteMes = 0;
        $dependentesProximosMeses = 0;
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'dependentes',
    'notificationCount' => $dependentesJaCompletaram,
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
            border-left: 4px solid var(--warning);
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
            background: linear-gradient(135deg, var(--warning) 0%, #e67e22 100%);
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

        .alert-warning-custom {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 4px solid var(--warning);
        }

        .alert-error-custom {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        /* Filtros de Pesquisa */
        .filtros-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--info);
        }

        .filtros-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .filtro-group {
            flex: 1;
            min-width: 200px;
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
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
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

        /* Tabela de Dependentes */
        .dependentes-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            overflow: hidden;
        }

        .dependentes-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dependentes-header h3 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .dependentes-header i {
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .table-dependentes {
            margin: 0;
        }

        .table-dependentes thead th {
            background: #f8f9fa;
            color: var(--dark);
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table-dependentes tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-dependentes tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Badges de Status */
        .badge-idade {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .idade-critica {
            background: var(--danger);
            color: white;
        }

        .idade-atencao {
            background: var(--warning);
            color: white;
        }

        .idade-normal {
            background: var(--success);
            color: white;
        }

        .badge-parentesco {
            background: var(--info);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
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

        /* Botões de ação */
        .btn-acao {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            margin: 0 0.25rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.3s ease;
        }

        .btn-contato {
            background: var(--info);
            color: white;
            border: 1px solid var(--info);
        }

        .btn-contato:hover {
            background: #0f7989;
            color: white;
            transform: translateY(-1px);
        }

        .btn-detalhes {
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }

        .btn-detalhes:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
        }

        /* Refresh button */
        .btn-refresh {
            background: var(--success);
            color: white;
            border: 1px solid var(--success);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-refresh:hover {
            background: #218838;
            color: white;
            transform: translateY(-2px);
        }

        /* ===== ESTILOS DE PAGINAÇÃO ===== */
        .pagination-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-top: 1rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            border-left: 4px solid var(--primary);
        }

        .pagination-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .registros-info {
            color: var(--secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .registros-por-pagina {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .registros-por-pagina label {
            font-size: 0.9rem;
            color: var(--secondary);
            margin: 0;
        }

        .registros-por-pagina select {
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            border: 2px solid #e9ecef;
            font-size: 0.9rem;
            min-width: 80px;
        }

        .registros-por-pagina select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 210, 0.25);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination-nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .pagination-nav .page-item {
            margin: 0;
        }

        .pagination-nav .page-link {
            padding: 0.5rem 0.75rem;
            border: none;
            background: transparent;
            color: var(--secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            border-right: 1px solid #e9ecef;
            position: relative;
            min-width: 40px;
            text-align: center;
        }

        .pagination-nav .page-item:last-child .page-link {
            border-right: none;
        }

        .pagination-nav .page-link:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
        }

        .pagination-nav .page-item.active .page-link {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        .pagination-nav .page-item.disabled .page-link {
            color: #c6c6c6;
            cursor: not-allowed;
            background: #f8f9fa;
        }

        .pagination-nav .page-item.disabled .page-link:hover {
            background: #f8f9fa;
            color: #c6c6c6;
            transform: none;
        }

        .pagination-summary {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .pagination-summary .summary-text {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .pagination-summary .summary-highlight {
            font-weight: 600;
            color: var(--primary);
        }

        /* Inputs de navegação rápida */
        .quick-nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
        }

        .quick-nav input {
            width: 60px;
            padding: 0.375rem 0.5rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            text-align: center;
            font-size: 0.9rem;
        }

        .quick-nav input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 210, 0.25);
        }

        .quick-nav button {
            padding: 0.375rem 0.75rem;
            border: 2px solid var(--primary);
            border-radius: 6px;
            background: var(--primary);
            color: white;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .quick-nav button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        /* Responsivo para paginação */
        @media (max-width: 768px) {
            .pagination-top {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .pagination-info {
                justify-content: center;
                flex-direction: column;
                gap: 1rem;
            }
            
            .pagination-controls {
                justify-content: center;
            }
            
            .pagination-nav .page-link {
                padding: 0.375rem 0.5rem;
                font-size: 0.8rem;
                min-width: 35px;
            }
            
            .quick-nav {
                margin-left: 0;
                margin-top: 0.5rem;
                justify-content: center;
            }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .filtros-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .btn-acao {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
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

        /* Elementos específicos */
        .idade-anos {
            font-weight: bold;
        }

        .data-nascimento {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .nome-responsavel {
            font-weight: 600;
            color: var(--primary);
        }

        .contato-info {
            color: var(--secondary);
            font-size: 0.9rem;
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
            <?php if (!$temPermissaoControle): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado ao Controle de Dependentes</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Requisitos para acesso:</h6>
                    <ul class="mb-0">
                        <li>Estar no <strong>Setor Financeiro</strong> (Departamento ID: 5) OU</li>
                        <li>Estar na <strong>Presidência</strong> (Departamento ID: 1) OU</li>
                        <li>Ser <strong>Diretor</strong> de qualquer departamento</li>
                    </ul>
                </div>
                
                <div class="btn-group d-block mt-3">
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
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="page-title">
                            <div class="page-title-icon">
                                <i class="fas fa-birthday-cake"></i>
                            </div>
                            Controle de Dependentes - 18 Anos
                            <?php if ($isFinanceiro): ?>
                                <small class="text-muted">- Setor Financeiro</small>
                            <?php elseif ($isPresidencia): ?>
                                <small class="text-muted">- Presidência</small>
                            <?php elseif ($isDiretoria): ?>
                                <small class="text-muted">- Diretoria</small>
                            <?php endif; ?>
                        </h1>
                        <p class="page-subtitle">
                            Monitore dependentes filhos(as) que completaram ou estão prestes a completar 18 anos para controle de mensalidade
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($erroCarregamentoStats): ?>
            <!-- Alert de erro nas estatísticas -->
            <div class="" data-aos="fade-up">
                <div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Alert informativo sobre o controle -->
            <div class="alert-custom alert-warning-custom" data-aos="fade-up">
                <div>
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h6 class="mb-1">
                        <i class="fas fa-info-circle text-warning"></i> Importante - Controle de Dependentes
                    </h6>
                    <small>
                        Dependentes filhos(as) que completam 18 anos devem começar a pagar mensalidade própria ou informar situação comercial. 
                        Entre em contato com os responsáveis para regularização.
                    </small>
                </div>
            </div>

            <!-- Filtros de Pesquisa -->
            <div class="filtros-container" data-aos="fade-up" data-aos-delay="100">
                <h5 class="mb-3">
                    <i class="fas fa-filter me-2"></i>
                    Filtros de Pesquisa
                </h5>
                
                <form class="filtros-form" onsubmit="filtrarDependentes(event)">
                    <div class="filtro-group">
                        <label class="form-label" for="filtroSituacao">Situação</label>
                        <select class="form-select" id="filtroSituacao">
                            <option value="todos">Todos</option>
                            <option value="ja_completaram" selected>Já completaram 18 anos</option>
                            <option value="este_mes">Completam este mês</option>
                            <option value="proximos_3_meses">Próximos 3 meses</option>
                            <option value="proximos_6_meses">Próximos 6 meses</option>
                        </select>
                    </div>
                    
                    <div class="filtro-group">
                        <label class="form-label" for="filtroBusca">Buscar por nome</label>
                        <input type="text" class="form-control" id="filtroBusca" 
                               placeholder="Nome do dependente ou responsável...">
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary" id="btnFiltrar">
                            <i class="fas fa-search me-2"></i>
                            Filtrar
                        </button>
                    </div>
                    
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="limparFiltros()">
                            <i class="fas fa-eraser me-2"></i>
                            Limpar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Dependentes -->
            <div class="dependentes-container" data-aos="fade-up" data-aos-delay="200">
                <div class="dependentes-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Lista de Dependentes
                        <small id="modoPaginacao" style="font-size: 0.7rem; opacity: 0.8; margin-left: 1rem;"></small>
                    </h3>
                </div>
                
                <div class="table-responsive" style="position: relative;">
                    <table class="table table-dependentes" id="tabelaDependentes">
                        <thead>
                            <tr>
                                <th>Dependente</th>
                                <th>Idade</th>
                                <th>Data Nascimento</th>
                                <th>Parentesco</th>
                                <th>Responsável</th>
                                <th>Contato</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="corpoTabelaDependentes">
                            <!-- Dados serão carregados via JavaScript -->
                        </tbody>
                    </table>
                    
                    <!-- Loading overlay -->
                    <div id="loadingDependentes" class="loading-overlay">
                        <div class="loading-spinner mb-3"></div>
                        <p class="text-muted">Carregando dependentes...</p>
                    </div>
                </div>
            </div>

            <!-- Container de Paginação -->
            <div class="pagination-container" id="paginationContainer" style="display: none;" data-aos="fade-up" data-aos-delay="300">
                <!-- Top: Informações e controles -->
                <div class="pagination-top">
                    <div class="pagination-info">
                        <div class="registros-info">
                            <i class="fas fa-info-circle me-1"></i>
                            <span id="registrosInfo">Aguardando carregamento...</span>
                        </div>
                        
                        <div class="registros-por-pagina">
                            <label for="registrosPorPagina">Registros por página:</label>
                            <select id="registrosPorPagina" onchange="alterarRegistrosPorPagina()">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="pagination-controls">
                        <!-- Navegação principal -->
                        <ul class="pagination-nav" id="paginationNav">
                            <!-- Exemplo inicial - será substituído pelo JavaScript -->
                            <li class="page-item disabled">
                                <a class="page-link" href="#">«</a>
                            </li>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">‹</a>
                            </li>
                            <li class="page-item active">
                                <a class="page-link" href="#">1</a>
                            </li>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">›</a>
                            </li>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">»</a>
                            </li>
                        </ul>
                        
                        <!-- Navegação rápida -->
                        <div class="quick-nav">
                            <span style="font-size: 0.9rem; color: var(--secondary);">Ir para:</span>
                            <input type="number" id="paginaRapida" min="1" placeholder="1">
                            <button type="button" onclick="irParaPagina()">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Bottom: Resumo -->
                <div class="pagination-summary">
                    <div class="summary-text">
                        Mostrando 
                        <span class="summary-highlight" id="registroInicio">0</span> -
                        <span class="summary-highlight" id="registroFim">0</span> de
                        <span class="summary-highlight" id="totalRegistros">0</span> registros
                        em <span class="summary-highlight" id="totalPaginas">0</span> páginas
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

        // ===== CLASSE DE PAGINAÇÃO =====
        class PaginationManager {
            constructor() {
                this.paginaAtual = 1;
                this.registrosPorPagina = 10;
                this.totalRegistros = 0;
                this.totalPaginas = 0;
                this.container = document.getElementById('paginationContainer');
                this.nav = document.getElementById('paginationNav');
            }

            // Atualizar informações de paginação
            atualizarPaginacao(dadosPaginacao) {
                this.paginaAtual = dadosPaginacao.pagina_atual;
                this.registrosPorPagina = dadosPaginacao.registros_por_pagina;
                this.totalRegistros = dadosPaginacao.total_registros;
                this.totalPaginas = dadosPaginacao.total_paginas;

                this.atualizarElementos();
                this.criarNavegacao();
                // Mostrar paginação apenas se houver registros
                this.container.style.display = this.totalRegistros > 0 ? 'block' : 'none';
            }

            // Atualizar elementos informativos
            atualizarElementos() {
                const inicio = (this.paginaAtual - 1) * this.registrosPorPagina + 1;
                const fim = Math.min(this.paginaAtual * this.registrosPorPagina, this.totalRegistros);

                document.getElementById('registrosInfo').textContent = 
                    `${this.totalRegistros} ${this.totalRegistros === 1 ? 'registro encontrado' : 'registros encontrados'}`;
                
                document.getElementById('registroInicio').textContent = inicio;
                document.getElementById('registroFim').textContent = fim;
                document.getElementById('totalRegistros').textContent = this.totalRegistros;
                document.getElementById('totalPaginas').textContent = this.totalPaginas;
                
                // Atualizar select de registros por página
                document.getElementById('registrosPorPagina').value = this.registrosPorPagina;
                
                // Atualizar input de navegação rápida
                document.getElementById('paginaRapida').max = this.totalPaginas;
                document.getElementById('paginaRapida').placeholder = this.paginaAtual;
            }

            // Criar navegação de páginas
            criarNavegacao() {
                const nav = this.nav;
                nav.innerHTML = '';

                // Botão Primeira
                this.adicionarBotao(nav, '«', 1, this.paginaAtual === 1, 'Primeira página');
                
                // Botão Anterior
                this.adicionarBotao(nav, '‹', this.paginaAtual - 1, this.paginaAtual === 1, 'Página anterior');

                // Páginas numéricas
                const inicioRange = Math.max(1, this.paginaAtual - 2);
                const fimRange = Math.min(this.totalPaginas, this.paginaAtual + 2);

                // Primeira página se não estiver no range
                if (inicioRange > 1) {
                    this.adicionarBotao(nav, 1, 1);
                    if (inicioRange > 2) {
                        this.adicionarEllipsis(nav);
                    }
                }

                // Range de páginas
                for (let i = inicioRange; i <= fimRange; i++) {
                    this.adicionarBotao(nav, i, i, false, '', i === this.paginaAtual);
                }

                // Última página se não estiver no range
                if (fimRange < this.totalPaginas) {
                    if (fimRange < this.totalPaginas - 1) {
                        this.adicionarEllipsis(nav);
                    }
                    this.adicionarBotao(nav, this.totalPaginas, this.totalPaginas);
                }

                // Botão Próxima
                this.adicionarBotao(nav, '›', this.paginaAtual + 1, this.paginaAtual === this.totalPaginas, 'Próxima página');
                
                // Botão Última
                this.adicionarBotao(nav, '»', this.totalPaginas, this.paginaAtual === this.totalPaginas, 'Última página');
            }

            // Adicionar botão de navegação
            adicionarBotao(nav, texto, pagina, disabled = false, titulo = '', ativo = false) {
                const li = document.createElement('li');
                li.className = `page-item ${disabled ? 'disabled' : ''} ${ativo ? 'active' : ''}`;

                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = texto;
                a.title = titulo;

                if (!disabled && !ativo) {
                    a.onclick = (e) => {
                        e.preventDefault();
                        this.irParaPagina(pagina);
                    };
                }

                li.appendChild(a);
                nav.appendChild(li);
            }

            // Adicionar ellipsis
            adicionarEllipsis(nav) {
                const li = document.createElement('li');
                li.className = 'page-item disabled';

                const span = document.createElement('span');
                span.className = 'page-link';
                span.textContent = '...';

                li.appendChild(span);
                nav.appendChild(li);
            }

            // Ir para página específica
            irParaPagina(pagina) {
                if (pagina >= 1 && pagina <= this.totalPaginas && pagina !== this.paginaAtual) {
                    this.paginaAtual = pagina;
                    
                    // Verificar se estamos em modo de teste
                    if (window.dadosTestePaginacao) {
                        carregarDadosTestePagina();
                    }
                    // Verificar se temos dados completos para paginação local
                    else if (window.dadosCompletos) {
                        carregarDadosLocaisPagina();
                    }
                    // Senão, fazer nova requisição à API
                    else {
                        carregarDependentes();
                    }
                }
            }

            // Alterar registros por página
            alterarRegistrosPorPagina(novoValor) {
                this.registrosPorPagina = novoValor;
                this.paginaAtual = 1; // Resetar para primeira página
                
                // Verificar se estamos em modo de teste
                if (window.dadosTestePaginacao) {
                    carregarDadosTestePagina();
                }
                // Verificar se temos dados completos para paginação local
                else if (window.dadosCompletos) {
                    carregarDadosLocaisPagina();
                }
                // Senão, fazer nova requisição à API
                else {
                    carregarDependentes();
                }
            }
        }

        // ===== VARIÁVEIS GLOBAIS =====
        const notifications = new NotificationSystem();
        const pagination = new PaginationManager();
        let dependentesAtual = [];
        let dadosTestePaginacao = null; // Para armazenar dados de teste
        const temPermissao = <?php echo json_encode($temPermissaoControle); ?>;
        const isFinanceiro = <?php echo json_encode($isFinanceiro); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const isDiretoria = <?php echo json_encode($isDiretoria); ?>;
        const departamentoUsuario = <?php echo json_encode($departamentoUsuario); ?>;

        // Dados das estatísticas carregadas do PHP
        const estatisticasIniciais = {
            totalFilhos: <?php echo $totalDependentesFilhos; ?>,
            jaCompletaram: <?php echo $dependentesJaCompletaram; ?>,
            esteMes: <?php echo $dependentesEsteMes; ?>,
            proximosMeses: <?php echo $dependentesProximosMeses; ?>
        };

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({ duration: 800, once: true });

            console.log('=== DEBUG CONTROLE DEPENDENTES - CORRIGIDO ===');
            console.log('Tem permissão:', temPermissao);
            console.log('É financeiro:', isFinanceiro);
            console.log('É presidência:', isPresidencia);
            console.log('É diretoria:', isDiretoria);
            console.log('Departamento usuário:', departamentoUsuario);
            console.log('Estatísticas carregadas:', estatisticasIniciais);

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

            // CRÍTICO: Inicializar paginação ANTES de qualquer carregamento
            inicializarPaginacaoSegura();
            
            configurarEventos();
            
            // Aguardar um pouco para garantir que tudo esteja inicializado
            setTimeout(() => {
                carregarDependentesComPaginacao();
            }, 100);

            const tipoUsuario = isFinanceiro ? 'Financeiro' : isPresidencia ? 'Presidência' : isDiretoria ? 'Diretoria' : 'Autorizado';
            notifications.show(`Controle de dependentes carregado - ${tipoUsuario}!`, 'success', 3000);

            // Exibir resumo das estatísticas carregadas
            if (estatisticasIniciais.totalFilhos > 0) {
                notifications.show(
                    `${estatisticasIniciais.totalFilhos} dependentes encontrados: ${estatisticasIniciais.jaCompletaram} já com 18+, ${estatisticasIniciais.esteMes} este mês, ${estatisticasIniciais.proximosMeses} próximos 3 meses`, 
                    'info', 
                    5000
                );
            }
        });

        // Inicializar paginação com proteção total
        function inicializarPaginacaoSegura() {
            console.log('🔒 Inicializando paginação segura...');
            
            // Forçar valores iniciais seguros
            pagination.paginaAtual = 1;
            pagination.registrosPorPagina = 10;
            pagination.totalRegistros = 0;
            pagination.totalPaginas = 0;
            
            const dadosIniciais = {
                pagina_atual: 1,
                registros_por_pagina: 10,
                total_registros: 0,
                total_paginas: 0
            };
            
            pagination.atualizarPaginacao(dadosIniciais);
            
            // Mostrar indicador de carregamento
            document.getElementById('modoPaginacao').textContent = '(Carregando...)';
            
            console.log('✅ Paginação segura inicializada:', dadosIniciais);
        }

        // Carregar dependentes COM paginação obrigatória
        function carregarDependentesComPaginacao() {
            console.log('🔄 Iniciando carregamento COM paginação obrigatória');
            console.log('📊 Parâmetros atuais:', {
                pagina: pagination.paginaAtual,
                registros_por_pagina: pagination.registrosPorPagina
            });
            
            carregarDependentes();
        }

        // ===== FUNÇÕES DE CARREGAMENTO =====

        // Carregar lista de dependentes
        async function carregarDependentes() {
            const loadingOverlay = document.getElementById('loadingDependentes');
            const corpoTabela = document.getElementById('corpoTabelaDependentes');
            
            loadingOverlay.style.display = 'flex';
            
            try {
                const filtroSituacao = document.getElementById('filtroSituacao').value;
                const filtroBusca = document.getElementById('filtroBusca').value;
                
                const params = new URLSearchParams({
                    situacao: filtroSituacao,
                    busca: filtroBusca,
                    pagina: pagination.paginaAtual,
                    registros_por_pagina: pagination.registrosPorPagina
                });
                
                console.log('🔄 Carregando dependentes com parâmetros:', {
                    situacao: filtroSituacao,
                    busca: filtroBusca,
                    pagina: pagination.paginaAtual,
                    registros_por_pagina: pagination.registrosPorPagina
                });
                
                const response = await fetch(`../api/dependentes/listar_18anos.php?${params}`);
                const result = await response.json();

                console.log('📊 Resposta da API:', result);

                if (result.status === 'success') {
                    let dependentesRecebidos = result.data.dependentes || [];
                    
                    // Se a API não implementou paginação ainda, simular aqui
                    let dadosPaginacao = result.data.paginacao;
                    
                    if (!dadosPaginacao && dependentesRecebidos.length > 0) {
                        console.log('⚠️ API sem paginação - implementando paginação local');
                        // Simular paginação local
                        const totalRegistros = dependentesRecebidos.length;
                        const totalPaginas = Math.ceil(totalRegistros / pagination.registrosPorPagina);
                        const inicio = (pagination.paginaAtual - 1) * pagination.registrosPorPagina;
                        const fim = inicio + pagination.registrosPorPagina;
                        
                        // Fatiar os dados para a página atual
                        dependentesAtual = dependentesRecebidos.slice(inicio, fim);
                        
                        dadosPaginacao = {
                            pagina_atual: pagination.paginaAtual,
                            registros_por_pagina: pagination.registrosPorPagina,
                            total_registros: totalRegistros,
                            total_paginas: totalPaginas
                        };
                        
                        // Armazenar dados completos para paginação local
                        window.dadosCompletos = dependentesRecebidos;
                        
                        // Atualizar indicador de modo
                        document.getElementById('modoPaginacao').textContent = '(Paginação Local)';
                        
                        console.log('📄 Paginação local criada:', dadosPaginacao);
                        console.log(`📊 Mostrando ${dependentesAtual.length} de ${totalRegistros} registros`);
                    } else if (dadosPaginacao) {
                        // API com paginação implementada
                        dependentesAtual = dependentesRecebidos;
                        // Atualizar indicador de modo
                        document.getElementById('modoPaginacao').textContent = '(Paginação API)';
                        console.log('📄 Paginação da API:', dadosPaginacao);
                    } else {
                        // Nenhum dado
                        dependentesAtual = [];
                        dadosPaginacao = {
                            pagina_atual: 1,
                            registros_por_pagina: pagination.registrosPorPagina,
                            total_registros: 0,
                            total_paginas: 0
                        };
                        // Limpar indicador de modo
                        document.getElementById('modoPaginacao').textContent = '';
                        console.log('📄 Nenhum dado encontrado');
                    }
                    
                    // Atualizar interface
                    pagination.atualizarPaginacao(dadosPaginacao);
                    exibirDependentes(dependentesAtual);
                    
                    const totalEncontrados = dadosPaginacao.total_registros;
                    if (totalEncontrados > 0) {
                        notifications.show(
                            `${totalEncontrados} ${totalEncontrados === 1 ? 'dependente encontrado' : 'dependentes encontrados'}`, 
                            'info', 
                            3000
                        );
                    }
                } else {
                    throw new Error(result.message || 'Erro desconhecido');
                }

            } catch (error) {
                console.error('❌ Erro ao carregar dependentes:', error);
                corpoTabela.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                            Erro ao carregar dados: ${error.message}
                        </td>
                    </tr>
                `;
                
                // Resetar paginação em caso de erro
                const dadosVazios = {
                    pagina_atual: 1,
                    registros_por_pagina: pagination.registrosPorPagina,
                    total_registros: 0,
                    total_paginas: 0
                };
                pagination.atualizarPaginacao(dadosVazios);
                
                // Limpar indicador de modo
                document.getElementById('modoPaginacao').textContent = '';
                
                notifications.show('Erro ao carregar dependentes', 'error');
            } finally {
                loadingOverlay.style.display = 'none';
            }
        }

        // Exibir dependentes na tabela
        function exibirDependentes(dependentes) {
            const corpoTabela = document.getElementById('corpoTabelaDependentes');
            
            if (dependentes.length === 0) {
                corpoTabela.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Nenhum dependente encontrado com os filtros aplicados
                        </td>
                    </tr>
                `;
                return;
            }
            
            const linhas = dependentes.map(dep => {
                const badgeIdade = getBadgePrioridade(dep.prioridade);
                const dataFormatada = formatarData(dep.data_nascimento);
                const telefoneFormatado = formatarTelefone(dep.telefone_responsavel);
                
                return `
                    <tr>
                        <td>
                            <div class="fw-bold">${dep.nome_dependente}</div>
                            <small class="text-muted">ID: ${dep.dependente_id}</small>
                        </td>
                        <td>
                            <span class="badge-idade ${badgeIdade.classe}">
                                ${dep.idade_atual} anos
                            </span>
                            <div class="mt-1">
                                <small class="text-muted">${dep.status}</small>
                            </div>
                        </td>
                        <td>
                            <div class="data-nascimento">${dataFormatada}</div>
                            <small class="text-muted">18 anos: ${formatarData(dep.data_18_anos)}</small>
                        </td>
                        <td>
                            <span class="badge-parentesco">${dep.parentesco}</span>
                        </td>
                        <td>
                            <div class="nome-responsavel">${dep.nome_responsavel}</div>
                            <small class="text-muted">RG: ${dep.rg_responsavel}</small>
                        </td>
                        <td>
                            <div class="contato-info">
                                ${telefoneFormatado ? `<i class="fas fa-phone me-1"></i>${telefoneFormatado}` : '<span class="text-muted">Sem telefone</span>'}
                            </div>
                            <div class="contato-info">
                                ${dep.email_responsavel ? `<i class="fas fa-envelope me-1"></i>${dep.email_responsavel}` : '<span class="text-muted">Sem email</span>'}
                            </div>
                        </td>
                        <td>
                            ${dep.telefone_responsavel ? 
                                `<a href="tel:${dep.telefone_responsavel}" class="btn-acao btn-contato" title="Ligar">
                                    <i class="fas fa-phone"></i>
                                </a>` : 
                                `<span class="btn-acao" style="opacity: 0.3;" title="Sem telefone">
                                    <i class="fas fa-phone-slash"></i>
                                </span>`
                            }
                            <button class="btn-acao btn-detalhes" onclick="verDetalhes('${dep.dependente_id}')" title="Ver detalhes">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
            
            corpoTabela.innerHTML = linhas;
        }

        // ===== FUNÇÕES DE FILTRO =====

        // Filtrar dependentes
        function filtrarDependentes(event) {
            event.preventDefault();
            pagination.paginaAtual = 1; // Resetar para primeira página
            
            // Limpar dados locais ao aplicar novos filtros
            window.dadosCompletos = null;
            window.dadosTestePaginacao = null;
            
            carregarDependentes();
        }

        // Limpar filtros
        function limparFiltros() {
            document.getElementById('filtroSituacao').value = 'ja_completaram';
            document.getElementById('filtroBusca').value = '';
            pagination.paginaAtual = 1; // Resetar para primeira página
            
            // Limpar dados locais ao limpar filtros
            window.dadosCompletos = null;
            window.dadosTestePaginacao = null;
            
            carregarDependentes();
        }

        // ===== FUNÇÕES DE PAGINAÇÃO =====

        // Alterar registros por página
        function alterarRegistrosPorPagina() {
            const novoValor = parseInt(document.getElementById('registrosPorPagina').value);
            pagination.alterarRegistrosPorPagina(novoValor);
            notifications.show(`Exibindo ${novoValor} registros por página`, 'info', 2000);
        }

        // Ir para página específica
        function irParaPagina() {
            const input = document.getElementById('paginaRapida');
            const pagina = parseInt(input.value);
            
            if (pagina && pagina >= 1 && pagina <= pagination.totalPaginas) {
                pagination.irParaPagina(pagina);
                input.value = '';
                notifications.show(`Navegando para página ${pagina}`, 'info', 2000);
            } else {
                notifications.show(`Digite um número entre 1 e ${pagination.totalPaginas}`, 'warning', 3000);
                input.focus();
            }
        }

        // Limpar dados de teste e voltar ao modo normal
        function limparDadosTeste() {
            window.dadosTestePaginacao = null;
            window.dadosCompletos = null;
            pagination.paginaAtual = 1;
            document.getElementById('modoPaginacao').textContent = '';
            notifications.show('Voltando ao modo normal...', 'info', 2000);
            carregarDependentes();
        }

        // ===== FUNÇÕES DE AÇÃO =====

        // Função para testar paginação com dados fictícios
        function testarPaginacao() {
            notifications.show('Gerando dados de teste para paginação...', 'info', 2000);
            
            // Gerar dados fictícios mais extensos
            const nomes = ['João', 'Maria', 'Pedro', 'Ana', 'Carlos', 'Lucia', 'Rafael', 'Beatriz', 'Fernando', 'Camila'];
            const sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Costa', 'Ferreira', 'Lima', 'Pereira', 'Rodrigues', 'Almeida', 'Nascimento'];
            const dependentesFicticios = [];
            
            for (let i = 1; i <= 87; i++) {
                const nome = nomes[Math.floor(Math.random() * nomes.length)];
                const sobrenome = sobrenomes[Math.floor(Math.random() * sobrenomes.length)];
                const idade = Math.floor(Math.random() * 3) + 17; // 17, 18 ou 19 anos
                const prioridades = ['critica', 'alta', 'media', 'baixa'];
                const prioridade = prioridades[Math.floor(Math.random() * prioridades.length)];
                
                dependentesFicticios.push({
                    dependente_id: i.toString(),
                    nome_dependente: `${nome} ${sobrenome} Teste`,
                    idade_atual: idade,
                    data_nascimento: `200${7-idade}-${String(Math.floor(Math.random() * 12) + 1).padStart(2, '0')}-${String(Math.floor(Math.random() * 28) + 1).padStart(2, '0')}`,
                    data_18_anos: `202${5+(idade-17)}-${String(Math.floor(Math.random() * 12) + 1).padStart(2, '0')}-${String(Math.floor(Math.random() * 28) + 1).padStart(2, '0')}`,
                    status: idade >= 18 ? 'Já completou 18 anos' : 'Fará 18 anos em breve',
                    prioridade: prioridade,
                    parentesco: Math.random() > 0.5 ? 'Filho' : 'Filha',
                    nome_responsavel: `Responsável ${sobrenome}`,
                    rg_responsavel: `${Math.floor(Math.random() * 90000000) + 10000000}-${Math.floor(Math.random() * 9) + 1}`,
                    telefone_responsavel: Math.random() > 0.3 ? `119${Math.floor(Math.random() * 100000000).toString().padStart(8, '0')}` : '',
                    email_responsavel: Math.random() > 0.4 ? `contato${i}@email.com` : '',
                    situacao_associado: 'Ativo'
                });
            }
            
            // Armazenar dados de teste globalmente
            window.dadosTestePaginacao = dependentesFicticios;
            
            // Simular paginação com dados extensos
            const totalRegistros = dependentesFicticios.length;
            const registrosPorPagina = pagination.registrosPorPagina;
            const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
            
            const dadosPaginacao = {
                pagina_atual: pagination.paginaAtual,
                registros_por_pagina: registrosPorPagina,
                total_registros: totalRegistros,
                total_paginas: totalPaginas
            };
            
            // Obter registros da página atual
            const inicio = (pagination.paginaAtual - 1) * registrosPorPagina;
            const fim = inicio + registrosPorPagina;
            const dependentesPagina = dependentesFicticios.slice(inicio, fim);
            
            // Atualizar interface
            pagination.atualizarPaginacao(dadosPaginacao);
            exibirDependentes(dependentesPagina);
            dependentesAtual = dependentesPagina;
            
            // Atualizar indicador de modo
            document.getElementById('modoPaginacao').textContent = '(Dados de Teste)';
            
            notifications.show(`${totalRegistros} registros de teste carregados em ${totalPaginas} páginas!`, 'success', 4000);
        }

        // Carregar dados de teste para página específica
        function carregarDadosTestePagina() {
            if (!window.dadosTestePaginacao) return;
            
            const dependentesFicticios = window.dadosTestePaginacao;
            const totalRegistros = dependentesFicticios.length;
            const registrosPorPagina = pagination.registrosPorPagina;
            const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
            
            const dadosPaginacao = {
                pagina_atual: pagination.paginaAtual,
                registros_por_pagina: registrosPorPagina,
                total_registros: totalRegistros,
                total_paginas: totalPaginas
            };
            
            // Obter registros da página atual
            const inicio = (pagination.paginaAtual - 1) * registrosPorPagina;
            const fim = inicio + registrosPorPagina;
            const dependentesPagina = dependentesFicticios.slice(inicio, fim);
            
            // Atualizar interface
            pagination.atualizarPaginacao(dadosPaginacao);
            exibirDependentes(dependentesPagina);
            dependentesAtual = dependentesPagina;
        }

        // Carregar dados locais para página específica (quando API não tem paginação)
        function carregarDadosLocaisPagina() {
            if (!window.dadosCompletos) return;
            
            console.log('🔄 Carregando dados locais para página', pagination.paginaAtual);
            
            const dadosCompletos = window.dadosCompletos;
            const totalRegistros = dadosCompletos.length;
            const registrosPorPagina = pagination.registrosPorPagina;
            const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
            
            const dadosPaginacao = {
                pagina_atual: pagination.paginaAtual,
                registros_por_pagina: registrosPorPagina,
                total_registros: totalRegistros,
                total_paginas: totalPaginas
            };
            
            // Obter registros da página atual
            const inicio = (pagination.paginaAtual - 1) * registrosPorPagina;
            const fim = inicio + registrosPorPagina;
            const dependentesPagina = dadosCompletos.slice(inicio, fim);
            
            // Atualizar interface
            pagination.atualizarPaginacao(dadosPaginacao);
            exibirDependentes(dependentesPagina);
            dependentesAtual = dependentesPagina;
            
            console.log(`📊 Página ${pagination.paginaAtual}: mostrando ${dependentesPagina.length} de ${totalRegistros} registros`);
        }

        // Ver detalhes do dependente
        function verDetalhes(dependenteId) {
            const dependente = dependentesAtual.find(d => d.dependente_id == dependenteId);
            if (!dependente) return;
            
            // Aqui você pode abrir um modal ou navegar para uma página de detalhes
            notifications.show(`Visualizando detalhes de ${dependente.nome_dependente}`, 'info');
            
            // Exemplo: redirecionar para página de detalhes
            // window.location.href = `../pages/dependente_detalhes.php?id=${dependenteId}`;
        }

        // Exportar dados
        function exportarDados() {
            if (dependentesAtual.length === 0) {
                notifications.show('Nenhum dado para exportar', 'warning');
                return;
            }
            
            notifications.show('Preparando exportação...', 'info');
            
            // Aqui você pode implementar a exportação em CSV, PDF, etc.
            setTimeout(() => {
                // Simulação de exportação
                const csv = gerarCSV(dependentesAtual);
                baixarCSV(csv, 'dependentes_18anos.csv');
                notifications.show(`${dependentesAtual.length} registros exportados com sucesso!`, 'success');
            }, 1000);
        }

        // Gerar CSV
        function gerarCSV(dados) {
            const cabecalho = [
                'Nome Dependente', 
                'Idade Atual', 
                'Data Nascimento', 
                'Data 18 Anos',
                'Status',
                'Prioridade',
                'Parentesco', 
                'Nome Responsável', 
                'RG Responsável', 
                'Telefone', 
                'Email',
                'Situação Associado'
            ];
            
            const linhas = dados.map(dep => [
                dep.nome_dependente,
                dep.idade_atual,
                dep.data_nascimento,
                dep.data_18_anos,
                dep.status,
                dep.prioridade,
                dep.parentesco,
                dep.nome_responsavel,
                dep.rg_responsavel,
                dep.telefone_responsavel || '',
                dep.email_responsavel || '',
                dep.situacao_associado
            ]);
            
            const csvContent = [cabecalho, ...linhas].map(linha => linha.join(',')).join('\n');
            return csvContent;
        }

        // Baixar CSV
        function baixarCSV(csvContent, nomeArquivo) {
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', nomeArquivo);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Configurar eventos
        function configurarEventos() {
            // Event listeners para filtros em tempo real
            document.getElementById('filtroSituacao').addEventListener('change', () => {
                pagination.paginaAtual = 1;
                // Limpar dados locais ao alterar filtros
                window.dadosCompletos = null;
                window.dadosTestePaginacao = null;
                carregarDependentes();
            });
            
            // Event listener para busca com delay
            let timeoutBusca;
            document.getElementById('filtroBusca').addEventListener('input', function() {
                clearTimeout(timeoutBusca);
                timeoutBusca = setTimeout(() => {
                    pagination.paginaAtual = 1;
                    // Limpar dados locais ao fazer busca
                    window.dadosCompletos = null;
                    window.dadosTestePaginacao = null;
                    carregarDependentes();
                }, 500);
            });

            // Event listener para Enter na navegação rápida
            document.getElementById('paginaRapida').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    irParaPagina();
                }
            });
        }

        // Obter badge de prioridade baseado na API
        function getBadgePrioridade(prioridade) {
            switch (prioridade) {
                case 'critica':
                    return { classe: 'idade-critica', texto: 'Crítico' };
                case 'alta':
                    return { classe: 'idade-atencao', texto: 'Alta' };
                case 'media':
                    return { classe: 'idade-atencao', texto: 'Média' };
                case 'baixa':
                    return { classe: 'idade-normal', texto: 'Baixa' };
                default:
                    return { classe: 'idade-normal', texto: 'Normal' };
            }
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

        // Log de inicialização
        console.log('✅ Sistema de Controle de Dependentes CORRIGIDO com Dados Reais carregado!');
        console.log(`🏢 Nível de acesso: ${isFinanceiro ? 'Financeiro (ID: 5)' : isPresidencia ? 'Presidência (ID: 1)' : isDiretoria ? 'Diretoria' : 'Desconhecido'}`);
        console.log(`🔐 Permissões: ${temPermissao ? 'Concedidas' : 'Negadas'}`);
        console.log(`👶 Estatísticas PHP: Total Filhos: ${estatisticasIniciais.totalFilhos}, Já 18+: ${estatisticasIniciais.jaCompletaram}, Este Mês: ${estatisticasIniciais.esteMes}, Próximos 3 meses: ${estatisticasIniciais.proximosMeses}`);
        console.log('📄 Sistema de paginação híbrida implementado:');
        console.log('   ✓ Paginação via API (quando disponível)');
        console.log('   ✓ Paginação local (fallback automático)');
        console.log('   ✓ Paginação de teste (para demonstração)');
        console.log('🧪 Botão "Teste" disponível para demonstrar paginação com dados fictícios');
        console.log('🛡️ Sistema preparado para APIs com e sem paginação implementada');
        console.log('📊 Dados das estatísticas carregados diretamente do PHP/MySQL');
    </script>

</body>

</html>