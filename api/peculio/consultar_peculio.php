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
    
    // Query para buscar associado + pecúlio
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
                LIMIT 1";
        $params = [$rg];
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
                LIMIT 1";
        $params = ['%' . $nome . '%'];
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resultado) {
        jsonResponse('error', 'Associado não encontrado');
    }
    
    // Formatar resposta (corrigindo datas inválidas)
    $dados = [
        'id' => $resultado['id'],
        'nome' => $resultado['nome'],
        'rg' => $resultado['rg'],
        'email' => $resultado['email'],
        'telefone' => $resultado['telefone'],
        'valor' => $resultado['valor'] ?: 0,
        'data_prevista' => ($resultado['data_prevista'] && $resultado['data_prevista'] !== '0000-00-00') ? $resultado['data_prevista'] : null,
        'data_recebimento' => ($resultado['data_recebimento'] && $resultado['data_recebimento'] !== '0000-00-00') ? $resultado['data_recebimento'] : null
    ];
    
    jsonResponse('success', 'Dados do pecúlio carregados!', $dados);
    
} catch (Exception $e) {
    jsonResponse('error', 'Erro interno: ' . $e->getMessage());
}
?>