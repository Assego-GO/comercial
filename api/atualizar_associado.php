<?php
/**
 * API para atualizar associado existente
 * api/atualizar_associado.php
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

// Configuração de erro reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Resposta padrão
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
    require_once '../classes/Auditoria.php';

    // Inicia sessão se não estiver iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Pega dados do usuário logado
    $usuarioLogado = $auth->getUser();
    
    // Log da requisição
    error_log("=== ATUALIZAR ASSOCIADO ===");
    error_log("ID do Associado: $associadoId");
    error_log("Usuário: " . $usuarioLogado['nome'] . " (ID: " . $usuarioLogado['id'] . ")");
    error_log("IP: " . $_SERVER['REMOTE_ADDR']);
    error_log("Dados recebidos: " . print_r($_POST, true));

    // Cria instância da classe Associados
    $associados = new Associados();
    
    // Busca dados atuais do associado
    $associadoAtual = $associados->getById($associadoId);
    if (!$associadoAtual) {
        throw new Exception('Associado não encontrado');
    }

    // Validação dos dados obrigatórios
    $camposObrigatorios = ['nome', 'cpf', 'rg', 'telefone', 'situacao'];
    $errosValidacao = [];

    foreach ($camposObrigatorios as $campo) {
        if (empty($_POST[$campo])) {
            $errosValidacao[] = "Campo '$campo' é obrigatório";
        }
    }

    if (!empty($errosValidacao)) {
        throw new Exception("Erro de validação: " . implode(", ", $errosValidacao));
    }

    // Prepara dados para atualização
    $dados = [
        // Dados pessoais
        'nome' => trim($_POST['nome']),
        'nasc' => $_POST['nasc'] ?: null,
        'sexo' => $_POST['sexo'] ?: null,
        'rg' => trim($_POST['rg']),
        'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf']), // Remove formatação
        'email' => trim($_POST['email']) ?: null,
        'situacao' => $_POST['situacao'],
        'escolaridade' => $_POST['escolaridade'] ?: null,
        'estadoCivil' => $_POST['estadoCivil'] ?: null,
        'telefone' => preg_replace('/[^0-9]/', '', $_POST['telefone']), // Remove formatação
        'indicacao' => trim($_POST['indicacao']) ?: null,
        
        // Data de filiação
        'dataFiliacao' => $_POST['dataFiliacao'] ?: $associadoAtual['data_filiacao'],
        'dataDesfiliacao' => $_POST['dataDesfiliacao'] ?: null,
        
        // Dados militares
        'corporacao' => $_POST['corporacao'] ?: null,
        'patente' => $_POST['patente'] ?: null,
        'categoria' => $_POST['categoria'] ?: null,
        'lotacao' => trim($_POST['lotacao']) ?: null,
        'unidade' => trim($_POST['unidade']) ?: null,
        
        // Endereço
        'cep' => preg_replace('/[^0-9]/', '', $_POST['cep']) ?: null,
        'endereco' => trim($_POST['endereco']) ?: null,
        'numero' => trim($_POST['numero']) ?: null,
        'complemento' => trim($_POST['complemento']) ?: null,
        'bairro' => trim($_POST['bairro']) ?: null,
        'cidade' => trim($_POST['cidade']) ?: null,
        
        // Dados financeiros
        'tipoAssociado' => $_POST['tipoAssociado'] ?: null,
        'situacaoFinanceira' => $_POST['situacaoFinanceira'] ?: null,
        'vinculoServidor' => $_POST['vinculoServidor'] ?: null,
        'localDebito' => $_POST['localDebito'] ?: null,
        'agencia' => trim($_POST['agencia']) ?: null,
        'operacao' => trim($_POST['operacao']) ?: null,
        'contaCorrente' => trim($_POST['contaCorrente']) ?: null
    ];

    // Processa foto se houver nova
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = processarUploadFoto($_FILES['foto'], $dados['cpf']);
        if ($uploadResult['success']) {
            // Remove foto antiga se existir
            if (!empty($associadoAtual['foto']) && file_exists('../' . $associadoAtual['foto'])) {
                unlink('../' . $associadoAtual['foto']);
            }
            $dados['foto'] = $uploadResult['path'];
        } else {
            error_log("Erro no upload da foto: " . $uploadResult['error']);
            // Não lança exceção, apenas continua sem atualizar a foto
        }
    }

    // Processa dependentes
    $dados['dependentes'] = [];
    if (isset($_POST['dependentes']) && is_array($_POST['dependentes'])) {
        foreach ($_POST['dependentes'] as $dep) {
            if (!empty($dep['nome'])) {
                $dados['dependentes'][] = [
                    'nome' => trim($dep['nome']),
                    'data_nascimento' => $dep['data_nascimento'] ?: null,
                    'parentesco' => $dep['parentesco'] ?: null,
                    'sexo' => $dep['sexo'] ?: null
                ];
            }
        }
    }

    // Prepara dados das mudanças para auditoria
    $alteracoes = [];
    foreach ($dados as $campo => $valorNovo) {
        $valorAtual = $associadoAtual[$campo] ?? null;
        
        // Compara valores (considerando null e string vazia como iguais)
        if ($valorAtual != $valorNovo && !($valorAtual === null && $valorNovo === '')) {
            $alteracoes[] = [
                'campo' => $campo,
                'valor_anterior' => $valorAtual,
                'valor_novo' => $valorNovo
            ];
        }
    }

    // Tenta atualizar o associado
    $resultado = $associados->atualizar($associadoId, $dados);
    
    if (!$resultado) {
        throw new Exception('Falha ao atualizar associado no banco de dados');
    }

    // Registra na auditoria se houve alterações
    if (!empty($alteracoes)) {
        $auditoria = new Auditoria();
        $auditoria->registrar([
            'tabela' => 'Associados',
            'acao' => 'UPDATE',
            'registro_id' => $associadoId,
            'associado_id' => $associadoId,
            'funcionario_id' => $usuarioLogado['id'],
            'alteracoes' => $alteracoes,
            'detalhes' => [
                'total_alteracoes' => count($alteracoes),
                'alterado_por' => $usuarioLogado['nome']
            ]
        ]);
    }

    // Busca dados atualizados do associado
    $associadoAtualizado = $associados->getById($associadoId);
    
    // Log de sucesso
    error_log("Associado atualizado com sucesso - ID: $associadoId");
    error_log("Total de alterações: " . count($alteracoes));
    
    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'message' => 'Associado atualizado com sucesso!',
        'data' => [
            'id' => $associadoId,
            'nome' => $associadoAtualizado['nome'],
            'cpf' => $associadoAtualizado['cpf'],
            'alteracoes' => count($alteracoes)
        ]
    ];

} catch (Exception $e) {
    error_log("Erro ao atualizar associado: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    http_response_code(400);
}

// Retorna resposta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Função auxiliar para processar upload de foto
 */
function processarUploadFoto($arquivo, $cpf) {
    $resultado = [
        'success' => false,
        'path' => null,
        'error' => null
    ];
    
    try {
        // Validações
        $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
        $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if ($arquivo['size'] > $tamanhoMaximo) {
            throw new Exception('Arquivo muito grande. Tamanho máximo: 5MB');
        }
        
        if (!in_array($arquivo['type'], $tiposPermitidos)) {
            throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG ou GIF');
        }
        
        // Verifica se é realmente uma imagem
        $imageInfo = getimagesize($arquivo['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Arquivo não é uma imagem válida');
        }
        
        // Define diretório de upload
        $uploadDir = '../uploads/fotos/';
        
        // Cria diretório se não existir
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Não foi possível criar diretório de upload');
            }
        }
        
        // Gera nome único para o arquivo
        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $nomeArquivo = 'foto_' . preg_replace('/[^0-9]/', '', $cpf) . '_' . time() . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        
        // Move o arquivo
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new Exception('Erro ao salvar arquivo');
        }
        
        // Otimiza a imagem
        otimizarImagem($caminhoCompleto, $arquivo['type']);
        
        // Caminho relativo para salvar no banco
        $resultado['success'] = true;
        $resultado['path'] = 'uploads/fotos/' . $nomeArquivo;
        
    } catch (Exception $e) {
        $resultado['error'] = $e->getMessage();
    }
    
    return $resultado;
}

/**
 * Função para otimizar imagem
 */
function otimizarImagem($caminho, $tipo) {
    try {
        // Carrega a imagem
        switch ($tipo) {
            case 'image/jpeg':
            case 'image/jpg':
                $imagem = imagecreatefromjpeg($caminho);
                break;
            case 'image/png':
                $imagem = imagecreatefrompng($caminho);
                break;
            case 'image/gif':
                $imagem = imagecreatefromgif($caminho);
                break;
            default:
                return;
        }
        
        if (!$imagem) {
            return;
        }
        
        // Obtém dimensões
        list($largura, $altura) = getimagesize($caminho);
        
        // Define tamanho máximo
        $larguraMax = 800;
        $alturaMax = 800;
        
        // Calcula novas dimensões mantendo proporção
        if ($largura > $larguraMax || $altura > $alturaMax) {
            $ratio = min($larguraMax / $largura, $alturaMax / $altura);
            $novaLargura = intval($largura * $ratio);
            $novaAltura = intval($altura * $ratio);
            
            // Cria nova imagem redimensionada
            $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
            
            // Preserva transparência para PNG
            if ($tipo === 'image/png') {
                imagealphablending($novaImagem, false);
                imagesavealpha($novaImagem, true);
                $transparent = imagecolorallocatealpha($novaImagem, 255, 255, 255, 127);
                imagefilledrectangle($novaImagem, 0, 0, $novaLargura, $novaAltura, $transparent);
            }
            
            // Redimensiona
            imagecopyresampled(
                $novaImagem, $imagem,
                0, 0, 0, 0,
                $novaLargura, $novaAltura,
                $largura, $altura
            );
            
            // Salva a imagem otimizada
            switch ($tipo) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($novaImagem, $caminho, 85); // 85% de qualidade
                    break;
                case 'image/png':
                    imagepng($novaImagem, $caminho, 8); // Compressão nível 8
                    break;
                case 'image/gif':
                    imagegif($novaImagem, $caminho);
                    break;
            }
            
            imagedestroy($novaImagem);
        } else {
            // Se já está no tamanho adequado, apenas otimiza a qualidade
            switch ($tipo) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($imagem, $caminho, 85);
                    break;
            }
        }
        
        imagedestroy($imagem);
        
    } catch (Exception $e) {
        error_log("Erro ao otimizar imagem: " . $e->getMessage());
    }
}

/**
 * Valida mudança de CPF
 */
function validarMudancaCPF($cpfNovo, $cpfAtual, $associadoId) {
    // Remove formatação
    $cpfNovo = preg_replace('/[^0-9]/', '', $cpfNovo);
    $cpfAtual = preg_replace('/[^0-9]/', '', $cpfAtual);
    
    // Se não mudou, está ok
    if ($cpfNovo === $cpfAtual) {
        return true;
    }
    
    // Se mudou, verifica se o novo CPF já existe para outro associado
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $stmt = $db->prepare("SELECT id FROM Associados WHERE cpf = ? AND id != ?");
        $stmt->execute([$cpfNovo, $associadoId]);
        
        if ($stmt->fetch()) {
            throw new Exception("CPF já cadastrado para outro associado");
        }
        
        return true;
    } catch (Exception $e) {
        throw $e;
    }
}

// Função de debug (desativar em produção)
function debugLog($message, $data = null) {
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log("[DEBUG] $message");
        if ($data !== null) {
            error_log("[DEBUG DATA] " . print_r($data, true));
        }
    }
}
?>