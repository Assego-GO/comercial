<?php
/**
 * Teste da API de gerar ficha virtual
 * teste_api_ficha.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE API FICHA VIRTUAL ===\n\n";

// 1. Verificar se a API existe
$apiPath = __DIR__ . '/api/documentos/documentos_gerar_ficha_virtual.php';
echo "1. Verificando se a API existe:\n";
if (file_exists($apiPath)) {
    echo "   ✓ API encontrada em: $apiPath\n";
} else {
    echo "   ✗ API NÃO encontrada em: $apiPath\n";
    exit(1);
}

// 2. Simular uma requisição para a API
echo "\n2. Testando requisição para a API:\n";

// URL da API (ajuste conforme seu ambiente)
$baseUrl = 'http://localhost/luis/comercial'; // AJUSTE ESTA URL
$apiUrl = $baseUrl . '/api/documentos/documentos_gerar_ficha_virtual.php';

echo "   URL da API: $apiUrl\n";

// Buscar um associado para teste
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'classes/Database.php';

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $stmt = $db->query("SELECT id, nome FROM Associados LIMIT 1");
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$associado) {
        echo "   ✗ Nenhum associado encontrado para teste\n";
        exit(1);
    }
    
    echo "   ✓ Associado para teste: " . $associado['nome'] . " (ID: " . $associado['id'] . ")\n";
    
} catch (Exception $e) {
    echo "   ✗ Erro ao buscar associado: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Fazer requisição POST
echo "\n3. Fazendo requisição POST para a API:\n";

$data = json_encode(['associado_id' => $associado['id']]);
echo "   Dados enviados: $data\n";

// Criar contexto para requisição
$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ],
        'content' => $data,
        'timeout' => 30
    ]
];

$context = stream_context_create($options);

// Fazer requisição
$result = @file_get_contents($apiUrl, false, $context);

if ($result === false) {
    echo "   ✗ Erro na requisição\n";
    
    // Pegar headers da resposta
    if (isset($http_response_header)) {
        echo "   Headers da resposta:\n";
        foreach ($http_response_header as $header) {
            echo "     $header\n";
        }
    }
    
    // Tentar com cURL
    echo "\n4. Tentando com cURL:\n";
    
    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        echo "   HTTP Code: $httpCode\n";
        if ($error) {
            echo "   cURL Error: $error\n";
        }
        
        if ($result) {
            echo "   Resposta: $result\n";
        }
    } else {
        echo "   ✗ cURL não está disponível\n";
    }
} else {
    echo "   ✓ Requisição bem-sucedida\n";
    echo "   Resposta: $result\n";
    
    // Decodificar resposta
    $response = json_decode($result, true);
    if ($response) {
        echo "\n   Resposta decodificada:\n";
        echo "   - Status: " . ($response['status'] ?? 'N/A') . "\n";
        echo "   - Mensagem: " . ($response['message'] ?? 'N/A') . "\n";
        if (isset($response['data'])) {
            echo "   - Dados: " . print_r($response['data'], true) . "\n";
        }
    }
}

// 5. Testar acesso direto à API (simulando sessão)
echo "\n5. Testando acesso direto à API:\n";

// Simular sessão para teste
session_start();
$_SESSION['funcionario_id'] = 1;
$_SESSION['funcionario_nome'] = 'Teste';
$_SESSION['departamento_id'] = 1;

// Simular POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Criar arquivo temporário com dados POST
$tempFile = tempnam(sys_get_temp_dir(), 'post');
file_put_contents($tempFile, json_encode(['associado_id' => $associado['id']]));

// Redirecionar entrada para o arquivo
$originalInput = fopen('php://input', 'r');
stream_wrapper_unregister('php');
stream_wrapper_register('php', 'MockPhpStream');
MockPhpStream::$data = json_encode(['associado_id' => $associado['id']]);

// Incluir a API diretamente
ob_start();
try {
    include $apiPath;
    $output = ob_get_clean();
    echo "   Saída da API: $output\n";
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✗ Erro ao executar API: " . $e->getMessage() . "\n";
}

// Limpar
unlink($tempFile);

echo "\n=== FIM DO TESTE ===\n";

// Classe auxiliar para simular php://input
class MockPhpStream {
    public static $data = '';
    private $position = 0;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($count) {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    
    public function stream_eof() {
        return $this->position >= strlen(self::$data);
    }
    
    public function stream_stat() {
        return [];
    }
    
    public function stream_tell() {
        return $this->position;
    }
}
?>