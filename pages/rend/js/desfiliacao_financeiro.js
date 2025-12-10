/**
 * ===== MÓDULO DESFILIAÇÕES FINANCEIRO =====
 * Sistema de aprovação de desfiliações pelo setor financeiro
 * Versão: 2.0
 */

(function() {
  'use strict';

  console.log('📦 Carregando módulo desfiliacao_financeiro.js...');

  // ===== VARIÁVEIS GLOBAIS =====
  let documentoSelecionado = null;
  let açãoSelecionada = null;

  // ===== CONFIGURAÇÃO =====
  const CONFIG = {
    API_LISTAR: '../api/desfiliacao_listar_financeiro.php',
    API_APROVAR: '../api/desfiliacao_aprovar.php',
    DEPARTAMENTO_ID: 2 // Financeiro
  };

  // ===== FUNÇÃO PRINCIPAL: CARREGAR DESFILIAÇÕES =====
  async function carregarDesfiliaçõesFinanceiro() {
    console.log('🔄 Carregando desfiliações do financeiro...');
    
    const container = document.getElementById('desfiliacao-container');
    if (!container) {
      console.error('❌ Container desfiliacao-container não encontrado');
      return;
    }

    // Mostrar loading
    container.innerHTML = `
      <div class="loading-spinner-desfiliacao">
        <div class="spinner"></div>
        <p class="text-muted">Carregando desfiliações...</p>
      </div>
    `;

    try {
      console.log('📡 Fazendo requisição para:', CONFIG.API_LISTAR);
      
      const response = await fetch(CONFIG.API_LISTAR);
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const resultado = await response.json();
      console.log('✅ Resposta da API:', resultado);

      // Verificar se houve erro
      if (resultado.status === 'error') {
        console.error('❌ Erro da API:', resultado.message);
        container.innerHTML = `
          <div class="alert alert-danger" style="margin: 1rem;">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Erro ao Carregar</h5>
            <p><strong>Mensagem:</strong> ${resultado.message}</p>
            <button class="btn btn-primary mt-2" onclick="window.carregarDesfiliaçõesFinanceiro()">
              <i class="fas fa-redo me-1"></i> Tentar Novamente
            </button>
          </div>
        `;
        return;
      }

      const data = resultado.data;
      console.log('📊 Total de desfiliações pendentes:', data.total_pendentes);

      // Atualizar badge de notificação
      atualizarBadge(data.total_pendentes);

      // Renderizar conteúdo
      if (data.total_pendentes === 0) {
        renderizarListaVazia(container);
      } else {
        renderizarLista(container, data.desfiliações);
      }

    } catch (error) {
      console.error('❌ Erro ao carregar desfiliações:', error);
      container.innerHTML = `
        <div class="alert alert-danger" style="margin: 1rem;">
          <h5><i class="fas fa-exclamation-triangle me-2"></i>Erro de Conexão</h5>
          <p><strong>Detalhes:</strong> ${error.message}</p>
          <p class="mb-2">Possíveis causas:</p>
          <ul>
            <li>API não encontrada ou inacessível</li>
            <li>Problema de conexão com o servidor</li>
            <li>Erro no banco de dados</li>
          </ul>
          <button class="btn btn-primary mt-2" onclick="window.carregarDesfiliaçõesFinanceiro()">
            <i class="fas fa-redo me-1"></i> Tentar Novamente
          </button>
        </div>
      `;
    }
  }

  // ===== ATUALIZAR BADGE DE NOTIFICAÇÕES =====
  function atualizarBadge(total) {
    const badge = document.getElementById('desfiliacao-badge');
    if (badge) {
      if (total > 0) {
        badge.textContent = total;
        badge.style.display = 'inline-block';
      } else {
        badge.style.display = 'none';
      }
    }
  }

  // ===== RENDERIZAR LISTA VAZIA =====
  function renderizarListaVazia(container) {
    container.innerHTML = `
      <div class="desfiliacao-empty">
        <div class="desfiliacao-empty-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <h4>Nenhuma desfiliação pendente</h4>
        <p>Todas as desfiliações foram processadas pelo financeiro.</p>
      </div>
    `;
  }

  // ===== RENDERIZAR LISTA DE DESFILIAÇÕES =====
  function renderizarLista(container, desfiliações) {
    let html = '<div class="desfiliacao-list">';
    
    desfiliações.forEach(desf => {
      html += criarCardDesfiliacao(desf);
    });
    
    html += '</div>';
    container.innerHTML = html;
  }

  // ===== CRIAR CARD DE DESFILIAÇÃO =====
  function criarCardDesfiliacao(desf) {
    const dataUpload = formatarData(desf.data_upload);
    const fluxoHtml = criarFluxoTimeline(desf.fluxo);

    return `
      <div class="desfiliacao-card">
        <div class="desfiliacao-card-header">
          <div class="desfiliacao-card-info">
            <div class="desfiliacao-associado">
              ${escapeHtml(desf.associado_nome)}
            </div>
            <div class="desfiliacao-meta">
              <span class="desfiliacao-meta-item">
                <i class="fas fa-id-card"></i>
                ${escapeHtml(desf.associado_cpf || 'N/A')}
              </span>
              <span class="desfiliacao-meta-item">
                <i class="fas fa-calendar"></i>
                ${dataUpload}
              </span>
              <span class="desfiliacao-meta-item">
                <i class="fas fa-user"></i>
                ${escapeHtml(desf.funcionario_comercial || 'N/A')}
              </span>
            </div>
          </div>
          <span class="desfiliacao-status">Aguardando</span>
        </div>
        
        <div class="desfiliacao-fluxo">
          <div class="desfiliacao-fluxo-title">Status de Aprovação</div>
          ${fluxoHtml}
        </div>
        
        <div class="desfiliacao-actions">
          <button 
            class="btn-visualizar" 
            onclick="window.visualizarDocumento(${desf.documento_id}, '${escapeHtml(desf.caminho_arquivo)}')">
            <i class="fas fa-eye"></i>
            Visualizar
          </button>
          <button 
            class="btn-aprovar" 
            onclick="window.abrirModalAçao(${desf.documento_id}, 'APROVADO', '${escapeHtml(desf.associado_nome)}')">
            <i class="fas fa-check"></i>
            Aprovar
          </button>
          <button 
            class="btn-rejeitar" 
            onclick="window.abrirModalAçao(${desf.documento_id}, 'REJEITADO', '${escapeHtml(desf.associado_nome)}')">
            <i class="fas fa-times"></i>
            Rejeitar
          </button>
        </div>
      </div>
    `;
  }

  // ===== CRIAR TIMELINE DE FLUXO =====
  function criarFluxoTimeline(fluxo) {
    let html = '<div class="fluxo-timeline">';
    
    fluxo.forEach((etapa, idx) => {
      if (idx > 0) {
        html += '<span class="fluxo-arrow">→</span>';
      }
      
      let classe = 'pending';
      if (etapa.status_aprovacao === 'APROVADO') {
        classe = 'done';
      } else if (etapa.ordem_aprovacao === 1 && etapa.status_aprovacao === 'PENDENTE') {
        classe = 'current';
      }
      
      html += `
        <div class="fluxo-step ${classe}">
          <i class="fas ${getIconeEtapa(etapa.ordem_aprovacao)}"></i>
          <span>${escapeHtml(etapa.departamento_nome)}</span>
        </div>
      `;
    });
    
    html += '</div>';
    return html;
  }

  // ===== VISUALIZAR DOCUMENTO =====
  function visualizarDocumento(documentoId, caminho) {
    console.log('📄 Visualizando documento:', documentoId, caminho);
    
    // Garantir que o caminho está correto
    const caminhoCompleto = caminho.startsWith('/') ? caminho : '/' + caminho;
    
    window.open(caminhoCompleto, '_blank', 'noopener,noreferrer');
  }

  // ===== ABRIR MODAL DE CONFIRMAÇÃO =====
  function abrirModalAçao(documentoId, ação, nomeAssociado) {
    console.log('🔔 Abrindo modal:', { documentoId, ação, nomeAssociado });
    
    documentoSelecionado = documentoId;
    açãoSelecionada = ação;
    
    const modal = document.getElementById('modalDesfiliacao');
    const titulo = document.getElementById('modalTitulo');
    const body = document.getElementById('modalBody');
    const btnConfirmar = document.getElementById('btnConfirmarAcao');
    const observacao = document.getElementById('observacaoInput');
    
    if (!modal || !titulo || !body || !btnConfirmar || !observacao) {
      console.error('❌ Elementos do modal não encontrados');
      alert('Erro ao abrir modal. Recarregue a página.');
      return;
    }

    // Limpar campo de observação
    observacao.value = '';
    
    if (ação === 'APROVADO') {
      titulo.textContent = '✓ Aprovar Desfiliação';
      titulo.style.color = '#28a745';
      body.innerHTML = `
        <p><strong>Associado:</strong> ${escapeHtml(nomeAssociado)}</p>
        <p><strong>Documento:</strong> ID ${documentoId}</p>
        <hr style="margin: 1rem 0; border-color: #e9ecef;">
        <p style="color: #6c757d;">
          <i class="fas fa-info-circle me-1"></i>
          Você está <strong>aprovando</strong> esta desfiliação. 
          O documento seguirá para a próxima etapa do fluxo.
        </p>
      `;
      btnConfirmar.textContent = '✓ Aprovar';
      btnConfirmar.style.background = '#28a745';
      observacao.placeholder = 'Adicione uma observação (opcional)...';
    } else {
      titulo.textContent = '✗ Rejeitar Desfiliação';
      titulo.style.color = '#dc3545';
      body.innerHTML = `
        <p><strong>Associado:</strong> ${escapeHtml(nomeAssociado)}</p>
        <p><strong>Documento:</strong> ID ${documentoId}</p>
        <hr style="margin: 1rem 0; border-color: #e9ecef;">
        <p style="color: #6c757d;">
          <i class="fas fa-exclamation-triangle me-1"></i>
          Você está <strong>rejeitando</strong> esta desfiliação. 
          Por favor, indique o motivo abaixo.
        </p>
      `;
      btnConfirmar.textContent = '✗ Rejeitar';
      btnConfirmar.style.background = '#dc3545';
      observacao.placeholder = 'Motivo da rejeição (obrigatório)...';
    }
    
    modal.classList.add('show');
  }

  // ===== FECHAR MODAL =====
  function fecharModal() {
    console.log('❌ Fechando modal');
    
    const modal = document.getElementById('modalDesfiliacao');
    if (modal) {
      modal.classList.remove('show');
    }
    
    documentoSelecionado = null;
    açãoSelecionada = null;
  }

  // ===== CONFIRMAR AÇÃO =====
  async function confirmarAçao() {
    if (!documentoSelecionado || !açãoSelecionada) {
      console.error('❌ Dados da ação não definidos');
      return;
    }
    
    const observacao = document.getElementById('observacaoInput')?.value || '';
    const acao = açãoSelecionada; // Armazenar localmente
    
    // Validar observação obrigatória em rejeição
    if (acao === 'REJEITADO' && !observacao.trim()) {
      alert('Por favor, indique o motivo da rejeição');
      return;
    }
    
    // Desabilitar botão enquanto processa
    const btnConfirmar = document.getElementById('btnConfirmarAcao');
    if (btnConfirmar) {
      btnConfirmar.disabled = true;
      btnConfirmar.textContent = 'Processando...';
    }

    try {
      console.log('📤 Enviando aprovação:', {
        documento_id: documentoSelecionado,
        departamento_id: CONFIG.DEPARTAMENTO_ID,
        status: acao,
        observacao: observacao
      });

      const response = await fetch(CONFIG.API_APROVAR, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          documento_id: documentoSelecionado,
          departamento_id: CONFIG.DEPARTAMENTO_ID,
          status: acao,
          observacao: observacao
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const resultado = await response.json();
      console.log('✅ Resposta da aprovação:', resultado);
      
      if (resultado.status === 'error') {
        throw new Error(resultado.message || 'Erro desconhecido');
      }
      
      // Sucesso
      fecharModal();
      
      const mensagem = acao === 'APROVADO' 
        ? 'Desfiliação aprovada com sucesso!' 
        : 'Desfiliação rejeitada com sucesso!';
      
      alert(mensagem);
      
      // Recarregar lista
      carregarDesfiliaçõesFinanceiro();
      
    } catch (error) {
      console.error('❌ Erro ao processar ação:', error);
      alert(`Erro ao processar a ação: ${error.message}`);
      
      // Reabilitar botão
      if (btnConfirmar) {
        btnConfirmar.disabled = false;
        btnConfirmar.textContent = acao === 'APROVADO' ? '✓ Aprovar' : '✗ Rejeitar';
      }
    }
  }

  // ===== HELPERS =====
  function formatarData(dataString) {
    try {
      const data = new Date(dataString);
      return data.toLocaleDateString('pt-BR');
    } catch {
      return 'Data inválida';
    }
  }

  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }

  function getIconeEtapa(ordem) {
    const icones = {
      1: 'fa-dollar-sign',  // Financeiro
      2: 'fa-gavel',        // Jurídico
      3: 'fa-user-tie'      // Presidência
    };
    return icones[ordem] || 'fa-circle';
  }

  // ===== EXPORTAR FUNÇÕES GLOBAIS =====
  window.carregarDesfiliaçõesFinanceiro = carregarDesfiliaçõesFinanceiro;
  window.visualizarDocumento = visualizarDocumento;
  window.abrirModalAçao = abrirModalAçao;
  window.fecharModal = fecharModal;
  window.confirmarAçao = confirmarAçao;

  console.log('✅ Módulo desfiliacao_financeiro.js carregado e pronto!');

  // ===== AUTO-INICIALIZAÇÃO =====
  // Aguarda o DOM estar pronto e carrega automaticamente
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      console.log('🚀 Auto-inicializando desfiliações financeiro...');
      setTimeout(carregarDesfiliaçõesFinanceiro, 100);
    });
  } else {
    console.log('🚀 Auto-inicializando desfiliações financeiro...');
    setTimeout(carregarDesfiliaçõesFinanceiro, 100);
  }

})();