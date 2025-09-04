<?php

/**
 * Página de Serviços Financeiros - Sistema ASSEGO
 * pages/financeiro.php
 * VERSÃO ATUALIZADA - Sistema de navegação interno com componentes dinâmicos
 * COM SUPORTE A PARTIALS E CARREGAMENTO DINÂMICO DE SCRIPTS
 * SEM BUSCA FINANCEIRA
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

// Verificação de permissões: APENAS financeiro (ID: 2) OU presidência (ID: 1)
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;

    if ($deptId == 2) { // Financeiro - CORRIGIDO: usar ID 2 consistentemente
        $temPermissaoFinanceiro = true;
        $isFinanceiro = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Financeiro (ID: 2)");
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoFinanceiro = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } else {
        $motivoNegacao = 'Acesso restrito EXCLUSIVAMENTE ao Setor Financeiro e Presidência.';
        error_log("❌ Acesso negado. Departamento: '$deptId'. Permitido apenas: Financeiro (ID: 2) ou Presidência (ID: 1)");
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

        // 1. Total de associados ativos
        $sql = "SELECT COUNT(DISTINCT a.id) as total 
                FROM Associados a 
                WHERE a.situacao = 'Filiado'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $totalAssociadosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // 2. Arrecadação do mês atual
        $sql = "SELECT COALESCE(SUM(sa.valor_aplicado), 0) as valor_mes 
                FROM Servicos_Associado sa
                INNER JOIN Associados a ON sa.associado_id = a.id
                WHERE sa.ativo = 1 
                AND a.situacao = 'Filiado'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $arrecadacaoMes = floatval($result['valor_mes'] ?? 0);

        // 3. Pagamentos recebidos hoje
        $pagamentosHoje = 0;
        try {
            $sql = "SELECT COUNT(*) as hoje 
                    FROM Pagamentos 
                    WHERE DATE(data_pagamento) = CURDATE() 
                    AND status_pagamento = 'PAGO'";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $pagamentosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'] ?? 0;
        } catch (Exception $e) {
            $pagamentosHoje = 0;
        }

        // 4. Associados inadimplentes
        $sql = "SELECT COUNT(DISTINCT a.id) as inadimplentes 
                FROM Associados a
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE a.situacao = 'Filiado' 
                AND (
                    f.situacaoFinanceira = 'Inadimplente' 
                    OR f.situacaoFinanceira = 'INADIMPLENTE'
                    OR f.situacaoFinanceira IS NULL
                )";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $associadosInadimplentes = $stmt->fetch(PDO::FETCH_ASSOC)['inadimplentes'] ?? 0;

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas financeiras: " . $e->getMessage());
        $totalAssociadosAtivos = 0;
        $pagamentosHoje = 0;
        $associadosInadimplentes = 0;
        $arrecadacaoMes = 0;
    }
} else {
    $totalAssociadosAtivos = 0;
    $pagamentosHoje = 0;
    $associadosInadimplentes = 0;
    $arrecadacaoMes = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
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
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
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
            padding: 1.5rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(44, 90, 160, 0.08);
            border-left: 4px solid var(--primary);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--secondary);
            margin: 0;
        }

        /* Stats Grid KPIs */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dual-stat-card {
            position: relative;
            overflow: visible;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 20px;
            padding: 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.08);
            min-width: 320px;
            width: 100%;
        }

        .dual-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .dual-stat-card:hover::before {
            transform: scaleX(1);
        }

        .dual-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.12);
            border-color: rgba(0, 86, 210, 0.2);
        }

        .dual-stat-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dual-stat-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .dual-stats-row {
            display: flex;
            align-items: stretch;
            padding: 0;
            min-height: 120px;
            width: 100%;
        }

        .dual-stat-item {
            flex: 1;
            min-width: 0;
            padding: 1.5rem 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1rem;
            transition: all 0.3s ease;
            position: relative;
            width: 50%;
        }

        .dual-stat-item:hover {
            background: rgba(0, 86, 210, 0.02);
        }

        .dual-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .dual-stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .dual-stat-label {
            font-size: 0.875rem;
            color: var(--secondary);
            font-weight: 600;
            line-height: 1;
        }

        .dual-stats-separator {
            width: 1px;
            background: linear-gradient(to bottom, transparent, #dee2e6, transparent);
            margin: 1.5rem 0;
            flex-shrink: 0;
        }

        .ativos-icon {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .inadimplentes-icon {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .arrecadacao-icon {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }

        .pagamentos-icon {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }

        /* ===== SISTEMA DE NAVEGAÇÃO INTERNO ULTRA COMPACTO ===== */
        .financial-nav {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(40, 167, 69, 0.08);
            margin-bottom: 0 !important;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .nav-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 1.5rem 2rem;
            color: white;
        }

        .nav-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
        }

        .nav-tabs-container {
            padding: 0 !important;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 0 !important;
        }

        .financial-nav-tabs {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 0;
            margin: 0 !important;
            list-style: none;
            border-bottom: none;
        }

        .financial-nav-tabs::-webkit-scrollbar {
            height: 4px;
        }

        .financial-nav-tabs::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .financial-nav-tabs::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .nav-tab {
            flex: 0 0 auto;
            min-width: 180px;
            position: relative;
        }

        .nav-tab-btn {
            width: 100%;
            padding: 1rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            min-height: 80px;
        }

        .nav-tab-btn:hover {
            background: rgba(0, 86, 210, 0.1);
            color: var(--primary);
        }

        .nav-tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 -2px 10px rgba(0, 86, 210, 0.1);
        }

        .nav-tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
        }

        .nav-tab-icon {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .nav-tab-label {
            font-size: 0.85rem;
            line-height: 1.2;
        }

        /* CORREÇÃO ULTRA AGRESSIVA - ELIMINAR TODO ESPAÇAMENTO */
        .financial-content {
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
        }

        .financial-content > * {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        .content-panel {
            padding: 0 !important;
            margin: 0 !important;
            min-height: auto !important;
            height: auto !important;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            display: none;
        }

        .content-panel.active {
            display: block !important;
            animation: fadeIn 0.3s ease-in;
        }

        .content-panel > * {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Remover headers desnecessários */
        .content-panel .content-header {
            display: none !important;
        }

        /* Forçar todos os IDs das abas */
        #gestao-peculio,
        #lista-inadimplentes,
        #neoconsig,
        #importar-asaas {
            padding: 0 !important;
            margin: 0 !important;
            min-height: auto !important;
            height: auto !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        #gestao-peculio > *,
        #lista-inadimplentes > *,
        #neoconsig > *,
        #importar-asaas > * {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Eliminar espaço da navegação */
        .nav-tabs-container {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }

        .financial-nav {
            margin-bottom: 0 !important;
        }

        /* Container geral */
        .content-area > * {
            margin-top: 0 !important;
        }

        /* Page Header compacto */
        .mb-4 {
            margin-bottom: 0 !important;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .content-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .content-description {
            font-size: 1rem;
            color: var(--secondary);
            margin: 0;
            line-height: 1.5;
        }

        /* Loading States COMPACTO */
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 50px !important;
            flex-direction: column;
            gap: 0.5rem;
            margin: 0 !important;
            padding: 0 !important;
        }

        .spinner {
            width: 25px;
            height: 25px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alert personalizado */
        .alert-custom {
            border-radius: 10px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .alert-custom i {
            font-size: 1.25rem;
            margin-right: 0.75rem;
        }

        /* Toast */
        .toast-container {
            z-index: 9999;
        }

        /* Responsividade */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }

            .financial-nav-tabs {
                flex-direction: column;
            }

            .nav-tab {
                min-width: 100%;
            }

            .nav-tab-btn {
                flex-direction: row;
                justify-content: flex-start;
                min-height: 60px;
                padding: 1rem;
                text-align: left;
            }

            .nav-tab-icon {
                margin-bottom: 0;
                margin-right: 0.75rem;
            }

            .dual-stats-row {
                flex-direction: column;
                min-height: auto;
            }

            .dual-stats-separator {
                width: 80%;
                height: 1px;
                margin: 0.75rem auto;
                background: linear-gradient(to right, transparent, #dee2e6, transparent);
            }

            .dual-stat-item {
                padding: 1.25rem;
                width: 100%;
                flex-direction: row !important;
                align-items: center !important;
                text-align: left !important;
                gap: 1rem !important;
                justify-content: flex-start !important;
            }
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

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
                    <a href="../pages/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Com Permissão -->
                
                <!-- Page Header COMPACTO -->
                <div class="mb-0" style="margin-bottom: 0.5rem !important;">
                    <h1 class="page-title">Serviços Financeiros</h1>
                    <p class="page-subtitle">Gerencie mensalidades, inadimplência, relatórios financeiros e arrecadação da ASSEGO</p>
                </div>

                <!-- Stats Grid
                <div class="stats-grid" data-aos="fade-up">
                    <div class="dual-stat-card">
                        <div class="dual-stat-header">
                            <div class="dual-stat-title">
                                <i class="fas fa-chart-line"></i>
                                Status dos Associados
                            </div>
                        </div>
                        <div class="dual-stats-row">
                            <div class="dual-stat-item">
                                <div class="dual-stat-icon ativos-icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <div>
                                    <div class="dual-stat-value"><?php echo number_format($totalAssociadosAtivos, 0, ',', '.'); ?></div>
                                    <div class="dual-stat-label">Associados Ativos</div>
                                </div>
                            </div>
                            <div class="dual-stats-separator"></div>
                            <div class="dual-stat-item">
                                <div class="dual-stat-icon inadimplentes-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <div class="dual-stat-value"><?php echo number_format($associadosInadimplentes, 0, ',', '.'); ?></div>
                                    <div class="dual-stat-label">Inadimplentes</div>
                                </div>
                            </div>
                        </div>
                    </div> 

                    <div class="dual-stat-card">
                        <div class="dual-stat-header">
                            <div class="dual-stat-title">
                                <i class="fas fa-dollar-sign"></i>
                                Movimentação Financeira
                            </div>
                        </div>
                        <div class="dual-stats-row">
                            <div class="dual-stat-item">
                                <div class="dual-stat-icon arrecadacao-icon">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div>
                                    <div class="dual-stat-value">R$ <?php echo number_format($arrecadacaoMes, 0, ',', '.'); ?></div>
                                    <div class="dual-stat-label">Arrecadação/Mês</div>
                                </div>
                            </div>
                            <div class="dual-stats-separator"></div>
                            <div class="dual-stat-item">
                                <div class="dual-stat-icon pagamentos-icon">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <div>
                                    <div class="dual-stat-value"><?php echo number_format($pagamentosHoje, 0, ',', '.'); ?></div>
                                    <div class="dual-stat-label">Pagamentos Hoje</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- Navegação SEM ESPAÇOS -->
                <div class="nav-tabs-container" style="margin: 0 !important; padding: 0 !important;">
                    <ul class="financial-nav-tabs">
                        <li class="nav-tab">
                            <button class="nav-tab-btn active" data-target="lista-inadimplentes">
                                <i class="nav-tab-icon fas fa-list-ul"></i>
                                <span class="nav-tab-label">Lista Inadimplentes</span>
                            </button>
                        </li>
                        <li class="nav-tab">
                            <button class="nav-tab-btn" data-target="neoconsig">
                                <i class="nav-tab-icon fas fa-file-download"></i>
                                <span class="nav-tab-label">NeoConsig</span>
                            </button>
                        </li>
                        <li class="nav-tab">
                            <button class="nav-tab-btn" data-target="importar-asaas">
                                <i class="nav-tab-icon fas fa-file-import"></i>
                                <span class="nav-tab-label">Importar ASAAS</span>
                            </button>
                        </li>
                        <li class="nav-tab">
                            <button class="nav-tab-btn" data-target="gestao-peculio">
                                <i class="nav-tab-icon fas fa-piggy-bank"></i>
                                <span class="nav-tab-label">Gestão Pecúlio</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Content Area SEM ESPAÇOS -->
                <div class="financial-content" style="margin: 0 !important; padding: 0 !important;">
                    <!-- Lista Inadimplentes SEM HEADER -->
                    <div id="lista-inadimplentes" class="content-panel active">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p class="text-muted">Carregando lista de inadimplentes...</p>
                        </div>
                    </div>

                    <!-- NeoConsig SEM HEADER -->
                    <div id="neoconsig" class="content-panel">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p class="text-muted">Carregando gerador de recorrência...</p>
                        </div>
                    </div>

                    <!-- Importar ASAAS SEM HEADER -->
                    <div id="importar-asaas" class="content-panel">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p class="text-muted">Carregando importador ASAAS...</p>
                        </div>
                    </div>

                    <!-- Gestão Pecúlio SEM HEADER -->
                    <div id="gestao-peculio" class="content-panel">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p class="text-muted">Carregando gestão de pecúlio...</p>
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

        // ===== MAPEAMENTO DE ABAS PARA SCRIPTS =====
        const TAB_SCRIPTS = {
            'gestao-peculio': './rend/js/gestao_peculio.js',
            'lista-inadimplentes': './rend/js/lista_inadimplentes.js',
            'neoconsig': './rend/js/neoconsig.js', 
            'importar-asaas': './rend/js/importar_asaas.js'
        };

        // ===== HELPER PARA CARREGAR SCRIPTS UMA ÚNICA VEZ =====
        const loadedScripts = new Set();

        function loadScriptOnce(src) {
            return new Promise((resolve, reject) => {
                if (loadedScripts.has(src)) {
                    console.log(`Script já carregado: ${src}`);
                    resolve();
                    return;
                }

                console.log(`Carregando script: ${src}`);
                const script = document.createElement('script');
                script.src = src;
                script.onload = () => {
                    loadedScripts.add(src);
                    console.log(`Script carregado com sucesso: ${src}`);
                    resolve();
                };
                script.onerror = (error) => {
                    console.error(`Erro ao carregar script: ${src}`, error);
                    reject(error);
                };
                document.head.appendChild(script);
            });
        }

        // ===== SISTEMA DE NAVEGAÇÃO INTERNO =====
        class FinancialNavigation {
            constructor() {
                this.activeTab = 'lista-inadimplentes';
                this.loadedTabs = new Set(['lista-inadimplentes']);
                this.init();
            }

            init() {
                // Event listeners para as abas
                document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const target = e.currentTarget.dataset.target;
                        this.switchTab(target);
                    });
                });

                // Carregar a primeira aba
                this.loadTabContent('lista-inadimplentes');
            }

            switchTab(tabId) {
                if (this.activeTab === tabId) return;

                // Atualiza botões
                document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector(`[data-target="${tabId}"]`).classList.add('active');

                // Esconde painel atual
                document.querySelectorAll('.content-panel').forEach(panel => {
                    panel.classList.remove('active');
                });

                // Mostra novo painel
                const targetPanel = document.getElementById(tabId);
                targetPanel.classList.add('active');

                // CORREÇÃO AGRESSIVA: Aplicar estilos de limpeza EXTREMOS
                targetPanel.style.cssText = `
                    padding: 0 !important;
                    margin: 0 !important;
                    min-height: auto !important;
                    background: transparent !important;
                    border: none !important;
                    box-shadow: none !important;
                `;

                // Carrega conteúdo se necessário
                if (!this.loadedTabs.has(tabId)) {
                    this.loadTabContent(tabId);
                    this.loadedTabs.add(tabId);
                }

                this.activeTab = tabId;
                notifications.show(`Seção ${this.getTabName(tabId)} ativada`, 'info', 2000);
            }

            getTabName(tabId) {
                const names = {
                    'lista-inadimplentes': 'Lista de Inadimplentes',
                    'neoconsig': 'NeoConsig',
                    'importar-asaas': 'Importar ASAAS',
                    'gestao-peculio': 'Gestão de Pecúlio'
                };
                return names[tabId] || tabId;
            }

            async loadTabContent(tabId) {
                const panel = document.getElementById(tabId);
                const spinner = panel.querySelector('.loading-spinner');

                try {
                    console.log(`Carregando conteúdo da aba: ${tabId}`);
                    
                    const partialUrl = tabId === 'gestao-peculio' 
                        ? '../pages/rend/gestao_peculio_content.php'
                        : `./rend/${tabId.replace('-', '_')}_content.php`;
                    
                    console.log(`Buscando partial: ${partialUrl}`);
                    
                    const response = await fetch(partialUrl);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status} - ${response.statusText}`);
                    }

                    const htmlContent = await response.text();
                    console.log(`✅ HTML carregado: ${htmlContent.length} chars`);

                    // Esconder spinner
                    if (spinner) {
                        spinner.style.display = 'none';
                    }
                    
                    // CORREÇÃO AGRESSIVA: Limpar completamente e injetar sem headers
                    panel.innerHTML = htmlContent;

                    // CORREÇÃO ADICIONAL: Aplicar estilos de limpeza AGRESSIVOS
                    panel.style.cssText = `
                        padding: 0 !important;
                        margin: 0 !important;
                        min-height: auto !important;
                        height: auto !important;
                        background: transparent !important;
                        border: none !important;
                        box-shadow: none !important;
                    `;

                    // CORREÇÃO: Remover QUALQUER content-header se existir
                    const contentHeaders = panel.querySelectorAll('.content-header');
                    contentHeaders.forEach(header => header.remove());

                    // CORREÇÃO: Aplicar estilos em todos os filhos diretos
                    Array.from(panel.children).forEach(child => {
                        if (child.classList.contains('content-header')) {
                            child.remove(); // Remover headers completamente
                        } else {
                            child.style.cssText = `
                                margin-top: 0 !important;
                                padding-top: 0 !important;
                            `;
                        }
                    });

                    console.log('✅ Estilos de limpeza aplicados agressivamente');

                    // Carregar scripts se necessário
                    const scriptSrc = TAB_SCRIPTS[tabId];
                    if (scriptSrc) {
                        await loadScriptOnce(scriptSrc);
                        await this.initializeTabModule(tabId);
                    }

                } catch (error) {
                    console.error(`❌ Erro ao carregar ${tabId}:`, error);
                    panel.innerHTML = `
                        <div class="alert alert-danger" style="margin: 0; padding: 1rem;">
                            <h4><i class="fas fa-exclamation-triangle"></i> Erro ao Carregar</h4>
                            <p>${error.message}</p>
                            <button class="btn btn-primary" onclick="financialNav.retryLoad('${tabId}')">
                                <i class="fas fa-redo"></i> Tentar Novamente
                            </button>
                        </div>
                    `;
                }
            }

            async initializeTabModule(tabId) {
                try {
                    switch(tabId) {
                        case 'gestao-peculio':
                            if (window.Peculio) {
                                console.log('Inicializando módulo Peculio...');
                                window.Peculio.init({
                                    temPermissao: temPermissaoFinanceiro,
                                    isFinanceiro: <?php echo json_encode($isFinanceiro); ?>,
                                    isPresidencia: <?php echo json_encode($isPresidencia); ?>
                                });
                            } else {
                                console.error('Módulo Peculio não encontrado após carregamento do script');
                            }
                            break;

                        case 'lista-inadimplentes':
                            if (window.ListaInadimplentes) {
                                console.log('Inicializando módulo Lista de Inadimplentes...');
                                window.ListaInadimplentes.init({
                                    temPermissao: temPermissaoFinanceiro
                                });
                            }
                            break;

                        case 'neoconsig':
                            if (window.NeoConsig) {
                                console.log('Inicializando módulo NeoConsig...');
                                window.NeoConsig.init({
                                    temPermissao: temPermissaoFinanceiro
                                });
                            }
                            break;

                        case 'importar-asaas':
                            if (window.ImportarAsaas) {
                                console.log('Inicializando módulo Importar ASAAS...');
                                window.ImportarAsaas.init({
                                    temPermissao: temPermissaoFinanceiro
                                });
                            }
                            break;
                    }
                } catch (error) {
                    console.error(`Erro ao inicializar módulo ${tabId}:`, error);
                    notifications.show(`Erro ao inicializar ${this.getTabName(tabId)}`, 'error');
                }
            }

            retryLoad(tabId) {
                console.log(`Tentando recarregar aba: ${tabId}`);
                
                this.loadedTabs.delete(tabId);
                const panel = document.getElementById(tabId);
                
                panel.innerHTML = `
                    <div class="loading-spinner" style="margin: 0 !important; padding: 0 !important;">
                        <div class="spinner"></div>
                        <p class="text-muted">Carregando ${this.getTabName(tabId).toLowerCase()}...</p>
                    </div>
                `;

                this.loadTabContent(tabId);
                this.loadedTabs.add(tabId);
            }
        }

        // ===== VARIÁVEIS GLOBAIS =====
        const notifications = new NotificationSystem();
        let financialNav;
        const temPermissaoFinanceiro = <?php echo json_encode($temPermissaoFinanceiro); ?>;

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                once: true
            });

            if (!temPermissaoFinanceiro) {
                console.log('❌ Usuário sem permissão');
                return;
            }

            // Inicializa sistema de navegação
            financialNav = new FinancialNavigation();

            notifications.show('Sistema financeiro carregado com sucesso!', 'success', 3000);
            console.log('✅ Sistema Financeiro inicializado - Layout ultra compacto sem busca financeira');
        });

        console.log('✓ Sistema Financeiro - Layout ultra compacto sem espaçamentos e sem busca financeira aplicado!');
    </script>
</body>

</html>