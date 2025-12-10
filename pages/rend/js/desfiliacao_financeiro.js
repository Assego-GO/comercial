// ===== MÓDULO DESFILIAÇÕES FINANCEIRO =====

let documentoSelecionado = null;
let açãoSelecionada = null;

async function carregarDesfiliaçõesFinanceiro() {
  const container = document.getElementById('desfiliacao-container');
  if (!container) {
    console.error('❌ Container desfiliacao-container não encontrado');
    return;
  }

  container.innerHTML = '<div class="loading-spinner-desfiliacao"><div class="spinner"></div><p class="text-muted">Carregando desfiliações...</p></div>';

  try {
    // 🔍 DEBUG: Mostrar onde estamos
    console.log('📍 URL atual da página:', window.location.href);
    console.log('📍 Base URL:', window.location.origin);
    console.log('📍 Pathname:', window.location.pathname);
    
    // ✅ CAMINHO RELATIVO (de pages/ para api/)
    const apiUrl = '../api/desfiliacao_listar_financeiro.php';
    console.log('🔍 DEBUG - Caminho relativo:', apiUrl);
    
    // Construir URL completa para ver para onde vai
    const urlCompleta = new URL(apiUrl, window.location.href);
    console.log('🌐 URL completa construída:', urlCompleta.href);
    
    const response = await fetch(apiUrl);
    
    console.log('📡 Status da resposta:', response.status);
    console.log('📡 URL final requisitada:', response.url);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const textoResposta = await response.text();
    console.log('📄 Conteúdo recebido (primeiros 500 chars):', textoResposta.substring(0, 500));

    let resultado;
    try {
      resultado = JSON.parse(textoResposta);
    } catch (e) {
      console.error('❌ Erro ao fazer parse do JSON:', e);
      console.error('📄 Conteúdo completo:', textoResposta);
      throw new Error('Resposta da API não é um JSON válido. Verifique o console para ver o conteúdo HTML retornado.');
    }

    console.log('✅ Dados recebidos:', resultado);

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
    console.error('❌ Erro completo:', error);
    container.innerHTML = `
      <div class="alert alert-danger">
        <h5><i class="fas fa-exclamation-triangle"></i> Erro ao carregar desfiliações</h5>
        <p><strong>Mensagem:</strong> ${error.message}</p>
        <p class="mt-2"><small>Verifique o Console (F12) para mais detalhes</small></p>
        <button class="btn btn-primary mt-2" onclick="carregarDesfiliaçõesFinanceiro()">
          <i class="fas fa-redo"></i> Tentar Novamente
        </button>
      </div>
    `;
  }
}

function visualizarDocumento(documentoId, caminho) {
  window.open(`../${caminho}`, '_blank');
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
    titulo.style.color = '#28a745';
    body.innerHTML = `
      <p><strong>Associado:</strong> ${nomeAssociado}</p>
      <p><strong>Documento:</strong> ID ${documentoId}</p>
      <p style="margin-top: 15px; color: #666;">Você está aprovando esta desfiliação. Se houver alguma observação, adicione abaixo:</p>
    `;
    btnConfirmar.textContent = '✓ Aprovar';
    btnConfirmar.className = 'btn btn-success';
    observacao.placeholder = 'Adicione uma observação (opcional)...';
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
    observacao.placeholder = 'Motivo da rejeição (obrigatório)...';
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
  const acao = açãoSelecionada;
  
  if (acao === 'REJEITADO' && !observacao.trim()) {
    alert('Por favor, indique o motivo da rejeição');
    return;
  }
  
  try {
    console.log('🔍 DEBUG - Enviando aprovação...');
    const response = await fetch('../api/desfiliacao_aprovar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        documento_id: documentoSelecionado,
        departamento_id: 2,
        status: acao,
        observacao: observacao
      })
    });
    
    console.log('📡 URL final (aprovar):', response.url);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    const resultado = await response.json();
    console.log('✅ Resposta da aprovação:', resultado);
    
    if (resultado.status === 'error') {
      alert(`Erro: ${resultado.message}`);
      return;
    }
    
    fecharModal();
    const mensagem = acao === 'APROVADO' 
      ? 'Desfiliação aprovada com sucesso!' 
      : 'Desfiliação rejeitada com sucesso!';
    alert(mensagem);
    
    await carregarDesfiliaçõesFinanceiro();
    
  } catch (error) {
    console.error('❌ Erro:', error);
    alert(`Erro ao processar a ação: ${error.message}`);
  }
}

// Tornar funções globais
window.carregarDesfiliaçõesFinanceiro = carregarDesfiliaçõesFinanceiro;
window.visualizarDocumento = visualizarDocumento;
window.abrirModalAçao = abrirModalAçao;
window.fecharModal = fecharModal;
window.confirmarAçao = confirmarAçao;

console.log('✅ Módulo desfiliacao_financeiro.js carregado');