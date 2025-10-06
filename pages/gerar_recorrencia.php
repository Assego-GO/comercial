<?php

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

// ✅ NOVA FUNCIONALIDADE: Preview dos associados via AJAX
if (isset($_GET['preview_associados'])) {
    $matriculas = $_GET['matriculas'] ?? '';
    
    header('Content-Type: application/json');
    
    if ($matriculas) {
        $matriculas_array = array_map('trim', explode(',', $matriculas));
        $matriculas_array = array_filter($matriculas_array, function($m) {
            return !empty($m) && is_numeric($m);
        });
        
        if ($matriculas_array) {
            try {
                $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
                $placeholders = str_repeat('?,', count($matriculas_array) - 1) . '?';
                
                $sql = "SELECT a.id, a.nome, a.cpf,
                               COALESCE(SUM(sa.valor_aplicado), 86.50) as valor_total,
                               f.id_neoconsig,
                               f.vinculoServidor
                        FROM Associados a 
                        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1
                        LEFT JOIN Financeiro f ON a.id = f.associado_id
                        WHERE a.id IN ($placeholders)
                        AND a.situacao = 'Filiado'
                        GROUP BY a.id, a.nome, a.cpf, f.id_neoconsig, f.vinculoServidor
                        ORDER BY a.id";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($matriculas_array);
                $encontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $ids_encontrados = array_column($encontrados, 'id');
                $ids_nao_encontrados = array_diff($matriculas_array, $ids_encontrados);
                
                echo json_encode([
                    'success' => true,
                    'encontrados' => $encontrados,
                    'nao_encontrados' => $ids_nao_encontrados,
                    'total_encontrados' => count($encontrados),
                    'valor_total' => array_sum(array_column($encontrados, 'valor_total'))
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Nenhuma matrícula válida']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Matrículas não informadas']);
    }
    exit;
}

// ✅ PROCESSAR GERAÇÃO DO ARQUIVO (usando GET para evitar reload)
if (isset($_GET['gerar']) && $_GET['gerar'] == '1') {
    try {
        $tipo_processamento = $_GET['tipo'] ?? '';
        $matriculas = $_GET['matriculas'] ?? '';
        $rubrica = $_GET['rubrica'] ?? '0900892';
        
        if ($tipo_processamento === '' || $tipo_processamento === null || empty($matriculas)) {
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
        
        $sql = "SELECT a.id, 
                       a.nome, 
                       a.cpf,
                       COALESCE(SUM(sa.valor_aplicado), 86.50) as valor_total_servicos,
                       f.id_neoconsig,
                       f.vinculoServidor
                FROM Associados a 
                LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1
                LEFT JOIN Financeiro f ON a.id = f.associado_id
                WHERE a.id IN ($placeholders) 
                AND a.situacao = 'Filiado'
                GROUP BY a.id, a.nome, a.cpf, f.id_neoconsig, f.vinculoServidor
                ORDER BY a.id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($matriculas_array);
        $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($associados)) {
            throw new Exception('Nenhum associado encontrado com as matrículas informadas: ' . implode(', ', $matriculas_array));
        }
        
        // Verificar se todos os associados têm vinculoServidor
        $associadosSemVinculo = array_filter($associados, function($assoc) {
            return empty($assoc['vinculoServidor']);
        });
        
        if (!empty($associadosSemVinculo)) {
            $nomesSemVinculo = array_map(function($assoc) {
                return $assoc['nome'] . ' (ID: ' . $assoc['id'] . ')';
            }, $associadosSemVinculo);
            
            throw new Exception('Os seguintes associados não possuem vínculo servidor cadastrado na tabela Financeiro: ' . implode(', ', $nomesSemVinculo) . '. É necessário cadastrar o vínculo servidor antes de gerar o arquivo.');
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
            $matricula = str_pad(substr($associado['vinculoServidor'] ?? '', 0, 12), 12, " ", STR_PAD_RIGHT);
            
            // CPF limpo
            $cpf = preg_replace('/[^0-9]/', '', $associado['cpf']);
            $cpf = str_pad($cpf, 11, "0", STR_PAD_LEFT);
            
            // Valor da parcela
            if ($tipo_processamento == "0") {
                $valor_parcela = "000000000000000"; // Cancelamentos
            } else {
                $valor_centavos = floatval($associado['valor_total_servicos']) * 100;
                $valor_parcela = str_pad($valor_centavos, 15, "0", STR_PAD_LEFT);
            }
            
            $total_parcelas = "999";
            
            // ID da operação
            if ($tipo_processamento == "1") {
                $id_operacao_formatado = "000000000000000"; // Inclusões
            } else {
                // Para cancelamentos e alterações, usar id_neoconsig do banco
                $id_usar = !empty($associado['id_neoconsig']) ? $associado['id_neoconsig'] : "55555";
                $id_operacao_formatado = str_pad($id_usar, 15, "0", STR_PAD_LEFT);
            }
            
            $matricula_final = str_pad(substr($associado['vinculoServidor'] ?? '', 0, 12), 12, "0", STR_PAD_LEFT);
            
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

// Busca estatísticas para exibir no header
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Total de associados ativos para o setor financeiro
    $sql = "SELECT COUNT(*) as total FROM Associados WHERE situacao = 'Filiado'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $totalAssociados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (Exception $e) {
    $totalAssociados = 0;
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'financeiro',
    'notificationCount' => 0,
    'showSearch' => true
]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery PRIMEIRO -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <style>
        /* Variáveis CSS modernas */
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #1e3d6f;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            --shadow-lg: 0 8px 40px rgba(44, 90, 160, 0.15);
            --border-radius: 15px;
            --border-radius-sm: 8px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 2rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
            max-width: 100%;
            width: 100%;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }

        .page-title-icon {
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            margin: 1rem 0 0 0;
            position: relative;
            z-index: 1;
        }

        /* Step Container Moderno */
        .step-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .step-container:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .step-title {
            color: var(--primary);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 2px solid var(--light);
            padding-bottom: 1rem;
        }

        .step-title i {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 0.75rem;
            border-radius: var(--border-radius-sm);
            font-size: 1.25rem;
        }

        .field-container {
            margin-bottom: 1.5rem;
        }

        .field-container label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control, .form-select {
            border-radius: var(--border-radius-sm);
            border: 2px solid #e9ecef;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.125rem rgba(0, 86, 210, 0.25);
            transform: translateY(-1px);
        }

        /* Botão Principal */
        .btn-generate {
            background: linear-gradient(135deg, var(--success), #1e7e34);
            border: none;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1.125rem;
            border-radius: var(--border-radius-sm);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            transition: all 0.3s ease;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-generate:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }

        /* Preview dos Associados */
        .preview-associados {
            background: linear-gradient(135deg, #f8f9ff, #e8f2ff);
            border: 2px solid var(--primary-light);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-top: 1.5rem;
            display: none;
            position: relative;
            overflow: hidden;
        }

        .preview-associados::before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 100px;
            height: 100px;
            background: rgba(74, 144, 226, 0.1);
            border-radius: 50%;
        }

        .associado-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .associado-card:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow);
        }

        .associado-info {
            flex-grow: 1;
        }

        .associado-valor {
            font-weight: 700;
            color: var(--success);
            font-size: 1.25rem;
            background: rgba(40, 167, 69, 0.1);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
            color: var(--primary);
        }

        .loading-spinner i {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Alertas Informativos */
        .alert {
            border-radius: var(--border-radius-sm);
            border: none;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        /* Preview das Matrículas */
        .matriculas-encontradas {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border: 2px solid var(--success);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        /* Botões Secundários */
        .btn-outline-primary, .btn-outline-secondary {
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover, .btn-outline-secondary:hover {
            transform: translateY(-2px);
        }

        /* Preview Link */
        .preview-link {
            color: var(--primary);
            text-decoration: none;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            word-break: break-all;
            background: rgba(0, 86, 210, 0.05);
            padding: 0.5rem;
            border-radius: var(--border-radius-sm);
            display: block;
            margin-top: 0.5rem;
        }

        /* ID Operação Info */
        .id-operacao-info {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 2px solid var(--info);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }
            
            .page-title {
                font-size: 2rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .step-container {
                padding: 1.5rem;
            }
            
            .associado-card {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
        }

        /* Toast Container */
        .toast-container {
            z-index: 9999;
        }

        /* Badge customizado */
        .badge {
            border-radius: var(--border-radius-sm);
            padding: 0.5rem 0.75rem;
            font-weight: 600;
        }

        /* ===== CSS ADICIONAL PARA BOTÕES BLOQUEADOS ===== */
/* Adicione este CSS ao arquivo gerar_recorrencia.php e neoconsig_content.php */

/* Estilo para botão bloqueado/desabilitado */
.btn-generate:disabled,
.btn-neo-generate:disabled {
    opacity: 0.6;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
    pointer-events: none;
}

/* Estilo para botão de erro/bloqueado */
.btn-generate.btn-danger,
.btn-neo-generate.btn-danger {
    background: linear-gradient(135deg, #dc3545, #a71e2a);
    border: none;
    color: white;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

.btn-generate.btn-danger:hover,
.btn-neo-generate.btn-danger:hover {
    background: linear-gradient(135deg, #dc3545, #a71e2a);
    transform: none !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

/* Estilo para botão secundário (estado inicial) */
.btn-generate.btn-secondary,
.btn-neo-generate.btn-secondary {
    background: linear-gradient(135deg, #6c757d, #495057);
    border: none;
    color: white;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
}

.btn-generate.btn-secondary:hover,
.btn-neo-generate.btn-secondary:hover {
    background: linear-gradient(135deg, #6c757d, #495057);
    transform: none !important;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
}

/* Animação de pulse para chamar atenção quando bloqueado */
.btn-blocked-pulse {
    animation: blockedPulse 2s infinite;
}

@keyframes blockedPulse {
    0% { 
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }
    50% { 
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.6);
    }
    100% { 
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }
}

/* Estilo para alertas informativos sobre botão bloqueado */
.alert-blocked-info {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border: 2px solid #ffc107;
    border-radius: 8px;
    color: #856404;
    padding: 1rem;
    margin: 1rem 0;
    font-weight: 500;
}

/* Estilo para indicador visual de status do botão */
.button-status-indicator {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 0.5rem;
}

.button-status-indicator.blocked {
    background: #dc3545;
    color: white;
}

.button-status-indicator.ready {
    background: #28a745;
    color: white;
}

.button-status-indicator.searching {
    background: #17a2b8;
    color: white;
}

/* Responsivo para dispositivos móveis */
@media (max-width: 768px) {
    .btn-generate,
    .btn-neo-generate {
        font-size: 0.9rem;
        padding: 0.75rem 1rem;
    }
    
    .button-status-indicator {
        display: block;
        margin: 0.5rem 0 0 0;
        text-align: center;
    }
}
    </style>
</head>

<body>
    <!-- Toast Container para Notificações -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-file-download"></i>
                    </div>
                    Optantes NeoConsig
                    <?php if ($isFinanceiro): ?>
                        <small style="opacity: 0.8; font-size: 1rem; font-weight: 400;">Setor Financeiro</small>
                    <?php elseif ($isPresidencia): ?>
                        <small style="opacity: 0.8; font-size: 1rem; font-weight: 400;">Presidência</small>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    <i class="fas fa-info-circle me-2"></i>
                    Gere arquivos TXT para inclusões, cancelamentos e alterações de valores do Governo do Estado de Goiás
                </p>
            </div>

            <?php if (isset($erro)): ?>
            <div class="alert alert-danger" data-aos="fade-up">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Erro:</strong> <?php echo htmlspecialchars($erro); ?>
            </div>
            <?php endif; ?>

            <!-- Formulário Principal -->
            <form method="GET" action="" id="formRecorrencia">
                <input type="hidden" name="gerar" value="1">
                
                <!-- Passo 1: Tipo de Processamento -->
                <div class="step-container" data-aos="fade-up" data-aos-delay="100">
                    <h4 class="step-title">
                        <i class="fas fa-cog"></i>
                        Tipo de Processamento
                    </h4>
                    
                    <div class="field-container">
                        <label class="form-label">Selecione o tipo de operação:</label>
                        <select name="tipo" id="tipo_processamento" class="form-select" required onchange="toggleCampos()">
                            <option value="">-- Selecione o tipo de processamento --</option>
                            <option value="1" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == '1') ? 'selected' : ''; ?>>
                                <i class="fas fa-plus-circle"></i> 1 - Inclusões (Novos Associados)
                            </option>
                            <option value="0" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == '0') ? 'selected' : ''; ?>>
                                <i class="fas fa-times-circle"></i> 0 - Cancelamentos
                            </option>
                            <option value="2" <?php echo (isset($_GET['tipo']) && $_GET['tipo'] == '2') ? 'selected' : ''; ?>>
                                <i class="fas fa-edit"></i> 2 - Alterações de Valores
                            </option>
                        </select>
                    </div>

                </div>

                <!-- Passo 2: Matrículas -->
                <div class="step-container" data-aos="fade-up" data-aos-delay="200">
                    <h4 class="step-title">
                        <i class="fas fa-users"></i>
                        Matrículas dos Associados
                    </h4>
                    
                    <div class="field-container">
                        <label class="form-label">Digite as matrículas dos associados:</label>
                        <textarea name="matriculas" id="matriculas" class="form-control" rows="4" 
                                  placeholder="Digite as matrículas separadas por vírgula. Ex: 445, 788, 1023, 1205, 1456" 
                                  required onchange="validarMatriculas()"><?php echo htmlspecialchars($_GET['matriculas'] ?? ''); ?></textarea>
                        <small class="text-muted">
                            <i class="fas fa-lightbulb me-1"></i>
                            <strong>Dica:</strong> Separe múltiplas matrículas com vírgula. Exemplo: 445,788,1023
                        </small>
                    </div>
                    
                    <!-- Preview das Matrículas -->
                    <div id="preview_matriculas" class="matriculas-encontradas" style="display: none;">
                        <h6><i class="fas fa-search me-2"></i>Preview das Matrículas:</h6>
                        <div id="lista_matriculas"></div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-primary" onclick="buscarAssociados()">
                                <i class="fas fa-search me-2"></i>Buscar Associado(s)
                            </button>
                        </div>
                    </div>
                    
                    <!-- Preview dos Associados Encontrados -->
                    <div id="preview_associados" class="preview-associados">
                        <h5><i class="fas fa-users text-primary me-2"></i>Associados Encontrados</h5>
                        
                        <!-- Loading -->
                        <div id="loading_associados" class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p class="mt-2">Buscando associados no banco de dados...</p>
                        </div>
                        
                        <!-- Lista de associados -->
                        <div id="lista_associados"></div>
                        
                        <!-- Resumo -->
                        <div id="resumo_associados"></div>
                    </div>
                </div>

                <!-- Passo 3: Gerar Arquivo -->
                <div class="step-container" data-aos="fade-up" data-aos-delay="300">
                    <h4 class="step-title">
                        <i class="fas fa-download"></i>
                        Gerar Arquivo
                    </h4>
                    
                    <!-- Preview do Link -->
                    <div id="preview_link" class="alert alert-light" style="display: none;">
                        <h6><i class="fas fa-link me-2"></i>Link que será executado:</h6>
                        <a href="#" id="link_geracao" class="preview-link" target="_blank"></a>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn-generate">
                            <i class="fas fa-file-download"></i>
                            Gerar e Baixar Arquivo TXT
                        </button>
                    </div>
                </div>
            </form>


            <!-- Botão Voltar -->
            <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="600">
                <a href="financeiro.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar ao Painel Financeiro
                </a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>
    
    <script>
        // ===== SISTEMA DE NOTIFICAÇÕES =====
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('toastContainer');
            }

            show(message, type = 'success', duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');

                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${this.getIcon(type)} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;

                this.container.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast, { delay: duration });
                bsToast.show();

                toast.addEventListener('hidden.bs.toast', () => {
                    toast.remove();
                });
            }

            getIcon(type) {
                const icons = {
                    success: 'check-circle',
                    error: 'exclamation-triangle',
                    warning: 'exclamation-circle',
                    info: 'info-circle'
                };
                return icons[type] || 'info-circle';
            }
        }

        // ===== INICIALIZAÇÃO =====
        const notifications = new NotificationSystem();

        document.addEventListener('DOMContentLoaded', function() {
    // Inicializar AOS
    AOS.init({ 
        duration: 800, 
        once: true,
        offset: 50
    });

    // ✅ INICIALIZAR BOTÃO COMO DESABILITADO
    const btnGerar = document.querySelector('.btn-generate');
    if (btnGerar) {
        btnGerar.disabled = true;
        btnGerar.innerHTML = '<i class="fas fa-search me-2"></i>Busque os Associados Primeiro';
        btnGerar.classList.add('btn-secondary');
        btnGerar.classList.remove('btn-success');
    }

    // ✅ ADICIONAR VALIDAÇÃO NO FORMULÁRIO
    const form = document.getElementById('formRecorrencia');
    if (form) {
        form.addEventListener('submit', validarAntesDeGerar);
    }

    // Inicializar funções
    toggleCampos();
    validarMatriculas();
    
    // ✅ RESETAR BOTÃO QUANDO CAMPOS IMPORTANTES MUDAREM
    ['tipo_processamento', 'matriculas'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', function() {
                resetarBotaoQuandoCamposMudarem();
                atualizarPreviewLink();
            });
            element.addEventListener('input', function() {
                resetarBotaoQuandoCamposMudarem();
                atualizarPreviewLink();
            });
        }
    });
    
    // Monitorar mudanças para atualizar preview
    const rubrica = document.getElementById('rubrica');
    if (rubrica) {
        rubrica.addEventListener('change', atualizarPreviewLink);
        rubrica.addEventListener('input', atualizarPreviewLink);
    }
    
    notifications.show('Sistema carregado! Busque os associados antes de gerar o arquivo.', 'info', 3000);
});

        // ===== FUNÇÕES PRINCIPAIS =====

        function toggleCampos() {
            const tipo = document.getElementById('tipo_processamento').value;
            const container = document.getElementById('container_id_operacao');
            
            if (tipo === '0' || tipo === '2') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
            
            atualizarPreviewLink();
        }

        function validarMatriculas() {
            const matriculas = document.getElementById('matriculas').value.trim();
            const preview = document.getElementById('preview_matriculas');
            const lista = document.getElementById('lista_matriculas');
            const previewAssociados = document.getElementById('preview_associados');
            
            if (matriculas) {
                const matriculasArray = matriculas.split(',').map(m => m.trim()).filter(m => m);
                const matriculasValidas = matriculasArray.filter(m => /^\d+$/.test(m));
                const matriculasInvalidas = matriculasArray.filter(m => !/^\d+$/.test(m));
                
                let html = `<div class="row">`;
                html += `<div class="col-md-4"><strong><i class="fas fa-list-ol me-2"></i>Encontradas:</strong> ${matriculasValidas.length}</div>`;
                html += `</div>`;
                
                if (matriculasInvalidas.length > 0) {
                    html += `<div class="mt-2"><strong class="text-danger"><i class="fas fa-times me-2"></i>Inválidas:</strong> ${matriculasInvalidas.join(', ')}</div>`;
                }
                
                lista.innerHTML = html;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
                previewAssociados.style.display = 'none';
            }
            
            atualizarPreviewLink();
        }

        // Buscar associados via AJAX
        async function buscarAssociados() {
    const matriculas = document.getElementById('matriculas').value.trim();
    const previewAssociados = document.getElementById('preview_associados');
    const loading = document.getElementById('loading_associados');
    const listaAssociados = document.getElementById('lista_associados');
    const resumoAssociados = document.getElementById('resumo_associados');
    
    // ✅ NOVO: Referência ao botão de gerar
    const btnGerar = document.querySelector('.btn-generate');
    const formGerar = document.getElementById('formRecorrencia');
    
    if (!matriculas) {
        notifications.show('Digite as matrículas primeiro', 'warning');
        return;
    }
    
    // Mostrar loading
    previewAssociados.style.display = 'block';
    loading.style.display = 'block';
    listaAssociados.innerHTML = '';
    resumoAssociados.innerHTML = '';
    
    // ✅ NOVO: Desabilitar botão durante a busca
    if (btnGerar) {
        btnGerar.disabled = true;
        btnGerar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Buscando associados...';
    }
    
    try {
        const response = await fetch(`?preview_associados=1&matriculas=${encodeURIComponent(matriculas)}`);
        const data = await response.json();
        
        loading.style.display = 'none';
        
        if (data.success) {
            if (data.encontrados.length > 0) {
                // ✅ ASSOCIADOS ENCONTRADOS - HABILITAR BOTÃO
                let html = '';
                
                data.encontrados.forEach(assoc => {
                    const cpfFormatado = assoc.cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                    const tipo = document.getElementById('tipo_processamento').value;
                    
                    // Mostrar ID de operação para cancelamentos e alterações
                    let idOperacaoInfo = '';
                    if (tipo === '0' || tipo === '2') {
                        const idOperacao = assoc.id_neoconsig || 'Não encontrado';
                        idOperacaoInfo = `<br><small><i class="fas fa-key me-1"></i>ID Operação: <span class="badge bg-secondary">${idOperacao}</span></small>`;
                    }
                    
                    // Mostrar vínculo servidor
                    let vinculoInfo = '';
                    if (assoc.vinculoServidor) {
                        vinculoInfo = `<br><small><i class="fas fa-id-badge me-1"></i>Vínculo: <span class="badge bg-info">${assoc.vinculoServidor}</span></small>`;
                    }
                    
                    html += `
                        <div class="associado-card">
                            <div class="associado-info">
                                <strong><i class="fas fa-user me-2"></i>${assoc.nome}</strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-id-badge me-1"></i>Matrícula: <span class="badge bg-primary">${assoc.id}</span> 
                                    | <i class="fas fa-id-card me-1"></i>CPF: <code>${cpfFormatado}</code>${vinculoInfo}${idOperacaoInfo}
                                </small>
                            </div>
                            <div class="associado-valor">
                                <i class="fas fa-dollar-sign me-1"></i>R$ ${parseFloat(assoc.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                            </div>
                        </div>
                    `;
                });
                
                listaAssociados.innerHTML = html;
                
                // Mostrar resumo
                let resumoHtml = `
                    <div class="alert alert-success mt-3">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <strong><i class="fas fa-users me-2"></i>Encontrados:</strong> ${data.total_encontrados}
                            </div>
                            <div class="col-md-4">
                                <strong><i class="fas fa-money-bill-wave me-2"></i>Total Mensal:</strong> R$ ${parseFloat(data.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                            </div>
                            <div class="col-md-4">
                                <strong><i class="fas fa-calculator me-2"></i>Média:</strong> R$ ${(parseFloat(data.valor_total) / data.total_encontrados).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                            </div>
                        </div>
                    </div>
                `;
                
                if (data.nao_encontrados.length > 0) {
                    resumoHtml += `
                        <div class="alert alert-warning mt-2">
                            <strong><i class="fas fa-exclamation-triangle me-2"></i>Não encontrados:</strong> ${data.nao_encontrados.join(', ')}
                        </div>
                    `;
                }
                
                resumoAssociados.innerHTML = resumoHtml;
                
                // ✅ HABILITAR BOTÃO - ASSOCIADOS ENCONTRADOS
                if (btnGerar) {
                    btnGerar.disabled = false;
                    btnGerar.innerHTML = '<i class="fas fa-file-download me-2"></i>Gerar e Baixar Arquivo TXT';
                    btnGerar.classList.remove('btn-danger');
                    btnGerar.classList.add('btn-success');
                }
                
                notifications.show(`${data.total_encontrados} associados encontrados! Botão liberado.`, 'success');
                
            } else {
                // ✅ NENHUM ASSOCIADO ENCONTRADO - BLOQUEAR BOTÃO
                listaAssociados.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Nenhum associado encontrado com as matrículas informadas.
                        <br><strong>O arquivo não pode ser gerado sem associados válidos.</strong>
                    </div>
                `;
                
                // ✅ BLOQUEAR BOTÃO
                if (btnGerar) {
                    btnGerar.disabled = true;
                    btnGerar.innerHTML = '<i class="fas fa-ban me-2"></i>Nenhum Associado - Arquivo Bloqueado';
                    btnGerar.classList.remove('btn-success');
                    btnGerar.classList.add('btn-danger');
                }
                
                notifications.show('Nenhum associado encontrado. Geração de arquivo bloqueada.', 'error');
            }
        } else {
            // ✅ ERRO NA BUSCA - BLOQUEAR BOTÃO
            listaAssociados.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Erro: ${data.error}
                </div>
            `;
            
            // ✅ BLOQUEAR BOTÃO
            if (btnGerar) {
                btnGerar.disabled = true;
                btnGerar.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Erro na Busca - Arquivo Bloqueado';
                btnGerar.classList.remove('btn-success');
                btnGerar.classList.add('btn-danger');
            }
            
            notifications.show('Erro na busca: ' + data.error, 'error');
        }
        
    } catch (error) {
        loading.style.display = 'none';
        listaAssociados.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Erro na comunicação: ${error.message}
            </div>
        `;
        
        // ✅ ERRO DE COMUNICAÇÃO - BLOQUEAR BOTÃO
        if (btnGerar) {
            btnGerar.disabled = true;
            btnGerar.innerHTML = '<i class="fas fa-wifi me-2"></i>Erro de Comunicação - Arquivo Bloqueado';
            btnGerar.classList.remove('btn-success');
            btnGerar.classList.add('btn-danger');
        }
        
        notifications.show('Erro na comunicação com o servidor', 'error');
    }
}

// ===== NOVA FUNÇÃO PARA VALIDAR ANTES DO ENVIO =====
function validarAntesDeGerar(event) {
    const btnGerar = document.querySelector('.btn-generate');
    
    // Se o botão estiver desabilitado, impedir o envio
    if (btnGerar && btnGerar.disabled) {
        event.preventDefault();
        notifications.show('Você precisa buscar associados válidos antes de gerar o arquivo!', 'error');
        return false;
    }
    
    // Verificar se foi feita uma busca
    const previewAssociados = document.getElementById('preview_associados');
    const listaAssociados = document.getElementById('lista_associados');
    
    if (!previewAssociados || previewAssociados.style.display === 'none' || !listaAssociados.innerHTML.includes('associado-card')) {
        event.preventDefault();
        notifications.show('Você deve buscar os associados primeiro clicando em "Buscar Associado(s)"!', 'warning');
        return false;
    }
    
    return true;
}

// ===== FUNÇÃO PARA RESETAR BOTÃO QUANDO CAMPOS MUDAREM =====
function resetarBotaoQuandoCamposMudarem() {
    const btnGerar = document.querySelector('.btn-generate');
    const previewAssociados = document.getElementById('preview_associados');
    
    if (btnGerar) {
        btnGerar.disabled = true;
        btnGerar.innerHTML = '<i class="fas fa-search me-2"></i>Busque os Associados Primeiro';
        btnGerar.classList.remove('btn-success', 'btn-danger');
        btnGerar.classList.add('btn-secondary');
    }
    
    if (previewAssociados) {
        previewAssociados.style.display = 'none';
    }
}

        function atualizarPreviewLink() {
            const form = document.getElementById('formRecorrencia');
            const formData = new FormData(form);
            const params = new URLSearchParams();
            
            for (let [key, value] of formData) {
                if (value) params.append(key, value);
            }
            
            const link = window.location.pathname + '?' + params.toString();
            const previewDiv = document.getElementById('preview_link');
            const linkElement = document.getElementById('link_geracao');
            
            if (params.get('tipo') && params.get('matriculas')) {
                linkElement.href = link;
                linkElement.textContent = link;
                previewDiv.style.display = 'block';
            } else {
                previewDiv.style.display = 'none';
            }
        }

        function consultarMatriculas() {
            const matriculas = document.getElementById('matriculas').value.trim();
            if (matriculas) {
                const url = `?consultar_matriculas=1&matriculas=${encodeURIComponent(matriculas)}`;
                window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes');
                notifications.show('Abrindo consulta em nova janela', 'info');
            } else {
                notifications.show('Digite as matrículas primeiro', 'warning');
            }
        }

        console.log('✅ Sistema de Geração de Recorrência carregado (versão moderna com header integrado)!');
    </script>
</body>
</html>

<?php
// ✅ CONSULTA DE MATRÍCULAS (popup window)
if (isset($_GET['consultar_matriculas'])) {
    $matriculas = $_GET['matriculas'] ?? '';
    
    echo "<!DOCTYPE html><html><head><title>Dados dos Associados</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>";
    echo "<style>body{font-family:'Plus Jakarta Sans',sans-serif;background:#f8f9fa;padding:20px;}</style>";
    echo "</head><body>";
    echo "<div class='container-fluid'>";
    echo "<h3><i class='fas fa-users me-3'></i>Dados dos Associados Encontrados</h3>";
    echo "<hr>";
    
    if ($matriculas) {
        $matriculas_array = array_map('trim', explode(',', $matriculas));
        $matriculas_array = array_filter($matriculas_array, function($m) {
            return !empty($m) && is_numeric($m);
        });
        
        if ($matriculas_array) {
            try {
                $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
                $placeholders = str_repeat('?,', count($matriculas_array) - 1) . '?';
                
                $sql = "SELECT a.id, a.nome, a.cpf,
                               COALESCE(SUM(sa.valor_aplicado), 86.50) as valor_total,
                               f.id_neoconsig,
                               f.vinculoServidor
                        FROM Associados a 
                        LEFT JOIN Servicos_Associado sa ON a.id = sa.associado_id AND sa.ativo = 1
                        LEFT JOIN Financeiro f ON a.id = f.associado_id
                        WHERE a.id IN ($placeholders)
                        AND a.situacao = 'Filiado'
                        GROUP BY a.id, a.nome, a.cpf, f.id_neoconsig, f.vinculoServidor
                        ORDER BY a.id";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($matriculas_array);
                $encontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $ids_encontrados = array_column($encontrados, 'id');
                $ids_nao_encontrados = array_diff($matriculas_array, $ids_encontrados);
                
                echo "<div class='alert alert-info'>";
                echo "<strong><i class='fas fa-search me-2'></i>Busca realizada:</strong> " . implode(', ', $matriculas_array) . "<br>";
                echo "<strong><i class='fas fa-check-circle me-2'></i>Encontrados:</strong> " . count($encontrados) . " associado(s)";
                if ($ids_nao_encontrados) {
                    echo "<br><strong><i class='fas fa-times-circle me-2'></i>Não encontrados:</strong> " . implode(', ', $ids_nao_encontrados);
                }
                echo "</div>";
                
                if ($encontrados) {
                    echo "<div class='table-responsive'>";
                    echo "<table class='table table-striped table-hover'>";
                    echo "<thead class='table-primary'>";
                    echo "<tr>";
                    echo "<th><i class='fas fa-id-badge me-1'></i>Matrícula</th>";
                    echo "<th><i class='fas fa-user me-1'></i>Nome</th>";
                    echo "<th><i class='fas fa-id-card me-1'></i>CPF</th>";
                    echo "<th><i class='fas fa-dollar-sign me-1'></i>Valor Mensal</th>";
                    echo "<th><i class='fas fa-link me-1'></i>Vínculo Servidor</th>";
                    echo "<th><i class='fas fa-key me-1'></i>ID Operação</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    
                    $total_geral = 0;
                    
                    foreach ($encontrados as $assoc) {
                        $cpf_formatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $assoc['cpf']);
                        $total_geral += $assoc['valor_total'];
                        
                        echo "<tr>";
                        echo "<td><span class='badge bg-primary fs-6'>{$assoc['id']}</span></td>";
                        echo "<td><strong>" . htmlspecialchars($assoc['nome']) . "</strong></td>";
                        echo "<td><code>{$cpf_formatado}</code></td>";
                        echo "<td><strong class='text-success'>R$ " . number_format($assoc['valor_total'], 2, ',', '.') . "</strong></td>";
                        echo "<td>";
                        if (!empty($assoc['vinculoServidor'])) {
                            echo "<span class='badge bg-info fs-6'>{$assoc['vinculoServidor']}</span>";
                        } else {
                            echo "<span class='text-muted'><i class='fas fa-minus'></i> Não informado</span>";
                        }
                        echo "</td>";
                        echo "<td>";
                        if (!empty($assoc['id_neoconsig'])) {
                            echo "<span class='badge bg-secondary fs-6'>{$assoc['id_neoconsig']}</span>";
                        } else {
                            echo "<span class='text-muted'><i class='fas fa-minus'></i> Não informado</span>";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody>";
                    echo "<tfoot class='table-light'>";
                    echo "<tr>";
                    echo "<th colspan='5' class='text-end'><i class='fas fa-calculator me-2'></i>TOTAL MENSAL:</th>";
                    echo "<th><strong class='text-success fs-5'>R$ " . number_format($total_geral, 2, ',', '.') . "</strong></th>";
                    echo "</tr>";
                    echo "</tfoot>";
                    echo "</table>";
                    echo "</div>";
                    
                    echo "<div class='row mt-4'>";
                    echo "<div class='col-md-6'>";
                    echo "<div class='alert alert-success text-center'>";
                    echo "<h5><i class='fas fa-users me-2'></i>Total de Associados</h5>";
                    echo "<h3 class='mb-0'>" . count($encontrados) . "</h3>";
                    echo "</div>";
                    echo "</div>";
                    echo "<div class='col-md-6'>";
                    echo "<div class='alert alert-info text-center'>";
                    echo "<h5><i class='fas fa-money-bill-wave me-2'></i>Arrecadação Mensal</h5>";
                    echo "<h3 class='mb-0'>R$ " . number_format($total_geral, 2, ',', '.') . "</h3>";
                    echo "</div>";
                    echo "</div>";
                    echo "</div>";
                    
                } else {
                    echo "<div class='alert alert-warning'>";
                    echo "<i class='fas fa-exclamation-triangle me-2'></i>";
                    echo "Nenhum associado encontrado com as matrículas informadas.";
                    echo "</div>";
                }
                
                if ($ids_nao_encontrados) {
                    echo "<div class='alert alert-danger'>";
                    echo "<h6><i class='fas fa-times-circle me-2'></i>Matrículas não encontradas:</h6>";
                    echo "<p class='mb-2'><strong>" . implode(', ', $ids_nao_encontrados) . "</strong></p>";
                    echo "<small>Verifique se os IDs estão corretos e se os associados possuem situação 'Filiado'.</small>";
                    echo "</div>";
                }
                
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>";
                echo "<i class='fas fa-exclamation-circle me-2'></i>";
                echo "<strong>Erro:</strong> " . $e->getMessage();
                echo "</div>";
            }
        }
    }
    
    echo "<div class='text-center mt-4'>";
    echo "<button onclick='window.close()' class='btn btn-secondary btn-lg'>";
    echo "<i class='fas fa-times me-2'></i>Fechar Janela";
    echo "</button>";
    echo "</div>";
    echo "</div>";
    echo "</body></html>";
    exit;
}
?>