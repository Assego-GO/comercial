<?php
/**
 * API para gerar relatórios comerciais - VERSÃO 8.0
 * api/relatorios/gerar_relatorio_comercial.php
 * 
 * @author Sistema ASSEGO
 * @version 8.0 - Com normalização de dados e tratamento inteligente de filtros
 */

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Desabilitar xdebug se existir
if (function_exists('xdebug_disable')) {
    xdebug_disable();
}

// Buffer de saída limpo
ob_start();
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

// Header JSON
header('Content-Type: application/json; charset=utf-8');

// Função para log detalhado
function logDebug($message, $data = null) {
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log("[DEBUG] " . $message . ($data ? " - " . json_encode($data) : ""));
    }
}

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Verificar permissão
if (!Permissoes::tem('COMERCIAL_RELATORIOS')) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para acessar relatórios']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Configurar o MySQL para aceitar datas zero temporariamente
    try {
        $db->exec("SET sql_mode = ''");
        $db->exec("SET SESSION sql_mode = ''");
    } catch (PDOException $e) {
        logDebug("Não foi possível alterar sql_mode", ['erro' => $e->getMessage()]);
    }
    
    // Configurar PDO
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Pegar e validar parâmetros
    $tipo = strip_tags(trim($_GET['tipo'] ?? 'desfiliacoes'));
    $dataInicio = strip_tags(trim($_GET['data_inicio'] ?? date('Y-m-01')));
    $dataFim = strip_tags(trim($_GET['data_fim'] ?? date('Y-m-d')));
    $corporacao = strip_tags(trim($_GET['corporacao'] ?? ''));
    $patente = strip_tags(trim($_GET['patente'] ?? ''));
    $lotacao = strip_tags(trim($_GET['lotacao'] ?? ''));
    $ordenacao = strip_tags(trim($_GET['ordenacao'] ?? 'nome'));
    $busca = strip_tags(trim($_GET['busca'] ?? ''));
    
    // Parâmetros de paginação
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $registrosPorPagina = max(10, min(100, intval($_GET['registros_por_pagina'] ?? 50)));
    
    logDebug("Parâmetros recebidos", $_GET);
    
    // Validar tipo de relatório
    $tiposValidos = ['desfiliacoes', 'indicacoes', 'aniversariantes', 'novos_cadastros', 'estatisticas'];
    if (!in_array($tipo, $tiposValidos)) {
        throw new Exception('Tipo de relatório inválido: ' . $tipo);
    }
    
    // Validar datas
    if (!validateDate($dataInicio) || !validateDate($dataFim)) {
        throw new Exception('Datas inválidas. Use o formato AAAA-MM-DD');
    }
    
    if (strtotime($dataInicio) > strtotime($dataFim)) {
        throw new Exception('Data inicial não pode ser maior que a data final');
    }
    
    // Executar relatório baseado no tipo
    $data = [];
    $totalRegistros = 0;
    
    switch ($tipo) {
        case 'desfiliacoes':
            $resultado = gerarRelatorioDesfiliacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $pagina, $registrosPorPagina);
            $data = $resultado['data'];
            $totalRegistros = $resultado['total'];
            break;
            
        case 'indicacoes':
            $resultado = gerarRelatorioIndicacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca, $ordenacao, $pagina, $registrosPorPagina);
            $data = $resultado['data'];
            $totalRegistros = $resultado['total'];
            break;
            
        case 'aniversariantes':
            $resultado = gerarRelatorioAniversariantes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $pagina, $registrosPorPagina);
            $data = $resultado['data'];
            $totalRegistros = $resultado['total'];
            break;
            
        case 'novos_cadastros':
            $resultado = gerarRelatorioNovosCadastros($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $pagina, $registrosPorPagina);
            $data = $resultado['data'];
            $totalRegistros = $resultado['total'];
            break;
            
        case 'estatisticas':
            // Estatísticas não precisam de paginação
            $data = gerarRelatorioEstatisticas($db, $dataInicio, $dataFim, $corporacao);
            $totalRegistros = count($data);
            break;
            
        default:
            throw new Exception('Tipo de relatório não implementado: ' . $tipo);
    }
    
    // Calcular informações de paginação
    $totalPaginas = ceil($totalRegistros / $registrosPorPagina);
    $paginaAtual = min($pagina, $totalPaginas);
    
    // Log de sucesso
    logDebug("Relatório gerado com sucesso", ['tipo' => $tipo, 'registros' => count($data), 'total' => $totalRegistros]);
    
    // Registrar log (opcional)
    try {
        registrarLogRelatorio($db, $tipo, count($data), $_GET);
    } catch (Exception $e) {
        logDebug("Erro ao registrar log", ['erro' => $e->getMessage()]);
    }
    
    // Limpar buffer e enviar resposta
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'paginacao' => [
            'pagina_atual' => $paginaAtual,
            'registros_por_pagina' => $registrosPorPagina,
            'total_registros' => $totalRegistros,
            'total_paginas' => $totalPaginas,
            'registros_inicio' => (($paginaAtual - 1) * $registrosPorPagina) + 1,
            'registros_fim' => min($paginaAtual * $registrosPorPagina, $totalRegistros)
        ],
        'filtros' => [
            'tipo' => $tipo,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'corporacao' => $corporacao,
            'patente' => $patente,
            'lotacao' => $lotacao
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    ob_clean();
    
    $errorMessage = 'Erro ao processar consulta no banco de dados';
    $debugInfo = [
        'erro' => $e->getMessage(),
        'codigo' => $e->getCode(),
        'linha' => $e->getLine(),
        'arquivo' => basename($e->getFile())
    ];
    
    error_log("Erro PDO no relatório: " . json_encode($debugInfo));
    
    // Em desenvolvimento, retornar erro detalhado
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $errorMessage .= ': ' . $e->getMessage();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $debugInfo : null
    ]);
    
} catch (Exception $e) {
    ob_clean();
    
    error_log("Erro no relatório: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
exit;

// ===== FUNÇÕES AUXILIARES =====

/**
 * Validar formato de data
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Função auxiliar para tratar datas inválidas
 */
function isValidMySQLDate($date) {
    if (empty($date)) return false;
    if ($date === '0000-00-00') return false;
    if ($date === '0000-00-00 00:00:00') return false;
    if ($date === '00:00:00') return false;
    if (strpos($date, '0000-00-00') !== false) return false;
    
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp < 0) return false;
    
    $year = date('Y', $timestamp);
    if ($year < 1970 || $year > 2100) return false;
    
    return true;
}

/**
 * Normalizar string para comparação
 * Remove acentos, espaços extras e converte para minúsculo
 */
function normalizeString($string) {
    $string = strtolower(trim($string));
    
    // Remove acentos
    $from = ['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ü', 'ç'];
    $to   = ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'c'];
    $string = str_replace($from, $to, $string);
    
    // Remove múltiplos espaços
    $string = preg_replace('/\s+/', ' ', $string);
    
    return $string;
}

/**
 * Normalizar patente para comparação
 * Trata variações com hífen e sem hífen
 */
function normalizePatente($patente) {
    $patente = normalizeString($patente);
    
    // Remove hífens e espaços
    $patente = str_replace(['-', ' '], '', $patente);
    
    // Padroniza algumas variações conhecidas
    $replacements = [
        'primeirosargento' => 'primeiro-sargento',
        'segundosargento' => 'segundo-sargento',
        'terceirosargento' => 'terceiro-sargento',
        'primeirotenente' => 'primeiro-tenente',
        'segundotenente' => 'segundo-tenente',
        'tenentecoronel' => 'tenente-coronel',
        'aspiranteaoficial' => 'aspirante-a-oficial',
        'soldado1classe' => 'soldado1classe',
        'soldado2classe' => 'soldado2classe'
    ];
    
    foreach ($replacements as $from => $to) {
        if (strpos($patente, $from) !== false) {
            return $to;
        }
    }
    
    return $patente;
}

/**
 * Relatório de Desfiliações - VERSÃO 8.0 COM NORMALIZAÇÃO
 */
function gerarRelatorioDesfiliacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $pagina = 1, $registrosPorPagina = 50) {
    try {
        // Query usando DISTINCT para evitar duplicatas
        $sql = "
            SELECT DISTINCT
                a.id,
                a.nome,
                a.rg,
                a.cpf,
                a.telefone,
                a.email,
                CASE 
                    WHEN c.dataDesfiliacao IS NOT NULL 
                         AND c.dataDesfiliacao != '0000-00-00'
                         AND YEAR(c.dataDesfiliacao) > 1970
                    THEN c.dataDesfiliacao
                    WHEN a.data_desfiliacao IS NOT NULL 
                         AND a.data_desfiliacao != '0000-00-00 00:00:00'
                         AND YEAR(a.data_desfiliacao) > 1970
                    THEN DATE(a.data_desfiliacao)
                    ELSE NULL
                END as data_desfiliacao,
                COALESCE(m.corporacao, '') as corporacao,
                COALESCE(m.patente, '') as patente,
                COALESCE(m.lotacao, '') as lotacao,
                COALESCE(m.unidade, '') as unidade
            FROM Associados a
            LEFT JOIN Contrato c ON a.id = c.associado_id
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE a.situacao IN ('DESFILIADO', 'Desfiliado', 'desfiliado')
        ";
        
        $params = [];
        
        // Filtro de data
        if (!empty($dataInicio) && !empty($dataFim)) {
            $sql .= " AND (
                (c.dataDesfiliacao IS NOT NULL 
                 AND c.dataDesfiliacao != '0000-00-00'
                 AND c.dataDesfiliacao BETWEEN :data_inicio1 AND :data_fim1)
                OR
                (c.dataDesfiliacao IS NULL 
                 AND a.data_desfiliacao IS NOT NULL 
                 AND a.data_desfiliacao != '0000-00-00 00:00:00'
                 AND DATE(a.data_desfiliacao) BETWEEN :data_inicio2 AND :data_fim2)
            )";
            
            $params[':data_inicio1'] = $dataInicio;
            $params[':data_fim1'] = $dataFim;
            $params[':data_inicio2'] = $dataInicio;
            $params[':data_fim2'] = $dataFim;
        }
        
        // Filtro de corporação - usando comparação exata mas case-insensitive
        if (!empty($corporacao)) {
            $sql .= " AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))";
            $params[':corporacao'] = $corporacao;
        }
        
        // Filtro de patente - tratamento especial para variações
        if (!empty($patente)) {
            // Normaliza a patente de busca
            $patenteNormalizada = normalizePatente($patente);
            
            // Busca com LIKE para pegar variações
            $sql .= " AND (
                LOWER(REPLACE(REPLACE(m.patente, '-', ''), ' ', '')) LIKE :patente_like
                OR m.patente = :patente_exact
            )";
            
            $params[':patente_like'] = '%' . str_replace('-', '', $patenteNormalizada) . '%';
            $params[':patente_exact'] = $patente;
        }
        
        // Filtro de lotação - usando LIKE para permitir busca parcial
        if (!empty($lotacao)) {
            $sql .= " AND (
                m.lotacao = :lotacao_exact
                OR m.lotacao LIKE :lotacao_like
            )";
            $params[':lotacao_exact'] = $lotacao;
            $params[':lotacao_like'] = "%$lotacao%";
        }
        
        // Filtro de busca geral
        if (!empty($busca)) {
            $sql .= " AND (
                a.nome LIKE :busca_nome 
                OR a.rg LIKE :busca_rg 
                OR a.cpf LIKE :busca_cpf
            )";
            $params[':busca_nome'] = "%$busca%";
            $params[':busca_rg'] = "%$busca%";
            $params[':busca_cpf'] = "%$busca%";
        }
        
        // Agregar para garantir registros únicos
        $sql .= " GROUP BY a.id";
        
        // Contar total de registros (sem paginação)
        $sqlCount = "SELECT COUNT(DISTINCT a.id) as total FROM Associados a
                     LEFT JOIN Contrato c ON a.id = c.associado_id
                     LEFT JOIN Militar m ON a.id = m.associado_id
                     WHERE a.situacao IN ('DESFILIADO', 'Desfiliado', 'desfiliado')";
        
        // Adicionar mesmos filtros ao count
        if (!empty($dataInicio) && !empty($dataFim)) {
            $sqlCount .= " AND (
                (c.dataDesfiliacao IS NOT NULL 
                 AND c.dataDesfiliacao != '0000-00-00'
                 AND c.dataDesfiliacao BETWEEN :data_inicio1 AND :data_fim1)
                OR
                (c.dataDesfiliacao IS NULL 
                 AND a.data_desfiliacao IS NOT NULL 
                 AND a.data_desfiliacao != '0000-00-00 00:00:00'
                 AND DATE(a.data_desfiliacao) BETWEEN :data_inicio2 AND :data_fim2)
            )";
        }
        
        if (!empty($corporacao)) {
            $sqlCount .= " AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))";
        }
        
        if (!empty($patente)) {
            $sqlCount .= " AND (
                LOWER(REPLACE(REPLACE(m.patente, '-', ''), ' ', '')) LIKE :patente_like
                OR m.patente = :patente_exact
            )";
        }
        
        if (!empty($lotacao)) {
            $sqlCount .= " AND (
                m.lotacao = :lotacao_exact
                OR m.lotacao LIKE :lotacao_like
            )";
        }
        
        if (!empty($busca)) {
            $sqlCount .= " AND (
                a.nome LIKE :busca_nome 
                OR a.rg LIKE :busca_rg 
                OR a.cpf LIKE :busca_cpf
            )";
        }
        
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ordenação
        switch ($ordenacao) {
            case 'data':
                $sql .= " ORDER BY data_desfiliacao DESC, a.nome ASC";
                break;
            case 'patente':
                $sql .= " ORDER BY m.patente ASC, a.nome ASC";
                break;
            case 'corporacao':
                $sql .= " ORDER BY m.corporacao ASC, a.nome ASC";
                break;
            default:
                $sql .= " ORDER BY a.nome ASC";
        }
        
        // Adicionar paginação
        $offset = ($pagina - 1) * $registrosPorPagina;
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $params[':limit'] = $registrosPorPagina;
        $params[':offset'] = $offset;
        
        logDebug("SQL Desfiliações", ['sql' => $sql, 'params' => $params]);
        
        $stmt = $db->prepare($sql);
        
        // Bind de parâmetros com tipos específicos
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Limpar datas inválidas
        foreach ($resultados as &$row) {
            if (!isValidMySQLDate($row['data_desfiliacao'])) {
                $row['data_desfiliacao'] = null;
            }
        }
        
        return [
            'data' => $resultados,
            'total' => $totalRegistros
        ];
        
    } catch (PDOException $e) {
        error_log("Erro SQL em gerarRelatorioDesfiliacoes: " . $e->getMessage());
        throw new Exception("Erro ao gerar relatório de desfiliações: " . $e->getMessage());
    }
}

/**
 * Relatório de Indicações - COM NORMALIZAÇÃO
 */
function gerarRelatorioIndicacoes($db, $dataInicio, $dataFim, $corporacao, $patente, $busca, $ordenacao, $pagina = 1, $registrosPorPagina = 50) {
    try {
        // Verificar se a tabela existe
        $checkTable = $db->query("SHOW TABLES LIKE 'Indicadores'");
        if ($checkTable->rowCount() == 0) {
            return ['data' => [], 'total' => 0];
        }
        
        $sql = "
            SELECT DISTINCT
                i.id,
                i.nome_completo as indicador,
                COALESCE(i.patente, '') as patente,
                COALESCE(i.corporacao, '') as corporacao,
                COALESCE(i.total_indicacoes, 0) as total_indicacoes
            FROM Indicadores i
            WHERE i.ativo = 1
        ";
        
        $params = [];
        
        if (!empty($corporacao)) {
            $sql .= " AND LOWER(TRIM(i.corporacao)) = LOWER(TRIM(:corporacao))";
            $params[':corporacao'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $patenteNormalizada = normalizePatente($patente);
            $sql .= " AND (
                LOWER(REPLACE(REPLACE(i.patente, '-', ''), ' ', '')) LIKE :patente_like
                OR i.patente = :patente_exact
            )";
            $params[':patente_like'] = '%' . str_replace('-', '', $patenteNormalizada) . '%';
            $params[':patente_exact'] = $patente;
        }
        
        if (!empty($busca)) {
            $sql .= " AND i.nome_completo LIKE :busca";
            $params[':busca'] = "%$busca%";
        }
        
        // Contar total
        $sqlCount = str_replace('SELECT DISTINCT i.id, i.nome_completo as indicador', 'SELECT COUNT(DISTINCT i.id) as total', $sql);
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ordenação e paginação
        $sql .= " ORDER BY i.total_indicacoes DESC, i.nome_completo ASC";
        
        $offset = ($pagina - 1) * $registrosPorPagina;
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $registrosPorPagina;
        $params[':offset'] = $offset;
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Adicionar dados de período se a tabela de histórico existir
        $checkHistTable = $db->query("SHOW TABLES LIKE 'Historico_Indicacoes'");
        if ($checkHistTable->rowCount() > 0 && !empty($resultados)) {
            foreach ($resultados as &$row) {
                try {
                    $stmtPeriodo = $db->prepare("
                        SELECT 
                            COUNT(*) as total,
                            MAX(CASE 
                                WHEN data_indicacao IS NOT NULL 
                                     AND data_indicacao != '0000-00-00 00:00:00'
                                     AND YEAR(data_indicacao) > 1970
                                THEN data_indicacao
                                ELSE NULL
                            END) as ultima
                        FROM Historico_Indicacoes
                        WHERE indicador_nome = :nome
                        AND data_indicacao IS NOT NULL
                        AND data_indicacao != '0000-00-00 00:00:00'
                        AND YEAR(data_indicacao) > 1970
                        AND DATE(data_indicacao) BETWEEN :inicio AND :fim
                    ");
                    
                    $stmtPeriodo->execute([
                        ':nome' => $row['indicador'],
                        ':inicio' => $dataInicio,
                        ':fim' => $dataFim
                    ]);
                    
                    $periodo = $stmtPeriodo->fetch(PDO::FETCH_ASSOC);
                    $row['indicacoes_periodo'] = $periodo['total'] ?? 0;
                    $row['ultima_indicacao'] = isValidMySQLDate($periodo['ultima']) ? $periodo['ultima'] : null;
                } catch (Exception $e) {
                    $row['indicacoes_periodo'] = 0;
                    $row['ultima_indicacao'] = null;
                }
            }
        }
        
        return [
            'data' => $resultados,
            'total' => $totalRegistros
        ];
        
    } catch (PDOException $e) {
        error_log("Erro SQL em gerarRelatorioIndicacoes: " . $e->getMessage());
        return ['data' => [], 'total' => 0];
    }
}

/**
 * Relatório de Aniversariantes - COM NORMALIZAÇÃO
 */
function gerarRelatorioAniversariantes($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $pagina = 1, $registrosPorPagina = 50) {
    try {
        $mesInicio = (int)date('m', strtotime($dataInicio));
        $diaInicio = (int)date('d', strtotime($dataInicio));
        $mesFim = (int)date('m', strtotime($dataFim));
        $diaFim = (int)date('d', strtotime($dataFim));
        
        // Query base para dados
        $sqlBase = "
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE a.situacao = 'Filiado'
            AND a.nasc IS NOT NULL
            AND a.nasc != '0000-00-00'
            AND YEAR(a.nasc) > 1900
            AND YEAR(a.nasc) < YEAR(CURDATE())
        ";
        
        $params = [];
        
        // Filtro de período para aniversariantes
        if ($mesInicio == $mesFim) {
            $sqlBase .= " AND MONTH(a.nasc) = :mes";
            $params[':mes'] = $mesInicio;
        } else if ($mesInicio < $mesFim) {
            $sqlBase .= " AND MONTH(a.nasc) >= :mes_inicio AND MONTH(a.nasc) <= :mes_fim";
            $params[':mes_inicio'] = $mesInicio;
            $params[':mes_fim'] = $mesFim;
        } else {
            $sqlBase .= " AND (MONTH(a.nasc) >= :mes_inicio OR MONTH(a.nasc) <= :mes_fim)";
            $params[':mes_inicio'] = $mesInicio;
            $params[':mes_fim'] = $mesFim;
        }
        
        // Outros filtros com normalização
        if (!empty($corporacao)) {
            $sqlBase .= " AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))";
            $params[':corporacao'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $patenteNormalizada = normalizePatente($patente);
            $sqlBase .= " AND (
                LOWER(REPLACE(REPLACE(m.patente, '-', ''), ' ', '')) LIKE :patente_like
                OR m.patente = :patente_exact
            )";
            $params[':patente_like'] = '%' . str_replace('-', '', $patenteNormalizada) . '%';
            $params[':patente_exact'] = $patente;
        }
        
        if (!empty($lotacao)) {
            $sqlBase .= " AND (
                m.lotacao = :lotacao_exact
                OR m.lotacao LIKE :lotacao_like
            )";
            $params[':lotacao_exact'] = $lotacao;
            $params[':lotacao_like'] = "%$lotacao%";
        }
        
        if (!empty($busca)) {
            $sqlBase .= " AND (a.nome LIKE :busca OR a.cpf LIKE :busca_cpf)";
            $params[':busca'] = "%$busca%";
            $params[':busca_cpf'] = "%$busca%";
        }
        
        // Contar total de registros (query simplificada)
        $sqlCount = "SELECT COUNT(DISTINCT a.id) as total " . $sqlBase;
        
        $stmtCount = $db->prepare($sqlCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalRegistros = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Query principal para buscar dados
        $sql = "
            SELECT DISTINCT
                a.id,
                a.nome,
                CASE 
                    WHEN a.nasc IS NOT NULL 
                         AND a.nasc != '0000-00-00'
                         AND YEAR(a.nasc) > 1900
                         AND YEAR(a.nasc) < YEAR(CURDATE())
                    THEN a.nasc
                    ELSE NULL
                END as data_nascimento,
                CASE 
                    WHEN a.nasc IS NOT NULL 
                         AND a.nasc != '0000-00-00'
                         AND YEAR(a.nasc) > 1900
                         AND YEAR(a.nasc) < YEAR(CURDATE())
                    THEN TIMESTAMPDIFF(YEAR, a.nasc, CURDATE())
                    ELSE NULL
                END as idade,
                a.telefone,
                a.email,
                COALESCE(m.corporacao, '') as corporacao,
                COALESCE(m.patente, '') as patente,
                COALESCE(m.lotacao, '') as lotacao,
                CASE 
                    WHEN a.nasc IS NOT NULL AND a.nasc != '0000-00-00'
                    THEN DAY(a.nasc)
                    ELSE NULL
                END as dia_aniversario,
                CASE 
                    WHEN a.nasc IS NOT NULL AND a.nasc != '0000-00-00'
                    THEN MONTH(a.nasc)
                    ELSE NULL
                END as mes_aniversario
        " . $sqlBase;
        
        // Ordenação
        $sql .= " ORDER BY MONTH(a.nasc), DAY(a.nasc), a.nome";
        
        // Adicionar paginação
        $offset = ($pagina - 1) * $registrosPorPagina;
        $sql .= " LIMIT :limit OFFSET :offset";
        
        // Preparar statement
        $stmt = $db->prepare($sql);
        
        // Bind todos os parâmetros
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        // Bind parâmetros de paginação
        $stmt->bindValue(':limit', $registrosPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatar datas nos resultados
        foreach ($resultados as &$row) {
            if (empty($row['data_nascimento']) || $row['data_nascimento'] == '0000-00-00') {
                $row['data_nascimento'] = null;
            }
        }
        
        return [
            'data' => $resultados,
            'total' => $totalRegistros
        ];
        
    } catch (PDOException $e) {
        error_log("Erro SQL em gerarRelatorioAniversariantes: " . $e->getMessage());
        error_log("SQL: " . (isset($sql) ? $sql : 'SQL não definido'));
        error_log("Params: " . json_encode($params));
        throw new Exception("Erro ao gerar relatório de aniversariantes: " . $e->getMessage());
    }
}

/**
 * Relatório de Novos Cadastros - COM NORMALIZAÇÃO
 */
function gerarRelatorioNovosCadastros($db, $dataInicio, $dataFim, $corporacao, $patente, $lotacao, $busca, $ordenacao, $pagina = 1, $registrosPorPagina = 50) {
    try {
        $sql = "
            SELECT DISTINCT
                a.id,
                a.nome,
                a.rg,
                a.cpf,
                a.telefone,
                a.email,
                CASE 
                    WHEN c.dataFiliacao IS NOT NULL 
                         AND c.dataFiliacao != '0000-00-00'
                         AND YEAR(c.dataFiliacao) > 1970
                    THEN c.dataFiliacao
                    WHEN a.data_aprovacao IS NOT NULL 
                         AND a.data_aprovacao != '0000-00-00 00:00:00'
                         AND YEAR(a.data_aprovacao) > 1970
                    THEN DATE(a.data_aprovacao)
                    ELSE NULL
                END as data_aprovacao,
                COALESCE(a.indicacao, '') as indicacao,
                a.pre_cadastro,
                COALESCE(m.corporacao, '') as corporacao,
                COALESCE(m.patente, '') as patente,
                COALESCE(m.lotacao, '') as lotacao,
                COALESCE(m.unidade, '') as unidade,
                CASE 
                    WHEN a.pre_cadastro = 1 THEN 'Pré-cadastro'
                    ELSE 'Cadastro Definitivo'
                END as tipo_cadastro
            FROM Associados a
            LEFT JOIN Contrato c ON a.id = c.associado_id
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE a.situacao = 'Filiado'
            AND (
                (c.dataFiliacao IS NOT NULL 
                 AND c.dataFiliacao != '0000-00-00'
                 AND c.dataFiliacao BETWEEN :data_inicio1 AND :data_fim1)
                OR
                (c.dataFiliacao IS NULL 
                 AND a.data_aprovacao IS NOT NULL 
                 AND a.data_aprovacao != '0000-00-00 00:00:00'
                 AND DATE(a.data_aprovacao) BETWEEN :data_inicio2 AND :data_fim2)
            )
        ";
        
        $params = [
            ':data_inicio1' => $dataInicio,
            ':data_fim1' => $dataFim,
            ':data_inicio2' => $dataInicio,
            ':data_fim2' => $dataFim
        ];
        
        // Filtros com normalização
        if (!empty($corporacao)) {
            $sql .= " AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))";
            $params[':corporacao'] = $corporacao;
        }
        
        if (!empty($patente)) {
            $patenteNormalizada = normalizePatente($patente);
            $sql .= " AND (
                LOWER(REPLACE(REPLACE(m.patente, '-', ''), ' ', '')) LIKE :patente_like
                OR m.patente = :patente_exact
            )";
            $params[':patente_like'] = '%' . str_replace('-', '', $patenteNormalizada) . '%';
            $params[':patente_exact'] = $patente;
        }
        
        if (!empty($lotacao)) {
            $sql .= " AND (
                m.lotacao = :lotacao_exact
                OR m.lotacao LIKE :lotacao_like
            )";
            $params[':lotacao_exact'] = $lotacao;
            $params[':lotacao_like'] = "%$lotacao%";
        }
        
        if (!empty($busca)) {
            $sql .= " AND (
                a.nome LIKE :busca 
                OR a.rg LIKE :busca_rg 
                OR a.cpf LIKE :busca_cpf 
                OR a.indicacao LIKE :busca_ind
            )";
            $params[':busca'] = "%$busca%";
            $params[':busca_rg'] = "%$busca%";
            $params[':busca_cpf'] = "%$busca%";
            $params[':busca_ind'] = "%$busca%";
        }
        
        // Agregar para garantir registros únicos
        $sql .= " GROUP BY a.id";
        
        // Contar total de registros
        $sqlCount = "SELECT COUNT(DISTINCT a.id) as total FROM Associados a
                     LEFT JOIN Contrato c ON a.id = c.associado_id
                     LEFT JOIN Militar m ON a.id = m.associado_id
                     WHERE a.situacao = 'Filiado'
                     AND (
                        (c.dataFiliacao IS NOT NULL 
                         AND c.dataFiliacao != '0000-00-00'
                         AND c.dataFiliacao BETWEEN :data_inicio1 AND :data_fim1)
                        OR
                        (c.dataFiliacao IS NULL 
                         AND a.data_aprovacao IS NOT NULL 
                         AND a.data_aprovacao != '0000-00-00 00:00:00'
                         AND DATE(a.data_aprovacao) BETWEEN :data_inicio2 AND :data_fim2)
                     )";
        
        if (!empty($corporacao)) {
            $sqlCount .= " AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))";
        }
        
        if (!empty($patente)) {
            $sqlCount .= " AND (
                LOWER(REPLACE(REPLACE(m.patente, '-', ''), ' ', '')) LIKE :patente_like
                OR m.patente = :patente_exact
            )";
        }
        
        if (!empty($lotacao)) {
            $sqlCount .= " AND (
                m.lotacao = :lotacao_exact
                OR m.lotacao LIKE :lotacao_like
            )";
        }
        
        if (!empty($busca)) {
            $sqlCount .= " AND (
                a.nome LIKE :busca 
                OR a.rg LIKE :busca_rg 
                OR a.cpf LIKE :busca_cpf 
                OR a.indicacao LIKE :busca_ind
            )";
        }
        
        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRegistros = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Ordenação
        switch ($ordenacao) {
            case 'data':
                $sql .= " ORDER BY data_aprovacao DESC, a.nome ASC";
                break;
            case 'patente':
                $sql .= " ORDER BY m.patente ASC, a.nome ASC";
                break;
            case 'corporacao':
                $sql .= " ORDER BY m.corporacao ASC, a.nome ASC";
                break;
            default:
                $sql .= " ORDER BY a.nome ASC";
        }
        
        // Adicionar paginação
        $offset = ($pagina - 1) * $registrosPorPagina;
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $registrosPorPagina;
        $params[':offset'] = $offset;
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Limpar datas inválidas
        foreach ($resultados as &$row) {
            if (!isValidMySQLDate($row['data_aprovacao'])) {
                $row['data_aprovacao'] = null;
            }
        }
        
        return [
            'data' => $resultados,
            'total' => $totalRegistros
        ];
        
    } catch (PDOException $e) {
        error_log("Erro SQL em gerarRelatorioNovosCadastros: " . $e->getMessage());
        throw new Exception("Erro ao gerar relatório de novos cadastros");
    }
}

/**
 * Relatório de Estatísticas (sem paginação)
 */
function gerarRelatorioEstatisticas($db, $dataInicio, $dataFim, $corporacao) {
    try {
        $estatisticas = [];
        $params = [];
        
        // Total de associados ativos
        $sql = "SELECT COUNT(DISTINCT a.id) as total FROM Associados a WHERE a.situacao = 'Filiado'";
        
        if (!empty($corporacao)) {
            $sql = "
                SELECT COUNT(DISTINCT a.id) as total 
                FROM Associados a 
                JOIN Militar m ON a.id = m.associado_id 
                WHERE a.situacao = 'Filiado' 
                AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))
            ";
            $params[':corporacao'] = $corporacao;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $total_ativos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $estatisticas[] = [
            'metrica' => 'Total de Associados Ativos',
            'valor' => $total_ativos,
            'percentual' => 100
        ];
        
        // Novos cadastros no período (usando tabela Contrato)
        $sql = "
            SELECT COUNT(DISTINCT a.id) as total 
            FROM Associados a
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE a.situacao = 'Filiado'
            AND (
                (c.dataFiliacao IS NOT NULL 
                 AND c.dataFiliacao != '0000-00-00'
                 AND c.dataFiliacao BETWEEN :data_inicio1 AND :data_fim1)
                OR
                (c.dataFiliacao IS NULL 
                 AND a.data_aprovacao IS NOT NULL
                 AND a.data_aprovacao != '0000-00-00 00:00:00'
                 AND DATE(a.data_aprovacao) BETWEEN :data_inicio2 AND :data_fim2)
            )
        ";
        
        $params = [
            ':data_inicio1' => $dataInicio,
            ':data_fim1' => $dataFim,
            ':data_inicio2' => $dataInicio,
            ':data_fim2' => $dataFim
        ];
        
        if (!empty($corporacao)) {
            $sql = str_replace('WHERE', 'JOIN Militar m ON a.id = m.associado_id WHERE', $sql);
            $sql .= " AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))";
            $params[':corporacao'] = $corporacao;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $novos_cadastros = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $estatisticas[] = [
            'metrica' => 'Novos Cadastros no Período',
            'valor' => $novos_cadastros,
            'percentual' => $total_ativos > 0 ? round(($novos_cadastros / $total_ativos) * 100, 2) : 0
        ];
        
        // Desfiliações no período (usando tabela Contrato)
        $sql = "
            SELECT COUNT(DISTINCT a.id) as total 
            FROM Associados a
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE a.situacao IN ('DESFILIADO', 'Desfiliado', 'desfiliado')
            AND (
                (c.dataDesfiliacao IS NOT NULL 
                 AND c.dataDesfiliacao != '0000-00-00'
                 AND c.dataDesfiliacao BETWEEN :data_inicio1 AND :data_fim1)
                OR
                (c.dataDesfiliacao IS NULL 
                 AND a.data_desfiliacao IS NOT NULL
                 AND a.data_desfiliacao != '0000-00-00 00:00:00'
                 AND DATE(a.data_desfiliacao) BETWEEN :data_inicio2 AND :data_fim2)
            )
        ";
        
        if (!empty($corporacao)) {
            $sql = str_replace('WHERE', 'JOIN Militar m ON a.id = m.associado_id WHERE', $sql);
            $sql .= " AND LOWER(TRIM(m.corporacao)) = LOWER(TRIM(:corporacao))";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $desfiliacoes = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $estatisticas[] = [
            'metrica' => 'Desfiliações no Período',
            'valor' => $desfiliacoes,
            'percentual' => $total_ativos > 0 ? round(($desfiliacoes / $total_ativos) * 100, 2) : 0
        ];
        
        // Crescimento líquido
        $crescimento = $novos_cadastros - $desfiliacoes;
        $estatisticas[] = [
            'metrica' => 'Crescimento Líquido',
            'valor' => $crescimento,
            'percentual' => $total_ativos > 0 ? round(($crescimento / $total_ativos) * 100, 2) : 0
        ];
        
        // Pré-cadastros pendentes
        $sql = "SELECT COUNT(DISTINCT id) as total FROM Associados WHERE pre_cadastro = 1 AND situacao != 'DESFILIADO'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $pre_cadastros = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $estatisticas[] = [
            'metrica' => 'Pré-cadastros Pendentes',
            'valor' => $pre_cadastros,
            'percentual' => 0
        ];
        
        // Se há filtro de corporação, adicionar estatística específica
        if (!empty($corporacao)) {
            $estatisticas[] = [
                'metrica' => 'Filtrado por Corporação',
                'valor' => 0,
                'percentual' => 0,
                'observacao' => $corporacao
            ];
        }
        
        return $estatisticas;
        
    } catch (PDOException $e) {
        error_log("Erro SQL em gerarRelatorioEstatisticas: " . $e->getMessage());
        throw new Exception("Erro ao gerar relatório de estatísticas");
    }
}

/**
 * Registrar log de relatório
 */
function registrarLogRelatorio($db, $tipo, $total_registros, $parametros) {
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'Historico_Relatorios'");
        if ($checkTable->rowCount() == 0) {
            return;
        }
        
        $stmt = $db->prepare("
            INSERT INTO Historico_Relatorios 
            (modelo_id, nome_relatorio, parametros, gerado_por, formato, contagem_registros, data_geracao)
            VALUES (NULL, :nome, :parametros, :funcionario, 'json', :contagem, NOW())
        ");
        
        $stmt->execute([
            ':nome' => 'Relatório Comercial - ' . ucfirst($tipo),
            ':parametros' => json_encode($parametros),
            ':funcionario' => $_SESSION['funcionario_id'] ?? null,
            ':contagem' => $total_registros
        ]);
    } catch (Exception $e) {
        error_log("Erro ao registrar log de relatório: " . $e->getMessage());
    }
}
?>