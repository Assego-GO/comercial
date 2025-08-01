<?php
/**
 * API para estatísticas de auditoria
 * /api/auditoria/estatisticas.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configurar tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }

    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    // Verificar se a classe Auditoria existe
    if (file_exists('../../classes/Auditoria.php')) {
        require_once '../../classes/Auditoria.php';
    }
    
    // Verificar se há autenticação (opcional, mas recomendado)
    session_start();
    if (!isset($_SESSION['funcionario_id'])) {
        // Log do problema mas continua (para debug)
        error_log("API Auditoria acessada sem sessão ativa");
    }
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Inicializar array de estatísticas
    $estatisticas = [
        'total_registros' => 0,
        'acoes_hoje' => 0,
        'mudanca_hoje' => 0,
        'usuarios_ativos' => 0,
        'alertas' => 0,
        'acoes_periodo' => ['labels' => [], 'data' => []],
        'tipos_acao' => ['labels' => [], 'data' => []]
    ];
    
    // === ESTATÍSTICAS BÁSICAS ===
    
    // Total de registros
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM Auditoria");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estatisticas['total_registros'] = (int)($result['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Erro ao contar registros de auditoria: " . $e->getMessage());
        $estatisticas['total_registros'] = 0;
    }
    
    // Ações hoje
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as hoje
            FROM Auditoria 
            WHERE DATE(data_hora) = CURDATE()
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estatisticas['acoes_hoje'] = (int)($result['hoje'] ?? 0);
    } catch (Exception $e) {
        error_log("Erro ao contar ações de hoje: " . $e->getMessage());
        $estatisticas['acoes_hoje'] = 0;
    }
    
    // Ações ontem para comparação
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as ontem
            FROM Auditoria 
            WHERE DATE(data_hora) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $acoesOntem = (int)($result['ontem'] ?? 0);
        
        // Calcular mudança percentual
        if ($acoesOntem > 0) {
            $estatisticas['mudanca_hoje'] = round((($estatisticas['acoes_hoje'] - $acoesOntem) / $acoesOntem) * 100, 1);
        } else {
            $estatisticas['mudanca_hoje'] = $estatisticas['acoes_hoje'] > 0 ? 100 : 0;
        }
    } catch (Exception $e) {
        error_log("Erro ao calcular mudança percentual: " . $e->getMessage());
        $estatisticas['mudanca_hoje'] = 0;
    }
    
    // Usuários ativos nas últimas 24h
    try {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT funcionario_id) as usuarios_ativos
            FROM Auditoria 
            WHERE data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND funcionario_id IS NOT NULL
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estatisticas['usuarios_ativos'] = (int)($result['usuarios_ativos'] ?? 0);
    } catch (Exception $e) {
        error_log("Erro ao contar usuários ativos: " . $e->getMessage());
        $estatisticas['usuarios_ativos'] = 0;
    }
    
    // Alertas (tentativas de login falhas, ações suspeitas, etc.)
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as alertas
            FROM Auditoria 
            WHERE acao IN ('LOGIN_FALHA', 'DELETE', 'LOGIN_FAILED') 
            AND DATE(data_hora) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estatisticas['alertas'] = (int)($result['alertas'] ?? 0);
    } catch (Exception $e) {
        error_log("Erro ao contar alertas: " . $e->getMessage());
        $estatisticas['alertas'] = 0;
    }
    
    // === DADOS PARA GRÁFICOS ===
    
    // Dados para gráfico de ações por período (últimos 7 dias)
    try {
        $stmt = $db->prepare("
            SELECT 
                DATE(data_hora) as data,
                COUNT(*) as total
            FROM Auditoria 
            WHERE data_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(data_hora)
            ORDER BY data ASC
        ");
        $stmt->execute();
        $acoesPeriodo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($acoesPeriodo)) {
            $estatisticas['acoes_periodo'] = [
                'labels' => array_map(function($item) {
                    return date('d/m', strtotime($item['data']));
                }, $acoesPeriodo),
                'data' => array_map(function($item) {
                    return (int)$item['total'];
                }, $acoesPeriodo)
            ];
        } else {
            // Dados de fallback para os últimos 7 dias
            $labels = [];
            $data = [];
            for ($i = 6; $i >= 0; $i--) {
                $labels[] = date('d/m', strtotime("-$i days"));
                $data[] = 0;
            }
            $estatisticas['acoes_periodo'] = [
                'labels' => $labels,
                'data' => $data
            ];
        }
    } catch (Exception $e) {
        error_log("Erro ao obter dados do gráfico de período: " . $e->getMessage());
        // Fallback com dados vazios
        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = date('d/m', strtotime("-$i days"));
            $data[] = 0;
        }
        $estatisticas['acoes_periodo'] = [
            'labels' => $labels,
            'data' => $data
        ];
    }
    
    // Dados para gráfico de tipos de ação
    try {
        $stmt = $db->prepare("
            SELECT 
                acao,
                COUNT(*) as total
            FROM Auditoria 
            WHERE data_hora >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY acao
            ORDER BY total DESC
            LIMIT 6
        ");
        $stmt->execute();
        $tiposAcao = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($tiposAcao)) {
            $estatisticas['tipos_acao'] = [
                'labels' => array_map(function($item) {
                    return $item['acao'];
                }, $tiposAcao),
                'data' => array_map(function($item) {
                    return (int)$item['total'];
                }, $tiposAcao)
            ];
        } else {
            // Dados de fallback
            $estatisticas['tipos_acao'] = [
                'labels' => ['INSERT', 'UPDATE', 'LOGIN', 'LOGOUT', 'DELETE'],
                'data' => [0, 0, 0, 0, 0]
            ];
        }
    } catch (Exception $e) {
        error_log("Erro ao obter dados do gráfico de tipos: " . $e->getMessage());
        $estatisticas['tipos_acao'] = [
            'labels' => ['INSERT', 'UPDATE', 'LOGIN', 'LOGOUT', 'DELETE'],
            'data' => [0, 0, 0, 0, 0]
        ];
    }
    
    // === ESTATÍSTICAS ADICIONAIS DA CLASSE AUDITORIA ===
    
    // Tentar usar a classe Auditoria se disponível
    if (class_exists('Auditoria')) {
        try {
            $auditoria = new Auditoria();
            
            // Verificar se o método existe antes de chamar
            if (method_exists($auditoria, 'gerarRelatorio')) {
                $relatorioGeral = $auditoria->gerarRelatorio('geral', 'mes');
                
                if ($relatorioGeral && isset($relatorioGeral['estatisticas']) && is_array($relatorioGeral['estatisticas'])) {
                    // Mesclar apenas dados numéricos para evitar conflitos
                    foreach ($relatorioGeral['estatisticas'] as $key => $value) {
                        if (is_numeric($value) && !isset($estatisticas[$key])) {
                            $estatisticas[$key] = $value;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao usar classe Auditoria: " . $e->getMessage());
            // Continua sem as estatísticas adicionais
        }
    }
    
    // === ESTATÍSTICAS COMPLEMENTARES ===
    
    // Adicionar algumas estatísticas complementares
    try {
        // Tabela mais ativa
        $stmt = $db->prepare("
            SELECT tabela, COUNT(*) as total
            FROM Auditoria 
            WHERE data_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY tabela
            ORDER BY total DESC
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estatisticas['tabela_mais_ativa'] = $result['tabela'] ?? 'N/A';
        
        // Horário de pico
        $stmt = $db->prepare("
            SELECT HOUR(data_hora) as hora, COUNT(*) as total
            FROM Auditoria 
            WHERE data_hora >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY HOUR(data_hora)
            ORDER BY total DESC
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $estatisticas['horario_pico'] = isset($result['hora']) ? $result['hora'] . ':00' : 'N/A';
        
    } catch (Exception $e) {
        error_log("Erro ao obter estatísticas complementares: " . $e->getMessage());
        $estatisticas['tabela_mais_ativa'] = 'N/A';
        $estatisticas['horario_pico'] = 'N/A';
    }
    
    // Adicionar timestamp da última atualização
    $estatisticas['ultima_atualizacao'] = date('Y-m-d H:i:s');
    $estatisticas['tempo_resposta'] = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    
    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Estatísticas obtidas com sucesso',
        'data' => $estatisticas,
        'meta' => [
            'versao' => '1.0',
            'timestamp' => time(),
            'servidor' => $_SERVER['SERVER_NAME'] ?? 'localhost'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    // Log do erro completo
    error_log("ERRO CRÍTICO na API de estatísticas de auditoria: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Resposta de erro
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor ao obter estatísticas',
        'error_code' => 'STATS_ERROR_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Error $e) {
    // Capturar erros fatais do PHP
    error_log("ERRO FATAL na API de estatísticas: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro fatal do sistema',
        'error_code' => 'STATS_FATAL_001',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>