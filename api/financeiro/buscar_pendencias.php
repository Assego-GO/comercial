<?php
/**
 * API: Buscar Pendências Financeiras do Associado (RESUMO)
 * VERSÃO PDO - USA DADOS REAIS DA TABELA Pagamentos_Associado
 * Para exibir no modal de detalhes do associado
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
        'message' => 'Erro interno do servidor'
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

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

function sendJson($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!isset($_SESSION['funcionario_id']) && !isset($_SESSION['user_id'])) {
    sendJson(['status' => 'error', 'message' => 'Não autorizado'], 401);
}

$associadoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($associadoId <= 0) {
    sendJson(['status' => 'error', 'message' => 'ID do associado inválido'], 400);
}

try {
    // Incluir arquivos de configuração
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../classes/Database.php';
    
    // Conectar usando a classe Database (retorna PDO)
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // ========================================
    // 1. BUSCAR DADOS DO ASSOCIADO
    // ========================================
    
    $sql = "SELECT a.id, a.nome, a.cpf 
            FROM Associados a 
            WHERE a.id = :id LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $associadoId, PDO::PARAM_INT);
    $stmt->execute();
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$associado) {
        sendJson(['status' => 'error', 'message' => 'Associado não encontrado'], 404);
    }
    
    // ========================================
    // 2. BUSCAR DADOS FINANCEIROS
    // ========================================
    
    $sql = "SELECT tipoAssociado, situacaoFinanceira, vinculoServidor, localDebito 
            FROM Financeiro WHERE associado_id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $associadoId, PDO::PARAM_INT);
    $stmt->execute();
    $dadosFinanceiros = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // ========================================
    // 3. CONTAR PENDÊNCIAS E CALCULAR TOTAL
    // ========================================
    
    $sql = "SELECT 
                COUNT(*) as total_pendencias,
                SUM(valor_pago) as valor_total_debito,
                MIN(mes_referencia) as divida_mais_antiga
            FROM Pagamentos_Associado
            WHERE associado_id = :id
            AND status_pagamento = 'PENDENTE'";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $associadoId, PDO::PARAM_INT);
    $stmt->execute();
    $resumo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalPendencias = (int)$resumo['total_pendencias'];
    $valorTotalDebito = (float)$resumo['valor_total_debito'];
    $dividaMaisAntiga = $resumo['divida_mais_antiga'];
    
    // Calcular meses de atraso
    $mesesAtraso = 0;
    if ($dividaMaisAntiga) {
        $dataAntiga = new DateTime($dividaMaisAntiga);
        $hoje = new DateTime();
        $diff = $dataAntiga->diff($hoje);
        $mesesAtraso = ($diff->y * 12) + $diff->m;
        
        if ($mesesAtraso == 0 && $totalPendencias > 0) {
            $mesesAtraso = 1;
        }
    }
    
    // Calcular valor mensal médio
    $valorMensal = 181.46; // Padrão
    if ($mesesAtraso > 0 && $valorTotalDebito > 0) {
        $valorMensal = $valorTotalDebito / $mesesAtraso;
    }
    
    // ========================================
    // 4. BUSCAR ÚLTIMO PAGAMENTO CONFIRMADO
    // ========================================
    
    $sql = "SELECT mes_referencia, valor_pago, data_pagamento 
            FROM Pagamentos_Associado 
            WHERE associado_id = :id
            AND status_pagamento = 'CONFIRMADO' 
            ORDER BY mes_referencia DESC 
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $associadoId, PDO::PARAM_INT);
    $stmt->execute();
    $ultimoPagamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular tempo relativo
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
    // 5. RESPOSTA FINAL (RESUMO)
    // ========================================
    
    sendJson([
        'status' => 'success',
        'data' => [
            'associado_id' => $associadoId,
            'nome_associado' => $associado['nome'],
            'situacao_financeira' => $mesesAtraso > 0 ? 'Inadimplente' : 'Adimplente',
            'tipo_associado' => $dadosFinanceiros['tipoAssociado'] ?? 'Contribuinte',
            'vinculo_servidor' => $dadosFinanceiros['vinculoServidor'] ?? null,
            'local_debito' => $dadosFinanceiros['localDebito'] ?? null,
            'valor_mensal' => round($valorMensal, 2),
            'meses_atraso' => $mesesAtraso,
            'valor_total_debito' => round($valorTotalDebito, 2),
            'total_pendencias' => $totalPendencias,
            'divida_mais_antiga' => $dividaMaisAntiga,
            'ultimo_pagamento' => $ultimoPagamento ? [
                'mes_referencia' => $ultimoPagamento['mes_referencia'],
                'mes_formatado' => date('m/Y', strtotime($ultimoPagamento['mes_referencia'])),
                'valor' => (float)$ultimoPagamento['valor_pago'],
                'data' => $ultimoPagamento['data_pagamento'],
                'tempo_relativo' => $tempoRelativo
            ] : null,
            'fonte_dados' => 'Pagamentos_Associado'
        ]
    ]);

} catch (Exception $e) {
    error_log("Erro em buscar_pendencias: " . $e->getMessage());
    sendJson([
        'status' => 'error',
        'message' => 'Erro ao processar',
        'debug' => $e->getMessage()
    ], 500);
}