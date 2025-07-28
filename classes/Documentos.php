<?php
/**
 * Classe para gerenciamento de documentos com fluxo de assinatura
 * classes/Documentos.php
 */

class Documentos {
    private $db;
    private $uploadDir;
    private $maxFileSize = 10485760; // 10MB
    private $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    private $tiposDocumentos = [
        'ficha_associacao' => 'Ficha de Associação',
        'contrato_associacao' => 'Contrato de Associação',
        'rg' => 'RG',
        'cpf' => 'CPF', 
        'comprovante_residencia' => 'Comprovante de Residência',
        'contra_cheque' => 'Contra-cheque',
        'certidao_nascimento' => 'Certidão de Nascimento',
        'certidao_casamento' => 'Certidão de Casamento',
        'foto_3x4' => 'Foto 3x4',
        'outros' => 'Outros'
    ];
    
    // Status do fluxo de documentos
    const STATUS_DIGITALIZADO = 'DIGITALIZADO';
    const STATUS_AGUARDANDO_ASSINATURA = 'AGUARDANDO_ASSINATURA';
    const STATUS_ASSINADO = 'ASSINADO';
    const STATUS_FINALIZADO = 'FINALIZADO';
    
    public function __construct() {
        $this->db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        
        // Ajustar caminho do upload para ser relativo ao projeto
        $this->uploadDir = dirname(__DIR__) . '/uploads/documentos/';
        
        // Criar diretório se não existir
        if (!file_exists($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                error_log("Erro ao criar diretório de uploads: " . $this->uploadDir);
                // Usar diretório temporário como fallback
                $this->uploadDir = sys_get_temp_dir() . '/uploads/documentos/';
                if (!file_exists($this->uploadDir)) {
                    mkdir($this->uploadDir, 0755, true);
                }
            }
        }
    }
    
    /**
     * Upload de documento com controle de fluxo
     */
    public function uploadDocumentoAssociacao($associadoId, $arquivo, $tipoOrigem = 'FISICO', $observacao = null) {
        try {
            $this->db->beginTransaction();
            
            // Fazer upload do arquivo
            $documentoId = $this->upload(
                $associadoId, 
                $arquivo, 
                'ficha_associacao', 
                $observacao
            );
            
            // Buscar departamento comercial
            $stmt = $this->db->prepare("SELECT id FROM Departamentos WHERE nome = 'Comercial' LIMIT 1");
            $stmt->execute();
            $dept = $stmt->fetch();
            $deptComercial = $dept ? $dept['id'] : 1; // Usar 1 como fallback
            
            // Atualizar documento com informações do fluxo
            $stmt = $this->db->prepare("
                UPDATE Documentos_Associado 
                SET tipo_origem = ?,
                    status_fluxo = ?,
                    departamento_atual = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $tipoOrigem,
                self::STATUS_DIGITALIZADO,
                $deptComercial,
                $documentoId
            ]);
            
            // Registrar no histórico do fluxo
            $this->registrarHistoricoFluxo(
                $documentoId,
                null,
                self::STATUS_DIGITALIZADO,
                null,
                $deptComercial,
                "Documento {$tipoOrigem} digitalizado e cadastrado no sistema"
            );
            
            $this->db->commit();
            return $documentoId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Enviar documento para assinatura na presidência
     */
    public function enviarParaAssinatura($documentoId, $observacao = null) {
        try {
            $this->db->beginTransaction();
            
            // Buscar documento
            $documento = $this->getById($documentoId);
            if (!$documento) {
                throw new Exception("Documento não encontrado");
            }
            
            // Verificar se pode ser enviado para assinatura
            if (!in_array($documento['status_fluxo'], [self::STATUS_DIGITALIZADO, self::STATUS_ASSINADO])) {
                throw new Exception("Documento não pode ser enviado para assinatura no status atual");
            }
            
            // Buscar departamento presidência
            $stmt = $this->db->prepare("SELECT id FROM Departamentos WHERE nome = 'Presidência' LIMIT 1");
            $stmt->execute();
            $dept = $stmt->fetch();
            $deptPresidencia = $dept ? $dept['id'] : 2; // Usar 2 como fallback
            
            // Atualizar status
            $stmt = $this->db->prepare("
                UPDATE Documentos_Associado 
                SET status_fluxo = ?,
                    departamento_atual = ?,
                    data_envio_assinatura = NOW(),
                    observacoes_fluxo = CONCAT(IFNULL(observacoes_fluxo, ''), ?)
                WHERE id = ?
            ");
            
            $obsAdicional = $observacao ? "\n[" . date('d/m/Y H:i') . "] Enviado para assinatura: " . $observacao : "";
            
            $stmt->execute([
                self::STATUS_AGUARDANDO_ASSINATURA,
                $deptPresidencia,
                $obsAdicional,
                $documentoId
            ]);
            
            // Registrar no histórico
            $this->registrarHistoricoFluxo(
                $documentoId,
                $documento['status_fluxo'],
                self::STATUS_AGUARDANDO_ASSINATURA,
                $documento['departamento_atual'],
                $deptPresidencia,
                $observacao ?? "Documento enviado para assinatura"
            );
            
            // Notificar presidência (implementar sistema de notificação)
            $this->notificarDepartamento($deptPresidencia, "Novo documento aguardando assinatura", $documentoId);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Assinar documento (presidência)
     */
    public function assinarDocumento($documentoId, $arquivoAssinado = null, $observacao = null) {
        try {
            $this->db->beginTransaction();
            
            // Buscar documento
            $documento = $this->getById($documentoId);
            if (!$documento) {
                throw new Exception("Documento não encontrado");
            }
            
            // Verificar se está aguardando assinatura
            if ($documento['status_fluxo'] !== self::STATUS_AGUARDANDO_ASSINATURA) {
                throw new Exception("Documento não está aguardando assinatura");
            }
            
            // Se foi fornecido arquivo assinado, fazer upload
            $caminhoArquivoAssinado = null;
            if ($arquivoAssinado && is_array($arquivoAssinado) && $arquivoAssinado['error'] === UPLOAD_ERR_OK) {
                $extensao = strtolower(pathinfo($arquivoAssinado['name'], PATHINFO_EXTENSION));
                $nomeArquivoAssinado = $this->gerarNomeArquivo($documento['associado_id'], 'assinado', $extensao);
                
                $subdir = $this->uploadDir . $documento['associado_id'] . '/';
                if (!file_exists($subdir)) {
                    mkdir($subdir, 0755, true);
                }
                
                $caminhoCompleto = $subdir . $nomeArquivoAssinado;
                $caminhoArquivoAssinado = 'uploads/documentos/' . $documento['associado_id'] . '/' . $nomeArquivoAssinado;
                
                if (!move_uploaded_file($arquivoAssinado['tmp_name'], $caminhoCompleto)) {
                    throw new Exception("Erro ao salvar arquivo assinado");
                }
            }
            
            // Buscar departamento comercial para retorno
            $stmt = $this->db->prepare("SELECT id FROM Departamentos WHERE nome = 'Comercial' LIMIT 1");
            $stmt->execute();
            $dept = $stmt->fetch();
            $deptComercial = $dept ? $dept['id'] : 1;
            
            // Atualizar documento
            $stmt = $this->db->prepare("
                UPDATE Documentos_Associado 
                SET status_fluxo = ?,
                    departamento_atual = ?,
                    data_assinatura = NOW(),
                    assinado_por = ?,
                    arquivo_assinado = ?,
                    observacoes_fluxo = CONCAT(IFNULL(observacoes_fluxo, ''), ?)
                WHERE id = ?
            ");
            
            $obsAdicional = "\n[" . date('d/m/Y H:i') . "] Assinado" . ($observacao ? ": " . $observacao : "");
            
            $stmt->execute([
                self::STATUS_ASSINADO,
                $deptComercial,
                $_SESSION['funcionario_id'],
                $caminhoArquivoAssinado,
                $obsAdicional,
                $documentoId
            ]);
            
            // Registrar no histórico
            $this->registrarHistoricoFluxo(
                $documentoId,
                self::STATUS_AGUARDANDO_ASSINATURA,
                self::STATUS_ASSINADO,
                $documento['departamento_atual'],
                $deptComercial,
                "Documento assinado e devolvido ao comercial"
            );
            
            // Notificar comercial
            $this->notificarDepartamento($deptComercial, "Documento assinado e devolvido", $documentoId);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Finalizar processo de documentação
     */
    public function finalizarProcesso($documentoId, $observacao = null) {
        try {
            $this->db->beginTransaction();
            
            // Buscar documento
            $documento = $this->getById($documentoId);
            if (!$documento) {
                throw new Exception("Documento não encontrado");
            }
            
            // Verificar se está assinado
            if ($documento['status_fluxo'] !== self::STATUS_ASSINADO) {
                throw new Exception("Documento precisa estar assinado para ser finalizado");
            }
            
            // Atualizar status
            $stmt = $this->db->prepare("
                UPDATE Documentos_Associado 
                SET status_fluxo = ?,
                    data_finalizacao = NOW(),
                    verificado = 1,
                    observacoes_fluxo = CONCAT(IFNULL(observacoes_fluxo, ''), ?)
                WHERE id = ?
            ");
            
            $obsAdicional = "\n[" . date('d/m/Y H:i') . "] Processo finalizado" . ($observacao ? ": " . $observacao : "");
            
            $stmt->execute([
                self::STATUS_FINALIZADO,
                $obsAdicional,
                $documentoId
            ]);
            
            // Registrar no histórico
            $this->registrarHistoricoFluxo(
                $documentoId,
                self::STATUS_ASSINADO,
                self::STATUS_FINALIZADO,
                $documento['departamento_atual'],
                $documento['departamento_atual'],
                "Processo de documentação finalizado"
            );
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Gerar ficha de associação virtual
     */
    public function gerarFichaVirtual($associadoId) {
        try {
            // Buscar dados do associado
            $stmt = $this->db->prepare("
                SELECT a.*, e.*, m.*, f.*
                FROM Associados a
                LEFT JOIN Endereco e ON a.id = e.associado_id
                LEFT JOIN Militar m ON a.id = m.associado_id
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE a.id = ?
            ");
            $stmt->execute([$associadoId]);
            $dadosAssociado = $stmt->fetch();
            
            if (!$dadosAssociado) {
                throw new Exception("Associado não encontrado");
            }
            
            // Aqui você pode usar uma biblioteca como TCPDF ou DomPDF
            // Por enquanto, vamos criar um HTML que pode ser convertido em PDF
            
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Ficha de Associação - ASSEGO</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 30px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .field { margin-bottom: 15px; }
                    .field label { font-weight: bold; display: inline-block; width: 200px; }
                    .signature { margin-top: 50px; border-top: 1px solid #000; width: 300px; text-align: center; }
                    h1 { color: #0056D2; }
                    h2 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    table td { padding: 8px; border-bottom: 1px solid #ddd; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>ASSEGO - Associação dos Servidores</h1>
                    <h2>Ficha de Associação</h2>
                </div>
                
                <h3>Dados Pessoais</h3>
                <div class="field">
                    <label>Nome Completo:</label> ' . htmlspecialchars($dadosAssociado['nome']) . '
                </div>
                
                <div class="field">
                    <label>CPF:</label> ' . $this->formatarCPF($dadosAssociado['cpf']) . '
                </div>
                
                <div class="field">
                    <label>RG:</label> ' . htmlspecialchars($dadosAssociado['rg']) . '
                </div>
                
                <div class="field">
                    <label>Data de Nascimento:</label> ' . date('d/m/Y', strtotime($dadosAssociado['nasc'])) . '
                </div>
                
                <div class="field">
                    <label>Email:</label> ' . htmlspecialchars($dadosAssociado['email']) . '
                </div>
                
                <div class="field">
                    <label>Telefone:</label> ' . htmlspecialchars($dadosAssociado['telefone']) . '
                </div>';
                
            if ($dadosAssociado['endereco']) {
                $html .= '
                <h3>Endereço</h3>
                <div class="field">
                    <label>Endereço:</label> ' . htmlspecialchars($dadosAssociado['endereco']) . ', ' . htmlspecialchars($dadosAssociado['numero']) . '
                </div>
                <div class="field">
                    <label>Bairro:</label> ' . htmlspecialchars($dadosAssociado['bairro']) . '
                </div>
                <div class="field">
                    <label>Cidade:</label> ' . htmlspecialchars($dadosAssociado['cidade']) . '
                </div>
                <div class="field">
                    <label>CEP:</label> ' . htmlspecialchars($dadosAssociado['cep']) . '
                </div>';
            }
            
            if ($dadosAssociado['corporacao']) {
                $html .= '
                <h3>Dados Militares</h3>
                <div class="field">
                    <label>Corporação:</label> ' . htmlspecialchars($dadosAssociado['corporacao']) . '
                </div>
                <div class="field">
                    <label>Patente:</label> ' . htmlspecialchars($dadosAssociado['patente']) . '
                </div>
                <div class="field">
                    <label>Unidade:</label> ' . htmlspecialchars($dadosAssociado['unidade']) . '
                </div>';
            }
            
            $html .= '
                <div class="field">
                    <label>Data de Preenchimento:</label> ' . date('d/m/Y') . '
                </div>
                
                <div style="margin-top: 100px;">
                    <table>
                        <tr>
                            <td style="text-align: center; width: 50%;">
                                <div class="signature">
                                    Assinatura do Associado
                                </div>
                            </td>
                            <td style="text-align: center; width: 50%;">
                                <div class="signature">
                                    Assinatura do Responsável ASSEGO
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </body>
            </html>';
            
            // Salvar como arquivo temporário
            $nomeArquivo = 'ficha_virtual_' . $associadoId . '_' . time() . '.html';
            $caminhoTemp = sys_get_temp_dir() . '/' . $nomeArquivo;
            file_put_contents($caminhoTemp, $html);
            
            return [
                'html' => $html,
                'arquivo_temp' => $caminhoTemp,
                'nome_arquivo' => $nomeArquivo,
                'associado_nome' => $dadosAssociado['nome']
            ];
            
        } catch (Exception $e) {
            error_log("Erro ao gerar ficha virtual: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Listar documentos em fluxo
     */
    public function listarDocumentosEmFluxo($filtros = []) {
        try {
            $sql = "
                SELECT 
                    d.*,
                    a.nome as associado_nome,
                    a.cpf as associado_cpf,
                    dept.nome as departamento_atual_nome,
                    f_upload.nome as funcionario_upload,
                    f_assinatura.nome as assinado_por_nome,
                    CASE 
                        WHEN d.status_fluxo = 'DIGITALIZADO' THEN 'Aguardando envio para assinatura'
                        WHEN d.status_fluxo = 'AGUARDANDO_ASSINATURA' THEN 'Na presidência para assinatura'
                        WHEN d.status_fluxo = 'ASSINADO' THEN 'Assinado, aguardando finalização'
                        WHEN d.status_fluxo = 'FINALIZADO' THEN 'Processo concluído'
                    END as status_descricao,
                    DATEDIFF(NOW(), d.data_upload) as dias_em_processo
                FROM Documentos_Associado d
                JOIN Associados a ON d.associado_id = a.id
                LEFT JOIN Departamentos dept ON d.departamento_atual = dept.id
                LEFT JOIN Funcionarios f_upload ON d.funcionario_id = f_upload.id
                LEFT JOIN Funcionarios f_assinatura ON d.assinado_por = f_assinatura.id
                WHERE d.tipo_documento IN ('ficha_associacao', 'contrato_associacao')
            ";
            
            $params = [];
            
            if (!empty($filtros['status'])) {
                $sql .= " AND d.status_fluxo = ?";
                $params[] = $filtros['status'];
            }
            
            if (!empty($filtros['origem'])) {
                $sql .= " AND d.tipo_origem = ?";
                $params[] = $filtros['origem'];
            }
            
            if (!empty($filtros['busca'])) {
                $sql .= " AND (a.nome LIKE ? OR a.cpf LIKE ?)";
                $busca = "%{$filtros['busca']}%";
                $params[] = $busca;
                $params[] = $busca;
            }
            
            // Ordenação
            $sql .= " ORDER BY 
                FIELD(d.status_fluxo, 'AGUARDANDO_ASSINATURA', 'DIGITALIZADO', 'ASSINADO', 'FINALIZADO'),
                d.data_upload DESC";
            
            // Paginação
            if (isset($filtros['limit']) && isset($filtros['offset'])) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = intval($filtros['limit']);
                $params[] = intval($filtros['offset']);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao listar documentos em fluxo: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter histórico do fluxo
     */
    public function getHistoricoFluxo($documentoId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    h.*,
                    f.nome as funcionario_nome,
                    do.nome as dept_origem_nome,
                    dd.nome as dept_destino_nome
                FROM Historico_Fluxo_Documento h
                JOIN Funcionarios f ON h.funcionario_id = f.id
                LEFT JOIN Departamentos do ON h.departamento_origem = do.id
                LEFT JOIN Departamentos dd ON h.departamento_destino = dd.id
                WHERE h.documento_id = ?
                ORDER BY h.data_acao ASC
            ");
            
            $stmt->execute([$documentoId]);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar histórico: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Estatísticas do fluxo de documentos
     */
    public function getEstatisticasFluxo() {
        try {
            $stats = [];
            
            // Total por status
            $stmt = $this->db->query("
                SELECT 
                    status_fluxo,
                    COUNT(*) as total
                FROM Documentos_Associado
                WHERE tipo_documento IN ('ficha_associacao', 'contrato_associacao')
                GROUP BY status_fluxo
            ");
            $stats['por_status'] = $stmt->fetchAll();
            
            // Tempo médio de processamento
            $stmt = $this->db->query("
                SELECT 
                    AVG(DATEDIFF(data_finalizacao, data_upload)) as tempo_medio_dias
                FROM Documentos_Associado
                WHERE status_fluxo = 'FINALIZADO'
                AND tipo_documento IN ('ficha_associacao', 'contrato_associacao')
            ");
            $result = $stmt->fetch();
            $stats['tempo_medio_processamento'] = $result['tempo_medio_dias'] ?? 0;
            
            // Documentos por origem
            $stmt = $this->db->query("
                SELECT 
                    tipo_origem,
                    COUNT(*) as total
                FROM Documentos_Associado
                WHERE tipo_documento IN ('ficha_associacao', 'contrato_associacao')
                GROUP BY tipo_origem
            ");
            $stats['por_origem'] = $stmt->fetchAll();
            
            // Documentos processados hoje
            $stmt = $this->db->query("
                SELECT COUNT(*) as total
                FROM Documentos_Associado
                WHERE DATE(data_upload) = CURDATE()
                AND tipo_documento IN ('ficha_associacao', 'contrato_associacao')
            ");
            $result = $stmt->fetch();
            $stats['processados_hoje'] = $result['total'];
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Registrar histórico do fluxo
     */
    private function registrarHistoricoFluxo($documentoId, $statusAnterior, $statusNovo, $deptOrigem, $deptDestino, $observacao) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Historico_Fluxo_Documento (
                    documento_id, status_anterior, status_novo,
                    departamento_origem, departamento_destino,
                    funcionario_id, observacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $documentoId,
                $statusAnterior,
                $statusNovo,
                $deptOrigem,
                $deptDestino,
                $_SESSION['funcionario_id'] ?? 1,
                $observacao
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar histórico: " . $e->getMessage());
        }
    }
    
    /**
     * Notificar departamento (placeholder para sistema de notificação)
     */
    private function notificarDepartamento($departamentoId, $mensagem, $documentoId) {
        // Implementar sistema de notificação
        // Por enquanto, apenas registrar
        error_log("Notificação para departamento {$departamentoId}: {$mensagem} (Doc: {$documentoId})");
    }
    
    /**
     * Formatar CPF
     */
    private function formatarCPF($cpf) {
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }
    
    // Métodos originais mantidos...
    
    /**
     * Upload de documento
     */
    public function upload($associadoId, $arquivo, $tipoDocumento, $observacao = null, $loteId = null) {
        try {
            // Validações
            if (!$this->validarArquivo($arquivo)) {
                throw new Exception("Arquivo inválido");
            }
            
            // Gerar nome único
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $nomeArquivo = $this->gerarNomeArquivo($associadoId, $tipoDocumento, $extensao);
            
            // Criar subdiretório por associado
            $subdir = $this->uploadDir . $associadoId . '/';
            if (!file_exists($subdir)) {
                if (!mkdir($subdir, 0755, true)) {
                    throw new Exception("Erro ao criar diretório para o associado");
                }
            }
            
            $caminhoCompleto = $subdir . $nomeArquivo;
            $caminhoRelativo = 'uploads/documentos/' . $associadoId . '/' . $nomeArquivo;
            
            // Mover arquivo
            if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
                throw new Exception("Erro ao salvar arquivo");
            }
            
            // Calcular hash do arquivo
            $hashArquivo = hash_file('sha256', $caminhoCompleto);
            
            // Verificar duplicidade
            if ($this->hashExiste($hashArquivo, $associadoId)) {
                unlink($caminhoCompleto);
                throw new Exception("Este arquivo já foi enviado anteriormente");
            }
            
            // Inserir no banco
            $stmt = $this->db->prepare("
                INSERT INTO Documentos_Associado (
                    associado_id, tipo_documento, nome_arquivo, 
                    caminho_arquivo, hash_arquivo, verificado,
                    funcionario_id, lote_id, observacao
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $associadoId,
                $tipoDocumento,
                $arquivo['name'],
                $caminhoRelativo,
                $hashArquivo,
                0, // não verificado
                $_SESSION['funcionario_id'] ?? null,
                $loteId,
                $observacao
            ]);
            
            $documentoId = $this->db->lastInsertId();
            
            // Registrar na auditoria
            $this->registrarAuditoria('UPLOAD', $documentoId, $associadoId);
            
            return $documentoId;
            
        } catch (Exception $e) {
            error_log("Erro no upload de documento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Listar documentos
     */
    public function listar($filtros = []) {
        try {
            $sql = "
                SELECT 
                    d.*,
                    a.nome as associado_nome,
                    a.cpf as associado_cpf,
                    f.nome as funcionario_nome,
                    l.observacao as lote_observacao,
                    l.data_geracao as lote_data
                FROM Documentos_Associado d
                JOIN Associados a ON d.associado_id = a.id
                LEFT JOIN Funcionarios f ON d.funcionario_id = f.id
                LEFT JOIN Lotes_Documentos l ON d.lote_id = l.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filtros['associado_id'])) {
                $sql .= " AND d.associado_id = ?";
                $params[] = $filtros['associado_id'];
            }
            
            if (!empty($filtros['tipo_documento'])) {
                $sql .= " AND d.tipo_documento = ?";
                $params[] = $filtros['tipo_documento'];
            }
            
            if (!empty($filtros['verificado'])) {
                $sql .= " AND d.verificado = ?";
                $params[] = $filtros['verificado'] == 'sim' ? 1 : 0;
            }
            
            if (!empty($filtros['periodo'])) {
                switch($filtros['periodo']) {
                    case 'hoje':
                        $sql .= " AND DATE(d.data_upload) = CURDATE()";
                        break;
                    case 'semana':
                        $sql .= " AND d.data_upload >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                        break;
                    case 'mes':
                        $sql .= " AND d.data_upload >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                        break;
                    case 'ano':
                        $sql .= " AND YEAR(d.data_upload) = YEAR(CURDATE())";
                        break;
                }
            }
            
            if (!empty($filtros['busca'])) {
                $sql .= " AND (
                    a.nome LIKE ? OR 
                    a.cpf LIKE ? OR 
                    d.nome_arquivo LIKE ? OR
                    d.observacao LIKE ?
                )";
                $busca = "%{$filtros['busca']}%";
                $params = array_merge($params, [$busca, $busca, $busca, $busca]);
            }
            
            // Ordenação
            $sql .= " ORDER BY d.data_upload DESC";
            
            // Paginação
            if (isset($filtros['limit']) && isset($filtros['offset'])) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = intval($filtros['limit']);
                $params[] = intval($filtros['offset']);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $documentos = $stmt->fetchAll();
            
            // Adicionar informações extras
            foreach ($documentos as &$doc) {
                $doc['tipo_documento_nome'] = $this->tiposDocumentos[$doc['tipo_documento']] ?? $doc['tipo_documento'];
                $doc['tamanho_formatado'] = $this->formatarTamanho($doc['caminho_arquivo']);
                $doc['extensao'] = pathinfo($doc['nome_arquivo'], PATHINFO_EXTENSION);
            }
            
            return $documentos;
            
        } catch (PDOException $e) {
            error_log("Erro ao listar documentos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buscar documento por ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    d.*,
                    a.nome as associado_nome,
                    a.cpf as associado_cpf,
                    f.nome as funcionario_nome
                FROM Documentos_Associado d
                JOIN Associados a ON d.associado_id = a.id
                LEFT JOIN Funcionarios f ON d.funcionario_id = f.id
                WHERE d.id = ?
            ");
            
            $stmt->execute([$id]);
            $documento = $stmt->fetch();
            
            if ($documento) {
                $documento['tipo_documento_nome'] = $this->tiposDocumentos[$documento['tipo_documento']] ?? $documento['tipo_documento'];
                $documento['tamanho_formatado'] = $this->formatarTamanho($documento['caminho_arquivo']);
                $documento['extensao'] = pathinfo($documento['nome_arquivo'], PATHINFO_EXTENSION);
            }
            
            return $documento;
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar documento: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar documento
     */
    public function verificar($id, $observacao = null) {
        try {
            $this->db->beginTransaction();
            
            // Buscar documento
            $documento = $this->getById($id);
            if (!$documento) {
                throw new Exception("Documento não encontrado");
            }
            
            // Atualizar status
            $stmt = $this->db->prepare("
                UPDATE Documentos_Associado 
                SET verificado = 1,
                    funcionario_id = ?,
                    observacao = CONCAT(IFNULL(observacao, ''), ?)
                WHERE id = ?
            ");
            
            $obsAdicional = $observacao ? "\nVerificado: " . $observacao : "";
            
            $stmt->execute([
                $_SESSION['funcionario_id'] ?? null,
                $obsAdicional,
                $id
            ]);
            
            // Registrar na auditoria
            $this->registrarAuditoria('VERIFICAR', $id, $documento['associado_id']);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao verificar documento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Excluir documento
     */
    public function excluir($id) {
        try {
            $this->db->beginTransaction();
            
            // Buscar documento
            $documento = $this->getById($id);
            if (!$documento) {
                throw new Exception("Documento não encontrado");
            }
            
            // Excluir arquivo físico
            $caminhoCompleto = dirname(__DIR__) . '/' . $documento['caminho_arquivo'];
            if (file_exists($caminhoCompleto)) {
                unlink($caminhoCompleto);
            }
            
            // Se tiver arquivo assinado, excluir também
            if ($documento['arquivo_assinado']) {
                $caminhoAssinado = dirname(__DIR__) . '/' . $documento['arquivo_assinado'];
                if (file_exists($caminhoAssinado)) {
                    unlink($caminhoAssinado);
                }
            }
            
            // Excluir do banco
            $stmt = $this->db->prepare("DELETE FROM Documentos_Associado WHERE id = ?");
            $stmt->execute([$id]);
            
            // Registrar na auditoria
            $this->registrarAuditoria('DELETE', $id, $documento['associado_id']);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erro ao excluir documento: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Download de documento
     */
    public function download($id) {
        try {
            $documento = $this->getById($id);
            if (!$documento) {
                throw new Exception("Documento não encontrado");
            }
            
            // Verificar qual arquivo baixar (assinado ou original)
            $arquivoParaBaixar = $documento['arquivo_assinado'] ?? $documento['caminho_arquivo'];
            $caminhoCompleto = dirname(__DIR__) . '/' . $arquivoParaBaixar;
            
            if (!file_exists($caminhoCompleto)) {
                throw new Exception("Arquivo não encontrado no servidor");
            }
            
            // Registrar download na auditoria
            $this->registrarAuditoria('DOWNLOAD', $id, $documento['associado_id']);
            
            // Determinar nome do arquivo para download
            $nomeDownload = $documento['nome_arquivo'];
            if ($documento['arquivo_assinado'] && $arquivoParaBaixar === $documento['arquivo_assinado']) {
                // Adicionar "_assinado" ao nome do arquivo
                $partes = pathinfo($nomeDownload);
                $nomeDownload = $partes['filename'] . '_assinado.' . $partes['extension'];
            }
            
            // Headers para download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
            header('Content-Length: ' . filesize($caminhoCompleto));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Enviar arquivo
            readfile($caminhoCompleto);
            exit;
            
        } catch (Exception $e) {
            error_log("Erro no download: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar estatísticas
     */
    public function getEstatisticas() {
        try {
            $stats = [];
            
            // Total de documentos
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM Documentos_Associado");
            $result = $stmt->fetch();
            $stats['total_documentos'] = $result['total'];
            
            // Documentos verificados
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM Documentos_Associado WHERE verificado = 1");
            $result = $stmt->fetch();
            $stats['verificados'] = $result['total'];
            
            // Documentos pendentes
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM Documentos_Associado WHERE verificado = 0");
            $result = $stmt->fetch();
            $stats['pendentes'] = $result['total'];
            
            // Por tipo
            $stmt = $this->db->query("
                SELECT tipo_documento, COUNT(*) as total
                FROM Documentos_Associado
                GROUP BY tipo_documento
                ORDER BY total DESC
            ");
            $stats['por_tipo'] = $stmt->fetchAll();
            
            // Uploads hoje
            $stmt = $this->db->query("
                SELECT COUNT(*) as total 
                FROM Documentos_Associado 
                WHERE DATE(data_upload) = CURDATE()
            ");
            $result = $stmt->fetch();
            $stats['uploads_hoje'] = $result['total'];
            
            // Últimos 30 dias
            $stmt = $this->db->query("
                SELECT DATE(data_upload) as data, COUNT(*) as total
                FROM Documentos_Associado
                WHERE data_upload >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(data_upload)
                ORDER BY data
            ");
            $stats['ultimos_30_dias'] = $stmt->fetchAll();
            
            // Associados com documentos
            $stmt = $this->db->query("
                SELECT COUNT(DISTINCT associado_id) as total 
                FROM Documentos_Associado
            ");
            $result = $stmt->fetch();
            $stats['associados_com_docs'] = $result['total'];
            
            // Adicionar estatísticas do fluxo
            $stats['fluxo'] = $this->getEstatisticasFluxo();
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validar arquivo
     */
    private function validarArquivo($arquivo) {
        // Verificar erro no upload
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload: " . $this->getUploadErrorMessage($arquivo['error']));
        }
        
        // Verificar tamanho
        if ($arquivo['size'] > $this->maxFileSize) {
            throw new Exception("Arquivo muito grande. Máximo: " . $this->formatarTamanho($this->maxFileSize));
        }
        
        // Verificar extensão
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (!in_array($extensao, $this->allowedExtensions)) {
            throw new Exception("Tipo de arquivo não permitido. Permitidos: " . implode(', ', $this->allowedExtensions));
        }
        
        // Verificar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $arquivo['tmp_name']);
        finfo_close($finfo);
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => ['image/jpeg', 'image/jpg'],
            'jpeg' => ['image/jpeg', 'image/jpg'],
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $expectedMime = $mimeTypes[$extensao] ?? null;
        if ($expectedMime) {
            if (is_array($expectedMime)) {
                if (!in_array($mimeType, $expectedMime)) {
                    throw new Exception("Tipo de arquivo inválido");
                }
            } else {
                if ($mimeType !== $expectedMime) {
                    throw new Exception("Tipo de arquivo inválido");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Gerar nome único para arquivo
     */
    private function gerarNomeArquivo($associadoId, $tipoDocumento, $extensao) {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        return "{$associadoId}_{$tipoDocumento}_{$timestamp}_{$random}.{$extensao}";
    }
    
    /**
     * Verificar se hash existe
     */
    private function hashExiste($hash, $associadoId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM Documentos_Associado 
            WHERE hash_arquivo = ? AND associado_id = ?
        ");
        $stmt->execute([$hash, $associadoId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Formatar tamanho de arquivo
     */
    private function formatarTamanho($caminho) {
        if (is_numeric($caminho)) {
            $bytes = $caminho;
        } else {
            $caminhoCompleto = dirname(__DIR__) . '/' . $caminho;
            if (!file_exists($caminhoCompleto)) {
                return 'N/A';
            }
            $bytes = filesize($caminhoCompleto);
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Mensagem de erro de upload
     */
    private function getUploadErrorMessage($code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo do formulário',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        
        return $errors[$code] ?? 'Erro desconhecido no upload';
    }
    
    /**
     * Registrar auditoria
     */
    private function registrarAuditoria($acao, $documentoId, $associadoId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO Auditoria (
                    tabela, acao, registro_id, associado_id, 
                    funcionario_id, ip_origem, browser_info, sessao_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                'Documentos_Associado',
                $acao,
                $documentoId,
                $associadoId,
                $_SESSION['funcionario_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id()
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar auditoria: " . $e->getMessage());
        }
    }
    
    /**
     * Obter tipos de documentos disponíveis
     */
    public function getTiposDocumentos() {
        $tipos = [];
        foreach ($this->tiposDocumentos as $codigo => $nome) {
            $tipos[] = [
                'codigo' => $codigo,
                'nome' => $nome
            ];
        }
        return $tipos;
    }
}
?>