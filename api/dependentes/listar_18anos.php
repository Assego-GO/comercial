<?php
/**
 * API para listar dependentes que completaram ou estão prestes a completar 18 anos
 * api/dependentes/listar_18anos.php
 * VERSÃO CORRIGIDA - Problema de parâmetros duplicados resolvido
 */

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Buffer de saída para evitar erros de header
ob_start();

// Inclui arquivos necessários
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

// Função para resposta JSON limpa
function jsonResponse($status, $message, $data = null)
{
    // Limpa qualquer saída anterior
    while (ob_get_level()) {
        ob_end_clean();
    }

    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Verifica se é método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
    jsonResponse('error', 'Método não permitido. Use GET.');
}

// Se for OPTIONS (preflight CORS), retorna sucesso
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Inicia autenticação
    $auth = new Auth();

    // Verifica se está logado
    if (!$auth->isLoggedIn()) {
        jsonResponse('error', 'Usuário não autenticado.');
    }

    // Pega dados do usuário logado
    $usuarioLogado = $auth->getUser();

    // Verificar permissões usando o sistema de permissões
    $permissoes = Permissoes::getInstance();

    // Verifica se tem permissão para gerenciar dependentes
    if (!$permissoes->hasPermission('COMERCIAL_DEPENDENTES', 'VIEW')) {
        jsonResponse('error', 'Você não tem permissão para acessar esta funcionalidade.');
    }

    // Obter parâmetros de filtro
    $situacao = $_GET['situacao'] ?? 'todos';
    $busca = $_GET['busca'] ?? '';

    // Limpar parâmetros
    $situacao = trim($situacao);
    $busca = trim($busca);

    // Log para debug
    error_log("=== API DEPENDENTES 18 ANOS ===");
    error_log("Usuário: " . $usuarioLogado['nome']);
    error_log("Situação filtro: " . $situacao);
    error_log("Busca: " . $busca);

    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Monta a query base - SIMPLIFICADA
    $sql = "
        SELECT 
            d.id as dependente_id,
            d.nome as nome_dependente,
            d.data_nascimento,
            d.parentesco,
            d.sexo,
            a.id as associado_id,
            a.nome as nome_responsavel,
            a.rg as rg_responsavel,
            a.cpf as cpf_responsavel,
            a.telefone as telefone_responsavel,
            a.email as email_responsavel,
            a.situacao as situacao_associado,
            TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) as idade_atual,
            DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR) as data_18_anos,
            DATEDIFF(DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR), CURDATE()) as dias_para_18
        FROM Dependentes d
        LEFT JOIN Associados a ON d.associado_id = a.id
        WHERE 1=1
    ";

    // Array para armazenar condições WHERE
    $whereConditions = [];
    $params = [];

    // Filtro de parentesco (apenas filhos)
    $whereConditions[] = "((LOWER(d.parentesco) LIKE '%filho%') OR (LOWER(d.parentesco) LIKE '%filha%'))";

    // Filtro de situação
    switch ($situacao) {
        case 'ja_completaram':
            $whereConditions[] = "TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) >= 18";
            break;

        case 'este_mes':
            $whereConditions[] = "YEAR(DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR)) = YEAR(CURDATE())";
            $whereConditions[] = "MONTH(DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR)) = MONTH(CURDATE())";
            $whereConditions[] = "TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) < 18";
            break;

        case 'proximos_3_meses':
            $whereConditions[] = "DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)";
            $whereConditions[] = "TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) < 18";
            break;

        case 'proximos_6_meses':
            $whereConditions[] = "DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 MONTH)";
            $whereConditions[] = "TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) < 18";
            break;

        case 'todos':
        default:
            // Sem filtro adicional de idade
            break;
    }

    // Filtro de busca - CORRIGIDO: usando parâmetros diferentes para cada ocorrência
    if (!empty($busca)) {
        $whereConditions[] = "(d.nome LIKE :busca1 OR a.nome LIKE :busca2 OR a.rg LIKE :busca3 OR a.cpf LIKE :busca4)";
        $buscaParam = '%' . $busca . '%';
        $params[':busca1'] = $buscaParam;
        $params[':busca2'] = $buscaParam;
        $params[':busca3'] = $buscaParam;
        $params[':busca4'] = $buscaParam;
    }

    // Adiciona condições WHERE à query
    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    // Ordena por prioridade (idade decrescente)
    $sql .= " ORDER BY idade_atual DESC, d.nome ASC";

    // Log da query para debug
    error_log("Query SQL: " . $sql);
    error_log("Parâmetros: " . json_encode($params));

    try {
        // Prepara e executa a query
        $stmt = $db->prepare($sql);

        // Bind dos parâmetros
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->execute();

        // Busca os resultados
        $dependentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erro SQL: " . $e->getMessage());
        error_log("Query: " . $sql);
        error_log("Params: " . json_encode($params));
        throw $e;
    }

    // Processa os dados
    $dependentesProcessados = [];

    foreach ($dependentes as $dep) {
        // Valida data de nascimento
        if (empty($dep['data_nascimento']) || $dep['data_nascimento'] == '0000-00-00') {
            continue; // Pula registros sem data válida
        }

        $idade = intval($dep['idade_atual']);
        $diasPara18 = intval($dep['dias_para_18']);

        // Determina o status e prioridade
        $status = '';
        $prioridade = 'normal';

        if ($idade >= 18) {
            $mesesDesde18 = ($idade - 18) * 12;
            if ($mesesDesde18 > 6) {
                $status = 'Já completou 18 anos há mais de 6 meses';
                $prioridade = 'critica';
            } else {
                $status = 'Já completou 18 anos';
                $prioridade = 'alta';
            }
        } else if ($diasPara18 <= 0) {
            $status = 'Completa 18 anos hoje';
            $prioridade = 'critica';
        } else if ($diasPara18 <= 30) {
            $status = 'Completa em ' . $diasPara18 . ' dias';
            $prioridade = 'alta';
        } else if ($diasPara18 <= 90) {
            $meses = round($diasPara18 / 30);
            $status = 'Completa em ' . $meses . ' ' . ($meses == 1 ? 'mês' : 'meses');
            $prioridade = 'media';
        } else {
            $meses = round($diasPara18 / 30);
            $status = 'Completa em ' . $meses . ' meses';
            $prioridade = 'baixa';
        }

        // Monta o array de resposta
        $dependentesProcessados[] = [
            'dependente_id' => $dep['dependente_id'],
            'nome_dependente' => $dep['nome_dependente'],
            'data_nascimento' => $dep['data_nascimento'],
            'parentesco' => $dep['parentesco'],
            'sexo' => $dep['sexo'],
            'idade_atual' => $idade,
            'data_18_anos' => $dep['data_18_anos'],
            'dias_para_18' => $diasPara18,
            'status' => $status,
            'prioridade' => $prioridade,
            'associado_id' => $dep['associado_id'],
            'nome_responsavel' => $dep['nome_responsavel'],
            'rg_responsavel' => $dep['rg_responsavel'],
            'cpf_responsavel' => $dep['cpf_responsavel'],
            'telefone_responsavel' => $dep['telefone_responsavel'],
            'email_responsavel' => $dep['email_responsavel'],
            'situacao_associado' => $dep['situacao_associado']
        ];
    }

    // Estatísticas
    $estatisticas = [
        'total_encontrados' => count($dependentesProcessados),
        'ja_completaram' => 0,
        'alta_prioridade' => 0,
        'media_prioridade' => 0,
        'baixa_prioridade' => 0
    ];

    // Calcula estatísticas
    foreach ($dependentesProcessados as $dep) {
        if ($dep['prioridade'] === 'critica' || ($dep['idade_atual'] >= 18)) {
            $estatisticas['ja_completaram']++;
        }
        if ($dep['prioridade'] === 'alta') {
            $estatisticas['alta_prioridade']++;
        }
        if ($dep['prioridade'] === 'media') {
            $estatisticas['media_prioridade']++;
        }
        if ($dep['prioridade'] === 'baixa') {
            $estatisticas['baixa_prioridade']++;
        }
    }

    // Log do resultado
    error_log("Total de dependentes encontrados: " . count($dependentesProcessados));

    // Resposta de sucesso
    jsonResponse('success', 'Dados carregados com sucesso', [
        'dependentes' => $dependentesProcessados,
        'estatisticas' => $estatisticas,
        'filtros_aplicados' => [
            'situacao' => $situacao,
            'busca' => $busca
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    jsonResponse('error', 'Erro ao acessar banco de dados: ' . $e->getMessage());

} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    jsonResponse('error', 'Erro interno: ' . $e->getMessage());
}

// Fallback - nunca deveria chegar aqui
jsonResponse('error', 'Erro inesperado no processamento');
?>