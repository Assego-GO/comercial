<?php
/**
 * API para download de documentos
 * ../api/documentos/upload_documentos_download.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
        exit;
    }

    // Pega ID do documento
    $documentoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$documentoId) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Busca documento
    $stmt = $db->prepare("
        SELECT df.*, a.nome as associado_nome
        FROM DocumentosFluxo df
        LEFT JOIN Associados a ON df.associado_id = a.id
        WHERE df.id = ?
    ");
    $stmt->execute([$documentoId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Documento não encontrado']);
        exit;
    }

    // Caminho do arquivo
    $caminhoArquivo = '../../' . $documento['caminho_arquivo'];

    if (!file_exists($caminhoArquivo)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Arquivo não encontrado']);
        exit;
    }

    // Nome para download
    $extensao = pathinfo($documento['nome_arquivo'], PATHINFO_EXTENSION);
    $nomeDownload = preg_replace('/[^a-zA-Z0-9_-]/', '_', $documento['associado_nome'] . '_' . $documento['tipo_descricao']) . '.' . $extensao;

    // Headers para download
    header('Content-Type: ' . $documento['tipo_mime']);
    header('Content-Length: ' . filesize($caminhoArquivo));
    header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');

    // Envia arquivo
    readfile($caminhoArquivo);
    exit;

} catch (Exception $e) {
    error_log("Erro no download: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Erro no download']);
}
?>