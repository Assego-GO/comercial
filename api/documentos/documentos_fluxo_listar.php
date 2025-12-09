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
    $tipoDocumento = isset($_GET['tipo_documento']) ? trim($_GET['tipo_documento']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Se filtro é específico para DESFILIACAO, buscar só da tabela Documentos_Associado
    if ($tipoDocumento === 'DESFILIACAO') {
        // Redirecionar para a API de desfiliação
        include 'documentos_desfiliacao_listar.php';
        exit;
    }

    // Query adaptada à estrutura REAL do banco
    // UNION entre DocumentosFluxo (filiações) e Documentos_Associado (desfiliações)
    
    $sqlBase = "SELECT 
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
                
                -- Campos de Departamentos
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
                
                -- Identificador de origem
                'FILIACAO' AS origem_tabela,
                NULL AS aprovacoes_json
                
            FROM DocumentosFluxo df
            LEFT JOIN Associados a ON df.associado_id = a.id
            LEFT JOIN Departamentos d ON df.departamento_atual = d.id
            WHERE 1=1";
    
    // Se não filtrou especificamente para FILIACAO, adicionar desfiliações via UNION
    if ($tipoDocumento !== 'FILIACAO') {
        $sqlBase .= "
            UNION ALL
            
            SELECT 
                da.id,
                da.associado_id,
                da.tipo_documento,
                'Ficha de Desfiliação' AS tipo_descricao,
                SUBSTRING_INDEX(da.caminho_arquivo, '/', -1) AS nome_arquivo,
                da.caminho_arquivo AS arquivo_path,
                0 AS tamanho_arquivo,
                'application/pdf' AS tipo_mime,
                da.status_fluxo,
                da.data_upload,
                da.funcionario_id AS funcionario_upload,
                NULL AS departamento_atual,
                NULL AS observacoes,
                da.data_atualizacao AS data_ultima_acao,
                NULL AS funcionario_ultima_acao,
                
                -- Campos de Associados
                a.nome AS associado_nome,
                a.cpf AS associado_cpf,
                a.email AS associado_email,
                
                -- Campos de Departamentos
                NULL AS departamento_atual_nome,
                
                -- Campos calculados
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM Aprovacoes_Desfiliacao ad 
                        WHERE ad.documento_id = da.id 
                        AND ad.status_aprovacao = 'REJEITADO'
                    ) THEN 'Rejeitado'
                    WHEN da.status_fluxo = 'FINALIZADO' THEN 'Finalizado'
                    ELSE 'Em Aprovação'
                END AS status_descricao,
                DATEDIFF(NOW(), da.data_upload) AS dias_em_processo,
                
                -- Identificador de origem
                'DESFILIACAO' AS origem_tabela,
                (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'departamento_id', ad2.departamento_id,
                            'departamento_nome', ad2.departamento_nome,
                            'status_aprovacao', ad2.status_aprovacao,
                            'ordem_aprovacao', ad2.ordem_aprovacao
                        )
                    )
                    FROM Aprovacoes_Desfiliacao ad2 
                    WHERE ad2.documento_id = da.id
                ) AS aprovacoes_json
                
            FROM Documentos_Associado da
            LEFT JOIN Associados a ON da.associado_id = a.id
            WHERE da.tipo_documento = 'ficha_desfiliacao'
            AND da.deletado = 0";
    }
    
    // Envolver UNION em subquery para permitir filtros
    $sql = "SELECT * FROM (" . $sqlBase . ") AS todos_documentos WHERE 1=1";

    $params = [];

    // Filtro por status
    if (!empty($status)) {
        $sql .= " AND status_fluxo = :status";
        $params[':status'] = $status;
    }

    // Filtro por busca (nome ou CPF)
    if (!empty($busca)) {
        $sql .= " AND (associado_nome LIKE :busca OR associado_cpf LIKE :busca_cpf)";
        $params[':busca'] = '%' . $busca . '%';
        $params[':busca_cpf'] = '%' . preg_replace('/\D/', '', $busca) . '%';
    }

    // Filtro por período
    if (!empty($periodo)) {
        switch ($periodo) {
            case 'hoje':
                $sql .= " AND DATE(data_upload) = CURDATE()";
                break;
            case 'semana':
                $sql .= " AND data_upload >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $sql .= " AND data_upload >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }

    // Ordenação e paginação
    $sql .= " ORDER BY data_upload DESC";
    $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);

    // Executar query
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total (incluindo desfiliações)
    $sqlCountBase = "SELECT COUNT(*) as total FROM DocumentosFluxo";
    if ($tipoDocumento !== 'FILIACAO') {
        $sqlCountBase .= " UNION ALL SELECT COUNT(*) as total FROM Documentos_Associado WHERE tipo_documento = 'ficha_desfiliacao' AND deletado = 0";
    }
    $sqlCount = "SELECT SUM(total) as total FROM (" . $sqlCountBase . ") AS counts";
    $stmtCount = $db->query($sqlCount);
    $totalRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow['total'] ?? 0;

    // Estatísticas por status (só filiações por enquanto)
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