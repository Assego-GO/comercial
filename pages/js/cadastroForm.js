// JavaScript Completo com FALLBACKS e FICHA DE FILIAÇÃO

// Estado do formulário - DECLARADO PRIMEIRO
let currentStep = 1;
const totalSteps = 6;
let dependenteIndex = 0;

// Depois pega os dados da página
const isEdit = window.pageData ? window.pageData.isEdit : false;
const associadoId = window.pageData ? window.pageData.associadoId : null;

// Dados carregados dos serviços (para edição)
let servicosCarregados = null;

// VARIÁVEIS GLOBAIS PARA DADOS DOS SERVIÇOS
let regrasContribuicao = [];
let servicosData = [];
let tiposAssociadoData = [];
let dadosCarregados = false;

// Inicialização
document.addEventListener('DOMContentLoaded', function () {
    console.log('Iniciando formulário de filiação...');
    console.log('Modo edição:', isEdit, 'ID:', associadoId);

    // Atalho ESC para voltar ao dashboard
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (confirm('Deseja voltar ao dashboard? Os dados não salvos serão perdidos.')) {
                window.location.href = 'dashboard.php';
            }
        }
    });

    // PRIMEIRA COISA: Carrega dados de serviços do banco
    carregarDadosServicos()
        .then(() => {
            console.log('✓ Dados de serviços carregados, continuando inicialização...');

            // Máscaras
            $('#cpf').mask('000.000.000-00');
            $('#telefone').mask('(00) 00000-0000');
            $('#cep').mask('00000-000');

            // Select2
            $('.form-select').select2({
                language: 'pt-BR',
                theme: 'default',
                width: '100%'
            });

            // Preview de foto
            document.getElementById('foto').addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        showAlert('Arquivo muito grande! O tamanho máximo é 5MB.', 'error');
                        e.target.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        document.getElementById('photoPreview').innerHTML =
                            `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Preview da ficha assinada (apenas novos cadastros)
            if (!isEdit) {
                const fichaInput = document.getElementById('ficha_assinada');
                if (fichaInput) {
                    fichaInput.addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (file) {
                            if (file.size > 10 * 1024 * 1024) {
                                showAlert('Arquivo muito grande! O tamanho máximo é 10MB.', 'error');
                                e.target.value = '';
                                return;
                            }

                            const preview = document.getElementById('fichaPreview');
                            const fileName = file.name;
                            const fileExt = fileName.split('.').pop().toLowerCase();

                            if (fileExt === 'pdf') {
                                preview.innerHTML = `
                            <div style="text-align: center; padding: 2rem;">
                                <i class="fas fa-file-pdf" style="font-size: 4rem; color: #dc3545; margin-bottom: 1rem;"></i>
                                <p style="font-weight: 600; color: var(--dark);">PDF Anexado</p>
                                <p style="font-size: 0.75rem; color: var(--gray-600);">${fileName}</p>
                            </div>
                        `;
                            } else {
                                // Para imagens, mostra preview
                                const reader = new FileReader();
                                reader.onload = function (e) {
                                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                                };
                                reader.readAsDataURL(file);
                            }
                        }
                    });
                }
            }

            // Validação em tempo real
            setupRealtimeValidation();

            // Carrega dados dos serviços se estiver editando
            if (isEdit && associadoId) {
                setTimeout(() => {
                    carregarServicosAssociado();
                }, 1000);
            }

            // Atualiza interface
            updateProgressBar();
            updateNavigationButtons();

            // Se for modo edição e houver dependentes já carregados, atualiza o índice
            const dependentesExistentes = document.querySelectorAll('.dependente-card');
            if (dependentesExistentes.length > 0) {
                dependenteIndex = dependentesExistentes.length;
            }

        })
        .catch(error => {
            console.error('Erro ao carregar dados de serviços:', error);
            showAlert('Erro ao carregar dados do sistema. Algumas funcionalidades podem não funcionar.', 'warning');
        });
});

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
                { id: "1", nome: "Social", valor_base: "173.10", obrigatorio: true },
                { id: "2", nome: "Jurídico", valor_base: "43.28", obrigatorio: false }
            ];

            regrasContribuicao = [
                { tipo_associado: "Contribuinte", servico_id: "1", percentual_valor: "100.00" },
                { tipo_associado: "Contribuinte", servico_id: "2", percentual_valor: "100.00" },
                { tipo_associado: "Aluno", servico_id: "1", percentual_valor: "50.00" },
                { tipo_associado: "Aluno", servico_id: "2", percentual_valor: "100.00" },
                { tipo_associado: "Soldado 2ª Classe", servico_id: "1", percentual_valor: "50.00" },
                { tipo_associado: "Soldado 2ª Classe", servico_id: "2", percentual_valor: "100.00" },
                { tipo_associado: "Soldado 1ª Classe", servico_id: "1", percentual_valor: "100.00" },
                { tipo_associado: "Soldado 1ª Classe", servico_id: "2", percentual_valor: "100.00" },
                { tipo_associado: "Agregado", servico_id: "1", percentual_valor: "50.00" },
                { tipo_associado: "Agregado", servico_id: "2", percentual_valor: "100.00" },
                { tipo_associado: "Remido", servico_id: "1", percentual_valor: "0.00" },
                { tipo_associado: "Remido", servico_id: "2", percentual_valor: "100.00" },
                { tipo_associado: "Remido 50%", servico_id: "1", percentual_valor: "50.00" },
                { tipo_associado: "Remido 50%", servico_id: "2", percentual_valor: "100.00" },
                { tipo_associado: "Benemerito", servico_id: "1", percentual_valor: "0.00" },
                { tipo_associado: "Benemerito", servico_id: "2", percentual_valor: "100.00" }
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
        { id: "1", nome: "Social", valor_base: "173.10" },
        { id: "2", nome: "Jurídico", valor_base: "43.28" }
    ];

    regrasContribuicao = [
        { tipo_associado: "Contribuinte", servico_id: "1", percentual_valor: "100.00" },
        { tipo_associado: "Contribuinte", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Aluno", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Aluno", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Soldado 2ª Classe", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Soldado 2ª Classe", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Soldado 1ª Classe", servico_id: "1", percentual_valor: "100.00" },
        { tipo_associado: "Soldado 1ª Classe", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Agregado", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Agregado", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Remido", servico_id: "1", percentual_valor: "0.00" },
        { tipo_associado: "Remido", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Remido 50%", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Remido 50%", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Benemerito", servico_id: "1", percentual_valor: "0.00" },
        { tipo_associado: "Benemerito", servico_id: "2", percentual_valor: "100.00" }
    ];

    console.log('Dados hardcoded definidos:', { regrasContribuicao, servicosData });
}

// Função para carregar serviços do associado (modo edição)
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
                restaurarHtmlServicos();
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

// Validação do step atual - ATUALIZADA PARA FICHA DE FILIAÇÃO
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

        // Valida foto do associado
        const fotoField = document.getElementById('foto');
        if (!isEdit && (!fotoField.files || fotoField.files.length === 0)) {
            // Verifica se já tem preview de foto
            const photoPreview = document.getElementById('photoPreview');
            const hasPhoto = photoPreview && photoPreview.querySelector('img');
            
            if (!hasPhoto) {
                showAlert('Por favor, adicione uma foto do associado!', 'error');
                isValid = false;
            }
        }

        // NOVA VALIDAÇÃO: Valida ficha assinada (apenas para novos cadastros)
        if (!isEdit) {
            const fichaField = document.getElementById('ficha_assinada');
            if (!fichaField.files || fichaField.files.length === 0) {
                showAlert('Por favor, anexe a ficha de filiação assinada!', 'error');
                isValid = false;
            }
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
        input.addEventListener('input', function () {
            if (this.value.trim()) {
                this.classList.remove('error');
            }
        });
    });

    // Validação específica de CPF
    const cpfField = document.getElementById('cpf');
    if (cpfField) {
        cpfField.addEventListener('blur', function () {
            if (this.value && !validarCPF(this.value)) {
                this.classList.add('error');
                showAlert('CPF inválido!', 'error');
            }
        });
    }

    // Validação específica de email
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.addEventListener('blur', function () {
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
        <div class="form-group full-width">
            <label class="form-label">Nome Completo</label>
            <input type="text" class="form-input" name="dependentes[${novoIndex}][nome]" 
                   placeholder="Nome do dependente">
        </div>
        <div class="form-group">
            <label class="form-label">Data de Nascimento</label>
            <input type="date" class="form-input" name="dependentes[${novoIndex}][data_nascimento]">
        </div>
        <div class="form-group">
            <label class="form-label">Parentesco</label>
            <select class="form-input form-select" name="dependentes[${novoIndex}][parentesco]">
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

    console.log('Calculando para:', { tipoAssociado, servicoJuridicoChecked });

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

    console.log('Regras encontradas no banco:', { regrasSocial, regrasJuridico });

    let valorTotalGeral = 0;

    // Calcular Serviço Social (sempre obrigatório)
    if (regrasSocial.length > 0) {
        const regra = regrasSocial[0];
        const servicoSocial = servicosData.find(s => s.id == 1);
        const valorBase = parseFloat(servicoSocial?.valor_base || 173.10);
        const percentual = parseFloat(regra.percentual_valor);
        const valorFinal = (valorBase * percentual) / 100;

        console.log('Cálculo Social (do banco):', { valorBase, percentual, valorFinal });

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

        console.log('Cálculo Jurídico (do banco):', { valorBase, percentual, valorFinal });

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

// Função para salvar associado - ATUALIZADA PARA FICHA DE FILIAÇÃO
function salvarAssociado() {
    console.log('=== SALVANDO ASSOCIADO COM FICHA DE FILIAÇÃO ===');

    // Validação final
    if (!validarFormularioCompleto()) {
        showAlert('Por favor, verifique todos os campos obrigatórios!', 'error');
        return;
    }

    showLoading();

    const formData = new FormData(document.getElementById('formAssociado'));

    // Garantir que o tipo de associado seja enviado
    const tipoAssociadoEl = document.getElementById('tipoAssociadoServico');
    if (tipoAssociadoEl && tipoAssociadoEl.value) {
        formData.set('tipoAssociadoServico', tipoAssociadoEl.value);
        console.log('✓ Tipo de associado:', tipoAssociadoEl.value);
    }

    // Garantir valores dos serviços
    const valorSocialEl = document.getElementById('valorSocial');
    const valorJuridicoEl = document.getElementById('valorJuridico');
    const percentualSocialEl = document.getElementById('percentualAplicadoSocial');
    const percentualJuridicoEl = document.getElementById('percentualAplicadoJuridico');

    if (valorSocialEl) {
        formData.set('valorSocial', valorSocialEl.value || '0');
    }
    if (percentualSocialEl) {
        formData.set('percentualAplicadoSocial', percentualSocialEl.value || '0');
    }
    if (valorJuridicoEl) {
        formData.set('valorJuridico', valorJuridicoEl.value || '0');
    }
    if (percentualJuridicoEl) {
        formData.set('percentualAplicadoJuridico', percentualJuridicoEl.value || '0');
    }

    // Log dos dados
    console.log('Dados sendo enviados:');
    for (let [key, value] of formData.entries()) {
        if (key.includes('ficha_assinada') || key.includes('foto')) {
            console.log(`${key}: [arquivo]`);
        } else {
            console.log(`${key}: ${value}`);
        }
    }

    // URL da API
    const url = isEdit
        ? `../api/atualizar_associado.php?id=${associadoId}`
        : '../api/criar_associado.php';

    console.log('URL da requisição:', url);
    console.log('Método:', isEdit ? 'ATUALIZAR' : 'CRIAR FICHA DE FILIAÇÃO');

    fetch(url, {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(responseText => {
            console.log('Response:', responseText);

            hideLoading();

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Erro ao fazer parse JSON:', e);
                showAlert('Erro de comunicação com o servidor', 'error');
                return;
            }

            if (data.status === 'success') {
                // Monta mensagem de sucesso detalhada
                let mensagem = isEdit ? 'Associado atualizado com sucesso!' : 'Ficha de filiação cadastrada com sucesso!';

                if (!isEdit && data.data) {
                    // Adiciona informações sobre o fluxo do documento
                    if (data.data.fluxo_documento) {
                        const fluxo = data.data.fluxo_documento;
                        if (fluxo.enviado_presidencia) {
                            mensagem += '\n\n✓ Ficha de filiação enviada automaticamente para assinatura na presidência.';
                        } else {
                            mensagem += '\n\n⚠ Ficha de filiação anexada. Aguardando envio manual para presidência.';
                        }
                    }

                    // Adiciona informações sobre serviços
                    if (data.data.servicos && data.data.servicos.valor_mensal) {
                        mensagem += '\n\nServiços contratados: ' + data.data.servicos.lista.join(', ');
                        mensagem += '\nValor total mensal: R$ ' + data.data.servicos.valor_mensal;
                    }
                }

                showAlert(mensagem, 'success');

                console.log('✓ Sucesso:', data);

                // Redireciona após 3 segundos
                setTimeout(() => {
                    if (!isEdit && data.data && data.data.fluxo_documento && data.data.fluxo_documento.enviado_presidencia) {
                        // Se foi enviado para presidência, redireciona para a página de fluxo
                        window.location.href = 'documentos_fluxo.php?novo=1';
                    } else {
                        // Caso contrário, vai para o dashboard normal
                        window.location.href = 'dashboard.php?success=1';
                    }
                }, 3000);

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

    console.log('=== FIM SALVAMENTO ===');
}

// Adiciona listeners aos campos de serviços
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM carregado - Modo edição:', isEdit);

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

// Preencher revisão
function preencherRevisao() {
    const container = document.getElementById('revisaoContainer');
    if (!container) return;

    const formData = new FormData(document.getElementById('formAssociado'));

    let html = `
<div class="overview-card" style="background: var(--gray-100); padding: 2rem; border-radius: 16px; margin-bottom: 1.5rem;">
    <div class="overview-card-header" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--gray-200);">
        <div class="overview-card-icon" style="width: 48px; height: 48px; background: var(--primary-light); color: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-user"></i>
        </div>
        <h3 class="overview-card-title" style="font-size: 1.25rem; font-weight: 700; color: var(--dark); margin: 0;">Resumo da Filiação</h3>
    </div>
    <div class="overview-card-content">
        <div class="row">
            <div class="col-md-6">
                <div class="overview-item" style="margin-bottom: 1rem;">
                    <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Nome:</span>
                    <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${formData.get('nome') || '-'}</span>
                </div>
                <div class="overview-item" style="margin-bottom: 1rem;">
                    <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">CPF:</span>
                    <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${formData.get('cpf') || '-'}</span>
                </div>
                <div class="overview-item" style="margin-bottom: 1rem;">
                    <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Telefone:</span>
                    <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${formData.get('telefone') || '-'}</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="overview-item" style="margin-bottom: 1rem;">
                    <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Tipo de Associado:</span>
                    <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${formData.get('tipoAssociadoServico') || '-'}</span>
                </div>
                <div class="overview-item" style="margin-bottom: 1rem;">
                    <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Valor Total Mensal:</span>
                    <span class="overview-value" style="font-size: 1.25rem; color: var(--primary); font-weight: 700;">R$ ${document.getElementById('valorTotalGeral')?.textContent || '0,00'}</span>
                </div>
            </div>
        </div>
    </div>
</div>`;

    // Se não for edição, adiciona aviso sobre ficha de filiação
    if (!isEdit) {
        html += `
<div class="alert-custom alert-warning" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); color: #856404; border: 1px solid #f0ad4e;">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Atenção!</strong>
        <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem;">
            A ficha de filiação anexada será enviada para aprovação da presidência.
            ${formData.get('enviar_presidencia') === '1' ? 'O envio será feito automaticamente após a filiação.' : 'Você precisará enviar manualmente para aprovação após a filiação.'}
        </p>
    </div>
</div>`;
    }

    container.innerHTML = html;
}

// Validação do formulário completo
function validarFormularioCompleto() {
    const form = document.getElementById('formAssociado');
    if (!form) return false;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        // Pula validação da foto se for modo edição
        if (isEdit && field.name === 'foto') {
            return;
        }

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
        alert(message); // Fallback
        return;
    }

    const alertId = 'alert-' + Date.now();

    // Formata mensagem com quebras de linha
    const formattedMessage = message.replace(/\n/g, '<br>');

    const alertHtml = `
<div id="${alertId}" class="alert-custom alert-${type}" style="white-space: pre-line;">
    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
    <span>${formattedMessage}</span>
</div>
`;

    alertContainer.insertAdjacentHTML('beforeend', alertHtml);

    // Remove após 7 segundos (mais tempo para ler mensagens longas)
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }
    }, 7000);
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

// Log final
console.log('JavaScript carregado completamente!');