<?php
/**
 * Classe para gerenciar notificações do sistema
 * classes/NotificacoesManager.php
 */
class NotificacoesManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }
    
    /**
     * Cria uma nova notificação para o departamento financeiro
     */
    public function criarNotificacaoFinanceiro($associado_id, $tipo, $titulo, $mensagem, $dados_alteracao = null, $criado_por = null, $prioridade = 'MEDIA') {
        // ID do departamento financeiro (baseado na sua tabela)
        $departamento_financeiro_id = 2;
        
        return $this->criarNotificacao(
            $departamento_financeiro_id,
            null, // null = todos do departamento
            $associado_id,
            $tipo,
            $titulo,
            $mensagem,
            $dados_alteracao,
            $criado_por,
            $prioridade
        );
    }
    
    /**
     * Cria uma notificação genérica
     */
    public function criarNotificacao($departamento_id, $funcionario_id, $associado_id, $tipo, $titulo, $mensagem, $dados_alteracao = null, $criado_por = null, $prioridade = 'MEDIA') {
        try {
            $sql = "INSERT INTO Notificacoes (
                        departamento_id, funcionario_id, associado_id, tipo, 
                        titulo, mensagem, dados_alteracao, criado_por, prioridade
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $dados_json = $dados_alteracao ? json_encode($dados_alteracao) : null;
            
            $result = $stmt->execute([
                $departamento_id,
                $funcionario_id,
                $associado_id,
                $tipo,
                $titulo,
                $mensagem,
                $dados_json,
                $criado_por,
                $prioridade
            ]);
            
            if ($result) {
                error_log("✓ Notificação criada: $titulo para departamento $departamento_id");
                return $this->db->lastInsertId();
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca notificações não lidas para um departamento
     */
    public function buscarNotificacoesDepartamento($departamento_id, $limite = 20, $apenas_nao_lidas = true) {
        try {
            $where_clause = "n.departamento_id = ?";
            $params = [$departamento_id];
            
            if ($apenas_nao_lidas) {
                $where_clause .= " AND n.lida = 0";
            }
            
            $sql = "SELECT 
                        n.*, 
                        a.nome as associado_nome,
                        a.cpf as associado_cpf,
                        f.nome as criado_por_nome,
                        TIMESTAMPDIFF(MINUTE, n.data_criacao, NOW()) as minutos_atras
                    FROM Notificacoes n
                    LEFT JOIN Associados a ON n.associado_id = a.id
                    LEFT JOIN Funcionarios f ON n.criado_por = f.id
                    WHERE $where_clause 
                    AND n.ativo = 1
                    ORDER BY n.data_criacao DESC
                    LIMIT ?";
            
            $params[] = $limite;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao buscar notificações: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca notificações para um funcionário específico
     * CORRIGIDO: Query simplificada para buscar por departamento
     */
    public function buscarNotificacoesFuncionario($funcionario_id, $limite = 20) {
        try {
            // Query corrigida: busca simples por departamento do funcionário
            $sql = "SELECT 
                        n.*, 
                        a.nome as associado_nome,
                        a.cpf as associado_cpf,
                        f.nome as criado_por_nome,
                        func.departamento_id,
                        TIMESTAMPDIFF(MINUTE, n.data_criacao, NOW()) as minutos_atras
                    FROM Notificacoes n
                    LEFT JOIN Associados a ON n.associado_id = a.id
                    LEFT JOIN Funcionarios f ON n.criado_por = f.id
                    INNER JOIN Funcionarios func ON func.id = ?
                    WHERE (
                        n.funcionario_id = ? OR 
                        (n.funcionario_id IS NULL AND n.departamento_id = func.departamento_id)
                    )
                    AND n.ativo = 1
                    AND n.lida = 0
                    ORDER BY n.prioridade DESC, n.data_criacao DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$funcionario_id, $funcionario_id, $limite]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro ao buscar notificações do funcionário: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marca notificação como lida
     */
    public function marcarComoLida($notificacao_id, $funcionario_id = null) {
        try {
            $sql = "UPDATE Notificacoes SET lida = 1, data_leitura = NOW() WHERE id = ?";
            $params = [$notificacao_id];
            
            // Se especificado funcionário, validar se ele pode marcar esta notificação
            if ($funcionario_id) {
                $sql .= " AND (funcionario_id = ? OR funcionario_id IS NULL)";
                $params[] = $funcionario_id;
            }
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Conta notificações não lidas
     * CORRIGIDO: Query simplificada para buscar por departamento
     */
    public function contarNaoLidas($funcionario_id) {
        try {
            // Query corrigida: busca simples por departamento do funcionário
            $sql = "SELECT COUNT(*) as total
                    FROM Notificacoes n
                    INNER JOIN Funcionarios f ON f.id = ?
                    WHERE (
                        n.funcionario_id = ? OR 
                        (n.funcionario_id IS NULL AND n.departamento_id = f.departamento_id)
                    )
                    AND n.ativo = 1
                    AND n.lida = 0";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$funcionario_id, $funcionario_id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Erro ao contar notificações: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * MÉTODOS ESPECÍFICOS PARA OS CASOS DE USO
     */
    
    /**
     * Notifica alteração nos dados financeiros
     */
    public function notificarAlteracaoFinanceiro($associado_id, $campos_alterados, $criado_por = null) {
        // Busca nome do associado
        $stmt = $this->db->prepare("SELECT nome, cpf FROM Associados WHERE id = ?");
        $stmt->execute([$associado_id]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$associado) return false;
        
        $titulo = "💰 Dados Financeiros Alterados";
        $mensagem = "Os dados financeiros do associado {$associado['nome']} (CPF: " . $this->formatarCPF($associado['cpf']) . ") foram alterados.";
        
        if (!empty($campos_alterados)) {
            $mensagem .= "\n\nCampos alterados: " . implode(', ', array_keys($campos_alterados));
        }
        
        return $this->criarNotificacaoFinanceiro(
            $associado_id,
            'ALTERACAO_FINANCEIRO',
            $titulo,
            $mensagem,
            $campos_alterados,
            $criado_por,
            'ALTA'
        );
    }
    
    /**
     * Notifica nova observação
     */
    public function notificarNovaObservacao($associado_id, $observacao_texto, $categoria, $criado_por = null) {
        // Busca nome do associado
        $stmt = $this->db->prepare("SELECT nome, cpf FROM Associados WHERE id = ?");
        $stmt->execute([$associado_id]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$associado) return false;
        
        $titulo = "📝 Nova Observação Registrada";
        $mensagem = "Nova observação foi registrada para {$associado['nome']} (CPF: " . $this->formatarCPF($associado['cpf']) . ")";
        
        if ($categoria) {
            $mensagem .= "\nCategoria: " . ucfirst($categoria);
        }
        
        // Prioridade baseada na categoria
        $prioridade = 'MEDIA';
        if (in_array($categoria, ['importante', 'pendencia', 'urgente'])) {
            $prioridade = 'ALTA';
        }
        
        return $this->criarNotificacaoFinanceiro(
            $associado_id,
            'NOVA_OBSERVACAO',
            $titulo,
            $mensagem,
            ['categoria' => $categoria, 'observacao_resumo' => substr($observacao_texto, 0, 100) . '...'],
            $criado_por,
            $prioridade
        );
    }
    
    /**
     * Notifica alteração em qualquer cadastro
     */
    public function notificarAlteracaoCadastro($associado_id, $campos_alterados, $criado_por = null) {
        // Só notifica se alterou dados que interessam ao financeiro
        $campos_interesse_financeiro = [
            'situacao', 'tipoAssociado', 'situacaoFinanceira', 
            'vinculoServidor', 'agencia', 'contaCorrente', 'localDebito'
        ];
        
        $campos_relevantes = array_intersect(array_keys($campos_alterados), $campos_interesse_financeiro);
        
        if (empty($campos_relevantes)) {
            return false; // Não interessa ao financeiro
        }
        
        // Busca nome do associado
        $stmt = $this->db->prepare("SELECT nome, cpf FROM Associados WHERE id = ?");
        $stmt->execute([$associado_id]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$associado) return false;
        
        $titulo = "📋 Cadastro Alterado";
        $mensagem = "O cadastro do associado {$associado['nome']} (CPF: " . $this->formatarCPF($associado['cpf']) . ") foi alterado em dados relevantes para o financeiro.";
        $mensagem .= "\n\nCampos alterados: " . implode(', ', $campos_relevantes);
        
        return $this->criarNotificacaoFinanceiro(
            $associado_id,
            'ALTERACAO_CADASTRO',
            $titulo,
            $mensagem,
            array_intersect_key($campos_alterados, array_flip($campos_relevantes)),
            $criado_por,
            'MEDIA'
        );
    }
    
    /**
     * Utilitário para formatar CPF
     */
    private function formatarCPF($cpf) {
        if (!$cpf) return '';
        $cpf = preg_replace('/\D/', '', $cpf);
        $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }
}
?>