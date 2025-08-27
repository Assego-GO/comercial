/**
 * Sistema de Autocomplete para Indicação de Associados
 * pages/js/cadastroFormAutocomplete.js
 */

(function() {
    'use strict';
    
    let timeoutBusca = null;
    let indicadorSelecionado = null;
    let resultadosAtuais = [];
    let indexSelecionado = -1;

    /**
     * Inicializa o sistema de autocomplete
     */
    function initAutocomplete() {
        const inputIndicacao = document.getElementById('indicacao');
        const suggestionsContainer = document.getElementById('indicacaoSuggestions');
        
        if (!inputIndicacao || !suggestionsContainer) {
            console.warn('Elementos de autocomplete não encontrados');
            return;
        }

        // Eventos do input
        inputIndicacao.addEventListener('input', handleInput);
        inputIndicacao.addEventListener('focus', handleFocus);
        inputIndicacao.addEventListener('blur', handleBlur);
        inputIndicacao.addEventListener('keydown', handleKeydown);
        
        // Fecha sugestões ao clicar fora
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.autocomplete-container')) {
                fecharSugestoes();
            }
        });
    }

    /**
     * Manipula entrada de texto
     */
    function handleInput(e) {
        const valor = e.target.value.trim();
        
        // Limpa timeout anterior
        if (timeoutBusca) {
            clearTimeout(timeoutBusca);
        }
        
        // Se valor muito curto, fecha sugestões
        if (valor.length < 2) {
            fecharSugestoes();
            return;
        }
        
        // Aguarda 300ms antes de buscar
        timeoutBusca = setTimeout(() => {
            buscarIndicadores(valor);
        }, 300);
    }

    /**
     * Manipula foco no campo
     */
    function handleFocus(e) {
        const valor = e.target.value.trim();
        if (valor.length >= 2 && resultadosAtuais.length > 0) {
            mostrarSugestoes(resultadosAtuais);
        }
    }

    /**
     * Manipula perda de foco
     */
    function handleBlur(e) {
        // Aguarda um pouco para permitir clique nas sugestões
        setTimeout(() => {
            fecharSugestoes();
        }, 200);
    }

    /**
     * Manipula teclas especiais
     */
    function handleKeydown(e) {
        const suggestionsContainer = document.getElementById('indicacaoSuggestions');
        const items = suggestionsContainer.querySelectorAll('.autocomplete-item');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (indexSelecionado < items.length - 1) {
                    indexSelecionado++;
                    atualizarSelecao(items);
                }
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                if (indexSelecionado > 0) {
                    indexSelecionado--;
                    atualizarSelecao(items);
                }
                break;
                
            case 'Enter':
                e.preventDefault();
                if (indexSelecionado >= 0 && items[indexSelecionado]) {
                    selecionarIndicador(resultadosAtuais[indexSelecionado]);
                }
                break;
                
            case 'Escape':
                fecharSugestoes();
                break;
        }
    }

    /**
     * Atualiza item selecionado visualmente
     */
    function atualizarSelecao(items) {
        items.forEach((item, index) => {
            if (index === indexSelecionado) {
                item.classList.add('selected');
                // Scroll para o item se necessário
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }

    /**
     * Busca indicadores no servidor
     */
    function buscarIndicadores(termo) {
        // Mostra loading
        const suggestionsContainer = document.getElementById('indicacaoSuggestions');
        suggestionsContainer.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        suggestionsContainer.style.display = 'block';
        
        // Faz a requisição
        fetch(`../api/buscar_indicadores.php?q=${encodeURIComponent(termo)}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.data.length > 0) {
                    resultadosAtuais = data.data;
                    mostrarSugestoes(data.data);
                } else {
                    // Se não encontrou resultados, mostra os indicadores padrão mais próximos
                    mostrarSugestoesComIndicadoresPadrao(termo);
                }
            })
            .catch(error => {
                console.error('Erro ao buscar indicadores:', error);
                // Em caso de erro, tenta mostrar indicadores padrão
                mostrarSugestoesComIndicadoresPadrao(termo);
            });
    }

    /**
     * Mostra sugestões de indicadores padrão quando não há resultados
     */
    function mostrarSugestoesComIndicadoresPadrao(termo) {
        // Lista de indicadores padrão fornecida
        const indicadoresPadrao = [
            'ST PM SÉRGIO', 'ST BM WESLEY', '2º Ten PM CLAUDIO', 'Maj BM NILTOMAR',
            'Cap PM LUIZ', 'Cap PM DE PAULA', '2º Ten PM ANA PAULA', 'ST PM ISABEL',
            'ST PM IVALDI', '2º Ten BM REGYS', '1º Ten BM LEIDYANA', '2º Ten PM AMARO',
            'ST PM ALÍRIO', 'TC PM JUNE', '2º Ten PM CIRILO', '2º Ten PM GILSON',
            'Cb BM GRANJA', '3º Sgt PM ANOAR', 'Maj BM MEDEIROS', 'Cap PM RODRIGO',
            '1º Ten PM CARDOSO', '2º Ten BM VAZ', '2º Ten BM FRANÇA', '2º Ten PM J. MELO',
            'ST BM PÁDUA', 'ST BM RUI', 'ST PM ADAILMA', 'ST PM AMAURY', 'ST PM F. CARDOSO',
            'ST PM WILIAN', '1º Sgt PM LINDOMAURO', '1º Sgt PM NÉLIA', '1º Sgt PM CLEITON',
            '2º Sgt PM DIONY', '2º Sgt PM PAULO CÉSAR', '2º Sgt PM OTTONI', '2º Sgt PM UELISCLEI',
            '2º Sgt BM TÉRCIO', '2º Sgt PM NIEDSON', '3º Sgt PM WASHINGTON', '3º Sgt BM TADEU',
            'Cb PM LINHARES', 'MAJ PM LASARO', 'Cap PM LUCIMAR', '1º Ten PM DANILLO',
            '1º Ten PM EDUARDO', '2º Ten PM MARCELO', '2º Ten PM CORDEIRO', '2º Ten PM DA MATA',
            'ST PM RESENDE', 'ST BM WELLINGTON', 'ST PM CLAUDE', 'ST BM KASSIA', 'ST PM LEMOS',
            '1º Sgt PM KLEBER NUNES', '1º Sgt PM APARECIDA', '1º Sgt PM NIVALDO',
            '1º Sgt PM MARTINS MENDES', '1º Sgt PM WEBER', '1º Sgt BM LUIZ',
            '2º Sgt PM DE MOURA', '2º Sgt PM EMERSON', '2º Sgt PM BORGES', '3º Sgt PM ELOAR',
            '1º Ten PM PEDROZA', '2º Ten PM DIONE ROCHA', 'ST PM LINDOMAR', 'ST PM FIRMINO',
            '1º Sgt BM ALMIR', '1º Sgt PM TERRA', '1º Sgt PM BERLANDA', '2º Sgt PM CASTALDI',
            '2º Sgt PM DÉLICE', '2º Sgt PM CALASSI', '3º Sgt PM MELO'
        ];
        
        // Filtra indicadores que contenham o termo
        const termoUpper = termo.toUpperCase();
        const sugestoes = indicadoresPadrao
            .filter(nome => nome.toUpperCase().includes(termoUpper))
            .slice(0, 10)
            .map(nome => ({
                value: nome,
                label: nome,
                patente: extrairPatente(nome),
                corporacao: extrairCorporacao(nome)
            }));
        
        if (sugestoes.length > 0) {
            resultadosAtuais = sugestoes;
            mostrarSugestoes(sugestoes);
        } else {
            const suggestionsContainer = document.getElementById('indicacaoSuggestions');
            suggestionsContainer.innerHTML = '<div class="autocomplete-empty">Nenhum indicador encontrado</div>';
        }
    }

    /**
     * Extrai patente do nome
     */
    function extrairPatente(nome) {
        const partes = nome.split(' ');
        const patentes = ['ST', 'Cb', '1º Sgt', '2º Sgt', '3º Sgt', '1º Ten', '2º Ten', 'Cap', 'Maj', 'TC'];
        
        for (let i = 0; i < partes.length - 1; i++) {
            const possivel = partes[i] + (partes[i + 1] === 'Sgt' || partes[i + 1] === 'Ten' ? ' ' + partes[i + 1] : '');
            if (patentes.includes(possivel)) {
                return expandirPatente(possivel);
            }
        }
        
        if (patentes.includes(partes[0])) {
            return expandirPatente(partes[0]);
        }
        
        return '';
    }

    /**
     * Extrai corporação do nome
     */
    function extrairCorporacao(nome) {
        if (nome.includes(' PM ')) {
            return 'Polícia Militar';
        } else if (nome.includes(' BM ')) {
            return 'Bombeiro Militar';
        }
        return '';
    }

    /**
     * Expande abreviação de patente
     */
    function expandirPatente(abrev) {
        const patentes = {
            'ST': 'Subtenente',
            'Cb': 'Cabo',
            '3º Sgt': 'Terceiro-Sargento',
            '2º Sgt': 'Segundo-Sargento',
            '1º Sgt': 'Primeiro-Sargento',
            '2º Ten': 'Segundo-Tenente',
            '1º Ten': 'Primeiro-Tenente',
            'Cap': 'Capitão',
            'Maj': 'Major',
            'MAJ': 'Major',
            'TC': 'Tenente-Coronel'
        };
        
        return patentes[abrev] || abrev;
    }

    /**
     * Mostra sugestões na interface
     */
    function mostrarSugestoes(sugestoes) {
        const suggestionsContainer = document.getElementById('indicacaoSuggestions');
        
        if (sugestoes.length === 0) {
            suggestionsContainer.innerHTML = '<div class="autocomplete-empty">Nenhum indicador encontrado</div>';
            suggestionsContainer.style.display = 'block';
            return;
        }
        
        // Remove duplicatas baseado no nome
        const sugestoesUnicas = [];
        const nomesAdicionados = new Set();
        
        sugestoes.forEach(item => {
            if (!nomesAdicionados.has(item.value.toUpperCase())) {
                sugestoesUnicas.push(item);
                nomesAdicionados.add(item.value.toUpperCase());
            }
        });
        
        // Monta HTML das sugestões
        let html = '';
        sugestoesUnicas.forEach((item, index) => {
            // Mostra apenas o nome principal, sem detalhes extras
            html += `
                <div class="autocomplete-item" data-index="${index}">
                    <div class="autocomplete-main">${item.value}</div>
                    ${item.total_indicacoes > 0 ? 
                        `<div class="autocomplete-details">
                            <span class="autocomplete-indicacoes">${item.total_indicacoes} indicações</span>
                        </div>` : ''}
                </div>
            `;
        });
        
        suggestionsContainer.innerHTML = html;
        suggestionsContainer.style.display = 'block';
        
        // Atualiza array de resultados com sugestões únicas
        resultadosAtuais = sugestoesUnicas;
        
        // Adiciona eventos de clique
        suggestionsContainer.querySelectorAll('.autocomplete-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                selecionarIndicador(sugestoesUnicas[index]);
            });
            
            item.addEventListener('mouseenter', () => {
                indexSelecionado = index;
                atualizarSelecao(suggestionsContainer.querySelectorAll('.autocomplete-item'));
            });
        });
        
        // Reset index selecionado
        indexSelecionado = -1;
    }

    /**
     * Seleciona um indicador
     */
    function selecionarIndicador(indicador) {
        const inputIndicacao = document.getElementById('indicacao');
        inputIndicacao.value = indicador.value;
        indicadorSelecionado = indicador;
        
        // Incrementa contador de indicações (se tiver ID)
        if (indicador.id) {
            incrementarIndicacao(indicador.id);
        }
        
        fecharSugestoes();
        
        // Dispara evento de mudança
        inputIndicacao.dispatchEvent(new Event('change'));
    }

    /**
     * Incrementa contador de indicações
     */
    function incrementarIndicacao(indicadorId) {
        // Envia requisição em background (não bloqueia)
        fetch('../api/incrementar_indicacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: indicadorId })
        }).catch(error => {
            console.error('Erro ao incrementar indicação:', error);
        });
    }

    /**
     * Fecha sugestões
     */
    function fecharSugestoes() {
        const suggestionsContainer = document.getElementById('indicacaoSuggestions');
        suggestionsContainer.style.display = 'none';
        suggestionsContainer.innerHTML = '';
        resultadosAtuais = [];
        indexSelecionado = -1;
    }

    // Inicializa quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutocomplete);
    } else {
        initAutocomplete();
    }

    // Exporta funções para uso global se necessário
    window.AutocompleteIndicacao = {
        init: initAutocomplete,
        buscar: buscarIndicadores,
        fechar: fecharSugestoes
    };

})();