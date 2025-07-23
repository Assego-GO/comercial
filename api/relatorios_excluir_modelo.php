<?php
/**
 * API para excluir modelo de relatório
 * api/relatorios_excluir_modelo.php
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Se for OPTIONS (preflight), retorna OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Relatorios.php';

// Resposta padrão
$response = [
    'status' => 'error',
    'message' => 'Erro desconhecido'
];

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        $response['message'] = 'Usuário não autenticado';
        http_response_code(401);
        echo json_encode($response);
        exit;
    }

    // Verifica se é diretor
    if (!$auth->isDiretor()) {
        $response['message'] = 'Apenas diretores podem excluir modelos de relatórios';
        http_response_code(403);
        echo json_encode($response);
        exit;
    }

    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Método não permitido';
        http_response_code(405);
        echo json_encode($response);
        exit;
    }

    // Obtém ID do modelo
    $modeloId = null;
    
    // Tenta obter de várias formas
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Para DELETE, geralmente vem na URL
        $modeloId = isset($_GET['id']) ? intval($_GET['id']) : null;
    } else {
        // Para POST (fallback), pode vir no corpo
        $input = file_get_contents('php://input');
        if ($input) {
            $dados = json_decode($input, true);
            $modeloId = isset($dados['id']) ? intval($dados['id']) : null;
        }
        
        // Ou no $_POST
        if (!$modeloId && isset($_POST['id'])) {
            $modeloId = intval($_POST['id']);
        }
        
        // Ou no $_GET
        if (!$modeloId && isset($_GET['id'])) {
            $modeloId = intval($_GET['id']);
        }
    }

    // Validação
    if (!$modeloId || $modeloId <= 0) {
        $response['message'] = 'ID do modelo inválido';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Inicializa classe de relatórios
    $relatorios = new Relatorios();
    
    // Busca o modelo para verificar se existe
    $modelo = $relatorios->getModeloById($modeloId);
    if (!$modelo) {
        $response['message'] = 'Modelo não encontrado';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    // Log antes de excluir
    error_log("Excluindo modelo: ID={$modeloId}, Nome={$modelo['nome']}, Por={$_SESSION['funcionario_nome']}");

    // Executa exclusão (soft delete)
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    try {
        $db->beginTransaction();
        
        // Soft delete - apenas marca como inativo
        $stmt = $db->prepare("UPDATE Modelos_Relatorios SET ativo = 0 WHERE id = ?");
        $stmt->execute([$modeloId]);
        
        // Registra na auditoria
        registrarAuditoria('DELETE', 'Modelos_Relatorios', $modeloId, [
            'nome' => $modelo['nome'],
            'excluido_por' => $_SESSION['funcionario_id'] ?? null
        ]);
        
        $db->commit();
        
        $response['status'] = 'success';
        $response['message'] = 'Modelo excluído com sucesso';
        http_response_code(200);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em relatorios_excluir_modelo.php: " . $e->getMessage());
    $response['message'] = 'Erro ao excluir modelo: ' . $e->getMessage();
    http_response_code(500);
}

// Retorna resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Registra ação na auditoria
 */
function registrarAuditoria($acao, $tabela, $registroId, $dados) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO Auditoria (
                tabela, acao, registro_id, funcionario_id, 
                alteracoes, ip_origem, browser_info, data_hora
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $tabela,
            $acao,
            $registroId,
            $_SESSION['funcionario_id'] ?? null,
            json_encode($dados),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao registrar auditoria: " . $e->getMessage());
    }
}
?>