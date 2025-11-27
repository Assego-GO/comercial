<?php
/**
 * API para histórico de sócio agregado
 * api/documentos/documentos_agregados_historico.php
 * 
 * Busca histórico da tabela Auditoria
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_clean();

$response = ['status' => 'error', 'message' => 'Erro ao processar requisição'];

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Aceitar tanto documento_id quanto agregado_id
    $agregadoId = isset($_GET['documento_id']) ? intval($_GET['documento_id']) : 0;
    if ($agregadoId <= 0) {
        $agregadoId = isset($_GET['agregado_id']) ? intval($_GET['agregado_id']) : 0;
    }

    if ($agregadoId <= 0) {
        throw new Exception('ID do agregado inválido');
    }

    Database::getInstance(DB_NAME_CADASTRO);

    // Verificar se tabela Auditoria existe
    $stmt = $db->query("SHOW TABLES LIKE 'Auditoria'");
    $temAuditoria = $stmt->rowCount() > 0;

    $historico = [];

    if ($temAuditoria) {
        // Buscar histórico da Auditoria
        $sql = "
            SELECT 
                a.id,
                a.acao,
                a.alteracoes,
                a.data_hora AS data_acao,
                a.funcionario_id,
                f.nome AS funcionario_nome,
                CASE a.acao
                    WHEN 'INSERT' THEN 'Cadastro Realizado'
                    WHEN 'UPDATE' THEN 'Atualização'
                    WHEN 'APROVACAO' THEN 'Aprovado pela Presidência'
                    WHEN 'FINALIZACAO' THEN 'Processo Finalizado'
                    WHEN 'DELETE' THEN 'Registro Excluído'
                    ELSE a.acao
                END AS acao_descricao
            FROM Auditoria a
            LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
            WHERE a.tabela = 'Socios_Agregados' 
            AND a.registro_id = :agregado_id
            ORDER BY a.data_hora DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([':agregado_id' => $agregadoId]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Processar alteracoes JSON
        foreach ($historico as &$item) {
            if (!empty($item['alteracoes'])) {
                $alteracoes = json_decode($item['alteracoes'], true);
                if ($alteracoes) {
                    $item['detalhes'] = $alteracoes;
                    // Extrair observação se existir
                    if (isset($alteracoes['observacao'])) {
                        $item['observacao'] = $alteracoes['observacao'];
                    }
                }
            }
            if (!isset($item['observacao'])) {
                $item['observacao'] = '';
            }
        }
    }

    // Buscar também dados atuais do agregado para contexto
    $stmt = $db->prepare("
        SELECT 
            id, nome, cpf, situacao, data_criacao, data_atualizacao, observacoes
        FROM Socios_Agregados 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $agregadoId]);
    $agregado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Se não tem histórico na auditoria, criar um item com o cadastro
    if (empty($historico) && $agregado) {
        $historico[] = [
            'id' => 0,
            'acao' => 'CADASTRO',
            'acao_descricao' => 'Cadastro Realizado',
            'data_acao' => $agregado['data_criacao'],
            'funcionario_id' => null,
            'funcionario_nome' => 'Sistema',
            'observacao' => 'Cadastro inicial do agregado',
            'detalhes' => [
                'nome' => $agregado['nome'],
                'cpf' => $agregado['cpf'],
                'situacao' => $agregado['situacao']
            ]
        ];
    }

    $response = [
        'status' => 'success',
        'data' => $historico,
        'agregado' => $agregado
    ];

} catch (PDOException $e) {
    error_log("Erro PDO em documentos_agregados_historico: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ];
    http_response_code(500);
} catch (Exception $e) {
    error_log("Erro em documentos_agregados_historico: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;