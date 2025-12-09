<!-- 
  Componente: Painel de Desfiliações para Jurídico
  Jurídico visualiza e aprova desfiliações condicionais (servico_id=2)
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
    background: #7c3aed;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
  }

  .desfiliacao-refresh:hover {
    background: #6d28d9;
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
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
    border-color: #7c3aed;
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
    background: #7c3aed;
    color: white;
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
    font-size: 13px;
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
  }

  .fluxo-timeline {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  .fluxo-step {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    min-width: 100px;
  }

  .fluxo-step.done {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
  }

  .fluxo-step.current {
    background: #ede9fe;
    color: #5b21b6;
    border: 2px solid #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
  }

  .fluxo-step.pending {
    background: #f3f4f6;
    color: #6b7280;
    border: 1px solid #d1d5db;
  }

  .fluxo-arrow {
    color: #d1d5db;
    font-size: 14px;
  }

  .desfiliacao-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
  }

  .btn-visualizar {
    padding: 8px 16px;
    background: #6366f1;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
  }

  .btn-visualizar:hover {
    background: #4f46e5;
    transform: translateY(-2px);
  }

  .btn-aprovar {
    padding: 8px 16px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
  }

  .btn-aprovar:hover {
    background: #059669;
    transform: translateY(-2px);
  }

  .btn-rejeitar {
    padding: 8px 16px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
  }

  .btn-rejeitar:hover {
    background: #dc2626;
    transform: translateY(-2px);
  }

  .desfiliacao-empty {
    text-align: center;
    padding: 60px 20px;
    color: #999;
  }

  .desfiliacao-empty-icon {
    font-size: 48px;
    margin-bottom: 16px;
    color: #d1d5db;
  }

  .loading-spinner-desfiliacao {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
    flex-direction: column;
    gap: 12px;
  }

  .loading-spinner-desfiliacao .spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f4f6;
    border-top-color: #7c3aed;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  /* Modal Styles */
  .modal-overlay-desfiliacao {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
  }

  .modal-overlay-desfiliacao.show {
    opacity: 1;
    visibility: visible;
  }

  .modal-content-desfiliacao {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9);
    transition: transform 0.3s ease;
  }

  .modal-overlay-desfiliacao.show .modal-content-desfiliacao {
    transform: scale(1);
  }

  .modal-header-desfiliacao {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .modal-title-desfiliacao {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
  }

  .modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
  }

  .modal-close:hover {
    background: #f3f4f6;
    color: #333;
  }

  .modal-body-desfiliacao {
    padding: 20px;
  }

  .modal-footer-desfiliacao {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
  }

  .observacao-input {
    width: 100%;
    padding: 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    resize: vertical;
    min-height: 80px;
    margin-top: 10px;
  }

  .observacao-input:focus {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
  }

  @media (max-width: 768px) {
    .desfiliacao-card-header {
      flex-direction: column;
    }

    .desfiliacao-actions {
      width: 100%;
      justify-content: stretch;
    }

    .desfiliacao-actions button {
      flex: 1;
    }

    .modal-content-desfiliacao {
      width: 95%;
    }
  }
</style>

<!-- Container Principal -->
<div style="padding: 20px;">
  <!-- Header -->
  <div class="desfiliacao-header">
    <div class="desfiliacao-title">
      <i class="fas fa-gavel" style="color: #7c3aed; margin-right: 8px;"></i>
      Desfiliações Pendentes - Jurídico
    </div>
    <button class="desfiliacao-refresh" onclick="carregarDesfiliaçõesJuridico()">
      <i class="fas fa-sync-alt"></i> Atualizar
    </button>
  </div>

  <!-- Container de Listagem -->
  <div id="desfiliacao-container">
    <div class="loading-spinner-desfiliacao">
      <div class="spinner"></div>
      <p class="text-muted">Carregando desfiliações...</p>
    </div>
  </div>
</div>

<!-- Modal de Confirmação de Ação -->
<div class="modal-overlay-desfiliacao" id="modalDesfiliacao">
  <div class="modal-content-desfiliacao">
    <div class="modal-header-desfiliacao">
      <h3 class="modal-title-desfiliacao" id="modalTitulo">Confirmar Ação</h3>
      <button class="modal-close" onclick="fecharModal()">&times;</button>
    </div>
    <div class="modal-body-desfiliacao" id="modalBody">
      <!-- Conteúdo dinâmico -->
    </div>
    <div class="modal-body-desfiliacao">
      <label for="observacaoInput" style="font-size: 14px; font-weight: 600; display: block; margin-bottom: 8px;">
        Observação
      </label>
      <textarea 
        id="observacaoInput" 
        class="observacao-input" 
        placeholder="Adicione uma observação (opcional)..."
      ></textarea>
    </div>
    <div class="modal-footer-desfiliacao">
      <button class="btn" style="padding: 10px 20px; background: #e5e7eb; color: #374151; border: none; border-radius: 6px; cursor: pointer;" onclick="fecharModal()">
        Cancelar
      </button>
      <button id="btnConfirmarAcao" class="btn btn-primary" style="padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;" onclick="confirmarAçao()">
        Confirmar
      </button>
    </div>
  </div>
</div>
