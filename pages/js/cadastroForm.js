/**
 * cadastroForm.js - JavaScript Completo do Formulário de Filiação
 * Versão: 2.0 - Atualizado com correções para agregados
 * Data: 26/11/2025
 * 
 * Funcionalidades:
 * - Navegação multi-step
 * - Salvamento individual por step
 * - Validações robustas
 * - Controle de serviço jurídico
 * - Busca de agregados
 * - Detecção automática de agregados
 * - NOVO: Preenchimento automático de dados militares para agregados
 * - NOVO: Desabilitação de campos financeiros para agregados
 */

// Estado do formulário
let currentStep = 1;
const totalSteps = 6;
let dependenteIndex = 0;
const isEdit = window.pageData ? window.pageData.isEdit : false;
const associadoId = window.pageData ? window.pageData.associadoId : null;
let servicosCarregados = null;
let regrasContribuicao = [];
let servicosData = [];
let tiposAssociadoData = [];
let dadosCarregados = false;
let stepsSalvos = new Set();
let salvandoStep = false;

// Inicialização
document.addEventListener('DOMContentLoaded', function () {
    console.log('=== INICIANDO FORMULÁRIO DE FILIAÇÃO ===');
    console.log('Modo edição:', isEdit, 'ID:', associadoId);

    // Verifica se é agregado (MODO EDIÇÃO)
    if (isEdit && associadoId) {
        setTimeout(() => {
            verificarSeEhAgregadoECarregarDados();
        }, 1500);
    }

    // Configura listeners para CPF do titular (AGREGADOS)
    configurarListenersCpfTitular();

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
            aplicarMascaras();
            inicializarSelect2();
            inicializarUploadPreviews();
            setupRealtimeValidation();
            configurarListenersServicos();

            if (isEdit && associadoId) {
                setTimeout(() => {
                    carregarServicosAssociado();
                }, 1000);
            }

            updateProgressBar();
            updateNavigationButtons();
            setTimeout(() => {
                inicializarNavegacaoSteps();
            }, 1000);

            const dependentesExistentes = document.querySelectorAll('.dependente-card');
            if (dependentesExistentes.length > 0) {
                dependenteIndex = dependentesExistentes.length;
            }

            console.log('✓ Formulário inicializado completamente!');
        })
        .catch(error => {
            console.error('Erro ao carregar dados de serviços:', error);
            showAlert('Erro ao carregar dados do sistema. Algumas funcionalidades podem não funcionar.', 'warning');
        });
});

// ===========================
// AGREGADOS: FUNÇÕES PRINCIPAIS
// ===========================

function configurarListenersCpfTitular() {
    console.log('🚀 Configurando listeners para CPF do titular');
    
    const cpfInput = document.getElementById('cpfTitular');
    if (cpfInput) {
        cpfInput.addEventListener('blur', buscarNomeTitularPorCpf);
        cpfInput.addEventListener('keyup', function() {
            const cpfLimpo = this.value.replace(/\D/g, '');
            if (cpfLimpo.length === 11) {
                buscarNomeTitularPorCpf();
            } else {
                const nomeInput = document.getElementById('nomeTitularInfo');
                const erroSpan = document.getElementById('erroCpfTitular');
                if (nomeInput) {
                    nomeInput.value = '';
                    nomeInput.style.background = '#f5f5f5';
                    nomeInput.style.color = '#666';
                }
                if (erroSpan && cpfLimpo.length > 0) {
                    erroSpan.style.display = 'block';
                    erroSpan.textContent = 'Digite o CPF completo (11 dígitos)';
                }
            }
        });
        console.log('✅ Listeners do CPF titular configurados');
    }
}

function buscarNomeTitularPorCpf() {
    console.log('🔍 Buscando titular por CPF');
    
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
            console.error('Erro ao buscar CPF:', error);
            nomeInput.value = '';
            nomeInput.style.background = '#f8d7da';
            nomeInput.style.color = '#721c24';
            erroSpan.style.display = 'block';
            erroSpan.textContent = 'Erro ao buscar CPF. Tente novamente.';
        });
}

function verificarSeEhAgregadoECarregarDados() {
    console.log('🔍 Verificando se associado é um sócio agregado...');
    
    if (!isEdit || !associadoId) return;
    
    const cpfAtual = document.getElementById('cpf')?.value;
    if (!cpfAtual) {
        setTimeout(verificarSeEhAgregadoECarregarDados, 2000);
        return;
    }
    
    fetch(`../api/buscar_associado_por_cpf.php?cpf=${cpfAtual}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                if (data.data.agregado_id && data.data.agregado_nome) {
                    console.log('✅ ASSOCIADO É UM SÓCIO AGREGADO!');
                    ativarModoAgregadoAutomatico(data.data);
                }
            }
        })
        .catch(error => {
            console.error('Erro ao verificar se é agregado:', error);
        });
}

function ativarModoAgregadoAutomatico(dadosResposta) {
    console.log('🔄 Ativando modo agregado automaticamente');
    
    const checkboxAgregado = document.getElementById('isAgregado');
    if (checkboxAgregado) {
        checkboxAgregado.checked = true;
    }
    
    const campoCpfTitular = document.getElementById('campoCpfTitular');
    if (campoCpfTitular) {
        campoCpfTitular.style.display = 'block';
    }
    
    const cpfTitularInput = document.getElementById('cpfTitular');
    let cpfTitular = dadosResposta.agregado_socio_titular_cpf || dadosResposta.titular_cpf;
    
    if (cpfTitularInput && cpfTitular) {
        let cpfFormatado = cpfTitular;
        if (cpfFormatado.length === 11) {
            cpfFormatado = cpfFormatado.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        cpfTitularInput.value = cpfFormatado;
    }
    
    const nomeTitularInput = document.getElementById('nomeTitularInfo');
    let nomeTitular = dadosResposta.agregado_socio_titular_nome || dadosResposta.titular_nome;
    
    if (nomeTitularInput && nomeTitular) {
        let nomeCompleto = nomeTitular;
        if (cpfTitular) {
            let cpfFormatado = cpfTitular;
            if (cpfFormatado.length === 11) {
                cpfFormatado = cpfFormatado.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
            nomeCompleto += ` - ${cpfFormatado}`;
        }
        nomeTitularInput.value = nomeCompleto;
        nomeTitularInput.style.background = '#d4edda';
        nomeTitularInput.style.color = '#155724';
    }
    
    const erroSpan = document.getElementById('erroCpfTitular');
    if (erroSpan) {
        erroSpan.style.display = 'none';
    }
    
    if (cpfTitularInput) {
        cpfTitularInput.required = true;
    }
    
    console.log('🎉 Modo agregado ativado automaticamente!');
}

// ===========================
// NOVO: PREENCHIMENTO AUTOMÁTICO DE DADOS MILITARES
// ===========================

function toggleAgregadoCampos() {
    const isAgregado = document.getElementById('isAgregado')?.checked;
    const campoCpfTitular = document.getElementById('campoCpfTitular');
    const cpfTitularInput = document.getElementById('cpfTitular');
    const nomeTitularInput = document.getElementById('nomeTitularInfo');
    const erroSpan = document.getElementById('erroCpfTitular');
    
    if (isAgregado) {
        if (campoCpfTitular) campoCpfTitular.style.display = 'block';
        if (cpfTitularInput) cpfTitularInput.required = true;
        
        // NOVO: Preenche dados militares
        setTimeout(() => {
            preencherDadosMilitaresAgregado();
        }, 100);
        
        console.log('✅ Modo agregado ATIVADO');
    } else {
        if (campoCpfTitular) campoCpfTitular.style.display = 'none';
        if (cpfTitularInput) {
            cpfTitularInput.required = false;
            cpfTitularInput.value = '';
        }
        if (nomeTitularInput) {
            nomeTitularInput.value = '';
            nomeTitularInput.style.background = '#f5f5f5';
            nomeTitularInput.style.color = '#666';
        }
        if (erroSpan) erroSpan.style.display = 'none';
        
        // NOVO: Limpa dados militares
        limparDadosMilitares();
        
        console.log('❌ Modo agregado DESATIVADO');
    }
}

function preencherDadosMilitaresAgregado() {
    console.log('📋 Preenchendo dados militares com "AGREGADO"');
    
    // Corporação
    const corporacaoSelect = $('#corporacao');
    if (corporacaoSelect.length) {
        if (corporacaoSelect.find('option[value="AGREGADO"]').length === 0) {
            corporacaoSelect.append(new Option('AGREGADO', 'AGREGADO', true, true));
        } else {
            corporacaoSelect.val('AGREGADO');
        }
        corporacaoSelect.trigger('change');
        console.log('  ✓ Corporação: AGREGADO');
    }
    
    // Patente
    const patenteSelect = $('#patente');
    if (patenteSelect.length) {
        if (patenteSelect.find('option[value="Nenhuma"]').length > 0) {
            patenteSelect.val('Nenhuma');
            console.log('  ✓ Patente: Nenhuma');
        } else {
            if (patenteSelect.find('option[value="AGREGADO"]').length === 0) {
                patenteSelect.append(new Option('AGREGADO', 'AGREGADO', true, true));
            } else {
                patenteSelect.val('AGREGADO');
            }
            console.log('  ✓ Patente: AGREGADO');
        }
        patenteSelect.trigger('change');
    }
    
    // Situação Funcional
    const categoriaSelect = $('#categoria');
    if (categoriaSelect.length) {
        if (categoriaSelect.find('option[value="AGREGADO"]').length === 0) {
            categoriaSelect.append(new Option('AGREGADO', 'AGREGADO', true, true));
        } else {
            categoriaSelect.val('AGREGADO');
        }
        categoriaSelect.trigger('change');
        console.log('  ✓ Situação Funcional: AGREGADO');
    }
    
    // Lotação
    const lotacaoSelect = $('#lotacao');
    if (lotacaoSelect.length) {
        if (lotacaoSelect.find('option[value="AGREGADO"]').length === 0) {
            lotacaoSelect.append(new Option('AGREGADO', 'AGREGADO', true, true));
        } else {
            lotacaoSelect.val('AGREGADO');
        }
        lotacaoSelect.trigger('change');
        console.log('  ✓ Lotação: AGREGADO');
    }
    
    // Unidade
    const unidadeInput = document.getElementById('unidade');
    if (unidadeInput) {
        unidadeInput.value = 'AGREGADO';
        console.log('  ✓ Unidade: AGREGADO');
    }
    
    console.log('✅ Dados militares preenchidos com "AGREGADO"');
}

function limparDadosMilitares() {
    console.log('🧹 Limpando dados militares');
    
    $('#corporacao').val('').trigger('change');
    $('#patente').val('').trigger('change');
    $('#categoria').val('').trigger('change');
    $('#lotacao').val('').trigger('change');
    
    const unidadeInput = document.getElementById('unidade');
    if (unidadeInput) {
        unidadeInput.value = '';
    }
    
    console.log('✅ Dados militares limpos');
}

// ===========================
// NOVO: DESABILITAÇÃO DE CAMPOS FINANCEIROS
// ===========================

function controlarCamposFinanceirosAgregado() {
    console.log('🔒 Controlando campos financeiros para agregado');
    
    const isAgregado = document.getElementById('isAgregado')?.checked;
    
    const camposFinanceiros = [
        'vinculoServidor',
        'localDebito',
        'agencia',
        'operacao',
        'contaCorrente',
        'doador'
    ];
    
    if (isAgregado) {
        console.log('🔒 Desabilitando campos financeiros');
        
        camposFinanceiros.forEach(campoId => {
            const campo = document.getElementById(campoId);
            if (campo) {
                campo.disabled = true;
                campo.required = false;
                campo.style.background = '#e9ecef';
                campo.style.color = '#6c757d';
                campo.style.cursor = 'not-allowed';
                campo.style.opacity = '0.6';
                
                if (campo.tagName === 'SELECT') {
                    campo.value = '';
                } else {
                    campo.value = '';
                }
                
                if (typeof $ !== 'undefined' && $(campo).hasClass('select2-hidden-accessible')) {
                    $(campo).trigger('change');
                }
                
                console.log(`  ✓ Campo ${campoId} desabilitado`);
            }
        });
        
        adicionarMensagemCamposFinanceirosAgregado();
        
    } else {
        console.log('🔓 Habilitando campos financeiros');
        
        camposFinanceiros.forEach(campoId => {
            const campo = document.getElementById(campoId);
            if (campo) {
                campo.disabled = false;
                campo.style.background = '';
                campo.style.color = '';
                campo.style.cursor = '';
                campo.style.opacity = '';
                
                console.log(`  ✓ Campo ${campoId} habilitado`);
            }
        });
        
        removerMensagemCamposFinanceirosAgregado();
    }
    
    console.log(`✅ Campos financeiros ${isAgregado ? 'desabilitados' : 'habilitados'}`);
}

function adicionarMensagemCamposFinanceirosAgregado() {
    removerMensagemCamposFinanceirosAgregado();
    
    const stepFinanceiro = document.querySelector('.section-card[data-step="4"]');
    if (!stepFinanceiro) return;
    
    const mensagem = document.createElement('div');
    mensagem.id = 'mensagem-campos-financeiros-agregado';
    mensagem.style.cssText = `
        background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
        color: #856404;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #ffc107;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.95rem;
        box-shadow: 0 2px 4px rgba(255, 193, 7, 0.2);
    `;
    
    mensagem.innerHTML = `
        <i class="fas fa-info-circle" style="font-size: 1.5rem;"></i>
        <div>
            <strong>Agregado detectado</strong><br>
            <span style="font-size: 0.875rem;">
                Os campos financeiros (banco, agência, conta) não são necessários para associados agregados.
                Esses dados já estão vinculados ao sócio titular.
            </span>
        </div>
    `;
    
    const formGrid = stepFinanceiro.querySelector('.form-grid');
    if (formGrid) {
        formGrid.parentNode.insertBefore(mensagem, formGrid);
    }
}

function removerMensagemCamposFinanceirosAgregado() {
    const mensagem = document.getElementById('mensagem-campos-financeiros-agregado');
    if (mensagem) {
        mensagem.remove();
    }
}

// ===========================
// INTEGRAÇÃO COM NAVEGAÇÃO DE STEPS
// ===========================

function aoEntrarStepMilitares() {
    const isAgregado = document.getElementById('isAgregado')?.checked;
    
    if (isAgregado) {
        console.log('📋 Step Militares + Modo Agregado - preenchendo');
        setTimeout(() => {
            preencherDadosMilitaresAgregado();
        }, 200);
    }
}

function aoEntrarStepFinanceiro() {
    console.log('💰 Entrando no step Financeiro');
    setTimeout(() => {
        controlarCamposFinanceirosAgregado();
    }, 200);
}

// Listener do checkbox agregado
document.addEventListener('DOMContentLoaded', function() {
    const checkboxAgregado = document.getElementById('isAgregado');
    if (checkboxAgregado) {
        checkboxAgregado.addEventListener('change', function() {
            toggleAgregadoCampos();
            
            const stepAtivo = document.querySelector('.section-card.active');
            if (stepAtivo && stepAtivo.getAttribute('data-step') === '4') {
                setTimeout(() => {
                    controlarCamposFinanceirosAgregado();
                }, 100);
            }
        });
    }
    
    if (checkboxAgregado?.checked) {
        setTimeout(() => {
            toggleAgregadoCampos();
        }, 500);
    }
});

// Validação ao submeter
document.getElementById('formAssociado')?.addEventListener('submit', function(e) {
    const isAgregado = document.getElementById('isAgregado')?.checked;
    
    if (isAgregado) {
        const cpfTitular = document.getElementById('cpfTitular')?.value;
        const nomeTitular = document.getElementById('nomeTitularInfo')?.value;
        const erroVisivel = document.getElementById('erroCpfTitular')?.style.display !== 'none';
        
        if (!cpfTitular || !nomeTitular || erroVisivel) {
            e.preventDefault();
            alert('Por favor, preencha corretamente o CPF do titular e verifique se está filiado.');
            document.getElementById('cpfTitular')?.focus();
            return false;
        }
    }
});

// ===========================
// MÁSCARAS
// ===========================

function aplicarMascaras() {
    console.log('Aplicando máscaras...');
    
    $('#cpf').mask('000.000.000-00', {
        placeholder: '000.000.000-00',
        clearIfNotMatch: true
    });
    
    $('#cpfTitular').mask('000.000.000-00', {
        placeholder: '000.000.000-00',
        clearIfNotMatch: true
    });
    
    $('#telefone').mask('(00) 00000-0000', {
        placeholder: '(00) 00000-0000'
    });
    
    $('#celular').mask('(00) 00000-0000', {
        placeholder: '(00) 00000-0000'
    });
    
    $('#cep').mask('00000-000', {
        placeholder: '00000-000'
    });
    
    console.log('✓ Máscaras aplicadas');
}

// ===========================
// SELECT2
// ===========================

function inicializarSelect2() {
    console.log('Inicializando Select2...');
    
    $('.form-select').not('#corporacao, #patente, #categoria, #lotacao').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: function() {
            return $(this).attr('placeholder') || 'Selecione...';
        }
    });
    
    $('#corporacao').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: 'Selecione ou digite a corporação...',
        allowClear: true,
        tags: true,
        createTag: function (params) {
            var term = $.trim(params.term);
            if (term === '') return null;
            return { id: term, text: term, newTag: true }
        }
    });
    
    $('#patente').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: 'Selecione ou digite a patente...',
        allowClear: true,
        tags: true,
        dropdownParent: $('#patente').parent(),
        createTag: function (params) {
            var term = $.trim(params.term);
            if (term === '') return null;
            return { id: term, text: term, newTag: true }
        }
    });
    
    $('#categoria').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: 'Selecione ou digite a situação...',
        allowClear: true,
        tags: true,
        createTag: function (params) {
            var term = $.trim(params.term);
            if (term === '') return null;
            return { id: term, text: term, newTag: true }
        }
    });
    
    $('#lotacao').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: 'Selecione ou digite a lotação...',
        allowClear: true,
        tags: true
    });
    
    console.log('✓ Select2 inicializado');
}

// ===========================
// UPLOADS E PREVIEWS
// ===========================

function inicializarUploadPreviews() {
    console.log('Configurando uploads...');
    
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

// ===========================
// CONTROLE SERVIÇO JURÍDICO
// ===========================

function controlarServicoJuridico() {
    console.log('=== CONTROLANDO SERVIÇO JURÍDICO ===');
    
    const tipoAssociado = document.getElementById('tipoAssociadoServico')?.value;
    const servicoJuridicoCheckbox = document.getElementById('servicoJuridico');
    const servicoJuridicoItem = document.getElementById('servicoJuridicoItem');
    const badgeJuridico = document.getElementById('badgeJuridico');
    const mensagemContainer = document.getElementById('mensagemRestricaoJuridico');
    const infoTipoAssociado = document.getElementById('infoTipoAssociado');
    const textoInfoTipo = document.getElementById('textoInfoTipo');
    
    const tiposSemJuridico = ['Benemérito', 'Benemerito', 'Agregado'];
    
    if (tiposSemJuridico.includes(tipoAssociado)) {
        console.log('❌ Tipo não tem direito ao serviço jurídico');
        
        if (servicoJuridicoCheckbox) {
            servicoJuridicoCheckbox.disabled = true;
            servicoJuridicoCheckbox.checked = false;
        }
        
        if (servicoJuridicoItem) {
            servicoJuridicoItem.classList.add('desabilitado', 'servico-bloqueado');
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
                <div class="mensagem-restricao" style="
                    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                    color: #721c24;
                    padding: 0.75rem;
                    border-radius: 6px;
                    font-size: 0.8rem;
                    margin-top: 0.75rem;
                    border-left: 4px solid #dc3545;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                ">
                    <i class="fas fa-exclamation-triangle"></i>
                    Associados do tipo "${tipoAssociado}" não têm direito ao serviço jurídico conforme regulamento da ASSEGO.
                </div>
            `;
        }
        
        if (infoTipoAssociado && textoInfoTipo) {
            infoTipoAssociado.style.display = 'block';
            textoInfoTipo.innerHTML = `Tipo "${tipoAssociado}" não tem direito ao serviço jurídico.`;
            infoTipoAssociado.style.color = '#dc3545';
        }
        
        zerarValoresJuridico();
        
    } else {
        console.log('✅ Tipo tem direito ao serviço jurídico');
        
        if (servicoJuridicoCheckbox) {
            servicoJuridicoCheckbox.disabled = false;
        }
        
        if (servicoJuridicoItem) {
            servicoJuridicoItem.classList.remove('desabilitado', 'servico-bloqueado');
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
        
        if (textoInfoTipo && textoInfoTipo.textContent.includes('não tem direito')) {
            if (infoTipoAssociado) {
                infoTipoAssociado.style.display = 'none';
            }
        }
    }
}

function zerarValoresJuridico() {
    updateElementSafe('valorJuridico', '0', 'value');
    updateElementSafe('percentualAplicadoJuridico', '0', 'value');
    updateElementSafe('valorFinalJuridico', '0,00');
    updateElementSafe('percentualJuridico', '0');
}

function validarTipoEServicos() {
    const tipoAssociado = document.getElementById('tipoAssociadoServico')?.value;
    
    if (!tipoAssociado) {
        showAlert('Por favor, selecione o tipo de associado antes de prosseguir.', 'warning');
        return false;
    }
    
    controlarServicoJuridico();
    return true;
}

function configurarListenersServicos() {
    console.log('Configurando listeners dos serviços');
    
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
    
    console.log('✓ Listeners configurados');
}

// ===========================
// CARREGAR DADOS DE SERVIÇOS
// ===========================

function carregarDadosServicos() {
    console.log('=== CARREGANDO DADOS DE SERVIÇOS ===');

    return fetch('../api/buscar_dados_servicos.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                regrasContribuicao = data.regras || [];
                servicosData = data.servicos || [];
                tiposAssociadoData = data.tipos_associado || [];
                dadosCarregados = true;

                console.log('✓ Dados carregados:', {
                    servicos: servicosData.length,
                    regras: regrasContribuicao.length,
                    tipos: tiposAssociadoData.length
                });

                preencherSelectTiposAssociado();
                return true;
            } else {
                throw new Error(data.message || 'Erro na API');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados:', error);
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
            option.style.background = '#fff3cd';
            option.style.color = '#856404';
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
        { id: "1", nome: "Social", valor_base: "181.46" },
        { id: "2", nome: "Jurídico", valor_base: "45.37" }
    ];

    regrasContribuicao = [
        { tipo_associado: "Contribuinte", servico_id: "1", percentual_valor: "100.00" },
        { tipo_associado: "Contribuinte", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Aluno", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Aluno", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Agregado", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Agregado", servico_id: "2", percentual_valor: "0.00" }
    ];

    tiposAssociadoData = ["Contribuinte", "Aluno", "Agregado", "Remido", "Benemerito"];
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

        const servicoSocial = servicosData.find(s => s.id == 1);
        if (servicoSocial) {
            updateElementSafe('valorBaseSocial', parseFloat(servicoSocial.valor_base).toFixed(2).replace('.', ','));
        }
    }

    if (dadosServicos.servicos && dadosServicos.servicos.juridico) {
        const juridico = dadosServicos.servicos.juridico;

        const juridicoCheckEl = document.getElementById('servicoJuridico');
        if (juridicoCheckEl) {
            juridicoCheckEl.checked = true;
        }

        updateElementSafe('valorJuridico', juridico.valor_aplicado, 'value');
        updateElementSafe('percentualAplicadoJuridico', juridico.percentual_aplicado, 'value');
        updateElementSafe('valorFinalJuridico', parseFloat(juridico.valor_aplicado).toFixed(2).replace('.', ','));
        updateElementSafe('percentualJuridico', parseFloat(juridico.percentual_aplicado).toFixed(0));

        const servicoJuridico = servicosData.find(s => s.id == 2);
        if (servicoJuridico) {
            updateElementSafe('valorBaseJuridico', parseFloat(servicoJuridico.valor_base).toFixed(2).replace('.', ','));
        }
    } else {
        const juridicoCheckEl = document.getElementById('servicoJuridico');
        if (juridicoCheckEl) {
            juridicoCheckEl.checked = false;
        }
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

    const regrasSocial = regrasContribuicao.filter(r =>
        r.tipo_associado === tipoAssociado && r.servico_id == 1
    );
    const regrasJuridico = regrasContribuicao.filter(r =>
        r.tipo_associado === tipoAssociado && r.servico_id == 2
    );

    let valorTotalGeral = 0;

    if (regrasSocial.length > 0) {
        const regra = regrasSocial[0];
        const servicoSocial = servicosData.find(s => s.id == 1);
        const valorBase = parseFloat(servicoSocial?.valor_base || 181.46);
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
        const valorBase = parseFloat(servicoJuridico?.valor_base || 45.37);
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

// ===========================
// SALVAMENTO MULTI-STEP
// ===========================

function salvarStepAtual() {
    if (salvandoStep) return;

    if (!validarStepAtual()) {
        showAlert('Por favor, corrija os erros antes de salvar.', 'warning');
        return;
    }

    const isAgregado = document.getElementById('isAgregado')?.checked;
    
    if (isAgregado && isEdit) {
        salvarAssociado();
        return;
    }

    if (isAgregado && !isEdit && !window.pageData.associadoId && currentStep === 1) {
        salvarNovoAssociadoPrimeiroPasso();
        return;
    }

    if (!isAgregado && !isEdit && !window.pageData.associadoId && currentStep === 1) {
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
        showAlert('Erro de comunicação com o servidor!', 'error');
    });
}

function criarFormDataStep(step) {
    const form = document.getElementById('formAssociado');
    const formData = new FormData();

    if (isEdit || window.pageData.associadoId) {
        formData.append('id', isEdit ? associadoId : window.pageData.associadoId);
    }

    const camposObrigatoriosBasicos = ['nome', 'cpf', 'rg', 'telefone', 'situacao'];
    camposObrigatoriosBasicos.forEach(campo => {
        const element = form.elements[campo];
        if (element) {
            if (element.type === 'radio') {
                const checked = form.querySelector(`input[name="${campo}"]:checked`);
                if (checked) formData.append(campo, checked.value);
            } else {
                formData.append(campo, element.value);
            }
        }
    });

    switch(step) {
        case 1:
            const camposStep1 = [
                'nome', 'nasc', 'sexo', 'estadoCivil', 'rg', 'cpf', 
                'telefone', 'email', 'escolaridade', 'indicacao', 
                'situacao', 'dataFiliacao'
            ];
            
            camposStep1.forEach(campo => {
                const element = form.elements[campo];
                if (element) {
                    if (element.type === 'radio') {
                        const checked = form.querySelector(`input[name="${campo}"]:checked`);
                        if (checked) formData.append(campo, checked.value);
                    } else {
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
            if (fotoFile) {
                formData.append('foto', fotoFile);
            }

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
                if (element) {
                    formData.append(campo, element.value);
                }
            });
            break;

        case 3:
            const camposStep3 = ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade'];
            camposStep3.forEach(campo => {
                const element = form.elements[campo];
                if (element) {
                    formData.append(campo, element.value);
                }
            });
            break;

        case 4:
            const camposStep4 = [
                'tipoAssociadoServico', 'tipoAssociado', 'situacaoFinanceira', 
                'vinculoServidor', 'localDebito', 'agencia', 'operacao', 
                'contaCorrente', 'doador'
            ];
            
            camposStep4.forEach(campo => {
                const element = form.elements[campo];
                if (element) {
                    formData.append(campo, element.value);
                }
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
                    if (input.value) {
                        formData.append(input.name, input.value);
                    }
                });
            });
            break;
    }

    return formData;
}

function salvarNovoAssociadoPrimeiroPasso() {
    if (!validarStepAtual()) {
        showAlert('Por favor, corrija os erros antes de salvar.', 'warning');
        return;
    }

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
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 3000);
    }
    
    if (btn) {
        btn.classList.add('saved');
        setTimeout(() => {
            btn.classList.remove('saved');
        }, 2000);
    }
}

function atualizarIndicadoresStep() {
    stepsSalvos.forEach(stepNum => {
        const stepElement = document.querySelector(`[data-step="${stepNum}"]`);
        if (stepElement) {
            stepElement.classList.add('saved');
        }
    });
}

// ===========================
// NAVEGAÇÃO
// ===========================

function proximoStep() {
    if (currentStep === 4) {
        if (!validarTipoEServicos()) {
            return;
        }
    }
    
    if (validarStepAtual()) {
        if (currentStep < totalSteps) {
            document.querySelector(`[data-step="${currentStep}"]`).classList.add('completed');
            irParaStep(currentStep + 1);
            
            if (currentStep === totalSteps) {
                preencherRevisao();
            }
        }
    }
}

function voltarStep() {
    if (currentStep > 1) {
        irParaStep(currentStep - 1);
    }
}

function irParaStep(numeroStep) {
    if (numeroStep < 1 || numeroStep > totalSteps) return;
    
    if (numeroStep > currentStep && !validarStepAtual()) {
        return;
    }
    
    currentStep = numeroStep;
    mostrarStep(currentStep);
}

function mostrarStep(step) {
    document.querySelectorAll('.section-card').forEach(card => {
        card.classList.remove('active');
    });

    document.querySelector(`.section-card[data-step="${step}"]`).classList.add('active');

    // Executa lógicas específicas por step
    if (step === 2) {
        aoEntrarStepMilitares();
    } else if (step === 4) {
        aoEntrarStepFinanceiro();
    }

    updateProgressBar();
    updateNavigationButtons();
    
    setTimeout(() => {
        inicializarNavegacaoSteps();
    }, 1000);

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
                if (stepsSalvos.has(currentStep)) {
                    saveText.textContent = 'Atualizar';
                } else {
                    saveText.textContent = 'Salvar';
                }
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

function inicializarNavegacaoSteps() {
    document.querySelectorAll('.step').forEach(step => {
        step.style.cursor = 'pointer';
        step.style.transition = 'all 0.3s ease';
        
        step.addEventListener('click', function() {
            const numeroStep = parseInt(this.getAttribute('data-step'));
            if (numeroStep) {
                irParaStep(numeroStep);
                
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
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

// ===========================
// VALIDAÇÃO
// ===========================

function validarStepAtual() {
    const stepCard = document.querySelector(`.section-card[data-step="${currentStep}"]`);
    const requiredFields = stepCard.querySelectorAll('[required]');
    let isValid = true;

    stepCard.querySelectorAll('.form-input').forEach(field => {
        field.classList.remove('error');
    });

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        }
    });

    if (currentStep === 1) {
        const isAgregado = document.getElementById('isAgregado')?.checked;
        if (isAgregado) {
            const cpfTitular = document.getElementById('cpfTitular')?.value;
            const nomeTitular = document.getElementById('nomeTitularInfo')?.value;
            const erroVisivel = document.getElementById('erroCpfTitular')?.style.display !== 'none';
            
            if (!cpfTitular || !nomeTitular || erroVisivel) {
                showAlert('Por favor, preencha corretamente o CPF do titular e verifique se está filiado.', 'error');
                document.getElementById('cpfTitular')?.classList.add('error');
                isValid = false;
            }
        }
        
        const cpfField = document.getElementById('cpf');
        if (cpfField && cpfField.value && !validarCPF(cpfField.value)) {
            cpfField.classList.add('error');
            isValid = false;
            showAlert('CPF inválido!', 'error');
        }

        if (!isEdit) {
            const fichaField = document.getElementById('ficha_assinada');
            if (!fichaField.files || fichaField.files.length === 0) {
                showAlert('Por favor, anexe a ficha de filiação assinada!', 'error');
                isValid = false;
            }
        }

        const emailField = document.getElementById('email');
        if (emailField && emailField.value && !validarEmail(emailField.value)) {
            emailField.classList.add('error');
            isValid = false;
            showAlert('E-mail inválido!', 'error');
        }
    }

    if (currentStep === 4) {
        const tipoAssociadoServico = document.getElementById('tipoAssociadoServico');
        const tipoAssociado = document.getElementById('tipoAssociado');
        const valorSocial = document.getElementById('valorSocial');

        if (tipoAssociadoServico && !tipoAssociadoServico.value) {
            tipoAssociadoServico.classList.add('error');
            isValid = false;
            showAlert('Por favor, selecione o tipo de associado para os serviços!', 'error');
        }

        if (tipoAssociado && !tipoAssociado.value) {
            tipoAssociado.classList.add('error');
            isValid = false;
            showAlert('Por favor, selecione a categoria do associado!', 'error');
        }

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

function setupRealtimeValidation() {
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('input', function () {
            if (this.value.trim()) {
                this.classList.remove('error');
            }
        });
    });

    const cpfField = document.getElementById('cpf');
    if (cpfField) {
        cpfField.addEventListener('blur', function () {
            if (this.value && !validarCPF(this.value)) {
                this.classList.add('error');
                showAlert('CPF inválido!', 'error');
            }
        });
    }

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

function validarEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validarFormularioCompleto() {
    const form = document.getElementById('formAssociado');
    if (!form) return false;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (isEdit && field.name === 'foto') {
            return;
        }

        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        }
    });

    return isValid;
}

// ===========================
// SALVAR ASSOCIADO COMPLETO
// ===========================

function salvarAssociado() {
    console.log('=== SALVANDO ASSOCIADO/AGREGADO ===');
    
    const isAgregado = document.getElementById('isAgregado')?.checked;
    
    if (isAgregado) {
        const cpfTitular = document.getElementById('cpfTitular')?.value;
        const nomeTitular = document.getElementById('nomeTitularInfo')?.value;
        const erroVisivel = document.getElementById('erroCpfTitular')?.style.display !== 'none';
        
        if (!cpfTitular || !nomeTitular || erroVisivel) {
            showAlert('Por favor, preencha corretamente o CPF do titular e verifique se está filiado.', 'error');
            document.getElementById('cpfTitular')?.focus();
            return;
        }
    }
    
    if (!validarFormularioCompleto()) {
        showAlert('Por favor, verifique todos os campos obrigatórios!', 'error');
        return;
    }
    
    showLoading();
    
    const formulario = document.querySelector('form');
    const formData = new FormData(formulario);
    
    // Campos manuais
    const camposManuais = [
        { id: 'nome', name: 'nome' },
        { id: 'cpf', name: 'cpf' },
        { id: 'rg', name: 'rg' },
        { id: 'telefone', name: 'telefone' },
        { id: 'celular', name: 'celular' },
        { id: 'email', name: 'email' },
        { id: 'endereco', name: 'endereco' },
        { id: 'numero', name: 'numero' },
        { id: 'complemento', name: 'complemento' },
        { id: 'bairro', name: 'bairro' },
        { id: 'cidade', name: 'cidade' },
        { id: 'estado', name: 'estado' },
        { id: 'cep', name: 'cep' },
        { id: 'banco', name: 'banco' },
        { id: 'agencia', name: 'agencia' },
        { id: 'contaCorrente', name: 'contaCorrente' },
        { id: 'estadoCivil', name: 'estadoCivil' },
        { id: 'dataFiliacao', name: 'dataFiliacao' },
        { id: 'situacao', name: 'situacao' },
        { id: 'escolaridade', name: 'escolaridade' }
    ];

    camposManuais.forEach(campo => {
        const elemento = document.getElementById(campo.id);
        if (elemento && elemento.value && elemento.value.trim() !== '') {
            formData.set(campo.name, elemento.value);
        }
    });
    
    // Data de nascimento
    let campoNasc = document.getElementById('nasc');
    if (campoNasc && campoNasc.value) {
        formData.set('dataNascimento', campoNasc.value);
        formData.set('nasc', campoNasc.value);
        formData.set('data_nascimento', campoNasc.value);
    }

    // Celular
    if (!formData.get('celular')) {
        const telefone = document.getElementById('telefone');
        if (telefone && telefone.value) {
            formData.set('celular', telefone.value);
        }
    }

    // Estado
    if (!formData.get('estado')) {
        formData.set('estado', 'GO');
    }
    
    // Agregados
    if (isAgregado) {
        if (!formData.get('banco') || formData.get('banco') === '') {
            formData.set('banco', 'Não informado');
        }
        if (!formData.get('agencia') || formData.get('agencia') === '') {
            formData.set('agencia', '');
        }
        if (!formData.get('contaCorrente') || formData.get('contaCorrente') === '') {
            formData.set('contaCorrente', '');
        }
        
        const cpfTitular = document.getElementById('cpfTitular')?.value;
        if (cpfTitular) {
            formData.set('cpfTitular', cpfTitular);
            formData.set('socioTitularCpf', cpfTitular);
        }
    }

    // Select2
    const camposSelect2 = [
        'corporacao', 'patente', 'categoria', 'lotacao',
        'tipoAssociadoServico', 'tipoAssociado', 'situacaoFinanceira'
    ];
    
    camposSelect2.forEach(campo => {
        const elemento = document.getElementById(campo);
        if (elemento && elemento.value) {
            formData.set(campo, elemento.value);
        }
    });

    // Sexo
    const sexoRadio = document.querySelector('input[name="sexo"]:checked');
    if (sexoRadio) {
        formData.set('sexo', sexoRadio.value);
    }
    
    // Serviços
    const servicoSocial = document.getElementById('valorSocial');
    if (servicoSocial && servicoSocial.value) {
        formData.set('servicoSocial', '1');
        formData.set('valorSocial', servicoSocial.value);
        formData.set('percentualAplicadoSocial', document.getElementById('percentualAplicadoSocial')?.value || '0');
    }
    
    const servicoJuridico = document.getElementById('servicoJuridico');
    if (servicoJuridico && servicoJuridico.checked && !servicoJuridico.disabled) {
        formData.set('servicoJuridico', '2');
        formData.set('valorJuridico', document.getElementById('valorJuridico')?.value || '0');
        formData.set('percentualAplicadoJuridico', document.getElementById('percentualAplicadoJuridico')?.value || '0');
    }
    
    // URL - Determinar endpoint correto
    const associadoId = document.getElementById('associadoId')?.value;
    let url;
    
    if (associadoId) {
        // MODO EDIÇÃO - sempre usa atualizar_associado.php
        url = `../api/atualizar_associado.php?id=${associadoId}`;
        console.log('Modo EDIÇÃO - ID:', associadoId);
    } else {
        // MODO CRIAÇÃO - verifica se é agregado ou associado
        if (isAgregado) {
            url = '../api/criar_agregado.php';
            console.log('Modo CRIAÇÃO - Agregado');
        } else {
            url = '../api/criar_associado.php';
            console.log('Modo CRIAÇÃO - Associado');
        }
    }
    
    console.log('Enviando para URL:', url);
    console.log('Modo:', associadoId ? 'EDIÇÃO' : 'CRIAÇÃO');
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Status da resposta:', response.status);
        console.log('Response OK:', response.ok);
        
        if (!response.ok) {
            throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.text();
    })
    .then(text => {
        console.log('Resposta recebida (primeiros 500 chars):', text.substring(0, 500));
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Erro ao fazer parse do JSON:', e);
            console.error('Resposta completa:', text);
            hideLoading();
            showAlert('Erro: Resposta inválida do servidor. Verifique o console para mais detalhes.', 'error');
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
            console.error('Erro retornado pela API:', data);
            showAlert(erro, 'error');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        console.error('Stack trace:', error.stack);
        hideLoading();
        showAlert('Erro de comunicação com o servidor: ' + error.message + '\nVerifique sua conexão ou tente novamente.', 'error');
    });
}

// ===========================
// DEPENDENTES
// ===========================

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
            if (numberEl) {
                numberEl.textContent = `Dependente ${index + 1}`;
            }
        });
    }, 300);
}

// ===========================
// REVISÃO
// ===========================

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
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${dadosRevisao.nome}</span>
                        </div>
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">CPF:</span>
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${dadosRevisao.cpf}</span>
                        </div>
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Telefone:</span>
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${dadosRevisao.telefone}</span>
                        </div>
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Corporação:</span>
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${dadosRevisao.corporacao}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Patente:</span>
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${dadosRevisao.patente}</span>
                        </div>
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Tipo de Associado:</span>
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${dadosRevisao.tipoAssociadoServico}</span>
                        </div>
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Categoria:</span>
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${dadosRevisao.tipoAssociado}</span>
                        </div>
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Serviço Jurídico:</span>
                            <span class="overview-value" style="font-size: 1rem; color: var(--dark); font-weight: 500;">${statusJuridico}</span>
                        </div>
                        <div class="overview-item" style="margin-bottom: 1rem;">
                            <span class="overview-label" style="font-size: 0.875rem; color: var(--gray-600); font-weight: 600;">Valor Total Mensal:</span>
                            <span class="overview-value" style="font-size: 1.25rem; color: var(--primary); font-weight: 700;">R$ ${dadosRevisao.valorTotal}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    if (stepsSalvos.size > 0) {
        html += `
            <div class="alert-custom alert-info" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); color: #0c5460; border: 1px solid #b8daff;">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Steps já salvos:</strong>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.875rem;">
                        ${Array.from(stepsSalvos).map(s => `Step ${s}`).join(', ')} foram salvos individualmente.
                    </p>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

// ===========================
// BUSCAR CEP
// ===========================

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

            if (numeroField) numeroField.focus();
        })
        .catch(error => {
            hideLoading();
            console.error('Erro ao buscar CEP:', error);
            showAlert('Erro ao buscar CEP!', 'error');
        });
}

// ===========================
// UTILIDADES
// ===========================

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

console.log('✅ cadastroForm.js v2.0 carregado completamente!');