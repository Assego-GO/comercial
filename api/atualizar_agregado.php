<?php
/**
 * API para Atualizar Sócio Agregado
 * api/atualizar_agregado.php
 */

// Headers CORS e Content-Type
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Só aceita POST/PUT
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido. Use POST ou PUT.'
    ]);
    exit;
}

// Includes necessários
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';

// Função para logar erros
function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - ATUALIZAR_AGREGADO - " . $message;
    if ($data) {
        $logMessage .= " - Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    error_log($logMessage);
}

// Função para validar CPF
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

// Função para validar email
function validarEmail($email) {
    if (empty($email)) return true;
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para limpar dados
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
    logError("=== INÍCIO ATUALIZAÇÃO SÓCIO AGREGADO ===");
    
    // =====================================================
    // CAPTURA E VALIDAÇÃO DO ID
    // =====================================================
    
    // ID pode vir via GET (?id=123) ou POST
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
            'message' => 'ID do sócio agregado é obrigatório',
            'debug' => 'ID deve ser fornecido via GET (?id=123) ou POST'
        ]);
        exit;
    }
    
    logError("ID recebido", ['id' => $agregadoId]);
    
    // =====================================================
    // CONEXÃO E VERIFICAÇÃO SE EXISTE
    // =====================================================
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Verifica se o registro existe e está ativo
    $stmtVerifica = $db->prepare("
        SELECT id, nome, cpf, situacao, data_criacao 
        FROM Socios_Agregados 
        WHERE id = ? AND ativo = 1
    ");
    $stmtVerifica->execute([$agregadoId]);
    
    if ($stmtVerifica->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sócio agregado não encontrado',
            'debug' => "ID {$agregadoId} não existe ou foi excluído"
        ]);
        logError("Registro não encontrado", ['id' => $agregadoId]);
        exit;
    }
    
    $registroAtual = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    logError("Registro encontrado", $registroAtual);
    
    // =====================================================
    // CAPTURA E VALIDAÇÃO DOS DADOS
    // =====================================================
    
    $dadosRecebidos = $_POST;
    logError("Dados recebidos para atualização", $dadosRecebidos);
    
    // Remove o ID dos dados (não deve ser alterado)
    unset($dadosRecebidos['id']);
    
    // Limpa dados
    $dados = limparDados($dadosRecebidos);
    
    // Campos obrigatórios (mesmos do criar)
    $camposObrigatorios = [
        'nome' => 'Nome completo',
        'dataNascimento' => 'Data de nascimento',
        'telefone' => 'Telefone',
        'celular' => 'Celular',
        'cpf' => 'CPF',
        'estadoCivil' => 'Estado civil',
        'dataFiliacao' => 'Data de filiação',
        'socioTitularNome' => 'Nome do sócio titular',
        'socioTitularFone' => 'Telefone do sócio titular',
        'socioTitularCpf' => 'CPF do sócio titular',
        'endereco' => 'Endereço',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'estado' => 'Estado',
        'banco' => 'Banco',
        'agencia' => 'Agência',
        'contaCorrente' => 'Conta corrente'
    ];
    
    $errosValidacao = [];
    
    // Valida campos obrigatórios
    foreach ($camposObrigatorios as $campo => $nomeCampo) {
        if (empty($dados[$campo])) {
            $errosValidacao[] = "Campo obrigatório: {$nomeCampo}";
        }
    }
    
    // Validações específicas
    if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
        $errosValidacao[] = "CPF inválido";
    }
    
    if (!empty($dados['socioTitularCpf']) && !validarCPF($dados['socioTitularCpf'])) {
        $errosValidacao[] = "CPF do sócio titular inválido";
    }
    
    if (!validarEmail($dados['email'] ?? '')) {
        $errosValidacao[] = "E-mail inválido";
    }
    
    if (!validarEmail($dados['socioTitularEmail'] ?? '')) {
        $errosValidacao[] = "E-mail do sócio titular inválido";
    }
    
    // Banco outro
    if (($dados['banco'] ?? '') === 'outro' && empty($dados['bancoOutroNome'])) {
        $errosValidacao[] = "Nome do banco é obrigatório quando selecionado 'Outro'";
    }
    
    if (!empty($errosValidacao)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Dados inválidos',
            'errors' => $errosValidacao,
            'debug' => 'Falha na validação dos campos'
        ]);
        logError("Erros de validação", $errosValidacao);
        exit;
    }
    
    // =====================================================
    // VERIFICAÇÃO DE CPF DUPLICADO (exceto o próprio)
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
                'message' => 'CPF já cadastrado em outro sócio agregado',
                'conflito' => [
                    'nome_existente' => $duplicado['nome'],
                    'id_existente' => $duplicado['id']
                ]
            ]);
            logError("CPF duplicado", [
                'cpf' => $dados['cpf'],
                'conflito_com' => $duplicado['nome']
            ]);
            exit;
        }
    }
    
    // =====================================================
    // PROCESSAMENTO DOS DEPENDENTES
    // =====================================================
    
    $dependentesJson = '[]'; // Array vazio por padrão
    
    if (isset($dados['dependentes']) && is_array($dados['dependentes'])) {
        $dependentesProcessados = [];
        
        foreach ($dados['dependentes'] as $index => $dependente) {
            if (!empty($dependente['tipo']) && !empty($dependente['data_nascimento'])) {
                $depProcessado = [
                    'tipo' => trim($dependente['tipo']),
                    'data_nascimento' => trim($dependente['data_nascimento'])
                ];
                
                // CPF do dependente (se fornecido)
                if (!empty($dependente['cpf'])) {
                    $cpfDep = trim($dependente['cpf']);
                    if (validarCPF($cpfDep)) {
                        $depProcessado['cpf'] = $cpfDep;
                    }
                }
                
                // Telefone do dependente
                if (!empty($dependente['telefone'])) {
                    $depProcessado['telefone'] = trim($dependente['telefone']);
                }
                
                $dependentesProcessados[] = $depProcessado;
            }
        }
        
        if (!empty($dependentesProcessados)) {
            $dependentesJson = json_encode($dependentesProcessados, JSON_UNESCAPED_UNICODE);
            logError("Dependentes atualizados", $dependentesProcessados);
        }
    }
    
    // =====================================================
    // ATUALIZAÇÃO NO BANCO DE DADOS
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
    
    // Parâmetros para atualização
    $parametros = [
        ':id' => $agregadoId,
        ':nome' => $dados['nome'],
        ':data_nascimento' => $dados['dataNascimento'],
        ':telefone' => $dados['telefone'],
        ':celular' => $dados['celular'],
        ':email' => $dados['email'] ?? null,
        ':cpf' => $dados['cpf'],
        ':documento' => $dados['documento'] ?? null,
        ':estado_civil' => $dados['estadoCivil'],
        ':data_filiacao' => $dados['dataFiliacao'],
        ':socio_titular_nome' => $dados['socioTitularNome'],
        ':socio_titular_fone' => $dados['socioTitularFone'],
        ':socio_titular_cpf' => $dados['socioTitularCpf'],
        ':socio_titular_email' => $dados['socioTitularEmail'] ?? null,
        ':cep' => $dados['cep'] ?? null,
        ':endereco' => $dados['endereco'],
        ':numero' => $dados['numero'],
        ':bairro' => $dados['bairro'],
        ':cidade' => $dados['cidade'],
        ':estado' => $dados['estado'],
        ':banco' => $dados['banco'],
        ':banco_outro_nome' => ($dados['banco'] === 'outro') ? ($dados['bancoOutroNome'] ?? null) : null,
        ':agencia' => $dados['agencia'],
        ':conta_corrente' => $dados['contaCorrente'],
        ':dependentes' => $dependentesJson
    ];
    
    logError("Parâmetros para atualização", $parametros);
    
    // Executa a atualização
    if ($stmt->execute($parametros)) {
        $linhasAfetadas = $stmt->rowCount();
        
        if ($linhasAfetadas === 0) {
            logError("Nenhuma linha foi atualizada", ['id' => $agregadoId]);
            
            // Verifica se foi porque não houve mudanças ou se houve erro
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Nenhuma alteração foi necessária (dados idênticos)',
                'data' => [
                    'id' => $agregadoId,
                    'linhas_afetadas' => 0
                ]
            ]);
            exit;
        }
        
        logError("Sócio agregado atualizado", [
            'id' => $agregadoId,
            'linhas_afetadas' => $linhasAfetadas,
            'nome' => $dados['nome']
        ]);
        
        // =====================================================
        // BUSCA DADOS ATUALIZADOS PARA RESPOSTA
        // =====================================================
        
        $stmtConsulta = $db->prepare("
            SELECT id, nome, cpf, telefone, celular, email,
                   socio_titular_nome, valor_contribuicao, 
                   data_filiacao, situacao, data_atualizacao,
                   JSON_LENGTH(COALESCE(dependentes, '[]')) as total_dependentes,
                   banco, agencia, conta_corrente
            FROM Socios_Agregados 
            WHERE id = ? AND ativo = 1
        ");
        $stmtConsulta->execute([$agregadoId]);
        $dadosAtualizados = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
        
        // =====================================================
        // RESPOSTA DE SUCESSO
        // =====================================================
        
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
                'valor_contribuicao' => $dadosAtualizados['valor_contribuicao'],
                'data_filiacao' => $dadosAtualizados['data_filiacao'],
                'situacao' => $dadosAtualizados['situacao'],
                'total_dependentes' => (int)$dadosAtualizados['total_dependentes'],
                'banco' => $dadosAtualizados['banco'],
                'agencia' => $dadosAtualizados['agencia'],
                'conta_corrente' => $dadosAtualizados['conta_corrente'],
                'data_atualizacao' => $dadosAtualizados['data_atualizacao'],
                'linhas_afetadas' => $linhasAfetadas
            ],
            'debug' => [
                'dependentes_json' => $dependentesJson,
                'registro_original' => $registroAtual,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // Erro na atualização
        $errorInfo = $stmt->errorInfo();
        logError("Erro na atualização SQL", $errorInfo);
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro interno ao atualizar sócio agregado',
            'debug' => [
                'sql_error' => $errorInfo[2] ?? 'Erro desconhecido',
                'sql_state' => $errorInfo[0] ?? '',
                'error_code' => $errorInfo[1] ?? ''
            ]
        ]);
    }
    
} catch (PDOException $e) {
    logError("Erro PDO", ['message' => $e->getMessage(), 'code' => $e->getCode()]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro de banco de dados',
        'debug' => [
            'error_type' => 'PDO Exception',
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode()
        ]
    ]);
    
} catch (Exception $e) {
    logError("Erro geral", ['message' => $e->getMessage(), 'code' => $e->getCode()]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor',
        'debug' => [
            'error_type' => 'General Exception',
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode()
        ]
    ]);
} finally {
    logError("=== FIM ATUALIZAÇÃO SÓCIO AGREGADO ===");
}

/**
 * ==============================================
 * DOCUMENTAÇÃO DA API
 * ==============================================
 * 
 * ENDPOINT: POST/PUT /api/atualizar_agregado.php?id=123
 * 
 * PARÂMETROS:
 * - id (via GET ou POST): ID do sócio agregado a ser atualizado
 * 
 * CAMPOS (todos iguais ao criar_agregado.php):
 * - Todos os campos do formulário podem ser atualizados
 * - Mesmas validações do criar
 * - Dependentes são substituídos completamente
 * 
 * DIFERENÇAS DO CRIAR:
 * - Requer ID existente
 * - Verifica duplicação de CPF (exceto o próprio registro)
 * - Permite atualização parcial de campos
 * - Retorna dados atualizados na resposta
 * 
 * RESPONSES:
 * 
 * SUCESSO (200):
 * {
 *   "status": "success",
 *   "message": "Sócio agregado atualizado com sucesso!",
 *   "data": {
 *     "id": 123,
 *     "nome": "Nome Atualizado",
 *     "cpf": "123.456.789-01",
 *     "total_dependentes": 1,
 *     "data_atualizacao": "2025-01-15 14:30:00",
 *     "linhas_afetadas": 1
 *   }
 * }
 * 
 * ID INVÁLIDO/INEXISTENTE (404):
 * {
 *   "status": "error",
 *   "message": "Sócio agregado não encontrado"
 * }
 * 
 * CPF DUPLICADO (409):
 * {
 *   "status": "error", 
 *   "message": "CPF já cadastrado em outro sócio agregado",
 *   "conflito": {
 *     "nome_existente": "João Silva",
 *     "id_existente": 45
 *   }
 * }
 * 
 * NENHUMA ALTERAÇÃO (200):
 * {
 *   "status": "success",
 *   "message": "Nenhuma alteração foi necessária (dados idênticos)",
 *   "data": {
 *     "id": 123,
 *     "linhas_afetadas": 0
 *   }
 * }
 * 
 * EXEMPLOS DE USO:
 * 
 * 1. Via GET: /api/atualizar_agregado.php?id=123
 * 2. Via POST: Form data com campo 'id' = 123
 * 
 * LOGS:
 * - Todas as operações são logadas
 * - Inclui dados antes/depois da atualização
 * - Erros detalhados para debugging
 */
?>