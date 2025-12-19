<?php
/**
 * API para Aprovar/Rejeitar Desfiliação
 * api/desfiliacao_aprovar.php
 * 
 * Requisição:
 * POST /api/desfiliacao_aprovar.php
 * {
 *   "documento_id": 123,
 *   "departamento_id": 1,
 *   "status": "APROVADO" | "REJEITADO",
 *   "observacao": "texto opcional"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

ob_start('ob_gzhandler');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Permissoes.php';
require_once '../atacadao/Client.php';
require_once '../atacadao/Logger.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Método não permitido', null, 405);
    }

    // Validar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonResponse('error', 'Não autenticado', null, 401);
    }

    // Validar dados obrigatórios
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['documento_id']) || empty($data['departamento_id']) || empty($data['status'])) {
        jsonResponse('error', 'documento_id, departamento_id e status são obrigatórios', null, 400);
    }

    $documentoId = intval($data['documento_id']);
    $departamentoId = intval($data['departamento_id']);
    $status = strtoupper($data['status']);
    $observacao = $data['observacao'] ?? '';
    $usuario = $auth->getUser();

    // Validar status
    if (!in_array($status, ['APROVADO', 'REJEITADO'])) {
        jsonResponse('error', 'Status deve ser APROVADO ou REJEITADO', null, 400);
    }

    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Verificar se documento existe
    $stmt = $db->prepare("SELECT id, associado_id, tipo_documento, status_fluxo FROM Documentos_Associado WHERE id = ?");
    $stmt->execute([$documentoId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        jsonResponse('error', 'Documento não encontrado', null, 404);
    }

    // Verificar se é uma desfiliação
    if ($documento['tipo_documento'] !== 'ficha_desfiliacao') {
        jsonResponse('error', 'Este documento não é uma desfiliação', null, 400);
    }

    // Validar que o status_fluxo é um ENUM válido
    $statusFluxoValidos = ['DIGITALIZADO', 'AGUARDANDO_ASSINATURA', 'ASSINADO', 'FINALIZADO'];
    if (!in_array($documento['status_fluxo'], $statusFluxoValidos)) {
        jsonResponse('error', 'Status do fluxo inválido', null, 400);
    }

    // Verificar se departamento existe
    $stmt = $db->prepare("SELECT id, nome FROM Departamentos WHERE id = ?");
    $stmt->execute([$departamentoId]);
    $departamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$departamento) {
        jsonResponse('error', 'Departamento não encontrado', null, 404);
    }

    // Iniciar transação
    $db->beginTransaction();

    try {
        // Verificar se é a vez deste departamento aprovar
        $stmt = $db->prepare("
            SELECT ordem_aprovacao, status_aprovacao
            FROM Aprovacoes_Desfiliacao
            WHERE documento_id = ?
            AND departamento_id = ?
        ");
        $stmt->execute([$documentoId, $departamentoId]);
        $aprovacaoAtual = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$aprovacaoAtual) {
            $db->rollBack();
            jsonResponse('error', 'Este departamento não precisa aprovar esta desfiliação', null, 400);
        }

        // Verificar se já foi aprovado/rejeitado
        if ($aprovacaoAtual['status_aprovacao'] !== 'PENDENTE') {
            $db->rollBack();
            jsonResponse('error', 'Esta etapa já foi processada', null, 400);
        }

        // Verificar se há etapas anteriores ainda pendentes
        $stmt = $db->prepare("
            SELECT COUNT(*) as pendentes_anteriores
            FROM Aprovacoes_Desfiliacao
            WHERE documento_id = ?
            AND ordem_aprovacao < ?
            AND status_aprovacao = 'PENDENTE'
        ");
        $stmt->execute([$documentoId, $aprovacaoAtual['ordem_aprovacao']]);
        $pendentesAnteriores = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pendentesAnteriores['pendentes_anteriores'] > 0) {
            $db->rollBack();
            jsonResponse('error', 'Ainda há etapas anteriores pendentes. Aguarde a aprovação sequencial.', null, 400);
        }

        // Registrar aprovação/rejeição
        $stmt = $db->prepare("
            UPDATE Aprovacoes_Desfiliacao
            SET status_aprovacao = ?,
                funcionario_id = ?,
                funcionario_nome = ?,
                observacao = ?,
                data_acao = NOW()
            WHERE documento_id = ?
            AND departamento_id = ?
        ");

        $stmt->execute([
            $status,
            $usuario['id'],
            $usuario['nome'],
            $observacao,
            $documentoId,
            $departamentoId
        ]);

        // Verificar status do fluxo completo
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status_aprovacao = 'APROVADO' THEN 1 ELSE 0 END) as aprovadas,
                SUM(CASE WHEN status_aprovacao = 'REJEITADO' THEN 1 ELSE 0 END) as rejeitadas,
                SUM(CASE WHEN status_aprovacao = 'PENDENTE' THEN 1 ELSE 0 END) as pendentes
            FROM Aprovacoes_Desfiliacao
            WHERE documento_id = ?
        ");
        $stmt->execute([$documentoId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $novoStatus = $documento['status_fluxo'];
        $mensagemFinalizacao = '';
        $proximaEtapa = null;

        // Normalizar contagens para inteiro
        $totalEtapas     = intval($stats['total']);
        $aprovadasEtapas = intval($stats['aprovadas']);
        $rejeitadasEtapas= intval($stats['rejeitadas']);
        $pendentesEtapas = intval($stats['pendentes']);

        // Se houver rejeição, finalizar como rejeitado
        if ($rejeitadasEtapas > 0) {
            $novoStatus = 'FINALIZADO';  // Usar ENUM válido
            $mensagemFinalizacao = "Desfiliação rejeitada por {$departamento['nome']}";
        }
        // Se todas aprovadas, finalizar
        elseif ($pendentesEtapas == 0 && $aprovadasEtapas == $totalEtapas) {
            $novoStatus = 'FINALIZADO';  // Usar ENUM válido
            $mensagemFinalizacao = "Desfiliação aprovada por todos os departamentos - Processo finalizado";
        }
        // Se ainda há pendentes, manter como AGUARDANDO_ASSINATURA (fluxo em andamento)
        elseif ($pendentesEtapas > 0) {
            $novoStatus = 'AGUARDANDO_ASSINATURA';  // Correção: não deve virar ASSINADO nesta etapa
            
            // Buscar próximo departamento na sequência
            $stmt = $db->prepare("
                SELECT departamento_nome, ordem_aprovacao
                FROM Aprovacoes_Desfiliacao
                WHERE documento_id = ?
                AND status_aprovacao = 'PENDENTE'
                ORDER BY ordem_aprovacao ASC
                LIMIT 1
            ");
            $stmt->execute([$documentoId]);
            $proxima = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($proxima) {
                $proximaEtapa = $proxima['departamento_nome'];
                $mensagemFinalizacao = "Aprovado por {$departamento['nome']}. Aguardando {$proximaEtapa}";
            }
        }

        if ($novoStatus !== $documento['status_fluxo']) {
            $stmt = $db->prepare("
                UPDATE Documentos_Associado
                SET status_fluxo = ?,
                    data_finalizacao = IF(? = 'FINALIZADO', NOW(), NULL)
                WHERE id = ?
            ");

            $stmt->execute([$novoStatus, $novoStatus, $documentoId]);

            // Registrar no histórico
            $stmt = $db->prepare("
                INSERT INTO Historico_Fluxo_Documento (
                    documento_id, status_anterior, status_novo,
                    departamento_origem, departamento_destino,
                    funcionario_id, observacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $documentoId,
                $documento['status_fluxo'],
                $novoStatus,
                $departamentoId,
                $departamentoId,
                $usuario['id'],
                $mensagemFinalizacao
            ]);
        }

        // Se todas as etapas foram aprovadas e o documento foi finalizado, atualizar situação do associado e integrar Atacadão
        $desfiliacaoConcluida = ($novoStatus === 'FINALIZADO' && $rejeitadasEtapas === 0 && $pendentesEtapas === 0 && $aprovadasEtapas === $totalEtapas);

        // Log de depuração para acompanhar finalização
        error_log("[DESFILIACAO] DEBUG concluiu?=" . ($desfiliacaoConcluida ? 'SIM' : 'NAO') . " total={$totalEtapas} aprov={$aprovadasEtapas} pend={$pendentesEtapas} rej={$rejeitadasEtapas} novoStatus={$novoStatus}");

        if ($desfiliacaoConcluida && !empty($documento['associado_id'])) {
            try {
                // Buscar CPF do associado
                $stmt = $db->prepare("SELECT cpf FROM Associados WHERE id = ? LIMIT 1");
                $stmt->execute([$documento['associado_id']]);
                $assoc = $stmt->fetch(PDO::FETCH_ASSOC);

                // Atualizar situação (padrão do sistema: 'Desfiliado') e data - SEM alterar ativo_atacadao ainda
                $stmt = $db->prepare("UPDATE Associados SET situacao = 'Desfiliado', data_desfiliacao = NOW() WHERE id = ?");
                $stmt->execute([$documento['associado_id']]);
                error_log("[DESFILIACAO] Associado ID {$documento['associado_id']} atualizado: situacao=Desfiliado");

                // Integrar com Atacadão para inativar o CPF
                $cpfAssoc = $assoc['cpf'] ?? '';
                $resAta = AtacadaoClient::ativarCliente($cpfAssoc, 'I', '58');

                AtacadaoLogger::logAtivacao(
                    intval($documento['associado_id']),
                    $cpfAssoc,
                    'I',
                    '58',
                    $resAta['http'] ?? 0,
                    $resAta['ok'] ?? false,
                    $resAta['data'] ?? null,
                    $resAta['error'] ?? null
                );

                // Só atualiza ativo_atacadao=0 se API retornou 200 OK
                $atacadaoOk = ($resAta['ok'] ?? false) && (($resAta['http'] ?? 0) === 200);
                if ($atacadaoOk) {
                    $stmt = $db->prepare("UPDATE Associados SET ativo_atacadao = 0 WHERE id = ?");
                    $stmt->execute([$documento['associado_id']]);
                    error_log("[DESFILIACAO][ATACADAO] ativo_atacadao=0 atualizado (API 200 OK)");
                } else {
                    error_log("[DESFILIACAO][ATACADAO] Falha ao inativar CPF {$cpfAssoc} | http=" . ($resAta['http'] ?? 'N/A') . " | ok=" . (($resAta['ok'] ?? false) ? '1' : '0') . " | erro=" . ($resAta['error'] ?? 'N/A'));
                    error_log("[DESFILIACAO][ATACADAO] ativo_atacadao NÃO atualizado (API não retornou 200)");
                }

            } catch (Exception $e) {
                error_log("[DESFILIACAO] Falha ao atualizar associado/Atacadão: " . $e->getMessage());
            }
        }

        $db->commit();

        error_log("[DESFILIACAO] {$status} registrado - Doc: {$documentoId}, Dept: {$departamento['nome']}, Novo Status: {$novoStatus}, Próxima: " . ($proximaEtapa ?? 'N/A'));

        jsonResponse('success', "Aprovação registrada com sucesso!", [
            'documento_id' => $documentoId,
            'departamento' => $departamento['nome'],
            'status_aprovacao' => $status,
            'novo_status_documento' => $novoStatus,
            'proxima_etapa' => $proximaEtapa,
            'total_etapas' => intval($stats['total']),
            'etapas_aprovadas' => intval($stats['aprovadas']),
            'etapas_rejeitadas' => intval($stats['rejeitadas']),
            'etapas_pendentes' => intval($stats['pendentes']),
            'mensagem' => $mensagemFinalizacao
        ], 200);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("[DESFILIACAO] Erro na aprovação: " . $e->getMessage());
        jsonResponse('error', 'Erro ao registrar aprovação: ' . $e->getMessage(), null, 500);
    }

} catch (Exception $e) {
    error_log("[DESFILIACAO] Erro geral: " . $e->getMessage());
    jsonResponse('error', 'Erro no servidor: ' . $e->getMessage(), null, 500);
}
