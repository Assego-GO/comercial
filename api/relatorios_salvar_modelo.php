<?php
/**
 * API para salvar modelo de relatório
 * api/relatorios_salvar_modelo.php
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Se for OPTIONS (preflight), retorna OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Relatorios.php';

// Resposta padrão
$response = [
    'status' => 'error',
    'message' => 'Erro desconhecido',
    'modelo_id' => null,
    'debug' => []
];

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        $response['message'] = 'Usuário não autenticado';
        http_response_code(401);
        echo json_encode($response);
        exit;
    }

    // Verifica método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        $response['message'] = 'Método não permitido';
        http_response_code(405);
        echo json_encode($response);
        exit;
    }

    // Obtém dados do POST - tenta várias formas
    $dados = null;
    
    // Primeiro tenta obter do corpo da requisição (JSON)
    $input = file_get_contents('php://input');
    if ($input) {
        $dados = json_decode($input, true);
        $response['debug']['input_method'] = 'json_body';
        $response['debug']['raw_input'] = substr($input, 0, 200) . '...'; // Primeiros 200 chars para debug
    }
    
    // Se não conseguiu decodificar JSON, tenta $_POST
    if (!$dados && !empty($_POST)) {
        $dados = $_POST;
        $response['debug']['input_method'] = 'post';
    }

    // Debug - registra o que foi recebido
    error_log("relatorios_salvar_modelo.php - Dados recebidos: " . json_encode($dados));
    error_log("relatorios_salvar_modelo.php - Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'não definido'));

    // Validações
    if (empty($dados)) {
        $response['message'] = 'Dados não recebidos';
        $response['debug']['content_type'] = $_SERVER['CONTENT_TYPE'] ?? 'não definido';
        $response['debug']['request_method'] = $_SERVER['REQUEST_METHOD'];
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Campos obrigatórios
    $camposObrigatorios = ['nome', 'tipo', 'campos'];
    $camposFaltando = [];
    
    foreach ($camposObrigatorios as $campo) {
        if (empty($dados[$campo])) {
            $camposFaltando[] = $campo;
        }
    }
    
    if (!empty($camposFaltando)) {
        $response['message'] = "Campos obrigatórios não informados: " . implode(', ', $camposFaltando);
        $response['debug']['campos_recebidos'] = array_keys($dados);
        $response['debug']['campos_faltando'] = $camposFaltando;
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validação do tipo
    $tiposValidos = ['associados', 'financeiro', 'militar', 'servicos', 'documentos'];
    if (!in_array($dados['tipo'], $tiposValidos)) {
        $response['message'] = 'Tipo de relatório inválido: ' . $dados['tipo'];
        $response['debug']['tipo_recebido'] = $dados['tipo'];
        $response['debug']['tipos_validos'] = $tiposValidos;
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Validação dos campos
    if (!is_array($dados['campos']) || count($dados['campos']) === 0) {
        $response['message'] = 'Selecione ao menos um campo para o relatório';
        $response['debug']['campos'] = $dados['campos'];
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Verifica se é atualização (PUT) ou criação (POST)
    $modeloId = isset($dados['id']) ? intval($dados['id']) : null;
    $isUpdate = ($modeloId > 0 && $_SERVER['REQUEST_METHOD'] === 'PUT');

    // Prepara dados para salvar
    $dadosModelo = [
        'nome' => trim($dados['nome']),
        'descricao' => isset($dados['descricao']) ? trim($dados['descricao']) : null,
        'tipo' => $dados['tipo'],
        'campos' => $dados['campos'],
        'filtros' => isset($dados['filtros']) ? $dados['filtros'] : [],
        'ordenacao' => isset($dados['ordenacao']) ? $dados['ordenacao'] : null
    ];

    if (!$isUpdate) {
        $dadosModelo['criado_por'] = $_SESSION['funcionario_id'] ?? null;
    }

    // Remove filtros vazios
    if (isset($dadosModelo['filtros']) && is_array($dadosModelo['filtros'])) {
        $dadosModelo['filtros'] = array_filter($dadosModelo['filtros'], function($valor) {
            return !empty($valor) && $valor !== '';
        });
    }

    // Log para debug
    error_log("Salvando modelo: " . json_encode([
        'id' => $modeloId,
        'nome' => $dadosModelo['nome'],
        'tipo' => $dadosModelo['tipo'],
        'campos_count' => count($dadosModelo['campos']),
        'filtros_count' => count($dadosModelo['filtros']),
        'is_update' => $isUpdate
    ]));

    // Inicializa classe de relatórios
    $relatorios = new Relatorios();
    
    // Salva ou atualiza o modelo
    if ($isUpdate) {
        $sucesso = $relatorios->atualizarModelo($modeloId, $dadosModelo);
        if ($sucesso) {
            $response['status'] = 'success';
            $response['message'] = 'Modelo atualizado com sucesso';
            $response['modelo_id'] = $modeloId;
            http_response_code(200);
        } else {
            $response['message'] = 'Erro ao atualizar modelo';
            http_response_code(500);
        }
    } else {
        // Validação do nome (evita duplicação) apenas para novos modelos
        if (!validarNomeModelo($dadosModelo['nome'])) {
            $response['message'] = 'Já existe um modelo com este nome';
            $response['debug']['nome_duplicado'] = $dadosModelo['nome'];
            http_response_code(400);
            echo json_encode($response);
            exit;
        }
        
        $modeloId = $relatorios->criarModelo($dadosModelo);
        
        if ($modeloId) {
            $response['status'] = 'success';
            $response['message'] = 'Modelo salvo com sucesso';
            $response['modelo_id'] = $modeloId;
            
            // Log de sucesso
            error_log("Modelo criado com sucesso. ID: $modeloId");
            
            // Registra na auditoria
            registrarAuditoria('CREATE', 'Modelos_Relatorios', $modeloId, [
                'nome' => $dadosModelo['nome'],
                'tipo' => $dadosModelo['tipo']
            ]);
            
            http_response_code(201);
        } else {
            $response['message'] = 'Erro ao salvar modelo';
            http_response_code(500);
        }
    }

    // Remove informações de debug em produção
    if (!defined('DEBUG') || !DEBUG) {
        unset($response['debug']);
    }

} catch (Exception $e) {
    error_log("Erro em relatorios_salvar_modelo.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response['message'] = 'Erro ao salvar modelo: ' . $e->getMessage();
    
    // Adiciona debug apenas em desenvolvimento
    if (defined('DEBUG') && DEBUG) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    
    http_response_code(500);
}

// Retorna resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Valida se o nome do modelo já existe
 */
function validarNomeModelo($nome) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM Modelos_Relatorios 
            WHERE nome = ? AND ativo = 1
        ");
        $stmt->execute([trim($nome)]);
        $result = $stmt->fetch();
        
        return $result['total'] == 0;
        
    } catch (Exception $e) {
        error_log("Erro ao validar nome do modelo: " . $e->getMessage());
        return true; // Em caso de erro, permite continuar
    }
}

/**
 * Registra ação na auditoria
 */
function registrarAuditoria($acao, $tabela, $registroId, $dados) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO Auditoria (
                tabela, acao, registro_id, funcionario_id, 
                alteracoes, ip_origem, browser_info, data_hora
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $tabela,
            $acao,
            $registroId,
            $_SESSION['funcionario_id'] ?? null,
            json_encode($dados),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao registrar auditoria: " . $e->getMessage());
    }
}
?>