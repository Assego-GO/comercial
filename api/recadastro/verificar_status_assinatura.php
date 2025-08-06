<?php


// ===== API 4: verificar_status_assinatura.php =====
/**
 * API para verificar status da assinatura no ZapSign
 * api/verificar_status_assinatura.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';

// Se tiver integração com ZapSign, descomentar:
// require_once '../../classes/ZapSign.php';

header('Content-Type: application/json');

try {
    $docId = $_GET['doc_id'] ?? null;
    
    if (!$docId) {
        throw new Exception('ID do documento não informado');
    }
    
    // Por enquanto, retornar um status mockado
    // Quando integrar com ZapSign, implementar a verificação real
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar status na tabela local
    $sql = "SELECT status FROM Solicitacoes_Recadastramento 
            WHERE zapsign_doc_id = :doc_id OR id = :id 
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':doc_id' => $docId, ':id' => $docId]);
    $solicitacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $status = 'pending';
    $assinadoPorAssociado = false;
    $assinadoPorPresidencia = false;
    
    if ($solicitacao) {
        switch ($solicitacao['status']) {
            case 'ASSINADO_ASSOCIADO':
                $status = 'signed_by_user';
                $assinadoPorAssociado = true;
                break;
            case 'ASSINADO_PRESIDENCIA':
            case 'EM_PROCESSAMENTO':
            case 'CONCLUIDO':
                $status = 'fully_signed';
                $assinadoPorAssociado = true;
                $assinadoPorPresidencia = true;
                break;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'status' => $status,
            'doc_status' => $solicitacao ? $solicitacao['status'] : 'unknown',
            'signed_by_user' => $assinadoPorAssociado,
            'signed_by_presidency' => $assinadoPorPresidencia
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}