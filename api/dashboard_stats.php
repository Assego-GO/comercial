<?php
/**
 * API para estatísticas do dashboard - COMPLETA COM ATIVA/RESERVA/PENSIONISTA EM TODOS OS KPIs
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
    
    // ===== 1. ASSOCIADOS ATIVOS COM ATIVA/RESERVA/PENSIONISTA =====
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_ativos,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA' THEN a.id END) as ativos_ativa,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA' THEN a.id END) as ativos_reserva,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA' THEN a.id END) as ativos_pensionista,
            COUNT(DISTINCT CASE WHEN m.id IS NULL OR TRIM(COALESCE(m.categoria, '')) = '' 
                OR UPPER(TRIM(COALESCE(m.categoria, ''))) NOT IN ('ATIVA', 'RESERVA', 'PENSIONISTA') THEN a.id END) as ativos_sem_categoria
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $associados_dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['associados_ativos'] = $associados_dados['total_ativos'] ?? 0;
    $stats['associados_ativa'] = $associados_dados['ativos_ativa'] ?? 0;
    $stats['associados_reserva'] = $associados_dados['ativos_reserva'] ?? 0;
    $stats['associados_pensionista'] = $associados_dados['ativos_pensionista'] ?? 0;
    $stats['associados_sem_categoria'] = $associados_dados['ativos_sem_categoria'] ?? 0;
    
    // ===== 2. NOVOS ASSOCIADOS (30 DIAS) COM ATIVA/RESERVA/PENSIONISTA =====
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_novos,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA' THEN a.id END) as novos_ativa,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA' THEN a.id END) as novos_reserva,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA' THEN a.id END) as novos_pensionista,
            COUNT(DISTINCT CASE WHEN m.id IS NULL OR TRIM(COALESCE(m.categoria, '')) = '' 
                OR UPPER(TRIM(COALESCE(m.categoria, ''))) NOT IN ('ATIVA', 'RESERVA', 'PENSIONISTA') THEN a.id END) as novos_sem_categoria
        FROM Associados a
        LEFT JOIN Contrato c ON a.id = c.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE a.situacao = 'Filiado'
        AND c.dataFiliacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $novos_dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['novos_associados'] = $novos_dados['total_novos'] ?? 0;
    $stats['novos_ativa'] = $novos_dados['novos_ativa'] ?? 0;
    $stats['novos_reserva'] = $novos_dados['novos_reserva'] ?? 0;
    $stats['novos_pensionista'] = $novos_dados['novos_pensionista'] ?? 0;
    $stats['novos_sem_categoria'] = $novos_dados['novos_sem_categoria'] ?? 0;
    
    // ===== 3. CORPORAÇÕES (PM/BM/OUTROS) COM ATIVA/RESERVA/PENSIONISTA =====
    $stmt = $db->prepare("
        SELECT 
            -- PM Total e por categoria
            COUNT(DISTINCT CASE WHEN 
                UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%'
                THEN a.id END) as pm_count,
                
            COUNT(DISTINCT CASE WHEN 
                (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA'
                THEN a.id END) as pm_ativa,
                
            COUNT(DISTINCT CASE WHEN 
                (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA'
                THEN a.id END) as pm_reserva,
                
            COUNT(DISTINCT CASE WHEN 
                (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA'
                THEN a.id END) as pm_pensionista,
                
            -- BM Total e por categoria
            COUNT(DISTINCT CASE WHEN 
                UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%'
                THEN a.id END) as bm_count,
                
            COUNT(DISTINCT CASE WHEN 
                (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA'
                THEN a.id END) as bm_ativa,
                
            COUNT(DISTINCT CASE WHEN 
                (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA'
                THEN a.id END) as bm_reserva,
                
            COUNT(DISTINCT CASE WHEN 
                (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                 OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA'
                THEN a.id END) as bm_pensionista
                
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $corporacoes_principais = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pm_count = $corporacoes_principais['pm_count'] ?? 0;
    $bm_count = $corporacoes_principais['bm_count'] ?? 0;
    $pm_ativa = $corporacoes_principais['pm_ativa'] ?? 0;
    $pm_reserva = $corporacoes_principais['pm_reserva'] ?? 0;
    $pm_pensionista = $corporacoes_principais['pm_pensionista'] ?? 0;
    $bm_ativa = $corporacoes_principais['bm_ativa'] ?? 0;
    $bm_reserva = $corporacoes_principais['bm_reserva'] ?? 0;
    $bm_pensionista = $corporacoes_principais['bm_pensionista'] ?? 0;
    $total_ativos = $stats['associados_ativos'];
    
    // Calcular "outros" subtraindo PM e BM do total de ativos
    $outros_count = $total_ativos - $pm_count - $bm_count;
    if ($outros_count < 0) {
        $outros_count = 0;
    }
    
    // Calcular Outros Ativa/Reserva/Pensionista
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN 
                -- Não é PM
                NOT (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%')
                AND 
                -- Não é BM
                NOT (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA'
                THEN a.id END) as outros_ativa,
                
            COUNT(DISTINCT CASE WHEN 
                -- Não é PM
                NOT (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%')
                AND 
                -- Não é BM
                NOT (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA'
                THEN a.id END) as outros_reserva,
                
            COUNT(DISTINCT CASE WHEN 
                -- Não é PM
                NOT (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'PM-%')
                AND 
                -- Não é BM
                NOT (UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                     OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE 'CBM%')
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA'
                THEN a.id END) as outros_pensionista
                
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $outros_categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $outros_ativa = $outros_categoria['outros_ativa'] ?? 0;
    $outros_reserva = $outros_categoria['outros_reserva'] ?? 0;
    $outros_pensionista = $outros_categoria['outros_pensionista'] ?? 0;
    
    $total_corporacoes = $pm_count + $bm_count + $outros_count;
    
    // Dados para o card principal (PM + BM + OUTROS) com Ativa/Reserva/Pensionista
    $stats['corporacoes_principais'] = [
        'pm_quantidade' => $pm_count,
        'bm_quantidade' => $bm_count,
        'outros_quantidade' => $outros_count,
        'total_quantidade' => $total_corporacoes,
        'pm_ativa' => $pm_ativa,
        'pm_reserva' => $pm_reserva,
        'pm_pensionista' => $pm_pensionista,
        'bm_ativa' => $bm_ativa,
        'bm_reserva' => $bm_reserva,
        'bm_pensionista' => $bm_pensionista,
        'outros_ativa' => $outros_ativa,
        'outros_reserva' => $outros_reserva,
        'outros_pensionista' => $outros_pensionista,
        'pm_percentual' => $total_ativos > 0 ? round(($pm_count * 100) / $total_ativos, 1) : 0,
        'bm_percentual' => $total_ativos > 0 ? round(($bm_count * 100) / $total_ativos, 1) : 0,
        'outros_percentual' => $total_ativos > 0 ? round(($outros_count * 100) / $total_ativos, 1) : 0,
        'total_percentual' => $total_ativos > 0 ? round(($total_corporacoes * 100) / $total_ativos, 1) : 0
    ];
    
    // ===== 4. CAPITAL VS INTERIOR COM ATIVA/RESERVA/PENSIONISTA =====
    $stmt = $db->prepare("
        SELECT 
            -- Capital (Goiânia)
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') THEN a.id 
                ELSE NULL 
            END) as capital_total,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA' THEN a.id 
                ELSE NULL 
            END) as capital_ativa,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA' THEN a.id 
                ELSE NULL 
            END) as capital_reserva,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA' THEN a.id 
                ELSE NULL 
            END) as capital_pensionista,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND (m.id IS NULL OR TRIM(COALESCE(m.categoria, '')) = '' 
                     OR UPPER(TRIM(COALESCE(m.categoria, ''))) NOT IN ('ATIVA', 'RESERVA', 'PENSIONISTA')) THEN a.id 
                ELSE NULL 
            END) as capital_sem_categoria,
            
            -- Interior
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) NOT IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND e.cidade IS NOT NULL 
                AND TRIM(e.cidade) != '' THEN a.id 
                ELSE NULL 
            END) as interior_total,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) NOT IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND e.cidade IS NOT NULL 
                AND TRIM(e.cidade) != ''
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA' THEN a.id 
                ELSE NULL 
            END) as interior_ativa,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) NOT IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND e.cidade IS NOT NULL 
                AND TRIM(e.cidade) != ''
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA' THEN a.id 
                ELSE NULL 
            END) as interior_reserva,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) NOT IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND e.cidade IS NOT NULL 
                AND TRIM(e.cidade) != ''
                AND UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA' THEN a.id 
                ELSE NULL 
            END) as interior_pensionista,
            
            COUNT(DISTINCT CASE 
                WHEN UPPER(TRIM(e.cidade)) NOT IN ('GOIÂNIA', 'GOIANIA-GO') 
                AND e.cidade IS NOT NULL 
                AND TRIM(e.cidade) != ''
                AND (m.id IS NULL OR TRIM(COALESCE(m.categoria, '')) = '' 
                     OR UPPER(TRIM(COALESCE(m.categoria, ''))) NOT IN ('ATIVA', 'RESERVA', 'PENSIONISTA')) THEN a.id 
                ELSE NULL 
            END) as interior_sem_categoria,
            
            -- Sem endereço ou sem cidade
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
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $localizacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['capital'] = $localizacao['capital_total'] ?? 0;
    $stats['capital_ativa'] = $localizacao['capital_ativa'] ?? 0;
    $stats['capital_reserva'] = $localizacao['capital_reserva'] ?? 0;
    $stats['capital_pensionista'] = $localizacao['capital_pensionista'] ?? 0;
    $stats['capital_sem_categoria'] = $localizacao['capital_sem_categoria'] ?? 0;
    
    $stats['interior'] = $localizacao['interior_total'] ?? 0;
    $stats['interior_ativa'] = $localizacao['interior_ativa'] ?? 0;
    $stats['interior_reserva'] = $localizacao['interior_reserva'] ?? 0;
    $stats['interior_pensionista'] = $localizacao['interior_pensionista'] ?? 0;
    $stats['interior_sem_categoria'] = $localizacao['interior_sem_categoria'] ?? 0;
    
    $stats['sem_endereco'] = $localizacao['sem_endereco'] ?? 0;
    $stats['endereco_sem_cidade'] = $localizacao['endereco_sem_cidade'] ?? 0;
    $stats['total_localizacao'] = $stats['capital'] + $stats['interior'];
    $stats['total_geral_endereco'] = $localizacao['total_geral'] ?? 0;
    
    // Para o dashboard, incluir os "sem cidade" no interior ou criar categoria "Não informado"
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
    
    // ===== 5. ASSOCIADOS POR CORPORAÇÃO (MANTÉM PARA COMPATIBILIDADE) =====
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
    
    // Mantém compatibilidade (pega a maior individual para fallback)
    if (!empty($stats['por_corporacao'])) {
        $stats['corporacao_principal'] = $stats['por_corporacao'][0];
    } else {
        $stats['corporacao_principal'] = ['corporacao' => 'N/A', 'quantidade' => 0, 'percentual' => 0];
    }
    
    // ===== 6. ANIVERSARIANTES DO DIA - APENAS ATIVOS =====
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
    
    // ===== 7. LISTA DETALHADA DOS ANIVERSARIANTES - APENAS ATIVOS, SEM DUPLICAÇÃO =====
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
            // Associados com ativa/reserva/pensionista
            'associados_ativos' => 0,
            'associados_ativa' => 0,
            'associados_reserva' => 0,
            'associados_pensionista' => 0,
            'associados_sem_categoria' => 0,
            
            // Novos com ativa/reserva/pensionista
            'novos_associados' => 0,
            'novos_ativa' => 0,
            'novos_reserva' => 0,
            'novos_pensionista' => 0,
            'novos_sem_categoria' => 0,
            
            // Corporações (com pensionista)
            'por_corporacao' => [],
            'corporacao_principal' => ['corporacao' => 'N/A', 'quantidade' => 0, 'percentual' => 0],
            'corporacoes_principais' => [
                'pm_quantidade' => 0,
                'bm_quantidade' => 0,
                'outros_quantidade' => 0,
                'total_quantidade' => 0,
                'pm_ativa' => 0,
                'pm_reserva' => 0,
                'pm_pensionista' => 0,
                'bm_ativa' => 0,
                'bm_reserva' => 0,
                'bm_pensionista' => 0,
                'outros_ativa' => 0,
                'outros_reserva' => 0,
                'outros_pensionista' => 0,
                'pm_percentual' => 0,
                'bm_percentual' => 0,
                'outros_percentual' => 0,
                'total_percentual' => 0
            ],
            
            // Capital/Interior com ativa/reserva/pensionista
            'capital' => 0,
            'capital_ativa' => 0,
            'capital_reserva' => 0,
            'capital_pensionista' => 0,
            'capital_sem_categoria' => 0,
            'interior' => 0,
            'interior_ativa' => 0,
            'interior_reserva' => 0,
            'interior_pensionista' => 0,
            'interior_sem_categoria' => 0,
            'capital_percentual' => 0,
            'interior_percentual' => 0,
            'nao_informado' => 0,
            'nao_informado_percentual' => 0,
            
            // Outros dados
            'aniversariantes_hoje' => 0,
            'aniversariantes_detalhes' => []
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>