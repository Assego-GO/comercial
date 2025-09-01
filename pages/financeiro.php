<?php

/**
 * Página de Serviços Financeiros - Sistema ASSEGO
 * pages/financeiro.php
 * VERSÃO ATUALIZADA - Suporte a múltiplos RGs de diferentes corporações
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
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;

    if ($deptId == 2) { // Financeiro
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

        // 1. Total de associados ativos (igual ao dashboard)
        $sql = "SELECT COUNT(DISTINCT a.id) as total 
                FROM Associados a 
                WHERE a.situacao = 'Filiado'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $totalAssociadosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // 2. Arrecadação do mês atual - CORRIGIDO para buscar dos serviços
        $sql = "SELECT COALESCE(SUM(sa.valor_aplicado), 0) as valor_mes 
                FROM Servicos_Associado sa
                INNER JOIN Associados a ON sa.associado_id = a.id
                WHERE sa.ativo = 1 
                AND a.situacao = 'Filiado'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $arrecadacaoMes = floatval($result['valor_mes'] ?? 0);

        error_log("Arrecadação mensal total: R$ " . $arrecadacaoMes);

        // 3. Pagamentos recebidos hoje (se existir tabela de pagamentos)
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
            // Tabela pode não existir, mantém 0
            error_log("Tabela Pagamentos não encontrada ou erro: " . $e->getMessage());
            $pagamentosHoje = 0;
        }

        // 4. Associados inadimplentes - CORRIGIDO
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

        error_log("Associados inadimplentes: " . $associadosInadimplentes);

        // OPCIONAL: Se quiser calcular a arrecadação REAL do mês (pagamentos efetivos)
        try {
            $sql = "SELECT COALESCE(SUM(valor_pagamento), 0) as valor_mes_real 
                    FROM Pagamentos 
                    WHERE YEAR(data_pagamento) = YEAR(CURDATE()) 
                    AND MONTH(data_pagamento) = MONTH(CURDATE())
                    AND status_pagamento = 'PAGO'";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $arrecadacaoMesReal = $stmt->fetch(PDO::FETCH_ASSOC)['valor_mes_real'] ?? 0;

            // Se tiver pagamentos reais, usa esse valor
            if ($arrecadacaoMesReal > 0) {
                error_log("Usando arrecadação real (pagamentos): R$ " . $arrecadacaoMesReal);
                // Descomente a linha abaixo se quiser usar os pagamentos reais ao invés do potencial
                // $arrecadacaoMes = $arrecadacaoMesReal;
            }
        } catch (Exception $e) {
            // Tabela de pagamentos pode não existir
            error_log("Não foi possível buscar pagamentos reais: " . $e->getMessage());
        }
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

// Debug final
error_log("=== ESTATÍSTICAS FINANCEIRAS FINAIS ===");
error_log("Associados Ativos: " . $totalAssociadosAtivos);
error_log("Arrecadação do Mês: R$ " . $arrecadacaoMes);
error_log("Pagamentos Hoje: " . $pagamentosHoje);
error_log("Inadimplentes: " . $associadosInadimplentes);

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
            padding: 1.5rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        /* Page Header - Mais compacto */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(44, 90, 160, 0.08);
            border-left: 4px solid var(--primary);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .page-title-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .page-title-icon i {
            color: white;
            font-size: 1.5rem;
        }

        .page-subtitle {
            color: var(--secondary);
            margin: 0.5rem 0 0;
            font-size: 0.95rem;
        }

        /* Stats Grid - Mais compacto */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.12);
        }

        .stat-card.primary {
            border-left-color: var(--primary);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-card.danger {
            border-left-color: var(--danger);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        .stat-change.neutral {
            color: var(--info);
        }

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

        .stat-icon.primary {
            background: var(--primary);
            color: var(--primary);
        }

        .stat-icon.success {
            background: var(--success);
            color: var(--success);
        }

        .stat-icon.warning {
            background: var(--warning);
            color: var(--warning);
        }

        .stat-icon.danger {
            background: var(--danger);
            color: var(--danger);
        }

        /* Seções de Serviços */
        .services-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .service-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.08);
            overflow: hidden;
            height: fit-content;
        }

        .service-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 1.5rem;
        }

        .service-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }

        .service-header i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }

        .service-content {
            padding: 1.5rem;
        }

        /* Seção de Gestão Financeira */
        .busca-form {
            display: flex;
            gap: 0.75rem;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .busca-input-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.375rem;
            font-size: 0.9rem;
        }

        .form-control {
            border-radius: 6px;
            border: 2px solid #e9ecef;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--secondary);
            border-color: var(--secondary);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
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

        .alert-info-custom {
            background: linear-gradient(135deg, var(--primary-light) 0%, #d4edda 100%);
            color: var(--primary-dark);
            border-left: 4px solid var(--primary);
        }

        /* Dados financeiros */
        .dados-financeiros-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1rem;
            border: 2px solid #e9ecef;
        }

        .dados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .dados-item {
            background: white;
            border-radius: 6px;
            padding: 0.75rem;
            border-left: 3px solid var(--primary);
            transition: all 0.3s ease;
        }

        .dados-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.08);
        }

        .dados-label {
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dados-value {
            color: var(--dark);
            font-size: 0.9rem;
            font-weight: 500;
            word-break: break-word;
        }

        /* Modal de Seleção de Associados */
        .modal-selecao-associado {
            z-index: 9999;
        }

        .associado-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .associado-card:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(0, 86, 210, 0.12);
        }

        .associado-card.selecionado {
            border-color: var(--success);
            background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
        }

        .associado-foto {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }

        .associado-info {
            flex: 1;
            margin-left: 1.5rem;
        }

        .associado-nome {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .associado-rg {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .associado-militar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .badge-corporacao {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .badge-pm {
            background: #e3f2fd;
            color: #1565c0;
        }

        .badge-bm {
            background: #ffebee;
            color: #c62828;
        }

        .badge-pc {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        .badge-default {
            background: #f5f5f5;
            color: #616161;
        }

        /* Identificação Militar */
        .identificacao-militar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .identificacao-militar h6 {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .militar-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .militar-info-item {
            display: flex;
            flex-direction: column;
        }

        .militar-info-label {
            font-size: 0.8rem;
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .militar-info-value {
            font-size: 1rem;
            color: var(--dark);
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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

        /* Financeiro options - NOVA estrutura para botões menores */
        .financeiro-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .financeiro-option {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 10px;
            padding: 1.25rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            min-height: 80px;
        }

        .financeiro-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 0 4px 15px rgba(0, 86, 210, 0.15);
        }

        .financeiro-option-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .financeiro-option-content h5 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .financeiro-option-content p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--secondary);
            line-height: 1.3;
        }

        /* Valores */
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

        .situacao-adimplente {
            background: var(--success);
            color: white;
        }

        .situacao-inadimplente {
            background: var(--danger);
            color: white;
        }

        /* Responsivo */
        @media (max-width: 1200px) {
            .financeiro-options {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .services-container {
                grid-template-columns: 1fr;
            }

            .financeiro-options {
                grid-template-columns: 1fr;
            }

            .financeiro-option {
                flex-direction: column;
                text-align: center;
                min-height: auto;
                padding: 1rem;
            }

            .financeiro-option-icon {
                margin-bottom: 0.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .content-area {
                padding: 1rem;
            }

            .busca-form {
                flex-direction: column;
                align-items: stretch;
            }

            .dados-grid {
                grid-template-columns: 1fr;
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
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado aos Serviços Financeiros</h4>
                    <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>

                    <a href="../pages/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>

            <?php else: ?>
                <!-- Com Permissão - Conteúdo Normal -->

                <!-- Page Header - TÍTULO MINIMALISTA -->
                <div class="mb-4">
                    <h1 style="font-size: 2.5rem; font-weight: 700; color: #212529; margin-bottom: 0.5rem;">Serviços Financeiros</h1>
                    <p class="text-muted mb-0" style="font-size: 1rem; color: #6c757d;">Gerencie mensalidades, inadimplência, relatórios financeiros e arrecadação da ASSEGO</p>
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
                                <div class="stat-label">Arrecadação Potencial do Mês</div>
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

                <!-- Alert informativo -->
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
                            Você tem acesso completo aos serviços financeiros. Sistema preparado para múltiplos RGs de diferentes corporações.
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
                            <p class="text-muted mb-3" style="font-size: 0.9rem;">
                                Consulte associados em débito. Sistema preparado para múltiplos RGs de diferentes corporações (PM, BM, PC, etc).
                            </p>

                            <form class="busca-form" onsubmit="buscarAssociadoPorRG(event)">
                                <div class="busca-input-group">
                                    <label class="form-label" for="rgBuscaFinanceiro">
                                        <i class="fas fa-id-card me-1"></i>
                                        RG Militar ou Nome
                                    </label>
                                    <input type="text" class="form-control" id="rgBuscaFinanceiro"
                                        placeholder="Digite o RG militar ou nome..." required>
                                    <small class="text-muted">
                                        Se houver múltiplos registros com o mesmo RG, você poderá escolher a corporação correta
                                    </small>
                                </div>
                                <div>
                                    <button type="submit" class="btn btn-primary" id="btnBuscarFinanceiro">
                                        <i class="fas fa-search me-1"></i>
                                        Buscar
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-secondary" onclick="limparBuscaFinanceiro()">
                                        <i class="fas fa-eraser me-1"></i>
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

                                <!-- Identificação Militar -->
                                <div id="identificacaoMilitar" class="identificacao-militar" style="display: none;">
                                    <h6>
                                        <i class="fas fa-shield-alt me-2"></i>
                                        Identificação Militar
                                    </h6>
                                    <div class="militar-info-grid" id="militarInfoGrid">
                                        <!-- Dados militares serão inseridos aqui -->
                                    </div>
                                </div>

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
                            <p class="text-muted mb-4" style="font-size: 0.9rem;">
                                Acesse relatórios de arrecadação, inadimplência e estatísticas financeiras.
                            </p>

                            <div class="financeiro-options">
                                <div class="financeiro-option" onclick="relatorioArrecadacao()">
                                    <div class="financeiro-option-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="financeiro-option-content">
                                        <h5>Relatório de Arrecadação</h5>
                                        <p>Visualize a evolução da arrecadação mensal e anual</p>
                                    </div>
                                </div>

                                <div class="financeiro-option" onclick="listarInadimplentes()">
                                    <div class="financeiro-option-icon">
                                        <i class="fas fa-list-ul"></i>
                                    </div>
                                    <div class="financeiro-option-content">
                                        <h5>Lista de Inadimplentes</h5>
                                        <p>Consulte e gerencie associados com pendências financeiras</p>
                                    </div>
                                </div>

                                <div class="financeiro-option" onclick="gerarRecorrencia()">
                                    <div class="financeiro-option-icon">
                                        <i class="fas fa-file-download"></i>
                                    </div>
                                    <div class="financeiro-option-content">
                                        <h5>Optantes NeoConsig</h5>
                                        <p>Gere arquivos TXT para inclusões, cancelamentos e alterações</p>
                                    </div>
                                </div>

                                <!-- <div class="financeiro-option" onclick="extratoFinanceiro()">
                                    <div class="financeiro-option-icon">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </div>
                                    <div class="financeiro-option-content">
                                        <h5>Extrato Financeiro</h5>
                                        <p>Acompanhe entradas, saídas e saldo atual da associação</p>
                                    </div>
                                </div>-->

                                <div class="financeiro-option" onclick="importarAsaas()">
                                    <div class="financeiro-option-icon">
                                        <i class="fas fa-file-import"></i>
                                    </div>
                                    <div class="financeiro-option-content">
                                        <h5>Importar CSV ASAAS</h5>
                                        <p>Importe arquivo CSV do ASAAS para atualizar status de adimplência automaticamente</p>
                                    </div>
                                </div>

                                <div class="financeiro-option" onclick="gerenciarPeculio()">
                                    <div class="financeiro-option-icon">
                                        <i class="fas fa-piggy-bank"></i>
                                    </div>
                                    <div class="financeiro-option-content">
                                        <h5>Gestão de Pecúlio</h5>
                                        <p>Gerencie fundos de pecúlio, reservas e benefícios especiais</p>
                                    </div>
                                </div>
                                <!--
                                <div class="financeiro-option" onclick="estatisticasFinanceiras()">
                                    <div class="financeiro-option-icon">
                                        <i class="fas fa-calculator"></i>
                                    </div>
                                    <div class="financeiro-option-content">
                                        <h5>Estatísticas Financeiras</h5>
                                        <p>Dashboard completo com indicadores financeiros</p>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Seleção de Associado (NOVO) -->
    <div class="modal fade modal-selecao-associado" id="modalSelecaoAssociado" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-users me-2"></i>
                        Múltiplos Associados Encontrados
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Atenção:</strong> Foram encontrados múltiplos associados com o mesmo RG em diferentes corporações.
                        Selecione o associado correto para visualizar os dados financeiros.
                    </div>
                    <div id="listaAssociadosSelecao">
                        <!-- Lista de associados será inserida aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarSelecao" disabled>
                        <i class="fas fa-check me-2"></i>
                        Confirmar Seleção
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
        let dadosFinanceirosAtual = null;
        let associadoSelecionadoId = null;
        let listaAssociadosMultiplos = [];
        const temPermissao = <?php echo json_encode($temPermissaoFinanceiro); ?>;

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                once: true
            });

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - não carregará funcionalidades');
                return;
            }

            // Event listener para Enter no campo de busca
            $('#rgBuscaFinanceiro').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAssociadoPorRG(e);
                }
            });

            // Event listener para o botão de confirmar seleção
            document.getElementById('btnConfirmarSelecao').addEventListener('click', buscarAssociadoSelecionado);

            notifications.show('Serviços financeiros carregados!', 'success', 3000);
        });

        // ===== FUNÇÕES DE BUSCA FINANCEIRA (ATUALIZADAS) =====

        // Buscar associado por RG - ATUALIZADA para suportar múltiplos resultados
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

                if (result.status === 'multiple_results') {
                    // Múltiplos resultados encontrados
                    listaAssociadosMultiplos = result.data;
                    mostrarModalSelecao(result.data);
                    mostrarAlertaBuscaFinanceiro('Múltiplos associados encontrados. Por favor, selecione o correto.', 'warning');
                } else if (result.status === 'success') {
                    // Um único resultado
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
                    mostrarAlertaBuscaFinanceiro(result.message || 'Erro ao buscar dados', 'danger');
                }

            } catch (error) {
                console.error('Erro na busca financeira:', error);
                mostrarAlertaBuscaFinanceiro('Erro ao consultar dados financeiros. Verifique sua conexão.', 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                btnBuscar.disabled = false;
            }
        }

        // NOVA FUNÇÃO - Mostrar modal de seleção
        function mostrarModalSelecao(associados) {
            const listaContainer = document.getElementById('listaAssociadosSelecao');
            listaContainer.innerHTML = '';

            associados.forEach(assoc => {
                const card = document.createElement('div');
                card.className = 'associado-card d-flex align-items-center';
                card.dataset.id = assoc.id;

                // Determina a classe do badge baseado na corporação
                let badgeClass = 'badge-default';
                let corporacaoIcon = 'fa-shield-alt';

                if (assoc.corporacao) {
                    const corp = assoc.corporacao.toUpperCase();
                    if (corp.includes('PM') || corp.includes('POLÍCIA MILITAR')) {
                        badgeClass = 'badge-pm';
                        corporacaoIcon = 'fa-shield';
                    } else if (corp.includes('BM') || corp.includes('BOMBEIRO')) {
                        badgeClass = 'badge-bm';
                        corporacaoIcon = 'fa-fire';
                    } else if (corp.includes('PC') || corp.includes('POLÍCIA CIVIL')) {
                        badgeClass = 'badge-pc';
                        corporacaoIcon = 'fa-user-shield';
                    }
                }

                card.innerHTML = `
                    <div class="form-check me-3">
                        <input class="form-check-input" type="radio" name="associadoSelecionado" 
                               value="${assoc.id}" id="assoc_${assoc.id}">
                    </div>
                    ${assoc.foto ? 
                        `<img src="${assoc.foto}" class="associado-foto" alt="${assoc.nome}">` : 
                        `<div class="associado-foto d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-user fa-2x text-muted"></i>
                        </div>`
                    }
                    <div class="associado-info">
                        <div class="associado-nome">${assoc.nome}</div>
                        <div class="associado-rg">
                            <i class="fas fa-id-card me-1"></i>
                            RG: ${assoc.rg} | CPF: ${assoc.cpf || 'Não informado'}
                        </div>
                        <div class="associado-militar">
                            <span class="badge-corporacao ${badgeClass}">
                                <i class="fas ${corporacaoIcon}"></i>
                                ${assoc.corporacao || 'Corporação não informada'}
                            </span>
                            ${assoc.patente ? 
                                `<span class="badge bg-secondary">
                                    <i class="fas fa-star me-1"></i>
                                    ${assoc.patente}
                                </span>` : ''
                            }
                            ${assoc.unidade ? 
                                `<span class="badge bg-info text-dark">
                                    <i class="fas fa-building me-1"></i>
                                    ${assoc.unidade}
                                </span>` : ''
                            }
                        </div>
                        ${assoc.situacao_financeira ? 
                            `<div class="mt-2">
                                <small class="text-muted">Situação: </small>
                                <span class="badge ${assoc.situacao_financeira === 'INADIMPLENTE' ? 'bg-danger' : 'bg-success'}">
                                    ${assoc.situacao_financeira}
                                </span>
                            </div>` : ''
                        }
                    </div>
                `;

                // Evento de clique no card
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;

                    // Remove seleção anterior
                    document.querySelectorAll('.associado-card').forEach(c => c.classList.remove('selecionado'));
                    this.classList.add('selecionado');

                    // Habilita botão de confirmação
                    document.getElementById('btnConfirmarSelecao').disabled = false;
                    associadoSelecionadoId = assoc.id;
                });

                listaContainer.appendChild(card);
            });

            // Mostra o modal
            const modal = new bootstrap.Modal(document.getElementById('modalSelecaoAssociado'));
            modal.show();
        }

        // NOVA FUNÇÃO - Buscar associado selecionado
        async function buscarAssociadoSelecionado() {
            if (!associadoSelecionadoId) return;

            // Fecha o modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalSelecaoAssociado'));
            modal.hide();

            // Busca os dados do associado selecionado
            const loadingOverlay = document.getElementById('loadingBuscaFinanceiro');
            const dadosContainer = document.getElementById('dadosFinanceirosContainer');

            loadingOverlay.style.display = 'flex';

            try {
                const response = await fetch(`../api/associados/buscar_situacao_financeira.php?id=${associadoSelecionadoId}`);
                const result = await response.json();

                if (result.status === 'success') {
                    dadosFinanceirosAtual = result.data;
                    exibirDadosFinanceiros(result.data);
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
                    mostrarAlertaBuscaFinanceiro(result.message || 'Erro ao buscar dados', 'danger');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarAlertaBuscaFinanceiro('Erro ao consultar dados financeiros.', 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                // Reset seleção
                associadoSelecionadoId = null;
                document.getElementById('btnConfirmarSelecao').disabled = true;
            }
        }

        // Exibir dados financeiros do associado - ATUALIZADA
        function exibirDadosFinanceiros(dados) {
            const grid = document.getElementById('dadosFinanceirosGrid');
            const militarContainer = document.getElementById('identificacaoMilitar');
            const militarGrid = document.getElementById('militarInfoGrid');

            grid.innerHTML = '';
            militarGrid.innerHTML = '';

            // Exibe dados militares se existirem
            if (dados.dados_militares && dados.dados_militares.corporacao !== 'Não informada') {
                militarContainer.style.display = 'block';

                militarGrid.innerHTML = `
                    <div class="militar-info-item">
                        <span class="militar-info-label">Corporação</span>
                        <span class="militar-info-value">${dados.dados_militares.corporacao}</span>
                    </div>
                    <div class="militar-info-item">
                        <span class="militar-info-label">Patente</span>
                        <span class="militar-info-value">${dados.dados_militares.patente}</span>
                    </div>
                    <div class="militar-info-item">
                        <span class="militar-info-label">Unidade</span>
                        <span class="militar-info-value">${dados.dados_militares.unidade || 'Não informada'}</span>
                    </div>
                    <div class="militar-info-item">
                        <span class="militar-info-label">Lotação</span>
                        <span class="militar-info-value">${dados.dados_militares.lotacao || 'Não informada'}</span>
                    </div>
                `;
            } else {
                militarContainer.style.display = 'none';
            }

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
                    const situacaoClass = value.toLowerCase() === 'inadimplente' ? 'situacao-inadimplente' : 'situacao-adimplente';
                    valorFormatado = `<span class="badge-situacao-financeira ${situacaoClass}">${value}</span>`;
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
            grid.innerHTML += criarDadosItemFinanceiro('Tipo de Associado', financeiro.tipo_associado, 'fa-user-tag');
            grid.innerHTML += criarDadosItemFinanceiro('Valor Mensalidade', financeiro.valor_mensalidade, 'fa-dollar-sign', 'monetario');

            // Dados bancários
            if (financeiro.agencia) {
                grid.innerHTML += criarDadosItemFinanceiro('Agência', financeiro.agencia, 'fa-university');
            }
            if (financeiro.conta_corrente) {
                grid.innerHTML += criarDadosItemFinanceiro('Conta Corrente', financeiro.conta_corrente, 'fa-credit-card');
            }

            // Dados de débito (se houver)
            if (financeiro.valor_debito && financeiro.valor_debito > 0) {
                grid.innerHTML += criarDadosItemFinanceiro('Valor em Débito', financeiro.valor_debito, 'fa-exclamation-triangle', 'debito');
                grid.innerHTML += criarDadosItemFinanceiro('Meses em Atraso', financeiro.meses_atraso, 'fa-calendar-times');
            }

            // Último pagamento
            if (financeiro.ultimo_pagamento) {
                grid.innerHTML += criarDadosItemFinanceiro('Último Pagamento', formatarData(financeiro.ultimo_pagamento), 'fa-calendar-check');
            }

            // Serviços ativos
            if (financeiro.servicos_ativos && financeiro.servicos_ativos.length > 0) {
                let servicosHtml = '<ul class="mb-0">';
                financeiro.servicos_ativos.forEach(servico => {
                    servicosHtml += `<li>${servico.nome} - ${formatarMoeda(servico.valor)}</li>`;
                });
                servicosHtml += '</ul>';
                grid.innerHTML += criarDadosItemFinanceiro('Serviços Ativos', servicosHtml, 'fa-list-check');
            }

            // Observações
            if (financeiro.observacoes) {
                grid.innerHTML += criarDadosItemFinanceiro('Observações', financeiro.observacoes, 'fa-comment');
            }
        }

        // Limpar busca financeiro
        function limparBuscaFinanceiro() {
            document.getElementById('rgBuscaFinanceiro').value = '';
            document.getElementById('dadosFinanceirosContainer').style.display = 'none';
            document.getElementById('dadosFinanceirosGrid').innerHTML = '';
            document.getElementById('identificacaoMilitar').style.display = 'none';
            dadosFinanceirosAtual = null;
            associadoSelecionadoId = null;
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

        function relatorioArrecadacao() {
            notifications.show('Carregando relatório de arrecadação...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/estatisticas.php';
            }, 1000);
        }

        function listarInadimplentes() {
            notifications.show('Carregando lista de inadimplentes...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/lista_inadimplentes.php';
            }, 1000);
        }

        function extratoFinanceiro() {
            notifications.show('Abrindo extrato financeiro...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/extrato_financeiro.php';
            }, 1000);
        }

        function estatisticasFinanceiras() {
            notifications.show('Carregando estatísticas financeiras...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/dashboard_financeiro.php';
            }, 1000);
        }

        function importarAsaas() {
            notifications.show('Redirecionando para importação ASAAS...', 'info');
            setTimeout(() => {
                window.location.href = 'importar_asaas.php';
            }, 1000);
        }

        // Gestão de Pecúlio
        function gerenciarPeculio() {
            notifications.show('Carregando gestão de pecúlio...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/gestao_peculio.php';
            }, 1000);
        }

        // ===== FUNÇÕES AUXILIARES =====

        function formatarMoeda(valor) {
            if (!valor || valor === 0) return 'R$ 0,00';
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(valor);
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

        function formatarData(data) {
            if (!data) return '';
            try {
                const dataObj = new Date(data + 'T00:00:00');
                return dataObj.toLocaleDateString('pt-BR');
            } catch (e) {
                return data;
            }
        }

        function gerarRecorrencia() {
            notifications.show('Abrindo gerador de recorrência...', 'info');
            setTimeout(() => {
                window.location.href = 'gerar_recorrencia.php';
            }, 1000);
        }

        // Log de inicialização
        console.log('✓ Sistema de Serviços Financeiros carregado com sucesso!');
        console.log('📋 Suporte a múltiplos RGs de diferentes corporações ativado');
    </script>

</body>

</html>