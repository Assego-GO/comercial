<?php
/**
 * API para salvar dados do associado na sessão
 * api/recadastro/salvar_sessao_recadastramento.php
 */

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/config.php';

header('Content-Type: application/json');

try {
    // Receber dados via POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados não recebidos');
    }
    
    if (!isset($input['associado_id']) || !isset($input['associado_data'])) {
        throw new Exception('Dados incompletos - ID ou dados do associado não informados');
    }
    
    // Validar se o ID é válido
    if (!is_numeric($input['associado_id']) || $input['associado_id'] <= 0) {
        throw new Exception('ID do associado inválido');
    }
    
    // Salvar na sessão
    $_SESSION['recadastramento_id'] = intval($input['associado_id']);
    $_SESSION['recadastramento_data'] = $input['associado_data'];
    $_SESSION['recadastramento_timestamp'] = time();
    
    // Log para debug
    error_log("=== SALVANDO SESSÃO DE RECADASTRAMENTO ===");
    error_log("ID do Associado: " . $_SESSION['recadastramento_id']);
    error_log("Nome: " . ($_SESSION['recadastramento_data']['nome'] ?? 'N/A'));
    error_log("CPF: " . ($_SESSION['recadastramento_data']['cpf'] ?? 'N/A'));
    error_log("Session ID: " . session_id());
    error_log("Timestamp: " . $_SESSION['recadastramento_timestamp']);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Dados salvos na sessão com sucesso',
        'session_id' => session_id(),
        'associado_id' => $_SESSION['recadastramento_id']
    ]);
    
} catch (Exception $e) {
    error_log("ERRO ao salvar sessão de recadastramento: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>