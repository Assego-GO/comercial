<?php
/**
 * API: Buscar Pendências Financeiras DETALHADAS do Associado
 * Retorna lista de pendências por mês para o modal de pendências
 * VERSÃO 1.0 - Baseado no padrão buscar_pendencias.php
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
        ob_start();
        @include_once $configPath;
        ob_end_clean();
    }
    
    // Verificar se $conn foi criado pelo include
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
            sendJson([
                'status' => 'error',
                'message' => 'Erro de conexão com banco de dados',
                'debug' => 'Connection failed'
            ], 500);
        }
    }
    
    $conn->set_charset("utf8mb4");
    
    // ========================================
    // 1. BUSCAR DADOS DO ASSOCIADO
    // ========================================
    
    $sql = "SELECT 
                a.id,
                a.nome,
                a.cpf,
                c.dataFiliacao,
                a.situacao
            FROM Associados a 
            LEFT JOIN Contrato c ON a.id = c.associado_id 
            WHERE a.id = ? 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendJson(['status' => 'error', 'message' => 'Erro na query', 'debug' => $conn->error], 500);
    }
    
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $associado = $result->fetch_assoc();
    $stmt->close();
    
    if (!$associado) {
        sendJson(['status' => 'error', 'message' => 'Associado não encontrado'], 404);
    }
    
    if (!$associado['dataFiliacao']) {
        sendJson([
            'status' => 'success',
            'data' => [
                'associado' => [
                    'id' => $associadoId,
                    'nome' => $associado['nome'],
                    'cpf' => $associado['cpf']
                ],
                'pendencias' => [],
                'total_debito' => 0,
                'meses_atraso' => 0,
                'valor_mensal' => 181.46,
                'mensagem' => 'Data de filiação não encontrada'
            ]
        ]);
    }
    
    $dataFiliacao = new DateTime($associado['dataFiliacao']);
    
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
    // 3. BUSCAR SERVIÇOS CONTRATADOS (para detalhar pendências)
    // ========================================
    
    $sql = "SELECT 
                sa.id,
                sa.servico_id,
                s.nome as servico_nome,
                sa.valor_aplicado,
                sa.data_adesao,
                sa.data_cancelamento,
                sa.ativo
            FROM Servicos_Associado sa
            INNER JOIN Servicos s ON sa.servico_id = s.id
            WHERE sa.associado_id = ? AND sa.ativo = 1
            ORDER BY s.nome ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $associadoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $servicosContratados = [];
    $valorMensalTotal = 0;
    
    while ($row = $result->fetch_assoc()) {
        $servicosContratados[] = $row;
        $valorMensalTotal += (float)$row['valor_aplicado'];
    }
    $stmt->close();
    
    // Valor padrão se não tem serviços
    if ($valorMensalTotal <= 0) {
        $valorMensalTotal = 181.46;
        // Serviço padrão
        $servicosContratados = [
            ['servico_nome' => 'Contribuição social', 'valor_aplicado' => 181.46]
        ];
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
    // 5. GERAR LISTA DE PENDÊNCIAS DETALHADAS
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
                'associado' => [
                    'id' => $associadoId,
                    'nome' => $associado['nome'],
                    'cpf' => $associado['cpf']
                ],
                'pendencias' => [],
                'total_debito' => 0,
                'meses_atraso' => 0,
                'valor_mensal' => round($valorMensalTotal, 2),
                'tipo_associado' => $dadosFinanceiros['tipoAssociado'] ?? 'Contribuinte',
                'vinculo_servidor' => $dadosFinanceiros['vinculoServidor'] ?? null,
                'local_debito' => $dadosFinanceiros['localDebito'] ?? null,
                'ultimo_pagamento' => null,
                'servicos' => $servicosContratados
            ]
        ]);
    }
    
    // Percorrer meses e gerar pendências detalhadas
    $pendencias = [];
    $valorTotalDebito = 0;
    $mesIterador = clone $primeiroMesCobranca;
    $idPendencia = 1;
    
    while ($mesIterador <= $ultimoMesVerificar) {
        $mesKey = $mesIterador->format('Y-m');
        
        // Se o mês NÃO foi pago
        if (!isset($mesesPagos[$mesKey])) {
            
            // Gerar uma pendência para cada serviço contratado
            foreach ($servicosContratados as $servico) {
                $pendencias[] = [
                    'id' => $idPendencia++,
                    'tipo' => $servico['servico_nome'] ?: 'Contribuição',
                    'mes' => $mesIterador->format('m/Y'),
                    'mes_referencia' => $mesIterador->format('Y-m-01'),
                    'valor' => round((float)$servico['valor_aplicado'], 2),
                    'status' => 'sem_retorno',
                    'status_texto' => 'sem retorno(assego)',
                    'data_vencimento' => $mesIterador->format('Y-m-10')
                ];
                
                $valorTotalDebito += (float)$servico['valor_aplicado'];
            }
        }
        
        $mesIterador->modify('+1 month');
    }
    
    // Inverter para mostrar os mais recentes primeiro
    $pendencias = array_reverse($pendencias);
    
    // Recalcular IDs após inversão
    $idPendencia = 1;
    foreach ($pendencias as &$p) {
        $p['id'] = $idPendencia++;
    }
    unset($p);
    
    $mesesAtraso = count(array_unique(array_column($pendencias, 'mes')));
    
    // ========================================
    // 6. CALCULAR TEMPO RELATIVO DO ÚLTIMO PAGAMENTO
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
    
    // ========================================
    // 7. RESPOSTA FINAL
    // ========================================
    
    sendJson([
        'status' => 'success',
        'data' => [
            'associado' => [
                'id' => $associadoId,
                'nome' => $associado['nome'],
                'cpf' => $associado['cpf']
            ],
            'pendencias' => $pendencias,
            'total_debito' => round($valorTotalDebito, 2),
            'meses_atraso' => $mesesAtraso,
            'valor_mensal' => round($valorMensalTotal, 2),
            'tipo_associado' => $dadosFinanceiros['tipoAssociado'] ?? 'Contribuinte',
            'vinculo_servidor' => $dadosFinanceiros['vinculoServidor'] ?? null,
            'local_debito' => $dadosFinanceiros['localDebito'] ?? null,
            'situacao_financeira' => $mesesAtraso > 0 ? 'Inadimplente' : 'Adimplente',
            'ultimo_pagamento' => $ultimoPagamento ? [
                'mes_referencia' => $ultimoPagamento['mes_referencia'],
                'mes_formatado' => date('m/Y', strtotime($ultimoPagamento['mes_referencia'])),
                'valor' => (float)$ultimoPagamento['valor_pago'],
                'data' => $ultimoPagamento['data_pagamento'],
                'tempo_relativo' => $tempoRelativo
            ] : null,
            'servicos_contratados' => $servicosContratados,
            'data_filiacao' => $dataFiliacao->format('d/m/Y'),
            'mes_atual' => date('m/Y')
        ]
    ]);

} catch (Exception $e) {
    sendJson([
        'status' => 'error',
        'message' => 'Erro ao processar',
        'debug' => $e->getMessage()
    ], 500);
}