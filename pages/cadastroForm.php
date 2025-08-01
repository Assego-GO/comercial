<?php
/**
 * Formulário de Cadastro de Associados - VERSÃO FICHA DE FILIAÇÃO
 * pages/cadastroForm.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Filiar Novo Associado - ASSEGO';

// Verifica se é edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$associadoId = $isEdit ? intval($_GET['id']) : null;
$associadoData = null;

if ($isEdit) {
    $associados = new Associados();
    $associadoData = $associados->getById($associadoId);

    if (!$associadoData) {
        header('Location: dashboard.php');
        exit;
    }

    $page_title = 'Editar Associado - ASSEGO';
}


try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

    // Buscar serviços ativos
    $stmt = $db->prepare("SELECT id, nome, valor_base FROM Servicos WHERE ativo = 1 ORDER BY nome");
    $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar tipos de associado únicos ordenados
    $stmt = $db->prepare("
        SELECT DISTINCT tipo_associado 
        FROM Regras_Contribuicao 
        ORDER BY 
            CASE 
                WHEN tipo_associado = 'Contribuinte' THEN 1
                WHEN tipo_associado = 'Aluno' THEN 2
                WHEN tipo_associado = 'Soldado 1ª Classe' THEN 3
                WHEN tipo_associado = 'Soldado 2ª Classe' THEN 4
                WHEN tipo_associado = 'Agregado' THEN 5
                WHEN tipo_associado = 'Remido 50%' THEN 6
                WHEN tipo_associado = 'Remido' THEN 7
                WHEN tipo_associado = 'Benemerito' THEN 8
                ELSE 9
            END
    ");
    $stmt->execute();
    $tiposAssociado = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Buscar regras de contribuição para usar no JavaScript
    $stmt = $db->prepare("
        SELECT rc.tipo_associado, rc.servico_id, rc.percentual_valor, rc.opcional, s.nome as servico_nome 
        FROM Regras_Contribuicao rc 
        INNER JOIN Servicos s ON rc.servico_id = s.id 
        WHERE s.ativo = 1
        ORDER BY rc.tipo_associado, s.nome
    ");
    $stmt->execute();
    $regrasContribuicao = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Se não há dados, cria os dados padrão
    if (empty($servicos) || empty($tiposAssociado) || empty($regrasContribuicao)) {
        // Chama a API para criar dados padrão
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../api/buscar_dados_servicos.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                // Recarrega os dados após criação
                $stmt = $db->prepare("SELECT id, nome, valor_base FROM Servicos WHERE ativo = 1 ORDER BY nome");
                $stmt->execute();
                $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $db->prepare("SELECT DISTINCT tipo_associado FROM Regras_Contribuicao ORDER BY tipo_associado");
                $stmt->execute();
                $tiposAssociado = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $stmt = $db->prepare("
                    SELECT rc.tipo_associado, rc.servico_id, rc.percentual_valor, rc.opcional, s.nome as servico_nome 
                    FROM Regras_Contribuicao rc 
                    INNER JOIN Servicos s ON rc.servico_id = s.id 
                    WHERE s.ativo = 1
                    ORDER BY rc.tipo_associado, s.nome
                ");
                $stmt->execute();
                $regrasContribuicao = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

} catch (Exception $e) {
    error_log("Erro ao buscar dados para serviços: " . $e->getMessage());
    $servicos = [];
    $tiposAssociado = [];
    $regrasContribuicao = [];
}
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- jQuery Mask -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <!-- Custom CSS Files -->
    <link rel="stylesheet" href="estilizacao/cadastroForm.css">
    <link rel="stylesheet" href="estilizacao/autocomplete.css">
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processando...</div>
    </div>

    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <div class="logo-section">
                <div
                    style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                    A
                </div>
                <div>
                    <h1 class="logo-text">ASSEGO</h1>
                    <p class="system-subtitle">Sistema de Gestão</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
        <nav style="display: flex; align-items: center; gap: 1rem;">
            <button type="button" class="btn-breadcrumb-back" onclick="window.location.href='dashboard.php'" title="Voltar ao Dashboard">
                <i class="fas fa-arrow-left"></i>
            </button>
            <ol class="breadcrumb-custom">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li><a href="dashboard.php">Associados</a></li>
                <li class="separator"><i class="fas fa-chevron-right"></i></li>
                <li class="active"><?php echo $isEdit ? 'Editar' : 'Nova Filiação'; ?></li>
            </ol>
        </nav>
    </div>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-user-plus"></i>
                        <?php echo $isEdit ? 'Editar Associado' : 'Filiar Novo Associado'; ?>
                    </h1>
                    <p class="page-subtitle">
                        <?php echo $isEdit ? 'Atualize os dados do associado' : 'Preencha todos os campos obrigatórios para filiar um novo associado'; ?>
                    </p>
                </div>
                <button type="button" class="btn-dashboard" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-left"></i>
                    Voltar ao Dashboard
                    <span style="font-size: 0.7rem; opacity: 0.8; margin-left: 0.5rem;">(ESC)</span>
                </button>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Progress Bar -->
            <div class="progress-bar-container">
                <div class="progress-steps">
                    <div class="progress-line" id="progressLine"></div>

                    <div class="step active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Dados Pessoais</div>
                    </div>

                    <div class="step" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Dados Militares</div>
                    </div>

                    <div class="step" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Endereço</div>
                    </div>

                    <div class="step" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Financeiro</div>
                    </div>

                    <div class="step" data-step="5">
                        <div class="step-circle">5</div>
                        <div class="step-label">Dependentes</div>
                    </div>

                    <div class="step" data-step="6">
                        <div class="step-circle">6</div>
                        <div class="step-label">Revisão</div>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <form id="formAssociado" class="form-content" enctype="multipart/form-data">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $associadoId; ?>">
                <?php endif; ?>

                <!-- Step 1: Dados Pessoais -->
                <div class="section-card active" data-step="1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Pessoais</h2>
                            <p class="section-subtitle">Informações básicas do associado</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">
                                Nome Completo <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="nome" id="nome" required
                                value="<?php echo $associadoData['nome'] ?? ''; ?>"
                                placeholder="Digite o nome completo do associado">
                            <span class="form-error">Por favor, insira o nome completo</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Nascimento <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="nasc" id="nasc" required
                                value="<?php echo $associadoData['nasc'] ?? ''; ?>">
                            <span class="form-error">Por favor, insira a data de nascimento</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Sexo <span class="required">*</span>
                            </label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_m" value="M" required <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'M') ? 'checked' : ''; ?>>
                                    <label for="sexo_m">Masculino</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_f" value="F" required <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'F') ? 'checked' : ''; ?>>
                                    <label for="sexo_f">Feminino</label>
                                </div>
                            </div>
                            <span class="form-error">Por favor, selecione o sexo</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Estado Civil
                            </label>
                            <select class="form-input form-select" name="estadoCivil" id="estadoCivil">
                                <option value="">Selecione...</option>
                                <option value="Solteiro(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Solteiro(a)') ? 'selected' : ''; ?>>Solteiro(a)
                                </option>
                                <option value="Casado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Casado(a)') ? 'selected' : ''; ?>>Casado(a)</option>
                                <option value="Divorciado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Divorciado(a)') ? 'selected' : ''; ?>>Divorciado(a)
                                </option>
                                <option value="Viúvo(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Viúvo(a)') ? 'selected' : ''; ?>>Viúvo(a)</option>
                                <option value="União Estável" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'União Estável') ? 'selected' : ''; ?>>União Estável
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                RG <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="rg" id="rg" required
                                value="<?php echo $associadoData['rg'] ?? ''; ?>" placeholder="Número do RG">
                            <span class="form-error">Por favor, insira o RG</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                CPF <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="cpf" id="cpf" required
                                value="<?php echo $associadoData['cpf'] ?? ''; ?>" placeholder="000.000.000-00">
                            <span class="form-error">Por favor, insira um CPF válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Telefone <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="telefone" id="telefone" required
                                value="<?php echo $associadoData['telefone'] ?? ''; ?>" placeholder="(00) 00000-0000">
                            <span class="form-error">Por favor, insira o telefone</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                E-mail
                            </label>
                            <input type="email" class="form-input" name="email" id="email"
                                value="<?php echo $associadoData['email'] ?? ''; ?>" placeholder="email@exemplo.com">
                            <span class="form-error">Por favor, insira um e-mail válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Escolaridade
                            </label>
                            <select class="form-input form-select" name="escolaridade" id="escolaridade">
                                <option value="">Selecione...</option>
                                <option value="Fundamental Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Incompleto') ? 'selected' : ''; ?>>
                                    Fundamental Incompleto</option>
                                <option value="Fundamental Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Completo') ? 'selected' : ''; ?>>
                                    Fundamental Completo</option>
                                <option value="Médio Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Incompleto') ? 'selected' : ''; ?>>Médio
                                    Incompleto</option>
                                <option value="Médio Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Completo') ? 'selected' : ''; ?>>Médio
                                    Completo</option>
                                <option value="Superior Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Incompleto') ? 'selected' : ''; ?>>
                                    Superior Incompleto</option>
                                <option value="Superior Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Completo') ? 'selected' : ''; ?>>Superior
                                    Completo</option>
                                <option value="Pós-graduação" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Pós-graduação') ? 'selected' : ''; ?>>Pós-graduação
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Indicado por
                                <i class="fas fa-info-circle info-tooltip"
                                    title="Nome da pessoa que indicou o associado"></i>
                            </label>
                            <div class="autocomplete-container" style="position: relative;">
                                <input type="text" class="form-input" name="indicacao" id="indicacao"
                                    value="<?php echo $associadoData['indicacao'] ?? ''; ?>"
                                    placeholder="Digite o nome de quem indicou..." autocomplete="off">
                                <div id="indicacaoSuggestions" class="autocomplete-suggestions"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Situação <span class="required">*</span>
                            </label>
                            <select class="form-input form-select" name="situacao" id="situacao" required>
                                <option value="Filiado" <?php echo (!isset($associadoData['situacao']) || $associadoData['situacao'] == 'Filiado') ? 'selected' : ''; ?>>Filiado</option>
                                <option value="Desfiliado" <?php echo (isset($associadoData['situacao']) && $associadoData['situacao'] == 'Desfiliado') ? 'selected' : ''; ?>>Desfiliado</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Filiação <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="dataFiliacao" id="dataFiliacao" required
                                value="<?php echo $associadoData['data_filiacao'] ?? date('Y-m-d'); ?>">
                            <span class="form-error">Por favor, insira a data de filiação</span>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                Foto do Associado <span class="required">*</span>
                            </label>
                            <div class="photo-upload-container">
                                <div class="photo-preview" id="photoPreview">
                                    <?php if (isset($associadoData['foto']) && $associadoData['foto']): ?>
                                        <img src="<?php echo $associadoData['foto']; ?>" alt="Foto do associado">
                                    <?php else: ?>
                                        <div class="photo-preview-placeholder">
                                            <i class="fas fa-camera"></i>
                                            <p>Sem foto</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <input type="file" name="foto" id="foto" accept="image/*" style="display: none;"
                                        <?php echo $isEdit ? '' : 'required'; ?>>
                                    <button type="button" class="photo-upload-btn"
                                        onclick="document.getElementById('foto').click();">
                                        <i class="fas fa-upload"></i>
                                        Escolher Foto
                                    </button>
                                    <p class="text-muted mt-2" style="font-size: 0.75rem;">
                                        Formatos aceitos: JPG, PNG, GIF<br>
                                        Tamanho máximo: 5MB
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Campo para upload da ficha assinada - APENAS PARA NOVOS CADASTROS -->
                        <?php if (!$isEdit): ?>
                            <div class="form-group full-width">
                                <label class="form-label">
                                    Ficha de Filiação Assinada <span class="required">*</span>
                                    <i class="fas fa-info-circle info-tooltip"
                                        title="Anexe a foto ou PDF da ficha preenchida e assinada pelo associado"></i>
                                </label>
                                <div class="ficha-upload-container"
                                    style="background: var(--warning); background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 2rem; border-radius: 16px; border: 2px dashed #f0ad4e;">
                                    <div style="display: flex; align-items: center; gap: 2rem;">
                                        <div class="ficha-preview" id="fichaPreview"
                                            style="width: 200px; height: 250px; background: var(--white); border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; border: 2px solid var(--warning);">
                                            <div class="ficha-preview-placeholder"
                                                style="text-align: center; color: var(--warning);">
                                                <i class="fas fa-file-contract"
                                                    style="font-size: 4rem; margin-bottom: 1rem;"></i>
                                                <p style="font-weight: 600;">Ficha de Filiação</p>
                                                <p style="font-size: 0.875rem;">Nenhum arquivo anexado</p>
                                            </div>
                                        </div>

                                        <div style="flex: 1;">
                                            <h4 style="color: var(--warning); margin-bottom: 1rem;">
                                                <i class="fas fa-exclamation-triangle"></i> Documento Obrigatório
                                            </h4>
                                            <p style="color: #856404; margin-bottom: 1rem;">
                                                É obrigatório anexar a ficha de filiação preenchida e assinada pelo
                                                associado.
                                                Este documento será enviado para aprovação da presidência.
                                            </p>

                                            <input type="file" name="ficha_assinada" id="ficha_assinada"
                                                accept=".pdf,.jpg,.jpeg,.png" style="display: none;" required>

                                            <button type="button" class="btn btn-warning"
                                                onclick="document.getElementById('ficha_assinada').click();"
                                                style="background: var(--warning); color: var(--dark); border: none; padding: 0.875rem 1.5rem; border-radius: 12px; font-weight: 600; cursor: pointer;">
                                                <i class="fas fa-upload"></i> Anexar Ficha Assinada
                                            </button>

                                            <p style="font-size: 0.75rem; color: #856404; margin-top: 0.5rem;">
                                                Formatos aceitos: PDF, JPG, PNG | Tamanho máximo: 10MB
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Opção de enviar automaticamente -->
                                    <div
                                        style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(240, 173, 78, 0.3);">
                                        <div class="checkbox-item">
                                            <input type="checkbox" name="enviar_presidencia" id="enviar_presidencia"
                                                value="1" checked>
                                            <label for="enviar_presidencia" style="color: #856404; font-weight: 600;">
                                                <i class="fas fa-paper-plane"></i> Enviar automaticamente para aprovação da
                                                presidência após a filiação
                                            </label>
                                        </div>
                                        <p
                                            style="font-size: 0.75rem; color: #856404; margin-top: 0.5rem; margin-left: 1.5rem;">
                                            Se desmarcado, a ficha de filiação ficará aguardando até que você envie manualmente
                                            para aprovação.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Dados Militares -->
                <div class="section-card" data-step="2">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Militares</h2>
                            <p class="section-subtitle">Informações sobre a carreira militar</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Corporação
                            </label>
                            <select class="form-input form-select" name="corporacao" id="corporacao">
                                <option value="">Selecione...</option>
                                <option value="Polícia Militar" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Militar') ? 'selected' : ''; ?>>Polícia
                                    Militar</option>
                                <option value="Corpo de Bombeiros" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Corpo de Bombeiros') ? 'selected' : ''; ?>>Corpo de
                                    Bombeiros</option>
                                <option value="Polícia Civil" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Civil') ? 'selected' : ''; ?>>Polícia Civil
                                </option>
                                <option value="Polícia Federal" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Federal') ? 'selected' : ''; ?>>Polícia
                                    Federal</option>
                                <option value="Forças Armadas" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Forças Armadas') ? 'selected' : ''; ?>>Forças Armadas
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Patente
                            </label>
                            <select class="form-input form-select" name="patente" id="patente">
                                <option value="">Selecione...</option>
                                <optgroup label="Praças">
                                    <option value="Soldado" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Soldado') ? 'selected' : ''; ?>>Soldado</option>
                                    <option value="Cabo" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Cabo') ? 'selected' : ''; ?>>Cabo</option>
                                    <option value="3º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '3º Sargento') ? 'selected' : ''; ?>>3º Sargento
                                    </option>
                                    <option value="2º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '2º Sargento') ? 'selected' : ''; ?>>2º Sargento
                                    </option>
                                    <option value="1º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '1º Sargento') ? 'selected' : ''; ?>>1º Sargento
                                    </option>
                                    <option value="Subtenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Subtenente') ? 'selected' : ''; ?>>Subtenente
                                    </option>
                                </optgroup>
                                <optgroup label="Oficiais">
                                    <option value="2º Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '2º Tenente') ? 'selected' : ''; ?>>2º Tenente
                                    </option>
                                    <option value="1º Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '1º Tenente') ? 'selected' : ''; ?>>1º Tenente
                                    </option>
                                    <option value="Capitão" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Capitão') ? 'selected' : ''; ?>>Capitão</option>
                                    <option value="Major" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Major') ? 'selected' : ''; ?>>Major</option>
                                    <option value="Tenente-Coronel" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Tenente-Coronel') ? 'selected' : ''; ?>>
                                        Tenente-Coronel</option>
                                    <option value="Coronel" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Coronel') ? 'selected' : ''; ?>>Coronel</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Categoria
                            </label>
                            <select class="form-input form-select" name="categoria" id="categoria">
                                <option value="">Selecione...</option>
                                <option value="Ativo" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Ativo') ? 'selected' : ''; ?>>Ativo</option>
                                <option value="Reserva" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Reserva') ? 'selected' : ''; ?>>Reserva</option>
                                <option value="Reformado" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Reformado') ? 'selected' : ''; ?>>Reformado</option>
                                <option value="Pensionista" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Lotação
                            </label>
                            <input type="text" class="form-input" name="lotacao" id="lotacao"
                                value="<?php echo $associadoData['lotacao'] ?? ''; ?>" placeholder="Local de lotação">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                Unidade
                            </label>
                            <input type="text" class="form-input" name="unidade" id="unidade"
                                value="<?php echo $associadoData['unidade'] ?? ''; ?>"
                                placeholder="Unidade em que serve/serviu">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Endereço -->
                <div class="section-card" data-step="3">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Endereço</h2>
                            <p class="section-subtitle">Dados de localização do associado</p>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="cep-search-container">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">
                                    CEP
                                </label>
                                <input type="text" class="form-input" name="cep" id="cep"
                                    value="<?php echo $associadoData['cep'] ?? ''; ?>" placeholder="00000-000">
                            </div>
                            <button type="button" class="btn-search-cep" onclick="buscarCEP()">
                                <i class="fas fa-search"></i>
                                Buscar CEP
                            </button>
                        </div>

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">
                                    Endereço
                                </label>
                                <input type="text" class="form-input" name="endereco" id="endereco"
                                    value="<?php echo $associadoData['endereco'] ?? ''; ?>"
                                    placeholder="Rua, Avenida, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Número
                                </label>
                                <input type="text" class="form-input" name="numero" id="numero"
                                    value="<?php echo $associadoData['numero'] ?? ''; ?>" placeholder="Nº">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Complemento
                                </label>
                                <input type="text" class="form-input" name="complemento" id="complemento"
                                    value="<?php echo $associadoData['complemento'] ?? ''; ?>"
                                    placeholder="Apto, Bloco, etc.">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Bairro
                                </label>
                                <input type="text" class="form-input" name="bairro" id="bairro"
                                    value="<?php echo $associadoData['bairro'] ?? ''; ?>" placeholder="Nome do bairro">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Cidade
                                </label>
                                <input type="text" class="form-input" name="cidade" id="cidade"
                                    value="<?php echo $associadoData['cidade'] ?? ''; ?>" placeholder="Nome da cidade">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card" data-step="4">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Financeiros</h2>
                            <p class="section-subtitle">Informações para cobrança e pagamentos</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <!-- NOVOS CAMPOS DE SERVIÇOS -->
                        <div class="form-group full-width">
                            <label class="form-label">
                                Tipo de Associado <span class="required">*</span>
                                <i class="fas fa-info-circle info-tooltip"
                                    title="Define o percentual de cobrança dos serviços"></i>
                            </label>
                            <select class="form-input form-select" name="tipoAssociadoServico" id="tipoAssociadoServico"
                                required onchange="calcularServicos()">
                                <option value="">Selecione o tipo de associado...</option>
                                <?php foreach ($tiposAssociado as $tipo): ?>
                                    <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo (isset($associadoData['tipoAssociadoServico']) && $associadoData['tipoAssociadoServico'] == $tipo) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="form-error">Por favor, selecione o tipo de associado</span>
                        </div>

                        <!-- Seção de Serviços -->
                        <div class="form-group full-width">
                            <div
                                style="background: var(--white); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--gray-200);">
                                <h4 style="margin-bottom: 1rem; color: var(--primary);">
                                    <i class="fas fa-clipboard-list"></i> Serviços do Associado
                                </h4>

                                <!-- Serviço Social (Obrigatório) -->
                                <div class="servico-item"
                                    style="margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div>
                                            <span style="font-weight: 600; color: var(--success);">
                                                <i class="fas fa-check-circle"></i> Serviço Social
                                            </span>
                                            <span
                                                style="background: var(--success); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; margin-left: 0.5rem;">
                                                OBRIGATÓRIO
                                            </span>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span
                                                    id="valorBaseSocial">173,10</span></div>
                                            <div style="font-weight: 700; color: var(--success);">Total: R$ <span
                                                    id="valorFinalSocial">0,00</span></div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                        Percentual aplicado: <span id="percentualSocial">0</span>%
                                        <span style="margin-left: 1rem;">Contribuição social para associados</span>
                                    </div>
                                    <input type="hidden" name="servicoSocial" value="1">
                                    <input type="hidden" name="valorSocial" id="valorSocial" value="0">
                                    <input type="hidden" name="percentualAplicadoSocial" id="percentualAplicadoSocial"
                                        value="0">
                                </div>

                                <!-- Serviço Jurídico (Opcional) -->
                                <div class="servico-item"
                                    style="margin-bottom: 1rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <input type="checkbox" name="servicoJuridico" id="servicoJuridico" value="2"
                                                onchange="calcularServicos()" style="width: 20px; height: 20px;">
                                            <label for="servicoJuridico"
                                                style="font-weight: 600; color: var(--info); cursor: pointer;">
                                                <i class="fas fa-balance-scale"></i> Serviço Jurídico
                                            </label>
                                            <span
                                                style="background: var(--info); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">
                                                OPCIONAL
                                            </span>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span
                                                    id="valorBaseJuridico">43,28</span></div>
                                            <div style="font-weight: 700; color: var(--info);">Total: R$ <span
                                                    id="valorFinalJuridico">0,00</span></div>
                                        </div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                        Percentual aplicado: <span id="percentualJuridico">0</span>%
                                        <span style="margin-left: 1rem;">Serviço jurídico opcional</span>
                                    </div>
                                    <input type="hidden" name="valorJuridico" id="valorJuridico" value="0">
                                    <input type="hidden" name="percentualAplicadoJuridico"
                                        id="percentualAplicadoJuridico" value="0">
                                </div>

                                <!-- Total Geral -->
                                <div
                                    style="padding: 1rem; background: var(--primary-light); border-radius: 8px; border: 2px solid var(--primary);">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="font-weight: 700; color: var(--primary); font-size: 1.1rem;">
                                            <i class="fas fa-calculator"></i> VALOR TOTAL MENSAL
                                        </span>
                                        <span style="font-weight: 800; color: var(--primary); font-size: 1.3rem;">
                                            R$ <span id="valorTotalGeral">0,00</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CAMPOS FINANCEIROS EXISTENTES -->
                        <div class="form-group">
                            <label class="form-label">
                                Tipo de Associado (Categoria)
                            </label>
                            <select class="form-input form-select" name="tipoAssociado" id="tipoAssociado">
                                <option value="">Selecione...</option>
                                <option value="Titular" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Titular') ? 'selected' : ''; ?>>Titular</option>
                                <option value="Pensionista" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista
                                </option>
                                <option value="Dependente" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Dependente') ? 'selected' : ''; ?>>Dependente
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Situação Financeira
                            </label>
                            <select class="form-input form-select" name="situacaoFinanceira" id="situacaoFinanceira">
                                <option value="">Selecione...</option>
                                <option value="Adimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Adimplente') ? 'selected' : ''; ?>>Adimplente
                                </option>
                                <option value="Inadimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Inadimplente') ? 'selected' : ''; ?>>
                                    Inadimplente</option>
                                <option value="Isento" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Isento') ? 'selected' : ''; ?>>Isento
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Vínculo Servidor
                            </label>
                            <select class="form-input form-select" name="vinculoServidor" id="vinculoServidor">
                                <option value="">Selecione...</option>
                                <option value="Estado" <?php echo (isset($associadoData['vinculoServidor']) && $associadoData['vinculoServidor'] == 'Estado') ? 'selected' : ''; ?>>Estado</option>
                                <option value="Federal" <?php echo (isset($associadoData['vinculoServidor']) && $associadoData['vinculoServidor'] == 'Federal') ? 'selected' : ''; ?>>Federal</option>
                                <option value="Municipal" <?php echo (isset($associadoData['vinculoServidor']) && $associadoData['vinculoServidor'] == 'Municipal') ? 'selected' : ''; ?>>Municipal
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Local de Débito
                            </label>
                            <select class="form-input form-select" name="localDebito" id="localDebito">
                                <option value="">Selecione...</option>
                                <option value="Folha de Pagamento" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Folha de Pagamento') ? 'selected' : ''; ?>>Folha de
                                    Pagamento</option>
                                <option value="Débito em Conta" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Débito em Conta') ? 'selected' : ''; ?>>Débito em
                                    Conta</option>
                                <option value="Boleto" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Boleto') ? 'selected' : ''; ?>>Boleto</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Agência
                            </label>
                            <input type="text" class="form-input" name="agencia" id="agencia"
                                value="<?php echo $associadoData['agencia'] ?? ''; ?>" placeholder="Número da agência">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Operação
                            </label>
                            <input type="text" class="form-input" name="operacao" id="operacao"
                                value="<?php echo $associadoData['operacao'] ?? ''; ?>"
                                placeholder="Código da operação">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Conta Corrente
                            </label>
                            <input type="text" class="form-input" name="contaCorrente" id="contaCorrente"
                                value="<?php echo $associadoData['contaCorrente'] ?? ''; ?>"
                                placeholder="Número da conta">
                        </div>
                    </div>
                </div>

                <!-- Step 5: Dependentes -->
                <div class="section-card" data-step="5">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dependentes</h2>
                            <p class="section-subtitle">Adicione os dependentes do associado</p>
                        </div>
                    </div>

                    <div id="dependentesContainer">
                        <?php if (isset($associadoData['dependentes']) && count($associadoData['dependentes']) > 0): ?>
                            <?php foreach ($associadoData['dependentes'] as $index => $dependente): ?>
                                <div class="dependente-card" data-index="<?php echo $index; ?>">
                                    <div class="dependente-header">
                                        <span class="dependente-number">Dependente <?php echo $index + 1; ?></span>
                                        <button type="button" class="btn-remove-dependente" onclick="removerDependente(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="form-grid">
                                        <div class="form-group full-width">
                                            <label class="form-label">Nome Completo</label>
                                            <input type="text" class="form-input"
                                                name="dependentes[<?php echo $index; ?>][nome]"
                                                value="<?php echo $dependente['nome'] ?? ''; ?>"
                                                placeholder="Nome do dependente">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Data de Nascimento</label>
                                            <input type="date" class="form-input"
                                                name="dependentes[<?php echo $index; ?>][data_nascimento]"
                                                value="<?php echo $dependente['data_nascimento'] ?? ''; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Parentesco</label>
                                            <select class="form-input form-select"
                                                name="dependentes[<?php echo $index; ?>][parentesco]">
                                                <option value="">Selecione...</option>
                                                <option value="Cônjuge" <?php echo ($dependente['parentesco'] == 'Cônjuge') ? 'selected' : ''; ?>>Cônjuge</option>
                                                <option value="Filho(a)" <?php echo ($dependente['parentesco'] == 'Filho(a)') ? 'selected' : ''; ?>>Filho(a)</option>
                                                <option value="Pai" <?php echo ($dependente['parentesco'] == 'Pai') ? 'selected' : ''; ?>>Pai</option>
                                                <option value="Mãe" <?php echo ($dependente['parentesco'] == 'Mãe') ? 'selected' : ''; ?>>Mãe</option>
                                                <option value="Irmão(ã)" <?php echo ($dependente['parentesco'] == 'Irmão(ã)') ? 'selected' : ''; ?>>Irmão(ã)</option>
                                                <option value="Outro" <?php echo ($dependente['parentesco'] == 'Outro') ? 'selected' : ''; ?>>Outro</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Sexo</label>
                                            <select class="form-input form-select"
                                                name="dependentes[<?php echo $index; ?>][sexo]">
                                                <option value="">Selecione...</option>
                                                <option value="M" <?php echo ($dependente['sexo'] == 'M') ? 'selected' : ''; ?>>
                                                    Masculino</option>
                                                <option value="F" <?php echo ($dependente['sexo'] == 'F') ? 'selected' : ''; ?>>
                                                    Feminino</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="btn-add-dependente" onclick="adicionarDependente()">
                        <i class="fas fa-plus"></i>
                        Adicionar Dependente
                    </button>
                </div>

                <!-- Step 6: Revisão -->
                <div class="section-card" data-step="6">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Revisão dos Dados</h2>
                            <p class="section-subtitle">Confira todos os dados antes de salvar</p>
                        </div>
                    </div>

                    <div id="revisaoContainer">
                        <!-- Conteúdo será preenchido dinamicamente -->
                    </div>
                </div>
            </form>

            <!-- Navigation -->
            <div class="form-navigation">
                <button type="button" class="btn-nav btn-back" id="btnVoltar" onclick="voltarStep()">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </button>

                <div>
                    <button type="button" class="btn-nav btn-back me-2" onclick="window.location.href='dashboard.php'" title="Voltar ao Dashboard sem salvar">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>

                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>

                    <button type="button" class="btn-nav btn-submit" id="btnSalvar" onclick="salvarAssociado()"
                        style="display: none;">
                        <i class="fas fa-save"></i>
                        <?php echo $isEdit ? 'Atualizar' : 'Salvar'; ?> Associado
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/pt-BR.min.js"></script>

    <!-- Scripts separados para melhor organização -->
    <script src="js/cadastroForm.js"></script>
    <script src="js/cadastroFormAutocomplete.js"></script>
</body>

</html>