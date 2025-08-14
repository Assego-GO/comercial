<?php
/**
 * API para recalcular valores dos serviços baseado nos novos valores base
 * api/recalcular_servicos.php
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => null
];

try {
    // Carrega configurações
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';

    // Verifica autenticação (sessão já iniciada pela classe Auth)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Acesso negado. Faça login.');
    }

  
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    error_log("=== INICIANDO RECÁLCULO DOS SERVIÇOS ===");
    error_log("Usuário: " . ($_SESSION['user_name'] ?? 'N/A'));

    $db->beginTransaction();

    // 1. BUSCA VALORES BASE ATUAIS
    $stmt = $db->prepare("SELECT id, nome, valor_base FROM Servicos WHERE ativo = 1 ORDER BY id");
    $stmt->execute();
    $servicosBase = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $valoresBase = [];
    foreach ($servicosBase as $servico) {
        $valoresBase[$servico['id']] = [
            'nome' => $servico['nome'],
            'valor_base' => floatval($servico['valor_base'])
        ];
    }

    error_log("✓ Valores base encontrados: " . json_encode($valoresBase));

    // 2. BUSCA REGRAS DE CONTRIBUIÇÃO
    $stmt = $db->prepare("
        SELECT rc.tipo_associado, rc.servico_id, rc.percentual_valor 
        FROM Regras_Contribuicao rc 
        INNER JOIN Servicos s ON rc.servico_id = s.id 
        WHERE s.ativo = 1
        ORDER BY rc.tipo_associado, rc.servico_id
    ");
    $stmt->execute();
    $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $regrasMap = [];
    foreach ($regras as $regra) {
        $regrasMap[$regra['tipo_associado']][$regra['servico_id']] = floatval($regra['percentual_valor']);
    }

    error_log("✓ Regras de contribuição carregadas: " . count($regras) . " regras");

    // 3. BUSCA TODOS OS SERVIÇOS ATIVOS DOS ASSOCIADOS
    $stmt = $db->prepare("
        SELECT 
            sa.id,
            sa.associado_id,
            sa.servico_id,
            sa.tipo_associado,
            sa.valor_aplicado as valor_atual,
            sa.percentual_aplicado,
            a.nome as associado_nome,
            s.nome as servico_nome,
            s.valor_base
        FROM Servicos_Associado sa
        INNER JOIN Associados a ON sa.associado_id = a.id
        INNER JOIN Servicos s ON sa.servico_id = s.id
        WHERE sa.ativo = 1 AND s.ativo = 1
        ORDER BY sa.associado_id, sa.servico_id
    ");
    $stmt->execute();
    $servicosAssociados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("✓ Encontrados " . count($servicosAssociados) . " serviços ativos para recalcular");

    // 4. RECALCULA CADA SERVIÇO
    $totalRecalculados = 0;
    $totalAlterados = 0;
    $erros = [];
    $detalhesAlteracoes = [];

    foreach ($servicosAssociados as $servicoAssociado) {
        try {
            $servicoId = $servicoAssociado['servico_id'];
            $tipoAssociado = $servicoAssociado['tipo_associado'];
            $valorAtual = floatval($servicoAssociado['valor_atual']);

            // Busca valor base atual
            if (!isset($valoresBase[$servicoId])) {
                $erros[] = "Serviço ID {$servicoId} não encontrado nos valores base";
                continue;
            }

            $valorBase = $valoresBase[$servicoId]['valor_base'];

            // Busca percentual para este tipo de associado
            if (!isset($regrasMap[$tipoAssociado][$servicoId])) {
                // Se não tem regra específica, usa 100%
                $percentual = 100.00;
                error_log("⚠ Não encontrada regra para {$tipoAssociado} + Serviço {$servicoId}, usando 100%");
            } else {
                $percentual = $regrasMap[$tipoAssociado][$servicoId];
            }

            // Calcula novo valor
            $novoValor = ($valorBase * $percentual) / 100;

            // Verifica se mudou (diferença maior que 1 centavo)
            if (abs($valorAtual - $novoValor) > 0.01) {
                
                // Registra histórico da alteração (se a tabela existir)
                try {
                    $stmt = $db->prepare("
                        INSERT INTO Historico_Servicos_Associado (
                            servico_associado_id, tipo_alteracao, valor_anterior, valor_novo,
                            percentual_anterior, percentual_novo, motivo, funcionario_id, data_alteracao
                        ) VALUES (?, 'RECALCULO', ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $servicoAssociado['id'],
                        $valorAtual,
                        $novoValor,
                        $servicoAssociado['percentual_aplicado'],
                        $percentual,
                        "Recálculo automático - Valor base alterado de {$servicoAssociado['valor_base']} para {$valorBase}",
                        $_SESSION['funcionario_id'] ?? null
                    ]);
                } catch (Exception $e) {
                    error_log("⚠ Aviso: Erro ao registrar histórico: " . $e->getMessage());
                    // Continua mesmo se não conseguir registrar histórico
                }

                // Atualiza o valor
                $stmt = $db->prepare("
                    UPDATE Servicos_Associado 
                    SET valor_aplicado = ?, percentual_aplicado = ?, observacao = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $novoValor,
                    $percentual,
                    "Recalculado automaticamente em " . date('d/m/Y H:i:s'),
                    $servicoAssociado['id']
                ]);

                $totalAlterados++;
                
                $detalhesAlteracoes[] = [
                    'associado' => $servicoAssociado['associado_nome'],
                    'servico' => $servicoAssociado['servico_nome'],
                    'tipo_associado' => $tipoAssociado,
                    'valor_anterior' => $valorAtual,
                    'valor_novo' => $novoValor,
                    'diferenca' => $novoValor - $valorAtual,
                    'percentual' => $percentual
                ];

                error_log("✓ Recalculado: {$servicoAssociado['associado_nome']} - {$servicoAssociado['servico_nome']} | R$ {$valorAtual} → R$ {$novoValor}");
            }

            $totalRecalculados++;

        } catch (Exception $e) {
            $erros[] = "Erro ao recalcular serviço ID {$servicoAssociado['id']}: " . $e->getMessage();
            error_log("✗ Erro ao recalcular serviço: " . $e->getMessage());
        }
    }

    // 5. REGISTRA AUDITORIA GERAL (se possível)
    try {
        $stmt = $db->prepare("
            INSERT INTO Auditoria (
                tabela, acao, registro_id, funcionario_id, 
                alteracoes, data_hora, ip_origem
            ) VALUES (
                'Servicos_Associado', 'RECALCULO', NULL, ?, 
                ?, NOW(), ?
            )
        ");
        
        $alteracoes = json_encode([
            'total_recalculados' => $totalRecalculados,
            'total_alterados' => $totalAlterados,
            'valores_base_usados' => $valoresBase,
            'detalhes_alteracoes' => array_slice($detalhesAlteracoes, 0, 10), // Primeiros 10
            'erros' => $erros
        ]);
        
        $stmt->execute([
            $_SESSION['funcionario_id'] ?? null,
            $alteracoes,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        error_log("⚠ Aviso: Erro ao registrar auditoria geral: " . $e->getMessage());
        // Continua mesmo se não conseguir registrar auditoria
    }

    $db->commit();

    error_log("✓ RECÁLCULO CONCLUÍDO: {$totalRecalculados} processados, {$totalAlterados} alterados");

    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => "Recálculo concluído com sucesso!",
        'data' => [
            'total_servicos_processados' => $totalRecalculados,
            'total_valores_alterados' => $totalAlterados,
            'total_sem_alteracao' => $totalRecalculados - $totalAlterados,
            'valores_base_utilizados' => $valoresBase,
            'alteracoes_detalhadas' => $detalhesAlteracoes,
            'erros' => $erros,
            'resumo_por_servico' => calcularResumoPorServico($detalhesAlteracoes),
            'economia_total' => array_sum(array_column($detalhesAlteracoes, 'diferenca')),
            'data_recalculo' => date('d/m/Y H:i:s')
        ]
    ];

    if ($totalAlterados > 0) {
        $response['message'] = "✓ Recálculo concluído! {$totalAlterados} valores foram atualizados de {$totalRecalculados} serviços processados.";
    } else {
        $response['message'] = "✓ Recálculo concluído! Todos os {$totalRecalculados} valores já estavam corretos.";
    }

    if (!empty($erros)) {
        $response['message'] .= " Atenção: " . count($erros) . " erro(s) encontrado(s).";
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    error_log("✗ ERRO no recálculo: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null,
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'user' => $_SESSION['user_name'] ?? 'N/A'
        ]
    ];
    
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;

/**
 * Função auxiliar para calcular resumo por serviço
 */
function calcularResumoPorServico($detalhesAlteracoes) {
    $resumo = [];
    
    foreach ($detalhesAlteracoes as $alteracao) {
        $servico = $alteracao['servico'];
        
        if (!isset($resumo[$servico])) {
            $resumo[$servico] = [
                'total_alterados' => 0,
                'valor_total_anterior' => 0,
                'valor_total_novo' => 0,
                'diferenca_total' => 0
            ];
        }
        
        $resumo[$servico]['total_alterados']++;
        $resumo[$servico]['valor_total_anterior'] += $alteracao['valor_anterior'];
        $resumo[$servico]['valor_total_novo'] += $alteracao['valor_novo'];
        $resumo[$servico]['diferenca_total'] += $alteracao['diferenca'];
    }
    
    return $resumo;
}
?>