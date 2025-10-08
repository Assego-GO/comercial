<?php
session_start();

// Includes necessários
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once './components/header.php';

// Verificação de autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

$usuarioLogado = $auth->getUser();
$page_title = 'Gerar Arquivo de Recorrência - ASSEGO';

// Verificar permissões (financeiro ou presidência)
$isFinanceiro = isset($usuarioLogado['departamento_id']) && $usuarioLogado['departamento_id'] == 2;
$isPresidencia = isset($usuarioLogado['departamento_id']) && $usuarioLogado['departamento_id'] == 1;
$temPermissao = $isFinanceiro || $isPresidencia;

if (!$temPermissao) {
    header('Location: ../pages/dashboard.php');
    exit;
}

// Processar geração do arquivo TXT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_arquivo'])) {
    try {
        $tipo_processamento = $_POST['tipo_processamento'] ?? '';
        $matriculas = $_POST['matriculas'] ?? '';
        $id_operacao = $_POST['id_operacao'] ?? '';
        $rubrica = $_POST['rubrica'] ?? '0009001'; // Rubrica padrão
        
        if (empty($tipo_processamento) || empty($matriculas)) {
            throw new Exception('Tipo de processamento e matrículas são obrigatórios');
        }
        
        // Limpar e processar matrículas
        $matriculas_array = array_map('trim', explode(',', $matriculas));
        $matriculas_array = array_filter($matriculas_array, function($m) {
            return !empty($m) && is_numeric($m);
        });
        
        if (empty($matriculas_array)) {
            throw new Exception('Nenhuma matrícula válida encontrada');
        }
        
        // Buscar associados no banco
        $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
        $placeholders = str_repeat('?,', count($matriculas_array) - 1) . '?';
        
        $sql = "SELECT a.id, a.nome, a.cpf, 
                       COALESCE(sa.valor_aplicado, 86.50) as valor_contribuicao,
                       COALESCE(f.contaCorrente, a.id) as matricula_servidor
                FROM Associados a 
                LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE a.id IN ($placeholders) 
                AND a.situacao = 'Filiado'
                ORDER BY a.id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($matriculas_array);
        $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($associados)) {
            throw new Exception('Nenhum associado encontrado com as matrículas informadas');
        }
        
        // Gerar arquivo TXT
        $filename = "recorrencia_" . date("Ymd_His") . ".txt";
        $data_geracao = date("dmY");
        
        $conteudo = "";
        
        // Cabeçalho único
        $conteudo .= "1{$data_geracao}RECORRENCIA         \n";
        
        // Registros de detalhes
        foreach ($associados as $index => $associado) {
            // Campos do registro
            $inicial = "2";
            $sequencial = str_pad($index + 1, 9, "0", STR_PAD_LEFT);
            $data_operacao = $data_geracao;
            $rubrica_formatada = str_pad($rubrica, 7, "0", STR_PAD_LEFT);
            $matricula = str_pad(substr($associado['matricula_servidor'], 0, 12), 12, " ", STR_PAD_RIGHT);
            
            // CPF limpo
            $cpf = preg_replace('/[^0-9]/', '', $associado['cpf']);
            $cpf = str_pad($cpf, 11, "0", STR_PAD_LEFT);
            
            // Valor da parcela
            if ($tipo_processamento == "0") {
                $valor_parcela = "000000000000000"; // Cancelamentos
            } else {
                $valor_centavos = floatval($associado['valor_contribuicao']) * 100;
                $valor_parcela = str_pad($valor_centavos, 15, "0", STR_PAD_LEFT);
            }
            
            $total_parcelas = "999";
            
            // ID da operação
            if ($tipo_processamento == "1") {
                $id_operacao_formatado = "000000000000000"; // Inclusões
            } else {
                $id_usar = !empty($id_operacao) ? $id_operacao : "55555";
                $id_operacao_formatado = str_pad($id_usar, 15, "0", STR_PAD_LEFT);
            }
            
            $matricula_final = "000000000000";
            
            // Montar linha
            $linha = $inicial . $sequencial . $data_operacao . $rubrica_formatada . $matricula . $cpf . $valor_parcela . $total_parcelas . $id_operacao_formatado . $tipo_processamento . $matricula_final;
            $conteudo .= $linha . "\n";
        }
        
        // Enviar arquivo para download
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($conteudo));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        echo $conteudo;
        exit;
        
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/financeiro.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .recorrencia-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .step-container {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .step-title {
            color: #0d6efd;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .field-container {
            margin-bottom: 1rem;
        }
        
        .preview-area {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1rem;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .alert-custom {
            border-radius: 8px;
            border: none;
            padding: 1rem;
        }
        
        .btn-generate {
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
        }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <div class="main-content">
            <div class="recorrencia-container">
                
                <!-- Cabeçalho -->
                <div class="text-center mb-4">
                    <h2><i class="fas fa-file-download text-primary"></i> Gerar Arquivo de Recorrência</h2>
                    <p class="text-muted">Gere arquivos TXT para inclusões, cancelamentos e alterações de valores</p>
                </div>

                <?php if (isset($erro)): ?>
                <div class="alert alert-danger alert-custom">
                    <i class="fas fa-exclamation-circle"></i> <strong>Erro:</strong> <?php echo htmlspecialchars($erro); ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="formRecorrencia">
                    
                    <!-- Passo 1: Tipo de Processamento -->
                    <div class="step-container">
                        <h4 class="step-title">
                            <i class="fas fa-cog"></i> Passo 1: Tipo de Processamento
                        </h4>
                        
                        <div class="field-container">
                            <label class="form-label fw-bold">Selecione o tipo de operação:</label>
                            <select name="tipo_processamento" id="tipo_processamento" class="form-select" required onchange="toggleCampos()">
                                <option value="">-- Selecione --</option>
                                <option value="1">1 - Inclusões</option>
                                <option value="0">0 - Cancelamentos</option>
                                <option value="2">2 - Alterações de Valores</option>
                            </select>
                        </div>

                        <!-- Campo ID Operação (condicional) -->
                        <div class="field-container" id="container_id_operacao" style="display: none;">
                            <label class="form-label fw-bold">ID da Operação:</label>
                            <input type="text" name="id_operacao" id="id_operacao" class="form-control" placeholder="Ex: 55555">
                            <small class="text-muted">Obrigatório para cancelamentos e alterações</small>
                        </div>

                        <!-- Campo Rubrica -->
                        <div class="field-container">
                            <label class="form-label fw-bold">Rubrica:</label>
                            <input type="text" name="rubrica" id="rubrica" class="form-control" value="0009001" maxlength="7">
                            <small class="text-muted">Código da rubrica (padrão: 0009001)</small>
                        </div>
                    </div>

                    <!-- Passo 2: Matrículas -->
                    <div class="step-container">
                        <h4 class="step-title">
                            <i class="fas fa-users"></i> Passo 2: Matrículas dos Associados
                        </h4>
                        
                        <div class="field-container">
                            <label class="form-label fw-bold">Digite as matrículas:</label>
                            <textarea name="matriculas" id="matriculas" class="form-control" rows="3" 
                                      placeholder="Digite as matrículas separadas por vírgula. Ex: 445, 788, 1023" required></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Separe múltiplas matrículas com vírgula. Ex: 445,788,1023
                            </small>
                        </div>
                    </div>

                    <!-- Passo 3: Gerar Arquivo -->
                    <div class="step-container">
                        <h4 class="step-title">
                            <i class="fas fa-download"></i> Passo 3: Gerar Arquivo
                        </h4>
                        
                        <div class="d-grid">
                            <button type="submit" name="gerar_arquivo" class="btn btn-primary btn-generate">
                                <i class="fas fa-file-download"></i> Gerar e Baixar Arquivo TXT
                            </button>
                        </div>
                    </div>

                </form>

                <!-- Informações sobre o formato -->
                <div class="alert alert-info alert-custom mt-4">
                    <h6><i class="fas fa-info-circle"></i> Informações sobre o arquivo:</h6>
                    <ul class="mb-0">
                        <li><strong>Formato:</strong> Arquivo de recorrência para Governo do Estado de Goiás</li>
                        <li><strong>Inclusões:</strong> Novos associados (ID operação = 15 zeros)</li>
                        <li><strong>Cancelamentos:</strong> Valor = 15 zeros, ID operação obrigatório</li>
                        <li><strong>Alterações:</strong> Mudança de valores, ID operação obrigatório</li>
                    </ul>
                </div>

                <!-- Botão Voltar -->
                <div class="text-center mt-4">
                    <a href="financeiro.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar ao Financeiro
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleCampos() {
            const tipo = document.getElementById('tipo_processamento').value;
            const container = document.getElementById('container_id_operacao');
            
            if (tipo === '0' || tipo === '2') {
                container.style.display = 'block';
                document.getElementById('id_operacao').required = true;
            } else {
                container.style.display = 'none';
                document.getElementById('id_operacao').required = false;
                document.getElementById('id_operacao').value = '';
            }
        }

        // Validação do formulário
        document.getElementById('formRecorrencia').addEventListener('submit', function(e) {
            const tipo = document.getElementById('tipo_processamento').value;
            const matriculas = document.getElementById('matriculas').value.trim();
            const idOperacao = document.getElementById('id_operacao').value.trim();
            
            if (!tipo) {
                alert('Selecione o tipo de processamento');
                e.preventDefault();
                return;
            }
            
            if (!matriculas) {
                alert('Digite pelo menos uma matrícula');
                e.preventDefault();
                return;
            }
            
            if ((tipo === '0' || tipo === '2') && !idOperacao) {
                alert('ID da operação é obrigatório para cancelamentos e alterações');
                e.preventDefault();
                return;
            }
            
            // Mostrar loading
            const btn = document.querySelector('.btn-generate');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando arquivo...';
            btn.disabled = true;
        });

        console.log('✓ Sistema de Geração de Recorrência carregado!');
    </script>

</body>
</html>