// ===== MÓDULO DESFILIAÇÕES JURÍDICO =====

let documentoSelecionado = null;
let açãoSelecionada = null;

async function carregarDesfiliaçõesJuridico() {
  const container = document.getElementById('desfiliacao-container');
  if (!container) {
    console.error('Container desfiliacao-container não encontrado');
    return;
  }

  container.innerHTML = '<div class="loading-spinner-desfiliacao"><div class="spinner"></div><p class="text-muted">Carregando desfiliações...</p></div>';

  try {
    const response = await fetch('../../api/desfiliacao_listar_juridico.php');
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
          <p style="font-size: 13px; margin-top: 10px;">Todas as desfiliações jurídicas foram processadas.</p>
        </div>
      `;
      return;
    }

    let html = '<div class="desfiliacao-list">';
    
    data.desfiliações.forEach(desf => {
      const dataUpload = new Date(desf.data_upload).toLocaleDateString('pt-BR');
      
      // Renderizar fluxo
      let fluxoHtml = '<div class="fluxo-timeline">';
      desf.fluxo.forEach((etapa, idx) => {
        if (idx > 0) fluxoHtml += '<span class="fluxo-arrow">→</span>';
        
        let classe = 'pending';
        if (etapa.status_aprovacao === 'APROVADO') classe = 'done';
        else if (etapa.ordem_aprovacao === 2 && etapa.status_aprovacao === 'PENDENTE') classe = 'current';
        
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
            <span class="desfiliacao-status">Aguardando Jurídico</span>
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
  window.open(`../../${caminho}`, '_blank');
}

function abrirModalAçao(documentoId, ação, nomeAssociado) {
  documentoSelecionado = documentoId;
  açãoSelecionada = ação;
  
  const modal = document.getElementById('modalDesfiliacao');
  const titulo = document.getElementById('modalTitulo');
  const body = document.getElementById('modalBody');
  const btnConfirmar = document.getElementById('btnConfirmarAcao');
  const observacao = document.getElementById('observacaoInput');
  
  observacao.value = '';
  
  if (ação === 'APROVADO') {
    titulo.textContent = '✓ Aprovar Desfiliação';
    titulo.style.color = '#10b981';
    body.innerHTML = `
      <p><strong>Associado:</strong> ${nomeAssociado}</p>
      <p><strong>Documento:</strong> ID ${documentoId}</p>
      <p style="margin-top: 15px; color: #666;">Você está aprovando esta desfiliação juridicamente. Se houver alguma observação, adicione abaixo:</p>
    `;
    btnConfirmar.textContent = '✓ Aprovar';
    btnConfirmar.className = 'btn btn-success';
    btnConfirmar.style.background = '#10b981';
  } else {
    titulo.textContent = '✗ Rejeitar Desfiliação';
    titulo.style.color = '#ef4444';
    body.innerHTML = `
      <p><strong>Associado:</strong> ${nomeAssociado}</p>
      <p><strong>Documento:</strong> ID ${documentoId}</p>
      <p style="margin-top: 15px; color: #666;">Você está rejeitando esta desfiliação por motivos jurídicos. Por favor, indique o motivo abaixo:</p>
    `;
    btnConfirmar.textContent = '✗ Rejeitar';
    btnConfirmar.className = 'btn btn-danger';
    btnConfirmar.style.background = '#ef4444';
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
  const acao = açãoSelecionada; // Armazenar localmente
  
  if (acao === 'REJEITADO' && !observacao.trim()) {
    alert('Por favor, indique o motivo da rejeição');
    return;
  }
  
  try {
    const response = await fetch('../../api/desfiliacao_aprovar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        documento_id: documentoSelecionado,
        departamento_id: 3, // Jurídico (ID 3)
        status: acao,
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
    const mensagem = acao === 'APROVADO' 
      ? 'Desfiliação aprovada juridicamente com sucesso!' 
      : 'Desfiliação rejeitada com sucesso!';
    alert(mensagem);
    
    // Recarregar lista
    carregarDesfiliaçõesJuridico();
    
  } catch (error) {
    console.error('Erro:', error);
    alert('Erro ao processar a ação. Tente novamente.');
  }
}

// Tornar funções globais
window.carregarDesfiliaçõesJuridico = carregarDesfiliaçõesJuridico;
window.visualizarDocumento = visualizarDocumento;
window.abrirModalAçao = abrirModalAçao;
window.fecharModal = fecharModal;
window.confirmarAçao = confirmarAçao;

console.log('✅ Módulo desfiliacao_juridico.js carregado');
