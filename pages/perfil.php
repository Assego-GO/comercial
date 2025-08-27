<?php
/**
 * Página de Perfil do Usuário
 * pages/perfil.php
 */

// Configuração e includes
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/Funcionarios.php';

// NOVO: Include do componente Header
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
$page_title = 'Meu Perfil - ASSEGO';

// Inicializa classe de funcionários
$funcionarios = new Funcionarios();

// Busca dados completos do funcionário
$funcionarioCompleto = $funcionarios->getById($usuarioLogado['id']);
$badges = $funcionarios->getBadges($usuarioLogado['id']);
$contribuicoes = $funcionarios->getContribuicoes($usuarioLogado['id']);
$estatisticas = $funcionarios->getEstatisticas($usuarioLogado['id']);

// Calcula tempo de empresa
$tempoEmpresa = '-';
if ($funcionarioCompleto['criado_em']) {
    $dataInicio = new DateTime($funcionarioCompleto['criado_em']);
    $hoje = new DateTime();
    $intervalo = $dataInicio->diff($hoje);
    
    if ($intervalo->y > 0) {
        $tempoEmpresa = $intervalo->y . ' ano' . ($intervalo->y > 1 ? 's' : '');
        if ($intervalo->m > 0) {
            $tempoEmpresa .= ' e ' . $intervalo->m . ' mes' . ($intervalo->m > 1 ? 'es' : '');
        }
    } elseif ($intervalo->m > 0) {
        $tempoEmpresa = $intervalo->m . ' mes' . ($intervalo->m > 1 ? 'es' : '');
    } else {
        $tempoEmpresa = $intervalo->d . ' dia' . ($intervalo->d > 1 ? 's' : '');
    }
}

// Cria instância do Header Component
$headerComponent = HeaderComponent::create([
    'usuario' => [
        'nome' => $usuarioLogado['nome'],
        'cargo' => $usuarioLogado['cargo'] ?? 'Funcionário',
        'avatar' => $usuarioLogado['avatar'] ?? null,
        'departamento_id' => $usuarioLogado['departamento_id'] ?? null
    ],
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => '',
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

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- CSS do Header Component -->
    <?php $headerComponent->renderCSS(); ?>
    <link rel="stylesheet" href="estilizacao/perfil.css">
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Carregando...</div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        
        <!-- NOVO: Header Component -->
        <?php $headerComponent->render(); ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-header-content">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($funcionarioCompleto['nome'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($funcionarioCompleto['nome']); ?></h1>
                    <div class="profile-meta">
                        <div class="profile-meta-item">
                            <i class="fas fa-briefcase"></i>
                            <span><?php echo htmlspecialchars($funcionarioCompleto['cargo'] ?? 'Sem cargo'); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-building"></i>
                            <span><?php echo htmlspecialchars($funcionarioCompleto['departamento_nome'] ?? 'Sem departamento'); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($funcionarioCompleto['email']); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-clock"></i>
                            <span>Há <?php echo $tempoEmpresa; ?> na empresa</span>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <!-- <button class="btn-modern btn-white" onclick="abrirModalEdicao()">
                            <i class="fas fa-edit"></i>
                            Editar Perfil
                        </button> -->
                        <button class="btn-modern btn-white" onclick="abrirModalSenha()">
                            <i class="fas fa-key"></i>
                            Alterar Senha
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="profile-grid">
                <!-- Sidebar -->
                <div class="profile-sidebar">
                    <!-- Stats Card - COMENTADO TEMPORARIAMENTE -->
                    <!--
                    <div class="profile-card" data-aos="fade-right">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-chart-line"></i>
                                Estatísticas
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <div class="stats-summary">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $estatisticas['total_badges']; ?></div>
                                    <div class="stat-label">Badges</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $estatisticas['total_pontos']; ?></div>
                                    <div class="stat-label">Pontos</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $estatisticas['total_contribuicoes']; ?></div>
                                    <div class="stat-label">Projetos</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    -->

                    <!-- Personal Info Card -->
                    <div class="profile-card" data-aos="fade-right" data-aos-delay="100">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-info-circle"></i>
                                Informações Pessoais
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="info-value">
                                        <?php if ($funcionarioCompleto['ativo'] == 1): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inativo</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">CPF</span>
                                    <span class="info-value">
                                        <?php 
                                        $cpf = $funcionarioCompleto['cpf'] ?? '';
                                        if ($cpf) {
                                            echo substr($cpf, 0, 3) . '.***.**-' . substr($cpf, -2);
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">RG</span>
                                    <span class="info-value"><?php echo htmlspecialchars($funcionarioCompleto['rg'] ?? '-'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Data de Cadastro</span>
                                    <span class="info-value">
                                        <?php 
                                        if ($funcionarioCompleto['criado_em']) {
                                            echo date('d/m/Y', strtotime($funcionarioCompleto['criado_em']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Última Alteração de Senha</span>
                                    <span class="info-value">
                                        <?php 
                                        if ($funcionarioCompleto['senha_alterada_em']) {
                                            echo date('d/m/Y H:i', strtotime($funcionarioCompleto['senha_alterada_em']));
                                        } else {
                                            echo 'Nunca alterada';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="profile-main">
                    <!-- Badges Section - COMENTADO TEMPORARIAMENTE -->
                    <!--
                    <div class="profile-card" data-aos="fade-up">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-medal"></i>
                                Badges e Conquistas
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <?php if (empty($badges)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-medal"></i>
                                    <p>Você ainda não conquistou nenhuma badge</p>
                                </div>
                            <?php else: ?>
                                <div class="badges-grid">
                                    <?php foreach ($badges as $badge): ?>
                                        <?php
                                        $nivel = strtolower($badge['badge_nivel'] ?? 'bronze');
                                        $iconClass = $nivel === 'ouro' ? 'gold' : ($nivel === 'prata' ? 'silver' : 'bronze');
                                        ?>
                                        <div class="badge-card">
                                            <div class="badge-points"><?php echo $badge['pontos'] ?? 0; ?> pts</div>
                                            <div class="badge-icon <?php echo $iconClass; ?>">
                                                <i class="<?php echo $badge['badge_icone'] ?? 'fas fa-award'; ?>"></i>
                                            </div>
                                            <div class="badge-name"><?php echo htmlspecialchars($badge['badge_nome']); ?></div>
                                            <div class="badge-date">
                                                <?php echo date('d/m/Y', strtotime($badge['data_conquista'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    -->

                    <!-- Contributions Section - COMENTADO TEMPORARIAMENTE -->
                    <!--
                    <div class="profile-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title">
                                <i class="fas fa-project-diagram"></i>
                                Contribuições e Projetos
                            </h3>
                        </div>
                        <div class="profile-card-body">
                            <?php if (empty($contribuicoes)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-project-diagram"></i>
                                    <p>Nenhuma contribuição registrada</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($contribuicoes as $contrib): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-dot"></div>
                                            <div class="timeline-content">
                                                <div class="timeline-header">
                                                    <div>
                                                        <div class="timeline-title"><?php echo htmlspecialchars($contrib['titulo']); ?></div>
                                                        <span class="timeline-type"><?php echo htmlspecialchars($contrib['tipo'] ?? 'PROJETO'); ?></span>
                                                    </div>
                                                </div>
                                                <?php if ($contrib['descricao']): ?>
                                                    <div class="timeline-description">
                                                        <?php echo htmlspecialchars($contrib['descricao']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="timeline-date">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php 
                                                    echo date('d/m/Y', strtotime($contrib['data_inicio']));
                                                    if ($contrib['data_fim']) {
                                                        echo ' até ' . date('d/m/Y', strtotime($contrib['data_fim']));
                                                    } else {
                                                        echo ' - Em andamento';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Perfil -->
    <div class="modal-custom" id="modalEdicao">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Editar Perfil</h2>
                <button class="modal-close-custom" onclick="fecharModalEdicao()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formEdicao">
                    <div class="form-group">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" class="form-control-custom" id="nome" name="nome" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['nome']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control-custom" id="email" name="email" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['email']); ?>" required>
                        <div class="form-text">Este email é usado para login no sistema</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">CPF</label>
                        <input type="text" class="form-control-custom" id="cpf" name="cpf" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['cpf'] ?? ''); ?>"
                               placeholder="000.000.000-00" maxlength="14">
                    </div>

                    <div class="form-group">
                        <label class="form-label">RG</label>
                        <input type="text" class="form-control-custom" id="rg" name="rg" 
                               value="<?php echo htmlspecialchars($funcionarioCompleto['rg'] ?? ''); ?>">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModalEdicao()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-save"></i>
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Alterar Senha -->
    <div class="modal-custom" id="modalSenha">
        <div class="modal-content-custom">
            <div class="modal-header-custom">
                <h2 class="modal-title-custom">Alterar Senha</h2>
                <button class="modal-close-custom" onclick="fecharModalSenha()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-custom">
                <form id="formSenha">
                    <div class="form-group">
                        <label class="form-label">Senha Atual</label>
                        <input type="password" class="form-control-custom" id="senhaAtual" name="senha_atual" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" class="form-control-custom" id="novaSenha" name="nova_senha" required>
                        <div class="form-text">Mínimo 6 caracteres. Use letras, números e símbolos para maior segurança.</div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control-custom" id="confirmarSenha" name="confirmar_senha" required>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-secondary" onclick="fecharModalSenha()">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-key"></i>
                            Alterar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

    <!-- JavaScript do Header Component -->
    <?php $headerComponent->renderJS(); ?>

    <!-- JavaScript customizado para os botões do header -->
    <script>
        function toggleSearch() {
            // Para a página de perfil, você pode redirecionar para o dashboard
            window.location.href = 'dashboard.php';
        }
        
        function toggleNotifications() {
            // Implementar painel de notificações
            console.log('Painel de notificações');
            alert('Painel de notificações em desenvolvimento');
        }
    </script>

    <script>
        // Inicializa AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Configuração inicial
        document.addEventListener('DOMContentLoaded', function() {
            // Máscaras
            $('#cpf').mask('000.000.000-00');

            // Event listeners
            document.getElementById('formEdicao').addEventListener('submit', salvarPerfil);
            document.getElementById('formSenha').addEventListener('submit', alterarSenha);
            document.getElementById('novaSenha').addEventListener('input', verificarForcaSenha);
        });

        // Loading functions
        function showLoading(texto = 'Processando...') {
            const overlay = document.getElementById('loadingOverlay');
            const loadingText = overlay.querySelector('.loading-text');
            loadingText.textContent = texto;
            overlay.classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Modal functions
        function abrirModalEdicao() {
            document.getElementById('modalEdicao').classList.add('show');
        }

        function fecharModalEdicao() {
            document.getElementById('modalEdicao').classList.remove('show');
        }

        function abrirModalSenha() {
            document.getElementById('modalSenha').classList.add('show');
            document.getElementById('formSenha').reset();
            document.getElementById('passwordStrength').className = 'password-strength-bar';
        }

        function fecharModalSenha() {
            document.getElementById('modalSenha').classList.remove('show');
        }

        // Salvar perfil
        function salvarPerfil(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const dados = {
                id: <?php echo $usuarioLogado['id']; ?>
            };
            
            // Converte FormData para objeto
            for (let [key, value] of formData.entries()) {
                dados[key] = value;
            }
            
            showLoading('Salvando alterações...');
            
            $.ajax({
                url: '../api/funcionarios_atualizar.php',
                method: 'PUT',
                data: JSON.stringify(dados),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        alert('Perfil atualizado com sucesso!');
                        fecharModalEdicao();
                        // Recarrega a página para mostrar os dados atualizados
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', xhr.responseText);
                    alert('Erro ao salvar alterações');
                }
            });
        }

        // Alterar senha
        function alterarSenha(e) {
            e.preventDefault();
            
            const senhaAtual = document.getElementById('senhaAtual').value;
            const novaSenha = document.getElementById('novaSenha').value;
            const confirmarSenha = document.getElementById('confirmarSenha').value;
            
            // Validações
            if (novaSenha.length < 6) {
                alert('A nova senha deve ter pelo menos 6 caracteres');
                return;
            }
            
            if (novaSenha !== confirmarSenha) {
                alert('As senhas não coincidem');
                return;
            }
            
            if (senhaAtual === novaSenha) {
                alert('A nova senha deve ser diferente da senha atual');
                return;
            }
            
            showLoading('Alterando senha...');
            
            $.ajax({
                url: '../api/alterar_senha.php',
                method: 'POST',
                data: JSON.stringify({
                    senha_atual: senhaAtual,
                    nova_senha: novaSenha
                }),
                contentType: 'application/json',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.status === 'success') {
                        alert('Senha alterada com sucesso!');
                        fecharModalSenha();
                    } else {
                        alert('Erro: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Erro:', xhr.responseText);
                    alert('Erro ao alterar senha');
                }
            });
        }

        // Verifica força da senha
        function verificarForcaSenha() {
            const senha = document.getElementById('novaSenha').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            
            // Critérios
            if (senha.length >= 6) strength++;
            if (senha.length >= 10) strength++;
            if (/[a-z]/.test(senha) && /[A-Z]/.test(senha)) strength++;
            if (/[0-9]/.test(senha)) strength++;
            if (/[^a-zA-Z0-9]/.test(senha)) strength++;
            
            // Atualiza barra
            strengthBar.className = 'password-strength-bar';
            
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        }

        // Fecha modal ao clicar fora
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-custom')) {
                event.target.classList.remove('show');
            }
        });

        // Tecla ESC fecha modais
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModalEdicao();
                fecharModalSenha();
            }
        });

        console.log('Página de perfil carregada com Header Component!');
    </script>
</body>
</html>