<?php
/**
 * API para listar dependentes que completaram ou estão prestes a completar 18 anos
 * api/dependentes/listar_18anos.php
 */

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratamento de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclui arquivos necessários
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

// Função para resposta JSON
function jsonResponse($status, $message, $data = null) {
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse('error', 'Método não permitido. Use GET.');
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
    
    // Verificar permissões para controle de dependentes
    $temPermissaoControle = false;
    $departamentoId = $usuarioLogado['departamento_id'] ?? null;
    
    // Financeiro (ID: 5), Presidência (ID: 1) ou Diretor
    if ($departamentoId == 5 || $departamentoId == 1 || $auth->isDiretor()) {
        $temPermissaoControle = true;
    }
    
    if (!$temPermissaoControle) {
        jsonResponse('error', 'Acesso negado. Apenas Financeiro, Presidência ou Diretoria têm acesso.');
    }
    
    // Obter parâmetros de filtro
    $situacao = $_GET['situacao'] ?? 'ja_completaram';
    $busca = $_GET['busca'] ?? '';
    
    // Log para debug
    error_log("=== API DEPENDENTES 18 ANOS ===");
    error_log("Usuário: " . $usuarioLogado['nome']);
    error_log("Departamento: " . $departamentoId);
    error_log("Situação: " . $situacao);
    error_log("Busca: " . $busca);
    
    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Monta a query base específica para APENAS FILHOS (não outros dependentes)
    $sqlBase = "
        SELECT 
            d.id as dependente_id,
            d.nome as nome_dependente,
            d.data_nascimento,
            d.parentesco,
            d.sexo,
            a.id as associado_id,
            a.nome as nome_responsavel,
            a.rg as rg_responsavel,
            a.telefone as telefone_responsavel,
            a.email as email_responsavel,
            a.situacao as situacao_associado,
            f.situacaoFinanceira as situacao_financeira,
            f.tipoAssociado as tipo_associado,
            TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) as idade_atual,
            DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR) as data_18_anos,
            DATEDIFF(DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR), CURDATE()) as dias_para_18
        FROM Dependentes d
        INNER JOIN Associados a ON d.associado_id = a.id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        WHERE (
            -- Valores específicos que representam filhos
            d.parentesco LIKE '%filho%' OR d.parentesco LIKE '%filha%' 
            OR d.parentesco IN ('Dependente', 'Menor', 'Criança')
        )
        AND (
            -- Exclui explicitamente não-filhos
            d.parentesco NOT LIKE '%cônjuge%' 
            AND d.parentesco NOT LIKE '%conjuge%'
            AND d.parentesco NOT LIKE '%esposa%' 
            AND d.parentesco NOT LIKE '%esposo%'
            AND d.parentesco NOT LIKE '%marido%'
            AND d.parentesco NOT LIKE '%pai%' 
            AND d.parentesco NOT LIKE '%mãe%'
            AND d.parentesco NOT LIKE '%mae%'
            AND d.parentesco NOT LIKE '%irmã%' 
            AND d.parentesco NOT LIKE '%irmao%'
            AND d.parentesco NOT LIKE '%irmão%'
            AND d.parentesco NOT LIKE '%avô%' 
            AND d.parentesco NOT LIKE '%avó%'
            AND d.parentesco NOT LIKE '%avo%'
            AND d.parentesco NOT LIKE '%sogro%' 
            AND d.parentesco NOT LIKE '%sogra%'
            AND d.parentesco NOT LIKE '%cunhad%'
            AND d.parentesco NOT LIKE '%primo%' 
            AND d.parentesco NOT LIKE '%prima%'
            AND d.parentesco NOT LIKE '%tio%' 
            AND d.parentesco NOT LIKE '%tia%'
        )
    ";
    
    // Adiciona filtros baseados na situação
    $params = [];
    $whereConditions = [];
    
    switch ($situacao) {
        case 'ja_completaram':
            $whereConditions[] = "TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) >= 18";
            break;
            
        case 'este_mes':
            $whereConditions[] = "YEAR(DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR)) = YEAR(CURDATE())";
            $whereConditions[] = "MONTH(DATE_ADD(d.data_nascimento, INTERVAL 18 YEAR)) = MONTH(CURDATE())";
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
            // Todos os dependentes filhos, sem filtro de idade
            break;
            
        default:
            $whereConditions[] = "TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) >= 18";
            break;
    }
    
    // Adiciona filtro de busca por nome
    if (!empty($busca)) {
        $whereConditions[] = "(d.nome LIKE :busca OR a.nome LIKE :busca OR a.rg LIKE :busca)";
        $params[':busca'] = '%' . $busca . '%';
    }
    
    // Monta a query final
    $sql = $sqlBase;
    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }
    
    // Ordena por idade decrescente (mais críticos primeiro)
    $sql .= " ORDER BY idade_atual DESC, d.nome ASC";
    
    // Log da query para debug
    error_log("Query: " . $sql);
    error_log("Params: " . json_encode($params));
    
    // Executa a query
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    // Busca os resultados
    $dependentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processa os dados para adicionar informações calculadas
    $dependentesProcessados = [];
    foreach ($dependentes as $dep) {
        // Calcula informações adicionais
        $idade = (int)$dep['idade_atual'];
        $diasPara18 = (int)$dep['dias_para_18'];
        
        // Determina o status
        $status = '';
        $prioridade = 'normal';
        
        if ($idade >= 18) {
            $status = 'Já completou 18 anos';
            $prioridade = 'critica';
        } else if ($diasPara18 <= 30) {
            $status = 'Completa em ' . $diasPara18 . ' dias';
            $prioridade = 'alta';
        } else if ($diasPara18 <= 90) {
            $status = 'Completa em ' . round($diasPara18/30) . ' meses';
            $prioridade = 'media';
        } else {
            $status = 'Completa em ' . round($diasPara18/30) . ' meses';
            $prioridade = 'baixa';
        }
        
        // Formata dados para a resposta
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
            'telefone_responsavel' => $dep['telefone_responsavel'],
            'email_responsavel' => $dep['email_responsavel'],
            'situacao_associado' => $dep['situacao_associado'],
            'situacao_financeira' => $dep['situacao_financeira'] ?? 'N/A',
            'tipo_associado' => $dep['tipo_associado'] ?? 'Regular'
        ];
    }
    
    // Log do resultado
    error_log("Dependentes encontrados: " . count($dependentesProcessados));
    
    // Estatísticas para o retorno
    $estatisticas = [
        'total_encontrados' => count($dependentesProcessados),
        'ja_completaram' => count(array_filter($dependentesProcessados, function($d) { return $d['prioridade'] === 'critica'; })),
        'alta_prioridade' => count(array_filter($dependentesProcessados, function($d) { return $d['prioridade'] === 'alta'; })),
        'media_prioridade' => count(array_filter($dependentesProcessados, function($d) { return $d['prioridade'] === 'media'; })),
        'baixa_prioridade' => count(array_filter($dependentesProcessados, function($d) { return $d['prioridade'] === 'baixa'; }))
    ];
    
    // Resposta de sucesso
    jsonResponse('success', 'Dependentes carregados com sucesso', [
        'dependentes' => $dependentesProcessados,
        'estatisticas' => $estatisticas,
        'filtros_aplicados' => [
            'situacao' => $situacao,
            'busca' => $busca
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Erro de banco de dados: " . $e->getMessage());
    jsonResponse('error', 'Erro ao acessar banco de dados: ' . $e->getMessage());
    
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    jsonResponse('error', 'Erro interno do servidor: ' . $e->getMessage());
}
?>