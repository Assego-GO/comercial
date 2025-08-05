<?php
/**
 * API para criar funcionário
 * api/funcionarios_criar.php
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
$cargoUsuario = $usuarioLogado['cargo'] ?? '';
$departamentoUsuario = $usuarioLogado['departamento_id'] ?? null;

// Verificar permissão para criar funcionários
$podeCriar = false;
$departamentosPermitidos = [];

// Cargos que podem criar funcionários
$cargosComPermissaoCriar = ['Diretor', 'Presidente', 'Vice-Presidente', 'Gerente', 'Supervisor', 'Coordenador'];

if ($departamentoUsuario == 1) {
    // Presidência pode criar em qualquer departamento
    $podeCriar = true;
    $departamentosPermitidos = 'TODOS';
} elseif (in_array($cargoUsuario, ['Diretor', 'Presidente', 'Vice-Presidente'])) {
    // Alta gestão pode criar em qualquer departamento
    $podeCriar = true;
    $departamentosPermitidos = 'TODOS';
} elseif (in_array($cargoUsuario, ['Gerente', 'Supervisor', 'Coordenador'])) {
    // Gestão intermediária pode criar apenas em seu departamento
    $podeCriar = true;
    $departamentosPermitidos = [$departamentoUsuario];
} else {
    // Funcionários comuns não podem criar
    $podeCriar = false;
}

if (!$podeCriar) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Você não tem permissão para criar funcionários']);
    exit;
}

// Pegar dados do POST
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

try {
    $funcionarios = new Funcionarios();
    
    // Validações básicas
    if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
        throw new Exception('Preencha todos os campos obrigatórios');
    }
    
    // Validar email
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    // Validar senha
    if (strlen($dados['senha']) < 6) {
        throw new Exception('A senha deve ter no mínimo 6 caracteres');
    }
    
    // Validar departamento
    if (isset($dados['departamento_id']) && $dados['departamento_id']) {
        // Verificar se o usuário pode criar neste departamento
        if ($departamentosPermitidos !== 'TODOS' && 
            !in_array($dados['departamento_id'], $departamentosPermitidos)) {
            throw new Exception('Você só pode criar funcionários em seu próprio departamento');
        }
    } else if ($departamentosPermitidos !== 'TODOS') {
        // Se não foi especificado departamento e o usuário tem restrição, usar o dele
        $dados['departamento_id'] = $departamentoUsuario;
    }
    
    // Validar cargo - gestores intermediários não podem criar cargos superiores
    if (in_array($cargoUsuario, ['Gerente', 'Supervisor', 'Coordenador'])) {
        $cargosSuperiores = ['Diretor', 'Presidente', 'Vice-Presidente'];
        if (isset($dados['cargo']) && in_array($dados['cargo'], $cargosSuperiores)) {
            throw new Exception('Você não pode criar funcionários com cargo superior ao seu');
        }
    }
    
    // Log da criação
    error_log("=== CRIANDO FUNCIONÁRIO ===");
    error_log("Criado por: {$usuarioLogado['nome']} ({$cargoUsuario})");
    error_log("Nome: {$dados['nome']}");
    error_log("Email: {$dados['email']}");
    error_log("Departamento: " . ($dados['departamento_id'] ?? 'Não especificado'));
    error_log("Cargo: " . ($dados['cargo'] ?? 'Não especificado'));
    
    // Criar funcionário
    $funcionario_id = $funcionarios->criar($dados);
    
    // Buscar dados completos do funcionário criado
    $funcionarioCriado = $funcionarios->getById($funcionario_id);
    
    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Funcionário criado com sucesso',
        'funcionario_id' => $funcionario_id,
        'funcionario' => [
            'id' => $funcionarioCriado['id'],
            'nome' => $funcionarioCriado['nome'],
            'email' => $funcionarioCriado['email'],
            'departamento_nome' => $funcionarioCriado['departamento_nome'] ?? 'Sem departamento',
            'cargo' => $funcionarioCriado['cargo'] ?? 'Sem cargo'
        ],
        'info' => [
            'senha_padrao' => 'Assego@123',
            'criado_por' => $usuarioLogado['nome'],
            'data_criacao' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao criar funcionário: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>