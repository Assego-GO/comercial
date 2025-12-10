<?php
/**
 * API para buscar inadimplentes - Sistema ASSEGO
 * VERSÃO PDO - 100% FUNCIONAL
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Usuário não autenticado',
            'data' => []
        ], 401);
    }

    $usuarioLogado = $auth->getUser();
    $deptId = $usuarioLogado['departamento_id'] ?? 0;
    
    if ($deptId != 2 && $deptId != 1) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Acesso negado',
            'data' => []
        ], 403);
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Parâmetros de filtro
    $filtroNome = trim($_GET['nome'] ?? '');
    $filtroRG = trim($_GET['rg'] ?? '');
    $filtroVinculo = trim($_GET['vinculo'] ?? '');
    $limite = intval($_GET['limite'] ?? 100);
    $offset = intval($_GET['offset'] ?? 0);

    // ========================================
    // QUERY PRINCIPAL
    // ========================================
    
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.rg,
            a.cpf,
            a.situacao,
            a.telefone,
            a.nasc,
            a.email,
            f.vinculoServidor,
            f.tipoAssociado,
            f.situacaoFinanceira,
            COUNT(p.id) as total_dividas,
            SUM(p.valor_pago) as valor_total_debito,
            MIN(p.mes_referencia) as divida_mais_antiga,
            MAX(p.mes_referencia) as divida_mais_recente
        FROM Associados a
        INNER JOIN Pagamentos_Associado p ON a.id = p.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        WHERE p.status_pagamento = 'PENDENTE'
        AND a.situacao = 'Filiado'
    ";
    
    $params = [];
    
    if (!empty($filtroNome)) {
        $sql .= " AND a.nome LIKE :nome";
        $params[':nome'] = '%' . $filtroNome . '%';
    }
    
    if (!empty($filtroRG)) {
        $sql .= " AND a.rg LIKE :rg";
        $params[':rg'] = '%' . $filtroRG . '%';
    }
    
    if (!empty($filtroVinculo)) {
        $sql .= " AND f.vinculoServidor = :vinculo";
        $params[':vinculo'] = $filtroVinculo;
    }
    
    // GROUP BY com todos os campos não agregados (ONLY_FULL_GROUP_BY)
    $sql .= " GROUP BY 
                a.id, a.nome, a.rg, a.cpf, a.situacao, 
                a.telefone, a.nasc, a.email,
                f.vinculoServidor, f.tipoAssociado, f.situacaoFinanceira";
    
    $sql .= " HAVING total_dividas > 0";
    $sql .= " ORDER BY valor_total_debito DESC, divida_mais_antiga ASC";
    $sql .= " LIMIT :limite OFFSET :offset";

    try {
        $stmt = $db->prepare($sql);
        
        // Bind dos parâmetros de filtro (string)
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        // Bind dos parâmetros de paginação (integer)
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $inadimplentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erro SQL: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Erro ao buscar inadimplentes',
            'debug' => $e->getMessage(),
            'data' => []
        ], 500);
    }

    // ========================================
    // CONTAR TOTAL
    // ========================================
    
    $sqlCount = "
        SELECT COUNT(DISTINCT a.id) as total
        FROM Associados a
        INNER JOIN Pagamentos_Associado p ON a.id = p.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        WHERE p.status_pagamento = 'PENDENTE'
        AND a.situacao = 'Filiado'
    ";
    
    $paramsCount = [];
    
    if (!empty($filtroNome)) {
        $sqlCount .= " AND a.nome LIKE :nome";
        $paramsCount[':nome'] = '%' . $filtroNome . '%';
    }
    
    if (!empty($filtroRG)) {
        $sqlCount .= " AND a.rg LIKE :rg";
        $paramsCount[':rg'] = '%' . $filtroRG . '%';
    }
    
    if (!empty($filtroVinculo)) {
        $sqlCount .= " AND f.vinculoServidor = :vinculo";
        $paramsCount[':vinculo'] = $filtroVinculo;
    }
    
    try {
        $stmtCount = $db->prepare($sqlCount);
        
        foreach ($paramsCount as $key => $value) {
            $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmtCount->execute();
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        error_log("Erro ao contar: " . $e->getMessage());
        $totalRegistros = 0;
    }

    // ========================================
    // ESTATÍSTICAS POR VÍNCULO
    // ========================================
    
    $estatisticas = ['por_vinculo' => []];
    
    try {
        $sqlVinculo = "
            SELECT 
                COALESCE(f.vinculoServidor, 'Não informado') as vinculoServidor, 
                COUNT(DISTINCT a.id) as total
            FROM Associados a
            INNER JOIN Pagamentos_Associado p ON a.id = p.associado_id
            LEFT JOIN Financeiro f ON a.id = f.associado_id
            WHERE p.status_pagamento = 'PENDENTE'
            AND a.situacao = 'Filiado'
            GROUP BY f.vinculoServidor
            ORDER BY total DESC
        ";
        
        $stmtVinculo = $db->query($sqlVinculo);
        $estatisticas['por_vinculo'] = $stmtVinculo->fetchAll(PDO::FETCH_ASSOC);
        
        if ($totalRegistros > 0) {
            foreach ($estatisticas['por_vinculo'] as &$vinculo) {
                $vinculo['percentual'] = round(($vinculo['total'] * 100.0 / $totalRegistros), 2);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro estatísticas: " . $e->getMessage());
    }
    
    // ========================================
    // PROCESSAR DADOS
    // ========================================
    
    foreach ($inadimplentes as &$inadimplente) {
        // Calcular idade
        $inadimplente['idade'] = null;
        if (!empty($inadimplente['nasc']) && $inadimplente['nasc'] != '0000-00-00') {
            try {
                $nascimento = new DateTime($inadimplente['nasc']);
                $hoje = new DateTime();
                $inadimplente['idade'] = $nascimento->diff($hoje)->y;
            } catch (Exception $e) {
                // Ignorar
            }
        }
        
        // Calcular meses de atraso
        $inadimplente['meses_atraso'] = 0;
        if (!empty($inadimplente['divida_mais_antiga'])) {
            try {
                $dataAntiga = new DateTime($inadimplente['divida_mais_antiga']);
                $hoje = new DateTime();
                $diff = $dataAntiga->diff($hoje);
                $inadimplente['meses_atraso'] = ($diff->y * 12) + $diff->m;
                
                if ($inadimplente['meses_atraso'] == 0) {
                    $inadimplente['meses_atraso'] = 1;
                }
            } catch (Exception $e) {
                $inadimplente['meses_atraso'] = (int)$inadimplente['total_dividas'];
            }
        }
        
        // Conversões de tipo
        $inadimplente['total_dividas'] = (int)$inadimplente['total_dividas'];
        $inadimplente['valor_total_debito'] = round((float)$inadimplente['valor_total_debito'], 2);
        
        // Flags
        $inadimplente['tem_telefone'] = !empty($inadimplente['telefone']);
        $inadimplente['tem_email'] = !empty($inadimplente['email']);
        
        // Valores padrão
        $inadimplente['telefone'] = $inadimplente['telefone'] ?? '';
        $inadimplente['email'] = $inadimplente['email'] ?? '';
        $inadimplente['vinculoServidor'] = $inadimplente['vinculoServidor'] ?? 'Não informado';
        $inadimplente['tipoAssociado'] = $inadimplente['tipoAssociado'] ?? 'Contribuinte';
        $inadimplente['situacaoFinanceira'] = 'INADIMPLENTE';
    }
    unset($inadimplente);

    // ========================================
    // RESPOSTA FINAL
    // ========================================
    
    sendJsonResponse([
        'status' => 'success',
        'message' => 'Inadimplentes carregados com sucesso',
        'data' => $inadimplentes,
        'meta' => [
            'total_registros' => (int)$totalRegistros,
            'registros_retornados' => count($inadimplentes),
            'limite' => $limite,
            'offset' => $offset,
            'tem_mais_registros' => ($offset + $limite) < $totalRegistros,
            'fonte_dados' => 'Pagamentos_Associado'
        ],
        'estatisticas' => $estatisticas,
        'filtros_aplicados' => [
            'nome' => $filtroNome,
            'rg' => $filtroRG,
            'vinculo' => $filtroVinculo
        ]
    ]);

} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Erro ao processar requisição',
        'debug' => $e->getMessage(),
        'data' => []
    ], 500);
}