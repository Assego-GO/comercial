<?php
/**
 * Classe para gerenciamento de associados - VERSÃO COM PRÉ-CADASTRO
 * classes/Associados.php
 */

class Associados
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }

    /**
     * Busca associado por ID com todos os dados relacionados
     */
    public function getById($id)
    {
        try {
            // Busca dados principais incluindo pré-cadastro
            $stmt = $this->db->prepare("
                SELECT 
                    a.*,
                    c.dataFiliacao as data_filiacao,
                    c.dataDesfiliacao as data_desfiliacao,
                    m.corporacao,
                    m.patente,
                    m.categoria,
                    m.lotacao,
                    m.unidade,
                    f.tipoAssociado,
                    f.situacaoFinanceira,
                    f.vinculoServidor,
                    f.localDebito,
                    f.agencia,
                    f.operacao,
                    f.contaCorrente,
                    e.cep,
                    e.endereco,
                    e.bairro,
                    e.cidade,
                    e.numero,
                    e.complemento,
                    fpc.status as status_pre_cadastro,
                    fpc.data_envio_presidencia,
                    fpc.data_retorno_presidencia,
                    func_aprovacao.nome as nome_aprovador
                FROM Associados a
                LEFT JOIN Contrato c ON a.id = c.associado_id
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                LEFT JOIN Endereco e ON a.id = e.associado_id
                LEFT JOIN Fluxo_Pre_Cadastro fpc ON a.id = fpc.associado_id
                LEFT JOIN Funcionarios func_aprovacao ON a.aprovado_por = func_aprovacao.id
                WHERE a.id = ?
            ");

            $stmt->execute([$id]);
            $associado = $stmt->fetch();

            if (!$associado) {
                return false;
            }

            // Adiciona dados relacionados
            $associado['dependentes'] = $this->getDependentes($id);
            $associado['redesSociais'] = $this->getRedesSociais($id);
            $associado['servicos'] = $this->getServicos($id);
            $associado['documentos'] = $this->getDocumentos($id);
            $associado['historico_fluxo'] = $this->getHistoricoFluxo($id);

            return $associado;

        } catch (PDOException $e) {
            error_log("Erro ao buscar associado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista todos os associados com filtros
     */
    public function listar($filtros = [])
    {
        try {
            $sql = "
                SELECT 
                    a.id,
                    a.nome,
                    a.nasc,
                    a.sexo,
                    a.rg,
                    a.cpf,
                    a.email,
                    a.situacao,
                    a.pre_cadastro,
                    a.data_pre_cadastro,
                    a.data_aprovacao,
                    a.escolaridade,
                    a.estadoCivil,
                    a.telefone,
                    a.foto,
                    a.indicacao,
                    c.dataFiliacao as data_filiacao,
                    c.dataDesfiliacao as data_desfiliacao,
                    m.corporacao,
                    m.patente,
                    m.categoria,
                    m.lotacao,
                    m.unidade,
                    f.tipoAssociado,
                    f.situacaoFinanceira,
                    f.vinculoServidor,
                    f.localDebito,
                    e.cidade,
                    e.bairro,
                    fpc.status as status_pre_cadastro,
                    func_aprovacao.nome as nome_aprovador
                FROM Associados a
                LEFT JOIN Contrato c ON a.id = c.associado_id
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                LEFT JOIN Endereco e ON a.id = e.associado_id
                LEFT JOIN Fluxo_Pre_Cadastro fpc ON a.id = fpc.associado_id
                LEFT JOIN Funcionarios func_aprovacao ON a.aprovado_por = func_aprovacao.id
                WHERE 1=1
            ";

            $params = [];

            // Filtro de pré-cadastro
            if (isset($filtros['pre_cadastro'])) {
                $sql .= " AND a.pre_cadastro = ?";
                $params[] = $filtros['pre_cadastro'];
            }

            // Filtro de status do fluxo
            if (!empty($filtros['status_fluxo'])) {
                $sql .= " AND fpc.status = ?";
                $params[] = $filtros['status_fluxo'];
            }

            // Aplicar outros filtros existentes
            if (!empty($filtros['situacao'])) {
                $sql .= " AND a.situacao = ?";
                $params[] = $filtros['situacao'];
            }

            if (!empty($filtros['corporacao'])) {
                $sql .= " AND m.corporacao = ?";
                $params[] = $filtros['corporacao'];
            }

            if (!empty($filtros['patente'])) {
                $sql .= " AND m.patente = ?";
                $params[] = $filtros['patente'];
            }

            if (!empty($filtros['busca'])) {
                $sql .= " AND (
                    a.nome LIKE ? OR 
                    a.cpf LIKE ? OR 
                    a.rg LIKE ? OR 
                    a.telefone LIKE ? OR
                    a.email LIKE ?
                )";
                $busca = "%{$filtros['busca']}%";
                $params = array_merge($params, [$busca, $busca, $busca, $busca, $busca]);
            }

            if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
                $sql .= " AND c.dataFiliacao BETWEEN ? AND ?";
                $params[] = $filtros['data_inicio'];
                $params[] = $filtros['data_fim'];
            }

            // Ordenação
            $sql .= " ORDER BY a.pre_cadastro DESC, a.id DESC";

            // Limite e offset para paginação
            if (isset($filtros['limit']) && isset($filtros['offset'])) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = intval($filtros['limit']);
                $params[] = intval($filtros['offset']);
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("Erro ao listar associados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Criar novo associado (SEMPRE como pré-cadastro)
     */
    public function criar($dados)
    {
        try {
            $this->db->beginTransaction();

            // Validações básicas
            if (empty($dados['nome']) || empty($dados['cpf'])) {
                throw new Exception("Nome e CPF são obrigatórios");
            }

            // Verifica se CPF já existe
            if ($this->cpfExiste($dados['cpf'])) {
                throw new Exception("CPF já cadastrado");
            }

            // SEMPRE cria como pré-cadastro
            $stmt = $this->db->prepare("
            INSERT INTO Associados (
                nome, nasc, sexo, rg, cpf, email, situacao, 
                escolaridade, estadoCivil, telefone, foto, indicacao,
                pre_cadastro, data_pre_cadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

            $stmt->execute([
                $dados['nome'],
                $dados['nasc'] ?? null,
                $dados['sexo'] ?? null,
                $dados['rg'] ?? null,
                $dados['cpf'],
                $dados['email'] ?? null,
                $dados['situacao'] ?? 'Filiado',
                $dados['escolaridade'] ?? null,
                $dados['estadoCivil'] ?? null,
                $dados['telefone'] ?? null,
                $dados['foto'] ?? null,
                $dados['indicacao'] ?? null
            ]);

            $associadoId = $this->db->lastInsertId();

            // VERIFICAR se já existe fluxo antes de criar
            $stmt = $this->db->prepare("SELECT id FROM Fluxo_Pre_Cadastro WHERE associado_id = ?");
            $stmt->execute([$associadoId]);
            $fluxoExistente = $stmt->fetch();

            if (!$fluxoExistente) {
                // Criar registro no fluxo de pré-cadastro APENAS se não existir
                try {
                    $stmt = $this->db->prepare("
                    INSERT INTO Fluxo_Pre_Cadastro (
                        associado_id, status, created_at
                    ) VALUES (?, 'AGUARDANDO_DOCUMENTOS', NOW())
                ");
                    $stmt->execute([$associadoId]);

                    $fluxoId = $this->db->lastInsertId();

                    // Registrar no histórico do fluxo
                    $this->registrarHistoricoFluxo(
                        $fluxoId,
                        $associadoId,
                        null,
                        'AGUARDANDO_DOCUMENTOS',
                        'Pré-cadastro criado',
                        $_SESSION['funcionario_id'] ?? null
                    );

                    error_log("✓ Fluxo de pré-cadastro criado para associado $associadoId");
                } catch (PDOException $e) {
                    // Se der erro de duplicação, apenas loga e continua
                    if ($e->getCode() == '23000') {
                        error_log("⚠ Fluxo já existe para associado $associadoId, continuando...");
                    } else {
                        throw $e;
                    }
                }
            } else {
                error_log("ℹ Fluxo já existente para associado $associadoId (ID: {$fluxoExistente['id']})");
            }

            // Inserir contrato
            if (!empty($dados['dataFiliacao'])) {
                $stmt = $this->db->prepare("
                INSERT INTO Contrato (associado_id, dataFiliacao, dataDesfiliacao)
                VALUES (?, ?, ?)
            ");
                $stmt->execute([
                    $associadoId,
                    $dados['dataFiliacao'],
                    $dados['dataDesfiliacao'] ?? null
                ]);
            }

            // Inserir dados militares
            if (!empty($dados['corporacao']) || !empty($dados['patente'])) {
                $stmt = $this->db->prepare("
                INSERT INTO Militar (
                    associado_id, corporacao, patente, categoria, lotacao, unidade
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
                $stmt->execute([
                    $associadoId,
                    $dados['corporacao'] ?? null,
                    $dados['patente'] ?? null,
                    $dados['categoria'] ?? null,
                    $dados['lotacao'] ?? null,
                    $dados['unidade'] ?? null
                ]);
            }

            // Inserir dados financeiros
            if (!empty($dados['tipoAssociado'])) {
                $stmt = $this->db->prepare("
                INSERT INTO Financeiro (
                    associado_id, tipoAssociado, situacaoFinanceira, 
                    vinculoServidor, localDebito, agencia, operacao, contaCorrente
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
                $stmt->execute([
                    $associadoId,
                    $dados['tipoAssociado'] ?? null,
                    $dados['situacaoFinanceira'] ?? null,
                    $dados['vinculoServidor'] ?? null,
                    $dados['localDebito'] ?? null,
                    $dados['agencia'] ?? null,
                    $dados['operacao'] ?? null,
                    $dados['contaCorrente'] ?? null
                ]);
            }

            // Inserir endereço
            if (!empty($dados['cep']) || !empty($dados['endereco'])) {
                $stmt = $this->db->prepare("
                INSERT INTO Endereco (
                    associado_id, cep, endereco, bairro, cidade, numero, complemento
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
                $stmt->execute([
                    $associadoId,
                    $dados['cep'] ?? null,
                    $dados['endereco'] ?? null,
                    $dados['bairro'] ?? null,
                    $dados['cidade'] ?? null,
                    $dados['numero'] ?? null,
                    $dados['complemento'] ?? null
                ]);
            }

            // Inserir dependentes
            if (!empty($dados['dependentes']) && is_array($dados['dependentes'])) {
                foreach ($dados['dependentes'] as $dep) {
                    if (!empty($dep['nome'])) {
                        $this->adicionarDependente($associadoId, $dep);
                    }
                }
            }

            // Registrar na auditoria
            $this->registrarAuditoria('INSERT', $associadoId, $dados);

            $this->db->commit();
            return $associadoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao criar associado: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar pré-cadastro para presidência
     */
    public function enviarParaPresidencia($associadoId, $observacoes = null)
    {
        try {
            // Cria conexão própria se necessário
            if (!$this->db) {
                $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
            }

            // Inicia transação própria
            $transactionStarted = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionStarted = true;
            }

            try {
                // Verifica se é pré-cadastro
                $stmt = $this->db->prepare("SELECT pre_cadastro FROM Associados WHERE id = ?");
                $stmt->execute([$associadoId]);
                $associado = $stmt->fetch();

                if (!$associado || $associado['pre_cadastro'] != 1) {
                    throw new Exception("Associado não está em pré-cadastro");
                }

                // Verifica se já existe registro no Fluxo_Pre_Cadastro
                $stmt = $this->db->prepare("SELECT id, status FROM Fluxo_Pre_Cadastro WHERE associado_id = ?");
                $stmt->execute([$associadoId]);
                $fluxo = $stmt->fetch();

                if (!$fluxo) {
                    // Se não existe, cria o registro
                    $stmt = $this->db->prepare("
                    INSERT INTO Fluxo_Pre_Cadastro (
                        associado_id, 
                        status, 
                        data_envio_presidencia,
                        funcionario_envio_id,
                        observacoes,
                        created_at
                    ) VALUES (?, 'ENVIADO_PRESIDENCIA', NOW(), ?, ?, NOW())
                ");
                    $stmt->execute([
                        $associadoId,
                        $_SESSION['funcionario_id'] ?? null,
                        $observacoes
                    ]);
                    $fluxoId = $this->db->lastInsertId();
                    $statusAnterior = null;
                } else {
                    // Se existe, apenas atualiza
                    $fluxoId = $fluxo['id'];
                    $statusAnterior = $fluxo['status'];

                    // Atualiza o fluxo
                    $stmt = $this->db->prepare("
                    UPDATE Fluxo_Pre_Cadastro 
                    SET status = 'ENVIADO_PRESIDENCIA',
                        data_envio_presidencia = NOW(),
                        funcionario_envio_id = ?,
                        observacoes = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                    $stmt->execute([
                        $_SESSION['funcionario_id'] ?? null,
                        $observacoes,
                        $fluxoId
                    ]);
                }

                // Tenta registrar no histórico (não crítico se falhar)
                try {
                    // Verifica se a tabela de histórico existe
                    $stmt = $this->db->query("SHOW TABLES LIKE 'Historico_Fluxo_Pre_Cadastro'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $this->db->prepare("
                        INSERT INTO Historico_Fluxo_Pre_Cadastro (
                            fluxo_id, associado_id, status_anterior, status_novo, 
                            funcionario_id, observacao, data_hora
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                        $stmt->execute([
                            $fluxoId,
                            $associadoId,
                            $statusAnterior,
                            'ENVIADO_PRESIDENCIA',
                            $_SESSION['funcionario_id'] ?? null,
                            $observacoes ?? 'Pré-cadastro enviado para aprovação'
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("Aviso: Erro ao registrar histórico: " . $e->getMessage());
                    // Continua - não é crítico
                }

                // Commit se iniciamos a transação
                if ($transactionStarted) {
                    $this->db->commit();
                }

                error_log("✓ Pré-cadastro {$associadoId} marcado como ENVIADO_PRESIDENCIA");
                return true;

            } catch (Exception $e) {
                // Rollback apenas se iniciamos a transação
                if ($transactionStarted && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Erro em enviarParaPresidencia: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Aprovar pré-cadastro (transforma em cadastro definitivo)
     */
    public function aprovarPreCadastro($associadoId, $documentoAssinado = null, $observacoes = null)
    {
        try {
            $this->db->beginTransaction();

            // Verifica se é pré-cadastro
            $stmt = $this->db->prepare("SELECT pre_cadastro FROM Associados WHERE id = ?");
            $stmt->execute([$associadoId]);
            $associado = $stmt->fetch();

            if (!$associado || $associado['pre_cadastro'] != 1) {
                throw new Exception("Associado não está em pré-cadastro");
            }

            // Busca fluxo
            $stmt = $this->db->prepare("SELECT id, status FROM Fluxo_Pre_Cadastro WHERE associado_id = ?");
            $stmt->execute([$associadoId]);
            $fluxo = $stmt->fetch();

            if (!$fluxo) {
                throw new Exception("Fluxo de pré-cadastro não encontrado");
            }

            // Atualiza associado para cadastro definitivo
            $stmt = $this->db->prepare("
                UPDATE Associados 
                SET pre_cadastro = 0,
                    data_aprovacao = NOW(),
                    aprovado_por = ?,
                    observacao_aprovacao = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_SESSION['funcionario_id'] ?? null,
                $observacoes,
                $associadoId
            ]);

            // Atualiza fluxo
            $stmt = $this->db->prepare("
                UPDATE Fluxo_Pre_Cadastro 
                SET status = 'APROVADO',
                    data_retorno_presidencia = NOW(),
                    funcionario_recebimento_id = ?,
                    documento_assinado_path = ?,
                    observacoes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_SESSION['funcionario_id'] ?? null,
                $documentoAssinado,
                $observacoes,
                $fluxo['id']
            ]);

            // Registra no histórico
            $this->registrarHistoricoFluxo(
                $fluxo['id'],
                $associadoId,
                $fluxo['status'],
                'APROVADO',
                'Cadastro aprovado pela presidência',
                $_SESSION['funcionario_id'] ?? null
            );

            // Registra na auditoria
            $this->registrarAuditoria(
                'APROVAR_PRE_CADASTRO',
                $associadoId,
                ['aprovado' => true, 'observacoes' => $observacoes]
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao aprovar pré-cadastro: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Rejeitar pré-cadastro
     */
    public function rejeitarPreCadastro($associadoId, $motivo)
    {
        try {
            $this->db->beginTransaction();

            // Busca fluxo
            $stmt = $this->db->prepare("SELECT id, status FROM Fluxo_Pre_Cadastro WHERE associado_id = ?");
            $stmt->execute([$associadoId]);
            $fluxo = $stmt->fetch();

            if (!$fluxo) {
                throw new Exception("Fluxo de pré-cadastro não encontrado");
            }

            // Atualiza fluxo
            $stmt = $this->db->prepare("
                UPDATE Fluxo_Pre_Cadastro 
                SET status = 'REJEITADO',
                    data_retorno_presidencia = NOW(),
                    funcionario_recebimento_id = ?,
                    observacoes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $_SESSION['funcionario_id'] ?? null,
                $motivo,
                $fluxo['id']
            ]);

            // Atualiza situação do associado
            $stmt = $this->db->prepare("
                UPDATE Associados 
                SET situacao = 'Rejeitado',
                    observacao_aprovacao = ?
                WHERE id = ?
            ");
            $stmt->execute([$motivo, $associadoId]);

            // Registra no histórico
            $this->registrarHistoricoFluxo(
                $fluxo['id'],
                $associadoId,
                $fluxo['status'],
                'REJEITADO',
                $motivo,
                $_SESSION['funcionario_id'] ?? null
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao rejeitar pré-cadastro: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar histórico do fluxo de pré-cadastro
     */
    public function getHistoricoFluxo($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    hf.*,
                    f.nome as funcionario_nome
                FROM Historico_Fluxo_Pre_Cadastro hf
                LEFT JOIN Funcionarios f ON hf.funcionario_id = f.id
                WHERE hf.associado_id = ?
                ORDER BY hf.data_hora DESC
            ");
            $stmt->execute([$associadoId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar histórico do fluxo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Registrar histórico do fluxo
     */
    private function registrarHistoricoFluxo($fluxoId, $associadoId, $statusAnterior, $statusNovo, $observacao, $funcionarioId)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Historico_Fluxo_Pre_Cadastro (
                    fluxo_id, associado_id, status_anterior, status_novo, 
                    funcionario_id, observacao, data_hora
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $fluxoId,
                $associadoId,
                $statusAnterior,
                $statusNovo,
                $funcionarioId,
                $observacao
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar histórico do fluxo: " . $e->getMessage());
        }
    }

    /**
     * Contar pré-cadastros por status
     */
    public function contarPreCadastrosPorStatus()
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(CASE WHEN fpc.status = 'AGUARDANDO_DOCUMENTOS' THEN 1 END) as aguardando_documentos,
                    COUNT(CASE WHEN fpc.status = 'ENVIADO_PRESIDENCIA' THEN 1 END) as enviado_presidencia,
                    COUNT(CASE WHEN fpc.status = 'ASSINADO' THEN 1 END) as assinado,
                    COUNT(CASE WHEN fpc.status = 'APROVADO' THEN 1 END) as aprovado,
                    COUNT(CASE WHEN fpc.status = 'REJEITADO' THEN 1 END) as rejeitado,
                    COUNT(CASE WHEN a.pre_cadastro = 1 AND fpc.id IS NULL THEN 1 END) as sem_fluxo
                FROM Associados a
                LEFT JOIN Fluxo_Pre_Cadastro fpc ON a.id = fpc.associado_id
                WHERE a.pre_cadastro = 1
            ");
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao contar pré-cadastros: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar estatísticas (ATUALIZADA)
     */
    public function getEstatisticas()
    {
        try {
            $stats = [];

            // Total de associados (apenas cadastros definitivos)
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM Associados WHERE pre_cadastro = 0");
            $stats['total'] = $stmt->fetch()['total'];

            // Total de pré-cadastros
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM Associados WHERE pre_cadastro = 1");
            $stats['pre_cadastros'] = $stmt->fetch()['total'];

            // Associados ativos (apenas cadastros definitivos)
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado' AND pre_cadastro = 0");
            $stats['ativos'] = $stmt->fetch()['total'];

            // Associados inativos (apenas cadastros definitivos)
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Desfiliado' AND pre_cadastro = 0");
            $stats['inativos'] = $stmt->fetch()['total'];

            // Novos nos últimos 30 dias (apenas cadastros definitivos)
            $stmt = $this->db->query("
                SELECT COUNT(*) as total 
                FROM Associados a
                JOIN Contrato c ON a.id = c.associado_id
                WHERE c.dataFiliacao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND a.pre_cadastro = 0
            ");
            $stats['novos_30_dias'] = $stmt->fetch()['total'];

            // Pré-cadastros dos últimos 30 dias
            $stmt = $this->db->query("
                SELECT COUNT(*) as total 
                FROM Associados 
                WHERE data_pre_cadastro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND pre_cadastro = 1
            ");
            $stats['pre_cadastros_30_dias'] = $stmt->fetch()['total'];

            // Status dos pré-cadastros
            $stats['status_pre_cadastros'] = $this->contarPreCadastrosPorStatus();

            // Por corporação (apenas cadastros definitivos)
            $stmt = $this->db->query("
                SELECT m.corporacao, COUNT(*) as total
                FROM Associados a
                JOIN Militar m ON a.id = m.associado_id
                WHERE m.corporacao IS NOT NULL AND a.pre_cadastro = 0
                GROUP BY m.corporacao
                ORDER BY total DESC
            ");
            $stats['por_corporacao'] = $stmt->fetchAll();

            // Por patente (apenas cadastros definitivos)
            $stmt = $this->db->query("
                SELECT m.patente, COUNT(*) as total
                FROM Associados a
                JOIN Militar m ON a.id = m.associado_id
                WHERE m.patente IS NOT NULL AND a.pre_cadastro = 0
                GROUP BY m.patente
                ORDER BY total DESC
            ");
            $stats['por_patente'] = $stmt->fetchAll();

            return $stats;

        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }

    // ... (manter todos os outros métodos existentes) ...

    /**
     * Atualizar associado (mantém as funcionalidades existentes)
     */
    public function atualizar($id, $dados)
    {
        try {
            $this->db->beginTransaction();

            // Buscar dados atuais para auditoria
            $associadoAtual = $this->getById($id);
            if (!$associadoAtual) {
                throw new Exception("Associado não encontrado");
            }

            // Verificar CPF se mudou
            if (isset($dados['cpf']) && $dados['cpf'] != $associadoAtual['cpf']) {
                if ($this->cpfExiste($dados['cpf'], $id)) {
                    throw new Exception("CPF já cadastrado para outro associado");
                }
            }

            // Atualizar dados principais
            $campos = [];
            $valores = [];

            $camposPermitidos = [
                'nome',
                'nasc',
                'sexo',
                'rg',
                'cpf',
                'email',
                'situacao',
                'escolaridade',
                'estadoCivil',
                'telefone',
                'foto',
                'indicacao'
            ];

            foreach ($camposPermitidos as $campo) {
                if (isset($dados[$campo])) {
                    $campos[] = "$campo = ?";
                    $valores[] = $dados[$campo];
                }
            }

            if (!empty($campos)) {
                $valores[] = $id;
                $sql = "UPDATE Associados SET " . implode(", ", $campos) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }

            // Atualizar contrato
            if (isset($dados['dataFiliacao']) || isset($dados['dataDesfiliacao'])) {
                $this->atualizarContrato($id, $dados);
            }

            // Atualizar dados militares
            if ($this->temDadosMilitares($dados)) {
                $this->atualizarDadosMilitares($id, $dados);
            }

            // Atualizar dados financeiros
            if ($this->temDadosFinanceiros($dados)) {
                $this->atualizarDadosFinanceiros($id, $dados);
            }

            // Atualizar endereço
            if ($this->temDadosEndereco($dados)) {
                $this->atualizarEndereco($id, $dados);
            }

            // Atualizar dependentes
            if (isset($dados['dependentes'])) {
                $this->atualizarDependentes($id, $dados['dependentes']);
            }

            // Registrar na auditoria
            $this->registrarAuditoria('UPDATE', $id, $dados, $associadoAtual);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao atualizar associado: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Excluir associado
     */
    public function excluir($id)
    {
        try {
            $this->db->beginTransaction();

            // Buscar dados antes de excluir
            $associado = $this->getById($id);
            if (!$associado) {
                throw new Exception("Associado não encontrado");
            }

            // Deletar registros relacionados
            $tabelas = [
                'Historico_Fluxo_Pre_Cadastro',
                'Fluxo_Pre_Cadastro',
                'Documentos_Associado',
                'Servicos_Associado',
                'Redes_sociais',
                'Dependentes',
                'Endereco',
                'Financeiro',
                'Militar',
                'Contrato',
                'codigos_verificacao',
                'sso_tokens',
                'Detalhes_Lote'
            ];

            foreach ($tabelas as $tabela) {
                $stmt = $this->db->prepare("DELETE FROM $tabela WHERE associado_id = ?");
                $stmt->execute([$id]);
            }

            // Deletar associado
            $stmt = $this->db->prepare("DELETE FROM Associados WHERE id = ?");
            $stmt->execute([$id]);

            // Registrar na auditoria
            $this->registrarAuditoria('DELETE', $id, [], $associado);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao excluir associado: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar dependentes
     */
    public function getDependentes($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Dependentes 
                WHERE associado_id = ?
                ORDER BY id
            ");
            $stmt->execute([$associadoId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar dependentes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Adicionar dependente
     */
    public function adicionarDependente($associadoId, $dados)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Dependentes (
                    associado_id, nome, data_nascimento, parentesco, sexo
                ) VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $associadoId,
                $dados['nome'],
                $dados['data_nascimento'] ?? null,
                $dados['parentesco'] ?? null,
                $dados['sexo'] ?? null
            ]);

            return $this->db->lastInsertId();

        } catch (PDOException $e) {
            error_log("Erro ao adicionar dependente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar redes sociais
     */
    public function getRedesSociais($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM Redes_sociais 
                WHERE associado_id = ?
                ORDER BY id
            ");
            $stmt->execute([$associadoId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar redes sociais: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar serviços
     */
    public function getServicos($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    sa.*,
                    s.nome as servico_nome,
                    s.descricao as servico_descricao,
                    s.valor_base
                FROM Servicos_Associado sa
                JOIN Servicos s ON sa.servico_id = s.id
                WHERE sa.associado_id = ?
                ORDER BY sa.data_adesao DESC
            ");
            $stmt->execute([$associadoId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar serviços: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Buscar documentos
     */
    public function getDocumentos($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    d.*,
                    f.nome as funcionario_nome
                FROM Documentos_Associado d
                LEFT JOIN Funcionarios f ON d.funcionario_id = f.id
                WHERE d.associado_id = ?
                ORDER BY d.data_upload DESC
            ");
            $stmt->execute([$associadoId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar documentos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se CPF existe
     */
    private function cpfExiste($cpf, $excluirId = null)
    {
        $sql = "SELECT COUNT(*) FROM Associados WHERE cpf = ?";
        $params = [$cpf];

        if ($excluirId) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Atualizar contrato
     */
    private function atualizarContrato($associadoId, $dados)
    {
        // Verifica se existe contrato
        $stmt = $this->db->prepare("SELECT id FROM Contrato WHERE associado_id = ?");
        $stmt->execute([$associadoId]);
        $existe = $stmt->fetch();

        if ($existe) {
            // Atualiza
            $campos = [];
            $valores = [];

            if (isset($dados['dataFiliacao'])) {
                $campos[] = "dataFiliacao = ?";
                $valores[] = $dados['dataFiliacao'];
            }

            if (isset($dados['dataDesfiliacao'])) {
                $campos[] = "dataDesfiliacao = ?";
                $valores[] = $dados['dataDesfiliacao'];
            }

            if (!empty($campos)) {
                $valores[] = $associadoId;
                $sql = "UPDATE Contrato SET " . implode(", ", $campos) . " WHERE associado_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
        } else {
            // Insere
            $stmt = $this->db->prepare("
                INSERT INTO Contrato (associado_id, dataFiliacao, dataDesfiliacao)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $associadoId,
                $dados['dataFiliacao'] ?? null,
                $dados['dataDesfiliacao'] ?? null
            ]);
        }
    }

    /**
     * Atualizar dados militares
     */
    private function atualizarDadosMilitares($associadoId, $dados)
    {
        // Verifica se existe
        $stmt = $this->db->prepare("SELECT id FROM Militar WHERE associado_id = ?");
        $stmt->execute([$associadoId]);
        $existe = $stmt->fetch();

        $campos = ['corporacao', 'patente', 'categoria', 'lotacao', 'unidade'];

        if ($existe) {
            // Atualiza
            $updates = [];
            $valores = [];

            foreach ($campos as $campo) {
                if (isset($dados[$campo])) {
                    $updates[] = "$campo = ?";
                    $valores[] = $dados[$campo];
                }
            }

            if (!empty($updates)) {
                $valores[] = $associadoId;
                $sql = "UPDATE Militar SET " . implode(", ", $updates) . " WHERE associado_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
        } else {
            // Insere
            $stmt = $this->db->prepare("
                INSERT INTO Militar (associado_id, corporacao, patente, categoria, lotacao, unidade)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $valores = [$associadoId];
            foreach ($campos as $campo) {
                $valores[] = $dados[$campo] ?? null;
            }

            $stmt->execute($valores);
        }
    }

    /**
     * Atualizar dados financeiros
     */
    private function atualizarDadosFinanceiros($associadoId, $dados)
    {
        // Verifica se existe
        $stmt = $this->db->prepare("SELECT id FROM Financeiro WHERE associado_id = ?");
        $stmt->execute([$associadoId]);
        $existe = $stmt->fetch();

        $campos = [
            'tipoAssociado',
            'situacaoFinanceira',
            'vinculoServidor',
            'localDebito',
            'agencia',
            'operacao',
            'contaCorrente'
        ];

        if ($existe) {
            // Atualiza
            $updates = [];
            $valores = [];

            foreach ($campos as $campo) {
                if (isset($dados[$campo])) {
                    $updates[] = "$campo = ?";
                    $valores[] = $dados[$campo];
                }
            }

            if (!empty($updates)) {
                $valores[] = $associadoId;
                $sql = "UPDATE Financeiro SET " . implode(", ", $updates) . " WHERE associado_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
        } else {
            // Insere
            $stmt = $this->db->prepare("
                INSERT INTO Financeiro (
                    associado_id, tipoAssociado, situacaoFinanceira, vinculoServidor,
                    localDebito, agencia, operacao, contaCorrente
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $valores = [$associadoId];
            foreach ($campos as $campo) {
                $valores[] = $dados[$campo] ?? null;
            }

            $stmt->execute($valores);
        }
    }

    /**
     * Atualizar endereço
     */
    private function atualizarEndereco($associadoId, $dados)
    {
        // Verifica se existe
        $stmt = $this->db->prepare("SELECT id FROM Endereco WHERE associado_id = ?");
        $stmt->execute([$associadoId]);
        $existe = $stmt->fetch();

        $campos = ['cep', 'endereco', 'bairro', 'cidade', 'numero', 'complemento'];

        if ($existe) {
            // Atualiza
            $updates = [];
            $valores = [];

            foreach ($campos as $campo) {
                if (isset($dados[$campo])) {
                    $updates[] = "$campo = ?";
                    $valores[] = $dados[$campo];
                }
            }

            if (!empty($updates)) {
                $valores[] = $associadoId;
                $sql = "UPDATE Endereco SET " . implode(", ", $updates) . " WHERE associado_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
        } else {
            // Insere
            $stmt = $this->db->prepare("
                INSERT INTO Endereco (associado_id, cep, endereco, bairro, cidade, numero, complemento)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $valores = [$associadoId];
            foreach ($campos as $campo) {
                $valores[] = $dados[$campo] ?? null;
            }

            $stmt->execute($valores);
        }
    }

    /**
     * Atualizar dependentes
     */
    private function atualizarDependentes($associadoId, $dependentes)
    {
        // Remove todos os dependentes atuais
        $stmt = $this->db->prepare("DELETE FROM Dependentes WHERE associado_id = ?");
        $stmt->execute([$associadoId]);

        // Adiciona os novos
        if (is_array($dependentes)) {
            foreach ($dependentes as $dep) {
                if (!empty($dep['nome'])) {
                    $this->adicionarDependente($associadoId, $dep);
                }
            }
        }
    }

    /**
     * Verifica se tem dados militares
     */
    private function temDadosMilitares($dados)
    {
        $campos = ['corporacao', 'patente', 'categoria', 'lotacao', 'unidade'];
        foreach ($campos as $campo) {
            if (isset($dados[$campo])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se tem dados financeiros
     */
    private function temDadosFinanceiros($dados)
    {
        $campos = [
            'tipoAssociado',
            'situacaoFinanceira',
            'vinculoServidor',
            'localDebito',
            'agencia',
            'operacao',
            'contaCorrente'
        ];
        foreach ($campos as $campo) {
            if (isset($dados[$campo])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se tem dados de endereço
     */
    private function temDadosEndereco($dados)
    {
        $campos = ['cep', 'endereco', 'bairro', 'cidade', 'numero', 'complemento'];
        foreach ($campos as $campo) {
            if (isset($dados[$campo])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Registrar auditoria
     */
    private function registrarAuditoria($acao, $registroId, $dadosNovos = [], $dadosAntigos = [])
    {
        try {
            $funcionarioId = $_SESSION['funcionario_id'] ?? null;
            $alteracoes = [];

            if ($acao == 'UPDATE' && $dadosAntigos) {
                foreach ($dadosNovos as $campo => $valorNovo) {
                    if (isset($dadosAntigos[$campo]) && $dadosAntigos[$campo] != $valorNovo) {
                        $alteracoes[] = [
                            'campo' => $campo,
                            'valor_anterior' => $dadosAntigos[$campo],
                            'valor_novo' => $valorNovo
                        ];
                    }
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, associado_id, funcionario_id, 
                    alteracoes, ip_origem, browser_info, sessao_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                'Associados',
                $acao,
                $registroId,
                $registroId,
                $funcionarioId,
                json_encode($alteracoes),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);

            // Se houver alterações detalhadas, registra em Auditoria_Detalhes
            if (!empty($alteracoes)) {
                $auditoriaId = $this->db->lastInsertId();

                foreach ($alteracoes as $alteracao) {
                    $stmt = $this->db->prepare("
                        INSERT INTO Auditoria_Detalhes (
                            auditoria_id, campo, valor_anterior, valor_novo
                        ) VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $auditoriaId,
                        $alteracao['campo'],
                        $alteracao['valor_anterior'],
                        $alteracao['valor_novo']
                    ]);
                }
            }

        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
        }
    }

}