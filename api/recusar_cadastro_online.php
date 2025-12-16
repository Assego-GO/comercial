<?php
/**
 * API para recusar/deletar cadastro online
 * api/recusar_cadastro_online.php
 * 
 * ✅ Deleta do sistema interno E do site
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

$usuarioLogado = $auth->getUser();
$dados = json_decode(file_get_contents('php://input'), true);

if (!isset($dados['id']) || !isset($dados['tipo'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
    exit;
}

$id = (int)$dados['id'];
$tipo = $dados['tipo']; // 'sistema' ou 'site'
$motivo = $dados['motivo'] ?? 'Não informado';
$observacao = $dados['observacao'] ?? '';

try {
    $dbCadastro = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    if ($tipo === 'sistema') {
        // ========================================================================
        // DELETAR DO SISTEMA INTERNO
        // ========================================================================
        
        $dbCadastro->beginTransaction();
        
        // Buscar dados do associado
        $sql = "SELECT nome, cpf FROM Associados WHERE id = ? AND pre_cadastro = 1";
        $stmt = $dbCadastro->prepare($sql);
        $stmt->execute([$id]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$associado) {
            throw new Exception('Pré-cadastro não encontrado');
        }
        
        // Registrar em auditoria
        $sqlAuditoria = "
            INSERT INTO Auditoria (
                tabela, acao, registro_id, funcionario_id, alteracoes, data_hora
            ) VALUES (
                'Associados', 'RECUSA_PRE_CADASTRO', ?, ?, ?, NOW()
            )
        ";
        
        $alteracoes = json_encode([
            'nome' => $associado['nome'],
            'cpf' => $associado['cpf'],
            'motivo' => $motivo,
            'observacao' => $observacao,
            'recusado_por' => $usuarioLogado['nome']
        ]);
        
        $stmtAudit = $dbCadastro->prepare($sqlAuditoria);
        $stmtAudit->execute([$id, $usuarioLogado['id'], $alteracoes]);
        
        // Deletar registros relacionados (CASCADE deve fazer automaticamente, mas garantindo)
        $dbCadastro->exec("DELETE FROM Fluxo_Pre_Cadastro WHERE associado_id = $id");
        $dbCadastro->exec("DELETE FROM Militar WHERE associado_id = $id");
        $dbCadastro->exec("DELETE FROM Endereco WHERE associado_id = $id");
        $dbCadastro->exec("DELETE FROM Financeiro WHERE associado_id = $id");
        
        // Deletar associado
        $dbCadastro->exec("DELETE FROM Associados WHERE id = $id");
        
        $dbCadastro->commit();
        
        error_log("✅ Pré-cadastro recusado e deletado - ID: $id, Motivo: $motivo, Por: {$usuarioLogado['nome']}");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Cadastro recusado e excluído com sucesso',
            'info' => [
                'id' => $id,
                'nome' => $associado['nome'],
                'motivo' => $motivo,
                'recusado_por' => $usuarioLogado['nome']
            ]
        ]);
        
    } elseif ($tipo === 'site') {
        // ========================================================================
        // DELETAR DO SITE (via API)
        // ========================================================================
        
        $apiUrlBase = 'https://associe-se.assego.com.br/associar/api';
        $apiKey = 'assego_2025_e303e77ad524f7a9f59bcdaa9883bb72';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "{$apiUrlBase}/deletar_cadastro.php",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'id' => $id,
                'api_key' => $apiKey,
                'motivo' => $motivo,
                'observacao' => $observacao,
                'deletado_por' => $usuarioLogado['nome']
            ]),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new Exception('Erro ao comunicar com API do site');
        }
        
        $respData = json_decode($response, true);
        
        if ($respData['status'] !== 'success') {
            throw new Exception($respData['message'] ?? 'Erro ao deletar no site');
        }
        
        error_log("✅ Cadastro do site recusado - ID: $id, Motivo: $motivo, Por: {$usuarioLogado['nome']}");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Cadastro do site recusado e excluído',
            'info' => [
                'id' => $id,
                'motivo' => $motivo,
                'recusado_por' => $usuarioLogado['nome']
            ]
        ]);
        
    } else {
        throw new Exception('Tipo inválido');
    }
    
} catch (Exception $e) {
    if (isset($dbCadastro) && $dbCadastro->inTransaction()) {
        $dbCadastro->rollBack();
    }
    
    error_log("❌ Erro ao recusar cadastro: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>