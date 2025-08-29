<?php
/**
 * Banner de simulação - VERSÃO CORRIGIDA
 * components/simulation-banner.php
 * 
 * Para incluir em qualquer página:
 * <?php include './components/simulation-banner.php'; ?>
 */

// Verificar se existe o método estaSimulando() e se está simulando
if (method_exists($auth, 'estaSimulando') && $auth->estaSimulando()): 
    $user_atual = $auth->getUser();
?>
<div id="simulationBanner" style="
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 0.875rem 1rem;
    text-align: center;
    font-weight: 600;
    position: relative;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    border-bottom: 3px solid #d97706;
    font-family: 'Inter', sans-serif;
">
    <div style="
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
        max-width: 1200px;
        margin: 0 auto;
    ">
        <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.95rem;">
            <svg style="width: 18px; height: 18px;" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <strong>MODO SIMULAÇÃO ATIVO</strong>
        </span>
        
        <span style="font-size: 0.9rem;">
            Visualizando como: 
            <strong><?php echo htmlspecialchars($user_atual['nome']); ?></strong>
            (<?php echo htmlspecialchars($user_atual['cargo'] ?? 'Sem cargo'); ?> - <?php echo htmlspecialchars($user_atual['departamento_nome'] ?? 'Sem departamento'); ?>)
        </span>
        
        <button onclick="voltarParaContaOriginal()" 
           style="
                background: rgba(255,255,255,0.2);
                color: white;
                border: 1px solid rgba(255,255,255,0.3);
                padding: 0.5rem 1rem;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 600;
                transition: all 0.2s;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
           "
           onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-1px)'"
           onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(0)'">
            <svg style="width: 14px; height: 14px;" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
            </svg>
            Sair da Simulação
        </button>
    </div>
</div>

<script>
// Animação do banner
document.addEventListener('DOMContentLoaded', function() {
    const banner = document.getElementById('simulationBanner');
    if (banner) {
        banner.style.transform = 'translateY(-100%)';
        banner.style.transition = 'transform 0.5s ease-out';
        
        setTimeout(() => {
            banner.style.transform = 'translateY(0)';
        }, 100);
        
        // Adicionar classe CSS ao body para ajustar layout se necessário
        document.body.classList.add('simulation-active');
    }
});

// Função para voltar para a conta original - VERSÃO CORRIGIDA
function voltarParaContaOriginal() {
    if (confirm('Deseja sair da simulação e voltar para sua conta original?')) {
        // Método mais direto - via formulário POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.pathname;
        form.innerHTML = '<input type="hidden" name="voltar_simulacao" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Função helper para mostrar mensagem de sucesso
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        z-index: 10000;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
    `;
    alertDiv.textContent = message;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}
</script>

<style>
/* Ajustes para quando banner estiver ativo */
body.simulation-active {
    padding-top: 0; /* Se necessário ajustar espaçamento */
}

/* Banner sempre no topo */
#simulationBanner {
    position: sticky;
    top: 0;
    z-index: 9999;
}

/* Animação suave */
#simulationBanner button {
    transition: all 0.2s ease;
}

/* Responsivo */
@media (max-width: 768px) {
    #simulationBanner > div {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    #simulationBanner span {
        font-size: 0.85rem;
        text-align: center;
    }
}
</style>

<?php endif; ?>