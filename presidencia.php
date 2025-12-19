// Na função que renderiza os cards de documentos

function renderDocumentoCard(doc) {
    // Garantir que o ID seja string e escapar aspas
    const docId = String(doc.id).replace(/'/g, "\\'");
    const tipoDoc = doc.tipo_documento;
    const temDocumento = doc.tem_documento === 1 || doc.tem_documento === '1';
    
    // Verificar se é um agregado sem documento
    const isAgregadoSemDoc = docId.startsWith('AGR_');
    const opacidade = isAgregadoSemDoc ? 'opacity-50' : '';
    
    // Gerar botões de ação baseado no status
    let botoesAcao = '';
    
    if (doc.status_fluxo === 'AGUARDANDO_ASSINATURA') {
        if (isAgregadoSemDoc) {
            botoesAcao = `
                <button class="btn btn-secondary btn-sm ${opacidade}" disabled 
                        title="Aguardando upload do documento físico">
                    <i class="fas fa-clock me-1"></i>Aguardando Documento
                </button>
            `;
        } else {
            botoesAcao = `
                <button class="btn btn-success btn-sm" 
                        onclick="assinarDocumento('${docId}', '${tipoDoc}')">
                    <i class="fas fa-check-circle me-1"></i>Assinar
                </button>
            `;
        }
    } else if (doc.status_fluxo === 'ASSINADO') {
        if (isAgregadoSemDoc) {
            botoesAcao = `
                <button class="btn btn-secondary btn-sm ${opacidade}" disabled 
                        title="Aguardando upload do documento físico">
                    <i class="fas fa-clock me-1"></i>Aguardando Documento
                </button>
            `;
        } else {
            botoesAcao = `
                <button class="btn btn-primary btn-sm" 
                        onclick="finalizarProcessoUnificado('${docId}', '${tipoDoc}')">
                    <i class="fas fa-flag-checkered me-1"></i>Finalizar
                </button>
            `;
        }
    } else if (doc.status_fluxo === 'FINALIZADO') {
        botoesAcao = `
            <span class="badge bg-success">
                <i class="fas fa-check-double me-1"></i>Finalizado
            </span>
        `;
    }
    
    // Botão de visualizar (sempre habilitado se tiver arquivo)
    const btnVisualizar = doc.caminho_arquivo ? `
        <button class="btn btn-outline-primary btn-sm" 
                onclick="visualizarDocumento('${docId}', '${tipoDoc}')">
            <i class="fas fa-eye me-1"></i>Visualizar
        </button>
    ` : '';

    // Formatar CPF
    const cpfFormatado = doc.cpf ? doc.cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : 'N/A';
    
    // Formatar data
    const dataFormatada = doc.data_upload ? new Date(doc.data_upload).toLocaleDateString('pt-BR') : 'N/A';
    
    // Badge de status
    const statusColors = {
        'DIGITALIZADO': 'info',
        'AGUARDANDO_ASSINATURA': 'warning',
        'ASSINADO': 'primary',
        'FINALIZADO': 'success'
    };
    const statusColor = statusColors[doc.status_fluxo] || 'secondary';
    
    // Retornar o HTML do card
    return `
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card documento-card ${isAgregadoSemDoc ? 'border-warning' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0">
                            <i class="fas ${tipoDoc === 'SOCIO' ? 'fa-user' : 'fa-user-friends'} me-2"></i>
                            ${doc.nome || 'Sem nome'}
                        </h6>
                        <span class="badge bg-${statusColor}">
                            ${doc.status_descricao}
                        </span>
                    </div>
                    
                    <div class="card-text small">
                        <p class="mb-1"><strong>CPF:</strong> ${cpfFormatado}</p>
                        <p class="mb-1"><strong>Tipo:</strong> ${doc.tipo_descricao}</p>
                        ${doc.titular_nome ? `<p class="mb-1"><strong>Titular:</strong> ${doc.titular_nome}</p>` : ''}
                        <p class="mb-1"><strong>Data:</strong> ${dataFormatada}</p>
                        <p class="mb-1"><strong>Departamento:</strong> ${doc.departamento_atual_nome || 'N/A'}</p>
                        ${isAgregadoSemDoc ? `<p class="mb-1 text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Documento físico pendente</p>` : ''}
                    </div>
                    
                    <div class="d-flex gap-2 mt-3">
                        ${botoesAcao}
                        ${btnVisualizar}
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Função para renderizar documentos unificados
function renderizarDocumentosUnificados(documentos) {
    const container = $('#documentosUnificadosContainer, #listaDocumentos, .documentos-container').first();
    
    if (!container.length) {
        console.error('❌ Container de documentos não encontrado');
        return;
    }

    if (!documentos || documentos.length === 0) {
        container.html('<div class="alert alert-info">Nenhum documento encontrado</div>');
        return;
    }

    console.log('📋 Renderizando documentos unificados:', documentos.length);

    const html = documentos.map(doc => {
        // Garantir que o ID seja string e escapar aspas
        const docId = String(doc.id).replace(/'/g, "\\'");
        const tipoDoc = doc.tipo_documento;
        const temDocumento = doc.tem_documento === 1 || doc.tem_documento === '1';
        
        // Verificar se é um agregado sem documento
        const isAgregadoSemDoc = docId.startsWith('AGR_');
        const opacidade = isAgregadoSemDoc ? 'opacity-50' : '';
        
        // Gerar botões de ação baseado no status
        let botoesAcao = '';
        
        if (doc.status_fluxo === 'AGUARDANDO_ASSINATURA') {
            if (isAgregadoSemDoc) {
                botoesAcao = `
                    <button class="btn btn-secondary btn-sm ${opacidade}" disabled 
                            title="Aguardando upload do documento físico">
                        <i class="fas fa-clock me-1"></i>Aguardando Documento
                    </button>
                `;
            } else {
                botoesAcao = `
                    <button class="btn btn-success btn-sm" 
                            onclick="assinarDocumento('${docId}', '${tipoDoc}')">
                        <i class="fas fa-check-circle me-1"></i>Assinar
                    </button>
                `;
            }
        } else if (doc.status_fluxo === 'ASSINADO') {
            if (isAgregadoSemDoc) {
                botoesAcao = `
                    <button class="btn btn-secondary btn-sm ${opacidade}" disabled 
                            title="Aguardando upload do documento físico">
                        <i class="fas fa-clock me-1"></i>Aguardando Documento
                    </button>
                `;
            } else {
                botoesAcao = `
                    <button class="btn btn-primary btn-sm" 
                            onclick="finalizarProcessoUnificado('${docId}', '${tipoDoc}')">
                        <i class="fas fa-flag-checkered me-1"></i>Finalizar
                    </button>
                `;
            }
        } else if (doc.status_fluxo === 'FINALIZADO') {
            botoesAcao = `
                <span class="badge bg-success">
                    <i class="fas fa-check-double me-1"></i>Finalizado
                </span>
            `;
        }
        
        // Botão de visualizar (sempre habilitado se tiver arquivo)
        const btnVisualizar = doc.caminho_arquivo ? `
            <button class="btn btn-outline-primary btn-sm" 
                    onclick="visualizarDocumento('${docId}', '${tipoDoc}')">
                <i class="fas fa-eye me-1"></i>Visualizar
            </button>
        ` : '';

        // Formatar CPF
        const cpfFormatado = doc.cpf ? doc.cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : 'N/A';
        
        // Formatar data
        const dataFormatada = doc.data_upload ? new Date(doc.data_upload).toLocaleDateString('pt-BR') : 'N/A';
        
        // Badge de status
        const statusColors = {
            'DIGITALIZADO': 'info',
            'AGUARDANDO_ASSINATURA': 'warning',
            'ASSINADO': 'primary',
            'FINALIZADO': 'success'
        };
        const statusColor = statusColors[doc.status_fluxo] || 'secondary';
        
        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card documento-card ${isAgregadoSemDoc ? 'border-warning' : ''}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">
                                <i class="fas ${tipoDoc === 'SOCIO' ? 'fa-user' : 'fa-user-friends'} me-2"></i>
                                ${doc.nome || 'Sem nome'}
                            </h6>
                            <span class="badge bg-${statusColor}">
                                ${doc.status_descricao}
                            </span>
                        </div>
                        
                        <div class="card-text small">
                            <p class="mb-1"><strong>CPF:</strong> ${cpfFormatado}</p>
                            <p class="mb-1"><strong>Tipo:</strong> ${doc.tipo_descricao}</p>
                            ${doc.titular_nome ? `<p class="mb-1"><strong>Titular:</strong> ${doc.titular_nome}</p>` : ''}
                            <p class="mb-1"><strong>Data:</strong> ${dataFormatada}</p>
                            <p class="mb-1"><strong>Departamento:</strong> ${doc.departamento_atual_nome || 'N/A'}</p>
                            ${isAgregadoSemDoc ? `<p class="mb-1 text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Documento físico pendente</p>` : ''}
                        </div>
                        
                        <div class="d-flex gap-2 mt-3">
                            ${botoesAcao}
                            ${btnVisualizar}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    container.html(`<div class="row">${html}</div>`);
}

// Função auxiliar para determinar a cor do badge de status
function getStatusColor(status) {
    const cores = {
        'DIGITALIZADO': 'info',
        'AGUARDANDO_ASSINATURA': 'warning',
        'ASSINADO': 'primary',
        'FINALIZADO': 'success'
    };
    return cores[status] || 'secondary';
}

// Função auxiliar para formatar CPF
function formatarCPF(cpf) {
    if (!cpf) return 'N/A';
    const numeros = cpf.replace(/\D/g, '');
    if (numeros.length === 11) {
        return numeros.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }
    return cpf;
}

// Função auxiliar para formatar data
function formatarData(data) {
    if (!data) return 'N/A';
    const d = new Date(data);
    return d.toLocaleDateString('pt-BR');
}

// Função para assinar documento (já existente, mas garantir que recebe string)
async function assinarDocumento(docId, tipoDoc) {
    // Converter para string e verificar se é agregado sem documento
    docId = String(docId);
    
    if (docId.startsWith('AGR_')) {
        Swal.fire({
            icon: 'warning',
            title: 'Documento não disponível',
            text: 'Este agregado ainda não possui documento digitalizado. Por favor, faça o upload do documento físico primeiro.',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    console.log(`🎯 Assinando documento: ${docId} (${tipoDoc})`);
    
    // ...resto do código de assinatura...
}

// Função para finalizar processo (já existente, mas garantir que recebe string)
async function finalizarProcessoUnificado(docId, tipoDoc) {
    // Converter para string e verificar se é agregado sem documento
    docId = String(docId);
    
    if (docId.startsWith('AGR_')) {
        Swal.fire({
            icon: 'warning',
            title: 'Documento não disponível',
            text: 'Este agregado ainda não possui documento digitalizado. Por favor, faça o upload do documento físico primeiro.',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    console.log(`🎯 Finalizando documento: ${docId} (${tipoDoc})`);

    try {
        const resp = await fetch('../api/desfiliacao_finalizar_presidencia.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ documento_id: parseInt(docId, 10) })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Finalizado',
                text: 'Desfiliação finalizada e associado atualizado.',
                timer: 2000,
                showConfirmButton: false
            });
            // Recarrega a lista após sucesso
            setTimeout(() => window.location.reload(), 1200);
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: data.message || 'Falha ao finalizar' });
        }
    } catch (e) {
        console.error(e);
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Erro de comunicação com o servidor.' });
    }
}