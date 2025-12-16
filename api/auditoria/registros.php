<?php
/**
 * API para listagem de registros de auditoria - VERSÃO HÍBRIDA
 * /api/auditoria/registros.php
 * 
 * CORREÇÃO CRÍTICA: Suporte para Funcionários + Associados-Diretores
 * PROBLEMA: Sistema só consultava tabela Funcionarios, não Associados
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
    require_once '../../classes/Auth.php';
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // ===========================================
    // CORREÇÃO: IDENTIFICAÇÃO HÍBRIDA DO USUÁRIO
    // ===========================================
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    $usuarioLogado = $auth->getUser();
    $userId = $usuarioLogado['id'];
    $userName = $usuarioLogado['nome'];
    $userProfile = strtolower(trim($usuarioLogado['cargo']));
    $userDepartmentId = (int)($usuarioLogado['departamento_id'] ?? 0);
    $tipoUsuario = $usuarioLogado['tipo_usuario'] ?? 'funcionario';
    
    error_log("=== AUDITORIA API HÍBRIDA ===");
    error_log("Usuário: $userName (ID: $userId)");
    error_log("Tipo: $tipoUsuario");
    error_log("Cargo: $userProfile");
    error_log("Departamento: $userDepartmentId");
    
    // ===========================================
    // CONTROLE DE ACESSO HÍBRIDO
    // ===========================================
    
    $whereConditions = [];
    $params = [];
    
    // Identificar permissões
    $isPresidencia = ($userDepartmentId === 1) || 
                     in_array($userProfile, ['presidente', 'vice-presidente']);
    
    $isDiretor = (strpos($userProfile, 'diretor') !== false) || 
                 ($tipoUsuario === 'associado'); // Associados que fazem login são diretores
    
    if ($isPresidencia) {
        // PRESIDÊNCIA VÊ TUDO
        error_log("ACESSO: Presidência - SEM FILTROS");
    } elseif ($isDiretor && $tipoUsuario === 'funcionario' && $userDepartmentId > 0) {
        // DIRETOR-FUNCIONÁRIO VÊ APENAS SEU DEPARTAMENTO
        $whereConditions[] = "f.departamento_id = :user_department_id";
        $params[':user_department_id'] = $userDepartmentId;
        error_log("ACESSO: Diretor-Funcionário - DEPARTAMENTO $userDepartmentId");
    } elseif ($isDiretor && $tipoUsuario === 'associado') {
        // ASSOCIADO-DIRETOR VÊ TODOS OS REGISTROS (como presidência)
        error_log("ACESSO: Associado-Diretor - VISUALIZAÇÃO GERAL");
    } else {
        // FUNCIONÁRIOS NORMAIS - APENAS PRÓPRIOS REGISTROS
        $whereConditions[] = "a.funcionario_id = :user_id";
        $params[':user_id'] = $userId;
        error_log("ACESSO: Funcionário Normal - APENAS PRÓPRIOS");
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
        $whereConditions[] = "(COALESCE(f.nome, ass.nome) LIKE :search OR a.tabela LIKE :search2)";
        $params[':search'] = $search;
        $params[':search2'] = $search;
    }
    
    // Filtro departamental externo (com validação)
    if (!empty($_GET['departamento_usuario'])) {
        $deptFiltro = (int)$_GET['departamento_usuario'];
        
        // Validar se pode aplicar este filtro
        if (!$isPresidencia && $tipoUsuario === 'funcionario' && $deptFiltro !== $userDepartmentId) {
            throw new Exception('Acesso negado: você não pode visualizar dados de outros departamentos');
        }
        
        // Para associados-diretores, permitir qualquer filtro departamental
        if ($tipoUsuario === 'funcionario') {
            $whereConditions[] = "f.departamento_id = :departamento_filtro";
            $params[':departamento_filtro'] = $deptFiltro;
        }
    }
    
    // Construir WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // ===========================================
    // QUERY PRINCIPAL HÍBRIDA
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
            a.funcionario_id,
            
            -- CORREÇÃO: Buscar nome em AMBAS as tabelas
            COALESCE(f.nome, ass_user.nome) as funcionario_nome,
            COALESCE(f.email, ass_user.email) as funcionario_email,
            CASE 
                WHEN f.id IS NOT NULL THEN f.cargo
                WHEN ass_user.id IS NOT NULL THEN 'Associado-Diretor'
                ELSE 'Sistema'
            END as funcionario_cargo,
            CASE 
                WHEN f.id IS NOT NULL THEN 'Funcionário'
                WHEN ass_user.id IS NOT NULL THEN 'Associado'
                ELSE 'Sistema'
            END as tipo_usuario_registro,
            
            f.departamento_id,
            d.nome as departamento_nome,
            ass_reg.nome as associado_nome,
            ass_reg.cpf as associado_cpf,
            ass_reg.rg as associado_rg
            
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Associados ass_user ON a.funcionario_id = ass_user.id AND f.id IS NULL
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        LEFT JOIN Associados ass_reg ON a.associado_id = ass_reg.id
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
    // CONTAR TOTAL COM QUERY HÍBRIDA
    // ===========================================
    
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Associados ass_user ON a.funcionario_id = ass_user.id AND f.id IS NULL
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        LEFT JOIN Associados ass_reg ON a.associado_id = ass_reg.id
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
            'tipo_usuario_registro' => $registro['tipo_usuario_registro'],
            'departamento_nome' => $registro['departamento_nome'],
            'associado_id' => $registro['associado_id'],
            'associado_nome' => $registro['associado_nome'],
            'associado_cpf' => $registro['associado_cpf'],
            'associado_rg' => $registro['associado_rg']
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
    foreach (['tabela', 'acao', 'funcionario_id', 'associado_id', 'data_inicio', 'data_fim', 'search', 'departamento_usuario'] as $filtro) {
        if (!empty($_GET[$filtro])) {
            $filtrosAplicados[$filtro] = $_GET[$filtro];
        }
    }
    
    $infoUsuario = [
        'user_id' => (int)$userId,
        'user_name' => $userName,
        'user_profile' => $userProfile,
        'user_department_id' => $userDepartmentId,
        'tipo_usuario' => $tipoUsuario,
        'is_presidencia' => $isPresidencia,
        'is_diretor' => $isDiretor,
        'escopo_acesso' => $isPresidencia ? 'GLOBAL' : 
                          ($isDiretor && $tipoUsuario === 'associado' ? 'GLOBAL_ASSOCIADO' : 
                          ($isDiretor ? "DEPARTAMENTO_$userDepartmentId" : 'PRÓPRIOS_REGISTROS')),
        'fonte_dados' => 'AUTH_HIBRIDA'
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
            'versao_api' => '5.0-hibrida'
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    error_log("ERRO PDO na API híbrida: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de banco de dados',
        'error_code' => 'DB_ERROR_HYBRID',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("ERRO na API híbrida: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_code' => 'GENERAL_ERROR_HYBRID',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>