<?php
/**
 * API para gerar relatórios comerciais - VERSÃO CORRIGIDA (SEM DUPLICAÇÕES)
 * api/relatorios/gerar_relatorio_comercial.php
 * 
 * @author Sistema ASSEGO
 * @version 14.0 - Correção de duplicação de dados
 */

// Desabilitar saída de erro para o browser
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpar buffers
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Modo debug
$DEBUG_MODE = isset($_GET['debug']) && $_GET['debug'] === 'true';

// Função para enviar resposta JSON
function sendJsonResponse($data, $statusCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Tratamento de erro fatal
register_shutdown_function(function() use ($DEBUG_MODE) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro interno do servidor',
            'debug' => $DEBUG_MODE ? $error : null
        ], 500);
    }
});

try {
    // Carregar configurações
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Permissoes.php';
    
    // Verificar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Sessão expirada. Por favor, faça login novamente.'
        ], 401);
    }
    
    // Verificar permissão
    if (!Permissoes::tem('COMERCIAL_RELATORIOS')) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Sem permissão para acessar relatórios.'
        ], 403);
    }
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Configurar MySQL
    $db->exec("SET NAMES utf8mb4");
    $db->exec("SET sql_mode = ''");
    
    // Pegar parâmetros
    $tipo = isset($_GET['tipo']) ? preg_replace('/[^a-z_]/', '', strtolower($_GET['tipo'])) : 'desfiliacoes';
    $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
    $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
    $corporacao = isset($_GET['corporacao']) ? trim($_GET['corporacao']) : '';
    $patente = isset($_GET['patente']) ? trim($_GET['patente']) : '';
    $lotacao = isset($_GET['lotacao']) ? trim($_GET['lotacao']) : '';
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $ordenacao = isset($_GET['ordenacao']) ? $_GET['ordenacao'] : 'nome';
    
    // Array para debug
    $debugInfo = [];
    
    // Executar relatório
    $resultado = [];
    
    switch ($tipo) {
        case 'desfiliacoes':
            $resultado = gerarRelatorioDesfiliacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $DEBUG_MODE);
            break;
            
        case 'indicacoes':
            $resultado = gerarRelatorioIndicacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca, $DEBUG_MODE);
            break;
            
        case 'aniversariantes':
            $resultado = gerarRelatorioAniversariantes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $DEBUG_MODE);
            break;
            
        case 'novos_cadastros':
            $resultado = gerarRelatorioNovosCadastros($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $DEBUG_MODE);
            break;
            
        default:
            throw new Exception('Tipo de relatório inválido');
    }
    
    // Resposta
    $response = [
        'success' => true,
        'data' => $resultado['data'] ?? $resultado,
        'total' => count($resultado['data'] ?? $resultado),
        'filtros' => [
            'tipo' => $tipo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'corporacao' => $corporacao,
            'patente' => $patente,
            'lotacao' => $lotacao
        ]
    ];
    
    if ($DEBUG_MODE) {
        $response['debug'] = array_merge($debugInfo, [
            'query_info' => $resultado['debug'] ?? null
        ]);
    }
    
    sendJsonResponse($response);
    
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $DEBUG_MODE ? [
            'trace' => $e->getTraceAsString()
        ] : null
    ], 400);
}

/**
 * RELATÓRIO DE DESFILIAÇÕES - CORRIGIDO COM DISTINCT
 */
function gerarRelatorioDesfiliacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $debug = false) {
    $debugInfo = [];
    
    try {
        // QUERY CORRIGIDA COM DISTINCT E GROUP BY
        $sql = "
            SELECT DISTINCT
                a.id,
                a.nome,
                COALESCE(a.rg, '') as rg,
                COALESCE(a.cpf, '') as cpf,
                COALESCE(a.telefone, '') as telefone,
                COALESCE(a.email, '') as email,
                MAX(COALESCE(m.patente, '')) as patente,
                MAX(COALESCE(m.corporacao, '')) as corporacao,
                MAX(COALESCE(m.lotacao, '')) as lotacao,
                a.situacao as situacao_atual,
                COALESCE(
                    DATE(a.data_desfiliacao),
                    MAX(DATE(c.dataDesfiliacao)),
                    NULL
                ) as data_desfiliacao
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filtro de situação
        $sql .= " AND (
            UPPER(a.situacao) LIKE '%DESFIL%'
            OR UPPER(a.situacao) LIKE '%DESLIG%'
            OR UPPER(a.situacao) LIKE '%CANCEL%'
            OR UPPER(a.situacao) LIKE '%INATIV%'
            OR UPPER(a.situacao) IN ('DESFILIADO', 'DESLIGADO', 'CANCELADO', 'INATIVO')
        )";
        
        // Filtro de data
        if ($dataInicio && $dataFim) {
            $sql .= " AND (
                (a.data_desfiliacao BETWEEN :data_inicio1 AND :data_fim1)
                OR (c.dataDesfiliacao BETWEEN :data_inicio2 AND :data_fim2)
                OR (a.data_desfiliacao IS NULL AND c.dataDesfiliacao IS NULL)
            )";
            $params[':data_inicio1'] = $dataInicio;
            $params[':data_fim1'] = $dataFim . ' 23:59:59';
            $params[':data_inicio2'] = $dataInicio;
            $params[':data_fim2'] = $dataFim;
        }
        
        // GROUP BY para evitar duplicação
        $sql .= " GROUP BY a.id, a.nome, a.rg, a.cpf, a.telefone, a.email, a.situacao, a.data_desfiliacao";
        
        // Aplicar filtros HAVING após GROUP BY
        if (!empty($corporacao)) {
            $sql .= " HAVING MAX(m.corporacao) = :corporacao";
            $params[':corporacao'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $sql .= (!empty($corporacao) ? " AND " : " HAVING ") . "MAX(m.patente) = :patente";
            $params[':patente'] = $patente;
        }
        
        if (!empty($lotacao)) {
            $havingClause = (!empty($corporacao) || !empty($patente)) ? " AND " : " HAVING ";
            $sql .= $havingClause . "MAX(m.lotacao) LIKE :lotacao";
            $params[':lotacao'] = '%' . $lotacao . '%';
        }
        
        // Filtro de busca
        if (!empty($busca)) {
            $buscaLimpa = preg_replace('/\D/', '', $busca);
            $havingClause = (!empty($corporacao) || !empty($patente) || !empty($lotacao)) ? " AND " : " HAVING ";
            $sql .= $havingClause . "(
                a.nome LIKE :busca 
                OR REPLACE(REPLACE(a.cpf, '.', ''), '-', '') LIKE :busca_cpf 
                OR a.rg LIKE :busca_rg
            )";
            $params[':busca'] = '%' . $busca . '%';
            $params[':busca_cpf'] = '%' . $buscaLimpa . '%';
            $params[':busca_rg'] = '%' . $busca . '%';
        }
        
        // Ordenação
        $sql .= " ORDER BY a.nome ASC LIMIT 2000";
        
        if ($debug) {
            $debugInfo['query'] = $sql;
            $debugInfo['params'] = $params;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        // Limpar dados e garantir unicidade
        $resultadosUnicos = [];
        $idsProcessados = [];
        
        foreach ($data as $row) {
            if (!in_array($row['id'], $idsProcessados)) {
                if ($row['data_desfiliacao'] == '0000-00-00' || empty($row['data_desfiliacao'])) {
                    $row['data_desfiliacao'] = '';
                }
                $resultadosUnicos[] = $row;
                $idsProcessados[] = $row['id'];
            }
        }
        
        if ($debug) {
            $debugInfo['total_encontrado'] = count($resultadosUnicos);
            $debugInfo['ids_unicos'] = count($idsProcessados);
            return ['data' => $resultadosUnicos, 'debug' => $debugInfo];
        }
        
        return $resultadosUnicos;
        
    } catch (Exception $e) {
        if ($debug) {
            $debugInfo['erro'] = $e->getMessage();
            return ['data' => [], 'debug' => $debugInfo];
        }
        return [];
    }
}

/**
 * RELATÓRIO DE ANIVERSARIANTES - CORRIGIDO COM DISTINCT
 */
function gerarRelatorioAniversariantes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $debug = false) {
    try {
        $mesInicio = (int)date('m', strtotime($dataInicio));
        $mesFim = (int)date('m', strtotime($dataFim));
        
        // QUERY CORRIGIDA COM DISTINCT E GROUP BY
        $sql = "
            SELECT DISTINCT
                a.id,
                a.nome,
                DATE(a.nasc) as data_nascimento,
                COALESCE(a.telefone, '') as telefone,
                COALESCE(a.email, '') as email,
                MAX(COALESCE(m.patente, '')) as patente,
                MAX(COALESCE(m.corporacao, '')) as corporacao,
                MAX(COALESCE(m.lotacao, '')) as lotacao,
                CASE 
                    WHEN a.nasc IS NOT NULL AND YEAR(a.nasc) > 1900
                    THEN YEAR(CURDATE()) - YEAR(a.nasc)
                    ELSE 0
                END as idade
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE 1=1
        ";
        
        // Filtro de situação
        $sql .= " AND (
            UPPER(a.situacao) IN ('FILIADO', 'ATIVO', 'ATIVA', 'ASSOCIADO')
            OR UPPER(a.situacao) LIKE '%ATIV%'
            OR UPPER(a.situacao) LIKE '%FILIAD%'
        )";
        
        $sql .= " AND a.nasc IS NOT NULL
                  AND a.nasc != '0000-00-00'
                  AND YEAR(a.nasc) > 1900
                  AND YEAR(a.nasc) < YEAR(CURDATE())";
        
        $params = [];
        
        // Filtro de mês
        if ($mesInicio == $mesFim) {
            $sql .= " AND MONTH(a.nasc) = :mes";
            $params[':mes'] = $mesInicio;
        } else if ($mesInicio < $mesFim) {
            $sql .= " AND MONTH(a.nasc) BETWEEN :mes_inicio AND :mes_fim";
            $params[':mes_inicio'] = $mesInicio;
            $params[':mes_fim'] = $mesFim;
        } else {
            $sql .= " AND (MONTH(a.nasc) >= :mes_inicio OR MONTH(a.nasc) <= :mes_fim)";
            $params[':mes_inicio'] = $mesInicio;
            $params[':mes_fim'] = $mesFim;
        }
        
        // GROUP BY para evitar duplicação
        $sql .= " GROUP BY a.id, a.nome, a.nasc, a.telefone, a.email";
        
        // Aplicar filtros HAVING após GROUP BY
        if (!empty($corporacao)) {
            $sql .= " HAVING MAX(m.corporacao) = :corporacao";
            $params[':corporacao'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $havingClause = !empty($corporacao) ? " AND " : " HAVING ";
            $sql .= $havingClause . "MAX(m.patente) = :patente";
            $params[':patente'] = $patente;
        }
        
        if (!empty($lotacao)) {
            $havingClause = (!empty($corporacao) || !empty($patente)) ? " AND " : " HAVING ";
            $sql .= $havingClause . "MAX(m.lotacao) LIKE :lotacao";
            $params[':lotacao'] = '%' . $lotacao . '%';
        }
        
        if (!empty($busca)) {
            $havingClause = (!empty($corporacao) || !empty($patente) || !empty($lotacao)) ? " AND " : " HAVING ";
            $sql .= $havingClause . "a.nome LIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }
        
        $sql .= " ORDER BY MONTH(a.nasc), DAY(a.nasc), a.nome LIMIT 2000";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $data = $stmt->fetchAll();
        
        // Garantir unicidade e limpar dados
        $resultadosUnicos = [];
        $idsProcessados = [];
        
        foreach ($data as $row) {
            if (!in_array($row['id'], $idsProcessados)) {
                if ($row['data_nascimento'] == '0000-00-00' || empty($row['data_nascimento'])) {
                    $row['data_nascimento'] = '';
                }
                $resultadosUnicos[] = $row;
                $idsProcessados[] = $row['id'];
            }
        }
        
        return $resultadosUnicos;
        
    } catch (Exception $e) {
        if ($debug) {
            return ['data' => [], 'debug' => ['erro' => $e->getMessage()]];
        }
        return [];
    }
}

/**
 * RELATÓRIO DE NOVOS CADASTROS - CORRIGIDO COM DISTINCT
 */
function gerarRelatorioNovosCadastros($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $debug = false) {
    try {
        // QUERY CORRIGIDA COM DISTINCT E GROUP BY
        $sql = "
            SELECT DISTINCT
                a.id,
                a.nome,
                COALESCE(a.rg, '') as rg,
                COALESCE(a.cpf, '') as cpf,
                COALESCE(a.telefone, '') as telefone,
                COALESCE(a.email, '') as email,
                MAX(COALESCE(m.patente, '')) as patente,
                MAX(COALESCE(m.corporacao, '')) as corporacao,
                MAX(COALESCE(m.lotacao, '')) as lotacao,
                COALESCE(a.indicacao, '') as indicado_por,
                COALESCE(
                    DATE(a.data_aprovacao),
                    DATE(a.data_pre_cadastro),
                    MAX(DATE(c.dataFiliacao)),
                    NULL
                ) as data_aprovacao,
                CASE 
                    WHEN a.pre_cadastro = 1 THEN 'Pré-cadastro'
                    ELSE 'Cadastro Definitivo'
                END as tipo_cadastro
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE 1=1
        ";
        
        // Filtro de situação
        $sql .= " AND (
            UPPER(a.situacao) IN ('FILIADO', 'ATIVO', 'ATIVA', 'ASSOCIADO')
            OR UPPER(a.situacao) LIKE '%ATIV%'
            OR UPPER(a.situacao) LIKE '%FILIAD%'
        )";
        
        $params = [];
        
        // Filtro de data
        if ($dataInicio && $dataFim) {
            $sql .= " AND (
                DATE(a.data_aprovacao) BETWEEN :data_inicio1 AND :data_fim1
                OR DATE(a.data_pre_cadastro) BETWEEN :data_inicio2 AND :data_fim2
                OR c.dataFiliacao BETWEEN :data_inicio3 AND :data_fim3
            )";
            
            $params[':data_inicio1'] = $dataInicio;
            $params[':data_fim1'] = $dataFim;
            $params[':data_inicio2'] = $dataInicio;
            $params[':data_fim2'] = $dataFim;
            $params[':data_inicio3'] = $dataInicio;
            $params[':data_fim3'] = $dataFim;
        }
        
        // GROUP BY para evitar duplicação
        $sql .= " GROUP BY a.id, a.nome, a.rg, a.cpf, a.telefone, a.email, 
                  a.indicacao, a.data_aprovacao, a.data_pre_cadastro, a.pre_cadastro";
        
        // Aplicar filtros HAVING após GROUP BY
        if (!empty($corporacao)) {
            $sql .= " HAVING MAX(m.corporacao) = :corporacao";
            $params[':corporacao'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $havingClause = !empty($corporacao) ? " AND " : " HAVING ";
            $sql .= $havingClause . "MAX(m.patente) = :patente";
            $params[':patente'] = $patente;
        }
        
        if (!empty($lotacao)) {
            $havingClause = (!empty($corporacao) || !empty($patente)) ? " AND " : " HAVING ";
            $sql .= $havingClause . "MAX(m.lotacao) LIKE :lotacao";
            $params[':lotacao'] = '%' . $lotacao . '%';
        }
        
        if (!empty($busca)) {
            $havingClause = (!empty($corporacao) || !empty($patente) || !empty($lotacao)) ? " AND " : " HAVING ";
            $sql .= $havingClause . "(a.nome LIKE :busca OR a.indicacao LIKE :busca2)";
            $params[':busca'] = '%' . $busca . '%';
            $params[':busca2'] = '%' . $busca . '%';
        }
        
        $sql .= " ORDER BY a.nome LIMIT 2000";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $data = $stmt->fetchAll();
        
        // Garantir unicidade e limpar dados
        $resultadosUnicos = [];
        $idsProcessados = [];
        
        foreach ($data as $row) {
            if (!in_array($row['id'], $idsProcessados)) {
                if ($row['data_aprovacao'] == '0000-00-00' || empty($row['data_aprovacao'])) {
                    $row['data_aprovacao'] = '';
                }
                $resultadosUnicos[] = $row;
                $idsProcessados[] = $row['id'];
            }
        }
        
        return $resultadosUnicos;
        
    } catch (Exception $e) {
        if ($debug) {
            return ['data' => [], 'debug' => ['erro' => $e->getMessage()]];
        }
        return [];
    }
}

/**
 * FUNÇÃO CORRIGIDA: RELATÓRIO DE INDICAÇÕES COM DISTINCT
 */
function gerarRelatorioIndicacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca, $debug = false) {
    $debugInfo = [];
    
    try {
        // Verificar se tabela Historico_Indicacoes existe
        $stmt = $db->query("SHOW TABLES LIKE 'Historico_Indicacoes'");
        if ($stmt->rowCount() == 0) {
            if ($debug) {
                $debugInfo['erro'] = 'Tabela Historico_Indicacoes não existe';
                return ['data' => [], 'debug' => $debugInfo];
            }
            return [];
        }
        
        // QUERY CORRIGIDA COM DISTINCT
        $sql = "
            SELECT DISTINCT
                hi.id as registro_id,
                DATE(hi.data_indicacao) as data_indicacao,
                
                -- DADOS DO INDICADOR (QUEM INDICOU)
                COALESCE(i.id, 0) as indicador_id,
                COALESCE(i.nome_completo, hi.indicador_nome, 'Não identificado') as indicador_nome,
                MAX(COALESCE(i.patente, '')) as indicador_patente,
                MAX(COALESCE(i.corporacao, '')) as indicador_corporacao,
                
                -- DADOS DO ASSOCIADO INDICADO (QUEM FOI INDICADO)
                a.id as associado_indicado_id,
                a.nome as associado_indicado_nome,
                COALESCE(a.cpf, '') as associado_indicado_cpf,
                COALESCE(a.rg, '') as associado_indicado_rg,
                COALESCE(a.telefone, '') as associado_indicado_telefone,
                COALESCE(a.email, '') as associado_indicado_email,
                MAX(COALESCE(m.patente, '')) as associado_indicado_patente,
                MAX(COALESCE(m.corporacao, '')) as associado_indicado_corporacao,
                MAX(COALESCE(m.lotacao, '')) as associado_indicado_lotacao,
                
                -- STATUS E INFORMAÇÕES ADICIONAIS
                a.situacao as situacao_associado,
                CASE 
                    WHEN UPPER(a.situacao) IN ('FILIADO', 'ATIVO', 'ATIVA', 'ASSOCIADO') THEN 'Ativo'
                    WHEN UPPER(a.situacao) LIKE '%DESFIL%' THEN 'Desfiliado'
                    WHEN UPPER(a.situacao) LIKE '%INATIV%' THEN 'Inativo'
                    ELSE a.situacao
                END as status_simplificado,
                CASE 
                    WHEN a.pre_cadastro = 1 THEN 'Pré-cadastro'
                    ELSE 'Cadastro Definitivo'
                END as tipo_cadastro,
                DATE(a.data_aprovacao) as data_aprovacao_cadastro,
                
                -- OBSERVAÇÕES
                COALESCE(hi.observacao, '') as observacao_indicacao
                
            FROM Historico_Indicacoes hi
            INNER JOIN Associados a ON hi.associado_id = a.id
            LEFT JOIN Indicadores i ON hi.indicador_id = i.id
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filtro de data
        if ($dataInicio && $dataFim) {
            $sql .= " AND DATE(hi.data_indicacao) BETWEEN :data_inicio AND :data_fim";
            $params[':data_inicio'] = $dataInicio;
            $params[':data_fim'] = $dataFim;
        }
        
        // GROUP BY para evitar duplicação
        $sql .= " GROUP BY hi.id, hi.data_indicacao, hi.indicador_nome, hi.observacao,
                  a.id, a.nome, a.cpf, a.rg, a.telefone, a.email, a.situacao, 
                  a.pre_cadastro, a.data_aprovacao, i.id, i.nome_completo";
        
        // Filtros HAVING após GROUP BY
        if (!empty($corporacao)) {
            $sql .= " HAVING (MAX(i.corporacao) = :corporacao1 OR MAX(m.corporacao) = :corporacao2)";
            $params[':corporacao1'] = $corporacao;
            $params[':corporacao2'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $havingClause = !empty($corporacao) ? " AND " : " HAVING ";
            $sql .= $havingClause . "(MAX(i.patente) = :patente1 OR MAX(m.patente) = :patente2)";
            $params[':patente1'] = $patente;
            $params[':patente2'] = $patente;
        }
        
        // Filtro de busca
        if (!empty($busca)) {
            $buscaLimpa = preg_replace('/\D/', '', $busca);
            $havingClause = (!empty($corporacao) || !empty($patente)) ? " AND " : " HAVING ";
            $sql .= $havingClause . "(
                i.nome_completo LIKE :busca1 
                OR hi.indicador_nome LIKE :busca2
                OR a.nome LIKE :busca3
                OR REPLACE(REPLACE(a.cpf, '.', ''), '-', '') LIKE :busca_cpf
                OR a.rg LIKE :busca_rg
                OR a.email LIKE :busca_email
            )";
            $params[':busca1'] = '%' . $busca . '%';
            $params[':busca2'] = '%' . $busca . '%';
            $params[':busca3'] = '%' . $busca . '%';
            $params[':busca_cpf'] = '%' . $buscaLimpa . '%';
            $params[':busca_rg'] = '%' . $busca . '%';
            $params[':busca_email'] = '%' . $busca . '%';
        }
        
        // Ordenação
        $sql .= " ORDER BY hi.data_indicacao DESC, hi.indicador_nome ASC, a.nome ASC
                  LIMIT 5000";
        
        if ($debug) {
            $debugInfo['query'] = $sql;
            $debugInfo['params'] = $params;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        // Processar e garantir unicidade
        $resultadosUnicos = [];
        $registrosProcessados = [];
        
        foreach ($data as &$row) {
            $chaveUnica = $row['registro_id'] . '-' . $row['associado_indicado_id'];
            
            if (!in_array($chaveUnica, $registrosProcessados)) {
                // Limpar datas inválidas
                if (isset($row['data_indicacao']) && ($row['data_indicacao'] == '0000-00-00' || empty($row['data_indicacao']))) {
                    $row['data_indicacao'] = '';
                }
                if (isset($row['data_aprovacao_cadastro']) && ($row['data_aprovacao_cadastro'] == '0000-00-00' || empty($row['data_aprovacao_cadastro']))) {
                    $row['data_aprovacao_cadastro'] = '';
                }
                
                // Formatar CPF se existir
                if (!empty($row['associado_indicado_cpf'])) {
                    $cpf = preg_replace('/\D/', '', $row['associado_indicado_cpf']);
                    if (strlen($cpf) == 11) {
                        $row['associado_indicado_cpf_formatado'] = substr($cpf, 0, 3) . '.' . 
                                                                    substr($cpf, 3, 3) . '.' . 
                                                                    substr($cpf, 6, 3) . '-' . 
                                                                    substr($cpf, 9, 2);
                    } else {
                        $row['associado_indicado_cpf_formatado'] = $row['associado_indicado_cpf'];
                    }
                }
                
                // Formatar telefone se existir
                if (!empty($row['associado_indicado_telefone'])) {
                    $tel = preg_replace('/\D/', '', $row['associado_indicado_telefone']);
                    if (strlen($tel) == 11) {
                        $row['associado_indicado_telefone_formatado'] = '(' . substr($tel, 0, 2) . ') ' . 
                                                                         substr($tel, 2, 5) . '-' . 
                                                                         substr($tel, 7, 4);
                    } elseif (strlen($tel) == 10) {
                        $row['associado_indicado_telefone_formatado'] = '(' . substr($tel, 0, 2) . ') ' . 
                                                                         substr($tel, 2, 4) . '-' . 
                                                                         substr($tel, 6, 4);
                    } else {
                        $row['associado_indicado_telefone_formatado'] = $row['associado_indicado_telefone'];
                    }
                }
                
                // Calcular totalizadores
                $row['total_indicacoes_do_indicador'] = calcularTotalIndicacoes($db, $row['indicador_id'], $row['indicador_nome']);
                $row['total_indicados_ativos'] = calcularIndicadosAtivos($db, $row['indicador_id'], $row['indicador_nome']);
                
                $resultadosUnicos[] = $row;
                $registrosProcessados[] = $chaveUnica;
            }
        }
        
        if ($debug) {
            $debugInfo['total_registros'] = count($resultadosUnicos);
            $debugInfo['registros_unicos'] = count($registrosProcessados);
            return ['data' => $resultadosUnicos, 'debug' => $debugInfo];
        }
        
        return $resultadosUnicos;
        
    } catch (Exception $e) {
        if ($debug) {
            return ['data' => [], 'debug' => ['erro' => $e->getMessage()]];
        }
        return [];
    }
}

/**
 * Função auxiliar para calcular total de indicações
 */
function calcularTotalIndicacoes($db, $indicadorId, $indicadorNome) {
    try {
        $sql = "SELECT COUNT(DISTINCT associado_id) as total 
                FROM Historico_Indicacoes 
                WHERE indicador_id = :id OR indicador_nome = :nome";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $indicadorId, ':nome' => $indicadorNome]);
        $result = $stmt->fetch();
        
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Função auxiliar para calcular indicados ativos
 */
function calcularIndicadosAtivos($db, $indicadorId, $indicadorNome) {
    try {
        $sql = "SELECT COUNT(DISTINCT hi.associado_id) as total 
                FROM Historico_Indicacoes hi
                INNER JOIN Associados a ON hi.associado_id = a.id
                WHERE (hi.indicador_id = :id OR hi.indicador_nome = :nome)
                AND UPPER(a.situacao) IN ('FILIADO', 'ATIVO', 'ATIVA', 'ASSOCIADO')";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $indicadorId, ':nome' => $indicadorNome]);
        $result = $stmt->fetch();
        
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}
?>