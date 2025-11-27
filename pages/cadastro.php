<?php
/**
 * Formulário de Cadastro Simplificado de Associados
 * pages/cadastro_simplificado.php
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
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #dbeafe;
            --success: #16a34a;
            --warning: #ea580c;
            --danger: #dc2626;
            --info: #0891b2;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--primary-light) 100%);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }

        .main-header {
            background: var(--white);
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .header-left {
            display: flex;
            align-items: center;
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
            font-size: 0.75rem;
            color: var(--gray-500);
            margin: 0;
        }

        .content-area {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            font-size: 1rem;
            color: var(--gray-600);
            margin: 0;
        }

        .form-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-card {
            padding: 2rem;
        }

        .section-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .section-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0 0 0.25rem 0;
        }

        .section-subtitle {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .required {
            color: var(--danger);
            font-weight: 700;
        }

        .form-input, .form-select {
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: var(--white);
            width: 100%;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-input.error, .form-select.error {
            border-color: var(--danger);
        }

        .form-error {
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: none;
        }

        .form-input.error + .form-error {
            display: block;
        }

        .radio-group {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 12px;
            border: 2px solid var(--gray-200);
            transition: all 0.2s ease;
        }

        .checkbox-group:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }

        .checkbox-label {
            font-weight: 600;
            color: var(--gray-700);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .photo-upload-container {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 12px;
            border: 2px dashed var(--gray-300);
        }

        .photo-preview {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview-placeholder {
            text-align: center;
            color: var(--gray-500);
        }

        .photo-preview-placeholder i {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }

        .photo-preview-placeholder p {
            font-size: 0.75rem;
            margin: 0;
        }

        .photo-upload-btn {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .photo-upload-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .form-navigation {
            padding: 2rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-nav {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-back {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-back:hover {
            background: var(--gray-300);
            transform: translateY(-1px);
        }

        .btn-submit {
            background: var(--success);
            color: var(--white);
        }

        .btn-submit:hover {
            background: #15803d;
            transform: translateY(-1px);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: var(--white);
            font-weight: 600;
            margin-top: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert-custom {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            animation: fadeInUp 0.3s ease;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-tooltip {
            color: var(--info);
            cursor: help;
        }

        /* === ESTILOS PARA TERMOS E CONDIÇÕES === */
        .terms-container {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--primary-light) 10%);
            border: 2px solid var(--primary);
            border-radius: 16px;
            padding: 2rem;
            margin-top: 1rem;
        }

        .terms-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-align: center;
            justify-content: center;
        }

        .terms-title i {
            font-size: 1.5rem;
        }

        .terms-content {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }

        .terms-text {
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--gray-700);
            margin-bottom: 1rem;
            text-align: justify;
        }

        .terms-text:last-child {
            margin-bottom: 0;
        }

        .terms-text strong {
            color: var(--gray-800);
            font-weight: 700;
        }

        .terms-acceptance {
            border-top: 2px solid var(--gray-200);
            padding-top: 1.5rem;
            margin-top: 1.5rem;
        }

        .terms-checkbox {
            border: 2px solid var(--primary);
            background: var(--white);
            padding: 1.25rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .terms-checkbox:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }

        .terms-checkbox input[type="checkbox"] {
            width: 24px;
            height: 24px;
            accent-color: var(--primary);
        }

        .terms-checkbox .checkbox-label {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
        }

        .terms-checkbox .checkbox-label .required {
            color: var(--danger);
            margin-left: 0.25rem;
        }

        /* Scrollbar personalizada para termos */
        .terms-content::-webkit-scrollbar {
            width: 8px;
        }

        .terms-content::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        .terms-content::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        .terms-content::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark, #1d4ed8);
        }

        /* Radio buttons para optante jurídico */
        .radio-item label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .radio-item:hover label {
            background: rgba(37, 99, 235, 0.05);
        }

        .radio-item input[type="radio"]:checked + label {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-navigation {
                flex-direction: column;
                gap: 1rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }

            .terms-container {
                padding: 1.5rem;
            }

            .terms-content {
                padding: 1rem;
                max-height: 250px;
            }

            .terms-title {
                font-size: 1.1rem;
            }

            .terms-text {
                font-size: 0.85rem;
            }
        }
    </style>
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
                <div style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                    <img src=img/logoassego.png alt="Logo ASSEGO" style="width: 100%; height: 100%; object-fit: contain;">
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
            <div style="display: flex; justify-content: center; margin-bottom: 1.5rem;">
                <img src="img/logoassego.png" alt="Logo ASSEGO" style="width: 120px; height: 120px; object-fit: contain;">
            </div>
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
            <!-- Form Content -->
            <form id="formAssociado" class="form-content" enctype="multipart/form-data">
                <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo $associadoId; ?>">
                <?php endif; ?>

                <!-- SEÇÃO 1: DADOS PESSOAIS -->
                <div class="section-card">
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
                                Indicado por
                                <i class="fas fa-info-circle info-tooltip" title="Nome da pessoa que indicou o associado"></i>
                            </label>
                            <input type="text" class="form-input" name="indicacao" id="indicacao"
                                value="<?php echo $associadoData['indicacao'] ?? ''; ?>"
                                placeholder="Digite o nome de quem indicou...">
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
                    </div>
                </div>

                <!-- SEÇÃO 2: FOTO DO ASSOCIADO -->
                <div class="section-card" style="border-top: 1px solid var(--gray-200); padding-top: 2rem;">
                    <div class="form-grid">
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

                <!-- SEÇÃO 3: TERMOS E CONDIÇÕES -->
                <div class="section-card" style="border-top: 1px solid var(--gray-200); padding-top: 2rem;">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <div class="terms-container">
                                <h3 class="terms-title">
                                    <i class="fas fa-file-contract"></i>
                                    Termos e Condições para associar-se
                                </h3>
                                
                                <div class="terms-content">
                                    <p class="terms-text">
                                        <strong>AUTORIZAÇÃO PARA DESCONTO</strong>, em conformidade com as deliberações fixadas na ASSEMBLÉIA GERAL, que institui o ESTATUTO SOCIAL da entidade registrado no 2º Cartório de Pessoas Jurídicas, Títulos, Documentos e Protestos da cidade de Goiânia-GO, Livro A2, folhas 21/2, sob o nº 279, em 28/08/1956, e em consonância com as disposições constitucionais e legais. AUTORIZO que seja descontado na minha folha de pagamento ou conta corrente para repassar à ASSOCIAÇÃO DOS SUBTENENTES E SARGENTOS PM & BM DO ESTADO DE GOIÁS, o valor correspondente a 1,75% (um, setenta e cinco por cento), calculada sobre o subsidio do 3º SARGENTO na forma do Estatuto Social, § 1º do art. 24, e na parte inicial do inciso IV do artigo 8º da CRFB. Autorizo também o uso da minha imagem caso necessário em publicações feitas pela entidade. Sabendo e entendendo, que estas e outras informações a meu respeito serão armazenadas em banco de dados do sistema de gestão da entidade em conformidade com a lei geral de proteção dos dados nº 13790/2018.
                                    </p>
                                    
                                    <p class="terms-text">
                                        <strong>DECLARO PARA OS DEVIDOS FINS DE DIREITO</strong> que tenho ciência ao disposto no art. 18 do Estatuto Social, em especial o § 6º que versa: "O associado contribuinte optante da assessoria jurídica que vier a utilizá-la dentro do período de carência e se desfiliar antes de decorrido o prazo de 12 (doze) meses de contribuição ininterrupta, deverá ressarcir a ASSEGO no valor correspondente ao restante até que se complete as 12 (doze) contribuições, sob pena, da devida ação de cobrança". Declaro também ter ciência que meus dados serão inseridos em sistema de gestão de uso da ASSEGO para tratamento de informações. Desta forma estou ciente do uso de dados pela Lei Geral de Proteção dos Dados (LGPD).
                                    </p>
                                </div>

                                <!-- Checkbox Optante Jurídico -->
                                <div class="form-group" style="margin: 1.5rem 0;">
                                    <label class="form-label" style="font-size: 1.1rem; color: var(--gray-800);">
                                        <strong>Optante Jurídico?</strong>
                                    </label>
                                    <div class="radio-group" style="margin-top: 0.75rem;">
                                        <div class="radio-item">
                                            <input type="radio" name="optanteJuridico" id="optante_sim" value="1"
                                                   <?php echo (isset($associadoData['servicoJuridico']) && $associadoData['servicoJuridico']) ? 'checked' : ''; ?>>
                                            <label for="optante_sim" style="font-weight: 600;">
                                                <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                                Sim
                                            </label>
                                        </div>
                                        <div class="radio-item">
                                            <input type="radio" name="optanteJuridico" id="optante_nao" value="0"
                                                   <?php echo (!isset($associadoData['servicoJuridico']) || !$associadoData['servicoJuridico']) ? 'checked' : ''; ?>>
                                            <label for="optante_nao" style="font-weight: 600;">
                                                <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                                                Não
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Checkbox de aceite dos termos -->
                                <div class="terms-acceptance">
                                    <div class="checkbox-group terms-checkbox">
                                        <input type="checkbox" name="aceitoTermos" id="aceitoTermos" value="1" required>
                                        <label for="aceitoTermos" class="checkbox-label">
                                            <i class="fas fa-check-square"></i>
                                            <strong>Li e concordo com os termos!</strong>
                                            <span class="required">*</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Navigation -->
            <div class="form-navigation">
                <button type="button" class="btn-nav btn-back" onclick="window.location.href='portal.php'">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                
                <button type="button" class="btn-nav btn-submit" onclick="salvarAssociado()">
                    <i class="fas fa-save"></i>
                    <?php echo $isEdit ? 'Atualizar' : 'Salvar'; ?> Associado
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/pt-BR.min.js"></script>
    
    <script>
    const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
    const associadoId = <?php echo $associadoId ? $associadoId : 'null'; ?>;

    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Iniciando formulário simplificado...');
        
        // Máscaras
        $('#cpf').mask('000.000.000-00');
        $('#telefone').mask('(00) 00000-0000');
        
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

        // Listener para checkbox dos termos
        const aceitoTermosCheckbox = document.getElementById('aceitoTermos');
        if (aceitoTermosCheckbox) {
            aceitoTermosCheckbox.addEventListener('change', function() {
                const termsCheckbox = document.querySelector('.terms-checkbox');
                if (this.checked && termsCheckbox) {
                    termsCheckbox.style.borderColor = 'var(--primary)';
                    termsCheckbox.style.background = 'var(--white)';
                    this.classList.remove('error');
                }
            });
        }
    });

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

    function validarFormulario() {
        const form = document.getElementById('formAssociado');
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        // Validação específica do checkbox de termos
        const aceitoTermos = document.getElementById('aceitoTermos');
        if (!aceitoTermos.checked) {
            aceitoTermos.classList.add('error');
            showAlert('Você deve concordar com os termos e condições para prosseguir!', 'error');
            isValid = false;
            
            // Destaca visualmente o checkbox
            const termsCheckbox = document.querySelector('.terms-checkbox');
            if (termsCheckbox) {
                termsCheckbox.style.borderColor = 'var(--danger)';
                termsCheckbox.style.background = 'rgba(220, 38, 38, 0.05)';
                
                // Remove o destaque após alguns segundos
                setTimeout(() => {
                    termsCheckbox.style.borderColor = 'var(--primary)';
                    termsCheckbox.style.background = 'var(--white)';
                }, 3000);
            }
        } else {
            aceitoTermos.classList.remove('error');
        }
        
        // Validações específicas
        const cpfField = document.getElementById('cpf');
        if (cpfField && cpfField.value && !validarCPF(cpfField.value)) {
            cpfField.classList.add('error');
            isValid = false;
            showAlert('CPF inválido!', 'error');
        }

        const fotoField = document.getElementById('foto');
        if (!isEdit && (!fotoField.files || fotoField.files.length === 0)) {
            showAlert('Por favor, adicione uma foto do associado!', 'error');
            isValid = false;
        }
        
        const emailField = document.getElementById('email');
        if (emailField && emailField.value && !validarEmail(emailField.value)) {
            emailField.classList.add('error');
            isValid = false;
            showAlert('E-mail inválido!', 'error');
        }
        
        if (!isValid) {
            showAlert('Por favor, preencha todos os campos obrigatórios e aceite os termos!', 'warning');
        }
        
        return isValid;
    }

    function salvarAssociado() {
        console.log('=== SALVANDO ASSOCIADO SIMPLIFICADO ===');
        
        if (!validarFormulario()) {
            return;
        }
        
        showLoading();
        
        const formData = new FormData(document.getElementById('formAssociado'));
        
        // Log dos dados que estão sendo enviados
        console.log('Dados sendo enviados:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        
        // URL da API
        const url = isEdit 
            ? `../api/atualizar_associado.php?id=${associadoId}`
            : '../api/criar_associado_simplificado.php';
        
        console.log('URL da requisição:', url);
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
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
                showAlert('Erro de comunicação: ' + responseText.substring(0, 200), 'error');
                return;
            }
            
            if (data.status === 'success') {
                let mensagem = isEdit ? 'Associado atualizado com sucesso!' : 'Associado cadastrado com sucesso!';
                showAlert(mensagem, 'success');
                
                console.log('✓ Sucesso:', data);
                
                // Redireciona após 2 segundos
                setTimeout(() => {
                    window.location.href = 'portal.php';
                }, 2000);
                
            } else {
                console.error('Erro da API:', data);
                showAlert(data.message || 'Erro ao salvar associado!', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Erro de rede:', error);
            showAlert('Erro de comunicação com o servidor!', 'error');
        });
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

    // Animação fadeOut
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }
    `;
    document.head.appendChild(style);

    console.log('✓ JavaScript do formulário simplificado carregado!');
    </script>
</body>
</html>