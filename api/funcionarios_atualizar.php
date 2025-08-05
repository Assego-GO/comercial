<?php
/**
 * API para atualizar funcionário
 * api/funcionarios_atualizar.php
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

// Pegar dados do PUT
$dados = json_decode(file_get_contents('php://input'), true);

if (!$dados || !isset($dados['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
    exit;
}

$funcionarioId = $dados['id'];
unset($dados['id']); // Remove ID dos dados de atualização

try {
    $funcionarios = new Funcionarios();
    
    // Buscar dados do funcionário que será atualizado
    $funcionarioAtual = $funcionarios->getById($funcionarioId);
    if (!$funcionarioAtual) {
        throw new Exception('Funcionário não encontrado');
    }
    
    // Verificar permissão para editar este funcionário
    $podeEditar = false;
    $restricoes = [];
    
    // Log de verificação de permissão
    error_log("=== VERIFICANDO PERMISSÃO DE EDIÇÃO ===");
    error_log("Usuário: {$usuarioLogado['nome']} (ID: {$usuarioLogado['id']})");
    error_log("Cargo: {$cargoUsuario}");
    error_log("Departamento: {$departamentoUsuario}");
    error_log("Editando funcionário: {$funcionarioAtual['nome']} (ID: {$funcionarioId})");
    error_log("Departamento do funcionário: {$funcionarioAtual['departamento_id']}");
    
    // Regras de permissão
    if ($funcionarioId == $usuarioLogado['id']) {
        // Editando próprio perfil
        $podeEditar = true;
        $restricoes = ['departamento_id', 'cargo', 'ativo']; // Não pode alterar estes campos
        error_log("✅ Editando próprio perfil - permitido com restrições");
    } elseif ($departamentoUsuario == 1) {
        // Presidência pode editar qualquer um
        $podeEditar = true;
        error_log("✅ Presidência - pode editar qualquer funcionário");
    } elseif (in_array($cargoUsuario, ['Diretor', 'Presidente', 'Vice-Presidente'])) {
        // Alta gestão pode editar qualquer um
        $podeEditar = true;
        error_log("✅ Alta gestão - pode editar qualquer funcionário");
    } elseif (in_array($cargoUsuario, ['Gerente', 'Supervisor', 'Coordenador'])) {
        // Gestão intermediária pode editar apenas funcionários do mesmo departamento
        if ($funcionarioAtual['departamento_id'] == $departamentoUsuario) {
            $podeEditar = true;
            
            // Não pode editar funcionários com cargo igual ou superior
            $cargosSuperiores = ['Diretor', 'Presidente', 'Vice-Presidente', 'Gerente'];
            if ($cargoUsuario === 'Supervisor') {
                $cargosSuperiores[] = 'Supervisor';
            } elseif ($cargoUsuario === 'Coordenador') {
                $cargosSuperiores[] = 'Supervisor';
                $cargosSuperiores[] = 'Coordenador';
            }
            
            if (in_array($funcionarioAtual['cargo'], $cargosSuperiores)) {
                $podeEditar = false;
                error_log("❌ Não pode editar funcionário com cargo igual ou superior");
            } else {
                error_log("✅ Gestão intermediária - pode editar funcionário do mesmo departamento");
            }
        } else {
            error_log("❌ Gestão intermediária - funcionário de outro departamento");
        }
    } else {
        // Funcionários comuns só podem editar o próprio perfil
        error_log("❌ Funcionário comum - sem permissão");
    }
    
    if (!$podeEditar) {
        throw new Exception('Você não tem permissão para editar este funcionário');
    }
    
    // Aplicar restrições se houver
    if (!empty($restricoes)) {
        foreach ($restricoes as $campo) {
            if (isset($dados[$campo])) {
                unset($dados[$campo]);
                error_log("⚠️ Campo '{$campo}' removido devido a restrições");
            }
        }
    }
    
    // Validações adicionais baseadas em permissões
    
    // Validar mudança de departamento
    if (isset($dados['departamento_id']) && $dados['departamento_id'] != $funcionarioAtual['departamento_id']) {
        if ($departamentoUsuario != 1 && !in_array($cargoUsuario, ['Diretor', 'Presidente', 'Vice-Presidente'])) {
            // Gestores intermediários não podem mover funcionários entre departamentos
            if (in_array($cargoUsuario, ['Gerente', 'Supervisor', 'Coordenador'])) {
                throw new Exception('Você não pode transferir funcionários entre departamentos');
            }
        }
    }
    
    // Validar mudança de cargo
    if (isset($dados['cargo']) && $dados['cargo'] != $funcionarioAtual['cargo']) {
        // Gestores intermediários não podem promover para cargos superiores ao seu
        if (in_array($cargoUsuario, ['Gerente', 'Supervisor', 'Coordenador'])) {
            $cargosSuperiores = ['Diretor', 'Presidente', 'Vice-Presidente'];
            if ($cargoUsuario === 'Supervisor' || $cargoUsuario === 'Coordenador') {
                $cargosSuperiores[] = 'Gerente';
            }
            if ($cargoUsuario === 'Coordenador') {
                $cargosSuperiores[] = 'Supervisor';
            }
            
            if (in_array($dados['cargo'], $cargosSuperiores)) {
                throw new Exception('Você não pode promover funcionários para cargos superiores ao seu');
            }
        }
    }
    
    // Validar email
    if (isset($dados['email']) && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    // Validar senha se fornecida
    if (isset($dados['senha']) && strlen($dados['senha']) < 6) {
        throw new Exception('A senha deve ter no mínimo 6 caracteres');
    }
    
    // Log das alterações
    error_log("=== ATUALIZANDO FUNCIONÁRIO ===");
    error_log("Atualizado por: {$usuarioLogado['nome']} ({$cargoUsuario})");
    error_log("Funcionário: {$funcionarioAtual['nome']}");
    
    $alteracoes = [];
    foreach ($dados as $campo => $valor) {
        if (isset($funcionarioAtual[$campo]) && $funcionarioAtual[$campo] != $valor) {
            $alteracoes[] = "{$campo}: '{$funcionarioAtual[$campo]}' → '{$valor}'";
        }
    }
    if (!empty($alteracoes)) {
        error_log("Alterações: " . implode(", ", $alteracoes));
    }
    
    // Atualizar funcionário
    $resultado = $funcionarios->atualizar($funcionarioId, $dados);
    
    if ($resultado) {
        // Buscar dados atualizados
        $funcionarioAtualizado = $funcionarios->getById($funcionarioId);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Funcionário atualizado com sucesso',
            'funcionario' => [
                'id' => $funcionarioAtualizado['id'],
                'nome' => $funcionarioAtualizado['nome'],
                'email' => $funcionarioAtualizado['email'],
                'departamento_nome' => $funcionarioAtualizado['departamento_nome'] ?? 'Sem departamento',
                'cargo' => $funcionarioAtualizado['cargo'] ?? 'Sem cargo',
                'ativo' => $funcionarioAtualizado['ativo']
            ],
            'info' => [
                'atualizado_por' => $usuarioLogado['nome'],
                'data_atualizacao' => date('Y-m-d H:i:s'),
                'campos_alterados' => array_keys($dados)
            ]
        ]);
    } else {
        throw new Exception('Erro ao atualizar funcionário');
    }
    
} catch (Exception $e) {
    error_log("Erro ao atualizar funcionário: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>