<?php
/**
 * API para Atualizar Sócio Agregado - VERSÃO SEM CAMPOS OBRIGATÓRIOS
 * api/atualizar_agregado.php
 * 
 * TODOS OS CAMPOS SÃO OPCIONAIS (exceto CPF do titular para validação)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido. Use POST ou PUT.'
    ]);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';

function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - ATUALIZAR_AGREGADO - " . $message;
    if ($data) {
        $logMessage .= " - Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage);
}

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) !== 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    
    return true;
}

function validarEmail($email) {
    if (empty($email)) return true;
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function limparDados($dados) {
    $dadosLimpos = [];
    
    foreach ($dados as $key => $value) {
        if (is_string($value)) {
            $dadosLimpos[$key] = trim($value);
        } else {
            $dadosLimpos[$key] = $value;
        }
    }
    
    return $dadosLimpos;
}

try {
    logError("=== INÍCIO ATUALIZAÇÃO SÓCIO AGREGADO - SEM CAMPOS OBRIGATÓRIOS ===");
    
    // =====================================================
    // CAPTURA E VALIDAÇÃO DO ID
    // =====================================================
    
    $agregadoId = null;
    
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $agregadoId = intval($_GET['id']);
    } elseif (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $agregadoId = intval($_POST['id']);
    }
    
    if (!$agregadoId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID do sócio agregado é obrigatório'
        ]);
        exit;
    }
    
    logError("ID recebido", ['id' => $agregadoId]);
    
    // =====================================================
    // CONEXÃO E VERIFICAÇÃO SE EXISTE
    // =====================================================
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $stmtVerifica = $db->prepare("
        SELECT * FROM Socios_Agregados 
        WHERE id = ? AND ativo = 1
    ");
    $stmtVerifica->execute([$agregadoId]);
    
    if ($stmtVerifica->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sócio agregado não encontrado'
        ]);
        exit;
    }
    
    $registroAtual = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    logError("Registro encontrado", ['nome' => $registroAtual['nome']]);
    
    // =====================================================
    // CAPTURA E LIMPEZA DOS DADOS
    // =====================================================
    
    $dadosRecebidos = $_POST;
    unset($dadosRecebidos['id']);
    $dados = limparDados($dadosRecebidos);
    
    // =============================
    // VALIDAÇÃO DO TITULAR (SE INFORMADO)
    // =============================
    
    $cpfTitularRecebido = null;
    
    if (!empty($dados['socioTitularCpf'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['socioTitularCpf']);
    } elseif (!empty($dados['cpfTitular'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['cpfTitular']);
    }
    
    // Se informou CPF do titular, valida
    if ($cpfTitularRecebido) {
        $stmtTitular = $db->prepare("
            SELECT a.id, a.nome, a.cpf, a.rg, a.email, a.telefone, a.situacao
            FROM Associados a
            WHERE REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
            LIMIT 1
        ");
        $stmtTitular->execute([$cpfTitularRecebido]);
        $titular = $stmtTitular->fetch(PDO::FETCH_ASSOC);
        
        if (!$titular) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Sócio titular não encontrado'
            ]);
            exit;
        }
        
        if (strtolower($titular['situacao']) !== 'filiado') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Titular deve estar filiado'
            ]);
            exit;
        }
        
        // Sobrescreve com dados do banco
        $dados['socioTitularNome'] = $titular['nome'];
        $dados['socioTitularCpf'] = $titular['cpf'];
        $dados['socioTitularFone'] = $titular['telefone'] ?? '';
        $dados['socioTitularEmail'] = $titular['email'] ?? '';
        
        logError("✓ Titular validado", ['nome' => $titular['nome']]);
    }
    
    // =====================================================
    // SEM VALIDAÇÕES OBRIGATÓRIAS - USA VALORES ATUAIS SE NÃO INFORMADO
    // =====================================================
    
    // Apenas valida CPF se foi informado
    if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'CPF inválido'
        ]);
        exit;
    }
    
    // Valida email se informado
    if (!validarEmail($dados['email'] ?? '')) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'E-mail inválido'
        ]);
        exit;
    }
    
    // =====================================================
    // VERIFICAÇÃO DE CPF DUPLICADO
    // =====================================================
    
    if (!empty($dados['cpf'])) {
        $stmtCpfDuplicado = $db->prepare("
            SELECT id, nome 
            FROM Socios_Agregados 
            WHERE cpf = ? AND id != ? AND ativo = 1
        ");
        $stmtCpfDuplicado->execute([$dados['cpf'], $agregadoId]);
        
        if ($stmtCpfDuplicado->rowCount() > 0) {
            $duplicado = $stmtCpfDuplicado->fetch(PDO::FETCH_ASSOC);
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'CPF já cadastrado em outro sócio agregado'
            ]);
            exit;
        }
    }
    
    // =====================================================
    // PROCESSAMENTO DOS DEPENDENTES
    // =====================================================
    
    $dependentesJson = $registroAtual['dependentes'] ?? '[]'; // Mantém atual se não informado
    
    if (isset($dados['dependentes']) && is_array($dados['dependentes'])) {
        $dependentesProcessados = [];
        
        foreach ($dados['dependentes'] as $dependente) {
            if (!empty($dependente['tipo']) && !empty($dependente['data_nascimento'])) {
                $depProcessado = [
                    'tipo' => trim($dependente['tipo']),
                    'data_nascimento' => trim($dependente['data_nascimento'])
                ];
                
                if (!empty($dependente['cpf']) && validarCPF($dependente['cpf'])) {
                    $depProcessado['cpf'] = $dependente['cpf'];
                }
                
                if (!empty($dependente['telefone'])) {
                    $depProcessado['telefone'] = $dependente['telefone'];
                }
                
                $dependentesProcessados[] = $depProcessado;
            }
        }
        
        if (!empty($dependentesProcessados)) {
            $dependentesJson = json_encode($dependentesProcessados, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // =====================================================
    // ATUALIZAÇÃO NO BANCO DE DADOS
    // Usa valor atual se não foi informado
    // =====================================================
    
    $sql = "UPDATE Socios_Agregados SET 
        nome = :nome,
        data_nascimento = :data_nascimento,
        telefone = :telefone,
        celular = :celular,
        email = :email,
        cpf = :cpf,
        documento = :documento,
        estado_civil = :estado_civil,
        data_filiacao = :data_filiacao,
        socio_titular_nome = :socio_titular_nome,
        socio_titular_fone = :socio_titular_fone,
        socio_titular_cpf = :socio_titular_cpf,
        socio_titular_email = :socio_titular_email,
        cep = :cep,
        endereco = :endereco,
        numero = :numero,
        bairro = :bairro,
        cidade = :cidade,
        estado = :estado,
        banco = :banco,
        banco_outro_nome = :banco_outro_nome,
        agencia = :agencia,
        conta_corrente = :conta_corrente,
        dependentes = :dependentes,
        data_atualizacao = NOW()
    WHERE id = :id AND ativo = 1";
    
    $stmt = $db->prepare($sql);
    
    // Usa valor atual se não informado (todos opcionais)
    $parametros = [
        ':id' => $agregadoId,
        ':nome' => $dados['nome'] ?? $registroAtual['nome'],
        ':data_nascimento' => $dados['dataNascimento'] ?? $registroAtual['data_nascimento'],
        ':telefone' => $dados['telefone'] ?? $registroAtual['telefone'],
        ':celular' => $dados['celular'] ?? $registroAtual['celular'],
        ':email' => $dados['email'] ?? $registroAtual['email'],
        ':cpf' => $dados['cpf'] ?? $registroAtual['cpf'],
        ':documento' => $dados['documento'] ?? $registroAtual['documento'],
        ':estado_civil' => $dados['estadoCivil'] ?? $registroAtual['estado_civil'],
        ':data_filiacao' => $dados['dataFiliacao'] ?? $registroAtual['data_filiacao'],
        ':socio_titular_nome' => $dados['socioTitularNome'] ?? $registroAtual['socio_titular_nome'],
        ':socio_titular_fone' => $dados['socioTitularFone'] ?? $registroAtual['socio_titular_fone'],
        ':socio_titular_cpf' => isset($dados['socioTitularCpf']) ? preg_replace('/\D/', '', $dados['socioTitularCpf']) : $registroAtual['socio_titular_cpf'],
        ':socio_titular_email' => $dados['socioTitularEmail'] ?? $registroAtual['socio_titular_email'],
        ':cep' => $dados['cep'] ?? $registroAtual['cep'],
        ':endereco' => $dados['endereco'] ?? $registroAtual['endereco'],
        ':numero' => $dados['numero'] ?? $registroAtual['numero'],
        ':bairro' => $dados['bairro'] ?? $registroAtual['bairro'],
        ':cidade' => $dados['cidade'] ?? $registroAtual['cidade'],
        ':estado' => $dados['estado'] ?? $registroAtual['estado'],
        ':banco' => $dados['banco'] ?? $registroAtual['banco'],
        ':banco_outro_nome' => (($dados['banco'] ?? $registroAtual['banco']) === 'outro') 
            ? ($dados['bancoOutroNome'] ?? $registroAtual['banco_outro_nome']) 
            : null,
        ':agencia' => $dados['agencia'] ?? $registroAtual['agencia'],
        ':conta_corrente' => $dados['contaCorrente'] ?? $registroAtual['conta_corrente'],
        ':dependentes' => $dependentesJson
    ];
    
    logError("Parâmetros para atualização", ['id' => $agregadoId]);
    
    if ($stmt->execute($parametros)) {
        $linhasAfetadas = $stmt->rowCount();
        
        logError("✓ Sócio agregado atualizado", ['id' => $agregadoId, 'linhas' => $linhasAfetadas]);
        
        // Busca dados atualizados
        $stmtConsulta = $db->prepare("
            SELECT id, nome, cpf, telefone, celular, email,
                   socio_titular_nome, socio_titular_cpf, valor_contribuicao, 
                   data_filiacao, situacao, data_atualizacao,
                   JSON_LENGTH(COALESCE(dependentes, '[]')) as total_dependentes,
                   banco, agencia, conta_corrente
            FROM Socios_Agregados 
            WHERE id = ? AND ativo = 1
        ");
        $stmtConsulta->execute([$agregadoId]);
        $dadosAtualizados = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Sócio agregado atualizado com sucesso!',
            'data' => [
                'id' => $dadosAtualizados['id'],
                'nome' => $dadosAtualizados['nome'],
                'cpf' => $dadosAtualizados['cpf'],
                'telefone' => $dadosAtualizados['telefone'],
                'celular' => $dadosAtualizados['celular'],
                'email' => $dadosAtualizados['email'],
                'socio_titular' => $dadosAtualizados['socio_titular_nome'],
                'socio_titular_cpf' => $dadosAtualizados['socio_titular_cpf'],
                'valor_contribuicao' => $dadosAtualizados['valor_contribuicao'],
                'data_filiacao' => $dadosAtualizados['data_filiacao'],
                'situacao' => $dadosAtualizados['situacao'],
                'total_dependentes' => (int)$dadosAtualizados['total_dependentes'],
                'banco' => $dadosAtualizados['banco'],
                'agencia' => $dadosAtualizados['agencia'],
                'conta_corrente' => $dadosAtualizados['conta_corrente'],
                'data_atualizacao' => $dadosAtualizados['data_atualizacao'],
                'linhas_afetadas' => $linhasAfetadas
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        $errorInfo = $stmt->errorInfo();
        logError("Erro na atualização SQL", $errorInfo);
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro interno ao atualizar sócio agregado'
        ]);
    }
    
} catch (PDOException $e) {
    logError("Erro PDO", ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de banco de dados: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    logError("Erro geral", ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage()
    ]);
} finally {
    logError("=== FIM ATUALIZAÇÃO SÓCIO AGREGADO ===");
}
?>