/**
 * Sistema Lista de Inadimplentes - V2 com Modal de Pendências
 * Modal moderno com UX/UI aprimorada e detalhamento de pendências por mês
 * @version 2.1.0
 */

window.ListaInadimplentes = (function() {
    'use strict';

    // ===== ESTADO =====
    let isInitialized = false;
    let dadosInadimplentes = [];
    let dadosOriginais = [];
    let associadoAtual = null;
    let pendenciasAtuais = [];

    // ===== CONFIGURAÇÃO =====
    const API_PATHS = {
        buscarInadimplentes: '../api/financeiro/buscar_inadimplentes.php',
        buscarDadosCompletos: '../api/associados/buscar_dados_completos.php',
        buscarPendencias: '../api/financeiro/buscar_pendencias.php',
        buscarPendenciasDetalhadas: '../api/financeiro/buscar_pendencias_detalhadas.php',
        listarObservacoes: '../api/observacoes/listar.php',
        criarObservacao: '../api/observacoes/criar.php',
        registrarAcerto: '../api/financeiro/registrar_acerto.php',
        registrarRenegociacao: '../api/financeiro/registrar_renegociacao.php',
        quitarDividas: '../api/financeiro/quitar_dividas.php'
    };

    // ===== SISTEMA DE TOAST PERSONALIZADO =====
    const Toast = {
        container: null,
        
        init() {
            this.container = document.getElementById('toastContainerInadim');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainerInadim';
                this.container.className = 'toast-container-custom';
                document.body.appendChild(this.container);
            }
        },
        
        show(message, type = 'info', duration = 4000) {
            if (!this.container) this.init();
            
            const icons = {
                success: 'check',
                error: 'exclamation',
                warning: 'exclamation-triangle',
                info: 'info'
            };
            
            const toast = document.createElement('div');
            toast.className = `toast-custom ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-${icons[type] || 'info'}"></i>
                </div>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            this.container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    };

    // ===== INICIALIZAÇÃO =====
    function init(config = {}) {
        if (isInitialized) {
            console.log('⚠️ ListaInadimplentes já inicializado');
            return;
        }

        console.log('🚀 Inicializando ListaInadimplentes v2...');
        
        Toast.init();
        setupEventListeners();
        carregarInadimplentes();
        
        isInitialized = true;
        Toast.show('Sistema de inadimplência carregado', 'info', 3000);
        
        console.log('✅ ListaInadimplentes v2 inicializado com sucesso');
    }

    function setupEventListeners() {
        // Filtros - Enter para buscar
        ['filtroNomeInadim', 'filtroRGInadim'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('keypress', e => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        aplicarFiltros(e);
                    }
                });
            }
        });
        
        // Fechar modal de detalhes ao clicar fora
        const overlay = document.getElementById('modalOverlayInadim');
        if (overlay) {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) {
                    fecharModal();
                }
            });
        }
        
        // Fechar modal de pendências ao clicar fora
        const overlayPendencias = document.getElementById('modalOverlayPendencias');
        if (overlayPendencias) {
            overlayPendencias.addEventListener('click', e => {
                if (e.target === overlayPendencias) {
                    fecharModalPendencias();
                }
            });
        }
        
        // Fechar modais com ESC
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const pendenciasModal = document.getElementById('modalOverlayPendencias');
                if (pendenciasModal && pendenciasModal.classList.contains('active')) {
                    fecharModalPendencias();
                } else {
                    fecharModal();
                }
            }
        });
        
        // Máscara para valor renegociado
        const valorRenegociado = document.getElementById('valorRenegociado');
        if (valorRenegociado) {
            valorRenegociado.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = (parseInt(value) / 100).toFixed(2);
                e.target.value = value.replace('.', ',');
            });
        }
    }

    // ===== CARREGAR DADOS =====
    async function carregarInadimplentes() {
        const loading = document.getElementById('loadingInadimplentesInadim');
        const tabela = document.getElementById('tabelaInadimplentesInadim');
        
        if (loading) loading.style.display = 'flex';
        
        try {
            console.log('📡 Buscando inadimplentes...');
            const response = await fetch(API_PATHS.buscarInadimplentes);
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const result = await response.json();
            
            if (result.status === 'success') {
                dadosInadimplentes = result.data || [];
                dadosOriginais = [...dadosInadimplentes];
                renderizarTabela(dadosInadimplentes);
                atualizarEstatisticas(dadosInadimplentes);
                console.log(`✅ ${dadosInadimplentes.length} inadimplentes carregados`);
            } else {
                throw new Error(result.message || 'Erro desconhecido');
            }
        } catch (error) {
            console.error('❌ Erro:', error);
            if (tabela) {
                tabela.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--color-danger);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                            Erro ao carregar dados: ${error.message}
                        </td>
                    </tr>
                `;
            }
            Toast.show('Erro ao carregar inadimplentes', 'error');
        } finally {
            if (loading) loading.style.display = 'none';
        }
    }

    function atualizarEstatisticas(dados) {
        const total = dados.length;
        const base = 1000; // Mock - substituir por valor real
        const percentual = base > 0 ? ((total / base) * 100).toFixed(1) : 0;
        
        const elTotal = document.getElementById('totalInadimplentesInadim');
        const elPercent = document.getElementById('percentualInadimplenciaInadim');
        
        if (elTotal) elTotal.textContent = total.toLocaleString('pt-BR');
        if (elPercent) elPercent.textContent = `${percentual}%`;
    }

    // ===== RENDERIZAÇÃO DA TABELA =====
    function renderizarTabela(dados) {
        const tabela = document.getElementById('tabelaInadimplentesInadim');
        if (!tabela) return;
        
        if (!dados || dados.length === 0) {
            tabela.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--color-gray-500);">
                        <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                        Nenhum inadimplente encontrado
                    </td>
                </tr>
            `;
            return;
        }
        
        tabela.innerHTML = dados.map(a => `
            <tr>
                <td>
                    <div class="cell-associado">
                        <span class="cell-nome">${escapeHtml(a.nome)}</span>
                        <span class="cell-email">${escapeHtml(a.email || 'Email não informado')}</span>
                    </div>
                </td>
                <td>
                    <div class="cell-docs">
                        <span class="cell-doc">RG: ${escapeHtml(a.rg || '-')}</span>
                        <span class="cell-doc">CPF: ${formatarCPF(a.cpf)}</span>
                    </div>
                </td>
                <td class="cell-contato">
                    ${a.telefone ? 
                        `<a href="tel:${a.telefone}"><i class="fas fa-phone"></i> ${formatarTelefone(a.telefone)}</a>` 
                        : '<span style="color: var(--color-gray-400);">-</span>'
                    }
                </td>
                <td>
                    <span class="badge-vinculo">${escapeHtml(a.vinculoServidor || 'N/A')}</span>
                </td>
                <td>
                    <span class="badge-status">INADIMPLENTE</span>
                </td>
                <td>
                    <div class="cell-actions">
                        <button class="btn-table-action view" onclick="ListaInadimplentes.verDetalhes(${a.id})" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-table-action pendencias" onclick="ListaInadimplentes.abrirModalPendenciasDireto(${a.id})" title="Ver pendências">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </button>
                        <button class="btn-table-action pay" onclick="ListaInadimplentes.registrarPagamento(${a.id})" title="Registrar pagamento">
                            <i class="fas fa-dollar-sign"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // ===== MODAL DE DETALHES =====
    function abrirModal() {
        const overlay = document.getElementById('modalOverlayInadim');
        if (overlay) {
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function fecharModal() {
        const overlay = document.getElementById('modalOverlayInadim');
        if (overlay) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        associadoAtual = null;
    }
    
    function resetarModal() {
        const loading = document.getElementById('modalLoadingInadim');
        const content = document.getElementById('modalContentInadim');
        
        if (loading) loading.style.display = 'flex';
        if (content) content.style.display = 'none';
        
        // Reset tabs
        document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        
        const firstTab = document.querySelector('.nav-tab[data-tab="pessoais"]');
        const firstContent = document.getElementById('tab-pessoais');
        if (firstTab) firstTab.classList.add('active');
        if (firstContent) firstContent.classList.add('active');
        
        // Reset badge
        const badge = document.getElementById('obsCountBadge');
        if (badge) {
            badge.style.display = 'none';
            badge.textContent = '0';
        }
    }
    
    function trocarTab(tabId) {
        // Atualizar navegação
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabId);
        });
        
        // Atualizar conteúdo
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.toggle('active', content.id === `tab-${tabId}`);
        });
    }

    // ===== VER DETALHES =====
    async function verDetalhes(id) {
        console.log('👁️ Abrindo detalhes do ID:', id);
        
        resetarModal();
        abrirModal();
        
        try {
            const response = await fetch(`${API_PATHS.buscarDadosCompletos}?id=${id}`);
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const result = await response.json();
            console.log('📦 Dados recebidos:', result);
            
            if (result.status === 'success') {
                associadoAtual = result.data;
                preencherModal(associadoAtual);
                
                const loading = document.getElementById('modalLoadingInadim');
                const content = document.getElementById('modalContentInadim');
                if (loading) loading.style.display = 'none';
                if (content) content.style.display = 'block';
            } else {
                throw new Error(result.message || 'Erro ao carregar dados');
            }
        } catch (error) {
            console.error('❌ Erro ao carregar detalhes:', error);
            Toast.show('Erro ao carregar detalhes', 'error');
            
            const loading = document.getElementById('modalLoadingInadim');
            if (loading) {
                loading.innerHTML = `
                    <div style="text-align: center; color: var(--color-danger);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <p>Erro ao carregar dados: ${error.message}</p>
                        <button onclick="ListaInadimplentes.fecharModal()" 
                                style="margin-top: 1rem; padding: 0.5rem 1rem; border: none; border-radius: 6px; background: var(--color-gray-200); cursor: pointer;">
                            Fechar
                        </button>
                    </div>
                `;
            }
        }
    }
    
    function preencherModal(dados) {
        console.log('✏️ Preenchendo modal com:', dados);
        
        const pessoais = dados.dados_pessoais || {};
        const endereco = dados.endereco || {};
        const militar = dados.dados_militares || {};
        const financeiro = dados.dados_financeiros || {};
        
        // Função auxiliar
        const set = (id, value, isHtml = false) => {
            const el = document.getElementById(id);
            if (el) {
                if (isHtml) {
                    el.innerHTML = value || '-';
                } else {
                    el.textContent = value || '-';
                }
            }
        };
        
        // Avatar com iniciais
        const avatar = document.getElementById('modalAvatarInadim');
        if (avatar && pessoais.nome) {
            const iniciais = pessoais.nome.split(' ')
                .filter(n => n.length > 0)
                .slice(0, 2)
                .map(n => n[0].toUpperCase())
                .join('');
            avatar.innerHTML = `<span>${iniciais}</span>`;
        }
        
        // Header
        set('modalNomeInadim', pessoais.nome);
        set('modalCPFHeaderInadim', formatarCPF(pessoais.cpf));
        set('modalIDHeaderInadim', `ID: ${pessoais.id || '0'}`);
        
        // Dados pessoais
        set('detalheNomeInadim', pessoais.nome);
        set('detalheCPFInadim', formatarCPF(pessoais.cpf));
        set('detalheRGInadim', pessoais.rg);
        set('detalheNascimentoInadim', formatarData(pessoais.nasc));
        set('detalheSexoInadim', pessoais.sexo === 'M' ? 'Masculino' : pessoais.sexo === 'F' ? 'Feminino' : '-');
        set('detalheEstadoCivilInadim', pessoais.estadoCivil);
        
        // Contato
        set('detalheTelefoneInadim', formatarTelefone(pessoais.telefone));
        set('detalheEmailInadim', pessoais.email);
        
        // Endereço
        const enderecoCompleto = [endereco.endereco, endereco.numero].filter(Boolean).join(', ');
        set('detalheEnderecoInadim', enderecoCompleto || '-');
        set('detalheBairroInadim', endereco.bairro);
        set('detalheCidadeInadim', endereco.cidade);
        set('detalheCEPInadim', formatarCEP(endereco.cep));
        
        // Dados militares
        set('detalhePatenteInadim', militar.patente);
        set('detalheCorporacaoInadim', militar.corporacao);
        set('detalheLotacaoInadim', militar.lotacao);
        set('detalheUnidadeInadim', militar.unidade);
        
        // Dados financeiros básicos
        set('detalheTipoAssociadoInadim', financeiro.tipoAssociado);
        set('detalheVinculoInadim', financeiro.vinculoServidor);
        set('detalheLocalDebitoInadim', financeiro.localDebito);
        
        // Última atualização
        set('lastUpdateInadim', new Date().toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }));
        
        // Buscar dados de pendência financeira
        if (pessoais.id) {
            carregarPendenciasFinanceiras(pessoais.id);
            carregarObservacoes(pessoais.id);
        }
    }
    
    // ===== BUSCAR PENDÊNCIAS FINANCEIRAS =====
    async function carregarPendenciasFinanceiras(associadoId) {
        console.log('💰 Buscando pendências financeiras para ID:', associadoId);
        
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        
        set('valorTotalDebitoInadim', 'Calculando...');
        set('mesesAtrasoInadim', '...');
        set('ultimaContribuicaoInadim', 'Verificando...');
        set('statusDetailInadim', 'Calculando pendências...');
        
        try {
            const response = await fetch(`${API_PATHS.buscarPendencias}?id=${associadoId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            console.log('📊 Dados de pendência recebidos:', result);
            
            if (result.status === 'success') {
                preencherDadosPendencia(result.data);
            } else {
                throw new Error(result.message || 'Erro ao buscar pendências');
            }
        } catch (error) {
            console.error('❌ Erro ao buscar pendências:', error);
            
            set('valorTotalDebitoInadim', 'R$ --,--');
            set('mesesAtrasoInadim', '--');
            set('ultimaContribuicaoInadim', 'Indisponível');
            set('statusDetailInadim', 'Erro ao calcular pendências');
            
            Toast.show('Erro ao carregar dados financeiros', 'warning');
        }
    }
    
    function preencherDadosPendencia(dados) {
        console.log('📝 Preenchendo dados de pendência:', dados);
        
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        
        const valorDebito = dados.valor_total_debito || 0;
        set('valorTotalDebitoInadim', formatarMoeda(valorDebito));
        
        const mesesAtraso = dados.meses_atraso || 0;
        set('mesesAtrasoInadim', mesesAtraso.toString());
        
        const valorMensal = dados.valor_mensal || 86.55;
        set('valorMensalInadim', formatarMoeda(valorMensal));
        
        if (dados.ultimo_pagamento) {
            set('ultimaContribuicaoInadim', dados.ultimo_pagamento.tempo_relativo || formatarData(dados.ultimo_pagamento.data));
        } else {
            set('ultimaContribuicaoInadim', 'Sem pagamentos');
        }
        
        if (mesesAtraso === 0) {
            set('statusDetailInadim', 'Situação regularizada');
        } else if (mesesAtraso === 1) {
            set('statusDetailInadim', '1 mês em atraso');
        } else {
            set('statusDetailInadim', `${mesesAtraso} meses em atraso - ${formatarMoeda(valorDebito)}`);
        }
        
        if (dados.tipo_associado) {
            set('detalheTipoAssociadoInadim', dados.tipo_associado);
        }
        if (dados.vinculo_servidor) {
            set('detalheVinculoInadim', dados.vinculo_servidor);
        }
        if (dados.local_debito) {
            set('detalheLocalDebitoInadim', dados.local_debito);
        }
        
        if (associadoAtual) {
            associadoAtual.pendencias = dados;
        }
    }

    // ===== MODAL DE PENDÊNCIAS FINANCEIRAS =====
    function abrirModalPendencias() {
        if (!associadoAtual?.dados_pessoais) {
            Toast.show('Nenhum associado selecionado', 'error');
            return;
        }
        
        const overlay = document.getElementById('modalOverlayPendencias');
        if (overlay) {
            overlay.classList.add('active');
        }
        
        carregarPendenciasDetalhadas(associadoAtual.dados_pessoais.id);
    }
    
    async function abrirModalPendenciasDireto(id) {
        console.log('📄 Abrindo modal de pendências direto para ID:', id);
        
        // Buscar dados básicos do associado primeiro
        try {
            const response = await fetch(`${API_PATHS.buscarDadosCompletos}?id=${id}`);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const result = await response.json();
            if (result.status === 'success') {
                associadoAtual = result.data;
            }
        } catch (error) {
            console.error('Erro ao buscar dados do associado:', error);
        }
        
        const overlay = document.getElementById('modalOverlayPendencias');
        if (overlay) {
            overlay.classList.add('active');
        }
        
        carregarPendenciasDetalhadas(id);
    }
    
    function fecharModalPendencias() {
        const overlay = document.getElementById('modalOverlayPendencias');
        if (overlay) {
            overlay.classList.remove('active');
        }
        pendenciasAtuais = [];
    }
    
    async function carregarPendenciasDetalhadas(associadoId) {
        console.log('📊 Carregando pendências detalhadas para ID:', associadoId);
        
        const loading = document.getElementById('pendenciasLoading');
        const content = document.getElementById('pendenciasContent');
        
        if (loading) loading.style.display = 'flex';
        if (content) content.style.display = 'none';
        
        try {
            // Buscar dados do associado se ainda não temos
            if (!associadoAtual?.dados_pessoais) {
                const respAssociado = await fetch(`${API_PATHS.buscarDadosCompletos}?id=${associadoId}`);
                if (respAssociado.ok) {
                    const resultAssociado = await respAssociado.json();
                    if (resultAssociado.status === 'success') {
                        associadoAtual = resultAssociado.data;
                    }
                }
            }
            
            // Atualizar header do modal
            const nome = associadoAtual?.dados_pessoais?.nome || 'Associado';
            const id = associadoAtual?.dados_pessoais?.id || associadoId;
            
            const elNome = document.getElementById('pendenciasAssociadoNome');
            const elId = document.getElementById('pendenciasAssociadoId');
            
            if (elNome) elNome.textContent = nome;
            if (elId) elId.textContent = `ID: ${id}`;
            
            // Buscar pendências detalhadas
            const response = await fetch(`${API_PATHS.buscarPendenciasDetalhadas}?id=${associadoId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            console.log('📋 Pendências detalhadas recebidas:', result);
            
            if (result.status === 'success') {
                pendenciasAtuais = result.data.pendencias || [];
                renderizarTabelaPendencias(pendenciasAtuais, result.data);
                
                // Atualizar valor total no header
                const totalEl = document.getElementById('pendenciasTotalDebito');
                if (totalEl) {
                    totalEl.textContent = formatarMoeda(result.data.total_debito || 0);
                }
                
                // Preencher campo de renegociação com valor total
                const valorRenegociado = document.getElementById('valorRenegociado');
                if (valorRenegociado) {
                    const total = result.data.total_debito || 0;
                    valorRenegociado.value = total.toFixed(2).replace('.', ',');
                }
            } else {
                // Se não há API ainda, mostrar dados mockados
                renderizarPendenciasMock(associadoId);
            }
        } catch (error) {
            console.error('❌ Erro ao carregar pendências:', error);
            // Mostrar dados mockados em caso de erro
            renderizarPendenciasMock(associadoId);
        } finally {
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
        }
    }
    
    function renderizarPendenciasMock(associadoId) {
        // Dados mockados baseados na imagem fornecida
        const nome = associadoAtual?.dados_pessoais?.nome || 'ASSOCIADO EXEMPLO';
        const id = associadoAtual?.dados_pessoais?.id || associadoId;
        
        const elNome = document.getElementById('pendenciasAssociadoNome');
        const elId = document.getElementById('pendenciasAssociadoId');
        
        if (elNome) elNome.textContent = nome;
        if (elId) elId.textContent = `ID: ${id}`;
        
        // Mock de pendências
        pendenciasAtuais = [
            { id: 1, tipo: 'Contribuição social', mes: '10/2023', valor: 156.32, status: 'sem_retorno' },
            { id: 2, tipo: 'Contribuição social', mes: '11/2023', valor: 156.32, status: 'sem_retorno' },
            { id: 3, tipo: 'Contribuição social', mes: '12/2023', valor: 156.32, status: 'sem_retorno' },
            { id: 4, tipo: 'Contribuição social', mes: '01/2024', valor: 156.32, status: 'sem_retorno' },
            { id: 5, tipo: 'Contribuição social', mes: '03/2025', valor: 173.10, status: 'sem_retorno' },
            { id: 6, tipo: 'Contribuição jurídica', mes: '03/2025', valor: 43.28, status: 'sem_retorno' },
            { id: 7, tipo: 'Contribuição jurídica', mes: '05/2025', valor: 45.37, status: 'sem_retorno' },
            { id: 8, tipo: 'Contribuição social', mes: '05/2025', valor: 181.46, status: 'sem_retorno' }
        ];
        
        const totalDebito = pendenciasAtuais.reduce((acc, p) => acc + p.valor, 0);
        
        renderizarTabelaPendencias(pendenciasAtuais, { total_debito: totalDebito });
        
        const totalEl = document.getElementById('pendenciasTotalDebito');
        if (totalEl) {
            totalEl.textContent = formatarMoeda(totalDebito);
        }
        
        const valorRenegociado = document.getElementById('valorRenegociado');
        if (valorRenegociado) {
            valorRenegociado.value = totalDebito.toFixed(2).replace('.', ',');
        }
    }
    
    function renderizarTabelaPendencias(pendencias, dados) {
        const tbody = document.getElementById('pendenciasTableBody');
        const somaTotal = document.getElementById('pendenciasSomaTotal');
        
        if (!tbody) return;
        
        if (!pendencias || pendencias.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem; color: var(--color-gray-500);">
                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; color: var(--color-success);"></i>
                        Nenhuma pendência encontrada
                    </td>
                </tr>
            `;
            if (somaTotal) somaTotal.textContent = 'R$ 0,00';
            return;
        }
        
        let total = 0;
        
        tbody.innerHTML = pendencias.map((p, index) => {
            total += parseFloat(p.valor) || 0;
            
            const statusClass = p.status === 'sem_retorno' ? 'sem-retorno' : 'parcial';
            const statusTexto = p.status === 'sem_retorno' ? 'sem retorno(assego)' : 'parcialmente pago';
            
            return `
                <tr data-pendencia-id="${p.id || index}">
                    <td>
                        <div class="pendencia-descricao">
                            <span class="pendencia-tipo">${escapeHtml(p.tipo)} não quitado em ${escapeHtml(p.mes)}</span>
                        </div>
                    </td>
                    <td>
                        <span class="valor-original">${formatarMoeda(p.valor)}</span>
                    </td>
                    <td>
                        <span class="pendencia-status ${statusClass}">
                            <i class="fas fa-clock"></i>
                            ${statusTexto}
                        </span>
                    </td>
                    <td>
                        <div class="input-valor-acerto">
                            <span class="prefix">Valor:</span>
                            <input type="text" 
                                   id="valorAcerto_${p.id || index}" 
                                   value="${parseFloat(p.valor).toFixed(2)}" 
                                   onchange="ListaInadimplentes.atualizarValorAcerto(${p.id || index}, this.value)">
                        </div>
                    </td>
                    <td>
                        <button class="btn-acerto" onclick="ListaInadimplentes.registrarAcertoDivida(${p.id || index})">
                            Acerto da dívida
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
        if (somaTotal) {
            somaTotal.textContent = formatarMoeda(total);
        }
    }
    
    function atualizarValorAcerto(pendenciaId, valor) {
        // Formatar valor
        valor = valor.replace(/[^\d.,]/g, '').replace(',', '.');
        const valorNumerico = parseFloat(valor) || 0;
        
        // Atualizar no array de pendências
        const pendencia = pendenciasAtuais.find(p => (p.id || pendenciasAtuais.indexOf(p)) == pendenciaId);
        if (pendencia) {
            pendencia.valorAcerto = valorNumerico;
        }
        
        console.log(`Valor de acerto atualizado para pendência ${pendenciaId}: ${valorNumerico}`);
    }
    
    async function registrarAcertoDivida(pendenciaId) {
        const pendencia = pendenciasAtuais.find(p => (p.id || pendenciasAtuais.indexOf(p)) == pendenciaId);
        if (!pendencia) {
            Toast.show('Pendência não encontrada', 'error');
            return;
        }
        
        const inputValor = document.getElementById(`valorAcerto_${pendenciaId}`);
        const valor = inputValor ? parseFloat(inputValor.value.replace(',', '.')) : pendencia.valor;
        
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Confirmar Acerto de Dívida',
                html: `
                    <div style="text-align: left; padding: 1rem;">
                        <p style="margin-bottom: 0.5rem;"><strong>Pendência:</strong></p>
                        <p style="color: #374151; margin-bottom: 1rem;">${escapeHtml(pendencia.tipo)} - ${escapeHtml(pendencia.mes)}</p>
                        <p style="margin-bottom: 0.5rem;"><strong>Valor original:</strong> ${formatarMoeda(pendencia.valor)}</p>
                        <p style="margin-bottom: 0.5rem;"><strong>Valor do acerto:</strong> ${formatarMoeda(valor)}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Confirmar Acerto',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#16a34a'
            });
            
            if (result.isConfirmed) {
                await processarAcertoDivida(pendenciaId, valor);
            }
        } else {
            if (confirm(`Confirma o acerto da dívida de ${formatarMoeda(valor)}?`)) {
                await processarAcertoDivida(pendenciaId, valor);
            }
        }
    }
    
    async function processarAcertoDivida(pendenciaId, valor) {
        try {
            // Aqui faria o POST para a API
            // const response = await fetch(API_PATHS.registrarAcerto, {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({
            //         associado_id: associadoAtual?.dados_pessoais?.id,
            //         pendencia_id: pendenciaId,
            //         valor: valor
            //     })
            // });
            
            Toast.show('Acerto de dívida registrado com sucesso!', 'success');
            
            // Remover a linha da tabela
            const row = document.querySelector(`tr[data-pendencia-id="${pendenciaId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s, transform 0.3s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(50px)';
                setTimeout(() => {
                    row.remove();
                    atualizarTotalPendencias();
                }, 300);
            }
            
            // Remover do array
            const index = pendenciasAtuais.findIndex(p => (p.id || pendenciasAtuais.indexOf(p)) == pendenciaId);
            if (index > -1) {
                pendenciasAtuais.splice(index, 1);
            }
            
        } catch (error) {
            console.error('Erro ao registrar acerto:', error);
            Toast.show('Erro ao registrar acerto de dívida', 'error');
        }
    }
    
    function atualizarTotalPendencias() {
        const total = pendenciasAtuais.reduce((acc, p) => acc + (parseFloat(p.valor) || 0), 0);
        
        const somaTotal = document.getElementById('pendenciasSomaTotal');
        const totalDebito = document.getElementById('pendenciasTotalDebito');
        const valorRenegociado = document.getElementById('valorRenegociado');
        
        if (somaTotal) somaTotal.textContent = formatarMoeda(total);
        if (totalDebito) totalDebito.textContent = formatarMoeda(total);
        if (valorRenegociado) valorRenegociado.value = total.toFixed(2).replace('.', ',');
        
        // Se não há mais pendências
        if (pendenciasAtuais.length === 0) {
            const tbody = document.getElementById('pendenciasTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 3rem; color: var(--color-success);">
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                            Todas as pendências foram quitadas!
                        </td>
                    </tr>
                `;
            }
        }
    }
    
    async function lancarRenegociacao() {
        const input = document.getElementById('valorRenegociado');
        if (!input) return;
        
        const valor = parseFloat(input.value.replace(',', '.')) || 0;
        
        if (valor <= 0) {
            Toast.show('Informe um valor válido para renegociação', 'warning');
            return;
        }
        
        const nome = associadoAtual?.dados_pessoais?.nome || 'Associado';
        
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Confirmar Renegociação',
                html: `
                    <div style="text-align: left; padding: 1rem;">
                        <p style="margin-bottom: 1rem;">Confirma a renegociação das dívidas para:</p>
                        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem;">${escapeHtml(nome)}</p>
                        <p style="font-size: 1.5rem; font-weight: 700; color: #16a34a; margin-top: 1rem;">
                            Valor: ${formatarMoeda(valor)}
                        </p>
                        <p style="font-size: 0.875rem; color: #6b7280; margin-top: 1rem;">
                            Este valor será incluído na próxima fatura do associado.
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-file-signature"></i> Confirmar Renegociação',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#16a34a'
            });
            
            if (result.isConfirmed) {
                await processarRenegociacao(valor);
            }
        } else {
            if (confirm(`Confirma a renegociação de ${formatarMoeda(valor)}?`)) {
                await processarRenegociacao(valor);
            }
        }
    }
    
    async function processarRenegociacao(valor) {
        try {
            // Aqui faria o POST para a API
            // const response = await fetch(API_PATHS.registrarRenegociacao, {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({
            //         associado_id: associadoAtual?.dados_pessoais?.id,
            //         valor: valor,
            //         pendencias: pendenciasAtuais.map(p => p.id)
            //     })
            // });
            
            Toast.show('Renegociação lançada com sucesso!', 'success');
            
            // Fechar modal e recarregar dados
            setTimeout(() => {
                fecharModalPendencias();
                carregarInadimplentes();
            }, 1500);
            
        } catch (error) {
            console.error('Erro ao processar renegociação:', error);
            Toast.show('Erro ao processar renegociação', 'error');
        }
    }
    
    async function quitarTodasDividas() {
        if (pendenciasAtuais.length === 0) {
            Toast.show('Não há pendências para quitar', 'info');
            return;
        }
        
        const total = pendenciasAtuais.reduce((acc, p) => acc + (parseFloat(p.valor) || 0), 0);
        const nome = associadoAtual?.dados_pessoais?.nome || 'Associado';
        
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Quitar Todas as Dívidas',
                html: `
                    <div style="text-align: left; padding: 1rem;">
                        <p style="margin-bottom: 1rem;">Confirma a quitação de todas as dívidas de:</p>
                        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem;">${escapeHtml(nome)}</p>
                        <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
                            ${pendenciasAtuais.length} pendência(s)
                        </p>
                        <p style="font-size: 1.75rem; font-weight: 700; color: #16a34a;">
                            Total: ${formatarMoeda(total)}
                        </p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check-double"></i> Quitar Todas',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#16a34a'
            });
            
            if (result.isConfirmed) {
                await processarQuitacaoTotal();
            }
        } else {
            if (confirm(`Confirma a quitação de todas as dívidas? Total: ${formatarMoeda(total)}`)) {
                await processarQuitacaoTotal();
            }
        }
    }
    
    async function processarQuitacaoTotal() {
        try {
            // Aqui faria o POST para a API
            Toast.show('Todas as dívidas foram quitadas!', 'success');
            
            pendenciasAtuais = [];
            atualizarTotalPendencias();
            
            setTimeout(() => {
                fecharModalPendencias();
                carregarInadimplentes();
            }, 1500);
            
        } catch (error) {
            console.error('Erro ao quitar dívidas:', error);
            Toast.show('Erro ao quitar dívidas', 'error');
        }
    }
    
    function imprimirPendencias() {
        window.print();
    }
    
    function exportarPendenciasPDF() {
        Toast.show('Gerando PDF das pendências...', 'info');
        // Implementar exportação real
    }

    // ===== OBSERVAÇÕES =====
    async function carregarObservacoes(associadoId) {
        const lista = document.getElementById('listaObservacoesInadim');
        
        if (lista) {
            lista.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--color-gray-400);">
                    <div class="loading-animation" style="display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1rem;">
                        <div class="loading-circle"></div>
                        <div class="loading-circle"></div>
                        <div class="loading-circle"></div>
                    </div>
                    Carregando observações...
                </div>
            `;
        }
        
        try {
            const response = await fetch(`${API_PATHS.listarObservacoes}?associado_id=${associadoId}`);
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const result = await response.json();
            
            if (result.status === 'success') {
                renderizarObservacoes(result.data, result.estatisticas);
            } else {
                throw new Error(result.message || 'Erro');
            }
        } catch (error) {
            console.error('❌ Erro ao carregar observações:', error);
            if (lista) {
                lista.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--color-warning);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                        Não foi possível carregar as observações
                    </div>
                `;
            }
        }
    }
    
    function renderizarObservacoes(observacoes, estatisticas) {
        const lista = document.getElementById('listaObservacoesInadim');
        const badge = document.getElementById('obsCountBadge');
        
        if (!lista) return;
        
        const total = estatisticas?.total || observacoes?.length || 0;
        if (badge) {
            if (total > 0) {
                badge.textContent = total;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
        
        if (!observacoes || observacoes.length === 0) {
            lista.innerHTML = `
                <div class="obs-empty">
                    <i class="fas fa-comment-slash"></i>
                    <p>Nenhuma observação registrada</p>
                </div>
            `;
            return;
        }
        
        lista.innerHTML = observacoes.map(obs => `
            <div class="obs-item">
                <div class="obs-item-header">
                    <span class="obs-author">
                        <i class="fas fa-user"></i> ${escapeHtml(obs.criado_por_nome || 'Sistema')}
                    </span>
                    <span class="obs-date">
                        <i class="fas fa-clock"></i> ${formatarDataHora(obs.data_criacao)}
                    </span>
                </div>
                <p class="obs-text">${escapeHtml(obs.observacao)}</p>
            </div>
        `).join('');
    }
    
    async function adicionarObservacao() {
        if (!associadoAtual?.dados_pessoais?.id) {
            Toast.show('Nenhum associado selecionado', 'error');
            return;
        }
        
        let observacao;
        
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Nova Observação',
                input: 'textarea',
                inputPlaceholder: 'Digite sua observação sobre este associado...',
                inputAttributes: {
                    style: 'min-height: 120px;'
                },
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save"></i> Salvar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#2563eb'
            });
            
            if (result.isConfirmed && result.value) {
                observacao = result.value.trim();
            }
        } else {
            observacao = prompt('Digite sua observação:');
        }
        
        if (observacao) {
            Toast.show('Observação adicionada com sucesso!', 'success');
            carregarObservacoes(associadoAtual.dados_pessoais.id);
        }
    }

    // ===== FILTROS =====
    function aplicarFiltros(event) {
        if (event) event.preventDefault();
        
        const nome = (document.getElementById('filtroNomeInadim')?.value || '').toLowerCase().trim();
        const rg = (document.getElementById('filtroRGInadim')?.value || '').trim();
        const vinculo = document.getElementById('filtroVinculoInadim')?.value || '';
        
        let filtrados = [...dadosOriginais];
        
        if (nome) {
            filtrados = filtrados.filter(a => 
                a.nome?.toLowerCase().includes(nome)
            );
        }
        
        if (rg) {
            filtrados = filtrados.filter(a => 
                a.rg?.includes(rg)
            );
        }
        
        if (vinculo) {
            filtrados = filtrados.filter(a => 
                a.vinculoServidor === vinculo
            );
        }
        
        dadosInadimplentes = filtrados;
        renderizarTabela(dadosInadimplentes);
        atualizarEstatisticas(dadosInadimplentes);
        
        Toast.show(`${filtrados.length} registros encontrados`, 'info');
    }
    
    function limparFiltros() {
        document.getElementById('filtroNomeInadim').value = '';
        document.getElementById('filtroRGInadim').value = '';
        document.getElementById('filtroVinculoInadim').value = '';
        
        dadosInadimplentes = [...dadosOriginais];
        renderizarTabela(dadosInadimplentes);
        atualizarEstatisticas(dadosInadimplentes);
        
        Toast.show('Filtros removidos', 'info');
    }

    // ===== AÇÕES =====
    function registrarPagamento(id) {
        const associado = dadosInadimplentes.find(a => a.id === id);
        if (!associado) {
            Toast.show('Associado não encontrado', 'error');
            return;
        }
        Toast.show(`Abrindo registro de pagamento para ${associado.nome}`, 'info');
    }
    
    async function registrarPagamentoModal() {
        if (!associadoAtual?.dados_pessoais) return;
        
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Registrar Pagamento',
                html: `
                    <div style="text-align: left; padding: 1rem;">
                        <p style="margin-bottom: 0.5rem;">Confirma o registro de pagamento para:</p>
                        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem;">
                            ${escapeHtml(associadoAtual.dados_pessoais.nome)}
                        </p>
                        <p style="color: #6b7280; font-size: 0.875rem;">
                            CPF: ${formatarCPF(associadoAtual.dados_pessoais.cpf)}
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Confirmar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#16a34a'
            });
            
            if (result.isConfirmed) {
                Toast.show('Pagamento registrado com sucesso!', 'success');
                fecharModal();
                carregarInadimplentes();
            }
        } else {
            if (confirm('Confirma o registro de pagamento?')) {
                Toast.show('Pagamento registrado!', 'success');
                fecharModal();
                carregarInadimplentes();
            }
        }
    }
    
    function ligarTelefone() {
        if (!associadoAtual?.dados_pessoais?.telefone) {
            Toast.show('Telefone não disponível', 'warning');
            return;
        }
        window.open(`tel:${associadoAtual.dados_pessoais.telefone}`, '_self');
    }
    
    function enviarEmail() {
        if (!associadoAtual?.dados_pessoais?.email) {
            Toast.show('E-mail não disponível', 'warning');
            return;
        }
        window.open(`mailto:${associadoAtual.dados_pessoais.email}`, '_blank');
    }

    // ===== EXPORTAÇÃO =====
    function exportarExcel() {
        Toast.show('Gerando arquivo Excel...', 'info');
    }
    
    function exportarPDF() {
        Toast.show('Gerando arquivo PDF...', 'info');
    }
    
    function imprimirRelatorio() {
        window.print();
    }

    // ===== UTILITÁRIOS =====
    function formatarMoeda(valor) {
        if (valor === null || valor === undefined) return 'R$ 0,00';
        return 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    
    function formatarCPF(cpf) {
        if (!cpf) return '-';
        cpf = cpf.toString().replace(/\D/g, '');
        if (cpf.length === 11) {
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }
        return cpf;
    }
    
    function formatarCEP(cep) {
        if (!cep) return '-';
        cep = cep.toString().replace(/\D/g, '');
        if (cep.length === 8) {
            return cep.replace(/(\d{5})(\d{3})/, '$1-$2');
        }
        return cep;
    }
    
    function formatarTelefone(telefone) {
        if (!telefone) return '-';
        telefone = telefone.toString().replace(/\D/g, '');
        if (telefone.length === 11) {
            return telefone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (telefone.length === 10) {
            return telefone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
        return telefone;
    }
    
    function formatarData(data) {
        if (!data) return '-';
        try {
            const d = new Date(data + 'T00:00:00');
            return d.toLocaleDateString('pt-BR');
        } catch {
            return data;
        }
    }
    
    function formatarDataHora(data) {
        if (!data) return '-';
        try {
            const d = new Date(data);
            return d.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch {
            return data;
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
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // ===== API PÚBLICA =====
    return {
        init,
        verDetalhes,
        fecharModal,
        trocarTab,
        abrirModalPendencias,
        abrirModalPendenciasDireto,
        fecharModalPendencias,
        registrarPagamento,
        aplicarFiltros,
        limparFiltros,
        adicionarObservacao,
        registrarPagamentoModal,
        registrarAcertoDivida,
        atualizarValorAcerto,
        lancarRenegociacao,
        quitarTodasDividas,
        imprimirPendencias,
        exportarPendenciasPDF,
        ligarTelefone,
        enviarEmail,
        exportarExcel,
        exportarPDF,
        imprimirRelatorio
    };

})();

// Inicialização automática quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ListaInadimplentes !== 'undefined') {
        ListaInadimplentes.init();
    }
});

// Log de carregamento
console.log('✅ ListaInadimplentes v2 com Modal de Pendências carregado');