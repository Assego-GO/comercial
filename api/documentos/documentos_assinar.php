<?php
/**
 * API para assinar documentos na presidência
 * api/documentos/documentos_assinar.php
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

    // Inicia sessão se não estiver iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    error_log("=== API ASSINAR DOCUMENTO ===");

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        throw new Exception('Usuário não autenticado');
    }

    // Pega dados do usuário
    $usuarioLogado = $auth->getUser();
    error_log("Usuário: " . $usuarioLogado['nome']);

    // Verifica permissão (diretor ou presidência)
    $temPermissao = false;
    if ($auth->isDiretor() || (isset($usuarioLogado['departamento_id']) && $usuarioLogado['departamento_id'] == 1)) {
        $temPermissao = true;
    }

    if (!$temPermissao) {
        http_response_code(403);
        throw new Exception('Você não tem permissão para assinar documentos');
    }

    // Validação dos parâmetros
    if (empty($_POST['documento_id'])) {
        throw new Exception('ID do documento não informado');
    }

    $documentoId = intval($_POST['documento_id']);
    $observacao = $_POST['observacao'] ?? null;
    $metodo = $_POST['metodo'] ?? 'digital';
    
    error_log("Documento ID: $documentoId | Método: $metodo");

    // Cria instância da classe Documentos
    $documentos = new Documentos();

    // Verifica se é upload de arquivo assinado
    $arquivoAssinado = null;
    if ($metodo === 'upload' && isset($_FILES['arquivo_assinado'])) {
        if ($_FILES['arquivo_assinado']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erro no upload do arquivo');
        }
        
        // Validar arquivo PDF
        $extensao = strtolower(pathinfo($_FILES['arquivo_assinado']['name'], PATHINFO_EXTENSION));
        if ($extensao !== 'pdf') {
            throw new Exception('Apenas arquivos PDF são permitidos');
        }
        
        // Validar tamanho (10MB)
        if ($_FILES['arquivo_assinado']['size'] > 10485760) {
            throw new Exception('Arquivo muito grande. Máximo: 10MB');
        }
        
        $arquivoAssinado = $_FILES['arquivo_assinado'];
        error_log("Arquivo assinado recebido: " . $arquivoAssinado['name']);
    }

    // Assina o documento
    $resultado = $documentos->assinarDocumento($documentoId, $arquivoAssinado, $observacao);

    if ($resultado) {
        $response = [
            'status' => 'success',
            'message' => 'Documento assinado com sucesso!',
            'data' => [
                'documento_id' => $documentoId,
                'assinado_por' => $usuarioLogado['nome'],
                'metodo' => $metodo,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        error_log("✓ Documento $documentoId assinado com sucesso por " . $usuarioLogado['nome']);
    } else {
        throw new Exception('Erro ao assinar documento');
    }

} catch (Exception $e) {
    error_log("❌ ERRO ao assinar: " . $e->getMessage());
    
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