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

// Sistema flexível de permissões
$temPermissaoFuncionarios = true; // Todos têm acesso básico
$escopoVisualizacao = ''; // 'TODOS', 'DEPARTAMENTO' ou 'PROPRIO'
$departamentoPermitido = null;

// Log para debug
error_log("=== DEBUG API FUNCIONÁRIOS ===");
error_log("Usuário: " . $usuarioLogado['nome']);
error_log("Cargo: " . ($usuarioLogado['cargo'] ?? 'Sem cargo'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));

// Determinar escopo baseado no cargo e departamento
$cargoUsuario = $usuarioLogado['cargo'] ?? '';
$departamentoUsuario = $usuarioLogado['departamento_id'] ?? null;

// Cargos com permissões especiais
$cargosAcessoTotal = ['Diretor', 'Presidente', 'Vice-Presidente'];
$cargosAcessoDepartamento = ['Gerente', 'Supervisor', 'Coordenador'];

// Define escopo de visualização
if ($departamentoUsuario == 1) {
    // PRESIDÊNCIA - vê todos
    $escopoVisualizacao = 'TODOS';
    error_log("✅ PRESIDÊNCIA: API retornará TODOS os funcionários");
} elseif (in_array($cargoUsuario, $cargosAcessoTotal)) {
    // Cargos de alta gestão - veem todos
    $escopoVisualizacao = 'TODOS';
    error_log("✅ {$cargoUsuario}: API retornará TODOS os funcionários");
} elseif (in_array($cargoUsuario, $cargosAcessoDepartamento)) {
    // Cargos de gestão intermediária - veem seu departamento
    $escopoVisualizacao = 'DEPARTAMENTO';
    $departamentoPermitido = $departamentoUsuario;
    error_log("✅ {$cargoUsuario}: API retornará funcionários do departamento {$departamentoPermitido}");
} else {
    // Funcionários comuns - veem apenas seus dados
    $escopoVisualizacao = 'PROPRIO';
    error_log("✅ Funcionário comum: API retornará apenas dados próprios");
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
    
    // Aplicar filtros baseados no escopo do usuário
    if ($escopoVisualizacao === 'DEPARTAMENTO' && $departamentoPermitido) {
        // Gestores intermediários: força filtro pelo próprio departamento
        $filtros['departamento_id'] = $departamentoPermitido;
        error_log("🔒 Filtro forçado por departamento: " . $departamentoPermitido);
    } elseif ($escopoVisualizacao === 'PROPRIO') {
        // Funcionários comuns: retorna apenas seus próprios dados
        $filtros['id_especifico'] = $usuarioLogado['id'];
        error_log("🔒 Filtro forçado para ID próprio: " . $usuarioLogado['id']);
    }
    
    // Parâmetros especiais da requisição
    if (isset($_GET['departamento_filtro'])) {
        // Usado quando a página força um departamento específico
        if ($escopoVisualizacao === 'TODOS' || 
            ($escopoVisualizacao === 'DEPARTAMENTO' && $_GET['departamento_filtro'] == $departamentoPermitido)) {
            $filtros['departamento_id'] = $_GET['departamento_filtro'];
            error_log("🔍 Filtro de departamento aplicado: " . $_GET['departamento_filtro']);
        }
    }
    
    if (isset($_GET['proprio_id'])) {
        // Usado quando a página solicita dados de um ID específico
        if ($escopoVisualizacao === 'PROPRIO' && $_GET['proprio_id'] == $usuarioLogado['id']) {
            $filtros['id_especifico'] = $_GET['proprio_id'];
            error_log("🔍 Filtro de ID próprio aplicado: " . $_GET['proprio_id']);
        } elseif ($escopoVisualizacao !== 'PROPRIO') {
            // Outros escopos podem ver qualquer ID (com suas restrições)
            $filtros['id_especifico'] = $_GET['proprio_id'];
        }
    }
    
    // Paginação
    if (isset($_GET['limite'])) {
        $filtros['limite'] = intval($_GET['limite']);
    }
    if (isset($_GET['offset'])) {
        $filtros['offset'] = intval($_GET['offset']);
    }
    
    // Ordenação
    if (isset($_GET['ordenar_por'])) {
        $filtros['ordenar_por'] = $_GET['ordenar_por'];
    }
    
    // Buscar funcionários
    $lista = $funcionarios->listar($filtros);
    
    // Para escopo PROPRIO, adicionar informações extras do próprio usuário
    if ($escopoVisualizacao === 'PROPRIO' && count($lista) > 0) {
        // Adicionar badges e contribuições
        $lista[0]['badges'] = $funcionarios->getBadges($usuarioLogado['id']);
        $lista[0]['contribuicoes'] = $funcionarios->getContribuicoes($usuarioLogado['id']);
        $lista[0]['estatisticas'] = $funcionarios->getEstatisticas($usuarioLogado['id']);
    }
    
    // Contar total (para paginação)
    $total = $funcionarios->contar($filtros);
    
    // Log do resultado
    error_log("📊 Retornando " . count($lista) . " funcionários de " . $total . " total");
    
    // Preparar resposta
    $resposta = [
        'status' => 'success',
        'funcionarios' => $lista,
        'total' => $total,
        'mostrando' => count($lista),
        'escopo' => $escopoVisualizacao,
        'filtros_aplicados' => $filtros,
        'permissoes' => [
            'pode_criar' => in_array($escopoVisualizacao, ['TODOS', 'DEPARTAMENTO']),
            'pode_editar' => in_array($escopoVisualizacao, ['TODOS', 'DEPARTAMENTO', 'PROPRIO']),
            'pode_ver_todos' => $escopoVisualizacao === 'TODOS',
            'pode_ver_departamento' => in_array($escopoVisualizacao, ['TODOS', 'DEPARTAMENTO']),
            'departamento_permitido' => $departamentoPermitido
        ]
    ];
    
    echo json_encode($resposta);
    
} catch (Exception $e) {
    error_log("Erro ao listar funcionários: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar funcionários: ' . $e->getMessage()
    ]);
}
?>