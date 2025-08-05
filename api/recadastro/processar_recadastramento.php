<?php

// ===== API 3: processar_recadastramento.php =====
/**
 * API para processar solicitação de recadastramento
 * api/processar_recadastramento.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/ZapSign.php';
require_once '../../classes/DocumentGenerator.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // Receber dados do formulário
    $associadoId = $_POST['associado_id'] ?? null;
    $motivo = $_POST['motivo_recadastramento'] ?? '';
    
    if (!$associadoId) {
        throw new Exception('ID do associado não informado');
    }
    
    // Criar registro de solicitação de recadastramento
    $sql = "INSERT INTO Solicitacoes_Recadastramento (
                associado_id,
                status,
                motivo,
                dados_alterados,
                data_solicitacao,
                ip_solicitacao
            ) VALUES (
                :associado_id,
                'PENDENTE',
                :motivo,
                :dados_alterados,
                NOW(),
                :ip
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':associado_id' => $associadoId,
        ':motivo' => $motivo,
        ':dados_alterados' => json_encode($_POST),
        ':ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $solicitacaoId = $db->lastInsertId();
    
    // Gerar documento PDF para assinatura
    $docGenerator = new DocumentGenerator();
    $pdfPath = $docGenerator->gerarDocumentoRecadastramento($associadoId, $_POST);
    
    // Criar documento no ZapSign
    $zapSign = new ZapSign();
    
    // Buscar dados do associado para assinatura
    $sqlAssoc = "SELECT nome, email, cpf FROM Associados WHERE id = :id";
    $stmtAssoc = $db->prepare($sqlAssoc);
    $stmtAssoc->execute([':id' => $associadoId]);
    $dadosAssoc = $stmtAssoc->fetch(PDO::FETCH_ASSOC);
    
    // Criar documento no ZapSign
    $docData = [
        'name' => "Recadastramento - {$dadosAssoc['nome']}",
        'lang' => 'pt-br',
        'disable_signer_emails' => false,
        'signed_file_suffix' => '_assinado',
        'brand_primary_color' => '#0056D2',
        'signers' => [
            [
                'name' => $dadosAssoc['nome'],
                'email' => $dadosAssoc['email'] ?: 'naotemmail@assego.org.br',
                'phone_country' => '55',
                'phone_number' => $_POST['telefone'] ?? '',
                'auth_mode' => 'assinaturaTela',
                'send_automatic_email' => true,
                'order_group' => 1
            ],
            [
                'name' => 'Presidência ASSEGO',
                'email' => 'presidencia@assego.org.br',
                'auth_mode' => 'assinaturaTela',
                'send_automatic_email' => true,
                'order_group' => 2
            ]
        ]
    ];
    
    $docResponse = $zapSign->createDocument($pdfPath, $docData);
    
    if (!$docResponse || !isset($docResponse['id'])) {
        throw new Exception('Erro ao criar documento no ZapSign');
    }
    
    // Atualizar solicitação com ID do documento
    $sqlUpdate = "UPDATE Solicitacoes_Recadastramento 
                  SET zapsign_doc_id = :doc_id,
                      status = 'AGUARDANDO_ASSINATURA'
                  WHERE id = :id";
    
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':doc_id' => $docResponse['id'],
        ':id' => $solicitacaoId
    ]);
    
    // Registrar no histórico
    $sqlHist = "INSERT INTO Historico_Recadastramento (
                    solicitacao_id,
                    status,
                    descricao,
                    data_hora
                ) VALUES (
                    :solicitacao_id,
                    'ENVIADO',
                    'Solicitação enviada para assinatura',
                    NOW()
                )";
    
    $stmtHist = $db->prepare($sqlHist);
    $stmtHist->execute([':solicitacao_id' => $solicitacaoId]);
    
    $db->commit();
    
    // Buscar token do assinante
    $signerToken = $docResponse['signers'][0]['token'] ?? null;
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'processo_id' => $solicitacaoId,
            'zapsign_doc_id' => $docResponse['id'],
            'zapsign_signer_token' => $signerToken
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}