<?php
/**
 * API para listagem de registros de auditoria - VERSÃO FINAL
 * /api/auditoria/registros.php
 * 
 * REGRAS DE ACESSO:
 * - Presidência (dept_id = 1): VÊ TUDO
 * - Diretores (outros depts): VÊ APENAS SEU DEPARTAMENTO
 * - Outros perfis: VÊ APENAS PRÓPRIOS REGISTROS
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }
    
    // Iniciar sessão
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // ===========================================
    // OBTER DADOS DO USUÁRIO LOGADO
    // ===========================================
    
    $userName = $_SESSION['nome'] ?? null;
    $userId = null;
    $userProfile = null;
    $userDepartmentId = null;
    
    // Buscar dados no banco pelo nome da sessão
    if ($userName) {
        $stmt = $db->prepare("SELECT id, cargo, departamento_id FROM Funcionarios WHERE nome = ? LIMIT 1");
        $stmt->execute([$userName]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            $userId = $userData['id'];
            $userProfile = strtolower(trim($userData['cargo']));
            $userDepartmentId = (int)$userData['departamento_id'];
        }
    }
    
    // Fallback para dados de sessão se não encontrou no banco
    if (!$userId) {
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['funcionario_id'] ?? null;
        $userProfile = strtolower($_SESSION['cargo'] ?? $_SESSION['user_profile'] ?? 'funcionario');
        $userDepartmentId = (int)($_SESSION['departamento_id'] ?? $_SESSION['user_department_id'] ?? 0);
    }
    
    // Verificar se tem dados mínimos
    if (!$userId) {
        throw new Exception('Usuário não identificado');
    }
    
    error_log("AUDITORIA API - Usuário: $userName (ID: $userId), Perfil: $userProfile, Dept: $userDepartmentId");
    
    // ===========================================
    // DEFINIR CONTROLE DE ACESSO
    // ===========================================
    
    $whereConditions = [];
    $params = [];
    
    // Identificar se é da presidência
    $isPresidencia = ($userDepartmentId === 1);
    
    // Identificar se é diretor
    $isDiretor = (strpos($userProfile, 'diretor') !== false);
    
    if ($isPresidencia) {
        // PRESIDÊNCIA VÊ TUDO - sem filtros (incluindo registros do Sistema)
        error_log("ACESSO: Presidência - SEM FILTROS (vê tudo, incluindo Sistema)");
    } elseif ($isDiretor && $userDepartmentId > 0) {
        // DIRETOR VÊ APENAS SEU DEPARTAMENTO (SEM registros do Sistema)
        $whereConditions[] = "f.departamento_id = :user_department_id";
        $params[':user_department_id'] = $userDepartmentId;
        error_log("ACESSO: Diretor - FILTRO ESTRITO POR DEPARTAMENTO: $userDepartmentId (sem Sistema)");
    } else {
        // OUTROS PERFIS - APENAS PRÓPRIOS REGISTROS
        $whereConditions[] = "a.funcionario_id = :user_id";
        $params[':user_id'] = $userId;
        error_log("ACESSO: Funcionário - APENAS PRÓPRIOS REGISTROS");
    }
    
    // ===========================================
    // FILTROS ADICIONAIS DA REQUEST
    // ===========================================
    
    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    // Filtro por tabela
    if (!empty($_GET['tabela'])) {
        $whereConditions[] = "a.tabela = :tabela";
        $params[':tabela'] = $_GET['tabela'];
    }
    
    // Filtro por ação
    if (!empty($_GET['acao'])) {
        $whereConditions[] = "a.acao = :acao";
        $params[':acao'] = $_GET['acao'];
    }
    
    // Filtro por data início
    if (!empty($_GET['data_inicio'])) {
        $whereConditions[] = "DATE(a.data_hora) >= :data_inicio";
        $params[':data_inicio'] = $_GET['data_inicio'];
    }
    
    // Filtro por data fim
    if (!empty($_GET['data_fim'])) {
        $whereConditions[] = "DATE(a.data_hora) <= :data_fim";
        $params[':data_fim'] = $_GET['data_fim'];
    }
    
    // Filtro por busca
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $whereConditions[] = "(f.nome LIKE :search OR a.tabela LIKE :search2)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
    }
    
    // Construir WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // ===========================================
    // BUSCAR REGISTROS
    // ===========================================
    
    $sql = "
        SELECT 
            a.id,
            a.tabela,
            a.acao,
            a.registro_id,
            a.data_hora,
            a.ip_origem,
            a.browser_info,
            a.sessao_id,
            a.alteracoes,
            a.associado_id,
            f.nome as funcionario_nome,
            f.email as funcionario_email,
            f.cargo as funcionario_cargo,
            f.departamento_id,
            d.nome as departamento_nome,
            ass.nome as associado_nome,
            ass.cpf as associado_cpf
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id
        $whereClause
        ORDER BY a.data_hora DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    
    // Bind dos parâmetros
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===========================================
    // CONTAR TOTAL DE REGISTROS
    // ===========================================
    
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id
        $whereClause
    ";
    
    $stmtCount = $db->prepare($sqlCount);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $stmtCount->bindValue($key, $value);
        }
    }
    $stmtCount->execute();
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    $totalPaginas = ceil($totalRegistros / $limit);
    
    // ===========================================
    // PROCESSAR REGISTROS
    // ===========================================
    
    $registrosProcessados = [];
    foreach ($registros as $registro) {
        $processado = [
            'id' => (int)$registro['id'],
            'tabela' => $registro['tabela'],
            'acao' => $registro['acao'],
            'registro_id' => $registro['registro_id'],
            'data_hora' => $registro['data_hora'],
            'ip_origem' => $registro['ip_origem'],
            'browser_info' => $registro['browser_info'],
            'sessao_id' => $registro['sessao_id'],
            'alteracoes' => $registro['alteracoes'],
            'funcionario_nome' => $registro['funcionario_nome'] ?: 'Sistema',
            'funcionario_email' => $registro['funcionario_email'],
            'funcionario_cargo' => $registro['funcionario_cargo'],
            'departamento_nome' => $registro['departamento_nome'],
            'associado_id' => $registro['associado_id'],
            'associado_nome' => $registro['associado_nome'],
            'associado_cpf' => $registro['associado_cpf']
        ];
        
        // Formatar data
        if ($processado['data_hora']) {
            $processado['data_hora_formatada'] = date('d/m/Y H:i:s', strtotime($processado['data_hora']));
        }
        
        // Decodificar alterações
        if (!empty($processado['alteracoes'])) {
            try {
                $processado['alteracoes_decoded'] = json_decode($processado['alteracoes'], true);
            } catch (Exception $e) {
                $processado['alteracoes_decoded'] = null;
            }
        } else {
            $processado['alteracoes_decoded'] = null;
        }
        
        $registrosProcessados[] = $processado;
    }
    
    // ===========================================
    // PREPARAR RESPOSTA
    // ===========================================
    
    $paginacao = [
        'pagina_atual' => $page,
        'total_paginas' => $totalPaginas,
        'total_registros' => (int)$totalRegistros,
        'registros_por_pagina' => $limit,
        'tem_proxima' => $page < $totalPaginas,
        'tem_anterior' => $page > 1,
        'primeira_pagina' => 1,
        'ultima_pagina' => $totalPaginas
    ];
    
    $filtrosAplicados = [];
    foreach (['tabela', 'acao', 'funcionario_id', 'associado_id', 'data_inicio', 'data_fim', 'search'] as $filtro) {
        if (!empty($_GET[$filtro])) {
            $filtrosAplicados[$filtro] = $_GET[$filtro];
        }
    }
    
    $infoUsuario = [
        'user_id' => (int)$userId,
        'user_profile' => $userProfile,
        'user_department_id' => $userDepartmentId,
        'is_presidencia' => $isPresidencia,
        'is_diretor' => $isDiretor,
        'pode_ver_todos_funcionarios' => ($isPresidencia || $isDiretor),
        'nome_usuario' => $userName,
        'escopo_acesso' => $isPresidencia ? 'GLOBAL' : ($isDiretor ? "DEPARTAMENTO_$userDepartmentId" : 'PRÓPRIOS_REGISTROS')
    ];
    
    $response = [
        'status' => 'success',
        'message' => 'Registros obtidos com sucesso',
        'data' => [
            'registros' => $registrosProcessados,
            'paginacao' => $paginacao,
            'filtros_aplicados' => $filtrosAplicados,
            'resumo' => [
                'total_encontrados' => count($registrosProcessados),
                'pagina_atual' => $page,
                'total_geral' => (int)$totalRegistros
            ],
            'usuario' => $infoUsuario
        ],
        'meta' => [
            'tempo_execucao' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'timestamp' => time(),
            'versao_api' => '3.0-final'
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    error_log("ERRO PDO na API de auditoria: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de banco de dados',
        'error_code' => 'DB_ERROR_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("ERRO na API de auditoria: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'error_code' => 'GENERAL_ERROR_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>