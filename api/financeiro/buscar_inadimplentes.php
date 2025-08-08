<?php
/**
 * API para buscar inadimplentes - Sistema ASSEGO
 * api/financeiro/buscar_inadimplentes.php
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Inclui arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
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
        throw new Exception('Acesso negado. Apenas funcionários do setor financeiro e presidência podem acessar este relatório.');
    }

    // Conecta ao banco de dados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

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
    
    // Log da query para debug
    error_log("Query SQL: " . $sql);
    error_log("Parâmetros: " . json_encode($params));

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
    
    $stmtCount = $db->prepare($sqlCount);
    foreach ($paramsCount as $key => $value) {
        $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmtCount->execute();
    $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Busca estatísticas adicionais
    $estatisticas = [];
    
    // Total de inadimplentes por vínculo
    $sqlVinculo = "
        SELECT 
            f.vinculoServidor, 
            COUNT(*) as total,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM Associados a2 INNER JOIN Financeiro f2 ON a2.id = f2.associado_id WHERE a2.situacao = 'Filiado' AND f2.situacaoFinanceira = 'INADIMPLENTE')), 2) as percentual
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
    
    // Processa dados para formatação
    foreach ($inadimplentes as &$inadimplente) {
        // Adiciona informações calculadas
        $inadimplente['idade'] = null;
        if ($inadimplente['nasc']) {
            $nascimento = new DateTime($inadimplente['nasc']);
            $hoje = new DateTime();
            $inadimplente['idade'] = $nascimento->diff($hoje)->y;
        }
        
        // Limpa dados sensíveis se necessário
        // (mantém todos os dados pois é para uso interno do setor financeiro)
        
        // Adiciona flags úteis
        $inadimplente['tem_telefone'] = !empty($inadimplente['telefone']);
        $inadimplente['tem_email'] = !empty($inadimplente['email']);
    }

    // Monta resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Inadimplentes carregados com sucesso',
        'data' => $inadimplentes,
        'meta' => [
            'total_registros' => $totalRegistros,
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

} catch (PDOException $e) {
    // Erro de banco de dados
    error_log("Erro de banco de dados ao buscar inadimplentes: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => 'Erro interno do servidor. Tente novamente.',
        'error_type' => 'database_error',
        'data' => []
    ];
    
    http_response_code(500);

} catch (Exception $e) {
    // Outros erros
    error_log("Erro ao buscar inadimplentes: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_type' => 'general_error',
        'data' => []
    ];
    
    http_response_code(400);

} finally {
    // Sempre retorna JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>