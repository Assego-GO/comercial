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
require_once '../classes/Relatorios.php';

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

try {
    // Inicializa classe de relatórios
    $relatorios = new Relatorios();
    
    // Se há um modelo_id, carrega e executa o modelo
    if ($modeloId) {
        $resultado = $relatorios->executarRelatorio($modeloId, $parametros);
    } else {
        // Executa relatório sem modelo (temporário)
        $modeloTemp = [
            'tipo' => $tipo,
            'campos' => $campos,
            'filtros' => $parametros,
            'ordenacao' => $_POST['ordenacao'] ?? null
        ];
        
        $resultado = executarRelatorioTemporario($modeloTemp, $relatorios);
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
function executarRelatorioTemporario($config, $relatorios) {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Constrói query baseada na configuração
    $query = construirQuery($config);
    
    // Executa query
    $stmt = $db->prepare($query['sql']);
    $stmt->execute($query['params']);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Registra execução no histórico (opcional para relatórios temporários)
    // $relatorios->registrarHistoricoTemporario($config, count($dados));
    
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
    
    // JOINs condicionais baseados nos campos - mas só adiciona se ainda não foi adicionado
    
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12pt; }
            table { font-size: 10pt; }
        }
        
        .header-report {
            background: #0056D2;
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .stats-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .table-report {
            font-size: 0.875rem;
        }
        
        .table-report th {
            background: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .footer-report {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header-report no-print">
            <h1><?php echo htmlspecialchars($titulo); ?></h1>
            <p class="mb-0">Gerado em <?php echo date('d/m/Y \à\s H:i'); ?></p>
        </div>
        
        <!-- Ações -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <div>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button class="btn btn-success" onclick="exportarExcel()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                <button class="btn btn-secondary" onclick="window.close()">
                    <i class="fas fa-times"></i> Fechar
                </button>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="stats-box">
            <div class="row">
                <div class="col-md-3">
                    <h5 class="text-muted mb-1">Total de Registros</h5>
                    <h2 class="mb-0"><?php echo number_format($total, 0, ',', '.'); ?></h2>
                </div>
                <?php if (!empty($resultado['parametros'])): ?>
                <div class="col-md-9">
                    <h5 class="text-muted mb-2">Filtros Aplicados</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($resultado['parametros'] as $key => $value): ?>
                            <?php if (!empty($value)): ?>
                            <span class="badge bg-primary">
                                <?php echo ucfirst(str_replace('_', ' ', $key)); ?>: 
                                <?php echo htmlspecialchars($value); ?>
                            </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabela de Dados -->
        <?php if ($total > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-report">
                <thead>
                    <tr>
                        <?php if (!empty($dados[0])): ?>
                            <?php foreach (array_keys($dados[0]) as $coluna): ?>
                            <th><?php echo formatarNomeColuna($coluna); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $linha): ?>
                    <tr>
                        <?php foreach ($linha as $key => $valor): ?>
                        <td><?php echo formatarValor($valor, $key); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Nenhum registro encontrado com os filtros aplicados.
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer-report">
            <p class="mb-1">ASSEGO - Associação dos Servidores do Estado de Goiás</p>
            <p class="text-muted small">
                Relatório gerado por <?php echo htmlspecialchars($GLOBALS['usuarioLogado']['nome']); ?> 
                em <?php echo date('d/m/Y \à\s H:i:s'); ?>
            </p>
        </div>
    </div>
    
    <script>
        function exportarExcel() {
            // Resubmete o formulário com formato excel
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo $_SERVER['PHP_SELF']; ?>';
            
            // Copia todos os parâmetros
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
            
            // Muda formato para Excel
            const inputFormato = document.createElement('input');
            inputFormato.type = 'hidden';
            inputFormato.name = 'formato';
            inputFormato.value = 'excel';
            form.appendChild(inputFormato);
            
            document.body.appendChild(form);
            form.submit();
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
            echo '<th style="background-color: #f0f0f0; font-weight: bold;">';
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
        return '-';
    }
    
    // Formatação por tipo de campo
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
        return $valor == 1 ? 'Sim' : 'Não';
    }
    
    if ($campo === 'sexo') {
        return $valor === 'M' ? 'Masculino' : ($valor === 'F' ? 'Feminino' : $valor);
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