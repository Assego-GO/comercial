/**
 * Sistema Verificar Associados - FUNÇÃO DE EXTRAÇÃO DE RG MELHORADA
 * Solução para RGs menores que 5 dígitos e RGs com pontos
 */

window.VerificarAssociados = (function() {
    'use strict';

    let notifications;
    let resultadosProcessamento = [];
    let dadosOriginais = [];

    // ===== SISTEMA DE NOTIFICAÇÕES =====
    class NotificationSystemVerif {
        constructor() {
            this.container = document.getElementById('toastContainer') || document.getElementById('toastContainerVerif');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainerVerif';
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
            
            if (typeof bootstrap !== 'undefined') {
                const bsToast = new bootstrap.Toast(toast, { delay: duration });
                bsToast.show();
            } else {
                toast.style.display = 'block';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, duration);
            }

            toast.addEventListener('hidden.bs.toast', () => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
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

    // ===== FUNÇÃO DE EXTRAÇÃO DE RG MELHORADA =====
    function extrairRG(linha) {
        console.log('🔍 Extraindo RG de:', linha);
        
        // Remove a numeração inicial (1-, 2-, 01-, 02-, etc.) para não confundir
        let linhaLimpa = linha.replace(/^\d+[\s\-\.]*/, '');
        
        // Padrões para identificar RGs de forma mais inteligente (agora incluindo 2 dígitos!)
        const padroes = [
            // RG explicitamente indicado (RG: 96, RG 30.239, etc.)
            /RG[:\s]*(\d{2,6}\.?\d{0,3})/gi,
            
            // Números após patentes (SGT 85, CAP 29767, CB nome 96)
            /(?:SGT|CAP|TEN|MAJ|TC|CEL)\s+(?:[A-Za-z\s]+\s+)?(\d{2,6}\.?\d{0,3})(?!\s*º)/gi,
            
            // Números de 2-6 dígitos que podem ter ponto e NÃO são seguidos de º
            /(\d{2,6}\.?\d{0,3})(?!\s*º)(?=\s+[A-Za-z]|\s*$)/g,
            
            // Números no final da linha (caso CB nome numero)
            /(\d{2,6})(?:\.\d{1,3})?\s*$/g,
            
            // Formato específicos (XX.XXX, XX, etc.)
            /(\d{2}\.\d{3}|\d{2,6})/g
        ];
        
        const rgsEncontrados = new Set(); // Usar Set para evitar duplicatas automaticamente
        
        for (const padrao of padroes) {
            const matches = [...linhaLimpa.matchAll(padrao)];
            
            for (const match of matches) {
                let rg = match[1] || match[0];
                
                // Limpar o RG mantendo pontos para análise
                let rgOriginal = rg.trim();
                let rgLimpo = rg.replace(/[^\d\.]/g, '');
                
                // Se tem ponto, verificar se é um RG válido (formato XX.XXX)
                if (rgLimpo.includes('.')) {
                    const partes = rgLimpo.split('.');
                    // Se tem formato XX.XXX ou similar, juntar tudo
                    if (partes.length === 2 && partes[0].length >= 1 && partes[1].length >= 1) {
                        rgLimpo = partes.join(''); // Remove o ponto para ter só números
                    }
                }
                
                // Remover todos os caracteres não numéricos para o RG final
                rgLimpo = rgLimpo.replace(/[^\d]/g, '');
                
                // Validações inteligentes
                const isValid = validarRG(rgLimpo, rgOriginal, linha);
                
                if (isValid) {
                    console.log('✅ RG encontrado:', rgLimpo, 'original:', rgOriginal, 'padrão:', padrao.source);
                    rgsEncontrados.add(rgLimpo);
                } else {
                    console.log('❌ RG rejeitado:', rgLimpo, 'original:', rgOriginal);
                }
            }
        }
        
        const resultado = [...rgsEncontrados];
        console.log('📋 RGs finais extraídos:', resultado);
        return resultado;
    }

    function validarRG(rgLimpo, rgOriginal, linhaCompleta) {
        // MUDANÇA: Aceitar RGs de 2 a 6 dígitos (ainda mais flexível!)
        if (rgLimpo.length < 2 || rgLimpo.length > 6) {
            console.log('❌ RG com tamanho inválido:', rgLimpo.length);
            return false;
        }
        
        // Não pode ser um CPF (11 dígitos)
        if (rgLimpo.length === 11) {
            console.log('❌ Descartado por ser CPF:', rgLimpo);
            return false;
        }
        
        // Não pode ser a numeração inicial da linha (1, 2, 3, 01, 02, etc.)
        const numeroInicial = linhaCompleta.match(/^(\d+)/);
        if (numeroInicial && numeroInicial[1] === rgLimpo) {
            console.log('❌ Descartado por ser numeração inicial:', rgLimpo);
            return false;
        }
        
        // Não pode ser seguido de º (grau de patente)
        if (rgOriginal.includes('º') || linhaCompleta.includes(rgLimpo + 'º')) {
            console.log('❌ Descartado por ter º (patente):', rgLimpo);
            return false;
        }
        
        // Para RGs de 2 dígitos, ser mais criterioso
        if (rgLimpo.length === 2) {
            // Sequências muito comuns de 2 dígitos que provavelmente não são RGs
            const sequenciasComuns = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20'];
            
            // Se for uma sequência comum E estiver no início da linha, rejeitar
            if (sequenciasComuns.includes(rgLimpo) && linhaCompleta.match(new RegExp(`^${rgLimpo}[\\s\\-\\.]`))) {
                console.log('❌ RG de 2 dígitos descartado por ser numeração provável:', rgLimpo);
                return false;
            }
        }
        
        // Números muito comuns que provavelmente não são RGs
        if (['12', '23', '34', '45', '56', '67', '78', '89', '99', '00', '123', '1234', '12345'].includes(rgLimpo)) {
            console.log('❌ Descartado por ser sequência comum:', rgLimpo);
            return false;
        }
        
        // Se chegou até aqui, é válido
        console.log('✅ RG válido:', rgLimpo, 'tamanho:', rgLimpo.length);
        return true;
    }

    function extrairCPF(linha) {
        const padrao = /(\d{11})/g;
        const matches = [...linha.matchAll(padrao)];
        return matches.map(match => match[1]);
    }

    function extrairNome(linha) {
        console.log('🔍 Extraindo nome de:', linha);
        
        let nome = linha;
        
        nome = nome.replace(/^\d+[\s\-\.]+/g, '');
        nome = nome.replace(/RG[:\s]*\d{2,6}\.?\d{0,3}/gi, '');
        nome = nome.replace(/\b(CAP|1°?\s*SGT|2°?\s*SGT|3°?\s*SGT|CB|TEN|MAJ|TC|CEL)\b/gi, '');
        nome = nome.replace(/\b\d{3,6}\b/g, '');
        nome = nome.replace(/[\-\.\:]/g, ' ');
        nome = nome.replace(/\s+/g, ' ').trim();
        
        console.log('✅ Nome final extraído:', nome);
        return nome.length >= 3 ? nome : null;
    }

    function processarLinha(linha, indice) {
        console.log(`\n🔄 PROCESSANDO LINHA ${indice}:`, linha);
        
        if (!linha || linha.trim().length < 3) {
            console.log('❌ Linha muito curta');
            return null;
        }
        
        const linhaLimpa = linha.trim().toLowerCase();
        
        if (linhaLimpa.includes('lista qrf') || 
            linhaLimpa.includes('nome completo') ||
            linhaLimpa.includes('posto e graduação')) {
            console.log('❌ Cabeçalho ignorado');
            return null;
        }
        
        const rgs = extrairRG(linha);
        const cpfs = extrairCPF(linha);
        const nome = extrairNome(linha);
        
        if (rgs.length === 0 && (!nome || nome.length < 3)) {
            console.log('❌ Sem dados válidos');
            return null;
        }
        
        const resultado = {
            linhaoriginal: linha.trim(),
            indice: indice,
            nome: nome || 'Nome não identificado',
            rgs: rgs,
            cpfs: cpfs,
            rgprincipal: rgs.length > 0 ? rgs[0] : null,
            cpfprincipal: cpfs.length > 0 ? cpfs[0] : null
        };
        
        console.log('✅ PROCESSADO:', resultado);
        return resultado;
    }

    // ===== RESTO DAS FUNÇÕES MANTIDAS IGUAIS =====
    function init(config = {}) {
        console.log('🔍 Inicializando VerificarAssociados MELHORADO...', config);
        
        notifications = new NotificationSystemVerif();
        attachEventListeners();
        notifications.show('Sistema Verificar Associados MELHORADO carregado!', 'info', 3000);
        console.log('✅ VerificarAssociados inicializado com extração de RG melhorada');
    }

    function attachEventListeners() {
        console.log('🔗 Anexando event listeners...');
        
        const btnProcessar = document.getElementById('btnProcessarLista');
        const btnPreview = document.getElementById('btnPreviewExtracao');
        const btnExemplo = document.getElementById('btnExemploLista');
        const btnLimpar = document.getElementById('btnLimparTudo');
        const btnExportar = document.getElementById('btnExportarResultado');
        
        if (btnProcessar) {
            btnProcessar.addEventListener('click', processarLista);
            console.log('✅ Event listener: btnProcessarLista');
        }
        
        if (btnPreview) {
            btnPreview.addEventListener('click', mostrarPreview);
        }
        
        if (btnExemplo) {
            btnExemplo.addEventListener('click', mostrarExemplo);
        }
        
        if (btnLimpar) {
            btnLimpar.addEventListener('click', limparTudo);
        }
        
        if (btnExportar) {
            btnExportar.addEventListener('click', exportarResultados);
        }

        const btnProcessarAposPreview = document.getElementById('btnProcessarAposPreview');
        const btnUsarExemplo = document.getElementById('btnUsarExemplo');
        
        if (btnProcessarAposPreview) {
            btnProcessarAposPreview.addEventListener('click', () => {
                processarLista();
                const modal = document.getElementById('modalPreviewExtracao');
                if (modal && typeof bootstrap !== 'undefined') {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                }
            });
        }
        
        if (btnUsarExemplo) {
            btnUsarExemplo.addEventListener('click', usarExemplo);
        }

        const listaInput = document.getElementById('listaInput');
        if (listaInput) {
            listaInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.max(150, this.scrollHeight) + 'px';
            });
        }
        
        console.log('✅ Todos os event listeners anexados');
    }

    function processarLista() {
        console.log('🔍 Processando lista com extração MELHORADA...');
        
        const listaInput = document.getElementById('listaInput');
        if (!listaInput) {
            console.error('❌ Elemento listaInput não encontrado');
            alert('Erro: Campo de lista não encontrado');
            return;
        }

        const textoLista = listaInput.value.trim();
        
        if (!textoLista) {
            alert('Por favor, cole uma lista para processar');
            return;
        }

        try {
            // Mostrar loading
            const tabelaLoading = document.getElementById('tabelaLoading');
            if (tabelaLoading) tabelaLoading.classList.remove('d-none');
            
            // Extrair dados com a nova função melhorada
            const linhas = textoLista.split('\n');
            const dadosExtraidos = [];
            
            linhas.forEach((linha, indice) => {
                const dadosLinha = processarLinha(linha, indice + 1);
                if (dadosLinha) {
                    dadosExtraidos.push(dadosLinha);
                }
            });

            if (dadosExtraidos.length === 0) {
                throw new Error('Nenhum dado válido encontrado na lista.');
            }

            console.log('✅ Dados extraídos com nova função:', dadosExtraidos.length, 'registros');

            // CHAMAR API REAL
            chamarAPIVerificacao(dadosExtraidos);
            
        } catch (error) {
            console.error('❌ Erro:', error);
            
            const tabelaLoading = document.getElementById('tabelaLoading');
            if (tabelaLoading) tabelaLoading.classList.add('d-none');
            
            if (notifications) {
                notifications.show('❌ Erro: ' + error.message, 'error');
            } else {
                alert('❌ Erro: ' + error.message);
            }
        }
    }

    async function chamarAPIVerificacao(dadosExtraidos) {
        console.log('📡 Chamando API de verificação...', dadosExtraidos);
        
        try {
            const response = await fetch('../api/financeiro/verificar_associados.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    dados: dadosExtraidos
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const resultado = await response.json();
            console.log('📥 Resposta da API:', resultado);

            if (resultado.success) {
                resultadosProcessamento = resultado.resultados;
                dadosOriginais = [...resultado.resultados];
                
                preencherTabelaResultados(resultado.resultados);
                atualizarEstatisticas(resultado.estatisticas || calcularEstatisticas(resultado.resultados));
                mostrarResultados();
                
                if (notifications) {
                    notifications.show(`✅ ${resultado.resultados.length} registros processados com extração MELHORADA!`, 'success');
                } else {
                    alert(`✅ ${resultado.resultados.length} registros processados!`);
                }
            } else {
                throw new Error(resultado.message || 'Erro desconhecido na API');
            }

        } catch (error) {
            console.error('❌ Erro na API:', error);
            
            if (notifications) {
                notifications.show('❌ Erro ao processar: ' + error.message, 'error');
            } else {
                alert('❌ Erro ao processar: ' + error.message);
            }
        } finally {
            // Esconder loading
            const tabelaLoading = document.getElementById('tabelaLoading');
            if (tabelaLoading) tabelaLoading.classList.add('d-none');
        }
    }

    // === TODAS AS OUTRAS FUNÇÕES MANTIDAS IGUAIS ===
    function calcularEstatisticas(resultados) {
        return {
            total: resultados.length,
            filiados: resultados.filter(r => r.statusverificacao === 'filiado').length,
            naofiliados: resultados.filter(r => r.statusverificacao === 'naofiliado').length,
            naoencontrados: resultados.filter(r => r.statusverificacao === 'naoencontrado').length
        };
    }

    function mostrarPreview() {
        console.log('👁️ Mostrando preview...');
        
        const listaInput = document.getElementById('listaInput');
        if (!listaInput || !listaInput.value.trim()) {
            alert('Cole uma lista primeiro para ver o preview');
            return;
        }

        const linhas = listaInput.value.trim().split('\n');
        const dadosExtraidos = [];
        const rgsEncontrados = new Set();
        const cpfsEncontrados = new Set();

        linhas.forEach((linha, indice) => {
            const dadosLinha = processarLinha(linha, indice + 1);
            if (dadosLinha) {
                dadosExtraidos.push(dadosLinha);
                dadosLinha.rgs.forEach(rg => rgsEncontrados.add(rg));
                dadosLinha.cpfs.forEach(cpf => cpfsEncontrados.add(cpf));
            }
        });

        const previewRGs = document.getElementById('previewRGs');
        if (previewRGs) {
            previewRGs.innerHTML = Array.from(rgsEncontrados).map(rg => 
                `<div><i class="fas fa-id-card text-primary me-2"></i>${rg}</div>`
            ).join('') || '<div class="text-muted">Nenhum RG encontrado</div>';
        }

        const previewCPFs = document.getElementById('previewCPFs');
        if (previewCPFs) {
            previewCPFs.innerHTML = Array.from(cpfsEncontrados).map(cpf => 
                `<div><i class="fas fa-id-badge text-success me-2"></i>${cpf}</div>`
            ).join('') || '<div class="text-muted">Nenhum CPF encontrado</div>';
        }

        const previewPessoas = document.getElementById('previewPessoas');
        if (previewPessoas) {
            previewPessoas.innerHTML = dadosExtraidos.map(pessoa => 
                `<div>
                    <strong>${pessoa.nome || 'Nome não identificado'}</strong><br>
                    <small>${pessoa.rgprincipal ? `RG: ${pessoa.rgprincipal}` : ''}
                    ${pessoa.cpfprincipal ? ` | CPF: ${pessoa.cpfprincipal}` : ''}</small>
                </div>`
            ).join('');
        }

        const modal = document.getElementById('modalPreviewExtracao');
        if (modal) {
            if (typeof bootstrap !== 'undefined') {
                new bootstrap.Modal(modal).show();
            } else {
                modal.style.display = 'block';
                modal.classList.add('show');
            }
        }
    }

    function preencherTabelaResultados(resultados) {
        const tbody = document.getElementById('tabelaResultadosBody');
        if (!tbody) return;

        const html = resultados.map((item, index) => {
            let statusClass, statusText;
            
            switch(item.statusverificacao) {
                case 'filiado':
                    statusClass = 'status-filiado';
                    statusText = 'Filiado';
                    break;
                case 'naofiliado':
                    statusClass = 'status-nao-filiado';
                    statusText = 'Não Filiado';
                    break;
                default:
                    statusClass = 'status-nao-encontrado';
                    statusText = 'Não Encontrado';
            }
            
            let nomeExibir;
            if (item.statusverificacao === 'filiado' || item.statusverificacao === 'naofiliado') {
                nomeExibir = item.nomeassociado || item.nomeextraido || 'Nome não identificado';
            } else {
                nomeExibir = item.nomeextraido || 'Nome não identificado';
            }
            
            return `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${nomeExibir}</strong></td>
                    <td>${item.rgextraido || item.rgassociado || '-'}</td>
                    <td>${item.cpfextraido || item.cpfassociado || '-'}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>${item.corporacao || '-'}</td>
                    <td>${item.patente || '-'}</td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = html;
    }

    function atualizarEstatisticas(estatisticas) {
        const totalEl = document.getElementById('totalProcessados');
        const filiadosEl = document.getElementById('totalFiliados');
        const naoFiliadosEl = document.getElementById('totalNaoFiliados');
        const naoEncontradosEl = document.getElementById('totalNaoEncontrados');
        
        if (totalEl) totalEl.textContent = estatisticas.total || 0;
        if (filiadosEl) filiadosEl.textContent = estatisticas.filiados || 0;
        if (naoFiliadosEl) naoFiliadosEl.textContent = estatisticas.naofiliados || 0;
        if (naoEncontradosEl) naoEncontradosEl.textContent = estatisticas.naoencontrados || 0;

        const stats = document.getElementById('estatisticasProcessamento');
        if (stats) stats.classList.remove('d-none');
        
        const btnExportar = document.getElementById('btnExportarResultado');
        if (btnExportar) btnExportar.disabled = false;
    }

    function mostrarResultados() {
        const container = document.getElementById('resultadosContainer');
        if (container) container.classList.remove('d-none');
    }

    function mostrarExemplo() {
        const modal = document.getElementById('modalExemplo');
        if (modal) {
            if (typeof bootstrap !== 'undefined') {
                new bootstrap.Modal(modal).show();
            } else {
                modal.style.display = 'block';
                modal.classList.add('show');
            }
        }
    }

    function usarExemplo() {
        const exemploTexto = `01 - RG:26.096 Charles Raniel Santos de Oliveira 
02 - RG: 30.239 Henrique Moreira Mendes 
03 - 3°SGT 35.782 Jefferson Pereira coelho 
04 - 1° SGT 33.140 Gumercindo Lemes dos Santos Filho
5 - Cap 29.767 Luís Alves dos Santos
6- 3º SGT 34.933 Antônio Carlos Franco Batista de Moura
7. 3ºSGT 35.912 Murilo Renato Parente Carneiro
8. 1⁰ SGT 31.004 José Augusto de Almeida Bento
9. 3° Sgt 35.353 Maurício leite de bessa
10. CB Lemuel Santiago Diniz 36423
11. ⁠ CAP 29738 Eládio José do Prado Neto
12. 2° Sgt 33.138 Wasley Lauri Amaral`;

        const listaInput = document.getElementById('listaInput');
        if (listaInput) {
            listaInput.value = exemploTexto;
            listaInput.style.height = 'auto';
            listaInput.style.height = Math.max(150, listaInput.scrollHeight) + 'px';
        }
        
        const modal = document.getElementById('modalExemplo');
        if (modal && typeof bootstrap !== 'undefined') {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        }

        if (notifications) {
            notifications.show('Exemplo carregado! Agora com extração MELHORADA!', 'info');
        }
    }

    function limparTudo() {
        const listaInput = document.getElementById('listaInput');
        if (listaInput) {
            listaInput.value = '';
            listaInput.style.height = '150px';
        }
        
        const resultadosContainer = document.getElementById('resultadosContainer');
        if (resultadosContainer) resultadosContainer.classList.add('d-none');
        
        const btnExportar = document.getElementById('btnExportarResultado');
        if (btnExportar) btnExportar.disabled = true;
        
        resultadosProcessamento = [];
        dadosOriginais = [];
        
        if (notifications) {
            notifications.show('Lista limpa!', 'info');
        }
    }

    function exportarResultados() {
        if (!resultadosProcessamento.length) {
            alert('Nenhum resultado para exportar');
            return;
        }

        const csv = 'Nome Extraído,Nome Associado,RG,CPF,Status,Corporação,Patente\n' + 
            resultadosProcessamento.map(item => 
                `"${item.nomeextraido || ''}","${item.nomeassociado || ''}","${item.rgextraido || item.rgassociado || ''}","${item.cpfextraido || item.cpfassociado || ''}","${item.statusverificacao}","${item.corporacao || ''}","${item.patente || ''}"`
            ).join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `verificacao_associados_${new Date().toISOString().slice(0,10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        if (notifications) {
            notifications.show('Arquivo exportado!', 'success');
        }
    }

    function debug() {
        const status = {
            resultados: resultadosProcessamento.length,
            bootstrap: typeof bootstrap !== 'undefined',
            notifications: !!notifications,
            elementos: {}
        };
        
        const elementos = ['btnProcessarLista', 'btnPreviewExtracao', 'btnExemploLista', 'listaInput'];
        elementos.forEach(id => {
            status.elementos[id] = !!document.getElementById(id);
        });
        
        return status;
    }

    // ===== API PÚBLICA =====
    return {
        init: init,
        processarLista: processarLista,
        mostrarPreview: mostrarPreview,
        mostrarExemplo: mostrarExemplo,
        usarExemplo: usarExemplo,
        limparTudo: limparTudo,
        exportarResultados: exportarResultados,
        debug: debug,
        // Novas funções para debug
        extrairRG: extrairRG,
        validarRG: validarRG
    };

})();

// Função global para ver detalhes (se necessário)
function verDetalhesAssociado(associadoId) {
    if (associadoId) {
        console.log('Ver detalhes do associado:', associadoId);
        // Implementar abertura de modal ou redirect
    }
}

console.log('✅ VerificarAssociados MELHORADO carregado - Aceita RGs de 2-6 dígitos e trata pontos corretamente!');