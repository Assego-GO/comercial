<?php
/**
 * API para Listar Documentos de Desfiliação no Fluxo
 * api/documentos/documentos_desfiliacao_listar.php
 * 
 * Lista todas as desfiliações em processo com seus status de aprovação
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

ob_start('ob_gzhandler');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

function jsonResponse($status, $message, $data = null, $code = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    jsonResponse('error', 'Não autenticado', null, 401);
}

try {
    // Parâmetros de filtro
    $status = $_GET['status'] ?? null;
    $busca = $_GET['busca'] ?? null;
    $periodo = $_GET['periodo'] ?? null;
    $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $porPagina = isset($_GET['por_pagina']) ? min(100, max(10, intval($_GET['por_pagina']))) : 20;
    $offset = ($pagina - 1) * $porPagina;

    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Construir query
    $where = ["da.tipo_documento = 'ficha_desfiliacao'", "da.deletado = 0"];
    $params = [];

    // Filtro de busca
    if ($busca) {
        $where[] = "(a.nome LIKE ? OR a.cpf LIKE ?)";
        $buscaTermo = "%{$busca}%";
        $params[] = $buscaTermo;
        $params[] = $buscaTermo;
    }

    // Filtro de período
    if ($periodo) {
        switch ($periodo) {
            case 'hoje':
                $where[] = "DATE(da.data_upload) = CURDATE()";
                break;
            case 'semana':
                $where[] = "YEARWEEK(da.data_upload, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'mes':
                $where[] = "YEAR(da.data_upload) = YEAR(CURDATE()) AND MONTH(da.data_upload) = MONTH(CURDATE())";
                break;
        }
    }

    // Filtro de status de aprovação
    if ($status) {
        if ($status === 'PENDENTE') {
            $where[] = "EXISTS (
                SELECT 1 FROM Aprovacoes_Desfiliacao ad 
                WHERE ad.documento_id = da.id 
                AND ad.status_aprovacao = 'PENDENTE'
            )";
        } elseif ($status === 'APROVADO') {
            $where[] = "da.status_fluxo = 'FINALIZADO' AND NOT EXISTS (
                SELECT 1 FROM Aprovacoes_Desfiliacao ad 
                WHERE ad.documento_id = da.id 
                AND ad.status_aprovacao = 'REJEITADO'
            )";
        } elseif ($status === 'REJEITADO') {
            $where[] = "EXISTS (
                SELECT 1 FROM Aprovacoes_Desfiliacao ad 
                WHERE ad.documento_id = da.id 
                AND ad.status_aprovacao = 'REJEITADO'
            )";
        }
    }

    $whereClause = implode(' AND ', $where);

    // Query principal
    $sql = "
        SELECT 
            da.id,
            da.associado_id,
            da.caminho_arquivo,
            da.data_upload,
            da.status_fluxo,
            a.nome AS associado_nome,
            a.cpf AS associado_cpf,
            a.rg AS associado_rg,
            func.nome AS funcionario_nome,
            DATEDIFF(NOW(), da.data_upload) AS dias_em_processo,
            (SELECT GROUP_CONCAT(
                CONCAT(ad2.departamento_nome, ':', ad2.status_aprovacao) 
                ORDER BY ad2.ordem_aprovacao 
                SEPARATOR '|'
            ) FROM Aprovacoes_Desfiliacao ad2 
            WHERE ad2.documento_id = da.id) AS fluxo_aprovacoes
        FROM Documentos_Associado da
        INNER JOIN Associados a ON da.associado_id = a.id
        LEFT JOIN Funcionarios func ON da.funcionario_id = func.id
        WHERE {$whereClause}
        ORDER BY da.data_upload DESC
        LIMIT {$porPagina} OFFSET {$offset}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM Documentos_Associado da
        INNER JOIN Associados a ON da.associado_id = a.id
        WHERE {$whereClause}
    ";
    
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Processar documentos para adicionar detalhes de aprovação
    foreach ($documentos as &$doc) {
        // Buscar detalhes das aprovações
        $stmtAprov = $db->prepare("
            SELECT 
                departamento_id,
                departamento_nome,
                ordem_aprovacao,
                status_aprovacao,
                funcionario_nome,
                data_acao,
                observacao
            FROM Aprovacoes_Desfiliacao
            WHERE documento_id = ?
            ORDER BY ordem_aprovacao ASC
        ");
        $stmtAprov->execute([$doc['id']]);
        $doc['aprovacoes'] = $stmtAprov->fetchAll(PDO::FETCH_ASSOC);

        // Determinar status geral
        $doc['status_geral'] = 'EM_APROVACAO';
        $temRejeitado = false;
        $todasAprovadas = true;
        
        foreach ($doc['aprovacoes'] as $aprov) {
            if ($aprov['status_aprovacao'] === 'REJEITADO') {
                $temRejeitado = true;
            }
            if ($aprov['status_aprovacao'] === 'PENDENTE') {
                $todasAprovadas = false;
            }
        }

        if ($temRejeitado) {
            $doc['status_geral'] = 'REJEITADO';
        } elseif ($todasAprovadas) {
            $doc['status_geral'] = 'APROVADO';
        }
    }

    // Estatísticas
    $stmtStats = $db->prepare("
        SELECT 
            SUM(CASE WHEN ad.departamento_id = 2 AND ad.status_aprovacao = 'PENDENTE' THEN 1 ELSE 0 END) as aguardando_financeiro,
            SUM(CASE WHEN ad.departamento_id = 3 AND ad.status_aprovacao = 'PENDENTE' THEN 1 ELSE 0 END) as aguardando_juridico,
            SUM(CASE WHEN ad.departamento_id = 1 AND ad.status_aprovacao = 'PENDENTE' THEN 1 ELSE 0 END) as aguardando_presidencia,
            SUM(CASE WHEN da.status_fluxo = 'FINALIZADO' THEN 1 ELSE 0 END) as finalizadas,
            SUM(CASE WHEN ad.status_aprovacao = 'REJEITADO' THEN 1 ELSE 0 END) as rejeitadas
        FROM Documentos_Associado da
        LEFT JOIN Aprovacoes_Desfiliacao ad ON da.id = ad.documento_id
        WHERE da.tipo_documento = 'ficha_desfiliacao'
        AND da.deletado = 0
    ");
    $stmtStats->execute();
    $estatisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);

    jsonResponse('success', 'Desfiliações carregadas com sucesso', [
        'documentos' => $documentos,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'por_pagina' => $porPagina,
            'total_registros' => intval($totalRegistros),
            'total_paginas' => ceil($totalRegistros / $porPagina)
        ],
        'estatisticas' => $estatisticas
    ]);

} catch (Exception $e) {
    error_log("Erro ao listar desfiliações: " . $e->getMessage());
    jsonResponse('error', 'Erro ao buscar desfiliações: ' . $e->getMessage(), null, 500);
}
