<?php
/**
 * API para assinar múltiplos documentos em lote
 * api/documentos/documentos_assinar_lote.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => null
];

try {
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }

    // Carrega arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Documentos.php';

    // Inicia sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    error_log("=== API ASSINAR DOCUMENTOS EM LOTE ===");

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        throw new Exception('Usuário não autenticado');
    }

    // Pega dados do usuário
    $usuarioLogado = $auth->getUser();

    // Verifica permissão
    $temPermissao = false;
    if ($auth->isDiretor() || (isset($usuarioLogado['departamento_id']) && $usuarioLogado['departamento_id'] == 1)) {
        $temPermissao = true;
    }

    if (!$temPermissao) {
        http_response_code(403);
        throw new Exception('Você não tem permissão para assinar documentos');
    }

    // Pega dados JSON do body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }

    if (empty($input['documentos_ids']) || !is_array($input['documentos_ids'])) {
        throw new Exception('Lista de documentos não informada');
    }

    $documentosIds = array_map('intval', $input['documentos_ids']);
    $observacao = $input['observacao'] ?? 'Assinatura em lote';

    error_log("Documentos para assinar: " . count($documentosIds));
    error_log("IDs: " . implode(', ', $documentosIds));

    // Cria instância da classe Documentos
    $documentos = new Documentos();

    // Assina documentos em lote
    $resultado = $documentos->assinarDocumentosLote($documentosIds, $observacao);

    if ($resultado['assinados'] > 0) {
        $response = [
            'status' => 'success',
            'message' => $resultado['assinados'] . ' de ' . $resultado['total'] . ' documentos assinados com sucesso',
            'data' => $resultado,
            'assinados' => $resultado['assinados']
        ];
        
        error_log("✓ Assinados em lote: {$resultado['assinados']}/{$resultado['total']} por " . $usuarioLogado['nome']);
    } else {
        throw new Exception('Nenhum documento foi assinado. Verifique os erros.');
    }

} catch (Exception $e) {
    error_log("❌ ERRO ao assinar em lote: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    if (http_response_code() === 200) {
        http_response_code(400);
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>