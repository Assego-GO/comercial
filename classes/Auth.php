<?php
/**
 * Classe de autenticação HÍBRIDA - CORRIGIDA
 * classes/Auth.php
 * 
 * SUPORTE PARA FUNCIONÁRIOS E ASSOCIADOS-DIRETORES
 * CORREÇÃO: Consultar ambas as tabelas para identificar usuário
 * SEGURANÇA: Apenas associados com is_diretor = 1 podem fazer login
 */

class Auth
{
    private $db;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }

    /**
     * CORREÇÃO: Login híbrido - busca em Funcionarios E Associados
     */
    public function login($email, $senha)
    {
        // Verificar tentativas de login
        if ($this->verificarBloqueio($email)) {
            return ['success' => false, 'message' => 'Usuário bloqueado temporariamente.'];
        }

        // BUSCAR PRIMEIRO EM FUNCIONÁRIOS
        $usuario = $this->buscarFuncionario($email);
        $tipoUsuario = 'funcionario';
        
        // SE NÃO ENCONTROU, BUSCAR EM ASSOCIADOS
        if (!$usuario) {
            $usuario = $this->buscarAssociado($email);
            $tipoUsuario = 'associado';
        }
        
        // Verificar senha
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Login bem-sucedido
            $this->criarSessaoHibrida($usuario, $tipoUsuario);
            $this->limparTentativas($email);
            $this->registrarLogin($usuario['id'], $tipoUsuario);

            return ['success' => true, 'tipo_usuario' => $tipoUsuario];
        }

        // Login falhou
        $this->registrarTentativaFalha($email);
        return ['success' => false, 'message' => 'Email ou senha inválidos!'];
    }

    /**
     * Buscar usuário na tabela Funcionarios
     */
    private function buscarFuncionario($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, d.nome as departamento_nome, 'funcionario' as tipo_usuario
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                WHERE f.email = ? AND f.ativo = 1
            ");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao buscar funcionário: " . $e->getMessage());
            return false;
        }
    }

    /**
     * CORREÇÃO CRÍTICA: Buscar APENAS associados que são diretores militares
     */
    private function buscarAssociado($email) {
        try {
            error_log("Buscando APENAS diretores militares com email: $email");
            
            // CORREÇÃO DE SEGURANÇA: Apenas associados com is_diretor = 1 podem fazer login
            $stmt = $this->db->prepare("
                SELECT 
                    a.*,
                    'Diretor Militar' as cargo,
                    NULL as departamento_id,
                    'Diretoria Militar' as departamento_nome,
                    'associado' as tipo_usuario
                FROM Associados a
                WHERE a.email = ? 
                AND a.senha IS NOT NULL
                AND a.is_diretor = 1
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $associado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($associado) {
                error_log("DIRETOR MILITAR encontrado: " . $associado['nome'] . " (ID: " . $associado['id'] . ")");
            } else {
                error_log("Email não pertence a um diretor militar ou diretor não ativo: $email");
            }
            
            return $associado ?: false;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar diretor militar: " . $e->getMessage());
            return false;
        }
    }

    /**
     * CORREÇÃO: Criar sessão híbrida (funcionários + associados-diretores)
     */
    private function criarSessaoHibrida($usuario, $tipoUsuario)
    {
        // Campos universais
        $_SESSION['funcionario_id'] = $usuario['id'];  // Manter nome para compatibilidade
        $_SESSION['funcionario_nome'] = $usuario['nome'];
        $_SESSION['funcionario_email'] = $usuario['email'];
        $_SESSION['funcionario_cargo'] = $usuario['cargo'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Campos específicos por tipo
        $_SESSION['tipo_usuario'] = $tipoUsuario;
        
        if ($tipoUsuario === 'funcionario') {
            $_SESSION['departamento_id'] = $usuario['departamento_id'];
            $_SESSION['departamento_nome'] = $usuario['departamento_nome'];
            $_SESSION['is_diretor'] = in_array($usuario['cargo'], ['Diretor', 'Gerente', 'Supervisor', 'Coordenador']);
        } else {
            // CORREÇÃO: Para associados-diretores (que JÁ passaram pelo filtro is_diretor = 1)
            $_SESSION['departamento_id'] = null;
            $_SESSION['departamento_nome'] = 'Diretoria Militar';
            $_SESSION['is_diretor'] = true;  // Só chegam aqui se is_diretor = 1 no banco
            $_SESSION['associado_id'] = $usuario['id'];
        }

        // Campos de compatibilidade (para códigos legados)
        $_SESSION['nome'] = $usuario['nome'];
        $_SESSION['id'] = $usuario['id'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['cargo'] = $usuario['cargo'];

        // DEBUG
        error_log("=== SESSÃO HÍBRIDA SEGURA CRIADA ===");
        error_log("Tipo de usuário: " . $tipoUsuario);
        error_log("ID: " . $usuario['id']);
        error_log("Nome: " . $usuario['nome']);
        error_log("Cargo: " . $usuario['cargo']);
        error_log("É Diretor: " . ($_SESSION['is_diretor'] ? 'SIM' : 'NÃO'));
        
        if ($tipoUsuario === 'funcionario') {
            error_log("Departamento: " . ($usuario['departamento_id'] ?? 'NULL'));
        } else {
            error_log("DIRETOR MILITAR CONFIRMADO (is_diretor=1 no banco)");
        }

        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);
    }

    /**
     * Realiza logout
     */
    public function logout()
    {
        // Registrar logout ANTES de destruir a sessão
        if (isset($_SESSION['funcionario_id'])) {
            $tipoUsuario = $_SESSION['tipo_usuario'] ?? 'funcionario';
            $this->registrarLogout($_SESSION['funcionario_id'], $tipoUsuario);
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
     * CORREÇÃO: Verificar se é diretor (funcionário OU associado-diretor)
     */
    public function isDiretor()
    {
        // Para funcionários, verificar cargo ou flag is_diretor
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'funcionario') {
            if (isset($_SESSION['is_diretor']) && $_SESSION['is_diretor']) {
                return true;
            }
            
            // Verificar por cargo também
            $cargo = $_SESSION['funcionario_cargo'] ?? '';
            return in_array($cargo, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador']);
        }
        
        // CORREÇÃO: Para associados, só chegam aqui se passaram pelo filtro is_diretor = 1
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'associado') {
            return isset($_SESSION['is_diretor']) && $_SESSION['is_diretor'] === true;
        }
        
        return false;
    }

    /**
     * Verifica se é do mesmo departamento (só para funcionários)
     */
    public function isDepartamento($departamento_id)
    {
        // Associados-diretores não têm departamento específico, têm acesso geral
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'associado') {
            return true;
        }
        
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

        if ($attempts['count'] >= MAX_TENTATIVAS_LOGIN) {
            $tempoDecorrido = time() - $attempts['last_attempt'];
            if ($tempoDecorrido < (BLOQUEIO_TEMPO_MINUTOS * 60)) {
                return true;
            } else {
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
     * CORREÇÃO: Registra login com tipo de usuário
     */
    private function registrarLogin($usuario_id, $tipoUsuario = 'funcionario')
    {
        error_log("Registrando login para $tipoUsuario ID: $usuario_id");
        
        if (class_exists('Auditoria')) {
            try {
                $auditoria = new Auditoria();
                $auditoria->registrarLoginHibrido($usuario_id, $tipoUsuario, true);
                error_log("Login híbrido registrado na auditoria com sucesso");
            } catch (Exception $e) {
                error_log("Erro ao registrar login na auditoria: " . $e->getMessage());
            }
        }
        
        error_log("Login bem-sucedido - $tipoUsuario ID: $usuario_id - IP: " . $_SERVER['REMOTE_ADDR']);
    }

    /**
     * CORREÇÃO: Registra logout com tipo de usuário
     */
    private function registrarLogout($usuario_id, $tipoUsuario = 'funcionario')
    {
        error_log("Registrando logout para $tipoUsuario ID: $usuario_id");
        
        if (class_exists('Auditoria')) {
            try {
                $auditoria = new Auditoria();
                $auditoria->registrarLogoutHibrido($usuario_id, $tipoUsuario);
                error_log("Logout híbrido registrado na auditoria com sucesso");
            } catch (Exception $e) {
                error_log("Erro ao registrar logout na auditoria: " . $e->getMessage());
            }
        }
    }

    /**
     * CORREÇÃO: Retorna dados do usuário logado (híbrido)
     */
    public function getUser()
    {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['funcionario_id'],  // Mantém nome para compatibilidade
                'nome' => $_SESSION['funcionario_nome'],
                'email' => $_SESSION['funcionario_email'],
                'cargo' => $_SESSION['funcionario_cargo'],
                'departamento_id' => $_SESSION['departamento_id'],
                'departamento_nome' => $_SESSION['departamento_nome'],
                'is_diretor' => $_SESSION['is_diretor'],
                'tipo_usuario' => $_SESSION['tipo_usuario'] ?? 'funcionario',
                'associado_id' => $_SESSION['associado_id'] ?? null
            ];
        }
        return null;
    }

    /**
     * CORREÇÃO: Buscar dados completos do usuário (em ambas as tabelas)
     */
    public function buscarDadosCompletos($usuario_id, $tipoUsuario = null) {
        try {
            if (!$tipoUsuario) {
                $tipoUsuario = $_SESSION['tipo_usuario'] ?? 'funcionario';
            }
            
            if ($tipoUsuario === 'funcionario') {
                $stmt = $this->db->prepare("
                    SELECT f.*, d.nome as departamento_nome, 'funcionario' as tipo_usuario
                    FROM Funcionarios f
                    LEFT JOIN Departamentos d ON f.departamento_id = d.id
                    WHERE f.id = ? AND f.ativo = 1
                ");
                $stmt->execute([$usuario_id]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $this->db->prepare("
                    SELECT 
                        a.*,
                        'Diretor Militar' as cargo,
                        NULL as departamento_id,
                        'Diretoria Militar' as departamento_nome,
                        'associado' as tipo_usuario
                    FROM Associados a
                    WHERE a.id = ? AND a.is_diretor = 1
                ");
                $stmt->execute([$usuario_id]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar dados completos: " . $e->getMessage());
            return null;
        }
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
            $tipoUsuario = $_SESSION['tipo_usuario'] ?? 'funcionario';
            $tabela = ($tipoUsuario === 'funcionario') ? 'Funcionarios' : 'Associados';
            
            $stmt = $this->db->prepare("SELECT senha FROM $tabela WHERE id = ?");
            $stmt->execute([$_SESSION['funcionario_id']]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                $senha_padrao = 'Assego@123';
                return password_verify($senha_padrao, $usuario['senha']);
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
?>