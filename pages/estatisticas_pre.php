<?php
/**
 * Página de Estatísticas Avançadas - Sistema ASSEGO
 * pages/estatisticas_pre.php
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
        /* Variáveis CSS ASSEGO */
        :root {
            --assego-blue: #003C8F;
            --assego-blue-light: #E6F0FF;
            --assego-gold: #FFB800;
            --assego-gold-light: #FFF4E0;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --gradient-primary: linear-gradient(135deg, #003C8F 0%, #002A66 100%);
            --gradient-gold: linear-gradient(135deg, #FFB800 0%, #E5A200 100%);
            --shadow-lg: 0 10px 30px rgba(0, 60, 143, 0.15);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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

        /* Page Header Elegante */
        .page-header {
            background: var(--gradient-primary);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,184,0,0.3)" stroke-width="0.5"/><circle cx="50" cy="50" r="25" fill="none" stroke="rgba(255,184,0,0.2)" stroke-width="0.3"/></svg>');
            opacity: 0.6;
            transform: translate(50px, -50px);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        /* Cards Elegantes */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 60, 143, 0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-gold);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .kpi-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--assego-gold);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .kpi-card:hover::before {
            transform: scaleX(1);
        }

        .kpi-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .kpi-icon.primary { background: var(--gradient-primary); }
        .kpi-icon.gold { background: var(--gradient-gold); }
        .kpi-icon.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .kpi-icon.info { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); }

        .kpi-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--assego-blue);
            margin-bottom: 0.5rem;
        }

        .kpi-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--assego-blue);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .chart-container.small {
            height: 300px;
        }

        .chart-container.large {
            height: 500px;
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--assego-blue-light);
            border-top: 4px solid var(--assego-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                padding: 1rem;
            }
            
            .chart-container {
                height: 300px;
            }
        }

        @media (max-width: 576px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .kpi-card {
                padding: 1rem;
            }
            
            .kpi-value {
                font-size: 1.5rem;
            }
        }

        /* Animações personalizadas */
        .fade-in-up {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Estilo para tabelas */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .data-table th {
            background: var(--assego-blue-light);
            color: var(--assego-blue);
            font-weight: 600;
        }

        .data-table tr:hover {
            background: rgba(0, 60, 143, 0.05);
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="text-muted">Carregando estatísticas avançadas...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <i class="fas fa-chart-line me-3"></i>
                    Estatísticas Avançadas
                </h1>
                <p class="page-subtitle">
                    Análise completa e detalhada de todos os dados da ASSEGO com gráficos interativos e insights avançados
                </p>
            </div>

            <!-- KPIs Resumo -->
            <div class="kpi-grid" data-aos="fade-up">
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

            <!-- Charts Grid -->
            <div class="charts-grid" data-aos="fade-up" data-aos-delay="200">
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
            </div>

            <!-- Charts Avançados -->
            <div class="row" data-aos="fade-up" data-aos-delay="400">
                <!-- Crescimento nos últimos 12 meses -->
                <div class="col-12 mb-4">
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Evolução de Novos Associados (12 meses)
                        </h3>
                        <div class="chart-container large">
                            <canvas id="crescimentoChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Análise por Lotação -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-building"></i>
                            Top Lotações/Unidades
                        </h3>
                        <div class="chart-container">
                            <canvas id="lotacoesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Patentes por Corporação -->
                <div class="col-lg-6 mb-4">
                    <div class="chart-card">
                        <h3 class="chart-title">
                            <i class="fas fa-layer-group"></i>
                            Patentes vs Corporações
                        </h3>
                        <div class="chart-container">
                            <canvas id="patentesCorpoChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer com informações -->
            <div class="text-center mt-5" data-aos="fade-up" data-aos-delay="600">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Dados atualizados em tempo real • Última atualização: <span id="ultimaAtualizacao">-</span>
                </small>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                easing: 'ease-out-cubic',
                once: true
            });

            carregarEstatisticas();
        });

        // Variáveis globais para os gráficos
        let charts = {};
        
        // Cores tema ASSEGO
        const cores = {
            primary: '#003C8F',
            secondary: '#FFB800',
            success: '#28a745',
            danger: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8',
            light: '#f8f9fa',
            dark: '#343a40'
        };

        // Paleta de cores para gráficos
        const paletaCores = [
            '#003C8F', '#FFB800', '#28a745', '#dc3545', 
            '#17a2b8', '#6f42c1', '#fd7e14', '#20c997',
            '#e83e8c', '#6c757d', '#495057', '#212529'
        ];

        // Função principal para carregar estatísticas
        async function carregarEstatisticas() {
            try {
                const response = await fetch('../api/estatisticas_avancadas.php');
                const result = await response.json();

                if (result.status === 'success') {
                    const data = result.data;
                    
                    // Atualiza KPIs
                    atualizarKPIs(data.resumo_geral);
                    
                    // Cria todos os gráficos
                    criarGraficoPatentes(data.por_patente);
                    criarGraficoCorporacoes(data.por_corporacao_detalhada);
                    criarGraficoIdade(data.por_faixa_etaria);
                    criarGraficoCidades(data.por_cidade);
                    criarGraficoCrescimento(data.crescimento_12_meses);
                    criarGraficoLotacoes(data.por_lotacao);
                    criarGraficoPatentesCorpo(data.patentes_por_corporacao);
                    
                    // Atualiza timestamp
                    document.getElementById('ultimaAtualizacao').textContent = 
                        new Date().toLocaleString('pt-BR');

                    // Esconde loading
                    document.getElementById('loadingOverlay').style.display = 'none';
                    
                    console.log('Estatísticas carregadas com sucesso!', data);
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
            document.getElementById('totalAssociados').textContent = 
                new Intl.NumberFormat('pt-BR').format(resumo.total_associados);
            document.getElementById('totalAtiva').textContent = 
                new Intl.NumberFormat('pt-BR').format(resumo.ativa);
            document.getElementById('totalReserva').textContent = 
                new Intl.NumberFormat('pt-BR').format(resumo.reserva);
            document.getElementById('totalPensionista').textContent = 
                new Intl.NumberFormat('pt-BR').format(resumo.pensionista);
        }

        // Gráfico de Pizza - Patentes
        function criarGraficoPatentes(dados) {
            const ctx = document.getElementById('patentesChart').getContext('2d');
            
            // Pega apenas as top 10 patentes
            const top10 = dados.slice(0, 10);
            
            charts.patentes = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: top10.map(item => item.patente),
                    datasets: [{
                        data: top10.map(item => item.quantidade),
                        backgroundColor: paletaCores.slice(0, top10.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20, usePointStyle: true }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const item = top10[context.dataIndex];
                                    return `${item.patente}: ${item.quantidade} (${item.percentual}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Barras - Corporações
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

            const labels = Object.keys(grupos);
            
            charts.corporacoes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Ativa',
                            data: labels.map(label => grupos[label].ativa),
                            backgroundColor: cores.success,
                            borderRadius: 6
                        },
                        {
                            label: 'Reserva',
                            data: labels.map(label => grupos[label].reserva),
                            backgroundColor: cores.secondary,
                            borderRadius: 6
                        },
                        {
                            label: 'Pensionista',
                            data: labels.map(label => grupos[label].pensionista),
                            backgroundColor: cores.info,
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true }
                    }
                }
            });
        }

        // Gráfico de Pizza - Faixa Etária
        function criarGraficoIdade(dados) {
            const ctx = document.getElementById('idadeChart').getContext('2d');
            
            charts.idade = new Chart(ctx, {
                type: 'polarArea',
                data: {
                    labels: dados.map(item => item.faixa_etaria),
                    datasets: [{
                        data: dados.map(item => item.quantidade),
                        backgroundColor: paletaCores.slice(0, dados.length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20 }
                        }
                    }
                }
            });
        }

        // Gráfico de Barras Horizontais - Cidades
        function criarGraficoCidades(dados) {
            const ctx = document.getElementById('cidadesChart').getContext('2d');
            
            charts.cidades = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dados.map(item => item.cidade),
                    datasets: [{
                        label: 'Associados',
                        data: dados.map(item => item.quantidade),
                        backgroundColor: cores.primary,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        }

        // Gráfico de Linha - Crescimento
        function criarGraficoCrescimento(dados) {
            const ctx = document.getElementById('crescimentoChart').getContext('2d');
            
            charts.crescimento = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dados.map(item => `${item.mes_nome}/${item.ano}`),
                    datasets: [{
                        label: 'Novos Associados',
                        data: dados.map(item => item.novos_associados),
                        borderColor: cores.primary,
                        backgroundColor: cores.primary + '20',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Gráfico de Barras - Lotações
        function criarGraficoLotacoes(dados) {
            const ctx = document.getElementById('lotacoesChart').getContext('2d');
            
            // Pega apenas as top 10 lotações
            const top10 = dados.slice(0, 10);
            
            charts.lotacoes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: top10.map(item => {
                        // Encurta nomes muito longos
                        const nome = item.lotacao;
                        return nome.length > 25 ? nome.substring(0, 22) + '...' : nome;
                    }),
                    datasets: [{
                        label: 'Associados',
                        data: top10.map(item => item.quantidade),
                        backgroundColor: cores.secondary,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Gráfico de Barras Agrupadas - Patentes por Corporação
        function criarGraficoPatentesCorpo(dados) {
            const ctx = document.getElementById('patentesCorpoChart').getContext('2d');
            
            // Pega as top 8 patentes mais comuns
            const todasPatentes = new Set();
            Object.values(dados).forEach(corpo => {
                corpo.forEach(item => todasPatentes.add(item.patente));
            });
            
            const patentesArray = Array.from(todasPatentes).slice(0, 8);
            
            const datasets = [];
            const coresCorpo = {
                'PM': cores.primary,
                'BM': cores.danger,
                'Outros': cores.secondary
            };
            
            Object.keys(dados).forEach(corporacao => {
                const dadosCorpo = patentesArray.map(patente => {
                    const item = dados[corporacao].find(p => p.patente === patente);
                    return item ? item.quantidade : 0;
                });
                
                datasets.push({
                    label: corporacao,
                    data: dadosCorpo,
                    backgroundColor: coresCorpo[corporacao] || cores.info,
                    borderRadius: 4
                });
            });
            
            charts.patentesCorpo = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: patentesArray,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45
                            }
                        },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Função para mostrar erros
        function showError(message) {
            document.getElementById('loadingOverlay').style.display = 'none';
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger';
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