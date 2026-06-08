<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
$db = getDB();
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/payment.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';

// Verificar se usuário está logado
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user_data'];
$userDb = getUserById($db, (int)$user['id']);
if ($userDb) {
    $user = array_merge($user, $userDb);
    syncUserPointsBalance($db, (int)$user['id'], 90);
    $user = getUserById($db, (int)$user['id']) ?: $user;
}
$subscriptionId = isset($_GET['subscription']) ? (int)$_GET['subscription'] : 0;
$isRenewal = isset($_GET['renewal']) && $_GET['renewal'] === 'true';

if (!$subscriptionId) {
    header('Location: index.php');
    exit;
}

if ($isRenewal) {
    // Para renovação, buscar dados do plano diretamente
    $stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$subscriptionId]);
    $plan = $stmt->fetch();
    
    if (!$plan) {
        header('Location: index.php');
        exit;
    }
    
    // Criar dados de assinatura temporária para exibição
    $subscription = [
        'id' => 0, // Temporário
        'plan_id' => $plan['id'],
        'plan_name' => $plan['name'],
        'price' => $plan['price'],
        'duration_months' => $plan['duration_months'],
        'user_name' => $user['name'],
        'email' => $user['email'],
        'user_id' => $user['id']
    ];
} else {
    // Buscar dados da assinatura existente
    $stmt = $db->prepare("
        SELECT s.*, p.name as plan_name, p.price, p.duration_months, u.name as user_name, u.email 
        FROM subscriptions s 
        JOIN plans p ON s.plan_id = p.id 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$subscriptionId]);
    $subscription = $stmt->fetch();
    
    if (!$subscription) {
        header('Location: index.php');
        exit;
    }
}

// Gerar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de segurança inválido. Recarregue a página e tente novamente.";
    } else {
        // Validar método de pagamento
        $allowedMethods = ['pix', 'credit_card', 'debit_card', 'boleto', 'paypal'];
        $paymentMethod = $_POST['payment_method'] ?? 'pix';
        $usePoints = isset($_POST['use_points']) ? (int)$_POST['use_points'] : 0;
        $allowedUsePoints = [0, 500, 1000];
        if (!in_array($usePoints, $allowedUsePoints, true)) {
            $usePoints = 0;
        }
        
        if (!in_array($paymentMethod, $allowedMethods)) {
            $error = "Método de pagamento inválido.";
        } else {
            try {
                $amountToCharge = (float)$subscription['price'];
                if ($paymentMethod === 'pix' && $usePoints > 0) {
                    syncUserPointsBalance($db, (int)$user['id'], 90);
                    $freshUser = getUserById($db, (int)$user['id']);
                    $availablePoints = (int)($freshUser['points'] ?? 0);
                    if ($availablePoints < $usePoints) {
                        throw new Exception('Pontos insuficientes para aplicar desconto.');
                    }

                    $discount = $usePoints / 100.0;
                    $maxDiscount = $amountToCharge * 0.20;
                    if ($discount > $maxDiscount) {
                        $discount = $maxDiscount;
                        $usePoints = (int)floor($discount * 100);
                    }
                    $minPayable = 5.00;
                    if (($amountToCharge - $discount) < $minPayable) {
                        $discount = max(0.0, $amountToCharge - $minPayable);
                        $usePoints = (int)floor($discount * 100);
                    }

                    $amountToCharge = max(0.0, $amountToCharge - $discount);
                } else {
                    $usePoints = 0;
                }

                $db->beginTransaction();
                if ($isRenewal) {
                    // Para renovação, criar nova assinatura
                    $stmt = $db->prepare("
                        INSERT INTO subscriptions (user_id, plan_id, status, created_at, updated_at) 
                        VALUES (?, ?, 'pending', NOW(), NOW())
                    ");
                    $stmt->execute([$user['id'], $subscription['plan_id']]);
                    $newSubscriptionId = $db->lastInsertId();
                    
                    // Processar pagamento com a nova assinatura
                    $paymentResult = processPayment($db, $newSubscriptionId, $amountToCharge, $paymentMethod);
                } else {
                    // Processar pagamento da assinatura existente
                    $paymentResult = processPayment($db, $subscriptionId, $amountToCharge, $paymentMethod);
                }
                
                if ($paymentResult) {
                    if ($paymentMethod === 'pix' && $usePoints > 0) {
                        $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
                        $stmt->execute([$usePoints, (int)$user['id']]);
                        insertPointsHistory(
                            $db,
                            (int)$user['id'],
                            null,
                            -$usePoints,
                            'payment_discount',
                            'Desconto no pagamento via pontos',
                            (int)$paymentResult
                        );
                        syncUserPointsBalance($db, (int)$user['id'], 90);
                    }

                    $db->commit();
                    $success = true;
                    // Regenerar token após sucesso
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } else {
                    $db->rollBack();
                    $error = "Erro ao processar o pagamento. Tente novamente.";
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = "Erro ao processar o pagamento. Tente novamente.";
                error_log("Payment error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento - KMKZ IPTV</title>
    <meta name="description" content="Finalize seu pagamento com segurança e ative sua assinatura.">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#020617">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
</head>
<body data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <div class="mesh-gradient"></div>

    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="83" height="45" class="d-inline-block align-top" decoding="async" fetchpriority="high">
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="glass-panel px-3 py-1 d-flex align-items-center">
                    <div class="user-avatar me-2">
                        <i class="fas fa-user-circle text-gradient" style="font-size: 1.5rem;"></i>
                    </div>
                    <span class="navbar-text p-0 fw-medium">
                        <?php echo htmlspecialchars($subscription['user_name']); ?>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Payment Section -->
    <section class="py-5" style="margin-top: 80px;" id="mainContent">
        <div class="container">
            <?php if (isset($error)): ?>
            <!-- Error Message -->
            <div class="row justify-content-center mb-4">
                <div class="col-lg-8">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <!-- Success Message -->
            <div class="row justify-content-center" data-aos="zoom-in">
                <div class="col-lg-7">
                    <div class="glass-panel p-5 text-center ultra-blur">
                        <div class="mb-4">
                            <div class="success-icon-container mb-4">
                                <i class="fas fa-check-circle text-gradient pulse-animation" style="font-size: 5rem;"></i>
                            </div>
                            <h1 class="display-5 fw-bold mb-3">Pagamento Confirmado!</h1>
                            <p class="text-muted-light fs-5 mb-4">
                                Sua experiência premium está pronta. Assinatura ativada com sucesso!
                            </p>
                        </div>
                        
                        <div class="glass-card text-start mb-4">
                            <h5 class="fw-bold mb-3"><i class="fas fa-rocket me-2 text-gradient"></i>Próximos Passos:</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="step-badge me-3">1</div>
                                        <span>Instruções enviadas para seu email</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="step-badge me-3">2</div>
                                        <span>Baixe o App oficial abaixo</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="step-badge me-3">3</div>
                                        <span>Use seus dados de login KMKZ</span>
                                    </div>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="step-badge me-3">4</div>
                                        <span>Suporte VIP 24h disponível</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="glass-card p-3 h-100 shimmer">
                                    <i class="fab fa-android text-gradient mb-2" style="font-size: 2rem;"></i>
                                    <h6 class="mb-1">Android</h6>
                                    <small class="text-muted">APK Direto</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="glass-card p-3 h-100 shimmer">
                                    <i class="fas fa-tv text-gradient mb-2" style="font-size: 2rem;"></i>
                                    <h6 class="mb-1">Smart TV</h6>
                                    <small class="text-muted">Samsung/LG</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="glass-card p-3 h-100 shimmer">
                                    <i class="fas fa-headset text-gradient mb-2" style="font-size: 2rem;"></i>
                                    <h6 class="mb-1">Suporte</h6>
                                    <small class="text-muted">WhatsApp VIP</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex flex-wrap justify-content-center gap-3">
                            <a href="dashboard.php" class="btn btn-primary btn-cta">
                                <i class="fas fa-tachometer-alt me-2"></i>Ir para o Painel
                            </a>
                            <a href="index.php" class="btn btn-glass px-4">
                                <i class="fas fa-home me-2"></i>Início
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Payment Form -->
            <div class="row">
                <!-- Order Summary -->
                <div class="col-lg-4 mb-4" data-aos="fade-right">
                    <div class="glass-panel p-4 sticky-top" style="top: 100px;">
                        <h4 class="fw-bold mb-4">
                            <i class="fas fa-receipt text-gradient me-2"></i>
                            Resumo do Pedido
                        </h4>
                        
                        <div class="glass-card mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted-light">Plano Selecionado</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($subscription['plan_name']); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted-light">Período</span>
                                <span><?php echo $subscription['duration_months']; ?> <?php echo $subscription['duration_months'] == 1 ? 'mês' : 'meses'; ?></span>
                            </div>
                            <hr class="border-glass">
                            <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-5">Total:</span>
                                <span class="h3 fw-bold text-gradient mb-0">
                                    R$ <?php echo number_format($subscription['price'], 2, ',', '.'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="glass-panel p-3 bg-opacity-10 border-success mb-3">
                            <div class="d-flex align-items-center text-success">
                                <i class="fas fa-shield-check fs-4 me-3"></i>
                                <div class="small">
                                    <strong>Checkout protegido</strong><br>
                                    Conexão segura e confirmação rápida
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Methods -->
                <div class="col-lg-8" data-aos="fade-left">
                    <div class="glass-panel p-4">
                        <h4 class="fw-bold mb-4">
                            <i class="fas fa-wallet text-gradient me-2"></i>
                            Escolha como pagar
                        </h4>
                        
                        <form method="POST" id="payment-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="payment-grid mb-4">
                                <div class="row g-3">
                                    <!-- PIX -->
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="payment_method" id="pix" value="pix" checked>
                                        <label class="glass-card w-100 p-4 h-100 text-center cursor-pointer payment-label" for="pix">
                                            <i class="fas fa-qrcode text-gradient d-block mb-3" style="font-size: 2.5rem;"></i>
                                            <h5 class="fw-bold mb-1">PIX</h5>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle mb-2">Instantâneo</span>
                                            <p class="small text-muted-light mb-0">Confirmação rápida, 24/7</p>
                                        </label>
                                    </div>
                                    
                                    <!-- Credit Card -->
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="payment_method" id="credit_card" value="credit_card">
                                        <label class="glass-card w-100 p-4 h-100 text-center cursor-pointer payment-label" for="credit_card">
                                            <i class="fas fa-credit-card text-gradient d-block mb-3" style="font-size: 2.5rem;"></i>
                                            <h5 class="fw-bold mb-1">Cartão de Crédito</h5>
                                            <span class="badge bg-purple-subtle text-purple border border-purple-subtle mb-2">Até 12x</span>
                                            <p class="small text-muted-light mb-0">Pague como preferir</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="glass-card p-4 mb-4">
                                <!-- PIX Details -->
                                <div id="pix-details" class="payment-details-view animate__animated animate__fadeIn">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="info-icon me-3">
                                            <i class="fas fa-info-circle text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold">Pagamento via PIX</h6>
                                            <p class="small text-muted-light mb-0">
                                                Ao confirmar, você verá um QR Code e um código “Copia e Cola” para pagar no app do seu banco.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Credit Card Details -->
                                <div id="credit_card-details" class="payment-details-view animate__animated animate__fadeIn" style="display: none;">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="info-icon me-3">
                                            <i class="fas fa-credit-card text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold">Pagamento com cartão</h6>
                                            <p class="small text-muted-light mb-0">
                                                Você será direcionado para um checkout seguro para concluir a compra.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="glass-card p-4 mb-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-1">Usar Pontos no Pagamento (PIX)</h6>
                                        <div class="small text-muted-light">Saldo atual: <span class="fw-bold text-warning"><?php echo (int)($user['points'] ?? 0); ?> pts</span></div>
                                    </div>
                                    <div class="badge bg-white bg-opacity-10 border border-white border-opacity-10">100 pts = R$ 1</div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <input type="radio" class="btn-check" name="use_points" id="use_points_0" value="0" checked>
                                        <label class="btn btn-glass w-100 py-2" for="use_points_0">Não usar</label>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="radio" class="btn-check" name="use_points" id="use_points_500" value="500">
                                        <label class="btn btn-glass w-100 py-2" for="use_points_500">500 pts (R$ 5)</label>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="radio" class="btn-check" name="use_points" id="use_points_1000" value="1000">
                                        <label class="btn btn-glass w-100 py-2" for="use_points_1000">1000 pts (R$ 10)</label>
                                    </div>
                                </div>

                                <div class="small text-muted-light mt-3">
                                    Limites: até 20% do valor do pagamento e mantendo no mínimo R$ 5,00 a pagar.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-cta w-100 py-3 shimmer">
                                <i class="fas fa-lock me-2"></i>
                                Finalizar Pagamento (R$ <?php echo number_format($subscription['price'], 2, ',', '.'); ?>)
                            </button>
                            
                            <div class="text-center mt-4">
                                <div class="d-flex justify-content-center align-items-center gap-3">
                                    <img src="https://logodownload.org/wp-content/uploads/2014/07/visa-logo-1.png" height="20" alt="Visa" class="grayscale opacity-50">
                                    <img src="https://logodownload.org/wp-content/uploads/2014/07/mastercard-logo.png" height="20" alt="Mastercard" class="grayscale opacity-50">
                                    <img src="https://logodownload.org/wp-content/uploads/2020/10/pix-logo-2.png" height="20" alt="Pix" class="grayscale opacity-50">
                                </div>
                                <p class="small text-muted-light mt-3 mb-0">
                                    Ambiente seguro autenticado com SSL de 256 bits
                                </p>
                            </div>
                            </div> <!-- col-lg-8 -->
                        </div> <!-- row -->
                    <?php endif; ?>
        </div>
    </section>

    <!-- Libraries -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Seleção de método de pagamento
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Esconder todos os detalhes com animação
                document.querySelectorAll('.payment-details-view').forEach(detail => {
                    detail.style.display = 'none';
                });
                
                // Mostrar detalhes selecionados
                const selectedId = this.id + '-details';
                const selectedDetails = document.getElementById(selectedId);
                if (selectedDetails) {
                    selectedDetails.style.display = 'block';
                }

                // Efeito visual nos labels
                document.querySelectorAll('.payment-label').forEach(label => {
                    label.classList.remove('selected-payment');
                });
                this.nextElementSibling.classList.add('selected-payment');

                const usePointsInputs = document.querySelectorAll('input[name="use_points"]');
                const disablePoints = this.value !== 'pix';
                usePointsInputs.forEach(i => {
                    i.disabled = disablePoints;
                });
                if (disablePoints) {
                    const none = document.getElementById('use_points_0');
                    if (none) {
                        none.checked = true;
                    }
                }
            });
        });

        const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
        if (selectedPayment) {
            const usePointsInputs = document.querySelectorAll('input[name="use_points"]');
            const disablePoints = selectedPayment.value !== 'pix';
            usePointsInputs.forEach(i => {
                i.disabled = disablePoints;
            });
        }
    </script>
    
    <!-- UI Enhancements -->
    <script defer src="assets/js/ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/assets/js/ui-enhancements.js') ?: time(); ?>"></script>

    <style>
        .payment-label {
            cursor: pointer;
            transition: var(--transition-smooth);
            border: 1px solid var(--border-glass);
        }
        
        .payment-label:hover {
            background: var(--surface-glass-hover);
            border-color: var(--purple-light);
            transform: translateY(-3px);
        }
        
        .btn-check:checked + .payment-label {
            background: rgba(147, 51, 234, 0.1);
            border-color: var(--purple-primary);
            box-shadow: var(--shadow-glow);
        }

        .step-badge {
            width: 24px;
            height: 24px;
            background: var(--purple-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .grayscale {
            filter: grayscale(100%);
            transition: all 0.3s ease;
        }

        .grayscale:hover {
            filter: grayscale(0%);
            opacity: 1 !important;
        }

        .border-glass {
            border-color: var(--border-glass) !important;
        }

        .text-muted-light {
            color: var(--text-gray-400) !important;
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-icon-container {
            display: inline-block;
            position: relative;
        }

        .success-icon-container::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            background: var(--purple-primary);
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.3;
            z-index: -1;
        }
    </style>
</body>
</html>
