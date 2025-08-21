<?php

/**
 * Componente Header Premium do Sistema ASSEGO
 * components/Header.php
 * Versão com design moderno e melhorias de UX/UI
 */

class HeaderComponent
{
    private $usuario;
    private $isDiretor;
    private $activePage;
    private $notificationCount;

    public function __construct($config = [])
    {
        $this->usuario = $config['usuario'] ?? ['nome' => 'Usuário', 'cargo' => 'Funcionário'];
        $this->isDiretor = $config['isDiretor'] ?? false;
        $this->activePage = $config['activePage'] ?? 'dashboard';
        $this->notificationCount = $config['notificationCount'] ?? 0;
    }

    /**
     * Renderiza o CSS do componente
     */
    public function renderCSS()
    {
?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

            :root {
                /* Cores Principais */
                --primary: #2563EB;
                --primary-dark: #1E40AF;
                --primary-light: #DBEAFE;
                --primary-gradient: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);

                /* Cores Secundárias */
                --secondary: #10B981;
                --accent: #F59E0B;
                --danger: #EF4444;
                --success: #10B981;
                --warning: #F59E0B;
                --info: #3B82F6;

                /* Tons de Cinza */
                --gray-50: #F9FAFB;
                --gray-100: #F3F4F6;
                --gray-200: #E5E7EB;
                --gray-300: #D1D5DB;
                --gray-400: #9CA3AF;
                --gray-500: #6B7280;
                --gray-600: #4B5563;
                --gray-700: #374151;
                --gray-800: #1F2937;
                --gray-900: #111827;
                --white: #FFFFFF;

                /* Configurações */
                --header-height: 72px;
                --header-bg: rgba(255, 255, 255, 0.95);
                --backdrop-blur: blur(20px);

                /* Sombras */
                --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.06);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
                --shadow-glow: 0 0 20px rgba(37, 99, 235, 0.15);

                /* Transições */
                --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
                --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
                --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
                --transition-slower: 500ms cubic-bezier(0.4, 0, 0.2, 1);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            /* Adiciona padding ao body */
            body.has-header {
                padding-top: var(--header-height) !important;
            }

            /* Header Principal */
            .header-container {
                background: var(--header-bg);
                backdrop-filter: var(--backdrop-blur);
                -webkit-backdrop-filter: var(--backdrop-blur);
                height: var(--header-height);
                border-bottom: 1px solid rgba(229, 231, 235, 0.8);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                transition: all var(--transition-base);
            }

            .header-container::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 100%;
                background: linear-gradient(90deg, rgba(37, 99, 235, 0.02) 0%, rgba(147, 51, 234, 0.02) 100%);
                pointer-events: none;
            }

            .header-content {
                position: relative;
                max-width: 100%;
                height: 100%;
                padding: 0 24px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            /* Seção Esquerda */
            .header-left {
                display: flex;
                align-items: center;
                gap: 32px;
                flex: 1;
            }

            /* Logo Animado */
            .logo-container {
                display: flex;
                align-items: center;
                gap: 14px;
                text-decoration: none;
                cursor: pointer;
                transition: transform var(--transition-base);
                padding: 8px 0;
            }

            .logo-container:hover {
                transform: scale(1.02);
            }

            .logo-icon {
                width: 44px;
                height: 44px;
                background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 900;
                font-size: 20px;
                box-shadow: 0 4px 14px 0 rgba(102, 126, 234, 0.25);
                position: relative;
                overflow: hidden;
            }

            .logo-icon::after {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
                transform: rotate(45deg);
                transition: all 0.5s;
                opacity: 0;
            }

            .logo-container:hover .logo-icon::after {
                animation: shine 0.5s ease-in-out;
            }

            @keyframes shine {
                0% {
                    transform: translateX(-100%) translateY(-100%) rotate(45deg);
                    opacity: 0;
                }

                50% {
                    opacity: 1;
                }

                100% {
                    transform: translateX(100%) translateY(100%) rotate(45deg);
                    opacity: 0;
                }
            }

            .logo-text-container {
                display: flex;
                flex-direction: column;
            }

            .logo-text {
                color: var(--gray-900);
                font-size: 19px;
                font-weight: 800;
                letter-spacing: -0.025em;
                line-height: 1;
            }

            .logo-subtitle {
                color: var(--gray-500);
                font-size: 11px;
                font-weight: 500;
                margin-top: 3px;
                letter-spacing: 0.025em;
            }

            /* Menu de Navegação */
            .nav-menu {
                display: flex;
                align-items: center;
                gap: 6px;
                flex: 1;
                padding: 0 20px;
            }

            .nav-item {
                position: relative;
            }

            .nav-link {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 14px;
                color: var(--gray-600);
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                border-radius: 10px;
                transition: all var(--transition-base);
                position: relative;
                overflow: hidden;
                border: 1px solid transparent;
            }

            .nav-link::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: var(--primary);
                opacity: 0;
                transition: opacity var(--transition-base);
                border-radius: 10px;
            }

            .nav-link:hover {
                color: var(--primary);
                background: rgba(37, 99, 235, 0.08);
                transform: translateY(-1px);
            }

            .nav-link.active {
                color: var(--white);
                background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
                box-shadow: 0 4px 12px 0 rgba(102, 126, 234, 0.25);
                font-weight: 600;
            }

            .nav-link.active::before {
                opacity: 1;
            }

            .nav-link i {
                font-size: 16px;
                transition: transform var(--transition-base);
            }

            .nav-link:hover i {
                transform: scale(1.1);
            }

            .nav-link span {
                position: relative;
                z-index: 1;
            }

            /* Badge de Novo */
            .nav-badge {
                position: absolute;
                top: 6px;
                right: 6px;
                background: var(--danger);
                color: white;
                font-size: 9px;
                font-weight: 700;
                padding: 2px 4px;
                border-radius: 4px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Seção Direita */
            .header-right {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-shrink: 0;
            }

            /* Botão de Notificações */
            .notification-btn {
                position: relative;
                width: 44px;
                height: 44px;
                border: 1px solid var(--gray-200);
                background: var(--white);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all var(--transition-base);
                color: var(--gray-600);
            }

            .notification-btn:hover {
                background: var(--gray-50);
                border-color: var(--primary);
                color: var(--primary);
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }

            .notification-btn i {
                font-size: 18px;
                transition: transform var(--transition-base);
            }

            .notification-btn:hover i {
                transform: scale(1.1);
                animation: bell-ring 0.5s ease-in-out;
            }

            @keyframes bell-ring {

                0%,
                100% {
                    transform: rotate(0deg) scale(1.1);
                }

                25% {
                    transform: rotate(-10deg) scale(1.1);
                }

                75% {
                    transform: rotate(10deg) scale(1.1);
                }
            }

            .notification-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                min-width: 20px;
                height: 20px;
                background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
                color: white;
                border-radius: 10px;
                font-size: 11px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 5px;
                border: 2px solid var(--white);
                box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
                animation: pulse-badge 2s infinite;
            }

            @keyframes pulse-badge {
                0% {
                    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
                }

                70% {
                    box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
                }

                100% {
                    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
                }
            }

            /* Menu do Usuário */
            .user-menu-container {
                position: relative;
            }

            .user-menu-trigger {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 8px;
                background: var(--white);
                border: 1px solid var(--gray-200);
                border-radius: 14px;
                cursor: pointer;
                transition: all var(--transition-base);
            }

            .user-menu-trigger:hover {
                background: var(--gray-50);
                border-color: var(--primary);
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }

            .user-info {
                text-align: right;
                padding-right: 4px;
            }

            .user-name {
                font-size: 14px;
                font-weight: 600;
                color: var(--gray-900);
                line-height: 1.2;
            }

            .user-role {
                font-size: 11px;
                color: var(--gray-500);
                margin-top: 2px;
                font-weight: 500;
            }

            .user-avatar {
                width: 38px;
                height: 38px;
                background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
                color: white;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 14px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
                position: relative;
            }

            .user-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .user-status {
                position: absolute;
                bottom: -2px;
                right: -2px;
                width: 12px;
                height: 12px;
                background: var(--success);
                border: 2px solid var(--white);
                border-radius: 50%;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }

            /* Dropdown do Usuário */
            .user-dropdown {
                position: absolute;
                top: calc(100% + 12px);
                right: 0;
                background: var(--white);
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
                min-width: 280px;
                padding: 8px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px) scale(0.95);
                transition: all var(--transition-slow);
                z-index: 1001;
                border: 1px solid var(--gray-100);
            }

            .user-dropdown.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }

            .user-dropdown-header {
                padding: 16px;
                background: var(--gray-50);
                border-radius: 12px;
                margin-bottom: 8px;
            }

            .user-dropdown-avatar {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }

            .user-dropdown-name {
                font-weight: 700;
                color: var(--gray-900);
                font-size: 15px;
            }

            .user-dropdown-email {
                font-size: 13px;
                color: var(--gray-500);
                margin-top: 2px;
            }

            .dropdown-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                color: var(--gray-700);
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                border-radius: 10px;
                transition: all var(--transition-base);
                position: relative;
                overflow: hidden;
            }

            .dropdown-item::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 3px;
                background: var(--primary);
                transform: translateX(-100%);
                transition: transform var(--transition-base);
            }

            .dropdown-item:hover {
                background: var(--gray-50);
                color: var(--primary);
                padding-left: 18px;
            }

            .dropdown-item:hover::before {
                transform: translateX(0);
            }

            .dropdown-item i {
                font-size: 16px;
                width: 20px;
                text-align: center;
            }

            .dropdown-divider {
                height: 1px;
                background: var(--gray-100);
                margin: 8px 0;
            }

            /* Mobile Menu Toggle */
            .mobile-menu-toggle {
                display: none;
                width: 44px;
                height: 44px;
                border: 1px solid var(--gray-200);
                background: var(--white);
                border-radius: 12px;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all var(--transition-base);
                color: var(--gray-600);
            }

            .mobile-menu-toggle:hover {
                background: var(--gray-50);
                border-color: var(--primary);
                color: var(--primary);
            }

            /* Responsive Design */
            @media (max-width: 1200px) {
                .nav-link {
                    padding: 10px 12px;
                    font-size: 13px;
                }

                .nav-link i {
                    font-size: 15px;
                }
            }

            @media (max-width: 992px) {
                .nav-menu {
                    display: none;
                }

                .mobile-menu-toggle {
                    display: flex;
                }

                .header-left {
                    gap: 16px;
                }
            }

            @media (max-width: 576px) {
                .header-content {
                    padding: 0 16px;
                }

                .user-info {
                    display: none;
                }

                .logo-subtitle {
                    display: none;
                }
            }

            /* Mobile Navigation */
            .mobile-nav {
                position: fixed;
                top: var(--header-height);
                left: -100%;
                width: 320px;
                height: calc(100vh - var(--header-height));
                background: var(--white);
                border-right: 1px solid var(--gray-200);
                padding: 20px;
                overflow-y: auto;
                transition: left var(--transition-slow);
                z-index: 999;
                box-shadow: 10px 0 40px rgba(0, 0, 0, 0.1);
            }

            .mobile-nav.show {
                left: 0;
            }

            .mobile-nav-header {
                padding: 16px;
                background: var(--gray-50);
                border-radius: 12px;
                margin-bottom: 20px;
            }

            .mobile-nav-item {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 14px 16px;
                color: var(--gray-700);
                text-decoration: none;
                font-size: 15px;
                font-weight: 500;
                border-radius: 12px;
                transition: all var(--transition-base);
                margin-bottom: 6px;
                position: relative;
            }

            .mobile-nav-item::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 3px;
                background: var(--primary);
                transform: scaleY(0);
                transition: transform var(--transition-base);
                border-radius: 0 3px 3px 0;
            }

            .mobile-nav-item:hover {
                background: var(--gray-50);
                color: var(--primary);
                padding-left: 20px;
            }

            .mobile-nav-item:hover::before {
                transform: scaleY(1);
            }

            .mobile-nav-item.active {
                background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
                color: var(--white);
                font-weight: 600;
                box-shadow: 0 4px 12px 0 rgba(102, 126, 234, 0.25);
            }

            .mobile-nav-item i {
                font-size: 18px;
                width: 24px;
            }

            .mobile-nav-divider {
                height: 1px;
                background: var(--gray-200);
                margin: 20px 0;
            }

            .mobile-nav-overlay {
                position: fixed;
                top: var(--header-height);
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.4);
                opacity: 0;
                visibility: hidden;
                transition: all var(--transition-slow);
                z-index: 998;
                backdrop-filter: blur(4px);
            }

            .mobile-nav-overlay.show {
                opacity: 1;
                visibility: visible;
            }

            /* Animações de Entrada */
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .header-container {
                animation: slideDown 0.5s ease-out;
            }

            /* Scrollbar Customizada */
            .mobile-nav::-webkit-scrollbar {
                width: 6px;
            }

            .mobile-nav::-webkit-scrollbar-track {
                background: var(--gray-100);
                border-radius: 3px;
            }

            .mobile-nav::-webkit-scrollbar-thumb {
                background: var(--gray-400);
                border-radius: 3px;
            }

            .mobile-nav::-webkit-scrollbar-thumb:hover {
                background: var(--gray-500);
            }
            .logo-icon {
    width: 40px !important;
    height: 40px !important;
    background: white !important; /* Fundo branco para garantir */
    border-radius: 8px !important;
    padding: 2px !important;
}

.logo-icon img {
    width: 100% !important;
    height: 100% !important;
    object-fit: contain !important;
    filter: none !important;
}
        </style>
    <?php
    }

    /**
     * Gera os itens de navegação baseado em permissões
     */
    private function getNavigationItems()
    {
        $items = [];

        // Verifica permissões
        $ehDaPresidencia = isset($this->usuario['departamento_id']) && $this->usuario['departamento_id'] == 1;
        $ehDoFinanceiro = isset($this->usuario['departamento_id']) && $this->usuario['departamento_id'] == 2;
        $ehDoRH = isset($this->usuario['departamento_id']) && $this->usuario['departamento_id'] == 9;
        $ehDoComercial = isset($this->usuario['departamento_id']) && $this->usuario['departamento_id'] == 10;

        // Associados - todos podem ver
        $items[] = [
            'id' => 'associados',
            'label' => 'Associados',
            'icon' => 'fas fa-users',
            'href' => 'dashboard.php'
        ];

        // Funcionários
        if ($this->isDiretor || $ehDaPresidencia || $ehDoRH || $ehDoComercial) {
            $items[] = [
                'id' => 'funcionarios',
                'label' => 'Funcionários',
                'icon' => 'fas fa-user-tie',
                'href' => 'funcionarios.php'
            ];
        }

        // Comercial
        if ($ehDaPresidencia || $ehDoComercial) {
            $items[] = [
                'id' => 'comercial',
                'label' => 'Comercial',
                'icon' => 'fas fa-briefcase',
                'href' => 'comercial.php'
            ];
        }

        // Financeiro
        if ($ehDaPresidencia || $ehDoFinanceiro) {
            $items[] = [
                'id' => 'financeiro',
                'label' => 'Financeiro',
                'icon' => 'fas fa-dollar-sign',
                'href' => 'financeiro.php'
            ];
        }

        // Auditoria
        if ($this->isDiretor || $ehDaPresidencia) {
            $items[] = [
                'id' => 'auditoria',
                'label' => 'Auditoria',
                'icon' => 'fas fa-user-shield',
                'href' => 'auditoria.php'
            ];
        }

        // Presidência
        if ($ehDaPresidencia) {
            $items[] = [
                'id' => 'presidencia',
                'label' => 'Presidência',
                'icon' => 'fas fa-landmark',
                'href' => 'presidencia.php'
            ];
        }

        // Relatórios - todos podem ver
        $items[] = [
            'id' => 'relatorios',
            'label' => 'Relatórios',
            'icon' => 'fas fa-chart-line',
            'href' => 'relatorios.php'
        ];

        // Documentos - todos podem ver
        $items[] = [
            'id' => 'documentos',
            'label' => 'Documentos',
            'icon' => 'fas fa-folder-open',
            'href' => 'documentos.php'
        ];

        return $items;
    }

    /**
     * Gera as iniciais do usuário
     */
    private function getUserInitials($nome)
    {
        if (empty($nome)) return '?';
        $parts = explode(' ', trim($nome));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }
        return strtoupper(substr($nome, 0, 2));
    }

    /**
     * Renderiza o JavaScript
     */
    public function renderJS()
    {
    ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Adiciona classe ao body para garantir padding
                document.body.classList.add('has-header');

                // User Dropdown
                const userMenuTrigger = document.getElementById('userMenuTrigger');
                const userDropdown = document.getElementById('userDropdown');

                if (userMenuTrigger && userDropdown) {
                    userMenuTrigger.addEventListener('click', function(e) {
                        e.stopPropagation();
                        userDropdown.classList.toggle('show');
                    });

                    // Fecha ao clicar fora
                    document.addEventListener('click', function() {
                        userDropdown.classList.remove('show');
                    });

                    userDropdown.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }

                // Mobile Menu
                const mobileMenuToggle = document.getElementById('mobileMenuToggle');
                const mobileNav = document.getElementById('mobileNav');
                const mobileNavOverlay = document.getElementById('mobileNavOverlay');

                if (mobileMenuToggle && mobileNav) {
                    mobileMenuToggle.addEventListener('click', function() {
                        mobileNav.classList.toggle('show');
                        mobileNavOverlay.classList.toggle('show');

                        // Anima o ícone do menu
                        const icon = this.querySelector('i');
                        if (mobileNav.classList.contains('show')) {
                            icon.classList.remove('fa-bars');
                            icon.classList.add('fa-times');
                        } else {
                            icon.classList.remove('fa-times');
                            icon.classList.add('fa-bars');
                        }
                    });
                }

                if (mobileNavOverlay) {
                    mobileNavOverlay.addEventListener('click', function() {
                        mobileNav.classList.remove('show');
                        mobileNavOverlay.classList.remove('show');

                        const icon = mobileMenuToggle.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    });
                }

                // Notifications
                const notificationBtn = document.getElementById('notificationBtn');
                if (notificationBtn) {
                    notificationBtn.addEventListener('click', function() {
                        // Implementar lógica de notificações
                        console.log('Notificações clicadas');
                    });
                }

                // Header scroll effect
                let lastScroll = 0;
                const header = document.querySelector('.header-container');

                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset;

                    if (currentScroll > 100) {
                        header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.08)';
                    } else {
                        header.style.boxShadow = 'none';
                    }

                    lastScroll = currentScroll;
                });

                // Tooltips nos ícones (opcional)
                const navLinks = document.querySelectorAll('.nav-link');
                navLinks.forEach(link => {
                    link.addEventListener('mouseenter', function() {
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.style.transform = 'scale(1.1) rotate(5deg)';
                        }
                    });

                    link.addEventListener('mouseleave', function() {
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.style.transform = 'scale(1) rotate(0deg)';
                        }
                    });
                });
            });

            // Função para garantir que o conteúdo não fique sob o header
            window.addEventListener('load', function() {
                const header = document.querySelector('.header-container');
                if (header) {
                    const headerHeight = header.offsetHeight;
                    document.body.style.paddingTop = headerHeight + 'px';
                }
            });

            // Adiciona efeitos de micro-interação
            document.querySelectorAll('button, a').forEach(element => {
                element.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    ripple.classList.add('ripple-effect');
                    this.appendChild(ripple);

                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        </script>
    <?php
    }

    /**
     * Renderiza o componente
     */
    public function render()
    {
        $navigationItems = $this->getNavigationItems();
        $userInitials = $this->getUserInitials($this->usuario['nome']);
    ?>

        <header class="header-container">
            <div class="header-content">
                <!-- Left Section -->
                <div class="header-left">
                    <!-- Mobile Menu Toggle -->
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>

                    <!-- Logo -->
                    <a href="dashboard.php" class="logo-container">
                        <div class="logo-icon">
                            <img src="img/logoassego.png" alt="Logo ASSEGO" class="logo-img">
                        </div>
                        <div class="logo-text-container">
                            <span class="logo-text">ASSEGO</span>
                        </div>
                    </a>

                    <!-- Desktop Navigation -->
                    <nav class="nav-menu">
                        <?php foreach ($navigationItems as $item): ?>
                            <div class="nav-item">
                                <a href="<?php echo htmlspecialchars($item['href']); ?>"
                                    class="nav-link <?php echo ($this->activePage === $item['id']) ? 'active' : ''; ?>">
                                    <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Right Section -->
                <div class="header-right">
                    <!-- Notifications -->
                    <button class="notification-btn" id="notificationBtn">
                        <i class="far fa-bell"></i>
                        <?php if ($this->notificationCount > 0): ?>
                            <span class="notification-badge">
                                <?php echo $this->notificationCount > 9 ? '9+' : $this->notificationCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- User Menu -->
                    <div class="user-menu-container">
                        <button class="user-menu-trigger" id="userMenuTrigger">
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($this->usuario['nome']); ?></div>
                                <div class="user-role"><?php echo htmlspecialchars($this->usuario['cargo'] ?? 'Funcionário'); ?></div>
                            </div>
                            <div class="user-avatar">
                                <?php if (isset($this->usuario['avatar']) && !empty($this->usuario['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($this->usuario['avatar']); ?>"
                                        alt="<?php echo htmlspecialchars($this->usuario['nome']); ?>">
                                <?php else: ?>
                                    <?php echo $userInitials; ?>
                                <?php endif; ?>
                                <span class="user-status"></span>
                            </div>
                        </button>

                        <!-- User Dropdown -->
                        <div class="user-dropdown" id="userDropdown">
                            <div class="user-dropdown-header">
                                <div class="user-dropdown-avatar">
                                    <div class="user-avatar">
                                        <?php echo $userInitials; ?>
                                    </div>
                                    <div>
                                        <div class="user-dropdown-name"><?php echo htmlspecialchars($this->usuario['nome']); ?></div>
                                        <div class="user-dropdown-email"><?php echo htmlspecialchars($this->usuario['email'] ?? 'usuario@assego.com.br'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <a href="perfil.php" class="dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                <span>Meu Perfil</span>
                            </a>

                            <a href="configuracoes.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                <span>Configurações</span>
                            </a>

                            <a href="ajuda.php" class="dropdown-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Ajuda</span>
                            </a>

                            <div class="dropdown-divider"></div>

                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Sair</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Navigation -->
        <nav class="mobile-nav" id="mobileNav">
            <div class="mobile-nav-header">
                <div class="user-dropdown-avatar">
                    <div class="user-avatar">
                        <?php echo $userInitials; ?>
                    </div>
                    <div>
                        <div class="user-dropdown-name"><?php echo htmlspecialchars($this->usuario['nome']); ?></div>
                        <div class="user-dropdown-email"><?php echo htmlspecialchars($this->usuario['cargo'] ?? 'Funcionário'); ?></div>
                    </div>
                </div>
            </div>

            <?php foreach ($navigationItems as $item): ?>
                <a href="<?php echo htmlspecialchars($item['href']); ?>"
                    class="mobile-nav-item <?php echo ($this->activePage === $item['id']) ? 'active' : ''; ?>">
                    <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
            <?php endforeach; ?>

            <div class="mobile-nav-divider"></div>

            <a href="perfil.php" class="mobile-nav-item">
                <i class="fas fa-user-circle"></i>
                <span>Meu Perfil</span>
            </a>

            <a href="configuracoes.php" class="mobile-nav-item">
                <i class="fas fa-cog"></i>
                <span>Configurações</span>
            </a>

            <a href="ajuda.php" class="mobile-nav-item">
                <i class="fas fa-question-circle"></i>
                <span>Ajuda</span>
            </a>

            <div class="mobile-nav-divider"></div>

            <a href="logout.php" class="mobile-nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </nav>

        <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>

    <?php
    }

    /**
     * Método estático para uso rápido
     */
    public static function create($config = [])
    {
        $header = new self($config);
        return $header;
    }
}

/**
 * Função helper para renderização rápida
 */
function renderHeader($config = [])
{
    $header = new HeaderComponent($config);
    $header->renderCSS();
    $header->render();
    $header->renderJS();
}

// Exemplo de uso quando acessado diretamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ASSEGO - Sistema de Gestão</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
            }

            .demo-content {
                padding: 40px;
                max-width: 1400px;
                margin: 0 auto;
            }

            .demo-card {
                background: white;
                border-radius: 20px;
                padding: 32px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                margin-bottom: 24px;
            }

            .demo-title {
                font-size: 28px;
                font-weight: 800;
                color: #111827;
                margin-bottom: 8px;
            }

            .demo-subtitle {
                font-size: 16px;
                color: #6B7280;
                margin-bottom: 24px;
            }

            .features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 24px;
            }

            .feature-card {
                padding: 20px;
                background: #F9FAFB;
                border-radius: 12px;
                border: 1px solid #E5E7EB;
            }

            .feature-icon {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                margin-bottom: 12px;
            }

            .feature-title {
                font-weight: 600;
                color: #111827;
                margin-bottom: 4px;
            }

            .feature-desc {
                font-size: 14px;
                color: #6B7280;
            }
        </style>
    </head>

    <body>
        <?php
        // Renderiza o header com configurações de exemplo
        renderHeader([
            'usuario' => [
                'nome' => 'Lydia de Souza Pauluci Ferreira',
                'cargo' => 'Contador',
                'email' => 'lydia@assego.com.br',
                'departamento_id' => 2, // Financeiro
                'avatar' => null
            ],
            'isDiretor' => false,
            'activePage' => 'financeiro',
            'notificationCount' => 3
        ]);
        ?>

        <div class="demo-content">
            <div class="demo-card">
                <h1 class="demo-title">🎨 Header Premium ASSEGO</h1>
                <p class="demo-subtitle">Design moderno com foco em UX/UI e micro-interações</p>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <div class="feature-title">Design Moderno</div>
                        <div class="feature-desc">Interface limpa com gradientes e sombras suaves</div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-magic"></i>
                        </div>
                        <div class="feature-title">Animações Fluidas</div>
                        <div class="feature-desc">Transições suaves e micro-interações elegantes</div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="feature-title">100% Responsivo</div>
                        <div class="feature-desc">Adaptável a todos os tamanhos de tela</div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="feature-title">Sistema de Permissões</div>
                        <div class="feature-desc">Controle de acesso por departamento</div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="feature-title">Notificações Animadas</div>
                        <div class="feature-desc">Badge com efeito pulse e contador</div>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="feature-title">Menu de Usuário</div>
                        <div class="feature-desc">Dropdown elegante com avatar e status</div>
                    </div>
                </div>
            </div>
        </div>
    </body>

    </html>
<?php
}
?>