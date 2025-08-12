<?php
/**
 * API para listar documentos anexados
 * ../api/documentos/upload_documentos_listar.php
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

    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Verifica se tabela existe
    $stmt = $db->prepare("SHOW TABLES LIKE 'DocumentosFluxo'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'success', 'data' => [], 'total' => 0]);
        exit;
    }

    // Pega ID do associado
    $associadoId = isset($_GET['associado_id']) ? (int)$_GET['associado_id'] : 0;

    // Busca documentos
    $sql = "
        SELECT 
            df.*,
            a.nome as associado_nome,
            f.nome as funcionario_upload,
            CASE df.status_fluxo
                WHEN 'DIGITALIZADO' THEN 'Digitalizado'
                WHEN 'AGUARDANDO_ASSINATURA' THEN 'Aguardando Assinatura'
                WHEN 'ASSINADO' THEN 'Assinado'
                WHEN 'FINALIZADO' THEN 'Finalizado'
                ELSE df.status_fluxo
            END as status_descricao,
            DATEDIFF(NOW(), df.data_upload) as dias_em_processo
        FROM DocumentosFluxo df
        LEFT JOIN Associados a ON df.associado_id = a.id
        LEFT JOIN Funcionarios f ON df.funcionario_upload = f.id
        WHERE df.associado_id = ?
        ORDER BY df.data_upload DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$associadoId]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona informações extras
    foreach ($documentos as &$doc) {
        // Formata tamanho
        $bytes = $doc['tamanho_arquivo'];
        if ($bytes == 0) {
            $doc['tamanho_formatado'] = '0 B';
        } else {
            $k = 1024;
            $sizes = ['B', 'KB', 'MB', 'GB'];
            $i = floor(log($bytes) / log($k));
            $doc['tamanho_formatado'] = round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
        }

        // Verifica se arquivo existe
        $doc['arquivo_existe'] = file_exists('../../' . $doc['caminho_arquivo']);
        
        // Formata datas
        $doc['data_upload_formatada'] = date('d/m/Y H:i', strtotime($doc['data_upload']));
    }

    echo json_encode([
        'status' => 'success',
        'data' => $documentos,
        'total' => count($documentos)
    ]);

} catch (Exception $e) {
    error_log("Erro ao listar documentos: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro interno']);
}
?>