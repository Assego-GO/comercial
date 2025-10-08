<?php
/**
 * API para listagem de funcionários para filtros
 * /api/auditoria/funcionarios.php
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
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // === BUSCAR FUNCIONÁRIOS QUE APARECEM NA AUDITORIA ===
    
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT 
                f.id,
                f.nome,
                f.cargo,
                f.email,
                d.nome as departamento_nome,
                COUNT(a.id) as total_acoes,
                MAX(a.data_hora) as ultima_acao,
                MIN(a.data_hora) as primeira_acao
            FROM Funcionarios f
            INNER JOIN Auditoria a ON f.id = a.funcionario_id
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.ativo = 1
            GROUP BY f.id, f.nome, f.cargo, f.email, d.nome
            ORDER BY total_acoes DESC, f.nome ASC
        ");
        
        $stmt->execute();
        $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // Fallback: buscar funcionários mesmo sem auditoria
        error_log("Erro ao buscar funcionários com auditoria, usando fallback: " . $e->getMessage());
        
        $stmt = $db->prepare("
            SELECT 
                f.id,
                f.nome,
                f.cargo,
                f.email,
                d.nome as departamento_nome,
                0 as total_acoes,
                NULL as ultima_acao,
                NULL as primeira_acao
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.ativo = 1
            ORDER BY f.nome ASC
            LIMIT 50
        ");
        
        $stmt->execute();
        $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // === PROCESSAR DADOS DOS FUNCIONÁRIOS ===
    
    $funcionariosProcessados = [];
    foreach ($funcionarios as $funcionario) {
        $processado = [
            'id' => (int)$funcionario['id'],
            'nome' => $funcionario['nome'],
            'cargo' => $funcionario['cargo'] ?? 'N/A',
            'email' => $funcionario['email'],
            'departamento' => $funcionario['departamento_nome'] ?? 'N/A',
            'total_acoes' => (int)($funcionario['total_acoes'] ?? 0),
            'nome_completo' => $funcionario['nome'] . ' (' . ($funcionario['cargo'] ?? 'N/A') . ')',
            'nome_com_dept' => $funcionario['nome'] . ' - ' . ($funcionario['departamento_nome'] ?? 'N/A')
        ];
        
        // Formatar datas se existirem
        if (!empty($funcionario['ultima_acao'])) {
            $processado['ultima_acao'] = $funcionario['ultima_acao'];
            $processado['ultima_acao_formatada'] = date('d/m/Y H:i', strtotime($funcionario['ultima_acao']));
        } else {
            $processado['ultima_acao'] = null;
            $processado['ultima_acao_formatada'] = 'Nunca';
        }
        
        if (!empty($funcionario['primeira_acao'])) {
            $processado['primeira_acao'] = $funcionario['primeira_acao'];
            $processado['primeira_acao_formatada'] = date('d/m/Y H:i', strtotime($funcionario['primeira_acao']));
        } else {
            $processado['primeira_acao'] = null;
            $processado['primeira_acao_formatada'] = 'Nunca';
        }
        
        // Calcular nível de atividade
        if ($processado['total_acoes'] == 0) {
            $processado['nivel_atividade'] = 'Inativo';
        } elseif ($processado['total_acoes'] < 10) {
            $processado['nivel_atividade'] = 'Baixo';
        } elseif ($processado['total_acoes'] < 50) {
            $processado['nivel_atividade'] = 'Médio';
        } elseif ($processado['total_acoes'] < 200) {
            $processado['nivel_atividade'] = 'Alto';
        } else {
            $processado['nivel_atividade'] = 'Muito Alto';
        }
        
        $funcionariosProcessados[] = $processado;
    }
    
    // === ESTATÍSTICAS GERAIS ===
    
    $estatisticas = [
        'total_funcionarios' => count($funcionariosProcessados),
        'funcionarios_ativos' => count(array_filter($funcionariosProcessados, function($f) {
            return $f['total_acoes'] > 0;
        })),
        'funcionarios_inativos' => count(array_filter($funcionariosProcessados, function($f) {
            return $f['total_acoes'] == 0;
        })),
        'total_acoes_geral' => array_sum(array_column($funcionariosProcessados, 'total_acoes')),
        'media_acoes' => count($funcionariosProcessados) > 0 ? 
            round(array_sum(array_column($funcionariosProcessados, 'total_acoes')) / count($funcionariosProcessados), 2) : 0
    ];
    
    // Funcionário mais ativo
    if (!empty($funcionariosProcessados)) {
        $maisAtivo = array_reduce($funcionariosProcessados, function($carry, $item) {
            return ($carry === null || $item['total_acoes'] > $carry['total_acoes']) ? $item : $carry;
        });
        $estatisticas['funcionario_mais_ativo'] = [
            'nome' => $maisAtivo['nome'],
            'total_acoes' => $maisAtivo['total_acoes']
        ];
    } else {
        $estatisticas['funcionario_mais_ativo'] = null;
    }
    
    // === FILTROS DISPONÍVEIS ===
    
    $filtrosDisponiveis = [
        'departamentos' => array_values(array_unique(array_filter(array_column($funcionariosProcessados, 'departamento')))),
        'cargos' => array_values(array_unique(array_filter(array_column($funcionariosProcessados, 'cargo')))),
        'niveis_atividade' => ['Inativo', 'Baixo', 'Médio', 'Alto', 'Muito Alto']
    ];
    
    // === RESPOSTA DE SUCESSO ===
    
    $response = [
        'status' => 'success',
        'message' => 'Funcionários obtidos com sucesso',
        'data' => $funcionariosProcessados,
        'meta' => [
            'estatisticas' => $estatisticas,
            'filtros_disponiveis' => $filtrosDisponiveis,
            'tempo_execucao' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'timestamp' => time(),
            'versao_api' => '1.0'
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (PDOException $e) {
    // Erro específico do banco de dados
    error_log("Erro PDO na API de funcionários de auditoria: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de banco de dados',
        'error_code' => 'DB_ERROR_003',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Outros erros
    error_log("Erro na API de funcionários de auditoria: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor: ' . $e->getMessage(),
        'error_code' => 'GENERAL_ERROR_003',
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'data' => []
    ], JSON_UNESCAPED_UNICODE);
}
?>