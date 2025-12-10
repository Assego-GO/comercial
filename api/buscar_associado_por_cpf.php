<?php
// filepath: /var/www/html/comercial/api/buscar_associado_por_cpf.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json; charset=utf-8');

$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? '');
if (!$cpf) {
    echo json_encode(['status' => 'error', 'message' => 'CPF não informado']);
    exit;
}

// Remove zeros à esquerda para busca robusta
$cpfBusca = ltrim($cpf, '0');

$db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

// Busca associado por CPF (inclui agregados)
// Agregados estão na mesma tabela, mas com corporacao = 'Agregados'
// Nota: associado_titular_id ainda não existe no banco
$sql = "SELECT 
    a.id as titular_id,
    a.nome as titular_nome,
    a.cpf as titular_cpf,
    a.rg as titular_rg,
    a.email as titular_email,
    a.telefone as titular_telefone,
    a.situacao as titular_situacao,
    m.corporacao,
    m.patente,
    NULL as vinculo_titular_id,
    NULL as vinculo_titular_nome,
    NULL as vinculo_titular_cpf,
    CASE 
        WHEN m.corporacao = 'Agregados' THEN 1
        ELSE 0
    END as eh_agregado
FROM Associados a
LEFT JOIN Militar m ON a.id = m.associado_id
WHERE TRIM(LEADING '0' FROM REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '')) = ?
   OR REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
   OR a.cpf = ?
   OR a.cpf = LPAD(?, 11, '0')
LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$cpfBusca, $cpf, $cpf, $cpfBusca]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data && !empty($data['titular_nome'])) {
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    echo json_encode(['status' => 'not_found']);
}
exit;