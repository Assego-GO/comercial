<?php

/**
 * API para atualizar associado - VERSÃO CORRIGIDA COM TIPO_ASSOCIADO E DATA_DESFILIACAO
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

    error_log("=== ATUALIZAR ASSOCIADO COM TIPO_ASSOCIADO E DATA_DESFILIACAO ===");
    error_log("ID: $associadoId | Usuário: " . $usuarioLogado['nome']);
    error_log("tipoAssociadoServico recebido: " . ($_POST['tipoAssociadoServico'] ?? 'NÃO INFORMADO'));
    error_log("situacao recebida: " . ($_POST['situacao'] ?? 'NÃO INFORMADA'));

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
    $associadoAtual = $associados->getById($associadoId);
    if (!$associadoAtual) {
        throw new Exception('Associado não encontrado');
    }

    error_log("✓ Associado encontrado: " . $associadoAtual['nome']);

    // ✅ NOVA LÓGICA: Verifica mudança de situação para atualizar data_desfiliacao
    $situacaoAtual = strtoupper(trim($associadoAtual['situacao'] ?? ''));
    $novaSituacao = strtoupper(trim($_POST['situacao'] ?? ''));
    $mudouSituacao = $situacaoAtual !== $novaSituacao;
    $ficouDesfiliado = $novaSituacao === 'DESFILIADO';
    $saiuDeDesfiliado = $situacaoAtual === 'DESFILIADO' && $novaSituacao !== 'DESFILIADO';

    error_log("=== VERIFICAÇÃO DE DESFILIAÇÃO ===");
    error_log("Situação atual: '$situacaoAtual' | Nova situação: '$novaSituacao'");
    error_log("Mudou situação: " . ($mudouSituacao ? 'SIM' : 'NÃO'));
    error_log("Ficou desfiliado: " . ($ficouDesfiliado ? 'SIM' : 'NÃO'));
    error_log("Saiu de desfiliado: " . ($saiuDeDesfiliado ? 'SIM' : 'NÃO'));

    // Determina a data de desfiliação
    $dataDesfiliacao = null;
    if ($ficouDesfiliado && $mudouSituacao) {
        // Está sendo desfiliado agora
        $dataDesfiliacao = date('Y-m-d H:i:s'); // NOW()
        error_log("✅ Definindo data_desfiliacao = NOW() para nova desfiliação");
    } elseif ($saiuDeDesfiliado) {
        // Estava desfiliado e agora está sendo reativado
        $dataDesfiliacao = null;
        error_log("✅ Limpando data_desfiliacao (reativação)");
    } else {
        // Mantém o valor atual ou usa o que veio do formulário
        $dataDesfiliacao = $_POST['dataDesfiliacao'] ?? $associadoAtual['data_desfiliacao'];
        error_log("✅ Mantendo data_desfiliacao existente: " . ($dataDesfiliacao ?? 'NULL'));
    }

    // INICIA TRANSAÇÃO ÚNICA PARA TUDO
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
            'indicacao' => trim($_POST['indicacao'] ?? '') ?: null,
            'dataFiliacao' => $_POST['dataFiliacao'] ?? $associadoAtual['data_filiacao'],
            'dataDesfiliacao' => $dataDesfiliacao, // ✅ Usa a lógica definida acima
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

        // Log da mudança de situação para auditoria
        if ($mudouSituacao) {
            error_log("🔄 MUDANÇA DE SITUAÇÃO DETECTADA:");
            error_log("   Associado: " . $associadoAtual['nome']);
            error_log("   De: '$situacaoAtual' → Para: '$novaSituacao'");
            error_log("   Data desfiliação: " . ($dataDesfiliacao ?? 'NULL'));

            if ($ficouDesfiliado) {
                error_log("🚨 NOVA DESFILIAÇÃO registrada em: " . date('Y-m-d H:i:s'));
            } elseif ($saiuDeDesfiliado) {
                error_log("🔄 REATIVAÇÃO de associado desfiliado");
            }
        }

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

        error_log("✓ Dados básicos atualizados (incluindo data_desfiliacao)");

        // INICIA NOVA TRANSAÇÃO PARA OS SERVIÇOS
        $db->beginTransaction();
        $transacaoAtiva = true;

        // 2. PROCESSA OS SERVIÇOS COM TIPO_ASSOCIADO CORRIGIDO
        $servicosAlterados = false;
        $detalhesServicos = [];

        // ✅ CORREÇÃO: Captura o tipo de associado
        $tipoAssociadoServico = trim($_POST['tipoAssociadoServico'] ?? '');

        error_log("=== PROCESSAMENTO DE SERVIÇOS CORRIGIDO ===");
        error_log("Tipo de associado para serviços: '$tipoAssociadoServico'");

        // Busca serviços atuais
        $stmt = $db->prepare("
            SELECT sa.*, s.nome as servico_nome 
            FROM Servicos_Associado sa
            INNER JOIN Servicos s ON sa.servico_id = s.id
            WHERE sa.associado_id = ? AND sa.ativo = 1
        ");
        $stmt->execute([$associadoId]);
        $servicosAtivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organiza serviços ativos por ID
        $servicosAtivosMap = [];
        foreach ($servicosAtivos as $servico) {
            $servicosAtivosMap[$servico['servico_id']] = $servico;
        }

        error_log("✓ Serviços atuais encontrados: " . count($servicosAtivos));

        // DEFINE OS NOVOS SERVIÇOS
        $novosServicos = [];

        if (!empty($tipoAssociadoServico)) {

            // SERVIÇO SOCIAL (ID = 1)
            $valorSocialStr = trim($_POST['valorSocial'] ?? '0');
            $valorSocial = floatval($valorSocialStr);

            if ($valorSocialStr !== '' && $valorSocial >= 0) {
                $novosServicos[1] = [
                    'tipo_associado' => $tipoAssociadoServico, // ✅ ADICIONADO
                    'valor_aplicado' => $valorSocial,
                    'percentual_aplicado' => floatval($_POST['percentualAplicadoSocial'] ?? 0),
                    'observacao' => "Atualizado - Tipo: $tipoAssociadoServico"
                ];
                error_log("✓ Novo serviço Social: R$ $valorSocial | Tipo: $tipoAssociadoServico");
            }

            // SERVIÇO JURÍDICO (ID = 2)
            if (
                !empty($_POST['servicoJuridico']) &&
                !empty($_POST['valorJuridico']) &&
                floatval($_POST['valorJuridico']) > 0
            ) {

                $novosServicos[2] = [
                    'tipo_associado' => $tipoAssociadoServico, // ✅ ADICIONADO
                    'valor_aplicado' => floatval($_POST['valorJuridico']),
                    'percentual_aplicado' => floatval($_POST['percentualAplicadoJuridico'] ?? 100),
                    'observacao' => "Atualizado - Tipo: $tipoAssociadoServico"
                ];
                error_log("✓ Novo serviço Jurídico: R$ " . $_POST['valorJuridico'] . " | Tipo: $tipoAssociadoServico");
            }
        }

        // 3. PROCESSA CADA SERVIÇO COM TIPO_ASSOCIADO
        foreach ([1, 2] as $servicoId) {
            $servicoNome = ($servicoId == 1) ? 'Social' : 'Jurídico';
            $servicoAtivo = isset($servicosAtivosMap[$servicoId]) ? $servicosAtivosMap[$servicoId] : null;
            $novoServico = isset($novosServicos[$servicoId]) ? $novosServicos[$servicoId] : null;

            if ($novoServico) {
                // QUER MANTER/CRIAR ESTE SERVIÇO

                if ($servicoAtivo) {
                    // JÁ EXISTE - VERIFICAR SE PRECISA ATUALIZAR

                    $valorMudou = abs($servicoAtivo['valor_aplicado'] - $novoServico['valor_aplicado']) > 0.01;
                    $percentualMudou = abs($servicoAtivo['percentual_aplicado'] - $novoServico['percentual_aplicado']) > 0.01;
                    $tipoMudou = ($servicoAtivo['tipo_associado'] ?? '') !== ($novoServico['tipo_associado'] ?? ''); // ✅ ADICIONADO

                    if ($valorMudou || $percentualMudou || $tipoMudou) {
                        // ✅ CORRIGIDO: ATUALIZAR SERVIÇO EXISTENTE COM TIPO_ASSOCIADO

                        // Registra histórico
                        $stmt = $db->prepare("
                            INSERT INTO Historico_Servicos_Associado (
                                servico_associado_id, tipo_alteracao, valor_anterior, valor_novo,
                                percentual_anterior, percentual_novo, motivo, funcionario_id
                            ) VALUES (?, 'ALTERACAO_VALOR', ?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $servicoAtivo['id'],
                            $servicoAtivo['valor_aplicado'],
                            $novoServico['valor_aplicado'],
                            $servicoAtivo['percentual_aplicado'],
                            $novoServico['percentual_aplicado'],
                            'Alteração via edição do associado' . ($tipoMudou ? " - Tipo alterado de '{$servicoAtivo['tipo_associado']}' para '{$novoServico['tipo_associado']}'" : ''),
                            $usuarioLogado['id']
                        ]);

                        // ✅ CORRIGIDO: Atualiza o serviço COM tipo_associado
                        $stmt = $db->prepare("
                            UPDATE Servicos_Associado 
                            SET tipo_associado = ?, valor_aplicado = ?, percentual_aplicado = ?, observacao = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $novoServico['tipo_associado'],
                            $novoServico['valor_aplicado'],
                            $novoServico['percentual_aplicado'],
                            $novoServico['observacao'],
                            $servicoAtivo['id']
                        ]);

                        $servicosAlterados = true;
                        $detalhesServicos[] = "Atualizado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.') . " (Tipo: {$novoServico['tipo_associado']})";
                        error_log("✓ Serviço {$servicoNome} atualizado com tipo_associado");
                    } else {
                        error_log("✓ Serviço {$servicoNome} sem alterações");
                    }
                } else {
                    // NÃO EXISTE - CRIAR NOVO

                    // ✅ CORRIGIDO: CRIAR NOVO SERVIÇO COM tipo_associado
                    $stmt = $db->prepare("
                        INSERT INTO Servicos_Associado (
                            associado_id, servico_id, tipo_associado, ativo, data_adesao, 
                            valor_aplicado, percentual_aplicado, observacao
                        ) VALUES (?, ?, ?, 1, NOW(), ?, ?, ?)
                    ");

                    $stmt->execute([
                        $associadoId,
                        $servicoId,
                        $novoServico['tipo_associado'],
                        $novoServico['valor_aplicado'],
                        $novoServico['percentual_aplicado'],
                        $novoServico['observacao']
                    ]);

                    $novoServicoId = $db->lastInsertId();

                    // Registra histórico de criação
                    $stmt = $db->prepare("
                        INSERT INTO Historico_Servicos_Associado (
                            servico_associado_id, tipo_alteracao, motivo, funcionario_id
                        ) VALUES (?, 'ADESAO', ?, ?)
                    ");

                    $stmt->execute([
                        $novoServicoId,
                        'Adicionado na edição do associado - Tipo: ' . $novoServico['tipo_associado'],
                        $usuarioLogado['id']
                    ]);

                    $servicosAlterados = true;
                    $detalhesServicos[] = "Adicionado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.') . " (Tipo: {$novoServico['tipo_associado']})";
                    error_log("✓ Novo serviço {$servicoNome} criado com tipo_associado");
                }
            } else {
                // NÃO QUER ESTE SERVIÇO - DESATIVAR SE ESTIVER ATIVO

                if ($servicoAtivo) {
                    // DESATIVAR SERVIÇO
                    $stmt = $db->prepare("
                        UPDATE Servicos_Associado 
                        SET ativo = 0, data_cancelamento = NOW()
                        WHERE id = ?
                    ");

                    $stmt->execute([$servicoAtivo['id']]);

                    // Registra histórico de cancelamento
                    $stmt = $db->prepare("
                        INSERT INTO Historico_Servicos_Associado (
                            servico_associado_id, tipo_alteracao, motivo, funcionario_id
                        ) VALUES (?, 'CANCELAMENTO', ?, ?)
                    ");

                    $stmt->execute([
                        $servicoAtivo['id'],
                        'Removido na edição do associado',
                        $usuarioLogado['id']
                    ]);

                    $servicosAlterados = true;
                    $detalhesServicos[] = "Removido {$servicoNome}";
                    error_log("✓ Serviço {$servicoNome} desativado");
                }
            }
        }

        // Salva auditoria com informações de desfiliação
        if (($mudouSituacao && $ficouDesfiliado) || (!empty($tipoAssociadoServico) && $servicosAlterados)) {
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
                'novos_campos_financeiros' => [
                    'observacoes' => $dados['observacoes'],
                    'doador' => $dados['doador']
                ]
            ];

            $stmt->execute([
                $associadoId,
                $usuarioLogado['id'],
                json_encode($alteracoes),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            if ($ficouDesfiliado) {
                error_log("✅ DESFILIAÇÃO registrada na auditoria: " . $associadoAtual['nome'] . " em " . date('Y-m-d H:i:s'));
            }
            if (!empty($tipoAssociadoServico)) {
                error_log("✓ Tipo de associado salvo na auditoria: $tipoAssociadoServico");
            }
        }

        // Confirma transação dos serviços
        $db->commit();
        $transacaoAtiva = false;
        error_log("✓ Transação dos serviços confirmada");

        // 3. SALVA DADOS EM JSON
        $resultadoJson = ['sucesso' => false, 'erro' => 'Não processado'];

        try {
            error_log("=== INICIANDO SALVAMENTO EM JSON (ATUALIZAÇÃO) ===");

            $jsonManager = new JsonManager();
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
        if ($mudouSituacao) {
            error_log("✓ Situação alterada: '$situacaoAtual' → '$novaSituacao'");
            if ($ficouDesfiliado) {
                error_log("🚨 NOVA DESFILIAÇÃO PROCESSADA com data_desfiliacao = $dataDesfiliacao");
            } elseif ($saiuDeDesfiliado) {
                error_log("🔄 REATIVAÇÃO PROCESSADA - data_desfiliacao limpa");
            }
        }
        if ($servicosAlterados) {
            error_log("✓ Alterações nos serviços: " . implode(', ', $detalhesServicos));
        }

        // Resposta de sucesso
        $response = [
            'status' => 'success',
            'message' => 'Associado atualizado com sucesso!',
            'data' => [
                'id' => $associadoId,
                'nome' => $associadoAtualizado['nome'] ?? $dados['nome'],
                'cpf' => $associadoAtualizado['cpf'] ?? $dados['cpf'],
                'situacao_alterada' => $mudouSituacao,
                'situacao_anterior' => $situacaoAtual,
                'situacao_nova' => $novaSituacao,
                'desfiliacao_processada' => $ficouDesfiliado,
                'reativacao_processada' => $saiuDeDesfiliado,
                'data_desfiliacao' => $dataDesfiliacao,
                'servicos_alterados' => $servicosAlterados,
                'detalhes_servicos' => $detalhesServicos,
                'total_alteracoes_servicos' => count($detalhesServicos),
                'tipo_associado_servico' => $tipoAssociadoServico,
                'novos_campos_financeiros' => [
                    'observacoes' => $dados['observacoes'],
                    'doador' => $dados['doador'],
                    'doador_texto' => $dados['doador'] ? 'Sim' : 'Não'
                ],
                'json_export' => [
                    'atualizado' => $resultadoJson['sucesso'],
                    'arquivo' => $resultadoJson['arquivo_individual'] ?? null,
                    'tamanho_bytes' => $resultadoJson['tamanho_bytes'] ?? 0,
                    'timestamp' => $resultadoJson['timestamp'] ?? null,
                    'erro' => $resultadoJson['sucesso'] ? null : $resultadoJson['erro'],
                    'operacao' => 'UPDATE',
                    'pronto_para_zapsing' => $resultadoJson['sucesso']
                ]
            ]
        ];

        if ($resultadoJson['sucesso']) {
            $response['message'] .= ' Dados atualizados na integração.';
        }

        if ($ficouDesfiliado) {
            $response['message'] .= ' Desfiliação registrada com data/hora.';
        } elseif ($saiuDeDesfiliado) {
            $response['message'] .= ' Associado reativado.';
        }

        if (!empty($dados['observacoes']) || $dados['doador']) {
            $infoNovos = [];
            if (!empty($dados['observacoes'])) {
                $infoNovos[] = 'observações financeiras';
            }
            if ($dados['doador']) {
                $infoNovos[] = 'status de doador';
            }
            $response['message'] .= ' Incluindo ' . implode(' e ', $infoNovos) . '.';
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
            'situacao_recebida' => $_POST['situacao'] ?? 'não informada',
            'tipo_associado_recebido' => $_POST['tipoAssociadoServico'] ?? 'não informado',
            'mudanca_situacao_detectada' => isset($mudouSituacao) ? ($mudouSituacao ? 'SIM' : 'NÃO') : 'não verificado',
            'novos_campos' => [
                'observacoes' => $_POST['observacoes'] ?? 'não informado',
                'doador' => $_POST['doador'] ?? 'não informado'
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
