<!DOCTYPE html>
<?php
// pages/gerenciar-permissoes.php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';
require_once '../classes/Permissoes.php';

$auth = new Auth();
$auth->checkPermissao('sistema.impersonar');

$funcionarios = new Funcionarios();
$usuario_atual = $auth->getUsuarioAtual();

// Processar ações
$mensagem = '';
$erro = '';

// Processar impersonation
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'impersonate' && isset($_POST['usuario_id'])) {
        $resultado = $auth->impersonar($_POST['usuario_id']);
        if ($resultado['success']) {
            header('Location: ' . BASE_URL . 'pages/dashboard.php');
            exit;
        } else {
            $erro = $resultado['message'];
        }
    }
}

// Parar impersonation
if (isset($_GET['stop_impersonate'])) {
    $auth->pararImpersonation();
    header('Location: ' . BASE_URL . 'pages/gerenciar-permissoes.php');
    exit;
}

// Buscar funcionários
$lista_funcionarios = $funcionarios->listar(['ativo' => 1]);

// Se solicitado, mostrar permissões de um usuário específico
$usuario_selecionado = null;
$permissoes_usuario = [];
if (isset($_GET['usuario_id'])) {
    $usuario_selecionado = $funcionarios->getById($_GET['usuario_id']);
    if ($usuario_selecionado) {
        $permissoes_usuario = Permissoes::getPermissoesUsuario(
            $usuario_selecionado['id'],
            $usuario_selecionado['cargo'],
            $usuario_selecionado['departamento_id']
        );
    }
}
?>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Permissões - ASSEGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a5490;
            --secondary-color: #2c7a7b;
            --success-color: #48bb78;
            --danger-color: #f56565;
            --warning-color: #ed8936;
            --info-color: #4299e1;
            --dark: #2d3748;
            --light: #f7fafc;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin: 20px auto;
            max-width: 1400px;
            padding: 30px;
            backdrop-filter: blur(10px);
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .impersonate-banner {
            background: linear-gradient(135deg, var(--warning-color), #dd6b20);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light);
        }

        .user-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-card:hover {
            transform: translateX(10px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .user-card.selected {
            background: linear-gradient(135deg, var(--info-color), var(--primary-color));
            color: white;
        }

        .permission-badge {
            display: inline-block;
            padding: 5px 12px;
            margin: 3px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .permission-badge.granted {
            background: var(--success-color);
            color: white;
        }

        .permission-badge.wildcard {
            background: var(--info-color);
            color: white;
        }

        .permission-badge.denied {
            background: var(--danger-color);
            color: white;
        }

        .btn-impersonate {
            background: linear-gradient(135deg, var(--warning-color), #dd6b20);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            transition: all 0.3s;
        }

        .btn-impersonate:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(237, 137, 54, 0.4);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: scale(1.05);
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-card .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid var(--light);
            border-radius: 25px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 84, 144, 0.1);
        }

        .search-box i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }

        .tab-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--light);
        }

        .tab-nav button {
            background: none;
            border: none;
            padding: 10px 20px;
            color: var(--dark);
            font-weight: 500;
            position: relative;
            transition: all 0.3s;
        }

        .tab-nav button.active {
            color: var(--primary-color);
        }

        .tab-nav button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-color);
        }

        .permission-category {
            margin-bottom: 25px;
        }

        .permission-category h5 {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 15px;
            text-transform: capitalize;
        }

        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }

        .permission-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: var(--light);
            border-radius: 8px;
            transition: all 0.3s;
        }

        .permission-item:hover {
            background: #e2e8f0;
        }

        .permission-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php if ($auth->estaImpersonando()): ?>
        <div class="impersonate-banner">
            <div>
                <i class="fas fa-user-secret me-2"></i>
                <strong>MODO IMPERSONAÇÃO ATIVO</strong> - 
                Você está visualizando como: <?= htmlspecialchars($_SESSION['impersonate_nome']) ?>
                (<?= htmlspecialchars($_SESSION['impersonate_cargo']) ?>)
            </div>
            <a href="?stop_impersonate=1" class="btn btn-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Sair da Impersonação
            </a>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-shield-alt me-3"></i>Gerenciar Permissões</h1>
                    <p class="mb-0 mt-2">Sistema de controle de acesso e impersonação</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i> Voltar
                    </a>
                </div>
            </div>
        </div>

        <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?= count($lista_funcionarios) ?></div>
                <div class="label">Funcionários Ativos</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= count(array_unique(array_column($lista_funcionarios, 'departamento_id'))) ?></div>
                <div class="label">Departamentos</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= count(Permissoes::PERMISSOES) ?></div>
                <div class="label">Permissões no Sistema</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= count(array_unique(array_column($lista_funcionarios, 'cargo'))) ?></div>
                <div class="label">Cargos Diferentes</div>
            </div>
        </div>

        <div class="row">
            <!-- Lista de Funcionários -->
            <div class="col-md-4">
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-users me-2"></i> Funcionários
                    </h3>
                    
                    <div class="search-box">
                        <input type="text" id="searchUser" placeholder="Buscar funcionário...">
                        <i class="fas fa-search"></i>
                    </div>

                    <div id="usersList">
                        <?php foreach ($lista_funcionarios as $func): ?>
                        <div class="user-card <?= (isset($_GET['usuario_id']) && $_GET['usuario_id'] == $func['id']) ? 'selected' : '' ?>" 
                             data-name="<?= strtolower($func['nome']) ?>"
                             onclick="window.location.href='?usuario_id=<?= $func['id'] ?>'">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($func['nome']) ?></strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($func['cargo'] ?? 'Sem cargo') ?> - 
                                        <?= htmlspecialchars($func['departamento_nome'] ?? 'Sem departamento') ?>
                                    </small>
                                </div>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Deseja impersonar este usuário?')">
                                    <input type="hidden" name="action" value="impersonate">
                                    <input type="hidden" name="usuario_id" value="<?= $func['id'] ?>">
                                    <button type="submit" class="btn-impersonate" title="Impersonar usuário">
                                        <i class="fas fa-user-secret"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Detalhes de Permissões -->
            <div class="col-md-8">
                <?php if ($usuario_selecionado): ?>
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-user-shield me-2"></i>
                        Permissões de <?= htmlspecialchars($usuario_selecionado['nome']) ?>
                    </h3>

                    <!-- Informações do Usuário -->
                    <div class="alert alert-info mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Cargo:</strong> <?= htmlspecialchars($usuario_selecionado['cargo'] ?? 'Não definido') ?><br>
                                <strong>Departamento:</strong> <?= htmlspecialchars($usuario_selecionado['departamento_nome'] ?? 'Não definido') ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Email:</strong> <?= htmlspecialchars($usuario_selecionado['email']) ?><br>
                                <strong>Status:</strong> <?= $usuario_selecionado['ativo'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="tab-nav">
                        <button class="active" onclick="showTab('permissions')">
                            <i class="fas fa-key me-2"></i> Permissões Ativas
                        </button>
                        <button onclick="showTab('all')">
                            <i class="fas fa-list me-2"></i> Todas as Permissões
                        </button>
                    </div>

                    <!-- Permissões Ativas -->
                    <div id="permissions-tab">
                        <h4 class="mb-3">Permissões Efetivas do Usuário</h4>
                        <div class="mb-3">
                            <?php 
                            $permissoes_expandidas = [];
                            foreach ($permissoes_usuario as $perm) {
                                if ($perm === '*') {
                                    echo '<span class="permission-badge wildcard"><i class="fas fa-crown me-1"></i> Acesso Total</span>';
                                    $permissoes_expandidas = array_keys(Permissoes::PERMISSOES);
                                } elseif (strpos($perm, '*') !== false) {
                                    echo '<span class="permission-badge wildcard"><i class="fas fa-folder-open me-1"></i> ' . htmlspecialchars($perm) . '</span>';
                                    $categoria = str_replace('.*', '', $perm);
                                    foreach (Permissoes::PERMISSOES as $key => $desc) {
                                        if (strpos($key, $categoria . '.') === 0) {
                                            $permissoes_expandidas[] = $key;
                                        }
                                    }
                                } else {
                                    echo '<span class="permission-badge granted"><i class="fas fa-check me-1"></i> ' . htmlspecialchars($perm) . '</span>';
                                    $permissoes_expandidas[] = $perm;
                                }
                            }
                            ?>
                        </div>

                        <h5 class="mt-4 mb-3">Detalhamento por Categoria</h5>
                        <?php
                        $categorias = Permissoes::listarPorCategoria();
                        foreach ($categorias as $categoria => $perms):
                        ?>
                        <div class="permission-category">
                            <h5><i class="fas fa-folder me-2"></i> <?= htmlspecialchars($categoria) ?></h5>
                            <div class="permission-grid">
                                <?php foreach ($perms as $key => $desc): ?>
                                <div class="permission-item">
                                    <?php if (in_array($key, $permissoes_expandidas)): ?>
                                        <i class="fas fa-check-circle text-success"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-danger"></i>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($desc) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Todas as Permissões -->
                    <div id="all-tab" style="display: none;">
                        <h4 class="mb-3">Sistema Completo de Permissões</h4>
                        <?php foreach ($categorias as $categoria => $perms): ?>
                        <div class="permission-category">
                            <h5><i class="fas fa-folder me-2"></i> <?= htmlspecialchars($categoria) ?></h5>
                            <div class="permission-grid">
                                <?php foreach ($perms as $key => $desc): ?>
                                <div class="permission-item">
                                    <i class="fas fa-key text-primary"></i>
                                    <span>
                                        <strong><?= htmlspecialchars($key) ?></strong><br>
                                        <small><?= htmlspecialchars($desc) ?></small>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="section-card text-center">
                    <i class="fas fa-user-shield fa-5x text-muted mb-3"></i>
                    <h4>Selecione um Funcionário</h4>
                    <p class="text-muted">Escolha um funcionário na lista ao lado para visualizar suas permissões ou fazer impersonação.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Busca de usuários
        document.getElementById('searchUser').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const users = document.querySelectorAll('.user-card');
            
            users.forEach(user => {
                const name = user.getAttribute('data-name');
                if (name.includes(searchTerm)) {
                    user.style.display = 'block';
                } else {
                    user.style.display = 'none';
                }
            });
        });

        // Tabs
        function showTab(tab) {
            // Esconder todas as tabs
            document.getElementById('permissions-tab').style.display = 'none';
            document.getElementById('all-tab').style.display = 'none';
            
            // Remover classe active de todos os botões
            document.querySelectorAll('.tab-nav button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Mostrar tab selecionada
            if (tab === 'permissions') {
                document.getElementById('permissions-tab').style.display = 'block';
                event.target.classList.add('active');
            } else if (tab === 'all') {
                document.getElementById('all-tab').style.display = 'block';
                event.target.classList.add('active');
            }
        }
    </script>
</body>
</html>