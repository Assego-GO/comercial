<?php
/**
 * Função para gerar relatório de aniversariantes
 * Adicione esta função ao seu arquivo relatorios_executar.php existente
 */

function gerarRelatorioAniversariantes($parametros) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Construir a query base
        $sql = "
            SELECT 
                a.id,
                a.nome,
                DATE_FORMAT(a.nasc, '%d/%m/%Y') as data_nascimento,
                a.nasc,
                YEAR(CURDATE()) - YEAR(a.nasc) - (DATE_FORMAT(CURDATE(), '%m%d') < DATE_FORMAT(a.nasc, '%m%d')) as idade,
                a.telefone,
                a.email,
                a.situacao,
                m.corporacao,
                m.patente,
                m.categoria,
                m.unidade,
                -- Calcular dias até o próximo aniversário
                CASE 
                    WHEN DATE_FORMAT(a.nasc, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') THEN 0
                    WHEN DATE_FORMAT(a.nasc, '%m-%d') > DATE_FORMAT(CURDATE(), '%m-%d') THEN 
                        DATEDIFF(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(a.nasc, '%m-%d')), '%Y-%m-%d'), CURDATE())
                    ELSE 
                        DATEDIFF(STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', DATE_FORMAT(a.nasc, '%m-%d')), '%Y-%m-%d'), CURDATE())
                END as dias_ate_aniversario,
                -- Próximo aniversário
                CASE 
                    WHEN DATE_FORMAT(a.nasc, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d') THEN 
                        DATE_FORMAT(STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(a.nasc, '%m-%d')), '%Y-%m-%d'), '%d/%m/%Y')
                    ELSE 
                        DATE_FORMAT(STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', DATE_FORMAT(a.nasc, '%m-%d')), '%Y-%m-%d'), '%d/%m/%Y')
                END as proximo_aniversario
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE a.situacao = 'Filiado' 
            AND a.nasc IS NOT NULL
        ";
        
        $params = [];
        $conditions = [];
        
        // Filtro por período de aniversário
        $periodo = $parametros['periodo_aniversario'] ?? 'hoje';
        
        switch($periodo) {
            case 'hoje':
                $conditions[] = "DATE_FORMAT(a.nasc, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')";
                break;
                
            case 'semana':
                $conditions[] = "
                    (
                        (DATE_FORMAT(a.nasc, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') 
                         AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d'))
                        OR 
                        (DATE_FORMAT(CURDATE(), '%m') = '12' AND DATE_FORMAT(a.nasc, '%m') = '01' 
                         AND DATE_FORMAT(a.nasc, '%m-%d') <= DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d'))
                    )
                ";
                break;
                
            case 'mes':
                $conditions[] = "DATE_FORMAT(a.nasc, '%m') = DATE_FORMAT(CURDATE(), '%m')";
                break;
                
            case 'customizado':
                if (!empty($parametros['data_inicio']) && !empty($parametros['data_fim'])) {
                    $conditions[] = "DATE_FORMAT(a.nasc, '%m-%d') BETWEEN ? AND ?";
                    $params[] = date('m-d', strtotime($parametros['data_inicio']));
                    $params[] = date('m-d', strtotime($parametros['data_fim']));
                }
                break;
        }
        
        // Filtro por corporação
        if (!empty($parametros['corporacao'])) {
            $conditions[] = "m.corporacao = ?";
            $params[] = $parametros['corporacao'];
        }
        
        // Filtro por situação (sempre ativo por padrão, mas permite override)
        if (isset($parametros['situacao']) && $parametros['situacao'] !== '') {
            // Remove a condição padrão se um filtro específico foi aplicado
            $sql = str_replace("AND a.situacao = 'Filiado'", "", $sql);
            $conditions[] = "a.situacao = ?";
            $params[] = $parametros['situacao'];
        }
        
        // Aplicar condições adicionais
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        // Ordenação
        $ordenacao = $parametros['ordenacao'] ?? '';
        switch($ordenacao) {
            case 'aniversario_asc':
                $sql .= " ORDER BY DATE_FORMAT(a.nasc, '%m-%d') ASC, a.nome ASC";
                break;
            case 'idade_desc':
                $sql .= " ORDER BY idade DESC, a.nome ASC";
                break;
            case 'nome_asc':
                $sql .= " ORDER BY a.nome ASC";
                break;
            default:
                // Para aniversariantes, ordem padrão é por proximidade do aniversário
                if ($periodo === 'hoje') {
                    $sql .= " ORDER BY a.nome ASC";
                } else {
                    $sql .= " ORDER BY dias_ate_aniversario ASC, a.nome ASC";
                }
        }
        
        // Executar query
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filtrar campos conforme solicitado
        $campos = $parametros['campos'] ?? [
            'nome', 'data_nascimento', 'idade', 'telefone', 'email', 'corporacao', 'patente'
        ];
        
        $dadosFiltrados = [];
        foreach ($resultados as $linha) {
            $linhafiltrada = [];
            foreach ($campos as $campo) {
                $linhafiltrada[$campo] = $linha[$campo] ?? '';
            }
            $dadosFiltrados[] = $linhafiltrada;
        }
        
        // Informações do relatório
        $info = [
            'titulo' => 'Relatório de Aniversariantes',
            'subtitulo' => getSubtituloAniversariantes($periodo, count($dadosFiltrados)),
            'data_geracao' => date('d/m/Y H:i'),
            'total_registros' => count($dadosFiltrados),
            'parametros' => $parametros
        ];
        
        return [
            'dados' => $dadosFiltrados,
            'info' => $info,
            'campos' => $campos
        ];
        
    } catch (Exception $e) {
        error_log("Erro no relatório de aniversariantes: " . $e->getMessage());
        throw new Exception("Erro ao gerar relatório de aniversariantes: " . $e->getMessage());
    }
}

function getSubtituloAniversariantes($periodo, $total) {
    $hoje = date('d/m/Y');
    
    switch($periodo) {
        case 'hoje':
            return "Aniversariantes do dia {$hoje} • {$total} pessoa(s)";
        case 'semana':
            $fimSemana = date('d/m/Y', strtotime('+7 days'));
            return "Aniversariantes de {$hoje} até {$fimSemana} • {$total} pessoa(s)";
        case 'mes':
            $mesAtual = strftime('%B de %Y', time());
            return "Aniversariantes de {$mesAtual} • {$total} pessoa(s)";
        default:
            return "Aniversariantes • {$total} pessoa(s)";
    }
}

// Adicionar este case no switch principal do seu relatorios_executar.php:
/*
case 'aniversariantes':
    $resultado = gerarRelatorioAniversariantes($parametros);
    break;
*/