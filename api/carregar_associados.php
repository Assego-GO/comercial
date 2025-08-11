<?php
/**
 * Script otimizado para carregar dados dos associados
 * api/carregar_associados.php
 */

// Desabilita erros de exibição
error_reporting(0);
ini_set('display_errors', '0');

// Aumenta limite de memória e tempo
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '300');

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Função para enviar resposta
function sendResponse($data) {
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

try {
    // Carrega configurações
    @include_once '../config/database.php';
    
    // Verifica constantes
    if (!defined('DB_HOST') || !defined('DB_NAME_CADASTRO')) {
        throw new Exception('Configurações não encontradas');
    }
    
    // Conexão com configurações otimizadas
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
    
    // Desabilita temporariamente o ONLY_FULL_GROUP_BY para esta sessão
    $pdo->exec("SET SESSION sql_mode = REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', '')");
    
    // Para compatibilidade com o frontend atual, vamos carregar todos os dados
    // mas de forma otimizada
    
    // Primeiro, conta o total de registros
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM Associados");
    $totalRegistros = $countStmt->fetch()['total'];
    
    // Se houver muitos registros, limita a consulta
    $limite = $totalRegistros > 5000 ? 5000 : $totalRegistros;
    
    // CORRIGIDO: Query otimizada - INCLUINDO OS NOVOS CAMPOS
    $sql = "
        SELECT DISTINCT
            a.id,
            a.nome,
            a.cpf,
            a.foto,
            a.rg,
            a.email,
            a.telefone,
            a.nasc,
            a.sexo,
            COALESCE(a.situacao, 'Desfiliado') as situacao,
            a.escolaridade,
            a.estadoCivil,
            a.indicacao,
            MAX(m.corporacao) as corporacao,
            MAX(m.patente) as patente,
            MAX(m.categoria) as categoria,
            MAX(m.lotacao) as lotacao,
            MAX(m.unidade) as unidade,
            MAX(f.tipoAssociado) as tipoAssociado,
            MAX(f.situacaoFinanceira) as situacaoFinanceira,
            MAX(f.vinculoServidor) as vinculoServidor,
            MAX(f.localDebito) as localDebito,
            MAX(f.agencia) as agencia,
            MAX(f.operacao) as operacao,
            MAX(f.contaCorrente) as contaCorrente,
            MAX(f.observacoes) as observacoes,
            MAX(f.doador) as doador,
            MAX(e.cep) as cep,
            MAX(e.endereco) as endereco,
            MAX(e.bairro) as bairro,
            MAX(e.cidade) as cidade,
            MAX(e.numero) as numero,
            MAX(e.complemento) as complemento,
            MAX(c.dataFiliacao) as data_filiacao,
            MAX(c.dataDesfiliacao) as data_desfiliacao
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Endereco e ON a.id = e.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        GROUP BY 
            a.id,
            a.nome,
            a.cpf,
            a.rg,
            a.email,
            a.telefone,
            a.nasc,
            a.sexo,
            a.situacao,
            a.escolaridade,
            a.estadoCivil,
            a.indicacao
        ORDER BY a.id DESC
        LIMIT " . $limite;
    
    $stmt = $pdo->query($sql);
    
    // Processa os dados e remove duplicatas no PHP também
    $dados = [];
    $idsProcessados = [];
    
    while ($row = $stmt->fetch()) {
        // Evita duplicatas verificando o ID
        if (in_array($row['id'], $idsProcessados)) {
            continue;
        }
        $idsProcessados[] = $row['id'];
        
        // CORRIGIDO: Incluindo os novos campos na resposta
        $dados[] = [
            'id' => intval($row['id']),
            'nome' => $row['nome'] ?? '',
            'cpf' => $row['cpf'] ?? '',
            'rg' => $row['rg'] ?? '',
            'email' => $row['email'] ?? '',
            'telefone' => $row['telefone'] ?? '',
            'nasc' => $row['nasc'] ?? '',
            'sexo' => $row['sexo'] ?? '',
            'situacao' => $row['situacao'],
            'escolaridade' => $row['escolaridade'] ?? '',
            'estadoCivil' => $row['estadoCivil'] ?? '',
            'foto' => $row['foto'] ?? '',
            'indicacao' => $row['indicacao'] ?? '',
            'corporacao' => $row['corporacao'] ?? '',
            'patente' => $row['patente'] ?? '',
            'categoria' => $row['categoria'] ?? '',
            'lotacao' => $row['lotacao'] ?? '',
            'unidade' => $row['unidade'] ?? '',
            'tipoAssociado' => $row['tipoAssociado'] ?? '',
            'situacaoFinanceira' => $row['situacaoFinanceira'] ?? '',
            'vinculoServidor' => $row['vinculoServidor'] ?? '',
            'localDebito' => $row['localDebito'] ?? '',
            'agencia' => $row['agencia'] ?? '',
            'operacao' => $row['operacao'] ?? '',
            'contaCorrente' => $row['contaCorrente'] ?? '',
            'observacoes' => $row['observacoes'] ?? '',
            'doador' => intval($row['doador'] ?? 0),
            'cep' => $row['cep'] ?? '',
            'endereco' => $row['endereco'] ?? '',
            'bairro' => $row['bairro'] ?? '',
            'cidade' => $row['cidade'] ?? '',
            'numero' => $row['numero'] ?? '',
            'complemento' => $row['complemento'] ?? '',
            'data_filiacao' => $row['data_filiacao'] ?? '',
            'data_desfiliacao' => $row['data_desfiliacao'] ?? '',
            'dependentes' => [],
            'redesSociais' => [],
            'servicos' => [],
            'documentos' => []
        ];
    }
    
    // Libera recursos
    $stmt->closeCursor();
    $stmt = null;
    $pdo = null;
    
    // Resposta de sucesso
    $response = [
        'status' => 'success',
        'total' => count($dados),
        'dados' => $dados,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($totalRegistros > $limite) {
        $response['aviso'] = "Mostrando apenas os $limite registros mais recentes de $totalRegistros total";
    }
    
    sendResponse($response);
    
} catch (Exception $e) {
    // Log do erro
    error_log("Erro em carregar_associados.php: " . $e->getMessage());
    
    // Resposta de erro
    sendResponse([
        'status' => 'error',
        'message' => 'Erro ao carregar dados',
        'total' => 0,
        'dados' => [],
        'error' => $e->getMessage()
    ]);
}
?>