<?php
/**
 * API: Registrar Renegociação de Dívidas
 * Consolida dívidas pendentes e lança valor renegociado na próxima fatura
 * 
 * POST /api/financeiro/registrar_renegociacao.php
 * Body JSON: {
 *   "associado_id": 123,
 *   "valor_renegociado": 500.00,
 *   "pendencias_ids": [1, 2, 3],
 *   "parcelas": 1,
 *   "observacao": "Acordo realizado em atendimento"
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
$valorRenegociado = isset($dados['valor_renegociado']) ? (float)$dados['valor_renegociado'] : 0;
$pendenciasIds = $dados['pendencias_ids'] ?? [];
$parcelas = isset($dados['parcelas']) ? (int)$dados['parcelas'] : 1;
$observacao = $dados['observacao'] ?? '';

if ($associadoId <= 0) {
    sendJson(['status' => 'error', 'message' => 'ID do associado inválido'], 400);
}

if ($valorRenegociado <= 0) {
    sendJson(['status' => 'error', 'message' => 'Valor renegociado deve ser maior que zero'], 400);
}

if ($parcelas < 1 || $parcelas > 24) {
    sendJson(['status' => 'error', 'message' => 'Número de parcelas deve ser entre 1 e 24'], 400);
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
    // BUSCAR VALOR ORIGINAL DAS DÍVIDAS (para registro)
    // ========================================
    
    $valorOriginalTotal = 0;
    $mesesPendentes = [];
    
    // Buscar pendências reais do associado
    $stmt = $conn->prepare("
        SELECT c.dataFiliacao
        FROM Contrato c
        WHERE c.associado_id = ?
    ");
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $contrato = $result->fetch_assoc();
    $stmt->close();
    
    if ($contrato && $contrato['dataFiliacao']) {
        // Buscar valor mensal dos serviços
        $stmt = $conn->prepare("
            SELECT SUM(valor_aplicado) as valor_mensal
            FROM Servicos_Associado
            WHERE associado_id = ? AND ativo = 1
        ");
        $stmt->bind_param('i', $associadoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $servicos = $result->fetch_assoc();
        $stmt->close();
        
        $valorMensal = (float)($servicos['valor_mensal'] ?? 181.46);
        
        // Buscar meses pagos
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
        
        // Calcular meses pendentes
        $dataFiliacao = new DateTime($contrato['dataFiliacao']);
        $mesAtual = new DateTime('first day of this month');
        $mesIterador = clone $dataFiliacao;
        $mesIterador->modify('first day of this month');
        
        while ($mesIterador < $mesAtual) {
            $mesKey = $mesIterador->format('Y-m');
            if (!isset($mesesPagos[$mesKey])) {
                $mesesPendentes[] = $mesIterador->format('m/Y');
                $valorOriginalTotal += $valorMensal;
            }
            $mesIterador->modify('+1 month');
        }
    }
    
    // ========================================
    // INICIAR TRANSAÇÃO
    // ========================================
    
    $conn->begin_transaction();
    
    try {
        // Calcular valor por parcela
        $valorParcela = round($valorRenegociado / $parcelas, 2);
        
        // Próximo mês para lançamento
        $proximoMes = new DateTime('first day of next month');
        
        $renegociacaoIds = [];
        
        // ========================================
        // CRIAR REGISTROS DE RENEGOCIAÇÃO
        // ========================================
        
        // Primeiro, criar registro mestre da renegociação na tabela de observações
        $obsTexto = "RENEGOCIAÇÃO DE DÍVIDAS\n";
        $obsTexto .= "-----------------------------------\n";
        $obsTexto .= "Valor original das dívidas: R$ " . number_format($valorOriginalTotal, 2, ',', '.') . "\n";
        $obsTexto .= "Valor renegociado: R$ " . number_format($valorRenegociado, 2, ',', '.') . "\n";
        $obsTexto .= "Parcelas: $parcelas x R$ " . number_format($valorParcela, 2, ',', '.') . "\n";
        $obsTexto .= "Meses pendentes quitados: " . implode(', ', $mesesPendentes) . "\n";
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
        // QUITAR MESES PENDENTES (marcar como RENEGOCIADO)
        // ========================================
        
        // Usar a mesma lógica para quitar os meses pendentes
        if ($contrato && $contrato['dataFiliacao']) {
            $dataFiliacao = new DateTime($contrato['dataFiliacao']);
            $mesAtual = new DateTime('first day of this month');
            $mesIterador = clone $dataFiliacao;
            $mesIterador->modify('first day of this month');
            
            while ($mesIterador < $mesAtual) {
                $mesKey = $mesIterador->format('Y-m');
                $mesRef = $mesIterador->format('Y-m-01');
                
                if (!isset($mesesPagos[$mesKey])) {
                    // Verificar se já existe registro
                    $stmt = $conn->prepare("
                        SELECT id FROM Pagamentos_Associado 
                        WHERE associado_id = ? AND mes_referencia = ?
                    ");
                    $stmt->bind_param('is', $associadoId, $mesRef);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existente = $result->fetch_assoc();
                    $stmt->close();
                    
                    if ($existente) {
                        // Atualizar existente
                        $stmt = $conn->prepare("
                            UPDATE Pagamentos_Associado 
                            SET status_pagamento = 'CONFIRMADO',
                                forma_pagamento = 'RENEGOCIACAO',
                                valor_pago = 0,
                                observacoes = CONCAT(IFNULL(observacoes, ''), '\nQuitado via renegociação #', ?),
                                funcionario_registro = ?,
                                data_atualizacao = NOW()
                            WHERE id = ?
                        ");
                        $stmt->bind_param('iii', $observacaoId, $funcionarioId, $existente['id']);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        // Inserir novo
                        $stmt = $conn->prepare("
                            INSERT INTO Pagamentos_Associado (
                                associado_id,
                                mes_referencia,
                                valor_pago,
                                data_pagamento,
                                forma_pagamento,
                                status_pagamento,
                                origem_importacao,
                                observacoes,
                                funcionario_registro
                            ) VALUES (?, ?, 0, CURDATE(), 'RENEGOCIACAO', 'CONFIRMADO', 'RENEGOCIACAO', ?, ?)
                        ");
                        $obsReneg = "Quitado via renegociação #$observacaoId";
                        $stmt->bind_param('issi', $associadoId, $mesRef, $obsReneg, $funcionarioId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                
                $mesIterador->modify('+1 month');
            }
        }
        
        // ========================================
        // LANÇAR PARCELAS NOS PRÓXIMOS MESES
        // ========================================
        
        $parcelasLancadas = [];
        
        for ($i = 0; $i < $parcelas; $i++) {
            $mesLancamento = clone $proximoMes;
            $mesLancamento->modify("+$i month");
            $mesRef = $mesLancamento->format('Y-m-01');
            
            // Observação da parcela
            $obsParcela = "RENEGOCIAÇÃO - Parcela " . ($i + 1) . "/$parcelas (Acordo #$observacaoId)";
            
            // Verificar se já existe registro para este mês
            $stmt = $conn->prepare("
                SELECT id, valor_pago FROM Pagamentos_Associado 
                WHERE associado_id = ? AND mes_referencia = ? AND status_pagamento = 'PENDENTE'
            ");
            $stmt->bind_param('is', $associadoId, $mesRef);
            $stmt->execute();
            $result = $stmt->get_result();
            $existente = $result->fetch_assoc();
            $stmt->close();
            
            if ($existente) {
                // Adicionar ao valor existente
                $novoValor = (float)$existente['valor_pago'] + $valorParcela;
                $stmt = $conn->prepare("
                    UPDATE Pagamentos_Associado 
                    SET valor_pago = ?,
                        observacoes = CONCAT(IFNULL(observacoes, ''), '\n', ?),
                        data_atualizacao = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('dsi', $novoValor, $obsParcela, $existente['id']);
                $stmt->execute();
                $stmt->close();
                
                $parcelasLancadas[] = [
                    'parcela' => $i + 1,
                    'mes' => $mesLancamento->format('m/Y'),
                    'valor' => $valorParcela,
                    'acao' => 'ADICIONADO'
                ];
            } else {
                // Criar novo registro pendente
                $stmt = $conn->prepare("
                    INSERT INTO Pagamentos_Associado (
                        associado_id,
                        mes_referencia,
                        valor_pago,
                        data_vencimento,
                        forma_pagamento,
                        status_pagamento,
                        origem_importacao,
                        observacoes,
                        funcionario_registro
                    ) VALUES (?, ?, ?, ?, 'RENEGOCIACAO', 'PENDENTE', 'RENEGOCIACAO', ?, ?)
                ");
                $dataVenc = $mesLancamento->format('Y-m-10');
                $stmt->bind_param('isdssi', 
                    $associadoId, 
                    $mesRef, 
                    $valorParcela, 
                    $dataVenc,
                    $obsParcela, 
                    $funcionarioId
                );
                $stmt->execute();
                $renegociacaoIds[] = $conn->insert_id;
                $stmt->close();
                
                $parcelasLancadas[] = [
                    'parcela' => $i + 1,
                    'mes' => $mesLancamento->format('m/Y'),
                    'valor' => $valorParcela,
                    'acao' => 'CRIADO'
                ];
            }
        }
        
        // ========================================
        // ATUALIZAR SITUAÇÃO FINANCEIRA
        // ========================================
        
        $stmt = $conn->prepare("
            UPDATE Financeiro 
            SET situacaoFinanceira = 'Em Renegociação',
                observacoes = CONCAT(IFNULL(observacoes, ''), '\n[', NOW(), '] Renegociação de dívidas - Acordo #', ?),
                data_ultima_verificacao = NOW()
            WHERE associado_id = ?
        ");
        $stmt->bind_param('ii', $observacaoId, $associadoId);
        $stmt->execute();
        $stmt->close();
        
        // ========================================
        // REGISTRAR AUDITORIA
        // ========================================
        
        $alteracoes = json_encode([
            'acao' => 'RENEGOCIACAO_DIVIDAS',
            'associado_id' => $associadoId,
            'valor_original' => $valorOriginalTotal,
            'valor_renegociado' => $valorRenegociado,
            'parcelas' => $parcelas,
            'valor_parcela' => $valorParcela,
            'meses_quitados' => $mesesPendentes,
            'parcelas_lancadas' => $parcelasLancadas,
            'observacao_id' => $observacaoId
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
            ) VALUES ('Pagamentos_Associado', 'RENEGOCIACAO', ?, ?, ?, ?, NOW(), ?)
        ");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param('iiiss', $observacaoId, $funcionarioId, $associadoId, $alteracoes, $ip);
        $stmt->execute();
        $stmt->close();
        
        // Commit
        $conn->commit();
        
        // ========================================
        // RESPOSTA SUCESSO
        // ========================================
        
        sendJson([
            'status' => 'success',
            'message' => 'Renegociação registrada com sucesso',
            'data' => [
                'acordo_id' => $observacaoId,
                'associado_id' => $associadoId,
                'associado_nome' => $associado['nome'],
                'valor_original' => round($valorOriginalTotal, 2),
                'valor_renegociado' => round($valorRenegociado, 2),
                'desconto_percentual' => $valorOriginalTotal > 0 
                    ? round((1 - ($valorRenegociado / $valorOriginalTotal)) * 100, 2) 
                    : 0,
                'parcelas' => $parcelas,
                'valor_parcela' => round($valorParcela, 2),
                'meses_quitados' => count($mesesPendentes),
                'lista_meses_quitados' => $mesesPendentes,
                'parcelas_lancadas' => $parcelasLancadas,
                'primeiro_vencimento' => $proximoMes->format('d/m/Y'),
                'situacao_financeira' => 'Em Renegociação',
                'registrado_em' => date('Y-m-d H:i:s'),
                'funcionario_id' => $funcionarioId
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro em registrar_renegociacao.php: " . $e->getMessage());
    sendJson([
        'status' => 'error',
        'message' => 'Erro ao processar renegociação',
        'debug' => $e->getMessage()
    ], 500);
}