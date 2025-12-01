<?php
/**
 * API: Quitar Todas as Dívidas
 * Quita todas as pendências de um associado de uma vez
 * 
 * POST /api/financeiro/quitar_dividas.php
 * Body JSON: {
 *   "associado_id": 123,
 *   "valor_total": 1068.49,
 *   "forma_pagamento": "PIX",
 *   "observacao": "Quitação total em dinheiro"
 * }
 */

ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true;
});

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

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro fatal do PHP'
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendJson($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['status' => 'error', 'message' => 'Método não permitido. Use POST.'], 405);
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$funcionarioId = $_SESSION['funcionario_id'] ?? $_SESSION['user_id'] ?? null;
if (!$funcionarioId) {
    sendJson(['status' => 'error', 'message' => 'Não autorizado'], 401);
}

// Ler dados
$input = file_get_contents('php://input');
$dados = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJson(['status' => 'error', 'message' => 'JSON inválido'], 400);
}

// Validações
$associadoId = isset($dados['associado_id']) ? (int)$dados['associado_id'] : 0;
$valorTotal = isset($dados['valor_total']) ? (float)$dados['valor_total'] : 0;
$formaPagamento = $dados['forma_pagamento'] ?? 'QUITACAO_TOTAL';
$observacao = $dados['observacao'] ?? '';

// Formas de pagamento válidas
$formasValidas = ['PIX', 'DINHEIRO', 'CARTAO', 'TRANSFERENCIA', 'BOLETO', 'QUITACAO_TOTAL', 'ACORDO'];

if ($associadoId <= 0) {
    sendJson(['status' => 'error', 'message' => 'ID do associado inválido'], 400);
}

if ($valorTotal < 0) {
    sendJson(['status' => 'error', 'message' => 'Valor total não pode ser negativo'], 400);
}

if (!in_array(strtoupper($formaPagamento), $formasValidas)) {
    $formaPagamento = 'QUITACAO_TOTAL';
}

try {
    // ========================================
    // CONEXÃO
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
    // VERIFICAR ASSOCIADO
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
    // BUSCAR DATA DE FILIAÇÃO
    // ========================================
    
    $stmt = $conn->prepare("SELECT dataFiliacao FROM Contrato WHERE associado_id = ?");
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $contrato = $result->fetch_assoc();
    $stmt->close();
    
    if (!$contrato || !$contrato['dataFiliacao']) {
        sendJson(['status' => 'error', 'message' => 'Data de filiação não encontrada'], 400);
    }
    
    // ========================================
    // BUSCAR VALOR MENSAL
    // ========================================
    
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(valor_aplicado), 181.46) as valor_mensal
        FROM Servicos_Associado
        WHERE associado_id = ? AND ativo = 1
    ");
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $servicos = $result->fetch_assoc();
    $stmt->close();
    
    $valorMensal = (float)$servicos['valor_mensal'];
    
    // ========================================
    // BUSCAR MESES JÁ PAGOS
    // ========================================
    
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(mes_referencia, '%Y-%m') as mes
        FROM Pagamentos_Associado
        WHERE associado_id = ? AND status_pagamento = 'CONFIRMADO'
    ");
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mesesPagos = [];
    while ($row = $result->fetch_assoc()) {
        $mesesPagos[$row['mes']] = true;
    }
    $stmt->close();
    
    // ========================================
    // IDENTIFICAR MESES PENDENTES
    // ========================================
    
    $dataFiliacao = new DateTime($contrato['dataFiliacao']);
    $mesAtual = new DateTime('first day of this month');
    $mesIterador = clone $dataFiliacao;
    $mesIterador->modify('first day of this month');
    
    $mesesPendentes = [];
    $valorCalculado = 0;
    
    while ($mesIterador < $mesAtual) {
        $mesKey = $mesIterador->format('Y-m');
        
        if (!isset($mesesPagos[$mesKey])) {
            $mesesPendentes[] = [
                'mes_key' => $mesKey,
                'mes_referencia' => $mesIterador->format('Y-m-01'),
                'mes_formatado' => $mesIterador->format('m/Y'),
                'valor' => $valorMensal
            ];
            $valorCalculado += $valorMensal;
        }
        
        $mesIterador->modify('+1 month');
    }
    
    // ========================================
    // VERIFICAR SE HÁ PENDÊNCIAS
    // ========================================
    
    if (count($mesesPendentes) === 0) {
        sendJson([
            'status' => 'info',
            'message' => 'Associado não possui pendências a quitar',
            'data' => [
                'associado_id' => $associadoId,
                'associado_nome' => $associado['nome'],
                'situacao' => 'Adimplente'
            ]
        ]);
    }
    
    // Se valor não foi informado, usar o calculado
    if ($valorTotal <= 0) {
        $valorTotal = $valorCalculado;
    }
    
    // ========================================
    // INICIAR TRANSAÇÃO
    // ========================================
    
    $conn->begin_transaction();
    
    try {
        $pagamentosInseridos = [];
        $pagamentosAtualizados = [];
        
        // ========================================
        // QUITAR CADA MÊS PENDENTE
        // ========================================
        
        foreach ($mesesPendentes as $pendencia) {
            $mesRef = $pendencia['mes_referencia'];
            
            // Verificar se já existe registro (pendente ou cancelado)
            $stmt = $conn->prepare("
                SELECT id, status_pagamento 
                FROM Pagamentos_Associado 
                WHERE associado_id = ? AND mes_referencia = ?
            ");
            $stmt->bind_param('is', $associadoId, $mesRef);
            $stmt->execute();
            $result = $stmt->get_result();
            $existente = $result->fetch_assoc();
            $stmt->close();
            
            $obsQuitacao = "Quitação total de dívidas - $formaPagamento" . ($observacao ? " - $observacao" : "");
            
            if ($existente) {
                // Atualizar registro existente
                $stmt = $conn->prepare("
                    UPDATE Pagamentos_Associado 
                    SET valor_pago = ?,
                        data_pagamento = CURDATE(),
                        forma_pagamento = ?,
                        status_pagamento = 'CONFIRMADO',
                        observacoes = ?,
                        funcionario_registro = ?,
                        data_atualizacao = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('dssii', 
                    $pendencia['valor'], 
                    $formaPagamento, 
                    $obsQuitacao, 
                    $funcionarioId, 
                    $existente['id']
                );
                $stmt->execute();
                $stmt->close();
                
                $pagamentosAtualizados[] = [
                    'id' => $existente['id'],
                    'mes' => $pendencia['mes_formatado'],
                    'valor' => $pendencia['valor']
                ];
            } else {
                // Inserir novo registro
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
                        funcionario_registro
                    ) VALUES (?, ?, ?, CURDATE(), ?, ?, 'CONFIRMADO', 'QUITACAO', ?, ?)
                ");
                
                $dataVenc = (new DateTime($mesRef))->format('Y-m-10');
                
                $stmt->bind_param('isdsssi', 
                    $associadoId, 
                    $mesRef, 
                    $pendencia['valor'],
                    $dataVenc,
                    $formaPagamento, 
                    $obsQuitacao, 
                    $funcionarioId
                );
                $stmt->execute();
                
                $pagamentosInseridos[] = [
                    'id' => $conn->insert_id,
                    'mes' => $pendencia['mes_formatado'],
                    'valor' => $pendencia['valor']
                ];
                $stmt->close();
            }
        }
        
        // ========================================
        // ATUALIZAR SITUAÇÃO FINANCEIRA
        // ========================================
        
        $stmt = $conn->prepare("
            UPDATE Financeiro 
            SET situacaoFinanceira = 'Adimplente',
                observacoes = CONCAT(IFNULL(observacoes, ''), '\n[', NOW(), '] Quitação total de ', ?, ' meses - Valor: R$ ', ?),
                data_ultima_verificacao = NOW()
            WHERE associado_id = ?
        ");
        $qtdMeses = count($mesesPendentes);
        $valorFormatado = number_format($valorTotal, 2, ',', '.');
        $stmt->bind_param('isi', $qtdMeses, $valorFormatado, $associadoId);
        $stmt->execute();
        $stmt->close();
        
        // ========================================
        // REGISTRAR OBSERVAÇÃO
        // ========================================
        
        $obsTexto = "QUITAÇÃO TOTAL DE DÍVIDAS\n";
        $obsTexto .= "-----------------------------------\n";
        $obsTexto .= "Meses quitados: $qtdMeses\n";
        $obsTexto .= "Valor total: R$ $valorFormatado\n";
        $obsTexto .= "Forma de pagamento: $formaPagamento\n";
        $obsTexto .= "Meses: " . implode(', ', array_column($mesesPendentes, 'mes_formatado')) . "\n";
        if ($observacao) {
            $obsTexto .= "Observação: $observacao\n";
        }
        
        $stmt = $conn->prepare("
            INSERT INTO Observacoes_Associado (
                associado_id,
                observacao,
                categoria,
                prioridade,
                importante,
                criado_por,
                data_criacao
            ) VALUES (?, ?, 'financeiro', 'alta', 1, ?, NOW())
        ");
        $stmt->bind_param('isi', $associadoId, $obsTexto, $funcionarioId);
        $stmt->execute();
        $observacaoId = $conn->insert_id;
        $stmt->close();
        
        // ========================================
        // REGISTRAR AUDITORIA
        // ========================================
        
        $alteracoes = json_encode([
            'acao' => 'QUITACAO_TOTAL',
            'associado_id' => $associadoId,
            'valor_total' => $valorTotal,
            'valor_calculado' => $valorCalculado,
            'meses_quitados' => count($mesesPendentes),
            'forma_pagamento' => $formaPagamento,
            'pagamentos_inseridos' => count($pagamentosInseridos),
            'pagamentos_atualizados' => count($pagamentosAtualizados),
            'meses_lista' => array_column($mesesPendentes, 'mes_formatado')
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
            ) VALUES ('Pagamentos_Associado', 'QUITACAO_TOTAL', ?, ?, ?, ?, NOW(), ?)
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param('iiiss', $observacaoId, $funcionarioId, $associadoId, $alteracoes, $ip);
        $stmt->execute();
        $stmt->close();
        
        // ========================================
        // CRIAR NOTIFICAÇÃO (se tabela existir)
        // ========================================
        
        // Verificar se tabela de notificações existe
        $result = $conn->query("SHOW TABLES LIKE 'Notificacoes'");
        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("
                INSERT INTO Notificacoes (
                    departamento_id,
                    associado_id,
                    tipo,
                    titulo,
                    mensagem,
                    dados_alteracao,
                    criado_por,
                    prioridade
                ) VALUES (2, ?, 'ALTERACAO_FINANCEIRO', ?, ?, ?, ?, 'ALTA')
            ");
            
            $titulo = "Quitação Total de Dívidas - " . $associado['nome'];
            $mensagem = "O associado {$associado['nome']} (ID: $associadoId) quitou todas as dívidas. Total: R$ $valorFormatado ($qtdMeses meses).";
            $dadosJson = json_encode([
                'valor' => $valorTotal,
                'meses' => $qtdMeses,
                'forma_pagamento' => $formaPagamento
            ]);
            
            $stmt->bind_param('isssi', $associadoId, $titulo, $mensagem, $dadosJson, $funcionarioId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Commit
        $conn->commit();
        
        // ========================================
        // RESPOSTA SUCESSO
        // ========================================
        
        sendJson([
            'status' => 'success',
            'message' => 'Todas as dívidas foram quitadas com sucesso',
            'data' => [
                'associado_id' => $associadoId,
                'associado_nome' => $associado['nome'],
                'cpf' => $associado['cpf'],
                'meses_quitados' => count($mesesPendentes),
                'valor_total' => round($valorTotal, 2),
                'valor_calculado' => round($valorCalculado, 2),
                'forma_pagamento' => $formaPagamento,
                'meses_detalhes' => array_map(function($m) {
                    return [
                        'mes' => $m['mes_formatado'],
                        'valor' => round($m['valor'], 2)
                    ];
                }, $mesesPendentes),
                'pagamentos_inseridos' => count($pagamentosInseridos),
                'pagamentos_atualizados' => count($pagamentosAtualizados),
                'situacao_financeira' => 'Adimplente',
                'observacao_id' => $observacaoId,
                'registrado_em' => date('Y-m-d H:i:s'),
                'funcionario_id' => $funcionarioId
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em quitar_dividas.php: " . $e->getMessage());
    sendJson([
        'status' => 'error',
        'message' => 'Erro ao processar quitação',
        'debug' => $e->getMessage()
    ], 500);
}