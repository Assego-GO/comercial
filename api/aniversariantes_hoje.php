<?php
/**
 * API para buscar aniversariantes de hoje (para widget do dashboard)
 * Salve como: ../api/aniversariantes_hoje.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

try {
    // Verificar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Query para buscar aniversariantes de hoje
    $sql = "
        SELECT 
            a.id,
            a.nome,
            DATE_FORMAT(a.nasc, '%d/%m/%Y') as data_nascimento,
            YEAR(CURDATE()) - YEAR(a.nasc) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(a.nasc, '%m%d')) as idade,
            a.telefone,
            a.email,
            m.corporacao,
            m.patente
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE a.situacao = 'Filiado' 
        AND a.nasc IS NOT NULL
        AND DATE_FORMAT(a.nasc, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
        ORDER BY a.nome ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $aniversariantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Resposta JSON
    echo json_encode([
        'status' => 'success',
        'data_consulta' => date('Y-m-d H:i:s'),
        'total' => count($aniversariantes),
        'aniversariantes' => $aniversariantes
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API aniversariantes_hoje: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'aniversariantes' => []
    ]);
}
?>