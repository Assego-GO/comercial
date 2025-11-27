<?php
/**
 * API para listar sócios agregados
 * api/documentos/documentos_agregados_listar.php
 * 
 * Lista diretamente da tabela Socios_Agregados
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Filtros
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $periodo = isset($_GET['periodo']) ? trim($_GET['periodo']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Query diretamente de Socios_Agregados
    $sql = "SELECT 
                sa.id,
                sa.id AS agregado_id,
                sa.nome AS agregado_nome,
                sa.cpf AS agregado_cpf,
                sa.email AS agregado_email,
                sa.telefone AS agregado_telefone,
                sa.celular AS agregado_celular,
                sa.data_nascimento,
                sa.estado_civil,
                sa.data_filiacao,
                sa.situacao,
                sa.ativo,
                sa.valor_contribuicao,
                sa.observacoes,
                sa.data_criacao,
                sa.data_atualizacao,
                
                -- Dados do titular
                sa.socio_titular_nome AS titular_nome,
                sa.socio_titular_cpf AS titular_cpf,
                sa.socio_titular_email AS titular_email,
                sa.socio_titular_fone AS titular_telefone,
                
                -- Endereço
                sa.cep,
                sa.endereco,
                sa.numero,
                sa.bairro,
                sa.cidade,
                sa.estado,
                
                -- Dados bancários
                sa.banco,
                sa.agencia,
                sa.conta_corrente,
                
                -- Campos para compatibilidade com o frontend
                sa.data_criacao AS data_upload,
                CASE 
                    WHEN sa.situacao IN ('pendente', 'aguardando') THEN 'AGUARDANDO_ASSINATURA'
                    WHEN sa.situacao = 'assinado' THEN 'ASSINADO'
                    WHEN sa.situacao = 'ativo' THEN 'FINALIZADO'
                    WHEN sa.situacao = 'inativo' THEN 'INATIVO'
                    ELSE 'AGUARDANDO_ASSINATURA'
                END AS status_fluxo,
                CASE 
                    WHEN sa.situacao IN ('pendente', 'aguardando') THEN 'Aguardando Aprovação'
                    WHEN sa.situacao = 'assinado' THEN 'Assinado'
                    WHEN sa.situacao = 'ativo' THEN 'Ativo'
                    WHEN sa.situacao = 'inativo' THEN 'Inativo'
                    ELSE sa.situacao
                END AS status_descricao,
                DATEDIFF(NOW(), sa.data_criacao) AS dias_em_processo,
                'Agregado' AS parentesco
                
            FROM Socios_Agregados sa
            WHERE 1=1";

    $params = [];

    // Filtro por status/situacao
    if (!empty($status)) {
        switch ($status) {
            case 'AGUARDANDO_ASSINATURA':
            case 'DIGITALIZADO':
                $sql .= " AND sa.situacao IN ('pendente', 'aguardando')";
                break;
            case 'ASSINADO':
                $sql .= " AND sa.situacao = 'assinado'";
                break;
            case 'FINALIZADO':
                $sql .= " AND sa.situacao = 'ativo'";
                break;
            case 'INATIVO':
                $sql .= " AND sa.situacao = 'inativo'";
                break;
            default:
                // Buscar pelo valor direto
                $sql .= " AND sa.situacao = :situacao";
                $params[':situacao'] = strtolower($status);
        }
    }

    // Filtro por busca
    if (!empty($busca)) {
        $sql .= " AND (
            sa.nome LIKE :busca 
            OR sa.cpf LIKE :busca_cpf 
            OR sa.socio_titular_nome LIKE :busca_titular 
            OR sa.socio_titular_cpf LIKE :busca_titular_cpf
            OR sa.email LIKE :busca_email
        )";
        $buscaLimpa = preg_replace('/\D/', '', $busca);
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca_cpf'] = '%' . $buscaLimpa . '%';
        $params[':busca_titular'] = '%' . $busca . '%';
        $params[':busca_titular_cpf'] = '%' . $buscaLimpa . '%';
        $params[':busca_email'] = '%' . $busca . '%';
    }

    // Filtro por período
    if (!empty($periodo)) {
        switch ($periodo) {
            case 'hoje':
                $sql .= " AND DATE(sa.data_criacao) = CURDATE()";
                break;
            case 'semana':
                $sql .= " AND sa.data_criacao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $sql .= " AND sa.data_criacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }

    $sql .= " ORDER BY sa.data_criacao DESC";
    $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $agregados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total geral
    $sqlCount = "SELECT COUNT(*) as total FROM Socios_Agregados";
    $stmtCount = $db->query($sqlCount);
    $totalRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow['total'] ?? 0;

    // Estatísticas por situacao
    $sqlStats = "SELECT 
                    situacao,
                    COUNT(*) as total 
                 FROM Socios_Agregados 
                 GROUP BY situacao";
    $stmtStats = $db->query($sqlStats);
    $estatisticas = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

    // Contar pendentes para o badge
    $sqlPendentes = "SELECT COUNT(*) as total FROM Socios_Agregados WHERE situacao IN ('pendente', 'aguardando')";
    $stmtPendentes = $db->query($sqlPendentes);
    $pendentesRow = $stmtPendentes->fetch(PDO::FETCH_ASSOC);
    $totalPendentes = $pendentesRow['total'] ?? 0;

    $response = [
        'status' => 'success',
        'data' => $agregados,
        'total' => count($agregados),
        'total_geral' => intval($total),
        'total_pendentes' => intval($totalPendentes),
        'estatisticas' => [
            'por_status' => $estatisticas
        ]
    ];

} catch (PDOException $e) {
    error_log("Erro PDO em documentos_agregados_listar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ];
    http_response_code(500);
} catch (Exception $e) {
    error_log("Erro em documentos_agregados_listar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;