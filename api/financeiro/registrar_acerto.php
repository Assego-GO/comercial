<?php
/**
 * API: Registrar Acerto de Dívida Individual
 * Quita uma pendência específica de um mês
 * 
 * POST /api/financeiro/registrar_acerto.php
 * Body JSON: {
 *   "associado_id": 123,
 *   "mes_referencia": "2024-03-01",
 *   "valor": 156.32,
 *   "servico_nome": "Contribuição social",
 *   "observacao": "Acerto realizado em atendimento presencial"
 * }
 */

// Capturar erros antes de qualquer output
ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Handler de erro
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true;
});

// Handler de exceção
set_exception_handler(function($e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'debug' => $e->getMessage()
    ]);
    exit;
});

// Handler de shutdown
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro fatal do PHP',
            'debug' => $error['message']
        ]);
        exit;
    }
});

ob_end_clean();
ob_start();

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para resposta JSON
function sendJson($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['status' => 'error', 'message' => 'Método não permitido. Use POST.'], 405);
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Verificar autenticação
$funcionarioId = $_SESSION['funcionario_id'] ?? $_SESSION['user_id'] ?? null;
if (!$funcionarioId) {
    sendJson(['status' => 'error', 'message' => 'Não autorizado'], 401);
}

// Ler dados do body
$input = file_get_contents('php://input');
$dados = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJson(['status' => 'error', 'message' => 'JSON inválido'], 400);
}

// Validar campos obrigatórios
$associadoId = isset($dados['associado_id']) ? (int)$dados['associado_id'] : 0;
$mesReferencia = $dados['mes_referencia'] ?? null;
$valor = isset($dados['valor']) ? (float)$dados['valor'] : 0;
$servicoNome = $dados['servico_nome'] ?? 'Contribuição';
$observacao = $dados['observacao'] ?? null;

if ($associadoId <= 0) {
    sendJson(['status' => 'error', 'message' => 'ID do associado inválido'], 400);
}

if (empty($mesReferencia)) {
    sendJson(['status' => 'error', 'message' => 'Mês de referência é obrigatório'], 400);
}

if ($valor <= 0) {
    sendJson(['status' => 'error', 'message' => 'Valor deve ser maior que zero'], 400);
}

// Validar formato do mês de referência
try {
    $dataMes = new DateTime($mesReferencia);
    $mesReferencia = $dataMes->format('Y-m-01'); // Normalizar para primeiro dia do mês
} catch (Exception $e) {
    sendJson(['status' => 'error', 'message' => 'Formato de data inválido. Use YYYY-MM-DD'], 400);
}

try {
    // ========================================
    // CONEXÃO COM BANCO
    // ========================================
    
    $conn = null;
    $configPath = __DIR__ . '/../../config/database.php';
    
    if (file_exists($configPath)) {
        ob_start();
        @include_once $configPath;
        ob_end_clean();
    }
    
    if (!isset($conn) || $conn === null || (is_object($conn) && $conn->connect_error)) {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $user = defined('DB_USER') ? DB_USER : 'wwasse';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dbname = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : 'wwasse_cadastro';
        
        if ($pass === '') {
            $configPath2 = __DIR__ . '/../../config/config.php';
            if (file_exists($configPath2)) {
                ob_start();
                @include_once $configPath2;
                ob_end_clean();
                
                $host = defined('DB_HOST') ? DB_HOST : 'localhost';
                $user = defined('DB_USER') ? DB_USER : 'wwasse';
                $pass = defined('DB_PASS') ? DB_PASS : '';
                $dbname = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : 'wwasse_cadastro';
            }
        }
        
        $conn = @new mysqli($host, $user, $pass, $dbname);
        
        if ($conn->connect_error) {
            sendJson(['status' => 'error', 'message' => 'Erro de conexão com banco'], 500);
        }
    }
    
    $conn->set_charset("utf8mb4");
    
    // ========================================
    // VERIFICAR SE ASSOCIADO EXISTE
    // ========================================
    
    $stmt = $conn->prepare("SELECT id, nome, cpf FROM Associados WHERE id = ?");
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $associado = $result->fetch_assoc();
    $stmt->close();
    
    if (!$associado) {
        sendJson(['status' => 'error', 'message' => 'Associado não encontrado'], 404);
    }
    
    // ========================================
    // VERIFICAR SE JÁ EXISTE PAGAMENTO PARA ESTE MÊS
    // ========================================
    
    $stmt = $conn->prepare("
        SELECT id, valor_pago, status_pagamento 
        FROM Pagamentos_Associado 
        WHERE associado_id = ? AND mes_referencia = ?
    ");
    $stmt->bind_param('is', $associadoId, $mesReferencia);
    $stmt->execute();
    $result = $stmt->get_result();
    $pagamentoExistente = $result->fetch_assoc();
    $stmt->close();
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        $pagamentoId = null;
        $acao = 'INSERT';
        
        if ($pagamentoExistente) {
            // Se já existe pagamento cancelado ou pendente, atualiza
            if (in_array($pagamentoExistente['status_pagamento'], ['CANCELADO', 'PENDENTE'])) {
                $stmt = $conn->prepare("
                    UPDATE Pagamentos_Associado 
                    SET valor_pago = ?,
                        data_pagamento = CURDATE(),
                        forma_pagamento = 'ACERTO_DIVIDA',
                        status_pagamento = 'CONFIRMADO',
                        observacoes = ?,
                        funcionario_registro = ?,
                        data_atualizacao = NOW()
                    WHERE id = ?
                ");
                
                $obsCompleta = "Acerto de dívida - $servicoNome" . ($observacao ? ". $observacao" : "");
                $stmt->bind_param('dsii', $valor, $obsCompleta, $funcionarioId, $pagamentoExistente['id']);
                $stmt->execute();
                $stmt->close();
                
                $pagamentoId = $pagamentoExistente['id'];
                $acao = 'UPDATE';
            } else {
                // Já existe pagamento confirmado
                $conn->rollback();
                sendJson([
                    'status' => 'error',
                    'message' => 'Já existe um pagamento confirmado para este mês',
                    'pagamento_existente' => [
                        'id' => $pagamentoExistente['id'],
                        'valor' => (float)$pagamentoExistente['valor_pago'],
                        'status' => $pagamentoExistente['status_pagamento']
                    ]
                ], 409);
            }
        } else {
            // Inserir novo pagamento
            $stmt = $conn->prepare("
                INSERT INTO Pagamentos_Associado (
                    associado_id,
                    mes_referencia,
                    valor_pago,
                    data_pagamento,
                    data_vencimento,
                    forma_pagamento,
                    status_pagamento,
                    origem_importacao,
                    observacoes,
                    funcionario_registro,
                    data_registro
                ) VALUES (?, ?, ?, CURDATE(), ?, 'ACERTO_DIVIDA', 'CONFIRMADO', 'SISTEMA', ?, ?, NOW())
            ");
            
            $dataVencimento = $dataMes->format('Y-m-10'); // Vencimento dia 10
            $obsCompleta = "Acerto de dívida - $servicoNome" . ($observacao ? ". $observacao" : "");
            
            $stmt->bind_param('isdssi', 
                $associadoId, 
                $mesReferencia, 
                $valor, 
                $dataVencimento,
                $obsCompleta, 
                $funcionarioId
            );
            $stmt->execute();
            $pagamentoId = $conn->insert_id;
            $stmt->close();
        }
        
        // ========================================
        // REGISTRAR NA AUDITORIA
        // ========================================
        
        $alteracoes = json_encode([
            'acao' => 'ACERTO_DIVIDA',
            'associado_id' => $associadoId,
            'mes_referencia' => $mesReferencia,
            'valor' => $valor,
            'servico' => $servicoNome,
            'pagamento_id' => $pagamentoId
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt = $conn->prepare("
            INSERT INTO Auditoria (
                tabela, 
                acao, 
                registro_id, 
                funcionario_id, 
                associado_id,
                alteracoes, 
                data_hora,
                ip_origem
            ) VALUES ('Pagamentos_Associado', 'ACERTO_DIVIDA', ?, ?, ?, ?, NOW(), ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param('iiiss', $pagamentoId, $funcionarioId, $associadoId, $alteracoes, $ip);
        $stmt->execute();
        $stmt->close();
        
        // ========================================
        // ATUALIZAR SITUAÇÃO FINANCEIRA (opcional)
        // ========================================
        
        // Verificar se ainda há pendências
        $stmt = $conn->prepare("
            SELECT COUNT(*) as pendentes
            FROM (
                SELECT DATE_FORMAT(d.data, '%Y-%m-01') as mes
                FROM (
                    SELECT DATE_ADD(c.dataFiliacao, INTERVAL n.n MONTH) as data
                    FROM Contrato c
                    CROSS JOIN (
                        SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
                        UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
                        UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
                        UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15
                        UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
                        UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23
                    ) n
                    WHERE c.associado_id = ?
                    AND DATE_ADD(c.dataFiliacao, INTERVAL n.n MONTH) < DATE_FORMAT(NOW(), '%Y-%m-01')
                ) d
            ) meses_devidos
            WHERE mes NOT IN (
                SELECT DATE_FORMAT(mes_referencia, '%Y-%m-01')
                FROM Pagamentos_Associado
                WHERE associado_id = ? AND status_pagamento = 'CONFIRMADO'
            )
        ");
        $stmt->bind_param('ii', $associadoId, $associadoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $pendencias = $result->fetch_assoc();
        $stmt->close();
        
        $novaSituacao = ($pendencias['pendentes'] ?? 0) > 0 ? 'Inadimplente' : 'Adimplente';
        
        // Atualizar situação financeira
        $stmt = $conn->prepare("
            UPDATE Financeiro 
            SET situacaoFinanceira = ?,
                data_ultima_verificacao = NOW()
            WHERE associado_id = ?
        ");
        $stmt->bind_param('si', $novaSituacao, $associadoId);
        $stmt->execute();
        $stmt->close();
        
        // Commit da transação
        $conn->commit();
        
        // ========================================
        // RESPOSTA DE SUCESSO
        // ========================================
        
        sendJson([
            'status' => 'success',
            'message' => 'Acerto de dívida registrado com sucesso',
            'data' => [
                'pagamento_id' => $pagamentoId,
                'associado_id' => $associadoId,
                'associado_nome' => $associado['nome'],
                'mes_referencia' => $mesReferencia,
                'mes_formatado' => $dataMes->format('m/Y'),
                'valor' => round($valor, 2),
                'forma_pagamento' => 'ACERTO_DIVIDA',
                'status' => 'CONFIRMADO',
                'acao_realizada' => $acao,
                'situacao_financeira' => $novaSituacao,
                'pendencias_restantes' => (int)($pendencias['pendentes'] ?? 0),
                'registrado_em' => date('Y-m-d H:i:s'),
                'funcionario_id' => $funcionarioId
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em registrar_acerto.php: " . $e->getMessage());
    sendJson([
        'status' => 'error',
        'message' => 'Erro ao processar acerto de dívida',
        'debug' => $e->getMessage()
    ], 500);
}