<?php
/**
 * API para listar funcionários
 * api/funcionarios_listar.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// NOVA LÓGICA: Verificar permissões e determinar escopo de visualização
$temPermissaoFuncionarios = false;
$escopoVisualizacao = ''; // 'TODOS' para presidência, 'DEPARTAMENTO' para diretor
$departamentoPermitido = null;

// Log para debug
error_log("=== DEBUG API FUNCIONÁRIOS ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));

if (isset($usuarioLogado['departamento_id']) && $usuarioLogado['departamento_id'] == 1) {
    // Funcionários da PRESIDÊNCIA veem TODOS os funcionários
    $temPermissaoFuncionarios = true;
    $escopoVisualizacao = 'TODOS';
    error_log("✅ PRESIDÊNCIA: API retornará TODOS os funcionários");
} elseif ($auth->isDiretor()) {
    // DIRETORES veem apenas funcionários do SEU departamento
    $temPermissaoFuncionarios = true;
    $escopoVisualizacao = 'DEPARTAMENTO';
    $departamentoPermitido = $usuarioLogado['departamento_id'];
    error_log("✅ DIRETOR: API retornará apenas funcionários do departamento " . $departamentoPermitido);
} else {
    error_log("❌ SEM PERMISSÃO para API de funcionários");
}

if (!$temPermissaoFuncionarios) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso restrito a diretores e funcionários da presidência']);
    exit;
}

try {
    $funcionarios = new Funcionarios();
    
    // Filtros opcionais vindos da requisição
    $filtros = [];
    if (isset($_GET['ativo'])) {
        $filtros['ativo'] = $_GET['ativo'];
    }
    if (isset($_GET['departamento_id'])) {
        $filtros['departamento_id'] = $_GET['departamento_id'];
    }
    if (isset($_GET['cargo'])) {
        $filtros['cargo'] = $_GET['cargo'];
    }
    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }
    
    // NOVO FILTRO: Baseado no escopo do usuário
    if ($escopoVisualizacao === 'DEPARTAMENTO' && $departamentoPermitido) {
        // Diretor: força filtro pelo próprio departamento
        $filtros['departamento_id'] = $departamentoPermitido;
        error_log("🔒 Filtro forçado por departamento: " . $departamentoPermitido);
    } elseif (isset($_GET['departamento_filtro'])) {
        // Parâmetro específico para filtro de departamento (usado internamente)
        $filtros['departamento_id'] = $_GET['departamento_filtro'];
        error_log("🔍 Filtro de departamento aplicado: " . $_GET['departamento_filtro']);
    }
    
    $lista = $funcionarios->listar($filtros);
    
    // Log do resultado
    error_log("📊 Retornando " . count($lista) . " funcionários");
    
    echo json_encode([
        'status' => 'success',
        'funcionarios' => $lista,
        'total' => count($lista),
        'escopo' => $escopoVisualizacao, // Para debug
        'filtros_aplicados' => $filtros // Para debug
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar funcionários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar funcionários: ' . $e->getMessage()
    ]);
}
?>