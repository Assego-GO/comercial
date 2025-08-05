<?php

// ===== API 5: status_recadastramento.php =====
/**
 * API para verificar status geral do recadastramento
 * api/status_recadastramento.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';

header('Content-Type: application/json');

try {
    $processoId = $_GET['processo_id'] ?? null;
    
    if (!$processoId) {
        throw new Exception('ID do processo não informado');
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar solicitação
    $sql = "SELECT 
                sr.*,
                a.nome as associado_nome,
                a.cpf as associado_cpf
            FROM Solicitacoes_Recadastramento sr
            INNER JOIN Associados a ON sr.associado_id = a.id
            WHERE sr.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $processoId]);
    $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$solicitacao) {
        throw new Exception('Processo não encontrado');
    }
    
    // Buscar histórico
    $sqlHist = "SELECT * FROM Historico_Recadastramento 
                WHERE solicitacao_id = :id 
                ORDER BY data_hora DESC";
    
    $stmtHist = $db->prepare($sqlHist);
    $stmtHist->execute([':id' => $processoId]);
    $historico = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapear status para a interface
    $statusMapeado = mapearStatus($solicitacao['status']);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'solicitacao' => $solicitacao,
            'historico' => $historico,
            'status' => $statusMapeado
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function mapearStatus($status) {
    $mapa = [
        'PENDENTE' => 'enviado',
        'AGUARDANDO_ASSINATURA' => 'enviado',
        'ASSINADO_ASSOCIADO' => 'assinado_associado',
        'ASSINADO_PRESIDENCIA' => 'assinado_presidencia',
        'EM_PROCESSAMENTO' => 'processado',
        'CONCLUIDO' => 'processado',
        'CANCELADO' => 'cancelado'
    ];
    
    return $mapa[$status] ?? 'enviado';
}