/**
 * cadastroForm.js - JavaScript VERSÃO SEM CAMPOS OBRIGATÓRIOS
 * Todos os campos são opcionais
 * ATUALIZADO: Campos financeiros cinzas para Agregado + Maiúsculas em Dados Militares
 */

// Estado do formulário
let currentStep = 1;
const totalSteps = 6;
let dependenteIndex = 0;

// Dados da página
const isEdit = window.pageData ? window.pageData.isEdit : false;
const associadoId = window.pageData ? window.pageData.associadoId : null;
const isSocioAgregado = window.pageData ? window.pageData.isSocioAgregado : false;

// Dados carregados dos serviços
let servicosCarregados = null;

// VARIÁVEIS GLOBAIS PARA DADOS DOS SERVIÇOS
let regrasContribuicao = [];
let servicosData = [];
let tiposAssociadoData = [];
let dadosCarregados = false;

// Estados de salvamento por step
let stepsSalvos = new Set();
let salvandoStep = false;

// Inicialização
document.addEventListener('DOMContentLoaded', function () {
    console.log('=== INICIANDO FORMULÁRIO (TODOS CAMPOS OPCIONAIS) ===');
    console.log('Modo edição:', isEdit, 'ID:', associadoId);

    // Atalho ESC para voltar ao dashboard
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (confirm('Deseja voltar ao dashboard? Os dados não salvos serão perdidos.')) {
                window.location.href = 'dashboard.php';
            }
        }
    });

    // Carrega dados de serviços do banco
    carregarDadosServicos()
        .then(() => {
            console.log('✓ Dados de serviços carregados');

            // Máscaras
            aplicarMascaras();

            // Select2
            inicializarSelect2();

            // Preview de arquivos
            inicializarUploadPreviews();

            // Event listeners dos serviços
            configurarListenersServicos();

            // NOVO: Aplicar maiúsculas nos dados militares
            aplicarMaiusculasDadosMilitares();

            // Carrega dados dos serviços se estiver editando
            if (isEdit && associadoId) {
                setTimeout(() => {
                    carregarServicosAssociado();
                }, 1000);
            }

            // Atualiza interface
            updateProgressBar();
            updateNavigationButtons();
            setTimeout(() => {
                inicializarNavegacaoSteps();
            }, 1000);

            // Se for modo edição e houver dependentes já carregados
            const dependentesExistentes = document.querySelectorAll('.dependente-card');
            if (dependentesExistentes.length > 0) {
                dependenteIndex = dependentesExistentes.length;
            }

            console.log('✓ Formulário inicializado com sucesso!');

        })
        .catch(error => {
            console.error('Erro ao carregar dados de serviços:', error);
            showAlert('Erro ao carregar dados do sistema. Algumas funcionalidades podem não funcionar.', 'warning');
        });
});

// Aplicar máscaras
function aplicarMascaras() {
    console.log('Aplicando máscaras...');
    
    $('#cpf').mask('000.000.000-00', { placeholder: '000.000.000-00' });
    $('#cpfTitular').mask('000.000.000-00', { placeholder: '000.000.000-00' });
    $('#telefone').mask('(00) 00000-0000', { placeholder: '(00) 00000-0000' });
    $('#celular').mask('(00) 00000-0000', { placeholder: '(00) 00000-0000' });
    $('#cep').mask('00000-000', { placeholder: '00000-000' });
    
    console.log('✓ Máscaras aplicadas');
}

// Inicializar Select2
function inicializarSelect2() {
    console.log('Inicializando Select2...');
    
    $('.form-select').not('#corporacao, #patente, #categoria, #lotacao').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        allowClear: true,
        placeholder: function() {
            return $(this).attr('placeholder') || 'Selecione...';
        }
    });
    
    // Select2 com digitação livre para campos militares
    $('#corporacao, #patente, #categoria, #lotacao').each(function() {
        $(this).select2({
            language: 'pt-BR',
            theme: 'default',
            width: '100%',
            allowClear: true,
            tags: true,
            placeholder: 'Selecione ou digite...',
            createTag: function (params) {
                var term = $.trim(params.term);
                if (term === '') return null;
                // Converter para maiúsculas ao criar tag
                return { id: term.toUpperCase(), text: term.toUpperCase(), newTag: true };
            }
        });
    });
    
    console.log('✓ Select2 inicializado');
}

// Inicializar uploads e previews
function inicializarUploadPreviews() {
    console.log('Configurando uploads...');
    
    // Preview de foto
    const fotoInput = document.getElementById('foto');
    if (fotoInput) {
        fotoInput.addEventListener('change', function (e) {
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
    }

    // Preview da ficha assinada
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
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                    };
                    reader.readAsDataURL(file);
                }
            }
        });
    }
    
    console.log('✓ Uploads configurados');
}

// Controlar serviço jurídico por tipo de associado
function controlarServicoJuridico() {
    const tipoAssociado = document.getElementById('tipoAssociadoServico')?.value;
    const servicoJuridicoCheckbox = document.getElementById('servicoJuridico');
    const servicoJuridicoItem = document.getElementById('servicoJuridicoItem');
    const badgeJuridico = document.getElementById('badgeJuridico');
    const mensagemContainer = document.getElementById('mensagemRestricaoJuridico');
    
    const tiposSemJuridico = ['Benemérito', 'Benemerito', 'Agregado'];
    
    // NOVO: Controlar campos financeiros para Agregado
    controlarCamposFinanceiroAgregado(tipoAssociado === 'Agregado');
    
    if (tiposSemJuridico.includes(tipoAssociado)) {
        if (servicoJuridicoCheckbox) {
            servicoJuridicoCheckbox.disabled = true;
            servicoJuridicoCheckbox.checked = false;
        }
        
        if (servicoJuridicoItem) {
            servicoJuridicoItem.style.opacity = '0.5';
            servicoJuridicoItem.style.pointerEvents = 'none';
        }
        
        if (badgeJuridico) {
            badgeJuridico.style.background = '#dc3545';
            badgeJuridico.textContent = 'INDISPONÍVEL';
        }
        
        if (mensagemContainer) {
            mensagemContainer.style.display = 'block';
            mensagemContainer.innerHTML = `
                <div style="background: #f8d7da; color: #721c24; padding: 0.75rem; border-radius: 6px; font-size: 0.8rem; margin-top: 0.75rem; border-left: 4px solid #dc3545;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Associados do tipo "${tipoAssociado}" não têm direito ao serviço jurídico.
                </div>
            `;
        }
        
        zerarValoresJuridico();
        
    } else {
        if (servicoJuridicoCheckbox) {
            servicoJuridicoCheckbox.disabled = false;
        }
        
        if (servicoJuridicoItem) {
            servicoJuridicoItem.style.opacity = '1';
            servicoJuridicoItem.style.pointerEvents = 'auto';
        }
        
        if (badgeJuridico) {
            badgeJuridico.style.background = 'var(--info)';
            badgeJuridico.textContent = 'OPCIONAL';
        }
        
        if (mensagemContainer) {
            mensagemContainer.style.display = 'none';
            mensagemContainer.innerHTML = '';
        }
    }
}

// NOVA FUNÇÃO: Controlar campos financeiros quando tipo é Agregado
function controlarCamposFinanceiroAgregado(isAgregado) {
    // Lista de IDs dos campos financeiros que devem ser desabilitados para agregados
    const camposFinanceiros = [
        'tipoAssociado',
        'situacaoFinanceira', 
        'vinculoServidor',
        'localDebito',
        'agencia',
        'operacao',
        'contaCorrente',
        'doador'
    ];
    
    camposFinanceiros.forEach(campoId => {
        const elemento = document.getElementById(campoId);
        if (elemento) {
            if (isAgregado) {
                elemento.disabled = true;
                elemento.style.backgroundColor = '#e9ecef';
                elemento.style.color = '#6c757d';
                elemento.style.cursor = 'not-allowed';
                elemento.style.opacity = '0.6';
                
                // Para Select2, precisa atualizar também
                if (typeof $ !== 'undefined' && $(elemento).hasClass('select2-hidden-accessible')) {
                    $(elemento).prop('disabled', true).trigger('change');
                }
            } else {
                elemento.disabled = false;
                elemento.style.backgroundColor = '';
                elemento.style.color = '';
                elemento.style.cursor = '';
                elemento.style.opacity = '';
                
                // Para Select2
                if (typeof $ !== 'undefined' && $(elemento).hasClass('select2-hidden-accessible')) {
                    $(elemento).prop('disabled', false).trigger('change');
                }
            }
        }
    });
    
    // Atualiza o aviso de agregado
    const avisoAgregado = document.getElementById('avisoAgregadoFinanceiro');
    if (avisoAgregado) {
        avisoAgregado.style.display = isAgregado ? 'block' : 'none';
        if (isAgregado) {
            avisoAgregado.innerHTML = `
                <i class="fas fa-info-circle" style="color: #856404;"></i>
                <span style="color: #856404; font-weight: 500;">
                    Para sócios agregados, os dados financeiros são gerenciados pelo titular. 
                    Os campos abaixo estão desabilitados.
                </span>
            `;
        }
    }
}

// NOVA FUNÇÃO: Aplicar maiúsculas nos campos de Dados Militares
function aplicarMaiusculasDadosMilitares() {
    console.log('Aplicando maiúsculas nos dados militares...');
    
    // Campo de texto simples - Unidade
    const campoUnidade = document.getElementById('unidade');
    if (campoUnidade) {
        // CSS para exibir em maiúsculas
        campoUnidade.style.textTransform = 'uppercase';
        
        // Converter valor para maiúsculas ao digitar
        campoUnidade.addEventListener('input', function() {
            const start = this.selectionStart;
            const end = this.selectionEnd;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(start, end);
        });
        
        // Converter ao perder foco também
        campoUnidade.addEventListener('blur', function() {
            this.value = this.value.toUpperCase();
        });
    }
    
    // Para campos Select2 com tags (permite digitação livre)
    const camposSelect2Militares = ['corporacao', 'patente', 'categoria', 'lotacao'];
    
    camposSelect2Militares.forEach(campoId => {
        const elemento = document.getElementById(campoId);
        if (elemento && typeof $ !== 'undefined') {
            // Interceptar quando uma tag é criada/selecionada
            $(elemento).on('select2:select', function(e) {
                if (e.params && e.params.data && e.params.data.newTag) {
                    // É uma tag nova digitada pelo usuário - converter para maiúsculas
                    const upperValue = e.params.data.text.toUpperCase();
                    $(this).val(upperValue).trigger('change.select2');
                }
            });
            
            // Aplicar CSS para input do Select2 quando aberto
            $(elemento).on('select2:open', function() {
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-search__field');
                    if (searchField) {
                        searchField.style.textTransform = 'uppercase';
                        
                        // Handler para converter enquanto digita
                        const inputHandler = function() {
                            const start = this.selectionStart;
                            const end = this.selectionEnd;
                            this.value = this.value.toUpperCase();
                            this.setSelectionRange(start, end);
                        };
                        
                        // Remover handler antigo se existir e adicionar novo
                        searchField.removeEventListener('input', searchField._upperHandler);
                        searchField._upperHandler = inputHandler;
                        searchField.addEventListener('input', inputHandler);
                    }
                }, 10);
            });
        }
    });
    
    console.log('✓ Maiúsculas aplicadas nos dados militares');
}

function zerarValoresJuridico() {
    updateElementSafe('valorJuridico', '0', 'value');
    updateElementSafe('percentualAplicadoJuridico', '0', 'value');
    updateElementSafe('valorFinalJuridico', '0,00');
    updateElementSafe('percentualJuridico', '0');
}

// Configurar listeners dos serviços
function configurarListenersServicos() {
    const tipoAssociadoEl = document.getElementById('tipoAssociadoServico');
    const servicoJuridicoEl = document.getElementById('servicoJuridico');

    if (tipoAssociadoEl) {
        tipoAssociadoEl.addEventListener('change', function() {
            controlarServicoJuridico();
            calcularServicos();
        });
    }

    if (servicoJuridicoEl) {
        servicoJuridicoEl.addEventListener('change', calcularServicos);
    }
}

// Carrega dados de serviços via AJAX
function carregarDadosServicos() {
    return fetch('../api/buscar_dados_servicos.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                regrasContribuicao = data.regras || [];
                servicosData = data.servicos || [];
                tiposAssociadoData = data.tipos_associado || [];
                dadosCarregados = true;
                preencherSelectTiposAssociado();
                return true;
            } else {
                throw new Error(data.message || 'Erro na API');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados de serviços:', error);
            useHardcodedData();
            preencherSelectTiposAssociado();
            return true;
        });
}

function preencherSelectTiposAssociado() {
    const select = document.getElementById('tipoAssociadoServico');
    if (!select) return;

    while (select.children.length > 1) {
        select.removeChild(select.lastChild);
    }

    tiposAssociadoData.forEach(tipo => {
        const option = document.createElement('option');
        option.value = tipo;
        
        const tiposSemJuridico = ['Benemérito', 'Benemerito', 'Agregado'];
        if (tiposSemJuridico.includes(tipo)) {
            option.textContent = `${tipo} (Sem serviço jurídico)`;
            option.setAttribute('data-restricao', 'sem-juridico');
        } else {
            option.textContent = tipo;
        }
        
        select.appendChild(option);
    });

    if (typeof $ !== 'undefined' && $('#tipoAssociadoServico').hasClass('select2-hidden-accessible')) {
        $('#tipoAssociadoServico').trigger('change');
    }
}

function useHardcodedData() {
    servicosData = [
        { id: "1", nome: "Social", valor_base: "173.10" },
        { id: "2", nome: "Jurídico", valor_base: "43.28" }
    ];

    regrasContribuicao = [
        { tipo_associado: "Contribuinte", servico_id: "1", percentual_valor: "100.00" },
        { tipo_associado: "Contribuinte", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Aluno", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Aluno", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Agregado", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Agregado", servico_id: "2", percentual_valor: "0.00" },
        { tipo_associado: "Remido", servico_id: "1", percentual_valor: "0.00" },
        { tipo_associado: "Remido", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Benemerito", servico_id: "1", percentual_valor: "0.00" },
        { tipo_associado: "Benemerito", servico_id: "2", percentual_valor: "0.00" }
    ];

    tiposAssociadoData = ["Contribuinte", "Aluno", "Soldado 2ª Classe", "Soldado 1ª Classe", "Agregado", "Remido 50%", "Remido", "Benemerito"];
    dadosCarregados = true;
}

function carregarServicosAssociado() {
    if (!associadoId) return;

    fetch(`../api/buscar_servicos_associado.php?associado_id=${associadoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                servicosCarregados = data.data;
                preencherDadosServicos(data.data);
            } else {
                setTimeout(() => {
                    if (document.getElementById('tipoAssociadoServico').value) {
                        controlarServicoJuridico();
                        calcularServicos();
                    }
                }, 500);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar serviços:', error);
        });
}

function preencherDadosServicos(dadosServicos) {
    resetarCalculos();

    if (dadosServicos.tipo_associado_servico) {
        const selectElement = document.getElementById('tipoAssociadoServico');
        if (selectElement) {
            selectElement.value = dadosServicos.tipo_associado_servico;
            if (typeof $ !== 'undefined' && $('#tipoAssociadoServico').length) {
                $('#tipoAssociadoServico').trigger('change');
            }
        }
    }

    if (dadosServicos.servicos && dadosServicos.servicos.social) {
        const social = dadosServicos.servicos.social;
        updateElementSafe('valorSocial', social.valor_aplicado, 'value');
        updateElementSafe('percentualAplicadoSocial', social.percentual_aplicado, 'value');
        updateElementSafe('valorFinalSocial', parseFloat(social.valor_aplicado).toFixed(2).replace('.', ','));
        updateElementSafe('percentualSocial', parseFloat(social.percentual_aplicado).toFixed(0));
    }

    if (dadosServicos.servicos && dadosServicos.servicos.juridico) {
        const juridico = dadosServicos.servicos.juridico;
        const juridicoCheckEl = document.getElementById('servicoJuridico');
        if (juridicoCheckEl) juridicoCheckEl.checked = true;

        updateElementSafe('valorJuridico', juridico.valor_aplicado, 'value');
        updateElementSafe('percentualAplicadoJuridico', juridico.percentual_aplicado, 'value');
        updateElementSafe('valorFinalJuridico', parseFloat(juridico.valor_aplicado).toFixed(2).replace('.', ','));
        updateElementSafe('percentualJuridico', parseFloat(juridico.percentual_aplicado).toFixed(0));
    }

    const totalMensal = dadosServicos.valor_total_mensal || 0;
    updateElementSafe('valorTotalGeral', parseFloat(totalMensal).toFixed(2).replace('.', ','));

    setTimeout(() => {
        controlarServicoJuridico();
    }, 100);
}

function calcularServicos() {
    controlarServicoJuridico();

    if (!dadosCarregados) {
        setTimeout(calcularServicos, 500);
        return;
    }

    const tipoAssociadoEl = document.getElementById('tipoAssociadoServico');
    const servicoJuridicoEl = document.getElementById('servicoJuridico');

    if (!tipoAssociadoEl || !servicoJuridicoEl) return;

    const tipoAssociado = tipoAssociadoEl.value;
    const servicoJuridicoChecked = servicoJuridicoEl.checked && !servicoJuridicoEl.disabled;

    if (!tipoAssociado) {
        resetarCalculos();
        return;
    }

    const regrasSocial = regrasContribuicao.filter(r => r.tipo_associado === tipoAssociado && r.servico_id == 1);
    const regrasJuridico = regrasContribuicao.filter(r => r.tipo_associado === tipoAssociado && r.servico_id == 2);

    let valorTotalGeral = 0;

    if (regrasSocial.length > 0) {
        const regra = regrasSocial[0];
        const servicoSocial = servicosData.find(s => s.id == 1);
        const valorBase = parseFloat(servicoSocial?.valor_base || 173.10);
        const percentual = parseFloat(regra.percentual_valor);
        const valorFinal = (valorBase * percentual) / 100;

        updateElementSafe('valorBaseSocial', valorBase.toFixed(2).replace('.', ','));
        updateElementSafe('percentualSocial', percentual.toFixed(0));
        updateElementSafe('valorFinalSocial', valorFinal.toFixed(2).replace('.', ','));
        updateElementSafe('valorSocial', valorFinal.toFixed(2), 'value');
        updateElementSafe('percentualAplicadoSocial', percentual.toFixed(2), 'value');

        valorTotalGeral += valorFinal;
    }

    if (servicoJuridicoChecked && regrasJuridico.length > 0) {
        const regra = regrasJuridico[0];
        const servicoJuridico = servicosData.find(s => s.id == 2);
        const valorBase = parseFloat(servicoJuridico?.valor_base || 43.28);
        const percentual = parseFloat(regra.percentual_valor);
        const valorFinal = (valorBase * percentual) / 100;

        updateElementSafe('valorBaseJuridico', valorBase.toFixed(2).replace('.', ','));
        updateElementSafe('percentualJuridico', percentual.toFixed(0));
        updateElementSafe('valorFinalJuridico', valorFinal.toFixed(2).replace('.', ','));
        updateElementSafe('valorJuridico', valorFinal.toFixed(2), 'value');
        updateElementSafe('percentualAplicadoJuridico', percentual.toFixed(2), 'value');

        valorTotalGeral += valorFinal;
    } else {
        updateElementSafe('percentualJuridico', '0');
        updateElementSafe('valorFinalJuridico', '0,00');
        updateElementSafe('valorJuridico', '0', 'value');
        updateElementSafe('percentualAplicadoJuridico', '0', 'value');
    }

    updateElementSafe('valorTotalGeral', valorTotalGeral.toFixed(2).replace('.', ','));
}

function resetarCalculos() {
    const servicoSocial = servicosData.find(s => s.id == 1);
    const servicoJuridico = servicosData.find(s => s.id == 2);

    const valorBaseSocial = servicoSocial ? parseFloat(servicoSocial.valor_base).toFixed(2).replace('.', ',') : '173,10';
    const valorBaseJuridico = servicoJuridico ? parseFloat(servicoJuridico.valor_base).toFixed(2).replace('.', ',') : '43,28';

    updateElementSafe('valorBaseSocial', valorBaseSocial);
    updateElementSafe('percentualSocial', '0');
    updateElementSafe('valorFinalSocial', '0,00');
    updateElementSafe('valorSocial', '0', 'value');
    updateElementSafe('percentualAplicadoSocial', '0', 'value');

    updateElementSafe('valorBaseJuridico', valorBaseJuridico);
    updateElementSafe('percentualJuridico', '0');
    updateElementSafe('valorFinalJuridico', '0,00');
    updateElementSafe('valorJuridico', '0', 'value');
    updateElementSafe('percentualAplicadoJuridico', '0', 'value');

    updateElementSafe('valorTotalGeral', '0,00');
}

function updateElementSafe(elementId, value, property = 'textContent') {
    const element = document.getElementById(elementId);
    if (element) {
        if (property === 'value') {
            element.value = value;
        } else {
            element[property] = value;
        }
    }
}

// ===== SALVAMENTO MULTI-STEP =====

function salvarStepAtual() {
    if (salvandoStep) return;

    console.log(`=== SALVANDO STEP ${currentStep} ===`);

    const isAgregado = document.getElementById('isAgregado')?.checked || isSocioAgregado;

    // Se for agregado em modo edição, usa salvarAssociado()
    if (isAgregado && isEdit) {
        salvarAssociado();
        return;
    }

    // Para novos cadastros, step 1 precisa criar o registro primeiro
    if (!isEdit && !window.pageData.associadoId && currentStep === 1) {
        salvarNovoAssociadoPrimeiroPasso();
        return;
    }

    salvandoStep = true;
    mostrarEstadoSalvando();

    const formData = criarFormDataStep(currentStep);
    
    if (!formData) {
        esconderEstadoSalvando();
        salvandoStep = false;
        return;
    }

    const associadoAtualId = isEdit ? associadoId : window.pageData.associadoId;
    const url = `../api/atualizar_associado.php?id=${associadoAtualId}`;

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        esconderEstadoSalvando();
        salvandoStep = false;

        if (data.status === 'success') {
            stepsSalvos.add(currentStep);
            
            if (!isEdit && !window.pageData.associadoId && data.data && data.data.id) {
                window.pageData.associadoId = data.data.id;
                window.pageData.isEdit = true;
                
                let hiddenId = document.getElementById('associado_id_hidden');
                if (!hiddenId) {
                    hiddenId = document.createElement('input');
                    hiddenId.type = 'hidden';
                    hiddenId.name = 'id';
                    hiddenId.id = 'associado_id_hidden';
                    document.getElementById('formAssociado').appendChild(hiddenId);
                }
                hiddenId.value = data.data.id;
            }

            mostrarSucessoSalvamento();
            atualizarIndicadoresStep();

            const stepNames = {
                1: 'Dados Pessoais',
                2: 'Dados Militares', 
                3: 'Endereço',
                4: 'Financeiro',
                5: 'Dependentes'
            };
            
            showAlert(`${stepNames[currentStep]} salvos com sucesso!`, 'success');

        } else {
            showAlert(data.message || 'Erro ao salvar dados!', 'error');
        }
    })
    .catch(error => {
        esconderEstadoSalvando();
        salvandoStep = false;
        console.error('Erro na requisição:', error);
        showAlert('Erro de comunicação com o servidor!', 'error');
    });
}

function criarFormDataStep(step) {
    const form = document.getElementById('formAssociado');
    const formData = new FormData();

    if (isEdit || window.pageData.associadoId) {
        formData.append('id', isEdit ? associadoId : window.pageData.associadoId);
    }

    // Campos básicos sempre enviados
    const camposBasicos = ['nome', 'cpf', 'rg', 'telefone', 'situacao'];
    camposBasicos.forEach(campo => {
        const element = form.elements[campo];
        if (element && element.value) {
            formData.append(campo, element.value);
        }
    });

    // Campos específicos por step
    switch(step) {
        case 1:
            const camposStep1 = ['nome', 'nasc', 'sexo', 'estadoCivil', 'rg', 'cpf', 'telefone', 'email', 'escolaridade', 'indicacao', 'situacao', 'dataFiliacao'];
            
            camposStep1.forEach(campo => {
                const element = form.elements[campo];
                if (element) {
                    if (element.type === 'radio') {
                        const checked = form.querySelector(`input[name="${campo}"]:checked`);
                        if (checked) formData.append(campo, checked.value);
                    } else if (element.value) {
                        formData.append(campo, element.value);
                    }
                }
            });

            const isAgregado = document.getElementById('isAgregado')?.checked;
            if (isAgregado) {
                const cpfTitular = document.getElementById('cpfTitular')?.value;
                if (cpfTitular) {
                    formData.append('cpfTitular', cpfTitular);
                    formData.append('socioTitularCpf', cpfTitular);
                }
                formData.append('isAgregado', '1');
            }

            const fotoFile = document.getElementById('foto').files[0];
            if (fotoFile) formData.append('foto', fotoFile);

            const fichaFile = document.getElementById('ficha_assinada')?.files[0];
            if (fichaFile) {
                formData.append('ficha_assinada', fichaFile);
                formData.append('enviar_presidencia', '1');
            }
            break;

        case 2:
            const camposStep2 = ['corporacao', 'patente', 'categoria', 'lotacao', 'unidade'];
            camposStep2.forEach(campo => {
                const element = form.elements[campo];
                if (element && element.value) {
                    // Garantir maiúsculas ao salvar
                    formData.append(campo, element.value.toUpperCase());
                }
            });
            break;

        case 3:
            const camposStep3 = ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade'];
            camposStep3.forEach(campo => {
                const element = form.elements[campo];
                if (element && element.value) formData.append(campo, element.value);
            });
            break;

        case 4:
            const camposStep4 = ['tipoAssociadoServico', 'tipoAssociado', 'situacaoFinanceira', 'vinculoServidor', 'localDebito', 'agencia', 'operacao', 'contaCorrente', 'doador'];
            
            camposStep4.forEach(campo => {
                const element = form.elements[campo];
                if (element && element.value) formData.append(campo, element.value);
            });

            formData.append('servicoSocial', '1');
            formData.append('valorSocial', document.getElementById('valorSocial')?.value || '0');
            formData.append('percentualAplicadoSocial', document.getElementById('percentualAplicadoSocial')?.value || '0');
            
            const servicoJuridico = document.getElementById('servicoJuridico');
            if (servicoJuridico && servicoJuridico.checked && !servicoJuridico.disabled) {
                formData.append('servicoJuridico', '2');
                formData.append('valorJuridico', document.getElementById('valorJuridico')?.value || '0');
                formData.append('percentualAplicadoJuridico', document.getElementById('percentualAplicadoJuridico')?.value || '0');
            }
            break;

        case 5:
            const dependentesCards = document.querySelectorAll('.dependente-card');
            dependentesCards.forEach((card, index) => {
                const inputs = card.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.value) formData.append(input.name, input.value);
                });
            });
            break;
    }

    return formData;
}

function salvarNovoAssociadoPrimeiroPasso() {
    salvandoStep = true;
    mostrarEstadoSalvando();

    const formData = new FormData(document.getElementById('formAssociado'));

    const isAgregado = document.getElementById('isAgregado')?.checked;
    let url = '../api/criar_associado.php';
    if (isAgregado) {
        url = '../api/criar_agregado.php';
        const cpfTitular = document.getElementById('cpfTitular')?.value;
        if (cpfTitular) {
            formData.append('socioTitularCpf', cpfTitular);
        }
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        esconderEstadoSalvando();
        salvandoStep = false;

        if (data.status === 'success') {
            window.pageData.isEdit = true;
            window.pageData.associadoId = data.data.associado_id || data.data.id;
            
            const hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = 'id';
            hiddenId.id = 'associado_id_hidden';
            hiddenId.value = window.pageData.associadoId;
            document.getElementById('formAssociado').appendChild(hiddenId);

            stepsSalvos.add(1);
            mostrarSucessoSalvamento();
            atualizarIndicadoresStep();

            showAlert('Dados Pessoais salvos com sucesso! Associado criado.', 'success');

        } else {
            showAlert(data.message || 'Erro ao criar associado!', 'error');
        }
    })
    .catch(error => {
        esconderEstadoSalvando();
        salvandoStep = false;
        console.error('Erro na requisição:', error);
        showAlert('Erro de comunicação com o servidor!', 'error');
    });
}

function mostrarEstadoSalvando() {
    const btn = document.getElementById('btnSalvarStep');
    const saveText = btn?.querySelector('.save-text');
    
    if (btn && saveText) {
        btn.classList.add('saving');
        btn.disabled = true;
        saveText.textContent = 'Salvando...';
    }
}

function esconderEstadoSalvando() {
    const btn = document.getElementById('btnSalvarStep');
    const saveText = btn?.querySelector('.save-text');
    
    if (btn && saveText) {
        btn.classList.remove('saving');
        btn.disabled = false;
        saveText.textContent = 'Salvar';
    }
}

function mostrarSucessoSalvamento() {
    const indicator = document.getElementById('saveIndicator');
    const btn = document.getElementById('btnSalvarStep');
    
    if (indicator) {
        indicator.classList.add('show');
        setTimeout(() => indicator.classList.remove('show'), 3000);
    }
    
    if (btn) {
        btn.classList.add('saved');
        setTimeout(() => btn.classList.remove('saved'), 2000);
    }
}

function atualizarIndicadoresStep() {
    stepsSalvos.forEach(stepNum => {
        const stepElement = document.querySelector(`[data-step="${stepNum}"]`);
        if (stepElement) stepElement.classList.add('saved');
    });
}

// ===== NAVEGAÇÃO =====

function proximoStep() {
    // SEM VALIDAÇÃO OBRIGATÓRIA - apenas avança
    if (currentStep < totalSteps) {
        document.querySelector(`[data-step="${currentStep}"]`).classList.add('completed');
        irParaStep(currentStep + 1);
        
        if (currentStep === totalSteps) {
            preencherRevisao();
        }
    }
}

function voltarStep() {
    if (currentStep > 1) {
        irParaStep(currentStep - 1);
    }
}

function mostrarStep(step) {
    document.querySelectorAll('.section-card').forEach(card => {
        card.classList.remove('active');
    });

    document.querySelector(`.section-card[data-step="${step}"]`).classList.add('active');

    updateProgressBar();
    updateNavigationButtons();
    setTimeout(() => inicializarNavegacaoSteps(), 1000);

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
    const btnSalvarStep = document.getElementById('btnSalvarStep');

    if (btnVoltar) {
        btnVoltar.style.display = currentStep === 1 ? 'none' : 'flex';
    }

    if (btnSalvarStep) {
        if (currentStep < totalSteps) {
            btnSalvarStep.style.display = 'flex';
            
            const saveText = btnSalvarStep.querySelector('.save-text');
            if (saveText) {
                saveText.textContent = stepsSalvos.has(currentStep) ? 'Atualizar' : 'Salvar';
            }
        } else {
            btnSalvarStep.style.display = 'none';
        }
    }

    if (currentStep === totalSteps) {
        if (btnProximo) btnProximo.style.display = 'none';
        if (btnSalvar) btnSalvar.style.display = 'flex';
    } else {
        if (btnProximo) btnProximo.style.display = 'flex';
        if (btnSalvar) btnSalvar.style.display = 'none';
    }
}

function irParaStep(numeroStep) {
    if (numeroStep < 1 || numeroStep > totalSteps) return;
    currentStep = numeroStep;
    mostrarStep(currentStep);
}

function inicializarNavegacaoSteps() {
    document.querySelectorAll('.step').forEach(step => {
        step.style.cursor = 'pointer';
        step.style.transition = 'all 0.3s ease';
        
        step.addEventListener('click', function() {
            const numeroStep = parseInt(this.getAttribute('data-step'));
            if (numeroStep) {
                irParaStep(numeroStep);
                this.style.transform = 'scale(0.95)';
                setTimeout(() => this.style.transform = 'scale(1)', 150);
            }
        });
        
        step.addEventListener('mouseenter', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'scale(1.05)';
                this.style.opacity = '0.8';
            }
        });
        
        step.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.opacity = '1';
        });
    });
}

// ===== FUNÇÕES AUXILIARES =====

function validarCPF(cpf) {
    cpf = cpf.replace(/[^\d]/g, '');
    if (cpf.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(cpf)) return false;

    let soma = 0;
    for (let i = 1; i <= 9; i++) {
        soma += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    }
    let resto = (soma * 10) % 11;
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

            if (document.getElementById('endereco')) document.getElementById('endereco').value = data.logradouro;
            if (document.getElementById('bairro')) document.getElementById('bairro').value = data.bairro;
            if (document.getElementById('cidade')) document.getElementById('cidade').value = data.localidade;
            if (document.getElementById('numero')) document.getElementById('numero').focus();
        })
        .catch(error => {
            hideLoading();
            console.error('Erro ao buscar CEP:', error);
            showAlert('Erro ao buscar CEP!', 'error');
        });
}

// ===== DEPENDENTES =====

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
                    <input type="text" class="form-input" name="dependentes[${novoIndex}][nome]" placeholder="Nome do dependente">
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
        document.querySelectorAll('.dependente-card').forEach((card, index) => {
            const numberEl = card.querySelector('.dependente-number');
            if (numberEl) numberEl.textContent = `Dependente ${index + 1}`;
        });
    }, 300);
}

// ===== SALVAR ASSOCIADO COMPLETO =====

function salvarAssociado() {
    console.log('=== SALVANDO ASSOCIADO COMPLETO ===');
    
    showLoading();
    
    const formulario = document.querySelector('form');
    
    if (!formulario) {
        hideLoading();
        showAlert('Erro: Formulário não encontrado!', 'error');
        return;
    }
    
    const formData = new FormData(formulario);
    
    // Adiciona campos manualmente
    const camposManuais = [
        'nome', 'cpf', 'rg', 'nasc', 'telefone', 'celular', 'email',
        'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'cep',
        'localDebito', 'agencia', 'contaCorrente', 'estadoCivil', 'dataFiliacao',
        'situacao', 'escolaridade', 'operacao', 'indicacao'
    ];

    camposManuais.forEach(campo => {
        const elemento = document.getElementById(campo);
        if (elemento && elemento.value) {
            formData.set(campo, elemento.value);
        }
    });

    // Celular fallback
    if (!formData.get('celular')) {
        const telefone = document.getElementById('telefone');
        if (telefone && telefone.value) {
            formData.set('celular', telefone.value);
        }
    }

    // Campos Select2 - garantir maiúsculas para campos militares
    const camposSelect2 = ['corporacao', 'patente', 'categoria', 'lotacao', 'unidade', 'tipoAssociadoServico', 'tipoAssociado', 'situacaoFinanceira', 'vinculoServidor'];
    const camposMilitaresUpper = ['corporacao', 'patente', 'categoria', 'lotacao', 'unidade'];
    
    camposSelect2.forEach(campo => {
        const elemento = document.getElementById(campo);
        if (elemento && elemento.value) {
            // Aplicar maiúsculas apenas nos campos militares
            if (camposMilitaresUpper.includes(campo)) {
                formData.set(campo, elemento.value.toUpperCase());
            } else {
                formData.set(campo, elemento.value);
            }
        }
    });

    // Sexo
    const sexoRadio = document.querySelector('input[name="sexo"]:checked');
    if (sexoRadio) formData.set('sexo', sexoRadio.value);
    
    // Agregado
    const isAgregado = document.getElementById('isAgregado')?.checked || isSocioAgregado;
    if (isAgregado) {
        const cpfTitular = document.getElementById('cpfTitular')?.value;
        if (cpfTitular) {
            formData.set('cpfTitular', cpfTitular);
            formData.set('socioTitularCpf', cpfTitular);
        }
    }
    
    // Serviços
    const servicoSocial = document.getElementById('valorSocial');
    const servicoJuridico = document.getElementById('servicoJuridico');
    
    if (servicoSocial && servicoSocial.value) {
        formData.set('servicoSocial', '1');
        formData.set('valorSocial', servicoSocial.value);
        formData.set('percentualAplicadoSocial', document.getElementById('percentualAplicadoSocial')?.value || '0');
    }
    
    if (servicoJuridico && servicoJuridico.checked && !servicoJuridico.disabled) {
        formData.set('servicoJuridico', '2');
        formData.set('valorJuridico', document.getElementById('valorJuridico')?.value || '0');
        formData.set('percentualAplicadoJuridico', document.getElementById('percentualAplicadoJuridico')?.value || '0');
    }
    
    // Define URL
    const associadoIdAtual = document.getElementById('associadoId')?.value || (isEdit ? associadoId : window.pageData.associadoId);
    let url;
    
    if (isAgregado) {
        url = '../api/criar_agregado.php';
    } else {
        url = associadoIdAtual ? 
            `../api/atualizar_associado.php?id=${associadoIdAtual}` : 
            '../api/criar_associado.php';
    }
    
    console.log('📡 Enviando para:', url);
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Erro ao fazer parse JSON:', e);
            hideLoading();
            showAlert('Resposta inválida do servidor.', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.status === 'success') {
            showAlert(data.message || 'Salvo com sucesso!', 'success');
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            let erro = data.message || 'Erro ao salvar';
            if (data.errors && Array.isArray(data.errors)) {
                erro += ':\n• ' + data.errors.join('\n• ');
            }
            showAlert(erro, 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        hideLoading();
        showAlert('Erro ao comunicar com o servidor: ' + error.message, 'error');
    });
}

// ===== REVISÃO =====

function preencherRevisao() {
    const container = document.getElementById('revisaoContainer');
    if (!container) return;

    const form = document.getElementById('formAssociado');
    const formData = new FormData(form);

    const dadosRevisao = {
        nome: formData.get('nome') || '-',
        cpf: formData.get('cpf') || '-',
        telefone: formData.get('telefone') || '-',
        corporacao: formData.get('corporacao') || '-',
        patente: formData.get('patente') || '-',
        tipoAssociadoServico: formData.get('tipoAssociadoServico') || '-',
        tipoAssociado: formData.get('tipoAssociado') || '-',
        valorTotal: document.getElementById('valorTotalGeral')?.textContent || '0,00'
    };

    const servicoJuridicoEl = document.getElementById('servicoJuridico');
    const statusJuridico = servicoJuridicoEl?.disabled 
        ? '❌ Não disponível para este tipo'
        : servicoJuridicoEl?.checked 
            ? '✅ Contratado' 
            : '⚪ Não contratado';

    let html = `
        <div class="overview-card" style="background: var(--gray-100); padding: 2rem; border-radius: 16px; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 1.5rem;">
                <i class="fas fa-user"></i> Resumo da Filiação
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Nome:</strong> ${dadosRevisao.nome}</p>
                    <p><strong>CPF:</strong> ${dadosRevisao.cpf}</p>
                    <p><strong>Telefone:</strong> ${dadosRevisao.telefone}</p>
                    <p><strong>Corporação:</strong> ${dadosRevisao.corporacao}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Patente:</strong> ${dadosRevisao.patente}</p>
                    <p><strong>Tipo de Associado:</strong> ${dadosRevisao.tipoAssociadoServico}</p>
                    <p><strong>Serviço Jurídico:</strong> ${statusJuridico}</p>
                    <p><strong>Valor Total Mensal:</strong> <span style="color: var(--primary); font-weight: 700;">R$ ${dadosRevisao.valorTotal}</span></p>
                </div>
            </div>
        </div>
    `;

    if (stepsSalvos.size > 0) {
        html += `
            <div style="background: #d1ecf1; border: 1px solid #b8daff; padding: 1rem; border-radius: 8px;">
                <i class="fas fa-info-circle" style="color: #0c5460;"></i>
                <strong style="color: #0c5460;">Steps já salvos:</strong>
                <span style="color: #0c5460;">${Array.from(stepsSalvos).map(s => `Step ${s}`).join(', ')}</span>
            </div>
        `;
    }

    container.innerHTML = html;
}

// ===== LOADING E ALERTS =====

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
    const formattedMessage = message.replace(/\n/g, '<br>');

    const alertHtml = `
        <div id="${alertId}" class="alert-custom alert-${type}" style="white-space: pre-line;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
            <span>${formattedMessage}</span>
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

// ===== AGREGADO =====

function toggleAgregadoCampos() {
    const isAgregado = document.getElementById('isAgregado').checked;
    const campoCpfTitular = document.getElementById('campoCpfTitular');
    const cpfTitularInput = document.getElementById('cpfTitular');
    const nomeTitularInput = document.getElementById('nomeTitularInfo');
    const erroSpan = document.getElementById('erroCpfTitular');
    const avisoFinanceiro = document.getElementById('avisoAgregadoFinanceiro');
    
    if (isAgregado) {
        if (campoCpfTitular) campoCpfTitular.style.display = 'block';
        if (avisoFinanceiro) avisoFinanceiro.style.display = 'block';
        
        // Também atualizar os campos financeiros
        controlarCamposFinanceiroAgregado(true);
    } else {
        if (campoCpfTitular) campoCpfTitular.style.display = 'none';
        if (cpfTitularInput) cpfTitularInput.value = '';
        if (nomeTitularInput) {
            nomeTitularInput.value = '';
            nomeTitularInput.style.background = '#f5f5f5';
            nomeTitularInput.style.color = '#666';
        }
        if (erroSpan) erroSpan.style.display = 'none';
        if (avisoFinanceiro) avisoFinanceiro.style.display = 'none';
        
        // Reativar campos financeiros
        controlarCamposFinanceiroAgregado(false);
    }
}

function buscarNomeTitularPorCpf() {
    const cpfInput = document.getElementById('cpfTitular');
    const nomeInput = document.getElementById('nomeTitularInfo');
    const erroSpan = document.getElementById('erroCpfTitular');
    
    if (!cpfInput || !nomeInput || !erroSpan) return;

    const cpf = cpfInput.value.replace(/\D/g, '');
    
    if (cpf.length !== 11) {
        nomeInput.value = '';
        nomeInput.style.background = '#f5f5f5';
        nomeInput.style.color = '#666';
        erroSpan.style.display = 'block';
        erroSpan.textContent = 'CPF deve ter 11 dígitos';
        return;
    }
    
    nomeInput.value = 'Buscando...';
    nomeInput.style.background = '#fff3cd';
    nomeInput.style.color = '#856404';
    erroSpan.style.display = 'none';
    
    fetch(`../api/buscar_associado_por_cpf.php?cpf=${cpf}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const titular = data.data;
                let nome = titular.titular_nome || titular.nome || '';
                let cpfFormatado = titular.titular_cpf || titular.cpf || '';
                let situacao = titular.titular_situacao || titular.situacao || '';
                
                if (cpfFormatado && cpfFormatado.length === 11) {
                    cpfFormatado = cpfFormatado.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                }
                
                if (nome && cpfFormatado) {
                    nomeInput.value = `${nome} - ${cpfFormatado}`;
                } else if (nome) {
                    nomeInput.value = nome;
                } else {
                    nomeInput.value = '';
                }
                
                if (situacao && situacao !== 'Filiado') {
                    nomeInput.style.background = '#f8d7da';
                    nomeInput.style.color = '#721c24';
                    erroSpan.style.display = 'block';
                    erroSpan.textContent = `Titular está ${situacao}. Somente titulares FILIADOS podem ter agregados.`;
                } else {
                    nomeInput.style.background = '#d4edda';
                    nomeInput.style.color = '#155724';
                    erroSpan.style.display = 'none';
                }
                
            } else {
                nomeInput.value = '';
                nomeInput.style.background = '#f8d7da';
                nomeInput.style.color = '#721c24';
                erroSpan.style.display = 'block';
                erroSpan.textContent = 'CPF não encontrado ou não é um associado filiado';
            }
        })
        .catch(error => {
            console.error('Erro na busca:', error);
            nomeInput.value = '';
            nomeInput.style.background = '#f8d7da';
            nomeInput.style.color = '#721c24';
            erroSpan.style.display = 'block';
            erroSpan.textContent = 'Erro ao buscar CPF. Tente novamente.';
        });
}

// Listener para CPF do titular
document.addEventListener('DOMContentLoaded', function() {
    const cpfInput = document.getElementById('cpfTitular');
    if (cpfInput) {
        cpfInput.addEventListener('blur', buscarNomeTitularPorCpf);
        cpfInput.addEventListener('keyup', function() {
            const cpfLimpo = this.value.replace(/\D/g, '');
            if (cpfLimpo.length === 11) {
                buscarNomeTitularPorCpf();
            }
        });
    }
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    if (!e.target.matches('input, textarea, select')) {
        if (e.key >= '1' && e.key <= '6') {
            e.preventDefault();
            irParaStep(parseInt(e.key));
        }
        else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            voltarStep();
        }
        else if (e.key === 'ArrowRight') {
            e.preventDefault();
            proximoStep();
        }
        else if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            if (currentStep < totalSteps) {
                salvarStepAtual();
            } else {
                salvarAssociado();
            }
        }
    }
});

console.log('✓ cadastroForm.js carregado - TODOS CAMPOS OPCIONAIS + AGREGADO CINZA + MAIÚSCULAS MILITARES');