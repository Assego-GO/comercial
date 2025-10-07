<?php
/**
 * Página de Controle de Dependentes - Sistema ASSEGO
 * pages/dependentes_18anos.php
 * VERSÃO CORRIGIDA SEM ESTATÍSTICAS BUGADAS
 * 
 * Controla dependentes que completaram ou estão prestes a completar 18 anos
 */

// Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once './components/header.php';
require_once '../classes/Permissoes.php';

// Inicia autenticação
$auth = new Auth();

// Verifica se está logado
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

// Pega dados do usuário logado
$usuarioLogado = $auth->getUser();

// Define o título da página
$page_title = 'Controle de Dependentes - 18 Anos - ASSEGO';

// Verificar permissões
$temPermissaoControle = false;
$motivoNegacao = '';
$departamentoUsuario = $usuarioLogado['departamento_id'] ?? null;
$permissoes = Permissoes::getInstance();

// Verifica se tem permissão para gerenciar dependentes
if ($permissoes->hasPermission('COMERCIAL_DEPENDENTES', 'VIEW')) {
    $temPermissaoControle = true;
} else {
    $motivoNegacao = 'Você não tem permissão para acessar esta funcionalidade.';
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'dependentes',
    'notificationCount' => 0,
    'showSearch' => true
]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    
    <!-- Estilos Personalizados -->
    <style>
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #003d94;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .content-area {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title i {
            color: var(--warning);
        }

        .page-subtitle {
            color: #64748b;
            font-size: 1rem;
            font-weight: 500;
        }

        .alert-premium {
            padding: 1.25rem;
            border-radius: 12px;
            border: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .alert-warning-premium {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
            color: #92400e;
            border-left: 4px solid var(--warning);
        }

        .alert-danger-premium {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .filtros-container {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }

        .filtros-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .filtros-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filtros-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .filtro-group {
            flex: 1;
            min-width: 200px;
        }

        .form-control-premium, .form-select-premium {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control-premium:focus, .form-select-premium:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 86, 210, 0.1);
            outline: none;
        }

        .form-label-premium {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-premium {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-md);
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary-premium {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-secondary-premium {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
        }

        .btn-success-premium {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--warning) 0%, #dc2626 100%);
            color: white;
            padding: 1.5rem;
        }

        .table-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .table-subtitle {
            margin: 0.5rem 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .table-premium {
            margin: 0;
        }

        .table-premium thead {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .table-premium thead th {
            padding: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            color: var(--dark);
            border: none;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-premium tbody td {
            padding: 0.875rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-premium tbody tr:hover {
            background: rgba(245, 158, 11, 0.05);
        }

        .badge-premium {
            padding: 0.5rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .badge-critico {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
        }

        .badge-atencao {
            background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
            color: white;
        }

        .badge-normal {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
        }

        .btn-action {
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            margin: 0 0.25rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            border: none;
            cursor: pointer;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-contato {
            background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
            color: white;
        }

        .btn-detalhes {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .loading-spinner {
            text-align: center;
            padding: 3rem;
        }

        .pagination-container {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: var(--shadow-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination {
            margin: 0;
        }

        .page-link {
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            color: var(--dark);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .page-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }

            .filtros-form {
                flex-direction: column;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (!$temPermissaoControle): ?>
            <!-- Sem Permissão -->
            <div class="alert-premium alert-danger-premium">
                <i class="fas fa-ban fa-2x"></i>
                <div>
                    <h4>Acesso Negado</h4>
                    <p class="mb-0"><?php echo htmlspecialchars($motivoNegacao); ?></p>
                    <div class="mt-3">
                        <a href="../pages/dashboard.php" class="btn btn-premium btn-secondary-premium">
                            <i class="fas fa-arrow-left me-2"></i>
                            Voltar ao Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Com Permissão - Conteúdo Normal -->
            
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    Controle de Dependentes - 18 Anos
                </h1>
                <p class="page-subtitle">
                    Monitore dependentes filhos(as) que completaram ou estão prestes a completar 18 anos
                </p>
            </div>

            <!-- Alert informativo -->
            <div class="alert-premium alert-warning-premium">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <h6 class="mb-1"><strong>Importante</strong></h6>
                    <p class="mb-0">
                        Dependentes filhos(as) que completam 18 anos devem começar a pagar mensalidade própria ou informar situação.
                    </p>
                </div>
            </div>

            <!-- Filtros de Pesquisa -->
            <div class="filtros-container">
                <div class="filtros-header">
                    <h3 class="filtros-title">
                        <i class="fas fa-filter"></i>
                        Filtros de Pesquisa
                    </h3>
                </div>
                
                <form class="filtros-form" id="formFiltros">
                    <div class="filtro-group">
                        <label class="form-label-premium" for="filtroSituacao">Situação</label>
                        <select class="form-select form-select-premium" id="filtroSituacao">
                            <option value="todos">Todos</option>
                            <option value="ja_completaram" selected>Já completaram 18 anos</option>
                            <option value="este_mes">Completam este mês</option>
                            <option value="proximos_3_meses">Próximos 3 meses</option>
                            <option value="proximos_6_meses">Próximos 6 meses</option>
                        </select>
                    </div>
                    
                    <div class="filtro-group">
                        <label class="form-label-premium" for="filtroBusca">Buscar</label>
                        <input type="text" class="form-control form-control-premium" id="filtroBusca" 
                               placeholder="Nome ou RG...">
                    </div>

                    <div class="filtro-group">
                        <label class="form-label-premium" for="filtroItens">Itens por página</label>
                        <select class="form-select form-select-premium" id="filtroItens">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-premium btn-primary-premium">
                            <i class="fas fa-search me-2"></i>
                            Filtrar
                        </button>
                    </div>
                    
                    <div>
                        <button type="button" class="btn btn-premium btn-secondary-premium" id="btnLimpar">
                            <i class="fas fa-eraser me-2"></i>
                            Limpar
                        </button>
                    </div>

                    <div>
                        <button type="button" class="btn btn-premium btn-success-premium" id="btnExportar">
                            <i class="fas fa-file-export me-2"></i>
                            Exportar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tabela de Dependentes -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-list me-2"></i>
                        Lista de Dependentes
                    </h3>
                    <p class="table-subtitle" id="infoTabela">Carregando dados...</p>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-premium">
                        <thead>
                            <tr>
                                <th>Dependente</th>
                                <th>Idade</th>
                                <th>Data Nascimento</th>
                                <th>Parentesco</th>
                                <th>Responsável</th>
                                <th>Contato</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="corpoTabela">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="loading-spinner">
                                        <div class="spinner-border text-warning" role="status">
                                            <span class="visually-hidden">Carregando...</span>
                                        </div>
                                        <p class="mt-2">Carregando dependentes...</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginação -->
            <div class="pagination-container" id="containerPaginacao" style="display: none;">
                <div class="pagination-info" id="infoPaginacao">
                    Mostrando 0 de 0 registros
                </div>
                <nav>
                    <ul class="pagination" id="paginacao">
                        <!-- Será preenchido via JavaScript -->
                    </ul>
                </nav>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Detalhes (será criado dinamicamente) -->
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
    // Variáveis globais
    let todosDependendes = [];
    let dependentesFiltrados = [];
    let paginaAtual = 1;
    let itensPorPagina = 10;

    // Classe para notificações
    class ToastNotification {
        static show(message, type = 'success') {
            const toastHtml = `
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            const container = document.getElementById('toastContainer');
            container.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = container.lastElementChild;
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
            
            setTimeout(() => toastElement.remove(), 5000);
        }
    }

    // Inicialização
    document.addEventListener('DOMContentLoaded', function() {
        // Carregar dados iniciais
        carregarDependentes();
        
        // Configurar event listeners
        document.getElementById('formFiltros').addEventListener('submit', function(e) {
            e.preventDefault();
            aplicarFiltros();
        });
        
        document.getElementById('btnLimpar').addEventListener('click', limparFiltros);
        document.getElementById('btnExportar').addEventListener('click', exportarDados);
        
        document.getElementById('filtroItens').addEventListener('change', function() {
            itensPorPagina = parseInt(this.value);
            paginaAtual = 1;
            renderizarTabela();
        });
        
        // Aplicar filtro ao mudar seleção
        document.getElementById('filtroSituacao').addEventListener('change', aplicarFiltros);
        
        // Aplicar filtro ao digitar (com delay)
        let timerBusca;
        document.getElementById('filtroBusca').addEventListener('input', function() {
            clearTimeout(timerBusca);
            timerBusca = setTimeout(aplicarFiltros, 500);
        });
    });

    // Carregar dependentes via API
    async function carregarDependentes() {
        try {
            const situacao = document.getElementById('filtroSituacao').value;
            const busca = document.getElementById('filtroBusca').value;
            
            const params = new URLSearchParams({
                situacao: situacao,
                busca: busca
            });
            
            const response = await fetch(`../api/dependentes/listar_18anos.php?${params}`);
            
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                todosDependendes = data.data.dependentes || [];
                dependentesFiltrados = [...todosDependendes];
                renderizarTabela();
            } else {
                throw new Error(data.message || 'Erro ao carregar dados');
            }
            
        } catch (error) {
            console.error('Erro:', error);
            ToastNotification.show('Erro ao carregar dependentes', 'danger');
            
            // Mostrar mensagem de erro na tabela
            document.getElementById('corpoTabela').innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                        Erro ao carregar dados
                    </td>
                </tr>
            `;
        }
    }

    // Aplicar filtros
    function aplicarFiltros() {
        paginaAtual = 1;
        carregarDependentes();
    }

    // Limpar filtros
    function limparFiltros() {
        document.getElementById('filtroSituacao').value = 'todos';
        document.getElementById('filtroBusca').value = '';
        aplicarFiltros();
    }

    // Renderizar tabela com paginação
    function renderizarTabela() {
        const inicio = (paginaAtual - 1) * itensPorPagina;
        const fim = inicio + itensPorPagina;
        const dependentesPagina = dependentesFiltrados.slice(inicio, fim);
        
        const corpoTabela = document.getElementById('corpoTabela');
        
        if (dependentesPagina.length === 0) {
            corpoTabela.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        Nenhum dependente encontrado
                    </td>
                </tr>
            `;
            document.getElementById('containerPaginacao').style.display = 'none';
            document.getElementById('infoTabela').textContent = 'Nenhum registro encontrado';
            return;
        }
        
        // Renderizar linhas
        corpoTabela.innerHTML = dependentesPagina.map(dep => `
            <tr>
                <td>
                    <div class="fw-bold">${dep.nome_dependente || 'Não informado'}</div>
                    <small class="text-muted">ID: ${dep.dependente_id}</small>
                </td>
                <td>
                    <span class="badge-premium ${getBadgeClass(dep.prioridade)}">
                        ${dep.idade_atual} anos
                    </span>
                </td>
                <td>${formatarData(dep.data_nascimento)}</td>
                <td>${dep.parentesco || 'Não informado'}</td>
                <td>
                    <div class="fw-bold">${dep.nome_responsavel || 'Não informado'}</div>
                    <small class="text-muted">RG: ${dep.rg_responsavel || 'N/A'}</small>
                </td>
                <td>
                    ${dep.telefone_responsavel ? 
                        `<div><i class="fas fa-phone me-1"></i> ${formatarTelefone(dep.telefone_responsavel)}</div>` : ''}
                    ${dep.email_responsavel ? 
                        `<div><i class="fas fa-envelope me-1"></i> ${dep.email_responsavel}</div>` : ''}
                    ${!dep.telefone_responsavel && !dep.email_responsavel ? 
                        '<span class="text-muted">Sem contato</span>' : ''}
                </td>
                <td>
                    <button class="btn-action btn-contato" 
                            onclick="contatar('${dep.telefone_responsavel || ''}', '${dep.email_responsavel || ''}', '${dep.nome_dependente}')">
                        <i class="fas fa-phone"></i> Contatar
                    </button>
                    <button class="btn-action btn-detalhes" 
                            onclick='verDetalhes(${JSON.stringify(dep).replace(/'/g, "&apos;")})'>
                        <i class="fas fa-eye"></i> Ver
                    </button>
                </td>
            </tr>
        `).join('');
        
        // Atualizar informações
        document.getElementById('infoTabela').textContent = 
            `${dependentesFiltrados.length} dependente(s) encontrado(s)`;
        
        // Renderizar paginação
        renderizarPaginacao();
        
        // Atualizar info de paginação
        document.getElementById('infoPaginacao').textContent = 
            `Mostrando ${inicio + 1} até ${Math.min(fim, dependentesFiltrados.length)} de ${dependentesFiltrados.length} registros`;
        
        // Mostrar container de paginação
        document.getElementById('containerPaginacao').style.display = 
            dependentesFiltrados.length > itensPorPagina ? 'flex' : 'none';
    }

    // Renderizar paginação
    function renderizarPaginacao() {
        const totalPaginas = Math.ceil(dependentesFiltrados.length / itensPorPagina);
        const paginacao = document.getElementById('paginacao');
        
        if (totalPaginas <= 1) {
            paginacao.innerHTML = '';
            return;
        }
        
        let html = '';
        
        // Botão anterior
        html += `
            <li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="irParaPagina(${paginaAtual - 1}); return false;">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
        
        // Páginas numeradas (máximo 7 páginas visíveis)
        let inicioPag = Math.max(1, paginaAtual - 3);
        let fimPag = Math.min(totalPaginas, inicioPag + 6);
        
        if (fimPag - inicioPag < 6) {
            inicioPag = Math.max(1, fimPag - 6);
        }
        
        if (inicioPag > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="irParaPagina(1); return false;">1</a>
                </li>
            `;
            if (inicioPag > 2) {
                html += `<li class="page-item disabled"><a class="page-link">...</a></li>`;
            }
        }
        
        for (let i = inicioPag; i <= fimPag; i++) {
            html += `
                <li class="page-item ${i === paginaAtual ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="irParaPagina(${i}); return false;">${i}</a>
                </li>
            `;
        }
        
        if (fimPag < totalPaginas) {
            if (fimPag < totalPaginas - 1) {
                html += `<li class="page-item disabled"><a class="page-link">...</a></li>`;
            }
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="irParaPagina(${totalPaginas}); return false;">${totalPaginas}</a>
                </li>
            `;
        }
        
        // Botão próximo
        html += `
            <li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="irParaPagina(${paginaAtual + 1}); return false;">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
        
        paginacao.innerHTML = html;
    }

    // Ir para página
    function irParaPagina(pagina) {
        const totalPaginas = Math.ceil(dependentesFiltrados.length / itensPorPagina);
        
        if (pagina < 1 || pagina > totalPaginas) return;
        
        paginaAtual = pagina;
        renderizarTabela();
        
        // Scroll suave para o topo da tabela
        document.querySelector('.table-container').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }

    // Funções auxiliares
    function getBadgeClass(prioridade) {
        const classes = {
            'critica': 'badge-critico',
            'alta': 'badge-atencao',
            'media': 'badge-atencao',
            'baixa': 'badge-normal'
        };
        return classes[prioridade] || 'badge-normal';
    }

    function formatarData(data) {
        if (!data) return 'N/A';
        const d = new Date(data + 'T00:00:00');
        return d.toLocaleDateString('pt-BR');
    }

    function formatarTelefone(telefone) {
        if (!telefone) return '';
        const limpo = telefone.replace(/\D/g, '');
        if (limpo.length === 11) {
            return limpo.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (limpo.length === 10) {
            return limpo.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
        return telefone;
    }

    // Contatar responsável
    function contatar(telefone, email, nome) {
        if (!telefone && !email) {
            ToastNotification.show('Nenhuma informação de contato disponível', 'warning');
            return;
        }
        
        if (telefone) {
            const numeroLimpo = telefone.replace(/\D/g, '');
            const numero = numeroLimpo.length <= 11 ? '55' + numeroLimpo : numeroLimpo;
            const mensagem = encodeURIComponent(
                `Olá! Contato do sistema ASSEGO sobre o(a) dependente ${nome} que completou ou está prestes a completar 18 anos.`
            );
            window.open(`https://api.whatsapp.com/send?phone=${numero}&text=${mensagem}`, '_blank');
        } else if (email) {
            window.open(`mailto:${email}?subject=ASSEGO - Dependente ${nome} - 18 anos`, '_blank');
        }
    }

    // Ver detalhes
    function verDetalhes(dependente) {
        const modalHtml = `
            <div class="modal fade" id="modalDetalhes" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-user-circle me-2"></i>
                                Detalhes do Dependente
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-warning mb-3">Dados do Dependente</h6>
                                    <p><strong>Nome:</strong> ${dependente.nome_dependente || 'N/A'}</p>
                                    <p><strong>Idade:</strong> ${dependente.idade_atual} anos</p>
                                    <p><strong>Nascimento:</strong> ${formatarData(dependente.data_nascimento)}</p>
                                    <p><strong>Completa 18:</strong> ${formatarData(dependente.data_18_anos)}</p>
                                    <p><strong>Parentesco:</strong> ${dependente.parentesco || 'N/A'}</p>
                                    <p><strong>Status:</strong> ${dependente.status || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Dados do Responsável</h6>
                                    <p><strong>Nome:</strong> ${dependente.nome_responsavel || 'N/A'}</p>
                                    <p><strong>RG:</strong> ${dependente.rg_responsavel || 'N/A'}</p>
                                    <p><strong>Telefone:</strong> ${formatarTelefone(dependente.telefone_responsavel) || 'N/A'}</p>
                                    <p><strong>Email:</strong> ${dependente.email_responsavel || 'N/A'}</p>
                                    <p><strong>Situação:</strong> ${dependente.situacao_associado || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            ${dependente.telefone_responsavel || dependente.email_responsavel ? `
                                <button type="button" class="btn btn-success" 
                                        onclick="contatar('${dependente.telefone_responsavel || ''}', '${dependente.email_responsavel || ''}', '${dependente.nome_dependente}')">
                                    <i class="fas fa-phone me-2"></i>
                                    Contatar Responsável
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove modal anterior se existir
        const modalExistente = document.getElementById('modalDetalhes');
        if (modalExistente) {
            modalExistente.remove();
        }
        
        // Adiciona novo modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Abre modal
        const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
        modal.show();
        
        // Remove modal após fechar
        document.getElementById('modalDetalhes').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    // Exportar dados
    function exportarDados() {
        if (dependentesFiltrados.length === 0) {
            ToastNotification.show('Nenhum dado para exportar', 'warning');
            return;
        }
        
        // Criar CSV
        const headers = ['Nome', 'Idade', 'Nascimento', 'Completa 18', 'Parentesco', 'Responsável', 'RG', 'Telefone', 'Email'];
        const rows = dependentesFiltrados.map(dep => [
            dep.nome_dependente || '',
            dep.idade_atual || '',
            dep.data_nascimento || '',
            dep.data_18_anos || '',
            dep.parentesco || '',
            dep.nome_responsavel || '',
            dep.rg_responsavel || '',
            dep.telefone_responsavel || '',
            dep.email_responsavel || ''
        ]);
        
        let csv = '\uFEFF'; // UTF-8 BOM
        csv += headers.map(h => `"${h}"`).join(',') + '\n';
        csv += rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
        
        // Download
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `dependentes_18anos_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        ToastNotification.show(`${dependentesFiltrados.length} registros exportados!`, 'success');
    }
    </script>
</body>
</html>