// KMKZ IPTV JavaScript

// Smooth scrolling for anchor links
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('a[href^="#"]');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                const offsetTop = targetSection.offsetTop - 80; // Account for fixed navbar
                
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Navbar background on scroll
    const navbar = document.querySelector('.custom-navbar');
    let lastScrollY = window.scrollY;
    let rafId = 0;

    function updateNavbarBackground() {
        rafId = 0;
        if (!navbar) return;
        const y = lastScrollY;
        navbar.style.background = y > 50 ? 'rgba(0, 0, 0, 0.9)' : 'rgba(0, 0, 0, 0.2)';
    }

    window.addEventListener('scroll', function() {
        lastScrollY = window.scrollY;
        if (!rafId) {
            rafId = window.requestAnimationFrame(updateNavbarBackground);
        }
    }, { passive: true });
    
    // Animate elements on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animateElements = document.querySelectorAll('.feature-card, .pricing-card, .pricing-section .glass-card, .testimonial-card, .loyalty-card');
    animateElements.forEach(el => observer.observe(el));
    
    // Counter animation for stats
    const statNumbers = document.querySelectorAll('.stat-number');
    
    const animateCounter = (element, target, duration = 2000) => {
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            
            // Format the number based on the original text
            const originalText = element.textContent;
            if (originalText.includes('K+')) {
                element.textContent = Math.floor(current) + 'K+';
            } else if (originalText.includes('%')) {
                element.textContent = Math.floor(current * 10) / 10 + '%';
            } else if (originalText.includes('/')) {
                element.textContent = Math.floor(current) + '/7';
            } else {
                element.textContent = Math.floor(current);
            }
        }, 16);
    };
    
    // Observe stats for counter animation
    const statsObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                const text = element.textContent;
                
                if (text.includes('5K+')) {
                    animateCounter(element, 5);
                } else if (text.includes('10K+')) {
                    animateCounter(element, 10);
                } else if (text.includes('24/7')) {
                    animateCounter(element, 24);
                } else if (text.includes('99.9%')) {
                    animateCounter(element, 99.9);
                }
                
                statsObserver.unobserve(element);
            }
        });
    }, { threshold: 0.5 });
    
    statNumbers.forEach(stat => statsObserver.observe(stat));
    
    // Mobile menu close on link click
    const navbarCollapse = document.querySelector('.navbar-collapse');
    const navLinks2 = document.querySelectorAll('.nav-link');
    
    navLinks2.forEach(link => {
        link.addEventListener('click', () => {
            if (navbarCollapse.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse);
                bsCollapse.hide();
            }
        });
    });
    
    // Pricing card hover effects
    const pricingCards = document.querySelectorAll('.pricing-card, .pricing-section .glass-card');
    
    pricingCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.classList.contains('popular')) {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            if (!this.classList.contains('popular')) {
                this.style.transform = 'translateY(0) scale(1)';
            }
        });
    });
    
    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;
        
        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
        circle.classList.add('ripple');
        
        const ripple = button.getElementsByClassName('ripple')[0];
        if (ripple) {
            ripple.remove();
        }
        
        button.appendChild(circle);
    }
    
    // Apply ripple effect to buttons
    const buttons = document.querySelectorAll('.btn-primary');
    buttons.forEach(button => {
        button.addEventListener('click', createRipple);
    });
    
    // Form validation (if forms are present)
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Show error message
                const errorMsg = form.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.style.display = 'block';
                }
            }
        });
    });
    
    // Add floating animation to feature icons
    const featureIcons = document.querySelectorAll('.feature-icon');
    
    featureIcons.forEach((icon, index) => {
        icon.style.animationDelay = `${index * 0.2}s`;
        icon.classList.add('floating');
    });
    
    // Add success message for newsletter signup (if present)
    const newsletterForm = document.querySelector('#newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show success message
            const successMsg = document.createElement('div');
            successMsg.className = 'alert alert-success mt-3';
            successMsg.textContent = 'Obrigado! Você receberá nossas novidades em breve.';
            
            this.appendChild(successMsg);
            this.reset();
            
            setTimeout(() => {
                successMsg.remove();
            }, 5000);
        });
    }
});

// Utility functions
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^\(?\d{2}\)?[\s-]?\d{4,5}[\s-]?\d{4}$/;
    return re.test(phone);
}

// Export functions for use in other scripts
window.KMKZ = {
    formatCurrency,
    validateEmail,
    validatePhone
};
