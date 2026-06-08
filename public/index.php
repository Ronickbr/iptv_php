<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/functions.php';

// Buscar dados da API
$plansResponse = apiGetPlans();
$plans = getApiResponseData($plansResponse);
$testimonials = [];
 
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';

// Fallback para dados estáticos se a API não estiver disponível
if (empty($plans)) {
    $plans = getFallbackData('plans');
}

if (empty($testimonials)) {
    $testimonials = getFallbackData('testimonials');
}

$db = getDB();
if ($db) {
    ensureMarketingPlans($db);
    $plansResponse = apiGetPlans();
    $plans = getApiResponseData($plansResponse);
    if (empty($plans)) {
        $plans = getFallbackData('plans');
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KMKZ IPTV - A Revolução do Entretenimento</title>
    <meta name="description" content="Mais de 500 canais, filmes e séries em HD/4K. Cancele sua TV a cabo e economize até 80% mensalmente!">
    <meta name="keywords" content="IPTV, streaming, canais, filmes, séries, HD, 4K, entretenimento">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="KMKZ IPTV - A Revolução do Entretenimento">
    <meta property="og:description" content="Mais de 500 canais, filmes e séries em HD/4K. Cancele sua TV a cabo e economize até 80% mensalmente!">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($scheme . '://' . $host . '/assets/images/Logo.png', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#020617">
    
    <!-- Google Fonts - Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
    
    <!-- UI Enhancements CSS -->
    <link href="assets/css/ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/assets/css/ui-enhancements.css') ?: time(); ?>" rel="stylesheet">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="assets/images/Logo.png" as="image">
    <link rel="icon" href="assets/images/Logo.png">
</head>
<body data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top custom-navbar" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand" href="index.php#home" data-aos="fade-right">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="74" height="40" class="d-none d-md-block logo-img" decoding="async" fetchpriority="high">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="59" height="32" class="d-md-none logo-img" decoding="async" fetchpriority="high">
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto" data-aos="fade-left">
                    <li class="nav-item">
                        <a class="nav-link smooth-scroll" href="#home">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link smooth-scroll" href="#features">Recursos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link smooth-scroll" href="#plans">Planos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link smooth-scroll" href="#testimonials">Depoimentos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link smooth-scroll" href="#faq">FAQ</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2 btn-login" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Entrar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="mainContent">
    <!-- Hero Section -->
    <section id="home" class="hero-section position-relative overflow-hidden">
        <!-- Mesh Gradient Background -->
        <div class="mesh-gradient"></div>
        <!-- Background particles -->
        <div class="particles-bg"></div>
        
        <div class="container position-relative">
            <div class="row justify-content-center text-center">
                <div class="col-lg-10">
                    <div class="hero-badge mb-4" data-aos="fade-down">
                        <i class="fas fa-star me-2"></i>
                        Ativação rápida • Suporte 24/7 • 3 dias de Teste
                    </div>
                    
                    <h1 class="hero-title mb-4" data-aos="fade-up" data-aos-delay="200" id="heroTitle">
                        Canais, filmes e séries em <span class="text-gradient">qualidade premium</span>
                    </h1>
                    <p class="hero-subtitle mb-5" data-aos="fade-up" data-aos-delay="400" id="heroSubtitle">
                        Mais de 500 canais e conteúdo on-demand para toda a família. Escolha um plano, receba o acesso e comece a assistir no seu dispositivo.
                    </p>
                    
                    <!-- Trust indicators -->
                    <div class="trust-indicators mb-5" data-aos="fade-up" data-aos-delay="600">
                        <div class="trust-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Conexão Segura</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-clock"></i>
                            <span>Ativação Rápida</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-undo"></i>
                            <span>3 Dias de Teste</span>
                        </div>
                    </div>
                    
                    <!-- Stats Bento Grid -->
                    <div class="bento-grid bento-stats mb-5" aria-label="Estatísticas do serviço">
                        <div class="bento-item bento-stat" data-aos="zoom-in" data-aos-delay="900">
                            <div class="bento-stat-head">
                                <div class="bento-stat-icon"><i class="fas fa-clapperboard"></i></div>
                                <div class="bento-stat-kicker">On-demand</div>
                            </div>
                            <div class="stat-number counter" data-target="10000" data-suffix="+">0</div>
                            <div class="stat-label">Filmes & Séries</div>
                            <div class="bento-stat-sub">Atualizações semanais</div>
                            <div class="feature-glow"></div>
                        </div>
                        <div class="bento-item bento-stat" data-aos="zoom-in" data-aos-delay="1000">
                            <div class="bento-stat-head">
                                <div class="bento-stat-icon"><i class="fas fa-headset"></i></div>
                                <div class="bento-stat-kicker">Suporte</div>
                            </div>
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Atendimento</div>
                            <div class="bento-stat-sub">WhatsApp e chat</div>
                            <div class="feature-glow"></div>
                        </div>
                        <div class="bento-item bento-stat" data-aos="zoom-in" data-aos-delay="1100">
                            <div class="bento-stat-head">
                                <div class="bento-stat-icon"><i class="fas fa-signal"></i></div>
                                <div class="bento-stat-kicker">Estabilidade</div>
                            </div>
                            <div class="stat-number">99,9%</div>
                            <div class="stat-label">Tempo online</div>
                            <div class="bento-stat-sub">Infra com redundância</div>
                            <div class="feature-glow"></div>
                        </div>
                        <div class="bento-item bento-stat" data-aos="zoom-in" data-aos-delay="1200">
                            <div class="bento-stat-head">
                                <div class="bento-stat-icon"><i class="fas fa-bolt"></i></div>
                                <div class="bento-stat-kicker">Performance</div>
                            </div>
                            <div class="stat-number">H.265</div>
                            <div class="stat-label">Alta velocidade</div>
                            <div class="bento-stat-sub">Streams otimizados</div>
                            <div class="feature-glow"></div>
                        </div>
                    </div>
                    
                    <div class="hero-cta" data-aos="fade-up" data-aos-delay="1200">
                        <a href="#plans" class="btn btn-primary btn-lg btn-cta me-3 smooth-scroll" id="heroCtaPrimary">
                            <i class="fas fa-play me-2" aria-hidden="true"></i>Ver Planos
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg smooth-scroll">
                            <i class="fas fa-info-circle me-2" aria-hidden="true"></i>Saiba Mais
                        </a>
                    </div>
                    
                    <!-- Floating elements -->
                    <div class="floating-elements">
                        <div class="floating-icon" style="--x: 12; --y: 22; --delay: 0s; --size: 64px; --tint: 271;">
                            <i class="fas fa-tv"></i>
                        </div>
                        <div class="floating-icon" style="--x: 86; --y: 30; --delay: 1s; --size: 56px; --tint: 330;">
                            <i class="fas fa-film"></i>
                        </div>
                        <div class="floating-icon" style="--x: 22; --y: 78; --delay: 2s; --size: 58px; --tint: 205;">
                            <i class="fas fa-futbol"></i>
                        </div>
                        <div class="floating-icon" style="--x: 88; --y: 76; --delay: 3s; --size: 60px; --tint: 271;">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll indicator 
        <div class="scroll-indicator" data-aos="fade-up" data-aos-delay="1500">
            <div class="scroll-mouse">
                <div class="scroll-wheel"></div>
            </div>
            <span>Role para baixo</span>
        </div> -->
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section py-5">
        <div class="container">
            <div class="row justify-content-center text-center mb-5">
                <div class="col-lg-8">
                    <div class="section-badge mb-3" data-aos="fade-up">
                        <i class="fas fa-award me-2"></i>
                        Recursos Premium
                    </div>
                    <h2 class="section-title mb-4" data-aos="fade-up" data-aos-delay="200">Tudo para você assistir sem complicação</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="400">Qualidade, suporte e compatibilidade para você apertar o play e aproveitar</p>
                </div>
            </div>
            
            <div class="features-bento">
                <!-- Canais - Large Spotlight Card -->
                <div class="bento-item bento-col-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="bento-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-tv"></i>
                        </div>
                        <div class="mt-auto">
                            <h4 class="display-6 fw-bold">500+ canais ao vivo</h4>
                            <p class="fs-5">Esportes, filmes, notícias e infantis em HD/FHD/4K (conforme disponibilidade) para toda a família.</p>
                            <div class="feature-highlight mt-4">
                                <span class="badge rounded-pill px-3 py-2 bg-primary"><i class="fas fa-bolt me-1"></i> Transmissão Ultra Rápida</span>
                                <span class="badge rounded-pill px-3 py-2 bg-info ms-2"><i class="fas fa-microchip me-1"></i> Codec H.265</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Suporte - Tall Card -->
                <div class="bento-item bento-col-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="bento-card h-100 bg-gradient-purple">
                        <div class="feature-icon text-white">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="mt-auto">
                            <h4>Suporte 24/7</h4>
                            <p>Ajuda para instalação, configuração e dúvidas quando você precisar.</p>
                            <div class="status-indicator mt-3">
                                <span class="pulse-dot"></span> Online Agora
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filmes - Wide Card -->
                <div class="bento-item bento-col-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="bento-card h-100">
                        <div class="row align-items-center text-center justify-content-center">
                            <div class="col-12">
                                <div class="feature-icon">
                                    <i class="fas fa-film"></i>
                                </div>
                                <h4>Filmes & séries on-demand</h4>
                                <p>Um catálogo para maratonar quando quiser, com atualizações regulares.</p>
                            </div>
                            <div class="feature-highlight mt-4 justify-content-center">
                                <span class="badge rounded-pill px-3 py-2 bg-primary"><i class="fas fa-bolt me-1"></i> Destaque</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dispositivos - Wide Card -->
                <div class="bento-item bento-col-6" data-aos="fade-up" data-aos-delay="500">
                    <div class="bento-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Assista onde preferir</h4>
                        <p>Compatível com Smart TV, Android e TV Box.</p>
                        <div class="device-icons-mini mt-2">
                            <i class="fab fa-android me-2"></i>
                            <i class="fab fa-apple me-2"></i>
                            <i class="fas fa-desktop me-2"></i>
                            <i class="fas fa-laptop"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional features showcase -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="features-showcase" data-aos="fade-up" data-aos-delay="800">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h3 class="mb-4">Do plano ao play em poucos passos</h3>
                                <div class="feature-list">
                                    <div class="feature-item">
                                        <i class="fas fa-check-circle text-success me-3"></i>
                                        <span>Configuração simples e guiada</span>
                                    </div>
                                    <div class="feature-item">
                                        <i class="fas fa-check-circle text-success me-3"></i>
                                        <span>Ativação rápida após confirmação</span>
                                    </div>
                                    <div class="feature-item">
                                        <i class="fas fa-check-circle text-success me-3"></i>
                                        <span>Compatível com os principais dispositivos</span>
                                    </div>
                                    <div class="feature-item">
                                        <i class="fas fa-check-circle text-success me-3"></i>
                                        <span>Atualizações regulares de conteúdo</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-center">
                                <div class="devices-preview">
                                    <i class="fas fa-tv device-icon"></i>
                                    <i class="fas fa-mobile-alt device-icon"></i>
                                    <i class="fas fa-tablet-alt device-icon"></i>
                                    <i class="fas fa-laptop device-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="plans" class="pricing-section py-5">
        <div class="container">
            <div class="row justify-content-center text-center mb-5">
                <div class="col-lg-8">
                    <div class="section-badge mb-3" data-aos="fade-up">
                        <i class="fas fa-crown me-2"></i>
                        Planos Exclusivos
                    </div>
                    <h2 class="section-title mb-4" data-aos="fade-up" data-aos-delay="200">Escolha seu plano ideal</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="400">Menos fricção para começar: escolha um plano e receba o acesso</p>
                    
                    <?php
                    $allowedPlans = ['Mensal', 'Trimestral', 'Semestral', 'Anual'];
                    $plansForDisplay = array_values(array_filter(is_array($plans) ? $plans : [], function($plan) {
                        return is_array($plan);
                    }));

                    $filteredPlans = array_values(array_filter($plansForDisplay, function($plan) use ($allowedPlans) {
                        return isset($plan['name']) && in_array($plan['name'], $allowedPlans, true);
                    }));

                    $durationOrder = [
                        'Mensal' => 1,
                        'Trimestral' => 3,
                        'Semestral' => 6,
                        'Anual' => 12
                    ];
                    usort($filteredPlans, function($a, $b) use ($durationOrder) {
                        $aKey = $a['name'] ?? '';
                        $bKey = $b['name'] ?? '';
                        $aOrder = $durationOrder[$aKey] ?? 999;
                        $bOrder = $durationOrder[$bKey] ?? 999;
                        if ($aOrder === $bOrder) {
                            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
                        }
                        return $aOrder <=> $bOrder;
                    });

                    $monthlyBasePrice = null;
                    foreach ($filteredPlans as $plan) {
                        if (($plan['name'] ?? null) !== 'Mensal') {
                            continue;
                        }
                        $planPrice = isset($plan['price']) && is_numeric($plan['price']) ? (float)$plan['price'] : 0.0;
                        if ($planPrice > 0) {
                            $monthlyBasePrice = $planPrice;
                        }
                        break;
                    }
                    if ($monthlyBasePrice === null) {
                        foreach ($filteredPlans as $plan) {
                            $planPrice = isset($plan['price']) && is_numeric($plan['price']) ? (float)$plan['price'] : 0.0;
                            $durationMonths = isset($plan['duration_months']) && is_numeric($plan['duration_months']) ? (int)$plan['duration_months'] : 0;
                            if ($planPrice > 0 && $durationMonths > 0) {
                                $monthlyBasePrice = $planPrice / $durationMonths;
                                break;
                            }
                        }
                    }

                    $annualSavingsPercent = null;
                    foreach ($filteredPlans as $plan) {
                        if (($plan['duration_months'] ?? null) != 12) continue;

                        $planPrice = isset($plan['price']) && is_numeric($plan['price']) ? (float)$plan['price'] : 0.0;
                        $durationMonths = isset($plan['duration_months']) && is_numeric($plan['duration_months']) ? (int)$plan['duration_months'] : 0;
                        $originalPrice = isset($plan['original_price']) && is_numeric($plan['original_price']) && (float)$plan['original_price'] > $planPrice
                            ? (float)$plan['original_price']
                            : (($monthlyBasePrice && $durationMonths > 0) ? ($monthlyBasePrice * $durationMonths) : null);

                        if ($originalPrice && $originalPrice > $planPrice) {
                            $annualSavingsPercent = (int)round((($originalPrice - $planPrice) / $originalPrice) * 100);
                        }
                        break;
                    }
                    ?>
                    
                    <!-- Pricing toggle 
                    <div class="pricing-toggle mt-4" data-aos="fade-up" data-aos-delay="600">
                        <div class="toggle-wrapper">
                            <span class="toggle-label">Mensal</span>
                            <div class="toggle-switch">
                                <input type="checkbox" id="pricing-toggle" checked>
                                <label for="pricing-toggle"></label>
                            </div>
                            <span class="toggle-label">Anual <span class="save-badge">Economize <?php echo $annualSavingsPercent !== null ? (int)$annualSavingsPercent : 20; ?>%</span></span>
                        </div>
                    </div> -->
                </div>
            </div>
            
            <div class="row g-4 justify-content-center">
                <?php foreach ($filteredPlans as $index => $plan): 
                    $isPopular = !empty($plan['is_popular']);
                    $delay = 200 + ($index * 200);
                ?>
                <div class="col-md-6 col-lg-3">
                    <div class="glass-card <?php echo $isPopular ? 'popular' : ''; ?> h-100" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                        <?php if ($isPopular): ?>
                        <div class="popular-badge pulse">
                            <i class="fas fa-crown"></i> Mais Popular
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $planPrice = isset($plan['price']) && is_numeric($plan['price']) ? (float)$plan['price'] : 0.0;
                        $durationMonths = isset($plan['duration_months']) && is_numeric($plan['duration_months']) ? (int)$plan['duration_months'] : 0;
                        
                        $originalPriceForDisplay = null;
                        if (isset($plan['original_price']) && is_numeric($plan['original_price']) && (float)$plan['original_price'] > $planPrice) {
                            $originalPriceForDisplay = (float)$plan['original_price'];
                        } elseif ($monthlyBasePrice && $durationMonths > 1) {
                            $candidate = $monthlyBasePrice * $durationMonths;
                            if ($candidate > ($planPrice + 0.01)) {
                                $originalPriceForDisplay = $candidate;
                            }
                        }
                        
                        $savingsPercent = 0;
                        if ($originalPriceForDisplay && $originalPriceForDisplay > $planPrice) {
                            $savingsPercent = (int)round((($originalPriceForDisplay - $planPrice) / $originalPriceForDisplay) * 100);
                        }
                        ?>
                        
                        <?php if ($savingsPercent > 0): ?>
                            <div class="savings-badge">
                                <span>-<?php echo $savingsPercent; ?>%</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="pricing-header">
                            <div class="plan-icon">
                                <i class="fas <?php echo $index === 0 ? 'fa-play' : ($index === 1 ? 'fa-star' : ($index === 2 ? 'fa-crown' : 'fa-gem')); ?>"></i>
                            </div>
                            <h4><?php echo htmlspecialchars($plan['name']); ?></h4>
                            <?php
                            $planDescriptions = [
                                'Mensal' => 'Para começar hoje, sem compromisso.',
                                'Trimestral' => 'Mais tempo para curtir, com economia.',
                                'Semestral' => 'Melhor custo-benefício para a maioria.',
                                'Anual' => 'Máxima economia para quem já decidiu.'
                            ];
                            $planDescription = !empty($plan['description'])
                                ? (string)$plan['description']
                                : (string)($planDescriptions[$plan['name'] ?? ''] ?? '');
                            ?>
                            <?php if (!empty($planDescription)): ?>
                            <p class="plan-description">
                                <?php echo htmlspecialchars($planDescription); ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="price-container">
                                <?php if ($savingsPercent > 0 && $originalPriceForDisplay): ?>
                                    <div class="original-price">R$ <?php echo number_format($originalPriceForDisplay, 2, ',', '.'); ?></div>
                                <?php endif; ?>
                                <div class="price">
                                    <span class="currency">R$</span>
                                    <span class="amount"><?php echo number_format($planPrice, 2, ',', '.'); ?></span>
                                </div>
                            </div>
                            <div class="period"><?php echo $durationMonths; ?> <?php echo $durationMonths == 1 ? 'mês' : 'meses'; ?></div>
                            
                            <?php if (isset($plan['quality']) && $plan['quality']): ?>
                            <div class="quality-badge">
                                Qualidade <?php echo htmlspecialchars($plan['quality']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pricing-features">
                            <ul>
                                <?php if (!empty($plan['features'])): ?>
                                    <?php 
                                    $features = is_string($plan['features']) ? json_decode($plan['features'], true) : $plan['features'];
                                    if (!$features) $features = explode(',', $plan['features']);
                                    ?>
                                    <?php foreach ($features as $feature): ?>
                                        <li class="feature-highlight">
                                            <i class="fas fa-check text-success"></i> 
                                            <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="feature-highlight">
                                        <i class="fas fa-tv text-primary"></i> 
                                        <span>Mais de 500 canais</span>
                                    </li>
                                    <li class="feature-highlight">
                                        <i class="fas fa-film text-warning"></i> 
                                        <span>Filmes e séries em <?php echo htmlspecialchars($plan['quality'] ?? 'HD'); ?></span>
                                    </li>
                                    <li class="feature-highlight">
                                        <i class="fas fa-mobile-alt text-info"></i> 
                                        <span>Até <?php echo $plan['max_devices'] ?? 1; ?> dispositivo(s)</span>
                                    </li>
                                    <li class="feature-highlight">
                                        <i class="fas fa-headset text-success"></i> 
                                        <span>Suporte 24/7</span>
                                    </li>
                                    <li class="feature-highlight">
                                        <i class="fas fa-undo text-secondary"></i> 
                                        <span>Sem compromisso</span>
                                    </li>
                                <?php endif; ?>
                                
                                <li class="bonus-feature">
                                    <i class="fas fa-gift text-warning"></i>
                                    <span>Teste por 3 dias</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="pricing-footer mt-auto">
                            <div class="trial-info mb-3">
                                <i class="fas fa-clock me-2"></i>
                                <small>Ativação imediata</small>
                            </div>
                            <a href="subscribe.php?plan=<?php echo $plan['id']; ?>" class="btn <?php echo $isPopular ? 'btn-primary' : 'btn-outline-primary'; ?> btn-lg w-100 btn-hover-effect">
                                <span class="btn-text">Escolher Plano</span>
                                <i class="fas fa-arrow-right btn-icon"></i>
                            </a>
                            <div class="security-info mt-2">
                                <i class="fas fa-shield-alt me-1"></i>
                                <small class="text-muted">Pagamento com conexão segura</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Loyalty Program Section -->
    <section class="loyalty-section py-5">
        <div class="container">
            <div class="row justify-content-center text-center mb-5">
                <div class="col-lg-8">
                    <h2 class="section-title mb-4">Programa de pontos por indicação</h2>
                    <p class="section-subtitle">Indique para amigos, acumule pontos e troque por benefícios dentro da plataforma.</p>
                </div>
            </div>
            
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="loyalty-card">
                        <div class="loyalty-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h4>100</h4>
                        <p>Pontos por Indicação</p>
                        <small>Para cada novo cliente</small>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="loyalty-card">
                        <div class="loyalty-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h4>500</h4>
                        <p>Pontos = 1 Mês Grátis</p>
                        <small>Troque por assinatura</small>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="loyalty-card">
                        <div class="loyalty-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h4>Prêmios</h4>
                        <p>Diversos</p>
                        <small>Assinaturas, produtos e muito mais</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials-section py-5">
        <div class="container">
            <div class="row justify-content-center text-center mb-5">
                <div class="col-lg-8">
                    <h2 class="section-title mb-4">Depoimentos de clientes</h2>
                </div>
            </div>
            
            <div class="row g-4">
                <?php foreach ($testimonials as $testimonial): ?>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="testimonial-text">"<?php echo htmlspecialchars($testimonial['comment']); ?>"</p>
                        <div class="testimonial-author">
                            <strong><?php echo htmlspecialchars($testimonial['name']); ?></strong>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="faq-section py-5">
        <div class="container">
            <div class="row justify-content-center text-center mb-5">
                <div class="col-lg-8">
                    <div class="section-badge mb-3" data-aos="fade-up">
                        <i class="fas fa-question-circle me-2"></i>
                        Perguntas Frequentes
                    </div>
                    <h2 class="section-title mb-4" data-aos="fade-up" data-aos-delay="200">Dúvidas Frequentes</h2>
                    <p class="section-subtitle" data-aos="fade-up" data-aos-delay="400">Encontre respostas para as principais dúvidas sobre nosso serviço de IPTV</p>
                </div>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="accordion" id="faqAccordion">
                        <!-- FAQ 1 -->
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="200">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
                                    <i class="fas fa-tv me-3"></i>
                                    O que é IPTV e como funciona?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>IPTV (Internet Protocol Television) é uma tecnologia que transmite conteúdo de TV através da internet. Diferente da TV tradicional por cabo ou satélite, o IPTV utiliza sua conexão de internet para entregar canais, filmes e séries diretamente para seus dispositivos.</p>
                                    <p><strong>Como funciona:</strong> Você recebe um aplicativo ou link de acesso, instala em seu dispositivo (Smart TV, celular, tablet, TV Box) e assiste ao conteúdo através da sua conexão de internet.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 2 -->
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="300">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                                    <i class="fas fa-wifi me-3"></i>
                                    Qual velocidade de internet preciso?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Para uma experiência perfeita, recomendamos:</p>
                                    <ul>
                                        <li><strong>HD (720p):</strong> Mínimo 5 Mbps</li>
                                        <li><strong>Full HD (1080p):</strong> Mínimo 10 Mbps</li>
                                        <li><strong>4K/Ultra HD:</strong> Mínimo 25 Mbps</li>
                                    </ul>
                                    <p>Para múltiplos dispositivos simultâneos, multiplique a velocidade pelo número de telas que serão usadas ao mesmo tempo.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 3 -->
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="400">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                                    <i class="fas fa-mobile-alt me-3"></i>
                                    Em quais dispositivos posso assistir?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Nosso serviço é compatível com praticamente todos os dispositivos:</p>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <ul>
                                                <li><i class="fas fa-tv text-primary me-2"></i>Smart TVs (Samsung, LG, Sony, etc.)</li>
                                                <li><i class="fas fa-mobile-alt text-success me-2"></i>Smartphones (Android/iOS)</li>
                                                <li><i class="fas fa-tablet-alt text-info me-2"></i>Tablets (Android/iPad)</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul>
                                                <li><i class="fas fa-laptop text-warning me-2"></i>Computadores (Windows/Mac/Linux)</li>
                                                <li><i class="fas fa-cube text-danger me-2"></i>TV Box Android</li>
                                                <li><i class="fas fa-gamepad text-secondary me-2"></i>Consoles (alguns modelos)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 4 -->
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="500">
                            <h2 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
                                    <i class="fas fa-clock me-3"></i>
                                    Quanto tempo demora para ativar?
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>A ativação é <strong>imediata</strong>! Após a confirmação do pagamento:</p>
                                    <div class="activation-steps mt-3">
                                        <div class="step-item">
                                            <span class="step-number">1</span>
                                            <span class="step-text">Pagamento confirmado automaticamente</span>
                                        </div>
                                        <div class="step-item">
                                            <span class="step-number">2</span>
                                            <span class="step-text">Dados de acesso enviados por email/WhatsApp</span>
                                        </div>
                                        <div class="step-item">
                                            <span class="step-number">3</span>
                                            <span class="step-text">Instalação e configuração em menos de 5 minutos</span>
                                        </div>
                                    </div>
                                    <p class="mt-3"><strong>Tempo total:</strong> Máximo 5 minutos após o pagamento!</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FAQ 5 -->
                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="600">
                            <h2 class="accordion-header" id="faq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5" aria-expanded="false" aria-controls="collapse5">
                                    <i class="fas fa-shield-alt me-3"></i>
                                    É seguro?
                                </button>
                            </h2>
                            <div id="collapse5" class="accordion-collapse collapse" aria-labelledby="faq5" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p>Sim. Priorizamos segurança e privacidade:</p>
                                    <div class="security-features mt-3">
                                        <div class="security-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <span>Pagamentos processados com segurança SSL</span>
                                        </div>
                                        <div class="security-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <span>Dados pessoais protegidos pela LGPD</span>
                                        </div>
                                        <div class="security-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <span>Suporte técnico 24/7 disponível</span>
                                        </div>
                                        <div class="security-item">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <span>7 dias de garantia</span>
                                        </div>
                                    </div>
                                    <p class="mt-3"><strong>Garantia:</strong> Você tem 7 dias para avaliar com tranquilidade.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section py-5">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h2 class="section-title mb-4">Pronto para começar?</h2>
                    <p class="section-subtitle mb-4">Escolha um plano e comece a assistir hoje mesmo, no dispositivo que você já usa.</p>
                    <div class="cta-features mb-4">
                        <span class="cta-feature">Sem compromisso</span>
                        <span class="cta-feature">Cancele quando quiser</span>
                        <span class="cta-feature">Suporte 24/7</span>
                    </div>
                    <a href="#plans" class="btn btn-primary btn-lg btn-cta">
                        <i class="fas fa-rocket me-2"></i>Quero Ver os Planos
                    </a>
                </div>
            </div>
        </div>
    </section>

    </main>
    <!-- Footer -->
    <footer class="footer py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; <?php echo date('Y'); ?> KMKZ IPTV. Todos os direitos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="#" class="text-decoration-none me-3">Termos de Uso</a>
                    <a href="#" class="text-decoration-none me-3">Política de Privacidade</a>
                    <a href="#" class="text-decoration-none">Contato</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation Library -->
    <script defer src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Custom JS -->
    <script defer src="assets/js/script.js?v=<?php echo filemtime(__DIR__ . '/assets/js/script.js') ?: time(); ?>"></script>
    <!-- UI Enhancements JS -->
    <script defer src="assets/js/ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/assets/js/ui-enhancements.js') ?: time(); ?>"></script>
</body>
</html>
