<?php
/**
 * ARQUIVO: /api/observacoes/salvar.php
 * Endpoint para salvar observações
 */

// Configurações e includes
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Associados.php';

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Se for requisição OPTIONS (preflight), retornar apenas headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Pegar dados do POST
$input = json_decode(file_get_contents('php://input'), true);

// Validar dados
if (empty($input['associado_id']) || empty($input['observacao'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados obrigatórios faltando']);
    exit;
}

try {
    // Criar instância da classe
    $associados = new Associados();
    $usuarioAtual = $auth->getUser();
    
    // Adicionar o ID do funcionário aos dados
    $_SESSION['funcionario_id'] = $usuarioAtual['id'];
    
    // Preparar dados
    $dados = [
        'associado_id' => $input['associado_id'],
        'observacao' => $input['observacao'],
        'categoria' => $input['categoria'] ?? 'geral',
        'prioridade' => $input['prioridade'] ?? 'media',
        'importante' => $input['importante'] ?? 0
    ];
    
    // Chamar o método para adicionar observação
    $resultado = $associados->adicionarObservacao($dados);
    
    if ($resultado) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Observação salva com sucesso',
            'data' => $resultado
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Erro ao salvar observação');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>