<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/functions.php';

// Obter planos da API
$plansResponse = apiGetPlans();
$plans = getApiResponseData($plansResponse);

// Dados de fallback se a API não estiver disponível
if (!$plans) {
    $plans = getFallbackData('plans');
}

$db = getDB();
if ($db) {
    ensureMarketingPlans($db);
    $plansResponse = apiGetPlans();
    $plans = getApiResponseData($plansResponse);
    if (!$plans) {
        $plans = getFallbackData('plans');
    }
}

$allowedPlans = ['Mensal', 'Trimestral', 'Semestral', 'Anual'];
$plans = array_values(array_filter(is_array($plans) ? $plans : [], function($plan) use ($allowedPlans) {
    return is_array($plan) && isset($plan['name']) && in_array($plan['name'], $allowedPlans, true);
}));

$durationOrder = [
    'Mensal' => 1,
    'Trimestral' => 3,
    'Semestral' => 6,
    'Anual' => 12
];
usort($plans, function($a, $b) use ($durationOrder) {
    $aKey = $a['name'] ?? '';
    $bKey = $b['name'] ?? '';
    $aOrder = $durationOrder[$aKey] ?? 999;
    $bOrder = $durationOrder[$bKey] ?? 999;
    if ($aOrder === $bOrder) {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    }
    return $aOrder <=> $bOrder;
});

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/plans.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos Premium - KMKZ IPTV</title>
    <meta name="description" content="Escolha o plano ideal e tenha acesso imediato a mais de 500 canais, filmes e séries em alta definição.">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Planos Premium - KMKZ IPTV">
    <meta property="og:description" content="Escolha o plano ideal e tenha acesso imediato a mais de 500 canais, filmes e séries em alta definição.">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($scheme . '://' . $host . '/assets/images/Logo.png', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#020617">
    
    <!-- Google Fonts - Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>">
    <link rel="icon" href="assets/images/Logo.png">
</head>
<body class="bg-dark text-white" data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <!-- Mesh Gradient Background -->
    <div class="mesh-gradient"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top custom-navbar" id="mainNavbar">
        <div class="container">
            <a class="navbar-brand" href="index.php" data-aos="fade-right">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="74" height="40" decoding="async" fetchpriority="high">
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto" data-aos="fade-left">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="plans.php">Planos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-primary btn-login px-4" href="login.php">
                            <i class="fas fa-sign-in-alt me-2"></i>Entrar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="mainContent">
    <!-- Plans Header -->
    <section class="py-5 mt-5">
        <div class="container py-5">
            <div class="text-center mb-5" data-aos="fade-up">
                <div class="badge bg-purple-light px-3 py-2 rounded-pill mb-3" style="background: rgba(147, 51, 234, 0.2); color: var(--purple-light); border: 1px solid rgba(147, 51, 234, 0.3);">
                    <i class="fas fa-star me-2"></i> Melhores Opções
                </div>
                <h1 class="display-4 fw-bold mb-3"><span class="text-gradient">Escolha Seu Plano</span></h1>
                <p class="lead text-gray-300 mx-auto" style="max-width: 600px;">Assine agora e tenha acesso imediato a mais de 500 canais, filmes e séries em alta definição.</p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php $delay = 100; foreach ($plans as $plan): ?>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; $delay += 100; ?>">
                    <div class="glass-panel glass-panel-hover h-100 position-relative overflow-hidden <?php echo (isset($plan['is_popular']) && $plan['is_popular']) ? 'border-primary' : ''; ?>" style="<?php echo (isset($plan['is_popular']) && $plan['is_popular']) ? 'border: 1px solid var(--purple-primary);' : ''; ?>">
                        <?php if (isset($plan['is_popular']) && $plan['is_popular']): ?>
                        <div class="position-absolute top-0 end-0 p-2">
                            <span class="badge bg-primary rounded-pill px-3 py-2" style="background: linear-gradient(135deg, var(--purple-primary), var(--pink-accent)) !important;">
                                <i class="fas fa-fire me-1"></i> Popular
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card-body p-4 text-center d-flex flex-column h-100">
                            <h3 class="h4 fw-bold mb-3"><?php echo htmlspecialchars($plan['name']); ?></h3>
                            
                            <div class="plan-price my-4">
                                <span class="h5 align-top mt-2 d-inline-block text-gray-400">R$</span>
                                <span class="display-5 fw-bold text-white"><?php echo number_format($plan['price'], 0, ',', '.'); ?></span>
                                <span class="h5 text-gray-400">,<?php echo substr(number_format($plan['price'], 2, ',', '.'), -2); ?></span>
                                <div class="text-muted small mt-1">/ <?php echo $plan['duration_months']; ?> <?php echo $plan['duration_months'] == 1 ? 'mês' : 'meses'; ?></div>
                            </div>

                            <hr class="my-4 border-white opacity-10">
                            
                            <ul class="list-unstyled text-start mb-auto">
                                <?php 
                                $features = [];
                                if (!empty($plan['features'])) {
                                    $features = is_string($plan['features']) ? json_decode($plan['features'], true) : $plan['features'];
                                    if (!$features) {
                                        $features = explode(',', $plan['features']);
                                    }
                                }
                                
                                if (empty($features)) {
                                    $features = [
                                        'Mais de 500 canais',
                                        'Qualidade ' . ($plan['quality'] ?? 'HD'),
                                        'Até ' . ($plan['max_devices'] ?? 1) . ' dispositivo(s)',
                                        'Suporte 24/7'
                                    ];
                                }
                                
                                $features = array_slice($features, 0, 5);
                                foreach ($features as $feature):
                                ?>
                                <li class="mb-3 d-flex align-items-center">
                                    <i class="fas fa-check-circle text-purple-light me-3" style="color: var(--purple-light);"></i>
                                    <span class="text-gray-300"><?php echo htmlspecialchars(trim($feature)); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <div class="mt-4">
                                <a href="subscribe.php?plan=<?php echo $plan['id']; ?>" 
                                   class="btn <?php echo (isset($plan['is_popular']) && $plan['is_popular']) ? 'btn-primary' : 'btn-outline-light'; ?> w-100 py-3 fw-bold rounded-pill shadow-sm">
                                    Assinar Plano
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Extra Info -->
            <div class="row mt-5 pt-5 text-center">
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="glass-card h-100">
                        <i class="fas fa-bolt fa-2x text-gradient mb-3"></i>
                        <h5>Ativação Instantânea</h5>
                        <p class="text-muted small mb-0">Seu acesso é liberado automaticamente após a confirmação do pagamento.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="glass-card h-100">
                        <i class="fas fa-shield-alt fa-2x text-gradient mb-3"></i>
                        <h5>Compra Segura</h5>
                        <p class="text-muted small mb-0">Pagamento criptografado e 7 dias de garantia incondicional.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="glass-card h-100">
                        <i class="fas fa-headset fa-2x text-gradient mb-3"></i>
                        <h5>Suporte Especializado</h5>
                        <p class="text-muted small mb-0">Time de prontidão 24 horas por dia para tirar todas as suas dúvidas.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </main>

    <!-- Footer -->
    <footer class="py-5 mt-5" style="background: rgba(0,0,0,0.3); border-top: 1px solid rgba(255,255,255,0.05);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-4 mb-md-0">
                    <img src="assets/images/Logo.png" alt="KMKZ IPTV" height="30" class="mb-3 opacity-75">
                    <p class="text-muted small mb-0">&copy; 2026 KMKZ IPTV. Todos os direitos reservados. Uma revolução no seu entretenimento.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <div class="social-links mb-3">
                        <a href="#" class="text-white opacity-50 hover-opacity-100 mx-2"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white opacity-50 hover-opacity-100 mx-2"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white opacity-50 hover-opacity-100 mx-2"><i class="fab fa-whatsapp fa-lg"></i></a>
                    </div>
                    <a href="#" class="text-muted text-decoration-none small mx-2">Termos</a>
                    <a href="#" class="text-muted text-decoration-none small mx-2">Privacidade</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script defer src="assets/js/ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/assets/js/ui-enhancements.js') ?: time(); ?>"></script>
</body>
</html>
