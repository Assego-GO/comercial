<?php

/**
 * Página de Gerenciamento do Fluxo de Assinatura - VERSÃO UNIFICADA
 * pages/documentos_fluxo.php
 * 
 * Esta página gerencia o fluxo de assinatura dos documentos
 * anexados durante o pré-cadastro (SÓCIOS E AGREGADOS)
 * 
 * MODIFICADO: Integração com API unificada documentos_unificados_listar.php
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Documentos.php';
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
$page_title = 'Fluxo de Assinatura - ASSEGO';

// Busca estatísticas de documentos em fluxo
try {
    $documentos = new Documentos();
    $statsFluxo = $documentos->getEstatisticasFluxo();
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de fluxo: " . $e->getMessage());
}

// Organizar dados do fluxo
$aguardandoEnvio = 0;
$naPresidencia = 0;
$assinados = 0;
$finalizados = 0;

if (isset($statsFluxo['por_status'])) {
    foreach ($statsFluxo['por_status'] as $status) {
        switch ($status['status_fluxo']) {
            case 'DIGITALIZADO':
                $aguardandoEnvio = $status['total'] ?? 0;
                break;
            case 'AGUARDANDO_ASSINATURA':
                $naPresidencia = $status['total'] ?? 0;
                break;
            case 'ASSINADO':
                $assinados = $status['total'] ?? 0;
                break;
            case 'FINALIZADO':
                $finalizados = $status['total'] ?? 0;
                break;
        }
    }
}

// Buscar estatísticas de desfiliação
$desfiliacao_stats = [
    'aguardando_financeiro' => 0,
    'aguardando_juridico' => 0,
    'aguardando_presidencia' => 0,
    'finalizadas' => 0,
    'rejeitadas' => 0
];

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Contar por status de aprovação
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN ad.departamento_id = 2 AND ad.status_aprovacao = 'PENDENTE' THEN 1 ELSE 0 END) as aguardando_financeiro,
            SUM(CASE WHEN ad.departamento_id = 3 AND ad.status_aprovacao = 'PENDENTE' THEN 1 ELSE 0 END) as aguardando_juridico,
            SUM(CASE WHEN ad.departamento_id = 1 AND ad.status_aprovacao = 'PENDENTE' THEN 1 ELSE 0 END) as aguardando_presidencia,
            SUM(CASE WHEN da.status_fluxo = 'FINALIZADO' THEN 1 ELSE 0 END) as finalizadas,
            SUM(CASE WHEN ad.status_aprovacao = 'REJEITADO' THEN 1 ELSE 0 END) as rejeitadas
        FROM Documentos_Associado da
        LEFT JOIN Aprovacoes_Desfiliacao ad ON da.id = ad.documento_id
        WHERE da.tipo_documento = 'ficha_desfiliacao'
        AND da.deletado = 0
    ");
    $stmt->execute();
    $desfiliacao_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas de desfiliação: " . $e->getMessage());
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'documentos',
    'notificationCount' => $aguardandoEnvio,
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./estilizacao/style.css">

    <!-- Estilos Personalizados Premium -->
    <style>
       /* === VARIÁVEIS CSS === */
:root {
    --primary: #0056d2;
    --primary-light: #4A90E2;
    --primary-dark: #003d94;
    --secondary: #6c757d;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #ffc107;
    --info: #17a2b8;
    --dark: #343a40;
    --white: #ffffff;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --border-light: #e9ecef;
    --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    --shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    /* NOVO: Cores para tipos de documento */
    --socio-color: #0056d2;
    --socio-light: rgba(0, 86, 210, 0.1);
    --agregado-color: #6f42c1;
    --agregado-light: rgba(111, 66, 193, 0.1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--gray-100);
    min-height: 100vh;
    position: relative;
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
    animation: fadeIn 0.5s ease-in-out;
}

/* Page Header */
.page-header {
    margin-bottom: 2rem;
    padding: 0 0 1rem 0;
    position: relative;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title i {
    color: var(--primary);
}

.page-subtitle {
    color: var(--gray-600);
    font-size: 1rem;
    font-weight: 500;
    margin: 0;
}

/* === STATS GRID === */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* === DUAL STAT CARDS === */
.stat-card, .dual-stat-card {
    position: relative;
    overflow: visible;
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 20px;
    padding: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
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
    box-shadow: var(--shadow-lg);
    border-color: rgba(0, 86, 210, 0.2);
}

/* Header do Card */
.dual-stat-header {
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dual-stat-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: var(--gray-700);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.dual-stat-percentage {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--primary);
    background: rgba(74, 144, 226, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.dual-stat-percentage.warning {
    color: var(--warning);
    background: rgba(255, 193, 7, 0.1);
}

/* Layout dos Stats */
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
    animation: float 4s ease-in-out infinite;
    color: white;
}

.dual-stat-item:hover .dual-stat-icon {
    animation: none;
    transform: scale(1.1) rotate(5deg);
}

.dual-stat-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    text-align: center;
    align-items: center;
}

.dual-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--dark);
    line-height: 1;
    margin-bottom: 0.25rem;
    transition: all 0.3s ease;
}

.dual-stat-label {
    font-size: 0.875rem;
    color: var(--gray-600);
    font-weight: 600;
    line-height: 1;
}

/* Separador vertical */
.dual-stats-separator {
    width: 1px;
    background: linear-gradient(to bottom, transparent, var(--gray-300), transparent);
    margin: 1.5rem 0;
    flex-shrink: 0;
}

/* Cores dos ícones específicos para documentos */
.aguardando-icon {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
}

.presidencia-icon {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.assinados-icon {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.finalizados-icon {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

/* NOVO: Ícones para sócios e agregados */
.socios-icon {
    background: linear-gradient(135deg, var(--socio-color) 0%, #003d94 100%);
}

.agregados-icon {
    background: linear-gradient(135deg, var(--agregado-color) 0%, #5a32a3 100%);
}

/* Efeitos hover específicos */
.dual-stat-item:hover .aguardando-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 25px rgba(23, 162, 184, 0.4);
}

.dual-stat-item:hover .presidencia-icon {
    transform: scale(1.1) rotate(-5deg);
    box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
}

.dual-stat-item:hover .assinados-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
}

.dual-stat-item:hover .finalizados-icon {
    transform: scale(1.1) rotate(-5deg);
    box-shadow: 0 8px 25px rgba(0, 123, 255, 0.4);
}

.dual-stat-item:hover .socios-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 8px 25px rgba(0, 86, 210, 0.4);
}

.dual-stat-item:hover .agregados-icon {
    transform: scale(1.1) rotate(-5deg);
    box-shadow: 0 8px 25px rgba(111, 66, 193, 0.4);
}

/* === CONTAINERS === */
.documents-container {
    background: var(--white);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
    position: relative;
    overflow: hidden;
}

.documents-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.documents-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.documents-title i {
    width: 36px;
    height: 36px;
    background: var(--primary);
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

/* === FILTERS BAR === */
.filters-bar {
    background: var(--white);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-light);
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--dark);
}

.filter-select,
.filter-input {
    padding: 0.75rem 1rem;
    border: 2px solid var(--gray-300);
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(0, 86, 210, 0.1);
}

.actions-row {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1rem;
}

/* === DOCUMENTS LIST === */
.documents-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.document-card {
    background: var(--white);
    border: 2px solid var(--gray-200);
    border-radius: 16px;
    padding: 1.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.document-card:hover {
    border-color: var(--primary);
    transform: translateX(4px);
    box-shadow: var(--shadow-lg);
}

/* NOVO: Estilos para tipos de documento (Sócio vs Agregado) */
.document-card.tipo-socio {
    border-left: 4px solid var(--socio-color);
}

.document-card.tipo-agregado {
    border-left: 4px solid var(--agregado-color);
}

.document-card.tipo-socio:hover {
    border-color: var(--socio-color);
}

.document-card.tipo-agregado:hover {
    border-color: var(--agregado-color);
}

.document-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.document-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* NOVO: Ícones diferenciados por tipo */
.document-icon.socio {
    background: linear-gradient(135deg, var(--socio-color) 0%, #003d94 100%);
    box-shadow: 0 4px 12px rgba(0, 86, 210, 0.3);
}

.document-icon.agregado {
    background: linear-gradient(135deg, var(--agregado-color) 0%, #5a32a3 100%);
    box-shadow: 0 4px 12px rgba(111, 66, 193, 0.3);
}

.document-info {
    flex: 1;
}

.document-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.document-subtitle {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin: 0;
}

/* NOVO: Badge de tipo de documento */
.badge-tipo {
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.badge-tipo.socio {
    background: var(--socio-light);
    color: var(--socio-color);
}

.badge-tipo.agregado {
    background: var(--agregado-light);
    color: var(--agregado-color);
}

/* NOVO: Informações do titular (para agregados) */
.titular-info {
    background: var(--agregado-light);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border-left: 3px solid var(--agregado-color);
}

.titular-info-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--agregado-color);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.titular-info-value {
    font-size: 0.875rem;
    color: var(--dark);
    font-weight: 500;
}

/* === STATUS BADGES === */
.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.digitalizado {
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.1) 0%, rgba(19, 132, 150, 0.1) 100%);
    color: #138496;
}

.status-badge.aguardando-assinatura {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(253, 126, 20, 0.1) 100%);
    color: #fd7e14;
}

.status-badge.assinado {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(32, 201, 151, 0.1) 100%);
    color: #20c997;
}

.status-badge.finalizado {
    background: linear-gradient(135deg, rgba(0, 123, 255, 0.1) 0%, rgba(102, 16, 242, 0.1) 100%);
    color: #6610f2;
}

.document-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--gray-600);
}

.meta-item i {
    color: var(--primary);
    width: 16px;
    text-align: center;
}

/* === FLUXO PROGRESS === */
.fluxo-progress {
    background: var(--gray-100);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.25rem;
}

.fluxo-steps {
    display: flex;
    justify-content: space-between;
    position: relative;
}

.fluxo-step {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 1;
}

.fluxo-step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gray-300);
    color: var(--gray-600);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.fluxo-step.active .fluxo-step-icon {
    background: linear-gradient(135deg, var(--warning) 0%, #fd7e14 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
}

.fluxo-step.completed .fluxo-step-icon {
    background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
    color: white;
}

.fluxo-step-label {
    font-size: 0.75rem;
    color: var(--gray-600);
    font-weight: 600;
}

.fluxo-line {
    position: absolute;
    top: 20px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--gray-300);
    z-index: 0;
}

.fluxo-step.completed .fluxo-line {
    background: var(--success);
}

.fluxo-step:last-child .fluxo-line {
    display: none;
}

.document-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

/* === BUTTONS === */
.btn-premium {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    border: none;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: var(--shadow-md);
    cursor: pointer;
}

.btn-premium::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-premium:hover::before {
    width: 300px;
    height: 300px;
}

.btn-primary-premium {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
}

.btn-primary-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 86, 210, 0.3);
    color: white;
}

.btn-success-premium {
    background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
    color: white;
}

.btn-success-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
    color: white;
}

.btn-warning-premium {
    background: linear-gradient(135deg, var(--warning) 0%, #dc2626 100%);
    color: white;
}

.btn-warning-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
    color: white;
}

.btn-secondary-premium {
    background: linear-gradient(135deg, #64748b 0%, #475569 100%);
    color: white;
}

.btn-secondary-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(100, 116, 139, 0.3);
    color: white;
}

.btn-modern {
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.875rem;
    border: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-modern.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

/* === ALERTS === */
.alert-premium {
    padding: 1.25rem;
    border-radius: 12px;
    border: none;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 1.5rem;
}

.alert-info-premium {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.1) 100%);
    color: #1e40af;
    border-left: 4px solid var(--primary);
}

/* === MODALS === */
.modal-content {
    border: none;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-2xl);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 1.75rem;
    border: none;
}

.modal-title {
    font-size: 1.375rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-body {
    padding: 2rem;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.modal-footer {
    padding: 1.5rem 2rem;
    background: #f8fafc;
    border-top: 1px solid rgba(0, 86, 210, 0.1);
}

/* === TIMELINE === */
.timeline {
    position: relative;
    padding: 1rem 0;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--primary), var(--primary-light));
}

.timeline-item {
    position: relative;
    padding-left: 50px;
    margin-bottom: 2rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 11px;
    top: 5px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    border: 4px solid var(--primary);
}

.timeline-content {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: var(--shadow-sm);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.timeline-title {
    font-weight: 700;
    color: var(--dark);
}

.timeline-date {
    font-size: 0.875rem;
    color: var(--gray-600);
}

/* === UPLOAD AREA === */
.upload-area {
    border: 2px dashed var(--gray-300);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover {
    border-color: var(--primary);
    background: rgba(0, 86, 210, 0.02);
}

.upload-area.dragging {
    border-color: var(--primary);
    background: rgba(0, 86, 210, 0.05);
}

/* === EMPTY STATE === */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #94a3b8;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h5 {
    color: var(--gray-600);
    margin-bottom: 0.5rem;
}

/* === LOADING === */
.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(0, 86, 210, 0.1);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

/* === PAGINAÇÃO === */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 1.5rem 0;
    border-top: 1px solid var(--gray-200);
    margin-top: 1.5rem;
}

.pagination-info {
    font-size: 0.875rem;
    color: var(--gray-600);
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pagination-btn {
    padding: 0.5rem 0.875rem;
    border: 1px solid var(--gray-300);
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.pagination-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-select {
    padding: 0.5rem;
    border: 1px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.875rem;
}

/* === ANIMAÇÕES === */
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

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* === ESTILOS DESFILIAÇÃO === */
.desfiliacao-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
}

.dual-stat-item:hover .desfiliacao-icon {
    transform: scale(1.1) rotate(-5deg);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
}

.badge-tipo.desfiliacao {
    background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    color: white;
}

.document-card.tipo-desfiliacao {
    border-left: 4px solid #f59e0b;
}

.document-card.tipo-desfiliacao:hover {
    box-shadow: 0 8px 30px rgba(245, 158, 11, 0.15);
    border-left-color: #f97316;
}

.desfiliacao-flow-indicators {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    padding: 10px;
    background: #fff7ed;
    border-radius: 8px;
    flex-wrap: wrap;
}

.flow-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 4px;
    background: white;
}

.flow-indicator.pendente {
    color: #f59e0b;
    border: 1px solid #f59e0b;
}

.flow-indicator.aprovado {
    color: #10b981;
    border: 1px solid #10b981;
}

.flow-indicator.rejeitado {
    color: #ef4444;
    border: 1px solid #ef4444;
}

/* === RESPONSIVO === */
@media (max-width: 768px) {
    .content-area {
        padding: 1rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 0 0.5rem;
    }

    .page-title {
        font-size: 1.75rem;
    }

    .filters-row {
        grid-template-columns: 1fr;
    }

    .document-meta {
        grid-template-columns: 1fr;
    }

    .fluxo-steps {
        flex-direction: column;
        gap: 1rem;
    }

    .fluxo-line {
        display: none;
    }

    .dual-stats-row {
        flex-direction: column;
        min-height: auto;
    }

    .dual-stat-item {
        padding: 1.25rem;
        width: 100%;
        min-width: 0;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.75rem;
        justify-content: center;
    }

    .dual-stats-separator {
        width: 80%;
        height: 1px;
        margin: 0.75rem auto;
        background: linear-gradient(to right, transparent, var(--gray-300), transparent);
    }

    .dual-stat-card {
        margin: 0 auto;
        max-width: 100%;
        overflow: hidden;
    }

    .document-actions {
        justify-content: center;
    }

    .pagination-container {
        flex-direction: column;
        text-align: center;
    }
}

/* Toast Container */
.toast-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 9999;
}
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header animate__animated animate__fadeInDown">
                <h1 class="page-title">
                    Fluxo de Assinatura de Documentos
                </h1>
                <p class="page-subtitle">
                    Gerencie o processo de assinatura das fichas de filiação de <strong>Sócios e Agregados</strong> com eficiência e controle
                </p>
            </div>

            <!-- Alert Informativo -->

            <?php if (isset($_GET['novo']) && $_GET['novo'] == '1'): ?>
                <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Pré-cadastro criado com sucesso!</strong>
                    A ficha de filiação foi anexada e está aguardando envio para assinatura.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid" data-aos="fade-up">
                <!-- Card 1: Aguardando Envio + Na Presidência -->
                <div class="stat-card dual-stat-card">
                    <div class="dual-stat-header">
                        <div class="dual-stat-title">
                            <i class="fas fa-paper-plane"></i>
                            Em Processo
                        </div>
                        <div class="dual-stat-percentage warning">
                            <i class="fas fa-hourglass-half"></i>
                            Pendente
                        </div>
                    </div>
                    <div class="dual-stats-row">
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon aguardando-icon">
                                <i class="fas fa-upload"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statAguardandoEnvio"><?php echo number_format($aguardandoEnvio, 0, ',', '.'); ?></div>
                                <div class="dual-stat-label">Aguardando Envio</div>
                            </div>
                        </div>
                        <div class="dual-stats-separator"></div>
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon presidencia-icon">
                                <i class="fas fa-signature"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statNaPresidencia"><?php echo number_format($naPresidencia, 0, ',', '.'); ?></div>
                                <div class="dual-stat-label">Na Presidência</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NOVO Card 2: Sócios + Agregados -->
                <div class="stat-card dual-stat-card">
                    <div class="dual-stat-header">
                        <div class="dual-stat-title">
                            <i class="fas fa-users"></i>
                            Por Tipo
                        </div>
                        <div class="dual-stat-percentage">
                            <i class="fas fa-chart-pie"></i>
                            Distribuição
                        </div>
                    </div>
                    <div class="dual-stats-row">
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon socios-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statTotalSocios">0</div>
                                <div class="dual-stat-label">Sócios</div>
                            </div>
                        </div>
                        <div class="dual-stats-separator"></div>
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon agregados-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statTotalAgregados">0</div>
                                <div class="dual-stat-label">Agregados</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Assinados + Finalizados -->
                <div class="stat-card dual-stat-card">
                    <div class="dual-stat-header">
                        <div class="dual-stat-title">
                            <i class="fas fa-check-circle"></i>
                            Concluídos
                        </div>
                        <div class="dual-stat-percentage">
                            <i class="fas fa-chart-line"></i>
                            Processados
                        </div>
                    </div>
                    <div class="dual-stats-row">
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon assinados-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statAssinados"><?php echo number_format($assinados, 0, ',', '.'); ?></div>
                                <div class="dual-stat-label">Assinados</div>
                            </div>
                        </div>
                        <div class="dual-stats-separator"></div>
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon finalizados-icon">
                                <i class="fas fa-flag-checkered"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statFinalizados"><?php echo number_format($finalizados, 0, ',', '.'); ?></div>
                                <div class="dual-stat-label">Finalizados</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Desfiliações em Fluxo -->
                <div class="stat-card dual-stat-card">
                    <div class="dual-stat-header">
                        <div class="dual-stat-title">
                            <i class="fas fa-user-times"></i>
                            Desfiliações
                        </div>
                        <div class="dual-stat-percentage">
                            <i class="fas fa-tasks"></i>
                            Em Aprovação
                        </div>
                    </div>
                    <div class="dual-stats-row">
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon desfiliacao-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statDesfiliacaoPendentes">
                                    <?php echo number_format(
                                        ($desfiliacao_stats['aguardando_financeiro'] ?? 0) + 
                                        ($desfiliacao_stats['aguardando_juridico'] ?? 0) + 
                                        ($desfiliacao_stats['aguardando_presidencia'] ?? 0), 
                                        0, ',', '.'
                                    ); ?>
                                </div>
                                <div class="dual-stat-label">Pendentes</div>
                            </div>
                        </div>
                        <div class="dual-stats-separator"></div>
                        <div class="dual-stat-item">
                            <div class="dual-stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <i class="fas fa-check-double"></i>
                            </div>
                            <div class="dual-stat-info">
                                <div class="dual-stat-value" id="statDesfiliacaoFinalizadas">
                                    <?php echo number_format($desfiliacao_stats['finalizadas'] ?? 0, 0, ',', '.'); ?>
                                </div>
                                <div class="dual-stat-label">Finalizadas</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert-premium alert-info-premium animate__animated animate__fadeIn">
                <i class="fas fa-info-circle fa-lg"></i>
                <div>
                    <strong>Como funciona o fluxo:</strong><br>
                    1. Ficha anexada no pré-cadastro → 2. Envio para presidência → 3. Assinatura → 4. Retorno ao comercial → 5. Aprovação do pré-cadastro
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="filters-bar animate__animated animate__fadeIn" data-aos="fade-up" data-aos-delay="500">
                <div class="filters-row">
                    <!-- NOVO: Filtro de Tipo de Documento -->
                    <div class="filter-group">
                        <label class="filter-label">Tipo de Documento</label>
                        <select class="filter-select" id="filtroTipoDocumento">
                            <option value="">Todos</option>
                            <option value="FILIACAO">Filiação (Sócios e Agregados)</option>
                            <option value="DESFILIACAO">Desfiliação</option>
                        </select>
                    </div>

                    <!-- NOVO: Filtro de Tipo de Pessoa -->
                    <div class="filter-group">
                        <label class="filter-label">Tipo de Pessoa</label>
                        <select class="filter-select" id="filtroTipoPessoa">
                            <option value="">Todos (Sócios e Agregados)</option>
                            <option value="SOCIO">Apenas Sócios</option>
                            <option value="AGREGADO">Apenas Agregados</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Status do Fluxo</label>
                        <select class="filter-select" id="filtroStatusFluxo">
                            <option value="">Todos os Status</option>
                            <option value="DIGITALIZADO">Aguardando Envio</option>
                            <option value="AGUARDANDO_ASSINATURA">Na Presidência</option>
                            <option value="ASSINADO">Assinados</option>
                            <option value="FINALIZADO">Finalizados</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Buscar</label>
                        <input type="text" class="filter-input" id="filtroBuscaFluxo"
                            placeholder="Nome, CPF ou Titular...">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Período</label>
                        <select class="filter-select" id="filtroPeriodo">
                            <option value="">Todo período</option>
                            <option value="hoje">Hoje</option>
                            <option value="semana">Esta semana</option>
                            <option value="mes">Este mês</option>
                        </select>
                    </div>
                </div>

                <div class="actions-row">
                    <button class="btn-premium btn-secondary-premium" onclick="limparFiltros()">
                        <i class="fas fa-eraser me-2"></i>
                        Limpar
                    </button>
                    <button class="btn-premium btn-primary-premium" onclick="aplicarFiltros()">
                        <i class="fas fa-filter me-2"></i>
                        Filtrar
                    </button>
                </div>
            </div>

            <!-- Documents Container -->
            <div class="documents-container animate__animated animate__fadeIn" data-aos="fade-up" data-aos-delay="600">
                <div class="documents-header">
                    <h3 class="documents-title">
                        <i class="fas fa-file-alt"></i>
                        Documentos em Fluxo
                    </h3>
                    <!-- NOVO: Seletor de itens por página -->
                    <div class="d-flex align-items-center gap-2">
                        <label class="filter-label mb-0">Exibir:</label>
                        <select class="pagination-select" id="itensPorPagina" onchange="mudarItensPorPagina()">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="documents-list" id="documentosFluxoList">
                    <!-- Documentos serão carregados aqui -->
                </div>

                <!-- NOVO: Container de paginação -->
                <div class="pagination-container" id="paginacaoContainer" style="display: none;">
                    <div class="pagination-info" id="paginacaoInfo">
                        Exibindo 0-0 de 0 registros
                    </div>
                    <div class="pagination-controls" id="paginacaoControles">
                        <!-- Controles serão gerados dinamicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico -->
    <div class="modal fade" id="historicoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history"></i>
                        Histórico do Documento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historicoContent">
                        <!-- Timeline será carregada aqui -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-premium btn-secondary-premium" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Assinatura -->
    <div class="modal fade" id="assinaturaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-signature"></i>
                        Assinar Documento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assinaturaForm">
                        <input type="hidden" id="assinaturaDocumentoId">
                        <input type="hidden" id="assinaturaTipoDocumento">

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Arquivo Assinado (opcional)</label>
                            <div class="upload-area" id="uploadAssinaturaArea">
                                <i class="fas fa-file-signature mb-3" style="font-size: 2.5rem; color: var(--primary);"></i>
                                <h6 class="mb-2">Upload do documento assinado</h6>
                                <p class="mb-0 text-muted small">Arraste o PDF ou clique para selecionar</p>
                                <input type="file" id="assinaturaFileInput" class="d-none" accept=".pdf">
                            </div>
                        </div>

                        <div id="assinaturaFilesList" class="mb-4"></div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Observações</label>
                            <textarea class="form-control" id="assinaturaObservacao" rows="3"
                                placeholder="Adicione observações sobre a assinatura..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-premium btn-secondary-premium" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn-premium btn-success-premium" onclick="assinarDocumento()">
                        <i class="fas fa-check me-2"></i>
                        Confirmar Assinatura
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
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            AOS.init({
                duration: 800,
                once: true,
                offset: 50
            });

            carregarDocumentosFluxo();
            configurarUploadAssinatura();
        });

        // Variáveis globais
        let arquivoAssinaturaSelecionado = null;
        let filtrosAtuais = {};
        // NOVO: Variáveis de paginação
        let paginaAtual = 1;
        let totalPaginas = 1;
        let totalRegistros = 0;
        let itensPorPagina = 20;

        // MODIFICADO: Carregar documentos usando API unificada
        function carregarDocumentosFluxo(filtros = {}) {
            const container = document.getElementById('documentosFluxoList');

            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando documentos em fluxo...</p>
                </div>
            `;

            // MODIFICADO: Preparar parâmetros para API unificada
            const params = new URLSearchParams();
            params.append('pagina', paginaAtual);
            params.append('por_pagina', itensPorPagina);

            // Mapear filtros
            if (filtros.tipo) params.append('tipo', filtros.tipo);
            if (filtros.status) params.append('status', filtros.status);
            if (filtros.busca) params.append('busca', filtros.busca);
            if (filtros.periodo) params.append('periodo', filtros.periodo);
            if (filtros.tipoDocumento) params.append('tipo_documento', filtros.tipoDocumento);

            // MODIFICADO: Usar API atualizada que inclui desfiliações
            $.get('../api/documentos/documentos_fluxo_listar.php?' + params.toString(), function(response) {
                if (response.status === 'success') {
                    const documentos = response.data.documentos || response.data || [];
                    
                    // Processar documentos para identificar desfiliações
                    documentos.forEach(doc => {
                        if (doc.origem_tabela === 'DESFILIACAO' || doc.tipo_documento === 'ficha_desfiliacao') {
                            // Processar aprovações JSON
                            if (doc.aprovacoes_json) {
                                try {
                                    doc.aprovacoes = JSON.parse(doc.aprovacoes_json);
                                    doc.status_geral = doc.status_descricao === 'Rejeitado' ? 'REJEITADO' : 
                                                      doc.status_descricao === 'Finalizado' ? 'APROVADO' : 
                                                      'EM_APROVACAO';
                                } catch (e) {
                                    doc.aprovacoes = [];
                                    doc.status_geral = 'EM_APROVACAO';
                                }
                            }
                        }
                    });
                    
                    renderizarDocumentosFluxo(documentos);
                    
                    // NOVO: Atualizar paginação
                    if (response.paginacao) {
                        atualizarPaginacao(response.paginacao);
                    }

                    // NOVO: Atualizar estatísticas
                    if (response.estatisticas) {
                        atualizarEstatisticas(response.estatisticas);
                    }
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5>Erro ao carregar documentos</h5>
                            <p>${response.message || 'Tente novamente mais tarde'}</p>
                        </div>
                    `;
                }
            }).fail(function() {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-wifi-slash"></i>
                        <h5>Erro de conexão</h5>
                        <p>Verifique sua conexão com a internet</p>
                    </div>
                `;
            });
        }

        // NOVO: Atualizar estatísticas na interface
        function atualizarEstatisticas(stats) {
            if (stats.total_socios !== undefined) {
                document.getElementById('statTotalSocios').textContent = stats.total_socios.toLocaleString('pt-BR');
            }
            if (stats.total_agregados !== undefined) {
                document.getElementById('statTotalAgregados').textContent = stats.total_agregados.toLocaleString('pt-BR');
            }
            if (stats.pendentes_socios !== undefined && stats.pendentes_agregados !== undefined) {
                const totalPendentes = stats.pendentes_socios + stats.pendentes_agregados;
                document.getElementById('statNaPresidencia').textContent = totalPendentes.toLocaleString('pt-BR');
            }
            if (stats.assinados_socios !== undefined && stats.assinados_agregados !== undefined) {
                const totalAssinados = stats.assinados_socios + stats.assinados_agregados;
                document.getElementById('statAssinados').textContent = totalAssinados.toLocaleString('pt-BR');
            }
        }

        // NOVO: Atualizar paginação
        function atualizarPaginacao(paginacao) {
            const container = document.getElementById('paginacaoContainer');
            const info = document.getElementById('paginacaoInfo');
            const controles = document.getElementById('paginacaoControles');

            totalPaginas = paginacao.total_paginas;
            totalRegistros = paginacao.total_registros;
            paginaAtual = paginacao.pagina_atual;

            if (totalRegistros === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'flex';

            // Calcular intervalo de exibição
            const inicio = ((paginaAtual - 1) * itensPorPagina) + 1;
            const fim = Math.min(paginaAtual * itensPorPagina, totalRegistros);
            info.textContent = `Exibindo ${inicio}-${fim} de ${totalRegistros.toLocaleString('pt-BR')} registros`;

            // Gerar controles de paginação
            let controlesHTML = '';

            // Botão anterior
            controlesHTML += `
                <button class="pagination-btn" onclick="irParaPagina(${paginaAtual - 1})" ${paginaAtual <= 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;

            // Números das páginas
            const maxBotoes = 5;
            let inicioBtn = Math.max(1, paginaAtual - Math.floor(maxBotoes / 2));
            let fimBtn = Math.min(totalPaginas, inicioBtn + maxBotoes - 1);

            if (fimBtn - inicioBtn + 1 < maxBotoes) {
                inicioBtn = Math.max(1, fimBtn - maxBotoes + 1);
            }

            if (inicioBtn > 1) {
                controlesHTML += `<button class="pagination-btn" onclick="irParaPagina(1)">1</button>`;
                if (inicioBtn > 2) {
                    controlesHTML += `<span class="px-2">...</span>`;
                }
            }

            for (let i = inicioBtn; i <= fimBtn; i++) {
                controlesHTML += `
                    <button class="pagination-btn ${i === paginaAtual ? 'active' : ''}" onclick="irParaPagina(${i})">${i}</button>
                `;
            }

            if (fimBtn < totalPaginas) {
                if (fimBtn < totalPaginas - 1) {
                    controlesHTML += `<span class="px-2">...</span>`;
                }
                controlesHTML += `<button class="pagination-btn" onclick="irParaPagina(${totalPaginas})">${totalPaginas}</button>`;
            }

            // Botão próximo
            controlesHTML += `
                <button class="pagination-btn" onclick="irParaPagina(${paginaAtual + 1})" ${paginaAtual >= totalPaginas ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;

            controles.innerHTML = controlesHTML;
        }

        // NOVO: Ir para página específica
        function irParaPagina(pagina) {
            if (pagina < 1 || pagina > totalPaginas) return;
            paginaAtual = pagina;
            carregarDocumentosFluxo(filtrosAtuais);
            
            // Scroll suave para o topo da lista
            document.getElementById('documentosFluxoList').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // NOVO: Mudar itens por página
        function mudarItensPorPagina() {
            itensPorPagina = parseInt(document.getElementById('itensPorPagina').value);
            paginaAtual = 1; // Resetar para primeira página
            carregarDocumentosFluxo(filtrosAtuais);
        }

        // MODIFICADO: Renderizar documentos com suporte a tipos (filiações e desfiliações)
        function renderizarDocumentosFluxo(documentos) {
            const container = document.getElementById('documentosFluxoList');
            container.innerHTML = '';

            if (documentos.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h5>Nenhum documento encontrado</h5>
                        <p>Os documentos aparecerão aqui</p>
                    </div>
                `;
                return;
            }

            documentos.forEach(doc => {
                // Verificar se é desfiliação
                if (doc.origem_tabela === 'DESFILIACAO' || doc.tipo_documento === 'ficha_desfiliacao') {
                    container.innerHTML += renderizarCardDesfiliacao(doc);
                } else {
                    // É filiação (sócio ou agregado)
                    container.innerHTML += renderizarCardFiliacao(doc);
                }
            });
        }

        // NOVA: Renderizar card de filiação
        function renderizarCardFiliacao(doc) {
            const statusClass = doc.status_fluxo.toLowerCase().replace('_', '-');
            // Determinar se é sócio ou agregado
            const isSocio = doc.tipo_documento === 'SOCIO';
            const tipoClass = isSocio ? 'socio' : 'agregado';
            const tipoLabel = isSocio ? 'Sócio' : 'Agregado';
            const tipoIcon = isSocio ? 'fa-user-tie' : 'fa-users';
            
            // Informações do titular (apenas para agregados)
            const titularInfo = !isSocio && doc.titular_nome ? `
                <div class="titular-info">
                    <div class="titular-info-label">
                        <i class="fas fa-user me-1"></i> Sócio Titular
                    </div>
                    <div class="titular-info-value">
                            ${doc.titular_nome} ${doc.titular_cpf ? '- CPF: ' + formatarCPF(doc.titular_cpf) : ''}
                        </div>
                    </div>
                ` : '';

                return `
                    <div class="document-card tipo-${tipoClass}" data-aos="fade-up">
                        <div class="document-header">
                            <div class="document-icon ${tipoClass}">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-info">
                                <h6 class="document-title">${doc.tipo_descricao || 'Ficha de Filiação'}</h6>
                                <p class="document-subtitle">${doc.tipo_origem === 'VIRTUAL' ? 'Gerada no Sistema' : 'Digitalizada'}</p>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-2">
                                <!-- Badge de tipo -->
                                <span class="badge-tipo ${tipoClass}">
                                    <i class="fas ${tipoIcon}"></i>
                                    ${tipoLabel}
                                </span>
                                <span class="status-badge ${statusClass}">
                                    <i class="fas fa-${getStatusIcon(doc.status_fluxo)}"></i>
                                    ${doc.status_descricao}
                                </span>
                            </div>
                        </div>

                        ${titularInfo}
                        
                        <div class="document-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><strong>${doc.nome || doc.associado_nome || 'N/A'}</strong></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-id-card"></i>
                                <span>CPF: ${formatarCPF(doc.cpf || doc.associado_cpf)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-building"></i>
                                <span>${doc.departamento_atual_nome || 'Comercial'}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>${formatarData(doc.data_upload)}</span>
                            </div>
                            ${doc.dias_em_processo > 0 ? `
                            <div class="meta-item">
                                <i class="fas fa-hourglass-half"></i>
                                <span class="text-warning"><strong>${doc.dias_em_processo} dias em processo</strong></span>
                            </div>
                            ` : ''}
                        </div>
                        
                        <div class="fluxo-progress">
                            <div class="fluxo-steps">
                                <div class="fluxo-step ${doc.status_fluxo !== 'DIGITALIZADO' ? 'completed' : 'active'}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="fluxo-step-label">Digitalizado</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'AGUARDANDO_ASSINATURA' ? 'active' : (doc.status_fluxo === 'ASSINADO' || doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-signature"></i>
                                    </div>
                                    <div class="fluxo-step-label">Assinatura</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'ASSINADO' ? 'active' : (doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="fluxo-step-label">Assinado</div>
                                    <div class="fluxo-line"></div>
                                </div>
                                <div class="fluxo-step ${doc.status_fluxo === 'FINALIZADO' ? 'completed' : ''}">
                                    <div class="fluxo-step-icon">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                    <div class="fluxo-step-label">Finalizado</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="document-actions">
                            ${doc.caminho_arquivo ? `
                            <button class="btn-modern btn-primary-premium btn-sm" onclick="downloadDocumento(${doc.id}, '${doc.tipo_documento}')">
                                <i class="fas fa-download me-1"></i>
                                Baixar
                            </button>
                            ` : ''}
                            
                            ${getAcoesFluxo(doc)}
                            
                            <button class="btn-modern btn-secondary-premium btn-sm" onclick="verHistorico(${doc.id}, '${doc.tipo_documento}')">
                                <i class="fas fa-history me-1"></i>
                                Histórico
                            </button>
                        </div>
                    </div>
                `;
        }

        // Antiga renderização inline (agora removida)
        function renderizarDocumentosFluxoLegacy(documentos) {
            // Função legada - não mais utilizada
            console.warn('renderizarDocumentosFluxoLegacy está deprecated');
        }

        function getStatusIcon(status) {
            const icons = {
                'DIGITALIZADO': 'upload',
                'AGUARDANDO_ASSINATURA': 'clock',
                'ASSINADO': 'check',
                'FINALIZADO': 'flag-checkered'
            };
            return icons[status] || 'file';
        }

        // MODIFICADO: Ações com suporte ao tipo de documento
        function getAcoesFluxo(doc) {
            let acoes = '';
            const tipo = doc.tipo_documento || 'SOCIO';

            switch (doc.status_fluxo) {
                case 'DIGITALIZADO':
                    acoes = `
                        <button class="btn-modern btn-warning-premium btn-sm" onclick="enviarParaAssinatura(${doc.id}, '${tipo}')">
                            <i class="fas fa-paper-plane me-1"></i>
                            Enviar para Presidência
                        </button>
                    `;
                    break;

                case 'AGUARDANDO_ASSINATURA':
                    // Botão "Assinar" só aparece na aba Presidência (departamento_id == 1)
                    // Na aba Documentos, apenas mostra o status sem ação
                    <?php if ($usuarioLogado['departamento_id'] == 1): ?>
                        acoes = `
                        <button class="btn-modern btn-success-premium btn-sm" onclick="abrirModalAssinatura(${doc.id}, '${tipo}')">
                            <i class="fas fa-signature me-1"></i>
                            Assinar
                        </button>
                    `;
                    <?php else: ?>
                        acoes = `
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-clock me-1"></i>
                            Aguardando Presidência
                        </span>
                    `;
                    <?php endif; ?>
                    break;

                case 'ASSINADO':
                    acoes = `
                        <button class="btn-modern btn-primary-premium btn-sm" onclick="finalizarProcesso(${doc.id}, '${tipo}')">
                            <i class="fas fa-flag-checkered me-1"></i>
                            Finalizar
                        </button>
                    `;
                    break;

                case 'FINALIZADO':
                    acoes = `
                        <button class="btn-modern btn-success-premium btn-sm" disabled>
                            <i class="fas fa-check-circle me-1"></i>
                            Concluído
                        </button>
                    `;
                    break;
            }

            return acoes;
        }

        // MODIFICADO: Enviar para assinatura com tipo
        function enviarParaAssinatura(documentoId, tipo = 'SOCIO') {
            if (confirm('Deseja enviar este documento para assinatura na presidência?')) {
                // Escolher endpoint baseado no tipo
                const endpoint = tipo === 'AGREGADO' 
                    ? '../api/documentos/agregados_enviar_assinatura.php'
                    : '../api/documentos/documentos_enviar_assinatura.php';

                $.ajax({
                    url: endpoint,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Documento enviado para assinatura'
                    }),
                    success: function(response) {
                        if (response.status === 'success') {
                            showToast('Documento enviado com sucesso!', 'success');
                            carregarDocumentosFluxo(filtrosAtuais);
                        } else {
                            showToast('Erro: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Erro ao enviar documento', 'danger');
                    }
                });
            }
        }

        // MODIFICADO: Abrir modal com tipo
        function abrirModalAssinatura(documentoId, tipo = 'SOCIO') {
            document.getElementById('assinaturaDocumentoId').value = documentoId;
            document.getElementById('assinaturaTipoDocumento').value = tipo;
            document.getElementById('assinaturaObservacao').value = '';
            document.getElementById('assinaturaFilesList').innerHTML = '';
            arquivoAssinaturaSelecionado = null;

            const modal = new bootstrap.Modal(document.getElementById('assinaturaModal'));
            modal.show();
        }

        // MODIFICADO: Assinar documento com tipo
        function assinarDocumento() {
            const documentoId = document.getElementById('assinaturaDocumentoId').value;
            const tipo = document.getElementById('assinaturaTipoDocumento').value || 'SOCIO';
            const observacao = document.getElementById('assinaturaObservacao').value;

            const formData = new FormData();
            formData.append('documento_id', documentoId);
            formData.append('observacao', observacao || 'Documento assinado pela presidência');

            if (arquivoAssinaturaSelecionado) {
                formData.append('arquivo_assinado', arquivoAssinaturaSelecionado);
            }

            // Escolher endpoint baseado no tipo
            const endpoint = tipo === 'AGREGADO' 
                ? '../api/documentos/agregados_assinar.php'
                : '../api/documentos/documentos_assinar.php';

            $.ajax({
                url: endpoint,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.status === 'success') {
                        showToast('Documento assinado com sucesso!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('assinaturaModal')).hide();
                        carregarDocumentosFluxo(filtrosAtuais);
                    } else {
                        showToast('Erro: ' + response.message, 'danger');
                    }
                },
                error: function() {
                    showToast('Erro ao assinar documento', 'danger');
                }
            });
        }

        // MODIFICADO: Finalizar processo com tipo
        function finalizarProcesso(documentoId, tipo = 'SOCIO') {
            if (confirm('Deseja finalizar o processo deste documento?')) {
                // Escolher endpoint baseado no tipo
                const endpoint = tipo === 'AGREGADO' 
                    ? '../api/documentos/documentos_agregados_finalizar.php'
                    : '../api/documentos/documentos_finalizar.php';

                $.ajax({
                    url: endpoint,
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        documento_id: documentoId,
                        observacao: 'Processo finalizado'
                    }),
                    success: function(response) {
                        if (response.status === 'success') {
                            showToast('Processo finalizado com sucesso!', 'success');
                            carregarDocumentosFluxo(filtrosAtuais);
                        } else {
                            showToast('Erro: ' + response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Erro ao finalizar processo', 'danger');
                    }
                });
            }
        }

        // MODIFICADO: Ver histórico com tipo
        function verHistorico(documentoId, tipo = 'SOCIO') {
            // Escolher endpoint baseado no tipo
            const endpoint = tipo === 'AGREGADO' 
                ? '../api/documentos/documentos_agregados_historico.php'
                : '../api/documentos/documentos_historico_fluxo.php';

            $.get(endpoint, {
                documento_id: documentoId
            }, function(response) {
                if (response.status === 'success') {
                    renderizarHistorico(response.data);
                    const modal = new bootstrap.Modal(document.getElementById('historicoModal'));
                    modal.show();
                } else {
                    showToast('Erro ao carregar histórico', 'danger');
                }
            });
        }

        function renderizarHistorico(historico) {
            const container = document.getElementById('historicoContent');

            if (!historico || historico.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">Nenhum histórico disponível</p>';
                return;
            }

            let timelineHtml = '<div class="timeline">';

            historico.forEach(item => {
                timelineHtml += `
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">${item.status_novo || item.acao || 'Ação'}</h6>
                                <span class="timeline-date">${formatarData(item.data_acao)}</span>
                            </div>
                            <p class="mb-2">${item.observacao || 'Sem observações'}</p>
                            <p class="text-muted mb-0">
                                <small>
                                    Por: ${item.funcionario_nome || 'Sistema'}<br>
                                    ${item.dept_origem_nome ? `De: ${item.dept_origem_nome}<br>` : ''}
                                    ${item.dept_destino_nome ? `Para: ${item.dept_destino_nome}` : ''}
                                </small>
                            </p>
                        </div>
                    </div>
                `;
            });

            timelineHtml += '</div>';
            container.innerHTML = timelineHtml;
        }

        function configurarUploadAssinatura() {
            const uploadArea = document.getElementById('uploadAssinaturaArea');
            const fileInput = document.getElementById('assinaturaFileInput');

            if (!uploadArea || !fileInput) return;

            uploadArea.addEventListener('click', () => fileInput.click());

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragging');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragging');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragging');
                handleAssinaturaFile(e.dataTransfer.files[0]);
            });

            fileInput.addEventListener('change', (e) => {
                handleAssinaturaFile(e.target.files[0]);
            });
        }

        function handleAssinaturaFile(file) {
            if (!file) return;

            if (file.type !== 'application/pdf') {
                showToast('Por favor, selecione apenas arquivos PDF', 'warning');
                return;
            }

            arquivoAssinaturaSelecionado = file;

            const filesList = document.getElementById('assinaturaFilesList');
            filesList.innerHTML = `
                <div class="alert alert-info d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-file-pdf me-2"></i>
                        <strong>${file.name}</strong> (${formatBytes(file.size)})
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="removerArquivoAssinatura()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }

        function removerArquivoAssinatura() {
            arquivoAssinaturaSelecionado = null;
            document.getElementById('assinaturaFilesList').innerHTML = '';
            document.getElementById('assinaturaFileInput').value = '';
        }

        // ============================================
        // FUNÇÕES DE DESFILIAÇÃO
        // ============================================

        // NOVO: Função para carregar desfiliações
        function carregarDesfiliacoes(filtros = {}, containerCustom = null) {
            const container = containerCustom || document.getElementById('documentosFluxoList');
            
            if (!container) return;

            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="loading-spinner mb-3"></div>
                    <p class="text-muted">Carregando desfiliações...</p>
                </div>
            `;

            const params = new URLSearchParams();
            params.append('pagina', paginaAtual);
            params.append('por_pagina', itensPorPagina);

            if (filtros.status) params.append('status', filtros.status);
            if (filtros.busca) params.append('busca', filtros.busca);
            if (filtros.periodo) params.append('periodo', filtros.periodo);

            $.get('../api/documentos/documentos_desfiliacao_listar.php?' + params.toString(), function(response) {
                if (response.status === 'success') {
                    renderizarDesfiliacoes(response.data.documentos);
                    
                    if (response.data.estatisticas) {
                        atualizarEstatisticasDesfiliacao(response.data.estatisticas);
                    }
                } else {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h5>Erro ao carregar desfiliações</h5>
                            <p>${response.message || 'Tente novamente mais tarde'}</p>
                        </div>
                    `;
                }
            }).fail(function() {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-wifi-slash"></i>
                        <h5>Erro de conexão</h5>
                        <p>Verifique sua conexão com a internet</p>
                    </div>
                `;
            });
        }

        // NOVO: Renderizar card individual de desfiliação
        function renderizarCardDesfiliacao(doc) {
            const statusClass = doc.status_geral ? doc.status_geral.toLowerCase().replace('_', '-') : '';
            
            // Criar indicadores de fluxo
            let fluxoHTML = '<div class="desfiliacao-flow-indicators">';
            if (doc.aprovacoes && doc.aprovacoes.length > 0) {
                doc.aprovacoes.forEach(aprov => {
                    const iconClass = aprov.status_aprovacao === 'APROVADO' ? 'fa-check-circle' : 
                                    aprov.status_aprovacao === 'REJEITADO' ? 'fa-times-circle' : 
                                    'fa-clock';
                    const statusText = aprov.status_aprovacao === 'APROVADO' ? 'Aprovado' :
                                      aprov.status_aprovacao === 'REJEITADO' ? 'Rejeitado' :
                                      'Pendente';
                    
                    fluxoHTML += `
                        <div class="flow-indicator ${aprov.status_aprovacao.toLowerCase()}">
                            <i class="fas ${iconClass}"></i>
                            ${aprov.departamento_nome}: ${statusText}
                        </div>
                    `;
                });
            }
            fluxoHTML += '</div>';

            return `
                <div class="document-card tipo-desfiliacao" data-aos="fade-up">
                    <div class="document-header">
                        <div class="document-icon desfiliacao-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="document-info">
                            <h6 class="document-title">Ficha de Desfiliação</h6>
                            <p class="document-subtitle">${doc.associado_nome || 'Nome não disponível'}</p>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-2">
                            <span class="badge-tipo desfiliacao">
                                <i class="fas fa-user-times"></i>
                                Desfiliação
                            </span>
                            <span class="status-badge ${statusClass}">
                                <i class="fas fa-${getStatusIconDesfiliacao(doc.status_geral)}"></i>
                                ${getStatusTextDesfiliacao(doc.status_geral)}
                            </span>
                        </div>
                    </div>
                    
                    <div class="document-meta">
                        <div class="meta-item">
                            <i class="fas fa-id-card"></i>
                            <span>CPF: ${formatarCPF(doc.associado_cpf)}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>${formatarData(doc.data_upload)}</span>
                        </div>
                        ${doc.funcionario_nome ? `
                        <div class="meta-item">
                            <i class="fas fa-user-circle"></i>
                            <span>Por: ${doc.funcionario_nome}</span>
                        </div>
                        ` : ''}
                        ${doc.dias_em_processo > 0 ? `
                        <div class="meta-item">
                            <i class="fas fa-hourglass-half"></i>
                            <span class="text-warning"><strong>${doc.dias_em_processo} dias em processo</strong></span>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${fluxoHTML}
                    
                    <div class="document-actions">
                        ${doc.caminho_arquivo ? `
                        <button class="btn-modern btn-primary-premium btn-sm" onclick="downloadDocumento(${doc.id}, 'DESFILIACAO')">
                            <i class="fas fa-download me-1"></i>
                            Baixar
                        </button>
                        ` : ''}
                        
                        <button class="btn-modern btn-secondary-premium btn-sm" onclick="verHistoricoDesfiliacao(${doc.id})">
                            <i class="fas fa-history me-1"></i>
                            Histórico
                        </button>
                    </div>
                </div>
            `;
        }

        // NOVO: Renderizar desfiliações (compatibilidade)
        function renderizarDesfiliacoes(documentos) {
            const container = document.getElementById('documentosFluxoList');
            container.innerHTML = '';

            if (documentos.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-times"></i>
                        <h5>Nenhuma desfiliação em fluxo</h5>
                        <p>As desfiliações aparecerão aqui</p>
                    </div>
                `;
                return;
            }

            documentos.forEach(doc => {
                container.innerHTML += renderizarCardDesfiliacao(doc);
            });
        }

        function getStatusIconDesfiliacao(status) {
            const icons = {
                'EM_APROVACAO': 'hourglass-half',
                'APROVADO': 'check-circle',
                'REJEITADO': 'times-circle'
            };
            return icons[status] || 'question-circle';
        }

        function getStatusTextDesfiliacao(status) {
            const texts = {
                'EM_APROVACAO': 'Em Aprovação',
                'APROVADO': 'Aprovado',
                'REJEITADO': 'Rejeitado'
            };
            return texts[status] || 'Desconhecido';
        }

        function atualizarEstatisticasDesfiliacao(stats) {
            if (stats.aguardando_financeiro !== undefined && stats.aguardando_juridico !== undefined && stats.aguardando_presidencia !== undefined) {
                const totalPendentes = parseInt(stats.aguardando_financeiro || 0) + 
                                      parseInt(stats.aguardando_juridico || 0) + 
                                      parseInt(stats.aguardando_presidencia || 0);
                const elem = document.getElementById('statDesfiliacaoPendentes');
                if (elem) elem.textContent = totalPendentes.toLocaleString('pt-BR');
            }
            
            if (stats.finalizadas !== undefined) {
                const elem = document.getElementById('statDesfiliacaoFinalizadas');
                if (elem) elem.textContent = parseInt(stats.finalizadas || 0).toLocaleString('pt-BR');
            }
        }

        function verHistoricoDesfiliacao(documentoId) {
            // Reutilizar a função de histórico existente
            verHistorico(documentoId, 'DESFILIACAO');
        }

        // ============================================
        // FILTROS E NAVEGAÇÃO
        // ============================================

        // MODIFICADO: Aplicar filtros com novo campo de tipo
        function aplicarFiltros() {
            filtrosAtuais = {};

            // NOVO: Filtro de tipo de pessoa
            const tipo = document.getElementById('filtroTipoPessoa').value;
            if (tipo) filtrosAtuais.tipo = tipo;

            const status = document.getElementById('filtroStatusFluxo').value;
            if (status) filtrosAtuais.status = status;

            const busca = document.getElementById('filtroBuscaFluxo').value.trim();
            if (busca) filtrosAtuais.busca = busca;

            const periodo = document.getElementById('filtroPeriodo').value;
            if (periodo) filtrosAtuais.periodo = periodo;

            // NOVO: Verificar tipo de documento
            const tipoDoc = document.getElementById('filtroTipoDocumento')?.value;
            if (tipoDoc) filtrosAtuais.tipoDocumento = tipoDoc;

            paginaAtual = 1; // Resetar para primeira página ao filtrar
            carregarDocumentosFluxo(filtrosAtuais);
        }

        function limparFiltros() {
            document.getElementById('filtroTipoPessoa').value = '';
            document.getElementById('filtroStatusFluxo').value = '';
            document.getElementById('filtroBuscaFluxo').value = '';
            document.getElementById('filtroPeriodo').value = '';
            filtrosAtuais = {};
            paginaAtual = 1;
            carregarDocumentosFluxo();
        }

       // CÓDIGO CORRIGIDO:
function downloadDocumento(id, tipo = 'SOCIO') {
    const endpoint = tipo === 'AGREGADO' 
        ? '../api/documentos/documentos_download.php?id=' + id + '&tipo=agregado'
        : '../api/documentos/documentos_download.php?id=' + id;
    window.open(endpoint, '_blank');
}

        // Funções auxiliares
        function formatarData(dataStr) {
            if (!dataStr) return '-';
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatarCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Sistema de Toast
        function showToast(message, type = 'success') {
            const toastHTML = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;

            const container = document.querySelector('.toast-container');
            const toastElement = document.createElement('div');
            toastElement.innerHTML = toastHTML;
            container.appendChild(toastElement.firstElementChild);

            const toast = new bootstrap.Toast(container.lastElementChild);
            toast.show();
        }

        // Auto-refresh a cada 30 segundos
        setInterval(function() {
            carregarDocumentosFluxo(filtrosAtuais);
        }, 30000);
    </script>
</body>

</html>
