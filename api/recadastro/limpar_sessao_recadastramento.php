<?php
/**
 * API para limpar dados de recadastramento da sessão
 * api/recadastro/limpar_sessao_recadastramento.php
 */

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log para debug
error_log("=== LIMPANDO SESSÃO DE RECADASTRAMENTO ===");
error_log("Session ID antes: " . session_id());
error_log("Dados antes: " . json_encode([
    'id' => $_SESSION['recadastramento_id'] ?? 'vazio',
    'tem_data' => isset($_SESSION['recadastramento_data']) ? 'sim' : 'não'
]));

// Limpar variáveis de sessão específicas do recadastramento
unset($_SESSION['recadastramento_id']);
unset($_SESSION['recadastramento_data']);
unset($_SESSION['recadastramento_timestamp']);

// Log após limpar
error_log("Sessão limpa com sucesso");

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success', 
    'message' => 'Sessão de recadastramento limpa com sucesso'
]);
?>