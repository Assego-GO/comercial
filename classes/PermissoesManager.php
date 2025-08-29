<?php
/**
 * Sistema de Permissões Hierárquico com Delegação
 * classes/PermissoesManager.php
 */

require_once 'Database.php';
require_once 'Permissoes.php';

class PermissoesManager {
    
    private $db;
    private static $instance = null;
    
    // Cache de permissões para performance
    private $cachePermissoes = [];
    
    private function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $this->inicializarTabelas();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializa as tabelas de permissões se não existirem
     */
    private function inicializarTabelas() {
        try {
            // Tabela de delegações de permissões
            $sql = "CREATE TABLE IF NOT EXISTS Permissoes_Delegadas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_origem INT NOT NULL COMMENT 'Quem delegou',
                funcionario_destino INT NOT NULL COMMENT 'Quem recebeu',
                permissao VARCHAR(100) NOT NULL,
                data_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
                data_fim DATETIME DEFAULT NULL,
                motivo TEXT,
                ativa BOOLEAN DEFAULT TRUE,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                revogado_em TIMESTAMP NULL,
                revogado_por INT DEFAULT NULL,
                FOREIGN KEY (funcionario_origem) REFERENCES Funcionarios(id),
                FOREIGN KEY (funcionario_destino) REFERENCES Funcionarios(id),
                FOREIGN KEY (revogado_por) REFERENCES Funcionarios(id),
                INDEX idx_destino_ativa (funcionario_destino, ativa),
                INDEX idx_permissao (permissao),
                INDEX idx_data_fim (data_fim)
            )";
            $this->db->exec($sql);
            
            // Tabela de grupos de permissões
            $sql = "CREATE TABLE IF NOT EXISTS Grupos_Permissoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL UNIQUE,
                descricao TEXT,
                departamento_id INT DEFAULT NULL,
                nivel_hierarquia INT DEFAULT 0 COMMENT 'Nivel hierarquico do grupo',
                ativo BOOLEAN DEFAULT TRUE,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (departamento_id) REFERENCES Departamentos(id)
            )";
            $this->db->exec($sql);
            
            // Relação entre grupos e permissões
            $sql = "CREATE TABLE IF NOT EXISTS Grupos_Permissoes_Itens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                grupo_id INT NOT NULL,
                permissao VARCHAR(100) NOT NULL,
                FOREIGN KEY (grupo_id) REFERENCES Grupos_Permissoes(id) ON DELETE CASCADE,
                UNIQUE KEY uk_grupo_permissao (grupo_id, permissao)
            )";
            $this->db->exec($sql);
            
            // Relação entre funcionários e grupos
            $sql = "CREATE TABLE IF NOT EXISTS Funcionarios_Grupos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                grupo_id INT NOT NULL,
                atribuido_por INT,
                data_atribuicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (funcionario_id) REFERENCES Funcionarios(id),
                FOREIGN KEY (grupo_id) REFERENCES Grupos_Permissoes(id),
                FOREIGN KEY (atribuido_por) REFERENCES Funcionarios(id),
                UNIQUE KEY uk_func_grupo (funcionario_id, grupo_id)
            )";
            $this->db->exec($sql);
            
            // Tabela de permissões temporárias
            $sql = "CREATE TABLE IF NOT EXISTS Permissoes_Temporarias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                funcionario_id INT NOT NULL,
                permissao VARCHAR(100) NOT NULL,
                data_inicio DATETIME NOT NULL,
                data_fim DATETIME NOT NULL,
                motivo TEXT,
                concedido_por INT,
                ativa BOOLEAN DEFAULT TRUE,
                criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (funcionario_id) REFERENCES Funcionarios(id),
                FOREIGN KEY (concedido_por) REFERENCES Funcionarios(id),
                INDEX idx_func_ativa (funcionario_id, ativa),
                INDEX idx_datas (data_inicio, data_fim)
            )";
            $this->db->exec($sql);
            
        } catch (PDOException $e) {
            error_log("Erro ao criar tabelas de permissões: " . $e->getMessage());
        }
    }
    
    /**
     * HIERARQUIA DE ACESSO TOTAL
     */
    private static $acessoTotal = [
        'cargos' => ['Presidente', 'Vice-Presidente'],
        'usuarios_especificos' => [], // IDs de usuários com acesso total
        'departamentos' => [1] // Presidência
    ];
    
    /**
     * Permissões exclusivas de diretores
     */
    private static $permissoesDiretor = [
        'funcionarios.desativar',
        'funcionarios.editar_salario',
        'funcionarios.badges',
        'financeiro.aprovar_pagamento',
        'financeiro.cancelar_pagamento',
        'relatorios.completos',
        'sistema.backup',
        'precadastro.aprovar',
        'precadastro.rejeitar',
        'documentos.assinar',
        'departamento.gerenciar_equipe',
        'departamento.delegar_permissoes',
        'departamento.configuracoes'
    ];
    
    /**
     * Verifica se o usuário tem acesso total
     */
    public function temAcessoTotal($funcionario_id = null) {
        if ($funcionario_id === null) {
            $funcionario_id = $_SESSION['funcionario_id'] ?? null;
        }
        
        // Verificar usuários específicos
        if (in_array($funcionario_id, self::$acessoTotal['usuarios_especificos'])) {
            return true;
        }
        
        // Buscar informações do funcionário
        $stmt = $this->db->prepare("
            SELECT cargo, departamento_id 
            FROM Funcionarios 
            WHERE id = ? AND ativo = 1
        ");
        $stmt->execute([$funcionario_id]);
        $funcionario = $stmt->fetch();
        
        if (!$funcionario) return false;
        
        // Verificar cargo
        if (in_array($funcionario['cargo'], self::$acessoTotal['cargos'])) {
            return true;
        }
        
        // Verificar departamento
        if (in_array($funcionario['departamento_id'], self::$acessoTotal['departamentos'])) {
            // Verificar se é diretor da presidência
            if ($funcionario['cargo'] == 'Diretor') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica se é diretor do departamento
     */
    public function isDiretorDepartamento($funcionario_id = null, $departamento_id = null) {
        if ($funcionario_id === null) {
            $funcionario_id = $_SESSION['funcionario_id'] ?? null;
        }
        
        $sql = "SELECT 1 FROM Funcionarios 
                WHERE id = ? AND cargo = 'Diretor' AND ativo = 1";
        
        $params = [$funcionario_id];
        
        if ($departamento_id !== null) {
            $sql .= " AND departamento_id = ?";
            $params[] = $departamento_id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Delegar permissão de diretor para funcionário
     */
    public function delegarPermissao($funcionario_destino, $permissao, $motivo = null, $data_fim = null) {
        $funcionario_origem = $_SESSION['funcionario_id'];
        
        // Verificar se quem está delegando é diretor
        if (!$this->isDiretorDepartamento($funcionario_origem)) {
            throw new Exception("Apenas diretores podem delegar permissões");
        }
        
        // Verificar se a permissão pode ser delegada
        if (!in_array($permissao, self::$permissoesDiretor) && !$this->temPermissao($funcionario_origem, $permissao)) {
            throw new Exception("Você não possui esta permissão para delegar");
        }
        
        // Verificar se funcionário destino é do mesmo departamento
        if (!$this->saoMesmoDepartamento($funcionario_origem, $funcionario_destino)) {
            throw new Exception("Só pode delegar para funcionários do mesmo departamento");
        }
        
        try {
            // Verificar se já existe delegação ativa
            $stmt = $this->db->prepare("
                SELECT id FROM Permissoes_Delegadas 
                WHERE funcionario_destino = ? 
                AND permissao = ? 
                AND ativa = 1
                AND (data_fim IS NULL OR data_fim > NOW())
            ");
            $stmt->execute([$funcionario_destino, $permissao]);
            
            if ($stmt->fetch()) {
                throw new Exception("Permissão já delegada para este funcionário");
            }
            
            // Inserir delegação
            $stmt = $this->db->prepare("
                INSERT INTO Permissoes_Delegadas 
                (funcionario_origem, funcionario_destino, permissao, motivo, data_fim) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $funcionario_origem, 
                $funcionario_destino, 
                $permissao, 
                $motivo,
                $data_fim
            ]);
            
            // Limpar cache
            $this->limparCache($funcionario_destino);
            
            // Registrar na auditoria
            $this->registrarAuditoria('DELEGAR_PERMISSAO', $funcionario_destino, [
                'permissao' => $permissao,
                'origem' => $funcionario_origem,
                'motivo' => $motivo,
                'data_fim' => $data_fim
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao delegar permissão: " . $e->getMessage());
            throw new Exception("Erro ao delegar permissão");
        }
    }
    
    /**
     * Revogar delegação
     */
    public function revogarDelegacao($delegacao_id, $motivo = null) {
        $funcionario_id = $_SESSION['funcionario_id'];
        
        try {
            // Verificar se pode revogar
            $stmt = $this->db->prepare("
                SELECT funcionario_origem, funcionario_destino, permissao 
                FROM Permissoes_Delegadas 
                WHERE id = ? AND ativa = 1
            ");
            $stmt->execute([$delegacao_id]);
            $delegacao = $stmt->fetch();
            
            if (!$delegacao) {
                throw new Exception("Delegação não encontrada");
            }
            
            // Pode revogar se: é quem delegou, é diretor do departamento, ou tem acesso total
            $podeRevogar = $delegacao['funcionario_origem'] == $funcionario_id ||
                          $this->isDiretorDepartamento($funcionario_id) ||
                          $this->temAcessoTotal($funcionario_id);
            
            if (!$podeRevogar) {
                throw new Exception("Sem permissão para revogar esta delegação");
            }
            
            // Revogar
            $stmt = $this->db->prepare("
                UPDATE Permissoes_Delegadas 
                SET ativa = 0, 
                    revogado_em = NOW(), 
                    revogado_por = ?
                WHERE id = ?
            ");
            $stmt->execute([$funcionario_id, $delegacao_id]);
            
            // Limpar cache
            $this->limparCache($delegacao['funcionario_destino']);
            
            // Registrar auditoria
            $this->registrarAuditoria('REVOGAR_DELEGACAO', $delegacao['funcionario_destino'], [
                'permissao' => $delegacao['permissao'],
                'motivo' => $motivo
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao revogar delegação: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verificar se tem permissão (considerando todas as fontes)
     */
    public function temPermissao($funcionario_id, $permissao) {
        // Verificar cache
        $cacheKey = $funcionario_id . '_' . $permissao;
        if (isset($this->cachePermissoes[$cacheKey])) {
            return $this->cachePermissoes[$cacheKey];
        }
        
        // Acesso total
        if ($this->temAcessoTotal($funcionario_id)) {
            $this->cachePermissoes[$cacheKey] = true;
            return true;
        }
        
        // Verificar permissão base (cargo/departamento)
        if (Permissoes::tem($permissao, $funcionario_id)) {
            $this->cachePermissoes[$cacheKey] = true;
            return true;
        }
        
        // Verificar delegações ativas
        if ($this->temPermissaoDelegada($funcionario_id, $permissao)) {
            $this->cachePermissoes[$cacheKey] = true;
            return true;
        }
        
        // Verificar grupos
        if ($this->temPermissaoGrupo($funcionario_id, $permissao)) {
            $this->cachePermissoes[$cacheKey] = true;
            return true;
        }
        
        // Verificar permissões temporárias
        if ($this->temPermissaoTemporaria($funcionario_id, $permissao)) {
            $this->cachePermissoes[$cacheKey] = true;
            return true;
        }
        
        $this->cachePermissoes[$cacheKey] = false;
        return false;
    }
    
    /**
     * Verificar permissão delegada
     */
    private function temPermissaoDelegada($funcionario_id, $permissao) {
        $stmt = $this->db->prepare("
            SELECT 1 FROM Permissoes_Delegadas 
            WHERE funcionario_destino = ? 
            AND permissao = ? 
            AND ativa = 1
            AND (data_fim IS NULL OR data_fim > NOW())
        ");
        
        $stmt->execute([$funcionario_id, $permissao]);
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Verificar permissão via grupo
     */
    private function temPermissaoGrupo($funcionario_id, $permissao) {
        $stmt = $this->db->prepare("
            SELECT 1 
            FROM Funcionarios_Grupos fg
            JOIN Grupos_Permissoes_Itens gpi ON fg.grupo_id = gpi.grupo_id
            JOIN Grupos_Permissoes gp ON fg.grupo_id = gp.id
            WHERE fg.funcionario_id = ? 
            AND gpi.permissao = ?
            AND gp.ativo = 1
        ");
        
        $stmt->execute([$funcionario_id, $permissao]);
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Verificar permissão temporária
     */
    private function temPermissaoTemporaria($funcionario_id, $permissao) {
        $stmt = $this->db->prepare("
            SELECT 1 FROM Permissoes_Temporarias
            WHERE funcionario_id = ?
            AND permissao = ?
            AND ativa = 1
            AND data_inicio <= NOW()
            AND data_fim >= NOW()
        ");
        
        $stmt->execute([$funcionario_id, $permissao]);
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Conceder permissão temporária
     */
    public function concederPermissaoTemporaria($funcionario_id, $permissao, $data_inicio, $data_fim, $motivo = null) {
        $concedido_por = $_SESSION['funcionario_id'];
        
        // Verificar se pode conceder
        if (!$this->temPermissao($concedido_por, 'sistema.gerenciar_permissoes') && 
            !$this->isDiretorDepartamento($concedido_por) &&
            !$this->temAcessoTotal($concedido_por)) {
            throw new Exception("Sem permissão para conceder permissões temporárias");
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Permissoes_Temporarias
                (funcionario_id, permissao, data_inicio, data_fim, motivo, concedido_por)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $funcionario_id,
                $permissao,
                $data_inicio,
                $data_fim,
                $motivo,
                $concedido_por
            ]);
            
            // Limpar cache
            $this->limparCache($funcionario_id);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao conceder permissão temporária: " . $e->getMessage());
            throw new Exception("Erro ao conceder permissão temporária");
        }
    }
    
    /**
     * Criar grupo de permissões
     */
    public function criarGrupoPermissoes($nome, $descricao, $permissoes = [], $departamento_id = null) {
        try {
            $this->db->beginTransaction();
            
            // Criar grupo
            $stmt = $this->db->prepare("
                INSERT INTO Grupos_Permissoes (nome, descricao, departamento_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$nome, $descricao, $departamento_id]);
            $grupo_id = $this->db->lastInsertId();
            
            // Adicionar permissões
            if (!empty($permissoes)) {
                $stmt = $this->db->prepare("
                    INSERT INTO Grupos_Permissoes_Itens (grupo_id, permissao)
                    VALUES (?, ?)
                ");
                
                foreach ($permissoes as $permissao) {
                    $stmt->execute([$grupo_id, $permissao]);
                }
            }
            
            $this->db->commit();
            return $grupo_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao criar grupo: " . $e->getMessage());
            throw new Exception("Erro ao criar grupo de permissões");
        }
    }
    
    /**
     * Atribuir funcionário a grupo
     */
    public function atribuirGrupo($funcionario_id, $grupo_id) {
        $atribuido_por = $_SESSION['funcionario_id'];
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Funcionarios_Grupos (funcionario_id, grupo_id, atribuido_por)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE atribuido_por = ?, data_atribuicao = NOW()
            ");
            
            $stmt->execute([$funcionario_id, $grupo_id, $atribuido_por, $atribuido_por]);
            
            // Limpar cache
            $this->limparCache($funcionario_id);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Erro ao atribuir grupo: " . $e->getMessage());
            throw new Exception("Erro ao atribuir grupo");
        }
    }
    
    /**
     * Listar delegações ativas de um funcionário
     */
    public function listarDelegacoes($funcionario_id = null) {
        if ($funcionario_id === null) {
            $funcionario_id = $_SESSION['funcionario_id'];
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                pd.*,
                fo.nome as origem_nome,
                fd.nome as destino_nome
            FROM Permissoes_Delegadas pd
            JOIN Funcionarios fo ON pd.funcionario_origem = fo.id
            JOIN Funcionarios fd ON pd.funcionario_destino = fd.id
            WHERE (pd.funcionario_origem = ? OR pd.funcionario_destino = ?)
            AND pd.ativa = 1
            AND (pd.data_fim IS NULL OR pd.data_fim > NOW())
            ORDER BY pd.criado_em DESC
        ");
        
        $stmt->execute([$funcionario_id, $funcionario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter todas as permissões efetivas de um funcionário
     */
    public function getPermissoesEfetivas($funcionario_id = null) {
        if ($funcionario_id === null) {
            $funcionario_id = $_SESSION['funcionario_id'];
        }
        
        $permissoes = [];
        
        // 1. Acesso total
        if ($this->temAcessoTotal($funcionario_id)) {
            return ['*']; // Todas as permissões
        }
        
        // 2. Permissões base (cargo/departamento)
        $permissoesBase = Permissoes::getPermissoesUsuario($funcionario_id);
        $permissoes = array_merge($permissoes, $permissoesBase);
        
        // 3. Delegações
        $stmt = $this->db->prepare("
            SELECT permissao FROM Permissoes_Delegadas
            WHERE funcionario_destino = ?
            AND ativa = 1
            AND (data_fim IS NULL OR data_fim > NOW())
        ");
        $stmt->execute([$funcionario_id]);
        while ($row = $stmt->fetch()) {
            $permissoes[] = $row['permissao'];
        }
        
        // 4. Grupos
        $stmt = $this->db->prepare("
            SELECT gpi.permissao 
            FROM Funcionarios_Grupos fg
            JOIN Grupos_Permissoes_Itens gpi ON fg.grupo_id = gpi.grupo_id
            JOIN Grupos_Permissoes gp ON fg.grupo_id = gp.id
            WHERE fg.funcionario_id = ? AND gp.ativo = 1
        ");
        $stmt->execute([$funcionario_id]);
        while ($row = $stmt->fetch()) {
            $permissoes[] = $row['permissao'];
        }
        
        // 5. Temporárias
        $stmt = $this->db->prepare("
            SELECT permissao FROM Permissoes_Temporarias
            WHERE funcionario_id = ?
            AND ativa = 1
            AND data_inicio <= NOW()
            AND data_fim >= NOW()
        ");
        $stmt->execute([$funcionario_id]);
        while ($row = $stmt->fetch()) {
            $permissoes[] = $row['permissao'];
        }
        
        return array_unique($permissoes);
    }
    
    /**
     * Verificar se são do mesmo departamento
     */
    private function saoMesmoDepartamento($funcionario1, $funcionario2) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM Funcionarios f1, Funcionarios f2
            WHERE f1.id = ? AND f2.id = ?
            AND f1.departamento_id = f2.departamento_id
        ");
        
        $stmt->execute([$funcionario1, $funcionario2]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Limpar cache de permissões
     */
    private function limparCache($funcionario_id = null) {
        if ($funcionario_id === null) {
            $this->cachePermissoes = [];
        } else {
            // Limpar apenas cache do funcionário específico
            foreach ($this->cachePermissoes as $key => $value) {
                if (strpos($key, $funcionario_id . '_') === 0) {
                    unset($this->cachePermissoes[$key]);
                }
            }
        }
    }
    
    /**
     * Registrar ação na auditoria
     */
    private function registrarAuditoria($acao, $registro_id, $detalhes = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Auditoria 
                (tabela, acao, registro_id, funcionario_id, alteracoes, ip_origem, browser_info)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                'Permissoes',
                $acao,
                $registro_id,
                $_SESSION['funcionario_id'],
                json_encode($detalhes),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
        }
    }
    
    /**
     * Dashboard de permissões do diretor
     */
    public function getDashboardDiretor($diretor_id = null) {
        if ($diretor_id === null) {
            $diretor_id = $_SESSION['funcionario_id'];
        }
        
        $dashboard = [
            'delegacoes_ativas' => 0,
            'funcionarios_com_delegacao' => 0,
            'permissoes_temporarias' => 0,
            'grupos_departamento' => 0
        ];
        
        // Delegações ativas
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT pd.id) as delegacoes,
                COUNT(DISTINCT pd.funcionario_destino) as funcionarios
            FROM Permissoes_Delegadas pd
            JOIN Funcionarios f ON pd.funcionario_origem = f.id
            WHERE pd.funcionario_origem = ?
            AND pd.ativa = 1
            AND (pd.data_fim IS NULL OR pd.data_fim > NOW())
        ");
        $stmt->execute([$diretor_id]);
        $result = $stmt->fetch();
        $dashboard['delegacoes_ativas'] = $result['delegacoes'];
        $dashboard['funcionarios_com_delegacao'] = $result['funcionarios'];
        
        // Permissões temporárias concedidas
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM Permissoes_Temporarias
            WHERE concedido_por = ?
            AND ativa = 1
            AND data_fim >= NOW()
        ");
        $stmt->execute([$diretor_id]);
        $dashboard['permissoes_temporarias'] = $stmt->fetchColumn();
        
        // Grupos do departamento
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT gp.id)
            FROM Grupos_Permissoes gp
            JOIN Funcionarios f ON f.departamento_id = gp.departamento_id
            WHERE f.id = ? AND gp.ativo = 1
        ");
        $stmt->execute([$diretor_id]);
        $dashboard['grupos_departamento'] = $stmt->fetchColumn();
        
        return $dashboard;
    }
    
    /**
     * Verificar permissões que expiram em breve
     */
    public function getPermissoesExpirando($dias = 7) {
        $stmt = $this->db->prepare("
            SELECT 
                'delegacao' as tipo,
                pd.id,
                pd.permissao,
                pd.data_fim,
                fd.nome as funcionario_nome,
                pd.funcionario_destino as funcionario_id
            FROM Permissoes_Delegadas pd
            JOIN Funcionarios fd ON pd.funcionario_destino = fd.id
            WHERE pd.ativa = 1
            AND pd.data_fim BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
            
            UNION ALL
            
            SELECT 
                'temporaria' as tipo,
                pt.id,
                pt.permissao,
                pt.data_fim,
                f.nome as funcionario_nome,
                pt.funcionario_id
            FROM Permissoes_Temporarias pt
            JOIN Funcionarios f ON pt.funcionario_id = f.id
            WHERE pt.ativa = 1
            AND pt.data_fim BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
            
            ORDER BY data_fim ASC
        ");
        
        $stmt->execute([$dias, $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Funções auxiliares globais
 */

function temPermissao($permissao) {
    return PermissoesManager::getInstance()->temPermissao($_SESSION['funcionario_id'] ?? null, $permissao);
}

function isDiretor() {
    return PermissoesManager::getInstance()->isDiretorDepartamento();
}

function temAcessoTotal() {
    return PermissoesManager::getInstance()->temAcessoTotal();
}