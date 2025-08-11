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
    
    // Primeiro, conta o total de registros
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM Associados");
    $totalRegistros = $countStmt->fetch()['total'];
    
    // Se houver muitos registros, limita a consulta
    $limite = $totalRegistros > 5000 ? 5000 : $totalRegistros;
    
    // Query otimizada - seleciona apenas campos essenciais
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
            a.pre_cadastro,
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
            a.indicacao,
            a.pre_cadastro,
            a.foto
        ORDER BY a.id DESC
        LIMIT " . $limite;
    
    $stmt = $pdo->query($sql);
    
    // Processa os dados e remove duplicatas no PHP também
    $dados = [];
    $idsProcessados = [];
    $associadosIds = [];
    
    while ($row = $stmt->fetch()) {
        // Evita duplicatas verificando o ID
        if (in_array($row['id'], $idsProcessados)) {
            continue;
        }
        $idsProcessados[] = $row['id'];
        $associadosIds[] = $row['id']; // Salva os IDs para buscar dependentes depois
        
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
            'pre_cadastro' => $row['pre_cadastro'] ?? 0,
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
            'cep' => $row['cep'] ?? '',
            'endereco' => $row['endereco'] ?? '',
            'bairro' => $row['bairro'] ?? '',
            'cidade' => $row['cidade'] ?? '',
            'numero' => $row['numero'] ?? '',
            'complemento' => $row['complemento'] ?? '',
            'data_filiacao' => $row['data_filiacao'] ?? '',
            'data_desfiliacao' => $row['data_desfiliacao'] ?? '',
            'dependentes' => [],
            'total_dependentes' => 0,
            'total_servicos' => 0,
            'total_documentos' => 0,
            'redesSociais' => [],
            'servicos' => [],
            'documentos' => []
        ];
    }
    
    // Busca dependentes para todos os associados de uma vez
    if (!empty($associadosIds)) {
        $placeholders = str_repeat('?,', count($associadosIds) - 1) . '?';
        
        // Busca os dependentes
        $sqlDependentes = "
            SELECT 
                associado_id,
                nome,
                data_nascimento,
                parentesco,
                sexo
            FROM Dependentes
            WHERE associado_id IN ($placeholders)
            ORDER BY associado_id, nome
        ";
        
        $stmtDep = $pdo->prepare($sqlDependentes);
        $stmtDep->execute($associadosIds);
        
        $dependentesPorAssociado = [];
        while ($dep = $stmtDep->fetch()) {
            if (!isset($dependentesPorAssociado[$dep['associado_id']])) {
                $dependentesPorAssociado[$dep['associado_id']] = [];
            }
            $dependentesPorAssociado[$dep['associado_id']][] = [
                'nome' => $dep['nome'] ?? '',
                'data_nascimento' => $dep['data_nascimento'] ?? '',
                'parentesco' => $dep['parentesco'] ?? '',
                'sexo' => $dep['sexo'] ?? ''
            ];
        }
        
        // Busca a contagem de serviços ativos
        $sqlServicos = "
            SELECT 
                associado_id,
                COUNT(*) as total
            FROM Servicos_Associado
            WHERE associado_id IN ($placeholders)
            AND ativo = 1
            GROUP BY associado_id
        ";
        
        $stmtServ = $pdo->prepare($sqlServicos);
        $stmtServ->execute($associadosIds);
        
        $servicosPorAssociado = [];
        while ($serv = $stmtServ->fetch()) {
            $servicosPorAssociado[$serv['associado_id']] = $serv['total'];
        }
        
        // Busca a contagem de documentos
        $sqlDocumentos = "
            SELECT 
                associado_id,
                COUNT(*) as total
            FROM Documentos_Associado
            WHERE associado_id IN ($placeholders)
            GROUP BY associado_id
        ";
        
        $stmtDoc = $pdo->prepare($sqlDocumentos);
        $stmtDoc->execute($associadosIds);
        
        $documentosPorAssociado = [];
        while ($doc = $stmtDoc->fetch()) {
            $documentosPorAssociado[$doc['associado_id']] = $doc['total'];
        }
        
        // Adiciona os dados aos associados
        foreach ($dados as &$associado) {
            $id = $associado['id'];
            
            // Adiciona dependentes
            if (isset($dependentesPorAssociado[$id])) {
                $associado['dependentes'] = $dependentesPorAssociado[$id];
                $associado['total_dependentes'] = count($dependentesPorAssociado[$id]);
            }
            
            // Adiciona total de serviços
            if (isset($servicosPorAssociado[$id])) {
                $associado['total_servicos'] = intval($servicosPorAssociado[$id]);
            }
            
            // Adiciona total de documentos
            if (isset($documentosPorAssociado[$id])) {
                $associado['total_documentos'] = intval($documentosPorAssociado[$id]);
            }
        }
    }
    
    // Libera recursos
    $stmt->closeCursor();
    $stmt = null;
    if (isset($stmtDep)) {
        $stmtDep->closeCursor();
        $stmtDep = null;
    }
    if (isset($stmtServ)) {
        $stmtServ->closeCursor();
        $stmtServ = null;
    }
    if (isset($stmtDoc)) {
        $stmtDoc->closeCursor();
        $stmtDoc = null;
    }
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