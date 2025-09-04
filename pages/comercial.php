<?php
/**
 * Página de Serviços Comerciais - Sistema ASSEGO
 * pages/comercial.php
 * VERSÃO COMPLETA COM SISTEMA DE PERMISSÕES INTEGRADO
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Permissoes.php';
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

// ====================================
// VERIFICAÇÃO DE PERMISSÕES
// ====================================

// Permissão básica - todos do comercial têm
$temPermissaoVisualizar = Permissoes::tem('COMERCIAL_DASHBOARD') || Permissoes::tem('COMERCIAL_VISUALIZAR');

// Permissões específicas
$temPermissaoCriarAssociado = Permissoes::tem('COMERCIAL_CRIAR_ASSOCIADO');
$temPermissaoDesfiliacao = Permissoes::tem('COMERCIAL_DESFILIACAO');
$temPermissaoDependentes = Permissoes::tem('COMERCIAL_DEPENDENTES');

// Permissão especial de relatórios - apenas diretor e ID 71
$temPermissaoRelatorios = Permissoes::tem('COMERCIAL_RELATORIOS');
// DEBUG TEMPORÁRIO - REMOVER DEPOIS
echo "<!-- DEBUG PERMISSÕES\n";
echo "Usuario ID: " . $usuarioLogado['id'] . "\n";
echo "Usuario Nome: " . $usuarioLogado['nome'] . "\n";
echo "Usuario Cargo: " . $usuarioLogado['cargo'] . "\n";
echo "Usuario Depto: " . $usuarioLogado['departamento_id'] . "\n";
echo "Tem Permissão Relatórios: " . ($temPermissaoRelatorios ? 'SIM' : 'NÃO') . "\n";

// Verificar diretamente no banco
// FIM DO DEBUG

// Se não tem nem permissão básica de visualizar, bloqueia acesso total
if (!$temPermissaoVisualizar) {
    Permissoes::registrarAcessoNegado('COMERCIAL_DASHBOARD', 'comercial.php');
    $_SESSION['erro'] = 'Você não tem permissão para acessar o setor comercial.';
    header('Location: ../pages/dashboard.php');
    exit;
}

// Verificar se é diretor do comercial ou a funcionária especial para gerenciar permissões
$podeGerenciarPermissoes = false;
if (
    $usuarioLogado['id'] == 71 ||
    ($usuarioLogado['cargo'] == 'Diretor' && $usuarioLogado['departamento_id'] == 10)
) {
    $podeGerenciarPermissoes = true;
}

// Busca estatísticas do setor comercial
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Total de associados ativos
    $sql = "SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $totalAssociadosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Novos cadastros hoje
    $sql = "SELECT COUNT(*) as hoje FROM Associados WHERE DATE(data_aprovacao) = CURDATE()";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $cadastrosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];

    // Pré-cadastros pendentes
    $sql = "SELECT COUNT(*) as pendentes FROM Associados WHERE pre_cadastro = 1";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $preCadastrosPendentes = $stmt->fetch(PDO::FETCH_ASSOC)['pendentes'];

    // Desfiliações recentes (30 dias)
    $sql = "SELECT COUNT(*) as desfiliacao FROM Associados 
            WHERE situacao IN ('DESFILIADO', 'Desfiliado', 'desfiliado')
            AND data_desfiliacao IS NOT NULL
            AND data_desfiliacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $desfiliacoesRecentes = $stmt->fetch(PDO::FETCH_ASSOC)['desfiliacao'] ?? 0;

} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $totalAssociadosAtivos = $cadastrosHoje = $preCadastrosPendentes = $desfiliacoesRecentes = 0;
}

// Se pode gerenciar permissões, busca funcionários do departamento
$funcionariosComercial = [];
if ($podeGerenciarPermissoes) {
    try {
        $sql = "SELECT f.id, f.nome, f.cargo, f.email,
                (SELECT COUNT(*) FROM funcionario_permissoes fp 
                 JOIN recursos r ON fp.recurso_id = r.id 
                 WHERE fp.funcionario_id = f.id 
                 AND r.codigo = 'COMERCIAL_RELATORIOS' 
                 AND fp.tipo = 'GRANT') as tem_relatorios
                FROM Funcionarios f
                WHERE f.departamento_id = 10 
                AND f.ativo = 1
                AND f.id != 71  -- Não mostrar a própria funcionária especial
                AND f.cargo != 'Diretor'  -- Diretor já tem acesso
                ORDER BY f.nome";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $funcionariosComercial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao buscar funcionários: " . $e->getMessage());
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./estilizacao/style.css">

    <!-- Estilos Personalizados Premium -->
    <style>
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #003d94;
            --secondary: #6c757d;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            --gradient-danger: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-warning: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            --shadow-premium: 0 10px 40px rgba(0, 86, 210, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            position: relative;
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
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            padding: 0 0 1rem 0;
            position: relative;
        }

        .page-title {
        font-size: 2rem;
        font-weight: 800;
        color: var(--dark);
        margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
        }

        /* Stats Grid Premium */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.75rem;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 86, 210, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-2xl);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 1;
            box-shadow: var(--shadow-lg);
        }

        .stat-icon::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 18px;
            background: inherit;
            filter: blur(10px);
            opacity: 0.4;
            z-index: -1;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%);
            color: white;
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
            line-height: 1;
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-trend {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        /* Actions Container Premium */
        .actions-container {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .actions-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: linear-gradient(180deg, rgba(0, 86, 210, 0.03) 0%, transparent 100%);
            pointer-events: none;
        }

        .actions-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid transparent;
            background: linear-gradient(90deg, var(--light) 0%, var(--light) 50%, transparent 50%);
            background-size: 20px 2px;
            background-repeat: repeat-x;
            background-position: bottom;
        }

        .actions-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .actions-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
        }

        .action-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 1.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(0, 86, 210, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .action-card:hover::before {
            width: 400px;
            height: 400px;
        }

        .action-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 12px 24px rgba(0, 86, 210, 0.15);
            background: white;
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.375rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .action-card:hover .action-icon {
            transform: rotate(5deg) scale(1.1);
        }

        .action-icon.primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(34, 197, 94, 0.4);
        }

        .action-icon.success {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }

        .action-icon.warning {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.4);
        }

        .action-icon.info {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.4);
        }

        .action-content {
            position: relative;
            z-index: 1;
        }

        .action-content h5 {
            margin: 0 0 0.375rem 0;
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
        }

        .action-content p {
            margin: 0;
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.5;
        }

        /* Permissões */
        .permission-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            display: inline-block;
            margin-left: 0.5rem;
        }

        .action-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .action-card.disabled::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 16px;
        }

        .no-permission-overlay {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            z-index: 10;
        }

        /* Gerenciamento de Permissões */
        .permissions-management {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .permission-user-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .permission-user-card:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }

        .switch-permission {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 28px;
        }

        .switch-permission input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider-permission {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 28px;
        }

        .slider-permission:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider-permission {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        input:checked+.slider-permission:before {
            transform: translateX(32px);
        }

        /* Modals */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-2xl);
        }

        .modal-header-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.75rem;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .modal-header-custom::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -25%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        }

        .modal-title-custom {
            font-size: 1.375rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .modal-body {
            padding: 2rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-top: 1px solid rgba(0, 86, 210, 0.1);
        }

        /* Buttons */
        .btn-premium {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-md);
        }

        .btn-premium::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-premium:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary-premium {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 86, 210, 0.3);
            color: white;
        }

        .btn-success-premium {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
        }

        .btn-success-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .btn-secondary-premium {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
        }

        .btn-secondary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
            color: white;
        }

        /* Forms */
        .form-control-premium {
            padding: 0.875rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control-premium:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 86, 210, 0.1);
            outline: none;
        }

        .form-label-premium {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Alerts */
        .alert-premium {
            padding: 1.25rem;
            border-radius: 12px;
            border: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
        }

        .alert-info-premium {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
            color: #1e40af;
            border-left: 4px solid var(--primary);
        }

        .alert-warning-premium {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
            color: #92400e;
            border-left: 4px solid var(--warning);
        }

        /* Cards */
        .result-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0, 86, 210, 0.1);
            margin-top: 1rem;
        }

        .selection-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .selection-card:hover {
            border-color: var(--primary);
            transform: translateX(4px);
            box-shadow: var(--shadow-lg);
        }

        .selection-card.selected {
            background: linear-gradient(135deg, rgba(0, 86, 210, 0.05) 0%, rgba(74, 144, 226, 0.05) 100%);
            border-color: var(--primary);
        }

        /* Loading */
        .loading-premium {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        .spinner-premium {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(0, 86, 210, 0.1);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Badge */
        .badge-premium {
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .badge-primary-premium {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        /* Animações */
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

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 1.75rem;
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
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas me-2"></i>Serviços Comerciais
                </h1>
                <p class="page-subtitle">
                          Gerencie cadastros, desfiliações e serviços do setor comercial com eficiência
                </p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="100">
                    <span class="stat-trend">+12%</span>
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($totalAssociadosAtivos, 0, ',', '.'); ?></div>
                    <div class="stat-label">Associados Ativos</div>
                </div>

                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-icon success">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-value"><?php echo $cadastrosHoje; ?></div>
                    <div class="stat-label">Cadastros Hoje</div>
                </div>

                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo $preCadastrosPendentes; ?></div>
                    <div class="stat-label">Pré-Filiados Pendentes</div>
                </div>

                <div class="stat-card animate__animated animate__fadeInUp" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-icon danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-value"><?php echo $desfiliacoesRecentes; ?></div>
                    <div class="stat-label">Desfiliações (30 dias)</div>
                </div>
            </div>

            <!-- Actions Container -->
            <div class="actions-container animate__animated animate__fadeIn" data-aos="fade-up" data-aos-delay="500">
                <div class="actions-header">
                    <h3 class="actions-title">
                        <i class="fas fa-tools"></i>
                        Ações Rápidas
                    </h3>
                </div>

                <div class="actions-grid">
                    <!-- Nova Filiação -->
                    <div class="action-card <?php echo !$temPermissaoCriarAssociado ? 'disabled' : ''; ?>"
                        onclick="<?php echo $temPermissaoCriarAssociado ? 'novoPreCadastro()' : 'semPermissao()'; ?>">
                        <?php if (!$temPermissaoCriarAssociado): ?>
                            <div class="no-permission-overlay">Sem permissão</div>
                        <?php endif; ?>
                        <div class="action-icon primary">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-content">
                            <h5>Nova Filiação</h5>
                            <p>Cadastrar novo associado no sistema</p>
                        </div>
                    </div>

                    <!-- Consultar Associado -->
                    <div class="action-card" onclick="consultarAssociado()">
                        <div class="action-icon success">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="action-content">
                            <h5>Consultar Associado</h5>
                            <p>Buscar e visualizar dados completos</p>
                        </div>
                    </div>

                    <!-- Solicitação de Desfiliação -->
                    <div class="action-card <?php echo !$temPermissaoDesfiliacao ? 'disabled' : ''; ?>"
                        onclick="<?php echo $temPermissaoDesfiliacao ? 'abrirModalDesfiliacao()' : 'semPermissao()'; ?>">
                        <?php if (!$temPermissaoDesfiliacao): ?>
                            <div class="no-permission-overlay">Sem permissão</div>
                        <?php endif; ?>
                        <div class="action-icon warning">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="action-content">
                            <h5>Solicitação de Desfiliação</h5>
                            <p>Gerar ficha oficial de desfiliação</p>
                        </div>
                    </div>

                    <!-- Dependentes 18+ -->
                    <div class="action-card <?php echo !$temPermissaoDependentes ? 'disabled' : ''; ?>"
                        onclick="<?php echo $temPermissaoDependentes ? 'consultarDependentes18()' : 'semPermissao()'; ?>">
                        <?php if (!$temPermissaoDependentes): ?>
                            <div class="no-permission-overlay">Sem permissão</div>
                        <?php endif; ?>
                        <div class="action-icon info">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="action-content">
                            <h5>Dependentes 18+</h5>
                            <p>Listar dependentes maiores de idade</p>
                        </div>
                    </div>

                    <!-- Relatórios -->
                    <div class="action-card <?php echo !$temPermissaoRelatorios ? 'disabled' : ''; ?>"
                        onclick="<?php echo $temPermissaoRelatorios ? 'relatoriosComerciais()' : 'semPermissao()'; ?>">
                        <?php if (!$temPermissaoRelatorios): ?>
                            <div class="no-permission-overlay">Sem permissão</div>
                        <?php endif; ?>
                        <div class="action-icon primary">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-content">
                            <h5>Relatórios Gerenciais</h5>
                            <p>Estatísticas e análises detalhadas</p>
                        </div>
                    </div>


                </div>
            </div>

            ''

            <!-- ADICIONE ESTE CSS ADICIONAL NO HEAD -->
            <style>
                .permission-user-card {
                    background: #f8fafc;
                    border-radius: 12px;
                    padding: 1rem;
                    margin-bottom: 0.75rem;
                    transition: all 0.3s ease;
                    border: 2px solid transparent;
                }

                .permission-user-card:hover {
                    background: #f1f5f9;
                    border-color: var(--primary-light);
                    transform: translateX(4px);
                }

                .badge-status .badge {
                    min-width: 110px;
                }

                #listaFuncionariosPermissoes::-webkit-scrollbar {
                    width: 8px;
                }

                #listaFuncionariosPermissoes::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }

                #listaFuncionariosPermissoes::-webkit-scrollbar-thumb {
                    background: var(--primary-light);
                    border-radius: 10px;
                }

                #listaFuncionariosPermissoes::-webkit-scrollbar-thumb:hover {
                    background: var(--primary);
                }

                .funcionario-item {
                    animation: fadeIn 0.3s ease;
                }

                .funcionario-item.hidden {
                    display: none;
                }

                /* Loading overlay para botões */
                .btn-loading {
                    position: relative;
                    pointer-events: none;
                    opacity: 0.7;
                }

                .btn-loading::after {
                    content: '';
                    position: absolute;
                    width: 16px;
                    height: 16px;
                    margin: auto;
                    border: 2px solid transparent;
                    border-top-color: #ffffff;
                    border-radius: 50%;
                    animation: button-loading-spinner 1s ease infinite;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                }

                @keyframes button-loading-spinner {
                    from {
                        transform: translate(-50%, -50%) rotate(0turn);
                    }

                    to {
                        transform: translate(-50%, -50%) rotate(1turn);
                    }
                }
            </style>

            <!-- Modal de Desfiliação -->
            <div class="modal fade" id="modalDesfiliacao" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-header-custom">
                            <h5 class="modal-title modal-title-custom">
                                <i class="fas fa-user-times"></i>
                                Solicitação de Desfiliação
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert-premium alert-info-premium">
                                <i class="fas fa-info-circle fa-lg"></i>
                                <div>
                                    <strong>Instruções:</strong><br>
                                    Digite o RG militar ou nome do associado para gerar a ficha de desfiliação oficial.
                                </div>
                            </div>

                            <form id="formBuscaDesfiliacao" onsubmit="buscarAssociadoDesfiliacao(event)">
                                <div class="mb-4">
                                    <label class="form-label-premium">
                                        <i class="fas fa-id-card"></i>
                                        RG Militar ou Nome do Associado
                                    </label>
                                    <input type="text" class="form-control form-control-premium" id="buscaDesfiliacao"
                                        placeholder="Ex: 123456 ou João da Silva" required>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-premium btn-primary-premium">
                                        <i class="fas fa-search me-2"></i>
                                        Buscar Associado
                                    </button>
                                    <button type="button" class="btn btn-premium btn-secondary-premium"
                                        onclick="limparBuscaDesfiliacao()">
                                        <i class="fas fa-eraser me-2"></i>
                                        Limpar
                                    </button>
                                </div>
                            </form>

                            <!-- Container para resultados -->
                            <div id="resultadoDesfiliacao" class="mt-4" style="display: none;">
                                <!-- Dados do associado serão exibidos aqui -->
                            </div>

                            <!-- Loading -->
                            <div id="loadingDesfiliacao" class="loading-premium" style="display: none;">
                                <div class="spinner-premium"></div>
                                <p class="mt-3 text-muted">Buscando dados do associado...</p>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-premium btn-secondary-premium" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>
                                Cancelar
                            </button>
                            <button type="button" class="btn btn-premium btn-success-premium" id="btnGerarFicha"
                                style="display: none;" onclick="gerarFichaDesfiliacao()">
                                <i class="fas fa-file-alt me-2"></i>
                                Gerar Ficha
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de Seleção (múltiplos associados) -->
            <div class="modal fade" id="modalSelecaoAssociado" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-header-custom">
                            <h5 class="modal-title modal-title-custom">
                                <i class="fas fa-users"></i>
                                Seleção de Associado
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert-premium alert-warning-premium">
                                <i class="fas fa-exclamation-triangle fa-lg"></i>
                                <div>
                                    <strong>Múltiplos registros encontrados!</strong><br>
                                    Selecione o associado correto para continuar.
                                </div>
                            </div>
                            <div id="listaAssociadosSelecao">
                                <!-- Lista será inserida aqui -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-premium btn-secondary-premium" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>
                                Cancelar
                            </button>
                            <button type="button" class="btn btn-premium btn-primary-premium" id="btnConfirmarSelecao"
                                disabled onclick="confirmarSelecaoAssociado()">
                                <i class="fas fa-check me-2"></i>
                                Confirmar Seleção
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de Ficha de Desfiliação -->
            <div class="modal fade" id="modalFichaDesfiliacao" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header modal-header-custom">
                            <h5 class="modal-title modal-title-custom">
                                <i class="fas fa-file-alt"></i>
                                Ficha de Desfiliação - ASSEGO
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="fichaDesfiliacao" style="padding: 2.5rem; background: white; border-radius: 12px;">
                                <!-- Conteúdo da ficha será gerado aqui -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-premium btn-secondary-premium" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>
                                Fechar
                            </button>
                            <button type="button" class="btn btn-premium btn-primary-premium" onclick="imprimirFicha()">
                                <i class="fas fa-print me-2"></i>
                                Imprimir Ficha
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
                // Variáveis globais
                let dadosAssociadoAtual = null;
                let associadoSelecionadoId = null;

                // Inicialização
                document.addEventListener('DOMContentLoaded', function () {
                    AOS.init({
                        duration: 800,
                        once: true,
                        offset: 50
                    });

                    // Adiciona efeito de ondulação nos cards
                    document.querySelectorAll('.action-card').forEach(card => {
                        card.addEventListener('click', function (e) {
                            const ripple = document.createElement('span');
                            ripple.classList.add('ripple');
                            this.appendChild(ripple);

                            setTimeout(() => {
                                ripple.remove();
                            }, 600);
                        });
                    });
                });

                // Função para mostrar aviso de sem permissão
                function semPermissao() {
                    showToast('Você não tem permissão para acessar esta funcionalidade', 'danger');
                }

                // Funções de navegação
                function novoPreCadastro() {
                    showToast('Redirecionando para novo cadastro...', 'info');
                    setTimeout(() => {
                        window.location.href = '../pages/cadastroForm.php';
                    }, 500);
                }

                function consultarAssociado() {
                    showToast('Abrindo consulta de associados...', 'info');
                    setTimeout(() => {
                        window.location.href = '../pages/dashboard.php';
                    }, 500);
                }

                function consultarDependentes18() {
                    showToast('Carregando dependentes...', 'info');
                    setTimeout(() => {
                        window.location.href = '../pages/dependentes_18anos.php';
                    }, 500);
                }

                function relatoriosComerciais() {
                    showToast('Abrindo relatórios comerciais...', 'info');
                    setTimeout(() => {
                        window.location.href = '../pages/comercial_relatorios.php';
                    }, 500);
                }

                function gerenciarPreCadastros() {
                    showToast('Carregando pré-cadastros...', 'info');
                    setTimeout(() => {
                        window.location.href = '../pages/pre_cadastros.php';
                    }, 500);
                }

                // Função para abrir modal de desfiliação
                function abrirModalDesfiliacao() {
                    const modal = new bootstrap.Modal(document.getElementById('modalDesfiliacao'));
                    modal.show();
                }

                // Buscar associado para desfiliação
                async function buscarAssociadoDesfiliacao(event) {
                    event.preventDefault();

                    const busca = document.getElementById('buscaDesfiliacao').value.trim();
                    const loading = document.getElementById('loadingDesfiliacao');
                    const resultado = document.getElementById('resultadoDesfiliacao');
                    const btnGerar = document.getElementById('btnGerarFicha');

                    if (!busca) {
                        showToast('Por favor, digite um RG ou nome', 'warning');
                        return;
                    }

                    loading.style.display = 'flex';
                    resultado.style.display = 'none';
                    btnGerar.style.display = 'none';

                    try {
                        const parametro = isNaN(busca) ? 'nome' : 'rg';
                        const response = await fetch(`../api/associados/buscar_por_rg.php?${parametro}=${encodeURIComponent(busca)}`);
                        const result = await response.json();

                        if (result.status === 'multiple_results') {
                            mostrarModalSelecao(result.data);
                        } else if (result.status === 'success') {
                            dadosAssociadoAtual = result.data;
                            exibirDadosAssociado(result.data);
                        } else {
                            showToast(result.message || 'Associado não encontrado', 'danger');
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        showToast('Erro ao buscar associado', 'danger');
                    } finally {
                        loading.style.display = 'none';
                    }
                }

                // Exibir dados do associado encontrado
                function exibirDadosAssociado(dados) {
                    const resultado = document.getElementById('resultadoDesfiliacao');
                    const btnGerar = document.getElementById('btnGerarFicha');

                    const pessoais = dados.dados_pessoais || {};
                    const militares = dados.dados_militares || {};

                    resultado.innerHTML = `
                <div class="result-card animate__animated animate__fadeIn">
                    <h6 class="mb-3" style="color: var(--primary); font-weight: 700;">
                        <i class="fas fa-user me-2"></i>
                        Dados do Associado Encontrado
                    </h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <small class="text-muted">Nome Completo</small>
                                    <p class="mb-0 fw-semibold">${pessoais.nome || '-'}</p>
                                </div>
                                <div>
                                    <small class="text-muted">RG Militar</small>
                                    <p class="mb-0 fw-semibold">${pessoais.rg || '-'}</p>
                                </div>
                                <div>
                                    <small class="text-muted">CPF</small>
                                    <p class="mb-0 fw-semibold">${formatarCPF(pessoais.cpf) || '-'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex flex-column gap-2">
                                <div>
                                    <small class="text-muted">Corporação</small>
                                    <p class="mb-0 fw-semibold">${militares.corporacao || '-'}</p>
                                </div>
                                <div>
                                    <small class="text-muted">Patente</small>
                                    <p class="mb-0 fw-semibold">${militares.patente || '-'}</p>
                                </div>
                                <div>
                                    <small class="text-muted">Lotação</small>
                                    <p class="mb-0 fw-semibold">${militares.lotacao || '-'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

                    resultado.style.display = 'block';
                    btnGerar.style.display = 'inline-block';
                }

                // Mostrar modal de seleção para múltiplos resultados
                function mostrarModalSelecao(associados) {
                    const lista = document.getElementById('listaAssociadosSelecao');
                    lista.innerHTML = '';

                    associados.forEach(assoc => {
                        const card = document.createElement('div');
                        card.className = 'selection-card';
                        card.innerHTML = `
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="associadoSelecao" 
                               value="${assoc.id}" id="assoc_${assoc.id}"
                               onchange="habilitarConfirmacao()">
                        <label class="form-check-label" for="assoc_${assoc.id}" style="cursor: pointer; width: 100%;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">${assoc.nome}</h6>
                                    <small class="text-muted">RG: ${assoc.rg} | CPF: ${assoc.cpf || '-'}</small><br>
                                    <span class="badge badge-premium badge-primary-premium mt-1">
                                        ${assoc.corporacao || 'Sem corporação'} - ${assoc.patente || '-'}
                                    </span>
                                </div>
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                        </label>
                    </div>
                `;

                        card.addEventListener('click', function () {
                            document.querySelector(`#assoc_${assoc.id}`).checked = true;
                            document.querySelectorAll('.selection-card').forEach(c => c.classList.remove('selected'));
                            this.classList.add('selected');
                            habilitarConfirmacao();
                        });

                        lista.appendChild(card);
                    });

                    // Fechar modal de busca
                    bootstrap.Modal.getInstance(document.getElementById('modalDesfiliacao')).hide();

                    // Abrir modal de seleção
                    const modalSelecao = new bootstrap.Modal(document.getElementById('modalSelecaoAssociado'));
                    modalSelecao.show();
                }

                // Habilitar botão de confirmação
                function habilitarConfirmacao() {
                    document.getElementById('btnConfirmarSelecao').disabled = false;
                }

                // Confirmar seleção de associado
                async function confirmarSelecaoAssociado() {
                    const selected = document.querySelector('input[name="associadoSelecao"]:checked');
                    if (!selected) return;

                    associadoSelecionadoId = selected.value;

                    // Fechar modal de seleção
                    bootstrap.Modal.getInstance(document.getElementById('modalSelecaoAssociado')).hide();

                    // Reabrir modal de desfiliação
                    const modalDesfiliacao = new bootstrap.Modal(document.getElementById('modalDesfiliacao'));
                    modalDesfiliacao.show();

                    // Buscar dados completos
                    const loading = document.getElementById('loadingDesfiliacao');
                    loading.style.display = 'flex';

                    try {
                        const response = await fetch(`../api/associados/buscar_por_rg.php?id=${associadoSelecionadoId}`);
                        const result = await response.json();

                        if (result.status === 'success') {
                            dadosAssociadoAtual = result.data;
                            exibirDadosAssociado(result.data);
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        showToast('Erro ao buscar dados', 'danger');
                    } finally {
                        loading.style.display = 'none';
                    }
                }

                // Gerar ficha de desfiliação
                function gerarFichaDesfiliacao() {
                    if (!dadosAssociadoAtual) {
                        showToast('Nenhum associado selecionado', 'warning');
                        return;
                    }

                    const pessoais = dadosAssociadoAtual.dados_pessoais || {};
                    const militares = dadosAssociadoAtual.dados_militares || {};
                    const endereco = dadosAssociadoAtual.endereco || {};

                    const hoje = new Date();
                    const meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

                    const fichaHTML = `
                <div style="font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #000;">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h2 style="color: #000; font-size: 18px; margin-bottom: 5px; font-weight: bold; letter-spacing: 1px;">
                            SOLICITAÇÃO DE DESFILIAÇÃO
                        </h2>
                        <h3 style="color: #000; font-size: 16px; font-weight: bold; letter-spacing: 1px;">ASSEGO</h3>
                    </div>
                    
                    <p style="text-align: right; margin-bottom: 30px; font-size: 14px;">
                        Goiânia, ${hoje.getDate()} de ${meses[hoje.getMonth()]} de ${hoje.getFullYear()}
                    </p>
                    
                    <p style="margin-bottom: 20px; font-size: 14px;"><strong>Prezado Sr. Presidente,</strong></p>
                    
                    <div style="text-align: justify; line-height: 1.8; margin-bottom: 20px; font-size: 14px;">
                        <p style="text-indent: 40px;">
                            Eu, <strong>${pessoais.nome || '_______________'}</strong>,
                            portador do RG militar: <strong>${pessoais.rg || '_______________'}</strong>,
                            Instituição: <strong>${militares.corporacao || '_______________'}</strong>,
                            residente e domiciliado: <strong>${formatarEndereco(endereco)}</strong>,
                            telefone <strong>${formatarTelefone(pessoais.telefone) || '_______________'}</strong>,
                            Lotação: <strong>${militares.lotacao || '_______________'}</strong>,
                            solicito minha desfiliação total da Associação dos Subtenentes e Sargentos do Estado
                            de Goiás – ASSEGO, pelo motivo:
                        </p>
                    </div>
                    
                    <div style="margin: 25px 0;">
                        <textarea id="motivoDesfiliacao" 
                                  style="width: 100%; 
                                         min-height: 100px; 
                                         padding: 10px; 
                                         border: 1px solid #000; 
                                         border-radius: 0; 
                                         font-family: Arial, Helvetica, sans-serif;
                                         font-size: 14px;
                                         line-height: 1.6;
                                         background: #fff;
                                         resize: vertical;"
                                  placeholder="Digite o motivo da desfiliação"
                                  onInput="this.style.height = 'auto'; this.style.height = this.scrollHeight + 'px'">
                        </textarea>
                    </div>
                    
                    <div style="text-align: justify; margin-bottom: 30px; font-size: 14px;">
                        <p style="text-indent: 40px;">
                            Me coloco à disposição, através do telefone informado acima para informações
                            adicionais necessárias à conclusão deste processo e, desde já, 
                            <strong>DECLARO ESTAR CIENTE QUE O PROCESSO INTERNO TEM UM PRAZO DE ATÉ 30 DIAS, 
                            A CONTAR DA DATA DE SOLICITAÇÃO, PARA SER CONCLUÍDO.</strong>
                        </p>
                    </div>
                    
                    <p style="margin-bottom: 60px; font-size: 14px;"><strong>Respeitosamente,</strong></p>
                    
                    <div style="text-align: center; margin-top: 80px;">
                        <div style="display: inline-block; border-top: 1px solid #000; 
                                    padding-top: 5px; min-width: 300px; font-size: 14px;">
                            <strong>Assinatura do requerente</strong>
                        </div>
                    </div>
                </div>
            `;

                    document.getElementById('fichaDesfiliacao').innerHTML = fichaHTML;

                    // Fechar modal de busca
                    bootstrap.Modal.getInstance(document.getElementById('modalDesfiliacao')).hide();

                    // Abrir modal da ficha
                    setTimeout(() => {
                        const modalFicha = new bootstrap.Modal(document.getElementById('modalFichaDesfiliacao'));
                        modalFicha.show();

                        // Focar no campo de texto após abrir o modal
                        setTimeout(() => {
                            const textarea = document.getElementById('motivoDesfiliacao');
                            if (textarea) {
                                textarea.focus();
                            }
                        }, 500);
                    }, 300);
                }

                // Função para imprimir ficha
                function imprimirFicha() {
                    // Capturar o valor do motivo antes de imprimir
                    const motivoTextarea = document.getElementById('motivoDesfiliacao');
                    const motivoTexto = motivoTextarea ? motivoTextarea.value : '';

                    // Criar uma cópia do conteúdo substituindo o textarea pelo texto
                    const conteudoOriginal = document.getElementById('fichaDesfiliacao').innerHTML;
                    const conteudoParaImprimir = conteudoOriginal.replace(
                        /<textarea[^>]*id="motivoDesfiliacao"[^>]*>.*?<\/textarea>/gi,
                        `<div style="border: 1px solid #000; 
                     min-height: 100px; 
                     padding: 10px; 
                     background: #fff;
                     white-space: pre-wrap;
                     word-wrap: break-word;
                     font-size: 14px;">${motivoTexto || '[Motivo não preenchido]'}</div>`
                    );

                    const janela = window.open('', '_blank', 'width=800,height=600');

                    janela.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Ficha de Desfiliação - ASSEGO</title>
                    <style>
                        body { 
                            font-family: Arial, Helvetica, sans-serif; 
                            padding: 20mm; 
                            line-height: 1.6;
                            color: #000;
                        }
                        @media print { 
                            body { 
                                margin: 0;
                                padding: 15mm;
                            } 
                        }
                        @page {
                            size: A4;
                            margin: 15mm;
                        }
                    </style>
                </head>
                <body>${conteudoParaImprimir}</body>
                </html>
            `);

                    janela.document.close();
                    janela.onload = function () {
                        janela.print();
                        janela.close();
                    };
                }

                // Limpar busca
                function limparBuscaDesfiliacao() {
                    document.getElementById('buscaDesfiliacao').value = '';
                    document.getElementById('resultadoDesfiliacao').style.display = 'none';
                    document.getElementById('btnGerarFicha').style.display = 'none';
                    dadosAssociadoAtual = null;
                }

                // Função para alterar permissão de relatórios (apenas para diretor)
                async function alterarPermissaoRelatorios(funcionarioId, conceder) {
                    try {
                        const response = await fetch('../api/permissoes/alterar_permissao_relatorios.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                funcionario_id: funcionarioId,
                                conceder: conceder
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            showToast(conceder ?
                                'Permissão de relatórios concedida com sucesso!' :
                                'Permissão de relatórios removida com sucesso!',
                                'success'
                            );
                        } else {
                            showToast(result.message || 'Erro ao alterar permissão', 'danger');
                            // Reverter o switch em caso de erro
                            const checkbox = document.querySelector(`input[onchange*="${funcionarioId}"]`);
                            if (checkbox) {
                                checkbox.checked = !conceder;
                            }
                        }
                    } catch (error) {
                        console.error('Erro:', error);
                        showToast('Erro ao alterar permissão', 'danger');
                        // Reverter o switch em caso de erro
                        const checkbox = document.querySelector(`input[onchange*="${funcionarioId}"]`);
                        if (checkbox) {
                            checkbox.checked = !conceder;
                        }
                    }
                }

                // Funções auxiliares
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

                function formatarEndereco(endereco) {
                    const partes = [];
                    if (endereco.endereco) {
                        let linha = endereco.endereco;
                        if (endereco.numero) linha += `, nº ${endereco.numero}`;
                        if (endereco.complemento) linha += `, ${endereco.complemento}`;
                        partes.push(linha);
                    }
                    if (endereco.bairro) partes.push(`Bairro: ${endereco.bairro}`);
                    if (endereco.cidade) partes.push(endereco.cidade);
                    if (endereco.cep) partes.push(`CEP: ${endereco.cep}`);

                    return partes.join(', ') || '_______________';
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
            </script>

            <script>
                // Função para abrir modal de permissões
                function abrirModalPermissoes() {
                    const modal = new bootstrap.Modal(document.getElementById('modalGerenciarPermissoes'));
                    modal.show();
                }

                // Função para filtrar funcionários na busca
                function filtrarFuncionarios() {
                    const busca = document.getElementById('buscaFuncionario').value.toLowerCase();
                    const funcionarios = document.querySelectorAll('.funcionario-item');

                    funcionarios.forEach(item => {
                        const nome = item.getAttribute('data-nome');
                        if (nome.includes(busca)) {
                            item.classList.remove('hidden');
                        } else {
                            item.classList.add('hidden');
                        }
                    });
                }

                // Função melhorada para alterar permissão
                async function alterarPermissaoModal(funcionarioId, conceder) {
                    const btn = document.getElementById(`btn_permissao_${funcionarioId}`);
                    const statusBadge = document.getElementById(`status_${funcionarioId}`);
                    const currentState = btn.getAttribute('data-current-state');

                    // Adicionar estado de loading
                    btn.classList.add('btn-loading');
                    btn.disabled = true;

                    try {
                        const response = await fetch('../api/permissoes/alterar_permissao_relatorios.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                funcionario_id: funcionarioId,
                                conceder: conceder
                            })
                        });

                        // Verificar se a resposta é válida
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const text = await response.text();

                        // Tentar fazer parse do JSON
                        let result;
                        try {
                            result = JSON.parse(text);
                        } catch (e) {
                            console.error('Resposta não é JSON válido:', text);
                            throw new Error('Resposta inválida do servidor');
                        }

                        if (result.success) {
                            // Atualizar UI
                            if (conceder) {
                                btn.className = 'btn btn-sm btn-danger';
                                btn.innerHTML = '<i class="fas fa-times me-1"></i>Remover';
                                btn.setAttribute('onclick', `alterarPermissaoModal(${funcionarioId}, false)`);
                                btn.setAttribute('data-current-state', '1');
                                statusBadge.innerHTML = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Com acesso</span>';
                            } else {
                                btn.className = 'btn btn-sm btn-success';
                                btn.innerHTML = '<i class="fas fa-check me-1"></i>Conceder';
                                btn.setAttribute('onclick', `alterarPermissaoModal(${funcionarioId}, true)`);
                                btn.setAttribute('data-current-state', '0');
                                statusBadge.innerHTML = '<span class="badge bg-secondary"><i class="fas fa-times-circle me-1"></i>Sem acesso</span>';
                            }

                            // Atualizar contadores
                            atualizarContadores();

                            // Mostrar toast de sucesso
                            showToast(conceder ?
                                'Permissão de relatórios concedida com sucesso!' :
                                'Permissão de relatórios removida com sucesso!',
                                'success'
                            );
                        } else {
                            throw new Error(result.message || 'Erro ao alterar permissão');
                        }
                    } catch (error) {
                        console.error('Erro detalhado:', error);
                        showToast('Erro ao alterar permissão: ' + error.message, 'danger');
                    } finally {
                        // Remover estado de loading
                        btn.classList.remove('btn-loading');
                        btn.disabled = false;
                    }
                }

                // Função para atualizar contadores
                function atualizarContadores() {
                    const badges = document.querySelectorAll('.badge-status .badge');
                    let comAcesso = 0;
                    let semAcesso = 0;

                    badges.forEach(badge => {
                        if (badge.textContent.includes('Com acesso')) {
                            comAcesso++;
                        } else {
                            semAcesso++;
                        }
                    });

                    // Atualizar os cards de estatística se existirem
                    const totalComAcessoEl = document.getElementById('totalComAcesso');
                    const totalSemAcessoEl = document.getElementById('totalSemAcesso');

                    if (totalComAcessoEl) {
                        totalComAcessoEl.textContent = comAcesso;
                    }
                    if (totalSemAcessoEl) {
                        totalSemAcessoEl.textContent = semAcesso;
                    }
                }

                // Adicionar listener para tecla ESC fechar o modal
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('modalGerenciarPermissoes'));
                        if (modal) {
                            modal.hide();
                        }
                    }
                });
            </script>
</body>

</html>