<?php
/**
 * Componente: Painel de Desfiliações para Financeiro
 * Financeiro visualiza e aprova desfiliações pendentes (ordem 1)
 */
?>

<style>
  /* ===== ESTILOS DO COMPONENTE DE DESFILIAÇÕES ===== */
  .desfiliacao-wrapper {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    padding: 0;
    margin: 0;
  }

  .desfiliacao-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 2px solid #e9ecef;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  }

  .desfiliacao-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c5aa0;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .desfiliacao-title i {
    font-size: 1.75rem;
  }

  .desfiliacao-refresh {
    padding: 0.625rem 1.25rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .desfiliacao-refresh:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
  }

  .desfiliacao-content {
    padding: 1.5rem;
  }

  .desfiliacao-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .desfiliacao-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
  }

  .desfiliacao-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    border-color: #007bff;
    transform: translateY(-2px);
  }

  .desfiliacao-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f0;
  }

  .desfiliacao-card-info {
    flex: 1;
  }

  .desfiliacao-associado {
    font-size: 1.125rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.5rem;
  }

  .desfiliacao-meta {
    font-size: 0.875rem;
    color: #6c757d;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .desfiliacao-meta-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
  }

  .desfiliacao-meta-item i {
    color: #007bff;
  }

  .desfiliacao-status {
    padding: 0.375rem 1rem;
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #000;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
  }

  .desfiliacao-fluxo {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
  }

  .desfiliacao-fluxo-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    margin-bottom: 0.75rem;
    letter-spacing: 0.5px;
  }

  .fluxo-timeline {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
  }

  .fluxo-step {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8125rem;
    padding: 0.5rem 0.875rem;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
  }

  .fluxo-step.done {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-color: #28a745;
    color: #155724;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
  }

  .fluxo-step.current {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border-color: #ffc107;
    color: #856404;
    box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
    transform: scale(1.05);
  }

  .fluxo-step.pending {
    background: #f8f9fa;
    border-color: #dee2e6;
    color: #999;
  }

  .fluxo-arrow {
    color: #dee2e6;
    font-size: 1rem;
    font-weight: 700;
  }

  .desfiliacao-actions {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.75rem;
  }

  .desfiliacao-actions button {
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
  }

  .btn-visualizar {
    background: #007bff;
    color: white;
  }

  .btn-visualizar:hover {
    background: #0056b3;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
  }

  .btn-aprovar {
    background: #28a745;
    color: white;
  }

  .btn-aprovar:hover {
    background: #218838;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
  }

  .btn-rejeitar {
    background: #dc3545;
    color: white;
  }

  .btn-rejeitar:hover {
    background: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
  }

  .desfiliacao-empty {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #6c757d;
  }

  .desfiliacao-empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
    color: #28a745;
  }

  .desfiliacao-empty h4 {
    font-weight: 700;
    color: #333;
    margin-bottom: 0.5rem;
  }

  .desfiliacao-empty p {
    font-size: 0.9375rem;
    margin: 0;
  }

  /* ===== MODAL ===== */
  .modal-desfiliacao {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
  }

  .modal-desfiliacao.show {
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .modal-content-desfiliacao {
    background-color: white;
    padding: 0;
    border-radius: 16px;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease;
  }

  @keyframes slideUp {
    from {
      transform: translateY(50px);
      opacity: 0;
    }
    to {
      transform: translateY(0);
      opacity: 1;
    }
  }

  .modal-header-desfiliacao {
    font-size: 1.375rem;
    font-weight: 700;
    padding: 1.5rem;
    border-bottom: 2px solid #e9ecef;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  }

  .modal-body-desfiliacao {
    padding: 1.5rem;
  }

  .modal-body-desfiliacao p {
    margin-bottom: 0.75rem;
    font-size: 0.9375rem;
    line-height: 1.6;
  }

  .modal-body-desfiliacao strong {
    color: #2c5aa0;
  }

  .modal-footer-desfiliacao {
    display: flex;
    gap: 0.75rem;
    padding: 1.5rem;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
  }

  .modal-footer-desfiliacao button {
    flex: 1;
    padding: 0.875rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .observacao-input {
    width: 100%;
    padding: 0.875rem;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.9375rem;
    resize: vertical;
    min-height: 100px;
    transition: all 0.3s ease;
  }

  .observacao-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
  }

  .loading-spinner-desfiliacao {
    text-align: center;
    padding: 3rem;
  }

  .spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }

  /* ===== RESPONSIVO ===== */
  @media (max-width: 768px) {
    .desfiliacao-actions {
      grid-template-columns: 1fr;
    }

    .desfiliacao-card-header {
      flex-direction: column;
      gap: 1rem;
    }

    .fluxo-timeline {
      flex-direction: column;
      align-items: stretch;
    }

    .fluxo-arrow {
      transform: rotate(90deg);
    }
  }
</style>

<div class="desfiliacao-wrapper">
  <div class="desfiliacao-header">
    <h3 class="desfiliacao-title">
      <i class="fas fa-file-signature"></i>
      Desfiliações Pendentes
    </h3>
    <button class="desfiliacao-refresh" onclick="window.carregarDesfiliaçõesFinanceiro()">
      <i class="fas fa-sync-alt"></i>
      Atualizar
    </button>
  </div>

  <div class="desfiliacao-content">
    <div id="desfiliacao-container">
      <div class="loading-spinner-desfiliacao">
        <div class="spinner"></div>
        <p class="text-muted">Carregando desfiliações...</p>
      </div>
    </div>
  </div>
</div>

<!-- Modal para Ação (Aprovar/Rejeitar) -->
<div id="modalDesfiliacao" class="modal-desfiliacao">
  <div class="modal-content-desfiliacao">
    <div class="modal-header-desfiliacao" id="modalTitulo">
      Confirmar Ação
    </div>
    
    <div class="modal-body-desfiliacao" id="modalBody">
      <!-- Conteúdo dinâmico -->
    </div>
    
    <div class="modal-body-desfiliacao">
      <label for="observacaoInput" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
        Observação:
      </label>
      <textarea 
        id="observacaoInput" 
        class="observacao-input" 
        placeholder="Adicione uma observação (opcional)..."
      ></textarea>
    </div>
    
    <div class="modal-footer-desfiliacao">
      <button 
        class="btn btn-secondary" 
        onclick="window.fecharModal()" 
        style="background: #6c757d; color: white;">
        Cancelar
      </button>
      <button 
        id="btnConfirmarAcao" 
        class="btn btn-primary" 
        onclick="window.confirmarAçao()"
        style="background: #007bff; color: white;">
        Confirmar
      </button>
    </div>
  </div>
</div>