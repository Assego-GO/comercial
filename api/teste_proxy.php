<?php
/**
 * Script de Teste e Diagnóstico
 * api/teste_proxy.php
 * 
 * ACESSE: http://assego.ddns.net:9994/matheus/comercial/api/teste_proxy.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Proxy - ASSEGO</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .section {
            background: #2d2d2d;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        h1, h2 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .success {
            color: #4ec9b0;
            font-weight: bold;
        }
        .error {
            color: #f48771;
            font-weight: bold;
        }
        .warning {
            color: #dcdcaa;
            font-weight: bold;
        }
        pre {
            background: #1e1e1e;
            border: 1px solid #444;
            padding: 15px;
            overflow-x: auto;
            border-radius: 4px;
        }
        .info {
            color: #569cd6;
        }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico do Proxy - ASSEGO</h1>
    <p><strong>Data/Hora:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
    <p><strong>Servidor:</strong> <?php echo $_SERVER['SERVER_NAME'] ?? 'N/A'; ?></p>

    <!-- TESTE 1: CONFIGURAÇÃO PHP -->
    <div class="section">
        <h2>📋 1. Configuração do PHP</h2>
        <p><strong>Versão PHP:</strong> <span class="info"><?php echo PHP_VERSION; ?></span></p>
        <p><strong>cURL Disponível:</strong> 
            <?php 
            if (function_exists('curl_init')) {
                echo '<span class="success">✅ SIM</span>';
                $curlVersion = curl_version();
                echo '<br><strong>Versão cURL:</strong> ' . $curlVersion['version'];
                echo '<br><strong>SSL:</strong> ' . $curlVersion['ssl_version'];
            } else {
                echo '<span class="error">❌ NÃO INSTALADO</span>';
            }
            ?>
        </p>
        <p><strong>JSON Disponível:</strong> 
            <?php echo function_exists('json_encode') ? '<span class="success">✅ SIM</span>' : '<span class="error">❌ NÃO</span>'; ?>
        </p>
        <p><strong>PDO Disponível:</strong> 
            <?php echo class_exists('PDO') ? '<span class="success">✅ SIM</span>' : '<span class="error">❌ NÃO</span>'; ?>
        </p>
    </div>

    <!-- TESTE 2: ARQUIVOS DO SISTEMA -->
    <div class="section">
        <h2>📁 2. Arquivos do Sistema</h2>
        <?php
        $basePath = dirname(__DIR__);
        $files = [
            'Config' => $basePath . '/config/config.php',
            'Database Config' => $basePath . '/config/database.php',
            'Database Class' => $basePath . '/classes/Database.php',
            'Auth Class' => $basePath . '/classes/Auth.php'
        ];
        
        foreach ($files as $label => $path) {
            $exists = file_exists($path);
            echo "<p><strong>{$label}:</strong> ";
            if ($exists) {
                echo '<span class="success">✅ Encontrado</span>';
                echo "<br><small style='color: #888;'>{$path}</small>";
            } else {
                echo '<span class="error">❌ Não encontrado</span>';
                echo "<br><small style='color: #888;'>{$path}</small>";
            }
            echo "</p>";
        }
        ?>
    </div>

    <!-- TESTE 3: CONEXÃO COM API EXTERNA -->
    <div class="section">
        <h2>🌐 3. Teste de Conexão com API Externa</h2>
        <?php
        if (function_exists('curl_init')) {
            $apiUrl = 'https://associe-se.assego.com.br/associar/api/listar_cadastros.php';
            $apiKey = 'assego_2025_e303e77ad524f7a9f59bcdaa9883bb72';
            $fullUrl = "{$apiUrl}?api_key={$apiKey}&limit=5";
            
            echo "<p><strong>URL Testada:</strong><br><code style='color: #569cd6;'>{$fullUrl}</code></p>";
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $fullUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $startTime = microtime(true);
            $response = curl_exec($ch);
            $endTime = microtime(true);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $curlInfo = curl_getinfo($ch);
            
            curl_close($ch);
            
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            echo "<p><strong>HTTP Code:</strong> ";
            if ($httpCode == 200) {
                echo '<span class="success">✅ ' . $httpCode . ' OK</span>';
            } else {
                echo '<span class="error">❌ ' . ($httpCode ?: 'FALHA NA CONEXÃO') . '</span>';
            }
            echo "</p>";
            
            echo "<p><strong>Tempo de Resposta:</strong> <span class='info'>{$duration}ms</span></p>";
            
            if ($curlErrno !== 0) {
                echo "<p><strong>Erro cURL:</strong> <span class='error'>#{$curlErrno} - {$curlError}</span></p>";
            } else {
                echo "<p><strong>Erro cURL:</strong> <span class='success'>Nenhum</span></p>";
            }
            
            echo "<p><strong>Tamanho da Resposta:</strong> <span class='info'>" . strlen($response) . " bytes</span></p>";
            echo "<p><strong>Content-Type:</strong> <span class='info'>" . ($curlInfo['content_type'] ?? 'N/A') . "</span></p>";
            
            if ($httpCode == 200 && !empty($response)) {
                $jsonData = json_decode($response, true);
                
                echo "<p><strong>JSON Válido:</strong> ";
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo '<span class="success">✅ SIM</span>';
                    
                    if (isset($jsonData['status'])) {
                        echo "<br><strong>Status API:</strong> <span class='info'>{$jsonData['status']}</span>";
                    }
                    
                    if (isset($jsonData['data']['cadastros'])) {
                        $total = count($jsonData['data']['cadastros']);
                        echo "<br><strong>Cadastros Retornados:</strong> <span class='info'>{$total}</span>";
                    }
                    
                    echo "<br><br><strong>Preview do JSON:</strong>";
                    echo "<pre>" . htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                    
                } else {
                    echo '<span class="error">❌ NÃO - ' . json_last_error_msg() . '</span>';
                    echo "<br><br><strong>Preview da Resposta:</strong>";
                    echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre>";
                }
            } elseif (!empty($response)) {
                echo "<br><br><strong>Resposta (primeiros 1000 caracteres):</strong>";
                echo "<pre>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre>";
            }
            
        } else {
            echo '<p class="error">❌ cURL não está disponível - não é possível testar</p>';
        }
        ?>
    </div>

    <!-- TESTE 4: VARIÁVEIS DE AMBIENTE -->
    <div class="section">
        <h2>⚙️ 4. Variáveis de Ambiente</h2>
        <pre><?php
        $envVars = [
            'SERVER_SOFTWARE' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? 'N/A',
            'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'N/A',
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'N/A',
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'N/A'
        ];
        
        foreach ($envVars as $key => $value) {
            echo str_pad($key . ':', 20) . $value . "\n";
        }
        ?></pre>
    </div>

    <!-- TESTE 5: PERMISSÕES -->
    <div class="section">
        <h2>🔐 5. Permissões de Diretórios</h2>
        <?php
        $dirs = [
            'Diretório Atual' => __DIR__,
            'Diretório Pai' => dirname(__DIR__),
            'Config' => dirname(__DIR__) . '/config',
            'Classes' => dirname(__DIR__) . '/classes'
        ];
        
        foreach ($dirs as $label => $path) {
            echo "<p><strong>{$label}:</strong> ";
            if (is_dir($path)) {
                $readable = is_readable($path) ? '✅ Leitura' : '❌ Sem leitura';
                $writable = is_writable($path) ? '✅ Escrita' : '⚠️ Sem escrita';
                echo "<span class='success'>Existe</span> - {$readable} - {$writable}";
                echo "<br><small style='color: #888;'>{$path}</small>";
            } else {
                echo '<span class="error">❌ Não existe</span>';
            }
            echo "</p>";
        }
        ?>
    </div>

    <!-- RECOMENDAÇÕES -->
    <div class="section">
        <h2>💡 Recomendações</h2>
        <?php
        $issues = [];
        
        if (!function_exists('curl_init')) {
            $issues[] = "❌ Instalar extensão cURL: <code>apt-get install php-curl</code> ou <code>yum install php-curl</code>";
        }
        
        if (!file_exists(dirname(__DIR__) . '/config/config.php')) {
            $issues[] = "❌ Arquivo config.php não encontrado - verificar caminho";
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $issues[] = "⚠️ PHP versão muito antiga (" . PHP_VERSION . ") - considerar atualizar para 7.4+";
        }
        
        if (empty($issues)) {
            echo '<p class="success">✅ Tudo configurado corretamente!</p>';
        } else {
            echo '<ul>';
            foreach ($issues as $issue) {
                echo "<li>{$issue}</li>";
            }
            echo '</ul>';
        }
        ?>
    </div>

    <p style="text-align: center; color: #888; margin-top: 40px;">
        <small>ASSEGO - Sistema de Diagnóstico v1.0</small>
    </p>
</body>
</html>