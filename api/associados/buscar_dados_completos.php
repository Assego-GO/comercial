<?php
/**
 * API para buscar dados completos do associado
 * api/associados/buscar_dados_completos.php
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

header('Content-Type: application/json');

try {
    $auth = new Auth();
    
    if (!$auth->isLoggedIn()) {
        throw new Exception('Não autorizado');
    }
    
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('ID do associado não informado');
    }
    
    $associadoId = intval($_GET['id']);
    
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar dados completos
    $stmt = $db->prepare("
        SELECT 
            a.*,
            e.cep,
            e.endereco,
            e.bairro,
            e.cidade,
            e.numero,
            e.complemento,
            m.corporacao,
            m.patente,
            m.categoria,
            m.lotacao,
            m.unidade,
            f.tipoAssociado,
            f.situacaoFinanceira,
            f.agencia,
            f.operacao,
            f.contaCorrente
        FROM Associados a
        LEFT JOIN Endereco e ON a.id = e.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        WHERE a.id = ?
    ");
    
    $stmt->execute([$associadoId]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dados) {
        throw new Exception('Associado não encontrado');
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $dados
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}   