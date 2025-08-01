<?php
/**
 * API específica para edições de associados e funcionários
 * /api/auditoria/edicoes.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Configurar tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }

    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    
    // Verificar autenticação (opcional, mas recomendado)
    session_start();
    if (!isset($_SESSION['funcionario_id'])) {
        error_log("API de edições acessada sem sessão ativa");
    }
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    // Preparar filtros específicos para edições
    $whereConditions = ["a.acao = 'UPDATE'"]; // Apenas edições
    $params = [];
    
    // Filtrar apenas por associados e funcionários por padrão
    $tabelasPermitidas = ['Associados', 'Funcionarios'];
    
    // Filtro por tabela específica
    if (!empty($_GET['tabela'])) {
        $tabelaSolicitada = $_GET['tabela'];
        if (in_array($tabelaSolicitada, $tabelasPermitidas)) {
            $whereConditions[] = "a.tabela = :tabela";
            $params[':tabela'] = $tabelaSolicitada;
        } else {
            throw new Exception('Tabela não permitida para edições');
        }
    } else {
        // Se não especificou tabela, filtrar pelas permitidas
        $whereConditions[] = "a.tabela IN ('" . implode("','", $tabelasPermitidas) . "')";
    }
    
    // Filtro por funcionário
    if (!empty($_GET['funcionario_id'])) {
        $whereConditions[] = "a.funcionario_id = :funcionario_id";
        $params[':funcionario_id'] = (int)$_GET['funcionario_id'];
    }
    
    // Filtro por associado
    if (!empty($_GET['associado_id'])) {
        $whereConditions[] = "a.associado_id = :associado_id";
        $params[':associado_id'] = (int)$_GET['associado_id'];
    }
    
    // Filtro por data início
    if (!empty($_GET['data_inicio'])) {
        $whereConditions[] = "DATE(a.data_hora) >= :data_inicio";
        $params[':data_inicio'] = $_GET['data_inicio'];
    }
    
    // Filtro por data fim
    if (!empty($_GET['data_fim'])) {
        $whereConditions[] = "DATE(a.data_hora) <= :data_fim";
        $params[':data_fim'] = $_GET['data_fim'];
    }
    
    // Filtro por busca (funcionário, associado ou tabela)
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(f.nome LIKE :search OR ass.nome LIKE :search2 OR a.tabela LIKE :search3)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
        $params[':search3'] = $search;
    }
    
    // Filtros departamentais (se aplicável)
    if (!empty($_GET['departamento_usuario'])) {
        $deptUsuario = (int)$_GET['departamento_usuario'];
        $whereConditions[] = "(
            f.departamento_id = :departamento_usuario 
            OR a.funcionario_id IN (
                SELECT id FROM Funcionarios WHERE departamento_id = :departamento_usuario2
            )
        )";
        $params[':departamento_usuario'] = $deptUsuario;
        $params[':departamento_usuario2'] = $deptUsuario;
    }
    
    // Construir cláusula WHERE
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // === BUSCAR EDIÇÕES ===
    
    $sql = "
        SELECT 
            a.id,
            a.tabela,
            a.acao,
            a.registro_id,
            a.data_hora,
            a.ip_origem,
            a.browser_info,
            a.sessao_id,
            a.alteracoes,
            a.associado_id,
            f.nome as funcionario_nome,
            f.email as funcionario_email,
            f.cargo as funcionario_cargo,
            f.departamento_id as funcionario_departamento_id,
            d.nome as funcionario_departamento,
            ass.nome as associado_nome,
            ass.cpf as associado_cpf,
            ass.email as associado_email
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id
        $whereClause
        ORDER BY a.data_hora DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    
    // Bind dos parâmetros
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $edicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // === CONTAR TOTAL DE EDIÇÕES ===
    
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id
        $whereClause
    ";
    
    $stmtCount = $db->prepare($sqlCount);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $stmtCount->bindValue($key, $value);
        }
    }
    $stmtCount->execute();
    $totalEdicoes = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPaginas = ceil($totalEdicoes / $limit);
    
    // === PROCESSAR EDIÇÕES ===
    
    $edicoesProcessadas = [];
    foreach ($edicoes as $edicao) {
        $processada = [
            'id' => (int)$edicao['id'],
            'tabela' => $edicao['tabela'],
            'acao' => $edicao['acao'],
            'registro_id' => $edicao['registro_id'],
            'data_hora' => $edicao['data_hora'],
            'ip_origem' => $edicao['ip_origem'],
            'browser_info' => $edicao['browser_info'],
            'sessao_id' => $edicao['sessao_id'],
            'alteracoes' => $edicao['alteracoes'],
            'funcionario_nome' => $edicao['funcionario_nome'] ?: 'Sistema',
            'funcionario_email' => $edicao['funcionario_email'],
            'funcionario_cargo' => $edicao['funcionario_cargo'],
            'funcionario_departamento_id' => $edicao['funcionario_departamento_id'],
            'funcionario_departamento' => $edicao['funcionario_departamento'],
            'associado_id' => $edicao['associado_id'],
            'associado_nome' => $edicao['associado_nome'],
            'associado_cpf' => $edicao['associado_cpf'],
            'associado_email' => $edicao['associado_email']
        ];
        
        // Formatar data
        if ($processada['data_hora']) {
            $processada['data_hora_formatada'] = date('d/m/Y H:i:s', strtotime($processada['data_hora']));
            $processada['data_formatada'] = date('d/m/Y', strtotime($processada['data_hora']));
            $processada['hora_formatada'] = date('H:i', strtotime($processada['data_hora']));
        }
        
        // Decodificar alterações se existir
        $processada['alteracoes_decoded'] = null;
        $processada['campos_alterados'] = 0;
        $processada['resumo_edicao'] = 'Dados alterados';
        
        if (!empty($processada['alteracoes'])) {
            try {
                $alteracoesDecoded = json_decode($processada['alteracoes'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($alteracoesDecoded)) {
                    $processada['alteracoes_decoded'] = $alteracoesDecoded;
                    $processada['campos_alterados'] = count($alteracoesDecoded);
                    
                    // Gerar resumo mais detalhado
                    if ($processada['campos_alterados'] === 1) {
                        $processada['resumo_edicao'] = '1 campo alterado';
                    } elseif ($processada['campos_alterados'] <= 3) {
                        $processada['resumo_edicao'] = $processada['campos_alterados'] . ' campos alterados';
                    } else {
                        $processada['resumo_edicao'] = $processada['campos_alterados'] . ' campos alterados (edição extensa)';
                    }
                    
                    // Se há poucos campos, listar os nomes
                    if ($processada['campos_alterados'] <= 2 && isset($alteracoesDecoded[0]['campo'])) {
                        $nomesCampos = array_column($alteracoesDecoded, 'campo');
                        $processada['resumo_edicao'] = 'Alterado: ' . implode(', ', $nomesCampos);
                    }
                }
            } catch (Exception $e) {
                error_log("Erro ao decodificar alterações da edição {$processada['id']}: " . $e->getMessage());
            }
        }
        
        // Informações específicas baseadas na tabela
        if ($processada['tabela'] === 'Associados') {
            $processada['tipo_registro'] = 'Associado';
            $processada['nome_registro'] = $processada['associado_nome'] ?: 'Associado ID ' . $processada['registro_id'];
            $processada['identificador_registro'] = $processada['associado_cpf'] ?: $processada['registro_id'];
        } elseif ($processada['tabela'] === 'Funcionarios') {
            $processada['tipo_registro'] = 'Funcionário';
            // Para funcionários, precisaríamos buscar o nome, mas por performance vamos usar o ID
            $processada['nome_registro'] = 'Funcionário ID ' . $processada['registro_id'];
            $processada['identificador_registro'] = $processada['registro_id'];
        } else {
            $processada['tipo_registro'] = $processada['tabela'];
            $processada['nome_registro'] = 'Registro ID ' . $processada['registro_id'];
            $processada['identificador_registro'] = $processada['registro_id'];
        }
        
        // Calcular tempo desde a edição
        $tempoEdicao = time() - strtotime($processada['data_hora']);
        if ($tempoEdicao < 3600) { // Menos de 1 hora
            $processada['tempo_relativo'] = 'Há ' . round($tempoEdicao / 60) . ' minutos';
        } elseif ($tempoEdicao < 86400) { // Menos de 1 dia
            $processada['tempo_relativo'] = 'Há ' . round($tempoEdicao / 3600) . ' horas';
        } else {
            $processada['tempo_relativo'] = 'Há ' . round($tempoEdicao / 86400) . ' dias';
        }
        
        $edicoesProcessadas[] = $processada;
    }
    
    // === ESTATÍSTICAS DAS EDIÇÕES ===
    
    // Edições por tabela
    $sqlStats = "
        SELECT 
            a.tabela,
            COUNT(*) as total_edicoes,
            COUNT(DISTINCT a.funcionario_id) as editores_unicos,
            MAX(a.data_hora) as ultima_edicao
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        $whereClause
        GROUP BY a.tabela
        ORDER BY total_edicoes DESC
    ";
    
    $stmtStats = $db->prepare($sqlStats);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $stmtStats->bindValue($key, $value);
        }
    }
    $stmtStats->execute();
    $estatisticasPorTabela = $stmtStats->fetchAll(PDO::FETCH_ASSOC);
    
    // Edições por período (últimos 7 dias)
    $sqlPeriodo = "
        SELECT 
            DATE(a.data_hora) as data,
            COUNT(*) as total_edicoes
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        $whereClause
        AND a.data_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(a.data_hora)
        ORDER BY data DESC
    ";
    
    $stmtPeriodo = $db->prepare($sqlPeriodo);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $stmtPeriodo->bindValue($key, $value);
        }
    }
    $stmtPeriodo->execute();
    $edicoesPorPeriodo = $stmtPeriodo->fetchAll(PDO::FETCH_ASSOC);
    
    // === PREPARAR DADOS DE PAGINAÇÃO ===
    
    $paginacao = [
        'pagina_atual' => $page,
        'total_paginas' => $totalPaginas,
        'total_registros' => (int)$totalEdicoes,
        'registros_por_pagina' => $limit,
        'tem_proxima' => $page < $totalPaginas,
        'tem_anterior' => $page > 1,
        'primeira_pagina' => 1,
        'ultima_pagina' => $totalPaginas,
        'inicio_registro' => $offset + 1,
        'fim_registro' => min($offset + $limit, $totalEdicoes)
    ];
    
    // === FILTROS APLICADOS ===
    
    $filtrosAplicados = [];
    foreach (['tabela', 'funcionario_id', 'associado_id', 'data_inicio', 'data_fim', 'search', 'departamento_usuario'] as $filtro) {
        if (!empty($_GET[$filtro])) {
            $filtrosAplicados[$filtro] = $_GET[$filtro];
        }
    }
    
    // === RESPOSTA DE SUCESSO ===
    
    $response = [
        'status' => 'success',
        'message' => 'Edições obtidas com sucesso',
        'data' => [
            'edicoes' => $edicoesProcessadas,
            'paginacao' => $paginacao,
            'filtros_aplicados' => $filtrosAplicados,
            'estatisticas' => [
                'total_edicoes' => (int)$totalEdicoes,
                'edicoes_por_tabela' => $estatisticasPorTabela,
                'edicoes_por_periodo' => $edicoesPorPeriodo,
                'tabelas_permitidas' => $tabelasPermitidas
            ],
            'resumo' => [
                'total_encontradas' => count($edicoesProcessadas),
                'pagina_atual' => $page,
                'total_geral' => (int)$totalEdicoes,
                'periodo_consulta' => [
                    'inicio' => $filtrosAplicados['data_inicio'] ?? 'Todas as datas',
                    'fim' => $filtrosAplicados['data_fim'] ?? 'Todas as datas'
                ]
            ]
        ],
        'meta' => [
            'tempo_execucao' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'timestamp' => time(),
            'versao_api' => '1.1',
            'endpoint' => 'edicoes',
            'metodo' => $_SERVER['REQUEST_METHOD']
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    // Erro específico do banco de dados
    error_log("Erro PDO na API de edições: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de banco de dados',
        'error_code' => 'DB_ERROR_EDICOES_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Outros erros
    error_log("Erro na API de edições: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor: ' . $e->getMessage(),
        'error_code' => 'GENERAL_ERROR_EDICOES_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Error $e) {
    // Erros fatais do PHP
    error_log("Erro fatal na API de edições: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro fatal do sistema',
        'error_code' => 'FATAL_ERROR_EDICOES_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>