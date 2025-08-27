<?php
/**
 * API para estatísticas do dashboard
 * api/dashboard_stats.php
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $stats = [];
    
    // 1. Associados Ativos (mantém)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.id) as total 
        FROM Associados a 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $stats['associados_ativos'] = $stmt->fetch()['total'] ?? 0;
    
    // 2. Novos Associados (30 dias) - APENAS ATIVOS
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.id) as total 
        FROM Associados a
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.situacao = 'Filiado'
        AND c.dataFiliacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $stats['novos_associados'] = $stmt->fetch()['total'] ?? 0;
    
    // 3. Associados por Corporação - APENAS ATIVOS, SEM DUPLICAÇÃO
    $stmt = $db->prepare("
        SELECT 
            COALESCE(m.corporacao, 'Não Informado') as corporacao,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 1) as percentual
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado' 
        GROUP BY COALESCE(m.corporacao, 'Não Informado')
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['por_corporacao'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // CORRIGIDO: Busca PM, BM e calcula OUTROS corretamente
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN 
                UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%'
                THEN a.id END) as pm_count,
                
            COUNT(DISTINCT CASE WHEN 
                UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%'
                THEN a.id END) as bm_count
                
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $corporacoes_principais = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pm_count = $corporacoes_principais['pm_count'] ?? 0;
    $bm_count = $corporacoes_principais['bm_count'] ?? 0;
    $total_ativos = $stats['associados_ativos'];
    
    // CORREÇÃO: Calcular "outros" subtraindo PM e BM do total de ativos
    $outros_count = $total_ativos - $pm_count - $bm_count;
    
    // Garantir que não fique negativo
    if ($outros_count < 0) {
        $outros_count = 0;
    }
    
    $total_corporacoes = $pm_count + $bm_count + $outros_count;
    
    // Dados para o card principal (PM + BM + OUTROS) - CORRIGIDO
    $stats['corporacoes_principais'] = [
        'pm_quantidade' => $pm_count,
        'bm_quantidade' => $bm_count,
        'outros_quantidade' => $outros_count,
        'total_quantidade' => $total_corporacoes,
        'pm_percentual' => $total_ativos > 0 ? round(($pm_count * 100) / $total_ativos, 1) : 0,
        'bm_percentual' => $total_ativos > 0 ? round(($bm_count * 100) / $total_ativos, 1) : 0,
        'outros_percentual' => $total_ativos > 0 ? round(($outros_count * 100) / $total_ativos, 1) : 0,
        'total_percentual' => $total_ativos > 0 ? round(($total_corporacoes * 100) / $total_ativos, 1) : 0
    ];
    
    // Mantém compatibilidade (pega a maior individual para fallback)
    if (!empty($stats['por_corporacao'])) {
        $stats['corporacao_principal'] = $stats['por_corporacao'][0];
    } else {
        $stats['corporacao_principal'] = ['corporacao' => 'N/A', 'quantidade' => 0, 'percentual' => 0];
    }
    
    // 4. Aniversariantes do Dia - APENAS ATIVOS
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT a.id) as total 
        FROM Associados a 
        WHERE a.situacao = 'Filiado' 
        AND DAY(a.nasc) = DAY(CURDATE()) 
        AND MONTH(a.nasc) = MONTH(CURDATE())
        AND a.nasc IS NOT NULL
    ");
    $stmt->execute();
    $stats['aniversariantes_hoje'] = $stmt->fetch()['total'] ?? 0;
    
    // 5. Capital vs Interior - APENAS ATIVOS, INCLUINDO OS SEM CIDADE
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') THEN a.id 
                ELSE NULL 
            END) as capital,
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) NOT IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND e.cidade IS NOT NULL 
                AND TRIM(e.cidade) != '' THEN a.id 
                ELSE NULL 
            END) as interior,
            COUNT(DISTINCT CASE 
                WHEN e.id IS NULL THEN a.id 
                ELSE NULL 
            END) as sem_endereco,
            COUNT(DISTINCT CASE 
                WHEN e.id IS NOT NULL 
                AND (e.cidade IS NULL OR TRIM(e.cidade) = '') THEN a.id 
                ELSE NULL 
            END) as endereco_sem_cidade,
            COUNT(DISTINCT a.id) as total_geral
        FROM Associados a 
        LEFT JOIN Endereco e ON a.id = e.associado_id 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $localizacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['capital'] = $localizacao['capital'] ?? 0;
    $stats['interior'] = $localizacao['interior'] ?? 0;
    $stats['sem_endereco'] = $localizacao['sem_endereco'] ?? 0;
    $stats['endereco_sem_cidade'] = $localizacao['endereco_sem_cidade'] ?? 0;
    $stats['total_localizacao'] = $stats['capital'] + $stats['interior'];
    $stats['total_geral_endereco'] = $localizacao['total_geral'] ?? 0;
    
    // Para o dashboard, vamos incluir os "sem cidade" no interior por enquanto
    // ou criar uma categoria "Não informado"
    $stats['nao_informado'] = $stats['sem_endereco'] + $stats['endereco_sem_cidade'];
    
    // Calcula percentuais baseado no total de ativos
    if ($stats['associados_ativos'] > 0) {
        $stats['capital_percentual'] = round(($stats['capital'] * 100) / $stats['associados_ativos'], 1);
        $stats['interior_percentual'] = round(($stats['interior'] * 100) / $stats['associados_ativos'], 1);
        $stats['nao_informado_percentual'] = round(($stats['nao_informado'] * 100) / $stats['associados_ativos'], 1);
    } else {
        $stats['capital_percentual'] = 0;
        $stats['interior_percentual'] = 0;
        $stats['nao_informado_percentual'] = 0;
    }
    
    // 6. Lista detalhada dos aniversariantes - APENAS ATIVOS, SEM DUPLICAÇÃO
    $stmt = $db->prepare("
        SELECT DISTINCT
            a.id,
            a.nome,
            a.nasc,
            m.patente,
            m.corporacao,
            TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) as idade
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE a.situacao = 'Filiado' 
        AND DAY(a.nasc) = DAY(CURDATE()) 
        AND MONTH(a.nasc) = MONTH(CURDATE())
        AND a.nasc IS NOT NULL
        ORDER BY a.nome
    ");
    $stmt->execute();
    $stats['aniversariantes_detalhes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retorna sucesso
    echo json_encode([
        'status' => 'success',
        'data' => $stats
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao buscar estatísticas do dashboard: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar estatísticas',
        'data' => [
            'associados_ativos' => 0,
            'novos_associados' => 0,
            'por_corporacao' => [],
            'corporacao_principal' => ['corporacao' => 'N/A', 'quantidade' => 0, 'percentual' => 0],
            'corporacoes_principais' => [
                'pm_quantidade' => 0,
                'bm_quantidade' => 0,
                'outros_quantidade' => 0,
                'total_quantidade' => 0,
                'pm_percentual' => 0,
                'bm_percentual' => 0,
                'outros_percentual' => 0,
                'total_percentual' => 0
            ],
            'aniversariantes_hoje' => 0,
            'capital' => 0,
            'interior' => 0,
            'capital_percentual' => 0,
            'interior_percentual' => 0,
            'aniversariantes_detalhes' => []
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>