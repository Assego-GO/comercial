<?php
/**
 * API para Listar Status de Aprovações de Desfiliação
 * api/desfiliacao_status_aprovacoes.php
 * 
 * Retorna o status de aprovação de cada departamento
 * GET /api/desfiliacao_status_aprovacoes.php?documento_id=123
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

    if (empty($_GET['documento_id'])) {
        jsonResponse('error', 'documento_id obrigatório', null, 400);
    }

    $documentoId = intval($_GET['documento_id']);
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Verificar documento
    $stmt = $db->prepare("
        SELECT id, tipo_documento, status_fluxo, associado_id
        FROM Documentos_Associado
        WHERE id = ? AND tipo_documento = 'ficha_desfiliacao'
    ");
    $stmt->execute([$documentoId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        jsonResponse('error', 'Documento de desfiliação não encontrado', null, 404);
    }

    // Buscar todas as aprovações (ordenadas pela sequência)
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.departamento_id,
            a.departamento_nome,
            a.ordem_aprovacao,
            a.obrigatorio,
            a.status_aprovacao,
            a.funcionario_nome,
            a.data_acao,
            a.observacao
        FROM Aprovacoes_Desfiliacao a
        WHERE a.documento_id = ?
        ORDER BY a.ordem_aprovacao ASC
    ");
    $stmt->execute([$documentoId]);
    $aprovacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular resumo
    $total = count($aprovacoes);
    $aprovadas = array_filter($aprovacoes, fn($a) => $a['status_aprovacao'] === 'APROVADO');
    $rejeitadas = array_filter($aprovacoes, fn($a) => $a['status_aprovacao'] === 'REJEITADO');
    $pendentes = array_filter($aprovacoes, fn($a) => $a['status_aprovacao'] === 'PENDENTE');

    // Buscar próxima etapa na sequência
    $proximaEtapa = null;
    foreach ($aprovacoes as $aprov) {
        if ($aprov['status_aprovacao'] === 'PENDENTE') {
            $proximaEtapa = [
                'departamento' => $aprov['departamento_nome'],
                'ordem' => $aprov['ordem_aprovacao']
            ];
            break;
        }
    }

    // Status geral
    $statusGeral = 'AGUARDANDO';
    if (count($rejeitadas) > 0) {
        $statusGeral = 'REJEITADO';
    } elseif (count($pendentes) === 0 && count($aprovadas) === $total) {
        $statusGeral = 'APROVADO';
    }

    jsonResponse('success', 'Status de aprovações obtido', [
        'documento_id' => $documentoId,
        'associado_id' => $documento['associado_id'],
        'status_documento' => $documento['status_fluxo'],
        'status_geral_aprovacoes' => $statusGeral,
        'proxima_etapa' => $proximaEtapa,
        'resumo' => [
            'total_etapas' => $total,
            'aprovados' => count($aprovadas),
            'rejeitados' => count($rejeitadas),
            'pendentes' => count($pendentes),
            'percentual_conclusao' => $total > 0 ? round((count($aprovadas) / $total) * 100) : 0
        ],
        'detalhes' => $aprovacoes
    ], 200);

} catch (Exception $e) {
    error_log("[DESFILIACAO] Erro ao buscar status: " . $e->getMessage());
    jsonResponse('error', 'Erro no servidor: ' . $e->getMessage(), null, 500);
}
