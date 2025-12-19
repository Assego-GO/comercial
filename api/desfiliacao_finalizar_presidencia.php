<?php
/**
 * Finaliza desfiliação a partir da Presidência
 * - Atualiza documento para FINALIZADO (se ainda não estiver)
 * - Atualiza Associados: situacao='Desfiliado', data_desfiliacao=NOW(), ativo_atacadao=0
 * - Tenta inativar no Atacadão (status I) e registra logs
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../atacadao/Client.php';
require_once '../atacadao/Logger.php';

function respond($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['status'=>$status,'message'=>$message,'data'=>$data], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond('error', 'Método não permitido', null, 405);
    }

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        respond('error', 'Não autenticado', null, 401);
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    $documentoId = isset($payload['documento_id']) ? intval($payload['documento_id']) : 0;

    if ($documentoId <= 0) {
        respond('error', 'documento_id é obrigatório', null, 400);
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Buscar documento
    $stmt = $db->prepare("SELECT id, associado_id, tipo_documento, status_fluxo FROM Documentos_Associado WHERE id = ?");
    $stmt->execute([$documentoId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        respond('error', 'Documento não encontrado', null, 404);
    }
    if ($doc['tipo_documento'] !== 'ficha_desfiliacao') {
        respond('error', 'Documento não é uma desfiliação', null, 400);
    }

    $db->beginTransaction();
    try {
        // Finaliza documento se necessário
        if ($doc['status_fluxo'] !== 'FINALIZADO') {
            $stmt = $db->prepare("UPDATE Documentos_Associado SET status_fluxo='FINALIZADO', data_finalizacao=NOW() WHERE id = ?");
            $stmt->execute([$documentoId]);
        }

        // Atualiza associado
        if (!empty($doc['associado_id'])) {
            $stmt = $db->prepare("SELECT cpf FROM Associados WHERE id = ? LIMIT 1");
            $stmt->execute([$doc['associado_id']]);
            $assoc = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Atualiza situação e data - SEM alterar ativo_atacadao ainda
            $stmt = $db->prepare("UPDATE Associados SET situacao='Desfiliado', data_desfiliacao=NOW() WHERE id = ?");
            $stmt->execute([$doc['associado_id']]);
            error_log("[PRESIDENCIA][DESFILIACAO] Associado {$doc['associado_id']} atualizado: situacao=Desfiliado");

            // Integração Atacadão
            $cpf = $assoc['cpf'] ?? '';
            if ($cpf) {
                $res = AtacadaoClient::ativarCliente($cpf, 'I', '58');
                AtacadaoLogger::logAtivacao((int)$doc['associado_id'], $cpf, 'I', '58', $res['http'] ?? 0, $res['ok'] ?? false, $res['data'] ?? null, $res['error'] ?? null);
                
                // Só atualiza ativo_atacadao=0 se API retornou 200 OK
                $atacadaoOk = ($res['ok'] ?? false) && (($res['http'] ?? 0) === 200);
                if ($atacadaoOk) {
                    $stmt = $db->prepare("UPDATE Associados SET ativo_atacadao = 0 WHERE id = ?");
                    $stmt->execute([$doc['associado_id']]);
                    error_log("[PRESIDENCIA][ATACADAO] ativo_atacadao=0 atualizado (API 200 OK)");
                } else {
                    error_log('[PRESIDENCIA][ATACADAO] Falha ao inativar CPF ' . $cpf . ' | http=' . ($res['http'] ?? 'N/A'));
                    error_log("[PRESIDENCIA][ATACADAO] ativo_atacadao NÃO atualizado (API não retornou 200)");
                }
            }
        }

        $db->commit();
        respond('success', 'Desfiliação finalizada e associado atualizado.', [
            'documento_id' => $documentoId,
            'associado_id' => intval($doc['associado_id'] ?? 0)
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('[PRESIDENCIA][DESFILIACAO] Erro: ' . $e->getMessage());
        respond('error', 'Erro ao finalizar desfiliação: ' . $e->getMessage(), null, 500);
    }
} catch (Exception $e) {
    respond('error', 'Erro no servidor: ' . $e->getMessage(), null, 500);
}
