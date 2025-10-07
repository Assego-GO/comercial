<?php
/**
 * NEW FILE: api/buscar_associados.php
 * Search across ALL records in database
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

@session_start();

if (!isset($_SESSION['funcionario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Normalize corporacao function (same as your existing)
function normalizarCorporacao($corporacao) {
    if (empty($corporacao)) return '';
    
    $corporacao = trim($corporacao);
    $corporacaoUpper = strtoupper($corporacao);
    
    $mapeamento = [
        'PM' => 'Polícia Militar', 'P.M.' => 'Polícia Militar', 
        'PMGO' => 'Polícia Militar', 'PM-GO' => 'Polícia Militar',
        'BM' => 'Bombeiro Militar', 'B.M.' => 'Bombeiro Militar',
        'BMGO' => 'Bombeiro Militar', 'CBM' => 'Bombeiro Militar',
        'PC' => 'Polícia Civil', 'PCGO' => 'Polícia Civil',
        'PP' => 'Polícia Penal', 'PPGO' => 'Polícia Penal'
    ];

    if (isset($mapeamento[$corporacaoUpper])) {
        return $mapeamento[$corporacaoUpper];
    }

    foreach ($mapeamento as $chave => $valor) {
        if (stripos($corporacaoUpper, $chave) !== false) {
            return $valor;
        }
    }

    return $corporacao;
}

try {
    @include_once '../config/database.php';

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_CADASTRO . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $termo = $_GET['termo'] ?? '';
    $limit = min(500, max(10, intval($_GET['limit'] ?? 200)));
    
    if (strlen($termo) < 2) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Termo deve ter pelo menos 2 caracteres'
        ]);
        exit;
    }

    // OPTIMIZED SEARCH with priorities
    $termoLike = '%' . $termo . '%';
    $termoExato = $termo;
    
    $sql = "
    (
        -- Exact CPF match (priority 1)
        SELECT DISTINCT
            a.id, a.nome, a.cpf, a.rg, a.telefone, a.foto,
            COALESCE(a.situacao, 'Desfiliado') as situacao,
            m.corporacao, m.patente, c.dataFiliacao as data_filiacao,
            1 as prioridade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.pre_cadastro = 0 AND a.cpf = :termo_exato
        LIMIT 50
    )
    UNION
    (
        -- Exact RG match (priority 2) 
        SELECT DISTINCT
            a.id, a.nome, a.cpf, a.rg, a.telefone, a.foto,
            COALESCE(a.situacao, 'Desfiliado') as situacao,
            m.corporacao, m.patente, c.dataFiliacao as data_filiacao,
            2 as prioridade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.pre_cadastro = 0 AND a.rg = :termo_exato 
        AND a.cpf != :termo_exato
        LIMIT 50
    )
    UNION
    (
        -- Phone search (priority 3)
        SELECT DISTINCT
            a.id, a.nome, a.cpf, a.rg, a.telefone, a.foto,
            COALESCE(a.situacao, 'Desfiliado') as situacao,
            m.corporacao, m.patente, c.dataFiliacao as data_filiacao,
            3 as prioridade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.pre_cadastro = 0 AND a.telefone LIKE :termo_like
        AND a.cpf != :termo_exato AND a.rg != :termo_exato
        LIMIT 100
    )
    UNION
    (
        -- Name search (priority 4)
        SELECT DISTINCT
            a.id, a.nome, a.cpf, a.rg, a.telefone, a.foto,
            COALESCE(a.situacao, 'Desfiliado') as situacao,
            m.corporacao, m.patente, c.dataFiliacao as data_filiacao,
            4 as prioridade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.pre_cadastro = 0 AND a.nome LIKE :termo_like
        AND a.cpf != :termo_exato AND a.rg != :termo_exato
        AND (a.telefone NOT LIKE :termo_like OR a.telefone IS NULL)
        LIMIT 300
    )
    ORDER BY prioridade ASC, nome ASC
    LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':termo_exato', $termoExato, PDO::PARAM_STR);
    $stmt->bindValue(':termo_like', $termoLike, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultados = $stmt->fetchAll();

    // Process results
    $dados = [];
    foreach ($resultados as $row) {
        $dados[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome'] ?? '',
            'cpf' => $row['cpf'] ?? '',
            'rg' => $row['rg'] ?? '',
            'telefone' => $row['telefone'] ?? '',
            'situacao' => $row['situacao'],
            'corporacao' => normalizarCorporacao($row['corporacao']),
            'patente' => $row['patente'] ?? '',
            'data_filiacao' => $row['data_filiacao'] ?? '',
            'foto' => $row['foto'] ?? '',
            'detalhes_carregados' => false
        ];
    }

    // Count approximate total
    $sqlCount = "
    SELECT COUNT(DISTINCT a.id) as total 
    FROM Associados a
    WHERE a.pre_cadastro = 0 AND (
        a.cpf = :termo_exato OR
        a.rg = :termo_exato OR  
        a.telefone LIKE :termo_like OR
        a.nome LIKE :termo_like
    )
    LIMIT 2000
    ";
    
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->bindValue(':termo_exato', $termoExato, PDO::PARAM_STR);
    $stmtCount->bindValue(':termo_like', $termoLike, PDO::PARAM_STR);
    $stmtCount->execute();
    $totalAproximado = $stmtCount->fetch()['total'];

    echo json_encode([
        'status' => 'success',
        'dados' => $dados,
        'total' => count($dados),
        'total_aproximado' => intval($totalAproximado),
        'termo_busca' => $termo,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro na busca',
        'dados' => []
    ]);
}
?>