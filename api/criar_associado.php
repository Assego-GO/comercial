<?php
/**
 * API para criar novo associado - VERSÃO COM SALVAMENTO EM JSON + ZAPSIGN
 * api/criar_associado.php
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_clean();

$response = [
    'status' => 'error',
    'message' => 'Erro ao processar requisição',
    'data' => null
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }

    if (empty($_POST)) {
        throw new Exception('Nenhum dado foi enviado via POST');
    }

    // Carrega arquivos necessários
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';
    require_once '../classes/Associados.php';
    require_once '../classes/Documentos.php';
    
    // ✅ NOVA LINHA - JsonManager para salvar em JSON
    require_once '../classes/JsonManager.php';
    
    // ✅ NOVA LINHA - API ZapSign
    require_once '../api/zapsign_api.php';

    // Sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        // Para desenvolvimento - remover em produção
        $_SESSION['user_id'] = 1;
        $_SESSION['funcionario_id'] = 1;
        $_SESSION['user_name'] = 'Sistema';
    }

    error_log("=== CRIAR PRÉ-CADASTRO COM FLUXO INTEGRADO + JSON + ZAPSIGN ===");
    error_log("Usuário: " . ($_SESSION['user_name'] ?? 'N/A'));
    error_log("POST fields: " . count($_POST));

    // Validação básica
    $campos_obrigatorios = ['nome', 'cpf', 'rg', 'telefone', 'situacao', 'dataFiliacao'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("Campo '$campo' é obrigatório");
        }
    }
    
    // ✅ VALIDAÇÃO ESPECÍFICA PARA DATAS
    if (!empty($_POST['dataFiliacao'])) {
        $dataFiliacao = $_POST['dataFiliacao'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFiliacao) && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataFiliacao)) {
            throw new Exception("Data de filiação deve estar no formato YYYY-MM-DD ou DD/MM/YYYY");
        }
    }
    
    // Valida data de nascimento se preenchida
    if (!empty($_POST['nasc'])) {
        $dataNasc = $_POST['nasc'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNasc) && !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataNasc)) {
            throw new Exception("Data de nascimento deve estar no formato YYYY-MM-DD ou DD/MM/YYYY");
        }
    }

    // ✅ FUNÇÃO AUXILIAR - Limpa campos de data vazios
    function limparCamposData(&$dados) {
        $camposData = ['nasc', 'dataFiliacao', 'dataDesfiliacao'];
        
        foreach ($camposData as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] === '') {
                $dados[$campo] = null;
            } elseif (isset($dados[$campo]) && !empty($dados[$campo])) {
                // Converte data brasileira para formato MySQL se necessário
                $dados[$campo] = converterDataParaMySQL($dados[$campo]);
            }
        }
        
        // Limpa datas dos dependentes também
        if (isset($dados['dependentes'])) {
            foreach ($dados['dependentes'] as &$dep) {
                if (isset($dep['data_nascimento']) && $dep['data_nascimento'] === '') {
                    $dep['data_nascimento'] = null;
                } elseif (isset($dep['data_nascimento']) && !empty($dep['data_nascimento'])) {
                    $dep['data_nascimento'] = converterDataParaMySQL($dep['data_nascimento']);
                }
            }
        }
    }
    
    // ✅ FUNÇÃO AUXILIAR - Converte data para formato MySQL
    function converterDataParaMySQL($data) {
        if (empty($data)) return null;
        
        // Se já está no formato MySQL (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }
        
        // Se está no formato brasileiro (DD/MM/YYYY)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        // Tenta usar DateTime para converter outros formatos
        try {
            $dateTime = new DateTime($data);
            return $dateTime->format('Y-m-d');
        } catch (Exception $e) {
            error_log("Erro ao converter data: {$data} - " . $e->getMessage());
            return null;
        }
    }
    
    // ✅ APLICA LIMPEZA DOS CAMPOS DE DATA
    limparCamposData($dados);
    
    // ✅ DEBUG - Log das datas processadas
    error_log("Datas processadas: nasc=" . ($dados['nasc'] ?? 'NULL') . 
              ", dataFiliacao=" . ($dados['dataFiliacao'] ?? 'NULL') . 
              ", dataDesfiliacao=" . ($dados['dataDesfiliacao'] ?? 'NULL'));

    // Verifica se tem documento anexado (ficha assinada)
  

    // Prepara dados do associado
    $dados = [
        'nome' => trim($_POST['nome']),
        'nasc' => !empty($_POST['nasc']) ? $_POST['nasc'] : null, // ✅ CORRIGIDO
        'sexo' => $_POST['sexo'] ?? null,
        'rg' => trim($_POST['rg']),
        'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf']),
        'email' => trim($_POST['email'] ?? '') ?: null,
        'situacao' => $_POST['situacao'],
        'escolaridade' => $_POST['escolaridade'] ?? null,
        'estadoCivil' => $_POST['estadoCivil'] ?? null,
        'telefone' => preg_replace('/[^0-9]/', '', $_POST['telefone']),
        'indicacao' => trim($_POST['indicacao'] ?? '') ?: null,
        'dataFiliacao' => !empty($_POST['dataFiliacao']) ? $_POST['dataFiliacao'] : null, // ✅ CORRIGIDO
        'dataDesfiliacao' => !empty($_POST['dataDesfiliacao']) ? $_POST['dataDesfiliacao'] : null, // ✅ CORRIGIDO
        // Dados militares
        'corporacao' => $_POST['corporacao'] ?? null,
        'patente' => $_POST['patente'] ?? null,
        'categoria' => $_POST['categoria'] ?? null,
        'lotacao' => trim($_POST['lotacao'] ?? '') ?: null,
        'telefoneLotacao' => preg_replace('/[^0-9]/', '', $_POST['telefoneLotacao'] ?? '') ?: null, // ✅ CAMPO PARA ZAPSIGN
        'unidade' => trim($_POST['unidade'] ?? '') ?: null,
        // Endereço
        'cep' => preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '') ?: null,
        'endereco' => trim($_POST['endereco'] ?? '') ?: null,
        'numero' => trim($_POST['numero'] ?? '') ?: null,
        'complemento' => trim($_POST['complemento'] ?? '') ?: null,
        'bairro' => trim($_POST['bairro'] ?? '') ?: null,
        'cidade' => trim($_POST['cidade'] ?? '') ?: null,
        'estado' => trim($_POST['estado'] ?? '') ?: null, // ✅ CAMPO PARA ZAPSIGN
        // Financeiro
        'tipoAssociado' => $_POST['tipoAssociado'] ?? null,
        'situacaoFinanceira' => $_POST['situacaoFinanceira'] ?? null,
        'vinculoServidor' => $_POST['vinculoServidor'] ?? null,
        'localDebito' => $_POST['localDebito'] ?? null,
        'agencia' => trim($_POST['agencia'] ?? '') ?: null,
        'operacao' => trim($_POST['operacao'] ?? '') ?: null,
        'contaCorrente' => trim($_POST['contaCorrente'] ?? '') ?: null,
        
        // ✅ NOVOS CAMPOS - Dados dos serviços para JSON
        'tipoAssociadoServico' => $_POST['tipoAssociadoServico'] ?? null,
        'valorSocial' => $_POST['valorSocial'] ?? '0',
        'percentualAplicadoSocial' => $_POST['percentualAplicadoSocial'] ?? '0',
        'valorJuridico' => $_POST['valorJuridico'] ?? '0',
        'percentualAplicadoJuridico' => $_POST['percentualAplicadoJuridico'] ?? '0',
        'servicoJuridico' => $_POST['servicoJuridico'] ?? null
    ];

    // ✅ MODIFICADO - Processa dependentes com telefone E data para cônjuges
    $dados['dependentes'] = [];
    if (isset($_POST['dependentes']) && is_array($_POST['dependentes'])) {
        foreach ($_POST['dependentes'] as $dep) {
            if (!empty($dep['nome'])) {
                $dependente = [
                    'nome' => trim($dep['nome']),
                    'parentesco' => $dep['parentesco'] ?? null,
                    'sexo' => $dep['sexo'] ?? null
                ];
                
                // ✅ NOVO: Para cônjuge, captura TELEFONE E DATA. Para outros, só data
                if ($dep['parentesco'] === 'Cônjuge') {
                    // Cônjuge tem AMBOS os campos
                    $dependente['telefone'] = preg_replace('/[^0-9]/', '', $dep['telefone'] ?? '');
                    $dependente['data_nascimento'] = !empty($dep['data_nascimento']) ? $dep['data_nascimento'] : null; // ✅ CORRIGIDO
                } else {
                    // Outros parentes só têm data de nascimento
                    $dependente['data_nascimento'] = !empty($dep['data_nascimento']) ? $dep['data_nascimento'] : null; // ✅ CORRIGIDO
                    $dependente['telefone'] = null; // Limpa telefone
                }
                
                $dados['dependentes'][] = $dependente;
            }
        }
    }

    // Processa foto do associado
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/fotos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $nomeArquivo = 'foto_' . preg_replace('/[^0-9]/', '', $dados['cpf']) . '_' . time() . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoCompleto)) {
            $dados['foto'] = 'uploads/fotos/' . $nomeArquivo;
            error_log("✓ Foto do associado salva: " . $dados['foto']);
        }
    }

    // IMPORTANTE: A classe Associados já gerencia sua própria transação
    // Não devemos iniciar outra transação aqui
    
    $associados = new Associados();
    $documentos = new Documentos();
    
    // PASSO 1: CRIA O PRÉ-CADASTRO (com transação interna)
    $associadoId = $associados->criar($dados);
    
    if (!$associadoId) {
        throw new Exception('Erro ao criar pré-cadastro');
    }
    
    error_log("✓ Pré-cadastro criado com ID: $associadoId");

    // PASSO 2: PROCESSA O DOCUMENTO (transação separada)
    $documentoId = null;
    $statusFluxo = 'DIGITALIZADO';
    $enviarAutomaticamente = isset($_POST['enviar_presidencia']) && $_POST['enviar_presidencia'] == '1';
    
    try {
        // Upload do documento (tem sua própria transação)
        $documentoId = $documentos->uploadDocumentoAssociacao(
            $associadoId,
            $_FILES['ficha_assinada'],
            'FISICO',
            'Ficha de filiação assinada - Anexada durante pré-cadastro'
        );
        
        error_log("✓ Ficha assinada anexada com ID: $documentoId");
        
        // PASSO 3: ENVIAR PARA PRESIDÊNCIA SE SOLICITADO
        if ($enviarAutomaticamente) {
            try {
                // Envia documento para assinatura (transação separada)
                $documentos->enviarParaAssinatura(
                    $documentoId,
                    "Pré-cadastro realizado - Enviado automaticamente para assinatura"
                );
                
                // Atualiza status do pré-cadastro (transação separada)
                $associados->enviarParaPresidencia(
                    $associadoId, 
                    "Documentação enviada automaticamente para aprovação"
                );
                
                $statusFluxo = 'AGUARDANDO_ASSINATURA';
                error_log("✓ Documento enviado para presidência assinar");
                
            } catch (Exception $e) {
                // Não é erro crítico - documento foi criado
                error_log("⚠ Aviso ao enviar para presidência: " . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        // Se falhar o documento, ainda temos o associado criado
        error_log("⚠ Erro ao processar documento: " . $e->getMessage());
        // Continua o processo...
    }

// PASSO 4: CRIA OS SERVIÇOS (transação separada)
$servicos_criados = [];
$valor_total_mensal = 0;

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $db->beginTransaction();
    
    // CORREÇÃO: Captura o tipo correto
    $tipoAssociadoServico = $_POST['tipoAssociadoServico'] ?? 'Contribuinte';
    
    // DEBUG
    error_log("=== SALVANDO SERVIÇOS ===");
    error_log("tipoAssociadoServico recebido: " . $tipoAssociadoServico);
    
    // Serviço Social
    if (isset($_POST['valorSocial']) && floatval($_POST['valorSocial']) >= 0) {
        $stmt = $db->prepare("
            INSERT INTO Servicos_Associado (
                associado_id, servico_id, tipo_associado, ativo, data_adesao, 
                valor_aplicado, percentual_aplicado, observacao
            ) VALUES (?, 1, ?, 1, NOW(), ?, ?, ?)
        ");
        
        $valorSocial = floatval($_POST['valorSocial']);
        $percentualSocial = floatval($_POST['percentualAplicadoSocial'] ?? 100);
        
        $stmt->execute([
            $associadoId,
            $tipoAssociadoServico,  // ← ESTE É O CAMPO CRÍTICO
            $valorSocial,
            $percentualSocial,
            "Cadastro - Tipo: {$tipoAssociadoServico}"
        ]);
        
        $servicos_criados[] = 'Social';
        $valor_total_mensal += $valorSocial;
        error_log("✓ Serviço Social salvo com tipo: $tipoAssociadoServico");
    }

    // Serviço Jurídico
    if (isset($_POST['servicoJuridico']) && $_POST['servicoJuridico'] && 
        isset($_POST['valorJuridico']) && floatval($_POST['valorJuridico']) > 0) {
        
        $stmt = $db->prepare("
            INSERT INTO Servicos_Associado (
                associado_id, servico_id, tipo_associado, ativo, data_adesao, 
                valor_aplicado, percentual_aplicado, observacao
            ) VALUES (?, 2, ?, 1, NOW(), ?, ?, ?)
        ");
        
        $valorJuridico = floatval($_POST['valorJuridico']);
        $percentualJuridico = floatval($_POST['percentualAplicadoJuridico'] ?? 100);
        
        $stmt->execute([
            $associadoId,
            $tipoAssociadoServico,  // ← ESTE É O CAMPO CRÍTICO
            $valorJuridico,
            $percentualJuridico,
            "Cadastro - Tipo: {$tipoAssociadoServico}"
        ]);
        
        $servicos_criados[] = 'Jurídico';
        $valor_total_mensal += $valorJuridico;
        error_log("✓ Serviço Jurídico salvo com tipo: $tipoAssociadoServico");
    }
    
    $db->commit();
    error_log("✓ Serviços salvos com sucesso!");
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("⚠ Erro ao criar serviços: " . $e->getMessage());
}

    // =====================================
    // ✅ PASSO 5: SALVA DADOS EM JSON
    // =====================================
    
    $resultadoJson = ['sucesso' => false, 'erro' => 'Não processado'];
    
    try {
        error_log("=== INICIANDO SALVAMENTO EM JSON ===");
        
        $jsonManager = new JsonManager();
        $resultadoJson = $jsonManager->salvarAssociadoJson($dados, $associadoId, 'CREATE');
        
        if ($resultadoJson['sucesso']) {
            error_log("✓ JSON salvo com sucesso: " . $resultadoJson['arquivo_individual']);
            error_log("✓ Tamanho do arquivo: " . $resultadoJson['tamanho_bytes'] . " bytes");
        } else {
            error_log("⚠ Erro ao salvar JSON: " . $resultadoJson['erro']);
        }
        
    } catch (Exception $e) {
        $resultadoJson = [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
        error_log("✗ ERRO CRÍTICO ao salvar JSON: " . $e->getMessage());
        // Não falha a operação por causa do JSON
    }

    // =====================================
    // ✅ PASSO 6: ENVIA PARA ZAPSIGN
    // =====================================
    
    $resultadoZapSign = ['sucesso' => false, 'erro' => 'Não processado'];
    
    try {
        error_log("=== INICIANDO ENVIO PARA ZAPSIGN ===");
        
        // ✅ VERIFICA SE O ARQUIVO DA API EXISTE
        $arquivoZapSign = '../api/zapsign_api.php';
        if (!file_exists($arquivoZapSign)) {
            throw new Exception("Arquivo zapsign_api.php não encontrado: " . $arquivoZapSign);
        }
        
        // ✅ VERIFICA SE A FUNÇÃO EXISTE
        if (!function_exists('enviarParaZapSign')) {
            throw new Exception("Função enviarParaZapSign() não encontrada. Verifique se o arquivo foi incluído corretamente.");
        }
        
        // ✅ VERIFICA SE O MÉTODO DO JSONMANAGER EXISTE
        if (!method_exists($jsonManager, 'obterDadosCompletos')) {
            throw new Exception("Método obterDadosCompletos() não encontrado na classe JsonManager. Adicione o método público.");
        }
        
        // ✅ USA A FUNÇÃO DO JSONMANAGER PARA PREPARAR DADOS
        $dadosCompletos = $jsonManager->obterDadosCompletos($dados, $associadoId, 'CREATE');
        
        error_log("Dados completos preparados. Seções: " . implode(', ', array_keys($dadosCompletos)));
        
        // ✅ ENVIA PARA ZAPSIGN
        $resultadoZapSign = enviarParaZapSign($dadosCompletos);
        
        if ($resultadoZapSign['sucesso']) {
            error_log("✓ ZapSign enviado com sucesso!");
            error_log("✓ Documento ID: " . ($resultadoZapSign['documento_id'] ?? 'N/A'));
            error_log("✓ Link assinatura: " . ($resultadoZapSign['link_assinatura'] ?? 'N/A'));
            
            // ✅ ATUALIZA BANCO COM DADOS DO ZAPSIGN
            try {
                $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
                $stmt = $db->prepare("
                    UPDATE associados 
                    SET zapsign_documento_id = ?, 
                        zapsign_link_assinatura = ?,
                        zapsign_status = 'ENVIADO',
                        zapsign_data_envio = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $resultadoZapSign['documento_id'],
                    $resultadoZapSign['link_assinatura'],
                    $associadoId
                ]);
                error_log("✓ Dados ZapSign salvos no banco");
            } catch (Exception $e) {
                error_log("⚠ Erro ao salvar dados ZapSign no banco: " . $e->getMessage());
            }
            
        } else {
            error_log("⚠ Erro ao enviar para ZapSign: " . $resultadoZapSign['erro']);
        }
        
    } catch (Exception $e) {
        $resultadoZapSign = [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
        error_log("✗ ERRO CRÍTICO no ZapSign: " . $e->getMessage());
        // Não falha a operação por causa do ZapSign
    }

    // =====================================
    // RESPOSTA FINAL
    // =====================================

    // Monta resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Pré-cadastro realizado com sucesso!',
        'data' => [
            'id' => $associadoId,
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf'],
            'pre_cadastro' => true,
            'fluxo_documento' => [
                'documento_id' => $documentoId,
                'status' => $statusFluxo,
                'enviado_presidencia' => $enviarAutomaticamente && $documentoId,
                'mensagem' => $documentoId 
                    ? ($enviarAutomaticamente 
                        ? 'Documento enviado para assinatura na presidência' 
                        : 'Documento aguardando envio manual para presidência')
                    : 'Documento não foi processado'
            ],
            'servicos' => [
                'lista' => $servicos_criados,
                'total' => count($servicos_criados),
                'valor_mensal' => number_format($valor_total_mensal, 2, ',', '.')
            ],
            'extras' => [
                'dependentes' => count($dados['dependentes']),
                'tem_foto' => !empty($dados['foto']),
                'tem_ficha_assinada' => $documentoId !== null,
                'telefone_lotacao' => !empty($dados['telefoneLotacao']), // ✅ NOVO
                'tem_conjuge' => temConjugeComTelefone($dados['dependentes']) // ✅ ATUALIZADO
            ],
            
            // ✅ SEÇÃO JSON
            'json_export' => [
                'salvo' => $resultadoJson['sucesso'],
                'arquivo' => $resultadoJson['arquivo_individual'] ?? null,
                'tamanho_bytes' => $resultadoJson['tamanho_bytes'] ?? 0,
                'timestamp' => $resultadoJson['timestamp'] ?? null,
                'erro' => $resultadoJson['sucesso'] ? null : $resultadoJson['erro'],
                'pronto_para_zapsing' => $resultadoJson['sucesso']
            ],
            
            // ✅ NOVA SEÇÃO - ZAPSIGN
            'zapsign' => [
                'enviado' => $resultadoZapSign['sucesso'],
                'documento_id' => $resultadoZapSign['documento_id'] ?? null,
                'link_assinatura' => $resultadoZapSign['link_assinatura'] ?? null,
                'erro' => $resultadoZapSign['sucesso'] ? null : $resultadoZapSign['erro'],
                'http_code' => $resultadoZapSign['http_code'] ?? null,
                'status' => $resultadoZapSign['sucesso'] ? 'ENVIADO' : 'ERRO',
                'debug_info' => !$resultadoZapSign['sucesso'] ? $resultadoZapSign : null // ✅ TEMPORÁRIO PARA DEBUG
            ]
        ]
    ];
    
    // ✅ Atualiza mensagens
    if ($resultadoJson['sucesso']) {
        $response['message'] .= ' Dados exportados para integração.';
    }
    
    if ($resultadoZapSign['sucesso']) {
        $response['message'] .= ' Documento enviado para assinatura eletrônica.';
    }

    error_log("=== PRÉ-CADASTRO CONCLUÍDO COM SUCESSO ===");
    error_log("ID: {$associadoId} | Documento: " . ($documentoId ?? 'N/A') . " | Status: {$statusFluxo}");
    error_log("JSON: " . ($resultadoJson['sucesso'] ? '✓ Salvo' : '✗ Falhou') . " | Arquivo: " . ($resultadoJson['arquivo_individual'] ?? 'N/A'));
    error_log("ZapSign: " . ($resultadoZapSign['sucesso'] ? '✓ Enviado' : '✗ Falhou') . " | Doc ID: " . ($resultadoZapSign['documento_id'] ?? 'N/A'));

} catch (Exception $e) {
    error_log("✗ ERRO GERAL: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null,
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'post_count' => count($_POST ?? []),
            'files_count' => count($_FILES ?? []),
            'session_active' => session_status() === PHP_SESSION_ACTIVE
        ]
    ];
    
    http_response_code(400);
}

// ✅ FUNÇÃO AUXILIAR ATUALIZADA
function temConjugeComTelefone($dependentes) {
    foreach ($dependentes as $dep) {
        if ($dep['parentesco'] === 'Cônjuge') {
            return true; // Cônjuge sempre tem telefone agora
        }
    }
    return false;
}

// Limpa buffer e envia resposta
ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>