/**
 * Sistema Lista de Inadimplentes - Versão Aba
 * Funciona como partial dentro do sistema de navegação financeiro
 */

window.ListaInadimplentes = (function() {
    'use strict';

    let notifications;
    let isInitialized = false;
    let dadosInadimplentes = [];
    let dadosOriginais = [];
    let associadoAtual = null;

    // Configuração de APIs
    const API_PATHS = {
        buscarInadimplentes: '../api/financeiro/buscar_inadimplentes.php',
        buscarDadosCompletos: '../api/associados/buscar_dados_completos.php',
        listarObservacoes: '../api/observacoes/listar.php',
        criarObservacao: '../api/observacoes/criar.php'
    };

    // ===== SISTEMA DE NOTIFICAÇÕES =====
    class NotificationSystemInadim {
        constructor() {
            // Usar o container existente ou criar um novo
            this.container = document.getElementById('toastContainer') || document.getElementById('toastContainerInadim');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainerInadim';
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
            console.log('ListaInadimplentes já foi inicializado');
            return;
        }

        console.log('🚀 Inicializando ListaInadimplentes...', config);
        
        // Inicializar sistema de notificações
        notifications = new NotificationSystemInadim();

        // Configurar event listeners
        setupEventListeners();

        // Carregar dados
        carregarInadimplentes();

        isInitialized = true;
        notifications.show('Lista de inadimplentes carregada!', 'info', 3000);
        
        console.log('✅ ListaInadimplentes inicializado com sucesso');
    }

    function setupEventListeners() {
        // Event listeners para Enter nos campos de filtro
        ['filtroNomeInadim', 'filtroRGInadim'].forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        aplicarFiltros(e);
                    }
                });
            }
        });
    }

    // Carregar lista de inadimplentes
    async function carregarInadimplentes() {
        const loadingElement = document.getElementById('loadingInadimplentesInadim');
        const tabelaElement = document.getElementById('tabelaInadimplentesInadim');

        if (loadingElement) loadingElement.style.display = 'flex';

        try {
            console.log('🔍 Buscando inadimplentes em:', API_PATHS.buscarInadimplentes);
            const response = await fetch(API_PATHS.buscarInadimplentes);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();

            if (result.status === 'success') {
                dadosInadimplentes = result.data;
                dadosOriginais = [...dadosInadimplentes];
                exibirInadimplentes(dadosInadimplentes);
                atualizarEstatisticas(dadosInadimplentes);
                console.log(`✅ ${dadosInadimplentes.length} inadimplentes carregados`);
            } else {
                throw new Error(result.message || 'Erro ao carregar inadimplentes');
            }

        } catch (error) {
            console.error('❌ Erro ao carregar inadimplentes:', error);
            if (tabelaElement) {
                tabelaElement.innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Erro ao carregar dados: ${error.message}
                        </td>
                    </tr>
                `;
            }
            notifications.show('Erro ao carregar lista de inadimplentes', 'error');
        } finally {
            if (loadingElement) loadingElement.style.display = 'none';
        }
    }

    // Atualizar estatísticas
    function atualizarEstatisticas(dados) {
        const totalInadimplentes = dados.length;
        const totalAssociados = 1000; // Mock - seria buscado da API
        const percentual = totalAssociados > 0 ? (totalInadimplentes / totalAssociados) * 100 : 0;

        const totalElement = document.getElementById('totalInadimplentesInadim');
        const percentualElement = document.getElementById('percentualInadimplenciaInadim');
        const totalAssocElement = document.getElementById('totalAssociadosInadim');

        if (totalElement) totalElement.textContent = totalInadimplentes.toLocaleString('pt-BR');
        if (percentualElement) percentualElement.textContent = `${percentual.toFixed(1)}%`;
        if (totalAssocElement) totalAssocElement.textContent = totalAssociados.toLocaleString('pt-BR');
    }

    // Exibir inadimplentes na tabela
    function exibirInadimplentes(dados) {
        const tabela = document.getElementById('tabelaInadimplentesInadim');
        if (!tabela) return;

        if (!dados || dados.length === 0) {
            tabela.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-muted">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum inadimplente encontrado
                    </td>
                </tr>
            `;
            return;
        }

        tabela.innerHTML = dados.map(associado => `
            <tr>
                <td><strong>${associado.id}</strong></td>
                <td>
                    <div class="fw-bold">${associado.nome}</div>
                    <small class="text-muted">${associado.email || 'Email não informado'}</small>
                </td>
                <td><code>${associado.rg || '-'}</code></td>
                <td><code>${formatarCPF(associado.cpf)}</code></td>
                <td>
                    ${associado.telefone ? 
                        `<a href="tel:${associado.telefone}" class="text-decoration-none">
                            ${formatarTelefone(associado.telefone)}
                        </a>` : '-'
                    }
                </td>
                <td>${formatarData(associado.nasc)}</td>
                <td>
                    <span class="badge bg-secondary">${associado.vinculoServidor || 'N/A'}</span>
                </td>
                <td>
                    <span class="badge bg-danger">INADIMPLENTE</span>
                </td>
                <td>
                    <div class="btn-group-sm">
                        <button class="btn btn-primary btn-sm" onclick="ListaInadimplentes.verDetalhes(${associado.id})" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="ListaInadimplentes.enviarCobranca(${associado.id})" title="Enviar cobrança">
                            <i class="fas fa-envelope"></i>
                        </button>
                        <button class="btn btn-success btn-sm" onclick="ListaInadimplentes.registrarPagamento(${associado.id})" title="Registrar pagamento">
                            <i class="fas fa-dollar-sign"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Ver detalhes do associado
    async function verDetalhes(id) {
        try {
            console.log('👀 Abrindo detalhes do associado ID:', id);

            const modalElement = document.getElementById('modalDetalhesInadimplenteLista');
            if (!modalElement) {
                throw new Error('Modal não encontrado no DOM');
            }

            // Resetar modal
            resetarModal();

            // Abrir modal
            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            // Buscar dados
            const apiUrl = `${API_PATHS.buscarDadosCompletos}?id=${id}`;
            console.log('📡 Chamando API:', apiUrl);
            
            const response = await fetch(apiUrl);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            console.log('✅ Dados recebidos da API:', result);

            if (result.status === 'success') {
                associadoAtual = result.data;
                preencherModal(associadoAtual);

                // Esconder loading e mostrar conteúdo
                const modalLoading = document.getElementById('modalLoadingInadim');
                const modalContent = document.getElementById('modalContentInadim');
                if (modalLoading) modalLoading.style.display = 'none';
                if (modalContent) modalContent.style.display = 'block';
            } else {
                throw new Error(result.message || 'Erro ao carregar dados');
            }

        } catch (error) {
            console.error('❌ Erro ao carregar detalhes:', error);
            notifications.show('Erro ao carregar detalhes do associado', 'error');

            const modalLoading = document.getElementById('modalLoadingInadim');
            if (modalLoading) {
                modalLoading.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Erro ao carregar dados: ${error.message}
                    </div>
                `;
            }
        }
    }

    // Preencher modal com dados
    function preencherModal(dados) {
        console.log('📝 Preenchendo modal com dados:', dados);

        const dadosPessoais = dados.dados_pessoais || {};
        const endereco = dados.endereco || {};
        const dadosMilitares = dados.dados_militares || {};
        const dadosFinanceiros = dados.dados_financeiros || {};

        // Função auxiliar para definir conteúdo
        function setContent(id, value, defaultValue = '-') {
            const element = document.getElementById(id);
            if (element) {
                if (typeof value === 'string' && value.includes('<')) {
                    element.innerHTML = value;
                } else {
                    element.textContent = value || defaultValue;
                }
            }
        }

        // Título do modal
        const modalSubtitle = document.getElementById('modalSubtitleInadim');
        if (modalSubtitle) {
            modalSubtitle.innerHTML = `<strong>${dadosPessoais.nome || 'Sem nome'}</strong> - ID: ${dadosPessoais.id || '0'}`;
        }

        // Dados pessoais
        setContent('detalheNomeInadim', dadosPessoais.nome);
        setContent('detalheCPFInadim', formatarCPF(dadosPessoais.cpf));
        setContent('detalheRGInadim', dadosPessoais.rg);
        setContent('detalheNascimentoInadim', formatarData(dadosPessoais.nasc));
        setContent('detalheSexoInadim', dadosPessoais.sexo === 'M' ? 'Masculino' : dadosPessoais.sexo === 'F' ? 'Feminino' : '-');
        setContent('detalheEstadoCivilInadim', dadosPessoais.estadoCivil);

        // Contato
        setContent('detalheTelefoneInadim', dadosPessoais.telefone ? 
            `<a href="tel:${dadosPessoais.telefone}" class="text-decoration-none">
                <i class="fas fa-phone me-1"></i>${formatarTelefone(dadosPessoais.telefone)}
            </a>` : '-'
        );

        setContent('detalheEmailInadim', dadosPessoais.email ?
            `<a href="mailto:${dadosPessoais.email}" class="text-decoration-none">
                <i class="fas fa-envelope me-1"></i>${dadosPessoais.email}
            </a>` : '-'
        );

        // Endereço
        setContent('detalheCEPInadim', endereco.cep ? formatarCEP(endereco.cep) : '-');
        setContent('detalheEnderecoInadim', endereco.endereco);
        setContent('detalheCidadeInadim', endereco.cidade);

        // Dados financeiros
        setContent('detalheVinculoInadim', dadosFinanceiros.vinculoServidor);
        
        // Dados militares
        setContent('detalheCorporacaoInadim', dadosMilitares.corporacao);
        setContent('detalhePatenteInadim', dadosMilitares.patente);
        setContent('detalheLotacaoInadim', dadosMilitares.lotacao);
        setContent('detalheUnidadeInadim', dadosMilitares.unidade);

        // Valores de débito (simulados)
        const valorMensal = 86.55;
        const mesesAtraso = 3;
        const valorTotal = valorMensal * mesesAtraso;

        setContent('valorTotalDebitoInadim', `R$ ${valorTotal.toFixed(2).replace('.', ',')}`);
        setContent('mesesAtrasoInadim', mesesAtraso);
        setContent('ultimaContribuicaoInadim', 'Há 3 meses');

        // Carregar observações
        if (dadosPessoais && dadosPessoais.id) {
            carregarObservacoes(dadosPessoais.id);
        }
    }

    // Carregar observações
    async function carregarObservacoes(associadoId) {
        const listaObservacoes = document.getElementById('listaObservacoesInadim');
        
        if (listaObservacoes) {
            listaObservacoes.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Carregando observações...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0">Carregando observações...</p>
                </div>
            `;
        }

        try {
            const apiUrl = `${API_PATHS.listarObservacoes}?associado_id=${associadoId}`;
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.status === 'success') {
                exibirObservacoes(result.data, result.estatisticas);
            } else {
                throw new Error(result.message || 'Erro ao buscar observações');
            }
            
        } catch (error) {
            console.error('❌ Erro ao carregar observações:', error);
            if (listaObservacoes) {
                listaObservacoes.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Não foi possível carregar as observações.
                    </div>
                `;
            }
        }
    }

    // Exibir observações
    function exibirObservacoes(observacoes, estatisticas) {
        const listaObservacoes = document.getElementById('listaObservacoesInadim');
        const badgeObservacoes = document.getElementById('badgeObservacoesInadim');
        
        if (!listaObservacoes) return;
        
        // Atualizar badge
        if (badgeObservacoes && estatisticas) {
            const total = estatisticas.total || observacoes.length || 0;
            if (total > 0) {
                badgeObservacoes.textContent = total;
                badgeObservacoes.style.display = 'inline-block';
            } else {
                badgeObservacoes.style.display = 'none';
            }
        }
        
        // Se não houver observações
        if (!observacoes || observacoes.length === 0) {
            listaObservacoes.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Nenhuma observação registrada para este associado</p>
                    <button class="btn btn-sm btn-primary mt-2" onclick="ListaInadimplentes.adicionarObservacao()">
                        <i class="fas fa-plus me-1"></i>Adicionar Primeira Observação
                    </button>
                </div>
            `;
            return;
        }

        // Construir HTML das observações
        let observacoesHtml = '';
        
        observacoes.forEach(obs => {
            observacoesHtml += `
                <div class="alert alert-light border-start border-3 border-primary">
                    <div class="d-flex justify-content-between">
                        <strong><i class="fas fa-comment me-2"></i>${obs.criado_por_nome || 'Sistema'}</strong>
                        <small class="text-muted">${formatarDataHora(obs.data_criacao)}</small>
                    </div>
                    <p class="mb-0 mt-2">${escapeHtml(obs.observacao)}</p>
                </div>
            `;
        });
        
        listaObservacoes.innerHTML = observacoesHtml;
    }

    // Resetar modal
    function resetarModal() {
        const modalLoading = document.getElementById('modalLoadingInadim');
        const modalContent = document.getElementById('modalContentInadim');

        if (modalLoading) modalLoading.style.display = 'block';
        if (modalContent) modalContent.style.display = 'none';

        // Resetar para primeira tab
        const firstTab = document.querySelector('#dadosPessoais-tab-inadim');
        if (firstTab) firstTab.click();

        // Limpar badge de observações
        const badgeObs = document.getElementById('badgeObservacoesInadim');
        if (badgeObs) {
            badgeObs.style.display = 'none';
            badgeObs.textContent = '0';
        }
    }

    // Adicionar observação
    async function adicionarObservacao() {
        if (!associadoAtual) {
            notifications.show('Nenhum associado selecionado', 'error');
            return;
        }

        // Usar SweetAlert2 se disponível, senão prompt simples
        let observacao;
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Nova Observação',
                input: 'textarea',
                inputPlaceholder: 'Digite sua observação...',
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar'
            });
            
            if (result.isConfirmed && result.value) {
                observacao = result.value.trim();
            }
        } else {
            observacao = prompt('Digite sua observação:');
        }

        if (observacao) {
            notifications.show('Observação adicionada com sucesso!', 'success');
            
            // Recarregar observações
            if (associadoAtual && associadoAtual.dados_pessoais) {
                carregarObservacoes(associadoAtual.dados_pessoais.id);
            }
        }
    }

    // Aplicar filtros
    function aplicarFiltros(event) {
        event.preventDefault();

        const filtroNome = document.getElementById('filtroNomeInadim');
        const filtroRG = document.getElementById('filtroRGInadim');
        const filtroVinculo = document.getElementById('filtroVinculoInadim');

        if (!filtroNome || !filtroRG || !filtroVinculo) return;

        const nomeValue = filtroNome.value.toLowerCase().trim();
        const rgValue = filtroRG.value.trim();
        const vinculoValue = filtroVinculo.value;

        let dadosFiltrados = [...dadosOriginais];

        if (nomeValue) {
            dadosFiltrados = dadosFiltrados.filter(associado =>
                associado.nome.toLowerCase().includes(nomeValue)
            );
        }

        if (rgValue) {
            dadosFiltrados = dadosFiltrados.filter(associado =>
                associado.rg && associado.rg.includes(rgValue)
            );
        }

        if (vinculoValue) {
            dadosFiltrados = dadosFiltrados.filter(associado =>
                associado.vinculoServidor === vinculoValue
            );
        }

        dadosInadimplentes = dadosFiltrados;
        exibirInadimplentes(dadosInadimplentes);
        atualizarEstatisticas(dadosInadimplentes);

        notifications.show(`Filtro aplicado: ${dadosFiltrados.length} registros encontrados`, 'info');
    }

    // Limpar filtros
    function limparFiltros() {
        const filtroNome = document.getElementById('filtroNomeInadim');
        const filtroRG = document.getElementById('filtroRGInadim');
        const filtroVinculo = document.getElementById('filtroVinculoInadim');

        if (filtroNome) filtroNome.value = '';
        if (filtroRG) filtroRG.value = '';
        if (filtroVinculo) filtroVinculo.value = '';

        dadosInadimplentes = [...dadosOriginais];
        exibirInadimplentes(dadosInadimplentes);
        atualizarEstatisticas(dadosInadimplentes);

        notifications.show('Filtros removidos', 'info');
    }

    // Enviar cobrança
    function enviarCobranca(id) {
        const associado = dadosInadimplentes.find(a => a.id === id);
        if (!associado) {
            notifications.show('Associado não encontrado', 'error');
            return;
        }

        notifications.show(`Cobrança enviada para ${associado.nome}`, 'success');
    }

    // Registrar pagamento
    function registrarPagamento(id) {
        const associado = dadosInadimplentes.find(a => a.id === id);
        if (!associado) {
            notifications.show('Associado não encontrado', 'error');
            return;
        }

        notifications.show(`Abrindo registro de pagamento para ${associado.nome}`, 'info');
    }

    // Funções do modal
    async function enviarCobrancaModal() {
        if (!associadoAtual) return;

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Enviar Cobrança',
                html: `
                    <p>Confirma o envio de cobrança para:</p>
                    <p><strong>${associadoAtual.dados_pessoais.nome}</strong></p>
                    <p>CPF: ${formatarCPF(associadoAtual.dados_pessoais.cpf)}</p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Enviar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                notifications.show(`Cobrança enviada para ${associadoAtual.dados_pessoais.nome}`, 'success');
            }
        } else {
            if (confirm('Confirma o envio de cobrança?')) {
                notifications.show(`Cobrança enviada para ${associadoAtual.dados_pessoais.nome}`, 'success');
            }
        }
    }

    async function registrarPagamentoModal() {
        if (!associadoAtual) return;

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Registrar Pagamento',
                html: `
                    <p>Registrar pagamento de:</p>
                    <p><strong>${associadoAtual.dados_pessoais.nome}</strong></p>
                `,
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                notifications.show('Pagamento registrado com sucesso!', 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalDetalhesInadimplenteLista'));
                if (modal) modal.hide();
                carregarInadimplentes();
            }
        } else {
            if (confirm('Confirma o registro de pagamento?')) {
                notifications.show('Pagamento registrado com sucesso!', 'success');
                carregarInadimplentes();
            }
        }
    }

    // Funções de exportação
    function exportarExcel() {
        notifications.show('Gerando arquivo Excel...', 'info');
    }

    function exportarPDF() {
        notifications.show('Gerando arquivo PDF...', 'info');
    }

    function imprimirRelatorio() {
        window.print();
    }

    // ===== FUNÇÕES AUXILIARES =====
    function formatarCPF(cpf) {
        if (!cpf) return '';
        cpf = cpf.toString().replace(/\D/g, '');
        if (cpf.length === 11) {
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }
        return cpf;
    }

    function formatarCEP(cep) {
        if (!cep) return '';
        cep = cep.toString().replace(/\D/g, '');
        if (cep.length === 8) {
            return cep.replace(/(\d{5})(\d{3})/, "$1-$2");
        }
        return cep;
    }

    function formatarTelefone(telefone) {
        if (!telefone) return '';
        telefone = telefone.toString().replace(/\D/g, '');
        if (telefone.length === 11) {
            return telefone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
        } else if (telefone.length === 10) {
            return telefone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
        }
        return telefone;
    }

    function formatarData(data) {
        if (!data) return '';
        try {
            const dataObj = new Date(data + 'T00:00:00');
            return dataObj.toLocaleDateString('pt-BR');
        } catch (e) {
            return data;
        }
    }

    function formatarDataHora(dataString) {
        if (!dataString) return '';
        try {
            const data = new Date(dataString);
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dataString;
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // ===== API PÚBLICA =====
    return {
        init: init,
        verDetalhes: verDetalhes,
        enviarCobranca: enviarCobranca,
        registrarPagamento: registrarPagamento,
        aplicarFiltros: aplicarFiltros,
        limparFiltros: limparFiltros,
        adicionarObservacao: adicionarObservacao,
        enviarCobrancaModal: enviarCobrancaModal,
        registrarPagamentoModal: registrarPagamentoModal,
        exportarExcel: exportarExcel,
        exportarPDF: exportarPDF,
        imprimirRelatorio: imprimirRelatorio
    };

})();

// Log de inicialização
console.log('✅ ListaInadimplentes module carregado');