<?php
/**
 * Classe para gerenciamento de relatórios
 * classes/Relatorios.php
 */

class Relatorios {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    }
    
    /**
     * Busca modelo de relatório por ID
     */
    public function getModeloById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT mr.*, f.nome as criado_por_nome
                FROM Modelos_Relatorios mr
                LEFT JOIN Funcionarios f ON mr.criado_por = f.id
                WHERE mr.id = ? AND mr.ativo = 1
            ");
            $stmt->execute([$id]);
            $modelo = $stmt->fetch();
            
            if ($modelo) {
                // Decodificar campos JSON
                $modelo['campos'] = json_decode($modelo['campos'], true);
                $modelo['filtros'] = json_decode($modelo['filtros'], true);
            }
            
            return $modelo;
        } catch (PDOException $e) {
            error_log("Erro ao buscar modelo de relatório: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lista modelos de relatórios disponíveis
     */
    public function listarModelos($filtros = []) {
        try {
            $sql = "
                SELECT mr.*, f.nome as criado_por_nome,
                       (SELECT COUNT(*) FROM Historico_Relatorios hr WHERE hr.modelo_id = mr.id) as total_execucoes
                FROM Modelos_Relatorios mr
                LEFT JOIN Funcionarios f ON mr.criado_por = f.id
                WHERE mr.ativo = 1
            ";
            
            $params = [];
            
            // Aplicar filtros
            if (isset($filtros['tipo'])) {
                $sql .= " AND mr.tipo = ?";
                $params[] = $filtros['tipo'];
            }
            
            if (isset($filtros['criado_por'])) {
                $sql .= " AND mr.criado_por = ?";
                $params[] = $filtros['criado_por'];
            }
            
            if (isset($filtros['busca'])) {
                $sql .= " AND (mr.nome LIKE ? OR mr.descricao LIKE ?)";
                $params[] = "%{$filtros['busca']}%";
                $params[] = "%{$filtros['busca']}%";
            }
            
            $sql .= " ORDER BY mr.nome ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao listar modelos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Criar novo modelo de relatório
     */
    public function criarModelo($dados) {
        try {
            $this->db->beginTransaction();
            
            // Log para debug
            error_log('criarModelo - Dados recebidos: ' . json_encode($dados));
            
            // Validar dados obrigatórios
            if (empty($dados['nome']) || empty($dados['tipo']) || empty($dados['campos'])) {
                error_log('criarModelo - Dados obrigatórios faltando');
                error_log('nome: ' . ($dados['nome'] ?? 'VAZIO'));
                error_log('tipo: ' . ($dados['tipo'] ?? 'VAZIO'));
                error_log('campos: ' . json_encode($dados['campos'] ?? []));
                throw new Exception("Dados obrigatórios não informados");
            }
            
            // Validar campos selecionados
            $campos_validos = $this->validarCampos($dados['campos']);
            if (!$campos_validos) {
                error_log('criarModelo - Campos inválidos: ' . json_encode($dados['campos']));
                throw new Exception("Campos selecionados inválidos");
            }
            
            // Garantir que campos e filtros sejam JSON válidos
            $camposJson = is_array($dados['campos']) ? json_encode($dados['campos']) : $dados['campos'];
            $filtrosJson = is_array($dados['filtros']) ? json_encode($dados['filtros']) : $dados['filtros'];
            
            error_log('criarModelo - JSON campos: ' . $camposJson);
            error_log('criarModelo - JSON filtros: ' . $filtrosJson);
            
            $stmt = $this->db->prepare("
                INSERT INTO Modelos_Relatorios (nome, descricao, tipo, campos, filtros, ordenacao, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $dados['nome'],
                $dados['descricao'] ?? null,
                $dados['tipo'],
                $camposJson,
                $filtrosJson,
                $dados['ordenacao'] ?? null,
                $_SESSION['funcionario_id'] ?? null
            ]);
            
            $modelo_id = $this->db->lastInsertId();
            
            error_log('criarModelo - Modelo criado com ID: ' . $modelo_id);
            
            // Registrar na auditoria
            $this->registrarAuditoria('INSERT', $modelo_id, $dados);
            
            $this->db->commit();
            return $modelo_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao criar modelo: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Atualizar modelo de relatório
     */
    public function atualizarModelo($id, $dados) {
        try {
            $this->db->beginTransaction();
            
            // Buscar modelo atual
            $modelo_atual = $this->getModeloById($id);
            if (!$modelo_atual) {
                throw new Exception("Modelo não encontrado");
            }
            
            // Validar campos se foram alterados
            if (isset($dados['campos'])) {
                $campos_validos = $this->validarCampos($dados['campos']);
                if (!$campos_validos) {
                    throw new Exception("Campos selecionados inválidos");
                }
            }
            
            // Preparar atualização
            $campos_update = [];
            $valores = [];
            
            $campos_permitidos = ['nome', 'descricao', 'tipo', 'campos', 'filtros', 'ordenacao', 'ativo'];
            foreach ($campos_permitidos as $campo) {
                if (isset($dados[$campo])) {
                    $campos_update[] = "$campo = ?";
                    $valor = $dados[$campo];
                    
                    // Converter arrays para JSON
                    if (in_array($campo, ['campos', 'filtros']) && is_array($valor)) {
                        $valor = json_encode($valor);
                    }
                    
                    $valores[] = $valor;
                }
            }
            
            if (!empty($campos_update)) {
                $campos_update[] = "data_modificacao = NOW()";
                $valores[] = $id;
                
                $sql = "UPDATE Modelos_Relatorios SET " . implode(", ", $campos_update) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
            
            // Registrar na auditoria
            $this->registrarAuditoria('UPDATE', $id, $dados, $modelo_atual);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao atualizar modelo: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Executar relatório com modelo salvo
     */
    public function executarRelatorio($modelo_id, $parametros = []) {
        try {
            $this->db->beginTransaction();
            
            // Buscar modelo
            $modelo = $this->getModeloById($modelo_id);
            if (!$modelo) {
                throw new Exception("Modelo não encontrado");
            }
            
            // Construir query baseada no modelo
            $query = $this->construirQuery($modelo, $parametros);
            
            // Executar query
            $stmt = $this->db->prepare($query['sql']);
            $stmt->execute($query['params']);
            $resultados = $stmt->fetchAll();
            
            // Registrar execução no histórico
            $this->registrarHistorico($modelo_id, $parametros, count($resultados));
            
            $this->db->commit();
            
            return [
                'modelo' => $modelo,
                'dados' => $resultados,
                'total' => count($resultados),
                'parametros' => $parametros
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao executar relatório: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Executar relatório temporário (sem modelo salvo)
     */
    public function executarRelatorioTemporario($config, $parametros = []) {
        try {
            // Mescla filtros do config com parâmetros adicionais
            if (isset($config['filtros'])) {
                $parametros = array_merge($config['filtros'], $parametros);
            }
            
            // Adiciona os parâmetros de volta ao config
            $config['filtros'] = $parametros;
            
            // Construir query baseada na configuração
            $query = $this->construirQuery($config, $parametros);
            
            // Executar query
            $stmt = $this->db->prepare($query['sql']);
            $stmt->execute($query['params']);
            $resultados = $stmt->fetchAll();
            
            // Opcionalmente registrar no histórico como relatório temporário
            // $this->registrarHistoricoTemporario($config, count($resultados));
            
            return [
                'modelo' => $config,
                'dados' => $resultados,
                'total' => count($resultados),
                'parametros' => $parametros
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao executar relatório temporário: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Construir query SQL baseada no modelo ou configuração
     */
    private function construirQuery($modelo, $parametros = []) {
        // Se modelo tem estrutura de modelo salvo
        if (isset($modelo['id'])) {
            $campos = $modelo['campos'];
            $tipo = $modelo['tipo'];
            $ordenacao = $modelo['ordenacao'] ?? '';
        } else {
            // Se é configuração temporária
            $campos = $modelo['campos'] ?? [];
            $tipo = $modelo['tipo'] ?? 'associados';
            $ordenacao = $modelo['ordenacao'] ?? '';
        }
        
        // Mapear tabelas principais por tipo
        $tabelas_principais = [
            'associados' => 'Associados a',
            'financeiro' => 'Financeiro f',
            'militar' => 'Militar m',
            'servicos' => 'Servicos_Associado sa',
            'documentos' => 'Documentos_Associado da'
        ];
        
        if (!isset($tabelas_principais[$tipo])) {
            throw new Exception("Tipo de relatório inválido");
        }
        
        // Iniciar construção da query
        $select_campos = [];
        $joins = [];
        $joins_adicionados = []; // Rastrear tabelas já adicionadas
        $where = ["1=1"];
        $params = [];
        
        // Construir SELECT baseado nos campos
        foreach ($campos as $campo) {
            $campo_info = $this->getCampoInfo($campo);
            if ($campo_info) {
                $select_campos[] = $campo_info['select_as'];
                
                // Adicionar JOINs necessários
                if (!empty($campo_info['join']) && !isset($joins_adicionados[$campo_info['join']])) {
                    $joins[] = $campo_info['join'];
                    $joins_adicionados[$campo_info['join']] = true;
                }
            }
        }
        
        // Se não há campos selecionados, usa campos básicos
        if (empty($select_campos)) {
            $select_campos = $this->getCamposBasicos($tipo);
        }
        
        // Adicionar JOINs padrão baseado no tipo
        $joins_padrao = $this->getJoinsPadrao($tipo);
        foreach ($joins_padrao as $join) {
            if (!isset($joins_adicionados[$join])) {
                $joins[] = $join;
                $joins_adicionados[$join] = true;
            }
        }
        
        // Verifica se precisa adicionar JOIN com Contrato para filtros de data
        if (($tipo === 'associados' || $tipo === 'financeiro' || $tipo === 'militar') && 
            (!empty($parametros['data_inicio']) || !empty($parametros['data_fim']))) {
            $joinContrato = 'LEFT JOIN Contrato c ON a.id = c.associado_id';
            if (!isset($joins_adicionados[$joinContrato])) {
                $joins[] = $joinContrato;
                $joins_adicionados[$joinContrato] = true;
            }
        }
        
        // Aplicar filtros
        $where_conditions = $this->aplicarFiltros($tipo, $parametros, $params);
        if (!empty($where_conditions)) {
            $where = array_merge($where, $where_conditions);
        }
        
        // Construir SQL final
        $sql = "SELECT DISTINCT " . implode(", ", $select_campos) . "\n";
        $sql .= "FROM " . $tabelas_principais[$tipo] . "\n";
        $sql .= implode("\n", $joins) . "\n";
        $sql .= "WHERE " . implode(" AND ", $where);
        
        // Adicionar ordenação
        if ($ordenacao) {
            $sql .= "\nORDER BY " . $ordenacao;
        } elseif ($tipo === 'associados' || in_array('a.nome', $select_campos)) {
            $sql .= "\nORDER BY a.nome ASC";
        }
        
        return [
            'sql' => $sql,
            'params' => $params
        ];
    }
    
    /**
     * Obter campos básicos por tipo
     */
    private function getCamposBasicos($tipo) {
        $campos_basicos = [
            'associados' => ['a.id', 'a.nome', 'a.cpf', 'a.rg', 'a.situacao'],
            'financeiro' => ['a.id', 'a.nome', 'a.cpf', 'f.tipoAssociado', 'f.situacaoFinanceira'],
            'militar' => ['a.id', 'a.nome', 'a.cpf', 'm.corporacao', 'm.patente'],
            'servicos' => ['a.id', 'a.nome', 'a.cpf', 's.nome as servico_nome', 'sa.valor_aplicado'],
            'documentos' => ['a.id', 'a.nome', 'a.cpf', 'da.tipo_documento', 'da.data_upload']
        ];
        
        return $campos_basicos[$tipo] ?? ['a.id', 'a.nome'];
    }
    
    /**
     * Obter JOINs padrão por tipo
     */
    private function getJoinsPadrao($tipo) {
        $joins_padrao = [
            'associados' => [],
            'financeiro' => ['JOIN Associados a ON f.associado_id = a.id'],
            'militar' => ['JOIN Associados a ON m.associado_id = a.id'],
            'servicos' => [
                'JOIN Associados a ON sa.associado_id = a.id',
                'JOIN Servicos s ON sa.servico_id = s.id'
            ],
            'documentos' => ['JOIN Associados a ON da.associado_id = a.id']
        ];
        
        return $joins_padrao[$tipo] ?? [];
    }
    
    /**
     * Obter informações do campo
     */
    private function getCampoInfo($campo_nome) {
        // Mapeamento direto de campos para SELECT e JOIN
        $mapeamento = [
            // Campos de Associados
            'nome' => ['select_as' => 'a.nome', 'join' => ''],
            'cpf' => ['select_as' => 'a.cpf', 'join' => ''],
            'rg' => ['select_as' => 'a.rg', 'join' => ''],
            'nasc' => ['select_as' => 'a.nasc', 'join' => ''],
            'sexo' => ['select_as' => 'a.sexo', 'join' => ''],
            'email' => ['select_as' => 'a.email', 'join' => ''],
            'telefone' => ['select_as' => 'a.telefone', 'join' => ''],
            'situacao' => ['select_as' => 'a.situacao', 'join' => ''],
            'escolaridade' => ['select_as' => 'a.escolaridade', 'join' => ''],
            'estadoCivil' => ['select_as' => 'a.estadoCivil', 'join' => ''],
            'indicacao' => ['select_as' => 'a.indicacao', 'join' => ''],
            
            // Campos de Militar
            'corporacao' => ['select_as' => 'm.corporacao', 'join' => 'LEFT JOIN Militar m ON a.id = m.associado_id'],
            'patente' => ['select_as' => 'm.patente', 'join' => 'LEFT JOIN Militar m ON a.id = m.associado_id'],
            'categoria' => ['select_as' => 'm.categoria', 'join' => 'LEFT JOIN Militar m ON a.id = m.associado_id'],
            'lotacao' => ['select_as' => 'm.lotacao', 'join' => 'LEFT JOIN Militar m ON a.id = m.associado_id'],
            'unidade' => ['select_as' => 'm.unidade', 'join' => 'LEFT JOIN Militar m ON a.id = m.associado_id'],
            
            // Campos de Financeiro
            'tipoAssociado' => ['select_as' => 'f.tipoAssociado', 'join' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id'],
            'situacaoFinanceira' => ['select_as' => 'f.situacaoFinanceira', 'join' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id'],
            'vinculoServidor' => ['select_as' => 'f.vinculoServidor', 'join' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id'],
            'localDebito' => ['select_as' => 'f.localDebito', 'join' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id'],
            'agencia' => ['select_as' => 'f.agencia', 'join' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id'],
            'operacao' => ['select_as' => 'f.operacao', 'join' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id'],
            'contaCorrente' => ['select_as' => 'f.contaCorrente', 'join' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id'],
            
            // Campos de Contrato
            'dataFiliacao' => ['select_as' => 'c.dataFiliacao', 'join' => 'LEFT JOIN Contrato c ON a.id = c.associado_id'],
            'dataDesfiliacao' => ['select_as' => 'c.dataDesfiliacao', 'join' => 'LEFT JOIN Contrato c ON a.id = c.associado_id'],
            
            // Campos de Endereço
            'cep' => ['select_as' => 'e.cep', 'join' => 'LEFT JOIN Endereco e ON a.id = e.associado_id'],
            'endereco' => ['select_as' => 'e.endereco', 'join' => 'LEFT JOIN Endereco e ON a.id = e.associado_id'],
            'numero' => ['select_as' => 'e.numero', 'join' => 'LEFT JOIN Endereco e ON a.id = e.associado_id'],
            'bairro' => ['select_as' => 'e.bairro', 'join' => 'LEFT JOIN Endereco e ON a.id = e.associado_id'],
            'cidade' => ['select_as' => 'e.cidade', 'join' => 'LEFT JOIN Endereco e ON a.id = e.associado_id'],
            'complemento' => ['select_as' => 'e.complemento', 'join' => 'LEFT JOIN Endereco e ON a.id = e.associado_id'],
            
            // Campos de Serviços
            'servico_nome' => ['select_as' => 's.nome as servico_nome', 'join' => ''],
            'valor_aplicado' => ['select_as' => 'sa.valor_aplicado', 'join' => ''],
            'percentual_aplicado' => ['select_as' => 'sa.percentual_aplicado', 'join' => ''],
            'data_adesao' => ['select_as' => 'sa.data_adesao', 'join' => ''],
            'ativo' => ['select_as' => 'sa.ativo', 'join' => ''],
            
            // Campos de Documentos
            'tipo_documento' => ['select_as' => 'da.tipo_documento', 'join' => ''],
            'nome_arquivo' => ['select_as' => 'da.nome_arquivo', 'join' => ''],
            'data_upload' => ['select_as' => 'da.data_upload', 'join' => ''],
            'verificado' => ['select_as' => 'da.verificado', 'join' => ''],
            'funcionario_nome' => ['select_as' => 'func.nome as funcionario_nome', 'join' => 'LEFT JOIN Funcionarios func ON da.funcionario_id = func.id'],
            'observacao' => ['select_as' => 'da.observacao', 'join' => ''],
            'lote_id' => ['select_as' => 'da.lote_id', 'join' => ''],
            'lote_status' => ['select_as' => 'ld.status as lote_status', 'join' => 'LEFT JOIN Lotes_Documentos ld ON da.lote_id = ld.id']
        ];
        
        return $mapeamento[$campo_nome] ?? null;
    }
    
    /**
     * Aplicar filtros baseados nos parâmetros
     */
    private function aplicarFiltros($tipo, $parametros, &$params) {
        $where = [];
        
        // Filtros de data - usando campos apropriados por tipo
        if (!empty($parametros['data_inicio']) || !empty($parametros['data_fim'])) {
            $campoData = null;
            
            // Define o campo de data apropriado por tipo
            switch ($tipo) {
                case 'associados':
                case 'financeiro':
                case 'militar':
                    // Para estes tipos, usa data de filiação do contrato
                    $campoData = 'c.dataFiliacao';
                    break;
                case 'servicos':
                    $campoData = 'sa.data_adesao';
                    break;
                case 'documentos':
                    $campoData = 'da.data_upload';
                    break;
            }
            
            if ($campoData) {
                if (!empty($parametros['data_inicio'])) {
                    $where[] = "DATE($campoData) >= ?";
                    $params[] = $parametros['data_inicio'];
                }
                
                if (!empty($parametros['data_fim'])) {
                    $where[] = "DATE($campoData) <= ?";
                    $params[] = $parametros['data_fim'];
                }
            }
        }
        
        // Filtros específicos
        if (!empty($parametros['situacao'])) {
            $where[] = "a.situacao = ?";
            $params[] = $parametros['situacao'];
        }
        
        // Filtros que dependem da tabela Militar
        if (!empty($parametros['corporacao']) || !empty($parametros['patente'])) {
            if ($tipo === 'militar' || $tipo === 'associados') {
                if (!empty($parametros['corporacao'])) {
                    $where[] = "m.corporacao = ?";
                    $params[] = $parametros['corporacao'];
                }
                
                if (!empty($parametros['patente'])) {
                    $where[] = "m.patente = ?";
                    $params[] = $parametros['patente'];
                }
            }
        }
        
        // Filtros que dependem da tabela Financeiro
        if (!empty($parametros['tipo_associado']) || !empty($parametros['situacaoFinanceira'])) {
            if ($tipo === 'financeiro' || $tipo === 'associados') {
                if (!empty($parametros['tipo_associado'])) {
                    $where[] = "f.tipoAssociado = ?";
                    $params[] = $parametros['tipo_associado'];
                }
                
                if (!empty($parametros['situacaoFinanceira'])) {
                    $where[] = "f.situacaoFinanceira = ?";
                    $params[] = $parametros['situacaoFinanceira'];
                }
            }
        }
        
        // Filtros específicos de serviços
        if ($tipo === 'servicos') {
            if (!empty($parametros['servico_id'])) {
                $where[] = "sa.servico_id = ?";
                $params[] = $parametros['servico_id'];
            }
            
            if (isset($parametros['ativo']) && $parametros['ativo'] !== '') {
                $where[] = "sa.ativo = ?";
                $params[] = $parametros['ativo'];
            }
        }
        
        // Filtros específicos de documentos
        if ($tipo === 'documentos') {
            if (!empty($parametros['tipo_documento'])) {
                $where[] = "da.tipo_documento = ?";
                $params[] = $parametros['tipo_documento'];
            }
            
            if (isset($parametros['verificado']) && $parametros['verificado'] !== '') {
                $where[] = "da.verificado = ?";
                $params[] = $parametros['verificado'];
            }
        }
        
        // Busca geral - sempre usa tabela associados
        if (!empty($parametros['busca'])) {
            $busca = "%{$parametros['busca']}%";
            $where[] = "(a.nome LIKE ? OR a.cpf LIKE ? OR a.rg LIKE ?)";
            $params[] = $busca;
            $params[] = $busca;
            $params[] = $busca;
        }
        
        return $where;
    }
    
    /**
     * Buscar campos disponíveis para relatórios
     */
    public function getCamposDisponiveis($tipo = null, $categoria = null) {
        try {
            // Por enquanto, retorna campos hardcoded
            // Em produção, você deve buscar da tabela Campos_Relatorios
            
            $todos_campos = [
                'associados' => [
                    'Dados Pessoais' => [
                        ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome Completo', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'rg', 'nome_exibicao' => 'RG', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'nasc', 'nome_exibicao' => 'Data de Nascimento', 'tipo_dado' => 'data'],
                        ['nome_campo' => 'sexo', 'nome_exibicao' => 'Sexo', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'email', 'nome_exibicao' => 'E-mail', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'telefone', 'nome_exibicao' => 'Telefone', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'escolaridade', 'nome_exibicao' => 'Escolaridade', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'estadoCivil', 'nome_exibicao' => 'Estado Civil', 'tipo_dado' => 'texto']
                    ],
                    'Informações Militares' => [
                        ['nome_campo' => 'corporacao', 'nome_exibicao' => 'Corporação', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'patente', 'nome_exibicao' => 'Patente', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'categoria', 'nome_exibicao' => 'Categoria', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'lotacao', 'nome_exibicao' => 'Lotação', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'unidade', 'nome_exibicao' => 'Unidade', 'tipo_dado' => 'texto']
                    ],
                    'Situação' => [
                        ['nome_campo' => 'situacao', 'nome_exibicao' => 'Situação', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'dataFiliacao', 'nome_exibicao' => 'Data de Filiação', 'tipo_dado' => 'data'],
                        ['nome_campo' => 'dataDesfiliacao', 'nome_exibicao' => 'Data de Desfiliação', 'tipo_dado' => 'data'],
                        ['nome_campo' => 'indicacao', 'nome_exibicao' => 'Indicado por', 'tipo_dado' => 'texto']
                    ],
                    'Endereço' => [
                        ['nome_campo' => 'cep', 'nome_exibicao' => 'CEP', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'endereco', 'nome_exibicao' => 'Endereço', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'numero', 'nome_exibicao' => 'Número', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'bairro', 'nome_exibicao' => 'Bairro', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'cidade', 'nome_exibicao' => 'Cidade', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'complemento', 'nome_exibicao' => 'Complemento', 'tipo_dado' => 'texto']
                    ]
                ],
                'financeiro' => [
                    'Dados Financeiros' => [
                        ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'tipoAssociado', 'nome_exibicao' => 'Tipo de Associado', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'situacaoFinanceira', 'nome_exibicao' => 'Situação Financeira', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'vinculoServidor', 'nome_exibicao' => 'Vínculo Servidor', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'localDebito', 'nome_exibicao' => 'Local de Débito', 'tipo_dado' => 'texto']
                    ],
                    'Dados Bancários' => [
                        ['nome_campo' => 'agencia', 'nome_exibicao' => 'Agência', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'operacao', 'nome_exibicao' => 'Operação', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'contaCorrente', 'nome_exibicao' => 'Conta Corrente', 'tipo_dado' => 'texto']
                    ]
                ],
                'militar' => [
                    'Dados Pessoais' => [
                        ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'rg', 'nome_exibicao' => 'RG', 'tipo_dado' => 'texto']
                    ],
                    'Informações Militares' => [
                        ['nome_campo' => 'corporacao', 'nome_exibicao' => 'Corporação', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'patente', 'nome_exibicao' => 'Patente', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'categoria', 'nome_exibicao' => 'Categoria', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'lotacao', 'nome_exibicao' => 'Lotação', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'unidade', 'nome_exibicao' => 'Unidade', 'tipo_dado' => 'texto']
                    ],
                    'Situação' => [
                        ['nome_campo' => 'situacao', 'nome_exibicao' => 'Situação do Associado', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'dataFiliacao', 'nome_exibicao' => 'Data de Filiação', 'tipo_dado' => 'data']
                    ]
                ],
                'servicos' => [
                    'Dados do Associado' => [
                        ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'corporacao', 'nome_exibicao' => 'Corporação', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'patente', 'nome_exibicao' => 'Patente', 'tipo_dado' => 'texto']
                    ],
                    'Serviços' => [
                        ['nome_campo' => 'servico_nome', 'nome_exibicao' => 'Nome do Serviço', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'valor_aplicado', 'nome_exibicao' => 'Valor Aplicado', 'tipo_dado' => 'moeda'],
                        ['nome_campo' => 'percentual_aplicado', 'nome_exibicao' => 'Percentual Aplicado', 'tipo_dado' => 'percentual'],
                        ['nome_campo' => 'data_adesao', 'nome_exibicao' => 'Data de Adesão', 'tipo_dado' => 'data'],
                        ['nome_campo' => 'ativo', 'nome_exibicao' => 'Status do Serviço', 'tipo_dado' => 'boolean']
                    ]
                ],
                'documentos' => [
                    'Dados do Associado' => [
                        ['nome_campo' => 'nome', 'nome_exibicao' => 'Nome do Associado', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'cpf', 'nome_exibicao' => 'CPF', 'tipo_dado' => 'texto']
                    ],
                    'Documentos' => [
                        ['nome_campo' => 'tipo_documento', 'nome_exibicao' => 'Tipo de Documento', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'nome_arquivo', 'nome_exibicao' => 'Nome do Arquivo', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'data_upload', 'nome_exibicao' => 'Data de Upload', 'tipo_dado' => 'data'],
                        ['nome_campo' => 'verificado', 'nome_exibicao' => 'Status de Verificação', 'tipo_dado' => 'boolean'],
                        ['nome_campo' => 'funcionario_nome', 'nome_exibicao' => 'Verificado por', 'tipo_dado' => 'texto'],
                        ['nome_campo' => 'observacao', 'nome_exibicao' => 'Observações', 'tipo_dado' => 'texto']
                    ],
                    'Lote' => [
                        ['nome_campo' => 'lote_id', 'nome_exibicao' => 'ID do Lote', 'tipo_dado' => 'numero'],
                        ['nome_campo' => 'lote_status', 'nome_exibicao' => 'Status do Lote', 'tipo_dado' => 'texto']
                    ]
                ]
            ];
            
            if ($tipo && isset($todos_campos[$tipo])) {
                return $todos_campos[$tipo];
            }
            
            return $todos_campos;
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar campos disponíveis: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar histórico de relatórios
     */
    public function getHistorico($filtros = []) {
        try {
            $sql = "
                SELECT hr.*, mr.nome as nome_modelo, f.nome as gerado_por_nome
                FROM Historico_Relatorios hr
                LEFT JOIN Modelos_Relatorios mr ON hr.modelo_id = mr.id
                LEFT JOIN Funcionarios f ON hr.gerado_por = f.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (isset($filtros['funcionario_id'])) {
                $sql .= " AND hr.gerado_por = ?";
                $params[] = $filtros['funcionario_id'];
            }
            
            if (isset($filtros['modelo_id'])) {
                $sql .= " AND hr.modelo_id = ?";
                $params[] = $filtros['modelo_id'];
            }
            
            if (!empty($filtros['data_inicio'])) {
                $sql .= " AND hr.data_geracao >= ?";
                $params[] = $filtros['data_inicio'] . ' 00:00:00';
            }
            
            if (!empty($filtros['data_fim'])) {
                $sql .= " AND hr.data_geracao <= ?";
                $params[] = $filtros['data_fim'] . ' 23:59:59';
            }
            
            $sql .= " ORDER BY hr.data_geracao DESC";
            
            if (isset($filtros['limite'])) {
                $sql .= " LIMIT " . intval($filtros['limite']);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $historico = $stmt->fetchAll();
            
            // Decodificar parâmetros JSON
            foreach ($historico as &$item) {
                $item['parametros'] = json_decode($item['parametros'], true);
            }
            
            return $historico;
        } catch (PDOException $e) {
            error_log("Erro ao buscar histórico: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validar campos selecionados
     */
    private function validarCampos($campos) {
        if (empty($campos) || !is_array($campos)) {
            return false;
        }
        
        // Lista de campos válidos conhecidos
        $camposValidos = [
            // Dados Pessoais
            'nome', 'cpf', 'rg', 'nasc', 'sexo', 'email', 'telefone',
            'escolaridade', 'estadoCivil', 'indicacao',
            // Informações Militares
            'corporacao', 'patente', 'categoria', 'lotacao', 'unidade',
            // Situação
            'situacao', 'dataFiliacao', 'dataDesfiliacao',
            // Endereço
            'cep', 'endereco', 'numero', 'bairro', 'cidade', 'complemento',
            // Financeiro
            'tipoAssociado', 'situacaoFinanceira', 'vinculoServidor', 
            'localDebito', 'agencia', 'operacao', 'contaCorrente',
            // Serviços
            'servico_nome', 'valor_aplicado', 'percentual_aplicado', 
            'data_adesao', 'ativo',
            // Documentos
            'tipo_documento', 'nome_arquivo', 'data_upload', 'verificado',
            'funcionario_nome', 'observacao', 'lote_id', 'lote_status'
        ];
        
        // Por ora, aceita todos os campos enviados
        // Em produção, você deve validar contra a tabela Campos_Relatorios
        return true;
    }
    
    /**
     * Registrar histórico de execução
     */
    private function registrarHistorico($modelo_id, $parametros, $contagem) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Historico_Relatorios (modelo_id, nome_relatorio, parametros, gerado_por, formato, contagem_registros)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            // Buscar nome do modelo
            $modelo = $this->getModeloById($modelo_id);
            $nome_relatorio = $modelo['nome'] ?? 'Relatório';
            
            $stmt->execute([
                $modelo_id,
                $nome_relatorio,
                json_encode($parametros),
                $_SESSION['funcionario_id'] ?? null,
                'html', // Por padrão
                $contagem
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar histórico: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar auditoria
     */
    private function registrarAuditoria($acao, $registro_id, $dados_novos = [], $dados_antigos = []) {
        try {
            $funcionario_id = $_SESSION['funcionario_id'] ?? null;
            $alteracoes = [];
            
            if ($acao == 'UPDATE' && $dados_antigos) {
                foreach ($dados_novos as $campo => $valor_novo) {
                    if (isset($dados_antigos[$campo]) && $dados_antigos[$campo] != $valor_novo) {
                        $alteracoes[] = [
                            'campo' => $campo,
                            'valor_anterior' => $dados_antigos[$campo],
                            'valor_novo' => $valor_novo
                        ];
                    }
                }
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (tabela, acao, registro_id, funcionario_id, alteracoes, ip_origem, browser_info)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                'Modelos_Relatorios',
                $acao,
                $registro_id,
                $funcionario_id,
                json_encode($alteracoes),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
        }
    }
    
    /**
     * Obter estatísticas de uso dos relatórios
     */
    public function getEstatisticas($periodo = 30) {
        try {
            $data_inicio = date('Y-m-d', strtotime("-$periodo days"));
            
            // Relatórios mais utilizados
            $stmt = $this->db->prepare("
                SELECT mr.nome, COUNT(hr.id) as total_execucoes, 
                       AVG(hr.contagem_registros) as media_registros
                FROM Historico_Relatorios hr
                JOIN Modelos_Relatorios mr ON hr.modelo_id = mr.id
                WHERE hr.data_geracao >= ?
                GROUP BY mr.id
                ORDER BY total_execucoes DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio]);
            $mais_utilizados = $stmt->fetchAll();
            
            // Usuários mais ativos
            $stmt = $this->db->prepare("
                SELECT f.nome, COUNT(hr.id) as total_relatorios
                FROM Historico_Relatorios hr
                JOIN Funcionarios f ON hr.gerado_por = f.id
                WHERE hr.data_geracao >= ?
                GROUP BY f.id
                ORDER BY total_relatorios DESC
                LIMIT 10
            ");
            $stmt->execute([$data_inicio]);
            $usuarios_ativos = $stmt->fetchAll();
            
            // Total de relatórios gerados
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total, 
                       COUNT(DISTINCT gerado_por) as total_usuarios,
                       COUNT(DISTINCT modelo_id) as total_modelos
                FROM Historico_Relatorios
                WHERE data_geracao >= ?
            ");
            $stmt->execute([$data_inicio]);
            $totais = $stmt->fetch();
            
            return [
                'mais_utilizados' => $mais_utilizados,
                'usuarios_ativos' => $usuarios_ativos,
                'totais' => $totais,
                'periodo_dias' => $periodo
            ];
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Duplicar modelo de relatório
     */
    public function duplicarModelo($modelo_id, $novo_nome = null) {
        try {
            $this->db->beginTransaction();
            
            // Buscar modelo original
            $modelo = $this->getModeloById($modelo_id);
            if (!$modelo) {
                throw new Exception("Modelo não encontrado");
            }
            
            // Definir novo nome
            $novo_nome = $novo_nome ?: $modelo['nome'] . ' - Cópia';
            
            // Criar cópia
            $stmt = $this->db->prepare("
                INSERT INTO Modelos_Relatorios (nome, descricao, tipo, campos, filtros, ordenacao, criado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $novo_nome,
                $modelo['descricao'],
                $modelo['tipo'],
                json_encode($modelo['campos']),
                json_encode($modelo['filtros']),
                $modelo['ordenacao'],
                $_SESSION['funcionario_id'] ?? null
            ]);
            
            $novo_id = $this->db->lastInsertId();
            
            $this->db->commit();
            return $novo_id;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao duplicar modelo: " . $e->getMessage());
            throw $e;
        }
    }
}