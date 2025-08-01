<?php
/**
 * API para listar departamentos
 * api/departamentos_listar.php
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

try {
    // Instanciar classe de funcionários (que tem o método getDepartamentos)
    $funcionarios = new Funcionarios();
    
    // Buscar todos os departamentos ativos
    $departamentos = $funcionarios->getDepartamentos();
    
    // Log da requisição
    error_log("=== LISTANDO DEPARTAMENTOS ===");
    error_log("Solicitado por: {$usuarioLogado['nome']} ({$cargoUsuario})");
    error_log("Total de departamentos: " . count($departamentos));
    
    // Adicionar informações extras baseadas em permissões
    $departamentosComInfo = [];
    
    foreach ($departamentos as $dept) {
        $deptInfo = [
            'id' => $dept['id'],
            'nome' => $dept['nome'],
            'descricao' => $dept['descricao']
        ];
        
        // Se o usuário tem permissões elevadas, adicionar estatísticas
        if ($departamentoUsuario == 1 || 
            in_array($cargoUsuario, ['Diretor', 'Presidente', 'Vice-Presidente']) ||
            $dept['id'] == $departamentoUsuario) {
            
            // Buscar estatísticas do departamento
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            // Total de funcionários no departamento
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_funcionarios,
                       SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as funcionarios_ativos
                FROM Funcionarios 
                WHERE departamento_id = ?
            ");
            $stmt->execute([$dept['id']]);
            $stats = $stmt->fetch();
            
            $deptInfo['estatisticas'] = [
                'total_funcionarios' => $stats['total_funcionarios'] ?? 0,
                'funcionarios_ativos' => $stats['funcionarios_ativos'] ?? 0
            ];
            
            // Se é o próprio departamento do usuário, marcar
            if ($dept['id'] == $departamentoUsuario) {
                $deptInfo['meu_departamento'] = true;
            }
        }
        
        $departamentosComInfo[] = $deptInfo;
    }
    
    // Determinar permissões do usuário sobre departamentos
    $permissoes = [
        'pode_criar_departamento' => false,
        'pode_editar_departamento' => false,
        'pode_ver_estatisticas_todos' => false
    ];
    
    if ($departamentoUsuario == 1 || in_array($cargoUsuario, ['Diretor', 'Presidente', 'Vice-Presidente'])) {
        $permissoes['pode_criar_departamento'] = true;
        $permissoes['pode_editar_departamento'] = true;
        $permissoes['pode_ver_estatisticas_todos'] = true;
    }
    
    // Resposta
    echo json_encode([
        'status' => 'success',
        'departamentos' => $departamentosComInfo,
        'total' => count($departamentosComInfo),
        'permissoes' => $permissoes,
        'usuario_departamento' => [
            'id' => $departamentoUsuario,
            'cargo' => $cargoUsuario
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar departamentos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar departamentos'
    ]);
}
?>