<?php
/**
 * Página de Serviços Comerciais - Sistema ASSEGO
 * pages/servicos_comercial.php
 * VERSÃO ATUALIZADA - Suporte a múltiplos RGs de diferentes corporações
 * LÓGICA DE PERMISSÕES: Estatísticas sempre visíveis, funcionalidades dependem de permissão
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
$page_title = 'Serviços Comerciais - ASSEGO';

// Verificar permissões para setor comercial - APENAS COMERCIAL E PRESIDÊNCIA
$temPermissaoComercial = false;
$motivoNegacao = '';
$isComercial = false;
$isPresidencia = false;
$departamentoUsuario = null;

error_log("=== DEBUG PERMISSÕES SERVIÇOS COMERCIAIS - RESTRITO ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

// Verificação de permissões: APENAS comercial (ID: 10) OU presidência (ID: 1)
if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    $departamentoUsuario = $deptId;
    
    if ($deptId == 10) { // Comercial
        $temPermissaoComercial = true;
        $isComercial = true;
        error_log("✅ Permissão concedida: Usuário pertence ao Setor Comercial (ID: 10)");
    } elseif ($deptId == 1) { // Presidência
        $temPermissaoComercial = true;
        $isPresidencia = true;
        error_log("✅ Permissão concedida: Usuário pertence à Presidência (ID: 1)");
    } else {
        $motivoNegacao = 'Acesso restrito EXCLUSIVAMENTE ao Setor Comercial e Presidência.';
        error_log("❌ Acesso negado. Departamento: '$deptId'. Permitido apenas: Comercial (ID: 10) ou Presidência (ID: 1)");
    }
} else {
    $motivoNegacao = 'Departamento não identificado no perfil do usuário.';
    error_log("❌ departamento_id não existe no array do usuário");
}

// Log final do resultado
if (!$temPermissaoComercial) {
    error_log("❌ ACESSO NEGADO AOS SERVIÇOS COMERCIAIS: " . $motivoNegacao);
} else {
    error_log("✅ ACESSO PERMITIDO - Usuário " . ($isComercial ? 'do Comercial' : 'da Presidência'));
}

// Busca estatísticas do setor comercial (SEMPRE VISÍVEIS - são apenas números gerais)
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Total de associados ativos (SEMPRE VISÍVEL)
    $sql = "SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $totalAssociadosAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Novos cadastros hoje (SEMPRE VISÍVEL) 
    // Baseado na estrutura real da tabela: usa data_aprovacao ou data_pre_cadastro
    try {
        // Primeiro tenta data_aprovacao (quando foi aprovado o cadastro)
        $sql = "SELECT COUNT(*) as hoje FROM Associados WHERE DATE(data_aprovacao) = CURDATE()";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $cadastrosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];
        
        // Se não houver aprovações hoje, conta os pré-cadastros de hoje
        if ($cadastrosHoje == 0) {
            $sql = "SELECT COUNT(*) as hoje FROM Associados WHERE DATE(data_pre_cadastro) = CURDATE()";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $cadastrosHoje = $stmt->fetch(PDO::FETCH_ASSOC)['hoje'];
        }
        
        error_log("Cadastros hoje encontrados: " . $cadastrosHoje);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar cadastros hoje: " . $e->getMessage());
        $cadastrosHoje = 0;
    }
    
    // Pré-cadastros pendentes (SEMPRE VISÍVEL)
    // Baseado na estrutura real: pre_cadastro = 1 são pré-cadastros
    try {
        // Conta todos os pré-cadastros ainda não aprovados
        $sql = "SELECT COUNT(*) as pendentes FROM Associados WHERE pre_cadastro = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $preCadastrosPendentes = $stmt->fetch(PDO::FETCH_ASSOC)['pendentes'];
        
        error_log("Pré-cadastros pendentes encontrados: " . $preCadastrosPendentes);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar pré-cadastros pendentes: " . $e->getMessage());
        $preCadastrosPendentes = 0;
    }
    
    // Solicitações de desfiliação (último mês) - SEMPRE VISÍVEL
    try {
        // Verifica se a tabela Auditoria existe
        $sql = "SHOW TABLES LIKE 'Auditoria'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Tabela existe, busca desfiliações
            $sql = "SELECT COUNT(*) as desfiliacao FROM Auditoria 
                    WHERE acao = 'UPDATE' 
                    AND tabela = 'Associados'
                    AND JSON_EXTRACT(valores_novos, '$.situacao') = 'DESFILIADO'
                    AND data_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $desfiliacoesRecentes = $stmt->fetch(PDO::FETCH_ASSOC)['desfiliacao'];
            
            error_log("Desfiliações recentes (com Auditoria): " . $desfiliacoesRecentes);
        } else {
            // Tabela não existe, tenta contar diretamente por situação
            $sql = "SELECT COUNT(*) as desfiliacao FROM Associados 
                    WHERE situacao = 'DESFILIADO' 
                    AND data_aprovacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $desfiliacoesRecentes = $stmt->fetch(PDO::FETCH_ASSOC)['desfiliacao'];
            
            error_log("Desfiliações recentes (sem Auditoria): " . $desfiliacoesRecentes);
        }
        
    } catch (Exception $e) {
        error_log("Erro ao buscar desfiliações: " . $e->getMessage());
        $desfiliacoesRecentes = 0;
    }

} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas comerciais: " . $e->getMessage());
    $totalAssociadosAtivos = $cadastrosHoje = $preCadastrosPendentes = $desfiliacoesRecentes = 0;
}

// Debug final das estatísticas
error_log("=== ESTATÍSTICAS COMERCIAIS FINAIS ===");
error_log("Associados Ativos: " . $totalAssociadosAtivos);
error_log("Cadastros Hoje: " . $cadastrosHoje);
error_log("Pré-cadastros Pendentes: " . $preCadastrosPendentes);
error_log("Desfiliações (30 dias): " . $desfiliacoesRecentes);

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
            border-left: 4px solid var(--primary);
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(44, 90, 160, 0.15);
        }

        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.info { border-left-color: var(--info); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            font-size: 0.8rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive { color: var(--success); }
        .stat-change.negative { color: var(--danger); }
        .stat-change.neutral { color: var(--info); }

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

        .stat-icon.primary { background: var(--primary); color: var(--primary); }
        .stat-icon.success { background: var(--success); color: var(--success); }
        .stat-icon.warning { background: var(--warning); color: var(--warning); }
        .stat-icon.info { background: var(--info); color: var(--info); }

        /* Seções de Serviços */
        .services-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .service-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            overflow: hidden;
        }

        .service-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.5rem 2rem;
        }

        .service-header h3 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .service-header i {
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .service-content {
            padding: 2rem;
        }

        /* Seção de Desfiliação */
        .busca-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .busca-input-group {
            flex: 1;
            min-width: 200px;
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
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
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

        /* Modal de Seleção de Associados */
        .modal-selecao-associado {
            z-index: 9999;
        }

        .associado-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .associado-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 86, 210, 0.15);
        }

        .associado-card.selecionado {
            border-color: var(--success);
            background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
        }

        .associado-foto {
            width: 80px;
            height: 80px;
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

        /* Seção de Cadastro */
        .cadastro-options {
            display: grid;
            gap: 1.5rem;
        }

        .cadastro-option {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 2rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .cadastro-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .cadastro-option-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .cadastro-option h5 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .cadastro-option p {
            color: var(--secondary);
            margin: 0;
            font-size: 0.9rem;
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
            background: linear-gradient(135deg, var(--primary-light) 0%, #e3f2fd 100%);
            color: var(--primary-dark);
            border-left: 4px solid var(--primary);
        }

        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .alert-warning-custom {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border-left: 4px solid var(--warning);
        }

        /* Dados do associado */
        .dados-associado-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 2rem;
            margin-top: 2rem;
            border: 2px solid #e9ecef;
        }

        .dados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .dados-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .dados-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.1);
        }

        .dados-label {
            font-weight: 600;
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dados-value {
            color: var(--dark);
            font-size: 1rem;
            font-weight: 500;
            word-break: break-word;
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        /* Ficha de Desfiliação */
        .ficha-desfiliacao-container {
            background: white;
            border-radius: 15px;
            margin-top: 2rem;
            box-shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            overflow: hidden;
        }

        .ficha-header-container {
            background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
            color: white;
            padding: 1.5rem 2rem;
        }

        .ficha-content {
            padding: 3rem;
        }

        .ficha-desfiliacao {
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
            color: #333;
            font-size: 14px;
        }

        .ficha-title {
            text-align: center;
            font-size: 1.6rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary);
        }

        .campo-preenchimento {
            border-bottom: 1px solid #333;
            min-width: 150px;
            display: inline-block;
            padding: 2px 8px;
            margin: 0 3px;
            font-weight: bold;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-radius: 3px;
        }

        .campo-preenchimento.largo {
            min-width: 400px;
        }

        .campo-preenchimento.medio {
            min-width: 250px;
        }

        .motivo-area {
            border: 2px solid var(--primary);
            min-height: 100px;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            background: #f8f9fa;
            font-style: italic;
        }

        .assinatura-area {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #333;
        }

        .linha-assinatura {
            border-top: 2px solid #333;
            width: 300px;
            margin: 2rem auto 1rem;
            padding-top: 0.5rem;
            font-weight: bold;
        }

        .ficha-actions {
            padding: 2rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            text-align: center;
        }

        .btn-imprimir {
            background: var(--success);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            margin-right: 1rem;
        }

        .btn-imprimir:hover {
            background: #146c43;
            transform: translateY(-2px);
        }

        .btn-gerar-pdf {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem 3rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-gerar-pdf:hover {
            background: #b02a37;
            transform: translateY(-2px);
        }

        /* Toast personalizado */
        .toast-container {
            z-index: 9999;
        }

        .toast {
            min-width: 300px;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .services-container {
                grid-template-columns: 1fr;
            }
            
            .busca-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .dados-grid {
                grid-template-columns: 1fr;
            }
            
            .ficha-content {
                padding: 2rem 1.5rem;
            }
        }

        /* Modo impressão */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .ficha-desfiliacao-container {
                box-shadow: none;
                border: 2px solid #000;
            }
            
            .ficha-content {
                padding: 2rem;
            }
        }

        /* Animações */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
            <?php if (!$temPermissaoComercial): ?>
            <!-- Sem Permissão -->
            <div class="alert alert-danger" data-aos="fade-up">
                <h4><i class="fas fa-ban me-2"></i>Acesso Negado aos Serviços Comerciais</h4>
                <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                
                <a href="../pages/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Voltar ao Dashboard
                </a>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    Serviços Comerciais
                    <?php if ($isComercial): ?>
                        <small class="text-muted">- Setor Comercial</small>
                    <?php elseif ($isPresidencia): ?>
                        <small class="text-muted">- Presidência</small>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    Gerencie desfiliações, cadastros de novos associados e demais serviços comerciais. Sistema preparado para múltiplos RGs de diferentes corporações.
                </p>
            </div>

            <!-- Estatísticas Comerciais - SEMPRE VISÍVEIS (independente de permissões) -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($totalAssociadosAtivos, 0, ',', '.'); ?></div>
                            <div class="stat-label">Associados Ativos</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-users"></i>
                                Base atual
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
                            <div class="stat-value"><?php echo number_format($cadastrosHoje, 0, ',', '.'); ?></div>
                            <div class="stat-label">Cadastros Hoje</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Novos cadastros
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($preCadastrosPendentes, 0, ',', '.'); ?></div>
                            <div class="stat-label">Pré-cadastros Pendentes</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-clock"></i>
                                Aguardando análise
                            </div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card info">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($desfiliacoesRecentes, 0, ',', '.'); ?></div>
                            <div class="stat-label">Desfiliações (30 dias)</div>
                            <div class="stat-change negative">
                                <i class="fas fa-user-times"></i>
                                Último mês
                            </div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert informativo sobre o nível de acesso -->
            <div class="alert-custom alert-info-custom" data-aos="fade-up">
                <div>
                    <i class="fas fa-<?php echo $isComercial ? 'handshake' : 'crown'; ?>"></i>
                </div>
                <div>
                    <h6 class="mb-1">
                        <?php if ($isComercial): ?>
                            <i class="fas fa-handshake text-primary"></i> Setor Comercial
                        <?php elseif ($isPresidencia): ?>
                            <i class="fas fa-crown text-warning"></i> Presidência
                        <?php endif; ?>
                    </h6>
                    <small>
                        <?php if ($isComercial): ?>
                            Você tem acesso completo aos serviços comerciais: estatísticas, desfiliações, cadastros e atendimento.
                        <?php elseif ($isPresidencia): ?>
                            Você tem acesso administrativo aos serviços comerciais como membro da presidência.
                        <?php else: ?>
                            Você pode visualizar as estatísticas, mas funcionalidades avançadas requerem permissão específica.
                        <?php endif; ?>
                        Sistema preparado para múltiplos RGs de diferentes corporações.
                    </small>
                </div>
            </div>

            <!-- Seções de Serviços -->
            <div class="services-container" data-aos="fade-up" data-aos-delay="200">
                
                <!-- Seção de Desfiliação -->
                <div class="service-section">
                    <div class="service-header">
                        <h3>
                            <i class="fas fa-user-times"></i>
                            Solicitação de Desfiliação
                        </h3>
                    </div>
                    <div class="service-content" style="position: relative;">
                        <p class="text-muted mb-3">
                            Busque um associado pelo RG militar ou nome e gere automaticamente a ficha de desfiliação. Sistema preparado para múltiplos RGs de diferentes corporações.
                        </p>
                        
                        <form class="busca-form" onsubmit="buscarAssociadoPorRG(event)">
                            <div class="busca-input-group">
                                <label class="form-label" for="rgBuscaComercial">
                                    <i class="fas fa-id-card me-1"></i>
                                    RG Militar ou Nome
                                </label>
                                <input type="text" class="form-control" id="rgBuscaComercial" 
                                       placeholder="Digite o RG militar ou nome..." required>
                                <small class="text-muted">
                                    Se houver múltiplos registros com o mesmo RG, você poderá escolher a corporação correta
                                </small>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary" id="btnBuscarComercial">
                                    <i class="fas fa-search me-2"></i>
                                    Buscar Associado
                                </button>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary" onclick="limparBuscaComercial()">
                                    <i class="fas fa-eraser me-2"></i>
                                    Limpar
                                </button>
                            </div>
                        </form>

                        <!-- Alert para mensagens de busca -->
                        <div id="alertBuscaComercial" class="alert" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="alertBuscaComercialText"></span>
                        </div>

                        <!-- Container para dados do associado -->
                        <div id="dadosAssociadoContainer" class="dados-associado-container fade-in" style="display: none;">
                            <h6 class="mb-3">
                                <i class="fas fa-user me-2" style="color: var(--primary);"></i>
                                Dados do Associado Encontrado
                            </h6>
                            
                            <!-- Identificação Militar -->
                            <div id="identificacaoMilitarComercial" class="identificacao-militar" style="display: none;">
                                <h6>
                                    <i class="fas fa-shield-alt me-2"></i>
                                    Identificação Militar
                                </h6>
                                <div class="militar-info-grid" id="militarInfoGridComercial">
                                    <!-- Dados militares serão inseridos aqui -->
                                </div>
                            </div>
                            
                            <div class="dados-grid" id="dadosAssociadoGrid">
                                <!-- Dados serão inseridos aqui dinamicamente -->
                            </div>
                        </div>

                        <!-- Loading overlay -->
                        <div id="loadingBuscaComercial" class="loading-overlay" style="display: none;">
                            <div class="loading-spinner mb-3"></div>
                            <p class="text-muted">Buscando dados do associado...</p>
                        </div>
                    </div>
                </div>

                <!-- Seção de Cadastro de Associados -->
                <div class="service-section">
                    <div class="service-header">
                        <h3>
                            <i class="fas fa-user-plus"></i>
                            Cadastro de Associados
                        </h3>
                    </div>
                    <div class="service-content">
                        <p class="text-muted mb-4">
                            Inicie novos cadastros de associados ou gerencie pré-cadastros existentes.
                        </p>
                        
                        <div class="cadastro-options">
                            <div class="cadastro-option" onclick="novoPreCadastro()">
                                <div class="cadastro-option-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <h5>Nova Filiação</h5>
                                <p>Inicie um nova filiação de associado com formulário completo</p>
                            </div>

                            <div class="cadastro-option" onclick="consultarAssociado()">
                                <div class="cadastro-option-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h5>Consultar Associado</h5>
                                <p>Busque e consulte dados de associados existentes</p>
                            </div>

                            <div class="cadastro-option" onclick="consultarDependentes18()">
                                <div class="cadastro-option-icon">
                                    <i class="fas fa-birthday-cake"></i>
                                </div>
                                <h5>Dependentes 18+</h5>
                                <p>Veja os dependentes que já completaram ou estão prestes a completar 18 anos</p>
                            </div>

                            <div class="cadastro-option" onclick="relatoriosComerciais()">
                                <div class="cadastro-option-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h5>Relatórios Comerciais</h5>
                                <p>Visualize estatísticas e relatórios do setor comercial</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Container para ficha de desfiliação (apenas com permissão) -->
            <?php if ($temPermissaoComercial): ?>
            <div id="fichaDesfiliacao" class="ficha-desfiliacao-container fade-in" style="display: none;" data-aos="fade-up">
                <div class="ficha-header-container no-print">
                    <h4>
                        <i class="fas fa-file-alt me-2"></i>
                        Ficha de Desfiliação - ASSEGO
                    </h4>
                    <p class="mb-0">Documento oficial preenchido automaticamente</p>
                </div>

                <div class="ficha-content">
                    <div class="ficha-desfiliacao">
                        <div class="ficha-title">
                            SOLICITAÇÃO DE DESFILIAÇÃO<br>
                            ASSEGO
                        </div>

                        <p>
                            Goiânia, <span class="campo-preenchimento" id="diaAtual"></span> de 
                            <span class="campo-preenchimento" id="mesAtual"></span> de 
                            <span class="campo-preenchimento" id="anoAtual"></span>
                        </p>

                        <br>

                        <p><strong>Prezado Sr. Presidente,</strong></p>

                        <br>

                        <p>
                            Eu, <span class="campo-preenchimento largo" id="nomeCompleto" contenteditable="true"></span>,
                            portador do RG militar: <span class="campo-preenchimento" id="rgMilitar" contenteditable="true"></span>, 
                            Instituição: <span class="campo-preenchimento medio" id="corporacao" contenteditable="true"></span>,
                            residente e domiciliado: 
                            <span class="campo-preenchimento largo" id="endereco1" contenteditable="true"></span>
                        </p>

                        <p>
                            <span class="campo-preenchimento largo" id="endereco2" contenteditable="true"></span>
                        </p>

                        <p>
                            <span class="campo-preenchimento largo" id="endereco3" contenteditable="true"></span>,
                            telefone <span class="campo-preenchimento" id="telefoneFormatado" contenteditable="true"></span>, 
                            Lotação: <span class="campo-preenchimento medio" id="lotacao" contenteditable="true"></span>,
                            solicito minha desfiliação total da Associação dos Subtenentes e Sargentos do Estado
                            de Goiás – ASSEGO, pelo motivo:
                        </p>

                        <div class="motivo-area" contenteditable="true" id="motivoDesfiliacao">
                            Clique aqui para digitar o motivo da desfiliação...
                        </div>

                        <br>

                        <p>
                            Me coloco à disposição, através do telefone informado acima para informações
                            adicionais necessárias à conclusão deste processo e, desde já, <strong>DECLARO ESTAR 
                            CIENTE QUE O PROCESSO INTERNO TEM UM PRAZO DE ATÉ 30 DIAS, A CONTAR DA 
                            DATA DE SOLICITAÇÃO, PARA SER CONCLUÍDO.</strong>
                        </p>

                        <br>

                        <p><strong>Respeitosamente,</strong></p>

                        <div class="assinatura-area">
                            <div class="linha-assinatura">
                                Assinatura do requerente
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de ação -->
                <div class="ficha-actions no-print">
                    <button class="btn-imprimir" onclick="imprimirFicha()">
                        <i class="fas fa-print me-2"></i>
                        Imprimir Ficha
                    </button>
                    <button class="btn-gerar-pdf" onclick="gerarPDFFicha()">
                        <i class="fas fa-file-pdf me-2"></i>
                        Gerar PDF
                    </button>
                </div>
            </div>

            <?php else: ?>
            <!-- Sem permissão - Apenas estatísticas visíveis -->
            <div class="alert alert-warning" data-aos="fade-up">
                <h5><i class="fas fa-lock me-2"></i>Funcionalidades Restritas</h5>
                <p class="mb-2">
                    Você pode visualizar as estatísticas comerciais, mas não tem permissão para acessar as funcionalidades avançadas.
                </p>
                <small class="text-muted">
                    <strong>Para acesso completo:</strong> Entre em contato com o administrador para obter permissões do setor comercial ou presidência.
                </small>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Seleção de Associado (apenas com permissão) -->
    <?php if ($temPermissaoComercial): ?>
    <div class="modal fade modal-selecao-associado" id="modalSelecaoAssociadoComercial" tabindex="-1">
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
                        Selecione o associado correto para visualizar os dados e gerar a ficha de desfiliação.
                    </div>
                    <div id="listaAssociadosSelecaoComercial">
                        <!-- Lista de associados será inserida aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarSelecaoComercial" disabled>
                        <i class="fas fa-check me-2"></i>
                        Confirmar Seleção
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

        // ===== VARIÁVEIS GLOBAIS =====
        const notifications = new NotificationSystem();
        let dadosAssociadoAtual = null;
        let associadoSelecionadoId = null;
        let listaAssociadosMultiplos = [];
        const temPermissao = <?php echo json_encode($temPermissaoComercial); ?>;
        const isComercial = <?php echo json_encode($isComercial); ?>;
        const isPresidencia = <?php echo json_encode($isPresidencia); ?>;
        const departamentoUsuario = <?php echo json_encode($departamentoUsuario); ?>;

        // ===== IMPORTANTE: API UTILIZADA =====
        // Este módulo comercial utiliza a API atualizada buscar_por_rg.php que agora possui:
        // - Suporte a múltiplos RGs de diferentes corporações  
        // - Retorno de dados completos (pessoais, militares, endereço, financeiros)
        // - Tratamento de múltiplos resultados com status 'multiple_results'
        // - Busca por RG, CPF, nome ou ID específico
        // - Sistema de alertas e dados estruturados
        // - Compatibilidade total com o sistema de múltiplos associados
        // 
        // A API foi atualizada especificamente para suportar essas funcionalidades avançadas.

        // ===== INICIALIZAÇÃO =====
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({ duration: 800, once: true });

            if (!temPermissao) {
                console.log('❌ Usuário sem permissão - funcionalidades restritas, mas estatísticas visíveis');
                // Note: Estatísticas são sempre visíveis, apenas funcionalidades são restritas
                configurarEventos(); // Configura eventos básicos mesmo sem permissão
                preencherDataAtual(); // Preenche data atual sempre
                notifications.show('Acesso limitado - apenas visualização de estatísticas!', 'warning', 4000);
                return;
            }

            preencherDataAtual();
            configurarEventos();
            <?php if ($temPermissaoComercial): ?>
            configurarFichaDesfiliacao();
            <?php endif; ?>

            // Event listener para Enter no campo de busca
            <?php if ($temPermissaoComercial): ?>
            $('#rgBuscaComercial').on('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarAssociadoPorRG(e);
                }
            });

            // Event listener para o botão de confirmar seleção
            document.getElementById('btnConfirmarSelecaoComercial').addEventListener('click', buscarAssociadoSelecionado);
            <?php endif; ?>

            const departamentoNome = isComercial ? 'Comercial' : isPresidencia ? 'Presidência' : 'Outro';
            const nivelAcesso = temPermissao ? 'completo' : 'visualização apenas';
            notifications.show(`Serviços comerciais - ${departamentoNome} (${nivelAcesso})!`, temPermissao ? 'success' : 'info', 3000);
        });

        // ===== FUNÇÕES DE BUSCA (ATUALIZADAS PARA MÚLTIPLOS ASSOCIADOS) =====

        // Buscar associado por RG - ATUALIZADA para suportar múltiplos resultados
        async function buscarAssociadoPorRG(event) {
            event.preventDefault();
            
            // Verifica permissão
            if (!temPermissao) {
                notifications.show('Você não tem permissão para buscar associados', 'error');
                return;
            }
            
            const rgInput = document.getElementById('rgBuscaComercial');
            const busca = rgInput.value.trim();
            const btnBuscar = document.getElementById('btnBuscarComercial');
            const loadingOverlay = document.getElementById('loadingBuscaComercial');
            const dadosContainer = document.getElementById('dadosAssociadoContainer');
            const fichaContainer = document.getElementById('fichaDesfiliacao');
            
            if (!busca) {
                mostrarAlertaBuscaComercial('Por favor, digite um RG ou nome para buscar.', 'danger');
                return;
            }

            // Mostra loading
            loadingOverlay.style.display = 'flex';
            btnBuscar.disabled = true;
            dadosContainer.style.display = 'none';
            fichaContainer.style.display = 'none';
            esconderAlertaBuscaComercial();

            try {
                // IMPORTANTE: Usando a API atualizada buscar_por_rg.php que agora tem:
                // - Lógica de múltiplos resultados quando há RGs iguais em diferentes corporações
                // - Retorno de dados completos do associado (pessoais, militares, endereço, etc.)
                // - Suporte a busca por RG, nome, CPF ou ID específico
                // - Sistema de alertas e dados estruturados
                
                // Determina se é busca por RG ou nome
                const parametro = isNaN(busca) ? 'nome' : 'rg';
                const response = await fetch(`../api/associados/buscar_por_rg.php?${parametro}=${encodeURIComponent(busca)}`);
                const result = await response.json();

                if (result.status === 'multiple_results') {
                    // Múltiplos resultados encontrados
                    listaAssociadosMultiplos = result.data;
                    mostrarModalSelecaoComercial(result.data);
                    mostrarAlertaBuscaComercial('Múltiplos associados encontrados. Por favor, selecione o correto.', 'warning');
                } else if (result.status === 'success') {
                    // Um único resultado
                    dadosAssociadoAtual = result.data;
                    exibirDadosAssociado(dadosAssociadoAtual);
                    preencherFichaDesfiliacao(dadosAssociadoAtual);
                    
                    dadosContainer.style.display = 'block';
                    fichaContainer.style.display = 'block';
                    
                    mostrarAlertaBuscaComercial('Associado encontrado! Dados carregados e ficha preenchida automaticamente.', 'success');
                    
                    // Scroll suave até os dados
                    setTimeout(() => {
                        dadosContainer.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start' 
                        });
                    }, 300);
                } else {
                    mostrarAlertaBuscaComercial(result.message || 'Erro ao buscar dados', 'danger');
                }

            } catch (error) {
                console.error('Erro na busca comercial:', error);
                mostrarAlertaBuscaComercial('Erro ao buscar associado. Verifique sua conexão.', 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                btnBuscar.disabled = false;
            }
        }

        // NOVA FUNÇÃO - Mostrar modal de seleção
        function mostrarModalSelecaoComercial(associados) {
            const listaContainer = document.getElementById('listaAssociadosSelecaoComercial');
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
                        <input class="form-check-input" type="radio" name="associadoSelecionadoComercial" 
                               value="${assoc.id}" id="assoc_comercial_${assoc.id}">
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
                        ${assoc.situacao ? 
                            `<div class="mt-2">
                                <small class="text-muted">Situação: </small>
                                <span class="badge ${assoc.situacao === 'DESFILIADO' ? 'bg-danger' : 'bg-success'}">
                                    ${assoc.situacao}
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
                    document.getElementById('btnConfirmarSelecaoComercial').disabled = false;
                    associadoSelecionadoId = assoc.id;
                });

                listaContainer.appendChild(card);
            });

            // Mostra o modal
            const modal = new bootstrap.Modal(document.getElementById('modalSelecaoAssociadoComercial'));
            modal.show();
        }

        // NOVA FUNÇÃO - Buscar associado selecionado
        async function buscarAssociadoSelecionado() {
            if (!associadoSelecionadoId || !temPermissao) return;

            // Fecha o modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalSelecaoAssociadoComercial'));
            modal.hide();

            // Busca os dados do associado selecionado
            const loadingOverlay = document.getElementById('loadingBuscaComercial');
            const dadosContainer = document.getElementById('dadosAssociadoContainer');
            const fichaContainer = document.getElementById('fichaDesfiliacao');

            loadingOverlay.style.display = 'flex';

            try {
                const response = await fetch(`../api/associados/buscar_por_rg.php?id=${associadoSelecionadoId}`);
                const result = await response.json();

                if (result.status === 'success') {
                    dadosAssociadoAtual = result.data;
                    exibirDadosAssociado(result.data);
                    preencherFichaDesfiliacao(result.data);
                    
                    dadosContainer.style.display = 'block';
                    fichaContainer.style.display = 'block';
                    
                    mostrarAlertaBuscaComercial('Dados carregados e ficha preenchida automaticamente!', 'success');

                    // Scroll suave até os dados
                    setTimeout(() => {
                        dadosContainer.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 300);
                } else {
                    mostrarAlertaBuscaComercial(result.message || 'Erro ao buscar dados', 'danger');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarAlertaBuscaComercial('Erro ao consultar dados do associado.', 'danger');
            } finally {
                loadingOverlay.style.display = 'none';
                // Reset seleção
                associadoSelecionadoId = null;
                document.getElementById('btnConfirmarSelecaoComercial').disabled = true;
            }
        }

        // Exibir dados do associado - ATUALIZADA
        function exibirDadosAssociado(dados) {
            const grid = document.getElementById('dadosAssociadoGrid');
            const militarContainer = document.getElementById('identificacaoMilitarComercial');
            const militarGrid = document.getElementById('militarInfoGridComercial');

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
            function criarDadosItem(label, value, icone = 'fa-info') {
                if (!value || value === 'null' || value === '') return '';
                
                return `
                    <div class="dados-item">
                        <div class="dados-label">
                            <i class="fas ${icone} me-1"></i>
                            ${label}
                        </div>
                        <div class="dados-value">${value}</div>
                    </div>
                `;
            }

            // Dados pessoais
            const pessoais = dados.dados_pessoais || {};
            grid.innerHTML += criarDadosItem('Nome Completo', pessoais.nome, 'fa-user');
            grid.innerHTML += criarDadosItem('RG Militar', pessoais.rg, 'fa-id-card');
            grid.innerHTML += criarDadosItem('CPF', formatarCPF(pessoais.cpf), 'fa-id-badge');
            grid.innerHTML += criarDadosItem('Data Nascimento', formatarData(pessoais.data_nascimento), 'fa-calendar');
            grid.innerHTML += criarDadosItem('Email', pessoais.email, 'fa-envelope');
            grid.innerHTML += criarDadosItem('Telefone', formatarTelefone(pessoais.telefone), 'fa-phone');

            // Dados militares
            const militares = dados.dados_militares || {};
            grid.innerHTML += criarDadosItem('Corporação', militares.corporacao, 'fa-shield-alt');
            grid.innerHTML += criarDadosItem('Patente', militares.patente, 'fa-medal');
            grid.innerHTML += criarDadosItem('Lotação', militares.lotacao, 'fa-building');
            grid.innerHTML += criarDadosItem('Unidade', militares.unidade, 'fa-map-marker-alt');

            // Endereço
            const endereco = dados.endereco || {};
            if (endereco.endereco) {
                const enderecoCompleto = [
                    endereco.endereco,
                    endereco.numero ? `nº ${endereco.numero}` : '',
                    endereco.bairro,
                    endereco.cidade
                ].filter(Boolean).join(', ');
                
                grid.innerHTML += criarDadosItem('Endereço', enderecoCompleto, 'fa-home');
            }
            grid.innerHTML += criarDadosItem('CEP', formatarCEP(endereco.cep), 'fa-map-pin');

            // Dados financeiros
            const financeiros = dados.dados_financeiros || {};
            grid.innerHTML += criarDadosItem('Tipo Associado', financeiros.tipo_associado, 'fa-user-tag');
            grid.innerHTML += criarDadosItem('Situação Financeira', financeiros.situacao_financeira, 'fa-dollar-sign');
            
            // Contrato
            const contrato = dados.contrato || {};
            grid.innerHTML += criarDadosItem('Data Filiação', formatarData(contrato.data_filiacao), 'fa-handshake');
            
            // Status
            const statusBadge = dados.status_cadastro === 'PRE_CADASTRO' 
                ? '<span class="badge bg-warning">Pré-cadastro</span>'
                : '<span class="badge bg-success">Cadastro Definitivo</span>';
            grid.innerHTML += `
                <div class="dados-item">
                    <div class="dados-label">
                        <i class="fas fa-info-circle me-1"></i>
                        Status do Cadastro
                    </div>
                    <div class="dados-value">${statusBadge}</div>
                </div>
            `;
        }

        // ===== FUNÇÕES DE DESFILIAÇÃO =====

        // Preencher data atual
        function preencherDataAtual() {
            const hoje = new Date();
            const dia = hoje.getDate();
            const meses = [
                'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'
            ];
            const mes = meses[hoje.getMonth()];
            const ano = hoje.getFullYear();

            document.getElementById('diaAtual').textContent = dia.toString().padStart(2, '0');
            document.getElementById('mesAtual').textContent = mes;
            document.getElementById('anoAtual').textContent = ano.toString();
        }

        // Preencher ficha de desfiliação
        function preencherFichaDesfiliacao(dados) {
            // Dados pessoais
            const pessoais = dados.dados_pessoais || {};
            document.getElementById('nomeCompleto').textContent = pessoais.nome || '';
            document.getElementById('rgMilitar').textContent = pessoais.rg || '';
            document.getElementById('telefoneFormatado').textContent = formatarTelefone(pessoais.telefone) || '';

            // Dados militares
            const militares = dados.dados_militares || {};
            document.getElementById('corporacao').textContent = militares.corporacao || '';
            document.getElementById('lotacao').textContent = militares.lotacao || '';

            // Endereço
            const endereco = dados.endereco || {};
            const enderecoCompleto = montarEnderecoCompleto(endereco);
            
            // Divide o endereço em até 3 linhas
            const linhas = quebrarEnderecoEmLinhas(enderecoCompleto);
            document.getElementById('endereco1').textContent = linhas[0] || '';
            document.getElementById('endereco2').textContent = linhas[1] || '';
            document.getElementById('endereco3').textContent = linhas[2] || '';

            // Limpa o motivo para o usuário digitar
            document.getElementById('motivoDesfiliacao').textContent = '';
        }

        // Montar endereço completo
        function montarEnderecoCompleto(endereco) {
            const partes = [];
            
            if (endereco.endereco) {
                let linha = endereco.endereco;
                if (endereco.numero) linha += `, nº ${endereco.numero}`;
                if (endereco.complemento) linha += `, ${endereco.complemento}`;
                partes.push(linha);
            }
            
            if (endereco.bairro) {
                partes.push(`Bairro: ${endereco.bairro}`);
            }
            
            if (endereco.cidade) {
                let cidade = endereco.cidade;
                if (endereco.cep) cidade += ` - CEP: ${formatarCEP(endereco.cep)}`;
                partes.push(cidade);
            }
            
            return partes.join(', ');
        }

        // Quebrar endereço em linhas
        function quebrarEnderecoEmLinhas(enderecoCompleto, maxPorLinha = 60) {
            if (!enderecoCompleto) return ['', '', ''];
            
            const palavras = enderecoCompleto.split(' ');
            const linhas = [];
            let linhaAtual = '';
            
            for (const palavra of palavras) {
                if ((linhaAtual + ' ' + palavra).length <= maxPorLinha) {
                    linhaAtual += (linhaAtual ? ' ' : '') + palavra;
                } else {
                    if (linhaAtual) {
                        linhas.push(linhaAtual);
                        linhaAtual = palavra;
                    } else {
                        linhas.push(palavra);
                    }
                }
            }
            
            if (linhaAtual) linhas.push(linhaAtual);
            
            // Garante 3 linhas
            while (linhas.length < 3) {
                linhas.push('');
            }
            
            return linhas.slice(0, 3);
        }

        // Configurar ficha de desfiliação
        function configurarFichaDesfiliacao() {
            // Só configura se tiver permissão e os elementos existirem
            if (!temPermissao) return;
            
            // Limpar placeholder do motivo ao clicar
            const motivoArea = document.getElementById('motivoDesfiliacao');
            
            if (motivoArea) {
                motivoArea.addEventListener('focus', function() {
                    if (this.textContent === 'Clique aqui para digitar o motivo da desfiliação...') {
                        this.textContent = '';
                    }
                });

                // Restaurar placeholder se vazio
                motivoArea.addEventListener('blur', function() {
                    if (this.textContent.trim() === '') {
                        this.textContent = 'Clique aqui para digitar o motivo da desfiliação...';
                    }
                });
            }
        }

        // Limpar busca comercial
        function limparBuscaComercial() {
            if (!temPermissao) {
                notifications.show('Você não tem permissão para esta funcionalidade', 'error');
                return;
            }
            document.getElementById('rgBuscaComercial').value = '';
            document.getElementById('dadosAssociadoContainer').style.display = 'none';
            document.getElementById('fichaDesfiliacao').style.display = 'none';
            document.getElementById('dadosAssociadoGrid').innerHTML = '';
            document.getElementById('identificacaoMilitarComercial').style.display = 'none';
            dadosAssociadoAtual = null;
            associadoSelecionadoId = null;
            esconderAlertaBuscaComercial();

            // Limpa campos da ficha
            const campos = [
                'nomeCompleto', 'rgMilitar', 'corporacao', 'endereco1', 
                'endereco2', 'endereco3', 'telefoneFormatado', 'lotacao'
            ];
            
            campos.forEach(campo => {
                const elemento = document.getElementById(campo);
                if (elemento) elemento.textContent = '';
            });
            
            // Restaura placeholder do motivo
            const motivoArea = document.getElementById('motivoDesfiliacao');
            if (motivoArea) {
                motivoArea.textContent = 'Clique aqui para digitar o motivo da desfiliação...';
            }
        }

        // Mostrar alerta de busca comercial
        function mostrarAlertaBuscaComercial(mensagem, tipo) {
            const alertDiv = document.getElementById('alertBuscaComercial');
            const alertText = document.getElementById('alertBuscaComercialText');
            
            // Só mostra se os elementos existirem (ou seja, se tiver permissão)
            if (!alertDiv || !alertText) return;
            
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
                setTimeout(esconderAlertaBuscaComercial, 5000);
            }
        }

        // Esconder alerta de busca comercial
        function esconderAlertaBuscaComercial() {
            const alertDiv = document.getElementById('alertBuscaComercial');
            if (alertDiv) {
                alertDiv.style.display = 'none';
            }
        }

        // Imprimir ficha
        function imprimirFicha() {
            if (!temPermissao) {
                notifications.show('Você não tem permissão para esta funcionalidade', 'error');
                return;
            }
            
            // Verifica se os campos obrigatórios estão preenchidos
            const nome = document.getElementById('nomeCompleto')?.textContent?.trim();
            const rg = document.getElementById('rgMilitar')?.textContent?.trim();
            const motivo = document.getElementById('motivoDesfiliacao')?.textContent?.trim();
            
            if (!nome || !rg) {
                mostrarAlertaBuscaComercial('Por favor, busque um associado antes de imprimir.', 'danger');
                return;
            }
            
            if (!motivo || motivo === 'Clique aqui para digitar o motivo da desfiliação...') {
                mostrarAlertaBuscaComercial('Por favor, informe o motivo da desfiliação antes de imprimir.', 'danger');
                return;
            }
            
            window.print();
        }

        // Gerar PDF
        function gerarPDFFicha() {
            if (!temPermissao) {
                notifications.show('Você não tem permissão para esta funcionalidade', 'error');
                return;
            }
            notifications.show('Funcionalidade de geração de PDF será implementada em breve.', 'info');
        }

        // ===== FUNÇÕES DE CADASTRO =====

        // Novo pré-cadastro
        function novoPreCadastro() {
            if (!temPermissao) {
                notifications.show('Você não tem permissão para esta funcionalidade', 'error');
                return;
            }
            notifications.show('Redirecionando para novo pré-cadastro...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/cadastroForm.php';
            }, 1000);
        }

        // Consultar associado
        function consultarAssociado() {
            if (!temPermissao) {
                notifications.show('Você não tem permissão para esta funcionalidade', 'error');
                return;
            }
            notifications.show('Abrindo consulta de associados...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/dashboard.php';
            }, 1000);
        }

        // Consultar dependentes com 18 anos
        function consultarDependentes18() {
            if (!temPermissao) {
                notifications.show('Você não tem permissão para esta funcionalidade', 'error');
                return;
            }
            notifications.show('Carregando dependentes 18+...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/dependentes_18anos.php';
            }, 1000);
        }

        // Relatórios comerciais
        function relatoriosComerciais() {
            if (!temPermissao) {
                notifications.show('Você não tem permissão para esta funcionalidade', 'error');
                return;
            }
            notifications.show('Carregando relatórios comerciais...', 'info');
            setTimeout(() => {
                window.location.href = '../pages/relatorios.php';
            }, 1000);
        }

        // ===== FUNÇÕES AUXILIARES =====

        // Configurar eventos
        function configurarEventos() {
            // Outros event listeners se necessário
        }

        // Funções auxiliares de formatação
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

        function formatarCEP(cep) {
            if (!cep) return '';
            cep = cep.toString().replace(/\D/g, '');
            if (cep.length === 8) {
                return cep.replace(/(\d{5})(\d{3})/, "$1-$2");
            }
            return cep;
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

        // Log de inicialização
        console.log('✓ Sistema de Serviços Comerciais carregado com sucesso!');
        console.log(`🏢 Departamento: ${isComercial ? 'Comercial (ID: 10)' : isPresidencia ? 'Presidência (ID: 1)' : 'Outro'}`);
        console.log(`🔐 Funcionalidades: ${temPermissao ? 'Liberadas' : 'Restritas'}`);
        console.log(`📊 Estatísticas: Sempre visíveis (independente de permissões)`);
        console.log(`📋 Suporte a múltiplos RGs de diferentes corporações ativado`);
    </script>

</body>

</html>