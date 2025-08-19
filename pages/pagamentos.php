<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Pagamentos - Sistema ASSEGO</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(44, 90, 160, 0.1);
            --border-radius: 15px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
            color: var(--dark);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: var(--shadow);
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--light), #e9ecef);
            border-bottom: 2px solid var(--primary);
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            font-weight: 600;
            color: var(--primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.mes-atual::before { background: var(--success); }
        .stat-card.pendentes::before { background: var(--danger); }
        .stat-card.total-ano::before { background: var(--info); }
        .stat-card.valor-total::before { background: var(--warning); }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 600;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge.pago {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .status-badge.pendente {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .status-badge.atrasado {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
        }

        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .table thead th {
            background: var(--light);
            color: var(--primary);
            font-weight: 700;
            border: none;
            padding: 1.25rem 1rem;
        }

        .table tbody tr:hover {
            background: rgba(0, 86, 210, 0.05);
        }

        .btn-action {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            color: white;
        }

        .filter-container {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .mes-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .mes-btn {
            padding: 0.5rem 1rem;
            border: 2px solid var(--primary);
            background: transparent;
            color: var(--primary);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .mes-btn.active,
        .mes-btn:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>

<body>
    <div class="container-fluid p-4">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="mb-0">
                <i class="fas fa-history me-3"></i>
                Histórico de Pagamentos
            </h1>
            <p class="mb-0 mt-2 opacity-90">
                Acompanhe os pagamentos mensais dos associados e identifique pendências
            </p>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="stats-grid">
            <div class="stat-card mes-atual">
                <div class="stat-value text-success" id="pagosMesAtual">0</div>
                <div class="stat-label">Pagos no Mês Atual</div>
            </div>
            <div class="stat-card pendentes">
                <div class="stat-value text-danger" id="pendentesMesAtual">0</div>
                <div class="stat-label">Pendentes no Mês</div>
            </div>
            <div class="stat-card total-ano">
                <div class="stat-value text-info" id="totalPagamentosAno">0</div>
                <div class="stat-label">Total de Pagamentos (Ano)</div>
            </div>
            <div class="stat-card valor-total">
                <div class="stat-value text-warning" id="valorTotalAno">R$ 0</div>
                <div class="stat-label">Valor Total Arrecadado (Ano)</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-container">
            <h5 class="mb-3">
                <i class="fas fa-filter me-2"></i>
                Filtros
            </h5>
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Selecionar Mês:</label>
                    <div class="mes-selector" id="mesSelector">
                        <!-- Meses serão carregados via JavaScript -->
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status:</label>
                    <select class="form-select" id="filtroStatus">
                        <option value="">Todos</option>
                        <option value="PAGO">Pagos</option>
                        <option value="PENDENTE">Pendentes</option>
                        <option value="ATRASADO">Atrasados</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Corporação:</label>
                    <select class="form-select" id="filtroCorporacao">
                        <option value="">Todas</option>
                        <option value="Exército">Exército</option>
                        <option value="Marinha">Marinha</option>
                        <option value="Aeronáutica">Aeronáutica</option>
                        <option value="Agregados">Agregados</option>
                        <option value="Pensionista">Pensionista</option>
                    </select>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="buscaNome" placeholder="Buscar por nome...">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="buscaCPF" placeholder="Buscar por CPF...">
                </div>
                <div class="col-md-4">
                    <button class="btn-action" onclick="aplicarFiltros()">
                        <i class="fas fa-search"></i>
                        Aplicar Filtros
                    </button>
                    <button class="btn btn-outline-secondary ms-2" onclick="limparFiltros()">
                        <i class="fas fa-times"></i>
                        Limpar
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabela de Situação Atual -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-table me-2"></i>
                        Situação dos Pagamentos - <span id="mesAtualTexto">Carregando...</span>
                    </div>
                    <div>
                        <button class="btn-action" onclick="exportarDados()">
                            <i class="fas fa-download"></i>
                            Exportar
                        </button>
                        <button class="btn-action ms-2" onclick="atualizarDados()">
                            <i class="fas fa-sync-alt"></i>
                            Atualizar
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table table-hover mb-0" id="tabelaPagamentos">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Corporação</th>
                                <th>Status Mês Atual</th>
                                <th>Último Pagamento</th>
                                <th>Valor</th>
                                <th>Dias Atraso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="corpoPagamentos">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                    <p class="mt-2 text-muted">Carregando dados...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal de Detalhes do Histórico -->
        <div class="modal fade" id="modalHistorico" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-history me-2"></i>
                            Histórico de Pagamentos - <span id="nomeAssociadoModal"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="conteudoHistoricoModal">
                            <!-- Conteúdo será carregado via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ===== SISTEMA DE HISTÓRICO DE PAGAMENTOS =====
        
        let dadosAssociados = [];
        let mesSelecionado = '';
        
        document.addEventListener('DOMContentLoaded', function() {
            inicializarPagina();
            carregarDados();
        });

        function inicializarPagina() {
            // Configurar mês atual
            const hoje = new Date();
            mesSelecionado = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-01';
            
            // Gerar seletor de meses
            gerarSeletorMeses();
            
            // Atualizar texto do mês atual
            document.getElementById('mesAtualTexto').textContent = formatarMesTexto(mesSelecionado);
        }

        function gerarSeletorMeses() {
            const selector = document.getElementById('mesSelector');
            const hoje = new Date();
            
            // Gerar últimos 12 meses
            for (let i = 0; i < 12; i++) {
                const data = new Date(hoje.getFullYear(), hoje.getMonth() - i, 1);
                const mesValue = data.getFullYear() + '-' + String(data.getMonth() + 1).padStart(2, '0') + '-01';
                const mesTexto = data.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
                
                const btn = document.createElement('button');
                btn.className = 'mes-btn' + (i === 0 ? ' active' : '');
                btn.textContent = mesTexto.charAt(0).toUpperCase() + mesTexto.slice(1);
                btn.onclick = () => selecionarMes(mesValue, btn);
                
                selector.appendChild(btn);
            }
        }

        function selecionarMes(mesValue, btn) {
            // Remover classe active de todos os botões
            document.querySelectorAll('.mes-btn').forEach(b => b.classList.remove('active'));
            
            // Adicionar classe active ao botão clicado
            btn.classList.add('active');
            
            // Atualizar mês selecionado
            mesSelecionado = mesValue;
            document.getElementById('mesAtualTexto').textContent = formatarMesTexto(mesValue);
            
            // Recarregar dados
            carregarDados();
        }

        function formatarMesTexto(mesValue) {
            const data = new Date(mesValue);
            return data.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
                      .replace(/^\w/, c => c.toUpperCase());
        }

        async function carregarDados() {
            try {
                // Aqui você faria a chamada AJAX real para buscar os dados
                // Por enquanto, vou simular dados para demonstração
                
                const response = await fetch(`../api/financeiro/buscar_historico_pagamentos.php?mes=${mesSelecionado}`);
                const dados = await response.json();
                
                if (dados.status === 'success') {
                    dadosAssociados = dados.associados;
                    atualizarEstatisticas(dados.estatisticas);
                    renderizarTabela(dadosAssociados);
                } else {
                    console.error('Erro ao carregar dados:', dados.message);
                    // Dados de exemplo para demonstração
                    carregarDadosExemplo();
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                carregarDadosExemplo();
            }
        }

        function carregarDadosExemplo() {
            // Dados de exemplo para demonstração
            dadosAssociados = [
                {
                    id: 1,
                    nome: "João Silva Santos",
                    cpf: "123.456.789-01",
                    corporacao: "Exército",
                    status_mes_atual: "PAGO",
                    ultimo_pagamento: "2025-01-15",
                    valor_ultimo: 150.00,
                    dias_atraso: 0,
                    total_pagamentos_ano: 12,
                    valor_total_ano: 1800.00
                },
                {
                    id: 2,
                    nome: "Maria Oliveira Costa",
                    cpf: "987.654.321-09",
                    corporacao: "Marinha",
                    status_mes_atual: "PENDENTE",
                    ultimo_pagamento: "2024-12-15",
                    valor_ultimo: 150.00,
                    dias_atraso: 15,
                    total_pagamentos_ano: 11,
                    valor_total_ano: 1650.00
                },
                {
                    id: 3,
                    nome: "Pedro Ferreira Lima",
                    cpf: "456.789.123-45",
                    corporacao: "Aeronáutica",
                    status_mes_atual: "PAGO",
                    ultimo_pagamento: "2025-01-10",
                    valor_ultimo: 150.00,
                    dias_atraso: 0,
                    total_pagamentos_ano: 12,
                    valor_total_ano: 1800.00
                }
            ];

            const estatisticas = {
                pagos_mes_atual: 2,
                pendentes_mes_atual: 1,
                total_pagamentos_ano: 35,
                valor_total_ano: 5250.00
            };

            atualizarEstatisticas(estatisticas);
            renderizarTabela(dadosAssociados);
        }

        function atualizarEstatisticas(stats) {
            document.getElementById('pagosMesAtual').textContent = stats.pagos_mes_atual || 0;
            document.getElementById('pendentesMesAtual').textContent = stats.pendentes_mes_atual || 0;
            document.getElementById('totalPagamentosAno').textContent = stats.total_pagamentos_ano || 0;
            document.getElementById('valorTotalAno').textContent = 'R$ ' + (stats.valor_total_ano || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2});
        }

        function renderizarTabela(dados) {
            const tbody = document.getElementById('corpoPagamentos');
            tbody.innerHTML = '';

            if (dados.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-search fa-2x text-muted"></i>
                            <p class="mt-2 text-muted">Nenhum resultado encontrado</p>
                        </td>
                    </tr>
                `;
                return;
            }

            dados.forEach(associado => {
                const status = obterStatusFormatado(associado.status_mes_atual, associado.dias_atraso);
                const valorFormatado = associado.valor_ultimo ? 
                    'R$ ' + associado.valor_ultimo.toLocaleString('pt-BR', {minimumFractionDigits: 2}) : 
                    '-';

                const row = `
                    <tr>
                        <td><strong>${associado.nome}</strong></td>
                        <td><code>${associado.cpf}</code></td>
                        <td><span class="badge bg-primary">${associado.corporacao}</span></td>
                        <td>${status}</td>
                        <td>${associado.ultimo_pagamento ? new Date(associado.ultimo_pagamento).toLocaleDateString('pt-BR') : '-'}</td>
                        <td><strong>${valorFormatado}</strong></td>
                        <td>
                            ${associado.dias_atraso > 0 ? 
                                `<span class="text-danger"><strong>${associado.dias_atraso} dias</strong></span>` : 
                                '<span class="text-success">Em dia</span>'
                            }
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="verHistorico(${associado.id}, '${associado.nome}')">
                                <i class="fas fa-history"></i>
                                Histórico
                            </button>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        }

        function obterStatusFormatado(status, diasAtraso) {
            if (status === 'PAGO') {
                return '<span class="status-badge pago"><i class="fas fa-check"></i> Pago</span>';
            } else if (diasAtraso > 30) {
                return '<span class="status-badge atrasado"><i class="fas fa-clock"></i> Atrasado</span>';
            } else {
                return '<span class="status-badge pendente"><i class="fas fa-exclamation-triangle"></i> Pendente</span>';
            }
        }

        function aplicarFiltros() {
            const filtroStatus = document.getElementById('filtroStatus').value;
            const filtroCorporacao = document.getElementById('filtroCorporacao').value;
            const buscaNome = document.getElementById('buscaNome').value.toLowerCase();
            const buscaCPF = document.getElementById('buscaCPF').value.replace(/\D/g, '');

            let dadosFiltrados = [...dadosAssociados];

            if (filtroStatus) {
                dadosFiltrados = dadosFiltrados.filter(a => {
                    if (filtroStatus === 'ATRASADO') {
                        return a.dias_atraso > 30;
                    }
                    return a.status_mes_atual === filtroStatus;
                });
            }

            if (filtroCorporacao) {
                dadosFiltrados = dadosFiltrados.filter(a => a.corporacao === filtroCorporacao);
            }

            if (buscaNome) {
                dadosFiltrados = dadosFiltrados.filter(a => a.nome.toLowerCase().includes(buscaNome));
            }

            if (buscaCPF) {
                dadosFiltrados = dadosFiltrados.filter(a => a.cpf.replace(/\D/g, '').includes(buscaCPF));
            }

            renderizarTabela(dadosFiltrados);
        }

        function limparFiltros() {
            document.getElementById('filtroStatus').value = '';
            document.getElementById('filtroCorporacao').value = '';
            document.getElementById('buscaNome').value = '';
            document.getElementById('buscaCPF').value = '';
            renderizarTabela(dadosAssociados);
        }

        function verHistorico(associadoId, nomeAssociado) {
            document.getElementById('nomeAssociadoModal').textContent = nomeAssociado;
            
            // Aqui você carregaria o histórico completo via AJAX
            const modalContent = document.getElementById('conteudoHistoricoModal');
            modalContent.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2">Carregando histórico...</p>
                </div>
            `;
            
            // Simular carregamento
            setTimeout(() => {
                modalContent.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Mês</th>
                                    <th>Data Pagamento</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Forma</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Janeiro/2025</td>
                                    <td>15/01/2025</td>
                                    <td>R$ 150,00</td>
                                    <td><span class="status-badge pago">Confirmado</span></td>
                                    <td>ASAAS</td>
                                </tr>
                                <tr>
                                    <td>Dezembro/2024</td>
                                    <td>12/12/2024</td>
                                    <td>R$ 150,00</td>
                                    <td><span class="status-badge pago">Confirmado</span></td>
                                    <td>ASAAS</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                `;
            }, 1000);
            
            new bootstrap.Modal(document.getElementById('modalHistorico')).show();
        }

        function atualizarDados() {
            carregarDados();
        }

        function exportarDados() {
            alert('Funcionalidade de exportação será implementada!');
        }

        console.log('✅ Sistema de Histórico de Pagamentos carregado!');
    </script>
</body>
</html>