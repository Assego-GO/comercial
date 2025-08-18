<?php
/**
 * Arquivo de teste para a API de Pecúlio
 * Salve como: teste_peculio.php na raiz do projeto
 */

echo "<h2>🧪 Teste da API de Pecúlio</h2>";
echo "<hr>";

// Teste 1: Verificar se a API existe
$apiUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api/peculio/consultar_peculio.php";
echo "<h3>📍 URL da API:</h3>";
echo "<code>$apiUrl</code><br><br>";

// Teste 2: Testar com um RG específico
if (isset($_GET['rg'])) {
    $rg = $_GET['rg'];
    
    echo "<h3>🔍 Testando RG: $rg</h3>";
    
    // Faz a requisição
    $testUrl = $apiUrl . "?rg=" . urlencode($rg);
    echo "<strong>URL de teste:</strong> <code>$testUrl</code><br><br>";
    
    // Tenta fazer a requisição
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $response = @file_get_contents($testUrl, false, $context);
    
    if ($response === false) {
        echo "❌ <strong>Erro:</strong> Não foi possível acessar a API<br>";
        echo "Verifique se o arquivo existe em: <code>api/peculio/consultar_peculio.php</code><br>";
    } else {
        echo "✅ <strong>Resposta da API:</strong><br>";
        echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
        echo htmlspecialchars($response);
        echo "</pre>";
        
        // Tenta decodificar o JSON
        $json = json_decode($response, true);
        if ($json) {
            echo "<h4>📊 Dados decodificados:</h4>";
            echo "<ul>";
            echo "<li><strong>Status:</strong> " . ($json['status'] ?? 'N/A') . "</li>";
            echo "<li><strong>Mensagem:</strong> " . ($json['message'] ?? 'N/A') . "</li>";
            if (isset($json['data'])) {
                echo "<li><strong>Tem dados:</strong> Sim</li>";
                if (isset($json['data']['nome'])) {
                    echo "<li><strong>Nome:</strong> " . $json['data']['nome'] . "</li>";
                }
                if (isset($json['data']['tem_peculio'])) {
                    echo "<li><strong>Tem pecúlio:</strong> " . ($json['data']['tem_peculio'] ? 'Sim' : 'Não') . "</li>";
                }
            } else {
                echo "<li><strong>Tem dados:</strong> Não</li>";
            }
            echo "</ul>";
        }
    }
    
    echo "<hr>";
}

// Teste 3: Verificar banco de dados
echo "<h3>🗄️ Verificações do Banco de Dados</h3>";

try {
    require_once 'config/config.php';
    require_once 'config/database.php';
    require_once 'classes/Database.php';
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    echo "✅ Conexão com banco estabelecida<br>";
    
    // Verifica se existe a tabela Associados
    $stmt = $db->query("SHOW TABLES LIKE 'Associados'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela 'Associados' existe<br>";
    } else {
        echo "❌ Tabela 'Associados' NÃO existe<br>";
    }
    
    // Verifica se existe a tabela Peculio
    $stmt = $db->query("SHOW TABLES LIKE 'Peculio'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela 'Peculio' existe<br>";
    } else {
        echo "❌ Tabela 'Peculio' NÃO existe<br>";
    }
    
    // Conta associados
    $stmt = $db->query("SELECT COUNT(*) as total FROM Associados");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "📊 Total de associados: $total<br>";
    
    // Conta registros de pecúlio
    $stmt = $db->query("SELECT COUNT(*) as total FROM Peculio");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "📊 Total de registros de pecúlio: $total<br>";
    
    // Verifica associados com situação 'Filiado'
    $stmt = $db->query("SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "👥 Associados 'Filiados': $total<br>";
    
    // Se foi passado um RG, verifica especificamente
    if (isset($_GET['rg'])) {
        $rg = $_GET['rg'];
        $stmt = $db->prepare("SELECT id, nome, situacao FROM Associados WHERE rg = ?");
        $stmt->execute([$rg]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h4>🔍 Verificação específica do RG $rg:</h4>";
        if ($associado) {
            echo "✅ Associado encontrado: {$associado['nome']}<br>";
            echo "📋 Situação: {$associado['situacao']}<br>";
            
            // Verifica se tem pecúlio
            $stmt = $db->prepare("SELECT * FROM Peculio WHERE associado_id = ?");
            $stmt->execute([$associado['id']]);
            $peculio = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($peculio) {
                echo "💰 Tem registro de pecúlio: Sim<br>";
                echo "📅 Data prevista: " . ($peculio['data_prevista'] ?: 'Não definida') . "<br>";
                echo "📅 Data recebimento: " . ($peculio['data_recebimento'] ?: 'Não recebido') . "<br>";
            } else {
                echo "💰 Tem registro de pecúlio: Não<br>";
            }
        } else {
            echo "❌ Associado com RG $rg NÃO encontrado<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro no banco: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Formulário de teste
echo "<h3>🧪 Teste Manual</h3>";
echo "<form method='GET'>";
echo "<label>RG para testar:</label><br>";
echo "<input type='text' name='rg' value='" . ($_GET['rg'] ?? '') . "' placeholder='Digite um RG (ex: 32032)'>";
echo "<button type='submit'>Testar</button>";
echo "</form>";

echo "<br><br>";
echo "<p><strong>💡 Dica:</strong> Verifique os logs do servidor em <code>error_log</code> ou no console do navegador para mais detalhes.</p>";
?>