<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se o usuário está logado
$authResponse = apiCheckAuth();
$user = null;

if (isApiResponseValid($authResponse) && $authResponse['success'] && ($authResponse['data']['logged_in'] ?? false)) {
    $user = $authResponse['data']['user'] ?? null;
} else {
    // Verificar sessão local como fallback
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] && isset($_SESSION['user_data'])) {
        $user = $_SESSION['user_data'];
    }
}

if (!$user) {
    header('Location: login.php');
    exit;
}

// Normalizar campo 'role' (compatibilidade entre API que retorna 'user_type' ou 'role')
if (!isset($user['role'])) {
    $user['role'] = $user['user_type'] ?? 'user';
}

// Redirecionar admin para painel administrativo
if ($user['role'] === 'admin' || ($user['user_type'] ?? '') === 'admin') {
    header('Location: admin.php');
    exit;
}

// Definir variáveis necessárias
$userId = $user['id'];
$db = getDB();

// Verificar se o usuário tem código de referência diretamente no banco
// Usa try/catch pois a coluna 'referral_code' pode não existir antes da migração ser executada.
// Execute migrate.php para adicionar as colunas necessárias ao banco.
try {
    $stmt = $db->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();

    if (empty($currentUser['referral_code'])) {
        $referralCode = generateReferralCode($db);
        if ($referralCode) {
            $stmt = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $stmt->execute([$referralCode, $userId]);
            $user['referral_code'] = $referralCode;
        } else {
            $user['referral_code'] = null;
        }
    } else {
        $user['referral_code'] = $currentUser['referral_code'];
    }
} catch (PDOException $e) {
    // A coluna referral_code não existe no banco ainda.
    // Acesse /migrate.php para executar a migração do banco de dados.
    error_log('KMKZ Dashboard: Coluna referral_code ausente. Execute /migrate.php para corrigir. Erro: ' . $e->getMessage());
    $user['referral_code'] = null;
}

// Obter dados do dashboard do usuário
$dashboardResponse = apiGetUserDashboard();
$dashboardData = getApiResponseData($dashboardResponse);

// Dados de fallback se a API não estiver disponível
if (!$dashboardData) {
    $dashboardData = [
        'user' => $user,
        'subscription' => [
            'plan_name' => 'Plano Mensal',
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'price' => 29.90,
            'duration_months' => 1
        ],
        'points' => [
            'balance' => 250,
            'total_earned' => 500,
            'ranking_position' => 25
        ],
        'recent_payments' => [],
        'available_rewards' => [],
        'referrals_count' => 0
    ];
}

// Extrair dados para compatibilidade com o código existente
$subscription = $dashboardData['subscription'];
$referralsCount = getUserReferralsCount($db, $userId);
$rewards = $dashboardData['available_rewards'] ?? [];
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/dashboard.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - KMKZ IPTV</title>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#020617">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
    <link href="assets/css/ui-enhancements.css?v=<?php echo filemtime(__DIR__ . '/assets/css/ui-enhancements.css') ?: time(); ?>" rel="stylesheet">
    <link href="assets/css/dashboard-fix.css?v=<?php echo filemtime(__DIR__ . '/assets/css/dashboard-fix.css') ?: time(); ?>" rel="stylesheet">
    
    <!-- AOS - Animate On Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Google Fonts - Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/images/Logo.png">

</head>
<body class="bg-dark text-white" data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <!-- Mesh Gradient Background -->
    <div class="mesh-gradient"></div>

    <div class="dashboard-container" id="mainContent">
        <div class="sidebar admin-sidebar-glass" id="sidebar">
            <div class="sidebar-header">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="147" height="80" class="logo" decoding="async" fetchpriority="high">
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="#dashboard" class="nav-link admin-nav-link active" data-section="dashboard">
                        <i class="fas fa-home"></i>
                        <span>Início</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#subscription" class="nav-link admin-nav-link" data-section="subscription">
                        <i class="fas fa-tv"></i>
                        <span>Assinatura</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#referrals" class="nav-link admin-nav-link" data-section="referrals">
                        <i class="fas fa-users"></i>
                        <span>Indicações</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#ranking" class="nav-link admin-nav-link" data-section="ranking">
                        <i class="fas fa-trophy"></i>
                        <span>Ranking VIP</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#rewards" class="nav-link admin-nav-link" data-section="rewards">
                        <i class="fas fa-gift"></i>
                        <span>Loja de Prêmios</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#profile" class="nav-link admin-nav-link" data-section="profile">
                        <i class="fas fa-user-circle"></i>
                        <span>Meu Perfil</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#support" class="nav-link admin-nav-link" data-section="support">
                        <i class="fas fa-headset"></i>
                        <span>Suporte 24h</span>
                    </a>
                </div>
                <div class="nav-item mt-auto pt-4">
                    <a href="logout.php" class="nav-link admin-nav-link text-danger">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair da Conta</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Top Navbar -->
            <div class="top-navbar admin-top-navbar d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="toggle-sidebar" id="toggleSidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="mb-0 ms-3 fw-bold" id="section-title">Dashboard</h4>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="points-badge d-none d-md-flex">
                        <i class="fas fa-star me-2"></i>
                        <span class="fw-bold"><?php echo $dashboardData['points']['balance'] ?? 0; ?></span>
                    </div>
                    
                    <div class="user-profile">
                        <div class="user-avatar shadow-sm">
                            <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                        </div>
                        <div class="d-none d-sm-block">
                            <div class="fw-bold lh-1"><?php echo htmlspecialchars($user['name']); ?></div>
                            <small class="text-muted opacity-75"><?php echo htmlspecialchars($user['email']); ?></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="p-0">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="content-section">
                    <div class="p-4 pb-0">
                        <div class="bento-card mb-4 welcome-bento-card overflow-hidden" data-aos="fade-down" style="min-height: 200px;">
                            <!-- Decorative blur blobs -->
                            <div class="position-absolute top-0 start-0 w-100 h-100 opacity-25" style="z-index: 0;">
                                <div class="position-absolute top-0 start-0 bg-primary rounded-circle" style="width: 300px; height: 300px; transform: translate(-30%, -30%); filter: blur(80px);"></div>
                                <div class="position-absolute bottom-0 end-0 bg-pink-accent rounded-circle" style="width: 250px; height: 250px; transform: translate(30%, 30%); filter: blur(70px);"></div>
                            </div>
                            
                            <div class="row align-items-center position-relative h-100" style="z-index: 1;">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center gap-4 mb-3">
                                        <div class="profile-avatar-container">
                                            <div class="profile-avatar-glow"></div>
                                            <div class="user-avatar shadow-lg" style="width: 100px; height: 100px; font-size: 2.5rem; border-radius: 24px; border: 2px solid rgba(255,255,255,0.1);">
                                                <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <h2 class="mb-1 fw-bold display-6">Bem-vindo, <span class="text-gradient"><?php echo explode(' ', htmlspecialchars($user['name']))[0]; ?></span>!</h2>
                                            <p class="mb-0 text-muted fs-5 opacity-75">Seu portal exclusivo de entretenimento premium.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end d-none d-md-block">
                                    <div class="catalog-badge pulse px-4 py-3 shadow-glow" style="background: rgba(255, 255, 255, 0.05); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1);">
                                        <i class="fas fa-crown me-2 text-warning"></i>
                                        <span class="small text-uppercase fw-bold letter-spacing-1">STATUS: <span class="text-warning">VIP GOLD</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Premium Bento Grid -->
                    <div class="features-bento px-4 pb-4">
                        <!-- Assinatura Card (Wide & Large) -->
                        <div class="bento-item bento-col-8 bento-row-2" data-aos="fade-up" data-aos-delay="100">
                            <div class="bento-card h-100">
                                <div class="feature-icon" style="background: rgba(147, 51, 234, 0.1); color: var(--purple-light);">
                                    <i class="fas fa-tv"></i>
                                </div>
                                <div class="mt-auto">
                                    <div class="label text-muted small text-uppercase fw-bold mb-1">Seu Plano</div>
                                    <h2 class="display-5 fw-bold mb-3"><?php echo $subscription ? htmlspecialchars($subscription['plan_name']) : 'Nenhum Plano'; ?></h2>
                                    
                                    <?php if ($subscription): ?>
                                        <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                                            <div class="status-indicator">
                                                <span class="pulse-dot"></span> Assinatura Ativa
                                            </div>
                                            <div class="badge rounded-pill bg-white-5 border border-white-10 px-3 py-2 text-white">
                                                <i class="far fa-calendar-alt me-2 text-primary"></i>
                                                Expira em: <?php echo date('d/m/Y', strtotime($subscription['expires_at'])); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <a href="plans.php" class="btn btn-premium mt-3">Explorar Planos <i class="fas fa-arrow-right ms-2"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Pontos Card (Small) -->
                        <div class="bento-item bento-col-4" data-aos="fade-up" data-aos-delay="200">
                            <div class="bento-card h-100">
                                <div class="feature-icon" style="background: rgba(251, 191, 36, 0.1); color: #fbbf24;">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="mt-auto">
                                    <div class="label text-muted small text-uppercase fw-bold">Pontos</div>
                                    <div class="value h2 fw-bold mb-0"><?php echo $dashboardData['points']['balance'] ?? 0; ?></div>
                                    <p class="small opacity-50 mt-1">Acumulados</p>
                                </div>
                            </div>
                        </div>

                        <!-- Indicações Card (Small) -->
                        <div class="bento-item bento-col-4" data-aos="fade-up" data-aos-delay="300">
                            <div class="bento-card h-100">
                                <div class="feature-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="mt-auto">
                                    <div class="label text-muted small text-uppercase fw-bold">Indicações</div>
                                    <div class="value h2 fw-bold mb-0"><?php echo $referralsCount; ?></div>
                                    <p class="small opacity-50 mt-1">Amigos ativos</p>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico Card (Wide & Large) -->
                        <div class="bento-item bento-col-8 bento-row-2" data-aos="fade-up" data-aos-delay="400">
                            <div class="bento-card h-100 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div>
                                        <h4 class="mb-1">Sua Atividade</h4>
                                        <p class="small text-muted">Uso da plataforma nos últimos meses</p>
                                    </div>
                                    <div class="badge bg-white-5 text-white border border-white-10 px-3 py-2">Frequência Semanal</div>
                                </div>
                                <div class="chart-container-modern flex-grow-1" style="min-height: 220px; position: relative;">
                                    <canvas id="activityChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Prêmios Card (Tall) -->
                        <div class="bento-item bento-col-4 bento-row-2" data-aos="fade-up" data-aos-delay="500">
                            <div class="bento-card h-100 bg-gradient-purple">
                                <div class="feature-icon text-white">
                                    <i class="fas fa-gift"></i>
                                </div>
                                <div class="mt-auto">
                                    <h4 class="text-white">Loja de Prêmios</h4>
                                    <p class="text-white opacity-75 mb-4">Você tem <?php echo count($rewards); ?> recompensas disponíveis para resgate.</p>
                                    <button class="btn btn-premium w-100" onclick="document.querySelector('[data-section=\'rewards\']').click()">
                                        Ir para a Loja <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Apps Card (Small/Action) -->
                        <div class="bento-item bento-col-4" data-aos="fade-up" data-aos-delay="600">
                            <div class="bento-card h-100">
                                <div class="feature-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="mt-auto">
                                    <h4 class="mb-2">Download App</h4>
                                    <div class="d-flex gap-2">
                                        <a href="#" class="btn btn-glass btn-sm flex-grow-1" title="Android"><i class="fab fa-android"></i></a>
                                        <a href="#" class="btn btn-glass btn-sm flex-grow-1" title="Windows"><i class="fab fa-windows"></i></a>
                                        <a href="#" class="btn btn-glass btn-sm flex-grow-1" title="Smart TV"><i class="fas fa-tv"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subscription Section -->
                <div id="subscription-section" class="content-section" style="display: none;">
                    <div class="p-4">
                        <div class="glass-card mb-4" data-aos="fade-up">
                            <h4 class="mb-4 text-gradient">
                                <i class="fas fa-tv me-2"></i>
                                Detalhes da Assinatura
                            </h4>
                            
                            <?php if ($subscription): ?>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="bento-item glass-panel-hover mb-3">
                                            <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Plano Atual</label>
                                            <span class="h5 fw-bold mb-0"><?php echo htmlspecialchars($subscription['plan_name']); ?></span>
                                        </div>
                                        <div class="bento-item glass-panel-hover">
                                            <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Valor Mensal</label>
                                            <span class="h5 fw-bold text-success mb-0">R$ <?php echo number_format($subscription['price'], 2, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bento-item glass-panel-hover mb-3">
                                            <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Data de Início</label>
                                            <span class="h5 fw-bold mb-0"><?php echo isset($subscription['created_at']) ? date('d/m/Y', strtotime($subscription['created_at'])) : 'N/A'; ?></span>
                                        </div>
                                        <div class="bento-item glass-panel-hover">
                                            <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Próximo Vencimento</label>
                                            <span class="h5 fw-bold mb-0"><?php echo isset($subscription['expires_at']) ? date('d/m/Y', strtotime($subscription['expires_at'])) : 'N/A'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php
                                $days_remaining = isset($subscription['expires_at']) ? ceil((strtotime($subscription['expires_at']) - time()) / (60 * 60 * 24)) : 0;
                                if ($days_remaining <= 7 && $days_remaining > 0): ?>
                                    <div class="alert bg-warning-subtle text-warning border-warning-subtle mt-4" data-aos="shake">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Sua assinatura vence em <?php echo $days_remaining; ?> dias. Renove agora!
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-3 mt-4">
                                    <button type="button" class="btn btn-premium" data-bs-toggle="modal" data-bs-target="#renewalModal">
                                        <i class="fas fa-sync-alt me-2"></i>
                                        Renovar Assinatura
                                    </button>
                                    <a href="#" class="btn btn-glass">
                                        <i class="fas fa-download me-2"></i>
                                        Baixar App
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="icon-box mx-auto mb-4" style="width: 80px; height: 80px; background: rgba(255,255,255,0.05); color: var(--purple-light); font-size: 2.5rem;">
                                        <i class="fas fa-tv"></i>
                                    </div>
                                    <h5>Nenhuma assinatura ativa</h5>
                                    <p class="text-muted mb-4">Escolha um plano para começar a aproveitar nossos serviços!</p>
                                    <a href="plans.php" class="btn btn-premium">
                                        <i class="fas fa-plus me-2"></i>
                                        Escolher Plano
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Referrals Section -->
                <div id="referrals-section" class="content-section" style="display: none;">
                    <div class="p-4">
                        <div class="row g-4">
                            <div class="col-md-8">
                                <div class="bento-card h-100" data-aos="fade-right">
                                    <h4 class="mb-4 text-gradient">
                                        <i class="fas fa-users me-2"></i>
                                        Sistema de Indicações
                                    </h4>
                                    
                                    <div class="alert bg-primary-5 text-primary border-primary-10 mb-4 rounded-4">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Como funciona:</strong> Compartilhe seu link único e ganhe <strong>1000 pontos</strong> quando seu amigo fizer o primeiro pagamento!
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-muted small text-uppercase opacity-75">Seu Link de Indicação</label>
                                        <div class="input-group glass-input-group">
                                            <input type="text" class="form-control bg-white-5 border-white-10 text-white rounded-start-4" id="referralLink" 
                                               value="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/ref.php?code=' . ($user['referral_code'] ?? $user['id']); ?>" readonly>
                                            <button class="btn btn-premium rounded-end-4" type="button" onclick="copyReferralLink()" title="Copiar link">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-muted small text-uppercase opacity-75">Seu Código de Indicação</label>
                                        <div class="input-group glass-input-group">
                                            <input type="text" class="form-control bg-white-5 border-white-10 text-white rounded-start-4" id="referralCode" 
                                               value="<?php echo $user['referral_code'] ?? 'Gerando...'; ?>" readonly>
                                            <button class="btn btn-premium rounded-end-4" type="button" onclick="copyReferralCode()" title="Copiar código">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted mt-2 d-block opacity-75">Seus amigos podem usar este código durante o cadastro</small>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-2 mb-4">
                                        <button class="btn btn-glass px-4 py-2" onclick="shareWhatsApp()" style="color: #25D366; border-color: rgba(37, 211, 102, 0.2);">
                                            <i class="fab fa-whatsapp me-2"></i> WhatsApp
                                        </button>
                                        <button class="btn btn-glass px-4 py-2" onclick="shareFacebook()" style="color: #1877F2; border-color: rgba(24, 119, 242, 0.2);">
                                            <i class="fab fa-facebook me-2"></i> Facebook
                                        </button>
                                        <button class="btn btn-glass px-4 py-2" onclick="shareEmail()">
                                            <i class="fas fa-envelope me-2"></i> Email
                                        </button>
                                    </div>
                                    
                                    <div class="referral-tips bento-card p-4 welcome-bento-card border-0" style="min-height: auto;">
                                        <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb me-2 text-warning"></i>Dicas para mais indicações:</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Compartilhe nas suas redes sociais</li>
                                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Envie para grupos de família e amigos</li>
                                            <li class="mb-0"><i class="fas fa-check-circle text-success me-2"></i>Mostre a qualidade dos canais e filmes</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="glass-card mb-4" data-aos="fade-left">
                                    <h5 class="mb-4 fw-bold">Estatísticas</h5>
                                    <div class="bento-item mb-3 text-center">
                                        <div class="h2 fw-bold text-gradient mb-0"><?php echo $referralsCount; ?></div>
                                        <div class="small text-muted text-uppercase fw-bold">Indicações</div>
                                    </div>
                                    <div class="bento-item mb-3 text-center">
                                        <div class="h2 fw-bold text-gradient mb-0"><?php echo $referralsCount * 1000; ?></div>
                                        <div class="small text-muted text-uppercase fw-bold">Pontos Acumulados</div>
                                    </div>
                                    <div class="bento-item text-center">
                                        <div class="ranking-position">
                                            <span class="h2 fw-bold text-warning mb-0">#<?php echo $dashboardData['points']['ranking_position'] ?? 'N/A'; ?></span>
                                            <small class="d-block text-muted text-uppercase fw-bold">Sua Posição</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="glass-card h-100 bg-gradient-dark" data-aos="fade-up">
                                    <h5 class="mb-4 fw-bold"><i class="fas fa-trophy me-2 text-warning"></i>Próxima Recompensa</h5>
                                    <div class="text-center p-3">
                                        <div class="reward-icon-container mb-4 position-relative d-inline-block">
                                            <div class="position-absolute top-50 start-50 translate-middle bg-primary rounded-circle opacity-20" style="width: 100px; height: 100px; filter: blur(30px);"></div>
                                            <i class="fas fa-gift fa-3x text-gradient-purple mb-3 position-relative"></i>
                                        </div>
                                        <h6 class="fw-bold mb-2">1 Mês de Plano VIP</h6>
                                        <div class="progress-info d-flex justify-content-between mb-2">
                                            <span class="small text-muted">Progresso</span>
                                            <span class="small fw-bold"><?php echo $referralsCount; ?> / 5 Indicações</span>
                                        </div>
                                        <div class="progress-premium mb-3">
                                            <div class="progress-bar-gradient h-100" style="width: <?php echo min(($referralsCount / 5) * 100, 100); ?>%"></div>
                                        </div>
                                        <p class="small text-muted mb-0">Indique mais <?php echo max(5 - $referralsCount, 0); ?> amigos para desbloquear seu prêmio!</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ranking Section -->
                <div id="ranking-section" class="content-section" style="display: none;">
                    <div class="p-4">
                        <div class="glass-card" data-aos="fade-up">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                                <h4 class="mb-0 text-gradient">
                                    <i class="fas fa-trophy me-2"></i>
                                    Ranking de Membros VIP
                                </h4>
                                
                                <div class="leaderboard-filters d-flex align-items-center gap-1 bg-white-5 p-1 rounded-pill">
                                    <button class="btn btn-sm btn-filter active" onclick="loadLeaderboard('all', this)" data-period="all">Sempre</button>
                                    <button class="btn btn-sm btn-filter" onclick="loadLeaderboard('month', this)" data-period="month">Mês</button>
                                    <button class="btn btn-sm btn-filter" onclick="loadLeaderboard('week', this)" data-period="week">Semana</button>
                                </div>

                                <button class="btn btn-glass btn-sm ms-md-auto" onclick="refreshLeaderboard()">
                                    <i class="fas fa-sync-alt me-1"></i> Atualizar
                                </button>
                            </div>
                            
                            <div id="leaderboard-content">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                    <p class="mt-3 text-muted">Carregando o ranking dos melhores membros...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rewards Section -->
                <div id="rewards-section" class="content-section" style="display: none;">
                    <div class="p-4">
                        <div class="glass-card" data-aos="fade-up">
                            <h4 class="mb-4 text-gradient">
                                <i class="fas fa-gift me-2"></i>
                                Recompensas Disponíveis
                            </h4>
                            
                            <?php if (!empty($rewards)): ?>
                                <div class="row g-4">
                                    <?php foreach ($rewards as $reward): ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="bento-item glass-panel-hover h-100 d-flex flex-column">
                                                <div class="icon-box mb-3" style="background: rgba(236, 72, 153, 0.1); color: var(--pink-accent);">
                                                    <i class="fas fa-gift"></i>
                                                </div>
                                                <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($reward['name']); ?></h6>
                                                <p class="text-muted small mb-4 flex-grow-1"><?php echo htmlspecialchars($reward['description']); ?></p>
                                                <div class="d-flex align-items-center justify-content-between mt-auto">
                                                    <div class="reward-points fw-bold text-warning"><?php echo $reward['points_required']; ?> pts</div>
                                                    <button class="btn btn-premium btn-sm py-1 px-3" onclick="redeemReward(<?php echo $reward['id']; ?>)" 
                                                            <?php echo (($user['points'] ?? 0) < $reward['points_required']) ? 'disabled' : ''; ?>>
                                                        <?php echo (($user['points'] ?? 0) >= $reward['points_required']) ? 'Resgatar' : 'Bloqueado'; ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="icon-box mx-auto mb-4" style="width: 80px; height: 80px; background: rgba(255,255,255,0.05); color: var(--pink-accent); font-size: 2.5rem;">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                    <h5>Nenhuma recompensa disponível</h5>
                                    <p class="text-muted">Continue acumulando pontos para desbloquear recompensas incríveis!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Section -->
                <div id="profile-section" class="content-section" style="display: none;">
                    <div class="p-4">
                        <div class="bento-card" data-aos="fade-up" style="justify-content: flex-start;">
                            <h4 class="mb-4 text-gradient">
                                <i class="fas fa-user me-2"></i>
                                Meu Perfil Premium
                            </h4>
                            
                            <div class="row g-4">
                                <div class="col-md-4 text-center">
                                    <div class="user-avatar shadow-lg mx-auto mb-3" style="width: 140px; height: 140px; font-size: 3.5rem; border-radius: 30px;">
                                        <?php echo mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                                    </div>
                                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                                    <p class="text-muted small mb-4 opacity-75"><?php echo htmlspecialchars($user['email']); ?></p>
                                    <div class="catalog-badge pulse d-inline-block">
                                        MEMBRO PREMIUM
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="bento-card p-4 border-0" style="min-height: auto; background: rgba(255,255,255,0.02) !important;">
                                                <label class="text-muted small text-uppercase fw-bold d-block mb-1 opacity-75">Telefone</label>
                                                <div class="fw-bold fs-5"><?php echo htmlspecialchars($user['phone'] ?? 'Não informado'); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="bento-card p-4 border-0" style="min-height: auto; background: rgba(255,255,255,0.02) !important;">
                                                <label class="text-muted small text-uppercase fw-bold d-block mb-1 opacity-75">Membro desde</label>
                                                <div class="fw-bold fs-5"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="bento-card p-4 border-0" style="min-height: auto; background: rgba(255,255,255,0.02) !important;">
                                                <label class="text-muted small text-uppercase fw-bold d-block mb-1 opacity-75">Código de Indicação</label>
                                                <div class="fw-bold fs-5 text-primary text-gradient"><?php echo $user['referral_code'] ?? 'N/A'; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="bento-card p-4 border-0" style="min-height: auto; background: rgba(255,255,255,0.02) !important;">
                                                <label class="text-muted small text-uppercase fw-bold d-block mb-1 opacity-75">Pontos Atuais</label>
                                                <div class="fw-bold fs-5 text-gradient-warning"><?php echo $user['points'] ?? 0; ?> pts</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 d-flex gap-3">
                                        <button class="btn btn-premium px-4">Editar Perfil</button>
                                        <button class="btn btn-glass px-4">Alterar Senha</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support Section -->
                <div id="support-section" class="content-section" style="display: none;">
                    <div class="p-4">
                        <div class="row g-4">
                            <div class="col-md-8">
                                <div class="glass-card h-100" data-aos="fade-right">
                                    <h4 class="mb-4 text-gradient">
                                        <i class="fas fa-headset me-2"></i>
                                        Central de Suporte 24h
                                    </h4>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="bento-item glass-panel-hover text-center p-4 h-100">
                                                <div class="icon-box mx-auto mb-3" style="background: rgba(37, 211, 102, 0.1); color: #25D366;">
                                                    <i class="fab fa-whatsapp"></i>
                                                </div>
                                                <h6 class="fw-bold">WhatsApp VIP</h6>
                                                <p class="text-muted small">Atendimento priorizado para assinantes.</p>
                                                <a href="#" class="btn btn-premium btn-sm w-100 mt-2">Falar agora</a>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="bento-item glass-panel-hover text-center p-4 h-100">
                                                <div class="icon-box mx-auto mb-3" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                                    <i class="fas fa-envelope"></i>
                                                </div>
                                                <h6 class="fw-bold">E-mail Técnico</h6>
                                                <p class="text-muted small">Para questões complexas ou faturas.</p>
                                                <a href="mailto:suporte@kmkziptv.com" class="btn btn-glass btn-sm w-100 mt-2">Enviar ticket</a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 bento-item glass-panel-hover">
                                        <h6 class="fw-bold mb-3"><i class="fas fa-question-circle me-2 text-primary"></i>Perguntas Frequentes</h6>
                                        <p class="small text-muted mb-0">Confira nossa base de conhecimento para soluções rápidas antes de abrir um chamado.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="glass-card h-100" data-aos="fade-left">
                                    <h5 class="mb-4 fw-bold">Apps & Players</h5>
                                    <div class="d-flex flex-column gap-3">
                                        <a href="#" class="bento-item glass-panel-hover text-decoration-none d-flex align-items-center gap-3 p-3">
                                            <i class="fab fa-android fa-2x text-success"></i>
                                            <div>
                                                <div class="fw-bold text-white">Android App</div>
                                                <div class="small text-muted">Versão 4.2.0</div>
                                            </div>
                                        </a>
                                        <a href="#" class="bento-item glass-panel-hover text-decoration-none d-flex align-items-center gap-3 p-3">
                                            <i class="fab fa-apple fa-2x text-white"></i>
                                            <div>
                                                <div class="fw-bold text-white">iOS / Apple TV</div>
                                                <div class="small text-muted">TestFlight</div>
                                            </div>
                                        </a>
                                        <a href="#" class="bento-item glass-panel-hover text-decoration-none d-flex align-items-center gap-3 p-3">
                                            <i class="fas fa-desktop fa-2x text-info"></i>
                                            <div>
                                                <div class="fw-bold text-white">Windows PC</div>
                                                <div class="small text-muted">Portable</div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Renovação -->
    <div class="modal fade" id="renewalModal" tabindex="-1" aria-labelledby="renewalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); color: white;">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title" id="renewalModalLabel">
                        <i class="fas fa-sync-alt me-2"></i>
                        Renovar Assinatura
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <h6 class="text-muted">Escolha o melhor plano para renovar sua assinatura</h6>
                    </div>
                    
                    <div class="row g-4">
                        <?php 
                        // Obter planos para o modal
                        $plansResponse = apiGetPlans();
                        $modalPlans = getApiResponseData($plansResponse);
                        
                        // Fallback se API não estiver disponível
                        if (!$modalPlans) {
                            $modalPlans = getFallbackData('plans');
                        }
                        
                        foreach ($modalPlans as $plan): 
                        ?>
                        <div class="col-lg-3 col-md-6">
                            <div class="card h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s ease;" 
                                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 30px rgba(147, 51, 234, 0.3)';" 
                                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                
                                <?php if (isset($plan['is_popular']) && $plan['is_popular']): ?>
                                <div class="position-absolute top-0 start-50 translate-middle">
                                    <span class="badge bg-warning text-dark px-3 py-2">
                                        <i class="fas fa-star me-1"></i>Mais Popular
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card-body text-center p-4">
                                    <h5 class="card-title text-white mb-3"><?php echo htmlspecialchars($plan['name']); ?></h5>
                                    
                                    <div class="mb-3">
                                        <span class="h2 text-primary">R$ <?php echo number_format($plan['price'], 2, ',', '.'); ?></span>
                                        <div class="text-muted small"><?php echo $plan['duration_months']; ?> <?php echo $plan['duration_months'] == 1 ? 'mês' : 'meses'; ?></div>
                                    </div>
                                    
                                    <?php if (!empty($plan['description'])): ?>
                                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($plan['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <ul class="list-unstyled text-start mb-4">
                                        <?php 
                                        $features = [];
                                        if (!empty($plan['features'])) {
                                            $features = is_string($plan['features']) ? json_decode($plan['features'], true) : $plan['features'];
                                            if (!$features) {
                                                $features = explode(',', $plan['features']);
                                            }
                                        }
                                        
                                        if (!$features || !is_array($features)) {
                                            $features = [
                                                'Mais de 500 canais',
                                                'Qualidade ' . ($plan['quality'] ?? 'HD'),
                                                'Até ' . ($plan['max_devices'] ?? 1) . ' dispositivo(s)',
                                                'Suporte 24/7'
                                            ];
                                        }
                                        
                                        foreach (array_slice($features, 0, 4) as $feature): 
                                            $feature = trim($feature);
                                            if (!empty($feature)):
                                        ?>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <small><?php echo htmlspecialchars($feature); ?></small>
                                        </li>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </ul>
                                    
                                    <button type="button" class="btn btn-primary w-100" 
                                            onclick="selectPlan(<?php echo $plan['id']; ?>, '<?php echo addslashes($plan['name']); ?>', '<?php echo number_format($plan['price'], 2, ',', '.'); ?>')">
                                        <i class="fas fa-credit-card me-2"></i>
                                        Renovar com este Plano
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <div class="w-100 text-center">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-2"></i>
                            Todos os planos incluem garantia de 7 dias
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Função para selecionar plano no modal
        function selectPlan(planId, planName, planPrice) {
            if (confirm(`Deseja renovar sua assinatura com o plano ${planName} por R$ ${planPrice}?`)) {
                // Redirecionar para payment.php com o plano selecionado
                window.location.href = `payment.php?subscription=${planId}&renewal=true`;
            }
        }
        
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('toggleSidebar');
            const navLinks = document.querySelectorAll('.nav-link[data-section]');
            const sections = document.querySelectorAll('.content-section');
            
            // Toggle sidebar
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
            
            // Navigation functionality
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Hide all sections
                    sections.forEach(section => section.style.display = 'none');
                    
                    // Show target section
                    const sectionName = this.dataset.section;
                    const targetSection = document.getElementById(sectionName + '-section');
                    if (targetSection) {
                        targetSection.style.display = 'block';
                        
                        // Load dynamic content based on section
                        if (sectionName === 'ranking') {
                            loadLeaderboard();
                        }
                    }
                    
                    // Update page title
                    const pageTitle = document.querySelector('.top-navbar h4');
                    pageTitle.textContent = this.querySelector('span').textContent;
                });
            });
            
            // Initialize chart
            initializeChart();
            
            // Mobile responsiveness
            if (window.innerWidth <= 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        });
        
        // Chart initialization
        function initializeChart() {
            const ctx = document.getElementById('activityChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                        datasets: [{
                            label: 'Atividade',
                            data: [65, 59, 80, 81, 56, 95],
                            borderColor: 'rgb(147, 51, 234)',
                            backgroundColor: function(context) {
                                const chart = context.chart;
                                const {ctx, chartArea} = chart;
                                if (!chartArea) return null;
                                const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                                gradient.addColorStop(0, 'rgba(147, 51, 234, 0)');
                                gradient.addColorStop(1, 'rgba(147, 51, 234, 0.3)');
                                return gradient;
                            },
                            borderWidth: 3,
                            pointBackgroundColor: 'white',
                            pointBorderColor: 'rgb(147, 51, 234)',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                titleFont: { family: 'Outfit', size: 14 },
                                bodyFont: { family: 'Inter', size: 13 },
                                padding: 12,
                                cornerRadius: 10,
                                displayColors: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(255,255,255,0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    color: 'rgba(255,255,255,0.5)',
                                    font: { family: 'Inter', size: 11 }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: 'rgba(255,255,255,0.5)',
                                    font: { family: 'Inter', size: 11 }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Referral functions
        function copyReferralLink() {
            const linkInput = document.getElementById('referralLink');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Show success message
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
        
        function shareWhatsApp() {
            const link = document.getElementById('referralLink').value;
            const message = `🎬 Olá! Venha conhecer a KMKZ IPTV, a melhor plataforma de streaming! \n\n✨ +500 canais em HD/4K\n🎯 Filmes e séries ilimitados\n⚽ Esportes premium ao vivo\n📱 Funciona em qualquer dispositivo\n\n🎁 Use meu código de indicação e ganhe benefícios especiais: ${link}`;
            const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }
        
        function shareFacebook() {
            const link = document.getElementById('referralLink').value;
            const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}&quote=${encodeURIComponent('Venha conhecer a KMKZ IPTV! A melhor plataforma de streaming com +500 canais!')}`;
            window.open(facebookUrl, '_blank');
        }
        
        function copyReferralCode() {
            const codeInput = document.getElementById('referralCode');
            codeInput.select();
            codeInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Show success feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
        
        function shareEmail() {
            const link = document.getElementById('referralLink').value;
            const subject = 'Convite KMKZ IPTV - Streaming Premium';
            const body = `Olá!\n\nVenha conhecer a KMKZ IPTV, a melhor plataforma de streaming!\n\n✨ Mais de 500 canais em HD/4K\n🎯 Filmes e séries ilimitados\n⚽ Esportes premium ao vivo\n📱 Funciona em qualquer dispositivo\n\n🎁 Use meu link de indicação e ganhe benefícios especiais:\n${link}\n\nNão perca essa oportunidade!\n\nAbraços!`;
            
            const mailtoUrl = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
            window.location.href = mailtoUrl;
        }
        
        function redeemReward(rewardId) {
            if (confirm('Deseja realmente resgatar esta recompensa?')) {
                // Show loading state
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = 'Processando...';
                button.disabled = true;
                
                // Real API call to redeem the reward
                fetch('api/points.php?action=redeem', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ reward_id: rewardId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Success feedback
                        button.textContent = 'Resgatado';
                        button.classList.remove('btn-primary');
                        button.classList.add('btn-success');
                        
                        alert('Recompensa resgatada com sucesso!');
                        
                        // Reload page to update points balance and status
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        // Error feedback
                        alert('Erro ao resgatar: ' + (data.error?.message || data.message || 'Erro desconhecido'));
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erro de conexão ao processar o resgate.');
                    button.textContent = originalText;
                    button.disabled = false;
                });
            }
        }


        let currentPeriod = 'all';

        function refreshLeaderboard() {
            const activeBtn = document.querySelector('.btn-filter.active');
            const period = activeBtn ? activeBtn.dataset.period : 'all';
            loadLeaderboard(period, activeBtn);
        }



        function loadLeaderboard(period = 'all', btn = null) {
            const container = document.getElementById('leaderboard-content');
            if (!container) return;

            currentPeriod = period;

            // Update active state of buttons
            if (btn) {
                document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            } else {
                // Find button by period if no button element provided
                const targetBtn = document.querySelector(`.btn-filter[data-period="${period}"]`);
                if (targetBtn) {
                    document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
                    targetBtn.classList.add('active');
                }
            }

            // Show loading state
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-3 text-muted">Carregando o ranking dos melhores membros...</p>
                </div>
            `;

            fetch(`api/points.php?action=leaderboard&period=${period}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const leaderboard = data.data.leaderboard;
                        const userPosition = data.data.user_position;
                        
                        if (!leaderboard || leaderboard.length === 0) {
                            container.innerHTML = `
                                <div class="text-center py-5">
                                    <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Nenhum dado de ranking disponível no momento.</p>
                                </div>
                            `;
                            return;
                        }

                        let html = `
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle admin-table mb-0">
                                    <thead>
                                        <tr class="text-muted small text-uppercase fw-bold">
                                            <th class="border-0 ps-4">Posição</th>
                                            <th class="border-0">Membro</th>
                                            <th class="border-0">Pontos</th>
                                            <th class="border-0 text-end pe-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;

                        leaderboard.forEach(member => {
                            const isCurrentUser = member.id == <?php echo $userId; ?>;
                            const rankClass = member.position <= 3 ? `rank-${member.position}` : '';
                            const medalIcon = member.position === 1 ? '🥇' : (member.position === 2 ? '🥈' : (member.position === 3 ? '🥉' : ''));
                            
                            html += `
                                <tr class="${isCurrentUser ? 'bg-primary-5 border-start border-primary border-4' : ''}">
                                    <td class="ps-4">
                                        <div class="rank-badge ${rankClass}">
                                            ${medalIcon ? medalIcon : member.position}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-sm rounded-circle bg-white-5 text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                ${member.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-white">${member.name}</div>
                                                <small class="text-muted small">${member.email}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-warning">${member.points} pts</span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="badge ${member.points > 1000 ? 'bg-warning text-dark' : 'bg-white-5 text-white'} rounded-pill px-3">
                                            ${member.points > 1000 ? 'VIP Platinum' : 'Membro VIP'}
                                        </span>
                                    </td>
                                </tr>
                            `;
                        });

                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;

                        // Se o usuário não estiver no top, mostrar sua posição atual separadamente
                        if (userPosition && userPosition.position > leaderboard.length) {
                            html += `
                                <div class="mt-4 p-3 rounded-4 bg-primary-10 border border-primary-20">
                                    <div class="d-flex justify-content-between align-items-center px-2">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rank-badge">#${userPosition.position}</div>
                                            <div class="fw-bold">Sua Posição Atual</div>
                                        </div>
                                        <div class="fw-bold text-warning">${userPosition.points} pts</div>
                                    </div>
                                </div>
                            `;
                        }

                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `
                            <div class="alert bg-danger-subtle text-danger border-danger-subtle m-3">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Erro ao carregar o ranking: ${data.message || 'Erro desconhecido'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = `
                        <div class="alert bg-danger-subtle text-danger border-danger-subtle m-3">
                            <i class="fas fa-wifi-slash me-2"></i>
                            Erro de conexão ao carregar o ranking.
                        </div>
                    `;
                });
        }

        function refreshLeaderboard() {
            loadLeaderboard(currentPeriod);
        }
    </script>
    
    <script>
        // Dashboard specific scripts
        document.addEventListener('DOMContentLoaded', function() {
            // Any specific dashboard logic that isn't in ui-enhancements.js
            
            // Listen for section changes to load ranking
            document.querySelectorAll('.admin-nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    const section = this.getAttribute('data-section');
                    if (section === 'ranking') {
                        // Small delay to allow section transition
                        setTimeout(() => loadLeaderboard('all'), 100);
                    }
                });
            });

            // Initial load if hash is ranking
            if (window.location.hash === '#ranking') {
                setTimeout(() => loadLeaderboard('all'), 500);
            }

            // Re-init AOS if content changes
            if (window.AOS) {
                AOS.refresh();
            }
        });
    </script>
    
    <!-- AOS JS -->
    <script defer src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Bootstrap JavaScript -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- UI Enhancements -->
    <script defer src="assets/js/ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/assets/js/ui-enhancements.js') ?: time(); ?>"></script>
</body>
</html>
