<?php
/**
 * Script para excluir associado usando a classe Associados
 * api/excluir_associado.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Acesso negado. Faça login para continuar.'
    ]);
    exit;
}

// Verifica se é diretor (apenas diretores podem excluir)
if (!$auth->isDiretor()) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Acesso negado. Apenas diretores podem excluir associados.'
    ]);
    exit;
}

// Verifica se foi enviado o ID
if (!isset($_POST['id']) || empty($_POST['id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'ID do associado não informado.'
    ]);
    exit;
}

$associadoId = intval($_POST['id']);

try {
    // Instancia a classe Associados
    $associados = new Associados();
    
    // Executa a exclusão
    $resultado = $associados->excluir($associadoId);
    
    if ($resultado) {
        // Resposta de sucesso
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Associado excluído com sucesso.',
            'associado_id' => $associadoId
        ]);
    } else {
        throw new Exception('Não foi possível excluir o associado.');
    }
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro ao excluir associado: " . $e->getMessage());
    
    // Resposta de erro
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}