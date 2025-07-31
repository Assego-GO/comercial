<?php
/**
 * API DOWNLOAD - BUSCA ARQUIVOS REAIS
 * api/documentos/documentos_download.php
 * Localização: uploads/documentos/temp/ficha_virtual_{ID}_{TIMESTAMP}.pdf
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
            <title>Erro - Download</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-light">
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
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
                                        <pre class="bg-light p-3 small border rounded" style="max-height: 400px; overflow-y: auto;"><?php 
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
                                    <button onclick="window.location.href = window.location.href.split('?')[0] + '?id=<?php echo $_GET['id'] ?? '1'; ?>&debug=1'" class="btn btn-info ms-2">
                                        <i class="fas fa-search me-1"></i>
                                        Ver Debug
                                    </button>
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
    logDebug("=== DOWNLOAD DE FICHAS REAIS ===");
    logDebug("URL: " . $_SERVER['REQUEST_URI']);
    logDebug("Servidor: " . $_SERVER['DOCUMENT_ROOT']);
    logDebug("Script: " . __FILE__);
    
    // Detectar estrutura do projeto
    $projectRoot = dirname(dirname(__DIR__)); // /luis/comercial/
    $uploadsDir = $projectRoot . '/uploads/documentos/temp/';
    logDebug("Raiz do projeto: " . $projectRoot);
    logDebug("Diretório de uploads: " . $uploadsDir);
    
    // Verificar se diretório existe
    if (!is_dir($uploadsDir)) {
        logDebug("❌ Diretório de uploads não existe: " . $uploadsDir);
        mostrarErro('Diretório Não Encontrado', 'O diretório de documentos não foi encontrado no servidor.');
    } else {
        logDebug("✅ Diretório de uploads existe");
    }
    
    // Verificar autenticação
    $usuarioAutenticado = null;
    try {
        $auth = new Auth();
        if ($auth->isLoggedIn()) {
            $usuarioAutenticado = $auth->getUser();
            logDebug("✅ Usuário autenticado: " . ($usuarioAutenticado['nome'] ?? 'Nome não disponível'));
            
            // Verificar permissões básicas
            $isDiretor = $auth->isDiretor();
            $departamento = $usuarioAutenticado['departamento_id'] ?? null;
            logDebug("   - É diretor: " . ($isDiretor ? 'SIM' : 'NÃO'));
            logDebug("   - Departamento: " . $departamento);
            
            // Permitir acesso se for diretor OU departamento 1 (presidência)
            if (!$isDiretor && $departamento != 1) {
                logDebug("❌ Usuário sem permissão para download");
                mostrarErro('Acesso Negado', 'Você não tem permissão para acessar este documento.');
            }
        } else {
            logDebug("❌ Usuário não autenticado");
            mostrarErro('Não Autorizado', 'Você precisa estar logado para acessar este arquivo.');
        }
    } catch (Exception $e) {
        logDebug("❌ Erro na autenticação: " . $e->getMessage());
        mostrarErro('Erro de Autenticação', 'Erro ao verificar credenciais.');
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
    
    // Procurar arquivo com padrão ficha_virtual_{ID}_{TIMESTAMP}.pdf
    logDebug("🔍 Procurando arquivo com padrão: ficha_virtual_{$documentoId}_*.pdf");
    
    $arquivoEncontrado = procurarFichaVirtual($uploadsDir, $documentoId);
    
    if (!$arquivoEncontrado) {
        // Listar todos os arquivos do diretório para debug
        $arquivosExistentes = listarArquivosDisponeis($uploadsDir);
        logDebug("📂 Arquivos disponíveis no diretório:");
        foreach ($arquivosExistentes as $arquivo) {
            logDebug("   - " . $arquivo);
        }
        
        $debugExtra = [
            "=== ARQUIVO NÃO ENCONTRADO ===",
            "ID procurado: {$documentoId}",
            "Padrão procurado: ficha_virtual_{$documentoId}_*.pdf",
            "Diretório: {$uploadsDir}",
            "Arquivos encontrados: " . count($arquivosExistentes),
            "Lista de arquivos:",
            ...array_map(fn($f) => "  - " . $f, array_slice($arquivosExistentes, 0, 20))
        ];
        
        if (count($arquivosExistentes) > 20) {
            $debugExtra[] = "  ... e mais " . (count($arquivosExistentes) - 20) . " arquivos";
        }
        
        mostrarErro(
            'Ficha Não Encontrada',
            "Não foi possível encontrar a ficha virtual para o documento ID {$documentoId}. " .
            "Verifique se o arquivo foi gerado corretamente.",
            $debugExtra
        );
    }
    
    // Buscar dados do documento (se disponível)
    $dadosDocumento = buscarDadosDocumento($documentoId);
    
    // Se for debug, mostrar informações sem fazer download
    if (isset($_GET['debug'])) {
        $infoArquivo = [
            "=== ARQUIVO ENCONTRADO ===",
            "ID: {$documentoId}",
            "Arquivo: " . basename($arquivoEncontrado),
            "Caminho completo: {$arquivoEncontrado}",
            "Tamanho: " . formatBytes(filesize($arquivoEncontrado)),
            "Data modificação: " . date('d/m/Y H:i:s', filemtime($arquivoEncontrado)),
            "Permissões: " . (is_readable($arquivoEncontrado) ? 'Legível' : 'Não legível'),
            "=== DADOS DO DOCUMENTO ===",
            "Nome: " . ($dadosDocumento['nome'] ?? 'Não disponível'),
            "CPF: " . ($dadosDocumento['cpf'] ?? 'Não disponível'),
            "Status: " . ($dadosDocumento['status'] ?? 'Não disponível')
        ];
        
        mostrarErro(
            'Debug - Informações do Download',
            'Debug executado com sucesso. O arquivo foi encontrado e o download funcionaria normalmente.',
            $infoArquivo
        );
    }
    
    // Fazer download do arquivo
    if (file_exists($arquivoEncontrado) && is_readable($arquivoEncontrado)) {
        fazerDownloadArquivo($arquivoEncontrado, $dadosDocumento, $documentoId);
    } else {
        logDebug("❌ Arquivo não é legível ou não existe");
        mostrarErro('Arquivo Inacessível', 'O arquivo foi encontrado mas não pode ser lido.');
    }
    
} catch (Exception $e) {
    logDebug("❌ Erro crítico: " . $e->getMessage());
    logDebug("   Arquivo: " . $e->getFile() . " linha " . $e->getLine());
    error_log("Erro crítico no download: " . $e->getMessage());
    
    mostrarErro(
        'Erro Interno',
        'Ocorreu um erro inesperado: ' . $e->getMessage(),
        ["Stack trace: " . $e->getTraceAsString()]
    );
}

// ===== FUNÇÕES AUXILIARES =====

function procurarFichaVirtual($uploadsDir, $documentoId) {
    logDebug("🔍 Iniciando busca por ficha virtual...");
    
    // Padrão: ficha_virtual_{ID}_*.pdf
    $pattern = $uploadsDir . "ficha_virtual_{$documentoId}_*.pdf";
    logDebug("   Padrão glob: " . $pattern);
    
    $arquivos = glob($pattern);
    logDebug("   Arquivos encontrados com glob: " . count($arquivos));
    
    if (!empty($arquivos)) {
        // Se encontrou múltiplos, pegar o mais recente
        if (count($arquivos) > 1) {
            logDebug("   ⚠️ Múltiplos arquivos encontrados, selecionando o mais recente");
            usort($arquivos, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
        }
        
        $arquivoSelecionado = $arquivos[0];
        logDebug("✅ Arquivo selecionado: " . basename($arquivoSelecionado));
        return $arquivoSelecionado;
    }
    
    // Se glob não funcionou, fazer busca manual
    logDebug("   Glob não encontrou, fazendo busca manual...");
    
    if (!is_dir($uploadsDir)) {
        logDebug("   ❌ Diretório não existe para busca manual");
        return null;
    }
    
    $handle = opendir($uploadsDir);
    $arquivosEncontrados = [];
    
    while (($arquivo = readdir($handle)) !== false) {
        if ($arquivo == '.' || $arquivo == '..') continue;
        
        // Verificar padrão ficha_virtual_{ID}_
        if (preg_match("/^ficha_virtual_{$documentoId}_\d+\.pdf$/", $arquivo)) {
            $caminhoCompleto = $uploadsDir . $arquivo;
            $arquivosEncontrados[] = $caminhoCompleto;
            logDebug("   📄 Encontrado: " . $arquivo);
        }
    }
    closedir($handle);
    
    if (empty($arquivosEncontrados)) {
        logDebug("   ❌ Nenhum arquivo encontrado na busca manual");
        return null;
    }
    
    // Se encontrou múltiplos na busca manual, pegar o mais recente
    if (count($arquivosEncontrados) > 1) {
        usort($arquivosEncontrados, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
    }
    
    logDebug("✅ Arquivo encontrado na busca manual: " . basename($arquivosEncontrados[0]));
    return $arquivosEncontrados[0];
}

function listarArquivosDisponeis($uploadsDir) {
    $arquivos = [];
    
    if (!is_dir($uploadsDir)) {
        return $arquivos;
    }
    
    $handle = opendir($uploadsDir);
    while (($arquivo = readdir($handle)) !== false) {
        if ($arquivo != '.' && $arquivo != '..' && str_ends_with($arquivo, '.pdf')) {
            $arquivos[] = $arquivo;
        }
    }
    closedir($handle);
    
    sort($arquivos);
    return $arquivos;
}

function buscarDadosDocumento($documentoId) {
    // Tentar buscar dados do documento no banco (se disponível)
    try {
        $db = Database::getInstance()->getConnection();
        
        // Tentar diferentes tabelas que podem conter dados do documento
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
                        'nome' => $resultado[$campos['nome']] ?? 'Nome não disponível',
                        'cpf' => $resultado[$campos['cpf']] ?? 'CPF não disponível',
                        'status' => $resultado['status'] ?? 'PENDENTE',
                        'data' => $resultado['data_upload'] ?? $resultado['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            } catch (Exception $e) {
                // Tabela não existe ou erro - continuar tentando
                continue;
            }
        }
        
        logDebug("⚠️ Dados não encontrados no banco para ID {$documentoId}");
        
    } catch (Exception $e) {
        logDebug("⚠️ Erro ao buscar dados no banco: " . $e->getMessage());
    }
    
    // Retornar dados básicos se não encontrou no banco
    return [
        'nome' => "Documento ID {$documentoId}",
        'cpf' => 'Não disponível',
        'status' => 'AGUARDANDO_ASSINATURA',
        'data' => date('Y-m-d H:i:s')
    ];
}

function fazerDownloadArquivo($caminhoArquivo, $dadosDocumento, $documentoId) {
    logDebug("📤 Iniciando download do arquivo...");
    
    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $tamanhoArquivo = filesize($caminhoArquivo);
    $nomeOriginal = basename($caminhoArquivo);
    
    // Nome para download (mais amigável)
    $nomeDownload = 'ficha_associacao_' . $documentoId . '.pdf';
    if (!empty($dadosDocumento['nome']) && $dadosDocumento['nome'] !== "Documento ID {$documentoId}") {
        $nomeDownload = sanitizarNome($dadosDocumento['nome']) . '_ficha_associacao.pdf';
    }
    
    logDebug("   - Arquivo: {$nomeOriginal}");
    logDebug("   - Tamanho: " . formatBytes($tamanhoArquivo));
    logDebug("   - Nome download: {$nomeDownload}");
    
    // Headers para download
    header('Content-Type: application/pdf');
    header('Content-Length: ' . $tamanhoArquivo);
    header('Content-Disposition: inline; filename="' . $nomeDownload . '"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Accept-Ranges: bytes');
    
    // Verificar se é requisição de range (para PDFs grandes)
    if (isset($_SERVER['HTTP_RANGE'])) {
        logDebug("   📥 Processando requisição de range: " . $_SERVER['HTTP_RANGE']);
        servirComRange($caminhoArquivo, $tamanhoArquivo);
    } else {
        logDebug("   📥 Enviando arquivo completo...");
        readfile($caminhoArquivo);
    }
    
    logDebug("✅ Download concluído com sucesso");
}

function servirComRange($arquivo, $tamanho) {
    $inicio = 0;
    $fim = $tamanho - 1;
    
    if (isset($_SERVER['HTTP_RANGE'])) {
        if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
            $inicio = intval($matches[1]);
            if (!empty($matches[2])) {
                $fim = intval($matches[2]);
            }
        }
    }
    
    if ($inicio > 0 || $fim < ($tamanho - 1)) {
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes {$inicio}-{$fim}/{$tamanho}");
        header('Content-Length: ' . ($fim - $inicio + 1));
    }
    
    $fp = fopen($arquivo, 'rb');
    fseek($fp, $inicio);
    
    $buffer = 8192;
    $pos = $inicio;
    
    while (!feof($fp) && $pos <= $fim && connection_status() == 0) {
        $tamanhoLeitura = min($buffer, $fim - $pos + 1);
        echo fread($fp, $tamanhoLeitura);
        $pos += $tamanhoLeitura;
        flush();
    }
    
    fclose($fp);
}

function sanitizarNome($nome) {
    // Remove acentos e caracteres especiais
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
    $nome = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $nome);
    $nome = preg_replace('/_+/', '_', $nome);
    $nome = trim($nome, '_');
    
    return $nome ?: 'documento';
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>