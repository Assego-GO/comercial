<?php
/**
 * Formulário de Recadastramento de Associados - VERSÃO COMPLETA
 * pages/recadastramentoForm.php
 */

// Iniciar sessão
session_start();

// Limpar parâmetros GET se existirem
if (!empty($_GET)) {
    header('Location: ' . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
    exit;
}

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';

// Define o título da página
$page_title = 'Recadastramento de Dados - ASSEGO';

// Verificar se tem dados na sessão
$associadoData = isset($_SESSION['recadastramento_data']) ? $_SESSION['recadastramento_data'] : null;
$associadoId = isset($_SESSION['recadastramento_id']) ? $_SESSION['recadastramento_id'] : null;

// Busca dados de serviços
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $stmt = $db->prepare("SELECT id, nome, valor_base FROM Servicos WHERE ativo = 1 ORDER BY nome");
    $stmt->execute();
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    $servicos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <link rel="stylesheet" href="estilizacao/cadastro.css">
    
    <style>
        :root {
            --primary: #0056D2;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.075);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .main-header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow-md);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }

        .system-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin: 0;
        }

        .content-area {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .busca-container {
            background: white;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: var(--shadow-md);
            max-width: 600px;
            margin: 0 auto 2rem;
            text-align: center;
        }

        .busca-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            justify-content: center;
        }

        .form-container {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
        }

        .progress-bar-container {
            margin-bottom: 2rem;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
            width: 0;
            z-index: 0;
        }

        .step {
            position: relative;
            text-align: center;
            flex: 1;
            z-index: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-300);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background: var(--primary);
            color: white;
        }

        .step.completed .step-circle {
            background: var(--success);
            color: white;
        }

        .step-label {
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .section-card {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .section-card.active {
            display: block;
        }

        .section-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .section-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }

        .section-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-input {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
        }

        .form-input[readonly] {
            background: var(--gray-100);
            cursor: not-allowed;
        }

        .required {
            color: var(--danger);
        }

        .form-error {
            color: var(--danger);
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .dados-antigos {
            background: var(--gray-100);
            padding: 0.5rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn-nav {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-next {
            background: var(--primary);
            color: white;
        }

        .btn-next:hover {
            background: #0047B3;
            transform: translateY(-2px);
        }

        .btn-back {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-back:hover {
            background: var(--gray-300);
        }

        .btn-submit {
            background: var(--success);
            color: white;
        }

        .btn-submit:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-custom {
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.3s ease;
        }

        .alert-success {
            background: var(--success);
            color: white;
        }

        .alert-error {
            background: var(--danger);
            color: white;
        }

        .alert-warning {
            background: var(--warning);
            color: var(--dark);
        }

        .alert-info {
            background: var(--info);
            color: white;
        }

        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .dependente-card {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .dependente-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-remove-dependente {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-remove-dependente:hover {
            background: #c82333;
        }

        .photo-upload-container {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .photo-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .photo-upload-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .photo-upload-btn:hover {
            background: #0047B3;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
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
                    <p class="system-subtitle">Sistema de Recadastramento</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-sync-alt"></i>
                Recadastramento de Dados
            </h1>
            <p class="page-subtitle">Atualize suas informações cadastrais</p>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <?php if (!$associadoData): ?>
        <!-- Container de Busca -->
        <div class="busca-container">
            <i class="fas fa-search" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
            <h2 style="margin-bottom: 1rem;">Identifique-se</h2>
            <p style="color: var(--gray-600); margin-bottom: 2rem;">Digite seu CPF ou RG para buscar seus dados</p>
            
            <div class="busca-form">
                <div class="form-group" style="flex: 1; max-width: 300px;">
                    <label class="form-label">CPF ou RG</label>
                    <input type="text" class="form-input" id="documento" placeholder="Digite CPF ou RG">
                </div>
                <button type="button" class="btn-nav btn-next" onclick="buscarAssociado(event)">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
            </div>
        </div>
        <?php else: ?>

        <!-- Form Container -->
        <div class="form-container">
            <!-- Info sobre dados atuais -->
            <div style="background: var(--info); color: white; padding: 1rem; border-radius: 12px; margin-bottom: 1rem;">
                <i class="fas fa-info-circle"></i>
                <span>Você está atualizando os dados de: <strong><?php echo htmlspecialchars($associadoData['nome']); ?></strong></span>
            </div>

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
            <form id="formRecadastramento" class="form-content" enctype="multipart/form-data">
                <input type="hidden" name="associado_id" value="<?php echo $associadoId; ?>">
                <input type="hidden" name="tipo_solicitacao" value="recadastramento">

                <!-- Step 1: Dados Pessoais -->
                <div class="section-card active" data-step="1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Pessoais</h2>
                            <p class="section-subtitle">Atualize suas informações pessoais</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">Nome Completo <span class="required">*</span></label>
                            <?php if(!empty($associadoData['nome'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['nome']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input" name="nome" id="nome" required
                                   value="<?php echo htmlspecialchars($associadoData['nome'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Data de Nascimento <span class="required">*</span></label>
                            <input type="date" class="form-input" name="nasc" id="nasc" required readonly
                                   value="<?php echo $associadoData['nasc'] ?? ''; ?>">
                            <small class="text-muted">Data de nascimento não pode ser alterada</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">RG <span class="required">*</span></label>
                            <input type="text" class="form-input" name="rg" id="rg" required readonly
                                   value="<?php echo htmlspecialchars($associadoData['rg'] ?? ''); ?>">
                            <small class="text-muted">RG não pode ser alterado</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">CPF <span class="required">*</span></label>
                            <input type="text" class="form-input" name="cpf" id="cpf" required readonly
                                   value="<?php echo htmlspecialchars($associadoData['cpf'] ?? ''); ?>">
                            <small class="text-muted">CPF não pode ser alterado</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Estado Civil</label>
                            <?php if(!empty($associadoData['estadoCivil'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['estadoCivil']); ?>
                            </div>
                            <?php endif; ?>
                            <select class="form-input" name="estadoCivil" id="estadoCivil">
                                <option value="">Selecione...</option>
                                <option value="Solteiro(a)" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Solteiro(a)' ? 'selected' : ''; ?>>Solteiro(a)</option>
                                <option value="Casado(a)" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Casado(a)' ? 'selected' : ''; ?>>Casado(a)</option>
                                <option value="Divorciado(a)" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Divorciado(a)' ? 'selected' : ''; ?>>Divorciado(a)</option>
                                <option value="Viúvo(a)" <?php echo ($associadoData['estadoCivil'] ?? '') == 'Viúvo(a)' ? 'selected' : ''; ?>>Viúvo(a)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Telefone <span class="required">*</span></label>
                            <?php if(!empty($associadoData['telefone'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['telefone']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="text" class="form-input" name="telefone" id="telefone" required
                                   value="<?php echo htmlspecialchars($associadoData['telefone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-mail</label>
                            <?php if(!empty($associadoData['email'])): ?>
                            <div class="dados-antigos">
                                <i class="fas fa-history"></i> Atual: <?php echo htmlspecialchars($associadoData['email']); ?>
                            </div>
                            <?php endif; ?>
                            <input type="email" class="form-input" name="email" id="email"
                                   value="<?php echo htmlspecialchars($associadoData['email'] ?? ''); ?>">
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
                            <p class="section-subtitle">Atualize suas informações militares</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Corporação <span class="required">*</span></label>
                            <select class="form-input" name="corporacao" id="corporacao" required>
                                <option value="">Selecione...</option>
                                <option value="Polícia Militar" <?php echo ($associadoData['corporacao'] ?? '') == 'Polícia Militar' ? 'selected' : ''; ?>>Polícia Militar</option>
                                <option value="Bombeiro Militar" <?php echo ($associadoData['corporacao'] ?? '') == 'Bombeiro Militar' ? 'selected' : ''; ?>>Bombeiro Militar</option>
                                <option value="Reserva PM" <?php echo ($associadoData['corporacao'] ?? '') == 'Reserva PM' ? 'selected' : ''; ?>>Reserva PM</option>
                                <option value="Reserva BM" <?php echo ($associadoData['corporacao'] ?? '') == 'Reserva BM' ? 'selected' : ''; ?>>Reserva BM</option>
                                <option value="Pensionista" <?php echo ($associadoData['corporacao'] ?? '') == 'Pensionista' ? 'selected' : ''; ?>>Pensionista</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Patente <span class="required">*</span></label>
                            <select class="form-input" name="patente" id="patente" required>
                                <option value="">Selecione...</option>
                                <optgroup label="Praças">
                                    <option value="Soldado" <?php echo ($associadoData['patente'] ?? '') == 'Soldado' ? 'selected' : ''; ?>>Soldado</option>
                                    <option value="Cabo" <?php echo ($associadoData['patente'] ?? '') == 'Cabo' ? 'selected' : ''; ?>>Cabo</option>
                                    <option value="3º Sargento" <?php echo ($associadoData['patente'] ?? '') == '3º Sargento' ? 'selected' : ''; ?>>3º Sargento</option>
                                    <option value="2º Sargento" <?php echo ($associadoData['patente'] ?? '') == '2º Sargento' ? 'selected' : ''; ?>>2º Sargento</option>
                                    <option value="1º Sargento" <?php echo ($associadoData['patente'] ?? '') == '1º Sargento' ? 'selected' : ''; ?>>1º Sargento</option>
                                    <option value="Subtenente" <?php echo ($associadoData['patente'] ?? '') == 'Subtenente' ? 'selected' : ''; ?>>Subtenente</option>
                                </optgroup>
                                <optgroup label="Oficiais">
                                    <option value="2º Tenente" <?php echo ($associadoData['patente'] ?? '') == '2º Tenente' ? 'selected' : ''; ?>>2º Tenente</option>
                                    <option value="1º Tenente" <?php echo ($associadoData['patente'] ?? '') == '1º Tenente' ? 'selected' : ''; ?>>1º Tenente</option>
                                    <option value="Capitão" <?php echo ($associadoData['patente'] ?? '') == 'Capitão' ? 'selected' : ''; ?>>Capitão</option>
                                    <option value="Major" <?php echo ($associadoData['patente'] ?? '') == 'Major' ? 'selected' : ''; ?>>Major</option>
                                    <option value="Tenente-Coronel" <?php echo ($associadoData['patente'] ?? '') == 'Tenente-Coronel' ? 'selected' : ''; ?>>Tenente-Coronel</option>
                                    <option value="Coronel" <?php echo ($associadoData['patente'] ?? '') == 'Coronel' ? 'selected' : ''; ?>>Coronel</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Categoria</label>
                            <select class="form-input" name="categoria" id="categoria">
                                <option value="">Selecione...</option>
                                <option value="Ativa" <?php echo ($associadoData['categoria'] ?? '') == 'Ativa' ? 'selected' : ''; ?>>Ativa</option>
                                <option value="Reserva" <?php echo ($associadoData['categoria'] ?? '') == 'Reserva' ? 'selected' : ''; ?>>Reserva</option>
                                <option value="Reformado" <?php echo ($associadoData['categoria'] ?? '') == 'Reformado' ? 'selected' : ''; ?>>Reformado</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lotação</label>
                            <input type="text" class="form-input" name="lotacao" id="lotacao"
                                   value="<?php echo htmlspecialchars($associadoData['lotacao'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">Unidade</label>
                            <input type="text" class="form-input" name="unidade" id="unidade"
                                   value="<?php echo htmlspecialchars($associadoData['unidade'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Endereço -->
                <div class="section-card" data-step="3">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Endereço</h2>
                            <p class="section-subtitle">Atualize seu endereço residencial</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">CEP <span class="required">*</span></label>
                            <input type="text" class="form-input" name="cep" id="cep" required
                                   value="<?php echo htmlspecialchars($associadoData['cep'] ?? ''); ?>">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Endereço <span class="required">*</span></label>
                            <input type="text" class="form-input" name="endereco" id="endereco" required
                                   value="<?php echo htmlspecialchars($associadoData['endereco'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Número <span class="required">*</span></label>
                            <input type="text" class="form-input" name="numero" id="numero" required
                                   value="<?php echo htmlspecialchars($associadoData['numero'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Complemento</label>
                            <input type="text" class="form-input" name="complemento" id="complemento"
                                   value="<?php echo htmlspecialchars($associadoData['complemento'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bairro <span class="required">*</span></label>
                            <input type="text" class="form-input" name="bairro" id="bairro" required
                                   value="<?php echo htmlspecialchars($associadoData['bairro'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cidade <span class="required">*</span></label>
                            <input type="text" class="form-input" name="cidade" id="cidade" required
                                   value="<?php echo htmlspecialchars($associadoData['cidade'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Step 4: Financeiro -->
                <div class="section-card" data-step="4">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Financeiros</h2>
                            <p class="section-subtitle">Atualize suas informações financeiras</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Tipo de Associado <span class="required">*</span></label>
                            <select class="form-input" name="tipoAssociado" id="tipoAssociado" required>
                                <option value="">Selecione...</option>
                                <option value="Efetivo" <?php echo ($associadoData['tipoAssociado'] ?? '') == 'Efetivo' ? 'selected' : ''; ?>>Efetivo</option>
                                <option value="Contribuinte" <?php echo ($associadoData['tipoAssociado'] ?? '') == 'Contribuinte' ? 'selected' : ''; ?>>Contribuinte</option>
                                <option value="Pensionista" <?php echo ($associadoData['tipoAssociado'] ?? '') == 'Pensionista' ? 'selected' : ''; ?>>Pensionista</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Vínculo do Servidor</label>
                            <select class="form-input" name="vinculoServidor" id="vinculoServidor">
                                <option value="">Selecione...</option>
                                <option value="Ativo" <?php echo ($associadoData['vinculoServidor'] ?? '') == 'Ativo' ? 'selected' : ''; ?>>Ativo</option>
                                <option value="Inativo" <?php echo ($associadoData['vinculoServidor'] ?? '') == 'Inativo' ? 'selected' : ''; ?>>Inativo</option>
                                <option value="Pensionista" <?php echo ($associadoData['vinculoServidor'] ?? '') == 'Pensionista' ? 'selected' : ''; ?>>Pensionista</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Local de Débito</label>
                            <select class="form-input" name="localDebito" id="localDebito">
                                <option value="">Selecione...</option>
                                <option value="Folha" <?php echo ($associadoData['localDebito'] ?? '') == 'Folha' ? 'selected' : ''; ?>>Folha</option>
                                <option value="Boleto" <?php echo ($associadoData['localDebito'] ?? '') == 'Boleto' ? 'selected' : ''; ?>>Boleto</option>
                                <option value="Débito em Conta" <?php echo ($associadoData['localDebito'] ?? '') == 'Débito em Conta' ? 'selected' : ''; ?>>Débito em Conta</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Agência</label>
                            <input type="text" class="form-input" name="agencia" id="agencia"
                                   value="<?php echo htmlspecialchars($associadoData['agencia'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Operação</label>
                            <input type="text" class="form-input" name="operacao" id="operacao"
                                   value="<?php echo htmlspecialchars($associadoData['operacao'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Conta Corrente</label>
                            <input type="text" class="form-input" name="contaCorrente" id="contaCorrente"
                                   value="<?php echo htmlspecialchars($associadoData['contaCorrente'] ?? ''); ?>">
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
                            <p class="section-subtitle">Atualize as informações dos dependentes</p>
                        </div>
                    </div>

                    <div id="dependentesContainer">
                        <?php if(isset($associadoData['dependentes']) && is_array($associadoData['dependentes'])): ?>
                            <?php foreach($associadoData['dependentes'] as $index => $dep): ?>
                            <div class="dependente-card" data-index="<?php echo $index; ?>">
                                <div class="dependente-header">
                                    <h4>Dependente <?php echo $index + 1; ?></h4>
                                    <button type="button" class="btn-remove-dependente" onclick="removerDependente(<?php echo $index; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Nome Completo</label>
                                        <input type="text" class="form-input" name="dependente_nome[]" 
                                               value="<?php echo htmlspecialchars($dep['nome'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Data de Nascimento</label>
                                        <input type="date" class="form-input" name="dependente_nascimento[]"
                                               value="<?php echo $dep['data_nascimento'] ?? ''; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Parentesco</label>
                                        <select class="form-input" name="dependente_parentesco[]">
                                            <option value="">Selecione...</option>
                                            <option value="Filho(a)" <?php echo ($dep['parentesco'] ?? '') == 'Filho(a)' ? 'selected' : ''; ?>>Filho(a)</option>
                                            <option value="Cônjuge" <?php echo ($dep['parentesco'] ?? '') == 'Cônjuge' ? 'selected' : ''; ?>>Cônjuge</option>
                                            <option value="Pai" <?php echo ($dep['parentesco'] ?? '') == 'Pai' ? 'selected' : ''; ?>>Pai</option>
                                            <option value="Mãe" <?php echo ($dep['parentesco'] ?? '') == 'Mãe' ? 'selected' : ''; ?>>Mãe</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Sexo</label>
                                        <select class="form-input" name="dependente_sexo[]">
                                            <option value="">Selecione...</option>
                                            <option value="M" <?php echo ($dep['sexo'] ?? '') == 'M' ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="F" <?php echo ($dep['sexo'] ?? '') == 'F' ? 'selected' : ''; ?>>Feminino</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="btn-nav btn-next" onclick="adicionarDependente()">
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
                            <p class="section-subtitle">Confira as alterações antes de enviar</p>
                        </div>
                    </div>

                    <div id="revisaoContainer"></div>

                    <div class="form-group">
                        <label class="form-label">Motivo do Recadastramento <span class="required">*</span></label>
                        <textarea class="form-input" name="motivo_recadastramento" id="motivo_recadastramento" 
                                  required rows="3" placeholder="Descreva o motivo da atualização"></textarea>
                    </div>
                </div>
            </form>

            <!-- Navigation -->
            <div class="form-navigation">
                <button type="button" class="btn-nav btn-back" id="btnVoltar" onclick="voltarStep()" style="display: none;">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </button>
                
                <div>
                    <button type="button" class="btn-nav btn-back me-2" onclick="cancelarRecadastramento()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    
                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <button type="button" class="btn-nav btn-submit" id="btnEnviar" 
                            onclick="enviarRecadastramento()" style="display: none;">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Recadastramento
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
// Estado do formulário
let currentStep = 1;
const totalSteps = 6;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Configurar campo de busca
    const documentoInput = document.getElementById('documento');
    if (documentoInput) {
        documentoInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarAssociado(e);
            }
        });
    }
    
    // Aplicar máscaras
    if (typeof $ !== 'undefined' && $.fn.mask) {
        $('#telefone').mask('(00) 00000-0000');
        $('#cep').mask('00000-000');
    }
});

// Buscar associado
function buscarAssociado(event) {
    if (event) event.preventDefault();
    
    const documentoInput = document.getElementById('documento');
    if (!documentoInput) return;
    
    const documento = documentoInput.value.replace(/\D/g, '');
    
    if (!documento || documento.length < 7) {
        showAlert('Digite um CPF ou RG válido!', 'warning');
        return;
    }
    
    showLoading();
    
    fetch('../api/recadastro/buscar_associado_recadastramento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ documento: documento })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success' && data.data) {
            showAlert('Associado encontrado!', 'success');
            
            // Salvar na sessão
            return fetch('../api/recadastro/salvar_sessao_recadastramento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    associado_id: data.data.id,
                    associado_data: data.data
                })
            });
        } else {
            throw new Error(data.message || 'Associado não encontrado');
        }
    })
    .then(response => response.json())
    .then(sessionData => {
        if (sessionData.status === 'success') {
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    })
    .catch(error => {
        hideLoading();
        showAlert(error.message, 'error');
    });
}

// Navegação
function proximoStep() {
    if (validarStepAtual()) {
        if (currentStep < totalSteps) {
            document.querySelector(`.step[data-step="${currentStep}"]`).classList.add('completed');
            currentStep++;
            mostrarStep(currentStep);
            
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
    document.querySelectorAll('.section-card').forEach(card => {
        card.classList.remove('active');
    });
    
    const currentCard = document.querySelector(`.section-card[data-step="${step}"]`);
    if (currentCard) {
        currentCard.classList.add('active');
    }
    
    updateProgressBar();
    updateNavigationButtons();
}

function updateProgressBar() {
    const progressLine = document.getElementById('progressLine');
    if (progressLine) {
        const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressLine.style.width = progressPercent + '%';
    }
    
    document.querySelectorAll('.step').forEach((step, index) => {
        const stepNumber = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNumber === currentStep) {
            step.classList.add('active');
        } else if (stepNumber < currentStep) {
            step.classList.add('completed');
        }
    });
}

function updateNavigationButtons() {
    const btnVoltar = document.getElementById('btnVoltar');
    const btnProximo = document.getElementById('btnProximo');
    const btnEnviar = document.getElementById('btnEnviar');
    
    if (btnVoltar) btnVoltar.style.display = currentStep === 1 ? 'none' : 'flex';
    
    if (currentStep === totalSteps) {
        if (btnProximo) btnProximo.style.display = 'none';
        if (btnEnviar) btnEnviar.style.display = 'flex';
    } else {
        if (btnProximo) btnProximo.style.display = 'flex';
        if (btnEnviar) btnEnviar.style.display = 'none';
    }
}

function validarStepAtual() {
    const stepCard = document.querySelector(`.section-card[data-step="${currentStep}"]`);
    if (!stepCard) return true;
    
    const requiredFields = stepCard.querySelectorAll('[required]:not([readonly])');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    if (!isValid) {
        showAlert('Preencha todos os campos obrigatórios!', 'warning');
    }
    
    return isValid;
}

function preencherRevisao() {
    const container = document.getElementById('revisaoContainer');
    if (!container) return;
    
    container.innerHTML = '<h4>Resumo das Alterações</h4><p>Revise seus dados antes de enviar.</p>';
}

function adicionarDependente() {
    const container = document.getElementById('dependentesContainer');
    const index = container.children.length;
    
    const html = `
        <div class="dependente-card" data-index="${index}">
            <div class="dependente-header">
                <h4>Dependente ${index + 1}</h4>
                <button type="button" class="btn-remove-dependente" onclick="removerDependente(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nome Completo</label>
                    <input type="text" class="form-input" name="dependente_nome[]">
                </div>
                <div class="form-group">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-input" name="dependente_nascimento[]">
                </div>
                <div class="form-group">
                    <label class="form-label">Parentesco</label>
                    <select class="form-input" name="dependente_parentesco[]">
                        <option value="">Selecione...</option>
                        <option value="Filho(a)">Filho(a)</option>
                        <option value="Cônjuge">Cônjuge</option>
                        <option value="Pai">Pai</option>
                        <option value="Mãe">Mãe</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sexo</label>
                    <select class="form-input" name="dependente_sexo[]">
                        <option value="">Selecione...</option>
                        <option value="M">Masculino</option>
                        <option value="F">Feminino</option>
                    </select>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
}

function removerDependente(index) {
    const card = document.querySelector(`.dependente-card[data-index="${index}"]`);
    if (card && confirm('Remover este dependente?')) {
        card.remove();
    }
}

function enviarRecadastramento() {
    if (!validarFormularioCompleto()) {
        showAlert('Verifique todos os campos obrigatórios!', 'error');
        return;
    }
    
    if (!confirm('Confirma o envio do recadastramento?')) {
        return;
    }
    
    showLoading();
    
    const formData = new FormData(document.getElementById('formRecadastramento'));
    
    fetch('../api/recadastro/processar_recadastramento.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.status === 'success') {
            showAlert('Recadastramento enviado com sucesso!', 'success');
            
            // Limpar sessão
            fetch('../api/recadastro/limpar_sessao_recadastramento.php');
            
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        } else {
            showAlert(data.message || 'Erro ao enviar', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showAlert('Erro ao processar recadastramento!', 'error');
    });
}

function validarFormularioCompleto() {
    const form = document.getElementById('formRecadastramento');
    const requiredFields = form.querySelectorAll('[required]:not([readonly])');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
        }
    });
    
    return isValid;
}

function cancelarRecadastramento() {
    if (confirm('Cancelar o recadastramento?')) {
        fetch('../api/recadastro/limpar_sessao_recadastramento.php')
            .then(() => {
                window.location.reload();
            });
    }
}

function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('active');
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.remove('active');
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    if (!container) return;
    
    const alertId = 'alert-' + Date.now();
    const icons = {
        success: 'check-circle',
        error: 'times-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const alertHtml = `
        <div id="${alertId}" class="alert-custom alert-${type}">
            <i class="fas fa-${icons[type]}"></i>
            <span>${message}</span>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', alertHtml);
    
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) alert.remove();
    }, 5000);
}
    </script>
</body>
</html>