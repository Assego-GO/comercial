<?php
header('Content-Type: application/json; charset=utf-8');

// Função para resposta JSON
function jsonResponse($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Incluir dependências
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    // Verificar parâmetros
    $rg = $_GET['rg'] ?? null;
    $nome = $_GET['nome'] ?? null;
    
    if (!$rg && !$nome) {
        jsonResponse('error', 'É necessário informar o RG ou nome do associado');
    }
    
    // Conectar no banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Query para buscar associado + pecúlio (SEM LIMIT 1!)
    if ($rg) {
        $sql = "SELECT
            a.id,
            a.nome,
            a.rg,
            a.email,
            a.telefone,
            p.valor,
            p.data_prevista,
            p.data_recebimento
        FROM Associados a
        LEFT JOIN Peculio p ON a.id = p.associado_id
        WHERE a.rg = ?
        ORDER BY a.nome ASC";
        $params = [$rg];
        $tipoBusca = "RG";
    } else {
        $sql = "SELECT
            a.id,
            a.nome,
            a.rg,
            a.email,
            a.telefone,
            p.valor,
            p.data_prevista,
            p.data_recebimento
        FROM Associados a
        LEFT JOIN Peculio p ON a.id = p.associado_id
        WHERE a.nome LIKE ?
        ORDER BY a.nome ASC
        LIMIT 10"; // Limitar busca por nome para evitar muitos resultados
        $params = ['%' . $nome . '%'];
        $tipoBusca = "Nome";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$resultados || count($resultados) === 0) {
        jsonResponse('error', "Nenhum associado encontrado com $tipoBusca informado");
    }
    
    // Processar resultados
    $dadosProcessados = [];
    foreach ($resultados as $resultado) {
        $dadosProcessados[] = [
            'id' => $resultado['id'],
            'nome' => $resultado['nome'],
            'rg' => $resultado['rg'],
            'email' => $resultado['email'],
            'telefone' => $resultado['telefone'],
            'valor' => $resultado['valor'] ?: 0,
            'data_prevista' => ($resultado['data_prevista'] && $resultado['data_prevista'] !== '0000-00-00') ? $resultado['data_prevista'] : null,
            'data_recebimento' => ($resultado['data_recebimento'] && $resultado['data_recebimento'] !== '0000-00-00') ? $resultado['data_recebimento'] : null
        ];
    }
    
    // Verificar se há múltiplos resultados
    if (count($dadosProcessados) > 1) {
        // Múltiplos resultados encontrados
        $message = count($dadosProcessados) . " associados encontrados com $tipoBusca: " . ($rg ?: $nome);
        
        // Usar padrão do sistema para múltiplos resultados
        jsonResponse('multiple_results', $message, $dadosProcessados);
    } else {
        // Resultado único
        $message = "Dados do pecúlio carregados para: " . $dadosProcessados[0]['nome'];
        jsonResponse('success', $message, $dadosProcessados[0]);
    }
    
} catch (Exception $e) {
    error_log("ERRO na consulta do pecúlio: " . $e->getMessage());
    jsonResponse('error', 'Erro interno: ' . $e->getMessage());
}
?>