<?php
/**
 * ========================================
 * ARQUIVO: /api/observacoes/toggle-importante.php
 * Endpoint para alternar o status "importante" de uma observação
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
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
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

// Validar método HTTP (aceitar POST ou PUT)
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido. Use POST ou PUT.'
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
    
    // Buscar observação atual
    $stmt = $db->prepare("
        SELECT 
            o.*,
            a.nome as associado_nome,
            a.cpf as associado_cpf,
            f.nome as criado_por_nome,
            f.cargo as criado_por_cargo
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
            'message' => 'Observação não encontrada ou foi excluída'
        ]);
        exit;
    }
    
    // Pegar dados do usuário atual
    $usuarioAtual = $auth->getUser();
    
    // Verificar permissão para alterar importância
    // Qualquer usuário logado pode marcar/desmarcar como importante
    // Mas vamos registrar quem fez a alteração
    
    // Criar instância da classe e executar toggle
    $associados = new Associados();
    $resultado = $associados->toggleImportanteObservacao($observacaoId);
    
    if ($resultado !== false) {
        // Buscar observação atualizada para retornar dados completos
        $stmt = $db->prepare("
            SELECT 
                o.*,
                a.nome as associado_nome,
                f.nome as criado_por_nome,
                DATE_FORMAT(o.data_criacao, '%d/%m/%Y às %H:%i') as data_formatada,
                DATE_FORMAT(o.data_edicao, '%d/%m/%Y às %H:%i') as data_edicao_formatada
            FROM Observacoes_Associado o
            JOIN Associados a ON o.associado_id = a.id
            LEFT JOIN Funcionarios f ON o.criado_por = f.id
            WHERE o.id = ?
        ");
        $stmt->execute([$observacaoId]);
        $observacaoAtualizada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log da alteração
        error_log(sprintf(
            "Toggle importante: ID=%d, Novo Status=%s, Por=%s (%d)",
            $observacaoId,
            $resultado['importante'] ? 'Importante' : 'Normal',
            $usuarioAtual['nome'] ?? 'Unknown',
            $usuarioAtual['id'] ?? 0
        ));
        
        // Registrar na auditoria (opcional)
        try {
            $stmt = $db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, associado_id, 
                    funcionario_id, alteracoes, ip_origem, 
                    browser_info, sessao_id, data_hora
                ) VALUES (
                    'Observacoes_Associado', 'UPDATE_IMPORTANTE', ?, ?, 
                    ?, ?, ?, 
                    ?, ?, NOW()
                )
            ");
            
            $alteracoes = json_encode([
                'campo' => 'importante',
                'valor_anterior' => $observacao['importante'],
                'valor_novo' => $resultado['importante'],
                'observacao_preview' => substr($observacao['observacao'], 0, 100) . '...'
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
            error_log("Aviso: Falha ao registrar auditoria de toggle importante: " . $e->getMessage());
        }
        
        // Contar total de observações importantes do associado
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM Observacoes_Associado 
            WHERE associado_id = ? AND ativo = 1 AND importante = 1
        ");
        $stmt->execute([$observacao['associado_id']]);
        $totalImportantes = $stmt->fetchColumn();
        
        // Retornar sucesso
        echo json_encode([
            'status' => 'success',
            'message' => $resultado['importante'] ? 
                'Observação marcada como importante' : 
                'Observação desmarcada como importante',
            'data' => [
                'id' => $observacaoId,
                'importante' => $resultado['importante'],
                'observacao' => $observacaoAtualizada,
                'associado' => [
                    'id' => $observacao['associado_id'],
                    'nome' => $observacao['associado_nome'],
                    'total_importantes' => $totalImportantes
                ],
                'alterado_por' => [
                    'id' => $usuarioAtual['id'],
                    'nome' => $usuarioAtual['nome'] ?? 'Sistema'
                ],
                'data_alteracao' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Falha ao alterar status de importante');
    }
    
} catch (PDOException $e) {
    error_log("Erro DB ao alterar importância: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao alterar status no banco de dados',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
} catch (Exception $e) {
    error_log("Erro ao alterar importância: " . $e->getMessage());
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