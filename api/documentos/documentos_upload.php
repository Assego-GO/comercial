<?php
/**
 * API para upload de documentos de associados
 * api/documentos/documentos_upload.php
 * 
 * VERSÃO CORRIGIDA: Atualiza pre_cadastro para 0 quando upload é feito pela presidência
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Includes necessários
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

// Verifica se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

try {
    // Verifica autenticação
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado']);
        exit;
    }

    $usuarioLogado = $auth->getUser();
    $funcionarioId = $usuarioLogado['id'];

    // ===== NOVA VERIFICAÇÃO: DETECTAR SE É PRESIDÊNCIA =====
    $isPresidencia = false;
    $departamentoId = $usuarioLogado['departamento_id'] ?? null;
    
    // Verifica se é da presidência (departamento 1) OU é diretor
    if ($departamentoId == 1 || $auth->isDiretor()) {
        $isPresidencia = true;
        error_log("✅ Upload pela PRESIDÊNCIA detectado - Usuário: " . $usuarioLogado['nome']);
    } else {
        error_log("📄 Upload por departamento comum - Departamento: " . $departamentoId);
    }

    // Valida dados obrigatórios
    if (!isset($_POST['associado_id']) || !isset($_POST['tipo_documento'])) {
        throw new Exception('Dados obrigatórios não informados');
    }

    $associadoId = filter_var($_POST['associado_id'], FILTER_VALIDATE_INT);
    $tipoDocumento = trim($_POST['tipo_documento']);
    $outroTipo = isset($_POST['outro_tipo']) ? trim($_POST['outro_tipo']) : '';
    $observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : '';

    if (!$associadoId) {
        throw new Exception('ID do associado inválido');
    }

    if (empty($tipoDocumento)) {
        throw new Exception('Tipo de documento é obrigatório');
    }

    if ($tipoDocumento === 'OUTROS' && empty($outroTipo)) {
        throw new Exception('Especificação do tipo é obrigatória quando selecionado "Outros"');
    }

    // Valida arquivo
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande (limite do servidor)',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande (limite do formulário)',
            UPLOAD_ERR_PARTIAL => 'Upload incompleto',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
            UPLOAD_ERR_CANT_WRITE => 'Erro ao gravar arquivo',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
        ];
        
        $errorCode = $_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMessage = $errorMessages[$errorCode] ?? 'Erro desconhecido no upload';
        throw new Exception($errorMessage);
    }

    $arquivo = $_FILES['arquivo'];
    $nomeOriginal = $arquivo['name'];
    $tamanho = $arquivo['size'];
    $tipoMime = $arquivo['type'];
    $arquivoTmp = $arquivo['tmp_name'];

    // Valida tamanho (5MB máximo)
    $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
    if ($tamanho > $tamanhoMaximo) {
        throw new Exception('Arquivo muito grande. Tamanho máximo: 5MB');
    }

    // Valida extensão
    $extensoesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    
    if (!in_array($extensao, $extensoesPermitidas)) {
        throw new Exception('Tipo de arquivo não permitido. Use: PDF, JPG, JPEG ou PNG');
    }

    // Valida MIME type
    $mimePermitidos = [
        'application/pdf',
        'image/jpeg',
        'image/jpg', 
        'image/png'
    ];
    
    if (!in_array($tipoMime, $mimePermitidos)) {
        throw new Exception('Tipo MIME não permitido');
    }

    // Conecta ao banco
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Verifica se o associado existe e pega status atual
    $stmt = $db->prepare("SELECT id, nome, pre_cadastro FROM Associados WHERE id = ?");
    $stmt->execute([$associadoId]);
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$associado) {
        throw new Exception('Associado não encontrado');
    }

    // Log do status atual do associado
    error_log("📋 Associado encontrado: " . $associado['nome'] . " | Pre-cadastro atual: " . $associado['pre_cadastro']);

    // Cria diretório de upload se não existir
    $baseDir = __DIR__ . '/../../uploads/anexos';
    $associadoDir = $baseDir . '/' . $associadoId;
    
    // Log para debug
    error_log("📂 Diretório base: " . realpath($baseDir));
    error_log("📂 Diretório do associado: " . $associadoDir);
    
    if (!is_dir($baseDir)) {
        if (!@mkdir($baseDir, 0775, true)) {
            throw new Exception('Erro ao criar diretório base de uploads. Verifique as permissões.');
        }
    }
    
    if (!is_dir($associadoDir)) {
        if (!@mkdir($associadoDir, 0775, true)) {
            error_log("❌ Erro ao criar diretório: " . $associadoDir . " - Permissão negada?");
            throw new Exception('Erro ao criar diretório do associado. Verifique as permissões do servidor.');
        }
    }
    
    // Verifica se podemos escrever no diretório
    if (!is_writable($associadoDir)) {
        error_log("❌ Diretório não é gravável: " . $associadoDir);
        throw new Exception('Diretório de uploads não tem permissão de escrita.');
    }

    // Gera nome único para o arquivo
    $timestamp = date('Y-m-d_H-i-s');
    $nomeArquivo = $associadoId . '_' . $timestamp . '_' . uniqid() . '.' . $extensao;
    $caminhoCompleto = $associadoDir . '/' . $nomeArquivo;

    // Move o arquivo
    if (!move_uploaded_file($arquivoTmp, $caminhoCompleto)) {
        throw new Exception('Erro ao salvar arquivo no servidor');
    }

    // Define descrição do tipo
    $tiposDescricao = [
        'FICHA_FILIACAO' => 'Ficha de Filiação',
        'FICHA_DESFILIACAO' => 'Ficha de Desfiliação',
        'RG' => 'RG (Cópia)',
        'CPF' => 'CPF (Cópia)',
        'COMPROVANTE_RESIDENCIA' => 'Comprovante de Residência',
        'FOTO_3X4' => 'Foto 3x4',
        'CERTIDAO_NASCIMENTO' => 'Certidão de Nascimento',
        'CERTIDAO_CASAMENTO' => 'Certidão de Casamento',
        'DECLARACAO_DEPENDENTES' => 'Declaração de Dependentes',
        'OUTROS' => $outroTipo ?: 'Outros'
    ];

    $tipoDescricao = $tiposDescricao[$tipoDocumento] ?? $tipoDocumento;

    // Verifica se existe tabela de documentos em fluxo
    $stmt = $db->prepare("SHOW TABLES LIKE 'DocumentosFluxo'");
    $stmt->execute();
    $tabelaExiste = $stmt->fetch();

    if (!$tabelaExiste) {
        // Cria a tabela se não existir
        $createTableSQL = "
            CREATE TABLE DocumentosFluxo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                associado_id INT NOT NULL,
                tipo_documento VARCHAR(50) NOT NULL,
                tipo_descricao VARCHAR(100) NOT NULL,
                nome_arquivo VARCHAR(255) NOT NULL,
                caminho_arquivo VARCHAR(500) NOT NULL,
                tamanho_arquivo INT NOT NULL,
                tipo_mime VARCHAR(100) NOT NULL,
                status_fluxo ENUM('DIGITALIZADO', 'AGUARDANDO_ASSINATURA', 'ASSINADO', 'FINALIZADO') DEFAULT 'DIGITALIZADO',
                data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                funcionario_upload INT NOT NULL,
                departamento_atual INT DEFAULT 1,
                observacao TEXT,
                data_ultima_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                funcionario_ultima_acao INT NOT NULL,
                INDEX idx_associado (associado_id),
                INDEX idx_status (status_fluxo),
                INDEX idx_data_upload (data_upload),
                FOREIGN KEY (associado_id) REFERENCES Associados(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
        ";
        
        $db->exec($createTableSQL);
    }

    // ===== NOVA LÓGICA: TRANSAÇÃO PARA GARANTIR CONSISTÊNCIA =====
    $db->beginTransaction();

    try {
        // Insere registro do documento no banco
        $stmt = $db->prepare("
            INSERT INTO DocumentosFluxo (
                associado_id, 
                tipo_documento, 
                tipo_descricao,
                nome_arquivo, 
                caminho_arquivo, 
                tamanho_arquivo, 
                tipo_mime,
                funcionario_upload,
                funcionario_ultima_acao,
                observacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $caminhoRelativo = 'uploads/anexos/' . $associadoId . '/' . $nomeArquivo;

        $result = $stmt->execute([
            $associadoId,
            $tipoDocumento,
            $tipoDescricao,
            $nomeArquivo,
            $caminhoRelativo,
            $tamanho,
            $tipoMime,
            $funcionarioId,
            $funcionarioId,
            $observacao
        ]);

        if (!$result) {
            throw new Exception('Erro ao registrar documento no banco de dados');
        }

        $documentoId = $db->lastInsertId();

        // ===== NOVA FUNCIONALIDADE: ATUALIZAR PRE_CADASTRO QUANDO É PRESIDÊNCIA =====
        $statusAssociadoAlterado = false;
        $statusAnterior = $associado['pre_cadastro'];
        
        if ($isPresidencia && $associado['pre_cadastro'] == 1) {
            // Atualiza o associado para cadastro definitivo
            $stmt = $db->prepare("
                UPDATE Associados 
                SET pre_cadastro = 0, 
                    data_aprovacao = CURRENT_TIMESTAMP,
                    aprovado_por = ?,
                    observacao_aprovacao = ?
                WHERE id = ?
            ");
            
            $observacaoAprovacao = "Cadastro aprovado pela presidência via upload de documento: " . $tipoDescricao;
            
            $result = $stmt->execute([
                $funcionarioId,
                $observacaoAprovacao,
                $associadoId
            ]);
            
            if ($result) {
                $statusAssociadoAlterado = true;
                error_log("✅ PRESIDÊNCIA: Associado {$associadoId} convertido de pré-cadastro para cadastro definitivo");
            } else {
                error_log("❌ Erro ao atualizar status do associado {$associadoId}");
            }
        }

        // Registra no histórico (se a tabela existir)
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE 'DocumentosFluxoHistorico'");
            $stmt->execute();
            $historicoExiste = $stmt->fetch();

            if (!$historicoExiste) {
                // Cria tabela de histórico
                $createHistoricoSQL = "
                    CREATE TABLE DocumentosFluxoHistorico (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        documento_id INT NOT NULL,
                        status_anterior VARCHAR(50),
                        status_novo VARCHAR(50) NOT NULL,
                        departamento_origem INT,
                        departamento_destino INT,
                        funcionario_acao INT NOT NULL,
                        data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        observacao TEXT,
                        INDEX idx_documento (documento_id),
                        INDEX idx_data_acao (data_acao),
                        FOREIGN KEY (documento_id) REFERENCES DocumentosFluxo(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
                ";
                
                $db->exec($createHistoricoSQL);
            }

            // Observação expandida para histórico
            $observacaoHistorico = 'Documento digitalizado e anexado ao sistema - ' . $tipoDescricao;
            
            if ($isPresidencia) {
                $observacaoHistorico .= ' | UPLOAD PELA PRESIDÊNCIA';
                if ($statusAssociadoAlterado) {
                    $observacaoHistorico .= ' | Associado aprovado automaticamente (pré-cadastro → cadastro definitivo)';
                }
            }

            $stmt = $db->prepare("
                INSERT INTO DocumentosFluxoHistorico (
                    documento_id, 
                    status_anterior, 
                    status_novo, 
                    departamento_destino,
                    funcionario_acao, 
                    observacao
                ) VALUES (?, NULL, 'DIGITALIZADO', 1, ?, ?)
            ");

            $stmt->execute([
                $documentoId,
                $funcionarioId,
                $observacaoHistorico
            ]);

        } catch (Exception $e) {
            // Log do erro mas não falha o upload
            error_log("Erro ao registrar histórico: " . $e->getMessage());
        }

        // Atualiza contador de documentos do associado (se campo existir)
        try {
            $stmt = $db->prepare("
                UPDATE Associados 
                SET total_documentos = (
                    SELECT COUNT(*) 
                    FROM DocumentosFluxo 
                    WHERE associado_id = ?
                ) 
                WHERE id = ?
            ");
            $stmt->execute([$associadoId, $associadoId]);
        } catch (Exception $e) {
            // Se o campo não existir, ignora
            error_log("Campo total_documentos não existe: " . $e->getMessage());
        }

        // ===== COMMIT DA TRANSAÇÃO =====
        $db->commit();

        // ===== RESPOSTA DE SUCESSO EXPANDIDA =====
        $responseData = [
            'documento_id' => $documentoId,
            'associado_id' => $associadoId,
            'associado_nome' => $associado['nome'],
            'tipo_documento' => $tipoDocumento,
            'tipo_descricao' => $tipoDescricao,
            'nome_arquivo' => $nomeArquivo,
            'tamanho_mb' => round($tamanho / (1024 * 1024), 2),
            'data_upload' => date('Y-m-d H:i:s'),
            'status' => 'DIGITALIZADO',
            'upload_pela_presidencia' => $isPresidencia
        ];

        // Adicionar informações sobre mudança de status se ocorreu
        if ($statusAssociadoAlterado) {
            $responseData['associado_status_alterado'] = true;
            $responseData['status_anterior'] = 'Pré-cadastro';
            $responseData['status_novo'] = 'Cadastro definitivo';
            $responseData['data_aprovacao'] = date('Y-m-d H:i:s');
            $responseData['aprovado_por'] = $funcionarioId;
        }

        $mensagem = 'Documento anexado com sucesso';
        if ($statusAssociadoAlterado) {
            $mensagem .= ' e associado aprovado automaticamente pela presidência';
        }

        echo json_encode([
            'status' => 'success',
            'message' => $mensagem,
            'data' => $responseData
        ]);

    } catch (Exception $e) {
        // Rollback em caso de erro
        $db->rollBack();
        
        // Remove arquivo se foi criado
        if (file_exists($caminhoCompleto)) {
            unlink($caminhoCompleto);
        }
        
        throw $e;
    }

} catch (Exception $e) {
    error_log("Erro no upload de documento: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Erro de banco no upload: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor'
    ]);
}
?>