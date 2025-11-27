<?php
/**
 * API DE DOWNLOAD - DOCUMENTOS DE ASSOCIADOS E AGREGADOS
 * api/documentos/documentos_download.php
 * 
 * Uso:
 *   Associado: ?id=123
 *   Agregado:  ?id=456&tipo=agregado
 */

// Limpar qualquer output anterior
if (ob_get_level()) {
    ob_end_clean();
}

// Desabilitar display de erros para download
error_reporting(0);
ini_set('display_errors', 0);

try {
    // 1. VALIDAÇÃO BÁSICA
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        die('ID inválido');
    }

    $documentoId = intval($_GET['id']);
    $tipo = $_GET['tipo'] ?? 'normal'; // 'normal', 'presencial' ou 'agregado'

    // 2. CARREGAR CONFIGURAÇÕES
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // 3. VERIFICAR AUTENTICAÇÃO
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        die('Não autorizado');
    }

    // 4. BUSCAR DOCUMENTO NO BANCO
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // =============================================
    // AGREGADO - Busca na tabela Documentos_Agregado
    // =============================================
    if ($tipo === 'agregado') {
        $sql = "SELECT 
                    d.*,
                    a.nome AS pessoa_nome,
                    a.cpf AS pessoa_cpf,
                    a.id AS pessoa_id
                FROM Documentos_Agregado d
                LEFT JOIN Socios_Agregados a ON d.agregado_id = a.id
                WHERE d.id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$documentoId]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$documento) {
            http_response_code(404);
            die('Documento de agregado não encontrado');
        }

        // Montar nome do arquivo
        $cpfLimpo = preg_replace('/[^0-9]/', '', $documento['pessoa_cpf'] ?? '');
        $extensao = pathinfo($documento['caminho_arquivo'], PATHINFO_EXTENSION) ?: 'pdf';
        $nomeDownload = "ficha_agregado_{$cpfLimpo}_" . date('Ymd') . ".{$extensao}";
        
        $caminhoRelativo = $documento['caminho_arquivo'];
        
        // Caminhos possíveis para agregado
        $possiveisCaminhos = [
            "../../" . $caminhoRelativo,
            "../" . $caminhoRelativo,
            $caminhoRelativo,
            "../../uploads/documentos/agregados/" . $documento['pessoa_id'] . "/" . basename($caminhoRelativo),
            "../../uploads/fichas_agregados/" . basename($caminhoRelativo)
        ];
    }
    // =============================================
    // ASSOCIADO - Busca na tabela Documentos_Associado
    // =============================================
    else {
        $sql = "SELECT * FROM Documentos_Associado WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$documentoId]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$documento) {
            http_response_code(404);
            die('Documento não encontrado');
        }

        // Determinar arquivo e nome para download
        if ($tipo === 'presencial' && $documento['tipo_origem'] === 'FISICO') {
            $caminhoRelativo = $documento['caminho_arquivo'];
            $extensao = pathinfo($documento['nome_arquivo'], PATHINFO_EXTENSION);
            $nomeDownload = "ficha_presencial_" . $documento['associado_id'] . "_" . date('Ymd') . "." . $extensao;
        } else if (!empty($documento['arquivo_assinado'])) {
            $caminhoRelativo = $documento['arquivo_assinado'];
            $nomeDownload = "ficha_assinada_" . $documento['associado_id'] . "_" . date('Ymd') . ".pdf";
        } else {
            $caminhoRelativo = $documento['caminho_arquivo'];
            $extensao = pathinfo($documento['nome_arquivo'], PATHINFO_EXTENSION);
            $nomeDownload = "ficha_" . $documento['associado_id'] . "_" . date('Ymd') . "." . $extensao;
        }

        // Caminhos possíveis para associado
        $possiveisCaminhos = [
            "../../" . $caminhoRelativo,
            "../" . $caminhoRelativo,
            $caminhoRelativo,
            "./../../uploads/documentos/" . basename($caminhoRelativo),
            "../../uploads/" . basename($caminhoRelativo)
        ];
    }

    // 5. PROCURAR ARQUIVO EM MÚLTIPLOS LOCAIS
    $arquivoEncontrado = null;
    foreach ($possiveisCaminhos as $caminho) {
        if (file_exists($caminho) && is_readable($caminho)) {
            $arquivoEncontrado = $caminho;
            break;
        }
    }

    // 6. SE NÃO ENCONTROU
    if (!$arquivoEncontrado) {
        error_log("Arquivo não encontrado - Tipo: {$tipo}, Doc ID: {$documentoId}");
        error_log("Caminhos tentados: " . implode(", ", $possiveisCaminhos));
        
        http_response_code(404);
        die('Arquivo não encontrado no servidor');
    }

    // 7. FAZER DOWNLOAD
    $mimeType = 'application/octet-stream';
    $extensao = strtolower(pathinfo($arquivoEncontrado, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain'
    ];
    
    if (isset($mimeTypes[$extensao])) {
        $mimeType = $mimeTypes[$extensao];
    }

    // Headers para download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
    header('Content-Length: ' . filesize($arquivoEncontrado));
    header('Cache-Control: no-cache');
    header('Pragma: no-cache');

    // Enviar arquivo
    readfile($arquivoEncontrado);
    exit;

} catch (Exception $e) {
    error_log("Erro no download: " . $e->getMessage());
    http_response_code(500);
    die('Erro interno: ' . $e->getMessage());
}
?>