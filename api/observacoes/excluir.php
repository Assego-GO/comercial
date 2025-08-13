<?php
/**
 * ========================================
 * ARQUIVO: /api/observacoes/excluir.php
 * Endpoint para excluir observações (soft delete)
 * ========================================
 */

// Configurações e includes
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Associados.php';

// Headers para JSON e CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Se for requisição OPTIONS (preflight), retornar apenas headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Não autorizado. Faça login para continuar.'
    ]);
    exit;
}

// Validar método HTTP (aceitar POST ou DELETE)
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido. Use POST ou DELETE.'
    ]);
    exit;
}

// Pegar dados da requisição
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);

// Validar se o JSON é válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Dados inválidos. Erro no JSON: ' . json_last_error_msg()
    ]);
    exit;
}

// Validar ID da observação
$observacaoId = $input['id'] ?? null;

if (!$observacaoId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ID da observação não fornecido'
    ]);
    exit;
}

// Validar se é um número inteiro válido
$observacaoId = filter_var($observacaoId, FILTER_VALIDATE_INT);
if (!$observacaoId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ID da observação inválido'
    ]);
    exit;
}

try {
    // Criar conexão com o banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar observação para verificar permissões
    $stmt = $db->prepare("
        SELECT 
            o.*,
            a.nome as associado_nome,
            a.cpf as associado_cpf,
            f.nome as criado_por_nome
        FROM Observacoes_Associado o
        JOIN Associados a ON o.associado_id = a.id
        LEFT JOIN Funcionarios f ON o.criado_por = f.id
        WHERE o.id = ? AND o.ativo = 1
    ");
    $stmt->execute([$observacaoId]);
    $observacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se a observação existe e está ativa
    if (!$observacao) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Observação não encontrada ou já foi excluída'
        ]);
        exit;
    }
    
    // Pegar dados do usuário atual
    $usuarioAtual = $auth->getUser();
    
    // Verificar permissão para excluir
    // Pode excluir se: criou a observação OU é diretor/admin
    $podeExcluir = false;
    
    if ($observacao['criado_por'] == $usuarioAtual['id']) {
        $podeExcluir = true;
    } elseif ($auth->isDiretor()) {
        $podeExcluir = true;
    } elseif (isset($usuarioAtual['cargo']) && in_array($usuarioAtual['cargo'], ['Administrador', 'Gerente'])) {
        $podeExcluir = true;
    }
    
    if (!$podeExcluir) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Você não tem permissão para excluir esta observação'
        ]);
        exit;
    }
    
    // Log da tentativa de exclusão
    error_log(sprintf(
        "Exclusão de observação: ID=%d, Associado=%s, Por=%s (%d)",
        $observacaoId,
        $observacao['associado_nome'],
        $usuarioAtual['nome'] ?? 'Unknown',
        $usuarioAtual['id'] ?? 0
    ));
    
    // Criar instância da classe e executar exclusão
    $associados = new Associados();
    $resultado = $associados->excluirObservacao($observacaoId);
    
    if ($resultado) {
        // Buscar total de observações restantes para o associado
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM Observacoes_Associado 
            WHERE associado_id = ? AND ativo = 1
        ");
        $stmt->execute([$observacao['associado_id']]);
        $totalRestante = $stmt->fetchColumn();
        
        // Registrar na auditoria (opcional - se a tabela existir)
        try {
            $stmt = $db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, associado_id, 
                    funcionario_id, alteracoes, ip_origem, 
                    browser_info, sessao_id, data_hora
                ) VALUES (
                    'Observacoes_Associado', 'DELETE', ?, ?, 
                    ?, ?, ?, 
                    ?, ?, NOW()
                )
            ");
            
            $alteracoes = json_encode([
                'observacao_excluida' => substr($observacao['observacao'], 0, 100) . '...',
                'criada_por' => $observacao['criado_por_nome'],
                'categoria' => $observacao['categoria'],
                'importante' => $observacao['importante']
            ]);
            
            $stmt->execute([
                $observacaoId,
                $observacao['associado_id'],
                $usuarioAtual['id'] ?? null,
                $alteracoes,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);
        } catch (Exception $e) {
            // Não é crítico se falhar a auditoria
            error_log("Aviso: Falha ao registrar auditoria de exclusão: " . $e->getMessage());
        }
        
        // Retornar sucesso
        echo json_encode([
            'status' => 'success',
            'message' => 'Observação excluída com sucesso',
            'data' => [
                'id_excluido' => $observacaoId,
                'associado' => [
                    'id' => $observacao['associado_id'],
                    'nome' => $observacao['associado_nome'],
                    'total_observacoes_restantes' => $totalRestante
                ],
                'excluido_por' => [
                    'id' => $usuarioAtual['id'],
                    'nome' => $usuarioAtual['nome'] ?? 'Sistema'
                ],
                'data_exclusao' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Falha ao excluir observação no banco de dados');
    }
    
} catch (PDOException $e) {
    error_log("Erro DB ao excluir observação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao excluir observação no banco de dados',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    error_log("Erro ao excluir observação: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar requisição: ' . $e->getMessage(),
        'debug' => DEBUG_MODE ? [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
}