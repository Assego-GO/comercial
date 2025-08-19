<?php
/**
 * Gera saída Excel ultra simples (HTML básico)
 */
function gerarExcelSimples($resultado) {
    error_log("=== INICIANDO GERAÇÃO EXCEL SIMPLES ===");
    
    if (!defined('EXPORT_MODE')) {
        define('EXPORT_MODE', true);
    }
    
    $dados = $resultado['dados'] ?? [];
    $total = count($dados);
    
    if (empty($dados)) {
        die('Erro: Nenhum dado disponível para exportação Excel.');
    }
    
    $filename = 'relatorio_simples_' . date('Y-m-d_H-i-s') . '.xls';
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    if (headers_sent($file, $line)) {
        die('Erro interno: Headers já enviados.');
    }
    
    // Headers mais básicos possíveis para Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    try {
        // HTML extremamente básico
        echo "<html>\n<body>\n";
        echo "<table>\n";
        
        // Informações básicas
        echo "<tr><td colspan='" . count(array_keys($dados[0])) . "'><b>Relatório - " . date('d/m/Y H:i:s') . "</b></td></tr>\n";
        echo "<tr><td colspan='" . count(array_keys($dados[0])) . "'>Registros: " . number_format($total, 0, ',', '.') . "</td></tr>\n";
        echo "<tr><td>&nbsp;</td></tr>\n";
        
        // Cabeçalhos
        echo "<tr>\n";
        foreach (array_keys($dados[0]) as $coluna) {
            echo "<td><b>" . formatarNomeColuna($coluna) . "</b></td>\n";
        }
        echo "</tr>\n";
        
        // Dados
        foreach ($dados as $linha) {
            echo "<tr>\n";
            foreach ($linha as $key => $valor) {
                $valorFormatado = formatarValor($valor, $key);
                $valorFormatado = strip_tags($valorFormatado);
                $valorFormatado = html_entity_decode($valorFormatado, ENT_QUOTES, 'UTF-8');
                echo "<td>" . $valorFormatado . "</td>\n";
            }
            echo "</tr>\n";
        }
        
        echo "</table>\n</body>\n</html>\n";
        
        error_log("=== EXCEL SIMPLES GERADO COM SUCESSO ===");
        
    } catch (Exception $e) {
        error_log("ERRO ao gerar Excel Simples: " . $e->getMessage());
        die('Erro ao gerar arquivo Excel: ' . $e->getMessage());
    }
    
    flush();
    exit;
}

/**
 * Gera saída Excel usando CSV mascarado (fallback mais confiável)
 */
function gerarExcelCSV($resultado) {
    error_log("=== INICIANDO GERAÇÃO EXCEL CSV ===");
    
    // Define modo de exportação para formatação limpa
    if (!defined('EXPORT_MODE')) {
        define('EXPORT_MODE', true);
    }
    
    $dados = $resultado['dados'] ?? [];
    $total = count($dados);
    
    if (empty($dados)) {
        die('Erro: Nenhum dado disponível para exportação Excel CSV.');
    }
    
    $filename = 'relatorio_csv_' . date('Y-m-d_H-i-s') . '.xls';
    
    // IMPORTANTE: Limpa qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verifica se headers já foram enviados
    if (headers_sent($file, $line)) {
        die('Erro interno: Headers já enviados. Não é possível gerar Excel.');
    }
    
    // Headers para Excel CSV (mais simples)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    header('Pragma: public');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    // Abre output
    $output = fopen('php://output', 'w');
    
    if (!$output) {
        die('Erro interno ao gerar Excel CSV.');
    }
    
    try {
        // Informações do cabeçalho
        $modelo = $resultado['modelo'] ?? [];
        $titulo = 'Relatório de ' . ucfirst($modelo['tipo'] ?? 'Dados');
        
        fputcsv($output, [$titulo], ',', '"');
        fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i:s')], ',', '"');
        fputcsv($output, ['Total: ' . number_format($total, 0, ',', '.')], ',', '"');
        fputcsv($output, [''], ',', '"'); // Linha vazia
        
        // Cabeçalhos das colunas
        $headers = array_map('formatarNomeColuna', array_keys($dados[0]));
        fputcsv($output, $headers, ',', '"');
        
        // Escreve dados
        foreach ($dados as $linha) {
            $linhaSemHTML = [];
            foreach ($linha as $key => $valor) {
                $valorFormatado = formatarValor($valor, $key);
                $valorFormatado = is_string($valorFormatado) ? strip_tags($valorFormatado) : $valorFormatado;
                $valorFormatado = html_entity_decode($valorFormatado, ENT_QUOTES, 'UTF-8');
                $linhaSemHTML[] = $valorFormatado;
            }
            fputcsv($output, $linhaSemHTML, ',', '"');
        }
        
        error_log("=== EXCEL CSV GERADO COM SUCESSO ===");
        
    } catch (Exception $e) {
        error_log("ERRO ao gerar Excel CSV: " . $e->getMessage());
        die('Erro ao gerar arquivo Excel CSV: ' . $e->getMessage());
    } finally {
        if ($output) {
            fclose($output);
        }
    }
    
    flush();
    exit;
}

/**
 * API para executar relatório e gerar saída
 * api/relatorios_executar.php
 */

// IMPORTANTE: Controle de output para exportações
ob_start();

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Inicia sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Constrói query COUNT específica para paginação
 */
function construirQueryCount($config) {
    $tipo = $config['tipo'];
    $campos = $config['campos'];
    $filtros = $config['filtros'] ?? [];
    
    // Mapear tabelas principais
    $tabelasPrincipais = [
        'associados' => 'Associados a',
        'financeiro' => 'Financeiro f',
        'militar' => 'Militar m',
        'servicos' => 'Servicos_Associado sa',
        'documentos' => 'Documentos_Associado da'
    ];
    
    $tabelaPrincipal = $tabelasPrincipais[$tipo] ?? 'Associados a';
    
    // Construir SELECT COUNT
    $where = ['1=1'];
    $params = [];
    
    // Obtém JOINs necessários (mesmo da query principal)
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
    
    // Aplica filtros (mesmo da query principal)
    $whereFiltros = aplicarFiltros($tipo, $filtros, $params);
    $where = array_merge($where, $whereFiltros);
    
    // Monta SQL COUNT
    $sql = "SELECT COUNT(DISTINCT a.id) as total\n";
    $sql .= "FROM " . $tabelaPrincipal . "\n";
    $sql .= implode("\n", $joins);
    $sql .= "\nWHERE " . implode(" AND ", $where);
    
    return [
        'sql' => $sql,
        'params' => $params
    ];
}

// Verifica autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die('Acesso negado. Usuário não autenticado.');
}

// Obtém dados do usuário
$usuarioLogado = $auth->getUser();

// Inicializa arrays se não existirem
if (!isset($_GET)) $_GET = [];
if (!isset($_POST)) $_POST = [];

// Processa dados do formulário
$tipo = $_POST['tipo'] ?? $_GET['tipo'] ?? '';
$campos = $_POST['campos'] ?? $_GET['campos'] ?? [];
$formato = $_POST['formato'] ?? $_GET['formato'] ?? 'html';
$modeloId = $_POST['modelo_id'] ?? $_GET['modelo_id'] ?? null;

// DEBUG: Log da requisição de exportação
error_log("=== REQUISIÇÃO RELATÓRIO ===");
error_log("Formato solicitado: " . $formato);
error_log("Tipo: " . $tipo);
error_log("POST dados: " . print_r($_POST, true));
error_log("GET dados: " . print_r($_GET, true));

// IMPORTANTE: Para exportações, inicia buffer de saída para controlar headers
if ($formato !== 'html') {
    ob_start();
    error_log("=== MODO EXPORTAÇÃO: " . strtoupper($formato) . " ===");
}

// Garante que campos seja um array
if (!is_array($campos)) {
    $campos = $campos ? [$campos] : [];
}

// Parâmetros de paginação (apenas para HTML)
$paginaAtual = max(1, (int)($_POST['pagina'] ?? $_GET['pagina'] ?? 1));
$registrosPorPagina = (int)($_POST['por_pagina'] ?? $_GET['por_pagina'] ?? 50);
$registrosPorPagina = min(max($registrosPorPagina, 10), 500); // Entre 10 e 500 registros

// Validações básicas
if (empty($tipo) && empty($modeloId)) {
    // Debug dos parâmetros recebidos
    error_log("=== ERRO RELATÓRIO ===");
    error_log("POST: " . print_r($_POST, true));
    error_log("GET: " . print_r($_GET, true));
    error_log("Tipo: '$tipo'");
    error_log("Modelo ID: '$modeloId'");
    
    die('Erro: Tipo de relatório ou modelo não informado. Verifique se todos os parâmetros foram enviados corretamente.');
}

if (empty($campos) && empty($modeloId)) {
    error_log("=== ERRO CAMPOS RELATÓRIO ===");
    error_log("Campos recebidos: " . print_r($campos, true));
    error_log("Tipo: '$tipo'");
    
    die('Erro: Nenhum campo selecionado para o relatório. Selecione pelo menos um campo antes de gerar o relatório.');
}

// Monta parâmetros/filtros de forma segura
$parametros = [];
$parametrosValidos = ['data_inicio', 'data_fim', 'situacao', 'corporacao', 'patente', 'tipo_associado', 'situacaoFinanceira', 'ativo', 'verificado', 'tipo_documento', 'busca'];

foreach (array_merge($_POST, $_GET) as $key => $value) {
    if (!in_array($key, ['tipo', 'campos', 'formato', 'salvar_modelo', 'nome_modelo', 'modelo_id', 'pagina', 'por_pagina'])) {
        if (!empty($value) && in_array($key, $parametrosValidos)) {
            $parametros[$key] = is_string($value) ? trim($value) : $value;
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
        
        // Debug da configuração do relatório
        error_log("=== DEBUG RELATÓRIO ===");
        error_log("Configuração: " . print_r($modeloTemp, true));
        error_log("Página atual: $paginaAtual");
        error_log("Registros por página: $registrosPorPagina");
        
        $resultado = executarRelatorioTemporario($modeloTemp, $formato, $paginaAtual, $registrosPorPagina);
    }
    
    // Processa formato de saída
    error_log("=== PROCESSANDO FORMATO: " . $formato . " ===");
    
    switch ($formato) {
        case 'excel':
            error_log("Gerando arquivo Excel (CSV Simples)...");
            // Limpa buffer antes de gerar Excel
            if (ob_get_level()) {
                ob_end_clean();
            }
            gerarExcel($resultado);
            break;
            
        case 'excel_simples':
            error_log("Gerando arquivo Excel Simples...");
            if (ob_get_level()) {
                ob_end_clean();
            }
            gerarExcelSimples($resultado);
            break;
            
        case 'excel_csv':
            error_log("Gerando arquivo Excel CSV...");
            if (ob_get_level()) {
                ob_end_clean();
            }
            gerarExcelCSV($resultado);
            break;
            
        case 'csv':
            error_log("Gerando arquivo CSV...");
            // Limpa buffer antes de gerar CSV
            if (ob_get_level()) {
                ob_end_clean();
            }
            gerarCSV($resultado);
            break;
            
        case 'pdf':
            error_log("Gerando arquivo PDF...");
            gerarPDF($resultado);
            break;
            
        default: // html
            error_log("Gerando saída HTML...");
            // Para HTML, mantém o buffer se existir
            gerarHTML($resultado);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro ao executar relatório: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Se for uma requisição de exportação que falhou, informa o erro
    if ($formato !== 'html') {
        header('Content-Type: text/plain; charset=utf-8');
        die('ERRO na exportação ' . strtoupper($formato) . ': ' . $e->getMessage());
    }
    
    die('Erro ao gerar relatório: ' . $e->getMessage());
}

// Se chegou até aqui, é um erro - deveria ter saído via exit nas funções de geração
if ($formato !== 'html') {
    error_log("ERRO: Script chegou ao final sem gerar exportação para formato: " . $formato);
    header('Content-Type: text/plain; charset=utf-8');
    die('ERRO: Falha na geração do arquivo ' . strtoupper($formato) . '. Verifique os logs do servidor.');
}

/**
 * Executa relatório temporário (sem modelo salvo)
 */
function executarRelatorioTemporario($config, $formato = 'html', $paginaAtual = 1, $registrosPorPagina = 50) {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Constrói query baseada na configuração
    $query = construirQuery($config);
    
    // Para HTML, implementa paginação
    if ($formato === 'html') {
        // Constrói uma query COUNT mais simples e segura
        $countQuery = construirQueryCount($config);
        
        $stmtCount = $db->prepare($countQuery['sql']);
        $stmtCount->execute($countQuery['params']);
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Calcula informações de paginação
        $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
        $offset = ($paginaAtual - 1) * $registrosPorPagina;
        
        // Adiciona LIMIT e OFFSET à query principal
        $queryPaginada = $query['sql'] . " LIMIT $registrosPorPagina OFFSET $offset";
        
        // Executa query paginada
        $stmt = $db->prepare($queryPaginada);
        $stmt->execute($query['params']);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'modelo' => $config,
            'dados' => $dados,
            'total' => $totalRegistros,
            'parametros' => $config['filtros'] ?? [],
            'paginacao' => [
                'pagina_atual' => $paginaAtual,
                'total_paginas' => $totalPaginas,
                'registros_por_pagina' => $registrosPorPagina,
                'total_registros' => $totalRegistros,
                'inicio' => $offset + 1,
                'fim' => min($offset + $registrosPorPagina, $totalRegistros)
            ]
        ];
    } else {
        // Para outros formatos, executa sem paginação (todos os dados)
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
    
    // Garante que sempre há pelo menos um campo de seleção
    if (empty($selectCampos)) {
        $selectCampos[] = 'a.id';
        $selectCampos[] = 'a.nome';
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
    global $usuarioLogado, $tipo, $campos, $parametros, $auth;
    
    // Garantir que as variáveis existem
    $usuarioLogado = $usuarioLogado ?? ['nome' => 'Sistema'];
    $tipo = $tipo ?? '';
    $campos = $campos ?? [];
    
    // Incluir o header component para relatórios HTML
    $headerComponent = null;
    if (file_exists('../pages/components/header.php')) {
        try {
            require_once '../pages/components/header.php';
            $headerComponent = HeaderComponent::create([
                'usuario' => $usuarioLogado,
                'isDiretor' => $auth->isDiretor(),
                'activeTab' => 'relatorios',
                'notificationCount' => 0,
                'showSearch' => true
            ]);
        } catch (Exception $e) {
            error_log("Erro ao carregar header component: " . $e->getMessage());
            $headerComponent = null;
        }
    }
    
    // Preparar parâmetros seguros para JavaScript
    $parametrosSegurosPHP = [];
    if (isset($_GET) && isset($_POST)) {
        $todoParametros = array_merge($_GET, $_POST);
        foreach ($todoParametros as $key => $value) {
            if ($value !== null && $value !== '' && $key !== '') {
                $parametrosSegurosPHP[htmlspecialchars($key, ENT_QUOTES)] = is_array($value) ? 
                    array_map(function($v) { return htmlspecialchars($v, ENT_QUOTES); }, $value) : 
                    htmlspecialchars($value, ENT_QUOTES);
            }
        }
    }
    
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
    
    <!-- CSS do Header Component (se disponível) -->
    <?php if ($headerComponent): ?>
        <?php $headerComponent->renderCSS(); ?>
    <?php endif; ?>
    
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

        /* Reset completo para eliminar espaçamentos */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box;
            height: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
            /* Header component original gerencia seu próprio posicionamento */
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            /* Header component original usa position: sticky, não precisa de margin-top */
        }

        .content-area {
            flex: 1;
            padding: 2rem;
            padding-top: 200px; /* AUMENTADO: Mais espaço para não esconder conteúdo */
            margin-left: 0;
            transition: margin-left 0.3s ease;
            /* Conteúdo flui por baixo do header, mas começa visível */
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
            z-index: 1; /* Menor que o header principal (z-index: 1000) */
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
            margin-top: 20px;
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

        /* Paginação */
        .pagination-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .pagination-info {
            color: var(--secondary-color);
            font-weight: 500;
        }

        .pagination-controls .form-select {
            width: auto;
            min-width: 80px;
        }

        .pagination .page-link {
            color: var(--primary-color);
            border: 1px solid #dee2e6;
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .pagination .page-link:hover {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .pagination .page-link i {
            font-size: 0.875rem;
        }

        /* Loading para paginação */
        .pagination-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius);
            z-index: 1000;
        }

        .table-footer-pagination {
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
        }

        .table-footer-pagination .pagination-info-footer {
            color: var(--secondary-color);
            font-weight: 500;
        }

        /* CSS adicional para ajustes do header component */
        
        /* FORÇA ABSOLUTA: Header no topo da página sem espaços */
        body > .main-wrapper > .no-print:first-child {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1000 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Remove qualquer espaçamento do container do header */
        .no-print {
            margin: 0 !important;
            padding: 0 !important;
            position: relative;
            top: 0;
        }
        
        /* Força o header a ficar exatamente no topo */
        .main-header {
            margin: 0 !important;
            padding-top: 0 !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1000;
            background: white; /* Garante fundo opaco */
        }
        
        .nav-tabs-container {
            margin: 0 !important;
            padding-top: 0 !important;
            position: sticky !important;
            top: var(--header-height, 70px) !important;
            background: white; /* Garante fundo opaco */
            z-index: 999;
        }
        
        /* Remove qualquer espaçamento extra do wrapper principal */
        .main-wrapper {
            margin: 0 !important;
            padding-top: 0 !important;
        }
        
        /* IMPORTANTE: O conteúdo flui por baixo do header */
        .content-area {
            /* Remove margin-top - conteúdo passa por baixo */
            position: relative;
            z-index: 1; /* Menor que o header */
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

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        
        <!-- Header Component (se disponível) -->
        <?php if ($headerComponent): ?>
        <div class="no-print">
            <?php 
            // CORREÇÃO 2: Usar o header component original, mas modificar as URLs internamente
            
            // Primeiro renderiza o CSS do header
            $headerComponent->renderCSS();
            
            // Agora vamos interceptar e modificar as URLs no JavaScript após o render
            $headerComponent->render();
            ?>
            
            <!-- Script para corrigir URLs do header -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('🔧 Corrigindo URLs do header...');
                
                // DEBUG: Verifica espaçamentos do header
                const header = document.querySelector('.main-header');
                const noprint = document.querySelector('.no-print');
                const mainWrapper = document.querySelector('.main-wrapper');
                const body = document.body;
                const html = document.documentElement;
                
                console.log('📏 === DEBUG POSICIONAMENTO ===');
                console.log('HTML margin/padding:', getComputedStyle(html).margin, getComputedStyle(html).padding);
                console.log('BODY margin/padding:', getComputedStyle(body).margin, getComputedStyle(body).padding);
                
                if (header) {
                    console.log('📏 Header offsetTop:', header.offsetTop);
                    console.log('📏 Header getBoundingClientRect().top:', header.getBoundingClientRect().top);
                    console.log('📏 Header style.marginTop:', getComputedStyle(header).marginTop);
                    console.log('📏 Header position:', getComputedStyle(header).position);
                }
                if (noprint) {
                    console.log('📏 No-print offsetTop:', noprint.offsetTop);
                    console.log('📏 No-print getBoundingClientRect().top:', noprint.getBoundingClientRect().top);
                    console.log('📏 No-print style.marginTop:', getComputedStyle(noprint).marginTop);
                    console.log('📏 No-print style.paddingTop:', getComputedStyle(noprint).paddingTop);
                }
                console.log('🎯 DESIGN: Conteúdo deve fluir POR BAIXO do header (sem compensação)');
                console.log('================================');
                
                // FORÇA POSICIONAMENTO FIXO NO TOPO (conteúdo passa por baixo)
                if (noprint) {
                    noprint.style.position = 'fixed';
                    noprint.style.top = '0';
                    noprint.style.left = '0';
                    noprint.style.right = '0';
                    noprint.style.zIndex = '1000';
                    noprint.style.margin = '0';
                    noprint.style.padding = '0';
                    console.log('🔧 Header forçado para position: fixed no topo (conteúdo flui por baixo)');
                }
                
                // Remove qualquer espaçamento forçadamente
                if (header) {
                    header.style.marginTop = '0';
                    header.style.paddingTop = '0';
                    header.style.backgroundColor = 'white'; // Garante fundo opaco
                }
                
                // Mapear URLs corretas
                const urlCorrections = {
                    'dashboard.php': '../pages/dashboard.php',
                    './funcionarios.php': '../pages/funcionarios.php', 
                    'funcionarios.php': '../pages/funcionarios.php',
                    'comercial.php': '../pages/comercial.php',
                    'financeiro.php': '../pages/financeiro.php',
                    'auditoria.php': '../pages/auditoria.php',
                    'presidencia.php': '../pages/presidencia.php',
                    'relatorios.php': '../pages/relatorios.php',
                    'documentos.php': '../pages/documentos.php',
                    'perfil.php': '../pages/perfil.php',
                    'configuracoes.php': '../pages/configuracoes.php',
                    'logout.php': '../pages/logout.php'
                };
                
                // Corrige todos os links do header//
                const allLinks = document.querySelectorAll('header a, nav a, .dropdown-menu-custom a');
                let corrigidos = 0;
                
                allLinks.forEach(function(link) {
                    const href = link.getAttribute('href');
                    if (href && urlCorrections[href]) {
                        const urlAntiga = href;
                        link.setAttribute('href', urlCorrections[href]);
                        console.log(`✅ Corrigido: ${urlAntiga} → ${urlCorrections[href]}`);
                        corrigidos++;
                    }
                });
                
                console.log(`🎯 Total de ${corrigidos} URLs corrigidas no header`);
                
                // Força a aba "Relatórios" como ativa
                const tabRelatorios = document.querySelector('.nav-tab-link[href*="relatorios"]');
                if (tabRelatorios) {
                    // Remove active de todas as outras abas
                    document.querySelectorAll('.nav-tab-link').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    // Adiciona active na aba relatórios
                    tabRelatorios.classList.add('active');
                    console.log('📊 Aba Relatórios marcada como ativa');
                }
            });
            </script>
        </div>
        <?php 
        // Renderiza o JavaScript do header component
        if ($headerComponent) {
            $headerComponent->renderJS();
        }
        ?>
        <?php endif; ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Header do Relatório -->
            <div class="header-report no-print fade-in-up">
                <div class="container-fluid">
                    <div class="header-content">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1><i class="fas fa-chart-line me-3 mt-10"></i><?php echo htmlspecialchars($titulo); ?></h1>
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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary btn-modern" onclick="window.print()" data-bs-toggle="tooltip" title="Imprimir relatório">
                        <i class="fas fa-print"></i>
                        <span>Imprimir</span>
                    </button>
                    <button class="btn btn-success btn-modern" onclick="exportarExcelCSV()" data-bs-toggle="tooltip" title="Exportar para Excel">
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
                <div class="stat-label">
                    <?php if (isset($resultado['paginacao'])): ?>
                        Total de Registros
                    <?php else: ?>
                        Registros Encontrados
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($resultado['paginacao'])): ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-number"><?php echo $resultado['paginacao']['registros_por_pagina']; ?></div>
                <div class="stat-label">Por Página</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-number"><?php echo $resultado['paginacao']['pagina_atual']; ?></div>
                <div class="stat-label">Página Atual</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-files-o"></i>
                </div>
                <div class="stat-number"><?php echo $resultado['paginacao']['total_paginas']; ?></div>
                <div class="stat-label">Total de Páginas</div>
            </div>
            <?php else: ?>
            
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
            <?php endif; ?>
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
        
        <!-- Paginação e Controles (se aplicável) -->
        <?php if (isset($resultado['paginacao']) && $resultado['paginacao']['total_paginas'] > 1): ?>
        <div class="pagination-container fade-in-up" style="animation-delay: 0.35s;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="pagination-info">
                    <span class="text-muted">
                        Mostrando <strong><?php echo number_format($resultado['paginacao']['inicio'], 0, ',', '.'); ?></strong> 
                        a <strong><?php echo number_format($resultado['paginacao']['fim'], 0, ',', '.'); ?></strong> 
                        de <strong><?php echo number_format($resultado['paginacao']['total_registros'], 0, ',', '.'); ?></strong> registros
                    </span>
                </div>
                
                <div class="pagination-controls">
                    <div class="d-flex align-items-center gap-3">
                        <!-- Seletor de registros por página -->
                        <div class="d-flex align-items-center">
                            <label class="text-muted me-2">Por página:</label>
                            <select class="form-select form-select-sm" id="registrosPorPagina" onchange="alterarRegistrosPorPagina(this.value)">
                                <option value="25" <?php echo $resultado['paginacao']['registros_por_pagina'] == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $resultado['paginacao']['registros_por_pagina'] == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $resultado['paginacao']['registros_por_pagina'] == 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?php echo $resultado['paginacao']['registros_por_pagina'] == 200 ? 'selected' : ''; ?>>200</option>
                                <option value="500" <?php echo $resultado['paginacao']['registros_por_pagina'] == 500 ? 'selected' : ''; ?>>500</option>
                            </select>
                        </div>
                        
                        <!-- Navegação de páginas -->
                        <nav aria-label="Navegação de páginas">
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $paginaAtual = $resultado['paginacao']['pagina_atual'] ?? 1;
                                $totalPaginas = $resultado['paginacao']['total_paginas'] ?? 1;
                                
                                // Primeira página
                                if ($paginaAtual > 1): ?>
                                <li class="page-item">
                                    <button class="page-link" onclick="navegarPagina(1)" title="Primeira página">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                </li>
                                <li class="page-item">
                                    <button class="page-link" onclick="navegarPagina(<?php echo $paginaAtual - 1; ?>)" title="Página anterior">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                </li>
                                <?php endif; ?>
                                
                                <?php
                                // Cálculo das páginas a mostrar
                                $inicio = max(1, $paginaAtual - 2);
                                $fim = min($totalPaginas, $paginaAtual + 2);
                                
                                // Reajusta se necessário
                                if ($fim - $inicio < 4 && $totalPaginas > 5) {
                                    if ($inicio == 1) {
                                        $fim = min($totalPaginas, 5);
                                    } else {
                                        $inicio = max(1, $totalPaginas - 4);
                                    }
                                }
                                
                                // Mostra reticências no início se necessário
                                if ($inicio > 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                                <li class="page-item <?php echo $i == $paginaAtual ? 'active' : ''; ?>">
                                    <button class="page-link" onclick="navegarPagina(<?php echo $i; ?>)"><?php echo $i; ?></button>
                                </li>
                                <?php endfor; ?>
                                
                                <?php 
                                // Mostra reticências no fim se necessário
                                if ($fim < $totalPaginas): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Última página -->
                                <?php if ($paginaAtual < $totalPaginas): ?>
                                <li class="page-item">
                                    <button class="page-link" onclick="navegarPagina(<?php echo $paginaAtual + 1; ?>)" title="Próxima página">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                </li>
                                <li class="page-item">
                                    <button class="page-link" onclick="navegarPagina(<?php echo $totalPaginas; ?>)" title="Última página">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
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
            
            <!-- Paginação Inferior (duplicada para facilitar navegação) -->
            <?php if (isset($resultado['paginacao']) && $resultado['paginacao']['total_paginas'] > 1): ?>
            <div class="table-footer-pagination p-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="pagination-info-footer">
                        <small class="text-muted">
                            Página <strong><?php echo $resultado['paginacao']['pagina_atual']; ?></strong> 
                            de <strong><?php echo $resultado['paginacao']['total_paginas']; ?></strong>
                            (<?php echo number_format($resultado['paginacao']['total_registros'], 0, ',', '.'); ?> total)
                        </small>
                    </div>
                    
                    <nav aria-label="Navegação de páginas inferior">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($paginaAtual > 1): ?>
                            <li class="page-item">
                                <button class="page-link" onclick="navegarPagina(1)" title="Primeira página">
                                    <i class="fas fa-angle-double-left"></i>
                                </button>
                            </li>
                            <li class="page-item">
                                <button class="page-link" onclick="navegarPagina(<?php echo $paginaAtual - 1; ?>)" title="Página anterior">
                                    <i class="fas fa-angle-left"></i>
                                </button>
                            </li>
                            <?php endif; ?>
                            
                            <li class="page-item active">
                                <span class="page-link"><?php echo $paginaAtual; ?></span>
                            </li>
                            
                            <?php if ($paginaAtual < $totalPaginas): ?>
                            <li class="page-item">
                                <button class="page-link" onclick="navegarPagina(<?php echo $paginaAtual + 1; ?>)" title="Próxima página">
                                    <i class="fas fa-angle-right"></i>
                                </button>
                            </li>
                            <li class="page-item">
                                <button class="page-link" onclick="navegarPagina(<?php echo $totalPaginas; ?>)" title="Última página">
                                    <i class="fas fa-angle-double-right"></i>
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="empty-state fade-in-up" style="animation-delay: 0.4s;">
            <div class="empty-state-icon">
                <i class="fas fa-search"></i>
            </div>
            <h3>Nenhum registro encontrado</h3>
            <p>Não foram encontrados registros com os filtros aplicados. Tente ajustar os parâmetros de busca.</p>
            
            <?php if (isset($resultado['paginacao']) && $resultado['paginacao']['pagina_atual'] > 1): ?>
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                Você está na página <?php echo $resultado['paginacao']['pagina_atual']; ?>. 
                <button class="btn btn-link p-0" onclick="navegarPagina(1)">
                    Voltar para a primeira página
                </button>
            </div>
            <?php endif; ?>
            
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
        
        </div> <!-- Fim da content-area -->
    </div> <!-- Fim da main-wrapper -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Verifica se há erros de sintaxe antes de executar
        try {
            // Loading overlay
            window.addEventListener('load', function() {
                setTimeout(function() {
                    const loadingElement = document.getElementById('loadingOverlay');
                    if (loadingElement) {
                        loadingElement.style.display = 'none';
                    }
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
            console.log('🟢 Iniciando exportação Excel...');
            showExportLoading('Excel');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            
            // Debug dos parâmetros que serão enviados
            console.log('📋 Parâmetros originais para Excel:', <?php echo json_encode($_POST, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
            
            // Copia TODOS os parâmetros POST originais
            <?php foreach ($_POST as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $index => $item): ?>
                    const input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?> = document.createElement('input');
                    input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.type = 'hidden';
                    input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.name = '<?php echo htmlspecialchars($key, ENT_QUOTES); ?>[]';
                    input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.value = '<?php echo htmlspecialchars($item, ENT_QUOTES); ?>';
                    form.appendChild(input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>);
                    console.log('📝 Array param:', '<?php echo $key; ?>[]', '=', '<?php echo htmlspecialchars($item, ENT_QUOTES); ?>');
                    <?php endforeach; ?>
                <?php else: ?>
                const input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?> = document.createElement('input');
                input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.type = 'hidden';
                input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.name = '<?php echo htmlspecialchars($key, ENT_QUOTES); ?>';
                input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.value = '<?php echo htmlspecialchars($value, ENT_QUOTES); ?>';
                form.appendChild(input_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>);
                console.log('📝 Simple param:', '<?php echo $key; ?>', '=', '<?php echo htmlspecialchars($value, ENT_QUOTES); ?>');
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Força formato Excel
            const inputFormato = document.createElement('input');
            inputFormato.type = 'hidden';
            inputFormato.name = 'formato';
            inputFormato.value = 'excel';
            form.appendChild(inputFormato);
            console.log('📝 Formato definido para: excel');
            
            console.log('📤 Enviando formulário para Excel...');
            document.body.appendChild(form);
            form.submit();
            
            // Remove loading após um tempo
            setTimeout(hideExportLoading, 3000);
        }

        function exportarExcelCSV() {
            console.log('🟢 Iniciando exportação Excel CSV...');
            showExportLoading('Excel');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            
            // Copia TODOS os parâmetros POST originais
            <?php foreach ($_POST as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $index => $item): ?>
                    const input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?> = document.createElement('input');
                    input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.type = 'hidden';
                    input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.name = '<?php echo htmlspecialchars($key, ENT_QUOTES); ?>[]';
                    input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.value = '<?php echo htmlspecialchars($item, ENT_QUOTES); ?>';
                    form.appendChild(input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>);
                    <?php endforeach; ?>
                <?php else: ?>
                const input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?> = document.createElement('input');
                input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.type = 'hidden';
                input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.name = '<?php echo htmlspecialchars($key, ENT_QUOTES); ?>';
                input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.value = '<?php echo htmlspecialchars($value, ENT_QUOTES); ?>';
                form.appendChild(input_excel_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>);
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Força formato excel_csv
            const inputFormato = document.createElement('input');
            inputFormato.type = 'hidden';
            inputFormato.name = 'formato';
            inputFormato.value = 'excel_csv';
            form.appendChild(inputFormato);
            
            document.body.appendChild(form);
            form.submit();
            
            setTimeout(hideExportLoading, 3000);
        }

        function exportarCSV() {
            console.log('🟢 Iniciando exportação CSV...');
            showExportLoading('CSV');
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            
            // Debug dos parâmetros que serão enviados
            console.log('📋 Parâmetros originais para CSV:', <?php echo json_encode($_POST, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
            
            // Copia TODOS os parâmetros POST originais
            <?php foreach ($_POST as $key => $value): ?>
                <?php if (is_array($value)): ?>
                    <?php foreach ($value as $index => $item): ?>
                    const input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?> = document.createElement('input');
                    input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.type = 'hidden';
                    input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.name = '<?php echo htmlspecialchars($key, ENT_QUOTES); ?>[]';
                    input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>.value = '<?php echo htmlspecialchars($item, ENT_QUOTES); ?>';
                    form.appendChild(input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key . '_' . $index); ?>);
                    console.log('📝 Array param CSV:', '<?php echo $key; ?>[]', '=', '<?php echo htmlspecialchars($item, ENT_QUOTES); ?>');
                    <?php endforeach; ?>
                <?php else: ?>
                const input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?> = document.createElement('input');
                input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.type = 'hidden';
                input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.name = '<?php echo htmlspecialchars($key, ENT_QUOTES); ?>';
                input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>.value = '<?php echo htmlspecialchars($value, ENT_QUOTES); ?>';
                form.appendChild(input_csv_<?php echo preg_replace('/[^a-zA-Z0-9_]/', '_', $key); ?>);
                console.log('📝 Simple param CSV:', '<?php echo $key; ?>', '=', '<?php echo htmlspecialchars($value, ENT_QUOTES); ?>');
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Força formato CSV
            const inputFormato = document.createElement('input');
            inputFormato.type = 'hidden';
            inputFormato.name = 'formato';
            inputFormato.value = 'csv';
            form.appendChild(inputFormato);
            console.log('📝 Formato definido para: csv');
            
            console.log('📤 Enviando formulário para CSV...');
            document.body.appendChild(form);
            form.submit();
            
            // Remove loading após um tempo
            setTimeout(hideExportLoading, 3000);
        }

        function showExportLoading(format) {
            console.log(`🔄 Iniciando loading para ${format}...`);
            const exportButtons = document.querySelectorAll('.btn-success, .btn-info');
            exportButtons.forEach(btn => {
                btn.disabled = true;
                const originalText = btn.innerHTML;
                btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Exportando ${format}...`;
                btn.setAttribute('data-original', originalText);
                btn.style.opacity = '0.7';
            });
            
            // Mostra uma mensagem de status
            console.log(`📊 Exportação ${format} em andamento...`);
        }

        function hideExportLoading() {
            console.log('✅ Finalizando loading...');
            const exportButtons = document.querySelectorAll('.btn-success, .btn-info');
            exportButtons.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
                const originalText = btn.getAttribute('data-original');
                if (originalText) {
                    btn.innerHTML = originalText;
                    btn.removeAttribute('data-original');
                }
            });
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

        // Funções de Paginação
        function navegarPagina(pagina) {
            mostrarLoadingPaginacao();
            
            // Constrói URL preservando TODOS os parâmetros originais
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = window.location.pathname;
            
            // Parâmetros originais (já definidos no início da função gerarHTML)
            const parametrosOriginais = <?php echo json_encode($parametrosSegurosPHP, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?> || {};
            
            Object.keys(parametrosOriginais).forEach(key => {
                const value = parametrosOriginais[key];
                
                if (Array.isArray(value)) {
                    // Para arrays (como campos[])
                    value.forEach(item => {
                        if (item !== null && item !== '') {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key + '[]';
                            input.value = item;
                            form.appendChild(input);
                        }
                    });
                } else if (value !== null && value !== '') {
                    // Para valores simples
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
            });
            
            // Atualiza página
            const inputPagina = document.createElement('input');
            inputPagina.type = 'hidden';
            inputPagina.name = 'pagina';
            inputPagina.value = pagina;
            form.appendChild(inputPagina);
            
            document.body.appendChild(form);
            form.submit();
        }

        function alterarRegistrosPorPagina(registrosPorPagina) {
            mostrarLoadingPaginacao();
            
            // Constrói URL preservando TODOS os parâmetros originais
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = window.location.pathname;
            
            // Usa os mesmos parâmetros seguros definidos anteriormente
            const parametrosOriginais = <?php echo json_encode($parametrosSegurosPHP ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?> || {};
            
            Object.keys(parametrosOriginais).forEach(key => {
                // Pula os parâmetros de paginação que serão sobrescritos
                if (key === 'pagina' || key === 'por_pagina') return;
                
                const value = parametrosOriginais[key];
                
                if (Array.isArray(value)) {
                    // Para arrays (como campos[])
                    value.forEach(item => {
                        if (item !== null && item !== '') {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key + '[]';
                            input.value = item;
                            form.appendChild(input);
                        }
                    });
                } else if (value !== null && value !== '') {
                    // Para valores simples
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
            });
            
            // Adiciona novos parâmetros de paginação
            const inputPorPagina = document.createElement('input');
            inputPorPagina.type = 'hidden';
            inputPorPagina.name = 'por_pagina';
            inputPorPagina.value = registrosPorPagina;
            form.appendChild(inputPorPagina);
            
            const inputPagina = document.createElement('input');
            inputPagina.type = 'hidden';
            inputPagina.name = 'pagina';
            inputPagina.value = 1; // Volta para primeira página
            form.appendChild(inputPagina);
            
            document.body.appendChild(form);
            form.submit();
        }

        function mostrarLoadingPaginacao() {
            // Cria overlay de loading sobre a tabela
            const container = document.querySelector('.table-container, .pagination-container');
            if (container && !container.querySelector('.pagination-loading')) {
                const loading = document.createElement('div');
                loading.className = 'pagination-loading';
                loading.innerHTML = `
                    <div class="text-center">
                        <div class="loading-spinner mb-2"></div>
                        <small class="text-muted">Carregando página...</small>
                    </div>
                `;
                container.style.position = 'relative';
                container.appendChild(loading);
            }
        }

        // Debug dos parâmetros (apenas em desenvolvimento)
        try {
            console.log('=== DEBUG PAGINAÇÃO ===');
            
            <?php if (isset($parametrosSegurosPHP) && !empty($parametrosSegurosPHP)): ?>
            console.log('Parâmetros originais:', <?php echo json_encode($parametrosSegurosPHP, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>);
            <?php else: ?>
            console.log('Nenhum parâmetro original encontrado');
            <?php endif; ?>
            
            <?php if (isset($resultado['paginacao'])): ?>
            console.log('Paginação atual:', {
                pagina: <?php echo (int)$resultado['paginacao']['pagina_atual']; ?>,
                total_paginas: <?php echo (int)$resultado['paginacao']['total_paginas']; ?>,
                registros_por_pagina: <?php echo (int)$resultado['paginacao']['registros_por_pagina']; ?>,
                total_registros: <?php echo (int)$resultado['paginacao']['total_registros']; ?>
            });
            <?php else: ?>
            console.log('Sem paginação ativa');
            <?php endif; ?>
            
            <?php if (!empty($tipo)): ?>
            console.log('Tipo de relatório:', <?php echo json_encode($tipo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
            <?php endif; ?>
            
            <?php if (!empty($campos) && is_array($campos)): ?>
            console.log('Campos selecionados:', <?php echo json_encode($campos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
            <?php endif; ?>
            
        } catch (debugError) {
            console.log('Erro no debug:', debugError.message);
        }

        // Atalhos de teclado para navegação
        document.addEventListener('keydown', function(e) {
            // Só funciona se não estiver em um input
            if (e.target.tagName.toLowerCase() === 'input' || 
                e.target.tagName.toLowerCase() === 'textarea' ||
                e.target.tagName.toLowerCase() === 'select') {
                return;
            }

            <?php if (isset($resultado['paginacao'])): ?>
            const paginaAtual = <?php echo $resultado['paginacao']['pagina_atual']; ?>;
            const totalPaginas = <?php echo $resultado['paginacao']['total_paginas']; ?>;
            
            // Seta esquerda ou 'A' - página anterior
            if ((e.key === 'ArrowLeft' || e.key.toLowerCase() === 'a') && paginaAtual > 1) {
                e.preventDefault();
                navegarPagina(paginaAtual - 1);
            }
            
            // Seta direita ou 'D' - próxima página  
            if ((e.key === 'ArrowRight' || e.key.toLowerCase() === 'd') && paginaAtual < totalPaginas) {
                e.preventDefault();
                navegarPagina(paginaAtual + 1);
            }
            
            // Home - primeira página
            if (e.key === 'Home' && paginaAtual > 1) {
                e.preventDefault();
                navegarPagina(1);
            }
            
            // End - última página
            if (e.key === 'End' && paginaAtual < totalPaginas) {
                e.preventDefault();
                navegarPagina(totalPaginas);
            }
            <?php endif; ?>
        });

        // Tooltip para atalhos de navegação
        <?php if (isset($resultado['paginacao']) && $resultado['paginacao']['total_paginas'] > 1): ?>
        // Adiciona tooltip com informações de atalhos
        const paginationContainer = document.querySelector('.pagination-container');
        if (paginationContainer) {
            const helpText = document.createElement('small');
            helpText.className = 'text-muted d-block mt-2';
            helpText.innerHTML = '<i class="fas fa-keyboard me-1"></i> <strong>Atalhos:</strong> ← → ou A/D para navegar | Home/End para primeira/última página';
            paginationContainer.appendChild(helpText);
        }
        <?php endif; ?>
        } catch (error) {
            console.error('Erro no JavaScript da paginação:', error);
            // Fallback simples se houver erro
            window.navegarPagina = function(pagina) {
                window.location.href = window.location.pathname + '?pagina=' + pagina;
            };
            window.alterarRegistrosPorPagina = function(registros) {
                window.location.href = window.location.pathname + '?por_pagina=' + registros + '&pagina=1';
            };
        }
    </script>
</body>
</html>
    <?php
}

/**
 * Gera saída CSV
 */
function gerarCSV($resultado) {
    error_log("=== INICIANDO GERAÇÃO CSV ===");
    
    // Define modo de exportação para formatação limpa
    define('EXPORT_MODE', true);
    
    $dados = $resultado['dados'] ?? [];
    $total = count($dados);
    error_log("Total de dados para CSV: " . $total);
    
    if (empty($dados)) {
        error_log("ERRO: Nenhum dado para exportar em CSV");
        die('Erro: Nenhum dado disponível para exportação CSV.');
    }
    
    $filename = 'relatorio_' . date('Y-m-d_H-i-s') . '.csv';
    error_log("Nome do arquivo CSV: " . $filename);
    
    // IMPORTANTE: Limpa qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verifica se headers já foram enviados
    if (headers_sent($file, $line)) {
        error_log("ERRO: Headers já foram enviados em $file:$line");
        die('Erro interno: Headers já enviados. Não é possível gerar CSV.');
    }
    
    // Headers para download do CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // BOM para UTF-8 (para Excel reconhecer acentos)
    echo "\xEF\xBB\xBF";
    
    // Abre output
    $output = fopen('php://output', 'w');
    
    if (!$output) {
        error_log("ERRO: Não foi possível abrir php://output para CSV");
        die('Erro interno ao gerar CSV.');
    }
    
    try {
        // Cabeçalhos das colunas
        $headers = array_map('formatarNomeColuna', array_keys($dados[0]));
        error_log("Headers CSV: " . implode(', ', $headers));
        
        // Escreve headers
        fputcsv($output, $headers, ';', '"');
        
        // Escreve dados
        $linhasEscritas = 0;
        foreach ($dados as $linha) {
            // Usa formatação limpa para CSV (sem HTML)
            $linhaSemHTML = [];
            foreach ($linha as $key => $valor) {
                $valorFormatado = formatarValor($valor, $key);
                // Remove qualquer HTML que possa ter restado
                $valorFormatado = is_string($valorFormatado) ? strip_tags($valorFormatado) : $valorFormatado;
                $valorFormatado = html_entity_decode($valorFormatado, ENT_QUOTES, 'UTF-8');
                $linhaSemHTML[] = $valorFormatado;
            }
            
            fputcsv($output, $linhaSemHTML, ';', '"');
            $linhasEscritas++;
            
            // Força flush a cada 100 linhas para evitar timeout
            if ($linhasEscritas % 100 == 0) {
                flush();
            }
        }
        
        error_log("Linhas escritas no CSV: " . $linhasEscritas);
        error_log("=== CSV GERADO COM SUCESSO ===");
        
    } catch (Exception $e) {
        error_log("ERRO ao gerar CSV: " . $e->getMessage());
        die('Erro ao gerar arquivo CSV: ' . $e->getMessage());
    } finally {
        if ($output) {
            fclose($output);
        }
    }
    
    // Força flush final e termina
    flush();
    exit;
}

/**
 * Gera saída Excel (CSV simples com extensão .xlsx)
 */
function gerarExcel($resultado) {
    error_log("=== INICIANDO GERAÇÃO EXCEL (CSV SIMPLES) ===");
    
    // Define modo de exportação para formatação limpa
    if (!defined('EXPORT_MODE')) {
        define('EXPORT_MODE', true);
    }
    
    $dados = $resultado['dados'] ?? [];
    $total = count($dados);
    error_log("Total de dados para Excel: " . $total);
    
    if (empty($dados)) {
        error_log("ERRO: Nenhum dado para exportar em Excel");
        die('Erro: Nenhum dado disponível para exportação Excel.');
    }
    
    $filename = 'relatorio_' . date('Y-m-d_H-i-s') . '.xlsx';
    error_log("Nome do arquivo Excel: " . $filename);
    
    // IMPORTANTE: Limpa qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Verifica se headers já foram enviados
    if (headers_sent($file, $line)) {
        error_log("ERRO: Headers já foram enviados em $file:$line");
        die('Erro interno: Headers já enviados. Não é possível gerar Excel.');
    }
    
    // Headers para Excel (CSV com extensão xlsx)
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Pragma: public');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    // Abre output
    $output = fopen('php://output', 'w');
    
    if (!$output) {
        error_log("ERRO: Não foi possível abrir php://output para Excel");
        die('Erro interno ao gerar Excel.');
    }
    
    try {
        // Modelo e informações do cabeçalho
        $modelo = $resultado['modelo'] ?? [];
        $titulo = 'Relatório de ' . ucfirst($modelo['tipo'] ?? 'Dados');
        
        // Linhas de cabeçalho informativo
        fputcsv($output, [$titulo], ',', '"');
        fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i:s')], ',', '"');
        fputcsv($output, ['Total de registros: ' . number_format($total, 0, ',', '.')], ',', '"');
        fputcsv($output, [''], ',', '"'); // Linha vazia
        
        // Cabeçalhos das colunas
        $headers = array_map('formatarNomeColuna', array_keys($dados[0]));
        fputcsv($output, $headers, ',', '"');
        
        // Escreve dados
        $linhasEscritas = 0;
        foreach ($dados as $linha) {
            $linhaSemHTML = [];
            foreach ($linha as $key => $valor) {
                $valorFormatado = formatarValor($valor, $key);
                // Remove qualquer HTML que possa ter restado
                $valorFormatado = is_string($valorFormatado) ? strip_tags($valorFormatado) : $valorFormatado;
                $valorFormatado = html_entity_decode($valorFormatado, ENT_QUOTES, 'UTF-8');
                
                // Para CPF, telefone, CEP, força como texto
                if (in_array($key, ['cpf', 'telefone', 'cep'])) {
                    $valorFormatado = "'" . $valorFormatado; // Força como texto no Excel
                }
                
                $linhaSemHTML[] = $valorFormatado;
            }
            
            fputcsv($output, $linhaSemHTML, ',', '"');
            $linhasEscritas++;
            
            // Força flush a cada 100 linhas para evitar timeout
            if ($linhasEscritas % 100 == 0) {
                flush();
            }
        }
        
        error_log("Linhas escritas no Excel: " . $linhasEscritas);
        error_log("=== EXCEL GERADO COM SUCESSO ===");
        
    } catch (Exception $e) {
        error_log("ERRO ao gerar Excel: " . $e->getMessage());
        die('Erro ao gerar arquivo Excel: ' . $e->getMessage());
    } finally {
        if ($output) {
            fclose($output);
        }
    }
    
    // Força flush final e termina
    flush();
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
        return defined('EXPORT_MODE') && EXPORT_MODE === true ? '-' : '<span class="text-muted">-</span>';
    }
    
    // Para exportação (CSV/Excel), retorna valor limpo sem HTML
    if (defined('EXPORT_MODE') && EXPORT_MODE === true) {
        if (strpos($campo, 'data') !== false || strpos($campo, 'Data') !== false) {
            if ($valor !== '0000-00-00' && $valor !== '0000-00-00 00:00:00') {
                try {
                    $data = new DateTime($valor);
                    return $data->format('d/m/Y');
                } catch (Exception $e) {
                    return $valor;
                }
            }
            return '-';
        }
        
        if ($campo === 'cpf') {
            return formatarCPF($valor);
        }
        
        if ($campo === 'telefone') {
            return formatarTelefone($valor);
        }
        
        if ($campo === 'cep') {
            return formatarCEP($valor);
        }
        
        if (strpos($campo, 'valor') !== false || strpos($campo, 'Valor') !== false) {
            return 'R$ ' . number_format($valor, 2, ',', '.');
        }
        
        if (strpos($campo, 'percentual') !== false) {
            return number_format($valor, 2, ',', '.') . '%';
        }
        
        if ($campo === 'ativo' || $campo === 'verificado') {
            return ($valor == 1) ? 'Sim' : 'Não';
        }
        
        if ($campo === 'sexo') {
            if ($valor === 'M') return 'Masculino';
            if ($valor === 'F') return 'Feminino';
            return $valor;
        }
        
        return $valor;
    }
    
    // Formatação original para HTML (com tags)
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