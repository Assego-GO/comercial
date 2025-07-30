<?php
/**
 * API para criar novo associado - VERSÃO CORRIGIDA SEM CONFLITO DE TRANSAÇÕES
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

    error_log("=== CRIAR PRÉ-CADASTRO COM FLUXO INTEGRADO ===");
    error_log("Usuário: " . ($_SESSION['user_name'] ?? 'N/A'));
    error_log("POST fields: " . count($_POST));

    // Validação básica
    $campos_obrigatorios = ['nome', 'cpf', 'rg', 'telefone', 'situacao', 'dataFiliacao'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("Campo '$campo' é obrigatório");
        }
    }

    // Verifica se tem documento anexado (ficha assinada)
    if (!isset($_FILES['ficha_assinada']) || $_FILES['ficha_assinada']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("É obrigatório anexar a ficha de filiação assinada pelo associado");
    }

    // Prepara dados do associado
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
        'dataFiliacao' => $_POST['dataFiliacao'],
        'dataDesfiliacao' => $_POST['dataDesfiliacao'] ?? null,
        // Dados militares
        'corporacao' => $_POST['corporacao'] ?? null,
        'patente' => $_POST['patente'] ?? null,
        'categoria' => $_POST['categoria'] ?? null,
        'lotacao' => trim($_POST['lotacao'] ?? '') ?: null,
        'unidade' => trim($_POST['unidade'] ?? '') ?: null,
        // Endereço
        'cep' => preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '') ?: null,
        'endereco' => trim($_POST['endereco'] ?? '') ?: null,
        'numero' => trim($_POST['numero'] ?? '') ?: null,
        'complemento' => trim($_POST['complemento'] ?? '') ?: null,
        'bairro' => trim($_POST['bairro'] ?? '') ?: null,
        'cidade' => trim($_POST['cidade'] ?? '') ?: null,
        // Financeiro
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
        
        $tipoAssociadoServico = $_POST['tipoAssociadoServico'] ?? 'Contribuinte';
        
        // Serviço Social
        if (isset($_POST['valorSocial']) && floatval($_POST['valorSocial']) >= 0) {
            $stmt = $db->prepare("
                INSERT INTO Servicos_Associado (
                    associado_id, servico_id, ativo, data_adesao, 
                    valor_aplicado, percentual_aplicado, observacao
                ) VALUES (?, 1, 1, NOW(), ?, ?, ?)
            ");
            
            $valorSocial = floatval($_POST['valorSocial']);
            $percentualSocial = floatval($_POST['percentualAplicadoSocial'] ?? 100);
            
            $stmt->execute([
                $associadoId,
                $valorSocial,
                $percentualSocial,
                "Pré-cadastro - Tipo: {$tipoAssociadoServico}"
            ]);
            
            $servicos_criados[] = 'Social';
            $valor_total_mensal += $valorSocial;
            error_log("✓ Serviço Social: R$ " . number_format($valorSocial, 2, ',', '.'));
        }

        // Serviço Jurídico
        if (isset($_POST['servicoJuridico']) && $_POST['servicoJuridico'] == '2' && 
            isset($_POST['valorJuridico']) && floatval($_POST['valorJuridico']) > 0) {
            
            $stmt = $db->prepare("
                INSERT INTO Servicos_Associado (
                    associado_id, servico_id, ativo, data_adesao, 
                    valor_aplicado, percentual_aplicado, observacao
                ) VALUES (?, 2, 1, NOW(), ?, ?, ?)
            ");
            
            $valorJuridico = floatval($_POST['valorJuridico']);
            $percentualJuridico = floatval($_POST['percentualAplicadoJuridico'] ?? 100);
            
            $stmt->execute([
                $associadoId,
                $valorJuridico,
                $percentualJuridico,
                "Pré-cadastro - Tipo: {$tipoAssociadoServico}"
            ]);
            
            $servicos_criados[] = 'Jurídico';
            $valor_total_mensal += $valorJuridico;
            error_log("✓ Serviço Jurídico: R$ " . number_format($valorJuridico, 2, ',', '.'));
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("⚠ Erro ao criar serviços: " . $e->getMessage());
        // Continua - não é crítico
    }

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
                'tem_ficha_assinada' => $documentoId !== null
            ]
        ]
    ];

    error_log("=== PRÉ-CADASTRO CONCLUÍDO COM SUCESSO ===");
    error_log("ID: {$associadoId} | Documento: " . ($documentoId ?? 'N/A') . " | Status: {$statusFluxo}");

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

// Limpa buffer e envia resposta
ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>