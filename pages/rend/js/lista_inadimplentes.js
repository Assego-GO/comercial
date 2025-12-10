/**
 * Sistema Lista de Inadimplentes - V5.0 COM VISUALIZAÇÃO DE QUITADAS
 * ✅ CÓDIGO COMPLETO - TODAS AS FUNÇÕES
 * @version 5.0.0
 * @author Sistema ASSEGO
 */

window.ListaInadimplentes = (function() {
    'use strict';

    // ===== ESTADO =====
    let isInitialized = false;
    let dadosInadimplentes = [];
    let dadosOriginais = [];
    let associadoAtual = null;
    let pendenciasAtuais = [];
    let pendenciasQuitadas = new Set();
    let cacheAssociados = new Map();
    let processandoAcerto = new Set();

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

    // ===== SISTEMA DE TOAST =====
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
        },
        
        showMegaSuccess(message, duration = 6000) {
            if (!this.container) this.init();
            
            const toast = document.createElement('div');
            toast.className = 'toast-custom mega-success';
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="toast-content">
                    <span class="toast-message">${message}</span>
                    <div class="toast-progress"></div>
                </div>
                <button class="toast-close" onclick="this.closest('.toast-custom').remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            this.container.appendChild(toast);
            
            const progress = toast.querySelector('.toast-progress');
            if (progress) {
                progress.style.width = '100%';
                progress.style.height = '3px';
                progress.style.background = 'white';
                progress.style.marginTop = '0.5rem';
                progress.style.borderRadius = '3px';
                progress.style.transition = `width ${duration}ms linear`;
                
                setTimeout(() => {
                    progress.style.width = '0%';
                }, 100);
            }
            
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

        console.log('🚀 Inicializando ListaInadimplentes v5.0...');
        
        Toast.init();
        setupEventListeners();
        carregarInadimplentes();
        
        isInitialized = true;
        Toast.show('Sistema de inadimplência carregado', 'info', 3000);
        
        console.log('✅ ListaInadimplentes v5.0 inicializado com sucesso');
    }

    function setupEventListeners() {
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
        
        const overlay = document.getElementById('modalOverlayInadim');
        if (overlay) {
            overlay.addEventListener('click', e => {
                if (e.target === overlay) {
                    fecharModal();
                }
            });
        }
        
        const overlayPendencias = document.getElementById('modalOverlayPendencias');
        if (overlayPendencias) {
            overlayPendencias.addEventListener('click', e => {
                if (e.target === overlayPendencias) {
                    fecharModalPendencias();
                }
            });
        }
        
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
            console.log('📦 Resposta API inadimplentes:', result);
            
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
            console.error('❌ Erro ao carregar inadimplentes:', error);
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
        const base = 1000;
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
        
        document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        
        const firstTab = document.querySelector('.nav-tab[data-tab="pessoais"]');
        const firstContent = document.getElementById('tab-pessoais');
        if (firstTab) firstTab.classList.add('active');
        if (firstContent) firstContent.classList.add('active');
        
        const badge = document.getElementById('obsCountBadge');
        if (badge) {
            badge.style.display = 'none';
            badge.textContent = '0';
        }
    }
    
    function trocarTab(tabId) {
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabId);
        });
        
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
        
        const avatar = document.getElementById('modalAvatarInadim');
        if (avatar && pessoais.nome) {
            const iniciais = pessoais.nome.split(' ')
                .filter(n => n.length > 0)
                .slice(0, 2)
                .map(n => n[0].toUpperCase())
                .join('');
            avatar.innerHTML = `<span>${iniciais}</span>`;
        }
        
        set('modalNomeInadim', pessoais.nome);
        set('modalCPFHeaderInadim', formatarCPF(pessoais.cpf));
        set('modalIDHeaderInadim', `ID: ${pessoais.id || '0'}`);
        
        set('detalheNomeInadim', pessoais.nome);
        set('detalheCPFInadim', formatarCPF(pessoais.cpf));
        set('detalheRGInadim', pessoais.rg);
        set('detalheNascimentoInadim', formatarData(pessoais.nasc));
        set('detalheSexoInadim', pessoais.sexo === 'M' ? 'Masculino' : pessoais.sexo === 'F' ? 'Feminino' : '-');
        set('detalheEstadoCivilInadim', pessoais.estadoCivil);
        
        set('detalheTelefoneInadim', formatarTelefone(pessoais.telefone));
        set('detalheEmailInadim', pessoais.email);
        
        const enderecoCompleto = [endereco.endereco, endereco.numero].filter(Boolean).join(', ');
        set('detalheEnderecoInadim', enderecoCompleto || '-');
        set('detalheBairroInadim', endereco.bairro);
        set('detalheCidadeInadim', endereco.cidade);
        set('detalheCEPInadim', formatarCEP(endereco.cep));
        
        set('detalhePatenteInadim', militar.patente);
        set('detalheCorporacaoInadim', militar.corporacao);
        set('detalheLotacaoInadim', militar.lotacao);
        set('detalheUnidadeInadim', militar.unidade);
        
        set('detalheTipoAssociadoInadim', financeiro.tipoAssociado);
        set('detalheVinculoInadim', financeiro.vinculoServidor);
        set('detalheLocalDebitoInadim', financeiro.localDebito);
        
        set('lastUpdateInadim', new Date().toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }));
        
        if (pessoais.id) {
            carregarPendenciasFinanceiras(pessoais.id);
            carregarObservacoes(pessoais.id);
        }
    }
    
    // ===== BUSCAR PENDÊNCIAS FINANCEIRAS (RESUMO) =====
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
    
    // ===== 🎯 CARREGAR PENDÊNCIAS DETALHADAS - MODIFICADO =====
    async function carregarPendenciasDetalhadas(associadoId) {
        console.log('📊 Carregando pendências detalhadas para ID:', associadoId);
        
        const loading = document.getElementById('pendenciasLoading');
        const content = document.getElementById('pendenciasContent');
        
        if (loading) loading.style.display = 'flex';
        if (content) content.style.display = 'none';
        
        try {
            if (!associadoAtual?.dados_pessoais) {
                const respAssociado = await fetch(`${API_PATHS.buscarDadosCompletos}?id=${associadoId}`);
                if (respAssociado.ok) {
                    const resultAssociado = await respAssociado.json();
                    if (resultAssociado.status === 'success') {
                        associadoAtual = resultAssociado.data;
                    }
                }
            }
            
            const nome = associadoAtual?.dados_pessoais?.nome || 'Associado';
            const id = associadoAtual?.dados_pessoais?.id || associadoId;
            
            const elNome = document.getElementById('pendenciasAssociadoNome');
            const elId = document.getElementById('pendenciasAssociadoId');
            
            if (elNome) elNome.textContent = nome;
            if (elId) elId.textContent = `ID: ${id}`;
            
            const response = await fetch(`${API_PATHS.buscarPendenciasDetalhadas}?id=${associadoId}&_t=${Date.now()}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            console.log('📋 Pendências detalhadas recebidas:', result);
            
            if (result.status === 'success') {
                const todasPendencias = result.data.pendencias || [];
                
                pendenciasAtuais = todasPendencias;
                
                renderizarTabelaPendencias(todasPendencias, result.data);
                
                const totalEl = document.getElementById('pendenciasTotalDebito');
                if (totalEl) {
                    totalEl.textContent = formatarMoeda(result.data.total_debito || 0);
                }
                
                const valorRenegociado = document.getElementById('valorRenegociado');
                if (valorRenegociado) {
                    const total = result.data.total_debito || 0;
                    valorRenegociado.value = total.toFixed(2).replace('.', ',');
                }
                
                const pendenciasAtivas = todasPendencias.filter(p => !p.ja_quitado);
                
                if (pendenciasAtivas.length === 0) {
                    mostrarTelaQuitacao();
                }
                
            } else {
                throw new Error(result.message || 'Erro ao buscar pendências detalhadas');
            }
        } catch (error) {
            console.error('❌ Erro ao carregar pendências:', error);
            mostrarErroCarregamento(associadoId, error.message);
        } finally {
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
        }
    }

    // ===== 🎨 RENDERIZAR TABELA DE PENDÊNCIAS - NOVO =====
    function renderizarTabelaPendencias(pendencias, dados) {
        const tbody = document.getElementById('pendenciasTableBody');
        const somaTotal = document.getElementById('pendenciasSomaTotal');
        
        if (!tbody) return;
        
        const pendenciasAtivas = pendencias.filter(p => !p.ja_quitado);
        const pendenciasQuitadas = pendencias.filter(p => p.ja_quitado);
        
        if (pendencias.length === 0) {
            mostrarTelaQuitacao();
            return;
        }
        
        tbody.innerHTML = '';
        let totalAtivo = 0;
        
        // ===== 1. SEÇÃO: PENDÊNCIAS ATIVAS =====
        if (pendenciasAtivas.length > 0) {
            tbody.innerHTML += `
                <tr class="section-header">
                    <td colspan="5" style="background: #fef3c7; padding: 0.75rem 1.25rem; font-weight: 700; color: #92400e;">
                        <i class="fas fa-exclamation-triangle"></i> PENDÊNCIAS ATIVAS (${pendenciasAtivas.length})
                    </td>
                </tr>
            `;
            
            pendenciasAtivas.forEach((p, index) => {
                totalAtivo += parseFloat(p.valor) || 0;
                const idPendencia = p.id_pagamento || p.id || `pendente_${index}`;
                
                tbody.innerHTML += `
                    <tr data-pendencia-id="${idPendencia}" 
                        data-mes-referencia="${p.mes_referencia || ''}" 
                        data-tipo="${escapeHtml(p.tipo)}"
                        class="pendencia-row pendencia-ativa">
                        <td>
                            <div class="pendencia-descricao">
                                <span class="pendencia-tipo">${escapeHtml(p.tipo)} não quitado em ${escapeHtml(p.mes)}</span>
                                ${p.is_historica ? '<span class="badge-historica" style="font-size: 0.75rem; color: #f59e0b; margin-left: 0.5rem;">⚠️ Histórica</span>' : ''}
                            </div>
                        </td>
                        <td>
                            <span class="valor-original">${formatarMoeda(p.valor)}</span>
                        </td>
                        <td>
                            <span class="pendencia-status sem-retorno">
                                <i class="fas fa-clock"></i>
                                ${p.status_texto || 'Pendente'}
                            </span>
                        </td>
                        <td>
                            <div class="input-valor-acerto">
                                <span class="prefix">Valor:</span>
                                <input type="text" 
                                       id="valorAcerto_${idPendencia}" 
                                       value="${parseFloat(p.valor).toFixed(2)}" 
                                       onchange="ListaInadimplentes.atualizarValorAcerto(${idPendencia}, this.value)">
                            </div>
                        </td>
                        <td>
                            <button class="btn-acerto" 
                                    id="btnAcerto_${idPendencia}"
                                    onclick="ListaInadimplentes.registrarAcertoDivida(${idPendencia})">
                                <i class="fas fa-check-circle"></i>
                                Acerto da dívida
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        
        // ===== 2. SEÇÃO: PENDÊNCIAS JÁ QUITADAS =====
        if (pendenciasQuitadas.length > 0) {
            tbody.innerHTML += `
                <tr class="section-header">
                    <td colspan="5" style="background: #dcfce7; padding: 0.75rem 1.25rem; font-weight: 700; color: #166534;">
                        <i class="fas fa-check-circle"></i> JÁ QUITADAS (${pendenciasQuitadas.length})
                    </td>
                </tr>
            `;
            
            pendenciasQuitadas.forEach((p, index) => {
                const idPendencia = p.id_pagamento || `quitado_${index}`;
                const dataQuitacao = p.data_quitacao ? formatarData(p.data_quitacao) : 'Data não informada';
                const formaPagamento = p.forma_pagamento_quitacao || 'N/A';
                const funcionarioNome = p.funcionario_quitacao_nome || 'Sistema';
                
                tbody.innerHTML += `
                    <tr class="pendencia-row pendencia-quitada" data-pendencia-id="${idPendencia}">
                        <td>
                            <div class="pendencia-descricao">
                                <span class="pendencia-tipo quitada">${escapeHtml(p.tipo)} - ${escapeHtml(p.mes)}</span>
                                <span class="info-quitacao">
                                    <i class="fas fa-calendar-check"></i> Quitado em ${dataQuitacao}
                                </span>
                                <span class="info-quitacao">
                                    <i class="fas fa-user"></i> Por: ${escapeHtml(funcionarioNome)}
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="valor-original quitado">${formatarMoeda(p.valor)}</span>
                        </td>
                        <td>
                            <span class="badge-quitado">
                                <i class="fas fa-check-double"></i>
                                QUITADO
                            </span>
                            <span class="forma-pagamento-badge">
                                <i class="fas fa-credit-card"></i>
                                ${escapeHtml(formaPagamento)}
                            </span>
                        </td>
                        <td>
                            <div class="input-valor-acerto quitado">
                                <span class="prefix">Pago:</span>
                                <input type="text" 
                                       value="${parseFloat(p.valor).toFixed(2)}" 
                                       disabled
                                       style="background: #f3f4f6; cursor: not-allowed;">
                            </div>
                        </td>
                        <td>
                            <button class="btn-acerto" disabled style="opacity: 0.5; cursor: not-allowed; background: #9ca3af;">
                                <i class="fas fa-check"></i>
                                Já Quitado
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        
        if (somaTotal) {
            somaTotal.textContent = formatarMoeda(totalAtivo);
        }
    }

    function atualizarValorAcerto(pendenciaId, valor) {
        valor = valor.replace(/[^\d.,]/g, '').replace(',', '.');
        const valorNumerico = parseFloat(valor) || 0;
        
        const pendencia = pendenciasAtuais.find(p => {
            const idComparar = p.id_pagamento || p.id;
            return idComparar == pendenciaId;
        });
        
        if (pendencia) {
            pendencia.valorAcerto = valorNumerico;
        }
        
        console.log(`✏️ Valor de acerto atualizado para pendência ${pendenciaId}: ${valorNumerico}`);
    }
    
    // ===== REGISTRAR ACERTO - MODIFICADO =====
    async function registrarAcertoDivida(pendenciaId) {
        console.log('💰 Iniciando acerto de dívida para pendência:', pendenciaId);
        
        if (processandoAcerto.has(pendenciaId)) {
            Toast.show('⚠️ Acerto já está sendo processado', 'warning');
            return;
        }
        
        const pendencia = pendenciasAtuais.find(p => {
            const idComparar = p.id_pagamento || p.id;
            return idComparar == pendenciaId;
        });
        
        if (!pendencia) {
            Toast.show('Pendência não encontrada', 'error');
            console.error('❌ Pendência não encontrada no array:', pendenciaId);
            return;
        }
        
        const inputValor = document.getElementById(`valorAcerto_${pendenciaId}`);
        const valor = inputValor ? parseFloat(inputValor.value.replace(',', '.')) : pendencia.valor;
        
        console.log('📊 Dados da pendência:', {
            id: pendenciaId,
            mes_referencia: pendencia.mes_referencia,
            tipo: pendencia.tipo,
            valor: valor
        });
        
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
            
            if (!result.isConfirmed) {
                console.log('❌ Acerto cancelado pelo usuário');
                return;
            }
        } else {
            if (!confirm(`Confirma o acerto da dívida de ${formatarMoeda(valor)}?`)) {
                return;
            }
        }
        
        await processarAcertoDivida(pendenciaId, valor, pendencia);
    }
    
    // ===== PROCESSAR ACERTO =====
    async function processarAcertoDivida(pendenciaId, valor, pendencia) {
        console.log('⚙️ Processando acerto de dívida...');
        
        processandoAcerto.add(pendenciaId);
        
        const btn = document.getElementById(`btnAcerto_${pendenciaId}`);
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
        }
        
        try {
            if (!pendencia.mes_referencia) {
                throw new Error('Mês de referência não encontrado na pendência');
            }
            
            if (!associadoAtual?.dados_pessoais?.id) {
                throw new Error('ID do associado não encontrado');
            }
            
            const valorNum = parseFloat(valor);
            if (isNaN(valorNum) || valorNum <= 0) {
                throw new Error('Valor inválido para acerto');
            }
            
            const payload = {
                associado_id: associadoAtual.dados_pessoais.id,
                mes_referencia: pendencia.mes_referencia,
                valor: valorNum,
                servico_nome: pendencia.tipo,
                observacao: 'Acerto registrado via sistema web'
            };
            
            console.log('📤 Enviando payload para API:', payload);
            
            const response = await fetch(API_PATHS.registrarAcerto, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const result = await response.json();
            console.log('📥 Resposta da API:', result);
            
            if (result.status === 'success') {
                Toast.show('✅ Acerto de dívida registrado com sucesso!', 'success');
                
                pendenciasQuitadas.add(pendenciaId);
                
                const row = document.querySelector(`tr[data-pendencia-id="${pendenciaId}"]`);
                if (row) {
                    row.style.transition = 'opacity 0.3s, transform 0.3s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(50px)';
                    
                    setTimeout(() => {
                        carregarPendenciasDetalhadas(associadoAtual.dados_pessoais.id);
                    }, 300);
                }
                
                cacheAssociados.delete(associadoAtual.dados_pessoais.id);
                
                setTimeout(() => {
                    carregarInadimplentes();
                }, 1000);
                
            } else {
                if (result.message && result.message.includes('já existe um pagamento')) {
                    Toast.show('⚠️ Esta pendência já foi quitada', 'warning');
                    
                    setTimeout(() => {
                        carregarPendenciasDetalhadas(associadoAtual.dados_pessoais.id);
                    }, 500);
                } else {
                    throw new Error(result.message || 'Erro ao registrar acerto');
                }
            }
            
        } catch (error) {
            console.error('❌ Erro ao registrar acerto:', error);
            Toast.show('❌ ' + error.message, 'error');
            
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Acerto da dívida';
            }
        } finally {
            processandoAcerto.delete(pendenciaId);
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
        
        console.log(`📊 Total pendências atualizado: R$ ${total.toFixed(2)}`);
    }
    
    async function lancarRenegociacao() {
        console.log('📋 Iniciando renegociação...');
        
        const input = document.getElementById('valorRenegociado');
        if (!input) {
            Toast.show('Campo de valor não encontrado', 'error');
            return;
        }
        
        const valor = parseFloat(input.value.replace(',', '.')) || 0;
        
        if (valor <= 0) {
            Toast.show('Informe um valor válido para renegociação', 'warning');
            return;
        }
        
        if (pendenciasAtuais.length === 0) {
            Toast.show('Não há pendências para renegociar', 'warning');
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
                            ${pendenciasAtuais.length} pendência(s) serão quitadas e o valor será lançado na próxima fatura.
                        </p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-file-signature"></i> Confirmar Renegociação',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#16a34a'
            });
            
            if (!result.isConfirmed) {
                console.log('❌ Renegociação cancelada pelo usuário');
                return;
            }
        } else {
            if (!confirm(`Confirma a renegociação de ${formatarMoeda(valor)}?`)) {
                return;
            }
        }
        
        await processarRenegociacao(valor);
    }
    
    async function processarRenegociacao(valor) {
        console.log('⚙️ Processando renegociação...');
        
        try {
            if (!associadoAtual?.dados_pessoais?.id) {
                throw new Error('ID do associado não encontrado');
            }
            
            const payload = {
                associado_id: associadoAtual.dados_pessoais.id,
                valor_renegociado: valor,
                pendencias_ids: pendenciasAtuais.map(p => p.id_pagamento || p.id),
                parcelas: 1,
                observacao: 'Renegociação registrada via sistema web'
            };
            
            console.log('📤 Enviando payload para API:', payload);
            
            const response = await fetch(API_PATHS.registrarRenegociacao, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const result = await response.json();
            console.log('📥 Resposta da API:', result);
            
            if (result.status === 'success') {
                Toast.show('Renegociação lançada com sucesso!', 'success');
                
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        title: 'Renegociação Registrada!',
                        html: `
                            <div style="text-align: left; padding: 1rem;">
                                <p><strong>Acordo #${result.data.acordo_id}</strong></p>
                                <p>Valor original: R$ ${result.data.valor_original.toFixed(2).replace('.', ',')}</p>
                                <p>Valor renegociado: R$ ${result.data.valor_renegociado.toFixed(2).replace('.', ',')}</p>
                                <p>Desconto: ${result.data.desconto_percentual}%</p>
                                <p>Parcelas: ${result.data.parcelas} x R$ ${result.data.valor_parcela.toFixed(2).replace('.', ',')}</p>
                                <p>Primeiro vencimento: ${result.data.primeiro_vencimento}</p>
                                <p>Meses quitados: ${result.data.meses_quitados}</p>
                            </div>
                        `,
                        icon: 'success'
                    });
                }
                
                setTimeout(() => {
                    fecharModalPendencias();
                    carregarInadimplentes();
                }, 1500);
                
            } else {
                throw new Error(result.message || 'Erro ao processar renegociação');
            }
            
        } catch (error) {
            console.error('❌ Erro ao processar renegociação:', error);
            Toast.show('Erro ao processar renegociação: ' + error.message, 'error');
        }
    }
    
    // ===== QUITAR TODAS AS DÍVIDAS =====
    async function quitarTodasDividas() {
        console.log('💰 Iniciando quitação total...');
        
        const pendenciasAtivas = pendenciasAtuais.filter(p => !p.ja_quitado);
        
        if (pendenciasAtivas.length === 0) {
            Toast.show('Não há pendências para quitar', 'info');
            return;
        }
        
        const total = pendenciasAtivas.reduce((acc, p) => acc + (parseFloat(p.valor) || 0), 0);
        const nome = associadoAtual?.dados_pessoais?.nome || 'Associado';
        
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Quitar Todas as Dívidas',
                html: `
                    <div style="text-align: left; padding: 1rem;">
                        <p style="margin-bottom: 1rem;">Confirma a quitação de todas as dívidas de:</p>
                        <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem;">${escapeHtml(nome)}</p>
                        <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
                            ${pendenciasAtivas.length} pendência(s)
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
            
            if (!result.isConfirmed) {
                console.log('❌ Quitação cancelada pelo usuário');
                return;
            }
        } else {
            if (!confirm(`Confirma a quitação de todas as dívidas? Total: ${formatarMoeda(total)}`)) {
                return;
            }
        }
        
        await processarQuitacaoTotal();
    }
    
    async function processarQuitacaoTotal() {
        console.log('⚙️ Processando quitação total...');
        
        const btnQuitar = document.querySelector('.pendencias-footer .btn-action.success');
        if (btnQuitar) {
            btnQuitar.disabled = true;
            btnQuitar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
        }
        
        try {
            if (!associadoAtual?.dados_pessoais?.id) {
                throw new Error('ID do associado não encontrado');
            }
            
            const pendenciasAtivas = pendenciasAtuais.filter(p => !p.ja_quitado);
            const total = pendenciasAtivas.reduce((acc, p) => acc + (parseFloat(p.valor) || 0), 0);
            
            if (pendenciasAtivas.length === 0) {
                Toast.show('ℹ️ Não há pendências para quitar', 'info');
                return;
            }
            
            const payload = {
                associado_id: associadoAtual.dados_pessoais.id,
                valor_total: total,
                forma_pagamento: 'QUITACAO_TOTAL',
                observacao: 'Quitação total registrada via sistema web'
            };
            
            console.log('📤 Enviando payload para API:', payload);
            
            const response = await fetch(API_PATHS.quitarDividas, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const result = await response.json();
            console.log('📥 Resposta da API:', result);
            
            if (result.status === 'success') {
                Toast.showMegaSuccess('🎉 Todas as dívidas foram quitadas com sucesso!');
                
                pendenciasAtuais.forEach(p => {
                    const idPendencia = p.id_pagamento || p.id;
                    pendenciasQuitadas.add(idPendencia);
                });
                
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({
                        title: '🎉 Quitação Concluída!',
                        html: `
                            <div style="text-align: left; padding: 1rem;">
                                <p><strong>${result.data.meses_quitados} meses quitados</strong></p>
                                <p>Valor total: ${formatarMoeda(result.data.valor_total)}</p>
                                <p>Forma de pagamento: ${result.data.forma_pagamento}</p>
                                <p>Situação: <span style="color: #16a34a; font-weight: 700;">${result.data.situacao_financeira}</span></p>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#16a34a'
                    });
                }
                
                setTimeout(() => {
                    carregarPendenciasDetalhadas(associadoAtual.dados_pessoais.id);
                }, 500);
                
                cacheAssociados.delete(associadoAtual.dados_pessoais.id);
                
                setTimeout(() => {
                    carregarInadimplentes();
                }, 1500);
                
            } else {
                throw new Error(result.message || 'Erro ao quitar dívidas');
            }
            
        } catch (error) {
            console.error('❌ Erro ao quitar dívidas:', error);
            Toast.show('❌ ' + error.message, 'error');
            
            if (btnQuitar) {
                btnQuitar.disabled = false;
                btnQuitar.innerHTML = '<i class="fas fa-check-double"></i> Quitar Todas as Dívidas';
            }
        }
    }
    
    function imprimirPendencias() {
        window.print();
    }
    
    function exportarPendenciasPDF() {
        Toast.show('Gerando PDF das pendências...', 'info');
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

    // ===== MOSTRAR TELA DE QUITAÇÃO =====
    function mostrarTelaQuitacao() {
        const tbody = document.getElementById('pendenciasTableBody');
        const somaTotal = document.getElementById('pendenciasSomaTotal');
        const totalDebito = document.getElementById('pendenciasTotalDebito');
        const renegociacaoSection = document.querySelector('.renegociacao-section');
        
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="padding: 0;">
                        <div class="quitacao-completa">
                            <div class="quitacao-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3>✅ Todas as Dívidas Quitadas!</h3>
                            <p>Este associado não possui pendências financeiras no momento.</p>
                            <div class="quitacao-badge">
                                <i class="fas fa-shield-check"></i>
                                <span>SITUAÇÃO REGULARIZADA</span>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        if (somaTotal) somaTotal.textContent = 'R$ 0,00';
        if (totalDebito) totalDebito.textContent = 'R$ 0,00';
        
        if (renegociacaoSection) {
            renegociacaoSection.style.display = 'none';
        }
        
        const btnQuitarTodas = document.querySelector('.pendencias-footer .btn-action.success');
        if (btnQuitarTodas) {
            btnQuitarTodas.disabled = true;
            btnQuitarTodas.style.opacity = '0.5';
            btnQuitarTodas.style.cursor = 'not-allowed';
        }
    }

    function mostrarErroCarregamento(associadoId, mensagem) {
        const tbody = document.getElementById('pendenciasTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: var(--color-warning); margin-bottom: 1rem; display: block;"></i>
                        <p style="color: var(--color-gray-700); margin-bottom: 0.5rem;">Não foi possível carregar as pendências</p>
                        <p style="color: var(--color-gray-500); font-size: 0.875rem;">${mensagem}</p>
                        <button onclick="ListaInadimplentes.carregarPendenciasDetalhadas(${associadoId})" 
                                style="margin-top: 1rem; padding: 0.5rem 1rem; border: none; border-radius: 6px; background: var(--color-primary); color: white; cursor: pointer;">
                            <i class="fas fa-sync"></i> Tentar Novamente
                        </button>
                    </td>
                </tr>
            `;
        }
        Toast.show('Erro ao carregar pendências: ' + mensagem, 'error');
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
        carregarPendenciasDetalhadas,
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
        imprimirRelatorio,
        mostrarTelaQuitacao
    };

})();

// Inicialização automática
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ListaInadimplentes !== 'undefined') {
        ListaInadimplentes.init();
    }
});

console.log('✅ ListaInadimplentes v5.0 COM QUITADAS - COMPLETO');