<?php
/**
 * API para Listar Desfiliações Pendentes do Financeiro
 * api/desfiliacao_listar_financeiro.php
 * 
 * Retorna desfiliações aguardando aprovação do Financeiro
 * GET /api/desfiliacao_listar_financeiro.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

ob_start('ob_gzhandler');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse('error', 'Método não permitido', null, 405);
    }

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonResponse('error', 'Não autenticado', null, 401);
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Buscar desfiliações pendentes para FINANCEIRO (ordem 1)
    $stmt = $db->prepare("
        SELECT 
            da.id as documento_id,
            da.associado_id,
            a.nome as associado_nome,
            a.cpf as associado_cpf,
            a.email as associado_email,
            a.telefone as associado_telefone,
            da.nome_arquivo,
            da.caminho_arquivo,
            da.data_upload,
            func.nome as funcionario_comercial,
            aprov.status_aprovacao,
            aprov.data_acao,
            aprov.observacao
        FROM Documentos_Associado da
        JOIN Associados a ON da.associado_id = a.id
        LEFT JOIN Funcionarios func ON da.funcionario_id = func.id
        JOIN Aprovacoes_Desfiliacao aprov ON da.id = aprov.documento_id
        WHERE da.tipo_documento = 'ficha_desfiliacao'
        AND aprov.departamento_id = 2  -- Financeiro ID (não 1, que é Presidência)
        AND aprov.ordem_aprovacao = 1   -- Primeira etapa
        AND aprov.status_aprovacao = 'PENDENTE'
        -- Garante que não há pendências anteriores (não deveria haver, mas garante)
        AND NOT EXISTS (
            SELECT 1 FROM Aprovacoes_Desfiliacao ant
            WHERE ant.documento_id = da.id
            AND ant.ordem_aprovacao < aprov.ordem_aprovacao
            AND ant.status_aprovacao = 'PENDENTE'
        )
        ORDER BY da.data_upload ASC
    ");
    
    $stmt->execute();
    $desfiliações = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar total de pendentes
    $totalPendentes = count($desfiliações);

    // Buscar também histórico resumido de cada desfiliação
    foreach ($desfiliações as &$desf) {
        $stmtHist = $db->prepare("
            SELECT 
                departamento_nome,
                ordem_aprovacao,
                status_aprovacao,
                funcionario_nome,
                data_acao
            FROM Aprovacoes_Desfiliacao
            WHERE documento_id = ?
            ORDER BY ordem_aprovacao ASC
        ");
        $stmtHist->execute([$desf['documento_id']]);
        $desf['fluxo'] = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    }

    jsonResponse('success', "Total de $totalPendentes desfiliações aguardando Financeiro", [
        'total_pendentes' => $totalPendentes,
        'desfiliações' => $desfiliações
    ], 200);

} catch (Exception $e) {
    error_log("[DESFILIACAO] Erro ao listar: " . $e->getMessage());
    jsonResponse('error', 'Erro no servidor: ' . $e->getMessage(), null, 500);
}
