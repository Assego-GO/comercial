<?php
/**
 * API para buscar histórico de assinaturas
 * api/documentos/historico_assinaturas.php
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
    'data' => null
];

try {
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

    error_log("=== API HISTÓRICO ASSINATURAS ===");

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
        throw new Exception('Você não tem permissão para acessar o histórico');
    }

    // Parâmetros da query
    $periodo = intval($_GET['periodo'] ?? 30);
    $funcionarioId = $_GET['funcionario_id'] ?? null;

    // Se funcionarioId estiver vazio, buscar todos
    if (empty($funcionarioId)) {
        $funcionarioId = null;
    } else {
        $funcionarioId = intval($funcionarioId);
    }

    error_log("Período: $periodo dias | Funcionário: " . ($funcionarioId ?? 'Todos'));

    // Cria instância da classe Documentos
    $documentos = new Documentos();

    // Busca histórico
    $historico = $documentos->getHistoricoAssinaturas($funcionarioId, $periodo);

    // Preparar dados de resposta
    $dados = [
        'historico' => $historico,
        'resumo' => [
            'total_assinados' => count($historico),
            'tempo_medio' => 0,
            'origem_fisica' => 0,
            'origem_virtual' => 0
        ]
    ];

    // Calcular resumo
    if (count($historico) > 0) {
        $totalTempo = 0;
        foreach ($historico as $item) {
            $totalTempo += $item['tempo_processamento'] ?? 0;
            
            if ($item['tipo_origem'] === 'FISICO') {
                $dados['resumo']['origem_fisica']++;
            } else {
                $dados['resumo']['origem_virtual']++;
            }
        }
        
        $dados['resumo']['tempo_medio'] = $totalTempo / count($historico);
    }

    $response = [
        'status' => 'success',
        'message' => 'Histórico carregado com sucesso',
        'data' => $dados
    ];
    
    error_log("✓ Histórico carregado: " . count($historico) . " registros");

} catch (Exception $e) {
    error_log("❌ ERRO no histórico: " . $e->getMessage());
    
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