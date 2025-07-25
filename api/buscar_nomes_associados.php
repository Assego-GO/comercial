<?php
/**
 * API para buscar nomes de associados para autocomplete
 * api/buscar_nomes_associados.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => []
];

try {
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }

    // Verifica se termo de busca foi fornecido
    $termo = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (strlen($termo) < 2) {
        throw new Exception('Digite pelo menos 2 caracteres para buscar');
    }

    // Carrega configurações
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';

    // Inicia sessão se não estiver iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica se está logado (básico)
    if (!isset($_SESSION['funcionario_id'])) {
        throw new Exception('Não autorizado');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Busca nomes de associados que coincidem com o termo
    $stmt = $db->prepare("
        SELECT DISTINCT nome 
        FROM Associados 
        WHERE nome LIKE ? 
        AND nome IS NOT NULL 
        AND nome != ''
        ORDER BY nome ASC 
        LIMIT 10
    ");
    
    $termoBusca = '%' . $termo . '%';
    $stmt->execute([$termoBusca]);
    $nomes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Remove duplicatas e filtra nomes válidos
    $nomes = array_unique(array_filter($nomes, function($nome) {
        return !empty(trim($nome)) && strlen(trim($nome)) > 2;
    }));

    // Reordena array após filtros
    $nomes = array_values($nomes);

    $response = [
        'status' => 'success',
        'message' => 'Nomes encontrados',
        'data' => $nomes,
        'total' => count($nomes)
    ];

} catch (Exception $e) {
    error_log("Erro ao buscar nomes de associados: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => []
    ];
    
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>