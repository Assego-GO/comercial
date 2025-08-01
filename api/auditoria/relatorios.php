<?php
/**
 * API para relatórios avançados de auditoria
 * /api/auditoria/relatorios.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auditoria.php';

try {
    // Verificar método
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        throw new Exception('Método não permitido');
    }

    // Criar instância da auditoria
    $auditoria = new Auditoria();
    
    // Determinar tipo de relatório
    $tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'geral';
    $periodo = $_GET['periodo'] ?? $_POST['periodo'] ?? 'mes';
    
    // Gerar relatório usando a classe Auditoria
    $relatorio = $auditoria->gerarRelatorio($tipo, $periodo);
    
    if (!$relatorio) {
        throw new Exception('Erro ao gerar relatório');
    }
    
    // Adicionar dados extras dependendo do tipo
    switch ($tipo) {
        case 'geral':
            $relatorio = enriquecerRelatorioGeral($relatorio);
            break;
            
        case 'por_funcionario':
            $relatorio = enriquecerRelatorioPorFuncionario($relatorio);
            break;
            
        case 'por_acao':
            $relatorio = enriquecerRelatorioPorAcao($relatorio);
            break;
            
        case 'acessos':
            $relatorio = enriquecerRelatorioAcessos($relatorio);
            break;
            
        case 'seguranca':
            $relatorio = gerarRelatorioSeguranca($periodo);
            break;
            
        case 'performance':
            $relatorio = gerarRelatorioPerformance($periodo);
            break;
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Relatório gerado com sucesso',
        'data' => $relatorio
    ]);

} catch (Exception $e) {
    error_log("Erro na API de relatórios de auditoria: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao gerar relatório: ' . $e->getMessage(),
        'data' => null
    ]);
}

/**
 * Enriquecer relatório geral com dados adicionais
 */
function enriquecerRelatorioGeral($relatorio) {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Adicionar comparação com período anterior
    $dataInicio = $relatorio['data_inicio'];
    $dataFim = $relatorio['data_fim'];
    
    // Calcular período anterior
    $diasPeriodo = (strtotime($dataFim) - strtotime($dataInicio)) / (60 * 60 * 24);
    $dataInicioAnterior = date('Y-m-d', strtotime($dataInicio . " -{$diasPeriodo} days"));
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_anterior
        FROM Auditoria
        WHERE DATE(data_hora) BETWEEN :data_inicio AND :data_fim
    ");
    
    $stmt->execute([
        ':data_inicio' => $dataInicioAnterior,
        ':data_fim' => date('Y-m-d', strtotime($dataInicio . ' -1 day'))
    ]);
    
    $totalAnterior = $stmt->fetch(PDO::FETCH_ASSOC)['total_anterior'];
    $totalAtual = $relatorio['estatisticas']['total_acoes'] ?? 0;
    
    if ($totalAnterior > 0) {
        $mudancaPercentual = (($totalAtual - $totalAnterior) / $totalAnterior) * 100;
        $relatorio['comparacao_periodo_anterior'] = [
            'total_anterior' => $totalAnterior,
            'total_atual' => $totalAtual,
            'mudanca_percentual' => round($mudancaPercentual, 2),
            'tendencia' => $mudancaPercentual > 0 ? 'crescimento' : 'declinio'
        ];
    }
    
    return $relatorio;
}

/**
 * Enriquecer relatório por funcionário
 */
function enriquecerRelatorioPorFuncionario($relatorio) {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    if (isset($relatorio['estatisticas']) && is_array($relatorio['estatisticas'])) {
        foreach ($relatorio['estatisticas'] as &$funcionario) {
            // Buscar informações adicionais do funcionário
            $stmt = $db->prepare("
                SELECT f.cargo, d.nome as departamento
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                WHERE f.id = :funcionario_id
            ");
            
            $stmt->execute([':funcionario_id' => $funcionario['id']]);
            $dadosAdicionais = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dadosAdicionais) {
                $funcionario['cargo'] = $dadosAdicionais['cargo'];
                $funcionario['departamento'] = $dadosAdicionais['departamento'];
            }
            
            // Calcular produtividade (ações por dia ativo)
            if ($funcionario['dias_ativos'] > 0) {
                $funcionario['produtividade'] = round($funcionario['total_acoes'] / $funcionario['dias_ativos'], 2);
            } else {
                $funcionario['produtividade'] = 0;
            }
        }
        
        // Ordenar por produtividade
        usort($relatorio['estatisticas'], function($a, $b) {
            return $b['produtividade'] <=> $a['produtividade'];
        });
    }
    
    return $relatorio;
}

/**
 * Enriquecer relatório por ação
 */
function enriquecerRelatorioPorAcao($relatorio) {
    if (isset($relatorio['estatisticas']) && is_array($relatorio['estatisticas'])) {
        foreach ($relatorio['estatisticas'] as &$acao) {
            // Adicionar descrição da ação
            $acao['descricao'] = getDescricaoAcao($acao['acao']);
            
            // Adicionar nível de criticidade
            $acao['criticidade'] = getNivelCriticidade($acao['acao']);
            
            // Calcular frequência média
            $diasPeriodo = (strtotime($acao['ultima_vez']) - strtotime($acao['primeira_vez'])) / (60 * 60 * 24);
            if ($diasPeriodo > 0) {
                $acao['frequencia_media'] = round($acao['total'] / $diasPeriodo, 2);
            } else {
                $acao['frequencia_media'] = $acao['total'];
            }
        }
    }
    
    return $relatorio;
}

/**
 * Enriquecer relatório de acessos
 */
function enriquecerRelatorioAcessos($relatorio) {
    if (isset($relatorio['estatisticas']) && is_array($relatorio['estatisticas'])) {
        // Agrupar por dia e calcular picos
        $acessosPorDia = [];
        foreach ($relatorio['estatisticas'] as $acesso) {
            $dia = $acesso['data'];
            if (!isset($acessosPorDia[$dia])) {
                $acessosPorDia[$dia] = ['total' => 0, 'horas' => []];
            }
            $acessosPorDia[$dia]['total'] += $acesso['total_acessos'];
            $acessosPorDia[$dia]['horas'][$acesso['hora']] = $acesso['total_acessos'];
        }
        
        // Encontrar dia com maior atividade
        $maiorAtividade = ['dia' => null, 'total' => 0];
        foreach ($acessosPorDia as $dia => $dados) {
            if ($dados['total'] > $maiorAtividade['total']) {
                $maiorAtividade = ['dia' => $dia, 'total' => $dados['total']];
            }
        }
        
        // Encontrar horário de pico
        $acessosPorHora = array_fill(0, 24, 0);
        foreach ($relatorio['estatisticas'] as $acesso) {
            $acessosPorHora[$acesso['hora']] += $acesso['total_acessos'];
        }
        
        $horarioPico = array_search(max($acessosPorHora), $acessosPorHora);
        
        $relatorio['analise'] = [
            'dia_maior_atividade' => $maiorAtividade,
            'horario_pico' => $horarioPico,
            'total_acessos_pico' => max($acessosPorHora),
            'media_acessos_por_hora' => array_sum($acessosPorHora) / 24
        ];
    }
    
    return $relatorio;
}

/**
 * Gerar relatório de segurança
 */
function gerarRelatorioSeguranca($periodo) {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $auditoria = new Auditoria();
    
    $dataInicio = $auditoria->calcularDataInicio($periodo);
    
    // Tentativas de login falharam
    $stmt = $db->prepare("
        SELECT 
            DATE(data_hora) as data,
            COUNT(*) as tentativas_falhas,
            COUNT(DISTINCT ip_origem) as ips_diferentes
        FROM Auditoria
        WHERE acao = 'LOGIN_FALHA'
        AND DATE(data_hora) >= :data_inicio
        GROUP BY DATE(data_hora)
        ORDER BY data DESC
    ");
    
    $stmt->execute([':data_inicio' => $dataInicio]);
    $loginsFalhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ações críticas (DELETE)
    $stmt = $db->prepare("
        SELECT 
            f.nome as funcionario,
            a.tabela,
            COUNT(*) as total_exclusoes,
            MAX(a.data_hora) as ultima_exclusao
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        WHERE a.acao = 'DELETE'
        AND DATE(a.data_hora) >= :data_inicio
        GROUP BY a.funcionario_id, a.tabela
        ORDER BY total_exclusoes DESC
    ");
    
    $stmt->execute([':data_inicio' => $dataInicio]);
    $exclusoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // IPs suspeitos (muitas ações em pouco tempo)
    $stmt = $db->prepare("
        SELECT 
            ip_origem,
            COUNT(*) as total_acoes,
            COUNT(DISTINCT funcionario_id) as funcionarios_diferentes,
            MIN(data_hora) as primeira_acao,
            MAX(data_hora) as ultima_acao
        FROM Auditoria
        WHERE DATE(data_hora) >= :data_inicio
        AND ip_origem IS NOT NULL
        GROUP BY ip_origem
        HAVING total_acoes > 100 OR funcionarios_diferentes > 1
        ORDER BY total_acoes DESC
    ");
    
    $stmt->execute([':data_inicio' => $dataInicio]);
    $ipsSuspeitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'tipo' => 'seguranca',
        'periodo' => $periodo,
        'data_inicio' => $dataInicio,
        'data_fim' => date('Y-m-d'),
        'estatisticas' => [
            'logins_falhas' => $loginsFalhas,
            'exclusoes_criticas' => $exclusoes,
            'ips_suspeitos' => $ipsSuspeitos,
            'resumo' => [
                'total_tentativas_falhas' => array_sum(array_column($loginsFalhas, 'tentativas_falhas')),
                'total_exclusoes' => array_sum(array_column($exclusoes, 'total_exclusoes')),
                'total_ips_suspeitos' => count($ipsSuspeitos)
            ]
        ]
    ];
}

/**
 * Gerar relatório de performance
 */
function gerarRelatorioPerformance($periodo) {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $auditoria = new Auditoria();
    
    $dataInicio = $auditoria->calcularDataInicio($periodo);
    
    // Ações por tabela
    $stmt = $db->prepare("
        SELECT 
            tabela,
            acao,
            COUNT(*) as total,
            AVG(UNIX_TIMESTAMP(data_hora)) as tempo_medio
        FROM Auditoria
        WHERE DATE(data_hora) >= :data_inicio
        GROUP BY tabela, acao
        ORDER BY total DESC
    ");
    
    $stmt->execute([':data_inicio' => $dataInicio]);
    $acoesPorTabela = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Atividade por dia da semana
    $stmt = $db->prepare("
        SELECT 
            DAYOFWEEK(data_hora) as dia_semana,
            CASE DAYOFWEEK(data_hora)
                WHEN 1 THEN 'Domingo'
                WHEN 2 THEN 'Segunda'
                WHEN 3 THEN 'Terça'
                WHEN 4 THEN 'Quarta'
                WHEN 5 THEN 'Quinta'
                WHEN 6 THEN 'Sexta'
                WHEN 7 THEN 'Sábado'
            END as nome_dia,
            COUNT(*) as total_acoes
        FROM Auditoria
        WHERE DATE(data_hora) >= :data_inicio
        GROUP BY DAYOFWEEK(data_hora)
        ORDER BY dia_semana
    ");
    
    $stmt->execute([':data_inicio' => $dataInicio]);
    $atividadePorDia = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'tipo' => 'performance',
        'periodo' => $periodo,
        'data_inicio' => $dataInicio,
        'data_fim' => date('Y-m-d'),
        'estatisticas' => [
            'acoes_por_tabela' => $acoesPorTabela,
            'atividade_por_dia_semana' => $atividadePorDia,
            'resumo' => [
                'tabela_mais_ativa' => $acoesPorTabela[0]['tabela'] ?? 'N/A',
                'dia_mais_ativo' => array_reduce($atividadePorDia, function($carry, $item) {
                    return ($carry === null || $item['total_acoes'] > $carry['total_acoes']) ? $item : $carry;
                })['nome_dia'] ?? 'N/A'
            ]
        ]
    ];
}

/**
 * Obter descrição da ação
 */
function getDescricaoAcao($acao) {
    $descricoes = [
        'INSERT' => 'Criação de novo registro',
        'UPDATE' => 'Atualização de registro existente',
        'DELETE' => 'Exclusão de registro',
        'LOGIN' => 'Login no sistema',
        'LOGOUT' => 'Logout do sistema',
        'LOGIN_FALHA' => 'Tentativa de login falharam',
        'VISUALIZAR' => 'Visualização de dados',
        'LISTAR' => 'Listagem de dados',
        'EXPORTAR' => 'Exportação de dados'
    ];
    
    return $descricoes[$acao] ?? 'Ação do sistema';
}

/**
 * Obter nível de criticidade da ação
 */
function getNivelCriticidade($acao) {
    $niveis = [
        'DELETE' => 'Alta',
        'LOGIN_FALHA' => 'Alta',
        'UPDATE' => 'Média',
        'INSERT' => 'Baixa',
        'LOGIN' => 'Baixa',
        'LOGOUT' => 'Baixa',
        'VISUALIZAR' => 'Baixa',
        'LISTAR' => 'Baixa',
        'EXPORTAR' => 'Média'
    ];
    
    return $niveis[$acao] ?? 'Baixa';
}
?>