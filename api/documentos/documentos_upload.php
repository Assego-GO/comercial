<?php
/**
 * API para upload de documentos de associados
 * api/documentos/documentos_upload.php
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

    // Verifica se o associado existe
    $stmt = $db->prepare("SELECT id, nome FROM Associados WHERE id = ?");
    $stmt->execute([$associadoId]);
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$associado) {
        throw new Exception('Associado não encontrado');
    }

    // Cria diretório de upload se não existir
    $baseDir = '../../uploads/documentos';
    $associadoDir = $baseDir . '/' . $associadoId;
    
    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0755, true)) {
            throw new Exception('Erro ao criar diretório base de uploads');
        }
    }
    
    if (!is_dir($associadoDir)) {
        if (!mkdir($associadoDir, 0755, true)) {
            throw new Exception('Erro ao criar diretório do associado');
        }
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

    $caminhoRelativo = 'uploads/documentos/' . $associadoId . '/' . $nomeArquivo;

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
        // Se falhou ao inserir no banco, remove o arquivo
        unlink($caminhoCompleto);
        throw new Exception('Erro ao registrar documento no banco de dados');
    }

    $documentoId = $db->lastInsertId();

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
            'Documento digitalizado e anexado ao sistema - ' . $tipoDescricao
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

    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Documento anexado com sucesso',
        'data' => [
            'documento_id' => $documentoId,
            'associado_id' => $associadoId,
            'associado_nome' => $associado['nome'],
            'tipo_documento' => $tipoDocumento,
            'tipo_descricao' => $tipoDescricao,
            'nome_arquivo' => $nomeArquivo,
            'tamanho_mb' => round($tamanho / (1024 * 1024), 2),
            'data_upload' => date('Y-m-d H:i:s'),
            'status' => 'DIGITALIZADO'
        ]
    ]);

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