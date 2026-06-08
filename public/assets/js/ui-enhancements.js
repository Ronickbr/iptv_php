// UI/UX Enhancements JavaScript for KMK IPTV

function kmkzPrefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function initAOS() {
    if (!window.AOS || kmkzPrefersReducedMotion()) return;
    AOS.init({
        duration: 800,
        easing: 'ease-in-out',
        once: true
    });
}

function kmkzReadJson(key) {
    try {
        const raw = window.localStorage.getItem(key);
        if (!raw) return null;
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

function kmkzWriteJson(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
    }
}

function kmkzGetConsent() {
    const v = kmkzReadJson('kmkz_consent');
    if (!v || typeof v !== 'object') return null;
    return {
        analytics: !!v.analytics,
        marketing: !!v.marketing,
        ts: Number.isFinite(v.ts) ? v.ts : Date.now()
    };
}

function kmkzSetConsent(consent) {
    const normalized = {
        analytics: !!consent?.analytics,
        marketing: !!consent?.marketing,
        ts: Date.now()
    };
    kmkzWriteJson('kmkz_consent', normalized);
    kmkzHideConsentBanner();
    kmkzLoadMarketingTags();
}

function kmkzCaptureAttribution() {
    const params = new URLSearchParams(window.location.search);
    const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref_code', 'referred_by'];
    const data = kmkzReadJson('kmkz_attribution') || {};
    let changed = false;

    keys.forEach((k) => {
        const v = params.get(k);
        if (v && typeof v === 'string' && v.trim() !== '') {
            data[k] = v.trim().slice(0, 200);
            changed = true;
        }
    });

    if (changed) {
        data.ts = Date.now();
        kmkzWriteJson('kmkz_attribution', data);
    }
}

function kmkzApplyAttributionToLinks() {
    const data = kmkzReadJson('kmkz_attribution') || {};
    const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'ref_code', 'referred_by'];
    const links = document.querySelectorAll('a[href]');

    links.forEach((a) => {
        const href = a.getAttribute('href');
        if (!href) return;
        if (!href.startsWith('subscribe.php') && !href.includes('subscribe.php?')) return;
        try {
            const url = new URL(href, window.location.href);
            keys.forEach((k) => {
                if (!url.searchParams.has(k) && data[k]) {
                    url.searchParams.set(k, data[k]);
                }
            });
            a.setAttribute('href', url.pathname + (url.search ? url.search : ''));
        } catch {
        }
    });
}

function kmkzShowConsentBanner() {
    if (document.getElementById('kmkzConsentBanner')) return;

    const banner = document.createElement('div');
    banner.id = 'kmkzConsentBanner';
    banner.className = 'kmkz-consent-banner';
    banner.setAttribute('role', 'region');
    banner.setAttribute('aria-label', 'Preferências de privacidade');

    banner.innerHTML = `
        <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-center justify-content-between">
            <div>
                <p class="fw-semibold mb-1">Privacidade e cookies</p>
                <p class="small opacity-75 mb-0">Usamos cookies essenciais e, com sua permissão, métricas (GA4) e marketing (Meta Pixel) para melhorar a experiência.</p>
            </div>
            <div class="kmkz-consent-actions">
                <button type="button" class="btn btn-outline-light btn-sm" id="kmkzConsentSettings">Preferências</button>
                <button type="button" class="btn btn-outline-light btn-sm" id="kmkzConsentReject">Rejeitar</button>
                <button type="button" class="btn btn-primary btn-sm" id="kmkzConsentAccept">Aceitar</button>
            </div>
        </div>
        <div class="kmkz-consent-details d-none" id="kmkzConsentDetails">
            <div class="kmkz-consent-toggle">
                <label for="kmkzConsentAnalytics" class="small">Métricas (GA4)</label>
                <input class="form-check-input" type="checkbox" id="kmkzConsentAnalytics">
            </div>
            <div class="kmkz-consent-toggle">
                <label for="kmkzConsentMarketing" class="small">Marketing (Meta Pixel)</label>
                <input class="form-check-input" type="checkbox" id="kmkzConsentMarketing">
            </div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-primary btn-sm" id="kmkzConsentSave">Salvar</button>
                <button type="button" class="btn btn-outline-light btn-sm" id="kmkzConsentCancel">Cancelar</button>
            </div>
        </div>
    `;

    document.body.appendChild(banner);

    const details = document.getElementById('kmkzConsentDetails');
    const analytics = document.getElementById('kmkzConsentAnalytics');
    const marketing = document.getElementById('kmkzConsentMarketing');

    const current = kmkzGetConsent();
    analytics.checked = current ? current.analytics : true;
    marketing.checked = current ? current.marketing : false;

    document.getElementById('kmkzConsentSettings')?.addEventListener('click', () => {
        details?.classList.toggle('d-none');
    });

    document.getElementById('kmkzConsentAccept')?.addEventListener('click', () => {
        kmkzSetConsent({ analytics: true, marketing: true });
    });

    document.getElementById('kmkzConsentReject')?.addEventListener('click', () => {
        kmkzSetConsent({ analytics: false, marketing: false });
    });

    document.getElementById('kmkzConsentSave')?.addEventListener('click', () => {
        kmkzSetConsent({ analytics: !!analytics.checked, marketing: !!marketing.checked });
    });

    document.getElementById('kmkzConsentCancel')?.addEventListener('click', () => {
        details?.classList.add('d-none');
    });
}

function kmkzHideConsentBanner() {
    const el = document.getElementById('kmkzConsentBanner');
    if (el) el.remove();
}

function kmkzLoadMarketingTags() {
    const consent = kmkzGetConsent();
    if (!consent) return;

    const gaId = document.body?.dataset?.ga4Id;
    if (consent.analytics && gaId && !window.__kmkzGaLoaded) {
        window.__kmkzGaLoaded = true;
        const s = document.createElement('script');
        s.async = true;
        s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(gaId)}`;
        document.head.appendChild(s);
        window.dataLayer = window.dataLayer || [];
        window.gtag = function() { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', gaId, { anonymize_ip: true });
    }

    const pixelId = document.body?.dataset?.metaPixelId;
    if (consent.marketing && pixelId && !window.__kmkzPixelLoaded) {
        window.__kmkzPixelLoaded = true;
        (function(f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function() { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = true;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = true;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s);
        })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
        window.fbq('init', pixelId);
        window.fbq('track', 'PageView');
    }
}

function kmkzTrack(name, params = {}) {
    const consent = kmkzGetConsent();
    if (!consent) return;
    if (consent.analytics && typeof window.gtag === 'function') {
        window.gtag('event', name, params);
    }
    if (consent.marketing && typeof window.fbq === 'function') {
        window.fbq('trackCustom', name, params);
    }
}

function kmkzInitAbHeroTest() {
    const titleEl = document.getElementById('heroTitle');
    const subtitleEl = document.getElementById('heroSubtitle');
    const ctaEl = document.getElementById('heroCtaPrimary');
    if (!titleEl && !subtitleEl && !ctaEl) return;

    const params = new URLSearchParams(window.location.search);
    const forced = params.get('ab');
    const stored = window.localStorage.getItem('kmkz_ab_hero');
    const variant = (forced === 'a' || forced === 'b') ? forced : (stored === 'a' || stored === 'b') ? stored : (Math.random() < 0.5 ? 'a' : 'b');
    window.localStorage.setItem('kmkz_ab_hero', variant);

    if (variant === 'b') {
        if (titleEl) titleEl.innerHTML = `Ative rápido e assista em <span class="text-gradient">qualquer dispositivo</span>`;
        if (subtitleEl) subtitleEl.textContent = 'Escolha um plano, receba o acesso e comece a assistir em Smart TV, Android, iOS, PC e TV Box.';
        if (ctaEl) ctaEl.innerHTML = `<i class="fas fa-play me-2" aria-hidden="true"></i>Ver Planos`;
    } else {
        if (titleEl) titleEl.innerHTML = `Canais, filmes e séries em <span class="text-gradient">qualidade premium</span>`;
        if (subtitleEl) subtitleEl.textContent = 'Mais de 500 canais e conteúdo on-demand para toda a família. Escolha um plano, receba o acesso e comece a assistir no seu dispositivo.';
        if (ctaEl) ctaEl.innerHTML = `<i class="fas fa-play me-2" aria-hidden="true"></i>Ver Planos`;
    }

    kmkzTrack('ab_hero_view', { variant });
}

// Counter Animation Function
function animateValue(element, start, end, duration, options = {}) {
    let startTimestamp = null;
    const decimals = Number.isFinite(options.decimals) ? options.decimals : 0;
    const prefix = options.prefix ?? '';
    const suffix = options.suffix ?? '';
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const raw = progress * (end - start) + start;
        const value = decimals > 0 ? Number(raw.toFixed(decimals)) : Math.floor(raw);
        const formatted = value.toLocaleString('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
        element.textContent = `${prefix}${formatted}${suffix}`;
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

function initNumbersAnimation() {
    const statNumbers = document.querySelectorAll('.stat-number[data-target], .value[data-target], .counter[data-target]');
    statNumbers.forEach(stat => {
        const targetAttr = stat.dataset?.target;
        if (targetAttr) {
            const finalValue = parseFloat(targetAttr);
            if (Number.isFinite(finalValue) && finalValue > 0) {
                const decimals = parseInt(stat.dataset.decimals ?? '0', 10);
                animateValue(stat, 0, finalValue, 1500, {
                    decimals: Number.isFinite(decimals) ? decimals : 0,
                    prefix: stat.dataset.prefix ?? '',
                    suffix: stat.dataset.suffix ?? ''
                });
            }
        }
    });
}

// Smooth scrolling for navigation links
function initSmoothScrolling() {
    document.querySelectorAll('.smooth-scroll').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Initialize counter animation when visible
function initCounterObserver() {
    const statNumbers = document.querySelectorAll('.stat-number[data-target], .value[data-target], .counter[data-target]');
    
    if (statNumbers.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const stat = entry.target;
                    const targetAttr = stat.dataset?.target;
                    if (targetAttr) {
                        const finalValue = parseFloat(targetAttr);
                        if (Number.isFinite(finalValue) && finalValue > 0) {
                            const decimals = parseInt(stat.dataset.decimals ?? '0', 10);
                            animateValue(stat, 0, finalValue, 1500, {
                                decimals: Number.isFinite(decimals) ? decimals : 0,
                                prefix: stat.dataset.prefix ?? '',
                                suffix: stat.dataset.suffix ?? ''
                            });
                        }
                        observer.unobserve(stat);
                        return;
                    }
                    observer.unobserve(stat);
                }
            });
        }, { threshold: 0.1, rootMargin: '200px 0px' });
        
        statNumbers.forEach(stat => observer.observe(stat));
    }
}

// Pricing toggle functionality
function initPricingToggle() {
    const pricingToggle = document.getElementById('pricing-toggle');
    
    if (pricingToggle) {
        pricingToggle.addEventListener('change', function() {
            // Add pricing toggle logic here if needed
            console.log('Pricing toggle changed:', this.checked);
            
            // Example: Toggle between monthly and annual pricing
            const pricingCards = document.querySelectorAll('.pricing-card');
            pricingCards.forEach(card => {
                // Add animation or price update logic here
                card.style.transform = this.checked ? 'scale(1.02)' : 'scale(1)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 300);
            });
        });
    }
}

// Add floating animation to elements
function initFloatingElements() {
    const floatingIcons = document.querySelectorAll('.floating-icon');
    
    floatingIcons.forEach((icon) => {
        if (!icon.style.getPropertyValue('--delay')) {
            const randomDelay = Math.random() * 2;
            icon.style.setProperty('--delay', `${randomDelay}s`);
        }

        if (!icon.style.getPropertyValue('--duration')) {
            const randomDuration = 7 + Math.random() * 5;
            icon.style.setProperty('--duration', `${randomDuration}s`);
        }

        const randomRange = 12 + Math.random() * 14;
        icon.style.setProperty('--float-range', `${randomRange}px`);

        const driftX = 10 + Math.random() * 18;
        const driftY = 6 + Math.random() * 12;
        icon.style.setProperty('--drift-x', `${driftX}px`);
        icon.style.setProperty('--drift-y', `${driftY}px`);

        const spin = 8 + Math.random() * 12;
        icon.style.setProperty('--spin', `${spin}deg`);
    });
}

// Enhanced button hover effects
function initButtonEffects() {
    const buttons = document.querySelectorAll('.btn-hover-effect');
    
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
}

// Parallax effect for hero section
function initParallaxEffect() {
    const heroSection = document.querySelector('.hero-section');
    
    if (heroSection) {
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;
            
            const particles = heroSection.querySelector('.particles-bg');
            if (particles) {
                particles.style.transform = `translateY(${rate}px)`;
            }
        });
    }
}

// Glow Cursor Interaction
function initGlowCursor() {
    const cursor = document.createElement('div');
    cursor.className = 'glow-cursor';
    document.body.appendChild(cursor);

    let mouseX = 0;
    let mouseY = 0;
    let cursorX = 0;
    let cursorY = 0;

    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
        
        if (!document.body.classList.contains('cursor-active')) {
            document.body.classList.add('cursor-active');
        }
    });

    // Smooth cursor movement
    function animate() {
        let dx = mouseX - cursorX;
        let dy = mouseY - cursorY;
        
        cursorX += dx * 0.1;
        cursorY += dy * 0.1;
        
        cursor.style.left = cursorX + 'px';
        cursor.style.top = cursorY + 'px';
        
        requestAnimationFrame(animate);
    }
    animate();

    // Interaction with clickable elements
    const clickables = document.querySelectorAll('a, button, .glass-card, .btn-premium');
    clickables.forEach(el => {
        el.addEventListener('mouseenter', () => {
            cursor.style.width = '600px';
            cursor.style.height = '600px';
            cursor.style.background = 'hsla(330, 95%, 62%, 0.2)';
        });
        
        el.addEventListener('mouseleave', () => {
            cursor.style.width = 'var(--glow-cursor-size)';
            cursor.style.height = 'var(--glow-cursor-size)';
            cursor.style.background = 'var(--glow-cursor-color)';
        });
    });
}

// Sidebar Navigation Smooth Transitions
function initSidebarNav() {
    const navLinks = document.querySelectorAll('.admin-nav-link');
    const sections = document.querySelectorAll('.content-section');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('data-section');
            if (!targetId) return;
            
            // This is often handled by existing logic, but we add visual polish
            navLinks.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            
            // Transition effect
            const mainContent = document.getElementById('mainContent');
            if (mainContent) {
                mainContent.style.opacity = '0';
                mainContent.style.transform = 'translateY(10px)';
                
                setTimeout(() => {
                    mainContent.style.opacity = '1';
                    mainContent.style.transform = 'translateY(0)';
                    if (window.AOS) AOS.refresh();
                }, 300);
            }
        });
    });
}

// Initialize all UI enhancements when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    kmkzCaptureAttribution();
    detectDevice();
    initSmoothScrolling();
    initNumbersAnimation = initCounterObserver; // Alias for backward compatibility if needed
    initCounterObserver();
    initPricingToggle();
    initFloatingElements();
    initButtonEffects();
    if (!kmkzPrefersReducedMotion()) {
        initParallaxEffect();
    }

    if (!kmkzPrefersReducedMotion() && !document.body.classList.contains('mobile-device')) {
        initGlowCursor();
    }
    initSidebarNav();
    kmkzApplyAttributionToLinks();
    kmkzInitAbHeroTest();

    const consent = kmkzGetConsent();
    if (!consent) {
        kmkzShowConsentBanner();
    } else {
        kmkzLoadMarketingTags();
    }
    
    // Add loading animation completion
    document.body.classList.add('loaded');

    initAOS();

    const cards = document.querySelectorAll('.bento-card, .glass-card, .glass-input');
    
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / card.clientWidth) * 100;
            const y = ((e.clientY - rect.top) / card.clientHeight) * 100;

            card.style.setProperty('--mouse-x', `${x}%`);
            card.style.setProperty('--mouse-y', `${y}%`);
            
            // Legacy support for --x and --y if needed elsewhere
            card.style.setProperty('--x', `${e.clientX - rect.left}px`);
            card.style.setProperty('--y', `${e.clientY - rect.top}px`);
        });
    });
});

let kmkzNavbarRaf = 0;
let kmkzScrollY = window.scrollY;
window.addEventListener('scroll', function() {
    kmkzScrollY = window.scrollY;
    if (kmkzNavbarRaf) return;
    kmkzNavbarRaf = window.requestAnimationFrame(() => {
        kmkzNavbarRaf = 0;
        const navbar = document.querySelector('.navbar');
        const navLinks = document.querySelectorAll('.nav-link');
        const sections = document.querySelectorAll('section[id]');

        if (navbar) {
            if (kmkzScrollY > 50) {
                navbar.classList.add('scrolled');
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('scrolled');
                navbar.classList.remove('navbar-scrolled');
            }
        }

        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (kmkzScrollY >= (sectionTop - 150)) {
                current = section.getAttribute('id');
            }
        });

        if (current) {
            navLinks.forEach(link => {
                link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
            });
        }
    });
}, { passive: true });

// Add device detection for enhanced mobile experience
function detectDevice() {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    if (isMobile) {
        document.body.classList.add('mobile-device');
        
        // Disable some animations on mobile for better performance
        const floatingElements = document.querySelector('.floating-elements');
        if (floatingElements) {
            floatingElements.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', detectDevice);

// Export functions for external use if needed
window.UIEnhancements = {
    animateValue,
    initSmoothScrolling,
    initCounterObserver,
    initPricingToggle,
    initFloatingElements,
    initButtonEffects,
    initParallaxEffect
};

window.KMKZMarketing = {
    getConsent: kmkzGetConsent,
    setConsent: kmkzSetConsent,
    track: kmkzTrack,
    openConsent: kmkzShowConsentBanner
};
