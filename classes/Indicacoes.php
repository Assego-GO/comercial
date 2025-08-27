<?php
/**
 * Classe para gerenciar indicações e indicadores
 * classes/Indicacoes.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Database.php';

class Indicacoes 
{
    private $db;
    
    public function __construct() 
    {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }
    
    /**
     * Processa uma indicação - cria/atualiza indicador e registra histórico
     * 
     * @param int $associadoId ID do associado indicado
     * @param string $nomeIndicador Nome do indicador
     * @param string|null $patenteIndicador Patente do indicador (opcional)
     * @param string|null $corporacaoIndicador Corporação do indicador (opcional)
     * @param int|null $funcionarioId ID do funcionário que está registrando
     * @param string|null $observacao Observações sobre a indicação
     * @return array Resultado da operação
     */
    public function processarIndicacao($associadoId, $nomeIndicador, $patenteIndicador = null, 
                                      $corporacaoIndicador = null, $funcionarioId = null, 
                                      $observacao = null) 
    {
        try {
            error_log("=== PROCESSANDO INDICAÇÃO ===");
            error_log("Associado ID: $associadoId | Indicador: $nomeIndicador");
            
            // Validação básica
            if (empty($associadoId) || empty($nomeIndicador)) {
                throw new Exception("Associado ID e nome do indicador são obrigatórios");
            }
            
            // Limpa e normaliza o nome
            $nomeIndicador = trim($nomeIndicador);
            $patenteIndicador = !empty($patenteIndicador) ? trim($patenteIndicador) : null;
            $corporacaoIndicador = !empty($corporacaoIndicador) ? trim($corporacaoIndicador) : null;
            
            $this->db->beginTransaction();
            
            // 1. Verifica se o indicador já existe
            $indicadorId = $this->obterOuCriarIndicador($nomeIndicador, $patenteIndicador, $corporacaoIndicador);
            
            // 2. Verifica se já existe indicação para este associado
            $stmt = $this->db->prepare("
                SELECT id FROM Historico_Indicacoes 
                WHERE associado_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$associadoId]);
            $indicacaoExistente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($indicacaoExistente) {
                error_log("⚠ Associado já possui indicação registrada. Atualizando...");
                
                // Atualiza a indicação existente
                $stmt = $this->db->prepare("
                    UPDATE Historico_Indicacoes 
                    SET indicador_id = ?,
                        indicador_nome = ?,
                        funcionario_id = ?,
                        observacao = ?,
                        data_indicacao = NOW()
                    WHERE associado_id = ?
                ");
                $stmt->execute([
                    $indicadorId,
                    $nomeIndicador,
                    $funcionarioId,
                    $observacao,
                    $associadoId
                ]);
                
                error_log("✓ Indicação atualizada no histórico");
            } else {
                // 3. Registra no histórico de indicações
                $stmt = $this->db->prepare("
                    INSERT INTO Historico_Indicacoes (
                        associado_id, 
                        indicador_id, 
                        indicador_nome,
                        funcionario_id,
                        observacao,
                        data_indicacao
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $associadoId,
                    $indicadorId,
                    $nomeIndicador,
                    $funcionarioId,
                    $observacao
                ]);
                
                error_log("✓ Nova indicação registrada no histórico");
            }
            
            // 4. Atualiza contador de indicações do indicador
            $stmt = $this->db->prepare("
                UPDATE Indicadores 
                SET total_indicacoes = (
                    SELECT COUNT(DISTINCT associado_id) 
                    FROM Historico_Indicacoes 
                    WHERE indicador_id = ?
                )
                WHERE id = ?
            ");
            $stmt->execute([$indicadorId, $indicadorId]);
            
            // 5. Atualiza o campo indicacao na tabela Associados (para compatibilidade)
            $stmt = $this->db->prepare("
                UPDATE Associados 
                SET indicacao = ?
                WHERE id = ?
            ");
            $stmt->execute([$nomeIndicador, $associadoId]);
            
            $this->db->commit();
            
            error_log("✓ Indicação processada com sucesso - Indicador ID: $indicadorId");
            
            return [
                'sucesso' => true,
                'indicador_id' => $indicadorId,
                'indicador_nome' => $nomeIndicador,
                'novo_indicador' => isset($novoIndicador) ? $novoIndicador : false,
                'mensagem' => 'Indicação registrada com sucesso'
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            error_log("✗ Erro ao processar indicação: " . $e->getMessage());
            
            return [
                'sucesso' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém ou cria um indicador
     * 
     * @param string $nome Nome completo do indicador
     * @param string|null $patente Patente do indicador
     * @param string|null $corporacao Corporação do indicador
     * @return int ID do indicador
     */
    private function obterOuCriarIndicador($nome, $patente = null, $corporacao = null) 
    {
        // Busca indicador existente pelo nome
        $stmt = $this->db->prepare("
            SELECT id, patente, corporacao 
            FROM Indicadores 
            WHERE nome_completo = ?
            LIMIT 1
        ");
        $stmt->execute([$nome]);
        $indicador = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($indicador) {
            // Indicador existe - atualiza dados se necessário
            $precisaAtualizar = false;
            $updates = [];
            $params = [];
            
            // Verifica se precisa atualizar patente
            if (!empty($patente) && $indicador['patente'] != $patente) {
                $updates[] = "patente = ?";
                $params[] = $patente;
                $precisaAtualizar = true;
            }
            
            // Verifica se precisa atualizar corporação
            if (!empty($corporacao) && $indicador['corporacao'] != $corporacao) {
                $updates[] = "corporacao = ?";
                $params[] = $corporacao;
                $precisaAtualizar = true;
            }
            
            if ($precisaAtualizar) {
                $params[] = $indicador['id'];
                $sql = "UPDATE Indicadores SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                error_log("✓ Indicador atualizado - ID: " . $indicador['id']);
            }
            
            return $indicador['id'];
        } else {
            // Cria novo indicador
            $stmt = $this->db->prepare("
                INSERT INTO Indicadores (
                    nome_completo, 
                    patente, 
                    corporacao,
                    ativo,
                    total_indicacoes,
                    data_cadastro
                ) VALUES (?, ?, ?, 1, 0, NOW())
            ");
            
            $stmt->execute([$nome, $patente, $corporacao]);
            $indicadorId = $this->db->lastInsertId();
            
            error_log("✓ Novo indicador criado - ID: $indicadorId | Nome: $nome");
            
            return $indicadorId;
        }
    }
    
    /**
     * Obtém estatísticas de indicações
     * 
     * @param int|null $indicadorId ID do indicador (opcional)
     * @return array Estatísticas
     */
    public function obterEstatisticas($indicadorId = null) 
    {
        try {
            if ($indicadorId) {
                // Estatísticas de um indicador específico
                $stmt = $this->db->prepare("
                    SELECT 
                        i.*,
                        COUNT(DISTINCT hi.associado_id) as total_indicacoes_real,
                        MAX(hi.data_indicacao) as ultima_indicacao
                    FROM Indicadores i
                    LEFT JOIN Historico_Indicacoes hi ON i.id = hi.indicador_id
                    WHERE i.id = ?
                    GROUP BY i.id
                ");
                $stmt->execute([$indicadorId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Top 10 indicadores
                $stmt = $this->db->prepare("
                    SELECT 
                        i.*,
                        COUNT(DISTINCT hi.associado_id) as total_indicacoes_real
                    FROM Indicadores i
                    LEFT JOIN Historico_Indicacoes hi ON i.id = hi.indicador_id
                    WHERE i.ativo = 1
                    GROUP BY i.id
                    ORDER BY total_indicacoes_real DESC
                    LIMIT 10
                ");
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca indicação de um associado
     * 
     * @param int $associadoId ID do associado
     * @return array|null Dados da indicação
     */
    public function obterIndicacaoAssociado($associadoId) 
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    hi.*,
                    i.nome_completo as indicador_nome_atual,
                    i.patente as indicador_patente,
                    i.corporacao as indicador_corporacao,
                    i.total_indicacoes,
                    f.nome as funcionario_nome
                FROM Historico_Indicacoes hi
                LEFT JOIN Indicadores i ON hi.indicador_id = i.id
                LEFT JOIN Funcionarios f ON hi.funcionario_id = f.id
                WHERE hi.associado_id = ?
                ORDER BY hi.data_indicacao DESC
                LIMIT 1
            ");
            
            $stmt->execute([$associadoId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar indicação: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Remove indicação de um associado
     * 
     * @param int $associadoId ID do associado
     * @return bool Sucesso
     */
    public function removerIndicacao($associadoId) 
    {
        try {
            $this->db->beginTransaction();
            
            // Remove do histórico
            $stmt = $this->db->prepare("DELETE FROM Historico_Indicacoes WHERE associado_id = ?");
            $stmt->execute([$associadoId]);
            
            // Limpa campo na tabela Associados
            $stmt = $this->db->prepare("UPDATE Associados SET indicacao = NULL WHERE id = ?");
            $stmt->execute([$associadoId]);
            
            // Atualiza contadores de todos os indicadores afetados
            $stmt = $this->db->prepare("
                UPDATE Indicadores i
                SET total_indicacoes = (
                    SELECT COUNT(DISTINCT associado_id) 
                    FROM Historico_Indicacoes 
                    WHERE indicador_id = i.id
                )
            ");
            $stmt->execute();
            
            $this->db->commit();
            
            error_log("✓ Indicação removida para associado ID: $associadoId");
            return true;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro ao remover indicação: " . $e->getMessage());
            return false;
        }
    }
}