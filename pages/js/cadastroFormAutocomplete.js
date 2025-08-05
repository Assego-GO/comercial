// Variáveis do autocomplete
let indicacaoTimeout = null;
let currentSelectedIndex = -1;
let currentSuggestions = [];

// Inicialização do autocomplete
document.addEventListener('DOMContentLoaded', function () {
    setupIndicacaoAutocomplete();
});

function setupIndicacaoAutocomplete() {
    const input = document.getElementById('indicacao');
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');

    if (!input || !suggestionsContainer) {
        console.warn('Elementos do autocomplete não encontrados');
        return;
    }

    // Event listener para digitação
    input.addEventListener('input', function () {
        const query = this.value.trim();
        currentSelectedIndex = -1;

        if (query.length < 2) {
            hideSuggestions();
            return;
        }

        // Debounce: aguarda 300ms após parar de digitar
        clearTimeout(indicacaoTimeout);
        indicacaoTimeout = setTimeout(() => {
            buscarNomesAssociados(query);
        }, 300);
    });

    // Navegação com teclado
    input.addEventListener('keydown', function (e) {
        const suggestionsVisible = suggestionsContainer.style.display !== 'none';

        if (!suggestionsVisible) return;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                navigateSuggestions(1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                navigateSuggestions(-1);
                break;
            case 'Enter':
                e.preventDefault();
                selectCurrentSuggestion();
                break;
            case 'Escape':
                e.preventDefault();
                hideSuggestions();
                break;
        }
    });

    // Esconde sugestões ao clicar fora
    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            hideSuggestions();
        }
    });

    console.log('✓ Autocomplete de indicação inicializado');
}

function buscarNomesAssociados(query) {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;

    // Mostra loading
    suggestionsContainer.innerHTML = '<div class="autocomplete-loading"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
    suggestionsContainer.style.display = 'block';

    console.log('Buscando nomes para:', query);

    fetch(`../api/buscar_nomes_associados.php?q=${encodeURIComponent(query)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Resposta da busca:', data);

            if (data.status === 'success') {
                mostrarSuggestions(data.data);
            } else {
                mostrarErro(data.message || 'Erro ao buscar nomes');
            }
        })
        .catch(error => {
            console.error('Erro na busca de nomes:', error);
            mostrarErro('Erro de conexão. Tente novamente.');
        });
}

function mostrarSuggestions(nomes) {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;

    currentSuggestions = nomes;
    currentSelectedIndex = -1;

    if (nomes.length === 0) {
        suggestionsContainer.innerHTML = '<div class="autocomplete-no-results">Nenhum nome encontrado</div>';
        suggestionsContainer.style.display = 'block';
        return;
    }

    let html = '';
    nomes.forEach((nome, index) => {
        html += `
            <div class="autocomplete-suggestion" data-index="${index}" onclick="selecionarNome('${nome.replace(/'/g, "\\'")}')">
                ${highlightMatch(nome, document.getElementById('indicacao').value)}
            </div>
        `;
    });

    suggestionsContainer.innerHTML = html;
    suggestionsContainer.style.display = 'block';
    suggestionsContainer.classList.add('show');

    console.log(`✓ ${nomes.length} sugestões exibidas`);
}

function mostrarErro(mensagem) {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;

    suggestionsContainer.innerHTML = `<div class="autocomplete-no-results" style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> ${mensagem}</div>`;
    suggestionsContainer.style.display = 'block';
}

function highlightMatch(text, query) {
    if (!query) return text;

    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<strong style="color: var(--primary);">$1</strong>');
}

function navigateSuggestions(direction) {
    const suggestions = document.querySelectorAll('.autocomplete-suggestion');
    if (suggestions.length === 0) return;

    // Remove seleção atual
    suggestions.forEach(s => s.classList.remove('selected'));

    // Calcula novo índice
    currentSelectedIndex += direction;

    if (currentSelectedIndex < 0) {
        currentSelectedIndex = suggestions.length - 1;
    } else if (currentSelectedIndex >= suggestions.length) {
        currentSelectedIndex = 0;
    }

    // Adiciona seleção
    suggestions[currentSelectedIndex].classList.add('selected');

    // Scroll se necessário
    suggestions[currentSelectedIndex].scrollIntoView({
        block: 'nearest'
    });
}

function selectCurrentSuggestion() {
    if (currentSelectedIndex >= 0 && currentSuggestions[currentSelectedIndex]) {
        selecionarNome(currentSuggestions[currentSelectedIndex]);
    }
}

function selecionarNome(nome) {
    const input = document.getElementById('indicacao');
    if (!input) return;

    input.value = nome;
    hideSuggestions();

    // Remove classe de erro se houver
    input.classList.remove('error');

    console.log('✓ Nome selecionado:', nome);
}

function hideSuggestions() {
    const suggestionsContainer = document.getElementById('indicacaoSuggestions');
    if (!suggestionsContainer) return;

    suggestionsContainer.style.display = 'none';
    suggestionsContainer.classList.remove('show');
    currentSelectedIndex = -1;
    currentSuggestions = [];
}

console.log('✓ Script de autocomplete carregado');