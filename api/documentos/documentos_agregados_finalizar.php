<?php
/**
 * API para finalizar processo de sócio agregado - VERSÃO UNIFICADA
 * api/documentos/documentos_agregados_finalizar.php
 * 
 * Atualiza:
 * 1. Documentos_Associado -> status_fluxo = 'FINALIZADO'
 * 2. Associados -> situacao permanece como está (já é 'Filiado')
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

function jsonError($message, $code = 400) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    error_log("[FINALIZAR_AGREGADO_ERROR] $message");
    exit;
}

function jsonSuccess($message, $data = []) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Método não permitido. Use POST.');
    }

    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';

    session_start();

    $funcionarioId = $_SESSION['funcionario_id'] ?? null;
    $funcionarioNome = $_SESSION['funcionario_nome'] ?? $_SESSION['usuario_nome'] ?? 'Sistema';
    
    if (!$funcionarioId) {
        jsonError('Funcionário não identificado');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $documentoId = isset($input['documento_id']) ? $input['documento_id'] : null;
    $agregadoId = isset($input['agregado_id']) ? intval($input['agregado_id']) : 0;
    $observacao = isset($input['observacao']) ? trim($input['observacao']) : '';

    // Se documento_id for string com prefixo AGR_, extrair o número
    if (is_string($documentoId) && strpos($documentoId, 'AGR_') === 0) {
        $agregadoId = intval(str_replace('AGR_', '', $documentoId));
    } elseif (is_numeric($documentoId) && $agregadoId <= 0) {
        $agregadoId = intval($documentoId);
    }

    if ($agregadoId <= 0) {
        jsonError('ID do agregado inválido');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();

    try {
        // =====================================================
        // 1. BUSCAR AGREGADO (ESTRUTURA UNIFICADA)
        // =====================================================
        $stmt = $db->prepare("
            SELECT 
                a.id,
                a.nome,
                a.cpf,
                a.situacao,
                a.associado_titular_id,
                m.corporacao,
                titular.nome as titular_nome
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Associados titular ON a.associado_titular_id = titular.id
            WHERE a.id = ?
            AND m.corporacao = 'Agregados'
        ");
        $stmt->execute([$agregadoId]);
        $agregado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agregado) {
            jsonError('Agregado não encontrado com ID: ' . $agregadoId, 404);
        }

        error_log("[FINALIZAR_AGREGADO] Agregado encontrado: {$agregado['nome']} (ID: {$agregadoId})");

        // =====================================================
        // 2. BUSCAR DOCUMENTO
        // =====================================================
        $stmt = $db->prepare("
            SELECT id, associado_id, status_fluxo
            FROM Documentos_Associado 
            WHERE associado_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$agregadoId]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$documento) {
            jsonError('Documento não encontrado para este agregado', 404);
        }

        $statusAnterior = $documento['status_fluxo'];

        // Validar se documento está ASSINADO
        if ($statusAnterior !== 'ASSINADO') {
            jsonError("Documento precisa estar ASSINADO para ser finalizado. Status atual: {$statusAnterior}", 400);
        }

        error_log("[FINALIZAR_AGREGADO] Status atual: {$statusAnterior}");

        // =====================================================
        // 3. ATUALIZAR DOCUMENTO PARA FINALIZADO
        // =====================================================
        $dataHora = date('d/m/Y H:i:s');
        $novaObservacao = "[FINALIZAÇÃO {$dataHora} - {$funcionarioNome}] ";
        $novaObservacao .= !empty($observacao) ? $observacao : "Processo finalizado - Agregado ativo";

        $stmt = $db->prepare("
            UPDATE Documentos_Associado 
            SET status_fluxo = 'FINALIZADO',
                data_finalizacao = NOW(),
                observacoes_fluxo = CONCAT(COALESCE(observacoes_fluxo, ''), ?)
            WHERE id = ?
        ");
        $stmt->execute([$novaObservacao . "\n", $documento['id']]);

        error_log("[FINALIZAR_AGREGADO] Documento atualizado para FINALIZADO - Doc ID: {$documento['id']}");

        // =====================================================
        // 3.5. ATUALIZAR PRE_CADASTRO NA TABELA ASSOCIADOS
        // =====================================================
        $stmt = $db->prepare("
            UPDATE Associados 
            SET pre_cadastro = 0 
            WHERE id = ?
        ");
        $stmt->execute([$agregadoId]);

        error_log("[FINALIZAR_AGREGADO] pre_cadastro alterado de 1 para 0 - Agregado ID: {$agregadoId}");

        // =====================================================
        // 4. COMMIT
        // =====================================================
        $db->commit();

        error_log("[AGREGADO] FINALIZAÇÃO CONCLUÍDA - ID: {$agregadoId}, Nome: {$agregado['nome']}, Por: {$funcionarioNome}");

        jsonSuccess('Processo finalizado com sucesso! O agregado está ativo no sistema.', [
            'agregado_id' => $agregadoId,
            'documento_id' => $documento['id'],
            'nome' => $agregado['nome'],
            'cpf' => $agregado['cpf'],
            'titular_nome' => $agregado['titular_nome'] ?? '',
            'status_anterior' => $statusAnterior,
            'status_novo' => 'FINALIZADO',
            'situacao' => $agregado['situacao'],
            'data_finalizacao' => date('Y-m-d H:i:s')
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro PDO em finalizar agregado: " . $e->getMessage());
    jsonError('Erro no banco de dados: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Erro em finalizar agregado: " . $e->getMessage());
    jsonError($e->getMessage(), 500);
}
