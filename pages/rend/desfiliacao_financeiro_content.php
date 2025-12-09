<!-- 
  Componente: Painel de Desfiliações para Financeiro
  Financeiro visualiza e aprova desfiliações pendentes
-->

<style>
  .desfiliacao-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #dee2e6;
  }

  .desfiliacao-title {
    font-size: 20px;
    font-weight: 600;
    color: #333;
  }

  .desfiliacao-refresh {
    padding: 8px 16px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
  }

  .desfiliacao-refresh:hover {
    background: #0056b3;
  }

  .desfiliacao-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
  }

  .desfiliacao-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    transition: all 0.3s ease;
    cursor: pointer;
  }

  .desfiliacao-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #007bff;
  }

  .desfiliacao-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
  }

  .desfiliacao-card-info {
    flex: 1;
  }

  .desfiliacao-associado {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
  }

  .desfiliacao-meta {
    font-size: 13px;
    color: #999;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
  }

  .desfiliacao-meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .desfiliacao-status {
    padding: 4px 12px;
    background: #ffc107;
    color: #000;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
  }

  .desfiliacao-fluxo {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 12px;
  }

  .desfiliacao-fluxo-title {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 8px;
  }

  .fluxo-timeline {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
  }

  .fluxo-step {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    padding: 4px 8px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
  }

  .fluxo-step.done {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
  }

  .fluxo-step.current {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
    font-weight: 600;
    box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.25);
  }

  .fluxo-step.pending {
    background: #f8f9fa;
    border-color: #dee2e6;
    color: #999;
  }

  .fluxo-arrow {
    color: #dee2e6;
    font-size: 12px;
    font-weight: 700;
  }

  .desfiliacao-actions {
    display: flex;
    gap: 10px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
  }

  .btn-aprovar {
    flex: 1;
    padding: 10px 16px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
  }

  .btn-aprovar:hover {
    background: #218838;
  }

  .btn-rejeitar {
    flex: 1;
    padding: 10px 16px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
  }

  .btn-rejeitar:hover {
    background: #c82333;
  }

  .btn-visualizar {
    flex: 1;
    padding: 10px 16px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
  }

  .btn-visualizar:hover {
    background: #0056b3;
  }

  .desfiliacao-empty {
    text-align: center;
    padding: 40px;
    color: #999;
  }

  .desfiliacao-empty-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
  }

  .modal-desfiliacao {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
  }

  .modal-desfiliacao.show {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .modal-content-desfiliacao {
    background-color: white;
    padding: 30px;
    border-radius: 10px;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  }

  .modal-header-desfiliacao {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dee2e6;
  }

  .modal-footer-desfiliacao {
    display: flex;
    gap: 10px;
    margin-top: 20px;
  }

  .observacao-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-family: Arial, sans-serif;
    font-size: 13px;
    resize: vertical;
    min-height: 80px;
    margin-bottom: 15px;
  }

  .loading-spinner-desfiliacao {
    text-align: center;
    padding: 40px;
  }

  .spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>

<div class="desfiliacao-header">
  <h3 class="desfiliacao-title">
    <i class="fas fa-file-alt me-2"></i>Desfiliações Pendentes
  </h3>
  <button class="desfiliacao-refresh" onclick="carregarDesfiliaçõesFinanceiro()">
    <i class="fas fa-sync-alt me-1"></i>Atualizar
  </button>
</div>

<div id="desfiliacao-container">
  <div class="loading-spinner-desfiliacao">
    <div class="spinner"></div>
    <p class="text-muted">Carregando desfiliações...</p>
  </div>
</div>

<!-- Modal para Ação (Aprovar/Rejeitar) -->
<div id="modalDesfiliacao" class="modal-desfiliacao">
  <div class="modal-content-desfiliacao">
    <div class="modal-header-desfiliacao" id="modalTitulo"></div>
    
    <div id="modalBody"></div>
    
    <textarea id="observacaoInput" class="observacao-input" placeholder="Adicione uma observação (opcional)..."></textarea>
    
    <div class="modal-footer-desfiliacao">
      <button class="btn btn-secondary" onclick="fecharModal()" style="flex: 1;">
        Cancelar
      </button>
      <button id="btnConfirmarAcao" class="btn btn-primary" onclick="confirmarAçao()" style="flex: 1;">
        Confirmar
      </button>
    </div>
  </div>
</div>

<script>
let documentoSelecionado = null;
let açãoSelecionada = null;

async function carregarDesfiliaçõesFinanceiro() {
  const container = document.getElementById('desfiliacao-container');
  container.innerHTML = '<div class="loading-spinner-desfiliacao"><div class="spinner"></div><p class="text-muted">Carregando desfiliações...</p></div>';

  try {
    const response = await fetch('/victor/comercial/api/desfiliacao_listar_financeiro.php');
    const resultado = await response.json();

    if (resultado.status === 'error') {
      container.innerHTML = `<div class="alert alert-danger">${resultado.message}</div>`;
      return;
    }

    const data = resultado.data;
    const badge = document.getElementById('desfiliacao-badge');
    
    if (data.total_pendentes > 0) {
      badge.textContent = data.total_pendentes;
      badge.style.display = 'inline-block';
    } else {
      badge.style.display = 'none';
    }

    if (data.total_pendentes === 0) {
      container.innerHTML = `
        <div class="desfiliacao-empty">
          <div class="desfiliacao-empty-icon">✓</div>
          <p><strong>Nenhuma desfiliação pendente</strong></p>
          <p style="font-size: 13px; margin-top: 10px;">Todas as desfiliações foram processadas.</p>
        </div>
      `;
      return;
    }

    let html = '<div class="desfiliacao-list">';
    
    data.desfiliações.forEach(desf => {
      const dataUpload = new Date(desf.data_upload).toLocaleDateString('pt-BR');
      const etapaAtual = desf.fluxo.find(f => f.ordem_aprovacao === 1);
      
      // Renderizar fluxo
      let fluxoHtml = '<div class="fluxo-timeline">';
      desf.fluxo.forEach((etapa, idx) => {
        if (idx > 0) fluxoHtml += '<span class="fluxo-arrow">→</span>';
        
        let classe = 'pending';
        if (etapa.status_aprovacao === 'APROVADO') classe = 'done';
        else if (etapa.ordem_aprovacao === 1 && etapa.status_aprovacao === 'PENDENTE') classe = 'current';
        
        fluxoHtml += `<div class="fluxo-step ${classe}">
          <span>${etapa.departamento_nome}</span>
        </div>`;
      });
      fluxoHtml += '</div>';

      html += `
        <div class="desfiliacao-card">
          <div class="desfiliacao-card-header">
            <div class="desfiliacao-card-info">
              <div class="desfiliacao-associado">${desf.associado_nome}</div>
              <div class="desfiliacao-meta">
                <span class="desfiliacao-meta-item">
                  <i class="fas fa-id-card"></i>${desf.associado_cpf || 'N/A'}
                </span>
                <span class="desfiliacao-meta-item">
                  <i class="fas fa-calendar"></i>${dataUpload}
                </span>
                <span class="desfiliacao-meta-item">
                  <i class="fas fa-user"></i>${desf.funcionario_comercial || 'N/A'}
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
            <button class="btn-visualizar" onclick="visualizarDocumento(${desf.documento_id}, '${desf.caminho_arquivo}')">
              <i class="fas fa-eye me-1"></i>Visualizar Documento
            </button>
            <button class="btn-aprovar" onclick="abrirModalAçao(${desf.documento_id}, 'APROVADO', '${desf.associado_nome}')">
              <i class="fas fa-check me-1"></i>Aprovar
            </button>
            <button class="btn-rejeitar" onclick="abrirModalAçao(${desf.documento_id}, 'REJEITADO', '${desf.associado_nome}')">
              <i class="fas fa-times me-1"></i>Rejeitar
            </button>
          </div>
        </div>
      `;
    });
    
    html += '</div>';
    container.innerHTML = html;

  } catch (error) {
    console.error('Erro:', error);
    container.innerHTML = `<div class="alert alert-danger">Erro ao carregar desfiliações. Tente novamente.</div>`;
  }
}

function visualizarDocumento(documentoId, caminho) {
  // Abre o documento em nova aba
  window.open(`/${caminho}`, '_blank');
}

function abrirModalAçao(documentoId, ação, nomeAssociado) {
  documentoSelecionado = documentoId;
  açãoSelecionada = ação;
  
  const modal = document.getElementById('modalDesfiliacao');
  const titulo = document.getElementById('modalTitulo');
  const body = document.getElementById('modalBody');
  const btnConfirmar = document.getElementById('btnConfirmarAcao');
  const observacao = document.getElementById('observacaoInput');
  
  // Limpar
  observacao.value = '';
  
  if (ação === 'APROVADO') {
    titulo.textContent = '✓ Aprovar Desfiliação';
    titulo.style.color = '#28a745';
    body.innerHTML = `
      <p><strong>Associado:</strong> ${nomeAssociado}</p>
      <p><strong>Documento:</strong> ID ${documentoId}</p>
      <p style="margin-top: 15px; color: #666;">Você está aprovando esta desfiliação. Se houver alguma observação, adicione abaixo:</p>
    `;
    btnConfirmar.textContent = '✓ Aprovar';
    btnConfirmar.className = 'btn btn-success';
  } else {
    titulo.textContent = '✗ Rejeitar Desfiliação';
    titulo.style.color = '#dc3545';
    body.innerHTML = `
      <p><strong>Associado:</strong> ${nomeAssociado}</p>
      <p><strong>Documento:</strong> ID ${documentoId}</p>
      <p style="margin-top: 15px; color: #666;">Você está rejeitando esta desfiliação. Por favor, indique o motivo abaixo:</p>
    `;
    btnConfirmar.textContent = '✗ Rejeitar';
    btnConfirmar.className = 'btn btn-danger';
    document.getElementById('observacaoInput').placeholder = 'Motivo da rejeição (obrigatório)...';
  }
  
  modal.classList.add('show');
}

function fecharModal() {
  document.getElementById('modalDesfiliacao').classList.remove('show');
  documentoSelecionado = null;
  açãoSelecionada = null;
}

async function confirmarAçao() {
  if (!documentoSelecionado || !açãoSelecionada) return;
  
  const observacao = document.getElementById('observacaoInput').value;
  
  // Validar observação em rejeição
  if (açãoSelecionada === 'REJEITADO' && !observacao.trim()) {
    alert('Por favor, indique o motivo da rejeição');
    return;
  }
  
  try {
    const response = await fetch('/victor/comercial/api/desfiliacao_aprovar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        documento_id: documentoSelecionado,
        departamento_id: 1, // Financeiro
        status: açãoSelecionada,
        observacao: observacao
      })
    });
    
    const resultado = await response.json();
    
    if (resultado.status === 'error') {
      alert(`Erro: ${resultado.message}`);
      return;
    }
    
    // Sucesso
    fecharModal();
    alert(`Desfiliação ${açãoSelecionada === 'APROVADO' ? 'aprovada' : 'rejeitada'} com sucesso!`);
    
    // Recarregar lista
    carregarDesfiliaçõesFinanceiro();
    
  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao processar a ação. Tente novamente.');
  }
}

// Inicializar quando o script for carregado (será chamado do financeiro.php)
// IMPORTANTE: Não usar DOMContentLoaded aqui porque o HTML é injetado dinamicamente
</script>
