<?php
/**
 * Classe para gerenciamento de auditoria HÍBRIDA - VERSÃO CORRIGIDA
 * classes/Auditoria.php
 * 
 * SUPORTE PARA FUNCIONÁRIOS E ASSOCIADOS-DIRETORES
 * CORREÇÃO: Identificar usuário em ambas as tabelas
 */

class Auditoria {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }
    
    /**
     * CORREÇÃO PRINCIPAL: Registra uma ação considerando usuários híbridos
     */
    public function registrar($dados) {
        try {
            $this->db->beginTransaction();
            
            // ===========================================
            // CORREÇÃO: IDENTIFICAR USUÁRIO EM AMBAS AS TABELAS
            // ===========================================
            
            $funcionario_id = $dados['funcionario_id'] ?? null;
            
            // Se não foi passado explicitamente, identificar da sessão
            if (!$funcionario_id) {
                $dadosUsuario = $this->identificarUsuarioLogado();
                $funcionario_id = $dadosUsuario['id'];
                
                error_log("=== DEBUG AUDITORIA HÍBRIDA ===");
                error_log("Usuário identificado: " . print_r($dadosUsuario, true));
                error_log("Funcionario ID final: " . ($funcionario_id ?? 'NULL'));
                error_log("===============================");
            }
            
            // Prepara dados básicos
            $tabela = $dados['tabela'] ?? '';
            $acao = $dados['acao'] ?? '';
            $registro_id = $dados['registro_id'] ?? null;
            $associado_id = $dados['associado_id'] ?? null;
            $alteracoes = $dados['alteracoes'] ?? [];
            $detalhes = $dados['detalhes'] ?? [];
            
            // Adiciona informações do ambiente
            $ip_origem = $this->getIpAddress();
            $browser_info = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $sessao_id = session_id() ?: null;
            
            // Prepara JSON das alterações
            $alteracoes_json = null;
            if (!empty($alteracoes)) {
                $alteracoes_json = json_encode($alteracoes, JSON_UNESCAPED_UNICODE);
            } elseif (!empty($detalhes)) {
                $alteracoes_json = json_encode($detalhes, JSON_UNESCAPED_UNICODE);
            }
            
            // Insere registro principal na tabela Auditoria
            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, associado_id, funcionario_id,
                    alteracoes, data_hora, ip_origem, browser_info, sessao_id
                ) VALUES (
                    :tabela, :acao, :registro_id, :associado_id, :funcionario_id,
                    :alteracoes, NOW(), :ip_origem, :browser_info, :sessao_id
                )
            ");
            
            $stmt->execute([
                ':tabela' => $tabela,
                ':acao' => $acao,
                ':registro_id' => $registro_id,
                ':associado_id' => $associado_id,
                ':funcionario_id' => $funcionario_id,
                ':alteracoes' => $alteracoes_json,
                ':ip_origem' => $ip_origem,
                ':browser_info' => $browser_info,
                ':sessao_id' => $sessao_id
            ]);
            
            $auditoria_id = $this->db->lastInsertId();
            
            // Se houver alterações detalhadas, registra em Auditoria_Detalhes
            if (!empty($alteracoes) && is_array($alteracoes)) {
                $stmtDetalhe = $this->db->prepare("
                    INSERT INTO Auditoria_Detalhes (
                        auditoria_id, campo, valor_anterior, valor_novo
                    ) VALUES (
                        :auditoria_id, :campo, :valor_anterior, :valor_novo
                    )
                ");
                
                foreach ($alteracoes as $alteracao) {
                    if (isset($alteracao['campo'])) {
                        $stmtDetalhe->execute([
                            ':auditoria_id' => $auditoria_id,
                            ':campo' => $alteracao['campo'],
                            ':valor_anterior' => $this->prepararValor($alteracao['valor_anterior'] ?? null),
                            ':valor_novo' => $this->prepararValor($alteracao['valor_novo'] ?? null)
                        ]);
                    }
                }
            }
            
            $this->db->commit();
            
            return $auditoria_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * NOVO: Identifica usuário logado em AMBAS as tabelas
     */
    private function identificarUsuarioLogado() {
        // Tentar usar Auth class primeiro
        if (class_exists('Auth')) {
            try {
                $auth = new Auth();
                if ($auth->isLoggedIn()) {
                    $usuario = $auth->getUser();
                    if ($usuario && isset($usuario['id'])) {
                        return [
                            'id' => $usuario['id'],
                            'nome' => $usuario['nome'],
                            'tipo' => $usuario['tipo_usuario'] ?? 'funcionario',
                            'fonte' => 'AUTH_CLASS'
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Erro ao usar Auth class: " . $e->getMessage());
            }
        }
        
        // Fallback: Buscar manualmente
        $funcionario_id_sessao = $_SESSION['funcionario_id'] ?? null;
        $nome_sessao = $_SESSION['funcionario_nome'] ?? $_SESSION['nome'] ?? null;
        $tipo_sessao = $_SESSION['tipo_usuario'] ?? 'funcionario';
        
        if ($funcionario_id_sessao) {
            // Validar na tabela apropriada
            $dados = $this->validarUsuarioNaTabela($funcionario_id_sessao, $tipo_sessao);
            if ($dados) {
                return [
                    'id' => $funcionario_id_sessao,
                    'nome' => $dados['nome'],
                    'tipo' => $tipo_sessao,
                    'fonte' => 'VALIDADO_SESSAO'
                ];
            }
        }
        
        if ($nome_sessao) {
            // Buscar por nome em AMBAS as tabelas
            $dados = $this->buscarPorNomeEmAmbasTabelas($nome_sessao);
            if ($dados) {
                return [
                    'id' => $dados['id'],
                    'nome' => $dados['nome'],
                    'tipo' => $dados['tipo'],
                    'fonte' => 'BUSCA_POR_NOME'
                ];
            }
        }
        
        // Se chegou aqui, não conseguiu identificar
        error_log("ERRO CRÍTICO: Não foi possível identificar usuário logado");
        error_log("Session funcionario_id: " . ($funcionario_id_sessao ?? 'NULL'));
        error_log("Session nome: " . ($nome_sessao ?? 'NULL'));
        error_log("Session tipo: " . ($tipo_sessao ?? 'NULL'));
        
        return [
            'id' => null,
            'nome' => 'Sistema',
            'tipo' => 'sistema',
            'fonte' => 'FALLBACK_SISTEMA'
        ];
    }
    
    /**
     * NOVO: Validar usuário na tabela apropriada
     */
    private function validarUsuarioNaTabela($id, $tipo) {
        try {
            if ($tipo === 'funcionario') {
                $stmt = $this->db->prepare("SELECT id, nome FROM Funcionarios WHERE id = ? AND ativo = 1");
            } else {
                $stmt = $this->db->prepare("SELECT id, nome FROM Associados WHERE id = ?");
            }
            
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao validar usuário: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * NOVO: Buscar usuário por nome em AMBAS as tabelas
     */
    private function buscarPorNomeEmAmbasTabelas($nome) {
        try {
            // Buscar primeiro em Funcionários
            $stmt = $this->db->prepare("SELECT id, nome, 'funcionario' as tipo FROM Funcionarios WHERE nome = ? AND ativo = 1 LIMIT 1");
            $stmt->execute([$nome]);
            $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($funcionario) {
                return $funcionario;
            }
            
            // Se não encontrou, buscar em Associados
            $stmt = $this->db->prepare("SELECT id, nome, 'associado' as tipo FROM Associados WHERE nome = ? LIMIT 1");
            $stmt->execute([$nome]);
            $associado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($associado) {
                return $associado;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Erro ao buscar por nome: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * NOVO: Registra login híbrido
     */
    public function registrarLoginHibrido($usuario_id, $tipoUsuario, $sucesso = true) {
        // Validar se o usuário existe na tabela correta
        $dados = $this->validarUsuarioNaTabela($usuario_id, $tipoUsuario);
        
        if (!$dados) {
            error_log("ERRO: Usuário não encontrado para login - ID: $usuario_id, Tipo: $tipoUsuario");
            return false;
        }
        
        return $this->registrar([
            'tabela' => $tipoUsuario === 'funcionario' ? 'Funcionarios' : 'Associados',
            'acao' => $sucesso ? 'LOGIN' : 'LOGIN_FALHA',
            'registro_id' => $usuario_id,
            'funcionario_id' => $usuario_id, // Usar o mesmo ID independente do tipo
            'detalhes' => [
                'sucesso' => $sucesso,
                'tipo_usuario' => $tipoUsuario,
                'nome_usuario' => $dados['nome'],
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $this->getIpAddress()
            ]
        ]);
    }
    
    /**
     * NOVO: Registra logout híbrido
     */
    public function registrarLogoutHibrido($usuario_id, $tipoUsuario) {
        $dados = $this->validarUsuarioNaTabela($usuario_id, $tipoUsuario);
        
        return $this->registrar([
            'tabela' => $tipoUsuario === 'funcionario' ? 'Funcionarios' : 'Associados',
            'acao' => 'LOGOUT',
            'registro_id' => $usuario_id,
            'funcionario_id' => $usuario_id,
            'detalhes' => [
                'tipo_usuario' => $tipoUsuario,
                'nome_usuario' => $dados['nome'] ?? 'N/A',
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * Registra login de usuário (método de compatibilidade)
     */
    public function registrarLogin($funcionario_id, $sucesso = true) {
        // Detectar automaticamente o tipo de usuário
        $tipoUsuario = $this->detectarTipoUsuario($funcionario_id);
        
        return $this->registrarLoginHibrido($funcionario_id, $tipoUsuario, $sucesso);
    }
    
    /**
     * Registra logout de usuário (método de compatibilidade)
     */
    public function registrarLogout($funcionario_id) {
        $tipoUsuario = $this->detectarTipoUsuario($funcionario_id);
        
        return $this->registrarLogoutHibrido($funcionario_id, $tipoUsuario);
    }
    
    /**
     * NOVO: Detectar automaticamente se é funcionário ou associado
     */
    private function detectarTipoUsuario($id) {
        try {
            // Verificar primeiro na tabela Funcionários
            $stmt = $this->db->prepare("SELECT id FROM Funcionarios WHERE id = ? AND ativo = 1 LIMIT 1");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                return 'funcionario';
            }
            
            // Se não encontrou, verificar na tabela Associados
            $stmt = $this->db->prepare("SELECT id FROM Associados WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                return 'associado';
            }
            
            error_log("AVISO: ID $id não encontrado em nenhuma tabela");
            return 'funcionario'; // fallback padrão
            
        } catch (Exception $e) {
            error_log("Erro ao detectar tipo de usuário: " . $e->getMessage());
            return 'funcionario';
        }
    }
    
    /**
     * Registra acesso a dados sensíveis
     */
    public function registrarAcesso($tabela, $registro_id, $tipo_acesso = 'VISUALIZAR') {
        return $this->registrar([
            'tabela' => $tabela,
            'acao' => $tipo_acesso,
            'registro_id' => $registro_id,
            'associado_id' => ($tabela === 'Associados') ? $registro_id : null,
            'detalhes' => [
                'tipo_acesso' => $tipo_acesso,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * CORREÇÃO: Busca histórico de auditoria com suporte híbrido
     */
    public function buscarHistorico($filtros = []) {
        try {
            $sql = "
                SELECT 
                    a.*,
                    COALESCE(f.nome, ass.nome) as funcionario_nome,
                    CASE 
                        WHEN f.id IS NOT NULL THEN 'Funcionário'
                        WHEN ass.id IS NOT NULL THEN 'Associado-Diretor'
                        ELSE 'Sistema'
                    END as tipo_usuario_registro,
                    f.cargo as funcionario_cargo,
                    f.departamento_id as funcionario_departamento,
                    ass2.nome as associado_nome
                FROM Auditoria a
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                LEFT JOIN Associados ass ON a.funcionario_id = ass.id AND f.id IS NULL
                LEFT JOIN Associados ass2 ON a.associado_id = ass2.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filtros['tabela'])) {
                $sql .= " AND a.tabela = :tabela";
                $params[':tabela'] = $filtros['tabela'];
            }
            
            if (!empty($filtros['acao'])) {
                $sql .= " AND a.acao = :acao";
                $params[':acao'] = $filtros['acao'];
            }
            
            if (!empty($filtros['funcionario_id'])) {
                $sql .= " AND a.funcionario_id = :funcionario_id";
                $params[':funcionario_id'] = $filtros['funcionario_id'];
            }
            
            if (!empty($filtros['associado_id'])) {
                $sql .= " AND a.associado_id = :associado_id";
                $params[':associado_id'] = $filtros['associado_id'];
            }
            
            if (!empty($filtros['data_inicio'])) {
                $sql .= " AND DATE(a.data_hora) >= :data_inicio";
                $params[':data_inicio'] = $filtros['data_inicio'];
            }
            
            if (!empty($filtros['data_fim'])) {
                $sql .= " AND DATE(a.data_hora) <= :data_fim";
                $params[':data_fim'] = $filtros['data_fim'];
            }
            
            // CORREÇÃO: Filtro departamental híbrido
            if (!empty($filtros['departamento_usuario'])) {
                $sql .= " AND (f.departamento_id = :departamento_usuario OR ass.id IS NOT NULL)";
                $params[':departamento_usuario'] = $filtros['departamento_usuario'];
            }
            
            // Ordenação
            $sql .= " ORDER BY a.data_hora DESC";
            
            // Limite
            if (!empty($filtros['limit'])) {
                $sql .= " LIMIT :limit";
                if (!empty($filtros['offset'])) {
                    $sql .= " OFFSET :offset";
                }
            }
            
            $stmt = $this->db->prepare($sql);
            
            // Bind dos parâmetros
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            if (!empty($filtros['limit'])) {
                $stmt->bindValue(':limit', (int)$filtros['limit'], PDO::PARAM_INT);
                if (!empty($filtros['offset'])) {
                    $stmt->bindValue(':offset', (int)$filtros['offset'], PDO::PARAM_INT);
                }
            }
            
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cada registro, busca os detalhes se existirem
            foreach ($registros as &$registro) {
                if ($registro['acao'] === 'UPDATE') {
                    $registro['detalhes_alteracoes'] = $this->buscarDetalhesAlteracoes($registro['id']);
                }
                
                // Decodifica JSON das alterações
                if (!empty($registro['alteracoes'])) {
                    $registro['alteracoes_decoded'] = json_decode($registro['alteracoes'], true);
                }
            }
            
            return $registros;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar histórico de auditoria: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca detalhes das alterações
     */
    private function buscarDetalhesAlteracoes($auditoria_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Auditoria_Detalhes 
                WHERE auditoria_id = :auditoria_id
                ORDER BY id
            ");
            
            $stmt->execute([':auditoria_id' => $auditoria_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar detalhes de alterações: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gera relatório de auditoria
     */
    public function gerarRelatorio($tipo = 'geral', $periodo = 'mes') {
        try {
            $data_inicio = $this->calcularDataInicio($periodo);
            
            $relatorio = [
                'periodo' => $periodo,
                'data_inicio' => $data_inicio,
                'data_fim' => date('Y-m-d'),
                'estatisticas' => []
            ];
            
            switch ($tipo) {
                case 'geral':
                    $relatorio['estatisticas'] = $this->estatisticasGerais($data_inicio);
                    break;
                    
                case 'por_funcionario':
                    $relatorio['estatisticas'] = $this->estatisticasPorFuncionario($data_inicio);
                    break;
                    
                case 'por_acao':
                    $relatorio['estatisticas'] = $this->estatisticasPorAcao($data_inicio);
                    break;
                    
                case 'acessos':
                    $relatorio['estatisticas'] = $this->estatisticasAcessos($data_inicio);
                    break;
            }
            
            return $relatorio;
            
        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de auditoria: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * CORREÇÃO: Estatísticas gerais com suporte híbrido
     */
    private function estatisticasGerais($data_inicio) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_acoes,
                COUNT(DISTINCT a.funcionario_id) as usuarios_ativos,
                COUNT(DISTINCT a.associado_id) as associados_afetados,
                COUNT(DISTINCT DATE(a.data_hora)) as dias_com_atividade,
                COUNT(DISTINCT a.ip_origem) as ips_unicos,
                SUM(CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END) as acoes_funcionarios,
                SUM(CASE WHEN ass.id IS NOT NULL THEN 1 ELSE 0 END) as acoes_associados
            FROM Auditoria a
            LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
            LEFT JOIN Associados ass ON a.funcionario_id = ass.id AND f.id IS NULL
            WHERE DATE(a.data_hora) >= :data_inicio
        ");
        
        $stmt->execute([':data_inicio' => $data_inicio]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * CORREÇÃO: Estatísticas por usuário (funcionários + associados)
     */
    private function estatisticasPorFuncionario($data_inicio) {
        $stmt = $this->db->prepare("
            SELECT 
                a.funcionario_id as id,
                COALESCE(f.nome, ass.nome) as nome,
                CASE 
                    WHEN f.id IS NOT NULL THEN f.cargo
                    WHEN ass.id IS NOT NULL THEN 'Associado-Diretor'
                    ELSE 'Sistema'
                END as cargo,
                CASE 
                    WHEN f.id IS NOT NULL THEN 'Funcionário'
                    WHEN ass.id IS NOT NULL THEN 'Associado'
                    ELSE 'Sistema'
                END as tipo,
                COUNT(a.id) as total_acoes,
                COUNT(DISTINCT DATE(a.data_hora)) as dias_ativos,
                COUNT(DISTINCT a.tabela) as tabelas_acessadas,
                MAX(a.data_hora) as ultima_acao
            FROM Auditoria a
            LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
            LEFT JOIN Associados ass ON a.funcionario_id = ass.id AND f.id IS NULL
            WHERE DATE(a.data_hora) >= :data_inicio
                AND a.funcionario_id IS NOT NULL
            GROUP BY a.funcionario_id
            HAVING total_acoes > 0
            ORDER BY total_acoes DESC
        ");
        
        $stmt->execute([':data_inicio' => $data_inicio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Estatísticas por tipo de ação
     */
    private function estatisticasPorAcao($data_inicio) {
        $stmt = $this->db->prepare("
            SELECT 
                acao,
                tabela,
                COUNT(*) as total,
                COUNT(DISTINCT funcionario_id) as funcionarios,
                MIN(data_hora) as primeira_vez,
                MAX(data_hora) as ultima_vez
            FROM Auditoria
            WHERE DATE(data_hora) >= :data_inicio
            GROUP BY acao, tabela
            ORDER BY total DESC
        ");
        
        $stmt->execute([':data_inicio' => $data_inicio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Estatísticas de acessos
     */
    private function estatisticasAcessos($data_inicio) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(data_hora) as data,
                HOUR(data_hora) as hora,
                COUNT(*) as total_acessos,
                COUNT(DISTINCT funcionario_id) as usuarios_unicos,
                COUNT(DISTINCT ip_origem) as ips_unicos
            FROM Auditoria
            WHERE DATE(data_hora) >= :data_inicio
                AND acao IN ('LOGIN', 'VISUALIZAR', 'LISTAR')
            GROUP BY DATE(data_hora), HOUR(data_hora)
            ORDER BY data DESC, hora DESC
        ");
        
        $stmt->execute([':data_inicio' => $data_inicio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Limpa registros antigos de auditoria
     */
    public function limparRegistrosAntigos($dias = 365) {
        try {
            $data_limite = date('Y-m-d', strtotime("-$dias days"));
            
            // Primeiro, remove detalhes
            $stmt = $this->db->prepare("
                DELETE ad FROM Auditoria_Detalhes ad
                INNER JOIN Auditoria a ON ad.auditoria_id = a.id
                WHERE DATE(a.data_hora) < :data_limite
            ");
            $stmt->execute([':data_limite' => $data_limite]);
            $detalhes_removidos = $stmt->rowCount();
            
            // Depois, remove registros principais
            $stmt = $this->db->prepare("
                DELETE FROM Auditoria
                WHERE DATE(data_hora) < :data_limite
            ");
            $stmt->execute([':data_limite' => $data_limite]);
            $registros_removidos = $stmt->rowCount();
            
            // Registra a limpeza
            $this->registrar([
                'tabela' => 'Auditoria',
                'acao' => 'LIMPEZA',
                'detalhes' => [
                    'dias' => $dias,
                    'data_limite' => $data_limite,
                    'registros_removidos' => $registros_removidos,
                    'detalhes_removidos' => $detalhes_removidos
                ]
            ]);
            
            return [
                'registros' => $registros_removidos,
                'detalhes' => $detalhes_removidos
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao limpar registros antigos: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtém o endereço IP real do cliente
     */
    private function getIpAddress() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Prepara valor para armazenamento
     */
    private function prepararValor($valor) {
        if (is_array($valor) || is_object($valor)) {
            return json_encode($valor, JSON_UNESCAPED_UNICODE);
        }
        
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        
        if ($valor === null || $valor === '') {
            return null;
        }
        
        return (string)$valor;
    }
    
    /**
     * Calcula data de início baseado no período
     */
    private function calcularDataInicio($periodo) {
        switch ($periodo) {
            case 'hoje':
                return date('Y-m-d');
            case 'semana':
                return date('Y-m-d', strtotime('-7 days'));
            case 'mes':
                return date('Y-m-d', strtotime('-30 days'));
            case 'trimestre':
                return date('Y-m-d', strtotime('-90 days'));
            case 'ano':
                return date('Y-m-d', strtotime('-365 days'));
            default:
                return date('Y-m-d', strtotime('-30 days'));
        }
    }
    
    /**
     * Log para debug
     */
    private function logDebug($mensagem, $dados = null) {
        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            error_log("[AUDITORIA] $mensagem");
            if ($dados !== null) {
                error_log("[AUDITORIA DATA] " . print_r($dados, true));
            }
        }
        
        // SEMPRE logar dados críticos de funcionário
        if (isset($dados['funcionario']) && isset($dados['funcionario_nome_sessao'])) {
            error_log("[AUDITORIA FUNCIONARIO] ID: {$dados['funcionario']}, Nome Sessão: {$dados['funcionario_nome_sessao']}");
        }
    }
}
?>