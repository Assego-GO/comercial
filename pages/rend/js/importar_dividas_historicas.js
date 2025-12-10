/**
 * ============================================================================
 * IMPORTAR DÍVIDAS HISTÓRICAS - VERSÃO 3.1 COM DEBUG DE VALORES
 * ============================================================================
 * Sistema ASSEGO - Serviços Financeiros
 * 
 * Funcionalidades:
 * - Parse de arquivo TXT com formato específico
 * - Preview interativo antes de importar
 * - Upload via AJAX com progress
 * - Histórico de importações
 * - Mapeamento completo de TODOS os tipos de contribuição
 * - Sistema de debug para identificar linhas não parseadas
 * - Verificação de valores e totais
 * 
 * Tipos Suportados:
 * ✅ Contribuição social (incluindo 50%)
 * ✅ Contribuição jurídica
 * ✅ Contribuição Pecúlio
 * ✅ Despesas bar
 * ✅ Contribuição soldado/aluno/aspirante
 * ✅ Outros tipos desconhecidos
 * ============================================================================
 */

const ImportarDividasHistoricas = (function () {
    'use strict';

    // ========================================================================
    // CONFIGURAÇÕES
    // ========================================================================
    
    const config = {
        apiUrl: getApiUrl(),
        maxFileSize: 10 * 1024 * 1024, // 10MB
        allowedExtensions: ['.txt'],
        permissoes: {
            visualizar: false,
            importar: false,
            exportar: false
        }
    };

    /**
     * Detecta URL da API dinamicamente
     */
    function getApiUrl() {
        const currentPath = window.location.pathname;
        
        if (currentPath.includes('/matheus/comercial/')) {
            const basePath = currentPath.substring(0, currentPath.indexOf('/matheus/comercial/') + 19);
            return window.location.origin + basePath + 'api/financeiro/importar_dividas_historicas_api.php';
        }
        
        return '../../api/financeiro/importar_dividas_historicas_api.php';
    }

    // ========================================================================
    // ESTADO DA APLICAÇÃO
    // ========================================================================
    
    let state = {
        arquivoAtual: null,
        dadosParsed: null,
        importando: false,
        debugInfo: null
    };

    // ========================================================================
    // CACHE DE ELEMENTOS DOM
    // ========================================================================
    
    const elements = {
        dropZone: null,
        fileInput: null,
        fileInfo: null,
        fileMeta: null,
        validation: null,
        previewContainer: null,
        previewBody: null,
        previewTbody: null,
        previewCount: null,
        progressContainer: null,
        progressBar: null,
        progressText: null,
        uploadBtn: null,
        clearBtn: null,
        togglePreviewBtn: null,
        resultsContainer: null,
        historicTable: null,
        refreshHistoricBtn: null
    };

    // ========================================================================
    // INICIALIZAÇÃO
    // ========================================================================
    
    function init(options = {}) {
        console.log('🚀 Inicializando ImportarDividasHistoricas v3.1...');
        console.log('════════════════════════════════════════════════════════════════');
        console.log('✅ Tipos de dívidas suportados:');
        console.log('   • Contribuição social (incluindo 50%) → SOCIAL');
        console.log('   • Contribuição jurídica → JURIDICO');
        console.log('   • Contribuição Pecúlio → PECULIO');
        console.log('   • Despesas bar → OUTROS');
        console.log('   • Contribuição soldado/aluno/aspirante → SOCIAL');
        console.log('   • Tipos desconhecidos → OUTROS');
        console.log('════════════════════════════════════════════════════════════════');
        console.log('🔗 API URL:', config.apiUrl);

        // Mesclar configurações
        if (options.permissoes) {
            Object.assign(config.permissoes, options.permissoes);
        }

        // Cachear elementos
        cacheElements();

        // Verificar permissões
        if (!config.permissoes.importar) {
            mostrarErro('Você não tem permissão para importar dívidas históricas');
            desabilitarInterface();
            return;
        }

        // Setup eventos
        setupEventListeners();

        // Carregar histórico
        carregarHistorico();

        console.log('✅ ImportarDividasHistoricas v3.1 inicializado!');
    }

    // ========================================================================
    // CACHE DE ELEMENTOS DOM
    // ========================================================================
    
    function cacheElements() {
        elements.dropZone = document.getElementById('dividas-drop-zone');
        elements.fileInput = document.getElementById('dividas-file-input');
        elements.fileInfo = document.getElementById('dividas-file-info');
        elements.fileMeta = document.getElementById('dividas-file-meta');
        elements.validation = document.getElementById('dividas-validation');
        elements.previewContainer = document.getElementById('dividas-preview-container');
        elements.previewBody = document.getElementById('dividas-preview-body');
        elements.previewTbody = document.getElementById('dividas-preview-tbody');
        elements.previewCount = document.getElementById('dividas-preview-count');
        elements.progressContainer = document.getElementById('dividas-progress-container');
        elements.progressBar = document.getElementById('dividas-progress-bar');
        elements.progressText = document.getElementById('dividas-progress-text');
        elements.uploadBtn = document.getElementById('upload-dividas-btn');
        elements.clearBtn = document.getElementById('clear-dividas-file');
        elements.togglePreviewBtn = document.getElementById('toggle-dividas-preview-btn');
        elements.resultsContainer = document.getElementById('dividas-results-container');
        elements.historicTable = document.getElementById('dividas-historic-table');
        elements.refreshHistoricBtn = document.getElementById('refresh-dividas-historic');
    }

    // ========================================================================
    // SETUP DE EVENTOS
    // ========================================================================
    
    function setupEventListeners() {
        // Drop Zone
        if (elements.dropZone && elements.fileInput) {
            elements.dropZone.addEventListener('click', () => elements.fileInput.click());
            elements.dropZone.addEventListener('dragover', handleDragOver);
            elements.dropZone.addEventListener('dragleave', handleDragLeave);
            elements.dropZone.addEventListener('drop', handleDrop);
            elements.fileInput.addEventListener('change', handleFileSelect);
        }

        // Botões
        if (elements.uploadBtn) {
            elements.uploadBtn.addEventListener('click', processarImportacao);
        }

        if (elements.clearBtn) {
            elements.clearBtn.addEventListener('click', limparArquivo);
        }

        if (elements.togglePreviewBtn) {
            elements.togglePreviewBtn.addEventListener('click', togglePreview);
        }

        if (elements.refreshHistoricBtn) {
            elements.refreshHistoricBtn.addEventListener('click', carregarHistorico);
        }
    }

    // ========================================================================
    // DRAG & DROP HANDLERS
    // ========================================================================
    
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.dropZone.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.dropZone.classList.remove('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        elements.dropZone.classList.remove('drag-over');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            processarArquivo(files[0]);
        }
    }

    function handleFileSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            processarArquivo(files[0]);
        }
    }

    // ========================================================================
    // PROCESSAMENTO DE ARQUIVO
    // ========================================================================
    
    function processarArquivo(file) {
        console.log('📄 Processando arquivo:', file.name);

        // Limpar estado anterior
        limparValidacoes();
        esconderPreview();
        esconderResultados();

        // Validar arquivo
        const validacao = validarArquivo(file);
        if (!validacao.valido) {
            mostrarValidacao(validacao.erros, 'error');
            return;
        }

        // Salvar arquivo no estado
        state.arquivoAtual = file;

        // Mostrar info do arquivo
        mostrarInfoArquivo(file);

        // Ler e parsear arquivo
        lerArquivo(file)
            .then(conteudo => {
                return parsearDividas(conteudo);
            })
            .then(dados => {
                state.dadosParsed = dados;
                state.debugInfo = dados.detalhesIgnorados;
                
                mostrarPreview(dados);
                
                // Mensagens de validação
                const mensagens = [
                    { 
                        tipo: 'success', 
                        mensagem: `✅ Arquivo validado: ${dados.total} associados com dívidas encontrados` 
                    }
                ];
                
                // Alerta se houver linhas ignoradas
                if (dados.linhasIgnoradas > 0) {
                    mensagens.push({
                        tipo: 'warning',
                        mensagem: `⚠️ ATENÇÃO: ${dados.linhasIgnoradas} linha(s) de dívida não foram reconhecidas. Abra o Console (F12) para detalhes.`
                    });
                }
                
                // Alerta se houver diferença entre totais
                if (dados.diferencaTotais && Math.abs(dados.diferencaTotais) > 0.01) {
                    mensagens.push({
                        tipo: 'warning',
                        mensagem: `⚠️ Diferença entre totais dos associados e soma das dívidas: R$ ${dados.diferencaTotais.toFixed(2)}`
                    });
                }
                
                mostrarValidacao(mensagens);
                
                elements.uploadBtn.disabled = false;
                elements.clearBtn.style.display = 'inline-block';
            })
            .catch(erro => {
                console.error('Erro ao processar arquivo:', erro);
                mostrarValidacao([
                    { tipo: 'error', mensagem: '❌ Erro ao ler arquivo: ' + erro.message }
                ], 'error');
            });
    }

    // ========================================================================
    // VALIDAÇÃO DE ARQUIVO
    // ========================================================================
    
    function validarArquivo(file) {
        const erros = [];

        // Validar tamanho
        if (file.size > config.maxFileSize) {
            erros.push({
                tipo: 'error',
                mensagem: `❌ Arquivo muito grande. Máximo: ${(config.maxFileSize / 1024 / 1024).toFixed(0)}MB`
            });
        }

        // Validar extensão
        const ext = '.' + file.name.split('.').pop().toLowerCase();
        if (!config.allowedExtensions.includes(ext)) {
            erros.push({
                tipo: 'error',
                mensagem: `❌ Extensão inválida. Permitido: ${config.allowedExtensions.join(', ')}`
            });
        }

        return {
            valido: erros.length === 0,
            erros: erros
        };
    }

    // ========================================================================
    // LEITURA DE ARQUIVO
    // ========================================================================
    
    function lerArquivo(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = (e) => {
                let conteudo = e.target.result;
                // Normalizar unicode para evitar problemas com acentos
                conteudo = conteudo.normalize('NFC');
                resolve(conteudo);
            };

            reader.onerror = () => {
                reject(new Error('Erro ao ler arquivo'));
            };

            reader.readAsText(file, 'UTF-8');
        });
    }

    // ========================================================================
    // PARSEAR DÍVIDAS - VERSÃO 3.1 COM DEBUG COMPLETO DE VALORES
    // ========================================================================
    
    function parsearDividas(conteudo) {
        console.log('🔍 Parseando conteúdo do arquivo...');

        const linhas = conteudo.split('\n');
        const associados = [];
        let associadoAtual = null;

        // Regex patterns
        const reAssociado = /^\s*(\d+)\s+(.+?)\s+([\d\/]+)\s+([\d\.\-]+)\s+([\d\+\-\s\(\)\.]+)\s+(Filiado|Desfiliado)\s+R\$\s*([\d\.\,]+)/;
        const reDivida = /^(.+?),\s*não quitado em\s+(\d{2})\/(\d{4})\s*:\s*R\$\s*([\d\.\,]+)(?:\s*-\s*(.+))?$/i;

        // Contadores
        let contadoresTipo = {
            'SOCIAL': 0,
            'JURIDICO': 0,
            'PECULIO': 0,
            'OUTROS': 0
        };
        
        let linhasIgnoradas = [];
        let totalLinhasDivida = 0;

        for (let i = 0; i < linhas.length; i++) {
            const linha = linhas[i].trim();

            // Pular linhas vazias ou cabeçalhos
            if (!linha || linha.startsWith('Lista de devedores') || linha.startsWith('Ident')) {
                continue;
            }

            // Detectar novo associado
            const matchAssociado = linha.match(reAssociado);
            if (matchAssociado) {
                // Salvar associado anterior se existir
                if (associadoAtual && associadoAtual.dividas.length > 0) {
                    associados.push(associadoAtual);
                }

                // Criar novo associado
                associadoAtual = {
                    ident: matchAssociado[1],
                    nome: matchAssociado[2].trim(),
                    rg: matchAssociado[3],
                    cpf: matchAssociado[4].trim(),
                    telefone: matchAssociado[5].trim(),
                    situacao: matchAssociado[6],
                    dividaTotal: parseFloat(matchAssociado[7].replace('.', '').replace(',', '.')),
                    dividaTotalOriginal: matchAssociado[7], // ✅ String original
                    dividas: []
                };
                continue;
            }

            // Detectar linhas de dívida
            if (linha.toLowerCase().includes('não quitado') || linha.toLowerCase().includes('nao quitado')) {
                totalLinhasDivida++;
                
                const matchDivida = linha.match(reDivida);
                
                if (matchDivida && associadoAtual) {
                    const tipoOriginal = matchDivida[1].trim();
                    const mes = matchDivida[2];
                    const ano = matchDivida[3];
                    const valorStr = matchDivida[4];
                    const valor = parseFloat(valorStr.replace('.', '').replace(',', '.'));
                    const motivo = matchDivida[5] ? matchDivida[5].trim() : '';

                    // Mapeamento de tipos
                    let tipoMapeado = 'OUTROS';
                    const tipoLower = tipoOriginal.toLowerCase();
                    
                    if (tipoLower.includes('social')) {
                        tipoMapeado = 'SOCIAL';
                    }
                    else if (tipoLower.includes('juridic')) {
                        tipoMapeado = 'JURIDICO';
                    }
                    else if (tipoLower.includes('pecul') || tipoLower.includes('pecúl')) {
                        tipoMapeado = 'PECULIO';
                    }
                    else if (tipoLower.includes('bar') || tipoLower.includes('despesa')) {
                        tipoMapeado = 'OUTROS';
                    }
                    else if (tipoLower.includes('soldado') || tipoLower.includes('aspirante') || tipoLower.includes('aluno')) {
                        tipoMapeado = 'SOCIAL';
                    }

                    contadoresTipo[tipoMapeado]++;

                    associadoAtual.dividas.push({
                        tipo: tipoMapeado,
                        tipoOriginal: tipoOriginal,
                        mes: mes,
                        ano: ano,
                        mesReferencia: `${ano}-${mes.padStart(2, '0')}-01`,
                        valor: valor,
                        valorOriginal: valorStr, // ✅ String original
                        motivo: motivo
                    });
                } else {
                    linhasIgnoradas.push({
                        linha: i + 1,
                        conteudo: linha,
                        associado: associadoAtual ? associadoAtual.nome : 'SEM ASSOCIADO'
                    });
                }
            }

            // Detectar "Total da divida" - fim do associado
            if (linha.toLowerCase().startsWith('total da divida') && associadoAtual) {
                if (associadoAtual.dividas.length > 0) {
                    associados.push(associadoAtual);
                }
                associadoAtual = null;
            }
        }

        // Salvar último associado se existir
        if (associadoAtual && associadoAtual.dividas.length > 0) {
            associados.push(associadoAtual);
        }

        // ====================================================================
        // ANÁLISE DE VALORES E DEBUG
        // ====================================================================
        
        // Coletar todas as dívidas
        const todasDividas = [];
        associados.forEach(assoc => {
            assoc.dividas.forEach(div => {
                todasDividas.push({
                    nome: assoc.nome,
                    cpf: assoc.cpf,
                    tipo: div.tipo,
                    tipoOriginal: div.tipoOriginal,
                    valor: div.valor,
                    valorOriginal: div.valorOriginal,
                    mes: div.mes,
                    ano: div.ano
                });
            });
        });
        
        // Ordenar por valor (maior para menor)
        todasDividas.sort((a, b) => b.valor - a.valor);
        
        // Calcular totais
        const totalDividas = associados.reduce((sum, a) => sum + a.dividas.length, 0);
        const valorTotalAssociados = associados.reduce((sum, a) => sum + a.dividaTotal, 0);
        const valorTotalDividas = todasDividas.reduce((sum, div) => sum + div.valor, 0);
        const diferenca = valorTotalAssociados - valorTotalDividas;

        // ====================================================================
        // RELATÓRIO DE DEBUG COMPLETO
        // ====================================================================
        console.log('════════════════════════════════════════════════════════════════');
        console.log(`✅ Parseados ${associados.length} associados`);
        console.log('📊 Dívidas capturadas por tipo:');
        console.log('   - SOCIAL:', contadoresTipo.SOCIAL);
        console.log('   - JURIDICO:', contadoresTipo.JURIDICO);
        console.log('   - PECULIO:', contadoresTipo.PECULIO);
        console.log('   - OUTROS:', contadoresTipo.OUTROS);
        console.log(`   TOTAL: ${Object.values(contadoresTipo).reduce((a, b) => a + b, 0)}`);
        console.log(`📋 Total de linhas com "não quitado": ${totalLinhasDivida}`);
        console.log(`⚠️  Linhas ignoradas (não parseadas): ${linhasIgnoradas.length}`);
        console.log('════════════════════════════════════════════════════════════════');
        console.log('💰 ANÁLISE DE VALORES:');
        console.log(`   Soma dos TOTAIS dos associados: R$ ${valorTotalAssociados.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`);
        console.log(`   Soma das DÍVIDAS individuais:   R$ ${valorTotalDividas.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`);
        console.log(`   DIFERENÇA:                       R$ ${diferenca.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`);
        console.log('════════════════════════════════════════════════════════════════');
        
        // Mostrar os 20 maiores valores
        console.log('💵 OS 20 MAIORES VALORES PARSEADOS:');
        console.log('════════════════════════════════════════════════════════════════');
        todasDividas.slice(0, 20).forEach((div, i) => {
            console.log(`${(i + 1).toString().padStart(2, '0')}. R$ ${div.valor.toFixed(2).padStart(10)} - ${div.nome.substring(0, 30)}`);
            console.log(`    ${div.tipoOriginal} (${div.tipo}) - ${div.mes}/${div.ano}`);
        });
        console.log('════════════════════════════════════════════════════════════════');
        
        // Verificar valores suspeitos
        const valoresSuspeitos = todasDividas.filter(d => d.valor < 1 || d.valor > 10000);
        if (valoresSuspeitos.length > 0) {
            console.log('⚠️  VALORES SUSPEITOS (< R$ 1,00 ou > R$ 10.000,00):');
            console.log('════════════════════════════════════════════════════════════════');
            valoresSuspeitos.slice(0, 10).forEach(div => {
                console.log(`R$ ${div.valor.toFixed(2)} - ${div.nome}`);
                console.log(`   ${div.tipoOriginal} - ${div.mes}/${div.ano} - Original: "${div.valorOriginal}"`);
            });
            console.log('════════════════════════════════════════════════════════════════');
        }
        
        // Mostrar linhas ignoradas (se houver)
        if (linhasIgnoradas.length > 0) {
            console.log('❌ LINHAS QUE NÃO FORAM PARSEADAS:');
            console.log('════════════════════════════════════════════════════════════════');
            linhasIgnoradas.slice(0, 30).forEach(item => {
                console.log(`Linha ${item.linha} [${item.associado}]:`);
                console.log(`  "${item.conteudo}"`);
                console.log('');
            });
            if (linhasIgnoradas.length > 30) {
                console.log(`... e mais ${linhasIgnoradas.length - 30} linha(s)`);
            }
            console.log('════════════════════════════════════════════════════════════════');
            console.log('💡 DICA: Copie essas linhas e envie para análise!');
        }
        console.log('════════════════════════════════════════════════════════════════');

        return {
            total: associados.length,
            totalDividas: totalDividas,
            totalLinhasDivida: totalLinhasDivida,
            linhasIgnoradas: linhasIgnoradas.length,
            valorTotal: valorTotalAssociados,
            valorTotalDividas: valorTotalDividas,
            diferencaTotais: diferenca,
            associados: associados,
            porTipo: contadoresTipo,
            detalhesIgnorados: linhasIgnoradas
        };
    }

    // ========================================================================
    // MOSTRAR INFO DO ARQUIVO
    // ========================================================================
    
    function mostrarInfoArquivo(file) {
        if (!elements.fileInfo) return;

        const fileName = document.getElementById('dividas-file-name');
        const fileSize = document.getElementById('dividas-file-size');
        const fileDate = document.getElementById('dividas-file-date');

        if (fileName) fileName.textContent = file.name;
        if (fileSize) {
            const kb = (file.size / 1024).toFixed(2);
            fileSize.textContent = kb + ' KB';
        }
        if (fileDate) {
            fileDate.textContent = new Date(file.lastModified).toLocaleString('pt-BR');
        }

        elements.fileInfo.style.display = 'block';
    }

    // ========================================================================
    // MOSTRAR PREVIEW
    // ========================================================================
    
    function mostrarPreview(dados) {
        if (!elements.previewContainer || !elements.previewTbody) return;

        // Atualizar contador
        if (elements.previewCount) {
            elements.previewCount.textContent = `${dados.total} associados`;
        }

        // Limpar tabela
        elements.previewTbody.innerHTML = '';

        // Preencher tabela (máximo 100 linhas)
        const maxLinhas = Math.min(100, dados.associados.length);

        for (let i = 0; i < maxLinhas; i++) {
            const assoc = dados.associados[i];
            const tr = document.createElement('tr');

            // Construir HTML das dívidas com badge colorido por tipo
            let dividasHtml = '';
            assoc.dividas.forEach(div => {
                const badgeClass = getTipoBadgeClass(div.tipo);
                dividasHtml += `
                    <div class="divida-item mb-1">
                        <span class="badge ${badgeClass}">${div.tipo}</span>
                        <span class="text-muted small">${div.tipoOriginal}</span>
                        <br>
                        <small>${div.mes}/${div.ano}: <strong>R$ ${div.valor.toFixed(2)}</strong></small>
                    </div>
                `;
            });

            tr.innerHTML = `
                <td>${i + 1}</td>
                <td>
                    <strong>${assoc.nome}</strong>
                    <div class="text-muted small">${assoc.situacao}</div>
                </td>
                <td><code>${assoc.cpf}</code></td>
                <td>${dividasHtml}</td>
                <td>
                    <strong class="text-danger">R$ ${assoc.dividaTotal.toFixed(2)}</strong>
                    <div class="text-muted small">${assoc.dividas.length} dívida(s)</div>
                </td>
            `;

            elements.previewTbody.appendChild(tr);
        }

        if (dados.associados.length > maxLinhas) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td colspan="5" class="text-center text-muted">
                    <i class="fas fa-info-circle me-2"></i>
                    Mostrando ${maxLinhas} de ${dados.associados.length} registros
                </td>
            `;
            elements.previewTbody.appendChild(tr);
        }

        // Mostrar meta do arquivo
        if (elements.fileMeta) {
            elements.fileMeta.innerHTML = `
                <div class="file-meta-item">
                    <div class="file-meta-label">Total Associados</div>
                    <div class="file-meta-value">${dados.total}</div>
                </div>
                <div class="file-meta-item">
                    <div class="file-meta-label">Total Dívidas</div>
                    <div class="file-meta-value">${dados.totalDividas}</div>
                </div>
                <div class="file-meta-item">
                    <div class="file-meta-label">Valor Total</div>
                    <div class="file-meta-value text-danger">R$ ${dados.valorTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                </div>
                <div class="file-meta-item">
                    <div class="file-meta-label">Por Tipo</div>
                    <div class="file-meta-value">
                        <span class="badge bg-primary">${dados.porTipo.SOCIAL}</span>
                        <span class="badge bg-warning">${dados.porTipo.JURIDICO}</span>
                        <span class="badge bg-success">${dados.porTipo.PECULIO}</span>
                        <span class="badge bg-secondary">${dados.porTipo.OUTROS}</span>
                    </div>
                </div>
                ${dados.linhasIgnoradas > 0 ? `
                <div class="file-meta-item" style="grid-column: 1 / -1;">
                    <div class="file-meta-label">⚠️ Linhas Ignoradas</div>
                    <div class="file-meta-value text-warning">
                        ${dados.linhasIgnoradas} linha(s) não foram reconhecidas
                        <br>
                        <small class="text-muted">Abra o Console (F12) para ver detalhes</small>
                    </div>
                </div>
                ` : ''}
                ${dados.diferencaTotais && Math.abs(dados.diferencaTotais) > 0.01 ? `
                <div class="file-meta-item" style="grid-column: 1 / -1;">
                    <div class="file-meta-label">⚠️ Diferença de Valores</div>
                    <div class="file-meta-value text-warning">
                        R$ ${dados.diferencaTotais.toFixed(2)}
                        <br>
                        <small class="text-muted">Totais dos associados vs. Soma das dívidas</small>
                    </div>
                </div>
                ` : ''}
            `;
        }

        // Mostrar container
        elements.previewContainer.style.display = 'block';
        elements.previewBody.style.display = 'block';
    }

    // ========================================================================
    // HELPER: Classe CSS do Badge por Tipo
    // ========================================================================
    
    function getTipoBadgeClass(tipo) {
        const classes = {
            'SOCIAL': 'bg-primary',
            'JURIDICO': 'bg-warning text-dark',
            'PECULIO': 'bg-success',
            'OUTROS': 'bg-secondary'
        };
        return classes[tipo] || 'bg-secondary';
    }

    // ========================================================================
    // TOGGLE PREVIEW
    // ========================================================================
    
    function togglePreview() {
        if (!elements.previewBody) return;
        const isVisible = elements.previewBody.style.display !== 'none';
        elements.previewBody.style.display = isVisible ? 'none' : 'block';
    }

    // ========================================================================
    // VALIDAÇÕES
    // ========================================================================
    
    function mostrarValidacao(mensagens, tipo = 'mixed') {
        if (!elements.validation) return;

        elements.validation.innerHTML = '';

        mensagens.forEach(msg => {
            const div = document.createElement('div');
            div.className = `validation-item ${msg.tipo || tipo}`;

            let icon = 'fa-check-circle';
            if (msg.tipo === 'error' || tipo === 'error') icon = 'fa-times-circle';
            if (msg.tipo === 'warning' || tipo === 'warning') icon = 'fa-exclamation-triangle';

            div.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${msg.mensagem}</span>
            `;

            elements.validation.appendChild(div);
        });

        elements.validation.style.display = 'block';
    }

    function limparValidacoes() {
        if (elements.validation) {
            elements.validation.innerHTML = '';
            elements.validation.style.display = 'none';
        }
    }

    // ========================================================================
    // ESCONDER ELEMENTOS
    // ========================================================================
    
    function esconderPreview() {
        if (elements.previewContainer) {
            elements.previewContainer.style.display = 'none';
        }
    }

    function esconderResultados() {
        if (elements.resultsContainer) {
            elements.resultsContainer.style.display = 'none';
        }
    }

    // ========================================================================
    // LIMPAR ARQUIVO
    // ========================================================================
    
    function limparArquivo() {
        state.arquivoAtual = null;
        state.dadosParsed = null;
        state.debugInfo = null;

        if (elements.fileInput) elements.fileInput.value = '';
        if (elements.fileInfo) elements.fileInfo.style.display = 'none';
        if (elements.uploadBtn) elements.uploadBtn.disabled = true;
        if (elements.clearBtn) elements.clearBtn.style.display = 'none';

        limparValidacoes();
        esconderPreview();
        esconderResultados();
        esconderProgresso();
    }

    // ========================================================================
    // PROCESSAR IMPORTAÇÃO
    // ========================================================================
    
    function processarImportacao() {
        if (!state.arquivoAtual || !state.dadosParsed) {
            mostrarErro('Nenhum arquivo selecionado');
            return;
        }

        if (state.importando) {
            return;
        }

        // Mensagem de confirmação
        let mensagemConfirm = `Confirma a importação de ${state.dadosParsed.totalDividas} dívidas de ${state.dadosParsed.total} associados?\n\nValor total: R$ ${state.dadosParsed.valorTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
        
        if (state.dadosParsed.linhasIgnoradas > 0) {
            mensagemConfirm += `\n\n⚠️ ATENÇÃO: ${state.dadosParsed.linhasIgnoradas} linha(s) de dívida não foram reconhecidas e serão ignoradas.`;
        }

        if (!confirm(mensagemConfirm)) {
            return;
        }

        state.importando = true;
        elements.uploadBtn.disabled = true;
        esconderResultados();
        limparValidacoes();
        mostrarProgresso('Iniciando importação...', 0);

        // Preparar FormData
        const formData = new FormData();
        formData.append('action', 'processar_txt');
        formData.append('associados', JSON.stringify(state.dadosParsed.associados));

        console.log('📡 Enviando para API:', config.apiUrl);
        console.log('📦 Total de associados:', state.dadosParsed.associados.length);
        console.log('📊 Por tipo:', state.dadosParsed.porTipo);

        // Enviar via AJAX
        fetch(config.apiUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                console.log('📡 Status HTTP:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            })
            .then(data => {
                console.log('✅ Resposta da API:', data);

                if (data.success) {
                    mostrarProgresso('Importação concluída!', 100);
                    setTimeout(() => {
                        esconderProgresso();
                        mostrarResultados(data.stats);
                        carregarHistorico();
                        limparArquivo();
                    }, 1000);
                } else {
                    throw new Error(data.message || 'Erro desconhecido');
                }
            })
            .catch(error => {
                console.error('❌ Erro na importação:', error);
                esconderProgresso();
                mostrarErro('Erro ao importar: ' + error.message);
            })
            .finally(() => {
                state.importando = false;
                elements.uploadBtn.disabled = false;
            });
    }

    // ========================================================================
    // MOSTRAR PROGRESSO
    // ========================================================================
    
    function mostrarProgresso(texto, porcentagem) {
        if (!elements.progressContainer) return;

        elements.progressContainer.style.display = 'block';

        if (elements.progressBar) {
            elements.progressBar.style.width = porcentagem + '%';
            elements.progressBar.textContent = porcentagem + '%';
            elements.progressBar.setAttribute('aria-valuenow', porcentagem);
        }

        if (elements.progressText) {
            elements.progressText.textContent = texto;
        }
    }

    function esconderProgresso() {
        if (elements.progressContainer) {
            elements.progressContainer.style.display = 'none';
        }
    }

    // ========================================================================
    // MOSTRAR RESULTADOS
    // ========================================================================
    
    function mostrarResultados(stats) {
        if (!elements.resultsContainer) return;

        const html = `
        <div class="import-results">
            <div class="results-header">
                <h5 class="mb-3">
                    <i class="fas fa-check-circle text-success me-2"></i>
                    Importação Concluída com Sucesso!
                </h5>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <div class="stat-card bg-primary">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${stats.total_associados || 0}</div>
                            <div class="stat-label">Associados Processados</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-success">
                        <div class="stat-icon">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${stats.dividas_inseridas || 0}</div>
                            <div class="stat-label">Dívidas Inseridas</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-warning">
                        <div class="stat-icon">
                            <i class="fas fa-copy"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${stats.dividas_duplicadas || 0}</div>
                            <div class="stat-label">Duplicadas (ignoradas)</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-danger">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${stats.erros || 0}</div>
                            <div class="stat-label">Erros</div>
                        </div>
                    </div>
                </div>
            </div>

            ${stats.por_tipo ? `
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <h6 class="mb-3">
                            <i class="fas fa-chart-pie me-2"></i>
                            Dívidas por Tipo
                        </h6>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left: 4px solid #3b82f6;">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value">${stats.por_tipo.SOCIAL || 0}</div>
                                <div class="stat-label">Contribuição Social</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left: 4px solid #f59e0b;">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <i class="fas fa-gavel"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value">${stats.por_tipo.JURIDICO || 0}</div>
                                <div class="stat-label">Jurídica</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left: 4px solid #10b981;">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <i class="fas fa-piggy-bank"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value">${stats.por_tipo.PECULIO || 0}</div>
                                <div class="stat-label">Pecúlio</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left: 4px solid #6b7280;">
                            <div class="stat-icon" style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);">
                                <i class="fas fa-ellipsis-h"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-value">${stats.por_tipo.OUTROS || 0}</div>
                                <div class="stat-label">Outros</div>
                            </div>
                        </div>
                    </div>
                </div>
            ` : ''}

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="stat-card bg-secondary">
                        <div class="stat-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">${stats.total_dividas || 0}</div>
                            <div class="stat-label">Total de Dívidas Processadas</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="stat-card bg-secondary">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">R$ ${(stats.valor_total || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
                            <div class="stat-label">Valor Total</div>
                        </div>
                    </div>
                </div>
            </div>

            ${stats.associados_nao_encontrados > 0 ? `
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>${stats.associados_nao_encontrados}</strong> associado(s) não foram encontrados pelo CPF no banco de dados
                </div>
            ` : ''}

            ${stats.detalhes_erros && stats.detalhes_erros.length > 0 ? `
                <div class="alert alert-info mt-3">
                    <h6><i class="fas fa-info-circle me-2"></i>Detalhes dos Erros:</h6>
                    <ul class="mb-0">
                        ${stats.detalhes_erros.slice(0, 5).map(erro => `
                            <li><strong>${erro.cpf}</strong> - ${erro.nome}: ${erro.erro}</li>
                        `).join('')}
                        ${stats.detalhes_erros.length > 5 ? `<li class="text-muted">... e mais ${stats.detalhes_erros.length - 5} erro(s)</li>` : ''}
                    </ul>
                </div>
            ` : ''}
        </div>
        `;

        elements.resultsContainer.innerHTML = html;
        elements.resultsContainer.style.display = 'block';

        // Scroll suave até resultados
        elements.resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // ========================================================================
    // CARREGAR HISTÓRICO
    // ========================================================================
    
    function carregarHistorico() {
        if (!elements.historicTable) return;

        elements.historicTable.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0">Carregando histórico...</p>
                </td>
            </tr>
        `;

        const url = `${config.apiUrl}?action=listar_historico`;
        console.log('🔗 Carregando histórico de:', url);

        fetch(url)
            .then(response => {
                console.log('📡 Status da resposta:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const contentType = response.headers.get('content-type');
                console.log('📄 Content-Type:', contentType);
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Resposta não é JSON. Verifique o caminho da API.');
                }
                return response.json();
            })
            .then(data => {
                console.log('✅ Dados recebidos:', data);
                if (data.success && data.historico) {
                    renderizarHistorico(data.historico);
                } else {
                    throw new Error(data.message || 'Erro ao carregar');
                }
            })
            .catch(error => {
                console.error('❌ Erro ao carregar histórico:', error);
                elements.historicTable.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Erro ao carregar histórico: ${error.message}
                            <div class="text-muted small mt-2">
                                Verifique o console (F12) para mais detalhes
                            </div>
                        </td>
                    </tr>
                `;
            });
    }

    // ========================================================================
    // RENDERIZAR HISTÓRICO
    // ========================================================================
    
    function renderizarHistorico(historico) {
        if (!elements.historicTable) return;

        if (historico.length === 0) {
            elements.historicTable.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-inbox me-2"></i>
                        Nenhuma importação realizada ainda
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        historico.forEach((item, index) => {
            const data = new Date(item.data_importacao);
            const dataFormatada = data.toLocaleString('pt-BR');

            const anoMatch = item.observacoes ? item.observacoes.match(/\b(19|20)\d{2}\b/) : null;
            const anoRef = anoMatch ? anoMatch[0] : 'N/A';

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${dataFormatada}</td>
                    <td>${item.funcionario_nome || 'N/A'}</td>
                    <td>${anoRef}</td>
                    <td class="text-center">-</td>
                    <td class="text-center">${item.total_registros || 0}</td>
                    <td class="text-center">
                        <strong class="text-danger">-</strong>
                    </td>
                    <td class="text-center">
                        ${item.erros > 0 ? `<span class="badge bg-warning">${item.erros}</span>` : '<span class="text-success">✓</span>'}
                    </td>
                </tr>
            `;
        });

        elements.historicTable.innerHTML = html;
    }

    // ========================================================================
    // MOSTRAR ERRO
    // ========================================================================
    
    function mostrarErro(mensagem) {
        mostrarValidacao([{ tipo: 'error', mensagem: '❌ ' + mensagem }], 'error');
    }

    // ========================================================================
    // DESABILITAR INTERFACE
    // ========================================================================
    
    function desabilitarInterface() {
        if (elements.uploadBtn) elements.uploadBtn.disabled = true;
        if (elements.dropZone) elements.dropZone.style.pointerEvents = 'none';
    }

    // ========================================================================
    // API PÚBLICA
    // ========================================================================
    
    return {
        init: init,
        limparArquivo: limparArquivo,
        carregarHistorico: carregarHistorico
    };

})();

// ============================================================================
// AUTO-INIT
// ============================================================================

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Aguardar inicialização manual via script inline na página
    });
}