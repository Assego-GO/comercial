<?php
/**
 * Visualizador de log Atacadão
 * pages/log_atacadao.php
 * Acesse: http://seu-dominio/pages/log_atacadao.php
 */

$logFile = __DIR__ . '/../logs/atacadao_integracao.log';
$logExists = file_exists($logFile);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Atacadão</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            margin-bottom: 20px;
        }
        .log-box {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 15px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.5;
        }
        .sucesso { color: #4ec9b0; }
        .erro { color: #f48771; }
        .info { color: #9cdcfe; }
        .timestamp { color: #858585; }
        .btn {
            background: #007acc;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .btn:hover {
            background: #005a9e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Log Atacadão</h1>
        <button class="btn" onclick="location.reload()">🔄 Atualizar</button>
        
        <?php if ($logExists): ?>
            <div class="log-box">
<?php
    $lines = file($logFile);
    $lines = array_slice($lines, -50); // Últimas 50 linhas
    
    foreach ($lines as $line) {
        $line = htmlspecialchars(trim($line));
        
        // Colorir com base no conteúdo
        if (strpos($line, '✅') !== false || strpos($line, 'SUCESSO') !== false) {
            echo '<span class="sucesso">' . $line . "</span>\n";
        } elseif (strpos($line, '❌') !== false || strpos($line, 'FALHA') !== false || strpos($line, '🚨') !== false) {
            echo '<span class="erro">' . $line . "</span>\n";
        } elseif (strpos($line, '[') === 0) {
            // Timestamp
            echo '<span class="timestamp">' . $line . "</span>\n";
        } else {
            echo '<span class="info">' . $line . "</span>\n";
        }
    }
?>
            </div>
        <?php else: ?>
            <p style="color: #858585;">📭 Nenhum log encontrado ainda. Cadastre um associado para gerar registros.</p>
            <p>Arquivo esperado: <?php echo $logFile; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
