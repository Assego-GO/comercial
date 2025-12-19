<?php
/**
 * API para listar TODOS os indicadores ativos para dropdown
 * api/listar_indicadores.php
 */

header('Content-Type: application/json; charset=utf-8');

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Verifica autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Busca TODOS os indicadores ativos, ordenados alfabeticamente
    $sql = "SELECT 
                id,
                nome_completo,
                patente,
                corporacao,
                total_indicacoes
            FROM Indicadores 
            WHERE ativo = 1 
            ORDER BY nome_completo ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $indicadores
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar indicadores: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao listar indicadores'
    ]);
}
?>
