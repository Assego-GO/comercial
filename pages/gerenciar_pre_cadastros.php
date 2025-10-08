<?php
/**
 * Página de Gerenciamento de Pré-Cadastros - VERSÃO ATUALIZADA
 * pages/gerenciar_pre_cadastros.php
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Associados.php';
require_once 'components/header.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$usuarioLogado = $auth->getUser();
$associados = new Associados();

// Busca estatísticas
$estatisticas = $associados->contarPreCadastrosPorStatus();

// Calcula pré-cadastros pendentes (aguardando + na presidência)
$preCadastrosPendentes = ($estatisticas['aguardando_documentos'] ?? 0) + 
                        ($estatisticas['enviado_presidencia'] ?? 0);

// Filtros
$filtroStatus = $_GET['status'] ?? '';
$filtroBusca = $_GET['busca'] ?? '';

$filtros = [
    'pre_cadastro' => 1,
    'limit' => 100
];

if ($filtroStatus) {
    $filtros['status_fluxo'] = $filtroStatus;
}

if ($filtroBusca) {
    $filtros['busca'] = $filtroBusca;
}

$listaPreCadastros = $associados->listar($filtros);

$page_title = 'Gerenciar Pré-Cadastros - ASSEGO';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <?php
    // Renderiza CSS do Header Component
    $header = new HeaderComponent();
    $header->renderCSS();
    ?>
    
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--gray-100);
            margin: 0;
            padding: 0;
        }

        /* Content Area */
        .content-area {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            width: 48px;
            height: 48px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stat-card.active {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin: 0;
        }

        /* Table Container */
        .table-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Status Badges */
        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 24px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-aguardando {
            background: #fff3cd;
            color: #856404;
        }

        .badge-enviado {
            background: #cce5ff;
            color: #004085;
        }

        .badge-aprovado {
            background: #d4edda;
            color: #155724;
        }

        .badge-rejeitado {
            background: #f8d7da;
            color: #721c24;
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-enviar {
            background: var(--info);
            color: var(--white);
        }

        .btn-aprovar {
            background: var(--success);
            color: var(--white);
        }

        .btn-rejeitar {
            background: var(--danger);
            color: var(--white);
        }

        .btn-detalhes {
            background: var(--gray-200);
            color: var(--dark);
        }

        /* Modal Styles */
        .modal-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            padding: 2rem;
            overflow-y: auto;
        }

        .modal-content-custom {
            background: var(--white);
            border-radius: 24px;
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .content-area {
                padding: 1rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: stretch;
            }

            .table-responsive {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <?php
    // Renderiza o Header Component
    renderHeader([
        'usuario' => $usuarioLogado,
        'isDiretor' => $auth->isDiretor(),
        'activeTab' => 'pre-cadastros',
        'preCadastrosPendentes' => $preCadastrosPendentes
    ]);
    ?>

    <!-- Content -->
    <div class="content-area">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-user-clock"></i>
                Gerenciar Pré-Cadastros
            </h1>
            <div>
                <a href="cadastroForm.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Novo Pré-Cadastro
                </a>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="stats-container">
            <div class="stat-card <?php echo $filtroStatus == '' ? 'active' : ''; ?>" onclick="filtrarPorStatus('')">
                <p class="stat-value"><?php echo array_sum((array)$estatisticas); ?></p>
                <p class="stat-label">Total de Pré-Cadastros</p>
            </div>
            
            <div class="stat-card <?php echo $filtroStatus == 'AGUARDANDO_DOCUMENTOS' ? 'active' : ''; ?>" onclick="filtrarPorStatus('AGUARDANDO_DOCUMENTOS')">
                <p class="stat-value" style="color: var(--warning);"><?php echo $estatisticas['aguardando_documentos'] ?? 0; ?></p>
                <p class="stat-label">Aguardando Documentos</p>
            </div>
            
            <div class="stat-card <?php echo $filtroStatus == 'ENVIADO_PRESIDENCIA' ? 'active' : ''; ?>" onclick="filtrarPorStatus('ENVIADO_PRESIDENCIA')">
                <p class="stat-value" style="color: var(--info);"><?php echo $estatisticas['enviado_presidencia'] ?? 0; ?></p>
                <p class="stat-label">Na Presidência</p>
            </div>
            
            <div class="stat-card <?php echo $filtroStatus == 'APROVADO' ? 'active' : ''; ?>" onclick="filtrarPorStatus('APROVADO')">
                <p class="stat-value" style="color: var(--success);"><?php echo $estatisticas['aprovado'] ?? 0; ?></p>
                <p class="stat-label">Aprovados</p>
            </div>
            
            <div class="stat-card <?php echo $filtroStatus == 'REJEITADO' ? 'active' : ''; ?>" onclick="filtrarPorStatus('REJEITADO')">
                <p class="stat-value" style="color: var(--danger);"><?php echo $estatisticas['rejeitado'] ?? 0; ?></p>
                <p class="stat-label">Rejeitados</p>
            </div>
        </div>

        <!-- Tabela -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="h5 mb-0">Lista de Pré-Cadastros</h2>
                
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" placeholder="Buscar por nome, CPF..." 
                           value="<?php echo htmlspecialchars($filtroBusca); ?>" 
                           onkeyup="if(event.key === 'Enter') buscar(this.value)"
                           style="min-width: 250px;">
                           
                    <?php if ($filtroBusca || $filtroStatus): ?>
                    <button class="btn btn-secondary btn-sm" onclick="limparFiltros()">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Corporação</th>
                            <th>Data Pré-Cadastro</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($listaPreCadastros)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>Nenhum pré-cadastro encontrado</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($listaPreCadastros as $associado): ?>
                            <tr>
                                <td><?php echo $associado['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($associado['nome']); ?></strong>
                                    <?php if ($associado['foto']): ?>
                                        <i class="fas fa-camera text-success ms-1" title="Tem foto"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatarCPF($associado['cpf']); ?></td>
                                <td><?php echo $associado['corporacao'] ?? '-'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($associado['data_pre_cadastro'])); ?></td>
                                <td>
                                    <?php
                                    $status = $associado['status_pre_cadastro'] ?? 'AGUARDANDO_DOCUMENTOS';
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    switch($status) {
                                        case 'AGUARDANDO_DOCUMENTOS':
                                            $statusClass = 'badge-aguardando';
                                            $statusText = 'Aguardando';
                                            break;
                                        case 'ENVIADO_PRESIDENCIA':
                                            $statusClass = 'badge-enviado';
                                            $statusText = 'Na Presidência';
                                            break;
                                        case 'APROVADO':
                                            $statusClass = 'badge-aprovado';
                                            $statusText = 'Aprovado';
                                            break;
                                        case 'REJEITADO':
                                            $statusClass = 'badge-rejeitado';
                                            $statusText = 'Rejeitado';
                                            break;
                                    }
                                    ?>
                                    <span class="badge-status <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn-action btn-detalhes" onclick="verDetalhes(<?php echo $associado['id']; ?>)"
                                                title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($status == 'AGUARDANDO_DOCUMENTOS'): ?>
                                        <button class="btn-action btn-enviar" onclick="enviarPresidencia(<?php echo $associado['id']; ?>)"
                                                title="Enviar para presidência">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($status == 'ENVIADO_PRESIDENCIA'): ?>
                                        <button class="btn-action btn-aprovar" onclick="aprovarPreCadastro(<?php echo $associado['id']; ?>)"
                                                title="Aprovar cadastro">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn-action btn-rejeitar" onclick="rejeitarPreCadastro(<?php echo $associado['id']; ?>)"
                                                title="Rejeitar cadastro">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Aprovação -->
    <div class="modal-custom" id="modalAprovar">
        <div class="modal-content-custom">
            <h3 class="mb-3">Aprovar Pré-Cadastro</h3>
            <form id="formAprovar">
                <input type="hidden" id="aprovar_associado_id">
                
                <div class="mb-3">
                    <label class="form-label">Documento Assinado pela Presidência (Opcional)</label>
                    <input type="file" class="form-control" id="documento_assinado" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Observações</label>
                    <textarea class="form-control" id="aprovar_observacoes" rows="3"></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirmar Aprovação
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalAprovar')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Rejeição -->
    <div class="modal-custom" id="modalRejeitar">
        <div class="modal-content-custom">
            <h3 class="mb-3">Rejeitar Pré-Cadastro</h3>
            <form id="formRejeitar">
                <input type="hidden" id="rejeitar_associado_id">
                
                <div class="mb-3">
                    <label class="form-label">Motivo da Rejeição <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejeitar_motivo" rows="4" required></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Confirmar Rejeição
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModal('modalRejeitar')">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php
    // Renderiza JS do Header Component
    $header->renderJS();
    ?>
    
    <script>
    function filtrarPorStatus(status) {
        window.location.href = `?status=${status}`;
    }
    
    function buscar(termo) {
        const params = new URLSearchParams(window.location.search);
        params.set('busca', termo);
        window.location.href = `?${params.toString()}`;
    }
    
    function limparFiltros() {
        window.location.href = 'gerenciar_pre_cadastros.php';
    }
    
    function verDetalhes(id) {
        window.location.href = `visualizar_associado.php?id=${id}`;
    }
    
    function enviarPresidencia(id) {
        Swal.fire({
            title: 'Enviar para Presidência?',
            text: 'Confirma o envio deste pré-cadastro para aprovação da presidência?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, enviar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#00B8D4',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../api/enviar_pre_cadastro_presidencia.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        associado_id: id,
                        observacoes: 'Enviado para aprovação'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Enviado!', data.message, 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Erro!', 'Erro ao processar requisição', 'error');
                });
            }
        });
    }
    
    function aprovarPreCadastro(id) {
        document.getElementById('aprovar_associado_id').value = id;
        document.getElementById('modalAprovar').style.display = 'block';
    }
    
    function rejeitarPreCadastro(id) {
        document.getElementById('rejeitar_associado_id').value = id;
        document.getElementById('modalRejeitar').style.display = 'block';
    }
    
    function fecharModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Form de aprovação
    document.getElementById('formAprovar').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('associado_id', document.getElementById('aprovar_associado_id').value);
        formData.append('observacoes', document.getElementById('aprovar_observacoes').value);
        
        const arquivo = document.getElementById('documento_assinado').files[0];
        if (arquivo) {
            formData.append('documento_assinado', arquivo);
        }
        
        fetch('../api/aprovar_pre_cadastro.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire('Aprovado!', data.message, 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Erro!', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erro!', 'Erro ao processar requisição', 'error');
        });
    });
    
    // Form de rejeição
    document.getElementById('formRejeitar').addEventListener('submit', function(e) {
        e.preventDefault();
        
        fetch('../api/rejeitar_pre_cadastro.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                associado_id: document.getElementById('rejeitar_associado_id').value,
                motivo: document.getElementById('rejeitar_motivo').value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire('Rejeitado!', data.message, 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Erro!', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Erro!', 'Erro ao processar requisição', 'error');
        });
    });
    
    // Fechar modal ao clicar fora
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-custom')) {
            event.target.style.display = 'none';
        }
    }
    
    // Funções do header
    function toggleSearch() {
        // Implementar busca global se necessário
        console.log('Busca global');
    }
    
    function toggleNotifications() {
        // Implementar notificações se necessário
        console.log('Notificações');
    }
    </script>
</body>
</html>

<?php
// Função auxiliar para formatar CPF
function formatarCPF($cpf) {
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}
?>