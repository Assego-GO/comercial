<?php
/**
 * API para buscar indicadores com autocomplete
 * api/buscar_indicadores.php
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
    // Pega o termo de busca
    $termo = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (strlen($termo) < 2) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Busca APENAS na tabela de Indicadores
    $sql = "SELECT DISTINCT
                id,
                nome_completo,
                patente,
                corporacao,
                total_indicacoes
            FROM Indicadores 
            WHERE ativo = 1 
            AND nome_completo LIKE :termo
            ORDER BY 
                CASE 
                    WHEN nome_completo = :termo_exato THEN 1
                    WHEN nome_completo LIKE :termo_inicio THEN 2
                    ELSE 3
                END,
                total_indicacoes DESC,
                nome_completo ASC
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $termoLike = '%' . $termo . '%';
    $termoInicio = $termo . '%';
    
    $stmt->bindParam(':termo', $termoLike);
    $stmt->bindParam(':termo_exato', $termo);
    $stmt->bindParam(':termo_inicio', $termoInicio);
    
    $stmt->execute();
    $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formata os resultados para o autocomplete
    $resultados = array_map(function($ind) {
        // Retorna apenas o nome, sem informações extras entre parênteses
        return [
            'id' => $ind['id'],
            'value' => $ind['nome_completo'],
            'label' => $ind['nome_completo'], // Apenas o nome, sem extras
            'patente' => $ind['patente'] ?? '',
            'corporacao' => $ind['corporacao'] ?? '',
            'total_indicacoes' => $ind['total_indicacoes'] ?? 0
        ];
    }, $indicadores);
    
    echo json_encode([
        'status' => 'success',
        'data' => $resultados
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar indicadores: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar indicadores'
    ]);
}
?>