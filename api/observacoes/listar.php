<?php
/**
 * ========================================
 * ARQUIVO: /api/observacoes/listar.php
 * Endpoint para listar observações de um associado
 * ========================================
 */

// Configurações e includes
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Associados.php';

// Headers para JSON e CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    echo json_encode([
        'status' => 'error', 
        'message' => 'Não autorizado. Faça login para continuar.'
    ]);
    exit;
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido. Use GET.'
    ]);
    exit;
}

// Pegar parâmetros da requisição
$associadoId = filter_input(INPUT_GET, 'associado_id', FILTER_VALIDATE_INT);

// Validar ID do associado (obrigatório)
if (!$associadoId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'ID do associado não fornecido ou inválido'
    ]);
    exit;
}

try {
    // Usar a classe Associados para buscar observações
    $associados = new Associados();
    
    // Buscar observações
    $observacoes = $associados->getObservacoes($associadoId);
    
    // Buscar estatísticas
    $estatisticas = $associados->getEstatisticasObservacoes($associadoId);
    
    // Buscar informações do associado
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $stmtAssociado = $db->prepare("
        SELECT id, nome, cpf 
        FROM Associados 
        WHERE id = ?
    ");
    $stmtAssociado->execute([$associadoId]);
    $associadoInfo = $stmtAssociado->fetch(PDO::FETCH_ASSOC);
    
    if (!$associadoInfo) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Associado não encontrado'
        ]);
        exit;
    }
    
    // Adicionar informações do usuário atual para verificar permissões
    $usuarioAtual = $auth->getUser();
    foreach ($observacoes as &$obs) {
        // Adicionar permissões
        $obs['pode_editar'] = ($obs['criado_por'] == $usuarioAtual['id']) || $auth->isDiretor();
        $obs['pode_excluir'] = ($obs['criado_por'] == $usuarioAtual['id']) || $auth->isDiretor();
        
        // Garantir que campos importantes estejam como string
        $obs['importante'] = (string)($obs['importante'] ?? '0');
        $obs['editado'] = (string)($obs['editado'] ?? '0');
        $obs['recente'] = (string)($obs['recente'] ?? '0');
    }
    
    // Retornar resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'data' => $observacoes,
        'estatisticas' => $estatisticas,
        'associado' => [
            'id' => $associadoInfo['id'],
            'nome' => $associadoInfo['nome'],
            'cpf' => $associadoInfo['cpf']
        ],
        'message' => count($observacoes) > 0 ? 
            "Encontradas " . count($observacoes) . " observações" : 
            "Nenhuma observação encontrada"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Erro em listar observações: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar observações',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
}
?>