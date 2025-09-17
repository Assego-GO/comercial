/**
 * Módulo Verificador de Associados
 * rend/js/verificar_associados.js
 */

window.VerificarAssociados = (function() {
    'use strict';

    let permissoes = {};
    let csvData = [];
    let processedResults = [];

    // ===== INICIALIZAÇÃO =====
    function init(options = {}) {
        console.log('🔍 Inicializando Verificador de Associados...');
        
        permissoes = options.permissoes || {};
        
        if (!permissoes.visualizar) {
            console.error('❌ Sem permissão para visualizar verificador');
            return;
        }

        initializeEvents();
        console.log('✅ Verificador de Associados inicializado');
    }

    // ===== EVENTOS =====
    function initializeEvents() {
        // File input change
        const csvFileInput = document.getElementById('csvFile');
        if (csvFileInput) {
            csvFileInput.addEventListener('change', handleFileSelect);
            
            // Clear previous value to ensure change event fires every time
            csvFileInput.addEventListener('click', function() {
                this.value = '';
            });
        }

        // Upload zone click - improved handling
        const uploadZone = document.getElementById('uploadZone');
        const selectButton = document.getElementById('selectFileBtn');
        
        if (uploadZone && selectButton && csvFileInput) {
            // Handle click on upload zone (but not on the button itself)
            uploadZone.addEventListener('click', function(e) {
                // Only trigger if NOT clicking on the button
                if (e.target !== selectButton && !selectButton.contains(e.target)) {
                    e.preventDefault();
                    csvFileInput.click();
                }
            });
            
            // Handle click specifically on the select button
            selectButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Select button clicked'); // Debug log
                csvFileInput.click();
            });
        }

        // Download template
        const downloadTemplate = document.getElementById('downloadTemplate');
        if (downloadTemplate) {
            downloadTemplate.addEventListener('click', downloadCSVTemplate);
        }

        // Export results
        const exportResults = document.getElementById('exportResults');
        if (exportResults) {
            exportResults.addEventListener('click', exportResultsToCSV);
        }

        // Tab change events for filtering
        const tabButtons = document.querySelectorAll('#resultTabs .nav-link');
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-bs-target');
                filterResultsByTab(targetTab);
            });
        });
    }

    // ===== DRAG AND DROP HANDLERS =====
    window.dropHandler = function(ev) {
        console.log('File(s) dropped');
        ev.preventDefault();

        const uploadZone = document.getElementById('uploadZone');
        uploadZone.classList.remove('dragover');

        if (ev.dataTransfer.items) {
            // Use DataTransferItemList interface
            for (let i = 0; i < ev.dataTransfer.items.length; i++) {
                if (ev.dataTransfer.items[i].kind === 'file') {
                    const file = ev.dataTransfer.items[i].getAsFile();
                    handleFile(file);
                    break; // Only process first file
                }
            }
        } else {
            // Use DataTransfer interface
            for (let i = 0; i < ev.dataTransfer.files.length; i++) {
                handleFile(ev.dataTransfer.files[i]);
                break; // Only process first file
            }
        }
    };

    window.dragOverHandler = function(ev) {
        ev.preventDefault();
    };

    window.dragEnterHandler = function(ev) {
        ev.preventDefault();
        const uploadZone = document.getElementById('uploadZone');
        uploadZone.classList.add('dragover');
    };

    window.dragLeaveHandler = function(ev) {
        ev.preventDefault();
        const uploadZone = document.getElementById('uploadZone');
        uploadZone.classList.remove('dragover');
    };

    // ===== FILE HANDLING =====
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            handleFile(file);
        }
    }

    function handleFile(file) {
        console.log('Processing file:', file.name);

        // Validate file type
        if (!file.name.toLowerCase().endsWith('.csv')) {
            showNotification('Por favor, selecione um arquivo CSV válido.', 'error');
            return;
        }

        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            showNotification('O arquivo é muito grande. Máximo 5MB permitido.', 'error');
            return;
        }

        readCSVFile(file);
    }

    function readCSVFile(file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const csv = e.target.result;
                parseCSV(csv);
            } catch (error) {
                console.error('Erro ao ler arquivo:', error);
                showNotification('Erro ao processar o arquivo CSV.', 'error');
            }
        };

        reader.onerror = function() {
            showNotification('Erro ao ler o arquivo.', 'error');
        };

        reader.readAsText(file, 'UTF-8');
    }

    function parseCSV(csvText) {
        console.log('Parsing CSV data...');
        
        try {
            const lines = csvText.split('\n');
            const headers = lines[0].toLowerCase().split(',').map(h => h.trim().replace(/"/g, ''));
            
            console.log('Headers encontrados:', headers);

            // Validate required columns
            if (!headers.includes('nome') || !headers.includes('rg')) {
                showNotification('O CSV deve conter as colunas "nome" e "rg".', 'error');
                return;
            }

            const nomeIndex = headers.indexOf('nome');
            const rgIndex = headers.indexOf('rg');

            csvData = [];
            
            for (let i = 1; i < lines.length; i++) {
                const line = lines[i].trim();
                if (line) {
                    const values = parseCSVLine(line);
                    
                    if (values.length >= Math.max(nomeIndex + 1, rgIndex + 1)) {
                        const nome = values[nomeIndex] ? values[nomeIndex].trim().replace(/"/g, '') : '';
                        let rg = values[rgIndex] ? values[rgIndex].trim().replace(/"/g, '') : '';
                        
                        // Clean RG - remove everything that's not a number
                        rg = cleanRG(rg);
                        
                        if (nome && rg) {
                            csvData.push({
                                nome: nome,
                                rg: rg,
                                rgOriginal: values[rgIndex].trim().replace(/"/g, '') // Keep original for display
                            });
                        }
                    }
                }
            }

            console.log(`Parsed ${csvData.length} records`);

            if (csvData.length === 0) {
                showNotification('Nenhum registro válido encontrado no arquivo CSV.', 'warning');
                return;
            }

            // Start processing
            processCSVData();

        } catch (error) {
            console.error('Erro ao fazer parse do CSV:', error);
            showNotification('Erro ao processar o arquivo CSV. Verifique o formato.', 'error');
        }
    }

    // Helper function to parse CSV line considering quoted fields
    function parseCSVLine(line) {
        const result = [];
        let current = '';
        let inQuotes = false;
        
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === ',' && !inQuotes) {
                result.push(current);
                current = '';
            } else {
                current += char;
            }
        }
        
        result.push(current);
        return result;
    }

    // Function to clean RG - remove dots, dashes, spaces, and keep only numbers
    function cleanRG(rg) {
        if (!rg) return '';
        return rg.replace(/[^\d]/g, '');
    }

    // ===== CSV PROCESSING =====
    async function processCSVData() {
        console.log('Starting CSV data processing...');
        
        showProgressSection();
        updateProgress(0, 'Iniciando verificação...');

        processedResults = [];
        const totalRecords = csvData.length;
        let processedCount = 0;
        
        // Process in batches to avoid overwhelming the server
        const batchSize = 20;
        
        for (let i = 0; i < csvData.length; i += batchSize) {
            const batch = csvData.slice(i, i + batchSize);
            
            try {
                const batchResults = await processBatch(batch);
                processedResults = processedResults.concat(batchResults);
                
                processedCount += batch.length;
                const progress = Math.round((processedCount / totalRecords) * 100);
                
                updateProgress(progress, `Verificados ${processedCount} de ${totalRecords} registros...`);
                
            } catch (error) {
                console.error('Erro ao processar lote:', error);
                showNotification('Erro ao processar alguns registros.', 'error');
            }
        }

        updateProgress(100, 'Processamento concluído!');
        
        setTimeout(() => {
            hideProgressSection();
            displayResults();
        }, 500);
    }

    async function processBatch(batch) {
        const batchData = batch.map(item => ({
            nome: item.nome,
            rg: item.rg,
            rgOriginal: item.rgOriginal
        }));

        try {
            const response = await fetch('../api/financeiro/verificar_associados_process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    action: 'verify_batch',
                    data: batchData 
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                return result.data;
            } else {
                throw new Error(result.message || 'Erro ao processar lote');
            }
            
        } catch (error) {
            console.error('Erro na requisição:', error);
            
            // Return failed results for this batch
            return batch.map(item => ({
                nome_pesquisado: item.nome,
                rg_pesquisado: item.rgOriginal,
                status: 'ERROR',
                nome: null,
                cpf: null,
                rg: null,
                situacao: null,
                patente: null,
                corporacao: null,
                observacao: 'Erro ao processar'
            }));
        }
    }

    // ===== RESULTS DISPLAY =====
    function displayResults() {
        console.log('Displaying results:', processedResults.length);
        
        if (processedResults.length === 0) {
            showNotification('Nenhum resultado para exibir.', 'warning');
            return;
        }

        // Calculate statistics
        const stats = calculateStats(processedResults);
        updateStatsDisplay(stats);
        
        // Show results section
        const resultsSection = document.getElementById('resultsSection');
        resultsSection.classList.remove('d-none');
        
        // Populate main table
        populateResultsTable(processedResults);
        
        // Enable export button
        const exportBtn = document.getElementById('exportResults');
        exportBtn.disabled = false;
        
        // Show success notification
        showNotification(`Verificação concluída! ${stats.total} registros processados.`, 'success');
    }

    function calculateStats(results) {
        const stats = {
            total: results.length,
            filiados: 0,
            naoFiliados: 0,
            naoEncontrados: 0
        };

        results.forEach(result => {
            if (result.status === 'FOUND') {
                if (result.situacao === 'Filiado') {
                    stats.filiados++;
                } else {
                    stats.naoFiliados++;
                }
            } else {
                stats.naoEncontrados++;
            }
        });

        return stats;
    }

    function updateStatsDisplay(stats) {
        document.getElementById('totalProcessed').textContent = stats.total;
        document.getElementById('totalFiliados').textContent = stats.filiados;
        document.getElementById('totalNaoFiliados').textContent = stats.naoFiliados;
        document.getElementById('totalNaoEncontrados').textContent = stats.naoEncontrados;
        
        // Update badges
        document.getElementById('badgeAll').textContent = stats.total;
        document.getElementById('badgeFiliados').textContent = stats.filiados;
        document.getElementById('badgeNaoFiliados').textContent = stats.naoFiliados;
        document.getElementById('badgeNaoEncontrados').textContent = stats.naoEncontrados;
    }

    function populateResultsTable(results) {
        const tableBody = document.getElementById('resultsTableBody');
        tableBody.innerHTML = '';

        results.forEach(result => {
            const row = createResultRow(result);
            tableBody.appendChild(row);
        });
    }

    function createResultRow(result) {
        const row = document.createElement('tr');
        
        let statusBadge = '';
        let statusClass = '';
        
        if (result.status === 'FOUND') {
            if (result.situacao === 'Filiado') {
                statusBadge = '<span class="badge status-badge status-filiado">Filiado</span>';
                statusClass = 'table-success';
            } else {
                statusBadge = `<span class="badge status-badge status-nao-filiado">${result.situacao || 'Não Filiado'}</span>`;
                statusClass = 'table-warning';
            }
        } else {
            statusBadge = '<span class="badge status-badge status-nao-encontrado">Não Encontrado</span>';
            statusClass = 'table-danger';
        }

        row.className = statusClass;
        
        row.innerHTML = `
            <td><strong>${result.nome || result.nome_pesquisado || '-'}</strong></td>
            <td><code>${result.rg_pesquisado || '-'}</code></td>
            <td>${result.cpf ? formatCPF(result.cpf) : '-'}</td>
            <td>${result.rg ? formatRG(result.rg) : '-'}</td>
            <td>${statusBadge}</td>
            <td>${result.patente || '-'}</td>
            <td>${result.corporacao || '-'}</td>
        `;

        return row;
    }

    function filterResultsByTab(targetTab) {
        // This would be implemented to filter results by status
        // For now, we'll populate all tabs with appropriate data
        setTimeout(() => {
            populateFilteredTables();
        }, 100);
    }

    function populateFilteredTables() {
        const filiados = processedResults.filter(r => r.status === 'FOUND' && r.situacao === 'Filiado');
        const naoFiliados = processedResults.filter(r => r.status === 'FOUND' && r.situacao !== 'Filiado');
        const naoEncontrados = processedResults.filter(r => r.status !== 'FOUND');

        populateSpecificTable('filiadosTableBody', filiados, 'filiados');
        populateSpecificTable('naoFiliadosTableBody', naoFiliados, 'nao-filiados');
        populateSpecificTable('naoEncontradosTableBody', naoEncontrados, 'nao-encontrados');
    }

    function populateSpecificTable(tableBodyId, results, type) {
        const tableBody = document.getElementById(tableBodyId);
        if (!tableBody) return;
        
        tableBody.innerHTML = '';

        results.forEach(result => {
            const row = document.createElement('tr');
            
            if (type === 'nao-encontrados') {
                row.innerHTML = `
                    <td><strong>${result.nome_pesquisado || '-'}</strong></td>
                    <td><code>${result.rg_pesquisado || '-'}</code></td>
                    <td class="text-muted">${result.observacao || 'Não encontrado na base de dados'}</td>
                `;
            } else {
                let statusInfo = '';
                if (type === 'nao-filiados') {
                    statusInfo = `<td><span class="badge status-badge status-nao-filiado">${result.situacao || 'Não Filiado'}</span></td>`;
                }
                
                row.innerHTML = `
                    <td><strong>${result.nome || '-'}</strong></td>
                    <td><code>${result.rg_pesquisado || '-'}</code></td>
                    <td>${result.cpf ? formatCPF(result.cpf) : '-'}</td>
                    <td>${result.rg ? formatRG(result.rg) : '-'}</td>
                    ${statusInfo}
                    <td>${result.patente || '-'}</td>
                    <td>${result.corporacao || '-'}</td>
                `;
            }
            
            tableBody.appendChild(row);
        });
    }

    // ===== UTILITY FUNCTIONS =====
    function formatCPF(cpf) {
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }

    function formatRG(rg) {
        if (rg.length <= 7) {
            return rg.replace(/(\d{1,2})(\d{3})(\d{3})/, '$1.$2.$3');
        }
        return rg;
    }

    function showProgressSection() {
        const section = document.getElementById('progressSection');
        section.classList.remove('d-none');
    }

    function hideProgressSection() {
        const section = document.getElementById('progressSection');
        section.classList.add('d-none');
    }

    function updateProgress(percentage, text) {
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        
        progressBar.style.width = percentage + '%';
        progressText.textContent = percentage + '%';
        
        // Also update the text below if provided
        if (text) {
            const container = progressBar.closest('.card-body');
            const textElement = container.querySelector('h6');
            if (textElement) {
                textElement.textContent = text;
            }
        }
    }

    function downloadCSVTemplate() {
        const csvContent = "nome,rg\n" +
                          "João Silva,12345678\n" +
                          "Maria Santos,87654321\n" +
                          "José Oliveira,11223344\n";
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'modelo_verificacao_associados.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        showNotification('Modelo CSV baixado com sucesso!', 'success');
    }

    function exportResultsToCSV() {
        if (processedResults.length === 0) {
            showNotification('Nenhum resultado para exportar.', 'warning');
            return;
        }

        let csvContent = 'Nome Pesquisado,RG Pesquisado,Nome Encontrado,CPF,RG Cadastrado,Status,Patente,Corporação,Observação\n';
        
        processedResults.forEach(result => {
            const row = [
                result.nome_pesquisado || '',
                result.rg_pesquisado || '',
                result.nome || '',
                result.cpf || '',
                result.rg || '',
                result.status === 'FOUND' ? (result.situacao || 'Não Filiado') : 'Não Encontrado',
                result.patente || '',
                result.corporacao || '',
                result.observacao || ''
            ].map(field => `"${field}"`).join(',');
            
            csvContent += row + '\n';
        });

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `verificacao_associados_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        showNotification('Resultados exportados com sucesso!', 'success');
    }

    function showNotification(message, type = 'info') {
        // Use the global notification system
        if (window.notifications) {
            window.notifications.show(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }

    // ===== PUBLIC API =====
    return {
        init: init
    };
})();