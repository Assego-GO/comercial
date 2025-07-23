/**
 * Login Script - Efeitos Avançados e Interatividade
 */

// Estado global da aplicação (ultra-otimizada)
const LoginApp = {
    isLoading: false,
    isLowEndDevice: false,
    
    // Inicialização (ultra-otimizada)
    init() {
        this.detectLowEndDevice();
        this.setupEventListeners();
        
        // Só criar estrelas se não for dispositivo fraco
        if (!this.isLowEndDevice) {
            this.createMinimalStars();
        }
        
        this.setupFormValidation();
        this.setupAnimations();
        console.log('🚀 Login App iniciado - Modo Ultra-Leve');
    },
    
    // Detecta dispositivos fracos
    detectLowEndDevice() {
        // Detecta RAM baixa ou dispositivo móvel
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const hasLowRAM = navigator.deviceMemory && navigator.deviceMemory <= 4;
        const hasSlowConnection = navigator.connection && navigator.connection.effectiveType && navigator.connection.effectiveType.includes('2g');
        
        this.isLowEndDevice = isMobile || hasLowRAM || hasSlowConnection || window.innerWidth < 1024;
        
        if (this.isLowEndDevice) {
            document.body.classList.add('low-end-device');
            console.log('Modo economizado ativado para dispositivo fraco');
        }
    },
    
    // Event Listeners (otimizado)
    setupEventListeners() {
        // Form submission
        const form = document.getElementById('loginForm');
        if (form) {
            form.addEventListener('submit', this.handleFormSubmit.bind(this));
        }
        
        // Input focus effects (simplificado)
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', this.handleInputFocus.bind(this));
            input.addEventListener('blur', this.handleInputBlur.bind(this));
            input.addEventListener('input', this.handleInputChange.bind(this));
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', this.handleKeyPress.bind(this));
        
        // Removido mouse movement (muito pesado)
    },
    
    // Criação de partículas flutuantes (otimizado)
    createParticles() {
        const container = document.getElementById('particles-container');
        if (!container) return;
        
        // Reduzido drasticamente para performance
        const particleCount = window.innerWidth > 768 ? 15 : 8;
        
        for (let i = 0; i < particleCount; i++) {
            setTimeout(() => {
                this.createSingleParticle();
            }, Math.random() * 3000);
        }
    },
    
    createSingleParticle() {
        const container = document.getElementById('particles-container');
        if (!container) return;
        
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        // Posicionamento aleatório
        const x = Math.random() * window.innerWidth;
        const y = Math.random() * window.innerHeight;
        const size = Math.random() * 3 + 1;
        const duration = Math.random() * 10 + 15;
        
        particle.style.left = x + 'px';
        particle.style.top = y + 'px';
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.animationDuration = duration + 's';
        particle.style.animationDelay = Math.random() * 5 + 's';
        
        // Cores azuis da galáxia
        const colors = ['#0ea5e9', '#38bdf8', '#7dd3fc', '#0284c7'];
        const color = colors[Math.floor(Math.random() * colors.length)];
        particle.style.background = color;
        particle.style.boxShadow = `0 0 10px ${color}`;
        
        container.appendChild(particle);
        
        // Remover após animação
        setTimeout(() => {
            if (particle.parentNode) {
                particle.parentNode.removeChild(particle);
            }
        }, (duration + 5) * 1000);
    },
    
    // Efeito de estrelas flutuantes
    startStarsEffect() {
        const container = document.getElementById('stars-container');
        if (!container) return;
        
        // Criar estrelas fixas de fundo
        this.createBackgroundStars();
        
        const createStar = () => {
            const star = document.createElement('div');
            star.className = 'star';
            
            // Tamanhos aleatórios
            const sizes = ['small', 'medium', 'large'];
            const size = sizes[Math.floor(Math.random() * sizes.length)];
            star.classList.add(size);
            
            // Alguns piscam
            if (Math.random() > 0.7) {
                star.classList.add('twinkle');
            }
            
            // Posição horizontal aleatória
            const x = Math.random() * window.innerWidth;
            star.style.left = x + 'px';
            star.style.top = window.innerHeight + 10 + 'px';
            
            // Duração fixa para movimento consistente
            star.style.animationDuration = '12s';
            star.style.animationDelay = Math.random() * 2 + 's';
            
            container.appendChild(star);
            
            // Remover após animação completa
            setTimeout(() => {
                if (star.parentNode) {
                    star.parentNode.removeChild(star);
                }
            }, 14000); // 12s animação + 2s buffer
        };
        
        const createShootingStar = () => {
            const shootingStar = document.createElement('div');
            shootingStar.className = 'shooting-star';
            
            // Posições aleatórias em toda a tela
            const side = Math.floor(Math.random() * 4); // 0: topo, 1: direita, 2: baixo, 3: esquerda
            let startX, startY;
            
            switch(side) {
                case 0: // Do topo
                    startX = Math.random() * window.innerWidth;
                    startY = -50;
                    break;
                case 1: // Da direita
                    startX = window.innerWidth + 50;
                    startY = Math.random() * window.innerHeight;
                    break;
                case 2: // Do baixo
                    startX = Math.random() * window.innerWidth;
                    startY = window.innerHeight + 50;
                    break;
                case 3: // Da esquerda
                    startX = -50;
                    startY = Math.random() * window.innerHeight;
                    break;
            }
            
            shootingStar.style.left = startX + 'px';
            shootingStar.style.top = startY + 'px';
            
            // Direção aleatória
            const angle = Math.random() * 360;
            shootingStar.style.setProperty('--shoot-angle', angle + 'deg');
            
            container.appendChild(shootingStar);
            
            // Remover após animação
            setTimeout(() => {
                if (shootingStar.parentNode) {
                    shootingStar.parentNode.removeChild(shootingStar);
                }
            }, 3000);
        };
        
        // Criar estrelas iniciais (reduzido)
        for (let i = 0; i < 8; i++) {
            setTimeout(() => {
                createStar();
            }, Math.random() * 2000);
        }
        
        // Criar estrelas continuamente (menos frequente)
        setInterval(() => {
            createStar();
        }, 2000); // Era 800ms, agora 2s
        
        // Criar estrelas cadentes menos frequentes
        setInterval(() => {
            createShootingStar();
        }, 8000); // Era 5s, agora 8s
    },
    
    // Criar estrelas fixas de fundo (otimizado)
    createBackgroundStars() {
        const container = document.getElementById('stars-container');
        if (!container) return;
        
        // Reduzido para melhor performance
        for (let i = 0; i < 60; i++) {
            const star = document.createElement('div');
            star.className = 'star-fixed';
            
            const x = Math.random() * window.innerWidth;
            const y = Math.random() * window.innerHeight;
            const size = Math.random() * 1.5 + 0.5;
            const opacity = Math.random() * 0.7 + 0.3;
            
            star.style.left = x + 'px';
            star.style.top = y + 'px';
            star.style.width = size + 'px';
            star.style.height = size + 'px';
            star.style.background = 'white';
            star.style.borderRadius = '50%';
            star.style.opacity = opacity;
            star.style.position = 'absolute';
            star.style.boxShadow = `0 0 ${size * 2}px rgba(255, 255, 255, ${opacity * 0.6})`;
            
            // Menos estrelas piscando para performance
            if (Math.random() > 0.9) {
                star.style.animation = 'twinkle 4s ease-in-out infinite alternate';
                star.style.animationDelay = Math.random() * 4 + 's';
            }
            
            container.appendChild(star);
        }
        
        // Apenas algumas estrelas brilhantes
        for (let i = 0; i < 8; i++) {
            const brightStar = document.createElement('div');
            brightStar.className = 'star-fixed bright';
            
            const x = Math.random() * window.innerWidth;
            const y = Math.random() * window.innerHeight;
            const size = Math.random() * 2 + 1;
            
            brightStar.style.left = x + 'px';
            brightStar.style.top = y + 'px';
            brightStar.style.width = size + 'px';
            brightStar.style.height = size + 'px';
            brightStar.style.background = 'white';
            brightStar.style.borderRadius = '50%';
            brightStar.style.opacity = '0.8';
            brightStar.style.position = 'absolute';
            brightStar.style.boxShadow = `0 0 ${size * 3}px rgba(255, 255, 255, 0.6)`;
            brightStar.style.animation = 'twinkle 3s ease-in-out infinite alternate';
            brightStar.style.animationDelay = Math.random() * 3 + 's';
            
            container.appendChild(brightStar);
        }
    },
    
    // Efeitos de galáxia
    startGalaxyEffects() {
        this.createNebulas();
        this.createGalaxySpiral();
    },
    
    createNebulas() {
        const container = document.getElementById('nebulas-container') || document.body;
        
        const nebulas = [
            { size: 300, x: '10%', y: '20%', color: '#1e293b', delay: 0 },
            { size: 250, x: '80%', y: '60%', color: '#0f172a', delay: 7 },
            { size: 200, x: '60%', y: '80%', color: '#334155', delay: 14 },
            { size: 180, x: '20%', y: '70%', color: '#475569', delay: 21 },
            { size: 220, x: '70%', y: '10%', color: '#1e1b4b', delay: 28 },
        ];
        
        nebulas.forEach((nebula, index) => {
            const element = document.createElement('div');
            element.className = 'nebula';
            element.style.width = nebula.size + 'px';
            element.style.height = (nebula.size * 0.6) + 'px';
            element.style.left = nebula.x;
            element.style.top = nebula.y;
            element.style.background = `radial-gradient(ellipse, ${nebula.color}15, transparent)`;
            element.style.filter = 'blur(60px)';
            element.style.opacity = '0.15';
            element.style.position = 'fixed';
            element.style.borderRadius = '50%';
            element.style.animation = `float 25s ease-in-out infinite`;
            element.style.animationDelay = nebula.delay + 's';
            element.style.zIndex = '2';
            
            container.appendChild(element);
        });
    },
    
    createGalaxySpiral() {
        const container = document.getElementById('galaxy-container') || document.body;
        
        // Criar braços espirais da galáxia
        for (let i = 0; i < 3; i++) {
            const arm = document.createElement('div');
            arm.className = 'galaxy-arm';
            arm.style.position = 'fixed';
            arm.style.width = '100%';
            arm.style.height = '100%';
            arm.style.background = `
                radial-gradient(ellipse 1200px 400px at center, 
                    transparent 40%, 
                    rgba(14, 165, 233, 0.1) 50%, 
                    transparent 60%),
                radial-gradient(ellipse 800px 200px at center, 
                    transparent 30%, 
                    rgba(56, 189, 248, 0.05) 50%, 
                    transparent 70%)
            `;
            arm.style.transform = `rotate(${i * 120}deg)`;
            arm.style.animation = `rotate 60s linear infinite`;
            arm.style.animationDelay = `${i * 20}s`;
            arm.style.zIndex = '1';
            
            container.appendChild(arm);
        }
    },
    
    // Validação de formulário (simplificada)
    setupFormValidation() {
        // Validação básica apenas
        const emailInput = document.getElementById('email');
        const senhaInput = document.getElementById('senha');
        
        if (emailInput) {
            emailInput.addEventListener('blur', () => {
                this.validateEmail(emailInput);
            });
        }
        
        if (senhaInput) {
            senhaInput.addEventListener('input', () => {
                this.validatePassword(senhaInput);
            });
        }
    },
    
    validateEmail(input) {
        const email = input.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = emailRegex.test(email);
        
        // Validação visual simplificada
        if (!isValid && email) {
            input.style.borderColor = '#ef4444';
        } else if (isValid) {
            input.style.borderColor = '#22c55e';
        } else {
            input.style.borderColor = '';
        }
        
        return isValid;
    },
    
    validatePassword(input) {
        const password = input.value;
        const isValid = password.length >= 4;
        
        // Validação visual simplificada
        if (!isValid && password) {
            input.style.borderColor = '#ef4444';
        } else if (isValid) {
            input.style.borderColor = '#22c55e';
        } else {
            input.style.borderColor = '';
        }
        
        return isValid;
    },
    
    // Manipulação de eventos (simplificado)
    handleFormSubmit(e) {
        e.preventDefault();
        
        const emailInput = document.getElementById('email');
        const senhaInput = document.getElementById('senha');
        
        const emailValid = this.validateEmail(emailInput);
        const senhaValid = this.validatePassword(senhaInput);
        
        if (emailValid && senhaValid) {
            this.showLoading();
            
            // Simular delay para demonstração
            setTimeout(() => {
                // Submeter formulário real
                e.target.submit();
            }, 1000); // Reduzido de 1500ms
        } else {
            // Removido shake animation (pesado)
        }
    },
    
    handleInputFocus(e) {
        const wrapper = e.target.closest('.form-group');
        wrapper.classList.add('focused');
        // Removido createFocusParticle (pesado)
    },
    
    handleInputBlur(e) {
        const wrapper = e.target.closest('.form-group');
        wrapper.classList.remove('focused');
    },
    
    handleInputChange(e) {
        // Validação em tempo real
        if (e.target.type === 'email') {
            this.validateEmail(e.target);
        } else if (e.target.type === 'password') {
            this.validatePassword(e.target);
        }
    },
    
    // Removido handleResize (pesado)
    
    handleKeyPress(e) {
        // Enter para submeter
        if (e.key === 'Enter' && !e.shiftKey) {
            // Simplificado
        }
        
        // Esc para limpar
        if (e.key === 'Escape') {
            this.clearForm();
        }
    },
    
    // Removido handleMouseMove (muito pesado)
    
    // Removido createFocusParticle (pesado)
    
    showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.remove('hidden');
            // Removido createLoadingParticles (pesado)
        }
    },
    
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    },
    
    // Removido createLoadingParticles (pesado)
    
    // Removido showShakeAnimation (pesado)
    
    clearForm() {
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.value = '';
            input.classList.remove('border-red-500', 'border-green-500');
        });
        
        // Limpar mensagens de erro
        const errorMessages = document.querySelectorAll('.error-message');
        errorMessages.forEach(msg => msg.remove());
    },
    
    setupAnimations() {
        // Animações simplificadas apenas se não for dispositivo fraco
        if (this.isLowEndDevice) return;
        
        // Fade-in simples sem observer pesado
        const elements = document.querySelectorAll('.form-group, .btn-login');
        elements.forEach((el, index) => {
            el.style.opacity = '0';
            setTimeout(() => {
                el.style.opacity = '1';
                el.style.transition = 'opacity 0.3s ease';
            }, index * 100 + 200);
        });
    }
};

// Função para toggle de senha (simplificada)
function togglePassword() {
    const passwordInput = document.getElementById('senha');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput && toggleIcon) {
        const isPassword = passwordInput.type === 'password';
        
        passwordInput.type = isPassword ? 'text' : 'password';
        toggleIcon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
}

// Inicialização quando DOM estiver pronto (simplificado)
document.addEventListener('DOMContentLoaded', () => {
    LoginApp.init();
    // Removido EasterEggs (desnecessário)
    
    // Auto-hide alerts (simplificado)
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-error');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        });
    }, 4000); // Reduzido de 5000ms
});

// Export para uso global
window.LoginApp = LoginApp;
window.togglePassword = togglePassword;