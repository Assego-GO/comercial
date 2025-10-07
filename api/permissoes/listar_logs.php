<?php
// ============================================
// api/permissoes/listar_logs.php
// ============================================

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$permissoes = Permissoes::getInstance();
if (!$permissoes->isSuperAdmin() && !$permissoes->hasPermission('SISTEMA_PERMISSOES', 'VIEW')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Parâmetros de filtro
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $resultado = isset($_GET['resultado']) ? $_GET['resultado'] : null;
    $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
    $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
    
    $where = [];
    $params = [];
    
    if ($resultado) {
        $where[] = "l.resultado = ?";
        $params[] = $resultado;
    }
    
    if ($dataInicio) {
        $where[] = "DATE(l.criado_em) >= ?";
        $params[] = $dataInicio;
    }
    
    if ($dataFim) {
        $where[] = "DATE(l.criado_em) <= ?";
        $params[] = $dataFim;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT 
                l.*,
                f.nome as funcionario_nome,
                rec.nome as recurso_nome,
                p.nome as permissao_nome
            FROM log_acessos l
            INNER JOIN Funcionarios f ON l.funcionario_id = f.id
            LEFT JOIN recursos rec ON l.recurso_id = rec.id
            LEFT JOIN permissoes p ON l.permissao_id = p.id
            $whereClause
            ORDER BY l.criado_em DESC
            LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($logs);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar logs: ' . $e->getMessage()]);
}
?>