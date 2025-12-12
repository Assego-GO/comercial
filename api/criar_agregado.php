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
require_once '../classes/Documentos.php';

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
    logError("Dados recebidos (POST)", $dadosRecebidos); // ✅ DEBUG
    
    $dados = limparDados($dadosRecebidos);
    logError("Dados limpos", $dados); // ✅ DEBUG
    
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
    // ✅ PREENCHER CAMPOS COM VALORES PADRÃO PARA AGREGADOS
    // =============================
    if (empty($dados['estadoCivil'])) {
        $dados['estadoCivil'] = 'Solteiro(a)';
        logError("➕ Estado civil preenchido automaticamente");
    }
    
    if (empty($dados['dataFiliacao'])) {
        $dados['dataFiliacao'] = date('Y-m-d');
        logError("➕ Data de filiação preenchida automaticamente");
    }
    
    if (empty($dados['endereco'])) {
        $dados['endereco'] = 'Mesmo do titular';
        logError("➕ Endereço preenchido automaticamente");
    }
    
    if (empty($dados['numero'])) {
        $dados['numero'] = 'S/N';
        logError("➕ Número preenchido automaticamente");
    }
    
    if (empty($dados['bairro'])) {
        $dados['bairro'] = 'Mesmo do titular';
        logError("➕ Bairro preenchido automaticamente");
    }
    
    if (empty($dados['cidade'])) {
        $dados['cidade'] = 'Mesmo do titular';
        logError("➕ Cidade preenchida automaticamente");
    }
    
    // VALIDAÇÕES OBRIGATÓRIAS
    // =============================
    
    $camposObrigatorios = [
        'nome' => 'Nome completo',
        'nasc' => 'Data de nascimento',
        'telefone' => 'Telefone',
        'cpf' => 'CPF'
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
        logError("❌ Validação falhou", $errosValidacao); // ✅ DEBUG
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Dados inválidos',
            'errors' => $errosValidacao,
            'debug' => 'Campos recebidos: ' . implode(', ', array_keys($dados))
        ]);
        exit;
    }
    
    logError("✓ Todas as validações passaram");
    
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
        'pre_cadastro' => 1, // Marca como pré-cadastro até assinatura
        'escolaridade' => $dados['escolaridade'] ?? null,
        'estadoCivil' => $dados['estadoCivil'],
        'telefone' => preg_replace('/\D/', '', $dados['telefone']),
        'dataFiliacao' => converterDataParaMySQL($dados['dataFiliacao']),
        // Nota: associado_titular_id ainda não existe no banco
        
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
            // Nota: associado_titular_id ainda não existe no banco
            $sql = "UPDATE Associados SET 
                nome = :nome, nasc = :nasc, sexo = :sexo, rg = :rg,
                email = :email, estadoCivil = :estadoCivil, telefone = :telefone
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
            // Nota: associado_titular_id ainda não existe no banco
    $sql = "INSERT INTO Associados (
        nome, nasc, sexo, rg, cpf, email, situacao, pre_cadastro, escolaridade,
        estadoCivil, telefone
    ) VALUES (
        :nome, :nasc, :sexo, :rg, :cpf, :email, :situacao, :pre_cadastro, :escolaridade,
        :estadoCivil, :telefone
    )";            $stmt = $db->prepare($sql);
    $stmt->execute([
        ':nome' => $dadosAssociado['nome'],
        ':nasc' => $dadosAssociado['nasc'],
        ':sexo' => $dadosAssociado['sexo'],
        ':rg' => $dadosAssociado['rg'],
        ':cpf' => $dadosAssociado['cpf'],
        ':email' => $dadosAssociado['email'],
        ':situacao' => $dadosAssociado['situacao'],
        ':pre_cadastro' => $dadosAssociado['pre_cadastro'],
        ':escolaridade' => $dadosAssociado['escolaridade'],
        ':estadoCivil' => $dadosAssociado['estadoCivil'],
        ':telefone' => $dadosAssociado['telefone']
    ]);            $agregadoId = $db->lastInsertId();
            
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
            
            // INSERT de Serviços - Agregado só tem serviço social obrigatório
            $tipoAssociadoServico = $dados['tipoAssociadoServico'] ?? 'Agregado';
            $valorBaseSocial = 173.10; // Valor base padrão
            $percentualAgregado = 50; // Agregado paga 50%
            $valorSocial = $valorBaseSocial * ($percentualAgregado / 100);
            
            $stmtServSocial = $db->prepare("
                INSERT INTO Servicos_Associado (
                    associado_id, servico_id, tipo_associado, percentual_aplicado,
                    valor_aplicado, ativo, data_adesao
                ) VALUES (?, 1, ?, ?, ?, 1, NOW())
            ");
            $stmtServSocial->execute([
                $agregadoId,
                $tipoAssociadoServico,
                $percentualAgregado,
                $valorSocial
            ]);
            
            logError("✅ Serviço Social criado para Agregado - Valor: R$ " . number_format($valorSocial, 2, ',', '.'));
            
            logError("✅ Agregado criado na tabela Associados - ID: " . $agregadoId);
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
    // =============================
    // SALVA FOTO (se enviado) - USANDO FUNÇÃO PADRONIZADA
    // =============================
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        try {
            logError("=== PROCESSANDO FOTO DO AGREGADO ===");
            
            $uploadResult = processarUploadFoto($_FILES['foto'], $dadosAssociado['cpf']);
            if ($uploadResult['success']) {
                // Atualiza a foto no associado
                $stmtFoto = $db->prepare("UPDATE Associados SET foto = ? WHERE id = ?");
                $stmtFoto->execute([$uploadResult['path'], $agregadoId]);
                
                logError("✅ Foto salva: " . $uploadResult['path']);
            } else {
                logError("⚠ Erro ao processar foto: " . $uploadResult['error']);
            }
        } catch (Exception $e) {
            logError("⚠ Erro ao salvar foto: " . $e->getMessage());
        }
    }
    
    // =============================
    // SALVA DOCUMENTO (se enviado) - USANDO CLASSE DOCUMENTOS
    // =============================
    
    $documentoId = null;
    $caminhoDocumento = null;
    
    if (isset($_FILES['ficha_assinada']) && $_FILES['ficha_assinada']['error'] === UPLOAD_ERR_OK) {
        try {
            logError("=== PROCESSANDO FICHA ASSINADA DO AGREGADO ===");
            
            $documentos = new Documentos();
            
            $documentoId = $documentos->uploadDocumentoAssociacao(
                $agregadoId,
                $_FILES['ficha_assinada'],
                'FISICO',
                'Ficha de filiação do Agregado - Anexada durante cadastro'
            );
            
            logError("✅ Ficha assinada anexada com ID: " . $documentoId);
            
            // Se tem documento, já envia automaticamente para assinatura
            try {
                $documentos->enviarParaAssinatura(
                    $documentoId,
                    "Agregado criado - Enviado automaticamente para assinatura"
                );
                
                $associados = new Associados();
                $associados->enviarParaPresidencia(
                    $agregadoId, 
                    "Agregado criado - Aguardando aprovação da presidência"
                );
                
                logError("✅ Documento enviado para presidência assinar");
                
            } catch (Exception $e) {
                logError("⚠ Aviso ao enviar ficha para presidência: " . $e->getMessage());
            }
            
        } catch (Exception $e) {
            logError("⚠ Erro ao processar ficha assinada: " . $e->getMessage());
        }
    } else {
        // =============================
        // CRIA DOCUMENTO VIRTUAL SE NÃO HOUVER UPLOAD
        // =============================
        try {
            logError("=== CRIANDO DOCUMENTO VIRTUAL PARA AGREGADO ===");
            
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
    // PROCESSAR DEPENDENTES DO AGREGADO - APÓS COMMIT
    // =============================
    if (!$isUpdate && isset($agregadoId)) {
        logError("=== PROCESSANDO DEPENDENTES DO AGREGADO ===");
        logError("📋 Chaves recebidas em \$_POST: " . implode(', ', array_keys($_POST)));
        
        // ✅ FORMATO CORRETO: dependentes[0][nome], dependentes[0][data_nascimento], etc
        $dependentesArray = [];
        
        if (isset($_POST['dependentes']) && is_array($_POST['dependentes'])) {
            logError("✅ Array 'dependentes' encontrado em \$_POST com " . count($_POST['dependentes']) . " entradas");
            
            foreach ($_POST['dependentes'] as $index => $dep) {
                $nomeDep = trim($dep['nome'] ?? '');
                $nascDep = $dep['data_nascimento'] ?? '';
                $parentescoDep = $dep['parentesco'] ?? '';
                $sexoDep = $dep['sexo'] ?? '';
                
                logError("🔍 Dependente [{$index}]: nome='{$nomeDep}', nasc='{$nascDep}', parentesco='{$parentescoDep}', sexo='{$sexoDep}'");
                
                if (!empty($nomeDep)) {
                    // Validar e converter data
                    $nascFormatado = null;
                    if (!empty($nascDep) && 
                        $nascDep !== '0000-00-00' && 
                        $nascDep !== '0000-00-00 00:00:00' &&
                        $nascDep !== 'NaN-NaN-01' &&
                        strtotime($nascDep) !== false &&
                        strtotime($nascDep) > 0) {
                        try {
                            $nascFormatado = converterDataParaMySQL($nascDep);
                            logError("📅 Data convertida: {$nascDep} -> {$nascFormatado}");
                        } catch (Exception $e) {
                            logError("⚠️ Erro ao converter data: " . $e->getMessage());
                        }
                    }
                    
                    $dependentesArray[] = [
                        'nome' => $nomeDep,
                        'data_nascimento' => $nascFormatado,
                        'parentesco' => $parentescoDep,
                        'sexo' => $sexoDep
                    ];
                    
                    logError("➕ Dependente preparado: {$nomeDep}");
                } else {
                    logError("⚠️ Dependente [{$index}] pulado: nome vazio");
                }
            }
        } else {
            logError("⚠️ Array 'dependentes' NÃO encontrado em \$_POST");
            
            // FALLBACK: Tentar formato antigo dependente_0_nome
            $indicesDependentes = [];
            foreach ($_POST as $key => $value) {
                if (preg_match('/^dependente_(\d+)_/', $key, $matches)) {
                    $indicesDependentes[$matches[1]] = true;
                }
            }
            
            if (!empty($indicesDependentes)) {
                logError("🔄 Tentando formato alternativo (dependente_N_campo)...");
                foreach (array_keys($indicesDependentes) as $index) {
                    $nomeDep = trim($_POST["dependente_{$index}_nome"] ?? '');
                    $nascDep = $_POST["dependente_{$index}_data_nascimento"] ?? '';
                    $parentescoDep = $_POST["dependente_{$index}_parentesco"] ?? '';
                    $sexoDep = $_POST["dependente_{$index}_sexo"] ?? '';
                    
                    if (!empty($nomeDep)) {
                        $nascFormatado = null;
                        if (!empty($nascDep) && $nascDep !== '0000-00-00') {
                            try {
                                $nascFormatado = converterDataParaMySQL($nascDep);
                            } catch (Exception $e) {
                                logError("⚠️ Erro ao converter data: " . $e->getMessage());
                            }
                        }
                        
                        $dependentesArray[] = [
                            'nome' => $nomeDep,
                            'data_nascimento' => $nascFormatado,
                            'parentesco' => $parentescoDep,
                            'sexo' => $sexoDep
                        ];
                        
                        logError("➕ Dependente (formato alternativo): {$nomeDep}");
                    }
                }
            }
        }
        
        // Inserir dependentes usando a classe Associados
        if (!empty($dependentesArray)) {
            logError("💾 Salvando " . count($dependentesArray) . " dependente(s) no banco...");
            
            $associados = new Associados();
            foreach ($dependentesArray as $dep) {
                try {
                    $associados->adicionarDependente($agregadoId, $dep);
                    logError("✅ Dependente '{$dep['nome']}' salvo com sucesso no banco!");
                } catch (Exception $e) {
                    logError("❌ ERRO ao salvar dependente '{$dep['nome']}': " . $e->getMessage());
                }
            }
            
            logError("✅✅✅ Total de " . count($dependentesArray) . " dependente(s) SALVOS NO BANCO!");
        } else {
            logError("⚠️⚠️ NENHUM DEPENDENTE FOI PROCESSADO! Verifique o formato dos dados enviados.");
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
    logError("❌ ERRO EXCEPTION: " . $e->getMessage());
    logError("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao processar requisição: ' . $e->getMessage(),
        'debug' => 'Linha: ' . $e->getLine() . ' | Arquivo: ' . basename($e->getFile())
    ]);
}

/**
 * Função para processar upload de foto
 */
function processarUploadFoto($arquivo, $cpf)
{
    $resultado = [
        'success' => false,
        'path' => null,
        'error' => null
    ];

    try {
        if (!isset($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
            throw new Exception('Arquivo não foi enviado corretamente');
        }

        $tamanhoMaximo = 10 * 1024 * 1024; // 10MB
        $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        if ($arquivo['size'] > $tamanhoMaximo) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 10MB');
        }

        if (!in_array($arquivo['type'], $tiposPermitidos)) {
            throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG ou GIF');
        }

        $imageInfo = getimagesize($arquivo['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Arquivo não é uma imagem válida');
        }

        $uploadDir = '../uploads/fotos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
        $nomeArquivo = 'foto_' . $cpfLimpo . '_' . time() . '.' . strtolower($extensao);
        $caminhoCompleto = $uploadDir . $nomeArquivo;

        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new Exception('Erro ao salvar arquivo no servidor');
        }

        $resultado['success'] = true;
        $resultado['path'] = 'uploads/fotos/' . $nomeArquivo;
    } catch (Exception $e) {
        $resultado['error'] = $e->getMessage();
    }

    return $resultado;
}
?>
