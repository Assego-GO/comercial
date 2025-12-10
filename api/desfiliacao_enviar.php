<?php
/**
 * API para Desfiliação - Upload e Envio
 * api/desfiliacao_enviar.php
 */

// Headers obrigatórios
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Desabilitar cache de saída para JSON limpo
ob_start('ob_gzhandler');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Permissoes.php';

function jsonResponse($status, $message, $data = null, $code = 200) {
    // Limpar buffer
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
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Método não permitido', null, 405);
    }

    // Validar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonResponse('error', 'Não autenticado', null, 401);
    }

    // Validar permissão
    if (!Permissoes::tem('COMERCIAL_DESFILIACAO')) {
        jsonResponse('error', 'Sem permissão para desfiliação', null, 403);
    }

    // Validar dados
    if (empty($_POST['associado_id'])) {
        jsonResponse('error', 'associado_id obrigatório', null, 400);
    }

    if (empty($_FILES['arquivo'])) {
        jsonResponse('error', 'arquivo obrigatório', null, 400);
    }

    $associadoId = intval($_POST['associado_id']);
    $observacao = $_POST['observacao'] ?? '';
    $usuario = $auth->getUser();
    
    // Validar arquivo
    $arquivo = $_FILES['arquivo'];
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    $maxSize = 10 * 1024 * 1024;

    if (!in_array($arquivo['type'], $allowedTypes)) {
        jsonResponse('error', 'Tipo de arquivo não permitido (PDF, JPG, PNG)', null, 400);
    }

    if ($arquivo['size'] > $maxSize) {
        jsonResponse('error', 'Arquivo muito grande (máximo 10MB)', null, 400);
    }

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        jsonResponse('error', 'Erro ao fazer upload do arquivo', null, 400);
    }

    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Verificar se associado existe
    $stmt = $db->prepare("SELECT id, nome, situacao FROM Associados WHERE id = ?");
    $stmt->execute([$associadoId]);
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$associado) {
        jsonResponse('error', 'Associado não encontrado', null, 404);
    }

    // Preparar upload
    $uploadDir = dirname(__DIR__) . '/uploads/documentos/desfiliacao/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Gerar nome único
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nomeArquivo = 'desfiliacao_' . $associadoId . '_' . time() . '.' . strtolower($extensao);
    $caminhoDestino = $uploadDir . $nomeArquivo;
    $caminhoRelativo = 'uploads/documentos/desfiliacao/' . $nomeArquivo;

    // Fazer upload
    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
        jsonResponse('error', 'Erro ao salvar arquivo no servidor', null, 500);
    }

    // Buscar departamento comercial
    $stmt = $db->prepare("SELECT id FROM Departamentos WHERE nome = 'Comercial' LIMIT 1");
    $stmt->execute();
    $deptComercial = $stmt->fetch(PDO::FETCH_ASSOC);
    $deptComercialId = $deptComercial ? $deptComercial['id'] : 1;

    // Iniciar transação
    $db->beginTransaction();

    try {
        // Inserir documento (tabela não possui tamanho_arquivo/tipo_mime)
        $stmt = $db->prepare("
            INSERT INTO Documentos_Associado (
                associado_id,
                tipo_documento,
                tipo_origem,
                nome_arquivo,
                caminho_arquivo,
                data_upload,
                funcionario_id,
                departamento_atual,
                status_fluxo,
                observacao,
                verificado
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $associadoId,
            'ficha_desfiliacao',
            'FISICO',
            $nomeArquivo,
            $caminhoRelativo,
            $usuario['id'],
            $deptComercialId,
            'DIGITALIZADO',
            $observacao ?: 'Desfiliação encaminhada pelo comercial',
            0
        ]);

        $documentoId = $db->lastInsertId();

        // Criar fluxo de aprovação sequencial usando PROCEDURE
        $stmtFluxo = $db->prepare("CALL criar_fluxo_desfiliacao(?, ?)");
        $stmtFluxo->execute([$documentoId, $associadoId]);

        $db->commit();

        error_log("[DESFILIACAO] Documento ID {$documentoId} criado para associado {$associadoId} - Fluxo sequencial iniciado");

        jsonResponse('success', 'Desfiliação enviada com sucesso!', [
            'documento_id' => $documentoId,
            'associado_id' => $associadoId,
            'associado_nome' => $associado['nome']
        ], 200);

    } catch (Exception $e) {
        $db->rollBack();
        @unlink($caminhoDestino);
        error_log("[DESFILIACAO] Erro na transação: " . $e->getMessage());
        // Expor detalhe temporariamente para debug
        jsonResponse('error', 'Erro ao processar desfiliação: ' . $e->getMessage(), null, 500);
    }

} catch (Exception $e) {
    error_log("[DESFILIACAO] Erro geral: " . $e->getMessage());
    jsonResponse('error', 'Erro no servidor: ' . $e->getMessage(), null, 500);
}
