
<?php
// ============================================
// api/permissoes/visualizar_permissoes_funcionario.php - NOVA
// ============================================

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';
require_once '../../classes/Permissoes.php';
require_once '../../classes/Auditoria.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$funcionario_id = $_GET['id'] ?? null;

if (!$funcionario_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do funcionário é obrigatório']);
    exit;
}

$usuarioLogado = $auth->getUser();
$permissoes = Permissoes::getInstance();

// Pode visualizar se:
// 1. É super admin
// 2. Tem permissão de visualização
// 3. É o próprio funcionário
$podeVisualizar = $permissoes->isSuperAdmin() || 
                  $permissoes->hasPermission('SISTEMA_PERMISSOES', 'VIEW') ||
                  $funcionario_id == $usuarioLogado['id'];

if (!$podeVisualizar) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão para visualizar']);
    exit;
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar dados do funcionário
    $stmt = $db->prepare("
        SELECT f.*, d.nome as departamento_nome
        FROM Funcionarios f
        LEFT JOIN Departamentos d ON f.departamento_id = d.id
        WHERE f.id = ?
    ");
    $stmt->execute([$funcionario_id]);
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$funcionario) {
        http_response_code(404);
        echo json_encode(['error' => 'Funcionário não encontrado']);
        exit;
    }
    
    // Buscar roles do funcionário
    $stmt = $db->prepare("
        SELECT 
            fr.*,
            r.codigo as role_codigo,
            r.nome as role_nome,
            r.nivel_hierarquia,
            r.tipo as role_tipo,
            d.nome as departamento_nome,
            f.nome as atribuido_por_nome
        FROM funcionario_roles fr
        JOIN roles r ON fr.role_id = r.id
        LEFT JOIN Departamentos d ON fr.departamento_id = d.id
        LEFT JOIN Funcionarios f ON fr.atribuido_por = f.id
        WHERE fr.funcionario_id = ?
        AND (fr.data_fim IS NULL OR fr.data_fim >= CURDATE())
        ORDER BY fr.principal DESC, r.nivel_hierarquia DESC
    ");
    $stmt->execute([$funcionario_id]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar permissões específicas
    $stmt = $db->prepare("
        SELECT 
            fp.*,
            rec.codigo as recurso_codigo,
            rec.nome as recurso_nome,
            rec.categoria as recurso_categoria,
            p.codigo as permissao_codigo,
            p.nome as permissao_nome,
            f.nome as atribuido_por_nome
        FROM funcionario_permissoes fp
        JOIN recursos rec ON fp.recurso_id = rec.id
        JOIN permissoes p ON fp.permissao_id = p.id
        LEFT JOIN Funcionarios f ON fp.atribuido_por = f.id
        WHERE fp.funcionario_id = ?
        AND (fp.data_fim IS NULL OR fp.data_fim >= CURDATE())
        ORDER BY rec.categoria, rec.nome
    ");
    $stmt->execute([$funcionario_id]);
    $permissoesEspecificas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar delegações recebidas
    $stmt = $db->prepare("
        SELECT 
            d.*,
            f1.nome as delegante_nome,
            r.nome as role_nome,
            rec.nome as recurso_nome
        FROM delegacoes d
        JOIN Funcionarios f1 ON d.delegante_id = f1.id
        LEFT JOIN roles r ON d.role_id = r.id
        LEFT JOIN recursos rec ON d.recurso_id = rec.id
        WHERE d.delegado_id = ?
        AND d.ativo = 1
        AND NOW() BETWEEN d.data_inicio AND d.data_fim
        ORDER BY d.data_inicio DESC
    ");
    $stmt->execute([$funcionario_id]);
    $delegacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Compilar todas as permissões efetivas
    $permissoesEfetivas = [];
    
    // 1. Permissões via roles
    foreach ($roles as $role) {
        $stmt = $db->prepare("
            SELECT 
                rec.codigo as recurso,
                rec.nome as recurso_nome,
                p.codigo as permissao
            FROM role_permissoes rp
            JOIN recursos rec ON rp.recurso_id = rec.id
            JOIN permissoes p ON rp.permissao_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$role['role_id']]);
        
        while ($perm = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $perm['recurso'] . '::' . $perm['permissao'];
            if (!isset($permissoesEfetivas[$key])) {
                $permissoesEfetivas[$key] = [
                    'recurso' => $perm['recurso'],
                    'recurso_nome' => $perm['recurso_nome'],
                    'permissao' => $perm['permissao'],
                    'origem' => 'Role: ' . $role['role_nome'],
                    'tipo' => 'GRANT'
                ];
            }
        }
    }
    
    // 2. Aplicar permissões específicas (GRANT e DENY)
    foreach ($permissoesEspecificas as $perm) {
        $key = $perm['recurso_codigo'] . '::' . $perm['permissao_codigo'];
        
        if ($perm['tipo'] === 'DENY') {
            // DENY sempre sobrescreve
            $permissoesEfetivas[$key] = [
                'recurso' => $perm['recurso_codigo'],
                'recurso_nome' => $perm['recurso_nome'],
                'permissao' => $perm['permissao_codigo'],
                'origem' => 'Permissão Específica',
                'tipo' => 'DENY'
            ];
        } elseif (!isset($permissoesEfetivas[$key]) || $permissoesEfetivas[$key]['tipo'] !== 'DENY') {
            // GRANT só adiciona se não existir ou não for DENY
            $permissoesEfetivas[$key] = [
                'recurso' => $perm['recurso_codigo'],
                'recurso_nome' => $perm['recurso_nome'],
                'permissao' => $perm['permissao_codigo'],
                'origem' => 'Permissão Específica',
                'tipo' => 'GRANT'
            ];
        }
    }
    
    // Registrar visualização na auditoria
    $auditoria = new Auditoria();
    $auditoria->registrarAcesso('funcionario_permissoes', $funcionario_id, 'VISUALIZAR_PERMISSOES');
    
    echo json_encode([
        'funcionario' => $funcionario,
        'roles' => $roles,
        'permissoes_especificas' => $permissoesEspecificas,
        'delegacoes' => $delegacoes,
        'permissoes_efetivas' => array_values($permissoesEfetivas),
        'total_permissoes' => count(array_filter($permissoesEfetivas, fn($p) => $p['tipo'] === 'GRANT'))
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar permissões: ' . $e->getMessage()]);
}
?>