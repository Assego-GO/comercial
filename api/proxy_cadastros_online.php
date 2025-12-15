<?php
/**
 * Proxy para API do Sistema Online
 * Evita problemas de CORS fazendo requisição server-side
 * api/proxy_cadastros_online.php
 * 
 * LOCALIZAÇÃO: Sistema 172 (INTERNO)
 * VERSÃO DEBUG - Com logs completos
 */

// ✅ ATIVAR RELATÓRIO DE ERROS COMPLETO
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar no output (vai pro log)
ini_set('log_errors', 1);

// ✅ HEADER ANTES DE QUALQUER OUTPUT
header('Content-Type: application/json; charset=utf-8');

// ✅ FUNÇÃO DE LOG SEGURA
function logDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[PROXY DEBUG {$timestamp}] {$message}");
}

// ✅ CAPTURAR TODOS OS ERROS PHP
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logDebug("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");
});

try {
    logDebug("===== INÍCIO DA REQUISIÇÃO =====");
    
    // ========================================================================
    // VERIFICAR ARQUIVOS DE CONFIGURAÇÃO
    // ========================================================================
    
    $configPath = __DIR__ . '/../config/config.php';
    $databasePath = __DIR__ . '/../config/database.php';
    $dbClassPath = __DIR__ . '/../classes/Database.php';
    $authClassPath = __DIR__ . '/../classes/Auth.php';
    
    logDebug("Verificando arquivos...");
    logDebug("Config: " . ($configPath ? 'OK' : 'FAIL'));
    logDebug("Database Config: " . ($databasePath ? 'OK' : 'FAIL'));
    logDebug("Database Class: " . ($dbClassPath ? 'OK' : 'FAIL'));
    logDebug("Auth Class: " . ($authClassPath ? 'OK' : 'FAIL'));
    
    // ✅ VERIFICAR SE CURL ESTÁ DISPONÍVEL
    if (!function_exists('curl_init')) {
        throw new Exception('Extensão cURL não está instalada no PHP');
    }
    logDebug("cURL: OK");
    
    // ✅ REQUIRE CONDICIONAL (evita erro fatal)
    if (file_exists($configPath)) {
        require_once $configPath;
        logDebug("Config carregado");
    } else {
        logDebug("AVISO: config.php não encontrado em {$configPath}");
    }
    
    if (file_exists($databasePath)) {
        require_once $databasePath;
        logDebug("Database config carregado");
    } else {
        logDebug("AVISO: database.php não encontrado");
    }
    
    if (file_exists($dbClassPath)) {
        require_once $dbClassPath;
        logDebug("Database class carregado");
    } else {
        logDebug("AVISO: Database.php class não encontrada");
    }
    
    if (file_exists($authClassPath)) {
        require_once $authClassPath;
        logDebug("Auth class carregado");
    } else {
        logDebug("AVISO: Auth.php class não encontrada");
    }
    
    // ========================================================================
    // VERIFICAR AUTENTICAÇÃO (OPCIONAL - pode comentar se der problema)
    // ========================================================================
    
    if (class_exists('Auth')) {
        $auth = new Auth();
        if (!$auth->isLoggedIn()) {
            logDebug("Usuário não autenticado");
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
            exit;
        }
        logDebug("Usuário autenticado: " . ($auth->getUser()['nome'] ?? 'N/A'));
    } else {
        logDebug("AVISO: Classe Auth não disponível - pulando verificação");
    }
    
    // ========================================================================
    // CONFIGURAÇÃO DA API EXTERNA
    // ========================================================================
    
    $apiBaseUrl = 'https://associe-se.assego.com.br/associar/api';
    $apiKey = 'assego_2025_e303e77ad524f7a9f59bcdaa9883bb72';
    
    logDebug("API Base URL: {$apiBaseUrl}");
    
    // ========================================================================
    // FAZER REQUISIÇÃO VIA CURL
    // ========================================================================
    
    $ch = curl_init();
    
    $url = "{$apiBaseUrl}/listar_cadastros.php?api_key={$apiKey}&limit=500";
    logDebug("URL completa: {$url}");
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'ASSEGO-Internal-System/1.0',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);
    
    logDebug("Iniciando requisição cURL...");
    $startTime = microtime(true);
    
    $response = curl_exec($ch);
    
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $curlInfo = curl_getinfo($ch);
    
    curl_close($ch);
    
    // ========================================================================
    // LOG COMPLETO DA RESPOSTA
    // ========================================================================
    
    logDebug("HTTP Code: {$httpCode}");
    logDebug("Tempo de resposta: {$duration}ms");
    logDebug("Response Length: " . strlen($response));
    logDebug("cURL Error Number: {$curlErrno}");
    
    if ($curlError) {
        logDebug("cURL Error: {$curlError}");
    }
    
    logDebug("Effective URL: " . ($curlInfo['url'] ?? 'N/A'));
    logDebug("Content Type: " . ($curlInfo['content_type'] ?? 'N/A'));
    logDebug("Redirect Count: " . ($curlInfo['redirect_count'] ?? '0'));
    
    // ========================================================================
    // VERIFICAR RESPOSTA
    // ========================================================================
    
    if ($curlErrno !== 0) {
        throw new Exception("Erro cURL #{$curlErrno}: {$curlError}");
    }
    
    if ($httpCode === 0) {
        throw new Exception("Não foi possível conectar à API externa. Verifique firewall/rede.");
    }
    
    if ($httpCode !== 200) {
        logDebug("Response Preview: " . substr($response, 0, 500));
        throw new Exception("API retornou HTTP {$httpCode}");
    }
    
    if (empty($response)) {
        throw new Exception("API retornou resposta vazia");
    }
    
    // ========================================================================
    // VALIDAR JSON
    // ========================================================================
    
    logDebug("Validando JSON...");
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $jsonError = json_last_error_msg();
        logDebug("JSON Error: {$jsonError}");
        logDebug("Response Preview: " . substr($response, 0, 1000));
        throw new Exception("Resposta não é JSON válido: {$jsonError}");
    }
    
    logDebug("JSON válido recebido");
    
    if (isset($data['status'])) {
        logDebug("Status da API: " . $data['status']);
    }
    
    if (isset($data['data']['cadastros'])) {
        $totalCadastros = count($data['data']['cadastros']);
        logDebug("Total de cadastros: {$totalCadastros}");
    }
    
    // ========================================================================
    // RETORNAR RESPOSTA
    // ========================================================================
    
    logDebug("===== REQUISIÇÃO CONCLUÍDA COM SUCESSO =====");
    
    http_response_code(200);
    echo json_encode($data);
    
} catch (Exception $e) {
    logDebug("===== ERRO NA REQUISIÇÃO =====");
    logDebug("Exception: " . $e->getMessage());
    logDebug("File: " . $e->getFile());
    logDebug("Line: " . $e->getLine());
    logDebug("Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'curl_version' => function_exists('curl_version') ? curl_version()['version'] : 'N/A',
            'api_url' => $apiBaseUrl ?? 'não definido',
            'http_code' => $httpCode ?? 0,
            'curl_error' => $curlError ?? 'nenhum',
            'curl_errno' => $curlErrno ?? 0,
            'effective_url' => $curlInfo['url'] ?? 'não disponível',
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}
?>