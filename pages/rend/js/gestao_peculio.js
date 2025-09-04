// Namespace global para Gestão de Pecúlio
window.Peculio = {
    dados: null,
    temPermissao: false,
    isFinanceiro: false,
    isPresidencia: false,

    // Inicialização - chamada após HTML estar no DOM
    init(config = {}) {
        console.log('Inicializando Gestão de Pecúlio...');
        
        this.temPermissao = config.temPermissao || false;
        this.isFinanceiro = config.isFinanceiro || false;
        this.isPresidencia = config.isPresidencia || false;

        if (!this.temPermissao) {
            console.log('Usuário sem permissão para Pecúlio');
            return;
        }

        this.attachEventListeners();
        this.showNotification('Gestão de Pecúlio carregada!', 'success');
    },

    // Event Listeners
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
    },

    // Buscar pecúlio do associado
    async buscar(event) {
        event.preventDefault();
        
        const rgInput = document.getElementById('rgBuscaPeculio');
        const busca = rgInput.value.trim();
        
        if (!busca) {
            this.mostrarAlert('Digite um RG ou nome para consultar.', 'danger');
            return;
        }

        this.mostrarLoading(true);
        this.esconderDados();

        try {
            const parametro = isNaN(busca) ? 'nome' : 'rg';
            const response = await fetch(`../api/peculio/consultar_peculio.php?${parametro}=${encodeURIComponent(busca)}`);
            const result = await response.json();

            if (result.status === 'success') {
                this.dados = result.data;
                this.exibirDados(this.dados);
                this.mostrarAlert('Dados carregados com sucesso!', 'success');
            } else {
                this.mostrarAlert(result.message, 'danger');
            }
        } catch (error) {
            console.error('Erro:', error);
            this.mostrarAlert('Erro ao consultar dados.', 'danger');
        } finally {
            this.mostrarLoading(false);
        }
    },

    // Limpar busca
    limpar() {
        document.getElementById('rgBuscaPeculio').value = '';
        this.esconderDados();
        this.esconderAlert();
        this.dados = null;
    },

    // Editar dados
    editar() {
        if (!this.dados) {
            this.showNotification('Nenhum associado selecionado', 'warning');
            return;
        }
        // Implementar modal de edição
        console.log('Editando:', this.dados);
    },

    // Confirmar recebimento
    async confirmarRecebimento() {
        if (!this.dados) {
            this.showNotification('Nenhum associado selecionado', 'warning');
            return;
        }

        if (confirm(`Confirmar recebimento do pecúlio de ${this.dados.nome}?`)) {
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
                    setTimeout(() => this.buscar({preventDefault: () => {}}), 1000);
                }
            } catch (error) {
                this.showNotification('Erro ao confirmar', 'error');
            }
        }
    },

    // Helpers
    exibirDados(dados) {
        document.getElementById('associadoNome').textContent = dados.nome || 'Nome não informado';
        document.getElementById('associadoRG').textContent = `RG: ${dados.rg || 'Não informado'}`;
        
        document.getElementById('dataPrevistaPeculio').textContent = this.formatarData(dados.data_prevista);
        document.getElementById('valorPeculio').textContent = this.formatarMoeda(dados.valor);
        document.getElementById('dataRecebimentoPeculio').textContent = this.formatarData(dados.data_recebimento) || 'Não recebido';

        document.getElementById('associadoInfoContainer').style.display = 'block';
        document.getElementById('peculioDadosContainer').style.display = 'block';
    },

    esconderDados() {
        document.getElementById('associadoInfoContainer').style.display = 'none';
        document.getElementById('peculioDadosContainer').style.display = 'none';
    },

    mostrarLoading(mostrar) {
        document.getElementById('loadingBuscaPeculio').style.display = mostrar ? 'flex' : 'none';
        document.getElementById('btnBuscarPeculio').disabled = mostrar;
    },

    mostrarAlert(msg, tipo) {
        const alert = document.getElementById('alertBuscaPeculio');
        const text = document.getElementById('alertBuscaPeculioText');
        
        text.textContent = msg;
        alert.className = `alert alert-${tipo} mt-3`;
        alert.style.display = 'flex';

        if (tipo === 'success') setTimeout(() => this.esconderAlert(), 5000);
    },

    esconderAlert() {
        document.getElementById('alertBuscaPeculio').style.display = 'none';
    },

    formatarData(data) {
        if (!data || data === '0000-00-00') return 'Não definida';
        return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
    },

    formatarMoeda(valor) {
        if (!valor) return 'R$ 0,00';
        return new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'}).format(valor);
    },

    showNotification(msg, type) {
        // Usar o sistema de notificações já existente no financeiro.php
        if (window.notifications) {
            window.notifications.show(msg, type);
        } else {
            console.log(`${type.toUpperCase()}: ${msg}`);
        }
    }
};