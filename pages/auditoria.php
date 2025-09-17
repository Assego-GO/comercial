<?php
/**
 * Página de Auditoria - Sistema ASSEGO
 * pages/auditoria.php
 * Versão 2.0 - Implementação Completa
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Auditoria.php';
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
$page_title = 'Auditoria - ASSEGO';

// Verificar se o usuário tem permissão para acessar a auditoria
$temPermissaoAuditoria = false;
$motivoNegacao = '';
$isPresidencia = false;
$isDiretor = false;
$departamentoUsuario = null;

// Debug completo ANTES das verificações
error_log("=== DEBUG DETALHADO PERMISSÕES AUDITORIA ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Array completo do usuário: " . print_r($usuarioLogado, true));
error_log("Departamento ID (valor): " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("Departamento ID (tipo): " . gettype($usuarioLogado['departamento_id'] ?? null));
error_log("É Diretor (método): " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// NOVA LÓGICA DE PERMISSÕES
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $cargoUsuario = $usuarioLogado['cargo'] ?? '';
    $departamentoUsuario = $deptId;
    
    // Debug dos testes de comparação
    error_log("Testes de comparação:");
    error_log("  deptId === '1': " . ($deptId === '1' ? 'true' : 'false'));
    error_log("  deptId === 1: " . ($deptId === 1 ? 'true' : 'false'));
    error_log("  deptId == 1: " . ($deptId == 1 ? 'true' : 'false'));
    error_log("  Cargo: " . $cargoUsuario);
    
    // Sistema flexível de permissões
    if ($deptId == 1) {
        // PRESIDÊNCIA - vê tudo
        $temPermissaoAuditoria = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Departamento da Presidência (ID = 1) - VÊ TUDO");
    } elseif (in_array($cargoUsuario, ['Presidente', 'Vice-Presidente'])) {
        // Apenas Presidente e Vice-Presidente veem todos (mesmo fora da presidência)
        $temPermissaoAuditoria = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: {$cargoUsuario} - VÊ TUDO");
    } elseif (in_array($cargoUsuario, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador'])) {
        // Diretores veem apenas seu departamento
        $temPermissaoAuditoria = true;
        $isDiretor = true;
        error_log("✅ Permissão concedida: {$cargoUsuario} - VÊ APENAS DEPARTAMENTO " . $deptId);
    } else {
        $motivoNegacao = 'Acesso restrito ao departamento da Presidência, Presidente/Vice-Presidente ou cargos de gestão.';
        error_log("❌ Acesso negado. Departamento: '$deptId', Cargo: '$cargoUsuario'. Necessário: Presidência (ID = 1) OU Presidente/Vice-Presidente OU Diretor/Gerente/Supervisor/Coordenador");
    }
} else {
    $motivoNegacao = 'Departamento não identificado. Acesso restrito ao departamento da Presidência ou cargos de gestão.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Log final do resultado
if (!$temPermissaoAuditoria) {
    error_log("❌ ACESSO NEGADO: " . $motivoNegacao);
} else {
    if ($isPresidencia) {
        $motivo = "Usuário com Acesso Total - " . ($usuarioLogado['departamento_id'] == 1 ? 'Presidência' : $usuarioLogado['cargo']);
        error_log("✅ ACESSO PERMITIDO - " . $motivo);
    } else {
        $motivo = "Usuário é {$cargoUsuario} - Acesso Departamental (Dept: " . $departamentoUsuario . ")";
        error_log("✅ ACESSO PERMITIDO - " . $motivo);
    }
}

// Busca estatísticas de auditoria (apenas se tem permissão)
if ($temPermissaoAuditoria) {
    try {
        $auditoria = new Auditoria();
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Adicionar filtro de departamento se não for presidência
        $whereDepartamento = '';
        $paramsDepartamento = [];
        
        if (!$isPresidencia && $isDiretor && $departamentoUsuario) {
            $whereDepartamento = " AND (
                f.departamento_id = :departamento_usuario 
                OR a.funcionario_id IN (
                    SELECT id FROM Funcionarios WHERE departamento_id = :departamento_usuario2
                )
            )";
            $paramsDepartamento = [
                ':departamento_usuario' => $departamentoUsuario,
                ':departamento_usuario2' => $departamentoUsuario
            ];
        }
        
        // Total de registros
        $sql = "SELECT COUNT(*) as total FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id 
                WHERE 1=1" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $totalRegistros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ações hoje
        $sql = "SELECT COUNT(*) as hoje FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id 
                WHERE DATE(a.data_hora) = CURDATE()" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $acoesHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];
        
        // Usuários ativos (últimas 24h)
        $sql = "SELECT COUNT(DISTINCT a.funcionario_id) as usuarios_ativos
                FROM Auditoria a
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                WHERE a.data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND a.funcionario_id IS NOT NULL" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $usuariosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['usuarios_ativos'];
        
        // Alertas (tentativas de login falharam, ações suspeitas)
        $sql = "SELECT COUNT(*) as alertas
                FROM Auditoria a 
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                WHERE a.acao IN ('LOGIN_FALHA', 'DELETE') 
                AND DATE(a.data_hora) = CURDATE()" . $whereDepartamento;
        $stmt = $db->prepare($sql);
        foreach ($paramsDepartamento as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->execute();
        $alertas = $stmt->fetch(PDO::FETCH_ASSOC)['alertas'];

    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas da auditoria: " . $e->getMessage());
        $totalRegistros = $acoesHoje = $usuariosAtivos = $alertas = 0;
    }
} else {
    $totalRegistros = $acoesHoje = $usuariosAtivos = $alertas = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'email' => $usuarioLogado['email'] ?? $_SESSION['funcionario_email'] ?? 'usuario@assego.com.br',
        'avatar' => $usuarioLogado['avatar'] ?? null,
        'departamento_id' => $usuarioLogado['departamento_id'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'auditoria',
    'notificationCount' => $alertas,
    'showSearch' => false
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

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/auditoriaModels.css">
    <link rel="stylesheet" href="estilizacao/auditoria.css">
    
    <!-- CSS Adicional para Sistema Completo -->
    <style>
        /* ===== CONFIGURAÇÕES AVANÇADAS ===== */
        .config-section {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.1);
        }

        .config-section h6 {
            color: white;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .config-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .config-item label {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
            font-weight: 500;
        }

        .config-item input,
        .config-item select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.95) !important;
            color: #333 !important;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .config-item input:focus,
        .config-item select:focus {
            border-color: #fff;
            background: #fff !important;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
            outline: none;
        }

        .config-item input::placeholder {
            color: rgba(0, 0, 0, 0.4);
        }

        .config-item select option {
            background: white;
            color: #333;
            padding: 8px;
        }

        .config-item select:hover {
            background: #fff !important;
            cursor: pointer;
        }

        /* Fix para garantir que os selects sejam visíveis */
        #configuracoesModal select,
        #configuracoesModal input[type="number"],
        #configuracoesModal input[type="text"] {
            background-color: rgba(255, 255, 255, 0.95) !important;
            color: #333 !important;
        }

        #configuracoesModal select option {
            background-color: white !important;
            color: #333 !important;
        }

        /* Estilo alternativo para selects no modal de configurações */
        .modal-body select.form-select,
        .modal-body input.form-control {
            background-color: white !important;
            color: #333 !important;
            border: 1px solid #dee2e6;
        }

        .modal-body select.form-select:focus,
        .modal-body input.form-control:focus {
            background-color: white !important;
            color: #333 !important;
            border-color: #2563eb;
            box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.25);
        }

        .config-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .config-toggle:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .config-toggle label {
            color: white;
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.3);
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #10b981;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Advanced Filters com azul */
        .advanced-filters {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.1);
        }

        .advanced-filters.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .advanced-filters input,
        .advanced-filters select {
            background: rgba(255, 255, 255, 0.95) !important;
            color: #333 !important;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .advanced-filters input:focus,
        .advanced-filters select:focus {
            background: #fff !important;
            border-color: #fff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        .advanced-filters label {
            color: white !important;
            font-weight: 500;
        }

        .config-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .config-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .config-toggle label {
            color: white;
            margin: 0;
            cursor: pointer;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.3);
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #4CAF50;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* Dark Mode */
        body.dark-theme {
            background: #1a1a2e;
            color: #eee;
        }

        body.dark-theme .content-area {
            background: #16213e;
        }

        body.dark-theme .stat-card {
            background: #0f3460;
            color: #eee;
        }

        body.dark-theme .audit-table {
            background: #0f3460;
            color: #eee;
        }

        body.dark-theme .modal-content {
            background: #16213e;
            color: #eee;
        }

        /* Compact View */
        body.compact-view .stat-card {
            padding: 15px;
        }

        body.compact-view .page-header {
            padding: 15px 0;
        }

        body.compact-view .audit-table td {
            padding: 8px;
            font-size: 0.875rem;
        }

        /* Performance Monitor */
        .performance-monitor {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: #4CAF50;
            padding: 10px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            z-index: 9999;
            display: none;
        }

        body.debug-mode .performance-monitor {
            display: block;
        }

        .performance-stat {
            margin: 2px 0;
        }

        /* Live Update Indicator */
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px;
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid #4CAF50;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #4CAF50;
        }

        .live-indicator.active::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .live-indicator.paused {
            background: rgba(255, 152, 0, 0.1);
            border-color: #FF9800;
            color: #FF9800;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }

        /* Export Options */
        .export-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .export-btn {
            padding: 8px 16px;
            border: 2px solid var(--primary);
            background: transparent;
            color: var(--primary);
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .export-btn i {
            margin-right: 6px;
        }

        /* Advanced Filters */
        .advanced-filters {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
        }

        .advanced-filters.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Cache Status */
        .cache-status {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            z-index: 1000;
            display: none;
        }

        .cache-status.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Department Badge */
        .dept-badge {
            display: inline-block;
            padding: 4px 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .dept-badge.presidencia {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        /* Notification Badge Animation */
        .notification-badge {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        /* Responsive Improvements */
        @media (max-width: 768px) {
            .config-grid {
                grid-template-columns: 1fr;
            }

            .export-options {
                flex-direction: column;
            }

            .export-btn {
                width: 100%;
            }

            .live-indicator {
                position: fixed;
                bottom: 10px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 100;
            }

            .cache-status {
                top: auto;
                bottom: 60px;
                right: 10px;
                left: 10px;
            }
        }

        /* Print Styles */
        @media print {
            .header-component,
            .quick-actions,
            .filter-bar,
            .section-actions,
            .btn-action,
            .pagination-wrapper {
                display: none !important;
            }

            .audit-table {
                font-size: 10pt;
            }

            .stat-card {
                break-inside: avoid;
            }
        }

        /* Accessibility Improvements */
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Focus Styles */
        button:focus,
        input:focus,
        select:focus,
        a:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            display: none;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* ===== CONFIGURAÇÕES MINIMALISTAS ===== */
.config-section {
    background: #ffffff;  /* Fundo branco simples */
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;  /* Borda cinza suave */
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);  /* Sombra muito sutil */
}

.config-section h6 {
    color: #374151;  /* Cinza escuro para títulos */
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.config-item {
    background: #fafafa;  /* Cinza muito claro */
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.config-item label {
    color: #6b7280;  /* Cinza médio */
    font-size: 0.875rem;
    margin-bottom: 8px;
    display: block;
    font-weight: 500;
}

.config-item input,
.config-item select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;  /* Borda cinza clara */
    background: #ffffff;
    color: #374151;
    border-radius: 4px;
    font-size: 0.875rem;
    transition: border-color 0.2s ease;
}

.config-item input:focus,
.config-item select:focus {
    border-color: #9ca3af;  /* Cinza um pouco mais escuro no foco */
    background: #ffffff;
    outline: none;
    box-shadow: 0 0 0 2px rgba(156, 163, 175, 0.1);  /* Sombra muito sutil */
}

/* Toggles minimalistas */
.config-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #fafafa;
    border-radius: 6px;
    margin-bottom: 8px;
    transition: background 0.2s ease;
    border: 1px solid #e5e7eb;
}

.config-toggle:hover {
    background: #f3f4f6;
}

.config-toggle label {
    color: #374151;
    margin: 0;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.875rem;
}

/* Switch minimalista */
.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;  /* Cinza claro desativado */
    transition: .3s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

input:checked + .slider {
    background-color: #374151;  /* Cinza escuro quando ativo */
}

input:checked + .slider:before {
    transform: translateX(20px);
}

/* Filtros avançados minimalistas */
.advanced-filters {
    background: #ffffff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
    display: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.advanced-filters.show {
    display: block;
    animation: fadeIn 0.2s ease;
}

.advanced-filters input,
.advanced-filters select {
    background: #ffffff !important;
    color: #374151 !important;
    border: 1px solid #d1d5db;
}

.advanced-filters input:focus,
.advanced-filters select:focus {
    background: #ffffff !important;
    border-color: #9ca3af;
    box-shadow: 0 0 0 2px rgba(156, 163, 175, 0.1);
}

.advanced-filters label {
    color: #6b7280 !important;
    font-weight: 500;
    font-size: 0.875rem;
}

/* Modal minimalista */
#configuracoesModal .modal-content {
    border: none;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

#configuracoesModal .modal-header {
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    padding: 20px 24px;
}

#configuracoesModal .modal-title {
    color: #111827;
    font-size: 1.125rem;
    font-weight: 600;
}

#configuracoesModal .modal-body {
    background: #fafafa;
    padding: 24px;
}

#configuracoesModal .modal-footer {
    background: #ffffff;
    border-top: 1px solid #e5e7eb;
    padding: 16px 24px;
}

/* Botões minimalistas no modal */
#configuracoesModal .btn {
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.875rem;
    padding: 8px 16px;
    transition: all 0.2s ease;
}

#configuracoesModal .btn-secondary {
    background: #ffffff;
    color: #6b7280;
    border: 1px solid #d1d5db;
}

#configuracoesModal .btn-secondary:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

#configuracoesModal .btn-success {
    background: #374151;
    border: 1px solid #374151;
    color: white;
}

#configuracoesModal .btn-success:hover {
    background: #1f2937;
    border-color: #1f2937;
}

#configuracoesModal .btn-warning {
    background: #ffffff;
    color: #92400e;
    border: 1px solid #fbbf24;
}

#configuracoesModal .btn-danger {
    background: #ffffff;
    color: #991b1b;
    border: 1px solid #f87171;
}

/* Info box minimalista */
.alert-info {
    background: #f0f9ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    border-radius: 6px;
}

/* Remover animações desnecessárias */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
    </style>
</head>

<body>
    <!-- Toast Container para Notificações -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;" id="toastContainer"></div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Performance Monitor -->
    <div class="performance-monitor" id="performanceMonitor">
        <div class="performance-stat">FPS: <span id="fps">60</span></div>
        <div class="performance-stat">Memory: <span id="memory">0</span> MB</div>
        <div class="performance-stat">Cache: <span id="cacheSize">0</span> items</div>
        <div class="performance-stat">Updates: <span id="updates">0</span></div>
    </div>

    <!-- Cache Status -->
    <div class="cache-status" id="cacheStatus">
        <i class="fas fa-database"></i> Cache: <span id="cacheInfo">0 items</span>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoAuditoria): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado à Área de Auditoria</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Como resolver:</h6>
                    <ol class="mb-0">
                        <li>Verifique se você está no departamento correto</li>
                        <li>Confirme se você tem o cargo adequado no sistema</li>
                        <li>Entre em contato com o administrador se necessário</li>
                    </ol>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Suas informações atuais:</h6>
                        <ul class="mb-0">
                            <li><strong>Nome:</strong> <?php echo htmlspecialchars($usuarioLogado['nome']); ?></li>
                            <li><strong>Cargo:</strong> <?php echo htmlspecialchars($usuarioLogado['cargo'] ?? 'N/A'); ?></li>
                            <li><strong>Departamento:</strong> <?php echo htmlspecialchars($usuarioLogado['departamento_id'] ?? 'N/A'); ?></li>
                            <li><strong>É Diretor:</strong> 
                                <span class="badge bg-<?php echo $auth->isDiretor() ? 'success' : 'danger'; ?>">
                                    <?php echo $auth->isDiretor() ? 'Sim' : 'Não'; ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Requisitos para acesso:</h6>
                        <ul class="mb-3">
                            <li>Ser diretor, gerente, supervisor ou coordenador <strong>OU</strong></li>
                            <li>Estar no departamento da Presidência <strong>OU</strong></li>
                            <li>Ter cargo de Presidente ou Vice-Presidente</li>
                        </ul>
                        
                        <div class="btn-group d-block">
                            <button class="btn btn-primary btn-sm" onclick="window.location.reload()">
                                <i class="fas fa-sync me-1"></i>
                                Recarregar Página
                            </button>
                            <button class="btn btn-secondary btn-sm ms-2" onclick="window.history.back()">
                                <i class="fas fa-arrow-left me-1"></i>
                                Voltar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-shield-alt"></i>
                            Sistema de Auditoria
                            <?php if (!$isPresidencia): ?>
                                <span class="dept-badge">Dept. <?php echo htmlspecialchars($departamentoUsuario); ?></span>
                            <?php else: ?>
                                <span class="dept-badge presidencia">Presidência</span>
                            <?php endif; ?>
                        </h1>
                        <p class="page-subtitle">
                            <?php if ($isPresidencia): ?>
                                Monitoramento completo de atividades e segurança do sistema
                            <?php else: ?>
                                Monitoramento de atividades do departamento <?php echo htmlspecialchars($departamentoUsuario); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="live-indicator active" id="liveIndicator">
                        <span>Auto-update</span>
                    </div>
                </div>
            </div>

            <!-- Stats Grid com Dual Cards -->
            <div class="stats-grid" data-aos="fade-up">
                <!-- Card 1: Total de Registros + Ações Hoje -->
                <div class="stat-card dual-stat-card">
                    <div class="dual-stat-header">
                        <div class="dual-stat-title">
                            <i class="fas fa-database"></i>
                            Atividade Geral
                            <?php echo !$isPresidencia ? ' (Dept.)' : ''; ?>
                        </div>
                        <div class="dual-stat-percentage" id="atividadePercent">
                            <i class="fas fa-chart-line"></i>
                            <span>0%</span>
                        </div>
                    </div>
                    <div class="dual-stats-row">
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon registros-icon">
                                <i class="fas fa-list"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="totalRegistrosCard"><?php echo number_format($totalRegistros); ?></div>
                                <div class="dual-stat-label">Total Registros</div>
                            </div>
                        </div>
                        <div class="dual-stats-separator"></div>
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon acoes-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="acoesHojeCard"><?php echo $acoesHoje; ?></div>
                                <div class="dual-stat-label">Ações Hoje</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Usuários Ativos + Alertas -->
                <div class="stat-card dual-stat-card">
                    <div class="dual-stat-header">
                        <div class="dual-stat-title">
                            <i class="fas fa-users"></i>
                            Monitoramento
                            <?php echo !$isPresidencia ? ' (Dept.)' : ''; ?>
                        </div>
                        <div class="dual-stat-percentage" id="monitoramentoPercent">
                            <i class="fas fa-shield-alt"></i>
                            <span>Seguro</span>
                        </div>
                    </div>
                    <div class="dual-stats-row">
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon usuarios-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="usuariosAtivosCard"><?php echo $usuariosAtivos; ?></div>
                                <div class="dual-stat-label">Usuários Ativos</div>
                            </div>
                        </div>
                        <div class="dual-stats-separator"></div>
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon alertas-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value <?php echo $alertas > 0 ? 'notification-badge' : ''; ?>" id="alertasCard"><?php echo $alertas; ?></div>
                                <div class="dual-stat-label">Alertas</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Performance Temporal -->
                <div class="stat-card dual-stat-card">
                    <div class="dual-stat-header">
                        <div class="dual-stat-title">
                            <i class="fas fa-chart-area"></i>
                            Performance
                            <?php echo !$isPresidencia ? ' (Dept.)' : ''; ?>
                        </div>
                        <div class="dual-stat-percentage" id="performancePercent">
                            <i class="fas fa-trending-up"></i>
                            <span>+0%</span>
                        </div>
                    </div>
                    <div class="dual-stats-row">
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon hoje-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="hojeCard"><?php echo $acoesHoje; ?></div>
                                <div class="dual-stat-label">Hoje</div>
                            </div>
                        </div>
                        <div class="dual-stats-separator"></div>
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon periodo-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="taxaAtividade">
                                    <?php 
                                        $tendencia = $totalRegistros > 0 ? round(($acoesHoje / max($totalRegistros, 1)) * 100, 1) : 0;
                                        echo $tendencia . '%';
                                    ?>
                                </div>
                                <div class="dual-stat-label">Taxa Atividade</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions" data-aos="fade-up" data-aos-delay="100">
                <h3 class="quick-actions-title">Ações Rápidas</h3>
                <div class="quick-actions-grid">
                    <button class="quick-action-btn" onclick="abrirRelatorios()">
                        <i class="fas fa-chart-line quick-action-icon"></i>
                        Relatórios
                    </button>
                    <button class="quick-action-btn" onclick="verEstatisticas()">
                        <i class="fas fa-chart-bar quick-action-icon"></i>
                        Estatísticas
                    </button>
                    <button class="quick-action-btn" onclick="exportarDados()">
                        <i class="fas fa-download quick-action-icon"></i>
                        Exportar
                    </button>
                    <button class="quick-action-btn" onclick="configurarAuditoria()">
                        <i class="fas fa-cog quick-action-icon"></i>
                        Configurações
                    </button>
                    <button class="quick-action-btn" onclick="toggleAdvancedFilters()">
                        <i class="fas fa-filter quick-action-icon"></i>
                        Filtros Avançados
                    </button>
                    <button class="quick-action-btn" onclick="imprimirRelatorio()">
                        <i class="fas fa-print quick-action-icon"></i>
                        Imprimir
                    </button>
                </div>
            </div>

            <!-- Advanced Filters Section -->
            <div class="advanced-filters" id="advancedFilters">
                <h4 class="text-white mb-3">
                    <i class="fas fa-filter"></i> Filtros Avançados
                </h4>
                <div class="row">
                    <div class="col-md-3">
                        <label class="text-white">Data Inicial:</label>
                        <input type="datetime-local" class="form-control" id="filterDataInicio">
                    </div>
                    <div class="col-md-3">
                        <label class="text-white">Data Final:</label>
                        <input type="datetime-local" class="form-control" id="filterDataFim">
                    </div>
                    <div class="col-md-3">
                        <label class="text-white">IP:</label>
                        <input type="text" class="form-control" id="filterIP" placeholder="Ex: 192.168.1.1">
                    </div>
                    <div class="col-md-3">
                        <label class="text-white">ID do Registro:</label>
                        <input type="number" class="form-control" id="filterRegistroId" placeholder="Ex: 1234">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label class="text-white">Funcionário:</label>
                        <select class="form-select" id="filterFuncionario">
                            <option value="">Todos</option>
                            <!-- Será preenchido dinamicamente -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="text-white">Sessão ID:</label>
                        <input type="text" class="form-control" id="filterSessao" placeholder="ID da sessão">
                    </div>
                    <div class="col-md-3">
                        <label class="text-white">Ordenar por:</label>
                        <select class="form-select" id="filterOrdenar">
                            <option value="data_desc">Data (Mais recente)</option>
                            <option value="data_asc">Data (Mais antiga)</option>
                            <option value="funcionario">Funcionário</option>
                            <option value="acao">Ação</option>
                            <option value="tabela">Tabela</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="text-white">&nbsp;</label>
                        <button class="btn btn-light w-100" onclick="aplicarFiltrosAvancados()">
                            <i class="fas fa-search"></i> Aplicar Filtros
                        </button>
                    </div>
                </div>
            </div>

            <!-- Audit Section -->
            <div class="documents-section" data-aos="fade-up" data-aos-delay="200">
                <div class="section-header">
                    <h2 class="section-title">
                        <div class="section-title-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        Registros de Auditoria
                        <?php if (!$isPresidencia): ?>
                            <small class="text-muted">- Departamento <?php echo htmlspecialchars($departamentoUsuario); ?></small>
                        <?php endif; ?>
                    </h2>
                    <div class="section-actions">
                        <div class="export-options">
                            <button class="export-btn" onclick="exportarCSV()">
                                <i class="fas fa-file-csv"></i>CSV
                            </button>
                            <button class="export-btn" onclick="exportarExcel()">
                                <i class="fas fa-file-excel"></i>Excel
                            </button>
                            <button class="export-btn" onclick="exportarJSON()">
                                <i class="fas fa-file-code"></i>JSON
                            </button>
                            <button class="export-btn" onclick="exportarPDF()">
                                <i class="fas fa-file-pdf"></i>PDF
                            </button>
                        </div>
                        <button class="btn-action secondary ms-3" onclick="atualizarRegistros()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <input type="text" class="filter-input" id="searchInput" placeholder="Buscar por funcionário, tabela ou ID...">
                    <select class="filter-select" id="filterAcao">
                        <option value="">Todas as ações</option>
                        <option value="INSERT">Inserção</option>
                        <option value="UPDATE">Atualização</option>
                        <option value="DELETE">Exclusão</option>
                        <option value="LOGIN">Login</option>
                        <option value="LOGOUT">Logout</option>
                        <option value="LOGIN_FALHA">Login Falhou</option>
                        <option value="VIEW">Visualização</option>
                        <option value="EXPORT">Exportação</option>
                    </select>
                    <select class="filter-select" id="filterTabela">
                        <option value="">Todas as tabelas</option>
                        <option value="Associados">Associados</option>
                        <option value="Funcionarios">Funcionários</option>
                        <option value="Documentos_Associado">Documentos</option>
                        <option value="Financeiro">Financeiro</option>
                        <option value="Militar">Militar</option>
                        <option value="Contratos">Contratos</option>
                    </select>
                    <input type="date" class="filter-input" id="filterData" style="min-width: 150px;">
                    <button class="btn btn-sm btn-primary ms-2" onclick="limparFiltros()">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                </div>

                <!-- Audit Table -->
                <div class="table-responsive">
                    <table class="audit-table table">
                        <thead>
                            <tr>
                                <th width="5%">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th width="15%">Data/Hora</th>
                                <th width="15%">Funcionário</th>
                                <th width="10%">Ação</th>
                                <th width="15%">Tabela</th>
                                <th width="10%">Registro</th>
                                <th width="15%">IP</th>
                                <th width="15%">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                    <p class="text-muted mt-2">Carregando registros de auditoria...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions mt-3" id="bulkActions" style="display: none;">
                    <span class="me-3">
                        <span id="selectedCount">0</span> registro(s) selecionado(s)
                    </span>
                    <button class="btn btn-sm btn-danger" onclick="exportarSelecionados()">
                        <i class="fas fa-download"></i> Exportar Selecionados
                    </button>
                    <button class="btn btn-sm btn-info ms-2" onclick="analisarSelecionados()">
                        <i class="fas fa-chart-pie"></i> Analisar
                    </button>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Mostrando <span id="paginaAtual">1</span> - <span id="totalPaginas">1</span> de <span id="totalRegistrosPagina">0</span> registros
                        <select class="form-select form-select-sm d-inline-block ms-3" style="width: auto;" id="registrosPorPagina" onchange="mudarRegistrosPorPagina()">
                            <option value="10">10 por página</option>
                            <option value="20" selected>20 por página</option>
                            <option value="50">50 por página</option>
                            <option value="100">100 por página</option>
                        </select>
                    </div>
                    <nav>
                        <ul class="pagination" id="paginationNav">
                            <!-- Paginação será gerada dinamicamente -->
                        </ul>
                    </nav>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="detalhesModal" tabindex="-1" aria-labelledby="detalhesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detalhesModalLabel">
                        <i class="fas fa-info-circle" style="color: var(--primary);"></i>
                        Detalhes da Auditoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detalhesModalBody">
                    <!-- Conteúdo será carregado dinamicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="exportarDetalhe()">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Relatórios -->
    <div class="modal fade" id="relatoriosModal" tabindex="-1" aria-labelledby="relatoriosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="relatoriosModalLabel">
                        <i class="fas fa-chart-line" style="color: var(--primary);"></i>
                        Relatórios de Auditoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="chartAcoesPorDia"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="chartTiposAcao"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6>Filtros de Relatório</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <select class="form-select" id="relatorioTipo">
                                        <option value="geral">Relatório Geral</option>
                                        <option value="por_funcionario">Por Funcionário</option>
                                        <option value="por_acao">Por Tipo de Ação</option>
                                        <option value="seguranca">Segurança</option>
                                        <option value="performance">Performance</option>
                                        <option value="departamental">Departamental</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="relatorioPeriodo">
                                        <option value="hoje">Hoje</option>
                                        <option value="ontem">Ontem</option>
                                        <option value="semana">Esta Semana</option>
                                        <option value="mes" selected>Este Mês</option>
                                        <option value="trimestre">Trimestre</option>
                                        <option value="semestre">Semestre</option>
                                        <option value="ano">Este Ano</option>
                                        <option value="personalizado">Personalizado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary w-100" onclick="gerarRelatorio()">
                                        <i class="fas fa-chart-bar"></i>
                                        Gerar
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-success w-100" onclick="exportarRelatorio()">
                                        <i class="fas fa-download"></i>
                                        Exportar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4" id="relatorioResultado" style="display: none;">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-file-alt text-success"></i>
                                        Resultado do Relatório
                                    </h6>
                                    <div id="relatorioConteudo">
                                        <!-- Conteúdo será inserido aqui -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Configurações -->
    <div class="modal fade" id="configuracoesModal" tabindex="-1" aria-labelledby="configuracoesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="configuracoesModalLabel">
                        <i class="fas fa-cog" style="color: var(--primary);"></i>
                        Configurações de Auditoria
                        <?php if (!$isPresidencia): ?>
                            <span class="dept-badge ms-2">Dept. <?php echo htmlspecialchars($departamentoUsuario); ?></span>
                        <?php endif; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Configurações de Visualização -->
                    <div class="config-section">
                        <h6><i class="fas fa-eye"></i> Configurações de Visualização</h6>
                        <div class="config-grid">
                            <div class="config-toggle">
                                <label for="autoUpdate">Auto-atualização (30s)</label>
                                <label class="switch">
                                    <input type="checkbox" id="autoUpdate" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="config-toggle">
                                <label for="showNotifications">Mostrar notificações</label>
                                <label class="switch">
                                    <input type="checkbox" id="showNotifications" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="config-toggle">
                                <label for="compactView">Visualização compacta</label>
                                <label class="switch">
                                    <input type="checkbox" id="compactView">
                                    <span class="slider"></span>
                                </label>
                            </div>
                           
                            <div class="config-toggle">
                                <label for="debugMode">Modo debug</label>
                                <label class="switch">
                                    <input type="checkbox" id="debugMode">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="config-toggle">
                                <label for="enableCache">Habilitar cache</label>
                                <label class="switch">
                                    <input type="checkbox" id="enableCache" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações de Dados -->
                    <div class="config-section mt-3">
                        <h6><i class="fas fa-database"></i> Configurações de Dados</h6>
                        <div class="config-grid">
                            <div class="config-item">
                                <label>Registros por página:</label>
                                <select id="recordsPerPage">
                                    <option value="10">10 registros</option>
                                    <option value="20" selected>20 registros</option>
                                    <option value="50">50 registros</option>
                                    <option value="100">100 registros</option>
                                </select>
                            </div>
                            <div class="config-item">
                                <label>Intervalo de atualização (segundos):</label>
                                <input type="number" id="updateInterval" value="30" min="10" max="300">
                            </div>
                            <div class="config-item">
                                <label>Tempo de cache (minutos):</label>
                                <input type="number" id="cacheTime" value="5" min="1" max="60">
                            </div>
                            <div class="config-item">
                                <label>Formato de exportação padrão:</label>
                                <select id="exportFormat">
                                    <option value="csv" selected>CSV</option>
                                    <option value="excel">Excel</option>
                                    <option value="json">JSON</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações de Filtros -->
                    <div class="config-section mt-3">
                        <h6><i class="fas fa-filter"></i> Filtros Padrão</h6>
                        <div class="config-grid">
                            <div class="config-item">
                                <label>Período padrão:</label>
                                <select id="defaultPeriod">
                                    <option value="">Todos os registros</option>
                                    <option value="hoje">Hoje</option>
                                    <option value="semana">Esta semana</option>
                                    <option value="mes" selected>Este mês</option>
                                </select>
                            </div>
                            <div class="config-item">
                                <label>Ação padrão:</label>
                                <select id="defaultAction">
                                    <option value="" selected>Todas as ações</option>
                                    <option value="LOGIN">Apenas Logins</option>
                                    <option value="UPDATE">Apenas Atualizações</option>
                                    <option value="INSERT">Apenas Inserções</option>
                                </select>
                            </div>
                            <div class="config-item">
                                <label>Ordenação padrão:</label>
                                <select id="defaultSort">
                                    <option value="data_desc" selected>Data (Mais recente)</option>
                                    <option value="data_asc">Data (Mais antiga)</option>
                                    <option value="funcionario">Funcionário</option>
                                    <option value="acao">Ação</option>
                                </select>
                            </div>
                            <div class="config-item">
                                <label>Mostrar alertas automáticos:</label>
                                <select id="autoAlerts">
                                    <option value="all">Todos</option>
                                    <option value="critical" selected>Apenas críticos</option>
                                    <option value="none">Nenhum</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Informações do Sistema -->
                    <div class="alert alert-info mt-3">
                        <h6><i class="fas fa-info-circle"></i> Informações do Sistema</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small>
                                    <strong>Versão:</strong> 2.0.0<br>
                                    <strong>Última atualização:</strong> <?php echo date('d/m/Y H:i'); ?><br>
                                    <strong>Cache ativo:</strong> <span id="cacheStatusInfo">0 itens</span><br>
                                    <strong>Memória utilizada:</strong> <span id="memoryUsage">0 MB</span>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small>
                                    <strong>Auto-update:</strong> <span id="autoUpdateStatus">Ativo</span><br>
                                    <strong>Registros carregados:</strong> <span id="loadedRecords">0</span><br>
                                    <strong>Permissão:</strong> <?php echo $temPermissaoAuditoria ? 'Concedida' : 'Negada'; ?><br>
                                    <strong>Escopo:</strong> <?php echo $isPresidencia ? 'Sistema completo' : 'Departamento ' . $departamentoUsuario; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="limparCache()">
                        <i class="fas fa-trash"></i>
                        Limpar Cache
                    </button>
                    <button type="button" class="btn btn-warning" onclick="resetarConfiguracoes()">
                        <i class="fas fa-undo"></i>
                        Resetar
                    </button>
                    <button type="button" class="btn btn-success" onclick="salvarConfiguracoes()">
                        <i class="fas fa-save"></i>
                        Salvar
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // ===== CONFIGURAÇÃO GLOBAL =====
        const CONFIG = {
            temPermissao: <?php echo json_encode($temPermissaoAuditoria); ?>,
            isPresidencia: <?php echo json_encode($isPresidencia); ?>,
            isDiretor: <?php echo json_encode($isDiretor); ?>,
            departamentoUsuario: <?php echo json_encode($departamentoUsuario); ?>,
            apiBaseUrl: '../api/auditoria',
            updateInterval: 30000,
            cacheTimeout: 300000,
            debugMode: false
        };

        // ===== CLASSES E SISTEMAS =====

        /**
         * Sistema de Notificações Toast
         */
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('toastContainer');
                this.enabled = true;
            }
            
            show(message, type = 'success', duration = 5000) {
                if (!this.enabled) return;
                
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

            setEnabled(enabled) {
                this.enabled = enabled;
            }
        }

        /**
         * Sistema de Cache Avançado
         */
        class AdvancedCache {
            constructor(ttl = 300000) {
                this.cache = new Map();
                this.ttl = ttl;
                this.hits = 0;
                this.misses = 0;
            }
            
            set(key, value) {
                const expiry = Date.now() + this.ttl;
                this.cache.set(key, { value, expiry });
                this.cleanup();
            }
            
            get(key) {
                const item = this.cache.get(key);
                if (!item) {
                    this.misses++;
                    return null;
                }
                
                if (Date.now() > item.expiry) {
                    this.cache.delete(key);
                    this.misses++;
                    return null;
                }
                
                this.hits++;
                return item.value;
            }
            
            clear() {
                this.cache.clear();
                this.hits = 0;
                this.misses = 0;
            }

            cleanup() {
                const now = Date.now();
                for (const [key, item] of this.cache.entries()) {
                    if (now > item.expiry) {
                        this.cache.delete(key);
                    }
                }
            }

            getStats() {
                return {
                    size: this.cache.size,
                    hits: this.hits,
                    misses: this.misses,
                    hitRate: this.hits > 0 ? (this.hits / (this.hits + this.misses) * 100).toFixed(2) : 0
                };
            }

            setTTL(ttl) {
                this.ttl = ttl;
            }
        }

        /**
         * Sistema de Atualização Automática Avançado
         */
        class AutoUpdater {
            constructor(interval = 30000) {
                this.interval = interval;
                this.timer = null;
                this.isActive = true;
                this.updateCount = 0;
            }
            
            start() {
                if (this.timer) this.stop();
                
                this.timer = setInterval(() => {
                    if (this.isActive && document.hasFocus()) {
                        this.updateCount++;
                        carregarRegistrosAuditoria();
                        atualizarEstatisticas();
                        
                        if (CONFIG.debugMode) {
                            console.log(`[AutoUpdate #${this.updateCount}] Atualização executada`);
                        }
                    }
                }, this.interval);

                this.updateIndicator(true);
            }
            
            stop() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
                this.updateIndicator(false);
            }
            
            pause() {
                this.isActive = false;
                this.updateIndicator(false);
            }
            
            resume() {
                this.isActive = true;
                this.updateIndicator(true);
            }

            setInterval(interval) {
                this.interval = interval;
                if (this.timer) {
                    this.stop();
                    this.start();
                }
            }

            updateIndicator(active) {
                const indicator = document.getElementById('liveIndicator');
                if (indicator) {
                    if (active) {
                        indicator.classList.add('active');
                        indicator.classList.remove('paused');
                        indicator.innerHTML = '<span>Auto-update</span>';
                    } else {
                        indicator.classList.remove('active');
                        indicator.classList.add('paused');
                        indicator.innerHTML = '<span>Pausado</span>';
                    }
                }
            }

            getStats() {
                return {
                    updateCount: this.updateCount,
                    isActive: this.isActive,
                    interval: this.interval
                };
            }
        }

        /**
         * Monitor de Performance
         */
        class PerformanceMonitor {
            constructor() {
                this.fps = 60;
                this.memory = 0;
                this.lastFrameTime = performance.now();
                this.frameCount = 0;
                this.monitoring = false;
            }

            start() {
                this.monitoring = true;
                this.monitor();
            }

            stop() {
                this.monitoring = false;
            }

            monitor() {
                if (!this.monitoring) return;

                const now = performance.now();
                const delta = now - this.lastFrameTime;
                this.fps = Math.round(1000 / delta);
                this.lastFrameTime = now;
                this.frameCount++;

                if (performance.memory) {
                    this.memory = Math.round(performance.memory.usedJSHeapSize / 1048576);
                }

                this.updateDisplay();

                requestAnimationFrame(() => this.monitor());
            }

            updateDisplay() {
                const fpsEl = document.getElementById('fps');
                const memoryEl = document.getElementById('memory');
                const cacheSizeEl = document.getElementById('cacheSize');
                const updatesEl = document.getElementById('updates');

                if (fpsEl) fpsEl.textContent = this.fps;
                if (memoryEl) memoryEl.textContent = this.memory;
                if (cacheSizeEl) cacheSizeEl.textContent = cache.cache.size;
                if (updatesEl) updatesEl.textContent = autoUpdater.updateCount;
            }

            getStats() {
                return {
                    fps: this.fps,
                    memory: this.memory,
                    frameCount: this.frameCount
                };
            }
        }

        // ===== INSTÂNCIAS GLOBAIS =====
        const notifications = new NotificationSystem();
        const cache = new AdvancedCache(CONFIG.cacheTimeout);
        const autoUpdater = new AutoUpdater(CONFIG.updateInterval);
        const performanceMonitor = new PerformanceMonitor();

        // ===== VARIÁVEIS GLOBAIS =====
        let registrosAuditoria = [];
        let registrosSelecionados = new Set();
        let currentPage = 1;
        let totalPages = 1;
        let recordsPerPage = 20;
        let chartAcoes, chartTipos;
        let currentDetailId = null;
        let filtrosAvancados = {};

        // ===== FUNÇÕES UTILITÁRIAS =====

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        const debouncedFilter = debounce(filtrarRegistros, 300);

        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.add('show');
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('show');
        }

        function formatarData(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr);
            return data.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('pt-BR').format(num);
        }

        function getDataInicioPeriodo(periodo) {
            const hoje = new Date();
            let dataInicio;
            
            switch (periodo) {
                case 'hoje':
                    dataInicio = hoje;
                    break;
                case 'ontem':
                    dataInicio = new Date(hoje.getTime() - (24 * 60 * 60 * 1000));
                    break;
                case 'semana':
                    dataInicio = new Date(hoje.getTime() - (7 * 24 * 60 * 60 * 1000));
                    break;
                case 'mes':
                    dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                    break;
                case 'trimestre':
                    const mesAtual = hoje.getMonth();
                    const mesInicioTrimestre = Math.floor(mesAtual / 3) * 3;
                    dataInicio = new Date(hoje.getFullYear(), mesInicioTrimestre, 1);
                    break;
                case 'semestre':
                    const semestreInicio = hoje.getMonth() < 6 ? 0 : 6;
                    dataInicio = new Date(hoje.getFullYear(), semestreInicio, 1);
                    break;
                case 'ano':
                    dataInicio = new Date(hoje.getFullYear(), 0, 1);
                    break;
                default:
                    dataInicio = new Date(hoje.getTime() - (30 * 24 * 60 * 60 * 1000));
            }
            
            return dataInicio.toISOString().split('T')[0];
        }

        function obterParametrosDepartamentais() {
            const params = {};
            
            if (!CONFIG.isPresidencia && CONFIG.isDiretor && CONFIG.departamentoUsuario) {
                params.departamento_usuario = CONFIG.departamentoUsuario;
            }
            
            return params;
        }

        // ===== INICIALIZAÇÃO =====

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa AOS
            AOS.init({
                duration: 800,
                once: true
            });

            console.log('🚀 Sistema de Auditoria Iniciando...', CONFIG);

            if (!CONFIG.temPermissao) {
                console.log('❌ Usuário sem permissão - funcionalidades desabilitadas');
                return;
            }

            // Carregar configurações salvas
            carregarConfiguracoesSalvas();

            // Inicializar componentes
            carregarRegistrosAuditoria();
            configurarFiltros();
            configurarEventos();
            initializeCharts();
            atualizarEstatisticas();
            carregarListaFuncionarios();
            
            // Iniciar auto-update
            if (localStorage.getItem('autoUpdate') !== 'false') {
                autoUpdater.start();
            }

            // Iniciar monitor de performance se debug ativo
            if (CONFIG.debugMode || localStorage.getItem('debugMode') === 'true') {
                document.body.classList.add('debug-mode');
                performanceMonitor.start();
            }

            // Atualizar KPIs
            setInterval(atualizarPercentuaisKPI, 5000);

            notifications.show('Sistema de Auditoria carregado com sucesso!', 'success', 3000);
        });

        // ===== FUNÇÕES DE CARREGAMENTO DE DADOS =====

        async function carregarRegistrosAuditoria(page = 1, filters = {}) {
            if (!CONFIG.temPermissao) {
                console.log('❌ Sem permissão para carregar registros');
                return;
            }
            
            const tbody = document.getElementById('auditTableBody');
            
            // Adicionar filtros departamentais
            const allFilters = {
                ...filters,
                ...filtrosAvancados,
                ...obterParametrosDepartamentais()
            };
            
            // Verificar cache
            const cacheKey = `audit_${page}_${JSON.stringify(allFilters)}_${recordsPerPage}`;
            const cached = cache.get(cacheKey);
            if (cached && !filters.noCache) {
                renderizarTabela(cached.registros);
                atualizarPaginacao(cached.paginacao);
                updateCacheStatus();
                return;
            }
            
            // Mostrar loading
            if (tbody && tbody.children.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="text-muted mt-2">Carregando registros...</p>
                        </td>
                    </tr>
                `;
            }

            try {
                const params = new URLSearchParams({
                    page: page,
                    limit: recordsPerPage,
                    ...allFilters
                });
                
                if (CONFIG.debugMode) {
                    console.log('📡 Requisição:', params.toString());
                }
                
                const response = await fetch(`${CONFIG.apiBaseUrl}/registros.php?${params}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    registrosAuditoria = data.data.registros || [];
                    
                    // Armazenar em cache
                    cache.set(cacheKey, {
                        registros: registrosAuditoria,
                        paginacao: data.data.paginacao
                    });
                    
                    renderizarTabela(registrosAuditoria);
                    atualizarPaginacao(data.data.paginacao);
                    updateCacheStatus();
                    
                    const mensagem = CONFIG.isPresidencia 
                        ? `${registrosAuditoria.length} registro(s) carregado(s)`
                        : `${registrosAuditoria.length} registro(s) do departamento ${CONFIG.departamentoUsuario}`;
                    
                    if (notifications.enabled) {
                        notifications.show(mensagem, 'success', 2000);
                    }
                } else {
                    throw new Error(data.message || 'Erro ao carregar registros');
                }
            } catch (error) {
                console.error('❌ Erro ao carregar registros:', error);
                mostrarErroTabela('Erro ao carregar registros: ' + error.message);
                
                // Tentar usar dados simulados como fallback
                if (page === 1 && Object.keys(filters).length === 0) {
                    carregarDadosSimulados();
                }
            }
        }

        function carregarDadosSimulados() {
            const dadosSimulados = gerarDadosSimulados();
            registrosAuditoria = dadosSimulados;
            renderizarTabela(dadosSimulados);
            
            notifications.show('Usando dados simulados (API indisponível)', 'warning');
        }

        function gerarDadosSimulados() {
            const acoes = ['INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW'];
            const tabelas = ['Associados', 'Funcionarios', 'Documentos_Associado', 'Financeiro'];
            const funcionarios = ['João Silva', 'Maria Santos', 'Pedro Costa', 'Ana Oliveira'];
            
            const dados = [];
            for (let i = 0; i < 20; i++) {
                dados.push({
                    id: i + 1,
                    data_hora: new Date(Date.now() - Math.random() * 86400000 * 7).toISOString(),
                    funcionario_nome: funcionarios[Math.floor(Math.random() * funcionarios.length)],
                    acao: acoes[Math.floor(Math.random() * acoes.length)],
                    tabela: tabelas[Math.floor(Math.random() * tabelas.length)],
                    registro_id: Math.floor(Math.random() * 1000),
                    ip_origem: `192.168.1.${Math.floor(Math.random() * 255)}`,
                    funcionario_departamento: CONFIG.departamentoUsuario
                });
            }
            
            return dados.sort((a, b) => new Date(b.data_hora) - new Date(a.data_hora));
        }

        async function atualizarEstatisticas() {
            try {
                const params = new URLSearchParams(obterParametrosDepartamentais());
                const response = await fetch(`${CONFIG.apiBaseUrl}/estatisticas.php?${params}`);
                
                if (!response.ok) return;
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    const stats = data.data;
                    
                    // Atualizar cards
                    document.getElementById('totalRegistrosCard').textContent = formatNumber(stats.total_registros || 0);
                    document.getElementById('acoesHojeCard').textContent = formatNumber(stats.acoes_hoje || 0);
                    document.getElementById('usuariosAtivosCard').textContent = formatNumber(stats.usuarios_ativos || 0);
                    document.getElementById('alertasCard').textContent = formatNumber(stats.alertas || 0);
                    document.getElementById('hojeCard').textContent = formatNumber(stats.acoes_hoje || 0);
                    
                    // Atualizar taxa de atividade
                    const taxa = stats.total_registros > 0 
                        ? Math.round((stats.acoes_hoje / stats.total_registros) * 100) 
                        : 0;
                    document.getElementById('taxaAtividade').textContent = taxa + '%';
                    
                    // Atualizar gráficos se existirem dados
                    if (stats.acoes_periodo && chartAcoes) {
                        chartAcoes.data.labels = stats.acoes_periodo.labels;
                        chartAcoes.data.datasets[0].data = stats.acoes_periodo.data;
                        chartAcoes.update();
                    }
                    
                    if (stats.tipos_acao && chartTipos) {
                        chartTipos.data.labels = stats.tipos_acao.labels;
                        chartTipos.data.datasets[0].data = stats.tipos_acao.data;
                        chartTipos.update();
                    }
                }
            } catch (error) {
                console.error('Erro ao atualizar estatísticas:', error);
            }
        }

        async function carregarListaFuncionarios() {
            try {
                const params = new URLSearchParams(obterParametrosDepartamentais());
                const response = await fetch(`${CONFIG.apiBaseUrl}/funcionarios.php?${params}`);
                
                if (!response.ok) return;
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    const select = document.getElementById('filterFuncionario');
                    if (select) {
                        select.innerHTML = '<option value="">Todos</option>';
                        data.data.forEach(func => {
                            const option = document.createElement('option');
                            option.value = func.id;
                            option.textContent = func.nome;
                            select.appendChild(option);
                        });
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar funcionários:', error);
            }
        }

        // ===== FUNÇÕES DE RENDERIZAÇÃO =====

        function renderizarTabela(registros) {
            const tbody = document.getElementById('auditTableBody');
            
            if (!tbody) return;
            
            tbody.innerHTML = '';
            registrosSelecionados.clear();
            updateBulkActions();

            if (registros.length === 0) {
                const mensagem = CONFIG.isPresidencia 
                    ? 'Nenhum registro encontrado no sistema'
                    : `Nenhum registro encontrado para o departamento ${CONFIG.departamentoUsuario}`;
                
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Nenhum registro encontrado</h5>
                            <p class="text-muted">${mensagem}</p>
                            <button class="btn btn-primary btn-sm" onclick="limparFiltros()">
                                <i class="fas fa-times"></i> Limpar Filtros
                            </button>
                        </td>
                    </tr>
                `;
                return;
            }

            registros.forEach(registro => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <input type="checkbox" class="select-registro" value="${registro.id}" onchange="toggleSelect(${registro.id})">
                    </td>
                    <td>${formatarData(registro.data_hora)}</td>
                    <td>
                        ${registro.funcionario_nome || 'Sistema'}
                        ${!CONFIG.isPresidencia && registro.funcionario_departamento ? 
                            `<br><small class="text-muted">Dept: ${registro.funcionario_departamento}</small>` : ''}
                    </td>
                    <td><span class="action-badge ${registro.acao}">${registro.acao}</span></td>
                    <td>${registro.tabela}</td>
                    <td>${registro.registro_id || '-'}</td>
                    <td>${registro.ip_origem || '-'}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="mostrarDetalhes(${registro.id})" title="Ver detalhes">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="exportarRegistro(${registro.id})" title="Exportar">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="analisarRegistro(${registro.id})" title="Analisar">
                                <i class="fas fa-chart-pie"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Atualizar informações de status
            document.getElementById('loadedRecords').textContent = registros.length;
        }

        function atualizarPaginacao(paginacao) {
            if (!paginacao) return;
            
            currentPage = paginacao.pagina_atual;
            totalPages = paginacao.total_paginas;
            
            document.getElementById('paginaAtual').textContent = paginacao.pagina_atual;
            document.getElementById('totalPaginas').textContent = paginacao.total_paginas;
            document.getElementById('totalRegistrosPagina').textContent = paginacao.total_registros;

            const nav = document.getElementById('paginationNav');
            nav.innerHTML = '';

            // Primeira página
            if (currentPage > 1) {
                nav.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="irParaPagina(1)">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="irParaPagina(${currentPage - 1})">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                `;
            }

            // Páginas numeradas
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                nav.innerHTML += `
                    <li class="page-item ${i === currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="irParaPagina(${i})">${i}</a>
                    </li>
                `;
            }

            // Última página
            if (currentPage < totalPages) {
                nav.innerHTML += `
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="irParaPagina(${currentPage + 1})">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="irParaPagina(${totalPages})">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                `;
            }
        }

        function mostrarErroTabela(mensagem) {
            const tbody = document.getElementById('auditTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                            <h5 class="text-danger">Erro</h5>
                            <p class="text-muted">${mensagem}</p>
                            <button class="btn btn-primary btn-sm mt-2" onclick="location.reload()">
                                <i class="fas fa-redo"></i> Recarregar Página
                            </button>
                        </td>
                    </tr>
                `;
            }
        }

        // ===== FUNÇÕES DE FILTROS =====

        function configurarFiltros() {
            const searchInput = document.getElementById('searchInput');
            const filterAcao = document.getElementById('filterAcao');
            const filterTabela = document.getElementById('filterTabela');
            const filterData = document.getElementById('filterData');

            if (searchInput) searchInput.addEventListener('input', debouncedFilter);
            if (filterAcao) filterAcao.addEventListener('change', filtrarRegistros);
            if (filterTabela) filterTabela.addEventListener('change', filtrarRegistros);
            if (filterData) filterData.addEventListener('change', filtrarRegistros);
        }

        function filtrarRegistros() {
            const filters = obterFiltrosAtuais();
            currentPage = 1;
            carregarRegistrosAuditoria(1, filters);
        }

        function obterFiltrosAtuais() {
            const filters = {};
            
            const searchInput = document.getElementById('searchInput');
            const filterAcao = document.getElementById('filterAcao');
            const filterTabela = document.getElementById('filterTabela');
            const filterData = document.getElementById('filterData');
            
            if (searchInput && searchInput.value) {
                filters.search = searchInput.value;
            }
            
            if (filterAcao && filterAcao.value) {
                filters.acao = filterAcao.value;
            }
            
            if (filterTabela && filterTabela.value) {
                filters.tabela = filterTabela.value;
            }
            
            if (filterData && filterData.value) {
                filters.data_inicio = filterData.value;
                filters.data_fim = filterData.value;
            }

            return filters;
        }

        function limparFiltros() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterAcao').value = '';
            document.getElementById('filterTabela').value = '';
            document.getElementById('filterData').value = '';
            
            // Limpar filtros avançados também
            document.getElementById('filterDataInicio').value = '';
            document.getElementById('filterDataFim').value = '';
            document.getElementById('filterIP').value = '';
            document.getElementById('filterRegistroId').value = '';
            document.getElementById('filterFuncionario').value = '';
            document.getElementById('filterSessao').value = '';
            
            filtrosAvancados = {};
            filtrarRegistros();
        }

        function toggleAdvancedFilters() {
            const section = document.getElementById('advancedFilters');
            if (section) {
                section.classList.toggle('show');
            }
        }

        function aplicarFiltrosAvancados() {
            filtrosAvancados = {};
            
            const dataInicio = document.getElementById('filterDataInicio').value;
            const dataFim = document.getElementById('filterDataFim').value;
            const ip = document.getElementById('filterIP').value;
            const registroId = document.getElementById('filterRegistroId').value;
            const funcionarioId = document.getElementById('filterFuncionario').value;
            const sessao = document.getElementById('filterSessao').value;
            const ordenar = document.getElementById('filterOrdenar').value;
            
            if (dataInicio) filtrosAvancados.data_inicio = dataInicio;
            if (dataFim) filtrosAvancados.data_fim = dataFim;
            if (ip) filtrosAvancados.ip = ip;
            if (registroId) filtrosAvancados.registro_id = registroId;
            if (funcionarioId) filtrosAvancados.funcionario_id = funcionarioId;
            if (sessao) filtrosAvancados.sessao = sessao;
            if (ordenar) filtrosAvancados.ordenar = ordenar;
            
            currentPage = 1;
            carregarRegistrosAuditoria(1, { ...obterFiltrosAtuais(), noCache: true });
            
            notifications.show('Filtros avançados aplicados!', 'success');
        }

        // ===== FUNÇÕES DE SELEÇÃO E AÇÕES EM LOTE =====

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.select-registro');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
                const id = parseInt(cb.value);
                if (selectAll.checked) {
                    registrosSelecionados.add(id);
                } else {
                    registrosSelecionados.delete(id);
                }
            });
            
            updateBulkActions();
        }

        function toggleSelect(id) {
            if (registrosSelecionados.has(id)) {
                registrosSelecionados.delete(id);
            } else {
                registrosSelecionados.add(id);
            }
            updateBulkActions();
        }

        function updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (registrosSelecionados.size > 0) {
                bulkActions.style.display = 'block';
                selectedCount.textContent = registrosSelecionados.size;
            } else {
                bulkActions.style.display = 'none';
            }
        }

        async function exportarSelecionados() {
            if (registrosSelecionados.size === 0) {
                notifications.show('Nenhum registro selecionado', 'warning');
                return;
            }
            
            const ids = Array.from(registrosSelecionados);
            const registros = registrosAuditoria.filter(r => ids.includes(r.id));
            
            exportarDadosComoCSV(registros, 'auditoria_selecionados');
            notifications.show(`${registrosSelecionados.size} registro(s) exportado(s)!`, 'success');
        }

        function analisarSelecionados() {
            if (registrosSelecionados.size === 0) {
                notifications.show('Nenhum registro selecionado', 'warning');
                return;
            }
            
            const ids = Array.from(registrosSelecionados);
            const registros = registrosAuditoria.filter(r => ids.includes(r.id));
            
            // Análise básica
            const analise = {
                total: registros.length,
                porAcao: {},
                porTabela: {},
                porFuncionario: {}
            };
            
            registros.forEach(r => {
                analise.porAcao[r.acao] = (analise.porAcao[r.acao] || 0) + 1;
                analise.porTabela[r.tabela] = (analise.porTabela[r.tabela] || 0) + 1;
                analise.porFuncionario[r.funcionario_nome || 'Sistema'] = 
                    (analise.porFuncionario[r.funcionario_nome || 'Sistema'] || 0) + 1;
            });
            
            mostrarAnalise(analise);
        }

        function mostrarAnalise(analise) {
            const modal = new bootstrap.Modal(document.getElementById('detalhesModal'));
            const body = document.getElementById('detalhesModalBody');
            
            let html = `
                <h5>Análise dos Registros Selecionados</h5>
                <p><strong>Total de registros:</strong> ${analise.total}</p>
                
                <div class="row">
                    <div class="col-md-4">
                        <h6>Por Ação:</h6>
                        <ul class="list-unstyled">
                            ${Object.entries(analise.porAcao)
                                .sort((a, b) => b[1] - a[1])
                                .map(([acao, count]) => 
                                    `<li><span class="badge bg-primary">${acao}</span> ${count}</li>`
                                ).join('')}
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>Por Tabela:</h6>
                        <ul class="list-unstyled">
                            ${Object.entries(analise.porTabela)
                                .sort((a, b) => b[1] - a[1])
                                .map(([tabela, count]) => 
                                    `<li><strong>${tabela}:</strong> ${count}</li>`
                                ).join('')}
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6>Por Funcionário:</h6>
                        <ul class="list-unstyled">
                            ${Object.entries(analise.porFuncionario)
                                .sort((a, b) => b[1] - a[1])
                                .slice(0, 5)
                                .map(([func, count]) => 
                                    `<li><strong>${func}:</strong> ${count}</li>`
                                ).join('')}
                        </ul>
                    </div>
                </div>
            `;
            
            body.innerHTML = html;
            modal.show();
        }

        // ===== FUNÇÕES DE DETALHES E MODAIS =====

        async function mostrarDetalhes(auditId) {
            currentDetailId = auditId;
            
            try {
                const params = new URLSearchParams({
                    id: auditId,
                    ...obterParametrosDepartamentais()
                });
                
                const response = await fetch(`${CONFIG.apiBaseUrl}/detalhes.php?${params}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    mostrarDetalhesModal(data.data);
                } else {
                    throw new Error(data.message || 'Erro ao carregar detalhes');
                }
                
            } catch (error) {
                console.error('Erro ao carregar detalhes:', error);
                mostrarDetalhesSimulados(auditId);
            }
        }

        function mostrarDetalhesModal(registro) {
            const modalBody = document.getElementById('detalhesModalBody');
            
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle text-primary"></i> Informações Básicas</h6>
                        <table class="table table-sm">
                            <tr><td><strong>ID:</strong></td><td>${registro.id}</td></tr>
                            <tr><td><strong>Data/Hora:</strong></td><td>${formatarData(registro.data_hora)}</td></tr>
                            <tr><td><strong>Funcionário:</strong></td><td>${registro.funcionario_nome || 'Sistema'}</td></tr>
                            <tr><td><strong>Ação:</strong></td><td><span class="action-badge ${registro.acao}">${registro.acao}</span></td></tr>
                            <tr><td><strong>Tabela:</strong></td><td>${registro.tabela}</td></tr>
                            <tr><td><strong>Registro ID:</strong></td><td>${registro.registro_id || '-'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-server text-info"></i> Informações Técnicas</h6>
                        <table class="table table-sm">
                            <tr><td><strong>IP Origem:</strong></td><td>${registro.ip_origem || '-'}</td></tr>
                            <tr><td><strong>Navegador:</strong></td><td>${registro.browser_info || '-'}</td></tr>
                            <tr><td><strong>Sessão ID:</strong></td><td>${registro.sessao_id || '-'}</td></tr>
                            <tr><td><strong>Associado ID:</strong></td><td>${registro.associado_id || '-'}</td></tr>
                            ${!CONFIG.isPresidencia && registro.funcionario_departamento ? 
                                `<tr><td><strong>Departamento:</strong></td><td>${registro.funcionario_departamento}</td></tr>` : ''}
                        </table>
                    </div>
                </div>
                
                ${registro.alteracoes ? `
                    <div class="mt-3">
                        <h6><i class="fas fa-exchange-alt text-warning"></i> Alterações Realizadas</h6>
                        <pre class="bg-light p-3 rounded" style="font-size: 0.8rem; max-height: 300px; overflow-y: auto;">
${JSON.stringify(JSON.parse(registro.alteracoes), null, 2)}</pre>
                    </div>
                ` : ''}
                
                ${!CONFIG.isPresidencia ? `
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Filtro Departamental:</strong> Visualizando registro do departamento ${CONFIG.departamentoUsuario}.
                        </div>
                    </div>
                ` : ''}
            `;
            
            new bootstrap.Modal(document.getElementById('detalhesModal')).show();
        }

        function mostrarDetalhesSimulados(auditId) {
            const registro = registrosAuditoria.find(r => r.id == auditId);
            
            if (registro) {
                mostrarDetalhesModal(registro);
            } else {
                notifications.show('Registro não encontrado', 'error');
            }
        }

        function exportarDetalhe() {
            if (!currentDetailId) return;
            
            const registro = registrosAuditoria.find(r => r.id == currentDetailId);
            if (registro) {
                exportarDadosComoJSON([registro], `auditoria_detalhe_${currentDetailId}`);
                notifications.show('Detalhe exportado com sucesso!', 'success');
            }
        }

        // ===== FUNÇÕES DE EXPORTAÇÃO =====

        async function exportarDados() {
            try {
                notifications.show('Preparando exportação...', 'info');
                
                const filters = obterFiltrosAtuais();
                const allFilters = {
                    ...filters,
                    ...filtrosAvancados,
                    ...obterParametrosDepartamentais()
                };
                
                const params = new URLSearchParams(allFilters);
                const response = await fetch(`${CONFIG.apiBaseUrl}/exportar.php?${params}`);
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = `auditoria_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    notifications.show('Dados exportados com sucesso!', 'success');
                } else {
                    throw new Error('Erro ao exportar dados');
                }
            } catch (error) {
                console.error('Erro ao exportar:', error);
                // Fallback: exportar dados locais
                exportarDadosLocais();
            }
        }

        function exportarDadosLocais() {
            const formato = document.getElementById('exportFormat')?.value || 'csv';
            
            switch (formato) {
                case 'csv':
                    exportarCSV();
                    break;
                case 'excel':
                    exportarExcel();
                    break;
                case 'json':
                    exportarJSON();
                    break;
                case 'pdf':
                    exportarPDF();
                    break;
                default:
                    exportarCSV();
            }
        }

        function exportarCSV() {
            exportarDadosComoCSV(registrosAuditoria, 'auditoria');
        }

        function exportarExcel() {
            const ws = XLSX.utils.json_to_sheet(registrosAuditoria);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Auditoria");
            XLSX.writeFile(wb, `auditoria_${new Date().toISOString().split('T')[0]}.xlsx`);
            notifications.show('Exportado para Excel!', 'success');
        }

        function exportarJSON() {
            exportarDadosComoJSON(registrosAuditoria, 'auditoria');
        }

        function exportarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(16);
            doc.text('Relatório de Auditoria', 20, 20);
            
            doc.setFontSize(10);
            doc.text(`Gerado em: ${new Date().toLocaleString('pt-BR')}`, 20, 30);
            doc.text(`Total de registros: ${registrosAuditoria.length}`, 20, 35);
            
            let y = 45;
            registrosAuditoria.slice(0, 20).forEach(registro => {
                doc.text(`${formatarData(registro.data_hora)} - ${registro.acao} - ${registro.tabela}`, 20, y);
                y += 5;
                if (y > 280) {
                    doc.addPage();
                    y = 20;
                }
            });
            
            doc.save(`auditoria_${new Date().toISOString().split('T')[0]}.pdf`);
            notifications.show('PDF gerado com sucesso!', 'success');
        }

        function exportarDadosComoCSV(dados, nomeArquivo) {
            if (!dados || dados.length === 0) {
                notifications.show('Nenhum dado para exportar', 'warning');
                return;
            }
            
            const headers = Object.keys(dados[0]);
            const csvContent = [
                headers.join(','),
                ...dados.map(row => 
                    headers.map(header => {
                        const value = row[header];
                        return typeof value === 'string' && value.includes(',') 
                            ? `"${value}"` 
                            : value || '';
                    }).join(',')
                )
            ].join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${nomeArquivo}_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            
            notifications.show('CSV exportado com sucesso!', 'success');
        }

        function exportarDadosComoJSON(dados, nomeArquivo) {
            const jsonContent = JSON.stringify(dados, null, 2);
            const blob = new Blob([jsonContent], { type: 'application/json' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${nomeArquivo}_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            
            notifications.show('JSON exportado com sucesso!', 'success');
        }

        function exportarRegistro(id) {
            const registro = registrosAuditoria.find(r => r.id === id);
            if (registro) {
                exportarDadosComoJSON([registro], `registro_${id}`);
            }
        }

        function analisarRegistro(id) {
            const registro = registrosAuditoria.find(r => r.id === id);
            if (registro) {
                mostrarAnalise({
                    total: 1,
                    porAcao: { [registro.acao]: 1 },
                    porTabela: { [registro.tabela]: 1 },
                    porFuncionario: { [registro.funcionario_nome || 'Sistema']: 1 }
                });
            }
        }

        // ===== FUNÇÕES DE CONFIGURAÇÃO =====

        function configurarAuditoria() {
            const modal = new bootstrap.Modal(document.getElementById('configuracoesModal'));
            carregarConfiguracoesModal();
            modal.show();
        }

        function carregarConfiguracoesModal() {
            const configs = JSON.parse(localStorage.getItem('auditoriaConfigs') || '{}');
            
            // Carregar configurações de visualização
            if (configs.autoUpdate !== undefined) {
                document.getElementById('autoUpdate').checked = configs.autoUpdate;
            }
            if (configs.showNotifications !== undefined) {
                document.getElementById('showNotifications').checked = configs.showNotifications;
            }
            if (configs.compactView !== undefined) {
                document.getElementById('compactView').checked = configs.compactView;
            }
            
            if (configs.debugMode !== undefined) {
                document.getElementById('debugMode').checked = configs.debugMode;
            }
            if (configs.enableCache !== undefined) {
                document.getElementById('enableCache').checked = configs.enableCache;
            }
            
            // Carregar configurações de dados
            if (configs.recordsPerPage) {
                document.getElementById('recordsPerPage').value = configs.recordsPerPage;
            }
            if (configs.updateInterval) {
                document.getElementById('updateInterval').value = configs.updateInterval;
            }
            if (configs.cacheTime) {
                document.getElementById('cacheTime').value = configs.cacheTime;
            }
            if (configs.exportFormat) {
                document.getElementById('exportFormat').value = configs.exportFormat;
            }
            
            // Carregar configurações de filtros
            if (configs.defaultPeriod) {
                document.getElementById('defaultPeriod').value = configs.defaultPeriod;
            }
            if (configs.defaultAction) {
                document.getElementById('defaultAction').value = configs.defaultAction;
            }
            if (configs.defaultSort) {
                document.getElementById('defaultSort').value = configs.defaultSort;
            }
            if (configs.autoAlerts) {
                document.getElementById('autoAlerts').value = configs.autoAlerts;
            }
            
            // Atualizar informações do sistema
            updateSystemInfo();
        }

        function salvarConfiguracoes() {
            const configuracoes = {
                // Visualização
                autoUpdate: document.getElementById('autoUpdate').checked,
                showNotifications: document.getElementById('showNotifications').checked,
                compactView: document.getElementById('compactView').checked,
                darkMode: document.getElementById('darkMode').checked,
                debugMode: document.getElementById('debugMode').checked,
                enableCache: document.getElementById('enableCache').checked,
                
                // Dados
                recordsPerPage: document.getElementById('recordsPerPage').value,
                updateInterval: parseInt(document.getElementById('updateInterval').value),
                cacheTime: parseInt(document.getElementById('cacheTime').value),
                exportFormat: document.getElementById('exportFormat').value,
                
                // Filtros
                defaultPeriod: document.getElementById('defaultPeriod').value,
                defaultAction: document.getElementById('defaultAction').value,
                defaultSort: document.getElementById('defaultSort').value,
                autoAlerts: document.getElementById('autoAlerts').value,
                
                // Metadados
                isPresidencia: CONFIG.isPresidencia,
                departamentoUsuario: CONFIG.departamentoUsuario,
                savedAt: new Date().toISOString()
            };
            
            // Salvar no localStorage
            localStorage.setItem('auditoriaConfigs', JSON.stringify(configuracoes));
            
            // Aplicar configurações
            aplicarConfiguracoes(configuracoes);
            
            notifications.show('Configurações salvas com sucesso!', 'success');
            
            // Fechar modal
            bootstrap.Modal.getInstance(document.getElementById('configuracoesModal')).hide();
        }

        function aplicarConfiguracoes(configs) {
            // Auto-update
            if (configs.autoUpdate) {
                autoUpdater.resume();
                autoUpdater.setInterval(configs.updateInterval * 1000);
            } else {
                autoUpdater.pause();
            }
            
            // Notificações
            notifications.setEnabled(configs.showNotifications);
            
            // Visualização compacta
            if (configs.compactView) {
                document.body.classList.add('compact-view');
            } else {
                document.body.classList.remove('compact-view');
            }
            
            // Modo escuro
            if (configs.darkMode) {
                document.body.classList.add('dark-theme');
            } else {
                document.body.classList.remove('dark-theme');
            }
            
            // Modo debug
            if (configs.debugMode) {
                document.body.classList.add('debug-mode');
                CONFIG.debugMode = true;
                performanceMonitor.start();
            } else {
                document.body.classList.remove('debug-mode');
                CONFIG.debugMode = false;
                performanceMonitor.stop();
            }
            
            // Cache
            if (!configs.enableCache) {
                cache.clear();
            }
            cache.setTTL(configs.cacheTime * 60000);
            
            // Registros por página
            recordsPerPage = parseInt(configs.recordsPerPage) || 20;
            
            // Aplicar filtros padrão se não houver filtros ativos
            if (!document.getElementById('filterAcao').value && configs.defaultAction) {
                document.getElementById('filterAcao').value = configs.defaultAction;
            }
            
            console.log('✅ Configurações aplicadas:', configs);
        }

        function resetarConfiguracoes() {
            if (confirm('Tem certeza que deseja resetar todas as configurações?')) {
                localStorage.removeItem('auditoriaConfigs');
                
                // Resetar valores padrão
                document.getElementById('autoUpdate').checked = true;
                document.getElementById('showNotifications').checked = true;
                document.getElementById('compactView').checked = false;
                document.getElementById('darkMode').checked = false;
                document.getElementById('debugMode').checked = false;
                document.getElementById('enableCache').checked = true;
                document.getElementById('recordsPerPage').value = '20';
                document.getElementById('updateInterval').value = '30';
                document.getElementById('cacheTime').value = '5';
                document.getElementById('exportFormat').value = 'csv';
                document.getElementById('defaultPeriod').value = 'mes';
                document.getElementById('defaultAction').value = '';
                document.getElementById('defaultSort').value = 'data_desc';
                document.getElementById('autoAlerts').value = 'critical';
                
                // Aplicar configurações padrão
                aplicarConfiguracoes({
                    autoUpdate: true,
                    showNotifications: true,
                    compactView: false,
                    darkMode: false,
                    debugMode: false,
                    enableCache: true,
                    recordsPerPage: 20,
                    updateInterval: 30,
                    cacheTime: 5,
                    exportFormat: 'csv'
                });
                
                notifications.show('Configurações resetadas!', 'info');
            }
        }

        function limparCache() {
            cache.clear();
            notifications.show('Cache limpo com sucesso!', 'success');
            updateSystemInfo();
            carregarRegistrosAuditoria(currentPage, { noCache: true });
        }

        function carregarConfiguracoesSalvas() {
            const configs = JSON.parse(localStorage.getItem('auditoriaConfigs') || '{}');
            
            if (Object.keys(configs).length > 0) {
                aplicarConfiguracoes(configs);
                console.log('📋 Configurações carregadas:', configs);
            }
        }

        function updateSystemInfo() {
            const cacheStats = cache.getStats();
            const autoStats = autoUpdater.getStats();
            const perfStats = performanceMonitor.getStats();
            
            document.getElementById('cacheStatusInfo').textContent = `${cacheStats.size} itens (${cacheStats.hitRate}% hit rate)`;
            document.getElementById('memoryUsage').textContent = `${perfStats.memory} MB`;
            document.getElementById('autoUpdateStatus').textContent = autoStats.isActive ? 'Ativo' : 'Pausado';
            document.getElementById('loadedRecords').textContent = registrosAuditoria.length;
        }

        // ===== FUNÇÕES DE RELATÓRIOS =====

        function abrirRelatorios() {
            const modal = new bootstrap.Modal(document.getElementById('relatoriosModal'));
            modal.show();
            
            setTimeout(() => {
                carregarDadosGraficos();
            }, 300);
        }

        async function carregarDadosGraficos() {
            try {
                const params = new URLSearchParams(obterParametrosDepartamentais());
                const response = await fetch(`${CONFIG.apiBaseUrl}/estatisticas.php?${params}`);
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    if (data.data.acoes_periodo && chartAcoes) {
                        chartAcoes.data.labels = data.data.acoes_periodo.labels;
                        chartAcoes.data.datasets[0].data = data.data.acoes_periodo.data;
                        chartAcoes.update();
                    }

                    if (data.data.tipos_acao && chartTipos) {
                        chartTipos.data.labels = data.data.tipos_acao.labels;
                        chartTipos.data.datasets[0].data = data.data.tipos_acao.data;
                        chartTipos.update();
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar dados dos gráficos:', error);
            }
        }

        async function gerarRelatorio() {
            const tipo = document.getElementById('relatorioTipo').value;
            const periodo = document.getElementById('relatorioPeriodo').value;
            
            try {
                showLoading();
                
                const response = await fetch(`${CONFIG.apiBaseUrl}/relatorios.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        tipo: tipo,
                        periodo: periodo,
                        ...obterParametrosDepartamentais()
                    })
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    notifications.show('Relatório gerado com sucesso!', 'success');
                    mostrarResultadoRelatorio(data.data, tipo, periodo);
                } else {
                    throw new Error(data.message || 'Erro ao gerar relatório');
                }
            } catch (error) {
                console.error('Erro ao gerar relatório:', error);
                notifications.show('Erro ao gerar relatório', 'error');
            } finally {
                hideLoading();
            }
        }

        function mostrarResultadoRelatorio(dadosRelatorio, tipo, periodo) {
            const container = document.getElementById('relatorioResultado');
            const conteudo = document.getElementById('relatorioConteudo');
            
            const escopo = CONFIG.isPresidencia ? 'Sistema Completo' : `Departamento ${CONFIG.departamentoUsuario}`;
            
            let html = `
                <div class="mb-3">
                    <h6>Relatório: ${getTipoRelatorioNome(tipo)}</h6>
                    <p class="text-muted">
                        Período: ${getPeriodoNome(periodo)} | 
                        Escopo: ${escopo} | 
                        Gerado em: ${new Date().toLocaleString('pt-BR')}
                    </p>
                </div>
            `;
            
            if (dadosRelatorio && dadosRelatorio.estatisticas) {
                switch (tipo) {
                    case 'geral':
                        html += gerarHtmlRelatorioGeral(dadosRelatorio.estatisticas);
                        break;
                    case 'por_funcionario':
                        html += gerarHtmlRelatorioPorFuncionario(dadosRelatorio.estatisticas);
                        break;
                    case 'por_acao':
                        html += gerarHtmlRelatorioPorAcao(dadosRelatorio.estatisticas);
                        break;
                    case 'seguranca':
                        html += gerarHtmlRelatorioSeguranca(dadosRelatorio.estatisticas);
                        break;
                    case 'performance':
                        html += gerarHtmlRelatorioPerformance(dadosRelatorio.estatisticas);
                        break;
                    case 'departamental':
                        html += gerarHtmlRelatorioDepartamental(dadosRelatorio.estatisticas);
                        break;
                    default:
                        html += `<pre>${JSON.stringify(dadosRelatorio, null, 2)}</pre>`;
                }
            } else {
                html += `<p class="text-muted">Nenhum dado disponível para este relatório.</p>`;
            }
            
            conteudo.innerHTML = html;
            container.style.display = 'block';
            
            container.scrollIntoView({ behavior: 'smooth' });
        }

        function gerarHtmlRelatorioGeral(stats) {
            return `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td><strong>Total de Ações:</strong></td><td>${formatNumber(stats.total_acoes || 0)}</td></tr>
                            <tr><td><strong>Funcionários Ativos:</strong></td><td>${formatNumber(stats.funcionarios_ativos || 0)}</td></tr>
                            <tr><td><strong>Associados Afetados:</strong></td><td>${formatNumber(stats.associados_afetados || 0)}</td></tr>
                            <tr><td><strong>Tabelas Modificadas:</strong></td><td>${formatNumber(stats.tabelas_modificadas || 0)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><td><strong>Dias com Atividade:</strong></td><td>${formatNumber(stats.dias_com_atividade || 0)}</td></tr>
                            <tr><td><strong>IPs Únicos:</strong></td><td>${formatNumber(stats.ips_unicos || 0)}</td></tr>
                            <tr><td><strong>Taxa de Erro:</strong></td><td>${stats.taxa_erro || 0}%</td></tr>
                            ${!CONFIG.isPresidencia ? `<tr><td><strong>Escopo:</strong></td><td>Departamento ${CONFIG.departamentoUsuario}</td></tr>` : ''}
                        </table>
                    </div>
                </div>
                ${stats.grafico ? `<canvas id="relatorioChart" height="100"></canvas>` : ''}
            `;
        }

        function gerarHtmlRelatorioPorFuncionario(stats) {
            if (!Array.isArray(stats)) return '<p>Dados inválidos</p>';
            
            return `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Funcionário</th>
                                <th>Total de Ações</th>
                                <th>Dias Ativos</th>
                                <th>Tabelas Acessadas</th>
                                <th>Última Atividade</th>
                                <th>Produtividade</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${stats.map(func => `
                                <tr>
                                    <td>
                                        ${func.nome}
                                        ${func.departamento ? `<br><small class="text-muted">${func.departamento}</small>` : ''}
                                    </td>
                                    <td>${formatNumber(func.total_acoes || 0)}</td>
                                    <td>${formatNumber(func.dias_ativos || 0)}</td>
                                    <td>${formatNumber(func.tabelas_acessadas || 0)}</td>
                                    <td>${func.ultima_atividade ? formatarData(func.ultima_atividade) : '-'}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: ${Math.min(100, (func.total_acoes || 0) / 2)}%">
                                                ${((func.total_acoes || 0) / Math.max(func.dias_ativos || 1, 1)).toFixed(1)} ações/dia
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function gerarHtmlRelatorioPorAcao(stats) {
            if (!Array.isArray(stats)) return '<p>Dados inválidos</p>';
            
            return `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Ação</th>
                                <th>Tabela</th>
                                <th>Total</th>
                                <th>Funcionários</th>
                                <th>Primeira Vez</th>
                                <th>Última Vez</th>
                                <th>Frequência</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${stats.map(acao => `
                                <tr>
                                    <td><span class="action-badge ${acao.acao}">${acao.acao}</span></td>
                                    <td>${acao.tabela}</td>
                                    <td>${formatNumber(acao.total || 0)}</td>
                                    <td>${formatNumber(acao.funcionarios || 0)}</td>
                                    <td>${acao.primeira_vez ? formatarData(acao.primeira_vez) : '-'}</td>
                                    <td>${acao.ultima_vez ? formatarData(acao.ultima_vez) : '-'}</td>
                                    <td>
                                        <span class="badge bg-${acao.frequencia === 'alta' ? 'danger' : acao.frequencia === 'media' ? 'warning' : 'success'}">
                                            ${acao.frequencia || 'baixa'}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function gerarHtmlRelatorioSeguranca(stats) {
            return `
                <div class="alert alert-warning">
                    <h6><i class="fas fa-shield-alt"></i> Relatório de Segurança</h6>
                    <p>Análise de eventos de segurança e atividades suspeitas.</p>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h5 class="card-title">Tentativas de Login</h5>
                                <h2 class="text-warning">${stats.tentativas_login_falhas || 0}</h2>
                                <small class="text-muted">Falhas detectadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h5 class="card-title">Exclusões</h5>
                                <h2 class="text-danger">${stats.total_exclusoes || 0}</h2>
                                <small class="text-muted">Registros excluídos</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h5 class="card-title">IPs Suspeitos</h5>
                                <h2 class="text-info">${stats.ips_suspeitos || 0}</h2>
                                <small class="text-muted">Endereços monitorados</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h5 class="card-title">Status</h5>
                                <h2 class="text-success">
                                    <i class="fas fa-${stats.status === 'seguro' ? 'check-circle' : 'exclamation-triangle'}"></i>
                                </h2>
                                <small class="text-muted">${stats.status || 'Seguro'}</small>
                            </div>
                        </div>
                    </div>
                </div>
                ${stats.alertas && stats.alertas.length > 0 ? `
                    <div class="mt-3">
                        <h6>Alertas Recentes:</h6>
                        <ul class="list-unstyled">
                            ${stats.alertas.map(alerta => `
                                <li class="alert alert-danger">
                                    <strong>${alerta.tipo}:</strong> ${alerta.descricao}
                                    <br><small>Detectado em: ${formatarData(alerta.data)}</small>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                ` : ''}
            `;
        }

        function gerarHtmlRelatorioPerformance(stats) {
            return `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Métricas de Performance</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Tempo Médio de Resposta:</strong></td><td>${stats.tempo_medio_resposta || 0}ms</td></tr>
                            <tr><td><strong>Taxa de Cache Hit:</strong></td><td>${stats.cache_hit_rate || 0}%</td></tr>
                            <tr><td><strong>Queries por Segundo:</strong></td><td>${stats.queries_por_segundo || 0}</td></tr>
                            <tr><td><strong>Memória Utilizada:</strong></td><td>${stats.memoria_utilizada || 0}MB</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Estatísticas de Uso</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Pico de Usuários:</strong></td><td>${stats.pico_usuarios || 0}</td></tr>
                            <tr><td><strong>Horário de Pico:</strong></td><td>${stats.horario_pico || 'N/A'}</td></tr>
                            <tr><td><strong>Uptime:</strong></td><td>${stats.uptime || '99.9'}%</td></tr>
                            <tr><td><strong>Erros/Hora:</strong></td><td>${stats.erros_por_hora || 0}</td></tr>
                        </table>
                    </div>
                </div>
            `;
        }

        function gerarHtmlRelatorioDepartamental(stats) {
            return `
                <div class="alert alert-info">
                    <h6><i class="fas fa-building"></i> Relatório Departamental</h6>
                    <p>Análise específica por departamento.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Departamento</th>
                                <th>Total Ações</th>
                                <th>Funcionários Ativos</th>
                                <th>Última Atividade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${stats.departamentos ? stats.departamentos.map(dept => `
                                <tr>
                                    <td>${dept.nome}</td>
                                    <td>${formatNumber(dept.total_acoes || 0)}</td>
                                    <td>${formatNumber(dept.funcionarios_ativos || 0)}</td>
                                    <td>${dept.ultima_atividade ? formatarData(dept.ultima_atividade) : '-'}</td>
                                    <td>
                                        <span class="badge bg-${dept.status === 'ativo' ? 'success' : 'secondary'}">
                                            ${dept.status || 'inativo'}
                                        </span>
                                    </td>
                                </tr>
                            `).join('') : '<tr><td colspan="5">Nenhum dado disponível</td></tr>'}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function getTipoRelatorioNome(tipo) {
            const nomes = {
                'geral': 'Relatório Geral',
                'por_funcionario': 'Relatório por Funcionário',
                'por_acao': 'Relatório por Tipo de Ação',
                'seguranca': 'Relatório de Segurança',
                'performance': 'Relatório de Performance',
                'departamental': 'Relatório Departamental'
            };
            return nomes[tipo] || tipo;
        }

        function getPeriodoNome(periodo) {
            const nomes = {
                'hoje': 'Hoje',
                'ontem': 'Ontem',
                'semana': 'Esta Semana',
                'mes': 'Este Mês',
                'trimestre': 'Trimestre Atual',
                'semestre': 'Semestre',
                'ano': 'Este Ano',
                'personalizado': 'Período Personalizado'
            };
            return nomes[periodo] || periodo;
        }

        async function exportarRelatorio() {
            try {
                const tipo = document.getElementById('relatorioTipo').value;
                const periodo = document.getElementById('relatorioPeriodo').value;
                
                notifications.show('Exportando relatório...', 'info');
                
                const response = await fetch(`${CONFIG.apiBaseUrl}/relatorios.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        tipo: tipo,
                        periodo: periodo,
                        formato: 'csv',
                        ...obterParametrosDepartamentais()
                    })
                });
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `relatorio_${tipo}_${periodo}_${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    notifications.show('Relatório exportado com sucesso!', 'success');
                } else {
                    throw new Error('Erro ao exportar relatório');
                }
            } catch (error) {
                console.error('Erro ao exportar relatório:', error);
                notifications.show('Erro ao exportar relatório', 'error');
            }
        }

        async function verEstatisticas() {
            try {
                notifications.show('Carregando estatísticas detalhadas...', 'info');
                
                const params = new URLSearchParams(obterParametrosDepartamentais());
                const response = await fetch(`${CONFIG.apiBaseUrl}/estatisticas.php?${params}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.status === 'success' && data.data) {
                    mostrarModalEstatisticas(data.data);
                } else {
                    throw new Error(data.message || 'Erro ao carregar estatísticas');
                }
                
            } catch (error) {
                console.error('Erro ao carregar estatísticas:', error);
                notifications.show('Usando estatísticas locais', 'warning');
                mostrarEstatisticasLocais();
            }
        }

        function mostrarModalEstatisticas(stats) {
            const detalhesModal = new bootstrap.Modal(document.getElementById('detalhesModal'));
            const body = document.getElementById('detalhesModalBody');
            
            body.innerHTML = `
                <h5>Estatísticas Detalhadas do Sistema</h5>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Resumo Geral</h6>
                        <table class="table table-sm">
                            <tr><td>Total de Registros:</td><td>${formatNumber(stats.total_registros || 0)}</td></tr>
                            <tr><td>Ações Hoje:</td><td>${formatNumber(stats.acoes_hoje || 0)}</td></tr>
                            <tr><td>Usuários Ativos (24h):</td><td>${formatNumber(stats.usuarios_ativos || 0)}</td></tr>
                            <tr><td>Alertas:</td><td>${formatNumber(stats.alertas || 0)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Performance</h6>
                        <table class="table table-sm">
                            <tr><td>Cache Hit Rate:</td><td>${cache.getStats().hitRate}%</td></tr>
                            <tr><td>Updates Realizados:</td><td>${autoUpdater.updateCount}</td></tr>
                            <tr><td>Memória Utilizada:</td><td>${performanceMonitor.memory}MB</td></tr>
                            <tr><td>FPS:</td><td>${performanceMonitor.fps}</td></tr>
                        </table>
                    </div>
                </div>
            `;
            
            detalhesModal.show();
        }

        function mostrarEstatisticasLocais() {
            const stats = {
                total_registros: registrosAuditoria.length,
                usuarios_unicos: new Set(registrosAuditoria.map(r => r.funcionario_nome)).size,
                tabelas_afetadas: new Set(registrosAuditoria.map(r => r.tabela)).size,
                cache_stats: cache.getStats(),
                update_stats: autoUpdater.getStats()
            };
            
            mostrarModalEstatisticas(stats);
        }

        // ===== FUNÇÕES DE EVENTOS E UTILITÁRIOS =====

        function configurarEventos() {
            // Pausar auto-update quando modal estiver aberto
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', () => autoUpdater.pause());
                modal.addEventListener('hidden.bs.modal', () => {
                    if (localStorage.getItem('autoUpdate') !== 'false') {
                        autoUpdater.resume();
                    }
                });
            });
            
            // Pausar quando página não estiver visível
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    autoUpdater.pause();
                } else if (localStorage.getItem('autoUpdate') !== 'false') {
                    autoUpdater.resume();
                }
            });

            // Atalhos de teclado
            document.addEventListener('keydown', function(e) {
                // ESC para fechar modais
                if (e.key === 'Escape') {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        bootstrap.Modal.getInstance(modal)?.hide();
                    });
                }
                
                // Ctrl+R para atualizar
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    atualizarRegistros();
                }
                
                // Ctrl+F para focar na busca
                if (e.ctrlKey && e.key === 'f') {
                    e.preventDefault();
                    document.getElementById('searchInput')?.focus();
                }
                
                // Ctrl+E para exportar
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    exportarDados();
                }
            });

            // Eventos de mudança de tamanho da página
            window.addEventListener('resize', debounce(() => {
                if (chartAcoes) chartAcoes.resize();
                if (chartTipos) chartTipos.resize();
            }, 250));
        }

        function initializeCharts() {
            const ctxAcoes = document.getElementById('chartAcoesPorDia');
            const ctxTipos = document.getElementById('chartTiposAcao');
            
            if (ctxAcoes) {
                chartAcoes = new Chart(ctxAcoes.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Ações por Dia',
                            data: [],
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                display: true,
                                position: 'bottom'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: { 
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }

            if (ctxTipos) {
                chartTipos = new Chart(ctxTipos.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: [],
                        datasets: [{
                            data: [],
                            backgroundColor: [
                                '#3b82f6', '#10b981', '#f59e0b', 
                                '#ef4444', '#8b5cf6', '#06b6d4',
                                '#ec4899', '#14b8a6', '#f97316'
                            ],
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
                                labels: {
                                    padding: 15,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        function atualizarPercentuaisKPI() {
            const totalRegistros = parseInt(document.getElementById('totalRegistrosCard').textContent.replace(/\./g, '')) || 0;
            const acoesHoje = parseInt(document.getElementById('acoesHojeCard').textContent.replace(/\./g, '')) || 0;
            const usuariosAtivos = parseInt(document.getElementById('usuariosAtivosCard').textContent.replace(/\./g, '')) || 0;
            const alertas = parseInt(document.getElementById('alertasCard').textContent.replace(/\./g, '')) || 0;
            
            // Atualizar percentual de atividade
            const atividadePercent = totalRegistros > 0 ? Math.round((acoesHoje / totalRegistros) * 100) : 0;
            const atividadeEl = document.getElementById('atividadePercent');
            if (atividadeEl) {
                atividadeEl.innerHTML = `
                    <i class="fas fa-chart-line"></i>
                    <span>${atividadePercent}%</span>
                `;
            }
            
            // Atualizar status de segurança
            const segurancaStatus = alertas === 0 ? 'Seguro' : alertas < 5 ? 'Atenção' : 'Crítico';
            const segurancaClass = alertas === 0 ? 'success' : alertas < 5 ? 'warning' : 'danger';
            const monitoramentoEl = document.getElementById('monitoramentoPercent');
            if (monitoramentoEl) {
                monitoramentoEl.innerHTML = `
                    <i class="fas fa-shield-alt"></i>
                    <span class="text-${segurancaClass}">${segurancaStatus}</span>
                `;
            }
            
            // Atualizar tendência de performance
            const trend = acoesHoje > 0 ? '+' + Math.round((acoesHoje / Math.max(usuariosAtivos, 1)) * 10) : '0';
            const performanceEl = document.getElementById('performancePercent');
            if (performanceEl) {
                performanceEl.innerHTML = `
                    <i class="fas fa-trending-${acoesHoje > 0 ? 'up' : 'down'}"></i>
                    <span>${trend}%</span>
                `;
            }
        }

        function irParaPagina(page) {
            if (page >= 1 && page <= totalPages) {
                currentPage = page;
                const filters = obterFiltrosAtuais();
                carregarRegistrosAuditoria(page, filters);
                
                // Scroll to top of table
                document.querySelector('.documents-section')?.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }

        function mudarRegistrosPorPagina() {
            const select = document.getElementById('registrosPorPagina');
            recordsPerPage = parseInt(select.value);
            currentPage = 1;
            carregarRegistrosAuditoria(1, obterFiltrosAtuais());
        }

        function atualizarRegistros() {
            cache.clear();
            carregarRegistrosAuditoria(currentPage, { ...obterFiltrosAtuais(), noCache: true });
            atualizarEstatisticas();
            
            notifications.show('Registros atualizados!', 'success');
        }

        function imprimirRelatorio() {
            window.print();
        }

        function updateCacheStatus() {
            const stats = cache.getStats();
            const statusEl = document.getElementById('cacheInfo');
            if (statusEl) {
                statusEl.textContent = `${stats.size} items (${stats.hitRate}% hit)`;
            }
            
            // Mostrar status temporariamente
            const cacheStatus = document.getElementById('cacheStatus');
            if (cacheStatus) {
                cacheStatus.classList.add('show');
                setTimeout(() => {
                    cacheStatus.classList.remove('show');
                }, 3000);
            }
        }

        // ===== LOG FINAL =====
        console.log('✅ Sistema de Auditoria v2.0 carregado com sucesso!');
        console.log('📊 Configurações:', CONFIG);
        console.log('💾 Cache ativo:', cache);
        console.log('🔄 Auto-update:', autoUpdater);
        console.log('📈 Performance monitor:', performanceMonitor);
    </script>
</body>
</html>