<?php
/**
 * Página de Serviços Financeiros - Sistema ASSEGO
 * pages/financeiro.php
 * VERSÃO COM SISTEMA DE PERMISSÕES RBAC/ACL INTEGRADO
 * Sistema de navegação interno com componentes dinâmicos
 * ATUALIZADO COM VERIFICADOR DE ASSOCIADOS VIA CSV, IMPORTAR QUITAÇÃO E DESFILIAÇÕES
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
$page_title = 'Serviços Financeiros - ASSEGO';

// ===== SISTEMA DE PERMISSÕES RBAC/ACL =====
$permissoes = Permissoes::getInstance();

// Verificar permissão geral para o módulo financeiro
$temPermissaoFinanceiro = $permissoes->hasPermission('FINANCEIRO_DASHBOARD', 'VIEW');

// Verificar se é do departamento financeiro (ID 2) - DEFINIR ANTES DE USAR
$departamentoFinanceiro = 2;
$isFinanceiro = ($usuarioLogado['departamento_id'] == $departamentoFinanceiro);

// Verificar permissões específicas para cada recurso
$permissoesDetalhadas = [
    'dashboard' => $permissoes->hasPermission('FINANCEIRO_DASHBOARD', 'VIEW'),
    'inadimplentes' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_INADIMPLENTES_VISUALIZAR', 'VIEW'),
        'exportar' => $permissoes->hasPermission('FINANCEIRO_INADIMPLENTES_EXPORTAR', 'EXPORT'),
        'gerenciar' => $permissoes->hasPermission('FINANCEIRO_INADIMPLENTES', 'FULL')
    ],
    'neoconsig' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_NEOCONSIG', 'VIEW'),
        'gerar' => $permissoes->hasPermission('FINANCEIRO_NEOCONSIG_GERAR', 'CREATE'),
        'historico' => $permissoes->hasPermission('FINANCEIRO_NEOCONSIG_HISTORICO', 'VIEW')
    ],
    'asaas' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_ASAAS', 'VIEW'),
        'importar' => $permissoes->hasPermission('FINANCEIRO_ASAAS_IMPORTAR', 'CREATE'),
        'relatorio' => $permissoes->hasPermission('FINANCEIRO_ASAAS_RELATORIO', 'VIEW')
    ],
    'peculio' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_PECULIO', 'VIEW'),
        'cadastrar' => $permissoes->hasPermission('FINANCEIRO_PECULIO_CADASTRAR', 'CREATE'),
        'editar' => $permissoes->hasPermission('FINANCEIRO_PECULIO_EDITAR', 'EDIT'),
        'aprovar' => $permissoes->hasPermission('FINANCEIRO_PECULIO_APROVAR', 'APPROVE')
    ],
    'verificador' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_VERIFICADOR', 'VIEW') || $temPermissaoFinanceiro,
        'importar' => $permissoes->hasPermission('FINANCEIRO_VERIFICADOR_IMPORTAR', 'CREATE') || $temPermissaoFinanceiro
    ],
    'relatorios' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_RELATORIOS', 'VIEW'),
        'gerar' => $permissoes->hasPermission('FINANCEIRO_RELATORIOS_GERAR', 'CREATE')
    ],
    'pagamentos' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_PAGAMENTOS', 'VIEW'),
        'registrar' => $permissoes->hasPermission('FINANCEIRO_PAGAMENTOS_REGISTRAR', 'CREATE'),
        'estornar' => $permissoes->hasPermission('FINANCEIRO_PAGAMENTOS_ESTORNAR', 'DELETE')
    ],
    'desfiliacao' => [
        'visualizar' => $permissoes->hasPermission('FINANCEIRO_DESFILIACAO', 'VIEW') || $isFinanceiro,
        'aprovar' => $permissoes->hasPermission('FINANCEIRO_DESFILIACAO_APROVAR', 'APPROVE') || $isFinanceiro
    ]
];

// Níveis de acesso baseados em role + departamento
$isOperadorFinanceiro = $isFinanceiro && $permissoes->hasRole('FUNCIONARIO');
$isSupervisorFinanceiro = $isFinanceiro && $permissoes->hasRole('SUPERVISOR');
$isDiretorFinanceiro = $isFinanceiro && $permissoes->hasRole('DIRETOR');
$isPresidencia = $permissoes->hasRole('PRESIDENTE') || $permissoes->hasRole('SUPER_ADMIN');

$isDiretor = $permissoes->isDiretor();
$departamentoUsuario = $usuarioLogado['departamento_id'] ?? null;

// Log de debug das permissões
error_log("=== DEBUG PERMISSÕES FINANCEIRAS RBAC/ACL ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("ID: " . ($usuarioLogado['id'] ?? 'NULL'));
error_log("Departamento: " . ($departamentoUsuario ?? 'NULL'));
error_log("Tem permissão financeiro: " . ($temPermissaoFinanceiro ? 'SIM' : 'NÃO'));
error_log("Roles: Financeiro=" . ($isFinanceiro ? 'SIM' : 'NÃO') .
    ", Presidência=" . ($isPresidencia ? 'SIM' : 'NÃO') .
    ", Diretor=" . ($isDiretor ? 'SIM' : 'NÃO'));

$motivoNegacao = '';
if (!$temPermissaoFinanceiro) {
    $motivoNegacao = 'Você não possui permissão para acessar o módulo financeiro. Entre em contato com o administrador do sistema.';
    error_log("❌ ACESSO NEGADO: Sem permissão FINANCEIRO_DASHBOARD");
}

// Busca estatísticas do setor financeiro (apenas se tem permissão)
$totalAssociadosAtivos = 0;
$pagamentosHoje = 0;
$associadosInadimplentes = 0;
$arrecadacaoMes = 0;

if ($temPermissaoFinanceiro) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

        // Estatísticas apenas se tem permissão de visualizar dashboard
        if ($permissoesDetalhadas['dashboard']) {
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
        }

        // 3. Pagamentos recebidos hoje (se tem permissão)
        if ($permissoesDetalhadas['pagamentos']['visualizar']) {
            try {
                $sql = "SELECT COUNT(*) as hoje 
                        FROM Pagamentos_Associado 
                        WHERE DATE(data_pagamento) = CURDATE() 
                        AND status_pagamento = 'CONFIRMADO'";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $pagamentosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'] ?? 0;
            } catch (Exception $e) {
                $pagamentosHoje = 0;
            }
        }

        // 4. Associados inadimplentes (se tem permissão)
        if ($permissoesDetalhadas['inadimplentes']['visualizar']) {
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
        }

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas financeiras: " . $e->getMessage());
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $isDiretor,
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
            --finance-blue: #2563eb;
            --finance-green: #059669;
            --finance-red: #dc2626;
            --finance-orange: #ea580c;
            --finance-purple: #7c3aed;
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

        /* ÍCONES MODERNOS E PADRONIZADOS */
        .ativos-icon {
            background: linear-gradient(135deg, var(--finance-green) 0%, #10b981 100%);
            color: white;
        }

        .inadimplentes-icon {
            background: linear-gradient(135deg, var(--finance-red) 0%, #ef4444 100%);
            color: white;
        }

        .arrecadacao-icon {
            background: linear-gradient(135deg, var(--finance-blue) 0%, #3b82f6 100%);
            color: white;
        }

        .pagamentos-icon {
            background: linear-gradient(135deg, var(--finance-orange) 0%, #f97316 100%);
            color: white;
        }

        /* ===== SISTEMA DE NAVEGAÇÃO INTERNO ===== */
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
            padding: 1.25rem 1.5rem;
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
            gap: 0.75rem;
            min-height: 85px;
        }

        .nav-tab-btn:hover {
            background: rgba(0, 86, 210, 0.1);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .nav-tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 -2px 10px rgba(0, 86, 210, 0.1);
            transform: translateY(-2px);
        }

        .nav-tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--finance-blue) 100%);
        }

        .nav-tab-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            transition: transform 0.2s ease;
            margin-bottom: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .nav-tab-btn:hover .nav-tab-icon {
            transform: scale(1.05);
        }

        .nav-tab-btn.active .nav-tab-icon {
            transform: scale(1.1);
        }

        .nav-tab-label {
            font-size: 0.85rem;
            line-height: 1.2;
            font-weight: 600;
        }

        /* ÍCONES ESPECÍFICOS POR ABA */
        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="lista-inadimplentes"] .nav-tab-icon {
            background: #dc2626 !important;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="lista-inadimplentes"] .nav-tab-icon::before {
            content: "\f15c";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="neoconsig"] .nav-tab-icon {
            background: #2563eb !important;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="neoconsig"] .nav-tab-icon::before {
            content: "\f155";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="importar-asaas"] .nav-tab-icon {
            background: #ea580c !important;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="importar-asaas"] .nav-tab-icon::before {
            content: "\f0c3";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="gestao-peculio"] .nav-tab-icon {
            background: #ecc814ff !important;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="gestao-peculio"] .nav-tab-icon::before {
            content: "\f19c";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        /* Verificador de Associados */
        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="verificar-associados"] .nav-tab-icon {
            background: #059669 !important;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="verificar-associados"] .nav-tab-icon::before {
            content: "\f002";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        /* Importar Quitação Icon */
        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="importar-quitacao"] .nav-tab-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="importar-quitacao"] .nav-tab-icon::before {
            content: "\f560";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        /* Desfiliações Icon */
        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="desfiliacao-pendentes"] .nav-tab-icon {
            background: #9333ea !important;
        }

        .financial-nav-tabs .nav-tab .nav-tab-btn[data-target="desfiliacao-pendentes"] .nav-tab-icon::before {
            content: "\f15b";
            font-family: "Font Awesome 6 Pro", "Font Awesome 6 Free";
            font-weight: 900;
        }

        /* Content Area */
        .financial-content {
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
        }

        .financial-content>* {
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

        .content-panel>* {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        .content-panel .content-header {
            display: none !important;
        }

        #gestao-peculio,
        #lista-inadimplentes,
        #neoconsig,
        #importar-asaas,
        #verificar-associados,
        #importar-quitacao,
        #desfiliacao-pendentes {
            padding: 0 !important;
            margin: 0 !important;
            min-height: auto !important;
            height: auto !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        #gestao-peculio>*,
        #lista-inadimplentes>*,
        #neoconsig>*,
        #importar-asaas>*,
        #verificar-associados>*,
        #importar-quitacao>*,
        #desfiliacao-pendentes>* {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        .nav-tabs-container {
            margin-bottom: 0 !important;
            padding-bottom: 0 !important;
        }

        .financial-nav {
            margin-bottom: 0 !important;
        }

        .content-area>* {
            margin-top: 0 !important;
        }

        .mb-4 {
            margin-bottom: 0 !important;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Loading States */
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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

        /* Indicador de permissão */
        .permission-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            background: rgba(0, 86, 210, 0.1);
            color: var(--primary);
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.25rem;
            margin-left: 0.5rem;
        }

        .permission-badge.denied {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
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
                width: 35px;
                height: 35px;
                font-size: 1.5rem;
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

                    <?php if ($isDiretor && !$isFinanceiro): ?>
                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Você possui cargo de diretor mas não tem role específica do setor financeiro.
                                Para obter acesso, solicite ao administrador a atribuição de uma das seguintes roles:
                                <ul class="mt-2 mb-0">
                                    <li>FINANCEIRO_OPERADOR - Acesso básico aos recursos financeiros</li>
                                    <li>FINANCEIRO_SUPERVISOR - Acesso intermediário com aprovações</li>
                                    <li>FINANCEIRO_DIRETOR - Acesso completo ao módulo financeiro</li>
                                </ul>
                            </small>
                        </div>
                    <?php endif; ?>

                    <a href="../pages/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>
            <?php else: ?>
                <!-- Com Permissão -->

                <!-- Page Header -->
                <div class="mb-0" style="margin-bottom: 0.5rem !important;">
                    <h1 class="page-title">
                        Serviços Financeiros
                        <?php if ($isPresidencia): ?>
                            <span class="permission-badge">
                                <i class="fas fa-crown"></i> Presidência
                            </span>
                        <?php elseif ($isFinanceiro): ?>
                            <span class="permission-badge">
                                <i class="fas fa-user-tie"></i> Financeiro
                            </span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">Gerencie mensalidades, inadimplência, relatórios financeiros e arrecadação da ASSEGO</p>
                </div>

                <!-- KPIs Dashboard (se tem permissão) -->
                <?php if ($permissoesDetalhadas['dashboard']): ?>
                    <div class="stats-grid">
                        <!-- Card Associados Ativos/Inadimplentes -->
                        <div class="dual-stat-card">
                            <div class="dual-stat-header">
                                <span class="dual-stat-title">
                                    <i class="fas fa-users"></i>
                                    STATUS DOS ASSOCIADOS
                                </span>
                            </div>
                            <div class="dual-stats-row">
                                <div class="dual-stat-item">
                                    <div class="dual-stat-icon ativos-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div>
                                        <div class="dual-stat-value">
                                            <?php echo number_format($totalAssociadosAtivos, 0, ',', '.'); ?>
                                        </div>
                                        <div class="dual-stat-label">Ativos</div>
                                    </div>
                                </div>
                                <div class="dual-stats-separator"></div>
                                <div class="dual-stat-item">
                                    <div class="dual-stat-icon inadimplentes-icon">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                    <div>
                                        <div class="dual-stat-value">
                                            <?php echo number_format($associadosInadimplentes, 0, ',', '.'); ?>
                                        </div>
                                        <div class="dual-stat-label">Inadimplentes</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Arrecadação/Pagamentos -->
                        <div class="dual-stat-card">
                            <div class="dual-stat-header">
                                <span class="dual-stat-title">
                                    <i class="fas fa-chart-line"></i>
                                    MOVIMENTAÇÃO FINANCEIRA
                                </span>
                            </div>
                            <div class="dual-stats-row">
                                <div class="dual-stat-item">
                                    <div class="dual-stat-icon arrecadacao-icon">
                                        <i class="fas fa-dollar-sign"></i>
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
                    </div>
                <?php endif; ?>

                <!-- Navegação com verificação de permissões -->
                <div class="nav-tabs-container" style="margin: 0 !important; padding: 0 !important;">
                    <ul class="financial-nav-tabs">
                        <?php if ($permissoesDetalhadas['inadimplentes']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn active" data-target="lista-inadimplentes">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">Lista Inadimplentes</span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if ($permissoesDetalhadas['neoconsig']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn" data-target="neoconsig">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">NeoConsig</span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if ($permissoesDetalhadas['asaas']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn" data-target="importar-asaas">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">Importar ASAAS</span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if ($permissoesDetalhadas['peculio']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn" data-target="gestao-peculio">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">Gestão Pecúlio</span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <?php if ($permissoesDetalhadas['verificador']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn" data-target="verificar-associados">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">Verificar Associados</span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <!-- Importar Quitação (usa permissão do neoconsig) -->
                        <?php if ($permissoesDetalhadas['neoconsig']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn" data-target="importar-quitacao">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">Importar Quitação</span>
                                </button>
                            </li>
                        <?php endif; ?>

                        <!-- Desfiliações -->
                        <?php if ($permissoesDetalhadas['desfiliacao']['visualizar']): ?>
                            <li class="nav-tab">
                                <button class="nav-tab-btn" data-target="desfiliacao-pendentes">
                                    <div class="nav-tab-icon"></div>
                                    <span class="nav-tab-label">
                                        Desfiliações
                                        <span id="desfiliacao-badge" class="badge bg-danger" style="display: none; margin-left: 0.5rem;">0</span>
                                    </span>
                                </button>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Content Area -->
                <div class="financial-content" style="margin: 0 !important; padding: 0 !important;">
                    <?php if ($permissoesDetalhadas['inadimplentes']['visualizar']): ?>
                        <div id="lista-inadimplentes" class="content-panel active">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando lista de inadimplentes...</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($permissoesDetalhadas['neoconsig']['visualizar']): ?>
                        <div id="neoconsig" class="content-panel">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando gerador de recorrência...</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($permissoesDetalhadas['asaas']['visualizar']): ?>
                        <div id="importar-asaas" class="content-panel">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando importador ASAAS...</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($permissoesDetalhadas['peculio']['visualizar']): ?>
                        <div id="gestao-peculio" class="content-panel">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando gestão de pecúlio...</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($permissoesDetalhadas['verificador']['visualizar']): ?>
                        <div id="verificar-associados" class="content-panel">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando verificador de associados...</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Importar Quitação -->
                    <?php if ($permissoesDetalhadas['neoconsig']['visualizar']): ?>
                        <div id="importar-quitacao" class="content-panel">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando importador de quitação...</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Desfiliações -->
                    <?php if ($permissoesDetalhadas['desfiliacao']['visualizar']): ?>
                        <div id="desfiliacao-pendentes" class="content-panel">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p class="text-muted">Carregando desfiliações pendentes...</p>
                            </div>
                        </div>
                    <?php endif; ?>
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
        // ===== PASSAR PERMISSÕES PARA JAVASCRIPT =====
        const permissoesUsuario = <?php echo json_encode($permissoesDetalhadas); ?>;
        const temPermissaoFinanceiro = <?php echo json_encode($temPermissaoFinanceiro); ?>;
        const isFinanceiro = <?php echo json_encode($isFinanceiro); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;

        console.log('=== Permissões do Usuário ===');
        console.log('Tem permissão financeiro:', temPermissaoFinanceiro);
        console.log('É do financeiro:', isFinanceiro);
        console.log('É da presidência:', isPresidencia);
        console.log('Permissões detalhadas:', permissoesUsuario);

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
            'gestao-peculio': './rend/js/gestao_peculio.js?v=' + Date.now(),
            'lista-inadimplentes': './rend/js/lista_inadimplentes.js?v=' + Date.now(),
            'neoconsig': './rend/js/neoconsig.js?v=' + Date.now(),
            'importar-asaas': './rend/js/importar_asaas.js?v=' + Date.now(),
            'verificar-associados': './rend/js/verificar_associados.js?v=' + Date.now(),
            'importar-quitacao': './rend/js/importar_quitacao.js?v=' + Date.now(),
            'desfiliacao-pendentes': './rend/js/desfiliacao_financeiro.js?v=' + Date.now()
        };

        // ===== HELPER PARA CARREGAR SCRIPTS =====
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
                this.activeTab = this.getFirstAvailableTab();
                this.loadedTabs = this.activeTab ? new Set([this.activeTab]) : new Set();
                this.init();
            }

            getFirstAvailableTab() {
                if (permissoesUsuario.inadimplentes?.visualizar) return 'lista-inadimplentes';
                if (permissoesUsuario.neoconsig?.visualizar) return 'neoconsig';
                if (permissoesUsuario.asaas?.visualizar) return 'importar-asaas';
                if (permissoesUsuario.peculio?.visualizar) return 'gestao-peculio';
                if (permissoesUsuario.verificador?.visualizar) return 'verificar-associados';
                if (permissoesUsuario.desfiliacao?.visualizar) return 'desfiliacao-pendentes';
                return null;
            }

            init() {
                if (!this.activeTab) {
                    console.error('Nenhuma aba disponível com as permissões atuais');
                    return;
                }

                document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const target = e.currentTarget.dataset.target;
                        this.switchTab(target);
                    });
                });

                document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });

                const firstBtn = document.querySelector(`[data-target="${this.activeTab}"]`);
                if (firstBtn) {
                    firstBtn.classList.add('active');
                }

                this.loadTabContent(this.activeTab);
            }

            switchTab(tabId) {
                if (this.activeTab === tabId) return;

                const tabPermissions = {
                    'lista-inadimplentes': permissoesUsuario.inadimplentes?.visualizar,
                    'neoconsig': permissoesUsuario.neoconsig?.visualizar,
                    'importar-asaas': permissoesUsuario.asaas?.visualizar,
                    'gestao-peculio': permissoesUsuario.peculio?.visualizar,
                    'verificar-associados': permissoesUsuario.verificador?.visualizar,
                    'importar-quitacao': permissoesUsuario.neoconsig?.visualizar,
                    'desfiliacao-pendentes': permissoesUsuario.desfiliacao?.visualizar
                };

                if (!tabPermissions[tabId]) {
                    notifications.show('Você não tem permissão para acessar esta seção', 'warning');
                    return;
                }

                document.querySelectorAll('.nav-tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelector(`[data-target="${tabId}"]`).classList.add('active');

                document.querySelectorAll('.content-panel').forEach(panel => {
                    panel.classList.remove('active');
                });

                const targetPanel = document.getElementById(tabId);
                if (targetPanel) {
                    targetPanel.classList.add('active');

                    targetPanel.style.cssText = `
                        padding: 0 !important;
                        margin: 0 !important;
                        min-height: auto !important;
                        background: transparent !important;
                        border: none !important;
                        box-shadow: none !important;
                    `;

                    if (!this.loadedTabs.has(tabId)) {
                        this.loadTabContent(tabId);
                        this.loadedTabs.add(tabId);
                    }
                }

                this.activeTab = tabId;
                notifications.show(`Seção ${this.getTabName(tabId)} ativada`, 'info', 2000);
            }

            getTabName(tabId) {
                const names = {
                    'lista-inadimplentes': 'Lista de Inadimplentes',
                    'neoconsig': 'NeoConsig',
                    'importar-asaas': 'Importar ASAAS',
                    'gestao-peculio': 'Gestão de Pecúlio',
                    'verificar-associados': 'Verificador de Associados',
                    'importar-quitacao': 'Importar Quitação',
                    'desfiliacao-pendentes': 'Desfiliações Pendentes'
                };
                return names[tabId] || tabId;
            }

            async loadTabContent(tabId) {
                const panel = document.getElementById(tabId);
                if (!panel) {
                    console.error(`Painel não encontrado: ${tabId}`);
                    return;
                }

                const spinner = panel.querySelector('.loading-spinner');

                try {
                    console.log(`Carregando conteúdo da aba: ${tabId}`);

                    // MAPEAMENTO ESPECIAL DE ARQUIVOS
                    let partialUrl;

                    if (tabId === 'gestao-peculio') {
                        partialUrl = '../pages/rend/gestao_peculio_content.php';
                    } else if (tabId === 'desfiliacao-pendentes') {
                        partialUrl = './rend/desfiliacao_financeiro_content.php';
                    } else {
                        partialUrl = `./rend/${tabId.replace(/-/g, '_')}_content.php`;
                    }

                    console.log(`Buscando partial: ${partialUrl}`);

                    const response = await fetch(partialUrl);

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status} - ${response.statusText}`);
                    }

                    const htmlContent = await response.text();
                    console.log(`✅ HTML carregado: ${htmlContent.length} chars`);

                    if (spinner) {
                        spinner.style.display = 'none';
                    }

                    panel.innerHTML = htmlContent;

                    panel.style.cssText = `
                        padding: 0 !important;
                        margin: 0 !important;
                        min-height: auto !important;
                        height: auto !important;
                        background: transparent !important;
                        border: none !important;
                        box-shadow: none !important;
                    `;

                    const contentHeaders = panel.querySelectorAll('.content-header');
                    contentHeaders.forEach(header => header.remove());

                    Array.from(panel.children).forEach(child => {
                        if (child.classList.contains('content-header')) {
                            child.remove();
                        } else {
                            child.style.cssText = `
                                margin-top: 0 !important;
                                padding-top: 0 !important;
                            `;
                        }
                    });

                    console.log('✅ Estilos aplicados');

                    const scriptSrc = TAB_SCRIPTS[tabId];
                    if (scriptSrc) {
                        console.log(`Carregando script: ${scriptSrc}`);
                        await loadScriptOnce(scriptSrc);
                        await this.initializeTabModule(tabId);
                    }

                    console.log(`✅ Aba ${tabId} carregada com sucesso`);

                } catch (error) {
                    console.error(`❌ Erro ao carregar ${tabId}:`, error);

                    if (spinner) {
                        spinner.style.display = 'none';
                    }

                    panel.innerHTML = `
                        <div class="alert alert-danger" style="margin: 1rem; padding: 1rem;">
                            <h4><i class="fas fa-exclamation-triangle"></i> Erro ao Carregar</h4>
                            <p><strong>Aba:</strong> ${this.getTabName(tabId)}</p>
                            <p><strong>Erro:</strong> ${error.message}</p>
                            <hr>
                            <button class="btn btn-primary mt-2" onclick="financialNav.retryLoad('${tabId}')">
                                <i class="fas fa-redo"></i> Tentar Novamente
                            </button>
                        </div>
                    `;
                }
            }

            async initializeTabModule(tabId) {
                try {
                    switch (tabId) {
                        case 'gestao-peculio':
                            if (window.Peculio) {
                                console.log('Inicializando módulo Peculio com permissões...');
                                window.Peculio.init({
                                    temPermissao: temPermissaoFinanceiro,
                                    isFinanceiro: isFinanceiro,
                                    isPresidencia: isPresidencia,
                                    permissoes: permissoesUsuario.peculio
                                });
                            }
                            break;

                        case 'lista-inadimplentes':
                            if (window.ListaInadimplentes) {
                                console.log('Inicializando módulo Lista de Inadimplentes com permissões...');
                                window.ListaInadimplentes.init({
                                    temPermissao: temPermissaoFinanceiro,
                                    permissoes: permissoesUsuario.inadimplentes
                                });
                            }
                            break;

                        case 'neoconsig':
                            if (window.NeoConsig) {
                                console.log('Inicializando módulo NeoConsig com permissões...');
                                window.NeoConsig.init({
                                    temPermissao: temPermissaoFinanceiro,
                                    permissoes: permissoesUsuario.neoconsig
                                });
                            }
                            break;

                        case 'importar-asaas':
                            if (window.ImportarAsaas) {
                                console.log('Inicializando módulo Importar ASAAS com permissões...');
                                window.ImportarAsaas.init({
                                    temPermissao: temPermissaoFinanceiro,
                                    permissoes: permissoesUsuario.asaas
                                });
                            }
                            break;

                        case 'verificar-associados':
                            if (window.VerificarAssociados) {
                                console.log('Inicializando módulo Verificador de Associados com permissões...');
                                window.VerificarAssociados.init({
                                    temPermissao: temPermissaoFinanceiro,
                                    permissoes: permissoesUsuario.verificador
                                });
                            }
                            break;

                        case 'importar-quitacao':
                            if (window.ImportarQuitacao) {
                                console.log('Inicializando módulo Importar Quitação com permissões...');
                                window.ImportarQuitacao.init({
                                    temPermissao: temPermissaoFinanceiro,
                                    permissoes: permissoesUsuario.neoconsig
                                });
                            }
                            break;

                        case 'desfiliacao-pendentes':
                            console.log('✅ Inicializando módulo Desfiliações Financeiro...');
                            if (typeof window.carregarDesfiliaçõesFinanceiro === 'function') {
                                console.log('📋 Carregando lista de desfiliações...');
                                await window.carregarDesfiliaçõesFinanceiro();
                            } else {
                                console.error('❌ Função carregarDesfiliaçõesFinanceiro não encontrada');
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

                if (panel) {
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
        }

        // ===== VARIÁVEIS GLOBAIS =====
        const notifications = new NotificationSystem();
        let financialNav;

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function () {
            AOS.init({
                duration: 800,
                once: true
            });

            if (!temPermissaoFinanceiro) {
                console.log('❌ Usuário sem permissão para módulo financeiro');
                return;
            }

            financialNav = new FinancialNavigation();

            if (isPresidencia) {
                notifications.show('Sistema financeiro carregado - Acesso Presidência', 'success', 3000);
            } else if (isFinanceiro) {
                notifications.show('Sistema financeiro carregado - Acesso Setor Financeiro', 'success', 3000);
            } else {
                notifications.show('Sistema financeiro carregado', 'success', 3000);
            }

            console.log('✅ Sistema Financeiro inicializado com permissões RBAC/ACL');
        });
    </script>
</body>

</html>