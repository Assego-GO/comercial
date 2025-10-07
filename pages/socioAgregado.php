<?php
/**
 * Formulário de Cadastro de Sócio Agregado
 * pages/cadastroAgregado.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

// Define o título da página
$page_title = 'Cadastro de Sócio Agregado - ASSEGO';

// Verifica se é edição
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$agregadoId = $isEdit ? intval($_GET['id']) : null;
$agregadoData = null;

if ($isEdit) {
    // Aqui implementaríamos a busca do agregado existente
    $page_title = 'Editar Sócio Agregado - ASSEGO';
}

try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    // Buscar sócios titulares para o autocomplete
    $stmt = $db->prepare("SELECT nome FROM Associados WHERE situacao = 'Filiado' ORDER BY nome");
    $stmt->execute();
    $sociosTitulares = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    $sociosTitulares = [];
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
                <i class="fas fa-user-friends"></i>
                <?php echo $isEdit ? 'Editar Sócio Agregado' : 'Ficha de Filiação - Sócio Agregado'; ?>
            </h1>
            <p class="page-subtitle">
                <?php echo $isEdit ? 'Atualize os dados do sócio agregado' : 'Preencha todos os campos para filiação como sócio agregado'; ?>
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
                        <div class="step-label">Sócio Titular</div>
                    </div>
                    
                    <div class="step" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Endereço</div>
                    </div>
                    
                    <div class="step" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Dados Bancários</div>
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
            <form id="formAgregado" class="form-content" enctype="multipart/form-data">
                <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo $agregadoId; ?>">
                <?php endif; ?>

                <!-- Step 1: Dados Pessoais do Agregado -->
                <div class="section-card active" data-step="1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Pessoais do Agregado</h2>
                            <p class="section-subtitle">Informações básicas do sócio agregado</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">
                                Nome Completo <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="nome" id="nome" required
                                   value="<?php echo $agregadoData['nome'] ?? ''; ?>"
                                   placeholder="Digite o nome completo do sócio agregado">
                            <span class="form-error">Por favor, insira o nome completo</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Nascimento <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="dataNascimento" id="dataNascimento" required
                                   value="<?php echo $agregadoData['data_nascimento'] ?? ''; ?>">
                            <span class="form-error">Por favor, insira a data de nascimento</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Telefone <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="telefone" id="telefone" required
                                   value="<?php echo $agregadoData['telefone'] ?? ''; ?>"
                                   placeholder="(00) 00000-0000">
                            <span class="form-error">Por favor, insira o telefone</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Celular <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="celular" id="celular" required
                                   value="<?php echo $agregadoData['celular'] ?? ''; ?>"
                                   placeholder="(00) 00000-0000">
                            <span class="form-error">Por favor, insira o celular</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                E-mail
                            </label>
                            <input type="email" class="form-input" name="email" id="email"
                                   value="<?php echo $agregadoData['email'] ?? ''; ?>"
                                   placeholder="email@exemplo.com">
                            <span class="form-error">Por favor, insira um e-mail válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                CPF <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="cpf" id="cpf" required
                                   value="<?php echo $agregadoData['cpf'] ?? ''; ?>"
                                   placeholder="000.000.000-00">
                            <span class="form-error">Por favor, insira um CPF válido</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Documento de Identificação
                            </label>
                            <input type="text" class="form-input" name="documento" id="documento"
                                   value="<?php echo $agregadoData['documento'] ?? ''; ?>"
                                   placeholder="RG, CNH, etc.">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Estado Civil <span class="required">*</span>
                            </label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="radio" name="estadoCivil" id="solteiro" value="solteiro" required
                                           <?php echo (isset($agregadoData['estado_civil']) && $agregadoData['estado_civil'] == 'solteiro') ? 'checked' : ''; ?>>
                                    <label for="solteiro">Solteiro</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="radio" name="estadoCivil" id="casado" value="casado" required
                                           <?php echo (isset($agregadoData['estado_civil']) && $agregadoData['estado_civil'] == 'casado') ? 'checked' : ''; ?>>
                                    <label for="casado">Casado</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="radio" name="estadoCivil" id="divorciado" value="divorciado" required
                                           <?php echo (isset($agregadoData['estado_civil']) && $agregadoData['estado_civil'] == 'divorciado') ? 'checked' : ''; ?>>
                                    <label for="divorciado">Divorciado</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="radio" name="estadoCivil" id="separado" value="separado_judicial" required
                                           <?php echo (isset($agregadoData['estado_civil']) && $agregadoData['estado_civil'] == 'separado_judicial') ? 'checked' : ''; ?>>
                                    <label for="separado">Separado Judicial</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="radio" name="estadoCivil" id="viuvo" value="viuvo" required
                                           <?php echo (isset($agregadoData['estado_civil']) && $agregadoData['estado_civil'] == 'viuvo') ? 'checked' : ''; ?>>
                                    <label for="viuvo">Viúvo</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="radio" name="estadoCivil" id="outro" value="outro" required
                                           <?php echo (isset($agregadoData['estado_civil']) && $agregadoData['estado_civil'] == 'outro') ? 'checked' : ''; ?>>
                                    <label for="outro">Outro</label>
                                </div>
                            </div>
                            <span class="form-error">Por favor, selecione o estado civil</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Data de Filiação <span class="required">*</span>
                            </label>
                            <input type="date" class="form-input" name="dataFiliacao" id="dataFiliacao" required
                                   value="<?php echo $agregadoData['data_filiacao'] ?? date('Y-m-d'); ?>">
                            <span class="form-error">Por favor, insira a data de filiação</span>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Sócio Titular -->
                <div class="section-card" data-step="2">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Sócio Titular</h2>
                            <p class="section-subtitle">Dados do sócio titular que está indicando</p>
                        </div>
                    </div>

                    <div class="alert alert-info mb-4">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-info-circle" style="font-size: 1.25rem; color: var(--info);"></i>
                            <div>
                                <strong>Importante:</strong> É necessário ter um sócio titular para validar a filiação como agregado.
                                <br><small>O sócio titular deve estar ativo na associação.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label class="form-label">
                                Nome do Sócio Titular <span class="required">*</span>
                                <i class="fas fa-info-circle info-tooltip" title="Digite o nome do sócio titular que está indicando"></i>
                            </label>
                            <div class="autocomplete-container" style="position: relative;">
                                <input type="text" class="form-input" name="socioTitularNome" id="socioTitularNome" required
                                    value="<?php echo $agregadoData['socio_titular_nome'] ?? ''; ?>"
                                    placeholder="Digite o nome do sócio titular..."
                                    autocomplete="off">
                                <div id="titularSuggestions" class="autocomplete-suggestions"></div>
                            </div>
                            <span class="form-error">Por favor, insira o nome do sócio titular</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Telefone do Titular <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="socioTitularFone" id="socioTitularFone" required
                                   value="<?php echo $agregadoData['socio_titular_fone'] ?? ''; ?>"
                                   placeholder="(00) 00000-0000">
                            <span class="form-error">Por favor, insira o telefone do titular</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                CPF do Titular <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="socioTitularCpf" id="socioTitularCpf" required
                                   value="<?php echo $agregadoData['socio_titular_cpf'] ?? ''; ?>"
                                   placeholder="000.000.000-00">
                            <span class="form-error">Por favor, insira o CPF do titular</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                E-mail do Titular
                            </label>
                            <input type="email" class="form-input" name="socioTitularEmail" id="socioTitularEmail"
                                   value="<?php echo $agregadoData['socio_titular_email'] ?? ''; ?>"
                                   placeholder="email@exemplo.com">
                        </div>

                        <div class="form-group full-width">
                            <div style="background: var(--warning-light); padding: 1rem; border-radius: 8px; border-left: 4px solid var(--warning);">
                                <h5 style="color: var(--warning); margin-bottom: 0.5rem;">
                                    <i class="fas fa-signature"></i> Assinatura do Sócio Titular
                                </h5>
                                <p style="margin-bottom: 0; font-size: 0.9rem; color: var(--text-secondary);">
                                    <strong>Requerimento:</strong> Senhor Presidente, com fulcro no Art. 6º, inciso V, requeiro a filiação de sócio agregado do meu ente acima qualificado. Desde já ressalto o conhecimento de todas as regras dispostas estatutariamente.
                                </p>
                                <div style="margin-top: 1rem; padding: 0.75rem; background: white; border-radius: 4px; min-height: 60px; border: 2px dashed var(--gray-300);">
                                    <small style="color: var(--gray-500); font-style: italic;">
                                        <i class="fas fa-pen"></i> Espaço para assinatura do sócio titular (será validado presencialmente)
                                    </small>
                                </div>
                            </div>
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
                            <p class="section-subtitle">Dados de localização do sócio agregado</p>
                        </div>
                    </div>

                    <div class="address-section">
                        <div class="cep-search-container">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">
                                    CEP
                                </label>
                                <input type="text" class="form-input" name="cep" id="cep"
                                       value="<?php echo $agregadoData['cep'] ?? ''; ?>"
                                       placeholder="00000-000">
                            </div>
                            <button type="button" class="btn-search-cep" onclick="buscarCEP()">
                                <i class="fas fa-search"></i>
                                Buscar CEP
                            </button>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    Rua/Avenida <span class="required">*</span>
                                </label>
                                <input type="text" class="form-input" name="endereco" id="endereco" required
                                       value="<?php echo $agregadoData['endereco'] ?? ''; ?>"
                                       placeholder="Nome da rua, avenida, etc.">
                                <span class="form-error">Por favor, insira o endereço</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Número <span class="required">*</span>
                                </label>
                                <input type="text" class="form-input" name="numero" id="numero" required
                                       value="<?php echo $agregadoData['numero'] ?? ''; ?>"
                                       placeholder="Nº">
                                <span class="form-error">Por favor, insira o número</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Bairro <span class="required">*</span>
                                </label>
                                <input type="text" class="form-input" name="bairro" id="bairro" required
                                       value="<?php echo $agregadoData['bairro'] ?? ''; ?>"
                                       placeholder="Nome do bairro">
                                <span class="form-error">Por favor, insira o bairro</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Cidade <span class="required">*</span>
                                </label>
                                <input type="text" class="form-input" name="cidade" id="cidade" required
                                       value="<?php echo $agregadoData['cidade'] ?? ''; ?>"
                                       placeholder="Nome da cidade">
                                <span class="form-error">Por favor, insira a cidade</span>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Estado <span class="required">*</span>
                                </label>
                                <select class="form-input form-select" name="estado" id="estado" required>
                                    <option value="">Selecione...</option>
                                    <option value="GO" <?php echo (isset($agregadoData['estado']) && $agregadoData['estado'] == 'GO') ? 'selected' : 'selected'; ?>>Goiás</option>
                                    <option value="AC">Acre</option>
                                    <option value="AL">Alagoas</option>
                                    <option value="AP">Amapá</option>
                                    <option value="AM">Amazonas</option>
                                    <option value="BA">Bahia</option>
                                    <option value="CE">Ceará</option>
                                    <option value="DF">Distrito Federal</option>
                                    <option value="ES">Espírito Santo</option>
                                    <option value="MA">Maranhão</option>
                                    <option value="MT">Mato Grosso</option>
                                    <option value="MS">Mato Grosso do Sul</option>
                                    <option value="MG">Minas Gerais</option>
                                    <option value="PA">Pará</option>
                                    <option value="PB">Paraíba</option>
                                    <option value="PR">Paraná</option>
                                    <option value="PE">Pernambuco</option>
                                    <option value="PI">Piauí</option>
                                    <option value="RJ">Rio de Janeiro</option>
                                    <option value="RN">Rio Grande do Norte</option>
                                    <option value="RS">Rio Grande do Sul</option>
                                    <option value="RO">Rondônia</option>
                                    <option value="RR">Roraima</option>
                                    <option value="SC">Santa Catarina</option>
                                    <option value="SP">São Paulo</option>
                                    <option value="SE">Sergipe</option>
                                    <option value="TO">Tocantins</option>
                                </select>
                                <span class="form-error">Por favor, selecione o estado</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Dados Bancários -->
                <div class="section-card" data-step="4">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <div>
                            <h2 class="section-title">Dados Bancários</h2>
                            <p class="section-subtitle">Informações para desconto da contribuição</p>
                        </div>
                    </div>

                    <div class="alert alert-warning mb-4">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-info-circle" style="font-size: 1.25rem; color: var(--warning);"></i>
                            <div>
                                <strong>Autorização para Desconto:</strong> Conforme deliberações da Assembleia Geral, o valor da contribuição social do associado agregado corresponde a <strong>50% do valor</strong> da contribuição social do associado contribuinte.
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Banco <span class="required">*</span>
                            </label>
                            <div class="banco-options">
                                <div class="banco-item">
                                    <input type="radio" name="banco" id="banco_itau" value="itau" required
                                           <?php echo (isset($agregadoData['banco']) && $agregadoData['banco'] == 'itau') ? 'checked' : ''; ?>>
                                    <label for="banco_itau" class="banco-label">
                                        <i class="fas fa-university"></i>
                                        Itaú
                                    </label>
                                </div>
                                <div class="banco-item">
                                    <input type="radio" name="banco" id="banco_caixa" value="caixa" required
                                           <?php echo (isset($agregadoData['banco']) && $agregadoData['banco'] == 'caixa') ? 'checked' : ''; ?>>
                                    <label for="banco_caixa" class="banco-label">
                                        <i class="fas fa-university"></i>
                                        Caixa
                                    </label>
                                </div>
                                <div class="banco-item">
                                    <input type="radio" name="banco" id="banco_outro" value="outro" required
                                           <?php echo (isset($agregadoData['banco']) && $agregadoData['banco'] == 'outro') ? 'checked' : ''; ?>>
                                    <label for="banco_outro" class="banco-label">
                                        <i class="fas fa-university"></i>
                                        Outro
                                    </label>
                                </div>
                            </div>
                            <span class="form-error">Por favor, selecione o banco</span>
                        </div>

                        <div class="form-group" id="bancoOutroContainer" style="display: none;">
                            <label class="form-label">
                                Nome do Banco
                            </label>
                            <input type="text" class="form-input" name="bancoOutroNome" id="bancoOutroNome"
                                   value="<?php echo $agregadoData['banco_outro_nome'] ?? ''; ?>"
                                   placeholder="Digite o nome do banco">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Agência <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="agencia" id="agencia" required
                                   value="<?php echo $agregadoData['agencia'] ?? ''; ?>"
                                   placeholder="Número da agência">
                            <span class="form-error">Por favor, insira a agência</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Conta Corrente <span class="required">*</span>
                            </label>
                            <input type="text" class="form-input" name="contaCorrente" id="contaCorrente" required
                                   value="<?php echo $agregadoData['conta_corrente'] ?? ''; ?>"
                                   placeholder="Número da conta corrente">
                            <span class="form-error">Por favor, insira a conta corrente</span>
                        </div>

                        <div class="form-group full-width">
                            <div style="background: var(--success-light); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--success);">
                                <h4 style="color: var(--success); margin-bottom: 1rem;">
                                    <i class="fas fa-calculator"></i> Valor da Contribuição
                                </h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: center;">
                                    <div>
                                        <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Valor Base (Associado Comum):</div>
                                        <div style="font-size: 1.1rem; font-weight: 600; color: var(--text);">R$ 173,10</div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Seu Valor (50% desconto):</div>
                                        <div style="font-size: 1.3rem; font-weight: 700; color: var(--success);">R$ 86,55</div>
                                    </div>
                                </div>
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--success); font-size: 0.85rem; color: var(--text-secondary);">
                                    <i class="fas fa-info-circle"></i> Valor mensal que será descontado de sua conta
                                </div>
                            </div>
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
                            <p class="section-subtitle">Adicione seus dependentes (opcional)</p>
                        </div>
                    </div>

                    <div class="alert alert-info mb-4">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-info-circle" style="font-size: 1.25rem; color: var(--info);"></i>
                            <div>
                                <strong>Dependentes:</strong> Inclua esposa(o)/companheira(o) e filhos menores de 18 anos ou estudantes até 21 anos.
                            </div>
                        </div>
                    </div>

                    <div id="dependentesContainer">
                        <!-- Dependentes serão adicionados aqui dinamicamente -->
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
                            <p class="section-subtitle">Confira todos os dados antes de finalizar a filiação</p>
                        </div>
                    </div>

                    <div id="revisaoContainer">
                        <!-- Conteúdo será preenchido dinamicamente -->
                    </div>

                    <div class="alert alert-success mt-4">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-clipboard-check" style="font-size: 1.25rem; color: var(--success);"></i>
                            <div>
                                <strong>Declaração:</strong> Concordo com o disposto no art. 6º, inciso V do Estatuto Social, que dispõe: o sócio agregado não poderá votar ou ser votado, não poderá aderir ao serviço de assessoria jurídica, não goza do direito a convites individuais, terá direito somente às devidas contribuições.
                            </div>
                        </div>
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
                    <button type="button" class="btn-nav btn-back me-2" onclick="window.location.href='dashboard.php'">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    
                    <button type="button" class="btn-nav btn-next" id="btnProximo" onclick="proximoStep()">
                        Próximo
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    
                    <button type="button" class="btn-nav btn-submit" id="btnSalvar" onclick="salvarAgregado()" style="display: none;">
                        <i class="fas fa-save"></i>
                        <?php echo $isEdit ? 'Atualizar' : 'Finalizar'; ?> Filiação
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
// JavaScript para o formulário de Sócio Agregado
// PADRÃO DE VALUES: snake_case, sem acentos, minúsculas
// Exemplo: "solteiro", "esposa_companheira", "itau", etc.

const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
const agregadoId = <?php echo $agregadoId ? $agregadoId : 'null'; ?>;

// Estado do formulário
let currentStep = 1;
const totalSteps = 6;
let dependenteIndex = 0;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    console.log('Iniciando formulário de sócio agregado...');
    
    // Máscaras
    $('#cpf').mask('000.000.000-00');
    $('#socioTitularCpf').mask('000.000.000-00');
    $('#telefone').mask('(00) 00000-0000');
    $('#celular').mask('(00) 00000-0000');
    $('#socioTitularFone').mask('(00) 00000-0000');
    $('#cep').mask('00000-000');
    
    // Select2
    $('.form-select').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%'
    });
    
    // Setup autocomplete para sócio titular
    setupTitularAutocomplete();
    
    // Setup banco options
    setupBancoOptions();
    
    // Validação em tempo real
    setupRealtimeValidation();
    
    // Atualiza interface
    updateProgressBar();
    updateNavigationButtons();
});

// Autocomplete para sócio titular
function setupTitularAutocomplete() {
    const input = document.getElementById('socioTitularNome');
    const suggestionsContainer = document.getElementById('titularSuggestions');
    let timeout = null;
    
    if (!input || !suggestionsContainer) return;
    
    input.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 2) {
            suggestionsContainer.style.display = 'none';
            return;
        }
        
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            buscarSociosTitulares(query);
        }, 300);
    });
    
    // Esconde sugestões ao clicar fora
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });
}

function buscarSociosTitulares(query) {
    const suggestionsContainer = document.getElementById('titularSuggestions');
    
    // Simula busca (aqui você implementaria a busca real)
    const socios = <?php echo json_encode($sociosTitulares); ?>;
    const filtrados = socios.filter(nome => 
        nome.toLowerCase().includes(query.toLowerCase())
    ).slice(0, 5);
    
    if (filtrados.length === 0) {
        suggestionsContainer.innerHTML = '<div class="autocomplete-no-results">Nenhum sócio encontrado</div>';
    } else {
        let html = '';
        filtrados.forEach(nome => {
            html += `
                <div class="autocomplete-suggestion" onclick="selecionarTitular('${nome.replace(/'/g, "\\'")}')">
                    ${highlightMatch(nome, query)}
                </div>
            `;
        });
        suggestionsContainer.innerHTML = html;
    }
    
    suggestionsContainer.style.display = 'block';
}

function selecionarTitular(nome) {
    const input = document.getElementById('socioTitularNome');
    const suggestionsContainer = document.getElementById('titularSuggestions');
    
    input.value = nome;
    suggestionsContainer.style.display = 'none';
    
    // Remove classe de erro
    input.classList.remove('error');
}

function highlightMatch(text, query) {
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<strong style="color: var(--primary);">$1</strong>');
}

// Setup para opções de banco
function setupBancoOptions() {
    const bancoInputs = document.querySelectorAll('input[name="banco"]');
    const outroContainer = document.getElementById('bancoOutroContainer');
    
    bancoInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.value === 'outro') {
                outroContainer.style.display = 'block';
                document.getElementById('bancoOutroNome').setAttribute('required', 'required');
            } else {
                outroContainer.style.display = 'none';
                document.getElementById('bancoOutroNome').removeAttribute('required');
            }
        });
    });
}

// Navegação entre steps
function proximoStep() {
    if (validarStepAtual()) {
        if (currentStep < totalSteps) {
            document.querySelector(`[data-step="${currentStep}"]`).classList.add('completed');
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
    
    document.querySelector(`.section-card[data-step="${step}"]`).classList.add('active');
    
    updateProgressBar();
    updateNavigationButtons();
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateProgressBar() {
    const progressLine = document.getElementById('progressLine');
    const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
    progressLine.style.width = progressPercent + '%';
    
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

function updateNavigationButtons() {
    const btnVoltar = document.getElementById('btnVoltar');
    const btnProximo = document.getElementById('btnProximo');
    const btnSalvar = document.getElementById('btnSalvar');
    
    btnVoltar.style.display = currentStep === 1 ? 'none' : 'flex';
    
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
        if (field.type === 'radio') {
            const radioGroup = stepCard.querySelectorAll(`input[name="${field.name}"]`);
            const isChecked = Array.from(radioGroup).some(radio => radio.checked);
            if (!isChecked) {
                radioGroup.forEach(radio => radio.parentElement.classList.add('error'));
                isValid = false;
            } else {
                radioGroup.forEach(radio => radio.parentElement.classList.remove('error'));
            }
        } else {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        }
    });
    
    // Validações específicas
    if (currentStep === 1) {
        const cpfField = document.getElementById('cpf');
        if (cpfField && cpfField.value && !validarCPF(cpfField.value)) {
            cpfField.classList.add('error');
            isValid = false;
            showAlert('CPF inválido!', 'error');
        }
    }
    
    if (currentStep === 2) {
        const titularCpfField = document.getElementById('socioTitularCpf');
        if (titularCpfField && titularCpfField.value && !validarCPF(titularCpfField.value)) {
            titularCpfField.classList.add('error');
            isValid = false;
            showAlert('CPF do titular inválido!', 'error');
        }
    }
    
    if (!isValid) {
        showAlert('Por favor, preencha todos os campos obrigatórios!', 'warning');
    }
    
    return isValid;
}

// Validação em tempo real
function setupRealtimeValidation() {
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('error');
            }
        });
    });
    
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll(`input[name="${this.name}"]`).forEach(r => {
                r.parentElement.classList.remove('error');
            });
        });
    });
}

// Gerenciar dependentes
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
                <div class="form-group">
                    <label class="form-label">Tipo de Dependente</label>
                    <select class="form-input form-select" name="dependentes[${novoIndex}][tipo]" onchange="toggleCamposDependente(this)">
                        <option value="">Selecione...</option>
                        <option value="esposa_companheira">Esposa/Companheira</option>
                        <option value="marido_companheiro">Marido/Companheiro</option>
                        <option value="filho_menor_18">Filho menor de 18 anos</option>
                        <option value="filha_menor_18">Filha menor de 18 anos</option>
                        <option value="filho_estudante">Filho estudante até 21 anos</option>
                        <option value="filha_estudante">Filha estudante até 21 anos</option>
                    </select>
                </div>
                
                <div class="form-group campo-data-nascimento">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" class="form-input" name="dependentes[${novoIndex}][data_nascimento]">
                </div>
                
                <div class="form-group campo-cpf" style="display: none;">
                    <label class="form-label">CPF</label>
                    <input type="text" class="form-input cpf-dependente" name="dependentes[${novoIndex}][cpf]"
                           placeholder="000.000.000-00">
                </div>
                
                <div class="form-group campo-telefone" style="display: none;">
                    <label class="form-label">Telefone</label>
                    <input type="text" class="form-input telefone-dependente" name="dependentes[${novoIndex}][telefone]"
                           placeholder="(00) 00000-0000">
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', dependenteHtml);
    
    // Inicializa Select2 e máscaras no novo dependente
    $(`[data-index="${novoIndex}"] .form-select`).select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%'
    });
    
    $(`[data-index="${novoIndex}"] .cpf-dependente`).mask('000.000.000-00');
    $(`[data-index="${novoIndex}"] .telefone-dependente`).mask('(00) 00000-0000');
}

function toggleCamposDependente(selectElement) {
    const dependenteCard = selectElement.closest('.dependente-card');
    if (!dependenteCard) return;
    
    const campoDataNascimento = dependenteCard.querySelector('.campo-data-nascimento');
    const campoCpf = dependenteCard.querySelector('.campo-cpf');
    const campoTelefone = dependenteCard.querySelector('.campo-telefone');
    const tipo = selectElement.value;
    
    // Reset visibility
    campoDataNascimento.style.display = 'block';
    campoCpf.style.display = 'none';
    campoTelefone.style.display = 'none';
    
    if (tipo === 'esposa_companheira' || tipo === 'marido_companheiro') {
        campoCpf.style.display = 'block';
        campoTelefone.style.display = 'block';
    } else if (tipo === 'filho_estudante' || tipo === 'filha_estudante') {
        campoCpf.style.display = 'block';
    }
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
            const estadoField = document.getElementById('estado');
            const numeroField = document.getElementById('numero');
            
            if (enderecoField) enderecoField.value = data.logradouro;
            if (bairroField) bairroField.value = data.bairro;
            if (cidadeField) cidadeField.value = data.localidade;
            if (estadoField) {
                estadoField.value = data.uf;
                $('#estado').trigger('change');
            }
            
            if (numeroField) numeroField.focus();
        })
        .catch(error => {
            hideLoading();
            console.error('Erro ao buscar CEP:', error);
            showAlert('Erro ao buscar CEP!', 'error');
        });
}

// Preencher revisão
function preencherRevisao() {
    const container = document.getElementById('revisaoContainer');
    if (!container) return;
    
    const formData = new FormData(document.getElementById('formAgregado'));
    
    // Função para converter values padronizados para exibição
    function formatarValor(campo, valor) {
        const formatMap = {
            'estadoCivil': {
                'solteiro': 'Solteiro',
                'casado': 'Casado', 
                'divorciado': 'Divorciado',
                'separado_judicial': 'Separado Judicial',
                'viuvo': 'Viúvo',
                'outro': 'Outro'
            },
            'banco': {
                'itau': 'Itaú',
                'caixa': 'Caixa',
                'outro': 'Outro'
            }
        };
        
        return formatMap[campo] && formatMap[campo][valor] ? formatMap[campo][valor] : valor;
    }
    
    let html = `
        <div class="overview-card">
            <div class="overview-card-header">
                <div class="overview-card-icon blue">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="overview-card-title">Dados Pessoais</h3>
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
                    <span class="overview-label">Estado Civil</span>
                    <span class="overview-value">${formatarValor('estadoCivil', formData.get('estadoCivil')) || '-'}</span>
                </div>
            </div>
        </div>
        
        <div class="overview-card">
            <div class="overview-card-header">
                <div class="overview-card-icon green">
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3 class="overview-card-title">Sócio Titular</h3>
            </div>
            <div class="overview-card-content">
                <div class="overview-item">
                    <span class="overview-label">Nome do Titular</span>
                    <span class="overview-value">${formData.get('socioTitularNome') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">CPF do Titular</span>
                    <span class="overview-value">${formData.get('socioTitularCpf') || '-'}</span>
                </div>
            </div>
        </div>
        
        <div class="overview-card">
            <div class="overview-card-header">
                <div class="overview-card-icon orange">
                    <i class="fas fa-university"></i>
                </div>
                <h3 class="overview-card-title">Dados Bancários</h3>
            </div>
            <div class="overview-card-content">
                <div class="overview-item">
                    <span class="overview-label">Banco</span>
                    <span class="overview-value">${formatarValor('banco', formData.get('banco')) || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">Agência</span>
                    <span class="overview-value">${formData.get('agencia') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">Conta Corrente</span>
                    <span class="overview-value">${formData.get('contaCorrente') || '-'}</span>
                </div>
                <div class="overview-item">
                    <span class="overview-label">Valor Mensal</span>
                    <span class="overview-value" style="color: var(--success); font-weight: 700;">R$ 86,55</span>
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
}

// Salvar agregado
function salvarAgregado() {
    console.log('Salvando sócio agregado...');
    
    if (!validarFormularioCompleto()) {
        showAlert('Por favor, verifique todos os campos obrigatórios!', 'error');
        return;
    }
    
    showLoading();
    
    const formData = new FormData(document.getElementById('formAgregado'));
    
    // Log dos dados
    console.log('Dados sendo enviados:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }
    
    const url = isEdit 
        ? `../api/atualizar_agregado.php?id=${agregadoId}`
        : '../api/criar_agregado.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.status === 'success') {
            const mensagem = isEdit ? 'Sócio agregado atualizado com sucesso!' : 'Filiação como sócio agregado realizada com sucesso!';
            showAlert(mensagem, 'success');
            
            // Redireciona após 2 segundos
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 2000);
        } else {
            showAlert(data.message || 'Erro ao salvar sócio agregado!', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Erro:', error);
        showAlert('Erro de comunicação com o servidor!', 'error');
    });
}

// Validação do formulário completo
function validarFormularioCompleto() {
    const form = document.getElementById('formAgregado');
    if (!form) return false;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (field.type === 'radio') {
            const radioGroup = form.querySelectorAll(`input[name="${field.name}"]`);
            const isChecked = Array.from(radioGroup).some(radio => radio.checked);
            if (!isChecked) {
                isValid = false;
            }
        } else {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

// Validação de CPF
function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]/g, '');
    
    if (cpf.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(cpf)) return false;
    
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
        alert(message);
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
    
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }
    }, 5000);
}

// Animações CSS
const style = document.createElement('style');
style.textContent = `
    .banco-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin-top: 0.5rem;
    }
    
    .banco-item {
        position: relative;
    }
    
    .banco-item input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    
    .banco-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        border: 2px solid var(--gray-300);
        border-radius: 12px;
        background: var(--white);
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        font-weight: 500;
    }
    
    .banco-label i {
        font-size: 1.5rem;
        color: var(--gray-500);
    }
    
    .banco-item input[type="radio"]:checked + .banco-label {
        border-color: var(--primary);
        background: var(--primary-light);
        color: var(--primary);
    }
    
    .banco-item input[type="radio"]:checked + .banco-label i {
        color: var(--primary);
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
    
    .autocomplete-suggestions {
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
    }
    
    .autocomplete-suggestion {
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid var(--gray-100);
        transition: background 0.2s ease;
    }
    
    .autocomplete-suggestion:hover,
    .autocomplete-suggestion.selected {
        background: var(--primary-light);
        color: var(--primary);
    }
    
    .autocomplete-no-results {
        padding: 0.75rem 1rem;
        color: var(--gray-500);
        font-style: italic;
        text-align: center;
    }
`;
document.head.appendChild(style);

console.log('JavaScript do formulário de sócio agregado carregado!');
    </script>
</body>
</html>