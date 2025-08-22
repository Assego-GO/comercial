<?php
/**
 * Componente Header Premium do Sistema ASSEGO
 * components/Header.php
 * VERSÃO ATUALIZADA COM SISTEMA DE NOTIFICAÇÃO DE SENHA PADRÃO
 * Versão com cores oficiais ASSEGO: Azul Royal (#003C8F) e Dourado (#FFB800)
 * Detecção automática de página ativa + Alerta de senha padrão
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

                // Notifications
                const notificationBtn = document.getElementById('notificationBtn');
                if (notificationBtn) {
                    notificationBtn.addEventListener('click', function() {
                        console.log('Notificações clicadas');
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