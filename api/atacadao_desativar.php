<?php
/**
 * Script para desativar CPF no Atacadão
 * api/atacadao_desativar.php?cpf=39818146069
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config/config.php';
    require_once '../atacadao/Client.php';
    require_once '../atacadao/Logger.php';

    $cpf = isset($_GET['cpf']) ? $_GET['cpf'] : null;
    
    if (!$cpf) {
        throw new Exception('CPF não fornecido. Use: ?cpf=39818146069');
    }

    echo json_encode([
        'status' => 'processando',
        'mensagem' => 'Enviando requisição de desativação...',
        'cpf' => substr(preg_replace('/\D/', '', $cpf), -11)
    ]);
    
    $res = AtacadaoClient::ativarCliente($cpf, 'I', '58');
    
    AtacadaoLogger::logAtivacao(
        0,
        $cpf,
        'I',
        '58',
        $res['http'] ?? 0,
        $res['ok'] ?? false,
        $res['data'] ?? null,
        $res['error'] ?? null
    );
    
    echo json_encode([
        'status' => $res['ok'] ? 'success' : 'error',
        'mensagem' => $res['ok'] ? '✅ CPF desativado com sucesso' : '❌ Erro ao desativar',
        'http_code' => $res['http'],
        'resposta' => $res['data'],
        'erro' => $res['error'] ?? null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'mensagem' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
