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
    
    // Paginação
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $porPagina = isset($_GET['por_pagina']) ? max(1, min(100, intval($_GET['por_pagina']))) : 20;
    $offset = ($pagina - 1) * $porPagina;
    $limit = $porPagina;

    // Query APENAS da tabela Documentos_Associado
    $sqlBase = "SELECT 
                da.id,
                da.associado_id,
                da.tipo_documento,
                CASE da.tipo_documento
                    WHEN 'ficha_desfiliacao' THEN 'Ficha de Desfiliação'
                    WHEN 'cpf' THEN 'CPF'
                    WHEN 'rg' THEN 'RG'
                    WHEN 'comprovante_residencia' THEN 'Comprovante de Residência'
                    ELSE UPPER(REPLACE(da.tipo_documento, '_', ' '))
                END AS tipo_descricao,
                da.nome_arquivo,
                da.caminho_arquivo AS arquivo_path,
                0 AS tamanho_arquivo,
                'application/pdf' AS tipo_mime,
                da.status_fluxo,
                da.data_upload,
                da.funcionario_id AS funcionario_upload,
                da.departamento_atual,
                da.observacoes_fluxo AS observacoes,
                da.data_upload AS data_ultima_acao,
                da.funcionario_id AS funcionario_ultima_acao,
                
                -- Campos de Associados
                a.nome AS associado_nome,
                a.cpf AS associado_cpf,
                a.email AS associado_email,
                a.situacao,
                a.pre_cadastro,
                
                -- CORRIGIDO: Se está na tabela Militar = SOCIO, se não está = AGREGADO
                CASE 
                    WHEN m.id IS NOT NULL THEN 'SOCIO'
                    ELSE 'AGREGADO'
                END AS tipo_pessoa,
                
                -- NOVO: Nome do titular (para agregados via associado_titular_id)
                titular.nome AS titular_nome,
                titular.cpf AS titular_cpf,
                titular.rg AS titular_rg,
                
                -- Campos de Departamentos
                d.nome AS departamento_atual_nome,
                
                -- Campos calculados
                CASE 
                    WHEN da.tipo_documento = 'ficha_desfiliacao' THEN
                        CASE 
                            WHEN EXISTS (
                                SELECT 1 FROM Aprovacoes_Desfiliacao ad 
                                WHERE ad.documento_id = da.id 
                                AND ad.status_aprovacao = 'REJEITADO'
                            ) THEN 'Rejeitado'
                            WHEN da.status_fluxo = 'FINALIZADO' THEN 'Finalizado'
                            ELSE 'Em Aprovação'
                        END
                    ELSE
                        CASE da.status_fluxo
                            WHEN 'DIGITALIZADO' THEN 'Digitalizado'
                            WHEN 'AGUARDANDO_ASSINATURA' THEN 'Aguardando Assinatura'
                            WHEN 'ASSINADO' THEN 'Assinado'
                            WHEN 'FINALIZADO' THEN 'Finalizado'
                            ELSE da.status_fluxo
                        END
                END AS status_descricao,
                DATEDIFF(NOW(), da.data_upload) AS dias_em_processo,
                
                -- Identificador de origem
                CASE 
                    WHEN da.tipo_documento = 'ficha_desfiliacao' THEN 'DESFILIACAO'
                    ELSE 'DOCUMENTO'
                END AS origem_tabela,
                
                -- Aprovações (somente para desfiliação)
                CASE 
                    WHEN da.tipo_documento = 'ficha_desfiliacao' THEN
                        (
                            SELECT JSON_ARRAYAGG(
                                JSON_OBJECT(
                                    'departamento_id', ad2.departamento_id,
                                    'departamento_nome', ad2.departamento_nome,
                                    'status_aprovacao', ad2.status_aprovacao,
                                    'ordem_aprovacao', ad2.ordem_aprovacao,
                                    'data_acao', ad2.data_acao,
                                    'funcionario_nome', ad2.funcionario_nome,
                                    'observacao', ad2.observacao
                                )
                            )
                            FROM Aprovacoes_Desfiliacao ad2 
                            WHERE ad2.documento_id = da.id
                            ORDER BY ad2.ordem_aprovacao
                        )
                    ELSE NULL
                END AS aprovacoes_json
                
            FROM Documentos_Associado da
            LEFT JOIN Associados a ON da.associado_id = a.id
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
            LEFT JOIN Departamentos d ON da.departamento_atual = d.id
            WHERE 1=1";
    
    $sql = $sqlBase;
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
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao buscar documentos: ' . $e->getMessage(),
            'sql_error' => $e->getMessage()
        ]);
        exit;
    }

    // Contar total de documentos
    $sqlCount = "SELECT COUNT(*) as total FROM Documentos_Associado WHERE 1=1";
    $stmtCount = $db->query($sqlCount);
    $totalRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow['total'] ?? 0;

    // Estatísticas por status
    $sqlStats = "SELECT status_fluxo, COUNT(*) as total FROM Documentos_Associado GROUP BY status_fluxo";
    $stmtStats = $db->query($sqlStats);
    $estatisticas = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

    // Calcular informações de paginação
    $totalPaginas = ceil($total / $porPagina);

    $response = [
        'status' => 'success',
        'data' => $documentos,
        'total' => count($documentos),
        'total_geral' => intval($total),
        'paginacao' => [
            'pagina_atual' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => $totalPaginas,
            'total_registros' => intval($total),
            'tem_proxima' => $pagina < $totalPaginas,
            'tem_anterior' => $pagina > 1
        ],
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