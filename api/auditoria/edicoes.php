<?php
/**
 * API específica para edições de associados e funcionários - VERSÃO ROBUSTA
 * /api/auditoria/edicoes.php
 */

// IMPORTANTE: Não mostrar erros na saída para não quebrar o JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Headers obrigatórios
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Função para retornar erro em JSON
function retornarErro($message, $code = 500, $details = null) {
    http_response_code($code);
    $error = [
        'status' => 'error',
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => 'edicoes'
    ];
    
    if ($details) {
        $error['details'] = $details;
    }
    
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para log de debug
function debugLog($message) {
    error_log("[EDICOES_API] " . $message);
}

try {
    debugLog("=== INÍCIO DA REQUISIÇÃO ===");
    debugLog("Método: " . $_SERVER['REQUEST_METHOD']);
    debugLog("Parâmetros GET: " . json_encode($_GET));
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        retornarErro('Método não permitido', 405);
    }

    // Verificar se os arquivos necessários existem
    $arquivosNecessarios = [
        '../../config/config.php',
        '../../config/database.php', 
        '../../classes/Database.php'
    ];
    
    foreach ($arquivosNecessarios as $arquivo) {
        if (!file_exists($arquivo)) {
            retornarErro("Arquivo necessário não encontrado: " . basename($arquivo), 500);
        }
    }
    
    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    debugLog("Arquivos carregados com sucesso");
    
    // Verificar se as constantes necessárias existem
    if (!defined('DB_NAME_CADASTRO')) {
        retornarErro('Configuração do banco de dados não encontrada', 500);
    }
    
    // Conectar ao banco
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        debugLog("Conexão com banco estabelecida");
    } catch (Exception $e) {
        debugLog("Erro na conexão: " . $e->getMessage());
        retornarErro('Erro na conexão com banco de dados', 500, $e->getMessage());
    }
    
    // Verificar se é solicitação de exportação
    if (isset($_GET['export']) && $_GET['export'] == 1) {
        debugLog("Solicitação de exportação detectada");
        exportarEdicoes($db);
        exit;
    }
    
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    debugLog("Paginação - Página: $page, Limit: $limit, Offset: $offset");
    
    // Preparar filtros básicos
    $whereConditions = ["a.acao = 'UPDATE'"]; // Apenas edições
    $params = [];
    
    // Filtrar apenas por associados e funcionários
    $tabelasPermitidas = ['Associados', 'Funcionarios'];
    $whereConditions[] = "a.tabela IN ('" . implode("','", $tabelasPermitidas) . "')";
    
    debugLog("Filtros básicos aplicados");
    
    // Filtro por tabela específica
    if (!empty($_GET['tabela'])) {
        $tabelaSolicitada = $_GET['tabela'];
        if (in_array($tabelaSolicitada, $tabelasPermitidas)) {
            $whereConditions[] = "a.tabela = :tabela";
            $params[':tabela'] = $tabelaSolicitada;
            debugLog("Filtro por tabela: $tabelaSolicitada");
        }
    }
    
    // Filtro por funcionário
    if (!empty($_GET['funcionario_id'])) {
        $whereConditions[] = "a.funcionario_id = :funcionario_id";
        $params[':funcionario_id'] = (int)$_GET['funcionario_id'];
        debugLog("Filtro por funcionário: " . $_GET['funcionario_id']);
    }
    
    // Filtro por associado
    if (!empty($_GET['associado_id'])) {
        $whereConditions[] = "a.associado_id = :associado_id";
        $params[':associado_id'] = (int)$_GET['associado_id'];
        debugLog("Filtro por associado: " . $_GET['associado_id']);
    }
    
    // Filtro por data início
    if (!empty($_GET['data_inicio'])) {
        $whereConditions[] = "DATE(a.data_hora) >= :data_inicio";
        $params[':data_inicio'] = $_GET['data_inicio'];
        debugLog("Filtro data início: " . $_GET['data_inicio']);
    }
    
    // Filtro por data fim
    if (!empty($_GET['data_fim'])) {
        $whereConditions[] = "DATE(a.data_hora) <= :data_fim";
        $params[':data_fim'] = $_GET['data_fim'];
        debugLog("Filtro data fim: " . $_GET['data_fim']);
    }
    
    // Filtro por busca
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(f.nome LIKE :search OR ass.nome LIKE :search2 OR a.tabela LIKE :search3)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
        $params[':search3'] = $search;
        debugLog("Filtro de busca: " . $_GET['search']);
    }
    
    // *** FILTRO DEPARTAMENTAL - CORREÇÃO PRINCIPAL ***
    if (!empty($_GET['departamento_usuario'])) {
        $deptUsuario = (int)$_GET['departamento_usuario'];
        debugLog("🔍 Aplicando filtro departamental para departamento: $deptUsuario");
        
        // Filtro restritivo - apenas edições de funcionários do departamento
        $whereConditions[] = "f.departamento_id = :departamento_usuario";
        $params[':departamento_usuario'] = $deptUsuario;
        
        debugLog("✅ Filtro departamental configurado");
    } else {
        debugLog("⚠️ Sem filtro departamental");
    }
    
    // Construir cláusula WHERE
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    debugLog("WHERE clause: $whereClause");
    
    // === QUERY PRINCIPAL ===
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
            a.funcionario_id,
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
    
    debugLog("SQL Query preparada");
    
    try {
        $stmt = $db->prepare($sql);
        
        // Bind dos parâmetros
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
            debugLog("Parâmetro $key: $value");
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $edicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        debugLog("Query executada - " . count($edicoes) . " edições encontradas");
        
    } catch (PDOException $e) {
        debugLog("Erro na query principal: " . $e->getMessage());
        retornarErro('Erro na consulta ao banco de dados', 500, $e->getMessage());
    }
    
    // === CONTAR TOTAL ===
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id
        $whereClause
    ";
    
    try {
        $stmtCount = $db->prepare($sqlCount);
        foreach ($params as $key => $value) {
            if ($key !== ':limit' && $key !== ':offset') {
                $stmtCount->bindValue($key, $value);
            }
        }
        $stmtCount->execute();
        $totalEdicoes = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        debugLog("Total de edições: $totalEdicoes");
        
    } catch (PDOException $e) {
        debugLog("Erro na query de contagem: " . $e->getMessage());
        retornarErro('Erro na contagem de registros', 500, $e->getMessage());
    }
    
    $totalPaginas = ceil($totalEdicoes / $limit);
    
    // === PROCESSAR EDIÇÕES ===
    $edicoesProcessadas = [];
    
    foreach ($edicoes as $edicao) {
        // Validação extra para filtro departamental
        if (!empty($_GET['departamento_usuario'])) {
            $deptUsuario = (int)$_GET['departamento_usuario'];
            
            if ($edicao['funcionario_departamento_id'] && 
                $edicao['funcionario_departamento_id'] != $deptUsuario) {
                debugLog("Edição {$edicao['id']} filtrada - dept {$edicao['funcionario_departamento_id']} != $deptUsuario");
                continue;
            }
        }
        
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
            'funcionario_id' => $edicao['funcionario_id'],
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
        
        // Processar alterações
        $processada['alteracoes_decoded'] = null;
        $processada['campos_alterados'] = 0;
        $processada['resumo_edicao'] = 'Dados alterados';
        
        if (!empty($processada['alteracoes'])) {
            $alteracoesDecoded = json_decode($processada['alteracoes'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($alteracoesDecoded)) {
                $processada['alteracoes_decoded'] = $alteracoesDecoded;
                $processada['campos_alterados'] = count($alteracoesDecoded);
                
                if ($processada['campos_alterados'] === 1) {
                    $processada['resumo_edicao'] = '1 campo alterado';
                } elseif ($processada['campos_alterados'] <= 3) {
                    $processada['resumo_edicao'] = $processada['campos_alterados'] . ' campos alterados';
                } else {
                    $processada['resumo_edicao'] = $processada['campos_alterados'] . ' campos alterados (extensa)';
                }
            }
        }
        
        // Informações do registro
        if ($processada['tabela'] === 'Associados') {
            $processada['tipo_registro'] = 'Associado';
            $processada['nome_registro'] = $processada['associado_nome'] ?: 'Associado ID ' . $processada['registro_id'];
        } elseif ($processada['tabela'] === 'Funcionarios') {
            $processada['tipo_registro'] = 'Funcionário';
            $processada['nome_registro'] = 'Funcionário ID ' . $processada['registro_id'];
        }
        
        $edicoesProcessadas[] = $processada;
    }
    
    debugLog("Edições processadas: " . count($edicoesProcessadas));
    
    // Log do resultado final para debug departamental
    if (!empty($_GET['departamento_usuario'])) {
        $deptUsuario = (int)$_GET['departamento_usuario'];
        $departamentosEncontrados = array_unique(array_filter(array_column($edicoesProcessadas, 'funcionario_departamento_id')));
        debugLog("📊 Departamento $deptUsuario: " . count($edicoesProcessadas) . " edições");
        debugLog("🏢 Departamentos encontrados: " . implode(', ', $departamentosEncontrados ?: ['nenhum']));
    }
    
    // === PREPARAR PAGINAÇÃO ===
    $paginacao = [
        'pagina_atual' => $page,
        'total_paginas' => max(1, $totalPaginas),
        'total_registros' => (int)$totalEdicoes,
        'registros_por_pagina' => $limit,
        'tem_proxima' => $page < $totalPaginas,
        'tem_anterior' => $page > 1,
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
    
    // === RESPOSTA FINAL ===
    $response = [
        'status' => 'success',
        'message' => 'Edições obtidas com sucesso',
        'data' => [
            'edicoes' => $edicoesProcessadas,
            'paginacao' => $paginacao,
            'filtros_aplicados' => $filtrosAplicados,
            'resumo' => [
                'total_encontradas' => count($edicoesProcessadas),
                'pagina_atual' => $page,
                'total_geral' => (int)$totalEdicoes,
                'filtro_departamental' => !empty($_GET['departamento_usuario']) ? 
                    "Departamento " . $_GET['departamento_usuario'] : 'Sem filtro'
            ]
        ],
        'meta' => [
            'tempo_execucao' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'timestamp' => time(),
            'versao_api' => '1.3',
            'endpoint' => 'edicoes'
        ]
    ];
    
    debugLog("=== RESPOSTA ENVIADA COM SUCESSO ===");
    debugLog("Edições retornadas: " . count($edicoesProcessadas));
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    debugLog("ERRO GERAL: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());
    retornarErro('Erro interno do servidor: ' . $e->getMessage(), 500);
} catch (Error $e) {
    debugLog("ERRO FATAL: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());  
    retornarErro('Erro fatal do sistema: ' . $e->getMessage(), 500);
}

// === FUNÇÃO DE EXPORTAÇÃO ===
function exportarEdicoes($db) {
    try {
        debugLog("📤 Iniciando exportação");
        
        $whereConditions = ["a.acao = 'UPDATE'"];
        $params = [];
        
        $whereConditions[] = "a.tabela IN ('Associados', 'Funcionarios')";
        
        if (!empty($_GET['departamento_usuario'])) {
            $deptUsuario = (int)$_GET['departamento_usuario'];
            $whereConditions[] = "f.departamento_id = :departamento_usuario";
            $params[':departamento_usuario'] = $deptUsuario;
            debugLog("📤 Exportando departamento: $deptUsuario");
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                a.id,
                a.tabela,
                a.data_hora,
                a.registro_id,
                f.nome as funcionario_nome,
                f.departamento_id as funcionario_departamento_id,
                d.nome as funcionario_departamento,
                ass.nome as associado_nome,
                ass.cpf as associado_cpf,
                a.alteracoes
            FROM Auditoria a
            LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            LEFT JOIN Associados ass ON a.associado_id = ass.id
            $whereClause
            ORDER BY a.data_hora DESC
        ";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $edicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sufixo = !empty($_GET['departamento_usuario']) ? 
            '_dept_' . $_GET['departamento_usuario'] : '_completo';
        
        $filename = 'edicoes' . $sufixo . '_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        fputcsv($output, [
            'ID', 'Data/Hora', 'Tabela', 'Registro ID', 
            'Funcionário', 'Departamento', 'Associado', 'CPF'
        ]);
        
        foreach ($edicoes as $edicao) {
            fputcsv($output, [
                $edicao['id'],
                $edicao['data_hora'],
                $edicao['tabela'],
                $edicao['registro_id'],
                $edicao['funcionario_nome'] ?: 'Sistema',
                $edicao['funcionario_departamento'] ?: 'N/A',
                $edicao['associado_nome'] ?: 'N/A',
                $edicao['associado_cpf'] ?: 'N/A'
            ]);
        }
        
        fclose($output);
        debugLog("✅ Exportação concluída: " . count($edicoes) . " registros");
        
    } catch (Exception $e) {
        debugLog("❌ Erro na exportação: " . $e->getMessage());
        retornarErro('Erro ao exportar: ' . $e->getMessage(), 500);
    }
}
?>