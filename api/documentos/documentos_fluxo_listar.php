<?php
/**
 * API para listar documentos em fluxo de assinatura
 * api/documentos/documentos_fluxo_listar.php
 * 
 * Versão adaptada à estrutura real do banco wwasse_cadastro
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight
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

    // Iniciar sessão se não estiver iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Obter conexão com banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Filtros da requisição
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
    $tipoOrigem = isset($_GET['tipo_origem']) ? trim($_GET['tipo_origem']) : '';
    $origem = isset($_GET['origem']) ? trim($_GET['origem']) : '';
    $periodo = isset($_GET['periodo']) ? trim($_GET['periodo']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Query adaptada à estrutura REAL do banco
    // Tabela DocumentosFluxo tem: id, associado_id, tipo_documento, tipo_descricao, 
    // nome_arquivo, caminho_arquivo, tamanho_arquivo, tipo_mime, status_fluxo,
    // data_upload, funcionario_upload, departamento_atual, observacao, 
    // data_ultima_acao, funcionario_ultima_acao
    
    $sql = "SELECT 
                df.id,
                df.associado_id,
                df.tipo_documento,
                df.tipo_descricao,
                df.nome_arquivo,
                df.caminho_arquivo AS arquivo_path,
                df.tamanho_arquivo,
                df.tipo_mime,
                df.status_fluxo,
                df.data_upload,
                df.funcionario_upload,
                df.departamento_atual,
                df.observacao AS observacoes,
                df.data_ultima_acao,
                df.funcionario_ultima_acao,
                
                -- Campos de Associados
                a.nome AS associado_nome,
                a.cpf AS associado_cpf,
                a.email AS associado_email,
                
                -- Campos de Departamentos (sem data_atualizacao que não existe)
                d.nome AS departamento_atual_nome,
                
                -- Campos calculados
                CASE df.status_fluxo
                    WHEN 'DIGITALIZADO' THEN 'Aguardando Envio'
                    WHEN 'AGUARDANDO_ASSINATURA' THEN 'Na Presidência'
                    WHEN 'ASSINADO' THEN 'Assinado'
                    WHEN 'FINALIZADO' THEN 'Finalizado'
                    ELSE df.status_fluxo
                END AS status_descricao,
                DATEDIFF(NOW(), df.data_upload) AS dias_em_processo,
                
                -- Simular campos que não existem na tabela
                NULL AS tipo_origem,
                NULL AS data_envio_presidencia,
                NULL AS data_assinatura,
                NULL AS data_finalizacao,
                NULL AS assinado_por
                
            FROM DocumentosFluxo df
            LEFT JOIN Associados a ON df.associado_id = a.id
            LEFT JOIN Departamentos d ON df.departamento_atual = d.id
            WHERE 1=1";

    $params = [];

    // Filtro por status
    if (!empty($status)) {
        $sql .= " AND df.status_fluxo = :status";
        $params[':status'] = $status;
    }

    // Filtro por busca (nome ou CPF)
    if (!empty($busca)) {
        $sql .= " AND (a.nome LIKE :busca OR a.cpf LIKE :busca_cpf)";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca_cpf'] = '%' . preg_replace('/\D/', '', $busca) . '%';
    }

    // Filtro por período
    if (!empty($periodo)) {
        switch ($periodo) {
            case 'hoje':
                $sql .= " AND DATE(df.data_upload) = CURDATE()";
                break;
            case 'semana':
                $sql .= " AND df.data_upload >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $sql .= " AND df.data_upload >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }

    // Ordenação e paginação
    $sql .= " ORDER BY df.data_upload DESC";
    $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);

    // Executar query
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total
    $sqlCount = "SELECT COUNT(*) as total FROM DocumentosFluxo";
    $stmtCount = $db->query($sqlCount);
    $totalRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow['total'] ?? 0;

    // Estatísticas por status
    $sqlStats = "SELECT status_fluxo, COUNT(*) as total FROM DocumentosFluxo GROUP BY status_fluxo";
    $stmtStats = $db->query($sqlStats);
    $estatisticas = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'status' => 'success',
        'data' => $documentos,
        'total' => count($documentos),
        'total_geral' => intval($total),
        'estatisticas' => [
            'por_status' => $estatisticas
        ],
        'filtros_aplicados' => [
            'status' => $status,
            'busca' => $busca,
            'tipo_origem' => $tipoOrigem ?: $origem,
            'periodo' => $periodo
        ]
    ];

} catch (PDOException $e) {
    error_log("Erro PDO em documentos_fluxo_listar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ];
    http_response_code(500);
} catch (Exception $e) {
    error_log("Erro em documentos_fluxo_listar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;