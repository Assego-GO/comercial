<?php

// ============================================
// api/permissoes/obter_matriz_completa.php
// ============================================
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$permissoes = Permissoes::getInstance();
if (!$permissoes->isSuperAdmin() && !$permissoes->hasPermission('SISTEMA_PERMISSOES', 'VIEW')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar todas as combinações role-recurso-permissão
    $sql = "SELECT 
                rp.role_id,
                rp.recurso_id,
                p.id as permissao_id,
                p.codigo,
                p.nome
            FROM role_permissoes rp
            JOIN permissoes p ON rp.permissao_id = p.id";
    
    $stmt = $db->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar em matriz
    $matriz = [];
    foreach ($result as $row) {
        $key = $row['role_id'] . '_' . $row['recurso_id'];
        if (!isset($matriz[$key])) {
            $matriz[$key] = [];
        }
        $matriz[$key][] = [
            'id' => $row['permissao_id'],
            'codigo' => $row['codigo'],
            'nome' => $row['nome']
        ];
    }
    
    echo json_encode($matriz);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar matriz de permissões']);
}