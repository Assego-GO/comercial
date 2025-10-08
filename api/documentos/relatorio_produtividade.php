<?php
/**
 * API para gerar relatório de produtividade da presidência
 * api/documentos/relatorio_produtividade.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Se for OPTIONS (preflight), retornar OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => null,
    'debug' => []
];

try {
    // Adicionar informações de debug
    $response['debug']['method'] = $_SERVER['REQUEST_METHOD'];
    $response['debug']['content_type'] = $_SERVER['CONTENT_TYPE'] ?? 'não definido';
    
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

    error_log("=== API RELATÓRIO PRODUTIVIDADE ===");
    error_log("Session ID: " . session_id());
    error_log("Session data: " . print_r($_SESSION, true));

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        throw new Exception('Usuário não autenticado');
    }

    // Pega dados do usuário
    $usuarioLogado = $auth->getUser();
    $response['debug']['usuario'] = $usuarioLogado['nome'] ?? 'desconhecido';

    // Verifica permissão
    $temPermissao = false;
    if ($auth->isDiretor() || (isset($usuarioLogado['departamento_id']) && $usuarioLogado['departamento_id'] == 1)) {
        $temPermissao = true;
    }

    if (!$temPermissao) {
        http_response_code(403);
        throw new Exception('Você não tem permissão para acessar relatórios');
    }

    // Pega dados JSON do body
    $inputRaw = file_get_contents('php://input');
    $response['debug']['input_raw'] = substr($inputRaw, 0, 100) . '...'; // Primeiros 100 chars
    
    $input = json_decode($inputRaw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro ao decodificar JSON: ' . json_last_error_msg());
    }
    
    if (!$input) {
        throw new Exception('Dados inválidos ou vazios');
    }

    $dataInicio = $input['data_inicio'] ?? null;
    $dataFim = $input['data_fim'] ?? null;

    $response['debug']['data_inicio'] = $dataInicio;
    $response['debug']['data_fim'] = $dataFim;

    if (!$dataInicio || !$dataFim) {
        throw new Exception('Período não informado. Início: ' . ($dataInicio ?? 'null') . ', Fim: ' . ($dataFim ?? 'null'));
    }

    // Valida datas
    $inicio = DateTime::createFromFormat('Y-m-d', $dataInicio);
    $fim = DateTime::createFromFormat('Y-m-d', $dataFim);
    
    if (!$inicio || !$fim) {
        throw new Exception('Formato de data inválido. Use Y-m-d (ex: 2025-07-31)');
    }

    if ($inicio > $fim) {
        throw new Exception('Data inicial não pode ser maior que data final');
    }

    error_log("Período validado: $dataInicio a $dataFim");

    // Verifica se a classe Documentos existe e tem o método
    if (!class_exists('Documentos')) {
        throw new Exception('Classe Documentos não encontrada');
    }

    $documentos = new Documentos();
    
    if (!method_exists($documentos, 'getRelatorioProdutividade')) {
        throw new Exception('Método getRelatorioProdutividade não encontrado na classe Documentos');
    }

    // Busca relatório
    try {
        $relatorio = $documentos->getRelatorioProdutividade($dataInicio, $dataFim);
        
        if ($relatorio === false || $relatorio === null) {
            throw new Exception('Método retornou dados inválidos');
        }
        
        $response = [
            'status' => 'success',
            'message' => 'Relatório gerado com sucesso',
            'data' => $relatorio,
            'debug' => null // Remover debug em produção
        ];
        
        error_log("✓ Relatório gerado com sucesso para período $dataInicio a $dataFim");
        error_log("Dados retornados: " . print_r($relatorio, true));
        
    } catch (Exception $e) {
        error_log("Erro ao chamar getRelatorioProdutividade: " . $e->getMessage());
        throw new Exception('Erro ao gerar relatório: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("❌ ERRO no relatório: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null,
        'debug' => AMBIENTE === 'desenvolvimento' ? $response['debug'] : null
    ];
    
    // Se não foi definido um código HTTP específico, usar 400
    if (http_response_code() === 200) {
        http_response_code(400);
    }
}

// Garantir que a resposta seja válida JSON
$jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($jsonResponse === false) {
    error_log("Erro ao codificar resposta JSON: " . json_last_error_msg());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao gerar resposta JSON',
        'data' => null
    ]);
} else {
    echo $jsonResponse;
}

exit;
?>