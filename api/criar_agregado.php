<?php
/**
 * API para Criar/Atualizar Agregado - VERSÃO UNIFICADA
 * api/criar_agregado.php
 * ✅ CRIA AGREGADOS NA TABELA ASSOCIADOS (estrutura unificada)
 * ✅ Identifica agregados via Militar.corporacao = 'Agregados'
 * ✅ Vincula ao titular via associado_titular_id
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
require_once '../classes/Associados.php';

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

function converterDataParaMySQL($data) {
    if (empty($data)) return null;
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }
    
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    
    try {
        $dateTime = new DateTime($data);
        return $dateTime->format('Y-m-d');
    } catch (Exception $e) {
        logError("Erro ao converter data: {$data} - " . $e->getMessage());
        return null;
    }
}

try {
    logError("=== INÍCIO CRIAR/ATUALIZAR AGREGADO (TABELA ASSOCIADOS) ===");
    
    $dadosRecebidos = $_POST;
    $dados = limparDados($dadosRecebidos);
    
    // Compatibilidade de campos
    if (empty($dados['nasc']) && !empty($dados['dataNascimento'])) {
        $dados['nasc'] = $dados['dataNascimento'];
    } elseif (empty($dados['nasc']) && !empty($dados['data_nascimento'])) {
        $dados['nasc'] = $dados['data_nascimento'];
    }
    
    if (empty($dados['telefone']) && !empty($dados['celular'])) {
        $dados['telefone'] = $dados['celular'];
    }
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // =============================
    // VALIDA E BUSCA TITULAR
    // =============================
    
    $cpfTitularRecebido = null;
    $titularId = null;
    
    if (!empty($dados['socioTitularCpf'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['socioTitularCpf']);
    } elseif (!empty($dados['cpfTitular'])) {
        $cpfTitularRecebido = preg_replace('/\D/', '', $dados['cpfTitular']);
    } elseif (!empty($dados['associadoTitular'])) {
        // Se vier o ID direto, usa para buscar
        $titularId = intval($dados['associadoTitular']);
    }
    
    if (!$cpfTitularRecebido && empty($titularId)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'CPF ou ID do sócio titular é obrigatório'
        ]);
        exit;
    }
    
    // Busca titular
    if (!empty($titularId)) {
        $stmtTitular = $db->prepare("
            SELECT a.id, a.nome, a.cpf, a.email, a.telefone, a.situacao, m.corporacao
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE a.id = ?
            LIMIT 1
        ");
        $stmtTitular->execute([$titularId]);
    } else {
        $stmtTitular = $db->prepare("
            SELECT a.id, a.nome, a.cpf, a.email, a.telefone, a.situacao, m.corporacao
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
            LIMIT 1
        ");
        $stmtTitular->execute([$cpfTitularRecebido]);
    }
    
    $titular = $stmtTitular->fetch(PDO::FETCH_ASSOC);
    
    if (!$titular) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sócio titular não encontrado'
        ]);
        exit;
    }
    
    // Validações do titular
    if (strtolower($titular['situacao']) !== 'filiado') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Titular deve estar filiado',
            'debug' => 'Situação: ' . $titular['situacao']
        ]);
        exit;
    }
    
    if (!empty($titular['corporacao']) && $titular['corporacao'] === 'Agregados') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'O titular não pode ser um agregado'
        ]);
        exit;
    }
    
    $titularId = $titular['id'];
    logError("✓ Titular validado", ['id' => $titularId, 'nome' => $titular['nome']]);
    
    // =============================
    // VALIDAÇÕES OBRIGATÓRIAS
    // =============================
    
    $camposObrigatorios = [
        'nome' => 'Nome completo',
        'nasc' => 'Data de nascimento',
        'telefone' => 'Telefone',
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
        }
    }
    
    if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
        $errosValidacao[] = "CPF inválido";
    }
    
    if (!validarEmail($dados['email'] ?? '')) {
        $errosValidacao[] = "E-mail inválido";
    }
    
    if (!empty($errosValidacao)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Dados inválidos',
            'errors' => $errosValidacao
        ]);
        exit;
    }
    
    // =============================
    // VERIFICA SE AGREGADO JÁ EXISTE
    // =============================
    
    $cpfAgregado = preg_replace('/\D/', '', $dados['cpf']);
    
    $stmtVerifica = $db->prepare("
        SELECT a.id, a.nome, m.corporacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        WHERE REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = ?
        LIMIT 1
    ");
    $stmtVerifica->execute([$cpfAgregado]);
    $agregadoExistente = $stmtVerifica->fetch(PDO::FETCH_ASSOC);
    
    $isUpdate = false;
    $agregadoId = null;
    
    if ($agregadoExistente) {
        // Se já existe, verifica se é agregado
        if (!empty($agregadoExistente['corporacao']) && $agregadoExistente['corporacao'] === 'Agregados') {
            $isUpdate = true;
            $agregadoId = $agregadoExistente['id'];
            logError("✓ Modo UPDATE - ID: " . $agregadoId);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'CPF já cadastrado como associado regular'
            ]);
            exit;
        }
    } else {
        logError("✓ Modo CREATE");
    }
    
    // =============================
    // PREPARA DADOS PARA ASSOCIADOS
    // =============================
    
    $dadosAssociado = [
        'nome' => $dados['nome'],
        'nasc' => converterDataParaMySQL($dados['nasc']),
        'sexo' => $dados['sexo'] ?? null,
        'rg' => $dados['documento'] ?? $dados['rg'] ?? 'Não informado',
        'cpf' => $cpfAgregado,
        'email' => $dados['email'] ?? null,
        'situacao' => 'Filiado', // Agregado inicia como Filiado
        'escolaridade' => $dados['escolaridade'] ?? null,
        'estadoCivil' => $dados['estadoCivil'],
        'telefone' => preg_replace('/\D/', '', $dados['telefone']),
        'dataFiliacao' => converterDataParaMySQL($dados['dataFiliacao']),
        'associado_titular_id' => $titularId, // ✅ Vínculo com titular
        
        // Endereço
        'cep' => preg_replace('/\D/', '', $dados['cep'] ?? '') ?: null,
        'endereco' => $dados['endereco'],
        'numero' => $dados['numero'],
        'complemento' => $dados['complemento'] ?? null,
        'bairro' => $dados['bairro'],
        'cidade' => $dados['cidade'],
        'estado' => $dados['estado'] ?? 'GO',
        
        // Financeiro
        'tipoAssociado' => 'Agregado',
        'situacaoFinanceira' => 'regular',
        'vinculoServidor' => $dados['vinculoServidor'] ?? 'Ativo',
        'localDebito' => $dados['localDebito'] ?? 'Em Folha',
        'agencia' => $dados['agencia'] ?? null,
        'contaCorrente' => $dados['contaCorrente'] ?? null,
        
        // Militar (forçado como Agregado)
        'corporacao' => 'Agregados', // ✅ Identifica como agregado
        'patente' => 'Agregado',
        'categoria' => 'Agregado'
    ];
    
    // =============================
    // EXECUTA CREATE OU UPDATE
    // =============================
    
    $db->beginTransaction();
    
    try {
        if ($isUpdate) {
            // UPDATE em Associados (sem campos de endereço)
            $sql = "UPDATE Associados SET 
                nome = :nome, nasc = :nasc, sexo = :sexo, rg = :rg,
                email = :email, estadoCivil = :estadoCivil, telefone = :telefone,
                associado_titular_id = :associado_titular_id
            WHERE id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nome' => $dadosAssociado['nome'],
                ':nasc' => $dadosAssociado['nasc'],
                ':sexo' => $dadosAssociado['sexo'],
                ':rg' => $dadosAssociado['rg'],
                ':email' => $dadosAssociado['email'],
                ':estadoCivil' => $dadosAssociado['estadoCivil'],
                ':telefone' => $dadosAssociado['telefone'],
                ':associado_titular_id' => $titularId,
                ':id' => $agregadoId
            ]);
            
            // UPDATE em Endereco
            $stmtEndUpd = $db->prepare("
                UPDATE Endereco SET
                    cep = ?, endereco = ?, numero = ?, complemento = ?,
                    bairro = ?, cidade = ?
                WHERE associado_id = ?
            ");
            $stmtEndUpd->execute([
                $dadosAssociado['cep'],
                $dadosAssociado['endereco'],
                $dadosAssociado['numero'],
                $dadosAssociado['complemento'],
                $dadosAssociado['bairro'],
                $dadosAssociado['cidade'],
                $agregadoId
            ]);
            
            // UPDATE em Financeiro (se existir)
            $stmtFin = $db->prepare("
                UPDATE Financeiro SET
                    tipoAssociado = 'Agregado',
                    situacaoFinanceira = 'regular',
                    vinculoServidor = :vinculoServidor,
                    localDebito = :localDebito,
                    agencia = :agencia,
                    contaCorrente = :contaCorrente
                WHERE associado_id = :id
            ");
            $stmtFin->execute([
                ':vinculoServidor' => $dadosAssociado['vinculoServidor'],
                ':localDebito' => $dadosAssociado['localDebito'],
                ':agencia' => $dadosAssociado['agencia'],
                ':contaCorrente' => $dadosAssociado['contaCorrente'],
                ':id' => $agregadoId
            ]);
            
            logError("✅ Agregado atualizado - ID: " . $agregadoId);
            
        } else {
            // INSERT em Associados (sem campos de endereço - vão em Endereco)
            $sql = "INSERT INTO Associados (
                nome, nasc, sexo, rg, cpf, email, situacao, escolaridade,
                estadoCivil, telefone, associado_titular_id
            ) VALUES (
                :nome, :nasc, :sexo, :rg, :cpf, :email, :situacao, :escolaridade,
                :estadoCivil, :telefone, :associado_titular_id
            )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nome' => $dadosAssociado['nome'],
                ':nasc' => $dadosAssociado['nasc'],
                ':sexo' => $dadosAssociado['sexo'],
                ':rg' => $dadosAssociado['rg'],
                ':cpf' => $dadosAssociado['cpf'],
                ':email' => $dadosAssociado['email'],
                ':situacao' => $dadosAssociado['situacao'],
                ':escolaridade' => $dadosAssociado['escolaridade'],
                ':estadoCivil' => $dadosAssociado['estadoCivil'],
                ':telefone' => $dadosAssociado['telefone'],
                ':associado_titular_id' => $titularId
            ]);
            
            $agregadoId = $db->lastInsertId();
            
            // INSERT em Endereco
            $stmtEnd = $db->prepare("
                INSERT INTO Endereco (associado_id, cep, endereco, numero, complemento, bairro, cidade)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtEnd->execute([
                $agregadoId,
                $dadosAssociado['cep'],
                $dadosAssociado['endereco'],
                $dadosAssociado['numero'],
                $dadosAssociado['complemento'],
                $dadosAssociado['bairro'],
                $dadosAssociado['cidade']
            ]);
            
            // INSERT em Militar (corporacao = 'Agregados')
            $stmtMil = $db->prepare("
                INSERT INTO Militar (associado_id, corporacao, patente, categoria)
                VALUES (?, 'Agregados', 'Agregado', 'Agregado')
            ");
            $stmtMil->execute([$agregadoId]);
            
            // INSERT em Financeiro
            $stmtFin = $db->prepare("
                INSERT INTO Financeiro (
                    associado_id, tipoAssociado, situacaoFinanceira,
                    vinculoServidor, localDebito, agencia, contaCorrente
                ) VALUES (?, 'Agregado', 'regular', ?, ?, ?, ?)
            ");
            $stmtFin->execute([
                $agregadoId,
                $dadosAssociado['vinculoServidor'],
                $dadosAssociado['localDebito'],
                $dadosAssociado['agencia'],
                $dadosAssociado['contaCorrente']
            ]);
            
            // INSERT em Contrato
            $stmtCont = $db->prepare("
                INSERT INTO Contrato (associado_id, dataFiliacao)
                VALUES (?, ?)
            ");
            $stmtCont->execute([
                $agregadoId,
                $dadosAssociado['dataFiliacao']
            ]);
            
            logError("✅ Agregado criado na tabela Associados - ID: " . $agregadoId);
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
    // =============================
    // SALVA DOCUMENTO (se enviado)
    // =============================
    
    $documentoId = null;
    $caminhoDocumento = null;
    
    if (isset($_FILES['ficha_assinada']) && $_FILES['ficha_assinada']['error'] === UPLOAD_ERR_OK) {
        try {
            $pastaBase = '../uploads/documentos/associados/';
            $pastaAgregado = $pastaBase . $agregadoId . '/';
            
            if (!file_exists($pastaAgregado)) {
                mkdir($pastaAgregado, 0755, true);
            }
            
            $arquivo = $_FILES['ficha_assinada'];
            $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
            $nomeArquivo = 'ficha_filiacao_' . date('Ymd_His') . '.' . $extensao;
            $caminhoCompleto = $pastaAgregado . $nomeArquivo;
            
            if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                $caminhoRelativo = str_replace('../', '', $pastaAgregado) . $nomeArquivo;
                $caminhoDocumento = $caminhoRelativo;
                
                // Usa tabela Documentos_Associado (unificada) com status AGUARDANDO_ASSINATURA
                $stmtDoc = $db->prepare("
                    INSERT INTO Documentos_Associado (
                        associado_id, tipo_documento, tipo_origem, nome_arquivo,
                        caminho_arquivo, data_upload, observacao, status_fluxo, verificado
                    ) VALUES (?, 'FICHA_FILIACAO', 'FISICO', ?, ?, NOW(), 'Agregado', 'AGUARDANDO_ASSINATURA', 0)
                ");
                
                $stmtDoc->execute([
                    $agregadoId,
                    $nomeArquivo,
                    $caminhoRelativo
                ]);
                
                $documentoId = $db->lastInsertId();
                logError("✅ Documento salvo com status AGUARDANDO_ASSINATURA - ID: " . $documentoId);
            }
            
        } catch (Exception $e) {
            logError("⚠ Erro ao salvar documento: " . $e->getMessage());
        }
    } else {
        // =============================
        // CRIA DOCUMENTO VIRTUAL SE NÃO HOUVER UPLOAD
        // =============================
        try {
            // Criar registro de documento virtual para controle de assinatura
            $stmtDoc = $db->prepare("
                INSERT INTO Documentos_Associado (
                    associado_id, tipo_documento, tipo_origem, nome_arquivo,
                    caminho_arquivo, data_upload, observacao, status_fluxo, verificado
                ) VALUES (?, 'FICHA_AGREGADO', 'VIRTUAL', 'ficha_virtual.pdf', '', NOW(), 
                          'Agregado - Aguardando assinatura da presidência', 'AGUARDANDO_ASSINATURA', 0)
            ");
            
            $stmtDoc->execute([$agregadoId]);
            $documentoId = $db->lastInsertId();
            
            logError("✅ Documento VIRTUAL criado com status AGUARDANDO_ASSINATURA - ID: " . $documentoId);
            
        } catch (Exception $e) {
            logError("⚠ Erro ao criar documento virtual: " . $e->getMessage());
        }
    }
    
    // =============================
    // RESPOSTA
    // =============================
    
    http_response_code($isUpdate ? 200 : 201);
    echo json_encode([
        'status' => 'success',
        'message' => $isUpdate ? 
            'Agregado atualizado com sucesso!' : 
            'Agregado cadastrado com sucesso na tabela Associados!',
        'operacao' => $isUpdate ? 'UPDATE' : 'CREATE',
        'data' => [
            'id' => $agregadoId,
            'associado_id' => $agregadoId,
            'nome' => $dadosAssociado['nome'],
            'cpf' => $dadosAssociado['cpf'],
            'titular_id' => $titularId,
            'titular_nome' => $titular['nome'],
            'corporacao' => 'Agregados'
        ],
        'documento' => [
            'id' => $documentoId,
            'caminho' => $caminhoDocumento
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    logError("=== SUCESSO - AGREGADO SALVO NA TABELA ASSOCIADOS ===");
    
} catch (Exception $e) {
    logError("❌ ERRO: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
?>
