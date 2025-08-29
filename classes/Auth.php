<?php
/**
 * Classe de autenticação com suporte a impersonation
 * classes/Auth.php
 */

session_start();
require_once 'Permissoes.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }

    /**
     * Realiza login do usuário
     */
    public function login($email, $senha)
    {
        // Verificar tentativas de login
        if ($this->verificarBloqueio($email)) {
            return ['success' => false, 'message' => 'Usuário bloqueado temporariamente.'];
        }

        $stmt = $this->db->prepare("
            SELECT f.*, d.nome as departamento_nome 
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.email = ? AND f.ativo = 1
        ");
        $stmt->execute([$email]);
        $funcionario = $stmt->fetch();

        if ($funcionario && password_verify($senha, $funcionario['senha'])) {
            // Login bem-sucedido
            $this->criarSessao($funcionario);
            $this->limparTentativas($email);
            $this->registrarLogin($funcionario['id']);

            return ['success' => true];
        }

        // Login falhou
        $this->registrarTentativaFalha($email);
        return ['success' => false, 'message' => 'Email ou senha inválidos!'];
    }

    /**
     * Cria sessão do usuário
     */
    private function criarSessao($funcionario, $isImpersonation = false)
    {
        if ($isImpersonation) {
            // Salvar dados do usuário real antes de impersonar
            $_SESSION['real_funcionario_id'] = $_SESSION['funcionario_id'];
            $_SESSION['real_funcionario_nome'] = $_SESSION['funcionario_nome'];
            $_SESSION['real_funcionario_email'] = $_SESSION['funcionario_email'];
            $_SESSION['real_funcionario_cargo'] = $_SESSION['funcionario_cargo'];
            $_SESSION['real_departamento_id'] = $_SESSION['departamento_id'];
            
            // Dados do usuário impersonado
            $_SESSION['impersonate_id'] = $funcionario['id'];
            $_SESSION['impersonate_nome'] = $funcionario['nome'];
            $_SESSION['impersonate_email'] = $funcionario['email'];
            $_SESSION['impersonate_cargo'] = $funcionario['cargo'];
            $_SESSION['impersonate_departamento_id'] = $funcionario['departamento_id'];
            $_SESSION['impersonate_departamento_nome'] = $funcionario['departamento_nome'];
            $_SESSION['impersonate_start'] = time();
        } else {
            $_SESSION['funcionario_id'] = $funcionario['id'];
            $_SESSION['funcionario_nome'] = $funcionario['nome'];
            $_SESSION['funcionario_email'] = $funcionario['email'];
            $_SESSION['funcionario_cargo'] = $funcionario['cargo'];
            $_SESSION['departamento_id'] = $funcionario['departamento_id'];
            $_SESSION['departamento_nome'] = $funcionario['departamento_nome'];
            $_SESSION['is_diretor'] = ($funcionario['cargo'] == 'Diretor');
            $_SESSION['login_time'] = time();
        }
        
        $_SESSION['last_activity'] = time();
        
        if (!$isImpersonation) {
            // Regenerar ID da sessão por segurança
            session_regenerate_id(true);
        }
    }

    /**
     * Iniciar impersonation
     */
    public function impersonar($usuario_id)
    {
        // Verificar se tem permissão para impersonar
        if (!Permissoes::podeImpersonar()) {
            return ['success' => false, 'message' => 'Você não tem permissão para impersonar usuários.'];
        }
        
        // Buscar dados do usuário a ser impersonado
        $stmt = $this->db->prepare("
            SELECT f.*, d.nome as departamento_nome 
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.id = ? AND f.ativo = 1
        ");
        $stmt->execute([$usuario_id]);
        $funcionario = $stmt->fetch();
        
        if (!$funcionario) {
            return ['success' => false, 'message' => 'Usuário não encontrado ou inativo.'];
        }
        
        // Não permitir impersonar a si mesmo
        if ($funcionario['id'] == $_SESSION['funcionario_id']) {
            return ['success' => false, 'message' => 'Você não pode impersonar a si mesmo.'];
        }
        
        // Criar sessão de impersonation
        $this->criarSessao($funcionario, true);
        
        // Registrar impersonation
        $this->registrarImpersonation($usuario_id, 'START');
        
        return ['success' => true, 'usuario' => $funcionario['nome']];
    }

    /**
     * Parar impersonation
     */
    public function pararImpersonation()
    {
        if (!$this->estaImpersonando()) {
            return false;
        }
        
        $impersonate_id = $_SESSION['impersonate_id'];
        
        // Registrar fim da impersonation
        $this->registrarImpersonation($impersonate_id, 'END');
        
        // Restaurar dados do usuário real
        $_SESSION['funcionario_id'] = $_SESSION['real_funcionario_id'];
        $_SESSION['funcionario_nome'] = $_SESSION['real_funcionario_nome'];
        $_SESSION['funcionario_email'] = $_SESSION['real_funcionario_email'];
        $_SESSION['funcionario_cargo'] = $_SESSION['real_funcionario_cargo'];
        $_SESSION['departamento_id'] = $_SESSION['real_departamento_id'];
        
        // Limpar dados de impersonation
        unset($_SESSION['impersonate_id']);
        unset($_SESSION['impersonate_nome']);
        unset($_SESSION['impersonate_email']);
        unset($_SESSION['impersonate_cargo']);
        unset($_SESSION['impersonate_departamento_id']);
        unset($_SESSION['impersonate_departamento_nome']);
        unset($_SESSION['impersonate_start']);
        unset($_SESSION['real_funcionario_id']);
        unset($_SESSION['real_funcionario_nome']);
        unset($_SESSION['real_funcionario_email']);
        unset($_SESSION['real_funcionario_cargo']);
        unset($_SESSION['real_departamento_id']);
        
        return true;
    }

    /**
     * Verificar se está impersonando
     */
    public function estaImpersonando()
    {
        return isset($_SESSION['impersonate_id']) && $_SESSION['impersonate_id'] !== null;
    }

    /**
     * Obter dados do usuário atual (real ou impersonado)
     */
    public function getUsuarioAtual()
    {
        if ($this->estaImpersonando()) {
            return [
                'id' => $_SESSION['impersonate_id'],
                'nome' => $_SESSION['impersonate_nome'],
                'email' => $_SESSION['impersonate_email'],
                'cargo' => $_SESSION['impersonate_cargo'],
                'departamento_id' => $_SESSION['impersonate_departamento_id'],
                'departamento_nome' => $_SESSION['impersonate_departamento_nome'],
                'impersonando' => true,
                'usuario_real' => [
                    'id' => $_SESSION['real_funcionario_id'],
                    'nome' => $_SESSION['real_funcionario_nome']
                ]
            ];
        }
        
        return $this->getUser();
    }

    /**
     * Registrar impersonation no log
     */
    private function registrarImpersonation($usuario_impersonado_id, $acao)
    {
        try {
            $usuario_real_id = $_SESSION['real_funcionario_id'] ?? $_SESSION['funcionario_id'];
            
            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (
                    tabela, 
                    acao, 
                    registro_id, 
                    funcionario_id, 
                    alteracoes, 
                    ip_origem, 
                    browser_info
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $detalhes = json_encode([
                'tipo' => 'IMPERSONATION',
                'acao' => $acao,
                'usuario_impersonado' => $usuario_impersonado_id,
                'timestamp' => time()
            ]);
            
            $stmt->execute([
                'Funcionarios',
                'IMPERSONATE_' . $acao,
                $usuario_impersonado_id,
                $usuario_real_id,
                $detalhes,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar impersonation: " . $e->getMessage());
        }
    }

    /**
     * Realiza logout
     */
    public function logout()
    {
        // Se estiver impersonando, parar primeiro
        if ($this->estaImpersonando()) {
            $this->pararImpersonation();
        }
        
        session_destroy();
        header('Location: ' . BASE_URL . 'pages/index.php');
        exit;
    }

    /**
     * Verifica se usuário está logado
     */
    public function isLoggedIn()
    {
        if (!isset($_SESSION['funcionario_id'])) {
            return false;
        }

        // Verificar tempo de inatividade
        if (isset($_SESSION['last_activity'])) {
            $inativo = time() - $_SESSION['last_activity'];
            if ($inativo > SESSAO_TEMPO_VIDA) {
                $this->logout();
                return false;
            }
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Verifica se é diretor
     */
    public function isDiretor()
    {
        $cargo = $this->estaImpersonando() ? 
            $_SESSION['impersonate_cargo'] : 
            $_SESSION['funcionario_cargo'] ?? null;
            
        return in_array($cargo, ['Diretor', 'Presidente', 'Vice-Presidente']);
    }

    /**
     * Verifica se é do mesmo departamento
     */
    public function isDepartamento($departamento_id)
    {
        $dept_atual = $this->estaImpersonando() ? 
            $_SESSION['impersonate_departamento_id'] : 
            $_SESSION['departamento_id'] ?? null;
            
        return $dept_atual == $departamento_id;
    }

    /**
     * Força verificação de autenticação
     */
    public function checkAuth()
    {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . '/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    /**
     * Verifica permissão específica
     */
    public function checkPermissao($permissao, $redirect = '/pages/dashboard.php')
    {
        $this->checkAuth();
        
        if (!Permissoes::tem($permissao)) {
            Permissoes::registrarAcessoNegado($permissao, $_SERVER['REQUEST_URI']);
            $_SESSION['erro'] = 'Você não tem permissão para acessar esta página.';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }
    }

    /**
     * Verifica múltiplas permissões (precisa ter todas)
     */
    public function checkPermissoes(array $permissoes, $redirect = '/pages/dashboard.php')
    {
        $this->checkAuth();
        
        if (!Permissoes::temTodas($permissoes)) {
            $_SESSION['erro'] = 'Você não tem as permissões necessárias para acessar esta página.';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }
    }

    /**
     * Verifica permissão de diretor
     */
    public function checkDiretor()
    {
        $this->checkAuth();
        if (!$this->isDiretor()) {
            header('Location: ' . BASE_URL . '/pages/dashboard.php');
            exit;
        }
    }

    /**
     * Registra tentativa de login falha
     */
    private function registrarTentativaFalha($email)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "login_attempts_" . md5($email . $ip);

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }

        $_SESSION[$key]['count']++;
        $_SESSION[$key]['last_attempt'] = time();
    }

    /**
     * Verifica se usuário está bloqueado
     */
    private function verificarBloqueio($email)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "login_attempts_" . md5($email . $ip);

        if (!isset($_SESSION[$key])) {
            return false;
        }

        $attempts = $_SESSION[$key];

        // Verificar se passou o tempo de bloqueio
        if ($attempts['count'] >= MAX_TENTATIVAS_LOGIN) {
            $tempoDecorrido = time() - $attempts['last_attempt'];
            if ($tempoDecorrido < (BLOQUEIO_TEMPO_MINUTOS * 60)) {
                return true;
            } else {
                // Limpar tentativas após o tempo de bloqueio
                unset($_SESSION[$key]);
            }
        }

        return false;
    }

    /**
     * Limpa tentativas de login
     */
    private function limparTentativas($email)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "login_attempts_" . md5($email . $ip);
        unset($_SESSION[$key]);
    }

    /**
     * Registra login bem-sucedido
     */
    private function registrarLogin($funcionario_id)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (
                    tabela, 
                    acao, 
                    registro_id, 
                    funcionario_id, 
                    ip_origem, 
                    browser_info,
                    sessao_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                'Funcionarios',
                'LOGIN',
                $funcionario_id,
                $funcionario_id,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar login: " . $e->getMessage());
        }
    }

    /**
     * Retorna dados do usuário logado
     */
    public function getUser()
    {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['funcionario_id'],
                'nome' => $_SESSION['funcionario_nome'],
                'email' => $_SESSION['funcionario_email'],
                'cargo' => $_SESSION['funcionario_cargo'],
                'departamento_id' => $_SESSION['departamento_id'],
                'departamento_nome' => $_SESSION['departamento_nome'],
                'is_diretor' => $this->isDiretor()
            ];
        }
        return null;
    }

    /**
     * Verifica se o usuário está usando a senha padrão
     */
    public function isUsingSenhaDefault()
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        try {
            $funcionario_id = $this->estaImpersonando() ? 
                $_SESSION['impersonate_id'] : 
                $_SESSION['funcionario_id'];
                
            $stmt = $this->db->prepare("SELECT senha FROM Funcionarios WHERE id = ?");
            $stmt->execute([$funcionario_id]);
            $funcionario = $stmt->fetch();

            if ($funcionario) {
                // Verifica se a senha atual é a senha padrão
                $senha_padrao = 'Assego@123';
                return password_verify($senha_padrao, $funcionario['senha']);
            }

            return false;
        } catch (PDOException $e) {
            error_log("Erro ao verificar senha padrão: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca que o usuário foi notificado sobre a senha padrão
     */
    public function setNotificadoSenhaPadrao()
    {
        $_SESSION['notificado_senha_padrao'] = true;
    }

    /**
     * Verifica se já foi notificado nesta sessão
     */
    public function foiNotificadoSenhaPadrao()
    {
        return isset($_SESSION['notificado_senha_padrao']) && $_SESSION['notificado_senha_padrao'];
    }
}