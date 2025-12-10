<?php
/**
 * API - Assinar Documento de Agregado
 * api/documentos/documentos_agregados_assinar.php
 * 
 * VERSÃO 4.0 - Cria documento se não existir + Atualiza status_fluxo
 * 
 * Fluxo:
 * 1. Verifica se existe documento na tabela Documentos_Agregado
 * 2. Se não existir, CRIA um novo registro
 * 3. Atualiza status_fluxo para 'ASSINADO'
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
    exit;
}

function jsonSuccess($message, $data = null) {
    ob_end_clean();
    http_response_code(200);
    $response = ['status' => 'success', 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido. Use POST.', 405);
}

try {
    // Carregar configurações
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Verificar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonError('Não autorizado. Faça login novamente.', 401);
    }

    $funcionarioId = $_SESSION['funcionario_id'] ?? null;
    $funcionarioNome = $_SESSION['funcionario_nome'] ?? $_SESSION['usuario_nome'] ?? 'Sistema';
    
    if (!$funcionarioId) {
        jsonError('ID do funcionário não encontrado na sessão', 401);
    }

    // Ler dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('JSON inválido: ' . json_last_error_msg(), 400);
    }

    // Extrair IDs
    $documentoId = isset($input['documento_id']) ? $input['documento_id'] : null;
    $agregadoId = isset($input['agregado_id']) ? intval($input['agregado_id']) : 0;
    $observacao = trim($input['observacao'] ?? '');

    // Se documento_id for string com prefixo AGR_, extrair o número
    if (is_string($documentoId) && strpos($documentoId, 'AGR_') === 0) {
        $agregadoId = intval(str_replace('AGR_', '', $documentoId));
    } elseif (is_numeric($documentoId) && $agregadoId <= 0) {
        $agregadoId = intval($documentoId);
    }

    if ($agregadoId <= 0) {
        jsonError('ID do agregado inválido', 400);
    }

    // Conectar ao banco
    $dbName = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : (defined('DB_NAME') ? DB_NAME : 'wwasse_cadastro');
    $db = Database::getInstance($dbName)->getConnection();

    // =====================================================
    // BUSCAR AGREGADO NA ESTRUTURA UNIFICADA (Associados)
    // Nota: associado_titular_id ainda não existe no banco
    // =====================================================
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.nome,
            a.cpf,
            a.situacao,
            m.corporacao,
            m.patente,
            NULL as titular_nome,
            NULL as titular_cpf
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE a.id = ?
        AND m.corporacao = 'Agregados'
    ");
    $stmt->execute([$agregadoId]);
    $agregado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agregado) {
        jsonError('Agregado não encontrado com ID: ' . $agregadoId, 404);
    }

    error_log("[ASSINAR_AGREGADO] Agregado encontrado: {$agregado['nome']} (ID: {$agregadoId})");

    // =====================================================
    // BUSCAR DOCUMENTO NA ESTRUTURA UNIFICADA
    // =====================================================
    $stmt = $db->prepare("
        SELECT id, associado_id, status_fluxo, tipo_documento
        FROM Documentos_Associado 
        WHERE associado_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$agregadoId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar status atual
    $statusFluxoAtual = $documento ? $documento['status_fluxo'] : null;

    error_log("[ASSINAR_AGREGADO] Status atual do documento: " . ($statusFluxoAtual ?? 'SEM_DOCUMENTO'));

    // Se documento existe e já está ASSINADO ou FINALIZADO
    if ($documento) {
        if ($statusFluxoAtual === 'ASSINADO') {
            jsonError('Este documento já foi assinado. Use "Finalizar" para concluir o processo.', 400);
        }
        if ($statusFluxoAtual === 'FINALIZADO') {
            jsonError('Este documento já está finalizado.', 400);
        }
    }

    // Iniciar transação
    $db->beginTransaction();

    try {
        $dataHora = date('d/m/Y H:i:s');
        $novaObservacao = "[ASSINATURA {$dataHora} - {$funcionarioNome}] ";
        $novaObservacao .= !empty($observacao) ? $observacao : "Assinado pela presidência";

        // =====================================================
        // 1. SE NÃO EXISTE DOCUMENTO, CRIAR UM NOVO
        // =====================================================
        if (!$documento) {
            error_log("[DOCUMENTO_AGREGADO] Criando novo documento para agregado ID: {$agregadoId}");
            
            $stmt = $db->prepare("
                INSERT INTO Documentos_Associado 
                (associado_id, tipo_documento, tipo_origem, status_fluxo, nome_arquivo, caminho_arquivo, verificado, data_upload)
                VALUES (?, 'FICHA_AGREGADO', 'VIRTUAL', 'ASSINADO', 'ficha_virtual.pdf', '', 1, NOW())
            ");
            $stmt->execute([$agregadoId]);
            
            $documentoIdNovo = $db->lastInsertId();
            
            $documento = [
                'id' => $documentoIdNovo,
                'associado_id' => $agregadoId,
                'status_fluxo' => 'ASSINADO'
            ];
            
            error_log("[DOCUMENTO_AGREGADO] Documento criado com ID: {$documentoIdNovo}");
        } else {
            // =====================================================
            // 2. SE EXISTE, ATUALIZAR STATUS PARA ASSINADO
            // =====================================================
            $stmt = $db->prepare("
                UPDATE Documentos_Associado 
                SET status_fluxo = 'ASSINADO',
                    data_assinatura = NOW(),
                    verificado = 1,
                    observacoes_fluxo = CONCAT(COALESCE(observacoes_fluxo, ''), ?)
                WHERE id = ?
            ");
            $stmt->execute([$novaObservacao . "\n", $documento['id']]);
            
            error_log("[DOCUMENTO_AGREGADO] Atualizado para ASSINADO - Doc ID: {$documento['id']}");
        }

        // =====================================================
        // 4. COMMIT DA TRANSAÇÃO
        // =====================================================
        $db->commit();

        error_log("[AGREGADO] ASSINATURA CONCLUÍDA - ID: {$agregadoId}, Nome: {$agregado['nome']}, Por: {$funcionarioNome}");

        jsonSuccess('Documento assinado com sucesso! Clique em "Finalizar" para ativar o agregado.', [
            'agregado_id' => $agregadoId,
            'documento_id' => $documento['id'],
            'nome' => $agregado['nome'],
            'cpf' => $agregado['cpf'],
            'titular_nome' => $agregado['titular_nome'] ?? '',
            'titular_cpf' => $agregado['titular_cpf'] ?? '',
            'status_fluxo_anterior' => $statusFluxoAtual ?? 'NOVO',
            'status_fluxo_novo' => 'ASSINADO',
            'data_assinatura' => date('Y-m-d H:i:s'),
            'assinado_por' => $funcionarioNome,
            'proximo_passo' => 'Clique em "Finalizar" para ativar o agregado'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro de banco ao assinar agregado: " . $e->getMessage());
    jsonError('Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Erro ao assinar agregado: " . $e->getMessage());
    jsonError('Erro ao processar assinatura: ' . $e->getMessage(), 500);
}