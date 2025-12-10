<?php

/**
 * Classe para gerenciamento de associados - VERSÃO COM PRÉ-CADASTRO E NOVOS CAMPOS
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
            // Busca dados principais incluindo pré-cadastro E NOVOS CAMPOS
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
                    f.observacoes,
                    f.doador,
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
                    f.observacoes,
                    f.doador,
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

            // CORRIGIDO: Inserir dados financeiros COM OS NOVOS CAMPOS
            if (!empty($dados['tipoAssociado']) || isset($dados['observacoes']) || isset($dados['doador'])) {
                $stmt = $this->db->prepare("
                INSERT INTO Financeiro (
                    associado_id, tipoAssociado, situacaoFinanceira, 
                    vinculoServidor, localDebito, agencia, operacao, contaCorrente,
                    observacoes, doador
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
                $stmt->execute([
                    $associadoId,
                    $dados['tipoAssociado'] ?? null,
                    $dados['situacaoFinanceira'] ?? null,
                    $dados['vinculoServidor'] ?? null,
                    $dados['localDebito'] ?? null,
                    $dados['agencia'] ?? null,
                    $dados['operacao'] ?? null,
                    $dados['contaCorrente'] ?? null,
                    $dados['observacoes'] ?? null,
                    isset($dados['doador']) ? intval($dados['doador']) : 0
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

            // ========================================
            // CRIAR DOCUMENTO COM STATUS CORRETO
            // ========================================
            $ehAgregado = isset($dados['tipoAssociado']) && $dados['tipoAssociado'] === 'Agregado';
            $statusFluxo = $ehAgregado ? 'AGUARDANDO_ASSINATURA' : 'DIGITALIZADO';
            $tipoDocumento = $ehAgregado ? 'FICHA_AGREGADO' : 'FICHA_ASSOCIADO';
            $observacaoDoc = $ehAgregado ? 'Agregado - Aguardando assinatura da presidência' : 'Associado - Documento digitalizado';
            
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO Documentos_Associado (
                        associado_id, tipo_documento, tipo_origem, nome_arquivo,
                        caminho_arquivo, data_upload, observacao, status_fluxo, verificado
                    ) VALUES (?, ?, 'VIRTUAL', 'documento_virtual.pdf', '', NOW(), ?, ?, 0)
                ");
                
                $stmt->execute([
                    $associadoId,
                    $tipoDocumento,
                    $observacaoDoc,
                    $statusFluxo
                ]);
                
                error_log("✅ Documento criado - Status: {$statusFluxo} - Tipo: {$tipoDocumento}");
                
            } catch (Exception $e) {
                error_log("⚠ Erro ao criar documento: " . $e->getMessage());
                // Não lança exceção para não interromper o cadastro
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
                    // Validação especial para campos de data
                    if ($campo === 'nasc') {
                        $dataNasc = $dados[$campo];
                        // Ignora valores inválidos
                        if (empty($dataNasc) || $dataNasc === 'NaN-NaN-01' || $dataNasc === '0000-00-00' || strtotime($dataNasc) === false) {
                            continue; // Não atualiza se a data for inválida
                        }
                    }
                    
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
                // Valida data antes de inserir
                $dataFiliacao = $dados['dataFiliacao'];
                if (!empty($dataFiliacao) && $dataFiliacao !== 'NaN-NaN-01' && strtotime($dataFiliacao) !== false) {
                    $campos[] = "dataFiliacao = ?";
                    $valores[] = $dataFiliacao;
                }
            }

            if (isset($dados['dataDesfiliacao'])) {
                // Valida data antes de inserir (pode ser NULL)
                $dataDesfiliacao = $dados['dataDesfiliacao'];
                if ($dataDesfiliacao === null || $dataDesfiliacao === '' || $dataDesfiliacao === 'NaN-NaN-01') {
                    $campos[] = "dataDesfiliacao = NULL";
                } elseif (strtotime($dataDesfiliacao) !== false) {
                    $campos[] = "dataDesfiliacao = ?";
                    $valores[] = $dataDesfiliacao;
                }
            }

            if (!empty($campos)) {
                $valores[] = $associadoId;
                $sql = "UPDATE Contrato SET " . implode(", ", $campos) . " WHERE associado_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
        } else {
            // Insere - valida datas antes
            $dataFiliacao = $dados['dataFiliacao'] ?? null;
            $dataDesfiliacao = $dados['dataDesfiliacao'] ?? null;
            
            // Valida dataFiliacao
            if (!empty($dataFiliacao) && ($dataFiliacao === 'NaN-NaN-01' || strtotime($dataFiliacao) === false)) {
                $dataFiliacao = null;
            }
            
            // Valida dataDesfiliacao
            if (!empty($dataDesfiliacao) && ($dataDesfiliacao === 'NaN-NaN-01' || strtotime($dataDesfiliacao) === false)) {
                $dataDesfiliacao = null;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO Contrato (associado_id, dataFiliacao, dataDesfiliacao)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $associadoId,
                $dataFiliacao,
                $dataDesfiliacao
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
     * CORRIGIDO: Atualizar dados financeiros COM NOVOS CAMPOS
     */
    private function atualizarDadosFinanceiros($associadoId, $dados)
    {
        // Verifica se existe
        $stmt = $this->db->prepare("SELECT id FROM Financeiro WHERE associado_id = ?");
        $stmt->execute([$associadoId]);
        $existe = $stmt->fetch();

        // INCLUIR NOVOS CAMPOS NA LISTA
        $campos = [
            'tipoAssociado',
            'situacaoFinanceira',
            'vinculoServidor',
            'localDebito',
            'agencia',
            'operacao',
            'contaCorrente',
            'observacoes',
            'doador'
        ];

        if ($existe) {
            // Atualiza
            $updates = [];
            $valores = [];

            foreach ($campos as $campo) {
                if (isset($dados[$campo])) {
                    $updates[] = "$campo = ?";
                    // Tratamento especial para doador (converte para int)
                    if ($campo === 'doador') {
                        $valores[] = intval($dados[$campo]);
                    } else {
                        $valores[] = $dados[$campo];
                    }
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
                    localDebito, agencia, operacao, contaCorrente, observacoes, doador
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $valores = [$associadoId];
            foreach ($campos as $campo) {
                if ($campo === 'doador') {
                    $valores[] = isset($dados[$campo]) ? intval($dados[$campo]) : 0;
                } else {
                    $valores[] = $dados[$campo] ?? null;
                }
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
     * CORRIGIDO: Verifica se tem dados financeiros (incluindo novos campos)
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
            'contaCorrente',
            'observacoes',
            'doador'
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

    /**
     * ========================================
     * MÉTODOS PARA GERENCIAMENTO DE OBSERVAÇÕES
     * Adicionar na classe Associados
     * ========================================
     */

    /**
     * Buscar observações de um associado
     */
    public function getObservacoes($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
            SELECT 
                o.*,
                f.nome as criado_por_nome,
                f.cargo as criado_por_cargo,
                f.foto as criado_por_foto,
                DATE_FORMAT(CONVERT_TZ(o.data_criacao, '+00:00', '-03:00'), '%d/%m/%Y às %H:%i') as data_formatada,
                DATE_FORMAT(CONVERT_TZ(o.data_edicao, '+00:00', '-03:00'), '%d/%m/%Y às %H:%i') as data_edicao_formatada,
                CASE 
                    WHEN o.data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 
                    ELSE 0 
                END as recente
            FROM Observacoes_Associado o
            LEFT JOIN Funcionarios f ON o.criado_por = f.id
            WHERE o.associado_id = ? AND o.ativo = 1
            ORDER BY o.data_criacao DESC
        ");

            $stmt->execute([$associadoId]);
            $observacoes = $stmt->fetchAll();

            // Adicionar tags baseadas na categoria
            foreach ($observacoes as &$obs) {
                $obs['tags'] = $this->getTagsObservacao($obs);
            }

            return $observacoes;
        } catch (PDOException $e) {
            error_log("Erro ao buscar observações: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Adicionar nova observação
     */
    public function adicionarObservacao($dados)
    {
        try {
            // Garantir que temos sessão
            if (!isset($_SESSION)) {
                session_start();
            }

            $this->db->beginTransaction();

            // Validar dados obrigatórios
            if (empty($dados['associado_id']) || empty($dados['observacao'])) {
                throw new Exception("Associado ID e observação são obrigatórios");
            }

            // Obter ID do funcionário atual
            $funcionarioId = $_SESSION['funcionario_id'] ?? null;

            // Log para debug
            error_log("Adicionando observação - Associado: {$dados['associado_id']}, Funcionário: $funcionarioId");

            $stmt = $this->db->prepare("
            INSERT INTO Observacoes_Associado (
                associado_id,
                observacao,
                categoria,
                prioridade,
                importante,
                criado_por,
                data_criacao,
                ativo
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
        ");

            $result = $stmt->execute([
                $dados['associado_id'],
                $dados['observacao'],
                $dados['categoria'] ?? 'geral',
                $dados['prioridade'] ?? 'media',
                $dados['importante'] ?? 0,
                $funcionarioId
            ]);

            if (!$result) {
                throw new Exception("Erro ao inserir observação: " . implode(', ', $stmt->errorInfo()));
            }

            $observacaoId = $this->db->lastInsertId();

            // Registrar na auditoria (opcional)
            try {
                $this->registrarAuditoriaObservacao('INSERT', $observacaoId, $dados);
            } catch (Exception $e) {
                error_log("Aviso: Erro ao registrar auditoria: " . $e->getMessage());
                // Não é crítico, continua
            }

            $this->db->commit();

            error_log("✓ Observação $observacaoId criada com sucesso");

            // Retornar dados da observação criada
            return ['id' => $observacaoId];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro ao adicionar observação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atualizar observação
     */
    public function atualizarObservacao($id, $dados)
    {
        try {
            $this->db->beginTransaction();

            // Buscar observação atual para auditoria
            $obsAtual = $this->getObservacaoById($id);
            if (!$obsAtual) {
                throw new Exception("Observação não encontrada");
            }

            // Verificar permissão (só quem criou ou admin pode editar)
            if ($obsAtual['criado_por'] != $_SESSION['funcionario_id'] && !$this->isAdmin()) {
                throw new Exception("Sem permissão para editar esta observação");
            }

            $campos = [];
            $valores = [];

            if (isset($dados['observacao'])) {
                $campos[] = "observacao = ?";
                $valores[] = $dados['observacao'];
            }

            if (isset($dados['categoria'])) {
                $campos[] = "categoria = ?";
                $valores[] = $dados['categoria'];
            }

            if (isset($dados['prioridade'])) {
                $campos[] = "prioridade = ?";
                $valores[] = $dados['prioridade'];
            }

            if (isset($dados['importante'])) {
                $campos[] = "importante = ?";
                $valores[] = $dados['importante'];
            }

            if (!empty($campos)) {
                $campos[] = "data_edicao = NOW()";
                $campos[] = "editado = 1";

                $valores[] = $id;

                $sql = "UPDATE Observacoes_Associado SET " . implode(", ", $campos) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);

                // Registrar na auditoria
                $this->registrarAuditoriaObservacao('UPDATE', $id, $dados, $obsAtual);
            }

            $this->db->commit();

            return $this->getObservacaoById($id);
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao atualizar observação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Excluir observação (soft delete)
     */
    public function excluirObservacao($id)
    {
        try {
            // Garantir que temos sessão
            if (!isset($_SESSION)) {
                session_start();
            }

            $this->db->beginTransaction();

            // Buscar observação
            $obs = $this->getObservacaoById($id);
            if (!$obs) {
                throw new Exception("Observação não encontrada");
            }

            // Obter ID do funcionário atual
            $funcionarioId = $_SESSION['funcionario_id'] ?? null;

            // Verificar permissão (criador ou admin pode excluir)
            $podeExcluir = false;
            $motivo = '';

            // Verifica se é o criador
            if ($obs['criado_por'] == $funcionarioId) {
                $podeExcluir = true;
                $motivo = 'É o criador da observação';
            }
            // Verifica se é admin/diretor
            elseif ($this->isAdmin()) {
                $podeExcluir = true;
                $motivo = 'Tem permissão administrativa';
            }

            if (!$podeExcluir) {
                throw new Exception("Sem permissão para excluir esta observação. " . $motivo);
            }

            // Log para debug
            error_log("Excluindo observação ID: $id, Funcionário: $funcionarioId, Motivo: $motivo");

            // Soft delete
            $stmt = $this->db->prepare("
            UPDATE Observacoes_Associado 
            SET ativo = 0, 
                data_exclusao = NOW(),
                excluido_por = ?
            WHERE id = ? AND ativo = 1
        ");

            $result = $stmt->execute([$funcionarioId, $id]);

            if (!$result) {
                throw new Exception("Erro ao executar UPDATE: " . implode(', ', $stmt->errorInfo()));
            }

            // Verificar se alguma linha foi afetada
            if ($stmt->rowCount() === 0) {
                throw new Exception("Nenhuma linha foi afetada. A observação pode já estar excluída.");
            }

            // Registrar na auditoria (opcional)
            try {
                $this->registrarAuditoriaObservacao('DELETE', $id, [], $obs);
            } catch (Exception $e) {
                error_log("Aviso: Erro ao registrar auditoria: " . $e->getMessage());
                // Não é crítico, continua
            }

            $this->db->commit();

            error_log("✓ Observação $id excluída com sucesso");
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro ao excluir observação: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Alternar importância da observação
     */
    public function toggleImportanteObservacao($id)
    {
        try {
            $this->db->beginTransaction();

            // Buscar estado atual
            $stmt = $this->db->prepare("SELECT importante FROM Observacoes_Associado WHERE id = ? AND ativo = 1");
            $stmt->execute([$id]);
            $obs = $stmt->fetch();

            if (!$obs) {
                throw new Exception("Observação não encontrada");
            }

            // Alternar estado
            $novoEstado = $obs['importante'] ? 0 : 1;

            $stmt = $this->db->prepare("
            UPDATE Observacoes_Associado 
            SET importante = ?,
                data_edicao = NOW()
            WHERE id = ?
        ");

            $stmt->execute([$novoEstado, $id]);

            $this->db->commit();

            return ['importante' => $novoEstado];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao alternar importância: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar observação por ID
     */
    private function getObservacaoById($id)
    {
        try {
            $stmt = $this->db->prepare("
            SELECT 
                o.*,
                f.nome as criado_por_nome,
                f.cargo as criado_por_cargo,
                f.foto as criado_por_foto
            FROM Observacoes_Associado o
            LEFT JOIN Funcionarios f ON o.criado_por = f.id
            WHERE o.id = ?
        ");

            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar observação por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Buscar estatísticas de observações
     */
    public function getEstatisticasObservacoes($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN importante = 1 THEN 1 END) as importantes,
                COUNT(CASE WHEN categoria = 'pendencia' THEN 1 END) as pendencias,
                COUNT(CASE WHEN prioridade = 'urgente' THEN 1 END) as urgentes,
                COUNT(CASE WHEN data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recentes
            FROM Observacoes_Associado
            WHERE associado_id = ? AND ativo = 1
        ");

            $stmt->execute([$associadoId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas de observações: " . $e->getMessage());
            return [
                'total' => 0,
                'importantes' => 0,
                'pendencias' => 0,
                'urgentes' => 0,
                'recentes' => 0
            ];
        }
    }

    /**
     * Gerar tags para observação
     */
    private function getTagsObservacao($observacao)
    {
        $tags = [];

        // Tag de categoria
        if (!empty($observacao['categoria'])) {
            $tags[] = ucfirst($observacao['categoria']);
        }

        // Tag de prioridade se for alta ou urgente
        if (in_array($observacao['prioridade'], ['alta', 'urgente'])) {
            $tags[] = ucfirst($observacao['prioridade']);
        }

        // Tag de importante
        if ($observacao['importante']) {
            $tags[] = 'Importante';
        }

        // Tag de recente
        if ($observacao['recente']) {
            $tags[] = 'Recente';
        }

        return implode(',', $tags);
    }

    /**
     * Registrar auditoria de observação
     */
    private function registrarAuditoriaObservacao($acao, $observacaoId, $dadosNovos = [], $dadosAntigos = [])
    {
        try {
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
                alteracoes, ip_origem, browser_info, sessao_id, data_hora
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

            $associadoId = $dadosNovos['associado_id'] ?? $dadosAntigos['associado_id'] ?? null;

            $stmt->execute([
                'Observacoes_Associado',
                $acao,
                $observacaoId,
                $associadoId,
                $_SESSION['funcionario_id'] ?? null,
                json_encode($alteracoes),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);
        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria de observação: " . $e->getMessage());
        }
    }

    /**
     * Verificar se usuário é admin
     */
    private function isAdmin()
    {
        // Verificar se tem sessão iniciada
        if (!isset($_SESSION)) {
            session_start();
        }

        // Verificar cargo diretamente da sessão
        if (isset($_SESSION['funcionario_cargo'])) {
            return in_array($_SESSION['funcionario_cargo'], ['Diretor', 'Administrador', 'Gerente']);
        }

        // Verificar também pela flag is_diretor
        if (isset($_SESSION['is_diretor']) && $_SESSION['is_diretor']) {
            return true;
        }

        // Se não tem informação de cargo, tentar buscar do banco
        if (isset($_SESSION['funcionario_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT cargo FROM Funcionarios WHERE id = ?");
                $stmt->execute([$_SESSION['funcionario_id']]);
                $funcionario = $stmt->fetch();

                if ($funcionario) {
                    $_SESSION['funcionario_cargo'] = $funcionario['cargo'];
                    return in_array($funcionario['cargo'], ['Diretor', 'Administrador', 'Gerente']);
                }
            } catch (Exception $e) {
                error_log("Erro ao verificar admin: " . $e->getMessage());
            }
        }

        return false;
    }
    private function getFuncionarioId()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        return $_SESSION['funcionario_id'] ?? null;
    }

    /**
     * Contar observações por associado
     */
    public function contarObservacoes($associadoId)
    {
        try {
            $stmt = $this->db->prepare("
            SELECT COUNT(*) as total 
            FROM Observacoes_Associado 
            WHERE associado_id = ? AND ativo = 1
        ");

            $stmt->execute([$associadoId]);
            $result = $stmt->fetch();

            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Erro ao contar observações: " . $e->getMessage());
            return 0;
        }
    }
}
