<?php
/**
 * Sistema de Permissões - Versão Refatorada com Banco de Dados
 * classes/Permissoes.php
 * 
 * Versão simplificada que consulta as permissões diretamente do banco
 */

require_once 'Database.php';

class Permissoes {
    
    private static $db = null;
    private static $cache = [];
    
    /**
     * Inicializa conexão com banco
     */
    private static function getDb() {
        if (self::$db === null) {
            self::$db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        }
        return self::$db;
    }
    
    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public static function tem($permissao, $usuario_id = null) {
        // Pega da sessão se não foi informado
        if ($usuario_id === null) {
            $usuario_id = $_SESSION['funcionario_id'] ?? null;
        }
        
        if (!$usuario_id) return false;
        
        // Verificar cache
        $cacheKey = $usuario_id . '_' . $permissao;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        try {
            $db = self::getDb();
            
            // Buscar informações do funcionário
            $stmt = $db->prepare("
                SELECT f.cargo, f.departamento_id 
                FROM Funcionarios f 
                WHERE f.id = ? AND f.ativo = 1
            ");
            $stmt->execute([$usuario_id]);
            $funcionario = $stmt->fetch();
            
            if (!$funcionario) {
                self::$cache[$cacheKey] = false;
                return false;
            }
            
            // 1. Verificar se é Presidente ou Vice (acesso total)
            if (in_array($funcionario['cargo'], ['Presidente', 'Vice-Presidente'])) {
                self::$cache[$cacheKey] = true;
                return true;
            }
            
            // 2. Verificar se é da Presidência (departamento_id = 1)
            if ($funcionario['departamento_id'] == 1) {
                self::$cache[$cacheKey] = true;
                return true;
            }
            
            // 3. Buscar recurso e permissão
            $partes = explode('.', $permissao);
            if (count($partes) != 2) {
                self::$cache[$cacheKey] = false;
                return false;
            }
            
            $recurso_chave = $permissao;
            $acao = $partes[1];
            
            // Mapear ação para ID da permissão
            $permissao_id = self::getPermissaoId($acao);
            
            // 4. Verificar permissões específicas do funcionário
            $stmt = $db->prepare("
                SELECT pf.concedido
                FROM Permissoes_Funcionario pf
                JOIN Recursos r ON pf.recurso_id = r.id
                WHERE pf.funcionario_id = ? 
                AND r.chave = ?
                AND pf.permissao_id = ?
            ");
            $stmt->execute([$usuario_id, $recurso_chave, $permissao_id]);
            
            if ($row = $stmt->fetch()) {
                self::$cache[$cacheKey] = (bool)$row['concedido'];
                return (bool)$row['concedido'];
            }
            
            // 5. Verificar permissões do departamento
            $stmt = $db->prepare("
                SELECT 1
                FROM Permissoes_Departamento pd
                JOIN Recursos r ON pd.recurso_id = r.id
                WHERE pd.departamento_id = ?
                AND r.chave = ?
                AND pd.permissao_id = ?
            ");
            $stmt->execute([$funcionario['departamento_id'], $recurso_chave, $permissao_id]);
            
            $resultado = $stmt->fetchColumn() !== false;
            self::$cache[$cacheKey] = $resultado;
            return $resultado;
            
        } catch (PDOException $e) {
            error_log("Erro ao verificar permissão: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mapeia nome da ação para ID da permissão
     */
    private static function getPermissaoId($acao) {
        $mapa = [
            'visualizar' => 1,
            'criar' => 2,
            'editar' => 3,
            'excluir' => 4,
            'exportar' => 5,
            'aprovar' => 6,
            'rejeitar' => 7,
            'assinar' => 8,
            'gerenciar' => 9,
            'delegar' => 10,
            'impersonar' => 11,
            // Adicione outros conforme necessário
            'upload' => 2,  // Upload é como criar
            'relatorios' => 1, // Relatórios é visualizar
            'importar_asaas' => 3, // Importar é como editar
            'desativar' => 4, // Desativar é similar a excluir
            'badges' => 9, // Badges é gerenciar
            'backup' => 9, // Backup é gerenciar
            'configuracoes' => 9, // Configurações é gerenciar
            'completos' => 5, // Relatórios completos é exportar
            'aprovar_documentos' => 6, // Aprovar documentos
            'gestao_completa' => 9, // Gestão completa é gerenciar
        ];
        
        return $mapa[$acao] ?? 1; // Default para visualizar
    }
    
    /**
     * Obtém todas as permissões de um usuário
     */
    public static function getPermissoesUsuario($usuario_id = null) {
        if ($usuario_id === null) {
            $usuario_id = $_SESSION['funcionario_id'] ?? null;
        }
        
        if (!$usuario_id) return [];
        
        try {
            $db = self::getDb();
            
            // Buscar informações do funcionário
            $stmt = $db->prepare("
                SELECT cargo, departamento_id 
                FROM Funcionarios 
                WHERE id = ? AND ativo = 1
            ");
            $stmt->execute([$usuario_id]);
            $funcionario = $stmt->fetch();
            
            if (!$funcionario) return [];
            
            // Se for Presidente/Vice ou da Presidência, tem tudo
            if (in_array($funcionario['cargo'], ['Presidente', 'Vice-Presidente']) || 
                $funcionario['departamento_id'] == 1) {
                return ['*']; // Acesso total
            }
            
            $permissoes = [];
            
            // Buscar permissões específicas do funcionário
            $stmt = $db->prepare("
                SELECT DISTINCT r.chave
                FROM Permissoes_Funcionario pf
                JOIN Recursos r ON pf.recurso_id = r.id
                WHERE pf.funcionario_id = ? AND pf.concedido = 1
            ");
            $stmt->execute([$usuario_id]);
            while ($row = $stmt->fetch()) {
                $permissoes[] = $row['chave'];
            }
            
            // Buscar permissões do departamento
            $stmt = $db->prepare("
                SELECT DISTINCT r.chave
                FROM Permissoes_Departamento pd
                JOIN Recursos r ON pd.recurso_id = r.id
                WHERE pd.departamento_id = ?
            ");
            $stmt->execute([$funcionario['departamento_id']]);
            while ($row = $stmt->fetch()) {
                $permissoes[] = $row['chave'];
            }
            
            return array_unique($permissoes);
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar permissões: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica se é da presidência (mantido para compatibilidade)
     */
    public static function ehPresidencia($usuario_id = null) {
        if ($usuario_id === null) {
            $cargo = $_SESSION['funcionario_cargo'] ?? null;
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        } else {
            $db = self::getDb();
            $stmt = $db->prepare("SELECT cargo, departamento_id FROM Funcionarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $func = $stmt->fetch();
            $cargo = $func['cargo'] ?? null;
            $departamento_id = $func['departamento_id'] ?? null;
        }
        
        return $departamento_id == 1 || in_array($cargo, ['Presidente', 'Vice-Presidente']);
    }
    
    /**
     * Verifica se é diretor (mantido para compatibilidade)
     */
    public static function ehDiretor($usuario_id = null) {
        if ($usuario_id === null) {
            $cargo = $_SESSION['funcionario_cargo'] ?? null;
        } else {
            $db = self::getDb();
            $stmt = $db->prepare("SELECT cargo FROM Funcionarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $func = $stmt->fetch();
            $cargo = $func['cargo'] ?? null;
        }
        
        return $cargo === 'Diretor';
    }
    
    /**
     * Verifica se é diretor de departamento específico
     */
    public static function ehDiretorDepartamento($departamento_check, $usuario_id = null) {
        if ($usuario_id === null) {
            $cargo = $_SESSION['funcionario_cargo'] ?? null;
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        } else {
            $db = self::getDb();
            $stmt = $db->prepare("SELECT cargo, departamento_id FROM Funcionarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $func = $stmt->fetch();
            $cargo = $func['cargo'] ?? null;
            $departamento_id = $func['departamento_id'] ?? null;
        }
        
        return $cargo === 'Diretor' && $departamento_id == $departamento_check;
    }
    
    /**
     * Métodos helper para compatibilidade (mantidos para não quebrar código existente)
     */
    public static function podeVerComercial() {
        return self::tem('comercial.visualizar');
    }
    
    public static function podeVerFinanceiro() {
        return self::tem('financeiro.visualizar');
    }
    
    public static function podeVerRH() {
        return self::tem('funcionarios.visualizar');
    }
    
    public static function podeImpersonar($usuario_id = null) {
        return self::tem('sistema.impersonar', $usuario_id);
    }
    
    public static function isAdmin($usuario_id = null) {
        if ($usuario_id === null) {
            $usuario_id = $_SESSION['funcionario_id'] ?? null;
        }
        return self::ehPresidencia($usuario_id) || self::tem('sistema.configuracoes', $usuario_id);
    }
    
    /**
     * Exigir permissão ou redirecionar
     */
    public static function exigir($permissao, $redirect = '/pages/dashboard.php') {
        if (!self::tem($permissao)) {
            $_SESSION['erro'] = 'Você não tem permissão para acessar esta página.';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }
    }
    
    /**
     * Registrar tentativa de acesso negado
     */
    public static function registrarAcessoNegado($permissao, $pagina) {
        try {
            $db = self::getDb();
            $stmt = $db->prepare("
                INSERT INTO Auditoria 
                (tabela, acao, funcionario_id, alteracoes, ip_origem, browser_info)
                VALUES ('Permissoes', 'ACESSO_NEGADO', ?, ?, ?, ?)
            ");
            
            $detalhes = json_encode([
                'permissao' => $permissao,
                'pagina' => $pagina
            ]);
            
            $stmt->execute([
                $_SESSION['funcionario_id'] ?? 0,
                $detalhes,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar acesso negado: " . $e->getMessage());
        }
    }
    
    /**
     * Limpar cache (usar quando atualizar permissões)
     */
    public static function limparCache($usuario_id = null) {
        if ($usuario_id === null) {
            self::$cache = [];
        } else {
            foreach (self::$cache as $key => $value) {
                if (strpos($key, $usuario_id . '_') === 0) {
                    unset(self::$cache[$key]);
                }
            }
        }
    }
}

/**
 * Funções helper globais para facilitar o uso
 */
function temPermissao($permissao) {
    return Permissoes::tem($permissao);
}

function ehPresidencia() {
    return Permissoes::ehPresidencia();
}

function ehDiretor() {
    return Permissoes::ehDiretor();
}