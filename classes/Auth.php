<?php
/**
 * Classe de autenticação HÍBRIDA com suporte a impersonation
 * classes/Auth.php
 * 
 * SUPORTE COMPLETO PARA:
 * - Funcionários (tabela Funcionarios)
 * - Associados-Diretores (tabela Associados com is_diretor = 1)
 * - Sistema de Impersonation
 * - Sistema de Permissões
 * 
 * SEGURANÇA: Apenas associados com is_diretor = 1 podem fazer login
 */
// Auto-processar saída de simulação
if (!defined('SKIP_AUTO_SIMULACAO')) {
    if (isset($_POST['voltar_simulacao']) || isset($_GET['sair_simulacao'])) {
        session_start();
        $auth = new Auth();
        if ($auth->estaSimulando()) {
            $auth->voltarParaAdmin();
            $url = strtok($_SERVER["REQUEST_URI"], '?');
            header('Location: ' . $url);
            exit;
        }
    }
}


require_once 'Permissoes.php';

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
     * Helper para obter valor de sessão com fallback
     */
    private function getSessionValue($key, $default = null)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }



    /**
     * Login híbrido - busca em Funcionarios E Associados
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
    private function buscarFuncionario($email)
    {
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
     * Buscar APENAS associados que são diretores militares
     */
    private function buscarAssociado($email)
    {
        try {
            error_log("Buscando APENAS diretores militares com email: $email");

            // SEGURANÇA: Apenas associados com is_diretor = 1 podem fazer login
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
     * Criar sessão híbrida (funcionários + associados-diretores) com suporte a impersonation
     */
    private function criarSessaoHibrida($usuario, $tipoUsuario, $isImpersonation = false)
    {
        if ($isImpersonation) {
            // Salvar dados do usuário real antes de impersonar
            $_SESSION['real_funcionario_id'] = $_SESSION['funcionario_id'] ?? null;
            $_SESSION['real_funcionario_nome'] = $_SESSION['funcionario_nome'] ?? null;
            $_SESSION['real_funcionario_email'] = $_SESSION['funcionario_email'] ?? null;
            $_SESSION['real_funcionario_cargo'] = $_SESSION['funcionario_cargo'] ?? null;
            $_SESSION['real_departamento_id'] = $_SESSION['departamento_id'] ?? null;
            $_SESSION['real_departamento_nome'] = $_SESSION['departamento_nome'] ?? null;
            $_SESSION['real_tipo_usuario'] = $_SESSION['tipo_usuario'] ?? 'funcionario';
            $_SESSION['real_is_diretor'] = $_SESSION['is_diretor'] ?? false;

            // Dados do usuário impersonado
            $_SESSION['impersonate_id'] = $usuario['id'];
            $_SESSION['impersonate_nome'] = $usuario['nome'];
            $_SESSION['impersonate_email'] = $usuario['email'];
            $_SESSION['impersonate_cargo'] = $usuario['cargo'];
            $_SESSION['impersonate_departamento_id'] = $usuario['departamento_id'];
            $_SESSION['impersonate_departamento_nome'] = $usuario['departamento_nome'];
            $_SESSION['impersonate_tipo_usuario'] = $usuario['tipo_usuario'] ?? 'funcionario';
            $_SESSION['impersonate_start'] = time();
        } else {
            // Campos universais
            $_SESSION['funcionario_id'] = $usuario['id'];
            $_SESSION['funcionario_nome'] = $usuario['nome'];
            $_SESSION['funcionario_email'] = $usuario['email'];
            $_SESSION['funcionario_cargo'] = $usuario['cargo'];
            $_SESSION['login_time'] = time();

            // Campos específicos por tipo
            $_SESSION['tipo_usuario'] = $tipoUsuario;

            if ($tipoUsuario === 'funcionario') {
                $_SESSION['departamento_id'] = $usuario['departamento_id'];
                $_SESSION['departamento_nome'] = $usuario['departamento_nome'];
                $_SESSION['is_diretor'] = in_array($usuario['cargo'], ['Diretor', 'Gerente', 'Supervisor', 'Coordenador', 'Presidente', 'Vice-Presidente']);
            } else {
                // Para associados-diretores (que JÁ passaram pelo filtro is_diretor = 1)
                $_SESSION['departamento_id'] = null;
                $_SESSION['departamento_nome'] = 'Diretoria Militar';
                $_SESSION['is_diretor'] = true;
                $_SESSION['associado_id'] = $usuario['id'];
            }

            // Campos de compatibilidade (para códigos legados)
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['id'] = $usuario['id'];
            $_SESSION['email'] = $usuario['email'];
            $_SESSION['cargo'] = $usuario['cargo'];

            // Regenerar ID da sessão por segurança
            session_regenerate_id(true);
        }

        $_SESSION['last_activity'] = time();

        // DEBUG
        error_log("=== SESSÃO " . ($isImpersonation ? "IMPERSONATION" : "HÍBRIDA") . " CRIADA ===");
        error_log("Tipo de usuário: " . $tipoUsuario);
        error_log("ID: " . $usuario['id']);
        error_log("Nome: " . $usuario['nome']);
        error_log("Cargo: " . $usuario['cargo']);
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

        // ADICIONE ESTA VERIFICAÇÃO:
        // Garantir que as variáveis de sessão atuais existem
        if (!isset($_SESSION['funcionario_id'])) {
            return ['success' => false, 'message' => 'Sessão inválida. Faça login novamente.'];
        }
        // Salvar dados do usuário real ANTES de impersonar
        $_SESSION['real_funcionario_id'] = $_SESSION['funcionario_id'];
        $_SESSION['real_funcionario_nome'] = $_SESSION['funcionario_nome'];
        $_SESSION['real_funcionario_email'] = $_SESSION['funcionario_email'];
        $_SESSION['real_funcionario_cargo'] = $_SESSION['funcionario_cargo'];
        $_SESSION['real_departamento_id'] = $_SESSION['departamento_id'] ?? null;
        $_SESSION['real_departamento_nome'] = $_SESSION['departamento_nome'] ?? null;
        $_SESSION['real_tipo_usuario'] = $_SESSION['tipo_usuario'] ?? 'funcionario';
        $_SESSION['real_is_diretor'] = $_SESSION['is_diretor'] ?? false;

        // Buscar dados do usuário a ser impersonado
        $stmt = $this->db->prepare("
            SELECT f.*, d.nome as departamento_nome, 'funcionario' as tipo_usuario
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.id = ? AND f.ativo = 1
        ");
        $stmt->execute([$usuario_id]);
        $funcionario = $stmt->fetch();

        if (!$funcionario) {
            // Tentar buscar em associados-diretores
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
            $funcionario = $stmt->fetch();

            if (!$funcionario) {
                return ['success' => false, 'message' => 'Usuário não encontrado ou inativo.'];
            }
        }

        // Não permitir impersonar a si mesmo
        if (
            $funcionario['id'] == $_SESSION['funcionario_id'] &&
            $funcionario['tipo_usuario'] == $_SESSION['tipo_usuario']
        ) {
            return ['success' => false, 'message' => 'Você não pode impersonar a si mesmo.'];
        }

        // Criar sessão de impersonation
        $this->criarSessaoHibrida($funcionario, $funcionario['tipo_usuario'], true);

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
        $_SESSION['departamento_nome'] = $_SESSION['real_departamento_nome'];
        $_SESSION['tipo_usuario'] = $_SESSION['real_tipo_usuario'];
        $_SESSION['is_diretor'] = $_SESSION['real_is_diretor'];

        // Limpar dados de impersonation
        unset($_SESSION['impersonate_id']);
        unset($_SESSION['impersonate_nome']);
        unset($_SESSION['impersonate_email']);
        unset($_SESSION['impersonate_cargo']);
        unset($_SESSION['impersonate_departamento_id']);
        unset($_SESSION['impersonate_departamento_nome']);
        unset($_SESSION['impersonate_tipo_usuario']);
        unset($_SESSION['impersonate_start']);
        unset($_SESSION['real_funcionario_id']);
        unset($_SESSION['real_funcionario_nome']);
        unset($_SESSION['real_funcionario_email']);
        unset($_SESSION['real_funcionario_cargo']);
        unset($_SESSION['real_departamento_id']);
        unset($_SESSION['real_departamento_nome']);
        unset($_SESSION['real_tipo_usuario']);
        unset($_SESSION['real_is_diretor']);

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
    /**
     * Obter dados do usuário atual (real ou impersonado)
     */
    public function getUsuarioAtual()
    {
        if ($this->estaImpersonando()) {
            // Verificar primeiro se estamos usando o sistema de simulação (assumirFuncionario)
            if (isset($_SESSION['admin_original'])) {
                // Sistema de simulação (assumirFuncionario)
                return [
                    'id' => $_SESSION['funcionario_id'],
                    'nome' => $_SESSION['funcionario_nome'],
                    'email' => $_SESSION['funcionario_email'],
                    'cargo' => $_SESSION['funcionario_cargo'],
                    'departamento_id' => $_SESSION['departamento_id'],
                    'departamento_nome' => $_SESSION['departamento_nome'],
                    'tipo_usuario' => $_SESSION['tipo_usuario'] ?? 'funcionario',
                    'impersonando' => true,
                    'usuario_real' => [
                        'id' => $_SESSION['admin_original']['id'] ?? null,
                        'nome' => $_SESSION['admin_original']['nome'] ?? 'Admin'
                    ]
                ];
            } else {
                // Sistema de impersonation normal
                return [
                    'id' => $_SESSION['impersonate_id'],
                    'nome' => $_SESSION['impersonate_nome'],
                    'email' => $_SESSION['impersonate_email'],
                    'cargo' => $_SESSION['impersonate_cargo'],
                    'departamento_id' => $_SESSION['impersonate_departamento_id'],
                    'departamento_nome' => $_SESSION['impersonate_departamento_nome'],
                    'tipo_usuario' => $_SESSION['impersonate_tipo_usuario'],
                    'impersonando' => true,
                    'usuario_real' => [
                        'id' => $_SESSION['real_funcionario_id'] ?? $_SESSION['funcionario_id'] ?? null,
                        'nome' => $_SESSION['real_funcionario_nome'] ?? $_SESSION['funcionario_nome'] ?? 'Admin'
                    ]
                ];
            }
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
     * Verificar se é diretor (funcionário OU associado-diretor)
     */
    public function isDiretor()
    {
        // Se está impersonando, verificar cargo do impersonado
        if ($this->estaImpersonando()) {
            $cargo = $_SESSION['impersonate_cargo'];
            $tipo = $_SESSION['impersonate_tipo_usuario'] ?? 'funcionario';

            if ($tipo === 'associado') {
                return true; // Associados só fazem login se is_diretor = 1
            }

            return in_array($cargo, ['Diretor', 'Presidente', 'Vice-Presidente', 'Gerente', 'Supervisor', 'Coordenador']);
        }

        // Para funcionários, verificar cargo ou flag is_diretor
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'funcionario') {
            if (isset($_SESSION['is_diretor']) && $_SESSION['is_diretor']) {
                return true;
            }

            $cargo = $_SESSION['funcionario_cargo'] ?? '';
            return in_array($cargo, ['Diretor', 'Gerente', 'Supervisor', 'Coordenador', 'Presidente', 'Vice-Presidente']);
        }

        // Para associados, só chegam aqui se passaram pelo filtro is_diretor = 1
        if (isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'associado') {
            return isset($_SESSION['is_diretor']) && $_SESSION['is_diretor'] === true;
        }

        return false;
    }

    /**
     * Verifica se é do mesmo departamento
     */
    public function isDepartamento($departamento_id)
    {
        // Se está impersonando, usar departamento do impersonado
        if ($this->estaImpersonando()) {
            $tipo = $_SESSION['impersonate_tipo_usuario'] ?? 'funcionario';

            // Associados-diretores não têm departamento específico, têm acesso geral
            if ($tipo === 'associado') {
                return true;
            }

            return $_SESSION['impersonate_departamento_id'] == $departamento_id;
        }

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
     * Registra login com tipo de usuário
     */
    private function registrarLogin($usuario_id, $tipoUsuario = 'funcionario')
    {
        error_log("Registrando login para $tipoUsuario ID: $usuario_id");

        try {
            $tabela = ($tipoUsuario === 'funcionario') ? 'Funcionarios' : 'Associados';

            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (
                    tabela, 
                    acao, 
                    registro_id, 
                    funcionario_id, 
                    associado_id,
                    ip_origem, 
                    browser_info,
                    sessao_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $funcionario_id = ($tipoUsuario === 'funcionario') ? $usuario_id : null;
            $associado_id = ($tipoUsuario === 'associado') ? $usuario_id : null;

            $stmt->execute([
                $tabela,
                'LOGIN',
                $usuario_id,
                $funcionario_id,
                $associado_id,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);

            error_log("Login registrado com sucesso - $tipoUsuario ID: $usuario_id");
        } catch (PDOException $e) {
            error_log("Erro ao registrar login: " . $e->getMessage());
        }
    }

    /**
     * Registra logout com tipo de usuário
     */
    private function registrarLogout($usuario_id, $tipoUsuario = 'funcionario')
    {
        error_log("Registrando logout para $tipoUsuario ID: $usuario_id");

        try {
            $tabela = ($tipoUsuario === 'funcionario') ? 'Funcionarios' : 'Associados';

            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (
                    tabela, 
                    acao, 
                    registro_id, 
                    funcionario_id, 
                    associado_id,
                    ip_origem, 
                    browser_info,
                    sessao_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $funcionario_id = ($tipoUsuario === 'funcionario') ? $usuario_id : null;
            $associado_id = ($tipoUsuario === 'associado') ? $usuario_id : null;

            $stmt->execute([
                $tabela,
                'LOGOUT',
                $usuario_id,
                $funcionario_id,
                $associado_id,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);

            error_log("Logout registrado com sucesso");
        } catch (PDOException $e) {
            error_log("Erro ao registrar logout: " . $e->getMessage());
        }
    }

    /**
     * Retorna dados do usuário logado (híbrido)
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
                'is_diretor' => $_SESSION['is_diretor'] ?? false,
                'tipo_usuario' => $_SESSION['tipo_usuario'] ?? 'funcionario',
                'associado_id' => $_SESSION['associado_id'] ?? null,
                'impersonando' => $this->estaImpersonando()
            ];
        }
        return null;
    }

    /**
     * Buscar dados completos do usuário (em ambas as tabelas)
     */
    public function buscarDadosCompletos($usuario_id, $tipoUsuario = null)
    {
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
            $id = $this->estaImpersonando() ? $_SESSION['impersonate_id'] : $_SESSION['funcionario_id'];
            $tipoUsuario = $this->estaImpersonando() ?
                $_SESSION['impersonate_tipo_usuario'] :
                ($_SESSION['tipo_usuario'] ?? 'funcionario');

            $tabela = ($tipoUsuario === 'funcionario') ? 'Funcionarios' : 'Associados';

            $stmt = $this->db->prepare("SELECT senha FROM $tabela WHERE id = ?");
            $stmt->execute([$id]);
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

    /**
     * Verifica se é admin (alias para isDiretor)
     */
    public function isAdmin()
    { // Verificar se tem role SUPER_ADMIN
        if (class_exists('Permissoes')) {
            $permissoes = Permissoes::getInstance();
            if ($permissoes->hasRole('SUPER_ADMIN')) {
                return true;
            }
        }

        return $this->isDiretor();
    }

    /**
     * Assume a identidade de outro funcionário (modo simulação)
     */
    public function assumirFuncionario($funcionario_id)
    {
        if (!$this->isAdmin()) {
            return ['success' => false, 'message' => 'Acesso negado.'];
        }

        try {
            // Buscar primeiro em funcionários
            $stmt = $this->db->prepare("
            SELECT f.*, d.nome as departamento_nome, 'funcionario' as tipo_usuario
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.id = ? AND f.ativo = 1
        ");
            $stmt->execute([$funcionario_id]);
            $funcionario = $stmt->fetch();

            // Se não encontrou, buscar em associados-diretores
            if (!$funcionario) {
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
                $stmt->execute([$funcionario_id]);
                $funcionario = $stmt->fetch();
            }

            if (!$funcionario) {
                return ['success' => false, 'message' => 'Usuário não encontrado.'];
            }

            // Não permitir simular a própria conta
            if (
                $funcionario['id'] == $_SESSION['funcionario_id'] &&
                $funcionario['tipo_usuario'] == ($_SESSION['tipo_usuario'] ?? 'funcionario')
            ) {
                return ['success' => false, 'message' => 'Você não pode simular sua própria conta.'];
            }

            // Salvar TODOS os dados do admin original (apenas na primeira vez)
            if (!isset($_SESSION['admin_original']) && !isset($_SESSION['simulando'])) {
                $_SESSION['admin_original'] = [
                    'id' => $_SESSION['funcionario_id'] ?? null,
                    'nome' => $_SESSION['funcionario_nome'] ?? 'Admin',
                    'email' => $_SESSION['funcionario_email'] ?? null,
                    'cargo' => $_SESSION['funcionario_cargo'] ?? null,
                    'departamento_id' => $_SESSION['departamento_id'] ?? null,
                    'departamento_nome' => $_SESSION['departamento_nome'] ?? null,
                    'is_diretor' => $_SESSION['is_diretor'] ?? false,
                    'tipo_usuario' => $_SESSION['tipo_usuario'] ?? 'funcionario',
                    'login_time' => $_SESSION['login_time'] ?? time(),
                    'last_activity' => $_SESSION['last_activity'] ?? time(),
                    'associado_id' => $_SESSION['associado_id'] ?? null
                ];
            }

            // IMPORTANTE: Definir variáveis para compatibilidade com Permissoes
            $_SESSION['impersonate_id'] = $funcionario['id'];
            $_SESSION['impersonate_nome'] = $funcionario['nome'];
            $_SESSION['impersonate_email'] = $funcionario['email'];
            $_SESSION['impersonate_cargo'] = $funcionario['cargo'];
            $_SESSION['impersonate_departamento_id'] = $funcionario['departamento_id'];
            $_SESSION['impersonate_departamento_nome'] = $funcionario['departamento_nome'];
            $_SESSION['impersonate_tipo_usuario'] = $funcionario['tipo_usuario'];
            $_SESSION['impersonate_start'] = time();

            // Atualizar variáveis principais da sessão
            $_SESSION['funcionario_id'] = $funcionario['id'];
            $_SESSION['funcionario_nome'] = $funcionario['nome'];
            $_SESSION['funcionario_email'] = $funcionario['email'];
            $_SESSION['funcionario_cargo'] = $funcionario['cargo'];
            $_SESSION['departamento_id'] = $funcionario['departamento_id'];
            $_SESSION['departamento_nome'] = $funcionario['departamento_nome'];
            $_SESSION['tipo_usuario'] = $funcionario['tipo_usuario'];

            // Determinar se é diretor
            if ($funcionario['tipo_usuario'] === 'associado') {
                $_SESSION['is_diretor'] = true; // Associados só logam se is_diretor = 1
                $_SESSION['associado_id'] = $funcionario['id'];
            } else {
                $_SESSION['is_diretor'] = in_array(
                    $funcionario['cargo'],
                    ['Diretor', 'Gerente', 'Supervisor', 'Coordenador', 'Presidente', 'Vice-Presidente']
                );
                $_SESSION['associado_id'] = null;
            }

            // Marcar que está simulando
            $_SESSION['simulando'] = true;

            // Atualizar última atividade
            $_SESSION['last_activity'] = time();

            // Registrar no log de auditoria
            $this->registrarSimulacao($funcionario_id, 'INICIO_SIMULACAO');

            return ['success' => true, 'message' => 'Simulação iniciada com sucesso'];

        } catch (PDOException $e) {
            error_log("Erro ao assumir funcionário: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno ao processar simulação.'];
        }
    }

    /**
     * Volta para a conta do admin original
     */
    public function voltarParaAdmin()
    {
        // Verificar se existe sessão de admin original
        if (!isset($_SESSION['admin_original'])) {
            return false;
        }

        // Registrar fim da simulação no log antes de restaurar
        if (isset($_SESSION['funcionario_id'])) {
            $this->registrarSimulacao($_SESSION['funcionario_id'], 'FIM_SIMULACAO');
        }

        // Restaurar TODOS os dados originais do admin
        $_SESSION['funcionario_id'] = $_SESSION['admin_original']['id'];
        $_SESSION['funcionario_nome'] = $_SESSION['admin_original']['nome'];
        $_SESSION['funcionario_email'] = $_SESSION['admin_original']['email'];
        $_SESSION['funcionario_cargo'] = $_SESSION['admin_original']['cargo'];
        $_SESSION['departamento_id'] = $_SESSION['admin_original']['departamento_id'];
        $_SESSION['departamento_nome'] = $_SESSION['admin_original']['departamento_nome'];
        $_SESSION['is_diretor'] = $_SESSION['admin_original']['is_diretor'];
        $_SESSION['tipo_usuario'] = $_SESSION['admin_original']['tipo_usuario'];
        $_SESSION['login_time'] = $_SESSION['admin_original']['login_time'];
        $_SESSION['associado_id'] = $_SESSION['admin_original']['associado_id'];

        // Atualizar última atividade
        $_SESSION['last_activity'] = time();

        // Campos de compatibilidade (para códigos legados)
        $_SESSION['nome'] = $_SESSION['admin_original']['nome'];
        $_SESSION['id'] = $_SESSION['admin_original']['id'];
        $_SESSION['email'] = $_SESSION['admin_original']['email'];
        $_SESSION['cargo'] = $_SESSION['admin_original']['cargo'];

        // IMPORTANTE: Limpar TODAS as variáveis de impersonate (para Permissoes)
        unset($_SESSION['impersonate_id']);
        unset($_SESSION['impersonate_nome']);
        unset($_SESSION['impersonate_email']);
        unset($_SESSION['impersonate_cargo']);
        unset($_SESSION['impersonate_departamento_id']);
        unset($_SESSION['impersonate_departamento_nome']);
        unset($_SESSION['impersonate_tipo_usuario']);
        unset($_SESSION['impersonate_start']);

        // Limpar flags e dados de simulação
        unset($_SESSION['simulando']);
        unset($_SESSION['admin_original']);

        // Invalidar cache de permissões se existir
        if (class_exists('Permissoes')) {
            Permissoes::invalidateCache($_SESSION['funcionario_id']);
        }

        return true;
    }

    private function registrarSimulacao($funcionario_simulado_id, $acao)
    {
        try {
            $admin_id = $_SESSION['admin_original']['id'] ?? $_SESSION['funcionario_id'];

            $stmt = $this->db->prepare("
            INSERT INTO Auditoria (
                tabela, 
                acao, 
                registro_id, 
                funcionario_id, 
                alteracoes, 
                ip_origem, 
                browser_info,
                sessao_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

            $detalhes = json_encode([
                'tipo' => 'SIMULACAO',
                'acao' => $acao,
                'admin_original_id' => $admin_id,
                'funcionario_simulado_id' => $funcionario_simulado_id,
                'timestamp' => time()
            ]);

            $stmt->execute([
                'Funcionarios',
                $acao,
                $funcionario_simulado_id,
                $admin_id,
                $detalhes,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);

        } catch (PDOException $e) {
            error_log("Erro ao registrar simulação: " . $e->getMessage());
        }
    }

    /**
     * Verifica se está simulando
     */
    public function estaSimulando()
    {
        return isset($_SESSION['simulando']) && $_SESSION['simulando'] === true;
    }

    /**
     * Lista funcionários e associados-diretores para seleção
     */
    public function listarUsuariosParaImpersonacao()
    {
        try {
            $usuarios = [];

            // Buscar funcionários
            $stmt = $this->db->prepare("
                SELECT 
                    f.id, 
                    f.nome, 
                    f.email, 
                    f.cargo, 
                    d.nome as departamento_nome,
                    'funcionario' as tipo_usuario
                FROM Funcionarios f
                LEFT JOIN Departamentos d ON f.departamento_id = d.id
                WHERE f.ativo = 1
                ORDER BY d.nome, f.cargo, f.nome
            ");
            $stmt->execute();
            $funcionarios = $stmt->fetchAll();

            // Buscar associados-diretores
            $stmt = $this->db->prepare("
                SELECT 
                    a.id,
                    a.nome,
                    a.email,
                    'Diretor Militar' as cargo,
                    'Diretoria Militar' as departamento_nome,
                    'associado' as tipo_usuario
                FROM Associados a
                WHERE a.is_diretor = 1
                ORDER BY a.nome
            ");
            $stmt->execute();
            $associados = $stmt->fetchAll();

            // Combinar e retornar
            $usuarios = array_merge($funcionarios, $associados);

            return $usuarios;

        } catch (PDOException $e) {
            error_log("Erro ao listar usuários para impersonação: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lista apenas funcionários (retrocompatibilidade)
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