<?php
/**
 * API para Criar Sócio Agregado
 * api/criar_agregado.php
 */

// Headers CORS e Content-Type
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit;
}

// Includes necessários
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';

// Função para logar erros
function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - CRIAR_AGREGADO - " . $message;
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
    if (empty($email)) return true; // Email é opcional
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para limpar e formatar dados
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
    logError("=== INÍCIO CRIAÇÃO SÓCIO AGREGADO ===");
    
    // Captura dados do POST
    $dadosRecebidos = $_POST;
    logError("Dados recebidos", $dadosRecebidos);
    
    // Limpa e valida dados básicos
    $dados = limparDados($dadosRecebidos);
    
    // =====================================================
    // VALIDAÇÕES OBRIGATÓRIAS
    // =====================================================
    
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
    
    // Verifica campos obrigatórios
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
    
    // Se banco for "outro", precisa do nome
    if (($dados['banco'] ?? '') === 'outro' && empty($dados['bancoOutroNome'])) {
        $errosValidacao[] = "Nome do banco é obrigatório quando selecionado 'Outro'";
    }
    
    // Se há erros de validação, retorna
    if (!empty($errosValidacao)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Dados inválidos',
            'errors' => $errosValidacao,
            'debug' => 'Falha na validação dos campos obrigatórios'
        ]);
        logError("Erros de validação", $errosValidacao);
        exit;
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
                
                // Adiciona CPF se fornecido e válido
                if (!empty($dependente['cpf'])) {
                    $cpfDep = trim($dependente['cpf']);
                    if (validarCPF($cpfDep)) {
                        $depProcessado['cpf'] = $cpfDep;
                    }
                }
                
                // Adiciona telefone se fornecido
                if (!empty($dependente['telefone'])) {
                    $depProcessado['telefone'] = trim($dependente['telefone']);
                }
                
                $dependentesProcessados[] = $depProcessado;
            }
        }
        
        if (!empty($dependentesProcessados)) {
            $dependentesJson = json_encode($dependentesProcessados, JSON_UNESCAPED_UNICODE);
            logError("Dependentes processados", $dependentesProcessados);
        }
    }
    
    // =====================================================
    // CONEXÃO COM BANCO DE DADOS
    // =====================================================
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Verifica se CPF já existe
    $stmtVerifica = $db->prepare("SELECT id, nome FROM Socios_Agregados WHERE cpf = ? AND ativo = 1");
    $stmtVerifica->execute([$dados['cpf']]);
    
    if ($stmtVerifica->rowCount() > 0) {
        $existente = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'CPF já cadastrado como sócio agregado',
            'conflito' => [
                'nome_existente' => $existente['nome'],
                'id_existente' => $existente['id']
            ]
        ]);
        logError("CPF já existe", ['cpf' => $dados['cpf'], 'nome_existente' => $existente['nome']]);
        exit;
    }
    
    // =====================================================
    // INSERÇÃO NO BANCO DE DADOS
    // =====================================================
    
    $sql = "INSERT INTO Socios_Agregados (
        nome, data_nascimento, telefone, celular, email, cpf, documento, 
        estado_civil, data_filiacao,
        socio_titular_nome, socio_titular_fone, socio_titular_cpf, socio_titular_email,
        cep, endereco, numero, bairro, cidade, estado,
        banco, banco_outro_nome, agencia, conta_corrente,
        dependentes, situacao, valor_contribuicao,
        data_criacao, data_atualizacao
    ) VALUES (
        :nome, :data_nascimento, :telefone, :celular, :email, :cpf, :documento,
        :estado_civil, :data_filiacao,
        :socio_titular_nome, :socio_titular_fone, :socio_titular_cpf, :socio_titular_email,
        :cep, :endereco, :numero, :bairro, :cidade, :estado,
        :banco, :banco_outro_nome, :agencia, :conta_corrente,
        :dependentes, :situacao, :valor_contribuicao,
        NOW(), NOW()
    )";
    
    $stmt = $db->prepare($sql);
    
    // Parâmetros para a inserção
    $parametros = [
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
        ':dependentes' => $dependentesJson,
        ':situacao' => 'ativo',
        ':valor_contribuicao' => 86.55
    ];
    
    logError("Parâmetros para inserção", $parametros);
    
    // Executa a inserção
    if ($stmt->execute($parametros)) {
        $agregadoId = $db->lastInsertId();
        
        logError("Sócio agregado criado com sucesso", [
            'id' => $agregadoId,
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf']
        ]);
        
        // =====================================================
        // RESPOSTA DE SUCESSO
        // =====================================================
        
        // Busca os dados inseridos para confirmação
        $stmtConsulta = $db->prepare("
            SELECT id, nome, cpf, telefone, celular, socio_titular_nome, 
                   valor_contribuicao, data_filiacao, situacao,
                   JSON_LENGTH(COALESCE(dependentes, '[]')) as total_dependentes
            FROM Socios_Agregados 
            WHERE id = ?
        ");
        $stmtConsulta->execute([$agregadoId]);
        $dadosCriados = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Sócio agregado cadastrado com sucesso!',
            'data' => [
                'id' => $agregadoId,
                'nome' => $dadosCriados['nome'],
                'cpf' => $dadosCriados['cpf'],
                'telefone' => $dadosCriados['telefone'],
                'celular' => $dadosCriados['celular'],
                'socio_titular' => $dadosCriados['socio_titular_nome'],
                'valor_contribuicao' => $dadosCriados['valor_contribuicao'],
                'data_filiacao' => $dadosCriados['data_filiacao'],
                'situacao' => $dadosCriados['situacao'],
                'total_dependentes' => (int)$dadosCriados['total_dependentes']
            ],
            'debug' => [
                'dependentes_json' => $dependentesJson,
                'banco_processado' => $dados['banco'],
                'banco_outro' => ($dados['banco'] === 'outro') ? ($dados['bancoOutroNome'] ?? null) : null,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // Erro na inserção
        $errorInfo = $stmt->errorInfo();
        logError("Erro na inserção SQL", $errorInfo);
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro interno do servidor ao criar sócio agregado',
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
    logError("=== FIM CRIAÇÃO SÓCIO AGREGADO ===");
}

/**
 * ==============================================
 * DOCUMENTAÇÃO DA API
 * ==============================================
 * 
 * ENDPOINT: POST /api/criar_agregado.php
 * 
 * CAMPOS OBRIGATÓRIOS:
 * - nome (string): Nome completo
 * - dataNascimento (date): Data nascimento (YYYY-MM-DD)
 * - telefone (string): Telefone fixo
 * - celular (string): Celular
 * - cpf (string): CPF (com ou sem formatação)
 * - estadoCivil (string): solteiro, casado, divorciado, separado_judicial, viuvo, outro
 * - dataFiliacao (date): Data filiação (YYYY-MM-DD)
 * - socioTitularNome (string): Nome do sócio titular
 * - socioTitularFone (string): Telefone do titular
 * - socioTitularCpf (string): CPF do titular
 * - endereco (string): Endereço
 * - numero (string): Número
 * - bairro (string): Bairro
 * - cidade (string): Cidade
 * - estado (string): Estado (sigla)
 * - banco (string): itau, caixa, outro
 * - agencia (string): Agência
 * - contaCorrente (string): Conta corrente
 * 
 * CAMPOS OPCIONAIS:
 * - email (string): E-mail
 * - documento (string): RG, CNH, etc.
 * - cep (string): CEP
 * - socioTitularEmail (string): E-mail do titular
 * - bancoOutroNome (string): Nome do banco (quando banco=outro)
 * - dependentes (array): Array de dependentes
 * 
 * FORMATO DOS DEPENDENTES:
 * dependentes[0][tipo] = esposa_companheira
 * dependentes[0][data_nascimento] = 1985-03-15
 * dependentes[0][cpf] = 123.456.789-00 (opcional)
 * dependentes[0][telefone] = (62) 99999-0000 (opcional)
 * 
 * TIPOS DE DEPENDENTES:
 * - esposa_companheira
 * - marido_companheiro  
 * - filho_menor_18
 * - filha_menor_18
 * - filho_estudante
 * - filha_estudante
 * 
 * RESPONSES:
 * 
 * SUCESSO (201):
 * {
 *   "status": "success",
 *   "message": "Sócio agregado cadastrado com sucesso!",
 *   "data": {
 *     "id": 123,
 *     "nome": "Nome Completo",
 *     "cpf": "123.456.789-01",
 *     "valor_contribuicao": "86.55",
 *     "situacao": "ativo",
 *     "total_dependentes": 2
 *   }
 * }
 * 
 * ERRO VALIDAÇÃO (400):
 * {
 *   "status": "error", 
 *   "message": "Dados inválidos",
 *   "errors": ["Campo obrigatório: Nome completo"]
 * }
 * 
 * CPF DUPLICADO (409):
 * {
 *   "status": "error",
 *   "message": "CPF já cadastrado como sócio agregado",
 *   "conflito": {
 *     "nome_existente": "João Silva",
 *     "id_existente": 45
 *   }
 * }
 * 
 * ERRO SERVIDOR (500):
 * {
 *   "status": "error",
 *   "message": "Erro interno do servidor"
 * }
 */
?>