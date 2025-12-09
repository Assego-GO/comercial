// Configuração inicial
console.log('=== INICIANDO SISTEMA ASSEGO ===');
console.log('jQuery versão:', jQuery.fn.jquery);

// Inicializa AOS com delay
setTimeout(() => {
    AOS.init({
        duration: 800,
        once: true
    });
}, 100);

// Variáveis globais
let todosAssociados = [];
let associadosFiltrados = [];
let carregamentoIniciado = false;
let carregamentoCompleto = false;
let imagensCarregadas = new Set();

// Variável global para armazenar o associado atual
let associadoAtual = null;

// Variável para controlar se os event listeners do modal já foram adicionados
let modalEventListenersAdded = false;

// Flag para prevenir múltiplas execuções de fecharModal
let modalFechando = false;

// Variáveis de paginação
let paginaAtual = 1;
let registrosPorPagina = 25;
let totalPaginas = 1;

// Loading functions
function showLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('active');
        console.log('Loading ativado');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        console.log('Loading desativado');
    }
}

// Função para obter URL da foto
function getFotoUrl(cpf) {
    if (!cpf) return null;
    const cpfNormalizado = normalizarCPF(cpf);
    return `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;
}

// Função para pré-carregar imagem
function preloadImage(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(url);
        img.onerror = () => reject(url);
        img.src = url;
    });
}

// Formata data
function formatarData(dataStr) {
    if (!dataStr || dataStr === "0000-00-00" || dataStr === "") return "-";
    try {
        const [ano, mes, dia] = dataStr.split("-");
        return `${dia}/${mes}/${ano}`;
    } catch (e) {
        return "-";
    }
}

// Formata CPF
function formatarCPF(cpf) {
    if (!cpf) return "-";
    cpf = cpf.toString().replace(/\D/g, '');
    cpf = cpf.padStart(11, '0');
    if (cpf.length !== 11) return cpf;
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
}

// Função para garantir CPF com 11 dígitos
function normalizarCPF(cpf) {
    if (!cpf) return '';
    cpf = cpf.toString().replace(/\D/g, '');
    return cpf.padStart(11, '0');
}

// Formata telefone
function formatarTelefone(telefone) {
    if (!telefone) return "-";
    telefone = telefone.toString().replace(/\D/g, '');
    if (telefone.length === 11) {
        return telefone.replace(/(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
    } else if (telefone.length === 10) {
        return telefone.replace(/(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
    } else if (telefone.length === 9) {
        return telefone.replace(/(\d{5})(\d{4})/, "$1-$2");
    } else if (telefone.length === 8) {
        return telefone.replace(/(\d{4})(\d{4})/, "$1-$2");
    }
    return telefone;
}

// NOVA FUNÇÃO: Formatar status de doador
function formatarDoador(doador) {
    if (doador === null || doador === undefined || doador === '') return '-';
    // Converte para booleano e exibe como texto
    const isDoador = Boolean(parseInt(doador));
    return isDoador ? 'Sim' : 'Não';
}

function carregarAssociados() {
    if (carregamentoIniciado || carregamentoCompleto) {
        console.log('Carregamento já realizado ou em andamento, ignorando nova chamada');
        return;
    }

    carregamentoIniciado = true;
    console.log('🚀 Iniciando carregamento OTIMIZADO...');
    showLoading();

    const startTime = Date.now();

    const timeoutId = setTimeout(() => {
        hideLoading();
        carregamentoIniciado = false;
        console.error('TIMEOUT: Requisição demorou mais de 15 segundos');
        alert('Tempo esgotado ao carregar dados. Tentando carregamento alternativo...');
        carregarTodosAssociadosFallback();
    }, 15000); // Reduzido para 15s

    // 🚀 Requisição otimizada - apenas 100 registros iniciais
    $.ajax({
        url: '../api/carregar_associados.php',
        method: 'GET',
        data: {
            page: 1,
            limit: 100,
            load_type: 'initial'
        },
        dataType: 'json',
        cache: false,
        timeout: 12000,
        beforeSend: function () {
            console.log('Enviando requisição OTIMIZADA para:', this.url);
        },
        success: function (response) {
            clearTimeout(timeoutId);
            const elapsed = Date.now() - startTime;
            console.log(`✅ Carregamento otimizado: ${elapsed}ms`);
            console.log(`📊 ${response.total} registros de ${response.total_banco} total`);

            if (response && response.status === 'success') {
                todosAssociados = Array.isArray(response.dados) ? response.dados : [];

                // Remove duplicatas baseado no ID
                const idsUnicos = new Set();
                todosAssociados = todosAssociados.filter(associado => {
                    if (idsUnicos.has(associado.id)) {
                        return false;
                    }
                    idsUnicos.add(associado.id);
                    return true;
                });

                // Ordena por ID decrescente
                todosAssociados.sort((a, b) => b.id - a.id);
                associadosFiltrados = [...todosAssociados];

                // 🚀 Preenche filtros usando dados do servidor (mais rápido)
                preencherFiltrosOtimizado(response);

                // Calcula paginação
                calcularPaginacao();
                renderizarPagina();

                // Marca como carregamento completo
                carregamentoCompleto = true;

                console.log('✅ Sistema pronto!', todosAssociados.length, 'registros em cache');

                // 🚀 Carrega mais dados em background se houver
                if (response.has_next && todosAssociados.length < 500) {
                    setTimeout(() => {
                        carregarProximoLoteBackground(response);
                    }, 1000);
                }

                if (response.aviso) {
                    console.warn(response.aviso);
                }
            } else {
                console.error('Resposta com erro:', response);
                alert('Erro ao carregar dados: ' + (response.message || 'Erro desconhecido'));
                renderizarTabela([]);
            }
        },
        error: function (xhr, status, error) {
            clearTimeout(timeoutId);
            const elapsed = Date.now() - startTime;
            console.error(`Erro após ${elapsed}ms:`, {
                status: xhr.status,
                statusText: xhr.statusText,
                error: error
            });

            // Fallback: tenta carregar da forma antiga
            console.log('🔄 Tentando carregamento fallback...');
            carregarTodosAssociadosFallback();
        },
        complete: function () {
            clearTimeout(timeoutId);
            hideLoading();
            carregamentoIniciado = false;
            console.log('Carregamento finalizado');
        }
    });
}

function preencherFiltrosOtimizado(response) {
    console.log('🔧 Preenchendo filtros otimizados...');

    const selectCorporacao = document.getElementById('filterCorporacao');
    const selectPatente = document.getElementById('filterPatente');
    const selectTipoAssociado = document.getElementById('filterTipoAssociado'); // NOVO

    if (!selectCorporacao || !selectPatente) return;

    selectCorporacao.innerHTML = '<option value="">Todos</option>';
    selectPatente.innerHTML = '<option value="">Todos</option>';

    // NOVO: Limpa e preenche Tipo Associado
    if (selectTipoAssociado) {
        selectTipoAssociado.innerHTML = '<option value="">Todos</option>';
    }

    // 🚀 Usa dados do servidor se disponíveis
    let corporacoes = response.corporacoes_unicas || [];
    let patentes = response.patentes_unicas || [];
    let tiposAssociado = response.tipos_associado_unicos || []; // NOVO

    // Fallback: extrai dos dados carregados se servidor não enviou
    if (corporacoes.length === 0) {
        corporacoes = [...new Set(todosAssociados
            .map(a => a.corporacao)
            .filter(c => c && c.trim() !== '')
        )].sort();
    }

    if (patentes.length === 0) {
        patentes = [...new Set(todosAssociados
            .map(a => a.patente)
            .filter(p => p && p.trim() !== '')
        )].sort();
    }

    // NOVO: Fallback para tipos de associado
    if (tiposAssociado.length === 0) {
        tiposAssociado = [...new Set(todosAssociados
            .map(a => a.tipo_associado)
            .filter(t => t && t.trim() !== '')
        )].sort();
    }

    // Preenche selects de corporações
    corporacoes.forEach(corp => {
        const option = document.createElement('option');
        option.value = corp;
        option.textContent = corp;
        selectCorporacao.appendChild(option);
    });

    // Preenche selects de patentes
    patentes.forEach(pat => {
        const option = document.createElement('option');
        option.value = pat;
        option.textContent = pat;
        selectPatente.appendChild(option);
    });

    // NOVO: Preenche select de tipos de associado
    if (selectTipoAssociado) {
        tiposAssociado.forEach(tipo => {
            const option = document.createElement('option');
            option.value = tipo;
            option.textContent = tipo;
            selectTipoAssociado.appendChild(option);
        });
    }

    console.log(`✅ Filtros otimizados: ${corporacoes.length} corporações, ${patentes.length} patentes, ${tiposAssociado.length} tipos de associado`);
}

// Carrega próximo lote em background
function carregarProximoLoteBackground(responseAnterior) {
    if (!responseAnterior.has_next || window.isLoadingMore) {
        return;
    }

    window.isLoadingMore = true;
    console.log('🔄 Carregando mais dados em background...');

    const nextPage = responseAnterior.page + 1;

    $.ajax({
        url: '../api/carregar_associados.php',
        method: 'GET',
        data: {
            page: nextPage,
            limit: 100,
            load_type: 'page'
        },
        dataType: 'json',
        cache: false,
        timeout: 10000,
        success: function (response) {
            if (response && response.status === 'success' && response.dados) {
                console.log(`📦 +${response.dados.length} registros carregados em background`);

                // Adiciona novos dados
                const novosRegistros = response.dados.filter(novo =>
                    !todosAssociados.some(existente => existente.id === novo.id)
                );

                todosAssociados = [...todosAssociados, ...novosRegistros];

                // Reaplica filtros se necessário
                if (temFiltrosAtivos()) {
                    aplicarFiltros();
                } else {
                    associadosFiltrados = [...todosAssociados];
                    calcularPaginacao();
                }

                console.log(`✅ Total em cache: ${todosAssociados.length} registros`);

                // Continua carregando se ainda houver dados
                if (response.has_next && todosAssociados.length < 1000) {
                    setTimeout(() => {
                        carregarProximoLoteBackground(response);
                    }, 2000);
                }
            }
        },
        error: function (xhr, status, error) {
            console.warn('⚠️ Erro ao carregar lote adicional:', error);
        },
        complete: function () {
            window.isLoadingMore = false;
        }
    });
}

// Função CORRIGIDA para verificar se há filtros ativos
function temFiltrosAtivos() {
    const search = document.getElementById('searchInput')?.value || '';
    const situacao = document.getElementById('filterSituacao')?.value || '';
    const tipoAssociado = document.getElementById('filterTipoAssociado')?.value || ''; // NOVO
    const corporacao = document.getElementById('filterCorporacao')?.value || '';
    const patente = document.getElementById('filterPatente')?.value || '';

    return search || situacao || tipoAssociado || corporacao || patente;
}

// Fallback para carregamento completo (compatibilidade)
function carregarTodosAssociadosFallback() {
    console.log('🔄 Fallback: carregando da forma tradicional...');

    $.ajax({
        url: '../api/carregar_associados.php',
        method: 'GET',
        data: { load_type: 'all' },
        dataType: 'json',
        cache: false,
        timeout: 30000,
        success: function (response) {
            if (response && response.status === 'success') {
                todosAssociados = response.dados || [];
                associadosFiltrados = [...todosAssociados];

                preencherFiltros(); // Usa a função original
                calcularPaginacao();
                renderizarPagina();

                console.log(`✅ Fallback completo: ${todosAssociados.length} registros`);
                carregamentoCompleto = true;
            }
        },
        error: function () {
            console.error('❌ Fallback também falhou');
            renderizarTabela([]);
            alert('Erro crítico ao carregar dados. Recarregue a página.');
        }
    });
}

// Preenche os filtros dinâmicos
function preencherFiltros() {
    console.log('Preenchendo filtros...');

    const selectCorporacao = document.getElementById('filterCorporacao');
    const selectPatente = document.getElementById('filterPatente');
    const selectTipoAssociado = document.getElementById('filterTipoAssociado'); // NOVO

    selectCorporacao.innerHTML = '<option value="">Todos</option>';
    selectPatente.innerHTML = '<option value="">Todos</option>';

    // NOVO: Limpa Tipo Associado
    if (selectTipoAssociado) {
        selectTipoAssociado.innerHTML = '<option value="">Todos</option>';
    }

    // Corporações
    const corporacoes = [...new Set(todosAssociados
        .map(a => a.corporacao)
        .filter(c => c && c.trim() !== '')
    )].sort();

    corporacoes.forEach(corp => {
        const option = document.createElement('option');
        option.value = corp;
        option.textContent = corp;
        selectCorporacao.appendChild(option);
    });

    // Patentes
    const patentes = [...new Set(todosAssociados
        .map(a => a.patente)
        .filter(p => p && p.trim() !== '')
    )].sort();

    patentes.forEach(pat => {
        const option = document.createElement('option');
        option.value = pat;
        option.textContent = pat;
        selectPatente.appendChild(option);
    });

    // NOVO: Tipos de Associado
    if (selectTipoAssociado) {
        const tiposAssociado = [...new Set(todosAssociados
            .map(a => a.tipo_associado)
            .filter(t => t && t.trim() !== '')
        )].sort();

        tiposAssociado.forEach(tipo => {
            const option = document.createElement('option');
            option.value = tipo;
            option.textContent = tipo;
            selectTipoAssociado.appendChild(option);
        });
    }

    console.log(`Filtros preenchidos: ${corporacoes.length} corporações, ${patentes.length} patentes`);
}

// Calcula paginação
function calcularPaginacao() {
    totalPaginas = Math.ceil(associadosFiltrados.length / registrosPorPagina);
    if (paginaAtual > totalPaginas) {
        paginaAtual = 1;
    }
    atualizarControlesPaginacao();
}

// Atualiza controles de paginação
function atualizarControlesPaginacao() {
    document.getElementById('currentPage').textContent = paginaAtual;
    document.getElementById('totalPages').textContent = totalPaginas;
    document.getElementById('totalCount').textContent = associadosFiltrados.length;

    document.getElementById('firstPage').disabled = paginaAtual === 1;
    document.getElementById('prevPage').disabled = paginaAtual === 1;
    document.getElementById('nextPage').disabled = paginaAtual === totalPaginas;
    document.getElementById('lastPage').disabled = paginaAtual === totalPaginas;

    const pageNumbers = document.getElementById('pageNumbers');
    pageNumbers.innerHTML = '';

    let startPage = Math.max(1, paginaAtual - 2);
    let endPage = Math.min(totalPaginas, paginaAtual + 2);

    for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (i === paginaAtual ? ' active' : '');
        btn.textContent = i;
        btn.onclick = () => irParaPagina(i);
        pageNumbers.appendChild(btn);
    }
}

// Renderiza página atual
function renderizarPagina() {
    const inicio = (paginaAtual - 1) * registrosPorPagina;
    const fim = inicio + registrosPorPagina;
    const dadosPagina = associadosFiltrados.slice(inicio, fim);

    renderizarTabela(dadosPagina);

    const mostrando = Math.min(registrosPorPagina, dadosPagina.length);
    document.getElementById('showingCount').textContent =
        `${inicio + 1}-${inicio + mostrando}`;
}

// Navegar entre páginas
function irParaPagina(pagina) {
    paginaAtual = pagina;
    renderizarPagina();
    atualizarControlesPaginacao();
}

// Renderiza tabela
function renderizarTabela(dados) {
    console.log(`📊 Renderizando ${dados.length} registros...`);
    console.log('📋 Primeiros dados a renderizar:', dados.slice(0, 2));
    
    const tbody = document.getElementById('tableBody');

    if (!tbody) {
        console.error('❌ Elemento tableBody não encontrado!');
        return;
    }

    tbody.innerHTML = '';

    if (dados.length === 0) {
        console.log('⚠️ Nenhum dado para renderizar');
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-5">
                    <div class="d-flex flex-column align-items-center">
                        <i class="fas fa-inbox fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                        <p class="text-muted mb-0">Nenhum associado encontrado</p>
                        <small class="text-muted">Tente ajustar os filtros de busca</small>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    dados.forEach(associado => {
        const situacaoBadge = associado.situacao === 'Filiado'
            ? '<span class="status-badge active"><i class="fas fa-check-circle"></i> Filiado</span>'
            : '<span class="status-badge inactive"><i class="fas fa-times-circle"></i> Desfiliado</span>';

        const row = document.createElement('tr');
        row.onclick = (e) => {
            if (!e.target.closest('.btn-icon')) {
                visualizarAssociado(associado.id);
            }
        };

        let fotoHtml = `<span>${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}</span>`;

        if (associado.cpf) {
            const cpfNormalizado = normalizarCPF(associado.cpf);
            const fotoUrl = associado.foto
                ? `../${associado.foto}`
                : `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;

            fotoHtml = `
                <img src="${fotoUrl}" 
                     alt="${associado.nome}"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                     onload="this.style.display='block'; this.nextElementSibling.style.display='none';">
                <span style="display:none;">${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}</span>
            `;
        }

        row.innerHTML = `
            <td>
                <div class="table-avatar">
                    ${fotoHtml}
                </div>
            </td>
            <td>
                <span class="fw-semibold">${associado.nome || 'Sem nome'}</span>
                <br>
                <small class="text-muted">Matrícula: ${associado.id}</small>
            </td>
            <td>${formatarCPF(associado.cpf)}</td>
            <td>${associado.rg || '-'}</td>
            <td>${situacaoBadge}</td>
            <td>${associado.corporacao || '-'}</td>
            <td>${associado.patente || '-'}</td>
            <td>${formatarData(associado.data_filiacao)}</td>
            <td>
                <div class="action-buttons-table">
                    ${permissoesUsuario.podeVisualizar ? `
                        <button class="btn-icon view" onclick="visualizarAssociado(${associado.id})" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </button>
                    ` : ''}
                    
                    ${associado.telefone && (permissoesUsuario.podeEditarContato || permissoesUsuario.podeEditarCompleto) ? `
                        <button class="btn-icon whatsapp" onclick="abrirWhatsApp('${associado.telefone}')" title="WhatsApp" style="background: #25D366;">
                            <i class="fab fa-whatsapp" style="color: white;"></i>
                        </button>
                    ` : ''}
                    
                    ${permissoesUsuario.podeExcluir ? `
                        <button class="btn-icon delete" onclick="excluirAssociado(${associado.id})" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    console.log(`✅ Renderização concluída: ${dados.length} linhas adicionadas à tabela`);
}

// Função UNIFICADA para aplicar filtros (incluindo busca local)
function aplicarFiltros() {
    console.log('🔍 Aplicando filtros...');

    const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
    const filterSituacao = document.getElementById('filterSituacao').value;
    const filterTipoAssociado = document.getElementById('filterTipoAssociado')?.value || '';
    const filterCorporacao = document.getElementById('filterCorporacao').value;
    const filterPatente = document.getElementById('filterPatente').value;

    // Define base de dados: se tem resultados do servidor, usa eles. Senão, usa todosAssociados
    let dadosBase = resultadosServidor && resultadosServidor.length > 0 ? resultadosServidor : todosAssociados;
    
    console.log(`📊 Base de dados: ${dadosBase.length} registros (${resultadosServidor ? 'servidor' : 'local'})`);

    associadosFiltrados = dadosBase.filter(associado => {
        // Filtro de busca LOCAL (só aplica se não veio do servidor)
        const matchSearch = !searchTerm || resultadosServidor ||
            (associado.nome && associado.nome.toLowerCase().includes(searchTerm)) ||
            (associado.cpf && associado.cpf.replace(/\D/g, '').includes(searchTerm.replace(/\D/g, ''))) ||
            (associado.rg && associado.rg.includes(searchTerm)) ||
            (associado.telefone && associado.telefone.replace(/\D/g, '').includes(searchTerm.replace(/\D/g, '')));

        // Outros filtros
        const matchSituacao = !filterSituacao || associado.situacao === filterSituacao;
        const matchTipoAssociado = !filterTipoAssociado || associado.tipo_associado === filterTipoAssociado;
        const matchCorporacao = !filterCorporacao || associado.corporacao === filterCorporacao;
        const matchPatente = !filterPatente || associado.patente === filterPatente;

        return matchSearch && matchSituacao && matchTipoAssociado && matchCorporacao && matchPatente;
    });

    console.log(`✅ Filtros aplicados: ${associadosFiltrados.length} de ${dadosBase.length} registros`);
    console.log(`📋 Primeiros resultados:`, associadosFiltrados.slice(0, 3));

    paginaAtual = 1;
    calcularPaginacao();
    renderizarPagina();
}

// Limpa filtros
function limparFiltros() {
    console.log('Limpando filtros...');

    document.getElementById('searchInput').value = '';
    document.getElementById('filterSituacao').value = '';
    document.getElementById('filterCorporacao').value = '';
    document.getElementById('filterPatente').value = '';

    // NOVO: Limpa filtro de tipo associado
    const filterTipoAssociado = document.getElementById('filterTipoAssociado');
    if (filterTipoAssociado) {
        filterTipoAssociado.value = '';
    }

    associadosFiltrados = [...todosAssociados];
    paginaAtual = 1;
    calcularPaginacao();
    renderizarPagina();
}

// Função para visualizar associado - VERSÃO CORRIGIDA
function visualizarAssociado(id) {
    console.log('👁️ Visualizando associado:', id);

    // NOVO: Se o modal já está aberto, fecha ele primeiro e aguarda
    const modal = document.getElementById('modalAssociado');
    if (modal.classList.contains('show')) {
        console.log('🔄 Modal já aberto, fechando para reabrir...');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        
        // Aguarda a animação de fechamento antes de abrir novamente
        setTimeout(() => {
            visualizarAssociadoInterno(id);
        }, 300); // Tempo da animação CSS
        return;
    }
    
    // Se modal não está aberto, abre normalmente
    visualizarAssociadoInterno(id);
}

// Função interna separada para evitar repetição de código
function visualizarAssociadoInterno(id) {
    console.log('📋 Carregando associado:', id);
    
    // CORREÇÃO: Procurar primeiro em associadosFiltrados (resultados da busca)
    // depois em todosAssociados (cache local)
    let associado = associadosFiltrados.find(a => a.id == id) ||
        todosAssociados.find(a => a.id == id);

    if (!associado) {
        console.log('📋 Associado não encontrado localmente, buscando direto da API...');

        // Se não encontrou localmente, busca direto da API
        const modal = document.getElementById('modalAssociado');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        document.getElementById('overview-tab').innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem;">
                <div class="loading-spinner" style="margin-bottom: 1rem;"></div>
                <p style="color: var(--gray-500);">Carregando detalhes...</p>
            </div>
        `;

        // Buscar direto da API sem precisar do cache local
        carregarDetalhesAssociado(id).then(detalhes => {
            if (detalhes) {
                // Criar objeto associado completo com os dados da API
                associado = detalhes;
                associado.detalhes_carregados = true;

                // Adicionar ao cache local para futuras consultas
                const existeNoCache = todosAssociados.find(a => a.id == id);
                if (!existeNoCache) {
                    todosAssociados.push(associado);
                }

                console.log('✅ Associado carregado da API:', associado);
                abrirModalAssociadoCompleto(associado);
            } else {
                alert('Associado não encontrado no servidor!');
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }).catch(error => {
            console.error('❌ Erro ao carregar detalhes:', error);
            alert('Erro ao carregar dados do associado');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        });

        return;
    }

    // Se não tem detalhes carregados, carrega via API
    if (!associado.detalhes_carregados) {
        console.log('📋 Carregando detalhes completos...');

        // Mostra loading no modal
        const modal = document.getElementById('modalAssociado');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        // Mostra loading
        document.getElementById('overview-tab').innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem;">
                <div class="loading-spinner" style="margin-bottom: 1rem;"></div>
                <p style="color: var(--gray-500);">Carregando detalhes completos...</p>
            </div>
        `;

        carregarDetalhesAssociado(id).then(detalhes => {
            if (detalhes) {
                // Merge dos detalhes no associado
                associado = { ...associado, ...detalhes, detalhes_carregados: true };

                // Atualiza no array principal
                const index = todosAssociados.findIndex(a => a.id == id);
                if (index !== -1) {
                    todosAssociados[index] = associado;
                }

                // Atualiza também em associadosFiltrados se estiver lá
                const indexFiltrados = associadosFiltrados.findIndex(a => a.id == id);
                if (indexFiltrados !== -1) {
                    associadosFiltrados[indexFiltrados] = associado;
                }

                console.log('✅ Detalhes carregados e merged');
            }

            abrirModalAssociadoCompleto(associado);
        }).catch(error => {
            console.error('❌ Erro ao carregar detalhes:', error);
            // Mesmo com erro, tenta abrir com os dados básicos
            abrirModalAssociadoCompleto(associado);
        });
    } else {
        // Tem detalhes, abre direto
        const modal = document.getElementById('modalAssociado');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        abrirModalAssociadoCompleto(associado);
    }
}

// Função para carregar detalhes do associado
function carregarDetalhesAssociado(id) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: '../api/carregar_detalhes_associado.php',
            method: 'GET',
            data: { id: id },
            dataType: 'json',
            timeout: 10000,
            success: function (response) {
                // DEBUG COMPLETO - Ver TUDO que está vindo
                console.log('📦 Resposta completa da API:', response);
                console.log('📦 Tipo da resposta:', typeof response);
                console.log('📦 Status:', response?.status);

                if (response && response.status === 'success') {
                    console.log('✅ Detalhes carregados para associado', id);

                    // DEBUG dos dados
                    if (response.dados) {
                        console.log('📋 Dados recebidos:', response.dados);
                        console.log('🏠 Endereço:', {
                            cep: response.dados.cep,
                            endereco: response.dados.endereco,
                            bairro: response.dados.bairro,
                            cidade: response.dados.cidade,
                            numero: response.dados.numero,
                            complemento: response.dados.complemento
                        });
                    }

                    resolve(response.dados);
                } else {
                    console.warn('⚠️ Resposta inválida ao carregar detalhes');
                    console.warn('❌ Resposta recebida:', response);
                    resolve(null);
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ Erro ao carregar detalhes:', error);
                console.error('❌ Status:', xhr.status);
                console.error('❌ Response Text:', xhr.responseText);

                // Tentar parsear a resposta mesmo com erro
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    console.error('❌ Erro parseado:', errorResponse);
                } catch (e) {
                    console.error('❌ Resposta não é JSON:', xhr.responseText);
                }

                reject(error);
            }
        });
    });
}

// Função principal para abrir modal do associado
function abrirModalAssociadoCompleto(associado) {
    // CORREÇÃO: Definir associadoAtual GLOBALMENTE
    associadoAtual = associado;

    // Resetar dados de observações ao abrir novo modal
    resetarObservacoes();

    // Atualiza o header do modal
    atualizarHeaderModal(associado);

    // Preenche as tabs
    preencherTabVisaoGeral(associado);
    preencherTabMilitar(associado);
    preencherTabFinanceiro(associado);
    preencherTabContato(associado);
    preencherTabDependentes(associado);
    preencherTabDocumentos(associado);

    // Carregar apenas o contador de observações
    carregarContadorObservacoes(associado.id);

    // Modal já está aberto, apenas força active na tab overview
    // abrirTab('overview');
}

// Função para resetar observações ao trocar de associado
function resetarObservacoes() {
    // Resetar variáveis globais
    observacoesData = [];
    currentObservacaoPage = 1;
    currentFilterObs = 'all';
    currentAssociadoIdObs = null;

    // Esconder badge
    const badge = document.getElementById('observacoesCountBadge');
    if (badge) {
        badge.style.display = 'none';
        badge.textContent = '0';
    }

    // Limpar container de observações
    const container = document.getElementById('observacoesContainer');
    if (container) {
        container.innerHTML = '';
    }

    // Resetar busca
    const searchInput = document.getElementById('searchObservacoes');
    if (searchInput) {
        searchInput.value = '';
    }

    // Resetar filtros
    const filterButtons = document.querySelectorAll('.observacoes-filter-buttons .filter-btn');
    filterButtons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === 'all') {
            btn.classList.add('active');
        }
    });
}

// Função para carregar apenas o contador de observações (mais rápido)
function carregarContadorObservacoes(associadoId) {
    if (!associadoId) return;

    // Fazer requisição leve apenas para contar observações
    $.ajax({
        url: '../api/observacoes/contar.php',
        method: 'GET',
        data: { associado_id: associadoId },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                const count = response.data.total || 0;
                const badge = document.getElementById('observacoesCountBadge');

                if (badge) {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        },
        error: function () {
            // Em caso de erro, tenta carregar pela API completa como fallback
            $.ajax({
                url: '../api/observacoes/listar.php',
                method: 'GET',
                data: { associado_id: associadoId },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        const count = (response.data || []).length;
                        const badge = document.getElementById('observacoesCountBadge');

                        if (badge) {
                            if (count > 0) {
                                badge.textContent = count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                }
            });
        }
    });
}

// Atualiza o header do modal
function atualizarHeaderModal(associado) {
    // Nome e ID
    document.getElementById('modalNome').textContent = associado.nome || 'Sem nome';
    document.getElementById('modalId').textContent = `Matrícula: ${associado.id}`;

    // Data de filiação
    document.getElementById('modalDataFiliacao').textContent =
        formatarData(associado.data_filiacao) !== '-'
            ? `Desde ${formatarData(associado.data_filiacao)}`
            : 'Data não informada';

    // Status
    const statusPill = document.getElementById('modalStatusPill');
    if (associado.situacao === 'Filiado') {
        statusPill.innerHTML = `
            <div class="status-pill active">
                <i class="fas fa-check-circle"></i>
                Ativo
            </div>
        `;
    } else {
        statusPill.innerHTML = `
            <div class="status-pill inactive">
                <i class="fas fa-times-circle"></i>
                Inativo
            </div>
        `;
    }

    // Avatar
    const modalAvatar = document.getElementById('modalAvatarHeader');
    if (associado.cpf) {
        const cpfNormalizado = normalizarCPF(associado.cpf);
        const fotoUrl = associado.foto
            ? `../${associado.foto}`
            : `https://assegonaopara.com.br/QRV/images/fotos/${cpfNormalizado}.jpg`;

        modalAvatar.innerHTML = `
            <img src="${fotoUrl}" 
                 alt="${associado.nome}"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                 onload="this.style.display='block'; this.nextElementSibling.style.display='none';">
            <div class="modal-avatar-header-placeholder" style="display:none;">
                ${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}
            </div>
        `;
    } else {
        modalAvatar.innerHTML = `
            <div class="modal-avatar-header-placeholder">
                ${associado.nome ? associado.nome.charAt(0).toUpperCase() : '?'}
            </div>
        `;
    }
}

// Preenche tab Visão Geral
function preencherTabVisaoGeral(associado) {
    const overviewTab = document.getElementById('overview-tab');

    // Calcula idade
    let idade = '-';
    if (associado.nasc && associado.nasc !== '0000-00-00') {
        const hoje = new Date();
        const nascimento = new Date(associado.nasc);
        idade = Math.floor((hoje - nascimento) / (365.25 * 24 * 60 * 60 * 1000));
        idade = idade + ' anos';
    }

    overviewTab.innerHTML = `
        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-item">
                <div class="stat-value">${associado.total_servicos || 0}</div>
                <div class="stat-label">Serviços Ativos</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${associado.total_dependentes || 0}</div>
                <div class="stat-label">Dependentes</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${associado.total_documentos || 0}</div>
                <div class="stat-label">Documentos</div>
            </div>
        </div>
        
        <!-- Overview Grid -->
        <div class="overview-grid">
            <!-- Dados Pessoais -->
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon blue">
                        <i class="fas fa-user"></i>
                    </div>
                    <h4 class="overview-card-title">Dados Pessoais</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Nome Completo</span>
                        <span class="overview-value">${associado.nome || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">CPF</span>
                        <span class="overview-value">${formatarCPF(associado.cpf)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">RG</span>
                        <span class="overview-value">${associado.rg || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Data de Nascimento</span>
                        <span class="overview-value">${formatarData(associado.nasc)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Idade</span>
                        <span class="overview-value">${idade}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Sexo</span>
                        <span class="overview-value">${associado.sexo === 'M' ? 'Masculino' : associado.sexo === 'F' ? 'Feminino' : '-'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Informações de Filiação -->
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon green">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <h4 class="overview-card-title">Informações de Filiação</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Situação</span>
                        <span class="overview-value">${associado.situacao || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Data de Filiação</span>
                        <span class="overview-value">${formatarData(associado.data_filiacao)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Data de Desfiliação</span>
                        <span class="overview-value">${formatarData(associado.data_desfiliacao)}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Escolaridade</span>
                        <span class="overview-value">${associado.escolaridade || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Estado Civil</span>
                        <span class="overview-value">${associado.estadoCivil || '-'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Informações Extras -->
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon purple">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h4 class="overview-card-title">Informações Adicionais</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Indicação</span>
                        <span class="overview-value">${associado.indicacao || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Tipo de Associado</span>
                        <span class="overview-value">${associado.tipoAssociado || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Situação Financeira</span>
                        <span class="overview-value">${associado.situacaoFinanceira || '-'}</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- NOVA SEÇÃO: Observações Recentes -->
        <div class="observacoes-overview-section" style="margin-top: 2rem; padding: 1rem 2rem; border-top: 1px solid #e5e7eb;">
            <div class="observacoes-overview-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e5e7eb;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #7c3aed, #5b21b6); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-sticky-note"></i>
                    </div>
                    <h4 style="margin: 0; color: #1f2937; font-weight: 600;">Observações Recentes</h4>
                </div>
                <button onclick="abrirModalNovaObservacao()" style="background: #7c3aed; color: white; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Nova Observação">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div id="overviewObservacoes" style="background: #f9fafb; border-radius: 8px; padding: 1rem; min-height: 100px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 2rem; color: #6b7280;">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Carregando observações...</span>
                </div>
            </div>
        </div>
    `;

    // CORREÇÃO PRINCIPAL: Carregar observações imediatamente após criar o HTML
    if (associado && associado.id) {
        console.log('🔄 Carregando observações para visão geral do associado:', associado.id);
        carregarObservacoesVisaoGeral(associado.id);
    }
}

// Preenche tab Militar
function preencherTabMilitar(associado) {
    const militarTab = document.getElementById('militar-tab');

    militarTab.innerHTML = `
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="section-title">Informações Militares</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Corporação</span>
                    <span class="detail-value">${associado.corporacao || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Patente</span>
                    <span class="detail-value">${associado.patente || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Categoria</span>
                    <span class="detail-value">${associado.categoria || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Lotação</span>
                    <span class="detail-value">${associado.lotacao || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Unidade</span>
                    <span class="detail-value">${associado.unidade || '-'}</span>
                </div>
            </div>
        </div>
    `;
}

// Preenche tab Financeiro
function preencherTabFinanceiro(associado) {
    const financeiroTab = document.getElementById('financeiro-tab');

    // Mostra loading enquanto carrega
    financeiroTab.innerHTML = `
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem; color: var(--gray-500);">
            <div class="loading-spinner" style="margin-bottom: 1rem;"></div>
            <p>Carregando informações financeiras...</p>
        </div>
    `;

    // Busca dados dos serviços do associado
    buscarServicosAssociado(associado.id)
        .then(dadosServicos => {
            console.log('Dados dos serviços:', dadosServicos);

            let servicosHtml = '';
            let historicoHtml = '';
            let valorTotalMensal = 0;
            let tipoAssociadoServico = 'Não definido';
            let servicosAtivos = [];
            let resumoServicos = 'Nenhum serviço ativo';

            if (dadosServicos && dadosServicos.status === 'success' && dadosServicos.data) {
                const dados = dadosServicos.data;
                valorTotalMensal = dados.valor_total_mensal || 0;
                tipoAssociadoServico = dados.tipo_associado_servico || 'Não definido';

                // Analisa os serviços contratados
                if (dados.servicos.social) {
                    servicosAtivos.push('Social');
                }
                if (dados.servicos.juridico) {
                    servicosAtivos.push('Jurídico');
                }

                // Define resumo dos serviços
                if (servicosAtivos.length === 2) {
                    resumoServicos = 'Social + Jurídico';
                } else if (servicosAtivos.includes('Social')) {
                    resumoServicos = 'Apenas Social';
                } else if (servicosAtivos.includes('Jurídico')) {
                    resumoServicos = 'Apenas Jurídico';
                }

                // Gera HTML dos serviços
                servicosHtml = gerarHtmlServicosCompleto(dados.servicos, valorTotalMensal);

                // Gera HTML do histórico
                if (dados.historico && dados.historico.length > 0) {
                    historicoHtml = gerarHtmlHistorico(dados.historico);
                }
            } else {
                servicosHtml = `
                    <div class="empty-state" style="padding: 2rem; text-align: center; color: var(--gray-500);">
                        <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <p>Nenhum serviço contratado</p>
                        <small>Este associado ainda não possui serviços ativos</small>
                    </div>
                `;
            }

            financeiroTab.innerHTML = `
                <!-- Resumo Financeiro Principal -->
                <div class="resumo-financeiro" style="margin: 1.5rem 2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: 16px; padding: 2rem; color: white; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -30px; right: -30px; font-size: 6rem; opacity: 0.1;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div style="position: relative; z-index: 1; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; align-items: center;">
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px;">
                                Valor Mensal Total
                            </div>
                            <div style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.25rem;">
                                R$ ${valorTotalMensal.toFixed(2).replace('.', ',')}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.8;">
                                ${servicosAtivos.length} serviço${servicosAtivos.length !== 1 ? 's' : ''} ativo${servicosAtivos.length !== 1 ? 's' : ''}
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px;">
                                Tipo de Associado
                            </div>
                            <div style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.25rem;">
                                ${tipoAssociadoServico}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.8;">
                                Define percentual de cobrança
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 1px;">
                                Serviços Contratados
                            </div>
                            <div style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.25rem;">
                                ${resumoServicos}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.8;">
                                ${servicosAtivos.includes('Jurídico') ? 'Inclui cobertura jurídica' : 'Cobertura básica'}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção de Serviços Contratados -->
                <div class="detail-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3 class="section-title">Detalhes dos Serviços</h3>
                    </div>
                    ${servicosHtml}
                </div>

                <!-- Dados Bancários e Cobrança -->
                <div class="detail-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-university"></i>
                        </div>
                        <h3 class="section-title">Dados Bancários e Cobrança</h3>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Categoria</span>
                            <span class="detail-value">${associado.tipoAssociado || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Situação Financeira</span>
                            <span class="detail-value">
                                ${associado.situacaoFinanceira ?
                    `<span style="color: ${associado.situacaoFinanceira === 'Adimplente' ? 'var(--success)' : 'var(--danger)'}; font-weight: 600;">${associado.situacaoFinanceira}</span>`
                    : '-'}
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vínculo Servidor</span>
                            <span class="detail-value">${associado.vinculoServidor || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Local de Débito</span>
                            <span class="detail-value">${associado.localDebito || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Agência</span>
                            <span class="detail-value">${associado.agencia || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Operação</span>
                            <span class="detail-value">${associado.operacao || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Conta Corrente</span>
                            <span class="detail-value">${associado.contaCorrente || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ID Neoconsig</span>
                            <span class="detail-value">
                                ${associado.id_neoconsig ?
                    `<span style="color: var(--info); font-weight: 600;">
                                        <i class="fas fa-link" style="margin-right: 0.25rem;"></i>
                                        ${associado.id_neoconsig}
                                    </span>`
                    : '<span style="color: var(--gray-500);">Não cadastrado</span>'}
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">É Doador</span>
                            <span class="detail-value">
                                <span style="color: ${formatarDoador(associado.doador) === 'Sim' ? 'var(--success)' : 'var(--gray-500)'}; font-weight: 600;">
                                    ${formatarDoador(associado.doador)}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
                
                ${historicoHtml ? `
                <!-- Histórico de Alterações -->
                <div class="detail-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="section-title">Histórico de Alterações</h3>
                    </div>
                    ${historicoHtml}
                </div>
                ` : ''}
            `;
        })
        .catch(error => {
            console.error('Erro ao buscar serviços:', error);

            // Fallback: mostra apenas dados tradicionais
            financeiroTab.innerHTML = `
                <div class="detail-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                        </div>
                        <h3 class="section-title">Dados Financeiros</h3>
                        <small style="color: var(--warning); font-size: 0.75rem;">⚠ Não foi possível carregar dados dos serviços</small>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Categoria</span>
                            <span class="detail-value">${associado.tipoAssociado || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Situação Financeira</span>
                            <span class="detail-value">${associado.situacaoFinanceira || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vínculo Servidor</span>
                            <span class="detail-value">${associado.vinculoServidor || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Local de Débito</span>
                            <span class="detail-value">${associado.localDebito || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Agência</span>
                            <span class="detail-value">${associado.agencia || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Operação</span>
                            <span class="detail-value">${associado.operacao || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Conta Corrente</span>
                            <span class="detail-value">${associado.contaCorrente || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ID Neoconsig</span>
                            <span class="detail-value">
                                ${associado.id_neoconsig ?
                    `<span style="color: var(--info); font-weight: 600;">
                                        <i class="fas fa-link" style="margin-right: 0.25rem;"></i>
                                        ${associado.id_neoconsig}
                                    </span>`
                    : '<span style="color: var(--gray-500);">Não cadastrado</span>'}
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">É Doador</span>
                            <span class="detail-value">
                                <span style="color: ${formatarDoador(associado.doador) === 'Sim' ? 'var(--success)' : 'var(--gray-500)'}; font-weight: 600;">
                                    ${formatarDoador(associado.doador)}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            `;
        });
}

// Função para gerar HTML dos serviços - VERSÃO COMPLETA
function gerarHtmlServicosCompleto(servicos, valorTotal) {
    let servicosHtml = '';

    // Verifica se tem serviços
    if (!servicos.social && !servicos.juridico) {
        return `
            <div class="empty-state" style="padding: 2rem; text-align: center; color: var(--gray-500);">
                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Nenhum serviço ativo encontrado</p>
                <small>Este associado não possui serviços contratados</small>
            </div>
        `;
    }

    servicosHtml += '<div class="servicos-container" style="display: flex; flex-direction: column; gap: 1.5rem;">';

    // Serviço Social
    if (servicos.social) {
        const social = servicos.social;
        const dataAdesao = new Date(social.data_adesao).toLocaleDateString('pt-BR');
        const valorBase = parseFloat(social.valor_base || 173.10);
        const desconto = ((valorBase - parseFloat(social.valor_aplicado)) / valorBase * 100).toFixed(0);

        servicosHtml += `
            <div class="servico-card" style="
                background: linear-gradient(135deg, var(--success) 0%, #00a847 100%);
                padding: 1.5rem;
                border-radius: 16px;
                color: white;
                position: relative;
                overflow: hidden;
                box-shadow: 0 8px 24px rgba(0, 200, 83, 0.3);
            ">
                <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.1;">
                    <i class="fas fa-heart"></i>
                </div>
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-heart"></i>
                                Serviço Social
                            </h4>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.75rem; flex-wrap: wrap;">
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    OBRIGATÓRIO
                                </span>
                                <span style="font-size: 0.875rem; opacity: 0.9;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 0.25rem;"></i>
                                    Desde ${dataAdesao}
                                </span>
                                ${desconto > 0 ? `
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fas fa-percentage" style="margin-right: 0.25rem;"></i>
                                    ${desconto}% desconto
                                </span>
                                ` : ''}
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 120px;">
                            <div style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.25rem;">
                                R$ ${parseFloat(social.valor_aplicado).toFixed(2).replace('.', ',')}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.5rem; border-radius: 8px;">
                                ${parseFloat(social.percentual_aplicado).toFixed(0)}% do valor base
                            </div>
                        </div>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 8px; font-size: 0.875rem; line-height: 1.5;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Valor base:</span>
                            <span style="font-weight: 600;">R$ ${valorBase.toFixed(2).replace('.', ',')}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Serviço Jurídico
    if (servicos.juridico) {
        const juridico = servicos.juridico;
        const dataAdesao = new Date(juridico.data_adesao).toLocaleDateString('pt-BR');
        const valorBase = parseFloat(juridico.valor_base || 43.28);
        const desconto = ((valorBase - parseFloat(juridico.valor_aplicado)) / valorBase * 100).toFixed(0);

        servicosHtml += `
            <div class="servico-card" style="
                background: linear-gradient(135deg, var(--info) 0%, #0097a7 100%);
                padding: 1.5rem;
                border-radius: 16px;
                color: white;
                position: relative;
                overflow: hidden;
                box-shadow: 0 8px 24px rgba(0, 184, 212, 0.3);
            ">
                <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.1;">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-balance-scale"></i>
                                Serviço Jurídico
                            </h4>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.75rem; flex-wrap: wrap;">
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    OPCIONAL
                                </span>
                                <span style="font-size: 0.875rem; opacity: 0.9;">
                                    <i class="fas fa-calendar-alt" style="margin-right: 0.25rem;"></i>
                                    Desde ${dataAdesao}
                                </span>
                                ${desconto > 0 ? `
                                <span style="background: rgba(255,255,255,0.25); padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                    <i class="fas fa-percentage" style="margin-right: 0.25rem;"></i>
                                    ${desconto}% desconto
                                </span>
                                ` : ''}
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 120px;">
                            <div style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.25rem;">
                                R$ ${parseFloat(juridico.valor_aplicado).toFixed(2).replace('.', ',')}
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.9; background: rgba(255,255,255,0.2); padding: 0.25rem 0.5rem; border-radius: 8px;">
                                ${parseFloat(juridico.percentual_aplicado).toFixed(0)}% do valor base
                            </div>
                        </div>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 1rem; border-radius: 8px; font-size: 0.875rem; line-height: 1.5;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Valor base:</span>
                            <span style="font-weight: 600;">R$ ${valorBase.toFixed(2).replace('.', ',')}</span>
                        </div>
                        
                    </div>
                </div>
            </div>
        `;
    }

    servicosHtml += '</div>';
    return servicosHtml;
}

// Função para gerar HTML do histórico
function gerarHtmlHistorico(historico) {
    if (!historico || historico.length === 0) {
        return '';
    }

    let historicoHtml = '<div class="historico-container" style="display: flex; flex-direction: column; gap: 1rem;">';

    historico.slice(0, 5).forEach(item => { // Mostra apenas os últimos 5
        const data = new Date(item.data_alteracao).toLocaleDateString('pt-BR');
        const hora = new Date(item.data_alteracao).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        let icone = 'fa-edit';
        let cor = 'var(--info)';
        let titulo = item.tipo_alteracao;

        if (item.tipo_alteracao === 'ADESAO') {
            icone = 'fa-plus-circle';
            cor = 'var(--success)';
            titulo = 'Adesão';
        } else if (item.tipo_alteracao === 'CANCELAMENTO') {
            icone = 'fa-times-circle';
            cor = 'var(--danger)';
            titulo = 'Cancelamento';
        } else if (item.tipo_alteracao === 'ALTERACAO_VALOR') {
            icone = 'fa-exchange-alt';
            cor = 'var(--warning)';
            titulo = 'Alteração de Valor';
        }

        historicoHtml += `
            <div style="
                background: var(--gray-100);
                padding: 1rem;
                border-radius: 12px;
                border-left: 4px solid ${cor};
                display: flex;
                align-items: flex-start;
                gap: 1rem;
            ">
                <div style="
                    width: 40px;
                    height: 40px;
                    background: ${cor};
                    color: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                ">
                    <i class="fas ${icone}"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <h5 style="margin: 0; font-weight: 600; color: var(--dark);">
                            ${titulo} - ${item.servico_nome}
                        </h5>
                        <small style="color: var(--gray-500); font-size: 0.75rem;">
                            ${data} às ${hora}
                        </small>
                    </div>
                    <div style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                        ${item.motivo || 'Sem observações'}
                    </div>
                    ${item.valor_anterior && item.valor_novo ? `
                        <div style="display: flex; gap: 1rem; font-size: 0.75rem;">
                            <span style="color: var(--danger);">
                                De: R$ ${parseFloat(item.valor_anterior).toFixed(2).replace('.', ',')}
                            </span>
                            <span style="color: var(--success);">
                                Para: R$ ${parseFloat(item.valor_novo).toFixed(2).replace('.', ',')}
                            </span>
                        </div>
                    ` : ''}
                    ${item.funcionario_nome ? `
                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.5rem;">
                            <i class="fas fa-user" style="margin-right: 0.25rem;"></i>
                            Por: ${item.funcionario_nome}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    });

    historicoHtml += '</div>';
    return historicoHtml;
}

// Função para buscar serviços do associado
function buscarServicosAssociado(associadoId) {
    return fetch(`../api/buscar_servicos_associado.php?associado_id=${associadoId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        });
}

// Preenche tab Contato
function preencherTabContato(associado) {
    const contatoTab = document.getElementById('contato-tab');

    contatoTab.innerHTML = `
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <h3 class="section-title">Informações de Contato</h3>
                ${(permissoesUsuario.podeEditarContato || permissoesUsuario.podeEditarCompleto) ? `
                    <button class="btn-modern btn-primary btn-sm" onclick="abrirModalEditarContato(${associado.id}, '${associado.nome.replace(/'/g, "\\'")}')">
                        <i class="fas fa-edit"></i> Editar Contato
                    </button>
                ` : ''}
            </div>

            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Telefone</span>
                    <span class="detail-value">${formatarTelefone(associado.telefone)}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">E-mail</span>
                    <span class="detail-value">${associado.email || '-'}</span>
                </div>
            </div>
        </div>
        
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3 class="section-title">Endereço</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">CEP</span>
                    <span class="detail-value">${associado.cep || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Endereço</span>
                    <span class="detail-value">${associado.endereco || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Número</span>
                    <span class="detail-value">${associado.numero || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Complemento</span>
                    <span class="detail-value">${associado.complemento || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Bairro</span>
                    <span class="detail-value">${associado.bairro || '-'}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Cidade</span>
                    <span class="detail-value">${associado.cidade || '-'}</span>
                </div>
            </div>
        </div>
    `;
}

// Preenche tab Dependentes
function preencherTabDependentes(associado) {
    const dependentesTab = document.getElementById('dependentes-tab');

    if (!associado.dependentes || associado.dependentes.length === 0) {
        dependentesTab.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>Nenhum dependente cadastrado</p>
            </div>
        `;
        return;
    }

    let dependentesHtml = `
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="section-title">Dependentes (${associado.dependentes.length})</h3>
            </div>
            <div class="list-container">
    `;

    associado.dependentes.forEach(dep => {
        let idade = '-';
        if (dep.data_nascimento && dep.data_nascimento !== '0000-00-00') {
            const hoje = new Date();
            const nascimento = new Date(dep.data_nascimento);
            idade = Math.floor((hoje - nascimento) / (365.25 * 24 * 60 * 60 * 1000));
            idade = idade + ' anos';
        }

        dependentesHtml += `
            <div class="list-item">
                <div class="list-item-content">
                    <div class="list-item-title">${dep.nome || 'Sem nome'}</div>
                    <div class="list-item-subtitle">
                        ${dep.parentesco || 'Parentesco não informado'} • 
                        ${formatarData(dep.data_nascimento)} • 
                        ${idade}
                    </div>
                </div>
                <span class="list-item-badge">${dep.sexo || '-'}</span>
            </div>
        `;
    });

    dependentesHtml += `
            </div>
        </div>
    `;

    dependentesTab.innerHTML = dependentesHtml;
}

function preencherTabDocumentos(associado) {
    const documentosTab = document.getElementById('documentos-tab');

    // Show loading
    documentosTab.innerHTML = `
        <div class="text-center py-5">
            <div class="loading-spinner mb-3"></div>
            <p class="text-muted">Carregando documentos...</p>
        </div>
    `;

    // Call NEW API to list documents
    $.ajax({
        url: '../api/documentos/upload_documentos_listar.php',
        method: 'GET',
        data: { associado_id: associado.id },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                renderizarDocumentosUpload(response.data, documentosTab, associado);
            } else {
                renderizarDocumentosUpload([], documentosTab, associado);
            }
        },
        error: function () {
            renderizarDocumentosUpload([], documentosTab, associado);
        }
    });
}

function renderizarDocumentosUpload(documentos, container, associado) {
    let html = `
        <!-- Header Principal estilo Financeiro -->
        <div style="
            margin: 1.5rem 2rem;
            background: linear-gradient(135deg, #4169E1 0%, #1E3A8A 100%);
            border-radius: 16px;
            padding: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        ">
            <div style="
                position: absolute;
                top: -30px;
                right: -30px;
                font-size: 6rem;
                opacity: 0.1;
            ">
                <i class="fas fa-folder-open"></i>
            </div>
            <div style="
                position: relative;
                z-index: 1;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 2rem;
                align-items: center;
            ">
                <div style="text-align: center;">
                    <div style="
                        font-size: 0.875rem;
                        opacity: 0.9;
                        margin-bottom: 0.5rem;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    ">
                        DOCUMENTOS ANEXADOS
                    </div>
                    <div style="
                        font-size: 2.5rem;
                        font-weight: 800;
                        margin-bottom: 0.25rem;
                    ">
                        ${documentos.length}
                    </div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">
                        documento${documentos.length !== 1 ? 's' : ''} no sistema
                    </div>
                </div>
                <div style="text-align: center;">
                    <div style="
                        font-size: 0.875rem;
                        opacity: 0.9;
                        margin-bottom: 0.5rem;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    ">
                        ASSOCIADO
                    </div>
                    <div style="
                        font-size: 1.3rem;
                        font-weight: 700;
                        margin-bottom: 0.25rem;
                    ">
                        ${associado.nome}
                    </div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">
                        Matrícula: ${associado.id}
                    </div>
                </div>
                <div style="text-align: center;">
                    <button onclick="abrirModalUploadDocumento(${associado.id}, '${associado.nome.replace(/'/g, "\\'")}')" style="
                        background: rgba(255, 255, 255, 0.2);
                        border: 2px solid rgba(255, 255, 255, 0.3);
                        color: white;
                        padding: 0.75rem 1.5rem;
                        border-radius: 12px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        font-size: 0.875rem;
                    " onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                        <i class="fas fa-plus me-2"></i>
                        Anexar Documento
                    </button>
                </div>
            </div>
        </div>

        <!-- Seção de Documentos -->
        <div class="detail-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h3 class="section-title">Documentos Anexados</h3>
            </div>
    `;

    if (documentos.length > 0) {
        // Container para os documentos
        html += '<div style="padding: 0 1rem;">';

        documentos.forEach(doc => {
            // Define cor baseada no status
            let statusColor = '#28a745';
            let statusBg = 'rgba(40, 167, 69, 0.1)';
            let statusIcon = 'check-circle';
            let statusText = doc.status_descricao || 'Digitalizado';

            if (!doc.arquivo_existe) {
                statusColor = '#dc3545';
                statusBg = 'rgba(220, 53, 69, 0.1)';
                statusIcon = 'exclamation-triangle';
                statusText = 'Arquivo não encontrado';
            }

            // Define ícone do arquivo
            let fileIcon = 'file-pdf';
            let iconColor = '#dc3545';

            if (doc.tipo_mime && doc.tipo_mime.includes('image')) {
                fileIcon = 'file-image';
                iconColor = '#28a745';
            }

            html += `
                <div style="
                    background: white;
                    border: 1px solid #e9ecef;
                    border-radius: 12px;
                    padding: 1.5rem;
                    margin-bottom: 1rem;
                    transition: all 0.3s ease;
                ">
                    <!-- Badge de Status -->
                    <div style="
                        display: inline-block;
                        background: ${statusBg};
                        color: ${statusColor};
                        padding: 0.375rem 0.75rem;
                        border-radius: 20px;
                        font-size: 0.75rem;
                        font-weight: 600;
                        margin-bottom: 1rem;
                    ">
                        <i class="fas fa-${statusIcon} me-1"></i>
                        ${statusText}
                    </div>
                    
                    <!-- Conteúdo do Documento -->
                    <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
                        <!-- Ícone -->
                        <div style="
                            width: 50px;
                            height: 50px;
                            background: ${iconColor}15;
                            color: ${iconColor};
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-shrink: 0;
                            font-size: 1.5rem;
                        ">
                            <i class="fas fa-${fileIcon}"></i>
                        </div>
                        
                        <!-- Informações -->
                        <div style="flex: 1;">
                            <h6 style="
                                margin: 0 0 0.25rem 0;
                                font-weight: 600;
                                color: #2c3e50;
                                font-size: 1rem;
                            ">
                                ${doc.tipo_descricao || 'Documento'}
                            </h6>
                            <p style="
                                margin: 0 0 0.5rem 0;
                                color: #6c757d;
                                font-size: 0.875rem;
                                word-break: break-all;
                            ">
                                ${doc.nome_arquivo || 'arquivo.pdf'}
                            </p>
                            <div style="
                                display: flex;
                                gap: 1.5rem;
                                font-size: 0.75rem;
                                color: #6c757d;
                            ">
                                ${doc.tamanho_formatado ? `
                                    <span>
                                        <i class="fas fa-weight-hanging me-1"></i>
                                        ${doc.tamanho_formatado}
                                    </span>
                                ` : ''}
                                ${doc.data_upload_formatada ? `
                                    <span>
                                        <i class="fas fa-calendar me-1"></i>
                                        ${doc.data_upload_formatada}
                                    </span>
                                ` : ''}
                                ${doc.funcionario_upload ? `
                                    <span>
                                        <i class="fas fa-user me-1"></i>
                                        ${doc.funcionario_upload}
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                    
                    ${doc.observacao ? `
                        <div style="
                            background: #f8f9fa;
                            padding: 0.75rem;
                            border-radius: 8px;
                            margin-bottom: 1rem;
                            font-size: 0.875rem;
                            color: #495057;
                        ">
                            <i class="fas fa-sticky-note me-1" style="color: #6c757d;"></i>
                            ${doc.observacao}
                        </div>
                    ` : ''}
                    
                    <!-- Botões de Ação -->
                    <div style="display: flex; gap: 0.75rem;">
                        ${doc.arquivo_existe !== false ? `
                            <button onclick="downloadDocumentoUpload(${doc.id})" style="
                                background: transparent;
                                color: #6c757d;
                                border: 1px solid #dee2e6;
                                padding: 0.5rem 1rem;
                                border-radius: 8px;
                                font-size: 0.8125rem;
                                cursor: pointer;
                                display: inline-flex;
                                align-items: center;
                                gap: 0.5rem;
                                transition: all 0.3s ease;
                            " onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
                                <i class="fas fa-cloud-download-alt"></i>
                                Baixar
                            </button>
                        ` : `
                            <button disabled style="
                                background: transparent;
                                color: #adb5bd;
                                border: 1px solid #dee2e6;
                                padding: 0.5rem 1rem;
                                border-radius: 8px;
                                font-size: 0.8125rem;
                                cursor: not-allowed;
                                display: inline-flex;
                                align-items: center;
                                gap: 0.5rem;
                            ">
                                <i class="fas fa-exclamation-triangle"></i>
                                Indisponível
                            </button>
                        `}
                        
                        <button onclick="if(confirm('Deseja remover este documento?')) { removerDocumento(${doc.id}); }" style="
                            background: transparent;
                            color: #dc3545;
                            border: 1px solid #dc3545;
                            padding: 0.5rem 1rem;
                            border-radius: 8px;
                            font-size: 0.8125rem;
                            cursor: pointer;
                            display: inline-flex;
                            align-items: center;
                            gap: 0.5rem;
                            transition: all 0.3s ease;
                        " onmouseover="this.style.background='#dc3545'; this.style.color='white';" onmouseout="this.style.background='transparent'; this.style.color='#dc3545';">
                            <i class="fas fa-trash"></i>
                            Remover
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
    } else {
        // Nenhum documento - Estado vazio
        html += `
            <div style="
                background: #f8f9fa;
                padding: 3rem 2rem;
                border-radius: 16px;
                border: 2px dashed #dee2e6;
                text-align: center;
                margin: 1rem;
            ">
                <div style="
                    font-size: 4rem;
                    color: #dee2e6;
                    margin-bottom: 1rem;
                ">
                    <i class="fas fa-folder-open"></i>
                </div>
                <h5 style="
                    color: #495057;
                    margin-bottom: 0.5rem;
                    font-weight: 600;
                ">
                    Nenhum documento anexado
                </h5>
                <p style="
                    color: #6c757d;
                    margin-bottom: 2rem;
                    font-size: 0.875rem;
                ">
                    ${associado.nome} ainda não possui documentos anexados ao cadastro
                </p>
                <button onclick="abrirModalUploadDocumento(${associado.id}, '${associado.nome.replace(/'/g, "\\'")}')" style="
                    background: #4169E1;
                    color: white;
                    border: none;
                    padding: 0.75rem 1.5rem;
                    border-radius: 12px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                " onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-plus me-2"></i>
                    Anexar Primeiro Documento
                </button>
            </div>
        `;
    }

    html += '</div>'; // Fecha detail-section

    container.innerHTML = html;
}

// ADD this new function to dashboard.php
function downloadDocumentoUpload(id) {
    window.open('../api/documentos/upload_documentos_download.php?id=' + id, '_blank');
}

function abrirModalUploadDocumento(associadoId, associadoNome) {
    const uploadModalHtml = `
        <div class="modal fade" id="uploadDocumentoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background: var(--primary); color: white;">
                        <h5 class="modal-title">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Anexar Documento
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4" style="background: var(--gray-100); padding: 1rem; border-radius: 8px;">
                            <h6 style="margin: 0;">
                                <i class="fas fa-user me-2"></i>
                                ${associadoNome}
                            </h6>
                            <small class="text-muted">ID: ${associadoId}</small>
                        </div>

                        <form id="uploadDocumentoForm" enctype="multipart/form-data">
                            <input type="hidden" name="associado_id" value="${associadoId}">
                            
                            <div class="mb-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select class="form-select" id="tipoDocumento" name="tipo_documento" required>
                                    <option value="">Selecione o tipo</option>
                                    <option value="FICHA_FILIACAO">Ficha de Filiação</option>
                                    <option value="RG">RG (Cópia)</option>
                                    <option value="CPF">CPF (Cópia)</option>
                                    <option value="COMPROVANTE_RESIDENCIA">Comprovante de Residência</option>
                                    <option value="FOTO_3X4">Foto 3x4</option>
                                    <option value="CERTIDAO_NASCIMENTO">Certidão de Nascimento</option>
                                    <option value="CERTIDAO_CASAMENTO">Certidão de Casamento</option>
                                    <option value="OUTROS">Outros</option>
                                </select>
                            </div>

                            <div class="mb-3" id="outroTipoDiv" style="display: none;">
                                <label class="form-label">Especifique o tipo</label>
                                <input type="text" class="form-control" id="outroTipo" name="outro_tipo">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Arquivo</label>
                                <div onclick="document.getElementById('arquivoDocumento').click()" style="
                                    border: 2px dashed var(--gray-300);
                                    border-radius: 12px;
                                    padding: 2rem;
                                    text-align: center;
                                    background: var(--gray-50);
                                    cursor: pointer;
                                ">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-2"><strong>Clique para selecionar</strong> o arquivo</p>
                                    <small class="text-muted">PDF, JPG, PNG até 5MB</small>
                                    <input type="file" id="arquivoDocumento" name="arquivo" accept=".pdf,.jpg,.jpeg,.png" required style="display: none;" onchange="updateFileInfo(this)">
                                </div>
                                <div id="fileInfo" class="mt-2" style="display: none;"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observações (opcional)</label>
                                <textarea class="form-control" name="observacao" rows="3"></textarea>
                            </div>

                            <div id="uploadProgress" style="display: none;">
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                                </div>
                                <small class="text-muted mt-1 d-block">Enviando arquivo...</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="enviarDocumento()">
                            <i class="fas fa-upload me-2"></i>
                            Enviar Documento
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal
    $('#uploadDocumentoModal').remove();

    // Add new modal
    $('body').append(uploadModalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('uploadDocumentoModal'));
    modal.show();

    // Handle document type change
    document.getElementById('tipoDocumento').addEventListener('change', function () {
        const outroTipoDiv = document.getElementById('outroTipoDiv');
        if (this.value === 'OUTROS') {
            outroTipoDiv.style.display = 'block';
            document.getElementById('outroTipo').required = true;
        } else {
            outroTipoDiv.style.display = 'none';
            document.getElementById('outroTipo').required = false;
        }
    });
}

function updateFileInfo(input) {
    const fileInfo = document.getElementById('fileInfo');
    if (input.files.length > 0) {
        const file = input.files[0];
        const fileSize = (file.size / 1024 / 1024).toFixed(2);

        fileInfo.innerHTML = `
            <div class="alert alert-info d-flex align-items-center" style="margin: 0;">
                <i class="fas fa-file me-2"></i>
                <div>
                    <strong>${file.name}</strong><br>
                    <small>${fileSize} MB</small>
                </div>
            </div>
        `;
        fileInfo.style.display = 'block';
    } else {
        fileInfo.style.display = 'none';
    }
}

// NEW FUNCTION: Send document
function enviarDocumento() {
    const form = document.getElementById('uploadDocumentoForm');
    const formData = new FormData(form);
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = uploadProgress.querySelector('.progress-bar');

    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Validate file
    const arquivo = document.getElementById('arquivoDocumento').files[0];
    if (!arquivo) {
        alert('Por favor, selecione um arquivo.');
        return;
    }

    // Check file size (5MB max)
    if (arquivo.size > 5 * 1024 * 1024) {
        alert('Arquivo muito grande. Máximo: 5MB');
        return;
    }

    // Show progress
    uploadProgress.style.display = 'block';

    // Disable buttons
    const buttons = document.querySelectorAll('#uploadDocumentoModal button');
    buttons.forEach(btn => btn.disabled = true);

    // Upload with progress
    const xhr = new XMLHttpRequest();

    xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) {
            const percent = (e.loaded / e.total) * 100;
            progressBar.style.width = percent + '%';
        }
    });

    xhr.addEventListener('load', function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    alert('Documento enviado com sucesso!');

                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('uploadDocumentoModal'));
                    modal.hide();

                    // Reload documents tab
                    const associadoId = document.getElementById('modalId').textContent.replace('Matrícula: ', '').trim();
                    const associado = todosAssociados.find(a => a.id == associadoId);
                    if (associado) {
                        preencherTabDocumentos(associado);
                    }
                } else {
                    alert('Erro: ' + response.message);
                }
            } catch (e) {
                alert('Erro ao processar resposta');
            }
        } else {
            alert('Erro ao enviar arquivo');
        }

        // Re-enable buttons
        buttons.forEach(btn => btn.disabled = false);
        uploadProgress.style.display = 'none';
    });

    xhr.addEventListener('error', function () {
        alert('Erro de conexão');
        buttons.forEach(btn => btn.disabled = false);
        uploadProgress.style.display = 'none';
    });

    xhr.open('POST', '../api/documentos/documentos_upload.php');
    xhr.send(formData);
}

function fecharModal() {
    const modal = document.getElementById('modalAssociado');
    
    // CORREÇÃO: Verificar se já está fechando ou já está fechado
    if (modalFechando || !modal.classList.contains('show')) {
        console.log('⚠️ Modal já está fechando ou fechado, ignorando chamada duplicada');
        return;
    }

    console.log('🔒 Fechando modal...');
    
    // Marcar que está fechando
    modalFechando = true;

    // Remover classe show imediatamente (inicia animação CSS)
    modal.classList.remove('show');
    modal.classList.remove('modal-edit-mode');
    document.body.style.overflow = 'auto';
    
    // Resetar estado de edição
    if (typeof modoEdicaoAtivo !== 'undefined') {
        modoEdicaoAtivo = false;
    }
    if (typeof dadosOriginaisAssociado !== 'undefined') {
        dadosOriginaisAssociado = null;
    }
    
    // Resetar botões
    const btnEditar = document.getElementById('btnEditarModal');
    const btnSalvar = document.getElementById('btnSalvarModal');
    const btnCancelar = document.getElementById('btnCancelarModal');
    
    if (btnEditar) btnEditar.style.display = 'none';
    if (btnSalvar) btnSalvar.style.display = 'none';
    if (btnCancelar) btnCancelar.style.display = 'none';

    // Aguardar animação CSS (300ms) antes de limpar dados
    setTimeout(() => {
        associadoAtual = null;
        resetarObservacoes();
        
        // Liberar flag de fechamento
        modalFechando = false;
        console.log('✅ Modal fechado completamente');
    }, 300);
}

// FUNÇÃO CORRIGIDA: Trocar de tab com carregamento de observações na visão geral
function abrirTab(tabName) {
    // Remove active de todas as tabs
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });

    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });

    // Adiciona active na tab selecionada
    const activeButton = document.querySelector(`.tab-button[onclick="abrirTab('${tabName}')"]`);
    if (activeButton) {
        activeButton.classList.add('active');
    }

    const activeContent = document.getElementById(`${tabName}-tab`);
    if (activeContent) {
        activeContent.classList.add('active');
    }

    // CORREÇÃO PRINCIPAL: Se for a aba overview e temos um associado, carregar observações
    if (tabName === 'overview' && associadoAtual && associadoAtual.id) {
        console.log('🔄 Aba overview aberta, carregando observações para associado:', associadoAtual.id);
        // Aguardar um pouco para garantir que o HTML foi renderizado
        setTimeout(() => {
            carregarObservacoesVisaoGeral(associadoAtual.id);
        }, 100);
    }

    // Se for a aba de observações, carregar dados completos
    if (tabName === 'observacoes' && associadoAtual && associadoAtual.id) {
        const associadoId = associadoAtual.id;
        // Só carrega se ainda não carregou para este associado
        if (currentAssociadoIdObs !== associadoId || observacoesData.length === 0) {
            carregarObservacoes(associadoId);
        } else {
            // Se já tem dados, apenas renderiza novamente
            renderizarObservacoes();
        }
    }
}

// Função para excluir associado
function excluirAssociado(id) {
    console.log('Excluindo associado ID:', id);
    event.stopPropagation();

    // Verificação de permissão
    if (!permissoesUsuario.podeExcluir) {
        alert('Você não tem permissão para excluir associados.');
        return;
    }

    const associado = todosAssociados.find(a => a.id == id);

    if (!associado) {
        alert('Associado não encontrado!');
        return;
    }

    if (!confirm(`Tem certeza que deseja excluir o associado ${associado.nome}?\n\nEsta ação não pode ser desfeita!`)) {
        return;
    }

    showLoading();

    // Chamada AJAX para excluir
    $.ajax({
        url: '../api/excluir_associado.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function (response) {
            hideLoading();

            if (response.status === 'success') {
                alert('Associado excluído com sucesso!');

                // Remove da lista local
                todosAssociados = todosAssociados.filter(a => a.id != id);
                associadosFiltrados = associadosFiltrados.filter(a => a.id != id);

                // Recalcula paginação e renderiza
                calcularPaginacao();
                renderizarPagina();
            } else {
                alert('Erro ao excluir associado: ' + response.message);
            }
        },
        error: function (xhr, status, error) {
            hideLoading();
            console.error('Erro ao excluir:', error);
            alert('Erro ao excluir associado. Por favor, tente novamente.');
        }
    });
}

// NOVA FUNÇÃO: Inicializar event listeners do modal (só executa uma vez)
function inicializarEventListenersModal() {
    if (modalEventListenersAdded) {
        console.log('⚠️ Event listeners do modal já foram adicionados');
        return;
    }

    console.log('✅ Inicializando event listeners do modal...');

    // Fecha modal ao clicar fora (no backdrop)
    window.addEventListener('click', function (event) {
        const modal = document.getElementById('modalAssociado');
        // Só fecha se clicar EXATAMENTE no modal (backdrop), não nos elementos internos
        if (event.target === modal && modal.classList.contains('show')) {
            console.log('👆 Clique fora do modal detectado');
            fecharModal();
        }
    });

    // Tecla ESC fecha o modal
    document.addEventListener('keydown', function (event) {
        const modal = document.getElementById('modalAssociado');
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            console.log('⌨️ ESC pressionado, fechando modal');
            fecharModal();
        }
    });

    modalEventListenersAdded = true;
    console.log('✅ Event listeners do modal inicializados com sucesso');
}

// Inicializa os event listeners quando o documento estiver pronto
$(document).ready(function() {
    inicializarEventListenersModal();
});

// FUNÇÃO CORRIGIDA: Carregar observações na visão geral
function carregarObservacoesVisaoGeral(associadoId) {
    const container = document.getElementById('overviewObservacoes');
    if (!container) {
        console.warn('Container overviewObservacoes não encontrado');
        return;
    }

    console.log('🔄 Carregando observações para visão geral, associado:', associadoId);

    // Mostrar loading
    container.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 2rem; color: #6b7280;">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Carregando observações...</span>
        </div>
    `;

    // Fazer requisição para o endpoint de observações
    fetch(`../api/observacoes/listar.php?associado_id=${associadoId}`)
        .then(response => response.json())
        .then(data => {
            console.log('📋 Dados de observações recebidos:', data);
            if (data.status === 'success') {
                const observacoes = data.data || [];

                // Atualizar contador na aba de observações
                const countBadge = document.getElementById('observacoesCountBadge');
                if (countBadge) {
                    const totalObs = data.estatisticas?.total || observacoes.length;
                    if (totalObs > 0) {
                        countBadge.textContent = totalObs;
                        countBadge.style.display = 'inline-block';
                    } else {
                        countBadge.style.display = 'none';
                    }
                }

                // Renderizar observações na visão geral (apenas as 3 mais recentes)
                renderizarObservacoesSimples(observacoes.slice(0, 3), observacoes.length);

            } else {
                console.error('Erro na resposta:', data.message);
                container.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: #9ca3af;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Erro ao carregar observações</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro ao carregar observações:', error);
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #9ca3af;">
                    <i class="fas fa-wifi"></i>
                    <p>Erro de conexão</p>
                </div>
            `;
        });
}

// Função para renderizar observações simples
function renderizarObservacoesSimples(observacoes, totalObservacoes) {
    const container = document.getElementById('overviewObservacoes');

    if (!observacoes || observacoes.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 2rem; color: #9ca3af;">
                <i class="fas fa-clipboard-list" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                <p>Nenhuma observação registrada</p>
            </div>
        `;
        return;
    }

    let html = '';

    observacoes.forEach(obs => {
        const dataFormatada = formatarDataSimples(obs.data_criacao);

        html += `
            <div class="observacao-item-overview">
                <div class="observacao-header-overview">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="observacao-categoria-badge">
                            ${(obs.categoria || 'geral').toUpperCase()}
                        </span>
                        ${obs.importante === '1' ? '<i class="fas fa-star" style="color: #f59e0b;" title="Importante"></i>' : ''}
                    </div>
                    <span style="font-size: 0.7rem; color: #9ca3af;">${dataFormatada}</span>
                </div>
                <div class="observacao-texto-overview">
                    ${truncarTexto(obs.observacao, 120)}
                </div>
                <div class="observacao-footer-overview">
                    <span style="font-weight: 500; color: #6b7280;">Por: ${obs.criado_por_nome || 'Sistema'}</span>
                </div>
            </div>
        `;
    });

    // Adicionar link para ver todas as observações se houver mais de 3
    if (totalObservacoes > 3) {
        html += `
            <a href="#" class="ver-todas-observacoes" onclick="abrirTab('observacoes'); return false;">
                <i class="fas fa-eye" style="margin-right: 0.5rem;"></i>
                Ver todas as ${totalObservacoes} observações
            </a>
        `;
    }

    container.innerHTML = html;
}

// Funções auxiliares simples
function formatarDataSimples(dataStr) {
    if (!dataStr) return 'Data não informada';

    try {
        const data = new Date(dataStr);
        const agora = new Date();
        const diffMs = agora - data;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHoras = Math.floor(diffMs / 3600000);
        const diffDias = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Agora mesmo';
        if (diffMins < 60) return `${diffMins}min atrás`;
        if (diffHoras < 24) return `${diffHoras}h atrás`;
        if (diffDias < 7) return `${diffDias}d atrás`;

        return data.toLocaleDateString('pt-BR');
    } catch (e) {
        return 'Data inválida';
    }
}

function truncarTexto(texto, limite) {
    if (!texto) return '';
    if (texto.length <= limite) return texto;
    return texto.substring(0, limite) + '...';
}

function abrirWhatsApp(telefone) {
    event.stopPropagation();

    // Remove caracteres não numéricos
    const numero = telefone.replace(/\D/g, '');

    // Adiciona código do país se não tiver
    const numeroFormatado = numero.startsWith('55') ? numero : '55' + numero;

    // Abre WhatsApp Web
    window.open(`https://wa.me/${numeroFormatado}`, '_blank');
}

// Nova função de edição com lógica de permissão
function editarAssociadoNovo(id) {
    event.stopPropagation();

    const associado = todosAssociados.find(a => a.id == id);
    if (!associado) {
        alert('Associado não encontrado!');
        return;
    }

    // Se pode editar completo, vai para o formulário
    if (permissoesUsuario.podeEditarCompleto) {
        window.location.href = `cadastroForm.php?id=${id}`;
    } else {
        // Senão, abre modal de edição de contato
        abrirModalEditarContato(associado);
    }
}

// Função para remover documento
function removerDocumento(documentoId) {
    // Mostrar loading no botão
    const botaoRemover = document.querySelector(`button[onclick*="removerDocumento(${documentoId})"]`);
    if (botaoRemover) {
        const textoOriginal = botaoRemover.innerHTML;
        botaoRemover.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removendo...';
        botaoRemover.disabled = true;

        // Restaurar botão em caso de erro
        const restaurarBotao = () => {
            botaoRemover.innerHTML = textoOriginal;
            botaoRemover.disabled = false;
        };
    }

    // Fazer requisição AJAX para remover
    $.ajax({
        url: '../api/documentos/upload_documentos_remover.php',
        method: 'POST',
        data: JSON.stringify({ id: documentoId }),
        contentType: 'application/json',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                // Mostrar mensagem de sucesso
                mostrarNotificacaoDoc('Documento removido com sucesso!', 'success');

                // Recarregar a aba de documentos
                const associadoId = document.getElementById('modalId').textContent.replace('Matrícula: ', '').trim();
                const associado = todosAssociados.find(a => a.id == associadoId);
                if (associado) {
                    preencherTabDocumentos(associado);
                }
            } else {
                alert('Erro ao remover documento: ' + (response.message || 'Erro desconhecido'));
                if (botaoRemover) restaurarBotao();
            }
        },
        error: function (xhr, status, error) {
            console.error('Erro ao remover documento:', error);

            let mensagem = 'Erro ao remover documento';
            if (xhr.status === 404) {
                mensagem = 'Documento não encontrado';
            } else if (xhr.status === 403) {
                mensagem = 'Sem permissão para remover documento';
            } else if (xhr.status === 500) {
                mensagem = 'Erro interno do servidor';
            }

            alert(mensagem + '. Tente novamente.');
            if (botaoRemover) restaurarBotao();
        }
    });
}

// Função auxiliar para mostrar notificações de documentos
function mostrarNotificacaoDoc(mensagem, tipo = 'info') {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    notificacao.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
    notificacao.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'times-circle' : 'info-circle'} me-2"></i>
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notificacao);

    // Remover após 4 segundos
    setTimeout(() => {
        if (notificacao && notificacao.parentNode) {
            notificacao.remove();
        }
    }, 4000);
}

// Função para abrir modal de edição de contato
function abrirModalEditarContato(associadoId, associadoNome) {
    // Se for passado um objeto ao invés de ID (compatibilidade)
    if (typeof associadoId === 'object') {
        const associado = associadoId;
        associadoId = associado.id;
        associadoNome = associado.nome;
    }

    // Buscar o associado completo se precisar
    const associado = todosAssociados.find(a => a.id == associadoId);

    if (!associado) {
        alert('Associado não encontrado!');
        return;
    }

    // Preenche os campos
    document.getElementById('editContatoId').value = associado.id;
    document.getElementById('editContatoNome').textContent = associado.nome;
    document.getElementById('editContatoTelefone').value = associado.telefone || '';
    document.getElementById('editContatoEmail').value = associado.email || '';
    document.getElementById('editContatoCep').value = associado.cep || '';
    document.getElementById('editContatoEndereco').value = associado.endereco || '';
    document.getElementById('editContatoNumero').value = associado.numero || '';
    document.getElementById('editContatoComplemento').value = associado.complemento || '';
    document.getElementById('editContatoBairro').value = associado.bairro || '';
    document.getElementById('editContatoCidade').value = associado.cidade || '';

    // Abre o modal
    const modal = new bootstrap.Modal(document.getElementById('modalEditarContato'));
    modal.show();
}

// Função para salvar contato editado
function salvarContatoEditado() {
    const form = document.getElementById('formEditarContato');
    const formData = new FormData(form);

    // Mostrar loading no botão
    const btnSalvar = document.querySelector('#modalEditarContato button[onclick="salvarContatoEditado()"]');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Salvando...';
    btnSalvar.disabled = true;

    // Fazer requisição
    $.ajax({
        url: '../api/editar_contato_associado.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            if (response.status === 'success') {
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalEditarContato'));
                modal.hide();

                // Atualizar dados locais
                const associadoId = document.getElementById('editContatoId').value;
                const associado = todosAssociados.find(a => a.id == associadoId);
                if (associado) {
                    associado.telefone = document.getElementById('editContatoTelefone').value;
                    associado.email = document.getElementById('editContatoEmail').value;
                    associado.cep = document.getElementById('editContatoCep').value;
                    associado.endereco = document.getElementById('editContatoEndereco').value;
                    associado.numero = document.getElementById('editContatoNumero').value;
                    associado.complemento = document.getElementById('editContatoComplemento').value;
                    associado.bairro = document.getElementById('editContatoBairro').value;
                    associado.cidade = document.getElementById('editContatoCidade').value;
                }

                // Recarregar tabela
                aplicarFiltros();

                alert('Informações de contato atualizadas com sucesso!');
            } else {
                alert('Erro: ' + (response.message || 'Erro desconhecido'));
            }
        },
        error: function () {
            alert('Erro ao salvar alterações. Tente novamente.');
        },
        complete: function () {
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    });
}

// ========================================
// FUNÇÕES DE BUSCA UNIFICADAS
// ========================================

// Variável global para timeout de busca e controle
let searchTimeout;
let ultimaBuscaServidor = '';
let resultadosServidor = null; // Armazena resultados da busca do servidor
let todosAssociadosOriginal = []; // Backup dos dados originais

// Handler para input de busca (com debounce)
function handleSearchInput(e) {
    const termo = e.target.value.trim();
    
    console.log('🔍 handleSearchInput chamado, termo:', termo);

    // Limpa timeout anterior
    clearTimeout(searchTimeout);

    // Se vazio, restaura dados originais
    if (!termo) {
        console.log('🔄 Busca limpa, mostrando todos os dados');
        ultimaBuscaServidor = '';
        resultadosServidor = null; // Limpa resultados do servidor
        aplicarFiltros(); // Reaplica outros filtros
        return;
    }

    // Se muito curto (menos de 3 chars), apenas busca local imediata
    if (termo.length < 3) {
        console.log('💻 Busca local (termo curto):', termo);
        resultadosServidor = null; // Limpa resultados do servidor para usar busca local
        aplicarFiltros(); // Usa a busca local integrada
        return;
    }

    // Mostra indicador de carregamento
    mostrarIndicadorBusca(true);

    // Debounce: aguarda 400ms antes de buscar no servidor
    searchTimeout = setTimeout(() => {
        buscarNoServidor(termo);
    }, 400);
}

// Busca no servidor (busca em TODOS os registros do banco)
async function buscarNoServidor(termo) {
    console.log('🌐 Buscando no servidor:', termo);
    console.log('📍 URL da API:', `../api/buscar_associados.php?termo=${encodeURIComponent(termo)}&limit=500`);
    
    ultimaBuscaServidor = termo;

    try {
        const url = `../api/buscar_associados.php?termo=${encodeURIComponent(termo)}&limit=500`;
        console.log('🔗 Fazendo fetch para:', url);
        
        const response = await fetch(url);
        
        console.log('📡 Response status:', response.status);
        console.log('📡 Response ok:', response.ok);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const resultado = await response.json();
        console.log('📦 Resposta do servidor:', resultado);

        // Verifica se o usuário ainda está buscando pelo mesmo termo
        const termoAtual = document.getElementById('searchInput').value.trim();
        if (termoAtual !== termo) {
            console.log('⚠️ Termo mudou durante a busca, ignorando resultado');
            return;
        }

        if (resultado.status === 'success') {
            console.log(`✅ Encontrados ${resultado.dados.length} resultados no servidor`);
            console.log('📋 Primeiros dados recebidos:', resultado.dados.slice(0, 2));

            // Armazena resultados do servidor
            resultadosServidor = resultado.dados;
            
            // Aplica outros filtros (situação, corporação, etc) nos resultados do servidor
            aplicarFiltros();

            if (resultado.total_aproximado && resultado.total_aproximado > resultado.dados.length) {
                console.log(`ℹ️ Mostrando ${resultado.dados.length} de ~${resultado.total_aproximado} resultados`);
            }

        } else {
            console.warn('⚠️ Erro na busca do servidor:', resultado.message);
            resultadosServidor = null;
            // Fallback para busca local
            aplicarFiltros();
        }

    } catch (error) {
        console.error('❌ Erro na busca do servidor:', error);
        resultadosServidor = null;
        // Fallback para busca local
        aplicarFiltros();
    } finally {
        mostrarIndicadorBusca(false);
    }
}

// Indicador visual de busca
function mostrarIndicadorBusca(mostrar) {
    const searchIcon = document.querySelector('.search-box .search-icon');
    if (!searchIcon) return;

    if (mostrar) {
        searchIcon.className = 'fas fa-spinner fa-spin search-icon';
        searchIcon.style.color = 'var(--primary)';
    } else {
        searchIcon.className = 'fas fa-search search-icon';
        searchIcon.style.color = '';
    }
}

// Event listeners - INICIALIZAÇÃO ÚNICA
document.addEventListener('DOMContentLoaded', function () {
    console.log('📋 Inicializando event listeners da busca...');
    
    // Adiciona listeners aos filtros
    const searchInput = document.getElementById('searchInput');
    const filterSituacao = document.getElementById('filterSituacao');
    const filterTipoAssociado = document.getElementById('filterTipoAssociado');
    const filterCorporacao = document.getElementById('filterCorporacao');
    const filterPatente = document.getElementById('filterPatente');

    if (searchInput) {
        console.log('✅ Campo de busca encontrado, registrando listener');
        // Remove old listener first
        searchInput.removeEventListener('input', aplicarFiltros);
        // Add new server search listener
        searchInput.addEventListener('input', handleSearchInput);
        console.log('✅ Listener handleSearchInput registrado');
    } else {
        console.error('❌ Campo searchInput não encontrado!');
    }
    
    if (filterSituacao) {
        filterSituacao.addEventListener('change', aplicarFiltros);
        console.log('✅ Listener filterSituacao registrado');
    }
    if (filterTipoAssociado) {
        filterTipoAssociado.addEventListener('change', aplicarFiltros);
        console.log('✅ Listener filterTipoAssociado registrado');
    }
    if (filterCorporacao) {
        filterCorporacao.addEventListener('change', aplicarFiltros);
        console.log('✅ Listener filterCorporacao registrado');
    }
    if (filterPatente) {
        filterPatente.addEventListener('change', aplicarFiltros);
        console.log('✅ Listener filterPatente registrado');
    }

    // Paginação
    const perPageSelect = document.getElementById('perPageSelect');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function () {
            registrosPorPagina = parseInt(this.value);
            paginaAtual = 1;
            calcularPaginacao();
            renderizarPagina();
        });
    }

    const firstPage = document.getElementById('firstPage');
    const prevPage = document.getElementById('prevPage');
    const nextPage = document.getElementById('nextPage');
    const lastPage = document.getElementById('lastPage');

    if (firstPage) firstPage.addEventListener('click', () => irParaPagina(1));
    if (prevPage) prevPage.addEventListener('click', () => irParaPagina(paginaAtual - 1));
    if (nextPage) nextPage.addEventListener('click', () => irParaPagina(paginaAtual + 1));
    if (lastPage) lastPage.addEventListener('click', () => irParaPagina(totalPaginas));

    console.log('Event listeners adicionados');

    // Carrega dados apenas UMA vez após 500ms
    setTimeout(function () {
        carregarAssociados();
    }, 500);
});

console.log('Sistema inicializado com Header Component e Fluxo de Documentos!');

// ========================================
// FUNCIONALIDADES DA ABA DE OBSERVAÇÕES
// ========================================

// Variáveis globais para observações
let observacoesData = [];
let currentObservacaoPage = 1;
let observacoesPerPage = 5;
let currentFilterObs = 'all';
let currentAssociadoIdObs = null;

function carregarObservacoes(associadoId, forceReload = false) {
    // Evitar carregar se já estiver carregando
    if (window.carregandoObservacoes) {
        console.log('Já está carregando observações, ignorando...');
        return;
    }

    // Evitar recarregar se já carregou para este associado E não for reload forçado
    if (!forceReload && currentAssociadoIdObs === associadoId && observacoesData.length > 0) {
        console.log('Observações já carregadas para este associado');
        renderizarObservacoes();
        return;
    }

    window.carregandoObservacoes = true;
    currentAssociadoIdObs = associadoId;

    // Mostrar loading
    const container = document.getElementById('observacoesContainer');
    if (!container) {
        window.carregandoObservacoes = false;
        return;
    }

    container.innerHTML = `
        <div class="observacoes-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3rem;">
            <div class="loading-spinner" style="margin-bottom: 1rem;"></div>
            <p style="color: var(--gray-500);">${forceReload ? 'Atualizando observações...' : 'Carregando observações...'}</p>
        </div>
    `;

    // Fazer requisição AJAX para buscar observações
    $.ajax({
        url: '../api/observacoes/listar.php',
        method: 'GET',
        data: { associado_id: associadoId },
        dataType: 'json',
        success: function (response) {
            console.log('Resposta do servidor:', response);

            if (response.status === 'success') {
                observacoesData = response.data || [];
                renderizarObservacoes();
                atualizarContadorObservacoes();
            } else {
                observacoesData = [];
                mostrarErroObservacoes('Erro ao carregar observações: ' + (response.message || 'Erro desconhecido'));
            }
        },
        error: function (xhr, status, error) {
            console.error('Erro AJAX:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });

            observacoesData = [];
            let mensagemErro = 'Erro de conexão ao carregar observações';

            if (xhr.responseText) {
                try {
                    const resposta = JSON.parse(xhr.responseText);
                    if (resposta.message) {
                        mensagemErro = resposta.message;
                    }
                } catch (e) {
                    // Se não for JSON, usar mensagem padrão
                }
            }

            mostrarErroObservacoes(mensagemErro);
        },
        complete: function () {
            window.carregandoObservacoes = false;
        }
    });
}

// Função para renderizar observações
function renderizarObservacoes() {
    const container = document.getElementById('observacoesContainer');
    if (!container) return;

    // Verificar se há dados
    if (!observacoesData || !Array.isArray(observacoesData)) {
        observacoesData = [];
    }

    // Filtrar observações
    let observacoesFiltradas = filtrarObservacoes(observacoesData, currentFilterObs);

    // Aplicar busca se houver
    const searchTerm = document.getElementById('searchObservacoes')?.value.toLowerCase();
    if (searchTerm) {
        observacoesFiltradas = observacoesFiltradas.filter(obs =>
            obs.observacao.toLowerCase().includes(searchTerm) ||
            (obs.criado_por_nome && obs.criado_por_nome.toLowerCase().includes(searchTerm))
        );
    }

    // Paginação
    const startIndex = (currentObservacaoPage - 1) * observacoesPerPage;
    const endIndex = startIndex + observacoesPerPage;
    const observacoesPaginadas = observacoesFiltradas.slice(startIndex, endIndex);

    // Verificar se há observações
    if (observacoesFiltradas.length === 0) {
        container.innerHTML = `
            <div class="empty-observacoes-state" style="text-align: center; padding: 4rem 2rem; background: var(--gray-100); border-radius: 16px; margin: 2rem;">
                <div class="empty-observacoes-icon" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1.5rem; opacity: 0.5;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h5 style="color: var(--gray-700); font-weight: 600; margin-bottom: 0.5rem;">
                    ${searchTerm ? 'Nenhuma observação encontrada' : 'Nenhuma observação registrada'}
                </h5>
                <p style="color: var(--gray-500); font-size: 0.875rem; margin-bottom: 1.5rem;">
                    ${searchTerm ? 'Nenhuma observação corresponde à sua busca.' : 'Ainda não há observações para este associado.'}
                </p>
                ${!searchTerm ? `
                    <button class="btn-modern btn-primary" onclick="abrirModalNovaObservacao()">
                        <i class="fas fa-plus"></i>
                        Adicionar Primeira Observação
                    </button>
                ` : ''}
            </div>
        `;

        // Esconder paginação se não houver resultados
        const paginacao = document.querySelector('.observacoes-pagination');
        if (paginacao) paginacao.style.display = 'none';
        return;
    }

    // Mostrar paginação
    const paginacao = document.querySelector('.observacoes-pagination');
    if (paginacao) paginacao.style.display = 'flex';

    // Renderizar observações
    container.innerHTML = observacoesPaginadas.map(obs => criarCardObservacao(obs)).join('');

    // Atualizar paginação
    atualizarPaginacaoObservacoes(observacoesFiltradas.length);
}

// Função para criar card de observação
function criarCardObservacao(obs) {
    const dataFormatada = obs.data_formatada || formatarDataHoraObs(obs.data_criacao);
    const isImportante = obs.importante == '1' || obs.categoria === 'importante';
    const prioridade = obs.prioridade || 'media';

    return `
        <div class="observacao-card ${isImportante ? 'important' : ''}" 
             data-id="${obs.id}" 
             data-categoria="${obs.categoria || 'geral'}"
             data-prioridade="${prioridade}">
            <div class="observacao-header">
                <div class="observacao-meta">
                    <div class="observacao-author">
                        <div class="author-avatar">
                            ${obs.criado_por_foto ?
            `<img src="${obs.criado_por_foto}" alt="${obs.criado_por_nome}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">` :
            `<i class="fas fa-user"></i>`
        }
                        </div>
                        <div class="author-info">
                            <span class="author-name">${obs.criado_por_nome || 'Sistema'}</span>
                            <span class="author-role">${obs.criado_por_cargo || 'Administrador'}</span>
                        </div>
                    </div>
                    <div class="observacao-actions">
                        <button class="btn-observacao-action" title="${isImportante ? 'Remover importância' : 'Marcar como importante'}" onclick="toggleImportanteObs(${obs.id})">
                            <i class="${isImportante ? 'fas' : 'far'} fa-star ${isImportante ? 'text-warning' : ''}"></i>
                        </button>
                    </div>
                </div>
                <div class="observacao-date">
                    <i class="far fa-calendar"></i>
                    ${dataFormatada}
                </div>
            </div>
            <div class="observacao-content">
                <p>${obs.observacao.replace(/\n/g, '<br>')}</p>
            </div>
            ${obs.tags || obs.categoria ? `
                <div class="observacao-tags">
                    ${obs.categoria ? criarTagObs(obs.categoria) : ''}
                    ${obs.tags ? obs.tags.split(',').map(tag => criarTagObs(tag.trim())).join('') : ''}
                </div>
            ` : ''}
            ${obs.editado == '1' ? `
                <div class="observacao-edited" style="font-size: 0.625rem; color: var(--gray-500); font-style: italic; margin-top: 0.5rem;">
                    <i class="fas fa-edit"></i>
                    Editado em ${obs.data_edicao_formatada || 'Data não disponível'}
                </div>
            ` : ''}
        </div>
    `;
}

// Função para criar tag
function criarTagObs(texto) {
    const tagClasses = {
        'financeiro': 'tag-success',
        'documentacao': 'tag-info',
        'pendencia': 'tag-danger',
        'importante': 'tag-warning',
        'atendimento': 'tag-primary',
        'geral': 'tag-secondary',
        'urgente': 'tag-danger',
        'alta': 'tag-warning'
    };

    const classe = tagClasses[texto.toLowerCase()] || 'tag-primary';
    return `<span class="tag ${classe}">${texto}</span>`;
}

// Função para filtrar observações
function filtrarObservacoes(observacoes, filtro) {
    if (!observacoes || observacoes.length === 0) return [];

    switch (filtro) {
        case 'recent':
            // Observações recentes (marcadas como recentes no backend)
            return observacoes.filter(obs => obs.recente == '1');

        case 'important':
            return observacoes.filter(obs => obs.importante == '1' || obs.categoria === 'importante');

        default:
            return observacoes;
    }
}

// Função para atualizar paginação
function atualizarPaginacaoObservacoes(total) {
    const totalPages = Math.ceil(total / observacoesPerPage);
    const startIndex = (currentObservacaoPage - 1) * observacoesPerPage + 1;
    const endIndex = Math.min(startIndex + observacoesPerPage - 1, total);

    // Atualizar informações
    const showingEl = document.getElementById('observacoesShowing');
    const totalEl = document.getElementById('observacoesTotal');

    if (showingEl) showingEl.textContent = `${startIndex}-${endIndex}`;
    if (totalEl) totalEl.textContent = total;

    // Atualizar botões
    const prevBtn = document.getElementById('prevObservacoes');
    const nextBtn = document.getElementById('nextObservacoes');
    const pageNumber = document.querySelector('.observacoes-pagination .page-number');

    if (prevBtn) prevBtn.disabled = currentObservacaoPage === 1;
    if (nextBtn) nextBtn.disabled = currentObservacaoPage === totalPages || totalPages === 0;
    if (pageNumber) pageNumber.textContent = totalPages > 0 ? `${currentObservacaoPage} / ${totalPages}` : '0 / 0';
}

// Função para atualizar contador no badge da tab
function atualizarContadorObservacoes() {
    const badge = document.getElementById('observacoesCountBadge');
    if (!badge) return;

    const count = observacoesData.length;

    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}

function salvarObservacao() {
    const texto = document.getElementById('observacaoTexto')?.value.trim();
    const categoria = document.getElementById('observacaoCategoria')?.value;
    const prioridade = document.getElementById('observacaoPrioridade')?.value;
    const importante = document.getElementById('observacaoImportante')?.checked;

    if (!texto) {
        alert('Por favor, digite uma observação.');
        return;
    }

    // VERIFICAR SE É EDIÇÃO OU NOVA OBSERVAÇÃO
    const editId = document.getElementById('formNovaObservacao').dataset.editId;
    const isEdicao = editId && editId !== '';

    // Dados para enviar
    const dados = {
        associado_id: currentAssociadoIdObs,
        observacao: texto,
        categoria: categoria || 'geral',
        prioridade: prioridade || 'media',
        importante: importante ? 1 : 0
    };

    // Se for edição, adicionar o ID
    if (isEdicao) {
        dados.id = editId;
    }

    // Mostrar loading no botão
    const btnSalvar = document.querySelector('#modalNovaObservacao button[onclick="salvarObservacao()"]');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> ' + (isEdicao ? 'Atualizando...' : 'Salvando...');
    btnSalvar.disabled = true;

    // Fazer requisição
    $.ajax({
        url: '../api/observacoes/salvar.php',
        method: 'POST',
        data: JSON.stringify(dados),
        contentType: 'application/json',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                // Fechar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalNovaObservacao'));
                modal.hide();

                // Limpar formulário
                document.getElementById('formNovaObservacao').reset();
                delete document.getElementById('formNovaObservacao').dataset.editId;

                // CORREÇÃO PRINCIPAL: Forçar recarregamento das observações
                carregarObservacoes(currentAssociadoIdObs, true); // true = forçar reload

                // ATUALIZAR TAMBÉM AS OBSERVAÇÕES DA VISÃO GERAL
                if (associadoAtual && associadoAtual.id) {
                    setTimeout(() => {
                        carregarObservacoesVisaoGeral(associadoAtual.id);
                    }, 500);
                }

                // Mostrar mensagem de sucesso
                mostrarNotificacaoObs(response.message || 'Operação realizada com sucesso!', 'success');
            } else {
                alert('Erro: ' + (response.message || 'Erro desconhecido'));
            }
        },
        error: function () {
            const operacao = isEdicao ? 'atualizar' : 'salvar';
            alert(`Erro ao ${operacao} observação. Tente novamente.`);
        },
        complete: function () {
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    });
}

// Função para editar observação
function editarObservacao(id) {
    const obs = observacoesData.find(o => o.id == id);
    if (!obs) return;

    // Preencher modal com dados existentes
    document.getElementById('observacaoTexto').value = obs.observacao;
    document.getElementById('observacaoCategoria').value = obs.categoria || 'geral';
    document.getElementById('observacaoPrioridade').value = obs.prioridade || 'media';
    document.getElementById('observacaoImportante').checked = obs.importante == '1';

    // Armazenar ID para edição
    document.getElementById('formNovaObservacao').dataset.editId = id;

    // Alterar título do modal
    document.querySelector('#modalNovaObservacao .modal-title').innerHTML =
        '<i class="fas fa-edit me-2"></i> Editar Observação';

    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('modalNovaObservacao'));
    modal.show();
}

function criarModalConfirmacaoExclusao() {
    // Verificar se já existe
    if (document.getElementById('modalConfirmarExclusao')) {
        return;
    }

    // Criar o modal HTML
    const modalHTML = `
        <div class="modal fade" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border-radius: 16px;">
                    
                    <!-- Header com ícone de alerta -->
                    <div class="modal-header" style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; border-radius: 16px 16px 0 0; border: none; padding: 2rem 2rem 1rem 2rem;">
                        <div style="width: 100%; text-align: center;">
                            <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2.5rem; color: white;"></i>
                            </div>
                            <h4 style="margin: 0; font-weight: 700; font-size: 1.5rem;">⚠️ ATENÇÃO - Ação Irreversível</h4>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 15px; right: 15px;"></button>
                    </div>

                    <!-- Corpo com aviso -->
                    <div class="modal-body" style="padding: 2rem; text-align: center;">
                        <div style="background: #fff3cd; border: 2px solid #ffeaa7; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                            <h5 style="color: #856404; margin: 0 0 1rem 0; font-weight: 600;">
                                🗂️ Você está prestes a excluir uma observação
                            </h5>
                            <p style="color: #856404; margin: 0; font-size: 1rem; line-height: 1.5;">
                                <strong>As observações contêm o histórico completo do associado na ASSEGO.</strong><br>
                                Esta informação pode ser crucial para atendimentos futuros.
                            </p>
                        </div>

                        <div style="background: #f8d7da; border: 2px solid #f5c6cb; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
                            <h6 style="color: #721c24; margin: 0 0 0.5rem 0; font-weight: 600;">
                                ❌ Esta ação NÃO pode ser desfeita
                            </h6>
                            <p style="color: #721c24; margin: 0; font-size: 0.9rem;">
                                Uma vez excluída, a observação será perdida permanentemente.
                            </p>
                        </div>

                        <div style="font-size: 1.1rem; color: #495057; font-weight: 500; margin-bottom: 1rem;">
                            Tem <strong>absoluta certeza</strong> que deseja continuar?
                        </div>
                    </div>

                    <!-- Footer com botões estilizados -->
                    <div class="modal-footer" style="border: none; padding: 0 2rem 2rem 2rem; gap: 1rem;">
                        <button type="button" 
                                class="btn btn-cancelar-exclusao" 
                                data-bs-dismiss="modal" 
                                style="background: #6c757d; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; flex: 1; transition: all 0.3s ease;">
                            <i class="fas fa-shield-alt me-2"></i>
                            Cancelar (Recomendado)
                        </button>
                        
                        <button type="button" 
                                id="btnConfirmarExclusaoFinal"
                                class="btn btn-confirmar-exclusao" 
                                style="background: #dc3545; color: white; border: none; padding: 0.75rem 2rem; border-radius: 8px; font-weight: 600; flex: 1; transition: all 0.3s ease;">
                            <i class="fas fa-trash-alt me-2"></i>
                            Sim, Excluir Definitivamente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Adicionar CSS
    const style = document.createElement('style');
    style.textContent = `
        /* Animação suave para o modal */
        #modalConfirmarExclusao .modal-content {
            animation: modalAppear 0.3s ease-out;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: scale(0.7) translateY(-50px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Efeitos hover nos botões */
        .btn-cancelar-exclusao:hover {
            background: #5a6268 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
        }

        .btn-confirmar-exclusao:hover {
            background: #c82333 !important;
            transform: scale(1.02) !important;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4) !important;
        }

        #modalConfirmarExclusao button {
            transition: all 0.3s ease !important;
        }
    `;

    // Adicionar ao DOM
    document.head.appendChild(style);
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Event listener para o botão de confirmação
    document.getElementById('btnConfirmarExclusaoFinal').addEventListener('click', function () {
        const id = window.observacaoParaExcluir;

        if (!id) {
            alert('Erro: ID da observação não encontrado');
            return;
        }

        // Fechar modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmarExclusao'));
        modal.hide();

        // Mostrar loading no botão
        const textoOriginal = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Excluindo...';
        this.disabled = true;

        // Fazer requisição de exclusão
        $.ajax({
            url: '../api/observacoes/excluir.php',
            method: 'POST',
            data: JSON.stringify({ id: id }),
            contentType: 'application/json',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    carregarObservacoes(currentAssociadoIdObs);

                    // ATUALIZAR TAMBÉM AS OBSERVAÇÕES DA VISÃO GERAL
                    if (associadoAtual && associadoAtual.id) {
                        setTimeout(() => {
                            carregarObservacoesVisaoGeral(associadoAtual.id);
                        }, 500);
                    }

                    mostrarNotificacaoObs('📋 Observação excluída com sucesso!', 'success');
                } else {
                    alert('❌ Erro ao excluir observação: ' + (response.message || 'Erro desconhecido'));
                }
            },
            error: function () {
                alert('❌ Erro de conexão ao excluir observação. Tente novamente.');
            },
            complete: function () {
                // Restaurar botão
                document.getElementById('btnConfirmarExclusaoFinal').innerHTML = textoOriginal;
                document.getElementById('btnConfirmarExclusaoFinal').disabled = false;

                // Limpar ID armazenado
                window.observacaoParaExcluir = null;
            }
        });
    });
}

// Função para excluir observação (VERSÃO FINAL)
function excluirObservacao(id) {
    // Criar modal se não existir
    criarModalConfirmacaoExclusao();

    // Armazenar ID globalmente
    window.observacaoParaExcluir = id;

    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('modalConfirmarExclusao'));
    modal.show();
}

// Função para alternar importância
function toggleImportanteObs(id) {
    const obs = observacoesData.find(o => o.id == id);
    if (!obs) return;

    $.ajax({
        url: '../api/observacoes/toggle-importante.php',
        method: 'POST',
        data: JSON.stringify({ id: id }),
        contentType: 'application/json',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                // CORREÇÃO: Forçar recarregamento após toggle
                carregarObservacoes(currentAssociadoIdObs, true); // true = forçar reload

                // ATUALIZAR TAMBÉM AS OBSERVAÇÕES DA VISÃO GERAL
                if (associadoAtual && associadoAtual.id) {
                    setTimeout(() => {
                        carregarObservacoesVisaoGeral(associadoAtual.id);
                    }, 500);
                }

                const novoStatus = response.data.importante;
                mostrarNotificacaoObs(
                    novoStatus ? 'Marcada como importante!' : 'Removida das importantes',
                    'success'
                );
            }
        },
        error: function () {
            console.error('Erro ao alterar importância');
        }
    });
}

function resetarObservacoesCache() {
    observacoesData = [];
    currentObservacaoPage = 1;
    currentFilterObs = 'all';
    currentAssociadoIdObs = null;
    window.carregandoObservacoes = false;

    // Esconder badge
    const badge = document.getElementById('observacoesCountBadge');
    if (badge) {
        badge.style.display = 'none';
        badge.textContent = '0';
    }

    // Limpar container
    const container = document.getElementById('observacoesContainer');
    if (container) {
        container.innerHTML = '';
    }

    console.log('Cache de observações resetado completamente');
}

// Função auxiliar para formatar data e hora
function formatarDataHoraObs(dataString) {
    if (!dataString) return '-';

    const data = new Date(dataString);
    const dia = String(data.getDate()).padStart(2, '0');
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const ano = data.getFullYear();
    const hora = String(data.getHours()).padStart(2, '0');
    const minuto = String(data.getMinutes()).padStart(2, '0');

    return `${dia}/${mes}/${ano} às ${hora}:${minuto}`;
}

// Função para mostrar notificação
function mostrarNotificacaoObs(mensagem, tipo = 'info') {
    // Criar elemento de notificação
    const notificacao = document.createElement('div');
    notificacao.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    notificacao.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notificacao.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(notificacao);

    // Remover após 3 segundos
    setTimeout(() => {
        notificacao.remove();
    }, 3000);
}

// Função para mostrar erro nas observações
function mostrarErroObservacoes(mensagem) {
    const container = document.getElementById('observacoesContainer');
    if (!container) return;

    container.innerHTML = `
        <div class="alert alert-danger m-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${mensagem}
        </div>
    `;
}

// Event Listeners para observações
$(document).ready(function () {
    // Busca em observações
    $(document).on('input', '#searchObservacoes', function () {
        clearTimeout(window.searchObsTimeout);
        window.searchObsTimeout = setTimeout(() => {
            renderizarObservacoes();
        }, 300);
    });

    // Filtros de observações
    $(document).on('click', '.observacoes-filter-buttons .filter-btn', function () {
        $('.observacoes-filter-buttons .filter-btn').removeClass('active');
        $(this).addClass('active');
        currentFilterObs = $(this).data('filter');
        currentObservacaoPage = 1;
        renderizarObservacoes();
    });

    // Paginação de observações
    $(document).on('click', '#prevObservacoes', function () {
        if (currentObservacaoPage > 1) {
            currentObservacaoPage--;
            renderizarObservacoes();
        }
    });

    $(document).on('click', '#nextObservacoes', function () {
        const totalPages = Math.ceil(observacoesData.length / observacoesPerPage);
        if (currentObservacaoPage < totalPages) {
            currentObservacaoPage++;
            renderizarObservacoes();
        }
    });

    // Reset do modal ao fechar
    $(document).on('hidden.bs.modal', '#modalNovaObservacao', function () {
        document.getElementById('formNovaObservacao').reset();
        delete document.getElementById('formNovaObservacao').dataset.editId;
        // Restaurar título original
        document.querySelector('#modalNovaObservacao .modal-title').innerHTML =
            '<i class="fas fa-plus-circle me-2"></i> Nova Observação';
    });
});

console.log('✓ Sistema de Observações carregado com sucesso!');

// =====================================================
// SISTEMA DE EDIÇÃO DENTRO DO MODAL DE VISUALIZAÇÃO
// =====================================================

// Variável global para controlar o modo de edição
let modoEdicaoAtivo = false;
let dadosOriginaisAssociado = null;

// Função para inicializar o botão de edição baseado nas permissões
function inicializarBotaoEdicao() {
    const btnEditar = document.getElementById('btnEditarModal');
    
    if (!btnEditar) return;
    
    // Verifica permissões para mostrar ou esconder o botão
    if (permissoesUsuario.podeEditarCompleto || permissoesUsuario.podeEditarContato) {
        btnEditar.style.display = 'flex';
    } else {
        btnEditar.style.display = 'none';
    }
}

// Função para alternar o modo de edição
function toggleModoEdicao() {
    if (!associadoAtual) {
        alert('Nenhum associado selecionado!');
        return;
    }

    modoEdicaoAtivo = !modoEdicaoAtivo;

    const btnEditar = document.getElementById('btnEditarModal');
    const btnSalvar = document.getElementById('btnSalvarModal');
    const btnCancelar = document.getElementById('btnCancelarModal');
    const modal = document.getElementById('modalAssociado');

    if (modoEdicaoAtivo) {
        // Salva os dados originais para poder cancelar
        dadosOriginaisAssociado = JSON.parse(JSON.stringify(associadoAtual));
        
        // Atualiza botões
        btnEditar.style.display = 'none';
        btnSalvar.style.display = 'flex';
        btnCancelar.style.display = 'flex';
        
        // Adiciona classe de modo edição
        modal.classList.add('modal-edit-mode');
        
        // Transforma campos em editáveis
        ativarModoEdicao();
        
        console.log('🖊️ Modo edição ativado');
    } else {
        // Desativa modo edição
        desativarModoEdicao();
    }
}

// Função para ativar modo de edição nos campos
function ativarModoEdicao() {
    // Renderiza novamente as tabs com campos editáveis
    preencherTabVisaoGeralEditavel(associadoAtual);
    preencherTabMilitarEditavel(associadoAtual);
    preencherTabFinanceiroEditavel(associadoAtual);
    preencherTabContatoEditavel(associadoAtual);
    preencherTabDependentesEditavel(associadoAtual);
}

// Função para desativar modo edição
function desativarModoEdicao() {
    modoEdicaoAtivo = false;
    
    const btnEditar = document.getElementById('btnEditarModal');
    const btnSalvar = document.getElementById('btnSalvarModal');
    const btnCancelar = document.getElementById('btnCancelarModal');
    const modal = document.getElementById('modalAssociado');
    
    btnEditar.style.display = 'flex';
    btnSalvar.style.display = 'none';
    btnCancelar.style.display = 'none';
    
    modal.classList.remove('modal-edit-mode');
    
    // Re-renderiza as tabs no modo visualização
    preencherTabVisaoGeral(associadoAtual);
    preencherTabMilitar(associadoAtual);
    preencherTabFinanceiro(associadoAtual);
    preencherTabContato(associadoAtual);
    preencherTabDependentes(associadoAtual);
    
    console.log('👁️ Modo visualização ativado');
}

// Função para cancelar edição
function cancelarEdicaoModal() {
    if (dadosOriginaisAssociado) {
        // Restaura dados originais
        associadoAtual = dadosOriginaisAssociado;
        dadosOriginaisAssociado = null;
    }
    
    desativarModoEdicao();
}

// Função para salvar edição
function salvarEdicaoModal() {
    if (!associadoAtual || !associadoAtual.id) {
        alert('Erro: Nenhum associado selecionado!');
        return;
    }

    // Coleta dados dos campos editáveis e mescla com dados existentes
    const dadosAtualizados = coletarDadosFormularioModal();
    
    if (!dadosAtualizados) {
        return; // Erro na validação
    }

    // Mostra loading
    const btnSalvar = document.getElementById('btnSalvarModal');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    btnSalvar.disabled = true;

    // Cria FormData para enviar como POST (API espera $_POST, não JSON)
    const formData = new FormData();
    
    // Adiciona todos os dados ao FormData
    for (const [key, value] of Object.entries(dadosAtualizados)) {
        if (key === 'dependentes' && Array.isArray(value)) {
            // Dependentes precisam ser enviados como array
            value.forEach((dep, index) => {
                for (const [depKey, depValue] of Object.entries(dep)) {
                    formData.append(`dependentes[${index}][${depKey}]`, depValue || '');
                }
            });
        } else {
            formData.append(key, value ?? '');
        }
    }

    // Faz requisição para API usando FormData
    $.ajax({
        url: `../api/atualizar_associado.php?id=${associadoAtual.id}`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Atualiza dados locais
                Object.assign(associadoAtual, dadosAtualizados);
                
                // Atualiza também no array principal
                const index = todosAssociados.findIndex(a => a.id == associadoAtual.id);
                if (index !== -1) {
                    Object.assign(todosAssociados[index], dadosAtualizados);
                }
                
                // Atualiza também em associadosFiltrados
                const indexFiltrado = associadosFiltrados.findIndex(a => a.id == associadoAtual.id);
                if (indexFiltrado !== -1) {
                    Object.assign(associadosFiltrados[indexFiltrado], dadosAtualizados);
                }
                
                // Atualiza header do modal
                atualizarHeaderModal(associadoAtual);
                
                // Desativa modo edição
                desativarModoEdicao();
                
                // Re-renderiza tabela
                renderizarPagina();
                
                // Mostra mensagem de sucesso
                mostrarNotificacao('Dados atualizados com sucesso!', 'success');
                
                console.log('✅ Associado atualizado com sucesso');
            } else {
                alert('Erro ao salvar: ' + (response.message || 'Erro desconhecido'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Erro ao salvar:', error);
            console.error('Response:', xhr.responseText);
            alert('Erro ao salvar as alterações. Tente novamente.');
        },
        complete: function() {
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    });
}

// Função para coletar dados do formulário do modal
// Mescla dados editados com dados existentes do associado
function coletarDadosFormularioModal() {
    // Começa com todos os dados atuais do associado (para não perder campos não editados)
    const dados = {
        // Campos obrigatórios - usa dados existentes como fallback
        nome: associadoAtual.nome || '',
        cpf: (associadoAtual.cpf || '').replace(/\D/g, ''),
        rg: associadoAtual.rg || 'N/I', // RG pode ser "N/I" (Não Informado) se vazio
        telefone: (associadoAtual.telefone || '').replace(/\D/g, ''),
        situacao: associadoAtual.situacao || 'Filiado',
        
        // Dados pessoais
        nasc: associadoAtual.nasc || '',
        sexo: associadoAtual.sexo || '',
        estadoCivil: associadoAtual.estadoCivil || '',
        escolaridade: associadoAtual.escolaridade || '',
        email: associadoAtual.email || '',
        
        // Dados militares
        corporacao: associadoAtual.corporacao || '',
        patente: associadoAtual.patente || '',
        categoria: associadoAtual.categoria || '',
        lotacao: associadoAtual.lotacao || '',
        unidade: associadoAtual.unidade || '',
        
        // Endereço
        cep: (associadoAtual.cep || '').replace(/\D/g, ''),
        endereco: associadoAtual.endereco || '',
        numero: associadoAtual.numero || '',
        complemento: associadoAtual.complemento || '',
        bairro: associadoAtual.bairro || '',
        cidade: associadoAtual.cidade || '',
        
        // Dados financeiros
        tipoAssociado: associadoAtual.tipoAssociado || '',
        situacaoFinanceira: associadoAtual.situacaoFinanceira || '',
        vinculoServidor: associadoAtual.vinculoServidor || '',
        localDebito: associadoAtual.localDebito || '',
        agencia: associadoAtual.agencia || '',
        operacao: associadoAtual.operacao || '',
        contaCorrente: associadoAtual.contaCorrente || '',
        doador: associadoAtual.doador || '0',
        tipoAssociadoServico: associadoAtual.tipoAssociadoServico || '',
        
        // Outros
        indicacao: associadoAtual.indicacao || '',
        dataFiliacao: associadoAtual.data_filiacao || '',
        observacoes: associadoAtual.observacoes || ''
    };
    
    // Sobrescreve com valores dos campos editados (se existirem)
    const camposEditaveis = {
        // Dados Pessoais
        'edit_nome': 'nome',
        'edit_rg': 'rg',
        'edit_nasc': 'nasc',
        'edit_sexo': 'sexo',
        'edit_estadoCivil': 'estadoCivil',
        'edit_escolaridade': 'escolaridade',
        'edit_situacao': 'situacao',
        
        // Dados Militares
        'edit_corporacao': 'corporacao',
        'edit_patente': 'patente',
        'edit_categoria': 'categoria',
        'edit_lotacao': 'lotacao',
        'edit_unidade': 'unidade',
        
        // Contato
        'edit_telefone': 'telefone',
        'edit_email': 'email',
        'edit_cep': 'cep',
        'edit_endereco': 'endereco',
        'edit_numero': 'numero',
        'edit_complemento': 'complemento',
        'edit_bairro': 'bairro',
        'edit_cidade': 'cidade',
        
        // Financeiro
        'edit_tipoAssociado': 'tipoAssociado',
        'edit_situacaoFinanceira': 'situacaoFinanceira',
        'edit_vinculoServidor': 'vinculoServidor',
        'edit_localDebito': 'localDebito',
        'edit_agencia': 'agencia',
        'edit_operacao': 'operacao',
        'edit_contaCorrente': 'contaCorrente',
        'edit_doador': 'doador'
    };
    
    // Atualiza apenas os campos que foram editados
    for (const [elementId, campoNome] of Object.entries(camposEditaveis)) {
        const elemento = document.getElementById(elementId);
        if (elemento) {
            let valor = elemento.value;
            
            // Limpa formatação de campos específicos
            if (['telefone', 'cep', 'cpf'].includes(campoNome)) {
                valor = valor.replace(/\D/g, '');
            } else {
                valor = valor.trim();
            }
            
            dados[campoNome] = valor;
        }
    }
    
    // Coleta dependentes se existirem
    dados.dependentes = coletarDependentesEditados();
    
    // Validações básicas
    if (!dados.nome || dados.nome.length < 3) {
        alert('Nome deve ter pelo menos 3 caracteres');
        return null;
    }
    
    if (!dados.cpf || dados.cpf.length !== 11) {
        alert('CPF inválido');
        return null;
    }
    
    if (!dados.telefone || dados.telefone.length < 10) {
        alert('Telefone inválido');
        return null;
    }
    
    return dados;
}

// Função para coletar dependentes editados
function coletarDependentesEditados() {
    const dependentes = [];
    const container = document.getElementById('dependentesEditaveis');
    
    if (!container) return dependentes;
    
    const linhas = container.querySelectorAll('.dependente-item');
    linhas.forEach(linha => {
        const nome = linha.querySelector('[name="dep_nome"]')?.value?.trim();
        const dataNascimento = linha.querySelector('[name="dep_data_nascimento"]')?.value;
        const parentesco = linha.querySelector('[name="dep_parentesco"]')?.value;
        const sexo = linha.querySelector('[name="dep_sexo"]')?.value;
        
        if (nome) {
            dependentes.push({
                nome: nome,
                data_nascimento: dataNascimento || '',
                parentesco: parentesco || '',
                sexo: sexo || ''
            });
        }
    });
    
    return dependentes;
}

// Função para preencher Tab Visão Geral no modo editável
function preencherTabVisaoGeralEditavel(associado) {
    const overviewTab = document.getElementById('overview-tab');
    
    // Calcula idade
    let idade = '-';
    if (associado.nasc && associado.nasc !== '0000-00-00') {
        const hoje = new Date();
        const nascimento = new Date(associado.nasc);
        idade = Math.floor((hoje - nascimento) / (365.25 * 24 * 60 * 60 * 1000));
        idade = idade + ' anos';
    }

    // Data de nascimento formatada para input date
    let dataNascInput = '';
    if (associado.nasc && associado.nasc !== '0000-00-00') {
        dataNascInput = associado.nasc;
    }

    overviewTab.innerHTML = `
        <!-- Badge de modo edição -->
        <div class="edit-mode-badge" style="display: block; position: relative; margin: 1rem 2rem 0;">
            <i class="fas fa-edit"></i> Modo Edição Ativo
        </div>
        
        <!-- Stats Section (apenas visualização) -->
        <div class="stats-section">
            <div class="stat-item">
                <div class="stat-value">${associado.total_servicos || 0}</div>
                <div class="stat-label">Serviços Ativos</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${associado.total_dependentes || 0}</div>
                <div class="stat-label">Dependentes</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">${associado.total_documentos || 0}</div>
                <div class="stat-label">Documentos</div>
            </div>
        </div>
        
        <!-- Overview Grid Editável -->
        <div class="overview-grid">
            <!-- Dados Pessoais Editáveis -->
            <div class="overview-card modo-edicao">
                <div class="overview-card-header">
                    <div class="overview-card-icon blue">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h4 class="overview-card-title">Dados Pessoais</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_nome">Nome Completo</label>
                        <input type="text" id="edit_nome" class="form-control" value="${associado.nome || ''}" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                    </div>
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_cpf">CPF</label>
                        <input type="text" id="edit_cpf" class="form-control" value="${formatarCPF(associado.cpf)}" disabled>
                        <small class="text-muted">CPF não pode ser alterado</small>
                    </div>
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_rg">RG</label>
                        <input type="text" id="edit_rg" class="form-control" value="${associado.rg || ''}" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                    </div>
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_nasc">Data de Nascimento</label>
                        <input type="date" id="edit_nasc" class="form-control" value="${dataNascInput}" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                    </div>
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_sexo">Sexo</label>
                        <select id="edit_sexo" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                            <option value="">Selecione</option>
                            <option value="M" ${associado.sexo === 'M' ? 'selected' : ''}>Masculino</option>
                            <option value="F" ${associado.sexo === 'F' ? 'selected' : ''}>Feminino</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Informações de Filiação Editáveis -->
            <div class="overview-card modo-edicao">
                <div class="overview-card-header">
                    <div class="overview-card-icon green">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <h4 class="overview-card-title">Informações de Filiação</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_situacao">Situação</label>
                        <select id="edit_situacao" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                            <option value="Filiado" ${associado.situacao === 'Filiado' ? 'selected' : ''}>Filiado</option>
                            <option value="Desfiliado" ${associado.situacao === 'Desfiliado' ? 'selected' : ''}>Desfiliado</option>
                            <option value="Falecido" ${associado.situacao === 'Falecido' ? 'selected' : ''}>Falecido</option>
                        </select>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Data de Filiação</span>
                        <span class="overview-value">${formatarData(associado.data_filiacao)}</span>
                    </div>
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_escolaridade">Escolaridade</label>
                        <select id="edit_escolaridade" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                            <option value="">Selecione</option>
                            <option value="Fundamental Incompleto" ${associado.escolaridade === 'Fundamental Incompleto' ? 'selected' : ''}>Fundamental Incompleto</option>
                            <option value="Fundamental Completo" ${associado.escolaridade === 'Fundamental Completo' ? 'selected' : ''}>Fundamental Completo</option>
                            <option value="Médio Incompleto" ${associado.escolaridade === 'Médio Incompleto' ? 'selected' : ''}>Médio Incompleto</option>
                            <option value="Médio Completo" ${associado.escolaridade === 'Médio Completo' ? 'selected' : ''}>Médio Completo</option>
                            <option value="Superior Incompleto" ${associado.escolaridade === 'Superior Incompleto' ? 'selected' : ''}>Superior Incompleto</option>
                            <option value="Superior Completo" ${associado.escolaridade === 'Superior Completo' ? 'selected' : ''}>Superior Completo</option>
                            <option value="Pós-graduação" ${associado.escolaridade === 'Pós-graduação' ? 'selected' : ''}>Pós-graduação</option>
                            <option value="Mestrado" ${associado.escolaridade === 'Mestrado' ? 'selected' : ''}>Mestrado</option>
                            <option value="Doutorado" ${associado.escolaridade === 'Doutorado' ? 'selected' : ''}>Doutorado</option>
                        </select>
                    </div>
                    <div class="overview-item campo-editavel">
                        <label class="overview-label" for="edit_estadoCivil">Estado Civil</label>
                        <select id="edit_estadoCivil" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                            <option value="">Selecione</option>
                            <option value="Solteiro(a)" ${associado.estadoCivil === 'Solteiro(a)' ? 'selected' : ''}>Solteiro(a)</option>
                            <option value="Casado(a)" ${associado.estadoCivil === 'Casado(a)' ? 'selected' : ''}>Casado(a)</option>
                            <option value="Divorciado(a)" ${associado.estadoCivil === 'Divorciado(a)' ? 'selected' : ''}>Divorciado(a)</option>
                            <option value="Viúvo(a)" ${associado.estadoCivil === 'Viúvo(a)' ? 'selected' : ''}>Viúvo(a)</option>
                            <option value="União Estável" ${associado.estadoCivil === 'União Estável' ? 'selected' : ''}>União Estável</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Informações Adicionais (apenas visualização) -->
            <div class="overview-card">
                <div class="overview-card-header">
                    <div class="overview-card-icon purple">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h4 class="overview-card-title">Informações Adicionais</h4>
                </div>
                <div class="overview-card-content">
                    <div class="overview-item">
                        <span class="overview-label">Indicação</span>
                        <span class="overview-value">${associado.indicacao || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Tipo de Associado</span>
                        <span class="overview-value">${associado.tipoAssociado || '-'}</span>
                    </div>
                    <div class="overview-item">
                        <span class="overview-label">Situação Financeira</span>
                        <span class="overview-value">${associado.situacaoFinanceira || '-'}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Função para preencher Tab Militar no modo editável
function preencherTabMilitarEditavel(associado) {
    const militarTab = document.getElementById('militar-tab');
    
    // Arrays de opções - USAR VALORES DO BANCO DE DADOS
    const corporacoes = [
        'Polícia Militar',
        'Bombeiro Militar', 
        'Polícia Civil',
        'Polícia Penal',
        'Outro',
        'Agregados'
    ];
    
    // Patentes - incluir todas as variações possíveis
    const patentes = [
        'Soldado', 'Cabo', 
        '3º Sargento', '2º Sargento', '1º Sargento', 'Sargento',
        'Subtenente', 'Aspirante', 
        '2º Tenente', '1º Tenente', 
        'Capitão', 'Major', 'Tenente Coronel', 'Coronel',
        // Patentes de bombeiros/outras corporações que podem ter nomes diferentes
        'Tenente', 'Tenente-Coronel',
        'Agregados'
    ];
    
    const categorias = ['Ativa', 'Reserva', 'Pensionista', 'Agregados'];
    
    // Função para verificar se o valor está selecionado (case insensitive e normalizado)
    const isSelected = (valorAtual, opcao) => {
        if (!valorAtual) return false;
        const valorNorm = valorAtual.toString().toLowerCase().trim();
        const opcaoNorm = opcao.toString().toLowerCase().trim();
        return valorNorm === opcaoNorm;
    };
    
    // Verificar se o valor atual existe nas opções, senão adicionar
    const corporacaoAtual = associado.corporacao || '';
    const patenteAtual = associado.patente || '';
    const categoriaAtual = associado.categoria || '';
    
    // Se a corporação atual não está na lista e não está vazia, adicionar
    let corporacoesOptions = [...corporacoes];
    if (corporacaoAtual && !corporacoes.some(c => isSelected(corporacaoAtual, c))) {
        corporacoesOptions.unshift(corporacaoAtual);
    }
    
    // Se a patente atual não está na lista e não está vazia, adicionar
    let patentesOptions = [...patentes];
    if (patenteAtual && !patentes.some(p => isSelected(patenteAtual, p))) {
        patentesOptions.unshift(patenteAtual);
    }
    
    // Se a categoria atual não está na lista e não está vazia, adicionar
    let categoriasOptions = [...categorias];
    if (categoriaAtual && !categorias.some(c => isSelected(categoriaAtual, c))) {
        categoriasOptions.unshift(categoriaAtual);
    }

    militarTab.innerHTML = `
        <div class="detail-section modo-edicao">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="section-title">Informações Militares</h3>
                <span class="badge bg-primary ms-2">Editando</span>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_corporacao">Corporação</label>
                    <select id="edit_corporacao" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                        <option value="">Selecione</option>
                        ${corporacoesOptions.map(c => `<option value="${c}" ${isSelected(corporacaoAtual, c) ? 'selected' : ''}>${c}</option>`).join('')}
                    </select>
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_patente">Patente</label>
                    <select id="edit_patente" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                        <option value="">Selecione</option>
                        ${patentesOptions.map(p => `<option value="${p}" ${isSelected(patenteAtual, p) ? 'selected' : ''}>${p}</option>`).join('')}
                    </select>
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_categoria">Categoria</label>
                    <select id="edit_categoria" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                        <option value="">Selecione</option>
                        ${categoriasOptions.map(c => `<option value="${c}" ${isSelected(categoriaAtual, c) ? 'selected' : ''}>${c}</option>`).join('')}
                    </select>
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_lotacao">Lotação</label>
                    <input type="text" id="edit_lotacao" class="form-control" value="${associado.lotacao || ''}" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_unidade">Unidade</label>
                    <input type="text" id="edit_unidade" class="form-control" value="${associado.unidade || ''}" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                </div>
            </div>
        </div>
    `;
}

// Função para preencher Tab Contato no modo editável
function preencherTabContatoEditavel(associado) {
    const contatoTab = document.getElementById('contato-tab');

    contatoTab.innerHTML = `
        <div class="detail-section modo-edicao">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <h3 class="section-title">Informações de Contato</h3>
                <span class="badge bg-primary ms-2">Editando</span>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_telefone">Telefone</label>
                    <input type="text" id="edit_telefone" class="form-control" value="${associado.telefone || ''}" placeholder="(00) 00000-0000">
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_email">E-mail</label>
                    <input type="email" id="edit_email" class="form-control" value="${associado.email || ''}" placeholder="email@exemplo.com">
                </div>
            </div>
        </div>
        
        <div class="detail-section modo-edicao" style="margin-top: 2rem;">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3 class="section-title">Endereço</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_cep">CEP</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" id="edit_cep" class="form-control" value="${associado.cep || ''}" placeholder="00000-000" style="flex: 1;">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="buscarCepEdicao()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="detail-item campo-editavel" style="grid-column: span 2;">
                    <label class="detail-label" for="edit_endereco">Endereço</label>
                    <input type="text" id="edit_endereco" class="form-control" value="${associado.endereco || ''}" placeholder="Rua, Avenida...">
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_numero">Número</label>
                    <input type="text" id="edit_numero" class="form-control" value="${associado.numero || ''}" placeholder="Nº">
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_complemento">Complemento</label>
                    <input type="text" id="edit_complemento" class="form-control" value="${associado.complemento || ''}" placeholder="Apto, Bloco...">
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_bairro">Bairro</label>
                    <input type="text" id="edit_bairro" class="form-control" value="${associado.bairro || ''}" placeholder="Bairro">
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_cidade">Cidade</label>
                    <input type="text" id="edit_cidade" class="form-control" value="${associado.cidade || ''}" placeholder="Cidade">
                </div>
            </div>
        </div>
    `;
}

// Função para buscar CEP no modo edição
function buscarCepEdicao() {
    const cep = document.getElementById('edit_cep').value.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        alert('CEP inválido. Digite 8 números.');
        return;
    }
    
    // Mostra loading
    const btnCep = document.querySelector('[onclick="buscarCepEdicao()"]');
    const textoOriginal = btnCep.innerHTML;
    btnCep.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btnCep.disabled = true;
    
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
            if (data.erro) {
                alert('CEP não encontrado!');
                return;
            }
            
            document.getElementById('edit_endereco').value = data.logradouro || '';
            document.getElementById('edit_bairro').value = data.bairro || '';
            document.getElementById('edit_cidade').value = data.localidade || '';
            
            // Foca no campo número
            document.getElementById('edit_numero').focus();
        })
        .catch(error => {
            console.error('Erro ao buscar CEP:', error);
            alert('Erro ao buscar CEP. Tente novamente.');
        })
        .finally(() => {
            btnCep.innerHTML = textoOriginal;
            btnCep.disabled = false;
        });
}

// Função para preencher Tab Financeiro no modo editável
function preencherTabFinanceiroEditavel(associado) {
    const financeiroTab = document.getElementById('financeiro-tab');
    
    // Opções de tipo de associado
    const tiposAssociado = ['Contribuinte', 'Agregado', 'Pensionista', 'Dependente'];
    const situacoesFinanceiras = ['Adimplente', 'Inadimplente', 'Isento'];
    const locaisDebito = ['SEGPLAN', 'IPASGO', 'BOLETO', 'DÉBITO EM CONTA', 'OUTRO'];
    
    // Função auxiliar para verificar seleção
    const isSelectedFin = (valorAtual, opcao) => {
        if (!valorAtual) return false;
        return valorAtual.toString().toLowerCase().trim() === opcao.toString().toLowerCase().trim();
    };
    
    // Valores atuais
    const tipoAtual = associado.tipoAssociado || associado.tipo_associado || '';
    const situacaoFinAtual = associado.situacaoFinanceira || associado.situacao_financeira || '';
    const localDebitoAtual = associado.localDebito || associado.local_debito || '';
    
    // Verificar se é agregado
    const isAgregado = tipoAtual.toLowerCase().trim() === 'agregado';
    
    // Adicionar valores atuais se não estiverem na lista
    let tiposOptions = [...tiposAssociado];
    if (tipoAtual && !tiposAssociado.some(t => isSelectedFin(tipoAtual, t))) {
        tiposOptions.unshift(tipoAtual);
    }
    
    let situacoesOptions = [...situacoesFinanceiras];
    if (situacaoFinAtual && !situacoesFinanceiras.some(s => isSelectedFin(situacaoFinAtual, s))) {
        situacoesOptions.unshift(situacaoFinAtual);
    }
    
    let locaisOptions = [...locaisDebito];
    if (localDebitoAtual && !locaisDebito.some(l => isSelectedFin(localDebitoAtual, l))) {
        locaisOptions.unshift(localDebitoAtual);
    }
    
    // Calcular valor mensal (buscar do associado ou calcular)
    const valorMensal = associado.valor_mensal || associado.valorMensal || 0;
    const servicosAtivos = associado.total_servicos || associado.servicos_ativos || 0;
    
    // Classe para desabilitar campos quando é agregado
    const disabledAgregado = isAgregado ? 'disabled style="background: #e9ecef; cursor: not-allowed;"' : '';

    financeiroTab.innerHTML = `
        <!-- Card Azul de Resumo (mantém visual bonito) -->
        <div class="financial-summary" style="background: linear-gradient(135deg, #0056D2, #003d94); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; color: white;">
            <div style="display: flex; justify-content: space-around; text-align: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="font-size: 0.75rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">VALOR MENSAL TOTAL</div>
                    <div style="font-size: 2rem; font-weight: 700;">R$ ${parseFloat(valorMensal).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div>
                    <div style="font-size: 0.85rem; opacity: 0.7;">${servicosAtivos} serviços ativos</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">TIPO DE ASSOCIADO</div>
                    <div style="font-size: 1.25rem; font-weight: 600;">${tipoAtual || 'Não definido'}</div>
                    <div style="font-size: 0.85rem; opacity: 0.7;">Define percentual de cobrança</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">SITUAÇÃO FINANCEIRA</div>
                    <div style="font-size: 1.25rem; font-weight: 600;">${situacaoFinAtual || 'Não definida'}</div>
                    <div style="font-size: 0.85rem; opacity: 0.7;">Status atual</div>
                </div>
            </div>
            <div style="text-align: center; margin-top: 1rem;">
                <span class="badge bg-primary" style="font-size: 0.8rem; padding: 0.5rem 1rem;"><i class="fas fa-edit"></i> Editando</span>
                ${isAgregado ? '<br><span class="badge bg-warning text-dark mt-2" style="font-size: 0.75rem;"><i class="fas fa-info-circle"></i> Agregado - Dados bancários não necessários</span>' : ''}
            </div>
        </div>
        
        <div class="detail-section modo-edicao">
            <div class="section-header">
                <div class="section-icon" style="background: linear-gradient(135deg, #0056D2, #003d94); color: white;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h3 class="section-title">Dados Financeiros</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_tipoAssociado">Tipo de Associado</label>
                    <select id="edit_tipoAssociado" class="form-control" onchange="onTipoAssociadoChange(this.value)" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                        <option value="">Selecione</option>
                        ${tiposOptions.map(t => `<option value="${t}" ${isSelectedFin(tipoAtual, t) ? 'selected' : ''}>${t}</option>`).join('')}
                    </select>
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_situacaoFinanceira">Situação Financeira</label>
                    <select id="edit_situacaoFinanceira" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                        <option value="">Selecione</option>
                        ${situacoesOptions.map(s => `<option value="${s}" ${isSelectedFin(situacaoFinAtual, s) ? 'selected' : ''}>${s}</option>`).join('')}
                    </select>
                </div>
                <div class="detail-item campo-editavel campo-agregado">
                    <label class="detail-label" for="edit_vinculoServidor">Vínculo Servidor</label>
                    <input type="text" id="edit_vinculoServidor" class="form-control" value="${associado.vinculoServidor || associado.vinculo_servidor || ''}" ${!permissoesUsuario.podeEditarCompleto || isAgregado ? 'disabled style="background: #e9ecef; cursor: not-allowed;"' : ''}>
                    ${isAgregado ? '<small class="text-muted">Não aplicável para agregados</small>' : ''}
                </div>
                <div class="detail-item campo-editavel campo-agregado">
                    <label class="detail-label" for="edit_localDebito">Local de Débito</label>
                    <select id="edit_localDebito" class="form-control" ${!permissoesUsuario.podeEditarCompleto || isAgregado ? 'disabled style="background: #e9ecef; cursor: not-allowed;"' : ''}>
                        <option value="">Selecione</option>
                        ${locaisOptions.map(l => `<option value="${l}" ${isSelectedFin(localDebitoAtual, l) ? 'selected' : ''}>${l}</option>`).join('')}
                    </select>
                    ${isAgregado ? '<small class="text-muted">Não aplicável para agregados</small>' : ''}
                </div>
            </div>
        </div>
        
        <div class="detail-section modo-edicao" style="margin-top: 2rem; ${isAgregado ? 'opacity: 0.6;' : ''}">
            <div class="section-header">
                <div class="section-icon" style="background: linear-gradient(135deg, ${isAgregado ? '#9ca3af, #6b7280' : '#6366f1, #4f46e5'}); color: white;">
                    <i class="fas fa-university"></i>
                </div>
                <h3 class="section-title">Dados Bancários ${isAgregado ? '<span class="badge bg-secondary ms-2">Não necessário</span>' : ''}</h3>
            </div>
            
            <div class="detail-grid">
                <div class="detail-item campo-editavel campo-agregado">
                    <label class="detail-label" for="edit_agencia">Agência</label>
                    <input type="text" id="edit_agencia" class="form-control" value="${associado.agencia || ''}" ${!permissoesUsuario.podeEditarCompleto || isAgregado ? 'disabled style="background: #e9ecef; cursor: not-allowed;"' : ''}>
                </div>
                <div class="detail-item campo-editavel campo-agregado">
                    <label class="detail-label" for="edit_operacao">Operação</label>
                    <input type="text" id="edit_operacao" class="form-control" value="${associado.operacao || ''}" ${!permissoesUsuario.podeEditarCompleto || isAgregado ? 'disabled style="background: #e9ecef; cursor: not-allowed;"' : ''}>
                </div>
                <div class="detail-item campo-editavel campo-agregado">
                    <label class="detail-label" for="edit_contaCorrente">Conta Corrente</label>
                    <input type="text" id="edit_contaCorrente" class="form-control" value="${associado.contaCorrente || associado.conta_corrente || ''}" ${!permissoesUsuario.podeEditarCompleto || isAgregado ? 'disabled style="background: #e9ecef; cursor: not-allowed;"' : ''}>
                </div>
                <div class="detail-item campo-editavel">
                    <label class="detail-label" for="edit_doador">É Doador?</label>
                    <select id="edit_doador" class="form-control" ${!permissoesUsuario.podeEditarCompleto ? 'disabled' : ''}>
                        <option value="0" ${!associado.doador || associado.doador === '0' || associado.doador === 0 ? 'selected' : ''}>Não</option>
                        <option value="1" ${associado.doador === '1' || associado.doador === 1 || associado.doador === true ? 'selected' : ''}>Sim</option>
                    </select>
                </div>
            </div>
        </div>
    `;
}

// Função para reagir à mudança do tipo de associado
function onTipoAssociadoChange(valor) {
    const isAgregado = valor.toLowerCase().trim() === 'agregado';
    
    // Campos que devem ser desabilitados para agregados
    const camposAgregado = [
        'edit_vinculoServidor',
        'edit_localDebito', 
        'edit_agencia',
        'edit_operacao',
        'edit_contaCorrente'
    ];
    
    camposAgregado.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            campo.disabled = isAgregado;
            campo.style.background = isAgregado ? '#e9ecef' : '';
            campo.style.cursor = isAgregado ? 'not-allowed' : '';
        }
    });
    
    // Atualizar campos militares se for agregado
    if (isAgregado) {
        atualizarCamposMilitaresAgregado();
    }
}

// Função para preencher campos militares como Agregados
function atualizarCamposMilitaresAgregado() {
    const camposMilitares = {
        'edit_corporacao': 'Agregados',
        'edit_patente': 'Agregados',
        'edit_categoria': 'Agregados'
    };
    
    for (const [id, valor] of Object.entries(camposMilitares)) {
        const campo = document.getElementById(id);
        if (campo) {
            // Verifica se a opção Agregados existe, senão adiciona
            let optionExists = false;
            for (let option of campo.options) {
                if (option.value.toLowerCase() === 'agregados') {
                    optionExists = true;
                    option.selected = true;
                    break;
                }
            }
            
            if (!optionExists) {
                const newOption = document.createElement('option');
                newOption.value = 'Agregados';
                newOption.text = 'Agregados';
                newOption.selected = true;
                campo.add(newOption);
            }
        }
    }
}

// Função para preencher Tab Dependentes no modo editável
function preencherTabDependentesEditavel(associado) {
    const dependentesTab = document.getElementById('dependentes-tab');
    
    // Opções de parentesco
    const parentescos = ['Cônjuge', 'Filho(a)', 'Pai', 'Mãe', 'Irmão(ã)', 'Avô(ó)', 'Neto(a)', 'Outro'];
    
    // Gera HTML dos dependentes existentes
    let dependentesHtml = '';
    const dependentes = associado.dependentes || [];
    
    dependentes.forEach((dep, index) => {
        dependentesHtml += gerarHtmlDependenteEditavel(dep, index, parentescos);
    });

    dependentesTab.innerHTML = `
        <div class="detail-section modo-edicao">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="section-title">Dependentes (${dependentes.length})</h3>
                <span class="badge bg-primary ms-2">Editando</span>
                <button type="button" class="btn btn-success btn-sm ms-auto" onclick="adicionarDependente()">
                    <i class="fas fa-plus"></i> Adicionar Dependente
                </button>
            </div>
            
            <div id="dependentesEditaveis" class="dependentes-container">
                ${dependentesHtml || `
                    <div class="empty-state text-center p-4">
                        <i class="fas fa-users" style="font-size: 2rem; color: #ccc;"></i>
                        <p class="text-muted mt-2">Nenhum dependente cadastrado</p>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarDependente()">
                            <i class="fas fa-plus"></i> Adicionar Primeiro Dependente
                        </button>
                    </div>
                `}
            </div>
        </div>
    `;
}

// Função auxiliar para gerar HTML de um dependente editável
function gerarHtmlDependenteEditavel(dep, index, parentescos) {
    return `
        <div class="dependente-item" style="background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; border: 1px solid #dee2e6;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="m-0"><i class="fas fa-user"></i> Dependente ${index + 1}</h6>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerDependente(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small">Nome</label>
                    <input type="text" name="dep_nome" class="form-control form-control-sm" value="${dep.nome || ''}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Data de Nascimento</label>
                    <input type="date" name="dep_data_nascimento" class="form-control form-control-sm" value="${dep.data_nascimento || ''}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Sexo</label>
                    <select name="dep_sexo" class="form-control form-control-sm">
                        <option value="">Selecione</option>
                        <option value="M" ${dep.sexo === 'M' ? 'selected' : ''}>Masculino</option>
                        <option value="F" ${dep.sexo === 'F' ? 'selected' : ''}>Feminino</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Parentesco</label>
                    <select name="dep_parentesco" class="form-control form-control-sm">
                        <option value="">Selecione</option>
                        ${parentescos.map(p => `<option value="${p}" ${dep.parentesco === p ? 'selected' : ''}>${p}</option>`).join('')}
                    </select>
                </div>
            </div>
        </div>
    `;
}

// Função para adicionar novo dependente
function adicionarDependente() {
    const container = document.getElementById('dependentesEditaveis');
    
    // Remove empty state se existir
    const emptyState = container.querySelector('.empty-state');
    if (emptyState) {
        emptyState.remove();
    }
    
    const parentescos = ['Cônjuge', 'Filho(a)', 'Pai', 'Mãe', 'Irmão(ã)', 'Avô(ó)', 'Neto(a)', 'Outro'];
    const index = container.querySelectorAll('.dependente-item').length;
    
    const novoHtml = gerarHtmlDependenteEditavel({}, index, parentescos);
    container.insertAdjacentHTML('beforeend', novoHtml);
    
    // Foca no campo nome do novo dependente
    const novoItem = container.lastElementChild;
    novoItem.querySelector('[name="dep_nome"]').focus();
    
    // Atualiza contador no header
    atualizarContadorDependentes();
}

// Função para remover dependente
function removerDependente(botao) {
    if (confirm('Deseja realmente remover este dependente?')) {
        const item = botao.closest('.dependente-item');
        item.remove();
        
        // Renumera os dependentes restantes
        renumerarDependentes();
        
        // Atualiza contador
        atualizarContadorDependentes();
        
        // Se não há mais dependentes, mostra empty state
        const container = document.getElementById('dependentesEditaveis');
        if (container.querySelectorAll('.dependente-item').length === 0) {
            container.innerHTML = `
                <div class="empty-state text-center p-4">
                    <i class="fas fa-users" style="font-size: 2rem; color: #ccc;"></i>
                    <p class="text-muted mt-2">Nenhum dependente cadastrado</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="adicionarDependente()">
                        <i class="fas fa-plus"></i> Adicionar Primeiro Dependente
                    </button>
                </div>
            `;
        }
    }
}

// Função para renumerar dependentes
function renumerarDependentes() {
    const container = document.getElementById('dependentesEditaveis');
    const items = container.querySelectorAll('.dependente-item');
    
    items.forEach((item, index) => {
        const titulo = item.querySelector('h6');
        if (titulo) {
            titulo.innerHTML = `<i class="fas fa-user"></i> Dependente ${index + 1}`;
        }
    });
}

// Função para atualizar contador de dependentes no header
function atualizarContadorDependentes() {
    const container = document.getElementById('dependentesEditaveis');
    const count = container.querySelectorAll('.dependente-item').length;
    
    const header = document.querySelector('#dependentes-tab .section-title');
    if (header) {
        header.textContent = `Dependentes (${count})`;
    }
}

// Função auxiliar para mostrar notificações
function mostrarNotificacao(mensagem, tipo = 'success') {
    // Remove notificações existentes
    document.querySelectorAll('.notificacao-toast').forEach(el => el.remove());
    
    const toast = document.createElement('div');
    toast.className = 'notificacao-toast';
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    if (tipo === 'success') {
        toast.style.background = 'linear-gradient(135deg, #00c853, #00a847)';
        toast.innerHTML = `<i class="fas fa-check-circle"></i> ${mensagem}`;
    } else if (tipo === 'error') {
        toast.style.background = 'linear-gradient(135deg, #dc3545, #c82333)';
        toast.innerHTML = `<i class="fas fa-times-circle"></i> ${mensagem}`;
    } else {
        toast.style.background = 'linear-gradient(135deg, #0056D2, #003d94)';
        toast.innerHTML = `<i class="fas fa-info-circle"></i> ${mensagem}`;
    }
    
    document.body.appendChild(toast);
    
    // Remove após 4 segundos
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Adiciona estilo da animação
const styleNotificacao = document.createElement('style');
styleNotificacao.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(styleNotificacao);

// Atualiza a função abrirModalAssociadoCompleto para inicializar botão de edição
const originalAbrirModalAssociadoCompleto = abrirModalAssociadoCompleto;
abrirModalAssociadoCompleto = function(associado) {
    // Chama função original
    originalAbrirModalAssociadoCompleto(associado);
    
    // Inicializa botão de edição
    setTimeout(() => {
        inicializarBotaoEdicao();
    }, 100);
};

console.log('✓ Sistema de Edição no Modal carregado com sucesso!');