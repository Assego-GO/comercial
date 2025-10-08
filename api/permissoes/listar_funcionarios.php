<?php

// ============================================
// api/permissoes/listar_funcionarios.php
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
    
    // Buscar funcionários com suas roles
    $sql = "SELECT 
                f.id,
                f.nome,
                f.email,
                f.cargo,
                d.nome as departamento,
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        '{\"id\":', r.id, 
                        ',\"codigo\":\"', r.codigo, '\"',
                        ',\"nome\":\"', r.nome, '\"}'
                    )
                ) as roles_json,
                (SELECT COUNT(*) FROM funcionario_permissoes fp 
                 WHERE fp.funcionario_id = f.id 
                 AND (fp.data_fim IS NULL OR fp.data_fim >= CURDATE())) as permissoes_especiais,
                (SELECT COUNT(*) FROM delegacoes del 
                 WHERE del.delegado_id = f.id 
                 AND del.ativo = 1
                 AND NOW() BETWEEN del.data_inicio AND del.data_fim) as delegacoes_ativas
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            LEFT JOIN funcionario_roles fr ON f.id = fr.funcionario_id 
                AND (fr.data_fim IS NULL OR fr.data_fim >= CURDATE())
            LEFT JOIN roles r ON fr.role_id = r.id
            WHERE f.ativo = 1
            GROUP BY f.id
            ORDER BY f.nome";
    
    $stmt = $db->query($sql);
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse roles JSON
    foreach ($funcionarios as &$func) {
        if ($func['roles_json']) {
            $rolesArray = explode('},{', trim($func['roles_json'], '{}'));
            $func['roles'] = [];
            foreach ($rolesArray as $roleStr) {
                if (!empty($roleStr)) {
                    $func['roles'][] = json_decode('{' . $roleStr . '}', true);
                }
            }
        } else {
            $func['roles'] = [];
        }
        unset($func['roles_json']);
    }
    
    echo json_encode($funcionarios);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar funcionários: ' . $e->getMessage()]);
}