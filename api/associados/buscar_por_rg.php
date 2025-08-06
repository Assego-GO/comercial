<?php
/**
 * API para buscar associado por RG ou CPF
 * api/associados/buscar_por_rg.php
 */

// Configurações de erro - SEMPRE retornar JSON
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers JSON obrigatórios
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Função para log de debug
function debug_log($message) {
    error_log("[DEBUG BUSCAR_RG_CPF] " . $message);
}

// Função para retornar resposta JSON
function retornarResposta($status, $message, $data = null, $debug = null) {
    $response = [
        'status' => $status,
        'message' => $message,
        'data' => $data
    ];
    
    if ($debug && (isset($_GET['debug']) || isset($_POST['debug']))) {
        $response['debug'] = $debug;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para detectar se é CPF ou RG
function detectarTipoDocumento($documento) {
    // Remove caracteres não numéricos
    $documentoLimpo = preg_replace('/\D/', '', $documento);
    
    // Se tem 11 dígitos, provavelmente é CPF
    if (strlen($documentoLimpo) == 11) {
        return 'CPF';
    }
    
    // Caso contrário, considera como RG
    return 'RG';
}

// Função para formatar CPF para busca
function formatarCPFParaBusca($cpf) {
    $cpfLimpo = preg_replace('/\D/', '', $cpf);
    if (strlen($cpfLimpo) == 11) {
        return $cpfLimpo;
    }
    return $cpf;
}

// Capturar qualquer saída inesperada
ob_start();

try {
    debug_log("Iniciando busca por RG ou CPF");
    
    // Verifica se o documento foi informado (mantém compatibilidade com 'rg')
    $documento = $_GET['rg'] ?? $_GET['cpf'] ?? $_GET['documento'] ?? $_POST['rg'] ?? $_POST['cpf'] ?? $_POST['documento'] ?? null;
    
    if (!$documento) {
        retornarResposta('error', 'RG ou CPF não informado');
    }
    
    $documento = trim($documento);
    if (empty($documento)) {
        retornarResposta('error', 'RG ou CPF não pode estar vazio');
    }
    
    // Detecta o tipo de documento
    $tipoDocumento = detectarTipoDocumento($documento);
    debug_log("Documento recebido: $documento (Tipo detectado: $tipoDocumento)");
    
    // CORRIGIDO: Usa o mesmo padrão do seu projeto
    $configPath = '../../config/config.php';
    $databaseConfigPath = '../../config/database.php';
    $classPath = '../../classes/Database.php';
    
    debug_log("Tentando carregar arquivos de configuração...");
    
    // Verifica se os arquivos existem
    if (!file_exists($configPath)) {
        retornarResposta('error', 'Arquivo config.php não encontrado', null, [
            'path_tentado' => $configPath,
            'dir_atual' => __DIR__
        ]);
    }
    
    if (!file_exists($databaseConfigPath)) {
        retornarResposta('error', 'Arquivo database.php não encontrado', null, [
            'path_tentado' => $databaseConfigPath,
            'dir_atual' => __DIR__
        ]);
    }
    
    if (!file_exists($classPath)) {
        retornarResposta('error', 'Classe Database.php não encontrada', null, [
            'path_tentado' => $classPath,
            'dir_atual' => __DIR__
        ]);
    }
    
    // Carrega os arquivos na ordem correta (igual ao dashboard)
    debug_log("Carregando config.php...");
    require_once $configPath;
    
    debug_log("Carregando database.php...");
    require_once $databaseConfigPath;
    
    debug_log("Carregando Database.php...");
    require_once $classPath;
    
    debug_log("Arquivos carregados com sucesso");
    
    // Verifica se a classe existe
    if (!class_exists('Database')) {
        retornarResposta('error', 'Classe Database não foi carregada');
    }
    
    // Verifica se a constante do banco existe
    if (!defined('DB_NAME_CADASTRO')) {
        retornarResposta('error', 'Constante DB_NAME_CADASTRO não definida', null, [
            'constantes_definidas' => array_keys(get_defined_constants(true)['user'] ?? [])
        ]);
    }
    
    debug_log("Conectando ao banco usando Database::getInstance...");
    
    // CORRIGIDO: Usa o mesmo padrão do seu projeto
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    if (!$db) {
        retornarResposta('error', 'Falha na conexão com banco de dados');
    }
    
    debug_log("Conexão estabelecida, executando query...");
    
    // NOVA Query: Busca tanto por RG quanto por CPF
    if ($tipoDocumento === 'CPF') {
        // Busca por CPF (remove formatação)
        $cpfParaBusca = formatarCPFParaBusca($documento);
        
        $query = "
            SELECT 
                a.id as associado_id,
                a.nome,
                a.nasc as data_nascimento,
                a.sexo,
                a.rg,
                a.cpf,
                a.email,
                a.telefone,
                a.escolaridade,
                a.estadoCivil as estado_civil,
                a.situacao,
                a.pre_cadastro,
                
                m.corporacao,
                m.patente,
                m.categoria,
                m.lotacao,
                m.unidade,
                
                e.cep,
                e.endereco,
                e.bairro,
                e.cidade,
                e.numero,
                e.complemento,
                
                f.tipoAssociado as tipo_associado,
                f.situacaoFinanceira as situacao_financeira,
                
                c.dataFiliacao as data_filiacao,
                c.dataDesfiliacao as data_desfiliacao
                
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Endereco e ON a.id = e.associado_id  
            LEFT JOIN Financeiro f ON a.id = f.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE REPLACE(REPLACE(REPLACE(a.cpf, '.', ''), '-', ''), ' ', '') = :documento
            LIMIT 1
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':documento', $cpfParaBusca, PDO::PARAM_STR);
        
    } else {
        // Busca por RG
        $query = "
            SELECT 
                a.id as associado_id,
                a.nome,
                a.nasc as data_nascimento,
                a.sexo,
                a.rg,
                a.cpf,
                a.email,
                a.telefone,
                a.escolaridade,
                a.estadoCivil as estado_civil,
                a.situacao,
                a.pre_cadastro,
                
                m.corporacao,
                m.patente,
                m.categoria,
                m.lotacao,
                m.unidade,
                
                e.cep,
                e.endereco,
                e.bairro,
                e.cidade,
                e.numero,
                e.complemento,
                
                f.tipoAssociado as tipo_associado,
                f.situacaoFinanceira as situacao_financeira,
                
                c.dataFiliacao as data_filiacao,
                c.dataDesfiliacao as data_desfiliacao
                
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Endereco e ON a.id = e.associado_id  
            LEFT JOIN Financeiro f ON a.id = f.associado_id
            LEFT JOIN Contrato c ON a.id = c.associado_id
            WHERE TRIM(UPPER(a.rg)) = TRIM(UPPER(:documento))
            LIMIT 1
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':documento', $documento, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    debug_log("Query executada. Resultado: " . ($associado ? 'encontrado' : 'não encontrado'));
    
    if (!$associado) {
        retornarResposta('error', "Associado não encontrado com o $tipoDocumento: $documento");
    }
    
    // Formatar dados para retorno
    $dadosFormatados = [
        'dados_pessoais' => [
            'id' => $associado['associado_id'],
            'nome' => $associado['nome'] ?? '',
            'data_nascimento' => $associado['data_nascimento'] ?? '',
            'sexo' => $associado['sexo'] ?? '',
            'rg' => $associado['rg'] ?? '',
            'cpf' => $associado['cpf'] ?? '',
            'email' => $associado['email'] ?? '',
            'telefone' => $associado['telefone'] ?? '',
            'escolaridade' => $associado['escolaridade'] ?? '',
            'estado_civil' => $associado['estado_civil'] ?? '',
            'situacao' => $associado['situacao'] ?? ''
        ],
        'dados_militares' => [
            'corporacao' => $associado['corporacao'] ?? '',
            'patente' => $associado['patente'] ?? '',
            'categoria' => $associado['categoria'] ?? '',
            'lotacao' => $associado['lotacao'] ?? '',
            'unidade' => $associado['unidade'] ?? ''
        ],
        'endereco' => [
            'cep' => $associado['cep'] ?? '',
            'endereco' => $associado['endereco'] ?? '',
            'bairro' => $associado['bairro'] ?? '',
            'cidade' => $associado['cidade'] ?? '',
            'numero' => $associado['numero'] ?? '',
            'complemento' => $associado['complemento'] ?? ''
        ],
        'dados_financeiros' => [
            'tipo_associado' => $associado['tipo_associado'] ?? '',
            'situacao_financeira' => $associado['situacao_financeira'] ?? ''
        ],
        'contrato' => [
            'data_filiacao' => $associado['data_filiacao'] ?? '',
            'data_desfiliacao' => $associado['data_desfiliacao'] ?? ''
        ],
        'status_cadastro' => ($associado['pre_cadastro'] == 1) ? 'PRE_CADASTRO' : 'DEFINITIVO',
        'tipo_busca' => $tipoDocumento // Informa qual tipo de documento foi usado na busca
    ];
    
    debug_log("Dados formatados com sucesso");
    
    // Limpa qualquer saída capturada
    ob_clean();
    
    // Retorna sucesso
    retornarResposta('success', "Associado encontrado com sucesso pelo $tipoDocumento", $dadosFormatados);
    
} catch (PDOException $e) {
    debug_log("Erro PDO: " . $e->getMessage());
    ob_clean();
    retornarResposta('error', 'Erro de banco de dados', null, [
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    
} catch (Exception $e) {
    debug_log("Erro geral: " . $e->getMessage());
    ob_clean();
    retornarResposta('error', 'Erro interno do servidor', null, [
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
    
} catch (Throwable $e) {
    debug_log("Erro fatal: " . $e->getMessage());
    ob_clean();
    retornarResposta('error', 'Erro fatal no servidor', null, [
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}

// Se chegou aqui, algo deu muito errado
ob_clean();
retornarResposta('error', 'Erro desconhecido no servidor');
?>