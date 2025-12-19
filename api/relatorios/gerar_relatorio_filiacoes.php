<?php
/**
 * API para gerar relatório de Filiações
 * Resumo de filiações por Diretor/Representante com tipos e comissões
 * api/relatorios/gerar_relatorio_filiacoes.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Permissoes.php';
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }
    
    if (!Permissoes::tem('COMERCIAL_RELATORIOS')) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão']);
        exit;
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Parâmetros
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
    
    error_log("=== RELATÓRIO FILIAÇÕES ===");
    error_log("Data: $dataInicio até $dataFim");
    
    // Valores de mensalidade e comissão por tipo
    $valoresMensalidade = [
        'Jurídico+Social' => 226.83,
        'Social' => 181.46,
        'Jurídico+50% Social' => 136.10,
        '50% Social' => 90.73,
        'Agregado/Al Sd' => 90.73
    ];
    
    $valoresComissao = [
        'Jurídico+Social' => 113.42,
        'Social' => 90.73,
        'Jurídico+50% Social' => 68.05,
        '50% Social' => 45.37,
        'Agregado/Al Sd' => 45.37
    ];
    
    // ============================================
    // BUSCAR INDICADORES COM SUAS FILIAÇÕES
    // ============================================
    
    // Query para buscar indicadores e suas filiações no período
    // Nota: Considera todas as indicações no período, independente da situação atual
    $sql = "
        SELECT 
            i.id as indicador_id,
            COALESCE(i.nome_completo, hi.indicador_nome) as indicador_nome,
            i.patente as indicador_patente,
            i.corporacao as indicador_corporacao,
            i.pix_tipo,
            i.pix_chave,
            
            -- Contagem por tipo de serviço
            SUM(CASE 
                WHEN sa.tipo_associado = 'Contribuinte' 
                     AND NOT EXISTS(SELECT 1 FROM Servicos_Associado sa2 WHERE sa2.associado_id = a.id AND sa2.servico_id = 2 AND sa2.ativo = 1)
                THEN 1 ELSE 0 END) as qtd_social,
            
            SUM(CASE 
                WHEN sa.tipo_associado = 'Contribuinte'
                     AND EXISTS(SELECT 1 FROM Servicos_Associado sa2 WHERE sa2.associado_id = a.id AND sa2.servico_id = 2 AND sa2.ativo = 1)
                THEN 1 ELSE 0 END) as qtd_juridico_social,
            
            SUM(CASE 
                WHEN sa.tipo_associado IN ('Aluno', 'Soldado 1ª Classe', 'Soldado 2ª Classe')
                THEN 1 ELSE 0 END) as qtd_aluno_sd,
            
            SUM(CASE 
                WHEN sa.tipo_associado IN ('Agregado', 'Agregado (Sem serviço jurídico)')
                THEN 1 ELSE 0 END) as qtd_agregado,
            
            SUM(CASE 
                WHEN sa.tipo_associado = 'Remido 50%'
                THEN 1 ELSE 0 END) as qtd_remido_50,
            
            COUNT(DISTINCT hi.associado_id) as qtd_total
            
        FROM Historico_Indicacoes hi
        INNER JOIN Associados a ON hi.associado_id = a.id
        LEFT JOIN Indicadores i ON hi.indicador_id = i.id
        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1 AND sa.servico_id = 1
        WHERE DATE(hi.data_indicacao) BETWEEN :data_inicio AND :data_fim
        GROUP BY i.id, i.nome_completo, hi.indicador_nome, i.patente, i.corporacao, i.pix_tipo, i.pix_chave
        HAVING qtd_total > 0
        ORDER BY qtd_total DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':data_inicio' => $dataInicio,
        ':data_fim' => $dataFim
    ]);
    
    $indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar dados e calcular comissões
    $resultado = [];
    $totais = [
        'social' => 0,
        'juridico_social' => 0,
        'aluno_sd' => 0,
        'agregado' => 0,
        'total' => 0,
        'comissao' => 0
    ];
    
    foreach ($indicadores as $ind) {
        // Calcular comissão do indicador
        $comissao = 0;
        $comissao += ($ind['qtd_social'] ?? 0) * $valoresComissao['Social'];
        $comissao += ($ind['qtd_juridico_social'] ?? 0) * $valoresComissao['Jurídico+Social'];
        $comissao += ($ind['qtd_aluno_sd'] ?? 0) * $valoresComissao['Agregado/Al Sd'];
        $comissao += ($ind['qtd_agregado'] ?? 0) * $valoresComissao['Agregado/Al Sd'];
        $comissao += ($ind['qtd_remido_50'] ?? 0) * $valoresComissao['50% Social'];
        
        $resultado[] = [
            'indicador_id' => $ind['indicador_id'],
            'indicador_nome' => $ind['indicador_nome'] ?? 'Não identificado',
            'indicador_patente' => $ind['indicador_patente'] ?? '',
            'indicador_corporacao' => $ind['indicador_corporacao'] ?? '',
            'pix_tipo' => $ind['pix_tipo'] ?? '',
            'pix_chave' => $ind['pix_chave'] ?? '',
            'qtd_social' => (int)($ind['qtd_social'] ?? 0),
            'qtd_juridico_social' => (int)($ind['qtd_juridico_social'] ?? 0),
            'qtd_aluno_sd' => (int)($ind['qtd_aluno_sd'] ?? 0),
            'qtd_agregado' => (int)($ind['qtd_agregado'] ?? 0),
            'qtd_total' => (int)($ind['qtd_total'] ?? 0),
            'comissao' => $comissao,
            'comissao_formatada' => 'R$ ' . number_format($comissao, 2, ',', '.')
        ];
        
        // Acumular totais
        $totais['social'] += (int)($ind['qtd_social'] ?? 0);
        $totais['juridico_social'] += (int)($ind['qtd_juridico_social'] ?? 0);
        $totais['aluno_sd'] += (int)($ind['qtd_aluno_sd'] ?? 0);
        $totais['agregado'] += (int)($ind['qtd_agregado'] ?? 0);
        $totais['total'] += (int)($ind['qtd_total'] ?? 0);
        $totais['comissao'] += $comissao;
    }
    
    // Formatar comissão total
    $totais['comissao_formatada'] = 'R$ ' . number_format($totais['comissao'], 2, ',', '.');
    
    // ============================================
    // RESUMO POR POSTO/GRADUAÇÃO E CORPORAÇÃO
    // ============================================
    
    // Ordem das patentes para ordenação
    $ordemPatentes = [
        'Cel' => 1,
        'TC' => 2,
        'Maj' => 3,
        'Cap' => 4,
        '1º Ten' => 5,
        '2º Ten' => 6,
        'ST' => 7,
        '1º Sgt' => 8,
        '2º Sgt' => 9,
        '3º Sgt' => 10,
        'Cb' => 11,
        'Sd 1ª Cl' => 12,
        'Sd 2ª Cl' => 13,
        'Sd' => 14,
        'Pensionista' => 15,
        'Agregado' => 16,
        'Civil' => 17,
        'Outros' => 99
    ];
    
    // Query para buscar novos associados do período com patente e corporação
    // JOIN com tabela Militar (patente, corporacao) e Contrato (dataFiliacao)
    $sqlResumo = "
        SELECT 
            COALESCE(NULLIF(TRIM(m.patente), ''), 'Outros') as patente,
            COALESCE(NULLIF(TRIM(m.corporacao), ''), 'Outros') as corporacao,
            COUNT(*) as quantidade
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE DATE(c.dataFiliacao) BETWEEN :data_inicio AND :data_fim
        GROUP BY 
            COALESCE(NULLIF(TRIM(m.patente), ''), 'Outros'),
            COALESCE(NULLIF(TRIM(m.corporacao), ''), 'Outros')
        ORDER BY patente, corporacao
    ";
    
    $stmtResumo = $db->prepare($sqlResumo);
    $stmtResumo->execute([
        ':data_inicio' => $dataInicio,
        ':data_fim' => $dataFim
    ]);
    $dadosResumo = $stmtResumo->fetchAll(PDO::FETCH_ASSOC);
    
    // Inicializar estrutura do resumo
    $resumoPorPosto = [];
    $corporacoesEncontradas = ['PM' => true, 'BM' => true]; // Sempre incluir PM e BM
    $totaisPorCorporacao = [
        'PM' => 0,
        'BM' => 0,
        'Pensionista' => 0,
        'Agregados' => 0,
        'Exército' => 0,
        'Civil' => 0,
        'Outros' => 0
    ];
    
    // Processar dados
    foreach ($dadosResumo as $dado) {
        $patente = $dado['patente'];
        $corp = $dado['corporacao'];
        $qtd = (int)$dado['quantidade'];
        
        // Normalizar corporação
        $corpNormalizada = 'Outros';
        if (stripos($corp, 'PM') !== false || $corp === 'Polícia Militar') {
            $corpNormalizada = 'PM';
        } elseif (stripos($corp, 'BM') !== false || $corp === 'Bombeiro Militar' || $corp === 'Corpo de Bombeiros') {
            $corpNormalizada = 'BM';
        } elseif (stripos($corp, 'Pensionista') !== false || stripos($patente, 'Pensionista') !== false) {
            $corpNormalizada = 'Pensionista';
        } elseif (stripos($corp, 'Agregado') !== false || stripos($patente, 'Agregado') !== false) {
            $corpNormalizada = 'Agregados';
        } elseif (stripos($corp, 'Exército') !== false || stripos($corp, 'EB') !== false) {
            $corpNormalizada = 'Exército';
        } elseif (stripos($corp, 'Civil') !== false) {
            $corpNormalizada = 'Civil';
        }
        
        // Normalizar patente para Pensionistas e Agregados
        if (stripos($patente, 'Pensionista') !== false) {
            $patente = 'Pensionista';
            $corpNormalizada = 'Pensionista';
        }
        if (stripos($patente, 'Agregado') !== false) {
            $patente = 'Agregado';
            $corpNormalizada = 'Agregados';
        }
        
        // Inicializar linha se não existir
        if (!isset($resumoPorPosto[$patente])) {
            $resumoPorPosto[$patente] = [
                'patente' => $patente,
                'PM' => 0,
                'BM' => 0,
                'Pensionista' => 0,
                'Agregados' => 0,
                'Exército' => 0,
                'Civil' => 0,
                'Outros' => 0,
                'total' => 0
            ];
        }
        
        // Adicionar quantidade
        $resumoPorPosto[$patente][$corpNormalizada] += $qtd;
        $resumoPorPosto[$patente]['total'] += $qtd;
        $totaisPorCorporacao[$corpNormalizada] += $qtd;
    }
    
    // Ordenar por patente
    uasort($resumoPorPosto, function($a, $b) use ($ordemPatentes) {
        $ordemA = $ordemPatentes[$a['patente']] ?? 99;
        $ordemB = $ordemPatentes[$b['patente']] ?? 99;
        return $ordemA - $ordemB;
    });
    
    // Calcular total geral
    $totalGeralResumo = array_sum($totaisPorCorporacao);
    
    // Preparar dados do resumo para retorno
    $resumoFiliacoes = [
        'dados' => array_values($resumoPorPosto),
        'totais' => $totaisPorCorporacao,
        'total_geral' => $totalGeralResumo,
        'colunas' => ['PM', 'BM', 'Pensionista', 'Agregados', 'Exército', 'Civil', 'Outros']
    ];

    // Tabela de valores
    $tabelaValores = [
        [
            'tipo' => 'Jurídico+Social',
            'mensalidade' => $valoresMensalidade['Jurídico+Social'],
            'mensalidade_formatada' => 'R$ ' . number_format($valoresMensalidade['Jurídico+Social'], 2, ',', '.'),
            'comissao' => $valoresComissao['Jurídico+Social'],
            'comissao_formatada' => 'R$ ' . number_format($valoresComissao['Jurídico+Social'], 2, ',', '.')
        ],
        [
            'tipo' => 'Social',
            'mensalidade' => $valoresMensalidade['Social'],
            'mensalidade_formatada' => 'R$ ' . number_format($valoresMensalidade['Social'], 2, ',', '.'),
            'comissao' => $valoresComissao['Social'],
            'comissao_formatada' => 'R$ ' . number_format($valoresComissao['Social'], 2, ',', '.')
        ],
        [
            'tipo' => 'Jurídico+50% Social',
            'mensalidade' => $valoresMensalidade['Jurídico+50% Social'],
            'mensalidade_formatada' => 'R$ ' . number_format($valoresMensalidade['Jurídico+50% Social'], 2, ',', '.'),
            'comissao' => $valoresComissao['Jurídico+50% Social'],
            'comissao_formatada' => 'R$ ' . number_format($valoresComissao['Jurídico+50% Social'], 2, ',', '.')
        ],
        [
            'tipo' => '50% Social',
            'mensalidade' => $valoresMensalidade['50% Social'],
            'mensalidade_formatada' => 'R$ ' . number_format($valoresMensalidade['50% Social'], 2, ',', '.'),
            'comissao' => $valoresComissao['50% Social'],
            'comissao_formatada' => 'R$ ' . number_format($valoresComissao['50% Social'], 2, ',', '.')
        ],
        [
            'tipo' => 'Agregado/Al Sd',
            'mensalidade' => $valoresMensalidade['Agregado/Al Sd'],
            'mensalidade_formatada' => 'R$ ' . number_format($valoresMensalidade['Agregado/Al Sd'], 2, ',', '.'),
            'comissao' => $valoresComissao['Agregado/Al Sd'],
            'comissao_formatada' => 'R$ ' . number_format($valoresComissao['Agregado/Al Sd'], 2, ',', '.')
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $resultado,
        'totais' => $totais,
        'resumo_posto_corporacao' => $resumoFiliacoes,
        'tabela_valores' => $tabelaValores,
        'periodo' => [
            'inicio' => $dataInicio,
            'fim' => $dataFim,
            'inicio_formatado' => date('d/m/Y', strtotime($dataInicio)),
            'fim_formatado' => date('d/m/Y', strtotime($dataFim))
        ],
        'total_indicadores' => count($resultado)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("ERRO RELATÓRIO FILIAÇÕES: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
