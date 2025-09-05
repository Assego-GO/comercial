/**
 * Módulo de Gestão de Pecúlio - Versão 100% Completa
 * Arquivo: ./rend/js/gestao_peculio.js
 * 
 * Usa exatamente as mesmas APIs da versão standalone:
 * - ../api/peculio/consultar_peculio.php
 * - ../api/peculio/confirmar_recebimento.php
 * - ../api/peculio/atualizar_peculio.php
 */

// Namespace global para Gestão de Pecúlio
window.Peculio = {
    dados: null,
    temPermissao: false,
    isFinanceiro: false,
    isPresidencia: false,

    // ===== INICIALIZAÇÃO =====
    init(config = {}) {
        console.log('🏦 Inicializando Gestão de Pecúlio...');
        
        this.temPermissao = config.temPermissao || false;
        this.isFinanceiro = config.isFinanceiro || false;
        this.isPresidencia = config.isPresidencia || false;

        console.log('Configuração Pecúlio:', {
            temPermissao: this.temPermissao,
            isFinanceiro: this.isFinanceiro,
            isPresidencia: this.isPresidencia
        });

        if (!this.temPermissao) {
            console.log('❌ Usuário sem permissão para Pecúlio');
            return;
        }

        // Verificar se elementos existem
        if (!this.verificarElementos()) {
            console.error('❌ Elementos necessários não encontrados');
            return;
        }

        this.attachEventListeners();
        this.showNotification('Gestão de Pecúlio carregada!', 'success', 2000);
        
        console.log('✅ Módulo Pecúlio inicializado com sucesso');
    },

    // ===== VERIFICAÇÃO DE ELEMENTOS =====
    verificarElementos() {
        const elementos = [
            'rgBuscaPeculio',
            'btnBuscarPeculio', 
            'loadingBuscaPeculio',
            'alertBuscaPeculio',
            'alertBuscaPeculioText',
            'associadoInfoContainer',
            'peculioDadosContainer',
            'acoesContainer',
            'associadoNome',
            'associadoRG',
            'dataPrevistaPeculio',
            'valorPeculio',
            'dataRecebimentoPeculio',
            'btnEditarPeculio',
            'btnConfirmarRecebimento'
        ];

        let todosExistem = true;
        elementos.forEach(id => {
            if (!document.getElementById(id)) {
                console.error(`❌ Elemento não encontrado: ${id}`);
                todosExistem = false;
            }
        });

        return todosExistem;
    },

    // ===== EVENT LISTENERS =====
    attachEventListeners() {
        const rgInput = document.getElementById('rgBuscaPeculio');
        if (rgInput) {
            rgInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.buscar(e);
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
            this.mostrarAlert('Por favor, digite um RG ou nome para consultar.', 'danger');
            return;
        }

        this.mostrarLoading(true);
        this.esconderDados();
        this.esconderAlert();

        try {
            console.log(`🔍 Buscando pecúlio para: ${busca}`);

            // Determina se é busca por RG ou nome
            const parametro = isNaN(busca) ? 'nome' : 'rg';
            const response = await fetch(`../api/peculio/consultar_peculio.php?${parametro}=${encodeURIComponent(busca)}`);
            const result = await response.json();

            console.log('📋 Resultado da busca:', result);

            if (result.status === 'success') {
                this.dados = result.data;
                this.exibirDados(this.dados);
                this.mostrarAlert('Dados do pecúlio carregados com sucesso!', 'success');

                // Scroll suave até os dados
                setTimeout(() => {
                    const container = document.getElementById('associadoInfoContainer');
                    if (container) {
                        container.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }, 300);

            } else {
                this.mostrarAlert(result.message, 'danger');
            }

        } catch (error) {
            console.error('❌ Erro na busca do pecúlio:', error);
            this.mostrarAlert('Erro ao consultar dados do pecúlio. Verifique sua conexão.', 'danger');
        } finally {
            this.mostrarLoading(false);
        }
    },

    // ===== EXIBIR DADOS =====
    exibirDados(dados) {
        console.log('📊 Exibindo dados do pecúlio:', dados);

        // Informações do associado
        document.getElementById('associadoNome').textContent = dados.nome || 'Nome não informado';
        document.getElementById('associadoRG').textContent = `RG Militar: ${dados.rg || 'Não informado'}`;

        // Data prevista (corrigir datas inválidas)
        const dataPrevista = (dados.data_prevista && dados.data_prevista !== '0000-00-00') ?
            this.formatarData(dados.data_prevista) :
            'Não definida';
        document.getElementById('dataPrevistaPeculio').textContent = dataPrevista;

        // Valor do pecúlio
        const valor = dados.valor ? this.formatarMoeda(parseFloat(dados.valor)) : 'Não informado';
        document.getElementById('valorPeculio').textContent = valor;
        document.getElementById('valorPeculio').className = dados.valor > 0 ? 'dados-value valor-monetario' : 'dados-value pendente';

        // Data de recebimento (corrigir datas inválidas)
        const dataRecebimento = (dados.data_recebimento && dados.data_recebimento !== '0000-00-00') ?
            this.formatarData(dados.data_recebimento) :
            'Ainda não recebido';
        const elementoRecebimento = document.getElementById('dataRecebimentoPeculio');
        elementoRecebimento.textContent = dataRecebimento;

        // Aplica estilo diferente se ainda não recebeu
        if (!dados.data_recebimento || dados.data_recebimento === '0000-00-00') {
            elementoRecebimento.className = 'dados-value pendente';
        } else {
            elementoRecebimento.className = 'dados-value data';
        }

        // Controle dos botões
        this.configurarBotoes(dados);

        // Mostrar containers
        document.getElementById('associadoInfoContainer').style.display = 'block';
        document.getElementById('peculioDadosContainer').style.display = 'block';
        document.getElementById('acoesContainer').style.display = 'block';

        console.log('✅ Dados exibidos com sucesso');
    },

    // ===== CONFIGURAR BOTÕES =====
    configurarBotoes(dados) {
        const jaRecebeu = dados.data_recebimento && dados.data_recebimento !== '0000-00-00';
        const btnConfirmar = document.getElementById('btnConfirmarRecebimento');
        const btnEditar = document.getElementById('btnEditarPeculio');

        console.log('🔘 Configurando botões. Já recebeu?', jaRecebeu);

        // SEMPRE MOSTRAR BOTÃO DE EDITAR
        if (btnEditar) {
            btnEditar.style.display = 'inline-block';
            btnEditar.style.visibility = 'visible';
        }

        // CONTROLAR BOTÃO DE CONFIRMAR RECEBIMENTO
        if (btnConfirmar) {
            if (jaRecebeu) {
                btnConfirmar.style.display = 'none';
                console.log('🔒 Botão Confirmar ocultado - já recebido');
            } else {
                btnConfirmar.style.display = 'inline-block';
                btnConfirmar.style.visibility = 'visible';
                console.log('✅ Botão Confirmar exibido');
            }
        }
    },

    // ===== LIMPAR BUSCA =====
    limpar() {
        document.getElementById('rgBuscaPeculio').value = '';
        this.esconderDados();
        this.esconderAlert();
        this.dados = null;
        console.log('🧹 Busca limpa');
    },

    // ===== EDITAR DADOS =====
    editar() {
        console.log('📝 Editando pecúlio...');
        
        if (!this.dados) {
            this.showNotification('Nenhum associado selecionado para edição', 'warning');
            return;
        }

        console.log('Dados para edição:', this.dados);

        // Criar modal de edição
        this.criarModalEdicao();
    },

    // ===== CRIAR MODAL DE EDIÇÃO =====
    criarModalEdicao() {
        const modalHtml = `
            <div class="modal fade" id="modalEditarPeculio" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-edit me-2"></i>
                                Editar Pecúlio - ${this.dados.nome}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formEditarPeculio">
                                <div class="mb-3">
                                    <label class="form-label">Valor do Pecúlio (R$)</label>
                                    <input type="number" class="form-control" id="editValor" 
                                           step="0.01" min="0" value="${this.dados.valor || 0}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Data Prevista</label>
                                    <input type="date" class="form-control" id="editDataPrevista" 
                                           value="${this.formatarDataParaInput(this.dados.data_prevista)}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Data de Recebimento</label>
                                    <input type="date" class="form-control" id="editDataRecebimento" 
                                           value="${this.formatarDataParaInput(this.dados.data_recebimento)}">
                                    <small class="text-muted">Deixe em branco se ainda não recebeu</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-warning" onclick="Peculio.salvarEdicao()">
                                <i class="fas fa-save me-2"></i>Salvar Alterações
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove modal anterior se existir
        const modalExistente = document.getElementById('modalEditarPeculio');
        if (modalExistente) {
            modalExistente.remove();
        }

        // Adiciona o modal ao body
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Mostra o modal
        const modal = new bootstrap.Modal(document.getElementById('modalEditarPeculio'));
        modal.show();

        console.log('✅ Modal de edição criado e exibido');
    },

    // ===== SALVAR EDIÇÃO =====
    async salvarEdicao() {
        const valor = document.getElementById('editValor').value;
        const dataPrevista = document.getElementById('editDataPrevista').value;
        const dataRecebimento = document.getElementById('editDataRecebimento').value;

        console.log('💾 Salvando edição:', {
            associado_id: this.dados.id,
            valor: valor,
            data_prevista: dataPrevista,
            data_recebimento: dataRecebimento
        });

        try {
            const response = await fetch('../api/peculio/atualizar_peculio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    associado_id: this.dados.id,
                    valor: valor,
                    data_prevista: dataPrevista,
                    data_recebimento: dataRecebimento
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showNotification('Dados do pecúlio atualizados com sucesso!', 'success');

                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarPeculio'));
                modal.hide();

                // Recarregar dados
                setTimeout(() => {
                    this.buscar({
                        preventDefault: () => {}
                    });
                }, 1000);
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('❌ Erro ao salvar:', error);
            this.showNotification('Erro ao salvar alterações', 'error');
        }
    },

    // ===== CONFIRMAR RECEBIMENTO =====
    async confirmarRecebimento() {
        console.log('✅ Confirmando recebimento...');
        
        if (!this.dados) {
            this.showNotification('Nenhum associado selecionado', 'warning');
            return;
        }

        if (confirm(`Confirmar recebimento do pecúlio de ${this.dados.nome}?`)) {
            try {
                const response = await fetch('../api/peculio/confirmar_recebimento.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        associado_id: this.dados.id,
                        data_recebimento: new Date().toISOString().split('T')[0]
                    })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    this.showNotification('Recebimento confirmado com sucesso!', 'success');

                    // Recarregar dados
                    setTimeout(() => {
                        this.buscar({
                            preventDefault: () => {}
                        });
                    }, 1000);
                } else {
                    this.showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('❌ Erro ao confirmar recebimento:', error);
                this.showNotification('Erro ao confirmar recebimento', 'error');
            }
        }
    },

    // ===== HELPERS DE INTERFACE =====
    esconderDados() {
        document.getElementById('associadoInfoContainer').style.display = 'none';
        document.getElementById('peculioDadosContainer').style.display = 'none';
        document.getElementById('acoesContainer').style.display = 'none';
    },

    mostrarLoading(mostrar) {
        document.getElementById('loadingBuscaPeculio').style.display = mostrar ? 'flex' : 'none';
        document.getElementById('btnBuscarPeculio').disabled = mostrar;
    },

    mostrarAlert(mensagem, tipo) {
        const alertDiv = document.getElementById('alertBuscaPeculio');
        const alertText = document.getElementById('alertBuscaPeculioText');

        alertText.textContent = mensagem;

        // Remove classes anteriores
        alertDiv.className = 'alert mt-3';

        // Adiciona classe baseada no tipo
        switch (tipo) {
            case 'success':
                alertDiv.classList.add('alert-success');
                break;
            case 'danger':
                alertDiv.classList.add('alert-danger');
                break;
            case 'info':
                alertDiv.classList.add('alert-info');
                break;
            case 'warning':
                alertDiv.classList.add('alert-warning');
                break;
        }

        alertDiv.style.display = 'flex';

        // Auto-hide após 5 segundos se for sucesso
        if (tipo === 'success') {
            setTimeout(() => this.esconderAlert(), 5000);
        }
    },

    esconderAlert() {
        document.getElementById('alertBuscaPeculio').style.display = 'none';
    },

    // ===== HELPERS DE FORMATAÇÃO =====
    formatarData(data) {
        if (!data || data === '0000-00-00') return 'Não definida';
        try {
            const dataObj = new Date(data + 'T00:00:00');
            return dataObj.toLocaleDateString('pt-BR');
        } catch (e) {
            return data;
        }
    },

    formatarDataParaInput(data) {
        if (!data || data === '0000-00-00') return '';
        try {
            const dataObj = new Date(data + 'T00:00:00');
            return dataObj.toISOString().split('T')[0];
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
        // Usar o sistema de notificações já existente no financeiro.php
        if (window.notifications) {
            window.notifications.show(msg, type, duration);
        } else {
            console.log(`${type.toUpperCase()}: ${msg}`);
        }
    }
};