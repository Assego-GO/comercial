<?php
/**
 * API: Buscar Pendências Financeiras do Associado
 * VERSÃO 3.0 - COM TRATAMENTO TOTAL DE ERROS
 */

// CRÍTICO: Capturar QUALQUER erro antes de qualquer coisa
ob_start();

// Desabilitar exibição de erros HTML completamente
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Handler de erro customizado
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

// Handler de shutdown para erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro fatal do PHP',
            'debug' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

// Limpar qualquer output anterior
ob_end_clean();
ob_start();

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Função para resposta JSON limpa
function sendJson($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Iniciar sessão com verificação
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Verificar autenticação
if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['user_id'])) {
    sendJson(['status' => 'error', 'message' => 'Não autorizado'], 401);
}

// Validar ID
$associadoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($associadoId <= 0) {
    sendJson(['status' => 'error', 'message' => 'ID do associado inválido'], 400);
}

try {
    // ========================================
    // CONEXÃO COM BANCO
    // ========================================
    
    $conn = null;
    
    // Tentar incluir config de forma segura
    $configPath = __DIR__ . '/../../config/database.php';
    
    if (file_exists($configPath)) {
        // Capturar qualquer output do include
        ob_start();
        @include_once $configPath;
        ob_end_clean();
    }
    
    // Verificar se $conn foi criado pelo include
    if (!isset($conn) || $conn === null || (is_object($conn) && $conn->connect_error)) {
        
        // Tentar pegar credenciais de constantes ou usar padrão
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $user = defined('DB_USER') ? DB_USER : 'wwasse';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $dbname = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : 'wwasse_cadastro';
        
        // Se não temos as credenciais, tentar config.php
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
        
        // Criar conexão
        $conn = @new mysqli($host, $user, $pass, $dbname);
        
        if ($conn->connect_error) {
            sendJson([
                'status' => 'error',
                'message' => 'Erro de conexão com banco de dados',
                'debug' => 'Connection failed'
            ], 500);
        }
    }
    
    $conn->set_charset("utf8mb4");
    
    // ========================================
    // 1. BUSCAR DATA DE FILIAÇÃO
    // ========================================
    
    $sql = "SELECT c.dataFiliacao, a.situacao, a.nome 
            FROM Contrato c 
            INNER JOIN Associados a ON a.id = c.associado_id 
            WHERE c.associado_id = ? 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendJson(['status' => 'error', 'message' => 'Erro na query de filiação', 'debug' => $conn->error], 500);
    }
    
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dadosFiliacao = $result->fetch_assoc();
    $stmt->close();
    
    if (!$dadosFiliacao || !$dadosFiliacao['dataFiliacao']) {
        sendJson([
            'status' => 'success',
            'data' => [
                'associado_id' => $associadoId,
                'meses_atraso' => 0,
                'valor_total_debito' => 0,
                'valor_mensal' => 181.46,
                'meses_pendentes' => [],
                'ultimo_pagamento' => null,
                'mensagem' => 'Data de filiação não encontrada'
            ]
        ]);
    }
    
    $dataFiliacao = new DateTime($dadosFiliacao['dataFiliacao']);
    $nomeAssociado = $dadosFiliacao['nome'] ?? '';
    
    // ========================================
    // 2. BUSCAR DADOS FINANCEIROS
    // ========================================
    
    $sql = "SELECT tipoAssociado, situacaoFinanceira, vinculoServidor, localDebito 
            FROM Financeiro WHERE associado_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dadosFinanceiros = $result->fetch_assoc() ?: [];
    $stmt->close();
    
    // ========================================
    // 3. BUSCAR VALOR MENSAL
    // ========================================
    
    $sql = "SELECT COALESCE(SUM(valor_aplicado), 181.46) as valor_mensal 
            FROM Servicos_Associado 
            WHERE associado_id = ? AND ativo = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $valorMensalAtual = (float)($row['valor_mensal'] ?? 181.46);
    $stmt->close();
    
    if ($valorMensalAtual <= 0) {
        $valorMensalAtual = 181.46;
    }
    
    // ========================================
    // 4. BUSCAR PAGAMENTOS CONFIRMADOS
    // ========================================
    
    $sql = "SELECT mes_referencia, valor_pago, data_pagamento 
            FROM Pagamentos_Associado 
            WHERE associado_id = ? AND status_pagamento = 'CONFIRMADO' 
            ORDER BY mes_referencia ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mesesPagos = [];
    $ultimoPagamento = null;
    
    while ($row = $result->fetch_assoc()) {
        $mesRef = date('Y-m', strtotime($row['mes_referencia']));
        $mesesPagos[$mesRef] = [
            'valor' => (float)$row['valor_pago'],
            'data_pagamento' => $row['data_pagamento']
        ];
        $ultimoPagamento = $row;
    }
    $stmt->close();
    
    // ========================================
    // 5. CALCULAR MESES PENDENTES
    // ========================================
    
    $mesAtual = new DateTime('first day of this month');
    $primeiroMesCobranca = new DateTime($dataFiliacao->format('Y-m-01'));
    
    // Último mês a verificar: mês ANTERIOR ao atual
    $ultimoMesVerificar = clone $mesAtual;
    $ultimoMesVerificar->modify('-1 month');
    
    // Se filiação é recente, sem pendências
    if ($primeiroMesCobranca > $ultimoMesVerificar) {
        sendJson([
            'status' => 'success',
            'data' => [
                'associado_id' => $associadoId,
                'nome_associado' => $nomeAssociado,
                'situacao_financeira' => 'Adimplente',
                'tipo_associado' => $dadosFinanceiros['tipoAssociado'] ?? 'Contribuinte',
                'vinculo_servidor' => $dadosFinanceiros['vinculoServidor'] ?? null,
                'local_debito' => $dadosFinanceiros['localDebito'] ?? null,
                'valor_mensal' => $valorMensalAtual,
                'meses_atraso' => 0,
                'valor_total_debito' => 0,
                'ultimo_pagamento' => null,
                'meses_pendentes' => []
            ]
        ]);
    }
    
    // Percorrer meses
    $mesesPendentes = [];
    $valorTotalDebito = 0;
    $mesIterador = clone $primeiroMesCobranca;
    
    while ($mesIterador <= $ultimoMesVerificar) {
        $mesKey = $mesIterador->format('Y-m');
        
        if (!isset($mesesPagos[$mesKey])) {
            $valorMes = $valorMensalAtual;
            
            $mesesPendentes[] = [
                'mes_referencia' => $mesIterador->format('Y-m-01'),
                'mes_formatado' => $mesIterador->format('m/Y'),
                'valor' => $valorMes,
                'status' => 'PENDENTE'
            ];
            
            $valorTotalDebito += $valorMes;
        }
        
        $mesIterador->modify('+1 month');
    }
    
    $mesesAtraso = count($mesesPendentes);
    
    // ========================================
    // 6. RESPOSTA FINAL
    // ========================================
    
    $tempoRelativo = null;
    if ($ultimoPagamento && isset($ultimoPagamento['mes_referencia'])) {
        $dataRef = new DateTime($ultimoPagamento['mes_referencia']);
        $hoje = new DateTime();
        $diff = $hoje->diff($dataRef);
        
        if ($diff->y > 0) {
            $tempoRelativo = "Há " . $diff->y . " ano" . ($diff->y > 1 ? "s" : "");
        } elseif ($diff->m > 0) {
            $tempoRelativo = "Há " . $diff->m . " " . ($diff->m > 1 ? "meses" : "mês");
        } else {
            $tempoRelativo = "Este mês";
        }
    }
    
    sendJson([
        'status' => 'success',
        'data' => [
            'associado_id' => $associadoId,
            'nome_associado' => $nomeAssociado,
            'data_filiacao' => $dataFiliacao->format('Y-m-d'),
            'situacao_financeira' => $mesesAtraso > 0 ? 'Inadimplente' : 'Adimplente',
            'tipo_associado' => $dadosFinanceiros['tipoAssociado'] ?? 'Contribuinte',
            'vinculo_servidor' => $dadosFinanceiros['vinculoServidor'] ?? null,
            'local_debito' => $dadosFinanceiros['localDebito'] ?? null,
            'valor_mensal' => round($valorMensalAtual, 2),
            'meses_atraso' => $mesesAtraso,
            'valor_total_debito' => round($valorTotalDebito, 2),
            'ultimo_pagamento' => $ultimoPagamento ? [
                'mes_referencia' => $ultimoPagamento['mes_referencia'],
                'valor' => (float)$ultimoPagamento['valor_pago'],
                'data' => $ultimoPagamento['data_pagamento'],
                'tempo_relativo' => $tempoRelativo
            ] : null,
            'meses_pendentes' => array_reverse($mesesPendentes)
        ]
    ]);

} catch (Exception $e) {
    sendJson([
        'status' => 'error',
        'message' => 'Erro ao processar',
        'debug' => $e->getMessage()
    ], 500);
}