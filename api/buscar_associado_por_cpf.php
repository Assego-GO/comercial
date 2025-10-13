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

// CORREÇÃO: Busca titular e agregado relacionado (se houver)
// Uso da função TRIM(LEADING '0' FROM ...) ao invés de ltrim com 2 parâmetros
$sql = "SELECT 
    t.id as titular_id,
    t.nome as titular_nome,
    t.cpf as titular_cpf,
    t.rg as titular_rg,
    t.email as titular_email,
    t.telefone as titular_telefone,
    t.situacao as titular_situacao,
    a.id as agregado_id,
    a.nome as agregado_nome,
    a.cpf as agregado_cpf,
    a.socio_titular_cpf as agregado_socio_titular_cpf,
    a.socio_titular_nome as agregado_socio_titular_nome,
    a.socio_titular_email as agregado_socio_titular_email,
    a.socio_titular_fone as agregado_socio_titular_fone
FROM Associados t
LEFT JOIN Socios_Agregados a
  ON TRIM(LEADING '0' FROM REPLACE(REPLACE(REPLACE(a.socio_titular_cpf, '.', ''), '-', ''), ' ', '')) = 
     TRIM(LEADING '0' FROM REPLACE(REPLACE(REPLACE(t.cpf, '.', ''), '-', ''), ' ', ''))
WHERE TRIM(LEADING '0' FROM REPLACE(REPLACE(REPLACE(t.cpf, '.', ''), '-', ''), ' ', '')) = ?
   OR REPLACE(REPLACE(REPLACE(t.cpf, '.', ''), '-', ''), ' ', '') = ?
   OR t.cpf = ?
LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$cpfBusca, $cpf, $cpf]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data && !empty($data['titular_nome'])) {
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// CORREÇÃO: Busca reversa também corrigida
$sql2 = "SELECT 
    id as titular_id,
    nome as titular_nome,
    cpf as titular_cpf,
    rg as titular_rg,
    email as titular_email,
    telefone as titular_telefone,
    situacao as titular_situacao
FROM Associados
WHERE TRIM(LEADING '0' FROM REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '')) = ?
   OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?
   OR cpf = ?
   OR cpf = LPAD(?, 11, '0')
LIMIT 1";

$stmt2 = $db->prepare($sql2);
$stmt2->execute([$cpfBusca, $cpf, $cpf, $cpfBusca]);
$data2 = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($data2 && !empty($data2['titular_nome'])) {
    echo json_encode(['status' => 'success', 'data' => $data2]);
} elseif ($data2 && !empty($data2['titular_id'])) {
    // Garante compatibilidade com o frontend
    echo json_encode(['status' => 'success', 'data' => $data2]);
} else {
    echo json_encode(['status' => 'not_found']);
}
exit;