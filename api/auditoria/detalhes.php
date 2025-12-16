<?php
/**
 * API para detalhes de um registro específico de auditoria
 * /api/auditoria/detalhes.php
 */

// Headers obrigatórios ANTES de qualquer output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Tratar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configurar erro reporting para não interferir no JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela
ini_set('log_errors', 1);     // Logar erros

// Buffer de output para capturar qualquer saída indesejada
ob_start();

try {
    // Log de debug inicial
    error_log("=== API DETALHES AUDITORIA ===");
    error_log("Método: " . $_SERVER['REQUEST_METHOD']);
    error_log("GET params: " . print_r($_GET, true));
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido. Use GET.');
    }

    // Verificar se o ID foi fornecido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Parâmetro ID é obrigatório');
    }

    $auditId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($auditId === false || $auditId <= 0) {
        throw new Exception('ID deve ser um número inteiro positivo');
    }
    
    error_log("ID recebido: " . $auditId);

    // Verificar se os arquivos necessários existem antes de incluir
    $requiredFiles = [
        '../../config/config.php',
        '../../config/database.php', 
        '../../classes/Database.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("Arquivo necessário não encontrado: $file");
        }
    }

    // Incluir arquivos necessários
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    
    // Verificar se as constantes necessárias existem
    if (!defined('DB_NAME_CADASTRO')) {
        throw new Exception('Constante DB_NAME_CADASTRO não definida');
    }
    
    error_log("Tentando conectar ao banco: " . DB_NAME_CADASTRO);
    
    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados');
    }
    
    error_log("Conexão com banco estabelecida");
    
    // Verificar se a tabela Auditoria existe
    $checkTable = $db->query("SHOW TABLES LIKE 'Auditoria'");
    if ($checkTable->rowCount() === 0) {
        throw new Exception('Tabela Auditoria não encontrada no banco de dados');
    }
    
    // Buscar registro principal com LEFT JOINs mais seguros
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.tabela,
            a.acao,
            a.registro_id,
            a.data_hora,
            a.ip_origem,
            a.browser_info,
            a.sessao_id,
            a.alteracoes,
            a.funcionario_id,
            a.associado_id,
            COALESCE(f.nome, 'Sistema') as funcionario_nome,
            f.email as funcionario_email,
            f.cargo as funcionario_cargo,
            COALESCE(ass.nome, 'N/A') as associado_nome,
            ass.cpf as associado_cpf,
            ass.rg as associado_rg,
            COALESCE(d.nome, 'N/A') as departamento_nome
        FROM Auditoria a
        LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
        LEFT JOIN Associados ass ON a.associado_id = ass.id  
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        WHERE a.id = :id
        LIMIT 1
    ");
    
    $stmt->execute([':id' => $auditId]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$registro) {
        throw new Exception("Registro de auditoria ID $auditId não encontrado");
    }
    
    error_log("Registro encontrado: " . $registro['acao'] . " na tabela " . $registro['tabela']);
    
    // Buscar detalhes das alterações se existirem (opcional - pode não haver tabela Auditoria_Detalhes)
    $detalhesAlteracoes = [];
    if ($registro['acao'] === 'UPDATE') {
        try {
            $checkDetailsTable = $db->query("SHOW TABLES LIKE 'Auditoria_Detalhes'");
            if ($checkDetailsTable->rowCount() > 0) {
                $stmtDetalhes = $db->prepare("
                    SELECT * FROM Auditoria_Detalhes 
                    WHERE auditoria_id = :auditoria_id
                    ORDER BY id
                ");
                
                $stmtDetalhes->execute([':auditoria_id' => $auditId]);
                $detalhesAlteracoes = $stmtDetalhes->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Detalhes de alterações encontrados: " . count($detalhesAlteracoes));
            }
        } catch (Exception $e) {
            error_log("Aviso: Não foi possível buscar detalhes das alterações: " . $e->getMessage());
            // Continuar sem os detalhes
        }
    }
    
    // Processar dados de forma mais segura
    $registroDetalhado = [
        'id' => (int)$registro['id'],
        'tabela' => $registro['tabela'] ?? 'N/A',
        'acao' => $registro['acao'] ?? 'UNKNOWN',
        'registro_id' => $registro['registro_id'],
        'data_hora' => $registro['data_hora'],
        'ip_origem' => $registro['ip_origem'] ?? 'N/A',
        'browser_info' => $registro['browser_info'] ?? 'N/A',
        'sessao_id' => $registro['sessao_id'] ?? 'N/A',
        'alteracoes' => $registro['alteracoes'],
        
        // Informações do funcionário
        'funcionario_id' => $registro['funcionario_id'],
        'funcionario_nome' => $registro['funcionario_nome'] ?? 'Sistema',
        'funcionario_email' => $registro['funcionario_email'] ?? 'N/A',
        'funcionario_cargo' => $registro['funcionario_cargo'] ?? 'N/A',
        'departamento_nome' => $registro['departamento_nome'] ?? 'N/A',
        
        // Informações do associado (se aplicável)
        'associado_id' => $registro['associado_id'],
        'associado_nome' => $registro['associado_nome'] ?? 'N/A',
        'associado_cpf' => $registro['associado_cpf'] ?? 'N/A',
        
        // Detalhes das alterações
        'detalhes_alteracoes' => $detalhesAlteracoes,
        
        // Dados processados
        'data_hora_formatada' => $registro['data_hora'] ? date('d/m/Y H:i:s', strtotime($registro['data_hora'])) : 'N/A',
        'alteracoes_decoded' => null
    ];
    
    // Decodificar JSON das alterações de forma segura
    if (!empty($registro['alteracoes'])) {
        try {
            $alteracoesDecoded = json_decode($registro['alteracoes'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $registroDetalhado['alteracoes_decoded'] = $alteracoesDecoded;
            } else {
                error_log("Erro ao decodificar JSON das alterações: " . json_last_error_msg());
                $registroDetalhado['alteracoes_decoded'] = ['erro' => 'JSON inválido'];
            }
        } catch (Exception $e) {
            error_log("Exceção ao decodificar alterações: " . $e->getMessage());
            $registroDetalhado['alteracoes_decoded'] = ['erro' => 'Erro ao processar alterações'];
        }
    }
    
    // Informações adicionais baseadas no tipo de ação
    $informacoesAdicionais = [];
    
    switch ($registro['acao']) {
        case 'LOGIN':
        case 'LOGOUT':
            $informacoesAdicionais['tipo_evento'] = 'Evento de Autenticação';
            $informacoesAdicionais['categoria'] = 'Segurança';
            $informacoesAdicionais['nivel_risco'] = 'Baixo';
            break;
            
        case 'INSERT':
            $informacoesAdicionais['tipo_evento'] = 'Criação de Registro';
            $informacoesAdicionais['categoria'] = 'Dados';
            $informacoesAdicionais['nivel_risco'] = 'Baixo';
            break;
            
        case 'UPDATE':
            $informacoesAdicionais['tipo_evento'] = 'Atualização de Registro';
            $informacoesAdicionais['categoria'] = 'Dados';
            $informacoesAdicionais['nivel_risco'] = 'Médio';
            $informacoesAdicionais['total_campos_alterados'] = count($detalhesAlteracoes);
            break;
            
        case 'DELETE':
            $informacoesAdicionais['tipo_evento'] = 'Exclusão de Registro';
            $informacoesAdicionais['categoria'] = 'Dados Críticos';
            $informacoesAdicionais['nivel_risco'] = 'Alto';
            break;
            
        case 'VISUALIZAR':
        case 'VIEW':
            $informacoesAdicionais['tipo_evento'] = 'Acesso a Dados';
            $informacoesAdicionais['categoria'] = 'Acesso';
            $informacoesAdicionais['nivel_risco'] = 'Baixo';
            break;
            
        default:
            $informacoesAdicionais['tipo_evento'] = 'Ação do Sistema';
            $informacoesAdicionais['categoria'] = 'Sistema';
            $informacoesAdicionais['nivel_risco'] = 'Médio';
    }
    
    $registroDetalhado['informacoes_adicionais'] = $informacoesAdicionais;
    
    // Buscar registros relacionados (mesmo usuário, mesma sessão, etc.) - opcional
    $registrosRelacionados = [];
    
    if (!empty($registro['sessao_id']) && $registro['sessao_id'] !== 'N/A') {
        try {
            $stmtRelacionados = $db->prepare("
                SELECT 
                    id, acao, tabela, data_hora,
                    TIMESTAMPDIFF(SECOND, :data_atual, data_hora) as diferenca_segundos
                FROM Auditoria 
                WHERE sessao_id = :sessao_id 
                AND id != :id
                AND data_hora BETWEEN 
                    DATE_SUB(:data_atual, INTERVAL 1 HOUR) AND 
                    DATE_ADD(:data_atual, INTERVAL 1 HOUR)
                ORDER BY data_hora DESC
                LIMIT 10
            ");
            
            $stmtRelacionados->execute([
                ':sessao_id' => $registro['sessao_id'],
                ':id' => $auditId,
                ':data_atual' => $registro['data_hora']
            ]);
            
            $registrosRelacionados = $stmtRelacionados->fetchAll(PDO::FETCH_ASSOC);
            error_log("Registros relacionados encontrados: " . count($registrosRelacionados));
            
        } catch (Exception $e) {
            error_log("Aviso: Não foi possível buscar registros relacionados: " . $e->getMessage());
            // Continuar sem os registros relacionados
        }
    }
    
    $registroDetalhado['registros_relacionados'] = $registrosRelacionados;
    
    // Limpar buffer de output antes de enviar JSON
    ob_clean();
    
    // Enviar resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Detalhes obtidos com sucesso',
        'data' => $registroDetalhado,
        'timestamp' => date('Y-m-d H:i:s'),
        'api_version' => '1.0'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    error_log("Resposta enviada com sucesso para ID: $auditId");

} catch (PDOException $e) {
    // Limpar buffer
    ob_clean();
    
    error_log("Erro de banco de dados na API de detalhes: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor - problema na base de dados',
        'error_code' => 'DB_ERROR',
        'data' => null,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Limpar buffer
    ob_clean();
    
    error_log("Erro na API de detalhes de auditoria: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'error_code' => 'API_ERROR',
        'data' => null,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} finally {
    // Garantir que o buffer seja limpo
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>