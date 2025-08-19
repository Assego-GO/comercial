<?php
/**
 * DIAGNÓSTICO COMPLETO DE DOWNLOAD
 * api/documentos/diagnostico_download.php
 * 
 * Execute este script para identificar o problema
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico de Download</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .ok { color: green; }
        .erro { color: red; }
        .aviso { color: orange; }
        .section { margin: 20px 0; padding: 10px; border: 1px solid #ccc; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Download de Documentos</h1>
    
    <?php
    echo "<div class='section'>";
    echo "<h2>1. Teste Básico PHP</h2>";
    echo "<span class='ok'>✓ PHP está funcionando</span><br>";
    echo "Versão PHP: " . phpversion() . "<br>";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "<br>";
    echo "</div>";

    echo "<div class='section'>";
    echo "<h2>2. Teste de Caminhos</h2>";
    echo "Diretório atual: " . __DIR__ . "<br>";
    echo "Caminho para config: " . realpath('../../config/config.php') . "<br>";
    echo "</div>";

    echo "<div class='section'>";
    echo "<h2>3. Teste de Conexão com Banco</h2>";
    try {
        require_once '../../config/config.php';
        require_once '../../config/database.php';
        require_once '../../classes/Database.php';
        
        echo "<span class='ok'>✓ Arquivos de configuração carregados</span><br>";
        
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        echo "<span class='ok'>✓ Conexão com banco estabelecida</span><br>";
        
        // Testa query
        $sql = "SELECT COUNT(*) as total FROM Documentos_Associado";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Total de documentos na base: " . $result['total'] . "<br>";
        
    } catch (Exception $e) {
        echo "<span class='erro'>❌ Erro de banco: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";

    echo "<div class='section'>";
    echo "<h2>4. Teste de Documentos</h2>";
    try {
        // Busca alguns documentos para teste
        $sql = "SELECT id, nome_arquivo, caminho_arquivo, tipo_origem FROM Documentos_Associado LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Documentos encontrados: " . count($documentos) . "<br><br>";
        
        foreach ($documentos as $doc) {
            echo "<strong>ID: {$doc['id']}</strong><br>";
            echo "Nome: {$doc['nome_arquivo']}<br>";
            echo "Caminho: {$doc['caminho_arquivo']}<br>";
            echo "Tipo: {$doc['tipo_origem']}<br>";
            
            $caminhoCompleto = "../../" . $doc['caminho_arquivo'];
            if (file_exists($caminhoCompleto)) {
                $tamanho = filesize($caminhoCompleto);
                echo "<span class='ok'>✓ Arquivo existe ({$tamanho} bytes)</span><br>";
                echo "<a href='documentos_download_teste.php?id={$doc['id']}' target='_blank'>🔗 Testar download</a><br>";
            } else {
                echo "<span class='erro'>❌ Arquivo não encontrado: {$caminhoCompleto}</span><br>";
            }
            echo "<hr>";
        }
        
    } catch (Exception $e) {
        echo "<span class='erro'>❌ Erro ao buscar documentos: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";

    echo "<div class='section'>";
    echo "<h2>5. Teste de Permissões</h2>";
    $uploadDir = "../../uploads/documentos/";
    if (is_dir($uploadDir)) {
        echo "<span class='ok'>✓ Diretório uploads existe</span><br>";
        if (is_readable($uploadDir)) {
            echo "<span class='ok'>✓ Diretório é legível</span><br>";
        } else {
            echo "<span class='erro'>❌ Diretório não é legível</span><br>";
        }
    } else {
        echo "<span class='erro'>❌ Diretório uploads não encontrado: {$uploadDir}</span><br>";
    }
    echo "</div>";

    echo "<div class='section'>";
    echo "<h2>6. Teste de Headers HTTP</h2>";
    echo "Método HTTP: " . $_SERVER['REQUEST_METHOD'] . "<br>";
    echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Não definido') . "<br>";
    echo "Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Não definido') . "<br>";
    echo "</div>";

    if (isset($_GET['test_download'])) {
        echo "<div class='section'>";
        echo "<h2>7. Teste de Download Real</h2>";
        
        // Cria um arquivo de teste
        $testFile = "/tmp/teste_download.txt";
        file_put_contents($testFile, "Este é um arquivo de teste criado em " . date('Y-m-d H:i:s'));
        
        if (file_exists($testFile)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="teste_download.txt"');
            header('Content-Length: ' . filesize($testFile));
            readfile($testFile);
            unlink($testFile);
            exit;
        }
        echo "</div>";
    } else {
        echo "<div class='section'>";
        echo "<h2>7. Teste de Download</h2>";
        echo "<a href='?test_download=1'>🔽 Testar download de arquivo simples</a><br>";
        echo "</div>";
    }
    ?>

    <div class='section'>
        <h2>8. Soluções Possíveis</h2>
        <p><strong>Se você está baixando HTML em vez do arquivo:</strong></p>
        <ul>
            <li>Verifique se o servidor está executando PHP (deve mostrar ✓ acima)</li>
            <li>Verifique se os caminhos dos arquivos estão corretos</li>
            <li>Verifique as permissões dos arquivos/diretórios</li>
            <li>Tente usar a API de teste: <code>documentos_download_teste.php</code></li>
        </ul>
        
        <p><strong>Se os arquivos não existem:</strong></p>
        <ul>
            <li>Verifique se o diretório <code>uploads/documentos/</code> existe</li>
            <li>Verifique se os caminhos no banco estão corretos</li>
            <li>Verifique as permissões do servidor web</li>
        </ul>
    </div>

</body>
</html>