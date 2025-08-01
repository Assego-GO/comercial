<?php
/**
 * API para buscar detalhes de um funcionário
 * api/funcionarios_detalhes.php
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

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID não informado']);
    exit;
}

try {
    $funcionarios = new Funcionarios();
    
    // Buscar dados básicos do funcionário
    $funcionario = $funcionarios->getById($id);
    
    if (!$funcionario) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Funcionário não encontrado']);
        exit;
    }
    
    // Verificar permissão para visualizar este funcionário
    $podeVisualizarCompleto = false;
    $podeVisualizarBadges = false;
    $podeVisualizarContribuicoes = false;
    $podeVisualizarDadosPessoais = false;
    
    // Log de verificação de permissão
    error_log("=== VERIFICANDO PERMISSÃO DE VISUALIZAÇÃO ===");
    error_log("Usuário: {$usuarioLogado['nome']} (ID: {$usuarioLogado['id']})");
    error_log("Cargo: {$cargoUsuario}");
    error_log("Departamento: {$departamentoUsuario}");
    error_log("Visualizando funcionário: {$funcionario['nome']} (ID: {$id})");
    error_log("Departamento do funcionário: {$funcionario['departamento_id']}");
    
    // Regras de permissão
    if ($id == $usuarioLogado['id']) {
        // Visualizando próprio perfil - acesso completo
        $podeVisualizarCompleto = true;
        $podeVisualizarBadges = true;
        $podeVisualizarContribuicoes = true;
        $podeVisualizarDadosPessoais = true;
        error_log("✅ Visualizando próprio perfil - acesso completo");
    } elseif ($departamentoUsuario == 1) {
        // Presidência vê tudo
        $podeVisualizarCompleto = true;
        $podeVisualizarBadges = true;
        $podeVisualizarContribuicoes = true;
        $podeVisualizarDadosPessoais = true;
        error_log("✅ Presidência - acesso completo");
    } elseif (in_array($cargoUsuario, ['Diretor', 'Presidente', 'Vice-Presidente'])) {
        // Alta gestão vê tudo
        $podeVisualizarCompleto = true;
        $podeVisualizarBadges = true;
        $podeVisualizarContribuicoes = true;
        $podeVisualizarDadosPessoais = true;
        error_log("✅ Alta gestão - acesso completo");
    } elseif (in_array($cargoUsuario, ['Gerente', 'Supervisor', 'Coordenador'])) {
        // Gestão intermediária
        if ($funcionario['departamento_id'] == $departamentoUsuario) {
            // Mesmo departamento - vê quase tudo
            $podeVisualizarCompleto = true;
            $podeVisualizarBadges = true;
            $podeVisualizarContribuicoes = true;
            $podeVisualizarDadosPessoais = false; // Não vê RG/CPF de outros
            error_log("✅ Gestão intermediária - mesmo departamento");
        } else {
            // Outro departamento - vê apenas dados básicos
            $podeVisualizarCompleto = false;
            $podeVisualizarBadges = true;
            $podeVisualizarContribuicoes = false;
            $podeVisualizarDadosPessoais = false;
            error_log("⚠️ Gestão intermediária - outro departamento (acesso limitado)");
        }
    } else {
        // Funcionários comuns - veem apenas dados básicos de outros
        if ($funcionario['departamento_id'] == $departamentoUsuario) {
            // Mesmo departamento
            $podeVisualizarCompleto = false;
            $podeVisualizarBadges = true;
            $podeVisualizarContribuicoes = false;
            $podeVisualizarDadosPessoais = false;
            error_log("⚠️ Funcionário comum - mesmo departamento (acesso básico)");
        } else {
            // Outro departamento - acesso mínimo
            $podeVisualizarCompleto = false;
            $podeVisualizarBadges = false;
            $podeVisualizarContribuicoes = false;
            $podeVisualizarDadosPessoais = false;
            error_log("❌ Funcionário comum - outro departamento (acesso mínimo)");
        }
    }
    
    // Preparar dados para retorno baseado nas permissões
    $dadosRetorno = [
        'id' => $funcionario['id'],
        'nome' => $funcionario['nome'],
        'email' => $funcionario['email'],
        'departamento_id' => $funcionario['departamento_id'],
        'departamento_nome' => $funcionario['departamento_nome'],
        'cargo' => $funcionario['cargo'],
        'ativo' => $funcionario['ativo'],
        'criado_em' => $funcionario['criado_em']
    ];
    
    // Adicionar dados pessoais se permitido
    if ($podeVisualizarDadosPessoais) {
        $dadosRetorno['cpf'] = $funcionario['cpf'];
        $dadosRetorno['rg'] = $funcionario['rg'];
    } else {
        // Mascarar dados sensíveis
        if (!empty($funcionario['cpf'])) {
            $dadosRetorno['cpf'] = substr($funcionario['cpf'], 0, 3) . '.***.***.***-**';
        }
        $dadosRetorno['rg'] = '***';
    }
    
    // Adicionar informações adicionais se permitido
    if ($podeVisualizarCompleto) {
        $dadosRetorno['senha_alterada_em'] = $funcionario['senha_alterada_em'];
        $dadosRetorno['foto'] = $funcionario['foto'];
    }
    
    // Buscar badges se permitido
    if ($podeVisualizarBadges) {
        $dadosRetorno['badges'] = $funcionarios->getBadges($id);
    } else {
        $dadosRetorno['badges'] = [];
    }
    
    // Buscar contribuições se permitido
    if ($podeVisualizarContribuicoes) {
        $dadosRetorno['contribuicoes'] = $funcionarios->getContribuicoes($id);
    } else {
        $dadosRetorno['contribuicoes'] = [];
    }
    
    // Sempre incluir estatísticas (são públicas)
    $dadosRetorno['estatisticas'] = $funcionarios->getEstatisticas($id);
    
    // Adicionar informações de permissão
    $dadosRetorno['permissoes_visualizacao'] = [
        'completo' => $podeVisualizarCompleto,
        'badges' => $podeVisualizarBadges,
        'contribuicoes' => $podeVisualizarContribuicoes,
        'dados_pessoais' => $podeVisualizarDadosPessoais,
        'pode_editar' => $funcionarios->podeEditar($id, $usuarioLogado['id'], $cargoUsuario, $departamentoUsuario)
    ];
    
    // Log do que foi retornado
    error_log("📊 Retornando dados do funcionário com as seguintes permissões:");
    error_log("- Visualização completa: " . ($podeVisualizarCompleto ? 'SIM' : 'NÃO'));
    error_log("- Badges: " . ($podeVisualizarBadges ? 'SIM' : 'NÃO'));
    error_log("- Contribuições: " . ($podeVisualizarContribuicoes ? 'SIM' : 'NÃO'));
    error_log("- Dados pessoais: " . ($podeVisualizarDadosPessoais ? 'SIM' : 'NÃO'));
    
    echo json_encode([
        'status' => 'success',
        'funcionario' => $dadosRetorno,
        'visualizado_por' => [
            'id' => $usuarioLogado['id'],
            'nome' => $usuarioLogado['nome'],
            'cargo' => $cargoUsuario,
            'departamento' => $departamentoUsuario
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar detalhes do funcionário: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar funcionário'
    ]);
}
?>