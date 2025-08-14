<?php
/**
 * API para buscar valores base dos serviços
 * api/buscar_valores_base.php
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => null
];

try {
    // Carrega configurações
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';

    // Verifica autenticação
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Acesso negado. Faça login.');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Busca valores base dos serviços
    $stmt = $db->prepare("
        SELECT id, nome, valor_base, descricao, ativo
        FROM Servicos 
        WHERE ativo = 1 
        ORDER BY id
    ");
    $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organiza por nome/tipo
    $valoresBase = [];
    foreach ($servicos as $servico) {
        $nome = strtolower($servico['nome']);
        if (strpos($nome, 'social') !== false) {
            $valoresBase['social'] = [
                'id' => $servico['id'],
                'nome' => $servico['nome'],
                'valor_base' => floatval($servico['valor_base']),
                'descricao' => $servico['descricao']
            ];
        } elseif (strpos($nome, 'juridico') !== false || strpos($nome, 'jurídico') !== false) {
            $valoresBase['juridico'] = [
                'id' => $servico['id'],
                'nome' => $servico['nome'],
                'valor_base' => floatval($servico['valor_base']),
                'descricao' => $servico['descricao']
            ];
        }
    }
    
    // Verifica se encontrou os serviços principais
    if (!isset($valoresBase['social'])) {
        throw new Exception('Serviço Social não encontrado. Verifique se existe um serviço com "social" no nome.');
    }
    
    if (!isset($valoresBase['juridico'])) {
        throw new Exception('Serviço Jurídico não encontrado. Verifique se existe um serviço com "juridico" no nome.');
    }
    
    // Busca também estatísticas básicas
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT sa.associado_id) as total_associados_com_servicos,
            COUNT(CASE WHEN sa.servico_id = ? THEN 1 END) as total_social,
            COUNT(CASE WHEN sa.servico_id = ? THEN 1 END) as total_juridico,
            SUM(CASE WHEN sa.servico_id = ? THEN sa.valor_aplicado ELSE 0 END) as receita_social,
            SUM(CASE WHEN sa.servico_id = ? THEN sa.valor_aplicado ELSE 0 END) as receita_juridico
        FROM Servicos_Associado sa
        WHERE sa.ativo = 1
    ");
    
    $stmt->execute([
        $valoresBase['social']['id'],
        $valoresBase['juridico']['id'],
        $valoresBase['social']['id'],
        $valoresBase['juridico']['id']
    ]);
    
    $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'status' => 'success',
        'message' => 'Valores base carregados com sucesso',
        'data' => [
            'social' => $valoresBase['social'],
            'juridico' => $valoresBase['juridico'],
            'estatisticas' => [
                'total_associados_com_servicos' => intval($estatisticas['total_associados_com_servicos']),
                'total_com_social' => intval($estatisticas['total_social']),
                'total_com_juridico' => intval($estatisticas['total_juridico']),
                'receita_social_atual' => floatval($estatisticas['receita_social']),
                'receita_juridico_atual' => floatval($estatisticas['receita_juridico']),
                'receita_total_atual' => floatval($estatisticas['receita_social']) + floatval($estatisticas['receita_juridico'])
            ],
            'data_consulta' => date('Y-m-d H:i:s')
        ]
    ];
    
} catch (Exception $e) {
    error_log("Erro ao buscar valores base: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>