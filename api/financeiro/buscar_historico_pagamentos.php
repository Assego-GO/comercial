<?php
/**
 * API para Buscar Histórico de Pagamentos
 * api/financeiro/buscar_historico_pagamentos.php
 */

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configurar erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Função para resposta JSON limpa
function enviarJSON($data) {
    if (ob_get_level()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        enviarJSON([
            'status' => 'error',
            'message' => 'Método não permitido. Use GET.'
        ]);
    }

    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';

    // Verificar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        enviarJSON([
            'status' => 'error',
            'message' => 'Usuário não autenticado.'
        ]);
    }

    $usuarioLogado = $auth->getUser();

    // Verificar permissões (apenas Financeiro e Presidência)
    if (!isset($usuarioLogado['departamento_id']) || !in_array($usuarioLogado['departamento_id'], [1, 5])) {
        enviarJSON([
            'status' => 'error',
            'message' => 'Acesso negado. Apenas Setor Financeiro e Presidência.'
        ]);
    }

    // Pegar parâmetros
    $mesReferencia = $_GET['mes'] ?? date('Y-m-01');
    $filtroStatus = $_GET['status'] ?? '';
    $filtroCorporacao = $_GET['corporacao'] ?? '';
    $limite = min(intval($_GET['limite'] ?? 500), 1000); // Máximo 1000 registros

    // Validar formato do mês
    if (!preg_match('/^\d{4}-\d{2}-01$/', $mesReferencia)) {
        enviarJSON([
            'status' => 'error',
            'message' => 'Formato de mês inválido. Use YYYY-MM-01'
        ]);
    }

    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // ===== BUSCAR ESTATÍSTICAS GERAIS =====
    $estatisticas = buscarEstatisticas($db, $mesReferencia);
    
    // ===== BUSCAR SITUAÇÃO DOS ASSOCIADOS =====
    $associados = buscarSituacaoAssociados($db, $mesReferencia, $filtroStatus, $filtroCorporacao, $limite);

    // Retornar resultado
    enviarJSON([
        'status' => 'success',
        'mes_referencia' => $mesReferencia,
        'mes_texto' => formatarMesTexto($mesReferencia),
        'estatisticas' => $estatisticas,
        'associados' => $associados,
        'total_registros' => count($associados),
        'data_consulta' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("❌ ERRO na API de histórico: " . $e->getMessage());
    enviarJSON([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'erro' => $e->getMessage()
        ]
    ]);
}

/**
 * BUSCAR ESTATÍSTICAS GERAIS
 */
function buscarEstatisticas($db, $mesReferencia) {
    try {
        $anoReferencia = date('Y', strtotime($mesReferencia));
        
        $sql = "SELECT 
                   -- Pagos no mês atual
                   COUNT(CASE WHEN p.mes_referencia = ? AND p.status_pagamento = 'CONFIRMADO' THEN 1 END) as pagos_mes_atual,
                   
                   -- Pendentes no mês atual (associados ativos que não pagaram)
                   (SELECT COUNT(*) 
                    FROM Associados a 
                    LEFT JOIN Financeiro f ON a.id = f.associado_id
                    WHERE a.situacao = 'Filiado' 
                    AND a.id NOT IN (
                        SELECT p2.associado_id 
                        FROM Pagamentos_Associado p2 
                        WHERE p2.mes_referencia = ? 
                        AND p2.status_pagamento = 'CONFIRMADO'
                    )
                   ) as pendentes_mes_atual,
                   
                   -- Total de pagamentos no ano
                   COUNT(CASE WHEN YEAR(p.mes_referencia) = ? AND p.status_pagamento = 'CONFIRMADO' THEN 1 END) as total_pagamentos_ano,
                   
                   -- Valor total arrecadado no ano
                   COALESCE(SUM(CASE WHEN YEAR(p.mes_referencia) = ? AND p.status_pagamento = 'CONFIRMADO' THEN p.valor_pago END), 0) as valor_total_ano,
                   
                   -- Média de valor por pagamento
                   COALESCE(AVG(CASE WHEN YEAR(p.mes_referencia) = ? AND p.status_pagamento = 'CONFIRMADO' THEN p.valor_pago END), 0) as valor_medio_pagamento
                   
                FROM Pagamentos_Associado p
                WHERE YEAR(p.mes_referencia) = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$mesReferencia, $mesReferencia, $anoReferencia, $anoReferencia, $anoReferencia, $anoReferencia]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular taxa de adimplência
        $totalAssociados = $resultado['pagos_mes_atual'] + $resultado['pendentes_mes_atual'];
        $taxaAdimplencia = $totalAssociados > 0 ? ($resultado['pagos_mes_atual'] / $totalAssociados) * 100 : 0;
        
        return [
            'pagos_mes_atual' => intval($resultado['pagos_mes_atual']),
            'pendentes_mes_atual' => intval($resultado['pendentes_mes_atual']),
            'total_pagamentos_ano' => intval($resultado['total_pagamentos_ano']),
            'valor_total_ano' => floatval($resultado['valor_total_ano']),
            'valor_medio_pagamento' => floatval($resultado['valor_medio_pagamento']),
            'taxa_adimplencia' => round($taxaAdimplencia, 2),
            'total_associados_ativos' => $totalAssociados
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas: " . $e->getMessage());
        return [
            'pagos_mes_atual' => 0,
            'pendentes_mes_atual' => 0,
            'total_pagamentos_ano' => 0,
            'valor_total_ano' => 0,
            'valor_medio_pagamento' => 0,
            'taxa_adimplencia' => 0,
            'total_associados_ativos' => 0
        ];
    }
}

/**
 * BUSCAR SITUAÇÃO DOS ASSOCIADOS
 */
function buscarSituacaoAssociados($db, $mesReferencia, $filtroStatus, $filtroCorporacao, $limite) {
    try {
        // Montar condições WHERE
        $whereConditions = ["a.situacao = 'Filiado'"];
        $params = [$mesReferencia, $mesReferencia];
        
        if ($filtroCorporacao) {
            $whereConditions[] = "m.corporacao = ?";
            $params[] = $filtroCorporacao;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT 
                   a.id,
                   a.nome,
                   a.cpf,
                   COALESCE(m.corporacao, 'N/A') as corporacao,
                   COALESCE(m.patente, 'N/A') as patente,
                   f.situacaoFinanceira as status_geral,
                   
                   -- Pagamento do mês atual
                   CASE 
                       WHEN p_atual.id IS NOT NULL THEN 'PAGO'
                       ELSE 'PENDENTE'
                   END as status_mes_atual,
                   
                   p_atual.valor_pago as valor_mes_atual,
                   p_atual.data_pagamento as data_pagamento_atual,
                   
                   -- Último pagamento
                   p_ultimo.mes_referencia as ultimo_mes_pago,
                   p_ultimo.valor_pago as valor_ultimo,
                   p_ultimo.data_pagamento as ultimo_pagamento,
                   
                   -- Estatísticas do associado
                   COALESCE(stats.total_pagamentos, 0) as total_pagamentos_ano,
                   COALESCE(stats.valor_total_pago, 0) as valor_total_ano,
                   
                   -- Dias em atraso
                   CASE 
                       WHEN p_atual.id IS NOT NULL THEN 0
                       ELSE GREATEST(0, DATEDIFF(CURDATE(), LAST_DAY(?)))
                   END as dias_atraso
                   
                FROM Associados a
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                
                -- Pagamento do mês específico
                LEFT JOIN Pagamentos_Associado p_atual ON (
                    a.id = p_atual.associado_id 
                    AND p_atual.mes_referencia = ?
                    AND p_atual.status_pagamento = 'CONFIRMADO'
                )
                
                -- Último pagamento confirmado
                LEFT JOIN Pagamentos_Associado p_ultimo ON (
                    a.id = p_ultimo.associado_id 
                    AND p_ultimo.status_pagamento = 'CONFIRMADO'
                    AND p_ultimo.data_pagamento = (
                        SELECT MAX(data_pagamento) 
                        FROM Pagamentos_Associado p2 
                        WHERE p2.associado_id = a.id 
                        AND p2.status_pagamento = 'CONFIRMADO'
                    )
                )
                
                -- Estatísticas do ano atual
                LEFT JOIN (
                    SELECT 
                        associado_id,
                        COUNT(*) as total_pagamentos,
                        SUM(valor_pago) as valor_total_pago
                    FROM Pagamentos_Associado 
                    WHERE YEAR(mes_referencia) = YEAR(?)
                    AND status_pagamento = 'CONFIRMADO'
                    GROUP BY associado_id
                ) stats ON a.id = stats.associado_id
                
                WHERE $whereClause
                ORDER BY a.nome
                LIMIT ?";
        
        // Adicionar parâmetros para as subconsultas
        array_splice($params, 2, 0, [$mesReferencia]); // Para o cálculo de dias em atraso
        $params[] = $limite;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Aplicar filtro de status se especificado
        if ($filtroStatus) {
            $resultados = array_filter($resultados, function($assoc) use ($filtroStatus) {
                switch ($filtroStatus) {
                    case 'PAGO':
                        return $assoc['status_mes_atual'] === 'PAGO';
                    case 'PENDENTE':
                        return $assoc['status_mes_atual'] === 'PENDENTE' && $assoc['dias_atraso'] <= 30;
                    case 'ATRASADO':
                        return $assoc['status_mes_atual'] === 'PENDENTE' && $assoc['dias_atraso'] > 30;
                    default:
                        return true;
                }
            });
        }
        
        // Formatar dados para o frontend
        return array_map(function($assoc) {
            return [
                'id' => intval($assoc['id']),
                'nome' => $assoc['nome'],
                'cpf' => formatarCPF($assoc['cpf']),
                'corporacao' => $assoc['corporacao'],
                'patente' => $assoc['patente'],
                'status_mes_atual' => $assoc['status_mes_atual'],
                'ultimo_pagamento' => $assoc['ultimo_pagamento'],
                'valor_ultimo' => floatval($assoc['valor_ultimo'] ?? 0),
                'dias_atraso' => intval($assoc['dias_atraso']),
                'total_pagamentos_ano' => intval($assoc['total_pagamentos_ano']),
                'valor_total_ano' => floatval($assoc['valor_total_ano']),
                'status_geral' => $assoc['status_geral']
            ];
        }, array_values($resultados));
        
    } catch (Exception $e) {
        error_log("Erro ao buscar situação dos associados: " . $e->getMessage());
        throw $e;
    }
}

/**
 * FORMATAR CPF
 */
function formatarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }
    return $cpf;
}

/**
 * FORMATAR MÊS PARA TEXTO
 */
function formatarMesTexto($mesReferencia) {
    $meses = [
        '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
        '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
        '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
        '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
    ];
    
    $data = date_create($mesReferencia);
    $mes = date_format($data, 'm');
    $ano = date_format($data, 'Y');
    
    return $meses[$mes] . '/' . $ano;
}
?>