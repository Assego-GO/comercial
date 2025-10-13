<?php
/**
 * API para Atualizar Sócio Agregado - VERSÃO CORRIGIDA
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
    
    $stmtVerifica = $db->prepare("
        SELECT id, nome, cpf, situacao, data_criacao 
        FROM Socios_Agregados 
        WHERE id = ? AND ativo = 1
    ");
    $stmtVerifica->execute([$agregadoId]);
    
    // ✅ CORREÇÃO: SE NÃO EXISTE, RETORNA ERRO E PARA A EXECUÇÃO
    if ($stmtVerifica->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sócio agregado não encontrado',
            'debug' => [
                'id_buscado' => $agregadoId,
                'sugestao' => 'Verifique se o ID está correto ou se o registro foi excluído'
            ]
        ]);
        logError("Agregado não encontrado para atualização", ['id' => $agregadoId]);
        exit; // ⚠️ CRÍTICO: Para a execução aqui
    }
    
    // ✅ SÓ CHEGA AQUI SE O REGISTRO FOI ENCONTRADO
    $registroAtual = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    logError("Registro encontrado", $registroAtual);
    
    // =====================================================
    // CAPTURA E VALIDAÇÃO DOS DADOS
    // =====================================================
    
    $dadosRecebidos = $_POST;
    logError("Dados recebidos para atualização", $dadosRecebidos);
    
    // Remove o ID dos dados
    unset($dadosRecebidos['id']);
    
    // Limpa dados
    $dados = limparDados($dadosRecebidos);
    
    // =============================
    // ✅ VALIDAÇÃO E BUSCA COMPLETA DO TITULAR
    // =============================
    
    $cpfTitularRecebido = null;
    
    // ✅ ACEITA TANTO cpfTitular QUANTO socioTitularCpf
    if (!empty($dados['socioTitularCpf'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['socioTitularCpf']);
    } elseif (!empty($dados['cpfTitular'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['cpfTitular']);
    }
    
    if ($cpfTitularRecebido) {
        // ✅ BUSCA DADOS COMPLETOS DO TITULAR NO BANCO
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
                'message' => 'Sócio titular não encontrado na base de associados',
                'debug' => 'CPF informado: ' . $cpfTitularRecebido
            ]);
            logError('Sócio titular não encontrado', ['cpf' => $cpfTitularRecebido]);
            exit;
        }
        
        if (strtolower($titular['situacao']) !== 'filiado') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Só é permitido vincular agregados a titulares filiados',
                'debug' => 'Situação do titular: ' . $titular['situacao']
            ]);
            logError('Titular não está filiado', ['cpf' => $cpfTitularRecebido, 'situacao' => $titular['situacao']]);
            exit;
        }
        
        // ✅ SOBRESCREVE DADOS DO TITULAR COM OS DADOS DO BANCO
        $dados['socioTitularNome'] = $titular['nome'];
        $dados['socioTitularCpf'] = $titular['cpf'];
        $dados['socioTitularFone'] = $titular['telefone'] ?? '';
        $dados['socioTitularEmail'] = $titular['email'] ?? '';
        
        logError("✓ Titular validado e dados carregados", [
            'titular_id' => $titular['id'],
            'titular_nome' => $titular['nome'],
            'titular_cpf' => $titular['cpf']
        ]);
    }
    
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
    
    foreach ($camposObrigatorios as $campo => $nomeCampo) {
        if (empty($dados[$campo])) {
            $errosValidacao[] = "Campo obrigatório: {$nomeCampo}";
        }
    }
    
    if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
        $errosValidacao[] = "CPF inválido";
    }
    
    if (!validarEmail($dados['email'] ?? '')) {
        $errosValidacao[] = "E-mail inválido";
    }
    
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
                'message' => 'CPF já cadastrado em outro sócio agregado',
                'conflito' => [
                    'nome_existente' => $duplicado['nome'],
                    'id_existente' => $duplicado['id']
                ]
            ]);
            logError("CPF duplicado", ['cpf' => $dados['cpf'], 'conflito_com' => $duplicado['nome']]);
            exit;
        }
    }
    
    // =====================================================
    // PROCESSAMENTO DOS DEPENDENTES
    // =====================================================
    
    $dependentesJson = '[]';
    
    if (isset($dados['dependentes']) && is_array($dados['dependentes'])) {
        $dependentesProcessados = [];
        
        foreach ($dados['dependentes'] as $index => $dependente) {
            if (!empty($dependente['tipo']) && !empty($dependente['data_nascimento'])) {
                $depProcessado = [
                    'tipo' => trim($dependente['tipo']),
                    'data_nascimento' => trim($dependente['data_nascimento'])
                ];
                
                if (!empty($dependente['cpf'])) {
                    $cpfDep = trim($dependente['cpf']);
                    if (validarCPF($cpfDep)) {
                        $depProcessado['cpf'] = $cpfDep;
                    }
                }
                
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
        ':socio_titular_nome' => $dados['socioTitularNome'], // ✅ Dados do banco
        ':socio_titular_fone' => $dados['socioTitularFone'], // ✅ Dados do banco
        ':socio_titular_cpf' => preg_replace('/\D/', '', $dados['socioTitularCpf']), // ✅ Dados do banco
        ':socio_titular_email' => $dados['socioTitularEmail'] ?? null, // ✅ Dados do banco
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
    
    logError("Parâmetros para atualização (COM DADOS DO TITULAR)", [
        'id' => $agregadoId,
        'nome_agregado' => $parametros[':nome'],
        'titular_nome' => $parametros[':socio_titular_nome'],
        'titular_cpf' => $parametros[':socio_titular_cpf']
    ]);
    
    if ($stmt->execute($parametros)) {
        $linhasAfetadas = $stmt->rowCount();
        
        logError("✓ Sócio agregado atualizado", [
            'id' => $agregadoId,
            'linhas_afetadas' => $linhasAfetadas,
            'nome' => $dados['nome']
        ]);
        
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
        
        // Resposta de sucesso
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
            ],
            'debug' => [
                'titular_validado' => [
                    'nome' => $dados['socioTitularNome'],
                    'cpf' => $dados['socioTitularCpf']
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
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
?>