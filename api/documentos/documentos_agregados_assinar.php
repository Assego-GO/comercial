<?php
/**
 * API - Assinar Documento de Agregado (Aprovar/Reativar)
 * api/documentos/documentos_agregados_assinar.php
 * 
 * VERSÃO 2.0 - Aceita agregados INATIVOS e muda para ATIVO
 * 
 * Fluxo:
 * - Agregado com situação 'pendente' ou 'inativo' → muda para 'ativo'
 * - Agregado já 'ativo' → erro (já está aprovado)
 */

// Desabilitar exibição de erros para garantir JSON limpo
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Limpar qualquer saída anterior
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Função para resposta de erro
function jsonError($message, $code = 500, $debug = null) {
    ob_end_clean();
    http_response_code($code);
    $response = ['status' => 'error', 'message' => $message];
    if ($debug !== null) {
        $response['debug'] = $debug;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para resposta de sucesso
function jsonSuccess($message, $data = null) {
    ob_end_clean();
    http_response_code(200);
    $response = ['status' => 'success', 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Tratar requisição OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido. Use POST.', 405);
}

try {
    // Carregar configurações
    $configPaths = [
        __DIR__ . '/../../config/config.php',
        __DIR__ . '/../../../config/config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/comercial/config/config.php'
    ];
    
    $configLoaded = false;
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            require_once $configPath;
            require_once dirname($configPath) . '/database.php';
            require_once dirname(dirname($configPath)) . '/classes/Database.php';
            require_once dirname(dirname($configPath)) . '/classes/Auth.php';
            $configLoaded = true;
            break;
        }
    }
    
    if (!$configLoaded) {
        jsonError('Arquivos de configuração não encontrados', 500);
    }

    // Verificar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonError('Não autorizado. Faça login novamente.', 401);
    }

    // Obter dados do funcionário logado
    $funcionarioId = $_SESSION['funcionario_id'] ?? null;
    $funcionarioNome = $_SESSION['funcionario_nome'] ?? $_SESSION['usuario_nome'] ?? 'Sistema';
    
    if (!$funcionarioId) {
        jsonError('ID do funcionário não encontrado na sessão', 401);
    }

    // Ler dados da requisição
    $inputRaw = file_get_contents('php://input');
    $input = json_decode($inputRaw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('JSON inválido: ' . json_last_error_msg(), 400);
    }

    // Validar documento_id
    $documentoId = isset($input['documento_id']) ? intval($input['documento_id']) : 0;
    if ($documentoId <= 0) {
        jsonError('ID do documento/agregado inválido', 400);
    }

    // Observação (opcional)
    $observacao = trim($input['observacao'] ?? '');

    // Conectar ao banco de dados - usar getConnection()
    $dbName = defined('DB_NAME_CADASTRO') ? DB_NAME_CADASTRO : (defined('DB_NAME') ? DB_NAME : 'wwasse_cadastro');
    $db = Database::getInstance($dbName)->getConnection();

    // Buscar o agregado
    $stmt = $db->prepare("
        SELECT id, nome, cpf, situacao, ativo, socio_titular_nome, socio_titular_cpf
        FROM Socios_Agregados 
        WHERE id = ?
    ");
    $stmt->execute([$documentoId]);
    $agregado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agregado) {
        jsonError('Agregado não encontrado', 400);
    }

    // Verificar situação atual
    $situacaoAtual = strtolower(trim($agregado['situacao']));
    
    // Se já está ativo, não precisa assinar novamente
    if ($situacaoAtual === 'ativo') {
        jsonError('Este agregado já está ativo. Não é necessário assinar novamente.', 400);
    }
    
    // PERMITIR: pendente, inativo, aguardando (qualquer coisa que não seja 'ativo')
    // Isso permite que a presidência ative/reative agregados

    // Determinar tipo de ação baseado na situação anterior
    $acao = ($situacaoAtual === 'inativo') ? 'REATIVAÇÃO' : 'APROVAÇÃO';

    // Iniciar transação
    $db->beginTransaction();

    try {
        // Montar observação
        $dataHora = date('d/m/Y H:i:s');
        $novaObservacao = "[{$acao} {$dataHora} - {$funcionarioNome}] ";
        $novaObservacao .= !empty($observacao) ? $observacao : "Aprovado pela presidência";

        // Verificar se a coluna observacoes existe
        $colunaObservacoes = true;
        try {
            $db->query("SELECT observacoes FROM Socios_Agregados LIMIT 1");
        } catch (PDOException $e) {
            $colunaObservacoes = false;
        }

        // Verificar se a coluna data_atualizacao existe
        $colunaDataAtualizacao = true;
        try {
            $db->query("SELECT data_atualizacao FROM Socios_Agregados LIMIT 1");
        } catch (PDOException $e) {
            $colunaDataAtualizacao = false;
        }

        // Montar SQL de update dinamicamente
        $sqlUpdate = "UPDATE Socios_Agregados SET situacao = 'ativo', ativo = 1";
        $params = [];

        if ($colunaObservacoes) {
            $sqlUpdate .= ", observacoes = CONCAT(COALESCE(observacoes, ''), '\n', ?)";
            $params[] = $novaObservacao;
        }

        if ($colunaDataAtualizacao) {
            $sqlUpdate .= ", data_atualizacao = NOW()";
        }

        $sqlUpdate .= " WHERE id = ?";
        $params[] = $documentoId;

        $stmtUpdate = $db->prepare($sqlUpdate);
        $resultado = $stmtUpdate->execute($params);

        if (!$resultado) {
            throw new Exception('Falha ao atualizar o agregado');
        }

        // Tentar registrar na tabela de auditoria (não falha se não existir)
        try {
            $stmtAudit = $db->prepare("
                INSERT INTO Auditoria (tabela, registro_id, acao, funcionario_id, data_acao, detalhes)
                VALUES ('Socios_Agregados', ?, ?, ?, NOW(), ?)
            ");
            $stmtAudit->execute([
                $documentoId,
                $acao . '_AGREGADO',
                $funcionarioId,
                json_encode([
                    'situacao_anterior' => $agregado['situacao'],
                    'situacao_nova' => 'ativo',
                    'observacao' => $novaObservacao
                ])
            ]);
        } catch (PDOException $e) {
            // Tabela de auditoria pode não existir - não é erro crítico
            error_log("Aviso: Não foi possível registrar auditoria: " . $e->getMessage());
        }

        // Commit da transação
        $db->commit();

        // Mensagem de sucesso
        $mensagem = ($acao === 'REATIVAÇÃO') 
            ? 'Agregado reativado com sucesso!' 
            : 'Agregado aprovado com sucesso!';

        // Log de sucesso
        error_log("[AGREGADO] {$acao} - ID: {$documentoId}, Nome: {$agregado['nome']}, Por: {$funcionarioNome}");

        // Resposta de sucesso
        jsonSuccess($mensagem, [
            'agregado_id' => $documentoId,
            'nome' => $agregado['nome'],
            'cpf' => $agregado['cpf'],
            'titular_nome' => $agregado['socio_titular_nome'],
            'titular_cpf' => $agregado['socio_titular_cpf'],
            'situacao_anterior' => $agregado['situacao'],
            'situacao_nova' => 'ativo',
            'acao' => $acao,
            'data_aprovacao' => date('Y-m-d H:i:s'),
            'aprovado_por' => $funcionarioNome
        ]);

    } catch (Exception $e) {
        // Rollback em caso de erro
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Erro de banco ao assinar agregado: " . $e->getMessage());
    jsonError('Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error_log("Erro ao assinar agregado: " . $e->getMessage());
    jsonError('Erro ao processar assinatura: ' . $e->getMessage(), 500);
}