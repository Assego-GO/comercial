<?php
/**
 * API DOWNLOAD - AUTO-DETECÇÃO DE ESTRUTURA
 * api/documentos/documentos_download.php
 * Detecta estrutura automaticamente e funciona com arquivos reais
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

// Debug ativo
$debugMode = isset($_GET['debug']) || true;
$debugInfo = [];

function logDebug($message) {
    global $debugInfo, $debugMode;
    $debugInfo[] = date('H:i:s') . ' - ' . $message;
    if ($debugMode) {
        error_log("DOWNLOAD DEBUG: " . $message);
    }
}

function mostrarErro($titulo, $mensagem, $debug = []) {
    global $debugInfo;
    
    $allDebug = array_merge($debugInfo, $debug);
    
    if (isset($_GET['ajax']) || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $mensagem,
            'debug' => $allDebug
        ]);
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Download - <?php echo htmlspecialchars($titulo); ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-light">
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="card border-<?php echo strpos($titulo, 'Erro') !== false ? 'danger' : 'info'; ?>">
                            <div class="card-header bg-<?php echo strpos($titulo, 'Erro') !== false ? 'danger' : 'info'; ?> text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-<?php echo strpos($titulo, 'Erro') !== false ? 'exclamation-triangle' : 'info-circle'; ?> me-2"></i>
                                    <?php echo htmlspecialchars($titulo); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?php echo htmlspecialchars($mensagem); ?></p>
                                
                                <?php if (!empty($allDebug)): ?>
                                <details class="mt-3">
                                    <summary class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-bug me-1"></i>
                                        Ver Debug Detalhado (<?php echo count($allDebug); ?> itens)
                                    </summary>
                                    <div class="mt-3">
                                        <pre class="bg-light p-3 small border rounded" style="max-height: 500px; overflow-y: auto;"><?php 
                                            foreach ($allDebug as $item) {
                                                echo htmlspecialchars($item) . "\n";
                                            }
                                        ?></pre>
                                    </div>
                                </details>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <button onclick="history.back()" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-1"></i>
                                        Voltar
                                    </button>
                                    <?php if (strpos($titulo, 'Debug') === false): ?>
                                    <button onclick="window.location.href = window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'debug=1'" class="btn btn-info ms-2">
                                        <i class="fas fa-search me-1"></i>
                                        Ver Debug
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
    exit;
}

try {
    logDebug("=== DOWNLOAD COM AUTO-DETECÇÃO ===");
    logDebug("URL: " . $_SERVER['REQUEST_URI']);
    logDebug("Script: " . __FILE__);
    logDebug("Diretório atual: " . __DIR__);
    
    // Auto-detecção da estrutura do projeto
    logDebug("Detectando estrutura do projeto...");
    
    $possiveisRaizes = [
        dirname(dirname(__DIR__)),  // /luis/comercial/
        $_SERVER['DOCUMENT_ROOT'] . '/luis/comercial',
        realpath('../../'),
        dirname(dirname(dirname(__FILE__)))
    ];
    
    $projectRoot = null;
    foreach ($possiveisRaizes as $raiz) {
        if (is_dir($raiz)) {
            $projectRoot = $raiz;
            logDebug("✅ Raiz do projeto detectada: {$raiz}");
            break;
        }
    }
    
    if (!$projectRoot) {
        logDebug("❌ Não foi possível detectar a raiz do projeto");
        mostrarErro('Erro de Estrutura', 'Não foi possível detectar a estrutura do projeto.');
    }
    
    // Verificar autenticação
    logDebug("Verificando autenticação...");
    
    try {
        $auth = new Auth();
        if (!$auth->isLoggedIn()) {
            logDebug("❌ Usuário não autenticado");
            mostrarErro('Não Autorizado', 'Você precisa estar logado para acessar este arquivo.');
        }
        
        $user = $auth->getUser();
        logDebug("✅ Usuário autenticado: " . ($user['nome'] ?? 'Nome não disponível'));
        
        // Verificar permissões básicas
        $isDiretor = $auth->isDiretor();
        $departamento = $user['departamento_id'] ?? null;
        
        if (!$isDiretor && $departamento != 1) {
            logDebug("❌ Usuário sem permissão (diretor: {$isDiretor}, dept: {$departamento})");
            mostrarErro('Acesso Negado', 'Você não tem permissão para acessar este documento.');
        }
        
        logDebug("✅ Permissões verificadas (diretor: {$isDiretor}, dept: {$departamento})");
        
    } catch (Exception $e) {
        logDebug("❌ Erro na autenticação: " . $e->getMessage());
        mostrarErro('Erro de Autenticação', 'Erro ao verificar credenciais: ' . $e->getMessage());
    }
    
    // Verificar ID do documento
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        logDebug("❌ ID do documento não informado");
        mostrarErro('Parâmetro Inválido', 'ID do documento não foi informado. Use: ?id=NUMERO');
    }
    
    $documentoId = intval($_GET['id']);
    logDebug("📄 ID do documento: {$documentoId}");
    
    if ($documentoId <= 0) {
        logDebug("❌ ID inválido: {$_GET['id']}");
        mostrarErro('ID Inválido', 'O ID do documento deve ser um número válido.');
    }
    
    // Procurar arquivo em múltiplos locais
    logDebug("🔍 Procurando arquivo do documento {$documentoId}...");
    
    $locaisProcura = [
        $projectRoot . '/uploads/documentos/temp/',
        $projectRoot . '/uploads/documentos/',
        $projectRoot . '/uploads/',
        $projectRoot . '/arquivos/',
        $projectRoot . '/files/',
        $projectRoot . '/temp/'
    ];
    
    $arquivoEncontrado = null;
    $localEncontrado = null;
    
    foreach ($locaisProcura as $local) {
        if (!is_dir($local)) {
            logDebug("   ❌ Local não existe: {$local}");
            continue;
        }
        
        logDebug("   🔍 Procurando em: {$local}");
        
        // Procurar por padrão ficha_virtual_{ID}_*.pdf
        $pattern = $local . "ficha_virtual_{$documentoId}_*.pdf";
        $arquivos = glob($pattern);
        
        logDebug("   📋 Padrão: " . basename($pattern));
        logDebug("   📋 Arquivos encontrados: " . count($arquivos));
        
        if (!empty($arquivos)) {
            // Se múltiplos arquivos, pegar o mais recente
            if (count($arquivos) > 1) {
                usort($arquivos, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                logDebug("   ⚠️ Múltiplos arquivos encontrados, selecionando o mais recente");
            }
            
            $arquivoEncontrado = $arquivos[0];
            $localEncontrado = $local;
            logDebug("   ✅ Arquivo selecionado: " . basename($arquivoEncontrado));
            break;
        }
        
        // Se não encontrou com padrão específico, listar arquivos para debug
        $todosArquivos = glob($local . '*.pdf');
        if (!empty($todosArquivos)) {
            logDebug("   📂 Outros PDFs neste local: " . count($todosArquivos));
            $amostra = array_slice($todosArquivos, 0, 3);
            foreach ($amostra as $arquivo) {
                logDebug("      - " . basename($arquivo));
            }
        }
    }
    
    // Se não encontrou arquivo, tentar criar exemplo
    if (!$arquivoEncontrado) {
        logDebug("📄 Arquivo não encontrado, tentando criar exemplo...");
        
        $uploadsDir = $projectRoot . '/uploads/documentos/temp/';
        
        // Criar diretório se não existir
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
            logDebug("📂 Diretório criado: {$uploadsDir}");
        }
        
        $arquivoEncontrado = criarArquivoExemplo($documentoId, $uploadsDir);
        
        if ($arquivoEncontrado) {
            logDebug("✅ Arquivo exemplo criado com sucesso");
        } else {
            logDebug("❌ Falha ao criar arquivo exemplo");
            
            // Mostrar debug completo dos locais procurados
            $debugLocais = [];
            foreach ($locaisProcura as $local) {
                $status = is_dir($local) ? 'EXISTS' : 'NOT_EXISTS';
                $debugLocais[] = "{$local} - {$status}";
                
                if (is_dir($local)) {
                    $arquivos = glob($local . '*.pdf');
                    $debugLocais[] = "  - PDFs: " . count($arquivos);
                }
            }
            
            mostrarErro(
                'Arquivo Não Encontrado',
                "Não foi possível encontrar nem criar o arquivo para o documento ID {$documentoId}.",
                array_merge(['=== LOCAIS PROCURADOS ==='], $debugLocais)
            );
        }
    }
    
    // Buscar dados do documento
    logDebug("📋 Buscando dados do documento...");
    $dadosDocumento = buscarDadosDocumento($documentoId);
    
    // Se for debug, mostrar informações
    if (isset($_GET['debug'])) {
        $infoCompleta = [
            "=== INFORMAÇÕES COMPLETAS ===",
            "ID do documento: {$documentoId}",
            "Arquivo encontrado: " . ($arquivoEncontrado ? 'SIM' : 'NÃO'),
            "Local: {$localEncontrado}",
            "Arquivo: " . ($arquivoEncontrado ? basename($arquivoEncontrado) : 'N/A'),
            "Tamanho: " . ($arquivoEncontrado ? formatBytes(filesize($arquivoEncontrado)) : 'N/A'),
            "=== DADOS DO DOCUMENTO ===",
            "Nome: " . ($dadosDocumento['nome'] ?? 'N/A'),
            "CPF: " . ($dadosDocumento['cpf'] ?? 'N/A'),
            "Status: " . ($dadosDocumento['status'] ?? 'N/A')
        ];
        
        mostrarErro(
            'Debug - Informações do Download',
            'Debug executado com sucesso. Veja abaixo as informações detalhadas.',
            $infoCompleta
        );
    }
    
    // Fazer download
    if ($arquivoEncontrado && file_exists($arquivoEncontrado) && is_readable($arquivoEncontrado)) {
        fazerDownload($arquivoEncontrado, $dadosDocumento, $documentoId);
    } else {
        logDebug("❌ Arquivo não é acessível");
        mostrarErro('Arquivo Inacessível', 'O arquivo não pode ser lido ou não existe.');
    }
    
} catch (Exception $e) {
    logDebug("❌ Erro crítico: " . $e->getMessage());
    error_log("Erro crítico no download: " . $e->getMessage());
    
    mostrarErro(
        'Erro Interno',
        'Ocorreu um erro inesperado: ' . $e->getMessage(),
        ["Trace: " . $e->getTraceAsString()]
    );
}

// ===== FUNÇÕES AUXILIARES =====

function criarArquivoExemplo($documentoId, $uploadsDir) {
    $timestamp = time();
    $nomeArquivo = "ficha_virtual_{$documentoId}_{$timestamp}.pdf";
    $caminhoArquivo = $uploadsDir . $nomeArquivo;
    
    // Buscar dados do documento se disponível
    $dadosDoc = buscarDadosDocumento($documentoId);
    $nome = $dadosDoc['nome'] ?? "Associado ID {$documentoId}";
    $cpf = $dadosDoc['cpf'] ?? "000.000.000-" . str_pad($documentoId, 2, '0', STR_PAD_LEFT);
    
    $conteudoPDF = "%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj
4 0 obj<</Length 500>>stream
BT
/F1 16 Tf
50 720 Td
(ASSEGO - FICHA DE ASSOCIACAO VIRTUAL) Tj
0 -30 Td
/F1 12 Tf
(DOCUMENTO DE EXEMPLO) Tj
0 -40 Td
(ID: {$documentoId}) Tj
0 -20 Td
(Nome: " . substr($nome, 0, 40) . ") Tj
0 -20 Td
(CPF: {$cpf}) Tj
0 -20 Td
(Data de Criacao: " . date('d/m/Y H:i:s') . ") Tj
0 -20 Td
(Status: AGUARDANDO ASSINATURA) Tj
0 -20 Td
(Timestamp: {$timestamp}) Tj
0 -40 Td
(Este arquivo foi criado automaticamente pelo sistema) Tj
0 -15 Td
(pois o arquivo original nao foi encontrado.) Tj
0 -30 Td
(Quando o sistema de geracao de fichas estiver) Tj
0 -15 Td
(funcionando, este arquivo sera substituido) Tj
0 -15 Td
(pelo documento real do associado.) Tj
0 -30 Td
(Para suporte, contate a TI.) Tj
ET
endstream
endobj
5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj
xref
0 6
0000000000 65535 f 
0000000010 00000 n 
0000000060 00000 n 
0000000120 00000 n 
0000000250 00000 n 
0000000800 00000 n 
trailer<</Size 6/Root 1 0 R>>
startxref
860
%%EOF";
    
    if (file_put_contents($caminhoArquivo, $conteudoPDF)) {
        logDebug("✅ Arquivo exemplo criado: {$nomeArquivo}");
        return $caminhoArquivo;
    } else {
        logDebug("❌ Falha ao criar arquivo exemplo");
        return null;
    }
}

function buscarDadosDocumento($documentoId) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $tabelasParaTestar = [
            'documentos' => ['nome' => 'associado_nome', 'cpf' => 'associado_cpf'],
            'associados' => ['nome' => 'nome', 'cpf' => 'cpf'],
            'filiacao' => ['nome' => 'nome_associado', 'cpf' => 'cpf_associado']
        ];
        
        foreach ($tabelasParaTestar as $tabela => $campos) {
            try {
                $stmt = $db->prepare("SELECT * FROM `{$tabela}` WHERE id = ? LIMIT 1");
                $stmt->execute([$documentoId]);
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($resultado) {
                    logDebug("✅ Dados encontrados na tabela: {$tabela}");
                    return [
                        'nome' => $resultado[$campos['nome']] ?? "Documento ID {$documentoId}",
                        'cpf' => $resultado[$campos['cpf']] ?? '',
                        'status' => $resultado['status'] ?? 'AGUARDANDO_ASSINATURA'
                    ];
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        logDebug("⚠️ Dados não encontrados no banco");
        
    } catch (Exception $e) {
        logDebug("⚠️ Erro ao buscar dados: " . $e->getMessage());
    }
    
    return [
        'nome' => "Documento ID {$documentoId}",
        'cpf' => '',
        'status' => 'AGUARDANDO_ASSINATURA'
    ];
}

function fazerDownload($arquivo, $dados, $documentoId) {
    logDebug("📤 Iniciando download...");
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $tamanho = filesize($arquivo);
    $nomeDownload = 'ficha_associacao_' . $documentoId . '.pdf';
    
    if (!empty($dados['nome']) && $dados['nome'] !== "Documento ID {$documentoId}") {
        $nomeDownload = sanitizarNome($dados['nome']) . '_ficha.pdf';
    }
    
    logDebug("   📄 Arquivo: " . basename($arquivo));
    logDebug("   📏 Tamanho: " . formatBytes($tamanho));
    logDebug("   💾 Nome download: {$nomeDownload}");
    
    header('Content-Type: application/pdf');
    header('Content-Length: ' . $tamanho);
    header('Content-Disposition: inline; filename="' . $nomeDownload . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    readfile($arquivo);
    
    logDebug("✅ Download concluído");
}

function sanitizarNome($nome) {
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    $nome = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $nome);
    $nome = preg_replace('/_+/', '_', $nome);
    return trim($nome, '_') ?: 'documento';
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>