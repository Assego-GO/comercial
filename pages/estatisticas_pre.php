<?php
/**
 * Página de Estatísticas Avançadas - Sistema ASSEGO
 * pages/estatisticas_pre.php - Versão Melhorada
 */

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
$page_title = 'Estatísticas Avançadas - ASSEGO';

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'estatisticas',
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <style>
        /* Variáveis CSS ASSEGO - Paleta Moderna */
        :root {
            --primary-blue: #1e40af;
            --primary-blue-light: #dbeafe;
            --accent-gold: #f59e0b;
            --accent-gold-light: #fef3c7;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --purple: #8b5cf6;
            --indigo: #6366f1;
            
            /* Tons de cinza modernos */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;

            /* Sombras modernas */
            --shadow-xs: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);

            /* Bordas arredondadas */
            --rounded-lg: 0.75rem;
            --rounded-xl: 1rem;
            --rounded-2xl: 1.5rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            line-height: 1.6;
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

        /* Page Header Limpo e Moderno */
        .page-header {
            background: white;
            border-radius: var(--rounded-2xl);
            padding: 3rem 2rem;
            margin-bottom: 2.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, var(--primary-blue-light) 0%, transparent 70%);
            opacity: 0.4;
            transform: translate(50px, -50px);
        }

        .page-title {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 2;
            color: var(--gray-900);
            letter-spacing: -0.025em;
        }

        .page-subtitle {
            font-size: 1.125rem;
            color: var(--gray-600);
            position: relative;
            z-index: 2;
            font-weight: 400;
        }

        .page-title i {
            color: var(--primary-blue);
            margin-right: 1rem;
        }

        /* Seções organizadas */
        .stats-section {
            margin-bottom: 4rem;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-blue);
        }

        .section-subtitle {
            color: var(--gray-600);
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        /* KPI Cards Modernos - MELHORADOS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .kpi-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: white;
            border-radius: var(--rounded-xl);
            padding: 2.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .kpi-card.mini {
            padding: 2rem;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--accent-gold) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .kpi-card:hover::before {
            transform: scaleX(1);
        }

        .kpi-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1.5rem;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            position: relative;
        }

        .kpi-icon.mini {
            width: 56px;
            height: 56px;
            margin-bottom: 1.25rem;
            font-size: 1.5rem;
        }

        .kpi-icon.primary { 
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--indigo) 100%);
        }
        .kpi-icon.success { 
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%); 
        }
        .kpi-icon.gold { 
            background: linear-gradient(135deg, var(--accent-gold) 0%, #d97706 100%); 
        }
        .kpi-icon.info { 
            background: linear-gradient(135deg, var(--info) 0%, var(--purple) 100%); 
        }
        .kpi-icon.danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
        }

        .kpi-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
            letter-spacing: -0.025em;
        }

        .kpi-value.mini {
            font-size: 2rem;
        }

        .kpi-label {
            font-size: 0.95rem;
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Charts Grid Melhorado */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(520px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .charts-grid.wide {
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        }

        .chart-card {
            background: white;
            border-radius: var(--rounded-xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            position: relative;
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.025em;
        }

        .chart-title i {
            color: var(--primary-blue);
            font-size: 1.25rem;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .chart-container.small {
            height: 320px;
        }

        .chart-container.large {
            height: 450px;
        }

        /* Botão para mostrar todos os bairros */
        .btn-ver-todos {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--indigo) 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: var(--rounded-lg);
            font-weight: 600;
            letter-spacing: 0.025em;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-ver-todos:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        /* Modal de bairros */
        .modal-bairros {
            backdrop-filter: blur(8px);
        }

        .modal-bairros .modal-content {
            border-radius: var(--rounded-xl);
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-bairros .modal-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--indigo) 100%);
            color: white;
            border-radius: var(--rounded-xl) var(--rounded-xl) 0 0;
            padding: 1.5rem;
        }

        .modal-bairros .modal-title {
            font-weight: 700;
            font-size: 1.25rem;
        }

        .bairros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            max-height: 60vh;
            overflow-y: auto;
            padding: 1rem;
        }

        .bairro-item {
            background: var(--gray-50);
            border-radius: var(--rounded-lg);
            padding: 1rem;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .bairro-item:hover {
            background: var(--primary-blue-light);
            transform: translateY(-2px);
        }

        .bairro-nome {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .bairro-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        /* Loading States Modernos */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid var(--gray-200);
            border-top: 3px solid var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        .loading-text {
            color: var(--gray-600);
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Footer informativo */
        .stats-footer {
            background: white;
            border-radius: var(--rounded-xl);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            margin-top: 2rem;
        }

        .stats-footer small {
            color: var(--gray-500);
            font-weight: 500;
        }

        /* Responsividade Melhorada */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .page-header {
                padding: 2rem 1.5rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .chart-card {
                padding: 1.5rem;
            }
            
            .chart-container {
                height: 300px;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .bairros-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .page-title {
                font-size: 1.75rem;
            }

            .kpi-card {
                padding: 1.5rem;
            }
            
            .kpi-value {
                font-size: 1.875rem;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animações suaves */
        .fade-in-up {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease-out forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay Melhorado -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando estatísticas avançadas...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header Limpo -->
            <div class="page-header" data-aos="fade-up">
                <h1 class="page-title">
                    <i class="fas fa-chart-line"></i>
                    Estatísticas Avançadas
                </h1>
                <p class="page-subtitle">
                    Análise completa e detalhada de todos os dados da ASSEGO com visualizações interativas e insights estratégicos
                </p>
            </div>

            <!-- ===== SEÇÃO 1: RESUMO GERAL ===== -->
            <div class="stats-section" data-aos="fade-up" data-aos-delay="100">
                <h2 class="section-title">
                    <i class="fas fa-tachometer-alt"></i>
                    Resumo Geral
                </h2>
                <p class="section-subtitle">Principais números da associação</p>

                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="kpi-value" id="totalAssociados">-</div>
                        <div class="kpi-label">Total de Associados</div>
                    </div>
                    
                    <div class="kpi-card">
                        <div class="kpi-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="kpi-value" id="totalAtiva">-</div>
                        <div class="kpi-label">Categoria Ativa</div>
                    </div>
                    
                    <div class="kpi-card">
                        <div class="kpi-icon gold">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="kpi-value" id="totalReserva">-</div>
                        <div class="kpi-label">Categoria Reserva</div>
                    </div>
                    
                    <div class="kpi-card">
                        <div class="kpi-icon info">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="kpi-value" id="totalPensionista">-</div>
                        <div class="kpi-label">Pensionistas</div>
                    </div>
                </div>
            </div>

            <!-- ===== SEÇÃO 2: ANÁLISE INSTITUCIONAL ===== -->
            <div class="stats-section" data-aos="fade-up" data-aos-delay="200">
                <h2 class="section-title">
                    <i class="fas fa-building"></i>
                    Análise Institucional
                </h2>
                <p class="section-subtitle">Distribuição por patentes, corporações e unidades</p>

                <div class="charts-grid">
                    <!-- Gráfico de Patentes -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-medal"></i>
                            Distribuição por Patentes
                        </h3>
                        <div class="chart-container">
                            <canvas id="patentesChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Corporações -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-shield-alt"></i>
                            Análise por Corporação
                        </h3>
                        <div class="chart-container">
                            <canvas id="corporacoesChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Patentes por Corporação -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-layer-group"></i>
                            Patentes vs Corporações
                        </h3>
                        <div class="chart-container">
                            <canvas id="patentesCorpoChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Lotações -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-sitemap"></i>
                            Top Lotações/Unidades
                        </h3>
                        <div class="chart-container">
                            <canvas id="lotacoesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== SEÇÃO 3: PERFIL DEMOGRÁFICO ===== -->
            <div class="stats-section" data-aos="fade-up" data-aos-delay="300">
                <h2 class="section-title">
                    <i class="fas fa-users-demographic"></i>
                    Perfil Demográfico
                </h2>
                <p class="section-subtitle">Análise por idade, localização e perfil social</p>

                <!-- KPIs de Perfil Social -->
                <div class="kpi-mini-grid">
                    <div class="kpi-card mini">
                        <div class="kpi-icon mini danger">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="kpi-value mini" id="totalDoadoras">-</div>
                        <div class="kpi-label">Doadoras de Sangue</div>
                    </div>
                    
                    <div class="kpi-card mini">
                        <div class="kpi-icon mini success">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <div class="kpi-value mini" id="situacaoRegular">-</div>
                        <div class="kpi-label">Situação Regular</div>
                    </div>
                    
                    <div class="kpi-card mini">
                        <div class="kpi-icon mini info">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="kpi-value mini" id="vinculoServidor">-</div>
                        <div class="kpi-label">Vínculo Servidor</div>
                    </div>
                </div>

                <div class="charts-grid wide">
                    <!-- Gráfico de Faixa Etária -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-birthday-cake"></i>
                            Distribuição por Idade
                        </h3>
                        <div class="chart-container">
                            <canvas id="idadeChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Doadoras -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-heart"></i>
                            Doadoras de Sangue
                        </h3>
                        <div class="chart-container">
                            <canvas id="doadorasChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Situação Financeira -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-wallet"></i>
                            Situação Financeira
                        </h3>
                        <div class="chart-container">
                            <canvas id="situacaoFinanceiraChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== SEÇÃO 4: ANÁLISE GEOGRÁFICA ===== -->
            <div class="stats-section" data-aos="fade-up" data-aos-delay="400">
                <h2 class="section-title">
                    <i class="fas fa-map-marked-alt"></i>
                    Análise Geográfica
                </h2>
                <p class="section-subtitle">Distribuição territorial por cidades e bairros</p>

                <div class="charts-grid">
                    <!-- Gráfico de Cidades -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Principais Cidades
                        </h3>
                        <div class="chart-container">
                            <canvas id="cidadesChart"></canvas>
                        </div>
                    </div>

                    <!-- Gráfico de Bairros com botão para ver todos -->
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-home"></i>
                            Top Bairros - Goiânia
                        </h3>
                        <div class="chart-container">
                            <canvas id="bairrosChart"></canvas>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-ver-todos" onclick="mostrarTodosBairros()">
                                <i class="fas fa-list me-2"></i>
                                Ver Todos os Bairros
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer com informações -->
            <div class="stats-footer" data-aos="fade-up" data-aos-delay="600">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    Dados atualizados em tempo real • Última atualização: <span id="ultimaAtualizacao">-</span>
                </small>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar todos os bairros -->
    <div class="modal fade modal-bairros" id="modalBairros" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-home me-2"></i>
                        Todos os Bairros de Goiânia
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="pesquisarBairro" placeholder="🔍 Pesquisar bairro...">
                    </div>
                    <div class="bairros-grid" id="bairrosContainer">
                        <!-- Bairros serão carregados aqui -->
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
        // Configuração global do Chart.js para melhor UX
        Chart.defaults.font.family = 'Inter';
        Chart.defaults.font.size = 12;
        Chart.defaults.font.weight = '500';
        Chart.defaults.color = '#64748b';
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 20;

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 600,
                easing: 'ease-out-quart',
                once: true,
                offset: 50
            });

            carregarEstatisticas();
        });

        // Variáveis globais para os gráficos
        let charts = {};
        let todosOsBairros = [];
        
        // Paleta de cores moderna e harmoniosa
        const paletaCoresModerna = [
            '#1e40af', '#f59e0b', '#10b981', '#ef4444', 
            '#8b5cf6', '#06b6d4', '#f97316', '#84cc16',
            '#ec4899', '#6b7280', '#374151', '#111827'
        ];

        const coresCategoria = {
            ativa: '#10b981',
            reserva: '#f59e0b', 
            pensionista: '#06b6d4'
        };

        // Função principal para carregar estatísticas
        async function carregarEstatisticas() {
            try {
                const response = await fetch('../api/estatisticas_avancadas.php');
                const result = await response.json();

                if (result.status === 'success') {
                    const data = result.data;
                    console.log('Dados recebidos:', data);
                    
                    // Armazena todos os bairros para o modal
                    todosOsBairros = data.todos_bairros || data.bairros_goiania || [];
                    
                    // Atualiza KPIs
                    atualizarKPIs(data.resumo_geral);
                    atualizarKPIsExtendidos(data);
                    
                    // Cria todos os gráficos com delay progressivo
                    setTimeout(() => criarGraficoPatentes(data.por_patente), 100);
                    setTimeout(() => criarGraficoCorporacoes(data.por_corporacao_detalhada), 200);
                    setTimeout(() => criarGraficoIdade(data.por_faixa_etaria), 300);
                    setTimeout(() => criarGraficoCidades(data.por_cidade), 400);
                    setTimeout(() => criarGraficoLotacoes(data.por_lotacao), 500);
                    setTimeout(() => criarGraficoPatentesCorpo(data.patentes_por_corporacao), 600);
                    
                    // Novos gráficos
                    setTimeout(() => criarGraficoDoadoras(data.doadoras_sangue), 700);
                    setTimeout(() => criarGraficoSituacaoFinanceira(data.situacao_financeira), 800);
                    setTimeout(() => criarGraficoBairros(data.bairros_goiania), 900);
                    
                    // Atualiza timestamp
                    document.getElementById('ultimaAtualizacao').textContent = 
                        new Date().toLocaleString('pt-BR');

                    // Esconde loading com delay
                    setTimeout(() => {
                        document.getElementById('loadingOverlay').style.opacity = '0';
                        setTimeout(() => {
                            document.getElementById('loadingOverlay').style.display = 'none';
                        }, 300);
                    }, 1200);
                    
                } else {
                    console.error('Erro:', result.message);
                    showError('Erro ao carregar estatísticas');
                }
            } catch (error) {
                console.error('Erro de rede:', error);
                showError('Erro de conexão');
            }
        }

        // Atualiza KPIs no topo
        function atualizarKPIs(resumo) {
            animateValue('totalAssociados', 0, resumo.total_associados, 1500);
            animateValue('totalAtiva', 0, resumo.ativa, 1500);
            animateValue('totalReserva', 0, resumo.reserva, 1500);
            animateValue('totalPensionista', 0, resumo.pensionista, 1500);
        }

        // Atualiza KPIs estendidos
        function atualizarKPIsExtendidos(data) {
            // Doadoras de sangue
            const doadoras = data.doadoras_sangue?.find(item => 
                item.status_doador === 'Doador')?.quantidade || 0;
            animateValue('totalDoadoras', 0, doadoras, 1800);

            // Situação regular (assumindo 'Adimplente')
            const regular = data.situacao_financeira?.find(item => 
                item.situacao_financeira === 'Adimplente')?.quantidade || 0;
            animateValue('situacaoRegular', 0, regular, 1800);

            // Vínculo servidor (assumindo 'Ativa')
            const servidor = data.vinculo_servidor?.find(item => 
                item.vinculo_servidor === 'Ativa')?.quantidade || 0;
            animateValue('vinculoServidor', 0, servidor, 1800);
        }

        // Animação dos números KPI
        function animateValue(elementId, start, end, duration) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const range = end - start;
            const increment = end > start ? 1 : -1;
            const stepTime = Math.abs(Math.floor(duration / range));
            let current = start;
            
            const timer = setInterval(() => {
                current += increment * Math.ceil(range / 50);
                if (current >= end) {
                    current = end;
                    clearInterval(timer);
                }
                element.textContent = new Intl.NumberFormat('pt-BR').format(Math.floor(current));
            }, stepTime);
        }

        // Modal para mostrar todos os bairros
        function mostrarTodosBairros() {
            const container = document.getElementById('bairrosContainer');
            
            if (todosOsBairros.length === 0) {
                container.innerHTML = '<div class="text-center">Nenhum bairro encontrado</div>';
            } else {
                let html = '';
                todosOsBairros.forEach((bairro, index) => {
                    html += `
                        <div class="bairro-item" data-bairro="${bairro.bairro?.toLowerCase() || ''}">
                            <div class="bairro-nome">${bairro.bairro || 'Não informado'}</div>
                            <div class="bairro-stats">
                                <span><i class="fas fa-users me-1"></i>${bairro.quantidade || 0} associados</span>
                                <span><strong>${bairro.percentual || '0'}%</strong></span>
                            </div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }
            
            // Abre o modal
            new bootstrap.Modal(document.getElementById('modalBairros')).show();
        }

        // Pesquisa nos bairros
        document.getElementById('pesquisarBairro').addEventListener('input', function(e) {
            const termo = e.target.value.toLowerCase();
            const bairrosItems = document.querySelectorAll('.bairro-item');
            
            bairrosItems.forEach(item => {
                const nomeBairro = item.getAttribute('data-bairro');
                if (nomeBairro.includes(termo)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Gráfico de Barras Horizontais - Patentes
        function criarGraficoPatentes(dados) {
            const ctx = document.getElementById('patentesChart').getContext('2d');
            const top8 = dados.slice(0, 8);
            
            charts.patentes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top8.map(item => {
                        let nome = item.patente;
                        if (nome.includes('Segundo')) nome = '2º Sargento';
                        if (nome.includes('Primeiro')) nome = '1º Sargento';
                        if (nome.includes('Terceiro')) nome = '3º Sargento';
                        if (nome.includes('Soldado')) nome = 'Soldado';
                        if (nome.includes('Subtenente')) nome = 'Subtenente';
                        if (nome.includes('Capitão')) nome = 'Capitão';
                        if (nome.includes('Cabo')) nome = 'Cabo';
                        return nome.length > 15 ? nome.substring(0, 12) + '...' : nome;
                    }),
                    datasets: [{
                        data: top8.map(item => item.quantidade),
                        backgroundColor: paletaCoresModerna.slice(0, top8.length),
                        borderRadius: 12,
                        borderSkipped: false,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                title: function(context) {
                                    return top8[context[0].dataIndex].patente;
                                },
                                label: function(context) {
                                    const item = top8[context.dataIndex];
                                    return `${item.quantidade} pessoas (${item.percentual}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { font: { weight: '600' } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { weight: '700', size: 11 } }
                        }
                    },
                    animation: {
                        delay: (context) => context.dataIndex * 150,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Gráfico de Barras Empilhadas - Corporações
        function criarGraficoCorporacoes(dados) {
            const ctx = document.getElementById('corporacoesChart').getContext('2d');
            
            const grupos = {};
            dados.forEach(item => {
                if (!grupos[item.corporacao_grupo]) {
                    grupos[item.corporacao_grupo] = { total: 0, ativa: 0, reserva: 0, pensionista: 0 };
                }
                grupos[item.corporacao_grupo].total += parseInt(item.quantidade);
                grupos[item.corporacao_grupo].ativa += parseInt(item.ativa);
                grupos[item.corporacao_grupo].reserva += parseInt(item.reserva);
                grupos[item.corporacao_grupo].pensionista += parseInt(item.pensionista);
            });

            const labels = Object.keys(grupos).map(label => {
                if (label.includes('Polícia')) return 'POLÍCIA MILITAR';
                if (label.includes('Bombeiros')) return 'BOMBEIROS';
                return label.toUpperCase();
            });
            
            charts.corporacoes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'ATIVA',
                            data: Object.keys(grupos).map(label => grupos[label].ativa),
                            backgroundColor: '#22c55e',
                            borderRadius: 8,
                            borderSkipped: false,
                        },
                        {
                            label: 'RESERVA',
                            data: Object.keys(grupos).map(label => grupos[label].reserva),
                            backgroundColor: '#f59e0b',
                            borderRadius: 8,
                            borderSkipped: false,
                        },
                        {
                            label: 'PENSIONISTA',
                            data: Object.keys(grupos).map(label => grupos[label].pensionista),
                            backgroundColor: '#06b6d4',
                            borderRadius: 8,
                            borderSkipped: false,
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: { 
                            position: 'top',
                            labels: {
                                padding: 25,
                                font: { weight: 'bold', size: 12 },
                                usePointStyle: true
                            }
                        }
                    },
                    scales: {
                        x: { 
                            stacked: true,
                            grid: { display: false },
                            ticks: { font: { weight: 'bold' } }
                        },
                        y: { 
                            stacked: true, 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { 
                                font: { weight: '500' },
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    animation: {
                        delay: (context) => context.datasetIndex * 200 + context.dataIndex * 100,
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Gráfico de Barras - Faixa Etária
        function criarGraficoIdade(dados) {
            const ctx = document.getElementById('idadeChart').getContext('2d');
            
            const dadosComEmojis = dados.map(item => ({
                ...item,
                emoji: getEmojiPorIdade(item.faixa_etaria),
                label: formatarIdade(item.faixa_etaria)
            }));
            
            charts.idade = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dadosComEmojis.map(item => `${item.emoji} ${item.label}`),
                    datasets: [{
                        data: dadosComEmojis.map(item => item.quantidade),
                        backgroundColor: ['#3b82f6', '#f59e0b', '#22c55e', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316'],
                        borderRadius: 12,
                        borderSkipped: false,
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                label: function(context) {
                                    const item = dadosComEmojis[context.dataIndex];
                                    return `${item.quantidade.toLocaleString('pt-BR')} pessoas (${item.percentual}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { weight: 'bold', size: 10 } }
                        },
                        y: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { 
                                font: { weight: '600' },
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    animation: {
                        delay: (context) => context.dataIndex * 200,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        function getEmojiPorIdade(faixa) {
            if (faixa.includes('18-29')) return '👶';
            if (faixa.includes('30-39')) return '🧒';
            if (faixa.includes('40-49')) return '👨';
            if (faixa.includes('50-59')) return '👴';
            if (faixa.includes('60-69')) return '👴';
            if (faixa.includes('70+')) return '👴';
            return '👤';
        }

        function formatarIdade(faixa) {
            if (faixa.includes('18-29')) return '18-29 ANOS';
            if (faixa.includes('30-39')) return '30-39 ANOS'; 
            if (faixa.includes('40-49')) return '40-49 ANOS';
            if (faixa.includes('50-59')) return '50-59 ANOS';
            if (faixa.includes('60-69')) return '60-69 ANOS';
            if (faixa.includes('70+')) return '70+ ANOS';
            return faixa.toUpperCase();
        }

        // Gráfico de Barras Horizontais - Cidades
        function criarGraficoCidades(dados) {
            const ctx = document.getElementById('cidadesChart').getContext('2d');
            const top8 = dados.slice(0, 8);
            
            charts.cidades = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top8.map(item => item.cidade.toUpperCase()),
                    datasets: [{
                        data: top8.map(item => item.quantidade),
                        backgroundColor: paletaCoresModerna.slice(0, top8.length),
                        borderRadius: 12,
                        borderSkipped: false,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                label: function(context) {
                                    const item = top8[context.dataIndex];
                                    return `${item.quantidade.toLocaleString('pt-BR')} associados (${item.percentual}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { 
                                font: { weight: '600' },
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { weight: 'bold', size: 10 } }
                        }
                    },
                    animation: {
                        delay: (context) => context.dataIndex * 150,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Gráfico de Barras - Lotações
        function criarGraficoLotacoes(dados) {
            const ctx = document.getElementById('lotacoesChart').getContext('2d');
            const top8 = dados.slice(0, 8);
            
            charts.lotacoes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top8.map((item, index) => {
                        let nome = item.lotacao.toUpperCase();
                        if (nome.includes('GERENCIA') || nome.includes('GERÊNCIA')) nome = nome.replace(/GERENCIA|GERÊNCIA/gi, 'GER.');
                        if (nome.includes('COMANDO')) nome = nome.replace(/COMANDO/gi, 'CMD.');
                        return `${index + 1}º ${nome.length > 20 ? nome.substring(0, 17) + '...' : nome}`;
                    }),
                    datasets: [{
                        data: top8.map(item => item.quantidade),
                        backgroundColor: ['#3b82f6', '#f59e0b', '#22c55e', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#ec4899'],
                        borderRadius: 12,
                        borderSkipped: false,
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                title: function(context) {
                                    return top8[context[0].dataIndex].lotacao;
                                },
                                label: function(context) {
                                    const item = top8[context.dataIndex];
                                    const posicao = context.dataIndex + 1;
                                    return `${posicao}º lugar com ${item.quantidade.toLocaleString('pt-BR')} associados`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45,
                                font: { weight: 'bold', size: 9 }
                            },
                            grid: { display: false }
                        },
                        y: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { 
                                font: { weight: '600' },
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    animation: {
                        delay: (context) => context.dataIndex * 150,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Gráfico de Barras Agrupadas - Patentes por Corporação
        function criarGraficoPatentesCorpo(dados) {
            const ctx = document.getElementById('patentesCorpoChart').getContext('2d');
            
            const todasPatentes = new Set();
            Object.values(dados).forEach(corpo => {
                corpo.forEach(item => todasPatentes.add(item.patente));
            });
            
            const patentesArray = Array.from(todasPatentes).slice(0, 6);
            
            const datasets = [];
            const coresCorpo = {
                'PM': '#3b82f6',
                'BM': '#ef4444', 
                'Outros': '#f59e0b'
            };

            Object.keys(dados).forEach(corporacao => {
                const dadosCorpo = patentesArray.map(patente => {
                    const item = dados[corporacao].find(p => p.patente === patente);
                    return item ? item.quantidade : 0;
                });
                
                datasets.push({
                    label: `${corporacao}`,
                    data: dadosCorpo,
                    backgroundColor: coresCorpo[corporacao] || '#64748b',
                    borderRadius: 8,
                    borderSkipped: false,
                });
            });
            
            charts.patentesCorpo = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: patentesArray.map(patente => {
                        let nome = patente;
                        if (nome.includes('Segundo')) nome = '2º SARG';
                        if (nome.includes('Primeiro')) nome = '1º SARG';
                        if (nome.includes('Terceiro')) nome = '3º SARG';
                        if (nome.includes('Subtenente')) nome = 'SUBTEN';
                        if (nome.includes('Soldado')) nome = 'SOLDADO';
                        if (nome.includes('Capitão')) nome = 'CAPITÃO';
                        if (nome.includes('Cabo')) nome = 'CABO';
                        return nome;
                    }),
                    datasets: datasets
                },
                options: {
                    plugins: {
                        legend: { 
                            position: 'top',
                            labels: {
                                padding: 25,
                                font: { weight: 'bold', size: 12 },
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                label: function(context) {
                                    const corporacao = context.dataset.label;
                                    const valor = context.parsed.y;
                                    return `${corporacao}: ${valor.toLocaleString('pt-BR')} pessoas`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { font: { weight: 'bold', size: 11 } },
                            grid: { display: false }
                        },
                        y: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { 
                                font: { weight: '600' },
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    animation: {
                        delay: (context) => context.datasetIndex * 200 + context.dataIndex * 50,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Gráfico de Doughnut - Doadoras de Sangue
        function criarGraficoDoadoras(dados) {
            const ctx = document.getElementById('doadorasChart').getContext('2d');
            
            charts.doadoras = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: dados.map(item => item.status_doador.toUpperCase()),
                    datasets: [{
                        data: dados.map(item => item.quantidade),
                        backgroundColor: ['#ef4444', '#6b7280', '#94a3b8'],
                        borderWidth: 4,
                        borderColor: '#ffffff',
                        hoverOffset: 8
                    }]
                },
                options: {
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { 
                                padding: 20,
                                font: { weight: 'bold', size: 11 }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                label: function(context) {
                                    const item = dados[context.dataIndex];
                                    return `${item.quantidade.toLocaleString('pt-BR')} pessoas (${item.percentual}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        duration: 1500,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Gráfico de Situação Financeira
        function criarGraficoSituacaoFinanceira(dados) {
            const ctx = document.getElementById('situacaoFinanceiraChart').getContext('2d');
            
            charts.situacaoFinanceira = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dados.map(item => item.situacao_financeira.toUpperCase()),
                    datasets: [{
                        data: dados.map(item => item.quantidade),
                        backgroundColor: dados.map((item, index) => {
                            if (item.situacao_financeira.toLowerCase().includes('adimplente')) return '#22c55e';
                            if (item.situacao_financeira.toLowerCase().includes('inadimplente')) return '#ef4444';
                            return paletaCoresModerna[index % paletaCoresModerna.length];
                        }),
                        borderRadius: 12,
                        borderSkipped: false,
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                label: function(context) {
                                    const item = dados[context.dataIndex];
                                    return `${item.quantidade.toLocaleString('pt-BR')} pessoas (${item.percentual}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { font: { weight: 'bold', size: 10 } },
                            grid: { display: false }
                        },
                        y: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { 
                                font: { weight: '600' },
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    animation: {
                        delay: (context) => context.dataIndex * 200,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Gráfico de Bairros
        function criarGraficoBairros(dados) {
            const ctx = document.getElementById('bairrosChart').getContext('2d');
            const top8 = dados.slice(0, 8);
            
            charts.bairros = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top8.map(item => item.bairro),
                    datasets: [{
                        data: top8.map(item => item.quantidade),
                        backgroundColor: paletaCoresModerna.slice(0, top8.length),
                        borderRadius: 12,
                        borderSkipped: false,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.9)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 12,
                            callbacks: {
                                label: function(context) {
                                    const item = top8[context.dataIndex];
                                    return `${item.quantidade.toLocaleString('pt-BR')} associados (${item.percentual}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: { 
                                font: { weight: '600' },
                                callback: function(value) {
                                    return value.toLocaleString('pt-BR');
                                }
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { weight: 'bold', size: 10 } }
                        }
                    },
                    animation: {
                        delay: (context) => context.dataIndex * 150,
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }

        // Função para mostrar erros
        function showError(message) {
            document.getElementById('loadingOverlay').style.display = 'none';
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger rounded-xl border-0';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
            `;
            
            document.querySelector('.content-area').insertBefore(
                errorDiv, 
                document.querySelector('.kpi-grid')
            );
        }
    </script>
</body>
</html>