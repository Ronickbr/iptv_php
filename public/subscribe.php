<?php
session_start();
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';

// Verificar se foi indicado por alguém
$incomingReferrerName = $_GET['referred_by'] ?? null;
$incomingReferralCode = $_GET['ref_code'] ?? null;
if ($incomingReferrerName && $incomingReferralCode) {
    $_SESSION['referrer_name'] = $incomingReferrerName;
    $_SESSION['referral_code'] = $incomingReferralCode;
}

$referrerName = $_GET['referred_by'] ?? $_SESSION['referrer_name'] ?? null;
$referralCode = $_GET['ref_code'] ?? $_SESSION['referral_code'] ?? null;
$showReferralInfo = !empty($referrerName) && !empty($referralCode);

// Verificar se foi passado um ID de plano
$planId = isset($_GET['plan']) ? (int)$_GET['plan'] : 1;
$planResponse = apiGetPlan($planId);
$plan = getApiResponseData($planResponse);

// Se a API falhar, usar dados de fallback
if (!$plan) {
    $fallbackPlans = getFallbackData('plans');
    $plan = null;
    
    // Encontrar o plano pelo ID
    foreach ($fallbackPlans as $fallbackPlan) {
        if ($fallbackPlan['id'] == $planId) {
            $plan = $fallbackPlan;
            break;
        }
    }
    
    // Se ainda não encontrou, usar o primeiro plano disponível
    if (!$plan && !empty($fallbackPlans)) {
        $plan = $fallbackPlans[0];
    }
}

// Se ainda não há plano, redirecionar
if (!$plan) {
    header('Location: index.php');
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/subscribe.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validações
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Todos os campos obrigatórios devem ser preenchidos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } elseif ($password !== $confirmPassword) {
        $error = 'As senhas não coincidem.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } else {
        $db = getDB();
        if (!$db) {
            $error = 'Erro de conexão com o banco de dados.';
        } elseif (emailExists($db, $email)) {
            $error = 'Email já está em uso.';
        } else {
            $userId = createUser($db, $name, $email, $password, $phone);
            if (!$userId) {
                $error = 'Erro ao criar usuário.';
            } else {
                $subscriptionId = createSubscription($db, (int)$userId, (int)$planId, 'pix');
                if (!$subscriptionId) {
                    $error = 'Erro ao criar assinatura.';
                } else {
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_data'] = [
                        'id' => (int)$userId,
                        'name' => $name,
                        'email' => $email,
                        'role' => 'user'
                    ];
                    header('Location: payment.php?subscription=' . (int)$subscriptionId);
                    exit;
                }
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
    <title>Assinar <?php echo htmlspecialchars($plan['name']); ?> - KMKZ IPTV</title>
    <meta name="description" content="Crie sua conta e finalize sua assinatura com segurança. Ativação rápida e suporte 24/7.">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Assinar <?php echo htmlspecialchars($plan['name']); ?> - KMKZ IPTV">
    <meta property="og:description" content="Crie sua conta e finalize sua assinatura com segurança. Ativação rápida e suporte 24/7.">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($scheme . '://' . $host . '/assets/images/Logo.png', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#020617">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
    <style>
        .progress-steps .step .step-number {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-gray-400);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 10px;
            transition: all 0.4s ease;
        }
        .progress-steps .step.active .step-number {
            background: linear-gradient(135deg, var(--purple-primary), var(--pink-accent));
            color: white;
            box-shadow: 0 0 20px rgba(147, 51, 234, 0.4);
            border: none;
        }
        .progress-steps .step .step-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-gray-400);
        }
        .progress-steps .step.active .step-label {
            color: white;
        }
        .mesh-gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: var(--bg-dark);
            overflow: hidden;
        }
        .mesh-gradient::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 75% 25%, rgba(147, 51, 234, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 25% 75%, rgba(236, 72, 153, 0.15) 0%, transparent 40%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-dark" data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <!-- Mesh Gradient Background -->
    <div class="mesh-gradient"></div>

    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="74" height="40" decoding="async" fetchpriority="high">
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link glass-panel px-4 py-2" href="index.php" style="border-radius: 50px;">
                    <i class="fas fa-arrow-left me-2" aria-hidden="true"></i>Voltar
                </a>
            </div>
        </div>
    </nav>

    <main id="mainContent">

    <!-- Progress Indicator -->
    <div class="progress-container" style="margin-top: 120px; border: none; background: none;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8" data-aos="fade-down">
                    <div class="progress-steps d-flex justify-content-between mb-4" style="border: none; background: none; position: relative;">
                        <!-- Line connecting steps -->
                        <div style="position: absolute; top: 25px; left: 10%; right: 10%; height: 2px; background: rgba(255,255,255,0.1); z-index: 0;"></div>
                        
                        <div class="step active" style="z-index: 1;">
                            <div class="step-number">
                                <i class="fas fa-list-check"></i>
                            </div>
                            <div class="step-label">Escolher Plano</div>
                        </div>
                        <div class="step active" style="z-index: 1;">
                            <div class="step-number">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="step-label">Criar Conta</div>
                        </div>
                        <div class="step" style="z-index: 1;">
                            <div class="step-number">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="step-label">Pagamento</div>
                        </div>
                        <div class="step" style="z-index: 1;">
                            <div class="step-number">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="step-label">Confirmação</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscription Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="row">
                        <!-- Plan Summary -->
                        <div class="col-lg-4 mb-4" data-aos="fade-right">
                            <div class="sticky-top" style="top: 100px;">
                                <div class="glass-panel pricing-card enhanced p-4 <?php echo $plan['is_popular'] ? 'popular' : ''; ?>" style="border-radius: 24px;">
                                    <?php if ($plan['is_popular']): ?>
                                    <div class="popular-badge mb-3" style="background: linear-gradient(135deg, var(--purple-primary), var(--pink-accent)); padding: 5px 15px; border-radius: 50px; display: inline-block; font-size: 0.8rem; font-weight: bold;">
                                        <i class="fas fa-crown me-1"></i>Mais Popular
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="pricing-header text-center mb-4">
                                        <div class="plan-icon mb-3" style="font-size: 2.5rem; background: linear-gradient(135deg, var(--purple-primary), var(--pink-accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                                            <i class="fas fa-tv"></i>
                                        </div>
                                        <h4 class="plan-name mb-3" style="font-weight: 700;"><?php echo htmlspecialchars($plan['name']); ?></h4>
                                        <div class="price-display">
                                            <div class="price h2 mb-0" style="font-weight: 800;">
                                                <span class="currency" style="font-size: 1rem; vertical-align: super;">R$</span>
                                                <span class="amount"><?php echo number_format($plan['price'], 2, ',', '.'); ?></span>
                                            </div>
                                            <div class="period text-muted" style="font-size: 0.9rem;"><?php echo $plan['duration_months']; ?> <?php echo $plan['duration_months'] == 1 ? 'mês' : 'meses'; ?></div>
                                            
                                            <?php if (isset($plan['savings']) && $plan['savings'] > 0): ?>
                                            <div class="savings mt-2 px-3 py-1 d-inline-block" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 50px; font-size: 0.8rem; font-weight: 600;">
                                                <i class="fas fa-piggy-bank me-1"></i>
                                                Economize R$ <?php echo number_format($plan['savings'], 2, ',', '.'); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="pricing-features">
                                        <h6 class="features-title mb-3" style="font-weight: 600; border: none;">
                                            <i class="fas fa-star text-gradient me-2"></i>O que você terá:
                                        </h6>
                                        <ul class="feature-list" style="padding-left: 0; list-style: none;">
                                            <li class="mb-2 d-flex align-items-center"><i class="fas fa-check-circle text-success me-2"></i> <span><strong>500+</strong> canais em HD/4K</span></li>
                                            <li class="mb-2 d-flex align-items-center"><i class="fas fa-check-circle text-success me-2"></i> <span><strong>Filmes</strong> e séries ilimitados</span></li>
                                            <li class="mb-2 d-flex align-items-center"><i class="fas fa-check-circle text-success me-2"></i> <span><strong>Esportes</strong> premium ao vivo</span></li>
                                            <li class="mb-2 d-flex align-items-center"><i class="fas fa-check-circle text-success me-2"></i> <span><strong>Suporte</strong> técnico 24/7</span></li>
                                        </ul>
                                    </div>
                                    
                                    <div class="guarantee-badge mt-4 p-3 text-center" style="background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.2); border-radius: 12px; font-size: 0.85rem;">
                                        <i class="fas fa-shield-alt text-success me-2"></i>
                                        <span>Você escolhe o plano e segue para o pagamento com segurança</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Registration Form -->
                        <div class="col-lg-8" data-aos="fade-left">
                            <div class="glass-panel registration-card p-0" style="border-radius: 24px; overflow: hidden;">
                                <div class="card-header border-0" style="background: linear-gradient(135deg, rgba(147, 51, 234, 0.2), rgba(236, 72, 153, 0.2)); padding: 2.5rem;">
                                    <h3 class="mb-1" style="font-weight: 700;">
                                        <i class="fas fa-user-plus me-2 text-gradient"></i>
                                        Crie sua conta para continuar
                                    </h3>
                                    <p class="text-muted-light mb-0">Leva menos de 1 minuto. Depois, você escolhe a forma de pagamento.</p>
                                </div>
                                
                                <div class="card-body p-4 p-lg-5">
                                
                                <?php if ($showReferralInfo): ?>
                                <div class="glass-panel border-0 mb-4 p-3" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border-left: 4px solid var(--purple-primary) !important;">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 text-gradient">
                                            <i class="fas fa-gift fa-2x"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1" style="font-weight: 700;">Você foi indicado por um amigo!</h6>
                                            <p class="mb-0 small">Indicado por: <strong><?php echo htmlspecialchars($referrerName); ?></strong></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($error): ?>
                                <div class="alert glass-panel border-0 text-white mb-4" style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444 !important;">
                                    <i class="fas fa-exclamation-triangle me-2 text-danger"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($success): ?>
                                <div class="alert glass-panel border-0 text-white mb-4" style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981 !important;">
                                    <i class="fas fa-check-circle me-2 text-success"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                                <?php endif; ?>
                                
                                <form method="POST" class="enhanced-form needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="name" class="form-label small text-muted-light mb-2">
                                                <i class="fas fa-user me-2"></i>NOME COMPLETO
                                            </label>
                                            <input type="text" class="form-control enhanced-input" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                                   placeholder="Digite seu nome completo" required>
                                            <div class="invalid-feedback">Informe seu nome completo.</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-4">
                                            <label for="email" class="form-label small text-muted-light mb-2">
                                                <i class="fas fa-envelope me-2"></i>EMAIL
                                            </label>
                                            <input type="email" class="form-control enhanced-input" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                                   placeholder="seu@email.com" required>
                                            <div class="invalid-feedback">Informe um email válido.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="phone" class="form-label small text-muted-light mb-2">
                                            <i class="fas fa-phone me-2"></i>TELEFONE / WHATSAPP
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text border-0" style="background: rgba(255,255,255,0.03); color: var(--text-gray-400); border-radius: 12px 0 0 12px;"><i class="fab fa-whatsapp"></i></span>
                                            <input type="tel" class="form-control enhanced-input" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                                   placeholder="(11) 99999-9999" style="border-radius: 0 12px 12px 0;">
                                        </div>
                                        <small class="text-muted small mt-1 d-block">
                                            Para suporte técnico rápido via WhatsApp
                                        </small>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <label for="password" class="form-label small text-muted-light mb-2">
                                                <i class="fas fa-lock me-2"></i>SENHA
                                            </label>
                                            <div class="input-group">
                                                <input type="password" class="form-control enhanced-input" id="password" name="password" 
                                                       minlength="6" placeholder="Mínimo 6 caracteres" required>
                                                <button class="btn border-0" type="button" id="togglePassword" style="background: rgba(255,255,255,0.03); color: var(--text-gray-400); border-radius: 0 12px 12px 0; border: 2px solid var(--border-white-10); border-left: none;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="password-strength mt-2">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small text-muted-light">Força da senha:</span>
                                                    <span class="strength-text small fw-bold"></span>
                                                </div>
                                                <div class="strength-meter">
                                                    <div class="strength-bar"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-4">
                                            <label for="confirm_password" class="form-label small text-muted-light mb-2">
                                                <i class="fas fa-lock me-2"></i>CONFIRMAR SENHA
                                            </label>
                                            <input type="password" class="form-control enhanced-input" id="confirm_password" 
                                                   name="confirm_password" minlength="6" placeholder="Repita a senha" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="form-check custom-check">
                                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                            <label class="form-check-label small text-muted-light" for="terms">
                                                Li e aceito os <a href="#" class="text-gradient fw-bold text-decoration-none">Termos de Uso</a> 
                                                e <a href="#" class="text-gradient fw-bold text-decoration-none">Política de Privacidade</a>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid pt-3">
                                        <button type="submit" class="btn btn-primary btn-lg enhanced-btn shadow-lg" id="submitBtn">
                                            <span class="btn-text">
                                                <i class="fas fa-rocket me-2"></i>
                                                Continuar para o Pagamento
                                            </span>
                                            <span class="btn-loading d-none">
                                                <i class="fas fa-spinner fa-spin me-2"></i>
                                                Processando...
                                            </span>
                                        </button>
                                    </div>
                                </form>
                                
                                </div>
                                
                                <div class="card-footer border-0 p-4" style="background: rgba(255,255,255,0.02);">
                                    <div class="security-badges">
                                        <div class="row g-2 text-center">
                                            <div class="col-3">
                                                <div class="security-item">
                                                    <i class="fas fa-lock text-muted small d-block mb-1"></i>
                                                    <span class="small text-muted" style="font-size: 0.65rem;">SSL SEGURO</span>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="security-item">
                                                    <i class="fas fa-user-shield text-muted small d-block mb-1"></i>
                                                    <span class="small text-muted" style="font-size: 0.65rem;">PROTEGIDO</span>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="security-item">
                                                    <i class="fas fa-headset text-muted small d-block mb-1"></i>
                                                    <span class="small text-muted" style="font-size: 0.65rem;">SUPORTE</span>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="security-item">
                                                    <i class="fas fa-medal text-muted small d-block mb-1"></i>
                                                    <span class="small text-muted" style="font-size: 0.65rem;">GARANTIA</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </main>

    <!-- Scripts -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script defer src="assets/js/ui-enhancements.js?v=<?php echo filemtime(__DIR__ . '/assets/js/ui-enhancements.js') ?: time(); ?>"></script>
    
    <script>
        // Enhanced Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        // Show loading state
                        const submitBtn = document.getElementById('submitBtn');
                        const btnText = submitBtn.querySelector('.btn-text');
                        const btnLoading = submitBtn.querySelector('.btn-loading');
                        
                        // Check password confirmation
                        const password = document.getElementById('password').value;
                        const confirmPassword = document.getElementById('confirm_password').value;
                        
                        if (password !== confirmPassword) {
                            document.getElementById('confirm_password').setCustomValidity('As senhas não coincidem');
                        } else {
                            document.getElementById('confirm_password').setCustomValidity('');
                        }
                        
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        } else {
                            // Show loading state
                            btnText.classList.add('d-none');
                            btnLoading.classList.remove('d-none');
                            submitBtn.disabled = true;
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        // Enhanced Phone mask
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
                    e.target.value = value;
                }
            });
        }
        
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthBar = document.querySelector('.strength-bar');
                const strengthText = document.querySelector('.strength-text');
                
                let strength = 0;
                if (password.length >= 6) strength += 25;
                if (password.match(/[a-z]/)) strength += 25;
                if (password.match(/[A-Z]/)) strength += 25;
                if (password.match(/[0-9]/)) strength += 25;
                
                strengthBar.style.width = strength + '%';
                
                if (strength < 50) {
                    strengthBar.style.background = '#dc3545';
                    strengthText.textContent = 'Fraca';
                } else if (strength < 75) {
                    strengthBar.style.background = '#ffc107';
                    strengthText.textContent = 'Média';
                } else {
                    strengthBar.style.background = '#28a745';
                    strengthText.textContent = 'Forte';
                }
            });
        }
        
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', function() {
                const password = document.getElementById('password');
                const icon = this.querySelector('i');
                
                if (password.type === 'password') {
                    password.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    password.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
        
        // Real-time password confirmation validation
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirmPassword = this.value;
                
                if (password !== confirmPassword) {
                    this.setCustomValidity('As senhas não coincidem');
                    this.classList.add('is-invalid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                    if (confirmPassword.length > 0) {
                        this.classList.add('is-valid');
                    }
                }
            });
        }
        
        // Real-time input validation
        const inputs = document.querySelectorAll('.enhanced-input');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.checkValidity()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        });
        
        if (window.AOS) {
            AOS.init({
                duration: 800,
                once: true,
                offset: 50
            });
        }
    </script>
</body>
</html>
