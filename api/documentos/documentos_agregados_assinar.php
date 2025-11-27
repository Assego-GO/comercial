<?php
/**
 * API para aprovar/assinar sócio agregado
 * api/documentos/documentos_agregados_assinar.php
 * 
 * Atualiza diretamente a tabela Socios_Agregados
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    $funcionarioId = $_SESSION['funcionario_id'] ?? null;
    if (!$funcionarioId) {
        throw new Exception('Funcionário não identificado');
    }

    // Ler dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Aceitar tanto documento_id quanto agregado_id
    $agregadoId = isset($input['documento_id']) ? intval($input['documento_id']) : 0;
    if ($agregadoId <= 0) {
        $agregadoId = isset($input['agregado_id']) ? intval($input['agregado_id']) : 0;
    }
    $observacao = isset($input['observacao']) ? trim($input['observacao']) : '';

    if ($agregadoId <= 0) {
        throw new Exception('ID do agregado inválido');
    }

    Database::getInstance(DB_NAME_CADASTRO);
    $db->beginTransaction();

    try {
        // Verificar se agregado existe
        $stmt = $db->prepare("
            SELECT id, nome, cpf, situacao 
            FROM Socios_Agregados 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $agregadoId]);
        $agregado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agregado) {
            throw new Exception('Agregado não encontrado');
        }

        // Verificar se está pendente
        if (!in_array($agregado['situacao'], ['pendente', 'aguardando'])) {
            throw new Exception('Este agregado não está pendente de aprovação. Situação atual: ' . $agregado['situacao']);
        }

        // Atualizar situação para 'ativo' (aprovado)
        $novaObservacao = $observacao ?: 'Aprovado pela presidência';
        $observacaoCompleta = "[APROVAÇÃO " . date('d/m/Y H:i') . " - Func.ID: $funcionarioId] " . $novaObservacao;
        
        $stmt = $db->prepare("
            UPDATE Socios_Agregados 
            SET situacao = 'ativo',
                ativo = 1,
                observacoes = CONCAT(IFNULL(observacoes, ''), '\n', :observacao),
                data_atualizacao = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $agregadoId,
            ':observacao' => $observacaoCompleta
        ]);

        // Registrar na auditoria se existir a tabela
        $stmt = $db->query("SHOW TABLES LIKE 'Auditoria'");
        if ($stmt->rowCount() > 0) {
            $stmt = $db->prepare("
                INSERT INTO Auditoria (tabela, acao, registro_id, funcionario_id, alteracoes, data_hora)
                VALUES ('Socios_Agregados', 'APROVACAO', :registro_id, :funcionario_id, :alteracoes, NOW())
            ");
            $stmt->execute([
                ':registro_id' => $agregadoId,
                ':funcionario_id' => $funcionarioId,
                ':alteracoes' => json_encode([
                    'situacao_anterior' => $agregado['situacao'],
                    'situacao_nova' => 'ativo',
                    'observacao' => $novaObservacao
                ])
            ]);
        }

        $db->commit();

        $response = [
            'status' => 'success',
            'message' => 'Agregado aprovado com sucesso!',
            'data' => [
                'agregado_id' => $agregadoId,
                'nome' => $agregado['nome'],
                'cpf' => $agregado['cpf'],
                'situacao_anterior' => $agregado['situacao'],
                'situacao_nova' => 'ativo',
                'data_aprovacao' => date('Y-m-d H:i:s')
            ]
        ];

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro PDO em documentos_agregados_assinar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ];
    http_response_code(500);
} catch (Exception $e) {
    error_log("Erro em documentos_agregados_assinar: " . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;