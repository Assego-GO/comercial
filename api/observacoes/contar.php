<?php
/**
 * API para contar observações de um associado
 * Crie este arquivo em: api/observacoes/contar.php
 */

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Includes necessários
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

// Inicializar resposta
$response = [
    'status' => 'error',
    'message' => '',
    'data' => ['total' => 0]
];

try {
    // Verificar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Validar parâmetros
    $associado_id = isset($_GET['associado_id']) ? intval($_GET['associado_id']) : 0;
    
    if ($associado_id <= 0) {
        throw new Exception('ID do associado inválido');
    }

    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Query simples e rápida para contar observações
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN importante = 1 THEN 1 ELSE 0 END) as importantes,
                SUM(CASE WHEN prioridade IN ('alta', 'urgente') THEN 1 ELSE 0 END) as prioritarias,
                SUM(CASE WHEN categoria = 'pendencia' THEN 1 ELSE 0 END) as pendencias
            FROM Observacoes_Associado 
            WHERE associado_id = :associado_id 
                AND ativo = 1";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Preparar resposta
    $response['status'] = 'success';
    $response['message'] = 'Contagem realizada com sucesso';
    $response['data'] = [
        'total' => intval($resultado['total'] ?? 0),
        'importantes' => intval($resultado['importantes'] ?? 0),
        'prioritarias' => intval($resultado['prioritarias'] ?? 0),
        'pendencias' => intval($resultado['pendencias'] ?? 0)
    ];

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    $response['data'] = ['total' => 0];
    
    // Log do erro
    error_log("Erro ao contar observações: " . $e->getMessage());
}

// Retornar resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);