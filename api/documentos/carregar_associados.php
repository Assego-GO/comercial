<?php
/**
 * API para carregar lista de associados
 * api/carregar_associados.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json');

try {
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        throw new Exception('Não autorizado');
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar associados
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.cpf,
            a.rg,
            a.email,
            m.patente,
            m.corporacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE a.situacao != 'Desligado' OR a.situacao IS NULL
        ORDER BY a.nome
    ";
    
    $stmt = $db->query($sql);
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'dados' => $associados
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>