<?php
/**
 * API para listar documentos pendentes de assinatura na presidência
 * api/documentos/documentos_presidencia_listar.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => []
];

try {
    // Carrega arquivos necessários - ajuste o caminho relativo
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Documentos.php';

    // Inicia sessão se não estiver iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Log inicial detalhado
    error_log("=== API DOCUMENTOS PRESIDÊNCIA ===");
    error_log("Session ID: " . session_id());
    error_log("User ID: " . ($_SESSION['user_id'] ?? 'N/A'));
    error_log("Funcionario ID: " . ($_SESSION['funcionario_id'] ?? 'N/A'));

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        error_log("❌ Usuário não autenticado");
        http_response_code(401);
        throw new Exception('Usuário não autenticado');
    }

    // Pega dados do usuário
    $usuarioLogado = $auth->getUser();
    error_log("✓ Usuário autenticado: " . $usuarioLogado['nome']);
    error_log("Cargo: " . ($usuarioLogado['cargo'] ?? 'N/A'));
    error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'N/A'));
    error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));

    // Verifica permissão para acessar a presidência
    $temPermissao = false;
    
    // Permite acesso se:
    // 1. É diretor OU
    // 2. Está no departamento da presidência (ID = 1)
    if ($auth->isDiretor()) {
        $temPermissao = true;
        error_log("✓ Acesso permitido: É DIRETOR");
    } elseif (isset($usuarioLogado['departamento_id']) && $usuarioLogado['departamento_id'] == 1) {
        $temPermissao = true;
        error_log("✓ Acesso permitido: Departamento Presidência (ID=1)");
    } else {
        error_log("❌ Acesso negado - Não é diretor e departamento_id = " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
    }

    if (!$temPermissao) {
        http_response_code(403);
        throw new Exception('Acesso negado. Você precisa ser diretor ou estar no departamento da presidência.');
    }

    // Cria instância da classe Documentos
    $documentos = new Documentos();

    // Prepara filtros da query string
    $filtros = [];
    
    // Status - por padrão busca AGUARDANDO_ASSINATURA
    $filtros['status'] = $_GET['status'] ?? 'AGUARDANDO_ASSINATURA';
    
    // Outros filtros opcionais
    if (isset($_GET['urgencia'])) {
        $filtros['urgencia'] = $_GET['urgencia'];
    }
    
    if (isset($_GET['origem'])) {
        $filtros['origem'] = $_GET['origem'];
    }
    
    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }

    error_log("Filtros aplicados: " . json_encode($filtros));

    // Busca documentos usando o método específico para presidência
    $listaDocumentos = $documentos->listarDocumentosPresidencia($filtros);
    
    error_log("Documentos encontrados: " . count($listaDocumentos));

    // Formata resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => count($listaDocumentos) . ' documento(s) encontrado(s)',
        'data' => $listaDocumentos,
        'meta' => [
            'total' => count($listaDocumentos),
            'filtros' => $filtros,
            'usuario' => $usuarioLogado['nome'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    // Log de sucesso
    error_log("✓ API executada com sucesso. Total de documentos: " . count($listaDocumentos));

} catch (Exception $e) {
    error_log("❌ ERRO na API: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => [],
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'funcionario_id' => $_SESSION['funcionario_id'] ?? null
        ]
    ];
    
    // Define código HTTP apropriado se ainda não foi definido
    if (http_response_code() === 200) {
        http_response_code(400);
    }
}

// Envia resposta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>