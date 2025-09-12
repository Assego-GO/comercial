<?php
/**
 * Script OTIMIZADO para carregar dados dos associados
 * api/carregar_associados.php
 * VERSÃO COM PAGINAÇÃO E CARREGAMENTO INTELIGENTE
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

    // 🚀 NOVIDADE: Parâmetros de paginação
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(500, max(10, intval($_GET['limit']))) : 100;
    $loadType = $_GET['load_type'] ?? 'initial'; // 'initial', 'page', 'all'
    
    $offset = ($page - 1) * $limit;

    // Conta o total de registros (uma vez só)
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM Associados WHERE pre_cadastro = 0");
    $totalRegistros = $countStmt->fetch()['total'];

    // 🚀 QUERY OTIMIZADA: Remove dados desnecessários para listagem
    if ($loadType === 'all') {
        // Carrega todos (para compatibilidade com código existente)
        $sqlLimit = "LIMIT 15000";
    } else {
        // Carrega apenas a página solicitada
        $sqlLimit = "LIMIT $limit OFFSET $offset";
    }

    $sql = "
    SELECT DISTINCT
        a.id,
        a.nome,
        a.cpf,
        a.rg,
        a.telefone,
        a.foto,
        COALESCE(a.situacao, 'Desfiliado') as situacao,
        -- Dados básicos apenas para listagem
        m.corporacao,
        m.patente,
        c.dataFiliacao as data_filiacao
    FROM Associados a
    LEFT JOIN Militar m ON a.id = m.associado_id
    LEFT JOIN Contrato c ON a.id = c.associado_id
    WHERE a.pre_cadastro = 0
    ORDER BY a.id DESC
    $sqlLimit
    ";

    $stmt = $pdo->query($sql);
    $dados = [];
    $associadosIds = [];

    while ($row = $stmt->fetch()) {
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
            // 🚀 Dados extras carregados sob demanda
            'detalhes_carregados' => false
        ];
    }

    // 🚀 Se for carregamento inicial, também busca os filtros
    $corporacoes = [];
    $patentes = [];
    
    if ($loadType === 'initial' || $loadType === 'all') {
        // Busca corporações únicas (otimizado)
        $sqlCorp = "SELECT DISTINCT corporacao FROM Militar WHERE corporacao IS NOT NULL AND corporacao != '' ORDER BY corporacao";
        $stmtCorp = $pdo->query($sqlCorp);
        while ($corp = $stmtCorp->fetch()) {
            if (!empty($corp['corporacao'])) {
                $corporacoes[] = normalizarCorporacao($corp['corporacao']);
            }
        }
        $corporacoes = array_unique($corporacoes);
        sort($corporacoes);

        // Busca patentes únicas (otimizado)
        $sqlPat = "SELECT DISTINCT patente FROM Militar WHERE patente IS NOT NULL AND patente != '' ORDER BY patente";
        $stmtPat = $pdo->query($sqlPat);
        while ($pat = $stmtPat->fetch()) {
            if (!empty($pat['patente'])) {
                $patentes[] = $pat['patente'];
            }
        }
        sort($patentes);
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
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Adiciona filtros apenas se necessário
    if ($loadType === 'initial' || $loadType === 'all') {
        $response['corporacoes_unicas'] = $corporacoes;
        $response['patentes_unicas'] = $patentes;
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