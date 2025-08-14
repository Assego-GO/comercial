<?php
/**
 * API para buscar inadimplentes - Sistema ASSEGO
 * api/financeiro/buscar_inadimplentes.php
 * VERSÃO CORRIGIDA - Sem exibição de erros HTML
 */

// IMPORTANTE: Desabilitar exibição de erros HTML
error_reporting(E_ALL);
ini_set('display_errors', 0); // NUNCA deixe como 1 em produção
ini_set('log_errors', 1);

// Headers para CORS e JSON - DEVE vir ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Função para enviar resposta JSON e encerrar
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Verifica se os arquivos necessários existem antes de incluir
    $requiredFiles = [
        '../../config/config.php',
        '../../config/database.php',
        '../../classes/Database.php',
        '../../classes/Auth.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            error_log("Arquivo não encontrado: $file");
            sendJsonResponse([
                'status' => 'error',
                'message' => 'Erro de configuração do servidor',
                'data' => []
            ], 500);
        }
    }
    
    // Inclui arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Verificar se session já foi iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Usuário não autenticado',
            'data' => []
        ], 401);
    }

    // Verifica permissões
    $usuarioLogado = $auth->getUser();
    $temPermissao = false;
    
    if (isset($usuarioLogado['departamento_id'])) {
        $deptId = $usuarioLogado['departamento_id'];
        // Apenas financeiro (ID: 5) ou presidência (ID: 1)
        if ($deptId == 5 || $deptId == 1) {
            $temPermissao = true;
        }
    }
    
    if (!$temPermissao) {
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Acesso negado. Apenas funcionários do setor financeiro e presidência podem acessar este relatório.',
            'data' => []
        ], 403);
    }

    // Conecta ao banco de dados
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    } catch (Exception $e) {
        error_log("Erro ao conectar ao banco: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Erro ao conectar ao banco de dados',
            'data' => []
        ], 500);
    }

    // Parâmetros de filtro (opcionais)
    $filtroNome = $_GET['nome'] ?? '';
    $filtroRG = $_GET['rg'] ?? '';
    $filtroVinculo = $_GET['vinculo'] ?? '';
    $limite = intval($_GET['limite'] ?? 100); // Limite padrão de 100 registros
    $offset = intval($_GET['offset'] ?? 0);

    // Constrói a query base com JOIN
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.rg,
            a.cpf,
            a.situacao,
            a.telefone,
            a.nasc,
            a.email,
            f.vinculoServidor,
            f.situacaoFinanceira,
            f.tipoAssociado
        FROM Associados a
        INNER JOIN Financeiro f ON a.id = f.associado_id
        WHERE a.situacao = 'Filiado' 
        AND f.situacaoFinanceira = 'INADIMPLENTE'
    ";
    
    $params = [];
    
    // Adiciona filtros condicionalmente
    if (!empty($filtroNome)) {
        $sql .= " AND a.nome LIKE :nome";
        $params[':nome'] = '%' . $filtroNome . '%';
    }
    
    if (!empty($filtroRG)) {
        $sql .= " AND a.rg LIKE :rg";
        $params[':rg'] = '%' . $filtroRG . '%';
    }
    
    if (!empty($filtroVinculo)) {
        $sql .= " AND f.vinculoServidor = :vinculo";
        $params[':vinculo'] = $filtroVinculo;
    }
    
    // Ordena por nome
    $sql .= " ORDER BY a.nome ASC";
    
    // Adiciona limite e offset
    $sql .= " LIMIT :limite OFFSET :offset";
    
    // Log da query para debug (apenas em arquivo de log)
    error_log("Query SQL: " . $sql);
    error_log("Parâmetros: " . json_encode($params));

    try {
        // Prepara e executa a query
        $stmt = $db->prepare($sql);
        
        // Bind dos parâmetros de filtro
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        // Bind dos parâmetros de paginação
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $inadimplentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erro SQL ao buscar inadimplentes: " . $e->getMessage());
        sendJsonResponse([
            'status' => 'error',
            'message' => 'Erro ao buscar dados dos inadimplentes',
            'data' => []
        ], 500);
    }

    // Query para contar o total de registros (sem limite)
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM Associados a
        INNER JOIN Financeiro f ON a.id = f.associado_id
        WHERE a.situacao = 'Filiado' 
        AND f.situacaoFinanceira = 'INADIMPLENTE'
    ";
    
    $paramsCount = [];
    
    if (!empty($filtroNome)) {
        $sqlCount .= " AND a.nome LIKE :nome";
        $paramsCount[':nome'] = '%' . $filtroNome . '%';
    }
    
    if (!empty($filtroRG)) {
        $sqlCount .= " AND a.rg LIKE :rg";
        $paramsCount[':rg'] = '%' . $filtroRG . '%';
    }
    
    if (!empty($filtroVinculo)) {
        $sqlCount .= " AND f.vinculoServidor = :vinculo";
        $paramsCount[':vinculo'] = $filtroVinculo;
    }
    
    try {
        $stmtCount = $db->prepare($sqlCount);
        foreach ($paramsCount as $key => $value) {
            $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        error_log("Erro ao contar registros: " . $e->getMessage());
        $totalRegistros = 0;
    }

    // Busca estatísticas adicionais
    $estatisticas = [];
    
    try {
        // Total de inadimplentes por vínculo
        $sqlVinculo = "
            SELECT 
                f.vinculoServidor, 
                COUNT(*) as total
            FROM Associados a
            INNER JOIN Financeiro f ON a.id = f.associado_id
            WHERE a.situacao = 'Filiado' 
            AND f.situacaoFinanceira = 'INADIMPLENTE'
            GROUP BY f.vinculoServidor
            ORDER BY total DESC
        ";
        
        $stmtVinculo = $db->prepare($sqlVinculo);
        $stmtVinculo->execute();
        $estatisticas['por_vinculo'] = $stmtVinculo->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular percentuais
        if ($totalRegistros > 0) {
            foreach ($estatisticas['por_vinculo'] as &$vinculo) {
                $vinculo['percentual'] = round(($vinculo['total'] * 100.0 / $totalRegistros), 2);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao buscar estatísticas: " . $e->getMessage());
        $estatisticas['por_vinculo'] = [];
    }
    
    // Processa dados para formatação
    foreach ($inadimplentes as &$inadimplente) {
        // Adiciona informações calculadas
        $inadimplente['idade'] = null;
        if (!empty($inadimplente['nasc']) && $inadimplente['nasc'] != '0000-00-00') {
            try {
                $nascimento = new DateTime($inadimplente['nasc']);
                $hoje = new DateTime();
                $inadimplente['idade'] = $nascimento->diff($hoje)->y;
            } catch (Exception $e) {
                $inadimplente['idade'] = null;
            }
        }
        
        // Adiciona flags úteis
        $inadimplente['tem_telefone'] = !empty($inadimplente['telefone']);
        $inadimplente['tem_email'] = !empty($inadimplente['email']);
        
        // Garantir que campos nulos sejam strings vazias para evitar problemas no frontend
        $inadimplente['telefone'] = $inadimplente['telefone'] ?? '';
        $inadimplente['email'] = $inadimplente['email'] ?? '';
        $inadimplente['vinculoServidor'] = $inadimplente['vinculoServidor'] ?? 'Não informado';
        $inadimplente['tipoAssociado'] = $inadimplente['tipoAssociado'] ?? 'Não informado';
    }

    // Monta resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Inadimplentes carregados com sucesso',
        'data' => $inadimplentes,
        'meta' => [
            'total_registros' => intval($totalRegistros),
            'registros_retornados' => count($inadimplentes),
            'limite' => $limite,
            'offset' => $offset,
            'tem_mais_registros' => ($offset + $limite) < $totalRegistros
        ],
        'estatisticas' => $estatisticas,
        'filtros_aplicados' => [
            'nome' => $filtroNome,
            'rg' => $filtroRG,
            'vinculo' => $filtroVinculo
        ]
    ];

    // Log de sucesso
    error_log("Inadimplentes carregados com sucesso: " . count($inadimplentes) . " registros de " . $totalRegistros . " total");
    
    // Envia resposta de sucesso
    sendJsonResponse($response);

} catch (Exception $e) {
    // Outros erros não capturados
    error_log("Erro geral ao buscar inadimplentes: " . $e->getMessage());
    
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Erro ao processar requisição',
        'error_type' => 'general_error',
        'data' => []
    ], 500);
}

// Garantir que nada mais seja enviado
exit;