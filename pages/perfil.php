<?php
/**
 * Página de Perfil do Usuário
 * pages/perfil.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';

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

// Define o título da página
$page_title = 'Meu Perfil - ASSEGO';

// Inicializa classe de funcionários
$funcionarios = new Funcionarios();

// Busca dados completos do funcionário
$funcionarioCompleto = $funcionarios->getById($usuarioLogado['id']);
$badges = $funcionarios->getBadges($usuarioLogado['id']);
$contribuicoes = $funcionarios->getContribuicoes($usuarioLogado['id']);
$estatisticas = $funcionarios->getEstatisticas($usuarioLogado['id']);

// Calcula tempo de empresa
$tempoEmpresa = '-';
if ($funcionarioCompleto['criado_em']) {
    $dataInicio = new DateTime($funcionarioCompleto['criado_em']);
    $hoje = new DateTime();
    $intervalo = $dataInicio->diff($hoje);
    
    if ($intervalo->y > 0) {
        $tempoEmpresa = $intervalo->y . ' ano' . ($intervalo->y > 1 ? 's' : '');
        if ($intervalo->m > 0) {
            $tempoEmpresa .= ' e ' . $intervalo->m . ' mes' . ($intervalo->m > 1 ? 'es' : '');
        }
    } elseif ($intervalo->m > 0) {
        $tempoEmpresa = $intervalo->m . ' mes' . ($intervalo->m > 1 ? 'es' : '');
    } else {
        $tempoEmpresa = $intervalo->d . ' dia' . ($intervalo->d > 1 ? 's' : '');
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'avatar' => $usuarioLogado['avatar'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => '', // Página de perfil não tem tab ativa
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

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--gray-100);
            color: var(--dark);
            overflow-x: hidden;
        }

        /* Main Wrapper */
        .main-wrapper {
            min-height: 100vh;
            background: var(--gray-100);
        }

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 3rem 2rem;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }

        .profile-header-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            border: 4px solid rgba(255, 255, 255, 0.3);
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-top: 1rem;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .profile-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .profile-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .profile-card-header {
            padding: 1.5rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
        }

        .profile-card-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-card-body {
            padding: 1.5rem;
        }

        /* Stats Summary */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            text-align: center;
        }

        .stat-item {
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 12px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Info List */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .info-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            text-align: right;
        }

        /* Badges Section */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
        }

        .badge-card {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .badge-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .badge-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1rem;
            position: relative;
        }

        .badge-icon.gold {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: var(--dark);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
        }

        .badge-icon.silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: var(--dark);
            box-shadow: 0 4px 12px rgba(192, 192, 192, 0.4);
        }

        .badge-icon.bronze {
            background: linear-gradient(135deg, #cd7f32, #e2a76f);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(205, 127, 50, 0.4);
        }

        .badge-name {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }

        .badge-date {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .badge-points {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--primary);
            color: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.625rem;
            font-weight: 700;
        }

        /* Contributions Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0.5rem;
            bottom: 0.5rem;
            width: 2px;
            background: var(--gray-200);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            background: var(--white);
            border: 3px solid var(--primary);
            border-radius: 50%;
        }

        .timeline-content {
            background: var(--gray-100);
            padding: 1.25rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .timeline-content:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .timeline-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .timeline-type {
            font-size: 0.625rem;
            padding: 0.25rem 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .timeline-description {
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .timeline-date {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Buttons */
        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--white);
            box-shadow: 0 2px 8px rgba(0, 86, 210, 0.25);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-white {
            background: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn-white:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        /* Modal */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        .modal-custom.show {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-content-custom {
            background: var(--white);
            border-radius: 24px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header-custom {
            padding: 1.5rem 2rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title-custom {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .modal-close-custom {
            width: 36px;
            height: 36px;
            border: none;
            background: transparent;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--gray-600);
        }

        .modal-close-custom:hover {
            background: var(--gray-200);
            color: var(--dark);
        }

        .modal-body-custom {
            padding: 2rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control-custom {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
        }

        .form-text {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .password-strength-bar.weak {
            width: 33%;
            background: var(--danger);
        }

        .password-strength-bar.medium {
            width: 66%;
            background: var(--warning);
        }

        .password-strength-bar.strong {
            width: 100%;
            background: var(--success);
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            margin-top: 1rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-header-content {
                flex-direction: column;
                text-align: center;
            }

            .profile-meta {
                justify-content: center;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .stats-summary {
                grid-template-columns: repeat(3, 1fr);
            }

            .badges-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        
        <!-- NOVO: Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($funcionarioCompleto['nome'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($funcionarioCompleto['nome']); ?></h1>
                    <div class="profile-meta">
                        <div class="profile-meta-item">
                            <i class="fas fa-briefcase"></i>
                            <span><?php echo htmlspecialchars($funcionarioCompleto['cargo'] ?? 'Sem cargo'); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-building"></i>
                            <span><?php echo htmlspecialchars($funcionarioCompleto['departamento_nome'] ?? 'Sem departamento'); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($funcionarioCompleto['email']); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-clock"></i>
                            <span>Há <?php echo $tempoEmpresa; ?> na empresa</span>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button class="btn-modern btn-white" onclick="abrirModalEdicao()">
                            <i class="fas fa-edit"></i>
                            Editar Perfil
                        </button>
                        <button class="btn-modern btn-white" onclick="abrirModalSenha()">
                            <i class="fas fa-key"></i>
                            Alterar Senha
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="profile-grid">
                <!-- Sidebar -->
                <div class="profile-sidebar">
                    <!-- Stats Card -->
                    <div class="profile-card" data-aos="fade-right">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-chart-line"></i>
                                Estatísticas
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <div class="stats-summary">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $estatisticas['total_badges']; ?></div>
                                    <div class="stat-label">Badges</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $estatisticas['total_pontos']; ?></div>
                                    <div class="stat-label">Pontos</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $estatisticas['total_contribuicoes']; ?></div>
                                    <div class="stat-label">Projetos</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Info Card -->
                    <div class="profile-card" data-aos="fade-right" data-aos-delay="100">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-info-circle"></i>
                                Informações Pessoais
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="info-value">
                                        <?php if ($funcionarioCompleto['ativo'] == 1): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">CPF</span>
                                    <span class="info-value">
                                        <?php 
                                        $cpf = $funcionarioCompleto['cpf'] ?? '';
                                        if ($cpf) {
                                            echo substr($cpf, 0, 3) . '.***.**-' . substr($cpf, -2);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">RG</span>
                                    <span class="info-value"><?php echo htmlspecialchars($funcionarioCompleto['rg'] ?? '-'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Data de Cadastro</span>
                                    <span class="info-value">
                                        <?php 
                                        if ($funcionarioCompleto['criado_em']) {
                                            echo date('d/m/Y', strtotime($funcionarioCompleto['criado_em']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Última Alteração de Senha</span>
                                    <span class="info-value">
                                        <?php 
                                        if ($funcionarioCompleto['senha_alterada_em']) {
                                            echo date('d/m/Y H:i', strtotime($funcionarioCompleto['senha_alterada_em']));
                                        } else {
                                            echo 'Nunca alterada';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="profile-main">
                    <!-- Badges Section -->
                    <div class="profile-card" data-aos="fade-up">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-medal"></i>
                                Badges e Conquistas
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <?php if (empty($badges)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-medal"></i>
                                    <p>Você ainda não conquistou nenhuma badge</p>
                                </div>
                            <?php else: ?>
                                <div class="badges-grid">
                                    <?php foreach ($badges as $badge): ?>
                                        <?php
                                        $nivel = strtolower($badge['badge_nivel'] ?? 'bronze');
                                        $iconClass = $nivel === 'ouro' ? 'gold' : ($nivel === 'prata' ? 'silver' : 'bronze');
                                        ?>
                                        <div class="badge-card">
                                            <div class="badge-points"><?php echo $badge['pontos'] ?? 0; ?> pts</div>
                                            <div class="badge-icon <?php echo $iconClass; ?>">
                                                <i class="<?php echo $badge['badge_icone'] ?? 'fas fa-award'; ?>"></i>
                                            </div>
                                            <div class="badge-name"><?php echo htmlspecialchars($badge['badge_nome']); ?></div>
                                            <div class="badge-date">
                                                <?php echo date('d/m/Y', strtotime($badge['data_conquista'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contributions Section -->
                    <div class="profile-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-project-diagram"></i>
                                Contribuições e Projetos
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <?php if (empty($contribuicoes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-project-diagram"></i>
                                    <p>Nenhuma contribuição registrada</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($contribuicoes as $contrib): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-dot"></div>
                                            <div class="timeline-content">
                                                <div class="timeline-header">
                                                    <div>
                                                        <div class="timeline-title"><?php echo htmlspecialchars($contrib['titulo']); ?></div>
                                                        <span class="timeline-type"><?php echo htmlspecialchars($contrib['tipo'] ?? 'PROJETO'); ?></span>
                                                    </div>
                                                </div>
                                                <?php if ($contrib['descricao']): ?>
                                                    <div class="timeline-description">
                                                        <?php echo htmlspecialchars($contrib['descricao']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="timeline-date">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php 
                                                    echo date('d/m/Y', strtotime($contrib['data_inicio']));
                                                    if ($contrib['data_fim']) {
                                                        echo ' até ' . date('d/m/Y', strtotime($contrib['data_fim']));
                                                    } else {
                                                        echo ' - Em andamento';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Perfil -->
    <div class="modal-custom" id="modalEdicao">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Editar Perfil</h2>
                <button class="modal-close-custom" onclick="fecharModalEdicao()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formEdicao">
                    <div class="form-group">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" class="form-control-custom" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['nome']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control-custom" id="email" name="email" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['email']); ?>" required>
                        <div class="form-text">Este email é usado para login no sistema</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">CPF</label>
                        <input type="text" class="form-control-custom" id="cpf" name="cpf" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['cpf'] ?? ''); ?>"
                               placeholder="000.000.000-00" maxlength="14">
                    </div>

                    <div class="form-group">
                        <label class="form-label">RG</label>
                        <input type="text" class="form-control-custom" id="rg" name="rg" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['rg'] ?? ''); ?>">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModalEdicao()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-save"></i>
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Alterar Senha -->
    <div class="modal-custom" id="modalSenha">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Alterar Senha</h2>
                <button class="modal-close-custom" onclick="fecharModalSenha()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formSenha">
                    <div class="form-group">
                        <label class="form-label">Senha Atual</label>
                        <input type="password" class="form-control-custom" id="senhaAtual" name="senha_atual" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" class="form-control-custom" id="novaSenha" name="nova_senha" required>
                        <div class="form-text">Mínimo 6 caracteres. Use letras, números e símbolos para maior segurança.</div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control-custom" id="confirmarSenha" name="confirmar_senha" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModalSenha()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-key"></i>
                            Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <!-- JavaScript customizado para os botões do header -->
    <script>
        function toggleSearch() {
            // Para a página de perfil, você pode redirecionar para o dashboard
            window.location.href = 'dashboard.php';
        }
        
        function toggleNotifications() {
            // Implementar painel de notificações
            console.log('Painel de notificações');
            alert('Painel de notificações em desenvolvimento');
        }
    </script>

    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Configuração inicial
        document.addEventListener('DOMContentLoaded', function() {
            // Máscaras
            $('#cpf').mask('000.000.000-00');

            // Event listeners
            document.getElementById('formEdicao').addEventListener('submit', salvarPerfil);
            document.getElementById('formSenha').addEventListener('submit', alterarSenha);
            document.getElementById('novaSenha').addEventListener('input', verificarForcaSenha);
        });

        // Loading functions
        function showLoading(texto = 'Processando...') {
            const overlay = document.getElementById('loadingOverlay');
            const loadingText = overlay.querySelector('.loading-text');
            loadingText.textContent = texto;
            overlay.classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Modal functions
        function abrirModalEdicao() {
            document.getElementById('modalEdicao').classList.add('show');
        }

        function fecharModalEdicao() {
            document.getElementById('modalEdicao').classList.remove('show');
        }

        function abrirModalSenha() {
            document.getElementById('modalSenha').classList.add('show');
            document.getElementById('formSenha').reset();
            document.getElementById('passwordStrength').className = 'password-strength-bar';
        }

        function fecharModalSenha() {
            document.getElementById('modalSenha').classList.remove('show');
        }

        // Salvar perfil
        function salvarPerfil(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {
                id: <?php echo $usuarioLogado['id']; ?>
            };
            
            // Converte FormData para objeto
            for (let [key, value] of formData.entries()) {
                dados[key] = value;
            }
            
            showLoading('Salvando alterações...');
            
            $.ajax({
                url: '../api/funcionarios_atualizar.php',
                method: 'PUT',
                data: JSON.stringify(dados),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        alert('Perfil atualizado com sucesso!');
                        fecharModalEdicao();
                        // Recarrega a página para mostrar os dados atualizados
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', xhr.responseText);
                    alert('Erro ao salvar alterações');
                }
            });
        }

        // Alterar senha
        function alterarSenha(e) {
            e.preventDefault();
            
            const senhaAtual = document.getElementById('senhaAtual').value;
            const novaSenha = document.getElementById('novaSenha').value;
            const confirmarSenha = document.getElementById('confirmarSenha').value;
            
            // Validações
            if (novaSenha.length < 6) {
                alert('A nova senha deve ter pelo menos 6 caracteres');
                return;
            }
            
            if (novaSenha !== confirmarSenha) {
                alert('As senhas não coincidem');
                return;
            }
            
            if (senhaAtual === novaSenha) {
                alert('A nova senha deve ser diferente da senha atual');
                return;
            }
            
            showLoading('Alterando senha...');
            
            $.ajax({
                url: '../api/alterar_senha.php',
                method: 'POST',
                data: JSON.stringify({
                    senha_atual: senhaAtual,
                    nova_senha: novaSenha
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        alert('Senha alterada com sucesso!');
                        fecharModalSenha();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', xhr.responseText);
                    alert('Erro ao alterar senha');
                }
            });
        }

        // Verifica força da senha
        function verificarForcaSenha() {
            const senha = document.getElementById('novaSenha').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            
            // Critérios
            if (senha.length >= 6) strength++;
            if (senha.length >= 10) strength++;
            if (/[a-z]/.test(senha) && /[A-Z]/.test(senha)) strength++;
            if (/[0-9]/.test(senha)) strength++;
            if (/[^a-zA-Z0-9]/.test(senha)) strength++;
            
            // Atualiza barra
            strengthBar.className = 'password-strength-bar';
            
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }

        // Fecha modal ao clicar fora
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-custom')) {
                event.target.classList.remove('show');
            }
        });

        // Tecla ESC fecha modais
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModalEdicao();
                fecharModalSenha();
            }
        });

        console.log('Página de perfil carregada com Header Component!');
    </script>
</body>
</html>