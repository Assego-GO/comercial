<?php

/**
 * Página de Gestão de Pecúlio - Sistema ASSEGO
 * pages/gestao_peculio.php
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
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
$page_title = 'Gestão de Pecúlio - ASSEGO';

// Verificar permissões para setor financeiro - APENAS FINANCEIRO E PRESIDÊNCIA
$temPermissaoFinanceiro = false;
$isFinanceiro = false;
$isPresidencia = false;

if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];

    if ($deptId == 5) { // Financeiro
        $temPermissaoFinanceiro = true;
        $isFinanceiro = true;
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoFinanceiro = true;
        $isPresidencia = true;
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'financeiro',
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
            background: linear-gradient(135deg, var(--warning) 0%, #ff8c00 100%);
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

        /* Card Principal */
        .peculio-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        /* Seção de Busca */
        .busca-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 2px solid #dee2e6;
        }

        .busca-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .busca-title i {
            margin-right: 0.75rem;
            color: var(--warning);
        }

        .busca-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .busca-input-group {
            flex: 1;
            min-width: 250px;
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
            border-color: var(--warning);
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }

        .btn-warning {
            background: var(--warning);
            border-color: var(--warning);
            color: #212529;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-warning:hover {
            background: #e0a800;
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

        .btn-success {
            background: var(--success);
            border-color: var(--success);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        /* Dados do Pecúlio */
        .peculio-dados {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--warning);
        }

        .peculio-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
        }

        .peculio-title i {
            margin-right: 0.75rem;
            color: var(--warning);
        }

        .dados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .dados-item {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 12px;
            padding: 1.5rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .dados-item:hover {
            border-color: var(--warning);
            transform: translateY(-2px);
        }

        .dados-label {
            font-weight: 600;
            color: #856404;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
        }

        .dados-label i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        .dados-value {
            color: var(--dark);
            font-size: 1.2rem;
            font-weight: 700;
        }

        .dados-value.data {
            color: #d39e00;
        }

        .dados-value.pendente {
            color: var(--secondary);
            font-style: italic;
        }

        /* Informações do Associado */
        .associado-info {
            background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }

        .associado-nome {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .associado-rg {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        /* Botões de Ação */
        .botoes-acoes {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            text-align: center;
            border: 2px solid #dee2e6;
            display: block !important; /* Força exibição */
        }

        .botoes-acoes h5 {
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .botoes-acoes .btn {
            margin: 0.5rem;
            min-width: 200px;
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
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
            border-left: 4px solid var(--info);
        }

        /* Loading */
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

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light);
            border-top: 4px solid var(--warning);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Toast */
        .toast-container {
            z-index: 9999;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .busca-form {
                flex-direction: column;
                align-items: stretch;
            }

            .dados-grid {
                grid-template-columns: 1fr;
            }

            .botoes-acoes .btn {
                display: block;
                width: 100%;
                margin: 0.5rem 0;
                min-width: auto;
            }
        }

        /* Animações */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
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
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Gestão de Pecúlio</h4>
                    <p class="mb-3">Acesso restrito EXCLUSIVAMENTE ao Setor Financeiro e Presidência.</p>

                    <div class="btn-group">
                        <a href="../pages/financeiro.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>
                            Voltar aos Serviços Financeiros
                        </a>
                        <a href="../pages/dashboard.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-home me-1"></i>
                            Dashboard
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Com Permissão - Conteúdo Normal -->

                <!-- Page Header -->
                <div class="page-header" data-aos="fade-right">
                    <h1 class="page-title">
                        <div class="page-title-icon">
                            <i class="fas fa-piggy-bank"></i>
                        </div>
                        Gestão de Pecúlio
                        <?php if ($isFinanceiro): ?>
                            <small class="text-muted">- Setor Financeiro</small>
                        <?php elseif ($isPresidencia): ?>
                            <small class="text-muted">- Presidência</small>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">
                        Consulte e gerencie informações sobre pecúlio dos associados da ASSEGO
                    </p>
                </div>

                <!-- Alert informativo -->
                <div class="alert-custom alert-info-custom" data-aos="fade-up">
                    <div>
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h6 class="mb-1">Gestão de Pecúlio</h6>
                        <small>
                            Consulte as datas previstas e efetivas de recebimento do pecúlio dos associados.
                        </small>
                    </div>
                </div>

                <!-- Card Principal -->
                <div class="peculio-card" data-aos="fade-up" data-aos-delay="200">

                    <!-- Seção de Busca -->
                    <div class="busca-section" style="position: relative;">
                        <h3 class="busca-title">
                            <i class="fas fa-search"></i>
                            Consultar Pecúlio do Associado
                        </h3>

                        <form class="busca-form" onsubmit="buscarPeculioAssociado(event)">
                            <div class="busca-input-group">
                                <label class="form-label" for="rgBuscaPeculio">RG Militar ou Nome do Associado</label>
                                <input type="text" class="form-control" id="rgBuscaPeculio"
                                    placeholder="Digite o RG militar ou nome completo..." required>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-warning" id="btnBuscarPeculio">
                                    <i class="fas fa-search me-2"></i>
                                    Consultar
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary" onclick="limparBuscaPeculio()">
                                    <i class="fas fa-eraser me-2"></i>
                                    Limpar
                                </button>
                            </div>
                        </form>

                        <!-- Alert para mensagens de busca -->
                        <div id="alertBuscaPeculio" class="alert mt-3" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="alertBuscaPeculioText"></span>
                        </div>

                        <!-- Loading overlay -->
                        <div id="loadingBuscaPeculio" class="loading-overlay" style="display: none;">
                            <div class="loading-spinner mb-3"></div>
                            <p class="text-muted">Consultando informações do pecúlio...</p>
                        </div>
                    </div>

                    <!-- Informações do Associado -->
                    <div id="associadoInfoContainer" class="associado-info fade-in" style="display: none;">
                        <div class="associado-nome" id="associadoNome"></div>
                        <div class="associado-rg" id="associadoRG"></div>
                    </div>

                    <!-- Dados do Pecúlio -->
                    <div id="peculioDadosContainer" class="peculio-dados fade-in" style="display: none;">
                        <h4 class="peculio-title">
                            <i class="fas fa-piggy-bank"></i>
                            Informações do Pecúlio
                        </h4>

                        <div class="dados-grid">
                            <div class="dados-item">
                                <div class="dados-label">
                                    <i class="fas fa-calendar-plus"></i>
                                    Data Prevista para Recebimento
                                </div>
                                <div class="dados-value data" id="dataPrevistaPeculio">
                                    <!-- Data será inserida aqui -->
                                </div>
                            </div>

                            <div class="dados-item">
                                <div class="dados-label">
                                    <i class="fas fa-dollar-sign"></i>
                                    Valor do Pecúlio
                                </div>
                                <div class="dados-value" id="valorPeculio">
                                    <!-- Valor será inserido aqui -->
                                </div>
                            </div>

                            <div class="dados-item">
                                <div class="dados-label">
                                    <i class="fas fa-calendar-check"></i>
                                    Data de Recebimento Efetivo
                                </div>
                                <div class="dados-value data" id="dataRecebimentoPeculio">
                                    <!-- Data será inserida aqui -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botões de Ação - CORRIGIDO -->
                    <div id="botoesAcoesContainer" class="botoes-acoes fade-in" style="display: none;">
                        <h5>
                            <i class="fas fa-tools me-2"></i>
                            Ações Disponíveis
                        </h5>
                        <div>
                            <button type="button" class="btn btn-warning" onclick="editarPeculio()" id="btnEditarPeculio">
                                <i class="fas fa-edit me-2"></i>
                                Editar Dados do Pecúlio
                            </button>
                            <button type="button" class="btn btn-success" onclick="confirmarRecebimento()" id="btnConfirmarRecebimento">
                                <i class="fas fa-check-circle me-2"></i>
                                Confirmar Recebimento
                            </button>
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
                const bsToast = new bootstrap.Toast(toast, {
                    delay: duration
                });
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
        let dadosPeculioAtual = null;
        const temPermissao = <?php echo json_encode($temPermissaoFinanceiro); ?>;
        const isFinanceiro = <?php echo json_encode($isFinanceiro); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                once: true
            });

            console.log('=== DEBUG GESTÃO DE PECÚLIO ===');
            console.log('Tem permissão:', temPermissao);
            console.log('É financeiro:', isFinanceiro);
            console.log('É presidência:', isPresidencia);

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão');
                return;
            }

            // Event listener para Enter no campo de busca
            $('#rgBuscaPeculio').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarPeculioAssociado(e);
                }
            });

            const departamentoNome = isFinanceiro ? 'Financeiro' : isPresidencia ? 'Presidência' : 'Autorizado';
            notifications.show(`Gestão de Pecúlio carregada - ${departamentoNome}!`, 'success', 3000);
        });

        // ===== FUNÇÃO DE BUSCA DE PECÚLIO =====

        // Buscar pecúlio do associado
        async function buscarPeculioAssociado(event) {
            event.preventDefault();

            const rgInput = document.getElementById('rgBuscaPeculio');
            const busca = rgInput.value.trim();
            const btnBuscar = document.getElementById('btnBuscarPeculio');
            const loadingOverlay = document.getElementById('loadingBuscaPeculio');
            const associadoInfo = document.getElementById('associadoInfoContainer');
            const peculioDados = document.getElementById('peculioDadosContainer');
            const botoesAcoes = document.getElementById('botoesAcoesContainer'); // NOVO

            if (!busca) {
                mostrarAlertBuscaPeculio('Por favor, digite um RG ou nome para consultar.', 'danger');
                return;
            }

            // Mostra loading
            loadingOverlay.style.display = 'flex';
            btnBuscar.disabled = true;
            associadoInfo.style.display = 'none';
            peculioDados.style.display = 'none';
            botoesAcoes.style.display = 'none'; // NOVO
            esconderAlertBuscaPeculio();

            try {
                // Determina se é busca por RG ou nome
                const parametro = isNaN(busca) ? 'nome' : 'rg';
                const response = await fetch(`../api/peculio/consultar_peculio.php?${parametro}=${encodeURIComponent(busca)}`);
                const result = await response.json();

                if (result.status === 'success') {
                    dadosPeculioAtual = result.data;
                    exibirDadosPeculio(dadosPeculioAtual);

                    // Exibir todos os containers
                    associadoInfo.style.display = 'block';
                    peculioDados.style.display = 'block';
                    botoesAcoes.style.display = 'block'; // NOVO

                    mostrarAlertBuscaPeculio('Dados do pecúlio carregados com sucesso!', 'success');

                    // Scroll suave até os dados
                    setTimeout(() => {
                        associadoInfo.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 300);
                } else {
                    mostrarAlertBuscaPeculio(result.message, 'danger');
                }

            } catch (error) {
                console.error('Erro na consulta do pecúlio:', error);
                mostrarAlertBuscaPeculio('Erro ao consultar dados do pecúlio. Verifique sua conexão.', 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                btnBuscar.disabled = false;
            }
        }

        // Exibir dados do pecúlio - CORRIGIDA
        function exibirDadosPeculio(dados) {
            console.log('=== EXIBINDO DADOS DO PECÚLIO ===');
            console.log('Dados recebidos:', dados);

            // Informações do associado
            document.getElementById('associadoNome').textContent = dados.nome || 'Nome não informado';
            document.getElementById('associadoRG').textContent = `RG Militar: ${dados.rg || 'Não informado'}`;

            // Data prevista (corrigir datas inválidas)
            const dataPrevista = (dados.data_prevista && dados.data_prevista !== '0000-00-00') ?
                formatarData(dados.data_prevista) :
                'Não definida';
            document.getElementById('dataPrevistaPeculio').textContent = dataPrevista;

            // Valor do pecúlio
            const valor = dados.valor ? formatarMoeda(parseFloat(dados.valor)) : 'Não informado';
            document.getElementById('valorPeculio').textContent = valor;
            document.getElementById('valorPeculio').className = dados.valor > 0 ? 'dados-value valor-monetario' : 'dados-value pendente';

            // Data de recebimento (corrigir datas inválidas)
            const dataRecebimento = (dados.data_recebimento && dados.data_recebimento !== '0000-00-00') ?
                formatarData(dados.data_recebimento) :
                'Ainda não recebido';
            const elementoRecebimento = document.getElementById('dataRecebimentoPeculio');
            elementoRecebimento.textContent = dataRecebimento;

            // Aplica estilo diferente se ainda não recebeu
            if (!dados.data_recebimento || dados.data_recebimento === '0000-00-00') {
                elementoRecebimento.className = 'dados-value pendente';
            } else {
                elementoRecebimento.className = 'dados-value data';
            }

            // CONTROLE DOS BOTÕES - CORRIGIDO
            const jaRecebeu = dados.data_recebimento && dados.data_recebimento !== '0000-00-00';
            const btnConfirmar = document.getElementById('btnConfirmarRecebimento');
            const btnEditar = document.getElementById('btnEditarPeculio');
            const containerBotoes = document.getElementById('botoesAcoesContainer');

            console.log('Já recebeu?', jaRecebeu);
            console.log('Container botões:', containerBotoes);
            console.log('Botão editar:', btnEditar);
            console.log('Botão confirmar:', btnConfirmar);

            // SEMPRE MOSTRAR O CONTAINER DOS BOTÕES
            if (containerBotoes) {
                containerBotoes.style.display = 'block';
                console.log('✅ Container dos botões exibido');
            }

            // SEMPRE MOSTRAR BOTÃO DE EDITAR
            if (btnEditar) {
                btnEditar.style.display = 'inline-block';
                btnEditar.style.visibility = 'visible';
                console.log('✅ Botão Editar exibido');
            } else {
                console.error('❌ Botão Editar não encontrado');
            }

            // CONTROLAR BOTÃO DE CONFIRMAR RECEBIMENTO
            if (btnConfirmar) {
                if (jaRecebeu) {
                    btnConfirmar.style.display = 'none';
                    console.log('🔒 Botão Confirmar ocultado - já recebido');
                } else {
                    btnConfirmar.style.display = 'inline-block';
                    btnConfirmar.style.visibility = 'visible';
                    console.log('✅ Botão Confirmar exibido');
                }
            } else {
                console.error('❌ Botão Confirmar não encontrado');
            }

            console.log('=== FIM EXIBIÇÃO DADOS ===');
        }

        // Formatação de moeda
        function formatarMoeda(valor) {
            if (!valor || valor === 0) return 'R$ 0,00';
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
        }

        // Limpar busca de pecúlio
        function limparBuscaPeculio() {
            document.getElementById('rgBuscaPeculio').value = '';
            document.getElementById('associadoInfoContainer').style.display = 'none';
            document.getElementById('peculioDadosContainer').style.display = 'none';
            document.getElementById('botoesAcoesContainer').style.display = 'none'; // NOVO
            dadosPeculioAtual = null;
            esconderAlertBuscaPeculio();
        }

        // Mostrar alerta de busca de pecúlio
        function mostrarAlertBuscaPeculio(mensagem, tipo) {
            const alertDiv = document.getElementById('alertBuscaPeculio');
            const alertText = document.getElementById('alertBuscaPeculioText');

            alertText.textContent = mensagem;

            // Remove classes anteriores
            alertDiv.className = 'alert mt-3';

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
                setTimeout(esconderAlertBuscaPeculio, 5000);
            }
        }

        // Esconder alerta de busca de pecúlio
        function esconderAlertBuscaPeculio() {
            document.getElementById('alertBuscaPeculio').style.display = 'none';
        }

        // ===== FUNÇÕES AUXILIARES =====

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

        // ===== FUNÇÕES DE EDIÇÃO =====

        // Editar dados do pecúlio
        function editarPeculio() {
            console.log('=== EDITAR PECÚLIO CHAMADO ===');
            console.log('Dados atuais:', dadosPeculioAtual);

            if (!dadosPeculioAtual) {
                notifications.show('Nenhum associado selecionado para edição', 'warning');
                return;
            }

            // Criar modal de edição
            const modalHtml = `
                <div class="modal fade" id="modalEditarPeculio" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-edit me-2"></i>
                                    Editar Pecúlio - ${dadosPeculioAtual.nome}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="formEditarPeculio">
                                    <div class="mb-3">
                                        <label class="form-label">Valor do Pecúlio (R$)</label>
                                        <input type="number" class="form-control" id="editValor" 
                                               step="0.01" min="0" value="${dadosPeculioAtual.valor || 0}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Data Prevista</label>
                                        <input type="date" class="form-control" id="editDataPrevista" 
                                               value="${formatarDataParaInput(dadosPeculioAtual.data_prevista)}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Data de Recebimento</label>
                                        <input type="date" class="form-control" id="editDataRecebimento" 
                                               value="${formatarDataParaInput(dadosPeculioAtual.data_recebimento)}">
                                        <small class="text-muted">Deixe em branco se ainda não recebeu</small>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-warning" onclick="salvarEdicaoPeculio()">
                                    <i class="fas fa-save me-2"></i>Salvar Alterações
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove modal anterior se existir
            const modalExistente = document.getElementById('modalEditarPeculio');
            if (modalExistente) {
                modalExistente.remove();
            }

            // Adiciona o modal ao body
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Mostra o modal
            const modal = new bootstrap.Modal(document.getElementById('modalEditarPeculio'));
            modal.show();

            console.log('✅ Modal de edição criado e exibido');
        }

        // Confirmar recebimento
        async function confirmarRecebimento() {
            console.log('=== CONFIRMAR RECEBIMENTO CHAMADO ===');
            
            if (!dadosPeculioAtual) {
                notifications.show('Nenhum associado selecionado', 'warning');
                return;
            }

            if (confirm(`Confirmar recebimento do pecúlio de ${dadosPeculioAtual.nome}?`)) {
                try {
                    const response = await fetch('../api/peculio/confirmar_recebimento.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            associado_id: dadosPeculioAtual.id,
                            data_recebimento: new Date().toISOString().split('T')[0]
                        })
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        notifications.show('Recebimento confirmado com sucesso!', 'success');

                        // Recarregar dados
                        setTimeout(() => {
                            buscarPeculioAssociado({
                                preventDefault: () => {}
                            });
                        }, 1000);
                    } else {
                        notifications.show(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Erro ao confirmar recebimento:', error);
                    notifications.show('Erro ao confirmar recebimento', 'error');
                }
            }
        }

        // Salvar edição do pecúlio
        async function salvarEdicaoPeculio() {
            const valor = document.getElementById('editValor').value;
            const dataPrevista = document.getElementById('editDataPrevista').value;
            const dataRecebimento = document.getElementById('editDataRecebimento').value;

            try {
                const response = await fetch('../api/peculio/atualizar_peculio.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        associado_id: dadosPeculioAtual.id,
                        valor: valor,
                        data_prevista: dataPrevista,
                        data_recebimento: dataRecebimento
                    })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    notifications.show('Dados do pecúlio atualizados com sucesso!', 'success');

                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarPeculio'));
                    modal.hide();

                    // Recarregar dados
                    setTimeout(() => {
                        buscarPeculioAssociado({
                            preventDefault: () => {}
                        });
                    }, 1000);
                } else {
                    notifications.show(result.message, 'error');
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                notifications.show('Erro ao salvar alterações', 'error');
            }
        }

        // Formatação de data para input HTML
        function formatarDataParaInput(data) {
            if (!data || data === '0000-00-00') return '';
            try {
                const dataObj = new Date(data + 'T00:00:00');
                return dataObj.toISOString().split('T')[0];
            } catch (e) {
                return '';
            }
        }


    </script>

</body>

</html>