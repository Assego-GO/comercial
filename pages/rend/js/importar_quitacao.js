/**
 * Módulo de Importação de Quitação - Sistema ASSEGO v2.0
 * Importa CSV de repasse mensal do NeoConsig
 * Atualiza status de pagamentos dos associados
 * 
 * VERSÃO MELHORADA COM:
 * ✨ Preview do CSV antes de importar
 * ✨ Validações visuais em tempo real
 * ✨ Estatísticas do arquivo
 * ✨ Interface moderna e responsiva
 */

window.ImportarQuitacao = (function() {
    'use strict';

    // ===== CONFIGURAÇÕES =====
    const CONFIG = {
        apiUrl: '../api/financeiro/importar_quitacao.php',
        maxFileSize: 10 * 1024 * 1024, // 10MB
        allowedExtensions: ['csv'],
        delimiter: ';',
        encoding: 'UTF-8',
        maxPreviewLines: 50 // Máximo de linhas no preview
    };

    // ===== VARIÁVEIS DE ESTADO =====
    let state = {
        file: null,
        processing: false,
        permissoes: null,
        currentImportData: null,
        csvPreviewData: null // NOVO: Dados do preview
    };

    // ===== UTILITÁRIOS =====
    const Utils = {
        showNotification(message, type = 'success', duration = 5000) {
            if (window.notifications) {
                window.notifications.show(message, type, duration);
            } else {
                console.log(`[${type.toUpperCase()}] ${message}`);
            }
        },

        formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        },

        formatDate(date) {
            if (!date) return '-';
            const d = new Date(date);
            return d.toLocaleDateString('pt-BR');
        },

        formatNumber(num) {
            return new Intl.NumberFormat('pt-BR').format(num);
        },

        validateFile(file) {
            if (!file) {
                return { valid: false, error: 'Nenhum arquivo selecionado' };
            }

            const extension = file.name.split('.').pop().toLowerCase();
            if (!CONFIG.allowedExtensions.includes(extension)) {
                return { 
                    valid: false, 
                    error: `Extensão inválida. Permitido: ${CONFIG.allowedExtensions.join(', ')}` 
                };
            }

            if (file.size > CONFIG.maxFileSize) {
                return { 
                    valid: false, 
                    error: `Arquivo muito grande. Máximo: ${CONFIG.maxFileSize / 1024 / 1024}MB` 
                };
            }

            return { valid: true };
        },

        // NOVO: Valida CPF (preservando zeros à esquerda)
        validarCPF(cpf) {
            const cpfLimpo = cpf.replace(/\D/g, '').padStart(11, '0');
            return cpfLimpo.length === 11;
        },

        // NOVO: Formata CPF (preservando zeros à esquerda)
        formatarCPF(cpf) {
            const cpfLimpo = cpf.replace(/\D/g, '').padStart(11, '0');
            if (cpfLimpo.length !== 11) return cpf;
            return `${cpfLimpo.substring(0, 3)}.${cpfLimpo.substring(3, 6)}.${cpfLimpo.substring(6, 9)}-${cpfLimpo.substring(9)}`;
        },

        escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    };

    // ===== GERENCIAMENTO DE UI =====
    const UI = {
        elements: {},

        init() {
            this.elements = {
                dropZone: document.getElementById('quitacao-drop-zone'),
                fileInput: document.getElementById('quitacao-file-input'),
                fileName: document.getElementById('quitacao-file-name'),
                fileSize: document.getElementById('quitacao-file-size'),
                fileDate: document.getElementById('quitacao-file-date'),
                fileInfo: document.getElementById('quitacao-file-info'),
                fileMeta: document.getElementById('quitacao-file-meta'),
                validationContainer: document.getElementById('quitacao-validation'),
                previewContainer: document.getElementById('quitacao-preview-container'),
                previewBody: document.getElementById('quitacao-preview-body'),
                previewTbody: document.getElementById('quitacao-preview-tbody'),
                previewCount: document.getElementById('quitacao-preview-count'),
                togglePreviewBtn: document.getElementById('toggle-preview-btn'),
                clearBtn: document.getElementById('clear-quitacao-file'),
                uploadBtn: document.getElementById('upload-quitacao-btn'),
                progressBar: document.getElementById('quitacao-progress-bar'),
                progressText: document.getElementById('quitacao-progress-text'),
                progressContainer: document.getElementById('quitacao-progress-container'),
                resultsContainer: document.getElementById('quitacao-results-container'),
                historicTable: document.getElementById('quitacao-historic-table'),
                refreshHistoricBtn: document.getElementById('refresh-quitacao-historic')
            };

            this.setupEventListeners();
            console.log('✅ UI do Importar Quitação inicializada');
        },

        setupEventListeners() {
            const { dropZone, fileInput, clearBtn, uploadBtn, refreshHistoricBtn, togglePreviewBtn } = this.elements;

            // Drag & Drop
            if (dropZone) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, this.preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'), false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'), false);
                });

                dropZone.addEventListener('drop', this.handleDrop.bind(this), false);
                dropZone.addEventListener('click', () => fileInput?.click());
            }

            // File Input
            if (fileInput) {
                fileInput.addEventListener('change', this.handleFileSelect.bind(this));
            }

            // Botões
            if (clearBtn) {
                clearBtn.addEventListener('click', this.clearFile.bind(this));
            }

            if (uploadBtn) {
                uploadBtn.addEventListener('click', () => FileProcessor.processFile());
            }

            if (refreshHistoricBtn) {
                refreshHistoricBtn.addEventListener('click', () => Historic.load());
            }

            // NOVO: Toggle preview
            if (togglePreviewBtn) {
                togglePreviewBtn.addEventListener('click', this.togglePreview.bind(this));
            }
        },

        preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        },

        handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                this.handleFiles(files[0]);
            }
        },

        handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                this.handleFiles(files[0]);
            }
        },

        async handleFiles(file) {
            const validation = Utils.validateFile(file);
            
            if (!validation.valid) {
                Utils.showNotification(validation.error, 'error');
                return;
            }

            state.file = file;
            
            // Exibir info básica
            this.displayFileInfo(file);
            
            // NOVO: Gerar preview
            await this.showCSVPreview(file);
            
            Utils.showNotification('Arquivo carregado. Revise o preview e clique em "Importar Quitação"', 'info', 3000);
        },

        displayFileInfo(file) {
            const { fileName, fileSize, fileDate, fileInfo, clearBtn, uploadBtn } = this.elements;

            const sizeInMB = (file.size / 1024 / 1024).toFixed(2);
            const dateModified = new Date(file.lastModified);

            if (fileName) {
                fileName.textContent = file.name;
            }

            if (fileSize) {
                fileSize.textContent = sizeInMB + ' MB';
            }

            if (fileDate) {
                fileDate.textContent = dateModified.toLocaleDateString('pt-BR');
            }

            if (fileInfo) {
                fileInfo.style.display = 'block';
            }

            if (clearBtn) {
                clearBtn.style.display = 'inline-block';
            }

            if (uploadBtn) {
                uploadBtn.disabled = false;
            }
        },

        // NOVO: Gera preview do CSV
        async showCSVPreview(file) {
            const { 
                previewContainer, 
                previewTbody, 
                previewCount, 
                fileMeta, 
                validationContainer 
            } = this.elements;
            
            if (!previewContainer || !previewTbody) return;

            try {
                // Ler arquivo
                const text = await file.text();
                const lines = text.split('\n').filter(line => line.trim());
                
                if (lines.length < 2) {
                    throw new Error('Arquivo vazio ou inválido');
                }

                // Estatísticas
                const stats = {
                    total: lines.length - 1, // Menos o cabeçalho
                    quitados: 0,
                    pendentes: 0,
                    erros: 0,
                    cpfsInvalidos: []
                };

                // Limpar preview anterior
                previewTbody.innerHTML = '';
                
                // Processar linhas (máximo configurado para preview)
                const maxPreview = Math.min(CONFIG.maxPreviewLines, lines.length - 1);
                let validacoes = [];

                for (let i = 1; i <= maxPreview; i++) {
                    const linha = lines[i];
                    if (!linha.trim()) continue;

                    const campos = linha.split(';').map(c => c.replace(/"/g, '').trim());
                    
                    if (campos.length < 14) continue;

                    const nome = campos[1] || '';
                    const cpf = campos[3] || '';
                    const valor = campos[11] || '0';
                    const vencimento = campos[5] || '';
                    const status = campos[13] || '';

                    // Contabilizar status
                    const statusUpper = status.toUpperCase();
                    if (statusUpper === 'QUITADO') {
                        stats.quitados++;
                    } else if (statusUpper === 'PENDENTE' || statusUpper.includes('PENDENTE')) {
                        stats.pendentes++;
                    }

                    // Validar CPF
                    const cpfValido = Utils.validarCPF(cpf);

                    if (!cpfValido) {
                        stats.erros++;
                        stats.cpfsInvalidos.push({ linha: i, cpf, nome });
                    }

                    // Criar linha da tabela
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${i}</td>
                        <td title="${Utils.escapeHtml(nome)}">${Utils.escapeHtml(nome.substring(0, 30))}${nome.length > 30 ? '...' : ''}</td>
                        <td>
                            ${cpfValido ? 
                                `<code>${Utils.formatarCPF(cpf)}</code>` : 
                                `<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Inválido</span>`
                            }
                        </td>
                        <td>R$ ${valor}</td>
                        <td>${vencimento}</td>
                        <td>
                            <span class="status-badge ${statusUpper === 'QUITADO' ? 'status-quitado' : 'status-pendente'}">
                                ${statusUpper === 'QUITADO' ? '✓ Quitado' : '⏳ Pendente'}
                            </span>
                        </td>
                    `;
                    previewTbody.appendChild(tr);
                }

                // Contar TODOS os quitados/pendentes no arquivo (não só no preview)
                for (let i = 1; i < lines.length; i++) {
                    const linha = lines[i];
                    if (!linha.trim()) continue;

                    const campos = linha.split(';').map(c => c.replace(/"/g, '').trim());
                    if (campos.length < 14) continue;

                    const status = (campos[13] || '').toUpperCase();
                    if (i > maxPreview) {
                        if (status === 'QUITADO') {
                            stats.quitados++;
                        } else if (status === 'PENDENTE' || status.includes('PENDENTE')) {
                            stats.pendentes++;
                        }
                    }

                    // Validar CPF de todos
                    const cpf = campos[3] || '';
                    if (!Utils.validarCPF(cpf)) {
                        stats.erros++;
                    }
                }

                // Salvar dados do preview no state
                state.csvPreviewData = stats;

                // Mostrar meta informações
                if (fileMeta) {
                    fileMeta.innerHTML = `
                        <div class="file-meta-item">
                            <div class="file-meta-label">Total de Linhas</div>
                            <div class="file-meta-value">${Utils.formatNumber(stats.total)}</div>
                        </div>
                        <div class="file-meta-item">
                            <div class="file-meta-label">Pagamentos Quitados</div>
                            <div class="file-meta-value text-success">${Utils.formatNumber(stats.quitados)}</div>
                        </div>
                        <div class="file-meta-item">
                            <div class="file-meta-label">Pagamentos Pendentes</div>
                            <div class="file-meta-value text-warning">${Utils.formatNumber(stats.pendentes)}</div>
                        </div>
                        <div class="file-meta-item">
                            <div class="file-meta-label">Taxa de Quitação</div>
                            <div class="file-meta-value text-info">${((stats.quitados / stats.total) * 100).toFixed(1)}%</div>
                        </div>
                    `;
                }

                // Atualizar contador
                if (previewCount) {
                    previewCount.textContent = `${maxPreview} de ${stats.total} linhas`;
                }

                // Validações
                validacoes.push({
                    type: 'success',
                    icon: 'check-circle',
                    message: `${Utils.formatNumber(stats.total)} registros encontrados no arquivo`
                });

                if (stats.quitados > 0) {
                    validacoes.push({
                        type: 'success',
                        icon: 'check-double',
                        message: `${Utils.formatNumber(stats.quitados)} pagamentos serão registrados (Quitados)`
                    });
                }

                if (stats.pendentes > 0) {
                    validacoes.push({
                        type: 'warning',
                        icon: 'clock',
                        message: `${Utils.formatNumber(stats.pendentes)} pagamentos pendentes serão ignorados`
                    });
                }

                if (stats.erros > 0) {
                    validacoes.push({
                        type: 'error',
                        icon: 'exclamation-triangle',
                        message: `${Utils.formatNumber(stats.erros)} registros com CPF inválido detectados`
                    });
                }

                // Mostrar validações
                if (validationContainer) {
                    validationContainer.innerHTML = validacoes.map(v => `
                        <div class="validation-item ${v.type}">
                            <i class="fas fa-${v.icon}"></i>
                            <span>${v.message}</span>
                        </div>
                    `).join('');
                    validationContainer.style.display = 'block';
                }

                // Mostrar preview
                previewContainer.style.display = 'block';

                console.log('✅ Preview gerado:', stats);

            } catch (error) {
                console.error('Erro ao gerar preview:', error);
                Utils.showNotification(`Erro ao ler arquivo: ${error.message}`, 'error');
                
                // Esconder preview em caso de erro
                if (previewContainer) {
                    previewContainer.style.display = 'none';
                }
            }
        },

        // NOVO: Toggle preview
        togglePreview() {
            const { previewBody, togglePreviewBtn } = this.elements;
            
            if (!previewBody || !togglePreviewBtn) return;

            const isHidden = previewBody.style.display === 'none';
            previewBody.style.display = isHidden ? 'block' : 'none';
            togglePreviewBtn.innerHTML = isHidden ? 
                '<i class="fas fa-eye-slash me-1"></i> Ocultar' :
                '<i class="fas fa-eye me-1"></i> Mostrar';
        },

        clearFile() {
            const { 
                fileInput, 
                fileName, 
                fileSize,
                fileDate,
                fileInfo, 
                fileMeta,
                validationContainer,
                previewContainer,
                clearBtn, 
                uploadBtn 
            } = this.elements;

            state.file = null;
            state.csvPreviewData = null;
            
            if (fileInput) fileInput.value = '';
            if (fileName) fileName.textContent = 'Arquivo.csv';
            if (fileSize) fileSize.textContent = '0 MB';
            if (fileDate) fileDate.textContent = '00/00/0000';
            if (fileInfo) fileInfo.style.display = 'none';
            if (fileMeta) fileMeta.innerHTML = '';
            if (validationContainer) validationContainer.style.display = 'none';
            if (previewContainer) previewContainer.style.display = 'none';
            if (clearBtn) clearBtn.style.display = 'none';
            if (uploadBtn) uploadBtn.disabled = true;

            this.hideProgress();
            this.hideResults();

            Utils.showNotification('Arquivo removido', 'info', 2000);
        },

        showProgress() {
            const { progressContainer } = this.elements;
            if (progressContainer) {
                progressContainer.style.display = 'block';
            }
        },

        updateProgress(percent, text = '') {
            const { progressBar, progressText } = this.elements;
            
            if (progressBar) {
                progressBar.style.width = `${percent}%`;
                progressBar.setAttribute('aria-valuenow', percent);
                progressBar.textContent = `${percent}%`;
            }

            if (progressText && text) {
                progressText.textContent = text;
            }
        },

        hideProgress() {
            const { progressContainer } = this.elements;
            if (progressContainer) {
                progressContainer.style.display = 'none';
            }
        },

        showResults(data) {
            const { resultsContainer } = this.elements;
            if (!resultsContainer) return;

            resultsContainer.innerHTML = this.buildResultsHTML(data);
            resultsContainer.style.display = 'block';

            // Scroll suave até os resultados
            setTimeout(() => {
                resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        },

        hideResults() {
            const { resultsContainer } = this.elements;
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
            }
        },

        buildResultsHTML(data) {
            const {
                total_linhas,
                processados,
                quitados,
                pendentes,
                erros,
                associados_atualizados,
                associados_nao_encontrados,
                pagamentos_novos,
                pagamentos_atualizados,
                detalhes_erros,
                tempo_processamento
            } = data;

            let html = `
                <div class="import-results">
                    <!-- Header -->
                    <div class="results-header">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h4 class="mb-1">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Importação Concluída
                                </h4>
                                <p class="text-muted mb-0 small">
                                    Processado em ${tempo_processamento}
                                </p>
                            </div>
                            <button class="btn btn-sm btn-primary" onclick="ImportarQuitacao.Historic.load()">
                                <i class="fas fa-sync-alt me-1"></i>
                                Atualizar Histórico
                            </button>
                        </div>
                    </div>

                    <!-- Cards de Estatísticas -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card bg-primary">
                                <div class="stat-icon">
                                    <i class="fas fa-file-csv"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value">${Utils.formatNumber(total_linhas)}</div>
                                    <div class="stat-label">Total de Linhas</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success">
                                <div class="stat-icon">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value">${Utils.formatNumber(quitados)}</div>
                                    <div class="stat-label">Pagamentos Quitados</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value">${Utils.formatNumber(pendentes)}</div>
                                    <div class="stat-label">Pagamentos Pendentes</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card ${erros > 0 ? 'bg-danger' : 'bg-secondary'}">
                                <div class="stat-icon">
                                    <i class="fas ${erros > 0 ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-value">${Utils.formatNumber(erros)}</div>
                                    <div class="stat-label">Erros</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detalhes do Processamento -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Detalhes do Processamento
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-plus-circle text-success me-2"></i>
                                            <strong>${Utils.formatNumber(pagamentos_novos || 0)}</strong> pagamentos novos registrados
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-sync-alt text-info me-2"></i>
                                            <strong>${Utils.formatNumber(pagamentos_atualizados || 0)}</strong> pagamentos já existentes (atualizados)
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-user-slash text-danger me-2"></i>
                                            <strong>${Utils.formatNumber(associados_nao_encontrados)}</strong> não encontrados no sistema
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            Taxa de sucesso: <strong>${((processados - erros) / processados * 100).toFixed(1)}%</strong>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-percentage text-info me-2"></i>
                                            Quitação: <strong>${(quitados / processados * 100).toFixed(1)}%</strong>
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-shield-alt text-primary me-2"></i>
                                            Sem duplicatas: <strong>Sistema protegido ✓</strong>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
            `;

            // Lista de Erros (se houver)
            if (erros > 0 && detalhes_erros && detalhes_erros.length > 0) {
                html += `
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Detalhes dos Erros (${erros})
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Linha</th>
                                            <th>CPF</th>
                                            <th>Nome</th>
                                            <th>Erro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;

                detalhes_erros.slice(0, 50).forEach(erro => {
                    html += `
                        <tr>
                            <td>${erro.linha || '-'}</td>
                            <td><code>${erro.cpf || '-'}</code></td>
                            <td>${Utils.escapeHtml(erro.nome || '-')}</td>
                            <td class="text-danger small">${Utils.escapeHtml(erro.erro || '-')}</td>
                        </tr>
                    `;
                });

                if (detalhes_erros.length > 50) {
                    html += `
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                <em>... e mais ${detalhes_erros.length - 50} erros</em>
                            </td>
                        </tr>
                    `;
                }

                html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
            }

            html += `</div>`;
            return html;
        }
    };

    // ===== PROCESSAMENTO DE ARQUIVO =====
    const FileProcessor = {
        async processFile() {
            if (!state.file) {
                Utils.showNotification('Nenhum arquivo selecionado', 'error');
                return;
            }

            if (state.processing) {
                Utils.showNotification('Já existe uma importação em andamento', 'warning');
                return;
            }

            // Verificar permissões (se fornecidas)
            // A verificação real de permissões é feita no backend
            if (state.permissoes && state.permissoes.importar === false) {
                Utils.showNotification('Você não tem permissão para importar quitações', 'error');
                return;
            }

            state.processing = true;
            UI.hideResults();
            UI.showProgress();
            UI.updateProgress(0, 'Preparando arquivo...');

            try {
                const formData = new FormData();
                formData.append('csv_file', state.file);
                formData.append('action', 'processar_csv');

                UI.updateProgress(10, 'Enviando arquivo...');

                const response = await fetch(CONFIG.apiUrl, {
                    method: 'POST',
                    body: formData
                });

                UI.updateProgress(50, 'Processando dados...');

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();

                UI.updateProgress(100, 'Concluído!');

                setTimeout(() => {
                    UI.hideProgress();
                }, 500);

                if (result.success) {
                    Utils.showNotification(
                        `Importação concluída! ${result.data.quitados} pagamentos quitados de ${result.data.processados} registros`,
                        'success'
                    );
                    UI.showResults(result.data);
                    Historic.load();
                } else {
                    throw new Error(result.message || 'Erro ao processar arquivo');
                }

            } catch (error) {
                console.error('Erro no processamento:', error);
                Utils.showNotification(`Erro ao processar: ${error.message}`, 'error');
                UI.hideProgress();
            } finally {
                state.processing = false;
            }
        }
    };

    // ===== HISTÓRICO DE IMPORTAÇÕES =====
    const Historic = {
        async load() {
            const { historicTable } = UI.elements;
            if (!historicTable) return;

            try {
                const response = await fetch(`${CONFIG.apiUrl}?action=listar_historico`);
                const result = await response.json();

                if (result.success && result.data) {
                    this.render(result.data);
                } else {
                    throw new Error(result.message || 'Erro ao carregar histórico');
                }
            } catch (error) {
                console.error('Erro ao carregar histórico:', error);
                Utils.showNotification('Erro ao carregar histórico de importações', 'error');
            }
        },

        render(data) {
            const { historicTable } = UI.elements;
            if (!historicTable) return;

            if (!data || data.length === 0) {
                historicTable.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">Nenhuma importação realizada ainda</p>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            data.forEach((item, index) => {
                const statusClass = item.erros > 0 ? 'warning' : 'success';
                const statusIcon = item.erros > 0 ? 'exclamation-triangle' : 'check-circle';

                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>
                            <small class="text-muted">
                                ${Utils.formatDate(item.data_importacao)}
                                <br>
                                ${new Date(item.data_importacao).toLocaleTimeString('pt-BR')}
                            </small>
                        </td>
                        <td>${Utils.escapeHtml(item.funcionario_nome || 'Sistema')}</td>
                        <td class="text-center">${Utils.formatNumber(item.total_registros)}</td>
                        <td class="text-center">
                            <span class="badge bg-success">${Utils.formatNumber(item.quitados)}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning text-dark">${Utils.formatNumber(item.pendentes)}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-${statusClass}">
                                <i class="fas fa-${statusIcon} me-1"></i>
                                ${item.erros > 0 ? Utils.formatNumber(item.erros) + ' erros' : 'Sucesso'}
                            </span>
                        </td>
                    </tr>
                `;
            });

            historicTable.innerHTML = html;
        }
    };

    // ===== INICIALIZAÇÃO =====
    function init(options = {}) {
        console.log('🚀 Inicializando módulo Importar Quitação v2.0');
        console.log('Permissões recebidas:', options.permissoes);

        state.permissoes = options.permissoes || {};

        UI.init();
        Historic.load();

        console.log('✅ Módulo Importar Quitação v2.0 inicializado com preview');
    }

    // ===== API PÚBLICA =====
    return {
        init,
        Utils,
        UI,
        FileProcessor,
        Historic
    };

})();

// Log de carregamento
console.log('📦 Módulo ImportarQuitacao v2.0 carregado com preview');