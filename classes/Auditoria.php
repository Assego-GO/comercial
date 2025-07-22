<?php
/**
 * Classe para gerenciamento de auditoria do sistema
 * classes/Auditoria.php
 */

class Auditoria {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }
    
    /**
     * Registra uma ação na auditoria
     * 
     * @param array $dados Dados da auditoria
     * - tabela: Nome da tabela afetada
     * - acao: INSERT, UPDATE, DELETE, LOGIN, LOGOUT, etc
     * - registro_id: ID do registro afetado
     * - associado_id: ID do associado (se aplicável)
     * - funcionario_id: ID do funcionário que executou a ação
     * - alteracoes: Array com as alterações (para UPDATE)
     * - detalhes: Detalhes adicionais em formato livre
     * 
     * @return int|false ID da auditoria criada ou false em caso de erro
     */
    public function registrar($dados) {
        try {
            $this->db->beginTransaction();
            
            // Prepara dados básicos
            $tabela = $dados['tabela'] ?? '';
            $acao = $dados['acao'] ?? '';
            $registro_id = $dados['registro_id'] ?? null;
            $associado_id = $dados['associado_id'] ?? null;
            $funcionario_id = $dados['funcionario_id'] ?? $_SESSION['funcionario_id'] ?? null;
            $alteracoes = $dados['alteracoes'] ?? [];
            $detalhes = $dados['detalhes'] ?? [];
            
            // Adiciona informações do ambiente
            $ip_origem = $this->getIpAddress();
            $browser_info = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $sessao_id = session_id() ?: null;
            
            // Prepara JSON das alterações para o campo principal
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
            
            // Se houver alterações detalhadas (para UPDATE), registra em Auditoria_Detalhes
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
            
            // Log para debug
            $this->logDebug("Auditoria registrada", [
                'id' => $auditoria_id,
                'tabela' => $tabela,
                'acao' => $acao,
                'funcionario' => $funcionario_id
            ]);
            
            return $auditoria_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra login de usuário
     */
    public function registrarLogin($funcionario_id, $sucesso = true) {
        return $this->registrar([
            'tabela' => 'Funcionarios',
            'acao' => $sucesso ? 'LOGIN' : 'LOGIN_FALHA',
            'registro_id' => $funcionario_id,
            'funcionario_id' => $sucesso ? $funcionario_id : null,
            'detalhes' => [
                'sucesso' => $sucesso,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $this->getIpAddress()
            ]
        ]);
    }
    
    /**
     * Registra logout de usuário
     */
    public function registrarLogout($funcionario_id) {
        return $this->registrar([
            'tabela' => 'Funcionarios',
            'acao' => 'LOGOUT',
            'registro_id' => $funcionario_id,
            'funcionario_id' => $funcionario_id,
            'detalhes' => [
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
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
     * Busca histórico de auditoria
     */
    public function buscarHistorico($filtros = []) {
        try {
            $sql = "
                SELECT 
                    a.*,
                    f.nome as funcionario_nome,
                    ass.nome as associado_nome
                FROM Auditoria a
                LEFT JOIN Funcionarios f ON a.funcionario_id = f.id
                LEFT JOIN Associados ass ON a.associado_id = ass.id
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
     * Estatísticas gerais
     */
    private function estatisticasGerais($data_inicio) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_acoes,
                COUNT(DISTINCT funcionario_id) as funcionarios_ativos,
                COUNT(DISTINCT associado_id) as associados_afetados,
                COUNT(DISTINCT DATE(data_hora)) as dias_com_atividade,
                COUNT(DISTINCT ip_origem) as ips_unicos
            FROM Auditoria
            WHERE DATE(data_hora) >= :data_inicio
        ");
        
        $stmt->execute([':data_inicio' => $data_inicio]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Estatísticas por funcionário
     */
    private function estatisticasPorFuncionario($data_inicio) {
        $stmt = $this->db->prepare("
            SELECT 
                f.id,
                f.nome,
                COUNT(a.id) as total_acoes,
                COUNT(DISTINCT DATE(a.data_hora)) as dias_ativos,
                COUNT(DISTINCT a.tabela) as tabelas_acessadas,
                MAX(a.data_hora) as ultima_acao
            FROM Funcionarios f
            LEFT JOIN Auditoria a ON f.id = a.funcionario_id 
                AND DATE(a.data_hora) >= :data_inicio
            GROUP BY f.id
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
    }
}
?>