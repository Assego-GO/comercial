<?php
/**
 * API para buscar associado por CPF, RG ou Nome
 * api/buscar_associado_desfiliacao.php
 */

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

$termo = trim($_GET['q'] ?? '');

if (strlen($termo) < 2) {
    echo json_encode(['status' => 'error', 'data' => []]);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Preparar o termo para busca flexível
    $termoBusca = '%' . $termo . '%';
    $cpfNumeros = preg_replace('/\D/', '', $termo);

    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.cpf,
            a.rg,
            a.situacao,
            m.corporacao,
            m.patente
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE 
            a.nome LIKE ? 
            OR a.cpf LIKE ?
            OR a.rg LIKE ?
            OR REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
        ORDER BY a.nome
        LIMIT 10
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$termoBusca, $termoBusca, $termoBusca, $cpfNumeros]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $resultados
    ]);

} catch (Exception $e) {
    error_log("Erro ao buscar associado: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'data' => [], 'message' => 'Erro na busca']);
}
