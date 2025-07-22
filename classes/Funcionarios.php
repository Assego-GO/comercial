<?php
/**
 * Classe para gerenciamento de funcionários
 * classes/Funcionarios.php
 */

class Funcionarios {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }
    
    /**
     * Busca funcionário por ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, d.nome as departamento_nome,
                       fd.rg, fd.cpf
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                LEFT JOIN Funcionarios_Dados fd ON f.id = fd.funcionario_id
                WHERE f.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar funcionário: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca funcionário por email
     */
    public function getByEmail($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, d.nome as departamento_nome
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                WHERE f.email = ?
            ");
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar funcionário por email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lista todos os funcionários
     */
    public function listar($filtros = []) {
        try {
            $sql = "
                SELECT f.*, d.nome as departamento_nome,
                       (SELECT COUNT(*) FROM Badges_Funcionario bf WHERE bf.funcionario_id = f.id AND bf.ativo = 1) as total_badges,
                       (SELECT COUNT(*) FROM Contribuicoes_Funcionario cf WHERE cf.funcionario_id = f.id AND cf.ativo = 1) as total_contribuicoes
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (isset($filtros['ativo'])) {
                $sql .= " AND f.ativo = ?";
                $params[] = $filtros['ativo'];
            }
            
            if (isset($filtros['departamento_id'])) {
                $sql .= " AND f.departamento_id = ?";
                $params[] = $filtros['departamento_id'];
            }
            
            if (isset($filtros['cargo'])) {
                $sql .= " AND f.cargo LIKE ?";
                $params[] = "%{$filtros['cargo']}%";
            }
            
            if (isset($filtros['busca'])) {
                $sql .= " AND (f.nome LIKE ? OR f.email LIKE ?)";
                $params[] = "%{$filtros['busca']}%";
                $params[] = "%{$filtros['busca']}%";
            }
            
            $sql .= " ORDER BY f.nome ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao listar funcionários: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Criar novo funcionário
     */
    public function criar($dados) {
        try {
            $this->db->beginTransaction();
            
            // Validar dados obrigatórios
            if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
                throw new Exception("Dados obrigatórios não informados");
            }
            
            // Verificar se email já existe
            if ($this->emailExiste($dados['email'])) {
                throw new Exception("Este email já está cadastrado");
            }
            
            // Hash da senha
            $senha_hash = password_hash($dados['senha'], PASSWORD_DEFAULT);
            
            // Inserir funcionário
            $stmt = $this->db->prepare("
                INSERT INTO Funcionarios (nome, email, departamento_id, cargo, foto, senha, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $dados['nome'],
                $dados['email'],
                $dados['departamento_id'] ?? null,
                $dados['cargo'] ?? null,
                $dados['foto'] ?? null,
                $senha_hash,
                $dados['ativo'] ?? 1
            ]);
            
            $funcionario_id = $this->db->lastInsertId();
            
            // Se tiver RG ou CPF, inserir em Funcionarios_Dados
            if (!empty($dados['rg']) || !empty($dados['cpf'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO Funcionarios_Dados (funcionario_id, rg, cpf)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $funcionario_id,
                    $dados['rg'] ?? null,
                    $dados['cpf'] ?? null
                ]);
            }
            
            // Registrar na auditoria
            $this->registrarAuditoria('INSERT', $funcionario_id, $dados);
            
            $this->db->commit();
            return $funcionario_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao criar funcionário: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Atualizar funcionário
     */
    public function atualizar($id, $dados) {
        try {
            $this->db->beginTransaction();
            
            // Buscar dados atuais para auditoria
            $funcionario_atual = $this->getById($id);
            if (!$funcionario_atual) {
                throw new Exception("Funcionário não encontrado");
            }
            
            // Verificar se email mudou e já existe
            if (isset($dados['email']) && $dados['email'] != $funcionario_atual['email']) {
                if ($this->emailExiste($dados['email'], $id)) {
                    throw new Exception("Este email já está cadastrado");
                }
            }
            
            // Preparar SQL de atualização
            $campos = [];
            $valores = [];
            
            $campos_permitidos = ['nome', 'email', 'departamento_id', 'cargo', 'foto', 'ativo'];
            foreach ($campos_permitidos as $campo) {
                if (isset($dados[$campo])) {
                    $campos[] = "$campo = ?";
                    $valores[] = $dados[$campo];
                }
            }
            
            // Se tiver nova senha
            if (!empty($dados['senha'])) {
                $campos[] = "senha = ?";
                $valores[] = password_hash($dados['senha'], PASSWORD_DEFAULT);
                $campos[] = "senha_alterada_em = NOW()";
            }
            
            if (!empty($campos)) {
                $valores[] = $id;
                $sql = "UPDATE Funcionarios SET " . implode(", ", $campos) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
            
            // Atualizar dados pessoais se necessário
            if (isset($dados['rg']) || isset($dados['cpf'])) {
                // Verificar se já existe registro
                $stmt = $this->db->prepare("SELECT id FROM Funcionarios_Dados WHERE funcionario_id = ?");
                $stmt->execute([$id]);
                $existe = $stmt->fetch();
                
                if ($existe) {
                    $stmt = $this->db->prepare("
                        UPDATE Funcionarios_Dados 
                        SET rg = ?, cpf = ?
                        WHERE funcionario_id = ?
                    ");
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO Funcionarios_Dados (rg, cpf, funcionario_id)
                        VALUES (?, ?, ?)
                    ");
                }
                
                $stmt->execute([
                    $dados['rg'] ?? null,
                    $dados['cpf'] ?? null,
                    $id
                ]);
            }
            
            // Registrar na auditoria
            $this->registrarAuditoria('UPDATE', $id, $dados, $funcionario_atual);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao atualizar funcionário: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Desativar funcionário
     */
    public function desativar($id) {
        return $this->atualizar($id, ['ativo' => 0]);
    }
    
    /**
     * Ativar funcionário
     */
    public function ativar($id) {
        return $this->atualizar($id, ['ativo' => 1]);
    }
    
    /**
     * Verificar se email existe
     */
    private function emailExiste($email, $excluir_id = null) {
        $sql = "SELECT COUNT(*) FROM Funcionarios WHERE email = ?";
        $params = [$email];
        
        if ($excluir_id) {
            $sql .= " AND id != ?";
            $params[] = $excluir_id;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Buscar badges do funcionário
     */
    public function getBadges($funcionario_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT bf.*, tb.nome as tipo_nome, tb.descricao as tipo_descricao,
                       tb.categoria, tb.icone as tipo_icone
                FROM Badges_Funcionario bf
                LEFT JOIN Tipos_Badges tb ON bf.badge_tipo = tb.codigo
                WHERE bf.funcionario_id = ? AND bf.ativo = 1
                ORDER BY bf.ordem_exibicao ASC, bf.data_conquista DESC
            ");
            $stmt->execute([$funcionario_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar badges: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar contribuições do funcionário
     */
    public function getContribuicoes($funcionario_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM Contribuicoes_Funcionario
                WHERE funcionario_id = ? AND ativo = 1
                ORDER BY destaque DESC, data_inicio DESC
            ");
            $stmt->execute([$funcionario_id]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar contribuições: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar departamentos
     */
    public function getDepartamentos() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, nome, descricao
                FROM Departamentos
                WHERE ativo = 1
                ORDER BY nome ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar departamentos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Estatísticas do funcionário
     */
    public function getEstatisticas($funcionario_id) {
        try {
            // Buscar total de badges
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_badges, SUM(pontos) as total_pontos
                FROM Badges_Funcionario
                WHERE funcionario_id = ? AND ativo = 1
            ");
            $stmt->execute([$funcionario_id]);
            $badges = $stmt->fetch();
            
            // Buscar total de contribuições
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_contribuicoes
                FROM Contribuicoes_Funcionario
                WHERE funcionario_id = ? AND ativo = 1
            ");
            $stmt->execute([$funcionario_id]);
            $contribuicoes = $stmt->fetch();
            
            return [
                'total_badges' => $badges['total_badges'] ?? 0,
                'total_pontos' => $badges['total_pontos'] ?? 0,
                'total_contribuicoes' => $contribuicoes['total_contribuicoes'] ?? 0
            ];
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [
                'total_badges' => 0,
                'total_pontos' => 0,
                'total_contribuicoes' => 0
            ];
        }
    }
    
    /**
     * Registrar auditoria
     */
    private function registrarAuditoria($acao, $registro_id, $dados_novos = [], $dados_antigos = []) {
        try {
            $funcionario_id = $_SESSION['funcionario_id'] ?? null;
            $alteracoes = [];
            
            if ($acao == 'UPDATE' && $dados_antigos) {
                foreach ($dados_novos as $campo => $valor_novo) {
                    if (isset($dados_antigos[$campo]) && $dados_antigos[$campo] != $valor_novo) {
                        $alteracoes[] = [
                            'campo' => $campo,
                            'valor_anterior' => $dados_antigos[$campo],
                            'valor_novo' => $valor_novo
                        ];
                    }
                }
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (tabela, acao, registro_id, funcionario_id, alteracoes, ip_origem, browser_info)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                'Funcionarios',
                $acao,
                $registro_id,
                $funcionario_id,
                json_encode($alteracoes),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
        }
    }
}