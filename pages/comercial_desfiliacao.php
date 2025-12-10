<?php
/**
 * Página de Desfiliação - Fluxo Comercial
 * pages/comercial_desfiliacao.php
 * 
 * Permite que o comercial faça upload da ficha de desfiliação assinada
 * e envie simultaneamente para Presidência, Jurídico e Comercial aprovarem
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Permissoes.php';
require_once './components/header.php';

// Autenticação
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

$usuarioLogado = $auth->getUser();
$page_title = 'Desfiliação - ASSEGO';

// Verificar permissão
if (!Permissoes::tem('COMERCIAL_DESFILIACAO')) {
    Permissoes::registrarAcessoNegado('COMERCIAL_DESFILIACAO', 'comercial_desfiliacao.php');
    $_SESSION['erro'] = 'Você não tem permissão para acessar esta funcionalidade.';
    header('Location: ../pages/dashboard.php');
    exit;
}

// Conectar ao banco
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
} catch (Exception $e) {
    die("Erro de conexão com banco de dados: " . $e->getMessage());
}

// Criar instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
    'notificationCount' => 0,
    'showSearch' => false
]);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <?php $headerComponent->renderCSS(); ?>
    <style>
        :root {
            --primary: #0056d2;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --dark: #212529;
            --light: #f8f9fa;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --white: #ffffff;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-area {
            padding: 4rem 0 2rem 0;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, #084298 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            margin-top: 1rem;
            box-shadow: 0 10px 30px rgba(0, 86, 210, 0.2);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-subtitle {
            margin: 0.5rem 0 0 0;
            opacity: 0.95;
            font-size: 0.95rem;
        }

        .form-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-section-title i {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .search-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-input {
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
            outline: none;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--gray-300);
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-results.show {
            display: block;
        }

        .result-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--gray-200);
            transition: background 0.2s ease;
        }

        .result-item:hover {
            background: var(--gray-100);
        }

        .result-item-name {
            font-weight: 600;
            color: var(--dark);
        }

        .result-item-info {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .selected-associado {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: none;
            border: 2px solid var(--primary);
        }

        .selected-associado.show {
            display: block;
        }

        .associado-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .info-item {
            flex: 1;
            min-width: 200px;
        }

        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 1rem;
            color: var(--dark);
            font-weight: 600;
            margin-top: 0.25rem;
        }

        .upload-zone {
            border: 2px dashed var(--primary);
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(0, 86, 210, 0.02);
            margin-bottom: 1.5rem;
        }

        .upload-zone:hover {
            background: rgba(0, 86, 210, 0.05);
            border-color: #084298;
        }

        .upload-zone.dragover {
            background: rgba(0, 86, 210, 0.1);
            border-color: #084298;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .upload-text {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .upload-hint {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .file-input {
            display: none;
        }

        .uploaded-files {
            margin-bottom: 1.5rem;
        }

        .file-item {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .file-item:hover {
            background: var(--gray-200);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: var(--dark);
        }

        .file-size {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .file-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-remove {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-remove:hover {
            background: #c82333;
        }

        .observacao-area {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .observacao-area:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.1);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #084298 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 86, 210, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .alert {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .approval-info {
            background: #e8f4fd;
            border-left: 4px solid var(--info);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .approval-info strong {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .approval-depts {
            display: flex;
            gap: 1rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
        }

        .dept-badge {
            background: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <?php $headerComponent->render(); ?>

        <div class="container-fluid content-area">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-file-signature"></i>
                    Desfiliação de Associados
                </h1>
                <p class="page-subtitle">
                    Upload da ficha de desfiliação e encaminhamento para aprovação dos departamentos
                </p>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <!-- Info Box -->
                <div class="approval-info">
                    <strong>⚡ Como funciona?</strong>
                    <p style="margin: 0;">
                        1. Selecione o associado que deseja desfiliado <br>
                        2. Faça upload da ficha de desfiliação assinada <br>
                        3. Ao enviar, o documento será encaminhado simultaneamente para:
                    </p>
                    <div class="approval-depts">
                        <div class="dept-badge">
                            <i class="fas fa-briefcase"></i> Comercial
                        </div>
                        <div class="dept-badge">
                            <i class="fas fa-gavel"></i> Jurídico
                        </div>
                        <div class="dept-badge">
                            <i class="fas fa-crown"></i> Presidência
                        </div>
                    </div>
                </div>

                <!-- Section 1: Search for Associate -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="fas fa-search"></i>
                        Localizar Associado
                    </h3>

                    <div class="search-group">
                        <input 
                            type="text" 
                            class="search-input w-100" 
                            id="searchAssociado"
                            placeholder="Digite o CPF ou nome do associado..."
                            autocomplete="off"
                        >
                        <div class="search-results" id="searchResults"></div>
                    </div>

                    <div class="selected-associado" id="selectedAssociado">
                        <div class="associado-info" id="associadoInfo"></div>
                    </div>
                </div>

                <!-- Section 2: Upload Document -->
                <div class="form-section" id="uploadSection" style="display: none;">
                    <h3 class="form-section-title">
                        <i class="fas fa-upload"></i>
                        Anexar Ficha de Desfiliação
                    </h3>

                    <div class="upload-zone" id="uploadZone">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="upload-text">Clique ou arraste a ficha aqui</div>
                        <div class="upload-hint">Aceita PDF, JPG, PNG (máximo 10MB)</div>
                        <input type="file" id="fileInput" class="file-input" accept=".pdf,.jpg,.jpeg,.png">
                    </div>

                    <div class="uploaded-files" id="uploadedFiles"></div>
                </div>

                <!-- Section 3: Observations -->
                <div class="form-section" id="obsSection" style="display: none;">
                    <h3 class="form-section-title">
                        <i class="fas fa-notes-medical"></i>
                        Observações (Opcional)
                    </h3>

                    <textarea 
                        class="observacao-area" 
                        id="observacoes"
                        placeholder="Adicione observações sobre a desfiliação (motivo, informações importantes, etc.)"
                    ></textarea>
                </div>

                <!-- Buttons -->
                <div class="button-group" id="buttonGroup" style="display: none;">
                    <button class="btn btn-secondary" onclick="limparFormulario()">
                        <i class="fas fa-redo"></i>
                        Limpar
                    </button>
                    <button class="btn btn-primary" id="submitBtn" onclick="enviarDesfiliacao()">
                        <i class="fas fa-paper-plane"></i>
                        Enviar para Aprovação
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; display: none; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; text-align: center;">
            <div class="spinner" style="margin: 0 auto 1rem;"></div>
            <p style="color: var(--dark); font-weight: 600;">Processando desfiliação...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let associadoSelecionado = null;
        let arquivoUpload = null;

        // Search Associado
        const searchInput = document.getElementById('searchAssociado');
        const searchResults = document.getElementById('searchResults');

        searchInput.addEventListener('input', async (e) => {
            const termo = e.target.value.trim();
            
            if (termo.length < 2) {
                searchResults.classList.remove('show');
                return;
            }

            try {
                const response = await fetch('../api/buscar_associado_desfiliacao.php?q=' + encodeURIComponent(termo));
                const data = await response.json();

                if (data.status === 'success' && data.data.length > 0) {
                    searchResults.innerHTML = data.data.map(ass => `
                        <div class="result-item" onclick="selecionarAssociado(${ass.id}, '${ass.nome.replace(/'/g, "\\'")}', '${ass.cpf}')">
                            <div class="result-item-name">${ass.nome}</div>
                            <div class="result-item-info">CPF: ${ass.cpf} | RG: ${ass.rg || 'N/A'}</div>
                        </div>
                    `).join('');
                    searchResults.classList.add('show');
                } else {
                    searchResults.innerHTML = '<div class="result-item" style="cursor: default;">Nenhum associado encontrado</div>';
                    searchResults.classList.add('show');
                }
            } catch (error) {
                console.error('Erro ao buscar:', error);
                showAlert('Erro ao buscar associado', 'danger');
            }
        });

        function selecionarAssociado(id, nome, cpf) {
            associadoSelecionado = { id, nome, cpf };
            searchInput.value = nome;
            searchResults.classList.remove('show');

            document.getElementById('associadoInfo').innerHTML = `
                <div class="info-item">
                    <div class="info-label">Nome</div>
                    <div class="info-value">${nome}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">CPF</div>
                    <div class="info-value">${cpf}</div>
                </div>
                <div class="info-item">
                    <button class="btn btn-secondary" onclick="limparAssociado()" style="margin-top: 1.5rem;">
                        <i class="fas fa-times"></i> Trocar
                    </button>
                </div>
            `;

            document.getElementById('selectedAssociado').classList.add('show');
            document.getElementById('uploadSection').style.display = 'block';
            document.getElementById('obsSection').style.display = 'block';
            document.getElementById('buttonGroup').style.display = 'flex';
        }

        function limparAssociado() {
            associadoSelecionado = null;
            searchInput.value = '';
            document.getElementById('selectedAssociado').classList.remove('show');
            document.getElementById('uploadSection').style.display = 'none';
            document.getElementById('obsSection').style.display = 'none';
            document.getElementById('buttonGroup').style.display = 'none';
        }

        // File Upload
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const uploadedFiles = document.getElementById('uploadedFiles');

        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                
                // Validar tipo e tamanho
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                const maxSize = 10 * 1024 * 1024; // 10MB

                if (!allowedTypes.includes(file.type)) {
                    showAlert('Tipo de arquivo não permitido. Use PDF, JPG ou PNG.', 'danger');
                    return;
                }

                if (file.size > maxSize) {
                    showAlert('Arquivo muito grande. Máximo 10MB.', 'danger');
                    return;
                }

                arquivoUpload = file;
                displayFile(file);
            }
        }

        function displayFile(file) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            uploadedFiles.innerHTML = `
                <div class="file-item">
                    <div class="file-info">
                        <div class="file-name">
                            <i class="fas fa-file"></i> ${file.name}
                        </div>
                        <div class="file-size">${sizeMB} MB</div>
                    </div>
                    <div class="file-actions">
                        <button class="btn-remove" onclick="removerArquivo()">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    </div>
                </div>
            `;
        }

        function removerArquivo() {
            arquivoUpload = null;
            uploadedFiles.innerHTML = '';
            fileInput.value = '';
        }

        function limparFormulario() {
            associadoSelecionado = null;
            arquivoUpload = null;
            searchInput.value = '';
            document.getElementById('observacoes').value = '';
            document.getElementById('selectedAssociado').classList.remove('show');
            document.getElementById('uploadSection').style.display = 'none';
            document.getElementById('obsSection').style.display = 'none';
            document.getElementById('buttonGroup').style.display = 'none';
            uploadedFiles.innerHTML = '';
            fileInput.value = '';
        }

        async function enviarDesfiliacao() {
            console.log('Função enviarDesfiliacao chamada');
            
            if (!associadoSelecionado) {
                showAlert('Selecione um associado', 'danger');
                return;
            }

            if (!arquivoUpload) {
                showAlert('Anexe a ficha de desfiliação', 'danger');
                return;
            }

            console.log('Validações OK, mostrando loading...');
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('loadingOverlay').style.display = 'flex';

            try {
                const formData = new FormData();
                formData.append('associado_id', associadoSelecionado.id);
                formData.append('arquivo', arquivoUpload);
                formData.append('observacao', document.getElementById('observacoes').value);

                console.log('Enviando desfiliação para:', associadoSelecionado.id);

                const response = await fetch('../api/desfiliacao_enviar.php', {
                    method: 'POST',
                    body: formData
                });

                console.log('Resposta status:', response.status);

                const data = await response.json();
                console.log('Dados recebidos:', data);

                document.getElementById('loadingOverlay').style.display = 'none';
                document.getElementById('submitBtn').disabled = false;

                if (data.status === 'success') {
                    const docId = data.data ? data.data.documento_id : 'N/A';
                    showAlert(`✅ Desfiliação enviada com sucesso! ID: ${docId}`, 'success');
                    setTimeout(() => limparFormulario(), 2000);
                } else {
                    showAlert(data.message || 'Erro ao enviar desfiliação', 'danger');
                }
            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('loadingOverlay').style.display = 'none';
                document.getElementById('submitBtn').disabled = false;
                showAlert('Erro ao processar a desfiliação', 'danger');
            }
        }

        function showAlert(message, type) {
            const container = document.querySelector('.form-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <button type="button" onclick="this.parentElement.remove()" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.5rem;">×</button>
                ${message}
            `;
            container.insertBefore(alert, container.firstChild);
            setTimeout(() => alert.remove(), 5000);
        }
    </script>
</body>
</html>
