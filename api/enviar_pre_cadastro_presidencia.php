<?php
/**
 * API para enviar pré-cadastro para presidência
 * api/enviar_pre_cadastro_presidencia.php
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';
    require_once '../classes/Associados.php';

    session_start();
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['associado_id'])) {
        throw new Exception('ID do associado não fornecido');
    }

    $associados = new Associados();
    $resultado = $associados->enviarParaPresidencia(
        $data['associado_id'],
        $data['observacoes'] ?? null
    );

    if ($resultado) {
        $response = [
            'status' => 'success',
            'message' => 'Pré-cadastro enviado para presidência com sucesso!'
        ];
    } else {
        throw new Exception('Erro ao enviar para presidência');
    }

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// ===================================================================

/**
 * API para aprovar pré-cadastro
 * api/aprovar_pre_cadastro.php
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';
    require_once '../classes/Associados.php';

    session_start();
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Verifica se usuário tem permissão (pode adicionar verificação mais específica)
    // Por enquanto, apenas verifica se está logado

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['associado_id'])) {
        throw new Exception('ID do associado não fornecido');
    }

    // Processa upload do documento assinado se houver
    $documentoAssinado = null;
    if (isset($_FILES['documento_assinado'])) {
        $uploadDir = '../uploads/documentos_assinados/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extensao = pathinfo($_FILES['documento_assinado']['name'], PATHINFO_EXTENSION);
        $nomeArquivo = 'assinado_' . $data['associado_id'] . '_' . date('YmdHis') . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        
        if (move_uploaded_file($_FILES['documento_assinado']['tmp_name'], $caminhoCompleto)) {
            $documentoAssinado = 'uploads/documentos_assinados/' . $nomeArquivo;
        }
    }

    $associados = new Associados();
    $resultado = $associados->aprovarPreCadastro(
        $data['associado_id'],
        $documentoAssinado,
        $data['observacoes'] ?? null
    );

    if ($resultado) {
        $response = [
            'status' => 'success',
            'message' => 'Pré-cadastro aprovado com sucesso! O associado agora está ativo no sistema.'
        ];
    } else {
        throw new Exception('Erro ao aprovar pré-cadastro');
    }

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// ===================================================================

/**
 * API para rejeitar pré-cadastro
 * api/rejeitar_pre_cadastro.php
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';
    require_once '../classes/Associados.php';

    session_start();
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['associado_id'])) {
        throw new Exception('ID do associado não fornecido');
    }
    
    if (empty($data['motivo'])) {
        throw new Exception('Motivo da rejeição não fornecido');
    }

    $associados = new Associados();
    $resultado = $associados->rejeitarPreCadastro(
        $data['associado_id'],
        $data['motivo']
    );

    if ($resultado) {
        $response = [
            'status' => 'success',
            'message' => 'Pré-cadastro rejeitado.'
        ];
    } else {
        throw new Exception('Erro ao rejeitar pré-cadastro');
    }

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;

// ===================================================================

/**
 * API para listar pré-cadastros
 * api/listar_pre_cadastros.php
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';
    require_once '../classes/Associados.php';

    session_start();
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $filtros = [
        'pre_cadastro' => 1,
        'limit' => $_GET['limit'] ?? 50,
        'offset' => $_GET['offset'] ?? 0
    ];

    // Adiciona filtros opcionais
    if (isset($_GET['status_fluxo'])) {
        $filtros['status_fluxo'] = $_GET['status_fluxo'];
    }
    
    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }

    $associados = new Associados();
    $lista = $associados->listar($filtros);
    $estatisticas = $associados->contarPreCadastrosPorStatus();

    $response = [
        'status' => 'success',
        'data' => $lista,
        'estatisticas' => $estatisticas,
        'total' => count($lista)
    ];

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;