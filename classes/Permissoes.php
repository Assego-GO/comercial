<?php
/**
 * Sistema de Gerenciamento de Permissões RBAC + ACL
 * classes/Permissoes.php
 * 
 * Sistema híbrido com:
 * - RBAC (Role-Based Access Control)
 * - ACL (Access Control List)
 * - Delegações temporárias
 * - Cache de permissões
 * - Políticas condicionais
 */

require_once 'Database.php';

class Permissoes {
    
    private static $instance = null;
    private $db;
    private $funcionarioId;
    private $tipoUsuario;
    private $cache = [];
    private $roles = null;
    private $permissions = null;
    private $useCache = true;
    private $cacheLifetime = 3600; // 1 hora
    
    /**
     * Constructor privado para Singleton
     */
    private function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Pegar dados da sessão considerando impersonation
        if (isset($_SESSION['impersonate_id'])) {
            $this->funcionarioId = $_SESSION['impersonate_id'];
            $this->tipoUsuario = $_SESSION['impersonate_tipo_usuario'] ?? 'funcionario';
        } else {
            $this->funcionarioId = $_SESSION['funcionario_id'] ?? null;
            $this->tipoUsuario = $_SESSION['tipo_usuario'] ?? 'funcionario';
        }
        
        if ($this->funcionarioId && $this->useCache) {
            $this->loadFromCache();
        }
    }
    
    /**
     * Obter instância única (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Método estático para verificar permissão
     */
    public static function tem($recurso, $permissao = 'VIEW') {
        return self::getInstance()->hasPermission($recurso, $permissao);
    }
    
    /**
     * Verificar múltiplas permissões (precisa ter todas)
     */
    public static function temTodas(array $permissoes) {
        $instance = self::getInstance();
        foreach ($permissoes as $recurso => $permissao) {
            if (is_numeric($recurso)) {
                // Se for array simples, assume VIEW
                if (!$instance->hasPermission($permissao, 'VIEW')) {
                    return false;
                }
            } else {
                if (!$instance->hasPermission($recurso, $permissao)) {
                    return false;
                }
            }
        }
        return true;
    }
    
    /**
     * Verificar se tem alguma das permissões
     */
    public static function temAlguma(array $permissoes) {
        $instance = self::getInstance();
        foreach ($permissoes as $recurso => $permissao) {
            if (is_numeric($recurso)) {
                if ($instance->hasPermission($permissao, 'VIEW')) {
                    return true;
                }
            } else {
                if ($instance->hasPermission($recurso, $permissao)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Verifica se pode impersonar
     */
    public static function podeImpersonar() {
        $instance = self::getInstance();
        return $instance->hasRole('SUPER_ADMIN') || 
               $instance->hasRole('PRESIDENTE') ||
               $instance->hasPermission('SISTEMA_IMPERSONAR', 'FULL');
    }
    
    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public function hasPermission($recurso, $permissao = 'VIEW') {
        // Super Admin tem acesso total
        if ($this->isSuperAdmin()) {
            $this->logAccess($recurso, $permissao, 'PERMITIDO', 'Super Admin');
            return true;
        }
        
        // Converter código do recurso para ID se necessário
        $recursoId = $this->getRecursoId($recurso);
        if (!$recursoId) {
            $this->logAccess($recurso, $permissao, 'NEGADO', 'Recurso não encontrado');
            return false;
        }
        
        $permissaoId = $this->getPermissaoId($permissao);
        if (!$permissaoId) {
            $this->logAccess($recurso, $permissao, 'NEGADO', 'Permissão inválida');
            return false;
        }
        
        // Verificar DENY específicos primeiro
        if ($this->hasDenyPermission($recursoId, $permissaoId)) {
            $this->logAccess($recurso, $permissao, 'NEGADO', 'Permissão negada explicitamente');
            return false;
        }
        
        // Verificar delegações ativas
        if ($this->hasDelegatedPermission($recursoId, $permissaoId)) {
            $this->logAccess($recurso, $permissao, 'PERMITIDO', 'Delegação ativa');
            return true;
        }
        
        // Verificar permissões por role
        if ($this->hasRolePermission($recursoId, $permissaoId)) {
            $this->logAccess($recurso, $permissao, 'PERMITIDO', 'Permissão via role');
            return true;
        }
        
        // Verificar permissões específicas GRANT
        if ($this->hasGrantPermission($recursoId, $permissaoId)) {
            $this->logAccess($recurso, $permissao, 'PERMITIDO', 'Permissão específica');
            return true;
        }
        
        // Verificar políticas de acesso
        if (!$this->checkPolicies($recursoId)) {
            $this->logAccess($recurso, $permissao, 'NEGADO', 'Política de acesso');
            return false;
        }
        
        $this->logAccess($recurso, $permissao, 'NEGADO', 'Sem permissão');
        return false;
    }
    
    /**
     * Verifica se tem role específica
     */
    public function hasRole($roleCode) {
        $roles = $this->getUserRoles();
        foreach ($roles as $role) {
            if ($role['codigo'] === $roleCode) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verifica se é Super Admin
     */
    public function isSuperAdmin() {
        // Verificar cache primeiro
        if (isset($this->cache['is_super_admin'])) {
            return $this->cache['is_super_admin'];
        }
        
        $result = $this->hasRole('SUPER_ADMIN');
        $this->cache['is_super_admin'] = $result;
        return $result;
    }
    
    /**
     * Verifica se é Presidente
     */
    public function isPresidente() {
        return $this->hasRole('PRESIDENTE');
    }
    
    /**
     * Verifica se é Diretor
     */
    public function isDiretor($departamentoId = null) {
        $roles = $this->getUserRoles();
        
        foreach ($roles as $role) {
            if ($role['codigo'] === 'DIRETOR') {
                if ($departamentoId === null || $role['departamento_id'] == $departamentoId) {
                    return true;
                }
            }
        }
        
        // Verificar também associados-diretores
        if ($this->tipoUsuario === 'associado') {
            return true; // Associados só logam se is_diretor = 1
        }
        
        return false;
    }
    
    /**
     * Verifica se é Subdiretor
     */
    public function isSubdiretor($departamentoId = null) {
        $roles = $this->getUserRoles();
        
        foreach ($roles as $role) {
            if ($role['codigo'] === 'SUBDIRETOR') {
                if ($departamentoId === null || $role['departamento_id'] == $departamentoId) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Obter todas as roles do usuário
     */
    private function getUserRoles() {
        if ($this->roles !== null && $this->useCache) {
            return $this->roles;
        }
        
        try {
            $sql = "
                SELECT 
                    r.id,
                    r.codigo,
                    r.nome,
                    r.nivel_hierarquia,
                    r.tipo,
                    fr.departamento_id,
                    fr.principal,
                    d.nome as departamento_nome
                FROM funcionario_roles fr
                INNER JOIN roles r ON fr.role_id = r.id
                LEFT JOIN Departamentos d ON fr.departamento_id = d.id
                WHERE fr.funcionario_id = ?
                AND (fr.data_fim IS NULL OR fr.data_fim >= CURDATE())
                ORDER BY r.nivel_hierarquia DESC, fr.principal DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->funcionarioId]);
            $this->roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->roles;
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar roles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verificar permissão negada específica
     */
    private function hasDenyPermission($recursoId, $permissaoId) {
        try {
            $sql = "
                SELECT COUNT(*) 
                FROM funcionario_permissoes
                WHERE funcionario_id = ?
                AND recurso_id = ?
                AND permissao_id = ?
                AND tipo = 'DENY'
                AND (data_fim IS NULL OR data_fim >= CURDATE())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->funcionarioId, $recursoId, $permissaoId]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar DENY: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar permissão concedida específica
     */
    private function hasGrantPermission($recursoId, $permissaoId) {
        try {
            $sql = "
                SELECT COUNT(*) 
                FROM funcionario_permissoes
                WHERE funcionario_id = ?
                AND recurso_id = ?
                AND permissao_id = ?
                AND tipo = 'GRANT'
                AND (data_fim IS NULL OR data_fim >= CURDATE())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->funcionarioId, $recursoId, $permissaoId]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar GRANT: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar permissão via roles
     */
    private function hasRolePermission($recursoId, $permissaoId) {
        try {
            $roles = $this->getUserRoles();
            if (empty($roles)) {
                return false;
            }
            
            $roleIds = array_column($roles, 'id');
            $placeholders = str_repeat('?,', count($roleIds) - 1) . '?';
            
            $sql = "
                SELECT COUNT(*)
                FROM role_permissoes
                WHERE role_id IN ($placeholders)
                AND recurso_id = ?
                AND permissao_id = ?
            ";
            
            $params = array_merge($roleIds, [$recursoId, $permissaoId]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar permissão via role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar delegações ativas
     */
    private function hasDelegatedPermission($recursoId, $permissaoId) {
        try {
            $sql = "
                SELECT COUNT(*)
                FROM delegacoes d
                LEFT JOIN role_permissoes rp ON d.role_id = rp.role_id
                WHERE d.delegado_id = ?
                AND d.ativo = 1
                AND NOW() BETWEEN d.data_inicio AND d.data_fim
                AND (
                    (d.recurso_id IS NULL OR d.recurso_id = ?)
                    AND (d.role_id IS NULL OR (rp.recurso_id = ? AND rp.permissao_id = ?))
                )
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $this->funcionarioId,
                $recursoId,
                $recursoId,
                $permissaoId
            ]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar delegação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar políticas de acesso
     */
    private function checkPolicies($recursoId) {
        try {
            // Buscar políticas aplicáveis
            $sql = "
                SELECT p.tipo, p.regras
                FROM politicas_acesso p
                INNER JOIN politica_aplicacoes pa ON p.id = pa.politica_id
                WHERE p.ativo = 1 AND pa.ativo = 1
                AND (
                    (pa.tipo_alvo = 'FUNCIONARIO' AND pa.alvo_id = ?)
                    OR (pa.tipo_alvo = 'ROLE' AND pa.alvo_id IN (
                        SELECT role_id FROM funcionario_roles 
                        WHERE funcionario_id = ? 
                        AND (data_fim IS NULL OR data_fim >= CURDATE())
                    ))
                    OR (pa.tipo_alvo = 'DEPARTAMENTO' AND pa.alvo_id = ?)
                )
                ORDER BY p.prioridade DESC
            ";
            
            $departamentoId = $_SESSION['departamento_id'] ?? null;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $this->funcionarioId,
                $this->funcionarioId,
                $departamentoId
            ]);
            
            $politicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($politicas as $politica) {
                $regras = json_decode($politica['regras'], true);
                
                switch ($politica['tipo']) {
                    case 'HORARIO':
                        if (!$this->checkHorarioPolicy($regras)) {
                            return false;
                        }
                        break;
                        
                    case 'IP':
                        if (!$this->checkIPPolicy($regras)) {
                            return false;
                        }
                        break;
                        
                    case 'DEPARTAMENTO':
                        if (!$this->checkDepartamentoPolicy($regras)) {
                            return false;
                        }
                        break;
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar políticas: " . $e->getMessage());
            return true; // Em caso de erro, permitir acesso
        }
    }
    
    /**
     * Verificar política de horário
     */
    private function checkHorarioPolicy($regras) {
        $horaAtual = date('H:i');
        $diaAtual = date('N'); // 1 = Segunda, 7 = Domingo
        
        if (isset($regras['dias_permitidos'])) {
            if (!in_array($diaAtual, $regras['dias_permitidos'])) {
                return false;
            }
        }
        
        if (isset($regras['hora_inicio']) && isset($regras['hora_fim'])) {
            if ($horaAtual < $regras['hora_inicio'] || $horaAtual > $regras['hora_fim']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Verificar política de IP
     */
    private function checkIPPolicy($regras) {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (isset($regras['ips_permitidos'])) {
            return in_array($clientIP, $regras['ips_permitidos']);
        }
        
        if (isset($regras['ranges_permitidos'])) {
            foreach ($regras['ranges_permitidos'] as $range) {
                if ($this->ipInRange($clientIP, $range)) {
                    return true;
                }
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar política de departamento
     */
    private function checkDepartamentoPolicy($regras) {
        $departamentoId = $_SESSION['departamento_id'] ?? null;
        
        if (isset($regras['departamentos_permitidos'])) {
            return in_array($departamentoId, $regras['departamentos_permitidos']);
        }
        
        return true;
    }
    
    /**
     * Obter ID do recurso pelo código
     */
    private function getRecursoId($codigo) {
        // Cache de recursos
        if (isset($this->cache['recursos'][$codigo])) {
            return $this->cache['recursos'][$codigo];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id FROM recursos WHERE codigo = ? AND ativo = 1");
            $stmt->execute([$codigo]);
            $id = $stmt->fetchColumn();
            
            if ($id) {
                $this->cache['recursos'][$codigo] = $id;
            }
            
            return $id;
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar recurso: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obter ID da permissão pelo código
     */
    private function getPermissaoId($codigo) {
        // Cache de permissões
        if (isset($this->cache['permissoes'][$codigo])) {
            return $this->cache['permissoes'][$codigo];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id FROM permissoes WHERE codigo = ?");
            $stmt->execute([$codigo]);
            $id = $stmt->fetchColumn();
            
            if ($id) {
                $this->cache['permissoes'][$codigo] = $id;
            }
            
            return $id;
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar permissão: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Carregar cache de permissões
     */
    private function loadFromCache() {
        try {
            $stmt = $this->db->prepare("
                SELECT permissoes_json, hash_permissoes, expira_em
                FROM cache_permissoes
                WHERE funcionario_id = ?
                AND (expira_em IS NULL OR expira_em > NOW())
            ");
            $stmt->execute([$this->funcionarioId]);
            $cache = $stmt->fetch();
            
            if ($cache) {
                $this->permissions = json_decode($cache['permissoes_json'], true);
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Erro ao carregar cache: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Salvar cache de permissões
     */
    public function saveToCache() {
        try {
            $permissoes = $this->getAllPermissions();
            $json = json_encode($permissoes);
            $hash = md5($json);
            $expira = date('Y-m-d H:i:s', time() + $this->cacheLifetime);
            
            $stmt = $this->db->prepare("
                INSERT INTO cache_permissoes (funcionario_id, permissoes_json, hash_permissoes, expira_em)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                permissoes_json = VALUES(permissoes_json),
                hash_permissoes = VALUES(hash_permissoes),
                expira_em = VALUES(expira_em),
                criado_em = NOW()
            ");
            
            $stmt->execute([$this->funcionarioId, $json, $hash, $expira]);
            
        } catch (PDOException $e) {
            error_log("Erro ao salvar cache: " . $e->getMessage());
        }
    }
    
    /**
     * Invalidar cache de permissões
     */
    public static function invalidateCache($funcionarioId = null) {
        try {
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            if ($funcionarioId) {
                $stmt = $db->prepare("DELETE FROM cache_permissoes WHERE funcionario_id = ?");
                $stmt->execute([$funcionarioId]);
            } else {
                $db->exec("DELETE FROM cache_permissoes WHERE 1=1");
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao invalidar cache: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar acesso (log)
     */
    private function logAccess($recurso, $permissao, $resultado, $motivo = null) {
        try {
            // Limitar logs para evitar sobrecarga
            if (rand(1, 100) > 10) { // Log apenas 10% dos acessos bem-sucedidos
                if ($resultado === 'PERMITIDO') {
                    return;
                }
            }
            
            $recursoId = is_numeric($recurso) ? $recurso : $this->getRecursoId($recurso);
            $permissaoId = is_numeric($permissao) ? $permissao : $this->getPermissaoId($permissao);
            
            $stmt = $this->db->prepare("
                INSERT INTO log_acessos (
                    funcionario_id, recurso_id, permissao_id, 
                    acao, resultado, motivo_negacao, 
                    ip, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->funcionarioId,
                $recursoId,
                $permissaoId,
                $recurso . '::' . $permissao,
                $resultado,
                $motivo,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar log de acesso: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar acesso negado (para análise)
     */
    public static function registrarAcessoNegado($permissao, $url) {
        try {
            $instance = self::getInstance();
            $instance->logAccess($url, $permissao, 'NEGADO', 'Acesso negado via checkPermissao');
        } catch (Exception $e) {
            error_log("Erro ao registrar acesso negado: " . $e->getMessage());
        }
    }
    
    /**
     * Obter todas as permissões do usuário (para cache)
     */
    private function getAllPermissions() {
        $permissoes = [];
        
        try {
            // Buscar todas as permissões via roles
            $sql = "
                SELECT DISTINCT
                    rec.codigo as recurso,
                    p.codigo as permissao,
                    'ROLE' as origem
                FROM funcionario_roles fr
                INNER JOIN role_permissoes rp ON fr.role_id = rp.role_id
                INNER JOIN recursos rec ON rp.recurso_id = rec.id
                INNER JOIN permissoes p ON rp.permissao_id = p.id
                WHERE fr.funcionario_id = ?
                AND (fr.data_fim IS NULL OR fr.data_fim >= CURDATE())
                AND rec.ativo = 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->funcionarioId]);
            $rolePerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rolePerms as $perm) {
                $permissoes[$perm['recurso']][$perm['permissao']] = true;
            }
            
            // Buscar permissões específicas GRANT
            $sql = "
                SELECT 
                    rec.codigo as recurso,
                    p.codigo as permissao
                FROM funcionario_permissoes fp
                INNER JOIN recursos rec ON fp.recurso_id = rec.id
                INNER JOIN permissoes p ON fp.permissao_id = p.id
                WHERE fp.funcionario_id = ?
                AND fp.tipo = 'GRANT'
                AND (fp.data_fim IS NULL OR fp.data_fim >= CURDATE())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->funcionarioId]);
            $grants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($grants as $grant) {
                $permissoes[$grant['recurso']][$grant['permissao']] = true;
            }
            
            // Remover permissões DENY
            $sql = "
                SELECT 
                    rec.codigo as recurso,
                    p.codigo as permissao
                FROM funcionario_permissoes fp
                INNER JOIN recursos rec ON fp.recurso_id = rec.id
                INNER JOIN permissoes p ON fp.permissao_id = p.id
                WHERE fp.funcionario_id = ?
                AND fp.tipo = 'DENY'
                AND (fp.data_fim IS NULL OR fp.data_fim >= CURDATE())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->funcionarioId]);
            $denies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($denies as $deny) {
                unset($permissoes[$deny['recurso']][$deny['permissao']]);
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar todas as permissões: " . $e->getMessage());
        }
        
        return $permissoes;
    }
    
    /**
     * Verificar se IP está em um range
     */
    private function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) == $subnet;
    }
    
    /**
     * Obter menu baseado em permissões
     */
    public static function getMenu() {
        $instance = self::getInstance();
        $menu = [];
        
        try {
            $sql = "
                SELECT DISTINCT
                    r.id,
                    r.codigo,
                    r.nome,
                    r.categoria,
                    r.modulo,
                    r.tipo,
                    r.rota,
                    r.icone,
                    r.ordem
                FROM recursos r
                WHERE r.tipo IN ('MENU', 'PAGINA')
                AND r.ativo = 1
                ORDER BY r.categoria, r.ordem, r.nome
            ";
            
            $stmt = $instance->db->prepare($sql);
            $stmt->execute();
            $recursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recursos as $recurso) {
                if ($instance->hasPermission($recurso['codigo'], 'VIEW')) {
                    if (!isset($menu[$recurso['categoria']])) {
                        $menu[$recurso['categoria']] = [];
                    }
                    $menu[$recurso['categoria']][] = $recurso;
                }
            }
            
        } catch (PDOException $e) {
            error_log("Erro ao gerar menu: " . $e->getMessage());
        }
        
        return $menu;
    }
    
    /**
     * Atribuir role a um funcionário
     */
    public static function atribuirRole($funcionarioId, $roleCode, $departamentoId = null, $atribuidoPor = null) {
        try {
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            // Buscar ID da role
            $stmt = $db->prepare("SELECT id FROM roles WHERE codigo = ?");
            $stmt->execute([$roleCode]);
            $roleId = $stmt->fetchColumn();
            
            if (!$roleId) {
                throw new Exception("Role não encontrada: $roleCode");
            }
            
            // Inserir atribuição
            $stmt = $db->prepare("
                INSERT INTO funcionario_roles (
                    funcionario_id, role_id, departamento_id, 
                    atribuido_por, data_inicio
                ) VALUES (?, ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE
                data_fim = NULL,
                atribuido_por = VALUES(atribuido_por)
            ");
            
            $stmt->execute([
                $funcionarioId,
                $roleId,
                $departamentoId,
                $atribuidoPor ?? $_SESSION['funcionario_id'] ?? null
            ]);
            
            // Invalidar cache
            self::invalidateCache($funcionarioId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao atribuir role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remover role de um funcionário
     */
    public static function removerRole($funcionarioId, $roleCode, $departamentoId = null) {
        try {
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            // Buscar ID da role
            $stmt = $db->prepare("SELECT id FROM roles WHERE codigo = ?");
            $stmt->execute([$roleCode]);
            $roleId = $stmt->fetchColumn();
            
            if (!$roleId) {
                return false;
            }
            
            // Atualizar data_fim
            $sql = "
                UPDATE funcionario_roles 
                SET data_fim = CURDATE()
                WHERE funcionario_id = ?
                AND role_id = ?
            ";
            
            $params = [$funcionarioId, $roleId];
            
            if ($departamentoId !== null) {
                $sql .= " AND departamento_id = ?";
                $params[] = $departamentoId;
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Invalidar cache
            self::invalidateCache($funcionarioId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao remover role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar delegação temporária
     */
    public static function criarDelegacao($deleganteId, $delegadoId, $dataInicio, $dataFim, $motivo, $roleId = null, $recursoId = null) {
        try {
            $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO delegacoes (
                    delegante_id, delegado_id, role_id, recurso_id,
                    data_inicio, data_fim, motivo, ativo
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $deleganteId,
                $delegadoId,
                $roleId,
                $recursoId,
                $dataInicio,
                $dataFim,
                $motivo
            ]);
            
            // Invalidar cache do delegado
            self::invalidateCache($delegadoId);
            
            return $db->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Erro ao criar delegação: " . $e->getMessage());
            return false;
        }
    }
}

