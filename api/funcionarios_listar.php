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

// CORREÇÃO: Lista de departamentos estratégicos que veem todos os funcionários
// IDs baseados no banco de dados real
$departamentosEstrategicos = [
    1,  // Presidência (ID: 1)
    2,  // Financeiro (ID: 2) 
    9,  // Recursos Humanos (ID: 9) - CORRIGIDO
    10, // Comercial (ID: 10) - CORRIGIDO
];

// Função para obter nome do departamento por ID (para logs)
function getNomeDepartamento($departamentoId) {
    $nomes = [
        1  => 'Presidência',
        2  => 'Financeiro',
        3  => 'Jurídico',
        4  => 'Hotel',
        5  => 'Comunicação',
        6  => 'Patrimônio',
        7  => 'Aruana',
        8  => 'Compras',
        9  => 'Recursos Humanos',
        10 => 'Comercial',
        11 => 'Parque Aquático',
        12 => 'Social',
        13 => 'Obras',
        14 => 'Geral',
        15 => 'Tecnologia da Informação',
        16 => 'Convênios',
        17 => 'Paisagismo',
        18 => 'Relacionamento',
    ];
    return $nomes[$departamentoId] ?? "Departamento {$departamentoId}";
}

// Sistema flexível de permissões
$temPermissaoFuncionarios = true; // Todos têm acesso básico
$escopoVisualizacao = ''; // 'TODOS', 'DEPARTAMENTO' ou 'PROPRIO'
$departamentoPermitido = null;

// Log detalhado para debug
error_log("=== DEBUG API FUNCIONÁRIOS ===");
error_log("Usuário: " . ($usuarioLogado['nome'] ?? 'NULL'));
error_log("ID do Usuário: " . ($usuarioLogado['id'] ?? 'NULL'));
error_log("Cargo: " . ($usuarioLogado['cargo'] ?? 'Sem cargo'));
error_log("É Diretor: " . ($auth->isDiretor() ? 'SIM' : 'NÃO'));
error_log("Departamento ID: " . ($usuarioLogado['departamento_id'] ?? 'NULL'));
error_log("Departamento: " . getNomeDepartamento($usuarioLogado['departamento_id'] ?? 0));

// Para debug: log dos departamentos estratégicos
error_log("Departamentos com acesso total configurados na API:");
foreach ($departamentosEstrategicos as $deptId) {
    error_log("- ID {$deptId}: " . getNomeDepartamento($deptId));
}

// Determinar escopo baseado no cargo e departamento
$cargoUsuario = $usuarioLogado['cargo'] ?? '';
$departamentoUsuario = $usuarioLogado['departamento_id'] ?? null;

// IMPORTANTE: Garantir que seja inteiro para comparação
$departamentoUsuario = (int)$departamentoUsuario;

error_log("Processando permissões na API:");
error_log("Cargo: '{$cargoUsuario}'");
error_log("Departamento (convertido para int): {$departamentoUsuario}");
error_log("Departamentos estratégicos: " . implode(', ', $departamentosEstrategicos));

// CORREÇÃO: Nova lógica de permissões com departamentos estratégicos
// Define escopo de visualização
error_log("Verificando permissões na API...");
error_log("Departamento do usuário: {$departamentoUsuario} (tipo: " . gettype($departamentoUsuario) . ")");
error_log("Testando se {$departamentoUsuario} está em [" . implode(', ', $departamentosEstrategicos) . "]");

$estaNoDepartamentoEstrategico = in_array($departamentoUsuario, $departamentosEstrategicos, true);
error_log("Está em departamento estratégico? " . ($estaNoDepartamentoEstrategico ? 'SIM' : 'NÃO'));

if ($estaNoDepartamentoEstrategico) {
    // DEPARTAMENTOS ESTRATÉGICOS - veem todos os funcionários
    $escopoVisualizacao = 'TODOS';
    $nomeDept = getNomeDepartamento($departamentoUsuario);
    error_log("✅ DEPARTAMENTO ESTRATÉGICO ({$nomeDept}): API retornará TODOS os funcionários");
} elseif (in_array($cargoUsuario, ['Presidente', 'Vice-Presidente'])) {
    // Presidente e Vice-Presidente sempre veem todos (independente do departamento)
    $escopoVisualizacao = 'TODOS';
    error_log("✅ {$cargoUsuario}: API retornará TODOS os funcionários");
} elseif (in_array($cargoUsuario, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador'])) {
    // Cargos de liderança em departamentos não-estratégicos - veem apenas seu departamento
    $escopoVisualizacao = 'DEPARTAMENTO';
    $departamentoPermitido = $departamentoUsuario;
    $nomeDept = getNomeDepartamento($departamentoUsuario);
    error_log("✅ {$cargoUsuario} - {$nomeDept}: API retornará funcionários do departamento {$departamentoPermitido}");
} else {
    // Funcionários comuns - veem apenas seus dados
    $escopoVisualizacao = 'PROPRIO';
    error_log("✅ Funcionário comum: API retornará apenas dados próprios");
}

// Log adicional para debug
error_log("=== RESULTADO FINAL DAS PERMISSÕES NA API ===");
error_log("Escopo final: {$escopoVisualizacao}");
error_log("Departamento permitido: " . ($departamentoPermitido ?? 'NULL'));

// Verificação final para garantir que o RH vê todos
if ($departamentoUsuario === 9 && $escopoVisualizacao !== 'TODOS') {
    error_log("❌ ERRO NA API: Usuário do RH (dept 9) deveria ter escopo TODOS mas tem: {$escopoVisualizacao}");
}

try {
    $funcionarios = new Funcionarios();
    
    // 🔧 CORREÇÃO 1: PAGINAÇÃO - CONVERSÃO DE PÁGINA PARA OFFSET
    $pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
    $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 10;

    // Validar parâmetros
    if ($pagina < 1) $pagina = 1;
    if ($limite < 1 || $limite > 100) $limite = 10;

    // CALCULAR OFFSET CORRETAMENTE - AQUI ESTAVA O BUG!
    $offset = ($pagina - 1) * $limite;

    // Log para debug
    error_log("=== PAGINAÇÃO API CORRIGIDA ===");
    error_log("Página recebida: " . ($_GET['pagina'] ?? 'null'));
    error_log("Limite recebido: " . ($_GET['limite'] ?? 'null'));
    error_log("Página processada: {$pagina}");
    error_log("Limite processado: {$limite}");
    error_log("OFFSET calculado: {$offset}");
    
    // 🔧 CORREÇÃO 2: FILTROS - MAPEAMENTO CORRETO
    $filtros = [];
    
    // ✅ FILTRO DE STATUS (frontend envia 'status', convertemos para 'ativo')
    if (isset($_GET['status']) && $_GET['status'] !== '') {
    // Frontend envia "1" para ativo e "0" para inativo
    if ($_GET['status'] === '1') {
        $filtros['ativo'] = 1;
        error_log("🔍 Filtro de status aplicado: Ativo (1)");
    } elseif ($_GET['status'] === '0') {
        $filtros['ativo'] = 0;
        error_log("🔍 Filtro de status aplicado: Inativo (0)");
    }
}

    // ✅ FILTRO DE DEPARTAMENTO (frontend envia 'departamento', convertemos para 'departamento_id')
    if (isset($_GET['departamento']) && !empty($_GET['departamento'])) {
        $filtros['departamento_id'] = $_GET['departamento'];
        error_log("🔍 Filtro de departamento aplicado: " . $_GET['departamento']);
    }

    // ✅ FILTRO DE CARGO (já estava correto)
    if (isset($_GET['cargo']) && !empty($_GET['cargo'])) {
        $filtros['cargo'] = $_GET['cargo'];
        error_log("🔍 Filtro de cargo aplicado: " . $_GET['cargo']);
    }

    // ✅ FILTRO DE BUSCA (já estava correto)
    if (isset($_GET['busca']) && !empty($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
        error_log("🔍 Filtro de busca aplicado: " . $_GET['busca']);
    }
    
    error_log("=== APLICANDO FILTROS NA API ===");
    error_log("Escopo de visualização: {$escopoVisualizacao}");
    
    // Aplicar filtros baseados no escopo do usuário
    if ($escopoVisualizacao === 'DEPARTAMENTO' && $departamentoPermitido) {
        // Gestores intermediários: força filtro pelo próprio departamento
        $filtros['departamento_id'] = $departamentoPermitido;
        error_log("🔒 Filtro forçado por departamento: " . $departamentoPermitido);
    } elseif ($escopoVisualizacao === 'PROPRIO') {
        // Funcionários comuns: retorna apenas seus próprios dados
        $filtros['id_especifico'] = $usuarioLogado['id'];
        error_log("🔒 Filtro forçado para ID próprio: " . $usuarioLogado['id']);
    } else {
        error_log("🔓 Sem filtro - API retornará todos os funcionários conforme filtros solicitados");
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
    
    // Aplicar paginação aos filtros
    $filtros['limite'] = $limite;
    $filtros['offset'] = $offset;
    
    // Ordenação
    if (isset($_GET['ordenar_por'])) {
        $filtros['ordenar_por'] = $_GET['ordenar_por'];
    }
    
    error_log("Filtros finais aplicados: " . print_r($filtros, true));
    
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
    error_log("📊 API retornando " . count($lista) . " funcionários de " . $total . " total");
    
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
        ],
        'debug_info' => [
            'departamento_usuario' => $departamentoUsuario,
            'cargo_usuario' => $cargoUsuario,
            'departamentos_estrategicos' => $departamentosEstrategicos,
            'esta_em_dept_estrategico' => $estaNoDepartamentoEstrategico
        ]
    ];
    
    echo json_encode($resposta);
    
} catch (Exception $e) {
    error_log("Erro ao listar funcionários na API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar funcionários: ' . $e->getMessage()
    ]);
}
?>