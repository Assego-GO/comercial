/**
 * Módulo de Gestão de Pecúlio - Versão com Seleção de RG Duplicado
 * Arquivo: ./rend/js/gestao_peculio.js
 * 
 * Usa as mesmas APIs da versão standalone com melhorias visuais básicas
 */

window.Peculio = {
    dados: null,
    temPermissao: false,
    isFinanceiro: false,
    isPresidencia: false,
    dadosMultiplos: null, // Para armazenar múltiplos resultados

    // ===== INICIALIZAÇÃO =====
    init(config = {}) {
        console.log('🏦 Inicializando Gestão de Pecúlio...');
        
        this.temPermissao = config.temPermissao || false;
        this.isFinanceiro = config.isFinanceiro || false;
        this.isPresidencia = config.isPresidencia || false;

        if (!this.temPermissao) {
            console.log('❌ Usuário sem permissão para Pecúlio');
            return;
        }

        if (!this.verificarElementos()) {
            console.error('❌ Elementos necessários não encontrados');
            return;
        }

        this.attachEventListeners();
        this.adicionarEstilosMelhorados();
        this.showNotification('Gestão de Pecúlio carregada!', 'success', 2000);
        
        console.log('✅ Módulo Pecúlio inicializado');
    },

    // ===== ESTILOS MELHORADOS SIMPLES =====
    adicionarEstilosMelhorados() {
        const style = document.createElement('style');
        style.textContent = `
            /* Transições suaves básicas */
            .form-control, .btn, .dados-item-ultra-compact {
                transition: all 0.3s ease;
            }
            
            /* Hover melhorado nos inputs */
            .form-control:hover {
                border-color: #ffc107;
                box-shadow: 0 2px 8px rgba(255, 193, 7, 0.15);
            }
            
            .form-control:focus {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
            }
            
            /* Botões com hover melhorado */
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            
            .btn:active {
                transform: translateY(0);
            }
            
            /* Cards de dados com hover */
            .dados-item-ultra-compact:hover {
                transform: translateY(-3px);
                box-shadow: 0 6px 20px rgba(255, 193, 7, 0.2);
            }
            
            /* Animação simples para mostrar/esconder */
            .fade-in-simple {
                animation: fadeInSimple 0.4s ease;
            }
            
            @keyframes fadeInSimple {
                from {
                    opacity: 0;
                    transform: translateY(15px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Loading spinner melhorado */
            .loading-spinner {
                position: relative;
            }
            
            .loading-spinner::after {
                content: '';
                position: absolute;
                top: -3px;
                left: -3px;
                right: -3px;
                bottom: -3px;
                border: 2px solid rgba(255, 193, 7, 0.3);
                border-radius: 50%;
                animation: pulse 1.5s ease infinite;
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.5; transform: scale(1.1); }
            }
            
            /* Alert melhorado */
            .alert {
                border: none;
                border-radius: 10px;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            }
            
            /* Feedback visual no botão durante loading */
            .btn-loading {
                position: relative;
                color: transparent !important;
            }
            
            .btn-loading::after {
                content: '';
                position: absolute;
                width: 16px;
                height: 16px;
                top: 50%;
                left: 50%;
                margin: -8px 0 0 -8px;
                border: 2px solid transparent;
                border-top: 2px solid currentColor;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                color: white;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Estilos para seleção de múltiplos RGs */
            .selecao-rg-container {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border: 2px solid #ffc107;
                border-radius: 12px;
                padding: 1.5rem;
                margin: 1rem 0;
                box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
            }

            .selecao-rg-title {
                color: #856404;
                font-weight: 600;
                font-size: 1.1rem;
                margin-bottom: 1rem;
                text-align: center;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .selecao-rg-title i {
                margin-right: 0.5rem;
                font-size: 1.2rem;
            }

            .rg-opcao {
                background: white;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 0.75rem;
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .rg-opcao:hover {
                border-color: #ffc107;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(255, 193, 7, 0.25);
            }

            .rg-opcao:active {
                transform: translateY(0);
            }

            .rg-opcao::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 193, 7, 0.1), transparent);
                transition: left 0.5s ease;
            }

            .rg-opcao:hover::before {
                left: 100%;
            }

            .rg-opcao-nome {
                font-weight: 600;
                color: #2c3e50;
                font-size: 1rem;
                margin-bottom: 0.3rem;
            }

            .rg-opcao-detalhes {
                color: #6c757d;
                font-size: 0.9rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
            }

            .rg-opcao-rg {
                font-weight: 500;
            }

            .rg-opcao-info {
                font-style: italic;
                color: #28a745;
            }

            .selecao-rg-actions {
                text-align: center;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(133, 100, 4, 0.2);
            }

            /* Responsivo para seleção de RG */
            @media (max-width: 768px) {
                .rg-opcao-detalhes {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.2rem;
                }
                
                .selecao-rg-container {
                    padding: 1rem;
                }
            }
        `;
        document.head.appendChild(style);
    },

    // ===== EVENT LISTENERS =====
    attachEventListeners() {
        const rgInput = document.getElementById('rgBuscaPeculio');
        
        if (rgInput) {
            // Enter para buscar
            rgInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.buscar(e);
                }
            });
            
            // Escape para limpar
            rgInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.limpar();
                }
            });
        }
        
        console.log('✅ Event listeners configurados');
    },

    // ===== BUSCAR PECÚLIO =====
    async buscar(event) {
        event.preventDefault();
        
        const rgInput = document.getElementById('rgBuscaPeculio');
        const busca = rgInput.value.trim();
        
        if (!busca) {
            this.mostrarAlert('Por favor, digite um RG ou nome para consultar.', 'warning');
            return;
        }

        this.mostrarLoading(true);
        this.esconderDados();
        this.esconderAlert();
        this.esconderSelecaoRG(); // Esconder seleção anterior

        try {
            console.log(`🔍 Buscando pecúlio para: ${busca}`);

            const parametro = isNaN(busca) ? 'nome' : 'rg';
            const response = await fetch(`../api/peculio/consultar_peculio.php?${parametro}=${encodeURIComponent(busca)}`);
            const result = await response.json();

            if (result.status === 'multiple_results') {
                // Padrão do sistema: múltiplos resultados
                console.log(`🔄 Encontrados ${result.data.length} associados com mesmo RG`);
                this.dadosMultiplos = result.data;
                this.mostrarSelecaoRG(result.data);
                this.mostrarAlert(`Encontrados ${result.data.length} associados com este RG. Selecione o desejado.`, 'info');
                
            } else if (result.status === 'success') {
                // Verificar se há múltiplos resultados mesmo com status 'success'
                if (Array.isArray(result.data) && result.data.length > 1) {
                    console.log(`🔄 Encontrados ${result.data.length} associados com mesmo RG`);
                    this.dadosMultiplos = result.data;
                    this.mostrarSelecaoRG(result.data);
                    this.mostrarAlert(`Encontrados ${result.data.length} associados com este RG. Selecione o desejado.`, 'info');
                } else {
                    // Resultado único (pode ser array com 1 item ou objeto único)
                    this.dados = Array.isArray(result.data) ? result.data[0] : result.data;
                    this.exibirDados(this.dados);
                    this.mostrarAlert('Dados carregados com sucesso!', 'success');

                    // Scroll suave
                    setTimeout(() => {
                        const container = document.getElementById('associadoInfoContainer');
                        container?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                }
            } else {
                this.mostrarAlert(result.message, 'danger');
            }

        } catch (error) {
            console.error('❌ Erro na busca:', error);
            this.mostrarAlert('Erro ao consultar dados. Verifique sua conexão.', 'danger');
        } finally {
            this.mostrarLoading(false);
        }
    },

    // ===== MOSTRAR SELEÇÃO DE RG =====
    mostrarSelecaoRG(associados) {
        this.esconderDados(); // Garantir que dados anteriores estejam escondidos

        let selecaoHtml = `
            <div id="selecaoRGContainer" class="selecao-rg-container fade-in-simple">
                <div class="selecao-rg-title">
                    <i class="fas fa-users"></i>
                    Múltiplos associados encontrados - Selecione um:
                </div>
        `;

        associados.forEach((associado, index) => {
            const dataPrevista = this.formatarData(associado.data_prevista);
            const valor = associado.valor ? this.formatarMoeda(parseFloat(associado.valor)) : 'Não informado';
            const jaRecebeu = associado.data_recebimento && associado.data_recebimento !== '0000-00-00';
            
            selecaoHtml += `
                <div class="rg-opcao" onclick="Peculio.selecionarAssociado(${index})">
                    <div class="rg-opcao-nome">${associado.nome}</div>
                    <div class="rg-opcao-detalhes">
                        <span class="rg-opcao-rg">RG: ${associado.rg}</span>
                        <span class="rg-opcao-info">
                            ${jaRecebeu ? '✅ Já recebido' : '⏳ Pendente'} | ${valor}
                        </span>
                    </div>
                </div>
            `;
        });

        selecaoHtml += `
                <div class="selecao-rg-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Peculio.cancelarSelecao()">
                        <i class="fas fa-times me-1"></i>
                        Cancelar
                    </button>
                </div>
            </div>
        `;

        // Inserir após a seção de busca
        const buscaSection = document.querySelector('.busca-section-ultra-compact');
        if (buscaSection) {
            buscaSection.insertAdjacentHTML('afterend', selecaoHtml);
        }

        // Scroll suave para a seleção
        setTimeout(() => {
            const container = document.getElementById('selecaoRGContainer');
            container?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    },

    // ===== SELECIONAR ASSOCIADO =====
    selecionarAssociado(index) {
        if (!this.dadosMultiplos || !this.dadosMultiplos[index]) {
            console.error('❌ Associado não encontrado no índice:', index);
            return;
        }

        const associadoSelecionado = this.dadosMultiplos[index];
        console.log(`✅ Associado selecionado:`, associadoSelecionado);

        // Esconder seleção e mostrar dados
        this.esconderSelecaoRG();
        this.dados = associadoSelecionado;
        this.exibirDados(this.dados);
        this.mostrarAlert(`Associado ${associadoSelecionado.nome} selecionado!`, 'success');

        // Scroll suave para os dados
        setTimeout(() => {
            const container = document.getElementById('associadoInfoContainer');
            container?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
    },

    // ===== CANCELAR SELEÇÃO =====
    cancelarSelecao() {
        this.esconderSelecaoRG();
        this.dadosMultiplos = null;
        this.mostrarAlert('Seleção cancelada. Faça uma nova busca.', 'info');
    },

    // ===== ESCONDER SELEÇÃO DE RG =====
    esconderSelecaoRG() {
        const container = document.getElementById('selecaoRGContainer');
        if (container) {
            container.remove();
        }
    },

    // ===== EXIBIR DADOS =====
    exibirDados(dados) {
        console.log('📊 Exibindo dados:', dados);

        // Informações do associado
        document.getElementById('associadoNome').textContent = dados.nome || 'Nome não informado';
        document.getElementById('associadoRG').textContent = `RG Militar: ${dados.rg || 'Não informado'}`;

        // Data prevista
        const dataPrevista = (dados.data_prevista && dados.data_prevista !== '0000-00-00') ?
            this.formatarData(dados.data_prevista) : 'Não definida';
        document.getElementById('dataPrevistaPeculio').textContent = dataPrevista;

        // Valor
        const valor = dados.valor ? this.formatarMoeda(parseFloat(dados.valor)) : 'Não informado';
        document.getElementById('valorPeculio').textContent = valor;
        document.getElementById('valorPeculio').className = dados.valor > 0 ? 'dados-value' : 'dados-value pendente';

        // Data de recebimento
        const dataRecebimento = (dados.data_recebimento && dados.data_recebimento !== '0000-00-00') ?
            this.formatarData(dados.data_recebimento) : 'Ainda não recebido';
        const recebimentoEl = document.getElementById('dataRecebimentoPeculio');
        recebimentoEl.textContent = dataRecebimento;
        recebimentoEl.className = (!dados.data_recebimento || dados.data_recebimento === '0000-00-00') ? 
            'dados-value pendente' : 'dados-value data';

        // Configurar botões
        this.configurarBotoes(dados);

        // Mostrar containers com animação simples
        this.mostrarContainer('associadoInfoContainer');
        setTimeout(() => this.mostrarContainer('peculioDadosContainer'), 100);
        setTimeout(() => this.mostrarContainer('acoesContainer'), 200);
    },

    // ===== CONFIGURAR BOTÕES =====
    configurarBotoes(dados) {
        const jaRecebeu = dados.data_recebimento && dados.data_recebimento !== '0000-00-00';
        const btnConfirmar = document.getElementById('btnConfirmarRecebimento');
        const btnEditar = document.getElementById('btnEditarPeculio');

        // Sempre mostrar editar
        if (btnEditar) {
            btnEditar.style.display = 'inline-block';
        }

        // Controlar confirmar
        if (btnConfirmar) {
            btnConfirmar.style.display = jaRecebeu ? 'none' : 'inline-block';
        }
    },

    // ===== LIMPAR =====
    limpar() {
        document.getElementById('rgBuscaPeculio').value = '';
        this.esconderDados();
        this.esconderAlert();
        this.esconderSelecaoRG();
        this.dados = null;
        this.dadosMultiplos = null;
        console.log('🧹 Busca limpa');
    },

    // ===== EDITAR =====
    editar() {
        if (!this.dados) {
            this.showNotification('Nenhum associado selecionado', 'warning');
            return;
        }
        
        this.criarModalEdicao();
    },

    // ===== MODAL DE EDIÇÃO SIMPLES =====
    criarModalEdicao() {
        const modalHtml = `
            <div class="modal fade" id="modalEditarPeculio" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 12px; border: none;">
                        <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%); border-radius: 12px 12px 0 0;">
                            <h5 class="modal-title text-white fw-bold">
                                <i class="fas fa-edit me-2"></i>
                                Editar Pecúlio - ${this.dados.nome}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Valor do Pecúlio (R$)</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="editValor" 
                                           step="0.01" min="0" value="${this.dados.valor || 0}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Data Prevista</label>
                                <input type="date" class="form-control" id="editDataPrevista" 
                                       value="${this.formatarDataParaInput(this.dados.data_prevista)}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Data de Recebimento</label>
                                <input type="date" class="form-control" id="editDataRecebimento" 
                                       value="${this.formatarDataParaInput(this.dados.data_recebimento)}">
                                <small class="text-muted">Deixe em branco se ainda não recebeu</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-warning" onclick="Peculio.salvarEdicao()">
                                <i class="fas fa-save me-2"></i>Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove modal anterior
        document.getElementById('modalEditarPeculio')?.remove();

        // Adiciona novo modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Mostra modal
        const modal = new bootstrap.Modal(document.getElementById('modalEditarPeculio'));
        modal.show();
    },

    // ===== SALVAR EDIÇÃO =====
    async salvarEdicao() {
        const valor = document.getElementById('editValor').value;
        const dataPrevista = document.getElementById('editDataPrevista').value;
        const dataRecebimento = document.getElementById('editDataRecebimento').value;

        const btnSalvar = document.querySelector('[onclick="Peculio.salvarEdicao()"]');
        this.setBtnLoading(btnSalvar, true);

        try {
            const response = await fetch('../api/peculio/atualizar_peculio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    associado_id: this.dados.id,
                    valor: valor,
                    data_prevista: dataPrevista,
                    data_recebimento: dataRecebimento
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showNotification('Dados atualizados com sucesso!', 'success');
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarPeculio'));
                modal.hide();
                
                setTimeout(() => this.buscar({preventDefault: () => {}}), 800);
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            this.showNotification('Erro ao salvar alterações', 'error');
        } finally {
            this.setBtnLoading(btnSalvar, false);
        }
    },

    // ===== CONFIRMAR RECEBIMENTO =====
    async confirmarRecebimento() {
        if (!this.dados) {
            this.showNotification('Nenhum associado selecionado', 'warning');
            return;
        }

        if (!confirm(`Confirmar recebimento do pecúlio de ${this.dados.nome}?`)) return;

        const btnConfirmar = document.getElementById('btnConfirmarRecebimento');
        this.setBtnLoading(btnConfirmar, true);

        try {
            const response = await fetch('../api/peculio/confirmar_recebimento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    associado_id: this.dados.id,
                    data_recebimento: new Date().toISOString().split('T')[0]
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showNotification('Recebimento confirmado!', 'success');
                setTimeout(() => this.buscar({preventDefault: () => {}}), 800);
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Erro ao confirmar:', error);
            this.showNotification('Erro ao confirmar recebimento', 'error');
        } finally {
            this.setBtnLoading(btnConfirmar, false);
        }
    },

    // ===== HELPERS DE INTERFACE =====
    mostrarContainer(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.style.display = 'block';
            container.classList.add('fade-in-simple');
        }
    },

    esconderDados() {
        ['associadoInfoContainer', 'peculioDadosContainer', 'acoesContainer'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    },

    mostrarLoading(mostrar) {
        document.getElementById('loadingBuscaPeculio').style.display = mostrar ? 'flex' : 'none';
        const btn = document.getElementById('btnBuscarPeculio');
        this.setBtnLoading(btn, mostrar);
    },

    setBtnLoading(btn, loading) {
        if (!btn) return;
        
        if (loading) {
            btn.disabled = true;
            btn.classList.add('btn-loading');
            btn.setAttribute('data-original-text', btn.innerHTML);
            btn.innerHTML = '<span style="opacity: 0;">Loading...</span>';
        } else {
            btn.disabled = false;
            btn.classList.remove('btn-loading');
            btn.innerHTML = btn.getAttribute('data-original-text') || btn.innerHTML;
        }
    },

    mostrarAlert(mensagem, tipo) {
        const alertDiv = document.getElementById('alertBuscaPeculio');
        const alertText = document.getElementById('alertBuscaPeculioText');

        alertText.textContent = mensagem;
        alertDiv.className = `alert alert-${tipo} mt-3`;
        alertDiv.style.display = 'flex';

        if (tipo === 'success') {
            setTimeout(() => this.esconderAlert(), 4000);
        }
    },

    esconderAlert() {
        const alert = document.getElementById('alertBuscaPeculio');
        if (alert) alert.style.display = 'none';
    },

    // ===== VERIFICAÇÃO DE ELEMENTOS =====
    verificarElementos() {
        const elementos = [
            'rgBuscaPeculio', 'btnBuscarPeculio', 'loadingBuscaPeculio',
            'alertBuscaPeculio', 'alertBuscaPeculioText', 'associadoInfoContainer',
            'peculioDadosContainer', 'acoesContainer', 'associadoNome', 'associadoRG',
            'dataPrevistaPeculio', 'valorPeculio', 'dataRecebimentoPeculio'
        ];

        return elementos.every(id => {
            const exists = !!document.getElementById(id);
            if (!exists) console.error(`Elemento não encontrado: ${id}`);
            return exists;
        });
    },

    // ===== FORMATAÇÃO =====
    formatarData(data) {
        if (!data || data === '0000-00-00') return 'Não definida';
        try {
            return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
        } catch (e) {
            return data;
        }
    },

    formatarDataParaInput(data) {
        if (!data || data === '0000-00-00') return '';
        try {
            return new Date(data + 'T00:00:00').toISOString().split('T')[0];
        } catch (e) {
            return '';
        }
    },

    formatarMoeda(valor) {
        if (!valor || valor === 0) return 'R$ 0,00';
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor);
    },

    showNotification(msg, type, duration = 3000) {
        if (window.notifications) {
            window.notifications.show(msg, type, duration);
        } else {
            console.log(`${type.toUpperCase()}: ${msg}`);
        }
    }
};