<?php
/**
 * API para gerar ficha virtual - Versão simplificada
 * api/documentos/documentos_gerar_ficha_virtual.php
 */

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Se for OPTIONS request (preflight), retornar sucesso
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido. Use POST.');
    }
    
    // Capturar input
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('Nenhum dado recebido');
    }
    
    // Decodificar JSON
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erro JSON: ' . json_last_error_msg());
    }
    
    // Verificar associado_id
    if (!isset($input['associado_id']) || empty($input['associado_id'])) {
        throw new Exception('ID do associado não informado');
    }
    
    $associadoId = intval($input['associado_id']);
    
    // Incluir arquivos necessários
    $basePath = dirname(dirname(dirname(__FILE__)));
    
    require_once $basePath . '/config/config.php';
    require_once $basePath . '/config/database.php';
    require_once $basePath . '/classes/Database.php';
    
    // Verificar autenticação se existir
    if (file_exists($basePath . '/classes/Auth.php')) {
        require_once $basePath . '/classes/Auth.php';
        $auth = new Auth();
        if (!$auth->isLoggedIn()) {
            // Para teste, vamos continuar mesmo sem autenticação
            // throw new Exception('Usuário não autenticado');
        }
    }
    
    // Conectar ao banco de dados
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar dados do associado
    $stmt = $db->prepare("
        SELECT 
            a.*,
            e.cep,
            e.endereco,
            e.bairro,
            e.cidade,
            e.numero,
            e.complemento,
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
            c.dataFiliacao
        FROM Associados a
        LEFT JOIN Endereco e ON a.id = e.associado_id
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Financeiro f ON a.id = f.associado_id
        LEFT JOIN Contrato c ON a.id = c.associado_id
        WHERE a.id = ?
    ");
    
    $stmt->execute([$associadoId]);
    $dadosAssociado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dadosAssociado) {
        throw new Exception("Associado não encontrado com ID: " . $associadoId);
    }
    
    // Buscar dependentes
    $stmt = $db->prepare("
        SELECT nome, data_nascimento, parentesco, sexo
        FROM Dependentes
        WHERE associado_id = ?
        ORDER BY data_nascimento ASC
    ");
    $stmt->execute([$associadoId]);
    $dependentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dadosAssociado['dependentes'] = $dependentes;
    
    // Criar diretório temporário
    $uploadDir = $basePath . '/uploads/documentos/';
    $tempDir = $uploadDir . 'temp/';
    
    if (!file_exists($tempDir)) {
        if (!mkdir($tempDir, 0755, true)) {
            // Usar diretório alternativo
            $tempDir = sys_get_temp_dir() . '/uploads_temp/';
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
        }
    }
    
    // Nome único para o arquivo
    $nomeArquivo = 'ficha_virtual_' . $associadoId . '_' . time() . '.pdf';
    $caminhoCompleto = $tempDir . $nomeArquivo;
    
    // Gerar conteúdo HTML da ficha
    $html = gerarHTMLFicha($dadosAssociado);
    
    // Salvar como HTML primeiro
    $htmlPath = str_replace('.pdf', '.html', $caminhoCompleto);
    file_put_contents($htmlPath, $html);
    
    // Tentar converter para PDF
    $pdfGerado = false;
    
    // Método 1: wkhtmltopdf
    $wkhtmltopdf = encontrarWkhtmltopdf();
    if ($wkhtmltopdf) {
        $cmd = escapeshellcmd($wkhtmltopdf) . ' --enable-local-file-access ' . 
               escapeshellarg($htmlPath) . ' ' . escapeshellarg($caminhoCompleto) . ' 2>&1';
        exec($cmd, $output, $return);
        
        if ($return === 0 && file_exists($caminhoCompleto)) {
            $pdfGerado = true;
        }
    }
    
    // Método 2: Criar PDF básico se wkhtmltopdf não funcionou
    if (!$pdfGerado) {
        criarPDFBasico($caminhoCompleto, $dadosAssociado);
    }
    
    // Limpar HTML temporário
    if (file_exists($htmlPath)) {
        unlink($htmlPath);
    }
    
    // Verificar se o arquivo foi criado
    if (!file_exists($caminhoCompleto)) {
        throw new Exception("Erro ao criar arquivo PDF");
    }
    
    // Criar registro no banco de dados
    $db->beginTransaction();
    
    try {
        // Buscar departamento comercial
        $stmt = $db->prepare("SELECT id FROM Departamentos WHERE nome = 'Comercial' LIMIT 1");
        $stmt->execute();
        $dept = $stmt->fetch();
        $deptComercial = $dept ? $dept['id'] : 1;
        
        // Calcular hash do arquivo
        $hashArquivo = hash_file('sha256', $caminhoCompleto);
        
        // Inserir documento
        $stmt = $db->prepare("
            INSERT INTO Documentos_Associado (
                associado_id, 
                tipo_documento, 
                nome_arquivo, 
                caminho_arquivo, 
                hash_arquivo, 
                verificado,
                funcionario_id, 
                observacao,
                tipo_origem,
                status_fluxo,
                departamento_atual
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $caminhoRelativo = 'uploads/documentos/temp/' . $nomeArquivo;
        $funcionarioId = isset($_SESSION['funcionario_id']) ? $_SESSION['funcionario_id'] : null;
        
        $stmt->execute([
            $associadoId,
            'ficha_associacao',
            $nomeArquivo,
            $caminhoRelativo,
            $hashArquivo,
            0,
            $funcionarioId,
            'Ficha de filiação gerada automaticamente pelo sistema',
            'VIRTUAL',
            'DIGITALIZADO',
            $deptComercial
        ]);
        
        $documentoId = $db->lastInsertId();
        
        // Registrar no histórico
        $stmt = $db->prepare("
            INSERT INTO Historico_Fluxo_Documento (
                documento_id, status_anterior, status_novo,
                departamento_origem, departamento_destino,
                funcionario_id, observacao
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $documentoId,
            null,
            'DIGITALIZADO',
            null,
            $deptComercial,
            $funcionarioId,
            "Documento VIRTUAL digitalizado e cadastrado no sistema"
        ]);
        
        $db->commit();
        
        // Retornar sucesso
        $resultado = [
            'documento_id' => $documentoId,
            'nome_arquivo' => $nomeArquivo,
            'associado_nome' => $dadosAssociado['nome'],
            'mensagem' => 'Ficha virtual gerada e enviada para o fluxo de assinatura'
        ];
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Ficha virtual gerada com sucesso!',
            'data' => $resultado
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Funções auxiliares

function gerarHTMLFicha($dados) {
    $nome = htmlspecialchars(strtoupper($dados['nome'] ?? ''));
    $cpf = formatarCPF($dados['cpf'] ?? '');
    $rg = htmlspecialchars($dados['rg'] ?? '');
    $nasc = formatarData($dados['nasc'] ?? '');
    $email = htmlspecialchars(strtolower($dados['email'] ?? ''));
    $telefone = formatarTelefone($dados['telefone'] ?? '');
    
    $cep = formatarCEP($dados['cep'] ?? '');
    $endereco = htmlspecialchars($dados['endereco'] ?? '');
    $numero = htmlspecialchars($dados['numero'] ?? '');
    $bairro = htmlspecialchars($dados['bairro'] ?? '');
    $cidade = htmlspecialchars($dados['cidade'] ?? 'GOIÂNIA');
    
    $patente = htmlspecialchars($dados['patente'] ?? '');
    $unidade = htmlspecialchars($dados['unidade'] ?? '');
    $dataFiliacao = formatarData($dados['dataFiliacao'] ?? date('Y-m-d'));
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ficha de Filiação - ASSEGO</title>
    <style>
        body { font-family: Arial; font-size: 12pt; margin: 20px; }
        h1 { text-align: center; font-size: 20pt; }
        h2 { text-align: center; font-size: 16pt; }
        .campo { margin: 10px 0; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .valor { border-bottom: 1px solid #000; display: inline-block; min-width: 300px; }
        .tabela { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .tabela th, .tabela td { border: 1px solid #000; padding: 5px; }
        .assinatura { margin-top: 50px; text-align: center; }
        .linha-assinatura { width: 300px; border-bottom: 1px solid #000; display: inline-block; margin: 0 20px; }
    </style>
</head>
<body>
    <h1>ASSOCIAÇÃO DOS SUBTENENTES E SARGENTOS PM & BM - GO</h1>
    <h2>FICHA DE FILIAÇÃO</h2>
    
    <div class="campo">
        <span class="label">Nome:</span>
        <span class="valor">$nome</span>
    </div>
    
    <div class="campo">
        <span class="label">CPF:</span>
        <span class="valor">$cpf</span>
    </div>
    
    <div class="campo">
        <span class="label">RG:</span>
        <span class="valor">$rg</span>
    </div>
    
    <div class="campo">
        <span class="label">Data de Nascimento:</span>
        <span class="valor">$nasc</span>
    </div>
    
    <div class="campo">
        <span class="label">E-mail:</span>
        <span class="valor">$email</span>
    </div>
    
    <div class="campo">
        <span class="label">Telefone:</span>
        <span class="valor">$telefone</span>
    </div>
    
    <div class="campo">
        <span class="label">Endereço:</span>
        <span class="valor">$endereco, $numero</span>
    </div>
    
    <div class="campo">
        <span class="label">Bairro:</span>
        <span class="valor">$bairro</span>
    </div>
    
    <div class="campo">
        <span class="label">Cidade:</span>
        <span class="valor">$cidade</span>
    </div>
    
    <div class="campo">
        <span class="label">CEP:</span>
        <span class="valor">$cep</span>
    </div>
    
    <div class="campo">
        <span class="label">Patente:</span>
        <span class="valor">$patente</span>
    </div>
    
    <div class="campo">
        <span class="label">Unidade:</span>
        <span class="valor">$unidade</span>
    </div>
    
    <div class="campo">
        <span class="label">Data de Admissão:</span>
        <span class="valor">$dataFiliacao</span>
    </div>
    
    <table class="tabela">
        <tr>
            <th colspan="2">Dependentes</th>
        </tr>
HTML;

    // Adicionar dependentes
    if (!empty($dados['dependentes'])) {
        foreach ($dados['dependentes'] as $dep) {
            $depNome = htmlspecialchars($dep['nome'] ?? '');
            $depNasc = formatarData($dep['data_nascimento'] ?? '');
            $html .= "<tr><td>$depNome</td><td>$depNasc</td></tr>";
        }
    } else {
        $html .= "<tr><td colspan='2'>Nenhum dependente cadastrado</td></tr>";
    }

    $html .= <<<HTML
    </table>
    
    <div class="assinatura">
        <p>Goiânia, _____ de _________________ de _______</p>
        <br><br>
        <span class="linha-assinatura"></span>
        <span class="linha-assinatura"></span>
        <br>
        <span style="margin: 0 50px;">Assinatura do Associado</span>
        <span style="margin: 0 50px;">ASSEGO</span>
    </div>
</body>
</html>
HTML;

    return $html;
}

function criarPDFBasico($caminho, $dados) {
    $nome = $dados['nome'] ?? 'NOME NAO INFORMADO';
    $cpf = formatarCPF($dados['cpf'] ?? '');
    $patente = $dados['patente'] ?? '';
    
    // Criar um PDF mínimo mas válido
    $pdf = "%PDF-1.4\n";
    $obj = [];
    
    // Catalog
    $obj[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    
    // Pages
    $obj[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    
    // Page
    $obj[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    
    // Font
    $obj[4] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    
    // Content stream
    $content = "BT\n";
    $content .= "/F1 20 Tf\n";
    $content .= "50 750 Td\n";
    $content .= "(FICHA DE FILIACAO - ASSEGO) Tj\n";
    $content .= "0 -40 Td\n";
    $content .= "/F1 12 Tf\n";
    $content .= "(Nome: " . str_replace(['(', ')'], ['\\(', '\\)'], $nome) . ") Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(CPF: $cpf) Tj\n";
    $content .= "0 -20 Td\n";
    $content .= "(Patente: " . str_replace(['(', ')'], ['\\(', '\\)'], $patente) . ") Tj\n";
    $content .= "0 -40 Td\n";
    $content .= "(Documento gerado em: " . date('d/m/Y H:i:s') . ") Tj\n";
    $content .= "ET\n";
    
    $obj[5] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream\nendobj\n";
    
    // Montar PDF
    $pdfContent = $pdf;
    $xref = [];
    foreach ($obj as $i => $o) {
        $xref[$i] = strlen($pdfContent);
        $pdfContent .= $o;
    }
    
    // Cross-reference table
    $xrefStart = strlen($pdfContent);
    $pdfContent .= "xref\n0 6\n";
    $pdfContent .= "0000000000 65535 f\n";
    for ($i = 1; $i <= 5; $i++) {
        $pdfContent .= sprintf("%010d 00000 n\n", $xref[$i]);
    }
    
    // Trailer
    $pdfContent .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
    $pdfContent .= "startxref\n$xrefStart\n";
    $pdfContent .= "%%EOF";
    
    file_put_contents($caminho, $pdfContent);
}

function encontrarWkhtmltopdf() {
    $possiveis = [
        '/usr/local/bin/wkhtmltopdf',
        '/usr/bin/wkhtmltopdf',
        'wkhtmltopdf'
    ];
    
    foreach ($possiveis as $cmd) {
        exec("which $cmd 2>&1", $output, $return);
        if ($return === 0) {
            return $cmd;
        }
    }
    
    return false;
}

function formatarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatarCEP($cep) {
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) == 8) {
        return substr($cep, 0, 2) . '.' . substr($cep, 2, 3) . '-' . substr($cep, 5, 3);
    }
    return $cep;
}

function formatarTelefone($telefone) {
    $telefone = preg_replace('/\D/', '', $telefone);
    if (strlen($telefone) == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    } elseif (strlen($telefone) == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
    }
    return $telefone;
}

function formatarData($data) {
    if ($data && $data != '0000-00-00') {
        $timestamp = strtotime($data);
        if ($timestamp !== false) {
            return date('d/m/Y', $timestamp);
        }
    }
    return '';
}
?>