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
     * Lista todos os funcionários com suporte a novos filtros
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
            
            // NOVO: Filtro para ID específico (visualização própria)
            if (isset($filtros['id_especifico'])) {
                $sql .= " AND f.id = ?";
                $params[] = $filtros['id_especifico'];
            }
            
            // NOVO: Filtro para múltiplos IDs
            if (isset($filtros['ids']) && is_array($filtros['ids']) && !empty($filtros['ids'])) {
                $placeholders = str_repeat('?,', count($filtros['ids']) - 1) . '?';
                $sql .= " AND f.id IN ($placeholders)";
                $params = array_merge($params, $filtros['ids']);
            }
            
            // NOVO: Filtro para excluir IDs
            if (isset($filtros['excluir_ids']) && is_array($filtros['excluir_ids']) && !empty($filtros['excluir_ids'])) {
                $placeholders = str_repeat('?,', count($filtros['excluir_ids']) - 1) . '?';
                $sql .= " AND f.id NOT IN ($placeholders)";
                $params = array_merge($params, $filtros['excluir_ids']);
            }
            
            // NOVO: Filtro por múltiplos departamentos
            if (isset($filtros['departamentos']) && is_array($filtros['departamentos']) && !empty($filtros['departamentos'])) {
                $placeholders = str_repeat('?,', count($filtros['departamentos']) - 1) . '?';
                $sql .= " AND f.departamento_id IN ($placeholders)";
                $params = array_merge($params, $filtros['departamentos']);
            }
            
            // NOVO: Filtro por data de cadastro
            if (isset($filtros['data_inicio'])) {
                $sql .= " AND DATE(f.criado_em) >= ?";
                $params[] = $filtros['data_inicio'];
            }
            
            if (isset($filtros['data_fim'])) {
                $sql .= " AND DATE(f.criado_em) <= ?";
                $params[] = $filtros['data_fim'];
            }
            
            // Ordenação
            $orderBy = " ORDER BY ";
            if (isset($filtros['ordenar_por'])) {
                switch ($filtros['ordenar_por']) {
                    case 'nome_desc':
                        $orderBy .= "f.nome DESC";
                        break;
                    case 'data_asc':
                        $orderBy .= "f.criado_em ASC";
                        break;
                    case 'data_desc':
                        $orderBy .= "f.criado_em DESC";
                        break;
                    case 'departamento':
                        $orderBy .= "d.nome ASC, f.nome ASC";
                        break;
                    case 'cargo':
                        $orderBy .= "f.cargo ASC, f.nome ASC";
                        break;
                    default:
                        $orderBy .= "f.nome ASC";
                }
            } else {
                $orderBy .= "f.nome ASC";
            }
            
            $sql .= $orderBy;
            
            // Limite e offset para paginação
            if (isset($filtros['limite'])) {
                $sql .= " LIMIT ?";
                $params[] = intval($filtros['limite']);
                
                if (isset($filtros['offset'])) {
                    $sql .= " OFFSET ?";
                    $params[] = intval($filtros['offset']);
                }
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao listar funcionários: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Conta total de funcionários com filtros
     */
    public function contar($filtros = []) {
        try {
            $sql = "
                SELECT COUNT(*) as total
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar os mesmos filtros do método listar
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
            
            if (isset($filtros['id_especifico'])) {
                $sql .= " AND f.id = ?";
                $params[] = $filtros['id_especifico'];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Erro ao contar funcionários: " . $e->getMessage());
            return 0;
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
     * Buscar estatísticas gerais
     */
    public function getEstatisticasGerais($filtros = []) {
        try {
            $where = "WHERE 1=1";
            $params = [];
            
            // Aplicar filtros
            if (isset($filtros['departamento_id'])) {
                $where .= " AND f.departamento_id = ?";
                $params[] = $filtros['departamento_id'];
            }
            
            // Total de funcionários
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM Funcionarios f
                $where
            ");
            $stmt->execute($params);
            $total = $stmt->fetch()['total'] ?? 0;
            
            // Funcionários ativos
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM Funcionarios f
                $where AND f.ativo = 1
            ");
            $stmt->execute($params);
            $ativos = $stmt->fetch()['total'] ?? 0;
            
            // Funcionários por departamento
            $stmt = $this->db->prepare("
                SELECT d.nome as departamento, COUNT(f.id) as total
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                $where
                GROUP BY f.departamento_id, d.nome
                ORDER BY total DESC
            ");
            $stmt->execute($params);
            $por_departamento = $stmt->fetchAll();
            
            // Funcionários por cargo
            $stmt = $this->db->prepare("
                SELECT f.cargo, COUNT(*) as total
                FROM Funcionarios f
                $where AND f.cargo IS NOT NULL
                GROUP BY f.cargo
                ORDER BY total DESC
            ");
            $stmt->execute($params);
            $por_cargo = $stmt->fetchAll();
            
            return [
                'total' => $total,
                'ativos' => $ativos,
                'inativos' => $total - $ativos,
                'por_departamento' => $por_departamento,
                'por_cargo' => $por_cargo
            ];
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas gerais: " . $e->getMessage());
            return [
                'total' => 0,
                'ativos' => 0,
                'inativos' => 0,
                'por_departamento' => [],
                'por_cargo' => []
            ];
        }
    }
    
    /**
     * Verificar permissão de edição
     */
    public function podeEditar($funcionario_id, $usuario_id, $usuario_cargo, $usuario_departamento) {
        try {
            // Buscar dados do funcionário alvo
            $funcionario = $this->getById($funcionario_id);
            if (!$funcionario) {
                return false;
            }
            
            // Se é o próprio funcionário
            if ($funcionario_id == $usuario_id) {
                return true;
            }
            
            // Se é da presidência
            if ($usuario_departamento == 1) {
                return true;
            }
            
            // Se é diretor
            if (in_array($usuario_cargo, ['Diretor', 'Presidente', 'Vice-Presidente'])) {
                return true;
            }
            
            // Se é gerente/supervisor do mesmo departamento
            if (in_array($usuario_cargo, ['Gerente', 'Supervisor', 'Coordenador'])) {
                return $funcionario['departamento_id'] == $usuario_departamento;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Erro ao verificar permissão de edição: " . $e->getMessage());
            return false;
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
    
    /**
     * Exportar funcionários para CSV
     */
    public function exportarCSV($filtros = []) {
        try {
            $funcionarios = $this->listar($filtros);
            
            $csv = "ID,Nome,Email,Departamento,Cargo,Status,Data Cadastro\n";
            
            foreach ($funcionarios as $f) {
                $status = $f['ativo'] == 1 ? 'Ativo' : 'Inativo';
                $csv .= "{$f['id']},\"{$f['nome']}\",{$f['email']},\"{$f['departamento_nome']}\",\"{$f['cargo']}\",{$status},{$f['criado_em']}\n";
            }
            
            return $csv;
        } catch (Exception $e) {
            error_log("Erro ao exportar CSV: " . $e->getMessage());
            return false;
        }
    }
}