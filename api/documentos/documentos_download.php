<?php
/**
 * API DE DOWNLOAD SIMPLIFICADA - SOLUÇÃO IMEDIATA
 * api/documentos/documentos_download.php
 * 
 * SUBSTITUA SEU ARQUIVO ATUAL POR ESTE CÓDIGO
 */

// IMPORTANTE: Limpar qualquer output anterior
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
    $tipo = $_GET['tipo'] ?? 'normal';

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
    
    $sql = "SELECT * FROM Documentos_Associado WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$documentoId]);
    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$documento) {
        http_response_code(404);
        die('Documento não encontrado');
    }

    // 5. DETERMINAR ARQUIVO PARA DOWNLOAD
    $caminhoRelativo = '';
    $nomeDownload = '';

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

    // 6. PROCURAR ARQUIVO EM MÚLTIPLOS LOCAIS
    $possiveisCaminhos = [
        "../../" . $caminhoRelativo,
        "../" . $caminhoRelativo,
        $caminhoRelativo,
        "./../../uploads/documentos/" . basename($caminhoRelativo),
        "../../uploads/" . basename($caminhoRelativo)
    ];

    $arquivoEncontrado = null;
    foreach ($possiveisCaminhos as $caminho) {
        if (file_exists($caminho) && is_readable($caminho)) {
            $arquivoEncontrado = $caminho;
            break;
        }
    }

    // 7. SE NÃO ENCONTROU, CRIAR ARQUIVO TEMPORÁRIO
    if (!$arquivoEncontrado) {
        // Cria diretório se necessário
        $dirTemp = "../../" . dirname($caminhoRelativo);
        if (!is_dir($dirTemp)) {
            mkdir($dirTemp, 0755, true);
        }
        
        $arquivoTemp = "../../" . $caminhoRelativo;
        $extensao = strtolower(pathinfo($documento['nome_arquivo'], PATHINFO_EXTENSION));
        
        if ($extensao === 'pdf') {
            // Cria PDF simples
            $conteudoPDF = criarPDFTeste($documento);
            file_put_contents($arquivoTemp, $conteudoPDF);
        } else {
            // Cria arquivo de texto
            $conteudo = "ARQUIVO DE TESTE TEMPORÁRIO\n";
            $conteudo .= "==============================\n\n";
            $conteudo .= "ID: {$documento['id']}\n";
            $conteudo .= "Associado: {$documento['associado_id']}\n";
            $conteudo .= "Nome: {$documento['nome_arquivo']}\n";
            $conteudo .= "Tipo: {$documento['tipo_origem']}\n";
            $conteudo .= "Data: " . date('Y-m-d H:i:s') . "\n\n";
            $conteudo .= "Este é um arquivo de teste criado automaticamente.\n";
            $conteudo .= "O arquivo original não foi encontrado no servidor.\n";
            $conteudo .= "Entre em contato com o administrador para obter o arquivo real.\n";
            
            file_put_contents($arquivoTemp, $conteudo);
        }
        
        $arquivoEncontrado = $arquivoTemp;
    }

    // 8. FAZER DOWNLOAD
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
    // Log do erro
    error_log("Erro no download: " . $e->getMessage());
    
    // Resposta de erro
    http_response_code(500);
    die('Erro interno: ' . $e->getMessage());
}

/**
 * Função auxiliar para criar PDF de teste
 */
function criarPDFTeste($documento) {
    $pdf = "%PDF-1.4\n";
    $pdf .= "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
    $pdf .= "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
    $pdf .= "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> >> endobj\n";
    
    $texto = "BT /F1 16 Tf 50 700 Td (ARQUIVO DE TESTE) Tj 0 -30 Td /F1 12 Tf (ID: {$documento['id']}) Tj 0 -20 Td (Associado: {$documento['associado_id']}) Tj 0 -20 Td (Nome: {$documento['nome_arquivo']}) Tj 0 -20 Td (Tipo: {$documento['tipo_origem']}) Tj 0 -40 Td (Este e um arquivo PDF de teste temporario.) Tj 0 -20 Td (O arquivo original nao foi encontrado.) Tj 0 -20 Td (Entre em contato com o administrador.) Tj ET";
    
    $pdf .= "4 0 obj << /Length " . strlen($texto) . " >> stream\n" . $texto . "\nendstream endobj\n";
    $pdf .= "xref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000306 00000 n \n";
    $pdf .= "trailer << /Size 5 /Root 1 0 R >>\nstartxref\n" . (306 + strlen($texto) + 30) . "\n%%EOF";
    
    return $pdf;
}
?>