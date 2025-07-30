<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function jsonResponse($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Método não permitido. Use POST.');
    }

    // Ler dados JSON
    $input = file_get_contents('php://input');
    $filtros = json_decode($input, true);

    if (!$filtros) {
        $filtros = $_POST;
    }

    if (empty($filtros)) {
        jsonResponse('error', 'Nenhum filtro recebido');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Construir query base
    $sql = "SELECT COUNT(*) as total, COALESCE(SUM(p.valor), 0) as valor_total
            FROM Associados a
            INNER JOIN Peculio p ON a.id = p.associado_id
            WHERE 1=1";
    
    $params = [];

    // Aplicar filtros por tipo
    // NOTA: Usando YEAR() e COALESCE para evitar erro com '0000-00-00' no MySQL modo estrito
    $tipo = $filtros['tipo'] ?? 'todos';
    
    switch ($tipo) {
        case 'recebidos':
            // Tem data de recebimento válida (ano > 0)
            $sql .= " AND p.data_recebimento IS NOT NULL 
                      AND YEAR(p.data_recebimento) > 0";
            break;
            
        case 'pendentes':
            // Não tem data de recebimento válida
            $sql .= " AND (p.data_recebimento IS NULL 
                      OR YEAR(p.data_recebimento) = 0)";
            break;
            
        case 'sem_data':
            // Não tem data prevista definida
            $sql .= " AND (p.data_prevista IS NULL 
                      OR YEAR(p.data_prevista) = 0)";
            break;
            
        case 'vencidos':
            // Tem data prevista válida no passado e ainda não recebeu
            $sql .= " AND p.data_prevista IS NOT NULL 
                      AND YEAR(p.data_prevista) > 0
                      AND p.data_prevista < CURDATE()
                      AND (p.data_recebimento IS NULL 
                           OR YEAR(p.data_recebimento) = 0)";
            break;
            
        case 'proximos':
            // Data prevista nos próximos 30 dias e ainda não recebeu
            $sql .= " AND p.data_prevista IS NOT NULL 
                      AND YEAR(p.data_prevista) > 0
                      AND p.data_prevista >= CURDATE()
                      AND p.data_prevista <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                      AND (p.data_recebimento IS NULL 
                           OR YEAR(p.data_recebimento) = 0)";
            break;
            
        case 'todos':
        default:
            // Sem filtro adicional
            break;
    }

    // Aplicar filtro de período se especificado
    if (!empty($filtros['periodo']) && is_array($filtros['periodo'])) {
        $tipoData = $filtros['periodo']['tipo_data'] ?? 'data_prevista';
        $dataInicio = $filtros['periodo']['data_inicio'] ?? null;
        $dataFim = $filtros['periodo']['data_fim'] ?? null;

        // Validar campo de data
        $campoData = ($tipoData === 'data_recebimento') ? 'p.data_recebimento' : 'p.data_prevista';

        if (!empty($dataInicio)) {
            $sql .= " AND $campoData >= ?";
            $params[] = $dataInicio;
        }
        if (!empty($dataFim)) {
            $sql .= " AND $campoData <= ?";
            $params[] = $dataFim;
        }
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse('success', 'Contagem realizada', [
        'total' => (int)($resultado['total'] ?? 0),
        'valor_total' => (float)($resultado['valor_total'] ?? 0),
        'tipo_filtro' => $tipo
    ]);

} catch (PDOException $e) {
    error_log("ERRO PDO ao contar registros: " . $e->getMessage());
    jsonResponse('error', 'Erro de banco de dados: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("ERRO ao contar registros do relatório: " . $e->getMessage());
    jsonResponse('error', 'Erro ao contar registros: ' . $e->getMessage());
}
?>