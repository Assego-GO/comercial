window.Peculio = {
    dados: null,
    temPermissao: false,
    isFinanceiro: false,
    isPresidencia: false,
    dadosMultiplos: null,
    listaTodosPeculios: null,
    dadosRelatorio: null, // NOVO: armazena dados do último relatório gerado

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
        this.initRelatorioListeners(); // NOVO: inicializa listeners do modal de relatório
        this.showNotification('Gestão de Pecúlio carregada!', 'success', 2000);
    },

    // ========================================
    // FUNÇÕES DE RELATÓRIO - NOVAS
    // ========================================

    abrirModalRelatorio() {
        const modal = new bootstrap.Modal(document.getElementById('modalRelatorioPeculio'));
        modal.show();
        this.atualizarPreviewCount();
    },

    initRelatorioListeners() {
        // Toggle do período
        const usarPeriodo = document.getElementById('usarPeriodo');
        if (usarPeriodo) {
            usarPeriodo.addEventListener('change', (e) => {
                document.getElementById('periodoFields').style.display = e.target.checked ? 'block' : 'none';
            });
        }

        // Atualizar preview quando mudar tipo de relatório
        document.querySelectorAll('input[name="tipoRelatorio"]').forEach(radio => {
            radio.addEventListener('change', () => this.atualizarPreviewCount());
        });

        // Atualizar preview quando mudar período
        ['dataInicioPeriodo', 'dataFimPeriodo', 'tipoDataPeriodo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => this.atualizarPreviewCount());
            }
        });
    },

    async atualizarPreviewCount() {
        const previewEl = document.getElementById('previewCount');
        if (!previewEl) return;

        previewEl.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Calculando...';

        try {
            const filtros = this.coletarFiltrosRelatorio();
            const response = await fetch('../api/peculio/contar_relatorio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(filtros)
            });

            const result = await response.json();
            
            if (result.status === 'success') {
                const count = result.data.total || 0;
                const valorTotal = this.formatarMoeda(result.data.valor_total || 0);
                previewEl.innerHTML = `<strong>${count}</strong> registro(s) encontrado(s) | Total: <strong>${valorTotal}</strong>`;
            } else {
                previewEl.innerHTML = 'Erro ao calcular';
            }
        } catch (error) {
            console.error('Erro ao contar registros:', error);
            previewEl.innerHTML = 'Não foi possível calcular';
        }
    },

    coletarFiltrosRelatorio() {
        const tipoRelatorio = document.querySelector('input[name="tipoRelatorio"]:checked')?.value || 'todos';
        const usarPeriodo = document.getElementById('usarPeriodo')?.checked || false;
        
        const filtros = {
            tipo: tipoRelatorio,
            ordenar_por: document.getElementById('ordenarPor')?.value || 'nome',
            formato: document.getElementById('formatoRelatorio')?.value || 'html',
            campos: this.coletarCamposSelecionados()
        };

        if (usarPeriodo) {
            filtros.periodo = {
                tipo_data: document.getElementById('tipoDataPeriodo')?.value || 'data_prevista',
                data_inicio: document.getElementById('dataInicioPeriodo')?.value || null,
                data_fim: document.getElementById('dataFimPeriodo')?.value || null
            };
        }

        return filtros;
    },

    coletarCamposSelecionados() {
        const campos = ['nome']; // sempre incluir nome
        
        if (document.getElementById('campoRG')?.checked) campos.push('rg');
        if (document.getElementById('campoCPF')?.checked) campos.push('cpf');
        if (document.getElementById('campoTelefone')?.checked) campos.push('telefone');
        if (document.getElementById('campoEmail')?.checked) campos.push('email');
        if (document.getElementById('campoValor')?.checked) campos.push('valor');
        if (document.getElementById('campoDataPrevista')?.checked) campos.push('data_prevista');
        if (document.getElementById('campoDataRecebimento')?.checked) campos.push('data_recebimento');
        if (document.getElementById('campoStatus')?.checked) campos.push('status');
        
        return campos;
    },

    definirPeriodo(tipo) {
        const hoje = new Date();
        let dataInicio, dataFim;

        switch(tipo) {
            case 'mes_atual':
                dataInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                dataFim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
                break;
            case 'mes_anterior':
                dataInicio = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
                dataFim = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
                break;
            case 'trimestre':
                const trimestre = Math.floor(hoje.getMonth() / 3);
                dataInicio = new Date(hoje.getFullYear(), trimestre * 3, 1);
                dataFim = new Date(hoje.getFullYear(), (trimestre + 1) * 3, 0);
                break;
            case 'ano_atual':
                dataInicio = new Date(hoje.getFullYear(), 0, 1);
                dataFim = new Date(hoje.getFullYear(), 11, 31);
                break;
        }

        document.getElementById('dataInicioPeriodo').value = this.formatarDataParaInput(dataInicio);
        document.getElementById('dataFimPeriodo').value = this.formatarDataParaInput(dataFim);
        
        this.atualizarPreviewCount();
        this.showNotification(`Período definido: ${tipo.replace('_', ' ')}`, 'info', 2000);
    },

    async previewRelatorio() {
        const btnPreview = document.querySelector('[onclick="Peculio.previewRelatorio()"]');
        this.setBtnLoading(btnPreview, true);

        try {
            const filtros = this.coletarFiltrosRelatorio();
            filtros.formato = 'html'; // forçar HTML para preview
            
            const response = await fetch('../api/peculio/gerar_relatorio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(filtros)
            });

            const result = await response.json();

            if (result.status === 'success') {
                this.dadosRelatorio = result.data;
                this.exibirPreviewRelatorio(result.data, filtros);
            } else {
                this.showNotification(result.message || 'Erro ao gerar preview', 'error');
            }
        } catch (error) {
            console.error('Erro ao gerar preview:', error);
            this.showNotification('Erro ao gerar preview do relatório', 'error');
        } finally {
            this.setBtnLoading(btnPreview, false);
        }
    },

    exibirPreviewRelatorio(dados, filtros) {
        const { registros, estatisticas } = dados;
        const campos = filtros.campos;
        
        // Gerar título baseado no tipo
        const titulos = {
            'todos': 'Relatório Geral de Pecúlios',
            'recebidos': 'Relatório de Pecúlios Recebidos',
            'pendentes': 'Relatório de Pecúlios Pendentes',
            'sem_data': 'Relatório de Pecúlios Sem Data Definida',
            'vencidos': 'Relatório de Pecúlios Vencidos',
            'proximos': 'Relatório de Pecúlios - Próximos 30 Dias'
        };
        
        const titulo = titulos[filtros.tipo] || 'Relatório de Pecúlios';
        document.getElementById('tituloPreviewRelatorio').textContent = titulo;

        // Gerar HTML do relatório
        let html = `
            <div class="relatorio-container">
                <div class="relatorio-header">
                    <div class="relatorio-logo">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <h1 class="relatorio-titulo">${titulo}</h1>
                    <p class="relatorio-subtitulo">ASSEGO - Associação dos Subtenentes e Sargentos do Estado de Goiás</p>
                </div>
                
                <div class="relatorio-info">
                    <div class="relatorio-info-item">
                        <i class="fas fa-calendar"></i>
                        <span>Gerado em: ${new Date().toLocaleDateString('pt-BR')} às ${new Date().toLocaleTimeString('pt-BR')}</span>
                    </div>
                    <div class="relatorio-info-item">
                        <i class="fas fa-filter"></i>
                        <span>Tipo: ${titulo}</span>
                    </div>
                    ${filtros.periodo ? `
                    <div class="relatorio-info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Período: ${this.formatarData(filtros.periodo.data_inicio)} a ${this.formatarData(filtros.periodo.data_fim)}</span>
                    </div>
                    ` : ''}
                    <div class="relatorio-info-item">
                        <i class="fas fa-sort"></i>
                        <span>Ordenação: ${this.getOrdenacaoLabel(filtros.ordenar_por)}</span>
                    </div>
                </div>
                
                <div class="relatorio-estatisticas">
                    <div class="stat-box">
                        <div class="stat-box-valor">${estatisticas.total}</div>
                        <div class="stat-box-label">Total de Registros</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-valor">${estatisticas.pendentes}</div>
                        <div class="stat-box-label">Pendentes</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-valor">${estatisticas.recebidos}</div>
                        <div class="stat-box-label">Recebidos</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-valor">${this.formatarMoeda(estatisticas.valor_total)}</div>
                        <div class="stat-box-label">Valor Total</div>
                    </div>
                </div>
                
                <div class="relatorio-tabela-container">
                    <table class="relatorio-tabela">
                        <thead>
                            <tr>
                                <th>#</th>
                                ${campos.includes('nome') ? '<th>Nome</th>' : ''}
                                ${campos.includes('rg') ? '<th>RG</th>' : ''}
                                ${campos.includes('cpf') ? '<th>CPF</th>' : ''}
                                ${campos.includes('telefone') ? '<th>Telefone</th>' : ''}
                                ${campos.includes('email') ? '<th>E-mail</th>' : ''}
                                ${campos.includes('valor') ? '<th>Valor</th>' : ''}
                                ${campos.includes('data_prevista') ? '<th>Data Prevista</th>' : ''}
                                ${campos.includes('data_recebimento') ? '<th>Data Recebimento</th>' : ''}
                                ${campos.includes('status') ? '<th>Status</th>' : ''}
                            </tr>
                        </thead>
                        <tbody>
        `;

        if (registros && registros.length > 0) {
            registros.forEach((registro, index) => {
                const statusClass = this.getStatusClass(registro);
                const statusLabel = this.getStatusLabel(registro);
                
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        ${campos.includes('nome') ? `<td><strong>${registro.nome || '-'}</strong></td>` : ''}
                        ${campos.includes('rg') ? `<td>${registro.rg || '-'}</td>` : ''}
                        ${campos.includes('cpf') ? `<td>${registro.cpf || '-'}</td>` : ''}
                        ${campos.includes('telefone') ? `<td>${registro.telefone || '-'}</td>` : ''}
                        ${campos.includes('email') ? `<td>${registro.email || '-'}</td>` : ''}
                        ${campos.includes('valor') ? `<td>${this.formatarMoeda(registro.valor)}</td>` : ''}
                        ${campos.includes('data_prevista') ? `<td>${this.formatarData(registro.data_prevista)}</td>` : ''}
                        ${campos.includes('data_recebimento') ? `<td>${this.formatarData(registro.data_recebimento)}</td>` : ''}
                        ${campos.includes('status') ? `<td><span class="status-badge ${statusClass}">${statusLabel}</span></td>` : ''}
                    </tr>
                `;
            });
        } else {
            const colSpan = campos.length + 1;
            html += `
                <tr>
                    <td colspan="${colSpan}" style="text-align: center; padding: 2rem;">
                        <i class="fas fa-inbox fa-3x text-muted mb-3" style="display: block;"></i>
                        Nenhum registro encontrado com os filtros selecionados
                    </td>
                </tr>
            `;
        }

        html += `
                        </tbody>
                    </table>
                </div>
                
                <div class="relatorio-footer">
                    <p>Relatório gerado automaticamente pelo Sistema de Gestão de Pecúlios - ASSEGO</p>
                    <p>Total de registros: ${registros?.length || 0} | Gerado em: ${new Date().toLocaleString('pt-BR')}</p>
                </div>
            </div>
        `;

        document.getElementById('conteudoPreviewRelatorio').innerHTML = html;

        // Fechar modal de configuração e abrir preview
        const modalConfig = bootstrap.Modal.getInstance(document.getElementById('modalRelatorioPeculio'));
        if (modalConfig) modalConfig.hide();

        const modalPreview = new bootstrap.Modal(document.getElementById('modalPreviewRelatorio'));
        modalPreview.show();
    },

    getStatusClass(registro) {
        if (registro.data_recebimento && registro.data_recebimento !== '0000-00-00') {
            return 'status-recebido';
        }
        if (!registro.data_prevista || registro.data_prevista === '0000-00-00') {
            return 'status-sem-data';
        }
        const hoje = new Date();
        const dataPrevista = new Date(registro.data_prevista);
        if (dataPrevista < hoje) {
            return 'status-vencido';
        }
        return 'status-pendente';
    },

    getStatusLabel(registro) {
        if (registro.data_recebimento && registro.data_recebimento !== '0000-00-00') {
            return '✓ Recebido';
        }
        if (!registro.data_prevista || registro.data_prevista === '0000-00-00') {
            return '? Sem Data';
        }
        const hoje = new Date();
        const dataPrevista = new Date(registro.data_prevista);
        if (dataPrevista < hoje) {
            return '⚠ Vencido';
        }
        return '⏳ Pendente';
    },

    getOrdenacaoLabel(ordenacao) {
        const labels = {
            'nome': 'Nome (A-Z)',
            'nome_desc': 'Nome (Z-A)',
            'data_prevista': 'Data Prevista (Antiga → Recente)',
            'data_prevista_desc': 'Data Prevista (Recente → Antiga)',
            'data_recebimento': 'Data Recebimento (Antiga → Recente)',
            'data_recebimento_desc': 'Data Recebimento (Recente → Antiga)',
            'valor': 'Valor (Menor → Maior)',
            'valor_desc': 'Valor (Maior → Menor)'
        };
        return labels[ordenacao] || ordenacao;
    },

    async gerarRelatorio() {
        const formato = document.getElementById('formatoRelatorio')?.value || 'html';
        
        if (formato === 'html') {
            // Para HTML, apenas mostra o preview
            await this.previewRelatorio();
            return;
        }

        const btnGerar = document.querySelector('[onclick="Peculio.gerarRelatorio()"]');
        this.setBtnLoading(btnGerar, true);

        try {
            const filtros = this.coletarFiltrosRelatorio();
            
            const response = await fetch('../api/peculio/gerar_relatorio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(filtros)
            });

            if (formato === 'pdf') {
                // Para PDF, espera um blob
                const blob = await response.blob();
                this.downloadArquivo(blob, `relatorio_peculios_${Date.now()}.pdf`, 'application/pdf');
            } else if (formato === 'excel' || formato === 'csv') {
                const blob = await response.blob();
                const ext = formato === 'excel' ? 'xlsx' : 'csv';
                const mime = formato === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';
                this.downloadArquivo(blob, `relatorio_peculios_${Date.now()}.${ext}`, mime);
            } else {
                const result = await response.json();
                if (result.status === 'success') {
                    this.showNotification('Relatório gerado com sucesso!', 'success');
                } else {
                    this.showNotification(result.message || 'Erro ao gerar relatório', 'error');
                }
            }
        } catch (error) {
            console.error('Erro ao gerar relatório:', error);
            this.showNotification('Erro ao gerar relatório', 'error');
        } finally {
            this.setBtnLoading(btnGerar, false);
        }
    },

    downloadArquivo(blob, filename, mimeType) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        this.showNotification(`Arquivo ${filename} baixado!`, 'success');
    },

    imprimirRelatorio() {
        const conteudo = document.getElementById('conteudoPreviewRelatorio');
        if (!conteudo) return;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Relatório de Pecúlios - ASSEGO</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: Arial, sans-serif; }
                    .relatorio-container { max-width: 100%; }
                    .relatorio-header { background: #7c3aed; color: white; padding: 1.5rem; text-align: center; }
                    .relatorio-titulo { font-size: 1.5rem; margin-bottom: 0.5rem; }
                    .relatorio-info { display: flex; flex-wrap: wrap; gap: 1rem; padding: 1rem; background: #f5f5f5; border-bottom: 1px solid #ddd; }
                    .relatorio-info-item { font-size: 0.85rem; }
                    .relatorio-estatisticas { display: flex; gap: 1rem; padding: 1rem; justify-content: center; }
                    .stat-box { text-align: center; padding: 0.5rem 1rem; border: 1px solid #ddd; border-radius: 5px; }
                    .stat-box-valor { font-size: 1.5rem; font-weight: bold; color: #7c3aed; }
                    .stat-box-label { font-size: 0.75rem; color: #666; }
                    .relatorio-tabela { width: 100%; border-collapse: collapse; margin: 1rem 0; }
                    .relatorio-tabela th { background: #7c3aed; color: white; padding: 0.5rem; text-align: left; }
                    .relatorio-tabela td { padding: 0.5rem; border-bottom: 1px solid #ddd; }
                    .relatorio-tabela tr:nth-child(even) { background: #f9f9f9; }
                    .status-badge { padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.75rem; }
                    .status-recebido { background: #d4edda; color: #155724; }
                    .status-pendente { background: #fff3cd; color: #856404; }
                    .status-vencido { background: #f8d7da; color: #721c24; }
                    .status-sem-data { background: #e2e3e5; color: #383d41; }
                    .relatorio-footer { text-align: center; padding: 1rem; font-size: 0.8rem; color: #666; border-top: 1px solid #ddd; }
                    @media print {
                        .relatorio-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        .relatorio-tabela th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    }
                </style>
            </head>
            <body>
                ${conteudo.innerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    },

    async exportarPDF() {
        this.showNotification('Preparando PDF...', 'info', 2000);
        
        // Usar a API para gerar PDF
        const filtros = this.coletarFiltrosRelatorio();
        filtros.formato = 'pdf';
        
        try {
            const response = await fetch('../api/peculio/gerar_relatorio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(filtros)
            });

            if (response.headers.get('content-type')?.includes('application/pdf')) {
                const blob = await response.blob();
                this.downloadArquivo(blob, `relatorio_peculios_${Date.now()}.pdf`, 'application/pdf');
            } else {
                // Fallback: usar impressão
                this.imprimirRelatorio();
            }
        } catch (error) {
            console.error('Erro ao exportar PDF:', error);
            // Fallback: usar impressão
            this.imprimirRelatorio();
        }
    },

    async exportarExcel() {
        this.showNotification('Preparando Excel...', 'info', 2000);
        
        const filtros = this.coletarFiltrosRelatorio();
        filtros.formato = 'excel';
        
        try {
            const response = await fetch('../api/peculio/gerar_relatorio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(filtros)
            });

            const contentType = response.headers.get('content-type');
            
            if (contentType?.includes('spreadsheet') || contentType?.includes('octet-stream')) {
                const blob = await response.blob();
                this.downloadArquivo(blob, `relatorio_peculios_${Date.now()}.xlsx`, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            } else {
                // Fallback: gerar CSV manualmente
                this.exportarCSVManual();
            }
        } catch (error) {
            console.error('Erro ao exportar Excel:', error);
            this.exportarCSVManual();
        }
    },

    exportarCSVManual() {
        if (!this.dadosRelatorio || !this.dadosRelatorio.registros) {
            this.showNotification('Nenhum dado para exportar. Gere um preview primeiro.', 'warning');
            return;
        }

        const registros = this.dadosRelatorio.registros;
        const campos = this.coletarCamposSelecionados();
        
        // Cabeçalho
        const headers = campos.map(campo => {
            const labels = {
                'nome': 'Nome',
                'rg': 'RG',
                'cpf': 'CPF',
                'telefone': 'Telefone',
                'email': 'E-mail',
                'valor': 'Valor',
                'data_prevista': 'Data Prevista',
                'data_recebimento': 'Data Recebimento',
                'status': 'Status'
            };
            return labels[campo] || campo;
        });
        
        let csv = headers.join(';') + '\n';
        
        // Dados
        registros.forEach(registro => {
            const linha = campos.map(campo => {
                let valor = registro[campo] || '';
                
                if (campo === 'valor') {
                    valor = parseFloat(valor || 0).toFixed(2).replace('.', ',');
                } else if (campo === 'data_prevista' || campo === 'data_recebimento') {
                    valor = this.formatarData(valor);
                } else if (campo === 'status') {
                    valor = this.getStatusLabel(registro);
                }
                
                // Escapar aspas e adicionar aspas se necessário
                if (typeof valor === 'string' && (valor.includes(';') || valor.includes('"') || valor.includes('\n'))) {
                    valor = '"' + valor.replace(/"/g, '""') + '"';
                }
                
                return valor;
            });
            csv += linha.join(';') + '\n';
        });
        
        // Download
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        this.downloadArquivo(blob, `relatorio_peculios_${Date.now()}.csv`, 'text/csv');
    },

    // ========================================
    // FUNÇÕES ORIGINAIS (mantidas)
    // ========================================

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
            if (data instanceof Date) {
                return data.toISOString().split('T')[0];
            }
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