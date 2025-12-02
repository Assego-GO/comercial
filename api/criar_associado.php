<?php
/**
 * API para criar novo associado - VERSÃO COM INDICAÇÕES INTEGRADAS
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
    require_once '../classes/JsonManager.php';
    require_once '../classes/Indicacoes.php'; // ✅ NOVO
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

    $funcionarioId = $_SESSION['funcionario_id'] ?? null;

    error_log("=== CRIAR PRÉ-CADASTRO COM INDICAÇÕES ===");
    error_log("Usuário: " . ($_SESSION['user_name'] ?? 'N/A'));
    error_log("Funcionário ID: " . $funcionarioId);

    // Validação básica
    $campos_obrigatorios = ['nome', 'cpf', 'rg', 'telefone', 'situacao', 'dataFiliacao'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("Campo '$campo' é obrigatório");
        }
    }

    // ✅ CAPTURA DADOS DE INDICAÇÃO
    $indicacaoNome = trim($_POST['indicacao'] ?? '');
    $indicacaoPatente = null;
    $indicacaoCorporacao = null;
    $temIndicacao = !empty($indicacaoNome);
    
    if ($temIndicacao) {
        error_log("📌 Indicação detectada: $indicacaoNome");
        
        // Tenta extrair patente e corporação do nome se estiver no formato padrão
        if (preg_match('/^(.*?)\s+(PM|BM)\s+(.*)$/i', $indicacaoNome, $matches)) {
            $indicacaoPatente = trim($matches[1]);
            $indicacaoCorporacao = ($matches[2] === 'PM') ? 'Polícia Militar' : 'Bombeiro Militar';
            error_log("  - Patente extraída: $indicacaoPatente");
            error_log("  - Corporação extraída: $indicacaoCorporacao");
        }
    }

    // ✅ CAPTURA DADOS DE AGREGADO
    $tipoAssociado = trim($_POST['tipoAssociado'] ?? '');
    $associadoTitularId = null;
    $ehAgregado = ($tipoAssociado === 'Agregado');
    
    if ($ehAgregado) {
        $associadoTitularId = !empty($_POST['associadoTitular']) ? intval($_POST['associadoTitular']) : null;
        
        if (!$associadoTitularId) {
            throw new Exception('Associado titular é obrigatório para agregados');
        }
        
        error_log("📌 Agregado detectado - Titular ID: $associadoTitularId");
        
        // Verifica se o titular existe e está ativo
        $dbCheck = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $stmtVerif = $dbCheck->prepare("
            SELECT a.id, a.nome, m.corporacao 
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            WHERE a.id = ? AND a.situacao = 'Filiado'
        ");
        $stmtVerif->execute([$associadoTitularId]);
        $titular = $stmtVerif->fetch(PDO::FETCH_ASSOC);
        
        if (!$titular) {
            throw new Exception('Associado titular não encontrado ou inativo');
        }
        
        if (!empty($titular['corporacao']) && $titular['corporacao'] === 'Agregados') {
            throw new Exception('O associado titular não pode ser um agregado');
        }
        
        // Forçar corporação como Agregados
        $_POST['corporacao'] = 'Agregados';
        $_POST['patente'] = 'Agregado';
        $_POST['categoria'] = 'Agregado';
        
        error_log("✓ Titular validado: " . $titular['nome']);
    }

    // Função auxiliar para limpar campos de data
    function limparCamposData(&$dados) {
        $camposData = ['nasc', 'dataFiliacao', 'dataDesfiliacao'];
        
        foreach ($camposData as $campo) {
            if (isset($dados[$campo]) && $dados[$campo] === '') {
                $dados[$campo] = null;
            } elseif (isset($dados[$campo]) && !empty($dados[$campo])) {
                $dados[$campo] = converterDataParaMySQL($dados[$campo]);
            }
        }
        
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
            error_log("Erro ao converter data: {$data} - " . $e->getMessage());
            return null;
        }
    }

    // Prepara dados do associado
    $dados = [
        'nome' => trim($_POST['nome']),
        'nasc' => !empty($_POST['nasc']) ? $_POST['nasc'] : null,
        'sexo' => $_POST['sexo'] ?? null,
        'rg' => trim($_POST['rg']),
        'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf']),
        'email' => trim($_POST['email'] ?? '') ?: null,
        'situacao' => $_POST['situacao'],
        'escolaridade' => $_POST['escolaridade'] ?? null,
        'estadoCivil' => $_POST['estadoCivil'] ?? null,
        'telefone' => preg_replace('/[^0-9]/', '', $_POST['telefone']),
        'indicacao' => $indicacaoNome, // Mantém na tabela Associados para compatibilidade
        'dataFiliacao' => !empty($_POST['dataFiliacao']) ? $_POST['dataFiliacao'] : null,
        'dataDesfiliacao' => !empty($_POST['dataDesfiliacao']) ? $_POST['dataDesfiliacao'] : null,
        'associado_titular_id' => $associadoTitularId, // ✅ NOVO: Vínculo com titular (para agregados)
        // Dados militares
        'corporacao' => $_POST['corporacao'] ?? null,
        'patente' => $_POST['patente'] ?? null,
        'categoria' => $_POST['categoria'] ?? null,
        'lotacao' => trim($_POST['lotacao'] ?? '') ?: null,
        'telefoneLotacao' => preg_replace('/[^0-9]/', '', $_POST['telefoneLotacao'] ?? '') ?: null,
        'unidade' => trim($_POST['unidade'] ?? '') ?: null,
        // Endereço
        'cep' => preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '') ?: null,
        'endereco' => trim($_POST['endereco'] ?? '') ?: null,
        'numero' => trim($_POST['numero'] ?? '') ?: null,
        'complemento' => trim($_POST['complemento'] ?? '') ?: null,
        'bairro' => trim($_POST['bairro'] ?? '') ?: null,
        'cidade' => trim($_POST['cidade'] ?? '') ?: null,
        'estado' => trim($_POST['estado'] ?? '') ?: null,
        // Financeiro
        'tipoAssociado' => $_POST['tipoAssociado'] ?? null,
        'situacaoFinanceira' => $_POST['situacaoFinanceira'] ?? null,
        'vinculoServidor' => $_POST['vinculoServidor'] ?? null,
        'localDebito' => $_POST['localDebito'] ?? null,
        'agencia' => trim($_POST['agencia'] ?? '') ?: null,
        'operacao' => trim($_POST['operacao'] ?? '') ?: null,
        'contaCorrente' => trim($_POST['contaCorrente'] ?? '') ?: null,
        // Serviços
        'tipoAssociadoServico' => $_POST['tipoAssociadoServico'] ?? null,
        'valorSocial' => $_POST['valorSocial'] ?? '0',
        'percentualAplicadoSocial' => $_POST['percentualAplicadoSocial'] ?? '0',
        'valorJuridico' => $_POST['valorJuridico'] ?? '0',
        'percentualAplicadoJuridico' => $_POST['percentualAplicadoJuridico'] ?? '0',
        'servicoJuridico' => $_POST['servicoJuridico'] ?? null
    ];

    // Aplica limpeza dos campos de data
    limparCamposData($dados);

    // Processa dependentes
    $dados['dependentes'] = [];
    if (isset($_POST['dependentes']) && is_array($_POST['dependentes'])) {
        foreach ($_POST['dependentes'] as $dep) {
            if (!empty($dep['nome'])) {
                $dependente = [
                    'nome' => trim($dep['nome']),
                    'parentesco' => $dep['parentesco'] ?? null,
                    'sexo' => $dep['sexo'] ?? null
                ];
                
                if ($dep['parentesco'] === 'Cônjuge') {
                    $dependente['telefone'] = preg_replace('/[^0-9]/', '', $dep['telefone'] ?? '');
                    $dependente['data_nascimento'] = !empty($dep['data_nascimento']) ? $dep['data_nascimento'] : null;
                } else {
                    $dependente['data_nascimento'] = !empty($dep['data_nascimento']) ? $dep['data_nascimento'] : null;
                    $dependente['telefone'] = null;
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

    $associados = new Associados();
    $documentos = new Documentos();
    $indicacoes = new Indicacoes(); // ✅ NOVO
    
    // PASSO 1: CRIA O PRÉ-CADASTRO
    $associadoId = $associados->criar($dados);
    
    if (!$associadoId) {
        throw new Exception('Erro ao criar pré-cadastro');
    }
    
    error_log("✓ Pré-cadastro criado com ID: $associadoId");

    // =====================================
    // ✅ PASSO 2: PROCESSA INDICAÇÃO
    // =====================================
    $indicacaoProcessada = false;
    $indicadorId = null;
    $indicadorInfo = null;
    
    if ($temIndicacao) {
        try {
            error_log("=== PROCESSANDO INDICAÇÃO ===");
            
            $resultadoIndicacao = $indicacoes->processarIndicacao(
                $associadoId,
                $indicacaoNome,
                $indicacaoPatente,
                $indicacaoCorporacao,
                $funcionarioId,
                "Indicação registrada no cadastro do associado"
            );
            
            if ($resultadoIndicacao['sucesso']) {
                $indicacaoProcessada = true;
                $indicadorId = $resultadoIndicacao['indicador_id'];
                $indicadorInfo = [
                    'id' => $indicadorId,
                    'nome' => $resultadoIndicacao['indicador_nome'],
                    'novo' => $resultadoIndicacao['novo_indicador'] ?? false
                ];
                
                error_log("✓ Indicação processada com sucesso!");
                error_log("  - Indicador ID: $indicadorId");
                error_log("  - Nome: " . $indicadorInfo['nome']);
                error_log("  - Novo indicador: " . ($indicadorInfo['novo'] ? 'SIM' : 'NÃO'));
            } else {
                error_log("⚠ Erro ao processar indicação: " . $resultadoIndicacao['erro']);
            }
            
        } catch (Exception $e) {
            error_log("⚠ Exceção ao processar indicação: " . $e->getMessage());
            // Não falha o cadastro por causa da indicação
        }
    }

    // PASSO 3: PROCESSA O DOCUMENTO
    $documentoId = null;
    $statusFluxo = 'DIGITALIZADO';
    $enviarAutomaticamente = isset($_POST['enviar_presidencia']) && $_POST['enviar_presidencia'] == '1';
    
    if (isset($_FILES['ficha_assinada']) && $_FILES['ficha_assinada']['error'] === UPLOAD_ERR_OK) {
        try {
            $documentoId = $documentos->uploadDocumentoAssociacao(
                $associadoId,
                $_FILES['ficha_assinada'],
                'FISICO',
                'Ficha de filiação assinada - Anexada durante pré-cadastro'
            );
            
            error_log("✓ Ficha assinada anexada com ID: $documentoId");
            
            if ($enviarAutomaticamente) {
                try {
                    $documentos->enviarParaAssinatura(
                        $documentoId,
                        "Pré-cadastro realizado - Enviado automaticamente para assinatura"
                    );
                    
                    $associados->enviarParaPresidencia(
                        $associadoId, 
                        "Documentação enviada automaticamente para aprovação"
                    );
                    
                    $statusFluxo = 'AGUARDANDO_ASSINATURA';
                    error_log("✓ Documento enviado para presidência assinar");
                    
                } catch (Exception $e) {
                    error_log("⚠ Aviso ao enviar para presidência: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            error_log("⚠ Erro ao processar documento: " . $e->getMessage());
        }
    }

    // PASSO 4: CRIA OS SERVIÇOS
    $servicos_criados = [];
    $valor_total_mensal = 0;

    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $db->beginTransaction();
        
        $tipoAssociadoServico = $_POST['tipoAssociadoServico'] ?? 'Contribuinte';
        
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
                $tipoAssociadoServico,
                $valorSocial,
                $percentualSocial,
                "Cadastro - Tipo: {$tipoAssociadoServico}"
            ]);
            
            $servicos_criados[] = 'Social';
            $valor_total_mensal += $valorSocial;
            error_log("✓ Serviço Social salvo");
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
                $tipoAssociadoServico,
                $valorJuridico,
                $percentualJuridico,
                "Cadastro - Tipo: {$tipoAssociadoServico}"
            ]);
            
            $servicos_criados[] = 'Jurídico';
            $valor_total_mensal += $valorJuridico;
            error_log("✓ Serviço Jurídico salvo");
        }
        
        $db->commit();
        error_log("✓ Serviços salvos com sucesso!");
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("⚠ Erro ao criar serviços: " . $e->getMessage());
    }

    // PASSO 5: SALVA DADOS EM JSON
    $resultadoJson = ['sucesso' => false, 'erro' => 'Não processado'];
    
    try {
        error_log("=== INICIANDO SALVAMENTO EM JSON ===");
        
        $jsonManager = new JsonManager();
        
        // ✅ Adiciona informações de indicação aos dados
        if ($indicacaoProcessada && $indicadorInfo) {
            $dados['indicacao_detalhes'] = [
                'indicador_id' => $indicadorInfo['id'],
                'indicador_nome' => $indicadorInfo['nome'],
                'processado' => true,
                'data_indicacao' => date('Y-m-d H:i:s')
            ];
        }
        
        $resultadoJson = $jsonManager->salvarAssociadoJson($dados, $associadoId, 'CREATE');
        
        if ($resultadoJson['sucesso']) {
            error_log("✓ JSON salvo com sucesso: " . $resultadoJson['arquivo_individual']);
        } else {
            error_log("⚠ Erro ao salvar JSON: " . $resultadoJson['erro']);
        }
        
    } catch (Exception $e) {
        $resultadoJson = [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
        error_log("✗ ERRO CRÍTICO ao salvar JSON: " . $e->getMessage());
    }

    // PASSO 6: ENVIA PARA ZAPSIGN
    $resultadoZapSign = ['sucesso' => false, 'erro' => 'Não processado'];
    
    try {
        error_log("=== INICIANDO ENVIO PARA ZAPSIGN ===");
        
        if (file_exists('../api/zapsign_api.php') && function_exists('enviarParaZapSign')) {
            if (method_exists($jsonManager, 'obterDadosCompletos')) {
                $dadosCompletos = $jsonManager->obterDadosCompletos($dados, $associadoId, 'CREATE');
                
                // ✅ Adiciona informações de indicação
                if ($indicacaoProcessada && $indicadorInfo) {
                    $dadosCompletos['indicacao'] = [
                        'indicador_nome' => $indicadorInfo['nome'],
                        'indicador_id' => $indicadorInfo['id'],
                        'data_indicacao' => date('Y-m-d H:i:s')
                    ];
                }
                
                $resultadoZapSign = enviarParaZapSign($dadosCompletos);
                
                if ($resultadoZapSign['sucesso']) {
                    error_log("✓ ZapSign enviado com sucesso!");
                    
                    try {
                        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
                        $stmt = $db->prepare("
                            UPDATE Associados 
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
            }
        }
        
    } catch (Exception $e) {
        $resultadoZapSign = [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
        error_log("✗ ERRO CRÍTICO no ZapSign: " . $e->getMessage());
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
            
            // ✅ NOVA SEÇÃO - INDICAÇÃO
            'indicacao' => [
                'tem_indicacao' => $temIndicacao,
                'processada' => $indicacaoProcessada,
                'indicador_id' => $indicadorId,
                'indicador_nome' => $indicacaoNome,
                'indicador_info' => $indicadorInfo,
                'mensagem' => $indicacaoProcessada 
                    ? 'Indicação registrada com sucesso' 
                    : ($temIndicacao ? 'Indicação salva mas não processada' : 'Sem indicação')
            ],
            
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
                'tem_ficha_assinada' => $documentoId !== null
            ],
            
            'json_export' => [
                'salvo' => $resultadoJson['sucesso'],
                'arquivo' => $resultadoJson['arquivo_individual'] ?? null,
                'tamanho_bytes' => $resultadoJson['tamanho_bytes'] ?? 0,
                'timestamp' => $resultadoJson['timestamp'] ?? null,
                'erro' => $resultadoJson['sucesso'] ? null : $resultadoJson['erro']
            ],
            
            'zapsign' => [
                'enviado' => $resultadoZapSign['sucesso'],
                'documento_id' => $resultadoZapSign['documento_id'] ?? null,
                'link_assinatura' => $resultadoZapSign['link_assinatura'] ?? null,
                'erro' => $resultadoZapSign['sucesso'] ? null : $resultadoZapSign['erro']
            ]
        ]
    ];
    
    // Atualiza mensagens
    if ($indicacaoProcessada) {
        $response['message'] .= ' Indicação registrada.';
    }
    
    if ($resultadoJson['sucesso']) {
        $response['message'] .= ' Dados exportados para integração.';
    }
    
    if ($resultadoZapSign['sucesso']) {
        $response['message'] .= ' Documento enviado para assinatura eletrônica.';
    }

    error_log("=== PRÉ-CADASTRO CONCLUÍDO COM SUCESSO ===");
    error_log("ID: {$associadoId} | Indicação: " . ($indicacaoProcessada ? '✓' : '✗'));

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

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>