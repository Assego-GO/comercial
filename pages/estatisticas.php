<?php
/**
 * Página de Relatórios Financeiros - Sistema ASSEGO
 * pages/relatorio_financeiro.php
 * VERSÃO CORRIGIDA COM DADOS ASAAS + NEOCONSIG
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once './components/header.php';

// Função para buscar dados reais do banco
function buscarDadosRelatorio($db, $filtros = []) {
    try {
        $dados = [];
        
        // ===== PREPARAR CONDIÇÕES DE FILTROS =====
        $filtrosPeriodo = construirFiltrosPeriodo($filtros);
        $filtrosStatus = construirFiltrosStatus($filtros);
        $filtrosTipo = construirFiltrosTipo($filtros);
        $filtrosValor = construirFiltrosValor($filtros);
        
        // 1. ESTATÍSTICAS GERAIS DE ASSOCIADOS
        $whereStats = [];
        $paramsStats = [];
        
        if ($filtrosStatus['condicao']) {
            $whereStats[] = $filtrosStatus['condicao'];
            $paramsStats = array_merge($paramsStats, $filtrosStatus['params']);
        }
        
        if ($filtrosTipo['condicao']) {
            $whereStats[] = $filtrosTipo['condicao'];
            $paramsStats = array_merge($paramsStats, $filtrosTipo['params']);
        }
        
        if ($filtrosPeriodo['condicao']) {
            $whereStats[] = $filtrosPeriodo['condicao_servicos'];
            $paramsStats = array_merge($paramsStats, $filtrosPeriodo['params']);
        }
        
        $sqlStats = "SELECT 
            COUNT(DISTINCT a.id) as total_associados,
            COUNT(DISTINCT CASE WHEN a.situacao = 'Filiado' THEN a.id END) as associados_ativos,
            COUNT(DISTINCT CASE WHEN a.situacao != 'Filiado' THEN a.id END) as cancelamentos,
            COUNT(DISTINCT sa.servico_id) as total_servicos_diferentes,
            COUNT(sa.id) as total_adesoes_servicos
        FROM Associados a
        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id";
        
        if (!empty($whereStats)) {
            $sqlStats .= " WHERE " . implode(" AND ", $whereStats);
        }
        
        $stmtStats = $db->prepare($sqlStats);
        $stmtStats->execute($paramsStats);
        $dados['estatisticas_gerais'] = $stmtStats->fetch(PDO::FETCH_ASSOC);
        
        // 2. VALORES A RECEBER (da tabela Servicos_Associado - valores contratados)
        $whereAReceber = ["sa.ativo = 1"];
        $paramsAReceber = [];
        
        if ($filtrosTipo['condicao']) {
            $whereAReceber[] = $filtrosTipo['condicao'];
            $paramsAReceber = array_merge($paramsAReceber, $filtrosTipo['params']);
        }
        
        if ($filtrosPeriodo['condicao']) {
            $whereAReceber[] = $filtrosPeriodo['condicao_servicos'];
            $paramsAReceber = array_merge($paramsAReceber, $filtrosPeriodo['params']);
        }
        
        $sqlAReceber = "SELECT 
            COALESCE(SUM(sa.valor_aplicado), 0) as total_a_receber,
            COUNT(DISTINCT sa.associado_id) as associados_com_servicos_ativos,
            COALESCE(AVG(sa.valor_aplicado), 0) as valor_medio_servico
        FROM Servicos_Associado sa
        JOIN Associados a ON sa.associado_id = a.id
        WHERE " . implode(" AND ", $whereAReceber);
        
        $stmtAReceber = $db->prepare($sqlAReceber);
        $stmtAReceber->execute($paramsAReceber);
        $dados['valores_a_receber'] = $stmtAReceber->fetch(PDO::FETCH_ASSOC);
        
        // 3. VALORES PAGOS VIA ASAAS (da tabela Pagamentos_Associado)
        $wherePagosAsaas = ["pa.status_pagamento = 'CONFIRMADO'"];
        $paramsPagosAsaas = [];
        
        // Aplicar filtro de período nos pagamentos
        if (!empty($filtros['periodo'])) {
            $filtroPagamentos = aplicarFiltroPeriodoPagamentos($filtros['periodo']);
            if ($filtroPagamentos['condicao']) {
                $wherePagosAsaas[] = $filtroPagamentos['condicao'];
                $paramsPagosAsaas = array_merge($paramsPagosAsaas, $filtroPagamentos['params']);
            }
        } else {
            $wherePagosAsaas[] = "YEAR(pa.mes_referencia) = YEAR(CURDATE())";
        }
        
        $sqlPagosAsaas = "SELECT 
            COALESCE(SUM(pa.valor_pago), 0) as total_pago_asaas,
            COUNT(*) as total_pagamentos_asaas,
            COUNT(DISTINCT pa.associado_id) as associados_pagantes_asaas,
            COALESCE(AVG(pa.valor_pago), 0) as valor_medio_pago_asaas
        FROM Pagamentos_Associado pa
        WHERE " . implode(" AND ", $wherePagosAsaas);
        
        $stmtPagosAsaas = $db->prepare($sqlPagosAsaas);
        $stmtPagosAsaas->execute($paramsPagosAsaas);
        $dados['valores_pagos_asaas'] = $stmtPagosAsaas->fetch(PDO::FETCH_ASSOC);
        
        // 4. VALORES PAGOS VIA NEOCONSIG (da tabela Financeiro - estimativa baseada na situação)
        $whereNeoconsig = ["f.id_neoconsig IS NOT NULL", "f.id_neoconsig != ''", "a.situacao = 'Filiado'"];
        $paramsNeoconsig = [];
        
        // Para NEOCONSIG, consideramos que estão pagando se não estão em situação ruim
        $sqlNeoconsig = "SELECT 
            COUNT(DISTINCT f.associado_id) as total_associados_neoconsig,
            COUNT(DISTINCT CASE 
                WHEN f.situacaoFinanceira IN ('Adimplente', 'Em dia', 'Regular') 
                   OR f.situacaoFinanceira IS NULL 
                   OR f.valor_em_aberto_asaas = 0 
                   OR f.valor_em_aberto_asaas IS NULL 
                THEN f.associado_id 
            END) as associados_pagantes_neoconsig,
            COUNT(DISTINCT CASE 
                WHEN f.situacaoFinanceira IN ('Inadimplente', 'Atraso', 'Pendente') 
                   AND f.valor_em_aberto_asaas > 0 
                THEN f.associado_id 
            END) as associados_inadimplentes_neoconsig,
            COALESCE(SUM(CASE 
                WHEN f.situacaoFinanceira IN ('Adimplente', 'Em dia', 'Regular') 
                   OR f.situacaoFinanceira IS NULL 
                   OR f.valor_em_aberto_asaas = 0 
                   OR f.valor_em_aberto_asaas IS NULL 
                THEN sa.valor_aplicado 
            END), 0) as valor_estimado_pago_neoconsig,
            COALESCE(SUM(sa.valor_aplicado), 0) as valor_total_contratado_neoconsig
        FROM Financeiro f
        JOIN Associados a ON f.associado_id = a.id
        JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1
        WHERE " . implode(" AND ", $whereNeoconsig);
        
        $stmtNeoconsig = $db->prepare($sqlNeoconsig);
        $stmtNeoconsig->execute($paramsNeoconsig);
        $dados['valores_pagos_neoconsig'] = $stmtNeoconsig->fetch(PDO::FETCH_ASSOC);
        
        // 5. CONSOLIDAÇÃO DOS VALORES PAGOS (ASAAS + NEOCONSIG)
        $dados['valores_pagos'] = [
            'total_pago' => $dados['valores_pagos_asaas']['total_pago_asaas'] + $dados['valores_pagos_neoconsig']['valor_estimado_pago_neoconsig'],
            'total_pagamentos' => $dados['valores_pagos_asaas']['total_pagamentos_asaas'] + $dados['valores_pagos_neoconsig']['associados_pagantes_neoconsig'],
            'associados_pagantes' => $dados['valores_pagos_asaas']['associados_pagantes_asaas'] + $dados['valores_pagos_neoconsig']['associados_pagantes_neoconsig'],
            'valor_medio_pago' => ($dados['valores_pagos_asaas']['total_pago_asaas'] + $dados['valores_pagos_neoconsig']['valor_estimado_pago_neoconsig']) / 
                                max(1, $dados['valores_pagos_asaas']['associados_pagantes_asaas'] + $dados['valores_pagos_neoconsig']['associados_pagantes_neoconsig'])
        ];
        
        // 6. ANÁLISE DE FORMAS DE PAGAMENTO
        $dados['analise_formas_pagamento'] = [
            'total_asaas' => $dados['valores_pagos_asaas']['total_pago_asaas'],
            'total_neoconsig' => $dados['valores_pagos_neoconsig']['valor_estimado_pago_neoconsig'],
            'associados_asaas' => $dados['valores_pagos_asaas']['associados_pagantes_asaas'],
            'associados_neoconsig' => $dados['valores_pagos_neoconsig']['associados_pagantes_neoconsig'],
            'percentual_asaas' => ($dados['valores_pagos_asaas']['total_pago_asaas'] / max(1, $dados['valores_pagos']['total_pago'])) * 100,
            'percentual_neoconsig' => ($dados['valores_pagos_neoconsig']['valor_estimado_pago_neoconsig'] / max(1, $dados['valores_pagos']['total_pago'])) * 100
        ];
        
        // 7. INADIMPLÊNCIA CORRIGIDA (considerando ASAAS + NEOCONSIG)
        $dados['inadimplencia'] = [
            'valor_esperado' => $dados['valores_a_receber']['total_a_receber'],
            'valor_recebido' => $dados['valores_pagos']['total_pago'],
            'valor_pendente' => $dados['valores_a_receber']['total_a_receber'] - $dados['valores_pagos']['total_pago'],
            'percentual_recebido' => $dados['valores_a_receber']['total_a_receber'] > 0 ? 
                ($dados['valores_pagos']['total_pago'] / $dados['valores_a_receber']['total_a_receber']) * 100 : 0,
            'inadimplentes_asaas_real' => $dados['valores_pagos_asaas']['total_pagamentos_asaas'] > 0 ? 
                ($dados['valores_a_receber']['total_a_receber'] - $dados['valores_pagos_asaas']['total_pago_asaas']) : 0,
            'inadimplentes_neoconsig' => $dados['valores_pagos_neoconsig']['associados_inadimplentes_neoconsig'] ?? 0
        ];
        
        // 8. SERVIÇOS MAIS POPULARES (baseado em contratos ativos)
        $whereServicos = ["s.ativo = 1"];
        $paramsServicos = [];
        
        if ($filtrosTipo['condicao']) {
            $whereServicos[] = str_replace('sa.tipo_associado', 'sa.tipo_associado', $filtrosTipo['condicao']);
            $paramsServicos = array_merge($paramsServicos, $filtrosTipo['params']);
        }
        
        if ($filtrosPeriodo['condicao']) {
            $whereServicos[] = $filtrosPeriodo['condicao_servicos'];
            $paramsServicos = array_merge($paramsServicos, $filtrosPeriodo['params']);
        }
        
        if ($filtrosStatus['condicao_servicos']) {
            $whereServicos[] = $filtrosStatus['condicao_servicos'];
            $paramsServicos = array_merge($paramsServicos, $filtrosStatus['params']);
        }
        
        $sqlServicos = "SELECT 
            s.nome as servico_nome,
            s.id as servico_id,
            COUNT(sa.id) as total_adesoes,
            COALESCE(SUM(sa.valor_aplicado), 0) as valor_total_contratado,
            COALESCE(AVG(sa.valor_aplicado), 0) as valor_medio,
            COUNT(CASE WHEN sa.ativo = 1 THEN 1 END) as adesoes_ativas
        FROM Servicos s
        LEFT JOIN Servicos_Associado sa ON s.id = sa.servico_id
        LEFT JOIN Associados a ON sa.associado_id = a.id
        WHERE " . implode(" AND ", $whereServicos) . "
        GROUP BY s.id, s.nome
        ORDER BY total_adesoes DESC
        LIMIT 5";
        
        $stmtServicos = $db->prepare($sqlServicos);
        $stmtServicos->execute($paramsServicos);
        $dados['servicos_populares'] = $stmtServicos->fetchAll(PDO::FETCH_ASSOC);
        
        // 9. DISTRIBUIÇÃO POR TIPO DE ASSOCIADO (valores contratados)
        $whereTipos = ["sa.ativo = 1", "a.situacao = 'Filiado'"];
        $paramsTipos = [];
        
        if ($filtrosTipo['condicao']) {
            $whereTipos[] = $filtrosTipo['condicao'];
            $paramsTipos = array_merge($paramsTipos, $filtrosTipo['params']);
        }
        
        if ($filtrosPeriodo['condicao']) {
            $whereTipos[] = $filtrosPeriodo['condicao_servicos'];
            $paramsTipos = array_merge($paramsTipos, $filtrosPeriodo['params']);
        }
        
        $sqlTipos = "SELECT 
            COALESCE(sa.tipo_associado, 'Sem Serviços') as tipo_associado,
            COUNT(DISTINCT sa.associado_id) as total_associados,
            COALESCE(SUM(sa.valor_aplicado), 0) as valor_total_contratado,
            COALESCE(AVG(sa.valor_aplicado), 0) as valor_medio
        FROM Servicos_Associado sa
        JOIN Associados a ON sa.associado_id = a.id
        WHERE " . implode(" AND ", $whereTipos) . "
        GROUP BY sa.tipo_associado
        ORDER BY total_associados DESC";
        
        $stmtTipos = $db->prepare($sqlTipos);
        $stmtTipos->execute($paramsTipos);
        $dados['tipo_associado'] = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
        
        // 10. MAIORES CONTRIBUINTES (valores contratados)
        $whereContrib = ["sa.ativo = 1", "a.situacao = 'Filiado'"];
        $paramsContrib = [];
        
        if ($filtrosTipo['condicao']) {
            $whereContrib[] = $filtrosTipo['condicao'];
            $paramsContrib = array_merge($paramsContrib, $filtrosTipo['params']);
        }
        
        if ($filtrosValor['condicao']) {
            $whereContrib[] = $filtrosValor['condicao'];
            $paramsContrib = array_merge($paramsContrib, $filtrosValor['params']);
        }
        
        if ($filtrosPeriodo['condicao']) {
            $whereContrib[] = $filtrosPeriodo['condicao_servicos'];
            $paramsContrib = array_merge($paramsContrib, $filtrosPeriodo['params']);
        }
        
        $sqlContrib = "SELECT 
            MAX(a.nome) as nome,
            MAX(a.rg) as rg,
            a.id as associado_id,
            SUM(sa.valor_aplicado) as valor_total_contratado,
            COUNT(sa.id) as total_servicos,
            MAX(a.situacao) as situacao,
            MAX(a.email) as email,
            COALESCE(MAX(m.patente), 'Não informado') as patente,
            COALESCE(MAX(m.corporacao), 'Não informado') as corporacao,
            CASE 
                WHEN MAX(f.id_neoconsig) IS NOT NULL AND MAX(f.id_neoconsig) != '' 
                THEN 'NEOCONSIG' 
                ELSE 'ASAAS' 
            END as forma_pagamento,
            MAX(f.situacaoFinanceira) as situacao_financeira
        FROM Associados a
        JOIN Servicos_Associado sa ON a.id = sa.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        WHERE " . implode(" AND ", $whereContrib) . "
        GROUP BY a.id
        ORDER BY valor_total_contratado DESC
        LIMIT 5";
        
        $stmtContrib = $db->prepare($sqlContrib);
        $stmtContrib->execute($paramsContrib);
        $dados['maiores_contribuintes'] = $stmtContrib->fetchAll(PDO::FETCH_ASSOC);
        
        // 11. GRÁFICO MENSAL CONSOLIDADO (ASAAS + NEOCONSIG estimado)
        $dados['grafico_pagamentos_mensais'] = [];
        
        if (!empty($filtros['periodo'])) {
            $periodoInfo = calcularPeriodoGrafico($filtros['periodo']);
            $inicioPeriodo = $periodoInfo['inicio'];
            $fimPeriodo = $periodoInfo['fim'];
        } else {
            $inicioPeriodo = 11;
            $fimPeriodo = 0;
        }
        
        for ($i = $inicioPeriodo; $i >= $fimPeriodo; $i--) {
            $mesAno = date('Y-m', strtotime("-$i months"));
            $mesFormatado = date('m/Y', strtotime("-$i months"));
            
            // Pagamentos ASAAS
            $sqlMesAsaas = "SELECT 
                COALESCE(SUM(CASE WHEN pa.status_pagamento = 'CONFIRMADO' THEN pa.valor_pago END), 0) as valor_pago_asaas,
                COUNT(CASE WHEN pa.status_pagamento = 'CONFIRMADO' THEN 1 END) as pagamentos_confirmados_asaas
            FROM Pagamentos_Associado pa
            WHERE DATE_FORMAT(pa.mes_referencia, '%Y-%m') = ?";
            
            $stmtMesAsaas = $db->prepare($sqlMesAsaas);
            $stmtMesAsaas->execute([$mesAno]);
            $resultMesAsaas = $stmtMesAsaas->fetch(PDO::FETCH_ASSOC);
            
            // Estimativa NEOCONSIG (baseado na média mensal dos contratos ativos)
            $sqlMesNeoconsig = "SELECT 
                COALESCE(SUM(CASE 
                    WHEN f.situacaoFinanceira IN ('Adimplente', 'Em dia', 'Regular') 
                       OR f.situacaoFinanceira IS NULL 
                       OR f.valor_em_aberto_asaas = 0 
                       OR f.valor_em_aberto_asaas IS NULL 
                    THEN sa.valor_aplicado 
                END), 0) as valor_estimado_neoconsig,
                COUNT(CASE 
                    WHEN f.id_neoconsig IS NOT NULL AND f.id_neoconsig != ''
                       AND (f.situacaoFinanceira IN ('Adimplente', 'Em dia', 'Regular') 
                            OR f.situacaoFinanceira IS NULL 
                            OR f.valor_em_aberto_asaas = 0 
                            OR f.valor_em_aberto_asaas IS NULL)
                    THEN 1 
                END) as pagamentos_estimados_neoconsig
            FROM Financeiro f
            JOIN Associados a ON f.associado_id = a.id
            JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1
            WHERE a.situacao = 'Filiado'";
            
            $stmtMesNeoconsig = $db->prepare($sqlMesNeoconsig);
            $stmtMesNeoconsig->execute();
            $resultMesNeoconsig = $stmtMesNeoconsig->fetch(PDO::FETCH_ASSOC);
            
            $dados['grafico_pagamentos_mensais'][] = [
                'mes' => $mesFormatado,
                'valor_pago_asaas' => floatval($resultMesAsaas['valor_pago_asaas']),
                'valor_estimado_neoconsig' => floatval($resultMesNeoconsig['valor_estimado_neoconsig']),
                'valor_total' => floatval($resultMesAsaas['valor_pago_asaas']) + floatval($resultMesNeoconsig['valor_estimado_neoconsig']),
                'pagamentos_confirmados_asaas' => intval($resultMesAsaas['pagamentos_confirmados_asaas']),
                'pagamentos_estimados_neoconsig' => intval($resultMesNeoconsig['pagamentos_estimados_neoconsig'])
            ];
        }
        
        // 12. DEMOGRAFIA DOS ASSOCIADOS
        $whereDemografia = [];
        $paramsDemografia = [];
        
        if ($filtrosPeriodo['condicao']) {
            $whereDemografia[] = $filtrosPeriodo['condicao_associados'];
            $paramsDemografia = array_merge($paramsDemografia, $filtrosPeriodo['params']);
        }
        
        $sqlDemo = "SELECT 
            COUNT(*) as total_associados_cadastrados,
            COUNT(CASE WHEN situacao = 'Filiado' THEN 1 END) as filiados,
            COUNT(CASE WHEN pre_cadastro = 1 THEN 1 END) as pre_cadastros,
            COUNT(CASE WHEN pre_cadastro = 0 AND situacao = 'Filiado' THEN 1 END) as aprovados
        FROM Associados";
        
        if (!empty($whereDemografia)) {
            $sqlDemo .= " WHERE " . implode(" AND ", $whereDemografia);
        }
        
        $stmtDemo = $db->prepare($sqlDemo);
        $stmtDemo->execute($paramsDemografia);
        $dados['demografia_associados'] = $stmtDemo->fetch(PDO::FETCH_ASSOC);
        
        return $dados;
        
    } catch (Exception $e) {
        error_log("Erro ao buscar dados do relatório: " . $e->getMessage());
        throw $e;
    }
}

// Funções auxiliares para construir filtros
function construirFiltrosPeriodo($filtros) {
    if (empty($filtros['periodo'])) {
        return ['condicao' => false, 'params' => [], 'condicao_servicos' => '', 'condicao_associados' => ''];
    }
    
    $params = [];
    switch ($filtros['periodo']) {
        case 'mes':
            return [
                'condicao' => true,
                'condicao_servicos' => 'sa.data_adesao >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
                'condicao_associados' => 'data_pre_cadastro >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
                'params' => []
            ];
        case 'trimestre':
            return [
                'condicao' => true,
                'condicao_servicos' => 'sa.data_adesao >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)',
                'condicao_associados' => 'data_pre_cadastro >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)',
                'params' => []
            ];
        case 'ano':
            return [
                'condicao' => true,
                'condicao_servicos' => 'YEAR(sa.data_adesao) = YEAR(CURDATE())',
                'condicao_associados' => 'YEAR(data_pre_cadastro) = YEAR(CURDATE())',
                'params' => []
            ];
        default:
            return ['condicao' => false, 'params' => [], 'condicao_servicos' => '', 'condicao_associados' => ''];
    }
}

function construirFiltrosStatus($filtros) {
    if (empty($filtros['status'])) {
        return ['condicao' => false, 'params' => [], 'condicao_servicos' => ''];
    }
    
    if ($filtros['status'] == '1') {
        return [
            'condicao' => "a.situacao = 'Filiado'",
            'condicao_servicos' => "sa.ativo = 1",
            'params' => []
        ];
    } else {
        return [
            'condicao' => "a.situacao != 'Filiado'",
            'condicao_servicos' => "sa.ativo = 0",
            'params' => []
        ];
    }
}

function construirFiltrosTipo($filtros) {
    if (empty($filtros['tipo'])) {
        return ['condicao' => false, 'params' => []];
    }
    
    return [
        'condicao' => 'sa.tipo_associado = ?',
        'params' => [$filtros['tipo']]
    ];
}

function construirFiltrosValor($filtros) {
    if (empty($filtros['valor_min']) || !is_numeric($filtros['valor_min'])) {
        return ['condicao' => false, 'params' => []];
    }
    
    return [
        'condicao' => 'sa.valor_aplicado >= ?',
        'params' => [floatval($filtros['valor_min'])]
    ];
}

function calcularPeriodoGrafico($periodo) {
    switch ($periodo) {
        case 'mes':
            return ['inicio' => 0, 'fim' => 0, 'meses' => 1];
        case 'trimestre':
            return ['inicio' => 2, 'fim' => 0, 'meses' => 3];
        case 'ano':
            return ['inicio' => 11, 'fim' => 0, 'meses' => 12];
        default:
            return ['inicio' => 11, 'fim' => 0, 'meses' => 12];
    }
}

function aplicarFiltroPeriodoPagamentos($periodo) {
    switch ($periodo) {
        case 'mes':
            return [
                'condicao' => 'mes_referencia >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
                'params' => []
            ];
        case 'trimestre':
            return [
                'condicao' => 'mes_referencia >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)',
                'params' => []
            ];
        case 'ano':
            return [
                'condicao' => 'YEAR(mes_referencia) = YEAR(CURDATE())',
                'params' => []
            ];
        default:
            return ['condicao' => false, 'params' => []];
    }
}

// Verifica se é uma requisição AJAX para filtros
if (isset($_POST['action']) && $_POST['action'] === 'aplicar_filtros') {
    header('Content-Type: application/json');
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_CADASTRO . ";charset=" . DB_CHARSET;
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        // Captura filtros
        $filtros = [
            'periodo' => $_POST['periodo'] ?? '',
            'tipo' => $_POST['tipo'] ?? '',
            'status' => $_POST['status'] ?? '',
            'valor_min' => $_POST['valor_min'] ?? ''
        ];
        
        // Busca dados com filtros aplicados
        $dadosFiltrados = buscarDadosRelatorio($db, $filtros);
        
        echo json_encode([
            'success' => true,
            'data' => $dadosFiltrados,
            'filtros_aplicados' => $filtros
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Relatórios Financeiros - ASSEGO';

// DEBUG USUÁRIO LOGADO - CONSOLE
echo "<script>";
echo "console.log('=== DEBUG SISTEMA RELATÓRIOS FINANCEIROS ===');";
echo "console.log('Usuário logado:', " . json_encode($usuarioLogado) . ");";
echo "console.log('Departamento ID:', " . (isset($usuarioLogado['departamento_id']) ? json_encode($usuarioLogado['departamento_id']) : 'NULL') . ");";
echo "</script>";

// Verificação de permissões: APENAS financeiro (ID: 5) OU presidência (ID: 1)
$temPermissaoFinanceiro = false;
$motivoNegacao = 'Acesso restrito ao departamento financeiro e presidência.';

if (isset($usuarioLogado['departamento_id'])) {
    $deptId = $usuarioLogado['departamento_id'];
    if ($deptId == 5 || $deptId == 1) {
        $temPermissaoFinanceiro = true;
    }
}

// ===== CARREGAMENTO DE DADOS REAIS =====
$dadosRelatorio = [];
$statusConexao = '';
$totalRegistros = 0;
$erroCarregamento = false;
$tiposAssociados = []; // Para popular o filtro de tipos

if ($temPermissaoFinanceiro) {
    try {
        // Conecta ao banco
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_CADASTRO . ";charset=" . DB_CHARSET;
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        // Conta total de registros para referência
        $sqlCount = "SELECT COUNT(*) as total FROM Servicos_Associado";
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute();
        $totalRegistros = $stmtCount->fetch()['total'];
        
        // Busca tipos de associados reais para os filtros
        $sqlTipos = "SELECT DISTINCT tipo_associado 
                     FROM Servicos_Associado 
                     WHERE tipo_associado IS NOT NULL 
                     AND tipo_associado != '' 
                     ORDER BY tipo_associado";
        $stmtTipos = $db->prepare($sqlTipos);
        $stmtTipos->execute();
        $tiposAssociados = $stmtTipos->fetchAll(PDO::FETCH_COLUMN);
        
        // Busca dados reais
        $dadosRelatorio = buscarDadosRelatorio($db);
        $statusConexao = "✅ Dados reais carregados (" . number_format($totalRegistros, 0, ',', '.') . " registros de serviços)";
        
    } catch (Exception $e) {
        $erroCarregamento = true;
        $statusConexao = "❌ Erro ao carregar dados: " . $e->getMessage();
        
        // Inicializa dados vazios para evitar erros na interface
        $dadosRelatorio = [
            'estatisticas_gerais' => [
                'total_associados' => 0,
                'associados_ativos' => 0,
                'cancelamentos' => 0,
                'total_servicos_diferentes' => 0,
                'total_adesoes_servicos' => 0
            ],
            'valores_a_receber' => [
                'total_a_receber' => 0,
                'associados_com_servicos_ativos' => 0,
                'valor_medio_servico' => 0
            ],
            'valores_pagos' => [
                'total_pago' => 0,
                'total_pagamentos' => 0,
                'associados_pagantes' => 0,
                'valor_medio_pago' => 0
            ],
            'valores_pagos_asaas' => [
                'total_pago_asaas' => 0,
                'total_pagamentos_asaas' => 0,
                'associados_pagantes_asaas' => 0,
                'valor_medio_pago_asaas' => 0
            ],
            'valores_pagos_neoconsig' => [
                'total_associados_neoconsig' => 0,
                'associados_pagantes_neoconsig' => 0,
                'associados_inadimplentes_neoconsig' => 0,
                'valor_estimado_pago_neoconsig' => 0,
                'valor_total_contratado_neoconsig' => 0
            ],
            'analise_formas_pagamento' => [
                'total_asaas' => 0,
                'total_neoconsig' => 0,
                'associados_asaas' => 0,
                'associados_neoconsig' => 0,
                'percentual_asaas' => 0,
                'percentual_neoconsig' => 0
            ],
            'inadimplencia' => [
                'valor_esperado' => 0,
                'valor_recebido' => 0,
                'valor_pendente' => 0,
                'percentual_recebido' => 0,
                'inadimplentes_asaas_real' => 0,
                'inadimplentes_neoconsig' => 0
            ],
            'servicos_populares' => [],
            'tipo_associado' => [],
            'maiores_contribuintes' => [],
            'grafico_pagamentos_mensais' => [],
            'demografia_associados' => [
                'total_associados_cadastrados' => 0,
                'filiados' => 0,
                'pre_cadastros' => 0,
                'aprovados' => 0
            ]
        ];
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'financeiro',
    'notificationCount' => $dadosRelatorio['estatisticas_gerais']['cancelamentos'] ?? 0,
    'showSearch' => true
]);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <!-- CSS customizado para relatórios -->
    <style>
        /* Reset e Variáveis */
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #6b7280;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --light: #f9fafb;
            --dark: #111827;
            --border: #e5e7eb;
            --neoconsig: #8b5cf6;
            --asaas: #06b6d4;
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--light);
        }

        .content-area {
            flex: 1;
            padding: 1.5rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            line-height: 1.2;
        }

        .page-subtitle {
            color: var(--secondary);
            margin: 0.5rem 0 0;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.primary { background: var(--primary); }
        .stat-icon.success { background: var(--success); }
        .stat-icon.warning { background: var(--warning); }
        .stat-icon.danger { background: var(--danger); }
        .stat-icon.info { background: var(--info); }
        .stat-icon.neoconsig { background: var(--neoconsig); }
        .stat-icon.asaas { background: var(--asaas); }

        /* Actions Bar */
        .actions-bar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .filter-select,
        .filter-input {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .actions-row {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-modern {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-modern.btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-modern.btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-modern.btn-primary:disabled {
            background: var(--secondary);
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-modern.btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-modern.btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }

        /* Loading state */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff40;
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Filter Status */
        .filter-status {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #0c4a6e;
            display: none;
        }

        .filter-status.active {
            display: block;
        }

        /* Chart Containers */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-content {
            position: relative;
            height: 350px;
        }

        /* Table Containers */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }

        .table-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .table {
            margin: 0;
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            background: var(--primary);
            color: white;
            font-weight: 600;
            border: none;
            padding: 0.75rem;
            font-size: 0.875rem;
            text-align: left;
        }

        .table tbody td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Valores */
        .valor-monetario {
            font-weight: 600;
            color: var(--success);
        }

        .valor-destaque {
            font-weight: 600;
            color: var(--primary);
        }

        /* Badges */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .bg-primary { background: var(--primary); color: white; }
        .bg-success { background: var(--success); color: white; }
        .bg-warning { background: var(--warning); color: white; }
        .bg-info { background: var(--info); color: white; }
        .bg-neoconsig { background: var(--neoconsig); color: white; }
        .bg-asaas { background: var(--asaas); color: white; }

        /* Error Alert */
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #ef4444;
            color: #b91c1c;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .actions-row {
                justify-content: stretch;
            }

            .chart-content {
                height: 250px;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .stat-value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoFinanceiro): ?>
                <!-- Sem Permissão -->
                <div class="alert alert-danger">
                    <h4><i class="fas fa-ban me-2"></i>Acesso Negado aos Relatórios Financeiros</h4>
                    <p class="mb-3"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                    <a href="../pages/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Voltar ao Dashboard
                    </a>
                </div>

            <?php else: ?>
                <!-- Page Title -->
                <div class="page-header" data-aos="fade-right">
                    <div>
                        <h1 class="page-title">Relatórios Financeiros Consolidados</h1>
                        <p class="page-subtitle">Sistema de análise financeira com dados ASAAS + NEOCONSIG - ASSEGO</p>
                    </div>
                </div>

                <!-- Alert de Status dos Dados -->
                <?php if ($erroCarregamento): ?>
                <div class="alert-error">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Erro ao Carregar Dados</h6>
                    <p class="mb-0">
                        <strong>Status:</strong> <?php echo $statusConexao; ?><br>
                        Verifique a conexão com o banco de dados e tente novamente.
                    </p>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <h6><i class="fas fa-check-circle me-2"></i>Dados Consolidados Carregados</h6>
                    <p class="mb-0">
                        <strong><?php echo $statusConexao; ?></strong><br>
                        <small>Incluindo dados de ASAAS e NEOCONSIG (desconto em folha)</small>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Filter Status -->
                <div class="filter-status" id="filterStatus">
                    <i class="fas fa-filter me-2"></i>
                    <span id="filterStatusText">Filtros aplicados</span>
                </div>

                <!-- Stats Grid - Dados Gerais -->
                <div class="stats-grid" data-aos="fade-up" id="statsGrid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-associados-ativos"><?php echo number_format($dadosRelatorio['estatisticas_gerais']['associados_ativos'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Associados Ativos</div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-servicos"><?php echo number_format($dadosRelatorio['estatisticas_gerais']['total_servicos_diferentes'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Serviços Diferentes</div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-cogs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-cancelamentos"><?php echo number_format($dadosRelatorio['estatisticas_gerais']['cancelamentos'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Associados Cancelados</div>
                            </div>
                            <div class="stat-icon danger">
                                <i class="fas fa-ban"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO: ANÁLISE DE FORMAS DE PAGAMENTO -->
                <div class="page-header" data-aos="fade-right">
                    <div>
                        <h2 style="font-size: 1.5rem; color: #8b5cf6; margin: 0;">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Análise por Forma de Pagamento
                        </h2>
                        <p class="page-subtitle">Separação entre pagamentos via ASAAS vs NEOCONSIG (desconto em folha)</p>
                    </div>
                </div>

                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" style="color: #06b6d4;">R$ <?php echo number_format($dadosRelatorio['analise_formas_pagamento']['total_asaas'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Total ASAAS</div>
                            </div>
                            <div class="stat-icon asaas">
                                <i class="fas fa-credit-card"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" style="color: #8b5cf6;">R$ <?php echo number_format($dadosRelatorio['analise_formas_pagamento']['total_neoconsig'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Total NEOCONSIG</div>
                            </div>
                            <div class="stat-icon neoconsig">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($dadosRelatorio['analise_formas_pagamento']['associados_asaas'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Associados ASAAS</div>
                            </div>
                            <div class="stat-icon asaas">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($dadosRelatorio['analise_formas_pagamento']['associados_neoconsig'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Associados NEOCONSIG</div>
                            </div>
                            <div class="stat-icon neoconsig">
                                <i class="fas fa-user-tie"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO: VALORES A RECEBER -->
                <div class="page-header" data-aos="fade-right">
                    <div>
                        <h2 style="font-size: 1.5rem; color: #2563eb; margin: 0;">
                            <i class="fas fa-file-contract me-2"></i>
                            Valores a Receber (Contratos Ativos)
                        </h2>
                        <p class="page-subtitle">Valores contratados - o que deveria ser recebido</p>
                    </div>
                </div>

                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-total-a-receber">R$ <?php echo number_format($dadosRelatorio['valores_a_receber']['total_a_receber'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Total Contratado</div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-associados-com-servicos"><?php echo number_format($dadosRelatorio['valores_a_receber']['associados_com_servicos_ativos'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Associados com Serviços</div>
                            </div>
                            <div class="stat-icon info">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-valor-medio-servico">R$ <?php echo number_format($dadosRelatorio['valores_a_receber']['valor_medio_servico'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Valor Médio por Serviço</div>
                            </div>
                            <div class="stat-icon info">
                                <i class="fas fa-calculator"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO: VALORES PAGOS CONSOLIDADOS -->
                <div class="page-header" data-aos="fade-right">
                    <div>
                        <h2 style="font-size: 1.5rem; color: #10b981; margin: 0;">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            Valores Pagos Consolidados (ASAAS + NEOCONSIG)
                        </h2>
                        <p class="page-subtitle">Valores efetivamente recebidos via todas as formas de pagamento</p>
                    </div>
                </div>

                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-total-pago">R$ <?php echo number_format($dadosRelatorio['valores_pagos']['total_pago'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Total Recebido</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-total-pagamentos"><?php echo number_format($dadosRelatorio['valores_pagos']['total_pagamentos'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Total de Pagamentos</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-receipt"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-associados-pagantes"><?php echo number_format($dadosRelatorio['valores_pagos']['associados_pagantes'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Associados Pagantes</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" id="stat-valor-medio-pago">R$ <?php echo number_format($dadosRelatorio['valores_pagos']['valor_medio_pago'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Valor Médio dos Pagamentos</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO: INADIMPLÊNCIA CORRIGIDA -->
                <?php if (isset($dadosRelatorio['inadimplencia'])): ?>
                <div class="page-header" data-aos="fade-right">
                    <div>
                        <h2 style="font-size: 1.5rem; color: #ef4444; margin: 0;">
                            <i class="fas fa-chart-pie me-2"></i>
                            Análise de Inadimplência Corrigida
                        </h2>
                        <p class="page-subtitle">Considerando pagamentos via ASAAS + NEOCONSIG</p>
                    </div>
                </div>

                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" style="color: #ef4444;">R$ <?php echo number_format($dadosRelatorio['inadimplencia']['valor_pendente'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Valor Real em Aberto</div>
                            </div>
                            <div class="stat-icon danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" style="color: #10b981;"><?php echo number_format($dadosRelatorio['inadimplencia']['percentual_recebido'] ?? 0, 1, ',', '.'); ?>%</div>
                                <div class="stat-label">Percentual Real Recebido</div>
                            </div>
                            <div class="stat-icon" style="background: <?php echo ($dadosRelatorio['inadimplencia']['percentual_recebido'] ?? 0) > 80 ? '#10b981' : '#ef4444'; ?>">
                                <i class="fas fa-percentage"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value">R$ <?php echo number_format($dadosRelatorio['inadimplencia']['valor_esperado'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Valor Esperado Total</div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-target"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" style="color: #10b981;">R$ <?php echo number_format($dadosRelatorio['inadimplencia']['valor_recebido'] ?? 0, 2, ',', '.'); ?></div>
                                <div class="stat-label">Valor Real Recebido</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Demografia -->
                <?php if (isset($dadosRelatorio['demografia_associados'])): ?>
                <div class="stats-grid" data-aos="fade-up">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($dadosRelatorio['demografia_associados']['filiados'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Filiados</div>
                            </div>
                            <div class="stat-icon info">
                                <i class="fas fa-id-badge"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($dadosRelatorio['demografia_associados']['pre_cadastros'] ?? 0, 0, ',', '.'); ?></div>
                                <div class="stat-label">Pré-Cadastros</div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="actions-bar" data-aos="fade-up" data-aos-delay="200">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label class="filter-label">Período</label>
                            <select class="filter-select" id="filtroPeriodo">
                                <option value="">Todo período</option>
                                <option value="mes">Este mês</option>
                                <option value="trimestre">Último trimestre</option>
                                <option value="ano">Este ano</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Tipo de Associado</label>
                            <select class="filter-select" id="filtroTipo">
                                <option value="">Todos os tipos</option>
                                <?php if (!empty($tiposAssociados)): ?>
                                    <?php foreach ($tiposAssociados as $tipo): ?>
                                        <option value="<?php echo htmlspecialchars($tipo); ?>">
                                            <?php echo htmlspecialchars($tipo); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="Contribuinte">Contribuinte</option>
                                    <option value="Pensionista">Pensionista</option>
                                    <option value="Dependente">Dependente</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select class="filter-select" id="filtroStatus">
                                <option value="">Todos os status</option>
                                <option value="1">Ativos</option>
                                <option value="0">Cancelados</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Valor Mínimo</label>
                            <input type="number" class="filter-input" id="filtroValorMin" placeholder="R$ 0,00" step="0.01">
                        </div>
                    </div>

                    <div class="actions-row">
                        <button class="btn-modern btn-secondary" onclick="limparFiltros()">
                            <i class="fas fa-eraser"></i>
                            Limpar Filtros
                        </button>
                        <button class="btn-modern btn-primary" id="btnAplicarFiltros" onclick="aplicarFiltros()">
                            <i class="fas fa-filter"></i>
                            Aplicar Filtros
                        </button>
                        <button class="btn-modern btn-primary" onclick="exportarRelatorio()">
                            <i class="fas fa-download"></i>
                            Exportar
                        </button>
                    </div>
                </div>
                
                <!-- Gráfico Consolidado -->
                <div class="chart-container" data-aos="fade-up" data-aos-delay="300">
                    <div class="chart-header">
                        <h5 class="chart-title">
                            <i class="fas fa-chart-area"></i>
                            Pagamentos Mensais Consolidados (ASAAS + NEOCONSIG)
                        </h5>
                        <div>
                            <button class="btn-modern btn-secondary btn-sm" onclick="alternarTipoGrafico()">
                                <i class="fas fa-exchange-alt"></i>
                                Alternar
                            </button>
                        </div>
                    </div>
                    <div class="chart-content">
                        <?php if (!empty($dadosRelatorio['grafico_pagamentos_mensais'])): ?>
                            <canvas id="graficoPagamentosMensais"></canvas>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-area"></i>
                                <h5>Nenhum pagamento encontrado</h5>
                                <p>Não há dados de pagamentos para exibir o gráfico.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Grid de Gráficos -->
                <div class="row" data-aos="fade-up" data-aos-delay="400">
                    <div class="col-lg-6 mb-4">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="chart-title">
                                    <i class="fas fa-chart-pie"></i>
                                    Serviços Mais Utilizados
                                </h5>
                            </div>
                            <div class="chart-content">
                                <?php if (!empty($dadosRelatorio['servicos_populares'])): ?>
                                    <canvas id="graficoServicos"></canvas>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-chart-pie"></i>
                                        <h5>Nenhum serviço encontrado</h5>
                                        <p>Não há serviços cadastrados para exibir.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="chart-title">
                                    <i class="fas fa-chart-doughnut"></i>
                                    Distribuição ASAAS vs NEOCONSIG
                                </h5>
                            </div>
                            <div class="chart-content">
                                <?php if (isset($dadosRelatorio['analise_formas_pagamento']) && 
                                         ($dadosRelatorio['analise_formas_pagamento']['total_asaas'] > 0 || 
                                          $dadosRelatorio['analise_formas_pagamento']['total_neoconsig'] > 0)): ?>
                                    <canvas id="graficoFormasPagamento"></canvas>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-chart-doughnut"></i>
                                        <h5>Nenhum dado encontrado</h5>
                                        <p>Não há dados de formas de pagamento para exibir.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Maiores Contribuintes -->
                <div class="table-container" data-aos="fade-up" data-aos-delay="500">
                    <div class="table-header">
                        <h5 class="table-title">
                            <i class="fas fa-trophy"></i>
                            Top 5 Maiores Contribuintes (Todas as Formas de Pagamento)
                        </h5>
                        <p style="font-size: 0.875rem; color: #6b7280; margin: 0.5rem 0 0;">
                            Incluindo identificação da forma de pagamento (ASAAS vs NEOCONSIG)
                        </p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Pos</th>
                                    <th>Nome</th>
                                    <th>RG</th>
                                    <th>Email</th>
                                    <th>Patente</th>
                                    <th>Corporação</th>
                                    <th>Forma Pagamento</th>
                                    <th>Situação Financeira</th>
                                    <th>Valor Contratado</th>
                                    <th>Serviços</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaContribuintes">
                                <?php if (!empty($dadosRelatorio['maiores_contribuintes'])): ?>
                                    <?php foreach ($dadosRelatorio['maiores_contribuintes'] as $index => $contrib): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $index + 1; ?>º</span>
                                            </td>
                                            <td class="valor-destaque"><?php echo htmlspecialchars($contrib['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($contrib['rg'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($contrib['email'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($contrib['patente']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($contrib['corporacao']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $contrib['forma_pagamento'] == 'NEOCONSIG' ? 'bg-neoconsig' : 'bg-asaas'; ?>">
                                                    <?php echo htmlspecialchars($contrib['forma_pagamento'] ?? 'ASAAS'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo htmlspecialchars($contrib['situacao_financeira'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td class="valor-monetario">
                                                R$ <?php echo number_format($contrib['valor_total_contratado'], 2, ',', '.'); ?>
                                            </td>
                                            <td><?php echo $contrib['total_servicos']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Nenhum contribuinte encontrado
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Análise Detalhada por Forma de Pagamento -->
                <div class="table-container" data-aos="fade-up" data-aos-delay="600">
                    <div class="table-header">
                        <h5 class="table-title">
                            <i class="fas fa-exchange-alt"></i>
                            Análise Detalhada por Forma de Pagamento
                        </h5>
                        <p style="font-size: 0.875rem; color: #6b7280; margin: 0.5rem 0 0;">
                            Comparação entre ASAAS e NEOCONSIG
                        </p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Forma de Pagamento</th>
                                    <th>Total Recebido</th>
                                    <th>Número de Associados</th>
                                    <th>Percentual do Total</th>
                                    <th>Valor Médio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <span class="badge bg-asaas">ASAAS</span>
                                    </td>
                                    <td class="valor-monetario">
                                        R$ <?php echo number_format($dadosRelatorio['analise_formas_pagamento']['total_asaas'] ?? 0, 2, ',', '.'); ?>
                                    </td>
                                    <td><?php echo number_format($dadosRelatorio['analise_formas_pagamento']['associados_asaas'] ?? 0, 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($dadosRelatorio['analise_formas_pagamento']['percentual_asaas'] ?? 0, 1, ',', '.'); ?>%</td>
                                    <td>
                                        R$ <?php echo number_format(
                                            ($dadosRelatorio['analise_formas_pagamento']['associados_asaas'] ?? 0) > 0 ? 
                                            ($dadosRelatorio['analise_formas_pagamento']['total_asaas'] ?? 0) / $dadosRelatorio['analise_formas_pagamento']['associados_asaas'] : 0, 
                                            2, ',', '.'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="badge bg-neoconsig">NEOCONSIG</span>
                                    </td>
                                    <td class="valor-monetario">
                                        R$ <?php echo number_format($dadosRelatorio['analise_formas_pagamento']['total_neoconsig'] ?? 0, 2, ',', '.'); ?>
                                    </td>
                                    <td><?php echo number_format($dadosRelatorio['analise_formas_pagamento']['associados_neoconsig'] ?? 0, 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($dadosRelatorio['analise_formas_pagamento']['percentual_neoconsig'] ?? 0, 1, ',', '.'); ?>%</td>
                                    <td>
                                        R$ <?php echo number_format(
                                            ($dadosRelatorio['analise_formas_pagamento']['associados_neoconsig'] ?? 0) > 0 ? 
                                            ($dadosRelatorio['analise_formas_pagamento']['total_neoconsig'] ?? 0) / $dadosRelatorio['analise_formas_pagamento']['associados_neoconsig'] : 0, 
                                            2, ',', '.'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Dados do relatório
        let dadosRelatorio = <?php echo json_encode($dadosRelatorio); ?>;
        const totalRegistros = <?php echo $totalRegistros; ?>;
        const erroCarregamento = <?php echo $erroCarregamento ? 'true' : 'false'; ?>;

        console.log('🔍 Dados do relatório consolidado:', dadosRelatorio);
        console.log('📊 Total de registros:', totalRegistros);
        console.log('❌ Erro no carregamento:', erroCarregamento);

        let graficos = {};
        let filtrosAtivos = false;

        document.addEventListener('DOMContentLoaded', function() {
            if (!erroCarregamento) {
                initGraficos();
            }
            console.log('✅ Relatórios consolidados inicializados', erroCarregamento ? 'com erro' : 'com dados reais');
        });

        function initGraficos() {
            Chart.defaults.font.family = "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#6b7280';

            criarGraficoPagamentosConsolidado();
            criarGraficoServicos();
            criarGraficoFormasPagamento();

            console.log('📊 Gráficos consolidados criados com sucesso!');
        }

        function criarGraficoPagamentosConsolidado() {
            const ctx = document.getElementById('graficoPagamentosMensais');
            if (!ctx) return;

            const dados = dadosRelatorio.grafico_pagamentos_mensais || [];
            
            if (dados.length === 0) {
                return;
            }
            
            // Destroi gráfico existente se houver
            if (graficos.pagamentosConsolidado) {
                graficos.pagamentosConsolidado.destroy();
            }
            
            graficos.pagamentosConsolidado = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dados.map(d => d.mes),
                    datasets: [{
                        label: 'ASAAS (R$)',
                        data: dados.map(d => d.valor_pago_asaas),
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6, 182, 212, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#06b6d4',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }, {
                        label: 'NEOCONSIG Estimado (R$)',
                        data: dados.map(d => d.valor_estimado_neoconsig),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#8b5cf6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }, {
                        label: 'Total Consolidado (R$)',
                        data: dados.map(d => d.valor_total),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: false,
                        tension: 0.3,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#ffffff',
                            bodyColor: '#ffffff',
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': R$ ' + context.parsed.y.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        function criarGraficoServicos() {
            const ctx = document.getElementById('graficoServicos');
            if (!ctx) return;

            const dados = dadosRelatorio.servicos_populares || [];
            const top5 = dados.slice(0, 5);

            if (top5.length === 0) {
                return;
            }

            // Destroi gráfico existente se houver
            if (graficos.servicos) {
                graficos.servicos.destroy();
            }

            const cores = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#06b6d4'];

            graficos.servicos = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: top5.map(s => s.servico_nome || 'Serviço'),
                    datasets: [{
                        data: top5.map(s => s.total_adesoes),
                        backgroundColor: cores,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, usePointStyle: true, font: { size: 12 } }
                        }
                    }
                }
            });
        }

        function criarGraficoFormasPagamento() {
            const ctx = document.getElementById('graficoFormasPagamento');
            if (!ctx) return;

            const dadosFormas = dadosRelatorio.analise_formas_pagamento || {};
            
            if (!dadosFormas.total_asaas && !dadosFormas.total_neoconsig) {
                return;
            }

            // Destroi gráfico existente se houver
            if (graficos.formasPagamento) {
                graficos.formasPagamento.destroy();
            }

            graficos.formasPagamento = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['ASAAS', 'NEOCONSIG'],
                    datasets: [{
                        data: [dadosFormas.total_asaas || 0, dadosFormas.total_neoconsig || 0],
                        backgroundColor: ['#06b6d4', '#8b5cf6'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, usePointStyle: true, font: { size: 12 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const valor = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const porcentagem = ((valor / total) * 100).toFixed(1);
                                    return context.label + ': R$ ' + valor.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }) + ' (' + porcentagem + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        function alternarTipoGrafico() {
            if (graficos.pagamentosConsolidado) {
                const grafico = graficos.pagamentosConsolidado;
                const novoTipo = grafico.config.type === 'line' ? 'bar' : 'line';
                
                grafico.config.type = novoTipo;
                grafico.update();
                
                console.log(`Gráfico alterado para ${novoTipo === 'line' ? 'linha' : 'barras'}!`);
            }
        }

        function aplicarFiltros() {
            const btnAplicar = document.getElementById('btnAplicarFiltros');
            const originalHTML = btnAplicar.innerHTML;
            
            // Mostra loading
            btnAplicar.disabled = true;
            btnAplicar.innerHTML = '<div class="loading-spinner"></div> Aplicando...';
            
            // Captura valores dos filtros
            const filtros = {
                action: 'aplicar_filtros',
                periodo: document.getElementById('filtroPeriodo').value,
                tipo: document.getElementById('filtroTipo').value,
                status: document.getElementById('filtroStatus').value,
                valor_min: document.getElementById('filtroValorMin').value
            };

            console.log('🔍 Aplicando filtros:', filtros);

            // Faz requisição AJAX
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: filtros,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Filtros aplicados com sucesso!', response.data);
                        
                        // Atualiza dados globais
                        dadosRelatorio = response.data;
                        filtrosAtivos = true;
                        
                        // Atualiza interface
                        atualizarEstatisticas(response.data);
                        atualizarTabelas(response.data);
                        atualizarGraficos(response.data);
                        
                        // Mostra status dos filtros
                        mostrarStatusFiltros(response.filtros_aplicados);
                        
                        console.log('🎉 Interface atualizada com dados filtrados!');
                    } else {
                        console.error('❌ Erro ao aplicar filtros:', response.error);
                        alert('Erro ao aplicar filtros: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erro na requisição AJAX:', error);
                    alert('Erro na comunicação com o servidor. Tente novamente.');
                },
                complete: function() {
                    // Remove loading
                    btnAplicar.disabled = false;
                    btnAplicar.innerHTML = originalHTML;
                }
            });
        }

        function atualizarEstatisticas(dados) {
            document.getElementById('stat-associados-ativos').textContent = new Intl.NumberFormat('pt-BR').format(dados.estatisticas_gerais.associados_ativos);
            document.getElementById('stat-servicos').textContent = new Intl.NumberFormat('pt-BR').format(dados.estatisticas_gerais.total_servicos_diferentes);
            document.getElementById('stat-cancelamentos').textContent = new Intl.NumberFormat('pt-BR').format(dados.estatisticas_gerais.cancelamentos);
            
            document.getElementById('stat-total-a-receber').textContent = 'R$ ' + new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(dados.valores_a_receber.total_a_receber);
            
            document.getElementById('stat-total-pago').textContent = 'R$ ' + new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(dados.valores_pagos.total_pago);
            
            document.getElementById('stat-associados-com-servicos').textContent = new Intl.NumberFormat('pt-BR').format(dados.valores_a_receber.associados_com_servicos_ativos);
            document.getElementById('stat-valor-medio-servico').textContent = 'R$ ' + new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(dados.valores_a_receber.valor_medio_servico);
            
            document.getElementById('stat-total-pagamentos').textContent = new Intl.NumberFormat('pt-BR').format(dados.valores_pagos.total_pagamentos);
            document.getElementById('stat-associados-pagantes').textContent = new Intl.NumberFormat('pt-BR').format(dados.valores_pagos.associados_pagantes);
            document.getElementById('stat-valor-medio-pago').textContent = 'R$ ' + new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(dados.valores_pagos.valor_medio_pago);
        }

        function atualizarTabelas(dados) {
            // Atualiza tabela de contribuintes
            const tabelaContribuintes = document.getElementById('tabelaContribuintes');
            if (dados.maiores_contribuintes.length > 0) {
                tabelaContribuintes.innerHTML = dados.maiores_contribuintes.map((contrib, index) => `
                    <tr>
                        <td><span class="badge bg-primary">${index + 1}º</span></td>
                        <td class="valor-destaque">${contrib.nome || 'Nome não informado'}</td>
                        <td>${contrib.rg || 'N/A'}</td>
                        <td>${contrib.email || 'N/A'}</td>
                        <td><span class="badge bg-info">${contrib.patente || 'N/A'}</span></td>
                        <td>${contrib.corporacao || 'N/A'}</td>
                        <td><span class="badge ${contrib.forma_pagamento === 'NEOCONSIG' ? 'bg-neoconsig' : 'bg-asaas'}">${contrib.forma_pagamento || 'ASAAS'}</span></td>
                        <td><span class="badge bg-success">${contrib.situacao_financeira || 'N/A'}</span></td>
                        <td class="valor-monetario">R$ ${new Intl.NumberFormat('pt-BR', {minimumFractionDigits: 2}).format(contrib.valor_total_contratado)}</td>
                        <td>${contrib.total_servicos}</td>
                    </tr>
                `).join('');
            } else {
                tabelaContribuintes.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            Nenhum contribuinte encontrado com os filtros aplicados
                        </td>
                    </tr>
                `;
            }
        }

        function atualizarGraficos(dados) {
            // Recria todos os gráficos com os novos dados
            criarGraficoPagamentosConsolidado();
            criarGraficoServicos();
            criarGraficoFormasPagamento();
        }

        function mostrarStatusFiltros(filtros) {
            const statusDiv = document.getElementById('filterStatus');
            const statusText = document.getElementById('filterStatusText');
            
            const filtrosAtivos = [];
            
            if (filtros.periodo) {
                const periodos = {
                    'mes': 'Este mês',
                    'trimestre': 'Último trimestre', 
                    'ano': 'Este ano'
                };
                filtrosAtivos.push(`Período: ${periodos[filtros.periodo]}`);
            }
            
            if (filtros.tipo) {
                filtrosAtivos.push(`Tipo: ${filtros.tipo}`);
            }
            
            if (filtros.status !== '') {
                filtrosAtivos.push(`Status: ${filtros.status === '1' ? 'Ativos' : 'Cancelados'}`);
            }
            
            if (filtros.valor_min) {
                filtrosAtivos.push(`Valor mín: R$ ${parseFloat(filtros.valor_min).toFixed(2)}`);
            }
            
            if (filtrosAtivos.length > 0) {
                statusText.textContent = 'Filtros aplicados: ' + filtrosAtivos.join(', ');
                statusDiv.classList.add('active');
            } else {
                statusDiv.classList.remove('active');
            }
        }

        function limparFiltros() {
            document.getElementById('filtroPeriodo').value = '';
            document.getElementById('filtroTipo').value = '';
            document.getElementById('filtroStatus').value = '';
            document.getElementById('filtroValorMin').value = '';
            
            // Remove status dos filtros
            document.getElementById('filterStatus').classList.remove('active');
            
            // Se houver filtros ativos, recarrega dados originais
            if (filtrosAtivos) {
                location.reload();
            }
            
            console.log('🧹 Filtros limpos');
        }

        function exportarRelatorio() {
            console.log('📥 Exportando relatório consolidado...');
            
            // Dados atuais (com filtros aplicados se houver)
            const dadosExport = {
                // Estatísticas Gerais
                estatisticas_gerais: dadosRelatorio.estatisticas_gerais,
                
                // Valores Separados
                valores_a_receber: dadosRelatorio.valores_a_receber,
                valores_pagos: dadosRelatorio.valores_pagos,
                valores_pagos_asaas: dadosRelatorio.valores_pagos_asaas,
                valores_pagos_neoconsig: dadosRelatorio.valores_pagos_neoconsig,
                
                // Análise de Formas de Pagamento
                analise_formas_pagamento: dadosRelatorio.analise_formas_pagamento,
                
                // Inadimplência Corrigida
                inadimplencia: dadosRelatorio.inadimplencia,
                
                // Análises
                servicos_populares: dadosRelatorio.servicos_populares,
                tipos_associados: dadosRelatorio.tipo_associado,
                maiores_contribuintes: dadosRelatorio.maiores_contribuintes,
                
                // Gráficos e Demografia
                grafico_pagamentos_mensais: dadosRelatorio.grafico_pagamentos_mensais,
                demografia_associados: dadosRelatorio.demografia_associados,
                
                // Metadados
                data_exportacao: new Date().toISOString(),
                total_registros: totalRegistros,
                modo_erro: erroCarregamento,
                filtros_aplicados: filtrosAtivos,
                
                // Explicações
                explicacoes: {
                    valores_a_receber: "Valores contratados - o que deveria ser recebido",
                    valores_pagos_asaas: "Valores efetivamente pagos via ASAAS",
                    valores_pagos_neoconsig: "Valores estimados como pagos via NEOCONSIG (desconto em folha)",
                    valores_pagos: "Consolidação ASAAS + NEOCONSIG",
                    inadimplencia: "Inadimplência real considerando ambas as formas de pagamento",
                    analise_formas_pagamento: "Separação detalhada entre ASAAS e NEOCONSIG"
                }
            };
            
            // Simula download
            const jsonData = JSON.stringify(dadosExport, null, 2);
            const blob = new Blob([jsonData], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = `relatorio_financeiro_consolidado_${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            console.log('✅ Relatório consolidado exportado!');
        }

        console.log('✅ Sistema de Relatórios Financeiros Consolidados funcionando!');
        console.log('💡 Modo:', erroCarregamento ? 'Erro no carregamento' : 'Produção com dados reais ASAAS + NEOCONSIG');
        console.log('🔧 Filtros implementados e funcionais');
        console.log('📊 Gráficos e tabelas operacionais');
        console.log('🎯 Tipos de associado disponíveis:', <?php echo json_encode($tiposAssociados); ?>);
        console.log('💰 VALORES CONSOLIDADOS:');
        console.log('  📈 Valores a Receber (Contratos):', dadosRelatorio.valores_a_receber);
        console.log('  💵 Valores Pagos ASAAS:', dadosRelatorio.valores_pagos_asaas);
        console.log('  🏢 Valores Pagos NEOCONSIG:', dadosRelatorio.valores_pagos_neoconsig);
        console.log('  📊 Valores Pagos Consolidados:', dadosRelatorio.valores_pagos);
        console.log('  ⚠️ Análise de Inadimplência Corrigida:', dadosRelatorio.inadimplencia);
        console.log('  🔄 Análise de Formas de Pagamento:', dadosRelatorio.analise_formas_pagamento);
        
        // Debug de filtros
        window.debugFiltros = function() {
            const filtros = {
                periodo: document.getElementById('filtroPeriodo').value,
                tipo: document.getElementById('filtroTipo').value,
                status: document.getElementById('filtroStatus').value,
                valor_min: document.getElementById('filtroValorMin').value
            };
            console.log('🔍 Estado atual dos filtros:', filtros);
            return filtros;
        };
        
        // Debug de valores consolidados
        window.debugValoresConsolidados = function() {
            console.log('💰 RESUMO FINANCEIRO CONSOLIDADO:');
            console.log('├─ 📋 Valores Contratados (a receber):', dadosRelatorio.valores_a_receber);
            console.log('├─ 💳 Valores ASAAS (confirmados):', dadosRelatorio.valores_pagos_asaas);
            console.log('├─ 🏢 Valores NEOCONSIG (estimados):', dadosRelatorio.valores_pagos_neoconsig);
            console.log('├─ ✅ Valores Consolidados:', dadosRelatorio.valores_pagos);
            console.log('├─ 📊 Inadimplência Corrigida:', dadosRelatorio.inadimplencia);
            console.log('└─ 🔄 Análise de Formas:', dadosRelatorio.analise_formas_pagamento);
        };
        
        console.log('💡 Para debug dos filtros, use: debugFiltros()');
        console.log('💡 Para debug dos valores consolidados, use: debugValoresConsolidados()');
        console.log('🎉 VALORES AGORA CONSOLIDADOS: ASAAS + NEOCONSIG!');
        console.log('✨ Inadimplência corrigida considerando desconto em folha!');
    </script>

</body>
</html>