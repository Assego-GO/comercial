<?php
/**
 * API para criar novo associado - VERSÃO SIMPLIFICADA
 * api/criar_associado_simplificado.php
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
    require_once '../classes/Indicacoes.php';

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

    error_log("=== CRIAR PRÉ-CADASTRO SIMPLIFICADO ===");
    error_log("Usuário: " . ($_SESSION['user_name'] ?? 'N/A'));
    error_log("Funcionário ID: " . $funcionarioId);

    // ✅ VALIDAÇÃO BÁSICA - APENAS CAMPOS ESSENCIAIS (SEM dataFiliacao)
    $campos_obrigatorios = ['nome', 'cpf', 'rg', 'telefone', 'situacao'];
    
    foreach ($campos_obrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("Campo '$campo' é obrigatório");
        }
    }

    // ✅ CAPTURA DADOS DE INDICAÇÃO
    $indicacaoNome = trim($_POST['indicacao'] ?? '');
    $temIndicacao = !empty($indicacaoNome);

    if ($temIndicacao) {
        error_log("📌 Indicação detectada: $indicacaoNome");
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

    // Prepara dados do associado - APENAS DADOS BÁSICOS
    $dados = [
        // Dados pessoais obrigatórios
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
        'indicacao' => $indicacaoNome,
        
        // ✅ Data de filiação: usa a data de hoje se não informada
        'dataFiliacao' => !empty($_POST['dataFiliacao']) ? $_POST['dataFiliacao'] : date('Y-m-d'),
        'dataDesfiliacao' => null,
        
        // Financeiro simplificado - apenas optante jurídico
        'tipoAssociadoServico' => 'Contribuinte', // Padrão
        'valorSocial' => '173.10', // Valor padrão do serviço social
        'percentualAplicadoSocial' => '100',
        'valorJuridico' => '0',
        'percentualAplicadoJuridico' => '0',
        'servicoJuridico' => null
    ];

    error_log("✓ Data de filiação definida: " . $dados['dataFiliacao']);

    // Verifica se optou pelo serviço jurídico
    if (isset($_POST['optanteJuridico']) && $_POST['optanteJuridico'] == '1') {
        $dados['servicoJuridico'] = '1';
        $dados['valorJuridico'] = '43.28'; // Valor padrão do serviço jurídico
        $dados['percentualAplicadoJuridico'] = '100';
        error_log("✓ Associado optou pelo serviço jurídico");
    }

    // Aplica limpeza dos campos de data
    limparCamposData($dados);

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
    $indicacoes = new Indicacoes();

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
                null, // patente
                null, // corporação
                $funcionarioId,
                "Indicação registrada no cadastro simplificado"
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
        } catch (Exception $e) {
            error_log("⚠ Exceção ao processar indicação: " . $e->getMessage());
            // Não falha o cadastro por causa da indicação
        }
    }

    // PASSO 3: CRIA OS SERVIÇOS BÁSICOS
    $servicos_criados = [];
    $valor_total_mensal = 0;

    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $db->beginTransaction();

        // Serviço Social (sempre obrigatório)
        $stmt = $db->prepare("
            INSERT INTO Servicos_Associado (
                associado_id, servico_id, tipo_associado, ativo, data_adesao,
                valor_aplicado, percentual_aplicado, observacao
            ) VALUES (?, 1, ?, 1, NOW(), ?, ?, ?)
        ");

        $valorSocial = floatval($dados['valorSocial']);
        $percentualSocial = floatval($dados['percentualAplicadoSocial']);

        $stmt->execute([
            $associadoId,
            'Contribuinte',
            $valorSocial,
            $percentualSocial,
            "Cadastro simplificado - Tipo: Contribuinte"
        ]);

        $servicos_criados[] = 'Social';
        $valor_total_mensal += $valorSocial;
        error_log("✓ Serviço Social salvo: R$ " . number_format($valorSocial, 2, ',', '.'));

        // Serviço Jurídico (se optou)
        if ($dados['servicoJuridico'] && floatval($dados['valorJuridico']) > 0) {
            $stmt = $db->prepare("
                INSERT INTO Servicos_Associado (
                    associado_id, servico_id, tipo_associado, ativo, data_adesao,
                    valor_aplicado, percentual_aplicado, observacao
                ) VALUES (?, 2, ?, 1, NOW(), ?, ?, ?)
            ");

            $valorJuridico = floatval($dados['valorJuridico']);
            $percentualJuridico = floatval($dados['percentualAplicadoJuridico']);

            $stmt->execute([
                $associadoId,
                'Contribuinte',
                $valorJuridico,
                $percentualJuridico,
                "Cadastro simplificado - Optante jurídico"
            ]);

            $servicos_criados[] = 'Jurídico';
            $valor_total_mensal += $valorJuridico;
            error_log("✓ Serviço Jurídico salvo: R$ " . number_format($valorJuridico, 2, ',', '.'));
        }

        $db->commit();
        error_log("✓ Serviços salvos com sucesso! Total: R$ " . number_format($valor_total_mensal, 2, ',', '.'));

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("⚠ Erro ao criar serviços: " . $e->getMessage());
    }

    // =====================================
    // RESPOSTA FINAL
    // =====================================

    // Monta resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Cadastro simplificado realizado com sucesso!',
        'data' => [
            'id' => $associadoId,
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf'],
            'data_filiacao' => $dados['dataFiliacao'],
            'pre_cadastro' => true,
            
            // Indicação
            'indicacao' => [
                'tem_indicacao' => $temIndicacao,
                'processada' => $indicacaoProcessada,
                'indicador_nome' => $indicacaoNome,
                'indicador_info' => $indicadorInfo,
                'mensagem' => $indicacaoProcessada 
                    ? 'Indicação registrada com sucesso' 
                    : ($temIndicacao ? 'Indicação salva mas não processada' : 'Sem indicação')
            ],
            
            // Serviços
            'servicos' => [
                'social' => true,
                'juridico' => $dados['servicoJuridico'] ? true : false,
                'lista' => $servicos_criados,
                'total' => count($servicos_criados),
                'valor_mensal' => number_format($valor_total_mensal, 2, ',', '.')
            ],
            
            // Extras
            'tem_foto' => !empty($dados['foto']),
            'tipo_cadastro' => 'simplificado'
        ]
    ];

    // Atualiza mensagens
    if ($indicacaoProcessada) {
        $response['message'] .= ' Indicação registrada.';
    }
    
    if ($dados['servicoJuridico']) {
        $response['message'] .= ' Serviço jurídico incluído.';
    }

    error_log("=== CADASTRO SIMPLIFICADO CONCLUÍDO COM SUCESSO ===");
    error_log("ID: {$associadoId} | Indicação: " . ($indicacaoProcessada ? '✓' : '✗') . " | Jurídico: " . ($dados['servicoJuridico'] ? '✓' : '✗'));

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