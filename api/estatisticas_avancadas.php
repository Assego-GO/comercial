<?php
/**
 * API de Estatísticas Avançadas Melhorada - Sistema ASSEGO
 * api/estatisticas_avancadas.php
 * Baseada na API original mas otimizada para nova interface
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

error_log("=== ESTATÍSTICAS API MELHORADA ===");

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    error_log("Conexão com banco estabelecida");

    $stats = [];
    
    // ===== 1. ESTATÍSTICAS GERAIS =====
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT a.id) as total_ativos,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA' THEN a.id END) as ativos_ativa,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA' THEN a.id END) as ativos_reserva,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA' THEN a.id END) as ativos_pensionista
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
    ");
    $stmt->execute();
    $geral = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['resumo_geral'] = [
        'total_associados' => $geral['total_ativos'] ?? 0,
        'ativa' => $geral['ativos_ativa'] ?? 0,
        'reserva' => $geral['ativos_reserva'] ?? 0,
        'pensionista' => $geral['ativos_pensionista'] ?? 0
    ];

    // ===== 2. ANÁLISE DE DOADORAS DE SANGUE =====
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN f.doador IS NULL OR TRIM(f.doador) = '' THEN 'Não Informado'
                WHEN UPPER(TRIM(f.doador)) IN ('SIM', 'S', '1', 'TRUE', 'VERDADEIRO') THEN 'Doador'
                ELSE 'Não Doador'
            END as status_doador,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Financeiro f ON a.id = f.associado_id 
        WHERE a.situacao = 'Filiado'
        GROUP BY CASE 
            WHEN f.doador IS NULL OR TRIM(f.doador) = '' THEN 'Não Informado'
            WHEN UPPER(TRIM(f.doador)) IN ('SIM', 'S', '1', 'TRUE', 'VERDADEIRO') THEN 'Doador'
            ELSE 'Não Doador'
        END
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['doadoras_sangue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== 3. SITUAÇÃO FINANCEIRA =====
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN f.situacaoFinanceira IS NULL OR TRIM(f.situacaoFinanceira) = '' THEN 'Não Informado'
                ELSE TRIM(f.situacaoFinanceira)
            END as situacao_financeira,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Financeiro f ON a.id = f.associado_id 
        WHERE a.situacao = 'Filiado'
        GROUP BY CASE 
            WHEN f.situacaoFinanceira IS NULL OR TRIM(f.situacaoFinanceira) = '' THEN 'Não Informado'
            ELSE TRIM(f.situacaoFinanceira)
        END
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['situacao_financeira'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== 4. TOP BAIRROS DE GOIÂNIA (para gráfico) =====
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN e.bairro IS NULL OR TRIM(e.bairro) = '' THEN 'Não Informado'
                ELSE UPPER(TRIM(e.bairro))
            END as bairro,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                LEFT JOIN Endereco e2 ON a2.id = e2.associado_id
                WHERE a2.situacao = 'Filiado' 
                AND UPPER(TRIM(COALESCE(e2.cidade, ''))) LIKE '%GOIÂNIA%'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Endereco e ON a.id = e.associado_id 
        WHERE a.situacao = 'Filiado'
        AND UPPER(TRIM(COALESCE(e.cidade, ''))) LIKE '%GOIÂNIA%'
        GROUP BY CASE 
            WHEN e.bairro IS NULL OR TRIM(e.bairro) = '' THEN 'Não Informado'
            ELSE UPPER(TRIM(e.bairro))
        END
        HAVING quantidade >= 5
        ORDER BY quantidade DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['bairros_goiania'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== 5. TODOS OS BAIRROS DE GOIÂNIA (para modal) =====
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN e.bairro IS NULL OR TRIM(e.bairro) = '' THEN 'Não Informado'
                ELSE UPPER(TRIM(e.bairro))
            END as bairro,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                LEFT JOIN Endereco e2 ON a2.id = e2.associado_id
                WHERE a2.situacao = 'Filiado' 
                AND UPPER(TRIM(COALESCE(e2.cidade, ''))) LIKE '%GOIÂNIA%'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Endereco e ON a.id = e.associado_id 
        WHERE a.situacao = 'Filiado'
        AND UPPER(TRIM(COALESCE(e.cidade, ''))) LIKE '%GOIÂNIA%'
        GROUP BY CASE 
            WHEN e.bairro IS NULL OR TRIM(e.bairro) = '' THEN 'Não Informado'
            ELSE UPPER(TRIM(e.bairro))
        END
        HAVING quantidade >= 1
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['todos_bairros'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== 6. ANÁLISE DE VÍNCULO SERVIDOR =====
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN f.vinculoServidor IS NULL OR TRIM(f.vinculoServidor) = '' THEN 'Não Informado'
                ELSE TRIM(f.vinculoServidor)
            END as vinculo_servidor,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Financeiro f ON a.id = f.associado_id 
        WHERE a.situacao = 'Filiado'
        GROUP BY CASE 
            WHEN f.vinculoServidor IS NULL OR TRIM(f.vinculoServidor) = '' THEN 'Não Informado'
            ELSE TRIM(f.vinculoServidor)
        END
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['vinculo_servidor'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== DADOS ORIGINAIS MANTIDOS =====
    
    // Patentes
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN m.patente IS NULL OR TRIM(m.patente) = '' THEN 'Não Informado'
                ELSE TRIM(m.patente)
            END as patente,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado' 
        GROUP BY CASE 
            WHEN m.patente IS NULL OR TRIM(m.patente) = '' THEN 'Não Informado'
            ELSE TRIM(m.patente)
        END
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['por_patente'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Corporações
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                THEN 'Polícia Militar'
                
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                THEN 'Bombeiros Militar'
                
                WHEN m.corporacao IS NULL OR TRIM(m.corporacao) = '' 
                THEN 'Não Informado'
                
                ELSE 'Outras Corporações'
            END as corporacao_grupo,
            COUNT(DISTINCT a.id) as quantidade,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'ATIVA' THEN a.id END) as ativa,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'RESERVA' THEN a.id END) as reserva,
            COUNT(DISTINCT CASE WHEN UPPER(TRIM(COALESCE(m.categoria, ''))) = 'PENSIONISTA' THEN a.id END) as pensionista
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
        GROUP BY 
            CASE 
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                THEN 'Polícia Militar'
                
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                THEN 'Bombeiros Militar'
                
                WHEN m.corporacao IS NULL OR TRIM(m.corporacao) = '' 
                THEN 'Não Informado'
                
                ELSE 'Outras Corporações'
            END
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['por_corporacao_detalhada'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lotações
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN m.lotacao IS NULL OR TRIM(m.lotacao) = '' THEN 'Não Informado'
                ELSE TRIM(m.lotacao)
            END as lotacao,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
        GROUP BY CASE 
            WHEN m.lotacao IS NULL OR TRIM(m.lotacao) = '' THEN 'Não Informado'
            ELSE TRIM(m.lotacao)
        END
        HAVING quantidade >= 5
        ORDER BY quantidade DESC
        LIMIT 20
    ");
    $stmt->execute();
    $stats['por_lotacao'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Patentes por Corporação
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                THEN 'PM'
                
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                THEN 'BM'
                
                ELSE 'Outros'
            END as corporacao,
            CASE 
                WHEN m.patente IS NULL OR TRIM(m.patente) = '' THEN 'Não Informado'
                ELSE TRIM(m.patente)
            END as patente,
            COUNT(DISTINCT a.id) as quantidade
        FROM Associados a 
        LEFT JOIN Militar m ON a.id = m.associado_id 
        WHERE a.situacao = 'Filiado'
        GROUP BY 
            CASE 
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLICIA MILITAR%' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%POLÍCIA MILITAR%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%PM %' 
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'PM'
                THEN 'PM'
                
                WHEN UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BOMBEIRO%'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%BM %'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) = 'BM'
                OR UPPER(TRIM(COALESCE(m.corporacao, ''))) LIKE '%CORPO DE BOMBEIRO%'
                THEN 'BM'
                
                ELSE 'Outros'
            END,
            CASE 
                WHEN m.patente IS NULL OR TRIM(m.patente) = '' THEN 'Não Informado'
                ELSE TRIM(m.patente)
            END
        HAVING quantidade >= 2
        ORDER BY corporacao, quantidade DESC
    ");
    $stmt->execute();
    $patentes_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats['patentes_por_corporacao'] = [
        'PM' => [],
        'BM' => [],
        'Outros' => []
    ];
    
    foreach ($patentes_raw as $row) {
        $stats['patentes_por_corporacao'][$row['corporacao']][] = [
            'patente' => $row['patente'],
            'quantidade' => $row['quantidade']
        ];
    }

    // Cidades
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') THEN 'Goiânia'
                WHEN e.cidade IS NULL OR TRIM(e.cidade) = '' THEN 'Não Informado'
                ELSE TRIM(e.cidade)
            END as cidade,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 2) as percentual
        FROM Associados a 
        LEFT JOIN Endereco e ON a.id = e.associado_id 
        WHERE a.situacao = 'Filiado'
        GROUP BY CASE 
            WHEN UPPER(TRIM(e.cidade)) IN ('GOIÂNIA', 'GOIANIA-GO') THEN 'Goiânia'
            WHEN e.cidade IS NULL OR TRIM(e.cidade) = '' THEN 'Não Informado'
            ELSE TRIM(e.cidade)
        END
        HAVING quantidade >= 10
        ORDER BY quantidade DESC
        LIMIT 15
    ");
    $stmt->execute();
    $stats['por_cidade'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Faixa Etária
    $stmt = $db->prepare("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) IS NULL THEN 'Não Informado'
                WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 30 THEN '18-29 anos'
                WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 40 THEN '30-39 anos'
                WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 50 THEN '40-49 anos'
                WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 60 THEN '50-59 anos'
                WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 70 THEN '60-69 anos'
                ELSE '70+ anos'
            END as faixa_etaria,
            COUNT(DISTINCT a.id) as quantidade,
            ROUND((COUNT(DISTINCT a.id) * 100.0 / (
                SELECT COUNT(DISTINCT a2.id) 
                FROM Associados a2 
                WHERE a2.situacao = 'Filiado'
            )), 2) as percentual
        FROM Associados a 
        WHERE a.situacao = 'Filiado'
        GROUP BY CASE 
            WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) IS NULL THEN 'Não Informado'
            WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 30 THEN '18-29 anos'
            WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 40 THEN '30-39 anos'
            WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 50 THEN '40-49 anos'
            WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 60 THEN '50-59 anos'
            WHEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE()) < 70 THEN '60-69 anos'
            ELSE '70+ anos'
        END
        ORDER BY quantidade DESC
    ");
    $stmt->execute();
    $stats['por_faixa_etaria'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Todas as estatísticas otimizadas calculadas com sucesso");
    error_log("Total de bairros encontrados: " . count($stats['todos_bairros']));

    // Retorna sucesso
    echo json_encode([
        'status' => 'success',
        'data' => $stats,
        'total_queries' => 10, // Número de consultas realizadas (removidas 3)
        'gerado_em' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao buscar estatísticas avançadas',
        'details' => $e->getMessage()
    ]);
}
?>