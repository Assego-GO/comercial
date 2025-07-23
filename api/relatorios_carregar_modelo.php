<?php
/**
 * API para carregar modelo de relatório salvo
 * api/relatorios_carregar_modelo.php
 */

// Headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

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
    'modelo' => null
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

    // Validação de parâmetros
    $modeloId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($modeloId <= 0) {
        $response['message'] = 'ID do modelo inválido';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Inicializa classe de relatórios
    $relatorios = new Relatorios();
    
    // Busca o modelo
    $modelo = $relatorios->getModeloById($modeloId);
    
    if (!$modelo) {
        $response['message'] = 'Modelo não encontrado';
        http_response_code(404);
        echo json_encode($response);
        exit;
    }

    // Verifica permissões (opcional - todos podem ver modelos ativos)
    // Se quiser restringir apenas ao criador ou diretores:
    /*
    $usuarioLogado = $auth->getUser();
    if ($modelo['criado_por'] != $usuarioLogado['id'] && !$auth->isDiretor()) {
        $response['message'] = 'Sem permissão para acessar este modelo';
        http_response_code(403);
        echo json_encode($response);
        exit;
    }
    */

    // Busca campos disponíveis para o tipo
    $camposDisponiveis = $relatorios->getCamposDisponiveis($modelo['tipo']);
    
    // Se não há campos no banco, usa campos padrão
    if (empty($camposDisponiveis)) {
        $camposDisponiveis = getCamposPadrao($modelo['tipo']);
    }

    // Enriquece dados do modelo
    $modelo['campos_disponiveis'] = $camposDisponiveis;
    
    // Adiciona estatísticas de uso
    $modelo['estatisticas'] = getEstatisticasModelo($modeloId);
    
    // Adiciona informações extras
    $modelo['pode_editar'] = podeEditarModelo($modelo, $auth);
    $modelo['pode_excluir'] = podeExcluirModelo($modelo, $auth);
    
    // Formata datas
    if (isset($modelo['data_criacao'])) {
        $modelo['data_criacao_formatada'] = formatarData($modelo['data_criacao']);
    }
    if (isset($modelo['data_modificacao'])) {
        $modelo['data_modificacao_formatada'] = formatarData($modelo['data_modificacao']);
    }

    // Registra uso do modelo (opcional)
    registrarUsoModelo($modeloId);

    $response['status'] = 'success';
    $response['message'] = 'Modelo carregado com sucesso';
    $response['modelo'] = $modelo;
    
    http_response_code(200);

} catch (Exception $e) {
    error_log("Erro em relatorios_carregar_modelo.php: " . $e->getMessage());
    $response['message'] = 'Erro ao carregar modelo: ' . $e->getMessage();
    http_response_code(500);
}

// Retorna resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Retorna campos padrão por tipo (mesma função da API de campos)
 */
function getCamposPadrao($tipo) {
    $campos = [];
    
    switch($tipo) {
        case 'associados':
            $campos = [
                'Dados Pessoais' => [
                    ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome Completo', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'rg', 'nome_exibicao' => 'RG', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'nasc', 'nome_exibicao' => 'Data de Nascimento', 'tipo_dado' => 'data'],
                    ['nome_campo' => 'sexo', 'nome_exibicao' => 'Sexo', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'email', 'nome_exibicao' => 'E-mail', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'telefone', 'nome_exibicao' => 'Telefone', 'tipo_dado' => 'texto']
                ],
                'Informações Militares' => [
                    ['nome_campo' => 'corporacao', 'nome_exibicao' => 'Corporação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'patente', 'nome_exibicao' => 'Patente', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'categoria', 'nome_exibicao' => 'Categoria', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'lotacao', 'nome_exibicao' => 'Lotação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'unidade', 'nome_exibicao' => 'Unidade', 'tipo_dado' => 'texto']
                ],
                'Situação' => [
                    ['nome_campo' => 'situacao', 'nome_exibicao' => 'Situação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'dataFiliacao', 'nome_exibicao' => 'Data de Filiação', 'tipo_dado' => 'data'],
                    ['nome_campo' => 'dataDesfiliacao', 'nome_exibicao' => 'Data de Desfiliação', 'tipo_dado' => 'data']
                ],
                'Endereço' => [
                    ['nome_campo' => 'cep', 'nome_exibicao' => 'CEP', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'endereco', 'nome_exibicao' => 'Endereço', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'numero', 'nome_exibicao' => 'Número', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'bairro', 'nome_exibicao' => 'Bairro', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'cidade', 'nome_exibicao' => 'Cidade', 'tipo_dado' => 'texto']
                ]
            ];
            break;
            
        case 'financeiro':
            $campos = [
                'Dados Financeiros' => [
                    ['nome_campo' => 'tipoAssociado', 'nome_exibicao' => 'Tipo de Associado', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'situacaoFinanceira', 'nome_exibicao' => 'Situação Financeira', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'vinculoServidor', 'nome_exibicao' => 'Vínculo Servidor', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'localDebito', 'nome_exibicao' => 'Local de Débito', 'tipo_dado' => 'texto']
                ],
                'Dados Bancários' => [
                    ['nome_campo' => 'agencia', 'nome_exibicao' => 'Agência', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'operacao', 'nome_exibicao' => 'Operação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'contaCorrente', 'nome_exibicao' => 'Conta Corrente', 'tipo_dado' => 'texto']
                ]
            ];
            break;
            
        case 'militar':
            $campos = [
                'Informações Militares' => [
                    ['nome_campo' => 'corporacao', 'nome_exibicao' => 'Corporação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'patente', 'nome_exibicao' => 'Patente', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'categoria', 'nome_exibicao' => 'Categoria', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'lotacao', 'nome_exibicao' => 'Lotação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'unidade', 'nome_exibicao' => 'Unidade', 'tipo_dado' => 'texto']
                ]
            ];
            break;
            
        case 'servicos':
            $campos = [
                'Serviços' => [
                    ['nome_campo' => 'servico_nome', 'nome_exibicao' => 'Nome do Serviço', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'valor_aplicado', 'nome_exibicao' => 'Valor Aplicado', 'tipo_dado' => 'moeda'],
                    ['nome_campo' => 'percentual_aplicado', 'nome_exibicao' => 'Percentual Aplicado', 'tipo_dado' => 'percentual'],
                    ['nome_campo' => 'data_adesao', 'nome_exibicao' => 'Data de Adesão', 'tipo_dado' => 'data'],
                    ['nome_campo' => 'ativo', 'nome_exibicao' => 'Status do Serviço', 'tipo_dado' => 'boolean']
                ]
            ];
            break;
            
        case 'documentos':
            $campos = [
                'Documentos' => [
                    ['nome_campo' => 'tipo_documento', 'nome_exibicao' => 'Tipo de Documento', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'nome_arquivo', 'nome_exibicao' => 'Nome do Arquivo', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'data_upload', 'nome_exibicao' => 'Data de Upload', 'tipo_dado' => 'data'],
                    ['nome_campo' => 'verificado', 'nome_exibicao' => 'Status de Verificação', 'tipo_dado' => 'boolean']
                ]
            ];
            break;
    }
    
    return $campos;
}

/**
 * Busca estatísticas de uso do modelo
 */
function getEstatisticasModelo($modeloId) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_execucoes,
                MAX(data_geracao) as ultima_execucao,
                AVG(contagem_registros) as media_registros,
                COUNT(DISTINCT gerado_por) as usuarios_unicos
            FROM Historico_Relatorios
            WHERE modelo_id = ?
        ");
        $stmt->execute([$modeloId]);
        $stats = $stmt->fetch();
        
        // Formata dados
        if ($stats['ultima_execucao']) {
            $stats['ultima_execucao_formatada'] = formatarData($stats['ultima_execucao']);
        }
        
        $stats['media_registros'] = round($stats['media_registros'] ?? 0);
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas do modelo: " . $e->getMessage());
        return [
            'total_execucoes' => 0,
            'ultima_execucao' => null,
            'media_registros' => 0,
            'usuarios_unicos' => 0
        ];
    }
}

/**
 * Verifica se o usuário pode editar o modelo
 */
function podeEditarModelo($modelo, $auth) {
    $usuarioLogado = $auth->getUser();
    
    // Diretor pode editar qualquer modelo
    if ($auth->isDiretor()) {
        return true;
    }
    
    // Criador pode editar seu próprio modelo
    if ($modelo['criado_por'] == $usuarioLogado['id']) {
        return true;
    }
    
    return false;
}

/**
 * Verifica se o usuário pode excluir o modelo
 */
function podeExcluirModelo($modelo, $auth) {
    // Mesmas regras de edição por enquanto
    return podeEditarModelo($modelo, $auth);
}

/**
 * Registra uso do modelo (analytics)
 */
function registrarUsoModelo($modeloId) {
    try {
        // Opcional: registrar visualização do modelo para analytics
        // Isso pode ser útil para identificar modelos mais populares
        
        /*
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $stmt = $db->prepare("
            INSERT INTO Modelo_Visualizacoes (modelo_id, funcionario_id, data_hora)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$modeloId, $_SESSION['funcionario_id'] ?? null]);
        */
        
    } catch (Exception $e) {
        // Não bloqueia se falhar
        error_log("Erro ao registrar uso do modelo: " . $e->getMessage());
    }
}

/**
 * Formata data para exibição
 */
function formatarData($data) {
    if (!$data) return '-';
    
    try {
        $dt = new DateTime($data);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $data;
    }
}

/**
 * Valida acesso ao modelo (adicional)
 */
function validarAcessoModelo($modelo, $auth) {
    // Se o modelo tem restrições específicas
    if (isset($modelo['restricoes']) && !empty($modelo['restricoes'])) {
        $restricoes = is_string($modelo['restricoes']) 
            ? json_decode($modelo['restricoes'], true) 
            : $modelo['restricoes'];
        
        // Exemplo: restrição por departamento
        if (isset($restricoes['departamentos'])) {
            $usuarioLogado = $auth->getUser();
            if (!in_array($usuarioLogado['departamento_id'], $restricoes['departamentos'])) {
                return false;
            }
        }
    }
    
    return true;
}
?>