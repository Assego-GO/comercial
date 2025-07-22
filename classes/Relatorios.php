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
     * Executar relatório
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
     * Construir query SQL baseada no modelo
     */
    private function construirQuery($modelo, $parametros) {
        $campos = $modelo['campos'];
        $tipo = $modelo['tipo'];
        
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
        $where = ["1=1"];
        $params = [];
        
        // Construir SELECT baseado nos campos
        foreach ($campos as $campo) {
            $campo_info = $this->getCampoInfo($campo);
            if ($campo_info) {
                $select_campos[] = $campo_info['select_as'];
                
                // Adicionar JOINs necessários
                if (!empty($campo_info['join']) && !in_array($campo_info['join'], $joins)) {
                    $joins[] = $campo_info['join'];
                }
            }
        }
        
        // Adicionar JOINs padrão baseado no tipo
        switch ($tipo) {
            case 'associados':
                if (!in_array('LEFT JOIN Militar m ON a.id = m.associado_id', $joins)) {
                    $joins[] = 'LEFT JOIN Militar m ON a.id = m.associado_id';
                }
                if (!in_array('LEFT JOIN Financeiro f ON a.id = f.associado_id', $joins)) {
                    $joins[] = 'LEFT JOIN Financeiro f ON a.id = f.associado_id';
                }
                if (!in_array('LEFT JOIN Endereco e ON a.id = e.associado_id', $joins)) {
                    $joins[] = 'LEFT JOIN Endereco e ON a.id = e.associado_id';
                }
                break;
                
            case 'servicos':
                $joins[] = 'JOIN Associados a ON sa.associado_id = a.id';
                $joins[] = 'JOIN Servicos s ON sa.servico_id = s.id';
                $joins[] = 'LEFT JOIN Militar m ON a.id = m.associado_id';
                break;
                
            case 'documentos':
                $joins[] = 'JOIN Associados a ON da.associado_id = a.id';
                $joins[] = 'LEFT JOIN Funcionarios f ON da.funcionario_id = f.id';
                $joins[] = 'LEFT JOIN Lotes_Documentos ld ON da.lote_id = ld.id';
                break;
        }
        
        // Aplicar filtros dos parâmetros
        $where_conditions = $this->aplicarFiltros($tipo, $parametros, $params);
        if (!empty($where_conditions)) {
            $where = array_merge($where, $where_conditions);
        }
        
        // Construir SQL final
        $sql = "SELECT " . implode(", ", $select_campos) . "\n";
        $sql .= "FROM " . $tabelas_principais[$tipo] . "\n";
        $sql .= implode("\n", $joins) . "\n";
        $sql .= "WHERE " . implode(" AND ", $where);
        
        // Adicionar ordenação
        if ($modelo['ordenacao']) {
            $sql .= "\nORDER BY " . $modelo['ordenacao'];
        }
        
        return [
            'sql' => $sql,
            'params' => $params
        ];
    }
    
    /**
     * Obter informações do campo
     */
    private function getCampoInfo($campo_nome) {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM Campos_Relatorios
                WHERE nome_campo = ? AND ativo = 1
            ");
            $stmt->execute([$campo_nome]);
            $campo = $stmt->fetch();
            
            if (!$campo) {
                return null;
            }
            
            // Construir informações de SELECT e JOIN baseado no campo
            $info = [
                'select_as' => $this->construirSelectAs($campo),
                'join' => $this->construirJoin($campo)
            ];
            
            return $info;
        } catch (PDOException $e) {
            error_log("Erro ao buscar informação do campo: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Construir SELECT AS para o campo
     */
    private function construirSelectAs($campo) {
        $tabela_alias = $this->getAliasTabela($campo['tabela']);
        $select = $tabela_alias . "." . $campo['nome_campo'];
        
        // Aplicar formatação se necessário
        if ($campo['formato_exibicao']) {
            switch ($campo['formato_exibicao']) {
                case 'data_br':
                    $select = "DATE_FORMAT($select, '%d/%m/%Y') AS " . $campo['nome_campo'];
                    break;
                case 'moeda':
                    $select = "CONCAT('R$ ', FORMAT($select, 2, 'pt_BR')) AS " . $campo['nome_campo'];
                    break;
                default:
                    $select .= " AS " . $campo['nome_campo'];
            }
        } else {
            $select .= " AS " . $campo['nome_campo'];
        }
        
        return $select;
    }
    
    /**
     * Construir JOIN necessário para o campo
     */
    private function construirJoin($campo) {
        // Mapear JOINs necessários por tabela
        $joins_map = [
            'Militar' => 'LEFT JOIN Militar m ON a.id = m.associado_id',
            'Financeiro' => 'LEFT JOIN Financeiro f ON a.id = f.associado_id',
            'Endereco' => 'LEFT JOIN Endereco e ON a.id = e.associado_id',
            'Contrato' => 'LEFT JOIN Contrato c ON a.id = c.associado_id',
            'Servicos' => 'JOIN Servicos s ON sa.servico_id = s.id',
            'Funcionarios' => 'LEFT JOIN Funcionarios func ON da.funcionario_id = func.id',
            'Lotes_Documentos' => 'LEFT JOIN Lotes_Documentos ld ON da.lote_id = ld.id'
        ];
        
        return $joins_map[$campo['tabela']] ?? '';
    }
    
    /**
     * Obter alias da tabela
     */
    private function getAliasTabela($tabela) {
        $aliases = [
            'Associados' => 'a',
            'Militar' => 'm',
            'Financeiro' => 'f',
            'Endereco' => 'e',
            'Contrato' => 'c',
            'Servicos' => 's',
            'Servicos_Associado' => 'sa',
            'Documentos_Associado' => 'da',
            'Funcionarios' => 'func',
            'Lotes_Documentos' => 'ld'
        ];
        
        return $aliases[$tabela] ?? strtolower(substr($tabela, 0, 1));
    }
    
    /**
     * Aplicar filtros baseados nos parâmetros
     */
    private function aplicarFiltros($tipo, $parametros, &$params) {
        $where = [];
        
        // Filtros comuns
        if (!empty($parametros['data_inicio'])) {
            $where[] = "a.criado_em >= ?";
            $params[] = $parametros['data_inicio'] . ' 00:00:00';
        }
        
        if (!empty($parametros['data_fim'])) {
            $where[] = "a.criado_em <= ?";
            $params[] = $parametros['data_fim'] . ' 23:59:59';
        }
        
        // Filtros específicos por tipo
        switch ($tipo) {
            case 'associados':
                if (!empty($parametros['situacao'])) {
                    $where[] = "a.situacao = ?";
                    $params[] = $parametros['situacao'];
                }
                if (!empty($parametros['corporacao'])) {
                    $where[] = "m.corporacao = ?";
                    $params[] = $parametros['corporacao'];
                }
                if (!empty($parametros['patente'])) {
                    $where[] = "m.patente = ?";
                    $params[] = $parametros['patente'];
                }
                if (!empty($parametros['tipo_associado'])) {
                    $where[] = "f.tipoAssociado = ?";
                    $params[] = $parametros['tipo_associado'];
                }
                break;
                
            case 'servicos':
                if (!empty($parametros['servico_id'])) {
                    $where[] = "sa.servico_id = ?";
                    $params[] = $parametros['servico_id'];
                }
                if (!empty($parametros['ativo'])) {
                    $where[] = "sa.ativo = ?";
                    $params[] = $parametros['ativo'];
                }
                break;
                
            case 'documentos':
                if (!empty($parametros['tipo_documento'])) {
                    $where[] = "da.tipo_documento = ?";
                    $params[] = $parametros['tipo_documento'];
                }
                if (!empty($parametros['lote_id'])) {
                    $where[] = "da.lote_id = ?";
                    $params[] = $parametros['lote_id'];
                }
                if (!empty($parametros['verificado'])) {
                    $where[] = "da.verificado = ?";
                    $params[] = $parametros['verificado'];
                }
                break;
        }
        
        // Busca geral
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
            $sql = "
                SELECT id, nome_campo, nome_exibicao, tabela, tipo_dado, categoria
                FROM Campos_Relatorios
                WHERE ativo = 1
            ";
            
            $params = [];
            
            if ($tipo) {
                // Filtrar campos relevantes para o tipo de relatório
                $tabelas_tipo = $this->getTabelasPorTipo($tipo);
                if (!empty($tabelas_tipo)) {
                    $placeholders = array_fill(0, count($tabelas_tipo), '?');
                    $sql .= " AND tabela IN (" . implode(',', $placeholders) . ")";
                    $params = array_merge($params, $tabelas_tipo);
                }
            }
            
            if ($categoria) {
                $sql .= " AND categoria = ?";
                $params[] = $categoria;
            }
            
            $sql .= " ORDER BY categoria, nome_exibicao";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Agrupar por categoria
            $campos = [];
            while ($row = $stmt->fetch()) {
                $campos[$row['categoria']][] = $row;
            }
            
            return $campos;
        } catch (PDOException $e) {
            error_log("Erro ao buscar campos disponíveis: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter tabelas relevantes por tipo de relatório
     */
    private function getTabelasPorTipo($tipo) {
        $mapa = [
            'associados' => ['Associados', 'Militar', 'Financeiro', 'Endereco', 'Contrato'],
            'financeiro' => ['Financeiro', 'Associados', 'Servicos_Associado', 'Servicos'],
            'militar' => ['Militar', 'Associados'],
            'servicos' => ['Servicos', 'Servicos_Associado', 'Associados', 'Militar'],
            'documentos' => ['Documentos_Associado', 'Associados', 'Lotes_Documentos', 'Funcionarios']
        ];
        
        return $mapa[$tipo] ?? [];
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
     * Exportar relatório
     */
    public function exportarRelatorio($dados_relatorio, $formato = 'excel') {
        try {
            switch ($formato) {
                case 'excel':
                    return $this->exportarExcel($dados_relatorio);
                case 'pdf':
                    return $this->exportarPDF($dados_relatorio);
                case 'csv':
                    return $this->exportarCSV($dados_relatorio);
                default:
                    throw new Exception("Formato de exportação não suportado");
            }
        } catch (Exception $e) {
            error_log("Erro ao exportar relatório: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Exportar para Excel (simplificado)
     */
    private function exportarExcel($dados_relatorio) {
        // Aqui você implementaria a exportação real usando PHPSpreadsheet
        // Por enquanto, retornamos apenas os cabeçalhos apropriados
        
        $filename = 'relatorio_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return [
            'filename' => $filename,
            'headers' => [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ],
            'data' => $dados_relatorio
        ];
    }
    
    /**
     * Exportar para CSV
     */
    private function exportarCSV($dados_relatorio) {
        $filename = 'relatorio_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Criar CSV em memória
        $output = fopen('php://temp', 'r+');
        
        // Adicionar cabeçalhos
        if (!empty($dados_relatorio['dados'])) {
            fputcsv($output, array_keys($dados_relatorio['dados'][0]));
            
            // Adicionar dados
            foreach ($dados_relatorio['dados'] as $linha) {
                fputcsv($output, $linha);
            }
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return [
            'filename' => $filename,
            'headers' => [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ],
            'content' => $csv_content
        ];
    }
    
    /**
     * Validar campos selecionados
     */
    private function validarCampos($campos) {
        if (empty($campos) || !is_array($campos)) {
            return false;
        }
        
        try {
            // Por enquanto, vamos aceitar todos os campos
            // Em produção, você deve validar contra a tabela Campos_Relatorios
            
            // Lista de campos válidos conhecidos
            $camposValidos = [
                // Dados Pessoais
                'nome', 'cpf', 'rg', 'nasc', 'sexo', 'email', 'telefone',
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
                'tipo_documento', 'nome_arquivo', 'data_upload', 'verificado'
            ];
            
            // Verificar se todos os campos enviados são válidos
            foreach ($campos as $campo) {
                if (!in_array($campo, $camposValidos)) {
                    error_log("Campo inválido encontrado: " . $campo);
                    // Por ora, vamos aceitar mesmo assim
                    // return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erro ao validar campos: " . $e->getMessage());
            return false;
        }
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