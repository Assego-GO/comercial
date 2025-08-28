<?php
/**
 * Classe de autenticação
 * classes/Auth.php
 */

session_start();

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
    private function criarSessao($funcionario)
    {
        $_SESSION['funcionario_id'] = $funcionario['id'];
        $_SESSION['funcionario_nome'] = $funcionario['nome'];
        $_SESSION['funcionario_email'] = $funcionario['email'];
        $_SESSION['funcionario_cargo'] = $funcionario['cargo'];
        $_SESSION['departamento_id'] = $funcionario['departamento_id'];
        $_SESSION['departamento_nome'] = $funcionario['departamento_nome'];
        $_SESSION['is_diretor'] = ($funcionario['cargo'] == 'Diretor');
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);
    }

    /**
     * Realiza logout
     */
    public function logout()
    {
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
        return isset($_SESSION['is_diretor']) && $_SESSION['is_diretor'];
    }

    /**
     * Verifica se é do mesmo departamento
     */
    public function isDepartamento($departamento_id)
    {
        return isset($_SESSION['departamento_id']) && $_SESSION['departamento_id'] == $departamento_id;
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
     * Registra login bem-sucedido (para auditoria futura)
     */
    private function registrarLogin($funcionario_id)
    {
        // Pode ser implementado log em banco de dados futuramente
        error_log("Login bem-sucedido - Funcionário ID: $funcionario_id - IP: " . $_SERVER['REMOTE_ADDR']);
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
                'is_diretor' => $_SESSION['is_diretor']
            ];
        }
        return null;
    }

    /**
     * Verifica se o usuário está usando a senha padrão
     * Adicionar este método na classe Auth após o método getUser()
     */
    public function isUsingSenhaDefault()
    {
        if (!$this->isLoggedIn()) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("SELECT senha FROM Funcionarios WHERE id = ?");
            $stmt->execute([$_SESSION['funcionario_id']]);
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
     * Marca que o usuário foi notificado sobre a senha padrão (para esta sessão)
     * Adicionar este método na classe Auth após o método anterior
     */
    public function setNotificadoSenhaPadrao()
    {
        $_SESSION['notificado_senha_padrao'] = true;
    }

    /**
     * Verifica se já foi notificado nesta sessão
     * Adicionar este método na classe Auth após o método anterior
     */
    public function foiNotificadoSenhaPadrao()
    {
        return isset($_SESSION['notificado_senha_padrao']) && $_SESSION['notificado_senha_padrao'];
    }

    public function isAdmin()
{
    return $this->isDiretor();
}

/**
 * Assume a identidade de outro funcionário
 */
public function assumirFuncionario($funcionario_id)
{
    if (!$this->isAdmin()) {
        return ['success' => false, 'message' => 'Acesso negado.'];
    }

    try {
        $stmt = $this->db->prepare("
            SELECT f.*, d.nome as departamento_nome 
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.id = ? AND f.ativo = 1
        ");
        $stmt->execute([$funcionario_id]);
        $funcionario = $stmt->fetch();

        if (!$funcionario) {
            return ['success' => false, 'message' => 'Funcionário não encontrado.'];
        }

        // Salvar TODOS os dados do admin original - CORRIGIDO
        if (!isset($_SESSION['admin_original'])) {
            $_SESSION['admin_original'] = [
                'id' => $_SESSION['funcionario_id'],
                'nome' => $_SESSION['funcionario_nome'],
                'email' => $_SESSION['funcionario_email'],
                'cargo' => $_SESSION['funcionario_cargo'],
                'departamento_id' => $_SESSION['departamento_id'],
                'departamento_nome' => $_SESSION['departamento_nome'],
                'is_diretor' => $_SESSION['is_diretor'],
                'login_time' => $_SESSION['login_time'],
                'last_activity' => $_SESSION['last_activity']
            ];
        }

        // Assumir identidade do funcionário
        $_SESSION['funcionario_id'] = $funcionario['id'];
        $_SESSION['funcionario_nome'] = $funcionario['nome'];
        $_SESSION['funcionario_email'] = $funcionario['email'];
        $_SESSION['funcionario_cargo'] = $funcionario['cargo'];
        $_SESSION['departamento_id'] = $funcionario['departamento_id'];
        $_SESSION['departamento_nome'] = $funcionario['departamento_nome'];
        $_SESSION['is_diretor'] = ($funcionario['cargo'] == 'Diretor');
        $_SESSION['simulando'] = true;

        return ['success' => true];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erro interno.'];
    }
}

/**
 * Volta para a conta do admin original - VERSÃO CORRIGIDA
 */
public function voltarParaAdmin()
{
    if (!isset($_SESSION['admin_original'])) {
        return false;
    }

    // Restaurar TODOS os dados originais do admin
    $_SESSION['funcionario_id'] = $_SESSION['admin_original']['id'];
    $_SESSION['funcionario_nome'] = $_SESSION['admin_original']['nome'];
    $_SESSION['funcionario_email'] = $_SESSION['admin_original']['email'];
    $_SESSION['funcionario_cargo'] = $_SESSION['admin_original']['cargo'];
    $_SESSION['departamento_id'] = $_SESSION['admin_original']['departamento_id'];
    $_SESSION['departamento_nome'] = $_SESSION['admin_original']['departamento_nome'];
    $_SESSION['is_diretor'] = $_SESSION['admin_original']['is_diretor'];
    $_SESSION['login_time'] = $_SESSION['admin_original']['login_time'];
    $_SESSION['last_activity'] = time(); // Apenas atualizar atividade

    // Limpar dados de simulação
    unset($_SESSION['simulando']);
    unset($_SESSION['admin_original']);
    
    return true;
}

/**
 * Verifica se está simulando
 */
public function estaSimulando()
{
    return isset($_SESSION['simulando']) && $_SESSION['simulando'] === true;
}

/**
 * Lista funcionários para seleção
 */
public function listarFuncionarios()
{
    try {
        $stmt = $this->db->prepare("
            SELECT f.id, f.nome, f.email, f.cargo, d.nome as departamento_nome
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.ativo = 1
            ORDER BY d.nome, f.cargo, f.nome
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}


    
}