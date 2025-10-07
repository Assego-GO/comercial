/**
 * Sistema de Importação ASAAS - Versão Aba
 * Funciona como partial dentro do sistema de navegação financeiro
 */

window.ImportarAsaas = (function() {
    'use strict';

    let notifications;
    let isInitialized = false;

    // ===== SISTEMA DE NOTIFICAÇÕES =====
    class NotificationSystemAsaas {
        constructor() {
            // Usar o container existente ou criar um novo
            this.container = document.getElementById('toastContainer') || document.getElementById('toastContainerAsaas');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainerAsaas';
                this.container.className = 'toast-container position-fixed top-0 end-0 p-3';
                document.body.appendChild(this.container);
            }
        }

        show(message, type = 'success', duration = 5000) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.style.minWidth = '350px';

            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${this.getIcon(type)} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            this.container.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: duration });
            bsToast.show();

            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        getIcon(type) {
            const icons = {
                success: 'check-circle',
                error: 'exclamation-triangle',
                warning: 'exclamation-circle',
                info: 'info-circle'
            };
            return icons[type] || 'info-circle';
        }
    }

    // ===== FUNÇÕES PRINCIPAIS =====
    function init(config = {}) {
        if (isInitialized) {
            console.log('ImportarAsaas já foi inicializado');
            return;
        }

        console.log('🚀 Inicializando ImportarAsaas...', config);
        
        // Inicializar sistema de notificações
        notifications = new NotificationSystemAsaas();

        // Configurar drag and drop
        setupDragAndDrop();

        // Verificar se Papa Parse está disponível
        if (typeof Papa === 'undefined') {
            console.warn('⚠️ PapaParse não encontrado, carregando...');
            loadPapaParse();
        }

        isInitialized = true;
        notifications.show('Sistema de Importação ASAAS carregado!', 'info', 3000);
        
        console.log('✅ ImportarAsaas inicializado com sucesso');
    }

    function loadPapaParse() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js';
        script.onload = () => console.log('✅ PapaParse carregado');
        script.onerror = () => console.error('❌ Erro ao carregar PapaParse');
        document.head.appendChild(script);
    }

    function setupDragAndDrop() {
        const uploadArea = document.getElementById('uploadAreaAsaas');
        if (!uploadArea) {
            console.warn('Upload area não encontrada');
            return;
        }
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                processarArquivo(files[0]);
            }
        });
    }

    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            processarArquivo(file);
        }
    }

    function processarArquivo(file) {
        // Validações
        if (!file.name.toLowerCase().endsWith('.csv')) {
            notifications.show('Erro: Por favor, selecione um arquivo CSV válido.', 'error');
            return;
        }

        if (file.size > 10 * 1024 * 1024) { // 10MB
            notifications.show('Erro: Arquivo muito grande. Máximo: 10MB', 'error');
            return;
        }

        // Verificar se Papa Parse está disponível
        if (typeof Papa === 'undefined') {
            notifications.show('Erro: Biblioteca de processamento CSV não carregada. Tente novamente.', 'error');
            return;
        }

        // Mostrar informações do arquivo
        mostrarInfoArquivo(file);

        // Mostrar progresso
        mostrarProgresso();

        // Processar CSV com PapaParse
        Papa.parse(file, {
            header: true,
            delimiter: ';',
            encoding: 'UTF-8',
            skipEmptyLines: true,
            complete: function(results) {
                if (results.errors.length > 0) {
                    console.error('Erros no CSV:', results.errors);
                    notifications.show('Erro ao processar CSV: ' + results.errors[0].message, 'error');
                    esconderProgresso();
                    return;
                }
                
                console.log('📊 CSV processado:', results.data.length, 'registros');
                
                // Enviar dados para o servidor
                enviarDadosServidor(results.data);
            },
            error: function(error) {
                console.error('Erro no parse:', error);
                notifications.show('Erro ao ler arquivo CSV: ' + error.message, 'error');
                esconderProgresso();
            }
        });
    }

    function mostrarInfoArquivo(file) {
        const fileInfo = document.getElementById('fileInfoAsaas');
        if (!fileInfo) return;
        
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        
        fileInfo.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-file-csv fa-3x text-success me-3"></i>
                <div>
                    <h6 class="mb-1 text-primary"><strong>${file.name}</strong></h6>
                    <small class="text-muted">
                        <i class="fas fa-weight me-1"></i>${fileSize} MB | 
                        <i class="fas fa-calendar me-1"></i>${file.lastModifiedDate?.toLocaleDateString() || 'Data não disponível'}
                    </small>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Arquivo OK</span>
                </div>
            </div>
        `;
        fileInfo.style.display = 'block';
        fileInfo.classList.add('animate-fade-in');
    }

    function mostrarProgresso() {
        const container = document.getElementById('progressContainerAsaas');
        if (!container) return;
        
        container.style.display = 'block';
        container.classList.add('animate-fade-in');
        updateProgress(10, 'Lendo arquivo CSV...');
    }

    function updateProgress(percent, text) {
        const progressBar = document.getElementById('progressBarAsaas');
        const progressText = document.getElementById('progressTextAsaas');
        
        if (progressBar) {
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
        }
        
        if (progressText) {
            progressText.textContent = text;
        }
    }

    function esconderProgresso() {
        const container = document.getElementById('progressContainerAsaas');
        if (container) {
            container.style.display = 'none';
        }
    }

    function enviarDadosServidor(dadosCSV) {
        updateProgress(30, 'Enviando dados para processamento...');

        const formData = new FormData();
        formData.append('dados_csv', JSON.stringify(dadosCSV));
        formData.append('action', 'processar_asaas');
        
        console.log('🚀 Enviando para processamento ASAAS...');

        fetch('../api/financeiro/processar_asaas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            console.log('📄 Resposta recebida:', text.substring(0, 200) + '...');
            
            // Tentar parsear JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                // Se falhar, tentar extrair JSON do meio do texto
                const jsonStart = text.lastIndexOf('{');
                const jsonEnd = text.lastIndexOf('}');
                
                if (jsonStart !== -1 && jsonEnd !== -1) {
                    const jsonText = text.substring(jsonStart, jsonEnd + 1);
                    return JSON.parse(jsonText);
                }
                
                throw new Error('Resposta inválida do servidor');
            }
        })
        .then(data => {
            updateProgress(100, 'Processamento concluído!');
            
            setTimeout(() => {
                esconderProgresso();
                
                if (data.status === 'success') {
                    mostrarResultados(data.resultado);
                    notifications.show(`✅ Importação concluída! ${data.resultado.resumo.totalProcessados} associados processados.`, 'success');
                } else {
                    notifications.show('❌ Erro: ' + data.message, 'error');
                    console.error('Erro:', data);
                }
            }, 1000);
        })
        .catch(error => {
            console.error('❌ Erro:', error);
            esconderProgresso();
            notifications.show('Erro de comunicação: ' + error.message, 'error');
        });
    }

    function validarEFormatarCPF(cpf) {
        if (!cpf && cpf !== 0) return '-';
        
        cpf = String(cpf).replace(/\D/g, '');
        
        if (cpf.length === 0) return '-';
        if (cpf.length !== 11) return cpf;
        
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }

    function mostrarResultados(resultado) {
        console.log('🎯 Mostrando resultados:', resultado);
        
        // Atualizar estatísticas
        const totalProcessados = document.getElementById('totalProcessadosAsaas');
        const totalPagantes = document.getElementById('totalPagantesAsaas');
        const totalNaoEncontrados = document.getElementById('totalNaoEncontradosAsaas');
        const totalIgnorados = document.getElementById('totalIgnoradosAsaas');

        if (totalProcessados) totalProcessados.textContent = resultado.resumo.totalProcessados;
        if (totalPagantes) totalPagantes.textContent = resultado.resumo.pagantes;
        if (totalNaoEncontrados) totalNaoEncontrados.textContent = resultado.resumo.nao_encontrados;
        if (totalIgnorados) totalIgnorados.textContent = resultado.resumo.ignorados;

        // Atualizar contadores nas tabs
        const countPagantes = document.getElementById('countPagantesAsaas');
        const countNaoEncontrados = document.getElementById('countNaoEncontradosAsaas');
        const countIgnorados = document.getElementById('countIgnoradosAsaas');

        if (countPagantes) countPagantes.textContent = resultado.resumo.pagantes;
        if (countNaoEncontrados) countNaoEncontrados.textContent = resultado.resumo.nao_encontrados;
        if (countIgnorados) countIgnorados.textContent = resultado.resumo.ignorados;

        // Preencher tabelas
        preencherTabelaPagantes(resultado.pagantes);
        preencherTabelaNaoEncontrados(resultado.nao_encontrados);
        preencherTabelaIgnorados(resultado.ignorados);

        // Mostrar container de resultados
        const resultsContainer = document.getElementById('resultsContainerAsaas');
        if (resultsContainer) {
            resultsContainer.style.display = 'block';
            resultsContainer.classList.add('animate-fade-in');
            
            // Scroll suave para os resultados
            resultsContainer.scrollIntoView({ 
                behavior: 'smooth' 
            });
        }
    }

    function preencherTabelaPagantes(pagantes) {
        const tbody = document.getElementById('pagantesTableAsaas');
        if (!tbody) return;
        
        tbody.innerHTML = '';

        pagantes.forEach(associado => {
            const row = document.createElement('tr');
            row.style.backgroundColor = '#f8fff9'; // Verde claro
            
            const cpfFormatado = validarEFormatarCPF(associado.cpf);
            const dadosPagamento = associado.dados_pagamento || {};
            
            row.innerHTML = `
                <td><strong>${associado.nome || 'Nome não informado'}</strong></td>
                <td><code>${cpfFormatado}</code></td>
                <td><span class="badge bg-primary">${associado.corporacao || 'N/A'}</span></td>
                <td>
                    <span class="status-badge-asaas pagou">
                        ✅ PAGOU
                    </span>
                </td>
                <td><strong class="text-success">R$ ${dadosPagamento.valor || '0,00'}</strong></td>
                <td>
                    <small class="text-success"><i class="fas fa-check-circle me-1"></i>Marcado como ADIMPLENTE</small>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    function preencherTabelaNaoEncontrados(naoEncontrados) {
        const tbody = document.getElementById('naoEncontradosTableAsaas');
        if (!tbody) return;
        
        tbody.innerHTML = '';

        naoEncontrados.forEach(associado => {
            const row = document.createElement('tr');
            row.style.backgroundColor = '#fff9e6'; // Amarelo claro
            
            const cpfFormatado = validarEFormatarCPF(associado.cpf);
            
            row.innerHTML = `
                <td><strong>${associado.nome || 'Nome não informado'}</strong></td>
                <td><code>${cpfFormatado}</code></td>
                <td><span class="badge bg-warning text-dark">${associado.corporacao || 'N/A'}</span></td>
                <td>
                    <span class="status-badge-asaas nao-encontrado">
                        ⚠️ NÃO ENCONTRADO
                    </span>
                </td>
                <td>
                    <small class="text-muted">${associado.motivo || 'Não encontrado no arquivo'}</small>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    function preencherTabelaIgnorados(ignorados) {
        const tbody = document.getElementById('ignoradosTableAsaas');
        if (!tbody) return;
        
        tbody.innerHTML = '';

        ignorados.forEach(pessoa => {
            const row = document.createElement('tr');
            row.style.backgroundColor = '#f8f9fa'; // Cinza claro
            
            const cpfFormatado = validarEFormatarCPF(pessoa.cpf);
            
            row.innerHTML = `
                <td><strong>${pessoa.nome || 'Nome não informado'}</strong></td>
                <td><code>${cpfFormatado}</code></td>
                <td><span class="badge bg-secondary">${pessoa.corporacao || 'N/A'}</span></td>
                <td>
                    <small class="text-muted">${pessoa.motivo || 'Fora do escopo'}</small>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    function voltarImportacao() {
        // Resetar formulário
        const csvFile = document.getElementById('csvFileAsaas');
        const fileInfo = document.getElementById('fileInfoAsaas');
        const resultsContainer = document.getElementById('resultsContainerAsaas');
        
        if (csvFile) csvFile.value = '';
        if (fileInfo) fileInfo.style.display = 'none';
        if (resultsContainer) resultsContainer.style.display = 'none';
        
        // Scroll para o topo da aba
        const container = document.querySelector('.importar-asaas-container');
        if (container) {
            container.scrollIntoView({ behavior: 'smooth' });
        }
        
        if (notifications) {
            notifications.show('Formulário resetado. Pronto para nova importação!', 'info');
        }
    }

    function limpar() {
        voltarImportacao();
    }

    // ===== API PÚBLICA =====
    return {
        init: init,
        handleFileSelect: handleFileSelect,
        voltarImportacao: voltarImportacao,
        limpar: limpar
    };

})();

// Log de inicialização
console.log('✅ ImportarAsaas module carregado');