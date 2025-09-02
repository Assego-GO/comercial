<?php
/**
 * API para carregar detalhes completos de UM associado específico
 * api/carregar_detalhes_associado.php
 * 🚀 OTIMIZADO - Carrega apenas quando necessário (modal)
 */

error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

function sendResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

@session_start();

if (!isset($_SESSION['funcionario_id'])) {
    sendResponse([
        'status' => 'error',
        'message' => 'Não autorizado'
    ]);
}

// Parâmetros
$associadoId = $_GET['id'] ?? null;

if (!$associadoId || !is_numeric($associadoId)) {
    sendResponse([
        'status' => 'error',
        'message' => 'ID do associado é obrigatório'
    ]);
}

try {
    @include_once '../config/database.php';

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

    // 🚀 Query otimizada para UM associado específico
    $sql = "
    SELECT 
        a.*,
        m.corporacao,
        m.patente,
        m.categoria,
        m.lotacao,
        m.unidade,
        f.tipoAssociado,
        f.situacaoFinanceira,
        f.vinculoServidor,
        f.localDebito,
        f.agencia,
        f.operacao,
        f.contaCorrente,
        f.id_neoconsig,
        f.doador,
        e.cep,
        e.endereco,
        e.bairro,
        e.cidade,
        e.numero,
        e.complemento,
        c.dataFiliacao as data_filiacao,
        c.dataDesfiliacao as data_desfiliacao,
        p.valor as peculio_valor,
        p.data_prevista as peculio_data_prevista,
        p.data_recebimento as peculio_data_recebimento
    FROM Associados a
    LEFT JOIN Militar m ON a.id = m.associado_id
    LEFT JOIN Financeiro f ON a.id = f.associado_id
    LEFT JOIN Endereco e ON a.id = e.associado_id
    LEFT JOIN Contrato c ON a.id = c.associado_id
    LEFT JOIN Peculio p ON a.id = p.associado_id
    WHERE a.id = :id
    LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $associadoId]);
    $associado = $stmt->fetch();

    if (!$associado) {
        sendResponse([
            'status' => 'error',
            'message' => 'Associado não encontrado'
        ]);
    }

    // 🚀 Busca dependentes
    $sqlDependentes = "
        SELECT nome, data_nascimento, parentesco, sexo
        FROM Dependentes
        WHERE associado_id = :id
        ORDER BY nome
    ";
    $stmtDep = $pdo->prepare($sqlDependentes);
    $stmtDep->execute(['id' => $associadoId]);
    $dependentes = $stmtDep->fetchAll();

    // 🚀 Busca contagens
    $sqlContagens = "
        SELECT 
            (SELECT COUNT(*) FROM Servicos_Associado WHERE associado_id = :id AND ativo = 1) as total_servicos,
            (SELECT COUNT(*) FROM Documentos_Associado WHERE associado_id = :id) as total_documentos,
            (SELECT COUNT(*) FROM Observacoes_Associado WHERE associado_id = :id AND ativo = 1) as total_observacoes,
            (SELECT COUNT(*) FROM Observacoes_Associado WHERE associado_id = :id AND ativo = 1 AND importante = 1) as observacoes_importantes
    ";
    $stmtCount = $pdo->prepare($sqlContagens);
    $stmtCount->execute(['id' => $associadoId]);
    $contagens = $stmtCount->fetch();

    // 🚀 Monta resposta completa
    $dadosCompletos = [
        'id' => intval($associado['id']),
        'nome' => $associado['nome'] ?? '',
        'cpf' => $associado['cpf'] ?? '',
        'rg' => $associado['rg'] ?? '',
        'email' => $associado['email'] ?? '',
        'telefone' => $associado['telefone'] ?? '',
        'nasc' => $associado['nasc'] ?? '',
        'sexo' => $associado['sexo'] ?? '',
        'situacao' => $associado['situacao'] ?? 'Desfiliado',
        'escolaridade' => $associado['escolaridade'] ?? '',
        'estadoCivil' => $associado['estadoCivil'] ?? '',
        'foto' => $associado['foto'] ?? '',
        'indicacao' => $associado['indicacao'] ?? '',
        
        // Militar
        'corporacao' => $associado['corporacao'] ?? '',
        'patente' => $associado['patente'] ?? '',
        'categoria' => $associado['categoria'] ?? '',
        'lotacao' => $associado['lotacao'] ?? '',
        'unidade' => $associado['unidade'] ?? '',
        
        // Financeiro
        'tipoAssociado' => $associado['tipoAssociado'] ?? '',
        'situacaoFinanceira' => $associado['situacaoFinanceira'] ?? '',
        'vinculoServidor' => $associado['vinculoServidor'] ?? '',
        'localDebito' => $associado['localDebito'] ?? '',
        'agencia' => $associado['agencia'] ?? '',
        'operacao' => $associado['operacao'] ?? '',
        'contaCorrente' => $associado['contaCorrente'] ?? '',
        'id_neoconsig' => $associado['id_neoconsig'] ?? '',
        'doador' => intval($associado['doador'] ?? 0),
        
        // Endereço
        'cep' => $associado['cep'] ?? '',
        'endereco' => $associado['endereco'] ?? '',
        'bairro' => $associado['bairro'] ?? '',
        'cidade' => $associado['cidade'] ?? '',
        'numero' => $associado['numero'] ?? '',
        'complemento' => $associado['complemento'] ?? '',
        
        // Datas
        'data_filiacao' => $associado['data_filiacao'] ?? '',
        'data_desfiliacao' => $associado['data_desfiliacao'] ?? '',
        
        // Péculio
        'peculio_valor' => $associado['peculio_valor'] ?? null,
        'peculio_data_prevista' => $associado['peculio_data_prevista'] ?? null,
        'peculio_data_recebimento' => $associado['peculio_data_recebimento'] ?? null,
        
        // Dependentes e contagens
        'dependentes' => $dependentes,
        'total_dependentes' => count($dependentes),
        'total_servicos' => intval($contagens['total_servicos'] ?? 0),
        'total_documentos' => intval($contagens['total_documentos'] ?? 0),
        'total_observacoes' => intval($contagens['total_observacoes'] ?? 0),
        'tem_observacoes_importantes' => intval($contagens['observacoes_importantes'] ?? 0) > 0,
        
        // Flag de controle
        'detalhes_carregados' => true
    ];

    sendResponse([
        'status' => 'success',
        'dados' => $dadosCompletos,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Erro em carregar_detalhes_associado.php: " . $e->getMessage());
    
    sendResponse([
        'status' => 'error',
        'message' => 'Erro ao carregar detalhes do associado',
        'error' => $e->getMessage()
    ]);
}
?>