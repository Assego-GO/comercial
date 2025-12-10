<?php
/**
 * API: Buscar Pendências Financeiras DETALHADAS do Associado
 * VERSÃO ATUALIZADA - Mostra pendentes E quitadas
 * ✅ AGORA MOSTRA DÍVIDAS JÁ QUITADAS COM VISUAL DIFERENTE
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
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../classes/Database.php';
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // ========================================
    // 1. BUSCAR DADOS DO ASSOCIADO
    // ========================================
    
    $sql = "SELECT 
                a.id,
                a.nome,
                a.cpf,
                a.situacao
            FROM Associados a 
            WHERE a.id = :id
            LIMIT 1";
    
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
    // 3. BUSCAR DATA DE FILIAÇÃO
    // ========================================
    
    $sql = "SELECT dataFiliacao FROM Contrato WHERE associado_id = :id LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $associadoId, PDO::PARAM_INT);
    $stmt->execute();
    $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrato || !$contrato['dataFiliacao']) {
        sendJson(['status' => 'error', 'message' => 'Data de filiação não encontrada'], 400);
    }
    
    // ========================================
    // 4. CALCULAR VALOR MENSAL
    // ========================================
    
    $sql = "SELECT COALESCE(SUM(valor_aplicado), 181.46) as valor_mensal
            FROM Servicos_Associado
            WHERE associado_id = :id AND ativo = 1";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $associadoId, PDO::PARAM_INT);
    $stmt->execute();
    $servicos = $stmt->fetch(PDO::FETCH_ASSOC);
    $valorMensal = (float)($servicos['valor_mensal'] ?? 181.46);
    
    // ========================================
    // 5. BUSCAR TODOS OS PAGAMENTOS (CONFIRMADOS E PENDENTES)
    // ========================================
    
    $sql = "SELECT 
                p.id,
                p.mes_referencia,
                p.valor_pago as valor,
                p.data_vencimento,
                p.data_pagamento,
                p.status_pagamento,
                p.forma_pagamento,
                p.origem_importacao,
                p.observacoes,
                p.data_registro,
                p.funcionario_registro
            FROM Pagamentos_Associado p
            WHERE p.associado_id = :id
            ORDER BY p.mes_referencia ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $associadoId, PDO::PARAM_INT);
    $stmt->execute();
    
    $pagamentosExistentes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $mesKey = date('Y-m', strtotime($row['mes_referencia']));
        $pagamentosExistentes[$mesKey] = $row;
    }
    
    // ========================================
    // 6. GERAR LISTA COMPLETA DE MESES (Filiação até Hoje)
    // ========================================
    
    $dataFiliacao = new DateTime($contrato['dataFiliacao']);
    $dataFiliacao->modify('first day of this month');
    
    $mesAtual = new DateTime('first day of this month');
    $mesIterador = clone $dataFiliacao;
    
    $todasDividas = [];
    $totalDebito = 0;
    $totalQuitado = 0;
    $idCounter = 1;
    
    while ($mesIterador < $mesAtual) {
        $mesKey = $mesIterador->format('Y-m');
        $mesRef = $mesIterador->format('Y-m-01');
        $mesFormatado = $mesIterador->format('m/Y');
        
        // Verificar se existe pagamento para este mês
        $pagamento = $pagamentosExistentes[$mesKey] ?? null;
        
        if ($pagamento) {
            // ✅ DÍVIDA QUITADA
            $isQuitado = ($pagamento['status_pagamento'] === 'CONFIRMADO');
            $isPendente = ($pagamento['status_pagamento'] === 'PENDENTE');
            
            if ($isQuitado) {
                $totalQuitado += (float)$pagamento['valor'];
            } else {
                $totalDebito += (float)$pagamento['valor'];
            }
            
            $todasDividas[] = [
                'id' => $idCounter++,
                'id_pagamento' => (int)$pagamento['id'],
                'tipo' => 'Contribuição social',
                'mes' => $mesFormatado,
                'mes_referencia' => $mesRef,
                'valor' => round((float)$pagamento['valor'], 2),
                'data_vencimento' => $pagamento['data_vencimento'],
                'status' => $isQuitado ? 'quitado' : 'pendente',
                'status_texto' => $isQuitado ? 'QUITADO' : 'Pendente',
                'origem' => $pagamento['origem_importacao'],
                'is_historica' => ($pagamento['origem_importacao'] === 'DIVIDA_HISTORICA'),
                
                // ✅ NOVOS CAMPOS PARA QUITADAS
                'ja_quitado' => $isQuitado,
                'data_quitacao' => $pagamento['data_pagamento'],
                'forma_pagamento_quitacao' => $pagamento['forma_pagamento'],
                'funcionario_quitacao' => $pagamento['funcionario_registro'],
                'observacoes' => $pagamento['observacoes'],
                'data_registro' => $pagamento['data_registro']
            ];
            
        } else {
            // ⚠️ DÍVIDA PENDENTE (SEM PAGAMENTO)
            $totalDebito += $valorMensal;
            
            $todasDividas[] = [
                'id' => $idCounter++,
                'id_pagamento' => null,
                'tipo' => 'Contribuição social',
                'mes' => $mesFormatado,
                'mes_referencia' => $mesRef,
                'valor' => round($valorMensal, 2),
                'data_vencimento' => $mesIterador->format('Y-m-10'),
                'status' => 'pendente',
                'status_texto' => 'sem retorno(assego)',
                'origem' => null,
                'is_historica' => false,
                
                // ✅ CAMPOS PARA PENDENTES
                'ja_quitado' => false,
                'data_quitacao' => null,
                'forma_pagamento_quitacao' => null,
                'funcionario_quitacao' => null,
                'observacoes' => null,
                'data_registro' => null
            ];
        }
        
        $mesIterador->modify('+1 month');
    }
    
    // ========================================
    // 7. BUSCAR ÚLTIMO PAGAMENTO CONFIRMADO
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
    
    // ========================================
    // 8. CALCULAR ESTATÍSTICAS
    // ========================================
    
    // Separar pendentes e quitadas
    $dividasPendentes = array_filter($todasDividas, function($d) {
        return !$d['ja_quitado'];
    });
    
    $dividasQuitadas = array_filter($todasDividas, function($d) {
        return $d['ja_quitado'];
    });
    
    $totalPendencias = count($dividasPendentes);
    $totalQuitadas = count($dividasQuitadas);
    
    $mesesAtraso = $totalPendencias;
    
    // Tempo relativo do último pagamento
    $tempoRelativo = null;
    if ($ultimoPagamento) {
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
    
    // Separar históricas
    $dividasHistoricas = array_filter($todasDividas, function($d) {
        return $d['is_historica'];
    });
    
    // ========================================
    // 9. BUSCAR NOME DO FUNCIONÁRIO QUE QUITOU (se houver)
    // ========================================
    
    $funcionariosCache = [];
    foreach ($dividasQuitadas as &$divida) {
        if ($divida['funcionario_quitacao']) {
            $funcId = $divida['funcionario_quitacao'];
            
            if (!isset($funcionariosCache[$funcId])) {
                $sql = "SELECT nome FROM Funcionarios WHERE id = :id LIMIT 1";
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':id', $funcId, PDO::PARAM_INT);
                $stmt->execute();
                $func = $stmt->fetch(PDO::FETCH_ASSOC);
                $funcionariosCache[$funcId] = $func['nome'] ?? 'Sistema';
            }
            
            $divida['funcionario_quitacao_nome'] = $funcionariosCache[$funcId];
        }
    }
    unset($divida);
    
    // ========================================
    // 10. RESPOSTA FINAL
    // ========================================
    
    sendJson([
        'status' => 'success',
        'data' => [
            'associado' => [
                'id' => $associadoId,
                'nome' => $associado['nome'],
                'cpf' => $associado['cpf']
            ],
            
            // ✅ TODAS AS DÍVIDAS (PENDENTES + QUITADAS)
            'pendencias' => $todasDividas,
            'pendencias_ativas' => array_values($dividasPendentes),
            'pendencias_quitadas' => array_values($dividasQuitadas),
            'pendencias_historicas' => array_values($dividasHistoricas),
            
            // Totais
            'total_debito' => round($totalDebito, 2),
            'total_quitado' => round($totalQuitado, 2),
            'total_geral' => round($totalDebito + $totalQuitado, 2),
            
            'meses_atraso' => $mesesAtraso,
            'meses_quitados' => $totalQuitadas,
            'meses_pendentes' => $totalPendencias,
            
            'valor_mensal' => round($valorMensal, 2),
            'tipo_associado' => $dadosFinanceiros['tipoAssociado'] ?? 'Contribuinte',
            'vinculo_servidor' => $dadosFinanceiros['vinculoServidor'] ?? null,
            'local_debito' => $dadosFinanceiros['localDebito'] ?? null,
            'situacao_financeira' => $totalPendencias > 0 ? 'Inadimplente' : 'Adimplente',
            
            'ultimo_pagamento' => $ultimoPagamento ? [
                'mes_referencia' => $ultimoPagamento['mes_referencia'],
                'mes_formatado' => date('m/Y', strtotime($ultimoPagamento['mes_referencia'])),
                'valor' => (float)$ultimoPagamento['valor_pago'],
                'data' => $ultimoPagamento['data_pagamento'],
                'tempo_relativo' => $tempoRelativo
            ] : null,
            
            'mes_atual' => date('m/Y'),
            'fonte_dados' => 'Pagamentos_Associado_COMPLETO',
            
            // ✅ NOVA ESTATÍSTICA
            'percentual_quitado' => $totalQuitadas > 0 ? round(($totalQuitadas / ($totalQuitadas + $totalPendencias)) * 100, 1) : 0
        ]
    ]);

} catch (Exception $e) {
    error_log("Erro em buscar_pendencias_detalhadas: " . $e->getMessage());
    sendJson([
        'status' => 'error',
        'message' => 'Erro ao processar',
        'debug' => $e->getMessage()
    ], 500);
}