window.Peculio = {
    dados: null,
    temPermissao: false,
    isFinanceiro: false,
    isPresidencia: false,
    dadosMultiplos: null,
    listaTodosPeculios: null,

    init(config = {}) {
        this.temPermissao = config.temPermissao || false;
        this.isFinanceiro = config.isFinanceiro || false;
        this.isPresidencia = config.isPresidencia || false;

        if (!this.temPermissao) {
            return;
        }

        if (!this.verificarElementos()) {
            return;
        }

        this.attachEventListeners();
        this.adicionarEstilosMelhorados();
        this.showNotification('Gestão de Pecúlio carregada!', 'success', 2000);
    },

    adicionarEstilosMelhorados() {
        const style = document.createElement('style');
        style.textContent = `
            .form-control, .btn, .dados-item-ultra-compact {
                transition: all 0.3s ease;
            }
            
            .form-control:hover {
                border-color: #ffc107;
                box-shadow: 0 2px 8px rgba(255, 193, 7, 0.15);
            }
            
            .form-control:focus {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            
            .btn:active {
                transform: translateY(0);
            }
            
            .dados-item-ultra-compact:hover {
                transform: translateY(-3px);
                box-shadow: 0 6px 20px rgba(255, 193, 7, 0.2);
            }
            
            .fade-in-simple {
                animation: fadeInSimple 0.4s ease;
            }
            
            @keyframes fadeInSimple {
                from {
                    opacity: 0;
                    transform: translateY(15px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .lista-peculios-modal {
                max-height: 70vh;
                overflow-y: auto;
            }

            .peculio-item-lista {
                background: white;
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 0.75rem;
                border-left: 4px solid #e9ecef;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .peculio-item-lista:hover {
                transform: translateX(5px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }

            .peculio-item-lista.urgencia-alta {
                border-left-color: #dc3545;
                background: linear-gradient(90deg, #fff5f5 0%, white 10%);
            }

            .peculio-item-lista.urgencia-media {
                border-left-color: #ffc107;
                background: linear-gradient(90deg, #fffbf0 0%, white 10%);
            }

            .peculio-item-lista.urgencia-baixa {
                border-left-color: #28a745;
            }

            .peculio-item-lista.recebido {
                border-left-color: #6c757d;
                opacity: 0.7;
            }

            .peculio-item-nome {
                font-weight: 600;
                color: #2c3e50;
                font-size: 1rem;
                margin-bottom: 0.3rem;
            }

            .peculio-item-detalhes {
                display: flex;
                gap: 1rem;
                flex-wrap: wrap;
                font-size: 0.85rem;
                color: #6c757d;
            }

            .peculio-item-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
                padding: 0.2rem 0.5rem;
                border-radius: 4px;
                font-size: 0.75rem;
                font-weight: 600;
            }

            .badge-vencido { background: #dc3545; color: white; }
            .badge-proximo { background: #ffc107; color: #212529; }
            .badge-atencao { background: #ff8c00; color: white; }
            .badge-normal { background: #28a745; color: white; }
            .badge-recebido { background: #6c757d; color: white; }

            .peculio-estatisticas {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .stat-card {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 8px;
                padding: 1rem;
                text-align: center;
                border-left: 3px solid #dee2e6;
            }

            .stat-card.destaque {
                border-left-color: #ffc107;
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            }

            .stat-numero {
                font-size: 1.8rem;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 0.2rem;
            }

            .stat-label {
                font-size: 0.85rem;
                color: #6c757d;
                font-weight: 600;
            }

            .filtros-lista {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1rem;
                flex-wrap: wrap;
            }

            .busca-lista-container {
                margin-bottom: 1rem;
                position: relative;
            }

            .busca-lista-input {
                width: 100%;
                padding: 0.75rem 2.5rem 0.75rem 1rem;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                font-size: 0.95rem;
                transition: all 0.3s ease;
            }

            .busca-lista-input:focus {
                border-color: #ffc107;
                outline: none;
                box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
            }

            .busca-lista-icon {
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #6c757d;
                pointer-events: none;
            }

            .busca-lista-clear {
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                display: none;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 0.75rem;
                transition: all 0.2s ease;
            }

            .busca-lista-clear:hover {
                background: #c82333;
                transform: translateY(-50%) scale(1.1);
            }

            .busca-lista-clear.show {
                display: flex;
            }

            .resultado-busca-info {
                text-align: center;
                padding: 0.5rem;
                color: #6c757d;
                font-size: 0.9rem;
                font-weight: 600;
            }

            .filtros-lista {
                display: flex;
                gap: 0.5rem;
                margin-bottom: 1rem;
                flex-wrap: wrap;
            }

            .filtro-btn {
                padding: 0.4rem 0.8rem;
                border-radius: 6px;
                border: 2px solid #e9ecef;
                background: white;
                cursor: pointer;
                font-size: 0.85rem;
                font-weight: 600;
                transition: all 0.3s ease;
            }

            .filtro-btn:hover {
                border-color: #ffc107;
            }

            .filtro-btn.ativo {
                background: #ffc107;
                border-color: #ffc107;
                color: #212529;
            }

            @media (max-width: 768px) {
                .peculio-estatisticas {
                    grid-template-columns: 1fr 1fr;
                }
            }
        `;
        document.head.appendChild(style);
    },

    async listarTodos() {
        const btnListar = document.querySelector('.btn-listar-todos');
        const textoOriginal = btnListar ? btnListar.innerHTML : '';
        if (btnListar) {
            btnListar.disabled = true;
            btnListar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Carregando todos...';
        }

        try {
            const response = await fetch('../api/peculio/listar_peculios.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();

            if (result.status === 'success') {
                this.listaTodosPeculios = result.data;
                this.mostrarModalLista(result.data);
            } else {
                this.showNotification(result.message || 'Erro ao carregar pecúlios', 'error');
            }

        } catch (error) {
            this.showNotification('Erro ao carregar lista: ' + error.message, 'error');
        } finally {
            if (btnListar) {
                btnListar.disabled = false;
                btnListar.innerHTML = textoOriginal;
            }
        }
    },

    mostrarModalLista(data) {
        const { peculios, estatisticas } = data;
        
        if (!peculios || peculios.length === 0) {
            this.showNotification('Nenhum pecúlio cadastrado', 'info');
            return;
        }
        
        const modalHtml = `
            <div class="modal fade" id="modalListaPeculios" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content" style="border-radius: 12px;">
                        <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);">
                            <h5 class="modal-title text-white fw-bold">
                                <i class="fas fa-list-ul me-2"></i>
                                Todos os Pecúlios (${peculios.length} registros)
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="peculio-estatisticas">
                                <div class="stat-card ${estatisticas.vencidos > 0 ? 'destaque' : ''}">
                                    <div class="stat-numero text-danger">${estatisticas.vencidos}</div>
                                    <div class="stat-label">Vencidos</div>
                                </div>
                                <div class="stat-card ${estatisticas.proximos_30_dias > 0 ? 'destaque' : ''}">
                                    <div class="stat-numero text-warning">${estatisticas.proximos_30_dias}</div>
                                    <div class="stat-label">Próximos 30 Dias</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-numero text-primary">${estatisticas.pendentes}</div>
                                    <div class="stat-label">Pendentes</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-numero text-success">${estatisticas.recebidos}</div>
                                    <div class="stat-label">Recebidos</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-numero">R$ ${this.formatarMoedaSemSimbolo(estatisticas.valor_total_pendente)}</div>
                                    <div class="stat-label">Total Pendente</div>
                                </div>
                            </div>

                            <div class="busca-lista-container">
                                <input type="text" 
                                       class="busca-lista-input" 
                                       id="buscaListaNome"
                                       placeholder="🔍 Buscar por nome do associado..."
                                       autocomplete="off">
                                <i class="fas fa-search busca-lista-icon"></i>
                                <button class="busca-lista-clear" id="limparBuscaLista" title="Limpar busca">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>

                            <div class="filtros-lista">
                                <button class="filtro-btn ativo" data-filtro="todos">
                                    <i class="fas fa-list me-1"></i>Todos
                                </button>
                                <button class="filtro-btn" data-filtro="urgentes">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Urgentes
                                </button>
                                <button class="filtro-btn" data-filtro="pendentes">
                                    <i class="fas fa-clock me-1"></i>Pendentes
                                </button>
                                <button class="filtro-btn" data-filtro="recebidos">
                                    <i class="fas fa-check-circle me-1"></i>Recebidos
                                </button>
                            </div>

                            <div class="resultado-busca-info" id="resultadoBuscaInfo" style="display: none;"></div>

                            <div class="lista-peculios-modal" id="listaModalPeculios">
                                ${this.renderizarListaPeculios(peculios, 'todos')}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Fechar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('modalListaPeculios')?.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const filtros = document.querySelectorAll('.filtro-btn');
        filtros.forEach(btn => {
            btn.addEventListener('click', () => {
                filtros.forEach(f => f.classList.remove('ativo'));
                btn.classList.add('ativo');
                const filtro = btn.getAttribute('data-filtro');
                
                const inputBusca = document.getElementById('buscaListaNome');
                if (inputBusca) inputBusca.value = '';
                document.getElementById('limparBuscaLista')?.classList.remove('show');
                document.getElementById('resultadoBuscaInfo').style.display = 'none';
                
                this.filtrarLista(peculios, filtro);
            });
        });

        const inputBusca = document.getElementById('buscaListaNome');
        const btnLimpar = document.getElementById('limparBuscaLista');
        
        if (inputBusca) {
            inputBusca.addEventListener('input', (e) => {
                const termo = e.target.value;
                
                if (termo.length > 0) {
                    btnLimpar?.classList.add('show');
                } else {
                    btnLimpar?.classList.remove('show');
                }
                
                this.buscarNaLista(peculios, termo);
            });
        }

        if (btnLimpar) {
            btnLimpar.addEventListener('click', () => {
                if (inputBusca) inputBusca.value = '';
                btnLimpar.classList.remove('show');
                document.getElementById('resultadoBuscaInfo').style.display = 'none';
                
                const filtroAtivo = document.querySelector('.filtro-btn.ativo');
                const filtro = filtroAtivo?.getAttribute('data-filtro') || 'todos';
                this.filtrarLista(peculios, filtro);
            });
        }

        const modal = new bootstrap.Modal(document.getElementById('modalListaPeculios'));
        modal.show();
    },

    renderizarListaPeculios(peculios, filtro = 'todos') {
        if (!peculios || peculios.length === 0) {
            return '<div class="alert alert-info">Nenhum pecúlio encontrado</div>';
        }

        let peculiosFiltrados = peculios;
        
        switch(filtro) {
            case 'urgentes':
                peculiosFiltrados = peculios.filter(p => 
                    p.prioridade === 'vencido' || p.prioridade === 'proximo'
                );
                break;
            case 'pendentes':
                peculiosFiltrados = peculios.filter(p => p.status === 'pendente');
                break;
            case 'recebidos':
                peculiosFiltrados = peculios.filter(p => p.status === 'recebido');
                break;
        }

        if (peculiosFiltrados.length === 0) {
            return '<div class="alert alert-info">Nenhum pecúlio neste filtro</div>';
        }

        let html = '';

        peculiosFiltrados.forEach(peculio => {
            const statusClass = peculio.status === 'recebido' ? 'recebido' : `urgencia-${peculio.urgencia}`;
            const badgeClass = this.getBadgeClass(peculio);
            const badgeText = this.getBadgeText(peculio);
            
            html += `
                <div class="peculio-item-lista ${statusClass}" onclick="Peculio.selecionarDaLista('${peculio.rg}')">
                    <div class="peculio-item-nome">
                        ${peculio.nome}
                        <span class="peculio-item-badge ${badgeClass}">
                            ${badgeText}
                        </span>
                    </div>
                    <div class="peculio-item-detalhes">
                        <span><i class="fas fa-id-card me-1"></i>RG: ${peculio.rg}</span>
                        <span><i class="fas fa-dollar-sign me-1"></i>${this.formatarMoeda(peculio.valor)}</span>
                        <span><i class="fas fa-calendar me-1"></i>Previsto: ${this.formatarData(peculio.data_prevista)}</span>
                        ${peculio.status === 'pendente' && peculio.data_prevista ? 
                            `<span><i class="fas fa-hourglass-half me-1"></i>${this.calcularDiasTexto(peculio.dias_ate_vencimento)}</span>` 
                            : ''}
                        ${peculio.status === 'recebido' ? 
                            `<span><i class="fas fa-check-circle me-1"></i>Recebido: ${this.formatarData(peculio.data_recebimento)}</span>` 
                            : ''}
                    </div>
                </div>
            `;
        });

        return html;
    },

    filtrarLista(peculios, filtro) {
        const container = document.getElementById('listaModalPeculios');
        if (container) {
            container.innerHTML = this.renderizarListaPeculios(peculios, filtro);
        }
    },

    buscarNaLista(peculios, termo) {
        const container = document.getElementById('listaModalPeculios');
        const infoDiv = document.getElementById('resultadoBuscaInfo');
        
        if (!container) return;

        if (!termo || termo.trim() === '') {
            const filtroAtivo = document.querySelector('.filtro-btn.ativo');
            const filtro = filtroAtivo?.getAttribute('data-filtro') || 'todos';
            container.innerHTML = this.renderizarListaPeculios(peculios, filtro);
            infoDiv.style.display = 'none';
            return;
        }

        const termoNormalizado = termo.toLowerCase().trim();

        const resultados = peculios.filter(p => 
            p.nome.toLowerCase().includes(termoNormalizado)
        );

        const filtroAtivo = document.querySelector('.filtro-btn.ativo');
        const filtro = filtroAtivo?.getAttribute('data-filtro') || 'todos';
        
        let resultadosFiltrados = resultados;
        
        switch(filtro) {
            case 'urgentes':
                resultadosFiltrados = resultados.filter(p => 
                    p.prioridade === 'vencido' || p.prioridade === 'proximo'
                );
                break;
            case 'pendentes':
                resultadosFiltrados = resultados.filter(p => p.status === 'pendente');
                break;
            case 'recebidos':
                resultadosFiltrados = resultados.filter(p => p.status === 'recebido');
                break;
        }

        if (infoDiv) {
            infoDiv.style.display = 'block';
            if (resultadosFiltrados.length === 0) {
                infoDiv.innerHTML = `❌ Nenhum resultado encontrado para "<strong>${termo}</strong>"`;
                infoDiv.style.color = '#dc3545';
            } else {
                infoDiv.innerHTML = `✅ ${resultadosFiltrados.length} resultado(s) encontrado(s) para "<strong>${termo}</strong>"`;
                infoDiv.style.color = '#28a745';
            }
        }

        if (resultadosFiltrados.length === 0) {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-search me-2"></i>
                    Nenhum associado encontrado com o nome "<strong>${termo}</strong>"
                </div>
            `;
        } else {
            let html = '';
            resultadosFiltrados.forEach(peculio => {
                const statusClass = peculio.status === 'recebido' ? 'recebido' : `urgencia-${peculio.urgencia}`;
                const badgeClass = this.getBadgeClass(peculio);
                const badgeText = this.getBadgeText(peculio);
                
                html += `
                    <div class="peculio-item-lista ${statusClass}" onclick="Peculio.selecionarDaLista('${peculio.rg}')">
                        <div class="peculio-item-nome">
                            ${this.highlightTexto(peculio.nome, termo)}
                            <span class="peculio-item-badge ${badgeClass}">
                                ${badgeText}
                            </span>
                        </div>
                        <div class="peculio-item-detalhes">
                            <span><i class="fas fa-id-card me-1"></i>RG: ${peculio.rg}</span>
                            <span><i class="fas fa-dollar-sign me-1"></i>${this.formatarMoeda(peculio.valor)}</span>
                            <span><i class="fas fa-calendar me-1"></i>Previsto: ${this.formatarData(peculio.data_prevista)}</span>
                            ${peculio.status === 'pendente' && peculio.data_prevista ? 
                                `<span><i class="fas fa-hourglass-half me-1"></i>${this.calcularDiasTexto(peculio.dias_ate_vencimento)}</span>` 
                                : ''}
                            ${peculio.status === 'recebido' ? 
                                `<span><i class="fas fa-check-circle me-1"></i>Recebido: ${this.formatarData(peculio.data_recebimento)}</span>` 
                                : ''}
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
    },

    highlightTexto(texto, termo) {
        if (!termo || termo.trim() === '') return texto;
        
        const regex = new RegExp(`(${termo})`, 'gi');
        return texto.replace(regex, '<mark style="background: #fff3cd; padding: 2px 4px; border-radius: 3px; font-weight: 600;">$1</mark>');
    },

    async selecionarDaLista(rg) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalListaPeculios'));
        if (modal) modal.hide();

        const rgInput = document.getElementById('rgBuscaPeculio');
        if (rgInput) {
            rgInput.value = rg;
            await this.buscar({preventDefault: () => {}});
        }
    },

    getBadgeClass(peculio) {
        if (peculio.status === 'recebido') return 'badge-recebido';
        if (peculio.prioridade === 'vencido') return 'badge-vencido';
        if (peculio.prioridade === 'proximo') return 'badge-proximo';
        if (peculio.prioridade === 'atencao') return 'badge-atencao';
        return 'badge-normal';
    },

    getBadgeText(peculio) {
        if (peculio.status === 'recebido') return '✓ Recebido';
        if (peculio.prioridade === 'vencido') return '⚠ Vencido';
        if (peculio.prioridade === 'proximo') return '🔔 Próximo';
        if (peculio.prioridade === 'atencao') return '👀 Atenção';
        return '📅 Normal';
    },

    calcularDiasTexto(dias) {
        if (dias < 0) return `${Math.abs(dias)} dias atrasado`;
        if (dias === 0) return 'Vence hoje';
        if (dias === 1) return 'Vence amanhã';
        if (dias <= 7) return `${dias} dias`;
        if (dias <= 30) return `${Math.floor(dias / 7)} semanas`;
        if (dias <= 90) return `${Math.floor(dias / 30)} meses`;
        return `${dias} dias`;
    },

    async buscar(event) {
        event.preventDefault();
        
        const rgInput = document.getElementById('rgBuscaPeculio');
        const busca = rgInput.value.trim();
        
        if (!busca) {
            this.mostrarAlert('Digite um RG ou nome', 'warning');
            return;
        }

        this.mostrarLoading(true);
        this.esconderDados();
        this.esconderAlert();
        this.esconderSelecaoRG();

        try {
            const parametro = isNaN(busca) ? 'nome' : 'rg';
            const response = await fetch(`../api/peculio/consultar_peculio.php?${parametro}=${encodeURIComponent(busca)}`);
            const result = await response.json();

            if (result.status === 'multiple_results') {
                this.dadosMultiplos = result.data;
                this.mostrarSelecaoRG(result.data);
                this.mostrarAlert(`${result.data.length} associados encontrados. Selecione um.`, 'info');
                
            } else if (result.status === 'success') {
                if (Array.isArray(result.data) && result.data.length > 1) {
                    this.dadosMultiplos = result.data;
                    this.mostrarSelecaoRG(result.data);
                } else {
                    this.dados = Array.isArray(result.data) ? result.data[0] : result.data;
                    this.exibirDados(this.dados);
                    this.mostrarAlert('Dados carregados!', 'success');

                    setTimeout(() => {
                        document.getElementById('associadoInfoContainer')?.scrollIntoView({ 
                            behavior: 'smooth', block: 'start' 
                        });
                    }, 300);
                }
            } else {
                this.mostrarAlert(result.message, 'danger');
            }

        } catch (error) {
            this.mostrarAlert('Erro ao consultar', 'danger');
        } finally {
            this.mostrarLoading(false);
        }
    },

    attachEventListeners() {
        const rgInput = document.getElementById('rgBuscaPeculio');
        
        if (rgInput) {
            rgInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.buscar(e);
                }
            });
            
            rgInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.limpar();
            });
        }
    },

    mostrarSelecaoRG(associados) {
        this.esconderDados();

        let selecaoHtml = `
            <div id="selecaoRGContainer" class="selecao-rg-container fade-in-simple">
                <div class="selecao-rg-title">
                    <i class="fas fa-users"></i>
                    Múltiplos associados - Selecione:
                </div>
        `;

        associados.forEach((associado, index) => {
            const valor = associado.valor ? this.formatarMoeda(parseFloat(associado.valor)) : 'N/A';
            const jaRecebeu = associado.data_recebimento && associado.data_recebimento !== '0000-00-00';
            
            selecaoHtml += `
                <div class="rg-opcao" onclick="Peculio.selecionarAssociado(${index})">
                    <div class="rg-opcao-nome">${associado.nome}</div>
                    <div class="rg-opcao-detalhes">
                        <span class="rg-opcao-rg">RG: ${associado.rg}</span>
                        <span>${jaRecebeu ? '✅ Recebido' : '⏳ Pendente'} | ${valor}</span>
                    </div>
                </div>
            `;
        });

        selecaoHtml += `
                <div class="selecao-rg-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="Peculio.cancelarSelecao()">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                </div>
            </div>
        `;

        const buscaSection = document.querySelector('.busca-section-ultra-compact');
        if (buscaSection) {
            buscaSection.insertAdjacentHTML('afterend', selecaoHtml);
        }
    },

    selecionarAssociado(index) {
        if (!this.dadosMultiplos || !this.dadosMultiplos[index]) return;

        const associado = this.dadosMultiplos[index];
        this.esconderSelecaoRG();
        this.dados = associado;
        this.exibirDados(this.dados);
        this.mostrarAlert(`${associado.nome} selecionado!`, 'success');

        setTimeout(() => {
            document.getElementById('associadoInfoContainer')?.scrollIntoView({ 
                behavior: 'smooth', block: 'start' 
            });
        }, 300);
    },

    cancelarSelecao() {
        this.esconderSelecaoRG();
        this.dadosMultiplos = null;
        this.mostrarAlert('Seleção cancelada', 'info');
    },

    esconderSelecaoRG() {
        document.getElementById('selecaoRGContainer')?.remove();
    },

    exibirDados(dados) {
        document.getElementById('associadoNome').textContent = dados.nome || 'N/A';
        document.getElementById('associadoRG').textContent = `RG: ${dados.rg || 'N/A'}`;

        const dataPrevista = (dados.data_prevista && dados.data_prevista !== '0000-00-00') ?
            this.formatarData(dados.data_prevista) : 'Não definida';
        document.getElementById('dataPrevistaPeculio').textContent = dataPrevista;

        const valor = dados.valor ? this.formatarMoeda(parseFloat(dados.valor)) : 'N/A';
        document.getElementById('valorPeculio').textContent = valor;

        const dataRecebimento = (dados.data_recebimento && dados.data_recebimento !== '0000-00-00') ?
            this.formatarData(dados.data_recebimento) : 'Ainda não recebido';
        document.getElementById('dataRecebimentoPeculio').textContent = dataRecebimento;

        this.configurarBotoes(dados);

        this.mostrarContainer('associadoInfoContainer');
        setTimeout(() => this.mostrarContainer('peculioDadosContainer'), 100);
        setTimeout(() => this.mostrarContainer('acoesContainer'), 200);
    },

    configurarBotoes(dados) {
        const jaRecebeu = dados.data_recebimento && dados.data_recebimento !== '0000-00-00';
        const btnConfirmar = document.getElementById('btnConfirmarRecebimento');
        const btnEditar = document.getElementById('btnEditarPeculio');

        if (btnEditar) btnEditar.style.display = 'inline-block';
        if (btnConfirmar) btnConfirmar.style.display = jaRecebeu ? 'none' : 'inline-block';
    },

    limpar() {
        document.getElementById('rgBuscaPeculio').value = '';
        this.esconderDados();
        this.esconderAlert();
        this.esconderSelecaoRG();
        this.dados = null;
        this.dadosMultiplos = null;
    },

    editar() {
        if (!this.dados) {
            this.showNotification('Nenhum associado selecionado', 'warning');
            return;
        }
        this.criarModalEdicao();
    },

    criarModalEdicao() {
        const modalHtml = `
            <div class="modal fade" id="modalEditarPeculio" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 12px;">
                        <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);">
                            <h5 class="modal-title text-white fw-bold">
                                <i class="fas fa-edit me-2"></i>Editar - ${this.dados.nome}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Valor (R$)</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="editValor" 
                                           step="0.01" min="0" value="${this.dados.valor || 0}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Data Prevista</label>
                                <input type="date" class="form-control" id="editDataPrevista" 
                                       value="${this.formatarDataParaInput(this.dados.data_prevista)}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Data Recebimento</label>
                                <input type="date" class="form-control" id="editDataRecebimento" 
                                       value="${this.formatarDataParaInput(this.dados.data_recebimento)}">
                                <small class="text-muted">Deixe em branco se não recebeu</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-warning" onclick="Peculio.salvarEdicao()">
                                <i class="fas fa-save me-2"></i>Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('modalEditarPeculio')?.remove();
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('modalEditarPeculio'));
        modal.show();
    },

    async salvarEdicao() {
        const btnSalvar = document.querySelector('[onclick="Peculio.salvarEdicao()"]');
        this.setBtnLoading(btnSalvar, true);

        try {
            const response = await fetch('../api/peculio/atualizar_peculio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    associado_id: this.dados.id,
                    valor: document.getElementById('editValor').value,
                    data_prevista: document.getElementById('editDataPrevista').value,
                    data_recebimento: document.getElementById('editDataRecebimento').value
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showNotification('Atualizado!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalEditarPeculio')).hide();
                setTimeout(() => this.buscar({preventDefault: () => {}}), 800);
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            this.showNotification('Erro ao salvar', 'error');
        } finally {
            this.setBtnLoading(btnSalvar, false);
        }
    },

    async confirmarRecebimento() {
        if (!this.dados) return;
        if (!confirm(`Confirmar recebimento de ${this.dados.nome}?`)) return;

        const btnConfirmar = document.getElementById('btnConfirmarRecebimento');
        this.setBtnLoading(btnConfirmar, true);

        try {
            const response = await fetch('../api/peculio/confirmar_recebimento.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    associado_id: this.dados.id,
                    data_recebimento: new Date().toISOString().split('T')[0]
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.showNotification('Confirmado!', 'success');
                setTimeout(() => this.buscar({preventDefault: () => {}}), 800);
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            this.showNotification('Erro', 'error');
        } finally {
            this.setBtnLoading(btnConfirmar, false);
        }
    },

    mostrarContainer(id) {
        const el = document.getElementById(id);
        if (el) {
            el.style.display = 'block';
            el.classList.add('fade-in-simple');
        }
    },

    esconderDados() {
        ['associadoInfoContainer', 'peculioDadosContainer', 'acoesContainer'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    },

    mostrarLoading(mostrar) {
        document.getElementById('loadingBuscaPeculio').style.display = mostrar ? 'flex' : 'none';
        const btn = document.getElementById('btnBuscarPeculio');
        this.setBtnLoading(btn, mostrar);
    },

    setBtnLoading(btn, loading) {
        if (!btn) return;
        
        if (loading) {
            btn.disabled = true;
            btn.classList.add('btn-loading');
            btn.setAttribute('data-original-text', btn.innerHTML);
            btn.innerHTML = '<span style="opacity: 0;">...</span>';
        } else {
            btn.disabled = false;
            btn.classList.remove('btn-loading');
            btn.innerHTML = btn.getAttribute('data-original-text') || btn.innerHTML;
        }
    },

    mostrarAlert(msg, tipo) {
        const alertDiv = document.getElementById('alertBuscaPeculio');
        const alertText = document.getElementById('alertBuscaPeculioText');

        alertText.textContent = msg;
        alertDiv.className = `alert alert-${tipo} mt-3`;
        alertDiv.style.display = 'flex';

        if (tipo === 'success') {
            setTimeout(() => this.esconderAlert(), 4000);
        }
    },

    esconderAlert() {
        const alert = document.getElementById('alertBuscaPeculio');
        if (alert) alert.style.display = 'none';
    },

    verificarElementos() {
        const elementos = [
            'rgBuscaPeculio', 'btnBuscarPeculio', 'loadingBuscaPeculio',
            'alertBuscaPeculio', 'alertBuscaPeculioText', 'associadoInfoContainer',
            'peculioDadosContainer', 'acoesContainer', 'associadoNome', 'associadoRG',
            'dataPrevistaPeculio', 'valorPeculio', 'dataRecebimentoPeculio'
        ];

        return elementos.every(id => !!document.getElementById(id));
    },

    formatarData(data) {
        if (!data || data === '0000-00-00') return 'Não definida';
        try {
            return new Date(data + 'T00:00:00').toLocaleDateString('pt-BR');
        } catch (e) {
            return data;
        }
    },

    formatarDataParaInput(data) {
        if (!data || data === '0000-00-00') return '';
        try {
            return new Date(data + 'T00:00:00').toISOString().split('T')[0];
        } catch (e) {
            return '';
        }
    },

    formatarMoeda(valor) {
        if (!valor || valor === 0) return 'R$ 0,00';
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor);
    },

    formatarMoedaSemSimbolo(valor) {
        if (!valor || valor === 0) return '0,00';
        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(valor);
    },

    showNotification(msg, type, duration = 3000) {
        if (window.notifications) {
            window.notifications.show(msg, type, duration);
        }
    }
};