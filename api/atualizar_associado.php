<?php
/**
 * API para atualizar associado - VERSÃO CORRIGIDA
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
    
    error_log("=== ATUALIZAR ASSOCIADO - VERSÃO CORRIGIDA ===");
    error_log("ID: $associadoId | Usuário: " . $usuarioLogado['nome']);
    error_log("POST dados: " . json_encode($_POST, JSON_PARTIAL_OUTPUT_ON_ERROR));

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
            'dataDesfiliacao' => $_POST['dataDesfiliacao'] ?? null,
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
            'contaCorrente' => trim($_POST['contaCorrente'] ?? '') ?: null
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
        
        if (!$resultado) {
            throw new Exception('Erro ao atualizar dados básicos do associado');
        }
        
        error_log("✓ Dados básicos atualizados");

        // INICIA NOVA TRANSAÇÃO PARA OS SERVIÇOS
        $db->beginTransaction();
        $transacaoAtiva = true;

        // 2. AGORA PROCESSA OS SERVIÇOS DE FORMA CORRETA
        $servicosAlterados = false;
        $detalhesServicos = [];
        
        // CORREÇÃO: Captura o tipo de associado para serviços CORRETAMENTE
        $tipoAssociadoServico = trim($_POST['tipoAssociadoServico'] ?? '');
        
        error_log("=== PROCESSAMENTO DE SERVIÇOS ===");
        error_log("Tipo de associado recebido: '$tipoAssociadoServico'");
        
        // BUSCA TODOS os serviços atuais (ativos e inativos)
        $stmt = $db->prepare("
            SELECT sa.*, s.nome as servico_nome 
            FROM Servicos_Associado sa
            INNER JOIN Servicos s ON sa.servico_id = s.id
            WHERE sa.associado_id = ?
            ORDER BY sa.id DESC
        ");
        $stmt->execute([$associadoId]);
        $todosServicosAtuais = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Busca apenas os ativos para comparação
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
        
        error_log("✓ Serviços atuais encontrados: " . count($servicosAtivos) . " ativos de " . count($todosServicosAtuais) . " total");

        // DEFINE OS NOVOS SERVIÇOS COM BASE NO FORMULÁRIO
        $novosServicos = [];
        
        // Verifica se tem dados de serviços no formulário
        if (!empty($tipoAssociadoServico)) {
            
            // SERVIÇO SOCIAL (ID = 1) - sempre presente se tem tipo de associado
            $valorSocialStr = trim($_POST['valorSocial'] ?? '0');
            $valorSocial = floatval($valorSocialStr);
            
            error_log("Valor Social recebido: '$valorSocialStr' -> $valorSocial");
            
            // CORREÇÃO: Aceita valor 0 para isentos
            if ($valorSocialStr !== '' && $valorSocial >= 0) {
                $novosServicos[1] = [
                    'valor_aplicado' => $valorSocial,
                    'percentual_aplicado' => floatval($_POST['percentualAplicadoSocial'] ?? 0),
                    'observacao' => "Atualizado - Tipo: $tipoAssociadoServico"
                ];
                error_log("✓ Novo serviço Social: R$ $valorSocial (incluindo isentos)");
            }
            
            // SERVIÇO JURÍDICO (ID = 2) - apenas se checkbox marcado E valor > 0
            if (!empty($_POST['servicoJuridico']) && 
                !empty($_POST['valorJuridico']) && 
                floatval($_POST['valorJuridico']) > 0) {
                
                $novosServicos[2] = [
                    'valor_aplicado' => floatval($_POST['valorJuridico']),
                    'percentual_aplicado' => floatval($_POST['percentualAplicadoJuridico'] ?? 100),
                    'observacao' => "Atualizado - Tipo: $tipoAssociadoServico"
                ];
                error_log("✓ Novo serviço Jurídico: R$ " . $_POST['valorJuridico']);
            }
            
            error_log("✓ Total de novos serviços definidos: " . count($novosServicos));
        }

        // 3. PROCESSA CADA SERVIÇO
        foreach ([1, 2] as $servicoId) { // 1 = Social, 2 = Jurídico
            $servicoNome = ($servicoId == 1) ? 'Social' : 'Jurídico';
            $servicoAtivo = isset($servicosAtivosMap[$servicoId]) ? $servicosAtivosMap[$servicoId] : null;
            $novoServico = isset($novosServicos[$servicoId]) ? $novosServicos[$servicoId] : null;
            
            if ($novoServico) {
                // QUER MANTER/CRIAR ESTE SERVIÇO
                
                if ($servicoAtivo) {
                    // JÁ EXISTE E ESTÁ ATIVO - VERIFICAR SE PRECISA ATUALIZAR
                    
                    $valorMudou = abs($servicoAtivo['valor_aplicado'] - $novoServico['valor_aplicado']) > 0.01;
                    $percentualMudou = abs($servicoAtivo['percentual_aplicado'] - $novoServico['percentual_aplicado']) > 0.01;
                    
                    if ($valorMudou || $percentualMudou) {
                        // ATUALIZAR SERVIÇO EXISTENTE
                        
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
                            'Alteração via edição do associado',
                            $usuarioLogado['id']
                        ]);
                        
                        // Atualiza o serviço
                        $stmt = $db->prepare("
                            UPDATE Servicos_Associado 
                            SET valor_aplicado = ?, percentual_aplicado = ?, observacao = ?
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([
                            $novoServico['valor_aplicado'],
                            $novoServico['percentual_aplicado'],
                            $novoServico['observacao'],
                            $servicoAtivo['id']
                        ]);
                        
                        $servicosAlterados = true;
                        $detalhesServicos[] = "Atualizado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.');
                        error_log("✓ Serviço {$servicoNome} atualizado");
                    } else {
                        error_log("✓ Serviço {$servicoNome} sem alterações");
                    }
                    
                } else {
                    // NÃO EXISTE OU ESTÁ INATIVO - CRIAR/REATIVAR
                    
                    // Verifica se existe inativo
                    $servicoInativo = null;
                    foreach ($todosServicosAtuais as $s) {
                        if ($s['servico_id'] == $servicoId && $s['ativo'] == 0) {
                            $servicoInativo = $s;
                            break;
                        }
                    }
                    
                    if ($servicoInativo) {
                        // REATIVAR SERVIÇO EXISTENTE
                        $stmt = $db->prepare("
                            UPDATE Servicos_Associado 
                            SET ativo = 1, data_adesao = NOW(), valor_aplicado = ?, 
                                percentual_aplicado = ?, observacao = ?, data_cancelamento = NULL
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([
                            $novoServico['valor_aplicado'],
                            $novoServico['percentual_aplicado'],
                            $novoServico['observacao'],
                            $servicoInativo['id']
                        ]);
                        
                        // Registra histórico de reativação
                        $stmt = $db->prepare("
                            INSERT INTO Historico_Servicos_Associado (
                                servico_associado_id, tipo_alteracao, motivo, funcionario_id
                            ) VALUES (?, 'ADESAO', ?, ?)
                        ");
                        
                        $stmt->execute([
                            $servicoInativo['id'],
                            'Reativado na edição do associado',
                            $usuarioLogado['id']
                        ]);
                        
                        $servicosAlterados = true;
                        $detalhesServicos[] = "Reativado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.');
                        error_log("✓ Serviço {$servicoNome} reativado");
                        
                    } else {
                        // CRIAR NOVO SERVIÇO
                        $stmt = $db->prepare("
                            INSERT INTO Servicos_Associado (
                                associado_id, servico_id, ativo, data_adesao, 
                                valor_aplicado, percentual_aplicado, observacao
                            ) VALUES (?, ?, 1, NOW(), ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $associadoId,
                            $servicoId,
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
                            'Adicionado na edição do associado',
                            $usuarioLogado['id']
                        ]);
                        
                        $servicosAlterados = true;
                        $detalhesServicos[] = "Adicionado {$servicoNome}: R$ " . number_format($novoServico['valor_aplicado'], 2, ',', '.');
                        error_log("✓ Novo serviço {$servicoNome} criado");
                    }
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

        // CORREÇÃO: Salva o tipo de associado para serviços em uma tabela separada
        // ou como metadata do associado
        if (!empty($tipoAssociadoServico) && $servicosAlterados) {
            // Salva o tipo na tabela de auditoria para referência futura
            $stmt = $db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, funcionario_id, 
                    alteracoes, data_hora, ip_origem
                ) VALUES (
                    'Servicos_Associado', 'UPDATE', ?, ?, 
                    ?, NOW(), ?
                )
            ");
            
            $alteracoes = json_encode([
                'tipo_associado_servico' => $tipoAssociadoServico,
                'detalhes_servicos' => $detalhesServicos
            ]);
            
            $stmt->execute([
                $associadoId,
                $usuarioLogado['id'],
                $alteracoes,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
            
            error_log("✓ Tipo de associado salvo na auditoria: $tipoAssociadoServico");
        }

        // Confirma transação dos serviços
        $db->commit();
        $transacaoAtiva = false;
        error_log("✓ Transação dos serviços confirmada");

        // Busca dados atualizados
        $associadoAtualizado = $associados->getById($associadoId);
        
        // Log final
        error_log("✓ SUCESSO - Associado ID $associadoId atualizado completamente");
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
                'servicos_alterados' => $servicosAlterados,
                'detalhes_servicos' => $detalhesServicos,
                'total_alteracoes_servicos' => count($detalhesServicos),
                'tipo_associado_servico' => $tipoAssociadoServico
            ]
        ];

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
            'tipo_associado_recebido' => $_POST['tipoAssociadoServico'] ?? 'não informado'
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
function processarUploadFoto($arquivo, $cpf) {
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