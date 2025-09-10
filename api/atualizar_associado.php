<?php
/**
 * API para atualizar associado - VERSÃO COMPLETA CORRIGIDA
 * api/atualizar_associado.php
 * 
 * CORREÇÕES APLICADAS:
 * 1. Removido código de debug que forçava user_id = 1
 * 2. Implementada verificação adequada de autenticação
 * 3. Corrigido rastreamento do usuário real na auditoria
 * 4. Mantidas todas as funcionalidades originais (indicações, serviços, etc)
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
    require_once '../classes/Indicacoes.php';

    // =========================================
    // CORREÇÃO PRINCIPAL: AUTENTICAÇÃO ADEQUADA
    // =========================================
    
    // Inicia sessão se ainda não iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Inicializa Auth para verificar usuário logado
    $auth = new Auth();
    
    // Verifica se usuário está autenticado
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado. Faça login para continuar.');
    }
    
    // Obtém dados do usuário logado REAL através da classe Auth
    $usuarioLogadoReal = $auth->getUser();
    
    // Se ainda assim não tiver os dados, tenta pegar da sessão
    if (!$usuarioLogadoReal || !isset($usuarioLogadoReal['id'])) {
        // Verifica diretamente na sessão
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['funcionario_id'])) {
            throw new Exception('Sessão inválida. Por favor, faça login novamente.');
        }
        
        // Monta dados do usuário a partir da sessão
        $usuarioLogadoReal = [
            'id' => $_SESSION['user_id'],
            'nome' => $_SESSION['user_name'] ?? 'Usuário',
            'email' => $_SESSION['user_email'] ?? null,
            'funcionario_id' => $_SESSION['funcionario_id']
        ];
    }
    
    // Valida que temos um ID de usuário válido
    if (empty($usuarioLogadoReal['id'])) {
        throw new Exception('ID do usuário não encontrado na sessão');
    }
    
    // Define as variáveis corretas do usuário logado
    $usuarioLogado = [
        'id' => $usuarioLogadoReal['funcionario_id'] ?? $usuarioLogadoReal['id'],
        'nome' => $usuarioLogadoReal['nome'],
        'email' => $usuarioLogadoReal['email'] ?? null
    ];
    
    $funcionarioId = $usuarioLogadoReal['funcionario_id'] ?? $usuarioLogadoReal['id'];
    
    // Log de debug com usuário REAL
    error_log("=== ATUALIZAR ASSOCIADO - USUÁRIO REAL ===");
    error_log("Associado ID: $associadoId");
    error_log("Usuário Logado ID: " . $usuarioLogado['id']);
    error_log("Usuário Nome: " . $usuarioLogado['nome']);
    error_log("Funcionário ID: " . $funcionarioId);

    // Validação básica dos campos
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
    $indicacoes = new Indicacoes();
    
    $associadoAtual = $associados->getById($associadoId);
    if (!$associadoAtual) {
        throw new Exception('Associado não encontrado');
    }

    error_log("✓ Associado encontrado: " . $associadoAtual['nome']);
    error_log("✓ Atualização será registrada pelo usuário: " . $usuarioLogado['nome'] . " (ID: " . $usuarioLogado['id'] . ")");

    // =====================================
    // PROCESSAMENTO DA INDICAÇÃO
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

    // Verifica mudança de situação
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
        // 1. ATUALIZA OS DADOS BÁSICOS DO ASSOCIADO
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
            'indicacao' => $indicacaoNome,
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

        // Rollback para usar transação própria da classe
        $db->rollback();
        $transacaoAtiva = false;

        // Atualiza através da classe Associados
        $resultado = $associados->atualizar($associadoId, $dados);

        if (!$resultado) {
            throw new Exception('Erro ao atualizar dados básicos do associado');
        }

        // Atualiza data_desfiliacao se necessário
        if ($resultado && $mudouSituacao && ($ficouDesfiliado || $saiuDeDesfiliado)) {
            try {
                $stmt = $db->prepare("UPDATE Associados SET data_desfiliacao = ? WHERE id = ?");
                $stmt->execute([$dados['dataDesfiliacao'], $associadoId]);
                error_log("✅ data_desfiliacao atualizada diretamente: " . ($dados['dataDesfiliacao'] ?? 'NULL'));
            } catch (Exception $e) {
                error_log("❌ Erro ao atualizar data_desfiliacao: " . $e->getMessage());
            }
        }

        error_log("✓ Dados básicos atualizados pelo usuário: " . $usuarioLogado['nome']);

        // =====================================
        // 2. PROCESSA INDICAÇÃO SE MUDOU
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
        // 3. PROCESSA OS SERVIÇOS
        // =====================================
        $servicosAlterados = false;
        $detalhesServicos = [];
        $tipoAssociadoServico = trim($_POST['tipoAssociadoServico'] ?? '');

        error_log("=== PROCESSANDO SERVIÇOS ===");
        error_log("Tipo Associado Serviço: '$tipoAssociadoServico'");

        // Busca TODOS os serviços do associado
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
            }
        }

        // PROCESSA CADA SERVIÇO
        foreach ([1, 2] as $servicoId) {
            $servicoNome = ($servicoId == 1) ? 'Social' : 'Jurídico';
            $servicoExistente = isset($servicosExistentesMap[$servicoId]) ? $servicosExistentesMap[$servicoId] : null;
            $novoServico = isset($novosServicos[$servicoId]) ? $novosServicos[$servicoId] : null;

            error_log("--- Processando Serviço $servicoNome (ID: $servicoId) ---");

            if ($novoServico) {
                // QUER ESTE SERVIÇO
                if ($servicoExistente) {
                    // JÁ EXISTE - ATUALIZAR
                    $valorMudou = abs($servicoExistente['valor_aplicado'] - $novoServico['valor_aplicado']) > 0.01;
                    $percentualMudou = abs($servicoExistente['percentual_aplicado'] - $novoServico['percentual_aplicado']) > 0.01;
                    $tipoMudou = ($servicoExistente['tipo_associado'] ?? '') !== ($novoServico['tipo_associado'] ?? '');
                    $precisaReativar = $servicoExistente['ativo'] == 0;

                    if ($valorMudou || $percentualMudou || $tipoMudou || $precisaReativar) {
                        // Registra histórico
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
                                    $usuarioLogado['id'] // USANDO O ID CORRETO DO USUÁRIO
                                ]);

                                error_log("✓ Histórico registrado para serviço $servicoNome");
                            } catch (Exception $e) {
                                error_log("⚠ Erro ao registrar histórico: " . $e->getMessage());
                            }
                        }

                        // Atualiza o serviço
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
                        $detalhesServicos[] = ($precisaReativar ? "Reativado" : "Atualizado") . 
                                              " {$servicoNome}: R$ " . 
                                              number_format($novoServico['valor_aplicado'], 2, ',', '.');
                        error_log("✓ Serviço $servicoNome " . ($precisaReativar ? "reativado" : "atualizado"));
                    }
                } else {
                    // NÃO EXISTE - CRIAR NOVO
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
                        $detalhesServicos[] = "Adicionado {$servicoNome}: R$ " . 
                                            number_format($novoServico['valor_aplicado'], 2, ',', '.');
                        error_log("✓ Serviço $servicoNome criado");
                        
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            // Chave duplicada - atualiza
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
                            $detalhesServicos[] = "Atualizado {$servicoNome}: R$ " . 
                                                number_format($novoServico['valor_aplicado'], 2, ',', '.');
                            error_log("✓ Serviço $servicoNome atualizado via fallback");
                        } else {
                            throw $e;
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
                }
            }
        }

        error_log("=== FIM PROCESSAMENTO SERVIÇOS ===");

        // =====================================
        // 4. SALVA AUDITORIA COM USUÁRIO CORRETO
        // =====================================
        if (($mudouSituacao && $ficouDesfiliado) || $servicosAlterados || $indicacaoMudou) {
            $stmt = $db->prepare("
                INSERT INTO Auditoria (
                    tabela, 
                    acao, 
                    registro_id, 
                    funcionario_id,  -- USUÁRIO CORRETO
                    associado_id,
                    alteracoes, 
                    data_hora, 
                    ip_origem,
                    browser_info,
                    sessao_id
                ) VALUES (
                    'Associados', 
                    'UPDATE', 
                    ?, 
                    ?,  -- ID DO USUÁRIO REAL
                    ?,
                    ?, 
                    NOW(), 
                    ?,
                    ?,
                    ?
                )
            ");

            $alteracoes = [
                'usuario_que_alterou' => [
                    'id' => $usuarioLogado['id'],
                    'nome' => $usuarioLogado['nome'],
                    'email' => $usuarioLogado['email']
                ],
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
                $associadoId,                                      // registro_id
                $usuarioLogado['id'],                              // funcionario_id - USUÁRIO REAL
                $associadoId,                                      // associado_id
                json_encode($alteracoes, JSON_UNESCAPED_UNICODE), // alteracoes
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',           // ip_origem
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',         // browser_info
                session_id()                                       // sessao_id
            ]);

            error_log("✓ Auditoria registrada para usuário ID: " . $usuarioLogado['id'] . " (" . $usuarioLogado['nome'] . ")");
        }

        // Confirma transação
        $db->commit();
        $transacaoAtiva = false;
        error_log("✓ Transação confirmada");

        // 5. SALVA DADOS EM JSON
        $resultadoJson = ['sucesso' => false, 'erro' => 'Não processado'];

        try {
            error_log("=== SALVANDO EM JSON ===");

            $jsonManager = new JsonManager();
            
            // Adiciona informações de indicação aos dados
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
                error_log("✓ JSON atualizado: " . $resultadoJson['arquivo_individual']);
            } else {
                error_log("⚠ Erro ao salvar JSON: " . $resultadoJson['erro']);
            }
        } catch (Exception $e) {
            $resultadoJson = [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
            error_log("✗ Erro ao salvar JSON: " . $e->getMessage());
        }

        // Busca dados atualizados
        $associadoAtualizado = $associados->getById($associadoId);

        // Log final
        error_log("✓ SUCESSO - Associado ID $associadoId atualizado");
        error_log("✓ Atualizado por: " . $usuarioLogado['nome'] . " (ID: " . $usuarioLogado['id'] . ")");

        // Resposta de sucesso
        $response = [
            'status' => 'success',
            'message' => 'Associado atualizado com sucesso!',
            'data' => [
                'id' => $associadoId,
                'nome' => $associadoAtualizado['nome'] ?? $dados['nome'],
                'cpf' => $associadoAtualizado['cpf'] ?? $dados['cpf'],
                
                // Informações do usuário que atualizou
                'atualizado_por' => [
                    'id' => $usuarioLogado['id'],
                    'nome' => $usuarioLogado['nome'],
                    'timestamp' => date('Y-m-d H:i:s')
                ],
                
                // Informações de indicação
                'indicacao' => [
                    'mudou' => $indicacaoMudou,
                    'anterior' => $indicacaoAnterior,
                    'nova' => $indicacaoNome,
                    'processada' => $indicacaoProcessada,
                    'indicador_id' => $indicadorId,
                    'indicador_info' => $indicadorInfo
                ],
                
                // Informações de situação
                'situacao_alterada' => $mudouSituacao,
                'situacao_anterior' => $situacaoAtual,
                'situacao_nova' => $novaSituacao,
                'desfiliacao_processada' => $ficouDesfiliado,
                'reativacao_processada' => $saiuDeDesfiliado,
                'data_desfiliacao' => $dataDesfiliacao,
                
                // Informações de serviços
                'servicos_alterados' => $servicosAlterados,
                'detalhes_servicos' => $detalhesServicos,
                'tipo_associado_servico' => $tipoAssociadoServico,
                
                // Informações de JSON
                'json_export' => [
                    'atualizado' => $resultadoJson['sucesso'],
                    'arquivo' => $resultadoJson['arquivo_individual'] ?? null,
                    'erro' => $resultadoJson['sucesso'] ? null : $resultadoJson['erro']
                ]
            ]
        ];

        if ($indicacaoMudou && $indicacaoProcessada) {
            $response['message'] .= ' Indicação atualizada.';
        }
        
        if ($resultadoJson['sucesso']) {
            $response['message'] .= ' Dados exportados.';
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
            'user_session' => [
                'user_id' => $_SESSION['user_id'] ?? 'não definido',
                'funcionario_id' => $_SESSION['funcionario_id'] ?? 'não definido'
            ]
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