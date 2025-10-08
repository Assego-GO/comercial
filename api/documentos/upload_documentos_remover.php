<?php
/**
 * API para remover documentos anexados
 * ../api/documentos/upload_documentos_remover.php
 */

header('Content-Type: application/json');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
        exit;
    }

    // Só aceita POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
        exit;
    }

    // Pega dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $documentoId = isset($input['id']) ? (int)$input['id'] : 0;

    if (!$documentoId) {
        echo json_encode(['status' => 'error', 'message' => 'ID do documento não informado']);
        exit;
    }

    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Verifica se tabela existe
    $stmt = $db->prepare("SHOW TABLES LIKE 'DocumentosFluxo'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Tabela de documentos não encontrada']);
        exit;
    }

    // Busca o documento para verificar se existe e pegar caminho do arquivo
    $stmt = $db->prepare("
        SELECT df.*, a.nome as associado_nome
        FROM DocumentosFluxo df
        LEFT JOIN Associados a ON df.associado_id = a.id
        WHERE df.id = ?
    ");
    $stmt->execute([$documentoId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        echo json_encode(['status' => 'error', 'message' => 'Documento não encontrado']);
        exit;
    }

    // Verifica permissões (opcional - pode adicionar lógica específica aqui)
    $usuarioLogado = $auth->getUser();
    // Exemplo: só quem fez upload ou admin pode remover
    // if ($documento['funcionario_upload'] != $usuarioLogado['id'] && !$auth->isAdmin()) {
    //     echo json_encode(['status' => 'error', 'message' => 'Sem permissão para remover este documento']);
    //     exit;
    // }

    // Inicia transação
    $db->beginTransaction();

    try {
        // Remove o registro do banco
        $stmt = $db->prepare("DELETE FROM DocumentosFluxo WHERE id = ?");
        $stmt->execute([$documentoId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Falha ao remover registro do banco');
        }

        // Remove o arquivo físico se existir
        $caminhoCompleto = '../../' . $documento['caminho_arquivo'];
        if (file_exists($caminhoCompleto)) {
            if (!unlink($caminhoCompleto)) {
                // Log do erro mas não falha a operação (registro já foi removido)
                error_log("Aviso: Não foi possível remover arquivo físico: " . $caminhoCompleto);
            }
        }

        // Remove diretório se estiver vazio (opcional)
        $diretorio = dirname($caminhoCompleto);
        if (is_dir($diretorio) && count(scandir($diretorio)) == 2) { // só tem . e ..
            @rmdir($diretorio);
        }

        // Confirma transação
        $db->commit();

        // Log da ação
        error_log("Documento removido: ID {$documentoId} - {$documento['nome_arquivo']} - Usuário: " . $usuarioLogado['nome']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Documento removido com sucesso',
            'data' => [
                'documento_id' => $documentoId,
                'nome_arquivo' => $documento['nome_arquivo'],
                'associado_nome' => $documento['associado_nome']
            ]
        ]);

    } catch (Exception $e) {
        // Desfaz transação
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro ao remover documento: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Erro interno do servidor'
    ]);
}
?>