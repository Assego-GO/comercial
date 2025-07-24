<?php
/**
 * API para criar novo associado - VERSÃO FINAL COM CLASSE
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

    // Sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Simula login para debug (REMOVER EM PRODUÇÃO)
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Debug User';
        $_SESSION['user_email'] = 'debug@test.com';
        $_SESSION['funcionario_id'] = 1; // Para auditoria
    }

    error_log("=== CRIAR ASSOCIADO ===");
    error_log("Usuário: " . ($_SESSION['user_name'] ?? 'N/A'));
    error_log("POST fields: " . count($_POST));

    // Validação
    $campos_obrigatorios = ['nome', 'cpf', 'rg', 'telefone', 'situacao', 'dataFiliacao'];
    foreach ($campos_obrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            throw new Exception("Campo '$campo' é obrigatório");
        }
    }

    // Prepara dados para a classe Associados
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

    // Processa foto
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
            error_log("Foto salva: " . $dados['foto']);
        }
    }

    error_log("Dados preparados - Total campos: " . count($dados));
    error_log("Dependentes: " . count($dados['dependentes']));

    // CRIA O ASSOCIADO USANDO A CLASSE
    try {
        $associados = new Associados();
        $associadoId = $associados->criar($dados);
        
        if (!$associadoId) {
            throw new Exception('Classe Associados retornou false');
        }
        
        error_log("✓ Associado criado com ID: $associadoId");
        
    } catch (Exception $e) {
        error_log("✗ Erro na classe Associados: " . $e->getMessage());
        throw new Exception("Erro ao criar associado: " . $e->getMessage());
    }

    // AGORA CRIA OS SERVIÇOS (separadamente)
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    $servicos_criados = [];
    $valor_total_mensal = 0;
    
    try {
        // Serviço Social
        if (!empty($_POST['valorSocial']) && floatval($_POST['valorSocial']) > 0) {
            $stmt = $db->prepare("
                INSERT INTO Servicos_Associado (
                    associado_id, servico_id, ativo, data_adesao, 
                    valor_aplicado, percentual_aplicado, observacao
                ) VALUES (?, 1, 1, NOW(), ?, ?, ?)
            ");
            
            $valorSocial = floatval($_POST['valorSocial']);
            $percentualSocial = floatval($_POST['percentualAplicadoSocial'] ?? 100);
            
            $resultado = $stmt->execute([
                $associadoId,
                $valorSocial,
                $percentualSocial,
                'Cadastro inicial - ' . ($_POST['tipoAssociadoServico'] ?? 'Padrão')
            ]);
            
            if ($resultado) {
                $servicos_criados[] = 'Social';
                $valor_total_mensal += $valorSocial;
                error_log("✓ Serviço Social criado - R$ " . number_format($valorSocial, 2, ',', '.'));
            }
        }

        // Serviço Jurídico
        if (!empty($_POST['servicoJuridico']) && !empty($_POST['valorJuridico']) && floatval($_POST['valorJuridico']) > 0) {
            $stmt = $db->prepare("
                INSERT INTO Servicos_Associado (
                    associado_id, servico_id, ativo, data_adesao, 
                    valor_aplicado, percentual_aplicado, observacao
                ) VALUES (?, 2, 1, NOW(), ?, ?, ?)
            ");
            
            $valorJuridico = floatval($_POST['valorJuridico']);
            $percentualJuridico = floatval($_POST['percentualAplicadoJuridico'] ?? 100);
            
            $resultado = $stmt->execute([
                $associadoId,
                $valorJuridico,
                $percentualJuridico,
                'Cadastro inicial - ' . ($_POST['tipoAssociadoServico'] ?? 'Padrão')
            ]);
            
            if ($resultado) {
                $servicos_criados[] = 'Jurídico';
                $valor_total_mensal += $valorJuridico;
                error_log("✓ Serviço Jurídico criado - R$ " . number_format($valorJuridico, 2, ',', '.'));
            }
        }
        
        error_log("✓ Total de serviços criados: " . count($servicos_criados));
        error_log("✓ Valor total mensal: R$ " . number_format($valor_total_mensal, 2, ',', '.'));
        
    } catch (Exception $e) {
        error_log("⚠ Erro ao criar serviços (não crítico): " . $e->getMessage());
        // Não falha o processo todo por causa dos serviços
    }

    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Associado cadastrado com sucesso!',
        'data' => [
            'id' => $associadoId,
            'nome' => $dados['nome'],
            'cpf' => $dados['cpf'],
            'servicos_criados' => $servicos_criados,
            'total_servicos' => count($servicos_criados),
            'valor_total_mensal' => $valor_total_mensal,
            'dependentes' => count($dados['dependentes']),
            'tem_foto' => !empty($dados['foto'])
        ]
    ];

    error_log("✓ SUCESSO - Associado ID $associadoId criado completamente");

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
            'post_count' => count($_POST),
            'session_active' => session_status() === PHP_SESSION_ACTIVE
        ]
    ];
    
    http_response_code(400);
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
?>