<?php
/**
 * API para atualizar valores base e recalcular automaticamente todos os associados
 * api/atualizar_valores_base_e_recalcular.php
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
    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }
    
    // Carrega configurações
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';

    // Verifica autenticação
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Acesso negado. Faça login.');
    }

  
    // Lê dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Dados JSON inválidos');
    }
    
    $valorSocial = floatval($input['valor_social'] ?? 0);
    $valorJuridico = floatval($input['valor_juridico'] ?? 0);
    
    if ($valorSocial <= 0 || $valorJuridico <= 0) {
        throw new Exception('Valores devem ser maiores que zero');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    error_log("=== ATUALIZAÇÃO DE VALORES BASE E RECÁLCULO GERAL ===");
    error_log("Usuário: " . ($_SESSION['user_name'] ?? 'N/A'));
    error_log("Novo valor Social: R$ $valorSocial");
    error_log("Novo valor Jurídico: R$ $valorJuridico");
    
    $db->beginTransaction();
    
    try {
        // 1. BUSCA E ATUALIZA OS VALORES BASE DOS SERVIÇOS
        $stmt = $db->prepare("
            SELECT id, nome, valor_base
            FROM Servicos 
            WHERE ativo = 1 AND (nome LIKE '%social%' OR nome LIKE '%juridico%' OR nome LIKE '%jurídico%')
            ORDER BY id
        ");
        $stmt->execute();
        $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $servicoSocialId = null;
        $servicoJuridicoId = null;
        $valorSocialAnterior = 0;
        $valorJuridicoAnterior = 0;
        
        foreach ($servicos as $servico) {
            $nome = strtolower($servico['nome']);
            if (strpos($nome, 'social') !== false) {
                $servicoSocialId = $servico['id'];
                $valorSocialAnterior = floatval($servico['valor_base']);
            } elseif (strpos($nome, 'juridico') !== false || strpos($nome, 'jurídico') !== false) {
                $servicoJuridicoId = $servico['id'];
                $valorJuridicoAnterior = floatval($servico['valor_base']);
            }
        }
        
        if (!$servicoSocialId || !$servicoJuridicoId) {
            throw new Exception('Serviços Social ou Jurídico não encontrados');
        }
        
        error_log("✓ Serviços encontrados - Social ID: $servicoSocialId, Jurídico ID: $servicoJuridicoId");
        
        // Atualiza valores base
        $stmt = $db->prepare("UPDATE Servicos SET valor_base = ? WHERE id = ?");
        $stmt->execute([$valorSocial, $servicoSocialId]);
        
        $stmt = $db->prepare("UPDATE Servicos SET valor_base = ? WHERE id = ?");
        $stmt->execute([$valorJuridico, $servicoJuridicoId]);
        
        error_log("✓ Valores base atualizados no banco");
        
        // 2. BUSCA REGRAS DE CONTRIBUIÇÃO
        $stmt = $db->prepare("
            SELECT tipo_associado, servico_id, percentual_valor 
            FROM Regras_Contribuicao 
            WHERE servico_id IN (?, ?)
            ORDER BY tipo_associado, servico_id
        ");
        $stmt->execute([$servicoSocialId, $servicoJuridicoId]);
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
                sa.valor_aplicado,
                sa.percentual_aplicado,
                a.nome as associado_nome,
                s.nome as servico_nome
            FROM Servicos_Associado sa
            INNER JOIN Associados a ON sa.associado_id = a.id
            INNER JOIN Servicos s ON sa.servico_id = s.id
            WHERE sa.ativo = 1 AND sa.servico_id IN (?, ?)
            ORDER BY sa.associado_id, sa.servico_id
        ");
        $stmt->execute([$servicoSocialId, $servicoJuridicoId]);
        $servicosAssociados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("✓ Encontrados " . count($servicosAssociados) . " serviços ativos para recalcular");
        
        // 4. RECALCULA CADA SERVIÇO
        $totalProcessados = 0;
        $totalAlterados = 0;
        $detalhesAlteracoes = [];
        $erros = [];
        
        foreach ($servicosAssociados as $servicoAssociado) {
            try {
                $servicoId = $servicoAssociado['servico_id'];
                $tipoAssociado = $servicoAssociado['tipo_associado'];
                $valorAtual = floatval($servicoAssociado['valor_aplicado']);
                
                // Determina novo valor base
                $novoValorBase = ($servicoId == $servicoSocialId) ? $valorSocial : $valorJuridico;
                
                // Busca percentual para este tipo de associado
                if (!isset($regrasMap[$tipoAssociado][$servicoId])) {
                    // Se não tem regra específica, usa 100%
                    $percentual = 100.00;
                } else {
                    $percentual = $regrasMap[$tipoAssociado][$servicoId];
                }
                
                // Calcula novo valor
                $novoValor = ($novoValorBase * $percentual) / 100;
                
                // Verifica se mudou (diferença maior que 1 centavo)
                if (abs($valorAtual - $novoValor) > 0.01) {
                    
                    // Registra histórico da alteração
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO Historico_Servicos_Associado (
                                servico_associado_id, tipo_alteracao, valor_anterior, valor_novo,
                                percentual_anterior, percentual_novo, motivo, funcionario_id, data_alteracao
                            ) VALUES (?, 'AJUSTE_VALOR_BASE', ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $servicoAssociado['id'],
                            $valorAtual,
                            $novoValor,
                            $servicoAssociado['percentual_aplicado'],
                            $percentual,
                            "Valor base atualizado via presidência - {$servicoAssociado['servico_nome']}",
                            $_SESSION['funcionario_id'] ?? null
                        ]);
                    } catch (Exception $e) {
                        error_log("⚠ Aviso: Erro ao registrar histórico para serviço {$servicoAssociado['id']}: " . $e->getMessage());
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
                        "Valor atualizado em " . date('d/m/Y H:i:s') . " pela presidência",
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
                
                $totalProcessados++;
                
            } catch (Exception $e) {
                $erros[] = "Erro ao recalcular serviço ID {$servicoAssociado['id']}: " . $e->getMessage();
                error_log("✗ Erro ao recalcular serviço: " . $e->getMessage());
            }
        }
        
        // 5. REGISTRA AUDITORIA GERAL
        try {
            $stmt = $db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, funcionario_id, 
                    alteracoes, data_hora, ip_origem
                ) VALUES (
                    'Servicos', 'UPDATE_BASE', NULL, ?, 
                    ?, NOW(), ?
                )
            ");
            
            $alteracoes = json_encode([
                'acao' => 'ATUALIZACAO_VALORES_BASE_E_RECALCULO',
                'valores_base_anteriores' => [
                    'social' => $valorSocialAnterior,
                    'juridico' => $valorJuridicoAnterior
                ],
                'valores_base_novos' => [
                    'social' => $valorSocial,
                    'juridico' => $valorJuridico
                ],
                'resultado_recalculo' => [
                    'total_processados' => $totalProcessados,
                    'total_alterados' => $totalAlterados,
                    'total_erros' => count($erros)
                ],
                'primeiras_alteracoes' => array_slice($detalhesAlteracoes, 0, 5)
            ]);
            
            $stmt->execute([
                $_SESSION['funcionario_id'] ?? null,
                $alteracoes,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            error_log("⚠ Aviso: Erro ao registrar auditoria geral: " . $e->getMessage());
        }
        
        $db->commit();
        
        error_log("✓ PROCESSO CONCLUÍDO COM SUCESSO");
        error_log("✓ Valores base atualizados e {$totalAlterados} serviços recalculados de {$totalProcessados} processados");
        
        // Calcula estatísticas finais
        $economiaTotal = array_sum(array_column($detalhesAlteracoes, 'diferenca'));
        
        $response = [
            'status' => 'success',
            'message' => "✅ Valores base atualizados e recálculo concluído com sucesso!",
            'data' => [
                'valores_base_atualizados' => [
                    'social' => [
                        'anterior' => $valorSocialAnterior,
                        'novo' => $valorSocial,
                        'diferenca' => $valorSocial - $valorSocialAnterior
                    ],
                    'juridico' => [
                        'anterior' => $valorJuridicoAnterior,
                        'novo' => $valorJuridico,
                        'diferenca' => $valorJuridico - $valorJuridicoAnterior
                    ]
                ],
                'resultado_recalculo' => [
                    'total_servicos_processados' => $totalProcessados,
                    'total_valores_alterados' => $totalAlterados,
                    'total_sem_alteracao' => $totalProcessados - $totalAlterados,
                    'total_erros' => count($erros)
                ],
                'impacto_financeiro' => [
                    'diferenca_total' => $economiaTotal,
                    'tipo_impacto' => $economiaTotal >= 0 ? 'aumento' : 'reducao',
                    'percentual_afetados' => $totalProcessados > 0 ? ($totalAlterados / $totalProcessados) * 100 : 0
                ],
                'alteracoes_detalhadas' => $detalhesAlteracoes,
                'erros' => $erros,
                'data_processamento' => date('d/m/Y H:i:s')
            ]
        ];
        
        // Ajusta mensagem baseada nos resultados
        if ($totalAlterados > 0) {
            $response['message'] = "✅ Valores base atualizados! {$totalAlterados} valores de associados foram recalculados automaticamente.";
            
            if ($economiaTotal > 0) {
                $response['message'] .= " Aumento total de R$ " . number_format($economiaTotal, 2, ',', '.');
            } elseif ($economiaTotal < 0) {
                $response['message'] .= " Redução total de R$ " . number_format(abs($economiaTotal), 2, ',', '.');
            }
        } else {
            $response['message'] = "✅ Valores base atualizados! Todos os {$totalProcessados} valores dos associados já estavam corretos.";
        }
        
        if (!empty($erros)) {
            $response['message'] .= " Atenção: " . count($erros) . " erro(s) encontrado(s).";
        }
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("✗ ERRO GERAL na atualização de valores base: " . $e->getMessage());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null,
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'user' => $_SESSION['user_name'] ?? 'N/A',
            'valor_social' => $valorSocial ?? 'N/A',
            'valor_juridico' => $valorJuridico ?? 'N/A'
        ]
    ];
    
    http_response_code(400);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>