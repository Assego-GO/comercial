<?php
/**
 * API para buscar campos disponíveis para relatórios
 * api/relatorios_campos.php
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
    'campos' => []
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
    $tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : null;

    // Tipos válidos
    $tiposValidos = ['associados', 'financeiro', 'militar', 'servicos', 'documentos'];
    
    if (!in_array($tipo, $tiposValidos)) {
        $response['message'] = 'Tipo de relatório inválido';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // Inicializa classe de relatórios
    $relatorios = new Relatorios();
    
    // Busca campos na base de dados
    $campos = $relatorios->getCamposDisponiveis($tipo, $categoria);
    
    // Se não encontrou campos no banco, usa campos padrão
    if (empty($campos)) {
        $campos = getCamposPadrao($tipo);
    }
    
    $response['status'] = 'success';
    $response['message'] = 'Campos carregados com sucesso';
    $response['campos'] = $campos;
    $response['tipo'] = $tipo;
    
    http_response_code(200);

} catch (Exception $e) {
    error_log("Erro em relatorios_campos.php: " . $e->getMessage());
    $response['message'] = 'Erro ao buscar campos: ' . $e->getMessage();
    http_response_code(500);
}

// Retorna resposta
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Retorna campos padrão por tipo (fallback caso não existam no banco)
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
                    ['nome_campo' => 'telefone', 'nome_exibicao' => 'Telefone', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'escolaridade', 'nome_exibicao' => 'Escolaridade', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'estadoCivil', 'nome_exibicao' => 'Estado Civil', 'tipo_dado' => 'texto']
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
                    ['nome_campo' => 'dataDesfiliacao', 'nome_exibicao' => 'Data de Desfiliação', 'tipo_dado' => 'data'],
                    ['nome_campo' => 'indicacao', 'nome_exibicao' => 'Indicado por', 'tipo_dado' => 'texto']
                ],
                'Endereço' => [
                    ['nome_campo' => 'cep', 'nome_exibicao' => 'CEP', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'endereco', 'nome_exibicao' => 'Endereço', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'numero', 'nome_exibicao' => 'Número', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'bairro', 'nome_exibicao' => 'Bairro', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'cidade', 'nome_exibicao' => 'Cidade', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'complemento', 'nome_exibicao' => 'Complemento', 'tipo_dado' => 'texto']
                ]
            ];
            break;
            
        case 'financeiro':
            $campos = [
                'Dados Financeiros' => [
                    ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
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
                'Dados Pessoais' => [
                    ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'rg', 'nome_exibicao' => 'RG', 'tipo_dado' => 'texto']
                ],
                'Informações Militares' => [
                    ['nome_campo' => 'corporacao', 'nome_exibicao' => 'Corporação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'patente', 'nome_exibicao' => 'Patente', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'categoria', 'nome_exibicao' => 'Categoria', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'lotacao', 'nome_exibicao' => 'Lotação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'unidade', 'nome_exibicao' => 'Unidade', 'tipo_dado' => 'texto']
                ],
                'Situação' => [
                    ['nome_campo' => 'situacao', 'nome_exibicao' => 'Situação do Associado', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'dataFiliacao', 'nome_exibicao' => 'Data de Filiação', 'tipo_dado' => 'data']
                ]
            ];
            break;
            
        case 'servicos':
            $campos = [
                'Dados do Associado' => [
                    ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'corporacao', 'nome_exibicao' => 'Corporação', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'patente', 'nome_exibicao' => 'Patente', 'tipo_dado' => 'texto']
                ],
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
                'Dados do Associado' => [
                    ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto']
                ],
                'Documentos' => [
                    ['nome_campo' => 'tipo_documento', 'nome_exibicao' => 'Tipo de Documento', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'nome_arquivo', 'nome_exibicao' => 'Nome do Arquivo', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'data_upload', 'nome_exibicao' => 'Data de Upload', 'tipo_dado' => 'data'],
                    ['nome_campo' => 'verificado', 'nome_exibicao' => 'Status de Verificação', 'tipo_dado' => 'boolean'],
                    ['nome_campo' => 'funcionario_nome', 'nome_exibicao' => 'Verificado por', 'tipo_dado' => 'texto'],
                    ['nome_campo' => 'observacao', 'nome_exibicao' => 'Observações', 'tipo_dado' => 'texto']
                ],
                'Lote' => [
                    ['nome_campo' => 'lote_id', 'nome_exibicao' => 'ID do Lote', 'tipo_dado' => 'numero'],
                    ['nome_campo' => 'lote_status', 'nome_exibicao' => 'Status do Lote', 'tipo_dado' => 'texto']
                ]
            ];
            break;
            
        default:
            $campos = [];
    }
    
    return $campos;
}

// Função alternativa para buscar campos diretamente do banco (opcional)
function getCamposDoBanco($tipo) {
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Primeiro, tenta buscar da tabela Campos_Relatorios se existir
        $checkTable = $db->query("SHOW TABLES LIKE 'Campos_Relatorios'");
        if ($checkTable->rowCount() > 0) {
            $relatorios = new Relatorios();
            return $relatorios->getCamposDisponiveis($tipo);
        }
        
        // Se não existe a tabela, retorna array vazio
        return [];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar campos do banco: " . $e->getMessage());
        return [];
    }
}
?>