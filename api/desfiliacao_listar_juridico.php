<?php
/**
 * API para Listar Desfiliações Pendentes - Jurídico
 * api/desfiliacao_listar_juridico.php
 * 
 * Lista desfiliações que aguardam aprovação do departamento Jurídico
 * Apenas desfiliações com servico_id=2 chegam no Jurídico (condicional)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

ob_start('ob_gzhandler');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Permissoes.php';

function jsonResponse($status, $message, $data = null, $code = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse('error', 'Método não permitido', null, 405);
    }

    // Validar autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonResponse('error', 'Não autenticado', null, 401);
    }

    $usuario = $auth->getUser();
    
    // Validar permissões
    $permissoes = Permissoes::getInstance();
    $isJuridico = ($usuario['departamento_id'] == 3); // Departamento Jurídico
    $temPermissao = $permissoes->hasPermission('JURIDICO_DESFILIACAO', 'VIEW') || $isJuridico;
    
    if (!$temPermissao) {
        jsonResponse('error', 'Você não tem permissão para visualizar desfiliações do jurídico', null, 403);
    }

    // Conectar ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Buscar desfiliações pendentes do Jurídico
    // ordem_aprovacao = 2 (Jurídico é a segunda etapa)
    // departamento_id = 3 (ID do Jurídico)
    $sql = "
        SELECT 
            doc.id AS documento_id,
            doc.associado_id,
            doc.caminho_arquivo,
            doc.data_upload,
            doc.funcionario_id AS funcionario_comercial_id,
            a.nome AS associado_nome,
            a.cpf AS associado_cpf,
            func.nome AS funcionario_comercial,
            aprov.id AS aprovacao_id,
            aprov.departamento_id,
            aprov.departamento_nome,
            aprov.ordem_aprovacao,
            aprov.status_aprovacao,
            aprov.data_acao,
            aprov.observacao
        FROM Documentos_Associado doc
        INNER JOIN Associados a ON doc.associado_id = a.id
        LEFT JOIN Funcionarios func ON doc.funcionario_id = func.id
        INNER JOIN Aprovacoes_Desfiliacao aprov ON doc.id = aprov.documento_id
        WHERE doc.tipo_documento = 'ficha_desfiliacao'
        AND aprov.departamento_id = 3  -- Jurídico ID (não 2 que é Financeiro)
        AND aprov.ordem_aprovacao = 2  -- Segunda etapa (após Financeiro)
        AND aprov.status_aprovacao = 'PENDENTE'
        ORDER BY doc.data_upload ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $desfiliações = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar o fluxo completo de cada desfiliação
    $resultado = [];
    foreach ($desfiliações as $desf) {
        // Buscar todas as etapas do fluxo
        $sqlFluxo = "
            SELECT 
                departamento_id,
                departamento_nome,
                ordem_aprovacao,
                status_aprovacao,
                funcionario_nome,
                data_acao,
                observacao
            FROM Aprovacoes_Desfiliacao
            WHERE documento_id = ?
            ORDER BY ordem_aprovacao ASC
        ";
        
        $stmtFluxo = $db->prepare($sqlFluxo);
        $stmtFluxo->execute([$desf['documento_id']]);
        $fluxo = $stmtFluxo->fetchAll(PDO::FETCH_ASSOC);

        $resultado[] = [
            'documento_id' => intval($desf['documento_id']),
            'associado_id' => intval($desf['associado_id']),
            'associado_nome' => $desf['associado_nome'],
            'associado_cpf' => $desf['associado_cpf'],
            'caminho_arquivo' => $desf['caminho_arquivo'],
            'data_upload' => $desf['data_upload'],
            'funcionario_comercial' => $desf['funcionario_comercial'],
            'aprovacao_id' => intval($desf['aprovacao_id']),
            'status_atual' => $desf['status_aprovacao'],
            'fluxo' => $fluxo
        ];
    }

    error_log("[JURIDICO] Listando desfiliações: " . count($resultado) . " encontradas");

    jsonResponse('success', 'Desfiliações carregadas com sucesso', [
        'total_pendentes' => count($resultado),
        'desfiliações' => $resultado
    ], 200);

} catch (Exception $e) {
    error_log("[JURIDICO] Erro ao listar desfiliações: " . $e->getMessage());
    jsonResponse('error', 'Erro no servidor: ' . $e->getMessage(), null, 500);
}
