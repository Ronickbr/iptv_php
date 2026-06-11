<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = strtok($_SERVER['REQUEST_URI'] ?? '/login.php', '?');
$canonicalUrl = $scheme . '://' . $host . $path;
$ga4Id = getenv('GA4_MEASUREMENT_ID') ?: '';
$metaPixelId = getenv('META_PIXEL_ID') ?: '';
$resetToken = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$isResetMode = $resetToken !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KMKZ IPTV</title>
    <meta name="description" content="Acesse sua conta KMKZ IPTV para gerenciar assinatura, pagamentos e recompensas.">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#020617">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/ui-enhancements.css" rel="stylesheet">
    
    <!-- AOS - Animate On Scroll -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Google Fonts - Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body data-ga4-id="<?php echo htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8'); ?>" data-meta-pixel-id="<?php echo htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8'); ?>">
    <a class="skip-link" href="#mainContent">Pular para o conteúdo</a>
    <!-- Mesh Gradient Background -->
    <div class="mesh-gradient"></div>

    <!-- Login Section -->
    <main id="mainContent">
    <section class="min-vh-100 d-flex align-items-center py-5">
        <div class="container" data-aos="zoom-in" data-aos-duration="1000">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">
                    <div class="glass-card p-4 p-md-5">
                        <div class="text-center mb-5">
                            <div class="mb-4 logo-floating">
                                <img src="assets/images/Logo.png" alt="KMKZ IPTV" width="147" height="80" style="height: 80px; width: auto;" decoding="async" fetchpriority="high">
                            </div>
                            <?php if ($isResetMode): ?>
                                <h2 class="fw-bold mb-2">Redefinir senha</h2>
                                <p class="text-muted">Crie uma nova senha para sua conta</p>
                            <?php else: ?>
                                <h2 class="fw-bold mb-2">Bem-vindo</h2>
                                <p class="text-muted">Acesse sua experiência premium</p>
                            <?php endif; ?>
                        </div>

                        <!-- Alerts -->
                        <div id="alertContainer"></div>

                        <?php if ($isResetMode): ?>
                            <!-- Reset Password Form -->
                            <form id="resetPasswordForm" class="needs-validation" novalidate>
                                <input type="hidden" id="resetToken" value="<?php echo htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8'); ?>">

                                <div class="mb-4">
                                    <label for="newPassword" class="form-label small text-uppercase fw-bold opacity-75">
                                        Nova senha
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0 border-white border-opacity-10">
                                            <i class="fas fa-lock text-pink-accent"></i>
                                        </span>
                                        <input type="password" class="form-control glass-input enhanced-input border-start-0 border-end-0" id="newPassword" name="newPassword"
                                               placeholder="Mínimo 6 caracteres" required minlength="6" autocomplete="new-password">
                                        <button class="btn btn-glass border-white border-opacity-10" type="button" id="toggleNewPassword">
                                            <i class="fas fa-eye text-muted"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Sua senha deve ter pelo menos 6 caracteres.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="confirmPassword" class="form-label small text-uppercase fw-bold opacity-75">
                                        Confirmar senha
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0 border-white border-opacity-10">
                                            <i class="fas fa-lock text-pink-accent"></i>
                                        </span>
                                        <input type="password" class="form-control glass-input enhanced-input border-start-0 border-end-0" id="confirmPassword" name="confirmPassword"
                                               placeholder="Repita a nova senha" required minlength="6" autocomplete="new-password">
                                        <button class="btn btn-glass border-white border-opacity-10" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye text-muted"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Confirme sua nova senha.
                                    </div>
                                </div>

                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-premium btn-lg py-3" id="resetSubmitBtn">
                                        <span class="btn-text">
                                            Alterar Senha <i class="fas fa-arrow-right ms-2"></i>
                                        </span>
                                        <span class="btn-loading d-none">
                                            <i class="fas fa-spinner fa-spin me-2"></i> Salvando...
                                        </span>
                                    </button>
                                </div>
                            </form>

                            <div class="text-center">
                                <a href="login.php" class="text-pink-accent fw-bold text-decoration-none hover-glow">Voltar para o login</a>
                            </div>
                        <?php else: ?>
                            <!-- Login Form -->
                            <form id="loginForm" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label for="email" class="form-label small text-uppercase fw-bold opacity-75">
                                        Email
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0 border-white border-opacity-10">
                                            <i class="fas fa-envelope text-pink-accent"></i>
                                        </span>
                                        <input type="email" class="form-control glass-input enhanced-input border-start-0" id="email" name="email"
                                               placeholder="seu@email.com" required autocomplete="email">
                                    </div>
                                    <div class="invalid-feedback">
                                        Por favor, informe um email válido.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="password" class="form-label small text-uppercase fw-bold opacity-75">
                                        Senha
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent border-end-0 border-white border-opacity-10">
                                            <i class="fas fa-lock text-pink-accent"></i>
                                        </span>
                                        <input type="password" class="form-control glass-input enhanced-input border-start-0 border-end-0" id="password" name="password"
                                               placeholder="Sua senha" required autocomplete="current-password">
                                        <button class="btn btn-glass border-white border-opacity-10" type="button" id="togglePassword">
                                            <i class="fas fa-eye text-muted"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Por favor, informe sua senha.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check custom-checkbox">
                                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                        <label class="form-check-label small opacity-75" for="remember">Lembrar-me</label>
                                    </div>
                                    <a href="#" class="small text-decoration-none text-pink-accent fw-bold hover-glow" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                                        Esqueceu a senha?
                                    </a>
                                </div>

                                <div class="d-grid mb-4">
                                    <button type="submit" class="btn btn-premium btn-lg py-3" id="submitBtn">
                                        <span class="btn-text">
                                            Entrar Agora <i class="fas fa-arrow-right ms-2"></i>
                                        </span>
                                        <span class="btn-loading d-none">
                                            <i class="fas fa-spinner fa-spin me-2"></i> Entrando...
                                        </span>
                                    </button>
                                </div>
                            </form>

                            <div class="text-center">
                                <p class="mb-0 text-muted small">Ainda não é membro?</p>
                                <a href="subscribe.php" class="text-pink-accent fw-bold text-decoration-none hover-glow">Assinar Plano Premium</a>
                            </div>

                            <!-- Forgot Password Modal -->
                            <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content glass-card p-0">
                                        <div class="modal-header border-0">
                                            <h5 class="modal-title fw-bold" id="forgotPasswordModalLabel">Recuperar senha</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body pt-0">
                                            <p class="text-muted small mb-4">Informe seu email. Se ele estiver cadastrado, enviaremos um link para redefinir sua senha.</p>
                                            <form id="forgotPasswordForm" class="needs-validation" novalidate>
                                                <div class="mb-3">
                                                    <label for="forgotEmail" class="form-label small text-uppercase fw-bold opacity-75">Email</label>
                                                    <input type="email" class="form-control glass-input enhanced-input" id="forgotEmail" name="forgotEmail" placeholder="seu@email.com" required autocomplete="email">
                                                    <div class="invalid-feedback">Por favor, informe um email válido.</div>
                                                </div>
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-premium py-3" id="forgotSubmitBtn">
                                                        <span class="btn-text">
                                                            Enviar link <i class="fas fa-paper-plane ms-2"></i>
                                                        </span>
                                                        <span class="btn-loading d-none">
                                                            <i class="fas fa-spinner fa-spin me-2"></i> Enviando...
                                                        </span>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    </main>
    
    <!-- Bootstrap JS -->
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
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        } else {
                            // Show loading state
                            const submitBtn = form.id === 'resetPasswordForm'
                                ? document.getElementById('resetSubmitBtn')
                                : form.id === 'forgotPasswordForm'
                                    ? document.getElementById('forgotSubmitBtn')
                                    : document.getElementById('submitBtn');

                            if (submitBtn) {
                                const btnText = submitBtn.querySelector('.btn-text');
                                const btnLoading = submitBtn.querySelector('.btn-loading');

                                if (btnText && btnLoading) {
                                    btnText.classList.add('d-none');
                                    btnLoading.classList.remove('d-none');
                                }
                                submitBtn.disabled = true;
                            }
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
        
        function escapeHtml(value) {
            const div = document.createElement('div');
            div.innerText = String(value ?? '');
            return div.innerHTML;
        }

        async function requestJson(url, options = {}) {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...(options.headers || {})
                },
                ...options
            });

            const responseText = await response.text();
            let result = null;

            try {
                result = responseText ? JSON.parse(responseText) : {};
            } catch (error) {
                throw new Error(`Resposta invalida do servidor (${response.status})`);
            }

            if (!response.ok) {
                const errorMessage =
                    result?.error?.message ||
                    result?.error ||
                    result?.message ||
                    `Erro HTTP ${response.status}`;
                throw new Error(errorMessage);
            }

            return result;
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

        function setupPasswordToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            if (!btn) return;
            btn.addEventListener('click', function() {
                const input = document.getElementById(inputId);
                const icon = this.querySelector('i');
                if (!input || !icon) return;

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }

        setupPasswordToggle('toggleNewPassword', 'newPassword');
        setupPasswordToggle('toggleConfirmPassword', 'confirmPassword');
        
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
        
        // Função para mostrar alertas
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'error' ? 'alert-danger' : type === 'success' ? 'alert-success' : 'alert-info';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    ${escapeHtml(message)}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        // Manipulador do formulário de login
        const loginForm = document.getElementById('loginForm');
        if (loginForm) loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!this.checkValidity()) {
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            // Mostrar loading
            btnText.classList.add('d-none');
            btnLoading.classList.remove('d-none');
            submitBtn.disabled = true;
            
            const formData = {
                action: 'login',
                email: document.getElementById('email').value,
                password: document.getElementById('password').value
            };
            
            try {
                const result = await requestJson('auth.php', {
                    method: 'POST',
                    body: JSON.stringify(formData)
                });

                if (result.success) {
                    showAlert('Login realizado com sucesso!', 'success');

                    const role = result.data?.user?.role;
                    if (result.data?.logged_in && role) {
                        setTimeout(() => {
                            window.location.href = role === 'admin' ? 'admin.php' : 'dashboard.php';
                        }, 600);
                    } else {
                        const checkResult = await requestJson('auth.php?action=check');
                        
                        if (checkResult.success && checkResult.data.logged_in && checkResult.data.user?.role) {
                            const checkRole = checkResult.data.user.role;
                            setTimeout(() => {
                                window.location.href = checkRole === 'admin' ? 'admin.php' : 'dashboard.php';
                            }, 600);
                        }
                    }
                } else {
                    showAlert(result.error || 'Erro ao fazer login', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showAlert(error.message || 'Erro de conexão. Tente novamente.', 'error');
            } finally {
                // Esconder loading
                btnText.classList.remove('d-none');
                btnLoading.classList.add('d-none');
                submitBtn.disabled = false;
            }
        });

        // Manipulador do formulário de recuperação de senha
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        if (forgotPasswordForm) forgotPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!this.checkValidity()) return;

            const submitBtn = document.getElementById('forgotSubmitBtn');
            const btnText = submitBtn?.querySelector('.btn-text');
            const btnLoading = submitBtn?.querySelector('.btn-loading');
            if (btnText && btnLoading) {
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
            }
            if (submitBtn) submitBtn.disabled = true;

            try {
                const email = document.getElementById('forgotEmail').value;
                const result = await requestJson('auth.php?action=forgot_password', {
                    method: 'POST',
                    body: JSON.stringify({ email })
                });

                if (result.success) {
                    showAlert(result.message || 'Se o email estiver cadastrado, você receberá um link de redefinição.', 'success');
                    const modalEl = document.getElementById('forgotPasswordModal');
                    const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl) : null;
                    if (modal) modal.hide();
                    this.reset();
                    this.classList.remove('was-validated');

                    const resetUrl = result.data?.reset_url;
                    if (resetUrl) {
                        showAlert('Link de redefinição (modo debug): ' + resetUrl, 'info');
                    }
                } else {
                    showAlert(result.message || result.error || 'Não foi possível processar sua solicitação.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showAlert(error.message || 'Erro de conexão. Tente novamente.', 'error');
            } finally {
                if (btnText && btnLoading) {
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                }
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        // Manipulador do formulário de redefinição de senha
        const resetPasswordForm = document.getElementById('resetPasswordForm');
        if (resetPasswordForm) resetPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!this.checkValidity()) return;

            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (newPassword !== confirmPassword) {
                showAlert('As senhas não conferem.', 'error');
                return;
            }

            const token = document.getElementById('resetToken').value;
            if (!token) {
                showAlert('Token inválido. Solicite um novo link.', 'error');
                return;
            }

            const submitBtn = document.getElementById('resetSubmitBtn');
            const btnText = submitBtn?.querySelector('.btn-text');
            const btnLoading = submitBtn?.querySelector('.btn-loading');
            if (btnText && btnLoading) {
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');
            }
            if (submitBtn) submitBtn.disabled = true;

            try {
                const result = await requestJson('auth.php?action=reset_password', {
                    body: JSON.stringify({ token, password: newPassword })
                });

                if (result.success) {
                    showAlert(result.message || 'Senha alterada com sucesso! Faça login novamente.', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php?message=password_reset_success';
                    }, 800);
                } else {
                    showAlert(result.message || result.error || 'Não foi possível redefinir sua senha.', 'error');
                }
            } catch (error) {
                console.error('Erro:', error);
                showAlert(error.message || 'Erro de conexão. Tente novamente.', 'error');
            } finally {
                if (btnText && btnLoading) {
                    btnText.classList.remove('d-none');
                    btnLoading.classList.add('d-none');
                }
                if (submitBtn) submitBtn.disabled = false;
            }
        });
        
        // Verificar se já está logado ao carregar a página
        window.addEventListener('load', async function() {
            // Verificar se há mensagem de logout na URL
            const urlParams = new URLSearchParams(window.location.search);
            const message = urlParams.get('message');
            
            if (message === 'logout_success') {
                showAlert('Logout realizado com sucesso! Faça login novamente para acessar sua conta.', 'success');
                // Limpar a URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            if (message === 'password_reset_success') {
                showAlert('Senha alterada com sucesso! Faça login para continuar.', 'success');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            try {
                const result = await requestJson('auth.php?action=check');
                
                if (result.success && result.data.logged_in) {
                    // Redirecionar baseado no tipo de usuário
                    if (result.data.user.role === 'admin') {
                        window.location.href = 'admin.php';
                    } else {
                        window.location.href = 'dashboard.php';
                    }
                }
            } catch (error) {
                console.error('Erro ao verificar login:', error);
            }
        });
        
        // Smooth scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observe elements for animation
        document.querySelectorAll('.registration-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>
