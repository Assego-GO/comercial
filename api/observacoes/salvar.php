<?php
/**
 * ARQUIVO: /api/observacoes/salvar.php
 * Endpoint para salvar/editar observações (CORRIGIDO)
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

// Verificar se é edição ou nova observação
$isEdicao = !empty($input['id']);

// Validações baseadas na operação
if ($isEdicao) {
    // Para edição: só precisa do ID e texto
    if (empty($input['id']) || empty($input['observacao'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID e observação são obrigatórios para edição']);
        exit;
    }
} else {
    // Para nova observação: precisa de associado_id e texto
    if (empty($input['associado_id']) || empty($input['observacao'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Associado ID e observação são obrigatórios']);
        exit;
    }
}

try {
    // Criar instância da classe
    $associados = new Associados();
    $usuarioAtual = $auth->getUser();
    
    // Log para debug
    error_log("DEBUG Observação - Usuario: " . json_encode($usuarioAtual));
    error_log("DEBUG Observação - Input: " . json_encode($input));
    
    // Adicionar o ID do funcionário aos dados
    if (isset($usuarioAtual['id'])) {
        $_SESSION['funcionario_id'] = $usuarioAtual['id'];
    } elseif (isset($usuarioAtual['funcionario_id'])) {
        $_SESSION['funcionario_id'] = $usuarioAtual['funcionario_id'];
    } else {
        error_log("ERRO: ID do funcionário não encontrado. Dados do usuário: " . print_r($usuarioAtual, true));
    }
    
    if ($isEdicao) {
        // ========== EDITAR OBSERVAÇÃO EXISTENTE ==========
        $dados = [
            'observacao' => $input['observacao'],
            'categoria' => $input['categoria'] ?? 'geral',
            'prioridade' => $input['prioridade'] ?? 'media',
            'importante' => $input['importante'] ?? 0
        ];
        
        $resultado = $associados->atualizarObservacao($input['id'], $dados);
        $mensagem = 'Observação atualizada com sucesso';
        
    } else {
        // ========== CRIAR NOVA OBSERVAÇÃO ==========
        $dados = [
            'associado_id' => $input['associado_id'],
            'observacao' => $input['observacao'],
            'categoria' => $input['categoria'] ?? 'geral',
            'prioridade' => $input['prioridade'] ?? 'media',
            'importante' => $input['importante'] ?? 0
        ];
        
        $resultado = $associados->adicionarObservacao($dados);
        $mensagem = 'Observação criada com sucesso';
    }
    
    if ($resultado) {
        echo json_encode([
            'status' => 'success',
            'message' => $mensagem,
            'data' => $resultado,
            'operacao' => $isEdicao ? 'update' : 'insert'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('Erro ao processar observação');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>