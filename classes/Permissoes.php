<?php
/**
 * Sistema de Permissões Simplificado
 * classes/Permissoes.php
 * Versão com apenas Presidente, Vice e Diretores por departamento
 */

class Permissoes {
    
    // Definição das permissões disponíveis no sistema
    const PERMISSOES = [
        // Permissões de Associados
        'associados.visualizar' => 'Visualizar Associados',
        'associados.criar' => 'Criar Associados',
        'associados.editar' => 'Editar Associados',
        'associados.excluir' => 'Excluir Associados',
        'associados.exportar' => 'Exportar Dados de Associados',
        
        // Permissões de Documentos
        'documentos.visualizar' => 'Visualizar Documentos',
        'documentos.upload' => 'Upload de Documentos',
        'documentos.excluir' => 'Excluir Documentos',
        'documentos.assinar' => 'Assinar Documentos',
        
        // Permissões Financeiras
        'financeiro.visualizar' => 'Visualizar Financeiro',
        'financeiro.editar' => 'Editar Financeiro',
        'financeiro.relatorios' => 'Gerar Relatórios Financeiros',
        'financeiro.importar_asaas' => 'Importar dados ASAAS',
        
        // Permissões de Funcionários
        'funcionarios.visualizar' => 'Visualizar Funcionários',
        'funcionarios.criar' => 'Criar Funcionários',
        'funcionarios.editar' => 'Editar Funcionários',
        'funcionarios.desativar' => 'Desativar Funcionários',
        'funcionarios.badges' => 'Gerenciar Badges',
        
        // Permissões de Sistema
        'sistema.auditoria' => 'Visualizar Auditoria',
        'sistema.configuracoes' => 'Gerenciar Configurações',
        'sistema.backup' => 'Realizar Backup',
        'sistema.impersonar' => 'Impersonar Usuários (TI)',
        
        // Permissões de Relatórios
        'relatorios.visualizar' => 'Visualizar Relatórios',
        'relatorios.criar' => 'Criar Relatórios',
        'relatorios.exportar' => 'Exportar Relatórios',
        'relatorios.completos' => 'Relatórios Completos',
        
        // Permissões de Pré-cadastro
        'precadastro.visualizar' => 'Visualizar Pré-cadastros',
        'precadastro.aprovar' => 'Aprovar Pré-cadastros',
        'precadastro.rejeitar' => 'Rejeitar Pré-cadastros',
        
        // Permissões de Notificações
        'notificacoes.visualizar' => 'Visualizar Notificações',
        'notificacoes.criar' => 'Criar Notificações',
        'notificacoes.gerenciar' => 'Gerenciar Todas Notificações',
        
        // Permissões de Presidência
        'presidencia.visualizar' => 'Visualizar Área da Presidência',
        'presidencia.aprovar_documentos' => 'Aprovar Documentos',
        'presidencia.gestao_completa' => 'Gestão Completa da Presidência',
        
        // Permissões Comerciais
        'comercial.visualizar' => 'Visualizar Área Comercial',
        'comercial.editar' => 'Editar Área Comercial',
        'comercial.relatorios' => 'Relatórios Comerciais'
    ];
    
    // IDs dos departamentos (baseado no seu banco)
    const DEPARTAMENTOS = [
        'PRESIDENCIA' => 1,
        'FINANCEIRO' => 2,
        'JURIDICO' => 3,
        'HOTEL' => 4,
        'COMUNICACAO' => 5,
        'PATRIMONIO' => 6,
        'ARUANA' => 7,
        'COMPRAS' => 8,
        'RH' => 9,           // Recursos Humanos
        'COMERCIAL' => 10,
        'PARQUE_AQUATICO' => 11,
        'SOCIAL' => 12,
        'OBRAS' => 13,
        'GERAL' => 14,
        'TI' => 15,          // Tecnologia da Informação
        'CONVENIOS' => 16,
        'PAISAGISMO' => 17,
        'RELACIONAMENTO' => 18
    ];
    
    // Permissões por cargo - SIMPLIFICADO
    private static $permissoesPorCargo = [
        // Presidente tem acesso TOTAL
        'Presidente' => [
            'associados.*',
            'documentos.*',
            'financeiro.*',
            'funcionarios.*',
            'sistema.auditoria',
            'sistema.configuracoes',
            'sistema.backup',
            'relatorios.*',
            'precadastro.*',
            'notificacoes.*',
            'presidencia.*',
            'comercial.*'
        ],
        
        // Vice-Presidente tem acesso similar ao Presidente
        'Vice-Presidente' => [
            'associados.*',
            'documentos.*',
            'financeiro.*',
            'funcionarios.*',
            'sistema.auditoria',
            'sistema.configuracoes',
            'sistema.backup',
            'relatorios.*',
            'precadastro.*',
            'notificacoes.*',
            'presidencia.*',
            'comercial.*'
        ],
        
        // Diretor - permissões base (as específicas vêm do departamento)
        'Diretor' => [
            'associados.visualizar',
            'documentos.visualizar',
            'documentos.upload',
            'relatorios.visualizar',
            'notificacoes.visualizar',
            'sistema.auditoria'
        ]
    ];
    
    // Permissões específicas para Diretores por departamento
    private static $permissoesDiretorPorDepartamento = [
        // Diretor do Comercial
        10 => [ // ID do departamento Comercial
            'comercial.*',
            'associados.*',
            'documentos.*',
            'relatorios.*',
            'precadastro.*'
        ],
        
        // Diretor do Financeiro
        2 => [ // ID do departamento Financeiro
            'financeiro.*',
            'relatorios.*',
            'associados.visualizar',
            'documentos.visualizar'
        ],
        
        // Diretor do RH
        9 => [ // ID do departamento RH (Recursos Humanos)
            'funcionarios.*',
            'associados.*',
            'documentos.*',
            'relatorios.visualizar'
        ],
        
        // Diretor da Presidência (mantém acesso amplo)
        1 => [ // ID do departamento Presidência
            'associados.*',
            'documentos.*',
            'financeiro.*',
            'funcionarios.*',
            'sistema.*',
            'relatorios.*',
            'precadastro.*',
            'notificacoes.*',
            'presidencia.*',
            'comercial.*'
        ],
        
        // Diretor do TI
        15 => [ // ID do departamento TI (Tecnologia da Informação)
            'sistema.*',
            'funcionarios.*',
            'relatorios.*',
            'associados.visualizar',
            'documentos.visualizar',
            'financeiro.visualizar'
        ]
    ];
    
    // Permissões por departamento (para não-diretores)
    private static $permissoesPorDepartamento = [
        1 => [ // Presidência
            'associados.*',
            'documentos.*',
            'financeiro.*',
            'funcionarios.*',
            'sistema.auditoria',
            'sistema.configuracoes',
            'sistema.backup',
            'relatorios.*',
            'precadastro.*',
            'notificacoes.*',
            'presidencia.*',
            'comercial.*'
        ],
        
        15 => [ // TI (Tecnologia da Informação) - acesso amplo para suporte
            'sistema.*',
            'associados.*',
            'funcionarios.*',
            'documentos.*',
            'financeiro.visualizar',
            'comercial.visualizar',
            'relatorios.*',
            'notificacoes.*',
            'sistema.auditoria'
        ],
        
        2 => [ // Financeiro
            'financeiro.*',
            'relatorios.*',
            'associados.visualizar',
            'documentos.visualizar',
            'notificacoes.*'
        ],
        
        9 => [ // RH (Recursos Humanos)
            'funcionarios.*',
            'associados.*',
            'documentos.*',
            'relatorios.visualizar',
            'notificacoes.*'
        ],
        
        10 => [ // Comercial
            'comercial.*',
            'associados.*',
            'documentos.visualizar',
            'documentos.upload',
            'relatorios.*',
            'precadastro.*',
            'notificacoes.*'
        ],
        
        8 => [ // Compras
            'associados.visualizar',
            'documentos.*',
            'relatorios.visualizar',
            'notificacoes.*'
        ],
        
        14 => [ // Geral (Administrativo)
            'associados.*',
            'documentos.*',
            'precadastro.*',
            'relatorios.visualizar',
            'notificacoes.*'
        ]
    ];
    
    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public static function tem($permissao, $usuario_id = null, $cargo = null, $departamento_id = null) {
        // Se não passar parâmetros, pega da sessão
        if ($usuario_id === null) {
            $usuario_id = $_SESSION['funcionario_id'] ?? null;
            $cargo = $_SESSION['funcionario_cargo'] ?? null;
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        }
        
        // Verificar se o usuário tem a permissão
        $permissoes = self::getPermissoesUsuario($usuario_id, $cargo, $departamento_id);
        
        // Verificar permissão exata
        if (in_array($permissao, $permissoes)) {
            return true;
        }
        
        // Verificar permissão wildcard (ex: associados.* para associados.visualizar)
        $partes = explode('.', $permissao);
        if (count($partes) == 2) {
            $wildcard = $partes[0] . '.*';
            if (in_array($wildcard, $permissoes)) {
                return true;
            }
        }
        
        // Verificar permissão total (*)
        if (in_array('*', $permissoes)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtém todas as permissões de um usuário
     */
    public static function getPermissoesUsuario($usuario_id, $cargo = null, $departamento_id = null) {
        $permissoes = [];
        
        // 1. Se for Presidente ou Vice, tem acesso total
        if (in_array($cargo, ['Presidente', 'Vice-Presidente'])) {
            if (isset(self::$permissoesPorCargo[$cargo])) {
                return self::$permissoesPorCargo[$cargo];
            }
        }
        
        // 2. Se for Diretor, aplicar lógica especial
        if ($cargo === 'Diretor') {
            // Pega permissões base de diretor
            $permissoes = self::$permissoesPorCargo['Diretor'] ?? [];
            
            // Adiciona permissões específicas do departamento do diretor
            if ($departamento_id && isset(self::$permissoesDiretorPorDepartamento[$departamento_id])) {
                $permissoes = array_merge($permissoes, self::$permissoesDiretorPorDepartamento[$departamento_id]);
            }
            
            return array_unique($permissoes);
        }
        
        // 3. Para QUALQUER outro cargo (Contrato, Assistente, Analista, etc)
        // Usa as permissões do departamento
        if ($departamento_id && isset(self::$permissoesPorDepartamento[$departamento_id])) {
            $permissoes = self::$permissoesPorDepartamento[$departamento_id];
        } else {
            // Se não tiver departamento definido, dá permissões mínimas
            $permissoes = [
                'associados.visualizar',
                'documentos.visualizar',
                'relatorios.visualizar',
                'notificacoes.visualizar'
            ];
        }
        
        return array_unique($permissoes);
    }
    
    /**
     * Verifica se é da presidência
     */
    public static function ehPresidencia($usuario_id = null, $departamento_id = null) {
        if ($departamento_id === null) {
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        }
        
        $cargo = $_SESSION['funcionario_cargo'] ?? null;
        
        // QUALQUER pessoa do departamento presidência (1) tem acesso
        // OU tem cargo de presidente/vice
        return $departamento_id == self::DEPARTAMENTOS['PRESIDENCIA'] || 
               in_array($cargo, ['Presidente', 'Vice-Presidente']);
    }
    
    /**
     * Verifica se é diretor de um departamento específico
     */
    public static function ehDiretorDepartamento($departamento_check, $usuario_id = null) {
        if ($usuario_id === null) {
            $cargo = $_SESSION['funcionario_cargo'] ?? null;
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        } else {
            // Aqui você precisaria buscar do banco, mas vou usar sessão por simplicidade
            $cargo = $_SESSION['funcionario_cargo'] ?? null;
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        }
        
        // Verifica se é diretor E do departamento especificado
        return $cargo === 'Diretor' && $departamento_id == $departamento_check;
    }
    
    /**
     * Verifica se pode ver área comercial
     */
    public static function podeVerComercial() {
        $cargo = $_SESSION['funcionario_cargo'] ?? null;
        $departamento_id = $_SESSION['departamento_id'] ?? null;
        
        // Presidência sempre pode (qualquer cargo do departamento 1)
        if ($departamento_id == self::DEPARTAMENTOS['PRESIDENCIA']) {
            return true;
        }
        
        // Presidente e Vice sempre podem
        if (in_array($cargo, ['Presidente', 'Vice-Presidente'])) {
            return true;
        }
        
        // TI pode ver para dar suporte
        if ($departamento_id == self::DEPARTAMENTOS['TI']) {
            return true;
        }
        
        // Diretor só se for do comercial
        if ($cargo === 'Diretor') {
            return $departamento_id == self::DEPARTAMENTOS['COMERCIAL'];
        }
        
        // Outros funcionários do comercial
        return $departamento_id == self::DEPARTAMENTOS['COMERCIAL'];
    }
    
    /**
     * Verifica se pode ver área financeira
     */
    public static function podeVerFinanceiro() {
        $cargo = $_SESSION['funcionario_cargo'] ?? null;
        $departamento_id = $_SESSION['departamento_id'] ?? null;
        
        // Presidência sempre pode (qualquer cargo do departamento 1)
        if ($departamento_id == self::DEPARTAMENTOS['PRESIDENCIA']) {
            return true;
        }
        
        // Presidente e Vice sempre podem
        if (in_array($cargo, ['Presidente', 'Vice-Presidente'])) {
            return true;
        }
        
        // TI pode ver para dar suporte
        if ($departamento_id == self::DEPARTAMENTOS['TI']) {
            return true;
        }
        
        // Diretor só se for do financeiro
        if ($cargo === 'Diretor') {
            return $departamento_id == self::DEPARTAMENTOS['FINANCEIRO'];
        }
        
        // Outros funcionários do financeiro
        return $departamento_id == self::DEPARTAMENTOS['FINANCEIRO'];
    }
    
    /**
     * Verifica se pode ver área de RH (funcionários)
     */
    public static function podeVerRH() {
        $cargo = $_SESSION['funcionario_cargo'] ?? null;
        $departamento_id = $_SESSION['departamento_id'] ?? null;
        
        // Presidência sempre pode (qualquer cargo do departamento 1)
        if ($departamento_id == self::DEPARTAMENTOS['PRESIDENCIA']) {
            return true;
        }
        
        // Presidente e Vice sempre podem
        if (in_array($cargo, ['Presidente', 'Vice-Presidente'])) {
            return true;
        }
        
        // TI pode ver para dar suporte
        if ($departamento_id == self::DEPARTAMENTOS['TI']) {
            return true;
        }
        
        // Diretor só se for do RH
        if ($cargo === 'Diretor') {
            return $departamento_id == self::DEPARTAMENTOS['RH'];
        }
        
        // Outros funcionários do RH
        return $departamento_id == self::DEPARTAMENTOS['RH'];
    }
    
    /**
     * Verifica se pode impersonar - EXCLUSIVO DO TI
     */
    public static function podeImpersonar($usuario_id = null) {
        if ($usuario_id === null) {
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        } else {
            $departamento_id = $_SESSION['departamento_id'] ?? null;
        }
        
        // Apenas departamento TI (2) pode impersonar
        return $departamento_id == self::DEPARTAMENTOS['TI'] && self::tem('sistema.impersonar', $usuario_id);
    }
    
    /**
     * Lista todas as permissões disponíveis
     */
    public static function listarTodasPermissoes() {
        return self::PERMISSOES;
    }
    
    /**
     * Lista permissões por categoria
     */
    public static function listarPorCategoria() {
        $categorias = [];
        
        foreach (self::PERMISSOES as $chave => $descricao) {
            $categoria = explode('.', $chave)[0];
            if (!isset($categorias[$categoria])) {
                $categorias[$categoria] = [];
            }
            $categorias[$categoria][$chave] = $descricao;
        }
        
        return $categorias;
    }
    
    /**
     * Verificar se é administrador do sistema
     */
    public static function isAdmin($usuario_id = null) {
        $cargo = $_SESSION['funcionario_cargo'] ?? null;
        $departamento_id = $_SESSION['departamento_id'] ?? null;
        
        // Presidência e TI são admins, mas com diferentes permissões
        return in_array($cargo, ['Presidente', 'Vice-Presidente']) || 
               $departamento_id == self::DEPARTAMENTOS['TI'];
    }
    
    /**
     * Método helper para verificar e redirecionar se não tiver permissão
     */
    public static function exigir($permissao, $redirect = '/pages/dashboard.php') {
        if (!self::tem($permissao)) {
            $_SESSION['erro'] = 'Você não tem permissão para acessar esta página.';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }
    }
    
    /**
     * Método para registrar tentativa de acesso negado
     */
    public static function registrarAcessoNegado($permissao, $pagina) {
        $usuario_id = $_SESSION['funcionario_id'] ?? 0;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        error_log("ACESSO NEGADO - Usuário: $usuario_id | Permissão: $permissao | Página: $pagina | IP: $ip");
    }
}