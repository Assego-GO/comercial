<?php
/**
 * Formulário de Cadastro de Associados
 * pages/cadastroForm.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';





// Define o título da página
$page_title = 'Cadastrar Novo Associado - ASSEGO';

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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- jQuery Mask -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <link rel="stylesheet" href="estilizacao/autocomplete.css">
    <link rel="stylesheet" href="estilizacao/cadastro.css">
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
                <div style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                    A
                </div>
                <div>
                    <h1 class="logo-text">ASSEGO</h1>
                    <p class="system-subtitle">Sistema de Gestão</p>
                </div>
            </div>
        </div>
    </header>



    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-plus"></i>
                <?php echo $isEdit ? 'Editar Associado' : 'Cadastro online ASSEGO'; ?>
            </h1>
            <p class="page-subtitle">
                <?php echo $isEdit ? 'Atualize os dados do associado' : 'Preencha todos os campos obrigatórios para se cadastrar'; ?>
            </p>
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
                                    <input type="radio" name="sexo" id="sexo_m" value="M" required
                                           <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'M') ? 'checked' : ''; ?>>
                                    <label for="sexo_m">Masculino</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="sexo" id="sexo_f" value="F" required
                                           <?php echo (isset($associadoData['sexo']) && $associadoData['sexo'] == 'F') ? 'checked' : ''; ?>>
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
                                <option value="Solteiro(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Solteiro(a)') ? 'selected' : ''; ?>>Solteiro(a)</option>
                                <option value="Casado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Casado(a)') ? 'selected' : ''; ?>>Casado(a)</option>
                                <option value="Divorciado(a)" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Divorciado(a)') ? 'selected' : ''; ?>>Divorciado(a)</option>
                                <option value="separacao judicial" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'Separação Judicial') ? 'selected' : ''; ?>>Separação Judicial</option>
                                <option value="outro" <?php echo (isset($associadoData['estadoCivil']) && $associadoData['estadoCivil'] == 'outro') ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                RG <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="rg" id="rg" required
                                   value="<?php echo $associadoData['rg'] ?? ''; ?>"
                                   placeholder="Número do RG">
                            <span class="form-error">Por favor, insira o RG</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                CPF <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="cpf" id="cpf" required
                                   value="<?php echo $associadoData['cpf'] ?? ''; ?>"
                                   placeholder="000.000.000-00">
                            <span class="form-error">Por favor, insira um CPF válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Telefone <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="telefone" id="telefone" required
                                   value="<?php echo $associadoData['telefone'] ?? ''; ?>"
                                   placeholder="(00) 00000-0000">
                            <span class="form-error">Por favor, insira o telefone</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                E-mail
                            </label>
                            <input type="email" class="form-input" name="email" id="email"
                                   value="<?php echo $associadoData['email'] ?? ''; ?>"
                                   placeholder="email@exemplo.com">
                            <span class="form-error">Por favor, insira um e-mail válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Escolaridade
                            </label>
                            <select class="form-input form-select" name="escolaridade" id="escolaridade">
                                <option value="">Selecione...</option>
                                <option value="Fundamental Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Incompleto') ? 'selected' : ''; ?>>Fundamental Incompleto</option>
                                <option value="Fundamental Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Fundamental Completo') ? 'selected' : ''; ?>>Fundamental Completo</option>
                                <option value="Médio Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Incompleto') ? 'selected' : ''; ?>>Médio Incompleto</option>
                                <option value="Médio Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Médio Completo') ? 'selected' : ''; ?>>Médio Completo</option>
                                <option value="Superior Incompleto" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Incompleto') ? 'selected' : ''; ?>>Superior Incompleto</option>
                                <option value="Superior Completo" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Superior Completo') ? 'selected' : ''; ?>>Superior Completo</option>
                                <option value="Pós-graduação" <?php echo (isset($associadoData['escolaridade']) && $associadoData['escolaridade'] == 'Pós-graduação') ? 'selected' : ''; ?>>Pós-graduação</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Indicado por
                                <i class="fas fa-info-circle info-tooltip" title="Nome da pessoa que indicou o associado"></i>
                            </label>
                            <div class="autocomplete-container" style="position: relative;">
                                <input type="text" class="form-input" name="indicacao" id="indicacao"
                                    value="<?php echo $associadoData['indicacao'] ?? ''; ?>"
                                    placeholder="Digite o nome de quem indicou..."
                                    autocomplete="off">
                                <div id="indicacaoSuggestions" class="autocomplete-suggestions" style="
                                    position: absolute;
                                    top: 100%;
                                    left: 0;
                                    right: 0;
                                    background: var(--white);
                                    border: 2px solid var(--gray-200);
                                    border-top: none;
                                    border-radius: 0 0 12px 12px;
                                    max-height: 200px;
                                    overflow-y: auto;
                                    z-index: 1000;
                                    display: none;
                                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                                "></div>
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
                                <input type="file" name="foto" id="foto" accept="image/*" style="display: none;" required>
                                <button type="button" class="photo-upload-btn" onclick="document.getElementById('foto').click();">
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
                                <option value="pm" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Polícia Militar') ? 'selected' : ''; ?>>Polícia Militar</option>
                                <option value="bm" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Corpo de Bombeiros') ? 'selected' : ''; ?>>Corpo de Bombeiros</option>
                                <option value="pensionista" <?php echo (isset($associadoData['corporacao']) && $associadoData['corporacao'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista</option>
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
                                    <option value="3º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '3º Sargento') ? 'selected' : ''; ?>>3º Sargento</option>
                                    <option value="2º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '2º Sargento') ? 'selected' : ''; ?>>2º Sargento</option>
                                    <option value="1º Sargento" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '1º Sargento') ? 'selected' : ''; ?>>1º Sargento</option>
                                    <option value="Subtenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Subtenente') ? 'selected' : ''; ?>>Subtenente</option>
                                </optgroup>
                                <optgroup label="Oficiais">
                                    <option value="2º Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '2º Tenente') ? 'selected' : ''; ?>>2º Tenente</option>
                                    <option value="1º Tenente" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == '1º Tenente') ? 'selected' : ''; ?>>1º Tenente</option>
                                    <option value="Capitão" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Capitão') ? 'selected' : ''; ?>>Capitão</option>
                                    <option value="Major" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Major') ? 'selected' : ''; ?>>Major</option>
                                    <option value="Tenente-Coronel" <?php echo (isset($associadoData['patente']) && $associadoData['patente'] == 'Tenente-Coronel') ? 'selected' : ''; ?>>Tenente-Coronel</option>
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
                                <option value="Pensionista" <?php echo (isset($associadoData['categoria']) && $associadoData['categoria'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Lotação
                            </label>
                            <input type="text" class="form-input" name="lotacao" id="lotacao"
                                   value="<?php echo $associadoData['lotacao'] ?? ''; ?>"
                                   placeholder="Local de lotação">
                        </div>

                        <!-- NOVO CAMPO: Telefone da Lotação -->
                        <div class="form-group">
                            <label class="form-label">
                                Telefone da Lotação
                            </label>
                            <input type="text" class="form-input" name="telefoneLotacao" id="telefoneLotacao"
                                   value="<?php echo $associadoData['telefoneLotacao'] ?? ''; ?>"
                                   placeholder="(00) 0000-0000">
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
                                       value="<?php echo $associadoData['cep'] ?? ''; ?>"
                                       placeholder="00000-000">
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
                                       value="<?php echo $associadoData['numero'] ?? ''; ?>"
                                       placeholder="Nº">
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
                                       value="<?php echo $associadoData['bairro'] ?? ''; ?>"
                                       placeholder="Nome do bairro">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Cidade
                                </label>
                                <input type="text" class="form-input" name="cidade" id="cidade"
                                       value="<?php echo $associadoData['cidade'] ?? ''; ?>"
                                       placeholder="Nome da cidade">
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
                <i class="fas fa-info-circle info-tooltip" title="Define o percentual de cobrança dos serviços"></i>
            </label>
            <select class="form-input form-select" name="tipoAssociadoServico" id="tipoAssociadoServico" required onchange="calcularServicos()">
                <option value="">Selecione o tipo de associado...</option>
                <?php foreach ($tiposAssociado as $tipo): ?>
                    <option value="<?php echo htmlspecialchars($tipo); ?>"
                        <?php echo (isset($associadoData['tipoAssociadoServico']) && $associadoData['tipoAssociadoServico'] == $tipo) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tipo); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-error">Por favor, selecione o tipo de associado</span>
        </div>

        <!-- Seção de Serviços -->
        <div class="form-group full-width">
            <div style="background: var(--white); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--gray-200);">
                <h4 style="margin-bottom: 1rem; color: var(--primary);">
                    <i class="fas fa-clipboard-list"></i> Serviços do Associado
                </h4>
                
                <!-- Serviço Social (Obrigatório) -->
                <div class="servico-item" style="margin-bottom: 1.5rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <div>
                            <span style="font-weight: 600; color: var(--success);">
                                <i class="fas fa-check-circle"></i> Serviço Social
                            </span>
                            <span style="background: var(--success); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem; margin-left: 0.5rem;">
                                OBRIGATÓRIO
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span id="valorBaseSocial">173,10</span></div>
                            <div style="font-weight: 700; color: var(--success);">Total: R$ <span id="valorFinalSocial">0,00</span></div>
                        </div>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                        Percentual aplicado: <span id="percentualSocial">0</span>% 
                        <span style="margin-left: 1rem;">Contribuição social para associados</span>
                    </div>
                    <input type="hidden" name="servicoSocial" value="1">
                    <input type="hidden" name="valorSocial" id="valorSocial" value="0">
                    <input type="hidden" name="percentualAplicadoSocial" id="percentualAplicadoSocial" value="0">
                </div>

                <!-- Serviço Jurídico (Opcional) -->
                <div class="servico-item" style="margin-bottom: 1rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="servicoJuridico" id="servicoJuridico" value="2" onchange="calcularServicos()" 
                                style="width: 20px; height: 20px;">
                            <label for="servicoJuridico" style="font-weight: 600; color: var(--info); cursor: pointer;">
                                <i class="fas fa-balance-scale"></i> Serviço Jurídico
                            </label>
                            <span style="background: var(--info); color: white; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">
                                OPCIONAL
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.8rem; color: var(--gray-600);">Valor Base: R$ <span id="valorBaseJuridico">43,28</span></div>
                            <div style="font-weight: 700; color: var(--info);">Total: R$ <span id="valorFinalJuridico">0,00</span></div>
                        </div>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                        Percentual aplicado: <span id="percentualJuridico">0</span>% 
                        <span style="margin-left: 1rem;">Serviço jurídico opcional</span>
                    </div>
                    <input type="hidden" name="valorJuridico" id="valorJuridico" value="0">
                    <input type="hidden" name="percentualAplicadoJuridico" id="percentualAplicadoJuridico" value="0">
                </div>

                <!-- Total Geral -->
                <div style="padding: 1rem; background: var(--primary-light); border-radius: 8px; border: 2px solid var(--primary);">
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
                <option value="Pensionista" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Pensionista') ? 'selected' : ''; ?>>Pensionista</option>
                <option value="Dependente" <?php echo (isset($associadoData['tipoAssociado']) && $associadoData['tipoAssociado'] == 'Dependente') ? 'selected' : ''; ?>>Dependente</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">
                Situação Financeira
            </label>
            <select class="form-input form-select" name="situacaoFinanceira" id="situacaoFinanceira">
                <option value="">Selecione...</option>
                <option value="Adimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Adimplente') ? 'selected' : ''; ?>>Adimplente</option>
                <option value="Inadimplente" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Inadimplente') ? 'selected' : ''; ?>>Inadimplente</option>
                <option value="Isento" <?php echo (isset($associadoData['situacaoFinanceira']) && $associadoData['situacaoFinanceira'] == 'Isento') ? 'selected' : ''; ?>>Isento</option>
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
                <option value="Municipal" <?php echo (isset($associadoData['vinculoServidor']) && $associadoData['vinculoServidor'] == 'Municipal') ? 'selected' : ''; ?>>Municipal</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">
                Local de Débito
            </label>
            <select class="form-input form-select" name="localDebito" id="localDebito">
                <option value="">Selecione...</option>
                <option value="Folha de Pagamento" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Folha de Pagamento') ? 'selected' : ''; ?>>Folha de Pagamento</option>
                <option value="Débito em Conta" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Débito em Conta') ? 'selected' : ''; ?>>Débito em Conta</option>
                <option value="Boleto" <?php echo (isset($associadoData['localDebito']) && $associadoData['localDebito'] == 'Boleto') ? 'selected' : ''; ?>>Boleto</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">
                Agência
            </label>
            <input type="text" class="form-input" name="agencia" id="agencia"
                   value="<?php echo $associadoData['agencia'] ?? ''; ?>"
                   placeholder="Número da agência">
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
                                        <input type="text" class="form-input" name="dependentes[<?php echo $index; ?>][nome]"
                                               value="<?php echo $dependente['nome'] ?? ''; ?>"
                                               placeholder="Nome do dependente">
                                    </div>
                                    
                                    <!-- Campo Data de Nascimento (sempre visível) -->
                                    <div class="form-group campo-data-nascimento">
                                        <label class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-input" name="dependentes[<?php echo $index; ?>][data_nascimento]"
                                               value="<?php echo $dependente['data_nascimento'] ?? ''; ?>">
                                    </div>
                                    
                                    <!-- Campo Telefone (visível só para cônjuge) -->
                                    <div class="form-group campo-telefone" style="display: <?php echo ($dependente['parentesco'] == 'Cônjuge') ? 'block' : 'none'; ?>;">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" class="form-input telefone-dependente" name="dependentes[<?php echo $index; ?>][telefone]"
                                               value="<?php echo $dependente['telefone'] ?? ''; ?>"
                                               placeholder="(00) 00000-0000">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Parentesco</label>
                                        <select class="form-input form-select parentesco-select" name="dependentes[<?php echo $index; ?>][parentesco]" onchange="toggleCamposDependente(this)">
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
                                        <select class="form-input form-select" name="dependentes[<?php echo $index; ?>][sexo]">
                                            <option value="">Selecione...</option>
                                            <option value="M" <?php echo ($dependente['sexo'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="F" <?php echo ($dependente['sexo'] == 'F') ? 'selected' : ''; ?>>Feminino</option>
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
                    <button type="button" class="btn-nav btn-back me-2" onclick="window.location.href='portal.php'">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    
                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <button type="button" class="btn-nav btn-submit" id="btnSalvar" onclick="salvarAssociado()" style="display: none;">
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
    
    <script>
// JavaScript Completo com FALLBACKS - substitua o JavaScript do cadastroForm.php

const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
const associadoId = <?php echo $associadoId ? $associadoId : 'null'; ?>;

// Estado do formulário
let currentStep = 1;
const totalSteps = 6;
let dependenteIndex = <?php echo isset($associadoData['dependentes']) ? count($associadoData['dependentes']) : 0; ?>;

// Dados carregados dos serviços (para edição)
let servicosCarregados = null;

// VARIÁVEIS GLOBAIS PARA DADOS DOS SERVIÇOS
let regrasContribuicao = [];
let servicosData = [];
let tiposAssociadoData = [];
let dadosCarregados = false;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando formulário de cadastro...');
    
    // PRIMEIRA COISA: Carrega dados de serviços do banco
    carregarDadosServicos()
        .then(() => {
            console.log('✓ Dados de serviços carregados, continuando inicialização...');
            
            // Máscaras
            $('#cpf').mask('000.000.000-00');
            $('#telefone').mask('(00) 00000-0000');
            $('#cep').mask('00000-000');
            $('#telefoneLotacao').mask('(00) 0000-0000'); // Nova máscara para telefone da lotação
            
            // Máscara para telefones dos dependentes
            $(document).on('focus', '.telefone-dependente', function() {
                $(this).mask('(00) 00000-0000');
            });
            
            // Select2
            $('.form-select').select2({
                language: 'pt-BR',
                theme: 'default',
                width: '100%'
            });
            
            // Preview de foto
            document.getElementById('foto').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        showAlert('Arquivo muito grande! O tamanho máximo é 5MB.', 'error');
                        e.target.value = '';
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('photoPreview').innerHTML = 
                            `<img src="${e.target.result}" alt="Preview">`;
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Validação em tempo real
            setupRealtimeValidation();
            
            // Carrega dados dos serviços se estiver editando
            if (isEdit && associadoId) {
                carregarServicosAssociado();
            }
            
            // Inicializa campos dos dependentes existentes
            document.querySelectorAll('.parentesco-select').forEach(select => {
                toggleCamposDependente(select);
            });
            
            // Atualiza interface
            updateProgressBar();
            updateNavigationButtons();
            
        })
        .catch(error => {
            console.error('Erro ao carregar dados de serviços:', error);
            showAlert('Erro ao carregar dados do sistema. Algumas funcionalidades podem não funcionar.', 'warning');
        });
});

// NOVA FUNÇÃO: Alternar campos do dependente baseado no parentesco
function toggleCamposDependente(selectElement) {
    const dependenteCard = selectElement.closest('.dependente-card');
    if (!dependenteCard) return;
    
    const campoDataNascimento = dependenteCard.querySelector('.campo-data-nascimento');
    const campoTelefone = dependenteCard.querySelector('.campo-telefone');
    const parentesco = selectElement.value;
    
    if (parentesco === 'Cônjuge') {
        // Para cônjuge, mostra AMBOS os campos
        campoDataNascimento.style.display = 'block';
        campoTelefone.style.display = 'block';
        
    } else {
        // Para outros parentescos, mostra só data de nascimento e esconde telefone
        campoDataNascimento.style.display = 'block';
        campoTelefone.style.display = 'none';
        
        // Limpa o campo de telefone
        const inputTelefone = campoTelefone.querySelector('input');
        if (inputTelefone) inputTelefone.value = '';
    }
}

// FUNÇÃO CORRIGIDA: Carrega dados de serviços via AJAX
function carregarDadosServicos() {
    console.log('=== CARREGANDO DADOS DE SERVIÇOS DO BANCO ===');
    
    return fetch('../api/buscar_dados_servicos.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Resposta da API de serviços:', data);
            
            if (data.status === 'success') {
                // CARREGA DADOS REAIS DO BANCO
                regrasContribuicao = data.regras || [];
                servicosData = data.servicos || [];
                tiposAssociadoData = data.tipos_associado || [];
                dadosCarregados = true;
                
                console.log('✓ Dados carregados do banco:');
                console.log('- Serviços:', servicosData.length);
                console.log('- Regras:', regrasContribuicao.length);
                console.log('- Tipos:', tiposAssociadoData.length);
                
                // Preenche o select de tipos de associado
                preencherSelectTiposAssociado();
                
                return true;
                
            } else {
                console.error('API retornou erro:', data.message);
                throw new Error(data.message || 'Erro na API');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados de serviços:', error);
            
            // FALLBACK: Usa dados básicos se falhar
            console.warn('Usando dados de fallback básicos...');
            
            servicosData = [
                {id: "1", nome: "Social", valor_base: "173.10", obrigatorio: true},
                {id: "2", nome: "Jurídico", valor_base: "43.28", obrigatorio: false}
            ];
            
            regrasContribuicao = [
                {tipo_associado: "Contribuinte", servico_id: "1", percentual_valor: "100.00"},
                {tipo_associado: "Contribuinte", servico_id: "2", percentual_valor: "100.00"},
                {tipo_associado: "Aluno", servico_id: "1", percentual_valor: "50.00"},
                {tipo_associado: "Aluno", servico_id: "2", percentual_valor: "100.00"},
                {tipo_associado: "Soldado 2ª Classe", servico_id: "1", percentual_valor: "50.00"},
                {tipo_associado: "Soldado 2ª Classe", servico_id: "2", percentual_valor: "100.00"},
                {tipo_associado: "Soldado 1ª Classe", servico_id: "1", percentual_valor: "100.00"},
                {tipo_associado: "Soldado 1ª Classe", servico_id: "2", percentual_valor: "100.00"},
                {tipo_associado: "Agregado", servico_id: "1", percentual_valor: "50.00"},
                {tipo_associado: "Agregado", servico_id: "2", percentual_valor: "100.00"},
                {tipo_associado: "Remido", servico_id: "1", percentual_valor: "0.00"},
                {tipo_associado: "Remido", servico_id: "2", percentual_valor: "100.00"},
                {tipo_associado: "Remido 50%", servico_id: "1", percentual_valor: "50.00"},
                {tipo_associado: "Remido 50%", servico_id: "2", percentual_valor: "100.00"},
                {tipo_associado: "Benemerito", servico_id: "1", percentual_valor: "0.00"},
                {tipo_associado: "Benemerito", servico_id: "2", percentual_valor: "100.00"}
            ];
            
            tiposAssociadoData = ["Contribuinte", "Aluno", "Soldado 2ª Classe", "Soldado 1ª Classe", "Agregado", "Remido 50%", "Remido", "Benemerito"];
            
            dadosCarregados = true;
            preencherSelectTiposAssociado();
            
            console.log('✓ Dados de fallback carregados');
            return true;
        });
}


function preencherSelectTiposAssociado() {
    const select = document.getElementById('tipoAssociadoServico');
    if (!select) {
        console.warn('Select tipoAssociadoServico não encontrado');
        return;
    }
    
    // Limpa opções existentes (exceto a primeira)
    while (select.children.length > 1) {
        select.removeChild(select.lastChild);
    }
    
    // Adiciona tipos do banco
    tiposAssociadoData.forEach(tipo => {
        const option = document.createElement('option');
        option.value = tipo;
        option.textContent = tipo;
        select.appendChild(option);
    });
    
    console.log(`✓ Select preenchido com ${tiposAssociadoData.length} tipos de associado`);
    
    // Atualiza Select2 se estiver inicializado
    if (typeof $ !== 'undefined' && $('#tipoAssociadoServico').hasClass('select2-hidden-accessible')) {
        $('#tipoAssociadoServico').trigger('change');
    }
}

// Dados hardcoded como último recurso
function useHardcodedData() {
    console.warn('Usando dados hardcoded como fallback');
    
    servicosData = [
        {id: "1", nome: "Social", valor_base: "173.10"},
        {id: "2", nome: "Jurídico", valor_base: "43.28"}
    ];
    
    regrasContribuicao = [
        {tipo_associado: "Contribuente", servico_id: "1", percentual_valor: "100.00"},
        {tipo_associado: "Contribuente", servico_id: "2", percentual_valor: "100.00"},
        {tipo_associado: "Aluno", servico_id: "1", percentual_valor: "50.00"},
        {tipo_associado: "Aluno", servico_id: "2", percentual_valor: "100.00"},
        {tipo_associado: "Soldado 2ª Classe", servico_id: "1", percentual_valor: "50.00"},
        {tipo_associado: "Soldado 2ª Classe", servico_id: "2", percentual_valor: "100.00"},
        {tipo_associado: "Soldado 1ª Classe", servico_id: "1", percentual_valor: "100.00"},
        {tipo_associado: "Soldado 1ª Classe", servico_id: "2", percentual_valor: "100.00"},
        {tipo_associado: "Agregado", servico_id: "1", percentual_valor: "50.00"},
        {tipo_associado: "Agregado", servico_id: "2", percentual_valor: "100.00"},
        {tipo_associado: "Remido", servico_id: "1", percentual_valor: "0.00"},
        {tipo_associado: "Remido", servico_id: "2", percentual_valor: "100.00"},
        {tipo_associado: "Remido 50%", servico_id: "1", percentual_valor: "50.00"},
        {tipo_associado: "Remido 50%", servico_id: "2", percentual_valor: "100.00"},
        {tipo_associado: "Benemerito", servico_id: "1", percentual_valor: "0.00"},
        {tipo_associado: "Benemerito", servico_id: "2", percentual_valor: "100.00"}
    ];
    
    console.log('Dados hardcoded definidos:', {regrasContribuicao, servicosData});
}

// Função para carregar serviços do associado (modo edição)
function carregarServicosAssociado() {
    console.log('Carregando serviços do associado...');
    
    fetch(`../api/buscar_servicos_associado.php?associado_id=${associadoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                servicosCarregados = data.data;
                preencherDadosServicos(data.data);
                console.log('Serviços carregados:', data.data);
            } else {
                console.warn('Nenhum serviço encontrado para este associado');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar serviços:', error);
        });
}

function preencherDadosServicos(dadosServicos) {
    console.log('=== PREENCHENDO DADOS DOS SERVIÇOS (CORRIGIDO) ===');
    console.log('Dados recebidos:', dadosServicos);
    
    // Limpa valores anteriores primeiro
    resetarCalculos();
    
    // Define o tipo de associado
    if (dadosServicos.tipo_associado_servico) {
        const selectElement = document.getElementById('tipoAssociadoServico');
        if (selectElement) {
            selectElement.value = dadosServicos.tipo_associado_servico;
            
            // Trigger change para atualizar Select2
            if (typeof $ !== 'undefined' && $('#tipoAssociadoServico').length) {
                $('#tipoAssociadoServico').trigger('change');
            }
            
            console.log('✓ Tipo de associado definido:', dadosServicos.tipo_associado_servico);
        }
    }
    
    // Preenche dados do serviço social
    if (dadosServicos.servicos && dadosServicos.servicos.social) {
        const social = dadosServicos.servicos.social;
        
        updateElementSafe('valorSocial', social.valor_aplicado, 'value');
        updateElementSafe('percentualAplicadoSocial', social.percentual_aplicado, 'value');
        updateElementSafe('valorFinalSocial', parseFloat(social.valor_aplicado).toFixed(2));
        updateElementSafe('percentualSocial', parseFloat(social.percentual_aplicado).toFixed(0));
        
        // Busca valor base para exibição
        const servicoSocial = servicosData.find(s => s.id == 1);
        if (servicoSocial) {
            updateElementSafe('valorBaseSocial', parseFloat(servicoSocial.valor_base).toFixed(2));
        }
        
        console.log('✓ Serviço Social preenchido:', social);
    }
    
    // Preenche dados do serviço jurídico se existir
    if (dadosServicos.servicos && dadosServicos.servicos.juridico) {
        const juridico = dadosServicos.servicos.juridico;
        
        // Marca o checkbox
        const juridicoCheckEl = document.getElementById('servicoJuridico');
        if (juridicoCheckEl) {
            juridicoCheckEl.checked = true;
        }
        
        updateElementSafe('valorJuridico', juridico.valor_aplicado, 'value');
        updateElementSafe('percentualAplicadoJuridico', juridico.percentual_aplicado, 'value');
        updateElementSafe('valorFinalJuridico', parseFloat(juridico.valor_aplicado).toFixed(2));
        updateElementSafe('percentualJuridico', parseFloat(juridico.percentual_aplicado).toFixed(0));
        
        // Busca valor base para exibição
        const servicoJuridico = servicosData.find(s => s.id == 2);
        if (servicoJuridico) {
            updateElementSafe('valorBaseJuridico', parseFloat(servicoJuridico.valor_base).toFixed(2));
        }
        
        console.log('✓ Serviço Jurídico preenchido:', juridico);
    } else {
        // Garante que o checkbox está desmarcado
        const juridicoCheckEl = document.getElementById('servicoJuridico');
        if (juridicoCheckEl) {
            juridicoCheckEl.checked = false;
        }
        console.log('✓ Serviço Jurídico não contratado');
    }
    
    // Atualiza total geral
    const totalMensal = dadosServicos.valor_total_mensal || 0;
    updateElementSafe('valorTotalGeral', parseFloat(totalMensal).toFixed(2));
    
    console.log('✓ Total mensal:', totalMensal);
    console.log('=== FIM PREENCHIMENTO ===');
}

// Navegação entre steps
function proximoStep() {
    if (validarStepAtual()) {
        if (currentStep < totalSteps) {
            // Marca step atual como completo
            document.querySelector(`[data-step="${currentStep}"]`).classList.add('completed');
            
            currentStep++;
            mostrarStep(currentStep);
            
            // Se for o último step, preenche a revisão
            if (currentStep === totalSteps) {
                preencherRevisao();
            }
        }
    }
}

function voltarStep() {
    if (currentStep > 1) {
        currentStep--;
        mostrarStep(currentStep);
    }
}

function mostrarStep(step) {
    // Esconde todos os cards
    document.querySelectorAll('.section-card').forEach(card => {
        card.classList.remove('active');
    });
    
    // Mostra o card atual
    document.querySelector(`.section-card[data-step="${step}"]`).classList.add('active');
    
    // Atualiza progress
    updateProgressBar();
    updateNavigationButtons();
    
    // Scroll para o topo
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Atualiza barra de progresso
function updateProgressBar() {
    const progressLine = document.getElementById('progressLine');
    const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
    progressLine.style.width = progressPercent + '%';
    
    // Atualiza steps
    document.querySelectorAll('.step').forEach((step, index) => {
        const stepNumber = index + 1;
        
        if (stepNumber === currentStep) {
            step.classList.add('active');
            step.classList.remove('completed');
        } else if (stepNumber < currentStep) {
            step.classList.remove('active');
            step.classList.add('completed');
        } else {
            step.classList.remove('active', 'completed');
        }
    });
}

// Atualiza botões de navegação
function updateNavigationButtons() {
    const btnVoltar = document.getElementById('btnVoltar');
    const btnProximo = document.getElementById('btnProximo');
    const btnSalvar = document.getElementById('btnSalvar');
    
    // Botão voltar
    btnVoltar.style.display = currentStep === 1 ? 'none' : 'flex';
    
    // Botões próximo/salvar
    if (currentStep === totalSteps) {
        btnProximo.style.display = 'none';
        btnSalvar.style.display = 'flex';
    } else {
        btnProximo.style.display = 'flex';
        btnSalvar.style.display = 'none';
    }
}

// Validação do step atual
function validarStepAtual() {
    const stepCard = document.querySelector(`.section-card[data-step="${currentStep}"]`);
    const requiredFields = stepCard.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Validações específicas
    if (currentStep === 1) {
        // Valida CPF
        const cpfField = document.getElementById('cpf');
        if (cpfField && cpfField.value && !validarCPF(cpfField.value)) {
            cpfField.classList.add('error');
            isValid = false;
            showAlert('CPF inválido!', 'error');
        }

        const fotoField = document.getElementById('foto');
        if (!fotoField.files || fotoField.files.length === 0) {
            showAlert('Por favor, adicione uma foto do associado!', 'error');
            isValid = false;
        }
        
        // Valida email
        const emailField = document.getElementById('email');
        if (emailField && emailField.value && !validarEmail(emailField.value)) {
            emailField.classList.add('error');
            isValid = false;
            showAlert('E-mail inválido!', 'error');
        }
    }
    
    // CORREÇÃO: Validação do step financeiro (4) - ACEITA VALOR 0
    if (currentStep === 4) {
        const tipoAssociadoServico = document.getElementById('tipoAssociadoServico');
        const valorSocial = document.getElementById('valorSocial');
        
        if (tipoAssociadoServico && !tipoAssociadoServico.value) {
            tipoAssociadoServico.classList.add('error');
            isValid = false;
            showAlert('Por favor, selecione o tipo de associado para os serviços!', 'error');
        }
        
        // CORREÇÃO: Aceita valor 0 para isentos, só não aceita vazio
        if (valorSocial && valorSocial.value === '') {
            isValid = false;
            showAlert('Erro no cálculo dos serviços. Verifique o tipo de associado selecionado!', 'error');
        }
    }
    
    if (!isValid) {
        showAlert('Por favor, preencha todos os campos obrigatórios!', 'warning');
    }
    
    return isValid;
}

console.log('✓ Funções JavaScript corrigidas carregadas!');

// Validação em tempo real
function setupRealtimeValidation() {
    // Remove classe de erro ao digitar
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('error');
            }
        });
    });
    
    // Validação específica de CPF
    const cpfField = document.getElementById('cpf');
    if (cpfField) {
        cpfField.addEventListener('blur', function() {
            if (this.value && !validarCPF(this.value)) {
                this.classList.add('error');
                showAlert('CPF inválido!', 'error');
            }
        });
    }
    
    // Validação específica de email
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.addEventListener('blur', function() {
            if (this.value && !validarEmail(this.value)) {
                this.classList.add('error');
                showAlert('E-mail inválido!', 'error');
            }
        });
    }
}

// Funções de validação
function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]/g, '');
    
    if (cpf.length !== 11) return false;
    
    // Verifica sequências inválidas
    if (/^(\d)\1{10}$/.test(cpf)) return false;
    
    // Validação do dígito verificador
    let soma = 0;
    let resto;
    
    for (let i = 1; i <= 9; i++) {
        soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    }
    
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.substring(9, 10))) return false;
    
    soma = 0;
    for (let i = 1; i <= 10; i++) {
        soma += parseInt(cpf.substring(i - 1, i)) * (12 - i);
    }
    
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.substring(10, 11))) return false;
    
    return true;
}

function validarEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Buscar CEP
function buscarCEP() {
    const cepField = document.getElementById('cep');
    if (!cepField) return;
    
    const cep = cepField.value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        showAlert('CEP inválido!', 'error');
        return;
    }
    
    showLoading();
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.erro) {
                showAlert('CEP não encontrado!', 'error');
                return;
            }
            
            const enderecoField = document.getElementById('endereco');
            const bairroField = document.getElementById('bairro');
            const cidadeField = document.getElementById('cidade');
            const numeroField = document.getElementById('numero');
            
            if (enderecoField) enderecoField.value = data.logradouro;
            if (bairroField) bairroField.value = data.bairro;
            if (cidadeField) cidadeField.value = data.localidade;
            
            // Foca no campo número
            if (numeroField) numeroField.focus();
        })
        .catch(error => {
            hideLoading();
            console.error('Erro ao buscar CEP:', error);
            showAlert('Erro ao buscar CEP!', 'error');
        });
}

// Gerenciar dependentes - FUNÇÃO MODIFICADA
function adicionarDependente() {
    const container = document.getElementById('dependentesContainer');
    if (!container) return;
    
    const novoIndex = dependenteIndex++;
    
    const dependenteHtml = `
        <div class="dependente-card" data-index="${novoIndex}" style="animation: fadeInUp 0.3s ease;">
            <div class="dependente-header">
                <span class="dependente-number">Dependente ${novoIndex + 1}</span>
                <button type="button" class="btn-remove-dependente" onclick="removerDependente(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label class="form-label">Nome Completo</label>
                    <input type="text" class="form-input" name="dependentes[${novoIndex}][nome]" 
                           placeholder="Nome do dependente">
                </div>
                
                <!-- Campo Data de Nascimento (sempre visível) -->
                <div class="form-group campo-data-nascimento">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-input" name="dependentes[${novoIndex}][data_nascimento]">
                </div>
                
                <!-- Campo Telefone (visível só para cônjuge) -->
                <div class="form-group campo-telefone" style="display: none;">
                    <label class="form-label">Telefone</label>
                    <input type="text" class="form-input telefone-dependente" name="dependentes[${novoIndex}][telefone]"
                           placeholder="(00) 00000-0000">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Parentesco</label>
                    <select class="form-input form-select parentesco-select" name="dependentes[${novoIndex}][parentesco]" onchange="toggleCamposDependente(this)">
                        <option value="">Selecione...</option>
                        <option value="Cônjuge">Cônjuge</option>
                        <option value="Filho(a)">Filho(a)</option>
                        <option value="Pai">Pai</option>
                        <option value="Mãe">Mãe</option>
                        <option value="Irmão(ã)">Irmão(ã)</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sexo</label>
                    <select class="form-input form-select" name="dependentes[${novoIndex}][sexo]">
                        <option value="">Selecione...</option>
                        <option value="M">Masculino</option>
                        <option value="F">Feminino</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', dependenteHtml);
    
    // Inicializa Select2 nos novos selects
    $(`[data-index="${novoIndex}"] .form-select`).select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%'
    });
    
    // Aplica máscara no novo campo de telefone
    $(`[data-index="${novoIndex}"] .telefone-dependente`).mask('(00) 00000-0000');
}

function removerDependente(button) {
    const card = button.closest('.dependente-card');
    if (!card) return;
    
    card.style.animation = 'fadeOut 0.3s ease';
    
    setTimeout(() => {
        card.remove();
        // Reordena os números
        document.querySelectorAll('.dependente-card').forEach((card, index) => {
            const numberEl = card.querySelector('.dependente-number');
            if (numberEl) {
                numberEl.textContent = `Dependente ${index + 1}`;
            }
        });
    }, 300);
}

function calcularServicos() {
    console.log('=== CALCULANDO SERVIÇOS (USANDO DADOS DO BANCO) ===');
    
    if (!dadosCarregados) {
        console.warn('Dados ainda não carregados, aguardando...');
        setTimeout(calcularServicos, 500);
        return;
    }
    
    const tipoAssociadoEl = document.getElementById('tipoAssociadoServico');
    const servicoJuridicoEl = document.getElementById('servicoJuridico');
    
    if (!tipoAssociadoEl || !servicoJuridicoEl) {
        console.error('Elementos não encontrados');
        return;
    }
    
    const tipoAssociado = tipoAssociadoEl.value;
    const servicoJuridicoChecked = servicoJuridicoEl.checked;
    
    console.log('Calculando para:', {tipoAssociado, servicoJuridicoChecked});
    
    if (!tipoAssociado) {
        resetarCalculos();
        return;
    }
    
    // Buscar regras para o tipo de associado selecionado USANDO DADOS DO BANCO
    const regrasSocial = regrasContribuicao.filter(r => 
        r.tipo_associado === tipoAssociado && r.servico_id == 1
    );
    const regrasJuridico = regrasContribuicao.filter(r => 
        r.tipo_associado === tipoAssociado && r.servico_id == 2
    );
    
    console.log('Regras encontradas no banco:', {regrasSocial, regrasJuridico});
    
    let valorTotalGeral = 0;
    
    // Calcular Serviço Social (sempre obrigatório)
    if (regrasSocial.length > 0) {
        const regra = regrasSocial[0];
        const servicoSocial = servicosData.find(s => s.id == 1);
        const valorBase = parseFloat(servicoSocial?.valor_base || 173.10);
        const percentual = parseFloat(regra.percentual_valor);
        const valorFinal = (valorBase * percentual) / 100;
        
        console.log('Cálculo Social (do banco):', {valorBase, percentual, valorFinal});
        
        // Atualiza elementos do DOM
        updateElementSafe('valorBaseSocial', valorBase.toFixed(2));
        updateElementSafe('percentualSocial', percentual.toFixed(0));
        updateElementSafe('valorFinalSocial', valorFinal.toFixed(2));
        updateElementSafe('valorSocial', valorFinal.toFixed(2), 'value');
        updateElementSafe('percentualAplicadoSocial', percentual.toFixed(2), 'value');
        
        valorTotalGeral += valorFinal;
    }
    
    // Calcular Serviço Jurídico (se selecionado)
    if (servicoJuridicoChecked && regrasJuridico.length > 0) {
        const regra = regrasJuridico[0];
        const servicoJuridico = servicosData.find(s => s.id == 2);
        const valorBase = parseFloat(servicoJuridico?.valor_base || 43.28);
        const percentual = parseFloat(regra.percentual_valor);
        const valorFinal = (valorBase * percentual) / 100;
        
        console.log('Cálculo Jurídico (do banco):', {valorBase, percentual, valorFinal});
        
        updateElementSafe('valorBaseJuridico', valorBase.toFixed(2));
        updateElementSafe('percentualJuridico', percentual.toFixed(0));
        updateElementSafe('valorFinalJuridico', valorFinal.toFixed(2));
        updateElementSafe('valorJuridico', valorFinal.toFixed(2), 'value');
        updateElementSafe('percentualAplicadoJuridico', percentual.toFixed(2), 'value');
        
        valorTotalGeral += valorFinal;
    } else {
        // Reset jurídico se não selecionado
        updateElementSafe('percentualJuridico', '0');
        updateElementSafe('valorFinalJuridico', '0,00');
        updateElementSafe('valorJuridico', '0', 'value');
        updateElementSafe('percentualAplicadoJuridico', '0', 'value');
    }
    
    // Atualizar total geral
    updateElementSafe('valorTotalGeral', valorTotalGeral.toFixed(2));
    
    console.log('Valor total calculado (do banco):', valorTotalGeral);
    console.log('=== FIM CÁLCULO SERVIÇOS ===');
}

// Função auxiliar para atualizar elementos com segurança
function updateElementSafe(elementId, value, property = 'textContent') {
    const element = document.getElementById(elementId);
    if (element) {
        if (property === 'value') {
            element.value = value;
        } else {
            element[property] = value;
        }
    } else {
        console.warn(`Elemento ${elementId} não encontrado`);
    }
}

// Função para preencher dados dos serviços no formulário - VERSÃO CORRIGIDA
function preencherDadosServicos(dadosServicos) {
    console.log('=== PREENCHENDO DADOS DOS SERVIÇOS ===');
    console.log('Dados recebidos:', dadosServicos);
    
    // Limpa valores anteriores primeiro
    resetarCalculos();
    
    // Define o tipo de associado
    if (dadosServicos.tipo_associado_servico) {
        const selectElement = document.getElementById('tipoAssociadoServico');
        if (selectElement) {
            selectElement.value = dadosServicos.tipo_associado_servico;
            
            // Trigger change para atualizar Select2
            if (typeof $ !== 'undefined' && $('#tipoAssociadoServico').length) {
                $('#tipoAssociadoServico').trigger('change');
            }
            
            console.log('✓ Tipo de associado definido:', dadosServicos.tipo_associado_servico);
        }
    }
    
    // Preenche dados do serviço social
    if (dadosServicos.servicos && dadosServicos.servicos.social) {
        const social = dadosServicos.servicos.social;
        
        updateElementSafe('valorSocial', social.valor_aplicado, 'value');
        updateElementSafe('percentualAplicadoSocial', social.percentual_aplicado, 'value');
        updateElementSafe('valorFinalSocial', parseFloat(social.valor_aplicado).toFixed(2));
        updateElementSafe('percentualSocial', parseFloat(social.percentual_aplicado).toFixed(0));
        
        // Busca valor base para exibição
        const servicoSocial = servicosData.find(s => s.id == 1);
        if (servicoSocial) {
            updateElementSafe('valorBaseSocial', parseFloat(servicoSocial.valor_base).toFixed(2));
        }
        
        console.log('✓ Serviço Social preenchido:', social);
    }
    
    // Preenche dados do serviço jurídico se existir
    if (dadosServicos.servicos && dadosServicos.servicos.juridico) {
        const juridico = dadosServicos.servicos.juridico;
        
        // Marca o checkbox
        const juridicoCheckEl = document.getElementById('servicoJuridico');
        if (juridicoCheckEl) {
            juridicoCheckEl.checked = true;
        }
        
        updateElementSafe('valorJuridico', juridico.valor_aplicado, 'value');
        updateElementSafe('percentualAplicadoJuridico', juridico.percentual_aplicado, 'value');
        updateElementSafe('valorFinalJuridico', parseFloat(juridico.valor_aplicado).toFixed(2));
        updateElementSafe('percentualJuridico', parseFloat(juridico.percentual_aplicado).toFixed(0));
        
        // Busca valor base para exibição
        const servicoJuridico = servicosData.find(s => s.id == 2);
        if (servicoJuridico) {
            updateElementSafe('valorBaseJuridico', parseFloat(servicoJuridico.valor_base).toFixed(2));
        }
        
        console.log('✓ Serviço Jurídico preenchido:', juridico);
    } else {
        // Garante que o checkbox está desmarcado
        const juridicoCheckEl = document.getElementById('servicoJuridico');
        if (juridicoCheckEl) {
            juridicoCheckEl.checked = false;
        }
        console.log('✓ Serviço Jurídico não contratado');
    }
    
    // Atualiza total geral
    const totalMensal = dadosServicos.valor_total_mensal || 0;
    updateElementSafe('valorTotalGeral', parseFloat(totalMensal).toFixed(2));
    
    console.log('✓ Total mensal:', totalMensal);
    console.log('=== FIM PREENCHIMENTO ===');
}

// Função para carregar serviços do associado (modo edição) - VERSÃO CORRIGIDA
function carregarServicosAssociado() {
    if (!associadoId) {
        console.log('Não é modo edição, pulando carregamento de serviços');
        return;
    }
    
    console.log('=== CARREGANDO SERVIÇOS DO ASSOCIADO ===');
    console.log('ID do associado:', associadoId);
    
    // Mostra loading
    const loadingHtml = `
        <div style="text-align: center; padding: 1rem; color: var(--gray-500);">
            <i class="fas fa-spinner fa-spin"></i> Carregando serviços...
        </div>
    `;
    
    const servicosContainer = document.querySelector('[data-step="4"] .form-group.full-width');
    if (servicosContainer) {
        const serviceDiv = servicosContainer.querySelector('div[style*="background: var(--white)"]');
        if (serviceDiv) {
            serviceDiv.innerHTML = loadingHtml;
        }
    }
    
    fetch(`../api/buscar_servicos_associado.php?associado_id=${associadoId}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Resposta da API:', data);
            
            if (data.status === 'success' && data.data) {
                servicosCarregados = data.data;
                preencherDadosServicos(data.data);
                
                // Restaura o HTML original dos serviços
                restaurarHtmlServicos();
                
                console.log('✓ Serviços carregados e preenchidos com sucesso');
            } else {
                console.warn('API retornou erro:', data.message || 'Erro desconhecido');
                // Mesmo assim, tenta calcular com os dados atuais
                setTimeout(() => {
                    if (document.getElementById('tipoAssociadoServico').value) {
                        calcularServicos();
                    }
                }, 500);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar serviços:', error);
            
            // Restaura o HTML e tenta calcular
            restaurarHtmlServicos();
            
            setTimeout(() => {
                if (document.getElementById('tipoAssociadoServico').value) {
                    calcularServicos();
                }
            }, 500);
        });
}

// Função para restaurar HTML dos serviços
function restaurarHtmlServicos() {
    const servicosContainer = document.querySelector('[data-step="4"] .form-group.full-width');
    if (servicosContainer) {
        const serviceDiv = servicosContainer.querySelector('div[style*="background: var(--white)"]');
        if (serviceDiv) {
            // Aqui você pode colocar o HTML original dos serviços
            // Por simplicidade, vou apenas remover o loading
            if (serviceDiv.innerHTML.includes('fa-spinner')) {
                location.reload(); // Recarrega para restaurar estado original
            }
        }
    }
}

function salvarAssociado() {
    console.log('=== SALVANDO ASSOCIADO (VERSÃO CORRIGIDA) ===');
    
    // Validação final
    if (!validarFormularioCompleto()) {
        showAlert('Por favor, verifique todos os campos obrigatórios!', 'error');
        return;
    }
    
    showLoading();
    
    const formData = new FormData(document.getElementById('formAssociado'));
    
    // CORREÇÃO: Garante que o tipo de associado seja enviado corretamente
    const tipoAssociadoEl = document.getElementById('tipoAssociadoServico');
    if (tipoAssociadoEl && tipoAssociadoEl.value) {
        formData.set('tipoAssociadoServico', tipoAssociadoEl.value);
        console.log('✓ Tipo de associado adicionado:', tipoAssociadoEl.value);
    }
    
    // CORREÇÃO: Garante que os valores dos serviços sejam enviados mesmo sendo 0
    const valorSocialEl = document.getElementById('valorSocial');
    const valorJuridicoEl = document.getElementById('valorJuridico');
    const percentualSocialEl = document.getElementById('percentualAplicadoSocial');
    const percentualJuridicoEl = document.getElementById('percentualAplicadoJuridico');
    
    if (valorSocialEl) {
        formData.set('valorSocial', valorSocialEl.value || '0');
        console.log('✓ Valor Social:', valorSocialEl.value || '0');
    }
    
    if (percentualSocialEl) {
        formData.set('percentualAplicadoSocial', percentualSocialEl.value || '0');
        console.log('✓ Percentual Social:', percentualSocialEl.value || '0');
    }
    
    if (valorJuridicoEl) {
        formData.set('valorJuridico', valorJuridicoEl.value || '0');
        console.log('✓ Valor Jurídico:', valorJuridicoEl.value || '0');
    }
    
    if (percentualJuridicoEl) {
        formData.set('percentualAplicadoJuridico', percentualJuridicoEl.value || '0');
        console.log('✓ Percentual Jurídico:', percentualJuridicoEl.value || '0');
    }
    
    // Log dos dados que estão sendo enviados
    console.log('Dados sendo enviados:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    // URL da API
    const url = isEdit 
        ? `../api/atualizar_associado.php?id=${associadoId}`
        : '../api/criar_associado.php';
    
    console.log('URL da requisição:', url);
    console.log('Método:', isEdit ? 'ATUALIZAR' : 'CRIAR');
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Content-Type:', response.headers.get('content-type'));
        
        return response.text();
    })
    .then(responseText => {
        console.log('Response texto:', responseText);
        
        hideLoading();
        
        let data;
        try {
            data = JSON.parse(responseText);
            console.log('JSON parseado:', data);
        } catch (e) {
            console.error('Erro ao fazer parse JSON:', e);
            console.log('Resposta não é JSON válido');
            
            if (responseText.includes('<html>') || responseText.includes('<!DOCTYPE')) {
                showAlert('Erro no servidor - retornou HTML ao invés de JSON. Verifique os logs.', 'error');
            } else {
                showAlert('Erro de comunicação: ' + responseText.substring(0, 200), 'error');
            }
            return;
        }
        
        if (data.status === 'success') {
            let mensagem = isEdit ? 'Associado atualizado com sucesso!' : 'Associado cadastrado com sucesso!';
            
            // Adiciona informações sobre serviços se houver
            if (data.data && data.data.servicos_alterados && data.data.detalhes_servicos) {
                mensagem += '\n\nServiços atualizados:\n' + data.data.detalhes_servicos.join('\n');
            }
            
            // CORREÇÃO: Mostra o tipo de associado salvo
            if (data.data && data.data.tipo_associado_servico) {
                mensagem += '\n\nTipo de associado: ' + data.data.tipo_associado_servico;
            }
            
            showAlert(mensagem, 'success');
            
            console.log('✓ Sucesso:', data);
            
        } else {
            console.error('Erro da API:', data);
            showAlert(data.message || 'Erro ao salvar associado!', 'error');
            
            if (data.debug) {
                console.log('Debug info:', data.debug);
            }
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro de rede:', error);
        showAlert('Erro de comunicação com o servidor!', 'error');
    });
    
    console.log('=== FIM SALVAMENTO ===');
}

// Garanta que esta função seja chamada no carregamento da página se for edição
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM carregado - Modo edição:', isEdit);
    
    if (isEdit && associadoId) {
        // Aguarda um pouco para garantir que tudo está carregado
        setTimeout(() => {
            carregarServicosAssociado();
        }, 1000);
    }
    
    // Adiciona listeners aos campos de serviços
    const tipoAssociadoEl = document.getElementById('tipoAssociadoServico');
    const servicoJuridicoEl = document.getElementById('servicoJuridico');
    
    if (tipoAssociadoEl) {
        tipoAssociadoEl.addEventListener('change', calcularServicos);
    }
    
    if (servicoJuridicoEl) {
        servicoJuridicoEl.addEventListener('change', calcularServicos);
    }
});

// Preencher revisão (versão simplificada por enquanto)
function preencherRevisao() {
    const container = document.getElementById('revisaoContainer');
    if (!container) return;
    
    const formData = new FormData(document.getElementById('formAssociado'));
    
    let html = `
        <div class="overview-card">
            <div class="overview-card-header">
                <div class="overview-card-icon blue">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="overview-card-title">Resumo do Cadastro</h3>
            </div>
            <div class="overview-card-content">
                <div class="overview-item">
                    <span class="overview-label">Nome</span>
                    <span class="overview-value">${formData.get('nome') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">CPF</span>
                    <span class="overview-value">${formData.get('cpf') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">Tipo de Associado</span>
                    <span class="overview-value">${formData.get('tipoAssociadoServico') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">Valor Total Mensal</span>
                    <span class="overview-value">R$ ${document.getElementById('valorTotalGeral')?.textContent || '0,00'}</span>
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}



// Função adicional para testar conexão
function testarConexao() {
    console.log('Testando conexão...');
    
    fetch('../api/criar_associado.php', {
        method: 'POST',
        body: new FormData() // FormData vazio só para testar
    })
    .then(response => response.text())
    .then(text => {
        console.log('Teste de conexão - resposta:');
        console.log(text);
    })
    .catch(error => {
        console.error('Erro no teste de conexão:', error);
    });
}

// Execute o teste quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    // Descomente a linha abaixo para testar a conexão
    // testarConexao();
});

// Validação do formulário completo
function validarFormularioCompleto() {
    const form = document.getElementById('formAssociado');
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
            
            // Encontra em qual step está o campo
            const stepCard = field.closest('.section-card');
            if (stepCard) {
                const step = stepCard.getAttribute('data-step');
                console.log(`Campo obrigatório vazio no step ${step}: ${field.name}`);
            }
        }
    });
    
    return isValid;
}

// Funções auxiliares
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('active');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.remove('active');
}

function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        console.log('Alert:', message);
        return;
    }
    
    const alertId = 'alert-' + Date.now();
    
    const alertHtml = `
        <div id="${alertId}" class="alert-custom alert-${type}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML('beforeend', alertHtml);
    
    // Remove após 5 segundos
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }
    }, 5000);
}


function resetarCalculos() {
    console.log('Resetando cálculos...');
    
    // Valores base dos serviços vindos do banco
    const servicoSocial = servicosData.find(s => s.id == 1);
    const servicoJuridico = servicosData.find(s => s.id == 2);
    
    const valorBaseSocial = servicoSocial ? parseFloat(servicoSocial.valor_base).toFixed(2) : '173,10';
    const valorBaseJuridico = servicoJuridico ? parseFloat(servicoJuridico.valor_base).toFixed(2) : '43,28';
    
    // Social
    updateElementSafe('valorBaseSocial', valorBaseSocial);
    updateElementSafe('percentualSocial', '0');
    updateElementSafe('valorFinalSocial', '0,00');
    updateElementSafe('valorSocial', '0', 'value');
    updateElementSafe('percentualAplicadoSocial', '0', 'value');
    
    // Jurídico
    updateElementSafe('valorBaseJuridico', valorBaseJuridico);
    updateElementSafe('percentualJuridico', '0');
    updateElementSafe('valorFinalJuridico', '0,00');
    updateElementSafe('valorJuridico', '0', 'value');
    updateElementSafe('percentualAplicadoJuridico', '0', 'value');
    
    // Total
    updateElementSafe('valorTotalGeral', '0,00');
}

// Inicializar após tudo carregado
inicializarCalculos();

// Animação fadeOut
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);

// Log final
console.log('JavaScript carregado completamente!');
    </script>






<script>
// Variáveis do autocomplete
let indicacaoTimeout = null;
let currentSelectedIndex = -1;
let currentSuggestions = [];

// Inicialização do autocomplete
document.addEventListener('DOMContentLoaded', function() {
    setupIndicacaoAutocomplete();
});

function setupIndicacaoAutocomplete() {
    const input = document.getElementById('indicacao');
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    
    if (!input || !suggestionsContainer) {
        console.warn('Elementos do autocomplete não encontrados');
        return;
    }

    // Event listener para digitação
    input.addEventListener('input', function() {
        const query = this.value.trim();
        currentSelectedIndex = -1;
        
        if (query.length < 2) {
            hideSuggestions();
            return;
        }
        
        // Debounce: aguarda 300ms após parar de digitar
        clearTimeout(indicacaoTimeout);
        indicacaoTimeout = setTimeout(() => {
            buscarNomesAssociados(query);
        }, 300);
    });

    // Navegação com teclado
    input.addEventListener('keydown', function(e) {
        const suggestionsVisible = suggestionsContainer.style.display !== 'none';
        
        if (!suggestionsVisible) return;
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                navigateSuggestions(1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                navigateSuggestions(-1);
                break;
            case 'Enter':
                e.preventDefault();
                selectCurrentSuggestion();
                break;
            case 'Escape':
                e.preventDefault();
                hideSuggestions();
                break;
        }
    });

    // Esconde sugestões ao clicar fora
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            hideSuggestions();
        }
    });

    console.log('✓ Autocomplete de indicação inicializado');
}

function buscarNomesAssociados(query) {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;
    
    // Mostra loading
    suggestionsContainer.innerHTML = '<div class="autocomplete-loading"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
    suggestionsContainer.style.display = 'block';
    
    console.log('Buscando nomes para:', query);
    
    fetch(`../api/buscar_nomes_associados.php?q=${encodeURIComponent(query)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Resposta da busca:', data);
            
            if (data.status === 'success') {
                mostrarSuggestions(data.data);
            } else {
                mostrarErro(data.message || 'Erro ao buscar nomes');
            }
        })
        .catch(error => {
            console.error('Erro na busca de nomes:', error);
            mostrarErro('Erro de conexão. Tente novamente.');
        });
}

function mostrarSuggestions(nomes) {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;
    
    currentSuggestions = nomes;
    currentSelectedIndex = -1;
    
    if (nomes.length === 0) {
        suggestionsContainer.innerHTML = '<div class="autocomplete-no-results">Nenhum nome encontrado</div>';
        suggestionsContainer.style.display = 'block';
        return;
    }
    
    let html = '';
    nomes.forEach((nome, index) => {
        html += `
            <div class="autocomplete-suggestion" data-index="${index}" onclick="selecionarNome('${nome.replace(/'/g, "\\'")}')">
                ${highlightMatch(nome, document.getElementById('indicacao').value)}
            </div>
        `;
    });
    
    suggestionsContainer.innerHTML = html;
    suggestionsContainer.style.display = 'block';
    suggestionsContainer.classList.add('show');
    
    console.log(`✓ ${nomes.length} sugestões exibidas`);
}

function mostrarErro(mensagem) {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;
    
    suggestionsContainer.innerHTML = `<div class="autocomplete-no-results" style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> ${mensagem}</div>`;
    suggestionsContainer.style.display = 'block';
}

function highlightMatch(text, query) {
    if (!query) return text;
    
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<strong style="color: var(--primary);">$1</strong>');
}

function navigateSuggestions(direction) {
    const suggestions = document.querySelectorAll('.autocomplete-suggestion');
    if (suggestions.length === 0) return;
    
    // Remove seleção atual
    suggestions.forEach(s => s.classList.remove('selected'));
    
    // Calcula novo índice
    currentSelectedIndex += direction;
    
    if (currentSelectedIndex < 0) {
        currentSelectedIndex = suggestions.length - 1;
    } else if (currentSelectedIndex >= suggestions.length) {
        currentSelectedIndex = 0;
    }
    
    // Adiciona seleção
    suggestions[currentSelectedIndex].classList.add('selected');
    
    // Scroll se necessário
    suggestions[currentSelectedIndex].scrollIntoView({
        block: 'nearest'
    });
}

function selectCurrentSuggestion() {
    if (currentSelectedIndex >= 0 && currentSuggestions[currentSelectedIndex]) {
        selecionarNome(currentSuggestions[currentSelectedIndex]);
    }
}

function selecionarNome(nome) {
    const input = document.getElementById('indicacao');
    if (!input) return;
    
    input.value = nome;
    hideSuggestions();
    
    // Remove classe de erro se houver
    input.classList.remove('error');
    
    console.log('✓ Nome selecionado:', nome);
}
//oi

function hideSuggestions() {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;
    
    suggestionsContainer.style.display = 'none';
    suggestionsContainer.classList.remove('show');
    currentSelectedIndex = -1;
    currentSuggestions = [];
}

console.log('✓ Script de autocomplete carregado');
</script>
</body>
</html>