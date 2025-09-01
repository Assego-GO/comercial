<?php
/**
 * Página de Gerenciamento de Permissões - Sistema ASSEGO
 * pages/permissoes.php
 */

// Tratamento de erros para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Permissoes.php';
require_once '../classes/PermissoesManager.php';
require_once '../classes/Funcionarios.php';
require_once './components/header.php';

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
$page_title = 'Gerenciamento de Permissões - ASSEGO';

// Inicializa classes
$permissoesManager = PermissoesManager::getInstance();
$funcionariosClass = new Funcionarios();
$db = Database::getInstance(DB_NAME_CADASTRO)->getConnection();

// Busca dados completos do usuário
$dadosUsuarioCompleto = $funcionariosClass->getById($usuarioLogado['id']);
$usuarioLogado['departamento_id'] = $dadosUsuarioCompleto['departamento_id'] ?? null;
$usuarioLogado['cargo'] = $dadosUsuarioCompleto['cargo'] ?? null;

// Verificação de permissões
$ehPresidencia = ($usuarioLogado['departamento_id'] == 1) || 
                 in_array($usuarioLogado['cargo'], ['Presidente', 'Vice-Presidente']);
$isDiretor = $permissoesManager->isDiretorDepartamento($usuarioLogado['id']);
$podeGerenciarPermissoes = $ehPresidencia || $isDiretor;

if (!$podeGerenciarPermissoes) {
    $_SESSION['erro'] = 'Você não tem permissão para acessar esta página.';
    header('Location: dashboard.php');
    exit;
}

// Busca dados do sistema
try {
    // Estatísticas gerais
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Funcionarios WHERE ativo = 1");
    $stmt->execute();
    $totalUsuarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Departamentos WHERE ativo = 1");
    $stmt->execute();
    $totalDepartamentos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Delegações ativas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM Permissoes_Delegadas 
        WHERE ativa = 1 
        AND (data_fim IS NULL OR data_fim > NOW())
    ");
    $stmt->execute();
    $totalDelegacoes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Grupos de permissões
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM Grupos_Permissoes WHERE ativo = 1");
    $stmt->execute();
    $totalGrupos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Lista de departamentos
    $stmt = $db->prepare("SELECT id, nome FROM Departamentos WHERE ativo = 1 ORDER BY nome");
    $stmt->execute();
    $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lista de funcionários (baseado no nível de acesso)
    if ($ehPresidencia) {
        $stmt = $db->prepare("
            SELECT f.*, d.nome as departamento_nome 
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.ativo = 1
            ORDER BY f.nome
        ");
    } else {
        $stmt = $db->prepare("
            SELECT f.*, d.nome as departamento_nome 
            FROM Funcionarios f
            LEFT JOIN Departamentos d ON f.departamento_id = d.id
            WHERE f.ativo = 1 AND f.departamento_id = ?
            ORDER BY f.nome
        ");
        $stmt->execute([$usuarioLogado['departamento_id']]);
    }
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Permissões disponíveis
    $permissoesDisponiveis = Permissoes::listarPorCategoria();

} catch (Exception $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    $totalUsuarios = 0;
    $totalDepartamentos = 0;
    $totalDelegacoes = 0;
    $totalGrupos = 0;
    $departamentos = [];
    $funcionarios = [];
    $permissoesDisponiveis = [];
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'permissoes',
    'notificationCount' => $totalDelegacoes,
    'showSearch' => false
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

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>

    <style>
        /* Variáveis CSS - Seguindo padrão do sistema */
        :root {
            --primary: #0056d2;
            --primary-light: #4A90E2;
            --primary-dark: #1e3d6f;
            --secondary: #6c757d;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--light);
            color: var(--dark);
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .content-area {
            flex: 1;
            padding: 1.5rem;
            margin-left: 0;
            transition: margin-left 0.3s ease;
        }

        /* Page Header - Padrão do sistema */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(44, 90, 160, 0.08);
            border-left: 4px solid var(--primary);
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .page-title-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .page-title-icon i {
            color: white;
            font-size: 1.5rem;
        }

        .page-subtitle {
            color: var(--secondary);
            margin: 0.5rem 0 0;
            font-size: 0.95rem;
        }

        /* Stats Grid - Padrão do sistema */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.12);
        }

        .stat-card.primary { border-left-color: var(--primary); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            opacity: 0.1;
        }

        .stat-icon.primary { background: var(--primary); color: var(--primary); }
        .stat-icon.success { background: var(--success); color: var(--success); }
        .stat-icon.warning { background: var(--warning); color: var(--warning); }
        .stat-icon.info { background: var(--info); color: var(--info); }

        /* Service Section - Padrão do sistema */
        .service-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(40, 167, 69, 0.08);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .service-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 1.5rem;
        }

        .service-header h3 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }

        .service-header i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }

        .service-content {
            padding: 1.5rem;
        }

        /* Tabs customizadas */
        .nav-tabs-custom {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }

        .nav-tabs-custom .nav-link {
            color: var(--secondary);
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .nav-tabs-custom .nav-link:hover {
            color: var(--primary);
            background: rgba(0, 86, 210, 0.05);
        }

        .nav-tabs-custom .nav-link.active {
            color: var(--primary);
            background: transparent;
            border-bottom-color: var(--primary);
        }

        /* Permissões Grid */
        .permissoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .permissao-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .permissao-item:hover {
            border-color: var(--primary);
            background: white;
        }

        .permissao-categoria {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        /* Tabela customizada */
        .table-custom {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-custom thead {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .table-custom th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Badges customizados */
        .badge-custom {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Botões de ação */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.85rem;
            border: none;
            transition: all 0.3s ease;
        }

        /* Modal */
        .modal-header-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        /* Filtros */
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .permissoes-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Page Header -->
            <div class="page-header" data-aos="fade-right">
                <h1 class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    Gerenciamento de Permissões
                    <?php if ($ehPresidencia): ?>
                        <small class="text-muted ms-2">- Presidência</small>
                    <?php elseif ($isDiretor): ?>
                        <small class="text-muted ms-2">- Diretor</small>
                    <?php endif; ?>
                </h1>
                <p class="page-subtitle">
                    Configure permissões por departamento, cargo e usuário
                </p>
            </div>

            <!-- Estatísticas -->
            <div class="stats-grid" data-aos="fade-up">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalUsuarios; ?></div>
                            <div class="stat-label">Usuários Ativos</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalDepartamentos; ?></div>
                            <div class="stat-label">Departamentos</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalDelegacoes; ?></div>
                            <div class="stat-label">Delegações Ativas</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card info">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $totalGrupos; ?></div>
                            <div class="stat-label">Grupos</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#departamentos">
                        <i class="fas fa-building me-2"></i>Por Departamento
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#usuarios">
                        <i class="fas fa-user me-2"></i>Por Usuário
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#delegacoes">
                        <i class="fas fa-exchange-alt me-2"></i>Delegações
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#grupos">
                        <i class="fas fa-layer-group me-2"></i>Grupos
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Tab: Departamentos -->
                <div class="tab-pane fade show active" id="departamentos">
                    <div class="service-section">
                        <div class="service-header">
                            <h3><i class="fas fa-building"></i> Permissões por Departamento</h3>
                        </div>
                        <div class="service-content">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Selecione o Departamento</label>
                                    <select class="form-select" id="selectDepartamento">
                                        <option value="">Escolha um departamento...</option>
                                        <?php foreach ($departamentos as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>">
                                                <?php echo htmlspecialchars($dept['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button class="btn btn-primary" onclick="salvarPermissoesDepartamento()">
                                        <i class="fas fa-save me-2"></i>Salvar Alterações
                                    </button>
                                </div>
                            </div>

                            <div id="permissoesDepartamento">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-arrow-up fa-3x mb-3 opacity-50"></i>
                                    <p>Selecione um departamento para configurar suas permissões</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Usuários -->
                <div class="tab-pane fade" id="usuarios">
                    <div class="service-section">
                        <div class="service-header">
                            <h3><i class="fas fa-users"></i> Permissões por Usuário</h3>
                        </div>
                        <div class="service-content">
                            <div class="filter-section mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" placeholder="Buscar usuário..." id="searchUsuario">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" id="filterDepartamento">
                                            <option value="">Todos os departamentos</option>
                                            <?php foreach ($departamentos as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>">
                                                    <?php echo htmlspecialchars($dept['nome']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-primary" onclick="abrirModalPermissaoUsuario()">
                                            <i class="fas fa-plus me-2"></i>Adicionar Permissão
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Cargo</th>
                                            <th>Departamento</th>
                                            <th>Permissões</th>
                                            <th width="150">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($funcionarios as $func): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($func['nome']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($func['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($func['cargo'] ?: 'N/A'); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($func['departamento_nome'] ?: 'N/A'); ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $permissoes = $permissoesManager->getPermissoesEfetivas($func['id']);
                                                $count = count($permissoes);
                                                ?>
                                                <span class="badge bg-info"><?php echo $count; ?> permissões</span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="verPermissoes(<?php echo $func['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" 
                                                            onclick="editarPermissoes(<?php echo $func['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Delegações -->
                <div class="tab-pane fade" id="delegacoes">
                    <div class="service-section">
                        <div class="service-header">
                            <h3><i class="fas fa-exchange-alt"></i> Delegações de Permissões</h3>
                        </div>
                        <div class="service-content">
                            <div class="mb-3">
                                <button class="btn btn-primary" onclick="abrirModalDelegacao()">
                                    <i class="fas fa-plus me-2"></i>Nova Delegação
                                </button>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Delegações permitem que diretores concedam temporariamente suas permissões a outros funcionários.
                            </div>

                            <div id="listaDelegacoes">
                                <!-- Carregado via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Grupos -->
                <div class="tab-pane fade" id="grupos">
                    <div class="service-section">
                        <div class="service-header">
                            <h3><i class="fas fa-layer-group"></i> Grupos de Permissões</h3>
                        </div>
                        <div class="service-content">
                            <div class="mb-3">
                                <button class="btn btn-primary" onclick="abrirModalGrupo()">
                                    <i class="fas fa-plus me-2"></i>Novo Grupo
                                </button>
                            </div>

                            <div id="listaGrupos">
                                <!-- Carregado via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Delegação -->
    <div class="modal fade" id="modalDelegacao" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title">Nova Delegação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formDelegacao">
                        <div class="mb-3">
                            <label class="form-label">Funcionário</label>
                            <select class="form-select" id="funcionarioDelegacao" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($funcionarios as $func): ?>
                                    <?php if ($func['id'] != $usuarioLogado['id']): ?>
                                    <option value="<?php echo $func['id']; ?>">
                                        <?php echo htmlspecialchars($func['nome']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permissão</label>
                            <select class="form-select" id="permissaoDelegacao" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($permissoesDisponiveis as $categoria => $perms): ?>
                                    <optgroup label="<?php echo htmlspecialchars($categoria); ?>">
                                        <?php foreach ($perms as $key => $desc): ?>
                                            <option value="<?php echo $key; ?>">
                                                <?php echo htmlspecialchars($desc); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Data de Expiração</label>
                            <input type="date" class="form-control" id="dataFimDelegacao" 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea class="form-control" id="motivoDelegacao" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarDelegacao()">Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <script>
        // Inicialização
        AOS.init({
            duration: 800,
            once: true
        });

        $(document).ready(function() {
            // Inicializa Select2
            $('.form-select').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });

            // Carrega dados iniciais
            carregarDelegacoes();
            carregarGrupos();

            // Event listeners
            $('#selectDepartamento').on('change', carregarPermissoesDepartamento);
            $('#searchUsuario').on('keyup', filtrarUsuarios);
            $('#filterDepartamento').on('change', filtrarUsuarios);
        });

        // Carregar permissões do departamento
        function carregarPermissoesDepartamento() {
            const deptId = $('#selectDepartamento').val();
            if (!deptId) return;

            $.get(`../api/permissoes/departamento.php?id=${deptId}`, function(data) {
                exibirPermissoesDepartamento(data);
            });
        }

        // Exibir permissões do departamento
        function exibirPermissoesDepartamento(permissoes) {
            let html = '<div class="permissoes-grid">';
            
            <?php foreach ($permissoesDisponiveis as $categoria => $perms): ?>
            html += `
                <div class="permissao-item">
                    <div class="permissao-categoria">
                        <i class="fas fa-folder me-2"></i><?php echo $categoria; ?>
                    </div>
                    <?php foreach ($perms as $key => $desc): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" 
                               value="<?php echo $key; ?>" 
                               id="perm_<?php echo $key; ?>"
                               ${permissoes.includes('<?php echo $key; ?>') ? 'checked' : ''}>
                        <label class="form-check-label" for="perm_<?php echo $key; ?>">
                            <?php echo htmlspecialchars($desc); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            `;
            <?php endforeach; ?>
            
            html += '</div>';
            $('#permissoesDepartamento').html(html);
        }

        // Salvar permissões do departamento
        function salvarPermissoesDepartamento() {
            const deptId = $('#selectDepartamento').val();
            if (!deptId) {
                alert('Selecione um departamento');
                return;
            }

            const permissoes = [];
            $('#permissoesDepartamento input:checked').each(function() {
                permissoes.push($(this).val());
            });

            $.post('../api/permissoes/departamento_salvar.php', {
                departamento_id: deptId,
                permissoes: permissoes
            }, function(response) {
                if (response.success) {
                    alert('Permissões salvas com sucesso!');
                }
            });
        }

        // Carregar delegações
        function carregarDelegacoes() {
            $.get('../api/permissoes/delegacoes_listar.php', function(data) {
                let html = '<div class="table-responsive"><table class="table">';
                html += '<thead><tr><th>De</th><th>Para</th><th>Permissão</th><th>Expira</th><th>Ações</th></tr></thead><tbody>';
                
                data.forEach(del => {
                    html += `
                        <tr>
                            <td>${del.origem_nome}</td>
                            <td>${del.destino_nome}</td>
                            <td>${del.permissao}</td>
                            <td>${del.data_fim || 'Sem prazo'}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="revogarDelegacao(${del.id})">
                                    Revogar
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                $('#listaDelegacoes').html(html);
            });
        }

        // Carregar grupos
        function carregarGrupos() {
            $.get('../api/permissoes/grupos_listar.php', function(data) {
                let html = '<div class="row">';
                
                data.forEach(grupo => {
                    html += `
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6>${grupo.nome}</h6>
                                    <p class="text-muted small">${grupo.descricao}</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editarGrupo(${grupo.id})">
                                        Editar
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                $('#listaGrupos').html(html);
            });
        }

        // Abrir modais
        function abrirModalDelegacao() {
            $('#modalDelegacao').modal('show');
        }

        // Salvar delegação
        function salvarDelegacao() {
            const dados = {
                funcionario_destino: $('#funcionarioDelegacao').val(),
                permissao: $('#permissaoDelegacao').val(),
                data_fim: $('#dataFimDelegacao').val(),
                motivo: $('#motivoDelegacao').val()
            };

            $.post('../api/permissoes/delegar.php', dados, function(response) {
                if (response.success) {
                    $('#modalDelegacao').modal('hide');
                    carregarDelegacoes();
                    alert('Delegação criada com sucesso!');
                }
            });
        }

        // Filtrar usuários
        function filtrarUsuarios() {
            const search = $('#searchUsuario').val().toLowerCase();
            const dept = $('#filterDepartamento').val();
            
            $('#usuarios tbody tr').each(function() {
                const nome = $(this).find('td:first').text().toLowerCase();
                const deptId = $(this).data('dept');
                
                const matchSearch = nome.includes(search);
                const matchDept = !dept || deptId == dept;
                
                $(this).toggle(matchSearch && matchDept);
            });
        }
    </script>
</body>
</html>