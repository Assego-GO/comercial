<?php
/**
 * API para histórico de sócio agregado
 * api/documentos/documentos_agregados_historico.php
 * 
 * Usa o método getHistoricoFluxoAgregado() da classe Documentos
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

try {
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../classes/Database.php';
    require_once '../../classes/Auth.php';
    require_once '../../classes/Documentos.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        throw new Exception('Usuário não autenticado');
    }

    // Aceitar tanto documento_id quanto agregado_id
    $documentoId = isset($_GET['documento_id']) ? intval($_GET['documento_id']) : 0;
    if ($documentoId <= 0) {
        $documentoId = isset($_GET['agregado_id']) ? intval($_GET['agregado_id']) : 0;
    }

    if ($documentoId <= 0) {
        throw new Exception('ID do documento/agregado inválido');
    }

    // Usar a classe Documentos para buscar o histórico
    $documentos = new Documentos();
    $historico = $documentos->getHistoricoFluxoAgregado($documentoId);

    // Buscar dados do agregado para contexto (opcional)
    $agregado = null;
    try {
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Buscar da estrutura unificada: Associados + Militar
        // Nota: associado_titular_id ainda não existe no banco
        $stmt = $db->prepare("
            SELECT 
                a.id,
                a.nome,
                a.cpf,
                a.situacao,
                a.data_cadastro,
                m.corporacao,
                m.patente,
                NULL as socio_titular_nome,
                NULL as titular_cpf
            FROM Associados a
            LEFT JOIN Militar m ON a.id = m.associado_id
            LEFT JOIN Documentos_Associado d ON d.associado_id = a.id
            WHERE m.corporacao = 'Agregados'
            AND (d.id = ? OR d.associado_id = ? OR a.id = ?)
            LIMIT 1
        ");
        $stmt->execute([$documentoId, $documentoId, $documentoId]);
        $agregado = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Se não conseguir buscar o agregado, continuar sem ele
        error_log("Aviso: Não foi possível buscar dados do agregado: " . $e->getMessage());
    }

    echo json_encode([
        'status' => 'success',
        'data' => $historico,
        'agregado' => $agregado
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Erro em documentos_agregados_historico: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}