<?php
/**
 * Página de Controle de Notificações - Sistema ASSEGO
 * pages/notificacoes.php
 * 
 * Lista e gerencia todas as notificações do sistema
 * Versão corporativa com design profissional
 * 
 * @author Senior PHP Developer
 * @version 2.0.0
 * @since 2025-01-01
 */

// Configuração de ambiente de produção
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Importação de dependências
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/NotificacoesManager.php';
require_once './components/header.php';

// Inicialização do sistema de autenticação
$auth = new Auth();

// Verificação de sessão ativa
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

// Recuperação de dados do usuário autenticado
$usuarioLogado = $auth->getUser();

// Configuração de metadados da página
$page_title = 'Central de Notificações - ASSEGO';

// Sistema de controle de permissões baseado em departamento
$temPermissaoControle = false;
$motivoNegacao = '';
$isFinanceiro = false;
$isPresidencia = false;
$isDiretoria = false;
$departamentoUsuario = null;

// Log de debug para rastreamento de permissões
error_log("=== DEBUG PERMISSÕES NOTIFICAÇÕES ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Lógica de verificação de permissões por departamento
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;
    
    if ($deptId == 2) { // Setor Financeiro
        $temPermissaoControle = true;
        $isFinanceiro = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Financeiro (ID: 2)");
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoControle = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } elseif ($auth->isDiretor()) { // Diretoria
        $temPermissaoControle = true;
        $isDiretoria = true;
        error_log("✅ Permissão concedida: Usuário é Diretor");
    } else {
        $motivoNegacao = 'Acesso restrito ao Setor Financeiro, Presidência e Diretoria.';
        error_log("❌ Acesso negado. Departamento: '$deptId' (tipo: " . gettype($deptId) . "). Permitido: Financeiro (ID: 2), Presidência (ID: 1) ou Diretores");
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Inicialização de variáveis de estatísticas
$totalNotificacoes = 0;
$notificacaoNaoLidas = 0;
$notificacoesLidas = 0;
$notificacoesHoje = 0;
$notificacoesUrgentes = 0;
$notificacoesFinanceiras = 0;
$notificacoesCadastrais = 0;
$notificacoesObservacoes = 0;
$erroCarregamentoStats = null;

// Busca de estatísticas do banco de dados
if ($temPermissaoControle) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Query otimizada para estatísticas de notificações
        $sqlEstatisticas = "
            SELECT 
                COUNT(*) as total_notificacoes,
                SUM(CASE WHEN lida = 0 THEN 1 ELSE 0 END) as nao_lidas,
                SUM(CASE WHEN lida = 1 THEN 1 ELSE 0 END) as lidas,
                SUM(CASE WHEN DATE(data_criacao) = CURDATE() THEN 1 ELSE 0 END) as hoje,
                SUM(CASE WHEN prioridade = 'URGENTE' AND lida = 0 THEN 1 ELSE 0 END) as urgentes,
                SUM(CASE WHEN tipo = 'ALTERACAO_FINANCEIRO' THEN 1 ELSE 0 END) as financeiras,
                SUM(CASE WHEN tipo = 'ALTERACAO_CADASTRO' THEN 1 ELSE 0 END) as cadastrais,
                SUM(CASE WHEN tipo = 'NOVA_OBSERVACAO' THEN 1 ELSE 0 END) as observacoes
            FROM Notificacoes n
            LEFT JOIN Funcionarios f ON f.id = ?
            WHERE (n.funcionario_id = ? OR (n.funcionario_id IS NULL AND n.departamento_id = f.departamento_id))
            AND n.ativo = 1
        ";
        
        $stmt = $db->prepare($sqlEstatisticas);
        $stmt->execute([$usuarioLogado['id'], $usuarioLogado['id']]);
        $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($estatisticas) {
            $totalNotificacoes = (int)($estatisticas['total_notificacoes'] ?? 0);
            $notificacaoNaoLidas = (int)($estatisticas['nao_lidas'] ?? 0);
            $notificacoesLidas = (int)($estatisticas['lidas'] ?? 0);
            $notificacoesHoje = (int)($estatisticas['hoje'] ?? 0);
            $notificacoesUrgentes = (int)($estatisticas['urgentes'] ?? 0);
            $notificacoesFinanceiras = (int)($estatisticas['financeiras'] ?? 0);
            $notificacoesCadastrais = (int)($estatisticas['cadastrais'] ?? 0);
            $notificacoesObservacoes = (int)($estatisticas['observacoes'] ?? 0);
            
            error_log("✅ Estatísticas de notificações carregadas:");
            error_log("   Total: $totalNotificacoes");
            error_log("   Não lidas: $notificacaoNaoLidas");
            error_log("   Lidas: $notificacoesLidas");
            error_log("   Hoje: $notificacoesHoje");
        }

    } catch (Exception $e) {
        $erroCarregamentoStats = $e->getMessage();
        error_log("❌ Erro ao buscar estatísticas de notificações: " . $e->getMessage());
        
        // Valores padrão em caso de erro
        $totalNotificacoes = 0;
        $notificacaoNaoLidas = 0;
        $notificacoesLidas = 0;
        $notificacoesHoje = 0;
    }
}

// Cálculo de percentuais para indicadores
$percentualNaoLidas = $totalNotificacoes > 0 ? round(($notificacaoNaoLidas / $totalNotificacoes) * 100, 1) : 0;
$percentualLidas = $totalNotificacoes > 0 ? round(($notificacoesLidas / $totalNotificacoes) * 100, 1) : 0;

// Criação da instância do componente de cabeçalho
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'notificacoes',
    'notificationCount' => $notificacaoNaoLidas,
    'showSearch' => false
]);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon corporativo -->
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Professional Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts - Inter para design corporativo -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery Library -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <style>
        /* ===== VARIÁVEIS CSS CORPORATIVAS ===== */
        :root {
            /* Cores primárias corporativas */
            --corporate-primary: #1e3a5f;
            --corporate-primary-light: #2c4a70;
            --corporate-primary-dark: #152940;
            
            /* Cores de status corporativas */
            --corporate-danger: #8b1a1a;
            --corporate-danger-light: #a62626;
            --corporate-success: #0d5f0d;
            --corporate-success-light: #146314;
            --corporate-warning: #cc5500;
            --corporate-warning-light: #e06000;
            --corporate-info: #003366;
            --corporate-info-light: #004080;
            
            /* Cores auxiliares corporativas */
            --corporate-purple: #4a148c;
            --corporate-purple-light: #5e1ba0;
            --corporate-orange: #bf4300;
            --corporate-orange-light: #d94f00;
            --corporate-teal: #006064;
            --corporate-teal-light: #007478;
            --corporate-gray: #495057;
            --corporate-gray-light: #6c757d;
            
            /* Cores neutras */
            --corporate-dark: #1a1a1a;
            --corporate-light: #f8f9fa;
            --corporate-white: #ffffff;
            --corporate-bg: #f5f6fa;
            --corporate-border: #d1d5db;
            --corporate-text: #2c3e50;
            --corporate-text-muted: #6b7280;
            
            /* Sombras corporativas */
            --corporate-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --corporate-shadow-md: 0 4px 6px rgba(0,0,0,0.07);
            --corporate-shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            --corporate-shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
        }

        /* ===== RESET E CONFIGURAÇÃO BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: var(--corporate-bg);
            color: var(--corporate-text);
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ===== ESTRUTURA PRINCIPAL ===== */
        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 24px;
            background: var(--corporate-bg);
        }

        /* ===== CABEÇALHO DA PÁGINA ===== */
        .page-header {
            margin-bottom: 32px;
            padding: 0;
            background: transparent;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--corporate-text);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--corporate-text-muted);
            font-weight: 400;
        }

        /* ===== CARDS DE ESTATÍSTICAS CORPORATIVAS ===== */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--corporate-white);
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--corporate-border);
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--corporate-shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        /* Barras superiores dos cards com cores corporativas */
        .stat-card.corporate-primary::before { background: var(--corporate-primary); }
        .stat-card.corporate-danger::before { background: var(--corporate-danger); }
        .stat-card.corporate-success::before { background: var(--corporate-success); }
        .stat-card.corporate-warning::before { background: var(--corporate-warning); }
        .stat-card.corporate-info::before { background: var(--corporate-info); }
        .stat-card.corporate-purple::before { background: var(--corporate-purple); }
        .stat-card.corporate-orange::before { background: var(--corporate-orange); }
        .stat-card.corporate-gray::before { background: var(--corporate-gray); }

        /* Ícones dos cards corporativos */
        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            color: var(--corporate-white);
            font-size: 20px;
        }

        /* Cores sólidas para ícones - design corporativo */
        .stat-card.corporate-primary .stat-icon-wrapper { 
            background: var(--corporate-primary);
            box-shadow: 0 4px 14px rgba(30, 58, 95, 0.25);
        }
        .stat-card.corporate-danger .stat-icon-wrapper { 
            background: var(--corporate-danger);
            box-shadow: 0 4px 14px rgba(139, 26, 26, 0.25);
        }
        .stat-card.corporate-success .stat-icon-wrapper { 
            background: var(--corporate-success);
            box-shadow: 0 4px 14px rgba(13, 95, 13, 0.25);
        }
        .stat-card.corporate-warning .stat-icon-wrapper { 
            background: var(--corporate-warning);
            box-shadow: 0 4px 14px rgba(204, 85, 0, 0.25);
        }
        .stat-card.corporate-info .stat-icon-wrapper { 
            background: var(--corporate-info);
            box-shadow: 0 4px 14px rgba(0, 51, 102, 0.25);
        }
        .stat-card.corporate-purple .stat-icon-wrapper { 
            background: var(--corporate-purple);
            box-shadow: 0 4px 14px rgba(74, 20, 140, 0.25);
        }
        .stat-card.corporate-orange .stat-icon-wrapper { 
            background: var(--corporate-orange);
            box-shadow: 0 4px 14px rgba(191, 67, 0, 0.25);
        }
        .stat-card.corporate-gray .stat-icon-wrapper { 
            background: var(--corporate-gray);
            box-shadow: 0 4px 14px rgba(73, 80, 87, 0.25);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--corporate-text);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--corporate-text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-percentage {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            gap: 4px;
        }

        .stat-percentage.success {
            background: rgba(13, 95, 13, 0.1);
            color: var(--corporate-success);
        }

        .stat-percentage.danger {
            background: rgba(139, 26, 26, 0.1);
            color: var(--corporate-danger);
        }

        /* ===== SEÇÃO DE FILTROS CORPORATIVOS ===== */
        .filtros-container {
            background: var(--corporate-white);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--corporate-border);
        }

        .filtros-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .filtros-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--corporate-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filtros-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filtro-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--corporate-text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control, .form-select {
            height: 40px;
            border: 1px solid var(--corporate-border);
            border-radius: 6px;
            font-size: 14px;
            padding: 0 12px;
            transition: all 0.2s;
            background: var(--corporate-white);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--corporate-primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
            outline: none;
        }

        /* ===== TABS DE NAVEGAÇÃO CORPORATIVA ===== */
        .filter-tabs {
            background: var(--corporate-white);
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid var(--corporate-border);
            padding: 8px;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 0;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            color: var(--corporate-text-muted);
            background: transparent;
            transition: all 0.2s;
            margin-right: 4px;
        }

        .nav-tabs .nav-link:hover {
            background: var(--corporate-light);
            color: var(--corporate-primary);
        }

        .nav-tabs .nav-link.active {
            background: var(--corporate-primary);
            color: var(--corporate-white);
        }

        .nav-tabs .badge {
            margin-left: 8px;
            padding: 2px 6px;
            font-size: 11px;
            border-radius: 10px;
            font-weight: 600;
        }

        /* ===== TABELAS CORPORATIVAS ===== */
        .notificacoes-container {
            background: var(--corporate-white);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--corporate-border);
            margin-bottom: 24px;
        }

        .notificacoes-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--corporate-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notificacoes-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--corporate-text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-notificacoes {
            margin: 0;
            font-size: 14px;
        }

        .table-notificacoes thead th {
            background: var(--corporate-light);
            border: none;
            padding: 12px 24px;
            font-weight: 600;
            color: var(--corporate-text-muted);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--corporate-border);
        }

        .table-notificacoes tbody td {
            padding: 16px 24px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .table-notificacoes tbody tr:hover {
            background: #fafbfc;
        }

        .table-notificacoes tbody tr.notif-nao-lida {
            background: #fffef5;
            font-weight: 500;
        }

        .table-notificacoes tbody tr.notif-lida {
            opacity: 0.85;
        }

        /* ===== BADGES CORPORATIVOS ===== */
        .badge-tipo {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .tipo-financeiro {
            background: rgba(0, 51, 102, 0.1);
            color: var(--corporate-info);
        }

        .tipo-observacao {
            background: rgba(73, 80, 87, 0.1);
            color: var(--corporate-gray);
        }

        .tipo-cadastro {
            background: rgba(74, 20, 140, 0.1);
            color: var(--corporate-purple);
        }

        .badge-prioridade {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .prioridade-urgente {
            background: rgba(139, 26, 26, 0.1);
            color: var(--corporate-danger);
            animation: pulse 2s infinite;
        }

        .prioridade-alta {
            background: rgba(204, 85, 0, 0.1);
            color: var(--corporate-warning);
        }

        .prioridade-media {
            background: rgba(0, 51, 102, 0.1);
            color: var(--corporate-info);
        }

        .prioridade-baixa {
            background: rgba(73, 80, 87, 0.1);
            color: var(--corporate-gray);
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-lida {
            background: rgba(13, 95, 13, 0.1);
            color: var(--corporate-success);
        }

        .status-nao-lida {
            background: rgba(139, 26, 26, 0.1);
            color: var(--corporate-danger);
        }

        /* ===== BOTÕES CORPORATIVOS ===== */
        .btn {
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: var(--corporate-primary);
            color: var(--corporate-white);
        }

        .btn-primary:hover {
            background: var(--corporate-primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--corporate-shadow-md);
        }

        .btn-secondary {
            background: var(--corporate-light);
            color: var(--corporate-text-muted);
            border: 1px solid var(--corporate-border);
        }

        .btn-secondary:hover {
            background: #e9ecef;
            color: var(--corporate-text);
        }

        .btn-success {
            background: var(--corporate-success);
            color: var(--corporate-white);
        }

        .btn-success:hover {
            background: var(--corporate-success-light);
            transform: translateY(-1px);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-acao {
            padding: 4px 10px;
            font-size: 12px;
            border-radius: 4px;
            text-decoration: none;
        }

        .btn-marcar-lida {
            background: var(--corporate-success);
            color: var(--corporate-white);
        }

        .btn-marcar-lida:hover {
            background: var(--corporate-success-light);
            color: var(--corporate-white);
            transform: translateY(-1px);
        }

        .btn-detalhes {
            background: var(--corporate-primary);
            color: var(--corporate-white);
        }

        .btn-detalhes:hover {
            background: var(--corporate-primary-dark);
            color: var(--corporate-white);
            transform: translateY(-1px);
        }

        /* ===== PAGINAÇÃO CORPORATIVA ===== */
        .pagination-container {
            background: var(--corporate-white);
            border-radius: 8px;
            padding: 20px 24px;
            margin-top: 16px;
            border: 1px solid var(--corporate-border);
        }

        .pagination-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .registros-info {
            color: var(--corporate-text-muted);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .registros-por-pagina {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .registros-por-pagina label {
            font-size: 13px;
            color: var(--corporate-text-muted);
            margin: 0;
        }

        .registros-por-pagina select {
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid var(--corporate-border);
            font-size: 13px;
            min-width: 60px;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
        }

        .pagination-nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            gap: 4px;
        }

        .pagination-nav .page-item .page-link {
            padding: 6px 12px;
            border: 1px solid var(--corporate-border);
            background: var(--corporate-white);
            color: var(--corporate-text-muted);
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            min-width: 36px;
            text-align: center;
            display: inline-block;
            transition: all 0.2s;
        }

        .pagination-nav .page-link:hover {
            background: var(--corporate-light);
            color: var(--corporate-primary);
            border-color: var(--corporate-primary);
        }

        .pagination-nav .page-item.active .page-link {
            background: var(--corporate-primary);
            color: var(--corporate-white);
            border-color: var(--corporate-primary);
        }

        .pagination-nav .page-item.disabled .page-link {
            color: #c6c6c6;
            cursor: not-allowed;
            background: var(--corporate-light);
        }

        .pagination-nav .page-item.disabled .page-link:hover {
            background: var(--corporate-light);
            color: #c6c6c6;
            border-color: var(--corporate-border);
        }

        /* ===== ELEMENTOS ESPECÍFICOS CORPORATIVOS ===== */
        .notif-titulo {
            font-weight: 600;
            color: var(--corporate-text);
            margin-bottom: 4px;
        }

        .notif-mensagem {
            color: var(--corporate-text-muted);
            font-size: 13px;
            line-height: 1.4;
        }

        .notif-meta {
            color: #95a5a6;
            font-size: 11px;
        }

        .notif-associado {
            font-weight: 500;
            color: var(--corporate-text);
        }

        .notif-data {
            color: var(--corporate-text-muted);
            font-size: 13px;
        }

        /* ===== LOADING E ESTADOS VAZIOS CORPORATIVOS ===== */
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
            border-radius: 8px;
            z-index: 1000;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--corporate-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            background: var(--corporate-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--corporate-border);
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--corporate-text);
            margin-bottom: 8px;
        }

        .empty-text {
            color: var(--corporate-text-muted);
            font-size: 14px;
        }

        /* ===== TOAST CONTAINER CORPORATIVO ===== */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
        }

        /* ===== RESPONSIVIDADE MOBILE ===== */
        @media (max-width: 768px) {
            .content-area {
                padding: 16px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filtros-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filtro-group {
                min-width: 100%;
            }
            
            .table-responsive {
                font-size: 12px;
            }
            
            .btn-acao {
                font-size: 11px;
                padding: 3px 8px;
            }

            .pagination-top {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* ===== ANIMAÇÕES CORPORATIVAS ===== */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== ALERTAS CORPORATIVOS ===== */
        .alert {
            border: none;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: rgba(139, 26, 26, 0.1);
            color: var(--corporate-danger);
        }

        .alert-info {
            background: rgba(0, 51, 102, 0.1);
            color: var(--corporate-info);
        }

        /* ===== CLASSES UTILITÁRIAS CORPORATIVAS ===== */
        .text-corporate-primary { color: var(--corporate-primary) !important; }
        .text-corporate-danger { color: var(--corporate-danger) !important; }
        .text-corporate-success { color: var(--corporate-success) !important; }
        .text-corporate-warning { color: var(--corporate-warning) !important; }
        .text-corporate-info { color: var(--corporate-info) !important; }
        .text-corporate-muted { color: var(--corporate-text-muted) !important; }
        
        .bg-corporate-primary { background-color: var(--corporate-primary) !important; }
        .bg-corporate-danger { background-color: var(--corporate-danger) !important; }
        .bg-corporate-success { background-color: var(--corporate-success) !important; }
        .bg-corporate-warning { background-color: var(--corporate-warning) !important; }
        .bg-corporate-info { background-color: var(--corporate-info) !important; }
    </style>
</head>

<body>
    <!-- Container de Notificações Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <!-- Wrapper Principal -->
    <div class="main-wrapper">
        <!-- Componente de Cabeçalho -->
        <?php $headerComponent->render(); ?>

        <!-- Área de Conteúdo Principal -->
        <div class="content-area">
            <?php if (!$temPermissaoControle): ?>
            <!-- Interface de Acesso Negado -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado às Notificações</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Requisitos para acesso:</h6>
                    <ul class="mb-0">
                        <li>Estar no <strong>Setor Financeiro</strong> (Departamento ID: 2) OU</li>
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
            <!-- Interface Principal com Permissão -->
            
            <!-- Cabeçalho da Página -->
            <div class="page-header">
                <h1 class="page-title">Central de Notificações</h1>
                <p class="page-subtitle">Gerencie todas as notificações do sistema</p>
            </div>

            <!-- Cards de Estatísticas Corporativas -->
            <div class="stats-container" data-aos="fade-up">
                <div class="stat-card corporate-primary">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalNotificacoes, 0, ',', '.'); ?></div>
                    <div class="stat-label">Total de Notificações</div>
                </div>
                
                <div class="stat-card corporate-danger">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($notificacaoNaoLidas, 0, ',', '.'); ?></div>
                    <div class="stat-label">Não Lidas</div>
                    <?php if ($totalNotificacoes > 0): ?>
                    <div class="stat-percentage danger">
                        <i class="fas fa-chart-line"></i>
                        <?php echo $percentualNaoLidas; ?>% do total
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card corporate-success">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($notificacoesLidas, 0, ',', '.'); ?></div>
                    <div class="stat-label">Lidas</div>
                    <?php if ($totalNotificacoes > 0): ?>
                    <div class="stat-percentage success">
                        <i class="fas fa-check"></i>
                        <?php echo $percentualLidas; ?>% do total
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card corporate-gray">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($notificacoesHoje, 0, ',', '.'); ?></div>
                    <div class="stat-label">Recebidas Hoje</div>
                </div>
                
                <div class="stat-card corporate-warning">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($notificacoesUrgentes, 0, ',', '.'); ?></div>
                    <div class="stat-label">Urgentes</div>
                </div>
                
                <div class="stat-card corporate-info">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($notificacoesFinanceiras, 0, ',', '.'); ?></div>
                    <div class="stat-label">Financeiras</div>
                </div>
                
                <div class="stat-card corporate-purple">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($notificacoesCadastrais, 0, ',', '.'); ?></div>
                    <div class="stat-label">Cadastrais</div>
                </div>

                <div class="stat-card corporate-orange">
                    <div class="stat-icon-wrapper">
                        <i class="fas fa-comment"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($notificacoesObservacoes, 0, ',', '.'); ?></div>
                    <div class="stat-label">Observações</div>
                </div>
            </div>

            <!-- Sistema de Abas de Navegação -->
            <div class="filter-tabs" data-aos="fade-up">
                <ul class="nav nav-tabs" id="notificationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="nao-lidas-tab" data-bs-toggle="tab" data-bs-target="#nao-lidas" type="button" role="tab">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Não Lidas <span class="badge bg-danger ms-1" id="badge-nao-lidas"><?php echo $notificacaoNaoLidas; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="todas-tab" data-bs-toggle="tab" data-bs-target="#todas" type="button" role="tab">
                            <i class="fas fa-list me-2"></i>
                            Todas <span class="badge bg-secondary ms-1" id="badge-todas"><?php echo $totalNotificacoes; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="lidas-tab" data-bs-toggle="tab" data-bs-target="#lidas" type="button" role="tab">
                            <i class="fas fa-check-circle me-2"></i>
                            Lidas <span class="badge bg-success ms-1" id="badge-lidas"><?php echo $notificacoesLidas; ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="notificationTabContent">
                    <!-- Aba: Notificações Não Lidas -->
                    <div class="tab-pane fade show active" id="nao-lidas" role="tabpanel">
                        <!-- Sistema de Filtros -->
                        <div class="filtros-container">
                            <div class="filtros-header">
                                <h5 class="filtros-title">
                                    <i class="fas fa-filter text-corporate-primary"></i>
                                    Filtros - Notificações Não Lidas
                                </h5>
                                <button type="button" class="btn btn-success btn-sm" onclick="marcarTodasLidas()">
                                    <i class="fas fa-check-double"></i>
                                    Marcar Todas como Lidas
                                </button>
                            </div>
                            
                            <form class="filtros-form" onsubmit="filtrarNotificacoes(event, 'nao-lidas')">
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroTipoNaoLidas">Tipo</label>
                                    <select class="form-select" id="filtroTipoNaoLidas">
                                        <option value="todos">Todos os tipos</option>
                                        <option value="ALTERACAO_FINANCEIRO">Alteração Financeira</option>
                                        <option value="NOVA_OBSERVACAO">Nova Observação</option>
                                        <option value="ALTERACAO_CADASTRO">Alteração Cadastral</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroPrioridadeNaoLidas">Prioridade</label>
                                    <select class="form-select" id="filtroPrioridadeNaoLidas">
                                        <option value="todas">Todas</option>
                                        <option value="URGENTE">Urgente</option>
                                        <option value="ALTA">Alta</option>
                                        <option value="MEDIA">Média</option>
                                        <option value="BAIXA">Baixa</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group" style="flex: 2;">
                                    <label class="form-label" for="filtroBuscaNaoLidas">Buscar</label>
                                    <input type="text" class="form-control" id="filtroBuscaNaoLidas" 
                                           placeholder="Título, associado ou mensagem...">
                                </div>
                                
                                <div style="min-width: auto;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                        Filtrar
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros('nao-lidas')">
                                        <i class="fas fa-eraser"></i>
                                        Limpar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tabela de Notificações Não Lidas -->
                        <div class="notificacoes-container">
                            <div class="notificacoes-header">
                                <h3>
                                    <i class="fas fa-exclamation-circle text-corporate-danger"></i>
                                    Notificações Não Lidas
                                    <small id="modoPaginacaoNaoLidas" style="font-size: 11px; opacity: 0.7; margin-left: 10px;"></small>
                                </h3>
                                <button class="btn btn-secondary btn-sm" onclick="atualizarNotificacoes('nao-lidas')">
                                    <i class="fas fa-sync"></i>
                                    Atualizar
                                </button>
                            </div>
                            
                            <div class="table-responsive" style="position: relative;">
                                <table class="table table-notificacoes" id="tabelaNaoLidas">
                                    <thead>
                                        <tr>
                                            <th>Notificação</th>
                                            <th>Tipo</th>
                                            <th>Prioridade</th>
                                            <th>Associado</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpoTabelaNaoLidas">
                                        <!-- Dados carregados dinamicamente via JavaScript -->
                                    </tbody>
                                </table>
                                
                                <div id="loadingNaoLidas" class="loading-overlay" style="display: none;">
                                    <div class="loading-spinner mb-3"></div>
                                    <p class="text-muted">Carregando notificações...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Sistema de Paginação -->
                        <div class="pagination-container" id="paginationNaoLidas" style="display: none;">
                            <div class="pagination-top">
                                <div class="pagination-info">
                                    <div class="registros-info">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="registrosInfoNaoLidas">Carregando...</span>
                                    </div>
                                    <div class="registros-por-pagina">
                                        <label for="registrosPorPaginaNaoLidas">Registros por página:</label>
                                        <select id="registrosPorPaginaNaoLidas" onchange="alterarRegistrosPorPagina('nao-lidas')">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-controls">
                                    <ul class="pagination-nav" id="paginationNavNaoLidas"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Todas as Notificações -->
                    <div class="tab-pane fade" id="todas" role="tabpanel">
                        <!-- Sistema de Filtros -->
                        <div class="filtros-container">
                            <div class="filtros-header">
                                <h5 class="filtros-title">
                                    <i class="fas fa-filter text-corporate-primary"></i>
                                    Filtros - Todas as Notificações
                                </h5>
                            </div>
                            
                            <form class="filtros-form" onsubmit="filtrarNotificacoes(event, 'todas')">
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroTipoTodas">Tipo</label>
                                    <select class="form-select" id="filtroTipoTodas">
                                        <option value="todos">Todos os tipos</option>
                                        <option value="ALTERACAO_FINANCEIRO">Alteração Financeira</option>
                                        <option value="NOVA_OBSERVACAO">Nova Observação</option>
                                        <option value="ALTERACAO_CADASTRO">Alteração Cadastral</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroStatusTodas">Status</label>
                                    <select class="form-select" id="filtroStatusTodas">
                                        <option value="todas">Todas</option>
                                        <option value="0">Não Lidas</option>
                                        <option value="1">Lidas</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group" style="flex: 2;">
                                    <label class="form-label" for="filtroBuscaTodas">Buscar</label>
                                    <input type="text" class="form-control" id="filtroBuscaTodas" 
                                           placeholder="Título, associado ou mensagem...">
                                </div>
                                
                                <div style="min-width: auto;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                        Filtrar
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros('todas')">
                                        <i class="fas fa-eraser"></i>
                                        Limpar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tabela de Todas as Notificações -->
                        <div class="notificacoes-container">
                            <div class="notificacoes-header">
                                <h3>
                                    <i class="fas fa-list text-corporate-primary"></i>
                                    Todas as Notificações
                                    <small id="modoPaginacaoTodas" style="font-size: 11px; opacity: 0.7; margin-left: 10px;"></small>
                                </h3>
                                <button class="btn btn-secondary btn-sm" onclick="atualizarNotificacoes('todas')">
                                    <i class="fas fa-sync"></i>
                                    Atualizar
                                </button>
                            </div>
                            
                            <div class="table-responsive" style="position: relative;">
                                <table class="table table-notificacoes" id="tabelaTodas">
                                    <thead>
                                        <tr>
                                            <th>Notificação</th>
                                            <th>Tipo</th>
                                            <th>Status</th>
                                            <th>Prioridade</th>
                                            <th>Associado</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpoTabelaTodas">
                                        <!-- Dados carregados dinamicamente via JavaScript -->
                                    </tbody>
                                </table>
                                
                                <div id="loadingTodas" class="loading-overlay" style="display: none;">
                                    <div class="loading-spinner mb-3"></div>
                                    <p class="text-muted">Carregando notificações...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Sistema de Paginação -->
                        <div class="pagination-container" id="paginationTodas" style="display: none;">
                            <div class="pagination-top">
                                <div class="pagination-info">
                                    <div class="registros-info">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="registrosInfoTodas">Carregando...</span>
                                    </div>
                                    <div class="registros-por-pagina">
                                        <label for="registrosPorPaginaTodas">Registros por página:</label>
                                        <select id="registrosPorPaginaTodas" onchange="alterarRegistrosPorPagina('todas')">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-controls">
                                    <ul class="pagination-nav" id="paginationNavTodas"></ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Aba: Notificações Lidas -->
                    <div class="tab-pane fade" id="lidas" role="tabpanel">
                        <!-- Sistema de Filtros -->
                        <div class="filtros-container">
                            <div class="filtros-header">
                                <h5 class="filtros-title">
                                    <i class="fas fa-filter text-corporate-primary"></i>
                                    Filtros - Notificações Lidas
                                </h5>
                            </div>
                            
                            <form class="filtros-form" onsubmit="filtrarNotificacoes(event, 'lidas')">
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroTipoLidas">Tipo</label>
                                    <select class="form-select" id="filtroTipoLidas">
                                        <option value="todos">Todos os tipos</option>
                                        <option value="ALTERACAO_FINANCEIRO">Alteração Financeira</option>
                                        <option value="NOVA_OBSERVACAO">Nova Observação</option>
                                        <option value="ALTERACAO_CADASTRO">Alteração Cadastral</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group">
                                    <label class="form-label" for="filtroDataLidas">Período</label>
                                    <select class="form-select" id="filtroDataLidas">
                                        <option value="todas">Todas as datas</option>
                                        <option value="hoje">Hoje</option>
                                        <option value="ontem">Ontem</option>
                                        <option value="semana">Esta semana</option>
                                        <option value="mes">Este mês</option>
                                    </select>
                                </div>
                                
                                <div class="filtro-group" style="flex: 2;">
                                    <label class="form-label" for="filtroBuscaLidas">Buscar</label>
                                    <input type="text" class="form-control" id="filtroBuscaLidas" 
                                           placeholder="Título, associado ou mensagem...">
                                </div>
                                
                                <div style="min-width: auto;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                        Filtrar
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros('lidas')">
                                        <i class="fas fa-eraser"></i>
                                        Limpar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tabela de Notificações Lidas -->
                        <div class="notificacoes-container">
                            <div class="notificacoes-header">
                                <h3>
                                    <i class="fas fa-check-circle text-corporate-success"></i>
                                    Notificações Lidas
                                    <small id="modoPaginacaoLidas" style="font-size: 11px; opacity: 0.7; margin-left: 10px;"></small>
                                </h3>
                                <button class="btn btn-secondary btn-sm" onclick="atualizarNotificacoes('lidas')">
                                    <i class="fas fa-sync"></i>
                                    Atualizar
                                </button>
                            </div>
                            
                            <div class="table-responsive" style="position: relative;">
                                <table class="table table-notificacoes" id="tabelaLidas">
                                    <thead>
                                        <tr>
                                            <th>Notificação</th>
                                            <th>Tipo</th>
                                            <th>Prioridade</th>
                                            <th>Associado</th>
                                            <th>Data Criação</th>
                                            <th>Data Leitura</th>
                                        </tr>
                                    </thead>
                                    <tbody id="corpoTabelaLidas">
                                        <!-- Dados carregados dinamicamente via JavaScript -->
                                    </tbody>
                                </table>
                                
                                <div id="loadingLidas" class="loading-overlay" style="display: none;">
                                    <div class="loading-spinner mb-3"></div>
                                    <p class="text-muted">Carregando notificações...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Sistema de Paginação -->
                        <div class="pagination-container" id="paginationLidas" style="display: none;">
                            <div class="pagination-top">
                                <div class="pagination-info">
                                    <div class="registros-info">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="registrosInfoLidas">Carregando...</span>
                                    </div>
                                    <div class="registros-por-pagina">
                                        <label for="registrosPorPaginaLidas">Registros por página:</label>
                                        <select id="registrosPorPaginaLidas" onchange="alterarRegistrosPorPagina('lidas')">
                                            <option value="10" selected>10</option>
                                            <option value="25">25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="pagination-controls">
                                    <ul class="pagination-nav" id="paginationNavLidas"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts Externos -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Componente de Cabeçalho -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        /**
         * Sistema de Notificações Corporativas
         * JavaScript Principal
         * 
         * @author Senior JavaScript Developer
         * @version 2.0.0
         */

        // ===== CLASSE DE SISTEMA DE NOTIFICAÇÕES =====
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
                    info: 'info-circle',
                    danger: 'exclamation-triangle'
                };
                return icons[type] || 'info-circle';
            }
        }

        // ===== CLASSE DE GERENCIAMENTO DE PAGINAÇÃO =====
        class MultiPaginationManager {
            constructor() {
                this.paginacoes = {
                    'nao-lidas': {
                        paginaAtual: 1,
                        registrosPorPagina: 10,
                        totalRegistros: 0,
                        totalPaginas: 0
                    },
                    'todas': {
                        paginaAtual: 1,
                        registrosPorPagina: 10,
                        totalRegistros: 0,
                        totalPaginas: 0
                    },
                    'lidas': {
                        paginaAtual: 1,
                        registrosPorPagina: 10,
                        totalRegistros: 0,
                        totalPaginas: 0
                    }
                };
            }

            // Conversão de identificadores
            getIdSuffix(aba) {
                const mapeamento = {
                    'nao-lidas': 'NaoLidas',
                    'todas': 'Todas', 
                    'lidas': 'Lidas'
                };
                return mapeamento[aba] || 'Todas';
            }

            // Atualização de paginação
            atualizarPaginacao(aba, dadosPaginacao) {
                const pag = this.paginacoes[aba];
                pag.paginaAtual = dadosPaginacao.pagina_atual;
                pag.registrosPorPagina = dadosPaginacao.registros_por_pagina;
                pag.totalRegistros = dadosPaginacao.total_registros;
                pag.totalPaginas = dadosPaginacao.total_paginas;

                this.atualizarElementos(aba);
                this.criarNavegacao(aba);
                
                const paginationElement = document.getElementById(`pagination${this.getIdSuffix(aba)}`);
                if (paginationElement) {
                    paginationElement.style.display = pag.totalRegistros > 0 ? 'block' : 'none';
                }
            }

            // Atualização de elementos informativos
            atualizarElementos(aba) {
                const pag = this.paginacoes[aba];
                const suffix = this.getIdSuffix(aba);
                
                const registrosInfoElement = document.getElementById(`registrosInfo${suffix}`);
                if (registrosInfoElement) {
                    registrosInfoElement.textContent = 
                        `${pag.totalRegistros} ${pag.totalRegistros === 1 ? 'notificação encontrada' : 'notificações encontradas'}`;
                }
                
                const registrosPorPaginaElement = document.getElementById(`registrosPorPagina${suffix}`);
                if (registrosPorPaginaElement) {
                    registrosPorPaginaElement.value = pag.registrosPorPagina;
                }
            }

            // Criação de navegação
            criarNavegacao(aba) {
                const pag = this.paginacoes[aba];
                const suffix = this.getIdSuffix(aba);
                const nav = document.getElementById(`paginationNav${suffix}`);
                
                if (!nav) return;
                
                nav.innerHTML = '';

                if (pag.totalPaginas <= 1) return;

                // Botão Primeira Página
                this.adicionarBotao(nav, '«', 1, pag.paginaAtual === 1, 'Primeira página', aba);
                
                // Botão Página Anterior
                this.adicionarBotao(nav, '‹', pag.paginaAtual - 1, pag.paginaAtual === 1, 'Página anterior', aba);

                // Páginas numéricas
                const inicioRange = Math.max(1, pag.paginaAtual - 2);
                const fimRange = Math.min(pag.totalPaginas, pag.paginaAtual + 2);

                for (let i = inicioRange; i <= fimRange; i++) {
                    this.adicionarBotao(nav, i, i, false, '', aba, i === pag.paginaAtual);
                }

                // Botão Próxima Página
                this.adicionarBotao(nav, '›', pag.paginaAtual + 1, pag.paginaAtual === pag.totalPaginas, 'Próxima página', aba);
                
                // Botão Última Página
                this.adicionarBotao(nav, '»', pag.totalPaginas, pag.paginaAtual === pag.totalPaginas, 'Última página', aba);
            }

            // Adição de botão de navegação
            adicionarBotao(nav, texto, pagina, disabled = false, titulo = '', aba, ativo = false) {
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
                        this.irParaPagina(aba, pagina);
                    };
                }

                li.appendChild(a);
                nav.appendChild(li);
            }

            // Navegação para página específica
            irParaPagina(aba, pagina) {
                const pag = this.paginacoes[aba];
                if (pagina >= 1 && pagina <= pag.totalPaginas && pagina !== pag.paginaAtual) {
                    pag.paginaAtual = pagina;
                    carregarNotificacoes(aba);
                }
            }

            // Alteração de registros por página
            alterarRegistrosPorPagina(aba, novoValor) {
                const pag = this.paginacoes[aba];
                pag.registrosPorPagina = novoValor;
                pag.paginaAtual = 1;
                carregarNotificacoes(aba);
            }
        }

        // ===== VARIÁVEIS GLOBAIS DO SISTEMA =====
        const notifications = new NotificationSystem();
        const pagination = new MultiPaginationManager();
        let notificacoesAtual = {
            'nao-lidas': [],
            'todas': [],
            'lidas': []
        };

        // Configurações de permissão
        const temPermissao = <?php echo json_encode($temPermissaoControle); ?>;
        const isFinanceiro = <?php echo json_encode($isFinanceiro); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const isDiretoria = <?php echo json_encode($isDiretoria); ?>;

        // Estatísticas iniciais do sistema
        const estatisticasIniciais = {
            total: <?php echo $totalNotificacoes; ?>,
            naoLidas: <?php echo $notificacaoNaoLidas; ?>,
            lidas: <?php echo $notificacoesLidas; ?>,
            hoje: <?php echo $notificacoesHoje; ?>
        };

        // ===== INICIALIZAÇÃO DO SISTEMA =====
        document.addEventListener('DOMContentLoaded', function() {
            // Aguardar carregamento completo do DOM
            setTimeout(() => {
                AOS.init({ duration: 800, once: true });

                console.log('=== DEBUG CENTRAL NOTIFICAÇÕES CORPORATIVA ===');
                console.log('Permissão:', temPermissao);
                console.log('Estatísticas:', estatisticasIniciais);

                if (!temPermissao) {
                    console.log('❌ Usuário sem permissão de acesso');
                    return;
                }

                configurarEventos();
                carregarNotificacoes('nao-lidas');

                notifications.show(`Central de notificações inicializada. ${estatisticasIniciais.naoLidas} não lidas.`, 'success', 3000);
            }, 100);
        });

        // ===== FUNÇÕES DE CARREGAMENTO DE DADOS =====

        // Carregamento principal de notificações
        async function carregarNotificacoes(aba) {
            console.log(`🔄 Carregando notificações - ${aba}`);
            
            const suffix = pagination.getIdSuffix(aba);
            const loadingId = `loading${suffix}`;
            const corpoTabelaId = `corpoTabela${suffix}`;
            const modoId = `modoPaginacao${suffix}`;
            
            const loadingOverlay = document.getElementById(loadingId);
            const corpoTabela = document.getElementById(corpoTabelaId);
            const modoElement = document.getElementById(modoId);
            
            console.log(`🔍 Verificando elementos DOM:`, {
                loadingId,
                corpoTabelaId,
                modoId,
                loadingOverlay: !!loadingOverlay,
                corpoTabela: !!corpoTabela,
                modoElement: !!modoElement
            });
            
            // Verificação de elementos DOM
            if (!loadingOverlay || !corpoTabela) {
                console.error(`❌ Elementos DOM não encontrados para aba ${aba}:`, {
                    loadingOverlay: !!loadingOverlay,
                    corpoTabela: !!corpoTabela
                });
                return;
            }
            
            loadingOverlay.style.display = 'flex';
            
            try {
                const pag = pagination.paginacoes[aba];
                
                // Busca dados reais via API
                console.log('🔄 Buscando dados reais do banco de dados...');
                
                const acoesParaTestar = ['listar', 'buscar', 'consultar', 'obter', 'get'];
                let apiSuccess = false;
                let result = null;
                
                for (const acao of acoesParaTestar) {
                    try {
                        const params = new URLSearchParams({
                            acao: acao,
                            tipo: getFiltroTipo(aba) || 'todos',
                            prioridade: getFiltroPrioridade(aba) || 'todas',
                            status: getFiltroStatus(aba) || 'todas',
                            busca: getFiltroBusca(aba) || '',
                            pagina: pag.paginaAtual,
                            registros_por_pagina: pag.registrosPorPagina
                        });
                        
                        console.log(`🔄 Testando endpoint "${acao}" - ${aba}:`, Object.fromEntries(params));
                        
                        const response = await fetch(`../api/notificacoes.php?${params}`);
                        const testResult = await response.json();
                        
                        console.log(`📋 Resposta da API para "${acao}":`, testResult);
                        
                        if (testResult.status === 'success' && testResult.data && testResult.data.length > 0) {
                            result = testResult;
                            apiSuccess = true;
                            console.log(`✅ Endpoint "${acao}" retornou ${testResult.data.length} registros!`);
                            if (modoElement) {
                                modoElement.textContent = '(Dados Reais - Banco de Dados)';
                            }
                            break;
                        } else {
                            console.log(`⚠️ Endpoint "${acao}": sem dados disponíveis`);
                        }
                    } catch (e) {
                        console.log(`❌ Erro no endpoint "${acao}":`, e.message);
                        continue;
                    }
                }
                
                // Fallback para dados simulados se necessário
                if (!apiSuccess) {
                    console.log('⚠️ API indisponível, utilizando dados simulados');
                    result = gerarDadosSimulados(aba);
                    if (modoElement) {
                        modoElement.textContent = '(Modo de Demonstração)';
                    }
                }

                if (result && result.status === 'success') {
                    let notificacoes = result.data || [];
                    
                    // Filtro local para dados simulados
                    if (!apiSuccess) {
                        if (aba === 'nao-lidas') {
                            notificacoes = notificacoes.filter(n => n.lida == 0);
                            console.log(`🔍 Filtradas ${notificacoes.length} notificações não lidas`);
                        } else if (aba === 'lidas') {
                            notificacoes = notificacoes.filter(n => n.lida == 1);
                            console.log(`🔍 Filtradas ${notificacoes.length} notificações lidas`);
                        }
                    } else {
                        console.log(`📊 Recebidos ${notificacoes.length} registros da API`);
                    }
                    
                    // Paginação dos dados
                    let dadosPaginacao = result.paginacao;
                    
                    if (!dadosPaginacao && notificacoes.length >= 0) {
                        const totalRegistros = notificacoes.length;
                        const totalPaginas = Math.ceil(totalRegistros / pag.registrosPorPagina);
                        const inicio = (pag.paginaAtual - 1) * pag.registrosPorPagina;
                        const fim = inicio + pag.registrosPorPagina;
                        
                        notificacoesAtual[aba] = notificacoes.slice(inicio, fim);
                        
                        dadosPaginacao = {
                            pagina_atual: pag.paginaAtual,
                            registros_por_pagina: pag.registrosPorPagina,
                            total_registros: totalRegistros,
                            total_paginas: totalPaginas
                        };
                        
                        if (modoElement && apiSuccess) {
                            modoElement.textContent = '(Dados Reais + Paginação Local)';
                        }
                    } else if (dadosPaginacao) {
                        notificacoesAtual[aba] = notificacoes;
                        if (modoElement && apiSuccess) {
                            modoElement.textContent = '(Dados Reais + Paginação Servidor)';
                        }
                    } else {
                        notificacoesAtual[aba] = [];
                        dadosPaginacao = {
                            pagina_atual: 1,
                            registros_por_pagina: pag.registrosPorPagina,
                            total_registros: 0,
                            total_paginas: 0
                        };
                    }
                    
                    console.log(`📊 Status da paginação ${aba}:`, dadosPaginacao);
                    console.log(`📝 Exibindo ${notificacoesAtual[aba].length} notificações`);
                    
                    pagination.atualizarPaginacao(aba, dadosPaginacao);
                    exibirNotificacoes(aba, notificacoesAtual[aba]);
                    
                    if (dadosPaginacao.total_registros > 0) {
                        const tipoFonte = apiSuccess ? 'do banco de dados' : 'de demonstração';
                        notifications.show(
                            `${dadosPaginacao.total_registros} notificações ${tipoFonte} carregadas`, 
                            apiSuccess ? 'success' : 'info', 
                            3000
                        );
                    } else {
                        notifications.show(`Nenhuma notificação encontrada para "${aba}"`, 'warning', 3000);
                    }
                } else {
                    throw new Error('Falha ao carregar dados');
                }

            } catch (error) {
                console.error(`❌ Erro ao carregar notificações - ${aba}:`, error);
                corpoTabela.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-corporate-danger me-2"></i>
                            Erro ao carregar notificações: ${error.message}
                        </td>
                    </tr>
                `;
                
                const dadosVazios = {
                    pagina_atual: 1,
                    registros_por_pagina: pagination.paginacoes[aba].registrosPorPagina,
                    total_registros: 0,
                    total_paginas: 0
                };
                pagination.atualizarPaginacao(aba, dadosVazios);
                
                notifications.show(`Erro ao carregar notificações - ${aba}`, 'error');
            } finally {
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
            }
        }

        // Geração de dados simulados para demonstração
        function gerarDadosSimulados(aba) {
            console.log(`🔧 Gerando dados de demonstração para: ${aba}`);
            
            const notificacoes = [];
            
            // Estatísticas do banco de dados
            const totalNaoLidas = estatisticasIniciais.naoLidas;
            const totalLidas = estatisticasIniciais.lidas;
            
            console.log(`📊 Base de dados: Não Lidas: ${totalNaoLidas}, Lidas: ${totalLidas}`);
            
            // Geração de notificações não lidas
            if (aba === 'nao-lidas' || aba === 'todas') {
                for (let i = 1; i <= totalNaoLidas; i++) {
                    notificacoes.push({
                        id: 1000 + i,
                        titulo: `Alteração Financeira Detectada`,
                        mensagem: `Dados financeiros do associado foram modificados. Campo alterado: situacaoFinanceira`,
                        tipo: 'ALTERACAO_FINANCEIRO',
                        prioridade: i <= 2 ? 'URGENTE' : (i <= 4 ? 'ALTA' : 'MEDIA'),
                        lida: 0,
                        associado_nome: `Associado #${i}`,
                        associado_cpf: `895.177.920-34`,
                        data_criacao: new Date(Date.now() - i * 3600000).toISOString(),
                        tempo_atras: `há ${i} hora${i > 1 ? 's' : ''}`
                    });
                }
                console.log(`✅ Geradas ${totalNaoLidas} notificações não lidas`);
            }
            
            // Geração de notificações lidas
            if (aba === 'lidas' || aba === 'todas') {
                for (let i = 1; i <= totalLidas; i++) {
                    notificacoes.push({
                        id: 2000 + i,
                        titulo: `Cadastro Atualizado`,
                        mensagem: `Informações cadastrais foram atualizadas no sistema. Campo: situacao`,
                        tipo: i % 3 === 0 ? 'ALTERACAO_CADASTRO' : (i % 2 === 0 ? 'NOVA_OBSERVACAO' : 'ALTERACAO_FINANCEIRO'),
                        prioridade: i <= 3 ? 'ALTA' : (i <= 6 ? 'MEDIA' : 'BAIXA'),
                        lida: 1,
                        associado_nome: `Associado #${100 + i}`,
                        associado_cpf: `895.177.920-34`,
                        data_criacao: new Date(Date.now() - (i + 10) * 7200000).toISOString(),
                        data_leitura: new Date(Date.now() - (i + 5) * 3600000).toISOString(),
                        tempo_atras: `há ${(i + 10) * 2} hora${(i + 10) * 2 > 1 ? 's' : ''}`
                    });
                }
                console.log(`✅ Geradas ${totalLidas} notificações lidas`);
            }
            
            console.log(`📝 Total de notificações geradas: ${notificacoes.length}`);
            
            return {
                status: 'success',
                data: notificacoes,
                message: `Dados de demonstração carregados para ${aba}`
            };
        }

        // Exibição de notificações na interface
        function exibirNotificacoes(aba, notificacoes) {
            const suffix = pagination.getIdSuffix(aba);
            const corpoTabelaId = `corpoTabela${suffix}`;
            const corpoTabela = document.getElementById(corpoTabelaId);
            
            if (!corpoTabela) {
                console.error(`❌ Elemento de tabela não encontrado: ${corpoTabelaId}`);
                return;
            }
            
            if (notificacoes.length === 0) {
                const colspan = aba === 'lidas' ? 6 : (aba === 'todas' ? 7 : 6);
                corpoTabela.innerHTML = `
                    <tr>
                        <td colspan="${colspan}" class="text-center py-4">
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h4 class="empty-title">Nenhuma notificação encontrada</h4>
                                <p class="empty-text">Não há notificações para exibir no momento.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            const linhas = notificacoes.map(notif => {
                const badgeTipo = getBadgeTipo(notif.tipo);
                const badgePrioridade = getBadgePrioridade(notif.prioridade);
                const dataFormatada = formatarDataHora(notif.data_criacao);
                
                let dataLeituraFormatada = '';
                if (notif.data_leitura) {
                    dataLeituraFormatada = formatarDataHora(notif.data_leitura);
                } else if (notif.lida == 1) {
                    dataLeituraFormatada = 'Lida';
                }
                
                const classeLinha = notif.lida == 0 ? 'notif-nao-lida' : 'notif-lida';
                
                let acoes = '';
                if (aba === 'nao-lidas' || (aba === 'todas' && notif.lida == 0)) {
                    acoes = `
                        <button class="btn-acao btn-marcar-lida" onclick="marcarComoLida(${notif.id}, '${aba}')">
                            <i class="fas fa-check"></i>
                            Marcar Lida
                        </button>
                    `;
                }
                
                let colunas = `
                    <td>
                        <div class="notif-titulo">${notif.titulo || 'Sem título'}</div>
                        <div class="notif-mensagem">${truncarTexto(notif.mensagem || '', 100)}</div>
                        <small class="notif-meta">ID: ${notif.id}</small>
                    </td>
                    <td>
                        <span class="badge-tipo ${badgeTipo.classe}">${badgeTipo.texto}</span>
                    </td>
                `;
                
                if (aba === 'todas') {
                    colunas += `
                        <td>
                            <span class="badge-status ${notif.lida == 1 ? 'status-lida' : 'status-nao-lida'}">
                                ${notif.lida == 1 ? 'Lida' : 'Não Lida'}
                            </span>
                        </td>
                    `;
                }
                
                colunas += `
                    <td>
                        <span class="badge-prioridade ${badgePrioridade.classe}">${badgePrioridade.texto}</span>
                    </td>
                    <td>
                        <div class="notif-associado">${notif.associado_nome || 'Sistema'}</div>
                        ${notif.associado_cpf ? `<small class="notif-meta">CPF: ${notif.associado_cpf}</small>` : ''}
                    </td>
                    <td>
                        <div class="notif-data">${dataFormatada}</div>
                        <small class="notif-meta">${notif.tempo_atras || 'Há pouco'}</small>
                    </td>
                `;
                
                if (aba === 'lidas') {
                    colunas += `
                        <td>
                            <div class="notif-data">${dataLeituraFormatada}</div>
                        </td>
                    `;
                } else {
                    colunas += `
                        <td>
                            ${acoes}
                        </td>
                    `;
                }
                
                return `<tr class="${classeLinha}">${colunas}</tr>`;
            }).join('');
            
            corpoTabela.innerHTML = linhas;
        }

        // ===== FUNÇÕES DE AÇÃO DO SISTEMA =====

        // Marcar notificação individual como lida
        async function marcarComoLida(notificacaoId, aba) {
            try {
                console.log(`🔄 Marcando notificação ${notificacaoId} como lida...`);
                
                const formData = new FormData();
                formData.append('acao', 'marcar_lida');
                formData.append('notificacao_id', notificacaoId);
                
                const response = await fetch('../api/notificacoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('📋 Resposta da API:', data);
                
                if (data.status === 'success') {
                    notifications.show('✅ Notificação marcada como lida!', 'success');
                    
                    // Atualização de estatísticas
                    atualizarEstatisticas();
                    
                    // Recarregamento de abas
                    if (aba === 'nao-lidas') {
                        carregarNotificacoes('nao-lidas');
                    }
                    carregarNotificacoes('todas');
                    carregarNotificacoes('lidas');
                } else {
                    throw new Error(data.message || 'Erro desconhecido');
                }
                
            } catch (error) {
                console.error('❌ Erro ao marcar como lida:', error);
                notifications.show('❌ Erro ao marcar notificação: ' + error.message, 'error');
            }
        }

        // Marcar todas as notificações como lidas
        async function marcarTodasLidas() {
            if (!confirm('Deseja marcar todas as notificações como lidas?')) {
                return;
            }
            
            try {
                console.log('🔄 Marcando todas as notificações como lidas...');
                
                const formData = new FormData();
                formData.append('acao', 'marcar_todas_lidas');
                
                const response = await fetch('../api/notificacoes.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                console.log('📋 Resposta da API:', data);
                
                if (data.status === 'success') {
                    notifications.show(`✅ ${data.total_marcadas || 'Todas as'} notificações marcadas como lidas!`, 'success');
                    
                    // Atualização de estatísticas
                    atualizarEstatisticas();
                    
                    // Recarregamento de todas as abas
                    carregarNotificacoes('nao-lidas');
                    carregarNotificacoes('todas');
                    carregarNotificacoes('lidas');
                } else {
                    throw new Error(data.message || 'Erro desconhecido');
                }
                
            } catch (error) {
                console.error('❌ Erro ao marcar todas:', error);
                notifications.show('❌ Erro ao marcar notificações: ' + error.message, 'error');
            }
        }

        // Atualização de estatísticas do sistema
        async function atualizarEstatisticas() {
            try {
                console.log('🔄 Atualizando estatísticas...');
                
                const response = await fetch(`../api/notificacoes.php?acao=contar`);
                const data = await response.json();
                
                console.log('📋 Estatísticas atualizadas:', data);
                
                if (data.status === 'success') {
                    const naoLidasElement = document.querySelector('.stat-number.nao-lidas');
                    const badgeNaoLidas = document.getElementById('badge-nao-lidas');
                    
                    if (naoLidasElement) naoLidasElement.textContent = data.total;
                    if (badgeNaoLidas) badgeNaoLidas.textContent = data.total;
                    
                    console.log(`✅ Estatísticas atualizadas: ${data.total} não lidas`);
                } else {
                    console.log('⚠️ Erro na atualização de estatísticas:', data.message);
                }
            } catch (error) {
                console.error('❌ Erro ao atualizar estatísticas:', error);
            }
        }

        // ===== FUNÇÕES DE FILTRO DO SISTEMA =====

        // Aplicação de filtros
        function filtrarNotificacoes(event, aba) {
            event.preventDefault();
            pagination.paginacoes[aba].paginaAtual = 1;
            carregarNotificacoes(aba);
        }

        // Limpeza de filtros
        function limparFiltros(aba) {
            const suffix = pagination.getIdSuffix(aba);
            
            // Reset de campos por aba
            if (aba === 'nao-lidas') {
                const tipoElement = document.getElementById(`filtroTipo${suffix}`);
                const prioridadeElement = document.getElementById(`filtroPrioridade${suffix}`);
                const buscaElement = document.getElementById(`filtroBusca${suffix}`);
                
                if (tipoElement) tipoElement.value = 'todos';
                if (prioridadeElement) prioridadeElement.value = 'todas';
                if (buscaElement) buscaElement.value = '';
            } else if (aba === 'todas') {
                const tipoElement = document.getElementById(`filtroTipo${suffix}`);
                const statusElement = document.getElementById('filtroStatusTodas');
                const buscaElement = document.getElementById(`filtroBusca${suffix}`);
                
                if (tipoElement) tipoElement.value = 'todos';
                if (statusElement) statusElement.value = 'todas';
                if (buscaElement) buscaElement.value = '';
            } else if (aba === 'lidas') {
                const tipoElement = document.getElementById(`filtroTipo${suffix}`);
                const dataElement = document.getElementById('filtroDataLidas');
                const buscaElement = document.getElementById(`filtroBusca${suffix}`);
                
                if (tipoElement) tipoElement.value = 'todos';
                if (dataElement) dataElement.value = 'todas';
                if (buscaElement) buscaElement.value = '';
            }
            
            pagination.paginacoes[aba].paginaAtual = 1;
            carregarNotificacoes(aba);
        }

        // Atualização manual
        function atualizarNotificacoes(aba) {
            carregarNotificacoes(aba);
            notifications.show(`Notificações atualizadas - ${aba}`, 'info', 2000);
        }

        // Alteração de registros por página
        function alterarRegistrosPorPagina(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`registrosPorPagina${suffix}`);
            if (!elemento) return;
            
            const novoValor = parseInt(elemento.value);
            pagination.alterarRegistrosPorPagina(aba, novoValor);
            notifications.show(`Exibindo ${novoValor} registros por página`, 'info', 2000);
        }

        // ===== FUNÇÕES AUXILIARES DO SISTEMA =====

        // Configuração de eventos
        function configurarEventos() {
            // Listeners para mudança de abas
            const tabButtons = document.querySelectorAll('#notificationTabs button[data-bs-toggle="tab"]');
            tabButtons.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const targetAba = e.target.getAttribute('data-bs-target').replace('#', '');
                    let aba = targetAba;
                    
                    // Mapeamento de abas
                    if (targetAba === 'todas') aba = 'todas';
                    else if (targetAba === 'lidas') aba = 'lidas';
                    else aba = 'nao-lidas';
                    
                    console.log(`🔄 Navegação para aba: ${aba}`);
                    
                    // Carregamento de dados da aba
                    setTimeout(() => {
                        carregarNotificacoes(aba);
                    }, 100);
                });
            });
        }

        // Obtenção de filtros
        function getFiltroTipo(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`filtroTipo${suffix}`);
            return elemento ? elemento.value : null;
        }

        function getFiltroPrioridade(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`filtroPrioridade${suffix}`);
            return elemento ? elemento.value : null;
        }

        function getFiltroStatus(aba) {
            if (aba === 'nao-lidas') return '0';
            if (aba === 'lidas') return '1';
            const elemento = document.getElementById('filtroStatusTodas');
            return elemento ? elemento.value : null;
        }

        function getFiltroBusca(aba) {
            const suffix = pagination.getIdSuffix(aba);
            const elemento = document.getElementById(`filtroBusca${suffix}`);
            return elemento ? elemento.value : null;
        }

        // Definição de badges
        function getBadgeTipo(tipo) {
            const tipos = {
                'ALTERACAO_FINANCEIRO': { classe: 'tipo-financeiro', texto: 'Financeiro' },
                'NOVA_OBSERVACAO': { classe: 'tipo-observacao', texto: 'Observação' },
                'ALTERACAO_CADASTRO': { classe: 'tipo-cadastro', texto: 'Cadastral' }
            };
            return tipos[tipo] || { classe: 'tipo-financeiro', texto: 'Sistema' };
        }

        function getBadgePrioridade(prioridade) {
            const prioridades = {
                'URGENTE': { classe: 'prioridade-urgente', texto: 'Urgente' },
                'ALTA': { classe: 'prioridade-alta', texto: 'Alta' },
                'MEDIA': { classe: 'prioridade-media', texto: 'Média' },
                'BAIXA': { classe: 'prioridade-baixa', texto: 'Baixa' }
            };
            return prioridades[prioridade] || { classe: 'prioridade-media', texto: 'Média' };
        }

        // Formatação de dados
        function formatarDataHora(dataStr) {
            if (!dataStr) return '';
            try {
                const data = new Date(dataStr);
                return data.toLocaleString('pt-BR');
            } catch (e) {
                return dataStr;
            }
        }

        function truncarTexto(texto, limite) {
            if (!texto) return '';
            return texto.length > limite ? texto.substring(0, limite) + '...' : texto;
        }

        // Log final do sistema
        console.log('✅ Sistema de Notificações Corporativas Carregado');
        console.log(`🏢 Nível de acesso: ${isFinanceiro ? 'Financeiro' : isPresidencia ? 'Presidência' : isDiretoria ? 'Diretoria' : 'Desconhecido'}`);
        console.log(`📊 Estatísticas: Total: ${estatisticasIniciais.total}, Não Lidas: ${estatisticasIniciais.naoLidas}, Lidas: ${estatisticasIniciais.lidas}`);
        console.log('🔧 Sistema corporativo completo com design profissional');
        console.log('📡 API Endpoints disponíveis: buscar, contar, marcar_lida, marcar_todas_lidas');
        console.log('✨ Interface corporativa com cores sóbrias e formais');
    </script>

</body>

</html>