<?php
/**
 * ========================================================================
 * API PARA IMPORTAÇÃO DE DÍVIDAS HISTÓRICAS
 * ========================================================================
 * Sistema ASSEGO - Gestão Financeira
 * Versão: 2.0 (Com tipos de dívida corrigidos)
 * 
 * Funcionalidades:
 * - Importa dívidas históricas (2014-2025) 
 * - Identifica tipo: SOCIAL, JURIDICO, PECULIO, OUTROS
 * - Previne duplicatas
 * - Registra histórico completo
 * - Estatísticas detalhadas
 * 
 * Arquivo: api/financeiro/importar_dividas_historicas_api.php
 * ========================================================================
 */

// =============================================================================
// CONFIGURAÇÕES INICIAIS
// =============================================================================

// Iniciar sessão
session_start();

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Não mostrar erros na tela (JSON puro)
ini_set('log_errors', 1);      // Salvar em log

// Aumentar limites para arquivos grandes
ini_set('max_execution_time', 600);  // 10 minutos
ini_set('memory_limit', '512M');
set_time_limit(600);

// =============================================================================
// FUNÇÕES AUXILIARES
// =============================================================================

/**
 * Envia resposta JSON limpa
 */
function sendJsonResponse($data) {
    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Registra log com timestamp
 */
function logMsg($mensagem, $tipo = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp][$tipo] $mensagem");
}

// =============================================================================
// HANDLERS DE ERRO
// =============================================================================

// Handler de erros PHP
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logMsg("PHP Error [$errno]: $errstr in $errfile:$errline", 'ERROR');
    return true;
});

// Handler de exceções não capturadas
set_exception_handler(function($exception) {
    logMsg("Uncaught Exception: " . $exception->getMessage(), 'FATAL');
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $exception->getMessage()
    ]);
});

// Handler de shutdown (erros fatais)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        logMsg("Fatal Error: " . print_r($error, true), 'FATAL');
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro fatal do servidor. Verifique os logs.'
        ]);
    }
});

// =============================================================================
// BLOCO PRINCIPAL
// =============================================================================

try {
    logMsg("=== INÍCIO DA REQUISIÇÃO ===", 'INFO');
    logMsg("Método: " . ($_SERVER['REQUEST_METHOD'] ?? 'DESCONHECIDO'), 'INFO');
    
    // =========================================================================
    // 1. VERIFICAR AUTENTICAÇÃO
    // =========================================================================
    
    $funcionario_id = null;
    
    if (isset($_SESSION['funcionario_id'])) {
        $funcionario_id = (int)$_SESSION['funcionario_id'];
    } elseif (isset($_SESSION['user_id'])) {
        $funcionario_id = (int)$_SESSION['user_id'];
    }
    
    if (!$funcionario_id) {
        logMsg("Tentativa de acesso sem autenticação", 'WARNING');
        sendJsonResponse([
            'success' => false,
            'message' => 'Sessão inválida. Faça login novamente.',
            'redirect' => '../index.php'
        ]);
    }
    
    logMsg("Funcionário autenticado: ID $funcionario_id", 'INFO');
    
    // =========================================================================
    // 2. CARREGAR CONFIGURAÇÕES DO BANCO
    // =========================================================================
    
    $configPath = __DIR__ . '/../../config/database.php';
    
    if (!file_exists($configPath)) {
        $configPath = __DIR__ . '/../../config/config.php';
    }
    
    if (!file_exists($configPath)) {
        throw new Exception("Arquivo de configuração não encontrado");
    }
    
    require_once $configPath;
    
    logMsg("Configurações carregadas de: $configPath", 'INFO');
    
    // =========================================================================
    // 3. CONECTAR AO BANCO DE DADOS
    // =========================================================================
    
    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME_CADASTRO
    );
    
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão ao banco: " . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
    
    logMsg("✅ Conectado ao banco: " . DB_NAME_CADASTRO, 'SUCCESS');
    
    // =========================================================================
    // 4. ROTEAMENTO DE AÇÕES
    // =========================================================================
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    logMsg("Ação solicitada: '$action'", 'INFO');
    
    switch ($action) {
        case 'processar_txt':
            processar_txt($conn, $funcionario_id);
            break;
            
        case 'listar_historico':
            listar_historico($conn);
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Ação inválida ou não especificada'
            ]);
    }
    
} catch (Exception $e) {
    logMsg("ERRO CRÍTICO: " . $e->getMessage(), 'FATAL');
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// =============================================================================
// FUNÇÕES PRINCIPAIS
// =============================================================================

/**
 * Processa arquivo TXT e importa dívidas históricas
 */
function processar_txt($conn, $funcionario_id) {
    logMsg("📦 Iniciando processamento de dívidas históricas", 'INFO');
    
    $associados = [];
    
    // Receber dados parseados do JavaScript
    if (isset($_POST['associados']) && !empty($_POST['associados'])) {
        logMsg("Recebendo dados parseados via POST", 'INFO');
        
        $dadosJson = $_POST['associados'];
        $associados = json_decode($dadosJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMsg("Erro ao decodificar JSON: " . json_last_error_msg(), 'ERROR');
            sendJsonResponse([
                'success' => false,
                'message' => 'Erro ao decodificar dados: ' . json_last_error_msg()
            ]);
        }
        
        logMsg("✅ Dados recebidos: " . count($associados) . " associados", 'SUCCESS');
    } else {
        logMsg("Nenhum dado recebido via POST", 'ERROR');
        sendJsonResponse([
            'success' => false,
            'message' => 'Nenhum dado enviado para processar'
        ]);
    }
    
    if (empty($associados)) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Nenhum dado válido encontrado para importar'
        ]);
    }
    
    // =========================================================================
    // ESTATÍSTICAS
    // =========================================================================
    
    $stats = [
        'total_associados' => count($associados),
        'total_dividas' => 0,
        'dividas_inseridas' => 0,
        'dividas_duplicadas' => 0,
        'associados_nao_encontrados' => 0,
        'erros' => 0,
        'valor_total' => 0,
        'detalhes_erros' => [],
        'por_tipo' => [
            'SOCIAL' => 0,
            'JURIDICO' => 0,
            'PECULIO' => 0,
            'OUTROS' => 0
        ]
    ];
    
    logMsg("Total de associados a processar: " . $stats['total_associados'], 'INFO');
    
    // =========================================================================
    // PROCESSAR IMPORTAÇÃO (COM TRANSAÇÃO)
    // =========================================================================
    
    $conn->begin_transaction();
    
    try {
        logMsg("🔄 Iniciando transação no banco de dados", 'INFO');
        
        // Processar cada associado
        foreach ($associados as $index => $assoc) {
            $numeroAssociado = $index + 1;
            $stats['valor_total'] += $assoc['dividaTotal'];
            
            logMsg("────────────────────────────────────────", 'INFO');
            logMsg("Processando associado $numeroAssociado/{$stats['total_associados']}", 'INFO');
            logMsg("Nome: {$assoc['nome']}", 'INFO');
            logMsg("CPF: {$assoc['cpf']}", 'INFO');
            logMsg("Dívidas: " . count($assoc['dividas']), 'INFO');
            
            // Buscar associado pelo CPF
            $cpfLimpo = preg_replace('/[^0-9]/', '', $assoc['cpf']);
            
            $sql = "SELECT id, nome FROM Associados 
                    WHERE LPAD(REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', ''), 11, '0') = LPAD(?, 11, '0') 
                    LIMIT 1";
            
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                logMsg("❌ Erro ao preparar busca de CPF: " . $conn->error, 'ERROR');
                $stats['erros']++;
                continue;
            }
            
            $stmt->bind_param('s', $cpfLimpo);
            $stmt->execute();
            $result = $stmt->get_result();
            $associadoDB = $result->fetch_assoc();
            $stmt->close();
            
            // Verificar se encontrou o associado
            if (!$associadoDB) {
                $stats['associados_nao_encontrados']++;
                $stats['erros']++;
                $stats['detalhes_erros'][] = [
                    'cpf' => $assoc['cpf'],
                    'nome' => $assoc['nome'],
                    'erro' => 'CPF não encontrado no banco de dados'
                ];
                logMsg("⚠️ CPF NÃO ENCONTRADO: {$assoc['cpf']}", 'WARNING');
                continue;
            }
            
            $associado_id = $associadoDB['id'];
            logMsg("✅ Associado encontrado no banco: ID {$associado_id} - {$associadoDB['nome']}", 'SUCCESS');
            
            // =====================================================================
            // REGISTRAR CADA DÍVIDA DO ASSOCIADO
            // =====================================================================
            
            foreach ($assoc['dividas'] as $indexDivida => $divida) {
                $stats['total_dividas']++;
                
                logMsg("  Dívida " . ($indexDivida + 1) . "/" . count($assoc['dividas']) . 
                       ": {$divida['tipo']} - {$divida['mesReferencia']} - R$ {$divida['valor']}", 'INFO');
                
                $resultado = registrarDivida(
                    $conn,
                    $associado_id,
                    $divida['mesReferencia'],
                    $divida['valor'],
                    $divida['tipo'],
                    $funcionario_id,
                    $divida['motivo'] ?? ''
                );
                
                // Contabilizar resultado
                if ($resultado === 'inserido') {
                    $stats['dividas_inseridas']++;
                    $stats['por_tipo'][$divida['tipo']]++;
                    logMsg("    ✓ INSERIDO", 'SUCCESS');
                } elseif ($resultado === 'duplicado') {
                    $stats['dividas_duplicadas']++;
                    logMsg("    ⊘ DUPLICADO (ignorado)", 'WARNING');
                } else {
                    $stats['erros']++;
                    logMsg("    ✗ ERRO ao inserir", 'ERROR');
                }
            }
        }
        
        // =====================================================================
        // REGISTRAR NO HISTÓRICO
        // =====================================================================
        
        logMsg("📝 Registrando no histórico de importações", 'INFO');
        
        $observacoes = sprintf(
            "IMPORTAÇÃO HISTÓRICA | %d associados | %d dívidas (%d inseridas, %d duplicadas, %d erros) | SOCIAL:%d JURIDICO:%d PECULIO:%d OUTROS:%d | Valor Total: R$ %.2f",
            $stats['total_associados'],
            $stats['total_dividas'],
            $stats['dividas_inseridas'],
            $stats['dividas_duplicadas'],
            $stats['erros'],
            $stats['por_tipo']['SOCIAL'],
            $stats['por_tipo']['JURIDICO'],
            $stats['por_tipo']['PECULIO'],
            $stats['por_tipo']['OUTROS'],
            $stats['valor_total']
        );
        
        $sqlHist = "INSERT INTO Historico_Importacoes_ASAAS (
                        funcionario_id, 
                        total_registros, 
                        atualizados, 
                        erros, 
                        observacoes,
                        ip_origem
                    ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmtHist = $conn->prepare($sqlHist);
        if ($stmtHist) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
            $stmtHist->bind_param('iiiiss',
                $funcionario_id,
                $stats['total_dividas'],
                $stats['dividas_inseridas'],
                $stats['erros'],
                $observacoes,
                $ip
            );
            $stmtHist->execute();
            $stmtHist->close();
            logMsg("✅ Histórico registrado", 'SUCCESS');
        }
        
        // =====================================================================
        // COMMIT DA TRANSAÇÃO
        // =====================================================================
        
        $conn->commit();
        logMsg("✅ TRANSAÇÃO CONFIRMADA (COMMIT)", 'SUCCESS');
        
        // =====================================================================
        // RELATÓRIO FINAL
        // =====================================================================
        
        logMsg("════════════════════════════════════════", 'INFO');
        logMsg("✅ IMPORTAÇÃO CONCLUÍDA COM SUCESSO!", 'SUCCESS');
        logMsg("════════════════════════════════════════", 'INFO');
        logMsg("📊 ESTATÍSTICAS FINAIS:", 'INFO');
        logMsg("   Total de Associados: {$stats['total_associados']}", 'INFO');
        logMsg("   Total de Dívidas: {$stats['total_dividas']}", 'INFO');
        logMsg("   ✓ Inseridas: {$stats['dividas_inseridas']}", 'SUCCESS');
        logMsg("   ⊘ Duplicadas: {$stats['dividas_duplicadas']}", 'WARNING');
        logMsg("   ✗ Erros: {$stats['erros']}", 'ERROR');
        logMsg("   💰 Valor Total: R$ " . number_format($stats['valor_total'], 2, ',', '.'), 'INFO');
        logMsg("", 'INFO');
        logMsg("📋 POR TIPO DE DÍVIDA:", 'INFO');
        logMsg("   🤝 SOCIAL: {$stats['por_tipo']['SOCIAL']}", 'INFO');
        logMsg("   ⚖️  JURIDICO: {$stats['por_tipo']['JURIDICO']}", 'INFO');
        logMsg("   🐷 PECULIO: {$stats['por_tipo']['PECULIO']}", 'INFO');
        logMsg("   📦 OUTROS: {$stats['por_tipo']['OUTROS']}", 'INFO');
        logMsg("════════════════════════════════════════", 'INFO');
        
        // Enviar resposta de sucesso
        sendJsonResponse([
            'success' => true,
            'message' => 'Importação concluída com sucesso',
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        $conn->rollback();
        logMsg("❌ ERRO NA TRANSAÇÃO - ROLLBACK EXECUTADO", 'ERROR');
        logMsg("Detalhes: " . $e->getMessage(), 'ERROR');
        throw $e;
    }
}

/**
 * Lista histórico de importações
 */
function listar_historico($conn) {
    logMsg("📜 Buscando histórico de importações", 'INFO');
    
    $sql = "SELECT 
                h.id,
                h.data_importacao,
                h.total_registros,
                h.atualizados,
                h.erros,
                h.observacoes,
                f.nome as funcionario_nome
            FROM Historico_Importacoes_ASAAS h
            LEFT JOIN Funcionarios f ON h.funcionario_id = f.id
            WHERE h.observacoes LIKE '%HISTÓRICA%' OR h.observacoes LIKE '%histórica%'
            ORDER BY h.data_importacao DESC
            LIMIT 50";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        logMsg("Erro ao buscar histórico: " . $conn->error, 'ERROR');
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao buscar histórico: ' . $conn->error
        ]);
    }
    
    $historico = [];
    while ($row = $result->fetch_assoc()) {
        $historico[] = $row;
    }
    
    logMsg("✅ Histórico recuperado: " . count($historico) . " registros", 'SUCCESS');
    
    sendJsonResponse([
        'success' => true,
        'historico' => $historico
    ]);
}

/**
 * Registra uma dívida no banco de dados
 * 
 * @param mysqli $conn Conexão com o banco
 * @param int $associado_id ID do associado
 * @param string $mes_referencia Mês de referência (YYYY-MM-DD)
 * @param float $valor Valor da dívida
 * @param string $tipo_divida Tipo: SOCIAL, JURIDICO, PECULIO, OUTROS
 * @param int $funcionario_id ID do funcionário que está importando
 * @param string $motivo Motivo/descrição da dívida
 * @return string 'inserido', 'duplicado' ou false
 */
function registrarDivida($conn, $associado_id, $mes_referencia, $valor, $tipo_divida, $funcionario_id, $motivo = '') {
    
    // =========================================================================
    // 1. VALIDAR TIPO DE DÍVIDA
    // =========================================================================
    
    $tipos_validos = ['SOCIAL', 'JURIDICO', 'PECULIO', 'OUTROS'];
    if (!in_array($tipo_divida, $tipos_validos)) {
        logMsg("⚠️ Tipo inválido '$tipo_divida' convertido para OUTROS", 'WARNING');
        $tipo_divida = 'OUTROS';
    }
    
    // =========================================================================
    // 2. VERIFICAR DUPLICATA (Associado + Mês + Tipo)
    // =========================================================================
    
    $sqlCheck = "SELECT id FROM Pagamentos_Associado 
                 WHERE associado_id = ? 
                 AND mes_referencia = ?
                 AND tipo_divida = ?";
    
    $stmt = $conn->prepare($sqlCheck);
    if (!$stmt) {
        logMsg("Erro ao preparar CHECK: " . $conn->error, 'ERROR');
        return false;
    }
    
    $stmt->bind_param('iss', $associado_id, $mes_referencia, $tipo_divida);
    $stmt->execute();
    $result = $stmt->get_result();
    $existe = $result->fetch_assoc();
    $stmt->close();
    
    if ($existe) {
        return 'duplicado';
    }
    
    // =========================================================================
    // 3. PREPARAR DADOS
    // =========================================================================
    
    // Calcular data de vencimento (último dia do mês)
    $timestamp = strtotime($mes_referencia);
    $ultimo_dia = date('t', $timestamp); // Último dia do mês
    $ano = date('Y', $timestamp);
    $mes = date('m', $timestamp);
    $data_vencimento = "$ano-$mes-$ultimo_dia";
    
    // Para dívidas históricas PENDENTES, data_pagamento = data_vencimento
    // (pois o campo é NOT NULL no banco)
    $data_pagamento = $data_vencimento;
    
    // Preparar observação detalhada
    $observacao = sprintf(
        "MIGRAÇÃO HISTÓRICA | Tipo: %s | Período: %s | Valor: R$ %.2f | Motivo: %s | Importado: %s",
        $tipo_divida,
        date('m/Y', $timestamp),
        $valor,
        $motivo ?: 'Débito em aberto',
        date('d/m/Y H:i:s')
    );
    
    // =========================================================================
    // 4. INSERIR NO BANCO
    // =========================================================================
    
    $sqlInsert = "INSERT INTO Pagamentos_Associado (
                      associado_id, 
                      mes_referencia, 
                      valor_pago, 
                      data_pagamento,
                      data_vencimento,
                      forma_pagamento,
                      tipo_divida,
                      status_pagamento, 
                      origem_importacao,
                      observacoes,
                      funcionario_registro,
                      data_registro
                  ) VALUES (?, ?, ?, ?, ?, 'IMPORTACAO_HISTORICA', ?, 'PENDENTE', 'DIVIDA_HISTORICA', ?, ?, NOW())";
    
    $stmt = $conn->prepare($sqlInsert);
    if (!$stmt) {
        logMsg("Erro ao preparar INSERT: " . $conn->error, 'ERROR');
        return false;
    }
    
    // ✅ BIND_PARAM CORRETO:
    // i = integer
    // s = string  
    // d = double (float)
    $stmt->bind_param('isdssssi',
        $associado_id,      // i = int
        $mes_referencia,    // s = string (date YYYY-MM-DD)
        $valor,             // d = double/float
        $data_pagamento,    // s = string (date YYYY-MM-DD) ✅
        $data_vencimento,   // s = string (date YYYY-MM-DD) ✅
        $tipo_divida,       // s = string (ENUM)
        $observacao,        // s = string (TEXT)
        $funcionario_id     // i = int
    );
    
    $resultado = $stmt->execute();
    
    if (!$resultado) {
        logMsg("❌ Erro ao executar INSERT: " . $stmt->error, 'ERROR');
        logMsg("   Associado: $associado_id", 'ERROR');
        logMsg("   Mês: $mes_referencia", 'ERROR');
        logMsg("   Tipo: $tipo_divida", 'ERROR');
        logMsg("   Valor: $valor", 'ERROR');
        logMsg("   Vencimento: $data_vencimento", 'ERROR');
        return false;
    }
    
    $stmt->close();
    
    return 'inserido';
}

// =============================================================================
// FIM DO ARQUIVO
// =============================================================================
?>