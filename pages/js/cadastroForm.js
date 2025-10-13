// === AGREGADO: Buscar nome do titular pelo CPF ===
function buscarNomeTitularPorCpf() {
    console.log('🔍 Função buscarNomeTitularPorCpf chamada');
    
    const cpfInput = document.getElementById('cpfTitular');
    const nomeInput = document.getElementById('nomeTitularInfo');
    const erroSpan = document.getElementById('erroCpfTitular');
    
    if (!cpfInput || !nomeInput || !erroSpan) {
        console.error('❌ Elementos do DOM não encontrados!', { cpfInput, nomeInput, erroSpan });
        return;
    }

    const cpf = cpfInput.value.replace(/\D/g, '');
    console.log('CPF digitado:', cpfInput.value);
    console.log('CPF limpo:', cpf);
    
    // Limpa o campo se CPF for inválido
    if (cpf.length !== 11) {
        nomeInput.value = '';
        nomeInput.style.background = '#f5f5f5';
        nomeInput.style.color = '#666';
        erroSpan.style.display = 'block';
        erroSpan.textContent = 'CPF deve ter 11 dígitos';
        console.log('❌ CPF inválido (não tem 11 dígitos)');
        return;
    }
    
    // Mostra loading
    nomeInput.value = 'Buscando...';
    nomeInput.style.background = '#fff3cd';
    nomeInput.style.color = '#856404';
    erroSpan.style.display = 'none';
    
    console.log('📡 Fazendo requisição para API...');
    console.log('URL:', `../api/buscar_associado_por_cpf.php?cpf=${cpf}`);
    
    // Faz a requisição
    fetch(`../api/buscar_associado_por_cpf.php?cpf=${cpf}`)
        .then(response => {
            console.log('📥 Resposta recebida:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('✅ Dados recebidos:', data);
            
            if (data.status === 'success' && data.data) {
                const titular = data.data;
                let nome = titular.titular_nome || titular.nome || '';
                let cpfFormatado = titular.titular_cpf || titular.cpf || '';
                let situacao = titular.titular_situacao || titular.situacao || '';
                
                console.log('Nome encontrado:', nome);
                console.log('Situação:', situacao);
                
                // Formata CPF
                if (cpfFormatado && cpfFormatado.length === 11) {
                    cpfFormatado = cpfFormatado.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                }
                
                // Preenche o campo
                if (nome && cpfFormatado) {
                    nomeInput.value = `${nome} - ${cpfFormatado}`;
                } else if (nome) {
                    nomeInput.value = nome;
                } else {
                    nomeInput.value = '';
                }
                
                // Verifica situação
                if (situacao && situacao !== 'Filiado') {
                    nomeInput.style.background = '#f8d7da';
                    nomeInput.style.color = '#721c24';
                    erroSpan.style.display = 'block';
                    erroSpan.textContent = `Titular está ${situacao}. Somente titulares FILIADOS podem ter agregados.`;
                    console.log('⚠️ Titular não está filiado:', situacao);
                } else {
                    nomeInput.style.background = '#d4edda';
                    nomeInput.style.color = '#155724';
                    erroSpan.style.display = 'none';
                    console.log('✅ Titular válido e filiado');
                }
                
            } else {
                console.log('❌ Associado não encontrado');
                nomeInput.value = '';
                nomeInput.style.background = '#f8d7da';
                nomeInput.style.color = '#721c24';
                erroSpan.style.display = 'block';
                erroSpan.textContent = 'CPF não encontrado ou não é um associado filiado';
            }
        })
        .catch(error => {
            console.error('❌ Erro na busca:', error);
            nomeInput.value = '';
            nomeInput.style.background = '#f8d7da';
            nomeInput.style.color = '#721c24';
            erroSpan.style.display = 'block';
            erroSpan.textContent = 'Erro ao buscar CPF. Tente novamente.';
        });
}

// === FUNÇÃO PARA DETECTAR SE É SÓCIO AGREGADO ===
function verificarSeEhAgregadoECarregarDados() {
    console.log('🔍 Verificando se associado é um sócio agregado...');
    
    if (!isEdit || !associadoId) {
        console.log('⚠️ Não é modo edição ou não tem ID - pulando verificação');
        return;
    }
    
    // Busca o CPF do associado atual no formulário
    const cpfAtual = document.getElementById('cpf')?.value;
    if (!cpfAtual) {
        console.log('⚠️ CPF não encontrado no formulário - aguardando 2 segundos...');
        // Tenta novamente após 2 segundos (pode estar carregando ainda)
        setTimeout(verificarSeEhAgregadoECarregarDados, 2000);
        return;
    }
    
    console.log('🔍 Verificando CPF:', cpfAtual);
    
    // Usa a API existente para buscar dados
    fetch(`../api/buscar_associado_por_cpf.php?cpf=${cpfAtual}`)
        .then(response => response.json())
        .then(data => {
            console.log('📥 Resposta busca por CPF:', data);
            
            if (data.status === 'success' && data.data) {
                // Verifica se tem dados de agregado na resposta
                if (data.data.agregado_id && data.data.agregado_nome) {
                    console.log('✅ ASSOCIADO É UM SÓCIO AGREGADO!');
                    
                    // Ativa o modo agregado automaticamente
                    ativarModoAgregadoAutomatico(data.data);
                    
                } else if (data.data.titular_id && data.data.titular_nome) {
                    console.log('ℹ️ Associado é um titular normal (não é agregado)');
                } else {
                    console.log('ℹ️ Dados insuficientes para determinar tipo');
                }
            } else {
                console.log('⚠️ Não foi possível verificar o tipo do associado');
            }
        })
        .catch(error => {
            console.error('❌ Erro ao verificar se é agregado:', error);
        });
}

// === ATIVAR MODO AGREGADO AUTOMATICAMENTE ===
function ativarModoAgregadoAutomatico(dadosResposta) {
    console.log('🔄 Ativando modo agregado automaticamente...', dadosResposta);
    
    // 1. Marca o checkbox de agregado
    const checkboxAgregado = document.getElementById('isAgregado');
    if (checkboxAgregado) {
        checkboxAgregado.checked = true;
        console.log('✅ Checkbox agregado marcado');
    }
    
    // 2. Mostra os campos de titular
    const campoCpfTitular = document.getElementById('campoCpfTitular');
    if (campoCpfTitular) {
        campoCpfTitular.style.display = 'block';
        console.log('✅ Campos de titular exibidos');
    }
    
    // 3. Preenche CPF do titular (busca na estrutura de dados da API existente)
    const cpfTitularInput = document.getElementById('cpfTitular');
    let cpfTitular = dadosResposta.agregado_socio_titular_cpf || dadosResposta.titular_cpf;
    
    if (cpfTitularInput && cpfTitular) {
        // Formata o CPF se necessário
        let cpfFormatado = cpfTitular;
        if (cpfFormatado.length === 11) {
            cpfFormatado = cpfFormatado.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        cpfTitularInput.value = cpfFormatado;
        console.log('✅ CPF do titular preenchido:', cpfFormatado);
    }
    
    // 4. Preenche nome do titular
    const nomeTitularInput = document.getElementById('nomeTitularInfo');
    let nomeTitular = dadosResposta.agregado_socio_titular_nome || dadosResposta.titular_nome;
    
    if (nomeTitularInput && nomeTitular) {
        let nomeCompleto = nomeTitular;
        
        // Se tem CPF, adiciona no formato "Nome - CPF"
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
        console.log('✅ Nome do titular preenchido:', nomeCompleto);
    }
    
    // 5. Esconde erro se estava visível
    const erroSpan = document.getElementById('erroCpfTitular');
    if (erroSpan) {
        erroSpan.style.display = 'none';
    }
    
    // 6. Define como obrigatório
    if (cpfTitularInput) {
        cpfTitularInput.required = true;
    }
    
    console.log('🎉 Modo agregado ativado automaticamente com sucesso!');
}

/**
 * cadastroForm.js - JavaScript Completo com Salvamento Multi-Step
 * Versão com botões de salvar em cada step + controle serviço jurídico + busca agregados + detecção automática
 */

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

// NOVOS: Estados de salvamento por step
let stepsSalvos = new Set(); // Armazena quais steps foram salvos
let salvandoStep = false; // Flag para evitar salvamentos duplicados

// Inicialização
document.addEventListener('DOMContentLoaded', function () {
    console.log('=== INICIANDO FORMULÁRIO DE FILIAÇÃO COM SALVAMENTO MULTI-STEP ===');
    console.log('Modo edição:', isEdit, 'ID:', associadoId);

    // 🆕 PRIMEIRA COISA: Verifica se é agregado (MODO EDIÇÃO)
    if (isEdit && associadoId) {
        setTimeout(() => {
            verificarSeEhAgregadoECarregarDados();
        }, 1500);
    }

    // 🆕 Configura listeners para CPF do titular (AGREGADOS)
    console.log('🚀 Configurando listeners para CPF do titular (agregados)');
    
    const cpfInput = document.getElementById('cpfTitular');
    if (cpfInput) {
        console.log('✅ Campo cpfTitular encontrado');
        
        // Listener para quando o campo perde o foco
        cpfInput.addEventListener('blur', function() {
            console.log('👁️ Campo cpfTitular perdeu o foco (blur)');
            buscarNomeTitularPorCpf();
        });
        
        // Listener para quando o usuário digita
        cpfInput.addEventListener('keyup', function() {
            const cpfLimpo = this.value.replace(/\D/g, '');
            console.log('⌨️ Keyup - CPF atual:', cpfLimpo);
            
            if (cpfLimpo.length === 11) {
                console.log('✅ CPF completo (11 dígitos) - buscando...');
                buscarNomeTitularPorCpf();
            } else {
                // Limpa o campo se não tiver 11 dígitos
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
        
        console.log('✅ Listeners adicionados ao campo cpfTitular');
    } else {
        console.warn('⚠️ Campo cpfTitular NÃO encontrado no DOM (talvez não seja modo agregado)');
    }

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
            aplicarMascaras();

            // Select2
            inicializarSelect2();

            // Preview de arquivos
            inicializarUploadPreviews();

            // Validação em tempo real
            setupRealtimeValidation();

            // Event listeners dos serviços (INCLUINDO CONTROLE JURÍDICO)
            configurarListenersServicos();

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

            // Se for modo edição e houver dependentes já carregados, atualiza o índice
            const dependentesExistentes = document.querySelectorAll('.dependente-card');
            if (dependentesExistentes.length > 0) {
                dependenteIndex = dependentesExistentes.length;
            }

            console.log('✓ Formulário inicializado com sucesso (+ salvamento multi-step + busca agregados + detecção automática)!');

        })
        .catch(error => {
            console.error('Erro ao carregar dados de serviços:', error);
            showAlert('Erro ao carregar dados do sistema. Algumas funcionalidades podem não funcionar.', 'warning');
        });
});

// === FUNÇÃO TOGGLEAGREGADOCAMPOS ATUALIZADA ===
function toggleAgregadoCampos() {
    const isAgregado = document.getElementById('isAgregado').checked;
    const campoCpfTitular = document.getElementById('campoCpfTitular');
    const cpfTitularInput = document.getElementById('cpfTitular');
    const nomeTitularInput = document.getElementById('nomeTitularInfo');
    const erroSpan = document.getElementById('erroCpfTitular');
    
    if (isAgregado) {
        // Mostra campos
        if (campoCpfTitular) campoCpfTitular.style.display = 'block';
        if (cpfTitularInput) cpfTitularInput.required = true;
        
        console.log('✅ Modo agregado ATIVADO manualmente');
    } else {
        // Esconde campos
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
        
        console.log('❌ Modo agregado DESATIVADO');
    }
}

// Validação ao submeter o formulário (AGREGADOS)
document.getElementById('formAssociado')?.addEventListener('submit', function(e) {
    const isAgregado = document.getElementById('isAgregado')?.checked;
    
    if (isAgregado) {
        console.log('📝 Validando CPF do titular antes de submeter...');
        
        const cpfTitular = document.getElementById('cpfTitular')?.value;
        const nomeTitular = document.getElementById('nomeTitularInfo')?.value;
        const erroVisivel = document.getElementById('erroCpfTitular')?.style.display !== 'none';
        
        if (!cpfTitular || !nomeTitular || erroVisivel) {
            e.preventDefault();
            alert('Por favor, preencha corretamente o CPF do titular e verifique se está filiado.');
            document.getElementById('cpfTitular')?.focus();
            console.log('❌ Validação falhou - CPF do titular inválido');
            return false;
        }
        
        console.log('✅ Validação OK - pode submeter');
    }
});

// Aplicar máscaras
function aplicarMascaras() {
    console.log('Aplicando máscaras...');
    
    // Máscara para CPF
    $('#cpf').mask('000.000.000-00', {
        placeholder: '000.000.000-00',
        clearIfNotMatch: true
    });
    
    // Máscara para CPF do Titular (AGREGADOS)
    $('#cpfTitular').mask('000.000.000-00', {
        placeholder: '000.000.000-00',
        clearIfNotMatch: true
    });
    
    // Máscara para telefone
    $('#telefone').mask('(00) 00000-0000', {
        placeholder: '(00) 00000-0000'
    });
    
    // Máscara para celular
    $('#celular').mask('(00) 00000-0000', {
        placeholder: '(00) 00000-0000'
    });
    
    // Máscara para CEP
    $('#cep').mask('00000-000', {
        placeholder: '00000-000'
    });
    
    console.log('✓ Máscaras aplicadas (incluindo CPF do titular)');
}

// Inicializar Select2
function inicializarSelect2() {
    console.log('Inicializando Select2...');
    
    // Select2 PADRÃO (sem digitação livre) - para campos que devem ter apenas opções fixas
    $('.form-select').not('#corporacao, #patente, #categoria, #lotacao').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: function() {
            return $(this).attr('placeholder') || 'Selecione...';
        }
    });
    
    // ========================================
    // 🆕 SELECT2 COM DIGITAÇÃO LIVRE (TAGS)
    // ========================================
    
    // Corporação - pode selecionar ou digitar
    $('#corporacao').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: 'Selecione ou digite a corporação...',
        allowClear: true,
        tags: true,
        createTag: function (params) {
            var term = $.trim(params.term);
            if (term === '') {
                return null;
            }
            return {
                id: term,
                text: term,
                newTag: true
            }
        }
    });
    
    // Patente - pode selecionar ou digitar
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
            if (term === '') {
                return null;
            }
            return {
                id: term,
                text: term,
                newTag: true
            }
        }
    });
    
    // Situação Funcional (categoria) - pode selecionar ou digitar
    $('#categoria').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: 'Selecione ou digite a situação...',
        allowClear: true,
        tags: true,
        createTag: function (params) {
            var term = $.trim(params.term);
            if (term === '') {
                return null;
            }
            return {
                id: term,
                text: term,
                newTag: true
            }
        }
    });
    
    // Lotação - pode selecionar ou digitar
    $('#lotacao').select2({
        language: 'pt-BR',
        theme: 'default',
        width: '100%',
        placeholder: 'Selecione ou digite a lotação...',
        allowClear: true,
        tags: true
    });
    
    console.log('✓ Select2 inicializado (com digitação livre nos campos militares)');
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
    
    console.log('✓ Uploads configurados');
}

// FUNÇÃO NOVA: Controlar serviço jurídico por tipo de associado
function controlarServicoJuridico() {
    console.log('=== CONTROLANDO ACESSO AO SERVIÇO JURÍDICO ===');
    
    const tipoAssociado = document.getElementById('tipoAssociadoServico')?.value;
    const servicoJuridicoCheckbox = document.getElementById('servicoJuridico');
    const servicoJuridicoItem = document.getElementById('servicoJuridicoItem');
    const badgeJuridico = document.getElementById('badgeJuridico');
    const mensagemContainer = document.getElementById('mensagemRestricaoJuridico');
    const infoTipoAssociado = document.getElementById('infoTipoAssociado');
    const textoInfoTipo = document.getElementById('textoInfoTipo');
    
    // Tipos que NÃO têm direito ao serviço jurídico
    const tiposSemJuridico = ['Benemérito', 'Benemerito', 'Agregado'];
    
    console.log('Tipo selecionado:', tipoAssociado);
    console.log('Tipos sem jurídico:', tiposSemJuridico);
    
    if (tiposSemJuridico.includes(tipoAssociado)) {
        console.log('❌ Tipo não tem direito ao serviço jurídico');
        
        // Desabilita o serviço jurídico
        if (servicoJuridicoCheckbox) {
            servicoJuridicoCheckbox.disabled = true;
            servicoJuridicoCheckbox.checked = false;
        }
        
        // Adiciona classes visuais
        if (servicoJuridicoItem) {
            servicoJuridicoItem.classList.add('desabilitado', 'servico-bloqueado');
            servicoJuridicoItem.style.opacity = '0.5';
            servicoJuridicoItem.style.pointerEvents = 'none';
        }
        
        // Atualiza o badge
        if (badgeJuridico) {
            badgeJuridico.style.background = '#dc3545';
            badgeJuridico.textContent = 'INDISPONÍVEL';
        }
        
        // Mostra mensagem de restrição
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
                    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.1);
                ">
                    <i class="fas fa-exclamation-triangle"></i>
                    Associados do tipo "${tipoAssociado}" não têm direito ao serviço jurídico conforme regulamento da ASSEGO.
                </div>
            `;
        }
        
        // Mostra informação no campo de tipo
        if (infoTipoAssociado && textoInfoTipo) {
            infoTipoAssociado.style.display = 'block';
            textoInfoTipo.innerHTML = `Tipo "${tipoAssociado}" não tem direito ao serviço jurídico.`;
            infoTipoAssociado.style.color = '#dc3545';
        }
        
        // Zera os valores do serviço jurídico
        zerarValoresJuridico();
        
    } else {
        console.log('✅ Tipo tem direito ao serviço jurídico');
        
        // Habilita o serviço jurídico
        if (servicoJuridicoCheckbox) {
            servicoJuridicoCheckbox.disabled = false;
        }
        
        // Remove classes visuais
        if (servicoJuridicoItem) {
            servicoJuridicoItem.classList.remove('desabilitado', 'servico-bloqueado');
            servicoJuridicoItem.style.opacity = '1';
            servicoJuridicoItem.style.pointerEvents = 'auto';
        }
        
        // Restaura o badge
        if (badgeJuridico) {
            badgeJuridico.style.background = 'var(--info)';
            badgeJuridico.textContent = 'OPCIONAL';
        }
        
        // Esconde mensagem de restrição
        if (mensagemContainer) {
            mensagemContainer.style.display = 'none';
            mensagemContainer.innerHTML = '';
        }
        
        // Esconde informação do tipo se era sobre restrição
        if (textoInfoTipo && textoInfoTipo.textContent.includes('não tem direito')) {
            if (infoTipoAssociado) {
                infoTipoAssociado.style.display = 'none';
            }
        }
    }
    
    console.log('=== FIM CONTROLE SERVIÇO JURÍDICO ===');
}

// FUNÇÃO NOVA: Zerar valores do serviço jurídico
function zerarValoresJuridico() {
    console.log('Zerando valores do serviço jurídico...');
    
    updateElementSafe('valorJuridico', '0', 'value');
    updateElementSafe('percentualAplicadoJuridico', '0', 'value');
    updateElementSafe('valorFinalJuridico', '0,00');
    updateElementSafe('percentualJuridico', '0');
    
    console.log('✓ Valores jurídicos zerados');
}

// FUNÇÃO NOVA: Validar tipos e serviços
function validarTipoEServicos() {
    const tipoAssociado = document.getElementById('tipoAssociadoServico')?.value;
    
    if (!tipoAssociado) {
        showAlert('Por favor, selecione o tipo de associado antes de prosseguir.', 'warning');
        return false;
    }
    
    // Executa o controle uma última vez para garantir consistência
    controlarServicoJuridico();
    
    return true;
}

// Configurar listeners dos serviços (ATUALIZADA)
function configurarListenersServicos() {
    console.log('Configurando listeners dos serviços + controle jurídico...');
    
    const tipoAssociadoEl = document.getElementById('tipoAssociadoServico');
    const servicoJuridicoEl = document.getElementById('servicoJuridico');

    if (tipoAssociadoEl) {
        tipoAssociadoEl.addEventListener('change', function() {
            console.log('Tipo de associado alterado:', this.value);
            controlarServicoJuridico();
            calcularServicos();
        });
        console.log('✓ Listener do tipo de associado adicionado (+ controle jurídico)');
    }

    if (servicoJuridicoEl) {
        servicoJuridicoEl.addEventListener('change', calcularServicos);
        console.log('✓ Listener do serviço jurídico adicionado');
    }
    
    console.log('✓ Listeners dos serviços configurados');
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
            useHardcodedData();
            preencherSelectTiposAssociado();

            console.log('✓ Dados de fallback carregados');
            return true;
        });
}

// Preencher select de tipos de associado
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
        
        // Adiciona indicação visual para tipos sem direito ao jurídico
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

    console.log(`✓ Select preenchido com ${tiposAssociadoData.length} tipos de associado (+ indicações de restrição)`);

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
        { tipo_associado: "Agregado", servico_id: "2", percentual_valor: "0.00" },
        { tipo_associado: "Remido", servico_id: "1", percentual_valor: "0.00" },
        { tipo_associado: "Remido", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Remido 50%", servico_id: "1", percentual_valor: "50.00" },
        { tipo_associado: "Remido 50%", servico_id: "2", percentual_valor: "100.00" },
        { tipo_associado: "Benemerito", servico_id: "1", percentual_valor: "0.00" },
        { tipo_associado: "Benemerito", servico_id: "2", percentual_valor: "0.00" }
    ];

    tiposAssociadoData = ["Contribuinte", "Aluno", "Soldado 2ª Classe", "Soldado 1ª Classe", "Agregado", "Remido 50%", "Remido", "Benemerito"];
    dadosCarregados = true;

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
                console.log('✓ Serviços carregados e preenchidos com sucesso');
            } else {
                console.warn('API retornou erro:', data.message || 'Erro desconhecido');
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
            setTimeout(() => {
                if (document.getElementById('tipoAssociadoServico').value) {
                    controlarServicoJuridico();
                    calcularServicos();
                }
            }, 500);
        });
}

// FUNÇÃO CORRIGIDA: Preencher dados dos serviços
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
        updateElementSafe('valorFinalSocial', parseFloat(social.valor_aplicado).toFixed(2).replace('.', ','));
        updateElementSafe('percentualSocial', parseFloat(social.percentual_aplicado).toFixed(0));

        // Busca valor base para exibição
        const servicoSocial = servicosData.find(s => s.id == 1);
        if (servicoSocial) {
            updateElementSafe('valorBaseSocial', parseFloat(servicoSocial.valor_base).toFixed(2).replace('.', ','));
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
        updateElementSafe('valorFinalJuridico', parseFloat(juridico.valor_aplicado).toFixed(2).replace('.', ','));
        updateElementSafe('percentualJuridico', parseFloat(juridico.percentual_aplicado).toFixed(0));

        // Busca valor base para exibição
        const servicoJuridico = servicosData.find(s => s.id == 2);
        if (servicoJuridico) {
            updateElementSafe('valorBaseJuridico', parseFloat(servicoJuridico.valor_base).toFixed(2).replace('.', ','));
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
    updateElementSafe('valorTotalGeral', parseFloat(totalMensal).toFixed(2).replace('.', ','));

    // IMPORTANTE: Executa controle do serviço jurídico após preencher
    setTimeout(() => {
        controlarServicoJuridico();
    }, 100);

    console.log('✓ Total mensal:', totalMensal);
    console.log('=== FIM PREENCHIMENTO ===');
}

// FUNÇÃO CORRIGIDA: Cálculo de serviços (INCLUINDO CONTROLE JURÍDICO)
function calcularServicos() {
    console.log('=== CALCULANDO SERVIÇOS + CONTROLE JURÍDICO ===');
    
    // PRIMEIRO: Controla o acesso ao serviço jurídico
    controlarServicoJuridico();

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
    const servicoJuridicoChecked = servicoJuridicoEl.checked && !servicoJuridicoEl.disabled;

    console.log('Calculando para:', { tipoAssociado, servicoJuridicoChecked, disabled: servicoJuridicoEl.disabled });

    if (!tipoAssociado) {
        resetarCalculos();
        return;
    }

    // Buscar regras para o tipo de associado selecionado
    const regrasSocial = regrasContribuicao.filter(r =>
        r.tipo_associado === tipoAssociado && r.servico_id == 1
    );
    const regrasJuridico = regrasContribuicao.filter(r =>
        r.tipo_associado === tipoAssociado && r.servico_id == 2
    );

    console.log('Regras encontradas:', { regrasSocial, regrasJuridico });

    let valorTotalGeral = 0;

    // Calcular Serviço Social (sempre obrigatório)
    if (regrasSocial.length > 0) {
        const regra = regrasSocial[0];
        const servicoSocial = servicosData.find(s => s.id == 1);
        const valorBase = parseFloat(servicoSocial?.valor_base || 173.10);
        const percentual = parseFloat(regra.percentual_valor);
        const valorFinal = (valorBase * percentual) / 100;

        console.log('Cálculo Social:', { valorBase, percentual, valorFinal });

        // Atualiza elementos do DOM
        updateElementSafe('valorBaseSocial', valorBase.toFixed(2).replace('.', ','));
        updateElementSafe('percentualSocial', percentual.toFixed(0));
        updateElementSafe('valorFinalSocial', valorFinal.toFixed(2).replace('.', ','));
        updateElementSafe('valorSocial', valorFinal.toFixed(2), 'value');
        updateElementSafe('percentualAplicadoSocial', percentual.toFixed(2), 'value');

        valorTotalGeral += valorFinal;
    }

    // Calcular Serviço Jurídico (SE habilitado E selecionado)
    if (servicoJuridicoChecked && regrasJuridico.length > 0) {
        const regra = regrasJuridico[0];
        const servicoJuridico = servicosData.find(s => s.id == 2);
        const valorBase = parseFloat(servicoJuridico?.valor_base || 43.28);
        const percentual = parseFloat(regra.percentual_valor);
        const valorFinal = (valorBase * percentual) / 100;

        console.log('Cálculo Jurídico:', { valorBase, percentual, valorFinal });

        updateElementSafe('valorBaseJuridico', valorBase.toFixed(2).replace('.', ','));
        updateElementSafe('percentualJuridico', percentual.toFixed(0));
        updateElementSafe('valorFinalJuridico', valorFinal.toFixed(2).replace('.', ','));
        updateElementSafe('valorJuridico', valorFinal.toFixed(2), 'value');
        updateElementSafe('percentualAplicadoJuridico', percentual.toFixed(2), 'value');

        valorTotalGeral += valorFinal;
    } else {
        // Reset jurídico se não selecionado ou desabilitado
        updateElementSafe('percentualJuridico', '0');
        updateElementSafe('valorFinalJuridico', '0,00');
        updateElementSafe('valorJuridico', '0', 'value');
        updateElementSafe('percentualAplicadoJuridico', '0', 'value');
        
        console.log('Serviço jurídico zerado (não selecionado ou desabilitado)');
    }

    // Atualizar total geral
    updateElementSafe('valorTotalGeral', valorTotalGeral.toFixed(2).replace('.', ','));

    console.log('Valor total calculado:', valorTotalGeral);
    console.log('=== FIM CÁLCULO SERVIÇOS ===');
}

// Função para resetar cálculos
function resetarCalculos() {
    console.log('Resetando cálculos...');

    // Valores base dos serviços vindos do banco
    const servicoSocial = servicosData.find(s => s.id == 1);
    const servicoJuridico = servicosData.find(s => s.id == 2);

    const valorBaseSocial = servicoSocial ? parseFloat(servicoSocial.valor_base).toFixed(2).replace('.', ',') : '173,10';
    const valorBaseJuridico = servicoJuridico ? parseFloat(servicoJuridico.valor_base).toFixed(2).replace('.', ',') : '43,28';

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

// ===== NOVAS FUNÇÕES DE SALVAMENTO MULTI-STEP =====

// FUNÇÃO PRINCIPAL: Salvar apenas o step atual
function salvarStepAtual() {
    if (salvandoStep) {
        console.log('Já está salvando, ignorando...');
        return;
    }

    console.log(`=== SALVANDO STEP ${currentStep} ===`);

    // Validação específica do step atual
    if (!validarStepAtual()) {
        showAlert('Por favor, corrija os erros antes de salvar.', 'warning');
        return;
    }

    // ✅ VERIFICA SE É AGREGADO
    const isAgregado = document.getElementById('isAgregado')?.checked;
    
    console.log('🔍 Verificação:', {
        isAgregado: isAgregado,
        isEdit: isEdit,
        associadoId: associadoId,
        currentStep: currentStep
    });

    // ✅✅✅ SE FOR AGREGADO EM MODO EDIÇÃO, USA salvarAssociado() ✅✅✅
    if (isAgregado && isEdit) {
        console.log('🔄 AGREGADO em EDIÇÃO detectado - usando salvarAssociado()');
        salvarAssociado(); // Chama a função principal que já está corrigida
        return;
    }

    // Para novos cadastros de AGREGADO, step 1 precisa criar o registro primeiro
    if (isAgregado && !isEdit && !window.pageData.associadoId && currentStep === 1) {
        console.log('🔄 AGREGADO NOVO - step 1 - criando registro');
        salvarNovoAssociadoPrimeiroPasso();
        return;
    }

    // Para novos cadastros de ASSOCIADO NORMAL, step 1 precisa criar o registro primeiro
    if (!isAgregado && !isEdit && !window.pageData.associadoId && currentStep === 1) {
        console.log('🔄 ASSOCIADO NOVO - step 1 - criando registro');
        salvarNovoAssociadoPrimeiroPasso();
        return;
    }

    // ✅ PARA TODOS OS OUTROS CASOS: Usa a API de atualização
    salvandoStep = true;
    mostrarEstadoSalvando();

    const formData = criarFormDataStep(currentStep);
    
    if (!formData) {
        esconderEstadoSalvando();
        salvandoStep = false;
        return;
    }

    // Usa a API de atualização existente (só para associados normais)
    const associadoAtualId = isEdit ? associadoId : window.pageData.associadoId;
    const url = `../api/atualizar_associado.php?id=${associadoAtualId}`;

    console.log(`Chamando API de atualização: ${url}`);

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        esconderEstadoSalvando();
        salvandoStep = false;

        if (data.status === 'success') {
            // Marca step como salvo
            stepsSalvos.add(currentStep);
            
            // Se ainda não tinha ID (primeiro salvamento), armazena
            if (!isEdit && !window.pageData.associadoId && data.data && data.data.id) {
                window.pageData.associadoId = data.data.id;
                window.pageData.isEdit = true;
                
                // Adiciona campo hidden com ID
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

            console.log(`✓ Step ${currentStep} salvo com sucesso!`);
            
            // Mostra mensagem específica do step salvo
            const stepNames = {
                1: 'Dados Pessoais',
                2: 'Dados Militares', 
                3: 'Endereço',
                4: 'Financeiro',
                5: 'Dependentes'
            };
            
            showAlert(`${stepNames[currentStep]} salvos com sucesso!`, 'success');

        } else {
            console.error('Erro ao salvar step:', data);
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

// Criar FormData específico para cada step
function criarFormDataStep(step) {
    const form = document.getElementById('formAssociado');
    const formData = new FormData();

    // ID do associado (se existir)
    if (isEdit || window.pageData.associadoId) {
        formData.append('id', isEdit ? associadoId : window.pageData.associadoId);
    }

    // SEMPRE inclui campos obrigatórios básicos para passar na validação da API
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

    // Campos específicos por step
    switch(step) {
        case 1: // Dados Pessoais
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

            // 🆕 AGREGADO: Adiciona CPF do titular se for agregado
            const isAgregado = document.getElementById('isAgregado')?.checked;
            if (isAgregado) {
                const cpfTitular = document.getElementById('cpfTitular')?.value;
                if (cpfTitular) {
                    formData.append('cpfTitular', cpfTitular);
                    formData.append('socioTitularCpf', cpfTitular);
                }
                formData.append('isAgregado', '1');
            }

            // Arquivos (foto e ficha assinada)
            const fotoFile = document.getElementById('foto').files[0];
            if (fotoFile) {
                formData.append('foto', fotoFile);
            }

            // Ficha assinada (disponível tanto para novos cadastros quanto para edição)
            const fichaFile = document.getElementById('ficha_assinada')?.files[0];
            if (fichaFile) {
                formData.append('ficha_assinada', fichaFile);
                formData.append('enviar_presidencia', '1');
                console.log('✓ Ficha assinada anexada:', fichaFile.name);
            }
            break;

        case 2: // Dados Militares
            const camposStep2 = ['corporacao', 'patente', 'categoria', 'lotacao', 'unidade'];
            camposStep2.forEach(campo => {
                const element = form.elements[campo];
                if (element) {
                    formData.append(campo, element.value);
                }
            });
            break;

        case 3: // Endereço
            const camposStep3 = ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade'];
            camposStep3.forEach(campo => {
                const element = form.elements[campo];
                if (element) {
                    formData.append(campo, element.value);
                }
            });
            break;

        case 4: // Financeiro
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

            // Dados dos serviços
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

        case 5: // Dependentes
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

        default:
            console.warn('Step não reconhecido:', step);
            return null;
    }

    return formData;
}

// Salvar novo associado - primeiro passo (usa API existente)
function salvarNovoAssociadoPrimeiroPasso() {
    console.log('=== SALVANDO NOVO ASSOCIADO - USANDO API EXISTENTE ===');

    if (!validarStepAtual()) {
        showAlert('Por favor, corrija os erros antes de salvar.', 'warning');
        return;
    }

    salvandoStep = true;
    mostrarEstadoSalvando();

    // Usa o FormData completo mas só com os campos preenchidos
    const formData = new FormData(document.getElementById('formAssociado'));

    // Verifica se é agregado
    const isAgregado = document.getElementById('isAgregado')?.checked;
    let url = '../api/criar_associado.php';
    if (isAgregado) {
        url = '../api/criar_agregado.php';
        // Garante que o campo do CPF do titular seja enviado corretamente
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
            // Atualiza estado para modo edição
            window.pageData.isEdit = true;
            window.pageData.associadoId = data.data.associado_id || data.data.id;
            
            // Adiciona campo hidden com ID
            const hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = 'id';
            hiddenId.id = 'associado_id_hidden';
            hiddenId.value = window.pageData.associadoId;
            document.getElementById('formAssociado').appendChild(hiddenId);

            // Marca step como salvo
            stepsSalvos.add(1);
            mostrarSucessoSalvamento();
            atualizarIndicadoresStep();

            console.log('✓ Associado criado com sucesso! ID:', window.pageData.associadoId);
            showAlert('Dados Pessoais salvos com sucesso! Associado criado.', 'success');

        } else {
            console.error('Erro ao criar associado:', data);
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

// Estados visuais do botão de salvamento
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
    // Atualiza indicadores visuais nos steps salvos
    stepsSalvos.forEach(stepNum => {
        const stepElement = document.querySelector(`[data-step="${stepNum}"]`);
        if (stepElement) {
            stepElement.classList.add('saved');
        }
    });
}

// Funções de navegação
function proximoStep() {
    // VALIDAÇÃO ESPECÍFICA para step financeiro
    if (currentStep === 4) {
        if (!validarTipoEServicos()) {
            return;
        }
    }
    
    if (validarStepAtual()) {
        if (currentStep < totalSteps) {
            // Marca step atual como completo
            document.querySelector(`[data-step="${currentStep}"]`).classList.add('completed');
            
            irParaStep(currentStep + 1);
            
            // Se for o último step, preenche a revisão
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
    setTimeout(() => {
        inicializarNavegacaoSteps();
    }, 1000);

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

// Botões de navegação + salvamento
function updateNavigationButtons() {
    const btnVoltar = document.getElementById('btnVoltar');
    const btnProximo = document.getElementById('btnProximo');
    const btnSalvar = document.getElementById('btnSalvar');
    const btnSalvarStep = document.getElementById('btnSalvarStep');

    // Botão voltar
    if (btnVoltar) {
        btnVoltar.style.display = currentStep === 1 ? 'none' : 'flex';
    }

    // Botão de salvar step atual (mostra em todos os steps exceto o último)
    if (btnSalvarStep) {
        if (currentStep < totalSteps) {
            btnSalvarStep.style.display = 'flex';
            
            // Atualiza texto baseado no estado
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

    // Botões próximo/salvar completo
    if (currentStep === totalSteps) {
        if (btnProximo) btnProximo.style.display = 'none';
        if (btnSalvar) btnSalvar.style.display = 'flex';
    } else {
        if (btnProximo) btnProximo.style.display = 'flex';
        if (btnSalvar) btnSalvar.style.display = 'none';
    }
}

// VALIDAÇÃO CORRIGIDA do step atual
function validarStepAtual() {
    const stepCard = document.querySelector(`.section-card[data-step="${currentStep}"]`);
    const requiredFields = stepCard.querySelectorAll('[required]');
    let isValid = true;

    // Limpa erros anteriores
    stepCard.querySelectorAll('.form-input').forEach(field => {
        field.classList.remove('error');
    });

    // Valida campos obrigatórios
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        }
    });

    // Validações específicas por step
    if (currentStep === 1) {
        // 🆕 VALIDAÇÃO AGREGADO
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
        
        // Valida CPF
        const cpfField = document.getElementById('cpf');
        if (cpfField && cpfField.value && !validarCPF(cpfField.value)) {
            cpfField.classList.add('error');
            isValid = false;
            showAlert('CPF inválido!', 'error');
        }

        // Valida ficha assinada (apenas para novos cadastros)
        if (!isEdit) {
            const fichaField = document.getElementById('ficha_assinada');
            if (!fichaField.files || fichaField.files.length === 0) {
                showAlert('Por favor, anexe a ficha de filiação assinada!', 'error');
                isValid = false;
            }
        } else {
            const fichaField = document.getElementById('ficha_assinada');
            if (fichaField && fichaField.files && fichaField.files.length > 0) {
                const file = fichaField.files[0];
                if (file.size > 10 * 1024 * 1024) {
                    showAlert('O arquivo da ficha é muito grande! Máximo: 10MB', 'error');
                    isValid = false;
                }
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

    if (currentStep === 4) {
        // Validação do step financeiro + controle jurídico
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

        // Aceita valor 0 para isentos, só não aceita vazio
        if (valorSocial && valorSocial.value === '') {
            isValid = false;
            showAlert('Erro no cálculo dos serviços. Verifique o tipo de associado selecionado!', 'error');
        }
        
        // Verifica se o serviço jurídico está corretamente configurado
        const servicoJuridicoEl = document.getElementById('servicoJuridico');
        const tipoSelecionado = tipoAssociadoServico?.value;
        const tiposSemJuridico = ['Benemérito', 'Benemerito', 'Agregado'];
        
        if (tiposSemJuridico.includes(tipoSelecionado) && servicoJuridicoEl && servicoJuridicoEl.checked) {
            showAlert(`Associados do tipo "${tipoSelecionado}" não podem contratar o serviço jurídico!`, 'error');
            isValid = false;
        }
    }

    if (!isValid) {
        showAlert('Por favor, preencha todos os campos obrigatórios!', 'warning');
    }

    return isValid;
}

// Validação em tempo real
function setupRealtimeValidation() {
    console.log('Configurando validação em tempo real...');
    
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
    
    console.log('✓ Validação em tempo real configurada');
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

function salvarAssociado() {
    console.log('=== SALVANDO ASSOCIADO COMPLETO ===');
    
    // ✅ VALIDAÇÃO AGREGADO
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
        
        console.log('✅ Validação de agregado OK', { cpfTitular, nomeTitular });
    }
    
    // Validação final
    if (!validarFormularioCompleto()) {
        showAlert('Por favor, verifique todos os campos obrigatórios!', 'error');
        return;
    }
    
    showLoading();
    
    // ✅ BUSCA FORMULÁRIO
    const formulario = document.querySelector('form');
    
    if (!formulario) {
        console.error('❌ Formulário não encontrado!');
        hideLoading();
        showAlert('Erro: Formulário não encontrado na página!', 'error');
        return;
    }
    
    console.log('✅ Formulário encontrado');
    const formData = new FormData(formulario);
    
    // ✅✅✅ ADICIONA CAMPOS MANUALMENTE DO DOM (SOLUÇÃO DO PROBLEMA) ✅✅✅
    console.log('📦 Adicionando campos manualmente do DOM...');
    
    const camposManuais = [
        { id: 'nome', name: 'nome' },
        { id: 'cpf', name: 'cpf' },
        { id: 'rg', name: 'rg' },
        { id: 'nasc', name: 'dataNascimento' },
        { id: 'data_nascimento', name: 'dataNascimento' },
        { id: 'dataNascimento', name: 'dataNascimento' },
        { id: 'telefone', name: 'telefone' },
        { id: 'celular', name: 'celular' },
        { id: 'email', name: 'email' },
        { id: 'endereco', name: 'endereco' },
        { id: 'numero', name: 'numero' },
        { id: 'complemento', name: 'complemento' },
        { id: 'bairro', name: 'bairro' },
        { id: 'cidade', name: 'cidade' },
        { id: 'estado', name: 'estado' },
        { id: 'uf', name: 'estado' },
        { id: 'cep', name: 'cep' },
        { id: 'banco', name: 'banco' },
        { id: 'localDebito', name: 'banco' },
        { id: 'agencia', name: 'agencia' },
        { id: 'contaCorrente', name: 'contaCorrente' },
        { id: 'estadoCivil', name: 'estadoCivil' },
        { id: 'dataFiliacao', name: 'dataFiliacao' },
        { id: 'situacao', name: 'situacao' },
        { id: 'escolaridade', name: 'escolaridade' },
        { id: 'operacao', name: 'operacao' },
        { id: 'indicacao', name: 'indicacao' }
    ];

    camposManuais.forEach(campo => {
        const elemento = document.getElementById(campo.id);
        if (elemento && elemento.value && elemento.value.trim() !== '') {
            formData.set(campo.name, elemento.value);
            console.log(`  ✓ ${campo.name}: ${elemento.value}`);
        }
    });

    // ✅ CELULAR: Tenta telefone se celular não existir
    if (!formData.get('celular')) {
        const telefone = document.getElementById('telefone');
        if (telefone && telefone.value) {
            formData.set('celular', telefone.value);
            console.log(`  ✓ celular (do telefone): ${telefone.value}`);
        }
    }

    // ✅ ESTADO: Busca em múltiplos possíveis lugares
    if (!formData.get('estado')) {
        // Tenta buscar de um select
        const estadoSelect = document.querySelector('select[name="estado"], select[name="uf"], select#estado, select#uf');
        if (estadoSelect && estadoSelect.value) {
            formData.set('estado', estadoSelect.value);
            console.log(`  ✓ estado (do select): ${estadoSelect.value}`);
        } else {
            // Tenta buscar de um input
            const estadoInput = document.querySelector('input[name="estado"], input[name="uf"], input#estado, input#uf');
            if (estadoInput && estadoInput.value) {
                formData.set('estado', estadoInput.value);
                console.log(`  ✓ estado (do input): ${estadoInput.value}`);
            } else {
                // Se ainda não encontrou, define GO (Goiás) como padrão baseado na cidade
                const cidade = formData.get('cidade');
                if (cidade && cidade.includes('Goiânia')) {
                    formData.set('estado', 'GO');
                    console.log(`  ✓ estado (padrão GO pela cidade): GO`);
                }
            }
        }
    }

    // ✅ CAMPOS SELECT2 (podem não ser capturados normalmente)
    console.log('📦 Adicionando campos Select2...');
    const camposSelect2 = [
        { id: 'corporacao', name: 'corporacao' },
        { id: 'patente', name: 'patente' },
        { id: 'categoria', name: 'categoria' },
        { id: 'lotacao', name: 'lotacao' },
        { id: 'unidade', name: 'unidade' },
        { id: 'tipoAssociadoServico', name: 'tipoAssociadoServico' },
        { id: 'tipoAssociado', name: 'tipoAssociado' },
        { id: 'situacaoFinanceira', name: 'situacaoFinanceira' },
        { id: 'vinculoServidor', name: 'vinculoServidor' }
    ];
    
    camposSelect2.forEach(campo => {
        const elemento = document.getElementById(campo.id);
        if (elemento && elemento.value) {
            formData.set(campo.name, elemento.value);
            console.log(`  ✓ Select2 ${campo.name}: ${elemento.value}`);
        }
    });

    // ✅ CAMPOS DE RADIO (sexo)
    const sexoRadio = document.querySelector('input[name="sexo"]:checked');
    if (sexoRadio) {
        formData.set('sexo', sexoRadio.value);
        console.log(`  ✓ sexo: ${sexoRadio.value}`);
    }
    
    // ✅ CPF DO TITULAR (AGREGADOS)
    if (isAgregado) {
        const cpfTitular = document.getElementById('cpfTitular')?.value;
        if (cpfTitular) {
            formData.set('cpfTitular', cpfTitular);
            formData.set('socioTitularCpf', cpfTitular);
            console.log('✅ CPF do titular:', cpfTitular);
        }
    }
    
    // ✅ DADOS DOS SERVIÇOS (FINANCEIRO)
    const servicoSocial = document.getElementById('valorSocial');
    const servicoJuridico = document.getElementById('servicoJuridico');
    
    if (servicoSocial && servicoSocial.value) {
        formData.set('servicoSocial', '1');
        formData.set('valorSocial', servicoSocial.value);
        formData.set('percentualAplicadoSocial', document.getElementById('percentualAplicadoSocial')?.value || '0');
        console.log('  ✓ Serviço Social adicionado');
    }
    
    if (servicoJuridico && servicoJuridico.checked && !servicoJuridico.disabled) {
        formData.set('servicoJuridico', '2');
        formData.set('valorJuridico', document.getElementById('valorJuridico')?.value || '0');
        formData.set('percentualAplicadoJuridico', document.getElementById('percentualAplicadoJuridico')?.value || '0');
        console.log('  ✓ Serviço Jurídico adicionado');
    }
    
    // ✅ LOG FINAL - CAMPOS CRÍTICOS
    console.log('📦 VERIFICAÇÃO FINAL DOS CAMPOS OBRIGATÓRIOS:');
    console.log('  nome:', formData.get('nome'));
    console.log('  cpf:', formData.get('cpf'));
    console.log('  dataNascimento:', formData.get('dataNascimento'));
    console.log('  telefone:', formData.get('telefone'));
    console.log('  celular:', formData.get('celular'));
    console.log('  estado:', formData.get('estado'));
    console.log('  banco:', formData.get('banco'));
    console.log('  cpfTitular:', formData.get('cpfTitular'));
    console.log('  estadoCivil:', formData.get('estadoCivil'));
    console.log('  dataFiliacao:', formData.get('dataFiliacao'));
    
    // ⚠️ VALIDAÇÃO FINAL: Garante que campos críticos existem
    const camposCriticos = [
        { campo: 'celular', nome: 'Celular' },
        { campo: 'estado', nome: 'Estado' }
    ];
    
    let faltaCampo = false;
    camposCriticos.forEach(item => {
        if (!formData.get(item.campo)) {
            console.error(`❌ Campo ${item.campo} está faltando!`);
            faltaCampo = true;
        }
    });
    
    if (faltaCampo) {
        hideLoading();
        showAlert('Erro: Campos obrigatórios não foram preenchidos (Celular e Estado). Verifique o formulário.', 'error');
        return;
    }
    
    // ✅ DEFINE URL
    const associadoId = document.getElementById('associadoId')?.value;
    let url;
    
    if (isAgregado) {
        url = '../api/criar_agregado.php';
        console.log('📋 Modo AGREGADO');
    } else {
        url = associadoId ? 
            `../api/atualizar_associado.php?id=${associadoId}` : 
            '../api/criar_associado.php';
        console.log('📋 Modo ASSOCIADO NORMAL');
    }
    
    console.log('📡 Enviando para:', url);
    console.log('🆔 ID:', associadoId || 'Novo');
    
    // ✅ ENVIA REQUISIÇÃO
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📥 Response Status:', response.status);
        return response.text();
    })
    .then(text => {
        console.log('📄 Resposta do servidor (primeiros 1000 chars):');
        console.log(text.substring(0, 1000));
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('❌ Erro ao fazer parse JSON:', e);
            console.error('📄 Resposta completa:', text);
            hideLoading();
            showAlert('Resposta inválida do servidor. Verifique o console (F12) para mais detalhes.', 'error');
            return;
        }
        
        hideLoading();
        
        if (data.status === 'success') {
            showAlert(data.message || 'Salvo com sucesso!', 'success');
            console.log('✅ Operação concluída com sucesso:', data);
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            let erro = data.message || 'Erro ao salvar';
            if (data.errors && Array.isArray(data.errors)) {
                erro += ':\n• ' + data.errors.join('\n• ');
            }
            showAlert(erro, 'error');
            console.error('❌ Erro retornado pelo servidor:', data);
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        hideLoading();
        showAlert('Erro ao comunicar com o servidor: ' + error.message, 'error');
    });
}
// Preencher revisão
function preencherRevisao() {
    console.log('Preenchendo revisão...');
    
    const container = document.getElementById('revisaoContainer');
    if (!container) return;

    const form = document.getElementById('formAssociado');
    const formData = new FormData(form);

    // Coleta dados dos campos
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

    // Status do serviço jurídico
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

    // Mostra quais steps foram salvos
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
    console.log('✓ Revisão preenchida com indicação de steps salvos');
}

// Validação do formulário completo
function validarFormularioCompleto() {
    console.log('Validando formulário completo...');
    
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

    console.log('Formulário válido:', isValid);
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

// Navegação por clique nos steps
function irParaStep(numeroStep) {
    if (numeroStep < 1 || numeroStep > totalSteps) return;
    
    if (numeroStep > currentStep && !validarStepAtual()) {
        return;
    }
    
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
    
    console.log('✓ Navegação por clique nos steps inicializada');
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

// Atalhos de teclado para navegação
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

// Log final
console.log('✓ JavaScript do formulário carregado completamente!');
console.log('✓ Funcionalidades incluídas:');
console.log('  - Salvamento individual por step');
console.log('  - Controle de serviço jurídico por tipo de associado');
console.log('  - Busca de titular para agregados (CPF)');
console.log('  - Validações robustas por step');
console.log('  - Indicadores visuais de steps salvos');
console.log('  - Atalho Ctrl+S para salvamento rápido');
console.log('  - Estados visuais de salvamento em andamento');
console.log('  - Lógica para esconder checkbox "Cadastrar como Agregado" se já for agregado');