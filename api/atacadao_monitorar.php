<?php
/**
 * API de monitoramento simples - Integração Atacadão
 * api/atacadao_monitorar.php
 * Mostra últimos associados cadastrados e status Atacadão
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Pega últimos 20 cadastros com status Atacadão
    $stmt = $db->prepare("
        SELECT 
            id,
            nome,
            cpf,
            data_criacao,
            ativo_atacadao,
            CASE 
                WHEN ativo_atacadao = 1 THEN '✅ Enviado com sucesso'
                WHEN ativo_atacadao = 0 THEN '❌ Falha ou não enviado'
                ELSE '❓ Desconhecido'
            END as status_texto
        FROM Associados
        ORDER BY data_criacao DESC
        LIMIT 20
    ");
    
    $stmt->execute();
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Máscara CPF
    foreach ($associados as &$assoc) {
        $cpf = preg_replace('/\D/', '', $assoc['cpf']);
        if (strlen($cpf) === 11) {
            $assoc['cpf_mascarado'] = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-**';
        } else {
            $assoc['cpf_mascarado'] = '***.***.***-**';
        }
    }
    
    // Conta totais
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN ativo_atacadao = 1 THEN 1 ELSE 0 END) as enviados,
            SUM(CASE WHEN ativo_atacadao = 0 THEN 1 ELSE 0 END) as falhas
        FROM Associados
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => [
            'total_associados' => (int)$stats['total'],
            'enviados_atacadao' => (int)$stats['enviados'],
            'falhas_atacadao' => (int)$stats['falhas'],
            'taxa_sucesso' => $stats['total'] > 0 
                ? round(($stats['enviados'] / $stats['total']) * 100, 2) . '%'
                : '0%'
        ],
        'ultimos_cadastros' => $associados
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
