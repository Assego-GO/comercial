<?php
/**
 * API simples para detalhar documento ZapSign
 */

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Token obrigatório']);
    exit;
}

// Configuração direta da API
$apiUrl = 'https://sandbox.api.zapsign.com.br/api/v1/docs/' . $token . '/';
$bearerToken = 'f0a60b1b-0a4c-413a-9c28-47f5dfd78c18021a89f2-9286-4cb9-b1f2-689d4b0b6f24'; // ← SUBSTITUA pelo seu token ZapSign

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $apiUrl);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $bearerToken,
    'Content-Type: application/json'
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    echo json_encode(['status' => 'error', 'message' => "Erro HTTP: $httpCode"]);
}
?>