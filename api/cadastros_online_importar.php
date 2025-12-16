<?php
/**
 * API para importar cadastro online para o sistema interno
 * api/cadastros_online_importar.php
 * 
 * ✅ VERSÃO CORRIGIDA - Salva corporacao e patente em Militar
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado']);
    exit;
}

$usuarioLogado = $auth->getUser();

// Pegar dados do POST
$dados = json_decode(file_get_contents('php://input'), true);

if (!isset($dados['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID do cadastro não informado']);
    exit;
}

$cadastroOnlineId = (int)$dados['id'];

try {
    // Conecta no banco do sistema interno
    $dbCadastro = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // ========================================================================
    // BUSCAR DADOS DO CADASTRO ONLINE VIA API
    // ========================================================================
    
    $apiUrlBase = 'https://associe-se.assego.com.br/associar/api';
    $apiKey = 'assego_2025_e303e77ad524f7a9f59bcdaa9883bb72';
    
    // Buscar dados completos do cadastro
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "{$apiUrlBase}/listar_cadastros.php?api_key={$apiKey}&limit=500",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Erro ao buscar dados do cadastro online. HTTP: {$httpCode}");
    }
    
    if (!$response) {
        throw new Exception("Erro na comunicação com API externa: " . $curlError);
    }
    
    $responseData = json_decode($response, true);
    
    if (!$responseData || $responseData['status'] !== 'success') {
        throw new Exception('Erro ao processar resposta da API do cadastro online');
    }
    
    // Encontrar o cadastro específico
    $cadastroOnline = null;
    foreach ($responseData['data']['cadastros'] as $c) {
        if ($c['id'] == $cadastroOnlineId) {
            $cadastroOnline = $c;
            break;
        }
    }
    
    if (!$cadastroOnline) {
        throw new Exception('Cadastro não encontrado na base online');
    }
    
    // ========================================================================
    // VERIFICAR SE CPF JÁ EXISTE NO SISTEMA INTERNO
    // ========================================================================
    
    $sqlVerificarCPF = "SELECT id, nome FROM Associados WHERE cpf = :cpf";
    $stmtVerificar = $dbCadastro->prepare($sqlVerificarCPF);
    $stmtVerificar->execute([':cpf' => $cadastroOnline['cpf']]);
    $associadoExistente = $stmtVerificar->fetch(PDO::FETCH_ASSOC);
    
    if ($associadoExistente) {
        throw new Exception("CPF já cadastrado! Associado: {$associadoExistente['nome']} (ID: {$associadoExistente['id']})");
    }
    
    // ========================================================================
    // INICIAR TRANSAÇÃO
    // ========================================================================
    
    $dbCadastro->beginTransaction();
    
    // ========================================================================
    // PROCESSAR FOTO SE HOUVER
    // ========================================================================
    
    $fotoPath = null;
    if ($cadastroOnline['tem_foto'] && !empty($cadastroOnline['foto_base64'])) {
        $uploadDir = '../uploads/fotos_associados/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extensao = 'jpg';
        if (!empty($cadastroOnline['foto_mime_type'])) {
            if (strpos($cadastroOnline['foto_mime_type'], 'png') !== false) {
                $extensao = 'png';
            } elseif (strpos($cadastroOnline['foto_mime_type'], 'gif') !== false) {
                $extensao = 'gif';
            }
        }
        
        $nomeArquivo = 'importado_' . time() . '_' . $cadastroOnlineId . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        
        $imagemBinaria = base64_decode($cadastroOnline['foto_base64']);
        if ($imagemBinaria && file_put_contents($caminhoCompleto, $imagemBinaria)) {
            $fotoPath = 'uploads/fotos_associados/' . $nomeArquivo;
        }
    }
    
    // ========================================================================
    // INSERIR ASSOCIADO COMO PRÉ-CADASTRO
    // ========================================================================
    
    $sqlAssociado = "
        INSERT INTO Associados (
            nome, cpf, rg, nasc, sexo, email, telefone,
            indicacao, situacao, pre_cadastro, data_pre_cadastro,
            observacao_aprovacao, foto, estadoCivil, escolaridade
        ) VALUES (
            :nome, :cpf, :rg, :nasc, :sexo, :email, :telefone,
            :indicacao, 'Aguardando Aprovação', 1, NOW(),
            :observacao, :foto, :estadoCivil, NULL
        )
    ";
    
    $observacao = "Importado do cadastro online em " . date('d/m/Y H:i:s') . " por " . $usuarioLogado['nome'];
    $observacao .= "\nProtocolo Online ID: " . $cadastroOnlineId;
    
    if ($cadastroOnline['optante_juridico']) {
        $observacao .= "\n⚖️ OPTANTE PELO SERVIÇO JURÍDICO";
    }
    
    $stmtAssociado = $dbCadastro->prepare($sqlAssociado);
    $stmtAssociado->execute([
        ':nome' => $cadastroOnline['nome'],
        ':cpf' => $cadastroOnline['cpf'],
        ':rg' => $cadastroOnline['rg'] ?: null,
        ':nasc' => $cadastroOnline['data_nascimento'] ?: null,
        ':sexo' => $cadastroOnline['sexo'] ?: null,
        ':email' => $cadastroOnline['email'] ?: null,
        ':telefone' => $cadastroOnline['telefone'],
        ':indicacao' => $cadastroOnline['indicacao'] ?: null,
        ':observacao' => $observacao,
        ':foto' => $fotoPath,
        ':estadoCivil' => $cadastroOnline['estado_civil'] ?: null
    ]);
    
    $novoAssociadoId = $dbCadastro->lastInsertId();
    
    // ========================================================================
    // ✅ NOVO: INSERIR DADOS MILITARES
    // ========================================================================
    
    if (!empty($cadastroOnline['corporacao']) || !empty($cadastroOnline['patente'])) {
        $sqlMilitar = "
            INSERT INTO Militar (
                associado_id, 
                corporacao, 
                patente,
                categoria,
                lotacao,
                unidade
            ) VALUES (
                :associado_id, 
                :corporacao, 
                :patente,
                NULL,
                NULL,
                NULL
            )
        ";
        
        $stmtMilitar = $dbCadastro->prepare($sqlMilitar);
        $stmtMilitar->execute([
            ':associado_id' => $novoAssociadoId,
            ':corporacao' => $cadastroOnline['corporacao'] ?: null,
            ':patente' => $cadastroOnline['patente'] ?: null
        ]);
        
        error_log("✅ Dados militares salvos - Associado ID: {$novoAssociadoId}, Corporação: {$cadastroOnline['corporacao']}, Patente: {$cadastroOnline['patente']}");
    }
    
    // ========================================================================
    // CRIAR FLUXO DE PRÉ-CADASTRO
    // ========================================================================
    
    $sqlFluxo = "
        INSERT INTO Fluxo_Pre_Cadastro (
            associado_id, status, observacoes, created_at
        ) VALUES (
            :associado_id, 'AGUARDANDO_DOCUMENTOS', :observacoes, NOW()
        )
    ";
    
    $obsFluxo = "Cadastro importado do site online.\n";
    $obsFluxo .= "Protocolo: " . date('Ymd') . str_pad($cadastroOnlineId, 6, '0', STR_PAD_LEFT) . "\n";
    $obsFluxo .= "IP: " . ($cadastroOnline['ip_origem'] ?: 'Não informado') . "\n";
    $obsFluxo .= "Aguardando há: " . ($cadastroOnline['dias_aguardando'] ?? 0) . " dias\n";
    
    if (!empty($cadastroOnline['corporacao'])) {
        $obsFluxo .= "Corporação: {$cadastroOnline['corporacao']}\n";
    }
    if (!empty($cadastroOnline['patente'])) {
        $obsFluxo .= "Patente: {$cadastroOnline['patente']}\n";
    }
    if ($cadastroOnline['optante_juridico']) {
        $obsFluxo .= "⚖️ OPTANTE PELO SERVIÇO JURÍDICO\n";
    }
    
    $stmtFluxo = $dbCadastro->prepare($sqlFluxo);
    $stmtFluxo->execute([
        ':associado_id' => $novoAssociadoId,
        ':observacoes' => $obsFluxo
    ]);
    
    // ========================================================================
    // COMMIT
    // ========================================================================
    
    $dbCadastro->commit();
    
    error_log("✅ IMPORTAÇÃO COMPLETA - Associado ID: {$novoAssociadoId}, Nome: {$cadastroOnline['nome']}");
    
    // ========================================================================
    // MARCAR COMO IMPORTADO NO SISTEMA ONLINE
    // ========================================================================
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "{$apiUrlBase}/marcar_importado.php",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'id' => $cadastroOnlineId,
            'api_key' => $apiKey,
            'importado_por' => $usuarioLogado['nome'],
            'observacao' => "Importado para o sistema interno com ID: {$novoAssociadoId}",
            'associado_id_criado' => $novoAssociadoId
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    
    $marcarResp = curl_exec($ch);
    curl_close($ch);
    
    // ========================================================================
    // RESPOSTA
    // ========================================================================
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Cadastro importado com sucesso!',
        'associado_id' => $novoAssociadoId,
        'info' => [
            'nome' => $cadastroOnline['nome'],
            'cpf' => $cadastroOnline['cpf_formatado'] ?? $cadastroOnline['cpf'],
            'corporacao' => $cadastroOnline['corporacao'] ?? null,
            'patente' => $cadastroOnline['patente'] ?? null,
            'estado_civil' => $cadastroOnline['estado_civil'] ?? null,
            'importado_por' => $usuarioLogado['nome'],
            'data_importacao' => date('d/m/Y H:i:s'),
            'protocolo' => date('Ymd') . str_pad($cadastroOnlineId, 6, '0', STR_PAD_LEFT)
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($dbCadastro) && $dbCadastro->inTransaction()) {
        $dbCadastro->rollBack();
    }
    
    error_log("❌ ERRO ao importar cadastro ID {$cadastroOnlineId}: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'cadastro_id' => $cadastroOnlineId
        ]
    ]);
}
?>