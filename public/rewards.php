<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar se usuário está logado (simulação)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'João Silva';
    $_SESSION['user_email'] = 'joao@email.com';
}

$db = getDB();
$userId = $_SESSION['user_id'];
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/rewards.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';

// Buscar dados do usuário
$user = getUserById($db, $userId);
if (!$user) {
    $stmt = $db->prepare("INSERT INTO users (id, name, email, password, points) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE points = points");
    $stmt->execute([1, 'João Silva', 'joao@email.com', password_hash('123456', PASSWORD_DEFAULT), 250]);
    $user = getUserById($db, $userId);
}

syncUserPointsBalance($db, $userId, 90);
$user = getUserById($db, $userId) ?: $user;

// Buscar todos os prêmios
$rewards = getRewards($db);

// Buscar histórico de resgates
$redemptions = [];
if (tableExists($db, 'user_rewards')) {
    $stmt = $db->prepare("
        SELECT ur.*, r.name as reward_name, r.description
        FROM user_rewards ur
        JOIN rewards r ON ur.reward_id = r.id
        WHERE ur.user_id = ?
        ORDER BY ur.redeemed_at DESC
    ");
    $stmt->execute([$userId]);
    $redemptions = $stmt->fetchAll();
} elseif (tableExists($db, 'reward_redemptions')) {
    $stmt = $db->prepare("
        SELECT rr.*, r.name as reward_name, r.description
        FROM reward_redemptions rr
        JOIN rewards r ON rr.reward_id = r.id
        WHERE rr.user_id = ?
        ORDER BY rr.created_at DESC
    ");
    $stmt->execute([$userId]);
    $redemptions = $stmt->fetchAll();
}

// Processar resgate de prêmio
if (isset($_POST['action']) && $_POST['action'] === 'redeem' && isset($_POST['reward_id'])) {
    $rewardId = (int)$_POST['reward_id'];

    $result = redeemRewardWithResult($db, $userId, $rewardId);
    if (!empty($result['success'])) {
        $_SESSION['success_message'] = $result['message'] ?? 'Prêmio resgatado com sucesso!';
    } else {
        $_SESSION['error_message'] = $result['message'] ?? 'Erro ao resgatar prêmio. Tente novamente.';
    }
    
    header('Location: rewards.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prêmios - KMKZ IPTV</title>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#020617">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
</head>
<body data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <!-- Mesh Gradient Background -->
    <div class="mesh-gradient-container">
        <div class="mesh-gradient"></div>
    </div>

    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="74" height="40" decoding="async" fetchpriority="high">
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir menu">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-grid-2 me-2"></i>Painel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="rewards.php">
                            <i class="fas fa-gift me-2"></i>Loja de Prêmios
                        </a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center gap-3">
                    <div class="points-badge">
                        <i class="fas fa-star me-2"></i>
                        <span><?php echo $user['points']; ?> Pontos</span>
                    </div>
                    
                    <div class="dropdown">
                        <button class="btn btn-glass rounded-pill dropdown-toggle px-3" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <?php echo htmlspecialchars($user['name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end glass-panel mt-2 border-0 shadow-lg">
                            <li><a class="dropdown-item py-2" href="dashboard.php"><i class="fas fa-tachometer-alt me-2 opacity-75"></i>Dashboard</a></li>
                            <li><a class="dropdown-item py-2" href="#"><i class="fas fa-user-cog me-2 opacity-75"></i>Perfil</a></li>
                            <li><hr class="dropdown-divider opacity-25"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Rewards Content -->
    <main class="py-5" id="mainContent">
        <div class="container">
            <!-- Hero Header -->
            <div class="row mb-5 pt-4">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="display-4 fw-bold mb-3 text-gradient">Loja de Prêmios</h1>
                    <p class="lead text-muted">Troque seus pontos acumulados por benefícios exclusivos e assinaturas grátis.</p>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message']) || isset($_SESSION['error_message'])): ?>
            <div class="row mb-4">
                <div class="col-lg-6 mx-auto">
                    <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="glass-panel p-3 border-success border-opacity-25 d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                            <i class="fas fa-check text-success"></i>
                        </div>
                        <div><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="glass-panel p-3 border-danger border-opacity-25 d-flex align-items-center">
                        <div class="bg-danger bg-opacity-10 p-2 rounded-circle me-3">
                            <i class="fas fa-exclamation text-danger"></i>
                        </div>
                        <div><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Rewards Grid -->
            <div class="row g-4 mb-5">
                <div class="col-12 mb-2">
                    <h3 class="fw-bold d-flex align-items-center">
                        <i class="fas fa-shopping-bag text-primary me-3"></i>
                        Ofertas Disponíveis
                    </h3>
                </div>
                
                <?php foreach ($rewards as $reward): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="glass-panel h-100 p-4 d-flex flex-column glass-panel-hover">
                        <div class="text-center mb-4">
                            <div class="reward-icon mb-4">
                                <?php
                                $icons = [
                                    'Mês Grátis' => 'fas fa-calendar-plus',
                                    'Desconto' => 'fas fa-percentage',
                                    'Upgrade' => 'fas fa-arrow-up',
                                    'Brinde' => 'fas fa-gift'
                                ];
                                $icon = 'fas fa-gift';
                                foreach ($icons as $key => $value) {
                                    if (stripos($reward['name'], $key) !== false) {
                                        $icon = $value;
                                        break;
                                    }
                                }
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($reward['name']); ?></h4>
                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($reward['description']); ?></p>
                        </div>
                        
                        <div class="mt-auto text-center pt-3">
                            <div class="reward-price mb-4">
                                <?php echo $reward['points_required']; ?><span class="unit">pts</span>
                            </div>
                            
                            <?php if ($user['points'] >= $reward['points_required']): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="redeem">
                                <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold enhanced-btn" 
                                        onclick="return confirm('Confirmar resgate de <?php echo $reward['points_required']; ?> pontos?')">
                                    Resgatar <i class="fas fa-shopping-cart ms-2"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <div class="w-100">
                                <button class="btn btn-glass w-100 py-3 rounded-pill opacity-50 cursor-not-allowed" disabled>
                                    <i class="fas fa-lock me-2"></i> Pontos Insuficientes
                                </button>
                                <div class="mt-2 small text-muted">
                                    Faltam <?php echo $reward['points_required'] - $user['points']; ?> pontos
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Redemption History -->
            <?php if (!empty($redemptions)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="glass-panel p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h4 class="fw-bold mb-0">
                                <i class="fas fa-history text-primary me-3"></i>
                                Seus Resgates
                            </h4>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="border-0 opacity-50 small text-uppercase">Prêmio</th>
                                        <th class="border-0 opacity-50 small text-uppercase">Custo</th>
                                        <th class="border-0 opacity-50 small text-uppercase">Data</th>
                                        <th class="border-0 opacity-50 small text-uppercase text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($redemptions as $redemption): ?>
                                    <?php
                                        $pointsCost = $redemption['points_spent'] ?? $redemption['points_used'] ?? 0;
                                        $dateValue = $redemption['redeemed_at'] ?? $redemption['created_at'] ?? null;
                                        $statusValue = $redemption['status'] ?? null;
                                    ?>
                                    <tr class="align-middle">
                                        <td class="py-3">
                                            <div class="fw-bold"><?php echo htmlspecialchars($redemption['reward_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($redemption['description']); ?></div>
                                        </td>
                                        <td class="py-3">
                                            <span class="text-primary fw-bold">-<?php echo (int)$pointsCost; ?> pts</span>
                                        </td>
                                        <td class="py-3 text-muted">
                                            <?php if ($dateValue): ?>
                                                <?php echo date('d/m/Y', strtotime($dateValue)); ?>
                                                <div class="small opacity-50"><?php echo date('H:i', strtotime($dateValue)); ?></div>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 text-end">
                                            <?php if ($statusValue === 'redeemed'): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill">
                                                    <i class="fas fa-clock me-1"></i> Ativo
                                                </span>
                                            <?php elseif ($statusValue === 'used'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill">
                                                    <i class="fas fa-check-circle me-1"></i> Aplicado
                                                </span>
                                            <?php elseif ($statusValue === 'expired'): ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2 rounded-pill">
                                                    <i class="fas fa-hourglass-end me-1"></i> Expirado
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill">
                                                    <i class="fas fa-check-circle me-1"></i> Concluído
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="assets/js/ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/assets/js/ui-enhancements.js') ?: time(); ?>"></script>
    <script>
        // Navbar effect on scroll
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                document.querySelector('.custom-navbar').classList.add('scrolled');
            } else {
                document.querySelector('.custom-navbar').classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
</body>
</html>
