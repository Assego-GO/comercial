<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Associado - ASSEGO</title>

    <!-- Favicon -->
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
            
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.24);
            --shadow-lg: 0 10px 20px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.24);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.12), 0 8px 16px rgba(0,0,0,0.24);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--gray-100);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
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

        .logo-text {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.5px;
        }

        .system-subtitle {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin: 0;
            font-weight: 500;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 4rem 2rem;
            text-align: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 24px;
            font-size: 0.875rem;
            backdrop-filter: blur(10px);
        }

        /* Content Section */
        .content-section {
            flex: 1;
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .section-title {
            text-align: center;
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 3rem;
        }

        /* Cards Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .service-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .service-card:hover::before {
            transform: scaleX(1);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .service-icon i {
            position: relative;
            z-index: 1;
        }

        .service-icon::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 20px;
            background: inherit;
            opacity: 0.2;
            transform: scale(1.2);
            filter: blur(20px);
        }

        /* Cores específicas para cada card */
        .service-card.new-member .service-icon {
            background: linear-gradient(135deg, var(--success) 0%, #00a847 100%);
            color: var(--white);
        }

        .service-card.update-data .service-icon {
            background: linear-gradient(135deg, var(--info) 0%, #0095a8 100%);
            color: var(--white);
        }

        .service-card.leave .service-icon {
            background: linear-gradient(135deg, var(--warning) 0%, var(--secondary-dark) 100%);
            color: var(--white);
        }

        .service-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .service-description {
            color: var(--gray-600);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .service-features {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem 0;
        }

        .service-features li {
            padding: 0.5rem 0;
            color: var(--gray-700);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .service-features li i {
            color: var(--success);
            font-size: 0.75rem;
        }

        .service-button {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .service-card.new-member .service-button {
            background: var(--success);
            color: var(--white);
        }

        .service-card.new-member .service-button:hover {
            background: #00a847;
            transform: scale(1.02);
        }

        .service-card.update-data .service-button {
            background: var(--info);
            color: var(--white);
        }

        .service-card.update-data .service-button:hover {
            background: #0095a8;
            transform: scale(1.02);
        }

        .service-card.leave .service-button {
            background: var(--warning);
            color: var(--white);
        }

        .service-card.leave .service-button:hover {
            background: var(--secondary-dark);
            transform: scale(1.02);
        }

        /* Help Section */
        .help-section {
            background: var(--white);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .help-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .help-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .help-text {
            color: var(--gray-600);
            margin-bottom: 1.5rem;
        }

        .help-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .help-button {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--gray-200);
            background: var(--white);
            color: var(--gray-700);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .help-button:hover {
            background: var(--gray-100);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .service-card {
            animation: fadeInUp 0.6s ease backwards;
        }

        .service-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .service-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .service-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .content-section {
                padding: 2rem 1rem;
            }

            .services-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .help-buttons {
                flex-direction: column;
            }

            .help-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <div class="logo-section">
                <div style="width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800;">
                    A
                </div>
                <div>
                    <h1 class="logo-text">ASSEGO</h1>
                    <p class="system-subtitle">Portal do Associado</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">Bem-vindo ao Portal do Associado</h1>
            <p class="hero-subtitle">Escolha uma das opções abaixo para continuar</p>
            <div class="hero-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Sistema seguro e confiável</span>
            </div>
        </div>
    </section>

    <!-- Content Section -->
    <section class="content-section">
        <h2 class="section-title">Como podemos ajudá-lo hoje?</h2>
        
        <div class="services-grid">
            <!-- Card Novo Associado -->
            <div class="service-card new-member" onclick="window.location.href='cadastro.php'">
                <div class="service-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="service-title">Quero me Associar</h3>
                <p class="service-description">
                    Faça parte da ASSEGO! Preencha o formulário de cadastro e aproveite todos os benefícios de ser um associado.
                </p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Cadastro 100% online</li>
                    <li><i class="fas fa-check-circle"></i> Processo rápido e seguro</li>
                    <li><i class="fas fa-check-circle"></i> Benefícios exclusivos</li>
                </ul>
                <button class="service-button">
                    <i class="fas fa-arrow-right"></i>
                    Iniciar Cadastro
                </button>
            </div>

            <!-- Card Recadastramento -->
            <div class="service-card update-data" onclick="window.location.href='recadastramentoForm.php'">
                <div class="service-icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <h3 class="service-title">Atualizar Meus Dados</h3>
                <p class="service-description">
                    Mantenha suas informações sempre atualizadas. Altere dados pessoais, endereço, contatos e muito mais.
                </p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Alteração de dados pessoais</li>
                    <li><i class="fas fa-check-circle"></i> Atualização de documentos</li>
                    <li><i class="fas fa-check-circle"></i> Inclusão de dependentes</li>
                </ul>
                <button class="service-button">
                    <i class="fas fa-arrow-right"></i>
                    Atualizar Cadastro
                </button>
            </div>

            <!-- Card Desfiliação -->
            <div class="service-card leave" onclick="window.location.href='desfiliacao.php'">
                <div class="service-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <h3 class="service-title">Solicitar Desfiliação</h3>
                <p class="service-description">
                    Precisa se desfiliar? Faça sua solicitação de forma simples e acompanhe todo o processo online.
                </p>
                <ul class="service-features">
                    <li><i class="fas fa-check-circle"></i> Processo transparente</li>
                    <li><i class="fas fa-check-circle"></i> Acompanhamento online</li>
                    <li><i class="fas fa-check-circle"></i> Sem burocracia</li>
                </ul>
                <button class="service-button">
                    <i class="fas fa-arrow-right"></i>
                    Solicitar Desfiliação
                </button>
            </div>
        </div>

        <!-- Help Section -->
        <div class="help-section">
            <div class="help-icon">
                <i class="fas fa-question"></i>
            </div>
            <h3 class="help-title">Precisa de Ajuda?</h3>
            <p class="help-text">
                Nossa equipe está pronta para atendê-lo. Entre em contato através dos canais abaixo:
            </p>
            <div class="help-buttons">
                <a href="tel:+556232010900" class="help-button">
                    <i class="fas fa-phone"></i>
                    (62) 3201-0900
                </a>
                <a href="https://wa.me/556232010900" class="help-button">
                    <i class="fab fa-whatsapp"></i>
                    WhatsApp
                </a>
                <a href="mailto:contato@assego.org.br" class="help-button">
                    <i class="fas fa-envelope"></i>
                    E-mail
                </a>
            </div>
        </div>
    </section>

    <script>
        // Adiciona efeito de ripple nos cards
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('mouseenter', function(e) {
                const ripple = document.createElement('div');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255,255,255,0.1)';
                ripple.style.width = '100px';
                ripple.style.height = '100px';
                ripple.style.left = e.offsetX - 50 + 'px';
                ripple.style.top = e.offsetY - 50 + 'px';
                ripple.style.animation = 'ripple 0.6s ease-out';
                ripple.style.pointerEvents = 'none';
                
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Animação de ripple
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                from {
                    transform: scale(0);
                    opacity: 1;
                }
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>