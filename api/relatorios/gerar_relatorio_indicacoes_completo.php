<?php
/**
 * API para gerar relatório de indicações COMPLETO
 * api/relatorios/gerar_relatorio_indicacoes_completo.php
 * 
 * @version 2.0 - Com detalhes dos associados indicados
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação e permissão
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

if (!Permissoes::tem('COMERCIAL_RELATORIOS')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Parâmetros
    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
    $corporacao = $_GET['corporacao'] ?? '';
    $patente = $_GET['patente'] ?? '';
    $busca = $_GET['busca'] ?? '';
    $tipoRelatorio = $_GET['sub_tipo'] ?? 'ranking'; // 'ranking' ou 'detalhado'
    
    // Paginação
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $registrosPorPagina = min(100, intval($_GET['registros_por_pagina'] ?? 50));
    $offset = ($pagina - 1) * $registrosPorPagina;
    
    $data = [];
    $totalRegistros = 0;
    
    if ($tipoRelatorio === 'ranking') {
        // RELATÓRIO DE RANKING DE INDICADORES
        $sql = "
            SELECT 
                i.id,
                i.nome_completo as indicador_nome,
                COALESCE(i.patente, '') as indicador_patente,
                COALESCE(i.corporacao, '') as indicador_corporacao,
                COALESCE(i.total_indicacoes, 0) as total_geral,
                -- Indicações no período
                (SELECT COUNT(DISTINCT hi.associado_id) 
                 FROM Historico_Indicacoes hi 
                 WHERE hi.indicador_id = i.id 
                 AND DATE(hi.data_indicacao) BETWEEN :data_inicio1 AND :data_fim1) as indicacoes_periodo,
                -- Última indicação
                (SELECT MAX(hi.data_indicacao)
                 FROM Historico_Indicacoes hi
                 WHERE hi.indicador_id = i.id) as ultima_indicacao,
                -- Lista dos últimos indicados
                (SELECT GROUP_CONCAT(
                    DISTINCT CONCAT(a.nome, ' (', DATE_FORMAT(hi.data_indicacao, '%d/%m/%Y'), ')')
                    ORDER BY hi.data_indicacao DESC
                    SEPARATOR '; '
                 )
                 FROM Historico_Indicacoes hi
                 JOIN Associados a ON hi.associado_id = a.id
                 WHERE hi.indicador_id = i.id
                 AND DATE(hi.data_indicacao) BETWEEN :data_inicio2 AND :data_fim2
                 LIMIT 5) as ultimos_indicados
            FROM Indicadores i
            WHERE i.ativo = 1
        ";
        
        $params = [
            ':data_inicio1' => $dataInicio,
            ':data_fim1' => $dataFim,
            ':data_inicio2' => $dataInicio,
            ':data_fim2' => $dataFim
        ];
        
        // Filtros
        if (!empty($corporacao)) {
            $sql .= " AND LOWER(TRIM(i.corporacao)) = LOWER(TRIM(:corporacao))";
            $params[':corporacao'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $sql .= " AND i.patente LIKE :patente";
            $params[':patente'] = "%$patente%";
        }
        
        if (!empty($busca)) {
            $sql .= " AND i.nome_completo LIKE :busca";
            $params[':busca'] = "%$busca%";
        }
        
        // Contar total
        $sqlCount = "SELECT COUNT(*) as total FROM Indicadores i WHERE i.ativo = 1";
        if (!empty($corporacao)) {
            $sqlCount .= " AND LOWER(TRIM(i.corporacao)) = LOWER(TRIM(:corporacao))";
        }
        if (!empty($patente)) {
            $sqlCount .= " AND i.patente LIKE :patente";
        }
        if (!empty($busca)) {
            $sqlCount .= " AND i.nome_completo LIKE :busca";
        }
        
        $stmtCount = $db->prepare($sqlCount);
        foreach ($params as $key => $value) {
            if (strpos($sqlCount, $key) !== false) {
                $stmtCount->bindValue($key, $value);
            }
        }
        $stmtCount->execute();
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ordenação e paginação
        $sql .= " ORDER BY indicacoes_periodo DESC, i.total_indicacoes DESC, i.nome_completo ASC";
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // RELATÓRIO DETALHADO DE INDICAÇÕES
        $sql = "
            SELECT 
                hi.id,
                hi.data_indicacao,
                -- Dados do Indicador
                COALESCE(i.nome_completo, hi.indicador_nome) as indicador_nome,
                i.patente as indicador_patente,
                i.corporacao as indicador_corporacao,
                -- Dados do Associado Indicado
                a.id as associado_id,
                a.nome as associado_nome,
                a.cpf as associado_cpf,
                a.telefone as associado_telefone,
                a.email as associado_email,
                m.patente as associado_patente,
                m.corporacao as associado_corporacao,
                m.lotacao as associado_lotacao,
                -- Data de filiação do associado
                CASE 
                    WHEN c.dataFiliacao IS NOT NULL AND c.dataFiliacao != '0000-00-00'
                    THEN c.dataFiliacao
                    WHEN a.data_aprovacao IS NOT NULL
                    THEN DATE(a.data_aprovacao)
                    ELSE NULL
                END as data_filiacao,
                -- Funcionário que registrou
                f.nome as registrado_por,
                hi.observacao
            FROM Historico_Indicacoes hi
            JOIN Associados a ON hi.associado_id = a.id
            LEFT JOIN Indicadores i ON hi.indicador_id = i.id
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            LEFT JOIN Funcionarios f ON hi.funcionario_id = f.id
            WHERE DATE(hi.data_indicacao) BETWEEN :data_inicio AND :data_fim
        ";
        
        $params = [
            ':data_inicio' => $dataInicio,
            ':data_fim' => $dataFim
        ];
        
        // Filtros
        if (!empty($corporacao)) {
            $sql .= " AND (LOWER(TRIM(i.corporacao)) = LOWER(TRIM(:corporacao)) 
                      OR LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao2)))";
            $params[':corporacao'] = $corporacao;
            $params[':corporacao2'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $sql .= " AND (i.patente LIKE :patente OR m.patente LIKE :patente2)";
            $params[':patente'] = "%$patente%";
            $params[':patente2'] = "%$patente%";
        }
        
        if (!empty($busca)) {
            $sql .= " AND (
                COALESCE(i.nome_completo, hi.indicador_nome) LIKE :busca1
                OR a.nome LIKE :busca2
                OR a.cpf LIKE :busca3
            )";
            $params[':busca1'] = "%$busca%";
            $params[':busca2'] = "%$busca%";
            $params[':busca3'] = "%$busca%";
        }
        
        // Contar total
        $sqlCount = str_replace('SELECT hi.id,', 'SELECT COUNT(*) as total FROM (SELECT hi.id', $sql) . ') as temp';
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ordenação e paginação
        $sql .= " ORDER BY hi.data_indicacao DESC, hi.id DESC";
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Formatar dados
    foreach ($data as &$row) {
        // Formatar datas
        if (isset($row['data_indicacao']) && $row['data_indicacao']) {
            $row['data_indicacao_formatada'] = date('d/m/Y', strtotime($row['data_indicacao']));
        }
        if (isset($row['ultima_indicacao']) && $row['ultima_indicacao']) {
            $row['ultima_indicacao_formatada'] = date('d/m/Y', strtotime($row['ultima_indicacao']));
        }
        if (isset($row['data_filiacao']) && $row['data_filiacao']) {
            $row['data_filiacao_formatada'] = date('d/m/Y', strtotime($row['data_filiacao']));
        }
        
        // Formatar CPF
        if (isset($row['associado_cpf']) && $row['associado_cpf']) {
            $cpf = preg_replace('/\D/', '', $row['associado_cpf']);
            if (strlen($cpf) === 11) {
                $row['associado_cpf_formatado'] = substr($cpf, 0, 3) . '.' . 
                    substr($cpf, 3, 3) . '.' . 
                    substr($cpf, 6, 3) . '-' . 
                    substr($cpf, 9, 2);
            }
        }
        
        // Formatar telefone
        if (isset($row['associado_telefone']) && $row['associado_telefone']) {
            $tel = preg_replace('/\D/', '', $row['associado_telefone']);
            if (strlen($tel) === 11) {
                $row['associado_telefone_formatado'] = '(' . substr($tel, 0, 2) . ') ' . 
                    substr($tel, 2, 5) . '-' . substr($tel, 7);
            }
        }
        
        // Garantir valores não nulos
        $row['indicador_patente'] = $row['indicador_patente'] ?? '';
        $row['indicador_corporacao'] = $row['indicador_corporacao'] ?? '';
        $row['associado_patente'] = $row['associado_patente'] ?? '';
        $row['associado_corporacao'] = $row['associado_corporacao'] ?? '';
        $row['associado_lotacao'] = $row['associado_lotacao'] ?? '';
        $row['registrado_por'] = $row['registrado_por'] ?? 'Sistema';
        $row['observacao'] = $row['observacao'] ?? '';
        
        // Adicionar valores padrão para campos numéricos
        if (isset($row['total_geral'])) {
            $row['total_geral'] = max(0, intval($row['total_geral']));
        }
        if (isset($row['indicacoes_periodo'])) {
            $row['indicacoes_periodo'] = max(0, intval($row['indicacoes_periodo']));
        }
    }
    
    // Calcular paginação
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
    
    // Resposta
    echo json_encode([
        'success' => true,
        'tipo_relatorio' => $tipoRelatorio,
        'data' => $data,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'registros_por_pagina' => $registrosPorPagina,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'registros_inicio' => ($pagina - 1) * $registrosPorPagina + 1,
            'registros_fim' => min($pagina * $registrosPorPagina, $totalRegistros)
        ],
        'filtros' => [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'corporacao' => $corporacao,
            'patente' => $patente,
            'busca' => $busca
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Erro no relatório de indicações: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao gerar relatório: ' . $e->getMessage()
    ]);
}
?>