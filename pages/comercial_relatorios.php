<?php
/**
 * Página de Relatórios Comerciais - Sistema ASSEGO
 * pages/comercial_relatorios.php
 * 
 * VERSÃO PADRONIZADA - Estilo minimalista
 */

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

// VERIFICAÇÃO DE PERMISSÃO
$temPermissaoRelatorios = Permissoes::tem('COMERCIAL_RELATORIOS');
if (!$temPermissaoRelatorios) {
    Permissoes::registrarAcessoNegado('COMERCIAL_RELATORIOS', 'comercial_relatorios.php');
    $_SESSION['erro'] = 'Você não tem permissão para acessar os relatórios comerciais.';
    header('Location: ../pages/comercial.php');
    exit;
}

// Define o título da página
$page_title = 'Relatórios Comerciais - ASSEGO';

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
    'notificationCount' => 0,
    'showSearch' => false
]);

// Buscar dados dinâmicos do banco para os filtros
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // BUSCAR CORPORAÇÕES ÚNICAS DO BANCO
    $stmt = $db->prepare("
        SELECT DISTINCT corporacao 
        FROM Militar 
        WHERE corporacao IS NOT NULL 
        AND corporacao != '' 
        ORDER BY corporacao
    ");
    $stmt->execute();
    $corporacoesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // BUSCAR PATENTES ÚNICAS DO BANCO
    $stmt = $db->prepare("
        SELECT DISTINCT patente 
        FROM Militar 
        WHERE patente IS NOT NULL 
        AND patente != '' 
        ORDER BY 
            CASE 
                WHEN patente LIKE '%Coronel%' THEN 1
                WHEN patente LIKE '%Tenente-Coronel%' THEN 2
                WHEN patente LIKE '%Major%' THEN 3
                WHEN patente LIKE '%Capitão%' THEN 4
                WHEN patente LIKE '%Tenente%' THEN 5
                WHEN patente LIKE '%Aspirante%' THEN 6
                WHEN patente LIKE '%Subtenente%' THEN 7
                WHEN patente LIKE '%Sargento%' THEN 8
                WHEN patente LIKE '%Cabo%' THEN 9
                WHEN patente LIKE '%Soldado%' THEN 10
                WHEN patente LIKE '%Aluno%' THEN 11
                ELSE 12
            END,
            patente
    ");
    $stmt->execute();
    $patentesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // BUSCAR LOTAÇÕES ÚNICAS DO BANCO
    $stmt = $db->prepare("
        SELECT lotacao, COUNT(*) as total 
        FROM Militar 
        WHERE lotacao IS NOT NULL 
        AND lotacao != '' 
        GROUP BY lotacao 
        ORDER BY total DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $lotacoesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // BUSCAR CIDADES ÚNICAS DO BANCO (da tabela Endereco)
    $stmt = $db->prepare("
        SELECT DISTINCT cidade 
        FROM Endereco 
        WHERE cidade IS NOT NULL 
        AND cidade != '' 
        ORDER BY cidade
    ");
    $stmt->execute();
    $cidadesDB = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $corporacoesDB = [];
    $patentesDB = [];
    $lotacoesDB = [];
    $cidadesDB = [];
    error_log("Erro ao buscar dados dinâmicos: " . $e->getMessage());
}

// Se não há dados no banco, usar valores padrão
if (empty($corporacoesDB)) {
    $corporacoesDB = ['Polícia Militar', 'Bombeiro Militar'];
}

if (empty($patentesDB)) {
    $patentesDB = [
        'Coronel',
        'Tenente-Coronel',
        'Major',
        'Capitão',
        'Primeiro-Tenente',
        'Segundo-Tenente',
        'Aspirante-a-Oficial',
        'Subtenente',
        'Primeiro-Sargento',
        'Segundo-Sargento',
        'Terceiro-Sargento',
        'Cabo',
        'Soldado 1ª Classe',
        'Soldado 2ª Classe',
        'Aluno Soldado'
    ];
}
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

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="./estilizacao/style.css">

    <!-- Estilos Padronizados - Estilo Minimalista -->
    <style>
        /* Variáveis CSS minimalistas */
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
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
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--gray-100);
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
        }

        /* Page Header - Fora de cards */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 0.5rem 0;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
        }

        /* Back Button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            background: var(--secondary);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            box-shadow: var(--shadow-sm);
        }

        .btn-back:hover {
            transform: translateX(-2px);
            color: white;
            box-shadow: var(--shadow-md);
            background: #5a6268;
        }

        /* Actions Container - Estilo Minimalista */
        .actions-container {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .actions-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .actions-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .actions-title i {
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

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }

        .action-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .action-card.active {
            background: linear-gradient(135deg, rgba(0, 86, 210, 0.05) 0%, rgba(74, 144, 226, 0.05) 100%);
            border-color: var(--primary);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .action-icon.primary { background: var(--primary); }
        .action-icon.success { background: var(--success); }
        .action-icon.warning { background: var(--warning); }
        .action-icon.info { background: var(--info); }

        .action-content h5 {
            margin: 0 0 0.25rem 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .action-content p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        /* Filters Container */
        .filters-container {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        /* Forms */
        .form-control-custom,
        .form-select-custom {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control-custom:focus,
        .form-select-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
            outline: none;
        }

        .form-label-custom {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label-custom i {
            color: var(--primary);
            font-size: 0.9rem;
        }

        /* Buttons */
        .btn-custom {
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .btn-primary-custom {
            background: var(--primary);
            color: white;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: var(--primary-light);
        }

        .btn-secondary-custom {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: #5a6268;
        }

        /* Results Container */
        .results-container {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            min-height: 400px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .results-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .results-title i {
            color: var(--primary);
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow-sm);
        }

        .btn-export-excel {
            background: var(--success);
            color: white;
        }

        .btn-export-pdf {
            background: var(--danger);
            color: white;
        }

        .btn-export-print {
            background: var(--info);
            color: white;
        }

        .btn-export:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Loading */
        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        .spinner-custom {
            width: 50px;
            height: 50px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Alert */
        .alert-custom {
            padding: 1rem;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-sm);
        }

        .alert-info-custom {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
            border-left: 3px solid var(--info);
        }

        /* Badge */
        .badge-custom {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 600;
            background: var(--primary);
            color: white;
        }

        /* DataTable Custom */
        .dataTables_wrapper {
            padding-top: 1rem;
        }

        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
        }

        table.dataTable thead th {
            background: var(--gray-100);
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border-light);
        }

        table.dataTable tbody tr:hover {
            background: var(--gray-100);
        }

        /* Melhorias visuais para tabelas de relatórios */
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            border: 1px solid #e3e6f0;
        }

        table.dataTable thead th {
            background: #000000 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            font-size: 0.95rem !important;
            letter-spacing: 1px;
            padding: 1.5rem 1rem !important;
            border: none !important;
            white-space: nowrap;
            position: relative;
            text-align: center;
        }

        table.dataTable thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.2);
        }

        table.dataTable tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
            border-right: 1px solid #f1f3f5;
            font-size: 0.9rem;
            color: #495057;
        }

        table.dataTable tbody td:last-child {
            border-right: none;
        }

        table.dataTable tbody tr {
            transition: all 0.3s ease;
        }

        table.dataTable tbody tr:nth-child(even) {
            background-color: #f8f9fc;
        }

        table.dataTable tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        table.dataTable tbody tr:hover {
            background: linear-gradient(90deg, #f0f4ff 0%, #e3ebff 100%) !important;
            transform: scale(1.005);
            box-shadow: 0 3px 12px rgba(102, 126, 234, 0.2);
            cursor: pointer;
        }

        /* Destaque para coluna # (número) */
        table.dataTable tbody td:first-child {
            font-weight: 700;
            color: #667eea;
            text-align: center;
            background-color: rgba(102, 126, 234, 0.08);
            font-size: 0.95rem;
        }

        /* Estilo especial para células com N/D */
        table.dataTable tbody td {
            position: relative;
        }

        table.dataTable tbody td:contains("N/D"),
        table.dataTable tbody td[data-nd="true"] {
            color: #6c757d !important;
            font-style: italic;
            font-weight: 500;
            background-color: #f8f9fa !important;
        }

        /* Badge dentro da tabela */
        table.dataTable tbody .badge {
            font-weight: 600;
            padding: 0.4rem 0.85rem;
            font-size: 0.8rem;
            border-radius: 0.5rem;
            letter-spacing: 0.3px;
        }

        /* Cores para badges */
        .badge.bg-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
        }

        .badge.bg-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
            box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3);
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e91e63 100%) !important;
            box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
        }

        /* Separadores visuais entre colunas */
        table.dataTable tbody td {
            border-right: 1px solid #f0f2f5;
        }

        table.dataTable tbody td:last-child {
            border-right: none;
        }

        /* Scroll personalizado */
        .dataTables_wrapper::-webkit-scrollbar {
            height: 10px;
            width: 10px;
        }

        .dataTables_wrapper::-webkit-scrollbar-track {
            background: #f1f3f5;
            border-radius: 10px;
        }

        .dataTables_wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }

        .dataTables_wrapper::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        /* Badge Status */
        .badge-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
        }

        /* Estilo especial para células N/D ou vazias */
        .cell-nd {
            color: #6c757d !important;
            font-style: italic;
            font-weight: 500;
            text-align: center;
            background-color: rgba(108, 117, 125, 0.08) !important;
        }

        table.dataTable tbody tr:hover .cell-nd {
            background-color: rgba(108, 117, 125, 0.15) !important;
        }

        /* Estilo especial para células N/D */
        .cell-nd {
            color: #9ca3af !important;
            font-style: italic;
            font-weight: 500;
            text-align: center;
        }

        /* Melhorias nos botões DataTables */
        .dt-buttons {
            margin-bottom: 1rem;
        }

        .dt-button {
            border-radius: 6px !important;
            padding: 0.5rem 1rem !important;
            margin-right: 0.5rem !important;
            transition: all 0.2s ease !important;
        }

        .dt-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        .badge-status.ativo {
            background: var(--success);
            color: white;
        }

        .badge-status.inativo {
            background: var(--danger);
            color: white;
        }

        .badge-status.pre-cadastro {
            background: var(--warning);
            color: white;
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        /* Animations */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                grid-template-columns: 1fr !important;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .export-buttons {
                width: 100%;
                justify-content: flex-start;
            }
        }

        /* ===================================================================
           MELHORIAS VISUAIS PARA RELATÓRIO DE DESFILIAÇÕES - V2.0
           =================================================================== */

        /* Estilo melhorado para a tabela de relatórios */
        #reportTable {
            border-collapse: separate !important;
            border-spacing: 0;
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Header da tabela - PRETO com texto branco e bem visível */
        #reportTable thead {
            background: #000000 !important;
        }

        #reportTable thead th {
            background: #000000 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 18px 14px !important;
            border: none !important;
            text-align: center !important;
            vertical-align: middle !important;
        }

        /* Primeira coluna (número) com destaque */
        #reportTable thead th:first-child {
            text-align: center;
            width: 60px;
        }

        /* Corpo da tabela com alternância de cores suave */
        #reportTable tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        #reportTable tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        #reportTable tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        #reportTable tbody tr:hover {
            background-color: #e3f2fd !important;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 86, 210, 0.15);
            cursor: pointer;
        }

        /* Células do corpo */
        #reportTable tbody td {
            padding: 14px 12px !important;
            font-size: 14px !important;                    /* ✅ FONTE MAIOR */
            color: #000000 !important;                     /* ✅ PRETO */
            vertical-align: middle;
            border: none !important;
            font-weight: 500;                              /* ✅ MAIS FORTE */
        }

        /* Coluna de número centralizada */
        #reportTable tbody td:first-child {
            text-align: center;
            font-weight: 600;
            color: #0056d2;
            font-size: 13px;
        }

        /* ✅ CPF - PRETO E VISÍVEL */
        #reportTable tbody td:nth-child(3) {
            font-family: 'Courier New', monospace;
            font-size: 14px !important;                    /* ✅ MAIOR */
            color: #000000 !important;                     /* ✅ PRETO */
            font-weight: 600 !important;                   /* ✅ NEGRITO */
        }

        /* Destaque para patente e corporação */
        #reportTable tbody td:nth-child(4) {
            font-weight: 600;
            color: #000000 !important;                     /* ✅ PRETO */
        }

        #reportTable tbody td:nth-child(5) {
            font-weight: 600;
            color: #000000 !important;                     /* ✅ PRETO */
        }

        /* Badge para status N/D */
        .badge-nd {
            background-color: #ffc107;
            color: #000;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        /* Container da tabela com padding */
        .table-responsive {
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
        }

        /* Botões de exportação estilizados */
        .dt-buttons {
            margin-bottom: 20px !important;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dt-buttons .btn {
            border-radius: 8px !important;
            padding: 8px 16px !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
            border: none !important;
        }

        .dt-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        /* Paginação estilizada */
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 20px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 6px !important;
            margin: 0 4px !important;
            padding: 6px 12px !important;
            border: 1px solid #dee2e6 !important;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0056d2 !important;
            color: white !important;
            border-color: #0056d2 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e3f2fd !important;
            border-color: #0056d2 !important;
            color: #0056d2 !important;
        }

        /* Info e busca */
        .dataTables_wrapper .dataTables_info {
            color: #6c757d;
            font-size: 13px;
            padding-top: 12px;
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 8px !important;
            border: 1px solid #dee2e6 !important;
            padding: 8px 16px !important;
            font-size: 13px !important;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #0056d2 !important;
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1) !important;
            outline: none !important;
        }

        /* Animação de carregamento suave */
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

        #reportTable tbody tr {
            animation: fadeIn 0.3s ease;
        }

        /* Scroll horizontal suave */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #0056d2;
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #003d99;
        }

        /* ===================================================================
           ESTILOS PARA IMPRESSÃO E PDF - VERSÃO PROFISSIONAL
           =================================================================== */
        
        @media print {
            /* Reset geral para impressão */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            /* Ocultar elementos desnecessários */
            .filters-container,
            .export-buttons,
            .dt-buttons,
            .dataTables_filter,
            .dataTables_length,
            .dataTables_info,
            .dataTables_paginate,
            .btn,
            button,
            .navbar,
            .sidebar,
            header,
            .toast-container,
            .loading-container {
                display: none !important;
            }
            
            /* Container principal */
            body {
                background: white !important;
                margin: 0;
                padding: 20px;
            }
            
            .main-wrapper,
            .content-area {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* Título do relatório */
            .results-title {
                font-size: 22px !important;         /* ✅ FONTE MAIOR */
                color: #0056d2 !important;          /* ✅ AZUL */
                margin-bottom: 20px !important;
                text-align: center;
                font-weight: bold;
                page-break-after: avoid;
            }
            
            /* Tabela - estilo limpo e profissional */
            #reportTable {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: avoid;
                font-size: 11px !important;        /* ✅ FONTE MAIOR */
            }
            
            /* Header da tabela - PRETO com texto branco */
            #reportTable thead {
                background: #000000 !important;
                border-bottom: 3px solid #000 !important;
            }
            
            #reportTable thead th {
                background: #000000 !important;
                color: #ffffff !important;
                font-weight: 700 !important;
                font-size: 13px !important;
                padding: 12px 10px !important;
                border: 1px solid #000 !important;
                text-align: center !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            /* Linhas da tabela */
            #reportTable tbody tr {
                page-break-inside: avoid;
                page-break-after: auto;
                border-bottom: 1px solid #e0e0e0 !important;
            }
            
            #reportTable tbody tr:nth-child(even) {
                background: #fafafa !important;
            }
            
            #reportTable tbody tr:nth-child(odd) {
                background: white !important;
            }
            
            /* Células da tabela */
            #reportTable tbody td {
                padding: 8px 8px !important;
                font-size: 11px !important;        /* ✅ FONTE MAIOR */
                color: #000000 !important;         /* ✅ PRETO */
                border: 1px solid #e0e0e0 !important;
                vertical-align: middle !important;
            }
            
            /* Coluna de número */
            #reportTable tbody td:first-child,
            #reportTable thead th:first-child {
                text-align: center !important;
                font-weight: 600 !important;
                width: 40px !important;
            }
            
            /* Badge N/D para impressão */
            .badge-nd {
                background: #fff3cd !important;
                color: #856404 !important;
                padding: 3px 8px !important;
                border-radius: 4px !important;
                font-size: 10px !important;        /* ✅ FONTE MAIOR */
                font-weight: 600 !important;
                border: 1px solid #ffc107 !important;
                display: inline-block !important;
            }
            
            /* Ajustes de página */
            @page {
                size: A4 landscape;
                margin: 15mm 10mm 15mm 10mm;
            }
            
            /* Cabeçalho do relatório */
            .page-header {
                text-align: center;
                margin-bottom: 20px;
                page-break-after: avoid;
            }
            
            /* Quebra de página */
            .page-break {
                page-break-before: always;
            }
            
            /* Container da tabela */
            .table-responsive {
                overflow: visible !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            /* Remover sombras */
            * {
                box-shadow: none !important;
                text-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Botão Voltar -->
            <a href="./comercial.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Voltar aos Serviços Comerciais
            </a>

            <!-- Page Header - Fora de cards como no comercial.php -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-chart-line me-2"></i>Relatórios Comerciais
                </h1>
                <p class="page-subtitle">
                    Sistema avançado de geração de relatórios para análise comercial e estratégica
                </p>
            </div>

            <!-- Report Type Selector - Cards de Ação -->
            <div class="actions-container">
                <div class="actions-header">
                    <h3 class="actions-title">
                        <i class="fas fa-clipboard-list"></i>
                        Selecione o Tipo de Relatório
                    </h3>
                </div>

                <div class="actions-grid">
                    <div class="action-card active" data-type="desfiliacoes" onclick="selecionarRelatorio('desfiliacoes')">
                        <div class="action-icon primary">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <div class="action-content">
                            <h5>Desfiliações</h5>
                            <p>Análise de associados desfiliados</p>
                        </div>
                    </div>

                    <div class="action-card" data-type="indicacoes" onclick="selecionarRelatorio('indicacoes')">
                        <div class="action-icon success">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-content">
                            <h5>Indicações</h5>
                            <p>Relatório completo de indicações</p>
                        </div>
                    </div>

                    <div class="action-card" data-type="aniversariantes" onclick="selecionarRelatorio('aniversariantes')">
                        <div class="action-icon warning">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="action-content">
                            <h5>Aniversariantes</h5>
                            <p>Aniversários por período</p>
                        </div>
                    </div>

                    <div class="action-card" data-type="novos_cadastros" onclick="selecionarRelatorio('novos_cadastros')">
                        <div class="action-icon info">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="action-content">
                            <h5>Novos Cadastros</h5>
                            <p>Associados recém cadastrados</p>
                        </div>
                    </div>

                    <div class="action-card" data-type="filiacoes" onclick="selecionarRelatorio('filiacoes')">
                        <div class="action-icon" style="background: linear-gradient(135deg, #9333ea 0%, #7c3aed 100%);">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="action-content">
                            <h5>Filiações e Desfiliações</h5>
                            <p>Resumo completo de filiações e desfiliações</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-container">
                <div class="actions-header">
                    <h3 class="actions-title">
                        <i class="fas fa-filter"></i>
                        Configuração de Filtros
                    </h3>
                </div>

                <form id="formFiltros">
                    <input type="hidden" name="tipo" id="tipoRelatorio" value="desfiliacoes">

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-calendar-alt"></i>
                                Data Inicial
                            </label>
                            <input type="date" name="data_inicio" class="form-control form-control-custom" id="dataInicio"
                                value="<?php echo date('Y-m-01'); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-calendar-check"></i>
                                Data Final
                            </label>
                            <input type="date" name="data_fim" class="form-control form-control-custom" id="dataFim"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-building"></i>
                                Tipo
                            </label>
                            <select name="corporacao" class="form-select form-select-custom select2" id="corporacao">
                                <option value="">Todas as corporações</option>
                                <?php foreach($corporacoesDB as $corporacao): ?>
                                    <?php if(!empty($corporacao)): ?>
                                        <option value="<?php echo htmlspecialchars($corporacao); ?>">
                                            <?php echo htmlspecialchars($corporacao); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-star"></i>
                                Patente
                            </label>
                            <select name="patente" class="form-select form-select-custom select2" id="patente">
                                <option value="">Todas as patentes</option>
                                <?php foreach($patentesDB as $patente): ?>
                                    <?php if(!empty($patente)): ?>
                                        <option value="<?php echo htmlspecialchars($patente); ?>">
                                            <?php echo htmlspecialchars($patente); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-map-marker-alt"></i>
                                Lotação
                            </label>
                            <select name="lotacao" class="form-select form-select-custom select2" id="lotacao">
                                <option value="">Todas as lotações</option>
                                <?php foreach($lotacoesDB as $lotacao): ?>
                                    <?php if(!empty($lotacao)): ?>
                                        <option value="<?php echo htmlspecialchars($lotacao); ?>">
                                            <?php echo htmlspecialchars($lotacao); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3" id="campoCidade" style="display: none;">
                            <label class="form-label-custom">
                                <i class="fas fa-city"></i>
                                Cidade
                            </label>
                            <select name="cidade" class="form-select form-select-custom select2" id="cidade">
                                <option value="">Todas as cidades</option>
                                <?php foreach($cidadesDB as $cidade): ?>
                                    <?php if(!empty($cidade)): ?>
                                        <option value="<?php echo htmlspecialchars($cidade); ?>">
                                            <?php echo htmlspecialchars($cidade); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3" id="campoSituacao" style="display: none;">
                            <label class="form-label-custom">
                                <i class="fas fa-user-check"></i>
                                Situação
                            </label>
                            <select name="situacao" class="form-select form-select-custom" id="situacao">
                                <option value="" selected>Todos</option>
                                <option value="Filiado">Filiado</option>
                                <option value="Desfiliado">Desfiliado</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-sort-alpha-down"></i>
                                Ordenar Por
                            </label>
                            <select name="ordenacao" class="form-select form-select-custom" id="ordenacao">
                                <option value="nome">Nome (A-Z)</option>
                                <option value="data">Data (Mais recente)</option>
                                <option value="patente">Patente (Hierarquia)</option>
                                <option value="corporacao">Tipo</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label-custom">
                                <i class="fas fa-search"></i>
                                Busca Rápida
                            </label>
                            <input type="text" name="busca" class="form-control form-control-custom" id="busca"
                                placeholder="Nome, CPF ou RG...">
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-custom btn-primary-custom w-100"
                                onclick="gerarRelatorio()">
                                <i class="fas fa-chart-bar me-2"></i>
                                Gerar Relatório
                            </button>
                        </div>
                    </div>

                    <!-- Botões de ação -->
                    <div class="text-center">
                        <button type="button" class="btn btn-custom btn-secondary-custom" onclick="limparFiltros()">
                            <i class="fas fa-eraser me-2"></i>
                            Limpar Filtros
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Container -->
            <div class="results-container">
                <div class="results-header">
                    <h5 class="results-title">
                        <i class="fas fa-table"></i>
                        <span id="tituloResultado">Aguardando Geração do Relatório</span>
                        <span class="badge-custom ms-3" id="totalRegistros" style="display: none;">0</span>
                    </h5>
                    <div class="export-buttons" id="exportButtons" style="display: none;">
                        <button class="btn-export btn-export-excel" onclick="exportarRelatorio('excel')">
                            <i class="fas fa-file-excel"></i>
                            Excel
                        </button>
                        <button class="btn-export btn-export-print" onclick="imprimirRelatorio()">
                            <i class="fas fa-print"></i>
                            Imprimir
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div id="loadingContainer" class="loading-container" style="display: none;">
                    <div class="spinner-custom"></div>
                    <p class="mt-3 text-muted">Processando relatório...</p>
                </div>

                <!-- Results -->
                <div id="resultsContainer">
                    <div class="alert-custom alert-info-custom">
                        <i class="fas fa-info-circle fa-lg"></i>
                        <div>
                            <strong>Pronto para gerar relatórios!</strong><br>
                            Configure os filtros desejados e clique em "Gerar Relatório" para visualizar os dados.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Variáveis globais
        let currentReportType = 'desfiliacoes';
        let currentReportData = null;
        let dataTable = null;

        // Inicialização
        $(document).ready(function () {
            // Inicializar Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: function() {
                    return $(this).find('option:first').text();
                },
                allowClear: true,
                language: {
                    noResults: function() {
                        return "Nenhum resultado encontrado";
                    }
                }
            });
        });

        // Selecionar tipo de relatório
        function selecionarRelatorio(tipo) {
            currentReportType = tipo;
            document.getElementById('tipoRelatorio').value = tipo;

            // Atualizar visual dos cards
            document.querySelectorAll('.action-card').forEach(card => {
                card.classList.remove('active');
                if (card.dataset.type === tipo) {
                    card.classList.add('active');
                }
            });

            // Mostrar/esconder campos específicos de aniversariantes (cidade e situação)
            const campoCidade = document.getElementById('campoCidade');
            const campoSituacao = document.getElementById('campoSituacao');
            
            if (campoCidade) {
                if (tipo === 'aniversariantes') {
                    campoCidade.style.display = 'block';
                    // Reinicializar Select2 para o campo cidade
                    if ($.fn.select2) {
                        $('#cidade').select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Todas as cidades',
                            allowClear: true,
                            width: '100%'
                        });
                    }
                } else {
                    campoCidade.style.display = 'none';
                    // Limpar seleção
                    $('#cidade').val('').trigger('change');
                }
            }
            
            if (campoSituacao) {
                if (tipo === 'aniversariantes') {
                    campoSituacao.style.display = 'block';
                } else {
                    campoSituacao.style.display = 'none';
                    // Resetar para padrão (Todos)
                    $('#situacao').val('');
                }
            }

            // Limpar resultados anteriores
            document.getElementById('resultsContainer').innerHTML = `
                <div class="alert-custom alert-info-custom">
                    <i class="fas fa-info-circle fa-lg"></i>
                    <div>
                        <strong>Tipo de relatório alterado!</strong><br>
                        Configure os filtros e clique em "Gerar Relatório" para visualizar os dados.
                    </div>
                </div>
            `;
            
            document.getElementById('exportButtons').style.display = 'none';
            document.getElementById('totalRegistros').style.display = 'none';

            showToast(`Relatório de ${getNomeTipoRelatorio(tipo)} selecionado`, 'info');
        }

        // Obter nome do tipo de relatório
        function getNomeTipoRelatorio(tipo) {
            const nomes = {
                'desfiliacoes': 'Desfiliações',
                'indicacoes': 'Indicações',
                'aniversariantes': 'Aniversariantes',
                'novos_cadastros': 'Novos Cadastros',
                'filiacoes': 'Filiações'
            };
            return nomes[tipo] || 'Relatório';
        }

        // Gerar relatório
        async function gerarRelatorio() {
            const formData = new FormData(document.getElementById('formFiltros'));
            const params = new URLSearchParams(formData);

            // Validar datas
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;

            if (!dataInicio || !dataFim) {
                showToast('Por favor, selecione as datas inicial e final', 'warning');
                return;
            }

            if (dataInicio > dataFim) {
                showToast('A data inicial não pode ser maior que a data final', 'warning');
                return;
            }

            // Mostrar loading
            document.getElementById('loadingContainer').style.display = 'flex';
            document.getElementById('resultsContainer').innerHTML = '';
            document.getElementById('exportButtons').style.display = 'none';
            document.getElementById('totalRegistros').style.display = 'none';
            document.getElementById('tituloResultado').textContent = 'Processando...';

            try {
                // Se for relatório de filiações, usa endpoint diferente
                let apiUrl = '../api/relatorios/gerar_relatorio_comercial.php';
                if (currentReportType === 'filiacoes') {
                    apiUrl = '../api/relatorios/gerar_relatorio_filiacoes.php';
                }
                
                const response = await fetch(`${apiUrl}?${params.toString()}`);
                
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    currentReportData = data;
                    renderizarRelatorio(data);
                    document.getElementById('exportButtons').style.display = 'flex';
                    showToast('Relatório gerado com sucesso!', 'success');
                } else {
                    throw new Error(data.message || 'Erro ao gerar relatório');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarErro(error.message || 'Erro ao processar relatório');
            } finally {
                document.getElementById('loadingContainer').style.display = 'none';
            }
        }

        // Renderizar relatório
        function renderizarRelatorio(response) {
            const container = document.getElementById('resultsContainer');
            const data = response.data || [];

            // Atualizar título
            document.getElementById('tituloResultado').textContent = `${getNomeTipoRelatorio(currentReportType)} - ${data.length} registros`;
            document.getElementById('totalRegistros').textContent = data.length;
            document.getElementById('totalRegistros').style.display = 'inline-block';

            if (data.length === 0) {
                container.innerHTML = `
                    <div class="alert-custom alert-info-custom">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div>
                            <strong>Nenhum registro encontrado</strong><br>
                            Tente ajustar os filtros ou verificar se existem dados no período selecionado.
                        </div>
                    </div>
                `;
                return;
            }

            // Renderização especial para INDICAÇÕES
            if (currentReportType === 'indicacoes') {
                renderizarRelatorioIndicacoes(data);
                return;
            }

            // Renderização especial para FILIAÇÕES
            if (currentReportType === 'filiacoes') {
                renderizarRelatorioFiliacoes(response);
                return;
            }

            // Criar tabela para outros relatórios
            let tableHTML = '<div class="table-responsive"><table id="reportTable" class="table table-striped table-hover">';
            
            // Headers
            const headers = getTableHeaders(currentReportType);
            tableHTML += '<thead><tr>';
            tableHTML += '<th width="50">#</th>';
            headers.forEach(header => {
                tableHTML += `<th>${header.label}</th>`;
            });
            tableHTML += '</tr></thead><tbody>';

            // Dados
            data.forEach((row, index) => {
                tableHTML += '<tr>';
                tableHTML += `<td>${index + 1}</td>`;
                headers.forEach(header => {
                    const value = row[header.key];
                    const formattedValue = formatValue(value, header.type);
                    const cssClass = (formattedValue === 'N/D') ? ' class="cell-nd"' : '';
                    tableHTML += `<td${cssClass}>${formattedValue}</td>`;
                });
                tableHTML += '</tr>';
            });

            tableHTML += '</tbody></table></div>';
            container.innerHTML = tableHTML;

            inicializarDataTable();
        }

        // Renderizar relatório de indicações especial
        function renderizarRelatorioIndicacoes(data) {
            const container = document.getElementById('resultsContainer');
            
            // Criar tabela detalhada para indicações
            let tableHTML = `
                <div class="table-responsive">
                    <table id="reportTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Data</th>
                                <th colspan="3" style="background: #e0f2fe;">INDICADOR (Quem indicou)</th>
                                <th colspan="6" style="background: #dcfce7;">ASSOCIADO INDICADO</th>
                                <th>Status</th>
                                <th>Totais</th>
                            </tr>
                            <tr>
                                <th></th>
                                <th>Indicação</th>
                                <!-- Indicador -->
                                <th style="background: #f0f9ff;">Nome</th>
                                <th style="background: #f0f9ff;">Patente</th>
                                <th style="background: #f0f9ff;">Corporação</th>
                                <!-- Associado Indicado -->
                                <th style="background: #f0fdf4;">Nome</th>
                                <th style="background: #f0fdf4;">CPF</th>
                                <th style="background: #f0fdf4;">Telefone</th>
                                <th style="background: #f0fdf4;">Patente</th>
                                <th style="background: #f0fdf4;">Corporação</th>
                                <th style="background: #f0fdf4;">Lotação</th>
                                <!-- Status e Totais -->
                                <th>Situação</th>
                                <th>Indicações</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            // Processar dados
            data.forEach((row, index) => {
                // Determinar cor do status
                let statusClass = 'badge-status ';
                let statusText = row.status_simplificado || row.situacao_associado || 'Desconhecido';
                
                if (statusText.toLowerCase().includes('ativo') || statusText.toLowerCase().includes('filiado')) {
                    statusClass += 'ativo';
                } else if (statusText.toLowerCase().includes('desfil') || statusText.toLowerCase().includes('inativ')) {
                    statusClass += 'inativo';
                } else if (row.tipo_cadastro === 'Pré-cadastro') {
                    statusClass += 'pre-cadastro';
                }

                tableHTML += '<tr>';
                tableHTML += `<td>${index + 1}</td>`;
                tableHTML += `<td>${formatValue(row.data_indicacao, 'date')}</td>`;
                
                // Dados do Indicador
                tableHTML += `<td style="background: #f0f9ff;"><strong>${row.indicador_nome || '-'}</strong></td>`;
                tableHTML += `<td style="background: #f0f9ff;">${row.indicador_patente || '-'}</td>`;
                tableHTML += `<td style="background: #f0f9ff;">${row.indicador_corporacao || '-'}</td>`;
                
                // Dados do Associado Indicado
                tableHTML += `<td style="background: #f0fdf4;"><strong>${row.associado_indicado_nome || '-'}</strong></td>`;
                tableHTML += `<td style="background: #f0fdf4;">${formatValue(row.associado_indicado_cpf_formatado || row.associado_indicado_cpf, 'cpf')}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${formatValue(row.associado_indicado_telefone_formatado || row.associado_indicado_telefone, 'phone')}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${row.associado_indicado_patente || '-'}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${row.associado_indicado_corporacao || '-'}</td>`;
                tableHTML += `<td style="background: #f0fdf4;">${row.associado_indicado_lotacao || '-'}</td>`;
                
                // Status
                tableHTML += `<td><span class="${statusClass}">${statusText}</span></td>`;
                
                // Total de indicações do indicador
                const totalIndicacoes = row.total_indicacoes_do_indicador || 0;
                const totalAtivos = row.total_indicados_ativos || 0;
                tableHTML += `<td>
                    <span class="badge bg-primary">${totalIndicacoes} total</span>
                    <span class="badge bg-success ms-1">${totalAtivos} ativos</span>
                </td>`;
                
                tableHTML += '</tr>';
            });

            tableHTML += '</tbody></table></div>';
            container.innerHTML = tableHTML;

            inicializarDataTable();
        }

        // Inicializar DataTable
        function inicializarDataTable() {
            // Destruir DataTable anterior se existir
            if (dataTable) {
                dataTable.destroy();
            }

            // Inicializar novo DataTable
            dataTable = $('#reportTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                pageLength: 25,
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Copiar',
                        className: 'btn btn-sm btn-secondary'
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: `Relatório_${currentReportType}_${new Date().toISOString().split('T')[0]}`
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-sm btn-info',
                        title: '',
                        messageTop: function() {
                            const tipoRelatorio = document.getElementById('tipoRelatorio').value;
                            const tipoTexto = {
                                'desfiliacoes': 'Desfiliações',
                                'aniversariantes': 'Aniversariantes',
                                'novos_cadastros': 'Novos Cadastros',
                                'indicacoes': 'Indicações'
                            };
                            
                            // Pega as datas selecionadas pelo usuário
                            const dataInicio = document.getElementById('dataInicio').value;
                            const dataFim = document.getElementById('dataFim').value;
                            
                            let periodoTexto = '';
                            if (dataInicio && dataFim) {
                                // Formata as datas para dd/mm/yyyy
                                const inicioFormatado = dataInicio.split('-').reverse().join('/');
                                const fimFormatado = dataFim.split('-').reverse().join('/');
                                periodoTexto = `${inicioFormatado} até ${fimFormatado}`;
                            } else {
                                periodoTexto = new Date().toLocaleDateString('pt-BR');
                            }
                            
                            // ✅ TÍTULO BONITO EM AZUL
                            return `<h2 style="text-align: center; margin-bottom: 25px; color: #0056d2; font-weight: bold; font-size: 22px;">
                                        Relatório de ${tipoTexto[tipoRelatorio] || 'Relatório'}
                                    </h2>
                                    <p style="text-align: center; margin-bottom: 20px; color: #666; font-size: 14px;">
                                        ${periodoTexto}
                                    </p>`;
                        },
                        customize: function(win) {
                            // ✅ CUSTOMIZAÇÃO DA IMPRESSÃO - BONITO, LIMPO E PROFISSIONAL
                            
                            $(win.document.body).css({
                                'background': 'white',
                                'padding': '20px',
                                'font-family': 'Arial, sans-serif'
                            });
                            
                            // ✅ Estilo da tabela - FONTES MAIORES
                            $(win.document.body).find('table')
                                .addClass('compact')
                                .css({
                                    'font-size': '11px',      // ✅ FONTE MAIOR
                                    'border-collapse': 'collapse',
                                    'width': '100%',
                                    'margin-top': '20px'
                                });
                            
                            // ✅ Header da tabela - PRETO com texto branco
                            $(win.document.body).find('table thead tr')
                                .css({
                                    'background': '#000000',
                                    'border-bottom': '3px solid #000'
                                });
                            
                            $(win.document.body).find('table thead th')
                                .css({
                                    'background': '#000000',
                                    'color': '#ffffff',
                                    'font-weight': '700',
                                    'font-size': '13px',
                                    'padding': '12px 10px',
                                    'border': '1px solid #000',
                                    'text-align': 'center',
                                    'text-transform': 'uppercase',
                                    'letter-spacing': '0.5px'
                                });
                            
                            // ✅ Linhas da tabela - alternância suave
                            $(win.document.body).find('table tbody tr:even')
                                .css('background', '#fafafa');
                            
                            $(win.document.body).find('table tbody tr:odd')
                                .css('background', 'white');
                            
                            // ✅ Células da tabela - PRETO e FONTE MAIOR
                            $(win.document.body).find('table tbody td')
                                .css({
                                    'padding': '8px 8px',
                                    'font-size': '11px',     // ✅ FONTE MAIOR
                                    'color': '#000000',      // ✅ PRETO
                                    'border': '1px solid #e0e0e0',
                                    'vertical-align': 'middle'
                                });
                            
                            // ✅ CPF em PRETO
                            $(win.document.body).find('table tbody td:nth-child(3)')
                                .css({
                                    'color': '#000000',      // ✅ PRETO
                                    'font-size': '11px'      // ✅ FONTE MAIOR
                                });
                            
                            // Primeira coluna centralizada
                            $(win.document.body).find('table tbody td:first-child, table thead th:first-child')
                                .css({
                                    'text-align': 'center',
                                    'font-weight': '600'
                                });
                            
                            // Badge N/D para impressão
                            $(win.document.body).find('.badge-nd')
                                .css({
                                    'background': '#fff3cd',
                                    'color': '#856404',
                                    'padding': '3px 8px',
                                    'border-radius': '4px',
                                    'font-size': '10px',
                                    'font-weight': '600',
                                    'border': '1px solid #ffc107',
                                    'display': 'inline-block'
                                });
                        }
                    }
                ]
            });
        }

        // Obter headers da tabela
        function getTableHeaders(tipo) {
            const headers = {
                'desfiliacoes': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'cpf', label: 'CPF', type: 'cpf' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'data_desfiliacao', label: 'Data Desfiliação', type: 'date' }
                ],
                'aniversariantes': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'data_nascimento', label: 'Data Nascimento', type: 'date' },
                    { key: 'idade', label: 'Idade', type: 'number' },
                    { key: 'situacao', label: 'Situação', type: 'text' },
                    { key: 'rg', label: 'RG', type: 'text' },
                    { key: 'endereco', label: 'Endereço', type: 'text' },
                    { key: 'numero', label: 'Nº', type: 'text' },
                    { key: 'bairro', label: 'Bairro', type: 'text' },
                    { key: 'cidade', label: 'Cidade', type: 'text' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'telefone', label: 'Telefone', type: 'phone' }
                ],
                'novos_cadastros': [
                    { key: 'nome', label: 'Nome', type: 'text' },
                    { key: 'cpf', label: 'CPF', type: 'cpf' },
                    { key: 'patente', label: 'Patente', type: 'text' },
                    { key: 'corporacao', label: 'Corporação', type: 'text' },
                    { key: 'indicado_por', label: 'Indicado Por', type: 'text' },
                    { key: 'data_aprovacao', label: 'Data Cadastro', type: 'date' },
                    { key: 'tipo_cadastro', label: 'Tipo', type: 'text' }
                ]
            };
            return headers[tipo] || [];
        }

        // Formatar valores
        function formatValue(value, type) {
            // Tratamento completo de valores inválidos
            if (!value || 
                value === null || 
                value === '' || 
                value === undefined ||
                value === 'null' ||
                value === 'undefined' ||
                value === 'undefined//' ||
                String(value).toLowerCase() === 'null' ||
                String(value).toLowerCase() === 'undefined' ||
                String(value).includes('undefined')) {
                return 'N/D';
            }

            switch (type) {
                case 'date':
                    return formatDate(value);
                case 'cpf':
                    return formatCPF(value);
                case 'phone':
                    return formatPhone(value);
                case 'number':
                    const num = parseInt(value);
                    if (isNaN(num)) return 'N/D';
                    return num.toLocaleString('pt-BR');
                default:
                    return value || 'N/D';
            }
        }

        // Formatar data
        function formatDate(date) {
            // ✅ CORREÇÃO: Tratar todos os casos de data inválida ou ausente
            if (!date || 
                date === '0000-00-00' || 
                date === '' || 
                date === 'N/D' || 
                date === null || 
                date === undefined ||
                date === 'null' ||
                date === 'undefined' ||
                date === 'undefined//' ||
                String(date).toLowerCase() === 'null' ||
                String(date).toLowerCase() === 'undefined' ||
                String(date).includes('undefined')) {
                return '<span class="badge-nd">N/D</span>';
            }
            
            // Converter para string caso não seja
            date = String(date);
            
            // Se já estiver no formato dd/mm/yyyy, retornar como está
            if (date.includes('/') && !date.includes('undefined')) return date;
            
            // Se estiver no formato yyyy-mm-dd, converter
            if (date.includes('-')) {
                const [year, month, day] = date.split('-');
                if (year && month && day && year !== '0000') {
                    return `${day}/${month}/${year}`;
                }
            }
            
            return '<span class="badge-nd">N/D</span>';
        }

        // Formatar CPF
        function formatCPF(cpf) {
            if (!cpf) return '-';
            cpf = cpf.toString().replace(/\D/g, '');
            if (cpf.length === 11) {
                return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            }
            return cpf || '-';
        }

        // Formatar telefone
        function formatPhone(phone) {
            if (!phone) return '-';
            phone = phone.toString().replace(/\D/g, '');
            if (phone.length === 11) {
                return phone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
            } else if (phone.length === 10) {
                return phone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
            }
            return phone || '-';
        }

        // Renderizar relatório de FILIAÇÕES (especial com tabela de comissões)
        function renderizarRelatorioFiliacoes(response) {
            const container = document.getElementById('resultsContainer');
            const data = response.data || [];
            const totais = response.totais || {};
            const tabelaValores = response.tabela_valores || [];
            const periodo = response.periodo || {};

            // Atualizar título
            document.getElementById('tituloResultado').textContent = `Filiações - ${data.length} indicadores`;
            document.getElementById('totalRegistros').textContent = data.length;
            document.getElementById('totalRegistros').style.display = 'inline-block';

            if (data.length === 0) {
                container.innerHTML = `
                    <div class="alert-custom alert-info-custom">
                        <i class="fas fa-exclamation-circle fa-lg"></i>
                        <div>
                            <strong>Nenhuma filiação encontrada no período</strong><br>
                            Período: ${periodo.inicio_formatado || ''} até ${periodo.fim_formatado || ''}
                        </div>
                    </div>
                `;
                return;
            }

            // Criar HTML do relatório
            let html = `
                <div class="filiacoes-report">
                    <!-- Período do relatório -->
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <strong>Período:</strong> ${periodo.inicio_formatado || ''} até ${periodo.fim_formatado || ''}
                    </div>

                    <!-- Tabela Principal - Resumo por Indicador -->
                    <div class="card mb-4">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                RESUMO FILIAÇÕES POR TIPO / BÔNUS POR DIRETOR/REPRESENTANTE/VENDEDOR
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="reportTableFiliacoes">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th rowspan="2" class="align-middle text-center" style="min-width: 200px;">DIRETOR / REPRESENTANTE</th>
                                            <th colspan="4" class="text-center bg-light">TIPOS DE FILIAÇÃO</th>
                                            <th rowspan="2" class="align-middle text-center">QTD<br>TOTAL</th>
                                            <th rowspan="2" class="align-middle text-center" style="min-width: 120px;">COMISSÃO</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center">Social</th>
                                            <th class="text-center">Jurídico<br>+Social</th>
                                            <th class="text-center">Aluno<br>Sd</th>
                                            <th class="text-center">Agregado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
            `;

            // Adicionar linhas de dados
            data.forEach((row, index) => {
                html += `
                    <tr>
                        <td><strong>${row.indicador_nome || 'Não identificado'}</strong></td>
                        <td class="text-center">${row.qtd_social || 0}</td>
                        <td class="text-center">${row.qtd_juridico_social || 0}</td>
                        <td class="text-center">${row.qtd_aluno_sd || 0}</td>
                        <td class="text-center">${row.qtd_agregado || 0}</td>
                        <td class="text-center"><strong>${row.qtd_total || 0}</strong></td>
                        <td class="text-end text-success"><strong>${row.comissao_formatada || 'R$ 0,00'}</strong></td>
                    </tr>
                `;
            });

            // Linha de totais
            html += `
                                    </tbody>
                                    <tfoot class="table-dark">
                                        <tr>
                                            <td class="text-center"><strong>TOTAL</strong></td>
                                            <td class="text-center"><strong>${totais.social || 0}</strong></td>
                                            <td class="text-center"><strong>${totais.juridico_social || 0}</strong></td>
                                            <td class="text-center"><strong>${totais.aluno_sd || 0}</strong></td>
                                            <td class="text-center"><strong>${totais.agregado || 0}</strong></td>
                                            <td class="text-center"><strong>${totais.total || 0}</strong></td>
                                            <td class="text-end"><strong>${totais.comissao_formatada || 'R$ 0,00'}</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de Valores de Referência -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-dollar-sign me-2"></i>
                                TABELA DE VALORES (Troca Direto de Banco)
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th class="text-center" style="width: 40%;">TIPO</th>
                                            <th class="text-center" style="width: 30%;">MENSALIDADE</th>
                                            <th class="text-center" style="width: 30%;">COMISSÃO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
            `;

            // Adicionar valores da tabela
            tabelaValores.forEach(item => {
                html += `
                    <tr>
                        <td>${item.tipo}</td>
                        <td class="text-end">${item.mensalidade_formatada}</td>
                        <td class="text-end text-success"><strong>${item.comissao_formatada}</strong></td>
                    </tr>
                `;
            });

            html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Botão de Exportar PDF -->
                    <div class="d-flex justify-content-center mt-4">
                        <button type="button" class="btn btn-danger btn-lg" onclick="exportarPDFFiliacoes()">
                            <i class="fas fa-file-pdf me-2"></i>
                            Exportar PDF do Relatório
                        </button>
                    </div>

                    <!-- Observações -->
                    <div class="alert alert-secondary mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Observações:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Só Diretor/Representante pode indicar</li>
                            <li>Se aparecer departamento, verificar dados</li>
                        </ul>
                    </div>
                </div>
            `;

            container.innerHTML = html;

            // Inicializar DataTable para a tabela principal
            if ($.fn.DataTable.isDataTable('#reportTableFiliacoes')) {
                $('#reportTableFiliacoes').DataTable().destroy();
            }
            
            dataTable = $('#reportTableFiliacoes').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                pageLength: 25,
                responsive: true,
                ordering: true,
                order: [[5, 'desc']], // Ordenar por total
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Copiar',
                        className: 'btn btn-sm btn-secondary'
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: `Relatorio_Filiacoes_${new Date().toISOString().split('T')[0]}`
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-sm btn-info'
                    }
                ]
            });
        }

        // Exportar PDF do relatório de Filiações
        function exportarPDFFiliacoes() {
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;
            
            if (!dataInicio || !dataFim) {
                showToast('Por favor, selecione as datas do período', 'warning');
                return;
            }
            
            // Abrir PDF em nova aba
            const url = `../api/relatorios/gerar_pdf_filiacoes.php?data_inicio=${dataInicio}&data_fim=${dataFim}`;
            window.open(url, '_blank');
            
            showToast('Gerando PDF do relatório...', 'success');
        }

        // Limpar filtros
        function limparFiltros() {
            document.getElementById('formFiltros').reset();
            $('.select2').val(null).trigger('change');
            document.getElementById('dataInicio').value = new Date().toISOString().slice(0, 8) + '01';
            document.getElementById('dataFim').value = new Date().toISOString().slice(0, 10);
            showToast('Filtros limpos com sucesso!', 'info');
        }

        // Exportar relatório
        function exportarRelatorio(formato) {
            if (!currentReportData || !currentReportData.data || currentReportData.data.length === 0) {
                showToast('Nenhum relatório para exportar', 'warning');
                return;
            }

            // Usar DataTables export
            if (dataTable) {
                switch(formato) {
                    case 'excel':
                        dataTable.button('.buttons-excel').trigger();
                        break;
                }
                showToast(`Exportando para ${formato.toUpperCase()}...`, 'success');
            }
        }

        // Imprimir relatório
        function imprimirRelatorio() {
            if (dataTable) {
                dataTable.button('.buttons-print').trigger();
            } else {
                window.print();
            }
        }

        // Mostrar erro
        function mostrarErro(mensagem) {
            document.getElementById('resultsContainer').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Erro:</strong> ${mensagem}
                </div>
            `;
            document.getElementById('tituloResultado').textContent = 'Erro ao gerar relatório';
            showToast(mensagem, 'danger');
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
    </script>
</body>
</html>