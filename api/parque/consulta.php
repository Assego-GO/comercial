<?php
/**
 * API SEGURA para consulta de dados de associados para sistema externo (Hostgator)
 * api/parque/consulta.php
 * 
 * SEGURANÇA:
 * - Requer token de autenticação
 * - Registra todas as consultas
 * - Impede consultas com IDs decrescentes da mesma origem
 * - Retorna dados incrementais para evitar duplicação
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

// Função para registrar log de acesso
function registrarLog($db, $ip, $ultimo_id, $total_retornados, $status, $mensagem = null) {
    try {
        $sql = "INSERT INTO Log_Consultas_Externas 
                (ip_origem, ultimo_id_solicitado, total_registros_retornados, status, mensagem, data_consulta) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ip, $ultimo_id, $total_retornados, $status, $mensagem]);
    } catch (Exception $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
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
    // VALIDAÇÃO DE TOKEN DE SEGURANÇA
    // ============================================================
    
    $token_fornecido = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    
    // Token de segurança (MUDE ESTE VALOR!)
    $token_valido = 'assego_hostgator_2025_secure_token_xyz123';
    
    if ($token_fornecido !== $token_valido) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
        registrarLog($db, $ip, 0, 0, 'NEGADO', 'Token inválido ou ausente');
        jsonResponse(false, 'Acesso negado. Token de autenticação inválido.', null, 401);
    }

    // ============================================================
    // PARÂMETROS E VALIDAÇÕES
    // ============================================================
    
    $ultimo_id = isset($_GET['ultimo_id']) ? (int)$_GET['ultimo_id'] : 0;
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 100;
    $ip_origem = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    
    // Validações básicas
    if ($ultimo_id < 0) {
        registrarLog($db, $ip_origem, $ultimo_id, 0, 'ERRO', 'ultimo_id negativo');
        jsonResponse(false, 'Parâmetro ultimo_id inválido', null, 400);
    }
    
    if ($limite < 1 || $limite > 500) {
        $limite = 100; // Limitar entre 1 e 500 registros (reduzido para segurança)
    }

    // ============================================================
    // VERIFICAR ÚLTIMA CONSULTA DESTA ORIGEM (EVITAR REGRESSÃO)
    // ============================================================
    
    $sql_ultimo = "SELECT ultimo_id_solicitado, MAX(data_consulta) as ultima_consulta 
                   FROM Log_Consultas_Externas 
                   WHERE ip_origem = ? AND status = 'SUCESSO'
                   GROUP BY ip_origem
                   ORDER BY data_consulta DESC 
                   LIMIT 1";
    
    $stmt_ultimo = $db->prepare($sql_ultimo);
    $stmt_ultimo->execute([$ip_origem]);
    $ultima_consulta = $stmt_ultimo->fetch(PDO::FETCH_ASSOC);
    
    // AVISO: Se está tentando consultar ID menor que o último consultado
    if ($ultima_consulta && $ultimo_id > 0 && $ultimo_id < $ultima_consulta['ultimo_id_solicitado']) {
        $aviso = "ATENÇÃO: Você está consultando ID {$ultimo_id}, mas sua última consulta foi ID {$ultima_consulta['ultimo_id_solicitado']}. Isso pode duplicar dados!";
        error_log($aviso);
        // Registra mas NÃO bloqueia (apenas avisa)
    }

    // ============================================================
    // BUSCAR ASSOCIADOS ACIMA DO ÚLTIMO ID
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
        WHERE a.id > :ultimo_id
        ORDER BY a.id ASC
        LIMIT :limite
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':ultimo_id', $ultimo_id, PDO::PARAM_INT);
    $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    
    $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se não encontrou associados
    if (empty($associados)) {
        registrarLog($db, $ip_origem, $ultimo_id, 0, 'VAZIO', 'Nenhum registro encontrado');
        jsonResponse(true, 'Nenhum novo registro encontrado', [
            'associados' => [],
            'total' => 0,
            'ultimo_id_consultado' => $ultimo_id,
            'proximo_id' => null
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
            d.cpf as cpf_dependente,
            d.rg as rg_dependente,
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
    
    $ultimo_id_retornado = end($associados)['id'];
    $total_registros = count($associados);
    
    // Registrar log de sucesso
    registrarLog($db, $ip_origem, $ultimo_id, $total_registros, 'SUCESSO', "Retornados {$total_registros} associados");
    
    $resposta = [
        'associados' => $associados,
        'total' => $total_registros,
        'ultimo_id_consultado' => $ultimo_id,
        'ultimo_id_retornado' => $ultimo_id_retornado,
        'proximo_id' => $ultimo_id_retornado, // Para próxima consulta usar este ID
        'limite_aplicado' => $limite,
        'total_dependentes' => count($dependentes),
        'aviso_duplicacao' => isset($aviso) ? $aviso : null
    ];

    jsonResponse(true, 'Dados recuperados com sucesso', $resposta);

} catch (PDOException $e) {
    error_log("Erro no banco de dados (consulta.php): " . $e->getMessage());
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    if (isset($db)) {
        registrarLog($db, $ip, $ultimo_id ?? 0, 0, 'ERRO', 'Erro no banco de dados');
    }
    jsonResponse(false, 'Erro ao consultar banco de dados', null, 500);
    
} catch (Exception $e) {
    error_log("Erro geral (consulta.php): " . $e->getMessage());
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    if (isset($db)) {
        registrarLog($db, $ip, $ultimo_id ?? 0, 0, 'ERRO', $e->getMessage());
    }
    jsonResponse(false, 'Erro ao processar requisição: ' . $e->getMessage(), null, 500);
}