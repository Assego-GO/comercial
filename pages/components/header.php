<?php
/**
 * Componente Header do Sistema ASSEGO
 * components/Header.php
 */

class HeaderComponent {
    private $usuario;
    private $isDiretor;
    private $activeTab;
    private $notificationCount;
    private $showSearch;
    private $customTabs;

    public function __construct($config = []) {
        $this->usuario = $config['usuario'] ?? ['nome' => 'Usuário', 'cargo' => 'Funcionário'];
        $this->isDiretor = $config['isDiretor'] ?? false;
        $this->activeTab = $config['activeTab'] ?? 'associados';
        $this->notificationCount = $config['notificationCount'] ?? 0;
        $this->showSearch = $config['showSearch'] ?? true;
        $this->customTabs = $config['customTabs'] ?? null;
    }

    /**
     * Renderiza o CSS do componente
     */
    public function renderCSS() {
        ?>
        <style>
            :root {
                --primary: #0056D2;
                --primary-dark: #003A8C;
                --primary-light: #E8F1FF;
                --secondary: #FFB800;
                --secondary-dark: #CC9200;
                --success: #00C853;
                --danger: #FF3B30;
                --warning: #FF9500;
                --info: #00B8D4;
                --dark: #1C1C1E;
                --gray-100: #F7F7F7;
                --gray-200: #E5E5E7;
                --gray-300: #D1D1D6;
                --gray-400: #C7C7CC;
                --gray-500: #8E8E93;
                --gray-600: #636366;
                --gray-700: #48484A;
                --gray-800: #3A3A3C;
                --gray-900: #2C2C2E;
                --white: #FFFFFF;
                --header-height: 70px;
                --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
                --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.24);
                --shadow-lg: 0 10px 20px rgba(0, 0, 0, 0.12), 0 4px 8px rgba(0, 0, 0, 0.24);
            }

            .main-header {
                background: var(--white);
                height: var(--header-height);
                padding: 0 2rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: var(--shadow-sm);
                position: sticky;
                top: 0;
                z-index: 100;
            }

            .header-left {
                display: flex;
                align-items: center;
                gap: 2rem;
            }

            .logo-section {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .logo-icon {
                width: 40px;
                height: 40px;
                background: var(--primary);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 800;
                font-size: 1.2rem;
            }

            .logo-text {
                color: var(--primary);
                font-size: 1.5rem;
                font-weight: 800;
                margin: 0 0 -2px 0;
                letter-spacing: -0.5px;
            }

            .system-subtitle {
                color: var(--gray-500);
                font-size: 0.875rem;
                margin: 0;
                font-weight: 500;
            }

            .header-right {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .header-btn {
                width: 40px;
                height: 40px;
                border: none;
                background: var(--gray-100);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
                color: var(--gray-600);
            }

            .header-btn:hover {
                background: var(--primary-light);
                color: var(--primary);
            }

            .notification-badge {
                position: absolute;
                top: 8px;
                right: 8px;
                width: 8px;
                height: 8px;
                background: var(--danger);
                border-radius: 50%;
                border: 2px solid var(--white);
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
                70% { box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
                100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
            }

            .user-menu {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.5rem;
                background: var(--gray-100);
                border-radius: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
            }

            .user-menu:hover {
                background: var(--gray-200);
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                background: var(--primary);
                color: var(--white);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                overflow: hidden;
            }

            .user-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .user-info {
                text-align: right;
            }

            .user-name {
                font-weight: 600;
                font-size: 0.875rem;
                color: var(--dark);
                margin: 0;
            }

            .user-role {
                font-size: 0.75rem;
                color: var(--gray-500);
                margin: 0;
            }

            .dropdown-menu-custom {
                position: absolute;
                top: calc(100% + 10px);
                right: 0;
                background: var(--white);
                border-radius: 12px;
                box-shadow: var(--shadow-lg);
                min-width: 200px;
                padding: 0.5rem;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: all 0.3s ease;
                z-index: 1000;
            }

            .dropdown-menu-custom.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .dropdown-item-custom {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem 1rem;
                border-radius: 8px;
                color: var(--gray-700);
                text-decoration: none;
                transition: all 0.2s ease;
            }

            .dropdown-item-custom:hover {
                background: var(--gray-100);
                color: var(--primary);
            }

            .dropdown-divider-custom {
                height: 1px;
                background: var(--gray-200);
                margin: 0.5rem 0;
            }

            .nav-tabs-container {
                background: var(--white);
                box-shadow: var(--shadow-sm);
                position: sticky;
                top: var(--header-height);
                z-index: 99;
                border-bottom: 1px solid var(--gray-200);
            }

            .nav-tabs-modern {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0.5rem 2rem;
                margin: 0;
                list-style: none;
                gap: 1rem;
            }

            .nav-tab-item {
                margin: 0;
            }

            .nav-tab-link {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 1rem 2rem;
                color: var(--gray-600);
                text-decoration: none;
                border: none;
                background: var(--gray-100);
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                border-radius: 12px;
                min-width: 120px;
            }

            .nav-tab-link:hover {
                background: var(--gray-200);
                color: var(--gray-700);
            }

            .nav-tab-link.active {
                background: var(--primary);
                color: var(--white);
                box-shadow: 0 4px 12px rgba(0, 86, 210, 0.25);
            }

            .nav-tab-icon {
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.125rem;
                margin-bottom: 0.375rem;
                transition: all 0.3s ease;
            }

            .nav-tab-link.active .nav-tab-icon {
                color: var(--white);
            }

            .nav-tab-text {
                font-weight: 600;
                font-size: 0.8125rem;
                transition: all 0.3s ease;
            }

            @media (max-width: 768px) {
                .main-header {
                    padding: 0 1rem;
                }

                .user-info {
                    display: none;
                }

                .nav-tabs-modern {
                    overflow-x: auto;
                    justify-content: flex-start;
                    padding: 0 1rem;
                }

                .nav-tab-link {
                    min-width: 100px;
                    padding: 1rem;
                }

                .nav-tab-icon {
                    width: 40px;
                    height: 40px;
                    font-size: 1rem;
                }
            }
        </style>
        <?php
    }

    /**
     * Gera as tabs do sistema
     */
    private function getTabs() {
        // DEBUG HEADER - CONSOLE
        echo "<script>";
        echo "console.log('=== DEBUG HEADER TABS ===');";
        echo "console.log('this.isDiretor:', " . ($this->isDiretor ? 'true' : 'false') . ");";
        echo "console.log('this.usuario:', " . json_encode($this->usuario) . ");";
        echo "console.log('Tem departamento_id no usuario?', " . (isset($this->usuario['departamento_id']) ? 'true' : 'false') . ");";
        
        if (isset($this->usuario['departamento_id'])) {
            echo "console.log('Departamento ID valor:', " . json_encode($this->usuario['departamento_id']) . ");";
            echo "console.log('Departamento ID tipo:', '" . gettype($this->usuario['departamento_id']) . "');";
            echo "console.log('departamento_id == 1:', " . ($this->usuario['departamento_id'] == 1 ? 'true' : 'false') . ");";
            echo "console.log('departamento_id === 1:', " . ($this->usuario['departamento_id'] === 1 ? 'true' : 'false') . ");";
            echo "console.log('departamento_id === \"1\":', " . ($this->usuario['departamento_id'] === '1' ? 'true' : 'false') . ");";
        }
        
        // Teste da lógica de permissão
        $podeVerFuncionarios = false;
        if ($this->isDiretor) {
            $podeVerFuncionarios = true;
            echo "console.log('✅ Permissão por DIRETOR');";
        } elseif (isset($this->usuario['departamento_id']) && $this->usuario['departamento_id'] == 1) {
            $podeVerFuncionarios = true;
            echo "console.log('✅ Permissão por PRESIDÊNCIA');";
        } else {
            echo "console.log('❌ SEM PERMISSÃO');";
        }
        
        echo "console.log('podeVerFuncionarios:', " . ($podeVerFuncionarios ? 'true' : 'false') . ");";
        echo "console.log('========================');";
        echo "</script>";
        
        if ($this->customTabs) {
            return $this->customTabs;
        }

        $tabs = [
            [
                'id' => 'associados',
                'label' => 'Associados',
                'icon' => 'fas fa-users',
                'href' => 'dashboard.php'
            ]
        ];

        // CORREÇÃO: Permite acesso tanto para DIRETORES quanto para usuários da PRESIDÊNCIA
        $podeVerFuncionarios = false;

        // Verifica se é diretor OU se é da presidência (departamento_id = 1)
        if ($this->isDiretor) {
            $podeVerFuncionarios = true;
        } elseif (isset($this->usuario['departamento_id']) && $this->usuario['departamento_id'] == 1) {
            $podeVerFuncionarios = true;
        }

        // Adiciona a aba se tiver permissão
        if ($podeVerFuncionarios) {
            $tabs[] = [
                'id' => 'funcionarios',
                'label' => 'Funcionários',
                'icon' => 'fas fa-user-tie',
                'href' => './funcionarios.php' // ← CORRIGIDO: Caminho relativo correto
            ];
            
            // Debug final
            echo "<script>console.log('✅ ABA FUNCIONÁRIOS ADICIONADA!');</script>";
        } else {
            echo "<script>console.log('❌ ABA FUNCIONÁRIOS NÃO ADICIONADA');</script>";
        }

        $tabs[] = [
            'id' => 'relatorios',
            'label' => 'Relatórios',
            'icon' => 'fas fa-chart-line',
            'href' => 'relatorios.php'
        ];

        $tabs[] = [
            'id' => 'presidencia',
            'label' => 'Presidência',
            'icon' => 'fas fa-landmark',
            'href' => 'presidencia.php'
        ];

        $tabs[] = [
            'id' => 'auditoria',
            'label' => 'Auditoria',
            'icon' => 'fas fa-user-shield',
            'href' => 'auditoria.php'
        ];

        $tabs[] = [
            'id' => 'documentos',
            'label' => 'Documentos',
            'icon' => 'fas fa-folder-open',
            'href' => 'documentos.php'
        ];

        return $tabs;
    }

    /**
     * Gera as iniciais do usuário
     */
    private function getUserInitials($nome) {
        if (empty($nome)) return '?';
        $parts = explode(' ', trim($nome));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($nome, 0, 1));
    }

    /**
     * Renderiza o JavaScript necessário
     */
    public function renderJS() {
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // User Dropdown Menu
                const userMenu = document.getElementById('userMenu');
                const userDropdown = document.getElementById('userDropdown');

                if (userMenu && userDropdown) {
                    userMenu.addEventListener('click', function(e) {
                        e.stopPropagation();
                        userDropdown.classList.toggle('show');
                    });

                    // Fecha dropdown ao clicar fora
                    document.addEventListener('click', function() {
                        userDropdown.classList.remove('show');
                    });
                }
            });
        </script>
        <?php
    }

    /**
     * Renderiza o componente completo
     */
    public function render() {
        $tabs = $this->getTabs();
        $userInitials = $this->getUserInitials($this->usuario['nome']);
        
        // DEBUG DOS LINKS - CONSOLE
        echo "<script>";
        echo "console.log('=== DEBUG LINKS DAS ABAS ===');";
        foreach ($tabs as $tab) {
            echo "console.log('Aba: " . $tab['label'] . " → Link: " . $tab['href'] . "');";
        }
        echo "console.log('============================');";
        echo "</script>";
        ?>
        
        <!-- Header Principal -->
        <header class="main-header">
            <div class="header-left">
                <div class="logo-section">
                    <div class="logo-icon">A</div>
                    <div>
                        <h1 class="logo-text">ASSEGO</h1>
                        <p class="system-subtitle">Sistema de Gestão</p>
                    </div>
                </div>
            </div>

            <div class="header-right">
                <?php if ($this->showSearch): ?>
                <button class="header-btn" onclick="toggleSearch()">
                    <i class="fas fa-search"></i>
                </button>
                <?php endif; ?>
                
                <button class="header-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($this->notificationCount > 0): ?>
                        <span class="notification-badge"></span>
                    <?php endif; ?>
                </button>
                
                <div class="user-menu" id="userMenu">
                    <div class="user-info">
                        <p class="user-name"><?php echo htmlspecialchars($this->usuario['nome']); ?></p>
                        <p class="user-role"><?php echo htmlspecialchars($this->usuario['cargo']); ?></p>
                    </div>
                    
                    <div class="user-avatar">
                        <?php if (isset($this->usuario['avatar']) && !empty($this->usuario['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($this->usuario['avatar']); ?>" 
                                 alt="<?php echo htmlspecialchars($this->usuario['nome']); ?>">
                        <?php else: ?>
                            <?php echo $userInitials; ?>
                        <?php endif; ?>
                    </div>
                    
                    <i class="fas fa-chevron-down ms-2" style="font-size: 0.75rem; color: var(--gray-500);"></i>

                    <!-- Dropdown Menu -->
                    <div class="dropdown-menu-custom" id="userDropdown">
                        <a href="perfil.php" class="dropdown-item-custom">
                            <i class="fas fa-user"></i>
                            <span>Meu Perfil</span>
                        </a>
                        
                        <?php if ($this->isDiretor): ?>
                            <a href="configuracoes.php" class="dropdown-item-custom">
                                <i class="fas fa-cog"></i>
                                <span>Configurações</span>
                            </a>
                        <?php endif; ?>
                        
                        <div class="dropdown-divider-custom"></div>
                        
                        <a href="logout.php" class="dropdown-item-custom">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="nav-tabs-container">
            <ul class="nav-tabs-modern">
                <?php foreach ($tabs as $tab): ?>
                    <li class="nav-tab-item">
                        <a href="<?php echo htmlspecialchars($tab['href']); ?>" 
                           class="nav-tab-link <?php echo ($this->activeTab === $tab['id']) ? 'active' : ''; ?>">
                            <div class="nav-tab-icon">
                                <i class="<?php echo htmlspecialchars($tab['icon']); ?>"></i>
                            </div>
                            <span class="nav-tab-text"><?php echo htmlspecialchars($tab['label']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        
        <!-- DEBUG - Event listeners para cliques -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔧 Adicionando listeners para debug de cliques...');
            const links = document.querySelectorAll('.nav-tab-link');
            links.forEach(function(link, index) {
                const texto = link.querySelector('.nav-tab-text').textContent;
                console.log(`Aba ${index + 1}: ${texto} → ${link.href}`);
                
                link.addEventListener('click', function(e) {
                    console.log(`🖱️ CLICOU: ${texto}`);
                    console.log(`🔗 URL de destino: ${this.href}`);
                    console.log(`📍 URL atual: ${window.location.href}`);
                    
                    // Se for funcionários, vamos debugar mais
                    if (texto === 'Funcionários') {
                        console.log('🔍 DEBUG ESPECIAL - FUNCIONÁRIOS:');
                        console.log('- Link absoluto:', this.href);
                        console.log('- Pathname:', new URL(this.href).pathname);
                        console.log('- Arquivo de destino:', this.href.split('/').pop());
                        
                        // Teste se o arquivo existe fazendo uma requisição
                        fetch(this.href, {method: 'HEAD'})
                            .then(response => {
                                if (response.ok) {
                                    console.log('✅ Arquivo funcionarios.php EXISTE e é acessível');
                                } else {
                                    console.log('❌ Arquivo funcionarios.php NÃO EXISTE (status:', response.status, ')');
                                }
                            })
                            .catch(error => {
                                console.log('❌ Erro ao verificar arquivo:', error);
                            });
                    }
                });
            });
        });
        </script>
        
        <?php
    }

    /**
     * Método estático para uso rápido
     */
    public static function create($config = []) {
        $header = new self($config);
        return $header;
    }
}

/**
 * Função helper para uso mais simples
 */
function renderHeader($config = []) {
    $header = new HeaderComponent($config);
    $header->renderCSS();
    $header->render();
    $header->renderJS();
}

// Exemplo de uso:
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    // Este código só roda se o arquivo for acessado diretamente (para testes)
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Exemplo Header Component</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #F7F7F7;
            }
        </style>
    </head>
    <body>
        <?php
        // Exemplo de uso do componente
        renderHeader([
            'usuario' => [
                'nome' => 'João Silva Santos',
                'cargo' => 'Diretor Executivo',
                'avatar' => null // ou URL da imagem
            ],
            'isDiretor' => true,
            'activeTab' => 'associados',
            'notificationCount' => 5,
            'showSearch' => true
        ]);
        ?>
        
        <!-- Conteúdo da página -->
        <div style="padding: 2rem;">
            <h2>Exemplo de Uso do Header Component</h2>
            <div style="background: white; padding: 2rem; border-radius: 12px; margin-top: 1rem;">
                <h3>Como usar:</h3>
                <pre style="background: #f5f5f5; padding: 1rem; border-radius: 8px; overflow-x: auto;"><code>&lt;?php

require_once 'components/header.php';
// Uso simples
renderHeader([
    'usuario' => $usuarioLogado,
    'isDiretor' => $auth->isDiretor(),
    'activeTab' => 'associados',
    'notificationCount' => 3
]);

// Ou usando a classe diretamente
$header = new HeaderComponent([
    'usuario' => ['nome' => 'João', 'cargo' => 'Admin'],
    'isDiretor' => true,
    'activeTab' => 'funcionarios'
]);

$header->renderCSS();
$header->render();
$header->renderJS();
?&gt;</code></pre>
            </div>
        </div>

        <script>
            // Funções de exemplo para os botões
            function toggleSearch() {
                alert('Busca clicada!');
            }
            
            function toggleNotifications() {
                alert('Notificações clicadas!');
            }
        </script>
    </body>
    </html>
    <?php
}
?>