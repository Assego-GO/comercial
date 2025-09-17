<?php
/**
 * Processador de Verificação de Associados
 * rend/verificar_associados_process.php
 */

// Headers para AJAX
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros no output JSON

// Includes
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Verifica permissões
    $permissoes = Permissoes::getInstance();
    $temPermissaoFinanceiro = $permissoes->hasPermission('FINANCEIRO_DASHBOARD', 'VIEW');
    
    if (!$temPermissaoFinanceiro) {
        throw new Exception('Sem permissão para acessar este recurso');
    }

    // Processa apenas requisições POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Lê dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados JSON inválidos');
    }

    $action = $data['action'] ?? '';

    switch ($action) {
        case 'verify_batch':
            $result = verifyBatch($data['data'] ?? []);
            break;
        
        default:
            throw new Exception('Ação não reconhecida');
    }

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);

} catch (Exception $e) {
    error_log("Erro em verificar_associados_process.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Verifica um lote de registros no banco de dados
 */
function verifyBatch($batchData) {
    if (empty($batchData) || !is_array($batchData)) {
        throw new Exception('Dados do lote inválidos');
    }

    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $results = [];

    foreach ($batchData as $item) {
        $nome = trim($item['nome'] ?? '');
        $rg = cleanRG($item['rg'] ?? '');
        $rgOriginal = trim($item['rgOriginal'] ?? '');

        if (empty($nome) || empty($rg)) {
            $results[] = [
                'nome_pesquisado' => $nome,
                'rg_pesquisado' => $rgOriginal,
                'status' => 'INVALID',
                'nome' => null,
                'cpf' => null,
                'rg' => null,
                'situacao' => null,
                'patente' => null,
                'corporacao' => null,
                'observacao' => 'Dados incompletos'
            ];
            continue;
        }

        try {
            $associado = searchAssociadoByRG($db, $rg);
            
            if ($associado) {
                $results[] = [
                    'nome_pesquisado' => $nome,
                    'rg_pesquisado' => $rgOriginal,
                    'status' => 'FOUND',
                    'nome' => $associado['nome'],
                    'cpf' => $associado['cpf'],
                    'rg' => $associado['rg'],
                    'situacao' => $associado['situacao'],
                    'patente' => $associado['patente'],
                    'corporacao' => $associado['corporacao'],
                    'observacao' => null
                ];
            } else {
                $results[] = [
                    'nome_pesquisado' => $nome,
                    'rg_pesquisado' => $rgOriginal,
                    'status' => 'NOT_FOUND',
                    'nome' => null,
                    'cpf' => null,
                    'rg' => null,
                    'situacao' => null,
                    'patente' => null,
                    'corporacao' => null,
                    'observacao' => 'RG não encontrado na base de dados'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Erro ao buscar associado RG {$rg}: " . $e->getMessage());
            
            $results[] = [
                'nome_pesquisado' => $nome,
                'rg_pesquisado' => $rgOriginal,
                'status' => 'ERROR',
                'nome' => null,
                'cpf' => null,
                'rg' => null,
                'situacao' => null,
                'patente' => null,
                'corporacao' => null,
                'observacao' => 'Erro ao processar consulta'
            ];
        }
    }

    return $results;
}

/**
 * Busca associado pelo RG
 */
function searchAssociadoByRG($db, $rg) {
    // Primeiro, tentar busca exata pelo RG limpo
    $sql = "
        SELECT 
            a.nome,
            a.cpf,
            a.rg,
            a.situacao,
            m.patente,
            m.corporacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE REPLACE(REPLACE(REPLACE(a.rg, '.', ''), '-', ''), ' ', '') = :rg
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':rg', $rg, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return $result;
    }

    // Se não encontrou, tentar busca por RG similar (pode ter formatação diferente)
    $sql = "
        SELECT 
            a.nome,
            a.cpf,
            a.rg,
            a.situacao,
            m.patente,
            m.corporacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE a.rg LIKE :rg_pattern
        LIMIT 1
    ";
    
    // Criar padrão de busca: adicionar % entre cada dígito para busca flexível
    $rgPattern = implode('%', str_split($rg));
    $rgPattern = '%' . $rgPattern . '%';
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':rg_pattern', $rgPattern, PDO::PARAM_STR);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Limpa RG removendo caracteres não numéricos
 */
function cleanRG($rg) {
    if (empty($rg)) {
        return '';
    }
    
    // Remove tudo que não for número
    return preg_replace('/[^\d]/', '', $rg);
}

/**
 * Registra log de auditoria
 */
function logAuditoria($db, $funcionarioId, $acao, $detalhes) {
    try {
        $sql = "
            INSERT INTO Auditoria (
                tabela, 
                acao, 
                funcionario_id, 
                alteracoes, 
                data_hora,
                ip_origem
            ) VALUES (
                'verificar_associados', 
                :acao, 
                :funcionario_id, 
                :detalhes, 
                NOW(),
                :ip
            )
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'acao' => $acao,
            'funcionario_id' => $funcionarioId,
            'detalhes' => json_encode($detalhes),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Erro ao registrar auditoria: " . $e->getMessage());
    }
}
?>