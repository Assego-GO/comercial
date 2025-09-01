<?php
/**
 * API para atualizar associado - VERSÃO COM INDICAÇÕES INTEGRADAS E CORREÇÃO DE DUPLICATAS
 * api/atualizar_associado.php
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
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
    // Verifica método
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
        throw new Exception('Método não permitido. Use POST ou PUT.');
    }

    // Verifica se ID foi fornecido
    $associadoId = isset($_GET['id']) ? intval($_GET['id']) : null;
    if (!$associadoId) {
        throw new Exception('ID do associado não fornecido');
    }

    // Carrega configurações e classes
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/Auth.php';
    require_once '../classes/Associados.php';
    require_once '../classes/JsonManager.php';
    require_once '../classes/Indicacoes.php'; // ✅ NOVO

    // Inicia sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Simula login para debug (REMOVER EM PRODUÇÃO)
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Debug User';
        $_SESSION['user_email'] = 'debug@test.com';
        $_SESSION['funcionario_id'] = 1;
    }

    $usuarioLogado = [
        'id' => $_SESSION['user_id'],
        'nome' => $_SESSION['user_name'] ?? 'Usuário',
        'email' => $_SESSION['user_email'] ?? null
    ];
    
    $funcionarioId = $_SESSION['funcionario_id'] ?? null;

    error_log("=== ATUALIZAR ASSOCIADO COM INDICAÇÕES ===");
    error_log("ID: $associadoId | Usuário: " . $usuarioLogado['nome']);
    error_log("Funcionário ID: " . $funcionarioId);

    // Validação básica
    $camposObrigatorios = ['nome', 'cpf', 'rg', 'telefone', 'situacao'];
    foreach ($camposObrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("Campo '$campo' é obrigatório");
        }
    }

    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Busca dados atuais do associado
    $associados = new Associados();
    $indicacoes = new Indicacoes(); // ✅ NOVO
    
    $associadoAtual = $associados->getById($associadoId);
    if (!$associadoAtual) {
        throw new Exception('Associado não encontrado');
    }

    error_log("✓ Associado encontrado: " . $associadoAtual['nome']);

    // =====================================
    // ✅ CAPTURA E VERIFICA INDICAÇÃO
    // =====================================
    $indicacaoNome = trim($_POST['indicacao'] ?? '');
    $indicacaoAnterior = trim($associadoAtual['indicacao'] ?? '');
    $indicacaoMudou = $indicacaoNome !== $indicacaoAnterior;
    $temNovaIndicacao = !empty($indicacaoNome);
    $indicacaoPatente = null;
    $indicacaoCorporacao = null;
    
    if ($indicacaoMudou) {
        error_log("📌 Mudança de indicação detectada:");
        error_log("  - Anterior: '$indicacaoAnterior'");
        error_log("  - Nova: '$indicacaoNome'");
        
        if ($temNovaIndicacao) {
            // Tenta extrair patente e corporação do nome
            if (preg_match('/^(.*?)\s+(PM|BM)\s+(.*)$/i', $indicacaoNome, $matches)) {
                $indicacaoPatente = trim($matches[1]);
                $indicacaoCorporacao = ($matches[2] === 'PM') ? 'Polícia Militar' : 'Bombeiro Militar';
                error_log("  - Patente extraída: $indicacaoPatente");
                error_log("  - Corporação extraída: $indicacaoCorporacao");
            }
        }
    }

    // Verifica mudança de situação para atualizar data_desfiliacao
    $situacaoAtual = strtoupper(trim($associadoAtual['situacao'] ?? ''));
    $novaSituacao = strtoupper(trim($_POST['situacao'] ?? ''));
    $mudouSituacao = $situacaoAtual !== $novaSituacao;
    $ficouDesfiliado = $novaSituacao === 'DESFILIADO';
    $saiuDeDesfiliado = $situacaoAtual === 'DESFILIADO' && $novaSituacao !== 'DESFILIADO';

    // Determina a data de desfiliação
    $dataDesfiliacao = null;
    if ($ficouDesfiliado && $mudouSituacao) {
        $dataDesfiliacao = date('Y-m-d H:i:s');
        error_log("✅ Definindo data_desfiliacao = NOW() para nova desfiliação");
    } elseif ($saiuDeDesfiliado) {
        $dataDesfiliacao = null;
        error_log("✅ Limpando data_desfiliacao (reativação)");
    } else {
        $dataDesfiliacao = $_POST['dataDesfiliacao'] ?? $associadoAtual['data_desfiliacao'];
    }

    // INICIA TRANSAÇÃO
    $db->beginTransaction();
    $transacaoAtiva = true;

    try {
        // 1. PRIMEIRO ATUALIZA OS DADOS BÁSICOS DO ASSOCIADO
        $dados = [
            'nome' => trim($_POST['nome']),
            'nasc' => $_POST['nasc'] ?? null,
            'sexo' => $_POST['sexo'] ?? null,
            'rg' => trim($_POST['rg']),
            'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf']),
            'email' => trim($_POST['email'] ?? '') ?: null,
            'situacao' => $_POST['situacao'],
            'escolaridade' => $_POST['escolaridade'] ?? null,
            'estadoCivil' => $_POST['estadoCivil'] ?? null,
            'telefone' => preg_replace('/[^0-9]/', '', $_POST['telefone']),
            'indicacao' => $indicacaoNome, // Mantém na tabela Associados para compatibilidade
            'dataFiliacao' => $_POST['dataFiliacao'] ?? $associadoAtual['data_filiacao'],
            'dataDesfiliacao' => $dataDesfiliacao,
            'corporacao' => $_POST['corporacao'] ?? null,
            'patente' => $_POST['patente'] ?? null,
            'categoria' => $_POST['categoria'] ?? null,
            'lotacao' => trim($_POST['lotacao'] ?? '') ?: null,
            'unidade' => trim($_POST['unidade'] ?? '') ?: null,
            'cep' => preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '') ?: null,
            'endereco' => trim($_POST['endereco'] ?? '') ?: null,
            'numero' => trim($_POST['numero'] ?? '') ?: null,
            'complemento' => trim($_POST['complemento'] ?? '') ?: null,
            'bairro' => trim($_POST['bairro'] ?? '') ?: null,
            'cidade' => trim($_POST['cidade'] ?? '') ?: null,
            'tipoAssociado' => $_POST['tipoAssociado'] ?? null,
            'situacaoFinanceira' => $_POST['situacaoFinanceira'] ?? null,
            'vinculoServidor' => $_POST['vinculoServidor'] ?? null,
            'localDebito' => $_POST['localDebito'] ?? null,
            'agencia' => trim($_POST['agencia'] ?? '') ?: null,
            'operacao' => trim($_POST['operacao'] ?? '') ?: null,
            'contaCorrente' => trim($_POST['contaCorrente'] ?? '') ?: null,
            'observacoes' => trim($_POST['observacoes'] ?? '') ?: null,
            'doador' => isset($_POST['doador']) ? intval($_POST['doador']) : 0,
            'tipoAssociadoServico' => $_POST['tipoAssociadoServico'] ?? null,
            'valorSocial' => $_POST['valorSocial'] ?? '0',
            'percentualAplicadoSocial' => $_POST['percentualAplicadoSocial'] ?? '0',
            'valorJuridico' => $_POST['valorJuridico'] ?? '0',
            'percentualAplicadoJuridico' => $_POST['percentualAplicadoJuridico'] ?? '0',
            'servicoJuridico' => $_POST['servicoJuridico'] ?? null
        ];

        // Processa dependentes
        $dados['dependentes'] = [];
        if (isset($_POST['dependentes']) && is_array($_POST['dependentes'])) {
            foreach ($_POST['dependentes'] as $dep) {
                if (!empty($dep['nome'])) {
                    $dados['dependentes'][] = [
                        'nome' => trim($dep['nome']),
                        'data_nascimento' => $dep['data_nascimento'] ?? null,
                        'parentesco' => $dep['parentesco'] ?? null,
                        'sexo' => $dep['sexo'] ?? null
                    ];
                }
            }
        }

        // Processa foto se houver
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploadResult = processarUploadFoto($_FILES['foto'], $dados['cpf']);
                if ($uploadResult['success']) {
                    if (!empty($associadoAtual['foto']) && file_exists('../' . $associadoAtual['foto'])) {
                        unlink('../' . $associadoAtual['foto']);
                    }
                    $dados['foto'] = $uploadResult['path'];
                    error_log("✓ Foto atualizada");
                }
            } catch (Exception $e) {
                error_log("⚠ Erro na foto: " . $e->getMessage());
            }
        }

        // FAZER ROLLBACK DA TRANSAÇÃO AUTOMÁTICA DA CLASSE E USAR A NOSSA
        $db->rollback();
        $transacaoAtiva = false;

        // A classe vai criar sua própria transação
        $resultado = $associados->atualizar($associadoId, $dados);

        if ($resultado && $mudouSituacao && ($ficouDesfiliado || $saiuDeDesfiliado)) {
            try {
                $stmt = $db->prepare("UPDATE Associados SET data_desfiliacao = ? WHERE id = ?");
                $stmt->execute([$dados['dataDesfiliacao'], $associadoId]);
                error_log("✅ data_desfiliacao atualizada diretamente: " . ($dados['dataDesfiliacao'] ?? 'NULL'));
            } catch (Exception $e) {
                error_log("❌ Erro ao atualizar data_desfiliacao: " . $e->getMessage());
            }
        }

        if (!$resultado) {
            throw new Exception('Erro ao atualizar dados básicos do associado');
        }

        error_log("✓ Dados básicos atualizados");

        // =====================================
        // ✅ 2. PROCESSA INDICAÇÃO SE MUDOU
        // =====================================
        $indicacaoProcessada = false;
        $indicadorId = null;
        $indicadorInfo = null;
        
        if ($indicacaoMudou) {
            try {
                error_log("=== PROCESSANDO MUDANÇA DE INDICAÇÃO ===");
                
                if (!$temNovaIndicacao) {
                    // Removendo indicação
                    $indicacoes->removerIndicacao($associadoId);
                    $indicacaoProcessada = true;
                    error_log("✓ Indicação removida");
                    
                } else {
                    // Adicionando ou alterando indicação
                    $resultadoIndicacao = $indicacoes->processarIndicacao(
                        $associadoId,
                        $indicacaoNome,
                        $indicacaoPatente,
                        $indicacaoCorporacao,
                        $funcionarioId,
                        "Indicação " . (empty($indicacaoAnterior) ? "adicionada" : "alterada") . " na edição do associado"
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
                    } else {
                        error_log("⚠ Erro ao processar indicação: " . $resultadoIndicacao['erro']);
                    }
                }
                
            } catch (Exception $e) {
                error_log("⚠ Exceção ao processar indicação: " . $e->getMessage());
                // Não falha a atualização por causa da indicação
            }
        } else if ($temNovaIndicacao) {
            // Mantém a indicação existente mas busca informações
            try {
                $indicacaoExistente = $indicacoes->obterIndicacaoAssociado($associadoId);
                if ($indicacaoExistente) {
                    $indicadorInfo = [
                        'id' => $indicacaoExistente['indicador_id'],
                        'nome' => $indicacaoExistente['indicador_nome_atual'] ?? $indicacaoNome,
                        'novo' => false
                    ];
                    error_log("✓ Indicação existente mantida: " . $indicadorInfo['nome']);
                }
            } catch (Exception $e) {
                error_log("⚠ Erro ao buscar indicação existente: " . $e->getMessage());
            }
        }

        // INICIA NOVA TRANSAÇÃO PARA OS SERVIÇOS
        $db->beginTransaction();
        $transacaoAtiva = true;

        // =====================================
        // ✅ 3. PROCESSA OS SERVIÇOS - VERSÃO CORRIGIDA
        // =====================================
        $servicosAlterados = false;
        $detalhesServicos = [];
        $tipoAssociadoServico = trim($_POST['tipoAssociadoServico'] ?? '');

        error_log("=== PROCESSANDO SERVIÇOS ===");
        error_log("Tipo Associado Serviço: '$tipoAssociadoServico'");

        // Busca TODOS os serviços do associado (ativos e inativos)
        $stmt = $db->prepare("
            SELECT sa.*, s.nome as servico_nome 
            FROM Servicos_Associado sa
            INNER JOIN Servicos s ON sa.servico_id = s.id
            WHERE sa.associado_id = ?
        ");
        $stmt->execute([$associadoId]);
        $servicosExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organiza serviços existentes por ID
        $servicosExistentesMap = [];
        foreach ($servicosExistentes as $servico) {
            $servicosExistentesMap[$servico['servico_id']] = $servico;
        }

        error_log("Serviços existentes no banco: " . count($servicosExistentes));
        foreach ($servicosExistentes as $serv) {
            error_log("  - Serviço ID {$serv['servico_id']} ({$serv['servico_nome']}): Ativo={$serv['ativo']}, Valor={$serv['valor_aplicado']}");
        }

        // DEFINE OS NOVOS SERVIÇOS
        $novosServicos = [];

        if (!empty($tipoAssociadoServico)) {
            // SERVIÇO SOCIAL (ID = 1) - SEMPRE OBRIGATÓRIO
            $valorSocialStr = trim($_POST['valorSocial'] ?? '0');
            $valorSocial = floatval($valorSocialStr);

            if ($valorSocialStr !== '' && $valorSocial >= 0) {
                $novosServicos[1] = [
                    'tipo_associado' => $tipoAssociadoServico,
                    'valor_aplicado' => $valorSocial,
                    'percentual_aplicado' => floatval($_POST['percentualAplicadoSocial'] ?? 0),
                    'observacao' => "Atualizado - Tipo: $tipoAssociadoServico"
                ];
                error_log("✓ Serviço Social definido: R$ $valorSocial");
            }

            // SERVIÇO JURÍDICO (ID = 2) - OPCIONAL
            $servicoJuridicoMarcado = !empty($_POST['servicoJuridico']);
            $valorJuridicoStr = trim($_POST['valorJuridico'] ?? '0');
            $valorJuridico = floatval($valorJuridicoStr);

            if ($servicoJuridicoMarcado && $valorJuridico > 0) {
                $novosServicos[2] = [
                    'tipo_associado' => $tipoAssociadoServico,
                    'valor_aplicado' => $valorJuridico,
                    'percentual_aplicado' => floatval($_POST['percentualAplicadoJuridico'] ?? 100),
                    'observacao' => "Atualizado - Tipo: $tipoAssociadoServico"
                ];
                error_log("✓ Serviço Jurídico definido: R$ $valorJuridico");
            } else {
                error_log("⚠ Serviço Jurídico não será adicionado (marcado: " . ($servicoJuridicoMarcado ? 'sim' : 'não') . ", valor: $valorJuridico)");
            }
        }

        // PROCESSA CADA SERVIÇO (1=Social, 2=Jurídico)
        foreach ([1, 2] as $servicoId) {
            $servicoNome = ($servicoId == 1) ? 'Social' : 'Jurídico';
            $servicoExistente = isset($servicosExistentesMap[$servicoId]) ? $servicosExistentesMap[$servicoId] : null;
            $novoServico = isset($novosServicos[$servicoId]) ? $novosServicos[$servicoId] : null;

            error_log("--- Processando Serviço $servicoNome (ID: $servicoId) ---");
            error_log("Existe no banco: " . ($servicoExistente ? 'sim (ID: ' . $servicoExistente['id'] . ', Ativo: ' . $servicoExistente['ativo'] . ')' : 'não'));
            error_log("Novo serviço definido: " . ($novoServico ? 'sim (Valor: ' . $novoServico['valor_aplicado'] . ')' : 'não'));

            if ($novoServico) {
                // QUER ESTE SERVIÇO
                if ($servicoExistente) {
                    // JÁ EXISTE NO BANCO - ATUALIZAR OU REATIVAR
                    $valorMudou = abs($servicoExistente['valor_aplicado'] - $novoServico['valor_aplicado']) > 0.01;
                    $percentualMudou = abs($servicoExistente['percentual_aplicado'] - $novoServico['percentual_aplicado']) > 0.01;
                    $tipoMudou = ($servicoExistente['tipo_associado'] ?? '') !== ($novoServico['tipo_associado'] ?? '');
                    $precisaReativar = $servicoExistente['ativo'] == 0;

                    if ($valorMudou || $percentualMudou || $tipoMudou || $precisaReativar) {
                        // Registra histórico se não estiver apenas reativando
                        if ($valorMudou || $percentualMudou || $tipoMudou) {
                            try {
                                $stmt = $db->prepare("
                                    INSERT INTO Historico_Servicos_Associado (
                                        servico_associado_id, tipo_alteracao, valor_anterior, valor_novo,
                                        percentual_anterior, percentual_novo, motivo, funcionario_id, data_alteracao
                                    ) VALUES (?, 'ALTERACAO_VALOR', ?, ?, ?, ?, ?, ?, NOW())
                                ");

                                $stmt->execute([
                                    $servicoExistente['id'],
                                    $servicoExistente['valor_aplicado'],
                                    $novoServico['valor_aplicado'],
                                    $servicoExistente['percentual_aplicado'],
                                    $novoServico['percentual_aplicado'],
                                    'Alteração via edição do associado',
                                    $usuarioLogado['id']
                                ]);

                                error_log("✓ Histórico registrado para serviço $servicoNome");
                            } catch (Exception $e) {
                                error_log("⚠ Erro ao registrar histórico: " . $e->getMessage());
                                // Continua mesmo se falhar o histórico
                            }
                        }

                        // Atualiza o serviço existente
                        $stmt = $db->prepare("
                            UPDATE Servicos_Associado 
                            SET tipo_associado = ?, valor_aplicado = ?, percentual_aplicado = ?, 
                                observacao = ?, ativo = 1, data_cancelamento = NULL
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $novoServico['tipo_associado'],
                            $novoServico['valor_aplicado'],
                            $novoServico['percentual_aplicado'],
                            $novoServico['observacao'],
                            $servicoExistente['id']
                        ]);

                        $servicosAlterados = true;
                        
                        if ($precisaReativar) {
                            $detalhesServicos[] = "Reativado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.');
                            error_log("✓ Serviço $servicoNome reativado");
                        } else {
                            $detalhesServicos[] = "Atualizado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.');
                            error_log("✓ Serviço $servicoNome atualizado");
                        }
                    } else {
                        error_log("- Serviço $servicoNome já está correto, não precisa atualizar");
                    }
                } else {
                    // NÃO EXISTE NO BANCO - CRIAR NOVO COM INSERT ... ON DUPLICATE KEY UPDATE
                    error_log("Criando novo serviço $servicoNome...");
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO Servicos_Associado (
                                associado_id, servico_id, tipo_associado, ativo, data_adesao, 
                                valor_aplicado, percentual_aplicado, observacao
                            ) VALUES (?, ?, ?, 1, NOW(), ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                tipo_associado = VALUES(tipo_associado),
                                valor_aplicado = VALUES(valor_aplicado),
                                percentual_aplicado = VALUES(percentual_aplicado),
                                observacao = VALUES(observacao),
                                ativo = 1,
                                data_cancelamento = NULL
                        ");

                        $stmt->execute([
                            $associadoId,
                            $servicoId,
                            $novoServico['tipo_associado'],
                            $novoServico['valor_aplicado'],
                            $novoServico['percentual_aplicado'],
                            $novoServico['observacao']
                        ]);

                        $servicosAlterados = true;
                        $detalhesServicos[] = "Adicionado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.');
                        error_log("✓ Serviço $servicoNome criado com sucesso");
                        
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            // Erro de chave duplicada - tenta atualizar em vez de inserir
                            error_log("⚠ Chave duplicada detectada, tentando atualizar...");
                            
                            $stmt = $db->prepare("
                                UPDATE Servicos_Associado 
                                SET tipo_associado = ?, valor_aplicado = ?, percentual_aplicado = ?, 
                                    observacao = ?, ativo = 1, data_cancelamento = NULL
                                WHERE associado_id = ? AND servico_id = ?
                            ");

                            $stmt->execute([
                                $novoServico['tipo_associado'],
                                $novoServico['valor_aplicado'],
                                $novoServico['percentual_aplicado'],
                                $novoServico['observacao'],
                                $associadoId,
                                $servicoId
                            ]);

                            $servicosAlterados = true;
                            $detalhesServicos[] = "Atualizado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.');
                            error_log("✓ Serviço $servicoNome atualizado via fallback");
                        } else {
                            throw $e; // Re-lança outros erros
                        }
                    }
                }
            } else {
                // NÃO QUER ESTE SERVIÇO - DESATIVAR SE ESTIVER ATIVO
                if ($servicoExistente && $servicoExistente['ativo'] == 1) {
                    $stmt = $db->prepare("
                        UPDATE Servicos_Associado 
                        SET ativo = 0, data_cancelamento = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([$servicoExistente['id']]);

                    $servicosAlterados = true;
                    $detalhesServicos[] = "Removido {$servicoNome}";
                    error_log("✓ Serviço $servicoNome desativado");
                } else {
                    error_log("- Serviço $servicoNome já estava inativo ou não existia");
                }
            }
        }

        error_log("=== FIM PROCESSAMENTO SERVIÇOS ===");
        error_log("Serviços alterados: " . ($servicosAlterados ? 'sim' : 'não'));
        error_log("Detalhes: " . implode(', ', $detalhesServicos));

        // Salva auditoria incluindo informações de indicação
        if (($mudouSituacao && $ficouDesfiliado) || $servicosAlterados || $indicacaoMudou) {
            $stmt = $db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, funcionario_id, 
                    alteracoes, data_hora, ip_origem
                ) VALUES (
                    'Associados', 'UPDATE', ?, ?, 
                    ?, NOW(), ?
                )
            ");

            $alteracoes = [
                'situacao_alterada' => $mudouSituacao,
                'situacao_anterior' => $situacaoAtual,
                'situacao_nova' => $novaSituacao,
                'desfiliacao_registrada' => $ficouDesfiliado,
                'reativacao_registrada' => $saiuDeDesfiliado,
                'data_desfiliacao' => $dataDesfiliacao,
                'tipo_associado_servico' => $tipoAssociadoServico,
                'detalhes_servicos' => $detalhesServicos,
                'indicacao' => [
                    'mudou' => $indicacaoMudou,
                    'anterior' => $indicacaoAnterior,
                    'nova' => $indicacaoNome,
                    'processada' => $indicacaoProcessada,
                    'indicador_id' => $indicadorId
                ]
            ];

            $stmt->execute([
                $associadoId,
                $usuarioLogado['id'],
                json_encode($alteracoes),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            error_log("✓ Auditoria registrada com informações de indicação");
        }

        // Confirma transação dos serviços
        $db->commit();
        $transacaoAtiva = false;
        error_log("✓ Transação confirmada");

        // 4. SALVA DADOS EM JSON
        $resultadoJson = ['sucesso' => false, 'erro' => 'Não processado'];

        try {
            error_log("=== INICIANDO SALVAMENTO EM JSON (ATUALIZAÇÃO) ===");

            $jsonManager = new JsonManager();
            
            // ✅ Adiciona informações de indicação aos dados
            if ($indicadorInfo) {
                $dados['indicacao_detalhes'] = [
                    'indicador_id' => $indicadorInfo['id'],
                    'indicador_nome' => $indicadorInfo['nome'],
                    'processado' => true,
                    'data_atualizacao' => date('Y-m-d H:i:s')
                ];
            }
            
            $resultadoJson = $jsonManager->salvarAssociadoJson($dados, $associadoId, 'UPDATE');

            if ($resultadoJson['sucesso']) {
                error_log("✓ JSON atualizado com sucesso: " . $resultadoJson['arquivo_individual']);
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

        // Busca dados atualizados
        $associadoAtualizado = $associados->getById($associadoId);

        // Log final
        error_log("✓ SUCESSO - Associado ID $associadoId atualizado completamente");
        if ($indicacaoMudou) {
            error_log("✓ Indicação alterada: '$indicacaoAnterior' → '$indicacaoNome'");
        }

        // Resposta de sucesso
        $response = [
            'status' => 'success',
            'message' => 'Associado atualizado com sucesso!',
            'data' => [
                'id' => $associadoId,
                'nome' => $associadoAtualizado['nome'] ?? $dados['nome'],
                'cpf' => $associadoAtualizado['cpf'] ?? $dados['cpf'],
                
                // ✅ NOVA SEÇÃO - INDICAÇÃO
                'indicacao' => [
                    'mudou' => $indicacaoMudou,
                    'anterior' => $indicacaoAnterior,
                    'nova' => $indicacaoNome,
                    'processada' => $indicacaoProcessada,
                    'indicador_id' => $indicadorId,
                    'indicador_info' => $indicadorInfo,
                    'mensagem' => $indicacaoMudou 
                        ? ($indicacaoProcessada ? 'Indicação atualizada com sucesso' : 'Indicação salva mas não processada')
                        : 'Indicação mantida'
                ],
                
                'situacao_alterada' => $mudouSituacao,
                'situacao_anterior' => $situacaoAtual,
                'situacao_nova' => $novaSituacao,
                'desfiliacao_processada' => $ficouDesfiliado,
                'reativacao_processada' => $saiuDeDesfiliado,
                'data_desfiliacao' => $dataDesfiliacao,
                'servicos_alterados' => $servicosAlterados,
                'detalhes_servicos' => $detalhesServicos,
                'tipo_associado_servico' => $tipoAssociadoServico,
                
                'json_export' => [
                    'atualizado' => $resultadoJson['sucesso'],
                    'arquivo' => $resultadoJson['arquivo_individual'] ?? null,
                    'tamanho_bytes' => $resultadoJson['tamanho_bytes'] ?? 0,
                    'timestamp' => $resultadoJson['timestamp'] ?? null,
                    'erro' => $resultadoJson['sucesso'] ? null : $resultadoJson['erro']
                ]
            ]
        ];

        if ($indicacaoMudou && $indicacaoProcessada) {
            $response['message'] .= ' Indicação atualizada.';
        }
        
        if ($resultadoJson['sucesso']) {
            $response['message'] .= ' Dados atualizados na integração.';
        }

        if ($ficouDesfiliado) {
            $response['message'] .= ' Desfiliação registrada.';
        } elseif ($saiuDeDesfiliado) {
            $response['message'] .= ' Associado reativado.';
        }

    } catch (Exception $e) {
        if ($transacaoAtiva) {
            $db->rollback();
        }
        error_log("✗ Erro na transação: " . $e->getMessage());
        throw $e;
    }

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
            'associado_id' => $associadoId ?? 'não fornecido',
            'post_count' => count($_POST),
            'indicacao_recebida' => $_POST['indicacao'] ?? 'não informada'
        ]
    ];

    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;

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

        $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
        $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

        if ($arquivo['size'] > $tamanhoMaximo) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 5MB');
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