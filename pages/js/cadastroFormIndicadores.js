/**
 * Carrega os indicadores no dropdown de indicação
 * pages/js/cadastroFormIndicadores.js
 */

(function() {
    'use strict';
    
    /**
     * Inicializa o carregamento dos indicadores
     */
    function initIndicadores() {
        const selectIndicacao = document.getElementById('indicacao');
        
        if (!selectIndicacao || selectIndicacao.tagName !== 'SELECT') {
            console.warn('Select de indicação não encontrado');
            return;
        }

        // Carrega os indicadores
        carregarIndicadores();
    }

    /**
     * Carrega todos os indicadores ativos do servidor
     */
    function carregarIndicadores() {
        const selectIndicacao = document.getElementById('indicacao');
        const valorAtual = document.getElementById('indicacao_valor_atual');
        
        // Mostra estado de carregamento
        selectIndicacao.innerHTML = '<option value="">Carregando indicadores...</option>';
        selectIndicacao.disabled = true;
        
        fetch('../api/listar_indicadores.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data.length > 0) {
                    // Limpa e adiciona opção padrão
                    selectIndicacao.innerHTML = '<option value="">-- Selecione o Indicador --</option>';
                    
                    // Adiciona cada indicador como opção
                    data.data.forEach(indicador => {
                        const option = document.createElement('option');
                        option.value = indicador.nome_completo;
                        option.textContent = indicador.nome_completo;
                        option.dataset.id = indicador.id;
                        option.dataset.patente = indicador.patente || '';
                        option.dataset.corporacao = indicador.corporacao || '';
                        
                        // Seleciona se for o valor atual (para edição)
                        if (valorAtual && valorAtual.value && 
                            indicador.nome_completo.toLowerCase() === valorAtual.value.toLowerCase()) {
                            option.selected = true;
                        }
                        
                        selectIndicacao.appendChild(option);
                    });
                    
                    // Se há valor atual que não está na lista (legado), adiciona-o
                    if (valorAtual && valorAtual.value) {
                        const valorExiste = Array.from(selectIndicacao.options).some(
                            opt => opt.value.toLowerCase() === valorAtual.value.toLowerCase()
                        );
                        
                        if (!valorExiste && valorAtual.value.trim() !== '') {
                            const optionLegado = document.createElement('option');
                            optionLegado.value = valorAtual.value;
                            optionLegado.textContent = valorAtual.value + ' (legado - não cadastrado)';
                            optionLegado.selected = true;
                            optionLegado.style.color = '#dc3545';
                            selectIndicacao.appendChild(optionLegado);
                        }
                    }
                    
                } else {
                    selectIndicacao.innerHTML = '<option value="">Nenhum indicador cadastrado</option>';
                }
                
                selectIndicacao.disabled = false;
            })
            .catch(error => {
                console.error('Erro ao carregar indicadores:', error);
                selectIndicacao.innerHTML = '<option value="">Erro ao carregar indicadores</option>';
                selectIndicacao.disabled = false;
            });
    }

    // Aguarda DOM carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initIndicadores);
    } else {
        initIndicadores();
    }
    
    // Expõe função para recarregar se necessário
    window.recarregarIndicadores = carregarIndicadores;
    
})();
