<?php
/**
 * API Relatórios Comerciais - VERSÃO SIMPLES E FUNCIONAL
 * Baseada na query que funcionou no teste
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Permissoes.php';
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }
    
    if (!Permissoes::tem('COMERCIAL_RELATORIOS')) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Parâmetros
    $tipo = $_GET['tipo'] ?? 'desfiliacoes';
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
    $corporacao = $_GET['corporacao'] ?? '';
    $patente = $_GET['patente'] ?? '';
    $busca = $_GET['busca'] ?? '';
    
    error_log("=== RELATÓRIO $tipo ===");
    error_log("Data: $dataInicio até $dataFim");
    
    $resultado = [];
    
    switch ($tipo) {
        case 'desfiliacoes':
            $resultado = relatorioDesfiliacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca);
            break;
        case 'novos_cadastros':
            $resultado = relatorioNovosCadastros($db, $dataInicio, $dataFim, $corporacao, $patente, $busca);
            break;
        case 'aniversariantes':
            $resultado = relatorioAniversariantes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca);
            break;
        case 'indicacoes':
            $resultado = relatorioIndicacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca);
            break;
        default:
            throw new Exception('Tipo inválido');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $resultado,
        'total' => count($resultado)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("ERRO: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// ============================================
// DESFILIAÇÕES - QUERY SIMPLES COM AGREGADOS
// ============================================
function relatorioDesfiliacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca) {
    // ✅ PARTE 1: ASSOCIADOS/MILITARES
    $sql = "
        SELECT 
            a.id,
            a.nome,
            COALESCE(a.rg, '') as rg,
            COALESCE(a.cpf, '') as cpf,
            COALESCE(a.telefone, '') as telefone,
            COALESCE(a.email, '') as email,
            COALESCE(m.patente, '') as patente,
            COALESCE(m.corporacao, '') as corporacao,
            COALESCE(m.lotacao, '') as lotacao,
            COALESCE(
                DATE(a.data_desfiliacao),
                DATE(c.dataDesfiliacao),
                NULL
            ) as data_desfiliacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE (
            UPPER(a.situacao) LIKE '%DESFIL%'
            OR UPPER(a.situacao) LIKE '%DESLIG%'
            OR UPPER(a.situacao) LIKE '%INATIV%'
        )
        AND (
            (a.data_desfiliacao IS NOT NULL 
             AND DATE(a.data_desfiliacao) BETWEEN ? AND ?)
            OR (c.dataDesfiliacao IS NOT NULL 
                AND DATE(c.dataDesfiliacao) BETWEEN ? AND ?)
        )
    ";
    
    $params = [$dataInicio, $dataFim . ' 23:59:59', $dataInicio, $dataFim . ' 23:59:59'];
    
    if ($corporacao) {
        $sql .= " AND m.corporacao = ?";
        $params[] = $corporacao;
    }
    
    if ($patente) {
        $sql .= " AND m.patente = ?";
        $params[] = $patente;
    }
    
    if ($busca) {
        $sql .= " AND (a.nome LIKE ? OR a.cpf LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }
    
    $sql .= " ORDER BY data_desfiliacao DESC LIMIT 2000";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($data as &$row) {
        if (empty($row['data_desfiliacao'])) {
            $row['data_desfiliacao'] = 'N/D';
        }
    }
    
    error_log("Desfiliações: " . count($data) . " registros (só militares/associados)");
    return $data;
}

// ============================================
// NOVOS CADASTROS - COM AGREGADOS
// ============================================
function relatorioNovosCadastros($db, $dataInicio, $dataFim, $corporacao, $patente, $busca) {
    // ✅ PARTE 1: ASSOCIADOS/MILITARES
    $sql = "
        SELECT 
            a.id,
            a.nome,
            COALESCE(a.rg, '') as rg,
            COALESCE(a.cpf, '') as cpf,
            COALESCE(a.telefone, '') as telefone,
            COALESCE(a.email, '') as email,
            COALESCE(m.patente, '') as patente,
            COALESCE(m.corporacao, '') as corporacao,
            COALESCE(a.indicacao, '') as indicado_por,
            COALESCE(
                DATE(a.data_aprovacao),
                DATE(a.data_pre_cadastro),
                DATE(c.dataFiliacao)
            ) as data_cadastro,
            CASE WHEN a.pre_cadastro = 1 THEN 'Pré-cadastro' ELSE 'Definitivo' END as tipo_cadastro
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE (
            UPPER(a.situacao) LIKE '%ATIV%'
            OR UPPER(a.situacao) LIKE '%FILIAD%'
        )
        AND (
            DATE(a.data_aprovacao) BETWEEN ? AND ?
            OR DATE(a.data_pre_cadastro) BETWEEN ? AND ?
            OR DATE(c.dataFiliacao) BETWEEN ? AND ?
        )
    ";
    
    $params = [$dataInicio, $dataFim, $dataInicio, $dataFim, $dataInicio, $dataFim];
    
    if ($corporacao) {
        $sql .= " AND m.corporacao = ?";
        $params[] = $corporacao;
    }
    
    if ($patente) {
        $sql .= " AND m.patente = ?";
        $params[] = $patente;
    }
    
    if ($busca) {
        $sql .= " AND (a.nome LIKE ? OR a.cpf LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }
    
    // ✅ PARTE 2: AGREGADOS (UNION ALL)
    // Nota: Agregados SÓ aparecem quando NÃO há filtro de corporação/patente
    if (empty($corporacao) && empty($patente)) {
        $sql .= "
            
            UNION ALL
            
            SELECT 
                ag.id,
                ag.nome,
                '' as rg,
                COALESCE(ag.cpf, '') as cpf,
                COALESCE(ag.telefone, ag.celular, '') as telefone,
                COALESCE(ag.email, '') as email,
                '' as patente,
                '' as corporacao,
                CONCAT('Agregado de ', COALESCE(ag.socio_titular_nome, 'Titular')) as indicado_por,
                COALESCE(DATE(ag.data_filiacao), DATE(ag.data_criacao)) as data_cadastro,
                'Agregado' as tipo_cadastro
            FROM Socios_Agregados ag
            WHERE (
                DATE(ag.data_filiacao) BETWEEN ? AND ?
                OR DATE(ag.data_criacao) BETWEEN ? AND ?
            )
        ";
        
        // Adicionar parâmetros para agregados
        $params[] = $dataInicio;
        $params[] = $dataFim;
        $params[] = $dataInicio;
        $params[] = $dataFim;
        
        if ($busca) {
            $sql .= " AND (ag.nome LIKE ? OR ag.cpf LIKE ?)";
            $params[] = "%$busca%";
            $params[] = "%$busca%";
        }
    }
    
    $sql .= " ORDER BY data_cadastro DESC LIMIT 2000";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Novos Cadastros: " . count($data) . " registros (com agregados)");
    return $data;
}

// ============================================
// ANIVERSARIANTES
// ============================================
function relatorioAniversariantes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca) {
    $mesInicio = (int)date('m', strtotime($dataInicio));
    $mesFim = (int)date('m', strtotime($dataFim));
    $diaInicio = (int)date('d', strtotime($dataInicio));
    $diaFim = (int)date('d', strtotime($dataFim));
    
    $sql = "
        SELECT 
            a.id,
            a.nome,
            DATE(a.nasc) as data_nascimento,
            COALESCE(a.telefone, '') as telefone,
            COALESCE(a.email, '') as email,
            COALESCE(m.patente, '') as patente,
            COALESCE(m.corporacao, '') as corporacao,
            YEAR(CURDATE()) - YEAR(a.nasc) as idade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE (
            UPPER(a.situacao) LIKE '%ATIV%'
            OR UPPER(a.situacao) LIKE '%FILIAD%'
        )
        AND a.nasc IS NOT NULL
        AND YEAR(a.nasc) > 1900
    ";
    
    $params = [];
    
    // ✅ Se é o mesmo mês E mesmo dia (filtro específico de 1 dia)
    if ($mesInicio == $mesFim && $diaInicio == $diaFim) {
        $sql .= " AND MONTH(a.nasc) = ? AND DAY(a.nasc) = ?";
        $params[] = $mesInicio;
        $params[] = $diaInicio;
    }
    // Se é o mesmo mês mas dias diferentes (range dentro do mês)
    else if ($mesInicio == $mesFim) {
        $sql .= " AND MONTH(a.nasc) = ? AND DAY(a.nasc) BETWEEN ? AND ?";
        $params[] = $mesInicio;
        $params[] = $diaInicio;
        $params[] = $diaFim;
    }
    // Se são meses diferentes
    else if ($mesInicio < $mesFim) {
        $sql .= " AND MONTH(a.nasc) BETWEEN ? AND ?";
        $params[] = $mesInicio;
        $params[] = $mesFim;
    }
    else {
        $sql .= " AND (MONTH(a.nasc) >= ? OR MONTH(a.nasc) <= ?)";
        $params[] = $mesInicio;
        $params[] = $mesFim;
    }
    
    if ($corporacao) {
        $sql .= " AND m.corporacao = ?";
        $params[] = $corporacao;
    }
    
    if ($patente) {
        $sql .= " AND m.patente = ?";
        $params[] = $patente;
    }
    
    if ($busca) {
        $sql .= " AND a.nome LIKE ?";
        $params[] = "%$busca%";
    }
    
    $sql .= " ORDER BY MONTH(a.nasc), DAY(a.nasc) LIMIT 2000";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Aniversariantes: " . count($data) . " registros (Dia $diaInicio/$mesInicio até $diaFim/$mesFim)");
    return $data;
}

// ============================================
// INDICAÇÕES
// ============================================
function relatorioIndicacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca) {
    $sql = "
        SELECT 
            hi.id as registro_id,
            DATE(hi.data_indicacao) as data_indicacao,
            COALESCE(i.nome_completo, hi.indicador_nome, 'N/D') as indicador_nome,
            COALESCE(i.patente, '') as indicador_patente,
            COALESCE(i.corporacao, '') as indicador_corporacao,
            a.id as associado_id,
            a.nome as associado_nome,
            COALESCE(a.cpf, '') as associado_cpf,
            COALESCE(a.telefone, '') as associado_telefone,
            COALESCE(m.patente, '') as associado_patente,
            COALESCE(m.corporacao, '') as associado_corporacao,
            a.situacao as associado_situacao
        FROM Historico_Indicacoes hi
        INNER JOIN Associados a ON hi.associado_id = a.id
        LEFT JOIN Indicadores i ON hi.indicador_id = i.id
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE DATE(hi.data_indicacao) BETWEEN ? AND ?
    ";
    
    $params = [$dataInicio, $dataFim];
    
    if ($corporacao) {
        $sql .= " AND (i.corporacao = ? OR m.corporacao = ?)";
        $params[] = $corporacao;
        $params[] = $corporacao;
    }
    
    if ($patente) {
        $sql .= " AND (i.patente = ? OR m.patente = ?)";
        $params[] = $patente;
        $params[] = $patente;
    }
    
    if ($busca) {
        $sql .= " AND (i.nome_completo LIKE ? OR a.nome LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }
    
    $sql .= " ORDER BY hi.data_indicacao DESC LIMIT 2000";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Indicações: " . count($data) . " registros");
    return $data;
}
?>