<?php
/**
 * Página: Gerenciamento Completo de Cadastros Online
 * Local: /matheus/comercial/pages/cadastros_online_importar.php
 * 
 * 3 ABAS:
 * 1. PENDENTES - Cadastros do site para importar
 * 2. IMPORTADOS - Cadastros do site já importados
 * 3. PRÉ-CADASTROS - Cadastros já no sistema (lógica antiga)
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once './components/header.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ../pages/index.php');
    exit;
}

$usuarioLogado = $auth->getUser();
$page_title = 'Gerenciar Cadastros Online';

// BUSCAR PRÉ-CADASTROS DO SISTEMA (lógica antiga)
$preCadastros = [];
try {
    $db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();
    
    $sql = "
        SELECT 
            a.id,
            a.nome,
            a.cpf,
            a.telefone,
            a.email,
            a.data_pre_cadastro,
            a.situacao,
            a.pre_cadastro,
            m.corporacao,
            m.patente,
            m.lotacao,
            fpc.status as status_fluxo,
            fpc.data_envio_presidencia
        FROM Associados a
        LEFT JOIN Militar m ON a.id = m.associado_id
        LEFT JOIN Fluxo_Pre_Cadastro fpc ON a.id = fpc.associado_id
        WHERE a.pre_cadastro = 1
        ORDER BY a.data_pre_cadastro DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $preCadastros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro ao buscar pré-cadastros: " . $e->getMessage());
}

$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'comercial',
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <?php $headerComponent->renderCSS(); ?>
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f5f5f5; }
        .container-fluid { padding: 2rem; }
        .page-header { background: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .page-header h2 { margin: 0; color: #0056d2; }
        
        /* Abas */
        .nav-tabs-custom { border-bottom: 2px solid #e9ecef; margin-bottom: 2rem; }
        .nav-tabs-custom button { 
            padding: 1rem 2rem; border: none; background: transparent; 
            color: #6c757d; font-weight: 600; cursor: pointer; 
            border-bottom: 3px solid transparent; transition: all 0.3s;
        }
        .nav-tabs-custom button:hover { color: #0056d2; background: rgba(0,86,210,0.05); }
        .nav-tabs-custom button.active { color: #0056d2; border-bottom-color: #0056d2; background: rgba(0,86,210,0.08); }
        .tab-badge { background: #0056d2; color: white; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; margin-left: 0.5rem; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        /* Cards */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .stat-card h3 { font-size: 2rem; font-weight: 700; margin: 0; }
        .stat-card p { margin: 0; color: #6c757d; }
        .stat-card.primary h3 { color: #0056d2; }
        .stat-card.warning h3 { color: #ffc107; }
        .stat-card.success h3 { color: #28a745; }
        .stat-card.info h3 { color: #17a2b8; }
        
        /* Tabela */
        .card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: none; }
        .table th { background: #f8f9fa; font-weight: 600; }
        .table tbody tr:hover { background: rgba(0,86,210,0.05); }
        
        /* Badges */
        .badge-pm { background: #fee2e2; color: #991b1b; padding: 0.4rem 0.8rem; border-radius: 20px; display: inline-block; }
        .badge-bm { background: #fef3c7; color: #92400e; padding: 0.4rem 0.8rem; border-radius: 20px; display: inline-block; }
        .badge-patente { background: #e0e7ff; color: #3730a3; padding: 0.4rem 0.8rem; border-radius: 20px; display: inline-block; }
        
        /* Botões */
        .btn-import { background: #28a745; color: white; border: none; }
        .btn-import:hover { background: #218838; color: white; }
        .btn-view { background: #17a2b8; color: white; border: none; }
        .btn-view:hover { background: #138496; color: white; }
        .btn-complete { background: #ffc107; color: #000; border: none; }
        .btn-complete:hover { background: #e0a800; color: #000; }
    </style>
</head>
<body>
    <?php $headerComponent->render(); ?>
    
    <div class="container-fluid">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-laptop"></i> Gerenciar Cadastros Online</h2>
                    <p class="text-muted mb-0">Importar cadastros do site e gerenciar pré-cadastros</p>
                </div>
                <div>
                    <a href="comercial.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button class="btn btn-primary" onclick="recarregarTudo()">
                        <i class="fas fa-sync-alt"></i> Atualizar Tudo
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Gerais -->
        <div class="stats-row">
            <div class="stat-card primary">
                <h3 id="statTotal">0</h3>
                <p>Total Geral</p>
            </div>
            <div class="stat-card warning">
                <h3 id="statPendentes">0</h3>
                <p>Pendentes Importação</p>
            </div>
            <div class="stat-card success">
                <h3 id="statImportados">0</h3>
                <p>Já Importados</p>
            </div>
            <div class="stat-card info">
                <h3 id="statPreCadastros"><?php echo count($preCadastros); ?></h3>
                <p>Pré-cadastros Sistema</p>
            </div>
        </div>

        <!-- Abas -->
        <div class="nav-tabs-custom">
            <button class="active" onclick="trocarAba('pendentes')" id="tab-pendentes">
                <i class="fas fa-clock"></i> Pendentes de Importação
                <span class="tab-badge" id="badge-pendentes">0</span>
            </button>
            <button onclick="trocarAba('importados')" id="tab-importados">
                <i class="fas fa-check-circle"></i> Já Importados
                <span class="tab-badge" id="badge-importados">0</span>
            </button>
            <button onclick="trocarAba('precadastros')" id="tab-precadastros">
                <i class="fas fa-edit"></i> Pré-cadastros Sistema
                <span class="tab-badge"><?php echo count($preCadastros); ?></span>
            </button>
        </div>

        <!-- ABA 1: PENDENTES -->
        <div class="tab-content active" id="content-pendentes">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Pendentes:</strong> Cadastros do site público aguardando importação para o sistema.
            </div>
            
            <div class="card">
                <div class="card-body">
                    <table id="tablePendentes" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Corporação</th>
                                <th>Patente</th>
                                <th>Telefone</th>
                                <th>Data Cadastro</th>
                                <th>Aguardando</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyPendentes">
                            <tr><td colspan="9" class="text-center"><div class="spinner-border"></div><p>Carregando...</p></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ABA 2: IMPORTADOS -->
        <div class="tab-content" id="content-importados">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Importados:</strong> Cadastros que já foram trazidos do site para o sistema.
            </div>
            
            <div class="card">
                <div class="card-body">
                    <table id="tableImportados" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Corporação</th>
                                <th>Patente</th>
                                <th>Data Cadastro</th>
                                <th>Data Importação</th>
                                <th>Importado Por</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyImportados">
                            <tr><td colspan="9" class="text-center"><div class="spinner-border"></div><p>Carregando...</p></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ABA 3: PRÉ-CADASTROS -->
        <div class="tab-content" id="content-precadastros">
            <div class="alert alert-warning">
                <i class="fas fa-edit"></i>
                <strong>Pré-cadastros:</strong> Cadastros já no sistema aguardando conclusão.
            </div>
            
            <div class="card">
                <div class="card-body">
                    <?php if (count($preCadastros) > 0): ?>
                    <table id="tablePreCadastros" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Corporação</th>
                                <th>Patente</th>
                                <th>Telefone</th>
                                <th>Data Pré-cadastro</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preCadastros as $pre): ?>
                            <tr>
                                <td><strong>#<?php echo $pre['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($pre['nome']); ?></td>
                                <td><code><?php echo htmlspecialchars($pre['cpf']); ?></code></td>
                                <td>
                                    <?php if ($pre['corporacao']): ?>
                                        <span class="badge-<?php echo strtolower($pre['corporacao']); ?>">
                                            <?php echo $pre['corporacao']; ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($pre['patente']): ?>
                                        <span class="badge-patente"><?php echo $pre['patente']; ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $pre['telefone'] ?: '-'; ?></td>
                                <td>
                                    <?php 
                                    if ($pre['data_pre_cadastro']) {
                                        echo date('d/m/Y H:i', strtotime($pre['data_pre_cadastro']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $pre['status_fluxo'] ?? 'AGUARDANDO';
                                    $badges = [
                                        'AGUARDANDO_DOCUMENTOS' => '<span class="badge bg-warning">Aguardando</span>',
                                        'ENVIADO_PRESIDENCIA' => '<span class="badge bg-info">Enviado</span>',
                                        'APROVADO' => '<span class="badge bg-success">Aprovado</span>',
                                        'REJEITADO' => '<span class="badge bg-danger">Rejeitado</span>',
                                    ];
                                    echo $badges[$status] ?? '<span class="badge bg-secondary">Pendente</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="cadastroForm.php?id=<?php echo $pre['id']; ?>" 
                                       class="btn btn-sm btn-complete" 
                                       target="_blank">
                                        <i class="fas fa-edit"></i> Completar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5>Nenhum pré-cadastro encontrado</h5>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <?php $headerComponent->renderJS(); ?>

    <script>
        let tables = {};
        let dadosOnline = [];

        $(document).ready(function() {
            carregarDadosOnline();
            
            // Inicializar tabela de pré-cadastros
            <?php if (count($preCadastros) > 0): ?>
            tables.preCadastros = $('#tablePreCadastros').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[0, 'desc']],
                pageLength: 25
            });
            <?php endif; ?>
        });

        function trocarAba(aba) {
            // Atualizar botões
            document.querySelectorAll('.nav-tabs-custom button').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + aba).classList.add('active');
            
            // Atualizar conteúdo
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('content-' + aba).classList.add('active');
        }

        function carregarDadosOnline() {
            $.ajax({
                url: '../api/proxy_cadastros_online.php',
                method: 'GET',
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        dadosOnline = resp.data.cadastros;
                        atualizarStats(resp.data.estatisticas);
                        renderizarPendentes();
                        renderizarImportados();
                    }
                },
                error: function() {
                    alert('Erro ao carregar dados do site');
                }
            });
        }

        function atualizarStats(stats) {
            $('#statTotal').text(stats.total_geral);
            $('#statPendentes').text(stats.pendentes);
            $('#statImportados').text(stats.importados);
            $('#badge-pendentes').text(stats.pendentes);
            $('#badge-importados').text(stats.importados);
        }

        function renderizarPendentes() {
            const tbody = $('#tbodyPendentes');
            tbody.empty();

            const pendentes = dadosOnline.filter(c => c.importado == 0);

            if (pendentes.length === 0) {
                tbody.html('<tr><td colspan="9" class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><h5>Nenhum cadastro pendente</h5></td></tr>');
                return;
            }

            pendentes.forEach(cad => {
                const row = `
                    <tr>
                        <td><strong>#${cad.id}</strong></td>
                        <td>${cad.nome}</td>
                        <td><code>${cad.cpf_formatado || cad.cpf}</code></td>
                        <td>${cad.corporacao ? '<span class="badge-' + cad.corporacao.toLowerCase() + '">' + cad.corporacao + '</span>' : '-'}</td>
                        <td>${cad.patente ? '<span class="badge-patente">' + cad.patente + '</span>' : '-'}</td>
                        <td>${cad.telefone || '-'}</td>
                        <td>${new Date(cad.data_cadastro).toLocaleString('pt-BR')}</td>
                        <td><span class="badge bg-warning">${cad.dias_aguardando} dias</span></td>
                        <td>
                            <button class="btn btn-sm btn-import" onclick="importar(${cad.id}, '${cad.nome.replace(/'/g, "\\'")}')">
                                <i class="fas fa-download"></i> Importar
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });

            if (tables.pendentes) tables.pendentes.destroy();
            tables.pendentes = $('#tablePendentes').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[0, 'desc']],
                pageLength: 25
            });
        }

        function renderizarImportados() {
            const tbody = $('#tbodyImportados');
            tbody.empty();

            const importados = dadosOnline.filter(c => c.importado == 1);

            if (importados.length === 0) {
                tbody.html('<tr><td colspan="9" class="text-center py-5"><i class="fas fa-inbox fa-3x text-muted mb-3"></i><h5>Nenhum cadastro importado ainda</h5></td></tr>');
                return;
            }

            importados.forEach(cad => {
                const row = `
                    <tr>
                        <td><strong>#${cad.id}</strong></td>
                        <td>${cad.nome}</td>
                        <td><code>${cad.cpf_formatado || cad.cpf}</code></td>
                        <td>${cad.corporacao ? '<span class="badge-' + cad.corporacao.toLowerCase() + '">' + cad.corporacao + '</span>' : '-'}</td>
                        <td>${cad.patente ? '<span class="badge-patente">' + cad.patente + '</span>' : '-'}</td>
                        <td>${new Date(cad.data_cadastro).toLocaleString('pt-BR')}</td>
                        <td>${cad.data_importacao ? new Date(cad.data_importacao).toLocaleString('pt-BR') : '-'}</td>
                        <td>${cad.importado_por || '-'}</td>
                        <td>
                            ${cad.observacao_importacao && cad.observacao_importacao.includes('ID:') ? 
                                '<button class="btn btn-sm btn-view" onclick="verCadastro(\'' + cad.observacao_importacao + '\')"><i class="fas fa-eye"></i> Ver</button>' : 
                                '<span class="text-muted">-</span>'
                            }
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });

            if (tables.importados) tables.importados.destroy();
            tables.importados = $('#tableImportados').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json' },
                order: [[6, 'desc']],
                pageLength: 25
            });
        }

        function importar(id, nome) {
            if (!confirm(`Importar cadastro de ${nome}?`)) return;

            $.ajax({
                url: '../api/cadastros_online_importar.php',
                method: 'POST',
                data: JSON.stringify({ id: id }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        alert(`✅ Importado!\nID: ${resp.associado_id}\nProtocolo: ${resp.info.protocolo}`);
                        window.open(`cadastroForm.php?id=${resp.associado_id}`, '_blank');
                        recarregarTudo();
                    } else {
                        alert('Erro: ' + resp.message);
                    }
                },
                error: function() {
                    alert('Erro ao importar');
                }
            });
        }

        function verCadastro(obs) {
            const match = obs.match(/ID:\s*(\d+)/i);
            if (match) {
                window.open(`cadastroForm.php?id=${match[1]}`, '_blank');
            } else {
                alert('ID não encontrado');
            }
        }

        function recarregarTudo() {
            location.reload();
        }
    </script>
</body>
</html>