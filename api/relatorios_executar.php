<?php
/**
 * API para executar relatório e gerar saída
 * api/relatorios_executar.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Inicia sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Acesso negado. Usuário não autenticado.');
}

// Obtém dados do usuário
$usuarioLogado = $auth->getUser();

// Processa dados do formulário
$tipo = $_POST['tipo'] ?? $_GET['tipo'] ?? '';
$campos = $_POST['campos'] ?? $_GET['campos'] ?? [];
$formato = $_POST['formato'] ?? $_GET['formato'] ?? 'html';
$modeloId = $_POST['modelo_id'] ?? $_GET['modelo_id'] ?? null;

// Validações básicas
if (empty($tipo) && empty($modeloId)) {
    die('Tipo de relatório ou modelo não informado.');
}

if (empty($campos) && empty($modeloId)) {
    die('Nenhum campo selecionado para o relatório.');
}

// Monta parâmetros/filtros
$parametros = [];
foreach ($_POST as $key => $value) {
    if (!in_array($key, ['tipo', 'campos', 'formato', 'salvar_modelo', 'nome_modelo', 'modelo_id'])) {
        if (!empty($value)) {
            $parametros[$key] = $value;
        }
    }
}
foreach ($_GET as $key => $value) {
    if (!in_array($key, ['tipo', 'campos', 'formato', 'salvar_modelo', 'nome_modelo', 'modelo_id'])) {
        if (!empty($value)) {
            $parametros[$key] = $value;
        }
    }
}

try {
    // Se há um modelo_id, carrega e executa o modelo
    if ($modeloId) {
        // Implementar carregamento de modelo salvo quando necessário
        $resultado = [];
    } else {
        // Executa relatório sem modelo (temporário)
        $modeloTemp = [
            'tipo' => $tipo,
            'campos' => $campos,
            'filtros' => $parametros,
            'ordenacao' => $_POST['ordenacao'] ?? $_GET['ordenacao'] ?? null
        ];
        
        $resultado = executarRelatorioTemporario($modeloTemp);
    }
    
    // Processa formato de saída
    switch ($formato) {
        case 'excel':
            gerarExcel($resultado);
            break;
            
        case 'csv':
            gerarCSV($resultado);
            break;
            
        case 'pdf':
            gerarPDF($resultado);
            break;
            
        default: // html
            gerarHTML($resultado);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro ao executar relatório: " . $e->getMessage());
    die('Erro ao gerar relatório: ' . $e->getMessage());
}

/**
 * Executa relatório temporário (sem modelo salvo)
 */
function executarRelatorioTemporario($config) {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Constrói query baseada na configuração
    $query = construirQuery($config);
    
    // Executa query
    $stmt = $db->prepare($query['sql']);
    $stmt->execute($query['params']);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'modelo' => $config,
        'dados' => $dados,
        'total' => count($dados),
        'parametros' => $config['filtros'] ?? []
    ];
}

/**
 * Constrói query SQL baseada na configuração
 */
function construirQuery($config) {
    $tipo = $config['tipo'];
    $campos = $config['campos'];
    $filtros = $config['filtros'] ?? [];
    $ordenacao = $config['ordenacao'] ?? '';
    
    // Mapear tabelas principais
    $tabelasPrincipais = [
        'associados' => 'Associados a',
        'financeiro' => 'Financeiro f',
        'militar' => 'Militar m',
        'servicos' => 'Servicos_Associado sa',
        'documentos' => 'Documentos_Associado da'
    ];
    
    $tabelaPrincipal = $tabelasPrincipais[$tipo] ?? 'Associados a';
    
    // Construir SELECT
    $selectCampos = [];
    $where = ['1=1'];
    $params = [];
    
    // Adiciona campos básicos sempre
    if ($tipo !== 'associados') {
        $selectCampos[] = 'a.id';
        $selectCampos[] = 'a.nome';
        $selectCampos[] = 'a.cpf';
    }
    
    // Adiciona campos selecionados
    foreach ($campos as $campo) {
        $campoSQL = mapearCampo($campo, $tipo);
        if ($campoSQL && !in_array($campoSQL, $selectCampos)) {
            $selectCampos[] = $campoSQL;
        }
    }
    
    // Obtém JOINs necessários
    $joinsInfo = obterJoins($tipo, $campos);
    $joins = $joinsInfo['joins'];
    $tabelasAdicionadas = $joinsInfo['tabelas'];
    
    // Verifica se precisa adicionar JOIN com Contrato para filtros de data
    if (($tipo === 'associados' || $tipo === 'financeiro' || $tipo === 'militar') && 
        (!empty($filtros['data_inicio']) || !empty($filtros['data_fim'])) &&
        !isset($tabelasAdicionadas['Contrato'])) {
        $joins[] = "LEFT JOIN Contrato c ON a.id = c.associado_id";
        $tabelasAdicionadas['Contrato'] = true;
    }
    
    // Aplica filtros
    $whereFiltros = aplicarFiltros($tipo, $filtros, $params);
    $where = array_merge($where, $whereFiltros);
    
    // Monta SQL
    $sql = "SELECT DISTINCT " . implode(", ", $selectCampos) . "\n";
    $sql .= "FROM " . $tabelaPrincipal . "\n";
    $sql .= implode("\n", $joins);
    $sql .= "\nWHERE " . implode(" AND ", $where);
    
    // Ordenação
    if ($ordenacao) {
        $sql .= "\nORDER BY " . $ordenacao;
    } else {
        $sql .= "\nORDER BY a.nome ASC";
    }
    
    return [
        'sql' => $sql,
        'params' => $params
    ];
}

/**
 * Mapeia campo para SQL
 */
function mapearCampo($campo, $tipo) {
    $mapeamento = [
        // Campos de Associados
        'nome' => 'a.nome',
        'cpf' => 'a.cpf',
        'rg' => 'a.rg',
        'nasc' => 'a.nasc',
        'sexo' => 'a.sexo',
        'email' => 'a.email',
        'telefone' => 'a.telefone',
        'situacao' => 'a.situacao',
        'escolaridade' => 'a.escolaridade',
        'estadoCivil' => 'a.estadoCivil',
        'indicacao' => 'a.indicacao',
        
        // Campos de Militar
        'corporacao' => 'm.corporacao',
        'patente' => 'm.patente',
        'categoria' => 'm.categoria',
        'lotacao' => 'm.lotacao',
        'unidade' => 'm.unidade',
        
        // Campos de Financeiro
        'tipoAssociado' => 'f.tipoAssociado',
        'situacaoFinanceira' => 'f.situacaoFinanceira',
        'vinculoServidor' => 'f.vinculoServidor',
        'localDebito' => 'f.localDebito',
        'agencia' => 'f.agencia',
        'operacao' => 'f.operacao',
        'contaCorrente' => 'f.contaCorrente',
        
        // Campos de Contrato
        'dataFiliacao' => 'c.dataFiliacao',
        'dataDesfiliacao' => 'c.dataDesfiliacao',
        
        // Campos de Endereço
        'cep' => 'e.cep',
        'endereco' => 'e.endereco',
        'numero' => 'e.numero',
        'bairro' => 'e.bairro',
        'cidade' => 'e.cidade',
        'complemento' => 'e.complemento',
        
        // Campos de Serviços
        'servico_nome' => 's.nome as servico_nome',
        'valor_aplicado' => 'sa.valor_aplicado',
        'percentual_aplicado' => 'sa.percentual_aplicado',
        'data_adesao' => 'sa.data_adesao',
        'ativo' => 'sa.ativo',
        
        // Campos de Documentos
        'tipo_documento' => 'da.tipo_documento',
        'nome_arquivo' => 'da.nome_arquivo',
        'data_upload' => 'da.data_upload',
        'verificado' => 'da.verificado',
        'funcionario_nome' => 'func.nome as funcionario_nome',
        'observacao' => 'da.observacao',
        'lote_id' => 'da.lote_id',
        'lote_status' => 'ld.status as lote_status'
    ];
    
    return $mapeamento[$campo] ?? null;
}

/**
 * Obtém JOINs necessários
 */
function obterJoins($tipo, $campos) {
    $joins = [];
    $camposStr = implode(',', $campos);
    
    // Array para rastrear tabelas já adicionadas
    $tabelasAdicionadas = [];
    
    // JOINs base por tipo
    switch ($tipo) {
        case 'associados':
            // Sempre associado como base
            break;
            
        case 'financeiro':
            $joins[] = "JOIN Associados a ON f.associado_id = a.id";
            $tabelasAdicionadas['Associados'] = true;
            break;
            
        case 'militar':
            $joins[] = "JOIN Associados a ON m.associado_id = a.id";
            $tabelasAdicionadas['Associados'] = true;
            break;
            
        case 'servicos':
            $joins[] = "JOIN Associados a ON sa.associado_id = a.id";
            $joins[] = "JOIN Servicos s ON sa.servico_id = s.id";
            $tabelasAdicionadas['Associados'] = true;
            $tabelasAdicionadas['Servicos'] = true;
            break;
            
        case 'documentos':
            $joins[] = "JOIN Associados a ON da.associado_id = a.id";
            $tabelasAdicionadas['Associados'] = true;
            break;
    }
    
    // JOINs condicionais baseados nos campos
    
    // Militar
    if (!isset($tabelasAdicionadas['Militar']) && $tipo !== 'militar' &&
        (strpos($camposStr, 'corporacao') !== false || 
         strpos($camposStr, 'patente') !== false || 
         strpos($camposStr, 'categoria') !== false ||
         strpos($camposStr, 'lotacao') !== false ||
         strpos($camposStr, 'unidade') !== false)) {
        $joins[] = "LEFT JOIN Militar m ON a.id = m.associado_id";
        $tabelasAdicionadas['Militar'] = true;
    }
    
    // Financeiro
    if (!isset($tabelasAdicionadas['Financeiro']) && $tipo !== 'financeiro' &&
        (strpos($camposStr, 'tipoAssociado') !== false || 
         strpos($camposStr, 'situacaoFinanceira') !== false ||
         strpos($camposStr, 'vinculoServidor') !== false ||
         strpos($camposStr, 'localDebito') !== false ||
         strpos($camposStr, 'agencia') !== false ||
         strpos($camposStr, 'operacao') !== false ||
         strpos($camposStr, 'contaCorrente') !== false)) {
        $joins[] = "LEFT JOIN Financeiro f ON a.id = f.associado_id";
        $tabelasAdicionadas['Financeiro'] = true;
    }
    
    // Contrato
    if (!isset($tabelasAdicionadas['Contrato']) &&
        (strpos($camposStr, 'dataFiliacao') !== false || 
         strpos($camposStr, 'dataDesfiliacao') !== false)) {
        $joins[] = "LEFT JOIN Contrato c ON a.id = c.associado_id";
        $tabelasAdicionadas['Contrato'] = true;
    }
    
    // Endereço
    if (!isset($tabelasAdicionadas['Endereco']) &&
        (strpos($camposStr, 'cep') !== false || 
         strpos($camposStr, 'endereco') !== false ||
         strpos($camposStr, 'bairro') !== false ||
         strpos($camposStr, 'cidade') !== false ||
         strpos($camposStr, 'complemento') !== false)) {
        $joins[] = "LEFT JOIN Endereco e ON a.id = e.associado_id";
        $tabelasAdicionadas['Endereco'] = true;
    }
    
    // Funcionários
    if (!isset($tabelasAdicionadas['Funcionarios']) &&
        strpos($camposStr, 'funcionario_nome') !== false) {
        $joins[] = "LEFT JOIN Funcionarios func ON da.funcionario_id = func.id";
        $tabelasAdicionadas['Funcionarios'] = true;
    }
    
    // Lotes Documentos
    if (!isset($tabelasAdicionadas['Lotes_Documentos']) &&
        strpos($camposStr, 'lote_status') !== false) {
        $joins[] = "LEFT JOIN Lotes_Documentos ld ON da.lote_id = ld.id";
        $tabelasAdicionadas['Lotes_Documentos'] = true;
    }
    
    return ['joins' => $joins, 'tabelas' => $tabelasAdicionadas];
}

/**
 * Aplica filtros à query
 */
function aplicarFiltros($tipo, $filtros, &$params) {
    $where = [];
    
    // Filtros de data - usando campos apropriados por tipo
    if (!empty($filtros['data_inicio']) || !empty($filtros['data_fim'])) {
        $campoData = null;
        
        // Define o campo de data apropriado por tipo
        switch ($tipo) {
            case 'associados':
            case 'financeiro':
            case 'militar':
                // Para estes tipos, usa data de filiação do contrato
                $campoData = 'c.dataFiliacao';
                break;
            case 'servicos':
                $campoData = 'sa.data_adesao';
                break;
            case 'documentos':
                $campoData = 'da.data_upload';
                break;
        }
        
        if ($campoData) {
            if (!empty($filtros['data_inicio'])) {
                $where[] = "DATE($campoData) >= ?";
                $params[] = $filtros['data_inicio'];
            }
            
            if (!empty($filtros['data_fim'])) {
                $where[] = "DATE($campoData) <= ?";
                $params[] = $filtros['data_fim'];
            }
        }
    }
    
    // Filtros específicos
    if (!empty($filtros['situacao'])) {
        $where[] = "a.situacao = ?";
        $params[] = $filtros['situacao'];
    }
    
    // Filtros que dependem da tabela Militar
    if (!empty($filtros['corporacao']) || !empty($filtros['patente'])) {
        if ($tipo === 'militar' || $tipo === 'associados') {
            if (!empty($filtros['corporacao'])) {
                $where[] = "m.corporacao = ?";
                $params[] = $filtros['corporacao'];
            }
            
            if (!empty($filtros['patente'])) {
                $where[] = "m.patente = ?";
                $params[] = $filtros['patente'];
            }
        }
    }
    
    // Filtros que dependem da tabela Financeiro
    if (!empty($filtros['tipo_associado']) || !empty($filtros['situacaoFinanceira'])) {
        if ($tipo === 'financeiro' || $tipo === 'associados') {
            if (!empty($filtros['tipo_associado'])) {
                $where[] = "f.tipoAssociado = ?";
                $params[] = $filtros['tipo_associado'];
            }
            
            if (!empty($filtros['situacaoFinanceira'])) {
                $where[] = "f.situacaoFinanceira = ?";
                $params[] = $filtros['situacaoFinanceira'];
            }
        }
    }
    
    // Filtros específicos de serviços
    if ($tipo === 'servicos') {
        if (!empty($filtros['servico_id'])) {
            $where[] = "sa.servico_id = ?";
            $params[] = $filtros['servico_id'];
        }
        
        if (isset($filtros['ativo']) && $filtros['ativo'] !== '') {
            $where[] = "sa.ativo = ?";
            $params[] = $filtros['ativo'];
        }
    }
    
    // Filtros específicos de documentos
    if ($tipo === 'documentos') {
        if (!empty($filtros['tipo_documento'])) {
            $where[] = "da.tipo_documento = ?";
            $params[] = $filtros['tipo_documento'];
        }
        
        if (isset($filtros['verificado']) && $filtros['verificado'] !== '') {
            $where[] = "da.verificado = ?";
            $params[] = $filtros['verificado'];
        }
    }
    
    // Busca geral - sempre usa tabela associados
    if (!empty($filtros['busca'])) {
        $busca = "%{$filtros['busca']}%";
        $where[] = "(a.nome LIKE ? OR a.cpf LIKE ? OR a.rg LIKE ?)";
        $params[] = $busca;
        $params[] = $busca;
        $params[] = $busca;
    }
    
    return $where;
}

/**
 * Gera saída HTML
 */
function gerarHTML($resultado) {
    global $usuarioLogado;
    $modelo = $resultado['modelo'] ?? [];
    $dados = $resultado['dados'] ?? [];
    $total = $resultado['total'] ?? 0;
    
    // Título do relatório
    $titulo = $modelo['nome'] ?? 'Relatório de ' . ucfirst($modelo['tipo'] ?? '');
    
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?> - ASSEGO</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0056D2;
            --primary-dark: #003db3;
            --primary-light: #1a6bdb;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8fafc;
            --border-color: #e1e5e9;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-md: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        @media print {
            .no-print { display: none !important; }
            body { 
                font-size: 11pt; 
                background: white !important;
                color: black !important;
            }
            .container-fluid { max-width: 100% !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .table-report { font-size: 9pt; }
            .header-report { background: #0056D2 !important; }
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e1e5e9;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        /* Header */
        .header-report {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .header-report::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        .header-report h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .header-report .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .header-report .badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        /* Cards */
        .card {
            background: white;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-bottom: 2px solid var(--border-color);
            padding: 1.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }

        /* Action Buttons */
        .action-bar {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            text-transform: none;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-modern:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-modern:hover:before {
            width: 300px;
            height: 300px;
        }

        .btn-modern i {
            transition: transform 0.3s ease;
        }

        .btn-modern:hover i {
            transform: scale(1.1);
        }

        .btn-primary.btn-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-success.btn-modern {
            background: linear-gradient(135deg, var(--success-color) 0%, #1e7e34 100%);
            color: white;
        }

        .btn-secondary.btn-modern {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
            color: white;
        }

        .btn-info.btn-modern {
            background: linear-gradient(135deg, var(--info-color) 0%, #117a8b 100%);
            color: white;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary-color);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        /* Filters */
        .filters-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .filter-badge {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.25rem;
            border: none;
            transition: var(--transition);
        }

        .filter-badge:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-sm);
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table-header {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .table-report {
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        .table-report th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 1rem 0.75rem;
            border: none;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-report td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            transition: var(--transition);
        }

        .table-report tbody tr {
            transition: var(--transition);
        }

        .table-report tbody tr:hover {
            background: #f8fafc;
            transform: scale(1.001);
        }

        .table-report tbody tr:hover td {
            border-color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--secondary-color);
            font-size: 2rem;
        }

        .empty-state h3 {
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #94a3b8;
        }

        /* Footer */
        .footer-report {
            margin-top: 3rem;
            padding: 2rem;
            text-align: center;
            color: var(--secondary-color);
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-report {
                padding: 2rem 0;
            }
            
            .header-report h1 {
                font-size: 1.75rem;
            }
            
            .action-bar {
                padding: 1rem;
            }
            
            .btn-modern {
                padding: 0.625rem 1rem;
                font-size: 0.875rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .table-report {
                font-size: 0.75rem;
            }
            
            .table-report th,
            .table-report td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease-out;
        }

        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Progress indicator for large tables */
        .table-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            background: var(--primary-color);
            z-index: 9999;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <p class="mt-3 text-muted">Carregando relatório...</p>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="table-progress" id="progressBar" style="width: 0%;"></div>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="header-report no-print fade-in-up">
            <div class="container-fluid">
                <div class="header-content">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1><i class="fas fa-chart-line me-3"></i><?php echo htmlspecialchars($titulo); ?></h1>
                            <p class="subtitle mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Gerado em <?php echo date('d/m/Y \à\s H:i'); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <span class="badge fs-6 px-3 py-2">
                                <i class="fas fa-database me-2"></i>
                                Sistema ASSEGO
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Bar -->
        <div class="action-bar no-print fade-in-up" style="animation-delay: 0.1s;">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary btn-modern" onclick="window.print()" data-bs-toggle="tooltip" title="Imprimir relatório">
                        <i class="fas fa-print"></i>
                        <span>Imprimir</span>
                    </button>
                    <button class="btn btn-success btn-modern" onclick="exportarExcel()" data-bs-toggle="tooltip" title="Exportar para Excel">
                        <i class="fas fa-file-excel"></i>
                        <span>Excel</span>
                    </button>
                    <button class="btn btn-info btn-modern" onclick="exportarCSV()" data-bs-toggle="tooltip" title="Exportar para CSV">
                        <i class="fas fa-file-csv"></i>
                        <span>CSV</span>
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-secondary btn-modern" onclick="voltarFormulario()" data-bs-toggle="tooltip" title="Voltar ao formulário">
                        <i class="fas fa-arrow-left"></i>
                        <span>Voltar</span>
                    </button>
                    <button class="btn btn-secondary btn-modern" onclick="window.close()" data-bs-toggle="tooltip" title="Fechar janela">
                        <i class="fas fa-times"></i>
                        <span>Fechar</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid fade-in-up" style="animation-delay: 0.2s;">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-list-ol"></i>
                </div>
                <div class="stat-number" id="totalRecords"><?php echo number_format($total, 0, ',', '.'); ?></div>
                <div class="stat-label">Total de Registros</div>
            </div>
            
            <?php if (!empty($resultado['parametros'])): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="stat-number"><?php echo count(array_filter($resultado['parametros'])); ?></div>
                <div class="stat-label">Filtros Aplicados</div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo date('H:i'); ?></div>
                <div class="stat-label">Hora de Geração</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $nomeUsuario = explode(' ', $usuarioLogado['nome']);
                    echo htmlspecialchars($nomeUsuario[0]);
                    ?>
                </div>
                <div class="stat-label">Gerado por</div>
            </div>
        </div>
        
        <!-- Filters Applied -->
        <?php if (!empty($resultado['parametros'])): ?>
        <div class="filters-container fade-in-up" style="animation-delay: 0.3s;">
            <h5 class="mb-3">
                <i class="fas fa-sliders-h me-2 text-primary"></i>
                Filtros Aplicados
            </h5>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($resultado['parametros'] as $key => $value): ?>
                    <?php if (!empty($value)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-tag me-1"></i>
                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                        <?php echo htmlspecialchars($value); ?>
                    </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Data Table -->
        <?php if ($total > 0): ?>
        <div class="table-container fade-in-up" style="animation-delay: 0.4s;">
            <div class="table-header">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2 text-primary"></i>
                    Dados do Relatório
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-report" id="dataTable">
                    <thead>
                        <tr>
                            <?php if (!empty($dados[0])): ?>
                                <?php foreach (array_keys($dados[0]) as $coluna): ?>
                                <th>
                                    <i class="fas fa-sort me-1"></i>
                                    <?php echo formatarNomeColuna($coluna); ?>
                                </th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados as $index => $linha): ?>
                        <tr class="animate-on-scroll" style="animation-delay: <?php echo ($index * 0.01); ?>s;">
                            <?php foreach ($linha as $key => $valor): ?>
                            <td><?php echo formatarValor($valor, $key); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state fade-in-up" style="animation-delay: 0.4s;">
            <div class="empty-state-icon">
                <i class="fas fa-search"></i>
            </div>
            <h3>Nenhum registro encontrado</h3>
            <p>Não foram encontrados registros com os filtros aplicados. Tente ajustar os parâmetros de busca.</p>
            <button class="btn btn-primary btn-modern mt-3" onclick="voltarFormulario()">
                <i class="fas fa-arrow-left me-2"></i>
                Voltar ao Formulário
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer-report fade-in-up" style="animation-delay: 0.5s;">
            <div class="footer-logo">
                <i class="fas fa-building me-2"></i>
                ASSEGO
            </div>
            <p class="mb-1">Associação dos Servidores do Estado de Goiás</p>
            <p class="text-muted small mb-0">
                <i class="fas fa-user me-1"></i>
                Relatório gerado por <strong><?php echo htmlspecialchars($usuarioLogado['nome']); ?></strong> 
                em <strong><?php echo date('d/m/Y \à\s H:i:s'); ?></strong>
            </p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Loading overlay
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 500);
        });

        // Animate elements on scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll');
            const windowHeight = window.innerHeight;
            
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                
                if (elementTop < windowHeight - 100) {
                    element.classList.add('visible');
                }
            });
        }

        // Progress bar for scrolling through large tables
        function updateProgress() {
            const table = document.getElementById('dataTable');
            if (table) {
                const scrollTop = window.pageYOffset;
                const docHeight = document.body.offsetHeight;
                const winHeight = window.innerHeight;
                const scrollPercent = scrollTop / (docHeight - winHeight);
                const progressBar = document.getElementById('progressBar');
                progressBar.style.width = (scrollPercent * 100) + '%';
            }
        }

        // Event listeners
        window.addEventListener('scroll', function() {
            animateOnScroll();
            updateProgress();
        });

        // Initial animations
        animateOnScroll();

        // Export functions with loading states
        function exportarExcel() {
            showExportLoading('Excel');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            
            // Copy all parameters
            <?php foreach ($_POST as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $item): ?>
                    const input_<?php echo $key; ?>_<?php echo $item; ?> = document.createElement('input');
                    input_<?php echo $key; ?>_<?php echo $item; ?>.type = 'hidden';
                    input_<?php echo $key; ?>_<?php echo $item; ?>.name = '<?php echo $key; ?>[]';
                    input_<?php echo $key; ?>_<?php echo $item; ?>.value = '<?php echo $item; ?>';
                    form.appendChild(input_<?php echo $key; ?>_<?php echo $item; ?>);
                    <?php endforeach; ?>
                <?php else: ?>
                const input_<?php echo $key; ?> = document.createElement('input');
                input_<?php echo $key; ?>.type = 'hidden';
                input_<?php echo $key; ?>.name = '<?php echo $key; ?>';
                input_<?php echo $key; ?>.value = '<?php echo $value; ?>';
                form.appendChild(input_<?php echo $key; ?>);
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Change format to Excel
            const inputFormato = document.createElement('input');
            inputFormato.type = 'hidden';
            inputFormato.name = 'formato';
            inputFormato.value = 'excel';
            form.appendChild(inputFormato);
            
            document.body.appendChild(form);
            form.submit();
            
            hideExportLoading();
        }

        function exportarCSV() {
            showExportLoading('CSV');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            
            // Copy all parameters
            <?php foreach ($_POST as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $item): ?>
                    const input_<?php echo $key; ?>_<?php echo $item; ?> = document.createElement('input');
                    input_<?php echo $key; ?>_<?php echo $item; ?>.type = 'hidden';
                    input_<?php echo $key; ?>_<?php echo $item; ?>.name = '<?php echo $key; ?>[]';
                    input_<?php echo $key; ?>_<?php echo $item; ?>.value = '<?php echo $item; ?>';
                    form.appendChild(input_<?php echo $key; ?>_<?php echo $item; ?>);
                    <?php endforeach; ?>
                <?php else: ?>
                const input_<?php echo $key; ?>_csv = document.createElement('input');
                input_<?php echo $key; ?>_csv.type = 'hidden';
                input_<?php echo $key; ?>_csv.name = '<?php echo $key; ?>';
                input_<?php echo $key; ?>_csv.value = '<?php echo $value; ?>';
                form.appendChild(input_<?php echo $key; ?>_csv);
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Change format to CSV
            const inputFormato = document.createElement('input');
            inputFormato.type = 'hidden';
            inputFormato.name = 'formato';
            inputFormato.value = 'csv';
            form.appendChild(inputFormato);
            
            document.body.appendChild(form);
            form.submit();
            
            hideExportLoading();
        }

        function showExportLoading(format) {
            const exportButtons = document.querySelectorAll('.btn-success, .btn-info');
            exportButtons.forEach(btn => {
                btn.disabled = true;
                const originalText = btn.innerHTML;
                btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Exportando ${format}...`;
                btn.setAttribute('data-original', originalText);
            });
        }

        function hideExportLoading() {
            setTimeout(() => {
                const exportButtons = document.querySelectorAll('.btn-success, .btn-info');
                exportButtons.forEach(btn => {
                    btn.disabled = false;
                    const originalText = btn.getAttribute('data-original');
                    if (originalText) {
                        btn.innerHTML = originalText;
                    }
                });
            }, 2000);
        }

        function voltarFormulario() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.close();
            }
        }

        // Counter animation for statistics
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString('pt-BR');
            }, 20);
        }

        // Animate statistics when page loads
        window.addEventListener('load', function() {
            const totalElement = document.getElementById('totalRecords');
            if (totalElement) {
                const target = parseInt(totalElement.textContent.replace(/\./g, ''));
                totalElement.textContent = '0';
                setTimeout(() => animateCounter(totalElement, target), 1000);
            }
        });

        // Print optimization
        window.addEventListener('beforeprint', function() {
            document.body.classList.add('printing');
        });

        window.addEventListener('afterprint', function() {
            document.body.classList.remove('printing');
        });
    </script>
</body>
</html>
    <?php
}

/**
 * Gera saída CSV
 */
function gerarCSV($resultado) {
    $dados = $resultado['dados'] ?? [];
    $filename = 'relatorio_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    // Abre output
    $output = fopen('php://output', 'w');
    
    // Cabeçalhos
    if (!empty($dados)) {
        $headers = array_map('formatarNomeColuna', array_keys($dados[0]));
        fputcsv($output, $headers, ';');
        
        // Dados
        foreach ($dados as $linha) {
            fputcsv($output, $linha, ';');
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Gera saída Excel (simplificado - usando CSV com extensão .xls)
 */
function gerarExcel($resultado) {
    $dados = $resultado['dados'] ?? [];
    $filename = 'relatorio_' . date('Y-m-d_H-i-s') . '.xls';
    
    // Headers para Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // HTML Table para Excel
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '</head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Cabeçalhos
    if (!empty($dados)) {
        echo '<tr>';
        foreach (array_keys($dados[0]) as $coluna) {
            echo '<th style="background-color: #0056D2; color: white; font-weight: bold;">';
            echo htmlspecialchars(formatarNomeColuna($coluna));
            echo '</th>';
        }
        echo '</tr>';
        
        // Dados
        foreach ($dados as $linha) {
            echo '<tr>';
            foreach ($linha as $key => $valor) {
                echo '<td>';
                echo htmlspecialchars(formatarValor($valor, $key));
                echo '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Gera saída PDF (placeholder - requer biblioteca adicional)
 */
function gerarPDF($resultado) {
    // Para implementar PDF, você precisaria de uma biblioteca como:
    // - TCPDF
    // - DomPDF
    // - mPDF
    
    die('Exportação para PDF não implementada. Use HTML e imprima como PDF.');
}

/**
 * Formata nome da coluna para exibição
 */
function formatarNomeColuna($coluna) {
    $mapeamento = [
        'nome' => 'Nome',
        'cpf' => 'CPF',
        'rg' => 'RG',
        'nasc' => 'Data Nascimento',
        'sexo' => 'Sexo',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'situacao' => 'Situação',
        'escolaridade' => 'Escolaridade',
        'estadoCivil' => 'Estado Civil',
        'corporacao' => 'Corporação',
        'patente' => 'Patente',
        'categoria' => 'Categoria',
        'lotacao' => 'Lotação',
        'unidade' => 'Unidade',
        'tipoAssociado' => 'Tipo Associado',
        'situacaoFinanceira' => 'Situação Financeira',
        'vinculoServidor' => 'Vínculo Servidor',
        'localDebito' => 'Local Débito',
        'agencia' => 'Agência',
        'operacao' => 'Operação',
        'contaCorrente' => 'Conta Corrente',
        'dataFiliacao' => 'Data Filiação',
        'dataDesfiliacao' => 'Data Desfiliação',
        'cep' => 'CEP',
        'endereco' => 'Endereço',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'complemento' => 'Complemento',
        'servico_nome' => 'Serviço',
        'valor_aplicado' => 'Valor',
        'percentual_aplicado' => 'Percentual',
        'data_adesao' => 'Data Adesão',
        'ativo' => 'Ativo',
        'tipo_documento' => 'Tipo Documento',
        'nome_arquivo' => 'Arquivo',
        'data_upload' => 'Data Upload',
        'verificado' => 'Verificado',
        'funcionario_nome' => 'Verificado por',
        'observacao' => 'Observações'
    ];
    
    return $mapeamento[$coluna] ?? ucfirst(str_replace('_', ' ', $coluna));
}

/**
 * Formata valor para exibição
 */
function formatarValor($valor, $campo) {
    if ($valor === null || $valor === '') {
        return '<span class="text-muted">-</span>';
    }
    
    // Formatação por tipo de campo
    if (strpos($campo, 'data') !== false || strpos($campo, 'Data') !== false) {
        if ($valor !== '0000-00-00' && $valor !== '0000-00-00 00:00:00') {
            try {
                $data = new DateTime($valor);
                return '<span class="text-info"><i class="fas fa-calendar-alt me-1"></i>' . $data->format('d/m/Y') . '</span>';
            } catch (Exception $e) {
                return $valor;
            }
        }
        return '<span class="text-muted">-</span>';
    }
    
    if ($campo === 'cpf') {
        return '<span class="font-monospace">' . formatarCPF($valor) . '</span>';
    }
    
    if ($campo === 'telefone') {
        return '<span class="font-monospace"><i class="fas fa-phone me-1"></i>' . formatarTelefone($valor) . '</span>';
    }
    
    if ($campo === 'email') {
        return '<span class="text-primary"><i class="fas fa-envelope me-1"></i>' . htmlspecialchars($valor) . '</span>';
    }
    
    if ($campo === 'cep') {
        return '<span class="font-monospace">' . formatarCEP($valor) . '</span>';
    }
    
    if (strpos($campo, 'valor') !== false || strpos($campo, 'Valor') !== false) {
        return '<span class="text-success fw-bold"><i class="fas fa-dollar-sign me-1"></i>R$ ' . number_format($valor, 2, ',', '.') . '</span>';
    }
    
    if (strpos($campo, 'percentual') !== false) {
        return '<span class="text-warning fw-bold">' . number_format($valor, 2, ',', '.') . '%</span>';
    }
    
    if ($campo === 'ativo' || $campo === 'verificado') {
        if ($valor == 1) {
            return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Sim</span>';
        } else {
            return '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Não</span>';
        }
    }
    
    if ($campo === 'sexo') {
        if ($valor === 'M') {
            return '<span class="text-primary"><i class="fas fa-mars me-1"></i>Masculino</span>';
        } elseif ($valor === 'F') {
            return '<span class="text-danger"><i class="fas fa-venus me-1"></i>Feminino</span>';
        }
        return $valor;
    }
    
    if ($campo === 'situacao') {
        $statusColors = [
            'Ativo' => 'success',
            'Filiado' => 'success',
            'Inativo' => 'danger',
            'DESFILIADO' => 'danger',
            'Pendente' => 'warning',
            'PENDENTE' => 'warning',
            'Suspenso' => 'secondary'
        ];
        $color = $statusColors[$valor] ?? 'primary';
        return '<span class="badge bg-' . $color . '">' . htmlspecialchars($valor) . '</span>';
    }
    
    return htmlspecialchars($valor);
}

/**
 * Formata CPF
 */
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

/**
 * Formata telefone
 */
function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

/**
 * Formata CEP
 */
function formatarCEP($cep) {
    $cep = preg_replace('/[^0-9]/', '', $cep);
    if (strlen($cep) === 8) {
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }
    return $cep;
}
?>