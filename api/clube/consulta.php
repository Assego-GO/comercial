<?php
/**
 * API para consulta de dados de associados para sistema externo (Hostgator)
 * api/parque/consulta.php
 * 
 * Recebe requisições GET com parâmetro 'ultimo_id' para retornar dados
 * de associados, dependentes e dados militares de forma incremental
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tratamento de preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para resposta JSON padronizada
function jsonResponse($success, $message, $data = null, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Método não permitido. Use GET.', null, 405);
}

try {
    // Incluir dependências
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../classes/Database.php';

    // Conectar ao banco de dados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // ============================================================
    // BUSCAR TODOS OS ASSOCIADOS
    // ============================================================
    
    $sql = "
        SELECT 
            -- Dados principais do associado
            a.id,
            a.nome,
            a.nasc as data_nascimento,
            a.sexo,
            a.rg,
            a.cpf,
            a.email,
            a.telefone,
            a.situacao,
            a.estadoCivil as estado_civil,
            a.escolaridade,
            a.foto,
            a.indicacao,
            a.pre_cadastro,
            a.data_pre_cadastro,
            a.data_aprovacao,
            
            -- Dados do contrato
            c.dataFiliacao as data_filiacao,
            c.dataDesfiliacao as data_desfiliacao,
            
            -- Dados militares
            m.corporacao,
            m.patente,
            m.categoria,
            m.lotacao,
            m.unidade,
            
            -- Dados financeiros
            f.tipoAssociado as tipo_associado,
            f.situacaoFinanceira as situacao_financeira,
            f.vinculoServidor as vinculo_servidor,
            f.localDebito as local_debito,
            f.agencia,
            f.operacao,
            f.contaCorrente as conta_corrente,
            f.doador,
            
            -- Dados de endereço
            e.cep,
            e.endereco,
            e.bairro,
            e.cidade,
            e.numero,
            e.complemento
            
        FROM Associados a
        LEFT JOIN Contrato c ON a.id = c.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Endereco e ON a.id = e.associado_id
        ORDER BY a.id ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se não encontrou associados
    if (empty($associados)) {
        jsonResponse(true, 'Nenhum registro encontrado', [
            'associados' => [],
            'total' => 0
        ]);
    }

    // ============================================================
    // BUSCAR DEPENDENTES DOS ASSOCIADOS RETORNADOS
    // ============================================================
    
    $ids_associados = array_column($associados, 'id');
    $placeholders = str_repeat('?,', count($ids_associados) - 1) . '?';
    
    $sql_dependentes = "
        SELECT 
            d.id as dependente_id,
            d.associado_id,
            d.nome as nome_dependente,
            d.data_nascimento as data_nascimento_dependente,
            d.parentesco,
            d.sexo as sexo_dependente,
            TIMESTAMPDIFF(YEAR, d.data_nascimento, CURDATE()) as idade
        FROM Dependentes d
        WHERE d.associado_id IN ($placeholders)
        ORDER BY d.associado_id, d.id
    ";
    
    $stmt_dep = $db->prepare($sql_dependentes);
    $stmt_dep->execute($ids_associados);
    $dependentes = $stmt_dep->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar dependentes por associado_id
    $dependentes_por_associado = [];
    foreach ($dependentes as $dep) {
        $associado_id = $dep['associado_id'];
        if (!isset($dependentes_por_associado[$associado_id])) {
            $dependentes_por_associado[$associado_id] = [];
        }
        $dependentes_por_associado[$associado_id][] = $dep;
    }
    
    // ============================================================
    // ADICIONAR DEPENDENTES AOS ASSOCIADOS
    // ============================================================
    
    foreach ($associados as &$associado) {
        $associado_id = $associado['id'];
        $associado['dependentes'] = $dependentes_por_associado[$associado_id] ?? [];
        $associado['total_dependentes'] = count($associado['dependentes']);
    }
    unset($associado); // Limpar referência

    // ============================================================
    // PREPARAR RESPOSTA
    // ============================================================
    
    $total_registros = count($associados);
    
    $resposta = [
        'associados' => $associados,
        'total' => $total_registros,
        'total_dependentes' => count($dependentes)
    ];

    jsonResponse(true, 'Todos os dados recuperados com sucesso', $resposta);

} catch (PDOException $e) {
    error_log("Erro no banco de dados (consulta.php): " . $e->getMessage());
    jsonResponse(false, 'Erro ao consultar banco de dados', null, 500);
    
} catch (Exception $e) {
    error_log("Erro geral (consulta.php): " . $e->getMessage());
    jsonResponse(false, 'Erro ao processar requisição: ' . $e->getMessage(), null, 500);
}
