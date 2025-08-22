/**
 * Sistema de Notificações ASSEGO
 * js/notificacoes.js
 * 
 * Sistema completo para gerenciar notificações do header
 * Funciona com o componente Header.php e API notificacoes.php
 */

class NotificacaoSystem {
    constructor() {
        this.isInitialized = false;
        this.updateInterval = null;
        this.refreshRate = 60000; // 1 minuto
        this.panelAberto = false;
        this.notificacoes = [];
        this.totalNaoLidas = 0;
        
        // Aguarda o DOM estar pronto
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    /**
     * Inicializa o sistema
     */
    init() {
        if (this.isInitialized) return;
        
        console.log('🔔 Iniciando Sistema de Notificações ASSEGO...');
        
        // Verifica se os elementos existem
        this.botaoNotificacao = document.getElementById('notificationBtn');
        this.badgeNotificacao = this.botaoNotificacao?.querySelector('.notification-badge');
        
        if (!this.botaoNotificacao) {
            console.log('⚠️ Botão de notificação não encontrado. Sistema desabilitado.');
            return;
        }
        
        this.criarPainelNotificacoes();
        this.configurarEventos();
        this.buscarNotificacoes();
        this.iniciarAtualizacaoAutomatica();
        
        this.isInitialized = true;
        console.log('✅ Sistema de Notificações inicializado com sucesso!');
    }
    
    /**
     * Cria o painel de notificações
     */
    criarPainelNotificacoes() {
        // Remove painel existente se houver
        const painelExistente = document.getElementById('painelNotificacoes');
        if (painelExistente) {
            painelExistente.remove();
        }
        
        // Cria o painel
        const painel = document.createElement('div');
        painel.id = 'painelNotificacoes';
        painel.className = 'painel-notificacoes';
        painel.innerHTML = `
            <div class="painel-header">
                <div class="painel-titulo">
                    <i class="fas fa-bell"></i>
                    <span>Notificações</span>
                    <span class="badge-contador" id="badgeContador">0</span>
                </div>
                <div class="painel-acoes">
                    <button class="btn-painel-acao" onclick="notificacaoSystem.marcarTodasLidas()" title="Marcar todas como lidas">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button class="btn-painel-acao" onclick="notificacaoSystem.atualizarNotificacoes()" title="Atualizar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            
            <div class="painel-filtros">
                <button class="filtro-btn active" data-filtro="todas">Todas</button>
                <button class="filtro-btn" data-filtro="financeiro">Financeiro</button>
                <button class="filtro-btn" data-filtro="observacoes">Observações</button>
            </div>
            
            <div class="painel-conteudo" id="painelConteudo">
                <div class="loading-notificacoes">
                    <div class="spinner-notificacoes"></div>
                    <span>Carregando notificações...</span>
                </div>
            </div>
            
            <div class="painel-footer">
                <button class="btn-ver-todas" onclick="window.location.href='notificacoes.php'">
                    Ver todas as notificações
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        `;
        
        // Adiciona o painel ao body
        document.body.appendChild(painel);
        
        // Configura filtros
        this.configurarFiltros();
    }
    
    /**
     * Configura eventos
     */
    configurarEventos() {
        // Click no botão de notificação
        this.botaoNotificacao.addEventListener('click', (e) => {
            e.stopPropagation();
            this.togglePainel();
        });
        
        // Fecha painel ao clicar fora
        document.addEventListener('click', (e) => {
            const painel = document.getElementById('painelNotificacoes');
            if (painel && !painel.contains(e.target) && !this.botaoNotificacao.contains(e.target)) {
                this.fecharPainel();
            }
        });
        
        // Previne fechamento ao clicar dentro do painel
        document.addEventListener('click', (e) => {
            if (e.target.closest('#painelNotificacoes')) {
                e.stopPropagation();
            }
        });
        
        // Atalho de teclado (Ctrl + N)
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                this.togglePainel();
            }
        });
        
        // Visibilidade da página para pausar/retomar atualizações
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pararAtualizacaoAutomatica();
            } else {
                this.iniciarAtualizacaoAutomatica();
                this.buscarNotificacoes(); // Atualiza imediatamente
            }
        });
    }
    
    /**
     * Configura filtros do painel
     */
    configurarFiltros() {
        const filtros = document.querySelectorAll('.filtro-btn');
        filtros.forEach(filtro => {
            filtro.addEventListener('click', () => {
                // Remove active de todos
                filtros.forEach(f => f.classList.remove('active'));
                // Adiciona active no clicado
                filtro.classList.add('active');
                
                const tipoFiltro = filtro.dataset.filtro;
                this.filtrarNotificacoes(tipoFiltro);
            });
        });
    }
    
    /**
     * Abre/fecha o painel
     */
    togglePainel() {
        if (this.panelAberto) {
            this.fecharPainel();
        } else {
            this.abrirPainel();
        }
    }
    
    /**
     * Abre o painel
     */
    abrirPainel() {
        const painel = document.getElementById('painelNotificacoes');
        if (!painel) return;
        
        // Posiciona o painel
        this.posicionarPainel();
        
        // Mostra o painel
        painel.classList.add('show');
        this.botaoNotificacao.classList.add('active');
        this.panelAberto = true;
        
        // Busca notificações atualizadas
        this.buscarNotificacoes();
        
        console.log('📱 Painel de notificações aberto');
    }
    
    /**
     * Fecha o painel
     */
    fecharPainel() {
        const painel = document.getElementById('painelNotificacoes');
        if (!painel) return;
        
        painel.classList.remove('show');
        this.botaoNotificacao.classList.remove('active');
        this.panelAberto = false;
        
        console.log('📱 Painel de notificações fechado');
    }
    
    /**
     * Posiciona o painel próximo ao botão
     */
    posicionarPainel() {
        const painel = document.getElementById('painelNotificacoes');
        const botao = this.botaoNotificacao;
        
        if (!painel || !botao) return;
        
        const rect = botao.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
        
        // Posição inicial (canto inferior direito do botão)
        let top = rect.bottom + scrollTop + 8;
        let left = rect.right + scrollLeft - 380; // 380px é a largura do painel
        
        // Verifica se não sai da tela
        if (left < 20) left = 20;
        if (left + 380 > window.innerWidth - 20) {
            left = window.innerWidth - 400;
        }
        
        painel.style.top = top + 'px';
        painel.style.left = left + 'px';
    }
    
    /**
     * Busca notificações da API
     */
    async buscarNotificacoes() {
        try {
            const response = await fetch('../api/notificacoes.php?acao=buscar&limite=20');
            const data = await response.json();
            
            if (data.status === 'success') {
                this.notificacoes = data.data;
                this.atualizarPainel();
                
                // Busca contagem separadamente para maior precisão
                this.buscarContagem();
                
                console.log(`📊 ${this.notificacoes.length} notificações carregadas`);
            } else {
                console.error('❌ Erro ao buscar notificações:', data.message);
                this.mostrarErro('Erro ao carregar notificações');
            }
        } catch (error) {
            console.error('❌ Erro de rede ao buscar notificações:', error);
            this.mostrarErro('Erro de conexão');
        }
    }
    
    /**
     * Busca apenas a contagem de notificações não lidas
     */
    async buscarContagem() {
        try {
            const response = await fetch('../api/notificacoes.php?acao=contar');
            const data = await response.json();
            
            if (data.status === 'success') {
                this.totalNaoLidas = data.total;
                this.atualizarBadge();
            }
        } catch (error) {
            console.error('❌ Erro ao buscar contagem:', error);
        }
    }
    
    /**
     * Atualiza o badge de notificações
     */
    atualizarBadge() {
        if (!this.badgeNotificacao) {
            // Cria o badge se não existir
            this.badgeNotificacao = document.createElement('span');
            this.badgeNotificacao.className = 'notification-badge';
            this.botaoNotificacao.appendChild(this.badgeNotificacao);
        }
        
        if (this.totalNaoLidas > 0) {
            this.badgeNotificacao.textContent = this.totalNaoLidas > 9 ? '9+' : this.totalNaoLidas;
            this.badgeNotificacao.style.display = 'flex';
            
            // Adiciona animação de pulse
            this.badgeNotificacao.classList.add('pulse');
            setTimeout(() => {
                this.badgeNotificacao?.classList.remove('pulse');
            }, 1000);
        } else {
            this.badgeNotificacao.style.display = 'none';
        }
        
        // Atualiza contador no painel
        const badgeContador = document.getElementById('badgeContador');
        if (badgeContador) {
            badgeContador.textContent = this.totalNaoLidas;
        }
    }
    
    /**
     * Atualiza o conteúdo do painel
     */
    atualizarPainel() {
        const conteudo = document.getElementById('painelConteudo');
        if (!conteudo) return;
        
        if (this.notificacoes.length === 0) {
            conteudo.innerHTML = `
                <div class="notificacoes-vazio">
                    <div class="vazio-icon">
                        <i class="fas fa-bell-slash"></i>
                    </div>
                    <h4>Nenhuma notificação</h4>
                    <p>Você está em dia! Não há notificações pendentes.</p>
                </div>
            `;
            return;
        }
        
        const html = this.notificacoes.map(notif => this.criarItemNotificacao(notif)).join('');
        conteudo.innerHTML = html;
    }
    
    /**
     * Cria HTML para um item de notificação
     */
    criarItemNotificacao(notif) {
        const prioridadeClass = notif.prioridade === 'ALTA' ? 'priority-high' : 
                               notif.prioridade === 'URGENTE' ? 'priority-urgent' : '';
        
        return `
            <div class="notificacao-item ${notif.lida ? 'lida' : 'nao-lida'} ${prioridadeClass}" 
                 data-id="${notif.id}" 
                 data-tipo="${notif.tipo}"
                 onclick="notificacaoSystem.marcarComoLida(${notif.id})">
                
                <div class="notif-icon" style="color: ${notif.cor}">
                    <i class="${notif.icone}"></i>
                </div>
                
                <div class="notif-content">
                    <div class="notif-header">
                        <span class="notif-titulo">${notif.titulo}</span>
                        <span class="notif-tempo">${notif.tempo_atras}</span>
                    </div>
                    
                    <div class="notif-mensagem">${notif.mensagem}</div>
                    
                    ${notif.associado_nome ? `
                        <div class="notif-associado">
                            <i class="fas fa-user"></i>
                            ${notif.associado_nome} ${notif.associado_cpf ? `(${notif.associado_cpf})` : ''}
                        </div>
                    ` : ''}
                    
                    ${notif.criado_por_nome ? `
                        <div class="notif-autor">
                            <i class="fas fa-user-edit"></i>
                            Por: ${notif.criado_por_nome}
                        </div>
                    ` : ''}
                    
                    ${notif.prioridade === 'ALTA' || notif.prioridade === 'URGENTE' ? `
                        <div class="notif-prioridade ${notif.prioridade.toLowerCase()}">
                            <i class="fas fa-exclamation-triangle"></i>
                            ${notif.prioridade}
                        </div>
                    ` : ''}
                </div>
                
                ${!notif.lida ? '<div class="notif-indicator"></div>' : ''}
            </div>
        `;
    }
    
    /**
     * Marca uma notificação como lida
     */
    async marcarComoLida(notificacaoId) {
        try {
            const formData = new FormData();
            formData.append('acao', 'marcar_lida');
            formData.append('notificacao_id', notificacaoId);
            
            const response = await fetch('../api/notificacoes.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Atualiza o item na lista local
                const notif = this.notificacoes.find(n => n.id == notificacaoId);
                if (notif) {
                    notif.lida = true;
                }
                
                // Atualiza visualmente
                const item = document.querySelector(`[data-id="${notificacaoId}"]`);
                if (item) {
                    item.classList.add('lida');
                    item.classList.remove('nao-lida');
                    const indicator = item.querySelector('.notif-indicator');
                    if (indicator) indicator.remove();
                }
                
                // Atualiza contagem
                this.buscarContagem();
                
                console.log('✅ Notificação marcada como lida:', notificacaoId);
            } else {
                console.error('❌ Erro ao marcar como lida:', data.message);
            }
        } catch (error) {
            console.error('❌ Erro ao marcar notificação:', error);
        }
    }
    
    /**
     * Marca todas as notificações como lidas
     */
    async marcarTodasLidas() {
        if (this.totalNaoLidas === 0) {
            this.mostrarToast('Não há notificações não lidas', 'info');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('acao', 'marcar_todas_lidas');
            
            const response = await fetch('../api/notificacoes.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                // Atualiza todas as notificações localmente
                this.notificacoes.forEach(notif => {
                    notif.lida = true;
                });
                
                // Atualiza visualmente
                document.querySelectorAll('.notificacao-item.nao-lida').forEach(item => {
                    item.classList.add('lida');
                    item.classList.remove('nao-lida');
                    const indicator = item.querySelector('.notif-indicator');
                    if (indicator) indicator.remove();
                });
                
                // Atualiza contagem
                this.totalNaoLidas = 0;
                this.atualizarBadge();
                
                this.mostrarToast(data.message, 'success');
                console.log('✅ Todas as notificações marcadas como lidas');
            } else {
                console.error('❌ Erro ao marcar todas como lidas:', data.message);
                this.mostrarToast('Erro ao marcar notificações', 'error');
            }
        } catch (error) {
            console.error('❌ Erro ao marcar todas as notificações:', error);
            this.mostrarToast('Erro de conexão', 'error');
        }
    }
    
    /**
     * Filtra notificações por tipo
     */
    filtrarNotificacoes(filtro) {
        const items = document.querySelectorAll('.notificacao-item');
        
        items.forEach(item => {
            const tipo = item.dataset.tipo;
            let mostrar = true;
            
            switch (filtro) {
                case 'financeiro':
                    mostrar = tipo === 'ALTERACAO_FINANCEIRO';
                    break;
                case 'observacoes':
                    mostrar = tipo === 'NOVA_OBSERVACAO';
                    break;
                case 'todas':
                default:
                    mostrar = true;
                    break;
            }
            
            item.style.display = mostrar ? 'flex' : 'none';
        });
    }
    
    /**
     * Inicia atualização automática
     */
    iniciarAtualizacaoAutomatica() {
        this.pararAtualizacaoAutomatica();
        
        this.updateInterval = setInterval(() => {
            if (!document.hidden) {
                this.buscarContagem();
                
                // Se o painel estiver aberto, atualiza as notificações também
                if (this.panelAberto) {
                    this.buscarNotificacoes();
                }
            }
        }, this.refreshRate);
        
        console.log(`🔄 Atualização automática iniciada (${this.refreshRate/1000}s)`);
    }
    
    /**
     * Para atualização automática
     */
    pararAtualizacaoAutomatica() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }
    
    /**
     * Força atualização manual
     */
    atualizarNotificacoes() {
        this.buscarNotificacoes();
        this.buscarContagem();
        this.mostrarToast('Notificações atualizadas', 'success');
    }
    
    /**
     * Mostra erro no painel
     */
    mostrarErro(mensagem) {
        const conteudo = document.getElementById('painelConteudo');
        if (conteudo) {
            conteudo.innerHTML = `
                <div class="notificacoes-erro">
                    <div class="erro-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h4>Erro ao carregar</h4>
                    <p>${mensagem}</p>
                    <button class="btn-tentar-novamente" onclick="notificacaoSystem.buscarNotificacoes()">
                        <i class="fas fa-redo"></i>
                        Tentar novamente
                    </button>
                </div>
            `;
        }
    }
    
    /**
     * Mostra toast de feedback
     */
    mostrarToast(mensagem, tipo = 'info') {
        // Remove toasts existentes
        document.querySelectorAll('.toast-notificacao').forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = `toast-notificacao toast-${tipo}`;
        toast.innerHTML = `
            <i class="fas fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${mensagem}</span>
        `;
        
        document.body.appendChild(toast);
        
        // Mostra o toast
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Remove após 3 segundos
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    /**
     * Limpa recursos quando necessário
     */
    destruir() {
        this.pararAtualizacaoAutomatica();
        const painel = document.getElementById('painelNotificacoes');
        if (painel) painel.remove();
        
        console.log('🧹 Sistema de notificações limpo');
    }
}

// Instância global
let notificacaoSystem;

// Inicialização automática
(() => {
    notificacaoSystem = new NotificacaoSystem();
    
    // Disponibiliza globalmente para debug
    window.notificacaoSystem = notificacaoSystem;
})();

// Cleanup ao sair da página
window.addEventListener('beforeunload', () => {
    if (notificacaoSystem) {
        notificacaoSystem.destruir();
    }
});