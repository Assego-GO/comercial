/**
 * Login Script - Efeitos Avançados e Interatividade
 */

// Estado global da aplicação
const LoginApp = {
    isLoading: false,
    particles: [],
    
    // Inicialização
    init() {
        this.setupEventListeners();
        this.createParticles();
        this.startStarsEffect();
        this.startGalaxyEffects();
        this.setupFormValidation();
        this.setupAnimations();
        console.log('🚀 Login App iniciado com sucesso!');
    },
    
    // Event Listeners
    setupEventListeners() {
        // Form submission
        const form = document.getElementById('loginForm');
        if (form) {
            form.addEventListener('submit', this.handleFormSubmit.bind(this));
        }
        
        // Input focus effects
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', this.handleInputFocus.bind(this));
            input.addEventListener('blur', this.handleInputBlur.bind(this));
            input.addEventListener('input', this.handleInputChange.bind(this));
        });
        
        // Window resize
        window.addEventListener('resize', this.handleResize.bind(this));
        
        // Keyboard shortcuts
        document.addEventListener('keydown', this.handleKeyPress.bind(this));
        
        // Mouse movement for parallax
        document.addEventListener('mousemove', this.handleMouseMove.bind(this));
    },
    
    // Criação de partículas flutuantes
    createParticles() {
        const container = document.getElementById('particles-container');
        if (!container) return;
        
        const particleCount = window.innerWidth > 768 ? 50 : 25;
        
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
        
        // Criar estrelas iniciais
        for (let i = 0; i < 15; i++) {
            setTimeout(() => {
                createStar();
            }, Math.random() * 2000);
        }
        
        // Criar estrelas continuamente
        setInterval(() => {
            createStar();
        }, 800);
        
        // Criar estrelas cadentes a cada 5 segundos
        setInterval(() => {
            createShootingStar();
        }, 5000);
    },
    
    // Criar estrelas fixas de fundo
    createBackgroundStars() {
        const container = document.getElementById('stars-container');
        if (!container) return;
        
        // Estrelas pequenas e distantes
        for (let i = 0; i < 200; i++) {
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
            star.style.boxShadow = `0 0 ${size * 2}px rgba(255, 255, 255, ${opacity * 0.8})`;
            
            if (Math.random() > 0.85) {
                star.style.animation = 'twinkle 4s ease-in-out infinite alternate';
                star.style.animationDelay = Math.random() * 4 + 's';
            }
            
            container.appendChild(star);
        }
        
        // Algumas estrelas maiores e mais brilhantes
        for (let i = 0; i < 20; i++) {
            const brightStar = document.createElement('div');
            brightStar.className = 'star-fixed bright';
            
            const x = Math.random() * window.innerWidth;
            const y = Math.random() * window.innerHeight;
            const size = Math.random() * 3 + 2;
            
            brightStar.style.left = x + 'px';
            brightStar.style.top = y + 'px';
            brightStar.style.width = size + 'px';
            brightStar.style.height = size + 'px';
            brightStar.style.background = 'white';
            brightStar.style.borderRadius = '50%';
            brightStar.style.opacity = '0.9';
            brightStar.style.position = 'absolute';
            brightStar.style.boxShadow = `0 0 ${size * 4}px rgba(255, 255, 255, 0.8), 0 0 ${size * 8}px rgba(255, 255, 255, 0.4)`;
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
    
    // Validação de formulário
    setupFormValidation() {
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
        
        this.updateFieldValidation(input, isValid, 'Email inválido');
        return isValid;
    },
    
    validatePassword(input) {
        const password = input.value;
        const isValid = password.length >= 4;
        
        this.updateFieldValidation(input, isValid, 'Senha muito curta');
        return isValid;
    },
    
    updateFieldValidation(input, isValid, message) {
        const wrapper = input.closest('.form-group');
        if (!wrapper) return;
        
        // Remover mensagens anteriores
        const existingError = wrapper.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }
        
        if (!isValid && input.value) {
            // Adicionar classe de erro
            input.classList.add('border-red-500', 'shake');
            
            // Criar mensagem de erro
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message text-red-400 text-xs mt-1 flex items-center animate-pulse';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle mr-1"></i>${message}`;
            wrapper.appendChild(errorDiv);
            
            // Remover classe de shake após animação
            setTimeout(() => {
                input.classList.remove('shake');
            }, 500);
        } else {
            // Remover classe de erro
            input.classList.remove('border-red-500');
            
            if (isValid && input.value) {
                // Adicionar classe de sucesso
                input.classList.add('border-green-500');
                
                // Ícone de sucesso
                const successIcon = document.createElement('div');
                successIcon.className = 'success-icon absolute right-12 top-1/2 transform -translate-y-1/2 text-green-400';
                successIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                wrapper.querySelector('.input-wrapper').appendChild(successIcon);
                
                setTimeout(() => {
                    if (successIcon.parentNode) {
                        successIcon.parentNode.removeChild(successIcon);
                    }
                    input.classList.remove('border-green-500');
                }, 2000);
            }
        }
    },
    
    // Manipulação de eventos
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
            }, 1500);
        } else {
            this.showShakeAnimation();
        }
    },
    
    handleInputFocus(e) {
        const wrapper = e.target.closest('.form-group');
        wrapper.classList.add('focused');
        
        // Criar partícula no foco
        this.createFocusParticle(e.target);
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
    
    handleResize() {
        // Recriar partículas no redimensionamento
        const container = document.getElementById('particles-container');
        if (container) {
            container.innerHTML = '';
            this.createParticles();
        }
    },
    
    handleKeyPress(e) {
        // Enter para submeter
        if (e.key === 'Enter' && !e.shiftKey) {
            const form = document.getElementById('loginForm');
            if (document.activeElement.tagName !== 'BUTTON') {
                // form.dispatchEvent(new Event('submit'));
            }
        }
        
        // Esc para limpar
        if (e.key === 'Escape') {
            this.clearForm();
        }
    },
    
    handleMouseMove(e) {
        // Efeito parallax 3D melhorado
        const container = document.querySelector('.login-container');
        if (!container) return;
        
        const rect = container.getBoundingClientRect();
        const x = (e.clientX - rect.left - rect.width / 2) / rect.width;
        const y = (e.clientY - rect.top - rect.height / 2) / rect.height;
        
        const rotateX = y * -10; // -10deg a 10deg
        const rotateY = x * 15;  // -15deg a 15deg
        const translateZ = Math.abs(x * y) * 20; // Efeito de profundidade
        
        container.style.transform = `
            perspective(1000px) 
            rotateX(${rotateX}deg) 
            rotateY(${rotateY}deg) 
            translateZ(${translateZ}px)
            scale(${1 + Math.abs(x * y) * 0.05})
        `;
        
        // Reset suave quando mouse sai
        container.addEventListener('mouseleave', () => {
            container.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateZ(0px) scale(1)';
        });
    },
    
    // Efeitos visuais
    createFocusParticle(input) {
        const rect = input.getBoundingClientRect();
        const particle = document.createElement('div');
        particle.className = 'absolute w-2 h-2 bg-sky-400 rounded-full pointer-events-none';
        particle.style.left = (rect.left + rect.width / 2) + 'px';
        particle.style.top = (rect.top + rect.height / 2) + 'px';
        particle.style.boxShadow = '0 0 10px #0ea5e9';
        particle.style.zIndex = '1000';
        
        document.body.appendChild(particle);
        
        // Animar partícula
        particle.animate([
            { 
                transform: 'translate(-50%, -50%) scale(0)', 
                opacity: 1 
            },
            { 
                transform: 'translate(-50%, -50%) scale(1)', 
                opacity: 0.8 
            },
            { 
                transform: 'translate(-50%, -50%) scale(0)', 
                opacity: 0 
            }
        ], {
            duration: 800,
            easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
        }).onfinish = () => {
            if (particle.parentNode) {
                particle.parentNode.removeChild(particle);
            }
        };
    },
    
    showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.remove('hidden');
            
            // Adicionar partículas de loading
            this.createLoadingParticles();
        }
    },
    
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    },
    
    createLoadingParticles() {
        const overlay = document.getElementById('loadingOverlay');
        if (!overlay) return;
        
        for (let i = 0; i < 10; i++) {
            setTimeout(() => {
                const particle = document.createElement('div');
                particle.className = 'absolute w-1 h-1 bg-sky-400 rounded-full animate-ping';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 2 + 's';
                
                overlay.appendChild(particle);
                
                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.parentNode.removeChild(particle);
                    }
                }, 3000);
            }, i * 100);
        }
    },
    
    showShakeAnimation() {
        const container = document.querySelector('.login-container');
        if (container) {
            container.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
                container.style.animation = '';
            }, 500);
        }
    },
    
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
        // Observador de interseção para animações
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });
        
        // Observar elementos animáveis
        const elements = document.querySelectorAll('.form-group, .btn-login');
        elements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
            observer.observe(el);
        });
        
        // Delay sequencial para elementos
        elements.forEach((el, index) => {
            setTimeout(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, index * 100 + 300);
        });
    }
};

// Função para toggle de senha
function togglePassword() {
    const passwordInput = document.getElementById('senha');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput && toggleIcon) {
        const isPassword = passwordInput.type === 'password';
        
        passwordInput.type = isPassword ? 'text' : 'password';
        toggleIcon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
        
        // Efeito de rotação no ícone
        toggleIcon.style.transform = 'rotateY(180deg)';
        setTimeout(() => {
            toggleIcon.style.transform = 'rotateY(0deg)';
        }, 200);
        
        // Criar partícula de feedback
        LoginApp.createFocusParticle(passwordInput);
    }
}

// Funções utilitárias
const Utils = {
    // Debounce para otimização
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Throttle para eventos frequentes
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    },
    
    // Criar elemento com classes
    createElement(tag, classes, content) {
        const element = document.createElement(tag);
        if (classes) element.className = classes;
        if (content) element.innerHTML = content;
        return element;
    }
};

// Easter Eggs
const EasterEggs = {
    konamiCode: [38, 38, 40, 40, 37, 39, 37, 39, 66, 65],
    userInput: [],
    
    init() {
        document.addEventListener('keydown', this.handleKonami.bind(this));
        document.addEventListener('dblclick', this.handleDoubleClick.bind(this));
    },
    
    handleKonami(e) {
        this.userInput.push(e.keyCode);
        this.userInput = this.userInput.slice(-this.konamiCode.length);
        
        if (JSON.stringify(this.userInput) === JSON.stringify(this.konamiCode)) {
            this.activateKonamiMode();
        }
    },
    
    handleDoubleClick(e) {
        if (e.target.classList.contains('logo')) {
            this.activatePartyMode();
        }
    },
    
    activateKonamiMode() {
        console.log('🎮 Konami Code ativado!');
        document.body.style.filter = 'hue-rotate(180deg)';
        
        setTimeout(() => {
            document.body.style.filter = '';
        }, 3000);
    },
    
    activatePartyMode() {
        console.log('🎉 Party Mode ativado!');
        for (let i = 0; i < 20; i++) {
            setTimeout(() => {
                LoginApp.createSingleParticle();
            }, i * 100);
        }
    }
};

// Inicialização quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    LoginApp.init();
    EasterEggs.init();
    
    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-error');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 300);
        });
    }, 5000);
});

// Prevenção de FOUC (Flash of Unstyled Content)
window.addEventListener('load', () => {
    document.body.style.opacity = '1';
});

// Export para uso global
window.LoginApp = LoginApp;
window.togglePassword = togglePassword;