/**
 * Sistema NeoConsig - Versão Aba
 * Funciona como partial dentro do sistema de navegação financeiro
 */

window.NeoConsig = (function() {
    'use strict';

    let notifications;
    let isInitialized = false;

    // ===== SISTEMA DE NOTIFICAÇÕES =====
    class NotificationSystemNeo {
        constructor() {
            // Usar o container existente ou criar um novo
            this.container = document.getElementById('toastContainer') || document.getElementById('toastContainerNeo');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainerNeo';
                this.container.className = 'toast-container position-fixed top-0 end-0 p-3';
                document.body.appendChild(this.container);
            }
        }

        show(message, type = 'success', duration = 5000) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.style.minWidth = '350px';

            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${this.getIcon(type)} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            this.container.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: duration });
            bsToast.show();

            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        getIcon(type) {
            const icons = {
                success: 'check-circle',
                error: 'exclamation-triangle',
                warning: 'exclamation-circle',
                info: 'info-circle'
            };
            return icons[type] || 'info-circle';
        }
    }

    // ===== FUNÇÕES PRINCIPAIS =====
    function init(config = {}) {
    if (isInitialized) {
        console.log('NeoConsig já foi inicializado');
        return;
    }

    console.log('🚀 Inicializando NeoConsig...', config);
    
    // Inicializar sistema de notificações
    notifications = new NotificationSystemNeo();

    // ✅ INICIALIZAR BOTÃO COMO DESABILITADO
    const btnGerar = document.getElementById('btnGerarNeo');
    if (btnGerar) {
        btnGerar.disabled = true;
        btnGerar.innerHTML = '<i class="fas fa-search me-2"></i>Busque os Associados Primeiro';
        btnGerar.classList.add('btn-secondary');
        btnGerar.classList.remove('btn-success');
    }

    // Configurar event listeners
    setupEventListeners();

    isInitialized = true;
    notifications.show('Sistema NeoConsig carregado! Busque os associados antes de gerar o arquivo.', 'info', 3000);
    
    console.log('✅ NeoConsig inicializado com sucesso');
}

   function setupEventListeners() {
    // Event listeners para mudanças nos campos
    const tipoProcessamento = document.getElementById('tipoProcessamentoNeo');
    const matriculas = document.getElementById('matriculasNeo');
    const rubrica = document.getElementById('rubricaNeo');
    
    if (tipoProcessamento) {
        tipoProcessamento.addEventListener('change', function() {
            toggleCampos();
            resetarBotaoNeoQuandoCamposMudarem();
        });
    }
    
    if (matriculas) {
        matriculas.addEventListener('input', function() {
            validarMatriculas();
            resetarBotaoNeoQuandoCamposMudarem();
        });
        matriculas.addEventListener('change', function() {
            validarMatriculas();
            resetarBotaoNeoQuandoCamposMudarem();
        });
    }
}

    function toggleCampos() {
        // Esta função pode ser expandida se necessário para mostrar/esconder campos
        // baseados no tipo de processamento selecionado
        console.log('Tipo de processamento alterado');
    }

    function validarMatriculas() {
        const matriculasElement = document.getElementById('matriculasNeo');
        const preview = document.getElementById('previewMatriculasNeo');
        const lista = document.getElementById('listaMatriculasNeo');
        const previewAssociados = document.getElementById('previewAssociadosNeo');
        
        if (!matriculasElement || !preview || !lista) return;
        
        const matriculas = matriculasElement.value.trim();
        
        if (matriculas) {
            const matriculasArray = matriculas.split(',').map(m => m.trim()).filter(m => m);
            const matriculasValidas = matriculasArray.filter(m => /^\d+$/.test(m));
            const matriculasInvalidas = matriculasArray.filter(m => !/^\d+$/.test(m));
            
            let html = `<div class="row">`;
            html += `<div class="col-md-4"><strong><i class="fas fa-list-ol me-2"></i>Encontradas:</strong> ${matriculasValidas.length}</div>`;
            html += `</div>`;
            
            if (matriculasInvalidas.length > 0) {
                html += `<div class="mt-2"><strong class="text-danger"><i class="fas fa-times me-2"></i>Inválidas:</strong> ${matriculasInvalidas.join(', ')}</div>`;
            }
            
            lista.innerHTML = html;
            preview.style.display = 'block';
            preview.classList.add('animate-fade-in-neo');
        } else {
            preview.style.display = 'none';
            if (previewAssociados) {
                previewAssociados.style.display = 'none';
            }
        }
    }

    async function buscarAssociados() {
    const matriculasElement = document.getElementById('matriculasNeo');
    const previewAssociados = document.getElementById('previewAssociadosNeo');
    const loading = document.getElementById('loadingAssociadosNeo');
    const listaAssociados = document.getElementById('listaAssociadosNeo');
    const resumoAssociados = document.getElementById('resumoAssociadosNeo');
    
    // ✅ NOVO: Referência ao botão de gerar
    const btnGerar = document.getElementById('btnGerarNeo');
    
    if (!matriculasElement) {
        notifications.show('Elemento de matrículas não encontrado', 'error');
        return;
    }

    const matriculas = matriculasElement.value.trim();
    
    if (!matriculas) {
        notifications.show('Digite as matrículas primeiro', 'warning');
        return;
    }
    
    // Mostrar loading
    if (previewAssociados) {
        previewAssociados.style.display = 'block';
        previewAssociados.classList.add('animate-fade-in-neo');
    }
    if (loading) loading.style.display = 'block';
    if (listaAssociados) listaAssociados.innerHTML = '';
    if (resumoAssociados) resumoAssociados.innerHTML = '';
    
    // ✅ NOVO: Desabilitar botão durante a busca
    if (btnGerar) {
        btnGerar.disabled = true;
        btnGerar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Buscando associados...';
    }
    
    try {
        // Fazer requisição para o endpoint de preview
        const response = await fetch(`../pages/gerar_recorrencia.php?preview_associados=1&matriculas=${encodeURIComponent(matriculas)}`);
        const data = await response.json();
        
        if (loading) loading.style.display = 'none';
        
        if (data.success) {
            if (data.encontrados.length > 0) {
                // ✅ ASSOCIADOS ENCONTRADOS - HABILITAR BOTÃO
                let html = '';
                
                data.encontrados.forEach(assoc => {
                    const cpfFormatado = assoc.cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                    const tipoElement = document.getElementById('tipoProcessamentoNeo');
                    const tipo = tipoElement ? tipoElement.value : '';
                    
                    // Mostrar ID de operação para cancelamentos e alterações
                    let idOperacaoInfo = '';
                    if (tipo === '0' || tipo === '2') {
                        const idOperacao = assoc.id_neoconsig || 'Não encontrado';
                        idOperacaoInfo = `<br><small><i class="fas fa-key me-1"></i>ID Operação: <span class="badge bg-secondary">${idOperacao}</span></small>`;
                    }
                    
                    // Mostrar vínculo servidor
                    let vinculoInfo = '';
                    if (assoc.vinculoServidor) {
                        vinculoInfo = `<br><small><i class="fas fa-id-badge me-1"></i>Vínculo: <span class="badge bg-info">${assoc.vinculoServidor}</span></small>`;
                    }
                    
                    html += `
                        <div class="associado-card-neo">
                            <div class="associado-info-neo">
                                <strong><i class="fas fa-user me-2"></i>${assoc.nome}</strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-id-badge me-1"></i>Matrícula: <span class="badge bg-primary">${assoc.id}</span> 
                                    | <i class="fas fa-id-card me-1"></i>CPF: <code>${cpfFormatado}</code>${vinculoInfo}${idOperacaoInfo}
                                </small>
                            </div>
                            <div class="associado-valor-neo">
                                <i class="fas fa-dollar-sign me-1"></i>R$ ${parseFloat(assoc.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                            </div>
                        </div>
                    `;
                });
                
                if (listaAssociados) {
                    listaAssociados.innerHTML = html;
                }
                
                // Mostrar resumo
                if (resumoAssociados) {
                    let resumoHtml = `
                        <div class="alert alert-success mt-3">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <strong><i class="fas fa-users me-2"></i>Encontrados:</strong> ${data.total_encontrados}
                                </div>
                                <div class="col-md-4">
                                    <strong><i class="fas fa-money-bill-wave me-2"></i>Total Mensal:</strong> R$ ${parseFloat(data.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                                </div>
                                <div class="col-md-4">
                                    <strong><i class="fas fa-calculator me-2"></i>Média:</strong> R$ ${(parseFloat(data.valor_total) / data.total_encontrados).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    if (data.nao_encontrados.length > 0) {
                        resumoHtml += `
                            <div class="alert alert-warning mt-2">
                                <strong><i class="fas fa-exclamation-triangle me-2"></i>Não encontrados:</strong> ${data.nao_encontrados.join(', ')}
                            </div>
                        `;
                    }
                    
                    resumoAssociados.innerHTML = resumoHtml;
                }
                
                // ✅ HABILITAR BOTÃO - ASSOCIADOS ENCONTRADOS
                if (btnGerar) {
                    btnGerar.disabled = false;
                    btnGerar.innerHTML = '<i class="fas fa-file-download me-2"></i>Gerar e Baixar Arquivo TXT';
                    btnGerar.classList.remove('btn-danger', 'btn-secondary');
                    btnGerar.classList.add('btn-success');
                }
                
                notifications.show(`${data.total_encontrados} associados encontrados! Botão liberado.`, 'success');
                
            } else {
                // ✅ NENHUM ASSOCIADO ENCONTRADO - BLOQUEAR BOTÃO
                if (listaAssociados) {
                    listaAssociados.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Nenhum associado encontrado com as matrículas informadas.
                            <br><strong>O arquivo não pode ser gerado sem associados válidos.</strong>
                        </div>
                    `;
                }
                
                // ✅ BLOQUEAR BOTÃO
                if (btnGerar) {
                    btnGerar.disabled = true;
                    btnGerar.innerHTML = '<i class="fas fa-ban me-2"></i>Nenhum Associado - Arquivo Bloqueado';
                    btnGerar.classList.remove('btn-success', 'btn-secondary');
                    btnGerar.classList.add('btn-danger');
                }
                
                notifications.show('Nenhum associado encontrado. Geração de arquivo bloqueada.', 'error');
            }
        } else {
            // ✅ ERRO NA BUSCA - BLOQUEAR BOTÃO
            if (listaAssociados) {
                listaAssociados.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Erro: ${data.error}
                    </div>
                `;
            }
            
            // ✅ BLOQUEAR BOTÃO
            if (btnGerar) {
                btnGerar.disabled = true;
                btnGerar.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Erro na Busca - Arquivo Bloqueado';
                btnGerar.classList.remove('btn-success', 'btn-secondary');
                btnGerar.classList.add('btn-danger');
            }
            
            notifications.show('Erro na busca: ' + data.error, 'error');
        }
        
    } catch (error) {
        if (loading) loading.style.display = 'none';
        if (listaAssociados) {
            listaAssociados.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Erro na comunicação: ${error.message}
                </div>
            `;
        }
        
        // ✅ ERRO DE COMUNICAÇÃO - BLOQUEAR BOTÃO
        if (btnGerar) {
            btnGerar.disabled = true;
            btnGerar.innerHTML = '<i class="fas fa-wifi me-2"></i>Erro de Comunicação - Arquivo Bloqueado';
            btnGerar.classList.remove('btn-success', 'btn-secondary');
            btnGerar.classList.add('btn-danger');
        }
        
        notifications.show('Erro na comunicação com o servidor', 'error');
    }
}

// ✅ NOVA FUNÇÃO: Validar antes de gerar arquivo
function validarAntesDeGerarNeo(event) {
    const btnGerar = document.getElementById('btnGerarNeo');
    
    // Se o botão estiver desabilitado, impedir o envio
    if (btnGerar && btnGerar.disabled) {
        event.preventDefault();
        notifications.show('Você precisa buscar associados válidos antes de gerar o arquivo!', 'error');
        return false;
    }
    
    // Verificar se foi feita uma busca
    const previewAssociados = document.getElementById('previewAssociadosNeo');
    const listaAssociados = document.getElementById('listaAssociadosNeo');
    
    if (!previewAssociados || previewAssociados.style.display === 'none' || 
        !listaAssociados || !listaAssociados.innerHTML.includes('associado-card-neo')) {
        event.preventDefault();
        notifications.show('Você deve buscar os associados primeiro clicando em "Buscar Associado(s)"!', 'warning');
        return false;
    }
    
    return true;
}

// ✅ NOVA FUNÇÃO: Resetar botão quando campos mudarem
function resetarBotaoNeoQuandoCamposMudarem() {
    const btnGerar = document.getElementById('btnGerarNeo');
    const previewAssociados = document.getElementById('previewAssociadosNeo');
    
    if (btnGerar) {
        btnGerar.disabled = true;
        btnGerar.innerHTML = '<i class="fas fa-search me-2"></i>Busque os Associados Primeiro';
        btnGerar.classList.remove('btn-success', 'btn-danger');
        btnGerar.classList.add('btn-secondary');
    }
    
    if (previewAssociados) {
        previewAssociados.style.display = 'none';
    }
}

    async function gerarArquivo(event) {
    // ✅ VALIDAR ANTES DE CONTINUAR
    if (!validarAntesDeGerarNeo(event)) {
        return;
    }

    event.preventDefault();

    const tipoProcessamento = document.getElementById('tipoProcessamentoNeo');
    const matriculas = document.getElementById('matriculasNeo');
    const rubrica = document.getElementById('rubricaNeo');
    const btnGerar = document.getElementById('btnGerarNeo');
    const loading = document.getElementById('loadingNeoConsig');
    const alert = document.getElementById('alertNeoConsig');
    const alertText = document.getElementById('alertNeoConsigText');

    if (!tipoProcessamento || !matriculas) {
        notifications.show('Elementos do formulário não encontrados', 'error');
        return;
    }

    // Validações
    if (!tipoProcessamento.value) {
        notifications.show('Selecione o tipo de processamento', 'warning');
        return;
    }

    if (!matriculas.value.trim()) {
        notifications.show('Digite as matrículas dos associados', 'warning');
        return;
    }

    // Mostrar loading
    if (btnGerar) {
        btnGerar.disabled = true;
        btnGerar.innerHTML = '<i class="fas fa-cog fa-spin me-2"></i>Gerando arquivo...';
    }
    if (loading) loading.style.display = 'block';
    if (alert) alert.style.display = 'none';

    try {
        // Construir URL para download
        const params = new URLSearchParams();
        params.append('gerar', '1');
        params.append('tipo', tipoProcessamento.value);
        params.append('matriculas', matriculas.value.trim());
        if (rubrica && rubrica.value) {
            params.append('rubrica', rubrica.value);
        }

        const url = `../pages/gerar_recorrencia.php?${params.toString()}`;
        
        // Fazer download do arquivo
        const link = document.createElement('a');
        link.href = url;
        link.download = `recorrencia_${new Date().getTime()}.txt`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Mostrar sucesso
        if (alert && alertText) {
            alertText.textContent = 'Arquivo gerado com sucesso!';
            alert.className = 'alert alert-success';
            alert.style.display = 'block';
        }

        notifications.show('Arquivo TXT gerado e baixado com sucesso!', 'success');

        // ✅ RESTAURAR BOTÃO APÓS SUCESSO
        if (btnGerar) {
            btnGerar.disabled = false;
            btnGerar.innerHTML = '<i class="fas fa-file-download me-2"></i>Gerar e Baixar Arquivo TXT';
            btnGerar.classList.add('btn-success');
        }

    } catch (error) {
        console.error('Erro ao gerar arquivo:', error);
        
        if (alert && alertText) {
            alertText.textContent = 'Erro ao gerar arquivo: ' + error.message;
            alert.className = 'alert alert-danger';
            alert.style.display = 'block';
        }

        notifications.show('Erro ao gerar arquivo: ' + error.message, 'error');
        
        // ✅ RESTAURAR BOTÃO APÓS ERRO
        if (btnGerar) {
            btnGerar.disabled = false;
            btnGerar.innerHTML = '<i class="fas fa-file-download me-2"></i>Gerar e Baixar Arquivo TXT';
            btnGerar.classList.add('btn-success');
        }
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

    function limpar() {
        // Limpar campos
        const tipoProcessamento = document.getElementById('tipoProcessamentoNeo');
        const matriculas = document.getElementById('matriculasNeo');
        const rubrica = document.getElementById('rubricaNeo');
        const previewMatriculas = document.getElementById('previewMatriculasNeo');
        const previewAssociados = document.getElementById('previewAssociadosNeo');
        const alert = document.getElementById('alertNeoConsig');

        if (tipoProcessamento) tipoProcessamento.value = '';
        if (matriculas) matriculas.value = '';
        if (rubrica) rubrica.value = '0900892';
        if (previewMatriculas) previewMatriculas.style.display = 'none';
        if (previewAssociados) previewAssociados.style.display = 'none';
        if (alert) alert.style.display = 'none';

        notifications.show('Formulário limpo!', 'info');
    }

    // ===== API PÚBLICA =====
    return {
        init: init,
        toggleCampos: toggleCampos,
        validarMatriculas: validarMatriculas,
        buscarAssociados: buscarAssociados,
        gerarArquivo: gerarArquivo,
        limpar: limpar
    };

})();

// Log de inicialização
console.log('✅ NeoConsig module carregado');