<?php
/**
 * API para Criar Sócio Agregado - VERSÃO COM FLUXO COMPLETO
 * api/criar_agregado.php
 * 
 * Fluxo: Formulário → Banco → JSON → ZapSign
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

// ✅ NOVOS INCLUDES - JsonManager e ZapSign para Agregados
require_once '../classes/agregados/JsonManagerAgregado.php';
require_once '../api/zapsign_agregado_api.php';

// Função para logar erros
function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - CRIAR_AGREGADO_COMPLETO - " . $message;
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
    logError("=== INÍCIO CRIAÇÃO SÓCIO AGREGADO - FLUXO COMPLETO ===");
    
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
    if (!$stmt->execute($parametros)) {
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
        exit;
    }
    
    $agregadoId = $db->lastInsertId();
    
    logError("Sócio agregado criado com sucesso no banco", [
        'id' => $agregadoId,
        'nome' => $dados['nome'],
        'cpf' => $dados['cpf']
    ]);

    // =====================================
    // ✅ PASSO 1: SALVA DADOS EM JSON
    // =====================================
    
    $resultadoJson = ['sucesso' => false, 'erro' => 'Não processado'];
    
    try {
        logError("=== INICIANDO SALVAMENTO EM JSON (AGREGADO) ===");
        
        $jsonManager = new JsonManagerAgregado();
        $resultadoJson = $jsonManager->salvarAgregadoJson($dados, $agregadoId, 'CREATE');
        
        if ($resultadoJson['sucesso']) {
            logError("✓ JSON do agregado salvo com sucesso: " . $resultadoJson['arquivo_individual']);
            logError("✓ Tamanho do arquivo: " . $resultadoJson['tamanho_bytes'] . " bytes");
        } else {
            logError("⚠ Erro ao salvar JSON do agregado: " . $resultadoJson['erro']);
        }
        
    } catch (Exception $e) {
        $resultadoJson = [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
        logError("✗ ERRO CRÍTICO ao salvar JSON do agregado: " . $e->getMessage());
        // Não falha a operação por causa do JSON
    }

    // =====================================
    // ✅ PASSO 2: ENVIA PARA ZAPSIGN
    // =====================================
    
    $resultadoZapSign = ['sucesso' => false, 'erro' => 'Não processado'];
    
    try {
        logError("=== INICIANDO ENVIO AGREGADO PARA ZAPSIGN ===");
        
        // ✅ VERIFICA SE O ARQUIVO DA API EXISTE
        $arquivoZapSign = '../api/zapsign_agregado_api.php';
        if (!file_exists($arquivoZapSign)) {
            throw new Exception("Arquivo zapsign_agregado_api.php não encontrado: " . $arquivoZapSign);
        }
        
        // ✅ VERIFICA SE A FUNÇÃO EXISTE
        if (!function_exists('enviarAgregadoParaZapSign')) {
            throw new Exception("Função enviarAgregadoParaZapSign() não encontrada. Verifique se o arquivo foi incluído corretamente.");
        }
        
        // ✅ VERIFICA SE O MÉTODO DO JSONMANAGER EXISTE
        if (!method_exists($jsonManager, 'obterDadosCompletos')) {
            throw new Exception("Método obterDadosCompletos() não encontrado na classe JsonManagerAgregado.");
        }
        
        // ✅ USA A FUNÇÃO DO JSONMANAGER PARA PREPARAR DADOS
        $dadosCompletos = $jsonManager->obterDadosCompletos($dados, $agregadoId, 'CREATE');
        
        logError("Dados completos do agregado preparados. Seções: " . implode(', ', array_keys($dadosCompletos)));
        
        // ✅ ENVIA PARA ZAPSIGN
        $resultadoZapSign = enviarAgregadoParaZapSign($dadosCompletos);
        
        if ($resultadoZapSign['sucesso']) {
            logError("✓ ZapSign agregado enviado com sucesso!");
            logError("✓ Documento ID: " . ($resultadoZapSign['documento_id'] ?? 'N/A'));
            logError("✓ Link assinatura: " . ($resultadoZapSign['link_assinatura'] ?? 'N/A'));
            
            // ✅ ATUALIZA BANCO COM DADOS DO ZAPSIGN
            try {
                $stmt = $db->prepare("
                    UPDATE Socios_Agregados 
                    SET observacoes = CONCAT(COALESCE(observacoes, ''), '\n=== ZAPSIGN ===\n',
                        'Documento ID: ', ?, '\n',
                        'Link: ', ?, '\n',
                        'Enviado: ', NOW())
                    WHERE id = ?
                ");
                $stmt->execute([
                    $resultadoZapSign['documento_id'],
                    $resultadoZapSign['link_assinatura'],
                    $agregadoId
                ]);
                logError("✓ Dados ZapSign salvos no banco do agregado");
            } catch (Exception $e) {
                logError("⚠ Erro ao salvar dados ZapSign no banco do agregado: " . $e->getMessage());
            }
            
        } else {
            logError("⚠ Erro ao enviar agregado para ZapSign: " . $resultadoZapSign['erro']);
        }
        
    } catch (Exception $e) {
        $resultadoZapSign = [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
        logError("✗ ERRO CRÍTICO no ZapSign do agregado: " . $e->getMessage());
        // Não falha a operação por causa do ZapSign
    }

    // =====================================
    // BUSCA DADOS FINAIS PARA RESPOSTA
    // =====================================
    
    $stmtConsulta = $db->prepare("
        SELECT id, nome, cpf, telefone, celular, email,
               socio_titular_nome, valor_contribuicao, 
               data_filiacao, situacao, data_criacao,
               JSON_LENGTH(COALESCE(dependentes, '[]')) as total_dependentes,
               banco, agencia, conta_corrente
        FROM Socios_Agregados 
        WHERE id = ? AND ativo = 1
    ");
    $stmtConsulta->execute([$agregadoId]);
    $dadosCriados = $stmtConsulta->fetch(PDO::FETCH_ASSOC);

    // =====================================
    // RESPOSTA FINAL
    // =====================================

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
            'email' => $dadosCriados['email'],
            'socio_titular' => $dadosCriados['socio_titular_nome'],
            'valor_contribuicao' => $dadosCriados['valor_contribuicao'],
            'data_filiacao' => $dadosCriados['data_filiacao'],
            'situacao' => $dadosCriados['situacao'],
            'total_dependentes' => (int)$dadosCriados['total_dependentes'],
            'banco' => $dadosCriados['banco'],
            'agencia' => $dadosCriados['agencia'],
            'conta_corrente' => $dadosCriados['conta_corrente']
        ],
        
        // ✅ SEÇÃO JSON
        'json_export' => [
            'salvo' => $resultadoJson['sucesso'],
            'arquivo' => $resultadoJson['arquivo_individual'] ?? null,
            'tamanho_bytes' => $resultadoJson['tamanho_bytes'] ?? 0,
            'timestamp' => $resultadoJson['timestamp'] ?? null,
            'erro' => $resultadoJson['sucesso'] ? null : $resultadoJson['erro'],
            'pronto_para_zapsign' => $resultadoJson['sucesso']
        ],
        
        // ✅ SEÇÃO ZAPSIGN
        'zapsign' => [
            'enviado' => $resultadoZapSign['sucesso'],
            'documento_id' => $resultadoZapSign['documento_id'] ?? null,
            'link_assinatura' => $resultadoZapSign['link_assinatura'] ?? null,
            'erro' => $resultadoZapSign['sucesso'] ? null : $resultadoZapSign['erro'],
            'http_code' => $resultadoZapSign['http_code'] ?? null,
            'status' => $resultadoZapSign['sucesso'] ? 'ENVIADO' : 'ERRO',
            'template_tipo' => 'socio_agregado'
        ],
        
        'debug' => [
            'dependentes_json' => $dependentesJson,
            'banco_processado' => $dados['banco'],
            'banco_outro' => ($dados['banco'] === 'outro') ? ($dados['bancoOutroNome'] ?? null) : null,
            'fluxo_completo' => [
                'banco' => 'OK',
                'json' => $resultadoJson['sucesso'] ? 'OK' : 'FALHOU',
                'zapsign' => $resultadoZapSign['sucesso'] ? 'OK' : 'FALHOU'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    // ✅ Atualiza mensagens de sucesso
    $mensagemFinal = 'Sócio agregado cadastrado com sucesso!';
    
    if ($resultadoJson['sucesso']) {
        $mensagemFinal .= ' Dados exportados para integração.';
    }
    
    if ($resultadoZapSign['sucesso']) {
        $mensagemFinal .= ' Documento enviado para assinatura eletrônica.';
    }

    logError("=== SÓCIO AGREGADO CRIADO COM SUCESSO (FLUXO COMPLETO) ===");
    logError("ID: {$agregadoId} | Nome: " . $dados['nome']);
    logError("JSON: " . ($resultadoJson['sucesso'] ? '✓ Salvo' : '✗ Falhou') . " | Arquivo: " . ($resultadoJson['arquivo_individual'] ?? 'N/A'));
    logError("ZapSign: " . ($resultadoZapSign['sucesso'] ? '✓ Enviado' : '✗ Falhou') . " | Doc ID: " . ($resultadoZapSign['documento_id'] ?? 'N/A'));

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
    logError("=== FIM CRIAÇÃO SÓCIO AGREGADO (FLUXO COMPLETO) ===");
}

/**
 * ==============================================
 * DOCUMENTAÇÃO DA API COMPLETA
 * ==============================================
 * 
 * ENDPOINT: POST /api/criar_agregado.php
 * 
 * FLUXO COMPLETO:
 * 1. Validação dos dados
 * 2. Inserção no banco (tabela Socios_Agregados)
 * 3. Salvamento em JSON (classes/agregado/JsonManagerAgregado.php)
 * 4. Envio para ZapSign (api/zapsign_agregado_api.php)
 * 
 * CAMPOS OBRIGATÓRIOS:
 * [mesmos do anterior...]
 * 
 * RESPOSTA COMPLETA (201):
 * {
 *   "status": "success",
 *   "message": "Sócio agregado cadastrado com sucesso! Dados exportados. Documento enviado para assinatura.",
 *   "data": {
 *     "id": 123,
 *     "nome": "Nome Completo",
 *     "total_dependentes": 2,
 *     "valor_contribuicao": "86.55"
 *   },
 *   "json_export": {
 *     "salvo": true,
 *     "arquivo": "agregado_000123_2025-01-15_14-30-00.json",
 *     "tamanho_bytes": 2048,
 *     "pronto_para_zapsign": true
 *   },
 *   "zapsign": {
 *     "enviado": true,
 *     "documento_id": "xxx-yyy-zzz",
 *     "link_assinatura": "https://app.zapsign.com.br/...",
 *     "status": "ENVIADO",
 *     "template_tipo": "socio_agregado"
 *   }
 * }
 * 
 * ARQUIVOS NECESSÁRIOS:
 * - classes/agregado/JsonManagerAgregado.php
 * - api/zapsign_agregado_api.php
 * - Tabela Socios_Agregados criada
 * 
 * PASTAS CRIADAS AUTOMATICAMENTE:
 * - data/json_agregados/individual/
 * - data/json_agregados/consolidado/
 * - data/json_agregados/processed/
 * - data/json_agregados/errors/
 * - logs/json_agregados.log
 */
?>