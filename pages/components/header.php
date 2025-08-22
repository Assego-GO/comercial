<?php
/**
 * Componente Header Premium do Sistema ASSEGO
 * components/Header.php
 * VERSÃO ATUALIZADA COM SISTEMA DE NOTIFICAÇÕES INTEGRADO
 * Versão com cores oficiais ASSEGO: Azul Royal (#003C8F) e Dourado (#FFB800)
 * Detecção automática de página ativa + Alerta de senha padrão + Sistema de Notificações
 */

class HeaderComponent {
    private $usuario;
    private $isDiretor;
    private $activePage;
    private $notificationCount;

    public function __construct($config = []) {
        $this->usuario = $config['usuario'] ?? ['nome' => 'Usuário', 'cargo' => 'Funcionário'];
        $this->isDiretor = $config['isDiretor'] ?? false;
        $this->notificationCount = $config['notificationCount'] ?? 0;
        
        // Detecta automaticamente a página ativa se não foi especificada
        if (isset($config['activePage'])) {
            $this->activePage = $config['activePage'];
        } else {
            $this->activePage = $this->detectActivePage();
        }
    }
    
    /**
     * Detecta automaticamente qual é a página ativa baseada no arquivo atual
     */
    private function detectActivePage() {
        $currentFile = basename($_SERVER['PHP_SELF']);
        
        // Mapeamento de arquivos para IDs de página
        $pageMap = [
            'dashboard.php' => 'associados',
            'index.php' => 'associados',
            'associados.php' => 'associados',
            'funcionarios.php' => 'funcionarios',
            'comercial.php' => 'comercial',
            'financeiro.php' => 'financeiro',
            'auditoria.php' => 'auditoria',
            'presidencia.php' => 'presidencia',
            'relatorios.php' => 'relatorios',
            'documentos.php' => 'documentos'
        ];
        
        return $pageMap[$currentFile] ?? 'associados';
    }

    /**
     * Renderiza o CSS do componente
     */
    public function renderCSS() {
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
            
            :root {
                /* Cores ASSEGO Oficiais */
                --assego-blue: #003C8F;
                --assego-blue-dark: #002A66;
                --assego-blue-light: #E6F0FF;
                --assego-gold: #FFB800;
                --assego-gold-dark: #E5A200;
                --assego-gold-light: #FFF4E0;
                
                /* Cores Principais */
                --primary: var(--assego-blue);
                --primary-dark: var(--assego-blue-dark);
                --primary-light: var(--assego-blue-light);
                --primary-gradient: linear-gradient(135deg, var(--assego-blue) 0%, var(--assego-blue-dark) 100%);
                
                /* Cores Secundárias */
                --secondary: var(--assego-gold);
                --accent: var(--assego-gold);
                --danger: #DC2626;
                --success: #16A34A;
                --warning: var(--assego-gold);
                --info: #0EA5E9;
                
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
                --header-bg: rgba(255, 255, 255, 0.98);
                --backdrop-blur: blur(20px);
                
                /* Sombras */
                --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.06);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
                
                /* Transições */
                --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
                --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
                --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
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
                background-color: #FAFBFC;
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
                border-top: 3px solid var(--assego-gold);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                transition: all var(--transition-base);
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            
            .header-container::before {
                content: '';
                position: absolute;
                top: 3px;
                left: 0;
                right: 0;
                height: 1px;
                background: linear-gradient(90deg, 
                    transparent, 
                    var(--assego-gold), 
                    var(--assego-gold), 
                    transparent);
                opacity: 0.3;
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

            /* Logo */
            .logo-container {
                display: flex;
                align-items: center;
                gap: 12px;
                text-decoration: none;
                cursor: pointer;
                transition: transform var(--transition-base);
            }

            .logo-container:hover {
                transform: scale(1.02);
            }
            
            .logo-container:hover .logo-icon {
                box-shadow: 0 6px 20px rgba(0, 60, 143, 0.3),
                           0 0 0 2px rgba(255, 184, 0, 0.2);
            }

            /* Estilo da logo com imagem */
            .logo-icon {
                width: 42px !important;
                height: 42px !important;
                background: white !important;
                border-radius: 12px !important;
                padding: 4px !important;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(0, 60, 143, 0.2);
                position: relative;
                overflow: hidden;
                transition: all var(--transition-base);
            }

            .logo-icon img {
                width: 100% !important;
                height: 100% !important;
                object-fit: contain !important;
                filter: none !important;
            }
            
            /* Fallback quando não tem imagem */
            .logo-icon.logo-letter {
                background: var(--assego-blue) !important;
                color: var(--assego-gold);
                font-weight: 900;
                font-size: 20px;
                padding: 0 !important;
            }
            
            .logo-icon::after {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: linear-gradient(45deg, 
                    transparent, 
                    rgba(255, 184, 0, 0.4), 
                    transparent);
                transform: rotate(45deg) translateX(-100%);
                transition: transform 0.6s;
            }
            
            .logo-container:hover .logo-icon::after {
                transform: rotate(45deg) translateX(100%);
            }

            .logo-text-container {
                display: flex;
                flex-direction: column;
            }

            .logo-text {
                color: var(--assego-blue);
                font-size: 19px;
                font-weight: 800;
                letter-spacing: -0.025em;
                line-height: 1;
                transition: all var(--transition-base);
            }
            
            .logo-container:hover .logo-text {
                color: var(--assego-blue-dark);
                text-shadow: 0 0 20px rgba(255, 184, 0, 0.3);
            }

            .logo-subtitle {
                color: var(--gray-500);
                font-size: 11px;
                font-weight: 500;
                margin-top: 3px;
                letter-spacing: 0.025em;
                transition: all var(--transition-base);
            }
            
            .logo-container:hover .logo-subtitle {
                color: var(--assego-gold-dark);
            }

            /* Menu de Navegação */
            .nav-menu {
                display: flex;
                align-items: center;
                gap: 4px;
                flex: 1;
                padding: 0 20px;
                height: 100%;
            }

            .nav-item {
                position: relative;
                height: 100%;
                display: flex;
                align-items: center;
            }

            /* Linha inferior para aba ativa */
            .nav-item::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%) scaleX(0);
                width: calc(100% - 16px);
                height: 3px;
                background: var(--assego-gold);
                border-radius: 3px 3px 0 0;
                transition: transform var(--transition-base);
                box-shadow: 0 2px 4px rgba(255, 184, 0, 0.3);
            }

            .nav-item.active::after {
                transform: translateX(-50%) scaleX(1);
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
                border: 1px solid transparent;
            }

            .nav-link:hover {
                color: var(--assego-blue);
                background: linear-gradient(135deg, var(--assego-blue-light) 0%, rgba(255, 184, 0, 0.05) 100%);
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(0, 60, 143, 0.1);
            }

            /* Estado Ativo */
            .nav-link.active {
                color: var(--assego-blue);
                background: var(--assego-blue-light);
                border-color: var(--assego-gold);
                font-weight: 600;
            }

            .nav-link.active i {
                color: var(--assego-blue);
                transform: scale(1.1);
            }

            .nav-link.active span {
                color: var(--assego-blue);
                font-weight: 700;
            }

            /* Indicador de ativo */
            .nav-link.active::before {
                content: '';
                position: absolute;
                top: 6px;
                right: 6px;
                width: 5px;
                height: 5px;
                background: var(--assego-gold);
                border-radius: 50%;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(255, 184, 0, 0.7); }
                70% { box-shadow: 0 0 0 5px rgba(255, 184, 0, 0); }
                100% { box-shadow: 0 0 0 0 rgba(255, 184, 0, 0); }
            }

            .nav-link i {
                font-size: 16px;
                transition: all var(--transition-base);
            }

            .nav-link:hover i {
                transform: scale(1.1);
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
                width: 42px;
                height: 42px;
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
                background: var(--assego-blue-light);
                border-color: var(--assego-blue);
                color: var(--assego-blue);
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }

            /* Estados especiais do botão de notificação */
            .notification-btn.has-notifications {
                animation: gentleGlow 3s ease-in-out infinite;
            }

            @keyframes gentleGlow {
                0%, 100% { box-shadow: 0 2px 8px rgba(0, 60, 143, 0.1); }
                50% { box-shadow: 0 2px 8px rgba(255, 184, 0, 0.4); }
            }

            .notification-btn.active {
                background: var(--assego-blue-light) !important;
                border-color: var(--assego-blue) !important;
                color: var(--assego-blue) !important;
                transform: translateY(-2px);
                box-shadow: var(--shadow-md) !important;
            }

            .notification-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                min-width: 18px;
                height: 18px;
                background: var(--assego-gold);
                color: var(--assego-blue-dark);
                border-radius: 9px;
                font-size: 11px;
                font-weight: 700;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 5px;
                border: 2px solid var(--white);
                box-shadow: 0 2px 4px rgba(255, 184, 0, 0.3);
                animation: fadeInBounce 0.5s ease-out;
            }

            @keyframes fadeInBounce {
                0% { opacity: 0; transform: scale(0.3); }
                50% { transform: scale(1.1); }
                100% { opacity: 1; transform: scale(1); }
            }

            .notification-badge.pulse {
                animation: badgePulse 0.6s ease-out;
            }

            @keyframes badgePulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.3); }
                100% { transform: scale(1); }
            }

            /* Tooltip para o botão de notificação */
            .notification-btn::before {
                content: 'Notificações (Ctrl+N)';
                position: absolute;
                bottom: -40px;
                right: 0;
                background: var(--gray-900);
                color: var(--white);
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                white-space: nowrap;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: all 0.3s ease;
                z-index: 1001;
                pointer-events: none;
            }

            .notification-btn::after {
                content: '';
                position: absolute;
                bottom: -30px;
                right: 10px;
                border: 5px solid transparent;
                border-bottom-color: var(--gray-900);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
                pointer-events: none;
            }

            .notification-btn:hover::before,
            .notification-btn:hover::after {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
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
                background: var(--assego-blue-light);
                border-color: var(--assego-blue);
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
                background: var(--assego-blue);
                color: var(--assego-gold);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 14px;
                overflow: hidden;
                position: relative;
                box-shadow: 0 2px 8px rgba(0, 60, 143, 0.2);
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
                width: 10px;
                height: 10px;
                background: var(--success);
                border: 2px solid var(--white);
                border-radius: 50%;
            }

            /* Dropdown do Usuário */
            .user-dropdown {
                position: absolute;
                top: calc(100% + 12px);
                right: 0;
                background: var(--white);
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0, 60, 143, 0.15);
                min-width: 260px;
                padding: 8px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px) scale(0.95);
                transition: all var(--transition-slow);
                z-index: 1001;
                border: 1px solid var(--gray-100);
                border-top: 2px solid var(--assego-gold);
            }

            .user-dropdown.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }

            .user-dropdown-header {
                padding: 16px;
                background: linear-gradient(135deg, var(--assego-blue-light) 0%, #FFF4E0 100%);
                border-radius: 12px;
                margin-bottom: 8px;
                border: 1px solid rgba(0, 60, 143, 0.1);
            }

            .user-dropdown-name {
                font-weight: 700;
                color: var(--assego-blue);
                font-size: 15px;
            }

            .user-dropdown-email {
                font-size: 13px;
                color: var(--gray-600);
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
            }

            .dropdown-item::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 3px;
                background: var(--assego-gold);
                transform: translateX(-100%);
                transition: transform var(--transition-base);
            }

            .dropdown-item:hover {
                background: var(--assego-blue-light);
                color: var(--assego-blue);
                padding-left: 18px;
            }

            .dropdown-item:hover::before {
                transform: translateX(0);
            }

            .dropdown-item i {
                font-size: 16px;
                width: 20px;
                text-align: center;
                color: var(--assego-blue);
            }

            .dropdown-divider {
                height: 1px;
                background: var(--gray-100);
                margin: 8px 0;
            }

            /* Mobile Menu Toggle */
            .mobile-menu-toggle {
                display: none;
                width: 42px;
                height: 42px;
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
                background: var(--assego-blue-light);
                border-color: var(--assego-blue);
                color: var(--assego-blue);
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

            @media (max-width: 768px) {
                .notification-btn::before,
                .notification-btn::after {
                    display: none;
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
                border-left: 3px solid transparent;
            }

            .mobile-nav-item:hover {
                background: var(--assego-blue-light);
                color: var(--assego-blue);
                padding-left: 20px;
                border-left-color: var(--assego-gold);
            }

            .mobile-nav-item.active {
                background: var(--assego-blue-light);
                color: var(--assego-blue);
                font-weight: 700;
                border-left-color: var(--assego-gold);
                padding-left: 20px;
            }

            .mobile-nav-item.active::after {
                content: '';
                position: absolute;
                top: 50%;
                right: 16px;
                transform: translateY(-50%);
                width: 6px;
                height: 6px;
                background: var(--assego-gold);
                border-radius: 50%;
            }

            .mobile-nav-item.active i {
                color: var(--assego-blue);
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

            /* ===== NOTIFICAÇÃO DE SENHA PADRÃO ===== */
            .alerta-senha-padrao {
                position: fixed;
                top: calc(var(--header-height) + 10px);
                left: 50%;
                transform: translateX(-50%);
                width: 90%;
                max-width: 800px;
                background: linear-gradient(135deg, #FFF9E6 0%, #FFF4D6 100%);
                border: 2px solid var(--assego-gold);
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(255, 184, 0, 0.3), 
                            0 0 60px rgba(255, 184, 0, 0.1);
                z-index: 2000;
                animation: slideDownBounce 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                overflow: hidden;
            }

            @keyframes slideDownBounce {
                0% {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-100px);
                }
                60% {
                    transform: translateX(-50%) translateY(20px);
                }
                80% {
                    transform: translateX(-50%) translateY(-5px);
                }
                100% {
                    opacity: 1;
                    transform: translateX(-50%) translateY(0);
                }
            }

            .alerta-senha-container {
                padding: 20px 25px;
                display: flex;
                align-items: center;
                gap: 20px;
                position: relative;
            }

            .alerta-senha-icon-wrapper {
                flex-shrink: 0;
            }

            .alerta-senha-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, var(--assego-gold) 0%, #FFD700 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--assego-blue-dark);
                font-size: 28px;
                box-shadow: 0 4px 15px rgba(255, 184, 0, 0.4);
            }

            .alerta-senha-icon.pulse {
                animation: pulseShadow 2s infinite;
            }

            @keyframes pulseShadow {
                0% {
                    box-shadow: 0 4px 15px rgba(255, 184, 0, 0.4),
                                0 0 0 0 rgba(255, 184, 0, 0.7);
                }
                50% {
                    box-shadow: 0 4px 15px rgba(255, 184, 0, 0.4),
                                0 0 0 15px rgba(255, 184, 0, 0);
                }
                100% {
                    box-shadow: 0 4px 15px rgba(255, 184, 0, 0.4),
                                0 0 0 0 rgba(255, 184, 0, 0);
                }
            }

            .alerta-senha-content {
                flex: 1;
                padding-right: 20px;
            }

            .alerta-senha-titulo {
                font-size: 18px;
                font-weight: 700;
                color: var(--assego-blue-dark);
                margin-bottom: 8px;
                display: flex;
                align-items: center;
            }

            .alerta-senha-mensagem {
                font-size: 14px;
                color: #5A4A00;
                margin-bottom: 10px;
                line-height: 1.5;
            }

            .alerta-senha-instrucoes {
                font-size: 13px;
                color: #6B5500;
                background: rgba(255, 255, 255, 0.6);
                padding: 8px 12px;
                border-radius: 8px;
                border-left: 3px solid var(--assego-gold);
                display: flex;
                align-items: flex-start;
            }

            .alerta-senha-instrucoes strong {
                color: var(--assego-blue-dark);
                font-weight: 600;
            }

            .alerta-senha-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
                align-items: flex-end;
            }

            .btn-alerta-perfil {
                background: linear-gradient(135deg, var(--assego-blue) 0%, var(--assego-blue-dark) 100%);
                color: white;
                border: none;
                border-radius: 10px;
                padding: 10px 20px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                box-shadow: 0 4px 15px rgba(0, 60, 143, 0.3);
                white-space: nowrap;
            }

            .btn-alerta-perfil:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 60, 143, 0.4);
                background: linear-gradient(135deg, var(--assego-blue-dark) 0%, var(--assego-blue) 100%);
            }

            .btn-alerta-fechar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.9);
                border: 2px solid var(--assego-gold);
                color: #8B7000;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 16px;
            }

            .btn-alerta-fechar:hover {
                background: white;
                color: var(--assego-blue-dark);
                transform: rotate(90deg);
                border-color: var(--assego-blue);
            }

            .alerta-senha-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 4px;
                background: linear-gradient(90deg, 
                    var(--assego-gold) 0%, 
                    #FFD700 50%, 
                    var(--assego-gold) 100%);
                animation: progressMove 3s linear infinite;
                width: 100%;
            }

            @keyframes progressMove {
                0% {
                    background-position: 0% 50%;
                }
                100% {
                    background-position: 100% 50%;
                }
            }

            /* Animação de saída */
            .alerta-senha-padrao.hiding {
                animation: slideUpFade 0.4s ease-out forwards;
            }

            @keyframes slideUpFade {
                to {
                    opacity: 0;
                    transform: translateX(-50%) translateY(-20px);
                }
            }

            @keyframes shakeAlert {
                0%, 100% { transform: translateX(-50%) translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-50%) translateX(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(-50%) translateX(5px); }
            }

            /* Responsivo para o alerta */
            @media (max-width: 768px) {
                .alerta-senha-padrao {
                    width: 95%;
                    top: calc(var(--header-height) + 5px);
                }
                
                .alerta-senha-container {
                    flex-direction: column;
                    text-align: center;
                    padding: 15px;
                }
                
                .alerta-senha-content {
                    padding-right: 0;
                }
                
                .alerta-senha-actions {
                    flex-direction: row;
                    width: 100%;
                    justify-content: space-between;
                    margin-top: 10px;
                }
                
                .alerta-senha-instrucoes {
                    font-size: 12px;
                    text-align: left;
                }
                
                .btn-alerta-perfil {
                    flex: 1;
                    justify-content: center;
                }
            }

            @media (max-width: 480px) {
                .alerta-senha-titulo {
                    font-size: 16px;
                }
                
                .alerta-senha-mensagem {
                    font-size: 13px;
                }
                
                .alerta-senha-icon {
                    width: 50px;
                    height: 50px;
                    font-size: 24px;
                }
            }

            /* ===== SISTEMA DE NOTIFICAÇÕES - CSS INTEGRADO ===== */

            /* Painel principal */
            .painel-notificacoes {
                position: fixed;
                width: 380px;
                max-height: 600px;
                background: var(--white);
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 60, 143, 0.15),
                            0 0 0 1px rgba(0, 60, 143, 0.05);
                border-top: 3px solid var(--assego-gold);
                z-index: 2000;
                
                opacity: 0;
                visibility: hidden;
                transform: translateY(-20px) scale(0.95);
                transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }

            .painel-notificacoes.show {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }

            /* Header do painel */
            .painel-header {
                padding: 20px 24px 16px;
                border-bottom: 1px solid var(--gray-100);
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: linear-gradient(135deg, var(--assego-blue-light) 0%, #FFF4E0 100%);
                border-radius: 16px 16px 0 0;
            }

            .painel-titulo {
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 700;
                color: var(--assego-blue);
                font-size: 16px;
            }

            .painel-titulo i {
                font-size: 18px;
                color: var(--assego-gold);
            }

            .badge-contador {
                background: var(--assego-gold);
                color: var(--assego-blue-dark);
                font-size: 12px;
                font-weight: 700;
                padding: 4px 8px;
                border-radius: 12px;
                min-width: 24px;
                text-align: center;
                box-shadow: 0 2px 8px rgba(255, 184, 0, 0.3);
            }

            .painel-acoes {
                display: flex;
                gap: 8px;
            }

            .btn-painel-acao {
                width: 32px;
                height: 32px;
                border: none;
                background: var(--white);
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s ease;
                color: var(--gray-600);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .btn-painel-acao:hover {
                background: var(--assego-blue);
                color: var(--white);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 60, 143, 0.3);
            }

            /* Filtros */
            .painel-filtros {
                padding: 16px 24px;
                display: flex;
                gap: 8px;
                border-bottom: 1px solid var(--gray-100);
                background: var(--gray-50);
            }

            .filtro-btn {
                padding: 8px 16px;
                border: none;
                background: var(--white);
                border-radius: 20px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                color: var(--gray-600);
                border: 1px solid var(--gray-200);
                flex: 1;
                text-align: center;
            }

            .filtro-btn:hover {
                background: var(--assego-blue-light);
                border-color: var(--assego-blue);
                color: var(--assego-blue);
            }

            .filtro-btn.active {
                background: var(--assego-blue);
                color: var(--white);
                border-color: var(--assego-blue);
                box-shadow: 0 2px 8px rgba(0, 60, 143, 0.3);
            }

            /* Conteúdo do painel */
            .painel-conteudo {
                max-height: 400px;
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: var(--gray-300) transparent;
            }

            .painel-conteudo::-webkit-scrollbar {
                width: 6px;
            }

            .painel-conteudo::-webkit-scrollbar-track {
                background: transparent;
            }

            .painel-conteudo::-webkit-scrollbar-thumb {
                background: var(--gray-300);
                border-radius: 3px;
            }

            .painel-conteudo::-webkit-scrollbar-thumb:hover {
                background: var(--gray-400);
            }

            /* Itens de notificação */
            .notificacao-item {
                display: flex;
                align-items: flex-start;
                gap: 16px;
                padding: 16px 24px;
                border-bottom: 1px solid var(--gray-100);
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
                min-height: 80px;
                animation: slideInNotification 0.3s ease-out;
            }

            @keyframes slideInNotification {
                from { opacity: 0; transform: translateX(-20px); }
                to { opacity: 1; transform: translateX(0); }
            }

            .notificacao-item:last-child {
                border-bottom: none;
            }

            .notificacao-item:hover {
                background: var(--gray-50);
                transform: translateX(4px);
            }

            .notificacao-item.nao-lida {
                background: linear-gradient(90deg, 
                    rgba(255, 184, 0, 0.02) 0%, 
                    rgba(255, 255, 255, 1) 8%);
                border-left: 3px solid var(--assego-gold);
            }

            .notificacao-item.nao-lida .notif-titulo {
                font-weight: 700;
            }

            .notificacao-item.lida {
                opacity: 0.7;
            }

            .notificacao-item.lida .notif-titulo {
                font-weight: 500;
            }

            .notificacao-item.priority-high {
                border-left: 3px solid #fd7e14;
            }

            .notificacao-item.priority-urgent {
                border-left: 3px solid #dc3545;
                animation: urgentPulse 2s infinite;
            }

            @keyframes urgentPulse {
                0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.3); }
                50% { box-shadow: 0 0 0 4px rgba(220, 53, 69, 0); }
            }

            /* Ícone da notificação */
            .notif-icon {
                width: 40px;
                height: 40px;
                border-radius: 12px;
                background: var(--gray-100);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                flex-shrink: 0;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            /* Conteúdo da notificação */
            .notif-content {
                flex: 1;
                min-width: 0;
            }

            .notif-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 6px;
                gap: 12px;
            }

            .notif-titulo {
                font-size: 14px;
                color: var(--gray-900);
                line-height: 1.3;
                flex: 1;
            }

            .notif-tempo {
                font-size: 11px;
                color: var(--gray-500);
                font-weight: 500;
                white-space: nowrap;
                flex-shrink: 0;
            }

            .notif-mensagem {
                font-size: 13px;
                color: var(--gray-700);
                line-height: 1.4;
                margin-bottom: 8px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .notif-associado,
            .notif-autor {
                font-size: 12px;
                color: var(--gray-600);
                margin-bottom: 4px;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .notif-associado i,
            .notif-autor i {
                font-size: 10px;
                color: var(--assego-blue);
                width: 12px;
            }

            .notif-prioridade {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                margin-top: 4px;
            }

            .notif-prioridade.alta {
                background: rgba(253, 126, 20, 0.1);
                color: #fd7e14;
            }

            .notif-prioridade.urgente {
                background: rgba(220, 53, 69, 0.1);
                color: #dc3545;
            }

            /* Indicador de não lida */
            .notif-indicator {
                position: absolute;
                top: 20px;
                right: 20px;
                width: 8px;
                height: 8px;
                background: var(--assego-gold);
                border-radius: 50%;
                box-shadow: 0 0 0 2px var(--white),
                            0 2px 4px rgba(255, 184, 0, 0.4);
                animation: pulse 2s infinite;
            }

            /* Footer do painel */
            .painel-footer {
                padding: 16px 24px;
                border-top: 1px solid var(--gray-100);
                background: var(--gray-50);
                border-radius: 0 0 16px 16px;
            }

            .btn-ver-todas {
                width: 100%;
                padding: 12px 20px;
                background: linear-gradient(135deg, var(--assego-blue) 0%, var(--assego-blue-dark) 100%);
                color: var(--white);
                border: none;
                border-radius: 10px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                font-size: 14px;
            }

            .btn-ver-todas:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 60, 143, 0.4);
            }

            /* Estados especiais */
            .loading-notificacoes {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 40px 20px;
                color: var(--gray-600);
                gap: 16px;
            }

            .spinner-notificacoes {
                width: 32px;
                height: 32px;
                border: 3px solid var(--gray-200);
                border-top: 3px solid var(--assego-blue);
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            .notificacoes-vazio {
                text-align: center;
                padding: 40px 20px;
                color: var(--gray-600);
            }

            .vazio-icon {
                width: 60px;
                height: 60px;
                margin: 0 auto 16px;
                background: var(--gray-100);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                color: var(--gray-400);
            }

            .notificacoes-vazio h4 {
                font-size: 16px;
                margin-bottom: 8px;
                color: var(--gray-700);
            }

            .notificacoes-vazio p {
                font-size: 14px;
                margin: 0;
            }

            .notificacoes-erro {
                text-align: center;
                padding: 40px 20px;
                color: var(--gray-600);
            }

            .erro-icon {
                width: 60px;
                height: 60px;
                margin: 0 auto 16px;
                background: rgba(220, 53, 69, 0.1);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                color: #dc3545;
            }

            .btn-tentar-novamente {
                margin-top: 16px;
                padding: 8px 16px;
                background: var(--assego-blue);
                color: var(--white);
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                transition: all 0.2s ease;
            }

            .btn-tentar-novamente:hover {
                background: var(--assego-blue-dark);
                transform: translateY(-1px);
            }

            /* Toast de feedback */
            .toast-notificacao {
                position: fixed;
                top: 100px;
                right: 30px;
                background: var(--white);
                padding: 16px 20px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                border-left: 4px solid var(--info);
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
                font-weight: 500;
                z-index: 3000;
                
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
            }

            .toast-notificacao.show {
                opacity: 1;
                transform: translateX(0);
            }

            .toast-notificacao.toast-success {
                border-left-color: var(--success);
                color: var(--success);
            }

            .toast-notificacao.toast-error {
                border-left-color: var(--danger);
                color: var(--danger);
            }

            .toast-notificacao.toast-info {
                border-left-color: var(--info);
                color: var(--info);
            }

            /* Responsivo para notificações */
            @media (max-width: 768px) {
                .painel-notificacoes {
                    width: calc(100vw - 40px);
                    max-width: 380px;
                    left: 20px !important;
                    right: 20px;
                    margin: 0 auto;
                }
                
                .notificacao-item {
                    padding: 12px 16px;
                    gap: 12px;
                }
                
                .notif-icon {
                    width: 36px;
                    height: 36px;
                    font-size: 14px;
                }
                
                .painel-header,
                .painel-filtros,
                .painel-footer {
                    padding-left: 16px;
                    padding-right: 16px;
                }
                
                .toast-notificacao {
                    right: 20px;
                    left: 20px;
                    width: auto;
                }
            }

            @media (max-width: 480px) {
                .painel-notificacoes {
                    width: calc(100vw - 20px);
                    left: 10px !important;
                    right: 10px;
                }
                
                .filtro-btn {
                    font-size: 12px;
                    padding: 6px 12px;
                }
                
                .notif-mensagem {
                    -webkit-line-clamp: 2;
                }
            }
        </style>
        <?php
    }

    /**
     * Gera os itens de navegação baseado em permissões
     */
    private function getNavigationItems() {
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
    private function getUserInitials($nome) {
        if (empty($nome)) return '?';
        $parts = explode(' ', trim($nome));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }
        return strtoupper(substr($nome, 0, 2));
    }

    /**
     * Verifica se um item está ativo
     */
    private function isItemActive($itemId) {
        return $this->activePage === $itemId;
    }

    /**
     * Renderiza o JavaScript
     */
    public function renderJS() {
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

                // Header scroll effect
                let lastScroll = 0;
                const header = document.querySelector('.header-container');
                
                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset;
                    
                    if (currentScroll > 100) {
                        header.style.boxShadow = '0 4px 20px rgba(0, 60, 143, 0.1)';
                        header.style.borderTopColor = 'var(--assego-gold-dark)';
                    } else {
                        header.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
                        header.style.borderTopColor = 'var(--assego-gold)';
                    }
                    
                    lastScroll = currentScroll;
                });

                // ===== NOTIFICAÇÃO DE SENHA PADRÃO =====
                const alertaSenha = document.getElementById('alertaSenhaPadrao');
                if (alertaSenha) {
                    // Auto-fechar após 30 segundos
                    setTimeout(() => {
                        if (alertaSenha && alertaSenha.style.display !== 'none') {
                            fecharAlertaSenha();
                        }
                    }, 30000);
                    
                    // Shake a cada 10 segundos para chamar atenção
                    setInterval(() => {
                        if (alertaSenha && alertaSenha.style.display !== 'none' && !alertaSenha.classList.contains('hiding')) {
                            alertaSenha.style.animation = 'none';
                            setTimeout(() => {
                                alertaSenha.style.animation = 'shakeAlert 0.5s ease-in-out';
                            }, 10);
                        }
                    }, 10000);
                }

                // ===== SISTEMA DE NOTIFICAÇÕES =====
                console.log('🔔 Iniciando Sistema de Notificações ASSEGO...');
                
                // Aguarda um pouco para garantir que tudo foi carregado
                setTimeout(() => {
                    window.notificacaoSystem = new NotificacaoSystem();
                }, 500);
            });

            // Função para garantir que o conteúdo não fique sob o header
            window.addEventListener('load', function() {
                const header = document.querySelector('.header-container');
                if (header) {
                    const headerHeight = header.offsetHeight;
                    document.body.style.paddingTop = headerHeight + 'px';
                }
            });

            // ===== FUNÇÕES DO ALERTA DE SENHA PADRÃO =====
            function fecharAlertaSenha() {
                const alerta = document.getElementById('alertaSenhaPadrao');
                if (alerta) {
                    alerta.classList.add('hiding');
                    
                    setTimeout(() => {
                        alerta.style.display = 'none';
                    }, 400);
                    
                    console.log('✓ Alerta de senha fechado temporariamente');
                }
            }

            function irParaPerfil() {
                window.location.href = 'perfil.php';
            }

            // ===== SISTEMA DE NOTIFICAÇÕES - CLASSE PRINCIPAL =====
            class NotificacaoSystem {
                constructor() {
                    this.isInitialized = false;
                    this.updateInterval = null;
                    this.refreshRate = 60000; // 1 minuto
                    this.panelAberto = false;
                    this.notificacoes = [];
                    this.totalNaoLidas = 0;
                    
                    this.init();
                }
                
                init() {
                    if (this.isInitialized) return;
                    
                    console.log('🔔 Iniciando Sistema de Notificações ASSEGO...');
                    
                    // Verifica se os elementos existem
                    this.botaoNotificacao = document.getElementById('notificationBtn');
                    this.badgeNotificacao = this.botaoNotificacao?.querySelector('.notification-badge');
                    
                    if (!this.botaoNotificacao) {
                        console.log('⚠️ Botão de notificação não encontrado. Sistema desabilitado.');
                        return;
                    }
                    
                    this.criarPainelNotificacoes();
                    this.configurarEventos();
                    this.buscarNotificacoes();
                    this.iniciarAtualizacaoAutomatica();
                    
                    this.isInitialized = true;
                    console.log('✅ Sistema de Notificações inicializado com sucesso!');
                }
                
                criarPainelNotificacoes() {
                    // Remove painel existente se houver
                    const painelExistente = document.getElementById('painelNotificacoes');
                    if (painelExistente) {
                        painelExistente.remove();
                    }
                    
                    // Cria o painel
                    const painel = document.createElement('div');
                    painel.id = 'painelNotificacoes';
                    painel.className = 'painel-notificacoes';
                    painel.innerHTML = `
                        <div class="painel-header">
                            <div class="painel-titulo">
                                <i class="fas fa-bell"></i>
                                <span>Notificações</span>
                                <span class="badge-contador" id="badgeContador">0</span>
                            </div>
                            <div class="painel-acoes">
                                <button class="btn-painel-acao" onclick="notificacaoSystem.marcarTodasLidas()" title="Marcar todas como lidas">
                                    <i class="fas fa-check-double"></i>
                                </button>
                                <button class="btn-painel-acao" onclick="notificacaoSystem.atualizarNotificacoes()" title="Atualizar">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="painel-filtros">
                            <button class="filtro-btn active" data-filtro="todas">Todas</button>
                            <button class="filtro-btn" data-filtro="financeiro">Financeiro</button>
                            <button class="filtro-btn" data-filtro="observacoes">Observações</button>
                        </div>
                        
                        <div class="painel-conteudo" id="painelConteudo">
                            <div class="loading-notificacoes">
                                <div class="spinner-notificacoes"></div>
                                <span>Carregando notificações...</span>
                            </div>
                        </div>
                        
                        <div class="painel-footer">
                            <button class="btn-ver-todas" onclick="window.location.href='notificacoes.php'">
                                Ver todas as notificações
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    `;
                    
                    // Adiciona o painel ao body
                    document.body.appendChild(painel);
                    
                    // Configura filtros
                    this.configurarFiltros();
                }
                
                configurarEventos() {
                    // Click no botão de notificação
                    this.botaoNotificacao.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.togglePainel();
                    });
                    
                    // Fecha painel ao clicar fora
                    document.addEventListener('click', (e) => {
                        const painel = document.getElementById('painelNotificacoes');
                        if (painel && !painel.contains(e.target) && !this.botaoNotificacao.contains(e.target)) {
                            this.fecharPainel();
                        }
                    });
                    
                    // Previne fechamento ao clicar dentro do painel
                    document.addEventListener('click', (e) => {
                        if (e.target.closest('#painelNotificacoes')) {
                            e.stopPropagation();
                        }
                    });
                    
                    // Atalho de teclado (Ctrl + N)
                    document.addEventListener('keydown', (e) => {
                        if (e.ctrlKey && e.key === 'n') {
                            e.preventDefault();
                            this.togglePainel();
                        }
                    });
                    
                    // Visibilidade da página para pausar/retomar atualizações
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) {
                            this.pararAtualizacaoAutomatica();
                        } else {
                            this.iniciarAtualizacaoAutomatica();
                            this.buscarNotificacoes(); // Atualiza imediatamente
                        }
                    });
                }
                
                configurarFiltros() {
                    const filtros = document.querySelectorAll('.filtro-btn');
                    filtros.forEach(filtro => {
                        filtro.addEventListener('click', () => {
                            // Remove active de todos
                            filtros.forEach(f => f.classList.remove('active'));
                            // Adiciona active no clicado
                            filtro.classList.add('active');
                            
                            const tipoFiltro = filtro.dataset.filtro;
                            this.filtrarNotificacoes(tipoFiltro);
                        });
                    });
                }
                
                togglePainel() {
                    if (this.panelAberto) {
                        this.fecharPainel();
                    } else {
                        this.abrirPainel();
                    }
                }
                
                abrirPainel() {
                    const painel = document.getElementById('painelNotificacoes');
                    if (!painel) return;
                    
                    // Posiciona o painel
                    this.posicionarPainel();
                    
                    // Mostra o painel
                    painel.classList.add('show');
                    this.botaoNotificacao.classList.add('active');
                    this.panelAberto = true;
                    
                    // Busca notificações atualizadas
                    this.buscarNotificacoes();
                    
                    console.log('📱 Painel de notificações aberto');
                }
                
                fecharPainel() {
                    const painel = document.getElementById('painelNotificacoes');
                    if (!painel) return;
                    
                    painel.classList.remove('show');
                    this.botaoNotificacao.classList.remove('active');
                    this.panelAberto = false;
                    
                    console.log('📱 Painel de notificações fechado');
                }
                
                posicionarPainel() {
                    const painel = document.getElementById('painelNotificacoes');
                    const botao = this.botaoNotificacao;
                    
                    if (!painel || !botao) return;
                    
                    const rect = botao.getBoundingClientRect();
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
                    
                    // Posição inicial (canto inferior direito do botão)
                    let top = rect.bottom + scrollTop + 8;
                    let left = rect.right + scrollLeft - 380; // 380px é a largura do painel
                    
                    // Verifica se não sai da tela
                    if (left < 20) left = 20;
                    if (left + 380 > window.innerWidth - 20) {
                        left = window.innerWidth - 400;
                    }
                    
                    painel.style.top = top + 'px';
                    painel.style.left = left + 'px';
                }
                
                async buscarNotificacoes() {
                    try {
                        const response = await fetch('../api/notificacoes.php?acao=buscar&limite=20');
                        const data = await response.json();
                        
                        if (data.status === 'success') {
                            this.notificacoes = data.data;
                            this.atualizarPainel();
                            
                            // Busca contagem separadamente para maior precisão
                            this.buscarContagem();
                            
                            console.log(`📊 ${this.notificacoes.length} notificações carregadas`);
                        } else {
                            console.error('❌ Erro ao buscar notificações:', data.message);
                            this.mostrarErro('Erro ao carregar notificações');
                        }
                    } catch (error) {
                        console.error('❌ Erro de rede ao buscar notificações:', error);
                        this.mostrarErro('Erro de conexão');
                    }
                }
                
                async buscarContagem() {
                    try {
                        const response = await fetch('../api/notificacoes.php?acao=contar');
                        const data = await response.json();
                        
                        if (data.status === 'success') {
                            this.totalNaoLidas = data.total;
                            this.atualizarBadge();
                        }
                    } catch (error) {
                        console.error('❌ Erro ao buscar contagem:', error);
                    }
                }
                
                atualizarBadge() {
                    if (!this.badgeNotificacao) {
                        // Cria o badge se não existir
                        this.badgeNotificacao = document.createElement('span');
                        this.badgeNotificacao.className = 'notification-badge';
                        this.botaoNotificacao.appendChild(this.badgeNotificacao);
                    }
                    
                    if (this.totalNaoLidas > 0) {
                        this.badgeNotificacao.textContent = this.totalNaoLidas > 9 ? '9+' : this.totalNaoLidas;
                        this.badgeNotificacao.style.display = 'flex';
                        
                        // Adiciona classe visual
                        this.botaoNotificacao.classList.add('has-notifications');
                        
                        // Adiciona animação de pulse
                        this.badgeNotificacao.classList.add('pulse');
                        setTimeout(() => {
                            this.badgeNotificacao?.classList.remove('pulse');
                        }, 1000);
                    } else {
                        this.badgeNotificacao.style.display = 'none';
                        this.botaoNotificacao.classList.remove('has-notifications');
                    }
                    
                    // Atualiza contador no painel
                    const badgeContador = document.getElementById('badgeContador');
                    if (badgeContador) {
                        badgeContador.textContent = this.totalNaoLidas;
                    }
                }
                
                atualizarPainel() {
                    const conteudo = document.getElementById('painelConteudo');
                    if (!conteudo) return;
                    
                    if (this.notificacoes.length === 0) {
                        conteudo.innerHTML = `
                            <div class="notificacoes-vazio">
                                <div class="vazio-icon">
                                    <i class="fas fa-bell-slash"></i>
                                </div>
                                <h4>Nenhuma notificação</h4>
                                <p>Você está em dia! Não há notificações pendentes.</p>
                            </div>
                        `;
                        return;
                    }
                    
                    const html = this.notificacoes.map(notif => this.criarItemNotificacao(notif)).join('');
                    conteudo.innerHTML = html;
                }
                
                criarItemNotificacao(notif) {
                    const prioridadeClass = notif.prioridade === 'ALTA' ? 'priority-high' : 
                                           notif.prioridade === 'URGENTE' ? 'priority-urgent' : '';
                    
                    return `
                        <div class="notificacao-item ${notif.lida ? 'lida' : 'nao-lida'} ${prioridadeClass}" 
                             data-id="${notif.id}" 
                             data-tipo="${notif.tipo}"
                             onclick="notificacaoSystem.marcarComoLida(${notif.id})">
                            
                            <div class="notif-icon" style="color: ${notif.cor}">
                                <i class="${notif.icone}"></i>
                            </div>
                            
                            <div class="notif-content">
                                <div class="notif-header">
                                    <span class="notif-titulo">${notif.titulo}</span>
                                    <span class="notif-tempo">${notif.tempo_atras}</span>
                                </div>
                                
                                <div class="notif-mensagem">${notif.mensagem}</div>
                                
                                ${notif.associado_nome ? `
                                    <div class="notif-associado">
                                        <i class="fas fa-user"></i>
                                        ${notif.associado_nome} ${notif.associado_cpf ? `(${notif.associado_cpf})` : ''}
                                    </div>
                                ` : ''}
                                
                                ${notif.criado_por_nome ? `
                                    <div class="notif-autor">
                                        <i class="fas fa-user-edit"></i>
                                        Por: ${notif.criado_por_nome}
                                    </div>
                                ` : ''}
                                
                                ${notif.prioridade === 'ALTA' || notif.prioridade === 'URGENTE' ? `
                                    <div class="notif-prioridade ${notif.prioridade.toLowerCase()}">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        ${notif.prioridade}
                                    </div>
                                ` : ''}
                            </div>
                            
                            ${!notif.lida ? '<div class="notif-indicator"></div>' : ''}
                        </div>
                    `;
                }
                
                async marcarComoLida(notificacaoId) {
                    try {
                        const formData = new FormData();
                        formData.append('acao', 'marcar_lida');
                        formData.append('notificacao_id', notificacaoId);
                        
                        const response = await fetch('../api/notificacoes.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.status === 'success') {
                            // Atualiza o item na lista local
                            const notif = this.notificacoes.find(n => n.id == notificacaoId);
                            if (notif) {
                                notif.lida = true;
                            }
                            
                            // Atualiza visualmente
                            const item = document.querySelector(`[data-id="${notificacaoId}"]`);
                            if (item) {
                                item.classList.add('lida');
                                item.classList.remove('nao-lida');
                                const indicator = item.querySelector('.notif-indicator');
                                if (indicator) indicator.remove();
                            }
                            
                            // Atualiza contagem
                            this.buscarContagem();
                            
                            console.log('✅ Notificação marcada como lida:', notificacaoId);
                        } else {
                            console.error('❌ Erro ao marcar como lida:', data.message);
                        }
                    } catch (error) {
                        console.error('❌ Erro ao marcar notificação:', error);
                    }
                }
                
                async marcarTodasLidas() {
                    if (this.totalNaoLidas === 0) {
                        this.mostrarToast('Não há notificações não lidas', 'info');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('acao', 'marcar_todas_lidas');
                        
                        const response = await fetch('../api/notificacoes.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.status === 'success') {
                            // Atualiza todas as notificações localmente
                            this.notificacoes.forEach(notif => {
                                notif.lida = true;
                            });
                            
                            // Atualiza visualmente
                            document.querySelectorAll('.notificacao-item.nao-lida').forEach(item => {
                                item.classList.add('lida');
                                item.classList.remove('nao-lida');
                                const indicator = item.querySelector('.notif-indicator');
                                if (indicator) indicator.remove();
                            });
                            
                            // Atualiza contagem
                            this.totalNaoLidas = 0;
                            this.atualizarBadge();
                            
                            this.mostrarToast(data.message, 'success');
                            console.log('✅ Todas as notificações marcadas como lidas');
                        } else {
                            console.error('❌ Erro ao marcar todas como lidas:', data.message);
                            this.mostrarToast('Erro ao marcar notificações', 'error');
                        }
                    } catch (error) {
                        console.error('❌ Erro ao marcar todas as notificações:', error);
                        this.mostrarToast('Erro de conexão', 'error');
                    }
                }
                
                filtrarNotificacoes(filtro) {
                    const items = document.querySelectorAll('.notificacao-item');
                    
                    items.forEach(item => {
                        const tipo = item.dataset.tipo;
                        let mostrar = true;
                        
                        switch (filtro) {
                            case 'financeiro':
                                mostrar = tipo === 'ALTERACAO_FINANCEIRO';
                                break;
                            case 'observacoes':
                                mostrar = tipo === 'NOVA_OBSERVACAO';
                                break;
                            case 'todas':
                            default:
                                mostrar = true;
                                break;
                        }
                        
                        item.style.display = mostrar ? 'flex' : 'none';
                    });
                }
                
                iniciarAtualizacaoAutomatica() {
                    this.pararAtualizacaoAutomatica();
                    
                    this.updateInterval = setInterval(() => {
                        if (!document.hidden) {
                            this.buscarContagem();
                            
                            // Se o painel estiver aberto, atualiza as notificações também
                            if (this.panelAberto) {
                                this.buscarNotificacoes();
                            }
                        }
                    }, this.refreshRate);
                    
                    console.log(`🔄 Atualização automática iniciada (${this.refreshRate/1000}s)`);
                }
                
                pararAtualizacaoAutomatica() {
                    if (this.updateInterval) {
                        clearInterval(this.updateInterval);
                        this.updateInterval = null;
                    }
                }
                
                atualizarNotificacoes() {
                    this.buscarNotificacoes();
                    this.buscarContagem();
                    this.mostrarToast('Notificações atualizadas', 'success');
                }
                
                mostrarErro(mensagem) {
                    const conteudo = document.getElementById('painelConteudo');
                    if (conteudo) {
                        conteudo.innerHTML = `
                            <div class="notificacoes-erro">
                                <div class="erro-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h4>Erro ao carregar</h4>
                                <p>${mensagem}</p>
                                <button class="btn-tentar-novamente" onclick="notificacaoSystem.buscarNotificacoes()">
                                    <i class="fas fa-redo"></i>
                                    Tentar novamente
                                </button>
                            </div>
                        `;
                    }
                }
                
                mostrarToast(mensagem, tipo = 'info') {
                    // Remove toasts existentes
                    document.querySelectorAll('.toast-notificacao').forEach(toast => toast.remove());
                    
                    const toast = document.createElement('div');
                    toast.className = `toast-notificacao toast-${tipo}`;
                    toast.innerHTML = `
                        <i class="fas fa-${tipo === 'success' ? 'check-circle' : tipo === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${mensagem}</span>
                    `;
                    
                    document.body.appendChild(toast);
                    
                    // Mostra o toast
                    setTimeout(() => toast.classList.add('show'), 100);
                    
                    // Remove após 3 segundos
                    setTimeout(() => {
                        toast.classList.remove('show');
                        setTimeout(() => toast.remove(), 300);
                    }, 3000);
                }
                
                destruir() {
                    this.pararAtualizacaoAutomatica();
                    const painel = document.getElementById('painelNotificacoes');
                    if (painel) painel.remove();
                    
                    console.log('🧹 Sistema de notificações limpo');
                }
            }

            // Cleanup ao sair da página
            window.addEventListener('beforeunload', () => {
                if (window.notificacaoSystem) {
                    window.notificacaoSystem.destruir();
                }
            });
        </script>
        <?php
    }

    /**
     * Renderiza o componente
     */
    public function render() {
        // NOVA VERIFICAÇÃO DE SENHA PADRÃO
        $mostrarAlertaSenha = false;
        $authInstance = null;
        
        // Verifica se deve mostrar alerta de senha padrão
        if (class_exists('Auth')) {
            $authInstance = new Auth();
            if ($authInstance->isUsingSenhaDefault() && !$authInstance->foiNotificadoSenhaPadrao()) {
                $mostrarAlertaSenha = true;
                $authInstance->setNotificadoSenhaPadrao();
            }
        }
        
        $navigationItems = $this->getNavigationItems();
        $userInitials = $this->getUserInitials($this->usuario['nome']);
        
        // Debug para verificar página ativa
        echo "<!-- Página Ativa: " . $this->activePage . " -->";
        ?>
        
        <!-- NOTIFICAÇÃO DE SENHA PADRÃO -->
        <?php if ($mostrarAlertaSenha): ?>
        <div id="alertaSenhaPadrao" class="alerta-senha-padrao">
            <div class="alerta-senha-container">
                <div class="alerta-senha-icon-wrapper">
                    <div class="alerta-senha-icon pulse">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                
                <div class="alerta-senha-content">
                    <div class="alerta-senha-titulo">
                        <i class="fas fa-lock me-2"></i>
                        Atenção: Você está usando a senha padrão!
                    </div>
                    <div class="alerta-senha-mensagem">
                        Por questões de segurança, é <strong>obrigatório</strong> alterar sua senha padrão.
                    </div>
                    <div class="alerta-senha-instrucoes">
                        <i class="fas fa-info-circle me-1"></i>
                        Como alterar: Clique no seu <strong>nome</strong> no canto superior direito → 
                        <strong>Meu Perfil</strong> → <strong>Alterar Senha</strong>
                    </div>
                </div>
                
                <div class="alerta-senha-actions">
                    <button onclick="irParaPerfil()" class="btn-alerta-perfil">
                        <i class="fas fa-user-cog me-1"></i>
                        Ir para Meu Perfil
                    </button>
                    <button onclick="fecharAlertaSenha()" class="btn-alerta-fechar" title="Fechar temporariamente">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <div class="alerta-senha-progress"></div>
        </div>
        <?php endif; ?>
        
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
                        <?php 
                        // Verifica se existe a imagem da logo
                        $logoPath = 'img/logoassego.png';
                        if (file_exists($logoPath)): 
                        ?>
                            <div class="logo-icon">
                                <img src="<?php echo $logoPath; ?>" alt="Logo ASSEGO" class="logo-img">
                            </div>
                        <?php else: ?>
                            <div class="logo-icon logo-letter">A</div>
                        <?php endif; ?>
                        <div class="logo-text-container">
                            <span class="logo-text">ASSEGO</span>
                            <span class="logo-subtitle">Sistema de Gestão</span>
                        </div>
                    </a>

                    <!-- Desktop Navigation -->
                    <nav class="nav-menu">
                        <?php foreach ($navigationItems as $item): ?>
                            <?php $isActive = $this->isItemActive($item['id']); ?>
                            <div class="nav-item <?php echo $isActive ? 'active' : ''; ?>">
                                <a href="<?php echo htmlspecialchars($item['href']); ?>" 
                                   class="nav-link <?php echo $isActive ? 'active' : ''; ?>">
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
                                <div class="user-dropdown-name"><?php echo htmlspecialchars($this->usuario['nome']); ?></div>
                                <div class="user-dropdown-email"><?php echo htmlspecialchars($this->usuario['email'] ?? 'usuario@assego.com.br'); ?></div>
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
                <div class="user-dropdown-name"><?php echo htmlspecialchars($this->usuario['nome']); ?></div>
                <div class="user-dropdown-email"><?php echo htmlspecialchars($this->usuario['cargo'] ?? 'Funcionário'); ?></div>
            </div>
            
            <?php foreach ($navigationItems as $item): ?>
                <?php $isActive = $this->isItemActive($item['id']); ?>
                <a href="<?php echo htmlspecialchars($item['href']); ?>" 
                   class="mobile-nav-item <?php echo $isActive ? 'active' : ''; ?>">
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
    public static function create($config = []) {
        $header = new self($config);
        return $header;
    }
}

/**
 * Função helper para renderização rápida
 */
function renderHeader($config = []) {
    $header = new HeaderComponent($config);
    $header->renderCSS();
    $header->render();
    $header->renderJS();
}
?>