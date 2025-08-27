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

// Função principal - Carrega dados da tabela
function carregarAssociados() {
    if (carregamentoIniciado || carregamentoCompleto) {
        console.log('Carregamento já realizado ou em andamento, ignorando nova chamada');
        return;
    }

    carregamentoIniciado = true;
    console.log('Iniciando carregamento de associados...');
    showLoading();

    const startTime = Date.now();

    const timeoutId = setTimeout(() => {
        hideLoading();
        carregamentoIniciado = false;
        console.error('TIMEOUT: Requisição demorou mais de 30 segundos');
        alert('Tempo esgotado ao carregar dados. Por favor, recarregue a página.');
        renderizarTabela([]);
    }, 30000);

    // Requisição AJAX
    $.ajax({
        url: '../api/carregar_associados.php',
        method: 'GET',
        dataType: 'json',
        cache: false,
        timeout: 25000,
        beforeSend: function () {
            console.log('Enviando requisição para:', this.url);
        },
        success: function (response) {
            clearTimeout(timeoutId);
            const elapsed = Date.now() - startTime;
            console.log(`Resposta recebida em ${elapsed}ms`);
            console.log('Total de registros:', response.total);

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

                // Ordena por ID decrescente (mais recentes primeiro)
                todosAssociados.sort((a, b) => b.id - a.id);

                associadosFiltrados = [...todosAssociados];

                // Preenche os filtros
                preencherFiltros();

                // Calcula total de páginas
                calcularPaginacao();

                // Renderiza a primeira página
                renderizarPagina();

                // Marca como carregamento completo
                carregamentoCompleto = true;

                console.log('✓ Dados carregados com sucesso!');
                console.log(`Total de associados únicos: ${todosAssociados.length}`);

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

            let mensagemErro = 'Erro ao carregar dados';

            if (xhr.status === 0) {
                mensagemErro = 'Sem conexão com o servidor';
            } else if (xhr.status === 404) {
                mensagemErro = 'Arquivo não encontrado';
            } else if (xhr.status === 500) {
                mensagemErro = 'Erro no servidor';
            } else if (status === 'timeout') {
                mensagemErro = 'Tempo esgotado';
            } else if (status === 'parsererror') {
                mensagemErro = 'Resposta inválida do servidor';
            }

            alert(mensagemErro + '\n\nPor favor, recarregue a página.');
            renderizarTabela([]);
        },
        complete: function () {
            clearTimeout(timeoutId);
            hideLoading();
            carregamentoIniciado = false;
            console.log('Carregamento finalizado');
        }
    });
}

// Preenche os filtros dinâmicos
function preencherFiltros() {
    console.log('Preenchendo filtros...');

    const selectCorporacao = document.getElementById('filterCorporacao');
    const selectPatente = document.getElementById('filterPatente');

    selectCorporacao.innerHTML = '<option value="">Todos</option>';
    selectPatente.innerHTML = '<option value="">Todos</option>';

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
    console.log(`Renderizando ${dados.length} registros...`);
    const tbody = document.getElementById('tableBody');

    if (!tbody) {
        console.error('Elemento tableBody não encontrado!');
        return;
    }

    tbody.innerHTML = '';

    if (dados.length === 0) {
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
            <td>${formatarTelefone(associado.telefone)}</td>
            <td>
                <div class="action-buttons-table">
                    <button class="btn-icon view" onclick="visualizarAssociado(${associado.id})" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon edit" onclick="editarAssociado(${associado.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon delete" onclick="excluirAssociado(${associado.id})" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Aplica filtros
function aplicarFiltros() {
    console.log('Aplicando filtros...');
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const filterSituacao = document.getElementById('filterSituacao').value;
    const filterCorporacao = document.getElementById('filterCorporacao').value;
    const filterPatente = document.getElementById('filterPatente').value;

    associadosFiltrados = todosAssociados.filter(associado => {
        const matchSearch = !searchTerm ||
            (associado.nome && associado.nome.toLowerCase().includes(searchTerm)) ||
            (associado.cpf && associado.cpf.includes(searchTerm)) ||
            (associado.rg && associado.rg.includes(searchTerm)) ||
            (associado.telefone && associado.telefone.includes(searchTerm));

        const matchSituacao = !filterSituacao || associado.situacao === filterSituacao;
        const matchCorporacao = !filterCorporacao || associado.corporacao === filterCorporacao;
        const matchPatente = !filterPatente || associado.patente === filterPatente;

        return matchSearch && matchSituacao && matchCorporacao && matchPatente;
    });

    console.log(`Filtros aplicados: ${associadosFiltrados.length} de ${todosAssociados.length} registros`);

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

    associadosFiltrados = [...todosAssociados];
    paginaAtual = 1;
    calcularPaginacao();
    renderizarPagina();
}

// Função para visualizar detalhes do associado
function visualizarAssociado(id) {
    console.log('Visualizando associado ID:', id);
    const associado = todosAssociados.find(a => a.id == id);

    if (!associado) {
        console.error('Associado não encontrado:', id);
        alert('Associado não encontrado!');
        return;
    }

    // NOVA LINHA: Resetar dados de observações ao abrir novo modal
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

    // NOVA LINHA: Carregar apenas o contador de observações (não os dados completos)
    carregarContadorObservacoes(associado.id);

    // Abre o modal
    document.getElementById('modalAssociado').classList.add('show');
    document.body.style.overflow = 'hidden';
}
// 2. NOVA FUNÇÃO: Resetar observações ao trocar de associado
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

// 3. NOVA FUNÇÃO: Carregar apenas o contador de observações (mais rápido)
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
    `;
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

// FUNÇÃO ATUALIZADA: Preenche tab Financeiro (SEM OBSERVAÇÕES)
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

// [RESTO DO CÓDIGO CONTINUA IGUAL...]
// Incluindo todas as outras funções: preencherTabContato, preencherTabDependentes, 
// preencherTabDocumentos, renderizarDocumentosUpload, e todas as funções de observações
// que permanecem exatamente iguais ao código original...

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

// NEW FUNCTION: Handle file drop
function handleFileDrop(event) {
    event.preventDefault();
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        document.getElementById('arquivoDocumento').files = files;
        updateFileInfo(document.getElementById('arquivoDocumento'));
    }
    // Reset visual state
    event.target.style.borderColor = 'var(--gray-300)';
    event.target.style.background = 'var(--gray-50)';
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
                    const associadoId = document.getElementById('modalId').textContent.replace('ID: ', '');
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

// FUNÇÃO ATUALIZADA: Renderizar documentos no modal com validação extra
function renderizarDocumentosNoModal(documentos, container) {
    let html = '<div class="document-flow-container">';

    // Adiciona contador de documentos
    html += `
                <div class="document-count-info" style="margin-bottom: 1rem; padding: 1rem; background: var(--gray-100); border-radius: 8px;">
                    <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 0.5rem;"></i>
                    <span style="font-size: 0.875rem; color: var(--gray-600);">
                        ${documentos.length} documento${documentos.length > 1 ? 's' : ''} em fluxo de assinatura
                    </span>
                </div>
            `;

    documentos.forEach(doc => {
        const statusClass = doc.status_fluxo.toLowerCase().replace('_', '-');

        html += `
                    <div class="document-flow-card">
                        <span class="status-badge-modal ${statusClass}">
                            <i class="fas fa-${getStatusIcon(doc.status_fluxo)} me-1"></i>
                            ${doc.status_descricao}
                        </span>
                        
                        <div class="document-flow-header">
                            <div class="document-flow-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="document-flow-info">
                                <h6>Ficha de Filiação</h6>
                                <p>${doc.tipo_origem === 'VIRTUAL' ? 'Gerada no Sistema' : 'Digitalizada'}</p>
                            </div>
                        </div>
                        
                        <div class="document-meta-modal">
                            <div class="meta-item-modal">
                                <i class="fas fa-calendar"></i>
                                <span>Cadastrado em ${formatarDataDocumento(doc.data_upload)}</span>
                            </div>
                            ${doc.departamento_atual_nome ? `
                                <div class="meta-item-modal">
                                    <i class="fas fa-building"></i>
                                    <span>${doc.departamento_atual_nome}</span>
                                </div>
                            ` : ''}
                            ${doc.dias_em_processo > 0 ? `
                                <div class="meta-item-modal">
                                    <i class="fas fa-hourglass-half"></i>
                                    <span>${doc.dias_em_processo} dia${doc.dias_em_processo > 1 ? 's' : ''} em processo</span>
                                </div>
                            ` : ''}
                            ${doc.funcionario_upload ? `
                                <div class="meta-item-modal">
                                    <i class="fas fa-user"></i>
                                    <span>Por: ${doc.funcionario_upload}</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <!-- Progress do Fluxo -->
                        <div class="fluxo-progress-modal">
                            <div class="fluxo-steps-modal">
                                <div class="fluxo-step-modal ${doc.status_fluxo !== 'DIGITALIZADO' ? 'completed' : 'active'}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Digitalizado</div>
                                    <div class="fluxo-line-modal"></div>
                                </div>
                                <div class="fluxo-step-modal ${doc.status_fluxo === 'AGUARDANDO_ASSINATURA' ? 'active' : (doc.status_fluxo === 'ASSINADO' || doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-signature"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Assinatura</div>
                                    <div class="fluxo-line-modal"></div>
                                </div>
                                <div class="fluxo-step-modal ${doc.status_fluxo === 'ASSINADO' ? 'active' : (doc.status_fluxo === 'FINALIZADO' ? 'completed' : '')}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Assinado</div>
                                    <div class="fluxo-line-modal"></div>
                                </div>
                                <div class="fluxo-step-modal ${doc.status_fluxo === 'FINALIZADO' ? 'completed' : ''}">
                                    <div class="fluxo-step-icon-modal">
                                        <i class="fas fa-flag-checkered"></i>
                                    </div>
                                    <div class="fluxo-step-label-modal">Finalizado</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações adicionais baseadas no status -->
                        ${renderizarInfoAdicional(doc)}
                        
                        <div class="document-actions-modal">
                            <button class="btn-modern btn-primary btn-sm" onclick="downloadDocumentoModal(${doc.id})">
                                <i class="fas fa-download"></i>
                                Baixar
                            </button>
                            
                            ${getAcoesFluxoModal(doc)}
                            
                            <button class="btn-modern btn-secondary btn-sm" onclick="verHistoricoModal(${doc.id})">
                                <i class="fas fa-history"></i>
                                Histórico
                            </button>
                        </div>
                    </div>
                `;
    });

    html += '</div>';
    container.innerHTML = html;
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

// NOVA FUNÇÃO: Renderizar informações adicionais baseadas no status
function renderizarInfoAdicional(doc) {
    let html = '';

    switch (doc.status_fluxo) {
        case 'DIGITALIZADO':
            html = `
                        <div class="alert-info-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(0, 123, 255, 0.1); border-radius: 8px;">
                            <i class="fas fa-info-circle" style="color: var(--info);"></i>
                            <span style="font-size: 0.8125rem;">Documento aguardando envio para assinatura</span>
                        </div>
                    `;
            break;

        case 'AGUARDANDO_ASSINATURA':
            html = `
                        <div class="alert-warning-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(255, 193, 7, 0.1); border-radius: 8px;">
                            <i class="fas fa-clock" style="color: var(--warning);"></i>
                            <span style="font-size: 0.8125rem;">Documento na presidência aguardando assinatura</span>
                        </div>
                    `;
            break;

        case 'ASSINADO':
            html = `
                        <div class="alert-success-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(40, 167, 69, 0.1); border-radius: 8px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            <span style="font-size: 0.8125rem;">Documento assinado e retornado ao comercial</span>
                        </div>
                    `;
            break;

        case 'FINALIZADO':
            html = `
                        <div class="alert-primary-custom" style="margin: 1rem 0; padding: 0.75rem; background: rgba(0, 86, 210, 0.1); border-radius: 8px;">
                            <i class="fas fa-flag-checkered" style="color: var(--primary);"></i>
                            <span style="font-size: 0.8125rem;">Processo concluído com sucesso</span>
                        </div>
                    `;
            break;
    }

    return html;
}

// NOVA FUNÇÃO: Obter ícone do status
function getStatusIcon(status) {
    const icons = {
        'DIGITALIZADO': 'upload',
        'AGUARDANDO_ASSINATURA': 'clock',
        'ASSINADO': 'check',
        'FINALIZADO': 'flag-checkered'
    };
    return icons[status] || 'file';
}

// NOVA FUNÇÃO: Obter ações do fluxo para o modal


// NOVA FUNÇÃO: Formatar data para documentos
function formatarDataDocumento(dataStr) {
    if (!dataStr) return '-';
    const data = new Date(dataStr);
    return data.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// NOVA FUNÇÃO: Download documento no modal
function downloadDocumentoModal(id) {
    window.open('../api/documentos/documentos_download.php?id=' + id, '_blank');
}

// FUNÇÃO ATUALIZADA: Ver histórico no modal com mais detalhes
function verHistoricoModal(documentoId) {
    // Criar um modal secundário para o histórico
    const historicoHtml = `
                <div class="modal fade" id="historicoDocumentoModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-history me-2" style="color: var(--primary);"></i>
                                    Histórico do Documento
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="historicoDocumentoContent">
                                    <div class="text-center py-5">
                                        <div class="loading-spinner mb-3"></div>
                                        <p class="text-muted">Carregando histórico...</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-modern btn-secondary" data-bs-dismiss="modal">
                                    Fechar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

    // Remove modal anterior se existir
    $('#historicoDocumentoModal').remove();

    // Adiciona o novo modal ao body
    $('body').append(historicoHtml);

    // Abre o modal
    const modalHistorico = new bootstrap.Modal(document.getElementById('historicoDocumentoModal'));
    modalHistorico.show();

    // Busca o histórico
    $.get('../api/documentos/documentos_historico_fluxo.php', { documento_id: documentoId }, function (response) {
        if (response.status === 'success' && response.data) {
            renderizarHistoricoNoModal(response.data);
        } else {
            $('#historicoDocumentoContent').html(`
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Não foi possível carregar o histórico do documento
                        </div>
                    `);
        }
    }).fail(function () {
        $('#historicoDocumentoContent').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        Erro ao carregar histórico
                    </div>
                `);
    });
}

// NOVA FUNÇÃO: Renderizar histórico no modal
function renderizarHistoricoNoModal(historico) {
    if (!historico || historico.length === 0) {
        $('#historicoDocumentoContent').html(`
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum histórico disponível para este documento
                    </div>
                `);
        return;
    }

    let html = '<div class="timeline">';

    historico.forEach((item, index) => {
        const isLast = index === historico.length - 1;
        html += `
                    <div class="timeline-item ${isLast ? 'last' : ''}">
                        <div class="timeline-marker">
                            <i class="fas fa-${getIconForStatus(item.status_novo)}"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <h6 class="timeline-title">${getStatusLabel(item.status_novo)}</h6>
                                <span class="timeline-date">${formatarDataDocumento(item.data_acao)}</span>
                            </div>
                            <p class="timeline-description">${item.observacao || 'Sem observações'}</p>
                            <div class="timeline-meta">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i> ${item.funcionario_nome || 'Sistema'}
                                    ${item.dept_origem_nome ? `<br><i class="fas fa-building me-1"></i> De: ${item.dept_origem_nome}` : ''}
                                    ${item.dept_destino_nome ? ` → Para: ${item.dept_destino_nome}` : ''}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
    });

    html += '</div>';
    $('#historicoDocumentoContent').html(html);
}

// FUNÇÃO AUXILIAR: Obter ícone para status
function getIconForStatus(status) {
    const icons = {
        'DIGITALIZADO': 'fa-upload',
        'AGUARDANDO_ASSINATURA': 'fa-clock',
        'ENVIADO_PRESIDENCIA': 'fa-paper-plane',
        'ASSINADO': 'fa-signature',
        'FINALIZADO': 'fa-flag-checkered'
    };
    return icons[status] || 'fa-circle';
}

// FUNÇÃO AUXILIAR: Obter label para status
function getStatusLabel(status) {
    const labels = {
        'DIGITALIZADO': 'Documento Digitalizado',
        'AGUARDANDO_ASSINATURA': 'Enviado para Assinatura',
        'ENVIADO_PRESIDENCIA': 'Na Presidência',
        'ASSINADO': 'Documento Assinado',
        'FINALIZADO': 'Processo Finalizado'
    };
    return labels[status] || status;
}

// NOVA FUNÇÃO: Enviar para assinatura no modal
function enviarParaAssinaturaModal(documentoId) {
    if (confirm('Deseja enviar este documento para assinatura na presidência?')) {
        $.ajax({
            url: '../api/documentos/documentos_enviar_assinatura.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                documento_id: documentoId,
                observacao: 'Documento enviado para assinatura via modal'
            }),
            success: function (response) {
                if (response.status === 'success') {
                    alert('Documento enviado para assinatura com sucesso!');
                    // Recarrega a tab de documentos
                    const associadoId = document.getElementById('modalId').textContent.replace('ID: ', '');
                    const associado = todosAssociados.find(a => a.id == associadoId);
                    if (associado) {
                        preencherTabDocumentos(associado);
                    }
                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function () {
                alert('Erro ao enviar documento para assinatura');
            }
        });
    }
}

// NOVA FUNÇÃO: Finalizar processo no modal
function finalizarProcessoModal(documentoId) {
    if (confirm('Deseja finalizar o processo deste documento?')) {
        $.ajax({
            url: '../api/documentos/documentos_finalizar.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                documento_id: documentoId,
                observacao: 'Processo finalizado via modal'
            }),
            success: function (response) {
                if (response.status === 'success') {
                    alert('Processo finalizado com sucesso!');
                    // Recarrega a tab de documentos
                    const associadoId = document.getElementById('modalId').textContent.replace('ID: ', '');
                    const associado = todosAssociados.find(a => a.id == associadoId);
                    if (associado) {
                        preencherTabDocumentos(associado);
                    }
                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function () {
                alert('Erro ao finalizar processo');
            }
        });
    }
}

// Função para fechar modal
function fecharModal() {
    const modal = document.getElementById('modalAssociado');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';

    // NOVA LINHA: Resetar observações ao fechar
    resetarObservacoes();
    
    // Volta para a primeira tab
    abrirTab('overview');
}

// Função para trocar de tab
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
}

// Função para editar associado
function editarAssociado(id) {
    console.log('Editando associado ID:', id);
    event.stopPropagation();
    window.location.href = `cadastroForm.php?id=${id}`;
}

// Função para excluir associado
function excluirAssociado(id) {
    console.log('Excluindo associado ID:', id);
    event.stopPropagation();

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

// Fecha modal ao clicar fora
window.addEventListener('click', function (event) {
    const modal = document.getElementById('modalAssociado');
    if (event.target === modal) {
        fecharModal();
    }
});

// Tecla ESC fecha o modal
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        fecharModal();
    }
});

// Event listeners - só adiciona UMA VEZ
document.addEventListener('DOMContentLoaded', function () {
    // Adiciona listeners aos filtros
    const searchInput = document.getElementById('searchInput');
    const filterSituacao = document.getElementById('filterSituacao');
    const filterCorporacao = document.getElementById('filterCorporacao');
    const filterPatente = document.getElementById('filterPatente');

    if (searchInput) searchInput.addEventListener('input', aplicarFiltros);
    if (filterSituacao) filterSituacao.addEventListener('change', aplicarFiltros);
    if (filterCorporacao) filterCorporacao.addEventListener('change', aplicarFiltros);
    if (filterPatente) filterPatente.addEventListener('change', aplicarFiltros);

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
// ADICIONAR AO FINAL DO dashboard.js
// FUNCIONALIDADES DA ABA DE OBSERVAÇÕES
// ========================================

// Variáveis globais para observações
let observacoesData = [];
let currentObservacaoPage = 1;
let observacoesPerPage = 5;
let currentFilterObs = 'all';
let currentAssociadoIdObs = null;

// Função para carregar observações quando a aba for aberta
function carregarObservacoes(associadoId) {
    // Evitar carregar se já estiver carregando
    if (window.carregandoObservacoes) {
        console.log('Já está carregando observações, ignorando...');
        return;
    }
    
    // Evitar recarregar se já carregou para este associado
    if (currentAssociadoIdObs === associadoId && observacoesData.length > 0) {
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
            <p style="color: var(--gray-500);">Carregando observações...</p>
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
        complete: function() {
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

// Função para salvar observação (CORRIGIDA)
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

    // Se for edição, adicionar o ID (CORREÇÃO PRINCIPAL)
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

                // Recarregar observações
                carregarObservacoes(currentAssociadoIdObs);

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
                carregarObservacoes(currentAssociadoIdObs);
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


// Função para remover documento (ESTAVA FALTANDO)
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

// Atualizar a função abrirTab existente para carregar observações
const abrirTabOriginal = window.abrirTab;
window.abrirTab = function (tabName) {
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

    // Se for a aba de observações, carregar dados APENAS se ainda não carregou
    if (tabName === 'observacoes') {
        const modalId = document.getElementById('modalId')?.textContent;
        if (modalId) {
            const associadoId = modalId.replace('Matrícula: ', '').trim();
            if (associadoId && associadoId !== '-') {
                // Só carrega se ainda não carregou para este associado
                if (currentAssociadoIdObs !== associadoId || observacoesData.length === 0) {
                    carregarObservacoes(associadoId);
                } else {
                    // Se já tem dados, apenas renderiza novamente
                    renderizarObservacoes();
                }
            }
        }
    }
};

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