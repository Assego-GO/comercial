<?php
/**
 * API para Criar/Atualizar Sócio Agregado - COM SALVAMENTO DE DOCUMENTO
 * api/criar_agregado.php
 * ✅ VERSÃO FINAL COMPLETA
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/agregados/JsonManagerAgregado.php';
require_once '../api/zapsign_agregado_api.php';

function logError($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - CRIAR_AGREGADO - " . $message;
    if ($data) {
        $logMessage .= " - " . json_encode($data, JSON_UNESCAPED_UNICODE);
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
        $dadosLimpos[$key] = is_string($value) ? trim($value) : $value;
    }
    return $dadosLimpos;
}

try {
    logError("=== INÍCIO CRIAR/ATUALIZAR AGREGADO ===");
    
    $dadosRecebidos = $_POST;
    
    logError("📦 Campos de data recebidos:", [
        'nasc' => $dadosRecebidos['nasc'] ?? 'VAZIO',
        'dataNascimento' => $dadosRecebidos['dataNascimento'] ?? 'VAZIO',
        'data_nascimento' => $dadosRecebidos['data_nascimento'] ?? 'VAZIO'
    ]);
    
    $dados = limparDados($dadosRecebidos);
    unset($dados['id']);
    
    // =============================
    // COMPATIBILIDADE DE CAMPOS
    // =============================
    
    if (empty($dados['dataNascimento']) && !empty($dados['nasc'])) {
        $dados['dataNascimento'] = $dados['nasc'];
        logError("✓ Campo 'nasc' convertido para 'dataNascimento'");
    }
    
    if (empty($dados['dataNascimento']) && !empty($dados['data_nascimento'])) {
        $dados['dataNascimento'] = $dados['data_nascimento'];
        logError("✓ Campo 'data_nascimento' convertido para 'dataNascimento'");
    }
    
    if (empty($dados['celular']) && !empty($dados['telefone'])) {
        $dados['celular'] = $dados['telefone'];
        logError("✓ Campo 'telefone' copiado para 'celular'");
    }
    
    if (empty($dados['telefone']) && !empty($dados['celular'])) {
        $dados['telefone'] = $dados['celular'];
        logError("✓ Campo 'celular' copiado para 'telefone'");
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // =============================
    // VALIDA E BUSCA TITULAR
    // =============================
    
    $cpfTitularRecebido = null;
    if (!empty($dados['socioTitularCpf'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['socioTitularCpf']);
    } elseif (!empty($dados['cpfTitular'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['cpfTitular']);
    }
    
    if (!$cpfTitularRecebido) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'CPF do sócio titular é obrigatório'
        ]);
        exit;
    }
    
    $stmtTitular = $db->prepare("
        SELECT id, nome, cpf, email, telefone, situacao
        FROM Associados 
        WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?
        LIMIT 1
    ");
    $stmtTitular->execute([$cpfTitularRecebido]);
    $titular = $stmtTitular->fetch(PDO::FETCH_ASSOC);
    
    if (!$titular) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sócio titular não encontrado',
            'debug' => 'CPF: ' . $cpfTitularRecebido
        ]);
        exit;
    }
    
    if (strtolower($titular['situacao']) !== 'filiado') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Titular deve estar filiado',
            'debug' => 'Situação: ' . $titular['situacao']
        ]);
        exit;
    }
    
    $dados['socioTitularNome'] = $titular['nome'];
    $dados['socioTitularCpf'] = $titular['cpf'];
    $dados['socioTitularFone'] = $titular['telefone'] ?? '';
    $dados['socioTitularEmail'] = $titular['email'] ?? '';
    
    logError("✓ Titular validado", ['nome' => $titular['nome']]);
    
    // =============================
    // VALIDAÇÕES OBRIGATÓRIAS
    // =============================
    
    $camposObrigatorios = [
        'nome' => 'Nome completo',
        'dataNascimento' => 'Data de nascimento',
        'telefone' => 'Telefone',
        'celular' => 'Celular',
        'cpf' => 'CPF',
        'estadoCivil' => 'Estado civil',
        'dataFiliacao' => 'Data de filiação',
        'endereco' => 'Endereço',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade'
    ];
    
    $errosValidacao = [];
    
    foreach ($camposObrigatorios as $campo => $nomeCampo) {
        if (empty($dados[$campo])) {
            $errosValidacao[] = "Campo obrigatório: {$nomeCampo}";
            logError("❌ Campo vazio: $campo");
        }
    }
    
    if (empty($dados['estado'])) {
        $dados['estado'] = 'GO';
        logError("✓ Estado preenchido automaticamente como 'GO'");
    }
    
    if (empty($dados['banco'])) {
        $dados['banco'] = 'Não informado';
    }
    if (empty($dados['agencia'])) {
        $dados['agencia'] = '';
    }
    if (empty($dados['contaCorrente'])) {
        $dados['contaCorrente'] = '';
    }
    
    if (($dados['banco'] ?? '') === 'outro' && empty($dados['bancoOutroNome'])) {
        $errosValidacao[] = "Nome do banco obrigatório quando 'Outro'";
    }
    
    if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
        $errosValidacao[] = "CPF inválido";
    }
    
    if (!validarEmail($dados['email'] ?? '')) {
        $errosValidacao[] = "E-mail inválido";
    }
    
    if (!empty($errosValidacao)) {
        logError("❌ Validação falhou", $errosValidacao);
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Dados inválidos',
            'errors' => $errosValidacao
        ]);
        exit;
    }
    
    // =============================
    // PROCESSA DEPENDENTES
    // =============================
    
    $dependentesJson = '[]';
    
    if (isset($dados['dependentes']) && is_array($dados['dependentes'])) {
        $dependentesProcessados = [];
        
        foreach ($dados['dependentes'] as $dependente) {
            if (!empty($dependente['tipo']) && !empty($dependente['data_nascimento'])) {
                $dep = [
                    'tipo' => trim($dependente['tipo']),
                    'data_nascimento' => trim($dependente['data_nascimento'])
                ];
                
                if (!empty($dependente['cpf']) && validarCPF($dependente['cpf'])) {
                    $dep['cpf'] = trim($dependente['cpf']);
                }
                
                if (!empty($dependente['telefone'])) {
                    $dep['telefone'] = trim($dependente['telefone']);
                }
                
                $dependentesProcessados[] = $dep;
            }
        }
        
        if (!empty($dependentesProcessados)) {
            $dependentesJson = json_encode($dependentesProcessados, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // =============================
    // VERIFICA SE JÁ EXISTE SIM
    // =============================
    
    $cpfAgregado = $dados['cpf'];
    $stmtVerifica = $db->prepare("
        SELECT id, nome 
        FROM Socios_Agregados 
        WHERE cpf = ? AND ativo = 1
    ");
    $stmtVerifica->execute([$cpfAgregado]);
    $agregadoExistente = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    
    $isUpdate = false;
    $agregadoId = null;
    
    if ($agregadoExistente) {
        $isUpdate = true;
        $agregadoId = $agregadoExistente['id'];
        logError("✓ Modo UPDATE - ID: " . $agregadoId);
    } else {
        logError("✓ Modo CREATE");
    }
    
    // =============================
    // PREPARA PARÂMETROS
    // =============================
    
    $parametros = [
        ':nome' => $dados['nome'],
        ':data_nascimento' => $dados['dataNascimento'],
        ':telefone' => $dados['telefone'],
        ':celular' => $dados['celular'],
        ':email' => $dados['email'] ?? null,
        ':cpf' => $cpfAgregado,
        ':documento' => $dados['documento'] ?? null,
        ':estado_civil' => $dados['estadoCivil'],
        ':data_filiacao' => $dados['dataFiliacao'],
        ':socio_titular_nome' => $dados['socioTitularNome'],
        ':socio_titular_fone' => $dados['socioTitularFone'],
        ':socio_titular_cpf' => preg_replace('/\D/', '', $dados['socioTitularCpf']),
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
    
    // =============================
    // EXECUTA CREATE OU UPDATE
    // =============================
    
    if ($isUpdate) {
        $sql = "UPDATE Socios_Agregados SET 
            nome = :nome, data_nascimento = :data_nascimento,
            telefone = :telefone, celular = :celular, email = :email,
            cpf = :cpf, documento = :documento, estado_civil = :estado_civil,
            data_filiacao = :data_filiacao,
            socio_titular_nome = :socio_titular_nome,
            socio_titular_fone = :socio_titular_fone,
            socio_titular_cpf = :socio_titular_cpf,
            socio_titular_email = :socio_titular_email,
            cep = :cep, endereco = :endereco, numero = :numero,
            bairro = :bairro, cidade = :cidade, estado = :estado,
            banco = :banco, banco_outro_nome = :banco_outro_nome,
            agencia = :agencia, conta_corrente = :conta_corrente,
            dependentes = :dependentes, data_atualizacao = NOW()
        WHERE id = :id AND ativo = 1";
        
        $parametros[':id'] = $agregadoId;
        $stmt = $db->prepare($sql);
        
        if (!$stmt->execute($parametros)) {
            throw new Exception('Erro ao atualizar agregado');
        }
        
        logError("✅ Agregado atualizado - ID: " . $agregadoId);
        
    } else {
        $sql = "INSERT INTO Socios_Agregados (
            nome, data_nascimento, telefone, celular, email, cpf, documento,
            estado_civil, data_filiacao,
            socio_titular_nome, socio_titular_fone, socio_titular_cpf, socio_titular_email,
            cep, endereco, numero, bairro, cidade, estado,
            banco, banco_outro_nome, agencia, conta_corrente,
            dependentes, situacao, valor_contribuicao,
            data_criacao, data_atualizacao, ativo
        ) VALUES (
            :nome, :data_nascimento, :telefone, :celular, :email, :cpf, :documento,
            :estado_civil, :data_filiacao,
            :socio_titular_nome, :socio_titular_fone, :socio_titular_cpf, :socio_titular_email,
            :cep, :endereco, :numero, :bairro, :cidade, :estado,
            :banco, :banco_outro_nome, :agencia, :conta_corrente,
            :dependentes, 'inativo', 86.55,
            NOW(), NOW(), 1
        )";
        
        $stmt = $db->prepare($sql);
        
        if (!$stmt->execute($parametros)) {
            throw new Exception('Erro ao criar agregado');
        }
        
        $agregadoId = $db->lastInsertId();
        logError("✅ Agregado criado - ID: " . $agregadoId);
    }
    
    // =============================
    // SALVA JSON
    // =============================
    
    $resultadoJson = ['sucesso' => false, 'erro' => 'Não processado'];
    
    try {
        $jsonManager = new JsonManagerAgregado();
        $operacao = $isUpdate ? 'UPDATE' : 'CREATE';
        $resultadoJson = $jsonManager->salvarAgregadoJson($dados, $agregadoId, $operacao);
        
        if ($resultadoJson['sucesso']) {
            logError("✓ JSON salvo");
        }
    } catch (Exception $e) {
        $resultadoJson = ['sucesso' => false, 'erro' => $e->getMessage()];
        logError("⚠ Erro JSON: " . $e->getMessage());
    }
    
    // =============================
    // ZAPSIGN (APENAS CREATE)
    // =============================
    
    $resultadoZapSign = ['sucesso' => false, 'erro' => 'Não aplicável'];
    
    if (!$isUpdate) {
        try {
            $dadosCompletos = $jsonManager->obterDadosCompletos($dados, $agregadoId, 'CREATE');
            $resultadoZapSign = enviarAgregadoParaZapSign($dadosCompletos);
            
            if ($resultadoZapSign['sucesso']) {
                logError("✓ ZapSign enviado");
            }
        } catch (Exception $e) {
            $resultadoZapSign = ['sucesso' => false, 'erro' => $e->getMessage()];
            logError("⚠ Erro ZapSign: " . $e->getMessage());
        }
    }
    
    // =============================
    // 🆕 SALVA DOCUMENTO (FICHA)
    // =============================
    
    $documentoId = null;
    
    if (isset($_FILES['ficha_assinada']) && $_FILES['ficha_assinada']['error'] === UPLOAD_ERR_OK) {
        try {
            logError("📄 Processando upload da ficha assinada");
            
            // Upload do arquivo
            $uploadDir = '../uploads/fichas_agregados/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $arquivo = $_FILES['ficha_assinada'];
            $nomeArquivo = 'ficha_agregado_' . $agregadoId . '_' . time() . '_' . basename($arquivo['name']);
            $caminhoCompleto = $uploadDir . $nomeArquivo;
            
            if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                logError("✓ Arquivo salvo: " . $caminhoCompleto);
                
                // Insere no banco COM AGREGADO_ID
                $stmtDoc = $db->prepare("
                    INSERT INTO Documentos_Associado (
                        agregado_id, tipo_documento, tipo_origem, caminho_arquivo,
                        status_fluxo, departamento_atual, data_upload
                    ) VALUES (?, 'FICHA_FILIACAO', 'FISICO', ?, 'AGUARDANDO_ASSINATURA', 2, NOW())
                ");
                
                $stmtDoc->execute([
                    $agregadoId,
                    'uploads/fichas_agregados/' . $nomeArquivo
                ]);
                
                $documentoId = $db->lastInsertId();
                
                logError("✅ Documento criado - ID: " . $documentoId);
                
                // Registra no histórico
                $stmtHist = $db->prepare("
                    INSERT INTO DocumentosFluxoHistorico (
                        documento_id, status_anterior, status_novo,
                        departamento_origem, departamento_destino,
                        funcionario_id, observacao, data_acao
                    ) VALUES (?, NULL, 'AGUARDANDO_ASSINATURA', 10, 2, 1, 'Ficha de agregado anexada', NOW())
                ");
                $stmtHist->execute([$documentoId]);
                
                logError("✓ Histórico registrado");
            }
            
        } catch (Exception $e) {
            logError("⚠ Erro ao salvar documento: " . $e->getMessage());
        }
    } else {
        logError("⚠ Nenhuma ficha anexada");
    }
    
    // =============================
    // RESPOSTA
    // =============================
    
    http_response_code($isUpdate ? 200 : 201);
    echo json_encode([
        'status' => 'success',
        'message' => $isUpdate ? 
            'Sócio agregado atualizado com sucesso!' : 
            'Sócio agregado cadastrado com sucesso!',
        'operacao' => $isUpdate ? 'UPDATE' : 'CREATE',
        'data' => [
            'id' => $agregadoId,
            'associado_id' => $agregadoId,
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf'],
            'documento_id' => $documentoId
        ],
        'json_export' => [
            'salvo' => $resultadoJson['sucesso']
        ],
        'zapsign' => [
            'enviado' => $resultadoZapSign['sucesso']
        ],
        'documento' => [
            'criado' => $documentoId !== null,
            'id' => $documentoId
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    logError("=== SUCESSO ===");
    
} catch (Exception $e) {
    logError("❌ ERRO: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>