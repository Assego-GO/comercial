<?php
/**
 * Script OTIMIZADO para carregar dados dos associados
 * api/carregar_associados.php
 * 
 * VERSÃO CORRIGIDA - Com filtro de tipo_associado funcionando corretamente
 * 
 * MUDANÇAS PRINCIPAIS:
 * 1. Aceita parâmetro GET 'tipo_associado' para filtrar no servidor
 * 2. Usa INNER JOIN com Servicos_Associado quando filtro está ativo
 * 3. Retorna contagem correta de registros filtrados
 */

// Desabilita erros de exibição
error_reporting(0);
ini_set('display_errors', '0');

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Função para enviar resposta
function sendResponse($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Inicia sessão
@session_start();

// Verifica autenticação
if (!isset($_SESSION['funcionario_id'])) {
    sendResponse([
        'status' => 'error',
        'message' => 'Não autorizado',
        'total' => 0,
        'dados' => []
    ]);
}

// Função para normalizar nome da corporação (mantida igual)
function normalizarCorporacao($corporacao)
{
    if (empty($corporacao)) return '';

    $corporacao = trim($corporacao);
    $corporacao = preg_replace('/\s+/', ' ', $corporacao);
    $corporacaoUpper = strtoupper($corporacao);
    $corporacaoUpper = str_replace(
        ['Á', 'À', 'Ã', 'Â', 'É', 'È', 'Ê', 'Í', 'Ì', 'Î', 'Ó', 'Ò', 'Õ', 'Ô', 'Ú', 'Ù', 'Û', 'Ç'],
        ['A', 'A', 'A', 'A', 'E', 'E', 'E', 'I', 'I', 'I', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'C'],
        $corporacaoUpper
    );

    $mapeamento = [
        'PM' => 'Polícia Militar', 'P.M.' => 'Polícia Militar', 'P.M' => 'Polícia Militar',
        'PMGO' => 'Polícia Militar', 'PM-GO' => 'Polícia Militar', 'PM GO' => 'Polícia Militar',
        'POLICIA MILITAR' => 'Polícia Militar', 'POLÍCIA MILITAR' => 'Polícia Militar',
        'BM' => 'Bombeiro Militar', 'B.M.' => 'Bombeiro Militar', 'B.M' => 'Bombeiro Militar',
        'BMGO' => 'Bombeiro Militar', 'BM-GO' => 'Bombeiro Militar', 'CBM' => 'Bombeiro Militar',
        'BOMBEIRO' => 'Bombeiro Militar', 'BOMBEIROS' => 'Bombeiro Militar',
        'BOMBEIRO MILITAR' => 'Bombeiro Militar', 'CORPO DE BOMBEIROS' => 'Bombeiro Militar',
        'PC' => 'Polícia Civil', 'P.C.' => 'Polícia Civil', 'PCGO' => 'Polícia Civil',
        'POLICIA CIVIL' => 'Polícia Civil', 'POLÍCIA CIVIL' => 'Polícia Civil',
        'PP' => 'Polícia Penal', 'P.P.' => 'Polícia Penal', 'PPGO' => 'Polícia Penal',
        'POLICIA PENAL' => 'Polícia Penal', 'AGEPEN' => 'Polícia Penal', 'DGAP' => 'Polícia Penal'
    ];

    if (isset($mapeamento[$corporacaoUpper])) {
        return $mapeamento[$corporacaoUpper];
    }

    foreach ($mapeamento as $chave => $valor) {
        if (stripos($corporacaoUpper, $chave) !== false) {
            return $valor;
        }
    }

    $palavras = explode(' ', mb_strtolower($corporacao, 'UTF-8'));
    $palavrasPadronizadas = array_map(function ($palavra) {
        $minusculas = ['de', 'da', 'do', 'dos', 'das', 'e', 'em'];
        if (in_array($palavra, $minusculas)) {
            return $palavra;
        }
        return mb_convert_case($palavra, MB_CASE_TITLE, 'UTF-8');
    }, $palavras);

    return implode(' ', $palavrasPadronizadas);
}

try {
    @include_once '../config/database.php';

    if (!defined('DB_HOST') || !defined('DB_NAME_CADASTRO')) {
        throw new Exception('Configurações não encontradas');
    }

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME_CADASTRO . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(500, max(10, intval($_GET['limit']))) : 100;
    $loadType = $_GET['load_type'] ?? 'initial';
    
    // NOVO: Parâmetro de filtro por tipo_associado
    $filterTipoAssociado = isset($_GET['tipo_associado']) ? trim($_GET['tipo_associado']) : '';
    
    $offset = ($page - 1) * $limit;

    // CORREÇÃO PRINCIPAL: A query agora usa JOIN para filtrar corretamente por tipo_associado
    // quando o filtro está ativo
    
    $whereConditions = ["a.pre_cadastro = 0"];
    $joinServicos = "";
    $params = [];
    
    // Se tiver filtro de tipo_associado, fazer JOIN com Servicos_Associado
    if (!empty($filterTipoAssociado)) {
        $joinServicos = "INNER JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1";
        $whereConditions[] = "sa.tipo_associado = :tipo_associado";
        $params[':tipo_associado'] = $filterTipoAssociado;
    }
    
    $whereClause = implode(' AND ', $whereConditions);

    // Conta o total de registros COM o filtro aplicado
    $countSql = "
        SELECT COUNT(DISTINCT a.id) as total 
        FROM Associados a
        $joinServicos
        WHERE $whereClause
    ";
    
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRegistros = $countStmt->fetch()['total'];

    // Define o limite
    if ($loadType === 'all') {
        $sqlLimit = "LIMIT 15000";
    } else {
        $sqlLimit = "LIMIT $limit OFFSET $offset";
    }

    // QUERY PRINCIPAL CORRIGIDA
    // Usa DISTINCT para evitar duplicatas quando há múltiplos serviços
    $sql = "
    SELECT DISTINCT
        a.id,
        a.nome,
        a.cpf,
        a.rg,
        a.telefone,
        a.foto,
        COALESCE(a.situacao, 'Desfiliado') as situacao,
        m.corporacao,
        m.patente,
        c.dataFiliacao as data_filiacao,
        -- Busca o tipo_associado (se filtro ativo, já está no JOIN)
        " . (empty($filterTipoAssociado) ? "
        (SELECT sa2.tipo_associado 
         FROM Servicos_Associado sa2 
         WHERE sa2.associado_id = a.id AND sa2.ativo = 1 
         ORDER BY sa2.id DESC LIMIT 1) as tipo_associado
        " : "sa.tipo_associado as tipo_associado") . "
    FROM Associados a
    LEFT JOIN Militar m ON a.id = m.associado_id
    LEFT JOIN Contrato c ON a.id = c.associado_id
    $joinServicos
    WHERE $whereClause
    ORDER BY a.id DESC
    $sqlLimit
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $dados = [];
    $associadosIds = [];

    while ($row = $stmt->fetch()) {
        // Evita duplicatas por ID
        if (in_array($row['id'], $associadosIds)) {
            continue;
        }
        $associadosIds[] = $row['id'];
        
        $dados[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome'] ?? '',
            'cpf' => $row['cpf'] ?? '',
            'rg' => $row['rg'] ?? '',
            'telefone' => $row['telefone'] ?? '',
            'situacao' => $row['situacao'],
            'corporacao' => normalizarCorporacao($row['corporacao']),
            'patente' => $row['patente'] ?? '',
            'data_filiacao' => $row['data_filiacao'] ?? '',
            'foto' => $row['foto'] ?? '',
            'tipo_associado' => $row['tipo_associado'] ?? '',
            'detalhes_carregados' => false
        ];
    }

    // Busca os filtros disponíveis
    $corporacoes = [];
    $patentes = [];
    $tiposAssociado = [];
    
    if ($loadType === 'initial' || $loadType === 'all') {
        // Busca corporações únicas
        $sqlCorp = "SELECT DISTINCT corporacao FROM Militar WHERE corporacao IS NOT NULL AND corporacao != '' ORDER BY corporacao";
        $stmtCorp = $pdo->query($sqlCorp);
        while ($corp = $stmtCorp->fetch()) {
            if (!empty($corp['corporacao'])) {
                $corporacoes[] = normalizarCorporacao($corp['corporacao']);
            }
        }
        $corporacoes = array_unique($corporacoes);
        sort($corporacoes);

        // Busca patentes únicas
        $sqlPat = "SELECT DISTINCT patente FROM Militar WHERE patente IS NOT NULL AND patente != '' ORDER BY patente";
        $stmtPat = $pdo->query($sqlPat);
        while ($pat = $stmtPat->fetch()) {
            if (!empty($pat['patente'])) {
                $patentes[] = $pat['patente'];
            }
        }
        sort($patentes);

        // CORREÇÃO: Busca tipos de associado únicos - conta quantos associados tem cada tipo
        $sqlTipos = "
            SELECT DISTINCT sa.tipo_associado, COUNT(DISTINCT sa.associado_id) as total
            FROM Servicos_Associado sa 
            WHERE sa.tipo_associado IS NOT NULL 
              AND sa.tipo_associado != '' 
              AND sa.ativo = 1 
            GROUP BY sa.tipo_associado
            ORDER BY sa.tipo_associado
        ";
        $stmtTipos = $pdo->query($sqlTipos);
        while ($tipo = $stmtTipos->fetch()) {
            if (!empty($tipo['tipo_associado'])) {
                $tiposAssociado[] = [
                    'valor' => $tipo['tipo_associado'],
                    'total' => $tipo['total']
                ];
            }
        }
    }

    $response = [
        'status' => 'success',
        'total' => count($dados),
        'total_banco' => $totalRegistros,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalRegistros / $limit),
        'has_next' => ($offset + $limit) < $totalRegistros,
        'dados' => $dados,
        'load_type' => $loadType,
        'filtro_tipo_associado' => $filterTipoAssociado, // DEBUG: mostra o filtro aplicado
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Adiciona filtros apenas se necessário
    if ($loadType === 'initial' || $loadType === 'all') {
        $response['corporacoes_unicas'] = $corporacoes;
        $response['patentes_unicas'] = $patentes;
        $response['tipos_associado_unicos'] = array_column($tiposAssociado, 'valor');
        $response['tipos_associado_contagem'] = $tiposAssociado; // Com contagem
    }

    sendResponse($response);

} catch (Exception $e) {
    error_log("Erro em carregar_associados.php: " . $e->getMessage());
    
    sendResponse([
        'status' => 'error',
        'message' => 'Erro ao carregar dados',
        'total' => 0,
        'dados' => [],
        'error' => $e->getMessage()
    ]);
}
?>